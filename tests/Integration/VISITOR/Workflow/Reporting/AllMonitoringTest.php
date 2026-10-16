<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Http\Controllers\VISITOR\Workflow\Reporting\LaporanVisitorController;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardOperasionalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MelihatSeluruhKategoriTamu;
use Tests\TestCase;

/**
 * Daftar "All Monitoring" per domain.
 *
 * Yang diuji bukan "endpoint membalas 200" — parameter yang terkirim tetapi
 * diabaikan akan lolos uji semacam itu dengan mulus. Yang ditagih di sini adalah
 * EFEKNYA: filter menyingkirkan baris, urutan mengubah susunan, halaman kedua
 * berisi baris yang berbeda.
 */
class AllMonitoringTest extends TestCase
{
    use DatabaseTransactions;
    use MelihatSeluruhKategoriTamu;

    private const PIC = 'ZZALL1';

    /**
     * Karyawan NYATA yang tertaut ke pengguna web.
     *
     * PIC fixture ('ZZALL1') sengaja karangan supaya barisnya mudah dikenali,
     * tetapi izin matriks berangkat dari pengguna web — kode karangan tidak
     * punya satu pun, sehingga laporannya selalu kosong.
     */
    private function kodeKaryawanNyata(): string
    {
        static $kode = null;

        return $kode ??= (string) DB::table('Karyawan')
            ->whereNotNull('UserID_Web')
            ->orderBy('Kode_Karyawan')
            ->value('Kode_Karyawan');
    }

    // ------------------------------------------------------------ pengurutan

    public function test_urutan_tamu_dapat_dibalik(): void
    {
        $this->buatTamu('Zulkifli Akbar', now()->subDays(2));
        $this->buatTamu('Ahmad Basri', now()->subDays(1));

        $naik = $this->nama($this->tamu(['urut' => 'tanggal', 'arah' => 'asc']));
        $turun = $this->nama($this->tamu(['urut' => 'tanggal', 'arah' => 'desc']));

        $this->assertNotSame(
            $naik,
            $turun,
            'Parameter urutan terkirim tetapi tidak mengubah hasil sama sekali.',
        );
        $this->assertSame($naik, array_reverse($turun));
    }

    public function test_urutan_tamu_menurut_nama(): void
    {
        $this->buatTamu('Zulkifli Akbar', now());
        $this->buatTamu('Ahmad Basri', now());

        $nama = $this->nama($this->tamu(['urut' => 'nama', 'arah' => 'asc', 'limit' => 200]));
        $urut = $nama;
        sort($urut, SORT_NATURAL | SORT_FLAG_CASE);

        $this->assertSame($urut, $nama);
    }

    public function test_kolom_urut_tak_dikenal_jatuh_ke_baku_bukan_galat(): void
    {
        // Nilai dari permintaan tidak boleh menyentuh ORDER BY. Kolom karangan
        // harus diabaikan diam-diam, bukan melahirkan galat SQL.
        $response = $this->panggilTamu(['urut' => 'DROP TABLE; --', 'arah' => 'xx']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['success'] ?? false);
    }

    // --------------------------------------------------------------- cari

    public function test_pencarian_tamu_menyaring_dan_membuang(): void
    {
        $unik = 'Qwertyx '.Str::upper(Str::random(6));
        $this->buatTamu($unik, now());
        $this->buatTamu('Nama Lain Sekali', now());

        $hasil = $this->nama($this->tamu(['cari' => $unik]));

        $this->assertContains($unik, $hasil);
        $this->assertNotContains('Nama Lain Sekali', $hasil);
    }

    public function test_pencarian_permit_menyaring_menurut_plat(): void
    {
        // Angka plat dibuat unik supaya pencariannya benar-benar menyaring,
        // bukan kebetulan cocok dengan data lain di basis pengembangan.
        $angka = (string) random_int(100000, 999999);
        $this->buatPermit($angka);
        $lain = $this->buatPermit((string) random_int(100000, 999999));

        $baris = json_decode($this->panggilPermit(['cari' => $angka])->getContent(), true)['result'] ?? [];
        $nomor = array_map(fn ($r) => $r['No_Transaksi'] ?? null, $baris);

        $this->assertNotEmpty($baris, "Permit berplat ...{$angka}... tidak ditemukan lewat pencarian.");
        $this->assertNotContains($lain, $nomor, 'Pencarian tidak menyaring apa pun.');
        foreach ($baris as $r) {
            $this->assertStringContainsString($angka, (string) ($r['No_Plat_Kendaraan'] ?? ''));
        }
    }

    // -------------------------------------------- kontrak kolom <-> balasan

    /**
     * Setiap kolom tabel harus benar-benar ada di balasan server.
     *
     * Ini bukan uji kosmetik. Kolom "Mulai" memakai `Tanggal_Mulai_Berlaku`
     * sementara server mengirim `Tanggal_Berlaku`, dan "Pemohon" memakai
     * `Nama_Pemohon` sementara server mengirim `Nama_Karyawan` — dua kolom
     * kosong sepanjang tabel tanpa satu pun error, karena kunci yang tidak ada
     * hanya menghasilkan undefined. Uji "endpoint membalas 200" tidak akan
     * pernah menyalakannya; hanya perbandingan silang seperti inilah yang bisa.
     *
     * @dataProvider tabelAllMonitoring
     */
    public function test_setiap_kolom_tabel_ada_di_balasan(string $berkas, string $jenis, array $virtual): void
    {
        $baris = $jenis === 'tamu'
            ? ($this->tamu(['limit' => 1])[0] ?? null)
            : (json_decode($this->panggilPermit(['limit' => 1])->getContent(), true)['result'][0] ?? null);

        if ($baris === null) {
            $this->markTestSkipped("Tidak ada baris {$jenis} untuk diperiksa.");
        }

        $diperiksa = array_diff($this->kunciKolom($berkas), $virtual);

        // Penjaga anti-hampa. Begitu seluruh kolom sebuah tabel berpindah ke
        // slot, daftar virtual menelan semuanya dan uji ini berubah menjadi
        // hijau abadi yang tidak memeriksa apa pun — persis nasib tabel permit,
        // yang karena itu tidak lagi diikutkan di sini.
        $this->assertNotEmpty(
            $diperiksa,
            "{$berkas}: seluruh kolomnya dianggap virtual, jadi uji ini tidak "
                .'memeriksa apa pun. Tinjau daftar virtualnya.',
        );

        foreach ($diperiksa as $kunci) {
            $this->assertArrayHasKey(
                $kunci,
                $baris,
                "Kolom '{$kunci}' tidak ada di balasan server — kolomnya akan kosong sepanjang tabel.",
            );
        }
    }

    /** @return array<string, array{0:string,1:string,2:array<int,string>}> */
    public static function tabelAllMonitoring(): array
    {
        return [
            // `masuk` dan `keluar` sengaja virtual: keduanya dirender slot dari
            // Waktu_Aktual_Masuk / Waktu_Aktual_Keluar.
            // `masuk`, `keluar`, `aksi` virtual: dua yang pertama dirender slot
            // dari Waktu_Aktual_*, yang terakhir kolom tombol tanpa data.
            'tamu' => ['TamuAllMonitoring.vue', 'tamu', ['masuk', 'keluar', 'aksi']],
            //
            // Daftar ini HARUS dirawat setiap kali sebuah kolom berpindah ke
            // slot #cell-<kunci>: sejak itu ia tidak lagi membaca field bernama
            // sama, dan menagihnya membuat penjaga ini memerah atas kolom yang
            // benar — yang terjadi pada 'pemohon'.
            //
            // Tabel PERMIT sengaja tidak diikutkan: kedelapan kolomnya kini
            // dirender slot, sehingga tidak tersisa satu pun kolom yang membaca
            // field bernama sama. Memasukkannya hanya menghasilkan uji yang
            // selalu hijau tanpa memeriksa apa pun. Silang-periksa permit
            // dilakukan test_kunci_urut_layar_dikenal_server, yang menagih
            // kosakata urutnya terhadap daftar putih server.
            //
            // Sempat dicoba menurunkan daftar virtual otomatis dari slotnya,
            // tetapi slot lazim membaca BEBERAPA field sekaligus, sehingga satu
            // rujukan yang salah tetap lolos selama saudaranya ada.
        ];
    }

    /**
     * Kunci urut yang dikirim layar harus ada di daftar putih server.
     *
     * Kunci di luar daftar putih TIDAK melahirkan galat — ia jatuh ke urutan
     * baku. Jadi kolom yang kunci urutnya salah ketik akan terlihat bisa
     * diklik, berubah panahnya, dan tidak mengurut apa pun.
     */
    public function test_kunci_urut_layar_dikenal_server(): void
    {
        $konstanta = (new \ReflectionClass(LaporanVisitorController::class))->getConstants();

        $pasangan = [
            'TamuAllMonitoring.vue' => $konstanta['URUT_TAMU'],
            'PermitAllMonitoring.vue' => $konstanta['URUT_PERMIT'],
        ];

        foreach ($pasangan as $berkas => $daftarPutih) {
            foreach ($this->kunciUrut($berkas) as $kolom => $kunci) {
                $this->assertArrayHasKey(
                    $kunci,
                    $daftarPutih,
                    "{$berkas}: kolom '{$kolom}' mengirim urut '{$kunci}' yang tidak dikenal server.",
                );
            }
        }
    }

    // ------------------------------------------------- kolom organisasi

    public function test_baris_permit_membawa_identitas_organisasi_pemohon(): void
    {
        $baris = json_decode($this->panggilPermit(['limit' => 1])->getContent(), true)['result'][0] ?? null;

        if ($baris === null) {
            $this->markTestSkipped('Tidak ada permit disetujui untuk diperiksa.');
        }

        foreach (['Nama_Divisi', 'Nama_Sub_Divisi', 'Nama_Jabatan', 'Nama_Level'] as $kunci) {
            $this->assertArrayHasKey($kunci, $baris);
            // ISNULL(...) di kueri: kolomnya tidak pernah null, jadi layar tidak
            // perlu menebak apakah '-' berarti kosong atau gagal join.
            $this->assertNotNull($baris[$kunci]);
        }
    }

    public function test_join_organisasi_tidak_menggandakan_baris(): void
    {
        // Join ke view jabatan/level dan ke Permit_Efektif menambah dua tabel.
        // Kalau salah satunya berbaris ganda per transaksi, totalnya membengkak
        // tanpa satu pun error — hanya angka yang salah.
        $baris = json_decode($this->panggilPermit(['limit' => 200])->getContent(), true)['result'] ?? [];
        $nomor = array_column($baris, 'No_Transaksi');

        $this->assertSame(
            count($nomor),
            count(array_unique($nomor)),
            'Ada No_Transaksi yang muncul lebih dari sekali — join menggandakan baris.',
        );
    }

    // ---------------------------------------------------- wewenang revoke

    public function test_status_efektif_ikut_dikirim(): void
    {
        // Revoke menulis ke N_VISITOR_Permit_Efektif, BUKAN ke tabel transaksi.
        // Tanpa kolom ini, permit yang sudah dicabut tetap tampil persis seperti
        // yang masih berlaku — dan tombol Revoke-nya masih bisa ditekan lagi.
        $baris = json_decode($this->panggilPermit(['limit' => 5])->getContent(), true)['result'] ?? [];

        if ($baris === []) {
            $this->markTestSkipped('Tidak ada permit disetujui untuk diperiksa.');
        }

        foreach ($baris as $r) {
            $this->assertArrayHasKey('Status_Efektif', $r);
            $this->assertNotSame('', (string) $r['Status_Efektif']);
        }
    }

    public function test_boleh_revoke_dijawab_server_dan_gagal_tertutup(): void
    {
        // Tanpa pengguna terautentikasi jawabannya HARUS false. Kalau layar yang
        // memutuskan, tombolnya bisa tampil untuk orang yang endpoint-nya
        // justru menolak.
        $isi = json_decode($this->panggilPermit()->getContent(), true);

        $this->assertArrayHasKey('boleh_revoke', $isi);
        $this->assertFalse($isi['boleh_revoke']);
    }

    public function test_route_revoke_permit_tetap_milik_meja_verifikasi(): void
    {
        // Wewenang mencabut permit yang sudah disetujui sengaja TIDAK dilebarkan
        // ke pemegang halaman Monitoring. Kalau middleware ini berubah, itu
        // keputusan wewenang — bukan penyesuaian tampilan.
        //
        // Kuncinya kini per DOMAIN (visitorVerifikatorPermitPage), bukan kunci
        // verifikator gabungan lama: meja verifikator sudah dipecah, dan
        // ADMIN_TAMU tidak boleh mencabut permit kendaraan.
        $middleware = \Illuminate\Support\Facades\Route::getRoutes()
            ->match(\Illuminate\Http\Request::create('/verifikator-permit/PMT-X/revoke', 'POST'))
            ->gatherMiddleware();

        $this->assertContains('check.jabatan:visitorVerifikatorPermitPage', $middleware);

        // Dan TIDAK boleh terbuka lewat kunci monitoring mana pun — itulah
        // pelebaran wewenang yang dijaga uji ini sejak semula.
        foreach (['visitorMonitoringPermitPage', 'visitorMonitoringTamuPage', 'visitorLaporanPage'] as $kunciLayar) {
            $this->assertNotContains('check.jabatan:'.$kunciLayar, $middleware);
        }
    }

    public function test_kartu_masih_berlaku_tidak_menghitung_yang_dicabut(): void
    {
        // Daftarnya SENGAJA memuat baris yang dicabut supaya sebabnya terlihat.
        // Angkanya tidak boleh ikut: "Masih berlaku" yang menghitung akses
        // dicabut membuat kartu dan tabel di layar yang sama bertentangan.
        $isi = json_decode($this->panggilPermit(['limit' => 200])->getContent(), true);

        $dicabutDiDaftar = collect($isi['result'])->where('Status_Efektif', 'DIBATALKAN')->count();
        $aktifDiDaftar = collect($isi['result'])->where('Status_Efektif', 'AKTIF')->count();
        // Sejak audit 27 Sep (M7) izin yang kedaluwarsa setelah disetujui ikut
        // dimuat, supaya filter & kartu "Sudah kedaluwarsa" tidak selalu 0.
        $kedaluwarsaDiDaftar = collect($isi['result'])->where('Status_Efektif', 'EXPIRED')->count();

        $this->assertArrayHasKey('dicabut', $isi['stats']);
        $this->assertSame($dicabutDiDaftar, (int) $isi['stats']['dicabut']);
        $this->assertLessThanOrEqual($aktifDiDaftar, (int) $isi['stats']['permit_aktif']);
        $this->assertSame(
            $aktifDiDaftar + $dicabutDiDaftar + $kedaluwarsaDiDaftar,
            (int) $isi['pagination']['total'],
            'Daftar dan totalnya berselisih.',
        );
    }

    /**
     * Pilih semua lalu cabut = PERSIS yang terlihat (30 Sep 2026).
     *
     * Dulu pencabutan massal menyusun saringannya sendiri tanpa asal dan
     * rentang tanggal: menyaring "permit kunjungan tamu" lalu memilih semua
     * ikut mencabut permit karyawan yang aktif.
     */
    public function test_cabut_pilih_semua_hanya_menyentuh_yang_terlihat_di_daftar(): void
    {
        Queue::fake();
        $saring = ['asal' => 'TAMU', 'status' => 'aktif'];

        $terlihat = collect(json_decode($this->panggilPermit($saring + ['limit' => 500])->getContent(), true)['result'])
            ->where('Status_Efektif', 'AKTIF')->pluck('No_Transaksi')->all();
        if ($terlihat === []) {
            $this->markTestSkipped('Butuh satu permit tamu aktif di dev.');
        }

        $karyawanAktif = fn () => DB::table('N_VISITOR_Permit_Efektif as pe')
            ->join('N_EMI_VISITOR_Transaksi_Permit as h', 'h.No_Transaksi', '=', 'pe.No_Transaksi')
            ->whereNull('h.No_Transaksi_Tamu')
            ->where('pe.Status_Efektif', 'AKTIF')
            ->count();
        $sebelum = $karyawanAktif();

        app(\App\Http\Controllers\VISITOR\Workflow\Verification\VerifikatorVisitorController::class)->bulkRevokePermit(
            Request::create('/verifikator-permit-bulk-revoke', 'POST', [
                'alasan' => 'UJI-PILIH-SEMUA',
                'select_all_matching' => true,
                'filters' => $saring,
            ])
        );

        $dicabut = DB::table('N_VISITOR_Permit_Efektif')->where('Alasan_Status', 'UJI-PILIH-SEMUA')->pluck('No_Transaksi')->all();
        $this->assertNotEmpty($dicabut, 'Tidak ada yang dicabut sama sekali.');
        $this->assertSame([], array_values(array_diff($dicabut, $terlihat)), 'Ada permit di luar daftar yang ikut dicabut.');
        $this->assertSame($sebelum, $karyawanAktif(), 'Permit karyawan ikut dicabut oleh saringan asal=TAMU.');
    }

    public function test_halaman_dan_ukuran_halaman_dibatasi(): void
    {
        // limit=0 dulu menjatuhkan ceil($total / 0) menjadi galat 500.
        $nol = json_decode($this->panggilPermit(['limit' => 0])->getContent(), true);
        $this->assertTrue($nol['success']);
        $this->assertSame(1, $nol['pagination']['per_page']);

        $raksasa = json_decode($this->panggilPermit(['limit' => 1000000])->getContent(), true);
        $this->assertSame(500, $raksasa['pagination']['per_page']);
        $this->assertLessThanOrEqual(500, count($raksasa['result']));

        $negatif = json_decode($this->panggilPermit(['page' => -3])->getContent(), true);
        $this->assertTrue($negatif['success']);
        $this->assertSame(1, $negatif['pagination']['current_page']);

        $tamu = json_decode($this->panggilTamu(['limit' => 0, 'page' => 0])->getContent(), true);
        $this->assertTrue($tamu['success']);
    }

    public function test_urutan_baku_tamu_adalah_pengajuan_terbaru_di_atas(): void
    {
        $lama = $this->buatTamu('Urut Diajukan Lama', now());
        $baru = $this->buatTamu('Urut Diajukan Baru', now()->subDays(3));
        // Kunjungan yang LEBIH AWAL tetapi DIAJUKAN belakangan harus di atas.
        DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $lama)->update(['Created_At' => now()->subHours(5)]);

        $nomor = array_column($this->tamu(['cari' => 'Urut Diajukan']), 'No_Transaksi');

        $this->assertSame([$baru, $lama], $nomor);
    }

    public function test_saringan_status_permit_tamu_dan_pencarian_plat(): void
    {
        $nomor = $this->buatTamu('Saring Permit Uji', now());
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->update([
            'Plat_Nomor_Prefix' => 'ZQ', 'Plat_Nomor_Angka' => '9911', 'Plat_Nomor_Suffix' => 'UJ',
        ]);

        $ada = fn (array $q) => in_array($nomor, array_column($this->tamu($q), 'No_Transaksi'), true);

        $this->assertTrue($ada(['cari' => 'ZQ 9911']), 'Pencarian plat tidak menemukan tamunya.');
        $this->assertTrue($ada(['cari' => 'Saring Permit Uji', 'status_permit' => 'TANPA_PERMIT']));
        foreach (['AKTIF', 'MENUNGGU', 'DITOLAK', 'KEDALUWARSA', 'DICABUT'] as $status) {
            $this->assertFalse($ada(['cari' => 'Saring Permit Uji', 'status_permit' => $status]), "Tamu tanpa permit lolos saringan {$status}.");
        }
    }

    public function test_urutan_baku_permit_adalah_pengajuan_terbaru_di_atas(): void
    {
        $nomor = collect(json_decode($this->panggilPermit(['limit' => 50])->getContent(), true)['result'])
            ->pluck('No_Transaksi')->all();
        $this->assertNotEmpty($nomor);

        $dibuat = DB::table('N_EMI_VISITOR_Transaksi_Permit')->whereIn('No_Transaksi', $nomor)
            ->pluck('Created_At', 'No_Transaksi');
        $urut = array_map(fn ($no) => (string) $dibuat[$no], $nomor);
        $harapan = $urut;
        rsort($harapan);

        $this->assertSame($harapan, $urut, 'Daftar tidak diurutkan dari pengajuan terbaru.');
    }

    public function test_saringan_kategori_permit_menyaring_dan_pilihannya_dikirim(): void
    {
        $isi = json_decode($this->panggilPermit(['jenis_permit' => 'COKLAT', 'limit' => 200])->getContent(), true);

        $this->assertNotContains(false, collect($isi['result'])->map(fn ($r) => ($r['Nama_Kategori'] ?? null) === 'COKLAT')->all());
        foreach (['HIJAU', 'MERAH', 'COKLAT'] as $kategori) {
            $this->assertContains($kategori, $isi['opsi_kategori']);
        }
        $this->assertNotContains('TEST BARU', $isi['opsi_kategori'], 'Kategori nonaktif tidak boleh ditawarkan.');
    }

    // ------------------------------------------------------- detail dossier

    /**
     * Detail monitoring memakai SUMBER yang sama dengan meja verifikasi.
     *
     * Kalau ia menyalin kuerinya sendiri, dua layar akan menampilkan dossier
     * yang perlahan berbeda untuk transaksi yang sama — dan tidak ada error
     * yang menandainya. Penjaga ini membandingkan hasilnya langsung.
     */
    public function test_detail_permit_sama_dengan_yang_dilihat_meja_verifikasi(): void
    {
        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('Flag_Aktif', 'Y')->value('Id_Transaksi_Permit');

        if (! $id) {
            $this->markTestSkipped('Tidak ada permit untuk diperiksa.');
        }

        $monitoring = $this->detail('getDetailPermit', (string) $id)['permit'];
        $verifikasi = (array) app(\App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService::class)
            ->basePermitQuery()->where('h.Id_Transaksi_Permit', $id)->first();

        $this->assertSame($verifikasi, (array) $monitoring);
    }

    public function test_detail_permit_membawa_sebab_pencabutan(): void
    {
        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->join('N_VISITOR_Permit_Efektif as pe', 'pe.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('pe.Status_Efektif', 'DIBATALKAN')
            ->value('h.Id_Transaksi_Permit');

        if (! $id) {
            $this->markTestSkipped('Tidak ada permit yang dicabut untuk diperiksa.');
        }

        $permit = (array) $this->detail('getDetailPermit', (string) $id)['permit'];

        $this->assertSame('DIBATALKAN', $permit['Status_Efektif']);
        $this->assertSame('REVOKED', $permit['Status_Permit']);
        foreach (['Alasan_Pencabutan', 'Dicabut_Oleh', 'Dicabut_Pada'] as $kunci) {
            $this->assertArrayHasKey($kunci, $permit);
        }
    }

    public function test_detail_permit_membawa_dokumen_checklist_dan_approval(): void
    {
        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('Flag_Aktif', 'Y')->value('Id_Transaksi_Permit');
        $isi = $this->detail('getDetailPermit', (string) $id);

        foreach (['permit', 'dokumens', 'checklists', 'approvals', 'boleh_revoke'] as $kunci) {
            $this->assertArrayHasKey($kunci, $isi);
        }

        // Halaman ini memantau, bukan memutuskan: penanda alur approval milik
        // meja verifikasi tidak boleh ikut, supaya tidak ada yang tergoda
        // membangun tombol approve di sini.
        $this->assertArrayNotHasKey('is_final_approval', $isi);
    }

    public function test_detail_tamu_membawa_kehadiran_dan_approval(): void
    {
        // Kunjungan Regular sendiri, bukan "baris aktif pertama": sejak
        // DDL-2026-121 detail di rute Regular membalas 404 untuk baris B2B.
        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('No_Transaksi', $this->buatTamu('Detail Reguler', now(), tipe: 'REGULAR'))
            ->value('Id_Transaksi_Tamu');

        $isi = $this->detail('getDetailTamu', (string) $id);
        $tamu = (array) $isi['tamu'];

        $this->assertArrayHasKey('approvals', $isi);
        foreach (['Nama_Tamu', 'Nama_PIC', 'Waktu_Aktual_Masuk', 'Waktu_Aktual_Keluar', 'Status_Kunjungan'] as $kunci) {
            $this->assertArrayHasKey($kunci, $tamu);
        }
    }

    public function test_detail_tamu_menolak_baris_bertipe_lain_dengan_404(): void
    {
        // DDL-2026-121: tipe ditetapkan rute. Detail Regular tidak melayani
        // baris B2B dan sebaliknya — pemegang kunci satu halaman tidak bisa
        // membaca kunjungan tipe lain hanya dengan menebak id-nya.
        $idB2b = (string) DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('No_Transaksi', $this->buatTamu('Detail B2B', now(), tipe: 'B2B'))
            ->value('Id_Transaksi_Tamu');
        $idReguler = (string) DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('No_Transaksi', $this->buatTamu('Detail Reguler', now(), tipe: 'REGULAR'))
            ->value('Id_Transaksi_Tamu');

        $this->assertSame(404, $this->responDetail('getDetailTamu', $idB2b)->getStatusCode());
        $this->assertSame(200, $this->responDetail('getDetailTamu', $idB2b, 'B2B')->getStatusCode());
        $this->assertSame(404, $this->responDetail('getDetailTamu', $idReguler, 'B2B')->getStatusCode());
        $this->assertSame(200, $this->responDetail('getDetailTamu', $idReguler)->getStatusCode());
    }

    public function test_detail_menolak_id_yang_tidak_terbaca_dengan_404(): void
    {
        // Tautan notifikasi membawa token; nilai yang tak terbaca harus
        // berhenti di sini. Mengalirkannya ke kolom int berbalas 500, bukan
        // 404 — dan 500 terbaca seperti aplikasinya rusak.
        foreach (['getDetailPermit', 'getDetailTamu'] as $metode) {
            $this->assertSame(404, $this->responDetail($metode, 'bukan-id-sama-sekali')->getStatusCode());
        }
    }

    public function test_boleh_revoke_pada_detail_gagal_tertutup(): void
    {
        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('Flag_Aktif', 'Y')->value('Id_Transaksi_Permit');

        // Objek pengguna yang bentuknya tidak seperti model asli tidak boleh
        // melempar 500 dan menyeret seluruh dialog ikut gagal.
        $this->assertFalse($this->detail('getDetailPermit', (string) $id)['boleh_revoke']);
    }

    // ------------------------------------------------------ saringan permit

    public function test_saringan_masa_berlaku_permit_benar_benar_menyaring(): void
    {
        // Layar mengirim 'segera' sementara server dulu hanya mengenal
        // 'hampir', sehingga saringan ini meloloskan SEMUA baris — totalnya
        // sama persis dengan tanpa saringan, jadi tidak ada yang curiga.
        $this->buatPermit((string) random_int(100000, 999999));

        $semua = $this->totalPermit([]);
        $segera = $this->totalPermit(['status' => 'segera']);
        $kadaluarsa = $this->totalPermit(['status' => 'kadaluarsa']);

        $this->assertLessThan($semua, $segera, "Saringan 'segera' tidak menyaring apa pun.");
        $this->assertSame($segera, $this->totalPermit(['status' => 'hampir']));
        $this->assertLessThanOrEqual($semua, $kadaluarsa);
    }

    // ---------------------------------------------------------- paginasi

    public function test_halaman_kedua_berisi_baris_yang_berbeda(): void
    {
        foreach (range(1, 4) as $i) {
            $this->buatTamu('Paginasi '.$i, now());
        }

        $satu = $this->nama($this->tamu(['limit' => 2, 'page' => 1, 'urut' => 'nama', 'arah' => 'asc']));
        $dua = $this->nama($this->tamu(['limit' => 2, 'page' => 2, 'urut' => 'nama', 'arah' => 'asc']));

        $this->assertNotEmpty($satu);
        $this->assertNotEmpty($dua);
        $this->assertEmpty(
            array_intersect($satu, $dua),
            'Halaman kedua mengulang baris halaman pertama.',
        );
    }

    // ------------------------------------------------- antrian per domain

    public function test_antrian_approval_dapat_disaring_per_domain(): void
    {
        $tamu = $this->buatTamuMenunggu();
        $permit = $this->buatPermitMenunggu();

        $svc = app(VisitorDashboardOperasionalService::class);
        $semua = $svc->queryAntrianApproval()->pluck('No_Transaksi')->all();
        $hanyaTamu = $svc->queryAntrianApproval('tamu')->pluck('No_Transaksi')->all();
        $hanyaPermit = $svc->queryAntrianApproval('permit')->pluck('No_Transaksi')->all();

        // Tanpa argumen: perilakunya harus persis seperti sebelumnya.
        $this->assertContains($tamu, $semua);
        $this->assertContains($permit, $semua);

        $this->assertContains($tamu, $hanyaTamu);
        $this->assertNotContains($permit, $hanyaTamu);

        $this->assertContains($permit, $hanyaPermit);
        $this->assertNotContains($tamu, $hanyaPermit);
    }

    public function test_antrian_tamu_dibelah_regular_dan_b2b(): void
    {
        // DDL-2026-121: 'tamu' kini hanya Regular (termasuk warisan tanpa
        // tipe), 'tamu_b2b' hanya B2B — tiap halaman punya antreannya sendiri.
        $reguler = $this->buatTamuMenunggu('REGULAR');
        $b2b = $this->buatTamuMenunggu('B2B');

        $svc = app(VisitorDashboardOperasionalService::class);
        $semua = $svc->queryAntrianApproval()->pluck('No_Transaksi')->all();
        $antreanReguler = $svc->queryAntrianApproval('tamu')->pluck('No_Transaksi')->all();
        $antreanB2b = $svc->queryAntrianApproval('tamu_b2b')->pluck('No_Transaksi')->all();

        $this->assertContains($reguler, $semua);
        $this->assertContains($b2b, $semua);
        $this->assertContains($reguler, $antreanReguler);
        $this->assertNotContains($b2b, $antreanReguler, 'Antrean B2B bocor ke antrean Regular.');
        $this->assertContains($b2b, $antreanB2b);
        $this->assertNotContains($reguler, $antreanB2b, 'Antrean Regular bocor ke antrean B2B.');
    }

    // ----------------------------------------------------- stats kehadiran

    public function test_stats_tamu_menghitung_kehadiran_dari_palang(): void
    {
        $sebelum = $this->statsTamu()['sudah_keluar'] ?? 0;

        $nomor = $this->buatTamu('Lewat Palang', now());
        $this->tandaiPalang($nomor, now()->subHours(2), now()->subHour());

        $this->assertSame(
            $sebelum + 1,
            $this->statsTamu()['sudah_keluar'] ?? 0,
            'Stats masih membaca kolom detail; tamu yang lewat palang tidak terhitung.',
        );
    }

    public function test_stats_expired_menghitung_status_cancelled(): void
    {
        // DDL-2026-121: 'expired' memakai definisi status tampil CANCELLED
        // (Estimasi Keluar lewat tanpa masuk), sama dengan kolomnya. Keputusan
        // akhir tersimpan (WITHDRAWN dkk.) tidak ikut terhitung walau
        // tanggalnya juga sudah lewat.
        $sebelum = $this->statsTamu()['expired'] ?? 0;

        $this->buatTamu('Tidak Hadir', now()->subDays(3));
        $this->buatTamu('Ditarik Pemohon', now()->subDays(3), 'WITHDRAWN');
        $this->buatTamu('Masih Terjadwal', now());

        $this->assertSame($sebelum + 1, $this->statsTamu()['expired'] ?? 0);
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<string,mixed> */
    private function detail(string $metode, string $id): array
    {
        return json_decode($this->responDetail($metode, $id)->getContent(), true)['result'] ?? [];
    }

    /** @param  string|null  $tipeRute  tipe yang ditetapkan rute; null = rute Regular */
    private function responDetail(string $metode, string $id, ?string $tipeRute = null)
    {
        $request = Request::create('/x', 'GET');
        $request->setUserResolver(fn () => $this->penggunaLihatSemuaTamu($this->kodeKaryawanNyata()));
        if ($tipeRute !== null) {
            $request->attributes->set(TipeTamu::ATRIBUT, $tipeRute);
        }

        return app(LaporanVisitorController::class)->{$metode}($request, $id);
    }

    private function totalPermit(array $q): int
    {
        return (int) (json_decode($this->panggilPermit($q)->getContent(), true)['pagination']['total'] ?? 0);
    }

    private function berkasLayar(string $nama): string
    {
        return file_get_contents(base_path(
            'resources/js/Pages/VISITOR/Workflow/Reporting/Monitoring/components/'.$nama,
        ));
    }

    /** @return array<int,string> */
    private function kunciKolom(string $berkas): array
    {
        $isi = $this->berkasLayar($berkas);

        preg_match('/const KOLOM = \[(.*?)\R\];/s', $isi, $blok);
        $this->assertNotEmpty($blok, "{$berkas}: blok KOLOM tidak ditemukan — uji ini berhenti menguji apa pun.");

        preg_match_all("/key: '([^']+)'/", $blok[1], $cocok);
        $this->assertNotEmpty($cocok[1], "{$berkas}: tidak ada satu pun kunci kolom terbaca.");

        return $cocok[1];
    }

    /** @return array<string,string> kolom => kunci urut */
    private function kunciUrut(string $berkas): array
    {
        $isi = $this->berkasLayar($berkas);

        preg_match('/const PETA_URUT = \{(.*?)\R\};/s', $isi, $blok);
        $this->assertNotEmpty($blok, "{$berkas}: blok PETA_URUT tidak ditemukan.");

        preg_match_all("/(\w+): '([^']+)'/", $blok[1], $cocok, PREG_SET_ORDER);
        $this->assertNotEmpty($cocok, "{$berkas}: tidak ada satu pun kunci urut terbaca.");

        return array_combine(
            array_column($cocok, 1),
            array_column($cocok, 2),
        );
    }

    private function panggilTamu(array $q = [])
    {
        // Laporan tamu kini disaring MATRIKS, bukan kunci halaman. Permintaan
        // tanpa pengguna memulangkan daftar kosong, dan kegagalannya menyamar
        // jadi "fitur rusak" padahal yang kurang izinnya.
        $request = Request::create('/laporanvisitor-tamu', 'GET', array_merge([
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to' => now()->toDateString(),
            'limit' => 100,
        ], $q));
        $request->setUserResolver(fn () => $this->penggunaLihatSemuaTamu($this->kodeKaryawanNyata()));

        return app(LaporanVisitorController::class)->getLaporanTamu($request);
    }

    private function panggilPermit(array $q = [])
    {
        return app(LaporanVisitorController::class)->getLaporanPermit(
            Request::create('/laporanvisitor-permit', 'GET', array_merge(['limit' => 100], $q))
        );
    }

    private function tamu(array $q = []): array
    {
        return json_decode($this->panggilTamu($q)->getContent(), true)['result'] ?? [];
    }

    private function statsTamu(): array
    {
        return json_decode($this->panggilTamu()->getContent(), true)['stats'] ?? [];
    }

    /** @return array<int,string> */
    private function nama(array $baris): array
    {
        return array_values(array_filter(array_map(
            fn ($r) => $r['Nama_Tamu'] ?? $r['Nama_Lengkap_Tamu'] ?? null,
            $baris,
        )));
    }

    /** Status memakai kode tersimpan DDL-2026-121 (StatusKunjunganTamu::TERSIMPAN). */
    private function buatTamu(string $nama, \DateTimeInterface $tanggal, string $status = 'SCHEDULED', ?string $tipe = null): string
    {
        $nomor = 'VSR-ALL-'.Str::upper(Str::random(10));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => $status,
            'Tanggal_Kunjungan' => $tanggal->format('Y-m-d'),
            'Needs_Approval' => 'T',
            'Flag_Approval' => 'Y',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'ALL-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Tamu' => $id,
            'Nama_Lengkap_Tamu' => $nama,
            'Instansi_Atau_Asal' => 'PT Uji All Monitoring',
            'Deksripsi_Keperluan' => 'Uji',
            'Jumlah_Tamu' => 1,
        ] + ($tipe !== null ? [
            'Tipe_Tamu' => $tipe,
            // Unit WAJIB ikut pada kunjungan B2B, bukan hiasan: pendaftarannya
            // menolak B2B tanpa unit (VISITOR_B2B_UNIT_TIDAK_SESUAI) dan
            // matriks tamu menyaring per unit, sehingga fixture B2B tanpa unit
            // adalah baris yang tak mungkin ada dan tak terlihat siapa pun.
            'B2B_Unit' => $tipe === 'B2B' ? 'PROCUREMENT' : null,
        ] : []));

        return $nomor;
    }

    private function tandaiPalang(string $nomor, \DateTimeInterface $masuk, ?\DateTimeInterface $keluar): void
    {
        DB::table('N_VISITOR_Tamu_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $nomor,
            'Nama_Tamu' => 'Lewat Palang',
            'Kode_Karyawan_PIC' => self::PIC,
            'Nama_PIC' => 'PIC Uji',
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Status_Efektif' => 'AKTIF',
            'Waktu_Aktual_Masuk' => $masuk,
            'Waktu_Aktual_Keluar' => $keluar,
            'Disetujui_Oleh_Nama' => 'Sistem Uji',
            'Disetujui_At' => now(),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Updated_At' => now(),
            'Flag_Sync' => 'Y',
        ]);
    }

    private function buatPermit(string $angkaPlat): string
    {
        $nomor = 'PMT-ALL-'.Str::upper(Str::random(10));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'ALL-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Permit' => $id,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => $angkaPlat,
            'Plat_Nomor_Suffix' => 'ZQ',
            'Tanggal_Mulai_Berlaku' => now()->subDay()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addMonth()->toDateString(),
        ]);

        return $nomor;
    }

    private function buatTamuMenunggu(?string $tipe = null): string
    {
        $nomor = $this->buatTamu('Menunggu Tamu', now(), 'PENDING_APPROVAL', $tipe);
        $this->antrian($nomor);

        return $nomor;
    }

    private function buatPermitMenunggu(): string
    {
        $nomor = 'PMT-ANT-'.Str::upper(Str::random(10));

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->insert([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'ANT-'.Str::upper(Str::random(20)),
        ]);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert(['No_Transaksi' => $nomor]);
        $this->antrian($nomor);

        return $nomor;
    }

    private function antrian(string $nomor): void
    {
        DB::table('HRIS_Approval_Request')->insert([
            'Kode_Karyawan' => self::PIC,
            'No_Transaksi' => $nomor,
            'Flag_Approval' => null,
            'update_at' => now(),
            'created_at' => now(),
        ]);
    }
}
