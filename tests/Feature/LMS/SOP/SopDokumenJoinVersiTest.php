<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopMonitoringService;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 4 Master Dokumen SOP — join yang menyimpulkan ITEM DARI VERSI.
 *
 * MASALAHNYA BUKAN PENGGANDAAN BARIS. N_SOP_Item_Versi.Id_SOP_Paket_Item adalah
 * FK TUNGGAL yang menunjuk satu item — yaitu item yang kebetulan membuat versi
 * itu. Begitu satu dokumen dirujuk beberapa paket, join lewat FK tersebut tidak
 * menggandakan apa pun; ia mengembalikan item YANG SALAH. Monitoring paket P2
 * menampilkan judul/tipe/urutan/section milik item di P1, dan otorisasi menolak
 * karyawan yang sebenarnya sah. Salah atribusi jauh lebih sulit terlihat
 * daripada baris ganda.
 *
 * Setiap test di sini mensimulasikan berbagi dokumen, karena data dev
 * sepenuhnya 1 dokumen = 1 item — tanpa simulasi, keempat situs akan "lulus"
 * tanpa pernah dieksekusi pada keadaan yang merusaknya.
 */
class SopDokumenJoinVersiTest extends TestCase
{
    use DatabaseTransactions;

    /** Item ber-versi aktif yang sudah punya baris rollup di paketnya sendiri. */
    private function itemBerVersiDenganRollup(): ?object
    {
        return DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->join('N_SOP_Coverage_Rollup as r', function ($j) {
                $j->on('r.Id_SOP_Item_Versi', '=', 'v.Id_SOP_Item_Versi')
                    ->on('r.Id_SOP_Paket', '=', 'i.Id_SOP_Paket');
            })
            ->whereIn('i.Tipe_Item', SopTipeItem::VERSIONED)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->first([
                'i.Id_SOP_Paket_Item', 'i.Id_SOP_Paket', 'i.Id_SOP_Dokumen', 'i.Tipe_Item',
                'i.Flag_Wajib', 'i.Flag_Highlight', 'v.Id_SOP_Item_Versi',
            ]);
    }

    private function paketLain(int $kecuali): ?int
    {
        $id = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_SOP_Paket', '!=', $kecuali)
            ->value('Id_SOP_Paket');

        return $id !== null ? (int) $id : null;
    }

    /** Item kedua ber-judul KHAS di paket lain, berbagi dokumen & versi aktif yang sama. */
    private function itemKeduaBerbagi(object $item, int $idPaketLain, string $judul): int
    {
        return (int) DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket' => $idPaketLain,
            'Tipe_Item' => $item->Tipe_Item,
            'Judul_Item' => $judul,
            'Id_SOP_Dokumen' => $item->Id_SOP_Dokumen,
            'Id_Item_Versi_Aktif' => $item->Id_SOP_Item_Versi,
            'Urutan' => 9999,
            'Flag_Wajib' => $item->Flag_Wajib,
            'Flag_Highlight' => $item->Flag_Highlight,
            'Created_By' => 1,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');
    }

    public function test_detail_paket_menampilkan_judul_item_paket_itu_bukan_paket_lain(): void
    {
        $item = $this->itemBerVersiDenganRollup();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-versi dengan baris rollup di data dev.');
        }

        $paketLain = $this->paketLain((int) $item->Id_SOP_Paket);
        if (! $paketLain) {
            $this->markTestSkipped('Tak ada paket lain.');
        }

        $this->itemKeduaBerbagi($item, $paketLain, 'JUDUL KHAS PAKET LAIN');

        // Baris rollup untuk paket LAIN atas versi yang sama.
        DB::table('N_SOP_Coverage_Rollup')->insert([
            'Id_SOP_Paket' => $paketLain,
            'Id_SOP_Item_Versi' => $item->Id_SOP_Item_Versi,
            'Total_Target' => 1,
            'Jumlah_Dibuka' => 0,
            'Jumlah_Dikonfirmasi' => 0,
            'Persen_Dikonfirmasi' => 0,
            'Updated_At' => now(),
        ]);

        $judul = collect(app(SopMonitoringService::class)->detailPaket($paketLain)['items'])
            ->pluck('Judul_Item')->all();

        $this->assertContains('JUDUL KHAS PAKET LAIN', $judul,
            'Monitoring sebuah paket harus menampilkan item MILIK PAKET ITU. Menautkan lewat '
            .'v.Id_SOP_Paket_Item membuat baris rollup paket ini menampilkan judul, tipe, dan '
            .'urutan milik item di paket lain — salah atribusi yang senyap.');
    }

    public function test_section_coverage_tidak_mengelompokkan_ke_section_paket_lain(): void
    {
        $item = $this->itemBerVersiDenganRollup();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-versi dengan baris rollup di data dev.');
        }

        $paketLain = $this->paketLain((int) $item->Id_SOP_Paket);
        if (! $paketLain) {
            $this->markTestSkipped('Tak ada paket lain.');
        }

        $this->itemKeduaBerbagi($item, $paketLain, 'JUDUL KHAS SECTION');
        DB::table('N_SOP_Coverage_Rollup')->insert([
            'Id_SOP_Paket' => $paketLain,
            'Id_SOP_Item_Versi' => $item->Id_SOP_Item_Versi,
            'Total_Target' => 1,
            'Jumlah_Dibuka' => 0,
            'Jumlah_Dikonfirmasi' => 0,
            'Persen_Dikonfirmasi' => 0,
            'Updated_At' => now(),
        ]);

        // sectionCoverage() mengembalikan Nama_Section (ISNULL -> 'Tanpa Bagian'),
        // bukan Id_SOP_Section — jadi yang dibandingkan namanya.
        $namaSah = DB::table('N_SOP_Paket_Section')
            ->where('Id_SOP_Paket', $paketLain)->pluck('Nama_Section')->all();
        $namaSah[] = 'Tanpa Bagian';

        foreach (app(SopMonitoringService::class)->sectionCoverage($paketLain) as $baris) {
            $this->assertContains($baris->Nama_Section, $namaSah,
                'Cakupan paket ini dikelompokkan ke bagian milik paket LAIN — bagian yang '
                .'bahkan tidak ada di paket ini.');
        }
    }

    /**
     * Jalur mundur untuk versi ber-Id_SOP_Dokumen NULL bukan kehati-hatian
     * teoretis: di data dev 18 dari 32 baris rollup (56%) menunjuk versi
     * semacam itu — milik item yang Tipe_Item-nya pernah diubah menjauh dari
     * tipe ber-versi tapi pointer versi aktifnya tertinggal. Tanpa cabang FK
     * lama, lebih dari separuh isi monitoring lenyap tanpa satu pun pesan.
     */
    public function test_versi_tanpa_dokumen_tetap_muncul_di_monitoring(): void
    {
        $yatim = DB::table('N_SOP_Coverage_Rollup as r')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'r.Id_SOP_Item_Versi')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->whereNull('v.Id_SOP_Dokumen')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->whereColumn('i.Id_Item_Versi_Aktif', 'v.Id_SOP_Item_Versi')
            ->first(['r.Id_SOP_Paket', 'i.Judul_Item']);
        if (! $yatim) {
            $this->markTestSkipped('Tak ada baris rollup ber-versi tanpa dokumen di data dev.');
        }

        $judul = collect(app(SopMonitoringService::class)->detailPaket((int) $yatim->Id_SOP_Paket)['items'])
            ->pluck('Judul_Item')->all();

        $this->assertContains($yatim->Judul_Item, $judul,
            'Baris rollup yang versinya tak punya dokumen wajib tetap muncul lewat cabang FK '
            .'lama. NULL = NULL bernilai NULL (bukan true), jadi perbandingan dokumen tak '
            .'akan pernah menangkapnya.');
    }

    // ---------------------------------------------------------------------
    // versiVisibleInPaket — berhenti salah menolak
    // ---------------------------------------------------------------------

    /**
     * Paket dengan penugasan aktif yang TIDAK punya snapshot rilis.
     *
     * Cabang DB di versiVisibleInPaket() hanya jalan bila snapshot tak ada —
     * dan di data dev itu justru keadaan yang dominan: hanya 1 dari 12 paket
     * punya snapshot, sementara 7 paket PUBLISHED dengan penugasan aktif
     * (sampai 34 orang) tidak. Cabang ini bukan kasus pinggir.
     */
    private function paketTanpaSnapshotDenganPenugasan(int $kecuali): ?object
    {
        $svc = new \App\Services\LMS\SOP\SopPaketVersionService;

        foreach (DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_SOP_Paket', '!=', $kecuali)->get(['Id_SOP_Paket']) as $p) {
            if ($svc->releasedSnapshot((int) $p->Id_SOP_Paket)) {
                continue;
            }

            $kode = DB::table('N_SOP_Penjadwalan as pj')
                ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
                ->where('pj.Id_SOP_Paket', $p->Id_SOP_Paket)
                ->where('pj.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Cabut_Akses', 'T')
                ->value('d.Kode_Karyawan');

            if ($kode) {
                return (object) ['Id_SOP_Paket' => (int) $p->Id_SOP_Paket, 'Kode_Karyawan' => $kode];
            }
        }

        return null;
    }

    public function test_versi_terlihat_di_paket_lain_yang_berbagi_dokumen(): void
    {
        $item = $this->itemBerVersiDenganRollup();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-versi dengan rollup di data dev.');
        }

        $versi = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $item->Id_SOP_Item_Versi)
            ->first(['Id_SOP_Item_Versi', 'Status']);
        if (! in_array($versi->Status, [SopStatus::PAKET_PUBLISHED, 'OBSOLETE'], true)) {
            $this->markTestSkipped('Versi sumber bukan PUBLISHED/OBSOLETE.');
        }

        $tujuan = $this->paketTanpaSnapshotDenganPenugasan((int) $item->Id_SOP_Paket);
        if (! $tujuan) {
            $this->markTestSkipped('Tak ada paket tanpa snapshot dengan penugasan aktif.');
        }

        $this->itemKeduaBerbagi($item, $tujuan->Id_SOP_Paket, 'BERBAGI DOKUMEN');

        $this->assertTrue(
            app(\App\Services\LMS\SOP\SopProgressReadService::class)
                ->versiVisibleInPaket($tujuan->Kode_Karyawan, $tujuan->Id_SOP_Paket, (int) $versi->Id_SOP_Item_Versi),
            'Karyawan yang sah di paket ini DITOLAK hanya karena versinya "milik" item di paket '
            .'lain. Menautkan lewat v.Id_SOP_Paket_Item membuat otorisasi salah menolak — '
            .'bukan salah mengizinkan, tapi sama-sama cacat otorisasi.');
    }

    public function test_versi_tetap_tak_terlihat_di_paket_yang_tak_menyajikan_dokumennya(): void
    {
        $item = $this->itemBerVersiDenganRollup();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-versi dengan rollup di data dev.');
        }

        $tujuan = $this->paketTanpaSnapshotDenganPenugasan((int) $item->Id_SOP_Paket);
        if (! $tujuan) {
            $this->markTestSkipped('Tak ada paket tanpa snapshot dengan penugasan aktif.');
        }

        // TANPA menautkan item kedua: paket tujuan tak menyajikan dokumen itu.
        $this->assertFalse(
            app(\App\Services\LMS\SOP\SopProgressReadService::class)
                ->versiVisibleInPaket($tujuan->Kode_Karyawan, $tujuan->Id_SOP_Paket, (int) $item->Id_SOP_Item_Versi),
            'Pelebaran harus berhenti pada paket yang BENAR-BENAR menyajikan dokumen itu. '
            .'Kalau tidak, perbaikan salah-tolak berubah jadi celah salah-izinkan.');
    }

    // ---------------------------------------------------------------------
    // Akses PDF aman — perubahan semantik keamanan yang disengaja
    // ---------------------------------------------------------------------

    /**
     * Berkas PDF nyata yang kontennya HANYA hidup di paket TERBATAS.
     *
     * Syarat itu bukan kerewelan. lewatJalurPublik() sengaja memberi akses ke
     * siapa pun untuk konten di paket PUBLIK terbit — itu jalur Perpustakaan,
     * dan docblock-nya menyatakannya eksplisit. Pada konten publik, canAccess()
     * mengembalikan true bahkan untuk kode karyawan yang tak ada, sehingga
     * cabang penugasan (yang justru diperbaiki di sini) tak pernah menentukan
     * apa pun. Hanya pada konten TERBATAS jalur publik menutup diri dan
     * penelusuran lewat item/paket benar-benar jadi penentu.
     *
     * Paket TUJUAN juga wajib belum menyajikan konten itu — kalau sudah, ia
     * punya jalannya sendiri dan test tak membuktikan apa pun.
     */
    private function berkasUntukUjiAkses(): ?object
    {
        $baris = DB::table('N_LMS_Master_Konten_Berkas as b')
            ->join('N_SOP_Item_Versi as v', 'v.Id_Master_Konten', '=', 'b.Id_Master_Konten')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('v.Id_SOP_Dokumen')
            /* Versinya wajib TERBIT. canAccess() memang hanya membagikan berkas
               milik versi berlaku — versi draft & cabutan ditolak, dan itu
               benar. Tanpa syarat ini fixture bisa memilih versi cabutan, lalu
               penolakan yang SAH terbaca sebagai cacat join yang tak pernah
               ada. Yang diuji di sini jalur penelusurannya, bukan statusnya. */
            ->where('v.Status', SopStatus::PAKET_PUBLISHED)
            // Konten ini tak boleh terjangkau lewat paket PUBLIK terbit mana pun.
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_SOP_Item_Versi as v3')
                    ->join('N_SOP_Paket_Item as i3', 'i3.Id_SOP_Paket_Item', '=', 'v3.Id_SOP_Paket_Item')
                    ->join('N_SOP_Master_Paket as p3', 'p3.Id_SOP_Paket', '=', 'i3.Id_SOP_Paket')
                    ->whereColumn('v3.Id_Master_Konten', 'b.Id_Master_Konten')
                    ->where('i3.Flag_Aktif', SopStatus::AKTIF)
                    ->where('p3.Flag_Aktif', SopStatus::AKTIF)
                    ->where('p3.Status', SopStatus::PAKET_PUBLISHED)
                    ->where(function ($w) {
                        $w->whereNull('p3.Mode_Distribusi')->orWhere('p3.Mode_Distribusi', '<>', 'TERBATAS');
                    });
            })
            ->get(['b.Id_Master_Konten_Berkas', 'b.Id_Master_Konten', 'v.Id_SOP_Item_Versi',
                'v.Id_SOP_Dokumen', 'i.Id_SOP_Paket', 'i.Tipe_Item', 'i.Flag_Wajib', 'i.Flag_Highlight']);

        $absensi = app(\App\Services\LMS\SOP\SopAbsensiService::class);

        foreach ($baris as $b) {
            // Paket berpenugasan yang BELUM punya item ber-konten ini.
            $kandidat = DB::table('N_SOP_Penjadwalan as pj')
                ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
                ->where('pj.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Cabut_Akses', 'T')
                ->where('pj.Id_SOP_Paket', '!=', $b->Id_SOP_Paket)
                ->whereNotExists(function ($q) use ($b) {
                    $q->select(DB::raw(1))->from('N_SOP_Paket_Item as i2')
                        ->join('N_SOP_Item_Versi as v2', 'v2.Id_SOP_Paket_Item', '=', 'i2.Id_SOP_Paket_Item')
                        ->whereColumn('i2.Id_SOP_Paket', 'pj.Id_SOP_Paket')
                        ->where('v2.Id_Master_Konten', $b->Id_Master_Konten);
                })
                ->distinct()
                ->get(['pj.Id_SOP_Paket', 'd.Kode_Karyawan']);

            foreach ($kandidat as $t) {
                /* Gerbang presensi diperiksa DI SINI, bukan diabaikan. canAccess()
                   menolak paket yang terkunci presensi — benar, dan bukan urusan
                   join. Fixture yang mengambil paket terkunci akan membuat test
                   ini gagal karena alasan yang salah dan menyamar sebagai cacat
                   join yang tak pernah ada. */
                if ($absensi->terkunci($t->Kode_Karyawan, (int) $t->Id_SOP_Paket)) {
                    continue;
                }

                $b->Paket_Tujuan = (int) $t->Id_SOP_Paket;
                $b->Kode_Karyawan = $t->Kode_Karyawan;

                return $b;
            }
        }

        return null;
    }

    public function test_karyawan_di_paket_yang_berbagi_dokumen_boleh_membuka_berkas(): void
    {
        $b = $this->berkasUntukUjiAkses();
        if (! $b) {
            $this->markTestSkipped('Tak ada kombinasi berkas/paket tujuan yang cocok di data dev.');
        }

        $svc = app(\App\Services\LMS\SOP\SopSecurePdfAccessService::class);

        // Item di paket tujuan yang berbagi DOKUMEN (bukan punya versi sendiri).
        DB::table('N_SOP_Paket_Item')->insert([
            'Id_SOP_Paket' => $b->Paket_Tujuan,
            'Tipe_Item' => $b->Tipe_Item,
            'Judul_Item' => 'BERBAGI DOKUMEN PDF',
            'Id_SOP_Dokumen' => $b->Id_SOP_Dokumen,
            'Id_Item_Versi_Aktif' => $b->Id_SOP_Item_Versi,
            'Urutan' => 9999,
            'Flag_Wajib' => $b->Flag_Wajib,
            'Flag_Highlight' => $b->Flag_Highlight,
            'Created_By' => 1,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ]);

        $this->assertTrue($svc->canAccess($b->Kode_Karyawan, (int) $b->Id_Master_Konten_Berkas),
            'Karyawan yang ditugaskan ke paket yang menyajikan dokumen ini harus boleh '
            .'membuka berkasnya. Penelusuran lewat v.Id_SOP_Paket_Item hanya menemukan '
            .'paket item pembuat versi, sehingga menolak akses yang sah lewat paket lain.');
    }

    public function test_karyawan_tanpa_penugasan_ke_konten_terbatas_tetap_ditolak(): void
    {
        $b = $this->berkasUntukUjiAkses();
        if (! $b) {
            $this->markTestSkipped('Tak ada berkas ber-konten TERBATAS yang cocok di data dev.');
        }

        $this->assertFalse(
            app(\App\Services\LMS\SOP\SopSecurePdfAccessService::class)
                ->canAccess('KODE-TIDAK-ADA-9999', (int) $b->Id_Master_Konten_Berkas),
            'Pelebaran akses harus berhenti pada karyawan yang benar-benar punya jalan masuk. '
            .'Untuk konten TERBATAS, jalur publik tertutup — kalau kode karyawan asing tetap '
            .'lolos, perbaikan salah-tolak sudah berubah jadi celah salah-izinkan.');
    }

    // ---------------------------------------------------------------------
    // Dua situs yang TERLEWAT dari daftar rencana Fase 4
    // ---------------------------------------------------------------------

    /**
     * Register kontrol dokumen men-join item<->versi DUA KALI: lewat FK
     * (i.Id_SOP_Paket_Item = v.Id_SOP_Paket_Item) DAN lewat pointer
     * (i.Id_Item_Versi_Aktif = v.Id_SOP_Item_Versi).
     *
     * Setelah fan-out Fase 3 memasang pointer pada item yang berbagi dokumen,
     * syarat FK-nya gagal — dan item itu LENYAP dari register. Register inilah
     * yang ditumbuhkan jadi halaman Master Dokumen di Fase 5, jadi kehilangan
     * baris di sini berarti dokumen terkendali yang tak pernah terlihat.
     */
    public function test_item_berbagi_dokumen_tidak_lenyap_dari_register_kontrol(): void
    {
        $item = $this->itemBerVersiDenganRollup();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-versi dengan rollup di data dev.');
        }

        // Register hanya memuat paket PUBLISHED.
        $paketTerbit = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Status', SopStatus::PAKET_PUBLISHED)
            ->where('Id_SOP_Paket', '!=', $item->Id_SOP_Paket)
            ->value('Id_SOP_Paket');
        if (! $paketTerbit) {
            $this->markTestSkipped('Tak ada paket terbit lain.');
        }

        $baru = $this->itemKeduaBerbagi($item, (int) $paketTerbit, 'ITEM BERBAGI DI REGISTER');

        // register() mengembalikan [rows, total] — bukan map ber-kunci 'rows'.
        [$rows] = app(\App\Services\LMS\SOP\SopDocumentControlService::class)->register([], 1, 500);

        $adaDiRegister = collect($rows)
            ->contains(fn ($r) => ($r->Judul_Item ?? null) === 'ITEM BERBAGI DI REGISTER');

        $this->assertTrue($adaDiRegister,
            "Item #{$baru} menunjuk versi aktif yang sama lewat dokumen bersama, tapi tak muncul "
            .'di register kontrol dokumen. Join lewat FK versi membuangnya — dan register '
            .'inilah yang jadi halaman Master Dokumen di Fase 5.');
    }
}
