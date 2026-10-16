<?php

namespace Tests\Feature\LMS\SOP\Export;

use App\Services\LMS\SOP\Export\SopReportExportService;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportExportServiceTest extends TestCase
{
    private function payload(): array
    {
        return [
            'module' => 'sop',
            'template_kode' => 'sop-sistem-rinci',
            'judul' => 'RINCIAN KEPATUHAN SOP',
            'definisi' => [
                'dataset' => 'kepatuhan',
                'mode' => 'rinci',
                'kolom' => ['kode', 'nama', 'paket', 'item', 'status_item'],
                'saringan' => [['field' => 'kode_karyawan', 'op' => 'sama_dengan', 'nilai' => 'TIDAK-ADA-ORANG-INI']],
            ],
            'lingkup' => [
                'kode_karyawan' => 'UJIX01',
                'admin_penuh' => true,
                'anggota_tim' => [],
                'boleh_field_terbatas' => true,
            ],
        ];
    }

    protected function tearDown(): void
    {
        DB::table(AbstractExportService::TABLE)->where('Requested_By', 'UJIX01')->delete();
        parent::tearDown();
    }

    public function test_job_menghasilkan_berkas_dan_status_ready(): void
    {
        Storage::fake('gcs');

        $svc = app(SopReportExportService::class);
        $id = $svc->makeExportId();
        $svc->startJob($id, 'csv', $this->payload(), 0, 'UJIX01');
        $svc->process($id);

        $job = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();

        $this->assertSame('ready', $job->Status, 'Pesan galat: '.($job->Error_Message ?? '-'));
        $this->assertNotNull($job->File_Path);
        Storage::disk('gcs')->assertExists($job->File_Path);
    }

    public function test_format_xlsx_dan_pdf_juga_terbentuk(): void
    {
        Storage::fake('gcs');
        $svc = app(SopReportExportService::class);

        foreach (['xlsx', 'pdf'] as $format) {
            $id = $svc->makeExportId();
            $svc->startJob($id, $format, $this->payload(), 0, 'UJIX01');
            $svc->process($id);

            $job = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();

            $this->assertSame('ready', $job->Status, "Format {$format} gagal: ".($job->Error_Message ?? '-'));
            $this->assertStringEndsWith('.'.$format, $job->File_Path);
        }
    }

    /**
     * Cloud Tasks bisa mengirim ulang permintaan yang sebenarnya sudah sukses
     * (mis. handler kena timeout proxy). Tanpa guard, job 'ready' ditimpa jadi
     * 'processing' dan berkas lamanya hilang -> unduhan 404.
     */
    public function test_proses_ulang_tidak_menimpa_job_yang_sudah_ready(): void
    {
        Storage::fake('gcs');

        $svc = app(SopReportExportService::class);
        $id = $svc->makeExportId();
        $svc->startJob($id, 'csv', $this->payload(), 0, 'UJIX01');
        $svc->process($id);

        $pertama = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();
        $svc->process($id);
        $kedua = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();

        $this->assertSame($pertama->File_Path, $kedua->File_Path);
        $this->assertSame('ready', $kedua->Status);
    }

    public function test_delivery_duplikat_tidak_mengklaim_job_yang_sedang_processing(): void
    {
        Storage::fake('gcs');

        $svc = app(SopReportExportService::class);
        $id = $svc->makeExportId();
        $svc->startJob($id, 'csv', $this->payload(), 0, 'UJIX01');
        DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->update([
            'Status' => AbstractExportService::STATUS_PROCESSING,
            'Processed_Records' => 17,
            'Percent' => 23,
        ]);

        $svc->process($id);

        $job = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();
        $this->assertSame(AbstractExportService::STATUS_PROCESSING, $job->Status);
        $this->assertSame(17, (int) $job->Processed_Records);
        $this->assertSame(23, (int) $job->Percent);
        $this->assertNull($job->File_Path);
    }

    public function test_kegagalan_dilepas_ke_queued_dan_dilempar_untuk_retry_job(): void
    {
        Storage::fake('gcs');

        $svc = app(SopReportExportService::class);
        $id = $svc->makeExportId();
        $payload = $this->payload();
        $payload['module'] = 'module-yang-tidak-terdaftar';
        $svc->startJob($id, 'csv', $payload, 0, 'UJIX01');

        try {
            $svc->process($id);
            $this->fail('Exception dataset harus diteruskan ke Laravel Job.');
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $job = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();
        $this->assertSame(AbstractExportService::STATUS_QUEUED, $job->Status);
        $this->assertStringContainsString('dicoba kembali', (string) $job->Error_Message);
        $this->assertNull($job->File_Path);
    }

    public function test_job_dibatalkan_tidak_menjadi_ready(): void
    {
        Storage::fake('gcs');

        $svc = app(SopReportExportService::class);
        $id = $svc->makeExportId();
        $svc->startJob($id, 'csv', $this->payload(), 0, 'UJIX01');

        $p = $this->payload();
        $p['aborted'] = true;
        DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)
            ->update(['Payload_Json' => json_encode($p), 'Status' => 'queued']);

        $svc->process($id);

        $this->assertNotSame('ready', DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->value('Status'));
    }

    /**
     * Template resmi 'rinci' deskripsinya berbunyi "dibuat untuk di-pivot di
     * Excel". Selama SELURUH sel dipaksa TYPE_STRING, PivotTable tidak bisa
     * menjumlah satu kolom pun — berkasnya terlihat benar dan tak berguna.
     */
    public function test_kolom_ukuran_ditulis_sebagai_angka_di_xlsx(): void
    {
        Storage::fake('gcs');

        $p = $this->payload();
        $p['definisi'] = [
            'dataset' => 'kepatuhan',
            'mode' => 'ringkasan',
            'dimensi' => ['distribusi'],
            'ukuran' => ['jml_item'],
        ];

        $svc = app(SopReportExportService::class);
        $id = $svc->makeExportId();
        $svc->startJob($id, 'xlsx', $p, 0, 'UJIX01');
        $svc->process($id);

        $job = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();
        $this->assertSame('ready', $job->Status, 'Pesan galat: '.($job->Error_Message ?? '-'));

        $tmp = tempnam(sys_get_temp_dir(), 'ujixlsx').'.xlsx';
        file_put_contents($tmp, Storage::disk('gcs')->get($job->File_Path));

        try {
            $sheet = IOFactory::load($tmp)->getActiveSheet();

            // Baris 4 kepala tabel, baris 5 baris data pertama.
            $this->assertSame(
                DataType::TYPE_STRING,
                $sheet->getCell('A5')->getDataType(),
                'Kolom dimensi harus tetap teks.'
            );
            $this->assertSame(
                DataType::TYPE_NUMERIC,
                $sheet->getCell('B5')->getDataType(),
                'Kolom ukuran harus ditulis sebagai angka agar bisa di-pivot.'
            );
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Nama kustom KOSONG harus mundur ke judul, bukan diperlakukan sebagai nama.
     *
     * Kolom "nama kustom" di Export Studio mengirim string kosong ketika tak
     * diisi. `??` hanya mundur pada null, sehingga nama berkasnya menjadi
     * "_20260826_070656.xlsx" — diawali garis bawah, tanpa petunjuk isi berkas —
     * dan entri Pusat Aktivitas berlabel " · XLSX" tanpa nama sama sekali.
     */
    public function test_nama_kustom_kosong_mundur_ke_judul(): void
    {
        Storage::fake('gcs');

        $svc = app(SopReportExportService::class);
        $id = $svc->makeExportId();
        $svc->startJob($id, 'csv', ['custom_name' => ''] + $this->payload(), 0, 'UJIX01');
        $svc->process($id);

        $job = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();

        $this->assertStringStartsNotWith('_', $job->File_Name, 'Nama berkas tak boleh diawali garis bawah.');
        $this->assertStringStartsWith('rincian_kepatuhan_sop_', $job->File_Name);
    }

    public function test_nama_kustom_terisi_dipakai_apa_adanya(): void
    {
        Storage::fake('gcs');

        $svc = app(SopReportExportService::class);
        $id = $svc->makeExportId();
        $svc->startJob($id, 'csv', ['custom_name' => 'Rekap Mingguan'] + $this->payload(), 0, 'UJIX01');
        $svc->process($id);

        $job = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();

        $this->assertStringStartsWith('rekap_mingguan_', $job->File_Name);
    }
}
