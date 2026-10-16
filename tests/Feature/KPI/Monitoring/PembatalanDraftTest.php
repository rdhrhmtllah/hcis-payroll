<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Services\KPI\KpiCancellationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin membatalkan DRAFT update performance dari monitoring.
 *
 * Draft tinggal di tabel sementara dan belum punya transaksi final maupun
 * alur approval, jadi ia dibatalkan lewat domain 'draft' tersendiri. Yang
 * dijaga di sini adalah dua balapan dengan karyawan pemilik draft: ia bisa
 * menyimpan ulang atau mengirim drafnya di antara preview dan eksekusi.
 */
class PembatalanDraftTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): KpiCancellationService
    {
        return app(KpiCancellationService::class);
    }

    /**
     * Header draft milik karyawan yang ada, dipaksa ke keadaan "hidup dan
     * belum dikirim". Flag-nya diubah di dalam transaksi uji.
     */
    private function drafHidup(): int
    {
        $id = DB::table('KPI_Update_Performance_Sementara as s')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 's.Id_User')
            ->orderByDesc('s.Id_Update_Performance_Sementara')
            ->value('s.Id_Update_Performance_Sementara');

        if (! $id) {
            $this->markTestSkipped('Tidak ada header draft berkaryawan di data dev.');
        }

        DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Update_Performance_Sementara', $id)
            ->update([
                'Flag_Aktif' => 'Y',
                'Flag_Pindah' => null,
                'Cancelled_At' => null,
                'Cancelled_By' => null,
                'Cancelled_By_Nama' => null,
                'Cancel_Reason' => null,
                'Cancel_Batch_Id' => null,
            ]);

        return (int) $id;
    }

    private function sidikJari(int $id): string
    {
        $item = $this->svc()->buildPreview('draft', [$id])['items'][0];
        $this->assertFalse($item['blocked'], 'Draft hidup harus boleh dibatalkan: '.($item['block_reason'] ?? ''));

        return $item['fingerprint'];
    }

    public function test_draft_hidup_dibatalkan_dan_tercatat_sebagai_manual(): void
    {
        $id = $this->drafHidup();

        $preview = $this->svc()->buildPreview('draft', [$id]);
        $item = $preview['items'][0];

        $this->assertFalse($item['blocked']);
        $this->assertSame('draft', $item['status']);
        $this->assertSame([], $item['cascade_performance'], 'Membatalkan draft tidak boleh menyeret transaksi lain.');

        $hasil = $this->svc()->execute('draft', [$id], 'Uji pembatalan draft', [$id => $item['fingerprint']], null);

        $this->assertTrue($hasil['ok'], json_encode($hasil['conflicts'] ?? []));
        $this->assertSame(['parameter' => 0, 'performance' => 0, 'draft' => 1], $hasil['result']['cancelled']);

        $header = DB::table('KPI_Update_Performance_Sementara')->where('Id_Update_Performance_Sementara', $id)->first();
        $this->assertSame('T', $header->Flag_Aktif);
        $this->assertNotNull($header->Cancelled_At);
        $this->assertSame($hasil['result']['batch_id'], $header->Cancel_Batch_Id);

        $riwayat = collect($this->svc()->getHistory(['batch_id' => $hasil['result']['batch_id']])['rows']);
        $this->assertCount(1, $riwayat);
        $this->assertSame('draft', $riwayat->first()['domain']);
        $this->assertSame(
            'manual',
            $riwayat->first()['source'],
            'Draft yang dibatalkan langsung bukan cascade; riwayat dulu selalu menulis "cascade".',
        );
    }

    public function test_draft_yang_sudah_dikirim_ditolak(): void
    {
        $id = $this->drafHidup();
        DB::table('KPI_Update_Performance_Sementara')->where('Id_Update_Performance_Sementara', $id)->update(['Flag_Pindah' => 'Y']);

        $item = $this->svc()->buildPreview('draft', [$id])['items'][0];

        $this->assertTrue($item['blocked'], 'Draft yang sudah jadi transaksi dibatalkan lewat transaksinya, bukan drafnya.');

        $hasil = $this->svc()->execute('draft', [$id], 'Uji pembatalan draft', [$id => 'apa-pun'], null);

        $this->assertFalse($hasil['ok']);
        $this->assertSame(
            'Y',
            DB::table('KPI_Update_Performance_Sementara')->where('Id_Update_Performance_Sementara', $id)->value('Flag_Aktif'),
        );
    }

    /**
     * Karyawan menyimpan ulang drafnya setelah admin membuka preview. Admin
     * sedang melihat isian lama; membatalkannya diam-diam membuang kerja
     * yang belum pernah ia lihat.
     */
    public function test_draft_yang_disimpan_ulang_setelah_preview_menolak_seluruh_aksi(): void
    {
        $id = $this->drafHidup();
        $sidik = $this->sidikJari($id);

        DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Update_Performance_Sementara', $id)
            ->update(['Tanggal_Terakhir' => now()->addDay(), 'Jam_Terakhir' => '23:59:59']);

        $hasil = $this->svc()->execute('draft', [$id], 'Uji pembatalan draft', [$id => $sidik], null);

        $this->assertFalse($hasil['ok']);
        $this->assertSame(
            'Y',
            DB::table('KPI_Update_Performance_Sementara')->where('Id_Update_Performance_Sementara', $id)->value('Flag_Aktif'),
            'Konflik harus berarti tidak ada yang dibatalkan.',
        );
    }

    public function test_domain_draft_diterima_endpoint(): void
    {
        $id = $this->drafHidup();

        $res = app(\App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController::class)
            ->previewCancellation(new \Illuminate\Http\Request(['domain' => 'draft', 'ids' => [$id]]));

        $this->assertSame(200, $res->getStatusCode(), $res->getContent());
        $this->assertSame('draft', json_decode($res->getContent(), true)['result']['domain']);
    }
}
