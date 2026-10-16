<?php

namespace Tests\Feature\SYSTEM;

use App\Services\ESS\Activity\Center\ActivityCenterService;
use App\Services\SYSTEM\TaskProcessing\TaskOutboxService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ajukan ulang ujian LMS berjalan lewat Outbox, bukan N_HRIS_Async_Tasks; tanpa sumber ini panel
 * Tugas & Aktivitas diam saja tentangnya — juga ketika ia gagal sesudah terkirim.
 */
class ActivityCenterOutboxLmsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ajukan_ulang_tampil_hanya_untuk_pengajunya_dengan_status_dan_sebab(): void
    {
        $pengaju = 'UJI'.strtoupper(Str::random(6));
        $selesai = $this->baris($pengaju, 'COMPLETED');
        $gagal = $this->baris($pengaju, 'FAILED', 'Queue tidak ditemukan.');
        $this->baris('ORANGLAIN', 'COMPLETED');

        $item = collect((new ActivityCenterService)->forUser($pengaju)->list(50))
            ->where('type', 'lms-outbox')->keyBy('id');

        $this->assertCount(2, $item, 'Hanya permintaan milik pengaju yang tampil.');
        $this->assertSame('ready', $item['outbox-'.$selesai]['status']);
        $this->assertStringContainsString('Ajukan Ulang Ujian · Budi (C9)', $item['outbox-'.$selesai]['label']);
        $this->assertSame('/lms/penjadwalan', $item['outbox-'.$selesai]['open_url']);
        $this->assertSame('failed', $item['outbox-'.$gagal]['status']);
        $this->assertSame('Queue tidak ditemukan.', $item['outbox-'.$gagal]['error_message']);

        // Tak bisa disembunyikan (Outbox tak punya penandanya), jadi "Bersihkan" tidak menawarkannya.
        $this->assertNull($item['outbox-'.$selesai]['dismiss_url']);
    }

    public function test_yang_dibatalkan_dan_yang_sudah_lewat_sehari_tidak_tampil(): void
    {
        $pengaju = 'UJI'.strtoupper(Str::random(6));
        $this->baris($pengaju, 'CANCELLED');
        $lama = $this->baris($pengaju, 'COMPLETED');
        DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $lama)
            ->update(['Created_At' => now('UTC')->subHours(30)]);
        $masihAntri = $this->baris($pengaju, 'QUEUED');
        DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $masihAntri)
            ->update(['Created_At' => now('UTC')->subHours(30)]);

        $item = collect((new ActivityCenterService)->forUser($pengaju)->list(50))->where('type', 'lms-outbox');

        $this->assertSame(['outbox-'.$masihAntri], $item->pluck('id')->values()->all(),
            'Yang masih berjalan tetap tampil berapa pun umurnya; yang final lewat 24 jam tidak.');
        $this->assertSame(1, (new ActivityCenterService)->forUser($pengaju)->summary()['active_count']);
    }

    private function baris(string $pengaju, string $status, ?string $galat = null): int
    {
        $row = app(TaskOutboxService::class)->create([
            'Task_Type' => 'LMS_REASSIGN_THIRD_PARTY',
            'Idempotency_Key' => 'UJI:PANEL:'.Str::uuid(),
            'Queue_Name' => 'penjadwalans-queue',
            'Task_Url' => 'https://example.test/task',
            'Payload' => ['kode_karyawan' => 'C9', 'data_karyawan' => [['Nama' => 'Budi']]],
            'Sender_Employee_Code' => $pengaju,
        ]);
        DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)
            ->update(['Status' => $status, 'Last_Error' => $galat]);

        return (int) $row->Id_Task_Outbox;
    }
}
