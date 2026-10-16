<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalDecisionService;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Permit karyawan yang batas approval-nya lewat tanpa keputusan: pemohonnya
 * dikabari SEKALI, permitnya tidak ditutup (tetap terbaca EXPIRED, tinggal
 * arsip). Tanpa kabar ini ia baru tahu bila membuka aplikasinya sendiri.
 */
class PermitApprovalKedaluwarsaTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PEMOHON = 'A1';

    protected function setUp(): void
    {
        parent::setUp();

        // A1 tercatat resign di dev (3 Sep 2025). Di sini ia diperlakukan masih
        // bekerja; perubahannya ikut dibatalkan bersama transaksi uji.
        DB::table('Karyawan')->where('Kode_Karyawan', self::PEMOHON)->update(['Tanggal_Resign' => null]);
    }

    public function test_pemohon_dikabari_sekali_dan_permitnya_tidak_ditutup(): void
    {
        Bus::fake([PublishVisitorNotificationsJob::class]);
        $no = $this->permitMenunggu(now()->subDay());

        $this->artisan('visitor:expire-efektif')->assertExitCode(0);

        Bus::assertDispatched(PublishVisitorNotificationsJob::class, fn ($job) => $this->memuat($job, $no));
        $this->assertSame(
            VisitorApprovalDecisionService::EXPIRED_OLEH_SISTEM,
            DB::table('HRIS_Approval_Request')->where('No_Transaksi', $no)->value('Expired_by'),
        );
        $this->assertNull(DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->value('Flag_Approval'));

        // Sapuan berikutnya tidak mengabarkan hal yang sama lagi.
        $this->assertNotContains($no, app(VisitorApprovalDecisionService::class)->tandaiPermitApprovalKedaluwarsa());
    }

    public function test_kedaluwarsa_lama_dan_yang_belum_kedaluwarsa_tidak_disentuh(): void
    {
        $lama = $this->permitMenunggu(now()->subDays(VisitorApprovalDecisionService::HARI_KABAR_KEDALUWARSA + 1));
        $hidup = $this->permitMenunggu(now()->addDay());

        $ditandai = app(VisitorApprovalDecisionService::class)->tandaiPermitApprovalKedaluwarsa();

        $this->assertNotContains($lama, $ditandai, 'Pengajuan yang sudah lama mati ikut dikabarkan serentak.');
        $this->assertNotContains($hidup, $ditandai);
        $this->assertNull(DB::table('HRIS_Approval_Request')->where('No_Transaksi', $lama)->value('Expired_by'));
    }

    public function test_isi_kabarnya_menyebut_kedaluwarsa(): void
    {
        $no = $this->permitMenunggu(now()->subDay());

        $this->assertSame(1, app(VisitorNotificationService::class)->notifikasiKeputusan(
            VisitorNotificationService::DOMAIN_PERMIT,
            $no,
            VisitorNotificationService::KEPUTUSAN_KEDALUWARSA,
        ));

        $notif = DB::table('N_HRIS_Notification')->where('Reference_Id', $no)->orderByDesc('Id_Notification')->first();
        $this->assertSame('Permit Kendaraan Kedaluwarsa', $notif->Title);
        $this->assertStringContainsString('kedaluwarsa', $notif->Message);
    }

    private function memuat(PublishVisitorNotificationsJob $job, string $no): bool
    {
        $events = $job->events;

        return collect($events)->contains(fn ($e) => $e['no_transaksi'] === $no
            && $e['decision'] === VisitorNotificationService::KEPUTUSAN_KEDALUWARSA);
    }

    private function permitMenunggu(\DateTimeInterface $expiredAt): string
    {
        $nomor = 'PMT-UJI-'.Str::upper(Str::random(8));

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->insert([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PEMOHON,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            'Sumber_Pengajuan' => 'HCIS',
            'Created_At' => now(),
            'Created_By' => self::PEMOHON,
            'Updated_At' => now(),
            'Idempotency_Key' => 'UJI-'.Str::upper(Str::random(20)),
        ]);

        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan' => self::PEMOHON,
            'Request_Order_Flow' => 1,
            'Flag_Approval' => null,
            'Expired_At' => $expiredAt,
            'created_at' => now(),
            'update_at' => now(),
        ]);

        return $nomor;
    }
}
