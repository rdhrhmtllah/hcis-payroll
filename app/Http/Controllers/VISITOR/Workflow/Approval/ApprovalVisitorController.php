<?php

namespace App\Http\Controllers\VISITOR\Workflow\Approval;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use App\Services\VISITOR\Workflow\Errors\VisitorApiFailure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @deprecated 2026-07-29 Digantikan oleh VerifikatorVisitorController (pool verifikator
 * gabungan permit+tamu).
 *
 * Yang TERSISA di sini hanya pembacaan: getList(), getStats(), dan show().
 * Seluruh mutasinya sudah dipensiunkan — rute POST-nya diikat ke closure 410
 * di routes/VISITOR/Workflow/Approval/ApprovalVisitorWeb.php, dan metodenya
 * sendiri sudah dihapus.
 *
 * index() dan halaman approvalPermit.vue yang direndernya SUDAH DIHAPUS:
 * rute GET-nya mengalihkan ke /verifikator-visitor, jadi halaman itu tidak
 * pernah dirender, dan 1.037 baris Vue-nya hanya menyimpan tiruan alur
 * approval yang sudah menyimpang dari yang berlaku — termasuk tombol yang
 * menembak endpoint checklist yang kini membalas 410.
 *
 * Endpoint BACA (getList/getStats/show) sengaja dipertahankan sebagai
 * permukaan kompatibilitas; lihat komentar di berkas rutenya.
 *
 * Jangan dipakai untuk fitur baru — gunakan VerifikatorVisitorController.
 */
class ApprovalVisitorController extends Controller
{
    public function __construct(
        protected VisitorPermitQueryService $permitQuery,
    ) {}

    public function getList(Request $request)
    {
        try {
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 15);
            $status = $request->input('status', '');
            $tipe = $request->input('tipe', '');
            $search = $request->input('search', '');

            $query = $this->permitQuery->basePermitQuery();

            if ($status) {
                $query->whereRaw($this->permitQuery->permitStatusSql().' = ?', [$status]);
            }

            if ($tipe) {
                $query->where('vt.Nama_Tipe_Kendaraan', 'LIKE', "%{$tipe}%");
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('k.Nama', 'LIKE', "%{$search}%")
                        ->orWhere('h.No_Transaksi', 'LIKE', "%{$search}%")
                        ->orWhere(DB::raw("CONCAT(ISNULL(d.Plat_Nomor_Prefix, ''), ' ', ISNULL(d.Plat_Nomor_Angka, ''), ' ', ISNULL(d.Plat_Nomor_Suffix, ''))"), 'LIKE', "%{$search}%")
                        ->orWhere('vm.Nama_Model_Kendaraan', 'LIKE', "%{$search}%");
                });
            }

            $total = $query->count();
            $data = $query
                ->orderByDesc('h.Created_At')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get();

            return ResponseHelper::successWithPaginationV2($data, $page, $limit, $total);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'legacy_approval_list', 'VISITOR_APPROVAL_LIST_FAILED', 'Daftar approval Visitor gagal dimuat.');
        }
    }

    public function show(string $id)
    {
        try {
            $header = $this->permitQuery->basePermitQuery()
                ->where('h.Id_Transaksi_Permit', $id)
                ->first();

            if (! $header) {
                return ResponseHelper::error('Data tidak ditemukan.', 404);
            }

            return ResponseHelper::success([
                'permit' => $header,
                'dokumens' => $this->permitQuery->getDokumens($header->No_Transaksi),
                'checklists' => $this->permitQuery->getPermitChecklists($header->No_Transaksi),
                'approvals' => $this->permitQuery->getApprovals($header->No_Transaksi),
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'legacy_approval_detail', 'VISITOR_APPROVAL_DETAIL_FAILED', 'Detail approval Visitor gagal dimuat.');
        }
    }

    /*
     * Metode mutasi lama (action/approve/reject/updateChecklist) DIHAPUS.
     *
     * Keempatnya sudah tidak terjangkau: seluruh rute POST-nya diikat ke
     * closure 410 di routes/VISITOR/Workflow/Approval/ApprovalVisitorWeb.php,
     * dan `php artisan route:list --path=approvalvisitor` memperlihatkan
     * keempat baris itu tanpa aksi controller sama sekali.
     *
     * Yang dihapus bukan sekadar kode menganggur. approve() menulis dalam DUA
     * langkah tanpa transaksi — ApprovalService::approve() lalu UPDATE
     * Flag_Approval terpisah — sehingga kegagalan di antaranya meninggalkan
     * approval yang tercatat dengan flag header kosong. Selama mati ia tak
     * membahayakan, tetapi siapa pun yang kelak menyambung kembali rutenya
     * akan menghidupkan cacat itu tanpa tahu.
     *
     * Penggantinya sudah ada dan benar: VisitorApprovalDecisionService
     * membungkus setiap keputusan dalam satu transaksi DAN memeriksa ulang
     * Flag_Approval di dalamnya, sehingga penjaga dan penulisnya atomik.
     */

    public function getStats()
    {
        try {
            $base = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('Flag_Aktif', 'Y');
            $pending = (clone $base)->whereNull('Flag_Approval')->count();
            $approved = (clone $base)->where('Flag_Approval', 'Y')->count();
            $rejected = (clone $base)->where('Flag_Approval', 'T')->count();

            return ResponseHelper::success([
                'menunggu_review' => $pending,
                'total_disetujui' => $approved,
                'ditolak' => $rejected,
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'legacy_approval_stats', 'VISITOR_APPROVAL_STATS_FAILED', 'Statistik approval gagal dimuat.');
        }
    }
}
