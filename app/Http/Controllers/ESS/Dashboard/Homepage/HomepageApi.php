<?php

namespace App\Http\Controllers\ESS\Dashboard\Homepage;

use App\Http\Controllers\Controller;
use App\Services\ESS\Dashboard\Homepage\HomepagePersonalDashboardService;
use App\Services\ESS\Dashboard\Homepage\HomepageRoleService;
use App\Services\ESS\Dashboard\Personal\UDashReadModelService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class HomepageApi extends Controller
{
    public function __construct(
        protected HomepageRoleService $homepageRoleService,
        protected UDashReadModelService $uDashReadModelService,
        protected HomepagePersonalDashboardService $homepagePersonalDashboardService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $kodeKaryawan = $user?->karyawan?->Kode_Karyawan ?? null;
        $userIdAbsen = $user?->karyawan?->UserID_Absen ?? ($user?->Username ?? null);

        if (! $user || ! $kodeKaryawan || ! $userIdAbsen) {
            return response()->json(['message' => 'Data karyawan tidak lengkap.'], 422);
        }

        $roleDetails = $this->homepageRoleService->getRoleDetails($kodeKaryawan);
        // dd($roleDetails);
        $pendingApprovals = 0;
        if (! empty($roleDetails['is_leader'])) {
            $pendingApprovals = $this->uDashReadModelService->countPendingApprovalsV2($kodeKaryawan) ?? 0;
        }

        // dd($pendingApprovals);

        // dd('ass');

        $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');
        $payload = $this->homepagePersonalDashboardService->buildInitialData(
            $kodeKaryawan,
            $userIdAbsen,
            $pendingApprovals,
        );

        return response()->json(['data' => $payload]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
