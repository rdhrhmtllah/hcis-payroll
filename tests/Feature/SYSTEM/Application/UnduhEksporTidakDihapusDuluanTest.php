<?php

namespace Tests\Feature\SYSTEM\Application;

use App\Jobs\SYSTEM\Application\Exports\DeleteExportFileJob;
use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use App\Services\SYSTEM\Governance\Authorization\Menu\MenuKaryawanMatrixExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Unduhan pertama dari Pusat Aktivitas tidak boleh keduluan penghapusan
 * berkasnya sendiri.
 *
 * Endpoint dulu mengirim DeleteExportFileJob tanpa jeda sebelum men-stream
 * berkas. Di Cloud Tasks job itu tereksekusi dalam ~1 detik, sering sebelum
 * stream dimulai, sehingga unduhan pertama berakhir 500 "No such object".
 */
class UnduhEksporTidakDihapusDuluanTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hapus_berkas_dijadwalkan_berjeda_dan_berkas_terkirim(): void
    {
        $user = User::query()->where('Flag_Active', 'Y')->first();
        if (! $user) {
            $this->markTestSkipped('Tidak ada user aktif di data dev.');
        }

        Storage::fake(AbstractExportService::DISK);
        Queue::fake();

        $ekspor = app(MenuKaryawanMatrixExportService::class);
        $id = $ekspor->makeExportId();
        $ekspor->startJob($id, 'xlsx', ['grup_id' => 0], 1, 'UJI');

        $path = 'menu-karyawan-matrix-exports/'.$id.'.xlsx';
        Storage::disk(AbstractExportService::DISK)->put($path, 'isi-uji');
        DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->update([
            'Status' => AbstractExportService::STATUS_READY,
            'File_Path' => $path,
            'File_Name' => 'uji.xlsx',
        ]);

        $res = $this->actingAs($user)->get("/api/v1/activities/export/{$id}/download");

        $res->assertOk();
        $this->assertSame('isi-uji', $res->streamedContent());

        Queue::assertPushed(DeleteExportFileJob::class, function ($job) {
            $this->assertNotNull($job->delay, 'Penghapusan tanpa jeda bisa mendahului stream.');
            $this->assertGreaterThanOrEqual(
                now()->addMinutes(50)->getTimestamp(),
                \Illuminate\Support\Carbon::parse($job->delay)->getTimestamp(),
            );

            return true;
        });
    }
}
