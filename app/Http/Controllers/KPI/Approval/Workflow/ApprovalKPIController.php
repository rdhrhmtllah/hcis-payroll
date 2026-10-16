<?php

namespace App\Http\Controllers\KPI\Approval\Workflow;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class ApprovalKPIController extends Controller
{
    public function index()
    {
        return inertia('KPI/Approval/Workflow/ManajemenApprovalKPI');
    }

    /**
     * Mencari karyawan berdasarkan nama atau kode karyawan.
     */
    public function searchEmployees(Request $request)
    {
        $query = $request->input('q', '');

        try {
            $employees = DB::table('Karyawan as k')
                ->join('View_Divisi_Sub_Divisi as vdsd', 'k.ID_Divisi_Sub_Divisi', '=', 'vdsd.ID_DIVISI_SUB_DIVISI')
                ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vgslj', 'k.ID_Level_Jabatan', '=', 'vgslj.ID_Level_Jabatan')
                ->select('k.Kode_Karyawan', 'k.Nama', 'vdsd.nama_divisi', 'vgslj.nama_jabatan')
                ->where('k.Aktif', 'Y')
                ->where(function ($q) use ($query) {
                    $q->where('k.Nama', 'like', "%{$query}%")
                        ->orWhere('k.Kode_Karyawan', 'like', "%{$query}%");
                })
                ->limit(15)
                ->get();

            $encodedData = $employees->map(function ($item) {
                $item->hashed_id = Crypt::encrypt($item->Kode_Karyawan);

                return $item;
            });

            return ResponseHelper::success($encodedData, 'Data karyawan ditemukan');
        } catch (\Exception $e) {
            Log::error('Error searching employees for KPI flow: '.$e->getMessage());

            return ResponseHelper::error('Gagal mencari karyawan.', 500);
        }
    }

    /**
     * Mengambil alur persetujuan untuk karyawan yang dipilih.
     */
    public function getApprovalFlow($hashedEmployeeId)
    {
        try {
            $decodedId = Crypt::decrypt($hashedEmployeeId);
            if (empty($decodedId)) {
                return ResponseHelper::error('ID Karyawan tidak valid.', 400);
            }
            // dd($decodedId);
            $requesterKode = $decodedId;

            $flow = DB::table('KPI_Approval_Flow_Parameter as afp')
                ->join('Karyawan as approver', 'afp.Kode_Karyawan_Approver', '=', 'approver.Kode_Karyawan')
                ->join('View_Divisi_Sub_Divisi as vdsd', 'approver.ID_Divisi_Sub_Divisi', '=', 'vdsd.ID_DIVISI_SUB_DIVISI')
                ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vgslj', 'approver.ID_Level_Jabatan', '=', 'vgslj.ID_Level_Jabatan')
                ->where('afp.Kode_Karyawan_Requester', $requesterKode)
                ->select(
                    'afp.Id_Approval_Flow',
                    'afp.Kode_Karyawan_Approver',
                    'approver.Nama as Nama_Approver',
                    'vdsd.nama_divisi as Divisi_Approver',
                    'vgslj.nama_jabatan as Jabatan_Approver',
                    'afp.order_flow'
                )
                ->orderBy('afp.order_flow', 'asc')
                ->get();
            // dd($flow);

            $encodedFlow = $flow->map(function ($item) {
                $item->hashed_id = Hashids::connection('custom')->encode($item->Id_Approval_Flow);
                $item->hashed_approver_id = Crypt::encrypt($item->Kode_Karyawan_Approver);

                return $item;
            });

            return ResponseHelper::success($encodedFlow, 'Alur persetujuan berhasil diambil.');

        } catch (\Exception $e) {
            Log::error('Error getting approval flow: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil data alur persetujuan.', 500);
        }
    }

    /**
     * Menyimpan atau memperbarui alur persetujuan.
     */
    public function saveApprovalFlow(Request $request)
    {
        $validated = $request->validate([
            'requester_id' => 'required|string',
            'approvers' => 'required|array',
            'approvers.*.hashed_approver_id' => 'required|string',
            'approvers.*.order_flow' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $decodedRequesterId = Hashids::connection('custom')->decode($validated['requester_id']);
            if (empty($decodedRequesterId)) {
                DB::rollBack();

                return ResponseHelper::error('ID Requester tidak valid.', 400);
            }
            $requesterKode = $decodedRequesterId[0];

            // Hapus flow lama untuk requester ini
            DB::table('KPI_Approval_Flow_Parameter')
                ->where('Kode_Karyawan_Requester', $requesterKode)
                ->delete();

            // Insert flow baru
            foreach ($validated['approvers'] as $approverData) {
                $decodedApproverId = Hashids::connection('custom')->decode($approverData['hashed_approver_id']);
                if (empty($decodedApproverId)) {
                    throw new \Exception('ID Approver tidak valid: '.$approverData['hashed_approver_id']);
                }
                $approverKode = $decodedApproverId[0];

                DB::table('KPI_Approval_Flow_Parameter')->insert([
                    'Kode_Karyawan_Requester' => $requesterKode,
                    'Kode_Karyawan_Approver' => $approverKode,
                    'order_flow' => $approverData['order_flow'],
                    'Status' => 'Y', // Asumsi status default adalah aktif
                ]);
            }

            DB::commit();

            return ResponseHelper::success(null, 'Alur persetujuan berhasil disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving approval flow: '.$e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan saat menyimpan data.', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }
}
