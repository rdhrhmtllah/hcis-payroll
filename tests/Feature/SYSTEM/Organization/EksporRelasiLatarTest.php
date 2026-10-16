<?php

namespace Tests\Feature\SYSTEM\Organization;

use App\Jobs\SYSTEM\Organization\GenerateRelasiOrganisasiExportJob;
use App\Services\SYSTEM\Organization\OrganizationRelationAsyncExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** Export Relasi di latar belakang: antrean, baca per batch, berkas tersimpan, hanya untuk pemintanya. */
class EksporRelasiLatarTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(OrganizationRelationAsyncExportService::DISK);
        Cache::forget('relasi-ekspor:penyimpanan-siap');
    }

    public function test_mulai_mengantrekan_dan_proses_menghasilkan_berkas_yang_sama_isinya(): void
    {
        Queue::fake();
        $layanan = app(OrganizationRelationAsyncExportService::class);
        $divisi = DB::table('View_Divisi_Sub_Divisi as d')
            ->join('Karyawan as k', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
            ->join('N_HRIS_Organization_Relationship as r', 'r.Kode_Karyawan_Subjek', '=', 'k.Kode_Karyawan')
            ->where('r.Flag_Aktif', 'Y')->value('d.nama_divisi');
        if ($divisi === null) {
            $this->markTestSkipped('Relasi dev kosong.');
        }

        $id = $layanan->mulai(['subjek_divisi' => [$divisi]], 'PEMINTA-UJI', 'uji');

        Queue::assertPushed(GenerateRelasiOrganisasiExportJob::class);
        $this->assertSame('queued', $layanan->statusUntuk($id, 'PEMINTA-UJI')['status']);
        $this->assertNull($layanan->statusUntuk($id, 'ORANG-LAIN'), 'Status hanya untuk pemintanya.');

        $layanan->process($id);

        $st = $layanan->statusUntuk($id, 'PEMINTA-UJI');
        $this->assertSame('ready', $st['status']);
        $this->assertSame(100, $st['percent']);
        $this->assertSame($st['total_records'], $st['processed_records']);
        $this->assertStringContainsString($divisi, $st['label']);

        $job = DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $id)->first();
        Storage::disk(OrganizationRelationAsyncExportService::DISK)->assertExists($job->File_Path);

        $berkas = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
        file_put_contents($berkas, Storage::disk(OrganizationRelationAsyncExportService::DISK)->get($job->File_Path));
        $buku = IOFactory::load($berkas);
        @unlink($berkas);
        $this->assertSame(['Relasi', 'Ringkasan'], $buku->getSheetNames());
        $this->assertSame($st['total_records'], $buku->getSheet(0)->getHighestDataRow() - 1 - $this->barisKepala($buku->getSheet(0)));
    }

    public function test_antrean_tidak_terjangkau_tidak_meninggalkan_tugas_menggantung(): void
    {
        Queue::shouldReceive('connection')->andThrow(new \RuntimeException('antrean mati'));
        Queue::shouldReceive('push')->andThrow(new \RuntimeException('antrean mati'));
        $layanan = app(OrganizationRelationAsyncExportService::class);

        $id = $layanan->mulai(['karyawan' => ['001|H45']], 'PEMINTA-UJI', 'uji');

        // Tercatat; diproses sesudah respons (app()->terminating) — dijalankan di sini.
        $this->app->terminate();
        $this->assertSame('ready', $layanan->statusUntuk($id, 'PEMINTA-UJI')['status']);
    }

    private function barisKepala($ws): int
    {
        // Baris data dimulai sesudah baris kepala tabel; cari baris "Kategori" di kolom A.
        for ($r = 1; $r <= 10; $r++) {
            if (stripos((string) $ws->getCell("A{$r}")->getValue(), 'kategori') !== false) {
                return $r - 1;
            }
        }

        return 0;
    }
}
