<?php

namespace App\Http\Controllers\LMS\SOP\Employee;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopTeamService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * SOP — Kepatuhan Tim (leader). Read-only: leader melihat status kepatuhan
 * SOP anggota timnya (HRIS_Karyawan_Team). Tanpa aksi/eskalasi.
 */
class SopTeamController extends Controller
{
    public function __construct(private SopTeamService $service) {}

    private function kode(): ?string
    {
        return optional(Auth::user()->karyawan)->Kode_Karyawan
            ? trim(Auth::user()->karyawan->Kode_Karyawan) : null;
    }

    /** GET /sop/tim */
    public function index()
    {
        return inertia('LMS/SOP/Employee/Team/SopTeam');
    }

    /** GET /api/v1/sop/tim/ringkasan */
    public function ringkasan()
    {
        $kode = $this->kode();
        if (! $kode) {
            return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
        }

        try {
            return ResponseHelper::success($this->service->ringkasan($kode));
        } catch (\Exception $e) {
            Log::error('SOP tim ringkasan gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /api/v1/sop/tim/anggota/{kode} */
    public function anggota(string $kode)
    {
        $leader = $this->kode();
        if (! $leader) {
            return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
        }

        try {
            $res = $this->service->anggota($leader, $kode);
            if ($res === null) {
                return ResponseHelper::error('Karyawan ini bukan anggota tim Anda.', 403);
            }

            return ResponseHelper::success($res);
        } catch (\Exception $e) {
            Log::error('SOP tim anggota gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }
}
