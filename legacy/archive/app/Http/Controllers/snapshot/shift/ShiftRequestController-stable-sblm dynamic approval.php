<?php

namespace App\Http\Controllers;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\ESS\Attendance\Shift\Request\ShiftRequestController;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ShiftRequestController extends Controller
{
    protected ShiftRequestService $shiftRequestService;

    public function __construct(ShiftRequestService $shiftRequestService)
    {
        $this->shiftRequestService = $shiftRequestService;
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
        $availableShifts = $this->shiftRequestService->getAvailableShifts();

        // Get Current Approver
        $approver = $this->shiftRequestService->getCurrentApprover($kodeKaryawan);

        return Inertia::render('SelfShift', [
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
     * Cancel request (only if still pending) - uses cancelLeaderShiftTransaction for consistency
     */
    public function cancel(Request $request, string $noTransaksi)
    {
        $karyawan = Auth::user()->karyawan;
        $kodeKaryawan = $karyawan?->Kode_Karyawan;

        // Verify user owns this transaction (3-tier: Kode_Karyawan is in Detail table)
        $header = DB::table('N_HRIS_Transaksi_Shift as h')
            ->join('N_HRIS_Transaksi_Shift_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('h.No_Transaksi', $noTransaksi)
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('h.Flag_Selesai')
            ->select('h.*')
            ->first();

        if (! $header) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak dapat dibatalkan.',
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
        $shifts = $this->shiftRequestService->getAvailableShifts();

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
                $shiftsList = $this->shiftRequestService->getAvailableShifts();
            } else {
                $shiftsList = $this->shiftRequestService->getShiftsForDay($validate['date']);
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
