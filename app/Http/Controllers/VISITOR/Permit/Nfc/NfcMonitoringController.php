<?php

namespace App\Http\Controllers\VISITOR\Permit\Nfc;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\VISITOR\Permit\Nfc\NfcImportParser;
use App\Services\VISITOR\Permit\Nfc\NfcImportService;
use App\Services\VISITOR\Permit\Nfc\NfcMonitoringService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use App\Services\VISITOR\Workflow\Errors\VisitorApiFailure;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use InvalidArgumentException;
use Throwable;

class NfcMonitoringController extends Controller
{
    public function __construct(
        private NfcMonitoringService $monitoringService,
        private NfcImportParser $parser,
        private NfcImportService $importService,
        private VisitorApprovalPolicyService $approvalPolicy,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('VISITOR/Permit/Nfc/NfcMonitoringPage', [
            'initialStats' => $this->monitoringService->stats(),
            'initialData' => $this->monitoringService->list($request->all()),
        ]);
    }

    public function data(Request $request)
    {
        try {
            return ResponseHelper::success([
                'list' => $this->monitoringService->list($request->all()),
                'stats' => $this->monitoringService->stats(),
            ]);
        } catch (Throwable $e) {
            return VisitorApiFailure::respond($e, 'nfc_monitoring_list', 'VISITOR_NFC_LIST_FAILED', 'Daftar monitoring NFC gagal dimuat.');
        }
    }

    public function suggestKaryawan(Request $request)
    {
        try {
            return ResponseHelper::success($this->monitoringService->suggestKaryawan((string) $request->input('q', '')));
        } catch (Throwable $e) {
            return VisitorApiFailure::respond($e, 'nfc_suggest_karyawan', 'VISITOR_NFC_SUGGEST_FAILED', 'Gagal memuat saran karyawan.');
        }
    }

    /**
     * Pratinjau impor: tidak ada yang ditulis.
     *
     * Berkas TIDAK disimpan di server. Di Cloud Run setiap instance punya disk
     * sendiri, jadi berkas yang ditaruh saat pratinjau bisa tidak ada di instance
     * yang menerima permintaan simpan. Layar mengirim ulang berkasnya bersama sidik
     * dari pratinjau ini.
     */
    public function pratinjauImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'tipe' => 'required|string|in:karyawan,phl',
        ]);

        try {
            $path = $request->file('file')->getRealPath();
            $parsed = $this->parser->parseFile($path, $request->input('tipe'));

            return ResponseHelper::success([
                'sidik' => hash_file('sha256', $path),
                'tipe' => $request->input('tipe'),
                'pratinjau' => $this->importService->pratinjau(
                    $parsed['valid'],
                    $parsed['skipped_tanpa_nfc'],
                    $parsed['total_baris'],
                ),
            ], 'Pratinjau berkas berhasil dibuat.');
        } catch (DomainException|InvalidArgumentException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_NFC_PREVIEW_VALIDATION', 422);
        } catch (Throwable $e) {
            Log::error('Pratinjau berkas NFC gagal', ['error' => $e->getMessage()]);

            return VisitorApiFailure::respond($e, 'nfc_import_preview', 'VISITOR_NFC_PREVIEW_FAILED', 'Gagal memproses pratinjau berkas NFC.');
        }
    }

    public function eksekusiImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'tipe' => 'required|string|in:karyawan,phl',
            'sidik' => 'required|string|size:64',
        ]);

        $path = $request->file('file')->getRealPath();
        if (! hash_equals((string) $request->input('sidik'), (string) hash_file('sha256', $path))) {
            return ResponseHelper::errorWithCode(
                'Berkas berbeda dengan yang dipratinjau. Jalankan pratinjau ulang sebelum menyimpan.',
                'VISITOR_NFC_PREVIEW_MISMATCH',
                409,
            );
        }

        try {
            $tipe = $request->input('tipe');
            $parsed = $this->parser->parseFile($path, $tipe);
            $hasil = $this->importService->import($parsed['valid'], $tipe, $this->pelaku($request, 'IMPORT_EXCEL'));

            return ResponseHelper::success([
                'hasil' => $hasil,
                'skipped_tanpa_nfc' => count($parsed['skipped_tanpa_nfc']),
                'total_baris_berkas' => $parsed['total_baris'],
            ], "Impor NFC {$tipe} berhasil disimpan ke basis data.");
        } catch (DomainException|InvalidArgumentException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_NFC_IMPORT_VALIDATION', 422);
        } catch (Throwable $e) {
            Log::error('Eksekusi impor NFC gagal', ['error' => $e->getMessage()]);

            return VisitorApiFailure::respond($e, 'nfc_import_execute', 'VISITOR_NFC_EXECUTE_FAILED', 'Gagal menyimpan impor berkas NFC.');
        }
    }

    public function simpanManual(Request $request)
    {
        $request->validate([
            'tipe' => 'required|string|in:karyawan,phl',
            'nfc_hex' => 'required|string|max:30',
            'nama' => 'required|string|max:100',
            'kode_karyawan' => 'nullable|string|max:50',
            'nik' => 'nullable|string|max:30',
            'no_mitra' => 'nullable|string|max:30',
            'nfc_inbio' => 'nullable|integer|min:0',
        ]);

        try {
            $hasil = $this->importService->simpanManual($request->all(), $this->pelaku($request, 'MANUAL_WEB'));

            return ResponseHelper::success($hasil, 'Pairing ID Card berhasil disimpan.');
        } catch (DomainException|InvalidArgumentException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_NFC_MANUAL_VALIDATION', 422);
        } catch (Throwable $e) {
            Log::error('Simpan manual pairing NFC gagal', ['error' => $e->getMessage()]);

            return VisitorApiFailure::respond($e, 'nfc_simpan_manual', 'VISITOR_NFC_MANUAL_FAILED', 'Gagal menyimpan pairing ID Card.');
        }
    }

    public function toggleBinding(Request $request, int $id)
    {
        try {
            return ResponseHelper::success(
                $this->monitoringService->toggleBindingStatus($id),
                'Status aktif pairing NFC berhasil diubah.',
            );
        } catch (DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_NFC_BINDING_CONFLICT', 409);
        } catch (Throwable $e) {
            return VisitorApiFailure::respond($e, 'nfc_toggle_binding', 'VISITOR_NFC_TOGGLE_FAILED', 'Gagal mengubah status pairing NFC.');
        }
    }

    public function toggleValidasi(Request $request, int $id)
    {
        try {
            return ResponseHelper::success(
                $this->monitoringService->toggleMasterValidasi($id),
                'Status validasi sistem kartu NFC berhasil diubah.',
            );
        } catch (DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_NFC_MASTER_NOT_FOUND', 404);
        } catch (Throwable $e) {
            return VisitorApiFailure::respond($e, 'nfc_toggle_validasi', 'VISITOR_NFC_TOGGLE_VALIDASI_FAILED', 'Gagal mengubah validasi kartu NFC.');
        }
    }

    /**
     * Kode karyawan pelaku untuk created_by. KPI_Users tidak punya kolom
     * Kode_Karyawan sendiri; tanpa resolver ini setiap baris tercatat atas nama
     * label cadangan dan tidak ada jejak siapa yang mengimpor.
     */
    private function pelaku(Request $request, string $cadangan): string
    {
        $user = $request->user();

        return (string) ($this->approvalPolicy->requesterKode($user) ?? $user?->Username ?? $cadangan);
    }
}
