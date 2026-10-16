<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCycleSetupService;
use App\Services\KPI\PerformanceAppraisal\PaLevelPaResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Membuat dan menyunting siklus (A09).
 *
 * PENJAGA TERPENTING DI SINI TIDAK TERLIHAT DARI LAYAR MANA PUN
 *
 * `Tahun` wajib sesuai tahun periode yang dinilai. Pilar KPI menyaring jadwal KPI memakai
 * TAHUN siklus, sementara bulan-bulannya diturunkan dari PERIODE. Kalau keduanya
 * berselisih, saringannya tidak menemukan satu baris pun, SETIAP peserta berakhir "Tidak
 * ada KPI disetujui", dan sebab sebenarnya tidak muncul di mana pun — siklusnya terlihat
 * berjalan, hasilnya kosong semua.
 *
 * Kegagalan itu hanya bisa dilihat dengan menyandingkan dua kolom yang tampak tidak
 * berhubungan. Uji inilah satu-satunya yang akan menangkapnya kalau penjaganya dicabut.
 */
class CycleSetupTest extends TestCase
{
    use DatabaseTransactions;

    /* ------------------------------------------------------------------ *
     * Tahun versus periode                                                *
     * ------------------------------------------------------------------ */

    public function test_tahun_yang_tidak_sesuai_periode_ditolak(): void
    {
        try {
            $this->service()->buat($this->muatan(['tahun' => 2025]), 1);
            $this->fail('Tahun 2025 dengan periode 2026 seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak sesuai periode', $e->getMessage());
            // Pesannya harus menjelaskan AKIBATNYA, bukan sekadar menyatakan tidak cocok.
            $this->assertStringContainsString('Tidak ada KPI disetujui', $e->getMessage());
        }

        // Arah sebaliknya: yang sesuai diterima, sehingga penolakan di atas terbukti
        // bergantung pada tahunnya dan bukan pada sesuatu yang lain.
        $hasil = $this->service()->buat($this->muatan(), 1);

        $this->assertGreaterThan(0, $hasil['cycle_id']);
    }

    public function test_tahun_boleh_sama_dengan_tahun_akhir_periode(): void
    {
        /* Periode lintas tahun memang ada — Juli 2025 sampai Juni 2026. Menuntut tahun
           sama dengan tahun MULAI saja akan menolak siklus yang sah. */
        $hasil = $this->service()->buat($this->muatan([
            'tahun' => 2026,
            'periode_dinilai_dari' => '2025-07-01',
            'periode_dinilai_sampai' => '2026-06-30',
        ]), 1);

        $this->assertGreaterThan(0, $hasil['cycle_id']);
    }

    public function test_tahun_sesudah_periode_juga_ditolak(): void
    {
        /* Arah yang sebelumnya tidak pernah diuji — dan justru bentuk salah ketik yang
           paling mungkin terjadi: siklus dibuat Januari 2027 untuk menilai periode 2026,
           lalu tahunnya diisi 2027 mengikuti kalender saat mengisinya.

           Tanpa uji ini, `$tahun >= $tahunDari` akan lolos sebagai implementasi — padahal
           ia meloloskan persis skenario "setiap peserta Tidak ada KPI disetujui". */
        try {
            $this->service()->buat($this->muatan(['tahun' => 2027]), 1);
            $this->fail('Tahun 2027 dengan periode 2026 seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak sesuai periode', $e->getMessage());
        }
    }

    public function test_tahun_boleh_sama_dengan_tahun_mulai_periode(): void
    {
        /* Pasangan dari uji tahun-akhir. Tahun fiskal lazim dinamai tahun MULAI-nya:
           "2025" untuk Juli 2025 sampai Juni 2026.

           Tanpa uji ini, cabang `$tahun === $tahunDari` bisa dihapus dan seluruh uji lain
           tetap hijau — padahal menghapusnya menolak siklus yang sah. */
        $hasil = $this->service()->buat($this->muatan([
            'tahun' => 2025,
            'periode_dinilai_dari' => '2025-07-01',
            'periode_dinilai_sampai' => '2026-06-30',
        ]), 1);

        $this->assertGreaterThan(0, $hasil['cycle_id']);
    }

    public function test_mengirim_ulang_jendela_pengisian_yang_sama_bukan_perubahan(): void
    {
        /* REGRESI atas cacat tipe. `Pengisian_*` bertipe `datetime2(0)` sedangkan periode
           dinilai bertipe `date`; basis data memulangkan "2026-07-01 00:00:00" versus
           "2026-07-01". Dibandingkan sebagai string, formulir yang mengirim ulang nilai
           yang SAMA PERSIS akan dinilai berubah — dan pada siklus OPEN itu berarti
           penolakan "Pengisian_Dari tidak bisa diubah" untuk orang yang cuma mengganti
           nama. */
        $id = $this->service()->buat($this->muatan([
            'pengisian_dari' => '2026-07-01',
            'pengisian_sampai' => '2026-07-31',
        ]), 1)['cycle_id'];

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $id)->update(['Status_Cycle' => 'OPEN']);
        $this->app->make(PaCycleGate::class)->lupakanMemo();

        // Nilai yang sama persis, pada siklus yang periodenya sudah beku.
        $hasil = $this->service()->perbarui($id, [
            'nama_cycle' => 'Hanya Nama Yang Berubah',
            'pengisian_dari' => '2026-07-01',
            'pengisian_sampai' => '2026-07-31',
        ], 1);

        $this->assertSame([], $hasil['periode_berubah']);
        $this->assertSame('Hanya Nama Yang Berubah', DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $id)->value('Nama_Cycle'));
    }

    /* ------------------------------------------------------------------ *
     * Rentang tanggal                                                     *
     * ------------------------------------------------------------------ */

    public function test_rentang_terbalik_ditolak_pada_kedua_jendela(): void
    {
        $this->tolak(['periode_dinilai_sampai' => '2025-12-31'], 'Periode dinilai');

        $this->tolak([
            'pengisian_dari' => '2026-08-01',
            'pengisian_sampai' => '2026-07-01',
        ], 'Jendela pengisian');
    }

    /**
     * Salin setelan dari siklus yang punya beberapa paket per level (satu per kategori):
     * kategori, kode soal sumber, dan asal salinan ikut tersalin. Dulu kategorinya hilang dan
     * sisipannya menabrak indeks unik setelah baris siklus baru telanjur tercipta.
     */
    public function test_salin_setelan_membawa_kategori_dan_kode_soal(): void
    {
        $sumber = DB::table('N_PA_Cycle_Question_Package')->where('Flag_Aktif', 'Y')
            ->whereNotNull('PA_Rater_Category_Id')
            ->groupBy('PA_Cycle_Id', 'ID_Level')->havingRaw('COUNT(*) > 1')
            ->value('PA_Cycle_Id');

        if ($sumber === null) {
            $this->markTestSkipped('Tidak ada siklus dengan beberapa paket per level di database pengembangan.');
        }

        $hasil = $this->service()->buat([
            'kode_cycle' => $this->kode(),
            'nama_cycle' => 'Uji Salin Setelan',
            'tahun' => 2026,
            'salin_dari_cycle_id' => (int) $sumber,
        ], 1);

        $baru = (int) $hasil['cycle_id'];
        $paket = fn (int $c) => DB::table('N_PA_Cycle_Question_Package')->where('PA_Cycle_Id', $c)->where('Flag_Aktif', 'Y')
            ->orderBy('ID_Level')->orderBy('PA_Rater_Category_Id')->get(['ID_Level', 'PA_Rater_Category_Id'])
            ->map(fn ($p) => $p->ID_Level.'|'.$p->PA_Rater_Category_Id)->all();

        $this->assertSame($paket((int) $sumber), $paket($baru));
        $this->assertSame((int) $sumber, (int) DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $baru)->value('PA_Cycle_Sumber_Id'));
        $this->assertSame(0, DB::table('N_PA_Cycle_Question_Package_Item as i')
            ->join('N_PA_Cycle_Question_Package as k', 'k.Id_PA_Cycle_Question_Package', '=', 'i.PA_Cycle_Question_Package_Id')
            ->where('k.PA_Cycle_Id', $baru)->whereNull('i.Kode_Soal_Sumber')->count(), 'Kode soal sumber tidak tersalin.');
    }

    public function test_periode_boleh_kosong_saat_masih_draft(): void
    {
        /* Siklus DRAFT memang belum tentu berjadwal, dan menuntut tanggalnya sejak awal
           memaksa orang mengarang angka yang kemudian dipercaya sistem. */
        $hasil = $this->service()->buat([
            'kode_cycle' => $this->kode(),
            'nama_cycle' => 'Uji Tanpa Tanggal',
            'tahun' => 2026,
        ], 1);

        $this->assertSame('DRAFT', $hasil['status']);
    }

    /* ------------------------------------------------------------------ *
     * Identitas                                                           *
     * ------------------------------------------------------------------ */

    public function test_kode_dibuat_server_dan_kiriman_admin_diabaikan(): void
    {
        /* Sebelum 6 September 2026 admin mengetiknya sendiri, dan hasilnya persis seperti
           yang bisa diduga: `CONTOHSAJA`, `UDUHEU`. Kode itu bukan hiasan — ia yang dipakai
           menyebut siklus di perintah artisan, laporan, dan pesan galat. */
        $tahun = (int) date('Y');

        $hasil = $this->service()->buat(
            $this->muatan(['kode_cycle' => 'UDUHEU', 'tahun' => $tahun]),
            1,
        );

        $this->assertMatchesRegularExpression('/^PA-'.$tahun.'-\d{2}$/', $hasil['kode_cycle']);
        $this->assertNotSame('UDUHEU', $hasil['kode_cycle']);

        // Yang tersimpan sama dengan yang dilaporkan; layar menampilkan balasan ini.
        $this->assertSame($hasil['kode_cycle'], DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $hasil['cycle_id'])->value('Kode_Cycle'));
    }

    public function test_nomor_urutnya_naik_dan_berselang_per_tahun(): void
    {
        $tahun = (int) date('Y');

        $pertama = $this->service()->buat($this->muatan(['tahun' => $tahun]), 1);
        $kedua = $this->service()->buat($this->muatan(['tahun' => $tahun]), 1);

        $nomor = fn (string $kode) => (int) substr($kode, strrpos($kode, '-') + 1);

        $this->assertSame($nomor($pertama['kode_cycle']) + 1, $nomor($kedua['kode_cycle']));

        /* Nomornya BERSELANG PER TAHUN. Kalau ia berjalan global, siklus pertama tahun
           depan lahir dengan nomor lanjutan dari tahun ini — dan "PA-2027-14" membuat
           orang mengira ada tiga belas siklus yang tidak pernah mereka lihat. */
        $tahunLain = $tahun + 1;
        $lain = $this->service()->buat($this->muatan([
            'tahun' => $tahunLain,
            'periode_dinilai_dari' => $tahunLain.'-01-01',
            'periode_dinilai_sampai' => $tahunLain.'-06-30',
        ]), 1);

        $this->assertSame(1, $nomor($lain['kode_cycle']));
        $this->assertStringStartsWith('PA-'.$tahunLain.'-', $lain['kode_cycle']);
    }

    public function test_nomor_dua_digit_supaya_urutan_teksnya_benar(): void
    {
        /* `PA-2026-2` jatuh SESUDAH `PA-2026-10` bila diurutkan sebagai teks. Setiap
           laporan yang mengurutkan berdasarkan kode akan menyajikan siklus dengan urutan
           yang salah, dan tidak ada yang gagal. */
        $tahun = (int) date('Y');
        $kode = $this->service()->buat($this->muatan(['tahun' => $tahun]), 1)['kode_cycle'];

        $this->assertSame(2, strlen(substr($kode, strrpos($kode, '-') + 1)));
    }

    public function test_kode_lama_yang_bukan_angka_tidak_ikut_menentukan_nomor(): void
    {
        /* Siklus contoh bernama `PA-DEMO-FE` dan siklus lama bernama `PA-2026-H1` masih
           ada di basis data. `(int) 'H1'` bernilai 0, sehingga ekor yang bukan angka
           diam-diam terbaca sebagai "belum ada satu pun" — dan kode berikutnya menabrak
           yang sudah ada. */
        $tahun = (int) date('Y');

        /* DIUKUR RELATIF, bukan ditegaskan sebagai angka mutlak. Basis data pengembangan
           sudah menyimpan siklus lain untuk tahun ini, jadi menuntut `PA-2026-01` membuat
           uji ini memerah karena data yang tidak ia buat — persis kekeliruan yang sama
           dengan yang baru saja diperbaiki di uji kontrak. */
        $tertinggiSebelum = $this->nomorTertinggi($tahun);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'PA-'.$tahun.'-H1',
            'Nama_Cycle' => 'Siklus Uji Ekor Bukan Angka',
            'Tahun' => $tahun,
            'Status_Cycle' => 'DRAFT',
            'Flag_Aktif' => 'Y',
        ]);

        $kode = $this->service()->buat($this->muatan(['tahun' => $tahun]), 1)['kode_cycle'];

        // `H1` diabaikan, jadi nomornya naik satu dari yang tertinggi — bukan mengulang
        // dari nol lalu menabrak kode yang sudah ada.
        $this->assertSame(
            sprintf('PA-%d-%02d', $tahun, $tertinggiSebelum + 1),
            $kode,
        );
    }

    /** Nomor urut tertinggi yang sudah terpakai pada satu tahun. */
    private function nomorTertinggi(int $tahun): int
    {
        $awalan = 'PA-'.$tahun.'-';

        return (int) DB::table('N_PA_Cycle')
            ->where('Kode_Cycle', 'like', $awalan.'%')
            ->pluck('Kode_Cycle')
            ->map(fn ($k) => substr((string) $k, strlen($awalan)))
            ->filter(fn ($ekor) => $ekor !== '' && ctype_digit($ekor))
            ->map(fn ($ekor) => (int) $ekor)
            ->max();
    }

    public function test_kode_tidak_bisa_disunting(): void
    {
        $id = $this->service()->buat($this->muatan(), 1)['cycle_id'];
        $kode = (string) DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $id)->value('Kode_Cycle');

        // Mengirim kode yang SAMA bukan penyuntingan: layar mengirim kembali seluruh isian
        // form, termasuk kode yang cuma ditampilkan.
        $this->service()->perbarui($id, ['kode_cycle' => $kode, 'nama_cycle' => 'Nama Baru'], 1);
        $this->assertSame($kode, DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $id)->value('Kode_Cycle'));

        try {
            $this->service()->perbarui($id, ['kode_cycle' => 'PA-1999-99'], 1);
            $this->fail('Mengubah kode seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak bisa diubah', $e->getMessage());
        }

        // Ditolak berarti TIDAK berubah, bukan sekadar dilaporkan gagal.
        $this->assertSame($kode, DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $id)->value('Kode_Cycle'));
    }

    public function test_cetak_biru_master_disalin_otomatis_saat_siklus_dibuat(): void
    {
        /* Sebelum 6 September 2026 salinan ini hanya terbentuk ketika admin menekan
           "Salin dari master" pada langkah Aturan Penilai. Akibatnya langkah PERTAMA
           wizard — Bobot Penilaian — menyajikan layar kosong pada siklus yang baru dibuat,
           karena ia membaca salinan siklus dan salinannya belum ada. */
        $hasil = $this->service()->buat($this->muatan(), 1);
        $id = $hasil['cycle_id'];

        $this->assertTrue($hasil['cetak_biru']['berhasil'], 'Cetak biru gagal disalin: '
            .($hasil['cetak_biru']['galat'] ?? ''));

        $bobot = DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')->where('PA_Cycle_Id', $id)->count();

        $this->assertGreaterThan(0, $bobot, 'Bobot pilar tidak tersalin; langkah pertama wizard akan kosong.');
        $this->assertSame($bobot, $hasil['cetak_biru']['pilar']);

        // Bukan bobot saja: aturan penilai, syarat kompetensi, dan pita nilai satu paket.
        $this->assertGreaterThan(0, DB::table('N_PA_Cycle_Rater_Policy_Snapshot')->where('PA_Cycle_Id', $id)->count());
        $this->assertGreaterThan(0, DB::table('N_PA_Cycle_Score_Range_Snapshot')->where('PA_Cycle_Id', $id)->count());
    }

    public function test_hanya_level_yang_dihuni_karyawan_aktif_yang_disalin(): void
    {
        /* Master memuat setiap level yang pernah ada; yang disalin hanya level yang
           benar-benar dihuni karyawan aktif. Menyalin seluruhnya berarti menaruh baris
           untuk orang yang tidak ada, dan admin diminta menyetel bobot bagi level kosong. */
        $id = $this->service()->buat($this->muatan(), 1)['cycle_id'];

        $levelDisalin = DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')
            ->where('PA_Cycle_Id', $id)->pluck('ID_Level')->map(fn ($x) => (int) $x)->sort()->values();

        /* "Dihuni" memakai Level PA (DDL-2026-177): karyawan yang levelnya dikoreksi ke level
           khusus PA (mis. Officer Specialist 1000000) menghuni level itu, bukan level HRIS-nya.
           Dibaca lewat resolver yang sama dengan penyalin, supaya uji ini tidak memegang
           salinan aturannya sendiri. */
        $c = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $id)->first(['Tanggal_Acuan_Organisasi', 'Periode_Dinilai_Sampai']);
        $acuan = ($t = $c->Tanggal_Acuan_Organisasi ?? $c->Periode_Dinilai_Sampai) === null ? null : substr((string) $t, 0, 10);
        $lv = PaLevelPaResolver::sqlLevel();
        $levelDihuni = PaLevelPaResolver::gabung(DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan'), 'k', 'v', $acuan)
            ->where('k.Aktif', 'Y')
            ->distinct()->pluck(DB::raw("{$lv} as lv"))->map(fn ($x) => (int) $x);

        $this->assertNotEmpty($levelDisalin, 'Tidak ada level tersalin sama sekali.');

        foreach ($levelDisalin as $level) {
            $this->assertTrue($levelDihuni->contains($level),
                "Level {$level} tersalin padahal tidak dihuni karyawan aktif.");
        }

        $this->assertLessThan(
            DB::table('N_PA_Pillar_Weight')->count(),
            $levelDisalin->count(),
            'Seluruh level master tersalin; penyaring level yang dihuni tidak bekerja.',
        );
    }

    public function test_siklus_selalu_lahir_draft(): void
    {
        // Status tidak diterima dari kiriman; yang lahir terkunci melewati penyiapan dan
        // pesertanya tidak pernah terbentuk.
        $hasil = $this->service()->buat($this->muatan() + ['status_cycle' => 'PUBLISHED'], 1);

        $this->assertSame('DRAFT', $hasil['status']);
        $this->assertSame('DRAFT', DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $hasil['cycle_id'])->value('Status_Cycle'));
    }

    /*
     | TANGGAL ACUAN ORGANISASI DIISI SERVER, BUKAN DITANYAKAN.
     |
     | Medannya dulu isian opsional bertulis "Opsional" di layar pembuatan siklus, dan
     | pertanyaan yang tidak bisa dijawab admin selalu berakhir kosong. Kosongnya bukan tanpa
     | akibat: pembaca relasi jatuh ke `Periode_Dinilai_Sampai`, sehingga susunan organisasi
     | dibaca menurut keadaan pada AKHIR periode yang dinilai — untuk siklus yang dibuat
     | berbulan-bulan sesudahnya, yang menilai adalah atasan lama.
     |
     | Yang diuji nilainya TERSIMPAN, bukan sekadar "tidak error": kekosongan yang ditafsirkan
     | saat dibaca berubah artinya setiap kali aturan tafsirnya berubah, sedangkan tanggal
     | yang tertulis tetap bisa dipertanggungjawabkan enam bulan kemudian.
     */
    public function test_tanggal_acuan_organisasi_diisi_tanggal_pembuatan(): void
    {
        $hasil = $this->service()->buat($this->muatan(), 1);

        $tersimpan = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $hasil['cycle_id'])
            ->value('Tanggal_Acuan_Organisasi');

        $this->assertNotNull($tersimpan, 'Tanggal acuan organisasi harus terisi sendiri.');
        $this->assertSame(
            \App\Helpers\FormatTanggalHelper::getCurrentTime()->format('Y-m-d'),
            substr((string) $tersimpan, 0, 10),
        );
    }

    /* Pemanggil yang MEMANG mengirimkannya tetap dihormati: yang berubah layarnya, bukan
       API-nya. Tanpa uji ini, "diisi sendiri" gampang berubah menjadi "ditimpa selalu". */
    public function test_tanggal_acuan_organisasi_kiriman_tidak_ditimpa(): void
    {
        $hasil = $this->service()->buat(
            $this->muatan() + ['tanggal_acuan_organisasi' => '2026-03-15'],
            1,
        );

        $this->assertSame('2026-03-15', substr((string) DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $hasil['cycle_id'])
            ->value('Tanggal_Acuan_Organisasi'), 0, 10));
    }

    public function test_kolom_wajib_dituntut_saat_membuat(): void
    {
        // `kode_cycle` sengaja TIDAK ada di sini: sejak kodenya dibuat server, menuntut
        // orang mengirimnya berarti menuntut sesuatu yang tak punya kolom lagi di layar.
        foreach (['nama_cycle', 'tahun'] as $hilang) {
            $muatan = $this->muatan();
            unset($muatan[$hilang]);

            try {
                $this->service()->buat($muatan, 1);
                $this->fail("Tanpa {$hilang} seharusnya ditolak.");
            } catch (AssertionFailedError $gagal) {
                // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
                // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
                // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
                throw $gagal;
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('wajib diisi', $e->getMessage());
            }
        }
    }

    /* ------------------------------------------------------------------ *
     * Dua tingkat penyuntingan                                            *
     * ------------------------------------------------------------------ */

    public function test_nama_boleh_disunting_kapan_saja_periode_tidak(): void
    {
        $id = $this->service()->buat($this->muatan(), 1)['cycle_id'];

        // DRAFT: keduanya boleh.
        $this->service()->perbarui($id, ['nama_cycle' => 'Nama Saat Draft'], 1);
        $ubah = $this->service()->perbarui($id, ['periode_dinilai_sampai' => '2026-07-31'], 1);

        $this->assertContains('Periode_Dinilai_Sampai', $ubah['periode_berubah']);

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $id)->update(['Status_Cycle' => 'OPEN']);
        $this->app->make(PaCycleGate::class)->lupakanMemo();

        try {
            $this->service()->perbarui($id, ['periode_dinilai_sampai' => '2026-08-31'], 1);
            $this->fail('Periode seharusnya beku sesudah siklus dikunci.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            /* Periode menentukan baris KPI mana yang ditarik tiap peserta, jadi
               mengubahnya sesudah kunci menggeser nilai orang tanpa jejak. */
            $this->assertStringContainsString('Periode_Dinilai_Sampai', $e->getMessage());
        }

        // Nama tetap boleh — ia tidak mengubah angka siapa pun. Tanpa penegasan ini,
        // penolakan di atas juga akan hijau seandainya SEMUA penyuntingan diblokir.
        $this->service()->perbarui($id, ['nama_cycle' => 'Nama Saat Open'], 1);

        $this->assertSame('Nama Saat Open', DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $id)->value('Nama_Cycle'));
    }

    public function test_perlu_siapkan_ulang_hanya_saat_pesertanya_sudah_ada(): void
    {
        $id = $this->service()->buat($this->muatan(), 1)['cycle_id'];

        $tanpaPeserta = $this->service()->perbarui($id, ['periode_dinilai_sampai' => '2026-07-31'], 1);

        $this->assertFalse($tanpaPeserta['perlu_siapkan_ulang']);

        $k = DB::table('Karyawan')->where('Aktif', 'Y')->orderBy('Kode_Karyawan')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $id,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level'),
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        $adaPeserta = $this->service()->perbarui($id, ['periode_dinilai_sampai' => '2026-08-31'], 1);

        /* Peserta yang sudah terbentuk memakai salinan organisasi pada tanggal acuan LAMA;
           mengubah periodenya tidak menyiapkan ulang mereka dengan sendirinya, dan tak ada
           yang akan memberi tahu kalau layar diam. */
        $this->assertTrue($adaPeserta['perlu_siapkan_ulang']);
    }

    public function test_menyunting_tanpa_mengubah_periode_tidak_melaporkan_perubahan(): void
    {
        $id = $this->service()->buat($this->muatan(), 1)['cycle_id'];

        $hasil = $this->service()->perbarui($id, [
            'nama_cycle' => 'Hanya Nama',
            // Nilai yang SAMA dengan yang tersimpan; ini bukan perubahan periode.
            'periode_dinilai_sampai' => '2026-06-30',
        ], 1);

        $this->assertSame([], $hasil['periode_berubah']);
        $this->assertFalse($hasil['perlu_siapkan_ulang']);
    }

    /* ------------------------------------------------------------------ *
     * Detail                                                              *
     * ------------------------------------------------------------------ */

    public function test_detail_menyebut_keadaan_bukan_menyerahkan_kesimpulan(): void
    {
        $id = $this->service()->buat($this->muatan(), 1)['cycle_id'];

        $draft = $this->service()->detail($id);

        $this->assertTrue($draft['boleh_ubah_periode']);
        $this->assertNull($draft['alasan_periode_terkunci']);
        $this->assertArrayHasKey('kelengkapan', $draft);
        $this->assertArrayHasKey('salinan_aturan_penilai', $draft['kelengkapan']);

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $id)->update(['Status_Cycle' => 'CLOSED']);
        $this->app->make(PaCycleGate::class)->lupakanMemo();

        $tertutup = $this->service()->detail($id);

        /* Layar mengunci formulirnya dari keadaan ini, bukan dari Status_Cycle. Aturannya
           milik server; layar yang menyimpulkannya sendiri akan berselisih begitu
           aturannya berubah. */
        $this->assertFalse($tertutup['boleh_ubah_periode']);
        $this->assertNotNull($tertutup['alasan_periode_terkunci']);
        $this->assertStringContainsString('CLOSED', $tertutup['alasan_periode_terkunci']);
    }

    public function test_siklus_yang_tidak_ada_ditolak(): void
    {
        $hantu = (int) DB::table('N_PA_Cycle')->max('Id_PA_Cycle') + 99999;

        $this->expectException(RuntimeException::class);

        $this->service()->detail($hantu);
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    private function service(): PaCycleSetupService
    {
        return $this->app->make(PaCycleSetupService::class);
    }

    /**
     * Kode uji, HURUF BESAR sejak dibangkitkan.
     *
     * Service menaikkannya dengan `strtoupper()`; membangkitkannya huruf kecil lalu
     * membandingkan masukan mentah dengan nilai tersimpan akan gagal — kecuali bila
     * karakter acaknya kebetulan angka semua, yang membuat uji hijau sepersekian waktu.
     */
    private function kode(): string
    {
        return 'UJI-SETUP-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    /**
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function muatan(array $ubah = []): array
    {
        return array_merge([
            'kode_cycle' => $this->kode(),
            'nama_cycle' => 'Siklus Uji Setup',
            'tahun' => 2026,
            'periode_dinilai_dari' => '2026-01-01',
            'periode_dinilai_sampai' => '2026-06-30',
        ], $ubah);
    }

    /**
     * @param  array<string, mixed>  $ubah
     */
    private function tolak(array $ubah, string $potongan): void
    {
        try {
            $this->service()->buat($this->muatan($ubah), 1);
            $this->fail("Seharusnya ditolak dengan pesan memuat '{$potongan}'.");
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($potongan, $e->getMessage());
        }
    }
}
