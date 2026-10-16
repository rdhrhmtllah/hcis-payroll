<?php

namespace App\Http\Controllers\ESS\Attendance\Shift\Request;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ShiftRequestController extends Controller
{
    protected ShiftRequestService $shiftRequestService;

    protected ApprovalFlowService $approvalFlowService;

    public function __construct(ShiftRequestService $shiftRequestService, ApprovalFlowService $approvalFlowService)
    {
        $this->shiftRequestService = $shiftRequestService;
        $this->approvalFlowService = $approvalFlowService;

        $this->Kode_Approval_Config = ['SELF_SHIFT', 'SELF_SHIFT_BACKDATE'];

    }

    /**
     * Main page - Render Self Shift Vue component
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $karyawan = $user->karyawan;
        $now = FormatTanggalHelper::getCurrentTime('self-shift');

        // Handle case where karyawan not linked
        if (! $karyawan) {
            return redirect()->back()->with('error', 'Data karyawan tidak ditemukan untuk user ini.');
        }

        $kodeKaryawan = $karyawan->Kode_Karyawan;

        // Get current week start (Monday)
        $startDate = $now->clone()->startOfWeek();
        if ($request->has('week_start')) {
            $startDate = Carbon::parse($request->week_start)->startOfWeek();
        }

        // Get week schedule
        $weekSchedule = $this->shiftRequestService->getWeekSchedule($kodeKaryawan, $startDate);

        // Get available shifts for dropdown
        $availableShifts = $this->shiftRequestService->getAvailableShifts($kodeKaryawan);

        // Get Current Approver
        $approver = $this->shiftRequestService->getCurrentApprover($kodeKaryawan);

        $approverFlows = [];
        foreach ($this->Kode_Approval_Config as $kodeConfig) {
            $approverFlows[$kodeConfig] = $this->approvalFlowService->getApproverFlow(
                $kodeKaryawan,
                $kodeConfig
            );
        }

        $flowTerbanyak = collect($approverFlows)
            ->sortByDesc(fn ($flow) => $flow->count()) // hitung isi collection
            ->first(); // ambil 1 flow saja

        // $JenisApprovalConfigPalingBanyakDariConfig = collect($approverFlows)->map(fn($flow) => count($flow))->max();
        $JenisApprovalConfigPalingBanyakDariConfig = [];
        if ($flowTerbanyak) {
            $JenisApprovalConfigPalingBanyakDariConfig = $flowTerbanyak
                ->map(function ($e) {
                    // dd($e);
                    // dd($this->shiftRequestService->getKaryawanName($e->kode_karyawan));
                    $e->Nama = $this->shiftRequestService->getKaryawanName($e->kode_karyawan);
                    $e->Jabatan = $this->shiftRequestService->getKaryawanJabatan($e->kode_karyawan);

                    return $e;
                })
                ->values();
        }
        // dd($JenisApprovalConfigPalingBanyakDariConfig);

        return Inertia::render('ESS/Attendance/Shift/Personal/SelfShift', [
            'weekSchedule' => $weekSchedule,
            'availableShifts' => $availableShifts,
            'approver' => $approver, // Pass approver data
            'karyawan' => [
                'kode' => $kodeKaryawan,
                'nama' => $karyawan->Nama_Karyawan ?? '-',
            ],
            'weekStart' => $startDate->format('Y-m-d'),
            'weekEnd' => $startDate->copy()->addDays(6)->format('Y-m-d'),
            'serverTime' => $now->toISOString(),
            'serverDate' => $now->format('Y-m-d'),
            'approverFlows' => $JenisApprovalConfigPalingBanyakDariConfig,
            'config' => [
                'backdate_max_days' => config('services.hcis.shift_request.backdate_max_days', 0),
                'future_max_days' => config('services.hcis.shift_request.future_max_days', 60),
                'backdate_needs_approval' => config('services.hcis.shift_request.backdate_needs_approval', true),
                'same_day_needs_approval' => config('services.hcis.shift_request.same_day_needs_approval', true),
                'future_needs_approval' => config('services.hcis.shift_request.future_needs_approval', true),
                'tukarhari_backdate_max_days' => config('services.hcis.swap_shift_request.backdate_max_days', 30),
                'tukarhari_future_max_days' => config('services.hcis.swap_shift_request.future_max_days', 60),
                'tukarhari_backdate_needs_approval' => config('services.hcis.swap_shift_request.backdate_needs_approval', true),
                'tukarhari_same_day_needs_approval' => config('services.hcis.swap_shift_request.same_day_needs_approval', true),
                'tukarhari_future_needs_approval' => config('services.hcis.swap_shift_request.future_needs_approval', true),
            ],
        ]);
    }

    /**
     * Get week schedule data (for AJAX navigation)
     */
    public function getWeekSchedule(Request $request)
    {
        $karyawan = Auth::user()->karyawan;
        $kodeKaryawan = $karyawan?->Kode_Karyawan;

        $startDate = Carbon::parse($request->week_start)->startOfWeek();
        $weekSchedule = $this->shiftRequestService->getWeekSchedule($kodeKaryawan, $startDate);

        return response()->json([
            'success' => true,
            'data' => [
                'schedule' => $weekSchedule,
                'week_start' => $startDate->format('Y-m-d'),
                'week_end' => $startDate->copy()->addDays(6)->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Submit shift request
     */
    public function submit(Request $request)
    {
        $request->validate([
            'target_table' => 'required|in:SEMENTARA,PERMANEN',
            'dates' => 'required|array|min:1',
            'dates.*.tanggal' => 'required|date',
            'dates.*.id_shift' => 'required|integer',
            'alasan' => 'nullable|string|max:500',
        ]);

        // dd($request->all());

        $karyawan = Auth::user()->karyawan;
        $kodeKaryawan = $karyawan?->Kode_Karyawan;

        try {
            // dd($request->all(), $kodeKaryawan);
            $result = $this->shiftRequestService->submit($request->all(), $kodeKaryawan);

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan shift berhasil diproses.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::channel('ShiftRequestController')->error('Error submit shift request: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pengajuan: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit swap day request
     */
    public function submitSwapHari(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'swap_date' => 'required|date|different:date',
            'make_dayoff' => 'required|boolean',
            'shift_id' => 'required|integer',
            'alasan' => 'nullable|string|max:500',
        ]);

        $karyawan = Auth::user()->karyawan;
        $kodeKaryawan = $karyawan?->Kode_Karyawan;
        if (! $kodeKaryawan) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 404);
        }

        try {
            $result = $this->shiftRequestService->submitSwapHari($request->all(), $kodeKaryawan);

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan tukar hari berhasil diproses.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::channel('ShiftRequestController')->error('Error submitSwapHari: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses tukar hari: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get request history
     */
    public function history(Request $request)
    {
        try {
            $karyawan = Auth::user()->karyawan;
            $kodeKaryawan = $karyawan?->Kode_Karyawan;
            $limit = $request->limit ?? 10;
            $page = $request->page ?? 1;

            $filters = [
                'status' => $request->status,
                'transaction_type' => $request->transaction_type,
                'periode' => $request->periode,
                'search' => $request->search,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ];

            $result = $this->shiftRequestService->getHistory(
                $kodeKaryawan,
                [
                    'page' => $page,
                    'limit' => $limit,
                    'filters' => $filters,
                ]
            );

            return ResponseHelper::successWithPaginationV2(
                [
                    'items' => $result['items'],
                    'stats' => $result['stats'],
                ],
                $result['page'] ?? 1,
                $result['limit'] ?? 10,
                $result['total'],
                'Data ditemukan'
            );

        } catch (\Exception $e) {
            Log::channel('ShiftRequestController')->error('Error getHistory: '.$e->getMessage());

            return ResponseHelper::error(
                'Gagal Mengambil Data',
                500
            );
        }

    }

    public function stats(Request $request)
    {
        try {
            $karyawan = Auth::user()->karyawan;
            $kodeKaryawan = $karyawan?->Kode_Karyawan;

            if (! $kodeKaryawan) {
                return ResponseHelper::error('Karyawan tidak ditemukan', 404);
            }

            return ResponseHelper::success([
                'stats' => $this->shiftRequestService->getHistoryStats(
                    $kodeKaryawan,
                ),
            ]);
        } catch (\Exception $e) {
            Log::channel('ShiftRequestController')->error('Error getStats: '.$e->getMessage());

            return ResponseHelper::error(
                'Gagal Mengambil Statistik',
                500
            );
        }
    }

    public function getHariSwap(Request $request)
    {
        try {
            $karyawan = Auth::user()->karyawan;
            $kodeKaryawan = $karyawan?->Kode_Karyawan;
            if (! $kodeKaryawan) {
                return ResponseHelper::error('Karyawan tidak ditemukan', 404);
            }
            $tanggal = $request->input('tanggal');
            if (! $tanggal) {
                return ResponseHelper::error('Tanggal tidak boleh kosong', 400);
            }
            $typeParam = $request->input('isDayOff', false);
            // dd($type);
            if (! $typeParam) {
                return ResponseHelper::error('Type tidak boleh kosong', 400);
            }

            $type = $typeParam == 'true' ? 'DAY_OFF' : 'WORKING_DAY';
            // dd($type);
            $hariSwapDates = $this->shiftRequestService->getHariSwapDates($kodeKaryawan, $tanggal, $type);

            return ResponseHelper::success([
                'dates' => $hariSwapDates,
            ]);
        } catch (\Exception $e) {
            Log::channel('ShiftRequestController')->error('Error getHariSwap: '.$e->getMessage());

            return ResponseHelper::error(
                'Gagal Mengambil Data Hari Swap',
                500
            );
        }
    }

    /**
     * Get single request detail
     */
    public function detail(string $noTransaksi)
    {
        $detail = $this->shiftRequestService->getDetail($noTransaksi);

        if (! $detail) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $detail,
        ]);
    }

    /**
     * Cancel request (only if still pending and no approval actions taken)
     * Handles both SHIFT and TUKAR_HARI transaction types
     */
    public function cancel(Request $request, string $noTransaksi)
    {
        $karyawan = Auth::user()->karyawan;
        $kodeKaryawan = $karyawan?->Kode_Karyawan;

        // Detect transaction type by prefix: TSH = Status Hari, TH = Tukar Hari, else = Shift
        if (str_starts_with($noTransaksi, 'TSH')) {
            $transactionType = 'STATUS_HARI';
            $headerTable = 'N_HRIS_Transaksi_Status_Hari';
            $detailTable = 'N_HRIS_Transaksi_Status_Hari_Detail';
        } elseif (str_starts_with($noTransaksi, 'TH')) {
            $transactionType = 'TUKAR_HARI';
            $headerTable = 'N_HRIS_Transaksi_Tukar_Hari';
            $detailTable = 'N_HRIS_Transaksi_Tukar_Hari_Detail';
        } else {
            $transactionType = 'SHIFT';
            $headerTable = 'N_HRIS_Transaksi_Shift';
            $detailTable = 'N_HRIS_Transaksi_Shift_Detail';
        }

        // Verify user owns this transaction (3-tier: Kode_Karyawan is in Detail table)
        // For Tukar Hari, check header directly since Kode_Karyawan is there
        if ($transactionType === 'TUKAR_HARI') {
            $header = DB::table($headerTable)
                ->where('No_Transaksi', $noTransaksi)
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->where(function ($q) {
                    $q->whereNull('Flag_Selesai')
                        ->orWhere('Flag_Selesai', '');
                })
                ->first();
        } else {
            // For Shift/Status Hari, check via Detail table
            $header = DB::table($headerTable.' as h')
                ->join($detailTable.' as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
                ->where('h.No_Transaksi', $noTransaksi)
                ->where('d.Kode_Karyawan', $kodeKaryawan)
                ->where(function ($q) {
                    $q->whereNull('h.Flag_Selesai')
                        ->orWhere('h.Flag_Selesai', '');
                })
                ->select('h.*')
                ->first();
        }

        if (! $header) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak dapat dibatalkan.',
            ], 400);
        }

        // Check if any approval action has been taken (defense-in-depth check via approval table)
        $hasApprovalAction = DB::table('HRIS_Approval_Request_Detail as d')
            ->join('HRIS_Approval_Request as ar', 'd.Id_Approval_Request', '=', 'ar.id')
            ->where('ar.No_Transaksi', $noTransaksi)
            ->whereNotNull('d.Flag_Approval')
            ->exists();

        if ($hasApprovalAction) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat membatalkan transaksi karena sudah ada persetujuan dari approver.',
            ], 400);
        }

        try {
            // Use cancelLeaderShiftTransaction with scope 'header' for full transaction cancel
            $result = $this->shiftRequestService->cancelLeaderShiftTransaction(
                $noTransaksi,
                'header',  // scope: cancel full transaction
                null,      // detail_id
                null       // det_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan berhasil dibatalkan.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get available shifts list
     */
    public function getShifts()
    {
        $karyawan = Auth::user()->karyawan;
        $kodeKaryawan = $karyawan?->Kode_Karyawan;
        $shifts = $this->shiftRequestService->getAvailableShifts($kodeKaryawan);

        return response()->json([
            'success' => true,
            'data' => $shifts,
        ]);
    }

    public function shiftForDay(Request $request)
    {
        $validate = $request->validate([
            'date' => 'required|date',
            'all' => 'required',
        ]);

        $karyawan = Auth::user()->karyawan;
        $kodeKaryawan = $karyawan?->Kode_Karyawan;

        try {
            // Fix: Handle partial boolean string input properly (e.g. "false" string is true in PHP loose comparison)
            // Use filter_var or request->boolean to correctly interpret "false" as boolean false.
            if (filter_var($request->input('all'), FILTER_VALIDATE_BOOLEAN) === true) {
                $shiftsList = $this->shiftRequestService->getAvailableShifts($kodeKaryawan);
            } else {
                $shiftsList = $this->shiftRequestService->getShiftsForDay($validate['date'], $kodeKaryawan);
            }

            $currentShift = $this->shiftRequestService->getUserShiftForDate($kodeKaryawan, Carbon::parse($validate['date']));
            $isOffDay = $this->shiftRequestService->isOffDay($kodeKaryawan, $validate['date']);

            // dd($isOffDay);
            return ResponseHelper::success([
                'shifts' => $shiftsList,
                'current_shift' => $currentShift,
                'is_off_day' => $isOffDay,
            ]);
        } catch (\Exception $e) {
            Log::channel('ShiftRequestController')->error('Error shiftForDay: '.$e->getMessage());

            return ResponseHelper::error($e->getMessage());
        }
    }

    public function getLockedDates()
    {
        $karyawan = Auth::user()->karyawan;
        $kodeKaryawan = $karyawan?->Kode_Karyawan;

        $dates = $this->shiftRequestService->getAllLockedDates($kodeKaryawan);

        return response()->json([
            'success' => true,
            'data' => $dates,
        ]);
    }

    public function getPermanentConflicts(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $karyawan = Auth::user()->karyawan;
        $kodeKaryawan = $karyawan?->Kode_Karyawan;
        if (! $kodeKaryawan) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 404);
        }

        $tanggal = Carbon::parse($request->date)->format('Y-m-d');
        $count = $this->shiftRequestService->checkPermanentConflict(
            $kodeKaryawan,
            $tanggal
        );

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $count,
            ],
        ]);
    }

    public function getOffDatesRange(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $karyawan = Auth::user()->karyawan;
        $kodeKaryawan = $karyawan?->Kode_Karyawan;
        if (! $kodeKaryawan) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 404);
        }

        $startDate = Carbon::parse($request->start_date)->format('Y-m-d');
        $endDate = Carbon::parse($request->end_date)->format('Y-m-d');

        if ($endDate < $startDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $dates = $this->shiftRequestService->getOffDatesInRange(
            $kodeKaryawan,
            $startDate,
            $endDate
        );

        return response()->json([
            'success' => true,
            'data' => [
                'dates' => $dates,
            ],
        ]);
    }
}
