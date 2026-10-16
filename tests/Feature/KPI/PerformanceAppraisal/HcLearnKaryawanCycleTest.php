<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnKaryawanSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pemeriksaan karyawan HC Learn pada Langkah 4.
 *
 * POPULASINYA BUKAN "SEMUA KARYAWAN AKTIF". Yang diperiksa hanya orang yang benar-benar
 * terlibat pada siklus: peserta DIIKUTKAN dan penilai minimum yang dipilih aturan + relasi
 * organisasi. Mengirim seluruh master karyawan menyembunyikan siapa yang menghalangi siklus,
 * membocorkan data yang tidak diperlukan, dan membuat admin menyinkronkan orang yang tak
 * pernah menyentuh asesmen ini.
 *
 * HC Learn dipalsukan di batas HTTP. Penentuan peserta, aturan salinan, relasi organisasi,
 * pemilihan penilai, pemetaan data Karyawan, route, controller, dan service tetap nyata.
 */
class HcLearnKaryawanCycleTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private object $peserta;

    private object $penilai;

    private object $detail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->penggunaBerakses());

        [$this->detail, $relasi] = $this->detailDanRelasiYangBisaDipakai();
        $this->peserta = (object) [
            'Kode_Perusahaan' => $relasi->Kode_Perusahaan_Subjek,
            'Kode_Karyawan' => $relasi->Kode_Karyawan_Subjek,
            'Nama' => $relasi->Nama_Subjek,
            'ID_Level_Jabatan' => $relasi->Level_Jabatan_Subjek,
            'ID_Level' => $relasi->ID_Level_Subjek,
        ];
        $this->penilai = (object) [
            'Kode_Perusahaan' => $relasi->Kode_Perusahaan_Terkait,
            'Kode_Karyawan' => $relasi->Kode_Karyawan_Terkait,
            'Nama' => $relasi->Nama_Terkait,
        ];

        $this->cycleId = $this->buatSiklusDanPeserta($relasi);
    }

    /**
     * Perubahan yang membuat uji ini merah: hilangkan penilai dari rencana, atau ambil semua
     * relasi tanpa membatasi sebanyak Min_Rater. Fake menandai penilai terpilih sebagai satu-
     * satunya kode yang belum terdaftar supaya baris yang salah langsung terlihat.
     */
    public function test_pemeriksaan_mencakup_peserta_dan_penilai_minimum_yang_dipilih(): void
    {
        Http::fake(function (Request $request) {
            $kode = $this->kodeDariPemeriksaan($request);

            return Http::response($this->jawabanPemeriksaan($kode, [
                (string) $this->penilai->Kode_Karyawan,
            ]));
        });

        $response = $this->getJson(
            "/api/v1/performance-appraisal/cycles/{$this->cycleId}/hclearn-karyawan"
        );

        $this->assertSame(200, $response->status(), (string) $response->json('message'));

        $response->assertOk()
            ->assertJsonPath('result.status', 'PERLU_SINKRON')
            ->assertJsonPath('result.ringkasan.terlibat', 2)
            ->assertJsonPath('result.ringkasan.siap', 1)
            ->assertJsonPath('result.ringkasan.belum_terdaftar', 1)
            ->assertJsonPath('result.ringkasan.tidak_siap', 0)
            ->assertJsonPath('result.baris.0.kode_karyawan', (string) $this->penilai->Kode_Karyawan)
            ->assertJsonPath('result.baris.0.nama', (string) $this->penilai->Nama)
            ->assertJsonPath('result.baris.0.alasan', 'BELUM_TERDAFTAR');

        $this->assertContains('PENILAI', $response->json('result.baris.0.peran'));
        $this->assertNotContains('DINILAI', $response->json('result.baris.0.peran'));

        /* Dua kode persis. Kalau layanan diam-diam memeriksa seluruh Karyawan aktif, fake
           tetap menjawab sukses dan assertion isi respons di atas bisa lolos; batas HTTP ini
           yang membuktikan populasinya memang siklus tersebut. */
        Http::assertSent(function (Request $request) {
            $kode = $this->kodeDariPemeriksaan($request);
            sort($kode);
            $diharapkan = [
                (string) $this->peserta->Kode_Karyawan,
                (string) $this->penilai->Kode_Karyawan,
            ];
            sort($diharapkan);

            return $kode === $diharapkan;
        });
    }

    /**
     * ASAL POPULASI DISEBUT: proyeksi di Langkah 4, kenyataan di Langkah 5.
     *
     * Panel yang sama berdiri di dua langkah, dan perbedaannya bukan hiasan. Sebelum
     * penugasan terbentuk, penilainya baru dugaan dari relasi organisasi — "259 siap" di sana
     * tidak pernah bisa berarti "siap dijadwalkan", karena populasinya masih bisa berubah.
     * Tanpa angka ini layar tidak punya cara membedakan keduanya, dan admin menutup Langkah 4
     * dengan perasaan sudah beres.
     *
     * CARA MEMBUATNYA MERAH: kembalikan `ringkasan` menjadi empat kunci saja, atau hitung
     * proyeksi sebagai banyaknya calon alih-alih orang yang BARU — pada penugasan yang sudah
     * terbentuk, angka proyeksinya akan naik justru ketika seharusnya nol.
     */
    public function test_asal_populasi_berpindah_dari_proyeksi_ke_penugasan(): void
    {
        Http::fake(fn (Request $request) => Http::response(
            $this->jawabanPemeriksaan($this->kodeDariPemeriksaan($request), [])
        ));

        $svc = app(PaHcLearnKaryawanSyncService::class);

        $sebelum = $svc->kesiapan($this->cycleId)['ringkasan'];

        $this->assertSame(1, $sebelum['dinilai']);
        $this->assertSame(0, $sebelum['penilai_terpasang'], 'Penugasan belum dibuat, tetapi dilaporkan terpasang.');
        $this->assertSame(1, $sebelum['penilai_proyeksi'], 'Penilai yang belum berpenugasan tidak dilaporkan sebagai proyeksi.');

        $this->buatPenugasan();

        $sesudah = $svc->kesiapan($this->cycleId)['ringkasan'];

        $this->assertSame(1, $sesudah['penilai_terpasang'], 'Penugasan yang sudah ada tidak terbaca.');

        /* NOL, bukan satu. Proyeksi yang jatuh pada orang yang penugasannya sudah ada bukan
           dugaan lagi — menghitungnya tetap membuat peringatan "masih proyeksi" menyala abadi
           di Langkah 5, dan peringatan yang selalu menyala berhenti dibaca. */
        $this->assertSame(0, $sesudah['penilai_proyeksi'], 'Penilai yang sudah berpenugasan masih dihitung proyeksi.');
        $this->assertSame($sebelum['terlibat'], $sesudah['terlibat'], 'Populasinya berubah padahal orangnya sama.');
    }

    /**
     * ORANG YANG MENGHALANGI MENUNJUK PEKERJAANNYA SENDIRI.
     *
     * "A1 sudah resign" memberi tahu ada masalah, bukan di mana. Yang harus dibereskan admin
     * bukan A1 — ia tidak bisa dibereskan — melainkan penugasan yang menunjuk padanya. Tanpa
     * daftar ini admin menelusuri sendiri ratusan orang untuk menemukan satu nama.
     *
     * `cukup_dibatalkan` adalah bagian yang paling mudah terlewat: kategori yang penilainya
     * TEPAT sebanyak minimumnya jatuh di bawah minimum begitu satu dibatalkan, dan pembatalan
     * tanpa pengganti tidak memunculkan galat apa pun — kompetensi itu hanya dihitung dari
     * penilai yang lebih sedikit, diam-diam, dengan bobot penuh.
     *
     * CARA MEMBUATNYA MERAH: buang `lengkapiTanggungan()`, atau hitung `cukup_dibatalkan`
     * dengan `>=` terhadap jumlah SEKARANG alih-alih jumlah SESUDAH satu dibatalkan.
     */
    public function test_baris_yang_menghalangi_menyebut_penugasan_yang_bergantung_padanya(): void
    {
        $kodePenilai = trim((string) $this->penilai->Kode_Karyawan);

        Http::fake(fn (Request $request) => Http::response(
            $this->jawabanPemeriksaan($this->kodeDariPemeriksaan($request), [], [$kodePenilai])
        ));

        $this->buatPenugasan();

        $hasil = app(PaHcLearnKaryawanSyncService::class)->kesiapan($this->cycleId);
        $bermasalah = collect($hasil['baris'])->firstWhere('kode_karyawan', $kodePenilai);

        $this->assertNotNull($bermasalah, 'Penilai yang tidak siap tidak muncul di daftar.');
        $this->assertFalse($bermasalah['siap']);
        $this->assertCount(1, $bermasalah['tanggungan'], 'Penugasan yang bergantung padanya tidak disebut.');

        $t = $bermasalah['tanggungan'][0];

        $this->assertSame(trim((string) $this->peserta->Kode_Karyawan), $t['kode_karyawan']);
        $this->assertSame((string) $this->peserta->Nama, $t['nama']);
        $this->assertSame((string) $this->detail->Kode_Kategori, $t['kategori']);
        $this->assertSame(1, $t['penilai_sekarang']);
        $this->assertSame(1, $t['min_rater']);

        /* Satu penilai, minimal satu: membatalkannya menyisakan NOL — jadi yang dibutuhkan
           penggantian, bukan pembatalan. */
        $this->assertFalse($t['cukup_dibatalkan']);

        /* Kuncinya memakai nama yang diminta dialog Kelola Rater, supaya barisnya bisa
           diteruskan apa adanya tanpa pemetaan kedua yang bisa salah tebak. */
        $this->assertArrayHasKey('participant_id', $t);
        $this->assertArrayHasKey('id_level', $t);
        $this->assertArrayHasKey('nama_level', $t);
    }

    /**
     * Baris yang SIAP tidak ikut ditelusuri — penelusurannya hanya untuk yang menghalangi,
     * dan itulah yang menjaga jumlah kueri tidak tumbuh bersama jumlah peserta.
     */
    public function test_orang_yang_siap_tidak_membawa_daftar_tanggungan(): void
    {
        Http::fake(fn (Request $request) => Http::response(
            $this->jawabanPemeriksaan($this->kodeDariPemeriksaan($request), [])
        ));

        $this->buatPenugasan();

        $hasil = app(PaHcLearnKaryawanSyncService::class)->kesiapan($this->cycleId);

        foreach ($hasil['baris'] as $b) {
            $this->assertTrue($b['siap']);
            $this->assertSame([], $b['tanggungan'] ?? []);
        }
    }

    /**
     * SUDAH DIBERESKAN HARUS BERARTI SELESAI.
     *
     * Admin mencabut penugasan penilai yang ditolak HC Learn. Orang itu TETAP muncul di
     * populasi — selama ia masih aktif di HCIS, aturan penilai terus memilihnya sebagai calon,
     * jadi proyeksi mengembalikannya — dan panelnya sendiri berkata "tidak ada penugasan aktif
     * yang menunjuk orang ini". Tetapi penguncian tetap tertahan atas namanya, dan tidak ada
     * satu pun tindakan yang bisa menyelesaikannya: pekerjaan yang sudah benar tidak pernah
     * berbuah. Itu terjadi pada PA-2026-02.
     *
     * Kehadirannya di daftar TIDAK salah — itu jujur, dan berguna supaya ia tidak diam-diam
     * terpasang lagi pada pembentukan berikutnya. Yang salah adalah menganggapnya penghalang.
     *
     * CARA MEMBUATNYA MERAH: kembalikan `menghalangi` menjadi kebalikan `siap`.
     */
    public function test_penilai_ditolak_tanpa_penugasan_tidak_menahan_apa_pun(): void
    {
        $kodePenilai = trim((string) $this->penilai->Kode_Karyawan);

        Http::fake(fn (Request $request) => Http::response(
            $this->jawabanPemeriksaan($this->kodeDariPemeriksaan($request), [], [$kodePenilai])
        ));

        $svc = app(PaHcLearnKaryawanSyncService::class);

        /* Keadaan SEBELUM dibereskan: penugasannya ada, jadi ia memang menahan. Tanpa langkah
           ini uji di bawah tetap hijau pada layanan yang tidak pernah menahan siapa pun. */
        $this->buatPenugasan();

        $sebelum = $svc->kesiapan($this->cycleId);
        $barisSebelum = collect($sebelum['baris'])->firstWhere('kode_karyawan', $kodePenilai);

        $this->assertTrue($barisSebelum['menghalangi'], 'Penilai berpenugasan seharusnya menahan.');
        $this->assertSame(PaHcLearnKaryawanSyncService::PERLU_TINDAKAN, $sebelum['status']);

        /* Admin membereskannya: penugasannya dicabut. */
        /* Dicabut lewat ID, bukan lewat UPDATE ber-JOIN: SQL Server menolak alias pada UPDATE,
           dan bentuk yang lolos parser pun bisa memperbarui nol baris tanpa mengeluh — uji ini
           lalu hijau/merah karena alasan yang tak ada hubungannya dengan perilakunya. */
        $idPenugasan = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $this->cycleId)
            ->where('a.Kode_Karyawan_Penilai', $kodePenilai)
            ->pluck('a.Id_PA_Assignment')
            ->all();

        $this->assertNotEmpty($idPenugasan, 'Penugasan uji tidak ditemukan untuk dicabut.');

        DB::table('N_PA_Assignment')
            ->whereIn('Id_PA_Assignment', $idPenugasan)
            ->update(['Flag_Aktif' => 'T', 'Status_Penugasan' => 'DIBATALKAN']);

        $sesudah = $svc->kesiapan($this->cycleId);
        $barisSesudah = collect($sesudah['baris'])->firstWhere('kode_karyawan', $kodePenilai);

        /* TIDAK DIPROYEKSIKAN LAGI. Penilai yang dicabut admin tidak akan dipilih ulang oleh
           pembentukan penugasan (`pernah_dicabut`), jadi proyeksi pun melewatinya. Sebelum
           8 Okt ia tetap tercatat sebagai "1 penilai masih proyeksi" sesudah dibereskan —
           peringatan yang menyuruh admin membentuk penugasan yang sudah lengkap. */
        $this->assertNull($barisSesudah, 'Penilai yang dicabut masih diproyeksikan.');

        // DAN TIDAK MENAHAN APA PUN LAGI.
        $this->assertSame(0, $sesudah['ringkasan']['menghalangi']);
        $this->assertSame(PaHcLearnKaryawanSyncService::SIAP, $sesudah['status']);
    }

    /**
     * Perubahan yang membuat uji ini merah: kirim semua karyawan yang terlibat ke add-new,
     * bukan hanya `BELUM_TERDAFTAR`; abaikan response 207; atau tidak periksa ulang sesudah
     * sinkronisasi.
     */
    public function test_sinkronisasi_hanya_mengirim_yang_belum_terdaftar_lalu_memeriksa_ulang(): void
    {
        $sudahDisinkron = false;
        $muatan = [];
        $pemeriksaan = 0;

        Http::fake(function (Request $request) use (&$sudahDisinkron, &$muatan, &$pemeriksaan) {
            if (str_contains($request->url(), '/pa/add-new/karyawan')) {
                $muatan = (array) ($request->data()['data'] ?? []);
                $sudahDisinkron = true;

                return Http::response([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Semua data terproses tanpa kegagalan.',
                    'result' => [
                        'ringkasan' => ['diminta' => 1, 'tersimpan' => 1, 'sudah_ada' => 0, 'gagal' => 0],
                        'tersimpan' => [[
                            'kode' => $this->penilai->Kode_Karyawan,
                            'nama' => $this->penilai->Nama,
                            'kolom_terisi' => 12,
                        ]],
                        'sudah_ada' => [],
                        'gagal' => [],
                    ],
                ]);
            }

            $pemeriksaan++;
            $kode = $this->kodeDariPemeriksaan($request);

            return Http::response($this->jawabanPemeriksaan(
                $kode,
                $sudahDisinkron ? [] : [(string) $this->penilai->Kode_Karyawan],
            ));
        });

        $response = $this->postJson(
            "/api/v1/performance-appraisal/cycles/{$this->cycleId}/hclearn-karyawan/sinkronkan"
        );

        $response->assertOk()
            ->assertJsonPath('result.sinkron.ringkasan.diminta', 1)
            ->assertJsonPath('result.sinkron.ringkasan.tersimpan', 1)
            ->assertJsonPath('result.kesiapan.status', 'SIAP')
            ->assertJsonPath('result.kesiapan.ringkasan.belum_terdaftar', 0);

        $this->assertSame(2, $pemeriksaan, 'Status HC Learn tidak diperiksa ulang sesudah sinkronisasi.');
        $this->assertCount(1, $muatan, 'add-new menerima orang yang sudah siap atau bukan bagian siklus.');
        $this->assertSame((string) $this->penilai->Kode_Karyawan, $muatan[0]['kode_karyawan']);
        $this->assertSame((string) $this->penilai->Nama, $muatan[0]['nama']);

        /* Data yang dibutuhkan asesmen ikut dikirim; data keuangan/benefit tidak. Endpoint
           mereka menerima kolom asing, tetapi "bisa dikirim" bukan alasan mengirim rekening,
           NPWP, atau BPJS untuk membuat akun ujian. */
        $this->assertArrayHasKey('kode_perusahaan', $muatan[0]);
        $this->assertArrayHasKey('kode_jabatan', $muatan[0]);
        $this->assertArrayHasKey('status_kepegawaian', $muatan[0]);
        $this->assertArrayNotHasKey('no_rek', $muatan[0]);
        $this->assertArrayNotHasKey('npwp', $muatan[0]);
        $this->assertArrayNotHasKey('no_bpjs_tk', $muatan[0]);
    }

    /**
     * SIKLUS TANPA PESERTA BUKAN "SIAP".
     *
     * Di Langkah 4 panel ini terbuka SEBELUM penyiapan dijalankan, jadi populasinya memang
     * masih kosong. Menjawab `SIAP` di sana adalah kebohongan yang tenang: admin membaca
     * "siap dijadwalkan" pada siklus yang belum punya seorang pun, lalu meneruskan.
     *
     * Perubahan yang membuat uji ini merah: kembalikan status populasi kosong menjadi SIAP.
     */
    public function test_siklus_tanpa_peserta_tidak_dilaporkan_siap(): void
    {
        DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->update(['Flag_Aktif' => 'T']);

        Http::fake();

        $response = $this->getJson(
            "/api/v1/performance-appraisal/cycles/{$this->cycleId}/hclearn-karyawan"
        );

        $response->assertOk()
            ->assertJsonPath('result.status', 'BELUM_ADA_PESERTA')
            ->assertJsonPath('result.ringkasan.terlibat', 0);

        /* HC Learn TIDAK dihubungi untuk daftar kosong. Permintaan tanpa satu pun kode
           hanya menambah perjalanan jaringan pada layar yang dibuka berulang kali. */
        Http::assertNothingSent();
    }

    /**
     * KUERINYA TETAP walau pesertanya berlipat.
     *
     * Aturan penilai dibaca per peserta lewat resolver, dan tanpa pemanasan massal itu
     * berarti satu kueri per orang — pola yang sudah dua kali membuat CPU 100 persen di
     * repo ini. Yang dijaga di sini BUKAN waktu (yang bergantung latensi mesin uji),
     * melainkan JUMLAH KUERI: berlipat gandanya peserta tidak boleh melipatgandakannya.
     *
     * Perubahan yang membuat uji ini merah: buang `muatPesertaBanyak()` atau
     * `muatOverrideSiklus()` dari proyeksi penilai.
     */
    public function test_jumlah_kueri_tidak_ikut_naik_bersama_jumlah_peserta(): void
    {
        Http::fake(fn (Request $request) => Http::response(
            $this->jawabanPemeriksaan($this->kodeDariPemeriksaan($request), [])
        ));

        $layanan = app(PaHcLearnKaryawanSyncService::class);

        $satu = $this->kueriSelama(fn () => $layanan->kesiapan($this->cycleId));

        $ditambah = $this->gandakanPeserta(12);

        if ($ditambah < 6) {
            $this->markTestSkipped('Karyawan aktif tidak cukup untuk menggandakan peserta uji.');
        }

        app()->forgetInstance(PaHcLearnKaryawanSyncService::class);
        $banyak = $this->kueriSelama(fn () => app(PaHcLearnKaryawanSyncService::class)->kesiapan($this->cycleId));

        /* Ambangnya SELISIH, bukan angka mutlak: jumlah dasarnya bergantung pada berapa
           level dan kategori yang dipakai data pengembangan, dan angka mutlak akan
           berbohong begitu datanya bergeser. Yang tidak boleh adalah tumbuh per orang. */
        $this->assertLessThanOrEqual(
            $satu + 2,
            $banyak,
            sprintf(
                'Kueri naik dari %d menjadi %d saat peserta bertambah %d orang — '
                .'aturan penilai kemungkinan dibaca per peserta tanpa pemanasan massal.',
                $satu,
                $banyak,
                $ditambah,
            )
        );
    }

    private function kueriSelama(callable $kerja): int
    {
        $jumlah = 0;
        DB::listen(function () use (&$jumlah) {
            $jumlah++;
        });

        $kerja();

        return $jumlah;
    }

    /** Menambah peserta DIIKUTKAN dari karyawan aktif yang belum jadi peserta. */
    private function gandakanPeserta(int $target): int
    {
        $snapshotId = (int) DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->value('Id_PA_Cycle_Rater_Policy_Snapshot');

        $sudah = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->pluck('Kode_Karyawan')
            ->all();

        $baris = [];

        foreach (DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v', function ($j) {
                $j->on('v.kode_perusahaan', '=', 'k.Kode_Perusahaan')
                    ->on('v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan');
            })
            ->where('k.Aktif', 'Y')
            ->where('v.ID_Level', $this->peserta->ID_Level)
            ->whereNotIn('k.Kode_Karyawan', $sudah)
            ->limit($target)
            ->get(['k.Kode_Perusahaan', 'k.Kode_Karyawan', 'k.Nama', 'k.ID_Level_Jabatan']) as $k) {
            /* DIKUNCI PASANGAN perusahaan+karyawan, bukan ditumpuk apa adanya.
               |
               | `whereNotIn` di atas menyaring dengan KODE saja, sementara indeks uniknya
               | `(PA_Cycle_Id, Kode_Perusahaan, Kode_Karyawan)`. Selama satu kode hanya ada
               | pada satu perusahaan keduanya setara — tetapi kesetaraan itu milik DATA, bukan
               | skema: kunci tabelnya memang komposit. Mengunci dengan pasangan di sini membuat
               | batch-nya tidak pernah bisa memuat baris kembar, apa pun bentuk datanya. */
            $baris[$k->Kode_Perusahaan.'|'.$k->Kode_Karyawan] = [
                'PA_Cycle_Id' => $this->cycleId,
                'Kode_Perusahaan' => $k->Kode_Perusahaan,
                'Kode_Karyawan' => $k->Kode_Karyawan,
                'Nama_Karyawan_Saat_Cycle' => $k->Nama,
                'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
                'ID_Level_Saat_Cycle' => $this->peserta->ID_Level,
                'PA_Cycle_Rater_Policy_Snapshot_Id' => $snapshotId,
                'Status_Peserta' => 'DIIKUTKAN',
            ];
        }

        if ($baris !== []) {
            DB::table('N_PA_Participant')->insert(array_values($baris));
        }

        return count($baris);
    }

    /**
     * Pemegang halaman admin PA yang SEKALIGUS punya User_Key.
     *
     * Dua syarat, bukan satu: rutenya dijaga `check.jabatan:PerformanceAppraisalPage`,
     * sementara permintaan ke HC Learn ditandatangani memakai `User_Key` orang yang sedang
     * masuk. Memilih salah satunya saja membuat uji gagal dengan sebab yang tidak ada
     * hubungannya dengan yang sedang diuji.
     */
    private function penggunaBerakses(): User
    {
        $userId = DB::table('HRIS_Page_Access as a')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'a.UserID_Web')
            ->where('a.Jenis_page', 'PerformanceAppraisalPage')
            ->where('a.Flag_Aktif', 'Y')
            ->whereNotNull('u.User_Key')
            ->value('a.UserID_Web');

        if ($userId === null) {
            $this->markTestSkipped(
                'Tidak ada pemegang PerformanceAppraisalPage yang punya User_Key; '
                .'permintaan ke HC Learn tidak bisa ditandatangani.'
            );
        }

        $user = User::find($userId);
        $this->assertNotNull($user, "User {$userId} tidak ditemukan.");

        return $user;
    }

    /** @return array{0:object,1:object} */
    private function detailDanRelasiYangBisaDipakai(): array
    {
        $detail = DB::table('N_PA_Rater_Policy_Detail as d')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'd.PA_Rater_Category_Id')
            ->where('d.Flag_Aktif', 'Y')
            ->where('c.Flag_Aktif', 'Y')
            ->whereNotNull('d.Organization_Relationship_Type_Id')
            ->where('d.Min_Rater', '>=', 1)
            ->orderBy('d.Id_PA_Rater_Policy_Detail')
            ->get([
                'd.Id_PA_Rater_Policy_Detail', 'd.PA_Rater_Policy_Id',
                'd.PA_Rater_Category_Id', 'd.Organization_Relationship_Type_Id',
                'd.Bobot_Persen', 'd.Min_Rater', 'd.Maks_Rater', 'd.Min_Rater_Selesai',
                'd.Flag_Izinkan_Manual', 'd.Urutan_Tampil',
                'c.Kode_Kategori', 'c.Nama_Kategori',
            ]);

        foreach ($detail as $d) {
            $relasi = DB::table('N_HRIS_Organization_Relationship as r')
                ->join('Karyawan as s', function ($join) {
                    $join->on('s.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Subjek')
                        ->on('s.Kode_Karyawan', '=', 'r.Kode_Karyawan_Subjek');
                })
                ->join('Karyawan as t', function ($join) {
                    $join->on('t.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Terkait')
                        ->on('t.Kode_Karyawan', '=', 'r.Kode_Karyawan_Terkait');
                })
                ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v', function ($join) {
                    $join->on('v.kode_perusahaan', '=', 's.Kode_Perusahaan')
                        ->on('v.ID_Level_Jabatan', '=', 's.ID_Level_Jabatan');
                })
                ->where('r.Id_Organization_Relationship_Type', $d->Organization_Relationship_Type_Id)
                ->where('r.Flag_Aktif', 'Y')
                ->whereNull('r.Cancelled_At')
                ->where('s.Aktif', 'Y')
                ->where('t.Aktif', 'Y')
                ->whereColumn('r.Kode_Karyawan_Subjek', '<>', 'r.Kode_Karyawan_Terkait')
                ->orderBy('r.Prioritas')
                ->orderBy('r.Id_Organization_Relationship')
                ->first([
                    'r.Id_Organization_Relationship', 'r.Berlaku_Dari', 'r.Berlaku_Sampai',
                    'r.Kode_Perusahaan_Subjek', 'r.Kode_Karyawan_Subjek',
                    'r.Kode_Perusahaan_Terkait', 'r.Kode_Karyawan_Terkait',
                    's.Nama as Nama_Subjek', 's.ID_Level_Jabatan as Level_Jabatan_Subjek',
                    't.Nama as Nama_Terkait', 'v.ID_Level as ID_Level_Subjek',
                ]);

            if ($relasi !== null) {
                return [$d, $relasi];
            }
        }

        $this->fail('Tidak ada pasangan aturan-relasi aktif untuk membangun uji keterlibatan HC Learn.');
    }

    private function buatSiklusDanPeserta(object $relasi): int
    {
        $kode = 'UJI-HCL-KRY-'.substr(bin2hex(random_bytes(4)), 0, 8);
        $acuan = substr((string) ($relasi->Berlaku_Dari ?? '2026-01-01'), 0, 10);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Karyawan HC Learn',
            'Tahun' => (int) substr($acuan, 0, 4),
            'Status_Cycle' => 'PREPARED',
            'Tanggal_Acuan_Organisasi' => $acuan,
            'Periode_Dinilai_Dari' => $acuan,
            'Periode_Dinilai_Sampai' => $relasi->Berlaku_Sampai === null
                ? $acuan
                : min($acuan, substr((string) $relasi->Berlaku_Sampai, 0, 10)),
        ]);

        $cycleId = (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');
        $policy = DB::table('N_PA_Rater_Policy')->where('Id_PA_Rater_Policy', $this->detail->PA_Rater_Policy_Id)->first();
        $this->assertNotNull($policy, 'Policy sumber detail uji tidak ada.');

        DB::table('N_PA_Cycle_Rater_Policy_Snapshot')->insert([
            'PA_Cycle_Id' => $cycleId,
            'ID_Level' => $this->peserta->ID_Level,
            'PA_Rater_Policy_Id' => $policy->Id_PA_Rater_Policy,
            'Kode_Policy_Salinan' => $kode,
            'Nama_Policy_Salinan' => 'Policy Uji HC Learn',
            'Versi_Policy_Salinan' => max(1, (int) $policy->Versi),
        ]);

        $snapshotId = (int) DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->value('Id_PA_Cycle_Rater_Policy_Snapshot');

        DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')->insert([
            'PA_Cycle_Rater_Policy_Snapshot_Id' => $snapshotId,
            'PA_Rater_Policy_Detail_Id' => $this->detail->Id_PA_Rater_Policy_Detail,
            'PA_Rater_Category_Id' => $this->detail->PA_Rater_Category_Id,
            'Kode_Kategori_Salinan' => $this->detail->Kode_Kategori,
            'Nama_Kategori_Salinan' => $this->detail->Nama_Kategori,
            'Organization_Relationship_Type_Id' => $this->detail->Organization_Relationship_Type_Id,
            'Kode_Relasi_Salinan' => $this->detail->Kode_Kategori,
            'Bobot_Persen' => 100,
            'Min_Rater' => 1,
            'Maks_Rater' => 1,
            'Min_Rater_Selesai' => 1,
            'Flag_Izinkan_Manual' => 'T',
            'Urutan_Tampil' => 1,
        ]);

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $cycleId,
            'Kode_Perusahaan' => $this->peserta->Kode_Perusahaan,
            'Kode_Karyawan' => $this->peserta->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $this->peserta->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $this->peserta->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $this->peserta->ID_Level,
            'PA_Cycle_Rater_Policy_Snapshot_Id' => $snapshotId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        return $cycleId;
    }

    /**
     * Satu penugasan untuk penilai yang tadinya hanya proyeksi.
     *
     * Ditulis mentah, bukan lewat pembentukan penugasan: yang diuji di sini pembacaan asal
     * populasinya, bukan pemilihan calonnya — dan pembentukan sungguhan akan ikut membawa
     * aturan, bobot, dan penjaga yang tidak ada hubungannya dengan pertanyaan ini.
     */
    private function buatPenugasan(): void
    {
        $participantId = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $this->peserta->Kode_Karyawan)
            ->value('Id_PA_Participant');

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $this->penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $this->penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $this->penilai->Nama,
            'PA_Rater_Category_Id' => $this->detail->PA_Rater_Category_Id,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'BELUM_MULAI',
        ]);
    }

    /** @return list<string> */
    private function kodeDariPemeriksaan(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return array_values(array_filter(explode(',', (string) ($query['kode_karyawan'] ?? ''))));
    }

    /** @param list<string> $kode @param list<string> $belum */
    /**
     * @param  list<string>  $kode  yang ditanyakan
     * @param  list<string>  $belum  yang dijawab BELUM_TERDAFTAR
     * @param  list<string>  $resign  yang dijawab SUDAH_RESIGN — terdaftar, tetapi tak terpakai
     */
    private function jawabanPemeriksaan(array $kode, array $belum, array $resign = []): array
    {
        $bentuk = function (string $k) use ($belum, $resign): array {
            if (in_array($k, $belum, true)) {
                return ['ada' => false, 'siap' => false, 'alasan' => 'BELUM_TERDAFTAR',
                    'keterangan' => 'Karyawan belum ada di HCLearn.'];
            }

            if (in_array($k, $resign, true)) {
                return ['ada' => true, 'siap' => false, 'alasan' => 'SUDAH_RESIGN',
                    'keterangan' => 'Karyawan terdaftar tetapi tidak aktif / sudah resign.'];
            }

            return ['ada' => true, 'siap' => true, 'alasan' => 'SIAP',
                'keterangan' => 'Karyawan terdaftar dan aktif.'];
        };

        $data = array_map(fn (string $k) => [
            'dicari' => $k,
            'ada' => $bentuk($k)['ada'],
            'siap_dipakai' => $bentuk($k)['siap'],
            'alasan' => $bentuk($k)['alasan'],
            'keterangan' => $bentuk($k)['keterangan'],
            'karyawan' => null,
        ], $kode);

        $siap = count(array_filter($data, fn (array $d) => $d['siap_dipakai']));

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Pemeriksaan karyawan selesai.',
            'result' => [
                'dicari_dengan' => 'kode_karyawan',
                'ringkasan' => [
                    'diminta' => count($kode),
                    'ditemukan' => count(array_filter($data, fn (array $d) => $d['ada'])),
                    'belum_ada' => count($kode) - count(array_filter($data, fn (array $d) => $d['ada'])),
                    'siap_dipakai' => $siap,
                ],
                'data' => $data,
            ],
        ];
    }
}
