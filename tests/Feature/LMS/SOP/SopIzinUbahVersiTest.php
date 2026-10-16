<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopItemVersionService;
use App\Services\LMS\SOP\SopTahapVersiService;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use App\Services\LMS\SOP\Support\SopVersiKeadaan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Izin ubah versi SOP per keadaan.
 *
 * Sampai 30 Agu 2026 aturannya hanya "DRAFT boleh, selain itu tidak" — kasarnya
 * tepat, tapi salah di dua arah. Versi terbit terkunci TOTAL, padahal
 * memperpanjang masa berlaku atau membetulkan salah ketik pengesah tak
 * melahirkan dokumen yang berbeda. Sebaliknya versi BATAL lolos, sebab
 * Status-nya memang masih DRAFT.
 *
 * Rancangan: docs/superpowers/specs/2026-08-30-izin-ubah-versi-per-keadaan.md
 */
class SopIzinUbahVersiTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function dokumen(string $judul): int
    {
        return $this->svc()->buat([
            'Judul' => $judul.' '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);
    }

    /** Versi DRAFT yang masih berjalan. */
    private function versiDraft(): array
    {
        $idDok = $this->dokumen('SOP IZIN DRAFT');
        $idVersi = $this->svc()->tambahVersi($idDok, [
            'Ringkasan_Perubahan' => 'Awal',
            'Tanggal_Kadaluarsa' => now()->addYear()->toDateString(),
        ], 1);
        $this->assertNotNull($idVersi);

        return [$idDok, $idVersi];
    }

    /**
     * Versi PUBLISHED.
     *
     * Statusnya disetel langsung. Menyeret seluruh gerbang penerbitan ke sini
     * membuat uji ini gagal karena sebab yang sama sekali lain — dan yang
     * hendak dibuktikan adalah IZIN UBAH-nya, bukan alur terbitnya.
     */
    private function versiTerbit(array $kolom = []): array
    {
        [$idDok, $idVersi] = $this->versiDraft();

        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update(array_merge([
            'Status' => SopStatus::VERSI_PUBLISHED,
            'Tahap_Id' => DB::table('N_SOP_Master_Tahap_Versi')
                ->where('Kode_Tahap', 'FILING_REGISTERED')->value('Id_Tahap'),
        ], $kolom));

        return [$idDok, $idVersi];
    }

    /**
     * Masukkan dokumen ke sebuah paket lalu jadwalkan paket itu ke satu karyawan.
     *
     * Sejak 5 Okt 2026 inilah garis yang mengunci berkas dan ujian versi terbit:
     * selama belum ada karyawan yang dijadwalkan membacanya, isi versi masih
     * boleh dibetulkan tanpa revisi baru.
     */
    private function jadwalkan(int $idDok): void
    {
        $idPaket = DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'UJI JADWAL '.substr((string) microtime(true), -5),
            'Prioritas' => 'KRITIS', 'Mode_Urutan_Item' => 'BEBAS', 'Status' => SopStatus::PAKET_PUBLISHED,
            'Flag_Absensi' => 'T', 'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ], 'Id_SOP_Paket');
        DB::table('N_SOP_Paket_Item')->insert([
            'Id_SOP_Paket' => $idPaket, 'Id_SOP_Dokumen' => $idDok, 'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => 'Uji', 'Urutan' => 1, 'Flag_Wajib' => 'Y', 'Flag_Highlight' => 'T',
            'Flag_Watermark' => 'T', 'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ]);
        $idJadwal = DB::table('N_SOP_Penjadwalan')->insertGetId([
            'Id_SOP_Paket' => $idPaket, 'Sumber' => 'MANUAL', 'Tipe_Tenggat' => 'DEADLINE',
            'Flag_Reminder' => 'T', 'Flag_Cabut_Akses' => 'T', 'Tanggal_Assign' => now(),
            'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ], 'Id_SOP_Penjadwalan');
        DB::table('N_SOP_Penjadwalan_Detail')->insert([
            'Id_SOP_Penjadwalan' => $idJadwal,
            'Kode_Karyawan' => DB::table('Karyawan')->where('Aktif', 'Y')->value('Kode_Karyawan'),
            'Status_Paket' => 'PROGRESS', 'Flag_Cabut_Akses' => 'T', 'Jumlah_Reminder' => 0,
            'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ]);
    }

    private function nilai(int $idVersi, string $kolom)
    {
        return DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value($kolom);
    }

    /* ══ KEBIJAKANNYA ═══════════════════════════════════════════════════ */

    /**
     * Daftar izinnya dijawab SATU tempat, dan bentuknya tetap larik — tak ada
     * nilai sentinel "semua" yang harus diterjemahkan layar.
     */
    public function test_kebijakan_izin_per_keadaan(): void
    {
        $tahap = fn (string $kode) => DB::table('N_SOP_Master_Tahap_Versi')
            ->where('Kode_Tahap', $kode)->first(['Kode_Tahap', 'Nonaktifkan_Versi']);

        $this->assertSame(SopVersiKeadaan::FIELD_DRAFT,
            SopVersiKeadaan::fieldBolehUbah(SopStatus::PAKET_DRAFT, $tahap('APPROVAL')));

        $this->assertSame(SopVersiKeadaan::FIELD_TERBIT,
            SopVersiKeadaan::fieldBolehUbah(SopStatus::VERSI_PUBLISHED, $tahap('FILING_REGISTERED')));

        /* ARSIP: tanggal saja. Riwayat tetap menjawab "apa yang terjadi",
           yang boleh dibetulkan hanyalah KAPAN-nya. */
        $this->assertSame(SopVersiKeadaan::FIELD_ARSIP,
            SopVersiKeadaan::fieldBolehUbah(SopStatus::VERSI_OBSOLETE, $tahap('OBSOLETE')));

        /* Versi batal berstatus DRAFT; yang menentukan adalah TAHAPNYA. */
        $this->assertSame(SopVersiKeadaan::FIELD_ARSIP,
            SopVersiKeadaan::fieldBolehUbah(SopStatus::PAKET_DRAFT, $tahap('CANCEL')));

        /* Isi versi terbit (berkas & ujian) terbuka HANYA selama dokumennya
           belum dijadwalkan ke karyawan. Bawaannya tetap terkunci: pemanggil
           yang tak tahu keadaan penjadwalan tak boleh membukanya. Nomor versi
           tak pernah ikut terbuka. */
        $terbitBelumDijadwalkan = SopVersiKeadaan::fieldBolehUbah(
            SopStatus::VERSI_PUBLISHED, $tahap('FILING_REGISTERED'), sudahDijadwalkan: false);
        foreach (SopVersiKeadaan::FIELD_ISI as $isi) {
            $this->assertContains($isi, $terbitBelumDijadwalkan);
        }
        $this->assertNotContains('Versi_Ke', $terbitBelumDijadwalkan);
        $this->assertSame(SopVersiKeadaan::FIELD_TERBIT, SopVersiKeadaan::fieldBolehUbah(
            SopStatus::VERSI_PUBLISHED, $tahap('FILING_REGISTERED'), sudahDijadwalkan: true));

        /* Begitu dijadwalkan, isi versi TIDAK boleh diubah — itulah yang membuat
           sebuah revisi menjadi revisi. Tanggal TIDAK termasuk isi. */
        foreach (['Id_Master_Konten', 'Id_Master_Ujian', 'Versi_Ke'] as $terlarang) {
            $this->assertNotContains($terlarang, SopVersiKeadaan::FIELD_TERBIT,
                "{$terlarang} tak boleh ikut dibuka pada versi terbit.");
            $this->assertNotContains($terlarang, SopVersiKeadaan::FIELD_ARSIP,
                "{$terlarang} tak boleh ikut dibuka pada arsip.");
        }

        /* Lampiran form kosong ikut aturan isi (5 Okt 2026). */
        $this->assertContains('Lampiran', SopVersiKeadaan::FIELD_DRAFT);
        $this->assertContains('Lampiran', SopVersiKeadaan::FIELD_ISI);
        $this->assertNotContains('Lampiran', SopVersiKeadaan::FIELD_ARSIP);
        $this->assertNotContains('Lampiran', SopVersiKeadaan::FIELD_TERBIT);
        $this->assertTrue(SopTipeItem::blokBerlaku('FORM', 'lampiran'));
        $this->assertFalse(SopTipeItem::blokWajib('FORM', 'lampiran'));
        $this->assertFalse(SopTipeItem::blokBerlaku('DOKUMEN', 'lampiran'));

        /* Seluruh tanggal proses terbuka pada versi terbit (21 Sep 2026). */
        foreach (SopVersiKeadaan::FIELD_TANGGAL as $tanggal) {
            $this->assertContains($tanggal, SopVersiKeadaan::FIELD_TERBIT,
                "{$tanggal} harus bisa dibetulkan sesudah versi terbit.");
        }

        /* "Isi beku" dinyatakan sendiri, bukan disimpulkan dari daftar kosong —
           pintu lain (catatan review) bergantung padanya. */
        $this->assertTrue(SopVersiKeadaan::bekuIsi(SopStatus::VERSI_OBSOLETE, $tahap('OBSOLETE')));
        $this->assertTrue(SopVersiKeadaan::bekuIsi(SopStatus::PAKET_DRAFT, $tahap('CANCEL')));
        $this->assertFalse(SopVersiKeadaan::bekuIsi(SopStatus::VERSI_PUBLISHED, $tahap('FILING_REGISTERED')));
        $this->assertFalse(SopVersiKeadaan::bekuIsi(SopStatus::PAKET_DRAFT, $tahap('APPROVAL')));
    }

    /* ══ VERSI TERBIT ═══════════════════════════════════════════════════ */

    public function test_terbit_menerima_field_yang_diizinkan(): void
    {
        [, $idVersi] = $this->versiTerbit();

        $this->assertNull($this->svc()->ubahVersi($idVersi, [
            'Tanggal_Kadaluarsa' => now()->addYears(3)->toDateString(),
            'Tanggal_Review_Berikutnya' => now()->addYears(2)->toDateString(),
            'Ringkasan_Perubahan' => 'Koreksi redaksional',
        ], 1));

        $this->assertSame(now()->addYears(3)->toDateString(),
            substr((string) $this->nilai($idVersi, 'Tanggal_Kadaluarsa'), 0, 10));
        $this->assertSame('Koreksi redaksional', $this->nilai($idVersi, 'Ringkasan_Perubahan'));
    }

    /**
     * Versi terbit yang BELUM dijadwalkan masih boleh diganti berkasnya.
     *
     * Permintaan 5 Okt 2026: salah lampir baru ketahuan sesudah terbit, dan
     * memaksa revisi baru hanya demi berkas yang belum pernah dibaca siapa pun
     * melahirkan versi tanpa riwayat yang berarti.
     */
    public function test_terbit_belum_dijadwalkan_menerima_ganti_berkas(): void
    {
        [, $idVersi] = $this->versiTerbit(['Id_Master_Konten' => null]);
        $konten = DB::table('N_LMS_Master_Konten')->where('Scope', 'SOP')->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten SOP untuk diuji.');
        }

        $this->assertNull($this->svc()->ubahVersi($idVersi, ['Id_Master_Konten' => $konten], 1));
        $this->assertSame((string) $konten, (string) $this->nilai($idVersi, 'Id_Master_Konten'));
    }

    /**
     * Sesudah dijadwalkan ke karyawan, berkas ditolak — DAN isinya tak berubah.
     *
     * Penjaga yang mengembalikan pesan sesudah menulis bukan penjaga.
     */
    public function test_terbit_sudah_dijadwalkan_menolak_ganti_berkas_dan_tak_mengubahnya(): void
    {
        [$idDok, $idVersi] = $this->versiTerbit(['Id_Master_Konten' => null]);
        $konten = DB::table('N_LMS_Master_Konten')->where('Scope', 'SOP')->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten SOP untuk diuji.');
        }
        $this->jadwalkan($idDok);

        $galat = $this->svc()->ubahVersi($idVersi, ['Id_Master_Konten' => $konten], 1);

        $this->assertNotNull($galat);
        $this->assertStringContainsString('dijadwalkan', $galat);
        $this->assertStringContainsString('versi baru', $galat);
        $this->assertNull($this->nilai($idVersi, 'Id_Master_Konten'),
            'Ditolak tapi tetap tertulis.');
    }

    public function test_terbit_menolak_ganti_nomor_versi(): void
    {
        [, $idVersi] = $this->versiTerbit();
        $sebelum = (int) $this->nilai($idVersi, 'Versi_Ke');

        $this->assertNotNull($this->svc()->ubahVersi($idVersi, ['Versi_Ke' => $sebelum + 5], 1));
        $this->assertSame($sebelum, (int) $this->nilai($idVersi, 'Versi_Ke'));
    }

    /**
     * Field terlarang yang nilainya SAMA diabaikan diam-diam.
     *
     * Formulir mengirim seluruh isian, bukan hanya yang berubah. Menolak setiap
     * field terlarang yang ikut terkirim membuat menyimpan menjadi MUSTAHIL —
     * dan penjaga yang membuat pekerjaan mustahil akan dimatikan orang, bukan
     * dipatuhi.
     */
    public function test_terbit_mengabaikan_field_terlarang_yang_nilainya_sama(): void
    {
        [, $idVersi] = $this->versiTerbit();
        $baris = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Id_Master_Konten', 'Versi_Ke', 'Tanggal_Berlaku']);

        $this->assertNull($this->svc()->ubahVersi($idVersi, [
            'Id_Master_Konten' => $baris->Id_Master_Konten,
            'Versi_Ke' => $baris->Versi_Ke,
            'Tanggal_Berlaku' => $baris->Tanggal_Berlaku,
            'Ringkasan_Perubahan' => 'Hanya ini yang berubah',
        ], 1), 'Kiriman yang hanya mengulang nilai lama ikut ditolak.');

        $this->assertSame('Hanya ini yang berubah', $this->nilai($idVersi, 'Ringkasan_Perubahan'));
    }

    /**
     * SIMPAN PARSIAL TAK BOLEH MENGOSONGKAN kolom lain.
     *
     * Ditemukan saat menjalankan penjaganya: larik update dirakit dengan
     * `?? null`, benar untuk DRAFT yang formulirnya penuh, tetapi pada versi
     * terbit formulirnya sempit — menyimpan ringkasan saja menghapus tanggal
     * kadaluarsa yang tak ikut terkirim, tanpa satu pun galat.
     */
    public function test_simpan_parsial_tak_mengosongkan_kolom_lain(): void
    {
        $konten = DB::table('N_LMS_Master_Konten')->where('Scope', 'SOP')->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten SOP untuk membuktikan isi versi tetap utuh.');
        }

        /* Berkasnya dipasang SENGAJA. Tanpa itu assertion "isi versi tak
           terhapus" lulus atas kolom yang memang sudah kosong sejak awal —
           hijau yang tak membuktikan apa pun. */
        [, $idVersi] = $this->versiTerbit(['Id_Master_Konten' => $konten]);

        $svc = $this->svc();
        $svc->ubahVersi($idVersi, ['Tanggal_Kadaluarsa' => now()->addYears(3)->toDateString()], 1);
        $svc->ubahVersi($idVersi, ['Ringkasan_Perubahan' => 'Simpanan kedua'], 1);

        $this->assertNotEmpty($this->nilai($idVersi, 'Tanggal_Kadaluarsa'),
            'Simpanan kedua mengosongkan tanggal kadaluarsa yang tak ikut terkirim.');
        $this->assertNotEmpty($this->nilai($idVersi, 'Id_Master_Konten'),
            'Simpanan parsial menghapus isi versi yang sudah terbit.');
    }

    /**
     * Menyimpan versi terbit BUKAN menerbitkan ulang.
     *
     * Tak menggeser pointer, tak melahirkan versi, tak menjalankan fan-out.
     * Karena itu pula pertanyaan minor/mayor tak perlu muncul: tak ada
     * kewajiban karyawan yang dibuka ulang.
     */
    public function test_simpan_versi_terbit_tak_menggeser_pointer_maupun_melahirkan_versi(): void
    {
        [$idDok, $idVersi] = $this->versiTerbit();
        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->update(['Id_Versi_Aktif' => $idVersi]);

        $jumlah = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Dokumen', $idDok)->count();

        $this->svc()->ubahVersi($idVersi, ['Ringkasan_Perubahan' => 'Perpanjangan masa berlaku'], 1);

        $this->assertSame($idVersi,
            (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('Id_Versi_Aktif'));
        $this->assertSame($jumlah,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Dokumen', $idDok)->count());
    }

    /* ══ ATURAN TANGGAL ═════════════════════════════════════════════════ */

    /**
     * Tanggal yang sudah TERLEWATI kini bisa dibetulkan.
     *
     * Aturan lama menolaknya dengan alasan "yang sudah terjadi tak bisa
     * dijadwalkan ulang". Alasan itu benar untuk penjadwalan, tetapi fitur ini
     * juga MENCATAT dokumen yang sudah berjalan di kertas: pada migrasi
     * konvensional ke digital, tanggal approval sebuah dokumen lama selalu di
     * masa lalu, dan salah ketiknya baru ketahuan setelah tersimpan. Aturan itu
     * membuat satu-satunya jalan pembetulan adalah menerbitkan versi baru yang
     * isinya sama persis. Dicabut 21 Sep 2026 atas permintaan produk.
     */
    public function test_tanggal_yang_sudah_lewat_bisa_dibetulkan(): void
    {
        [, $idVersi] = $this->versiTerbit(['Tanggal_Kadaluarsa' => now()->subMonth()->toDateString()]);

        $this->assertNull($this->svc()->ubahVersi($idVersi, [
            'Tanggal_Kadaluarsa' => now()->addYear()->toDateString(),
        ], 1), 'Tanggal terlewati harus bisa dibetulkan.');

        $this->assertSame(now()->addYear()->toDateString(),
            substr((string) $this->nilai($idVersi, 'Tanggal_Kadaluarsa'), 0, 10));
    }

    /**
     * Seluruh tanggal proses — termasuk yang dulu terkunci sesudah terbit —
     * benar-benar tersimpan, bukan sekadar lolos penjaga.
     */
    public function test_seluruh_tanggal_proses_tersimpan_pada_versi_terbit(): void
    {
        [, $idVersi] = $this->versiTerbit();

        $isi = [
            'Tanggal_Permintaan' => '2019-01-05',
            'Tanggal_Respon' => '2019-01-10',
            'Tanggal_Akhir_Approval' => '2019-02-01',
            'Tanggal_Berlaku' => '2019-03-01',
            'Tanggal_Pengesahan' => '2019-02-05',
        ];

        $this->assertNull($this->svc()->ubahVersi($idVersi, $isi, 1));

        foreach ($isi as $kolom => $nilai) {
            $this->assertSame($nilai, substr((string) $this->nilai($idVersi, $kolom), 0, 10),
                "{$kolom} tak tersimpan.");
        }
    }

    /** Arsip menerima tanggal, tetapi ISI-nya tetap beku. */
    public function test_arsip_menerima_tanggal_tetapi_menolak_isi(): void
    {
        [, $idVersi] = $this->versiTerbit();
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update([
            'Status' => SopStatus::VERSI_OBSOLETE,
            'Tahap_Id' => DB::table('N_SOP_Master_Tahap_Versi')
                ->where('Kode_Tahap', 'OBSOLETE')->value('Id_Tahap'),
        ]);

        $this->assertNull($this->svc()->ubahVersi($idVersi, [
            'Tanggal_Akhir_Approval' => '2018-12-31',
        ], 1), 'Riwayat harus menerima pembetulan tanggal.');
        $this->assertSame('2018-12-31',
            substr((string) $this->nilai($idVersi, 'Tanggal_Akhir_Approval'), 0, 10));

        $galat = $this->svc()->ubahVersi($idVersi, ['Versi_Ke' => 99], 1);
        $this->assertNotNull($galat, 'Nomor versi tak boleh ikut terbuka pada riwayat.');
        $this->assertNotSame(99, (int) $this->nilai($idVersi, 'Versi_Ke'));
    }

    /** Yang BELUM lewat tetap bisa digeser — penjaga yang selalu menyala tak berguna. */
    public function test_tanggal_yang_belum_lewat_masih_bisa_digeser(): void
    {
        [, $idVersi] = $this->versiTerbit(['Tanggal_Kadaluarsa' => now()->addMonth()->toDateString()]);

        $this->assertNull($this->svc()->ubahVersi($idVersi, [
            'Tanggal_Kadaluarsa' => now()->addYears(2)->toDateString(),
        ], 1));
    }

    /* ══ YANG BEKU ══════════════════════════════════════════════════════ */

    public function test_versi_batal_menolak_setiap_field(): void
    {
        [$idDok, $idVersi] = $this->versiDraft();
        $tahap = app(SopTahapVersiService::class);
        foreach (['REVIEW', 'APPROVAL', 'CANCEL'] as $kode) {
            $tahap->lupakanNilai($idVersi);
            $tahap->pindah($idVersi, $kode, 1);
        }

        $galat = $this->svc()->ubahVersi($idVersi, ['Ringkasan_Perubahan' => 'x'], 1);
        $this->assertNotNull($galat, 'Versi batal masih menerima perubahan isi.');
        $this->assertSame('Awal', $this->nilai($idVersi, 'Ringkasan_Perubahan'));

        /* Tanggal DIBUKA sejak 21 Sep 2026: arsip dari migrasi dokumen lama
           kerap salah tanggal, dan memperbaikinya tak mengubah riwayatnya. */
        $this->assertNull(
            $this->svc()->ubahVersi($idVersi, ['Tanggal_Kadaluarsa' => '2030-01-01'], 1),
            'Arsip harus menerima pembetulan tanggal.',
        );
        $this->assertSame('2030-01-01', substr((string) $this->nilai($idVersi, 'Tanggal_Kadaluarsa'), 0, 10));
        $this->assertNotNull($idDok);
    }

    public function test_versi_obsolete_menolak_isi_versinya(): void
    {
        [, $idVersi] = $this->versiTerbit();
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update([
            'Status' => SopStatus::VERSI_OBSOLETE,
            'Tahap_Id' => DB::table('N_SOP_Master_Tahap_Versi')
                ->where('Kode_Tahap', 'OBSOLETE')->value('Id_Tahap'),
        ]);

        $galat = $this->svc()->ubahVersi($idVersi, ['Ringkasan_Perubahan' => 'x'], 1);

        $this->assertNotNull($galat, 'Riwayat masih bisa diubah.');
        $this->assertStringContainsString('riwayat', strtolower($galat));
        $this->assertSame('Awal', $this->nilai($idVersi, 'Ringkasan_Perubahan'));
    }

    /* ══ MUATAN KE LAYAR ════════════════════════════════════════════════ */

    /**
     * Layar menerima daftar yang SAMA dengan yang ditegakkan service.
     *
     * Diuji lewat alur, bukan lewat metode: yang berulang sepanjang 30 Agu 2026
     * adalah layar menawarkan sesuatu yang server tolak, dan itu hanya terlihat
     * bila keduanya dibandingkan.
     */
    public function test_muatan_membawa_izin_yang_sama_dengan_penjaganya(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }

        [$idDok, $idVersi] = $this->versiTerbit();

        $baris = collect(
            $this->actingAs($admin)
                ->getJson('/api/v1/sop/admin/dokumen/'
                    .Hashids::connection('custom')->encode($idDok).'/versi?limit=50')
                ->assertOk()->json('result.rows')
        )->firstWhere('Id_SOP_Item_Versi', Hashids::connection('custom')->encode($idVersi));

        $this->assertNotNull($baris);
        $tahap = DB::table('N_SOP_Master_Tahap_Versi')->where('Kode_Tahap', 'FILING_REGISTERED')
            ->first(['Kode_Tahap', 'Nonaktifkan_Versi']);
        $this->assertSame(
            SopVersiKeadaan::fieldBolehUbah(SopStatus::VERSI_PUBLISHED, $tahap, sudahDijadwalkan: false),
            $baris['Field_Boleh_Ubah'],
            'Layar menerima daftar izin yang berbeda dari yang ditegakkan service — '
            .'admin akan disodori isian yang server tolak.');
    }

    /**
     * Versi terbit membawa ISI-nya ke layar, walau isinya tak boleh diganti.
     *
     * Muatan dulu membuang Id_Master_Konten dan Id_Master_Ujian untuk setiap
     * versi non-draft — masuk akal selama wizard hanya bisa dibuka untuk draft.
     * Sejak versi terbit boleh dibuka untuk perubahan terbatas, pembuangan itu
     * membuat kartu "Lampiran Berkas Prosedur" tampil KOSONG pada versi yang
     * jelas punya berkas: layar melaporkan tak ada lampiran pada dokumen yang
     * justru sedang dibaca karyawan.
     *
     * Yang menahan PENGGANTIANNYA adalah Field_Boleh_Ubah, bukan hilangnya
     * kolomnya — menyembunyikan data supaya tak bisa diubah adalah penjaga yang
     * salah alamat, dan harganya dibayar dengan layar yang berbohong.
     */
    public function test_versi_terbit_membawa_isi_versinya_ke_layar(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }

        $konten = DB::table('N_LMS_Master_Konten')->where('Scope', 'SOP')->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten SOP untuk dilampirkan.');
        }

        [$idDok, $idVersi] = $this->versiTerbit(['Id_Master_Konten' => $konten]);

        $baris = collect(
            $this->actingAs($admin)
                ->getJson('/api/v1/sop/admin/dokumen/'
                    .Hashids::connection('custom')->encode($idDok).'/versi?limit=50')
                ->assertOk()->json('result.rows')
        )->firstWhere('Id_SOP_Item_Versi', Hashids::connection('custom')->encode($idVersi));

        $this->assertNotNull($baris);
        $this->assertTrue($baris['Punya_Berkas']);
        $this->assertNotEmpty($baris['Id_Master_Konten'] ?? null,
            'Versi terbit tak membawa isi versinya — kartu lampiran tampil kosong '
            .'pada versi yang jelas punya berkas.');
        $this->assertNotEmpty($baris['Konten_Nama'] ?? null);

        /* Belum dijadwalkan: masih boleh diganti. */
        $this->assertContains('Id_Master_Konten', $baris['Field_Boleh_Ubah']);

        /* Sudah dijadwalkan: terlihat, tapi tak boleh diganti. */
        $this->jadwalkan($idDok);
        $baris = collect(
            $this->actingAs($admin)
                ->getJson('/api/v1/sop/admin/dokumen/'
                    .Hashids::connection('custom')->encode($idDok).'/versi?limit=50')
                ->assertOk()->json('result.rows')
        )->firstWhere('Id_SOP_Item_Versi', Hashids::connection('custom')->encode($idVersi));
        $this->assertNotEmpty($baris['Id_Master_Konten'] ?? null);
        $this->assertNotContains('Id_Master_Konten', $baris['Field_Boleh_Ubah']);
    }

    /**
     * CATATAN REVIEW ikut terkunci pada versi batal DAN riwayat.
     *
     * Pintu ini punya penjaganya sendiri di controller, dan penjaga itu semula
     * hanya menanyakan `dibatalkan()` — sehingga versi OBSOLETE lolos, padahal
     * ia riwayat. Satu pertanyaan dijawab dua rumus adalah cara cacat
     * berikutnya lahir; keduanya kini memakai kebijakan yang sama.
     */
    public function test_catatan_review_terkunci_pada_batal_dan_riwayat(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }

        /* Riwayat (obsolete). */
        [, $idObs] = $this->versiTerbit();
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idObs)->update([
            'Status' => SopStatus::VERSI_OBSOLETE,
            'Tahap_Id' => DB::table('N_SOP_Master_Tahap_Versi')
                ->where('Kode_Tahap', 'OBSOLETE')->value('Id_Tahap'),
        ]);

        /* Arsip (dibatalkan). */
        [, $idBatal] = $this->versiDraft();
        $tahap = app(SopTahapVersiService::class);
        foreach (['REVIEW', 'APPROVAL', 'CANCEL'] as $kode) {
            $tahap->lupakanNilai($idBatal);
            $tahap->pindah($idBatal, $kode, 1);
        }

        foreach ([$idObs => 'riwayat', $idBatal => 'arsip'] as $idVersi => $sebutan) {
            $sebelum = DB::table('N_SOP_Item_Versi_Review')
                ->where('Id_SOP_Item_Versi', $idVersi)->count();

            $this->actingAs($admin)
                ->putJson('/api/v1/sop/admin/dokumen/versi/'
                    .Hashids::connection('custom')->encode($idVersi).'/review', [
                        'Review' => [[
                            'Review_Ke' => 1,
                            'Tanggal_Review' => now()->toDateString(),
                            'Hasil_Review' => 'Catatan pada versi '.$sebutan,
                        ]],
                    ])
                ->assertStatus(422);

            $this->assertSame($sebelum,
                DB::table('N_SOP_Item_Versi_Review')->where('Id_SOP_Item_Versi', $idVersi)->count(),
                "Catatan review tetap tersimpan pada versi {$sebutan}.");
        }
    }

    /**
     * Penilai "terkunci penuh" menjawab dari KEADAAN versi.
     *
     * Dulu ia menyimpulkannya dari "daftar izin kosong". Sejak arsip menerima
     * pembetulan tanggal, daftar itu tak pernah kosong — dan pintu yang
     * memakainya (catatan review) akan ikut terbuka tanpa ada yang memutuskan.
     */
    public function test_terkunci_penuh_sejalan_dengan_daftar_izin(): void
    {
        [, $idTerbit] = $this->versiTerbit();
        $this->assertNull($this->svc()->terkunciPenuh($idTerbit),
            'Versi terbit masih menerima perubahan terbatas — tak boleh dinyatakan terkunci penuh.');

        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idTerbit)->update([
            'Status' => SopStatus::VERSI_OBSOLETE,
            'Tahap_Id' => DB::table('N_SOP_Master_Tahap_Versi')
                ->where('Kode_Tahap', 'OBSOLETE')->value('Id_Tahap'),
        ]);

        $alasan = $this->svc()->terkunciPenuh($idTerbit);
        $this->assertNotNull($alasan);
        $this->assertStringContainsString('riwayat', strtolower($alasan));
    }

    /** Kelas ini tak boleh ikut mematikan jalur pembuatan versi. */
    public function test_draft_tetap_bisa_disunting_seperti_biasa(): void
    {
        [, $idVersi] = $this->versiDraft();

        $this->assertNull($this->svc()->ubahVersi($idVersi, [
            'Ringkasan_Perubahan' => 'Diubah saat draft',
            'Tanggal_Berlaku' => now()->addWeek()->toDateString(),
        ], 1));

        $this->assertSame('Diubah saat draft', $this->nilai($idVersi, 'Ringkasan_Perubahan'));
        $this->assertNotNull(app(SopItemVersionService::class));
    }
}
