<?php

namespace Tests\Integration\SYSTEM\TaskProcessing;

use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use App\Services\SYSTEM\TaskProcessing\LmsReassignThirdPartyHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class LmsReassignThirdPartyHandlerTest extends TestCase
{
    public function test_it_reassigns_outside_the_database_transaction_and_persists_the_new_token(): void
    {
        /*
         | SISA DARI JALANNYA YANG TERPUTUS DISAPU LEBIH DULU.
         |
         | Pembersihan uji ini ada di blok `finally`, dan `finally` tidak pernah jalan bila
         | prosesnya DIBUNUH di tengah — batas waktu, Ctrl+C, mesin kehabisan memori. Yang
         | tertinggal adalah baris integrasi bertoken OLD-TOKEN-001 berpayload {"old":true},
         | dan itu MEMERAHKAN uji lain: penjaga data di LmsMateriLinkUjianTest menuntut setiap
         | payload integrasi memuat Link_Ujian.
         |
         | Kegagalannya menyesatkan dua kali — muncul di berkas yang tidak ada hubungannya,
         | dan berbunyi seperti "bentuk data berubah" padahal cuma sampah uji. Menyapunya di
         | sini membuat jalan berikutnya membereskan sendiri apa yang ditinggalkan jalan
         | sebelumnya, tanpa seorang pun perlu tahu ceritanya.
         */
        DB::table('N_LMS_Penjadwalan_Integrasi')
            ->whereIn('Id_Ujian_Token', ['OLD-TOKEN-001', 'NEW-TOKEN-001'])
            ->delete();

        config(['services.frans.hclearn_api.domain' => 'https://lms.example.test']);
        Http::fake(['https://lms.example.test/api/v1/penjadwalan/training/reassign' => Http::response([
            'result' => [[
                'Id_Ujian_Token' => 'NEW-TOKEN-001',
                'Payload_Json' => ['otp' => '123456'],
            ]],
        ], 200)]);

        $detId = DB::table('N_LMS_Penjadwalan_Det')->insertGetId([
            'Waktu_Mulai_Akses' => '2026-08-16 09:00:00',
            'Waktu_Batas_Akses' => null,
        ], 'Id_Penjadwalan_Det');
        $integrationId = DB::table('N_LMS_Penjadwalan_Integrasi')->insertGetId([
            'Id_Penjadwalan_Det' => $detId,
            'Id_Master_Ujian' => 'OLD-EXAM',
            'Waktu_Mulai' => '2026-08-16 09:00:00',
            'Waktu_Akhir' => null,
            'Id_Ujian_Token' => 'OLD-TOKEN-001',
            'Payload_Json' => '{"old":true}',
        ]);
        $row = SystemTaskOutbox::query()->create([
            'Task_Type' => 'LMS_REASSIGN_THIRD_PARTY',
            'Idempotency_Key' => 'TEST:LMS:REASSIGN:'.Str::uuid(),
            'Queue_Name' => 'lms',
            'Payload' => [
                'integration_id' => $integrationId,
                'id_penjadwalan_det' => $detId,
                'kode_karyawan' => 'K-TEST-001',
                'id_master_ujian' => 'NEW-EXAM',
                'waktu_mulai' => '2026-08-16 10:00:00',
                'waktu_akhir' => null,
                'flag_camera' => false,
                'old_token' => 'OLD-TOKEN-001',
                'app_id' => 'app-test',
                'kode_karyawan_auth' => 'SYSTEM',
            ],
            'Status' => SystemTaskOutbox::STATUS_PROCESSING,
        ]);

        try {
            $result = app(LmsReassignThirdPartyHandler::class)->handle($row, $row->Payload);
            $this->assertSame('NEW-TOKEN-001', $result['new_token']);
            $this->assertSame(0, DB::transactionLevel());
            $fresh = DB::table('N_LMS_Penjadwalan_Integrasi')->where('Id_Penjadwalan_Integrasi', $integrationId)->first();
            $this->assertSame('NEW-TOKEN-001', $fresh->Id_Ujian_Token);
            Http::assertSentCount(1);
        } finally {
            DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)->delete();
            DB::table('N_LMS_Penjadwalan_Integrasi')->where('Id_Penjadwalan_Integrasi', $integrationId)->delete();
            DB::table('N_LMS_Penjadwalan_Det')->where('Id_Penjadwalan_Det', $detId)->delete();
        }
    }
}
