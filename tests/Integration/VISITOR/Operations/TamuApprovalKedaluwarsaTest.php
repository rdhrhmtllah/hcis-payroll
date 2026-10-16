<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalDecisionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kunjungan yang approval-nya lewat Expired_At tanpa keputusan tidak boleh
 * tertahan PENDING_APPROVAL selamanya (audit VMS M11): ApprovalService menolak
 * memutus langkah kedaluwarsa, jadi hanya penyapu yang bisa menutupnya.
 */
class TamuApprovalKedaluwarsaTest extends TestCase
{
    use DatabaseTransactions;

    private function kunjunganMenunggu(string $expiredAt): string
    {
        $no = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('Flag_Aktif', 'Y')->orderByDesc('Created_At')->value('No_Transaksi');
        if (! $no) {
            $this->markTestSkipped('Tidak ada kunjungan di DB uji.');
        }

        DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $no)
            ->update(['Flag_Approval' => null, 'Status_Kunjungan' => StatusKunjunganTamu::PENDING_APPROVAL]);
        DB::table('HRIS_Approval_Request')->where('No_Transaksi', $no)->delete();
        DB::table('HRIS_Approval_Request')->insert([
            'Kode_Karyawan' => 'A1',
            'No_Transaksi' => $no,
            'Flag_Approval' => null,
            'Request_Order_Flow' => 1,
            'Expired_At' => $expiredAt,
            'created_at' => now(),
            'update_at' => now(),
        ]);

        return $no;
    }

    public function test_approval_kedaluwarsa_menutup_kunjungan_sebagai_ditolak(): void
    {
        Bus::fake([PublishVisitorNotificationsJob::class]);
        $no = $this->kunjunganMenunggu(now()->subDay()->toDateTimeString());

        $this->artisan('visitor:expire-efektif')->assertExitCode(0);

        $header = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $no)->first();
        $this->assertSame(StatusKunjunganTamu::REJECTED, $header->Status_Kunjungan);
        $this->assertSame('T', $header->Flag_Approval);
        $this->assertSame(
            VisitorApprovalDecisionService::ALASAN_APPROVAL_KEDALUWARSA,
            DB::table('HRIS_Approval_Request')->where('No_Transaksi', $no)->value('Catatan'),
        );
        Bus::assertDispatched(PublishVisitorNotificationsJob::class);
    }

    public function test_memutus_approval_kedaluwarsa_ditolak_sebagai_konflik_bukan_galat_server(): void
    {
        // ApprovalService melempar \Exception polos -> 500. Kini DomainException
        // (409 di controller) dengan sebab yang jelas.
        $no = $this->kunjunganMenunggu(now()->subDay()->toDateTimeString());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Batas waktu approval');

        app(VisitorApprovalDecisionService::class)->rejectTamu($no, 'H45', 'uji');
    }

    public function test_approval_yang_belum_kedaluwarsa_tidak_disentuh(): void
    {
        $no = $this->kunjunganMenunggu(now()->addDay()->toDateTimeString());

        $this->assertSame([], array_filter(
            app(VisitorApprovalDecisionService::class)->tutupTamuApprovalKedaluwarsa(),
            fn ($k) => $k['no_transaksi'] === $no,
        ));
        $this->assertSame(
            StatusKunjunganTamu::PENDING_APPROVAL,
            DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $no)->value('Status_Kunjungan'),
        );
    }
}
