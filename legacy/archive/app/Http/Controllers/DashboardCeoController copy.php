<?php

namespace App\Http\Controllers;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class DashboardCeoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('KPI/Performance/Dashboard/ceo/dashboardCeo');
    }

    public function getDataListDivisi()
    {
        $pengguna = Auth::user();

        try {
            $getData = DB::table('KPI_User_Akses_Divisi')
                ->select(
                    'KPI_User_Akses_Divisi.Id_Divisi',
                    'View_Divisi_Sub_Divisi.nama_divisi as Nama_Divisi',
                )
                ->groupBy('KPI_User_Akses_Divisi.Id_Divisi',
                    'View_Divisi_Sub_Divisi.nama_divisi')
                ->join(
                    'View_Divisi_Sub_Divisi',
                    'KPI_User_Akses_Divisi.Id_Divisi',
                    '=',
                    'View_Divisi_Sub_Divisi.ID_Divisi'
                )
                ->where('KPI_User_Akses_Divisi.Id_User', $pengguna->Id_Users)
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Divisi = Hashids::connection('custom')->encode($item->Id_Divisi);

                return $item;
            });

            return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('DashboardCeoController')->error('getDataListDivisiAndDepartemenOption: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getDataListDepartemen($divisionIds)
    {
        $pengguna = Auth::user();

        try {
            $query = DB::table('KPI_User_Akses_Divisi as a')
                ->join('View_Divisi_Sub_Divisi as b', 'a.Id_Divisi', '=', 'b.ID_Divisi')
                ->select('b.ID_Sub_Divisi', 'b.nama_sub_divisi')
                ->where('a.Id_User', $pengguna->Id_Users);

            if ($divisionIds !== 'ALL') {
                $decodedIds = [];
                try {
                    $hashedIds = explode(',', $divisionIds);
                    foreach ($hashedIds as $hashedId) {
                        $decoded = Hashids::connection('custom')->decode($hashedId);
                        if (! empty($decoded)) {
                            $decodedIds[] = $decoded[0];
                        }
                    }

                    if (empty($decodedIds)) {
                        return ResponseHelper::error('Format Kunci Tidak Valid.', 400);
                    }
                } catch (\Exception $e) {
                    return ResponseHelper::error('Format Kunci Tidak Valid.', 400);
                }
                $query->whereIn('b.ID_Divisi', $decodedIds);
            }

            $getData = $query->distinct()->get();
            $encodedData = $getData->map(function ($item) {
                $item->ID_Sub_Divisi = Hashids::connection('custom')->encode($item->ID_Sub_Divisi);

                return $item;
            });

            return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('DashboardCeoController')->error('getDataListDepartemen: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getDataListLevelOption()
    {
        $pengguna = Auth::user();

        try {
            $getData = DB::table('KPI_User_Akses_Level')
                ->select(
                    'KPI_User_Akses_Level.Id_Level',
                    'View_Golongan_Sub_Golongan_Level_Jabatan.nama_level',
                )
                ->join(
                    'View_Golongan_Sub_Golongan_Level_Jabatan',
                    'KPI_User_Akses_Level.Id_Level',
                    '=',
                    'View_Golongan_Sub_Golongan_Level_Jabatan.ID_Level'
                )
                ->groupBy(
                    'KPI_User_Akses_Level.Id_Level',
                    'View_Golongan_Sub_Golongan_Level_Jabatan.nama_level'
                )
                ->where('KPI_User_Akses_Level.Id_User', $pengguna->Id_Users)
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Level = Hashids::connection('custom')->encode($item->Id_Level);

                return $item;
            });

            return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('DashboardCeoController')->error('getDataListDivisiAndDepartemenOption: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    // versi optimalisasi
    public function getDataInformasi(Request $request)
    {

        $pengguna = Auth::user();
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 5);
        $search = $request->input('q', null);

        $divisiIdsInput = $request->input('divisi');
        $departemenIdsInput = $request->input('departemen');
        $levelIdsInput = $request->input('level');
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');
        $levelIdsTopInput = $request->input('levelTop');
        $levelIdsUnderInput = $request->input('levelUnder');
        $sortStatus = $request->input('sort_status');

        try {

            $userAccessDivisi = DB::table('KPI_User_Akses_Divisi')
                ->where('Id_User', $pengguna->Id_Users)
                ->pluck('Id_Divisi');

            $userAccessLevel = DB::table('KPI_User_Akses_Level')
                ->where('Id_User', $pengguna->Id_Users)
                ->pluck('Id_Level');

            if ($userAccessDivisi->isEmpty() || $userAccessLevel->isEmpty()) {
                return ResponseHelper::success($this->getEmptyResultStructure(), 'Data Tidak Ditemukan (Tidak Ada Akses)', 200);
            }

            $decodeHashids = fn ($s) => $s ? collect(explode(',', $s))->map(fn ($h) => (Hashids::connection('custom')->decode(trim($h))[0] ?? null))->filter() : collect();

            $divisiIds = ($divisiIdsInput && $divisiIdsInput !== 'ALL') ? $decodeHashids($divisiIdsInput) : collect();
            $departemenIds = ($departemenIdsInput && $departemenIdsInput !== 'ALL') ? $decodeHashids($departemenIdsInput) : collect();
            $levelIds = ($levelIdsInput && $levelIdsInput !== 'ALL') ? $decodeHashids($levelIdsInput) : collect();
            $levelIdsTop = ($levelIdsTopInput && $levelIdsTopInput !== 'ALL') ? $decodeHashids($levelIdsTopInput) : collect();
            $levelIdsUnder = ($levelIdsUnderInput && $levelIdsUnderInput !== 'ALL') ? $decodeHashids($levelIdsUnderInput) : collect();

            $baseQuery = DB::table('KPI_Update_Performance')
                ->join('Karyawan as k', 'KPI_Update_Performance.Id_User', '=', 'k.UserID_Web')
                ->join('View_Divisi_Sub_Divisi as vdsd', 'k.ID_Divisi_Sub_Divisi', '=', 'vdsd.ID_Divisi_Sub_Divisi')
                ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj', 'k.Id_Level_Jabatan', '=', 'vgsglj.ID_Level_Jabatan')
                ->whereNull('KPI_Update_Performance.Status')
                ->where('KPI_Update_Performance.Status_Pengajuan', 'disetujui')
                ->where('KPI_Update_Performance.Flag_Approval', 'Y')
                ->where('KPI_Update_Performance.Flag_Verifikasi', 'Y')
                ->whereIn('vdsd.Id_Divisi', $userAccessDivisi)
                ->whereIn('vgsglj.ID_Level', $userAccessLevel);

            if ($tahun) {
                $baseQuery->whereRaw('LEFT(KPI_Update_Performance.Periode, 4) = ?', [$tahun]);
            }

            if ($divisiIds->isNotEmpty()) {
                $baseQuery->whereIn('vdsd.Id_Divisi', $divisiIds);
            }
            if ($departemenIds->isNotEmpty()) {
                $baseQuery->whereIn('vdsd.ID_Sub_Divisi', $departemenIds);
            }
            if ($levelIds->isNotEmpty()) {
                $baseQuery->whereIn('vgsglj.ID_Level', $levelIds);
            }

            $formatScore = fn ($score) => fmod(round($score, 1), 1) === 0.0 ? (int) round($score, 1) : round($score, 1);
            $allMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];

            $countEmployee = 0;
            $onTarget = 0;
            $underTarget = 0;
            $resultRecent = [];
            $topPerformers = collect();
            $underPerformers = collect();
            $chartBar = [];
            $chartHeatmap = [];

            if ($bulan && $bulan !== 'ALL') {
                $baseQuery->whereRaw('RIGHT(KPI_Update_Performance.Periode, 2) = ?', [$bulan]);

                $widgetCounts = (clone $baseQuery)
                    ->selectRaw('COUNT(DISTINCT KPI_Update_Performance.Id_User) as countEmployee')
                    ->selectRaw('COUNT(CASE WHEN KPI_Update_Performance.Total_Score >= 100 THEN 1 END) as onTarget')
                    ->selectRaw('COUNT(CASE WHEN KPI_Update_Performance.Total_Score < 100 THEN 1 END) as underTarget')
                    ->first();

                $countEmployee = $widgetCounts->countEmployee ?? 0;
                $onTarget = $widgetCounts->onTarget ?? 0;
                $underTarget = $widgetCounts->underTarget ?? 0;

                $allDataQuery = (clone $baseQuery)
                    ->join('KPI_Master_Performance as mp', 'KPI_Update_Performance.Id_Performance', '=', 'mp.Id_Master_Performance')
                    ->select(
                        'KPI_Update_Performance.Id_Update_Performance', 'k.Nama as name', 'vdsd.nama_divisi as division',
                        'vdsd.nama_sub_divisi as subdivision', 'KPI_Update_Performance.Total_Score as achievement',
                        'KPI_Update_Performance.Id_Pengajuan', 'mp.Keterangan as status', 'mp.Kode',
                        'KPI_Update_Performance.Tanggal', 'vgsglj.ID_Level', 'vgsglj.nama_jabatan as Nama_Jabatan'
                    );

                if (! empty($search)) {
                    $allDataQuery->where(fn ($q) => $q->where('k.Nama', 'LIKE', "%$search%")
                        ->orWhere('vdsd.nama_divisi', 'LIKE', "%$search%")
                    );
                }

                if ($sortStatus && in_array($sortStatus, ['asc', 'desc'])) {
                    $orderCase = ($sortStatus === 'asc')
                        ? "CASE mp.Kode WHEN 'NP' THEN 1 WHEN 'SY' THEN 2 WHEN 'GD' THEN 3 WHEN 'ET' THEN 4 ELSE 5 END ASC"
                        : "CASE mp.Kode WHEN 'ET' THEN 1 WHEN 'GD' THEN 2 WHEN 'SY' THEN 3 WHEN 'NP' THEN 4 ELSE 5 END ASC";
                    $allDataQuery->orderByRaw($orderCase);
                }

                $allDataQuery->orderByDesc('KPI_Update_Performance.Tanggal')
                    ->orderByDesc('KPI_Update_Performance.Id_Update_Performance');

                $allMonthlyData = $allDataQuery->get();

                $totalData = $allMonthlyData->count();
                $paginatedItems = $allMonthlyData->forPage($page, $limit);

                $resultRecent = [
                    'data' => $paginatedItems->values()->map(fn ($item) => [
                        'id' => bin2hex(random_bytes(16)).Str::random(16),
                        'Id_Update_Performance' => Hashids::connection('custom')->encode($item->Id_Update_Performance),
                        'Id_Pengajuan' => Hashids::connection('custom')->encode($item->Id_Pengajuan),
                        'name' => $item->name,
                        'division' => $item->division,
                        'subdivision' => $item->subdivision,
                        'achievement' => round($item->achievement, 1),
                        'status' => $item->status,
                    ])->all(),
                    'page' => $page,
                    'total_page' => ceil($totalData / $limit),
                    'total_data' => $totalData,
                    'is_full_year' => false,
                ];

                $topPerformersBase = ($levelIdsTop->isNotEmpty())
                    ? $allMonthlyData->whereIn('ID_Level', $levelIdsTop)
                    : $allMonthlyData;

                $topPerformers = $topPerformersBase
                    ->whereIn('Kode', ['ET', 'GD', 'SY'])
                    ->sortByDesc('achievement')
                    ->take(3)
                    ->values()
                    ->map(fn ($item, $key) => [
                        'rank' => $key + 1,
                        'Nama_Lengkap' => $item->name,
                        'Total_Score' => $item->achievement,
                        'Nama_Divisi' => $item->division,
                        'Nama_Departemen' => $item->subdivision,
                    ]);

                $underPerformersBase = ($levelIdsUnder->isNotEmpty())
                    ? $allMonthlyData->whereIn('ID_Level', $levelIdsUnder)
                    : $allMonthlyData;

                $underPerformers = $underPerformersBase
                    ->where('Kode', 'NP')
                    ->sortBy('achievement')
                    ->take(3)
                    ->values()
                    ->map(fn ($item, $key) => [
                        'rank' => $key + 1,
                        'Nama_Lengkap' => $item->name,
                        'Total_Score' => $item->achievement,
                        'Nama_Divisi' => $item->division,
                        'Nama_Departemen' => $item->subdivision,
                    ]);

                $chartBarData = $allMonthlyData->sortByDesc('achievement');
                $chartBar = [
                    'labels' => $chartBarData->pluck('name'),
                    'series' => $chartBarData->pluck('achievement')->map($formatScore),
                    'details' => $chartBarData->map(fn ($item) => [
                        'departemen' => $item->subdivision,
                        'jabatan' => $item->Nama_Jabatan,
                        'nama' => $item->name,
                    ]),
                ];

            } else {

                $performanceRanges = DB::table('KPI_Master_Performance')->orderBy('Range_Awal')->get();
                $performanceMasterMap = $performanceRanges->pluck('Keterangan', 'Kode');

                $latestUpdatesSubquery = (clone $baseQuery)
                    ->select(
                        'Id_User', 'Id_Update_Performance', 'Id_Pengajuan',
                        DB::raw('ROW_NUMBER() OVER (
                            PARTITION BY KPI_Update_Performance.Id_User 
                            ORDER BY KPI_Update_Performance.Tanggal DESC, KPI_Update_Performance.Id_Update_Performance DESC
                        ) as rn')
                    );

                $latestUpdatesMap = DB::table(DB::raw("({$latestUpdatesSubquery->toSql()}) as sub"))
                    ->mergeBindings($latestUpdatesSubquery)
                    ->where('rn', 1)
                    ->get()
                    ->keyBy('Id_User');

                $aggregatedData = (clone $baseQuery)
                    ->join('KPI_Update_Performance_Detail as kupd', 'KPI_Update_Performance.Id_Update_Performance', '=', 'kupd.Id_Update_Performance')
                    ->join('KPI_Pengajuan_Det as kpd', 'kupd.Id_Pengajuan_Det', '=', 'kpd.Id_Pengajuan_Det')
                    ->join('KPI_Pengajuan_Detail as kpi_detail', 'kpd.Id_Pengajuan_Detail', '=', 'kpi_detail.Id_Pengajuan_Detail')
                    ->select(
                        'k.UserID_Web', 'k.Nama', 'vdsd.nama_divisi', 'vdsd.nama_sub_divisi',
                        'vgsglj.nama_jabatan', 'vgsglj.ID_Level',
                        'kpi_detail.Id_Pengajuan_Detail', 'kpi_detail.Bobot',
                        'kpi_detail.Id_Master_Formula',
                        DB::raw('SUM(kpd.Nilai_Target) as total_target'),
                        DB::raw('SUM(kupd.Nilai_Actual) as total_actual')
                    )
                    ->groupBy(
                        'k.UserID_Web', 'k.Nama', 'vdsd.nama_divisi', 'vdsd.nama_sub_divisi',
                        'vgsglj.nama_jabatan', 'vgsglj.ID_Level',
                        'kpi_detail.Id_Pengajuan_Detail', 'kpi_detail.Bobot',
                        'kpi_detail.Id_Master_Formula'
                    )
                    ->get();

                $yearlyPerformance = $aggregatedData->groupBy('UserID_Web')->map(function ($kpiRecords, $userId) use ($latestUpdatesMap, $performanceRanges) {

                    $totalYearlyScore = 0;
                    $karyawanData = $kpiRecords->first();

                    foreach ($kpiRecords as $kpi) {
                        $achievementKPI = 0;

                        if ($kpi->total_target > 0 || $kpi->total_actual > 0) {

                            switch ($kpi->Id_Master_Formula) {
                                case 3:
                                    if ($kpi->total_target > 0) {
                                        $achievementKPI = ($kpi->total_actual / $kpi->total_target) * 100;
                                    }
                                    break;
                                case 4:
                                    if ($kpi->total_actual > 0) {
                                        $achievementKPI = ($kpi->total_target / $kpi->total_actual) * 100;
                                    } elseif ($kpi->total_target > 0) {
                                        $achievementKPI = 200;
                                    }
                                    break;
                                default:
                                    if ($kpi->total_target > 0) {
                                        $achievementKPI = ($kpi->total_actual / $kpi->total_target) * 100;
                                    }
                                    break;
                            }
                        }

                        $totalYearlyScore += $achievementKPI * ($kpi->Bobot / 100);
                    }

                    $performanceCode = 'NP';
                    foreach ($performanceRanges as $range) {
                        if ($totalYearlyScore >= $range->Range_Awal && $totalYearlyScore <= $range->Range_Akhir) {
                            $performanceCode = $range->Kode;
                            break;
                        }
                    }

                    $latestUpdate = $latestUpdatesMap->get($userId);

                    return [
                        'Id_Update_Performance' => $latestUpdate->Id_Update_Performance ?? null,
                        'Id_Pengajuan' => $latestUpdate->Id_Pengajuan ?? null,
                        'Nama_Lengkap' => $karyawanData->Nama,
                        'Total_Score' => $totalYearlyScore,
                        'Nama_Divisi' => $karyawanData->nama_divisi,
                        'Nama_Departemen' => $karyawanData->nama_sub_divisi,
                        'Nama_Jabatan' => $karyawanData->nama_jabatan,
                        'ID_Level' => $karyawanData->ID_Level,
                        'Kode' => $performanceCode,
                    ];
                });

                $yearlyPerformanceCollection = $yearlyPerformance->values();
                $countEmployee = $yearlyPerformanceCollection->count();
                $onTarget = $yearlyPerformanceCollection->where('Total_Score', '>=', 100)->count();
                $underTarget = $yearlyPerformanceCollection->where('Total_Score', '<', 100)->count();

                $topPerformersFiltered = ($levelIdsTop->isNotEmpty())
                    ? $yearlyPerformanceCollection->whereIn('ID_Level', $levelIdsTop)
                    : $yearlyPerformanceCollection;
                $topPerformers = $topPerformersFiltered->whereIn('Kode', ['ET', 'GD', 'SY'])->sortByDesc('Total_Score')->take(3)->values()->map(fn ($item, $key) => ['rank' => $key + 1] + $item);

                $underPerformersFiltered = ($levelIdsUnder->isNotEmpty())
                    ? $yearlyPerformanceCollection->whereIn('ID_Level', $levelIdsUnder)
                    : $yearlyPerformanceCollection;
                $underPerformers = $underPerformersFiltered->where('Kode', 'NP')->sortBy('Total_Score')->take(3)->values()->map(fn ($item, $key) => ['rank' => $key + 1] + $item);

                $recentKpiCollection = $yearlyPerformanceCollection;
                if (! empty($search)) {
                    $recentKpiCollection = $recentKpiCollection->filter(fn ($item) => stripos($item['Nama_Lengkap'], $search) !== false ||
                        stripos($item['Nama_Divisi'], $search) !== false
                    );
                }

                if ($sortStatus && in_array($sortStatus, ['asc', 'desc'])) {
                    $ascOrder = ['NP' => 1, 'SY' => 2, 'GD' => 3, 'ET' => 4, 'DEFAULT' => 5];
                    $descOrder = ['ET' => 1, 'GD' => 2, 'SY' => 3, 'NP' => 4, 'DEFAULT' => 5];
                    $orderMap = ($sortStatus === 'asc') ? $ascOrder : $descOrder;

                    $recentKpiCollection = $recentKpiCollection->sortBy(fn ($item) => $orderMap[$item['Kode']] ?? $orderMap['DEFAULT'])->values();
                } else {
                    $recentKpiCollection = $recentKpiCollection->sortByDesc('Total_Score')->values();
                }

                $totalData = $recentKpiCollection->count();
                $totalPage = (int) ceil($totalData / $limit);
                if ($totalPage < 1) {
                    $totalPage = 1;
                }
                $paginatedItems = $recentKpiCollection->forPage($page, $limit)->values();

                $formattedData = $paginatedItems->map(function ($item) use ($performanceMasterMap) {
                    return [
                        'id' => bin2hex(random_bytes(16)).Str::random(16),
                        'Id_Update_Performance' => $item['Id_Update_Performance'] ? Hashids::connection('custom')->encode($item['Id_Update_Performance']) : null,
                        'Id_Pengajuan' => $item['Id_Pengajuan'] ? Hashids::connection('custom')->encode($item['Id_Pengajuan']) : null,
                        'name' => $item['Nama_Lengkap'],
                        'division' => $item['Nama_Divisi'],
                        'subdivision' => $item['Nama_Departemen'],
                        'achievement' => round($item['Total_Score'], 1),
                        'status' => $performanceMasterMap[$item['Kode']] ?? $item['Kode'],
                    ];
                });

                $resultRecent = [
                    'data' => $formattedData,
                    'page' => $page,
                    'total_page' => $totalPage,
                    'total_data' => $totalData,
                    'is_full_year' => true,
                ];

                $chartBarData = $yearlyPerformanceCollection->sortByDesc('Total_Score');
                $chartBar = [
                    'labels' => $chartBarData->pluck('Nama_Lengkap'),
                    'series' => $chartBarData->pluck('Total_Score')->map($formatScore),
                    'details' => $chartBarData->map(fn ($item) => [
                        'departemen' => $item['Nama_Departemen'],
                        'jabatan' => $item['Nama_Jabatan'],
                        'nama' => $item['Nama_Lengkap'],
                    ]),
                ];
            }

            $heatmapRawData = (clone $baseQuery)
                ->select(
                    'k.UserID_Web', 'k.Nama as Nama_Lengkap', 'vgsglj.nama_jabatan as Nama_Jabatan',
                    'vdsd.nama_divisi as Nama_Divisi', 'vdsd.nama_sub_divisi as Nama_Departemen',
                    'KPI_Update_Performance.Periode', 'KPI_Update_Performance.Total_Score'
                )
                ->whereNotNull('k.UserID_Web')
                ->orderBy('k.Nama', 'asc')
                ->get();

            $monthCount = ($bulan && $bulan !== 'ALL') ? 1 : 12;
            $heatmapCategories = ($bulan && $bulan !== 'ALL')
                ? [$allMonths[(int) $bulan - 1]]
                : $allMonths;

            $groupedData = $heatmapRawData->groupBy('UserID_Web');
            $heatmapSeries = [];
            $heatmapDetails = [];

            foreach ($groupedData as $userId => $records) {
                $monthlyScores = array_fill(0, $monthCount, null);

                foreach ($records as $record) {
                    if ($record->Periode) {
                        $monthIndex = (int) substr($record->Periode, 5, 2) - 1; // 0-11

                        if ($monthCount === 1 && $monthIndex === ((int) $bulan - 1)) {
                            $monthlyScores[0] = $formatScore($record->Total_Score ?? 0);
                        } elseif ($monthCount === 12 && $monthIndex >= 0 && $monthIndex < 12) {
                            $monthlyScores[$monthIndex] = $formatScore($record->Total_Score ?? 0);
                        }
                    }
                }

                $firstRecord = $records->first();
                if ($firstRecord) {
                    $heatmapSeries[] = ['name' => $firstRecord->Nama_Lengkap, 'data' => $monthlyScores];
                    $heatmapDetails[] = [
                        'departemen' => $firstRecord->Nama_Departemen,
                        'divisi' => $firstRecord->Nama_Divisi,
                        'jabatan' => $firstRecord->Nama_Jabatan,
                    ];
                }
            }

            $chartHeatmap = ['series' => $heatmapSeries, 'details' => $heatmapDetails, 'categories' => $heatmapCategories];

            $resultAkhir = [
                'Widget' => [
                    'Employee' => $countEmployee,
                    'On_Target' => $onTarget,
                    'Under_Target' => $underTarget,
                ],
                'Recent_Kpi_Update' => $resultRecent,
                'Top_3_Performers' => $topPerformers->map(fn ($item) => (object) [
                    'rank' => $item['rank'],
                    'Nama_Lengkap' => $item['Nama_Lengkap'],
                    'Total_Score' => $formatScore($item['Total_Score']),
                    'Nama_Divisi' => $item['Nama_Divisi'],
                    'Nama_Departemen' => $item['Nama_Departemen'],
                ]),
                'Under_3_Performers' => $underPerformers->map(fn ($item) => (object) [
                    'rank' => $item['rank'],
                    'Nama_Lengkap' => $item['Nama_Lengkap'],
                    'Total_Score' => $formatScore($item['Total_Score']),
                    'Nama_Divisi' => $item['Nama_Divisi'],
                    'Nama_Departemen' => $item['Nama_Departemen'],
                ]),
                'Chart_Bar' => $chartBar,
                'Chart_Heatmap' => $chartHeatmap,
            ];

            return ResponseHelper::success($resultAkhir, 'Data Ditemukan', 200);

        } catch (\Exception $e) {
            Log::channel('DashboardCeoController')->error('getDataInformasi: '.$e->getMessage().' on line '.$e->getLine().' in file '.$e->getFile());

            return ResponseHelper::error('Terjadi Kesalahan Pada Server', 500);
        }
    }

    private function getEmptyResultStructure()
    {
        $emptyPaginated = [
            'data' => [], 'page' => 1, 'total_page' => 1, 'total_data' => 0, 'is_full_year' => false,
        ];

        return [
            'Widget' => ['Employee' => 0, 'On_Target' => 0, 'Under_Target' => 0],
            'Recent_Kpi_Update' => $emptyPaginated,
            'Top_3_Performers' => [],
            'Under_3_Performers' => [],
            'Chart_Bar' => ['labels' => [], 'series' => [], 'details' => []],
            'Chart_Heatmap' => ['series' => [], 'details' => [], 'categories' => []],
        ];
    }

    private function calculateAchievement($formulaString, $actual, $target)
    {
        $actual = (float) ($actual ?? 0);
        $target = (float) ($target ?? 0);
        $formulaTrimmed = trim($formulaString ?? '');

        try {
            // Pattern: [actualKpiEvo]/[targetKpiEvo] (Formula ID 3)
            if ($formulaTrimmed === '[actualKpiEvo]/[targetKpiEvo]') {
                if ($target > 0) {
                    return ($actual / $target) * 100.0;
                } else {
                    return ($actual == 0) ? 100.0 : 0.0; // Define 0/0 -> 100, x/0 -> 0
                }
            }

            // Pattern: (1+([targetKpiEvo]-[actualKpiEvo])/[targetKpiEvo]) (Formula ID 4)
            // This calculates 1 + (deviation ratio). Multiply by 100 for percentage scale.
            if ($formulaTrimmed === '(1+([targetKpiEvo]-[actualKpiEvo])/[targetKpiEvo])') {
                if ($target > 0) {
                    // Safe calculation: 1 + (target/target) - (actual/target) = 2 - (actual/target)
                    // Then multiply by 100
                    return (2.0 - ($actual / $target)) * 100.0;
                    // Alternate safe eval for this specific structure if needed:
                    // $result = eval('return (1 + (($target - $actual) / $target));');
                    // if ($result === false || is_nan($result) || is_infinite($result)) return 0.0;
                    // return (float)$result * 100.0;
                } else {
                    // Target = 0. If actual also 0, 100%. If actual > 0, score is bad (0%).
                    return ($actual == 0) ? 100.0 : 0.0;
                }
            }

            // Pattern: [targetKpiEvo]/[actualKpiEvo] (Misal ini ID 5)
            if ($formulaTrimmed === '[targetKpiEvo]/[actualKpiEvo]') {
                if ($actual > 0) {
                    return ($target / $actual) * 100.0;
                } elseif ($target > 0) { // Actual is 0, Target > 0
                    return 200.0; // Max score for T/A when actual is 0
                } else { // Actual is 0, Target is 0
                    return 100.0; // Define 0/0 behavior.
                }
            }

            // --- Fallback / Default ---
            // Jika tidak cocok pattern di atas, log warning dan coba A/T sebagai default
            Log::warning("KPI Formula Warning: Unknown formula pattern '{$formulaTrimmed}'. Using default A/T.", ['actual' => $actual, 'target' => $target]);
            if ($target > 0) {
                return ($actual / $target) * 100.0;
            } else {
                return ($actual == 0) ? 100.0 : 0.0;
            }

        } catch (\Throwable $e) {
            Log::error("KPI Formula Calculation Error: {$e->getMessage()}", ['formula' => $formulaString, 'actual' => $actual, 'target' => $target]);

            return 0.0;
        }
    }

    public function recentKpiUserDetail($id_pengajuan, $id_update_performance, $isyear)
    {
        try {
            $isYearBool = filter_var($isyear, FILTER_VALIDATE_BOOLEAN);

            $decodedPengajuanId = Hashids::connection('custom')->decode(trim($id_pengajuan));
            $decodedUpdatePerformanceId = Hashids::connection('custom')->decode(trim($id_update_performance));

            if (empty($decodedPengajuanId) || empty($decodedUpdatePerformanceId)) {
                return ResponseHelper::error('ID Pengajuan atau ID Update Performance tidak valid.', 400);
            }

            $pengajuanId = $decodedPengajuanId[0];
            $updatePerformanceId = $decodedUpdatePerformanceId[0];

            $performanceHeaderRef = DB::table('KPI_Update_Performance as kup')
                ->join('Karyawan as k', 'kup.Id_User', '=', 'k.UserID_Web')
                ->where('kup.Id_Update_Performance', $updatePerformanceId)
                ->where('kup.Id_Pengajuan', $pengajuanId)
                ->select('kup.Id_User', DB::raw('LEFT(kup.Periode, 4) as Tahun'), 'k.Nama as Nama_Lengkap', 'kup.Periode')
                ->first();

            if (! $performanceHeaderRef) {
                return ResponseHelper::error('Data performance KPI referensi tidak ditemukan.', 404);
            }
            $userId = $performanceHeaderRef->Id_User;
            $periodeTahun = $performanceHeaderRef->Tahun;
            $periodeBulan = ! $isYearBool ? Carbon::parse($performanceHeaderRef->Periode)->month : null;

            $masterFormulas = DB::table('KPI_Master_Formula')
                ->where('Flag_Aktif', 'Y')
                ->pluck('Rumus', 'Id_Master_Formula');

            $performanceRanges = DB::table('KPI_Master_Performance')
                ->orderBy('Range_Awal', 'DESC')
                ->get();

            $getPerformanceLevel = function ($scoreValue) use ($performanceRanges) {
                if (is_null($scoreValue)) {
                    return (object) ['Keterangan' => null, 'Warna' => null, 'Kode' => null];
                }
                foreach ($performanceRanges as $range) {
                    if ($scoreValue >= $range->Range_Awal && $scoreValue <= $range->Range_Akhir) {
                        return (object) ['Keterangan' => $range->Keterangan, 'Warna' => $range->Warna, 'Kode' => $range->Kode];
                    }
                }
                $lastRange = $performanceRanges->last();

                return $lastRange ? (object) ['Keterangan' => $lastRange->Keterangan, 'Warna' => $lastRange->Warna, 'Kode' => $lastRange->Kode] : (object) ['Keterangan' => 'N/A', 'Warna' => '#808080', 'Kode' => 'NA'];
            };

            if ($isYearBool !== true) {

                $performanceHeaderMonthly = DB::table('KPI_Update_Performance as kup')
                    ->join('KPI_Master_Performance as kmp', 'kup.Id_Performance', '=', 'kmp.Id_Master_Performance')
                    ->join('Karyawan as k', 'kup.Id_User', '=', 'k.UserID_Web')
                    ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj', 'k.Id_Level_Jabatan', '=', 'vgsglj.Id_Level_Jabatan')
                    ->join('View_Divisi_Sub_Divisi as vdss', 'k.ID_Divisi_Sub_Divisi', '=', 'vdss.ID_Divisi_Sub_Divisi')
                    ->select('kup.*', 'kmp.Keterangan as Status_Performance', 'kmp.Warna', 'k.Nama as Nama_Lengkap', 'k.Email', 'k.HP as Kontak', 'vgsglj.nama_jabatan', 'vdss.nama_sub_divisi')
                    ->where('kup.Id_Update_Performance', $updatePerformanceId)->where('kup.Id_Pengajuan', $pengajuanId)->first();

                if (! $performanceHeaderMonthly) {
                    return ResponseHelper::error('Data performance KPI bulanan tidak ditemukan.', 404);
                }

                $allDetailsMonthly = DB::table('KPI_Pengajuan_Detail as kpi_main')
                    ->join('KPI_Master_Satuan as kms', 'kpi_main.Id_Master_Satuan', '=', 'kms.Id_Master_Satuan')
                    ->leftJoin('KPI_Pengajuan_Det as kpd', function ($join) use ($periodeBulan, $periodeTahun) {
                        $join->on('kpi_main.Id_Pengajuan_Detail', '=', 'kpd.Id_Pengajuan_Detail')
                            ->whereRaw('LEFT(kpd.Periode, 4) = ?', [$periodeTahun])->whereRaw('SUBSTRING(kpd.Periode, 6, 2) = ?', [str_pad($periodeBulan, 2, '0', STR_PAD_LEFT)]);
                    })
                    ->leftJoin('KPI_Update_Performance_Detail as kupd', function ($join) use ($updatePerformanceId) {
                        $join->on('kpd.Id_Pengajuan_Det', '=', 'kupd.Id_Pengajuan_Det')->where('kupd.Id_Update_Performance', $updatePerformanceId);
                    })
                    ->where('kpi_main.Id_Pengajuan', $pengajuanId)
                    ->select('kpi_main.Id_Pengajuan_Detail', 'kpi_main.Nama_Parameter', 'kpi_main.Bobot', 'kpi_main.Id_Master_Formula', 'kms.Simbol_Satuan', 'kpd.Id_Pengajuan_Det', 'kpd.Nilai_Target', 'kupd.Id_Update_Performance_Detail', 'kupd.Nilai_Actual', 'kupd.File_Berkas', 'kupd.Alasan_Reject', 'kupd.Flag_Pica')
                    ->get();

                $transformedDetailsMonthly = $allDetailsMonthly->map(function ($item) use ($getPerformanceLevel, $masterFormulas) {
                    $newItem = new \stdClass;
                    $isInputPeriod = ! is_null($item->Id_Pengajuan_Det);
                    $wasSubmitted = ! is_null($item->Id_Update_Performance_Detail);

                    $newItem->id = $wasSubmitted ? Hashids::connection('custom')->encode($item->Id_Update_Performance_Detail) : ($isInputPeriod ? Hashids::connection('custom')->encode($item->Id_Pengajuan_Det) : Hashids::connection('custom')->encode($item->Id_Pengajuan_Detail));
                    $newItem->parameter = $item->Nama_Parameter;
                    $newItem->Bobot = (float) $item->Bobot;

                    $targetMonthly = $isInputPeriod ? (float) $item->Nilai_Target : null;
                    $actualMonthly = $wasSubmitted ? (float) $item->Nilai_Actual : null;

                    $achievementMonthly = null;
                    if ($wasSubmitted) {
                        $formulaString = $masterFormulas->get($item->Id_Master_Formula);
                        $achievementMonthly = $this->calculateAchievement($formulaString, $actualMonthly, $targetMonthly);
                        // $achievementMonthly = min($achievementMonthly, 200);
                    }

                    $scoreMonthly = $wasSubmitted ? ($achievementMonthly * ($newItem->Bobot / 100)) : null;

                    $newItem->targetValue = $targetMonthly;
                    $newItem->targetUnit = $item->Simbol_Satuan;
                    $newItem->actual = $actualMonthly;
                    $newItem->achievement = $wasSubmitted ? (float) round($achievementMonthly, 1) : null;
                    $newItem->score = $wasSubmitted ? (float) round($scoreMonthly, 1) : null;

                    $performanceMonthly = $getPerformanceLevel($newItem->achievement);
                    $newItem->statusKeterangan = $performanceMonthly->Keterangan;
                    $newItem->statusWarna = $performanceMonthly->Warna;

                    $newItem->is_input_period = $isInputPeriod;
                    $newItem->dataSourceFile = $wasSubmitted && ! empty($item->File_Berkas) ? (object) ['name' => $item->File_Berkas] : null;
                    $newItem->File_Berkas_Url = $wasSubmitted && ! empty($item->File_Berkas) ? Storage::disk('gcs')->temporaryUrl($item->File_Berkas, now()->addMinutes(20)) : null;
                    $newItem->isRejected = $wasSubmitted && ! is_null($item->Alasan_Reject);
                    $newItem->alasan_penolakan = $wasSubmitted ? $item->Alasan_Reject : null;
                    $newItem->pica = null;

                    if ($wasSubmitted && $item->Flag_Pica === 'Y') {
                        $picaDataMonthly = DB::table('KPI_Update_Performance_Det')->where('Id_Update_Performance_Detail', $item->Id_Update_Performance_Detail)->first();
                        if ($picaDataMonthly) {
                            $newItem->pica = (object) ['problem' => $picaDataMonthly->Identifikasi, 'action' => $picaDataMonthly->Koreksi_Aksi, 'targetDate' => FormatTanggalHelper::format($picaDataMonthly->Tanggal_Pelaksanaan, true)];
                        }
                    }

                    return $newItem;
                });

                $periodeMonthly = Carbon::parse($performanceHeaderMonthly->Periode);
                $periodeFormattedMonthly = 'Periode '.$periodeMonthly->format('M Y');
                $formattedHeaderMonthly = (object) ['Nama_Lengkap' => $performanceHeaderMonthly->Nama_Lengkap, 'Nama_Jabatan' => $performanceHeaderMonthly->nama_jabatan, 'Nama_Departemen' => $performanceHeaderMonthly->nama_sub_divisi, 'Status_Pengajuan' => $performanceHeaderMonthly->Status_Pengajuan, 'Achievement' => (float) round($performanceHeaderMonthly->Total_Score, 1), 'Status' => $performanceHeaderMonthly->Status_Performance, 'Warna' => $performanceHeaderMonthly->Warna, 'Email' => $performanceHeaderMonthly->Email, 'Kontak' => $performanceHeaderMonthly->Kontak, 'Jam' => $performanceHeaderMonthly->Jam, 'Tanggal' => $performanceHeaderMonthly->Tanggal, 'Flag_Aktif' => $performanceHeaderMonthly->Flag_Aktif, 'Periode' => $periodeFormattedMonthly, 'Flag_Pindah' => 'Y', 'is_year' => false];

                return ResponseHelper::success(['header' => $formattedHeaderMonthly, 'details' => $transformedDetailsMonthly], 'Data KPI Detail Bulanan Ditemukan!', 200);

            } else {

                $kpiParams = DB::table('KPI_Pengajuan_Detail as kpi_main')
                    ->join('KPI_Master_Satuan as kms', 'kpi_main.Id_Master_Satuan', '=', 'kms.Id_Master_Satuan')
                    ->where('kpi_main.Id_Pengajuan', $pengajuanId)
                    ->select('kpi_main.Id_Pengajuan_Detail', 'kpi_main.Nama_Parameter', 'kpi_main.Bobot', 'kms.Simbol_Satuan', 'kpi_main.Id_Master_Formula')
                    ->get();
                $paramIds = $kpiParams->pluck('Id_Pengajuan_Detail');

                $allTargets = DB::table('KPI_Pengajuan_Det')->whereIn('Id_Pengajuan_Detail', $paramIds)->whereRaw('LEFT(Periode, 4) = ?', [$periodeTahun])->groupBy('Id_Pengajuan_Detail')->select('Id_Pengajuan_Detail', DB::raw('SUM(ISNULL(Nilai_Target, 0)) as Total_Target'))->pluck('Total_Target', 'Id_Pengajuan_Detail');
                $allActuals = DB::table('KPI_Update_Performance_Detail as kupd')->join('KPI_Pengajuan_Det as kpd', 'kupd.Id_Pengajuan_Det', '=', 'kpd.Id_Pengajuan_Det')->join('KPI_Update_Performance as kup', 'kupd.Id_Update_Performance', '=', 'kup.Id_Update_Performance')->whereIn('kpd.Id_Pengajuan_Detail', $paramIds)->where('kup.Id_User', $userId)->where('kup.Status_Pengajuan', 'disetujui')->where('kup.Flag_Approval', 'Y')->where('kup.Flag_Verifikasi', 'Y')->whereNull('kup.Status')->whereRaw('LEFT(kup.Periode, 4) = ?', [$periodeTahun])->groupBy('kpd.Id_Pengajuan_Detail')->select('kpd.Id_Pengajuan_Detail', DB::raw('SUM(ISNULL(kupd.Nilai_Actual, 0)) as Total_Actual'))->pluck('Total_Actual', 'Id_Pengajuan_Detail');
                $allPicas = DB::table('KPI_Update_Performance_Det as pica_det')->join('KPI_Update_Performance_Detail as kupd', 'pica_det.Id_Update_Performance_Detail', '=', 'kupd.Id_Update_Performance_Detail')->join('KPI_Update_Performance as kup', 'kupd.Id_Update_Performance', '=', 'kup.Id_Update_Performance')->join('KPI_Pengajuan_Det as kpd', 'kupd.Id_Pengajuan_Det', '=', 'kpd.Id_Pengajuan_Det')->where('kup.Id_User', $userId)->where('kupd.Flag_Pica', 'Y')->whereRaw('LEFT(kup.Periode, 4) = ?', [$periodeTahun])->select('kpd.Id_Pengajuan_Detail', 'pica_det.Identifikasi', 'pica_det.Koreksi_Aksi', 'pica_det.Tanggal_Pelaksanaan', 'kup.Periode')->orderBy('kup.Periode')->get();
                $groupedPicas = $allPicas->groupBy('Id_Pengajuan_Detail');
                $allDataSources = DB::table('KPI_Update_Performance_Detail as kupd')->join('KPI_Update_Performance as kup', 'kupd.Id_Update_Performance', '=', 'kup.Id_Update_Performance')->join('KPI_Pengajuan_Det as kpd', 'kupd.Id_Pengajuan_Det', '=', 'kpd.Id_Pengajuan_Det')->whereIn('kpd.Id_Pengajuan_Detail', $paramIds)->where('kup.Id_User', $userId)->where('kup.Status_Pengajuan', 'disetujui')->where('kup.Flag_Approval', 'Y')->where('kup.Flag_Verifikasi', 'Y')->whereNull('kup.Status')->whereRaw('LEFT(kup.Periode, 4) = ?', [$periodeTahun])->whereNotNull('kupd.File_Berkas')->where('kupd.File_Berkas', '!=', '')->select('kpd.Id_Pengajuan_Detail', 'kupd.File_Berkas', 'kup.Periode')->orderBy('kup.Periode')->get();
                $groupedDataSources = $allDataSources->groupBy('Id_Pengajuan_Detail');

                // ***** PERBAIKAN LOGIKA: Kalkulasi Dinamis *****
                $totalYearlyScore = 0;
                $transformedDetailsYearly = $kpiParams->map(function ($item) use ($allTargets, $allActuals, $groupedPicas, $groupedDataSources, &$totalYearlyScore, $getPerformanceLevel, $masterFormulas) { // <-- Tambahkan $masterFormulas

                    $newItem = new \stdClass;
                    $paramId = $item->Id_Pengajuan_Detail;

                    $target = $allTargets->get($paramId, 0);
                    $actual = $allActuals->get($paramId, 0);

                    // Kalkulasi Achievement TAHUNAN Dinamis
                    $achievementKPI = 0;
                    $formulaString = $masterFormulas->get($item->Id_Master_Formula); // Ambil rumus string
                    $achievementKPI = $this->calculateAchievement($formulaString, $actual, $target); // Gunakan $actual, $target (SUM tahunan)
                    // $achievementKPI = min($achievementKPI, 200);

                    $score = $achievementKPI * ($item->Bobot / 100);
                    $totalYearlyScore += $score;

                    $newItem->id = Hashids::connection('custom')->encode($item->Id_Pengajuan_Detail);
                    $newItem->parameter = $item->Nama_Parameter;
                    $newItem->Bobot = (float) $item->Bobot;
                    $newItem->targetValue = (float) $target;
                    $newItem->targetUnit = $item->Simbol_Satuan;
                    $newItem->actual = (float) $actual;
                    $newItem->achievement = (float) round($achievementKPI, 1);
                    $newItem->score = (float) round($score, 1);

                    $performanceYearlyParam = $getPerformanceLevel($newItem->achievement);
                    $newItem->statusKeterangan = $performanceYearlyParam->Keterangan;
                    $newItem->statusWarna = $performanceYearlyParam->Warna;
                    $newItem->is_input_period = true;

                    $sourcesForThisParam = $groupedDataSources->get($paramId);
                    if ($sourcesForThisParam && $sourcesForThisParam->isNotEmpty()) {
                        $newItem->dataSourceFile = $sourcesForThisParam->map(fn ($source) => (object) ['name' => $source->File_Berkas, 'month' => Carbon::parse($source->Periode)->format('M'), 'periode' => Carbon::parse($source->Periode)->format('M Y')])->all();
                        $newItem->File_Berkas_Url = $sourcesForThisParam->map(fn ($source) => (object) ['url' => Storage::disk('gcs')->temporaryUrl($source->File_Berkas, now()->addMinutes(20)), 'month' => Carbon::parse($source->Periode)->format('M'), 'periode' => Carbon::parse($source->Periode)->format('M Y')])->filter()->all();
                    } else {
                        $newItem->dataSourceFile = [];
                        $newItem->File_Berkas_Url = [];
                    }

                    $newItem->isRejected = false;
                    $newItem->alasan_penolakan = null;

                    $picasForThisParam = $groupedPicas->get($paramId);
                    if ($picasForThisParam && $picasForThisParam->isNotEmpty()) {
                        $newItem->pica = $picasForThisParam->map(fn ($picaData) => (object) ['problem' => $picaData->Identifikasi, 'action' => $picaData->Koreksi_Aksi, 'targetDate' => FormatTanggalHelper::format($picaData->Tanggal_Pelaksanaan, true), 'periode' => Carbon::parse($picaData->Periode)->format('M Y')])->all();
                    } else {
                        $newItem->pica = null;
                    }

                    return $newItem;
                });
                // ***** AKHIR PERBAIKAN KALKULASI *****

                $roundedTotalScore = (float) round($totalYearlyScore, 1);
                $yearlyPerformanceOverall = $getPerformanceLevel($roundedTotalScore);

                $lastMonthlyHeader = DB::table('KPI_Update_Performance as kup')
                    ->join('Karyawan as k', 'kup.Id_User', '=', 'k.UserID_Web')
                    ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj', 'k.Id_Level_Jabatan', '=', 'vgsglj.Id_Level_Jabatan')
                    ->join('View_Divisi_Sub_Divisi as vdss', 'k.ID_Divisi_Sub_Divisi', '=', 'vdss.ID_Divisi_Sub_Divisi')
                    ->where('kup.Id_User', $userId)->whereRaw('LEFT(kup.Periode, 4) = ?', [$periodeTahun])->orderBy('kup.Periode', 'desc')->select('vgsglj.nama_jabatan', 'vdss.nama_sub_divisi', 'k.Email', 'k.HP as Kontak', 'kup.Jam', 'kup.Tanggal', 'kup.Flag_Aktif')->first();

                $formattedHeaderYearly = (object) ['Nama_Lengkap' => $performanceHeaderRef->Nama_Lengkap, 'Nama_Jabatan' => $lastMonthlyHeader->nama_jabatan ?? 'N/A', 'Nama_Departemen' => $lastMonthlyHeader->nama_sub_divisi ?? 'N/A', 'Status_Pengajuan' => 'disetujui', 'Achievement' => $roundedTotalScore, 'Status' => $yearlyPerformanceOverall->Keterangan, 'Warna' => $yearlyPerformanceOverall->Warna, 'Email' => $lastMonthlyHeader->Email ?? 'N/A', 'Kontak' => $lastMonthlyHeader->Kontak ?? 'N/A', 'Jam' => $lastMonthlyHeader->Jam ?? null, 'Tanggal' => $lastMonthlyHeader->Tanggal ?? null, 'Flag_Aktif' => $lastMonthlyHeader->Flag_Aktif ?? 'Y', 'Periode' => 'Full Year '.$periodeTahun, 'Flag_Pindah' => 'Y', 'is_year' => true];

                return ResponseHelper::success(['header' => $formattedHeaderYearly, 'details' => $transformedDetailsYearly], 'Data KPI Detail Tahunan Ditemukan!', 200);
            }

        } catch (\Exception $e) {
            Log::channel('RecentKpiDetail')->error('Error in recentKpiUserDetail: '.$e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString(), 'id_pengajuan' => $id_pengajuan, 'id_update' => $id_update_performance, 'isyear' => $isyear]);

            return ResponseHelper::error('Terjadi Kesalahan Pada Server Saat Mengambil Detail KPI.', 500);
        }
    }
}
