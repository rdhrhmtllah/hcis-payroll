<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting\Export;

use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use App\Services\SYSTEM\Application\Reports\ReportRunner;
use App\Services\SYSTEM\Application\Reports\TautanBerkasRegistry;
use App\Services\VISITOR\Workflow\Reporting\Export\TautanBerkasVisitor;
use App\Services\VISITOR\Workflow\Reporting\Export\VisitorReportExportService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tautan berkas di hasil export (keputusan user 30 Sep 2026, opsi 2):
 * dibuka TANPA login, lewat domain HCIS, berlaku 1 hari, dan URL GCS
 * tidak pernah terlihat.
 */
class TautanBerkasVisitorTest extends TestCase
{
    private int $idDokumen;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $dok = DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')
            ->whereRaw("LTRIM(RTRIM(ISNULL(Path_File_URL, ''))) <> ''")
            ->orderByDesc('Id_Permit_Dokumen')
            ->first(['Id_Permit_Dokumen', 'Path_File_URL']);

        if (! $dok) {
            $this->markTestSkipped('Butuh satu dokumen permit berpath di dev.');
        }

        $this->idDokumen = (int) $dok->Id_Permit_Dokumen;
        $this->path = trim($dok->Path_File_URL);

        Storage::fake('gcs');
        Storage::disk('gcs')->put($this->path, 'ISI-FOTO-UJI');
    }

    protected function tearDown(): void
    {
        DB::table(AbstractExportService::TABLE)->where('Requested_By', 'UJITAUTAN')->delete();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_tautan_lewat_domain_hcis_dan_terbuka_tanpa_login(): void
    {
        $url = $this->tautan();

        $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/berkas-visitor/dokumen-permit/'.$this->idDokumen, $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringNotContainsString($this->path, $url, 'Path objek GCS bocor di tautan.');

        $res = $this->get($this->relatif($url));

        $res->assertOk();
        $this->assertSame('ISI-FOTO-UJI', $res->streamedContent());
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('googleapis', json_encode($res->headers->all()), 'Alamat GCS bocor lewat header.');
    }

    public function test_tautan_kedaluwarsa_setelah_satu_hari(): void
    {
        $url = $this->tautan();

        Carbon::setTestNow(now()->addHours(TautanBerkasVisitor::MASA_BERLAKU_JAM - 1));
        $this->get($this->relatif($url))->assertOk();

        Carbon::setTestNow(now()->addHours(2));
        $this->get($this->relatif($url))->assertStatus(410)->assertSee('export ulang');
    }

    public function test_id_yang_diganti_ditolak(): void
    {
        $palsu = str_replace('/'.$this->idDokumen.'?', '/'.($this->idDokumen + 1).'?', $this->relatif($this->tautan()));

        $this->get($palsu)->assertStatus(403);
        $this->get('/berkas-visitor/dokumen-permit/'.$this->idDokumen)->assertStatus(403);
    }

    public function test_kolom_export_berformat_tautan_menjadi_tautan_bertanda_tangan(): void
    {
        $registry = app(TautanBerkasRegistry::class);
        $this->assertTrue($registry->punya('visitor_dokumen_permit'));
        $this->assertTrue($registry->punya('visitor_foto_tamu'));
        $this->assertNull($registry->buat('tidak_dikenal', '1'));

        $format = new \ReflectionMethod(ReportRunner::class, 'formatNilai');
        $format->setAccessible(true);
        $runner = app(ReportRunner::class);

        $nilai = $format->invoke($runner, (string) $this->idDokumen, 'tautan:visitor_dokumen_permit');
        $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/berkas-visitor/', $nilai);
        $this->get($this->relatif($nilai))->assertOk();

        // Tanpa berkas: tanda kosong biasa, bukan tautan ke ID kosong.
        $this->assertSame('-', $format->invoke($runner, null, 'tautan:visitor_dokumen_permit'));
    }

    public function test_xlsx_menulis_tautan_sebagai_hyperlink_yang_bisa_diklik(): void
    {
        $permit = DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen as dk')
            ->join('N_VISITOR_Permit_Efektif as e', 'e.No_Transaksi', '=', 'dk.No_Transaksi')
            ->where('e.Jenis_Akses', 'PERMIT')
            ->where('dk.Jenis_Dokumen', 'STNK')
            ->whereRaw("LTRIM(RTRIM(ISNULL(dk.Path_File_URL, ''))) <> ''")
            ->value('dk.No_Transaksi');

        if (! $permit) {
            $this->markTestSkipped('Butuh satu permit efektif ber-STNK di dev.');
        }

        $svc = app(VisitorReportExportService::class);
        $id = $svc->makeExportId();
        $svc->startJob($id, 'xlsx', [
            'module' => 'visitor',
            'judul' => 'UJI TAUTAN',
            'definisi' => [
                'dataset' => 'permit',
                'mode' => 'rinci',
                'kolom' => ['no_transaksi', 'tautan_stnk'],
            ],
            'lingkup' => ['kode_karyawan' => 'UJITAUTAN', 'admin_penuh' => true, 'anggota_tim' => [], 'boleh_field_terbatas' => true],
        ], 0, 'UJITAUTAN');
        $svc->process($id);

        $job = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();
        $this->assertSame('ready', $job->Status, 'Pesan galat: '.($job->Error_Message ?? '-'));

        $tmp = tempnam(sys_get_temp_dir(), 'ujitautan').'.xlsx';
        file_put_contents($tmp, Storage::disk('gcs')->get($job->File_Path));

        try {
            $sheet = IOFactory::load($tmp)->getActiveSheet();
            $tautan = null;
            foreach ($sheet->getRowIterator() as $baris) {
                foreach ($baris->getCellIterator() as $sel) {
                    if ($sel->getValue() === 'Lihat berkas') {
                        $tautan = $sel->getHyperlink()->getUrl();
                    }
                }
            }

            $this->assertNotNull($tautan, 'Kolom tautan tidak ditulis sebagai hyperlink.');
            $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/berkas-visitor/dokumen-permit/', $tautan);
            $this->assertStringNotContainsString('googleapis', $tautan);
        } finally {
            @unlink($tmp);
        }
    }

    private function tautan(): string
    {
        return (string) app(TautanBerkasVisitor::class)->buat(TautanBerkasVisitor::DOKUMEN_PERMIT, (string) $this->idDokumen);
    }

    private function relatif(string $url): string
    {
        return substr($url, strlen(rtrim(config('app.url'), '/')));
    }
}
