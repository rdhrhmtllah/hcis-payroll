<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use App\Services\KPI\PerformanceAppraisal\PaCycleSetupService;
use App\Services\KPI\PerformanceAppraisal\PaKategoriGugurException;
use App\Services\KPI\PerformanceAppraisal\PaPayloadMapperService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use App\Services\KPI\PerformanceAppraisal\PaResultPublishService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * RANTAI PENUH: buat siklus -> buka -> berbagai cara orang mengisi -> hitung -> terbit.
 *
 * ------------------------------------------------------------------------------------------
 * KENAPA BERKAS INI ADA, PADAHAL SUDAH ADA RantaiStatusTest
 * ------------------------------------------------------------------------------------------
 *
 * `RantaiStatusTest` memeriksa MATRIKS GERBANG — apakah tiap transisi status diizinkan.
 * Ia tidak pernah menjalankan satu siklus pun. Uji-uji lain memeriksa potongan: penugasan,
 * payload, penerbitan, masing-masing berdiri sendiri.
 *
 * Yang tidak dijaga siapa pun adalah SAMBUNGANNYA — apakah keadaan yang ditinggalkan satu
 * tahap benar-benar bisa dipakai tahap berikutnya. Modul ini sudah pernah membayar mahal
 * untuk celah itu: audit lapisan melaporkan 115/115 pada hari siklusnya tidak bisa
 * dijalani sampai selesai.
 *
 * ------------------------------------------------------------------------------------------
 * APA YANG DIPALSUKAN, DAN KENAPA
 * ------------------------------------------------------------------------------------------
 *
 * DUA hal saja, keduanya karena menjalankannya sungguhan akan menguji modul lain:
 *
 * 1. PESERTA DAN SALINAN ATURANNYA disisipkan langsung, tidak lewat `siapkan()`. Penyiapan
 *    sungguhan membentuk peserta dari seluruh karyawan aktif — 900-an orang pada basis data
 *    pengembangan — dan uji yang berjalan belasan menit tidak akan pernah dijalankan orang.
 *
 * 2. PILAR KPI diisi langsung pada tahap penerbitan. Nilainya datang dari modul KPI lewat
 *    rantai persetujuannya sendiri; membangun fixture-nya di sini berarti menguji KPI, bukan
 *    PA. Yang tetap dijalankan sungguhan adalah PENCAMPURAN kedua pilar dan penerbitannya.
 *
 * Selebihnya nyata: penguncian, pembukaan, penurunan tenggat, seluruh cara orang mengisi,
 * pemetaan butir, pembagian ulang bobot kategori, perhitungan, dan penerbitan.
 */
class RantaiPenuhTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $participantId;

    private int $levelId;

    private int $selfCategoryId;

    private int $atasanCategoryId;

    private int $packageId;

    private int $assignmentSelf;

    private int $assignmentAtasan;

    private string $tokenSelf;

    protected function setUp(): void
    {
        parent::setUp();

        /* Siklus lain yang terbuka dinetralkan: aturan "hanya satu siklus boleh terbuka"
           berlaku se-database, dan satu siklus demo yang tertinggal OPEN akan menolak
           pembukaan di sini dengan pesan tentang siklus yang tak ada hubungannya. Aman
           karena berkas ini memakai DatabaseTransactions. */
        DB::table('N_PA_Cycle')->where('Status_Cycle', 'OPEN')->update(['Status_Cycle' => 'CLOSED']);

        $this->selfCategoryId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', 'SELF')->value('Id_PA_Rater_Category');
        $this->atasanCategoryId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', 'ATASAN')->value('Id_PA_Rater_Category');

        if (! $this->selfCategoryId || ! $this->atasanCategoryId) {
            $this->markTestSkipped('Master kategori penilai SELF/ATASAN belum ada.');
        }
    }

    /** @test */
    public function siklus_bisa_ditempuh_dari_dibuat_sampai_terbit(): void
    {
        // ── 1. BUAT ────────────────────────────────────────────────────────────
        $kode = 'UJI-RANTAI-'.bin2hex(random_bytes(4));
        $setup = app(PaCycleSetupService::class);

        /* Kuncinya snake_case — `periksa()` memetakannya ke nama kolom. Mengirim nama
           kolom apa adanya diterima tanpa keluhan lalu ditolak sebagai "wajib diisi",
           karena tak satu pun kunci dikenali. */
        $dibuat = $setup->buat([
            'kode_cycle' => $kode,
            'nama_cycle' => 'Siklus Uji Rantai Penuh',
            'tahun' => 2026,
            'periode_dinilai_dari' => '2026-01-01',
            'periode_dinilai_sampai' => '2026-06-30',
        ], 1);

        $this->cycleId = (int) $dibuat['cycle_id'];
        $this->assertSame('DRAFT', $dibuat['status'], 'Siklus baru harus lahir DRAFT.');

        // ── 2. PENYIAPAN (difixture — lihat catatan kelas) ─────────────────────
        $this->siapkanSalinanDanPeserta($kode);
        $this->packageId = $this->buatPaket(['Q1', 'Q2']);
        $this->buatPenugasan();

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'PREPARED']);

        // ── 3. KUNCI ───────────────────────────────────────────────────────────
        $persiapan = app(PaCyclePreparationService::class);
        $persiapan->kunci($this->cycleId, 1);

        $this->assertSame('LOCKED', $this->statusSiklus(),
            'Penguncian tidak memindahkan status; tahap berikutnya akan menolak.');

        /* Idempoten: menekan kunci dua kali adalah hal paling wajar yang dilakukan orang
           saat layarnya lambat. Yang kedua tidak boleh melempar. */
        $persiapan->kunci($this->cycleId, 1);
        $this->assertSame('LOCKED', $this->statusSiklus());

        // Atribut kompetensi ikut dibekukan, sekali saja (DDL-2026-170).
        $this->assertSame(
            DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')->count(),
            DB::table('N_PA_Cycle_Competency_Snapshot')->where('PA_Cycle_Id', $this->cycleId)->count(),
            'Kunci harus membekukan atribut setiap kompetensi aktif tepat satu kali.');

        // ── 4. BUKA PENGISIAN, BESERTA JENDELANYA ──────────────────────────────
        $buka = $persiapan->bukaPengisian($this->cycleId, 1, [
            'pengisian_dari' => '2026-07-01',
            'pengisian_sampai' => '2026-07-31',
        ]);

        $this->assertSame('OPEN', $this->statusSiklus());
        $this->assertFalse($buka['dibuka_ulang'], 'Pembukaan pertama bukan pembukaan ulang.');

        /* TENGGAT BENAR-BENAR TURUN KE PENUGASAN. Tanpa ini penilai yang tidak mengisi
           tidak pernah tercatat terlambat, dan pengingat kehilangan dasarnya. */
        /* DUA: keduanya masih aktif saat jendela dibuka. ATASAN baru dibatalkan nanti,
           di tengah siklus — dan itu memang inti yang diuji berkas ini. */
        $this->assertSame(2, (int) $buka['tenggat_diturunkan']);
        $this->assertSame(
            0,
            DB::table('N_PA_Assignment')
                ->where('PA_Participant_Id', $this->participantId)
                ->where('Flag_Aktif', 'Y')
                ->whereNull('Due_At')
                ->count(),
            'Masih ada penugasan tanpa tenggat sesudah pengisian dibuka.',
        );

        // ── 5. BERBAGAI CARA ORANG MENGISI ─────────────────────────────────────
        $mapper = app(PaPayloadMapperService::class);

        $this->kasusKirimanTakLengkap($mapper);
        $this->kasusMenungguVerifikasi($mapper);
        $this->kasusDitolak($mapper);
        $this->kasusLengkapLaluKirimUlang($mapper);

        /* PEMBATALAN DI TENGAH SIKLUS BERJALAN — tepat keadaan yang dulu mustahil.

           `batalkan()` dulu menumpang gerbang BUAT_PENUGASAN yang berhenti di PREPARED,
           sehingga peserta yang resign atau penilai yang pindah unit tidak punya jalan
           keluar selain SQL manual. Sejak ia punya operasi sendiri
           (BATALKAN_PENUGASAN), pembatalan boleh sampai OPEN — dan uji ini menempuhnya
           pada siklus yang benar-benar terbuka, bukan pada siklus yang belum dikunci. */
        $this->kasusPenugasanDibatalkan();

        // ── 5b. JENDELA DIPERPANJANG DI TENGAH SIKLUS ─────────────────────────
        $this->kasusJendelaDiperpanjang();

        // ── 6. HITUNG ──────────────────────────────────────────────────────────
        $hitung = app(PaResultCalculationService::class)->hitung($this->participantId, 1);

        /* SATU-SATUNYA kategori yang memenuhi ambang adalah SELF; ATASAN dibatalkan pada
           langkah sebelumnya. Bobotnya dibagi ulang dari 40 menjadi 100, jadi nilai
           kompetensi = nilai SELF apa adanya.

           Nilai SELF: dua butir, 4/5 dan 5/5, keduanya berbobot 1 -> (80 + 100) / 2 = 90. */
        $this->assertSame(90.0, (float) $hitung['nilai_kompetensi'],
            'Pembagian ulang bobot kategori tidak menghasilkan nilai SELF apa adanya.');

        /* TERTAHAN, dan itu BENAR: karyawan uji ini tidak punya KPI disetujui. Mesin
           menandai alih-alih mengarang nol — persis yang seharusnya terjadi. */
        $this->assertSame('TERTAHAN', $hitung['status']);
        $this->assertNotNull($hitung['alasan_tertahan']);
        $this->assertNull($hitung['final_score'],
            'Nilai akhir tidak boleh lahir dari satu pilar saja.');

        // ── 7. TUTUP PENGISIAN, KUNCI KURVA, LALU TERBIT ──────────────────────
        /* Kurva hanya boleh dikunci sesudah jendela ditutup (KUNCI_KURVA = CLOSED saja):
           ia satu-satunya tempat nilai seseorang bergantung pada orang lain. */
        $tutup = app(PaCyclePreparationService::class)->tutupPengisian($this->cycleId, 1);
        $this->assertSame('CLOSED', $tutup['status']);

        $this->lengkapiPilarKpi();

        /* Siklus yang dibuat lewat aplikasi bermetode KURVA (DDL-2026-120): grade resmi baru
           ada sesudah kurva dikunci, dan penerbitan menolak siklus yang belum dikunci.
           Satu peserta = tanpa sebaran, jadi ia mendapat grade yang rentang z-nya memuat 0. */
        $this->assertSame('KURVA', DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->value('Metode_Grade'));
        /* Rantai uji ini satu peserta per grup, jadi grupnya "datar" (tanpa sebaran). Sejak #706
           kurva datar hanya boleh dikunci dengan alasan tertulis — persis yang dituntut admin. */
        app(PaKurvaService::class)->kunci($this->cycleId, 1, 'Rantai uji: satu peserta per grup kurva.');

        $grade = DB::table('N_PA_Result')->where('PA_Participant_Id', $this->participantId)
            ->where('Flag_Aktif', 'Y')->first(['Kode_Grade_Sistem', 'PA_Cycle_Curve_Id']);
        $this->assertSame('B', $grade->Kode_Grade_Sistem);
        $this->assertNotNull($grade->PA_Cycle_Curve_Id, 'Grade kurva harus menunjuk kurva yang memberinya.');

        /* Riwayat angka (DDL-2026-168): tiap peristiwa meninggalkan potret, urut seperti
           terjadinya — hitung yang TERTAHAN, hitung ulang yang diantrekan penutupan pengisian
           (antrean sync di uji), dan kurva yang memberi B. lengkapiPilarKpi menimpa barisnya
           langsung di luar mesin, jadi tidak meninggalkan potret. */
        $riwayat = DB::table('N_PA_Result_History as h')
            ->join('N_PA_Result as r', 'r.Id_PA_Result', '=', 'h.PA_Result_Id')
            ->where('r.PA_Participant_Id', $this->participantId)
            ->orderBy('h.Id_PA_Result_History')
            ->get(['h.Jenis_Peristiwa', 'h.Status_Hasil', 'h.Kode_Grade_Sistem', 'h.Created_By']);
        $this->assertSame(['HITUNG', 'HITUNG_ULANG', 'KURVA_DIKUNCI'], $riwayat->pluck('Jenis_Peristiwa')->all());
        $this->assertSame('TERTAHAN', $riwayat[0]->Status_Hasil);
        $this->assertSame('B', $riwayat[2]->Kode_Grade_Sistem);
        $this->assertSame(1, (int) $riwayat[0]->Created_By);

        /* Peserta rantai ini tidak punya Atasan (0/0), jadi kategorinya GUGUR dan penerbitan
           menahannya sejak 13 Okt 2026 — penahanan itu dibuktikan dulu, lalu diterbitkan
           dengan sadar seperti yang akan dilakukan admin. */
        try {
            app(PaResultPublishService::class)->terbitkanPeserta($this->participantId, 1);
            $this->fail('Hasil berkategori gugur seharusnya ditahan.');
        } catch (PaKategoriGugurException) {
            $this->assertSame('DIHITUNG', $this->statusHasil());
        }

        $terbit = app(PaResultPublishService::class)->terbitkanPeserta($this->participantId, 1, false, true);

        $this->assertSame('TERBIT', $terbit['status'] ?? $this->statusHasil());
        $this->assertSame('TERBIT', $this->statusHasil());

        $hasil = DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $this->participantId)
            ->where('Flag_Aktif', 'Y')
            ->first(['Published_At', 'Published_By', 'Final_Score']);

        $this->assertNotNull($hasil->Published_At, 'Jejak penerbitan tidak tercatat.');
        $this->assertSame(1, (int) $hasil->Published_By);

        /* Menerbitkan ulang ditolak: angka yang sudah dilihat orang tidak diterbitkan
           dua kali dengan diam-diam. */
        $this->expectException(RuntimeException::class);
        app(PaResultPublishService::class)->terbitkanPeserta($this->participantId, 1);
    }

    /* ------------------------------------------------------------------ *
     * Cara-cara orang mengisi                                             *
     * ------------------------------------------------------------------ */

    /** Kiriman yang butirnya kurang: tersimpan, tetapi TIDAK menyelesaikan penugasan. */
    private function kasusKirimanTakLengkap(PaPayloadMapperService $mapper): void
    {
        $hasil = $mapper->terimaDanPetakan($this->tokenSelf, [
            'Id_Ujian_Nilai_Akhir' => 'SEBAGIAN-'.bin2hex(random_bytes(4)),
            'Detail' => [
                ['Kode_Soal' => 'Q1', 'Skor' => 4, 'Skor_Maksimal' => 5, 'Bobot_Penilaian' => 1],
            ],
        ], 1);

        $this->assertFalse($hasil['pemetaan']['kontrak_lengkap'],
            'Kiriman tanpa seluruh butir tidak boleh dianggap lengkap.');
        $this->assertSame(['Q2'], $hasil['pemetaan']['butir_hilang']);
        $this->assertNotSame('SELESAI', $this->statusPenugasan($this->assignmentSelf),
            'Penugasan ditandai selesai padahal butirnya belum utuh.');
    }

    /** Nilai yang masih menunggu verifikasi admin HC Learn. */
    private function kasusMenungguVerifikasi(PaPayloadMapperService $mapper): void
    {
        $mapper->catatStatusSkor($this->tokenSelf, PaPayloadMapperService::SKOR_MENUNGGU, 1);

        $this->assertSame(
            PaAssignmentBuilderService::STATUS_MENUNGGU_VERIFIKASI,
            $this->statusPenugasan($this->assignmentSelf),
        );

        /* Bukan SELESAI — nilainya belum sah, jadi tidak boleh ikut dihitung. Pengingat
           berhenti karena penyaringnya mengecualikan status ini; yang dijaga di sini
           adalah bahwa keadaannya memang keadaan KETIGA, bukan salah satu dari dua. */
        $this->assertNotSame('SELESAI', $this->statusPenugasan($this->assignmentSelf));
    }

    /** Verifikasi menolak: penilainya harus mengerjakan ulang. */
    private function kasusDitolak(PaPayloadMapperService $mapper): void
    {
        $mapper->catatStatusSkor($this->tokenSelf, PaPayloadMapperService::SKOR_DITOLAK, 1);

        $this->assertSame(
            PaAssignmentBuilderService::STATUS_BELUM_MULAI,
            $this->statusPenugasan($this->assignmentSelf),
            'Penolakan harus mengembalikan penugasan supaya pengingat berjalan lagi.',
        );
    }

    /** Kiriman utuh, lalu dikirim ulang dengan isi yang sama persis. */
    private function kasusLengkapLaluKirimUlang(PaPayloadMapperService $mapper): void
    {
        $payload = [
            'Id_Ujian_Nilai_Akhir' => 'LENGKAP-'.bin2hex(random_bytes(4)),
            'Detail' => [
                ['Kode_Soal' => 'Q1', 'Skor' => 4, 'Skor_Maksimal' => 5, 'Bobot_Penilaian' => 1],
                ['Kode_Soal' => 'Q2', 'Skor' => 5, 'Skor_Maksimal' => 5, 'Bobot_Penilaian' => 1],
            ],
        ];

        $pertama = $mapper->terimaDanPetakan($this->tokenSelf, $payload, 1);

        $this->assertTrue($pertama['baru']);
        $this->assertTrue($pertama['pemetaan']['kontrak_lengkap']);

        /* TERKUMPUL, bukan SELESAI. Pemetaan payload berhenti di sini sejak
           `PaRaterSubmitService`: jawabannya tersimpan utuh, tetapi pelepasannya menjadi
           SELESAI menunggu penilai mengirim SELURUH tugasnya. Tanpa jeda itu, satu lembar
           bisa menjadi lembar terakhir yang memicu hasil seorang peserta terbit di atas
           pekerjaan yang pengisinya masih berniat meninjau ulang. */
        $this->assertSame('TERKUMPUL', $this->statusPenugasan($this->assignmentSelf));

        /* PENILAI MENEKAN KIRIM — difixture, sama seperti pilar KPI di tahap penerbitan.
           Perhitungan hanya membaca penugasan SELESAI, dan yang diuji rantai ini adalah
           tahap-tahap SESUDAH pengisian; alur pengirimannya sendiri punya berkas ujinya. */
        DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $this->assignmentSelf)
            ->update(['Status_Penugasan' => 'SELESAI']);

        $sebelum = DB::table('N_PA_Assignment_Payload')->count();

        /* Kiriman ulang yang isinya persis sama BUKAN kesalahan — HC Learn mencoba ulang
           ketika jawabannya hilang di jaringan, dan datanya sudah sampai. Yang tidak boleh
           terjadi adalah baris kedua. */
        $kedua = $mapper->terimaDanPetakan($this->tokenSelf, $payload, 1);

        $this->assertFalse($kedua['baru']);
        $this->assertSame($pertama['payload_id'], $kedua['payload_id']);
        $this->assertSame($sebelum, DB::table('N_PA_Assignment_Payload')->count(),
            'Kiriman ulang yang identik membuat baris payload kedua.');
    }

    /**
     * Jendela pengisian digeser SESUDAH siklus dibuka.
     *
     * Dulu mustahil: `STATUS_BOLEH_UBAH_JENDELA` berhenti di LOCKED, dengan alasan yang
     * benar — tenggat sudah diturunkan ke penugasan dan tidak ikut berubah, sehingga
     * menggeser jendelanya membuat keduanya menunjuk tanggal berbeda.
     *
     * Alasan itu kini hilang karena keadaannya diperbaiki: `perbarui()` menurunkan ulang
     * tenggatnya. Yang dijaga di sini adalah keduanya — jendelanya bergeser, DAN
     * tenggat penugasan ikut bergeser bersamanya.
     */
    private function kasusJendelaDiperpanjang(): void
    {
        $sebelum = DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $this->assignmentSelf)
            ->value('Due_At');

        $hasil = app(PaCycleSetupService::class)->perbarui($this->cycleId, [
            'pengisian_sampai' => '2026-08-15',
        ], 1);

        $sesudah = DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $this->assignmentSelf)
            ->value('Due_At');

        /* Penugasan SELF sudah SELESAI pada langkah sebelumnya, jadi tenggatnya TIDAK
           boleh ikut bergeser: angka itu sudah menjadi bagian dari catatan apakah orang
           itu terlambat, dan menggesernya menulis ulang sejarah yang sudah terjadi. */
        $this->assertSame((string) $sebelum, (string) $sesudah,
            'Tenggat penugasan yang sudah SELESAI ikut ditimpa; catatan keterlambatannya berubah.');

        /* Yang belum selesai memang harus ikut. Di rantai ini ATASAN sudah dibatalkan,
           jadi yang tersisa nol — dan itu pun harus dilaporkan apa adanya, bukan
           dilaporkan sebagai jumlah seluruh penugasan. */
        $this->assertSame(0, (int) $hasil['tenggat_diperbarui'],
            'Penugasan yang selesai atau dibatalkan tidak boleh ikut terhitung diperbarui.');

        $this->assertSame(
            '2026-08-15',
            substr((string) DB::table('N_PA_Cycle')
                ->where('Id_PA_Cycle', $this->cycleId)
                ->value('Pengisian_Sampai'), 0, 10),
            'Jendela siklus tidak bergeser padahal statusnya mengizinkan.',
        );
    }

    /** Penugasan yang dibatalkan tidak boleh menerima kiriman lagi. */
    private function kasusPenugasanDibatalkan(): void
    {
        app(PaAssignmentBuilderService::class)->batalkan(
            $this->assignmentAtasan,
            'Penilainya pindah unit di tengah siklus.',
            1,
        );

        $this->assertSame(
            PaAssignmentBuilderService::STATUS_DIBATALKAN,
            $this->statusPenugasan($this->assignmentAtasan),
        );
    }

    /* ------------------------------------------------------------------ *
     * Fixture                                                             *
     * ------------------------------------------------------------------ */

    private function siapkanSalinanDanPeserta(string $kode): void
    {
        $karyawan = DB::table('Karyawan')->where('Aktif', 'Y')
            ->whereNotNull('ID_Level_Jabatan')
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);

        /* SIKLUS BARU SUDAH MEMBAWA SALINAN ATURANNYA SENDIRI.
           |
           | `buat()` kini menyalin aturan penilai master saat siklus dibuat — pada data ini
           | tujuh header, masing-masing beserta detailnya. Fixture ini dulu menyisipkan
           | salinannya sendiri di atas salinan itu dan menabrak UX_PA_Cycle_Rater_Det_Snap_-
           | Aktif, dengan pesan "duplicate key" yang menuduh datanya padahal yang keliru
           | anggapan fixture-nya.
           |
           | Levelnya karena itu DIAMBIL dari salinan yang sudah ada, bukan dipilih sendiri
           | dari HRIS_Level: memilih level yang tidak punya salinan menghasilkan peserta
           | yang aturannya tak bisa diresolusi, dan gagalnya muncul jauh di tahap
           | perhitungan sebagai "tak satu pun kategori memenuhi ambang". */
        $header = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->orderBy('Id_PA_Cycle_Rater_Policy_Snapshot')
            ->first(['Id_PA_Cycle_Rater_Policy_Snapshot', 'ID_Level']);

        $this->assertNotNull($header, 'Siklus baru seharusnya sudah membawa salinan aturan penilai.');

        $this->levelId = (int) $header->ID_Level;

        /* Salinannya menunjuk aturan MASTER — kolomnya NOT NULL, dan memang begitu
           seharusnya: salinan yang tidak bisa menunjuk asalnya tak bisa dijelaskan
           setahun kemudian saat hasilnya disanggah. */
        DB::table('N_PA_Rater_Policy')->insert([
            'Kode_Policy' => $kode,
            'Nama_Policy' => 'Policy Rantai Penuh',
            'ID_Level' => $this->levelId,
            'Berlaku_Dari' => '2026-01-01',
        ]);
        $policyId = (int) DB::table('N_PA_Rater_Policy')
            ->where('Kode_Policy', $kode)->value('Id_PA_Rater_Policy');

        $detailId = [];

        foreach ([
            ['SELF', $this->selfCategoryId, 40, 1],
            ['ATASAN', $this->atasanCategoryId, 60, 2],
        ] as [$kodeKat, $kategoriId, $bobot, $urutan]) {
            DB::table('N_PA_Rater_Policy_Detail')->insert([
                'PA_Rater_Policy_Id' => $policyId,
                'PA_Rater_Category_Id' => $kategoriId,
                'Bobot_Persen' => $bobot,
                'Min_Rater' => 1,
                'Min_Rater_Selesai' => 1,
                'Flag_Izinkan_Manual' => 'Y',
                'Urutan_Tampil' => $urutan,
            ]);
            $detailId[$kodeKat] = (int) DB::table('N_PA_Rater_Policy_Detail')
                ->where('PA_Rater_Policy_Id', $policyId)
                ->where('PA_Rater_Category_Id', $kategoriId)
                ->value('Id_PA_Rater_Policy_Detail');
        }

        $snapshotId = (int) $header->Id_PA_Cycle_Rater_Policy_Snapshot;

        /* Salinan itu diarahkan ke aturan master yang baru saja dibuat, lalu detailnya
           DIGANTI seluruhnya. Menambah di atas detail bawaan akan membuat bobot kategori
           berjumlah lebih dari 100 — dan yang diuji berkas ini justru pembagian ulang
           bobot, yang angkanya jadi tak bermakna. */
        DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('Id_PA_Cycle_Rater_Policy_Snapshot', $snapshotId)
            ->update([
                'PA_Rater_Policy_Id' => $policyId,
                'Kode_Policy_Salinan' => $kode,
                'Nama_Policy_Salinan' => 'Policy Rantai Penuh',
                'Versi_Policy_Salinan' => 1,
            ]);

        DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')
            ->where('PA_Cycle_Rater_Policy_Snapshot_Id', $snapshotId)
            ->delete();

        /* DUA kategori dengan bobot berbeda. Yang diuji lewat susunan ini adalah
           PEMBAGIAN ULANG: ATASAN nanti dibatalkan, dan bobot SELF harus naik dari 40
           menjadi 100 — bukan nilai kompetensi yang tinggal 40% dari seharusnya. */
        foreach ([
            ['SELF', 'Diri Sendiri', $this->selfCategoryId, 40, 1],
            ['ATASAN', 'Atasan Langsung', $this->atasanCategoryId, 60, 2],
        ] as [$kodeKat, $nama, $kategoriId, $bobot, $urutan]) {
            DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')->insert([
                'PA_Cycle_Rater_Policy_Snapshot_Id' => $snapshotId,
                'PA_Rater_Policy_Detail_Id' => $detailId[$kodeKat],
                'PA_Rater_Category_Id' => $kategoriId,
                'Kode_Kategori_Salinan' => $kodeKat,
                'Nama_Kategori_Salinan' => $nama,
                'Bobot_Persen' => $bobot,
                'Min_Rater' => 1,
                'Min_Rater_Selesai' => 1,
                'Flag_Izinkan_Manual' => 'Y',
                'Urutan_Tampil' => $urutan,
            ]);
        }

        /* updateOrInsert, bukan insert: salinan bobot pilar juga sudah dibuat bersama
           siklusnya. Yang dituju fixture ini angkanya (60/40), bukan keberadaan barisnya. */
        DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')->updateOrInsert(
            ['PA_Cycle_Id' => $this->cycleId, 'ID_Level' => $this->levelId],
            ['Bobot_KPI' => 60, 'Bobot_Kompetensi' => 40],
        );

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $karyawan->Kode_Perusahaan,
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $karyawan->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $karyawan->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $this->levelId,
            'PA_Cycle_Rater_Policy_Snapshot_Id' => $snapshotId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        $this->participantId = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->value('Id_PA_Participant');

        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $this->participantId,
            'Status_Hasil' => 'BELUM_DIHITUNG',
            'Flag_Aktif' => 'Y',
        ]);
    }

    /** @param list<string> $soal */
    private function buatPaket(array $soal): int
    {
        DB::table('N_PA_Cycle_Question_Package')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'Id_Master_Ujian' => 'PKG-RANTAI-'.bin2hex(random_bytes(2)),
            'Nama_Ujian_Sumber' => 'Paket Rantai Penuh',
            'Sumber_Kontrak' => 'MANUAL',
            'Flag_Aktif' => 'Y',
        ]);

        $packageId = (int) DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->value('Id_PA_Cycle_Question_Package');

        /* Kompetensinya diambil dari yang WAJIB di level ini, dan BERBEDA tiap butir.
           |
           | Dua aturan bertemu di sini. UX_PA_CycleQItem_Kompetensi (DDL-2026-090) melarang
           | satu kompetensi dipegang dua butir dalam paket yang sama. Dan perhitungan hanya
           | menjumlahkan kompetensi yang WAJIB bagi level peserta — butir yang menunjuk
           | kompetensi di luar daftar itu tersimpan rapi lalu tidak ikut dihitung, sehingga
           | nilai kompetensinya pulang NOL tanpa satu pun galat. */
        /* SATU jenis kompetensi saja yang diwajibkan pada siklus ini.
           |
           | Siklus baru kini menyalin SELURUH syarat kompetensi level — pada level uji ini
           | 17 kompetensi di dua jenis. Penjaga cakupan lalu menahan pilar kompetensi di NOL
           | karena paket dua butir jelas tidak menutupi semuanya, dan uji ini merah karena
           | penjaga yang bukan urusannya.
           |
           | Cakupan punya berkas ujinya sendiri (ServiceFlowTest). Yang diuji DI SINI adalah
           | pembagian ulang bobot kategori, jadi syaratnya dipersempit ke satu jenis dan
           | kedua butirnya mengambil kompetensi dari jenis itu — sama seperti fixture ini
           | sudah mempersempit aturan penilainya menjadi SELF dan ATASAN saja. */
        $jenis = (int) DB::table('N_PA_Cycle_Competency_Requirement_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('ID_Level', $this->levelId)
            ->orderBy('PA_Competency_Type_Id')
            ->value('PA_Competency_Type_Id');

        $this->assertNotSame(0, $jenis, 'Siklus baru seharusnya membawa salinan syarat kompetensi.');

        DB::table('N_PA_Cycle_Competency_Requirement_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('ID_Level', $this->levelId)
            ->where('PA_Competency_Type_Id', '<>', $jenis)
            ->delete();

        /* Satu kompetensi BERBEDA per butir: UX_PA_CycleQItem_Kompetensi (DDL-2026-090)
           melarang satu kompetensi dipegang dua butir dalam paket yang sama. */
        $kompetensi = DB::table('N_PA_Competency')
            ->where('PA_Competency_Type_Id', $jenis)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Competency')
            ->limit(count($soal))
            ->pluck('Id_PA_Competency')
            ->map(fn ($x) => (int) $x)
            ->all();

        $this->assertCount(count($soal), $kompetensi,
            'Jenis kompetensi uji ini tidak punya cukup anggota untuk seluruh butir fixture.');

        foreach ($soal as $i => $kodeSoal) {
            DB::table('N_PA_Cycle_Question_Package_Item')->insert([
                'PA_Cycle_Question_Package_Id' => $packageId,
                'Id_Jenis_Indikator' => 'ALT-RANTAI',
                /* KODE DAN ID BAGIAN DIBEDAKAN. Fixture yang menaruh kode ke dalam
                   `Id_Jenis_Soal` akan tetap hijau pada pemeta yang mencocokkan
                   `Kode_Soal` callback dengan id bagian — pemeta yang di production
                   tidak memetakan satu butir pun (10 September 2026). */
                'Id_Jenis_Soal' => 'BAGIAN-'.$kodeSoal,
                'Kode_Soal_Sumber' => $kodeSoal,
                'Nama_Bagian_Sumber' => 'Soal '.$kodeSoal,
                'PA_Competency_Id' => $kompetensi[$i],
                'Skor_Maksimal' => 5,
                'Urutan_Tampil' => $i + 1,
            ]);
        }

        return $packageId;
    }

    private function buatPenugasan(): void
    {
        $peserta = DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $this->participantId)
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama_Karyawan_Saat_Cycle']);

        foreach ([
            [$this->selfCategoryId, $this->assignmentSelf ?? null],
            [$this->atasanCategoryId, null],
        ] as [$kategoriId]) {
            DB::table('N_PA_Assignment')->insert([
                'PA_Participant_Id' => $this->participantId,
                'Kode_Perusahaan_Penilai' => $peserta->Kode_Perusahaan,
                'Kode_Karyawan_Penilai' => $peserta->Kode_Karyawan,
                'Nama_Penilai_Saat_Cycle' => $peserta->Nama_Karyawan_Saat_Cycle,
                'PA_Rater_Category_Id' => $kategoriId,
                'Bobot_Dalam_Kategori' => 100,
                'Sumber_Penugasan' => 'MANUAL',
                'Alasan_Manual' => 'Fixture uji rantai penuh.',
                'Status_Penugasan' => 'BELUM_MULAI',
                'Flag_Aktif' => 'Y',
            ]);
        }

        $penugasan = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $this->participantId)
            ->orderBy('Id_PA_Assignment')
            ->get(['Id_PA_Assignment', 'PA_Rater_Category_Id']);

        foreach ($penugasan as $p) {
            if ((int) $p->PA_Rater_Category_Id === $this->selfCategoryId) {
                $this->assignmentSelf = (int) $p->Id_PA_Assignment;
            } else {
                $this->assignmentAtasan = (int) $p->Id_PA_Assignment;
            }
        }

        $this->tokenSelf = 'RANTAI-'.bin2hex(random_bytes(6));

        DB::table('N_PA_Assignment_Integration')->insert([
            'PA_Assignment_Id' => $this->assignmentSelf,
            'PA_Cycle_Question_Package_Id' => $this->packageId,
            'Id_Ujian_Token' => $this->tokenSelf,
            'Flag_Aktif' => 'Y',
        ]);
    }

    /**
     * Melengkapi pilar KPI supaya penerbitan bisa diuji.
     *
     * Nilainya DIISI LANGSUNG, bukan dihitung: ia datang dari modul KPI lewat rantai
     * persetujuannya sendiri, dan membangun fixture-nya di sini berarti menguji KPI.
     * Yang tetap dijalankan sungguhan adalah pencampuran kedua pilar dan penerbitannya.
     *
     * KPI 80 x 60% + Kompetensi 90 x 40% = 48 + 36 = 84.
     */
    private function lengkapiPilarKpi(): void
    {
        $grade = DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Score_Type', 'FINAL')
            ->where('Score_Usage', 'GRADE')
            ->value('Kode_Range');

        DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $this->participantId)
            ->where('Flag_Aktif', 'Y')
            ->update([
                'Status_Hasil' => 'DIHITUNG',
                'Nilai_KPI' => 80,
                'Final_Score' => 84,
                'Kode_Grade_Sistem' => $grade ?? 'B',
                'Alasan_Tertahan' => null,
                'Jumlah_KPI_Belum_Disetujui' => 0,
            ]);
    }

    /* ------------------------------------------------------------------ *
     * Pembacaan keadaan                                                   *
     * ------------------------------------------------------------------ */

    private function statusSiklus(): string
    {
        return (string) DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $this->cycleId)
            ->value('Status_Cycle');
    }

    private function statusPenugasan(int $assignmentId): string
    {
        return (string) DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $assignmentId)
            ->value('Status_Penugasan');
    }

    private function statusHasil(): string
    {
        return (string) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $this->participantId)
            ->where('Flag_Aktif', 'Y')
            ->value('Status_Hasil');
    }
}
