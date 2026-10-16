<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnScheduleService;
use App\Services\KPI\PerformanceAppraisal\PaParticipantEditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Penjadwalan penilai lewat kontrak HC Learn yang sebenarnya.
 *
 * ── Apa yang dijaga ───────────────────────────────────────────────────────────
 *
 * Bentuk kontraknya ASINKRON dan MASSAL, dan dua sifat itulah yang paling mudah salah
 * diterapkan:
 *
 *  1. `store` menjawab **202** — tokennya BELUM ada. Baris yang lahir dari penjadwalan
 *     karena itu harus berstatus ANTRE dan membawa `process_id`, BUKAN dianggap selesai.
 *     Menganggapnya selesai membuat layar melaporkan penilai sudah punya pintu padahal
 *     tautannya belum ada, dan admin baru tahu ketika orangnya mengeluh.
 *
 *  2. Satu kiriman memuat SATU master ujian. Penugasan dari sel yang berbeda ujiannya harus
 *     terpisah kirimannya; menggabungkannya berarti sebagian penilai menerima ujian yang
 *     bukan miliknya.
 *
 * Ditambah dua penjaga yang tidak boleh longgar: karyawan yang belum dikenal HC Learn
 * disaring DI MUKA (kalau tidak, satu orang yang belum terdaftar membatalkan seluruh
 * kiriman), dan penugasan yang sudah punya pintu tidak dijadwalkan dua kali.
 *
 * HC Learn dipalsukan di tingkat HTTP: yang teruji dengan begitu adalah jalur yang
 * benar-benar dipakai production, termasuk penandatanganan dan pembacaan balasannya.
 *
 * ── Cara membuatnya merah ─────────────────────────────────────────────────────
 *
 * Tulis Status_Integrasi 'BERHASIL' saat penjadwalan; gabungkan seluruh subjek ke satu
 * kiriman tanpa memisahkan ujiannya; atau lewati pemeriksaan karyawan.
 */
class PenjadwalanHcLearnTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $levelId;

    /** @var array<string, int> kode kategori => id */
    private array $kategori = [];

    /** @var list<object> */
    private array $stok = [];

    /** @var list<int> */
    private array $assignmentId = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->stok = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(4)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();

        $this->assertCount(4, $this->stok, 'Uji ini butuh empat karyawan aktif.');

        $pengguna = User::whereNotNull('User_Key')->first();
        $this->assertNotNull($pengguna, 'Tidak ada pengguna ber-User_Key.');
        $this->actingAs($pengguna);

        $this->levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        foreach (DB::table('N_PA_Rater_Category')->whereIn('Kode_Kategori', ['ATASAN', 'SELF'])->get() as $k) {
            $this->kategori[$k->Kode_Kategori] = (int) $k->Id_PA_Rater_Category;
        }

        $this->assertCount(2, $this->kategori, 'Uji ini butuh kategori ATASAN dan SELF.');

        $this->cycleId = $this->buatSiklus();
    }

    public function test_penjadwalan_berhenti_pada_antre_dan_menyimpan_process_id(): void
    {
        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        $this->palsukanHcLearn(['proses-A']);

        $hasil = app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        $this->assertSame(1, $hasil['diminta']);
        $this->assertSame(1, $hasil['dititipkan']);
        $this->assertSame(0, $hasil['gagal']);
        $this->assertSame(['proses-A'], $hasil['proses']);

        $baris = $this->integrasi($this->assignmentId[0]);

        /* ANTRE, bukan BERHASIL. Tokennya memang belum ada pada tahap ini — mengaku sudah
           berpintu di sini adalah kebohongan yang baru ketahuan saat penilai membuka
           tautannya. */
        $this->assertSame('ANTRE', $baris->Status_Integrasi);
        $this->assertSame('proses-A', $baris->Process_Id);
        $this->assertNull($baris->Api_Ref, 'api_ref belum boleh ada sebelum statusnya ditarik.');
    }

    public function test_satu_kiriman_satu_master_ujian(): void
    {
        /* Dua sel dengan ujian BERBEDA. Menggabungkannya ke satu kiriman berarti sebagian
           penilai menerima ujian yang bukan miliknya — dan HC Learn tidak bisa menolaknya,
           karena dari sisi mereka kirimannya sah. */
        $this->buatSel('ATASAN', '100160');
        $this->buatSel('SELF', '100161');

        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[2], 'SELF');

        $this->palsukanHcLearn(['proses-A', 'proses-B']);

        $hasil = app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        $this->assertSame(2, $hasil['dititipkan']);
        $this->assertCount(2, $hasil['proses'], 'Dua ujian berbeda harus jadi dua kiriman.');

        $ujianDikirim = [];

        foreach (Http::recorded() as [$permintaan]) {
            if (str_contains($permintaan->url(), '/pa/penjadwalan/store')) {
                $ujianDikirim[] = (string) $permintaan->data()['Id_Master_Ujian'];
            }
        }

        sort($ujianDikirim);
        $this->assertSame(['100160', '100161'], $ujianDikirim);
    }

    public function test_karyawan_yang_belum_terdaftar_disaring_di_muka(): void
    {
        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        $this->palsukanHcLearn(['proses-A'], siapSemua: false);

        $hasil = app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        $this->assertSame(0, $hasil['dititipkan']);
        $this->assertSame(1, $hasil['gagal']);
        $this->assertNotEmpty($hasil['contoh_galat']);

        /* Barisnya TIDAK dibuat: baris ANTRE atas kiriman yang tak pernah berangkat akan
           ditunggu selamanya oleh penarik status. */
        $this->assertNull($this->integrasi($this->assignmentId[0]));

        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/pa/penjadwalan/store'));
    }

    /**
     * `Id_Integrasi` YANG DIKIRIM ADALAH BARIS INTEGRASI, BUKAN PENUGASAN.
     *
     * HC Learn tidak peduli angka apa — dokumentasi mereka menyebutnya "angka milik HCIS
     * sendiri, dikembalikan apa adanya". Yang menentukan justru SISI KITA, dan sisi kita
     * sudah memutuskan: `PaApiGuard` menolak 403 bila `Id_Integrasi` pada callback tidak
     * sama dengan `Id_PA_Assignment_Integration`.
     *
     * Sampai 10 September 2026 penjadwalan mengirim `Id_PA_Assignment`. Dua sisi sistem yang
     * sama memakai dua arti untuk satu nama field: kiriman HC Learn dijawab 403 atas angka
     * yang kita sendiri yang memberikan. Tidak ada galat di sisi penjadwalan, tidak ada
     * gejala sampai callback pertama datang.
     *
     * SATU PENUGASAN BISA PUNYA BEBERAPA BARIS INTEGRASI (paket soal diganti, pintu dicetak
     * ulang). Id penugasan karena itu tidak pernah bisa menunjuk token yang mana.
     *
     * Cara membuatnya merah: kembalikan `Id_Integrasi` ke `assignment_id`.
     */
    public function test_id_integrasi_yang_dikirim_adalah_baris_integrasi(): void
    {
        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        $this->palsukanHcLearn(['proses-A']);
        app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        $baris = $this->integrasi($this->assignmentId[0]);
        $this->assertNotNull($baris, 'Baris integrasi tidak terbentuk.');

        $dikirim = null;

        foreach (Http::recorded() as [$permintaan]) {
            if (str_contains($permintaan->url(), '/pa/penjadwalan/store')) {
                $dikirim = $permintaan->data()['Id_Integrasi'][0]['Id_Integrasi'] ?? null;
            }
        }

        $this->assertSame(
            (int) $baris->Id_PA_Assignment_Integration,
            (int) $dikirim,
            'Id_Integrasi yang dikirim bukan id baris integrasi; callback HC Learn akan '
            .'dijawab 403 oleh PaApiGuard atas angka yang kita sendiri berikan.'
        );

        /* Dan BUKAN id penugasan. Tanpa penegasan ini, uji di atas tetap lolos pada data
           uji yang kebetulan punya id bernilai sama. */
        $this->assertNotSame(
            (int) $this->assignmentId[0],
            (int) $dikirim,
            'Id penugasan dan id baris integrasi kebetulan sama pada data uji ini; '
            .'penjaganya tidak membuktikan apa pun.'
        );
    }

    /**
     * TANPA SESI, penjadwalan tetap bisa ditandatangani lewat akun layanan.
     *
     * `pa:jadwalkan-penilaian` dijalankan dari terminal dan penjadwal — di sana tidak ada
     * yang sedang masuk. Versi pertama `kirim()` hanya membaca `Auth::user()`, sehingga
     * perintah itu berhenti pada "Sambungan HC Learn belum tersedia" sebelum satu permintaan
     * pun terkirim, dan kalimatnya menuduh konfigurasi domain yang justru lengkap.
     *
     * CARA MEMBUATNYA MERAH: kembalikan `identitas()` menjadi `Auth::user()->User_Key ?? null`.
     */
    public function test_tanpa_sesi_penjadwalan_ditandatangani_akun_layanan(): void
    {
        config([
            'services.frans.hclearn_api.service_user_key' => 'kunci-layanan-uji',
            'services.frans.hclearn_api.service_kode_users' => 'SVC1',
        ]);

        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        $this->palsukanHcLearn(['proses-A']);

        /* Sesi DILEPAS, meniru jalur perintah. */
        auth()->logout();

        $hasil = app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        $this->assertSame(1, $hasil['dititipkan'], 'Penjadwalan tanpa sesi tidak sampai terkirim.');

        Http::assertSent(fn ($permintaan) => str_contains($permintaan->url(), '/pa/penjadwalan/store')
            && ($permintaan->header('X-App-ID')[0] ?? null) === 'kunci-layanan-uji');
    }

    /**
     * Akun layanan HARUS berpasangan; separuh terisi ditolak di sisi kita, bukan di sana.
     *
     * HC Learn memverifikasi X-App-ID dan X-Kode-Karyawan bersama, jadi kunci tanpa kode
     * dijawab 401 — jawaban yang datang sesudah kiriman melintas dan jauh lebih sulit dibaca
     * daripada penolakan di rumah sendiri.
     */
    public function test_akun_layanan_separuh_terisi_tidak_dipakai(): void
    {
        config([
            'services.frans.hclearn_api.service_user_key' => 'kunci-layanan-uji',
            'services.frans.hclearn_api.service_kode_users' => null,
        ]);

        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        $this->palsukanHcLearn(['proses-A']);
        auth()->logout();

        $pesan = null;

        try {
            app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);
        } catch (RuntimeException $e) {
            $pesan = $e->getMessage();
        }

        $this->assertNotNull($pesan, 'Kiriman lolos tanpa identitas yang berpasangan.');

        /* Pesannya menyebut AKUN LAYANAN, bukan "domain kosong". Sebab yang salah disebut
           mengirim orang membaca berkas konfigurasi yang justru sudah benar. */
        $this->assertStringContainsString('akun layanan', $pesan);

        Http::assertNotSent(fn ($permintaan) => str_contains($permintaan->url(), '/pa/penjadwalan/store'));
    }

    /**
     * JENDELA SEHARI PENUH TETAP SEHARI PENUH SAMPAI DI SEBERANG.
     *
     * Kedua ujung jendela diisi lewat pemilih TANGGAL, jadi tersimpan sebagai tengah malam.
     * "Pengisian 10 September sampai 10 September" berarti sehari penuh — tetapi yang terkirim
     * dulu `00:00:00` pada kedua ujungnya, jendela berdurasi nol, dan HC Learn menolaknya 422
     * (`Waktu_Akhir harus sesudah Waktu_Mulai`). Karena satu penolakan membatalkan SELURUH
     * kiriman, 400 penugasan siklus PA-2026-01 tercatat GAGAL sekaligus pada 10 September 2026.
     *
     * CARA MEMBUATNYA MERAH: kembalikan `jendelaSiklus()` memulangkan `Pengisian_Sampai` apa
     * adanya.
     */
    public function test_jendela_bertanggal_dikirim_sampai_akhir_hari(): void
    {
        $hari = now()->format('Y-m-d');

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->update([
            'Pengisian_Dari' => $hari.' 00:00:00',
            'Pengisian_Sampai' => $hari.' 00:00:00',
        ]);

        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        $this->palsukanHcLearn(['proses-A']);
        $hasil = app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        $this->assertSame(1, $hasil['dititipkan'], 'Jendela sehari penuh malah tidak terkirim.');

        Http::assertSent(function ($permintaan) use ($hari) {
            if (! str_contains($permintaan->url(), '/pa/penjadwalan/store')) {
                return false;
            }

            $data = $permintaan->data();

            return ($data['Waktu_Mulai'] ?? null) === $hari.' 00:00:00'
                && ($data['Waktu_Akhir'] ?? null) === $hari.' 23:59:59';
        });
    }

    /**
     * JAM YANG DITULIS ADMIN TIDAK DIGESER.
     *
     * Pembentangan hanya berlaku pada tengah malam — penanda "ini tanggal, bukan jam". Kalau
     * suatu hari jendelanya benar-benar ditutup pukul 17.00, jam itu miliknya, dan memindahkan
     * penutupan tujuh jam lebih malam berarti penilai yang seharusnya sudah terkunci masih
     * bisa mengubah nilainya.
     */
    public function test_jam_penutupan_yang_ditulis_tidak_dibentangkan(): void
    {
        $tutup = now()->addDays(3)->format('Y-m-d').' 17:00:00';

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->update([
            'Pengisian_Sampai' => $tutup,
        ]);

        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        $this->palsukanHcLearn(['proses-A']);
        app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        Http::assertSent(fn ($permintaan) => ! str_contains($permintaan->url(), '/pa/penjadwalan/store')
            || ($permintaan->data()['Waktu_Akhir'] ?? null) === $tutup);
    }

    /**
     * JENDELA BERDURASI NOL DITOLAK DI RUMAH SENDIRI, sebelum satu permintaan pun berangkat.
     *
     * Satu penolakan HC Learn membatalkan SELURUH kiriman, jadi jendela mustahil yang
     * dibiarkan berangkat menghasilkan ratusan baris GAGAL berbarengan atas sebab yang sudah
     * bisa diketahui sebelumnya — dan admin membaca "HC Learn menolak" untuk kesalahan yang
     * sepenuhnya ada di data kita.
     *
     * JAMNYA BUKAN TENGAH MALAM, dan itu bagian dari premisnya. `CK_PA_Cycle_Pengisian` sudah
     * menolak jendela TERBALIK di tingkat basis data, tetapi ia mengizinkan `>=` — jadi yang
     * masih bisa lolos adalah dua ujung yang PERSIS SAMA. Pada tengah malam keadaan itu justru
     * sah karena dibentangkan ke akhir hari; yang tersisa sebagai jendela nol adalah dua ujung
     * berjam sama seperti di sini.
     */
    public function test_jendela_berdurasi_nol_ditolak_sebelum_dikirim(): void
    {
        $saat = now()->addDays(2)->format('Y-m-d').' 08:00:00';

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->update([
            'Pengisian_Dari' => $saat,
            'Pengisian_Sampai' => $saat,
        ]);

        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        $this->palsukanHcLearn(['proses-A']);

        $pesan = null;

        try {
            app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);
        } catch (RuntimeException $e) {
            $pesan = $e->getMessage();
        }

        $this->assertNotNull($pesan, 'Jendela terbalik tetap dikirim ke HC Learn.');
        $this->assertStringContainsString('Jendela pengisian', $pesan);
        Http::assertNothingSent();
    }

    /**
     * SEBAB PENOLAKAN 422 IKUT TERSIMPAN, bukan hanya kalimat umumnya.
     *
     * `message` mereka selalu berbunyi "Permintaan tidak lolos pemeriksaan" — kalimat yang
     * sama untuk setiap sebab — sementara yang menjelaskan bersarang di `meta.error.details`.
     * Tanpa ini, 400 baris GAGAL menyimpan satu kalimat yang tidak menunjuk apa pun, dan
     * satu-satunya cara mengetahui sebabnya adalah mengirim ulang sambil menyadap jaringan.
     *
     * Bentuk balasan di uji ini disalin dari penolakan SUNGGUHAN pada 10 September 2026.
     */
    public function test_rincian_penolakan_hc_learn_ikut_tersimpan(): void
    {
        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        Http::fake([
            '*/api/v1/pa/checking/karyawan*' => Http::response([
                'success' => true,
                'result' => [
                    'data' => array_map(fn ($k) => [
                        'dicari' => $k->Kode_Karyawan,
                        'ada' => true,
                        'siap_dipakai' => true,
                        'alasan' => 'SIAP',
                        'keterangan' => 'Karyawan terdaftar.',
                    ], $this->stok),
                ],
            ]),
            '*/api/v1/pa/penjadwalan/store' => Http::response([
                'success' => false,
                'status' => 422,
                'message' => 'Permintaan tidak lolos pemeriksaan.',
                'result' => null,
                'meta' => [
                    'error' => [
                        'code' => 'VALIDASI-GAGAL',
                        'details' => ['Waktu_Akhir' => ['Waktu_Akhir harus sesudah Waktu_Mulai.']],
                    ],
                ],
            ], 422),
        ]);

        $hasil = app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        $this->assertSame(1, $hasil['gagal']);
        $this->assertNotEmpty($hasil['contoh_galat']);
        $this->assertStringContainsString('Waktu_Akhir', $hasil['contoh_galat'][0]);
        $this->assertStringContainsString('sesudah Waktu_Mulai', $hasil['contoh_galat'][0]);

        /* Dan sebabnya juga TERSIMPAN pada barisnya, bukan hanya lewat di layar sekali: yang
           membereskannya besok pagi membaca kolom ini, bukan tangkapan layar. Barisnya dicari
           TANPA saringan Flag_Aktif — kiriman yang gagal memang dinonaktifkan supaya percobaan
           berikutnya melihat penugasannya lagi. */
        $baris = DB::table('N_PA_Assignment_Integration')
            ->where('PA_Assignment_Id', $this->assignmentId[0])
            ->first(['Status_Integrasi', 'Pesan_Gagal']);

        $this->assertNotNull($baris);
        $this->assertSame('GAGAL', $baris->Status_Integrasi);
        $this->assertStringContainsString('Waktu_Akhir', (string) $baris->Pesan_Gagal);
    }

    /**
     * BARIS ANTREAN BUKAN PINTU, dan ringkasan pra-kunci tidak boleh menghitungnya begitu.
     *
     * `store` menjawab 202 dan menggarap antreannya belakangan; tautan penilai baru ada sesudah
     * hasilnya ditarik. Sampai 10 September 2026 `pintu.tercetak` menerima baris integrasi APA
     * PUN yang aktif, termasuk yang masih ANTRE tanpa satu tautan pun — sehingga siklus dengan
     * 1.140 baris antre dan NOL tautan dilaporkan "kurang 0", dan layar penjadwalan memasang
     * "100% SELESAI · seluruh rater telah memiliki akses". Admin membuka jendela pengisian, dan
     * tidak seorang pun bisa masuk.
     *
     * CARA MEMBUATNYA MERAH: hitung `tercetak` tanpa syarat `Api_Ref IS NOT NULL`.
     */
    public function test_baris_antre_belum_dihitung_sebagai_pintu(): void
    {
        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        $this->palsukanHcLearn(['proses-A']);
        app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        $baris = $this->integrasi($this->assignmentId[0]);
        $this->assertNotNull($baris);
        $this->assertSame('ANTRE', $baris->Status_Integrasi, 'Prasyarat uji gagal: barisnya bukan ANTRE.');
        $this->assertNull($baris->Api_Ref, 'Prasyarat uji gagal: tautannya sudah terbit.');

        $pintu = app(PaCyclePreparationService::class)->ringkasanPraKunci($this->cycleId)['pintu'];

        $this->assertSame(0, $pintu['tercetak'], 'Baris antre dihitung sebagai pintu yang sudah tercetak.');
        $this->assertSame(1, $pintu['antre'], 'Baris antre tidak dilaporkan sebagai antre.');
        $this->assertSame(1, $pintu['kurang'], 'Penugasan tanpa tautan dilaporkan sudah cukup.');
    }

    /**
     * Sesudah hasilnya DITARIK, barulah ia pintu. Tanpa penegasan ini, uji di atas tetap hijau
     * pada hitungan yang tidak pernah menghitung apa pun sebagai tercetak.
     */
    public function test_pintu_terhitung_sesudah_hasilnya_ditarik(): void
    {
        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        $this->palsukanHcLearn(['proses-A']);
        app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        $this->palsukanStatus('proses-A', [[
            'Id_Integrasi' => $this->idIntegrasi($this->assignmentId[0]),
            'Kode_Karyawan' => $this->stok[1]->Kode_Karyawan,
            'status' => 'BERHASIL',
            'api_ref' => str_repeat('a', 32),
            'short_token' => 'EVOG31GT',
            'akses_otp' => 'XCBADFBM',
            'waktu_mulai' => '2026-09-01 08:00:00',
            'waktu_akhir' => '2026-09-30 17:00:00',
            'link_ujian' => 'https://hclearn.example.com/evo/rekrutmen?wo_aut=EVOG31GT',
        ]]);

        app(PaHcLearnScheduleService::class)->tarikStatus($this->cycleId, 1);

        $pintu = app(PaCyclePreparationService::class)->ringkasanPraKunci($this->cycleId)['pintu'];

        $this->assertSame(1, $pintu['tercetak'], 'Pintu yang sudah bertautan tidak terhitung.');
        $this->assertSame(0, $pintu['antre']);
        $this->assertSame(0, $pintu['kurang']);
    }

    public function test_menarik_status_mengisi_pintunya(): void
    {
        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        $this->palsukanHcLearn(['proses-A']);
        app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        $this->palsukanStatus('proses-A', [[
            'Id_Integrasi' => $this->idIntegrasi($this->assignmentId[0]),
            'Kode_Karyawan' => $this->stok[1]->Kode_Karyawan,
            'status' => 'BERHASIL',
            'api_ref' => '6e586446c08633488e35c207408457f5',
            'short_token' => 'EVOG31GT',
            'akses_otp' => 'XCBADFBM',
            'waktu_mulai' => '2026-09-01 08:00:00',
            'waktu_akhir' => '2026-09-30 17:00:00',
            'link_ujian' => 'https://hclearn.example.com/evo/rekrutmen?wo_aut=EVOG31GT&otp=XCBADFBM',
        ]]);

        $hasil = app(PaHcLearnScheduleService::class)->tarikStatus($this->cycleId, 1);

        $this->assertSame(1, $hasil['terisi']);
        $this->assertSame(0, $hasil['gagal']);

        $baris = $this->integrasi($this->assignmentId[0]);
        $this->assertSame('BERHASIL', $baris->Status_Integrasi);
        $this->assertSame('6e586446c08633488e35c207408457f5', $baris->Api_Ref);
        $this->assertSame('EVOG31GT', $baris->Short_Token);
        $this->assertNotNull($baris->Link_Ujian);
    }

    public function test_subjek_yang_gagal_menyimpan_sebabnya(): void
    {
        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        $this->palsukanHcLearn(['proses-A']);
        app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        $this->palsukanStatus('proses-A', [[
            'Id_Integrasi' => $this->idIntegrasi($this->assignmentId[0]),
            'status' => 'GAGAL',
            'pesan' => 'Paket ujian sudah tidak aktif.',
        ]]);

        $hasil = app(PaHcLearnScheduleService::class)->tarikStatus($this->cycleId, 1);

        $this->assertSame(1, $hasil['gagal']);

        /* Sebabnya DISIMPAN. Tanpa itu admin hanya melihat "gagal" dan satu-satunya cara
           mengetahui kenapa adalah bertanya ke HC Learn. */
        $baris = $this->integrasi($this->assignmentId[0]);
        $this->assertSame('GAGAL', $baris->Status_Integrasi);
        $this->assertSame('Paket ujian sudah tidak aktif.', $baris->Pesan_Gagal);
    }

    public function test_penugasan_yang_sudah_berpintu_tidak_dijadwalkan_dua_kali(): void
    {
        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');

        $this->palsukanHcLearn(['proses-A']);
        app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        $kedua = app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);

        $this->assertSame(0, $kedua['diminta'], 'Penjadwalan kedua tidak boleh menyentuh yang sudah berpintu.');
        $this->assertSame(1, DB::table('N_PA_Assignment_Integration')
            ->where('PA_Assignment_Id', $this->assignmentId[0])
            ->where('Flag_Aktif', 'Y')
            ->count());
    }

    /* ------------------------------------------------------------------ *
     * Penugasan yang dibatalkan: jadwal HC Learn-nya ikut dihapus          *
     * ------------------------------------------------------------------ */

    /**
     * Sebelum 13 Okt 2026 pembatalan tidak menyentuh HC Learn: penilai yang dibatalkan tetap
     * memegang tautan aktif, mengisi, lalu kirimannya ditolak HCIS.
     *
     * Cara membuatnya merah: hapus `HapusJadwalHcLearnPaJob::dispatch` dari `batalkan()`.
     */
    public function test_membatalkan_penugasan_menghapus_jadwalnya_di_hc_learn(): void
    {
        $id = $this->penugasanBerpintu('6e586446c08633488e35c207408457f5');

        Http::fake(['*/api/v1/pa/delete/penjadwalan/*' => Http::response(['success' => true, 'status' => 200, 'result' => []])]);

        app(PaAssignmentBuilderService::class)->batalkan($id, 'Penilai diganti (uji).', 1);

        Http::assertSent(fn ($r) => $r->method() === 'DELETE'
            && str_ends_with($r->url(), '/api/v1/pa/delete/penjadwalan/6e586446c08633488e35c207408457f5'));
        $this->assertNull($this->integrasi($id), 'Baris integrasi masih aktif sesudah jadwalnya dihapus.');
        $this->assertSame(0, app(PaHcLearnScheduleService::class)->ringkasanJadwalDibatalkan($this->cycleId)['jumlah']);
    }

    /**
     * JADWALKAN ULANG yang sudah SELESAI: jadwal lama dihapus di HC Learn, jawabannya tidak
     * lagi dihitung, status kembali BELUM_MULAI, lalu jadwal baru dikirim (13 Okt 2026).
     */
    public function test_jadwalkan_ulang_menghapus_jadwal_dan_jawaban_lalu_menjadwalkan_baru(): void
    {
        $id = $this->penugasanBerpintu('a1b2c3d4e5f60718293a4b5c6d7e8f90');
        DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $id)
            ->update(['Status_Penugasan' => 'SELESAI', 'Started_At' => now()->subHour(), 'Finished_At' => now()]);
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->update(['Status_Cycle' => 'OPEN']);
        app(\App\Services\KPI\PerformanceAppraisal\PaCycleGate::class)->lupakanMemo();

        $this->palsukanHcLearn(['proses-ulang']);
        Http::fake(['*/api/v1/pa/delete/penjadwalan/*' => Http::response(['success' => true, 'status' => 200, 'result' => []])]);

        $hasil = app(PaHcLearnScheduleService::class)->jadwalkanUlangPintu($id, 'Penilai meninggalkan tes (uji).', 1);

        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/a1b2c3d4e5f60718293a4b5c6d7e8f90'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/v1/pa/penjadwalan/store'));
        $this->assertTrue($hasil['jawaban_dihapus']);
        $this->assertSame('BELUM_MULAI', DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $id)->value('Status_Penugasan'));
        $baru = $this->integrasi($id);
        $this->assertNotNull($baru, 'Jadwal baru tidak tercatat.');
        $this->assertNotSame('a1b2c3d4e5f60718293a4b5c6d7e8f90', $baru->Api_Ref);
        $this->assertSame(1, DB::table('N_PA_Assignment_Integration')->where('PA_Assignment_Id', $id)->where('Flag_Aktif', 'T')
            ->where('Pesan_Gagal', 'like', 'Dijadwalkan ulang:%')->count());
    }

    /**
     * Di antrean TIDAK ADA pengguna yang masuk, dan akun layanan dev kosong — job latar harus
     * menandatangani sebagai admin yang memulainya. Tanpa itu setiap kiriman gagal "tidak bisa
     * ditandatangani" (13 Okt 2026, ditemukan sebelum uji nyata).
     */
    public function test_job_latar_menandatangani_sebagai_admin_pemulainya(): void
    {
        $penggunaId = (int) auth()->id();
        $id = $this->penugasanBerpintu(str_repeat('d', 32));
        config(['services.frans.hclearn_api.service_user_key' => null, 'services.frans.hclearn_api.service_kode_users' => null]);
        $this->app['auth']->forgetGuards();
        $this->assertFalse(auth()->check());

        Http::fake(['*/api/v1/pa/delete/penjadwalan/*' => Http::response(['success' => true, 'status' => 200, 'result' => []])]);

        app(PaAssignmentBuilderService::class)->batalkan($id, 'Dibatalkan dari antrean (uji).', $penggunaId);

        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), str_repeat('d', 32)));
        $this->assertNull($this->integrasi($id));
    }

    public function test_penolakan_hc_learn_tersimpan_dan_bisa_dicoba_lagi(): void
    {
        $id = $this->penugasanBerpintu(str_repeat('b', 32));

        Http::fake(['*/api/v1/pa/delete/penjadwalan/*' => Http::response([
            'success' => false, 'status' => 422, 'message' => 'Peserta sudah mengerjakan.',
        ], 422)]);

        app(PaAssignmentBuilderService::class)->batalkan($id, 'Dibatalkan (uji).', 1);

        /* Pembatalannya TETAP SAH walau HC Learn menolak. */
        $this->assertSame('T', DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $id)->value('Flag_Aktif'));

        $sisa = app(PaHcLearnScheduleService::class)->ringkasanJadwalDibatalkan($this->cycleId);
        $this->assertSame(1, $sisa['jumlah']);
        $this->assertSame(1, $sisa['gagal']);
        $this->assertStringContainsString('Peserta sudah mengerjakan', (string) $sisa['contoh']);
    }

    public function test_jadwal_yang_masih_antre_dihapus_sesudah_hasilnya_ditarik(): void
    {
        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $id = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');
        $this->palsukanHcLearn(['proses-A']);
        app(PaHcLearnScheduleService::class)->jadwalkanSiklus($this->cycleId, 1);
        $idIntegrasi = $this->idIntegrasi($id);

        app(PaAssignmentBuilderService::class)->batalkan($id, 'Dibatalkan saat masih antre (uji).', 1);

        /* Belum ada `Api_Ref` untuk dihapus — ditunggu, bukan dilupakan. */
        $this->assertSame(1, app(PaHcLearnScheduleService::class)->ringkasanJadwalDibatalkan($this->cycleId)['jumlah']);

        $this->palsukanStatus('proses-A', [[
            'Id_Integrasi' => $idIntegrasi,
            'Kode_Karyawan' => $this->stok[1]->Kode_Karyawan,
            'status' => 'BERHASIL',
            'api_ref' => str_repeat('c', 32),
            'short_token' => 'EVOG31GT',
            'akses_otp' => 'XCBADFBM',
            'waktu_mulai' => '2026-09-01 08:00:00',
            'waktu_akhir' => '2026-09-30 17:00:00',
            'link_ujian' => 'https://hclearn.example.com/evo/rekrutmen?wo_aut=EVOG31GT',
        ]]);
        Http::fake(['*/api/v1/pa/delete/penjadwalan/*' => Http::response(['success' => true, 'status' => 200, 'result' => []])]);

        app(PaHcLearnScheduleService::class)->tarikStatus($this->cycleId, 1);

        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), str_repeat('c', 32)));
        $this->assertSame(0, app(PaHcLearnScheduleService::class)->ringkasanJadwalDibatalkan($this->cycleId)['jumlah']);
    }

    /**
     * Mengecualikan peserta yang SATU penilainya sudah mengisi: sebelum 13 Okt 2026 seluruh
     * pengecualian gagal, karena `batalkan()` menolak penugasan yang sudah diisi.
     */
    public function test_mengecualikan_peserta_melewati_penugasan_yang_sudah_diisi(): void
    {
        $this->buatSel('ATASAN', '100160');
        $this->buatSel('SELF', '100161');
        $peserta = $this->buatPeserta($this->stok[0]);
        $diisi = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');
        $belum = $this->buatPenugasan($peserta, $this->stok[2], 'SELF');
        DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $diisi)->update(['Status_Penugasan' => 'SELESAI']);
        DB::table('N_PA_Participant')->where('Id_PA_Participant', $peserta)->update(['PA_Cycle_Rater_Policy_Snapshot_Id' => null]);

        $hasil = app(PaParticipantEditService::class)->ubahStatus($peserta, 'DIKECUALIKAN', 'Resign (uji).', 1);

        $this->assertSame(1, $hasil['penugasan_dibatalkan']);
        $this->assertSame('Y', DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $diisi)->value('Flag_Aktif'));
        $this->assertSame('DIBATALKAN', DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $belum)->value('Status_Penugasan'));
    }

    /** Penugasan dengan baris integrasi BERHASIL ber-`Api_Ref` — keadaan sesudah dijadwalkan. */
    private function penugasanBerpintu(string $apiRef): int
    {
        $this->buatSel('ATASAN', '100160');
        $peserta = $this->buatPeserta($this->stok[0]);
        $id = $this->buatPenugasan($peserta, $this->stok[1], 'ATASAN');
        $paket = (int) DB::table('N_PA_Cycle_Question_Package')->where('PA_Cycle_Id', $this->cycleId)->value('Id_PA_Cycle_Question_Package');

        DB::table('N_PA_Assignment_Integration')->insert([
            'PA_Assignment_Id' => $id,
            'PA_Cycle_Question_Package_Id' => $paket,
            'Flag_Aktif' => 'Y',
            'Status_Integrasi' => 'BERHASIL',
            'Api_Ref' => $apiRef,
            'Process_Id' => 'proses-uji',
        ]);

        return $id;
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    /** @param list<string> $prosesId */
    private function palsukanHcLearn(array $prosesId, bool $siapSemua = true): void
    {
        $antrean = array_map(fn ($id) => Http::response([
            'success' => true,
            'status' => 202,
            'result' => [
                'process_id' => $id,
                'total_subjek' => 1,
                'status' => 'ANTRE',
                'url_status' => 'https://hclearn.example.com/api/v1/pa/penjadwalan/status/'.$id,
                'id_master_ujian' => 100160,
                'nama_ujian' => 'Employee Assessment 360 - STAFF',
            ],
        ], 202), $prosesId);

        Http::fake([
            '*/api/v1/pa/checking/karyawan*' => Http::response([
                'success' => true,
                'result' => [
                    'data' => array_map(fn ($k) => [
                        'dicari' => $k->Kode_Karyawan,
                        'ada' => $siapSemua,
                        'siap_dipakai' => $siapSemua,
                        'alasan' => $siapSemua ? 'SIAP' : 'BELUM_TERDAFTAR',
                        'keterangan' => $siapSemua ? 'Karyawan terdaftar.' : 'Karyawan belum ada di HCLearn.',
                    ], $this->stok),
                ],
            ]),
            '*/api/v1/pa/penjadwalan/store' => Http::sequence($antrean),
        ]);
    }

    /** @param list<array<string, mixed>> $baris */
    private function palsukanStatus(string $processId, array $baris): void
    {
        Http::fake([
            '*/api/v1/pa/penjadwalan/status/*' => Http::response([
                'success' => true,
                'status' => 200,
                'result' => [
                    'process_id' => $processId,
                    'status' => 'SELESAI',
                    'ringkasan' => ['total' => count($baris), 'antre' => 0],
                    'result' => $baris,
                ],
            ]),
        ]);
    }

    /** Id baris integrasi milik satu penugasan — itulah `Id_Integrasi` menurut kontrak kita. */
    private function idIntegrasi(int $assignmentId): int
    {
        $baris = $this->integrasi($assignmentId);

        $this->assertNotNull($baris, "Penugasan {$assignmentId} belum punya baris integrasi.");

        return (int) $baris->Id_PA_Assignment_Integration;
    }

    private function integrasi(int $assignmentId): ?object
    {
        return DB::table('N_PA_Assignment_Integration')
            ->where('PA_Assignment_Id', $assignmentId)
            ->where('Flag_Aktif', 'Y')
            ->first();
    }

    private function buatSiklus(): int
    {
        $kode = 'UJI-JDW-'.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus uji penjadwalan',
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => 'OPEN',
            'Pengisian_Dari' => now()->subDay()->format('Y-m-d H:i:s'),
            'Pengisian_Sampai' => now()->addDays(20)->format('Y-m-d H:i:s'),
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');
    }

    private function buatSel(string $kodeKategori, string $idMasterUjian): int
    {
        DB::table('N_PA_Cycle_Question_Package')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'PA_Rater_Category_Id' => $this->kategori[$kodeKategori],
            'Id_Master_Ujian' => $idMasterUjian,
            'Nama_Ujian_Sumber' => 'Ujian uji '.$idMasterUjian,
            'Sumber_Kontrak' => 'HCLEARN',
            'Flag_Aktif' => 'Y',
        ]);

        return (int) DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('PA_Rater_Category_Id', $this->kategori[$kodeKategori])
            ->value('Id_PA_Cycle_Question_Package');
    }

    private function buatPeserta(object $k): int
    {
        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $this->levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        return (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $k->Kode_Karyawan)
            ->value('Id_PA_Participant');
    }

    private function buatPenugasan(int $participantId, object $penilai, string $kodeKategori): int
    {
        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $this->kategori[$kodeKategori],
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'BELUM_MULAI',
        ]);

        return (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Kode_Karyawan_Penilai', $penilai->Kode_Karyawan)
            ->value('Id_PA_Assignment');
    }
}
