<?php

namespace App\Http\Controllers\VISITOR\Workflow\Reporting\Export;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\VISITOR\Workflow\Reporting\Export\RunVisitorReportRequest;
use App\Http\Requests\VISITOR\Workflow\Reporting\Export\SaveVisitorReportTemplateRequest;
use App\Services\SYSTEM\Application\Reports\AgregatRacikan;
use App\Services\SYSTEM\Application\Reports\DatasetDenganAgregatRacikan;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\DefinisiValidator;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\PenyediaOpsiSaringan;
use App\Services\SYSTEM\Application\Reports\ReportRunner;
use App\Services\SYSTEM\Application\Reports\ReportTemplateService;
use App\Services\VISITOR\Workflow\Errors\VisitorApiFailure;
use App\Services\VISITOR\Workflow\Reporting\Export\VisitorReportExportService;
use App\Services\VISITOR\Workflow\Reporting\Export\VisitorSystemTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use RuntimeException;

/** Halaman & API Export Studio Visitor, page registry visitorExportStudioPage. */
class VisitorExportStudioController extends Controller
{
    private const MODULE = 'visitor';

    private const DATASETS = ['tamu', 'permit'];

    public function __construct(
        private DatasetRegistry $registry,
        private DefinisiValidator $validator,
        private ReportRunner $runner,
        private VisitorReportExportService $exportService,
    ) {}

    /**
     * Halaman Export Studio.
     *
     * Sejak dipisah dari /laporanvisitor, Studio berdiri sendiri: Monitoring
     * mewarisi URL dan kunci lama, Studio memakai visitorExportStudioPage.
     */
    /**
     * URL lama studio gabungan.
     *
     * Export Studio dipecah per dataset (DML-2026-106). Rute ini dipertahankan
     * sebagai pengalihan supaya tautan dan bookmark lama tetap mendarat.
     */
    public function halaman()
    {
        return redirect('/visitor/export-studio/tamu');
    }

    public function halamanTamu()
    {
        return $this->halamanDataset('tamu');
    }

    public function halamanPermit()
    {
        return $this->halamanDataset('permit');
    }

    /**
     * Satu komponen halaman, dua dataset.
     *
     * Datasetnya DIPAKU dari server, bukan dipilih di layar: pemisahan ini soal
     * siapa boleh merakit laporan domain mana, dan tombol pengganti dataset
     * yang tetap ada akan memanggil endpoint domain lain — dibalas 403 tanpa
     * sebab yang terbaca pemakainya.
     *
     * Komponennya sengaja TIDAK digandakan: isinya ratusan baris gaya perakit
     * yang identik, dan salinan kedua akan menua sendiri-sendiri.
     */
    private function halamanDataset(string $dataset)
    {
        return Inertia::render('VISITOR/Workflow/Reporting/ExportStudio/exportStudioVisitor', [
            'baseUrl' => '/api/v1/visitor/export-studio',
            'datasetAwal' => $dataset,
            'datasetTerkunci' => true,
        ]);
    }

    /**
     * Dataset katalog + ukuran racikan pengguna, sebagai satu katalog.
     *
     * Dipakai SETIAP jalur yang memvalidasi atau menjalankan definisi. Satu
     * jalur yang lupa membungkusnya akan menolak ukuran racikan sebagai "tidak
     * dikenal" — template yang tersimpan baik-baik saja tiba-tiba gagal dijalankan,
     * dan galatnya menunjuk ke kolom yang barusan dipilih pemakainya sendiri.
     */
    private function datasetUntuk(string $dataset, DefinisiLaporan $definisi): DatasetLaporan
    {
        return DatasetDenganAgregatRacikan::dari(
            $this->registry->ambil(self::MODULE, $dataset),
            $definisi->agregat,
        );
    }

    /**
     * PARAMETER DIBACA DARI RUTE, BUKAN DARI URUTAN ARGUMEN.
     *
     * Aksi controller dipanggil lewat `Controller::callAction()` yang memakai
     * `array_values($parameters)` — posisional, mengikuti urutan parameter
     * RUTE. Di rute ini `studioDataset` datang dari `->defaults(...)`, dan
     * default selalu menyusul SESUDAH parameter URI, sehingga urutannya
     * `[dataset, studioDataset]`. Tanda tangan lama menerima keduanya
     * TERTUKAR; karena dua-duanya string, PHP tidak protes dan salahnya diam.
     */
    public function katalog(Request $request)
    {
        $studioDataset = (string) $request->route('studioDataset');
        $dataset = (string) $request->route('dataset');

        if ($studioDataset !== $dataset || ! $this->datasetValid($dataset)) {
            return $this->datasetUnknown();
        }

        $ds = $this->registry->ambil(self::MODULE, $dataset);

        return ResponseHelper::success([
            'kunci' => $ds->kunci(),
            'label' => $ds->label(),
            'grain' => $ds->grain(),
            'dimensi' => $ds->dimensi(),
            // Fungsi agregat yang sah PER KOLOM, dari server.
            //
            // Layar tidak boleh menyimpulkannya sendiri: dua definisi "fungsi
            // yang sah" akan menyimpang, dan yang menyimpang di sisi layar
            // berarti pemakainya menyusun laporan yang baru ditolak setelah
            // ditekan.
            'agregat' => AgregatRacikan::katalog($ds->dimensi()),
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

    public function simpan(string $studioDataset, SaveVisitorReportTemplateRequest $request)
    {
        $definisi = DefinisiLaporan::dariArray((array) $request->input('definisi', []));
        if (! $this->definitionMatches($studioDataset, $definisi)) {
            return $this->datasetMismatch();
        }

        $galat = $this->validator->periksa($definisi, $this->datasetUntuk($studioDataset, $definisi));
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

    public function perbarui(SaveVisitorReportTemplateRequest $request)
    {
        $studioDataset = (string) $request->route('studioDataset');
        $kode = (string) $request->route('kode');

        $definisi = DefinisiLaporan::dariArray((array) $request->input('definisi', []));
        if (! $this->definitionMatches($studioDataset, $definisi)) {
            return $this->datasetMismatch();
        }

        $galat = $this->validator->periksa($definisi, $this->datasetUntuk($studioDataset, $definisi));
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

    public function duplikat(Request $request)
    {
        $studioDataset = (string) $request->route('studioDataset');
        $kode = (string) $request->route('kode');

        try {
            $this->assertTemplateDataset($studioDataset, $kode);
            $baru = $this->templates($studioDataset)->duplikat($kode, $this->kodeKaryawan());
        } catch (RuntimeException $e) {
            return $this->templateForbidden();
        }

        return ResponseHelper::success(['kode' => $baru], 'Template diduplikat.', 201);
    }

    public function hapus(Request $request)
    {
        $studioDataset = (string) $request->route('studioDataset');
        $kode = (string) $request->route('kode');

        try {
            $this->assertTemplateDataset($studioDataset, $kode);
            $this->templates($studioDataset)->nonaktifkan($kode, $this->kodeKaryawan());
        } catch (RuntimeException $e) {
            return $this->templateForbidden();
        }

        return ResponseHelper::success(null, 'Template dinonaktifkan.');
    }

    public function pratinjau(string $studioDataset, RunVisitorReportRequest $request)
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

        $ds = $this->datasetUntuk($studioDataset, $definisi);
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
            Log::error('VISITOR_REPORT_PREVIEW_FAILED', [
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

    public function jalankan(string $studioDataset, RunVisitorReportRequest $request)
    {
        try {
            $templateKode = $request->input('template_kode');
            $sumber = $templateKode
                ? $this->templateForDataset($studioDataset, $templateKode)
                : ['nama' => $studioDataset === 'tamu' ? 'Laporan Buku Tamu' : 'Laporan Permit Kendaraan', 'definisi' => $request->input('definisi', [])];
        } catch (RuntimeException $e) {
            return $this->templateForbidden();
        }

        $definisi = DefinisiLaporan::dariArray((array) ($sumber['definisi'] ?? []));
        if (! $this->definitionMatches($studioDataset, $definisi)) {
            return $this->datasetMismatch();
        }

        $galat = $this->validator->periksa($definisi, $this->datasetUntuk($studioDataset, $definisi));
        if ($galat !== []) {
            return $this->definitionInvalid($galat);
        }

        $lingkup = $this->lingkup();
        $exportId = $this->exportService->makeExportId();
        $customName = trim((string) $request->input('custom_name', ''));
        $nama = $customName !== '' ? $customName : (string) ($sumber['nama'] ?? 'Laporan Visitor');

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
                Log::error('VISITOR_REPORT_DISPATCH_STATUS_FAILED', [
                    'export_id' => $exportId,
                    'exception' => $markFailure,
                ]);
            }

            return VisitorApiFailure::respond(
                $e,
                'report_export_dispatch',
                'VISITOR_REPORT_DISPATCH_FAILED',
                'Laporan gagal masuk antrean. Silakan coba lagi.',
                ['export_id' => $exportId],
            );
        }

        return ResponseHelper::success(['task_id' => $exportId], 'Laporan diproses. Cek panel Aktivitas.', 202);
    }

    private function templates(string $dataset): ReportTemplateService
    {
        return new ReportTemplateService(
            self::MODULE,
            $this->registry,
            $this->validator,
            VisitorSystemTemplates::untuk($dataset),
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
}
