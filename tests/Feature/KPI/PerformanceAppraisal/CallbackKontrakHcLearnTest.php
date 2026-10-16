<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaKirimanTinjauService;
use App\Services\KPI\PerformanceAppraisal\PaNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Callback HC Learn → HCIS, diuji dengan PAYLOAD NYATA milik mereka.
 *
 * Bahannya `docs/KPI/performance-appraisal/contoh-callback-hclearn.json`, disalin apa
 * adanya dari dokumentasi HC Learn. Menyusun payload sendiri di dalam uji akan menguji
 * bentuk yang KITA bayangkan, dan bentuk yang kita bayangkan sudah pernah salah: sampai
 * hari ini penerima kita menuntut `Id_Ujian_Token`, sementara kontrak final mereka
 * menyatakan id numerik SENGAJA tidak pernah dikirim.
 *
 * ── Yang dijaga ───────────────────────────────────────────────────────────────
 *
 * 1. `Api_Reference` diterima sebagai identitas penilaian. Ini yang memutus rantai hari
 *    ini: setiap kiriman sungguhan dijawab 422 karena field yang kita tuntut tidak ada.
 * 2. Yang disimpan bukan cuma JSON mentah. Interpretasi, warna, keterangan, dan cacah
 *    soal per butir ikut mendarat di kolomnya — itulah yang dibaca layar per kompetensi.
 * 3. Idempotensi: kiriman ulang sesudah timeout dijawab `baru=false` dengan `payload_id`
 *    yang SAMA, tanpa baris kedua.
 */
class CallbackKontrakHcLearnTest extends TestCase
{
    use DatabaseTransactions;

    private const ALAMAT_RELATIF = 'api/v1/pa/third-party/penilaian/complete';

    private string $apiPublic;

    private string $apiSecret;

    /** @var array<string, mixed> */
    private array $contoh;

    private int $assignmentId;

    private int $integrationId;

    private int $paketId;

    private int $cycleId;

    private string $apiRef;

    private string $kodePenilai;

    protected function setUp(): void
    {
        parent::setUp();

        $berkas = base_path('docs/KPI/performance-appraisal/contoh-callback-hclearn.json');
        $this->assertFileExists($berkas, 'Snapshot kontrak callback HC Learn hilang.');

        $isi = json_decode((string) file_get_contents($berkas), true);
        $this->assertIsArray($isi, 'Snapshot kontrak callback bukan JSON yang bisa dibaca.');
        $this->contoh = $isi;

        $this->siapkanPenugasanBerpintu();
    }

    /**
     * Perubahan yang membuat uji ini merah: kembalikan penerima agar menuntut
     * `Id_Ujian_Token`, atau cari integrasinya lewat kolom selain `Api_Ref`.
     */
    public function test_kiriman_final_diterima_lewat_api_reference(): void
    {
        $response = $this->kirim($this->payloadFinal());

        $this->assertSame(200, $response->status(), (string) $response->json('message'));

        // Kiriman lengkap tidak menegur admin.
        $this->assertFalse(DB::table('N_HRIS_Notification')
            ->where('Type', PaNotificationService::TYPE_KIRIMAN_TERTAHAN)
            ->where('Reference_Id', (string) $this->cycleId)->exists());

        $response->assertJsonPath('result.assignment_id', $this->assignmentId)
            ->assertJsonPath('result.baru', true)
            ->assertJsonPath('result.butir', 2)
            ->assertJsonPath('result.berskor', 2);

        /* Bukti mentahnya tersimpan utuh — itu yang membuat sengketa nilai bisa ditelusuri
           setahun kemudian tanpa bergantung pada HC Learn. */
        $payload = DB::table('N_PA_Assignment_Payload')
            ->where('PA_Assignment_Integration_Id', $this->integrationId)
            ->first();

        $this->assertNotNull($payload);
        $this->assertSame($this->apiRef, $payload->Api_Reference);
        $this->assertSame('FINAL', $payload->Status_Skor);
        $this->assertSame('PA360', $payload->Kode_Akses);
        $this->assertSame('Employee Assessment 360 - STAFF', $payload->Nama_Tes);
        $this->assertSame('Tidak Lulus', $payload->Status_Kelulusan);
        $this->assertNotNull($payload->Waktu_Selesai_Akses, 'Waktu pengerjaan sebenarnya tidak tersimpan.');

        $mentah = json_decode((string) $payload->Payload_Json, true);
        $this->assertSame($this->apiRef, $mentah['Api_Reference'] ?? null);
    }

    /**
     * Perubahan yang membuat uji ini merah: berhenti menyimpan kolom rincian dan andalkan
     * Payload_Json saja. Layar per kompetensi kemudian hanya punya angka mentah.
     */
    public function test_rincian_per_butir_tersimpan_beserta_interpretasinya(): void
    {
        $this->kirim($this->payloadFinal())->assertOk();

        /* KOLOM DISEBUT SATU PER SATU, dan itu bukan kerapian.
           |
           | `Warna` ada di KEDUA tabel — warna kelulusan di header, warna interpretasi per
           | butir — dan `get()` tanpa daftar kolom membuat yang belakangan menimpa yang
           | duluan tanpa satu pun peringatan. Uji ini sempat merah karena itu, dan layar
           | per kompetensi akan kena persis hal yang sama bila ia menjoin keduanya. */
        $baris = DB::table('N_PA_Assignment_Score as s')
            ->join('N_PA_Assignment_Payload as p', 'p.Id_PA_Assignment_Payload', '=', 's.PA_Assignment_Payload_Id')
            ->where('p.PA_Assignment_Integration_Id', $this->integrationId)
            ->where('s.Flag_Aktif', 'Y')
            ->orderBy('s.Urutan_Tampil')
            ->get([
                's.Kode_Soal_Sumber', 's.Kode_Alat_Tes', 's.Nama_Alat_Tes',
                's.Status_Interpretation', 's.Warna', 's.Keterangan',
                's.Jumlah_Soal', 's.Jumlah_Terjawab', 's.Skor', 's.Skor_Maksimal',
            ])
            ->keyBy('Kode_Soal_Sumber');

        $this->assertCount(2, $baris);

        $be = $baris['BE'];
        $this->assertSame('PA360', $be->Kode_Alat_Tes);
        $this->assertSame('Employee Assessment 360 - STAFF', $be->Nama_Alat_Tes);
        $this->assertSame('Low', $be->Status_Interpretation);
        $this->assertSame('#d01616', $be->Warna);
        $this->assertSame('Sikap terhadap etika bisnis', $be->Keterangan);
        $this->assertSame(20, (int) $be->Jumlah_Soal);
        $this->assertSame(20, (int) $be->Jumlah_Terjawab);

        /* Interpretasi yang BERBEDA antar butir. Menyimpan satu nilai untuk semua baris
           lolos dari penegasan mana pun yang hanya melihat satu butir. */
        $this->assertSame('High', $baris['KWN']->Status_Interpretation);
        $this->assertSame('#21b080', $baris['KWN']->Warna);
    }

    /**
     * BUTIR DIPETAKAN LEWAT KODE YANG MEREKA KEMBALIKAN, bukan id bagian.
     *
     * Pada 10 September 2026 penilaian pertama yang benar-benar diisi orang masuk lengkap:
     * 13 butir, tanda tangan sah, tersimpan utuh, dijawab 200 — dan NOL terpetakan. Pemeta
     * mencocokkan `Detail[].Kode_Soal` ("K01") dengan `Id_Jenis_Soal` ("100158"), dua hal
     * yang tidak akan pernah sama. `kontrak_lengkap` false, penugasannya tidak pernah
     * ditandai selesai, dan tidak ada satu pun pesan galat di layar mana pun: penilai sudah
     * mengerjakan, HCIS tetap berkata "belum mulai".
     *
     * CARA MEMBUATNYA MERAH: kembalikan kunci `petaSoal()`/`butirDiharapkan()` ke
     * `Id_Jenis_Soal`.
     */
    public function test_butir_dipetakan_lewat_kode_soal_bukan_id_bagian(): void
    {
        $r = $this->kirim($this->payloadFinal());

        $r->assertOk();

        /* Kedua angka disebut: terpetakan SEMUA, dan tidak ada yang tersisa. Menegaskan
           salah satunya saja lolos pada pemeta yang memetakan separuh. */
        $r->assertJsonPath('result.berskor', 2)
            ->assertJsonPath('result.tak_terpetakan', [])
            ->assertJsonPath('result.butir_hilang', [])
            ->assertJsonPath('result.kontrak_lengkap', true);

        /* Kompetensinya BENAR-BENAR terisi. `kontrak_lengkap` bisa saja benar sementara
           kompetensinya kosong bila keduanya dibaca dari sumber yang berbeda. */
        $tanpaKompetensi = DB::table('N_PA_Assignment_Score as s')
            ->join('N_PA_Assignment_Payload as p', 'p.Id_PA_Assignment_Payload', '=', 's.PA_Assignment_Payload_Id')
            ->where('p.PA_Assignment_Integration_Id', $this->integrationId)
            ->where('s.Flag_Aktif', 'Y')
            ->whereNull('s.PA_Competency_Id')
            ->count();

        $this->assertSame(0, $tanpaKompetensi, 'Ada butir yang tersimpan tanpa kompetensi.');

        /* Dan penugasannya BERPINDAH. Inilah yang dilihat admin, dan yang kemarin tidak
           pernah terjadi. */
        $status = DB::table('N_PA_Assignment_Integration as i')
            ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'i.PA_Assignment_Id')
            ->where('i.Id_PA_Assignment_Integration', $this->integrationId)
            ->value('a.Status_Penugasan');

        $this->assertNotSame('BELUM_MULAI', $status, 'Penugasan tidak berpindah walau kiriman lengkap.');
    }

    /**
     * BUTIR TANPA KODE TIDAK DIAM-DIAM DIANGGAP COCOK.
     *
     * Paket lama bisa saja punya butir yang kodenya tidak diketahui — masternya sendiri
     * tidak menyimpannya. Butir seperti itu harus TERLIHAT sebagai butir yang hilang, bukan
     * ditampung satu kunci NULL yang menelan butir kedua tanpa kode dan menimpanya.
     */
    public function test_butir_paket_tanpa_kode_dilaporkan_hilang(): void
    {
        DB::table('N_PA_Cycle_Question_Package_Item')
            ->where('PA_Cycle_Question_Package_Id', $this->paketId)
            ->where('Kode_Soal_Sumber', 'KWN')
            ->update(['Kode_Soal_Sumber' => null]);

        $r = $this->kirim($this->payloadFinal());

        $r->assertOk()
            ->assertJsonPath('result.kontrak_lengkap', false);

        /* KWN yang dikirim HC Learn kini tak dikenali, dan butir paketnya ikut hilang —
           keduanya dilaporkan, tidak satu pun ditelan diam-diam. */
        $this->assertContains('KWN', $r->json('result.tak_terpetakan'));
    }

    /**
     * SIMULATOR HC LEARN WAJIB MEMAKAI KUNCI YANG SAMA DENGAN HC LEARN.
     *
     * Ini penjaga atas FIXTURE-nya, bukan atas pemetanya. `PaSimulasiHcLearnCommand` adalah
     * satu-satunya cara separuh hilir modul ini pernah dijalani sebelum ada kiriman
     * sungguhan — dan sampai 11 September 2026 ia menyusun `Kode_Soal` dari `Id_Jenis_Soal`,
     * kolom yang PERSIS SAMA dengan yang dikunci pemeta waktu itu. Dua-duanya salah dengan
     * cara yang sama, jadi simulasinya hijau sempurna: butir terpetakan, kontrak lengkap,
     * penugasan selesai. Yang tidak pernah tersentuh justru satu-satunya bentuk yang dipakai
     * di lapangan, dan cacatnya baru terlihat ketika penilaian pertama yang benar-benar diisi
     * orang mendarat dengan nol butir terpetakan — tanpa satu pun pesan galat.
     *
     * Pemetanya sudah dijaga `test_butir_dipetakan_lewat_kode_soal_bukan_id_bagian`. Yang
     * belum ada penjaganya adalah sisi pengirim tiruannya, dan uji perilaku tidak bisa
     * menangkapnya: fixture yang salah bersama kodenya selalu lulus.
     *
     * CARA MEMBUATNYA MERAH: kembalikan `'Kode_Soal' => $b->Id_Jenis_Soal` di
     * `PaSimulasiHcLearnCommand::detailUntuk()`.
     */
    public function test_simulator_hclearn_menyusun_kode_soal_dari_kode_sumber(): void
    {
        $berkas = app_path('Console/Commands/KPI/PerformanceAppraisal/PaSimulasiHcLearnCommand.php');
        $this->assertFileExists($berkas, 'Simulator HC Learn hilang; penjaga ini kehilangan sasarannya.');

        $isi = (string) file_get_contents($berkas);

        /* Yang diperiksa adalah PEMBENTUKAN `Kode_Soal`, bukan disebut-tidaknya
           `Id_Jenis_Soal` — kolom itu tetap sah dipakai untuk hal lain. */
        $this->assertMatchesRegularExpression(
            "/'Kode_Soal'\s*=>\s*\\\$kodeSoal/",
            $isi,
            "Simulator HC Learn tidak menyusun 'Kode_Soal' dari kode soal sumber.",
        );

        $this->assertDoesNotMatchRegularExpression(
            "/'Kode_Soal'\s*=>\s*\\\$b->Id_Jenis_Soal/",
            $isi,
            "Simulator HC Learn mengirim Id_Jenis_Soal sebagai 'Kode_Soal'. HC Learn mengirim "
            .'kode yang mereka berikan saat paket diikat (K01..), dan pemeta mengunci '
            .'Kode_Soal_Sumber — fixture ini akan hijau atas bentuk yang tidak pernah ada.',
        );

        $this->assertStringContainsString(
            'Kode_Soal_Sumber',
            $isi,
            'Simulator HC Learn tidak membaca Kode_Soal_Sumber dari butir paket siklus.',
        );
    }

    /**
     * KIRIMAN YANG MENGGANTUNG PUNYA TEMPATNYA, dan tempat itu bisa menyelesaikannya.
     *
     * Sampai 10 September 2026 keadaan "masuk tetapi tidak terpetakan" hanya dikirim balik
     * ke HC Learn di badan respons callback. Admin HCIS tidak pernah melihatnya: penilaian
     * yang sudah diisi orang menggantung tanpa satu pun tanda, dan yang memberi tahu
     * akhirnya orangnya sendiri.
     *
     * Uji ini menempuh perjalanan penuhnya: ikatan salah -> kiriman masuk -> muncul di
     * daftar tinjau dengan sebabnya -> ikatannya dibetulkan -> dipetakan ulang -> daftarnya
     * bersih dan penugasannya berpindah.
     *
     * CARA MEMBUATNYA MERAH: buang `PaKirimanTinjauService::daftar()`, atau saring kiriman
     * yang tak terpetakan keluar dari daftarnya.
     */
    public function test_kiriman_menggantung_muncul_di_daftar_tinjau_dan_bisa_dipetakan_ulang(): void
    {
        /* Ikatannya DIRUSAK lebih dulu: kode paketnya tidak lagi sama dengan yang dikirim
           HC Learn — persis bentuk kegagalan yang sebenarnya. */
        DB::table('N_PA_Cycle_Question_Package_Item')
            ->where('PA_Cycle_Question_Package_Id', $this->paketId)
            ->update(['Kode_Soal_Sumber' => DB::raw("'X-' + Kode_Soal_Sumber")]);

        // Admin pemegang siklus: dialah yang harus diberi tahu kirimannya tertahan.
        $admin = DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('UserID_Web')
            ->orderBy('Kode_Karyawan')->first(['Kode_Karyawan', 'UserID_Web']);
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Created_By' => (int) $admin->UserID_Web]);

        $this->kirim($this->payloadFinal())->assertOk();

        $notif = DB::table('N_HRIS_Notification')
            ->where('Type', PaNotificationService::TYPE_KIRIMAN_TERTAHAN)
            ->where('Reference_Id', (string) $this->cycleId)
            ->first(['Kode_Karyawan', 'Action_Url']);
        $this->assertNotNull($notif, 'Admin siklus tidak diberi tahu ada kiriman tertahan.');
        $this->assertSame($admin->Kode_Karyawan, $notif->Kode_Karyawan);
        $this->assertSame(PaNotificationService::TUJUAN_PEMANTAUAN.'?cycle='.$this->cycleId.'&tahap=pengisian', $notif->Action_Url);

        $tinjau = app(PaKirimanTinjauService::class);
        $daftar = $tinjau->daftar($this->cycleId);

        $this->assertSame(1, $daftar['ringkasan']['perlu_ditinjau'], 'Kiriman yang menggantung tidak terdaftar.');

        $baris = $daftar['baris'][0];

        /* ORANGNYA DISEBUT, bukan hanya id-nya: yang dicari admin adalah nama. */
        $this->assertNotSame('', $baris['nama_penilai']);
        $this->assertNotSame('', $baris['nama_peserta']);
        $this->assertContains('BE', $baris['tak_terpetakan']);
        $this->assertContains('X-BE', $baris['butir_hilang']);
        $this->assertStringContainsString('ikatan paket soal', $baris['saran']);

        /* Admin membetulkan ikatannya. */
        DB::table('N_PA_Cycle_Question_Package_Item')
            ->where('PA_Cycle_Question_Package_Id', $this->paketId)
            ->update(['Kode_Soal_Sumber' => DB::raw('SUBSTRING(Kode_Soal_Sumber, 3, 60)')]);

        $hasil = $tinjau->petakanUlang($this->cycleId, $baris['payload_id'], 1);

        $this->assertTrue($hasil['kontrak_lengkap'], 'Pemetaan ulang tidak menutup kasusnya.');
        $this->assertSame([], $tinjau->daftar($this->cycleId)['baris'], 'Daftar tinjau tidak ikut bersih.');

        /* Waktu isi diambil dari HC Learn, bukan jam pemetaan ulang: kiriman yang
           dipetakan berjam-jam kemudian tidak boleh terbaca selesai pada jam itu. */
        $tugas = DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $this->assignmentId)
            ->first(['Status_Penugasan', 'Started_At', 'Finished_At']);
        $this->assertSame('TERKUMPUL', $tugas->Status_Penugasan);
        $this->assertSame('2026-09-10 09:12:44', substr((string) $tugas->Started_At, 0, 19));
        $this->assertSame('2026-09-10 09:41:03', substr((string) $tugas->Finished_At, 0, 19));
    }

    /**
     * Kiriman milik penugasan/peserta yang tidak lagi berlaku tidak bisa ditindaklanjuti,
     * jadi tidak masuk daftar tinjau. Siklus nonaktif #10244 dulu melaporkan 49 dari 98
     * kiriman "perlu ditinjau" padahal pesertanya sudah dinonaktifkan semua.
     */
    public function test_daftar_tinjau_mengabaikan_penugasan_dan_peserta_yang_tidak_berlaku(): void
    {
        DB::table('N_PA_Cycle_Question_Package_Item')
            ->where('PA_Cycle_Question_Package_Id', $this->paketId)
            ->update(['Kode_Soal_Sumber' => DB::raw("'X-' + Kode_Soal_Sumber")]);
        $this->kirim($this->payloadFinal())->assertOk();

        $tinjau = app(PaKirimanTinjauService::class);
        $this->assertSame(1, $tinjau->daftar($this->cycleId)['ringkasan']['perlu_ditinjau']);

        $pesertaId = (int) DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $this->assignmentId)->value('PA_Participant_Id');

        DB::table('N_PA_Participant')->where('Id_PA_Participant', $pesertaId)->update(['Flag_Aktif' => 'T']);
        $this->assertSame([], $tinjau->daftar($this->cycleId)['baris'], 'Peserta nonaktif masih terdaftar.');

        DB::table('N_PA_Participant')->where('Id_PA_Participant', $pesertaId)->update(['Flag_Aktif' => 'Y']);
        DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $this->assignmentId)->update(['Status_Penugasan' => 'DIBATALKAN']);
        $this->assertSame([], $tinjau->daftar($this->cycleId)['baris'], 'Penugasan dibatalkan masih terdaftar.');
    }

    /**
     * Kiriman tanpa waktu akses (tanggal rusak sudah ditolak pintu callback dengan 422)
     * jatuh ke jam pemetaan, dan mulai disamakan dengan selesai.
     */
    public function test_tanpa_waktu_akses_jatuh_ke_jam_pemetaan(): void
    {
        $muatan = $this->payloadFinal();
        unset($muatan['penilaian']['Waktu_Mulai_Akses'], $muatan['penilaian']['Waktu_Selesai_Akses']);

        $this->kirim($muatan)->assertOk();

        $tugas = DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $this->assignmentId)
            ->first(['Started_At', 'Finished_At']);
        $this->assertNotNull($tugas->Finished_At);
        $this->assertNotSame('2026-09-10 09:41:03', substr((string) $tugas->Finished_At, 0, 19));
        $this->assertSame(substr((string) $tugas->Finished_At, 0, 19), substr((string) $tugas->Started_At, 0, 19));
    }

    /**
     * KIRIMAN MILIK SIKLUS LAIN TIDAK BISA DISENTUH dari layar siklus ini.
     *
     * Pemetaan ulang menulis; tanpa pemeriksaan kepemilikan, satu id yang salah ketik
     * mengubah nilai orang di siklus yang tidak sedang dibuka.
     */
    public function test_petakan_ulang_menolak_kiriman_milik_siklus_lain(): void
    {
        $this->kirim($this->payloadFinal())->assertOk();

        $payloadId = (int) DB::table('N_PA_Assignment_Payload')
            ->where('PA_Assignment_Integration_Id', $this->integrationId)
            ->value('Id_PA_Assignment_Payload');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/bukan milik siklus/i');

        app(PaKirimanTinjauService::class)->petakanUlang($this->cycleId + 999_000, $payloadId, 1);
    }

    /**
     * Perubahan yang membuat uji ini merah: buat baris payload kedua untuk kiriman yang
     * isinya sama, atau jawab `baru=true` pada pengulangan.
     */
    public function test_kiriman_ulang_setelah_timeout_tidak_menggandakan_nilai(): void
    {
        $pertama = $this->kirim($this->payloadFinal());
        $pertama->assertOk()->assertJsonPath('result.baru', true);

        $kedua = $this->kirim($this->payloadFinal());
        $kedua->assertOk()->assertJsonPath('result.baru', false);

        $this->assertSame(
            $pertama->json('result.payload_id'),
            $kedua->json('result.payload_id'),
            'payload_id berbeda pada kiriman ulang; HC Learn tidak bisa mengenali kirimannya sudah masuk.'
        );

        $this->assertSame(
            1,
            DB::table('N_PA_Assignment_Payload')
                ->where('PA_Assignment_Integration_Id', $this->integrationId)
                ->count(),
            'Kiriman ulang membuat baris payload kedua; nilainya akan terhitung dua kali.'
        );
    }

    /**
     * Perubahan yang membuat uji ini merah: hitung MENUNGGU_VERIFIKASI sebagai nilai, atau
     * tolak kirimannya karena Detail kosong.
     */
    public function test_menunggu_verifikasi_dicatat_tanpa_menyimpan_nilai(): void
    {
        $muatan = $this->contoh['menunggu_verifikasi'];
        unset($muatan['_kapan']);
        $muatan['Api_Reference'] = $this->apiRef;
        $muatan['Id_Integrasi'] = $this->integrationId;
        $muatan['Kode_Karyawan'] = $this->kodePenilai;

        $response = $this->kirim($muatan);
        $this->assertSame(200, $response->status(), (string) $response->json('message'));

        $this->assertSame(
            0,
            DB::table('N_PA_Assignment_Payload')
                ->where('PA_Assignment_Integration_Id', $this->integrationId)
                ->count(),
            'Kiriman MENUNGGU_VERIFIKASI tersimpan sebagai payload; nilainya belum sah dan tidak boleh dihitung.'
        );

        $this->assertSame(
            'MENUNGGU_VERIFIKASI',
            DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $this->assignmentId)->value('Status_Penugasan'),
            'Penugasan tidak ditandai menunggu verifikasi; penilainya akan terus ditagih pengingat.'
        );
    }

    /**
     * `Id_Integrasi` ADALAH BARIS INTEGRASI — gerbang menolak yang lain.
     *
     * Ini penjaga yang menegaskan ARTINYA, bukan sekadar keberadaannya. Dokumentasi HC Learn
     * menyebut field ini "angka milik HCIS sendiri, dikembalikan apa adanya" — jadi yang
     * menentukan maknanya memang sisi kita, dan sisi kita memilih `Id_PA_Assignment_Integration`
     * karena satu penugasan boleh punya beberapa baris integrasi (paket soal diganti, pintu
     * dicetak ulang) sehingga id penugasan tidak pernah bisa menunjuk token yang mana.
     *
     * Id PENUGASAN dipakai sebagai lawan, dan itu bukan angka acak: sampai 10 September 2026
     * penjadwalan kita justru mengirim angka ITU, sementara gerbang ini menuntut id baris.
     * Dua sisi sistem yang sama memakai dua arti untuk satu nama field.
     *
     * Cara membuatnya merah: longgarkan pemeriksaan Id_Integrasi di `PaApiGuard`, atau
     * bandingkan dengan `PA_Assignment_Id`.
     */
    public function test_id_integrasi_milik_penugasan_ditolak_gerbang(): void
    {
        $muatan = $this->payloadFinal();
        $muatan['Id_Integrasi'] = $this->assignmentId;

        $this->assertNotSame(
            $this->assignmentId,
            $this->integrationId,
            'Id penugasan dan id baris integrasi kebetulan sama; penjaganya tidak membuktikan apa pun.'
        );

        $this->kirim($muatan)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Id_Integrasi tidak cocok dengan token yang dikirim');

        $this->assertSame(
            0,
            DB::table('N_PA_Assignment_Payload')
                ->where('PA_Assignment_Integration_Id', $this->integrationId)
                ->count(),
            'Kiriman ber-Id_Integrasi salah tetap tersimpan; identitas yang tidak cocok '
            .'berarti kita tidak tahu nilai itu milik penilaian yang mana.'
        );
    }

    /**
     * Kontrak 26 September 2026: bagian esai membawa `Esai[]` per soal, berskor mesin, dan
     * `Jawaban_Teks` bagiannya null. Perubahan yang membuat uji ini merah: berhenti menyimpan
     * anak esai, atau menyimpan skor mesin bagian esai (keputusan E1: skor esai diabaikan).
     */
    public function test_esai_per_soal_tersimpan_berurutan_dan_skor_bagian_esai_diabaikan(): void
    {
        $this->tambahButirEsaiUra();

        $response = $this->kirim($this->payloadBeresai());

        $this->assertSame(200, $response->status(), (string) $response->json('message'));
        $response->assertJsonPath('result.kontrak_lengkap', true);

        $skor = DB::table('N_PA_Assignment_Score as s')
            ->join('N_PA_Assignment_Payload as p', 'p.Id_PA_Assignment_Payload', '=', 's.PA_Assignment_Payload_Id')
            ->where('p.PA_Assignment_Integration_Id', $this->integrationId)
            ->where('s.Flag_Aktif', 'Y')
            ->get(['s.Id_PA_Assignment_Score', 's.Kode_Soal_Sumber', 's.Skor', 's.Skor_Maksimal'])
            ->keyBy('Kode_Soal_Sumber');

        $this->assertEquals(21, (float) $skor['BE']->Skor, 'Bagian bukan esai tetap berskor.');
        $this->assertNull($skor['URA']->Skor, 'Skor mesin bagian esai tidak boleh ikut dihitung.');
        $this->assertNull($skor['URA']->Skor_Maksimal);

        $esai = DB::table('N_PA_Assignment_Score_Esai')
            ->where('PA_Assignment_Score_Id', $skor['URA']->Id_PA_Assignment_Score)
            ->orderBy('Urutan_Tampil')
            ->get(['Id_Master_Soal', 'Urutan_Tampil', 'Pertanyaan', 'Jawaban_Teks', 'Waktu_Dijawab']);

        $this->assertSame([102633, 102634, 102635, 102636], $esai->pluck('Id_Master_Soal')->map(fn ($x) => (int) $x)->all());
        $this->assertSame([1, 2, 3, 4], $esai->pluck('Urutan_Tampil')->map(fn ($x) => (int) $x)->all());
        $this->assertSame('Ceritakan bagaimana Anda menyelesaikan pekerjaan yang menumpuk.', $esai[0]->Pertanyaan);
        $this->assertSame('Saya urutkan menurut tenggat, lalu kerjakan yang paling mendesak dulu.', $esai[0]->Jawaban_Teks);
        $this->assertSame('2026-09-24 14:05:11', substr((string) $esai[0]->Waktu_Dijawab, 0, 19));

        $this->assertSame(0, DB::table('N_PA_Assignment_Score_Esai')
            ->whereIn('PA_Assignment_Score_Id', [$skor['BE']->Id_PA_Assignment_Score, $skor['KWN']->Id_PA_Assignment_Score])
            ->count(), 'Bagian tanpa esai tidak boleh punya anak esai.');
    }

    public function test_jumlah_esai_yang_tidak_cocok_ditolak_tanpa_menyimpan_nilai(): void
    {
        $this->tambahButirEsaiUra();
        $muatan = $this->payloadBeresai();

        foreach ($muatan['Detail'] as $i => $d) {
            if ($d['Kode_Soal'] === 'URA') {
                $muatan['Detail'][$i]['Jumlah_Esai'] = 3;
            }
        }

        $response = $this->kirim($muatan);

        $this->assertSame(422, $response->status());
        $this->assertStringContainsString('URA', (string) $response->json('message'));
        $this->assertSame(0, DB::table('N_PA_Assignment_Score as s')
            ->join('N_PA_Assignment_Payload as p', 'p.Id_PA_Assignment_Payload', '=', 's.PA_Assignment_Payload_Id')
            ->where('p.PA_Assignment_Integration_Id', $this->integrationId)
            ->where('s.Flag_Aktif', 'Y')
            ->count());
    }

    /** @return array<string, mixed> */
    private function payloadBeresai(): array
    {
        $muatan = $this->contoh['final_beresai_per_soal'];
        unset($muatan['_kapan'], $muatan['_sumber']);

        $muatan['Api_Reference'] = $this->apiRef;
        $muatan['Id_Integrasi'] = $this->integrationId;
        $muatan['Kode_Karyawan'] = $this->kodePenilai;

        return $muatan;
    }

    /** Butir URA pada paket, supaya kiriman beresai terbaca lengkap. */
    private function tambahButirEsaiUra(): void
    {
        DB::table('N_PA_Cycle_Question_Package_Item')->insert([
            'PA_Cycle_Question_Package_Id' => $this->paketId,
            'Id_Jenis_Indikator' => '900910',
            'Id_Jenis_Soal' => '900922',
            'Kode_Soal_Sumber' => 'URA',
            'Nama_Alat_Tes' => 'Employee Assessment 360 - STAFF',
            'Nama_Bagian_Sumber' => 'Uraian Situasional',
            'PA_Competency_Id' => DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')
                ->where('Flag_Berskor', 'T')->orderBy('Id_PA_Competency')->value('Id_PA_Competency'),
            'Urutan_Tampil' => 3,
            'Flag_Aktif' => 'Y',
        ]);
    }

    /** @return array<string, mixed> */
    private function payloadFinal(): array
    {
        $muatan = $this->contoh['final_tanpa_esai'];
        unset($muatan['_kapan']);

        $muatan['Api_Reference'] = $this->apiRef;
        $muatan['Id_Integrasi'] = $this->integrationId;
        $muatan['Kode_Karyawan'] = $this->kodePenilai;

        return $muatan;
    }

    /**
     * Satu penugasan yang pintunya sudah terbit, beserta paket soal yang memuat kedua
     * butir contoh (BE dan KWN) supaya kontraknya bisa dinyatakan lengkap.
     */
    private function siapkanPenugasanBerpintu(): void
    {
        $stok = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(2)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();

        $this->assertCount(2, $stok, 'Uji ini butuh dua karyawan aktif.');
        $this->kodePenilai = (string) $stok[1]->Kode_Karyawan;

        $levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $kategoriId = (int) DB::table('N_PA_Rater_Category')->where('Kode_Kategori', 'ATASAN')->value('Id_PA_Rater_Category');
        $this->assertGreaterThan(0, $kategoriId, 'Kategori ATASAN tidak ada di master.');

        $kode = 'UJI-CB-'.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Callback',
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => 'OPEN',
            'Pengisian_Dari' => now()->subDay()->format('Y-m-d H:i:s'),
            'Pengisian_Sampai' => now()->addDays(20)->format('Y-m-d H:i:s'),
        ]);

        $this->cycleId = $cycleId = (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');

        DB::table('N_PA_Cycle_Question_Package')->insert([
            'PA_Cycle_Id' => $cycleId,
            'ID_Level' => $levelId,
            'PA_Rater_Category_Id' => $kategoriId,
            'Id_Master_Ujian' => '900900',
            'Nama_Ujian_Sumber' => 'Employee Assessment 360 - STAFF',
            'Sumber_Kontrak' => 'HCLEARN',
            'Flag_Aktif' => 'Y',
        ]);

        $this->paketId = $paketId = (int) DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $cycleId)
            ->value('Id_PA_Cycle_Question_Package');

        /* Dua butir dengan kode PERSIS seperti contoh HC Learn. Kalau kodenya berbeda,
           kirimannya akan dijawab kontrak_lengkap=false dan uji ini merah karena alasan
           yang tidak ada hubungannya dengan yang sedang diuji. */
        $kompetensi = DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Competency')->limit(2)->pluck('Id_PA_Competency')->all();
        $this->assertCount(2, $kompetensi, 'Master kompetensi kurang dari dua baris.');

        /* ID BAGIAN DAN KODE SOAL DIBEDAKAN, dan itu inti dari perbaikan 10 September 2026.
           |
           | Versi pertama fixture ini menaruh KODE ('BE', 'KWN') ke dalam `Id_Jenis_Soal`,
           | sehingga uji tetap hijau pada pemeta yang mencocokkan `Kode_Soal` dengan id
           | bagian — persis pemeta yang di production tidak memetakan satu butir pun.
           | Fixture yang dibentuk mengikuti kekeliruan kodenya berhenti menguji apa pun.
           |
           | Bentuk yang benar: `Id_Jenis_Soal` id bagian milik HC Learn (angka), dan
           | `Kode_Soal_Sumber` kode yang mereka kembalikan di callback. */
        foreach ([['BE', 'Business Ethic'], ['KWN', 'Kewarganegaraan']] as $i => [$kodeSoal, $jenis]) {
            DB::table('N_PA_Cycle_Question_Package_Item')->insert([
                'PA_Cycle_Question_Package_Id' => $paketId,
                'Id_Jenis_Indikator' => '900910',
                'Id_Jenis_Soal' => (string) (900920 + $i),
                'Kode_Soal_Sumber' => $kodeSoal,
                'Nama_Alat_Tes' => 'Employee Assessment 360 - STAFF',
                'Nama_Bagian_Sumber' => $jenis,
                'PA_Competency_Id' => $kompetensi[$i],
                'Urutan_Tampil' => $i + 1,
                'Flag_Aktif' => 'Y',
            ]);
        }

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $cycleId,
            'Kode_Perusahaan' => $stok[0]->Kode_Perusahaan,
            'Kode_Karyawan' => $stok[0]->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $stok[0]->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $stok[0]->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        $participantId = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->value('Id_PA_Participant');

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $stok[1]->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $stok[1]->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $stok[1]->Nama,
            'PA_Rater_Category_Id' => $kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'BELUM_MULAI',
        ]);

        $this->assignmentId = (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->value('Id_PA_Assignment');

        $this->apiRef = bin2hex(random_bytes(16));

        DB::table('N_PA_Assignment_Integration')->insert([
            'PA_Assignment_Id' => $this->assignmentId,
            'PA_Cycle_Question_Package_Id' => $paketId,
            'Api_Ref' => $this->apiRef,
            'Short_Token' => 'UJICB'.substr(bin2hex(random_bytes(2)), 0, 3),
            'Status_Integrasi' => 'BERHASIL',
            'Flag_Aktif' => 'Y',
        ]);

        $this->integrationId = (int) DB::table('N_PA_Assignment_Integration')
            ->where('PA_Assignment_Id', $this->assignmentId)
            ->value('Id_PA_Assignment_Integration');

        $this->siapkanAplikasiApi();
    }

    /**
     * Aplikasi API uji, supaya kirimannya melewati `PaApiGuard` SUNGGUHAN.
     *
     * Versi pertama berkas ini mem-bypass gerbangnya. Akibatnya `Id_Integrasi` tidak pernah
     * diperiksa di sini — padahal justru di sanalah cacat terbesarnya: penjadwalan mengirim
     * id penugasan sementara gerbang menuntut id baris integrasi, sehingga setiap callback
     * sungguhan dijawab 403 atas angka yang kita sendiri berikan. Uji yang melompati
     * gerbang tidak akan pernah melihatnya.
     */
    private function siapkanAplikasiApi(): void
    {
        $this->apiPublic = 'UJI-CB-'.substr(bin2hex(random_bytes(6)), 0, 10);
        $this->apiSecret = bin2hex(random_bytes(16));

        DB::table('N_LMS_Api_Aplikasi')->insert([
            'Kode_Aplikasi' => $this->apiPublic,
            'Nama_Aplikasi' => 'HC Learn (uji callback)',
            'Api_Public' => $this->apiPublic,
            'Api_Key' => bin2hex(random_bytes(8)),
            'Api_Secret' => $this->apiSecret,
            'Flag_Aktif' => 'Y',
        ]);

        $appId = (int) DB::table('N_LMS_Api_Aplikasi')
            ->where('Api_Public', $this->apiPublic)
            ->value('Id_Api_Aplikasi');

        $endpointId = DB::table('N_LMS_Endpoint_Api')
            ->where('Url_Route', '/'.self::ALAMAT_RELATIF)
            ->where('Method', 'POST')
            ->where('Flag_Aktif', 'Y')
            ->value('Id_Endpoint_Api');

        $this->assertNotNull(
            $endpointId,
            'Endpoint callback PA belum terdaftar di N_LMS_Endpoint_Api; gerbangnya akan '
            .'menolak 403 sebelum menyentuh isi kiriman.'
        );

        DB::table('N_LMS_Akses_Endpoint')->insert([
            'Id_Api_Aplikasi' => $appId,
            'Id_Endpoint_Api' => $endpointId,
            'Flag_Aktif' => 'Y',
        ]);
    }

    /**
     * Mengirim kiriman bertanda tangan, persis seperti HC Learn melakukannya.
     *
     * @param  array<string, mixed>  $muatan
     */
    private function kirim(array $muatan): \Illuminate\Testing\TestResponse
    {
        $badan = json_encode($muatan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $bodyHash = hash('sha256', $badan);
        $timestamp = date('Y-m-d H:i:s');
        $nonce = bin2hex(random_bytes(16));

        /* Susunan payload tanda tangannya WAJIB sama persis dengan yang disusun gerbang.
           Menyalinnya di sini memang duplikasi, dan duplikasi itulah yang membuat uji ini
           berguna: kalau susunannya bergeser di satu sisi saja, uji ini merah — bukan
           production yang diam-diam menolak seluruh kiriman HC Learn. */
        $tandaTangan = hash_hmac('sha512', implode("\n", [
            'POST',
            '/'.self::ALAMAT_RELATIF,
            '',
            $timestamp,
            $nonce,
            $bodyHash,
            $this->apiPublic,
        ]), $this->apiSecret);

        return $this->call(
            'POST',
            '/'.self::ALAMAT_RELATIF,
            [],
            [],
            [],
            $this->headerServer([
                'X-HC-Public' => $this->apiPublic,
                'X-HC-Signature' => $tandaTangan,
                'X-HC-Timestamp' => $timestamp,
                'X-HC-Nonce' => $nonce,
                'X-HC-Body-Hash' => $bodyHash,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
            $badan,
        );
    }

    /** @param array<string, string> $header @return array<string, string> */
    private function headerServer(array $header): array
    {
        $server = [];

        foreach ($header as $nama => $nilai) {
            $server['HTTP_'.str_replace('-', '_', strtoupper($nama))] = $nilai;
        }

        $server['CONTENT_TYPE'] = $header['Content-Type'];

        return $server;
    }
}
