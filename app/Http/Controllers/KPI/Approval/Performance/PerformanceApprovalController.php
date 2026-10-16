<?php

namespace App\Http\Controllers\KPI\Approval\Performance;

use App\Helpers\ResponseHelper;
use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\KPI\Approval\Verification\KpiVerifikasiController;
use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Services\KPI\KpiPicaIsi;
use App\Services\SYSTEM\Governance\Approval\Shared\AnggotaApprovalResolver;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PerformanceApprovalController extends Controller
{
    public function index()
    {
        $dataPerformance = DB::table('KPI_Master_Performance')
            ->orderBy('Range_Akhir', 'DESC')
            ->first();
        $dataPerformance = DB::table('KPI_Master_Performance')
            ->orderBy('Range_Akhir', 'DESC')
            ->select('Range_Awal')
            ->first();

        // dd((int) $dataPerformance->Range_Awal);
        return inertia('KPI/Approval/Performance/performanceApproval', ['dataPerformance' => (int) $dataPerformance->Range_Awal]);
    }

    private function calculateFormulaStream($actual, $target, $weight, $idMasterFormula, $formulas)
    {
        // Terpusat di KpiScoringService (acuan kanonik). Perilaku identik.
        return app(\App\Services\KPI\KpiScoringService::class)
            ->scoreParameterById($actual, $target, $weight, $idMasterFormula, $formulas);
    }

    public function getApprovals(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {

            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            $search = strip_tags($validated['search'] ?? '');
            $division = $request->division ?? '';
            $level = $request->level ?? '';
            $status = $request->status ?? '';
            $history = $request->history ?? false;
            $perPage = 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $perPage;

            // $query = "
            //     WITH
            //     cte AS (
            //         SELECT
            //             akpi.Tanggal,
            //             akpi.Jam,
            //             akpi.Id_Update_Performance AS id_Performance_Approval,
            //             akpi.Id_Pengajuan,
            //             a.Flag_Approval,
            //             a.create_at,
            //             a.update_at,
            //             k.Nama,
            //             k.Kode_Karyawan,
            //             b.Kode_Karyawan_Approver,
            //             b.Kode_Karyawan_Requester,
            //             d.nama_jabatan AS Jabatan,
            //             c.nama_divisi AS Department,
            //             a.Id_Approval_Request,
            //             a.No_Transaksi,
            //             b.order_flow,
            //             akpi.Periode,
            //             akpi.Total_Score AS total_score,
            //             ISNULL((
            //                 SELECT COUNT(z.Id_Pengajuan_Detail)
            //                 FROM KPI_Pengajuan_Detail z
            //                 WHERE z.Id_Pengajuan = akpi.Id_Pengajuan
            //             ), 0) AS kpi_count,
            //             ISNULL((
            //                 SELECT COUNT(x.Id_Update_Performance_Detail)
            //                 FROM KPI_Update_Performance_Det x
            //                 JOIN KPI_Update_Performance_Detail z ON z.Id_Update_Performance_Detail = x.Id_Update_Performance_Detail
            //                 WHERE z.Id_Update_Performance = akpi.Id_Update_Performance
            //             ), 0) AS pica_count
            //         FROM
            //             KPI_Approval_Request_Performance a
            //             JOIN KPI_Approval_Flow_Performance b ON a.Flow_Id = b.Id_Approval_Flow
            //             JOIN KPI_Update_Performance akpi ON akpi.No_Transaksi = a.No_Transaksi
            //             JOIN Karyawan k ON k.Kode_Karyawan = b.Kode_Karyawan_Requester
            //             JOIN View_Divisi_Sub_Divisi c ON k.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
            //             JOIN View_Golongan_Sub_Golongan_Level_Jabatan d ON d.ID_Level_Jabatan = k.ID_Level_Jabatan
            //         WHERE
            //             b.Kode_Karyawan_Approver = ?
            // ";

            $query = "
                WITH
                cte AS (
                    SELECT
                        akpi.Tanggal,
                        akpi.Jam,
                        akpi.Id_Update_Performance AS id_Performance_Approval,
                        akpi.Id_Pengajuan,
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
                        akpi.Periode,
                        akpi.Total_Score AS total_score,
                        ISNULL((
                            SELECT COUNT(z.Id_Pengajuan_Detail)
                            FROM KPI_Pengajuan_Detail z
                            WHERE z.Id_Pengajuan = akpi.Id_Pengajuan
                        ), 0) AS kpi_count,
                        ISNULL((
                            SELECT COUNT(x.Id_Update_Performance_Detail)
                            FROM KPI_Update_Performance_Det x
                            JOIN KPI_Update_Performance_Detail z 
                                ON z.Id_Update_Performance_Detail = x.Id_Update_Performance_Detail
                            WHERE z.Id_Update_Performance = akpi.Id_Update_Performance
                        ), 0) AS pica_count
                    FROM
                        KPI_Approval_Request_Performance a
                        JOIN KPI_Approval_Flow_Performance b ON a.Flow_Id = b.Id_Approval_Flow
                        JOIN KPI_Update_Performance akpi ON akpi.No_Transaksi = a.No_Transaksi
                        JOIN Karyawan k ON k.Kode_Karyawan = b.Kode_Karyawan_Requester
                        JOIN View_Divisi_Sub_Divisi c ON k.ID_Divisi_Sub_Divisi = c.ID_DIVISI_SUB_DIVISI
                        JOIN View_Golongan_Sub_Golongan_Level_Jabatan d ON d.ID_Level_Jabatan = k.ID_Level_Jabatan
                        
                    WHERE
                        b.Kode_Karyawan_Approver = ?
                    AND akpi.Flag_Aktif = 'Y' AND akpi.Status is null
                        
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
                $query .= ' AND (a.No_Transaksi LIKE ? OR k.Nama LIKE ? OR c.nama_divisi LIKE ? OR d.nama_jabatan LIKE ?)';
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
                    LEFT JOIN KPI_Approval_Flow_Performance prev ON prev.Kode_Karyawan_Requester = cte.Kode_Karyawan_Requester
                    AND prev.order_flow = TRY_CAST(cte.order_flow AS INT) - 1
                    AND TRY_CAST(cte.order_flow AS INT) > 1
                    LEFT JOIN KPI_Approval_Request_Performance prev_req ON prev_req.Flow_Id = prev.Id_Approval_Flow
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

            if ($history && $status) {
                if ($status === 'approved') {
                    $query .= " AND cte2.Flag_Approval = 'Y'";
                } elseif ($status === 'rejected') {
                    $query .= " AND cte2.Flag_Approval = 'T'";
                }
            }
            $selectCount = DB::select($query, $params);

            $approvalCount = collect($selectCount)->count();

            /*
             * Queue: tanggal & jam PENGAJUAN (create_at) terbaru di atas.
             * History: yang TERAKHIR diproses (update_at) di atas.
             *
             * Aturan itu ditulis di KpiApprovalController (jalur parameter) dan
             * berlaku untuk keluarga layar yang sama. Layar ini dulu memakai
             * update_at untuk KEDUANYA, sehingga antrean yang belum diproses
             * tersusun menurut kapan terakhir disentuh, bukan kapan diajukan.
             *
             * Kunci ini HARUS sama dengan kunci pengurutan ulang sesudah baris
             * CENTRAL digabung, di bawah. Mengurut halaman dengan satu kunci
             * lalu menampilkannya dengan kunci lain membuat isi halaman dan
             * urutannya berhenti sepakat.
             */
            $orderColumn = $history ? 'cte2.update_at' : 'cte2.create_at';
            $query .= " ORDER BY {$orderColumn} DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY;";
            $params[] = $offset;
            $params[] = $perPage;

            $approvalRequests = DB::select($query, $params);

            $baseQuery = "
                WITH cte AS (
                    SELECT
                        a.Flag_Approval,
                        b.Kode_Karyawan_Approver,
                        TRY_CAST(b.order_flow AS INT) AS num_order,
                        prev_req.Flag_Approval AS prev_approval_step
                    FROM KPI_Approval_Request_Performance a
                    JOIN KPI_Update_Performance akpi ON akpi.No_Transaksi = a.No_Transaksi
                    JOIN KPI_Approval_Flow_Performance b ON a.Flow_Id = b.Id_Approval_Flow
                    LEFT JOIN KPI_Approval_Flow_Performance prev ON prev.Kode_Karyawan_Requester = b.Kode_Karyawan_Requester
                    AND prev.order_flow = TRY_CAST(b.order_flow AS INT) - 1
                    LEFT JOIN KPI_Approval_Request_Performance prev_req ON prev_req.Flow_Id = prev.Id_Approval_Flow
                    AND prev_req.No_Transaksi = a.No_Transaksi
                    WHERE b.Kode_Karyawan_Approver = ?
                    AND akpi.Flag_Aktif = 'Y' AND akpi.Status is null
                )
                SELECT * FROM cte
                WHERE (num_order = 1 OR (num_order > 1 AND prev_approval_step = 'Y'))
            ";
            $baseParams = [$Kode_Karyawan];
            // Approved (Flag = Y)
            $queryApproved = $baseQuery." AND Flag_Approval = 'Y'";
            $totalApproved = collect(DB::select($queryApproved, $baseParams))->count();

            // Rejected (Flag = T)
            $queryRejected = $baseQuery." AND Flag_Approval = 'T'";
            $totalRejected = collect(DB::select($queryRejected, $baseParams))->count();

            // Pending (Flag = NULL)
            $queryPending = $baseQuery.' AND Flag_Approval IS NULL';
            $totalPending = collect(DB::select($queryPending, $baseParams))->count();

            // ==============================================================
            // SUMBER CENTRAL (HRIS_Approval_Request) — merge di level PHP.
            // Query legacy di atas DIBIARKAN UTUH (zero regresi). Baris CENTRAL
            // dibentuk flat agar identik dengan output cte2 yang dibaca frontend.
            // ==============================================================
            $centralRows = $this->getCentralPerformanceApprovals(
                $Kode_Karyawan,
                (bool) $history,
                $status,
                $search,
                $division,
                $level,
            );

            if ($centralRows->isNotEmpty()) {
                // Sisi legacy sudah ter-paginate di SQL (OFFSET/FETCH). Untuk pilot,
                // baris CENTRAL ditampilkan pada halaman pertama saja agar tidak
                // mengganggu paginasi legacy yang sudah berjalan. Saat cutover penuh
                // (Fase 7) seluruh sumber akan dipindah ke pipeline terpusat.
                if ($page <= 1) {
                    /*
                     * Kunci yang SAMA dengan ORDER BY di atas — bukan
                     * `update_at ?? create_at`, yang mencampur dua kolom
                     * berbeda dalam satu pengurutan.
                     *
                     * Dibandingkan lewat strtotime(), bukan sebagai teks.
                     * Kumpulan ini menggabungkan dua sumber: baris legacy dari
                     * SQL mentah (terukur berbentuk '2026-04-15 15:26:33.980')
                     * dan baris CENTRAL dari N_HRIS_VW_Approval_Request_V2.
                     * Perbandingan teks hanya benar selama kedua sumber
                     * merender waktunya dengan bentuk yang persis sama — itu
                     * asumsi, bukan jaminan.
                     */
                    $mergedSortKey = $history ? 'update_at' : 'create_at';

                    $approvalRequests = $centralRows
                        ->concat(collect($approvalRequests))
                        ->sortByDesc(fn ($r) => strtotime((string) ($r->{$mergedSortKey} ?? '')))
                        ->values()
                        ->all();
                }

                $approvalCount += $centralRows->count();

                // Tambahkan kontribusi CENTRAL ke counter status (queue view).
                $centralCounts = $this->getCentralPerformanceApprovalCounts($Kode_Karyawan);
                $totalApproved += $centralCounts['approved'];
                $totalRejected += $centralCounts['rejected'];
                $totalPending += $centralCounts['pending'];
            }

            // dd($approvalRequests);
            // $result = [];

            // foreach ($approvalRequests as $request) {
            //     // $currentPeriod = $request->Periode;
            //     // dd($request);
            //     $kpiDetails = DB::table('KPI_Pengajuan as p')
            //             ->join('KPI_Pengajuan_Detail as pd', 'p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
            //             ->join('KPI_Master_Bsc_Kategori as bc', 'bc.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
            //             ->join('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
            //             ->join('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
            //             ->where('p.Id_Pengajuan', '=', $request->Id_Pengajuan)
            //             ->select([
            //                 'pd.Nama_Parameter as parameter',
            //                 'pd.Id_Bsc_Kategori',
            //                 'pd.Deskripsi',
            //                 'pd.Bobot as weight',
            //                 'pd.Id_Master_Target',
            //                 'bc.Nama_Kategori as bsc_category',
            //                 'pd.Alasan_Tolak',
            //                 'mf.Rumus as rumus',
            //                 'ms.Simbol_Satuan as unit',

            //                 DB::raw("(SELECT TOP 1 upd.Id_Update_Performance_Detail
            //                         FROM KPI_Pengajuan_Det pdet
            //                         JOIN KPI_Update_Performance_Detail upd
            //                             ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
            //                         JOIN KPI_Update_Performance up
            //                             ON upd.Id_Update_Performance = up.Id_Update_Performance
            //                         WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$request->Periode' ) as id"),

            //                 DB::raw("(SELECT TOP 1 pdet.Nilai_Target
            //                         FROM KPI_Pengajuan_Det pdet
            //                         JOIN KPI_Update_Performance_Detail upd
            //                             ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
            //                         JOIN KPI_Update_Performance up
            //                             ON upd.Id_Update_Performance = up.Id_Update_Performance
            //                         WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$request->Periode' ) as Nilai_Target"),

            //                 DB::raw("(SELECT TOP 1 upd.Nilai_Actual
            //                         FROM KPI_Pengajuan_Det pdet
            //                         JOIN KPI_Update_Performance_Detail upd
            //                             ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
            //                         JOIN KPI_Update_Performance up
            //                             ON upd.Id_Update_Performance = up.Id_Update_Performance
            //                         WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$request->Periode' ) as actual_value"),

            //                 DB::raw("(SELECT TOP 1 upd.Score
            //                         FROM KPI_Pengajuan_Det pdet
            //                         JOIN KPI_Update_Performance_Detail upd
            //                             ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
            //                         JOIN KPI_Update_Performance up
            //                             ON upd.Id_Update_Performance = up.Id_Update_Performance
            //                         WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$request->Periode') as score"),

            //                 DB::raw("(SELECT TOP 1 upd.Achievement
            //                         FROM KPI_Pengajuan_Det pdet
            //                         JOIN KPI_Update_Performance_Detail upd
            //                             ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
            //                         JOIN KPI_Update_Performance up
            //                             ON upd.Id_Update_Performance = up.Id_Update_Performance
            //                         WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$request->Periode') as achievement"),

            //                 DB::raw("(SELECT TOP 1 upd.Flag_Pica
            //                         FROM KPI_Pengajuan_Det pdet
            //                         JOIN KPI_Update_Performance_Detail upd
            //                             ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
            //                         JOIN KPI_Update_Performance up
            //                             ON upd.Id_Update_Performance = up.Id_Update_Performance
            //                         WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$request->Periode') as Flag_Pica"),

            //                 DB::raw("(SELECT TOP 1 upd.File_Berkas
            //                         FROM KPI_Pengajuan_Det pdet
            //                         JOIN KPI_Update_Performance_Detail upd
            //                             ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
            //                         JOIN KPI_Update_Performance up
            //                             ON upd.Id_Update_Performance = up.Id_Update_Performance
            //                         WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$request->Periode') as File_Berkas"),

            //                 DB::raw("ISNULL((SELECT TOP 1 'T'
            //                         FROM KPI_Pengajuan_Det pdet
            //                         JOIN KPI_Update_Performance_Detail upd
            //                             ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
            //                         JOIN KPI_Update_Performance up
            //                             ON upd.Id_Update_Performance = up.Id_Update_Performance
            //                         WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$request->Periode'), 'Y') as Is_Disabled"),
            //             ])
            //             ->get();

            //     // dd($kpiDetails);
            //     foreach ($kpiDetails as $kpi) {
            //         if ($kpi->Is_Disabled == 'T') {
            //             $kpi->pica = DB::table('KPI_Update_Performance_Det')
            //                 ->where('Id_Update_Performance_Detail', $kpi->id)
            //                 ->select( 'Id_Update_Performance_Detail','Identifikasi as problem_identification', 'Koreksi_Aksi as corrective_actions', 'Tanggal_Pelaksanaan as due_date')
            //                 ->get();
            //         } else {
            //             $kpi->pica = [];
            //         }
            //     }

            //     $kpiDetails->map(function($e){
            //             if($e->rumus){

            //                 $rumus = preg_replace('/\[(.*?)\]/', '$1', $e->rumus);

            //                 $mapping = [
            //                     'targetKpiEvo' => 'Target',
            //                     'actualKpiEvo' => 'actual',
            //                 ];

            //                 $rumus = str_replace(array_keys($mapping), array_values($mapping), $rumus);

            //                 $e->rumus = $rumus;
            //                 $e->score = (float) $e->score;
            //             }
            //             return $e;
            //     });

            //     $result[] = [
            //         'id_Performance_Approval' => $request->id_Performance_Approval,
            //         'Id_Approval_Request' => $request->Id_Approval_Request,
            //         'Id_Pengajuan' => $request->Id_Pengajuan,
            //         'No_Transaksi' => $request->No_Transaksi,
            //         'Flag_Approval' => $request->Flag_Approval,
            //         'Periode' => $request->Periode,
            //         'create_at' => $request->create_at,
            //         'update_at' => $request->update_at,
            //         'total_score' => $request->total_score,
            //         'kpi_count' => $request->kpi_count,
            //         'pica_count' => $request->pica_count,
            //         'employee' => [
            //             'Nama' => $request->Nama,
            //             'Kode_Karyawan' => $request->Kode_Karyawan,
            //             'Jabatan' => $request->Jabatan,
            //             'Department' => $request->Department,
            //         ],
            //         'kpiDetails' => $kpiDetails
            //     ];
            // }

            $final_page = new LengthAwarePaginator(
                collect($approvalRequests),
                $approvalCount,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );

            $result = [
                'data' => $final_page,
                'totalReject' => $totalRejected,
                'totalApproved' => $totalApproved,
                'totalPending' => $totalPending,
            ];

            return ResponseHelper::success($result, 'Approvals retrieved successfully', 200);

        } catch (\Exception $e) {
            Log::channel('ApprovalPerformanceController')->error('ERROR GET_APPROVALS: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Ambil baris inbox approval performance dari sistem CENTRAL (HRIS_Approval_Request),
     * dibentuk FLAT agar identik dengan output cte2 legacy yang dibaca frontend.
     *
     * Hanya domain KPI performance (Id_Jenis_Approval KPI) & step CURRENT (atau HISTORY bila $history).
     */
    private function getCentralPerformanceApprovals(
        string $kodeApprover,
        bool $history,
        ?string $status,
        ?string $search,
        ?string $division,
        ?string $level,
    ) {
        $kpiSvc = app(\App\Services\KPI\Approval\KpiApprovalService::class);
        $jenisIds = $kpiSvc->kpiJenisIds();
        if (empty($jenisIds)) {
            return collect();
        }

        $reqSvc = app(\App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService::class);

        if ($history) {
            // History: step yang sudah diproses approver ini (lintas transaksi KPI).
            $rows = collect(
                DB::table('N_HRIS_VW_Approval_Request_V2')
                    ->where('Flow_State', 'HISTORY')
                    ->where('Approved_By_Kode', $kodeApprover)
                    ->whereIn('Id_Jenis_Approval', $jenisIds)
                    ->get(),
            );
        } else {
            // Pending = step CURRENT untuk approver ini (sudah handle SERIAL & ROLE pool).
            $rows = $reqSvc->getPendingApprovalsForApprover($kodeApprover)
                ->filter(fn ($r) => in_array((int) $r->Id_Jenis_Approval, $jenisIds, true))
                ->values();
        }

        if ($rows->isEmpty()) {
            return collect();
        }

        // Filter status untuk history queue.
        if ($history && $status) {
            $flag = $status === 'approved' ? 'Y' : ($status === 'rejected' ? 'T' : null);
            if ($flag !== null) {
                $rows = $rows->filter(fn ($r) => $r->Flag_Approval === $flag)->values();
            }
        }

        // Satu transaksi bisa muncul >1 baris di view/pending (mis. >1 step utk approver
        // yg sama) -> tampilkan SEKALI per No_Transaksi agar tidak dobel.
        $rows = $rows->unique('No_Transaksi')->values();

        $noTransaksiList = $rows->pluck('No_Transaksi')->unique()->all();

        // Enrich: data pengajuan performance (aktif) per No_Transaksi.
        $perf = DB::table('KPI_Update_Performance')
            ->whereIn('No_Transaksi', $noTransaksiList)
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->get()
            ->keyBy('No_Transaksi');

        // Join karyawan via requester pada view (lebih andal daripada kolom di header).
        $requesterByNo = $rows->pluck('Kode_Karyawan_Requester', 'No_Transaksi');
        $karyawan = DB::table('Karyawan as k')
            ->join('View_Divisi_Sub_Divisi as c', 'k.ID_Divisi_Sub_Divisi', '=', 'c.ID_DIVISI_SUB_DIVISI')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as d', 'd.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->whereIn('k.Kode_Karyawan', $requesterByNo->values()->unique()->all())
            ->select('k.Kode_Karyawan', 'k.Nama', 'd.nama_jabatan as Jabatan', 'c.nama_divisi as Department', 'c.Id_Divisi', 'd.Id_Level')
            ->get()
            ->keyBy('Kode_Karyawan');

        $result = $rows->map(function ($r) use ($perf, $karyawan) {
            $akpi = $perf->get($r->No_Transaksi);
            if (! $akpi) {
                return null; // pengajuan performance tidak ditemukan/aktif
            }
            $kar = $karyawan->get($r->Kode_Karyawan_Requester);

            $kpiCount = (int) DB::table('KPI_Pengajuan_Detail')
                ->where('Id_Pengajuan', $akpi->Id_Pengajuan)
                ->count();

            return (object) [
                'engine' => 'CENTRAL',
                'Id_Approval_Request' => $r->Id_Approval_Request,
                'id_Performance_Approval' => $akpi->Id_Update_Performance,
                'Id_Pengajuan' => $akpi->Id_Pengajuan,
                'No_Transaksi' => $r->No_Transaksi,
                'Flag_Approval' => $r->Flag_Approval,
                'Periode' => $akpi->Periode,
                'create_at' => $r->Tanggal_Request,
                'update_at' => $r->Tanggal_Approval ?? $r->Tanggal_Request,
                'total_score' => $akpi->Total_Score,
                'kpi_count' => $kpiCount,
                'pica_count' => (int) DB::table('KPI_Update_Performance_Detail')
                    ->where('Id_Update_Performance', $akpi->Id_Update_Performance)
                    ->where('Flag_Pica', 'Y')
                    ->count(),
                'order_flow' => $r->Request_Order_Flow,
                'Nama' => $kar->Nama ?? null,
                'Kode_Karyawan' => $r->Kode_Karyawan_Requester,
                'Jabatan' => $kar->Jabatan ?? null,
                'Department' => $kar->Department ?? null,
            ];
        })->filter();

        // Filter UI (search/division/level) di PHP agar konsisten dgn legacy.
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

    /**
     * Hitung kontribusi CENTRAL ke counter status inbox (approved/rejected/pending).
     */
    private function getCentralPerformanceApprovalCounts(string $kodeApprover): array
    {
        $kpiSvc = app(\App\Services\KPI\Approval\KpiApprovalService::class);
        $jenisIds = $kpiSvc->kpiJenisIds();
        if (empty($jenisIds)) {
            return ['approved' => 0, 'rejected' => 0, 'pending' => 0];
        }

        $reqSvc = app(\App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService::class);

        // Pending = step current untuk approver ini.
        $pendingReqs = $reqSvc->getPendingApprovalsForApprover($kodeApprover)
            ->filter(fn ($r) => in_array((int) $r->Id_Jenis_Approval, $jenisIds, true))
            ->values();

        // History = step yang sudah diproses approver ini.
        $processedRows = DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('Flow_State', 'HISTORY')
            ->where('Approved_By_Kode', $kodeApprover)
            ->whereIn('Id_Jenis_Approval', $jenisIds)
            ->get(['No_Transaksi', 'Flag_Approval']);

        // Konsisten dgn list (getCentralPerformanceApprovals): ketiga counter HANYA
        // menghitung transaksi yang HEADER performance-nya masih AKTIF (Flag_Aktif='Y'
        // & Status NULL). Ini sekaligus membuang transaksi yang dibatalkan (Status='Y')
        // dan request non-performance (mis. parameter) yang tak punya header performance.
        $allNo = $pendingReqs->pluck('No_Transaksi')
            ->merge($processedRows->pluck('No_Transaksi'))
            ->filter()
            ->unique()
            ->all();

        $activeNo = empty($allNo)
            ? collect()
            : DB::table('KPI_Update_Performance')
                ->whereIn('No_Transaksi', $allNo)
                ->where('Flag_Aktif', 'Y')
                ->whereNull('Status')
                ->pluck('No_Transaksi')
                ->flip();

        $isActive = fn ($r) => isset($activeNo[$r->No_Transaksi]);

        // unique('No_Transaksi'): view bisa punya >1 baris per transaksi -> hitung
        // sekali per transaksi agar konsisten dgn list.
        return [
            'approved' => $processedRows->where('Flag_Approval', 'Y')->filter($isActive)->unique('No_Transaksi')->count(),
            'rejected' => $processedRows->where('Flag_Approval', 'T')->filter($isActive)->unique('No_Transaksi')->count(),
            'pending' => $pendingReqs->filter($isActive)->unique('No_Transaksi')->count(),
        ];
    }

    /**
     * Approve performance jalur CENTRAL (HRIS_Approval_Request).
     *
     * Delegasi keputusan flow ke KpiApprovalService/ApprovalService. Finalisasi
     * status KPI_Update_Performance (saat flow selesai) + WA approver berikutnya
     * dengan template KPI khusus ditangani di sini (controller).
     *
     * @param  object  $centralRow  baris HRIS_Approval_Request (PK = $id dari frontend)
     */
    private function approvePerformanceCentral(object $centralRow)
    {
        $noTransaksi = $centralRow->No_Transaksi;
        $next = null;

        try {
            DB::beginTransaction();

            $kodeApprover = Auth::user()->karyawan->Kode_Karyawan;

            // 1) Proses approve via engine terpusat (validasi akses, lock, next-action).
            app(\App\Services\KPI\Approval\KpiApprovalService::class)->approve($noTransaksi, $kodeApprover, null);

            // 2) Tentukan apakah flow sudah selesai atau masih ada approver berikutnya.
            $reqSvc = app(\App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService::class);
            $next = $reqSvc->getCurrentApprovalRequest($noTransaksi);

            if (! $next) {
                // Flow selesai → finalisasi pengajuan performance (samakan dengan legacy).
                DB::table('KPI_Update_Performance')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update([
                        'Status_Pengajuan' => 'disetujui',
                        'Flag_Approval' => 'Y',
                        'Flag_Verifikasi' => 'Y',
                    ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('ApprovalPerformanceController')->error('ERROR APPROVE_PERFORMANCE_CENTRAL: '.$e->getMessage());

            return ResponseHelper::error($e->getMessage() ?: 'Terjadi Kesalahan', 500);
        }

        // Pasca-COMMIT: notifikasi (WA/in-app) memanggil HTTP eksternal — jangan
        // ditahan di dalam transaksi. Best-effort, tidak boleh membatalkan approve.
        try {
            if (! $next) {
                $this->notifyKpiPerformanceRequesterApproved($noTransaksi);
            } else {
                $this->notifyKpiPerformanceNextApprover($noTransaksi, $next);
            }
        } catch (\Throwable $e) {
            Log::channel('ApprovalPerformanceController')->warning(
                'Gagal notifikasi approve performance: '.$e->getMessage(),
                ['no_transaksi' => $noTransaksi],
            );
        }

        try {
            app(\App\Services\KPI\Notification\KpiNotificationService::class)
                ->resolveRolePoolNotifications($noTransaksi);
        } catch (\Throwable $e) {
            Log::channel('ApprovalPerformanceController')->warning(
                'Gagal bersihkan notif role-pool: '.$e->getMessage(),
                ['no_transaksi' => $noTransaksi],
            );
        }

        return ResponseHelper::success([], 'Performance approved successfully', 200);
    }

    /**
     * Reject performance jalur CENTRAL.
     *
     * @param  object  $centralRow  baris HRIS_Approval_Request
     */
    private function rejectPerformanceCentral(Request $request, object $centralRow)
    {
        $validated = $this->validateRejectPayload($request);

        try {
            DB::beginTransaction();

            $kodeRejector = Auth::user()->karyawan->Kode_Karyawan;
            $noTransaksi = $centralRow->No_Transaksi;
            $reason = (string) ($validated['reason'] ?? '');

            // Alasan global disimpan engine ke HRIS_Approval_Request.Catatan.
            app(\App\Services\KPI\Approval\KpiApprovalService::class)
                ->reject($noTransaksi, $kodeRejector, $reason);

            $this->persistPerformanceRejection($noTransaksi, $reason, $validated['kpi_comments'] ?? []);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('ApprovalPerformanceController')->error('ERROR REJECT_PERFORMANCE_CENTRAL: '.$e->getMessage());

            return ResponseHelper::error($e->getMessage() ?: 'Terjadi Kesalahan', 500);
        }

        $this->afterPerformanceRejected(
            $centralRow->No_Transaksi,
            (string) ($validated['reason'] ?? ''),
            $validated['kpi_comments'] ?? [],
            Auth::user()->karyawan->Nama ?? null,
        );

        return ResponseHelper::success([], 'Performance rejected successfully', 200);
    }

    /**
     * Aturan validasi payload reject — dipakai jalur LEGACY & CENTRAL.
     */
    private function validateRejectPayload(Request $request): array
    {
        return $request->validate([
            'no_transaksi' => 'nullable|string|max:50',
            'reason' => 'required|string|max:500',
            'kpi_comments' => 'nullable|array',
            'kpi_comments.*' => 'nullable|string|max:1000',
        ]);
    }

    /**
     * Satu-satunya sumber kebenaran "pengajuan performance ini ditolak".
     * Dipanggil dari rejectPerformance (LEGACY), rejectPerformanceCentral (CENTRAL),
     * dan KpiVerifikasiController::finalizeRejected (VERIFIKATOR).
     *
     * WAJIB dipanggil di dalam transaksi yang sudah terbuka.
     *
     * Status ditulis 'ditolak' (bukan 'rejected'): itulah nilai yang dibaca seluruh
     * jalur revisi requester (UpdatePerformanceController) — dan Flag_Approval='T'
     * wajib ikut, karena reader menyaring whereNotNull('Flag_Approval') lebih dulu.
     *
     * @param  array<int|string,string>  $kpiComments  keyed by Id_Update_Performance_Detail
     */
    public function persistPerformanceRejection(string $noTransaksi, string $globalReason, array $kpiComments = []): void
    {
        $idUp = DB::table('KPI_Update_Performance')
            ->where('No_Transaksi', $noTransaksi)
            ->value('Id_Update_Performance');

        if (! $idUp) {
            return;
        }

        // Komentar valid saja: key harus numerik (Id_Update_Performance_Detail).
        $comments = [];
        foreach ($kpiComments as $detailId => $comment) {
            $comment = trim((string) $comment);
            if ($comment === '' || ! is_numeric($detailId)) {
                continue;
            }
            $comments[(int) $detailId] = $comment;
        }

        // Satu UPDATE untuk SEMUA detail: menulis komentar baru sekaligus mengosongkan
        // komentar ronde sebelumnya (ELSE NULL). Tanpa pengosongan itu, requester —
        // yang menghitung isRejected dari `Alasan_Reject IS NOT NULL` — akan melihat
        // penolakan hantu pada parameter yang sudah benar.
        //
        // WHERE dikunci ke Id_Update_Performance, sehingga key karangan di payload
        // tidak bisa menyentuh baris detail milik karyawan lain.
        if (empty($comments)) {
            DB::table('KPI_Update_Performance_Detail')
                ->where('Id_Update_Performance', $idUp)
                ->update(['Alasan_Reject' => null]);
        } else {
            $cases = [];
            $bindings = [];
            foreach ($comments as $detailId => $comment) {
                $cases[] = 'WHEN ? THEN ?';
                $bindings[] = $detailId;
                $bindings[] = $comment;
            }
            $bindings[] = $idUp;

            DB::update(
                'UPDATE KPI_Update_Performance_Detail
                 SET Alasan_Reject = CASE Id_Update_Performance_Detail '
                    .implode(' ', $cases).
                    ' ELSE NULL END
                 WHERE Id_Update_Performance = ?',
                $bindings,
            );
        }

        // Catatan: KPI_Update_Performance TIDAK punya kolom update_at (kolom itu milik
        // KPI_Approval_Request_Performance). Kode reject lama menulisnya di sini dan
        // selalu melempar "Invalid column name" — itulah sebab reject tak pernah jadi.
        DB::table('KPI_Update_Performance')
            ->where('Id_Update_Performance', $idUp)
            ->update([
                'Status_Pengajuan' => 'ditolak',
                'Flag_Approval' => 'T',
                'Alasan_Reject' => $globalReason !== '' ? $globalReason : null,
            ]);
    }

    /**
     * Kerja pasca-COMMIT untuk penolakan performance: notifikasi in-app ke requester
     * + bersihkan tray verifikator. Sinkron tapi best-effort — kegagalan di sini
     * tidak boleh membatalkan penolakan yang sudah commit.
     *
     * @param  array<int|string,string>  $kpiComments
     */
    private function afterPerformanceRejected(string $noTransaksi, string $globalReason, array $kpiComments = [], ?string $namaApprover = null): void
    {
        $kpiNotif = app(\App\Services\KPI\Notification\KpiNotificationService::class);

        try {
            $kpiNotif->notifyRequesterRejected($noTransaksi, $globalReason, $kpiComments);
        } catch (\Throwable $e) {
            Log::channel('ApprovalPerformanceController')->warning(
                'Gagal notif requester penolakan performance: '.$e->getMessage(),
                ['no_transaksi' => $noTransaksi],
            );
        }

        // WA penolakan (post-commit, best-effort). Pengiriman diantre via Cloud Tasks
        // oleh WhatsappLogHelper sehingga tidak menahan lock DB.
        try {
            $kpiNotif->notifyRequesterRejectedWhatsapp($noTransaksi, 'Update Performance KPI', $namaApprover);
        } catch (\Throwable $e) {
            Log::channel('ApprovalPerformanceController')->warning(
                'Gagal kirim WA penolakan performance: '.$e->getMessage(),
                ['no_transaksi' => $noTransaksi],
            );
        }

        try {
            $kpiNotif->resolveRolePoolNotifications($noTransaksi);
        } catch (\Throwable $e) {
            Log::channel('ApprovalPerformanceController')->warning(
                'Gagal bersihkan notif role-pool: '.$e->getMessage(),
                ['no_transaksi' => $noTransaksi],
            );
        }
    }

    /**
     * Kirim WA template KPI ke approver berikutnya (CENTRAL).
     */
    private function notifyKpiPerformanceNextApprover(string $noTransaksi, object $next): void
    {
        $approverKode = $next->Current_Approver ?? null;
        if (! $approverKode) {
            // GROUP / ROLE pool: kirim In-App notification ke semua anggota
            if (($next->Flag_Group ?? null) === 'Y') {
                $this->notifyGroupApproversPerformance($noTransaksi, $next);
            }

            return;
        }

        $user = DB::table('Karyawan')->where('Kode_Karyawan', $approverKode)->first();
        $pengajuan = DB::table('KPI_Update_Performance')->where('No_Transaksi', $noTransaksi)->first();
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
                        ['type' => 'text', 'text' => 'Update Performance KPI'],
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
                Log::channel('ApprovalPerformanceController')->info("WA next approver KPI (CENTRAL) terkirim ke {$user->Kode_Karyawan}", [
                    'no_transaksi' => $noTransaksi,
                ]);
            },
            function ($reason, $response) use ($user, $noTransaksi, $pesan) {
                Log::channel('ApprovalPerformanceController')->warning("Gagal WA next approver KPI (CENTRAL) {$user->Kode_Karyawan}", [
                    'reason' => $reason,
                ]);
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
     * Kirim In-App notification ke semua anggota group/role pool approver (CENTRAL).
     */
    private function notifyGroupApproversPerformance(string $noTransaksi, object $next): void
    {
        $groupMembers = app(AnggotaApprovalResolver::class)->dariStepApproval($next);
        if ($groupMembers->isEmpty()) {
            Log::channel('ApprovalPerformanceController')->warning(
                'notifyGroupApproversPerformance: tidak ada anggota group',
                ['no_transaksi' => $noTransaksi, 'group_ref' => $next->Group_Reference_Id ?? null],
            );

            return;
        }

        $pengajuan = DB::table('KPI_Update_Performance')->where('No_Transaksi', $noTransaksi)->first();
        $sentUser = $pengajuan ? DB::table('Karyawan')->where('UserID_Web', $pengajuan->Id_User)->first() : null;
        $namaRequester = $sentUser->Nama ?? 'Karyawan';
        $periode = $pengajuan->Periode ?? '-';

        $notificationService = app(\App\Services\SYSTEM\Notification\NotificationService::class);

        // Deteksi apakah step ini verifikator (ROLE_KPI_VERIF) atau approval biasa
        $isVerification = $this->isVerificationStep($next);
        $actionUrl = $isVerification ? '/kpi-verifikasi' : '/performance-approval';
        $stepLabel = $isVerification ? 'Verifikasi' : 'Persetujuan';
        $menuLabel = $isVerification ? 'KPI Verifikasi' : 'Performance Approval';

        $title = "Update Performance KPI - Menunggu {$stepLabel}";
        $message = "No. Transaksi: {$noTransaksi}\n"
            ."Jenis: Update Performance KPI\n"
            ."Periode: {$periode}\n"
            ."Pengaju: {$namaRequester}\n\n"
            ."Segera lakukan {$stepLabel} melalui menu {$menuLabel}.";

        foreach ($groupMembers as $member) {
            $notificationService->createNotification([
                'kode_karyawan' => $member->Kode_Karyawan,
                'type' => 'KPI_PERF_APPROVAL',
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

        Log::channel('ApprovalPerformanceController')->info(
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
     * Kirim WA template KPI ke requester saat performance disetujui penuh (CENTRAL).
     * Mengikuti kondisi legacy (team tanpa sender).
     */
    private function notifyKpiPerformanceRequesterApproved(string $noTransaksi): void
    {
        $pengajuan = DB::table('KPI_Update_Performance')->where('No_Transaksi', $noTransaksi)->first();
        if (! $pengajuan) {
            return;
        }
        $sentUser = DB::table('Karyawan')->where('UserID_Web', $pengajuan->Id_User)->first();
        if (! $sentUser || ! $sentUser->HP || trim($sentUser->HP) === '') {
            return;
        }
        if (! ($pengajuan->Flag_Team == 'T' && empty($pengajuan->Id_User_Sender))) {
            return; // ikuti gating legacy
        }

        try {
            $periode = Carbon::createFromFormat('Y-m', $pengajuan->Periode)->format('F Y');
        } catch (\Throwable $e) {
            $periode = $pengajuan->Periode;
        }

        $pesan = [
            'messaging_product' => 'whatsapp',
            'to' => $sentUser->HP,
            'type' => 'template',
            'template' => [
                'name' => 'kpi_approval_pengajuan_dan_performance_diterima',
                'language' => ['code' => 'id', 'policy' => 'deterministic'],
                'components' => [[
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $sentUser->Nama],
                        ['type' => 'text', 'text' => "Update Performance KPI Periode {$periode}"],
                    ],
                ]],
            ],
        ];

        WhatsappLogHelper::sendAndLog(
            $sentUser->Kode_Karyawan,
            $sentUser->Nama,
            $sentUser->HP,
            $noTransaksi,
            $pesan,
            function ($response) use ($sentUser, $noTransaksi) {
                Log::channel('ApprovalPerformanceController')->info("WA approved KPI (CENTRAL) terkirim ke {$sentUser->Kode_Karyawan}", [
                    'no_transaksi' => $noTransaksi,
                ]);
            },
            function ($reason, $response) use ($sentUser, $noTransaksi, $pesan) {
                Log::channel('ApprovalPerformanceController')->warning("Gagal WA approved KPI (CENTRAL) {$sentUser->Kode_Karyawan}", [
                    'reason' => $reason,
                ]);
                DB::table('Whatsapp_Log')->insert([
                    'Kode_Karyawan_Destination' => $sentUser->Kode_Karyawan,
                    'Nama' => $sentUser->Nama,
                    'HP' => $sentUser->HP,
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

    public function getApprovalDetail(Request $request, $id, $periode)
    {

        try {
            // dd($periode);

            $kpiDetails = DB::table('KPI_Pengajuan as p')
                ->join('KPI_Pengajuan_Detail as pd', 'p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
                ->join('KPI_Master_Bsc_Kategori as bc', 'bc.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
                ->join('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
                ->join('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
                ->where('p.Id_Pengajuan', '=', $id)
                ->select([
                    'pd.Nama_Parameter as parameter',
                    'pd.Id_Bsc_Kategori',
                    'pd.Deskripsi',
                    'pd.Bobot as weight',
                    'pd.Id_Master_Target',
                    'bc.Nama_Kategori as bsc_category',
                    'mf.Rumus as rumus',
                    'ms.Format_Input',
                    'ms.Simbol_Satuan as unit',

                    DB::raw("(SELECT TOP 1 upd.Id_Update_Performance_Detail
                                    FROM KPI_Pengajuan_Det pdet
                                    JOIN KPI_Update_Performance_Detail upd
                                        ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
                                    JOIN KPI_Update_Performance up
                                        ON upd.Id_Update_Performance = up.Id_Update_Performance
                                    WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$periode' and up.Flag_Aktif = 'Y' ) as id"),

                    DB::raw("(SELECT TOP 1 pdet.Nilai_Target
                                    FROM KPI_Pengajuan_Det pdet
                                    JOIN KPI_Update_Performance_Detail upd
                                        ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
                                    JOIN KPI_Update_Performance up
                                        ON upd.Id_Update_Performance = up.Id_Update_Performance
                                    WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$periode' and up.Flag_Aktif = 'Y' ) as Nilai_Target"),

                    DB::raw("(SELECT TOP 1 upd.Nilai_Actual
                                    FROM KPI_Pengajuan_Det pdet
                                    JOIN KPI_Update_Performance_Detail upd
                                        ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
                                    JOIN KPI_Update_Performance up
                                        ON upd.Id_Update_Performance = up.Id_Update_Performance
                                    WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$periode' and up.Flag_Aktif = 'Y' ) as actual_value"),

                    DB::raw("(SELECT TOP 1 upd.Score
                                    FROM KPI_Pengajuan_Det pdet
                                    JOIN KPI_Update_Performance_Detail upd
                                        ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
                                    JOIN KPI_Update_Performance up
                                        ON upd.Id_Update_Performance = up.Id_Update_Performance
                                    WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$periode' and up.Flag_Aktif = 'Y') as score"),

                    // Jejak penjepitan per parameter. Tanpa dua kolom ini, layar
                    // approval memperlihatkan angka yang sudah dipangkas plafon
                    // tanpa satu pun tanda bahwa pemangkasan terjadi — dan
                    // approver justru orang yang paling perlu melihatnya.
                    DB::raw("(SELECT TOP 1 upd.Score_Raw
                                    FROM KPI_Pengajuan_Det pdet
                                    JOIN KPI_Update_Performance_Detail upd
                                        ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
                                    JOIN KPI_Update_Performance up
                                        ON upd.Id_Update_Performance = up.Id_Update_Performance
                                    WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$periode' and up.Flag_Aktif = 'Y') as score_raw"),

                    DB::raw("(SELECT TOP 1 upd.Flag_Capped
                                    FROM KPI_Pengajuan_Det pdet
                                    JOIN KPI_Update_Performance_Detail upd
                                        ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
                                    JOIN KPI_Update_Performance up
                                        ON upd.Id_Update_Performance = up.Id_Update_Performance
                                    WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$periode' and up.Flag_Aktif = 'Y') as flag_capped"),

                    DB::raw("(SELECT TOP 1 upd.Achievement
                                    FROM KPI_Pengajuan_Det pdet
                                    JOIN KPI_Update_Performance_Detail upd
                                        ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
                                    JOIN KPI_Update_Performance up
                                        ON upd.Id_Update_Performance = up.Id_Update_Performance
                                    WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$periode' and up.Flag_Aktif = 'Y') as achievement"),

                    DB::raw("(SELECT TOP 1 upd.Flag_Pica
                                    FROM KPI_Pengajuan_Det pdet
                                    JOIN KPI_Update_Performance_Detail upd
                                        ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
                                    JOIN KPI_Update_Performance up
                                        ON upd.Id_Update_Performance = up.Id_Update_Performance
                                    WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$periode' and up.Flag_Aktif = 'Y') as Flag_Pica"),

                    DB::raw("(SELECT TOP 1 upd.File_Berkas
                                    FROM KPI_Pengajuan_Det pdet
                                    JOIN KPI_Update_Performance_Detail upd
                                        ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
                                    JOIN KPI_Update_Performance up
                                        ON upd.Id_Update_Performance = up.Id_Update_Performance
                                    WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$periode' and up.Flag_Aktif = 'Y') as File_Berkas"),

                    DB::raw("(SELECT TOP 1 upd.Alasan_Reject
                                    FROM KPI_Pengajuan_Det pdet
                                    JOIN KPI_Update_Performance_Detail upd
                                        ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
                                    JOIN KPI_Update_Performance up
                                        ON upd.Id_Update_Performance = up.Id_Update_Performance
                                    WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$periode' and up.Flag_Aktif = 'Y') as Alasan_Tolak"),

                    DB::raw("ISNULL((SELECT TOP 1 'T'
                                    FROM KPI_Pengajuan_Det pdet
                                    JOIN KPI_Update_Performance_Detail upd
                                        ON upd.Id_Pengajuan_Det = pdet.Id_Pengajuan_Det
                                    JOIN KPI_Update_Performance up
                                        ON upd.Id_Update_Performance = up.Id_Update_Performance
                                    WHERE pdet.Id_Pengajuan_Detail = pd.Id_Pengajuan_Detail and up.Periode = '$periode' and up.Flag_Aktif = 'Y'), 'Y') as Is_Disabled"),
                ])
                ->get();

            // dd($kpiDetails);
            /*
             * PICA diambil SEKALI untuk seluruh detail, bukan satu kueri per
             * baris. Ini pintu KETIGA dari pola yang sama: KpiVerifikasiController
             * dan KpiApprovalController sudah lebih dulu dipusatkan dengan cara
             * yang sama persis.
             *
             * Hanya detail ber-Is_Disabled='T' yang butuh PICA, jadi hanya id
             * itu yang ditanyakan -- sisanya tetap larik kosong seperti semula.
             */
            $idButuhPica = $kpiDetails->where('Is_Disabled', 'T')->pluck('id')->all();

            $picaPerDetail = $idButuhPica === []
                ? collect()
                : DB::table('KPI_Update_Performance_Det')
                    ->whereIn('Id_Update_Performance_Detail', $idButuhPica)
                    ->select('Id_Update_Performance_Detail', 'Identifikasi as problem_identification', 'Koreksi_Aksi as corrective_actions', 'Tanggal_Pelaksanaan as due_date')
                    ->get()
                    ->groupBy('Id_Update_Performance_Detail');

            foreach ($kpiDetails as $kpi) {
                $kpi->pica = $kpi->Is_Disabled == 'T'
                    ? ($picaPerDetail[$kpi->id] ?? collect())
                    : [];
            }

            $kpiDetails->map(function ($e) {
                // if($e->File_Berkas){
                //     $e->File_Berkas =
                // }
                if ($e->rumus) {

                    $rumus = preg_replace('/\[(.*?)\]/', '$1', $e->rumus);

                    $mapping = [
                        'targetKpiEvo' => 'Target',
                        'actualKpiEvo' => 'actual',
                    ];

                    $rumus = str_replace(array_keys($mapping), array_values($mapping), $rumus);

                    $e->rumus = $rumus;
                    $e->score = (float) $e->score;
                }

                return $e;
            });

            // Alasan penolakan GLOBAL (komentar umum approver) dari pengajuan aktif
            // periode ini — sumber yang sama dengan alasan per-parameter (Flag_Aktif='Y').
            $header = DB::table('KPI_Update_Performance')
                ->where('Id_Pengajuan', $id)
                ->where('Periode', $periode)
                ->where('Flag_Aktif', 'Y')
                ->select('Alasan_Reject', 'Status_Pengajuan')
                ->first();

            $result = [
                'kpiDetails' => $kpiDetails,
                'alasan_reject_global' => ($header && $header->Status_Pengajuan === 'ditolak')
                    ? ($header->Alasan_Reject ?? null)
                    : null,
            ];

            return ResponseHelper::success($result, 'Approvals retrieved successfully', 200);

        } catch (\Exception $e) {
            Log::channel('ApprovalPerformanceController')->error('ERROR GET_APPROVALS: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getFilterOptions($type)
    {
        $pengguna = Auth::user();
        $leaderId = $pengguna->karyawan->Kode_Karyawan;
        $teamDiv = DB::table('KPI_Karyawan_Team as kt')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
            ->join('View_Divisi_Sub_Divisi as b', 'b.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
                        // ->join('View_Golongan_Sub_Golongan_Level_Jabatan as l', 'l.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('Kode_Karyawan_Leader', $leaderId);
        $teamMemberIds = $teamDiv->pluck('Kode_Karyawan_Team');

        if ($teamMemberIds->isNotEmpty()) {
            $queryDiv = $teamDiv->select('ID_Divisi as id', 'nama_divisi as name');
        } else {
            $queryDiv = DB::table('KPI_User_Akses_Divisi as a')
                ->join('View_Divisi_Sub_Divisi as b', 'a.Id_Divisi', '=', 'b.ID_Divisi')
                ->select('b.ID_Divisi as id', 'b.nama_divisi as name')
                ->where('a.Id_User', $pengguna->Id_Users);
        }

        $teamLev = DB::table('KPI_Karyawan_Team as kt')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as l', 'l.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('Kode_Karyawan_Leader', $leaderId);
        $teamMemberIdsLev = $teamLev->pluck('Kode_Karyawan_Team');

        if ($teamMemberIdsLev->isNotEmpty()) {
            $queryLevel = $teamLev->select('Id_Level as id', 'nama_level as name');
        } else {
            $queryLevel = DB::table('KPI_User_Akses_Level as a')
                ->join('View_Golongan_Sub_Golongan_Level_Jabatan as l', 'a.Id_Level', '=', 'l.Id_Level')
                ->select('l.Id_Level as id', 'l.nama_level as name')
                ->where('a.Id_User', $pengguna->Id_Users);
        }

        try {
            if ($type === 'divisions') {
                // $divisions = DB::table('View_Divisi_Sub_Divisi')
                //     ->select('ID_DIVISI_SUB_DIVISI as id', 'nama_divisi as name')
                //     ->orderBy('nama_divisi')
                //     ->groupBy('ID_DIVISI_SUB_DIVISI', 'nama_divisi')
                //     ->get();
                $divisions = $queryDiv->orderBy('b.nama_divisi')
                    ->groupBy('b.ID_Divisi', 'b.nama_divisi')
                    ->get();

                return ResponseHelper::success($divisions, 'Divisions retrieved successfully', 200);
            }

            if ($type === 'levels') {
                // $levels = DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')
                //     ->select('Id_Level as id', 'Nama_Level as name')
                //     ->orderBy('Nama_Level')
                //     ->groupBy('Id_Level', 'Nama_Level')
                //     ->get();
                $levels = $queryLevel->orderBy('l.nama_level')
                    ->groupBy('l.Id_Level', 'l.nama_level')
                    ->get();

                return ResponseHelper::success($levels, 'Levels retrieved successfully', 200);
            }

            return ResponseHelper::error('Invalid filter type', 400);

        } catch (\Exception $e) {
            Log::channel('ApprovalPerformanceController')->error('ERROR GET_FILTER_OPTIONS: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getAttachmentUrl(Request $request)
    {
        try {
            $filePath = $request->input('path');
            // dd($filePath);
            if (! $filePath) {
                return response()->json(['message' => 'Path lampiran tidak diberikan.'], 400);
            }

            // Get file content dari GCS
            $fileContent = Storage::disk('gcs')->get($filePath);
            $fileName = basename($filePath);

            // Tentukan mime type
            $mimeType = Storage::disk('gcs')->mimeType($filePath);

            return response($fileContent, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                'Content-Length' => strlen($fileContent),
            ]);

        } catch (\Exception $e) {
            Log::channel('ApprovalPerformanceController')->error('Error downloading file getAttachmentUrl: '.$e->getMessage());

            return response()->json(['message' => 'Gagal mendownload file.'], 500);
        }
    }

    /**
     * Tentukan baris CENTRAL untuk transaksi performance, ATAU null bila LEGACY.
     *
     * Routing WAJIB berbasis No_Transaksi (unik), BUKAN numeric $id. $id legacy berasal
     * dari KPI_Approval_Request_Performance.id_Approval_Request, sedangkan central pakai
     * HRIS_Approval_Request.id — dua auto-increment terpisah yang bisa bertabrakan,
     * sehingga `where('id', $id)` bisa menelan id legacy dan salah-route ke CENTRAL.
     *
     * no_transaksi dikirim FE; bila absen, fallback ke lookup-by-id (kompat lama).
     */
    private function resolvePerfCentralRow(?string $noTransaksi, $id): ?object
    {
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

        return DB::table('HRIS_Approval_Request')->where('id', $id)->first();
    }

    public function approvePerformance(Request $request, $id)
    {
        $centralRow = $this->resolvePerfCentralRow($request->input('no_transaksi'), $id);
        if ($centralRow) {
            return $this->approvePerformanceCentral($centralRow);
        }

        try {
            DB::beginTransaction();

            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            $approvalRequest = DB::table('KPI_Approval_Request_Performance as ar')
                ->join('KPI_Approval_Flow_Performance as af', 'ar.Flow_id', '=', 'af.Id_Approval_Flow')
                ->select('ar.*', 'af.Kode_Karyawan_Approver')
                ->where('ar.id_Approval_Request', $id)
                ->where('af.Kode_Karyawan_Approver', $Kode_Karyawan)
                ->first();

            if (! $approvalRequest) {
                return ResponseHelper::error('Approval request not found', 404);
            }

            // Update approval status. whereNull menjadikannya klaim atomik: bila approver
            // lain sudah memproses step ini, tidak ada baris yang tersentuh.
            $claimed = DB::table('KPI_Approval_Request_Performance')
                ->where('Id_Approval_Request', $id)
                ->whereNull('Flag_Approval')
                ->update([
                    'Flag_Approval' => 'Y',
                ]);

            if ($claimed === 0) {
                DB::rollBack();

                return ResponseHelper::error('Transaksi ini baru saja diproses oleh orang lain.', 409);
            }

            // Check if there are more approval flows
            $currentFlow = DB::table('KPI_Approval_Flow_Performance')
                ->where('Id_Approval_Flow', $approvalRequest->Flow_Id)
                ->first();

            $nextFlow = DB::table('KPI_Approval_Request_Performance as ar')
                ->join('KPI_Approval_Flow_Performance as af', 'ar.Flow_id', '=', 'af.Id_Approval_Flow')
                ->where('ar.No_Transaksi', $approvalRequest->No_Transaksi)
                ->where('af.order_flow', $currentFlow->order_flow + 1)
                ->whereNull('ar.Flag_Approval')
                ->whereNull('af.Status')
                ->first();
            // dd($nextFlow);

            if ($nextFlow) {
                $pengajuan = DB::table('KPI_Update_Performance')
                    ->where('No_Transaksi', $approvalRequest->No_Transaksi)
                    ->first();
                $sentUser = DB::table('Karyawan')->where('UserID_Web', $pengajuan->Id_User)->first() ?? null;
                $userNextFlow = DB::table('Karyawan')->where('Kode_Karyawan', $nextFlow->Kode_Karyawan_Approver)->first() ?? null;
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
                                        ['type' => 'text', 'text' => 'Update Performance KPI'],
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
                            DB::table('KPI_Approval_Request_Performance as a')
                                ->join('KPI_Approval_Flow_Performance as b', 'a.Flow_Id', '=', 'b.Id_Approval_Flow')
                                ->where('a.No_Transaksi', $approvalRequest->No_Transaksi)
                                ->whereNull('b.Status')
                                ->where('b.Kode_Karyawan_Approver', $user->Kode_Karyawan)
                                ->update(['a.Wa_Sent_Count' => DB::raw('COALESCE(Wa_Sent_Count, 0) + 1')]);

                            Log::channel('ApprovalPerformanceController')->info("WA sukses dikirim ke approver {$user->Kode_Karyawan}", [
                                'requester' => $sentUser->Kode_Karyawan,
                                'response' => $response,
                            ]);
                        },
                        function ($reason, $response) use ($user, $approvalRequest, $pesan) {
                            Log::channel('ApprovalPerformanceController')->warning("Gagal kirim WA ke approver {$user->Kode_Karyawan}", [
                                'reason' => $reason,
                                'response' => $response,
                            ]);

                            DB::table('Whatsapp_Log')->insert([
                                'Kode_Karyawan_Destination' => $user->Kode_Karyawan,
                                'Nama' => $user->Nama,
                                'HP' => $user->HP,
                                'No_Transaksi' => $approvalRequest->No_Transaksi,
                                'Error_Message' => is_array($response)
                                    ? json_encode($response, JSON_UNESCAPED_UNICODE)
                                    : ($reason ?? 'Unknown error'),
                                'RawMessage' => print_r($pesan, true),
                                'RawMessageJson' => json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                                'Created_At' => now(),
                                'Is_Retried' => 0,
                            ]);

                        }
                    );
                }
            } else {
                DB::table('KPI_Update_Performance')
                    ->where('No_Transaksi', $approvalRequest->No_Transaksi)
                    ->update([
                        'Status_Pengajuan' => 'disetujui',
                        'Flag_Approval' => 'Y',
                        'Flag_Verifikasi' => 'Y',
                    ]);
                $pengajuan = DB::table('KPI_Update_Performance')
                    ->where('No_Transaksi', $approvalRequest->No_Transaksi)
                    ->first();
                $currentApprover = DB::table('Karyawan')
                    ->where('Kode_Karyawan', $currentFlow->Kode_Karyawan_Approver)
                    ->first();
                $sentUser = DB::table('Karyawan')->where('UserID_Web', $pengajuan->Id_User)->first() ?? null;
                if ($pengajuan && $sentUser && $pengajuan->Flag_Team == 'T' && $pengajuan && empty($pengajuan->Id_User_Sender) && (trim($sentUser->HP) != '')) {

                    // if (!$sentUser || !$sentUser->HP || $sentUser->HP == '' || !$sentUser->Nama) {
                    //     Log::channel('ApprovalPerformanceController')->error(
                    //         'Approval tidak punya No HP/Nama! Karyawan : ' . $Kode_Karyawan
                    //     );
                    //     return ResponseHelper::error("Approval tidak punya No HP/Nama!", 404);
                    // }
                    $date = Carbon::createFromFormat('Y-m', $pengajuan->Periode);
                    $periode = $date->format('F Y');

                    $pesan = [
                        'messaging_product' => 'whatsapp',
                        'to' => $sentUser->HP,
                        'type' => 'template',
                        'template' => [
                            'name' => 'kpi_approval_pengajuan_dan_performance_diterima',
                            'language' => ['code' => 'id', 'policy' => 'deterministic'],
                            'components' => [[
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $sentUser->Nama],
                                    ['type' => 'text', 'text' => "Update Performance KPI Periode {$periode}"],
                                ],
                            ]],
                        ],
                    ];

                    $waResult = WhatsappLogHelper::sendAndLog(
                        $sentUser->Kode_Karyawan,
                        $sentUser->Nama,
                        $sentUser->HP,
                        $approvalRequest->No_Transaksi,
                        $pesan,
                        function ($response) use ($currentApprover, $sentUser) {
                            Log::channel('ApprovalPerformanceController')->info("WA sukses dikirim ke approver {$sentUser->Kode_Karyawan}", [
                                'approver' => $currentApprover->Kode_Karyawan,
                                'response' => $response,
                            ]);
                        },
                        function ($reason, $response) use ($approvalRequest, $sentUser, $pesan) {
                            Log::channel('ApprovalPerformanceController')->warning("Gagal kirim WA ke approver {$sentUser->Kode_Karyawan}", [
                                'reason' => $reason,
                                'response' => $response,
                            ]);

                            DB::table('Whatsapp_Log')->insert([
                                'Kode_Karyawan_Destination' => $sentUser->Kode_Karyawan,
                                'Nama' => $sentUser->Nama,
                                'HP' => $sentUser->HP,
                                'No_Transaksi' => $approvalRequest->No_Transaksi,
                                'Error_Message' => is_array($response)
                                    ? json_encode($response, JSON_UNESCAPED_UNICODE)
                                    : ($reason ?? 'Unknown error'),
                                'RawMessage' => print_r($pesan, true),
                                'RawMessageJson' => json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                                'Created_At' => now(),
                                'Is_Retried' => 0,
                            ]);

                        }
                    );

                }
            }

            DB::commit();

            return ResponseHelper::success([], 'Performance approved successfully', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('ApprovalPerformanceController')->error('ERROR APPROVE_PERFORMANCE: '.$e->getMessage());

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

    public function rejectPerformance(Request $request, $id)
    {
        $centralRow = $this->resolvePerfCentralRow($request->input('no_transaksi'), $id);
        if ($centralRow) {
            return $this->rejectPerformanceCentral($request, $centralRow);
        }

        $validated = $this->validateRejectPayload($request);
        $reason = (string) ($validated['reason'] ?? '');
        $kpiComments = $validated['kpi_comments'] ?? [];

        $approval = DB::table('KPI_Approval_Request_Performance')
            ->where('Id_Approval_Request', $id)
            ->first();

        if (! $approval) {
            return ResponseHelper::error('Approval request not found', 404);
        }

        try {
            DB::beginTransaction();

            // Alasan global (legacy) tersimpan di kolom Alasan approval request.
            DB::table('KPI_Approval_Request_Performance')
                ->where('Id_Approval_Request', $id)
                ->update([
                    'Flag_Approval' => 'T',
                    'Alasan' => $reason,
                ]);

            $this->persistPerformanceRejection($approval->No_Transaksi, $reason, $kpiComments);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('ApprovalPerformanceController')->error('ERROR REJECT_PERFORMANCE: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }

        $this->afterPerformanceRejected(
            $approval->No_Transaksi,
            $reason,
            $kpiComments,
            Auth::user()->karyawan->Nama ?? null,
        );

        return ResponseHelper::success([], 'Performance rejected successfully', 200);
    }

    public function editAndApprovePerformance(Request $request, $id)
    {
        $centralRow = $this->resolvePerfCentralRow($request->input('no_transaksi'), $id);
        if ($centralRow) {
            return $this->editAndApprovePerformanceCentral($request, $centralRow);
        }

        $this->validateEditApprovePayload($request);

        $approval = DB::table('KPI_Approval_Request_Performance')
            ->where('Id_Approval_Request', $id)
            ->first();

        if (! $approval) {
            return ResponseHelper::error('Approval request not found', 404);
        }

        if (($approval->Flag_Approval ?? null) !== null) {
            return ResponseHelper::error('Transaksi ini sudah diproses.', 409);
        }

        // Resolve Id_Update_Performance dari approval (legacy) — basis recalc total.
        $idUpdatePerformance = DB::table('KPI_Update_Performance')
            ->where('No_Transaksi', $approval->No_Transaksi)
            ->value('Id_Update_Performance');

        // Upload GCS SEBELUM transaksi dibuka: HTTP upload yang lambat tidak boleh
        // menahan lock DB.
        try {
            [$newPathByDetailId, $newPaths] = $this->preUploadDataSourceFiles($request);
        } catch (\Throwable $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }

        $oldPaths = [];

        try {
            DB::beginTransaction();

            $summary = ['pica_updated' => 0, 'pica_removed' => 0];

            if ($idUpdatePerformance) {
                $summary = $this->applyPerformanceEdits($request, (int) $idUpdatePerformance);
                $oldPaths = $this->applyDataSourceChanges(
                    (int) $idUpdatePerformance,
                    $newPathByDetailId,
                );
                $this->recalcPerformanceTotals((int) $idUpdatePerformance);
            }

            // Approve (jalur legacy — teruskan $request agar resolvePerfCentralRow
            // tetap dapat membaca no_transaksi bila ada).
            $approvalResult = $this->approvePerformance($request, $id);
            if ($approvalResult->getStatusCode() !== 200) {
                throw new \RuntimeException('Gagal menyetujui pengajuan setelah edit.');
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->cleanupGcsFiles($newPaths); // berkas BARU mati bersama transaksi; berkas LAMA utuh

            Log::channel('ApprovalPerformanceController')->error('ERROR EDIT_APPROVE_PERFORMANCE', [
                'approval_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ResponseHelper::error('Terjadi Kesalahan: '.$e->getMessage(), 500);
        }

        // Pasca-COMMIT, best-effort — kegagalan di sini tidak boleh membatalkan approve.
        try {
            $this->deleteReplacedDataSources($oldPaths);
        } catch (\Throwable $e) {
            Log::channel('ApprovalPerformanceController')->warning('Gagal hapus data source lama: '.$e->getMessage());
        }

        Log::channel('ApprovalPerformanceController')->info('EDIT_APPROVE_PERFORMANCE_SUCCESS', [
            'approval_id' => $id,
            'actual_values_updated' => count((array) $request->input('actual_values', [])),
            'data_source_changed' => count($newPathByDetailId),
            'processed_by' => auth()->user()->id ?? 'unknown',
        ] + $summary);

        return ResponseHelper::success([
            'actual_updated' => count((array) $request->input('actual_values', [])),
            'pica_updated' => $summary['pica_updated'],
            'pica_removed' => $summary['pica_removed'],
            'data_source_changed' => count($newPathByDetailId),
        ], 'Performance edited and approved successfully', 200);
    }

    /**
     * Terapkan edit actual + PICA. DI DALAM transaksi. Dipakai jalur LEGACY & CENTRAL
     * (dulu diduplikasi utuh di dua tempat).
     *
     * @return array{pica_updated:int,pica_removed:int}
     */
    private function applyPerformanceEdits(Request $request, int $idUpdatePerformance): array
    {
        $removalFlags = (array) $request->input('pica_removal_flags', []);
        $updatedPicaCount = 0;
        $removedPicaCount = 0;
        $ditolakPicaCount = 0;

        // 1) Edit actual per parameter.
        foreach ((array) $request->input('actual_values', []) as $kpiId => $actualValue) {
            $this->saveEditedActual($idUpdatePerformance, $kpiId, (float) $actualValue);
        }

        // 2) PICA removal (mandiri).
        foreach ($removalFlags as $kpiId => $shouldRemove) {
            if (! self::boolFlag($shouldRemove)) {
                continue;
            }
            if (! $this->detailBelongsToPerformance($kpiId, $idUpdatePerformance)) {
                continue;
            }
            DB::table('KPI_Update_Performance_Det')->where('Id_Update_Performance_Detail', $kpiId)->delete();
            DB::table('KPI_Update_Performance_Detail')->where('Id_Update_Performance_Detail', $kpiId)->update(['Flag_Pica' => 'T']);
            $removedPicaCount++;
        }

        // 3) PICA create/update (mandiri).
        foreach ((array) $request->input('pica_data', []) as $kpiId => $picaData) {
            if (self::boolFlag($removalFlags[$kpiId] ?? false)) {
                continue;
            }
            if (! $this->detailBelongsToPerformance($kpiId, $idUpdatePerformance)) {
                continue;
            }

            if (self::boolFlag($picaData['enabled'] ?? false)) {
                if (empty($picaData['problem_identification']) || empty($picaData['corrective_actions']) || empty($picaData['due_date'])) {
                    continue;
                }
                /* Pintu KETIGA penulisan PICA (approver menyunting). Aturan yang
                   sama dengan form karyawan — lihat App\Services\KPI\KpiPicaIsi.
                   `empty()` di atas tidak cukup: '-' dan '1' lolos darinya, dan
                   1.167 dari 2.766 baris PICA di production memang berisi itu.
                   Di sini tidak dilempar sebagai galat karena metode ini memproses
                   banyak parameter sekaligus; yang ditolak dihitung dan dilaporkan
                   balik supaya penolakannya tidak senyap. */
                if (! KpiPicaIsi::pasanganBermakna(
                    $picaData['problem_identification'],
                    $picaData['corrective_actions'],
                )) {
                    $ditolakPicaCount++;

                    continue;
                }
                DB::table('KPI_Update_Performance_Det')->updateOrInsert(
                    ['Id_Update_Performance_Detail' => $kpiId],
                    [
                        'Identifikasi' => $picaData['problem_identification'],
                        'Koreksi_Aksi' => $picaData['corrective_actions'],
                        'Tanggal_Pelaksanaan' => $picaData['due_date'],
                    ],
                );
                DB::table('KPI_Update_Performance_Detail')->where('Id_Update_Performance_Detail', $kpiId)->update(['Flag_Pica' => 'Y']);
                $updatedPicaCount++;
            } else {
                DB::table('KPI_Update_Performance_Det')->where('Id_Update_Performance_Detail', $kpiId)->delete();
                DB::table('KPI_Update_Performance_Detail')->where('Id_Update_Performance_Detail', $kpiId)->update(['Flag_Pica' => 'T']);
            }
        }

        return [
            'pica_updated' => $updatedPicaCount,
            'pica_removed' => $removedPicaCount,
            'pica_ditolak' => $ditolakPicaCount,
        ];
    }

    private function detailBelongsToPerformance($detailId, int $idUpdatePerformance): bool
    {
        return DB::table('KPI_Update_Performance_Detail')
            ->where('Id_Update_Performance_Detail', $detailId)
            ->where('Id_Update_Performance', $idUpdatePerformance)
            ->exists();
    }

    /**
     * Edit (actual + PICA) lalu approve untuk jalur CENTRAL.
     *
     * Mereplikasi logika editAndApprovePerformance versi legacy, tetapi:
     *  - resolve No_Transaksi dari HRIS_Approval_Request (bukan KPI_Approval_Request_Performance)
     *  - validasi detail via No_Transaksi
     *  - approve didelegasikan ke approvePerformanceCentral()
     *
     * @param  object  $centralRow  baris HRIS_Approval_Request (PK = $id dari frontend)
     */
    private function editAndApprovePerformanceCentral(Request $request, object $centralRow)
    {
        $this->validateEditApprovePayload($request);

        $noTransaksi = $centralRow->No_Transaksi;

        $idUpdatePerformance = DB::table('KPI_Update_Performance')
            ->where('No_Transaksi', $noTransaksi)
            ->value('Id_Update_Performance');

        if (! $idUpdatePerformance) {
            return ResponseHelper::error('Data performance tidak ditemukan', 404);
        }

        // Pre-check SEBELUM transaksi edit dibuka. Jalur CENTRAL meng-commit edit lebih
        // dulu lalu approve di transaksi terpisah; tanpa pre-check ini, submit dari
        // approver yang salah (atau double-submit) tetap MENYIMPAN edit-nya padahal
        // approve-nya gagal — data berubah tanpa persetujuan.
        if (($centralRow->Flag_Approval ?? null) !== null) {
            return ResponseHelper::error('Transaksi ini baru saja diproses oleh orang lain.', 409);
        }

        $akses = app(\App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService::class)
            ->validateApproverAccess($noTransaksi, Auth::user()->karyawan->Kode_Karyawan);

        if (! ($akses['valid'] ?? false)) {
            return ResponseHelper::error($akses['reason'] ?? 'Anda tidak berhak menyetujui step ini.', 403);
        }

        // Upload GCS SEBELUM transaksi dibuka.
        try {
            [$newPathByDetailId, $newPaths] = $this->preUploadDataSourceFiles($request);
        } catch (\Throwable $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }

        $oldPaths = [];

        try {
            DB::beginTransaction();

            $summary = $this->applyPerformanceEdits($request, (int) $idUpdatePerformance);
            $oldPaths = $this->applyDataSourceChanges(
                (int) $idUpdatePerformance,
                $newPathByDetailId,
            );

            // Recalc TOTAL dari SELURUH detail (bukan hanya yang diedit).
            $this->recalcPerformanceTotals((int) $idUpdatePerformance);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->cleanupGcsFiles($newPaths);

            Log::channel('ApprovalPerformanceController')->error('ERROR EDIT_APPROVE_PERFORMANCE_CENTRAL', [
                'no_transaksi' => $noTransaksi,
                'error' => $e->getMessage(),
            ]);

            return ResponseHelper::error('Terjadi Kesalahan: '.$e->getMessage(), 500);
        }

        // Approve via jalur CENTRAL (membuka transaksinya sendiri).
        $approvalResult = $this->approvePerformanceCentral($centralRow);

        if ($approvalResult->getStatusCode() !== 200) {
            // Edit sudah commit tapi approve gagal. Data source baru SUDAH dirujuk DB,
            // jadi jangan dibersihkan — tapi berkas lama harus tetap hidup, karena
            // approver berikutnya masih perlu melihat riwayatnya.
            Log::channel('ApprovalPerformanceController')->warning(
                'Edit ter-commit tetapi approve gagal — berkas lama sengaja tidak dihapus.',
                ['no_transaksi' => $noTransaksi],
            );

            return $approvalResult;
        }

        try {
            $this->deleteReplacedDataSources($oldPaths);
        } catch (\Throwable $e) {
            Log::channel('ApprovalPerformanceController')->warning('Gagal hapus data source lama: '.$e->getMessage());
        }

        return ResponseHelper::success([
            'actual_updated' => count((array) $request->input('actual_values', [])),
            'pica_updated' => $summary['pica_updated'],
            'pica_removed' => $summary['pica_removed'],
            'data_source_changed' => count($newPathByDetailId),
        ], 'Performance edited and approved successfully', 200);
    }

    private function getPerformanceLevel(float $finalScore)
    {
        return app(\App\Services\KPI\KpiScoringService::class)->resolveLevel($finalScore);
    }

    /**
     * Payload multipart mengirim SEMUA nilai sebagai string, dan string "false"
     * bernilai truthy di PHP. Semua flag harus lewat sini.
     */
    private static function boolFlag($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Validasi payload edit & approve (LEGACY dan CENTRAL).
     *
     * Sengaja tanpa aturan `mimes:` — sniffing mime xlsx/docx/pptx tidak reliable di
     * sebagian server dan menolak file valid. Whitelist ekstensi ditegakkan di
     * uploadDataSourceStream(), identik dengan UpdatePerformanceController.
     */
    private function validateEditApprovePayload(Request $request): void
    {
        // Bila body melewati post_max_size, PHP mengosongkan seluruh request: validasi
        // akan lolos hampa dan pengajuan ter-approve TANPA satu pun edit. Cegat dulu.
        if (empty($request->all()) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            abort(413, 'Total ukuran file melebihi batas server. Kurangi jumlah atau ukuran lampiran.');
        }

        // Data source hanya boleh DIGANTI (bukan dikosongkan): tidak ada removal flag.
        $request->validate([
            'no_transaksi' => 'nullable|string|max:50',
            'actual_values' => 'nullable|array',
            'actual_values.*' => 'nullable|numeric',
            'pica_data' => 'nullable|array',
            'pica_data.*.problem_identification' => 'nullable|string|max:1000',
            'pica_data.*.corrective_actions' => 'nullable|string|max:1000',
            'pica_data.*.due_date' => 'nullable|date',
            'pica_removal_flags' => 'nullable|array',
            'data_source_files' => 'nullable|array',
            'data_source_files.*' => 'file|max:3072', // KB -> 3MB, sama dgn UpdatePerformanceController
        ]);
    }

    /**
     * Upload satu berkas data source ke GCS. Mengikuti persis
     * UpdatePerformanceController::handleFileUploadStream (validasi, penamaan, path).
     */
    private function uploadDataSourceStream(\Illuminate\Http\UploadedFile $file): string
    {
        if (! $file->isValid()) {
            throw new \Exception('File '.$file->getClientOriginalName().' gagal diunggah.');
        }

        if ($file->getSize() > 3 * 1024 * 1024) {
            throw new \Exception('File '.$file->getClientOriginalName().' melebihi ukuran maksimal 3MB.');
        }

        // Penggantian data source dibatasi ke dokumen bukti umum: pdf & gambar.
        $allowedMimes = ['pdf', 'jpg', 'jpeg', 'png'];
        if (! in_array(strtolower($file->getClientOriginalExtension()), $allowedMimes)) {
            throw new \Exception('File '.$file->getClientOriginalName().' tidak diperbolehkan. Hanya PDF, JPG, JPEG, atau PNG.');
        }

        $newFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
            .'_'.\Illuminate\Support\Str::random(6)
            .'_'.date('dmY')
            .'.'.$file->getClientOriginalExtension();

        $temporaryPath = $file->getRealPath() ?: $file->getPathname();
        if (! is_string($temporaryPath) || trim($temporaryPath) === '' || ! is_file($temporaryPath)) {
            throw new \Exception('File '.$file->getClientOriginalName().' tidak memiliki temporary path yang valid.');
        }

        $stream = fopen($temporaryPath, 'rb');
        if ($stream === false) {
            throw new \Exception('File '.$file->getClientOriginalName().' gagal dibaca.');
        }

        $gcsPath = 'KPI/data-source/'.$newFileName;

        try {
            $stored = Storage::disk('gcs')->put($gcsPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $stored) {
            throw new \Exception('File '.$file->getClientOriginalName().' gagal disimpan.');
        }

        return $gcsPath;
    }

    /**
     * Upload SEMUA data source baru SEBELUM transaksi dibuka, agar HTTP upload yang
     * lambat tidak menahan lock DB. Bila salah satu gagal, yang terlanjur ter-upload
     * langsung dibersihkan lalu exception dilempar ulang.
     *
     * @return array{0: array<int,string>, 1: array<int,string>} [path baru per detailId, semua path baru]
     */
    private function preUploadDataSourceFiles(Request $request): array
    {
        $newPathByDetailId = [];
        $newPaths = [];

        try {
            foreach ((array) $request->file('data_source_files', []) as $detailId => $file) {
                if (! $file) {
                    continue;
                }
                $path = $this->uploadDataSourceStream($file);
                $newPathByDetailId[(int) $detailId] = $path;
                $newPaths[] = $path;
            }
        } catch (\Throwable $e) {
            $this->cleanupGcsFiles($newPaths);
            throw $e;
        }

        return [$newPathByDetailId, $newPaths];
    }

    /**
     * Hapus berkas GCS yatim (upload sukses tapi transaksi rollback). Best-effort:
     * kegagalan hanya di-log, tidak pernah melempar.
     *
     * @param  array<int,string>  $paths
     */
    private function cleanupGcsFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }
            try {
                Storage::disk('gcs')->delete($path);
            } catch (\Throwable $e) {
                Log::channel('ApprovalPerformanceController')->warning(
                    'Gagal hapus berkas GCS yatim setelah rollback: '.$path.' — '.$e->getMessage(),
                );
            }
        }
    }

    /**
     * DI DALAM transaksi. Terapkan PENGGANTIAN data source per parameter (tidak ada
     * penghapusan — data source tidak boleh dikosongkan). Setiap detailId diverifikasi
     * milik $idUpdatePerformance dulu (payload karangan tidak boleh menyentuh lampiran
     * karyawan lain).
     *
     * @param  array<int,string>  $newPathByDetailId
     * @return array<int,string> path lama — kandidat dihapus SETELAH commit
     */
    private function applyDataSourceChanges(int $idUpdatePerformance, array $newPathByDetailId): array
    {
        if (empty($newPathByDetailId)) {
            return [];
        }

        $existing = DB::table('KPI_Update_Performance_Detail')
            ->where('Id_Update_Performance', $idUpdatePerformance)
            ->whereIn('Id_Update_Performance_Detail', array_keys($newPathByDetailId))
            ->pluck('File_Berkas', 'Id_Update_Performance_Detail');

        $oldPaths = [];

        foreach ($newPathByDetailId as $detailId => $newPath) {
            if (! $existing->has($detailId)) {
                Log::channel('ApprovalPerformanceController')->warning(
                    "Data source diabaikan: detail {$detailId} bukan milik performance {$idUpdatePerformance}",
                );

                continue;
            }

            $oldPath = trim((string) $existing->get($detailId));

            DB::table('KPI_Update_Performance_Detail')
                ->where('Id_Update_Performance_Detail', $detailId)
                ->where('Id_Update_Performance', $idUpdatePerformance)
                ->update(['File_Berkas' => $newPath]);

            if ($oldPath !== '' && $oldPath !== $newPath) {
                $oldPaths[] = $oldPath;
            }

            Log::channel('ApprovalPerformanceController')->info('DATA_SOURCE_CHANGED', [
                'id_update_performance' => $idUpdatePerformance,
                'id_detail' => $detailId,
                'aksi' => 'ganti',
                'path_lama' => $oldPath ?: null,
                'path_baru' => $newPath,
                'oleh' => auth()->user()->id ?? 'unknown',
            ]);
        }

        return $oldPaths;
    }

    /**
     * SETELAH commit. Hapus berkas lama dari GCS — tapi HANYA bila tidak ada baris lain
     * yang masih mereferensikannya.
     *
     * Alur resubmit menyalin string File_Berkas dari pengajuan sebelumnya ke pengajuan
     * baru (UpdatePerformanceController), jadi satu path bisa dipakai bersama. Hapus
     * buta akan melubangi riwayat pengajuan lama dan export.
     *
     * @param  array<int,string>  $oldPaths
     */
    private function deleteReplacedDataSources(array $oldPaths): void
    {
        foreach (array_unique($oldPaths) as $oldPath) {
            if (! is_string($oldPath) || $oldPath === '') {
                continue;
            }

            try {
                // Hapus HANYA bila tidak ada baris detail dari pengajuan yang masih AKTIF
                // (Flag_Aktif='Y') yang memakai path ini. Contoh yang HARUS tetap dilindungi:
                // dua parameter di satu pengajuan aktif memakai berkas sama.
                //
                // Pengajuan lama yang sudah TIDAK aktif (ditolak / tergantikan oleh revisi)
                // TIDAK dihitung: saat resubmit, File_Berkas yang tidak diganti disalin dari
                // pengajuan sebelumnya, sehingga path yang sama tertinggal di pengajuan lama
                // yang sudah nonaktif. Menghitungnya akan MEMBLOKIR penghapusan selamanya dan
                // meninggalkan orphan di GCS.
                //
                // Tabel *_Sementara juga TIDAK dicek: area draft selalu menyalin path final.
                $stillUsed = DB::table('KPI_Update_Performance_Detail as d')
                    ->join('KPI_Update_Performance as h', 'h.Id_Update_Performance', '=', 'd.Id_Update_Performance')
                    ->where('d.File_Berkas', $oldPath)
                    ->where('h.Flag_Aktif', 'Y')
                    ->exists();

                if ($stillUsed) {
                    Log::channel('ApprovalPerformanceController')->info(
                        'Berkas GCS lama masih dipakai pengajuan lain, tidak dihapus: '.$oldPath,
                    );

                    continue;
                }

                Storage::disk('gcs')->delete($oldPath);

                Log::channel('ApprovalPerformanceController')->info(
                    'Berkas GCS lama dihapus setelah penggantian data source: '.$oldPath,
                );
            } catch (\Throwable $e) {
                Log::channel('ApprovalPerformanceController')->warning(
                    'Gagal hapus berkas GCS lama: '.$oldPath.' — '.$e->getMessage(),
                );
            }
        }
    }

    /**
     * Simpan satu actual yang diedit leader + hitung ulang Score & Achievement
     * parameter tsb. Konvensi sistem (lihat UpdatePerformanceController):
     *   Score       = $calc['achievement'] (persentase pencapaian)
     *   Achievement = $calc['score']       (skor berbobot = bobot * ratio)
     */
    private function saveEditedActual(int $idUpdatePerformance, $kpiId, $actualValue, ?string $noTransaksi = null): void
    {
        $detail = DB::table('KPI_Update_Performance_Detail')
            ->where('Id_Update_Performance_Detail', $kpiId)
            ->where('Id_Update_Performance', $idUpdatePerformance)
            ->first();

        if (! $detail) {
            Log::channel('ApprovalPerformanceController')->warning("Invalid KPI ID: {$kpiId} for performance: {$idUpdatePerformance}");

            return;
        }

        $param = DB::table('KPI_Pengajuan_Det as a')
            ->join('KPI_Pengajuan_Detail as b', 'a.Id_Pengajuan_Detail', '=', 'b.Id_Pengajuan_Detail')
            ->join('KPI_Master_Formula as c', 'b.Id_Master_Formula', '=', 'c.Id_Master_Formula')
            ->where('a.Id_Pengajuan_Det', $detail->Id_Pengajuan_Det)
            ->select('b.Bobot', 'a.Nilai_Target', 'c.Id_Master_Formula', 'Rumus')
            ->first();

        if (! $param) {
            return;
        }

        $formulas = [$param->Id_Master_Formula => $param->Rumus];
        $calc = $this->calculateFormulaStream($actualValue, $param->Nilai_Target, $param->Bobot, $param->Id_Master_Formula, $formulas);

        DB::table('KPI_Update_Performance_Detail')
            ->where('Id_Update_Performance_Detail', $kpiId)
            ->update([
                'Nilai_Actual' => $actualValue,
                'Score' => $calc['achievement'],
                'Achievement' => $calc['score'],
                'Score_Raw' => $calc['achievement_raw'] ?? $calc['achievement'],
                'Achievement_Raw' => $calc['score_raw'] ?? $calc['score'],
                'Flag_Capped' => ! empty($calc['is_capped']) ? 'Y' : 'T',
            ]);
    }

    /**
     * Hitung ulang TOTAL header dari SELURUH detail aktif (bukan hanya yang diedit),
     * lalu terapkan cap min/max. Dipakai setelah edit actual / hapus PICA agar
     * Total_Score & Total_Score_Achievement selalu konsisten dengan data faktual.
     *
     * Konvensi total (UpdatePerformanceController::calculateScores):
     *   sum_of_scores = Σ(detail.Achievement)  [skor berbobot]
     *   final %       = (sum_of_scores / Σbobot) * 100  -> di-cap -> Total_Score
     *   Total_Score_Achievement = sum_of_scores (TIDAK di-cap, data faktual)
     */
    /**
     * Recalc total berdasarkan No_Transaksi (dipakai finalisasi verifikasi agar
     * total header selalu konsisten dgn detail faktual saat disetujui final).
     */
    public function recalcTotalsByNoTransaksi(string $noTransaksi): void
    {
        $idUp = DB::table('KPI_Update_Performance')->where('No_Transaksi', $noTransaksi)->value('Id_Update_Performance');
        if ($idUp) {
            $this->recalcPerformanceTotals((int) $idUp);
        }
    }

    private function recalcPerformanceTotals(int $idUpdatePerformance): void
    {
        $details = DB::table('KPI_Update_Performance_Detail as upd')
            ->join('KPI_Pengajuan_Det as pdet', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
            ->join('KPI_Pengajuan_Detail as pd', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
            ->where('upd.Id_Update_Performance', $idUpdatePerformance)
            ->get(['upd.Achievement', 'upd.Achievement_Raw', 'pd.Bobot']);

        // Terpusat: agregasi (Σweighted/Σbobot×100) + cap + level via KpiScoringService,
        // jalur kanonik yang sama dengan finalisasi UpdatePerformanceController.
        $svc = app(\App\Services\KPI\KpiScoringService::class);
        $scores = $svc->applyCap($svc->aggregate($details)); // keys default: Achievement (weighted) & Bobot
        $cappedPct = (float) $scores['final_score_percentage'];
        $level = $svc->resolveLevel(floor($cappedPct));

        DB::table('KPI_Update_Performance')
            ->where('Id_Update_Performance', $idUpdatePerformance)
            ->update([
                'Total_Score' => $cappedPct,
                'Total_Score_Achievement' => round((float) $scores['sum_of_scores'], 2),
                'Total_Bobot' => $scores['sum_of_active_bobot'],
                'Total_Score_Raw' => $scores['raw_final_score_percentage'],
                'Total_Score_Achievement_Raw' => round((float) $scores['raw_sum_of_scores'], 2),
                'Flag_Capped' => $scores['is_capped'] ? 'Y' : 'T',
                // Lihat UpdatePerformanceController::METODE_CAP_PARAMETER - header
                // Flag_Capped bermakna ganda, kolom ini yang membedakan metodenya.
                'Metode_Cap' => UpdatePerformanceController::METODE_CAP_PARAMETER,
                // Selalu diisi: nilainya menjelaskan ATURAN yang berlaku saat record
                // dihitung, bukan sekadar jejak bahwa penjepitan pernah terjadi.
                // Jalur Update Performance sudah selalu mengisinya; ini menyamakan.
                'Cap_Min_Applied' => $scores['cap_min_applied'],
                'Cap_Max_Applied' => $scores['cap_max_applied'],
                'Id_Performance' => $level->Id_Master_Performance,
                'Flag_Edit' => 'Y',
            ]);
    }

    // public function editAndApprovePerformance(Request $request, $id)
    // {
    //     try {

    //         // dd($request->all());
    //         DB::beginTransaction();

    //         $approval = DB::table('KPI_Approval_Request_Performance')
    //             ->where('Id_Approval_Request', $id)
    //             ->first();

    //         if (!$approval) {
    //             return ResponseHelper::error("Approval request not found", 404);
    //         }

    //         // Update actual values if provided
    //         if ($request->has('actual_values')) {
    //             foreach ($request->actual_values as $kpiId => $actualValue) {
    //                 DB::table('KPI_Update_Performance_Detail')
    //                     ->where('Id_Update_Performance_Detail', $kpiId)
    //                     ->update([
    //                         'Nilai_Actual' => $actualValue,
    //                     ]);
    //             }
    //         }

    //         // Update PICA data if provided
    //         if ($request->has('pica_data')) {
    //             foreach ($request->pica_data as $kpiId => $picaData) {
    //                 if ($picaData['enabled']) {
    //                     DB::table('KPI_Update_Performance_Det')
    //                         ->updateOrInsert(
    //                             ['Id_Update_Performance_Detail' => $kpiId],
    //                             [
    //                                 'Identifikasi' => $picaData['problem_identification'],
    //                                 'Koreksi_Aksi' => $picaData['corrective_actions'],
    //                                 'Tanggal_Pelaksanaan' => $picaData['due_date'],
    //                             ]
    //                         );

    //                     // Set Flag_Pica to Y in main table
    //                     DB::table('KPI_Update_Performance_Detail')
    //                         ->where('Id_Update_Performance_Detail', $kpiId)
    //                         ->update([
    //                             'Flag_Pica' => 'Y'
    //                         ]);
    //                 }
    //             }
    //         }

    //         // Then approve the performance
    //         $this->approvePerformance($id);

    //         DB::commit();

    //         return ResponseHelper::success([], "Performance edited and approved successfully", 200);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::channel('ApprovalPerformanceController')->error('ERROR EDIT_APPROVE_PERFORMANCE: '. $e->getMessage());
    //         return ResponseHelper::error("Terjadi Kesalahan", 500);
    //     }
    // }
}
