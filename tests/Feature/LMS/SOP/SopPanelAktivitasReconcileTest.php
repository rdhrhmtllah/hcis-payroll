<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopReconcileTaskService;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SopPanelAktivitasReconcileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_daur_hidup_baris_task_rekonsiliasi(): void
    {
        $svc = app(SopReconcileTaskService::class);

        $id = $svc->mulai(['id_dokumen' => 1, 'judul' => 'Dokumen Uji'], 40, 'UJI-KODE');

        $baris = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();
        $this->assertNotNull($baris, 'Baris task tidak terbentuk.');
        $this->assertSame(SopReconcileTaskService::TASK_TYPE, $baris->Task_Type);
        $this->assertSame(SopReconcileTaskService::MODULE, $baris->Module);
        $this->assertSame(AbstractExportService::STATUS_QUEUED, $baris->Status);
        $this->assertSame(40, (int) $baris->Total_Records);

        $svc->bumpProgress($id, 20, 40);
        $baris = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();
        $this->assertSame(20, (int) $baris->Processed_Records);
        $this->assertSame(50, (int) $baris->Percent);

        $svc->selesai($id, ['paket' => 3, 'diberitahu' => 12, 'gagal' => []]);
        $baris = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();
        $this->assertSame(AbstractExportService::STATUS_READY, $baris->Status);

        $hasil = json_decode($baris->Payload_Json, true)['hasil'] ?? null;
        $this->assertSame(3, $hasil['paket'] ?? null, 'Hasil tidak tersimpan di payload.');
    }

    public function test_baris_rekonsiliasi_terhitung_di_daftar_dan_di_lencana(): void
    {
        $svc = app(SopReconcileTaskService::class);

        /*
         * Panel dibangun ULANG untuk tiap pembacaan, bukan sekali di awal.
         *
         * ActivityCenterService menyatukan empat adapternya jadi SATU kueri dan
         * menyimpan hasilnya di instance ($petaTugas) — itu optimasi yang
         * disengaja: satu permintaan memanggil list() dan summary(), dan tanpa
         * cache itu keduanya membayar delapan perjalanan ke basis data.
         *
         * Konsekuensinya satu instance memotret SATU saat. Memakai instance yang
         * sama sebelum dan sesudah membuat baris berarti membaca potret lama,
         * dan itu bukan keadaan yang pernah dialami produksi: di sana tiap
         * permintaan membangun service-nya sendiri.
         */
        $panelBaru = fn () => app(\App\Services\ESS\Activity\Center\ActivityCenterService::class)->forUser('UJI-PANEL');

        // Lencana diukur sebagai SELISIH: pdfRenderItems() tidak disaring per
        // pengguna, jadi angka mutlak bisa sudah > 0 di data dev dan membuat
        // penjaga ini hijau palsu tanpa adapter apa pun.
        $sebelum = (int) ($panelBaru()->summary()['active_count'] ?? 0);

        $id = $svc->mulai(['id_dokumen' => 1, 'judul' => 'Dokumen Uji', 'paket' => 5], 5, 'UJI-PANEL');

        $daftar = collect($panelBaru()->list(50, false));
        $this->assertContains($id, $daftar->pluck('id')->all(),
            'Baris rekonsiliasi tak muncul di daftar panel Aktivitas.');

        $item = $daftar->firstWhere('id', $id);
        $this->assertSame('Rekonsiliasi Dokumen · Dokumen Uji · 5 paket', $item['label']);
        $this->assertSame('reconcile', $item['type'],
            'Frontend menyusun URL aksi dari field type — nilai lain membuat tombol tutup 404/422.');
        $this->assertSame("/api/v1/activities/reconcile/{$id}/dismiss", $item['dismiss_url']);
        $this->assertNull($item['download_url']);
        $this->assertNull($item['retry_url']);

        $sesudah = (int) ($panelBaru()->summary()['active_count'] ?? 0);
        $this->assertSame($sebelum + 1, $sesudah,
            'Baris rekonsiliasi tak terhitung di lencana. list() dan summary() adalah '
            .'dua pintu setara — mengerjakan salah satunya saja membuat item muncul '
            .'tanpa pernah menghitung, atau sebaliknya.');
    }

    public function test_label_menyebut_potongan_yang_gagal(): void
    {
        $svc = app(SopReconcileTaskService::class);
        $panel = app(\App\Services\ESS\Activity\Center\ActivityCenterService::class)->forUser('UJI-PANEL-GAGAL');

        $id = $svc->mulai(['id_dokumen' => 1, 'judul' => 'Prosedur Gudang', 'paket' => 3], 3, 'UJI-PANEL-GAGAL');
        $svc->selesai($id, ['paket' => 3, 'diberitahu' => 12, 'gagal' => 2]);

        $item = collect($panel->list(50, false))->firstWhere('id', $id);
        $this->assertNotNull($item, 'Baris rekonsiliasi selesai tak muncul di panel.');
        $this->assertSame(
            'Rekonsiliasi Dokumen · Prosedur Gudang · 3 paket — 3 paket, 12 diberitahu, 2 potongan gagal',
            $item['label'],
            'Potongan yang gagal tak disebut — admin melihat "selesai" atas pekerjaan yang separuh.'
        );
        $this->assertSame(AbstractExportService::STATUS_READY, $item['status'],
            'Rekonsiliasi tak punya File_Path; status ready jangan dijadikan "expired" oleh mapRow().');
    }

    public function test_payload_jalur_paket_tetap_berlabel_anggun(): void
    {
        $svc = app(SopReconcileTaskService::class);
        $panel = app(\App\Services\ESS\Activity\Center\ActivityCenterService::class)->forUser('UJI-PANEL-PAKET');

        $id = $svc->mulai(['id_paket' => 7, 'nama_paket' => 'Paket Gudang'], 1, 'UJI-PANEL-PAKET');

        $item = collect($panel->list(50, false))->firstWhere('id', $id);
        $this->assertNotNull($item, 'Baris rekonsiliasi jalur paket tak muncul di panel.');
        $this->assertSame('Rekonsiliasi Dokumen · Paket Gudang · 1 paket', $item['label']);
    }

    public function test_handler_menyemai_progres_untuk_paket_perujuk(): void
    {
        $dok = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->where('i.Flag_Aktif', 'Y')
            ->where('i.Flag_Wajib', 'Y')
            ->first(['i.Id_SOP_Dokumen', 'i.Id_SOP_Paket']);
        if (! $dok) {
            $this->markTestSkipped('Tak ada item wajib bertaut dokumen di data dev.');
        }

        $adaPenugasan = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $dok->Id_SOP_Paket)
            ->where('d.Flag_Aktif', 'Y')->where('d.Flag_Cabut_Akses', 'T')
            ->exists();
        if (! $adaPenugasan) {
            $this->markTestSkipped('Paket perujuk belum punya penugasan aktif di data dev.');
        }

        $taskSvc = app(\App\Services\LMS\SOP\SopReconcileTaskService::class);
        $taskId = $taskSvc->mulai(['id_dokumen' => (int) $dok->Id_SOP_Dokumen], 1, 'UJI');

        $outbox = \App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox::query()->create([
            'Task_Type' => 'SOP_RECONCILE_DOKUMEN',
            'Idempotency_Key' => 'UJI:'.uniqid('', true),
            'Queue_Name' => config('queue.names.penjadwalan_training'),
            'Status' => \App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox::STATUS_QUEUED,
        ]);

        $hasil = app(\App\Services\LMS\SOP\SopReconcileHandler::class)->handle($outbox, [
            'id_dokumen' => (int) $dok->Id_SOP_Dokumen,
            'alasan' => 'VERSI_BARU',
            'user_id' => null,
            'task_id' => $taskId,
        ]);

        $this->assertIsArray($hasil);
        $this->assertArrayHasKey('paket', $hasil, 'Handler tak melaporkan paket yang disentuh.');
        $this->assertGreaterThan(0, $hasil['paket'], 'Paket perujuk tak satu pun direkonsiliasi.');

        $baris = DB::table(AbstractExportService::TABLE)->where('Export_Id', $taskId)->first();
        $this->assertSame(AbstractExportService::STATUS_READY, $baris->Status,
            'Baris task tidak ditutup handler — panel Aktivitas akan menggantung di "memproses".');
    }
}
