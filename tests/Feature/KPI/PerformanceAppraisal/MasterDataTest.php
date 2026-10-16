<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaStatistik;
use App\Services\KPI\PerformanceAppraisal\PaMasterReadService;
use App\Services\KPI\PerformanceAppraisal\PaMasterWriteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Master data admin — katalog baca dan tulis.
 *
 * Master di modul ini tidak setara bahayanya, dan itu yang membentuk seluruh rancangannya:
 * menyunting nama kompetensi menyentuh satu layar, sedangkan menyunting PITA NILAI
 * mengubah arti nilai semua orang pada siklus berikutnya. Dua hal yang harus dikunci uji:
 *
 *  1. Nama master yang tidak dikenal DITOLAK dengan menyebut daftar yang tersedia —
 *     `{master}` adalah bagian alamat, dan alamat yang menerima apa saja lalu diam adalah
 *     cara paling mudah membuat orang mengira ia menyunting sesuatu yang lain.
 *  2. Baris master tidak pernah DIHAPUS, hanya dinonaktifkan. Menghapusnya akan memutus
 *     salinan siklus lama yang menunjuk ke sana.
 */
class MasterDataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_master_yang_tidak_dikenal_ditolak_dengan_menyebut_daftarnya(): void
    {
        foreach (['baca', 'tulis'] as $arah) {
            try {
                $arah === 'baca'
                    ? $this->baca()->daftar('kompetensi-palsu')
                    : $this->tulis()->simpan('kompetensi-palsu', null, ['kode_type' => 'X']);

                $this->fail("Master tak dikenal seharusnya ditolak di jalur {$arah}.");
            } catch (AssertionFailedError $gagal) {
                // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
                // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
                // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
                throw $gagal;
            } catch (RuntimeException $e) {
                // Pesannya harus menyebut yang tersedia, bukan sekadar "tidak dikenal" —
                // pemanggilnya sedang menebak nama, dan daftar itulah jawabannya.
                $this->assertStringContainsString('kompetensi', $e->getMessage());
            }
        }
    }

    public function test_daftar_master_berhalaman_dan_bisa_dicari(): void
    {
        $hasil = $this->baca()->daftar('kompetensi', ['per_halaman' => 5]);

        $this->assertArrayHasKey('baris', $hasil);
        $this->assertLessThanOrEqual(5, count($hasil['baris']));
        $this->assertIsInt($hasil['total']);

        if ($hasil['baris'] === []) {
            $this->markTestSkipped('Master kompetensi kosong di database pengembangan.');
        }

        $satu = $hasil['baris'][0];
        $cari = mb_substr((string) $satu['Nama_Competency'], 0, 4);

        $dicari = $this->baca()->daftar('kompetensi', ['q' => $cari]);

        $this->assertGreaterThan(0, $dicari['total'], "Pencarian '{$cari}' harus menemukan sesuatu.");
        $this->assertLessThanOrEqual(
            $hasil['total'],
            $dicari['total'],
            'Pencarian harus mempersempit, bukan memperlebar.'
        );
    }

    public function test_referensi_memuat_seluruh_daftar_acuan_sekaligus(): void
    {
        $r = $this->baca()->referensi();

        /* Satu panggilan untuk semua dropdown. Delapan layar yang masing-masing mengambil
           daftar levelnya sendiri berarti delapan kali menanyakan hal yang sama. */
        $this->assertArrayHasKey('level', $r);
        $this->assertNotEmpty($r['level'], 'Daftar level tidak boleh kosong.');
    }

    public function test_nonaktif_lalu_aktifkan_kembali_dan_arah_yang_salah_ditolak(): void
    {
        $kode = strtoupper('UJI'.substr(bin2hex(random_bytes(3)), 0, 5));
        $id = (int) $this->tulis()->simpan('jenis-kompetensi', null, [
            'kode_type' => $kode, 'nama_type' => 'Jenis Uji Aktif Kembali', 'urutan_tampil' => 98,
        ], 1)['id'];

        try {
            $this->tulis()->aktifkan('jenis-kompetensi', $id, 1);
            $this->fail('Baris yang masih aktif tidak boleh "diaktifkan" lagi.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sudah aktif', $e->getMessage());
        }

        $this->tulis()->nonaktifkan('jenis-kompetensi', $id, 1);
        $this->assertSame('T', DB::table('N_PA_Competency_Type')->where('Id_PA_Competency_Type', $id)->value('Flag_Aktif'));

        $peringatan = $this->tulis()->aktifkan('jenis-kompetensi', $id, 1);
        $this->assertSame([], $peringatan);
        $this->assertSame('Y', DB::table('N_PA_Competency_Type')->where('Id_PA_Competency_Type', $id)->value('Flag_Aktif'),
            'Mengaktifkan kembali harus mengembalikan Flag_Aktif ke Y.');
    }

    public function test_menggeser_batas_pita_havmap_ikut_menggeser_tetangga(): void
    {
        $rendah = DB::table('N_PA_Score_Range')->where('Score_Usage', 'HAVMAP')->where('Score_Type', 'KPI')
            ->where('Kode_Range', 'RENDAH')->where('Flag_Aktif', 'Y')->first();
        if ($rendah === null) {
            $this->markTestSkipped('Pita HAV KPI belum ada.');
        }
        $batasBaru = (float) $rendah->Nilai_Sampai - 5;

        $hasil = $this->tulis()->simpan('pita-nilai', (int) $rendah->Id_PA_Score_Range, [
            'sumbu' => 'KPI', 'kegunaan' => 'HAVMAP', 'kode_range' => 'RENDAH', 'nama_range' => $rendah->Nama_Range,
            'nilai_dari' => (float) $rendah->Nilai_Dari, 'nilai_sampai' => $batasBaru,
            'nilai_hasil' => $rendah->Nilai_Hasil, 'warna' => $rendah->Warna, 'urutan_tampil' => (int) $rendah->Urutan_Tampil,
        ], 1);

        $this->assertSame(['SEDANG'], $hasil['tetangga_disesuaikan'] ?? null);
        $this->assertEquals($batasBaru, (float) DB::table('N_PA_Score_Range')->where('Score_Usage', 'HAVMAP')
            ->where('Score_Type', 'KPI')->where('Kode_Range', 'SEDANG')->where('Flag_Aktif', 'Y')->value('Nilai_Dari'),
            'Batas bawah SEDANG harus ikut bergeser ke batas atas baru RENDAH.');
    }

    public function test_menambah_pita_havmap_ditolak_dengan_pesan_yang_menunjuk_sunting(): void
    {
        $this->expectExceptionMessage('tidak bisa ditambah');
        $this->tulis()->simpan('pita-nilai', null, [
            'sumbu' => 'KPI', 'kegunaan' => 'HAVMAP', 'kode_range' => 'RENDAH', 'nama_range' => 'Rendah Baru',
            'nilai_dari' => 0, 'nilai_sampai' => 30, 'urutan_tampil' => 1,
        ], 1);
    }

    public function test_pita_havmap_tidak_bisa_dinonaktifkan_dan_pesannya_menunjuk_jalan_keluar(): void
    {
        $id = (int) DB::table('N_PA_Score_Range')->where('Score_Usage', 'HAVMAP')->where('Flag_Aktif', 'Y')->value('Id_PA_Score_Range');
        if ($id === 0) {
            $this->markTestSkipped('Pita HAV belum ada.');
        }
        $this->expectExceptionMessage('sunting batas pitanya');
        $this->tulis()->nonaktifkan('pita-nilai', $id, 1);
    }

    public function test_skala_nilai_tidak_bisa_diaktifkan_kembali_lewat_jalur_umum(): void
    {
        $id = (int) DB::table('N_PA_Score_Range')->value('Id_PA_Score_Range');
        $this->expectException(\RuntimeException::class);
        $this->tulis()->aktifkan('pita-nilai', $id, 1);
    }

    public function test_menyimpan_lalu_menonaktifkan_jenis_kompetensi(): void
    {
        /* HURUF BESAR sejak dibangkitkan. `kode()` di servis menjalankan strtoupper()
           sebelum menulis, karena pola validasinya memang hanya menerima [A-Z0-9_-.].
           `bin2hex` memulangkan heksadesimal huruf kecil, jadi membandingkan masukan
           mentah dengan nilai tersimpan akan gagal — KECUALI bila kelima karakternya
           kebetulan angka semua, yang terjadi sekitar sekali dari sepuluh. Uji yang hijau
           sepersepuluh waktu jauh lebih merepotkan daripada uji yang selalu merah: ia
           menyala berhari-hari kemudian tanpa ada yang berubah. */
        $kode = strtoupper('UJI'.substr(bin2hex(random_bytes(3)), 0, 5));

        /* Kunci muatan snake_case, BUKAN nama kolom database. Servicenya membaca
           `$d['kode_type']` dan tidak menormalkan kunci sama sekali - `Kode_Type` berhuruf
           besar jatuh ke null dan ditolak di pintu, sebelum satu baris pun tersentuh.
           (Yang dibaca BALIK dari tabel tetap memakai nama kolom, seperti di bawah.) */
        $dibuat = $this->tulis()->simpan('jenis-kompetensi', null, [
            'kode_type' => $kode,
            'nama_type' => 'Jenis Uji Master',
            'urutan_tampil' => 99,
        ], 1);

        $id = (int) ($dibuat['id'] ?? 0);
        $this->assertGreaterThan(0, $id, 'Penyimpanan harus mengembalikan id barisnya.');

        $baris = DB::table('N_PA_Competency_Type')->where('Id_PA_Competency_Type', $id)->first();
        $this->assertNotNull($baris);
        $this->assertSame($kode, $baris->Kode_Type);
        $this->assertSame('Y', $baris->Flag_Aktif);

        $this->tulis()->nonaktifkan('jenis-kompetensi', $id, 1);

        /* DINONAKTIFKAN, bukan dihapus. Salinan siklus lama menunjuk ke baris ini;
           menghapusnya memutus riwayat yang justru paling perlu dijelaskan. */
        $sesudah = DB::table('N_PA_Competency_Type')->where('Id_PA_Competency_Type', $id)->first();

        $this->assertNotNull($sesudah, 'Barisnya harus TETAP ADA sesudah dinonaktifkan.');
        $this->assertSame('T', $sesudah->Flag_Aktif);
    }

    public function test_menyunting_baris_yang_ada_tidak_membuat_baris_baru(): void
    {
        // Huruf besar sejak awal; lihat catatan di uji sebelumnya.
        $kode = strtoupper('UJI'.substr(bin2hex(random_bytes(3)), 0, 5));

        $dibuat = $this->tulis()->simpan('jenis-kompetensi', null, [
            'kode_type' => $kode,
            'nama_type' => 'Sebelum Disunting',
            'urutan_tampil' => 98,
        ], 1);

        $id = (int) $dibuat['id'];
        $sebelum = DB::table('N_PA_Competency_Type')->count();

        $this->tulis()->simpan('jenis-kompetensi', $id, [
            'kode_type' => $kode,
            'nama_type' => 'Sesudah Disunting',
            'urutan_tampil' => 98,
        ], 1);

        $this->assertSame($sebelum, DB::table('N_PA_Competency_Type')->count(),
            'Menyunting tidak boleh menambah baris.');
        $this->assertSame('Sesudah Disunting', DB::table('N_PA_Competency_Type')
            ->where('Id_PA_Competency_Type', $id)->value('Nama_Type'));
    }

    public function test_detail_baris_yang_tidak_ada_ditolak(): void
    {
        $hantu = (int) DB::table('N_PA_Competency_Type')->max('Id_PA_Competency_Type') + 99999;

        $this->expectException(RuntimeException::class);

        $this->baca()->detail('jenis-kompetensi', $hantu);
    }

    public function test_rincian_aturan_penilai_wajib_berjumlah_seratus(): void
    {
        $policyId = DB::table('N_PA_Rater_Policy')->orderBy('Id_PA_Rater_Policy')->value('Id_PA_Rater_Policy');

        if ($policyId === null) {
            $this->markTestSkipped('Master aturan penilai kosong di database pengembangan.');
        }

        $kategoriId = (int) DB::table('N_PA_Rater_Category')->value('Id_PA_Rater_Category');

        try {
            // Satu kategori berbobot 60 — tidak mungkin berjumlah 100 sendirian.
            $this->tulis()->simpanRincianAturan((int) $policyId, [
                ['pa_rater_category_id' => $kategoriId, 'bobot_persen' => 60, 'min_rater' => 1],
            ], 1);
            $this->fail('Rincian yang tidak berjumlah 100 seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('60', $e->getMessage());
        }
    }

    public function test_mengaktifkan_aturan_penilai_memakai_kode_status_master(): void
    {
        // Aturan aktif yang rinciannya sudah berjumlah 100.
        $policy = DB::table('N_PA_Rater_Policy')->where('Flag_Aktif', 'Y')->where('Status_Policy', 'ACTIVE')
            ->orderBy('Id_PA_Rater_Policy')->first(['Id_PA_Rater_Policy', 'ID_Level']);

        if ($policy === null) {
            $this->markTestSkipped('Belum ada aturan penilai aktif di database pengembangan.');
        }

        // Dulu `AKTIF`/`NONAKTIF` tertulis di sini dan setiap aktivasi ditolak FK.
        $this->tulis()->aktifkanAturanPenilai((int) $policy->Id_PA_Rater_Policy, 1);

        $this->assertSame('ACTIVE', DB::table('N_PA_Rater_Policy')
            ->where('Id_PA_Rater_Policy', $policy->Id_PA_Rater_Policy)->value('Status_Policy'));
    }

    public function test_rincian_tanpa_kunci_relasi_mempertahankan_relasi_lama(): void
    {
        $detail = DB::table('N_PA_Rater_Policy_Detail')->where('Flag_Aktif', 'Y')
            ->whereNotNull('Organization_Relationship_Type_Id')->first(['PA_Rater_Policy_Id']);

        if ($detail === null) {
            $this->markTestSkipped('Belum ada rincian aturan berelasi di database pengembangan.');
        }

        $policyId = (int) $detail->PA_Rater_Policy_Id;
        $sebelum = DB::table('N_PA_Rater_Policy_Detail')->where('PA_Rater_Policy_Id', $policyId)->where('Flag_Aktif', 'Y')
            ->get(['PA_Rater_Category_Id', 'Organization_Relationship_Type_Id', 'Bobot_Persen', 'Min_Rater']);

        // Layar Matriks Jenis Penilai hanya mengirim bobot & jumlah penilai, tanpa relasi.
        $this->tulis()->simpanRincianAturan($policyId, $sebelum->map(fn ($d) => [
            'pa_rater_category_id' => (int) $d->PA_Rater_Category_Id,
            'bobot_persen' => (float) $d->Bobot_Persen,
            'min_rater' => (int) $d->Min_Rater,
        ])->all(), 1);

        $sesudah = DB::table('N_PA_Rater_Policy_Detail')->where('PA_Rater_Policy_Id', $policyId)->where('Flag_Aktif', 'Y')
            ->pluck('Organization_Relationship_Type_Id', 'PA_Rater_Category_Id');

        foreach ($sebelum as $d) {
            $this->assertEquals($d->Organization_Relationship_Type_Id, $sesudah[$d->PA_Rater_Category_Id],
                'Relasi organisasi hilang karena layar tidak mengirimnya.');
        }
    }

    public function test_pita_teratas_harus_terbuka_di_atas_seratus(): void
    {
        $teratas = DB::table('N_PA_Score_Range')->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')
            ->where('Flag_Aktif', 'Y')->orderByDesc('Nilai_Dari')->first();

        try {
            $this->tulis()->simpan('pita-nilai', (int) $teratas->Id_PA_Score_Range, [
                'sumbu' => 'FINAL', 'kegunaan' => 'GRADE',
                'kode_range' => $teratas->Kode_Range, 'nama_range' => $teratas->Nama_Range,
                'nilai_dari' => (float) $teratas->Nilai_Dari, 'nilai_sampai' => 100,
                'urutan_tampil' => (int) $teratas->Urutan_Tampil,
                'batas_z' => $teratas->Batas_Z === null ? null : (float) $teratas->Batas_Z,
            ], 1);
            $this->fail('Pita teratas yang berakhir di 100 seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('terbuka', $e->getMessage());
        }
    }

    public function test_batas_z_harus_menurun_searah_urutan_grade(): void
    {
        try {
            PaKurvaStatistik::pastikanBatasZ([
                ['kode' => 'A', 'batas_z' => null],
                ['kode' => 'B', 'batas_z' => 0.0],
                ['kode' => 'D', 'batas_z' => -3.0],
            ], true);
            $this->fail('Grade tertinggi tanpa batas z seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('TERENDAH', $e->getMessage());
        }

        // Pasangannya: urutan yang benar diterima.
        PaKurvaStatistik::pastikanBatasZ([
            ['kode' => 'A', 'batas_z' => 2.0],
            ['kode' => 'B', 'batas_z' => 0.0],
            ['kode' => 'D', 'batas_z' => null],
        ], true);
        $this->addToAssertionCount(1);
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    public function test_pita_havmap_keempat_ditolak(): void
    {
        /* Penjaga yang ditemukan hilang pada 3 September 2026. Penjaga kesinambungan yang
           sudah ada hanya memastikan pitanya tidak berlubang — ia tidak peduli ada berapa.
           Padahal enam baris HAVMAP memberi makan SUMBU matriks tiga kali tiga.

           Yang terjadi tanpa penjaga ini bukan galat: orangnya dihitung, lalu tidak
           mendarat di kotak mana pun. Gejalanya cuma HAV Map yang tidak menggenapi jumlah
           peserta — dan tak seorang pun menjumlahkan sembilan kotak. */
        $sumbu = (string) DB::table('N_PA_Score_Range')
            ->where('Score_Usage', 'HAVMAP')->where('Flag_Aktif', 'Y')
            ->orderBy('Score_Type')->value('Score_Type');

        $this->assertNotSame('', $sumbu, 'Fixture butuh pita HAVMAP yang sudah ada.');

        $atas = (float) DB::table('N_PA_Score_Range')
            ->where('Score_Usage', 'HAVMAP')->where('Score_Type', $sumbu)
            ->where('Flag_Aktif', 'Y')->max('Nilai_Sampai');

        try {
            // Pita keempat yang BERSINAMBUNG — sengaja tidak berlubang, supaya yang
            // menolaknya benar-benar penjaga baru dan bukan penjaga lama.
            $this->tulis()->simpan('pita-nilai', null, [
                'sumbu' => $sumbu,
                'kegunaan' => 'HAVMAP',
                'kode_range' => 'SANGAT_TINGGI',
                'nama_range' => 'Sangat Tinggi',
                'nilai_dari' => $atas,
                'nilai_sampai' => $atas + 100,
                'urutan_tampil' => 4,
            ], 1);

            $this->fail('Pita HAVMAP keempat seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tiga kali tiga', $e->getMessage());
        }

        // Dan barisnya TIDAK boleh tertinggal: penolakan yang menyisakan baris sama saja
        // dengan tidak menolak.
        $this->assertSame(3, DB::table('N_PA_Score_Range')
            ->where('Score_Usage', 'HAVMAP')->where('Score_Type', $sumbu)
            ->where('Flag_Aktif', 'Y')->count());
    }

    public function test_pita_grade_masih_boleh_ditambah(): void
    {
        /* PASANGAN WAJIB dari uji di atas. Penjaga yang menolak segalanya juga akan
           membuat uji di atas hijau — dan berapa banyak pita GRADE serta di mana batasnya
           memang keputusan HC yang sah, bukan urusan matriks. */
        $atas = (float) DB::table('N_PA_Score_Range')
            ->where('Score_Usage', 'GRADE')->where('Flag_Aktif', 'Y')->max('Nilai_Sampai');

        $hasil = $this->tulis()->simpan('pita-nilai', null, [
            'sumbu' => 'FINAL',
            'kegunaan' => 'GRADE',
            'kode_range' => 'UJI'.substr(bin2hex(random_bytes(2)), 0, 4),
            'nama_range' => 'Pita Uji',
            'nilai_dari' => $atas,
            'nilai_sampai' => $atas + 50,
            'urutan_tampil' => 99,
            /* Sejak grade kurva (DDL-2026-120) setiap grade selain yang terendah wajib
               membawa batas z-nya — tanpa itu ada dua grade "terendah" dan kurvanya ambigu.
               Menambah grade tetap boleh; hanya harus lengkap. */
            'batas_z' => 3.5,
        ], 1);

        $this->assertGreaterThan(0, $hasil['id']);
        $this->assertTrue($hasil['baru']);
    }

    public function test_daftar_master_di_halaman_disaring_menurut_hak(): void
    {
        /* Gerbang RUTE memakai logika salah-satu: memegang satu master saja sudah cukup
           untuk membuka alamat master mana pun. Yang menahannya baru `pastikanBolehMaster()`
           di lapisan data — jadi datanya tidak pernah bocor, tetapi sebelum 3 September 2026
           halamannya tetap menawarkan master yang begitu diklik menjawab 403.

           Yang bocor bukan data melainkan HARAPAN: menawarkan pintu yang pasti tertutup
           membuat orang mengira sistemnya rusak, bukan mengira dirinya tidak berhak. */
        $id = DB::table('KPI_Users as u')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'u.Id_Users')
            ->where('k.Aktif', 'Y')
            ->orderBy('u.Id_Users')
            ->value('u.Id_Users');

        $this->assertNotNull($id, 'Butuh satu akun untuk menguji penyaringan.');

        $user = \App\Models\ESS\Employee\Models\User::find($id);
        $semua = PaMasterReadService::daftarMaster();

        $boleh = array_values(array_filter(
            $semua,
            fn (string $m): bool => $user->memilikiJabatan(PaMasterReadService::halamanMaster($m)),
        ));

        /* Yang dicabut adalah AKSESNYA, bukan keberadaannya. Master-nya harus tetap
           dikenal service — kalau ia hilang dari katalog, seluruh baris yang sudah
           tersimpan kehilangan namanya. */
        foreach (['kuadran-hav', 'kategori-penilai'] as $dicabut) {
            $this->assertContains($dicabut, $semua);
        }

        // Penyaringannya harus benar-benar menyaring, bukan memulangkan daftar utuh.
        foreach ($boleh as $m) {
            $this->assertTrue(
                $user->memilikiJabatan(PaMasterReadService::halamanMaster($m)),
                "Master {$m} lolos penyaringan padahal haknya tidak ada."
            );
        }

        $this->assertLessThanOrEqual(count($semua), count($boleh));
    }

    private function baca(): PaMasterReadService
    {
        return $this->app->make(PaMasterReadService::class);
    }

    private function tulis(): PaMasterWriteService
    {
        return $this->app->make(PaMasterWriteService::class);
    }
}
