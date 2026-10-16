<?php

namespace App\Http\Controllers\VISITOR\Governance\MasterData;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\VISITOR\Governance\MasterData\ChangeVisitorMasterStatusRequest;
use App\Http\Requests\VISITOR\Governance\MasterData\ExportVisitorMasterDataRequest;
use App\Http\Requests\VISITOR\Governance\MasterData\ImportVisitorMasterDataRequest;
use App\Http\Requests\VISITOR\Governance\MasterData\ListVisitorMasterDataRequest;
use App\Http\Requests\VISITOR\Governance\MasterData\SaveVisitorMasterDataRequest;
use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataExportService;
use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataImportService;
use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use LogicException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class VisitorMasterDataController extends Controller
{
    public function __construct(
        private VisitorMasterDataService $service,
        private VisitorMasterDataExportService $exportService,
        private VisitorMasterDataImportService $importService,
    ) {}

    /**
     * URL lama halaman gabungan.
     *
     * Master Data dipecah per domain (DML-2026-107); rute ini dipertahankan
     * sebagai pengalihan supaya tautan dan bookmark lama tetap mendarat.
     */
    public function page()
    {
        return redirect('/admin/visitor/master-data/permit');
    }

    public function pagePermit()
    {
        return $this->halamanDomain('permit', 'Master Data Permit', 'Kelola referensi operasional yang dipakai pengajuan permit kendaraan dan checklist verifikasinya.', 'permit');
    }

    public function pageTamu()
    {
        return $this->halamanDomain('tamu', 'Master Data Tamu', 'Kelola referensi operasional yang dipakai registrasi kunjungan tamu.');
    }

    /**
     * Satu komponen halaman, katalog yang disaring per domain.
     *
     * Penyaringnya di SERVER, bukan di layar: katalog yang dikirim utuh lalu
     * disembunyikan sebagian tetap memberi tahu halaman tamu seluruh bentuk
     * master permit, dan tab yang terlewat disembunyikan akan memanggil
     * endpoint yang menolaknya.
     */
    private function halamanDomain(string $domain, string $judul, string $subjudul, string $domainExport = '')
    {
        return Inertia::render('VISITOR/Governance/MasterData/VisitorMasterDataPage', [
            'masterCatalog' => $this->service->catalog($domain),
            'apiBaseUrl' => '/api/v1/visitor/master-data',
            'judulHalaman' => $judul,
            'subjudulHalaman' => $subjudul,
            // Kosong berarti halaman ini belum punya export. Layar tidak boleh
            // menyimpulkannya dari nama domainnya sendiri: tombol yang muncul
            // tanpa endpoint di belakangnya hanya menghasilkan 404 di tangan
            // admin.
            'domainExport' => $domainExport,
        ]);
    }

    public function index(string $master, ListVisitorMasterDataRequest $request)
    {
        try {
            $result = $this->service->list($master, $request->validated());

            return ResponseHelper::successWithPaginationV2(
                $result['items'],
                $result['page'],
                $result['limit'],
                $result['total'],
                'Berhasil',
                200,
                [
                    'relations' => $result['relations'],
                    'summary' => $result['summary'],
                    'stats' => $result['stats'],
                ],
            );
        } catch (Throwable $e) {
            return $this->failure($e, 'list', $master);
        }
    }

    /**
     * Dry run export: lembar apa saja yang akan ditulis dan berapa barisnya.
     *
     * Tidak menulis berkas dan tidak memulai unduhan. Angkanya dibangun lewat
     * jalur yang sama dengan berkas sungguhan, jadi pratinjau yang cocok
     * berarti berkasnya cocok.
     */
    public function exportPratinjau(string $domain, ExportVisitorMasterDataRequest $request)
    {
        try {
            [$master, $filters] = $this->parameterExport($request);

            return ResponseHelper::success(
                $this->exportService->pratinjau($domain, $master, $filters),
                'Pratinjau export siap.',
            );
        } catch (Throwable $e) {
            return $this->failure($e, 'export_preview', $domain);
        }
    }

    public function exportUnduh(string $domain, ExportVisitorMasterDataRequest $request)
    {
        try {
            [$master, $filters] = $this->parameterExport($request);
            $spreadsheet = $this->exportService->spreadsheet($domain, $master, $filters);
            $namaBerkas = $this->exportService->namaBerkas($domain);
        } catch (Throwable $e) {
            return $this->failure($e, 'export', $domain);
        }

        // Berkas dirakit LEBIH DULU, aliran keluar baru dimulai sesudahnya:
        // galat yang terjadi setelah respons mulai mengalir tidak bisa lagi
        // diganti menjadi JSON, dan pemakainya menerima berkas xlsx rusak
        // berisi pesan galat.
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $namaBerkas, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Dry run import: berkas dibaca dan dinilai, TIDAK ada baris yang ditulis.
     */
    public function importPratinjau(string $domain, ImportVisitorMasterDataRequest $request)
    {
        try {
            $berkas = $request->file('berkas');
            $laporan = $this->importService->pratinjau($domain, $berkas->getRealPath(), $berkas->getClientOriginalName());

            return ResponseHelper::success(
                $laporan + ['sidik' => $this->importService->sidik($berkas->getRealPath())],
                'Pratinjau import siap.',
            );
        } catch (Throwable $e) {
            return $this->failure($e, 'import_preview', $domain);
        }
    }

    public function importJalankan(string $domain, ImportVisitorMasterDataRequest $request)
    {
        try {
            $berkas = $request->file('berkas');
            $sidik = $this->importService->sidik($berkas->getRealPath());

            // Berkas yang dieksekusi harus berkas yang dipratinjau. Tanpa
            // penjaga ini, dry run hanya menjadi ritual: yang ditulis bisa
            // berkas lain yang laporannya tidak pernah dilihat siapa pun.
            if ($request->validated('sidik') !== $sidik) {
                return ResponseHelper::errorWithCode(
                    'Berkasnya berbeda dengan yang dipratinjau. Jalankan pratinjau ulang sebelum import.',
                    'VISITOR_MASTER_IMPORT_STALE',
                    409,
                );
            }

            $laporan = $this->importService->jalankan($domain, $berkas->getRealPath(), $berkas->getClientOriginalName());

            return ResponseHelper::success(
                $laporan + ['sidik' => $sidik],
                'Import selesai: '.$laporan['ringkas']['ditambah'].' baris ditambahkan.',
            );
        } catch (Throwable $e) {
            return $this->failure($e, 'import', $domain);
        }
    }

    /**
     * @return array{0: ?string, 1: array<string, mixed>}
     */
    private function parameterExport(ExportVisitorMasterDataRequest $request): array
    {
        $filters = $request->validated();
        $master = $filters['master'] ?? null;
        unset($filters['master']);

        return [$master, $filters];
    }

    public function store(string $master, SaveVisitorMasterDataRequest $request)
    {
        try {
            return ResponseHelper::success($this->service->create($master, $request->validated()), 'Data master berhasil ditambahkan.', 201);
        } catch (Throwable $e) {
            return $this->failure($e, 'create', $master);
        }
    }

    /**
     * MASTER DAN ID DIBACA DARI RUTE, BUKAN DARI URUTAN ARGUMEN.
     *
     * Laravel memanggil aksi controller lewat
     * `Controller::callAction()` yang melakukan `array_values($parameters)` —
     * artinya argumennya POSISIONAL, mengikuti urutan parameter RUTE, bukan
     * urutan tanda tangan method. Pada rute ini `master` datang dari
     * `->defaults('master', ...)`, dan default selalu ditambahkan SESUDAH
     * parameter URI, sehingga urutannya `['id' => '15', 'master' => '...']`.
     *
     * Tanda tangan lamanya `(string $master, int $id, ...)` karena itu menerima
     * '15' sebagai $master dan 'approval-question' sebagai $id, lalu PHP
     * melempar TypeError sebelum satu baris pun kode ini berjalan — di luar
     * blok try, jadi yang sampai ke layar hanya 500 "Server Error" tanpa satu
     * pun jejak di log aplikasi modul ini.
     *
     * Membalik urutan tanda tangan akan memperbaikinya hari ini dan pecah lagi
     * begitu rutenya ditulis ulang. Membaca lewat nama tidak bisa tertukar.
     */
    public function update(SaveVisitorMasterDataRequest $request)
    {
        [$master, $id] = $this->sasaran($request);

        try {
            return ResponseHelper::success($this->service->update($master, $id, $request->validated()), 'Data master berhasil diperbarui.');
        } catch (Throwable $e) {
            return $this->failure($e, 'update', $master, $id);
        }
    }

    public function changeStatus(ChangeVisitorMasterStatusRequest $request)
    {
        [$master, $id] = $this->sasaran($request);

        try {
            $status = (string) $request->validated('status');

            return ResponseHelper::success(
                $this->service->changeStatus($master, $id, $status),
                $status === 'Y' ? 'Data master diaktifkan.' : 'Data master dinonaktifkan.',
            );
        } catch (Throwable $e) {
            return $this->failure($e, 'change_status', $master, $id);
        }
    }

    /**
     * Master dan id baris dari rute.
     *
     * @return array{0: string, 1: int}
     */
    private function sasaran(Request $request): array
    {
        return [
            (string) $request->route('master'),
            (int) $request->route('id'),
        ];
    }

    private function failure(Throwable $e, string $operation, string $master, ?int $id = null)
    {
        if ($e instanceof DomainException) {
            $status = str_contains(strtolower($e->getMessage()), 'tidak ditemukan') ? 404 : 409;

            return ResponseHelper::errorWithCode(
                $e->getMessage(),
                $status === 404 ? 'VISITOR_MASTER_NOT_FOUND' : 'VISITOR_MASTER_CONFLICT',
                $status,
            );
        }

        if ($e instanceof LogicException) {
            return ResponseHelper::errorWithCode('Operasi tidak didukung untuk master data ini.', 'VISITOR_MASTER_OPERATION_NOT_SUPPORTED', 422);
        }

        Log::error('VISITOR_MASTER_DATA_FAILED', [
            'module' => 'VISITOR',
            'feature' => 'Governance/MasterData',
            'operation' => $operation,
            'master' => $master,
            'entity_id' => $id,
            'exception' => $e,
        ]);

        return ResponseHelper::errorWithCode('Master data belum dapat diproses. Silakan coba lagi.', 'VISITOR_MASTER_PROCESSING_FAILED', 500);
    }
}
