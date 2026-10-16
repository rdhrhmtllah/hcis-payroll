<?php

namespace App\Http\Controllers\ESS\Attendance\Leave\Admin;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\Whatsapp;
use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Models\ESS\Employee\Models\Karyawan;
use App\Services\ESS\Attendance\Leave\Shared\TanggalTransaksiIzin;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\str;

class AdminCutiController extends Controller
{
    private TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function index()
    {
        return inertia('ESS/Attendance/Leave/Cuti/Admin/CutiAdmin');
    }

    public function getKaryawan(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $search = strip_tags($validated['search'] ?? '');
            $searchColumns = ['k.Nama', 'vd.nama_divisi', 'vd.nama_sub_divisi', 'vg.nama_level', 'vg.nama_jabatan'];

            $baseQuery = DB::table('Karyawan as k')
                ->join('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
                ->join(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'vg.Id_Level_Jabatan',
                    '=',
                    'k.Id_Level_Jabatan',
                )
                ->select(
                    'k.Nama',
                    'k.Kode_Karyawan',
                    'vd.nama_divisi',
                    'vd.nama_sub_divisi',
                    'vg.nama_level',
                    'vg.nama_jabatan',
                )
                ->where('k.Aktif', 'Y');

            if (! empty($validated['search'])) {
                $search = $validated['search'];

                $baseQuery->where(function ($query) use ($searchColumns, $search) {
                    foreach ($searchColumns as $col) {
                        $query->orWhere($col, 'LIKE', "%{$search}%");
                    }
                });
            }
            $allKaryawan = $baseQuery->limit(10)->get();
            // dd($allKaryawan);
            $encodedData = collect($allKaryawan)->map(function ($e) {
                $e->Kode_Karyawan = Crypt::encryptString($e->Kode_Karyawan);

                return $e;
            });

            return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('cutiController')->error(
                'Gagal mengambil data karyawan: '.$e->getMessage().' on line '.$e->getLine(),
            );

            return ResponseHelper::error('Gagal mengambil data karyawan.', 500);
        }
    }

    public function getKuotaCuti(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = strip_tags($validated['search'] ?? '');
        $searchColumns = ['k.Nama', 'vd.nama_divisi', 'vd.nama_sub_divisi', 'vg.nama_level', 'vg.nama_jabatan'];

        $baseQuery = DB::table('Karyawan as k')
            ->join('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'k.Id_Level_Jabatan')
            ->select(
                'k.Nama',
                'k.Kode_Karyawan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                'vg.nama_level',
                'vg.nama_jabatan',
            )
            ->where('k.Aktif', 'Y');
        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $baseQuery->where(function ($query) use ($searchColumns, $search) {
                foreach ($searchColumns as $col) {
                    $query->orWhere($col, 'LIKE', "%{$search}%");
                }
            });
        }
        $allKaryawan = $baseQuery->limit(10)->get();
        $encodedData = collect($allKaryawan)->map(function ($e) {
            $e->Kode_Karyawan = Crypt::encryptString($e->Kode_Karyawan);

            return $e;
        });

        return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
    }

    public function getDetailKuotaCuti(Request $request)
    {
        $validated = $request->validate([
            'Kode_Karyawan' => ['required', 'string'],
        ]);

        $Kode_Karyawan = Crypt::decryptString($validated['Kode_Karyawan']);

        $dataCuti = DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $Kode_Karyawan)
            ->orderBy('tanggal_expired', 'asc')
            ->get();

        return ResponseHelper::success($dataCuti, 'Data Ditemukan', 200);
    }

    public function addKuotaCuti(Request $request)
    {
        $validated = $request->validate([
            'Kode_Karyawan' => ['required', 'string'],
            'tipe_cuti' => ['required', 'string'],
            'tanggal_diberikan' => ['required', 'date'],
            'tanggal_expired' => ['required', 'date'],
            'jumlah_awal_cuti' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $Kode_Karyawan = Crypt::decryptString($validated['Kode_Karyawan']);

        try {
            $idJenisCuti = $this->resolveJenisCutiId($validated['tipe_cuti']);
            $adminKode = optional(optional(Auth::user())->karyawan)->Kode_Karyawan ?? 'SYSTEM_ADMIN';

            // Manual kuota add wajib lewat manualAdjustment agar jejak mutasi konsisten.
            $this->transactionService->manualAdjustment(
                $Kode_Karyawan,
                $idJenisCuti,
                'ADD',
                (float) $validated['jumlah_awal_cuti'],
                $validated['notes'] ?? 'Manual Add Kuota Cuti',
                $adminKode,
                $validated['tanggal_diberikan'] ?? null,
            );

            return ResponseHelper::success(null, 'Berhasil menambahkan kuota cuti', 200);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            return ResponseHelper::error($th->getMessage(), 500);
        }
    }

    public function mengurangiKuotaCuti(Request $request)
    {
        $validated = $request->validate([
            'Kode_Karyawan' => ['required', 'string'],
            'jumlah_pengurangan' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $Kode_Karyawan = Crypt::decryptString($validated['Kode_Karyawan']);
        $jumlah_pengurangan = $validated['jumlah_pengurangan'];

        try {
            $adminKode = optional(optional(Auth::user())->karyawan)->Kode_Karyawan ?? 'SYSTEM_ADMIN';

            // Manual kuota subtract wajib lewat manualAdjustment agar bisa dibedakan dari proses approval otomatis.
            $this->transactionService->manualAdjustment(
                $Kode_Karyawan,
                TransactionService::ID_TAHUNAN,
                'SUB',
                (float) $jumlah_pengurangan,
                $validated['notes'] ?? 'Manual Subtract Kuota Cuti',
                $adminKode,
            );

            return ResponseHelper::success(null, 'Berhasil mengurangi kuota cuti', 200);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());

            return ResponseHelper::error($th->getMessage(), 500);
        }
    }

    public function deleteKuotaCuti(Request $request)
    {
        $validated = $request->validate([
            'Id_Buku_Cuti' => ['required', 'integer'],
        ]);

        DB::beginTransaction();
        try {
            $row = DB::table('HRIS_Buku_Cuti')
                ->where('Id_Buku_Cuti', $validated['Id_Buku_Cuti'])
                ->lockForUpdate()
                ->first();

            if (! $row) {
                DB::rollBack();

                return ResponseHelper::error('Data kuota cuti tidak ditemukan', 404);
            }

            if ($row->Flag_Aktif == 'T' && (float) $row->sisa_cuti == 0.0) {
                DB::commit();

                return ResponseHelper::success(null, 'Kuota cuti sudah tidak aktif', 200);
            }

            $saldoSebelum = (float) $row->sisa_cuti;
            $adminKode = optional(optional(Auth::user())->karyawan)->Kode_Karyawan ?? 'SYSTEM_ADMIN';
            $bulanTahun = Carbon::now()->format('m-y');
            $refNo = $this->transactionService->getNoTransaction(
                'N_HRIS_Log_Buku_Cuti',
                'VOID',
                'No_Transaksi_Ref',
                $bulanTahun,
            );
            $reason = 'Void kuota cuti oleh admin';
            $notes = trim((string) ($row->notes ?? ''));

            DB::table('HRIS_Buku_Cuti')
                ->where('Id_Buku_Cuti', $row->Id_Buku_Cuti)
                ->update([
                    'sisa_cuti' => 0,
                    'Flag_Aktif' => 'T',
                    'updated_at' => now(),
                    'notes' => $notes ? "{$notes} | {$reason}" : $reason,
                ]);

            $this->transactionService->recordBalanceMutation(
                $row->Id_Buku_Cuti,
                $refNo,
                $saldoSebelum < 0 ? 'CREDIT' : 'DEBIT',
                'ADMIN_VOID_BALANCE',
                abs($saldoSebelum),
                $saldoSebelum,
                0,
                $reason,
                $adminKode,
                null,
                now(),
            );

            DB::commit();

            return ResponseHelper::success(null, 'Berhasil menonaktifkan kuota cuti', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage());

            return ResponseHelper::error($th->getMessage(), 500);
        }
    }

    // public function getPengajuan(Request $request){
    //      try {
    //         $validated = $request->validate([
    //             'search' => ['nullable', 'string', 'max:100'],
    //             'status' => ['nullable', 'string', 'max:100'],
    //             'jenis_cuti' => ['nullable', 'string', 'max:100'],
    //             'page' => ['nullable', 'integer', 'min:1'],
    //         ]);

    //         $search = strip_tags($validated['search'] ?? '');
    //         $status = $validated['status'] ?? '';
    //         $jenis_cuti = $validated['jenis_cuti'] ?? '';
    //         $limit = 10;
    //         $page = $validated['page'] ?? 1;
    //         $offset = ($page - 1) * $limit;
    //         $searchColoms = [
    //             'nama_karyawan',
    //             'No_Transaksi',
    //             'jenis_cuti'
    //         ];

    //         $hcOpsGroupId = 100;

    //         // CTE untuk menemukan transaksi yang relevan untuk HC Ops
    //         $eligibleTransactionsCte = "
    //             WITH RelevantTransactions AS (
    //                 SELECT
    //                     ar.No_Transaksi,
    //                     ar.Request_Order_Flow AS HcOpsOrderFlow
    //                 FROM HRIS_Approval_Request ar
    //                 WHERE ar.Flag_Group = 'Y'
    //                   AND ar.Group_Reference_Id = ?
    //                   AND ar.Flag_Approval IS NULL -- Yang masih pending di HC Ops
    //             ),
    //             ApprovalStatus AS (
    //                 SELECT
    //                     No_Transaksi,
    //                     -- Cek apakah semua flow SEBELUM HC Ops sudah di-approve
    //                     CASE
    //                         WHEN NOT EXISTS (
    //                             SELECT 1
    //                             FROM HRIS_Approval_Request ar_prev
    //                             WHERE ar_prev.No_Transaksi = rt.No_Transaksi
    //                               AND ar_prev.Request_Order_Flow < rt.HcOpsOrderFlow
    //                               AND (ar_prev.Flag_Approval IS NULL OR ar_prev.Flag_Approval = 'T')
    //                         ) THEN 1
    //                         ELSE 0
    //                     END AS IsHcOpsTurn
    //                 FROM RelevantTransactions rt
    //             )
    //             SELECT ast.No_Transaksi
    //             FROM ApprovalStatus ast
    //             WHERE ast.IsHcOpsTurn = 1
    //         ";

    //         $eligibleTransactions = collect(DB::select($eligibleTransactionsCte, [$hcOpsGroupId]))->pluck('No_Transaksi');

    //         if ($eligibleTransactions->isEmpty()) {
    //             return ResponseHelper::successWithPaginationV2([], $page, $limit, 0, "Tidak ada pengajuan yang memerlukan persetujuan.", 200);
    //         }

    //         // CTE untuk mengambil semua data yang dibutuhkan
    //         $dataCte = "
    //             WITH PengajuanCuti AS (
    //                 SELECT
    //                     b.No_Transaksi,
    //                     k.Nama as nama_karyawan,
    //                     k.Kode_Karyawan as kode_karyawan,
    //                     vdsd.nama_divisi as divisi,
    //                     vglj.nama_level as level,
    //                     a.Jenis AS jenis_cuti,
    //                     a.Tanggal_Cuti_Dari AS tanggal_mulai,
    //                     a.Tanggal_Cuti_Sampai AS tanggal_selesai,
    //                     DATEDIFF(DAY, a.Tanggal_Cuti_Dari, a.Tanggal_Cuti_Sampai) + 1 AS jumlah_hari,
    //                     a.Alasan AS alasan,
    //                     b.Tanggal AS created_at,
    //                     a.filePath,
    //                     jc.Deskripsi as deskripsi_cuti,
    //                     ISNULL(
    //                         (
    //                             SELECT TOP 1 ar.Request_Order_Flow
    //                             FROM HRIS_Approval_Request ar
    //                             WHERE ar.No_Transaksi = b.No_Transaksi
    //                             AND ar.Flag_Approval IS NOT NULL
    //                             ORDER BY ar.Request_Order_Flow DESC
    //                         ),
    //                         0
    //                     ) AS currentFlow,
    //                     (
    //                         SELECT MAX(ar.Request_Order_Flow)
    //                         FROM HRIS_Approval_Request ar
    //                         WHERE ar.No_Transaksi = b.No_Transaksi
    //                     ) AS maxFlow,
    //                     CASE
    //                         WHEN EXISTS (
    //                             SELECT 1
    //                             FROM HRIS_Approval_Request
    //                             WHERE No_Transaksi = b.No_Transaksi
    //                             AND Flag_Approval = 'T'
    //                         ) THEN 'rejected'
    //                         WHEN NOT EXISTS (
    //                             SELECT 1
    //                             FROM HRIS_Approval_Request
    //                             WHERE No_Transaksi = b.No_Transaksi
    //                             AND Flag_Approval IS NULL
    //                         ) THEN 'approved'
    //                         ELSE 'pending'
    //                     END AS status,
    //                     (
    //                         SELECT
    //                             COALESCE(ar.Approver_Karyawan_Kode, 'GROUP:' + CAST(ar.Group_Reference_Id AS VARCHAR(10))) AS approver_id,
    //                             COALESCE(
    // 								k_approver.Nama,
    // 								(
    // 									SELECT TOP 1 g.Keterangan
    // 									FROM N_HRIS_HC_Approval g
    // 									WHERE g.Group_Id = ar.Group_Reference_Id
    //                                     AND ar.Flag_Group = 'Y'
    // 								),
    // 								'Unknown'
    // 							) AS approver_name,
    //                             ISNULL(ar.Flag_Approval, 'N') AS approval_status,
    //                             ar.Request_Order_Flow AS order_flow,
    //                             ar.Flag_Group AS is_group
    //                         FROM HRIS_Approval_Request ar
    //                         LEFT JOIN Karyawan k_approver ON ar.Approver_Karyawan_Kode = k_approver.Kode_Karyawan
    //                         LEFT JOIN N_HRIS_HC_Approval g ON ar.Group_Reference_Id = g.Group_Id AND ar.Flag_Group = 'Y'
    //                         WHERE ar.No_Transaksi = b.No_Transaksi
    //                         ORDER BY ar.Request_Order_Flow
    //                         FOR JSON PATH
    //                     ) AS approvers
    //                 FROM Transaksi_Cuti b
    //                 INNER JOIN Transaksi_Cuti_Detail a ON b.No_Transaksi = a.No_Transaksi
    //                 INNER JOIN Karyawan k ON a.Kode_Karyawan = k.Kode_Karyawan
    //                 INNER JOIN View_Divisi_Sub_Divisi as vdsd ON k.ID_Divisi_Sub_Divisi = vdsd.ID_DIVISI_SUB_DIVISI
    //                 INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan as vglj ON k.Id_Level_Jabatan = vglj.Id_Level_Jabatan
    //                 LEFT JOIN N_HRIS_Jenis_Cuti jc ON a.Id_Jenis_Cuti = jc.Id_Jenis_Cuti
    //                 --WHERE b.Flag_Approval IS NULL
    //                 WHERE b.No_Transaksi IN ('" . implode("','", $eligibleTransactions->toArray()) . "')
    //                 AND b.Status IS NULL
    //             )
    //         ";

    //         $params = [];
    //         $whereClause = "where 1=1";
    //         if($search){
    //             $searchParam = '%' . $search .'%';
    //             $likeParts = [];
    //             foreach($searchColoms as $col){
    //                 $likeParts[]= "$col Like ?";
    //                 $params[] = $searchParam;
    //             }

    //             $likeQuery = implode(' OR ', $likeParts);
    //             $whereClause .= " AND ($likeQuery) ";
    //         }

    //         if($status){
    //             $whereClause .= " AND status = ?";
    //             $params[] = $status;
    //         }
    //         if($jenis_cuti){
    //             $whereClause .= " AND jenis_cuti = ?";
    //             $params[] = $validated['jenis_cuti'];
    //         }

    //         $countQuery = $dataCte . " SELECT COUNT(*) AS total FROM PengajuanCuti $whereClause";
    //         $total = DB::select($countQuery, $params)[0]->total;

    //         $dataQuery = $dataCte . " SELECT * FROM PengajuanCuti $whereClause ORDER BY created_at DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    //         $params[] = $offset;
    //         $params[] = $limit;

    //         $pengajuanList = collect(DB::select($dataQuery, $params))->map(function ($item) {
    //             $item->approvers = json_decode($item->approvers);
    //             // Anda bisa menambahkan logika untuk file_url di sini jika diperlukan
    //             return $item;
    //         });

    //         return ResponseHelper::successWithPaginationV2(
    //             $pengajuanList,
    //             $page,
    //             $limit,
    //             $total,
    //             "Data pengajuan berhasil diambil.",
    //             200
    //         );
    //     } catch (\Exception $e) {
    //         Log::channel('cutiController')->error('Gagal mengambil data pengajuan: ' . $e->getMessage() . ' on line ' . $e->getLine());
    //         return ResponseHelper::error('Gagal mengambil data pengajuan.', 500);
    //     }
    // }

    /**
     * DETEKSI (READ-ONLY): transaksi cuti yang jumlah baris per-hari (Transaksi_Cuti_Det)
     * MELEBIHI submitted_days pada Calendar_Audit_Json — indikasi bug over-booking hari
     * (hari kerja yang di-deselect user ikut terbooking → saldo terpotong berlebih).
     *
     * Baris tanpa Calendar_Audit_Json (transaksi lama / NULL) di-skip (tak bisa dinilai).
     * Endpoint ini TIDAK mengubah data apa pun. GET /api/v1/cuti/admin/audit/extra-days
     */
    public function getExtraDaysAudit(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $limit = 20;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $limit;
            $search = strip_tags($validated['search'] ?? '');

            // Sinyal bug = inkonsistensi audit: server memilih lebih banyak hari daripada
            // yang disubmit user. JSON path '$.summary.*' aman di double-quote ('$.' = literal).
            // day_rows (LEFT JOIN) = jumlah baris Transaksi_Cuti_Det aktual sekarang (bisa
            // beda bila baris sudah dibatalkan/diubah) → untuk cek apakah saldo masih terdampak.
            $where =
                'WHERE d.Calendar_Audit_Json IS NOT NULL AND ISJSON(d.Calendar_Audit_Json) = 1 '.
                "AND TRY_CAST(JSON_VALUE(d.Calendar_Audit_Json, '$.summary.submitted_days') AS INT) IS NOT NULL ".
                "AND TRY_CAST(JSON_VALUE(d.Calendar_Audit_Json, '$.summary.selected_work_days') AS INT) ".
                "  > TRY_CAST(JSON_VALUE(d.Calendar_Audit_Json, '$.summary.submitted_days') AS INT) ";

            $bindings = [];
            if ($search !== '') {
                $where .= 'AND (d.No_Transaksi LIKE ? OR d.Kode_Karyawan LIKE ?) ';
                $bindings = ['%'.$search.'%', '%'.$search.'%'];
            }

            $from =
                'FROM dbo.Transaksi_Cuti_Detail d '.
                'LEFT JOIN (SELECT No_Transaksi, COUNT(*) AS day_rows FROM dbo.Transaksi_Cuti_Det GROUP BY No_Transaksi) det '.
                'ON det.No_Transaksi = d.No_Transaksi ';

            $total = (int) (DB::selectOne("SELECT COUNT(*) AS c $from $where", $bindings)->c ?? 0);

            $rows = DB::select(
                'SELECT d.No_Transaksi, d.Kode_Karyawan, d.Id_Jenis_Cuti, ISNULL(det.day_rows, 0) AS day_rows, '.
                "TRY_CAST(JSON_VALUE(d.Calendar_Audit_Json, '$.summary.submitted_days') AS INT) AS submitted_days, ".
                "TRY_CAST(JSON_VALUE(d.Calendar_Audit_Json, '$.summary.selected_work_days') AS INT) AS selected_work_days, ".
                "TRY_CAST(JSON_VALUE(d.Calendar_Audit_Json, '$.summary.selected_work_days') AS INT) ".
                "  - TRY_CAST(JSON_VALUE(d.Calendar_Audit_Json, '$.summary.submitted_days') AS INT) AS extra_days ".
                "$from $where ".
                'ORDER BY extra_days DESC, d.No_Transaksi DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY',
                array_merge($bindings, [$offset, $limit]),
            );

            return ResponseHelper::successWithPaginationV2(
                $rows,
                $page,
                $limit,
                $total,
                'Transaksi cuti dengan kelebihan hari (day_rows > submitted_days).',
                200,
            );
        } catch (\Throwable $e) {
            Log::error('getExtraDaysAudit error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil data audit kelebihan hari cuti.', 500);
        }
    }

    public function getPengajuan(Request $request)
    {
        // 1. Validasi Input
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'jenis_cuti' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $limit = 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $limit;

            $search = strip_tags($validated['search'] ?? '');
            $statusFilter = $validated['status'] ?? ''; // approved/rejected/pending
            $jenisCutiFilter = $validated['jenis_cuti'] ?? '';

            $currentUserId = Auth::user()->Id_Users;

            $query = DB::table('N_HRIS_VW_All_Transactions as t')
                ->join('N_HRIS_VW_Approval_Request as ar', 't.No_Transaksi', '=', 'ar.No_Transaksi')
                ->join('HRIS_Approval_Request as req', 'req.id', '=', 'ar.Id_Approval_Request')
                ->join('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'req.Id_Jenis_Approval')
                ->join('N_HRIS_Role_Functional as rf', function ($join) {
                    $join
                        ->on('rf.Id_Role', '=', 'req.Group_Reference_Id')
                        ->on('rf.Role_Code', '=', 'ja.Source_Reference');
                })
                ->join('N_HRIS_Role_User as ru', 'ru.Id_Role', '=', 'rf.Id_Role')
                ->where('ar.Flow_State', 'CURRENT')
                ->whereNull('ar.Flag_Approval')
                ->whereNull('t.Status_Header')
                ->where('req.Flag_Group', 'Y')
                ->where('ja.Source_Type', 'ROLE')
                ->where('ja.Flag_Aktif', 'Y')
                ->where('rf.Flag_Aktif', 'Y')
                ->where('ru.Flag_Aktif', 'Y')
                ->where('ru.UserId_Web', $currentUserId)
                ->select('t.*', 'ar.Request_Order_Flow', 'ar.Current_Approver_Kode', 'rf.Role_Name', 'rf.Role_Code')
                ->distinct();

            // 3. FILTER SEARCH
            if ($search) {
                $searchParam = '%'.$search.'%';
                $query->where(function ($q) use ($searchParam) {
                    $q->where('t.Nama_Requester', 'like', $searchParam)
                        ->orWhere('t.No_Transaksi', 'like', $searchParam)
                        ->orWhere('t.Sub_Tipe', 'like', $searchParam);
                });
            }

            if ($jenisCutiFilter) {
                $query->where('t.Sub_Tipe', $jenisCutiFilter);
            }

            $total = $query->count();

            $items = $query->orderBy('t.Created_At', 'DESC')->offset($offset)->limit($limit)->get();

            if ($items->isEmpty()) {
                return ResponseHelper::successWithPaginationV2([], $page, $limit, 0, 'Tidak ada pengajuan.', 200);
            }

            // Kita perlu query lagi ke View Approval Request untuk transaksi yang terpilih saja
            $transactionIds = $items->pluck('No_Transaksi')->toArray();

            $approvalHistory = DB::table('N_HRIS_VW_Approval_Request as ar')
                ->leftJoin('HRIS_Approval_Request as req', 'req.id', '=', 'ar.Id_Approval_Request')
                ->leftJoin('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'req.Id_Jenis_Approval')
                ->leftJoin('N_HRIS_Role_Functional as rf', function ($join) {
                    $join
                        ->on('rf.Id_Role', '=', 'req.Group_Reference_Id')
                        ->on('rf.Role_Code', '=', 'ja.Source_Reference');
                })
                ->leftJoin('Karyawan as k', 'ar.Current_Approver_Kode', '=', 'k.Kode_Karyawan')
                ->whereIn('ar.No_Transaksi', $transactionIds)
                ->select(
                    'ar.No_Transaksi',
                    'ar.Flag_Approval',
                    'ar.Request_Order_Flow',
                    'ar.Target_Group_Id',
                    'ar.Current_Approver_Kode',
                    'k.Nama as Nama_Personal_Approver',
                    'rf.Role_Name',
                    'rf.Role_Code',
                )
                ->orderBy('ar.Request_Order_Flow', 'ASC')
                ->get()
                ->groupBy('No_Transaksi');

            $formattedData = $items->map(function ($item) use ($approvalHistory) {
                $histories = $approvalHistory->get($item->No_Transaksi, collect());

                $statusListStr = $histories
                    ->map(function ($h) {
                        $name =
                            $h->Nama_Personal_Approver ??
                            ($h->Role_Name ??
                                ($h->Target_Group_Id ? 'Group '.$h->Target_Group_Id : $h->Current_Approver_Kode));

                        $st = $h->Flag_Approval ?? 'T';

                        return "{$name} : {$st}";
                    })
                    ->implode(', ');

                // Cari logic Previous Approver (Opsional, untuk field Status_Approval_Langkah_Sebelumnya)
                $currentOrder = $item->Request_Order_Flow;
                $prevStatus = $histories
                    ->where('Request_Order_Flow', '<', $currentOrder)
                    ->sortByDesc('Request_Order_Flow')
                    ->first();

                // URL File
                $fileUrl = null;
                if ($item->File_Path) {
                    try {
                        // $fileUrl = Storage::disk('gcs')->temporaryUrl($item->File_Path, now()->addMinutes(15));
                        $fileUrl = $item->File_Path;
                    } catch (\Exception $e) {
                    }
                }

                $tanggalArray = DB::table('Transaksi_Cuti_Det')
                    ->where('No_Transaksi', $item->No_Transaksi)
                    ->pluck('Tanggal_Cuti')
                    ->toArray();

                $dapartment = DB::table('Karyawan as a')
                    ->join('View_Divisi_Sub_Divisi as b', 'a.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
                    ->where('a.Kode_Karyawan', $item->Kode_Karyawan_Requester)
                    ->select('b.nama_Sub_divisi')
                    ->first();

                return [
                    'tanggal_create' => $item->Created_At,
                    'No_Transaksi' => $item->No_Transaksi,
                    'Approver_Kode_Karyawan' => Crypt::encryptString($item->Current_Approver_Kode ?? ''), // ID HC Ops/Group
                    'requester_Nama' => $item->Nama_Requester,
                    'Requester_Kode_Karyawan' => Crypt::encryptString($item->Kode_Karyawan_Requester),
                    'department' => $dapartment->nama_Sub_divisi ?? null,

                    'Tipe_Izin2' => method_exists($this, 'parseSubTipe')
                        ? $this->parseSubTipe($item->Sub_Tipe)
                        : $item->Sub_Tipe,
                    'Tipe_Izin' => $item->Sub_Tipe,
                    'Alasan' => $item->Deskripsi,
                    'TanggalIzin' => $item->Tanggal_Mulai,
                    'Tanggal_Selesai' => $item->Tanggal_Selesai,

                    'Lampiran' => $item->File_Path,
                    'Lampiran_Url' => $fileUrl,

                    'MaxFlow' => $histories->max('Request_Order_Flow') ?? 0,
                    'currentFlow' => $item->Request_Order_Flow,

                    // Status Global Transaksi
                    'status' => method_exists($this, 'parseIzin')
                        ? $this->parseIzin($item->Flag_Approval)
                        : $item->Flag_Approval,

                    'Approver' => $item->Role_Name ?? ($item->Role_Code ?? 'Role Approval'),
                    'Status_Approval_Saat_Ini' => 'Pending',
                    'Jam_Mulai' => $item->Jam_Diajukan,
                    'Status_Approval_Langkah_Sebelumnya' => $prevStatus->Flag_Approval ?? null,
                    'order_flow' => $item->Request_Order_Flow,
                    'STATUSLIST' => $statusListStr,
                    'tanggalReal' => FormatTanggalHelper::formatTanggalMultiple($tanggalArray),
                ];
            });

            // dd($formattedData);
            return ResponseHelper::successWithPaginationV2(
                $formattedData,
                $page,
                $limit,
                $total,
                'Data pengajuan berhasil diambil.',
                200,
            );
        } catch (\Exception $e) {
            Log::channel('cutiController')->error('Gagal mengambil data pengajuan: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil data pengajuan.', 500);
        }
    }

    private function parseIzin($status)
    {
        if ($status == 'Y') {
            return 'approved';
        } elseif ($status == 'T') {
            return 'rejected';
        } elseif ($status == 'P') {
            return 'pending';
        }

        return 'pending';
    }

    // public function getDetailPengajuan($id){

    //     $No_Transaksi = $id;

    //     $dataCuti = DB::table('Transaksi_Cuti as tc')
    //                     ->join('Transaksi_Cuti_Detail as tcd', 'tc.No_Transaksi', '=', 'tcd.No_Transaksi')
    //                     ->join('Karyawan as k', 'tcd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
    //                     ->join('View_Divisi_Sub_Divisi as vdsd', 'k.ID_Divisi_Sub_Divisi', '=', 'vdsd.ID_DIVISI_SUB_DIVISI')
    //                     ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vglj', 'k.ID_Level_Jabatan', '=', 'vglj.ID_Level_Jabatan')
    //                     ->leftJoin('N_HRIS_Jenis_Cuti as njc', 'tcd.Jenis', '=', 'njc.Nama_Jenis_Cuti')
    //                     ->select(
    //                         'tc.No_Transaksi',
    //                         'k.Nama as nama_karyawan',
    //                         'k.Kode_Karyawan as kode_karyawan',
    //                         'vdsd.nama_divisi as divisi',
    //                         'vglj.nama_level as level',
    //                         'tcd.Jenis as jenis_cuti',
    //                         'tcd.Tanggal_Cuti_Dari as tanggal_mulai',
    //                         'tcd.Tanggal_Cuti_Sampai as tanggal_selesai',
    //                         DB::raw('DATEDIFF(DAY, tcd.Tanggal_Cuti_Dari, tcd.Tanggal_Cuti_Sampai) + 1 AS jumlah_hari'),
    //                         'tcd.Alasan as alasan',
    //                         'tc.Tanggal as created_at',
    //                         'tcd.filePath',
    //                         'njc.Deskripsi as deskripsi_cuti',
    //                         DB::raw("
    //                             CASE
    //                                 WHEN EXISTS (
    //                                     SELECT 1
    //                                     FROM HRIS_Approval_Request
    //                                     WHERE No_Transaksi = tc.No_Transaksi
    //                                     AND Flag_Approval = 'T'
    //                                 ) THEN 'rejected'
    //                                 WHEN NOT EXISTS (
    //                                     SELECT 1
    //                                     FROM HRIS_Approval_Request
    //                                     WHERE No_Transaksi = tc.No_Transaksi
    //                                     AND Flag_Approval IS NULL
    //                                 ) THEN 'approved'
    //                                 ELSE 'pending'
    //                             END AS status
    //                         "),
    //                         DB::raw("
    //                             (
    //                                 SELECT
    //                                     COALESCE(ar.Approver_Karyawan_Kode, 'GROUP:' + CAST(ar.Group_Reference_Id AS VARCHAR(10))) AS approver_id,
    //                                     COALESCE(k_approver.Nama, g.Keterangan, 'Unknown') AS approver_name,
    //                                     ISNULL(ar.Flag_Approval, 'N') AS approval_status,
    //                                     ar.Request_Order_Flow AS order_flow,
    //                                     ar.Flag_Group AS is_group
    //                                 FROM HRIS_Approval_Request ar
    //                                 LEFT JOIN Karyawan k_approver ON ar.Approver_Karyawan_Kode = k_approver.Kode_Karyawan
    //                                 LEFT JOIN N_HRIS_HC_Approval g ON ar.Group_Reference_Id = g.Group_Id AND ar.Flag_Group = 'Y'
    //                                 WHERE ar.No_Transaksi = tc.No_Transaksi
    //                                 ORDER BY ar.Request_Order_Flow
    //                                 FOR JSON PATH
    //                             ) AS approvers
    //                         ")
    //                     )
    //                     ->where('tc.No_Transaksi', $No_Transaksi)
    //                     ->first();

    //     if (!$dataCuti) {
    //         return ResponseHelper::error( "Data pengajuan tidak ditemukan.", 404);
    //     }

    //     $dataCuti->approvers = json_decode($dataCuti->approvers);

    //     return ResponseHelper::success($dataCuti, "Data Ditemukan", 200);
    // }

    public function getDetailPengajuan($id)
    {
        try {
            $No_Transaksi = $id;

            $item = DB::table('N_HRIS_VW_All_Transactions as t')->where('t.No_Transaksi', $No_Transaksi)->first();

            if (! $item) {
                return ResponseHelper::error('Data pengajuan tidak ditemukan.', 404);
            }

            $approverRows = DB::table('N_HRIS_VW_Approval_Request as ar')
                ->leftJoin('HRIS_Approval_Request as req', 'req.id', '=', 'ar.Id_Approval_Request')
                ->leftJoin('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'req.Id_Jenis_Approval')
                ->leftJoin('N_HRIS_Role_Functional as rf', function ($join) {
                    $join
                        ->on('rf.Id_Role', '=', 'req.Group_Reference_Id')
                        ->on('rf.Role_Code', '=', 'ja.Source_Reference');
                })
                ->leftJoin('Karyawan as k', 'ar.Current_Approver_Kode', '=', 'k.Kode_Karyawan')
                ->leftJoin('Karyawan as reviewer', 'reviewer.Kode_Karyawan', '=', 'req.Approved_By_Kode')
                ->where('ar.No_Transaksi', $item->No_Transaksi)
                ->select(
                    'ar.*',
                    'req.Approved_By_Kode',
                    'req.Tanggal_Approval',
                    'req.Catatan as Catatan_Approval',
                    'rf.Role_Name',
                    'rf.Role_Code',
                    'k.Nama as Nama_Approver',
                    'reviewer.Nama as Nama_Reviewer',
                    DB::raw("COALESCE(k.Nama, rf.Role_Name,
                        (SELECT TOP 1 g.Keterangan 
                        FROM N_HRIS_HC_Approval as g 
                        WHERE g.Group_Id = ar.Target_Group_Id), 
                    'Unknown') AS approver_name"),
                )
                ->orderBy('ar.Request_Order_Flow', 'ASC')
                ->get();

            $currentFlow = $approverRows->where('Flag_Approval', null)->sortBy('Request_Order_Flow')->first();
            $ownFlow = $approverRows->where('Role_Code', '!=', null)->first();
            $reviewedRoleFlow = $approverRows
                ->filter(fn ($row) => ! empty($row->Role_Code) && in_array($row->Flag_Approval, ['Y', 'T'], true))
                ->sortByDesc('Request_Order_Flow')
                ->first();

            if (! $currentFlow) {
                $currentFlow = $approverRows->last();
            }

            $previousApprover = $approverRows
                ->where('Request_Order_Flow', ($currentFlow->Request_Order_Flow ?? 0) - 1)
                ->first();

            $maxFlow = $approverRows->max('Request_Order_Flow');

            $approverPlaceholder = $approverRows
                ->map(function ($row) {
                    $name = $row->Nama_Approver ?? ($row->Role_Name ?? $row->Current_Approver_Kode);

                    return "{$name} : ".($row->Flag_Approval ?? 'T');
                })
                ->implode(', ');

            /*
             * Dulu baris ini berbunyi `method_exists($this, 'getTanggal') ? … : …`.
             * Syarat itu SELALU benar — metodenya didefinisikan di kelas yang sama —
             * jadi cabang cadangannya kode mati. Ia dibuang bersama metodenya:
             * membiarkannya justru berbahaya, karena sesudah metodenya pindah ke
             * rumah bersama syaratnya berbalik menjadi selalu SALAH, dan
             * tanggalReal serta jumlah_hari diam-diam menjadi null dan 0.
             */
            $tanggalDet = TanggalTransaksiIzin::ringkas($item->No_Transaksi);

            $fileUrl = null;
            if ($item->File_Path) {
                try {
                    $fileUrl = Storage::disk('gcs')->temporaryUrl($item->File_Path, now()->addMinutes(15));
                } catch (\Exception $e) {
                    $fileUrl = $item->File_Path;
                }
            }

            $formattedData = [
                'tanggal_create' => $item->Created_At,
                'No_Transaksi' => $item->No_Transaksi,
                'Approver_Kode_Karyawan' => Crypt::encryptString($currentFlow->Current_Approver_Kode ?? ''),
                'requester_Nama' => $item->Nama_Requester,
                'Requester_Kode_Karyawan' => Crypt::encryptString($item->Kode_Karyawan_Requester),
                'approvedBy' => $reviewedRoleFlow->Nama_Reviewer ?? ($reviewedRoleFlow->Approved_By_Kode ?? null),
                'Admin_Reviewer' => $reviewedRoleFlow->Nama_Reviewer ?? ($reviewedRoleFlow->Approved_By_Kode ?? '-'),
                'Tanggal_Review_Admin' => $reviewedRoleFlow->Tanggal_Approval ?? null,
                // Helper parsing (Cek method exist agar aman)
                'Tipe_Izin2' => method_exists($this, 'parseSubTipe')
                    ? $this->parseSubTipe($item->Sub_Tipe)
                    : $item->Sub_Tipe,
                'Tipe_Izin' => $item->Sub_Tipe,
                'Alasan' => $item->Deskripsi,
                'TanggalIzin' => $item->Tanggal_Mulai,
                'Tanggal_Selesai' => $item->Tanggal_Selesai,

                'Lampiran' => $item->File_Path ?? null,
                'Lampiran_Url' => $fileUrl,

                'MaxFlow' => $maxFlow ?? 0,
                'currentFlow' => $currentFlow->Request_Order_Flow ?? 0,

                // Mengambil status header transaksi
                'status' => method_exists($this, 'parseIzin')
                    ? $this->parseIzin($item->Flag_Approval)
                    : $item->Flag_Approval,

                'Approver' => $currentFlow->approver_name ?? ($currentFlow->Current_Approver_Kode ?? 'System'),
                'Status_Approval_Saat_Ini' => method_exists($this, 'parseIzin')
                    ? $this->parseIzin($currentFlow->Flag_Approval)
                    : $currentFlow->Flag_Approval ?? 'Pending',
                'Jam_Mulai' => $item->Jam_Diajukan ?? null,
                'Status_Approval_Langkah_Sebelumnya' => $previousApprover->Flag_Approval ?? null,
                'order_flow' => $currentFlow->Request_Order_Flow ?? 0,
                'STATUSLIST' => $approverPlaceholder,
                'tanggalReal' => $tanggalDet['tanggal'] ?? null,
                'jumlah_hari' => $tanggalDet['jumlahHari'] ?? 0,

                'approvers' => $approverRows,
            ];

            return ResponseHelper::success($formattedData, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('cutiController')->error(
                'Gagal mengambil detail pengajuan: '.$e->getMessage().' on line '.$e->getLine(),
            );

            return ResponseHelper::error('Gagal mengambil detail pengajuan.', 500);
        }
    }

    public function approvePengajuan(Request $request)
    {
        $validated = $request->validate([
            'No_Transaksi' => ['required', 'string'],
        ]);

        $No_Transaksi = $validated['No_Transaksi'];
        $user_id = Auth::user()->karyawan->Kode_Karyawan;
        $kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

        DB::beginTransaction();
        try {
            $approvalRequest = $this->getCurrentRoleApprovalForUser($No_Transaksi, true);

            if (! $approvalRequest) {
                DB::rollBack();

                return ResponseHelper::error('Pengajuan tidak ditemukan atau sudah diproses.', 404);
            }

            $updated = DB::table('HRIS_Approval_Request')
                ->where('id', $approvalRequest->id)
                ->whereNull('Flag_Approval')
                ->update([
                    'Flag_Approval' => 'Y',
                    'Tanggal_Approval' => Carbon::now(),
                    'Approved_By_Kode' => $user_id,
                    'update_at' => Carbon::now(),
                ]);

            if ($updated < 1) {
                DB::rollBack();

                return ResponseHelper::error('Pengajuan sudah diproses oleh approver lain.', 409);
            }

            // Cek apakah ada flow selanjutnya
            $nextApproval = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $No_Transaksi)
                ->where('Request_Order_Flow', '>', $approvalRequest->Request_Order_Flow)
                ->orderBy('Request_Order_Flow', 'asc')
                ->first();

            if ($nextApproval) {
                // Kirim notifikasi ke approver selanjutnya
                if ($nextApproval->Flag_Group == 'Y') {
                    // Ambil semua anggota grup dan kirim notifikasi
                    // $groupMembers = DB::table('N_HCIS_HC_Approval_Detail')
                    //     ->where('Group_Id', $nextApproval->Group_Reference_Id)
                    //     ->pluck('Kode_Karyawan');

                    // foreach ($groupMembers as $memberKode) {
                    //     $karyawan = DB::table('Karyawan')->where('Kode_Karyawan', $memberKode)->first();
                    //     if ($karyawan && !empty($karyawan->HP) && Str::startsWith($karyawan->HP, '628')) {
                    //         $nama_karyawan_pengaju = DB::table('Transaksi_Cuti_Detail as tcd')
                    //             ->join('Karyawan as k', 'tcd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                    //             ->where('tcd.No_Transaksi', $No_Transaksi)
                    //             ->value('k.Nama');

                    //         $pesan = [
                    //             "messaging_product" => "whatsapp",
                    //             "to" => $karyawan->HP,
                    //             "type" => "template",
                    //             "template" => [
                    //                 "name" => "notif_approval_cuti",
                    //                 "language" => ["code" => "en", "policy" => "deterministic"],
                    //                 "components" => [
                    //                     [
                    //                         "type" => "body",
                    //                         "parameters" => [
                    //                             ["type" => "text", "text" => $nama_karyawan_pengaju],
                    //                             ["type" => "text", "text" => $No_Transaksi],
                    //                         ]
                    //                     ]
                    //                 ]
                    //             ]
                    //         ];
                    //         $response = Whatsapp::send_message($pesan);
                    //         WhatsappLogHelper::logWhatsappResponse($karyawan->Kode_Karyawan, $pesan, $response, 'notif_approval_cuti');
                    //     }
                    // }
                } else {
                    // if ($nextApproval->Flag_Group == null || $nextApproval->Flag_Group == 'T') {
                    // Kirim notifikasi ke single approver
                    $karyawan = DB::table('Karyawan')
                        ->where('Kode_Karyawan', $nextApproval->Approver_Karyawan_Kode)
                        ->first();
                    if ($karyawan && ! empty($karyawan->HP) && Str::startsWith($karyawan->HP, '628')) {
                        $noFaktur = $nextApproval->No_Transaksi;
                        $jenis_cuti = DB::table('Transaksi_Cuti_Detail')
                            ->where('No_Transaksi', $noFaktur)
                            ->value('Id_Jenis_Cuti');
                        $Nama_Jenis_Cuti =
                            DB::table('N_HRIS_Jenis_Cuti')
                                ->where('Id_Jenis_Cuti', $jenis_cuti)
                                ->value('Nama_Jenis_Cuti') ?? 'Cuti';
                        $tanggalMulai = DB::table('Transaksi_Cuti_Detail')
                            ->where('No_Transaksi', $noFaktur)
                            ->value('Tanggal_Cuti_Dari');
                        $tanggalSelesai = DB::table('Transaksi_Cuti_Detail')
                            ->where('No_Transaksi', $noFaktur)
                            ->value('Tanggal_Cuti_Sampai');
                        $requester = DB::table('Karyawan')
                            ->where('Kode_Karyawan', $nextApproval->Kode_Karyawan)
                            ->first();

                        if (! $requester) {
                            Log::channel('cutiController')->warning(
                                'Requester tidak ditemukan untuk notifikasi approval cuti berikutnya.',
                                [
                                    'no_transaksi' => $noFaktur,
                                    'kode_karyawan' => $nextApproval->Kode_Karyawan,
                                ],
                            );
                            $requester = (object) [
                                'Nama' => 'Karyawan',
                                'Kode_Karyawan' => $nextApproval->Kode_Karyawan,
                            ];
                        }

                        $pesan = [
                            'messaging_product' => 'whatsapp',
                            'to' => $karyawan->HP,
                            'type' => 'template',
                            'template' => [
                                'name' => 'notif_approver_hcis',
                                'language' => [
                                    'code' => 'id',
                                    'policy' => 'deterministic',
                                ],
                                'components' => [
                                    [
                                        'type' => 'body',
                                        'parameters' => [
                                            ['type' => 'text', 'text' => $karyawan->Nama],
                                            ['type' => 'text', 'text' => $noFaktur],
                                            ['type' => 'text', 'text' => $Nama_Jenis_Cuti],
                                            [
                                                'type' => 'text',
                                                'text' => FormatTanggalHelper::convertDoubleDate(
                                                    $tanggalMulai,
                                                    $tanggalSelesai,
                                                ),
                                            ],
                                            ['type' => 'text', 'text' => $requester->Nama],
                                        ],
                                    ],
                                ],
                            ],
                        ];

                        $waResult = WhatsappLogHelper::sendAndLog(
                            $karyawan->Kode_Karyawan,
                            $karyawan->Nama,
                            $karyawan->HP,
                            $noFaktur,
                            $pesan,
                            function ($response) use ($requester, $noFaktur, $karyawan) {
                                // DB::table('HRIS_Approval_Request as a')
                                // ->where("a.No_Transaksi", $noFaktur)
                                // ->where("a.Kode_Karyawan_Approver", $karyawan->Kode_Karyawan)
                                // ->update(['a.angka_wa' => DB::raw('COALESCE(angka_wa, 0) + 1')]);
                                DB::table('HRIS_Approval_Request as a')
                                    ->leftJoin('HRIS_Approval_Flow as b', 'a.Flow_Id', 'b.id')
                                    ->where('a.No_Transaksi', $noFaktur)
                                    ->where(function ($q) use ($karyawan) {
                                        $q->where('a.Approver_Karyawan_Kode', $karyawan->Kode_Karyawan)->orWhere(
                                            'b.Kode_Karyawan',
                                            $karyawan->Kode_Karyawan,
                                        );
                                    })
                                    ->update([
                                        'a.angka_wa' => DB::raw('COALESCE(a.angka_wa, 0) + 1'),
                                    ]);

                                Log::channel('cutiController')->info(
                                    "WA sukses dikirim ke approver {$karyawan->Kode_Karyawan}",
                                    [
                                        'requester' => $requester->Kode_Karyawan,
                                        'response' => $response,
                                    ],
                                );
                            },
                            function ($reason, $response) use ($karyawan, $noFaktur, $pesan) {
                                Log::channel('cutiController')->warning(
                                    "Gagal kirim WA ke approver {$karyawan->Kode_Karyawan}",
                                    [
                                        'reason' => $reason,
                                        'response' => $response,
                                    ],
                                );

                                DB::table('Whatsapp_Log')->insert([
                                    'Kode_Karyawan_Destination' => $karyawan->Kode_Karyawan,
                                    'Nama' => $karyawan->Nama,
                                    'HP' => $karyawan->HP,
                                    'No_Transaksi' => $noFaktur,
                                    'Error_Message' => is_array($response)
                                        ? json_encode($response, JSON_UNESCAPED_UNICODE)
                                        : $reason ?? 'Unknown error',
                                    'RawMessage' => print_r($pesan, true),
                                    'RawMessageJson' => json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                                    'Created_At' => now(),
                                    'Is_Retried' => 0,
                                ]);
                            },
                        );
                    }
                }
            } else {
                // Tidak ada flow selanjutnya, berarti approval selesai
                // Update status di Transaksi_Cuti
                DB::table('Transaksi_Cuti_Detail')
                    ->where('No_Transaksi', $No_Transaksi)
                    ->update(['Flag_Approval' => 'Y']);

                // Kurangi kuota cuti karyawan
                $cutiDetail = DB::table('Transaksi_Cuti_Detail')->where('No_Transaksi', $No_Transaksi)->first();
                if ($cutiDetail) {
                    $kodeKaryawanPengaju = $cutiDetail->Kode_Karyawan;
                    $jumlahHariCuti = DB::table('Transaksi_Cuti_Det')->where('No_Transaksi', $No_Transaksi)->count();
                    $memotongSaldo = DB::table('N_HRIS_Jenis_Cuti')
                        ->where('Id_Jenis_Cuti', $cutiDetail->Id_Jenis_Cuti)
                        ->value('Memotong_Saldo_Cuti');

                    if ($memotongSaldo === 'Y' && $jumlahHariCuti > 0) {
                        $this->deductLeaveQuota(
                            $kodeKaryawanPengaju,
                            $jumlahHariCuti,
                            (int) ($cutiDetail->Id_Jenis_Cuti ?? TransactionService::ID_TAHUNAN),
                            $No_Transaksi,
                        );
                    }
                }

                // Kirim notifikasi ke karyawan pengaju bahwa cuti disetujui
                $karyawanPengaju = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawanPengaju)->first();
                if ($karyawanPengaju && ! empty($karyawanPengaju->HP) && Str::startsWith($karyawanPengaju->HP, '628')) {
                    $pesan = [
                        'messaging_product' => 'whatsapp',
                        'to' => $karyawanPengaju->HP,
                        'type' => 'template',
                        'template' => [
                            'name' => 'confirm_izin_diterima',
                            'language' => [
                                'code' => 'id',
                                'policy' => 'deterministic',
                            ],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => [
                                        [
                                            'type' => 'text',
                                            'text' => $karyawanPengaju->Nama,
                                        ],
                                        [
                                            'type' => 'text',
                                            'text' => $cutiDetail->Jenis.' anda No. '.$No_Transaksi,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ];

                    $waResult = WhatsappLogHelper::sendAndLog(
                        $karyawanPengaju->Kode_Karyawan,
                        $karyawanPengaju->Nama,
                        $karyawanPengaju->HP,
                        $No_Transaksi,
                        $pesan,
                        function ($response) use ($karyawanPengaju) {
                            // DB::table('HRIS_Approval_Request as a')
                            // ->where("a.No_Transaksi", $No_Transaksi)
                            // ->where("a.Kode_Karyawan_Approver", $karyawanPengaju->Kode_Karyawan)
                            // ->update(['a.angka_wa' => DB::raw('COALESCE(angka_wa, 0) + 1')]);
                            // DB::table('HRIS_Approval_Request as a')
                            // ->leftJoin('HRIS_Approval_Flow as b', 'a.Flow_Id', 'b.id')
                            // ->where('a.No_Transaksi', $No_Transaksi)
                            // ->where(function ($q) use ($approverData) {
                            //     $q->where('a.Approver_Karyawan_Kode', $approverData->Kode_Karyawan)
                            //     ->orWhere('b.Kode_Karyawan', $approverData->Kode_Karyawan);
                            // })
                            // ->update([
                            //     'a.angka_wa' => DB::raw('COALESCE(a.angka_wa, 0) + 1')
                            // ]);

                            Log::channel('cutiController')->info(
                                "WA sukses dikirim ke approver {$karyawanPengaju->Kode_Karyawan}",
                                [
                                    'requester' => $karyawanPengaju->Kode_Karyawan,
                                    'response' => $response,
                                ],
                            );
                        },
                        function ($reason, $response) use ($karyawanPengaju, $No_Transaksi, $pesan) {
                            Log::channel('cutiController')->warning(
                                "Gagal kirim WA ke approver {$karyawanPengaju->Kode_Karyawan}",
                                [
                                    'reason' => $reason,
                                    'response' => $response,
                                ],
                            );

                            DB::table('Whatsapp_Log')->insert([
                                'Kode_Karyawan_Destination' => $karyawanPengaju->Kode_Karyawan,
                                'Nama' => $karyawanPengaju->Nama,
                                'HP' => $karyawanPengaju->HP,
                                'No_Transaksi' => $No_Transaksi,
                                'Error_Message' => is_array($response)
                                    ? json_encode($response, JSON_UNESCAPED_UNICODE)
                                    : $reason ?? 'Unknown error',
                                'RawMessage' => print_r($pesan, true),
                                'RawMessageJson' => json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                                'Created_At' => now(),
                                'Is_Retried' => 0,
                            ]);
                        },
                    );
                }
            }

            DB::commit();

            // Bersihkan notif role-review yang masih outstanding di tray admin
            // lain, lalu cek apakah step berikutnya juga ROLE pool (multi-role
            // flow) — kalau ya, fire notif lagi untuk pool berikutnya.
            try {
                $notifier = app(\App\Services\ESS\Attendance\Leave\Notification\CutiNotificationService::class);
                $notifier->resolveRolePoolNotifications($No_Transaksi);
                $notifier->notifyRolePoolForCurrentStep($No_Transaksi);
            } catch (\Throwable $notifEx) {
                Log::channel('cutiController')->warning(
                    'Gagal sync notif role pool (approve cuti): '.
                        $notifEx->getMessage().
                        ' - Transaksi: '.
                        $No_Transaksi,
                );
            }

            return ResponseHelper::success(null, 'Pengajuan cuti berhasil disetujui.', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('cutiController')->error(
                'Gagal menyetujui pengajuan cuti: '.$e->getMessage().' on line '.$e->getLine(),
            );

            return ResponseHelper::error('Gagal menyetujui pengajuan cuti.', 500);
        }
    }

    // public function getRiwayatPengajuan(Request $request){
    //     try {
    //         $validated = $request->validate([
    //             'search' => ['nullable', 'string', 'max:100'],
    //             'status' => ['nullable', 'string', 'max:100'],
    //             'jenis_cuti' => ['nullable', 'string', 'max:100'],
    //             'page' => ['nullable', 'integer', 'min:1'],
    //         ]);

    //         $search = strip_tags($validated['search'] ?? '');
    //         $status = $validated['status'] ?? '';
    //         $jenis_cuti = $validated['jenis_cuti'] ?? '';
    //         $limit = 5;
    //         $page = $validated['page'] ?? 1;
    //         $offset = ($page - 1) * $limit;
    //         $searchColoms = [
    //             'nama_karyawan',
    //             'No_Transaksi',
    //             'jenis_cuti'
    //         ];
    //         // CTE untuk mengambil semua data yang dibutuhkan
    //         $dataCte = "
    //             WITH PengajuanCuti AS (
    //                 SELECT
    //                     b.No_Transaksi,
    //                     k.Nama as nama_karyawan,
    //                     k.Kode_Karyawan as kode_karyawan,
    //                     vdsd.nama_divisi as divisi,
    //                     vglj.nama_level as level,
    //                     a.Jenis AS jenis_cuti,
    //                     a.Tanggal_Cuti_Dari AS tanggal_mulai,
    //                     a.Tanggal_Cuti_Sampai AS tanggal_selesai,
    //                     DATEDIFF(DAY, a.Tanggal_Cuti_Dari, a.Tanggal_Cuti_Sampai) + 1 AS jumlah_hari,
    //                     a.Alasan AS alasan,
    //                     b.Tanggal AS created_at,
    //                     a.filePath,
    //                     jc.Deskripsi as deskripsi_cuti,
    //                     ISNULL(
    //                         (
    //                             SELECT TOP 1 ar.Request_Order_Flow
    //                             FROM HRIS_Approval_Request ar
    //                             WHERE ar.No_Transaksi = b.No_Transaksi
    //                             AND ar.Flag_Approval IS NOT NULL
    //                             ORDER BY ar.Request_Order_Flow DESC
    //                         ),
    //                         0
    //                     ) AS currentFlow,
    //                     (
    //                         SELECT MAX(ar.Request_Order_Flow)
    //                         FROM HRIS_Approval_Request ar
    //                         WHERE ar.No_Transaksi = b.No_Transaksi
    //                     ) AS maxFlow,
    //                     CASE
    //                         WHEN EXISTS (
    //                             SELECT 1
    //                             FROM HRIS_Approval_Request
    //                             WHERE No_Transaksi = b.No_Transaksi
    //                             AND Flag_Group = 'Y'
    // 							AND Group_Reference_Id = 100
    // 							AND Flag_Approval = 'T'
    //                         ) THEN 'rejected'
    //                         WHEN NOT EXISTS (
    //                             SELECT 1
    //                             FROM HRIS_Approval_Request
    //                             WHERE No_Transaksi = b.No_Transaksi
    // 							AND Flag_Group = 'Y'
    // 							AND Group_Reference_Id = 100
    //                             AND Flag_Approval is null
    //                         ) THEN 'approved'
    //                         ELSE 'pending'
    //                     END AS status,
    //                     (
    //                         SELECT
    //                             COALESCE(ar.Approver_Karyawan_Kode, 'GROUP:' + CAST(ar.Group_Reference_Id AS VARCHAR(10))) AS approver_id,
    //                             COALESCE(
    // 								k_approver.Nama,
    // 								(
    // 									SELECT TOP 1 g.Keterangan
    // 									FROM N_HRIS_HC_Approval g
    // 									WHERE g.Group_Id = ar.Group_Reference_Id
    //                                     AND ar.Flag_Group = 'Y'
    // 								),
    // 								'Unknown'
    // 							) AS approver_name,
    //                             ISNULL(ar.Flag_Approval, 'N') AS approval_status,
    //                             ar.Request_Order_Flow AS order_flow,
    //                             ar.Flag_Group AS is_group
    //                         FROM HRIS_Approval_Request ar
    //                         LEFT JOIN Karyawan k_approver ON ar.Approver_Karyawan_Kode = k_approver.Kode_Karyawan
    //                         LEFT JOIN N_HRIS_HC_Approval g ON ar.Group_Reference_Id = g.Group_Id AND ar.Flag_Group = 'Y'
    //                         WHERE ar.No_Transaksi = b.No_Transaksi
    //                         ORDER BY ar.Request_Order_Flow
    //                         FOR JSON PATH
    //                     ) AS approvers,
    //                      (
    // 							SELECT top 1 Request_Order_Flow
    //                             FROM HRIS_Approval_Request
    //                             WHERE No_Transaksi = b.No_Transaksi
    //                             AND Flag_Group = 'Y'
    // 							AND Group_Reference_Id = 100

    // 					) as userFlow
    //                 FROM Transaksi_Cuti b
    //                 INNER JOIN Transaksi_Cuti_Detail a ON b.No_Transaksi = a.No_Transaksi
    //                 INNER JOIN Karyawan k ON a.Kode_Karyawan = k.Kode_Karyawan
    //                 INNER JOIN View_Divisi_Sub_Divisi as vdsd ON k.ID_Divisi_Sub_Divisi = vdsd.ID_Divisi_Sub_Divisi
    //                 INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan as vglj ON k.Id_Level_Jabatan = vglj.Id_Level_Jabatan
    //                 LEFT JOIN N_HRIS_Jenis_Cuti jc ON a.Id_Jenis_Cuti = jc.Id_Jenis_Cuti
    //                 WHERE b.Status IS NULL
    //             )
    //         ";

    //         $params = [];
    //         $whereClause = "where 1=1 and (currentFlow >= userFlow or userFlow is null)";
    //         if($search){
    //             $searchParam = '%' . $search .'%';
    //             $likeParts = [];
    //             foreach($searchColoms as $col){
    //                 $likeParts[]= "$col Like ?";
    //                 $params[] = $searchParam;
    //             }

    //             $likeQuery = implode(' OR ', $likeParts);
    //             $whereClause .= " AND ($likeQuery) ";
    //         }

    //         if($status){
    //             $whereClause .= " AND status = ?";
    //             $params[] = $status;
    //         }
    //         if($jenis_cuti){
    //             $whereClause .= " AND jenis_cuti = ?";
    //             $params[] = $validated['jenis_cuti'];
    //         }

    //         $countQuery = $dataCte . " SELECT COUNT(*) AS total FROM PengajuanCuti $whereClause";
    //         $total = DB::select($countQuery, $params)[0]->total;

    //         $dataQuery = $dataCte . " SELECT * FROM PengajuanCuti $whereClause ORDER BY created_at DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    //         $params[] = $offset;
    //         $params[] = $limit;

    //         $pengajuanList = collect(DB::select($dataQuery, $params))->map(function ($item) {
    //             $item->approvers = json_decode($item->approvers);
    //             // Anda bisa menambahkan logika untuk file_url di sini jika diperlukan
    //             return $item;
    //         });

    //         return ResponseHelper::successWithPaginationV2(
    //             $pengajuanList,
    //             $page,
    //             $limit,
    //             $total,
    //             "Data riwayat pengajuan berhasil diambil.",
    //             200
    //         );
    //     } catch (\Exception $e) {
    //         Log::channel('cutiController')->error('Gagal mengambil data riwayat pengajuan: ' . $e->getMessage() . ' on line ' . $e->getLine());
    //         return ResponseHelper::error('Gagal mengambil data riwayat pengajuan.', 500);
    //     }
    // }

    public function getRiwayatPengajuan(Request $request)
    {
        // 1. Validasi Input
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'jenis_cuti' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'tanggal' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $search = strip_tags($validated['search'] ?? '');
            $status = $validated['status'] ?? '';
            $jenis_cuti = $validated['jenis_cuti'] ?? '';
            $tanggal = $validated['tanggal'] ?? '';
            $limit = 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $limit;

            $currentUserId = Auth::user()->Id_Users;

            $query = DB::table('N_HRIS_VW_All_Transactions as t')
                ->join('N_HRIS_VW_Approval_Request as ar_filter', 't.No_Transaksi', '=', 'ar_filter.No_Transaksi')
                ->join('HRIS_Approval_Request as req_filter', 'req_filter.id', '=', 'ar_filter.Id_Approval_Request')
                ->join(
                    'N_HRIS_Jenis_Approval as ja_filter',
                    'ja_filter.Id_Jenis_Approval',
                    '=',
                    'req_filter.Id_Jenis_Approval',
                )
                ->join('N_HRIS_Role_Functional as rf_filter', function ($join) {
                    $join
                        ->on('rf_filter.Id_Role', '=', 'req_filter.Group_Reference_Id')
                        ->on('rf_filter.Role_Code', '=', 'ja_filter.Source_Reference');
                })
                ->join('N_HRIS_Role_User as ru_filter', 'ru_filter.Id_Role', '=', 'rf_filter.Id_Role')
                ->whereNull('t.Status_Header')
                ->where('req_filter.Flag_Group', 'Y')
                ->where('ja_filter.Source_Type', 'ROLE')
                ->where('ja_filter.Flag_Aktif', 'Y')
                ->where('rf_filter.Flag_Aktif', 'Y')
                ->where('ru_filter.Flag_Aktif', 'Y')
                ->where('ru_filter.UserId_Web', $currentUserId)
                ->whereIn('req_filter.Flag_Approval', ['Y', 'T'])
                ->whereNotNull('req_filter.Approved_By_Kode')
                ->select('t.*')
                ->distinct();

            // 3. FILTER SEARCH
            if ($search) {
                $searchParam = '%'.$search.'%';
                $query->where(function ($q) use ($searchParam) {
                    // Mapping kolom view untuk pencarian
                    $q->where('t.Nama_Requester', 'like', $searchParam)
                        ->orWhere('t.No_Transaksi', 'like', $searchParam)
                        ->orWhere('t.Sub_Tipe', 'like', $searchParam);
                });
            }

            if ($tanggal) {
                $filterDate = Carbon::parse($tanggal)->toDateString();
                $dataQuery->where(function ($q) use ($filterDate) {
                    $q->whereDate('t.Tanggal_Mulai', '<=', $filterDate)->whereDate(
                        't.Tanggal_Selesai',
                        '>=',
                        $filterDate,
                    );
                });
            }

            if ($status) {
                if ($status == 'approved') {
                    $query->where('t.Flag_Approval', 'Y');
                } elseif ($status == 'rejected') {
                    $query->where('t.Flag_Approval', 'T');
                } elseif ($status == 'pending') {
                    $query->whereNull('t.Flag_Approval');
                }
            }

            if ($jenis_cuti) {
                $query->where('t.Sub_Tipe', $jenis_cuti);
            }

            // 5. Pagination Execution
            $total = $query->count(); // Count distinct

            $items = $query->orderBy('t.Created_At', 'DESC')->offset($offset)->limit($limit)->get();

            // 6. AMBIL DATA APPROVAL (Untuk membuat STATUSLIST)
            // Kita query ulang ke View Approval hanya untuk transaksi yang tampil di halaman ini
            $transactionIds = $items->pluck('No_Transaksi')->toArray();

            $approvalDetails = [];
            if (! empty($transactionIds)) {
                $approvalDetails = DB::table('N_HRIS_VW_Approval_Request as ar')
                    ->leftJoin('HRIS_Approval_Request as req', 'req.id', '=', 'ar.Id_Approval_Request')
                    ->leftJoin('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'req.Id_Jenis_Approval')
                    ->leftJoin('N_HRIS_Role_Functional as rf', function ($join) {
                        $join
                            ->on('rf.Id_Role', '=', 'req.Group_Reference_Id')
                            ->on('rf.Role_Code', '=', 'ja.Source_Reference');
                    })
                    ->leftJoin('Karyawan as k', 'ar.Current_Approver_Kode', '=', 'k.Kode_Karyawan')
                    ->leftJoin('Karyawan as reviewer', 'reviewer.Kode_Karyawan', '=', 'req.Approved_By_Kode')
                    ->whereIn('ar.No_Transaksi', $transactionIds)
                    ->select(
                        'ar.No_Transaksi',
                        'ar.Flag_Approval',
                        'ar.Request_Order_Flow',
                        'ar.Current_Approver_Kode',
                        'ar.Target_Group_Id',
                        'req.Approved_By_Kode',
                        'req.Tanggal_Approval',
                        'k.Nama as Nama_Personal_Approver',
                        'reviewer.Nama as Nama_Reviewer',
                        'rf.Role_Name',
                        'rf.Role_Code',
                    )
                    ->orderBy('ar.Request_Order_Flow', 'ASC')
                    ->get()
                    ->groupBy('No_Transaksi');
            }

            // 7. MAPPING DATA (Output disamakan PERSIS dengan getRiwayat)
            $formattedData = $items->map(function ($item) use ($approvalDetails) {
                // Ambil list approval untuk transaksi ini
                $approvers = $approvalDetails->get($item->No_Transaksi, collect());

                // --- Logic Penentuan Posisi Flow (Copy dari getRiwayat) ---

                // 1. Cari Flow Saat Ini (yang pending)
                $currentFlow = $approvers->where('Flag_Approval', null)->sortBy('Request_Order_Flow')->first();

                // 2. Jika tidak ada yang pending (sudah done semua), ambil yang terakhir
                if (! $currentFlow) {
                    $currentFlow = $approvers->last();
                }

                // 3. Logic Previous Flow
                $previousApprover = $approvers
                    ->where('Request_Order_Flow', ($currentFlow->Request_Order_Flow ?? 0) - 1)
                    ->first();

                $maxFlow = $approvers->max('Request_Order_Flow');
                $reviewedRoleFlow = $approvers
                    ->filter(fn ($row) => ! empty($row->Role_Code) && in_array($row->Flag_Approval, ['Y', 'T'], true))
                    ->sortByDesc('Request_Order_Flow')
                    ->first();

                // 4. Generate String STATUSLIST (Format: "Nama : Status")
                $statusListStr = $approvers
                    ->map(function ($row) {
                        // Tentukan nama: Nama Karyawan atau Nama Group
                        $name =
                            $row->Nama_Personal_Approver ??
                            ($row->Role_Name ??
                                ($row->Target_Group_Id
                                    ? 'Group '.$row->Target_Group_Id
                                    : $row->Current_Approver_Kode));
                        // Status: Y/T/N
                        $st = $row->Flag_Approval ?? 'N';

                        return "{$name} : {$st}";
                    })
                    ->implode(', ');

                // 5. Generate File URL
                $fileUrl = null;
                if ($item->File_Path) {
                    try {
                        // $fileUrl = Storage::disk('gcs')->temporaryUrl($item->File_Path, now()->addMinutes(15));
                        $fileUrl = $item->File_Path;
                    } catch (\Exception $e) {
                    }
                }

                $tanggalReal = TanggalTransaksiIzin::ringkas($item->No_Transaksi);
                $dapartment = DB::table('Karyawan as a')
                    ->join('View_Divisi_Sub_Divisi as b', 'a.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
                    ->where('a.Kode_Karyawan', $item->Kode_Karyawan_Requester)
                    ->select('b.nama_Sub_divisi')
                    ->first();

                // 6. Return Array Keys (Strictly matching getRiwayat)
                return [
                    'tanggal_create' => $item->Created_At,
                    'No_Transaksi' => $item->No_Transaksi,
                    'Approver_Kode_Karyawan' => Crypt::encryptString($currentFlow->Current_Approver_Kode ?? ''),
                    'requester_Nama' => $item->Nama_Requester,
                    'Requester_Kode_Karyawan' => Crypt::encryptString($item->Kode_Karyawan_Requester),

                    // Helper parsing (Pastikan function ini ada di controller)
                    'Tipe_Izin2' => method_exists($this, 'parseSubTipe')
                        ? $this->parseSubTipe($item->Sub_Tipe)
                        : $item->Sub_Tipe,
                    'Tipe_Izin' => $item->Sub_Tipe,
                    'Alasan' => $item->Deskripsi,
                    'TanggalIzin' => $item->Tanggal_Mulai,
                    'Tanggal_Selesai' => $item->Tanggal_Selesai,

                    'Lampiran' => $item->File_Path,
                    'Lampiran_Url' => $fileUrl,

                    'MaxFlow' => $maxFlow ?? 0,
                    'currentFlow' => $currentFlow->Request_Order_Flow ?? 0,

                    // Status Transaksi Global
                    'status' => method_exists($this, 'parseIzin')
                        ? $this->parseIzin($item->Flag_Approval)
                        : $item->Flag_Approval,
                    'department' => $dapartment->nama_Sub_divisi ?? null,
                    'Admin_Reviewer' => $reviewedRoleFlow->Nama_Reviewer ?? ($reviewedRoleFlow->Approved_By_Kode ?? '-'),
                    'Tanggal_Review_Admin' => $reviewedRoleFlow->Tanggal_Approval ?? null,
                    // Detail Approver saat ini
                    'Approver' => $currentFlow->Nama_Personal_Approver ??
                        ($currentFlow->Role_Name ?? ($currentFlow->Target_Group_Id ? 'Role Approval' : 'Unknown')),
                    'Status_Approval_Saat_Ini' => method_exists($this, 'parseIzin')
                        ? $this->parseIzin($currentFlow->Flag_Approval)
                        : $currentFlow->Flag_Approval ?? 'Pending',
                    'Jam_Mulai' => $item->Jam_Diajukan,
                    'Status_Approval_Langkah_Sebelumnya' => $previousApprover->Flag_Approval ?? null,
                    'order_flow' => $currentFlow->Request_Order_Flow ?? 0,
                    'STATUSLIST' => $statusListStr,
                    'tanggalReal' => $tanggalReal['tanggal'],
                    'Jumlah_Hari' => $tanggalReal['jumlahHari'],
                ];
            });

            return ResponseHelper::successWithPaginationV2(
                $formattedData,
                $page,
                $limit,
                $total,
                'Data riwayat pengajuan berhasil diambil.',
                200,
            );
        } catch (\Exception $e) {
            Log::channel('cutiController')->error(
                'Gagal mengambil data riwayat pengajuan: '.$e->getMessage().' on line '.$e->getLine(),
            );

            return ResponseHelper::error('Gagal mengambil data riwayat pengajuan.', 500);
        }
    }

    public function rejectPengajuan(Request $request)
    {
        $validated = $request->validate([
            'No_Transaksi' => ['required', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);

        $No_Transaksi = $validated['No_Transaksi'];
        $catatan = $validated['catatan'] ?? null;
        $user_id = Auth::user()->karyawan->Kode_Karyawan;
        $kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

        DB::beginTransaction();
        try {
            $approvalRequest = $this->getCurrentRoleApprovalForUser($No_Transaksi, true);

            if (! $approvalRequest) {
                DB::rollBack();

                return ResponseHelper::error('Pengajuan tidak ditemukan atau sudah diproses.', 404);
            }

            $updated = DB::table('HRIS_Approval_Request')
                ->where('id', $approvalRequest->id)
                ->whereNull('Flag_Approval')
                ->update([
                    'Flag_Approval' => 'T',
                    'Catatan' => $catatan,
                    'Tanggal_Approval' => Carbon::now(),
                    'Approved_By_Kode' => $user_id,
                    'update_at' => Carbon::now(),
                ]);

            if ($updated < 1) {
                DB::rollBack();

                return ResponseHelper::error('Pengajuan sudah diproses oleh approver lain.', 409);
            }

            // Update status di Transaksi_Cuti menjadi ditolak
            DB::table('Transaksi_Cuti_Detail')
                ->where('No_Transaksi', $No_Transaksi)
                ->update(['Flag_Approval' => 'T']);

            // Kirim notifikasi ke karyawan pengaju bahwa cuti ditolak
            $cutiDetail = DB::table('Transaksi_Cuti_Detail')->where('No_Transaksi', $No_Transaksi)->first();
            if ($cutiDetail) {
                $HCOps = null;
                if ($approvalRequest->Flag_Group == 'Y') {
                    $HCOps = 'Admin HCIS - ';
                }
                $namaApprover = ($HCOps ? $HCOps : '').Auth::user()->karyawan->Nama;
                $noFaktur = $cutiDetail->No_Transaksi;
                $kodeKaryawanPengaju = $cutiDetail->Kode_Karyawan;
                $requester = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawanPengaju)->first();
                if ($requester && ! empty($requester->HP) && Str::startsWith($requester->HP, '628')) {
                    $pesan = [
                        'messaging_product' => 'whatsapp',
                        'to' => $requester->HP,
                        'type' => 'template',
                        'template' => [
                            'name' => 'confirm_izin_tolak',
                            'language' => ['code' => 'id', 'policy' => 'deterministic'],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => [
                                        ['type' => 'text', 'text' => $requester->Nama],
                                        [
                                            'type' => 'text',
                                            'text' => $No_Transaksi.' Pengajuan '.$cutiDetail->Jenis,
                                        ],
                                        ['type' => 'text', 'text' => $namaApprover],
                                    ],
                                ],
                            ],
                        ],
                    ];
                    $waResult = WhatsappLogHelper::sendAndLog(
                        $requester->Kode_Karyawan,
                        $requester->Nama,
                        $requester->HP,
                        $noFaktur,
                        $pesan,
                        function ($response) use ($requester) {
                            // DB::table('HRIS_Approval_Request as a')
                            // ->where("a.No_Transaksi", $noFaktur)
                            // ->where("a.Kode_Karyawan_Approver", $requester->Kode_Karyawan)
                            // ->update(['a.angka_wa' => DB::raw('COALESCE(angka_wa, 0) + 1')]);

                            Log::channel('cutiController')->info(
                                "WA sukses dikirim ke approver {$requester->Kode_Karyawan}",
                                [
                                    'requester' => $requester->Kode_Karyawan,
                                    'response' => $response,
                                ],
                            );
                        },
                        function ($reason, $response) use ($requester, $noFaktur, $pesan) {
                            Log::channel('cutiController')->warning(
                                "Gagal kirim WA ke approver {$requester->Kode_Karyawan}",
                                [
                                    'reason' => $reason,
                                    'response' => $response,
                                ],
                            );

                            DB::table('Whatsapp_Log')->insert([
                                'Kode_Karyawan_Destination' => $requester->Kode_Karyawan,
                                'Nama' => $requester->Nama,
                                'HP' => $requester->HP,
                                'No_Transaksi' => $noFaktur,
                                'Error_Message' => is_array($response)
                                    ? json_encode($response, JSON_UNESCAPED_UNICODE)
                                    : $reason ?? 'Unknown error',
                                'RawMessage' => print_r($pesan, true),
                                'RawMessageJson' => json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                                'Created_At' => now(),
                                'Is_Retried' => 0,
                            ]);
                        },
                    );
                }
            }

            DB::commit();

            // Tolak final → bersihkan tray admin lain juga.
            try {
                app(\App\Services\ESS\Attendance\Leave\Notification\CutiNotificationService::class)->resolveRolePoolNotifications(
                    $No_Transaksi,
                );
            } catch (\Throwable $notifEx) {
                Log::channel('cutiController')->warning(
                    'Gagal resolve notif role pool (reject cuti): '.
                        $notifEx->getMessage().
                        ' - Transaksi: '.
                        $No_Transaksi,
                );
            }

            return ResponseHelper::success(null, 'Pengajuan cuti berhasil ditolak.', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('cutiController')->error(
                'Gagal menolak pengajuan cuti: '.$e->getMessage().' on line '.$e->getLine(),
            );

            return ResponseHelper::error('Gagal menolak pengajuan cuti.', 500);
        }
    }

    private function deductLeaveQuota($kodeKaryawan, $daysToDeduct, $idJenisCuti, $noTransaksi)
    {
        $this->transactionService->debitBalanceWithDebt(
            $kodeKaryawan,
            (int) $idJenisCuti,
            (float) $daysToDeduct,
            $noTransaksi,
            'PENGAJUAN_CUTI',
            'Potong Cuti Approved',
            'SYSTEM',
        );
    }

    /**
     * Mengambil step approval AKTIF saat ini untuk user login pada sebuah transaksi.
     *
     * Mendukung DUA tipe approver:
     *  1. ROLE/GROUP  → user adalah anggota role pool (Flag_Group='Y', Source_Type='ROLE').
     *  2. INDIVIDUAL  → user adalah approver atasan langsung (Flag_Group<>'Y',
     *     Approver_Karyawan_Kode = Kode_Karyawan user).
     *
     * Sebelumnya method ini hanya menangani ROLE/GROUP sehingga approval cuti
     * bertipe "Atasan" (individual) tidak pernah ditemukan → leader tidak bisa
     * menyetujui. Kini keduanya ditangani, tetap menghormati urutan flow
     * (step sebelumnya yang belum/ditolak menghalangi step ini menjadi aktif).
     */
    private function getCurrentRoleApprovalForUser(string $noTransaksi, bool $lock = false): ?object
    {
        $userId = Auth::user()->Id_Users;
        $kodeKaryawan = Auth::user()->karyawan->Kode_Karyawan ?? null;

        // Guard urutan flow yang dipakai bersama: step sebelumnya tidak boleh
        // masih pending atau ditolak.
        $applyOrderGuard = function ($q) {
            $q->whereNotExists(function ($prev) {
                $prev
                    ->select(DB::raw(1))
                    ->from('HRIS_Approval_Request as prev_ar')
                    ->whereColumn('prev_ar.No_Transaksi', 'ar.No_Transaksi')
                    ->whereColumn('prev_ar.Request_Order_Flow', '<', 'ar.Request_Order_Flow')
                    ->where(function ($state) {
                        $state->whereNull('prev_ar.Flag_Approval')->orWhere('prev_ar.Flag_Approval', 'T');
                    });
            });
        };

        // 1) ROLE/GROUP approver (user anggota role pool)
        $roleQuery = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'ar.Id_Jenis_Approval')
            ->join('N_HRIS_Role_Functional as rf', function ($join) {
                $join->on('rf.Id_Role', '=', 'ar.Group_Reference_Id')->on('rf.Role_Code', '=', 'ja.Source_Reference');
            })
            ->join('N_HRIS_Role_User as ru', 'ru.Id_Role', '=', 'rf.Id_Role')
            ->where('ar.No_Transaksi', $noTransaksi)
            ->where('ar.Flag_Group', 'Y')
            ->whereNull('ar.Flag_Approval')
            ->where('ja.Source_Type', 'ROLE')
            ->where('ja.Flag_Aktif', 'Y')
            ->where('rf.Flag_Aktif', 'Y')
            ->where('ru.Flag_Aktif', 'Y')
            ->where('ru.UserId_Web', $userId);
        $applyOrderGuard($roleQuery);
        $roleQuery->select('ar.*', 'rf.Role_Code', 'rf.Role_Name', 'ja.Source_Reference');
        if ($lock) {
            $roleQuery->lockForUpdate();
        }
        $roleRow = $roleQuery->orderBy('ar.Request_Order_Flow')->first();

        // 2) INDIVIDUAL approver (atasan langsung)
        $individualRow = null;
        if ($kodeKaryawan) {
            $indQuery = DB::table('HRIS_Approval_Request as ar')
                ->where('ar.No_Transaksi', $noTransaksi)
                ->whereNull('ar.Flag_Approval')
                ->where(function ($q) {
                    $q->where('ar.Flag_Group', '<>', 'Y')->orWhereNull('ar.Flag_Group');
                })
                ->whereRaw('UPPER(LTRIM(RTRIM(ar.Approver_Karyawan_Kode))) = ?', [
                    strtoupper(trim((string) $kodeKaryawan)),
                ]);
            $applyOrderGuard($indQuery);
            $indQuery->select(
                'ar.*',
                DB::raw('NULL as Role_Code'),
                DB::raw('NULL as Role_Name'),
                DB::raw('NULL as Source_Reference'),
            );
            if ($lock) {
                $indQuery->lockForUpdate();
            }
            $individualRow = $indQuery->orderBy('ar.Request_Order_Flow')->first();
        }

        // Kembalikan step dengan urutan flow paling kecil (yang benar-benar aktif).
        if ($roleRow && $individualRow) {
            return (int) $roleRow->Request_Order_Flow <= (int) $individualRow->Request_Order_Flow
                ? $roleRow
                : $individualRow;
        }

        return $roleRow ?: $individualRow;
    }

    private function resolveJenisCutiId(string $tipeCuti): int
    {
        $id = DB::table('N_HRIS_Jenis_Cuti')->where('Nama_Jenis_Cuti', $tipeCuti)->value('Id_Jenis_Cuti');

        return $id ? (int) $id : TransactionService::ID_TAHUNAN;
    }
}
