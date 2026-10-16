<?php

namespace App\Http\Controllers\ESS\Attendance\Shift\Admin;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowService;
use App\Services\SYSTEM\Governance\Authorization\Shared\AccessContext;
use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;
use App\Services\SYSTEM\Notification\WhatsappMessageBuilder;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminShiftController extends Controller
{
    protected FunctionalRole $role;

    public function __construct(ShiftRequestService $shiftService, FunctionalRole $role, ApprovalFlowService $approvalFlowService, WhatsappLogHelper $whatsappLogHelper, WhatsappMessageBuilder $whatsappMessageBuilder)
    {
        $this->shiftService = $shiftService;
        $this->approvalFlowService = $approvalFlowService;
        $this->whatsappLogHelper = $whatsappLogHelper;
        $this->role = $role;

        $this->whatsappMessageBuilder = $whatsappMessageBuilder;

        // Override config so NO approval is needed anywhere, anytime
        $this->leaderConfig = [
            'backdate_needs_approval' => false,
            'same_day_needs_approval' => false,
            'future_needs_approval' => false,
            'backdate_max_days' => 365,
            'same_days' => true,
            'future_max_days' => 365,
        ];
    }

    public function index()
    {
        $user = Auth::user();
        $now = FormatTanggalHelper::getCurrentTime('leader-shift');
        $karyawan = $user->karyawan;

        if (! $karyawan) {
            return redirect()->back()->with('error', 'Anda tidak terdaftar sebagai karyawan.');
        }
        $context = app(AccessContext::class);

        // dd($this->getStats($karyawan->Kode_Karyawan));
        $disabledDates = [];

        return Inertia::render('ESS/Attendance/Shift/Leadership/LeaderShift', [
            'serverTime' => $now->toISOString(),
            'serverDate' => $now->format('Y-m-d'),
            'approverInfo' => null, // Admin doesn't need approver info
            'initialStats' => $this->getStats($karyawan->Kode_Karyawan), // Admin gets global stats
            'config' => $this->leaderConfig, // Use admin config
            'disabledDates' => $disabledDates,
            'isAdmin' => true,
            'pageTitle' => 'Admin Shift Management',
            'pageSubtitle' => 'Kelola jadwal shift seluruh karyawan tanpa melalui proses approval',
        ]);
    }

    protected function getStats(string $leaderKode): array
    {
        try {
            $user = Auth::user();
            $karyawan = $user->karyawan;
            $context = app(AccessContext::class);
            // For admin, we want to show stats for ALL teams, so we pass null or empty string
            $result = $this->shiftService->getStatsForLeader($karyawan->Kode_Karyawan, $this->role, $context);
            Log::channel('LeaderShiftController')->info('getStats Result', $result);

            return $result;
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error('Error getStats: '.$e->getMessage());

            return [
                'team_count' => 0,
                'pending_count' => 0,
                'approved_month' => 0,
            ];
        }
    }
}
