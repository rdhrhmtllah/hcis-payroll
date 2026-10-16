<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Jobs\KPI\PerformanceAppraisal\HitungHasilPesertaJob;
use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaNotificationService;
use App\Services\KPI\PerformanceAppraisal\PaPayloadMapperService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

class ServiceFlowTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $participantId;

    private int $levelId;

    private int $selfCategoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->selfCategoryId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', 'SELF')
            ->value('Id_PA_Rater_Category');
        $this->levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        $this->buatPesertaDenganAturanSelf();
    }

    public function test_assignment_self_dibentuk_dengan_bobot_seratus(): void
    {
        $service = $this->app->make(PaAssignmentBuilderService::class);
        $usulan = $service->usulkan($this->participantId);

        $this->assertNull($usulan['SELF']['calon'][0]['organization_relationship_id']);
        $this->assertFalse($usulan['SELF']['calon'][0]['sudah_ditugaskan']);

        $hasil = $service->tugaskan($this->participantId, [[
            'kode_kategori' => 'SELF',
            'kode_perusahaan' => $usulan['SELF']['calon'][0]['kode_perusahaan'],
            'kode_karyawan' => $usulan['SELF']['calon'][0]['kode_karyawan'],
            'sumber' => 'USULAN',
            'organization_relationship_id' => null,
        ]], 1);

        $this->assertSame(1, $hasil['dibuat']);

        $assignment = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $this->participantId)
            ->first();

        $this->assertSame('SELF', DB::table('N_PA_Rater_Category')
            ->where('Id_PA_Rater_Category', $assignment->PA_Rater_Category_Id)
            ->value('Kode_Kategori'));
        $this->assertSame(100.0, (float) $assignment->Bobot_Dalam_Kategori);
        $this->assertSame('BELUM_MULAI', $assignment->Status_Penugasan);
    }

    public function test_assignment_manual_tanpa_alasan_ditolak_sebelum_insert(): void
    {
        $service = $this->app->make(PaAssignmentBuilderService::class);
        $peserta = DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $this->participantId)
            ->first(['Kode_Perusahaan', 'Kode_Karyawan']);

        try {
            $service->tugaskan($this->participantId, [[
                'kode_kategori' => 'SELF',
                'kode_perusahaan' => $peserta->Kode_Perusahaan,
                'kode_karyawan' => $peserta->Kode_Karyawan,
                'sumber' => 'MANUAL',
                'alasan_manual' => '   ',
            ]], 1);
            $this->fail('Penugasan MANUAL tanpa alasan seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('wajib menyertakan alasan', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $this->participantId)
            ->count());
    }

    public function test_callback_token_historis_memakai_paket_soal_asalnya(): void
    {
        $assignmentId = $this->buatAssignmentSelf();
        $oldPackageId = $this->buatPaket('OLD', 'T', ['OLD-01']);
        $newPackageId = $this->buatPaket('NEW', 'Y', ['NEW-01']);

        $oldIntegrationId = $this->buatIntegrasi($assignmentId, $oldPackageId, 'TOKEN-OLD', 'T');
        $this->buatIntegrasi($assignmentId, $newPackageId, 'TOKEN-NEW', 'Y');

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'OPEN']);

        $hasil = $this->app->make(PaPayloadMapperService::class)->terimaDanPetakan(
            'TOKEN-OLD',
            $this->payload('OLD-01'),
            1,
        );

        $this->assertTrue($hasil['baru']);
        $this->assertTrue($hasil['pemetaan']['kontrak_lengkap']);
        $this->assertSame([], $hasil['pemetaan']['butir_hilang']);
        $this->assertSame($oldIntegrationId, (int) DB::table('N_PA_Assignment_Payload')
            ->where('Id_PA_Assignment_Payload', $hasil['payload_id'])
            ->value('PA_Assignment_Integration_Id'));
        $this->assertSame('OLD-01', DB::table('N_PA_Assignment_Score')
            ->where('PA_Assignment_Payload_Id', $hasil['payload_id'])
            ->value('Kode_Soal_Sumber'));

        /* TERKUMPUL, BUKAN SELESAI — dan itu memang yang benar sejak `PaRaterSubmitService`.
           |
           | Pemetaan payload berhenti di TERKUMPUL: jawabannya tersimpan utuh, tetapi
           | pelepasannya menjadi SELESAI menunggu penilai menekan kirim untuk SELURUH
           | tugasnya. Tanpa jeda itu, satu lembar bisa menjadi lembar terakhir yang memicu
           | hasil seorang peserta terbit — di atas pekerjaan yang pengisinya masih berniat
           | meninjau ulang.
           |
           | Uji ini sempat menegaskan SELESAI dan merah sejak perubahan itu mendarat.
           | Yang salah penegasannya, bukan kodenya. */
        $this->assertSame(
            PaAssignmentBuilderService::STATUS_TERKUMPUL,
            DB::table('N_PA_Assignment')
                ->where('Id_PA_Assignment', $assignmentId)
                ->value('Status_Penugasan'),
        );
    }

    public function test_payload_tidak_lengkap_tidak_menyelesaikan_assignment(): void
    {
        $assignmentId = $this->buatAssignmentSelf();
        $packageId = $this->buatPaket('INCOMPLETE', 'Y', ['Q-01', 'Q-02']);
        $this->buatIntegrasi($assignmentId, $packageId, 'TOKEN-INCOMPLETE', 'Y');

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'OPEN']);

        $hasil = $this->app->make(PaPayloadMapperService::class)->terimaDanPetakan(
            'TOKEN-INCOMPLETE',
            $this->payload('Q-01'),
            1,
        );

        $this->assertFalse($hasil['pemetaan']['kontrak_lengkap']);
        $this->assertSame(['Q-02'], $hasil['pemetaan']['butir_hilang']);
        $this->assertSame('BELUM_MULAI', DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $assignmentId)
            ->value('Status_Penugasan'));
    }

    public function test_perhitungan_pertama_memakai_versi_satu_dan_menahan_tanpa_kpi(): void
    {
        $tanpaAkun = DB::table('Karyawan as k')
            ->leftJoin('KPI_Users as u', function ($join) {
                $join->on('u.Kode_Users', '=', 'k.Kode_Karyawan')
                    ->where('u.Flag_Active', 'Y');
            })
            ->where('k.Aktif', 'Y')
            ->whereNull('u.Id_Users')
            ->first(['k.Kode_Perusahaan', 'k.Kode_Karyawan', 'k.Nama']);

        $this->assertNotNull($tanpaAkun, 'Fixture membutuhkan satu karyawan aktif tanpa akun KPI.');

        DB::table('N_PA_Participant')->where('Id_PA_Participant', $this->participantId)
            ->update([
                'Kode_Perusahaan' => $tanpaAkun->Kode_Perusahaan,
                'Kode_Karyawan' => $tanpaAkun->Kode_Karyawan,
                'Nama_Karyawan_Saat_Cycle' => $tanpaAkun->Nama,
            ]);
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update([
                'Status_Cycle' => 'CLOSED',
                'Periode_Dinilai_Dari' => '2026-01-01',
                'Periode_Dinilai_Sampai' => '2026-06-30',
            ]);
        DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'Bobot_KPI' => 50,
            'Bobot_Kompetensi' => 50,
        ]);
        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $this->participantId,
        ]);

        $hasil = $this->app->make(PaResultCalculationService::class)
            ->hitung($this->participantId, 1);

        $this->assertSame('TERTAHAN', $hasil['status']);
        $this->assertSame(1, $hasil['versi']);
        // Sengaja TANPA kata "aktif": pemetaan kode karyawan -> akun KPI tidak lagi
        // menyaring Flag_Active (lihat komentar di PaResultCalculationService). Frasa
        // ini tetap membedakan sebab "tak punya akun" dari empat sebab tertahan
        // lainnya ("menunjuk N akun KPI", "Tidak ada KPI disetujui", "berbobot nol",
        // dan ambang jumlah pengisi).
        $this->assertStringContainsString('tidak punya akun KPI', $hasil['alasan_tertahan']);
        $this->assertSame(1, (int) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $this->participantId)
            ->value('Calculation_Version'));
    }

    /*
     | TERBIT DI TENGAH PERHITUNGAN tidak boleh ditimpa (14 Okt 2026).
     |
     | Penjaga TERBIT dibaca sebelum masukan dihitung; admin yang menekan Terbitkan di antara
     | keduanya dulu membuat hasil yang baru terbit kembali menjadi DIHITUNG. Keadaan itu
     | ditiru dengan menerbitkan barisnya tepat saat transaksi perhitungan dimulai.
     */
    public function test_hasil_yang_terbit_selagi_dihitung_tidak_ditimpa(): void
    {
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'CLOSED', 'Periode_Dinilai_Dari' => '2026-01-01', 'Periode_Dinilai_Sampai' => '2026-06-30']);
        DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId, 'ID_Level' => $this->levelId, 'Bobot_KPI' => 50, 'Bobot_Kompetensi' => 50,
        ]);
        DB::table('N_PA_Result')->insert(['PA_Participant_Id' => $this->participantId]);

        /* Diterbitkan TEPAT SESUDAH hitung() membaca hasil lamanya (penjaga awal lolos) dan
           SEBELUM transaksinya dimulai — urutan balapan yang sesungguhnya. */
        $sekali = false;
        DB::listen(function ($q) use (&$sekali) {
            if ($sekali || ! str_contains($q->sql, '[N_PA_Participant] as [p] left join [N_PA_Result] as [r]')) {
                return;
            }
            $sekali = true;
            DB::table('N_PA_Result')->where('PA_Participant_Id', $this->participantId)
                ->update(['Status_Hasil' => 'TERBIT', 'Published_At' => now()]);
        });

        try {
            $this->app->make(PaResultCalculationService::class)->hitung($this->participantId, 1);
            $this->fail('Perhitungan atas hasil yang baru terbit harus ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('baru saja TERBIT', $e->getMessage());
        }

        $this->assertSame('TERBIT', DB::table('N_PA_Result')->where('PA_Participant_Id', $this->participantId)->value('Status_Hasil'));
    }

    public function test_jenis_kompetensi_wajib_yang_tak_tercakup_menahan_hasil(): void
    {
        /* Aturan #12 dari `docs/architecture/pa-aturan-yang-tidak-dijaga-database.md`.
           Sampai 1 September 2026 ia hanya hidup sebagai prosa: `N_PA_Competency_Requirement`
           berisi 33 baris, salinannya dibekukan per siklus, `competencyRequirements()`
           sudah ada — dan tak satu pun kode produksi memanggilnya. Seluruh anggota badan
           itu dibangun untuk keputusan yang tidak pernah diambil.

           Akibatnya seorang manajer bisa dinilai tanpa satu butir pun Manajerial, mendapat
           grade yang tampak utuh, dan tak ada apa pun di layar yang mengatakan sepertiga
           penilaiannya tidak pernah terjadi. */
        $assignmentId = $this->buatAssignmentSelf();

        $kompetensi = DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Competency')->first(['Id_PA_Competency', 'PA_Competency_Type_Id']);

        $this->assertNotNull($kompetensi, 'Master kompetensi kosong.');

        // Jenis LAIN yang diwajibkan tetapi tidak akan tercakup satu butir pun.
        $jenisLain = DB::table('N_PA_Competency_Type')
            ->where('Id_PA_Competency_Type', '<>', $kompetensi->PA_Competency_Type_Id)
            ->first(['Id_PA_Competency_Type', 'Kode_Type', 'Nama_Type']);

        $this->assertNotNull($jenisLain, 'Uji ini butuh dua jenis kompetensi.');

        DB::table('N_PA_Cycle_Competency_Requirement_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'PA_Competency_Type_Id' => $jenisLain->Id_PA_Competency_Type,
            'Kode_Type_Salinan' => $jenisLain->Kode_Type,
            'Nama_Type_Salinan' => $jenisLain->Nama_Type,
            'Flag_Wajib' => 'Y',
        ]);

        // Paket soal hanya memuat butir jenis PERTAMA — jenis wajib kedua tak tersentuh.
        $paket = $this->buatPaket('CAKUP', 'Y', ['CVR-01']);
        DB::table('N_PA_Cycle_Question_Package_Item')
            ->where('PA_Cycle_Question_Package_Id', $paket)
            ->update(['PA_Competency_Id' => $kompetensi->Id_PA_Competency]);

        $this->buatIntegrasi($assignmentId, $paket, 'TOKEN-CAKUP', 'Y');

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'OPEN']);

        $this->app->make(PaPayloadMapperService::class)
            ->terimaDanPetakan('TOKEN-CAKUP', $this->payload('CVR-01'), 1);

        /* PENILAI MENEKAN KIRIM — difixture, sama seperti pilar KPI di uji lain.
           |
           | Sejak `PaRaterSubmitService`, pemetaan payload berhenti di TERKUMPUL dan
           | pelepasan ke SELESAI menunggu penilai mengirim SELURUH tugasnya. Perhitungan
           | hanya membaca penugasan SELESAI, jadi tanpa langkah ini penjaga yang benar-benar
           | diuji di sini — cakupan jenis kompetensi — tidak pernah tersentuh: yang menahan
           | hasilnya justru "tak satu pun kategori penilai memenuhi ambang", dan ujinya
           | merah karena sebab yang sama sekali lain.
           |
           | Ditulis langsung, bukan lewat `kirimSemua()`, supaya uji ini tetap menguji
           | penjaga cakupan dan bukan alur pengiriman — yang punya berkas ujinya sendiri. */
        DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $assignmentId)
            ->update(['Status_Penugasan' => PaPayloadMapperService::STATUS_SELESAI]);

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'CLOSED']);
        $this->app->make(PaCycleGate::class)->lupakanMemo();

        /* Tiga penyiapan yang tanpanya perhitungan mati SEBELUM sampai ke penjaga yang
           diuji — dan tiap galatnya menyamar sebagai penjaga yang bekerja, padahal
           penjaganya belum pernah tersentuh sama sekali.

           Pesertanya DIPAKU ke karyawan tanpa akun KPI. Bukan untuk memperpendek jalan,
           melainkan supaya sebab pilar KPI pasti ada — dengan begitu uji ini benar-benar
           menghadapkan DUA sebab sekaligus, yang justru merupakan keadaan tempat penjaga
           kompetensi dulu tak pernah terlihat. Tanpa pemakuan ini, karyawan pertama yang
           kebetulan terpilih bisa punya KPI utuh dan uji penggabungannya jadi bergantung
           pada isi basis data.

           Rentang periode ikut disetel karena `setUp()` tidak mengisinya; tahunnya harus
           2026 mengikuti `Tahun` siklus, sebab menyilangkan keduanya memicu penjaga A09
           dan menahan hasil karena sebab yang sama sekali lain. */
        $tanpaAkunKpi = DB::table('Karyawan as k')
            ->leftJoin('KPI_Users as u', function ($join) {
                $join->on('u.Kode_Users', '=', 'k.Kode_Karyawan')
                    ->where('u.Flag_Active', 'Y');
            })
            ->where('k.Aktif', 'Y')
            ->whereNull('u.Id_Users')
            ->first(['k.Kode_Perusahaan', 'k.Kode_Karyawan', 'k.Nama']);

        $this->assertNotNull($tanpaAkunKpi, 'Fixture membutuhkan satu karyawan aktif tanpa akun KPI.');

        DB::table('N_PA_Participant')->where('Id_PA_Participant', $this->participantId)
            ->update([
                'Kode_Perusahaan' => $tanpaAkunKpi->Kode_Perusahaan,
                'Kode_Karyawan' => $tanpaAkunKpi->Kode_Karyawan,
                'Nama_Karyawan_Saat_Cycle' => $tanpaAkunKpi->Nama,
            ]);
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->update([
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-12-31',
        ]);
        DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'Bobot_KPI' => 50,
            'Bobot_Kompetensi' => 50,
        ]);
        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $this->participantId,
        ]);

        $hasil = $this->app->make(PaResultCalculationService::class)->hitung($this->participantId, 1);

        $this->assertSame('TERTAHAN', $hasil['status']);

        /* Peserta ini juga tidak punya akun KPI, jadi ADA DUA sebab sekaligus. Sampai
           1 September 2026 keduanya dipilih dengan `??` dan sebab KPI menang, sehingga
           penjaga kompetensi tidak akan pernah terlihat di sini betapapun benar kodenya.
           Menegaskan kedua sebab sekaligus mengunci perbaikan itu: kalau suatu saat
           salah satunya kembali menutupi yang lain, uji ini merah. */
        $this->assertStringContainsString('tidak punya akun KPI', (string) $hasil['alasan_tertahan']);
        $this->assertStringContainsString(
            $jenisLain->Nama_Type,
            (string) $hasil['alasan_tertahan'],
            'Sebabnya harus MENYEBUT jenis mana yang tidak tercakup; "tertahan" saja '
            .'memaksa admin menebak apa yang kurang.'
        );
        /* Sebabnya menyebut KEDUA kemungkinan, bukan menuduh satu. Yang diukur penjaga
           adalah "tidak ada kompetensi jenis ini yang TERNILAI"; itu bisa berarti paketnya
           memang tidak memuatnya, bisa juga butirnya ada tetapi tak ada yang menjawab.
           Menuduh paket soal padahal yang kurang adalah jawaban mengirim admin memperbaiki
           hal yang tidak rusak. */
        $this->assertStringContainsString('tidak memuat satu butir pun', (string) $hasil['alasan_tertahan']);
        $this->assertStringContainsString('tak seorang penilai pun menjawabnya', (string) $hasil['alasan_tertahan']);
    }

    private function buatPesertaDenganAturanSelf(): void
    {
        $kode = 'UJI-SVC-'.bin2hex(random_bytes(4));

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Service PA',
            'Tahun' => 2026,
            'Status_Cycle' => 'PREPARED',
        ]);
        $this->cycleId = (int) DB::table('N_PA_Cycle')
            ->where('Kode_Cycle', $kode)
            ->value('Id_PA_Cycle');

        DB::table('N_PA_Rater_Policy')->insert([
            'Kode_Policy' => $kode,
            'Nama_Policy' => 'Policy Uji Service PA',
            'ID_Level' => $this->levelId,
            'Berlaku_Dari' => '2026-01-01',
        ]);
        $policyId = (int) DB::table('N_PA_Rater_Policy')
            ->where('Kode_Policy', $kode)
            ->value('Id_PA_Rater_Policy');

        DB::table('N_PA_Rater_Policy_Detail')->insert([
            'PA_Rater_Policy_Id' => $policyId,
            'PA_Rater_Category_Id' => $this->selfCategoryId,
            'Bobot_Persen' => 100,
            'Min_Rater' => 1,
            'Min_Rater_Selesai' => 1,
            'Flag_Izinkan_Manual' => 'Y',
            'Urutan_Tampil' => 1,
        ]);
        $detailId = (int) DB::table('N_PA_Rater_Policy_Detail')
            ->where('PA_Rater_Policy_Id', $policyId)
            ->value('Id_PA_Rater_Policy_Detail');

        DB::table('N_PA_Cycle_Rater_Policy_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'PA_Rater_Policy_Id' => $policyId,
            'Kode_Policy_Salinan' => $kode,
            'Nama_Policy_Salinan' => 'Policy Uji Service PA',
            'Versi_Policy_Salinan' => 1,
        ]);
        $snapshotId = (int) DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->value('Id_PA_Cycle_Rater_Policy_Snapshot');

        DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')->insert([
            'PA_Cycle_Rater_Policy_Snapshot_Id' => $snapshotId,
            'PA_Rater_Policy_Detail_Id' => $detailId,
            'PA_Rater_Category_Id' => $this->selfCategoryId,
            'Kode_Kategori_Salinan' => 'SELF',
            'Nama_Kategori_Salinan' => 'Diri Sendiri',
            'Bobot_Persen' => 100,
            'Min_Rater' => 1,
            'Min_Rater_Selesai' => 1,
            'Flag_Izinkan_Manual' => 'Y',
            'Urutan_Tampil' => 1,
        ]);

        $karyawan = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);

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
    }

    public function test_kiriman_untuk_penugasan_yang_dibatalkan_ditolak(): void
    {
        /* REGRESI atas cacat yang ditemukan uji rantai penuh 2 September 2026.
           `terima()` mencari integrasi HANYA lewat token, tanpa sekali pun melihat
           penugasannya — dan `tandaiPenugasanSelesai()` hanya berhenti bila statusnya
           sudah SELESAI, sehingga DIBATALKAN lolos lalu DITIMPA jadi SELESAI. Hasilnya
           baris yang berbunyi "dibatalkan" dan "selesai" sekaligus, lengkap dengan
           Cancel_Reason di samping Finished_At.

           Gerbang siklus tidak bisa menangkapnya: siklusnya memang sah menerima payload;
           yang tidak sah adalah PENUGASANNYA. */
        $assignmentId = $this->buatAssignmentSelf();
        $packageId = $this->buatPaket('CANCEL', 'Y', ['Q-01']);
        $this->buatIntegrasi($assignmentId, $packageId, 'TOKEN-CANCEL', 'Y');

        $this->app->make(PaAssignmentBuilderService::class)
            ->batalkan($assignmentId, 'Peserta dikecualikan dari siklus.', 1);

        // Prasyarat: pembatalannya harus benar-benar terjadi, kalau tidak penolakan di
        // bawah bisa datang dari sebab yang sama sekali lain.
        $this->assertSame('DIBATALKAN', DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $assignmentId)->value('Status_Penugasan'));

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'OPEN']);
        $this->app->make(PaCycleGate::class)->lupakanMemo();

        try {
            $this->app->make(PaPayloadMapperService::class)
                ->terimaDanPetakan('TOKEN-CANCEL', $this->payload('Q-01'), 1);
            $this->fail('Kiriman untuk penugasan yang dibatalkan seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sudah dibatalkan', $e->getMessage());
            // Sebabnya ikut disebut: penilai memegang tautan yang dikirimkan kepadanya
            // dan tidak tahu apa-apa, jadi penolakan tanpa keterangan terbaca sebagai
            // sistem yang rusak.
            $this->assertStringContainsString('dikecualikan', $e->getMessage());
        }

        /* Yang paling penting: statusnya TIDAK BOLEH bergeser. Cacat aslinya bukan
           "diterima" melainkan "diterima lalu menimpa DIBATALKAN menjadi SELESAI". */
        $sesudah = DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $assignmentId)
            ->first(['Status_Penugasan', 'Finished_At']);

        $this->assertSame('DIBATALKAN', $sesudah->Status_Penugasan);
        $this->assertNull($sesudah->Finished_At);
    }

    public function test_penilai_yang_dicabut_ditandai_dan_tidak_diusulkan_lagi(): void
    {
        /* Defect UAT 8 Okt: sesudah dicabut, calon pertama yang belum terpasang untuk menutup
           minimum justru orang yang baru dicabut — centangnya muncul lagi seketika. */
        $assignmentId = $this->buatAssignmentSelf();

        $this->app->make(PaAssignmentBuilderService::class)
            ->batalkan($assignmentId, 'Salah pilih penilai.', 1);

        $calon = $this->app->make(PaAssignmentBuilderService::class)
            ->usulkan($this->participantId)['SELF']['calon'][0];

        $this->assertFalse($calon['sudah_ditugaskan']);
        $this->assertTrue($calon['pernah_dicabut']);
    }

    public function test_pembatalan_memberi_tahu_penilainya(): void
    {
        /* Terukur pada uji rantai penuh: 16 pemberitahuan lahir saat penugasan dibentuk,
           NOL saat enam di antaranya dibatalkan. Penilai tetap memegang undangannya dan
           tetap mengerjakannya. Ini pasangan wajib dari penjaga di atas — penjaga tanpa
           pemberitahuan hanya menolak pekerjaan orang tanpa pernah memberitahunya. */
        $assignmentId = $this->buatAssignmentSelf();

        $penilai = (string) DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $assignmentId)->value('Kode_Karyawan_Penilai');

        $sebelum = DB::table('N_HRIS_Notification')
            ->where('Kode_Karyawan', $penilai)
            ->where('Type', PaNotificationService::TYPE_DIBATALKAN)
            ->count();

        $this->app->make(PaAssignmentBuilderService::class)
            ->batalkan($assignmentId, 'Paket soalnya diganti.', 1);

        $sesudah = DB::table('N_HRIS_Notification')
            ->where('Kode_Karyawan', $penilai)
            ->where('Type', PaNotificationService::TYPE_DIBATALKAN)
            ->orderByDesc('Id_Notification')
            ->first(['Title', 'Message', 'source', 'Module']);

        $this->assertNotNull($sesudah, 'Pembatalan harus melahirkan pemberitahuan.');
        $this->assertGreaterThan($sebelum, DB::table('N_HRIS_Notification')
            ->where('Kode_Karyawan', $penilai)
            ->where('Type', PaNotificationService::TYPE_DIBATALKAN)->count());

        // Sebabnya ikut, dan dua jebakan modul lain tetap dihindari: topbar menyaring
        // `Source`, dan modul yang tak dikenal diam-diam dijadikan 'HCIS'.
        $this->assertStringContainsString('Paket soalnya diganti.', (string) $sesudah->Message);
        $this->assertSame(PaNotificationService::SUMBER, $sesudah->source);
        $this->assertSame(PaNotificationService::MODUL, $sesudah->Module);
    }

    /*
     | PEMBATALAN DI DALAM TRANSAKSI LUAR: PEMBERITAHUAN MENUNGGU COMMIT TERLUAR.
     |
     | Revisi peserta dan ubah status membatalkan beberapa penugasan dalam SATU transaksi luar.
     | Pemberitahuan yang ditulis selagi transaksi itu masih memegang kunci penugasan adalah
     | persis keadaan yang pernah menghabiskan kolam koneksi. Dan bila transaksi luarnya batal,
     | pembatalan tidak pernah terjadi — penilai tidak boleh dikabari sesuatu yang tidak ada.
     */
    public function test_pembatalan_di_transaksi_luar_memberi_tahu_sesudah_commit(): void
    {
        $assignmentId = $this->buatAssignmentSelf();
        $penilai = (string) DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $assignmentId)->value('Kode_Karyawan_Penilai');
        $hitung = fn () => DB::table('N_HRIS_Notification')->where('Kode_Karyawan', $penilai)
            ->where('Type', PaNotificationService::TYPE_DIBATALKAN)->count();
        $sebelum = $hitung();

        DB::transaction(function () use ($assignmentId, $hitung, $sebelum) {
            $this->app->make(PaAssignmentBuilderService::class)->batalkan($assignmentId, 'Revisi peserta.', 1);
            $this->assertSame($sebelum, $hitung(), 'Pemberitahuan tidak boleh ditulis selagi transaksi luar masih terbuka.');
        });

        $this->assertSame($sebelum + 1, $hitung(), 'Sesudah transaksi luar commit, penilainya dikabari.');
    }

    public function test_pembatalan_yang_ikut_dibatalkan_transaksi_luar_tidak_memberi_tahu(): void
    {
        $assignmentId = $this->buatAssignmentSelf();
        $penilai = (string) DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $assignmentId)->value('Kode_Karyawan_Penilai');
        $hitung = fn () => DB::table('N_HRIS_Notification')->where('Kode_Karyawan', $penilai)
            ->where('Type', PaNotificationService::TYPE_DIBATALKAN)->count();
        $sebelum = $hitung();

        try {
            DB::transaction(function () use ($assignmentId) {
                $this->app->make(PaAssignmentBuilderService::class)->batalkan($assignmentId, 'Revisi peserta.', 1);
                throw new \RuntimeException('revisi gagal di langkah berikutnya');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame($sebelum, $hitung(), 'Pembatalan yang tidak jadi tidak boleh dikabarkan.');
        $this->assertNull(DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $assignmentId)->value('Cancelled_At'));
    }

    /*
     | PEMBATALAN JUGA MELENGKAPKAN, DAN KARENA ITU JUGA MEMICU PERHITUNGAN.
     |
     | Aturannya satu kalimat: begitu tidak ada lagi penilai yang ditunggu, hasilnya dihitung.
     | Sampai 7 September 2026 aturan itu hanya berlaku pada satu jalan — kiriman penilai
     | terakhir. Padahal ada jalan kedua yang menghasilkan keadaan yang sama persis: penilai
     | terakhir yang ditunggu justru DIBATALKAN. `sisaPenugasan()` memang tidak menghitung
     | yang dibatalkan, jadi orangnya lengkap sejak detik itu — tetapi tak ada yang
     | mengantrekan perhitungannya, dan ia tidak muncul di keranjang mana pun sebagai
     | pekerjaan baru.
     */
    public function test_membatalkan_penilai_terakhir_mengantrekan_perhitungan(): void
    {
        Bus::fake();

        $assignmentId = $this->buatAssignmentSelf();

        $this->app->make(PaAssignmentBuilderService::class)
            ->batalkan($assignmentId, 'Salah pilih penilai.', 1);

        $this->assertSame(
            0,
            $this->app->make(PaResultCalculationService::class)->sisaPenugasan($this->participantId),
            'Peserta seharusnya tidak lagi menunggu penilai mana pun.'
        );

        Bus::assertDispatched(
            HitungHasilPesertaJob::class,
            fn ($job) => true,
        );
    }

    /* Selama masih ada penilai yang ditunggu, pembatalan TIDAK boleh memicu apa pun:
       menghitung peserta yang belum lengkap menghasilkan angka yang berubah begitu sisanya
       masuk, dan angka yang berubah sendiri adalah yang paling sulit dipercaya. */
    public function test_pembatalan_tidak_memicu_perhitungan_bila_masih_ada_yang_ditunggu(): void
    {
        Bus::fake();

        $pertama = $this->buatAssignmentSelf();

        /* Satu penilai lagi dibuat supaya masih ada yang ditunggu sesudah yang pertama
           dibatalkan. Tanpa ini ujinya hijau karena alasan yang salah. */
        $kedua = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $this->participantId)
            ->where('Id_PA_Assignment', '<>', $pertama)
            ->where('Flag_Aktif', 'Y')
            ->whereNotIn('Status_Penugasan', ['SELESAI', 'DIBATALKAN'])
            ->count();

        if ($kedua === 0) {
            /* Karyawannya DICARI, bukan dikarang: `FK_PA_Assignment_Penilai` menunjuk tabel
               `Karyawan`, dan kode karangan ditolak basis data. */
            $penilaiLain = DB::table('Karyawan')->where('Aktif', 'Y')
                ->whereNotIn('Kode_Karyawan', [
                    (string) DB::table('N_PA_Assignment')
                        ->where('Id_PA_Assignment', $pertama)->value('Kode_Karyawan_Penilai'),
                ])
                ->orderBy('Kode_Karyawan')
                ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);

            $this->assertNotNull($penilaiLain, 'Butuh satu karyawan aktif lain untuk uji ini.');

            DB::table('N_PA_Assignment')->insert([
                'PA_Participant_Id' => $this->participantId,
                'Kode_Perusahaan_Penilai' => $penilaiLain->Kode_Perusahaan,
                'Kode_Karyawan_Penilai' => $penilaiLain->Kode_Karyawan,
                'Nama_Penilai_Saat_Cycle' => $penilaiLain->Nama,
                'PA_Rater_Category_Id' => (int) DB::table('N_PA_Assignment')
                    ->where('Id_PA_Assignment', $pertama)->value('PA_Rater_Category_Id'),
                'Bobot_Dalam_Kategori' => 100,
                'Status_Penugasan' => 'BELUM_MULAI',
                'Flag_Aktif' => 'Y',
            ]);
        }

        $this->app->make(PaAssignmentBuilderService::class)
            ->batalkan($pertama, 'Salah pilih penilai.', 1);

        Bus::assertNotDispatched(HitungHasilPesertaJob::class);
    }

    private function buatAssignmentSelf(): int
    {
        $service = $this->app->make(PaAssignmentBuilderService::class);
        $calon = $service->usulkan($this->participantId)['SELF']['calon'][0];

        $service->tugaskan($this->participantId, [[
            'kode_kategori' => 'SELF',
            'kode_perusahaan' => $calon['kode_perusahaan'],
            'kode_karyawan' => $calon['kode_karyawan'],
            'sumber' => 'USULAN',
            'organization_relationship_id' => null,
        ]], 1);

        return (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $this->participantId)
            ->value('Id_PA_Assignment');
    }

    /** @param list<string> $questions */
    private function buatPaket(string $suffix, string $active, array $questions): int
    {
        DB::table('N_PA_Cycle_Question_Package')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'Id_Master_Ujian' => 'PKG-'.$suffix.'-'.bin2hex(random_bytes(2)),
            'Nama_Ujian_Sumber' => 'Paket '.$suffix,
            'Sumber_Kontrak' => 'MANUAL',
            'Flag_Aktif' => $active,
        ]);
        $packageId = (int) DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Nama_Ujian_Sumber', 'Paket '.$suffix)
            ->value('Id_PA_Cycle_Question_Package');

        /* Kompetensi diambil BERBEDA untuk tiap butir.
           |
           | Sejak DDL-2026-090, UX_PA_CycleQItem_Kompetensi melarang satu kompetensi
           | dipegang dua butir dalam paket yang sama — satu bagian tepat satu kompetensi.
           | Fixture yang memberi kompetensi yang sama ke semua butir akan ditolak indeks
           | itu, dan penolakannya terbaca seperti cacat kode padahal cacat fixture. */
        $kompetensi = DB::table('N_PA_Competency')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Competency')
            ->limit(count($questions))
            ->pluck('Id_PA_Competency')
            ->all();

        $this->assertGreaterThanOrEqual(
            count($questions),
            count($kompetensi),
            'Kompetensi aktif kurang dari cacah butir yang diminta fixture.',
        );

        foreach ($questions as $index => $code) {
            /* Satu tabel, satu sisipan. Fixture yang menyisipkan ke DUA tabel
               pemetaan membuat uji ini hijau sementara jalur sungguhan putus —
               dan itulah yang terjadi sampai DDL-2026-083 r2. */
            DB::table('N_PA_Cycle_Question_Package_Item')->insert([
                'PA_Cycle_Question_Package_Id' => $packageId,
                'Id_Jenis_Indikator' => 'ALT-'.$suffix,
                /* KODE DAN ID BAGIAN DIBEDAKAN. Fixture yang menaruh kode ke dalam
                   `Id_Jenis_Soal` akan tetap hijau pada pemeta yang mencocokkan
                   `Kode_Soal` callback dengan id bagian — pemeta yang di production
                   tidak memetakan satu butir pun (10 September 2026). */
                'Id_Jenis_Soal' => 'BAGIAN-'.$code,
                'Kode_Soal_Sumber' => $code,
                'Nama_Bagian_Sumber' => 'Soal '.$code,
                'PA_Competency_Id' => $kompetensi[$index],
                'Skor_Maksimal' => 5,
                'Urutan_Tampil' => $index + 1,
            ]);
        }

        return $packageId;
    }

    private function buatIntegrasi(
        int $assignmentId,
        int $packageId,
        string $token,
        string $active,
    ): int {
        DB::table('N_PA_Assignment_Integration')->insert([
            'PA_Assignment_Id' => $assignmentId,
            'PA_Cycle_Question_Package_Id' => $packageId,
            'Id_Ujian_Token' => $token,
            'Flag_Aktif' => $active,
        ]);

        return (int) DB::table('N_PA_Assignment_Integration')
            ->where('PA_Assignment_Id', $assignmentId)
            ->where('PA_Cycle_Question_Package_Id', $packageId)
            ->value('Id_PA_Assignment_Integration');
    }

    /** @return array<string, mixed> */
    private function payload(string $question): array
    {
        return [
            'Id_Ujian_Nilai_Akhir' => 'RESULT-'.bin2hex(random_bytes(5)),
            'Api_Version' => 'v2',
            'Detail' => [[
                'Kode_Soal' => $question,
                'Jenis_Soal' => 'SKALA',
                'Skor' => 4,
                'Skor_Maksimal' => 5,
                'Bobot_Penilaian' => 100,
            ]],
        ];
    }
}
