<?php

namespace Tests\Feature\LMS\SOP\Otomasi;

use App\Services\LMS\SOP\SopAutomationRunService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Riwayat eksekusi otomasi: satu baris induk, satu baris anak per paket.
 *
 * Dua hal yang dijaga. Pertama, penjaga konkurensi tetap melihat baris INDUK
 * saja — kalau baris anak ikut terhitung, aturan tampak "masih berjalan"
 * selamanya dan tak pernah bisa di-apply lagi. Kedua, plafon riwayat
 * melaporkan dirinya; pemotongan senyap membuat jejak audit yang hilang
 * terbaca seperti jejak audit yang lengkap.
 */
class SopRiwayatOtomasiTest extends TestCase
{
    use DatabaseTransactions;

    private function idPaket(): int
    {
        $id = (int) DB::table('N_SOP_Master_Paket')->value('Id_SOP_Paket');
        if (! $id) {
            $this->markTestSkipped('Tidak ada paket dokumen di basis data uji.');
        }

        return $id;
    }

    public function test_baris_anak_menempel_pada_induknya_dan_membawa_paketnya(): void
    {
        $svc = app(SopAutomationRunService::class);
        $idPaket = $this->idPaket();

        $induk = $svc->queue('RULE_APPLY', 'ACTIVATION');
        $anak = $svc->queueAnak($induk, $idPaket, 'RULE_APPLY', 'ACTIVATION', null, null, null);

        $baris = DB::table('N_SOP_Automation_Run')->where('Id_SOP_Automation_Run', $anak)->first();

        $this->assertSame($induk, (int) $baris->Id_Run_Induk, 'Baris anak tidak menunjuk induknya.');
        $this->assertSame($idPaket, (int) $baris->Id_SOP_Paket, 'Baris anak tidak menyimpan paketnya.');
    }

    public function test_riwayat_memuat_induk_beserta_anaknya_dan_nama_paket(): void
    {
        $svc = app(SopAutomationRunService::class);
        $idPaket = $this->idPaket();

        $induk = $svc->queue('RULE_APPLY', 'ACTIVATION');
        $anak = $svc->queueAnak($induk, $idPaket, 'RULE_APPLY', 'ACTIVATION');

        $rows = collect($svc->history(50)['rows'])->keyBy('Id_SOP_Automation_Run');

        $barisAnak = $rows->get($svc->encodeUji($anak));
        $this->assertNotNull($barisAnak, 'Baris anak tidak muncul di riwayat.');
        $this->assertTrue((bool) $barisAnak->Is_Anak, 'Baris anak tidak ditandai sebagai anak.');
        $this->assertNotEmpty($barisAnak->Nama_Paket,
            'Nama paket tidak ikut di baris anak; kolom Paket Dokumen di tabel jadi kosong.');

        $this->assertNotNull($rows->get($svc->encodeUji($induk)), 'Baris induk tidak muncul di riwayat.');
    }

    /**
     * PENJAGA. Plafon tidak boleh memotong diam-diam.
     */
    public function test_riwayat_melaporkan_plafon_dan_total_sebenarnya(): void
    {
        $svc = app(SopAutomationRunService::class);
        for ($i = 0; $i < 3; $i++) {
            $svc->queue('RULE_SCAN', 'MANUAL');
        }

        $riwayat = $svc->history(2);

        $this->assertArrayHasKey('ditampilkan', $riwayat);
        $this->assertArrayHasKey('total_induk', $riwayat);
        $this->assertSame(2, $riwayat['ditampilkan'], 'Plafon tidak ditaati.');
        $this->assertGreaterThanOrEqual(3, $riwayat['total_induk'],
            'Total sebenarnya tidak dilaporkan, sehingga pemotongan jadi senyap.');
    }

    /**
     * Baris anak tidak boleh memakan jatah plafon: satu aktivasi lima paket
     * kalau tidak akan menghabiskan sepersepuluh jendela riwayat sendirian.
     */
    public function test_baris_anak_tidak_memakan_jatah_plafon(): void
    {
        $svc = app(SopAutomationRunService::class);
        $idPaket = $this->idPaket();

        $induk = $svc->queue('RULE_APPLY', 'ACTIVATION');
        for ($i = 0; $i < 4; $i++) {
            $svc->queueAnak($induk, $idPaket, 'RULE_APPLY', 'ACTIVATION');
        }

        $riwayat = $svc->history(1);

        $this->assertSame(1, $riwayat['ditampilkan'], 'Plafon induk tidak ditaati.');
        $this->assertCount(5, $riwayat['rows'],
            'Baris anak tidak ikut serta, atau justru memakan jatah plafon induk.');
    }

    /**
     * Penjaga konkurensi hanya melihat baris induk. Kalau baris anak ikut
     * terhitung, aturan tampak "masih berjalan" setelah induknya selesai dan
     * tak pernah bisa di-apply lagi.
     */
    public function test_penjaga_konkurensi_mengabaikan_baris_anak(): void
    {
        $svc = app(SopAutomationRunService::class);
        $idPaket = $this->idPaket();

        $induk = $svc->queue('RULE_APPLY', 'ACTIVATION');
        $svc->queueAnak($induk, $idPaket, 'RULE_APPLY', 'ACTIVATION');
        $svc->success($induk, []);

        $this->assertFalse($svc->hasActive('RULE_APPLY'),
            'Baris anak yang berstatus RUNNING membuat otomasi terlihat masih berjalan selamanya.');
    }

    public function test_reap_stale_tidak_menyapu_baris_anak(): void
    {
        $svc = app(SopAutomationRunService::class);
        $idPaket = $this->idPaket();

        $induk = $svc->queue('RULE_SCAN', 'MANUAL');
        $anak = $svc->queueAnak($induk, $idPaket, 'RULE_APPLY', 'ACTIVATION');

        // Tuakan keduanya melewati ambang penyapuan.
        DB::table('N_SOP_Automation_Run')
            ->whereIn('Id_SOP_Automation_Run', [$induk, $anak])
            ->update(['Queued_At' => now()->subHours(5), 'Started_At' => now()->subHours(5)]);

        $svc->reapStale(120);

        $statusAnak = DB::table('N_SOP_Automation_Run')->where('Id_SOP_Automation_Run', $anak)->value('Status_Run');
        $statusInduk = DB::table('N_SOP_Automation_Run')->where('Id_SOP_Automation_Run', $induk)->value('Status_Run');

        $this->assertSame('FAILED', $statusInduk, 'Induk yang menggantung tidak disapu.');
        $this->assertSame('RUNNING', $statusAnak,
            'Baris anak ikut disapu; hasil per paket jadi tertimpa pesan timeout milik induknya.');
    }
}
