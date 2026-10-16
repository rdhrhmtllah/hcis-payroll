<?php

namespace App\Http\Controllers\KPI\Approval\Workflow;

use App\Helpers\ResponseHelper;
use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Governance\Approval\Shared\AnggotaApprovalResolver;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class KpiApprovalController extends Controller
{
    public function index()
    {
        return inertia('KPI/Approval/KPI/approvalKPI');
    }

    public function getApprovals(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            // dd($request->all());
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            $search = strip_tags($validated['search'] ?? '');
            $division = $request->division ?? '';
            // dd($division);
            $level = $request->level ?? '';
            $status = $request->status ?? '';
            $history = $request->history ?? false;
            $perPage = 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $perPage;
            $query = "
                WITH
                cte AS (
                    SELECT
                    pkpi.Tanggal,
                    pkpi.Jam,
                    a.Flag_Approval,
                    a.create_at,
                    a.update_at,
                    k.Nama,
                    k.Kode_Karyawan,
                    b.Kode_Karyawan_Approver,
                    b.Kode_Karyawan_Requester,
                    d.nama_jabatan AS Jabatan,
                    c.nama_divisi AS Department,
                    a.Id_Approval_Request,
                    a.No_Transaksi,
                    b.order_flow,
                    a.Alasan,
                    ISNULL((select count(z.Id_Pengajuan_Detail) from KPI_Pengajuan_Detail z where z.Id_Pengajuan = pkpi.Id_Pengajuan), 0) as totalKPI,
                    ISNULL((select sum(z.Bobot) from KPI_Pengajuan_Detail z where z.Id_Pengajuan = pkpi.Id_Pengajuan), 0) as bobot
                    FROM
                    KPI_Approval_Request_Parameter a
                    JOIN KPI_Approval_Flow_Parameter b ON a.Flow_Id = b.Id_Approval_Flow
                    JOIN KPI_Pengajuan pkpi ON pkpi.No_Transaksi = a.No_Transaksi
                    JOIN Karyawan k ON k.Kode_Karyawan = b.Kode_Karyawan_Requester
                    JOIN View_Divisi_Sub_Divisi c ON k.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
                    JOIN View_Golongan_Sub_Golongan_Level_Jabatan d ON d.ID_Level_Jabatan = k.ID_Level_Jabatan
                    WHERE
                    b.Kode_Karyawan_Approver = ?
                    and pkpi.Flag_Aktif = 'Y'
                    and pkpi.Status is null
            ";

            $params = [$Kode_Karyawan];

            // Filter untuk history atau queue
            if ($history) {
                $query .= ' AND a.Flag_Approval IS NOT NULL';
            } else {
                $query .= ' AND a.Flag_Approval IS NULL';
            }

            // Apply search filter
            if ($search) {
                $query .=
                    ' AND (a.No_Transaksi LIKE ? OR k.Nama LIKE ? OR c.nama_divisi LIKE ? OR d.nama_jabatan LIKE ?)';
                $searchParam = '%'.$search.'%';
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            // Apply division filter
            if ($division) {
                $query .= ' AND c.Id_Divisi = ?';
                $params[] = $division;
            }

            // Apply level filter
            if ($level) {
                $query .= ' AND d.Id_Level = ?';
                $params[] = $level;
            }

            $query .= "),
                cte2 AS (
                    SELECT
                    cte.*,
                    TRY_CAST(cte.order_flow AS INT) AS num_order,
                    prev_req.Flag_Approval AS prev_approval_step
                    FROM
                    cte
                    LEFT JOIN KPI_Approval_Flow_Parameter prev ON prev.Kode_Karyawan_Requester = cte.Kode_Karyawan_Requester
                    AND prev.order_flow = TRY_CAST(cte.order_flow AS INT) - 1
                    AND TRY_CAST(cte.order_flow AS INT) > 1
                    LEFT JOIN KPI_Approval_Request_Parameter prev_req ON prev_req.Flow_Id = prev.Id_Approval_Flow
                    AND prev_req.No_Transaksi = cte.No_Transaksi
                )
                SELECT
                *
                FROM
                cte2
                WHERE
                cte2.Kode_Karyawan_Approver = ?
                AND (
                    cte2.num_order = 1
                    OR (
                    cte2.num_order > 1
                    AND cte2.prev_approval_step = 'Y'
                    )
                )";

            $params[] = $Kode_Karyawan;

            // Apply status filter untuk history
            if ($history && $status) {
                if ($status === 'approved') {
                    $query .= " AND cte2.Flag_Approval = 'Y'";
                } elseif ($status === 'rejected') {
                    $query .= " AND cte2.Flag_Approval = 'T'";
                }
            }

            $selectCount = DB::select($query, $params);

            $approvalCount = collect($selectCount)->count();

            // Queue (pending): urut berdasarkan tanggal & jam PENGAJUAN (create_at) desc,
            // karena update_at belum terisi utk yang belum diproses. History (sudah
            // diproses): urut berdasarkan yang TERAKHIR di-approve/tolak (update_at) desc.
            $orderColumn = $history ? 'cte2.update_at' : 'cte2.create_at';
            $query .= " ORDER BY {$orderColumn} DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY;";
            $params[] = $offset;
            $params[] = $perPage;

            $approvalRequests = DB::select($query, $params);

            // $result = [];

            // foreach ($approvalRequests as $request) {
            //     $kpiDetails = DB::table('KPI_Pengajuan_Detail as pd')
            //         ->join('KPI_Master_Bsc_Kategori as bc', 'pd.Id_Bsc_Kategori', '=', 'bc.Id_Master_Bsc_Kategori')
            //         ->join('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
            //         ->select(
            //             'pd.Id_Pengajuan_Detail',
            //             'pd.Nama_Parameter',
            //             'pd.Id_Bsc_Kategori',
            //             'pd.Deskripsi',
            //             'pd.Bobot',
            //             'pd.Id_Master_Target',
            //             'bc.Nama_Kategori',
            //             'pd.Alasan_Tolak',
            //             'ms.Id_Master_Satuan',
            //             'ms.Simbol_Satuan',
            //             'ms.Format_Input'
            //         )
            //         ->where('pd.Id_Pengajuan', function ($query) use ($request) {
            //             $query->select('Id_Pengajuan')
            //                 ->from('KPI_Pengajuan')
            //                 ->where('No_Transaksi', $request->No_Transaksi);
            //         })
            //         ->get();

            //     foreach ($kpiDetails as $kpi) {
            //         $targets = DB::table('KPI_Pengajuan_Det')
            //             ->where('Id_Pengajuan_Detail', $kpi->Id_Pengajuan_Detail)
            //             ->select('Nama_Target', 'Nilai_Target')
            //             ->get();

            //         $kpi->targets = $targets;
            //     }

            //     $result[] = [
            //         'id_Approval_Request' => $request->Id_Approval_Request,
            //         'No_Transaksi' => $request->No_Transaksi,
            //         'Flag_Approval' => $request->Flag_Approval,
            //         'Alasan' => $request->Alasan,
            //         'create_at' => $request->create_at,true,
            //         'update_at' => $request->update_at,true,
            //         'requester' => [
            //             'Nama' => $request->Nama,
            //             'Kode_Karyawan' => $request->Kode_Karyawan,
            //             'Jabatan' => $request->Jabatan,
            //             'Department' => $request->Department,
            //         ],
            //         'kpiDetails' => $kpiDetails
            //     ];
            // }
            //   $result[] = [
            //         'id_Approval_Request' => $request->Id_Approval_Request,
            //         'No_Transaksi' => $request->No_Transaksi,
            //         'Flag_Approval' => $request->Flag_Approval,
            //         'Alasan' => $request->Alasan,
            //         'create_at' => $request->create_at,true,
            //         'update_at' => $request->update_at,true,
            //         'requester' => [
            //             'Nama' => $request->Nama,
            //             'Kode_Karyawan' => $request->Kode_Karyawan,
            //             'Jabatan' => $request->Jabatan,
            //             'Department' => $request->Department,
            //         ],
            //     ];
            $baseQuery = "
                WITH cte AS (
                    SELECT
                        a.Flag_Approval,
                        b.Kode_Karyawan_Approver,
                        TRY_CAST(b.order_flow AS INT) AS num_order,
                        prev_req.Flag_Approval AS prev_approval_step
                    FROM KPI_Approval_Request_Parameter a
                    JOIN KPI_Approval_Flow_Parameter b ON a.Flow_Id = b.Id_Approval_Flow
                    LEFT JOIN KPI_Approval_Flow_Parameter prev ON prev.Kode_Karyawan_Requester = b.Kode_Karyawan_Requester
                        AND prev.order_flow = TRY_CAST(b.order_flow AS INT) - 1
                    LEFT JOIN KPI_Approval_Request_Parameter prev_req ON prev_req.Flow_Id = prev.Id_Approval_Flow
                        AND prev_req.No_Transaksi = a.No_Transaksi
                    JOIN KPI_Pengajuan pkpi ON pkpi.No_Transaksi = a.No_Transaksi
                    WHERE
                        b.Kode_Karyawan_Approver = ?
                        AND pkpi.Flag_Aktif = 'Y'
                        AND pkpi.Status IS NULL
                )
                SELECT * FROM cte
                WHERE (num_order = 1 OR (num_order > 1 AND prev_approval_step = 'Y'))
            ";

            $baseParams = [$Kode_Karyawan];

            if ($history) {
                $baseQuery .= ' AND Flag_Approval IS NOT NULL';
            } else {
                $baseQuery .= ' AND Flag_Approval IS NULL';
            }

            $queryApproved = $baseQuery." AND Flag_Approval = 'Y'";
            $totalApproved = collect(DB::select($queryApproved, $baseParams))->count();

            $queryRejected = $baseQuery." AND Flag_Approval = 'T'";
            $totalRejected = collect(DB::select($queryRejected, $baseParams))->count();

            $queryPending = $baseQuery.' AND Flag_Approval IS NULL';
            $totalPending = collect(DB::select($queryPending, $baseParams))->count();

            // SUMBER CENTRAL (HRIS_Approval_Request) — merge di level PHP. Query legacy utuh.
            $centralRows = $this->getCentralParamApprovals($Kode_Karyawan, (bool) $history, $status ?? null, $search ?? null);
            $mergedRaw = collect($approvalRequests);
            if ($centralRows->isNotEmpty()) {
                if ($page <= 1) {
                    $mergedRaw = $centralRows->concat($mergedRaw);
                }
                $approvalCount += $centralRows->count();
                $centralCounts = $this->getCentralParamApprovalCounts($Kode_Karyawan);
                $totalApproved += $centralCounts['approved'];
                $totalRejected += $centralCounts['rejected'];
                $totalPending += $centralCounts['pending'];
            }

            // Urutkan hasil GABUNGAN (legacy + central) — bukan hanya legacy. Central
            // (getCentralParamApprovals) tak terurut tanggal & di-prepend, sehingga
            // ORDER BY di query legacy saja tidak cukup. Queue: tanggal & jam PENGAJUAN
            // (create_at) terbaru di atas. History: yang TERAKHIR diproses (update_at) di atas.
            $mergedSortKey = $history ? 'update_at' : 'create_at';
            $mergedRaw = $mergedRaw
                ->sortByDesc(fn ($r) => strtotime((string) ($r->{$mergedSortKey} ?? '')))
                ->values();

            $result = $mergedRaw->map(function ($request) {
                return [
                    'id_Approval_Request' => $request->Id_Approval_Request,
                    'No_Transaksi' => $request->No_Transaksi,
                    // Status PER-LANGKAH: tiap approver melihat keputusan langkahnya sendiri
                    // (approve -> "Disetujui", tolak -> "Ditolak"). Approver yang langkahnya
                    // belum tercapai tidak muncul sama sekali (query history hanya mengambil
                    // step Flow_State='HISTORY' dgn Approved_By_Kode = approver ini).
                    'Flag_Approval' => $request->Flag_Approval,
                    'Alasan' => $request->Alasan ?? null,
                    'create_at' => $request->create_at,
                    true,
                    'update_at' => $request->update_at,
                    true,
                    'totalKPI' => $request->totalKPI,
                    'Bobot' => $request->bobot,
                    'requester' => [
                        'Nama' => $request->Nama,
                        'Kode_Karyawan' => $request->Kode_Karyawan,
                        'Jabatan' => $request->Jabatan,
                        'Department' => $request->Department,
                    ],
                ];
            });

            $final_page = new LengthAwarePaginator($result, $approvalCount, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
            // dd($result);
            $result = [
                'data' => $final_page,
                'totalReject' => $totalRejected,
                'totalApproved' => $totalApproved,
                'totalPending' => $totalPending,
            ];

            return ResponseHelper::success($result, 'Approvals retrieved successfully', 200);
        } catch (\Exception $e) {
            Log::channel('kpiApprovalController')->error('ERROR GET_APPROVALS: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Baris inbox approval parameter dari sistem CENTRAL, dibentuk flat agar
     * cocok dengan map() di getApprovals (Id_Approval_Request, Nama, totalKPI, bobot, dst).
     */
    private function getCentralParamApprovals(string $kodeApprover, bool $history, ?string $status, ?string $search)
    {
        $kpiSvc = app(\App\Services\KPI\Approval\KpiApprovalService::class);
        $jenisIds = $kpiSvc->kpiJenisIds();
        if (empty($jenisIds)) {
            return collect();
        }

        $reqSvc = app(\App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService::class);

        if ($history) {
            $rows = collect(
                DB::table('N_HRIS_VW_Approval_Request_V2')
                    ->where('Flow_State', 'HISTORY')
                    ->where('Approved_By_Kode', $kodeApprover)
                    ->whereIn('Id_Jenis_Approval', $jenisIds)
                    ->get(),
            );
            if ($status === 'approved') {
                $rows = $rows->where('Flag_Approval', 'Y')->values();
            } elseif ($status === 'rejected') {
                $rows = $rows->where('Flag_Approval', 'T')->values();
            }
        } else {
            $rows = $reqSvc->getPendingApprovalsForApprover($kodeApprover)
                ->filter(fn ($r) => in_array((int) $r->Id_Jenis_Approval, $jenisIds, true))
                ->values();
        }

        if ($rows->isEmpty()) {
            return collect();
        }

        // Batasi ke transaksi yang ada di KPI_Pengajuan (domain parameter, bukan performance)
        // DAN yang headernya masih aktif. Transaksi yang dibatalkan/digantikan
        // (Flag_Aktif='T', Status='Y') harus lenyap dari antrian meski step approval
        // centralnya masih 'CURRENT' (view N_HRIS_VW_Approval_Request_V2 tidak membaca
        // Status). Header = sumber kebenaran; sama seperti path performance & verifikasi.
        $noList = $rows->pluck('No_Transaksi')->unique()->all();
        $pengajuan = DB::table('KPI_Pengajuan')
            ->whereIn('No_Transaksi', $noList)
            // Hanya pengajuan yang masih hidup. Tanpa dua filter ini, pengajuan yang
            // sudah dibatalkan atau digantikan pengajuan ulang tetap tampil di inbox
            // approver dan bisa ikut disetujui (SQL engine legacy sudah memfilternya).
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->get()
            ->keyBy('No_Transaksi');

        $karyawan = DB::table('Karyawan as k')
            ->join('View_Divisi_Sub_Divisi as c', 'k.ID_Divisi_Sub_Divisi', '=', 'c.ID_DIVISI_SUB_DIVISI')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as d', 'd.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->whereIn('k.Kode_Karyawan', $rows->pluck('Kode_Karyawan_Requester')->unique()->all())
            ->select('k.Kode_Karyawan', 'k.Nama', 'd.nama_jabatan as Jabatan', 'c.nama_divisi as Department')
            ->get()
            ->keyBy('Kode_Karyawan');

        // Total KPI & total bobot untuk SEMUA pengajuan sekaligus (1 query grouped),
        // bukan count()+sum() per baris (2N). Di-key by Id_Pengajuan lalu dilookup di memori.
        $idPengajuanList = $pengajuan->pluck('Id_Pengajuan')->unique()->all();
        $detailAgg = empty($idPengajuanList) ? collect() : DB::table('KPI_Pengajuan_Detail')
            ->whereIn('Id_Pengajuan', $idPengajuanList)
            ->groupBy('Id_Pengajuan')
            ->selectRaw('Id_Pengajuan, COUNT(*) as totalKPI, ISNULL(SUM(Bobot), 0) as bobot')
            ->get()
            ->keyBy('Id_Pengajuan');

        $result = $rows->map(function ($r) use ($pengajuan, $karyawan, $detailAgg) {
            $pj = $pengajuan->get($r->No_Transaksi);
            if (! $pj) {
                return null; // bukan transaksi parameter
            }
            $kar = $karyawan->get($r->Kode_Karyawan_Requester);

            $agg = $detailAgg->get($pj->Id_Pengajuan);
            $totalKPI = (int) ($agg->totalKPI ?? 0);
            $bobot = (float) ($agg->bobot ?? 0);

            return (object) [
                'Id_Approval_Request' => $r->Id_Approval_Request,
                'No_Transaksi' => $r->No_Transaksi,
                // Status PER-LANGKAH (keputusan approver ini sendiri): approver yg meng-
                // approve langkahnya melihat "Disetujui", yang menolak melihat "Ditolak".
                'Flag_Approval' => $r->Flag_Approval,
                'Alasan' => $r->Catatan ?? null,
                'create_at' => $r->Tanggal_Request,
                'update_at' => $r->Tanggal_Approval ?? $r->Tanggal_Request,
                'totalKPI' => $totalKPI,
                'bobot' => $bobot,
                'Nama' => $kar->Nama ?? null,
                'Kode_Karyawan' => $r->Kode_Karyawan_Requester,
                'Jabatan' => $kar->Jabatan ?? null,
                'Department' => $kar->Department ?? null,
            ];
        })->filter();

        if ($search) {
            $needle = mb_strtolower($search);
            $result = $result->filter(function ($r) use ($needle) {
                return str_contains(mb_strtolower((string) $r->No_Transaksi), $needle)
                    || str_contains(mb_strtolower((string) $r->Nama), $needle)
                    || str_contains(mb_strtolower((string) $r->Department), $needle)
                    || str_contains(mb_strtolower((string) $r->Jabatan), $needle);
            });
        }

        return $result->values();
    }

    private function getCentralParamApprovalCounts(string $kodeApprover): array
    {
        $kpiSvc = app(\App\Services\KPI\Approval\KpiApprovalService::class);
        $jenisIds = $kpiSvc->kpiJenisIds();
        if (empty($jenisIds)) {
            return ['approved' => 0, 'rejected' => 0, 'pending' => 0];
        }

        $reqSvc = app(\App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService::class);

        // Hanya transaksi parameter (ada di KPI_Pengajuan) yang headernya masih aktif —
        // transaksi yang dibatalkan (Flag_Aktif='T'/Status='Y') tidak ikut dihitung,
        // konsisten dengan getCentralParamApprovals.
        // SET-BASED (bukan exists per-baris) agar tidak N+1 saat approver punya banyak
        // pending: satu whereIn untuk mengambil header aktif, lalu cocokkan di memori.
        $pendingRows = $reqSvc->getPendingApprovalsForApprover($kodeApprover)
            ->filter(fn ($r) => in_array((int) $r->Id_Jenis_Approval, $jenisIds, true))
            ->values();

        $pendingNoList = $pendingRows->pluck('No_Transaksi')->unique()->all();
        $activeParamSet = empty($pendingNoList) ? [] : array_flip(
            DB::table('KPI_Pengajuan')
                ->whereIn('No_Transaksi', $pendingNoList)
                ->where('Flag_Aktif', 'Y')
                ->whereNull('Status')
                ->pluck('No_Transaksi')
                ->all(),
        );

        $pending = $pendingRows->filter(fn ($r) => isset($activeParamSet[$r->No_Transaksi]))->count();

        // Sama seperti $pending & list history: transaksi yang dibatalkan
        // (Flag_Aktif='T'/Status='Y') sudah tidak tampil di list history, jadi jangan
        // ikut dihitung di badge approved/rejected — kalau tidak, angka badge > baris.
        $processed = DB::table('N_HRIS_VW_Approval_Request_V2 as v')
            ->join('KPI_Pengajuan as p', 'p.No_Transaksi', '=', 'v.No_Transaksi')
            ->where('v.Flow_State', 'HISTORY')
            ->where('v.Approved_By_Kode', $kodeApprover)
            ->where('p.Flag_Aktif', 'Y')
            ->whereNull('p.Status')
            ->whereIn('v.Id_Jenis_Approval', $jenisIds);

        $approved = (clone $processed)->where('v.Flag_Approval', 'Y')->count();
        $rejected = (clone $processed)->where('v.Flag_Approval', 'T')->count();

        return ['approved' => $approved, 'rejected' => $rejected, 'pending' => $pending];
    }

    public function getApprovalDetail(Request $request, $id)
    {
        // dd($id);
        try {
            // leftJoin (bukan inner join) agar SEMUA detail selalu tampil — kalau inner,
            // detail dgn Id_Bsc_Kategori/Id_Master_Satuan tak ter-resolve akan hilang dari
            // daftar FE, membuat index checkbox tak sinkron dgn set detail saat write reject.
            $kpiDetails = DB::table('KPI_Pengajuan_Detail as pd')
                ->leftJoin('KPI_Master_Bsc_Kategori as bc', 'pd.Id_Bsc_Kategori', '=', 'bc.Id_Master_Bsc_Kategori')
                ->leftJoin('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
                ->select(
                    'pd.Id_Pengajuan_Detail',
                    'pd.Nama_Parameter',
                    'pd.Id_Bsc_Kategori',
                    'pd.Deskripsi',
                    'pd.Bobot',
                    'pd.Id_Master_Target',
                    'bc.Nama_Kategori',
                    'pd.Alasan_Tolak',
                    'ms.Id_Master_Satuan',
                    'ms.Simbol_Satuan',
                    'ms.Format_Input',
                )
                ->where('pd.Id_Pengajuan', function ($query) use ($id) {
                    $query->select('Id_Pengajuan')->from('KPI_Pengajuan')->where('No_Transaksi', $id);
                })
                // Urutan HARUS deterministik & sama dengan query saat reject (rejectParamCentral
                // / legacy rejectWithDetail) karena kpi_comments dicocokkan BY INDEX — kalau beda
                // urutan, komentar per-parameter bisa nyasar ke parameter lain.
                ->orderBy('pd.Id_Pengajuan_Detail')
                ->get();

            /*
             * Target per parameter diambil SEKALI, bukan satu kueri per baris
             * detail. Perulangan ini sendiri berada di dalam perulangan atas
             * daftar approval request, jadi biayanya berlipat dua arah.
             *
             * Kolom pengelompokan ikut diambil lalu dibuang saat dirakit, supaya
             * bentuk tiap item target tetap persis {Nama_Target, Nilai_Target}
             * seperti sebelumnya.
             */
            $targetPerDetail = DB::table('KPI_Pengajuan_Det')
                ->whereIn('Id_Pengajuan_Detail', $kpiDetails->pluck('Id_Pengajuan_Detail')->all())
                ->select('Id_Pengajuan_Detail', 'Nama_Target', 'Nilai_Target')
                ->get()
                ->groupBy('Id_Pengajuan_Detail')
                ->map(fn ($baris) => $baris->map(fn ($r) => (object) [
                    'Nama_Target' => $r->Nama_Target,
                    'Nilai_Target' => $r->Nilai_Target,
                ])->values());

            foreach ($kpiDetails as $kpi) {
                $kpi->targets = $targetPerDetail[$kpi->Id_Pengajuan_Detail] ?? collect();
            }

            $result[] = [
                'id_Approval_Request' => $request->Id_Approval_Request,
                'No_Transaksi' => $request->No_Transaksi,
                'Flag_Approval' => $request->Flag_Approval,
                'Alasan' => $request->Alasan,
                'create_at' => $request->create_at,
                true,
                'update_at' => $request->update_at,
                true,
                'requester' => [
                    'Nama' => $request->Nama,
                    'Kode_Karyawan' => $request->Kode_Karyawan,
                    'Jabatan' => $request->Jabatan,
                    'Department' => $request->Department,
                ],
                'kpiDetails' => $kpiDetails,
            ];

            // Alasan penolakan GLOBAL (header) — hanya bila pengajuan ditolak.
            $header = DB::table('KPI_Pengajuan')
                ->where('No_Transaksi', $id)
                ->select('Flag_Approval', 'Alasan_Reject')
                ->first();

            // dd($kpiDetails);
            $result = [
                'kpiDetails' => $kpiDetails,
                'alasan_reject_global' => ($header && $header->Flag_Approval === 'T')
                    ? ($header->Alasan_Reject ?? null)
                    : null,
            ];

            return ResponseHelper::success($result, 'Approvals retrieved successfully', 200);
        } catch (\Exception $e) {
            Log::channel('kpiApprovalController')->error('ERROR GET_APPROVALS: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getFilterOptions($type)
    {
        try {
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            if ($type === 'divisions') {
                $divisions = DB::table('View_Divisi_Sub_Divisi')
                    ->select('ID_Divisi as id', 'nama_divisi as name')
                    ->groupBy('ID_Divisi', 'nama_divisi')
                    ->orderBy('nama_divisi')
                    ->get();

                return ResponseHelper::success($divisions, 'Divisions retrieved successfully', 200);
            }

            if ($type === 'levels') {
                $levels = DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')
                    ->select('ID_Level as id', 'nama_level as name')
                    // ->whereIn('ID_Level_Jabatan', function($query) use ($Kode_Karyawan) {
                    //     $query->select('k.ID_Level_Jabatan')
                    //         ->from('KPI_Approval_Flow_Parameter as af')
                    //         ->join('Karyawan as k', 'af.Kode_Karyawan_Requester', '=', 'k.Kode_Karyawan')
                    //         ->where('af.Kode_Karyawan_Approver', $Kode_Karyawan);
                    // })
                    ->groupBy('ID_Level', 'nama_level')
                    ->orderBy('nama_level')
                    ->get();

                return ResponseHelper::success($levels, 'Levels retrieved successfully', 200);
            }

            return ResponseHelper::error('Invalid filter type', 400);
        } catch (\Exception $e) {
            Log::channel('kpiApprovalController')->error('ERROR GET_FILTER_OPTIONS: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getStats()
    {
        try {
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            $stats = DB::table('KPI_Approval_Request_Parameter as ar')
                ->join('KPI_Approval_Flow_Parameter as af', 'ar.Flow_id', '=', 'af.Id_Approval_Flow')
                ->select(
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN ar.Flag_Approval IS NULL THEN 1 ELSE 0 END) as pending'),
                    // Literal string di SQL Server memakai kutip tunggal; kutip ganda
                    // dibaca sebagai nama kolom sehingga query selalu gagal.
                    DB::raw("SUM(CASE WHEN ar.Flag_Approval = 'Y' THEN 1 ELSE 0 END) as approved"),
                    DB::raw("SUM(CASE WHEN ar.Flag_Approval = 'T' THEN 1 ELSE 0 END) as rejected"),
                )
                ->where('af.Kode_Karyawan_Approver', $Kode_Karyawan)
                ->whereNull('af.Status')
                ->first();

            return ResponseHelper::success($stats, 'Statistics retrieved successfully', 200);
        } catch (\Exception $e) {
            Log::channel('kpiApprovalController')->error('ERROR GET_STATS: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Approve pengajuan parameter jalur CENTRAL (HRIS_Approval_Request).
     *
     * @param  object  $centralRow  baris HRIS_Approval_Request (PK = $id dari frontend)
     */
    private function approveParamCentral(object $centralRow)
    {
        try {
            DB::beginTransaction();

            $kodeApprover = Auth::user()->karyawan->Kode_Karyawan;
            $noTransaksi = $centralRow->No_Transaksi;

            app(\App\Services\KPI\Approval\KpiApprovalService::class)->approve($noTransaksi, $kodeApprover, null);

            $reqSvc = app(\App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService::class);
            $next = $reqSvc->getCurrentApprovalRequest($noTransaksi);

            if (! $next) {
                // Flow selesai → finalisasi pengajuan parameter (samakan dengan legacy).
                DB::table('KPI_Pengajuan')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update(['Flag_Approval' => 'Y']);
            } else {
                $this->notifyKpiParamNextApproverCentral($noTransaksi, $next);
            }

            DB::commit();

            return ResponseHelper::success(null, 'KPI submission approved successfully', 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('kpiApprovalController')->error('ERROR APPROVE_PARAM_CENTRAL: '.$e->getMessage());

            return ResponseHelper::error($e->getMessage() ?: 'Terjadi Kesalahan', 500);
        }
    }

    /**
     * Reject pengajuan parameter jalur CENTRAL.
     *
     * @param  object  $centralRow  baris HRIS_Approval_Request
     * @param  string|null  $reason  alasan tunggal (dari reject biasa)
     * @param  array|null  $kpiComments  komentar per detail by index (dari rejectWithDetail)
     */
    private function rejectParamCentral(object $centralRow, ?string $reason = null, ?array $kpiComments = null)
    {
        try {
            DB::beginTransaction();

            $kodeRejector = Auth::user()->karyawan->Kode_Karyawan;
            $namaRejector = Auth::user()->karyawan->Nama ?? null;
            $noTransaksi = $centralRow->No_Transaksi;

            app(\App\Services\KPI\Approval\KpiApprovalService::class)
                ->reject($noTransaksi, $kodeRejector, $reason ?? 'Ditolak');

            // Finalisasi pengajuan ditolak. Alasan GLOBAL disimpan di header (mirror
            // KPI_Update_Performance.Alasan_Reject) supaya bisa ditampilkan tanpa join
            // ke engine approval; engine tetap simpan salinannya di HRIS_Approval_Request.Catatan.
            DB::table('KPI_Pengajuan')
                ->where('No_Transaksi', $noTransaksi)
                ->update([
                    'Flag_Approval' => 'T',
                    'Alasan_Reject' => ($reason !== null && trim($reason) !== '') ? $reason : null,
                ]);

            // Komentar penolakan per detail (rejectWithDetail) — by index urut detail.
            if (is_array($kpiComments)) {
                $idPengajuan = DB::table('KPI_Pengajuan')->where('No_Transaksi', $noTransaksi)->value('Id_Pengajuan');
                $details = DB::table('KPI_Pengajuan_Detail')
                    ->where('Id_Pengajuan', $idPengajuan)
                    ->orderBy('Id_Pengajuan_Detail')
                    ->get();

                foreach ($details as $index => $kpi) {
                    $alasan = isset($kpiComments[$index]) && trim((string) $kpiComments[$index]) !== ''
                        ? $kpiComments[$index]
                        : null;
                    DB::table('KPI_Pengajuan_Detail')
                        ->where('Id_Pengajuan_Detail', $kpi->Id_Pengajuan_Detail)
                        ->update(['Alasan_Tolak' => $alasan]);
                }
            }

            DB::commit();

            // Pasca-COMMIT, best-effort: notif in-app + WA ke pengaju (template sama
            // dgn penolakan Update Performance: hcis_tolak_pengajuan_kpi).
            $this->notifyParamRejected($noTransaksi, $reason ?? '', is_array($kpiComments) ? $kpiComments : [], $namaRejector);

            return ResponseHelper::success(null, 'KPI submission rejected successfully', 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('kpiApprovalController')->error('ERROR REJECT_PARAM_CENTRAL: '.$e->getMessage());

            return ResponseHelper::error($e->getMessage() ?: 'Terjadi Kesalahan', 500);
        }
    }

    /**
     * Notif penolakan pengajuan parameter ke pengaju: in-app + WA (template sama dgn
     * penolakan Update Performance, hcis_tolak_pengajuan_kpi). Best-effort, dipanggil
     * SETELAH commit — kegagalan notif tidak menggagalkan penolakan.
     *
     * @param  array<int|string,string>  $kpiComments
     */
    private function notifyParamRejected(string $noTransaksi, string $reason, array $kpiComments, ?string $namaRejector): void
    {
        // Dijamin TIDAK pernah melempar: dipanggil setelah DB::commit() tetapi masih di
        // dalam try transaksi di pemanggilnya — kalau sampai melempar, catch pemanggil
        // akan rollBack() (padahal sudah commit) & balas 500 walau penolakan sukses.
        try {
            $kpiNotif = app(\App\Services\KPI\Notification\KpiNotificationService::class);

            try {
                $kpiNotif->notifyRequesterRejected($noTransaksi, $reason, $kpiComments);
            } catch (\Throwable $e) {
                Log::channel('kpiApprovalController')->warning('Gagal notif in-app penolakan parameter: '.$e->getMessage(), [
                    'no_transaksi' => $noTransaksi,
                ]);
            }

            try {
                $kpiNotif->notifyRequesterRejectedWhatsapp($noTransaksi, 'Pengajuan Parameter KPI', $namaRejector);
            } catch (\Throwable $e) {
                Log::channel('kpiApprovalController')->warning('Gagal kirim WA penolakan parameter: '.$e->getMessage(), [
                    'no_transaksi' => $noTransaksi,
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('kpiApprovalController')->warning('notifyParamRejected gagal total: '.$e->getMessage(), [
                'no_transaksi' => $noTransaksi,
            ]);
        }
    }

    /**
     * WA template KPI ke approver berikutnya pengajuan parameter (CENTRAL).
     */
    private function notifyKpiParamNextApproverCentral(string $noTransaksi, object $next): void
    {
        $approverKode = $next->Current_Approver ?? null;
        if (! $approverKode) {
            // GROUP / ROLE pool: kirim In-App notification ke semua anggota
            if (($next->Flag_Group ?? null) === 'Y') {
                $this->notifyGroupApproversParameter($noTransaksi, $next);
            }

            return;
        }

        $user = DB::table('Karyawan')->where('Kode_Karyawan', $approverKode)->first();
        $pengajuan = DB::table('KPI_Pengajuan')->where('No_Transaksi', $noTransaksi)->first();
        $sentUser = $pengajuan ? DB::table('Karyawan')->where('UserID_Web', $pengajuan->Id_User)->first() : null;

        if (! $user || ! $user->HP || trim($user->HP) === '' || ! $pengajuan) {
            return;
        }

        $pesan = [
            'messaging_product' => 'whatsapp',
            'to' => $user->HP,
            'type' => 'template',
            'template' => [
                'name' => 'kpi_approval_pengajuan_dan_performance',
                'language' => ['code' => 'id', 'policy' => 'deterministic'],
                'components' => [[
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $user->Nama],
                        ['type' => 'text', 'text' => '1'],
                        ['type' => 'text', 'text' => 'Pengajuan Parameter KPI'],
                        ['type' => 'text', 'text' => $sentUser->Nama ?? '-'],
                    ],
                ]],
            ],
        ];

        WhatsappLogHelper::sendAndLog(
            $user->Kode_Karyawan,
            $user->Nama,
            $user->HP,
            $noTransaksi,
            $pesan,
            function ($response) use ($user, $noTransaksi) {
                Log::channel('kpiApprovalController')->info("WA next approver KPI param (CENTRAL) terkirim ke {$user->Kode_Karyawan}", ['no_transaksi' => $noTransaksi]);
            },
            function ($reason, $response) use ($user, $noTransaksi, $pesan) {
                Log::channel('kpiApprovalController')->warning("Gagal WA next approver KPI param (CENTRAL) {$user->Kode_Karyawan}", ['reason' => $reason]);
                DB::table('Whatsapp_Log')->insert([
                    'Kode_Karyawan_Destination' => $user->Kode_Karyawan,
                    'Nama' => $user->Nama,
                    'HP' => $user->HP,
                    'No_Transaksi' => $noTransaksi,
                    'Error_Message' => is_array($response) ? json_encode($response, JSON_UNESCAPED_UNICODE) : ($reason ?? 'Unknown error'),
                    'RawMessage' => print_r($pesan, true),
                    'RawMessageJson' => json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    'Created_At' => now(),
                    'Is_Retried' => 0,
                ]);
            },
        );
    }

    /**
     * Kirim In-App notification ke semua anggota group/role pool approver (CENTRAL) — Parameter KPI.
     */
    private function notifyGroupApproversParameter(string $noTransaksi, object $next): void
    {
        $groupMembers = app(AnggotaApprovalResolver::class)->dariStepApproval($next);
        if ($groupMembers->isEmpty()) {
            Log::channel('kpiApprovalController')->warning(
                'notifyGroupApproversParameter: tidak ada anggota group',
                ['no_transaksi' => $noTransaksi, 'group_ref' => $next->Group_Reference_Id ?? null],
            );

            return;
        }

        $pengajuan = DB::table('KPI_Pengajuan')->where('No_Transaksi', $noTransaksi)->first();
        $sentUser = $pengajuan ? DB::table('Karyawan')->where('UserID_Web', $pengajuan->Id_User)->first() : null;
        $namaRequester = $sentUser->Nama ?? 'Karyawan';

        $notificationService = app(\App\Services\SYSTEM\Notification\NotificationService::class);

        // Deteksi apakah step ini verifikator (ROLE_KPI_VERIF) atau approval biasa
        $isVerification = $this->isVerificationStep($next);
        $actionUrl = $isVerification ? '/kpi-verifikasi' : '/kpi-approval';
        $stepLabel = $isVerification ? 'Verifikasi' : 'Persetujuan';
        $menuLabel = $isVerification ? 'KPI Verifikasi' : 'KPI Approval';

        $title = "Pengajuan Parameter KPI - Menunggu {$stepLabel}";
        $message = "No. Transaksi: {$noTransaksi}\n"
            ."Jenis: Pengajuan Parameter KPI\n"
            ."Pengaju: {$namaRequester}\n\n"
            ."Segera lakukan {$stepLabel} melalui menu {$menuLabel}.";

        foreach ($groupMembers as $member) {
            $notificationService->createNotification([
                'kode_karyawan' => $member->Kode_Karyawan,
                'type' => 'KPI_PARAM_APPROVAL',
                'title' => $title,
                'message' => $message,
                'module' => 'HCIS',
                'severity' => 'info',
                'action_url' => $actionUrl,
                'reference_id' => $noTransaksi,
                'channel' => \App\Services\SYSTEM\Notification\NotificationService::DELIVERY_IN_APP,
                'created_by' => 'SYSTEM',
                'priority' => 3,
            ]);
        }

        Log::channel('kpiApprovalController')->info(
            'In-app notification terkirim ke '.$groupMembers->count().' anggota group',
            ['no_transaksi' => $noTransaksi, 'group_ref' => $next->Group_Reference_Id ?? null],
        );
    }

    /**
     * Deteksi apakah step approval saat ini adalah verifikator (ROLE_KPI_VERIF).
     */
    private function isVerificationStep(object $next): bool
    {
        $idJenis = (int) ($next->Id_Jenis_Approval ?? 0);
        if ($idJenis <= 0) {
            return false;
        }

        return DB::table('N_HRIS_Jenis_Approval')
            ->where('Id_Jenis_Approval', $idJenis)
            ->where('Source_Reference', 'ROLE_KPI_VERIF')
            ->exists();
    }

    /**
     * Tentukan baris CENTRAL untuk transaksi yang sedang diproses, ATAU null bila
     * transaksi ini LEGACY (harus diproses lewat KPI_Approval_Request_Parameter).
     *
     * Routing engine WAJIB berbasis No_Transaksi (unik per transaksi), BUKAN numeric
     * $id. Sebab $id legacy berasal dari KPI_Approval_Request_Parameter.id_Approval_Request
     * sedangkan central pakai HRIS_Approval_Request.id — dua auto-increment terpisah yang
     * bisa bertabrakan, sehingga `where('id', $id)` bisa menelan id legacy dan salah-route
     * ke CENTRAL (lalu gagal karena approver bukan approver transaksi asing tsb).
     *
     * $request->no_transaksi dikirim dari FE. Bila tidak ada (kompat lama), fallback ke
     * lookup-by-id agar perilaku tidak berubah untuk pemanggil lama.
     */
    private function resolveParamCentralRow(Request $request, $id): ?object
    {
        $noTransaksi = $request->input('no_transaksi');

        if ($noTransaksi) {
            if (! app(\App\Services\KPI\Approval\KpiApprovalService::class)->isCentral($noTransaksi)) {
                return null; // LEGACY → jangan disentuh jalur central
            }

            return DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNull('Flag_Approval')
                ->orderBy('Request_Order_Flow')
                ->first()
                ?? DB::table('HRIS_Approval_Request')->where('No_Transaksi', $noTransaksi)->first();
        }

        // Fallback kompat lama (rawan collision — hanya dipakai bila FE tak kirim no_transaksi).
        return DB::table('HRIS_Approval_Request')->where('id', $id)->first();
    }

    public function approve(Request $request, $id)
    {
        $centralRow = $this->resolveParamCentralRow($request, $id);
        if ($centralRow) {
            return $this->approveParamCentral($centralRow);
        }

        DB::beginTransaction();
        try {
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            $approvalRequest = DB::table('KPI_Approval_Request_Parameter as ar')
                ->join('KPI_Approval_Flow_Parameter as af', 'ar.Flow_id', '=', 'af.Id_Approval_Flow')
                ->select('ar.*', 'af.Kode_Karyawan_Approver')
                ->where('ar.id_Approval_Request', $id)
                ->where('af.Kode_Karyawan_Approver', $Kode_Karyawan)
                ->first();

            $currentFlow = DB::table('KPI_Approval_Flow_Parameter')
                ->where('Id_Approval_Flow', $approvalRequest->Flow_Id)
                ->first();
            // dd($currentFlow);

            if (! $approvalRequest) {
                DB::rollBack();

                return ResponseHelper::error('Approval request not found', 404);
            }

            if ($approvalRequest->Flag_Approval !== null) {
                DB::rollBack();

                return ResponseHelper::error('Approval request already processed', 400);
            }

            DB::table('KPI_Approval_Request_Parameter')
                ->where('id_Approval_Request', $id)
                ->update([
                    'Flag_Approval' => 'Y',
                    'update_at' => now(),
                ]);

            $nextFlow = DB::table('KPI_Approval_Request_Parameter as ar')
                ->join('KPI_Approval_Flow_Parameter as af', 'ar.Flow_id', '=', 'af.Id_Approval_Flow')
                ->where('ar.No_Transaksi', $approvalRequest->No_Transaksi)
                ->where('af.order_flow', $currentFlow->order_flow + 1)
                ->whereNull('ar.Flag_Approval')
                ->whereNull('af.Status')
                ->first();
            // dd($nextFlow->Kode_Karyawan_Approver);
            if ($nextFlow) {
                $pengajuan = DB::table('KPI_Pengajuan')->where('No_Transaksi', $approvalRequest->No_Transaksi)->first();
                $sentUser = DB::table('Karyawan')->where('UserID_Web', $pengajuan->Id_User)->first() ?? null;
                $userNextFlow =
                    DB::table('Karyawan')->where('Kode_Karyawan', $nextFlow->Kode_Karyawan_Approver)->first() ?? null;
                if ($pengajuan && $userNextFlow && $userNextFlow->HP && trim($userNextFlow->HP) != '') {
                    $user = $userNextFlow;

                    $pesan = [
                        'messaging_product' => 'whatsapp',
                        'to' => $user->HP,
                        'type' => 'template',
                        'template' => [
                            'name' => 'kpi_approval_pengajuan_dan_performance',
                            'language' => [
                                'code' => 'id',
                                'policy' => 'deterministic',
                            ],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => [
                                        ['type' => 'text', 'text' => $user->Nama],
                                        ['type' => 'text', 'text' => '1'],
                                        ['type' => 'text', 'text' => 'Pengajuan Parameter KPI'],
                                        ['type' => 'text', 'text' => $sentUser->Nama],
                                    ],
                                ],
                            ],
                        ],
                    ];

                    $waResult = WhatsappLogHelper::sendAndLog(
                        $user->Kode_Karyawan,
                        $user->Nama,
                        $user->HP,
                        $approvalRequest->No_Transaksi,
                        $pesan,
                        function ($response) use ($sentUser, $approvalRequest, $user) {
                            DB::table('KPI_Approval_Request_Parameter as a')
                                ->join('KPI_Approval_Flow_Parameter as b', 'a.Flow_Id', '=', 'b.Id_Approval_Flow')
                                ->where('a.No_Transaksi', $approvalRequest->No_Transaksi)
                                ->whereNull('b.Status')
                                ->where('b.Kode_Karyawan_Approver', $user->Kode_Karyawan)
                                ->update(['a.Wa_Sent_Count' => DB::raw('COALESCE(Wa_Sent_Count, 0) + 1')]);

                            Log::channel('kpiApprovalController')->info(
                                "WA sukses dikirim ke approver {$user->Kode_Karyawan}",
                                [
                                    'requester' => $sentUser->Kode_Karyawan,
                                    'response' => $response,
                                ],
                            );
                        },
                        function ($reason, $response) use ($user, $approvalRequest, $pesan) {
                            Log::channel('kpiApprovalController')->warning(
                                "Gagal kirim WA ke approver {$user->Kode_Karyawan}",
                                [
                                    'reason' => $reason,
                                    'response' => $response,
                                ],
                            );

                            DB::table('Whatsapp_Log')->insert([
                                'Kode_Karyawan_Destination' => $user->Kode_Karyawan,
                                'Nama' => $user->Nama,
                                'HP' => $user->HP,
                                'No_Transaksi' => $approvalRequest->No_Transaksi,
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
            } else {
                DB::table('KPI_Pengajuan')
                    ->where('No_Transaksi', $approvalRequest->No_Transaksi)
                    ->update([
                        'Flag_Approval' => 'Y',
                    ]);
                $pengajuan = DB::table('KPI_Pengajuan')->where('No_Transaksi', $approvalRequest->No_Transaksi)->first();
                $currentApprover = DB::table('Karyawan')
                    ->where('Kode_Karyawan', $currentFlow->Kode_Karyawan_Approver)
                    ->first();
                $sentUser = DB::table('Karyawan')->where('UserID_Web', $pengajuan->Id_User)->first() ?? null;
                if (
                    $pengajuan &&
                    $sentUser &&
                    ! $pengajuan->Flag_Team &&
                    empty($pengajuan->Id_User_Sender) &&
                    trim($sentUser->HP) != ''
                ) {
                    $sentUser = DB::table('Karyawan')
                        ->where('Kode_Karyawan', $approvalRequest->Kode_Karyawan_Requester)
                        ->first();

                    if (! $sentUser) {
                        // Approval-nya sendiri SUDAH tersimpan di atas. Dulu di sini ada
                        // `return` telanjang sehingga DB::commit() tak pernah dipanggil dan
                        // persetujuan hilang diam-diam (respons tetap 200 berbadan kosong).
                        // Karyawan pengaju yang tak ditemukan cukup membatalkan
                        // notifikasinya saja, bukan persetujuannya.
                        Log::channel('kpiApprovalController')->warning(
                            'Notifikasi WA dilewati: data karyawan pengaju tidak ditemukan.',
                            [
                                'no_transaksi' => $approvalRequest->No_Transaksi,
                                'kode_karyawan_requester' => $approvalRequest->Kode_Karyawan_Requester,
                            ],
                        );
                    } else {

                        // if (!$sentUser || !$sentUser->HP || $sentUser->HP == '' || !$sentUser->Nama) {
                        //     Log::channel('kpiApprovalController')->error(
                        //         'Approval tidak punya No HP/Nama! Karyawan : ' . $Kode_Karyawan
                        //     );
                        //     return ResponseHelper::error("Approval tidak punya No HP/Nama!", 404);
                        // }

                        $pesan = [
                            'messaging_product' => 'whatsapp',
                            'to' => $sentUser->HP,
                            'type' => 'template',
                            'template' => [
                                'name' => 'kpi_approval_pengajuan_dan_performance_diterima',
                                'language' => ['code' => 'id', 'policy' => 'deterministic'],
                                'components' => [
                                    [
                                        'type' => 'body',
                                        'parameters' => [
                                            ['type' => 'text', 'text' => $sentUser->Nama],
                                            ['type' => 'text', 'text' => 'Pengajuan KPI'],
                                        ],
                                    ],
                                ],
                            ],
                        ];

                        $waResult = WhatsappLogHelper::sendAndLog(
                            $sentUser->Kode_Karyawan,
                            $sentUser->Nama,
                            $sentUser->HP,
                            $approvalRequest->No_Transaksi,
                            $pesan,
                            function ($response) use ($currentApprover, $sentUser) {
                                Log::channel('kpiApprovalController')->info(
                                    "WA sukses dikirim ke approver {$sentUser->Kode_Karyawan}",
                                    [
                                        'approver' => $currentApprover->Kode_Karyawan,
                                        'response' => $response,
                                    ],
                                );
                            },
                            function ($reason, $response) use ($approvalRequest, $sentUser, $pesan) {
                                Log::channel('kpiApprovalController')->warning(
                                    "Gagal kirim WA ke approver {$sentUser->Kode_Karyawan}",
                                    [
                                        'reason' => $reason,
                                        'response' => $response,
                                    ],
                                );

                                DB::table('Whatsapp_Log')->insert([
                                    'Kode_Karyawan_Destination' => $sentUser->Kode_Karyawan,
                                    'Nama' => $sentUser->Nama,
                                    'HP' => $sentUser->HP,
                                    'No_Transaksi' => $approvalRequest->No_Transaksi,
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
            }

            DB::commit();

            return ResponseHelper::success(null, 'KPI submission approved successfully', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('kpiApprovalController')->error('ERROR APPROVE: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function reject(Request $request, $id)
    {
        $centralRow = $this->resolveParamCentralRow($request, $id);
        if ($centralRow) {
            $validated = $request->validate(['reason' => 'required|string|min:10|max:500']);

            return $this->rejectParamCentral($centralRow, $validated['reason']);
        }

        DB::beginTransaction();
        try {
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            $approvalRequest = DB::table('KPI_Approval_Request_Parameter as ar')
                ->join('KPI_Approval_Flow_Parameter as af', 'ar.Flow_id', '=', 'af.Id_Approval_Flow')
                ->select('ar.*', 'af.Kode_Karyawan_Approver')
                ->where('ar.id_Approval_Request', $id)
                ->where('af.Kode_Karyawan_Approver', $Kode_Karyawan)
                ->first();

            if (! $approvalRequest) {
                DB::rollBack();

                return ResponseHelper::error('Approval request not found', 404);
            }

            if ($approvalRequest->Flag_Approval !== null) {
                DB::rollBack();

                return ResponseHelper::error('Approval request already processed', 400);
            }

            $validated = $request->validate([
                'reason' => 'required|string|min:10|max:500',
            ]);

            DB::table('KPI_Approval_Request_Parameter')
                ->where('id_Approval_Request', $id)
                ->update([
                    'Flag_Approval' => 'T',
                    'Alasan' => $validated['reason'],
                    'update_at' => now(),
                ]);

            DB::table('KPI_Approval_Request_Parameter as ar')
                ->join('KPI_Approval_Flow_Parameter as af', 'ar.Flow_id', '=', 'af.Id_Approval_Flow')
                ->where('ar.No_Transaksi', $approvalRequest->No_Transaksi)
                ->whereNull('ar.Flag_Approval')
                ->update([
                    'ar.Flag_Approval' => 'T',
                    'ar.Alasan' => 'Automatically rejected due to rejection by another approver',
                    'ar.update_at' => now(),
                ]);

            DB::table('KPI_Pengajuan')
                ->where('No_Transaksi', $approvalRequest->No_Transaksi)
                ->update([
                    'Flag_Approval' => 'T',
                ]);

            DB::commit();

            return ResponseHelper::success(null, 'KPI submission rejected successfully', 200);
        } catch (ValidationException $e) {
            /* Validasi di sini TIDAK bisa dinaikkan ke luar try: transaksi sudah
               dibuka sebelum try, dan pemeriksaan kepemilikan approval berjalan
               lebih dulu. Tanpa klausa ini ValidationException tertangkap catch
               \Exception di bawah dan input yang salah dilaporkan 500, bukan 422.
               Transaksi ditutup dulu, baru dilempar ulang supaya jawabannya sama
               persis dengan titik lain yang validasinya berada di luar try. */
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('kpiApprovalController')->error('ERROR REJECT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function rejectWithDetail(Request $request, $id)
    {
        $centralRow = $this->resolveParamCentralRow($request, $id);
        if ($centralRow) {
            $validated = $request->validate([
                'reason' => 'required|string|max:500',
                'kpi_comments' => 'nullable|array',
                'kpi_comments.*' => 'nullable|string|max:1000',
            ]);

            return $this->rejectParamCentral($centralRow, $validated['reason'], $validated['kpi_comments'] ?? []);
        }

        DB::beginTransaction();
        try {
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            $approvalRequest = DB::table('KPI_Approval_Request_Parameter as ar')
                ->join('KPI_Approval_Flow_Parameter as af', 'ar.Flow_id', '=', 'af.Id_Approval_Flow')
                ->select('ar.*', 'af.Kode_Karyawan_Approver')
                ->where('ar.id_Approval_Request', $id)
                ->where('af.Kode_Karyawan_Approver', $Kode_Karyawan)
                ->first();

            if (! $approvalRequest) {
                DB::rollBack();

                return ResponseHelper::error('Approval request not found', 404);
            }

            if ($approvalRequest->Flag_Approval !== null) {
                DB::rollBack();

                return ResponseHelper::error('Approval request already processed', 400);
            }

            $validated = $request->validate([
                'reason' => 'required|string|max:500',
                'kpi_comments' => 'nullable|array',
                'kpi_comments.*' => 'nullable|string|max:1000',
            ]);
            $reason = $validated['reason'];

            // Update approval request (step aktif) — simpan alasan global agar tampil di
            // history card (yang membaca a.Alasan).
            DB::table('KPI_Approval_Request_Parameter')
                ->where('id_Approval_Request', $id)
                ->update([
                    'Flag_Approval' => 'T',
                    'Alasan' => $reason,
                    'update_at' => now(),
                ]);

            // Update KPI details dengan komentar penolakan. Urutan HARUS sama dengan
            // getApprovalDetail (yang jadi acuan index checkbox di FE) — pakai
            // Id_Pengajuan_Detail agar kpi_comments by-index tidak nyasar.
            $kpiDetails = DB::table('KPI_Pengajuan_Detail as pd')
                ->where('pd.Id_Pengajuan', function ($query) use ($approvalRequest) {
                    $query
                        ->select('Id_Pengajuan')
                        ->from('KPI_Pengajuan')
                        ->where('No_Transaksi', $approvalRequest->No_Transaksi);
                })
                ->orderBy('pd.Id_Pengajuan_Detail')
                ->get();

            $kpiComments = $validated['kpi_comments'] ?? [];
            foreach ($kpiDetails as $index => $kpi) {
                $alasan = isset($kpiComments[$index]) && trim((string) $kpiComments[$index]) !== ''
                    ? $kpiComments[$index]
                    : null;

                DB::table('KPI_Pengajuan_Detail')
                    ->where('Id_Pengajuan_Detail', $kpi->Id_Pengajuan_Detail)
                    ->update([
                        'Alasan_Tolak' => $alasan,
                    ]);
            }

            DB::table('KPI_Approval_Request_Parameter as ar')
                ->join('KPI_Approval_Flow_Parameter as af', 'ar.Flow_id', '=', 'af.Id_Approval_Flow')
                ->where('ar.No_Transaksi', $approvalRequest->No_Transaksi)
                ->whereNull('ar.Flag_Approval')
                ->update([
                    'ar.Flag_Approval' => 'T',
                    'ar.Alasan' => 'Automatically rejected due to rejection by another approver',
                    'ar.update_at' => now(),
                ]);

            // Update main transaction status + alasan GLOBAL di header (mirror
            // KPI_Update_Performance.Alasan_Reject).
            DB::table('KPI_Pengajuan')
                ->where('No_Transaksi', $approvalRequest->No_Transaksi)
                ->update([
                    'Flag_Approval' => 'T',
                    'Alasan_Reject' => $reason,
                ]);

            DB::commit();

            // Pasca-COMMIT, best-effort: notif in-app + WA ke pengaju memakai template
            // yang SAMA dengan penolakan Update Performance (hcis_tolak_pengajuan_kpi).
            // Menggantikan WA inline template lama agar konsisten & andal walau HP kosong.
            $namaRejector = Auth::user()->karyawan->Nama ?? null;
            $this->notifyParamRejected($approvalRequest->No_Transaksi, $reason, $kpiComments, $namaRejector);

            return ResponseHelper::success(null, 'KPI submission rejected successfully with detailed comments', 200);
        } catch (ValidationException $e) {
            /* Lihat catatan yang sama di reject(): validasi tak bisa dinaikkan
               karena transaksi sudah terbuka sebelum try. */
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('kpiApprovalController')->error('ERROR REJECT WITH DETAIL: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
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
