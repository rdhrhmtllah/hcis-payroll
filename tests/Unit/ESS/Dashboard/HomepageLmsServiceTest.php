<?php

namespace Tests\Unit\ESS\Dashboard;

use App\Services\ESS\Dashboard\Homepage\HomepageLmsService;
use App\Services\LMS\TrainingExecutionService;
use App\Services\LMS\TrainingScheduleQueryService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class HomepageLmsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-04-29 09:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_get_summary_uses_active_schedules_and_execution_enrichment(): void
    {
        $scheduleService = Mockery::mock(TrainingScheduleQueryService::class);
        $scheduleService->shouldReceive('getSchedulesByKodeKaryawans')
            ->once()
            ->with(['EMP001'], true)
            ->andReturn(collect([
                (object) [
                    'Kode_Karyawan' => 'EMP001',
                    'Id_Penjadwalan' => 10,
                    'Id_Master_Paket' => 3,
                    'Flag_Selesai' => 'Y',
                    'Flag_Cabut_Akses' => null,
                    'Waktu_Enroll' => '2026-04-10 08:00:00',
                    'Nama_Paket' => 'Training Safety',
                    'Flag_Learning_Gain' => 'Y',
                ],
                (object) [
                    'Kode_Karyawan' => 'EMP001',
                    'Id_Penjadwalan' => 11,
                    'Id_Master_Paket' => 4,
                    'Flag_Selesai' => 'T',
                    'Flag_Cabut_Akses' => null,
                    'Waktu_Enroll' => '2026-04-20 08:00:00',
                    'Nama_Paket' => 'Training SOP',
                ],
            ]));
        $scheduleService->shouldReceive('getSchedulesByKodeKaryawans')
            ->with(['EMP001'], false)
            ->andReturn(collect());
        $scheduleService->shouldReceive('getProgressSnapshotsByPenjadwalanIds')
            ->once()
            ->with([10, 11])
            ->andReturn(collect([
                10 => (object) ['Id_Penjadwalan' => 10, 'total_items' => 4, 'completed_items' => 4],
                11 => (object) ['Id_Penjadwalan' => 11, 'total_items' => 4, 'completed_items' => 1],
            ]));

        $executionService = Mockery::mock(TrainingExecutionService::class);
        $executionService->shouldReceive('getExecutionSummariesByPenjadwalanIds')
            ->once()
            ->with([10, 11])
            ->andReturn(collect([
                /* Id_Keterangan_Kelulusan WAJIB ada. Layanan memakainya untuk
                   membedakan baris hasil yang sudah dinilai dari baris
                   penampung yang lahir saat training dimulai — yang berisi skor
                   0 dan tak boleh ikut menurunkan rata-rata. Tanpa properti ini
                   layanan melempar, tangkapannya menelan pesannya, dan yang
                   terlihat hanya has_data=false tanpa sebab. */
                10 => (object) [
                    'Id_Penjadwalan' => 10,
                    'Id_Keterangan_Kelulusan' => 1,
                    'Total_Score' => 91.5,
                    'Score_Learning_Gain' => 4.2,
                ],
            ]));

        $service = new HomepageLmsService($scheduleService, $executionService);
        $summary = $service->getSummary('EMP001');

        $this->assertTrue($summary['has_data']);
        $this->assertSame(2, $summary['enrolled_courses']);
        $this->assertSame(1, $summary['completed_courses']);
        $this->assertSame(100, $summary['trainings'][0]['status'] === 'complete' ? 100 : 0);
        $this->assertSame(91.5, $summary['average_score']);
        $this->assertSame(4.2, $summary['learning_gain']);
        $this->assertSame('Training SOP', $summary['next_session']);
        $this->assertCount(2, $summary['learning_progress']);
        $this->assertCount(12, $summary['completed_monthly']);
    }

    /**
     * Penugasan NONAKTIF tidak dihitung — dan tidak ada jalan mundur ke sana.
     *
     * Uji ini semula bernama "falls back to inactive schedules" dan menuntut
     * kebalikannya: bila tak ada penugasan aktif, layanan diminta bertanya lagi
     * tanpa penyaring aktif lalu memakai hasilnya. Perilaku itu SENGAJA DICABUT
     * (aturan PD-053/LD-023, disebut di komentar HomepageLmsService): penugasan
     * yang sudah dicabut aksesnya bukan training aktif, dan menampilkannya di
     * beranda membuat karyawan mengira masih punya kewajiban yang sudah hilang.
     *
     * Yang dijaga sekarang justru ketiadaan jalan mundur itu: layanan hanya
     * boleh bertanya SEKALI, dengan penyaring aktif menyala.
     */
    public function test_penugasan_nonaktif_tidak_dijadikan_jalan_mundur(): void
    {
        $scheduleService = Mockery::mock(TrainingScheduleQueryService::class);
        $scheduleService->shouldReceive('getSchedulesByKodeKaryawans')
            ->once()
            ->with(['EMP001'], true)
            ->andReturn(collect());

        /* Penyaring-mati TIDAK BOLEH dipanggil. Tanpa harapan ini, penambahan
           kembali jalan mundur akan lolos tanpa suara. */
        $scheduleService->shouldReceive('getSchedulesByKodeKaryawans')
            ->never()
            ->with(['EMP001'], false);

        $executionService = Mockery::mock(TrainingExecutionService::class);

        $summary = (new HomepageLmsService($scheduleService, $executionService))->getSummary('EMP001');

        $this->assertFalse($summary['has_data'],
            'Beranda menyajikan data padahal seluruh penugasan aktifnya sudah tak ada.');
        $this->assertSame(0, $summary['enrolled_courses']);
        $this->assertSame(0, $summary['mandatory_pending'],
            'Karyawan masih diberi tahu punya kewajiban tertunda dari penugasan yang dicabut.');
    }
}
