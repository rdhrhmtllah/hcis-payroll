<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Export;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\KPI\PerformanceAppraisal\Export\RunPaReportRequest;
use App\Http\Requests\KPI\PerformanceAppraisal\Export\SavePaReportTemplateRequest;
use App\Services\KPI\PerformanceAppraisal\Export\PaReportExportService;
use App\Services\KPI\PerformanceAppraisal\Export\PaSystemTemplates;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\DefinisiValidator;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\PenyediaOpsiSaringan;
use App\Services\SYSTEM\Application\Reports\ReportRunner;
use App\Services\SYSTEM\Application\Reports\ReportTemplateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RuntimeException;

/** Halaman & API Export Studio Performance Appraisal, page registry PaExportStudioPage. */
class PaExportStudioController extends Controller
{
    private const MODULE = 'pa';

    private const DATASETS = ['hasil', 'penugasan', 'kompetensi', 'peserta'];

    /**
     * Nama laporan bawaan per dataset, dipakai saat jalankan() tidak memakai
     * template (tidak ada 'nama' dari templateForDataset()) -- nama ini
     * mengalir ke judul job di Pusat Aktivitas DAN ke nama berkas ekspor.
     *
     * Dulu ternary dua-cabang (`$studioDataset === 'hasil' ? ... : ...`):
     * benar untuk dua dataset, tapi diam-diam salah begitu dataset ketiga
     * terdaftar (mis. tahap 2: kompetensi) -- kompetensi akan ikut dinamai
     * "Laporan Penugasan Performance Appraisal" tanpa satu pun galat. Peta
     * di sini WAJIB berkunci sama persis dengan self::DATASETS.
     */
    private const DATASET_LABELS = [
        'hasil' => 'Laporan Hasil Performance Appraisal',
        'penugasan' => 'Laporan Penugasan Performance Appraisal',
        'kompetensi' => 'Laporan Kompetensi Performance Appraisal',
        'peserta' => 'Laporan Peserta Performance Appraisal',
    ];

    public function __construct(
        private DatasetRegistry $registry,
        private DefinisiValidator $validator,
        private ReportRunner $runner,
        private PaReportExportService $exportService,
    ) {}

    /** Halaman Export Studio. */
    public function halaman()
    {
        return Inertia::render('KPI/PerformanceAppraisal/Export/PaExportStudioPage', [
            'baseUrl' => '/api/v1/performance-appraisal/export-studio',
        ]);
    }

    public function katalog(string $studioDataset, string $dataset)
    {
        if ($studioDataset !== $dataset || ! $this->datasetValid($dataset)) {
            return $this->datasetUnknown();
        }

        $ds = $this->registry->ambil(self::MODULE, $dataset);

        return ResponseHelper::success([
            'kunci' => $ds->kunci(),
            'label' => $ds->label(),
            'grain' => $ds->grain(),
            'dimensi' => $ds->dimensi(),
            'ukuran' => $ds->ukuran(),
            'saringan' => $ds->saringan(),
            'opsi_saringan' => $ds instanceof PenyediaOpsiSaringan ? $ds->opsiSaringan() : [],
        ]);
    }

    public function galeri(string $studioDataset)
    {
        if (! $this->datasetValid($studioDataset)) {
            return $this->datasetUnknown();
        }

        return ResponseHelper::success(
            $this->filterGaleri($this->templates($studioDataset)->galeri($this->kodeKaryawan()), $studioDataset)
        );
    }

    public function simpan(string $studioDataset, SavePaReportTemplateRequest $request)
    {
        $definisi = DefinisiLaporan::dariArray((array) $request->input('definisi', []));
        if (! $this->definitionMatches($studioDataset, $definisi)) {
            return $this->datasetMismatch();
        }

        $galat = $this->validator->periksa($definisi, $this->registry->ambil(self::MODULE, $studioDataset));
        if ($galat !== []) {
            return $this->definitionInvalid($galat);
        }

        $kode = $this->templates($studioDataset)->simpan([
            'nama' => (string) $request->input('nama'),
            'deskripsi' => $request->input('deskripsi'),
            'definisi' => $definisi->toArray(),
            'tingkat_bagi' => $request->input('tingkat_bagi', 'PRIBADI'),
        ], $this->kodeKaryawan());

        return ResponseHelper::success(['kode' => $kode], 'Template tersimpan.', 201);
    }

    public function perbarui(string $studioDataset, string $kode, SavePaReportTemplateRequest $request)
    {
        $definisi = DefinisiLaporan::dariArray((array) $request->input('definisi', []));
        if (! $this->definitionMatches($studioDataset, $definisi)) {
            return $this->datasetMismatch();
        }

        $galat = $this->validator->periksa($definisi, $this->registry->ambil(self::MODULE, $studioDataset));
        if ($galat !== []) {
            return $this->definitionInvalid($galat);
        }

        try {
            $this->assertTemplateDataset($studioDataset, $kode);
            $tersimpan = $this->templates($studioDataset)->perbarui($kode, [
                'nama' => (string) $request->input('nama'),
                'deskripsi' => $request->input('deskripsi'),
                'definisi' => $definisi->toArray(),
                'tingkat_bagi' => $request->input('tingkat_bagi', 'PRIBADI'),
            ], $this->kodeKaryawan());
        } catch (RuntimeException $e) {
            return $this->templateForbidden();
        }

        return ResponseHelper::success(['kode' => $tersimpan], 'Template diperbarui.');
    }

    public function duplikat(string $studioDataset, string $kode)
    {
        try {
            $this->assertTemplateDataset($studioDataset, $kode);
            $baru = $this->templates($studioDataset)->duplikat($kode, $this->kodeKaryawan());
        } catch (RuntimeException $e) {
            return $this->templateForbidden();
        }

        return ResponseHelper::success(['kode' => $baru], 'Template diduplikat.', 201);
    }

    public function hapus(string $studioDataset, string $kode)
    {
        try {
            $this->assertTemplateDataset($studioDataset, $kode);
            $this->templates($studioDataset)->nonaktifkan($kode, $this->kodeKaryawan());
        } catch (RuntimeException $e) {
            return $this->templateForbidden();
        }

        return ResponseHelper::success(null, 'Template dinonaktifkan.');
    }

    public function pratinjau(string $studioDataset, RunPaReportRequest $request)
    {
        try {
            $definisi = $this->resolveDefinition($studioDataset, $request->input('template_kode'), (array) $request->input('definisi', []));
        } catch (RuntimeException $e) {
            return $this->templateForbidden();
        }

        if (! $this->definitionMatches($studioDataset, $definisi)) {
            return $this->datasetMismatch();
        }

        $sortKey = $request->input('sort_key');
        if ($sortKey) {
            $definisi = $definisi->denganUrut([[
                'key' => $sortKey,
                'arah' => $request->input('sort_order', 'asc') === 'desc' ? 'desc' : 'asc',
            ]]);
        }

        $ds = $this->registry->ambil(self::MODULE, $studioDataset);
        $galat = $this->validator->periksa($definisi, $ds);
        if ($galat !== []) {
            return $this->definitionInvalid($galat);
        }

        try {
            $hasil = $this->runner->jalankanPratinjau(
                $definisi,
                $ds,
                $this->lingkup(),
                'PRATINJAU',
                (int) $request->input('page', 1),
                (int) $request->input('per_page', 24),
                $request->input('search'),
                $request->has('hitung_total') ? $request->boolean('hitung_total') : true,
            );
        } catch (\Throwable $e) {
            Log::error('PA_REPORT_PREVIEW_FAILED', [
                'module' => self::MODULE,
                'dataset' => $studioDataset,
                'definition_hash' => md5((string) json_encode($definisi->toArray())),
                'exception' => $e,
            ]);

            return ResponseHelper::errorWithCode(
                'Pratinjau gagal diproses. Sederhanakan saringan atau kurangi kolom, lalu coba lagi.',
                'REPORT_PREVIEW_FAILED',
                500
            );
        }

        return ResponseHelper::success($hasil);
    }

    public function jalankan(string $studioDataset, RunPaReportRequest $request)
    {
        try {
            $templateKode = $request->input('template_kode');
            $sumber = $templateKode
                ? $this->templateForDataset($studioDataset, $templateKode)
                : ['nama' => self::DATASET_LABELS[$studioDataset] ?? 'Laporan Performance Appraisal', 'definisi' => $request->input('definisi', [])];
        } catch (RuntimeException $e) {
            return $this->templateForbidden();
        }

        $definisi = DefinisiLaporan::dariArray((array) ($sumber['definisi'] ?? []));
        if (! $this->definitionMatches($studioDataset, $definisi)) {
            return $this->datasetMismatch();
        }

        $galat = $this->validator->periksa($definisi, $this->registry->ambil(self::MODULE, $studioDataset));
        if ($galat !== []) {
            return $this->definitionInvalid($galat);
        }

        $lingkup = $this->lingkup();
        $exportId = $this->exportService->makeExportId();
        $customName = trim((string) $request->input('custom_name', ''));
        $nama = $customName !== '' ? $customName : (string) ($sumber['nama'] ?? 'Laporan Performance Appraisal');

        try {
            $this->exportService->startJob($exportId, (string) $request->input('format', $definisi->formatBawaan), [
                'module' => self::MODULE,
                'template_kode' => $request->input('template_kode'),
                'nama' => $nama,
                'judul' => $nama,
                'custom_name' => $customName,
                'definisi' => $definisi->toArray(),
                'lingkup' => [
                    'kode_karyawan' => $lingkup->kodeKaryawan,
                    'admin_penuh' => true,
                    'anggota_tim' => [],
                    'boleh_field_terbatas' => true,
                ],
            ], 0, $lingkup->kodeKaryawan);

            $this->exportService->dispatchJob($exportId);
        } catch (\Throwable $e) {
            try {
                $this->exportService->markFailed($exportId, 'Laporan gagal masuk antrean. Silakan coba lagi.');
            } catch (\Throwable $markFailure) {
                Log::error('PA_REPORT_DISPATCH_STATUS_FAILED', [
                    'export_id' => $exportId,
                    'exception' => $markFailure,
                ]);
            }

            return $this->dispatchFailed($e, $exportId);
        }

        return ResponseHelper::success(['task_id' => $exportId], 'Laporan diproses. Cek panel Aktivitas.', 202);
    }

    private function templates(string $dataset): ReportTemplateService
    {
        return new ReportTemplateService(
            self::MODULE,
            $this->registry,
            $this->validator,
            PaSystemTemplates::untuk($dataset),
        );
    }

    private function resolveDefinition(string $dataset, ?string $templateKode, array $raw): DefinisiLaporan
    {
        if ($templateKode) {
            $template = $this->templateForDataset($dataset, $templateKode);

            return DefinisiLaporan::dariArray((array) ($template['definisi'] ?? []));
        }

        return DefinisiLaporan::dariArray($raw);
    }

    private function templateForDataset(string $dataset, string $kode): array
    {
        $template = $this->templates($dataset)->ambil($kode, $this->kodeKaryawan());
        $templateDataset = (string) ($template['dataset'] ?? ($template['definisi']['dataset'] ?? ''));
        if ($templateDataset !== $dataset) {
            throw new RuntimeException('Template berada pada dataset berbeda.');
        }

        return $template;
    }

    private function assertTemplateDataset(string $dataset, string $kode): void
    {
        $this->templateForDataset($dataset, $kode);
    }

    private function filterGaleri(array $galeri, string $dataset): array
    {
        foreach (['resmi', 'saya', 'dibagikan'] as $kelompok) {
            $galeri[$kelompok] = array_values(array_filter(
                (array) ($galeri[$kelompok] ?? []),
                function ($template) use ($dataset) {
                    if (is_object($template)) {
                        return (string) ($template->Dataset_Key ?? '') === $dataset;
                    }

                    return (string) ($template['dataset'] ?? $template['Dataset_Key'] ?? '') === $dataset;
                }
            ));
        }

        return $galeri;
    }

    private function lingkup(): Lingkup
    {
        return new Lingkup($this->kodeKaryawan(), true, [], true);
    }

    private function kodeKaryawan(): string
    {
        return (string) optional(Auth::user()->karyawan)->Kode_Karyawan;
    }

    private function datasetValid(string $dataset): bool
    {
        return in_array($dataset, self::DATASETS, true);
    }

    private function definitionMatches(string $dataset, DefinisiLaporan $definisi): bool
    {
        return $this->datasetValid($dataset) && $definisi->dataset === $dataset;
    }

    private function datasetUnknown()
    {
        return ResponseHelper::errorWithCode('Dataset tidak dikenal.', 'REPORT_DATASET_UNKNOWN', 404);
    }

    private function datasetMismatch()
    {
        return ResponseHelper::errorWithCode('Dataset definisi tidak sesuai dengan Studio aktif.', 'REPORT_DATASET_MISMATCH', 422);
    }

    private function definitionInvalid(array $errors)
    {
        return ResponseHelper::errorWithCode('Definisi laporan tidak sah.', 'REPORT_DEFINITION_INVALID', 422, $errors);
    }

    private function templateForbidden()
    {
        return ResponseHelper::errorWithCode('Template tidak ditemukan atau tidak dapat diakses.', 'REPORT_TEMPLATE_FORBIDDEN', 403);
    }

    /**
     * Tiruan `VisitorApiFailure::respond()` yang di-inline: tugas ini dibatasi
     * hanya boleh membuat controller, request, dan berkas rute -- menambah
     * kelas Errors/PaApiFailure baru berarti keluar dari cakupan yang diminta.
     */
    private function dispatchFailed(\Throwable $exception, string $exportId)
    {
        $requestId = (string) Str::uuid();

        Log::error('PA_API_OPERATION_FAILED', [
            'export_id' => $exportId,
            'request_id' => $requestId,
            'module' => self::MODULE,
            'operation' => 'report_export_dispatch',
            'error_code' => 'PA_REPORT_DISPATCH_FAILED',
            'exception' => $exception,
        ]);

        return ResponseHelper::errorWithCode(
            'Laporan gagal masuk antrean. Silakan coba lagi.',
            'PA_REPORT_DISPATCH_FAILED',
            500,
            [],
            $requestId,
        );
    }
}
