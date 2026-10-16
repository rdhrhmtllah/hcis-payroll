<?php

namespace App\Http\Controllers\KPI\Performance\Team;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KpiKaryawanTeamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('KPI/Performance/Team/karyawanTeam');
    }

    public function getDataCurrent(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('q', null);

            $query = DB::table('Karyawan as k')
                ->join('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
                ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
                ->select(
                    'k.Kode_Karyawan',
                    'k.Nama',
                    'vd.nama_divisi',
                    'vd.nama_sub_divisi',
                    'vg.nama_level',
                    'vg.nama_jabatan',
                    'k.Aktif as Flag_Aktif',
                    DB::raw("(Select count(z.Kode_Karyawan_Leader) from KPI_Karyawan_Team z where z.Kode_Karyawan_Leader = k.Kode_Karyawan and z.Flag_Aktif = 'Y') as countTeam"),
                    DB::raw("(CASE WHEN EXISTS(select top 1 * from KPI_Karyawan_Team z where z.Kode_Karyawan_Team = k.Kode_Karyawan and z.Flag_Aktif = 'Y') THEN 'TRUE' ELSE 'FALSE' END) AS KPISubmit")
                );

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('k.Nama', 'LIKE', "%{$search}%")
                        ->orWhere('vd.nama_divisi', 'LIKE', "%{$search}%")
                        ->orWhere('vd.nama_sub_divisi', 'LIKE', "%{$search}%")
                        ->orWhere('vg.nama_level', 'LIKE', "%{$search}%")
                        ->orWhere('vg.nama_jabatan', 'LIKE', "%{$search}%");
                });
            }

            $total = $query->count();

            $getData = $query
                ->offset($offset)
                ->limit($limit)
                ->orderBy('k.Kode_Karyawan')
                ->orderByRaw("CASE WHEN k.Aktif = 'T' THEN 0 ELSE 1 END ASC")
                ->get();
            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Kode_Karyawan = Crypt::encryptString($item->Kode_Karyawan);

                return $item;
            });

            return ResponseHelper::successWithPagination($encodedData, $page, $limit, $total, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('bscKategoriController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getAllKaryawan()
    {
        try {
            $all = DB::table('Karyawan as k')
                ->join('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
                ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
                ->select(
                    'k.Kode_Karyawan',
                    'k.Nama',
                    'vd.nama_divisi',
                    'vd.nama_sub_divisi',
                    'vg.nama_level',
                    'vg.nama_jabatan',
                    'k.Aktif as Flag_Aktif',
                    DB::raw("(Select count(z.Kode_Karyawan_Leader) from KPI_Karyawan_Team z where z.Kode_Karyawan_Leader = k.Kode_Karyawan and z.Flag_Aktif = 'Y') as countTeam"),
                    DB::raw("(CASE WHEN EXISTS(select top 1 * from KPI_Karyawan_Team z where z.Kode_Karyawan_Team = k.Kode_Karyawan and z.Flag_Aktif = 'Y') THEN 'TRUE' ELSE 'FALSE' END) AS KPISubmit")
                );

            return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('bscKategoriController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
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
