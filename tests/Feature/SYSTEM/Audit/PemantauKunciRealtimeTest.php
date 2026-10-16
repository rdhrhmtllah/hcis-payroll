<?php

namespace Tests\Feature\SYSTEM\Audit;

use App\Services\SYSTEM\Audit\Activity\GerbangDiagnostikRealtime;
use App\Services\SYSTEM\Audit\Activity\PembacaKueriTerberat;
use App\Services\SYSTEM\Audit\Activity\PembacaKunciLangsung;
use App\Services\SYSTEM\Audit\Activity\PenilaiTemuanDiagnostik;
use App\Services\SYSTEM\Audit\Activity\PerekamKueriPerRute;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pemantauan lock realtime: pagarnya, dan atribusinya.
 *
 * Dua hal yang dijaga di sini, dan keduanya pernah gagal DIAM-DIAM:
 *
 * 1. PAGAR. Membaca DMV berulang-ulang murah di dev dan merusak di produksi —
 *    tiap sampel memakai satu koneksi dari pool yang sama yang sedang habis.
 *    Log produksi 1 Sep 2026: 212 "Login timeout expired" dalam 11 menit,
 *    permintaan menggantung sampai batas 301 detik Cloud Run, dan NOL "Lock
 *    request time out" — yang habis koneksinya. Menambah penyedot koneksi ke
 *    dalam keadaan itu bukan pemantauan.
 *
 * 2. ATRIBUSI. Baris DMV sendirian hanya menyebut session_id. Supaya bisa
 *    ditunjuk ke controller, sesi harus bertanda rutenya. Sebelum 4 Sep 2026
 *    penandaan itu tidak pernah berjalan di dev sama sekali: trace-nya diambil
 *    HANYA dari header X-Cloud-Trace-Context milik Cloud Run, yang di luar
 *    Cloud Run tidak ada — jadi tagSession() selalu keluar lebih awal. Tidak
 *    ada galat, hanya atribusi yang tak pernah muncul.
 */
class PemantauKunciRealtimeTest extends TestCase
{
    use DatabaseTransactions;

    private function gerbang(): GerbangDiagnostikRealtime
    {
        return app(GerbangDiagnostikRealtime::class);
    }

    /** Ketiga kunci dibuka, supaya tiap uji bisa menutup satu per satu. */
    private function bukaSemuaKunci(): void
    {
        Config::set('app.env', 'local');
        Config::set('request-diagnostics.realtime.enabled', true);
        Config::set('database.default', 'sqlsrv');
        Config::set('database.connections.sqlsrv.host', 'dev.internal');
        Config::set('request-diagnostics.realtime.host_terlarang', ['prod', 'cloudsql']);
    }

    // ── Pagar ───────────────────────────────────────────────────────────────

    public function test_baku_nya_tertutup_tanpa_perlu_disetel_apa_pun(): void
    {
        // Yang diperiksa nilai BAKU di berkas config, bukan keadaan .env mesin
        // ini — sebab yang ikut ter-deploy adalah berkas config-nya, sedangkan
        // .env mesin pengembang justru MENYALAKANNYA. Karena itu variabelnya
        // dicabut dulu; tanpa itu uji ini cuma membaca setelan lokal dan tidak
        // membuktikan apa pun tentang apa yang terjadi saat deploy.
        $kunci = 'DB_REALTIME_WATCH_ENABLED';
        $asli = Env::getRepository()->get($kunci);
        Env::getRepository()->clear($kunci);

        try {
            $baku = require base_path('config/request-diagnostics.php');
        } finally {
            if ($asli !== null) {
                Env::getRepository()->set($kunci, (string) $asli);
            }
        }

        $this->assertFalse(
            (bool) ($baku['realtime']['enabled'] ?? true),
            'Pemantauan realtime harus MATI secara baku; menyala secara baku berarti '.
            'satu deploy tanpa env var yang benar sudah cukup menyalakannya di produksi.',
        );
    }

    public function test_terbuka_saat_ketiga_kuncinya_setuju(): void
    {
        $this->bukaSemuaKunci();

        $this->assertNull($this->gerbang()->alasanTertutup());
        $this->assertTrue($this->gerbang()->boleh());
    }

    public function test_lingkungan_produksi_menutupnya(): void
    {
        $this->bukaSemuaKunci();
        Config::set('app.env', 'production');

        $this->assertStringContainsString('production', (string) $this->gerbang()->alasanTertutup());
        $this->assertFalse($this->gerbang()->boleh());
    }

    public function test_sakelar_mati_menutupnya(): void
    {
        $this->bukaSemuaKunci();
        Config::set('request-diagnostics.realtime.enabled', false);

        $this->assertNotNull($this->gerbang()->alasanTertutup());
        $this->assertFalse($this->gerbang()->boleh());
    }

    /**
     * Kunci ketiga inilah yang membuat pagarnya bukan sekadar dua salinan kunci
     * yang sama: dua kunci pertama sama-sama env var, jadi satu salah set saat
     * deploy meruntuhkan keduanya sekaligus. Host DB datang dari sumber lain.
     */
    public function test_host_db_berbau_produksi_menutupnya_walau_dua_kunci_lain_terbuka(): void
    {
        foreach (['hcis-prod-db.internal', '/cloudsql/proyek:asia:instans'] as $host) {
            $this->bukaSemuaKunci();
            Config::set('database.connections.sqlsrv.host', $host);

            $this->assertFalse(
                $this->gerbang()->boleh(),
                "Host '{$host}' harus menutup gerbang meski env dan sakelarnya sudah dev.",
            );
        }
    }

    public function test_endpoint_menyebut_alasan_saat_tertutup_bukan_diam(): void
    {
        Config::set('request-diagnostics.realtime.enabled', false);

        $pembaca = app(PembacaKunciLangsung::class);
        $respons = app(\App\Http\Controllers\SYSTEM\Audit\Activity\AdminActivityController::class)
            ->diagnosticsLive(request(), $this->gerbang(), $pembaca);

        $isi = json_decode($respons->getContent(), true)['result'] ?? [];

        $this->assertFalse($isi['aktif'] ?? true);
        $this->assertNotEmpty(
            $isi['alasan'] ?? '',
            'Pemantau yang diam tanpa sebab menghabiskan waktu orang yang mengira ia bekerja.',
        );
    }

    // ── Atribusi ────────────────────────────────────────────────────────────

    public function test_tanda_sesi_membawa_rutenya_bukan_hanya_trace(): void
    {
        Config::set('request-diagnostics.enabled', true);
        Config::set('request-diagnostics.tag_session', true);

        $this->get('/login');

        $tanda = (string) DB::selectOne(
            "SELECT LTRIM(RTRIM(REPLACE(CONVERT(varchar(128), CONTEXT_INFO()), CHAR(0), ''))) AS t"
        )->t;

        $this->assertNotSame('', $tanda,
            'Sesi tidak tertandai sama sekali — di luar Cloud Run trace-nya kosong '.
            'dan tagSession() keluar lebih awal.');
        $this->assertStringContainsString('|', $tanda, 'Tanda harus berbentuk "trace|METHOD /jalur".');
        $this->assertStringContainsString('/login', $tanda,
            'Tanda harus memuat RUTE-nya: baris N_HRIS_Request_Diagnostics baru lahir '.
            'setelah respons, jadi permintaan yang sedang menahan lock tak bisa '.
            'dikenali lewat tabel itu.');
    }

    public function test_tanda_diuraikan_jadi_trace_dan_rute(): void
    {
        $m = new ReflectionMethod(PembacaKunciLangsung::class, 'uraikanTanda');
        $m->setAccessible(true);
        $pembaca = app(PembacaKunciLangsung::class);

        $this->assertSame(['abc123', 'GET /api/v1/activities'],
            $m->invoke($pembaca, 'abc123|GET /api/v1/activities'));

        // Bentuk lama (trace saja) tidak boleh dianggap rute.
        $this->assertSame(['abc123', null], $m->invoke($pembaca, 'abc123'));
        $this->assertSame([null, null], $m->invoke($pembaca, '   '));
    }

    // ── Peringkat biaya kueri ───────────────────────────────────────────────

    /**
     * Potret hanya melihat yang sedang berjalan, sehingga kueri cepat-tapi-sering
     * tak pernah tertangkap — padahal justru gabungan itulah yang menghabiskan
     * CPU. Angka nyata dari dev: satu SELECT atas N_HRIS_VW_All_Transactions2
     * dijalankan 1.934 kali dengan 7.470 logical reads sekali jalan.
     */
    public function test_peringkat_kueri_terbaca_dan_terurut(): void
    {
        $h = app(PembacaKueriTerberat::class)->ambil('cpu', 8);

        $this->assertFalse($h['ada_garis_dasar'], 'Tanpa penanda, tak boleh ada garis dasar tersisa.');
        $this->assertNotEmpty($h['baris'], 'Statistik kueri tak terbaca sama sekali.');

        $cpu = array_column($h['baris'], 'cpu_ms');
        $urut = $cpu;
        rsort($urut);
        $this->assertSame($urut, $cpu, 'Hasilnya harus turun menurut kolom yang diminta.');

        foreach ($h['baris'] as $b) {
            $this->assertNotSame('', trim((string) $b['sql_text']), 'Tiap baris wajib membawa teks kuerinya.');
        }
    }

    /**
     * Garis dasar mengubahnya dari "seumur server" menjadi "selama uji tadi".
     *
     * Tanpa ini, kueri yang sudah menumpuk sebulan SELALU menutupi kueri yang
     * baru saja dipicu — dan yang dicari justru yang kedua.
     */
    public function test_garis_dasar_menyisakan_hanya_yang_bergerak(): void
    {
        $pembaca = app(PembacaKueriTerberat::class);
        $pembaca->hapusDasar();

        try {
            $pembaca->tandaiDasar();

            // Satu kueri khas yang pasti bergerak sesudah penanda.
            $penanda = 'UJI_PERINGKAT_'.bin2hex(random_bytes(4));
            for ($i = 0; $i < 5; $i++) {
                DB::select("SELECT TOP 1 '{$penanda}' AS penanda FROM Karyawan");
            }

            $h = $pembaca->ambil('eksekusi', 100);

            $this->assertTrue($h['ada_garis_dasar']);
            $this->assertNotNull($h['garis_dasar_pada']);

            // Yang TIDAK bergerak harus hilang, bukan tampil bernilai nol:
            // baris nol hanya membuat yang bergerak lebih sulit dilihat.
            foreach ($h['baris'] as $b) {
                $this->assertGreaterThan(0, (int) $b['eksekusi'] + (int) $b['cpu_ms'] + (int) $b['baca'],
                    'Baris yang tak bergerak sejak garis dasar tidak boleh ikut tampil.');
            }
        } finally {
            $pembaca->hapusDasar();
        }
    }

    public function test_kolom_urut_di_luar_daftar_jatuh_ke_cpu_bukan_disisipkan_ke_sql(): void
    {
        // Daftar tertutup: nama kolom masuk ke ORDER BY apa adanya, jadi
        // masukan bebas di sana berarti lubang injeksi.
        $h = app(PembacaKueriTerberat::class)->ambil('1; DROP TABLE Karyawan --', 5);

        $this->assertSame('cpu', $h['urut']);
        $this->assertNotEmpty($h['baris']);
    }

    // ── Rekaman kueri per rute ──────────────────────────────────────────────

    /**
     * Menjawab yang tak bisa dijawab sisi basis data: rute mana yang
     * menjalankan kueri itu. dm_exec_query_stats tahu sebuah kueri mahal dan
     * sering, tetapi tidak tahu endpoint mana pemanggilnya — sedangkan yang
     * ingin diperbaiki orang adalah FITURNYA.
     */
    public function test_rekaman_mengelompokkan_kueri_per_rute(): void
    {
        $this->bukaSemuaKunci();
        Config::set('request-diagnostics.enabled', true);

        $perekam = app(PerekamKueriPerRute::class);
        $sesi = $perekam->mulai()['sesi'];

        try {
            $this->get('/login');
            $this->get('/login');

            $l = $perekam->laporan($sesi);

            // Sengaja >= 1, bukan >= 2: permintaan yang TIDAK menjalankan satu
            // kueri pun tidak ditulis. Permintaan kedua ke halaman yang sama
            // sering begitu karena config & sesinya sudah ter-cache, dan baris
            // kosong hanya menyulitkan membaca yang berisi.
            $this->assertGreaterThanOrEqual(1, $l['total']['permintaan'],
                'Permintaan yang dijalankan uji ini tidak terekam sama sekali.');

            $rute = array_column($l['rute'], 'rute');
            $this->assertContains('GET /login', $rute,
                'Rekaman harus dikelompokkan per rute, bukan dicampur jadi satu.');
        } finally {
            $perekam->berhenti();
            $perekam->hapus($sesi);
        }
    }

    /**
     * Laporannya harus tetap terbaca SESUDAH berhenti.
     *
     * Alur wajarnya "mulai → jalankan fitur → berhenti → baca hasilnya".
     * Percobaan pertama kehilangan hasilnya begitu tombol berhenti ditekan,
     * karena laporan hanya mengenal sesi yang sedang berjalan.
     */
    public function test_laporan_tetap_terbaca_setelah_berhenti(): void
    {
        $this->bukaSemuaKunci();
        Config::set('request-diagnostics.enabled', true);

        $perekam = app(PerekamKueriPerRute::class);
        $sesi = $perekam->mulai()['sesi'];

        try {
            $this->get('/login');
            $perekam->berhenti();

            $l = $perekam->laporan();   // TANPA menyebut id-nya

            $this->assertSame($sesi, $l['sesi'], 'Sesi terakhir harus tetap diingat setelah berhenti.');
            $this->assertFalse($l['berjalan']);
            $this->assertGreaterThan(0, $l['total']['permintaan']);
        } finally {
            $perekam->hapus($sesi);
        }
    }

    /**
     * Alat diagnostiknya tidak boleh memuncaki daftarnya sendiri.
     *
     * SET CONTEXT_INFO dari Layer 2 berjalan pada SETIAP permintaan dan
     * biayanya nyata — satu round-trip penuh, terukur 166-276 ms ke dev.
     * Tanpa penyaring ia jadi kueri teratas di tiap rekaman, dan orang
     * mengejar bayangan alat ukurnya sendiri.
     */
    public function test_kueri_milik_pemantau_tidak_ikut_terekam(): void
    {
        $this->bukaSemuaKunci();
        Config::set('request-diagnostics.enabled', true);
        Config::set('request-diagnostics.tag_session', true);

        $perekam = app(PerekamKueriPerRute::class);
        $sesi = $perekam->mulai()['sesi'];

        try {
            $this->get('/login');
            $l = $perekam->laporan($sesi);

            foreach ($l['rute'] as $r) {
                foreach ($r['daftar'] as $d) {
                    $this->assertStringNotContainsStringIgnoringCase('SET CONTEXT_INFO', $d['sql'],
                        'Kueri milik pemantau ikut terekam dan akan memuncaki daftarnya sendiri.');
                }
            }
        } finally {
            $perekam->berhenti();
            $perekam->hapus($sesi);
        }
    }

    public function test_tanpa_sesi_perekam_diam(): void
    {
        $this->bukaSemuaKunci();
        $perekam = app(PerekamKueriPerRute::class);
        $perekam->berhenti();

        $this->assertFalse($perekam->sedangMerekam());
    }

    /**
     * Penunjuk sesi harus menyeberangi PROSES dan DRIVER CACHE.
     *
     * phpunit.xml memasang CACHE_DRIVER=array. Selama penunjuknya disimpan di
     * cache, sesi yang dimulai oleh `php artisan diagnostik:rekam mulai` sama
     * sekali tak terlihat oleh proses uji — perekamannya diam dan tak ada yang
     * memberi tahu kenapa. Terukur: satu suite penuh menghasilkan 0 baris.
     *
     * Uji ini berjalan DI DALAM proses ber-cache array, jadi ia gagal begitu
     * penunjuknya dikembalikan ke cache.
     */
    public function test_penunjuk_sesi_tidak_bergantung_pada_driver_cache(): void
    {
        $this->assertSame('array', config('cache.default'),
            'Uji ini hanya bermakna bila cache-nya array — itulah keadaan phpunit.xml.');

        $perekam = app(PerekamKueriPerRute::class);
        $sesi = $perekam->mulai()['sesi'];

        try {
            // Cache DIKOSONGKAN. Proses lain tak berbagi cache array sama
            // sekali, dan mengosongkannya di sini menirukan keadaan itu tanpa
            // perlu benar-benar menjalankan proses kedua. Membuat instans baru
            // saja TIDAK cukup: dalam satu proses ia tetap berbagi cache yang
            // sama, sehingga sabotase apa pun akan lolos.
            Cache::flush();

            $lain = new PerekamKueriPerRute(app(GerbangDiagnostikRealtime::class));

            $this->assertSame($sesi, $lain->sesiAktif(),
                'Sesi hilang begitu cache dikosongkan — perekaman dari proses uji akan diam '.
                'tanpa satu pun pesan yang menjelaskan kenapa.');

            $lain->berhenti();
            $this->assertNull($perekam->sesiAktif());
            $this->assertSame($sesi, $perekam->sesiTerakhir());
        } finally {
            $perekam->hapus($sesi);
        }
    }

    // ── Penilaian temuan ────────────────────────────────────────────────────

    /**
     * Angka saja tidak memberi tahu siapa pun apa yang harus diperbaiki.
     *
     * Penilai inilah yang menerjemahkan "kueri sama 8 kali dalam satu
     * permintaan" menjadi "ini N+1, di rute ini". Tanpa terjemahan itu,
     * pembacanya harus sudah mengerti lebih dulu — dan orang yang sudah
     * mengerti tidak butuh laporan.
     */
    public function test_pola_n_plus_satu_dikenali_dan_disebut_rutenya(): void
    {
        $this->bukaSemuaKunci();

        $perekam = app(PerekamKueriPerRute::class);
        $sesi = $perekam->mulai()['sesi'];

        try {
            // Satu permintaan yang menjalankan kueri yang SAMA delapan kali.
            $perekam->catat([
                'rute' => 'GET /uji/n-plus-satu',
                'kueri' => 9,
                'ms' => 120.0,
                'sql' => [
                    'select * from [Karyawan] where [Kode_Karyawan] = ?' => ['n' => 8, 'ms' => 96.0],
                    'select * from [KPI_Users] where [Id_Users] = ?' => ['n' => 1, 'ms' => 24.0],
                ],
            ]);

            $temuan = app(PenilaiTemuanDiagnostik::class)->nilai($sesi, 1);
            $n1 = array_values(array_filter($temuan, static fn ($t) => $t['jenis'] === 'N+1'));

            $this->assertNotEmpty($n1, 'Pola N+1 yang jelas tidak dikenali.');
            $this->assertSame('GET /uji/n-plus-satu', $n1[0]['tempat'],
                'Temuan harus menyebut RUTE-nya — itu satu-satunya yang menunjuk ke kode.');
            $this->assertStringContainsString('8x', $n1[0]['bukti'],
                'Temuan harus membawa buktinya supaya bisa dibantah.');
            $this->assertNotSame('', trim($n1[0]['saran']));
        } finally {
            $perekam->berhenti();
            $perekam->hapus($sesi);
        }
    }

    /**
     * Lima permintaan yang masing-masing menjalankannya SEKALI bukan N+1,
     * meski totalnya lima. Tanpa pembagian ini, rute yang sekadar sering
     * dipanggil akan dituduh N+1 dan orang memperbaiki yang tidak rusak.
     */
    public function test_kueri_yang_sering_tapi_sekali_per_permintaan_bukan_n_plus_satu(): void
    {
        $this->bukaSemuaKunci();

        $perekam = app(PerekamKueriPerRute::class);
        $sesi = $perekam->mulai()['sesi'];

        try {
            for ($i = 0; $i < 6; $i++) {
                $perekam->catat([
                    'rute' => 'GET /uji/sering-tapi-wajar',
                    'kueri' => 1,
                    'ms' => 10.0,
                    'sql' => ['select * from [Karyawan] where [Kode_Karyawan] = ?' => ['n' => 1, 'ms' => 10.0]],
                ]);
            }

            $temuan = app(PenilaiTemuanDiagnostik::class)->nilai($sesi, 1);
            $n1 = array_filter(
                $temuan,
                static fn ($t) => $t['jenis'] === 'N+1' && $t['tempat'] === 'GET /uji/sering-tapi-wajar',
            );

            $this->assertEmpty($n1,
                'Sekali per permintaan bukan N+1 — menuduhnya membuat orang memperbaiki yang tidak rusak.');
        } finally {
            $perekam->berhenti();
            $perekam->hapus($sesi);
        }
    }

    // ── Pembacaan ───────────────────────────────────────────────────────────

    /**
     * Sampel harus terbaca UTUH, termasuk lock bertipe KEY/PAGE.
     *
     * Percobaan pertama melempar "Arithmetic overflow": kolom
     * resource_associated_entity_id berarti object_id untuk lock OBJECT tetapi
     * hobt_id (bigint) untuk KEY/PAGE/RID, dan OBJECT_NAME() hanya menerima int.
     * Lock barislah yang paling sering muncul, jadi cacat itu akan menjatuhkan
     * pemantauannya justru pada beban yang normal.
     */
    /**
     * Sesi yang memegang transaksi tanpa menjalankan apa pun WAJIB membawa
     * kalimat SQL terakhirnya.
     *
     * Tanpa itu, akar sebuah rantai blokir hanya berupa nomor sesi — dan nomor
     * sesi tidak menunjuk ke kode mana pun. Percobaan pertama memulangkannya
     * kosong: satu sesi bisa punya beberapa baris koneksi dan sebagian tidak
     * membawa SQL, sehingga baris pertama yang kebetulan kosong menang.
     */
    public function test_transaksi_menganggur_membawa_kalimat_terakhirnya(): void
    {
        // bukaSemuaKunci() SENGAJA tidak dipanggil di sini: ia memalsukan host
        // DB jadi 'dev.internal' untuk menguji gerbang, dan koneksi kedua di
        // bawah akan menyalin config palsu itu lalu gagal menyambung.
        // Pembacanya sendiri tidak memeriksa gerbang, jadi tak ada yang hilang.

        // Bentuknya dibuat sendiri supaya pasti ada, TIDAK bergantung pada
        // kebetulan isi dev saat itu. Harus lewat koneksi KEDUA: pembacanya
        // mengecualikan sesinya sendiri (@@SPID) — dan pengecualian itu memang
        // benar, sebab sesi pemantau bukan tersangka.
        //
        // Nilainya disetel ke DIRINYA SENDIRI lalu di-ROLLBACK: tidak ada data
        // yang berubah, dua kali.
        Config::set('database.connections.uji_sesi_kedua', Config::get('database.connections.sqlsrv'));
        $lain = DB::connection('uji_sesi_kedua');

        $lain->beginTransaction();
        try {
            $lain->statement('UPDATE N_HRIS_Pengumpulan_Data_Config SET Is_Aktif = Is_Aktif');

            $menganggur = app(PembacaKunciLangsung::class)->sampel()['transaksi_menganggur'];
        } finally {
            $lain->rollBack();
            $lain->disconnect();
        }

        $this->assertNotEmpty($menganggur, 'Sesi bertransaksi yang menganggur harus terlihat.');

        $berkalimat = array_filter(
            $menganggur,
            static fn ($m) => trim((string) ($m['sql_terakhir'] ?? '')) !== '',
        );

        $this->assertNotEmpty($berkalimat,
            'Tidak satu pun sesi menganggur membawa kalimat terakhirnya — akar rantai '.
            'blokir jadi sekadar nomor sesi, yang tidak menunjuk ke kode mana pun.');
    }

    public function test_satu_sampel_terbaca_utuh(): void
    {
        $this->bukaSemuaKunci();

        $s = app(PembacaKunciLangsung::class)->sampel();

        foreach ([
            'diambil_pada', 'atribusi_aktif', 'permintaan', 'rantai_blokir',
            'transaksi_menganggur', 'lock_menunggu', 'lock_per_objek', 'koneksi',
        ] as $k) {
            $this->assertArrayHasKey($k, $s);
        }
        $this->assertIsArray($s['koneksi']['per_program']);
        $this->assertArrayHasKey('tidur_bertransaksi', $s['koneksi']);
        $this->assertGreaterThan(0, $s['koneksi']['total_sesi'],
            'Uji ini sendiri satu sesi; nol berarti pembacaannya tidak melihat apa pun.');
    }
}
