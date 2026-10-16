<?php

namespace App\Http\Controllers\ESS\Attendance\Shift\Approval;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ShiftApprovalController extends Controller
{
    protected ShiftRequestService $shiftService;

    protected ApprovalService $approvalService;

    protected ApprovalRequestService $approvalRequestService;

    public function __construct(
        ShiftRequestService $shiftService,
        ApprovalService $approvalService,
        ApprovalRequestService $approvalRequestService
    ) {
        $this->shiftService = $shiftService;
        $this->approvalService = $approvalService;
        $this->approvalRequestService = $approvalRequestService;
    }

    public function index()
    {
        $user = Auth::user();
        $now = FormatTanggalHelper::getCurrentTime('shift-approval');
        // Assuming user acts as approver based on their Karyawan Kode
        $kodeApprover = $user->karyawan?->Kode_Karyawan;

        if (! $kodeApprover) {
            return redirect()->back()->with('error', 'Anda tidak terdaftar sebagai karyawan.');
        }

        return Inertia::render('ESS/Attendance/Shift/Approval/ShiftApproval', [
            'serverTime' => $now->toISOString(),
            'serverDate' => $now->format('Y-m-d'),
        ]);
    }

    public function getDashboardStats()
    {
        $kodeApprover = Auth::user()->karyawan?->Kode_Karyawan;

        return response()->json([
            'success' => true,
            'data' => $this->getStatsData($kodeApprover),
        ]);
    }

    public function getInbox(Request $request)
    {
        try {
            $kodeApprover = Auth::user()->karyawan?->Kode_Karyawan;

            $filters = [
                'status' => $request->input('status', 'all'),
                'search' => $request->input('search'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'jabatan' => $request->input('jabatan'),
                'transaction_type' => $request->input('transaction_type'),
            ];

            $params = [
                'page' => $request->page ?? 1,
                'limit' => $request->limit ?? 10,
                'filters' => $filters,
            ];

            $data = $this->shiftService->getApproverInbox($kodeApprover, $params);

            return ResponseHelper::successWithPaginationV2(
                $data['data'] ?? [],  // Pass array langsung, jangan wrap
                $data['page'] ?? 1,
                $data['limit'] ?? 10,
                $data['total'],
                'Data ditemukan'
            );
        } catch (Exception $e) {
            Log::channel('ShiftApprovalController')->error('Error getApproverInbox: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getHistory(Request $request)
    {
        try {
            $kodeApprover = Auth::user()->karyawan?->Kode_Karyawan;

            $filters = [
                'status' => $request->input('status', 'all'),
                'search' => $request->input('search'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'jabatan' => $request->input('jabatan'),
                'transaction_type' => $request->input('transaction_type'),
            ];

            $params = [
                'page' => $request->page ?? 1,
                'limit' => $request->limit ?? 10,
                'filters' => $filters,
            ];

            $result = $this->shiftService->getApproverHistory($kodeApprover, $params);

            // DEBUG: Log service result
            Log::channel('ShiftApprovalController')->info('getHistory result', [
                'kodeApprover' => $kodeApprover,
                'data_count' => count($result['data'] ?? []),
                'total' => $result['total'] ?? 0,
            ]);

            return ResponseHelper::successWithPaginationV2(
                $result['data'] ?? [],  // Service return 'data' key
                $result['page'] ?? 1,
                $result['limit'] ?? 10,
                $result['total'],
                'Data ditemukan'
            );
        } catch (Exception $e) {
            Log::channel('ShiftApprovalController')->error('Error getApproverHistory: '.$e->getMessage());

            return ResponseHelper::error(
                'Gagal Mengambil Data',
                500
            );
        }
    }

    public function getJabatan()
    {
        $user = Auth::user();
        $Karyawan = $user->karyawan;

        return DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')
            ->select('Id_Jabatan', 'nama_jabatan')
            ->groupBy('Id_Jabatan', 'nama_jabatan')
            ->orderBy('nama_jabatan', 'asc')
            ->get();
    }

    public function approve(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'no_transaksi' => 'required|string',
            'action' => 'required|in:APPROVE,REJECT',
            'details' => 'nullable|array', // ['2023-10-01' => 'Y', '2023-10-02' => 'T']
            'notes' => 'nullable|string',
        ]);

        $kodeApprover = Auth::user()->karyawan?->Kode_Karyawan;
        $noTransaksi = $request->no_transaksi;
        $action = $request->action;
        $notes = $request->notes;

        if (! $kodeApprover) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak terdaftar sebagai karyawan.',
            ], 403);
        }

        try {
            DB::beginTransaction();

            // ================================================================
            // STEP 1: Validate approver access menggunakan ApprovalRequestService
            // ================================================================
            $validation = $this->approvalRequestService->validateApproverAccess($noTransaksi, $kodeApprover);

            if (! $validation['valid']) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => $validation['reason'],
                ], 403);
            }

            // ================================================================
            // STEP 2: Process approval/rejection menggunakan ApprovalService
            // ================================================================
            if ($action === 'APPROVE') {
                $this->approvalService->approve($noTransaksi, $kodeApprover, $notes);
            } else {
                // REJECT
                $this->approvalService->reject($noTransaksi, $kodeApprover, $notes ?? 'Ditolak oleh approver');
            }

            // Cek pembatalan Detail sblm masuk ke shift service.
            // Satu kueri untuk SELURUH detail keputusan ini: sebelumnya
            // isRowCancelled() dipanggil di dalam reject() sehingga tiap baris
            // detail membawa kuerinya sendiri — approval massal berarti ratusan
            // perjalanan ke basis data di jalur tulis yang sedang memegang lock.
            $details = collect($request->input('details', []));

            if (str_starts_with($noTransaksi, 'TSH')) {
                $namaTable = 'N_HRIS_Transaksi_Status_Hari_Det';
            } elseif (str_starts_with($noTransaksi, 'TH')) {
                $namaTable = 'N_HRIS_Transaksi_Tukar_Hari_Detail';
            } else {
                $namaTable = 'N_HRIS_Transaksi_Shift_Det';
            }

            // Hanya baris ber-status 'T' yang perlu diperiksa; sisanya tak
            // pernah ditolak oleh pemeriksaan ini.
            $sudahDibatalkan = $this->shiftService->barisDibatalkan(
                $noTransaksi,
                $details->filter(fn ($status) => $status === 'T')->keys()->all(),
                $namaTable,
            );

            $filteredDetails = $details
                ->reject(fn ($status, $id) => $status === 'T'
                    && in_array((int) $id, $sudahDibatalkan, true))
                ->toArray();

            $request->merge(['details' => $filteredDetails]);
            // dd($request->input('details', []), $filteredDetails);

            // ================================================================
            // STEP 3: Handle shift-specific logic (detail approvals, updates)
            // ================================================================
            $result = $this->shiftService->processApproval(
                $kodeApprover,
                $noTransaksi,
                $action,
                $request->details ?? [],
                $notes,
                true
            );

            // ================================================================
            // STEP 4: Get latest flow state and approval summary
            // ================================================================
            $currentApproval = $this->approvalRequestService->getCurrentApprovalRequest($noTransaksi);
            $nextAction = $currentApproval
                ? [
                    'action' => 'WAITING_NEXT',
                    'nextApproval' => $currentApproval,
                    'isTerminal' => false,
                    'reason' => 'Menunggu approver berikutnya',
                ]
                : [
                    'action' => $result['status'] ?? 'COMPLETED',
                    'nextApproval' => null,
                    'isTerminal' => true,
                    'reason' => 'Flow approval selesai',
                ];
            $summary = $this->approvalRequestService->getApprovalSummary($noTransaksi);

            DB::commit();

            $message = $action === 'APPROVE'
                ? (($result['status'] ?? null) === 'WAITING_NEXT'
                    ? 'Approval berhasil diproses, menunggu approver berikutnya.'
                    : 'Approval berhasil diproses.')
                : (($result['status'] ?? null) === 'WAITING_NEXT'
                    ? 'Penolakan berhasil dicatat, transaksi dilanjutkan ke approver berikutnya.'
                    : 'Penolakan berhasil diproses.');

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'result' => $result,
                    'nextAction' => $nextAction,
                    'summary' => $summary,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Shift Approval Error [$noTransaksi]: ".$e->getMessage(), [
                'exception' => $e,
                'approver' => $kodeApprover,
                'action' => $action,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses: '.$e->getMessage(),
            ], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    // protected function getStatsData($kodeApprover)
    // {
    //     $pendingCount = DB::table('N_HRIS_VW_Approval_Request_V2')
    //         ->where('Flow_State', 'CURRENT')
    //         ->where(function ($q) use ($kodeApprover) {
    //             $q->where('Current_Approver', $kodeApprover)
    //                 ->where(function ($flag) {
    //                     $flag->where('Flag_Group', '!=', 'Y')
    //                         ->orWhereNull('Flag_Group');
    //                 });
    //         })
    //         ->where(function ($q) {
    //             $q->where('No_Transaksi', 'like', 'SH%')
    //                 ->orWhere('No_Transaksi', 'like', 'TH%')
    //                 ->orWhere('No_Transaksi', 'like', 'TSH%');
    //         })
    //         ->count();

    //     $processedCount = DB::table('HRIS_Approval_Request')
    //         ->where('Approved_By_Kode', $kodeApprover)
    //         ->whereNotNull('Flag_Approval')
    //         ->whereMonth('Tanggal_Approval', now()->month)
    //         ->whereYear('Tanggal_Approval', now()->year)
    //         ->where(function ($q) {
    //             $q->where('No_Transaksi', 'like', 'SH%')
    //                 ->orWhere('No_Transaksi', 'like', 'TH%')
    //                 ->orWhere('No_Transaksi', 'like', 'TSH%');
    //         })
    //         ->count();

    //     $rejectedCount = DB::table('HRIS_Approval_Request')
    //         ->where('Approved_By_Kode', $kodeApprover)
    //         ->where('Flag_Approval', 'T')
    //         ->whereMonth('Tanggal_Approval', now()->month)
    //         ->whereYear('Tanggal_Approval', now()->year)
    //         ->where(function ($q) {
    //             $q->where('No_Transaksi', 'like', 'SH%')
    //                 ->orWhere('No_Transaksi', 'like', 'TH%')
    //                 ->orWhere('No_Transaksi', 'like', 'TSH%');
    //         })
    //         ->count();

    //     return [
    //         'pending_count' => $pendingCount,
    //         'processed_month' => $processedCount,
    //         'rejected_month' => $rejectedCount,
    //         'team_summary' => []
    //     ];
    // }

    protected function getStatsData($kodeApprover)
    {
        $allTx = new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService;
        $jenisShift = ['STATUS_HARI', 'SHIFT', 'TUKAR_HARI', 'KONVERSI_LEMBUR'];

        /*
        |--------------------------------------------------------------------------
        | 1. PENDING (yang sedang menunggu approver ini)
        |--------------------------------------------------------------------------
        | Sudah include:
        | - cek cancel (karena source sudah filter Status IS NULL)
        | - cek flow state
        | - tidak double item
        */
        $pendingCount = DB::query()
            ->fromSub($allTx->forApprover($kodeApprover), 'tx')
            ->where('Dashboard_Status', 'PENDING')
            ->whereIn('Jenis', $jenisShift)
            ->Distinct('No_Transaksi')
            ->count();

        /*
        |--------------------------------------------------------------------------
        | 2. PROCESSED BULAN INI (Approved + Rejected oleh approver ini)
        |--------------------------------------------------------------------------
        | Ini menggantikan HRIS_Approval_Request lama.
        */
        $processedCount = DB::query()
            ->fromSub($allTx->forProcessedBy($kodeApprover), 'tx')
            ->whereIn('Dashboard_Status', ['APPROVED', 'REJECTED'])
            /* Rentang setengah-terbuka, bukan whereMonth+whereYear: membungkus
               kolomnya dengan MONTH()/YEAR() membuat indeks tak bisa di-seek.
               Batas atasnya EKSKLUSIF supaya tidak mengulang jebakan
               23:59:59.999 yang dibulatkan datetime ke tengah malam berikutnya. */
            ->where('Tanggal_Approval', '>=', now()->startOfMonth())
            ->where('Tanggal_Approval', '<', now()->startOfMonth()->addMonth())
            ->whereIn('Jenis', $jenisShift)
            ->Distinct('No_Transaksi')
            ->count();

        /*
        |--------------------------------------------------------------------------
        | 3. REJECTED BULAN INI
        |--------------------------------------------------------------------------
        */
        $rejectedCount = DB::query()
            ->fromSub($allTx->forProcessedBy($kodeApprover), 'tx')
            ->where('Dashboard_Status', 'REJECTED')
            /* Rentang setengah-terbuka, bukan whereMonth+whereYear: membungkus
               kolomnya dengan MONTH()/YEAR() membuat indeks tak bisa di-seek.
               Batas atasnya EKSKLUSIF supaya tidak mengulang jebakan
               23:59:59.999 yang dibulatkan datetime ke tengah malam berikutnya. */
            ->where('Tanggal_Approval', '>=', now()->startOfMonth())
            ->where('Tanggal_Approval', '<', now()->startOfMonth()->addMonth())
            ->whereIn('Jenis', $jenisShift)
            ->Distinct('No_Transaksi')
            ->count();

        /*
        |--------------------------------------------------------------------------
        | 4. TEAM SUMMARY (Optional tapi biasanya dipakai dashboard)
        |--------------------------------------------------------------------------
        | Rekap berdasarkan jenis transaksi (SHIFT / CUTI / dll)
        */
        $teamSummary = DB::query()
            ->fromSub($allTx->forApproverOrProcessedBy($kodeApprover), 'tx')
            ->selectRaw("
                Jenis,
                COUNT(*) AS total,
                SUM(CASE WHEN Dashboard_Status = 'PENDING' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN Dashboard_Status = 'APPROVED' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN Dashboard_Status = 'REJECTED' THEN 1 ELSE 0 END) AS rejected
            ")
            ->whereIn('Jenis', [
                'STATUS_HARI',
                'SHIFT',
                'TUKAR_HARI',
                'KONVERSI_LEMBUR',
            ])
            ->groupBy('Jenis')
            ->orderBy('Jenis')
            ->get();

        return [
            'pending_count' => $pendingCount,
            'processed_month' => $processedCount,
            'rejected_month' => $rejectedCount,
            'team_summary' => $teamSummary,
        ];
    }

    /**
     * Get approval status & next approver info untuk display
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getApprovalStatus(Request $request)
    {
        $request->validate([
            'no_transaksi' => 'required|string',
        ]);

        try {
            $noTransaksi = $request->no_transaksi;

            $summary = $this->approvalRequestService->getApprovalSummary($noTransaksi);
            $current = $this->approvalRequestService->getCurrentApprovalRequest($noTransaksi);
            $nextAction = $this->approvalRequestService->determineNextAction($noTransaksi, 'APPROVE');

            return ResponseHelper::success([
                'summary' => $summary,
                'current' => $current,
                'nextAction' => $nextAction,
            ], 'Approval status ditemukan');

        } catch (\Exception $e) {
            Log::error('Get Approval Status Error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil approval status: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get full approval chain flow untuk display di modal/detail
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getApprovalFlow(Request $request)
    {
        $request->validate([
            'no_transaksi' => 'required|string',
        ]);

        try {
            $noTransaksi = $request->no_transaksi;

            $pending = $this->approvalRequestService->getPendingApprovals($noTransaksi);
            $history = $this->approvalRequestService->getApprovalHistory($noTransaksi);

            return ResponseHelper::success([
                'pending' => $pending,
                'history' => $history,
            ], 'Approval flow ditemukan');

        } catch (\Exception $e) {
            Log::error('Get Approval Flow Error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil approval flow: '.$e->getMessage(), 500);
        }
    }

    public function downloadLampiran(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $filePath = trim((string) $request->query('path', ''));

            if ($filePath === '') {
                return response()->json(
                    ['message' => 'Path lampiran tidak diberikan.'],
                    400,
                );
            }

            if (! Storage::disk('gcs')->exists($filePath)) {
                return response()->json(
                    ['message' => 'File lampiran tidak ditemukan.'],
                    404,
                );
            }

            $fileContent = Storage::disk('gcs')->get($filePath);
            $fileName = basename($filePath);
            $mimeType =
                Storage::disk('gcs')->mimeType($filePath) ?:
                'application/octet-stream';

            return response($fileContent, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                'Content-Length' => strlen($fileContent),
            ]);
        } catch (\Throwable $th) {
            Log::error('Download lampiran shift approval gagal', [
                'path' => $request->query('path'),
                'error' => $th->getMessage(),
            ]);

            return response()->json(
                ['message' => 'Gagal mendownload lampiran.'],
                500,
            );
        }
    }
}
