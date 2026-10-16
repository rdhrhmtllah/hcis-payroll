<?php

namespace App\Http\Controllers\LMS\SOP\Employee;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopComplianceExportService;
use App\Services\LMS\SOP\SopProgressReadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SOP — Sisi Karyawan (SOP Saya & Riwayat).
 */
class SopSayaController extends Controller
{
    public function __construct(
        private SopProgressReadService $read,
        private SopComplianceExportService $exportSvc
    ) {}

    private function kodeKaryawan(): ?string
    {
        return optional(Auth::user()->karyawan)->Kode_Karyawan;
    }

    /* ================= HALAMAN (Inertia) ================= */

    /** Hub "SOP Saya". Route: GET /sop/saya */
    public function index()
    {
        return inertia('LMS/SOP/Employee/Saya/SopSaya', [
            'prioritas' => app(\App\Services\LMS\SOP\SopPrioritasService::class)->untukLayar(),
        ]);
    }

    /* ================= API ================= */

    /**
     * GET /api/v1/sop/saya?tab=perlu_dibaca
     * Kartu tugas + jumlah per tab.
     */
    public function apiSaya(Request $request)
    {
        try {
            $kode = $this->kodeKaryawan();
            if (! $kode) {
                return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
            }

            $tab = $request->query('tab', 'perlu_dibaca');
            $result = $this->read->tugasSaya($kode, $tab);
            // penanda leader → tampilkan pintasan "Kepatuhan Tim" di SopSaya
            $result['is_leader'] = app(\App\Services\LMS\SOP\SopTeamService::class)->isLeader(trim($kode));

            return ResponseHelper::success($result);
        } catch (\Exception $e) {
            Log::error('SOP saya apiSaya gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * POST /api/v1/sop/saya/kartu-kepatuhan
     * Trigger export "Kartu Kepatuhan" (async → Activity Center).
     */
    public function apiKartuKepatuhan(Request $request)
    {
        try {
            $kode = $this->kodeKaryawan();
            if (! $kode) {
                return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
            }

            $total = DB::table('N_SOP_Penjadwalan_Detail')
                ->where('Kode_Karyawan', $kode)->where('Flag_Aktif', 'Y')->count();

            $exportId = $this->exportSvc->makeExportId();
            $this->exportSvc->startJob(
                $exportId,
                $request->input('format', 'xlsx'),
                ['jenis' => 'kartu', 'kode_karyawan' => $kode, 'nama' => 'Kartu_Kepatuhan_'.$kode],
                $total,
                $kode
            );
            $this->exportSvc->dispatchJob($exportId);

            return ResponseHelper::success(
                ['task_id' => $exportId],
                'Kartu kepatuhan sedang diproses. Cek panel Aktivitas.',
                202
            );
        } catch (\Exception $e) {
            Log::error('SOP saya apiKartuKepatuhan gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }
}
