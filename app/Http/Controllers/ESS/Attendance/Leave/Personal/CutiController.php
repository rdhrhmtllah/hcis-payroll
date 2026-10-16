<?php

namespace App\Http\Controllers\ESS\Attendance\Leave\Personal;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\Whatsapp;
use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Models\ESS\Employee\Models\Karyawan;
use App\Services\ESS\Attendance\Leave\Admin\cutiBersamaService as CutiBersamaService;
use App\Services\ESS\Attendance\Leave\Notification\CutiNotificationService;
use App\Services\ESS\Attendance\Leave\Shared\TanggalCutiBersama;
use App\Services\ESS\Attendance\Leave\Shared\TanggalTransaksiIzin;
use App\Services\ESS\Attendance\Overtime\Conversion\KonversiLemburService;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApproverResolverService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class CutiController extends Controller
{
    public function __construct(
        protected CutiBersamaService $cutiBersamaService,
        protected AttendanceDirtyResolverService $attendanceDirtyResolver,
        protected ApprovalFlowService $approvalFlowService,
        protected ApproverResolverService $approverResolver,
        protected CutiNotificationService $cutiNotificationService,
        protected TransactionService $transactionService,
        protected KonversiLemburService $konversiLemburService,
    ) {}

    public function index()
    {
        $karyawan = Auth::user()->karyawan;
        $durasiMasuk = Carbon::parse($karyawan->Tgl_Masuk)->diffInMonths(now());
        $gender = $karyawan->Jenis_Kelamin;

        $tanggalMerah = DB::table('HRIS_Hari_Libur')->get();
        // dd($gender);

        return inertia('ESS/Attendance/Leave/Cuti/Karyawan/CutiPengajuan', [
            'durasiMasuk' => $durasiMasuk,
            'gender' => $gender,
            'specialDate' => $tanggalMerah,
        ]);
    }

    public function current(Request $request)
    {
        $karyawan = Auth::user()->karyawan;
        $batasApproveCuti = config('services.hcis.batas_approve_cuti');
        $hasWindow = $request->filled('start') && $request->filled('end');
        $startRange = null;
        $endRange = null;

        if ($hasWindow) {
            try {
                $startRange = Carbon::parse($request->query('start'))->startOfDay();
                $endRange = Carbon::parse($request->query('end'))->startOfDay();

                if ($startRange->greaterThan($endRange)) {
                    [$startRange, $endRange] = [$endRange, $startRange];
                }
            } catch (\Throwable $e) {
                $hasWindow = false;
                $startRange = null;
                $endRange = null;
            }
        }
        // $tanggalCuti = DB::table('Transaksi_Cuti_Det')
        //                 ->join('Transaksi_Cuti','Transaksi_Cuti_Det.No_Transaksi','=','Transaksi_Cuti.No_Transaksi')
        //                 ->join('Transaksi_Cuti_Detail','Transaksi_Cuti_Det.No_Transaksi','=','Transaksi_Cuti_Detail.No_Transaksi')
        //                 ->where('Transaksi_Cuti_Det.Kode_Karyawan', $karyawan->Kode_Karyawan)
        //                 ->where(function($q) {
        //                     $q->whereNull('Transaksi_Cuti_Detail.Flag_approval')
        //                     ->orWhere('Transaksi_Cuti_Detail.Flag_approval', '==', 'Y');
        //                 })
        //                 ->get();
        $tanggalCuti = DB::table('Transaksi_Cuti_Det')
            ->join('Transaksi_Cuti', 'Transaksi_Cuti_Det.No_Transaksi', '=', 'Transaksi_Cuti.No_Transaksi')
            ->join('Transaksi_Cuti_Detail', function ($join) {
                $join
                    ->on('Transaksi_Cuti_Det.No_Transaksi', '=', 'Transaksi_Cuti_Detail.No_Transaksi')
                    ->on('Transaksi_Cuti_Det.Urut_Detail', '=', 'Transaksi_Cuti_Detail.Urut_Oto');
            })
            ->where('Transaksi_Cuti_Det.Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->whereNull('Transaksi_Cuti.Status')
            ->where(function ($q) {
                $q->whereNull('Transaksi_Cuti_Detail.Flag_approval')->orWhere(
                    'Transaksi_Cuti_Detail.Flag_approval',
                    'Y',
                );
            });

        if (! empty($batasApproveCuti)) {
            $tanggalCuti->whereRaw(
                "
                    DATEDIFF(
                        DAY,
                        TRY_CONVERT(
                            DATETIME,
                            CONCAT(
                                CONVERT(
                                    VARCHAR,
                                    CONVERT(DATE, Transaksi_Cuti.Tanggal, 105),
                                    23
                                ),
                                ' ',
                                Transaksi_Cuti.Jam
                            ),
                            120
                        ),
                        GETDATE()
                    ) < ?
                ",
                [$batasApproveCuti],
            );
        }

        if ($hasWindow) {
            $tanggalCuti
                ->whereDate('Transaksi_Cuti_Det.Tanggal_Cuti', '>=', $startRange->toDateString())
                ->whereDate('Transaksi_Cuti_Det.Tanggal_Cuti', '<=', $endRange->toDateString());
        }

        $tanggalCuti = $tanggalCuti
            ->select([
                'Transaksi_Cuti_Det.No_Transaksi',
                'Transaksi_Cuti_Det.Kode_Karyawan',
                'Transaksi_Cuti_Det.Tanggal_Cuti',
                'Transaksi_Cuti_Detail.Jenis',
                'Transaksi_Cuti_Detail.Flag_approval',
                'Transaksi_Cuti_Detail.Id_Jenis_Cuti',
            ])
            ->get();

        $tanggalLibur = DB::table('HRIS_Hari_Libur')
            ->when($hasWindow, function ($query) use ($startRange, $endRange) {
                $query
                    ->whereDate('Tanggal', '>=', $startRange->toDateString())
                    ->whereDate('Tanggal', '<=', $endRange->toDateString());
            })
            ->get();

        $tanggalSakitIzin = DB::table('Transaksi_Sakit_Izin_Det')
            ->join(
                'Transaksi_Sakit_Izin',
                'Transaksi_Sakit_Izin_Det.No_Transaksi',
                '=',
                'Transaksi_Sakit_Izin.No_Transaksi',
            )
            ->join('Transaksi_Sakit_Izin_Detail', function ($join) {
                $join
                    ->on('Transaksi_Sakit_Izin_Det.No_Transaksi', '=', 'Transaksi_Sakit_Izin_Detail.No_Transaksi')
                    ->on('Transaksi_Sakit_Izin_Det.Urut_Detail', '=', 'Transaksi_Sakit_Izin_Detail.Urut_Oto');
            })
            ->where('Transaksi_Sakit_Izin_Det.Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->whereNull('Transaksi_Sakit_Izin.Status')
            ->where(function ($q) {
                $q->whereNull('Transaksi_Sakit_Izin_Detail.Flag_approval')->orWhere(
                    'Transaksi_Sakit_Izin_Detail.Flag_approval',
                    'Y',
                );
            })
            // Sembunyikan pengajuan yang sudah GUGUR (approval flow kedaluwarsa) agar
            // penandaan konflik di kalender selaras dengan submit-guard & dashboard
            // homepage. Sumber kebenaran: step Flow_State='EXPIRED' di
            // N_HRIS_VW_Approval_Request_V2 (bukan heuristik DATEDIFF umur yang lama).
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_HRIS_VW_Approval_Request_V2 as ar_exp')
                    ->whereColumn('ar_exp.No_Transaksi', 'Transaksi_Sakit_Izin_Det.No_Transaksi')
                    ->whereRaw("UPPER(LTRIM(RTRIM(COALESCE(ar_exp.Flow_State, '')))) = 'EXPIRED'");
            })
            ->when($hasWindow, function ($query) use ($startRange, $endRange) {
                $query
                    ->whereDate('Transaksi_Sakit_Izin_Det.Tanggal_Sakit_Izin', '>=', $startRange->toDateString())
                    ->whereDate('Transaksi_Sakit_Izin_Det.Tanggal_Sakit_Izin', '<=', $endRange->toDateString());
            })
            ->select([
                'Transaksi_Sakit_Izin_Det.No_Transaksi',
                'Transaksi_Sakit_Izin_Det.Kode_Karyawan',
                'Transaksi_Sakit_Izin_Det.Tanggal_Sakit_Izin',
                'Transaksi_Sakit_Izin_Detail.Jenis',
                'Transaksi_Sakit_Izin_Detail.Flag_approval',
            ])
            ->get();
        $tanggalTerlambatPulangCepat = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
            ->join(
                'Transaksi_Terlambat_Pulang_Cepat',
                'Transaksi_Terlambat_Pulang_Cepat_Detail.No_Transaksi',
                '=',
                'Transaksi_Terlambat_Pulang_Cepat.No_Transaksi',
            )
            ->where('Transaksi_Terlambat_Pulang_Cepat_Detail.Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->whereNull('Transaksi_Terlambat_Pulang_Cepat.Status')
            ->where(function ($q) {
                $q->whereNull('Transaksi_Terlambat_Pulang_Cepat_Detail.Flag_approval')->orWhere(
                    'Transaksi_Terlambat_Pulang_Cepat_Detail.Flag_approval',
                    'Y',
                );
            })
            // Sama seperti sakit/izin: sembunyikan yang sudah GUGUR (Flow_State='EXPIRED')
            // agar konsisten dengan submit-guard & dashboard, bukan heuristik DATEDIFF.
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_HRIS_VW_Approval_Request_V2 as ar_exp')
                    ->whereColumn('ar_exp.No_Transaksi', 'Transaksi_Terlambat_Pulang_Cepat_Detail.No_Transaksi')
                    ->whereRaw("UPPER(LTRIM(RTRIM(COALESCE(ar_exp.Flow_State, '')))) = 'EXPIRED'");
            })
            ->when($hasWindow, function ($query) use ($startRange, $endRange) {
                $query->whereRaw(
                    'CAST(COALESCE(Transaksi_Terlambat_Pulang_Cepat_Detail.Tanggal_Hari_Kerja, Transaksi_Terlambat_Pulang_Cepat_Detail.Tanggal_Masuk_Pulang) AS DATE) BETWEEN ? AND ?',
                    [$startRange->toDateString(), $endRange->toDateString()],
                );
            })
            ->select([
                'Transaksi_Terlambat_Pulang_Cepat_Detail.No_Transaksi',
                'Transaksi_Terlambat_Pulang_Cepat_Detail.Kode_Karyawan',
                'Transaksi_Terlambat_Pulang_Cepat_Detail.Tanggal_Masuk_Pulang',
                'Transaksi_Terlambat_Pulang_Cepat_Detail.Tanggal_Hari_Kerja',
                'Transaksi_Terlambat_Pulang_Cepat_Detail.Jenis',
                'Transaksi_Terlambat_Pulang_Cepat_Detail.Flag_approval',
                'Transaksi_Terlambat_Pulang_Cepat_Detail.Jam',
                DB::raw(
                    'CAST(COALESCE(Transaksi_Terlambat_Pulang_Cepat_Detail.Tanggal_Hari_Kerja, Transaksi_Terlambat_Pulang_Cepat_Detail.Tanggal_Masuk_Pulang) AS DATE) as Tanggal_Aktual_Izin',
                ),
            ])
            ->get();
        if (! $hasWindow) {
            $year = Carbon::now()->year;
            $startRange = Carbon::create($year - 1, 1, 1); // 1 Januari tahun kemarin
            $endRange = Carbon::create($year + 1, 12, 31); // 31 Desember tahun depan
        }

        $tanggalCutiBersama = collect($this->getCutiBersamaItemsForKaryawan($karyawan->Kode_Karyawan))
            ->filter(
                fn ($item) => $this->cutiBersamaItemOverlapsRange(
                    $item,
                    $startRange->toDateString(),
                    $endRange->toDateString(),
                ),
            )
            ->flatMap(fn ($item) => $this->mapCutiBersamaItemToLegacyRecords($item))
            ->values();

        // TVF GetStatusHariRange berat & sering dipanggil ulang utk rentang sama;
        // status-hari deterministik per hari -> cache pendek per (perusahaan, karyawan, rentang).
        $kodePerusahaanDayOff = $karyawan->Kode_Perusahaan ?? '001';
        $tanggalDayOff = collect(
            \Illuminate\Support\Facades\Cache::remember(
                'statushari.range:'.$kodePerusahaanDayOff.':'.$karyawan->Kode_Karyawan
                    .':'.$startRange->toDateString().':'.$endRange->toDateString().':OFF',
                300,
                fn () => DB::select("SELECT * FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?) WHERE StatusHari LIKE 'OFF%'", [
                    $kodePerusahaanDayOff,
                    $karyawan->Kode_Karyawan,
                    $startRange->toDateString(),
                    $endRange->toDateString(),
                ]),
            ),
        )
            ->map(function ($row) {
                if (empty($row->Tanggal)) {
                    return null;
                }

                return [
                    'date' => Carbon::parse($row->Tanggal)->format('Y-m-d'),
                    'status_hari' => $row->StatusHari ?? 'OFF',
                ];
            })
            ->filter()
            ->unique('date')
            ->values()
            ->all();
        // Konversi lembur -> Day Off yang masih PENDING (menunggu approval) = kandidat libur.
        // Tanggal ini belum muncul sebagai OFF di status-hari, jadi perlu di-disable manual.
        $tanggalKonversiLemburPending = $hasWindow
            ? $this->konversiLemburService->getPendingDayOffTargetDates(
                $karyawan->Kode_Karyawan,
                $startRange->toDateString(),
                $endRange->toDateString(),
            )
            : [];

        $groupedTanggal = [
            'tanggalCuti' => $tanggalCuti,
            'tanggalLibur' => $tanggalLibur,
            'tanggalSakitIzin' => $tanggalSakitIzin,
            'tanggalTerlambatPulangCepat' => $tanggalTerlambatPulangCepat,
            'tanggalCutiBersama' => $tanggalCutiBersama,
            'tanggalDayOff' => $tanggalDayOff,
            'tanggalKonversiLemburPending' => $tanggalKonversiLemburPending,
        ];

        // dd($groupedTanggal);
        return ResponseHelper::success(
            $groupedTanggal,
            'Data tanggal cuti, sakit izin, terlambat pulang cepat, dan hari libur berhasil diambil.',
            200,
        );
    }
    // public function getRiwayat(Request $request)
    // {
    //     try {
    //         $pengguna = Auth::user()->karyawan;
    //         $limit = (int) $request->input('limit', 5);
    //         $page = (int) $request->input('page', 1);
    //         $offset = ($page - 1) * $limit;

    //         $searchQuery = $request->input('search_query');
    //         $statusFilter = $request->input('status_filter');

    //         // Base query tanpa pagination
    //         $cte = "
    //             WITH RiwayatCuti AS (
    //                 SELECT
    //                     b.No_Transaksi,
    //                     a.Jenis AS jenis_cuti,
    //                     a.Tanggal_Cuti_Dari AS tanggal_mulai,
    //                     a.Tanggal_Cuti_Sampai AS tanggal_selesai,

    //                     --DATEDIFF(DAY, a.Tanggal_Cuti_Dari, a.Tanggal_Cuti_Sampai) + 1 AS jumlah_hari,
    //                     (SELECT COUNT(Tanggal_Cuti) FROM Transaksi_Cuti_Det WHERE No_Transaksi = b.No_Transaksi AND Kode_Karyawan = a.Kode_Karyawan)AS jumlah_hari,
    //                     (SELECT String_AGG(FORMAT(Tanggal_Cuti, 'dd MMMM yyyy', 'id-ID'), ', ') FROM Transaksi_Cuti_Det WHERE No_Transaksi = b.No_Transaksi AND Kode_Karyawan = a.Kode_Karyawan)AS tanggal_cuti,
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
    //                             COALESCE(k.Nama, g.Keterangan, 'Unknown') AS approver_name,
    //                             ISNULL(ar.Flag_Approval, 'N') AS approval_status,
    //                             ar.Request_Order_Flow AS order_flow,
    //                             ar.Flag_Group AS is_group
    //                         FROM HRIS_Approval_Request ar
    //                         LEFT JOIN Karyawan k ON ar.Approver_Karyawan_Kode = k.Kode_Karyawan
    //                         LEFT JOIN N_HRIS_HC_Approval g ON ar.Group_Reference_Id = g.Group_Id AND ar.Flag_Group = 'Y'
    //                         WHERE ar.No_Transaksi = b.No_Transaksi
    //                         ORDER BY ar.Request_Order_Flow
    //                         FOR JSON PATH
    //                     ) AS approvers
    //                 FROM Transaksi_Cuti b
    //                 INNER JOIN Transaksi_Cuti_Detail a ON b.No_Transaksi = a.No_Transaksi
    //                 LEFT JOIN N_HRIS_Jenis_Cuti jc ON a.Jenis = jc.Nama_Jenis_Cuti
    //                 WHERE a.Kode_Karyawan = ?
    //                 AND b.Status IS NULL
    //             )
    //         ";

    //         // Build WHERE filter tambahan
    //         $whereClauses = [];
    //         $params = [$pengguna->Kode_Karyawan];

    //         if ($searchQuery) {
    //             $whereClauses[] = "alasan LIKE ? OR jenis_cuti LIKE ? OR deskripsi_cuti LIKE ?";
    //             $params[] = "%{$searchQuery}%";
    //             $params[] = "%{$searchQuery}%";
    //             $params[] = "%{$searchQuery}%";
    //         }

    //         if ($statusFilter) {
    //             $whereClauses[] = "status = ?";
    //             $params[] = $statusFilter;
    //         }

    //         $whereSql = !empty($whereClauses)
    //             ? "WHERE " . implode(" AND ", $whereClauses)
    //             : "";

    //         // Query count total
    //         $countQuery = $cte . " SELECT COUNT(*) AS total FROM RiwayatCuti $whereSql";
    //         $total = DB::select($countQuery, $params)[0]->total;

    //         // Query data dengan pagination
    //         $dataQuery = $cte . "
    //             SELECT * FROM RiwayatCuti
    //             $whereSql
    //             ORDER BY created_at DESC
    //             OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
    //         ";

    //         $params[] = $offset;
    //         $params[] = $limit;

    //         $riwayat = collect(DB::select($dataQuery, $params))->map(function ($item) {
    //             $item->approvers = json_decode($item->approvers);
    //             if ($item->filePath) {
    //                 try {
    //                     $item->file_url = Storage::disk('gcs')->temporaryUrl($item->filePath, now()->addMinutes(15));
    //                 } catch (\Exception $e) {
    //                     Log::channel('cutiController')->warning('Gagal generate temporary URL: ' . $e->getMessage());
    //                     $item->file_url = null;
    //                 }
    //             } else {
    //                 $item->file_url = null;
    //             }
    //             return $item;
    //         });

    //         return ResponseHelper::successWithPaginationV2(
    //             $riwayat,
    //             $page,
    //             $limit,
    //             $total,
    //             "Riwayat cuti berhasil diambil.",
    //             200
    //         );

    //     } catch (\Exception $e) {
    //         Log::channel('cutiController')->error('Gagal mengambil riwayat cuti: ' . $e->getMessage() . ' on line ' . $e->getLine());
    //         return ResponseHelper::error('Gagal mengambil riwayat cuti.', 500);
    //     }
    // }

    // public function getRiwayat(Request $request)
    // {
    //     try {
    //         $userKode = Auth::user()->karyawan->Kode_Karyawan;

    //         // 1. Validasi Input
    //         $limit = (int) $request->input('limit', 10);
    //         $page = (int) $request->input('page', 1);
    //         $offset = ($page - 1) * $limit;

    //         $searchQuery = strip_tags($request->input('search_query') ?? '');
    //         $statusFilter = $request->input('status_filter');
    //         $tanggalFilter = $request->input('tanggalFilter');

    //         // 2. QUERY BUILDER UTAMA (MENGGUNAKAN VIEW)
    //         // Filter data berdasarkan Kode Karyawan (Milik Saya)
    //         $dataQuery = DB::table('N_HRIS_VW_All_Transactions as t')
    //             ->where('t.Kode_Karyawan_Requester', $userKode)
    //             ->where('t.Tipe_Transaksi', 'CUTI');
    //         // dd($dataQuery->get());

    //         // 3. FILTER SEARCH
    //         if ($searchQuery) {
    //             $searchParam = '%' . $searchQuery . '%';
    //             $dataQuery->where(function($query) use ($searchParam) {
    //                 $query->where('t.Deskripsi', 'like', $searchParam) // Alasan
    //                     ->orWhere('t.Sub_Tipe', 'like', $searchParam) // Jenis Cuti
    //                     ->orWhere('t.No_Transaksi', 'like', $searchParam);
    //             });
    //         }

    //         // 4. FILTER STATUS
    //         if ($statusFilter) {
    //             // Mapping status filter dari Frontend ke kolom View
    //             if ($statusFilter == 'approved') {
    //                 $dataQuery->where('t.Flag_Approval', 'Y');
    //             } elseif ($statusFilter == 'rejected') {
    //                 $dataQuery->where('t.Flag_Approval', 'T');
    //             } elseif ($statusFilter == 'pending') {
    //                 $dataQuery->whereNull('t.Flag_Approval');
    //             }
    //         }

    //         if($tanggalFilter){
    //             $filterDate = Carbon::parse($tanggalFilter)->toDateString();
    //             // dd($dataQuery->get());
    //             $dataQuery->where(function($q) use ($filterDate) {
    //                 $q->whereDate('t.Tanggal_Mulai', '<=', $filterDate)
    //                 ->whereDate('t.Tanggal_Selesai', '>=', $filterDate);
    //             });
    //         }

    //         // 5. HITUNG TOTAL (Untuk Pagination)
    //         // Clone query agar where clause-nya terbawa
    //         $total = $dataQuery->count();

    //         // 6. EKSEKUSI PAGINATION
    //         $riwayatItems = $dataQuery
    //             ->orderBy('t.Created_At', 'DESC')
    //             ->offset($offset)
    //             ->limit($limit)
    //             ->get();

    //         // 7. MAPPING DATA & AMBIL APPROVAL FLOW
    //         // Kumpulkan No_Transaksi untuk Eager Loading Approval
    //         $transactionIds = $riwayatItems->pluck('No_Transaksi')->toArray();

    //         $allApprovals = collect();
    //         if (!empty($transactionIds)) {
    //             // Ambil history approval dari View Approval Request
    //             $allApprovals = DB::table('N_HRIS_VW_Approval_Request as ar')
    //                 ->leftJoin('Karyawan as k', 'ar.Current_Approver_Kode', '=', 'k.Kode_Karyawan')
    //                 ->whereIn('ar.No_Transaksi', $transactionIds)
    //                 ->select(
    //                     'ar.No_Transaksi',
    //                     'ar.Current_Approver_Kode',
    //                     'ar.Flag_Approval',
    //                     'ar.Request_Order_Flow',
    //                     'ar.Tanggal_Approved',
    //                     'ar.Flow_State',
    //                     'k.Nama as Nama_Approver'
    //                 )
    //                 ->orderBy('ar.Request_Order_Flow', 'ASC')
    //                 ->get()
    //                 ->groupBy('No_Transaksi');
    //         }

    //         $formattedData = $riwayatItems->map(function ($item) use ($allApprovals) {

    //             // Logic Approvers
    //             $approverRows = $allApprovals->get($item->No_Transaksi, collect());

    //             // Format timeline untuk frontend (approvers list)
    //             $approversList = $approverRows->map(function($row) {
    //                 $statusText = 'Menunggu';
    //                 if ($row->Flag_Approval == 'Y') $statusText = 'Disetujui';
    //                 if ($row->Flag_Approval == 'T') $statusText = 'Ditolak';

    //                 return [
    //                     'approver_id'   => Crypt::encryptString($row->Current_Approver_Kode),
    //                     'approver_name' => $row->Nama_Approver ?? $row->Current_Approver_Kode,
    //                     'approval_status' => $row->Flag_Approval ?? 'N',
    //                     'status_text'   => $statusText,
    //                     'order_flow'    => $row->Request_Order_Flow,
    //                     'date'          => $row->Tanggal_Approved
    //                 ];
    //             });

    //             // Tentukan Status Text Global
    //             $statusGlobal = 'pending';
    //             if ($item->Flag_Approval == 'Y') $statusGlobal = 'approved';
    //             if ($item->Flag_Approval == 'T') $statusGlobal = 'rejected';

    //             // Generate Temporary URL untuk Lampiran
    //             $fileUrl = null;
    //             if ($item->File_Path) {
    //                 try {
    //                     $fileUrl = Storage::disk('gcs')->temporaryUrl($item->File_Path, now()->addMinutes(15));
    //                 } catch (\Exception $e) {
    //                     // Silent error log
    //                 }
    //             }

    //             // Format Tanggal Cuti
    //             $tglDet = TanggalTransaksiIzin::ringkas($item->No_Transaksi)['tanggal'];
    //             // dd($tglDet);

    //             // Jika Cuti, jumlah hari dihitung atau ambil dari kolom Durasi_Angka di View
    //             $jumlahHari = TanggalTransaksiIzin::ringkas($item->No_Transaksi)['jumlahHari'];

    //             return [
    //                 'No_Transaksi'   => $item->No_Transaksi,
    //                 ''
    //                 'jenis_cuti'     => $item->Sub_Tipe,
    //                 'tanggal_mulai'  => $item->Tanggal_Mulai,
    //                 'tanggal_selesai'=> $item->Tanggal_Selesai,
    //                 'jumlah_hari'    => $jumlahHari,
    //                 'tanggal_cuti'   => $tglDet,
    //                 'alasan'         => $item->Deskripsi,
    //                 'created_at'     => $item->Created_At,
    //                 'filePath'       => $item->File_Path,
    //                 'file_url'       => $fileUrl,
    //                 'deskripsi_cuti' => $item->Deskripsi, // Atau ambil dari join jenis cuti jika ada

    //                 'currentFlow'    => $approverRows->where('Flag_Approval', '!=', null)->max('Request_Order_Flow') ?? 0,
    //                 'maxFlow'        => $approverRows->max('Request_Order_Flow') ?? 0,

    //                 'status'         => $statusGlobal,
    //                 'approvers'      => $approversList
    //             ];
    //         });

    //         return ResponseHelper::successWithPaginationV2(
    //             $formattedData,
    //             $page,
    //             $limit,
    //             $total,
    //             "Riwayat cuti berhasil diambil.",
    //             200
    //         );

    //     } catch (\Exception $e) {
    //         Log::channel('cutiController')->error('Gagal mengambil riwayat cuti: ' . $e->getMessage() . ' on line ' . $e->getLine());
    //         return ResponseHelper::error('Gagal mengambil riwayat cuti.', 500);
    //     }
    // }

    public function getRiwayat(Request $request)
    {
        // 1. Validasi Input (Disamakan style dengan getData2)
        $validated = $request->validate([
            'search_query' => ['nullable', 'string', 'max:100'], // Di getData2 'search', disini 'search_query', sesuaikan jika perlu
            'status_filter' => ['nullable', 'string', 'max:100'],
            'tanggalFilter' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $pengguna = Auth::user()->karyawan;

            $limit = $validated['limit'] ?? 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $limit;

            $search = strip_tags($request->input('search_query') ?? '');
            $status = $request->input('status_filter');
            $tanggal = $request->input('tanggalFilter');
            $batasApproveCuti = config('services.hcis.batas_approve_cuti');

            // Kolom untuk pencarian
            $searchColoms = ['Deskripsi', 'Sub_Tipe', 'No_Transaksi'];

            // 2. QUERY BUILDER UTAMA
            // Tetap filter berdasarkan Kode_Karyawan (Milik Saya/Requester)
            $dataQuery = DB::table('N_HRIS_VW_All_Transactions as t')
                ->where('t.Kode_Karyawan_Requester', $pengguna->Kode_Karyawan)
                ->where('t.Tipe_Transaksi', 'CUTI');

            // 3. FILTER SEARCH
            if ($search) {
                $searchParam = '%'.$search.'%';
                $dataQuery->where(function ($query) use ($searchColoms, $searchParam) {
                    foreach ($searchColoms as $col) {
                        // Perhatikan 't.' karena di query builder kita aliaskan table
                        $query->orWhere('t.'.$col, 'like', $searchParam);
                    }
                });
            }
            // dd($dataQuery->get());

            // // 4. FILTER STATUS
            // if ($status) {
            //     if ($status == 'approved') {
            //         $dataQuery->where('t.Flag_Approval', 'Y');
            //     } elseif ($status == 'rejected') {
            //         $dataQuery->where('t.Flag_Approval', 'T');
            //     } elseif ($status == 'pending') {
            //         $dataQuery->whereNull('t.Flag_Approval');
            //     } elseif ($status == 'expired') {
            //         if(!$batasApproveCuti){
            //             $dataQuery->whereNull('t.Flag_Approval');
            //         }else{

            //             $dataQuery->whereNull('t.Flag_Approval')
            //             ->where(DB::raw('DATEDIFF(DAY,t.Created_At, GETDATE())'), '>', 3 );
            //         }
            //     }
            // }

            // 4. FILTER STATUS
            if (! empty($status)) {
                if ($status === 'approved') {
                    $dataQuery->where('t.Flag_Approval', 'Y');
                } elseif ($status === 'rejected') {
                    $dataQuery->where('t.Flag_Approval', 'T');
                } elseif ($status === 'pending') {
                    $dataQuery->whereNull('t.Flag_Approval');
                } elseif ($status === 'expired') {
                    // expired = pending + lewat batas approve cuti
                    if ($batasApproveCuti > 0) {
                        $dataQuery
                            ->whereNull('t.Flag_Approval')
                            ->whereRaw('DATEDIFF(DAY, t.Created_At, GETDATE()) > ?', [$batasApproveCuti]);
                    } else {
                        // fallback kalau config bermasalah
                        $dataQuery->whereNull('t.Flag_Approval');
                    }
                }
            }

            // 5. FILTER TANGGAL
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

            // 6. Pagination Execution
            $total = $dataQuery->count();

            $paginatedData = $dataQuery->orderBy('t.Created_At', 'DESC')->offset($offset)->limit($limit);

            $items = collect($paginatedData->get());

            // Jika kosong langsung return
            if ($items->isEmpty()) {
                $final_page = new LengthAwarePaginator([], $total, $limit, $page, [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]);

                return ResponseHelper::success($final_page, 'Riwayat cuti berhasil diambil.', 200);
            }

            // 7. AMBIL DATA APPROVAL (Eager Loading)
            $transactionIds = $items->pluck('No_Transaksi')->toArray();
            // dd($transactionIds);

            $allApprovals = DB::table('N_HRIS_VW_Approval_Request as ar')
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
                    'ar.*',
                    'rf.Role_Name',
                    'rf.Role_Code',
                    'ja.Nama_Approval',
                    'k.Nama as Nama_Approver',
                    'req.Catatan as Catatan_Approval',
                    DB::raw("COALESCE(k.Nama, rf.Role_Name, ja.Nama_Approval, 'Unknown') AS approver_name"),
                )
                ->orderBy('ar.Request_Order_Flow', 'ASC')
                ->get()
                ->groupBy('No_Transaksi');
            // dd($allApprovals);

            // $batasApproveCuti = config('services.hcis.batas_approve_cuti');
            //     }

            //     $angkaPengajuanHari = Carbon::parse($tanggal)
            //         ->diffInDays(Carbon::now(), false);

            //     if ($flag === 'Y') {
            //         return 'Y';
            //     }

            //     if (empty($flag) && $angkaPengajuanHari > $batasApproveCuti) {
            //         return 'P'; // Pending / Expired approval
            //     }

            //     if (!empty($flag)) {
            //         return $flag;
            //     }

            //     return 'N';
            // };

            // 8. MAPPING DATA (Format disamakan dengan getData2)
            $formattedData = $items->map(function ($item) use ($allApprovals) {
                // Ambil tanggal detail (Helper function)
                $tanggalDet = TanggalTransaksiIzin::ringkas($item->No_Transaksi);

                // Logic Approvers
                $approverRows = $allApprovals->get($item->No_Transaksi, collect());

                /*
                 * Transaksi yang belum punya satu pun baris approval TETAP
                 * ditampilkan.
                 *
                 * Sebelumnya baris seperti itu di-`return null` dan dibuang di
                 * klien, padahal `$total` sudah dihitung dari kueri MENTAH di
                 * atas. Akibatnya paginasi berbohong: header menyebut 15 hasil
                 * dan "Halaman 1 dari 2", halaman 1 hanya memunculkan 5 baris,
                 * dan halaman 2 kosong sama sekali sehingga yang tampil justru
                 * ilustrasi "Tidak Ada Data" — yang gambarnya bertuliskan 404
                 * dan gampang disalahartikan sebagai galat HTTP.
                 *
                 * Menyembunyikannya juga tidak jujur: transaksinya nyata, milik
                 * pengguna itu, dan ikut terhitung di widget Pengajuan Pending.
                 * Yang belum ada hanya jejak approval-nya, dan itu ditampilkan
                 * apa adanya sebagai kosong.
                 */
                $currentFlow = $approverRows->where('Flag_Approval', null)->sortBy('Request_Order_Flow')->first();
                // dd($allApprovals);

                // Jika semua sudah approve/reject (tidak ada yang null), ambil yang terakhir
                if (! $currentFlow) {
                    $currentFlow = $approverRows->last();
                }

                // Previous flow logic
                $previousApprover = $approverRows
                    ->where('Request_Order_Flow', ($currentFlow?->Request_Order_Flow ?? 0) - 1)
                    ->first();

                $maxFlow = $approverRows->max('Request_Order_Flow');

                // Generate String STATUSLIST
                $approverPlaceholder = $approverRows
                    ->map(function ($row) {
                        // Gunakan nama jika ada, jika tidak kode
                        $name = $row->approver_name ?? ($row->Nama_Approver ?? $row->Current_Approver_Kode);

                        return "{$name} : ".
                            ($this->parseStatusPengjuan($row->Flag_Approval, $row->Tanggal_Request) ?? 'N');
                    })
                    ->implode(', ');

                // Generate URL File (Optional: Tetap disertakan agar frontend bisa buka file)
                $fileUrl = null;
                if ($item->File_Path) {
                    try {
                        $fileUrl = Storage::disk('gcs')->temporaryUrl($item->File_Path, now()->addMinutes(15));
                    } catch (\Exception $e) {
                    }
                }

                // RETURN ARRAY STRICTLY MATCHING getData2 KEYS
                return [
                    'tanggal_create' => $item->Created_At,
                    'No_Transaksi' => $item->No_Transaksi,
                    // Approver yang sedang pegang bola
                    'Approver_Kode_Karyawan' => Crypt::encryptString($currentFlow?->Current_Approver_Kode ?? ''),
                    'requester_Nama' => $item->Nama_Requester ?? 'Saya', // Karena ini riwayat saya
                    'Requester_Kode_Karyawan' => Crypt::encryptString($item->Kode_Karyawan_Requester),

                    // Menggunakan helper yang sama dengan getData2
                    'Tipe_Izin2' => method_exists($this, 'parseSubTipe')
                        ? $this->parseSubTipe($item->Sub_Tipe)
                        : $item->Sub_Tipe,
                    'Tipe_Izin' => $item->Sub_Tipe,
                    'Alasan' => $item->Deskripsi,
                    'TanggalIzin' => $item->Tanggal_Mulai,
                    'Tanggal_Selesai' => $item->Tanggal_Selesai,

                    // Di getData2 'Lampiran' isinya path, kita bisa kasih path atau URL
                    'Lampiran' => $item->File_Path ?? null,
                    'Lampiran_Url' => $fileUrl, // Extra field agar tetap bisa dibuka (opsional)

                    'MaxFlow' => $maxFlow ?? 0,
                    'currentFlow' => $currentFlow?->Request_Order_Flow ?? 0,

                    // Menggunakan helper parseStatusRequest jika ada
                    'status' => method_exists($this, 'parseIzin')
                        ? $this->parseIzin($this->parseStatusPengjuan($item->Flag_Approval, $item->Created_At))
                        : $this->parseStatusPengjuan($item->Flag_Approval, $item->Created_At),

                    'Approver' => $currentFlow?->approver_name
                        ?? $currentFlow?->Nama_Approver
                        ?? $currentFlow?->Current_Approver_Kode,
                    'Status_Approval_Saat_Ini' => $currentFlow
                        ? $this->parseIzin(
                            $this->parseStatusPengjuan($currentFlow->Flag_Approval, $currentFlow->Tanggal_Request),
                        )
                        : null,
                    'Jam_Mulai' => $item->Jam_Diajukan ?? null, // Sesuaikan kolom DB
                    'Status_Approval_Langkah_Sebelumnya' => $previousApprover && $previousApprover->Flag_Approval
                            ? $this->parseIzin($previousApprover->Flag_Approval)
                            : null,
                    'order_flow' => $currentFlow?->Request_Order_Flow ?? 0,
                    'STATUSLIST' => $approverPlaceholder,
                    'tanggalReal' => $tanggalDet,
                ];
            });

            // 9. Return menggunakan LengthAwarePaginator (Sama seperti getData2)
            // $final_page = new LengthAwarePaginator(
            //     $formattedData,
            //     $total,
            //     $limit,
            //     $page,
            //     [
            //         'path' => request()->url(),
            //         'query' => request()->query(),
            //     ]
            // );

            return ResponseHelper::successWithPaginationV2(
                $formattedData,
                $page,
                $limit,
                $total,
                'Riwayat cuti berhasil diambil.',
                200,
            );

            // return ResponseHelper::success($final_page, "Riwayat cuti berhasil diambil.", 200);
        } catch (\Exception $e) {
            Log::channel('cutiController')->error(
                'Gagal mengambil riwayat cuti: '.$e->getMessage().' on line '.$e->getLine(),
            );

            return ResponseHelper::error('Gagal mengambil riwayat cuti.', 500);
        }
    }

    public function getDetailRiwayat($no_transaksi)
    {
        try {
            $pengguna = Auth::user()->karyawan;

            $item = DB::table('N_HRIS_VW_All_Transactions as t')
                ->where('t.No_Transaksi', $no_transaksi)
                ->where('t.Kode_Karyawan_Requester', $pengguna->Kode_Karyawan)
                ->where('t.Tipe_Transaksi', 'CUTI')
                ->first();

            if (! $item) {
                return ResponseHelper::error('Data transaksi tidak ditemukan atau Anda tidak memiliki akses.', 404);
            }

            // 3. AMBIL DATA APPROVAL (Khusus untuk No_Transaksi ini saja)
            $approverRows = DB::table('N_HRIS_VW_Approval_Request as ar')
                ->leftJoin('HRIS_Approval_Request as req', 'req.id', '=', 'ar.Id_Approval_Request')
                ->leftJoin('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'req.Id_Jenis_Approval')
                ->leftJoin('N_HRIS_Role_Functional as rf', function ($join) {
                    $join
                        ->on('rf.Id_Role', '=', 'req.Group_Reference_Id')
                        ->on('rf.Role_Code', '=', 'ja.Source_Reference');
                })
                ->leftJoin('Karyawan as k', 'ar.Current_Approver_Kode', '=', 'k.Kode_Karyawan')
                ->where('ar.No_Transaksi', $item->No_Transaksi)
                ->select(
                    'ar.*',
                    'rf.Role_Name',
                    'rf.Role_Code',
                    'ja.Nama_Approval',
                    'k.Nama as Nama_Approver',
                    'req.Catatan as Catatan_Approval',
                    DB::raw("COALESCE(k.Nama, rf.Role_Name, ja.Nama_Approval, 'Unknown') AS approver_name"),
                )
                ->orderBy('ar.Request_Order_Flow', 'ASC')
                ->get();

            $approversMap = $approverRows->map(function ($e) {
                $e->Flag_Approval = $this->parseStatusPengjuan($e->Flag_Approval, $e->Tanggal_Request);

                return $e;
            });

            // dd($approversMap);

            // 4. LOGIC PENENTUAN POSISI FLOW (Sama seperti sebelumnya tapi tanpa grouping)

            // Cari Flow Saat Ini (Approval yang sedang pending/null, urutkan dari flow terkecil)
            $currentFlow = $approverRows->where('Flag_Approval', null)->sortBy('Request_Order_Flow')->first();

            // Jika semua sudah approve/reject (tidak ada yang null), ambil yang terakhir
            if (! $currentFlow) {
                $currentFlow = $approverRows->last();
            }

            // Previous flow logic
            $previousApprover = $approverRows
                ->where('Request_Order_Flow', ($currentFlow->Request_Order_Flow ?? 0) - 1)
                ->first();

            $maxFlow = $approverRows->max('Request_Order_Flow');

            // Generate String STATUSLIST
            $approverPlaceholder = $approverRows
                ->map(function ($row) {
                    $name = $row->Nama_Approver ?? $row->Current_Approver_Kode;

                    // return "{$name} : " . ($row->Flag_Approval ?? 'N');
                    return "{$name} : ".$this->parseStatusPengjuan($row->Flag_Approval, $row->Tanggal_Request);
                })
                ->implode(', ');

            // 5. AMBIL TANGGAL DETAIL (Helper)
            $tanggalDet = TanggalTransaksiIzin::ringkas($item->No_Transaksi);

            // 6. GENERATE URL FILE (GCS)
            $fileUrl = null;
            if ($item->File_Path) {
                try {
                    $fileUrl = Storage::disk('gcs')->temporaryUrl($item->File_Path, now()->addMinutes(15));
                } catch (\Exception $e) {
                    // Log error jika perlu, atau biarkan null
                }
            }

            // 7. MAPPING DATA FINAL (Single Object)
            $formattedData = [
                'tanggal_create' => $item->Created_At,
                'No_Transaksi' => $item->No_Transaksi,
                'Approver_Kode_Karyawan' => Crypt::encryptString($currentFlow->Current_Approver_Kode ?? ''),
                'requester_Nama' => $item->Nama_Requester ?? 'Saya',
                'Requester_Kode_Karyawan' => Crypt::encryptString($item->Kode_Karyawan_Requester),

                // Helper parsing
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

                'status' => method_exists($this, 'parseIzin')
                    ? $this->parseIzin($this->parseStatusPengjuan($item->Flag_Approval, $item->Created_At))
                    : $this->parseStatusPengjuan($item->Flag_Approval, $item->Created_At),

                'Approver' => $currentFlow->Nama_Approver ?? ($currentFlow->Current_Approver_Kode ?? null),
                'Status_Approval_Saat_Ini' => $this->parseIzin(
                    $this->parseStatusPengjuan($currentFlow->Flag_Approval, $currentFlow->Tanggal_Request),
                ) ?? null,
                'Jam_Mulai' => $item->Jam_Diajukan ?? null,
                'Status_Approval_Langkah_Sebelumnya' => $previousApprover && $previousApprover->Flag_Approval
                        ? $this->parseIzin($previousApprover->Flag_Approval)
                        : null,
                'order_flow' => $currentFlow->Request_Order_Flow ?? 0,
                'STATUSLIST' => $approverPlaceholder,
                'tanggalReal' => $tanggalDet,
                'approvers' => $approverRows,
            ];

            // 8. RETURN SUCCESS (Single Data)
            return ResponseHelper::success($formattedData, 'Detail riwayat cuti berhasil diambil.', 200);
        } catch (\Exception $e) {
            Log::channel('cutiController')->error(
                'Gagal mengambil detail riwayat cuti: '.$e->getMessage().' on line '.$e->getLine(),
            );

            return ResponseHelper::error('Gagal mengambil detail riwayat cuti.', 500);
        }
    }

    private function parseStatusPengjuan($flag, $tanggal)
    {
        $batasApproveCuti = config('services.hcis.batas_approve_cuti');
        if ($batasApproveCuti == null || $tanggal == null) {
            return $flag;
        }
        // // Kalau config tidak diset, jangan override apa pun
        // if ($batasApproveCuti == null) {
        //     return $flag ?? 'N';
        // }

        $angkaPengajuanHari = Carbon::parse($tanggal)->diffInDays(Carbon::now(), false);
        // dd($angkaPengajuanHari, $batasApproveCuti);
        if ($flag === 'Y') {
            return 'Y';
        }

        if (! empty($flag)) {
            return $flag;
        }

        // dd($angkaPengajuanHari, $batasApproveCuti);
        if (empty($flag) && $angkaPengajuanHari > $batasApproveCuti) {
            return 'P'; // Pending / Expired approval
        }

        return 'N';
    }

    private function parseIzin($status)
    {
        if ($status == 'Y') {
            return 'approved';
        } elseif ($status == 'T') {
            return 'rejected';
        } elseif ($status == 'P') {
            return 'expired';
        }

        return 'pending';
    }
    // public function store(Request $request)
    // {
    //     // dd($request->all());
    //     DB::beginTransaction();
    //     try {
    //         $pengguna = Auth::user()->karyawan;
    //         $validated = $request->validate([
    //             'jenis_cuti_id' => 'required',
    //             'selected_dates'     => ['required', 'array'],
    //             // 'dateRange'     => ['required', 'array', 'size:2'],
    //             // 'dateRange.0'   => ['required', 'date_format:Y-m-d'],
    //             // 'dateRange.1'   => ['required', 'date_format:Y-m-d', 'after_or_equal:dateRange.0'],
    //             'jumlah_hari' => 'required|integer|min:1',
    //             'alasan' => 'required|string|max:1000',
    //             'file_pendukung' => 'nullable|file|max:2048|mimes:pdf,jpg,jpeg,png'
    //         ]);
    //         // dd($validated['file_pendukung']);
    //         $Id_Jenis = Hashids::connection('custom')->decode($validated['jenis_cuti_id'])[0];
    //         $Jenis_Cuti = DB::table('N_HRIS_Jenis_Cuti')
    //             ->where('Id_Jenis_Cuti', $Id_Jenis)
    //             ->where('Flag_Aktif', 'Y')->first();

    //         if (!$Jenis_Cuti) {
    //             return ResponseHelper::error('Jenis cuti yang dipilih tidak valid.', 400);
    //         }

    //         // Cek Saldo Cuti
    //         if ($Jenis_Cuti->Nama_Jenis_Cuti == 'Tahunan') {
    //             $sisaCuti = $pengguna->sisa_cuti();
    //             if ($sisaCuti < $validated['jumlah_hari']) {
    //                 return ResponseHelper::error('Saldo cuti tahunan tidak mencukupi.', 400);
    //             }
    //         }

    //         // Cek Tumpang Tindih
    //         // range
    //         // $tanggalMulai = Carbon::parse($validated['dateRange'][0]);
    //         // $tanggalSelesai = Carbon::parse($validated['dateRange'][1]);
    //         // Multiple
    //         $tanggalMulai   = Carbon::parse($validated['selected_dates'][0]);
    //         $tanggalSelesai = Carbon::parse(end($validated['selected_dates']));
    //         $selectedDates = $validated['selected_dates'];

    //         // range
    //         // $tumpangTindih = DB::table('Transaksi_Cuti_Detail as a')
    //         //     ->join('Transaksi_Cuti as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //         //     ->where('Kode_Karyawan', $pengguna->Kode_Karyawan)
    //         //     ->whereNull('b.Status') // Hanya cek transaksi yang masih aktif/pending
    //         //     ->where(function ($q) {
    //         //         $q->whereNull('a.Flag_Approval')->orWhere('a.Flag_Approval', 'Y');
    //         //     })
    //         //     ->where(function ($query) use ($tanggalMulai, $tanggalSelesai) {
    //         //         $query->whereBetween('Tanggal_Cuti_Dari', [$tanggalMulai, $tanggalSelesai])
    //         //             ->orWhereBetween('Tanggal_Cuti_Sampai', [$tanggalMulai, $tanggalSelesai])
    //         //             ->orWhere(function ($q) use ($tanggalMulai, $tanggalSelesai) {
    //         //                 $q->where('Tanggal_Cuti_Dari', '<=', $tanggalMulai)
    //         //                   ->where('Tanggal_Cuti_Sampai', '>=', $tanggalSelesai);
    //         //             });
    //         //     })->exists();

    //         $tumpangTindih = DB::table('Transaksi_Cuti_Detail as a')
    //             ->join('Transaksi_Cuti as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('Kode_Karyawan', $pengguna->Kode_Karyawan)
    //             ->whereNull('b.Status') // transaksi aktif / pending
    //             ->where(function ($q) {
    //                 $q->whereNull('a.Flag_Approval')
    //                 ->orWhere('a.Flag_Approval', 'Y');
    //             })
    //             ->where(function ($query) use ($selectedDates) {

    //                 foreach ($selectedDates as $tgl) {
    //                     $query->orWhere(function ($q) use ($tgl) {
    //                         $q->where('Tanggal_Cuti_Dari', '<=', $tgl)
    //                         ->where('Tanggal_Cuti_Sampai', '>=', $tgl);
    //                     });
    //                 }

    //             })
    //             ->exists();

    //         if ($tumpangTindih) {
    //             return ResponseHelper::error('Anda sudah memiliki pengajuan cuti pada rentang tanggal tersebut.', 400);
    //         }

    //         // --- START: LOGIKA APPROVAL FLOW DINAMIS ---
    //         $approverFlows = DB::table('N_HRIS_Cuti_Approval as ca')
    //             ->join('N_HRIS_Jenis_Approval as ja', 'ca.Id_Jenis_Approval', '=', 'ja.Id_Jenis_Approval')
    //             ->where('ca.Id_Jenis_Cuti', $Id_Jenis)
    //             ->where('ca.Flag_Aktif', 'Y') // Pastikan Cuti_Approval aktif
    //             ->where('ja.Flag_Aktif', 'Y') // Pastikan Jenis_Approval juga aktif
    //             ->orderBy('ca.Order_Flow', 'asc')
    //             ->get();

    //         if ($approverFlows->isEmpty() && $Jenis_Cuti->Perlu_Persetujuan_Khusus === 'Y') {
    //             return ResponseHelper::error('Jenis cuti ini belum memiliki alur persetujuan. Harap hubungi admin.', 400);
    //         }

    //         $finalApprovers = collect();
    //         foreach ($approverFlows as $flow) {
    //             if ($flow->Source_Type === 'GROUP_TABLE' && !empty($flow->Source_Reference)) {
    //                 // Mode PARALLEL untuk HC Approval
    //                 $table = $flow->Source_Reference;
    //                 if (!Schema::hasTable($table)) {
    //                     DB::rollBack();
    //                     Log::channel('cutiController')->error('Table refensi tidak ditemukan.' . $table);
    //                     return ResponseHelper::error('Table refensi tidak ditemukan.' . $table, 404);
    //                 }
    //                 $groupApprover = new \stdClass();
    //                 $groupApprover->Flag_Group = 'Y';

    //                 $groupApprover->id_jenis_approval = $flow->Id_Jenis_Approval;
    //                 $groupApprover->order_flow = $flow->Order_Flow;
    //                 $groupApprover->group_reference_id = 100;
    //                 $finalApprovers->push($groupApprover);
    //             } else if ($flow->Source_Type === 'TABLE' && !empty($flow->Source_Reference)) {
    //                  // Mode SERIAL untuk tabel flow lainnya
    //                 $table = $flow->Source_Reference;
    //                 if (!Schema::hasTable($table)) {
    //                     DB::rollBack();
    //                     Log::channel('cutiController')->error('Table refensi tidak ditemukan.' . $table);
    //                     return ResponseHelper::error('Table refensi tidak ditemukan.' . $table, 404);
    //                 }
    //                  $approversFromTable = DB::table($flow->Source_Reference . ' as source_table')
    //                      ->join('Karyawan as k', 'source_table.Kode_Karyawan', '=', 'k.Kode_Karyawan')
    //                      ->where('Kode_Karyawan_Requester', $pengguna->Kode_Karyawan)
    //                      ->whereNull('source_table.Status') // Status di tabel flow harus NULL
    //                      ->where('k.Aktif', 'Y') // Karyawan approver harus aktif
    //                      ->orderBy('order_flow', 'asc')
    //                      ->get();

    //                  foreach ($approversFromTable as $approver) {
    //                      $approver->id_jenis_approval = $flow->Id_Jenis_Approval;
    //                      $approver->Approval_Flow_Id = $approver->id;
    //                      $finalApprovers->push($approver);
    //                  }
    //             } elseif ($flow->Source_Type === 'DIRECT_EMPLOYEE') {
    //                 $directApproverData = DB::table('Karyawan')
    //                     ->where('Kode_Karyawan', $flow->Source_Reference)
    //                     ->where('Aktif', 'Y') // Karyawan approver harus aktif
    //                     ->first();
    //                 if ($directApproverData) {
    //                     $directApprover = (object) ['Kode_Karyawan' => $directApproverData->Kode_Karyawan, 'order_flow' => $flow->Order_Flow, 'id_jenis_approval' => $flow->Id_Jenis_Approval ,  'Approval_Flow_Id' => null];
    //                     $finalApprovers->push($directApprover);
    //                 }
    //             }
    //         }

    //         if ($finalApprovers->isEmpty() && $Jenis_Cuti->Perlu_Persetujuan_Khusus === 'Y') {
    //             return ResponseHelper::error('Tidak ditemukan approver untuk Anda pada alur persetujuan ini.', 400);
    //         }

    //         // dd($finalApprovers);
    //         // --- END: LOGIKA APPROVAL FLOW DINAMIS ---

    //         // Buat No Transaksi
    //         $formatTanggalSekarang = Carbon::now()->format('m-y');
    //         $prefix = "CT" . $formatTanggalSekarang . "-";
    //         $checkLastFaktur = DB::table('Transaksi_Cuti')->where('No_Transaksi', 'like', $prefix . '%')->orderByDesc('No_Transaksi')->first();
    //         $angkaTerakhir = $checkLastFaktur ? (int)substr($checkLastFaktur->No_Transaksi, -4) + 1 : 1;
    //         $noFaktur = $prefix . sprintf('%04d', $angkaTerakhir);

    //         // Handle File Upload
    //         $file = null;
    //         $fileName = null;
    //         $folderPath = null;
    //         $filePath = null;
    //         if ($validated['file_pendukung']) {
    //             $file = $validated['file_pendukung'];
    //             $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
    //             $folderPath = 'izinSakitCuti/' . date('Y/m');
    //             $filePath = $file->storeAs($folderPath, $fileName, 'public');
    //         }
    //         if($filePath !== null){
    //             Storage::disk('gcs')->putFileAs($folderPath, $file, $fileName);
    //         }

    //         // dd($file);
    //         // Insert ke Transaksi_Cuti
    //         DB::table('Transaksi_Cuti')->insert([
    //             'Kode_Perusahaan' => '001',
    //             'No_Transaksi' => $noFaktur,
    //             'Tanggal' => now(),
    //             'Jam' => now()->format('H:i:s'),
    //             'Status' => null,
    //             'UserID' => $pengguna->UserID_Web,
    //         ]);

    //         // Insert ke Transaksi_Cuti_Detail dan dapatkan ID-nya
    //         $detailId = DB::table('Transaksi_Cuti_Detail')->insertGetId([
    //             'Kode_Perusahaan' => '001',
    //             'No_Transaksi' => $noFaktur,
    //             'Kode_Karyawan' => $pengguna->Kode_Karyawan,
    //             'Jenis' => $Jenis_Cuti->Nama_Jenis_Cuti,
    //             'Id_Jenis_Cuti' => $Jenis_Cuti->Id_Jenis_Cuti,
    //             'Tanggal_Cuti_Dari' => $tanggalMulai,
    //             'Tanggal_Cuti_Sampai' => $tanggalSelesai,
    //             'Alasan' => $validated['alasan'],
    //             'filePath' => $filePath != "" ? $filePath : null,
    //         ]);

    //         // range
    //         // Insert ke Transaksi_Cuti_Det
    //         // $currentDate = $tanggalMulai->copy();
    //         // while ($currentDate->lte($tanggalSelesai)) {
    //         //     DB::table('Transaksi_Cuti_Det')->insert([
    //         //         'Kode_Perusahaan' => '001',
    //         //         'No_Transaksi' => $noFaktur,
    //         //         'Kode_Karyawan' => $pengguna->Kode_Karyawan,
    //         //         'Urut_Detail' => $detailId,
    //         //         'Tanggal_Cuti' => $currentDate->toDateString(),
    //         //     ]);
    //         //     $currentDate->addDay();
    //         // }

    //         // multiple
    //         foreach ($selectedDates as $date) {
    //             DB::table('Transaksi_Cuti_Det')->insert([
    //                 'Kode_Perusahaan' => '001',
    //                 'No_Transaksi' => $noFaktur,
    //                 'Kode_Karyawan' => $pengguna->Kode_Karyawan,
    //                 'Urut_Detail' => $detailId,
    //                 'Tanggal_Cuti' => Carbon::parse($date)->toDateString(),
    //             ]);
    //         }

    //         // Insert ke HRIS_Approval_Request jika perlu persetujuan
    //         if ($Jenis_Cuti->Perlu_Persetujuan_Khusus === 'Y') {
    //             foreach ($finalApprovers as $approver) {
    //                 if (isset($approver->Flag_Group) && $approver->Flag_Group === 'Y') {
    //                     // Insert untuk mode PARALLEL (HC Approval)
    //                     DB::table('HRIS_Approval_Request')->insert([
    //                         'No_Transaksi' => $noFaktur,
    //                         'Kode_Karyawan' => $pengguna->Kode_Karyawan,
    //                         'Id_Jenis_Approval' => $approver->id_jenis_approval,
    //                         'Request_Order_Flow' => $approver->order_flow,
    //                         'Flag_Group' => 'Y',
    //                         'Group_Reference_Id' => $approver->group_reference_id, // Menyimpan Group_Id
    //                         'Approver_Karyawan_Kode' => null,
    //                         'Flow_Id' => null,
    //                         'created_at' => now(),
    //                         'update_at' => now()
    //                     ]);
    //                 } else {
    //                     // Insert untuk mode SERIAL (Flow biasa)
    //                     DB::table('HRIS_Approval_Request')->insert([
    //                         'No_Transaksi' => $noFaktur,
    //                         'Kode_Karyawan' => $pengguna->Kode_Karyawan,
    //                         'Id_Jenis_Approval' => $approver->id_jenis_approval,
    //                         'Request_Order_Flow' => $approver->order_flow,
    //                         'Approver_Karyawan_Kode' => $approver->Kode_Karyawan,
    //                         'Flow_Id' => $approver->Approval_Flow_Id,
    //                         'created_at' => now(),
    //                         'update_at' => now()
    //                     ]);
    //                 }
    //             }
    //             // Kirim Notifikasi WA ke approver pertama
    //             $firstApprover = $finalApprovers->sortBy('order_flow')->first();
    //             // dd($finalApprovers);
    //             if ($firstApprover && property_exists($firstApprover, 'Flag_Group') && $firstApprover->Flag_Group !== 'Y') {
    //                 $approverKaryawan = Karyawan::where('Kode_Karyawan', $firstApprover->Kode_Karyawan)->first();
    //                 if ($approverKaryawan && $approverKaryawan->HP) {
    //                         $pesan = [
    //                             "messaging_product" => "whatsapp",
    //                             "to" => $approverKaryawan->HP,
    //                             "type" => "template",
    //                             "template" => [
    //                                 "name" => "notif_approver_hcis",
    //                                 "language" => [
    //                                     "code" => "id",
    //                                     "policy" => "deterministic"
    //                                 ],
    //                                 "components" => [
    //                                     [
    //                                         "type" => "body",
    //                                         "parameters" => [
    //                                             ["type" => "text", "text" => $approverKaryawan->Nama],
    //                                             ["type" => "text", "text" => $noFaktur],
    //                                             ["type" => "text", "text" => $Jenis_Cuti->Nama_Jenis_Cuti],
    //                                             ["type" => "text", "text" => FormatTanggalHelper::convertDoubleDate($tanggalMulai, $tanggalSelesai)],
    //                                             ["type" => "text", "text" => $pengguna->karyawan->Nama]
    //                                         ]
    //                                     ]
    //                                 ]
    //                             ]
    //                         ];

    //                         $waResult = WhatsappLogHelper::sendAndLog(
    //                             $approverKaryawan->Kode_Karyawan,
    //                             $approverKaryawan->Nama,
    //                             $approverKaryawan->HP,
    //                             $noFaktur,
    //                             $pesan,
    //                             function ($response) use ($pengguna,$noFaktur, $approverKaryawan) {
    //                                 DB::table('HRIS_Approval_Request as a')
    //                                 ->where("a.No_Transaksi", $noFaktur)
    //                                 ->where("a.Kode_Karyawan_Approver", $approverKaryawan->Kode_Karyawan)
    //                                 ->update(['a.angka_wa' => DB::raw('COALESCE(angka_wa, 0) + 1')]);

    //                                 Log::channel('cutiController')->info("WA sukses dikirim ke approver {$approverKaryawan->Kode_Karyawan}", [
    //                                     'requester' => $pengguna->Kode_Karyawan,
    //                                     'response' => $response
    //                                 ]);
    //                             },
    //                             function ($reason, $response) use ($approverKaryawan, $noFaktur, $pesan) {
    //                                 Log::channel('cutiController')->warning("Gagal kirim WA ke approver {$approverKaryawan->Kode_Karyawan}", [
    //                                     'reason' => $reason,
    //                                     'response' => $response
    //                                 ]);

    //                                 DB::table('Whatsapp_Log')->insert([
    //                                 'Kode_Karyawan_Destination' => $approverKaryawan->Kode_Karyawan,
    //                                 'Nama'          => $approverKaryawan->Nama,
    //                                 'HP'            => $approverKaryawan->HP,
    //                                 'No_Transaksi'  => $noFaktur,
    //                                 'Error_Message' => is_array($response)
    //                                     ? json_encode($response, JSON_UNESCAPED_UNICODE)
    //                                     : ($reason ?? 'Unknown error'),
    //                                 'RawMessage'    => print_r($pesan, true),
    //                                 'RawMessageJson'=> json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    //                                 'Created_At'    => now(),
    //                                 'Is_Retried'    => 0,
    //                             ]);

    //                             }
    //                         );
    //                 }
    //             }
    //         }

    //         DB::commit();

    //         return ResponseHelper::success(null, 'Pengajuan cuti berhasil dikirim.');

    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         DB::rollBack();
    //         return ResponseHelper::error($e->validator->errors()->first(), 422);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::channel('cutiController')->error('Gagal menyimpan pengajuan cuti: ' . $e->getMessage() . ' on line ' . $e->getLine());
    //         return ResponseHelper::error('Terjadi kesalahan server saat mengajukan cuti.', 500);
    //     }
    // }

    private function deleteUploadedCutiFile(?string $path): void
    {
        if (! $path) {
            return;
        }
        try {
            Storage::disk('gcs')->delete($path);
        } catch (\Throwable $e) {
            Log::channel('cutiController')->warning('Gagal hapus berkas cuti orphan: '.$path.' — '.$e->getMessage());
        }
    }

    public function store(Request $request)
    {
        // dd($request->all());
        // Berkas pendukung di-upload SEBELUM transaksi dibuka (lihat di bawah) agar
        // HTTP upload GCS tidak menahan lock DB. Transaksi hanya membungkus penulisan
        // data; $uploadedGcsPath dipakai untuk menghapus file bila transaksi gagal.
        $uploadedGcsPath = null;
        try {
            $pengguna = Auth::user()->karyawan;

            // 1. VALIDASI INPUT
            $validated = $request->validate([
                'jenis_cuti_id' => 'required',
                'selected_dates' => ['required', 'array'],
                'jumlah_hari' => 'required|integer|min:1',
                'alasan' => 'required|string|max:1000',
                'requested_date_from' => 'nullable|date',
                'requested_date_to' => 'nullable|date',
                'file_pendukung' => 'nullable|file|max:2048|mimes:pdf,jpg,jpeg,png',
            ]);

            $Id_Jenis = Hashids::connection('custom')->decode($validated['jenis_cuti_id'])[0];

            $Jenis_Cuti = DB::table('N_HRIS_Jenis_Cuti')
                ->where('Id_Jenis_Cuti', $Id_Jenis)
                ->where('Flag_Aktif', 'Y')
                ->first();

            if (! $Jenis_Cuti) {
                return ResponseHelper::error('Jenis cuti yang dipilih tidak valid.', 400);
            }

            $calendarGuard = $this->buildCalendarAuditForSubmission(
                $pengguna,
                $validated['selected_dates'],
                $validated['requested_date_from'] ?? null,
                $validated['requested_date_to'] ?? null,
                $Jenis_Cuti,
            );

            if (! empty($calendarGuard['error'])) {
                return ResponseHelper::error($calendarGuard['error'], 422);
            }

            $selectedDates = $calendarGuard['selected_dates'];
            $jumlahHariFinal = count($selectedDates);
            $calendarAuditJson = json_encode($calendarGuard['audit'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($jumlahHariFinal < 1) {
                return ResponseHelper::error('Pilih minimal satu tanggal cuti yang valid.', 400);
            }

            // 2. CEK SALDO & BATAS HUTANG CUTI (model HUTANG AKTIF)
            // Hutang baru dari pengajuan ini = kelebihan atas saldo positif yang tersisa
            // (setelah pengajuan pending lain yang dipotong lebih dulu). Konsisten dengan
            // cara approval (debitBalanceWithDebt) memakai dompet positif.
            if (($Jenis_Cuti->Memotong_Saldo_Cuti ?? 'T') === 'Y') {
                $b = $pengguna->hutangBreakdown();
                $availableForNew = max(0, $b['positive'] - $b['pending_days']);
                $projectedDebt = max(0, $jumlahHariFinal - $availableForNew);

                if ($projectedDebt > 0) {
                    $bisaHutang = ($Jenis_Cuti->Flag_Bisa_Hutang_Cuti ?? 'T') === 'Y';

                    if (! $bisaHutang) {
                        return ResponseHelper::error('Saldo cuti tidak mencukupi dan jenis cuti ini tidak boleh hutang cuti.', 400);
                    }

                    $maks = $Jenis_Cuti->Maks_Hutang_Cuti !== null ? (int) $Jenis_Cuti->Maks_Hutang_Cuti : null;
                    if (! empty($maks)) {
                        // sisaJatah = maks - hutang aktif berjalan - hutang dari pengajuan pending lain
                        $sisaJatah = $this->transactionService->getSisaJatahHutang(
                            $pengguna->Kode_Karyawan,
                            (int) $Jenis_Cuti->Id_Jenis_Cuti,
                            $maks,
                            $Jenis_Cuti->Hutang_Cuti_Mode ?: 'OUTSTANDING',
                            $Jenis_Cuti->Hutang_Cuti_Basis_Periode ?: 'KALENDER',
                            $pengguna->Tgl_Masuk,
                            $Jenis_Cuti->Hutang_Periodik_Perhitungan ?: 'GROSS',
                        );

                        if ($projectedDebt > $sisaJatah) {
                            return ResponseHelper::error(
                                'Melebihi batas hutang cuti. Perlu hutang '.(int) $projectedDebt.' hari, sisa jatah hutang '.(int) $sisaJatah.' hari.',
                                400,
                            );
                        }
                    }
                }
            }

            // 3. CEK TUMPANG TINDIH (OVERLAP)
            $tanggalMulai = Carbon::parse($selectedDates[0]);
            $tanggalSelesai = Carbon::parse(end($selectedDates));
            $batasHariPengajuan = config('services.hcis.batas_approve_izin');
            $batasApproveCuti = config('services.hcis.batas_approve_cuti');

            $tumpangTindih = DB::table('Transaksi_Cuti_Detail as a')
                ->join('Transaksi_Cuti as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->join('Transaksi_Cuti_Det as det', function ($join) {
                    $join->on('det.No_Transaksi', '=', 'a.No_Transaksi')->on('det.Urut_Detail', '=', 'a.Urut_Oto');
                })
                ->where('a.Kode_Karyawan', $pengguna->Kode_Karyawan)
                ->whereNull('b.Status') // Transaksi aktif
                ->where(function ($q) {
                    $q->whereNull('a.Flag_Approval')->orWhere('a.Flag_Approval', 'Y');
                })
                ->whereIn(DB::raw('CAST(det.Tanggal_Cuti AS DATE)'), $selectedDates)
                ->whereRaw(
                    "
                    DATEDIFF(
                        DAY,
                        TRY_CONVERT(
                            DATETIME,
                            CONCAT(
                                CONVERT(
                                    VARCHAR,
                                    CONVERT(DATE, b.Tanggal, 105),
                                    23
                                ),
                                ' ',
                                b.Jam
                            ),
                            120
                        ),
                        GETDATE()
                    ) < $batasHariPengajuan
                ",
                )
                ->exists();

            if ($tumpangTindih) {
                return ResponseHelper::error('Anda sudah memiliki pengajuan cuti pada tanggal tersebut.', 400);
            }

            // 4. INSERT HEADER & DETAIL TRANSAKSI
            // Generate No Transaksi
            $formatTanggalSekarang = Carbon::now()->format('m-y');
            $prefix = 'CT'.$formatTanggalSekarang.'-';
            // Nomor via COUNTER ATOMIK (HRIS_Counter): bebas race duplikat & tanpa
            // scan tabel. Dipanggil SEBELUM transaksi bisnis (lihat A4) sehingga
            // kunci baris counter lepas seketika. Self-seeding dari MAX yang sudah
            // ada saat prefix ini pertama kali dipakai (mis. awal bulan).
            $noFaktur = app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
                $prefix,
                fn () => DB::table('Transaksi_Cuti')
                    ->where('No_Transaksi', 'like', $prefix.'%')
                    ->max(DB::raw('CAST(RIGHT(No_Transaksi, 4) AS INT)')),
            );

            // Upload File
            $filePath = null;
            // dd($folderPath);
            // if (isset($validated['file_pendukung'])) {
            //     $file = $validated['file_pendukung'];
            //     $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            //     $folderPath = 'izinSakitCuti/' . date('Y/m');
            //     $filePath = $file->storeAs($folderPath, $fileName, 'public');
            //     Storage::disk('gcs')->putFileAs($folderPath, $file, $fileName);
            // }
            //             dd(
            //     $request->all(),
            //     $request->file('file_pendukung'),
            //     $request->hasFile('file_pendukung')
            // );

            // dd(env('GOOGLE_CLOUD_KEY_FILE_PATH'));
            // if ($request->hasFile('file_pendukung')) {
            //     $file = $request->file('file_pendukung');

            //     if ($file->isValid()) {
            //         $fileName   = Str::uuid() . '.' . $file->getClientOriginalExtension();
            //         $folderPath = 'izinSakitCuti/' . date('Y/m');
            //         // dd($folderPath, $file, $fileName);
            //         Storage::disk('gcs')->putFileAs(
            //             $folderPath,
            //             $file,
            //             $fileName
            //         );

            //         $filePath = $folderPath . '/' . $fileName;
            //     }
            // }
            // dd(config('services.hcis.batas_approve_cuti'));
            if ($request->hasFile('file_pendukung')) {
                $file = $request->file('file_pendukung');

                if ($file->isValid()) {
                    $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
                    $path = 'izinSakitCuti/'.date('Y/m').'/'.$fileName;

                    Storage::disk('gcs')->put(
                        $path,
                        fopen($file->getPathname(), 'r'), // ⬅️ PENTING
                    );

                    $filePath = $path;
                    $uploadedGcsPath = $path;
                }
            }

            // dd($filePath);
            // Insert Header — transaksi dibuka DI SINI (setelah validasi & upload),
            // sehingga HTTP upload di atas tidak menahan lock DB.
            DB::beginTransaction();
            DB::table('Transaksi_Cuti')->insert([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noFaktur,
                'Tanggal' => now(),
                'Jam' => now()->format('H:i:s'),
                'Status' => null,
                'UserID' => $pengguna->UserID_Web,
            ]);

            // Insert Detail
            $detailId = DB::table('Transaksi_Cuti_Detail')->insertGetId([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noFaktur,
                'Kode_Karyawan' => $pengguna->Kode_Karyawan,
                'Jenis' => $Jenis_Cuti->Nama_Jenis_Cuti,
                'Id_Jenis_Cuti' => $Jenis_Cuti->Id_Jenis_Cuti,
                'Tanggal_Cuti_Dari' => $tanggalMulai,
                'Tanggal_Cuti_Sampai' => $tanggalSelesai,
                'Alasan' => $validated['alasan'],
                'filePath' => $filePath,
                'Calendar_Audit_Json' => $calendarAuditJson,
            ]);

            /*
             * Satu INSERT untuk SEMUA tanggal, bukan satu per tanggal.
             *
             * Kenapa ini berarti di sini, padahal kuerinya sendiri sepele:
             * satu bolak-balik ke basis data pada lingkungan ini TERUKUR 54 ms
             * (SELECT 1 kosong, p50 dari 30 percobaan), sedangkan eksekusi
             * kuerinya 0,4 ms. Jadi yang dibayar pengguna adalah PERJALANAN,
             * bukan pekerjaannya - dan bentuk lama membayar satu perjalanan
             * penuh per tanggal cuti.
             *
             * Populasi nyata: 80 pengajuan di basis data ini rata-rata 2,30
             * tanggal, maksimum 6. Jadi hematnya sekitar 1,3 x 54 ms = ~70 ms
             * per pengajuan - kecil, dan sengaja tidak dibesar-besarkan.
             *
             * Yang lebih penting daripada 70 ms itu: perulangan ini berada DI
             * DALAM transaksi, dan RCSI mati di basis data ini. Selama N
             * perjalanan itu berlangsung, kunci pada Transaksi_Cuti_Det
             * tertahan dan pembacanya ANTRE di belakangnya. Memampatkannya
             * jadi satu perjalanan memperpendek jendela itu sependek mungkin.
             *
             * Dipotong per 400 baris: SQL Server membatasi 2.100 parameter per
             * pernyataan, dan lima kolom x 400 baris = 2.000 - masih di bawah
             * batas bahkan bila suatu saat ada pengajuan yang jauh lebih
             * panjang daripada 6 hari.
             */
            $barisTanggal = [];
            foreach ($selectedDates as $date) {
                $barisTanggal[] = [
                    'Kode_Perusahaan' => '001',
                    'No_Transaksi' => $noFaktur,
                    'Kode_Karyawan' => $pengguna->Kode_Karyawan,
                    'Urut_Detail' => $detailId,
                    'Tanggal_Cuti' => Carbon::parse($date)->toDateString(),
                ];
            }

            foreach (array_chunk($barisTanggal, 400) as $potongan) {
                DB::table('Transaksi_Cuti_Det')->insert($potongan);
            }

            // ==========================================================================
            // 5. LOGIKA APPROVAL FLOW DINAMIS (RE-INDEXING & FLATTENING)
            // ==========================================================================

            if ($Jenis_Cuti->Perlu_Persetujuan_Khusus === 'Y') {
                $flowResult = $this->approvalFlowService->createFlowFromConfig(
                    $noFaktur,
                    $pengguna->Kode_Karyawan,
                    'N_HRIS_Cuti_Approval',
                    'Id_Jenis_Cuti',
                    (int) $Id_Jenis,
                );

                if (! $flowResult->success) {
                    Log::channel('cutiController')->warning('Konfigurasi approval cuti tidak lengkap saat submit.', [
                        'no_transaksi' => $noFaktur,
                        'kode_karyawan' => $pengguna->Kode_Karyawan,
                        'id_jenis_cuti' => (int) $Id_Jenis,
                        'jenis_cuti' => $Jenis_Cuti->Nama_Jenis_Cuti ?? null,
                        'resolver_error' => $flowResult->error,
                    ]);

                    DB::rollBack();
                    $this->deleteUploadedCutiFile($uploadedGcsPath);

                    return ResponseHelper::error(
                        'Pengajuan belum bisa dikirim karena alur approval cuti Anda belum tersedia. Silakan hubungi HC/Admin untuk mengecek data atasan atau konfigurasi approval jenis cuti ini.',
                        422,
                    );
                }

                $this->notifyFirstApprover($flowResult->firstApprover, $noFaktur, $Jenis_Cuti, $pengguna);
            }

            DB::commit();
            $this->attendanceDirtyResolver->markFromTransaction(
                $noFaktur,
                'CUTI_SUBMITTED',
                'CUTI_CONTROLLER_STORE',
                3,
            );

            // Notif IN_APP ke seluruh member role pool kalau step pertama
            // adalah ROLE (mis. Verifikasi HC Ops Cuti). Tidak mengganggu WA
            // ke atasan untuk step TABLE.
            $this->cutiNotificationService->notifyRolePoolForCurrentStep($noFaktur, [
                'requesterNama' => $pengguna->Nama ?? null,
                'jenisCutiNama' => $Jenis_Cuti->Nama_Jenis_Cuti ?? null,
            ]);

            $successMessage = 'Pengajuan cuti berhasil dikirim.';
            $skippedDays = count($calendarGuard['audit']['skipped_dates'] ?? []);
            if ($skippedDays > 0) {
                $successMessage = "Pengajuan cuti berhasil dikirim untuk {$jumlahHariFinal} hari kerja. {$skippedDays} tanggal libur/day off/cuti bersama tidak dihitung.";
            }

            return ResponseHelper::success(null, $successMessage);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->deleteUploadedCutiFile($uploadedGcsPath);

            return ResponseHelper::error($e->validator->errors()->first(), 422);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->deleteUploadedCutiFile($uploadedGcsPath);
            Log::channel('cutiController')->error(
                'Gagal menyimpan pengajuan cuti: '.$e->getMessage().' line '.$e->getLine(),
            );

            return ResponseHelper::error('Terjadi kesalahan server.', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $e;
        }
    }

    public function destroy(Request $request)
    {
        // dd($request->all());
        $validated = $request->validate(['no_transaksi' => 'required']);

        DB::beginTransaction();
        try {
            $no_transaksi = $validated['no_transaksi'];
            $pengguna = Auth::user()->karyawan;
            $userid_web = Auth::user()->Id_Users;

            // Cek apakah transaksi ada dan milik karyawan yang sedang login
            $transaksi = DB::table('Transaksi_Cuti_Detail as a')
                ->join('Transaksi_Cuti as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->where('a.No_Transaksi', $no_transaksi)
                ->where('a.Kode_Karyawan', $pengguna->Kode_Karyawan)
                ->first();

            if (! $transaksi) {
                DB::rollBack();

                return ResponseHelper::error('Transaksi tidak ditemukan atau Anda tidak memiliki akses.', 404);
            }
            // Cek status approval
            $approvalStatus = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $no_transaksi)
                ->whereNotNull('Flag_Approval')
                ->exists();

            if ($approvalStatus) {
                DB::rollBack();

                return ResponseHelper::error('Transaksi ini sudah diproses dan tidak dapat dibatalkan.', 400);
            }
            // dd($no_transaksi);
            // Hapus Transaksi_Cuti
            $destroyData = DB::table('Transaksi_Cuti')
                ->where('No_Transaksi', $no_transaksi)
                ->update([
                    'Status' => 'Y',
                    'Updated_At' => now(),
                    'Updated_By' => $userid_web,
                ]);

            DB::commit();
            $this->attendanceDirtyResolver->markFromTransaction(
                $no_transaksi,
                'CUTI_CANCELLED',
                'CUTI_CONTROLLER_DESTROY',
                1,
            );

            return ResponseHelper::success($destroyData, 'Pengajuan cuti berhasil dibatalkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('cutiController')->error(
                'Gagal membatalkan pengajuan cuti: '.$e->getMessage().' on line '.$e->getLine(),
            );

            return ResponseHelper::error('Terjadi kesalahan server saat membatalkan pengajuan cuti.', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function getJenisCuti()
    {
        $pengguna = Auth::user()->karyawan;
        $jenis = DB::table('N_HRIS_Jenis_Cuti')->where('Flag_Aktif', 'Y')->get();
        $payload = collect($jenis)->map(function ($e) use ($pengguna) {
            $maks = $e->Maks_Hutang_Cuti !== null ? (int) $e->Maks_Hutang_Cuti : null;
            $mode = $e->Hutang_Cuti_Mode ?: 'OUTSTANDING';
            $basis = $e->Hutang_Cuti_Basis_Periode ?: 'KALENDER';
            $perhitungan = $e->Hutang_Periodik_Perhitungan ?: 'GROSS';

            // Sisa jatah hutang per jenis untuk user ini (untuk enforcement UX di frontend).
            $sisaJatah = null;
            $hutangTerpakai = null;
            if ($e->Flag_Bisa_Hutang_Cuti == 'Y' && ! empty($maks) && $pengguna) {
                $hutangTerpakai = $this->transactionService->getHutangTerpakai(
                    $pengguna->Kode_Karyawan,
                    (int) $e->Id_Jenis_Cuti,
                    $mode,
                    $basis,
                    $pengguna->Tgl_Masuk,
                    $perhitungan,
                );
                $sisaJatah = max(0.0, (float) $maks - $hutangTerpakai);
            }

            return [
                'id' => Hashids::connection('custom')->encode($e->Id_Jenis_Cuti),
                'nama' => $e->Nama_Jenis_Cuti,
                'deskripsi' => $e->Deskripsi,
                'kuota_default' => $e->Kuota_Default,
                'satuan' => $e->Satuan,
                'masa_berlaku_bulan' => (int) $e->Masa_Berlaku_Bulan,
                'dapat_diakumulasi' => $e->Dapat_Diakumulasi == 'Y' ? true : false,
                'gender_spesifik' => $e->Gender_Spesifik,
                'minimal_masa_kerja' => (int) $e->Minimal_Masa_Kerja_Bulan,
                'maksimum_pengajuan' => (int) $e->Maksimum_Pengajuan,
                'perlu_persetujuan_khusus' => $e->Perlu_Persetujuan_Khusus == 'Y' ? true : false,
                'dokumen_pendukung' => $e->Dokumen_Pendukung == 'Y' ? true : false,
                'memotong_saldo' => $e->Memotong_Saldo_Cuti == 'Y' ? true : false,
                'bisa_hutang_cuti' => $e->Flag_Bisa_Hutang_Cuti == 'Y' ? true : false,
                'maks_hutang_cuti' => $maks,
                'hutang_cuti_mode' => $mode,
                'hutang_cuti_basis_periode' => $basis,
                'hutang_periodik_perhitungan' => $perhitungan,
                'hutang_terpakai' => $hutangTerpakai !== null ? (float) $hutangTerpakai : null,
                'sisa_jatah_hutang' => $sisaJatah !== null ? (float) $sisaJatah : null,
                'batas_hari_pengajuan' => (int) $e->Batas_Hari_Pengajuan,
                'tipe_select_tanggal' => $e->Format_Tanggal_Select,
            ];
        });

        return ResponseHelper::success($payload, 'Berhasil mendapatkan data', 200);
    }

    /**
     * Rincian pemakaian jatah hutang cuti periode berjalan untuk user login.
     * Dipakai panel transparansi di halaman /cuti agar user paham kenapa jatah habis.
     */
    public function getHutangUsage(string $id)
    {
        try {
            $pengguna = Auth::user()->karyawan;
            $decoded = Hashids::connection('custom')->decode($id);
            $idJenis = $decoded[0] ?? null;

            if (! $idJenis) {
                return ResponseHelper::error('Jenis cuti tidak valid.', 404);
            }

            $jenis = DB::table('N_HRIS_Jenis_Cuti')->where('Id_Jenis_Cuti', $idJenis)->first();
            if (! $jenis) {
                return ResponseHelper::error('Jenis cuti tidak ditemukan.', 404);
            }

            $maks = $jenis->Maks_Hutang_Cuti !== null ? (int) $jenis->Maks_Hutang_Cuti : null;
            $mode = $jenis->Hutang_Cuti_Mode ?: 'OUTSTANDING';
            $basis = $jenis->Hutang_Cuti_Basis_Periode ?: 'KALENDER';
            $perhitungan = $jenis->Hutang_Periodik_Perhitungan ?: 'GROSS';

            [$start, $end] = $this->transactionService->getHutangPeriodeWindow($pengguna->Tgl_Masuk, $basis);

            $terpakai = ($jenis->Flag_Bisa_Hutang_Cuti === 'Y' && ! empty($maks))
                ? $this->transactionService->getHutangTerpakai($pengguna->Kode_Karyawan, (int) $idJenis, $mode, $basis, $pengguna->Tgl_Masuk, $perhitungan)
                : 0.0;
            $sisa = ! empty($maks) ? max(0.0, (float) $maks - $terpakai) : null;

            // Rincian berbasis JURNAL periode berjalan pada dompet hutang (notes 'Hutang').
            $debtWalletIds = DB::table('HRIS_Buku_Cuti')
                ->where('Kode_Karyawan', $pengguna->Kode_Karyawan)
                ->whereIn('Id_Jenis_Cuti', [1, 2])
                ->where('notes', 'like', '%Hutang%')
                ->pluck('Id_Buku_Cuti');

            // Ambil DEBIT (pinjam) + CREDIT ROLLBACK (pembatalan) pada dompet hutang dalam periode.
            $logRows = collect();
            $repaidTotal = 0.0;
            if ($debtWalletIds->isNotEmpty()) {
                $logRows = DB::table('N_HRIS_Log_Buku_Cuti')
                    ->whereIn('Id_Buku_Cuti', $debtWalletIds)
                    ->whereBetween('Created_At', [$start, $end])
                    ->where(function ($q) {
                        $q->where('Jenis_Mutasi', 'DEBIT')
                            ->orWhere(function ($x) {
                                $x->where('Jenis_Mutasi', 'CREDIT')->where('Kategori_Transaksi', 'like', '%ROLLBACK%');
                            });
                    })
                    ->orderBy('Id_Buku_Cuti')->orderBy('Created_At')->orderBy('Id_Log')
                    ->get(['Id_Log', 'Id_Buku_Cuti', 'Jumlah', 'No_Transaksi_Ref', 'Created_At', 'Kategori_Transaksi', 'Jenis_Mutasi']);

                // Pelunasan (CREDIT non-rollback) — hanya relevan untuk mode NETTO.
                $repaidTotal = (float) DB::table('N_HRIS_Log_Buku_Cuti')
                    ->whereIn('Id_Buku_Cuti', $debtWalletIds)
                    ->where('Jenis_Mutasi', 'CREDIT')
                    ->where('Kategori_Transaksi', 'not like', '%ROLLBACK%')
                    ->whereBetween('Created_At', [$start, $end])
                    ->sum(DB::raw('ABS(Jumlah)'));
            }

            // Cocokkan tiap ROLLBACK ke DEBIT terakhir pada dompet yang sama (LIFO) → tandai
            // debit yang dibatalkan agar TIDAK ikut ditampilkan/dihitung (tanpa baris terpisah).
            $rolledBackIds = [];
            foreach ($logRows->groupBy('Id_Buku_Cuti') as $walletLogs) {
                $stack = [];
                foreach ($walletLogs as $r) {
                    if ($r->Jenis_Mutasi === 'DEBIT') {
                        $stack[] = $r;
                    } else {
                        $amt = abs((float) $r->Jumlah);
                        while ($amt > 0.0001 && ! empty($stack)) {
                            $d = array_pop($stack);
                            $rolledBackIds[$d->Id_Log] = true;
                            $amt -= abs((float) $d->Jumlah);
                        }
                    }
                }
            }

            $borrowRows = $logRows->filter(fn ($r) => $r->Jenis_Mutasi === 'DEBIT' && empty($rolledBackIds[$r->Id_Log]))->values();

            // Konteks tanggal cuti untuk ref 'CT...'.
            $ctRefs = $borrowRows->pluck('No_Transaksi_Ref')->filter(fn ($r) => $r && str_starts_with($r, 'CT'))->unique()->values();
            $cutiCtx = collect();
            if ($ctRefs->isNotEmpty()) {
                $cutiCtx = DB::table('Transaksi_Cuti_Det')
                    ->whereIn('No_Transaksi', $ctRefs->all())
                    ->selectRaw('No_Transaksi, MIN(Tanggal_Cuti) as mulai, MAX(Tanggal_Cuti) as selesai, COUNT(*) as hari')
                    ->groupBy('No_Transaksi')->get()->keyBy('No_Transaksi');
            }

            $events = $borrowRows->map(function ($r) use ($cutiCtx) {
                $ref = $r->No_Transaksi_Ref ?? '';
                $kat = $r->Kategori_Transaksi ?? '';
                $konteks = null;

                if (str_starts_with($ref, 'CT')) {
                    $sumber = 'Pengajuan cuti';
                    $c = $cutiCtx->get($ref);
                    if ($c && $c->mulai) {
                        $mulai = Carbon::parse($c->mulai);
                        $selesai = Carbon::parse($c->selesai);
                        $rangeTxt = $mulai->toDateString() === $selesai->toDateString()
                            ? $mulai->format('d M Y')
                            : $mulai->format('d M').' – '.$selesai->format('d M Y');
                        $konteks = "Cuti {$rangeTxt} ({$c->hari} hari)";
                    }
                } elseif ($kat === 'CUTI_BERSAMA') {
                    $sumber = 'Cuti bersama';
                } elseif ($ref === 'MANUAL-ADJUST' || str_starts_with($ref, 'MANUAL') || str_starts_with($kat, 'ADJUSTMENT')) {
                    $sumber = 'Penyesuaian saldo oleh admin';
                } else {
                    $sumber = 'Hutang cuti';
                }

                return [
                    'tanggal' => Carbon::parse($r->Created_At)->toDateString(),
                    'jumlah' => (float) abs($r->Jumlah),
                    'sumber' => $sumber,
                    'ref' => $ref ?: null,
                    'konteks' => $konteks,
                    'pending' => false,
                    'status' => null,
                ];
            })->values();

            // NETTO: pelunasan mengembalikan jatah — kurangi entri sisa (terbaru dulu), tanpa baris terpisah.
            if ($perhitungan === 'NETTO' && $repaidTotal > 0) {
                $arr = $events->all();
                $rem = $repaidTotal;
                for ($i = count($arr) - 1; $i >= 0 && $rem > 0.0001; $i--) {
                    $take = min($rem, $arr[$i]['jumlah']);
                    $arr[$i]['jumlah'] -= $take;
                    $rem -= $take;
                }
                $events = collect(array_values(array_filter($arr, fn ($e) => $e['jumlah'] > 0.0001)));
            }

            // Entri PENDING: pengajuan cuti aktif yang BELUM di-approve final
            // (belum masuk jurnal HUTANG_CUTI, tapi sudah menahan jatah hutang).
            // Status='Y' (dibatalkan) tidak ikut → otomatis hilang saat dibatalkan.
            $pendingRows = DB::table('Transaksi_Cuti as t')
                ->join('Transaksi_Cuti_Detail as d', 'd.No_Transaksi', '=', 't.No_Transaksi')
                ->join('Transaksi_Cuti_Det as det', 'det.No_Transaksi', '=', 't.No_Transaksi')
                ->where('det.Kode_Karyawan', $pengguna->Kode_Karyawan)
                ->where('d.Id_Jenis_Cuti', (int) $idJenis)
                ->whereNull('d.Flag_Approval')
                ->whereNull('t.Status')
                ->groupBy('t.No_Transaksi')
                ->selectRaw('t.No_Transaksi as no, MIN(det.Tanggal_Cuti) as mulai, MAX(det.Tanggal_Cuti) as selesai, COUNT(det.Urut_Oto) as hari')
                ->get();

            // Bagian pending yang benar-benar jadi HUTANG = pendingContribution (agregat).
            // Alokasikan ke tiap pengajuan (yang terbaru dianggap hutang; yang lama tertutup
            // saldo) supaya jumlah rincian pending SUM-nya cocok dengan total terpakai.
            $pendingDebtLeft = (float) $pengguna->hutangPendingContribution();
            $pendingEvents = collect();
            foreach ($pendingRows->sortByDesc(fn ($p) => $p->mulai)->values() as $p) {
                if ($pendingDebtLeft <= 0) {
                    break; // pengajuan sisanya tertutup saldo → tidak menambah hutang
                }
                $portion = min($pendingDebtLeft, (float) $p->hari);
                $pendingDebtLeft -= $portion;

                $mulai = $p->mulai ? Carbon::parse($p->mulai) : null;
                $selesai = $p->selesai ? Carbon::parse($p->selesai) : null;
                $konteks = null;
                if ($mulai) {
                    $rangeTxt = ($selesai && $mulai->toDateString() !== $selesai->toDateString())
                        ? $mulai->format('d M').' – '.$selesai->format('d M Y')
                        : $mulai->format('d M Y');
                    $konteks = "Cuti {$rangeTxt} ({$p->hari} hari diajukan)";
                }

                $pendingEvents->push([
                    'tanggal' => $mulai ? $mulai->toDateString() : null,
                    'jumlah' => (float) $portion,
                    'sumber' => 'Pengajuan cuti',
                    'ref' => $p->no,
                    'konteks' => $konteks,
                    'pending' => true,
                    'status' => 'MENUNGGU',
                ]);
            }

            // Pending ditaruh paling atas (paling relevan / bisa dibatalkan).
            $events = $pendingEvents->values()->concat($events)->values();

            $outstanding = (float) $pengguna->hutang_cuti();

            // Tanggal reset (awal periode berikutnya).
            $resetDate = $basis === 'KALENDER'
                ? Carbon::parse($end)->copy()->addDay()
                : Carbon::parse($end);

            return ResponseHelper::success([
                'jenis' => [
                    'id' => $id,
                    'nama' => $jenis->Nama_Jenis_Cuti,
                    'satuan' => $jenis->Satuan ?: 'hari',
                ],
                'bisa_hutang' => $jenis->Flag_Bisa_Hutang_Cuti === 'Y',
                'mode' => $mode,
                'basis' => $basis,
                'perhitungan' => $perhitungan,
                'maks' => $maks,
                'terpakai' => (float) $terpakai,
                'sisa' => $sisa,
                'outstanding' => $outstanding,
                'semua_lunas' => $outstanding <= 0,
                'tahun' => Carbon::parse($start)->year,
                'periode_start' => Carbon::parse($start)->toDateString(),
                'periode_end' => Carbon::parse($end)->toDateString(),
                'reset_date' => $resetDate->toDateString(),
                'events' => $events,
            ], 'Berhasil mendapatkan rincian hutang cuti', 200);
        } catch (\Throwable $e) {
            Log::error('getHutangUsage error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil rincian hutang cuti.', 500);
        }
    }

    public function getJenisCutiApprovalFlow(string $id)
    {
        try {
            $decoded = Hashids::connection('custom')->decode($id);
            $idJenisCuti = $decoded[0] ?? null;

            if (! $idJenisCuti) {
                return ResponseHelper::error('Jenis cuti tidak valid.', 404);
            }

            $jenisCuti = DB::table('N_HRIS_Jenis_Cuti')
                ->where('Id_Jenis_Cuti', $idJenisCuti)
                ->where('Flag_Aktif', 'Y')
                ->first();

            if (! $jenisCuti) {
                return ResponseHelper::error('Jenis cuti tidak ditemukan.', 404);
            }

            $configSelects = [
                'ca.Order_Flow',
                'ca.Order_Approver_Limit',
                'ja.Id_Jenis_Approval',
                'ja.Nama_Approval',
                'ja.Source_Type',
                'ja.Source_Reference',
            ];

            $configs = DB::table('N_HRIS_Cuti_Approval as ca')
                ->join('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'ca.Id_Jenis_Approval')
                ->where('ca.Id_Jenis_Cuti', $idJenisCuti)
                ->where('ca.Flag_Aktif', 'Y')
                ->where('ja.Flag_Aktif', 'Y')
                ->orderBy('ca.Order_Flow', 'asc')
                ->select($configSelects)
                ->get();

            if ($configs->isEmpty() && $jenisCuti->Perlu_Persetujuan_Khusus === 'Y') {
                return ResponseHelper::success(
                    [
                        'jenis_cuti' => [
                            'id' => $id,
                            'nama' => $jenisCuti->Nama_Jenis_Cuti,
                            'perlu_persetujuan_khusus' => true,
                        ],
                        'is_valid' => false,
                        'warnings' => collect([
                            'Jenis cuti ini wajib approval, tetapi konfigurasi alur approval belum tersedia.',
                        ]),
                        'steps' => collect(),
                    ],
                    'Preview alur approval berhasil diambil.',
                    200,
                );
            }

            $steps = $configs
                ->map(function ($config) {
                    $limit = $config->Order_Approver_Limit !== null
                        ? (int) $config->Order_Approver_Limit
                        : null;

                    $resolved = $this->approverResolver->resolve(
                        (int) $config->Id_Jenis_Approval,
                        (string) $config->Source_Type,
                        $config->Source_Reference,
                        Auth::user()->karyawan->Kode_Karyawan,
                        $limit,
                    );

                    // Mirror semantik ApprovalFlowService::createFlowFromConfig agar
                    // preview dan submit sinkron.
                    $found = $resolved->count();
                    $expected = ($limit !== null && $limit > 0) ? $limit : 1;
                    $shortfall = max(0, $expected - $found);

                    $isStepValid = true;
                    $warning = null;
                    if ($found === 0) {
                        $isStepValid = false;
                        $warning =
                            'Approver untuk tahap '.
                            ((int) $config->Order_Flow).
                            ' ('.
                            ($config->Nama_Approval ?? 'Approval').
                            ') belum ditemukan atau belum aktif.';
                    } elseif ($limit !== null && $limit > 0 && $found < $limit) {
                        $isStepValid = false;
                        $warning =
                            'Tahap '.
                            ((int) $config->Order_Flow).
                            ' ('.
                            ($config->Nama_Approval ?? 'Approval').
                            ') butuh '.
                            $expected.
                            ' approver berjenjang (level atasan), baru '.
                            $found.
                            ' tersedia — kurang '.
                            $shortfall.
                            ' level. Hubungi HC/Admin untuk melengkapi data atasan Anda.';
                    }

                    $approvers = collect();
                    if ($config->Source_Type === 'ROLE') {
                        $role = DB::table('N_HRIS_Role_Functional')
                            ->where('Role_Code', $config->Source_Reference)
                            ->where('Flag_Aktif', 'Y')
                            ->first();

                        if ($isStepValid) {
                            $approvers = collect([
                                [
                                    'nama' => $role->Role_Name ?? ($config->Source_Reference ?? 'Role approval'),
                                    'kode' => $role->Role_Code ?? null,
                                    'tipe' => 'ROLE',
                                ],
                            ]);
                        }
                    } elseif ($config->Source_Type === 'GROUP_TABLE') {
                        $approvers = collect([
                            [
                                'nama' => 'Tim approval',
                                'kode' => null,
                                'tipe' => 'GROUP',
                            ],
                        ]);
                    } else {
                        $kodeKaryawan = $resolved->pluck('kode_karyawan')->filter()->unique()->values();
                        $namaByKode = $kodeKaryawan->isEmpty()
                            ? collect()
                            : DB::table('Karyawan')
                                ->whereIn('Kode_Karyawan', $kodeKaryawan)
                                ->pluck('Nama', 'Kode_Karyawan');

                        $approvers = $resolved
                            ->map(function ($row) use ($namaByKode) {
                                $kode = $row->kode_karyawan ?? null;

                                return [
                                    'nama' => $kode ? $namaByKode[$kode] ?? $kode : 'Approver',
                                    'kode' => $kode,
                                    'tipe' => strtoupper((string) ($row->type ?? 'SERIAL')),
                                ];
                            })
                            ->values();
                    }

                    return [
                        'order_flow' => (int) $config->Order_Flow,
                        'nama_approval' => $config->Nama_Approval,
                        'source_type' => $config->Source_Type,
                        'source_reference' => $config->Source_Reference,
                        'order_approver_limit' => $limit,
                        'expected' => $expected,
                        'found' => $found,
                        'shortfall' => $shortfall,
                        'is_valid' => $isStepValid,
                        'warning' => $warning,
                        'approvers' => $approvers,
                    ];
                })
                ->values();

            $flowWarnings = $steps->pluck('warning')->filter(fn ($warning) => ! empty($warning))->values();

            $isFlowValid = $flowWarnings->isEmpty();

            return ResponseHelper::success(
                [
                    'jenis_cuti' => [
                        'id' => $id,
                        'nama' => $jenisCuti->Nama_Jenis_Cuti,
                        'perlu_persetujuan_khusus' => $jenisCuti->Perlu_Persetujuan_Khusus === 'Y',
                    ],
                    'is_valid' => $isFlowValid,
                    'warnings' => $flowWarnings,
                    'steps' => $steps,
                ],
                'Preview alur approval berhasil diambil.',
                200,
            );
        } catch (\Throwable $e) {
            Log::error('Gagal mengambil preview alur approval jenis cuti', [
                'jenis_cuti_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return ResponseHelper::error('Gagal mengambil preview alur approval jenis cuti.', 500);
        }
    }

    public function checkOverlapCutiBersama(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        $karyawan = Auth::user()->karyawan;
        $cutiBersama = collect($this->getCutiBersamaItemsForKaryawan($karyawan->Kode_Karyawan))
            ->filter(fn ($item) => $this->cutiBersamaItemOverlapsRange($item, $request->start, $request->end))
            ->values();

        return response()->json([
            'has_overlap' => $cutiBersama->count() > 0,
            'cuti_bersama_list' => $cutiBersama->pluck('nama_cuti_bersama'),
        ]);
    }

    public function checkSaldoCuti(Request $request)
    {
        $karyawan = Auth::user()->karyawan;
        $saldo = $karyawan->sisa_cuti();
        $hutang = $karyawan->hutang_cuti();

        $sisa = $saldo ? $saldo : 0;

        $payload = [
            'saldo' => $saldo,
            'hutang' => $hutang,
        ];

        return ResponseHelper::success($payload, 'Berhasil mendapatkan data', 200);
    }

    public function getSaldoCuti()
    {
        $karyawan = Auth::user()->karyawan;
        $saldo = $karyawan->sisa_cuti();
        $hutang = $karyawan->hutang_cuti();

        $payload = [
            'saldo' => $saldo,
            'hutang' => $hutang,
            'pending' => $this->hitungPengajuanCutiPending($karyawan->Kode_Karyawan),
        ];

        return ResponseHelper::success($payload, 'Berhasil mendapatkan data', 200);
    }

    /**
     * Jumlah pengajuan cuti milik sendiri yang masih menunggu approval.
     *
     * Ikut definisi yang sama dengan saringan `status_filter=pending` di
     * getRiwayat(): satu sumber, supaya angka di widget tidak pernah berbeda
     * dari isi tab Riwayat. Dihitung di server dan bukan dari `riwayatData`
     * milik halaman, karena data itu ter-paginasi dan ikut tersaring — apa pun
     * yang diturunkan darinya hanya menghitung halaman yang sedang tampil.
     */
    private function hitungPengajuanCutiPending(string $kodeKaryawan): int
    {
        return (int) DB::table('N_HRIS_VW_All_Transactions')
            ->where('Kode_Karyawan_Requester', $kodeKaryawan)
            ->where('Tipe_Transaksi', 'CUTI')
            ->whereNull('Flag_Approval')
            ->count();
    }

    private function notifyFirstApprover(
        ?object $firstStep,
        string $noFaktur,
        object $jenisCuti,
        object $pengguna,
    ): void {
        if (! $firstStep) {
            Log::channel('cutiController')->warning('Approval flow dibuat tanpa first approver.', [
                'no_transaksi' => $noFaktur,
            ]);

            return;
        }

        // Skip WhatsApp untuk approval pool/GROUP (mis. ROLE pool admin HC).
        // Notifikasi ke role pool sudah dihandle via IN_APP di
        // CutiNotificationService::notifyRolePoolForCurrentStep(). Broadcast WA
        // ke seluruh member pool menghabiskan biaya tanpa benefit.
        if (($firstStep->type ?? null) === 'GROUP') {
            Log::channel('cutiController')->info('Skip WA approver pertama: step pertama bertipe GROUP/POOL.', [
                'no_transaksi' => $noFaktur,
                'source_type' => $firstStep->source_type ?? null,
                'group_id' => $firstStep->group_id ?? null,
            ]);

            return;
        }

        $tanggalArray = DB::table('Transaksi_Cuti_Det')
            ->where('No_Transaksi', $noFaktur)
            ->pluck('Tanggal_Cuti')
            ->toArray();

        $approver = DB::table('Karyawan')
            ->where('Kode_Karyawan', $firstStep->kode_karyawan)
            ->where('Aktif', 'Y')
            ->select('Kode_Karyawan', 'Nama', 'HP')
            ->first();
        $approvers = $approver ? collect([$approver]) : collect();

        if ($approvers->isEmpty()) {
            Log::channel('cutiController')->warning('Tidak ada approver aktif untuk notifikasi pertama cuti.', [
                'no_transaksi' => $noFaktur,
                'first_step_type' => $firstStep->type ?? null,
                'first_step_group_id' => $firstStep->group_id ?? null,
            ]);

            return;
        }

        foreach ($approvers as $approverKaryawan) {
            if (empty($approverKaryawan->HP)) {
                continue;
            }

            $pesan = [
                'messaging_product' => 'whatsapp',
                'to' => $approverKaryawan->HP,
                'type' => 'template',
                'template' => [
                    'name' => 'notif_approver_hcis',
                    'language' => ['code' => 'id', 'policy' => 'deterministic'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $approverKaryawan->Nama],
                                ['type' => 'text', 'text' => $noFaktur],
                                ['type' => 'text', 'text' => $jenisCuti->Nama_Jenis_Cuti],
                                [
                                    'type' => 'text',
                                    'text' => FormatTanggalHelper::formatTanggalMultiple($tanggalArray),
                                ],
                                ['type' => 'text', 'text' => $pengguna->Nama],
                            ],
                        ],
                    ],
                ],
            ];

            WhatsappLogHelper::sendAndLog(
                $approverKaryawan->Kode_Karyawan,
                $approverKaryawan->Nama,
                $approverKaryawan->HP,
                $noFaktur,
                $pesan,
                function ($response) use ($noFaktur, $approverKaryawan, $firstStep, $pengguna) {
                    DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noFaktur)
                        ->where('Request_Order_Flow', 1)
                        ->when(
                            ($firstStep->type ?? null) === 'GROUP',
                            function ($query) use ($firstStep) {
                                $query->where('Flag_Group', 'Y')->where('Group_Reference_Id', $firstStep->group_id);
                            },
                            function ($query) use ($approverKaryawan) {
                                $query->where('Approver_Karyawan_Kode', $approverKaryawan->Kode_Karyawan);
                            },
                        )
                        ->update(['angka_wa' => DB::raw('COALESCE(angka_wa, 0) + 1')]);

                    Log::channel('cutiController')->info(
                        "WA sukses dikirim ke approver {$approverKaryawan->Kode_Karyawan}",
                        ['requester' => $pengguna->Kode_Karyawan, 'response' => $response],
                    );
                },
                function ($reason, $response) use ($approverKaryawan, $noFaktur, $pesan) {
                    Log::channel('cutiController')->warning(
                        "Gagal kirim WA ke approver {$approverKaryawan->Kode_Karyawan}",
                        ['reason' => $reason, 'response' => $response],
                    );

                    DB::table('Whatsapp_Log')->insert([
                        'Kode_Karyawan_Destination' => $approverKaryawan->Kode_Karyawan,
                        'Nama' => $approverKaryawan->Nama,
                        'HP' => $approverKaryawan->HP,
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

    private function buildCalendarAuditForSubmission(
        $karyawan,
        array $submittedDates,
        ?string $requestedFrom,
        ?string $requestedTo,
        object $jenisCuti,
    ): array {
        $checkedAt = now();
        $submitted = collect($submittedDates)
            ->map(function ($date) {
                try {
                    return Carbon::parse($date)->toDateString();
                } catch (\Throwable $e) {
                    return null;
                }
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (empty($submitted)) {
            return ['error' => 'Pilih minimal satu tanggal cuti yang valid.'];
        }

        try {
            if (! empty($requestedFrom) && ! empty($requestedTo)) {
                $start = Carbon::parse($requestedFrom)->startOfDay();
                $end = Carbon::parse($requestedTo)->startOfDay();
            } else {
                $start = Carbon::parse($submitted[0])->startOfDay();
                $end = Carbon::parse($submitted[count($submitted) - 1])->startOfDay();
            }
        } catch (\Throwable $e) {
            return ['error' => 'Range tanggal cuti tidak valid.'];
        }

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) > 370) {
            return ['error' => 'Range tanggal cuti terlalu panjang.'];
        }

        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $isRangeSubmission = ! empty($requestedFrom) && ! empty($requestedTo);
        $requestedDates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $requestedDates[] = $date->toDateString();
        }

        try {
            $dayStatusMap = collect(
                DB::select('SELECT * FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?)', [
                    $karyawan->Kode_Perusahaan ?? '001',
                    $karyawan->Kode_Karyawan,
                    $startDate,
                    $endDate,
                ]),
            )
                ->filter(fn ($row) => ! empty($row->Tanggal))
                ->keyBy(fn ($row) => Carbon::parse($row->Tanggal)->toDateString());
        } catch (\Throwable $e) {
            Log::channel('cutiController')->error('Gagal validasi status hari saat submit cuti: '.$e->getMessage(), [
                'kode_karyawan' => $karyawan->Kode_Karyawan ?? null,
                'start' => $startDate,
                'end' => $endDate,
            ]);

            return ['error' => 'Data kalender belum bisa divalidasi. Silakan coba lagi.'];
        }

        $holidayMap = DB::table('HRIS_Hari_Libur')
            ->whereDate('Tanggal', '>=', $startDate)
            ->whereDate('Tanggal', '<=', $endDate)
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->Tanggal)->toDateString());

        $cutiBersamaMap = collect($this->getCutiBersamaItemsForKaryawan($karyawan->Kode_Karyawan))
            ->filter(fn ($item) => $this->cutiBersamaItemOverlapsRange($item, $startDate, $endDate))
            ->flatMap(function ($item) {
                return collect(TanggalCutiBersama::dariItem($item))->mapWithKeys(function ($date) use ($item) {
                    return [
                        $date => [
                            'name' => $item['nama_cuti_bersama'] ?? 'Cuti Bersama',
                            'id' => $item['id_cuti_bersama'] ?? null,
                        ],
                    ];
                });
            });

        $existingCutiMap = DB::table('Transaksi_Cuti_Det')
            ->join('Transaksi_Cuti', 'Transaksi_Cuti_Det.No_Transaksi', '=', 'Transaksi_Cuti.No_Transaksi')
            ->join('Transaksi_Cuti_Detail', function ($join) {
                $join
                    ->on('Transaksi_Cuti_Det.No_Transaksi', '=', 'Transaksi_Cuti_Detail.No_Transaksi')
                    ->on('Transaksi_Cuti_Det.Urut_Detail', '=', 'Transaksi_Cuti_Detail.Urut_Oto');
            })
            ->where('Transaksi_Cuti_Det.Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->whereNull('Transaksi_Cuti.Status')
            ->where(function ($q) {
                $q->whereNull('Transaksi_Cuti_Detail.Flag_Approval')->orWhere(
                    'Transaksi_Cuti_Detail.Flag_Approval',
                    'Y',
                );
            })
            ->whereDate('Transaksi_Cuti_Det.Tanggal_Cuti', '>=', $startDate)
            ->whereDate('Transaksi_Cuti_Det.Tanggal_Cuti', '<=', $endDate)
            ->select([
                'Transaksi_Cuti_Det.Tanggal_Cuti',
                'Transaksi_Cuti_Det.No_Transaksi',
                'Transaksi_Cuti_Detail.Jenis',
            ])
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->Tanggal_Cuti)->toDateString());

        $sakitIzinMap = DB::table('Transaksi_Sakit_Izin_Det')
            ->join(
                'Transaksi_Sakit_Izin',
                'Transaksi_Sakit_Izin_Det.No_Transaksi',
                '=',
                'Transaksi_Sakit_Izin.No_Transaksi',
            )
            ->join('Transaksi_Sakit_Izin_Detail', function ($join) {
                $join
                    ->on('Transaksi_Sakit_Izin_Det.No_Transaksi', '=', 'Transaksi_Sakit_Izin_Detail.No_Transaksi')
                    ->on('Transaksi_Sakit_Izin_Det.Urut_Detail', '=', 'Transaksi_Sakit_Izin_Detail.Urut_Oto');
            })
            ->where('Transaksi_Sakit_Izin_Det.Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->whereNull('Transaksi_Sakit_Izin.Status')
            ->where(function ($q) {
                $q->whereNull('Transaksi_Sakit_Izin_Detail.Flag_Approval')->orWhere(
                    'Transaksi_Sakit_Izin_Detail.Flag_Approval',
                    'Y',
                );
            })
            ->whereDate('Transaksi_Sakit_Izin_Det.Tanggal_Sakit_Izin', '>=', $startDate)
            ->whereDate('Transaksi_Sakit_Izin_Det.Tanggal_Sakit_Izin', '<=', $endDate)
            ->select([
                'Transaksi_Sakit_Izin_Det.Tanggal_Sakit_Izin',
                'Transaksi_Sakit_Izin_Det.No_Transaksi',
                'Transaksi_Sakit_Izin_Detail.Jenis',
            ])
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->Tanggal_Sakit_Izin)->toDateString());

        $terlambatPulangCepatMap = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
            ->join(
                'Transaksi_Terlambat_Pulang_Cepat',
                'Transaksi_Terlambat_Pulang_Cepat_Detail.No_Transaksi',
                '=',
                'Transaksi_Terlambat_Pulang_Cepat.No_Transaksi',
            )
            ->where('Transaksi_Terlambat_Pulang_Cepat_Detail.Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->whereNull('Transaksi_Terlambat_Pulang_Cepat.Status')
            ->where(function ($q) {
                $q->whereNull('Transaksi_Terlambat_Pulang_Cepat_Detail.Flag_Approval')->orWhere(
                    'Transaksi_Terlambat_Pulang_Cepat_Detail.Flag_Approval',
                    'Y',
                );
            })
            ->whereRaw(
                'CAST(COALESCE(Transaksi_Terlambat_Pulang_Cepat_Detail.Tanggal_Hari_Kerja, Transaksi_Terlambat_Pulang_Cepat_Detail.Tanggal_Masuk_Pulang) AS DATE) BETWEEN ? AND ?',
                [$startDate, $endDate],
            )
            ->select([
                'Transaksi_Terlambat_Pulang_Cepat_Detail.No_Transaksi',
                'Transaksi_Terlambat_Pulang_Cepat_Detail.Jenis',
                DB::raw(
                    'CAST(COALESCE(Transaksi_Terlambat_Pulang_Cepat_Detail.Tanggal_Hari_Kerja, Transaksi_Terlambat_Pulang_Cepat_Detail.Tanggal_Masuk_Pulang) AS DATE) as Tanggal_Aktual_Izin',
                ),
            ])
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->Tanggal_Aktual_Izin)->toDateString());

        // Sakit/izin & terlambat/pulang-cepat GUGUR karena umur bila alur approval-nya
        // kedaluwarsa. Transaksi gugur = void, jadi TIDAK boleh memblokir pengajuan cuti
        // baru. Pakai sumber kebenaran yang sama dengan dashboard homepage: step
        // Flow_State='EXPIRED' di N_HRIS_VW_Approval_Request_V2 (lih.
        // HomepagePersonalDashboardService::isPersonalSubmissionExpiredRecord).
        $expiredTransaksiSet = $this->expiredConflictTransactions(
            collect($sakitIzinMap)->pluck('No_Transaksi')
                ->merge(collect($terlambatPulangCepatMap)->pluck('No_Transaksi'))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        );

        if (! empty($expiredTransaksiSet)) {
            $sakitIzinMap = $sakitIzinMap->reject(
                fn ($row) => isset($expiredTransaksiSet[$row->No_Transaksi ?? '']),
            );
            $terlambatPulangCepatMap = $terlambatPulangCepatMap->reject(
                fn ($row) => isset($expiredTransaksiSet[$row->No_Transaksi ?? '']),
            );
        }

        $minAllowedDate = Carbon::today()->addDays((int) ($jenisCuti->Batas_Hari_Pengajuan ?? 0))->toDateString();
        $selected = [];
        $selectedAudit = [];
        $skipped = [];
        $conflicts = [];

        // Bug fix (over-deduksi cuti): rentang requested hanya untuk konteks/audit.
        // HANYA proses tanggal yang benar-benar disubmit user; jangan menambah hari
        // kerja yang user deselect (mis. tengah rentang) ke $selected.
        $submittedSet = array_flip($submitted);

        foreach ($requestedDates as $date) {
            if (! isset($submittedSet[$date])) {
                continue;
            }

            $dayStatus = $dayStatusMap->get($date);
            $statusHari = $dayStatus->StatusHari ?? 'WORK';

            if ($date < $minAllowedDate) {
                $conflicts[] = [
                    'date' => $date,
                    'reason' => 'BEFORE_ALLOWED_DATE',
                    'message' => 'Tanggal melewati batas minimal pengajuan.',
                ];

                continue;
            }

            if ($holiday = $holidayMap->get($date)) {
                $skipped[] = [
                    'date' => $date,
                    'reason' => 'HOLIDAY',
                    'label' => $holiday->Nama_Hari_Libur ?? ($holiday->Keterangan ?? 'Hari Libur'),
                    'source' => 'HRIS_Hari_Libur',
                ];

                continue;
            }

            if (stripos((string) $statusHari, 'OFF') === 0) {
                $skipped[] = [
                    'date' => $date,
                    'reason' => 'DAY_OFF',
                    'label' => $statusHari,
                    'source' => 'N_HRIS_FN_GetStatusHariRange',
                ];

                continue;
            }

            if ($cutiBersama = $cutiBersamaMap->get($date)) {
                $skipped[] = [
                    'date' => $date,
                    'reason' => 'CUTI_BERSAMA',
                    'label' => $cutiBersama['name'] ?? 'Cuti Bersama',
                    'source' => 'cuti_bersama',
                ];

                continue;
            }

            if ($existing = $existingCutiMap->get($date)) {
                $unavailable = [
                    'date' => $date,
                    'reason' => 'EXISTING_CUTI',
                    'ref' => $existing->No_Transaksi ?? null,
                    'source' => 'Transaksi_Cuti_Det',
                ];
                $isRangeSubmission ? ($skipped[] = $unavailable) : ($conflicts[] = $unavailable);

                continue;
            }

            if ($existing = $sakitIzinMap->get($date)) {
                $unavailable = [
                    'date' => $date,
                    'reason' => 'EXISTING_SAKIT_IZIN',
                    'ref' => $existing->No_Transaksi ?? null,
                    'jenis' => $existing->Jenis ?? null,
                    'source' => 'Transaksi_Sakit_Izin_Det',
                ];
                $isRangeSubmission ? ($skipped[] = $unavailable) : ($conflicts[] = $unavailable);

                continue;
            }

            if ($existing = $terlambatPulangCepatMap->get($date)) {
                $unavailable = [
                    'date' => $date,
                    'reason' => 'EXISTING_TERLAMBAT_PULANG_CEPAT',
                    'ref' => $existing->No_Transaksi ?? null,
                    'jenis' => $existing->Jenis ?? null,
                    'source' => 'Transaksi_Terlambat_Pulang_Cepat_Detail',
                ];
                $isRangeSubmission ? ($skipped[] = $unavailable) : ($conflicts[] = $unavailable);

                continue;
            }

            $selected[] = $date;
            $selectedAudit[] = [
                'date' => $date,
                'status' => $statusHari,
                'source' => 'N_HRIS_FN_GetStatusHariRange',
            ];
        }

        $audit = [
            'checked_at' => $checkedAt->toDateTimeString(),
            'requested_range' => [
                'from' => $startDate,
                'to' => $endDate,
            ],
            'submitted_dates' => $submitted,
            'selected_dates' => $selectedAudit,
            'skipped_dates' => $skipped,
            'conflict_dates' => $conflicts,
            'summary' => [
                'requested_calendar_days' => count($requestedDates),
                'submitted_days' => count($submitted),
                'selected_work_days' => count($selected),
                'skipped_days' => count($skipped),
                'conflict_days' => count($conflicts),
            ],
        ];

        if (! empty($conflicts)) {
            $detailKonflik = $this->summarizeUnavailableDates($conflicts);

            return [
                'error' => 'Beberapa tanggal sudah tidak tersedia. Silakan refresh kalender dan pilih ulang.'
                    .($detailKonflik !== '' ? ' Tanggal bentrok: '.$detailKonflik.'.' : ''),
                'audit' => $audit,
            ];
        }

        if (empty($selected)) {
            $detailSkip = $this->summarizeUnavailableDates($skipped);

            return [
                'error' => 'Tidak ada tanggal kerja yang bisa diajukan dalam range ini.'
                    .($detailSkip !== '' ? ' Tanggal tidak tersedia: '.$detailSkip.'.' : ''),
                'audit' => $audit,
            ];
        }

        return [
            'selected_dates' => $selected,
            'audit' => $audit,
        ];
    }

    /**
     * Dari daftar No_Transaksi konflik, kembalikan yang sudah GUGUR (approval flow
     * kedaluwarsa) sebagai set [No_Transaksi => true]. Transaksi gugur tidak boleh
     * memblokir pengajuan cuti. Kriteria selaras dengan dashboard homepage: ada step
     * Flow_State='EXPIRED' di N_HRIS_VW_Approval_Request_V2.
     *
     * @param  array<int,string>  $noTransaksiList
     * @return array<string,bool>
     */
    private function expiredConflictTransactions(array $noTransaksiList): array
    {
        if (empty($noTransaksiList)) {
            return [];
        }

        return array_flip(
            DB::table('N_HRIS_VW_Approval_Request_V2')
                ->whereIn('No_Transaksi', $noTransaksiList)
                ->whereRaw("UPPER(LTRIM(RTRIM(COALESCE(Flow_State, '')))) = 'EXPIRED'")
                ->distinct()
                ->pluck('No_Transaksi')
                ->all(),
        );
    }

    /**
     * Ubah kode alasan skip/konflik jadi keterangan yang bisa dibaca user.
     */
    private function humanizeSkipReason(string $reason): string
    {
        return match ($reason) {
            'HOLIDAY' => 'Hari Libur',
            'DAY_OFF' => 'Hari Off',
            'CUTI_BERSAMA' => 'Cuti Bersama',
            'EXISTING_CUTI' => 'sudah ada pengajuan Cuti',
            'EXISTING_SAKIT_IZIN' => 'sudah tercatat Sakit/Izin',
            'EXISTING_TERLAMBAT_PULANG_CEPAT' => 'sudah tercatat Terlambat/Pulang Cepat',
            'BEFORE_ALLOWED_DATE' => 'melewati batas minimal pengajuan',
            default => 'tidak tersedia',
        };
    }

    /**
     * Rangkum daftar tanggal skip/konflik jadi kalimat "16 Jul 2026 (alasan), ...".
     */
    private function summarizeUnavailableDates(array $items): string
    {
        return collect($items)
            ->map(function ($item) {
                $tanggal = Carbon::parse($item['date'])->format('d M Y');

                return $tanggal.' ('.$this->humanizeSkipReason($item['reason'] ?? '').')';
            })
            ->implode(', ');
    }

    private function getCutiBersamaItemsForKaryawan(string $kodeKaryawan): array
    {
        $result = $this->cutiBersamaService->getDataCutiByKode_Karyawan($kodeKaryawan);

        return $result['data'][0]['items'] ?? [];
    }

    private function cutiBersamaItemOverlapsRange(array $item, string $start, string $end): bool
    {
        $startDate = Carbon::parse($start)->toDateString();
        $endDate = Carbon::parse($end)->toDateString();

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $dates = TanggalCutiBersama::dariItem($item);
        if (! empty($dates)) {
            return collect($dates)->contains(fn ($date) => $date >= $startDate && $date <= $endDate);
        }

        $itemStart = ! empty($item['tanggal_mulai']) ? Carbon::parse($item['tanggal_mulai'])->toDateString() : null;
        $itemEnd = ! empty($item['tanggal_selesai'])
            ? Carbon::parse($item['tanggal_selesai'])->toDateString()
            : $itemStart;

        return $itemStart !== null && $itemStart <= $endDate && $itemEnd >= $startDate;
    }

    private function mapCutiBersamaItemToLegacyRecord(array $item): array
    {
        $dates = TanggalCutiBersama::dariItem($item);

        return [
            'Id_Cuti_Bersama' => $item['id_cuti_bersama'] ?? null,
            'Nama_Cuti_Bersama' => $item['nama_cuti_bersama'] ?? null,
            'Nama_Hari_Libur' => $item['nama_cuti_bersama'] ?? 'Cuti Bersama',
            'Tanggal_Mulai' => $dates[0] ?? ($item['tanggal_mulai'] ?? null),
            'Tanggal_Selesai' => ! empty($dates) ? $dates[count($dates) - 1] : $item['tanggal_selesai'] ?? null,
            'Jumlah_Hari' => $item['jumlah_hari'] ?? count($dates),
            'Selected_Dates' => $dates,
            'Status_Cuti_Bersama' => $item['status'] ?? null,
        ];
    }

    private function mapCutiBersamaItemToLegacyRecords(array $item): array
    {
        $dates = TanggalCutiBersama::dariItem($item);

        if (empty($dates)) {
            return [$this->mapCutiBersamaItemToLegacyRecord($item)];
        }

        return collect($dates)
            ->map(function ($date) use ($item, $dates) {
                return [
                    'Id_Cuti_Bersama' => $item['id_cuti_bersama'] ?? null,
                    'Nama_Cuti_Bersama' => $item['nama_cuti_bersama'] ?? null,
                    'Nama_Hari_Libur' => $item['nama_cuti_bersama'] ?? 'Cuti Bersama',
                    'Tanggal_Mulai' => $date,
                    'Tanggal_Selesai' => $date,
                    'Jumlah_Hari' => 1,
                    'Selected_Dates' => $dates,
                    'Status_Cuti_Bersama' => $item['status'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    // private function checkSaldoCuti($karyawanId, $jenisCutiId, $jumlahHari)
    // {
    //     $saldo = SaldoCuti::where('karyawan_id', $karyawanId)
    //         ->where('jenis_cuti_id', $jenisCutiId)
    //         ->first();

    //     return $saldo && $saldo->sisa >= $jumlahHari;
    // }
}
