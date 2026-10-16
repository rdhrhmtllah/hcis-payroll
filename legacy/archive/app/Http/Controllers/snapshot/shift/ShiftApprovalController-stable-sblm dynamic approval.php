<?php

namespace App\Http\Controllers;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\ESS\Attendance\Shift\Approval\ShiftApprovalController;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ShiftApprovalController extends Controller
{
    protected ShiftRequestService $shiftService;

    public function __construct(ShiftRequestService $shiftService)
    {
        $this->shiftService = $shiftService;
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

        return Inertia::render('ShiftApproval', [
            'initialStats' => $this->getStatsData($kodeApprover),
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
            ];

            $params = [
                'page' => $request->page ?? 1,
                'limit' => $request->limit ?? 10,
                'filters' => $filters,
            ];

            $data = $this->shiftService->getApproverInbox($kodeApprover, $params);

            // dd($data);
            return ResponseHelper::successWithPaginationV2(
                [
                    'items' => $data['data'],
                    'stats' => $data['stats'],
                ],
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
            ];

            $params = [
                'page' => $request->page ?? 1,
                'limit' => $request->limit ?? 10,
                'filters' => $filters,
            ];

            $result = $this->shiftService->getApproverHistory($kodeApprover, $params);

            return ResponseHelper::successWithPaginationV2(
                [
                    'items' => $result['data'],
                    // 'stats' => $result['stats'],
                ],
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

        try {
            DB::beginTransaction();
            $result = $this->shiftService->processApproval(
                $kodeApprover,
                $noTransaksi,
                $request->action,
                $request->details ?? [],
                $request->notes
            );

            DB::commit(); // CRITICAL: Commit the transaction to persist changes

            return response()->json([
                'success' => true,
                'message' => 'Approval berhasil diproses.',
                'result' => $result,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses: '.$e->getMessage(),
            ], 500);
        }
    }

    protected function getStatsData($kodeApprover)
    {
        // 1. Pending Count (Only Shifts)
        $pendingCount = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Shift as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('ar.Approver_Karyawan_Kode', $kodeApprover)
            ->whereNull('ar.Status')
            ->whereNull('ar.Flag_Approval')
            ->count();

        // 2. Processed This Month (Only Shifts)
        $processedCount = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Shift as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('ar.Approver_Karyawan_Kode', $kodeApprover)
            ->whereNull('ar.Status')
            ->whereNotNull('ar.Flag_Approval')
            ->whereMonth('ar.Tanggal_Approval', now()->month)
            ->count();

        return [
            'pending_count' => $pendingCount,
            'processed_month' => $processedCount,
            'team_summary' => [],
        ];
    }
}
