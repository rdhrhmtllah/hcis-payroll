<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaParticipantOverrideService;
use App\Services\KPI\PerformanceAppraisal\PaParticipantRuleResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Jalur TULIS penyimpangan per peserta (K32).
 *
 * `ResolverPenggabunganTest` sudah menguji sisi BACA — bagaimana salinan siklus dan
 * penyimpangan digabung. Yang belum pernah diuji adalah sisi tulisnya, dan di situlah
 * satu-satunya aturan yang membuat seluruh rancangan ini masuk akal:
 *
 *   Bobot wajib berjumlah 100 SESUDAH digabung dengan aturan level, bukan sesudah
 *   dijumlahkan sendiri.
 *
 * Sebuah override yang tampak wajar sendirian bisa membuat gabungannya 105, karena
 * kategori yang tidak disimpangkan tetap membawa bobot aslinya. Itulah sebabnya
 * kirimannya harus berupa HIMPUNAN UTUH dan pemeriksaannya harus lewat resolver — bukan
 * penjumlahan atas apa yang kebetulan dikirim.
 */
class ParticipantOverrideTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $participantId;

    private int $levelId;

    /** @var array<string, int> */
    private array $kategori = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (DB::table('N_PA_Rater_Category')->get() as $r) {
            $this->kategori[$r->Kode_Kategori] = (int) $r->Id_PA_Rater_Category;
        }

        foreach (['SELF', 'ATASAN', 'BAWAHAN'] as $perlu) {
            $this->assertArrayHasKey($perlu, $this->kategori, "Master kategori {$perlu} tidak ada.");
        }

        $this->levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        $this->siapkanSiklus();
    }

    /* ------------------------------------------------------------------ *
     * Aturan yang membuat K32 ada                                         *
     * ------------------------------------------------------------------ */

    public function test_override_sebagian_yang_membuat_gabungan_bukan_seratus_ditolak(): void
    {
        /* Aturan levelnya SELF 20 / ATASAN 50 / BAWAHAN 30. Menaikkan ATASAN menjadi 55
           SENDIRIAN tampak wajar — angkanya masuk akal, bentuknya benar — tetapi
           gabungannya menjadi 105 karena dua kategori lain tetap membawa bobot aslinya.
           Penjumlahan atas apa yang dikirim tidak akan menangkap ini. */
        try {
            $this->service()->simpanOverridePenilai($this->participantId, [
                ['pa_rater_category_id' => $this->kategori['ATASAN'],
                    'bobot_persen' => 55, 'min_rater' => 1,
                    'alasan_perubahan' => 'atasan lebih berbobot untuk peran ini'],
            ], 1);
            $this->fail('Gabungan 105 seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('105', $e->getMessage());
        }

        // Tidak boleh menyisakan baris setengah jadi.
        $this->assertSame(0, DB::table('N_PA_Participant_Rater_Override')
            ->where('PA_Participant_Id', $this->participantId)->count());
    }

    public function test_himpunan_utuh_yang_berjumlah_seratus_diterima(): void
    {
        $hasil = $this->service()->simpanOverridePenilai($this->participantId, [
            ['pa_rater_category_id' => $this->kategori['SELF'],
                'bobot_persen' => 25, 'min_rater' => 1, 'alasan_perubahan' => 'penyesuaian'],
            ['pa_rater_category_id' => $this->kategori['ATASAN'],
                'bobot_persen' => 55, 'min_rater' => 1, 'alasan_perubahan' => 'penyesuaian'],
            ['pa_rater_category_id' => $this->kategori['BAWAHAN'],
                'bobot_persen' => 20, 'min_rater' => 1, 'alasan_perubahan' => 'penyesuaian'],
        ], 1);

        $this->assertSame(3, $hasil['tersimpan']);

        /* `bobot_efektif` adalah GABUNGAN yang sebenarnya berlaku, bukan gema dari yang
           dikirim. Keduanya berbeda ketika sebagian kategori tidak disimpangkan, dan
           layar yang menampilkan kiriman alih-alih gabungan akan berbohong justru pada
           peserta yang paling menyimpang. */
        $this->assertSame(100.0, round(array_sum($hasil['bobot_efektif']), 2));
        $this->assertSame(55.0, (float) $hasil['bobot_efektif']['ATASAN']);
    }

    public function test_bobot_efektif_memantulkan_gabungan_bukan_kiriman(): void
    {
        // Hanya DUA kategori yang digeser; BAWAHAN dibiarkan mengikuti aturan level (30).
        $hasil = $this->service()->simpanOverridePenilai($this->participantId, [
            ['pa_rater_category_id' => $this->kategori['SELF'],
                'bobot_persen' => 10, 'min_rater' => 1, 'alasan_perubahan' => 'penyesuaian'],
            ['pa_rater_category_id' => $this->kategori['ATASAN'],
                'bobot_persen' => 60, 'min_rater' => 1, 'alasan_perubahan' => 'penyesuaian'],
        ], 1);

        $this->assertSame(2, $hasil['tersimpan'], 'Hanya dua baris yang benar-benar disimpang.');

        // 10 + 60 dari override, 30 dari salinan level — gabungannya 100.
        $this->assertSame(30.0, (float) $hasil['bobot_efektif']['BAWAHAN']);
        $this->assertSame(100.0, round(array_sum($hasil['bobot_efektif']), 2));
    }

    public function test_mencabut_seluruh_override_mengembalikan_aturan_level(): void
    {
        $this->service()->simpanOverridePenilai($this->participantId, [
            ['pa_rater_category_id' => $this->kategori['SELF'],
                'bobot_persen' => 10, 'min_rater' => 1, 'alasan_perubahan' => 'penyesuaian'],
            ['pa_rater_category_id' => $this->kategori['ATASAN'],
                'bobot_persen' => 60, 'min_rater' => 1, 'alasan_perubahan' => 'penyesuaian'],
        ], 1);

        $this->service()->cabutSeluruhOverride($this->participantId, 1);

        $resolver = $this->app->make(PaParticipantRuleResolver::class);
        $resolver->lupakanMemo();
        $efektif = $resolver->raterRules($this->participantId);

        // Kembali persis ke salinan level: 20 / 50 / 30.
        $this->assertSame(20.0, (float) $efektif['SELF']['bobot_persen']);
        $this->assertSame(50.0, (float) $efektif['ATASAN']['bobot_persen']);
        $this->assertSame(
            PaParticipantRuleResolver::SUMBER_SALINAN,
            $efektif['ATASAN']['sumber'],
            'Sesudah dicabut, sumbernya harus kembali SALINAN.'
        );
    }

    /* ------------------------------------------------------------------ *
     * Bobot pilar per peserta                                             *
     * ------------------------------------------------------------------ */

    public function test_override_pilar_wajib_berjumlah_seratus(): void
    {
        try {
            $this->service()->simpanOverridePilar($this->participantId, 70, 40, 'coba', 1);
            $this->fail('Bobot pilar 110 seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('110', $e->getMessage());
        }

        $this->assertSame(0, DB::table('N_PA_Participant_Pillar_Override')
            ->where('PA_Participant_Id', $this->participantId)->count());
    }

    public function test_override_pilar_yang_sama_dengan_aturan_level_tidak_disimpan(): void
    {
        /* Salinan levelnya 70/30 (lihat siapkanSiklus). Mengirim angka yang sama persis
           bukan penyimpangan — ia hanya baris yang harus dibaca orang tanpa memberi tahu
           apa pun, dan membuat peserta ini tampak menyimpang padahal tidak.

           Servicenya MENOLAK DENGAN LANTANG, bukan mengabaikan dengan diam — dan itu
           bahasa modul ini: `PaCalibrationService` juga melempar untuk kalibrasi yang
           tidak mengubah apa pun. Menolak lebih baik daripada diam, karena orang yang
           menekan simpan berhak tahu bahwa tidak ada yang terjadi. */
        try {
            $this->service()->simpanOverridePilar($this->participantId, 70, 30, 'sama saja', 1);
            $this->fail('Nilai identik dengan salinan seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sama dengan aturan level', $e->getMessage());
        }

        $this->assertSame(0, DB::table('N_PA_Participant_Pillar_Override')
            ->where('PA_Participant_Id', $this->participantId)
            ->where('Flag_Aktif', 'Y')
            ->count(), 'Penolakan tidak boleh menyisakan baris.');

        // Arah sebaliknya: yang benar-benar berbeda TERSIMPAN.
        $this->service()->simpanOverridePilar($this->participantId, 60, 40, 'benar berbeda', 1);

        $this->assertSame(1, DB::table('N_PA_Participant_Pillar_Override')
            ->where('PA_Participant_Id', $this->participantId)
            ->where('Flag_Aktif', 'Y')
            ->count());
    }

    /* ------------------------------------------------------------------ *
     * Gerbang                                                             *
     * ------------------------------------------------------------------ */

    public function test_siklus_terkunci_menolak_penyimpangan(): void
    {
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'LOCKED']);

        try {
            $this->service()->simpanOverridePilar($this->participantId, 60, 40, 'coba', 1);
            $this->fail('Siklus LOCKED seharusnya menolak penyimpangan.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            /* Sesudah dikunci, aturan sudah beku. Menyimpangkannya di tengah jalan berarti
               sebagian orang dinilai dengan aturan yang berbeda dari sebagian yang lain. */
            $this->assertStringContainsString('tidak diizinkan', $e->getMessage());
        }
    }

    public function test_peserta_yang_tidak_ada_ditolak(): void
    {
        $hantu = (int) DB::table('N_PA_Participant')->max('Id_PA_Participant') + 99999;

        $this->expectException(RuntimeException::class);

        $this->service()->simpanOverridePilar($hantu, 60, 40, 'coba', 1);
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    private function service(): PaParticipantOverrideService
    {
        return $this->app->make(PaParticipantOverrideService::class);
    }

    private function siapkanSiklus(): void
    {
        $kode = 'UJI-OVR-'.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Override',
            'Tahun' => (int) date('Y'),
            // DRAFT: penyimpangan hanya boleh sebelum siklus dikunci.
            'Status_Cycle' => 'DRAFT',
        ]);

        $this->cycleId = (int) DB::table('N_PA_Cycle')
            ->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');

        DB::table('N_PA_Rater_Policy')->insert([
            'Kode_Policy' => $kode,
            'Nama_Policy' => 'Policy Uji Override',
            'ID_Level' => $this->levelId,
            'Berlaku_Dari' => date('Y').'-01-01',
        ]);

        $policyId = (int) DB::table('N_PA_Rater_Policy')
            ->where('Kode_Policy', $kode)->value('Id_PA_Rater_Policy');

        DB::table('N_PA_Cycle_Rater_Policy_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'PA_Rater_Policy_Id' => $policyId,
            'Kode_Policy_Salinan' => $kode,
            'Nama_Policy_Salinan' => 'Policy Uji Override',
            'Versi_Policy_Salinan' => 1,
        ]);

        $snapshotId = (int) DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->value('Id_PA_Cycle_Rater_Policy_Snapshot');

        // 20 / 50 / 30 — berjumlah 100, dan sengaja tidak sama rata supaya penggabungannya
        // benar-benar teruji dan bukan kebetulan cocok.
        foreach ([['SELF', 20, 1], ['ATASAN', 50, 2], ['BAWAHAN', 30, 3]] as [$kodeKat, $bobot, $urutan]) {
            DB::table('N_PA_Rater_Policy_Detail')->insert([
                'PA_Rater_Policy_Id' => $policyId,
                'PA_Rater_Category_Id' => $this->kategori[$kodeKat],
                'Bobot_Persen' => $bobot,
                'Min_Rater' => 1,
                'Urutan_Tampil' => $urutan,
            ]);

            $detailId = (int) DB::table('N_PA_Rater_Policy_Detail')
                ->where('PA_Rater_Policy_Id', $policyId)
                ->where('PA_Rater_Category_Id', $this->kategori[$kodeKat])
                ->value('Id_PA_Rater_Policy_Detail');

            DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')->insert([
                'PA_Cycle_Rater_Policy_Snapshot_Id' => $snapshotId,
                'PA_Rater_Policy_Detail_Id' => $detailId,
                'PA_Rater_Category_Id' => $this->kategori[$kodeKat],
                'Kode_Kategori_Salinan' => $kodeKat,
                'Nama_Kategori_Salinan' => $kodeKat.' Salinan',
                'Bobot_Persen' => $bobot,
                'Min_Rater' => 1,
                'Min_Rater_Selesai' => 1,
                'Flag_Izinkan_Manual' => 'Y',
                'Urutan_Tampil' => $urutan,
            ]);
        }

        DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'Bobot_KPI' => 70,
            'Bobot_Kompetensi' => 30,
        ]);

        $k = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $this->levelId,
            'PA_Cycle_Rater_Policy_Snapshot_Id' => $snapshotId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        $this->participantId = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->value('Id_PA_Participant');
    }
}
