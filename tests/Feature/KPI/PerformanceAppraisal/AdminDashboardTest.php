<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaAdminDashboardService;
use App\Services\KPI\PerformanceAppraisal\PaPesertaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dashboard admin dan monitoring progres.
 *
 * YANG PALING PENTING DI BERKAS INI
 *
 * Keterlambatan dihitung DUA KALI di modul ini: PHP untuk satu baris, SQL untuk cacah
 * ribuan. Dua tempat untuk satu gagasan adalah bentuk yang suatu hari berselisih, dan
 * yang berselisih di sini adalah angka yang dipakai mengejar orang. Uji
 * `test_aturan_terlambat_sql_sama_dengan_php` membandingkan keduanya atas baris yang
 * sama persis — itulah satu-satunya cara memastikan keduanya tidak diam-diam berpisah.
 */
class AdminDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $levelId;

    private int $kategoriId;

    /** Karyawan nyata untuk PESERTA; ada FK ke `Karyawan` sehingga kode karangan ditolak. */
    private array $stok = [];

    /**
     * Karyawan nyata untuk PENILAI, terpisah dari stok peserta.
     *
     * `UX_PA_Assignment_Peserta_Penilai` unik per (peserta, penilai, kategori) dan
     * disaring `Flag_Aktif='Y'` — bukan per status. Jadi dua penugasan aktif dengan
     * penilai yang sama untuk peserta yang sama akan bentrok, bahkan ketika salah satunya
     * DIBATALKAN.
     */
    private array $stokPenilai = [];

    protected function setUp(): void
    {
        parent::setUp();

        $semua = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(14)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();

        $this->assertGreaterThanOrEqual(10, count($semua), 'Uji ini butuh beberapa karyawan.');

        $this->stok = array_slice($semua, 0, 7);
        $this->stokPenilai = array_slice($semua, 7);

        $this->levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $this->kategoriId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', 'SELF')->value('Id_PA_Rater_Category');

        $this->cycleId = $this->buatSiklus();
    }

    /* ------------------------------------------------------------------ *
     * Satu aturan terlambat, dua tempat menghitungnya                     *
     * ------------------------------------------------------------------ */

    public function test_aturan_terlambat_sql_sama_dengan_php(): void
    {
        $kemarin = $this->buatPeserta();
        $hariIni = $this->buatPeserta();
        $besok = $this->buatPeserta();

        $this->buatPenugasan($kemarin, now()->subDay()->format('Y-m-d'));
        $this->buatPenugasan($hariIni, now()->format('Y-m-d'));
        $this->buatPenugasan($besok, now()->addDay()->format('Y-m-d'));

        // Sisi SQL.
        $sql = $this->service()->ringkasan($this->cycleId)['penugasan']['terlambat'];

        // Sisi PHP, atas baris yang SAMA PERSIS.
        $peserta = $this->app->make(PaPesertaService::class);
        $metode = new \ReflectionMethod($peserta, 'keterlambatan');
        $metode->setAccessible(true);

        $php = 0;

        foreach (DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $this->cycleId)
            ->get(['a.Due_At', 'a.Status_Penugasan']) as $b) {
            if ($metode->invoke($peserta, $b)['terlambat']) {
                $php++;
            }
        }

        $this->assertSame(1, $sql, 'Hanya tenggat kemarin yang terlambat.');
        $this->assertSame($php, $sql, 'Aturan SQL dan PHP tidak boleh berselisih.');
    }

    public function test_tenggat_hari_ini_belum_terlambat(): void
    {
        $p = $this->buatPeserta();
        $this->buatPenugasan($p, now()->format('Y-m-d'));

        // `Due_At` bertipe DATE dan berarti sampai AKHIR hari itu. Ambang `<=` akan
        // menandai orang terlambat pada hari yang masih miliknya.
        $this->assertSame(0, $this->service()->ringkasan($this->cycleId)['penugasan']['terlambat']);
    }

    public function test_penugasan_selesai_dan_dibatalkan_tidak_terhitung_terlambat(): void
    {
        foreach (['SELESAI', 'DIBATALKAN'] as $status) {
            $p = $this->buatPeserta();
            $this->buatPenugasan($p, now()->subDays(10)->format('Y-m-d'), $status);
        }

        $this->assertSame(0, $this->service()->ringkasan($this->cycleId)['penugasan']['terlambat']);
    }

    /* ------------------------------------------------------------------ *
     * Angka yang harus berjumlah sama                                     *
     * ------------------------------------------------------------------ */

    public function test_jumlah_per_divisi_sama_dengan_total_penugasan(): void
    {
        /* Peserta tanpa divisi TIDAK boleh dibuang dari peta. Kalau dibuang, jumlah per
           divisi tidak akan pernah sama dengan totalnya — selisih yang akan dicari orang
           berjam-jam tanpa petunjuk apa pun. */
        $berdivisi = $this->buatPeserta(divisi: 'PRODUCTION');
        $tanpaDivisi = $this->buatPeserta(divisi: null);

        $this->buatPenugasan($berdivisi, null);
        $this->buatPenugasan($tanpaDivisi, null);

        $r = $this->service()->ringkasan($this->cycleId);

        $this->assertSame(2, $r['penugasan']['total']);
        $this->assertSame(2, array_sum(array_column($r['per_divisi'], 'total')));

        $nama = array_column($r['per_divisi'], 'nama_divisi');
        $this->assertContains('(Tanpa divisi)', $nama);
    }

    public function test_penugasan_dibatalkan_tidak_masuk_hitungan(): void
    {
        $p = $this->buatPeserta();
        $this->buatPenugasan($p, null);
        $this->buatPenugasan($p, null, 'DIBATALKAN');

        $r = $this->service()->ringkasan($this->cycleId);

        $this->assertSame(1, $r['penugasan']['total']);
        $this->assertArrayNotHasKey('DIBATALKAN', $r['penugasan']['per_status']);
    }

    public function test_penyelesaian_persen_dibulatkan_server_sebagai_bilangan_bulat(): void
    {
        $p = $this->buatPeserta();
        $this->buatPenugasan($p, null, 'SELESAI');
        $this->buatPenugasan($p, null, 'BELUM_MULAI');
        $this->buatPenugasan($p, null, 'BELUM_MULAI');

        $r = $this->service()->ringkasan($this->cycleId);

        $this->assertIsInt($r['penugasan']['penyelesaian_persen']);
        $this->assertSame(33, $r['penugasan']['penyelesaian_persen']);
    }

    /* ------------------------------------------------------------------ *
     * Monitoring                                                          *
     * ------------------------------------------------------------------ */

    public function test_peta_panas_selalu_lengkap_selnya(): void
    {
        $a = $this->buatPeserta(divisi: 'PRODUCTION');
        $b = $this->buatPeserta(divisi: 'IT');

        $this->buatPenugasan($a, null, 'SELESAI');
        $this->buatPenugasan($b, null, 'BELUM_MULAI');

        $peta = $this->service()->monitoring($this->cycleId)['peta_panas'];

        $this->assertCount(2, $peta['status'], 'Dua status muncul di siklus ini.');

        foreach ($peta['baris'] as $baris) {
            /* Nol pun ditulis. Sel yang kadang tidak ada memaksa layar menebak apakah
               kosong berarti nol atau berarti belum dimuat. */
            $this->assertCount(count($peta['status']), $baris['sel'], "Divisi {$baris['divisi']}");
        }
    }

    public function test_daftar_terlambat_diurut_paling_lama_lebih_dulu(): void
    {
        $a = $this->buatPeserta();
        $b = $this->buatPeserta();

        $this->buatPenugasan($a, now()->subDays(2)->format('Y-m-d'));
        $this->buatPenugasan($b, now()->subDays(9)->format('Y-m-d'));

        $baris = $this->service()->monitoring($this->cycleId)['terlambat']['baris'];

        $this->assertCount(2, $baris);
        $this->assertSame(9, $baris[0]['hari_terlambat'], 'Yang paling lama terlambat lebih dulu.');
        $this->assertSame(2, $baris[1]['hari_terlambat']);
    }

    public function test_pilihan_saringan_hanya_berisi_yang_ada_di_siklus_ini(): void
    {
        $p = $this->buatPeserta(divisi: 'PRODUCTION');
        $this->buatPenugasan($p, null);

        $pilihan = $this->service()->monitoring($this->cycleId)['pilihan_saringan'];

        /* Diambil dari peserta siklus, bukan dari master. Dropdown berisi divisi yang tak
           seorang pun menempatinya membuat orang menyaring, menatap tabel kosong, lalu
           mengira sistemnya rusak. */
        $this->assertSame(['PRODUCTION'], array_column($pilihan['divisi'], 'nama'));
        $this->assertCount(1, $pilihan['kategori_penilai']);
    }

    public function test_saringan_divisi_mempersempit_daftar_terlambat(): void
    {
        $a = $this->buatPeserta(divisi: 'PRODUCTION', idDivisi: 111);
        $b = $this->buatPeserta(divisi: 'IT', idDivisi: 222);

        $this->buatPenugasan($a, now()->subDays(3)->format('Y-m-d'));
        $this->buatPenugasan($b, now()->subDays(3)->format('Y-m-d'));

        $semua = $this->service()->monitoring($this->cycleId)['terlambat']['total'];
        $satu = $this->service()->monitoring($this->cycleId, ['id_divisi' => 111])['terlambat']['total'];

        $this->assertSame(2, $semua);
        $this->assertSame(1, $satu, 'Saringan harus benar-benar mempersempit.');
    }

    public function test_permintaan_di_atas_plafon_dilaporkan_dipangkas(): void
    {
        $hasil = $this->service()->monitoring($this->cycleId, ['per_halaman' => 5000]);

        // Pembatasan yang tidak diberitahukan membuat orang mengira datanya memang segitu.
        $this->assertTrue($hasil['terlambat']['dipangkas']);
        $this->assertSame(PaAdminDashboardService::MAKS_PER_HALAMAN, $hasil['terlambat']['per_halaman']);
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    /* ------------------------------------------------------------------ *
     * Koreksi tahap Pengisian (7 Okt 2026)                                *
     * ------------------------------------------------------------------ */

    public function test_halaman_pemantauan_per_karyawan_dengan_progres_semua_penilai(): void
    {
        $a = $this->buatPeserta();
        $b = $this->buatPeserta();
        $this->buatPenugasan($a, now()->addDays(3)->format('Y-m-d'), 'SELESAI');
        $this->buatPenugasan($a, now()->addDays(3)->format('Y-m-d'), 'SELESAI');
        $this->buatPenugasan($a, now()->addDays(3)->format('Y-m-d'));
        $this->buatPenugasan($b, now()->addDays(5)->format('Y-m-d'));

        // Satu karyawan per halaman: seluruh penilainya ikut di halaman yang sama.
        $h = $this->service()->monitoring($this->cycleId, ['per_halaman' => 1])['peserta'];
        $this->assertSame(2, $h['total']);
        $this->assertSame(2, $h['total_halaman']);
        $this->assertSame($a, $h['baris'][0]['Id_PA_Participant']);
        $this->assertSame(3, count($h['baris'][0]['penilai']));
        $this->assertSame(['total' => 3, 'selesai' => 2], array_intersect_key($h['baris'][0]['progres'], ['total' => 0, 'selesai' => 0]));

        // Saringan hanya menyempitkan penilai yang ditampilkan; progresnya tetap atas semua penilai.
        $selesai = $this->service()->monitoring($this->cycleId, ['status' => 'SELESAI'])['peserta'];
        $this->assertSame(1, $selesai['total']);
        $this->assertSame(2, count($selesai['baris'][0]['penilai']));
        $this->assertSame(3, $selesai['baris'][0]['progres']['total']);
    }

    /**
     * "Belum Selesai" = SEMUA yang belum SELESAI, dan MENUNGGU_VERIFIKASI punya cacahnya
     * sendiri. Dulu status itu ikut `total` tetapi tidak masuk keranjang mana pun, dan kartu
     * hanya menjumlah belum mulai + berjalan sementara "% sisa" memakai 100 − persen selesai.
     */
    public function test_saringan_belum_selesai_mencakup_semua_yang_belum_selesai(): void
    {
        $a = $this->buatPeserta();
        $b = $this->buatPeserta();
        $this->buatPenugasan($a, null, 'BELUM_MULAI');
        $this->buatPenugasan($a, null, 'BERJALAN');
        $this->buatPenugasan($a, null, 'TERKUMPUL');
        $this->buatPenugasan($b, null, 'MENUNGGU_VERIFIKASI');
        $this->buatPenugasan($b, null, 'SELESAI');

        $r = $this->service()->monitoring($this->cycleId)['ringkasan'];

        $this->assertSame(1, $r['menunggu_verifikasi']);
        $this->assertSame(2, $r['karyawan_dinilai'], 'Orang yang dinilai se-siklus, bukan baris halaman.');
        $this->assertSame(
            $r['total'],
            $r['selesai'] + $r['belum_mulai'] + $r['berjalan'] + $r['terkumpul'] + $r['menunggu_verifikasi'],
            'Setiap penugasan harus masuk tepat satu keranjang.',
        );

        $m = $this->service()->monitoring($this->cycleId, ['status' => 'BELUM_SELESAI']);
        $this->assertSame($r['total'] - $r['selesai'], $m['penugasan']['total']);
        $this->assertSame(4, $m['penugasan']['total']);

        $progresB = collect($this->service()->monitoring($this->cycleId)['peserta']['baris'])
            ->firstWhere('Id_PA_Participant', $b)['progres'];
        $this->assertSame(1, $progresB['menunggu_verifikasi']);
    }

    /** 100% hanya bila semuanya selesai — `round()` membuat 1.139/1.140 terbaca tuntas. */
    public function test_persen_dibulatkan_ke_bawah(): void
    {
        $p = $this->buatPeserta();
        $this->buatPenugasan($p, null, 'SELESAI');
        $this->buatPenugasan($p, null, 'SELESAI');
        $this->buatPenugasan($p, null, 'BELUM_MULAI');

        $m = $this->service()->monitoring($this->cycleId);
        $r = $this->service()->ringkasan($this->cycleId);

        // 2/3 = 66,7: round() memberi 67, floor 66.
        $this->assertSame(66, $m['ringkasan']['persen']);
        $this->assertSame(66, $m['kategori']['SELF']['persen']);
        $this->assertSame(66, $r['penugasan']['penyelesaian_persen']);
        $this->assertSame(66, $r['per_divisi'][0]['penyelesaian_persen']);
    }

    /**
     * Peta panas memakai basis yang sama dengan kartu (peserta dikecualikan tidak ikut),
     * membawa `terlambat` untuk tooltip "Lewat Tenggat", dan `id_divisi` untuk saringan —
     * `null` pada "(Tanpa divisi)" supaya layar tahu baris itu tak bisa disaring.
     */
    public function test_peta_panas_sebasis_dengan_kartu_dan_membawa_terlambat(): void
    {
        $it = $this->buatPeserta(divisi: 'IT', idDivisi: 222);
        $tanpa = $this->buatPeserta(divisi: null);
        $keluar = $this->buatPeserta(divisi: 'PRODUCTION', idDivisi: 111);
        $this->buatPenugasan($it, now()->subDays(3)->format('Y-m-d'));
        $this->buatPenugasan($tanpa, null);
        $this->buatPenugasan($keluar, null);
        DB::table('N_PA_Participant')->where('Id_PA_Participant', $keluar)->update(['Status_Peserta' => 'DIKECUALIKAN']);

        $m = $this->service()->monitoring($this->cycleId);
        $baris = collect($m['peta_panas']['baris'])->keyBy('divisi');

        $this->assertSame(['(Tanpa divisi)', 'IT'], $baris->keys()->all());
        $this->assertSame($m['ringkasan']['total'], $baris->sum('total'));
        $this->assertSame(1, $baris['IT']['terlambat']);
        $this->assertSame(222, $baris['IT']['id_divisi']);
        $this->assertNull($baris['(Tanpa divisi)']['id_divisi']);
    }

    /**
     * Halaman pemantauan hanya menuntut `PaPemantauanPage`, tetapi memuat tombol fase lain.
     * Hak per fase dikirim sebagai prop supaya tombol yang pasti 403 tidak ditawarkan.
     */
    public function test_halaman_pemantauan_membawa_hak_per_fase(): void
    {
        $userId = DB::table('HRIS_Page_Access')->where('Jenis_page', 'PaPemantauanPage')->where('Flag_Aktif', 'Y')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        if ($userId === null) {
            $this->markTestSkipped('Tidak ada user berakses PaPemantauanPage di database ini.');
        }

        $user = \App\Models\ESS\Employee\Models\User::find($userId);

        $this->actingAs($user)->get('/performance-appraisal/pemantauan')->assertOk()
            ->assertInertia(fn ($p) => $p->component('KPI/PerformanceAppraisal/Cycle/pemantauan')
                ->where('akses.pemantauan', true)
                ->where('akses.hasil', $user->memilikiJabatan('PaHasilPage'))
                ->where('akses.penyiapan', $user->memilikiJabatan('PerformanceAppraisalPage')));
    }

    public function test_angka_kartu_terlambat_sama_dengan_isi_daftar_saat_diklik(): void
    {
        // Lembar terisi yang belum dikirim dan lewat tenggat tetap ditagih — di kartu maupun daftar.
        $this->buatPenugasan($this->buatPeserta(), now()->subDays(2)->format('Y-m-d'), 'TERKUMPUL');
        $this->buatPenugasan($this->buatPeserta(), now()->subDays(2)->format('Y-m-d'));

        $m = $this->service()->monitoring($this->cycleId);
        $daftar = $this->service()->monitoring($this->cycleId, ['status' => 'TERLAMBAT']);

        $this->assertSame(2, $m['ringkasan']['terlambat']);
        $this->assertSame($m['ringkasan']['terlambat'], $daftar['penugasan']['total']);
    }

    public function test_kartu_pengisian_tidak_menghitung_dibatalkan_dan_peserta_dikecualikan(): void
    {
        $ikut = $this->buatPeserta();
        $keluar = $this->buatPeserta();
        $this->buatPenugasan($ikut, null, 'SELESAI');
        $this->buatPenugasan($ikut, null, 'DIBATALKAN');
        $this->buatPenugasan($keluar, null);
        DB::table('N_PA_Participant')->where('Id_PA_Participant', $keluar)->update(['Status_Peserta' => 'DIKECUALIKAN']);

        $m = $this->service()->monitoring($this->cycleId);

        // Satu penugasan sah, sudah selesai: 100%, bukan 33%.
        $this->assertSame(1, $m['ringkasan']['total']);
        $this->assertSame(100, $m['ringkasan']['persen']);
        $this->assertSame(1, array_sum(array_column($m['kategori'], 'total')));
        $this->assertSame(1, $m['peserta']['total']);
    }

    public function test_cari_dengan_nik_karyawan_yang_dinilai(): void
    {
        $p = $this->buatPeserta();
        $this->buatPenugasan($p, null);
        $nik = (string) DB::table('N_PA_Participant')->where('Id_PA_Participant', $p)->value('Kode_Karyawan');

        $h = $this->service()->monitoring($this->cycleId, ['q' => $nik])['peserta'];

        $this->assertSame(1, $h['total']);
        $this->assertSame($p, $h['baris'][0]['Id_PA_Participant']);
    }

    /* ------------------------------------------------------------------ *
     * Keranjang sebab tertahan (koreksi 8 Okt 2026)                        *
     * ------------------------------------------------------------------ */

    /**
     * Satu orang tertahan karena DUA sebab muncul di DUA keranjang. Dulu penggolongnya
     * mencocokkan awalan kalimat, sehingga sebab kedua (yang digabung sesudah sebab KPI)
     * tidak pernah terbaca: keranjang penilai kehilangan orang yang juga bermasalah KPI.
     */
    public function test_tertahan_banyak_sebab_masuk_ke_setiap_keranjang_sebabnya(): void
    {
        $kpi = 'Tidak ada KPI disetujui pada rentang periode siklus. Sistem menandai, admin yang memutuskan — bukan otomatis nol.';
        $penilai = 'Tak satu pun kategori penilai memenuhi ambang jumlah pengisi. Menghitung dari sisa yang ada…';
        $jenis = 'Jenis kompetensi wajib tidak tercakup penilaian: KUALITATIF. Periksa dua hal.';

        $kpiDanPenilai = $this->buatTertahan($kpi.' '.$penilai);
        $penilaiSaja = $this->buatTertahan($penilai);
        $kpiDanJenis = $this->buatTertahan($kpi.' '.$jenis);
        $takDikenal = $this->buatTertahan('Sebab yang belum pernah ada.');

        $k = $this->service()->keranjang($this->cycleId);

        // Total dihitung per ORANG, bukan dijumlahkan dari keranjang yang kini tumpang tindih.
        $this->assertSame(4, $k['tertahan']);
        $this->assertSame(2, $k['needs_kpi']);
        $this->assertSame(2, $k['needs_rater']);
        $this->assertSame(2, $k['tertahan_lain']);

        $isi = fn (string $jenisKeranjang) => collect($this->service()->isiKeranjang($this->cycleId, $jenisKeranjang)['baris'])
            ->pluck('Id_PA_Participant')->map(fn ($v) => (int) $v)->sort()->values()->all();
        $urut = fn (array $ids) => collect($ids)->sort()->values()->all();

        // Angka kartu dan isi daftar lahir dari potongan SQL yang sama.
        $this->assertSame($urut([$kpiDanPenilai, $kpiDanJenis]), $isi('needs_kpi'));
        $this->assertSame($urut([$kpiDanPenilai, $penilaiSaja]), $isi('needs_rater'));
        $this->assertSame($urut([$kpiDanJenis, $takDikenal]), $isi('tertahan_lain'));
        $this->assertCount($k['tertahan'], $isi('tertahan'));
    }

    public function test_isi_keranjang_dicari_di_server_bukan_di_halaman_terbuka(): void
    {
        $a = $this->buatTertahan('Tak satu pun kategori penilai memenuhi ambang.');
        $b = $this->buatTertahan('Tak satu pun kategori penilai memenuhi ambang.');
        $namaB = (string) DB::table('N_PA_Participant')->where('Id_PA_Participant', $b)->value('Nama_Karyawan_Saat_Cycle');

        // Satu baris per halaman: yang dicari belum tentu ada di halaman pertama.
        $semua = $this->service()->isiKeranjang($this->cycleId, 'needs_rater', 1, 1);
        $cari = $this->service()->isiKeranjang($this->cycleId, 'needs_rater', 1, 1, $namaB);

        $this->assertSame(2, $semua['total']);
        $this->assertSame(1, $cari['total']);
        $this->assertSame($b, (int) $cari['baris'][0]['Id_PA_Participant']);
        $this->assertNotSame($a, (int) $cari['baris'][0]['Id_PA_Participant']);
    }

    private function buatTertahan(string $alasan): int
    {
        $p = $this->buatPeserta();

        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $p,
            'Status_Hasil' => 'TERTAHAN',
            'Alasan_Tertahan' => $alasan,
        ]);

        return $p;
    }

    private function service(): PaAdminDashboardService
    {
        return $this->app->make(PaAdminDashboardService::class);
    }

    private function buatSiklus(): int
    {
        $nama = 'Siklus Uji Dashboard '.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-DASH-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => $nama,
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => 'OPEN',
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Nama_Cycle', $nama)->value('Id_PA_Cycle');
    }

    private function buatPeserta(?string $divisi = 'PRODUCTION', ?int $idDivisi = 111): int
    {
        $k = array_shift($this->stok);
        $this->assertNotNull($k, 'Stok karyawan uji habis.');

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $this->levelId,
            'Nama_Level_Saat_Cycle' => 'STAFF',
            'ID_Divisi_Saat_Cycle' => $divisi === null ? null : $idDivisi,
            'Nama_Divisi_Saat_Cycle' => $divisi,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        return (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $k->Kode_Karyawan)
            ->value('Id_PA_Participant');
    }

    private function buatPenugasan(int $participantId, ?string $dueAt, string $status = 'BELUM_MULAI'): int
    {
        // Penilai BERBEDA tiap penugasan: indeks uniknya menyaring Flag_Aktif, bukan
        // status, sehingga penilai yang sama dua kali akan bentrok walau salah satunya
        // dibatalkan.
        $penilai = array_shift($this->stokPenilai);
        $this->assertNotNull($penilai, 'Stok penilai uji habis.');

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $this->kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => $status,
            'Due_At' => $dueAt,
        ]);

        return (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->orderByDesc('Id_PA_Assignment')
            ->value('Id_PA_Assignment');
    }
}
