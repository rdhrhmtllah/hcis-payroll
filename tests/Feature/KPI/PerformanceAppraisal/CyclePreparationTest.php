<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MembandingkanBentukRespons;
use Tests\TestCase;

/**
 * Jalur tulis pertama modul PA.
 *
 * Sampai service ini ada, 40 tabel PA tidak bisa diisi oleh siapa pun dan tidak ada
 * yang bisa diuji ujung ke ujung. Uji ini karena itu menyentuh data development yang
 * sebenarnya — 958 karyawan aktif — bukan fixture buatan, karena yang ingin dibuktikan
 * justru bahwa resolusi level dan divisi bekerja pada data nyata.
 */
class CyclePreparationTest extends TestCase
{
    use DatabaseTransactions;
    use MembandingkanBentukRespons;

    private PaCyclePreparationService $service;

    private PaCycleGate $gate;

    /**
     * Level yang benar-benar dipakai berkas ini.
     *
     * DISEBUT SATU PER SATU, bukan "kosongkan semuanya". Mengosongkan seluruh pengecualian
     * menarik level lain ikut disiapkan — termasuk yang bobot penilainya sedang tidak genap
     * 100 di basis data pengembangan — dan ujinya lalu gagal menyebut level yang tidak ada
     * hubungannya dengan perilaku yang sedang diuji.
     */
    private const LEVEL_YANG_DIPAKAI = [2];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gate = new PaCycleGate;

        /* Dirakit container, bukan `new` dengan daftar argumen tetap. Versi sebelumnya
           menuliskan dua ketergantungan secara harfiah, lalu MATI TOTAL ("Too few
           arguments") begitu servicenya menerima ketergantungan ketiga — kegagalan yang
           tidak mengatakan apa pun tentang perilaku yang sedang diuji, dan yang akan
           terulang setiap kali constructor-nya bertambah. */
        $this->service = app(PaCyclePreparationService::class);

        /* SIKLUS LAIN YANG SEDANG TERBUKA DINETRALKAN LEBIH DULU.

           Aturan "hanya satu siklus boleh terbuka" berlaku se-database, jadi satu siklus
           demo yang tertinggal OPEN membuat SETIAP uji pembukaan di sini gagal — dengan
           pesan tentang siklus lain yang tidak ada hubungannya dengan perilaku yang
           sedang diuji. Kegagalan seperti itu terbaca seperti regresi, lalu dikejar ke
           tempat yang salah.

           Aman ditulis karena berkas ini memakai DatabaseTransactions: seluruhnya
           dikembalikan saat uji selesai, termasuk bila ujinya gagal di tengah. Yang
           dinetralkan hanya siklus LAIN — bukan aturannya, yang justru punya ujinya
           sendiri. */
        DB::table('N_PA_Cycle')
            ->where('Status_Cycle', 'OPEN')
            ->update(['Status_Cycle' => 'CLOSED']);
        /* PENGECUALIAN LEVEL DINETRALKAN LEBIH DULU, dengan sebab yang sama seperti siklus
           terbuka di atas: sejak Matriks Jenis Penilai bisa disunting admin (DDL-2026-091),
           daftar level yang tidak dinilai adalah DATA HIDUP. Satu level yang dikecualikan
           seseorang membuat uji ini gagal dengan pesan tentang peserta yang tidak terbentuk —
           kegagalan yang terbaca seperti regresi, lalu dikejar ke tempat yang salah.

           Aman ditulis karena berkas ini memakai DatabaseTransactions: seluruhnya
           dikembalikan saat uji selesai. Memonya ikut dilupakan, kalau tidak jawaban lama
           yang sudah dibaca sebelumnya tetap dipakai. */
        DB::table('N_PA_Level_Exclusion')
            ->where('Flag_Aktif', 'Y')
            ->whereIn('ID_Level', self::LEVEL_YANG_DIPAKAI)
            ->update(['Flag_Aktif' => 'T']);
        PaLevelDikecualikan::lupakan();
    }

    public function test_menyiapkan_siklus_membentuk_salinan_peserta_dan_baris_hasil(): void
    {
        $cycleId = $this->siklusDraft();

        $hasil = $this->service->siapkan($cycleId);

        // Salinan aturan dibuat hanya untuk level yang benar-benar dipakai karyawan
        // aktif, bukan untuk 16 level di master.
        $this->assertGreaterThan(0, $hasil['salinan']['policy']);
        $this->assertGreaterThan(0, $hasil['salinan']['detail']);
        $this->assertSame(12, $hasil['salinan']['pita'], 'Pita nilai disalin per siklus, bukan per level.');

        $peserta = DB::table('N_PA_Participant')->where('PA_Cycle_Id', $cycleId)->count();
        $aktif = DB::table('Karyawan')->where('Aktif', 'Y')->count();

        // Setiap karyawan aktif mendapat baris — termasuk yang dikecualikan. Ketiadaan
        // baris tidak boleh jadi jawaban.
        $this->assertSame($aktif, $peserta);

        // Satu baris hasil per peserta, dibuat sejak awal.
        $barisHasil = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->count();
        $this->assertSame($peserta, $barisHasil);

        $belum = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('r.Status_Hasil', 'BELUM_DIHITUNG')
            ->count();
        $this->assertSame($peserta, $belum);

        $this->assertSame('PREPARED', $this->statusSiklus($cycleId));
    }

    public function test_freelance_dikecualikan_dengan_alasan_bukan_dihilangkan(): void
    {
        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);

        $freelance = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('ID_Level_Saat_Cycle', 21)
            ->get(['Status_Peserta', 'Alasan_Dikecualikan']);

        $this->assertGreaterThan(0, $freelance->count(), 'Data dev harus punya karyawan FREELANCE.');

        foreach ($freelance as $f) {
            $this->assertSame('DIKECUALIKAN', $f->Status_Peserta);
            // Alasannya wajib tertulis; tanpa itu "kenapa dia tidak dinilai" hanya bisa
            // dijawab dengan menebak.
            $this->assertNotEmpty($f->Alasan_Dikecualikan);
        }
    }

    public function test_kondisi_organisasi_benar_benar_dibekukan(): void
    {
        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);

        $contoh = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->first();

        $this->assertNotNull($contoh);
        $this->assertNotNull($contoh->Nama_Karyawan_Saat_Cycle);
        $this->assertNotNull($contoh->ID_Level_Saat_Cycle);
        $this->assertNotNull($contoh->Nama_Level_Saat_Cycle);

        // Level TIDAK ada di tabel Karyawan; kalau kolom ini terisi, resolusi dua
        // lompatan lewat view golongan memang bekerja pada data nyata.
        $this->assertNotSame($contoh->ID_Level_Jabatan_Saat_Cycle, $contoh->ID_Level_Saat_Cycle);

        // Peserta DIIKUTKAN wajib menunjuk salinan aturan (aturan #15).
        $this->assertNotNull($contoh->PA_Cycle_Rater_Policy_Snapshot_Id);
    }

    public function test_menjalankan_ulang_tidak_menggandakan_apa_pun(): void
    {
        $cycleId = $this->siklusDraft();

        $this->service->siapkan($cycleId);
        $sebelum = $this->cacah($cycleId);

        // Status sudah PREPARED, dan gerbang masih mengizinkan penyiapan pada PREPARED.
        $this->gate->lupakanMemo();
        $this->service->siapkan($cycleId);
        $sesudah = $this->cacah($cycleId);

        $this->assertSame($sebelum, $sesudah);
    }

    public function test_level_tanpa_aturan_penilai_mendarat_di_keranjang_tertunda(): void
    {
        // Mematikan aturan level STAFF, level dengan karyawan terbanyak.
        DB::table('N_PA_Rater_Policy')->where('ID_Level', 2)->update(['Flag_Aktif' => 'T']);

        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);

        $tertunda = $this->service->keranjangTertunda($cycleId);

        $this->assertNotEmpty($tertunda, 'Karyawan level STAFF seharusnya tertunda.');
        $this->assertSame(2, (int) $tertunda[0]->ID_Level_Saat_Cycle);

        // Dan mereka TIDAK boleh menunjuk salinan aturan.
        $salah = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'TERTUNDA')
            ->whereNotNull('PA_Cycle_Rater_Policy_Snapshot_Id')
            ->count();
        $this->assertSame(0, $salah);
    }

    public function test_penguncian_ditolak_selama_keranjang_tertunda_terisi(): void
    {
        DB::table('N_PA_Rater_Policy')->where('ID_Level', 2)->update(['Flag_Aktif' => 'T']);

        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);

        try {
            $this->service->kunci($cycleId);
            $this->fail('Penguncian seharusnya ditolak selama ada peserta TERTUNDA.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('TERTUNDA', $e->getMessage());
        }

        // Dan statusnya TIDAK boleh berubah diam-diam.
        $this->assertSame('PREPARED', $this->statusSiklus($cycleId));
    }

    public function test_penguncian_berhasil_saat_keranjang_bersih(): void
    {
        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);

        $this->assertSame([], $this->service->keranjangTertunda($cycleId));

        $this->service->kunci($cycleId);

        $this->assertSame('LOCKED', $this->statusSiklus($cycleId));
    }

    /**
     * PENGUNCIAN DITAHAN selama HC Learn menolak seseorang yang terlibat.
     *
     * Mengunci adalah titik ketika penyiapan berhenti bisa diulang. Sesudahnya penilai yang
     * ditolak HC Learn tidak akan pernah punya pintu, dan layar "Buka Pengisian" melapor
     * "1 rater belum dijadwalkan" selamanya sambil menawarkan tombol "Jadwalkan sisanya" yang
     * tidak pernah bisa menepatinya — persis yang terjadi pada siklus PA-2026-02: 1.139 dari
     * 1.140 terjadwal, sisanya seorang penilai yang sudah resign di sisi mereka.
     *
     * CARA MEMBUATNYA MERAH: buang pemanggilan `pastikanSiapDiHcLearn()` dari `kunci()`.
     */
    public function test_penguncian_ditahan_bila_hc_learn_menolak_seseorang(): void
    {
        $this->masukSebagaiPenandaTangan();

        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);

        $korban = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->orderBy('Kode_Karyawan')
            ->value('Kode_Karyawan');

        $this->assertNotNull($korban, 'Penyiapan tidak menghasilkan peserta.');

        /* SELURUH kode yang ditanyakan dijawab, dan hanya SATU yang ditolak. Fake yang hanya
           menjawab satu orang membuat sisanya terbaca "tidak dijawab" — layanannya benar
           memperlakukan itu sebagai penghalang juga, dan yang terukur jadi kekeliruan fake,
           bukan perilaku yang sedang diuji. */
        Http::fake(function ($permintaan) use ($korban) {
            parse_str((string) parse_url($permintaan->url(), PHP_URL_QUERY), $query);
            $kode = array_values(array_filter(explode(',', (string) ($query['kode_karyawan'] ?? ''))));

            return Http::response([
                'success' => true,
                'result' => [
                    'data' => array_map(fn (string $k) => [
                        'dicari' => $k,
                        'ada' => true,
                        'siap_dipakai' => $k !== (string) $korban,
                        'alasan' => $k === (string) $korban ? 'SUDAH_RESIGN' : 'SIAP',
                        'keterangan' => $k === (string) $korban
                            ? 'Karyawan terdaftar tetapi tidak aktif / sudah resign.'
                            : 'Karyawan terdaftar dan aktif.',
                    ], $kode),
                ],
            ]);
        });

        $pesan = null;

        try {
            $this->service->kunci($cycleId);
        } catch (RuntimeException $e) {
            $pesan = $e->getMessage();
        }

        $this->assertNotNull($pesan, 'Siklus terkunci padahal ada orang yang ditolak HC Learn.');

        /* NAMANYA DISEBUT. "1 orang belum siap" memindahkan pekerjaan menemukan orangnya
           kembali kepada admin, dan nama itu hanya ada di HC Learn. */
        $this->assertStringContainsString((string) $korban, $pesan);
        $this->assertStringContainsString('SUDAH_RESIGN', $pesan);

        // Dan statusnya TIDAK boleh berubah diam-diam.
        $this->assertSame('PREPARED', $this->statusSiklus($cycleId));
    }

    /**
     * SAMBUNGAN PUTUS TIDAK MENAHAN PENGUNCIAN.
     *
     * Kalau HC Learn tidak bisa dihubungi kita tidak TAHU ada penghalang, dan menahan
     * pekerjaan atas dugaan berarti satu layanan luar yang tumbang membekukan pekerjaan yang
     * tidak membutuhkannya. Yang ditahan hanya jawaban yang TEGAS.
     */
    public function test_hc_learn_yang_tak_terjangkau_tidak_menahan_penguncian(): void
    {
        $this->masukSebagaiPenandaTangan();

        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);

        Http::fake([
            '*/api/v1/pa/checking/karyawan*' => Http::response(
                ['success' => false, 'message' => 'Layanan sedang tidak tersedia'],
                503,
            ),
        ]);

        $this->service->kunci($cycleId);

        $this->assertSame('LOCKED', $this->statusSiklus($cycleId));
    }

    /** Permintaan ke HC Learn ditandatangani identitas pengguna yang sedang masuk. */
    private function masukSebagaiPenandaTangan(): void
    {
        $pengguna = User::whereNotNull('User_Key')->first();

        $this->assertNotNull($pengguna, 'Uji ini butuh pengguna ber-User_Key.');

        $this->actingAs($pengguna);
    }

    public function test_penguncian_ditolak_bila_tidak_ada_peserta_yang_diikutkan(): void
    {
        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);

        DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->update(['Status_Peserta' => 'DIKECUALIKAN', 'Alasan_Dikecualikan' => 'uji']);

        // Siklus tanpa satu pun peserta bukan siklus. Menguncinya menghasilkan siklus
        // yang tampak sah tetapi tidak akan pernah menghasilkan apa pun.
        $this->expectException(RuntimeException::class);
        $this->service->kunci($cycleId);
    }

    public function test_gerbang_menolak_penyiapan_pada_siklus_yang_sudah_dikunci(): void
    {
        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);
        $this->service->kunci($cycleId);

        $this->gate->lupakanMemo();

        // Inilah janji yang membuat lapisan salinan siklus punya alasan keberadaan.
        $this->expectException(RuntimeException::class);
        $this->service->siapkan($cycleId);
    }

    public function test_bobot_aturan_yang_rusak_menghentikan_penyiapan_sebelum_menyalin(): void
    {
        // Merusak bobot level STAFF sehingga jumlahnya bukan 100.
        $policyId = DB::table('N_PA_Rater_Policy')->where('ID_Level', 2)
            ->where('Flag_Aktif', 'Y')->value('Id_PA_Rater_Policy');
        DB::table('N_PA_Rater_Policy_Detail')
            ->where('PA_Rater_Policy_Id', $policyId)
            ->where('Bobot_Persen', 80)
            ->update(['Bobot_Persen' => 50]);

        $cycleId = $this->siklusDraft();

        try {
            $this->service->siapkan($cycleId);
            $this->fail('Bobot yang tidak berjumlah 100 seharusnya menghentikan penyiapan.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('berjumlah', $e->getMessage());
        }

        // Transaksinya digulung: tidak boleh ada salinan separuh jadi yang tertinggal.
        $this->assertSame(0, DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)->count());
        $this->assertSame(0, DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)->count());
    }

    public function test_karyawan_yang_mundur_sebelum_periode_berakhir_dikecualikan(): void
    {
        $karyawan = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')->first(['Kode_Perusahaan', 'Kode_Karyawan']);

        DB::table('Karyawan')
            ->where('Kode_Perusahaan', $karyawan->Kode_Perusahaan)
            ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->update(['Tanggal_Resign' => '2026-03-31']);

        $cycleId = $this->siklusDraft(['Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30']);
        $this->service->siapkan($cycleId);

        $baris = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->first(['Status_Peserta', 'Alasan_Dikecualikan']);

        $this->assertSame('DIKECUALIKAN', $baris->Status_Peserta);
        $this->assertStringContainsString('2026-03-31', $baris->Alasan_Dikecualikan);
    }

    public function test_siklus_tanpa_tanggal_melewati_pemeriksaan_resign_bukan_menebaknya(): void
    {
        $karyawan = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')->first(['Kode_Perusahaan', 'Kode_Karyawan']);

        DB::table('Karyawan')
            ->where('Kode_Perusahaan', $karyawan->Kode_Perusahaan)
            ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->update(['Tanggal_Resign' => '2026-03-31']);

        // Siklus DRAFT boleh belum punya tanggal (K30). Menebak batasnya lebih buruk
        // daripada melewatkan pemeriksaannya.
        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);

        $status = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->value('Status_Peserta');

        $this->assertNotSame('DIKECUALIKAN', $status);
    }

    /**
     * @return array<string, int>
     */
    private function cacah(int $cycleId): array
    {
        return [
            'policy' => DB::table('N_PA_Cycle_Rater_Policy_Snapshot')->where('PA_Cycle_Id', $cycleId)->count(),
            'detail' => DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot as d')
                ->join('N_PA_Cycle_Rater_Policy_Snapshot as h',
                    'h.Id_PA_Cycle_Rater_Policy_Snapshot', '=', 'd.PA_Cycle_Rater_Policy_Snapshot_Id')
                ->where('h.PA_Cycle_Id', $cycleId)->count(),
            'pilar' => DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')->where('PA_Cycle_Id', $cycleId)->count(),
            'syarat' => DB::table('N_PA_Cycle_Competency_Requirement_Snapshot')->where('PA_Cycle_Id', $cycleId)->count(),
            'pita' => DB::table('N_PA_Cycle_Score_Range_Snapshot')->where('PA_Cycle_Id', $cycleId)->count(),
            'peserta' => DB::table('N_PA_Participant')->where('PA_Cycle_Id', $cycleId)->count(),
            'hasil' => DB::table('N_PA_Result as r')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                ->where('p.PA_Cycle_Id', $cycleId)->count(),
        ];
    }

    /* ------------------------------------------------------------------ *
     * Perjalanan status: LOCKED -> OPEN -> CLOSED                          *
     * ------------------------------------------------------------------ */

    public function test_penguncian_ditolak_pada_siklus_yang_sudah_berjalan(): void
    {
        /* REGRESI atas cacat yang ditemukan uji rantai penuh 2 September 2026.
           `kunci()` adalah satu-satunya perpindahan status yang tidak digerbangi, dan
           penjaga TERTUNDA/DIIKUTKAN di dalamnya menjaga ISI siklus, bukan status
           asalnya — siklus yang sudah ditutup tetap memenuhi keduanya. Akibatnya siklus
           CLOSED berisi hasil yang sudah TERBIT bisa dikembalikan ke LOCKED, dan tanpa
           jalan kembali itu berarti mati permanen. */
        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);
        $this->service->kunci($cycleId);

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)
            ->update(['Status_Cycle' => 'CLOSED']);
        $this->gate->lupakanMemo();

        try {
            $this->service->kunci($cycleId);
            $this->fail('Mengunci siklus CLOSED seharusnya ditolak gerbang.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('KUNCI_SIKLUS', $e->getMessage());
        }

        $this->assertSame('CLOSED', $this->statusSiklus($cycleId), 'Statusnya tidak boleh bergeser.');
    }

    public function test_penguncian_mencatat_pelakunya(): void
    {
        /* `Locked_By` ada sejak DDL-2026-066 tetapi tidak pernah diisi, dan methodnya
           bahkan tidak menerima id pengguna — sehingga lini masa audit melaporkan
           pembekuan aturan sebagai peristiwa tanpa pelaku SELAMANYA. */
        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);
        $this->service->kunci($cycleId, 4);

        $baris = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)
            ->first(['Locked_At', 'Locked_By']);

        $this->assertNotNull($baris->Locked_At);
        $this->assertSame(4, (int) $baris->Locked_By);
    }

    public function test_membuka_dan_menutup_pengisian(): void
    {
        /* Sampai 2 September 2026 tidak ada satu baris kode produksi pun yang menulis
           OPEN maupun CLOSED — hanya perintah data contoh. Empat operasi inti bergerbang
           pada dua status yang tak terjangkau, sehingga modulnya tidak bisa dijalani
           sampai selesai lewat aplikasi. */
        $cycleId = $this->siklusDraft([
            'Pengisian_Dari' => '2026-07-01',
            'Pengisian_Sampai' => '2026-07-31',
        ]);
        $this->service->siapkan($cycleId);
        $this->service->kunci($cycleId, 4);

        $buka = $this->service->bukaPengisian($cycleId, 4);

        $this->assertSame('OPEN', $this->statusSiklus($cycleId));
        $this->assertSame('LOCKED', $buka['status_lama']);
        $this->assertFalse($buka['dibuka_ulang']);

        $tutup = $this->service->tutupPengisian($cycleId, 4);

        $this->assertSame('CLOSED', $this->statusSiklus($cycleId));
        $this->assertIsInt($tutup['penugasan_belum_selesai']);
    }

    public function test_siklus_yang_ditutup_masih_bisa_dibuka_lagi(): void
    {
        /* Menutup pengisian terlalu cepat adalah kekeliruan yang WAJAR — penilai minta
           perpanjangan, atau ada yang ketinggalan. Aturan yang tidak menyediakan jalan
           kembali untuk kekeliruan yang wajar hanya memindahkan perbaikannya ke SQL
           manual, dan di sanalah kerusakan yang sesungguhnya terjadi. */
        $cycleId = $this->siklusDraft([
            'Pengisian_Dari' => '2026-07-01',
            'Pengisian_Sampai' => '2026-07-31',
        ]);
        $this->service->siapkan($cycleId);
        $this->service->kunci($cycleId, 4);
        $this->service->bukaPengisian($cycleId, 4);
        $this->service->tutupPengisian($cycleId, 4);

        $lagi = $this->service->bukaPengisian($cycleId, 4);

        $this->assertSame('OPEN', $this->statusSiklus($cycleId));
        $this->assertTrue(
            $lagi['dibuka_ulang'],
            'Membuka ulang harus DIBEDAKAN dari membuka pertama kali: hasil yang sudah '
            .'dihitung menjadi basi, dan layar perlu memperingatkannya.'
        );
    }

    public function test_membuka_pengisian_ditolak_tanpa_jendela_pengisian(): void
    {
        /* Jendela kosong membuat tenggat penugasan lahir kosong — dan tenggat kosong
           membuat "terlambat" mustahil dihitung, sehingga penilai yang tidak mengisi baru
           ketahuan saat siklus sudah ditutup dan tak ada lagi yang bisa dilakukan. */
        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);
        $this->service->kunci($cycleId, 4);

        try {
            $this->service->bukaPengisian($cycleId, 4);
            $this->fail('Membuka pengisian tanpa jendela seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('jendela pengisian', $e->getMessage());
        }

        $this->assertSame('LOCKED', $this->statusSiklus($cycleId));
    }

    public function test_menutup_pengisian_ditolak_bila_belum_dibuka(): void
    {
        $cycleId = $this->siklusDraft();
        $this->service->siapkan($cycleId);
        $this->service->kunci($cycleId, 4);

        try {
            $this->service->tutupPengisian($cycleId, 4);
            $this->fail('Menutup pengisian pada siklus LOCKED seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('TUTUP_PENGISIAN', $e->getMessage());
        }
    }

    public function test_buka_dan_tutup_pengisian_sebentuk_dengan_fixture(): void
    {
        /* Fixture kedua alamat ini dipanen dari respons sungguhan, dan tanpa penjaga di
           sini ia akan basi diam-diam begitu bentuk balasannya berubah — persis jenis
           kegagalan yang paling lama tidak ketahuan, karena frontend tetap hijau memakai
           bentuk lama sampai hari integrasi. */
        $cycleId = $this->siklusDraft([
            'Pengisian_Dari' => '2026-07-01',
            'Pengisian_Sampai' => '2026-07-31',
        ]);
        $this->service->siapkan($cycleId);
        $this->service->kunci($cycleId, 4);

        $buka = $this->service->bukaPengisian($cycleId, 4);
        $this->samakanBentuk('buka-pengisian.json', $this->amplop($buka));

        $tutup = $this->service->tutupPengisian($cycleId, 4);
        $this->samakanBentuk('tutup-pengisian.json', $this->amplop($tutup));
    }

    /*
     | SIKLUS YANG DITUTUP TERLALU CEPAT BISA DIBUKA LAGI SAMBIL DIPERPANJANG.
     |
     | Sampai 7 September 2026 ini jalan buntu. Gerbang `BUKA_PENGISIAN` menerima CLOSED —
     | menutup pengisian terlalu cepat memang kekeliruan yang wajar — tetapi `perbarui()`
     | menolak menggeser jendela pada status CLOSED, dan `bukaPengisian()` menuliskan
     | jendelanya SEBELUM statusnya berpindah. Jadi "buka ulang sambil memperpanjang" selalu
     | ditolak, sementara membuka ulang TANPA tanggal berhasil dan meninggalkan siklus OPEN
     | dengan tenggat yang sudah lewat — seluruh penilai tercatat terlambat sejak detik
     | pertama.
     */
    public function test_siklus_tertutup_bisa_dibuka_lagi_sambil_jendelanya_diperpanjang(): void
    {
        $cycleId = $this->siklusDraft([
            'Pengisian_Dari' => '2026-07-01',
            'Pengisian_Sampai' => '2026-07-31',
        ]);

        $this->service->siapkan($cycleId);
        $this->service->kunci($cycleId, 4);
        $this->service->bukaPengisian($cycleId, 4);
        $this->service->tutupPengisian($cycleId, 4);

        $this->assertSame('CLOSED', DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)->value('Status_Cycle'));

        $hasil = $this->service->bukaPengisian($cycleId, 4, [
            'pengisian_dari' => '2026-07-01',
            'pengisian_sampai' => '2026-08-31',
        ]);

        $this->assertSame('OPEN', $hasil['status']);

        $siklus = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)
            ->first(['Pengisian_Sampai']);

        $this->assertSame(
            '2026-08-31',
            substr((string) $siklus->Pengisian_Sampai, 0, 10),
            'Jendela tidak ikut diperpanjang; siklus dibuka dengan tenggat yang sudah lewat.'
        );
    }

    /*
     | TETAPI LAYAR SUNTING SIKLUS TETAP MENOLAK.
     |
     | Yang dibuka hanya jalur pembukaan ulang, tempat tanggal barunya ditulis bersamaan
     | dengan pembukaannya. Menggeser jendela siklus yang tetap tertutup hanya mengaburkan
     | catatan keterlambatan yang sudah terbentuk — dan tanpa uji ini, kelonggaran tadi
     | gampang melebar menjadi "CLOSED boleh diapa-apakan".
     */
    public function test_jendela_siklus_tertutup_tetap_ditolak_dari_layar_sunting(): void
    {
        $cycleId = $this->siklusDraft([
            'Pengisian_Dari' => '2026-07-01',
            'Pengisian_Sampai' => '2026-07-31',
        ]);

        $this->service->siapkan($cycleId);
        $this->service->kunci($cycleId, 4);
        $this->service->bukaPengisian($cycleId, 4);
        $this->service->tutupPengisian($cycleId, 4);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Buka Pengisian');

        app(\App\Services\KPI\PerformanceAppraisal\PaCycleSetupService::class)
            ->perbarui($cycleId, ['pengisian_sampai' => '2026-08-31'], 4);
    }

    /** Amplop yang persis dibangun `ResponseHelper::success()`. */
    private function amplop(array $result): array
    {
        return ['success' => true, 'status' => 200, 'message' => 'Berhasil', 'result' => $result];
    }

    private function siklusDraft(array $tambahan = []): int
    {
        $kode = 'UJI-PREP-'.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert(array_merge([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Penyiapan',
            'Tahun' => 2026,
            'Status_Cycle' => 'DRAFT',
        ], $tambahan));

        $this->gate->lupakanMemo();

        return (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');
    }

    private function statusSiklus(int $cycleId): string
    {
        return (string) DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->value('Status_Cycle');
    }
}
