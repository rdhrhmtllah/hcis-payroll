<?php

namespace App\Http\Controllers\LMS\Training\Monitoring\AdminTrainingMonitoring;

use App\Helpers\ResponseHelper;
use App\Helpers\SignatureHelper;
use App\Http\Controllers\Controller;
use App\Jobs\LMS\Training\Export\GenerateLmsTrainingExportJob;
use App\Services\LMS\LmsTrainingExportService;
use App\Services\LMS\Training\Scoring\PenilaiEkspresiNumerik;
use App\Services\LMS\TrainingProgressReadService;
use App\Services\LMS\TrainingScheduleQueryService;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class AdminTrainingMonitoringController extends Controller
{
    public function __construct(
        private TrainingProgressReadService $trainingProgressReadService,
        private TrainingScheduleQueryService $trainingScheduleQueryService,
    ) {}

    /**
     * Halaman utama monitoring
     */
    public function index()
    {
        return inertia('LMS/Training/Monitoring/AdminTrainingMonitoring/MonitoringPage');
    }

    /**
     * API: Get filter options (departemen and divisi) dynamically
     */
    public function getFilterOptions()
    {
        try {
            $baseQuery = DB::table('Karyawan as k')->whereIn(
                'k.Kode_Karyawan',
                $this->trainingScheduleQueryService->activeKodeKaryawanSubQuery(),
            );

            $departemenQuery = clone $baseQuery;
            $divisiQuery = clone $baseQuery;
            $levelQuery = clone $baseQuery;
            $jabatanQuery = clone $baseQuery;

            $departemen = $departemenQuery
                ->join('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->select('div.nama_sub_divisi as name')
                ->whereNotNull('div.nama_sub_divisi')
                ->distinct()
                ->pluck('name');

            $divisi = $divisiQuery
                ->join('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->select('div.nama_divisi as name')
                ->whereNotNull('div.nama_divisi')
                ->distinct()
                ->pluck('name');

            $level = $levelQuery
                ->join(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as lvl',
                    'k.ID_Level_Jabatan',
                    '=',
                    'lvl.ID_Level_Jabatan',
                )
                ->select('lvl.nama_level as name')
                ->whereNotNull('lvl.nama_level')
                ->distinct()
                ->pluck('name');

            $jabatan = $jabatanQuery
                ->join(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as lvl',
                    'k.ID_Level_Jabatan',
                    '=',
                    'lvl.ID_Level_Jabatan',
                )
                ->select('lvl.nama_jabatan as name')
                ->whereNotNull('lvl.nama_jabatan')
                ->distinct()
                ->pluck('name');

            $lokasi = (clone $baseQuery)
                ->select('k.Cabang_Untuk_Gaji as name')
                ->whereNotNull('k.Cabang_Untuk_Gaji')
                ->where('k.Cabang_Untuk_Gaji', '!=', '')
                ->distinct()
                ->orderBy('k.Cabang_Untuk_Gaji')
                ->pluck('name');

            $kategori = DB::table('N_LMS_Training_Kategori')
                ->select('Nama_Kategori as name')
                ->whereNotNull('Nama_Kategori')
                ->distinct()
                ->orderBy('Nama_Kategori')
                ->pluck('name');

            return ResponseHelper::success([
                'departemen' => $departemen,
                'divisi' => $divisi,
                'level' => $level,
                'jabatan' => $jabatan,
                'lokasi' => $lokasi,
                'kategori' => $kategori,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getFilterOptions: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Terapkan filter rentang tanggal penjadwalan ke query builder.
     * Kolom dipilih dari mode: 'created' => Created_At, selain itu Waktu_Enroll.
     * Tidak melakukan apa-apa bila date_start / date_end kosong.
     */
    private function applyEnrollDateFilter($query, Request $request, string $alias = ''): void
    {
        $start = trim((string) $request->query('date_start', ''));
        $end = trim((string) $request->query('date_end', ''));
        if ($start === '' || $end === '') {
            return;
        }
        $col = $request->query('date_mode', 'enroll') === 'created' ? 'Created_At' : 'Waktu_Enroll';
        if ($alias !== '') {
            $col = "{$alias}.{$col}";
        }
        $query->whereDate($col, '>=', $start)->whereDate($col, '<=', $end);
    }

    /**
     * API: Daftar karyawan yang memiliki penjadwalan training
     */
    public function getEmployeeList(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 15);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('q', '');
            $statusFilter = $request->query('status', ''); // 'all', 'in-progress', 'completed'

            // Base query Karyawan yang ada di N_LMS_Penjadwalan
            $query = DB::table('Karyawan as k')->whereIn(
                'k.Kode_Karyawan',
                $this->trainingScheduleQueryService->activeKodeKaryawanSubQuery(),
            );

            // Join Divis / Departemen views for string display
            $query
                ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as lvl',
                    'k.ID_Level_Jabatan',
                    '=',
                    'lvl.ID_Level_Jabatan',
                );

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('k.Nama', 'LIKE', '%'.$search.'%')
                        ->orWhere('k.Kode_Karyawan', 'LIKE', '%'.$search.'%')
                        ->orWhere('k.No_Induk_Karyawan', 'LIKE', '%'.$search.'%');
                });
            }

            // Filter departemen / divisi / lokasi (multi-pilih, dicocokkan persis).
            $deptFilter = $this->multiValues($request, 'dept');
            $divisiFilter = $this->multiValues($request, 'divisi');
            $lokasiFilter = $this->multiValues($request, 'lokasi');
            if (! empty($deptFilter)) {
                $query->whereIn('div.nama_sub_divisi', $deptFilter);
            }
            if (! empty($divisiFilter)) {
                $query->whereIn('div.nama_divisi', $divisiFilter);
            }
            if (! empty($lokasiFilter)) {
                $query->whereIn('k.Cabang_Untuk_Gaji', $lokasiFilter);
            }

            // Eliminasi karyawan yang tidak punya training dalam rentang tanggal terpilih
            if ($request->filled('date_start') && $request->filled('date_end')) {
                $dateSub = DB::table('N_LMS_Penjadwalan')->select('Kode_Karyawan');
                $this->trainingScheduleQueryService->applyActiveFilter($dateSub);
                $this->applyEnrollDateFilter($dateSub, $request);
                $query->whereIn('k.Kode_Karyawan', $dateSub);
            }

            $totalData = $query->count();
            $totalPages = ceil($totalData / $limit) ?: 1;

            $query->select([
                'k.Kode_Karyawan',
                'k.No_Induk_Karyawan',
                'k.Nama as nama_karyawan',
                'div.nama_sub_divisi as departemen',
                'div.nama_divisi as divisi',
                'lvl.nama_jabatan as jabatan',
            ]);

            $query->orderBy('k.Nama', 'ASC')->offset($offset)->limit($limit);

            $employees = $query->get();

            // Append badge counts
            $kodeKaryawans = $employees
                ->pluck('Kode_Karyawan')
                ->map(function ($k) {
                    return strtoupper(trim($k));
                })
                ->toArray();

            $statsMap = [];
            if (! empty($kodeKaryawans)) {
                /**
                 * Compute in-progress and completed for each employee
                 */
                $penjadwalanStats = DB::table('N_LMS_Penjadwalan')
                    ->select(
                        DB::raw('UPPER(LTRIM(RTRIM(Kode_Karyawan))) as Kode_Karyawan'),
                        DB::raw("SUM(CASE WHEN Flag_Selesai = 'Y' THEN 1 ELSE 0 END) as total_selesai"),
                        DB::raw(
                            "SUM(CASE WHEN Flag_Selesai IS NULL OR Flag_Selesai != 'Y' THEN 1 ELSE 0 END) as total_berlangsung",
                        ),
                    )
                    ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(Kode_Karyawan)))'), $kodeKaryawans)
                    ->groupBy(DB::raw('UPPER(LTRIM(RTRIM(Kode_Karyawan)))'));
                $this->trainingScheduleQueryService->applyActiveFilter($penjadwalanStats);
                $this->applyEnrollDateFilter($penjadwalanStats, $request);
                $penjadwalanStats = $penjadwalanStats->get();

                foreach ($penjadwalanStats as $ps) {
                    $statsMap[$ps->Kode_Karyawan] = [
                        'selesai' => (int) $ps->total_selesai,
                        'berlangsung' => (int) $ps->total_berlangsung,
                    ];
                }
            }

            // Global stats for QuickStat widget
            $globalStats = DB::table('N_LMS_Penjadwalan')->select(
                DB::raw('COUNT(DISTINCT UPPER(LTRIM(RTRIM(Kode_Karyawan)))) as total_karyawan'),
                DB::raw("SUM(CASE WHEN Flag_Selesai = 'Y' THEN 1 ELSE 0 END) as total_selesai"),
                DB::raw(
                    "SUM(CASE WHEN Flag_Selesai IS NULL OR Flag_Selesai != 'Y' THEN 1 ELSE 0 END) as total_berlangsung",
                ),
            );
            $this->trainingScheduleQueryService->applyActiveFilter($globalStats);
            $this->applyEnrollDateFilter($globalStats, $request);
            $globalStats = $globalStats->first();

            $formattedEmployees = $employees->map(function ($emp) use ($statsMap) {
                $k = strtoupper(trim($emp->Kode_Karyawan));
                $berlangsung = $statsMap[$k]['berlangsung'] ?? 0;
                $selesai = $statsMap[$k]['selesai'] ?? 0;
                $total = $berlangsung + $selesai;

                return [
                    'kode_karyawan' => $emp->Kode_Karyawan,
                    'no_induk' => $emp->No_Induk_Karyawan ?? '-',
                    'nama' => $emp->nama_karyawan,
                    'departemen' => $emp->departemen ?? '-',
                    'divisi' => $emp->divisi ?? '-',
                    'jabatan' => $emp->jabatan ?? '-',
                    'training_berlangsung' => $berlangsung,
                    'training_selesai' => $selesai,
                    'total_training' => $total,
                ];
            });

            // Filter status client-side (mock server side status filter for now since it requires HAVING)
            if ($statusFilter === 'in-progress') {
                $formattedEmployees = $formattedEmployees
                    ->filter(function ($e) {
                        return $e['training_berlangsung'] > 0;
                    })
                    ->values();
            } elseif ($statusFilter === 'completed') {
                $formattedEmployees = $formattedEmployees
                    ->filter(function ($e) {
                        return $e['training_selesai'] > 0 && $e['training_berlangsung'] === 0;
                    })
                    ->values();
            }

            $response = ResponseHelper::successWithPaginationV2(
                $formattedEmployees,
                $page,
                $limit,
                $totalData,
                'Data karyawan berhasil dimuat',
            );

            $data = $response->getData(true);
            $data['summary'] = [
                'total_karyawan' => $globalStats->total_karyawan ?? 0,
                'total_berlangsung' => $globalStats->total_berlangsung ?? 0,
                'total_selesai' => $globalStats->total_selesai ?? 0,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error getEmployeeList: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * API: Daftar training spesifik milik satu karyawan
     */
    public function getEmployeeTrainings(string $kodeKaryawan, Request $request)
    {
        try {
            $decryptedKode = $kodeKaryawan;
            $normalizedKode = strtoupper(trim($decryptedKode));

            $karyawan = DB::table('Karyawan as k')
                ->whereRaw('UPPER(LTRIM(RTRIM(k.Kode_Karyawan))) = ?', [$normalizedKode])
                ->select(['k.Kode_Karyawan', 'k.Nama', 'k.No_Induk_Karyawan'])
                ->first();

            if (! $karyawan) {
                return ResponseHelper::error('Karyawan tidak ditemukan', 404);
            }

            $penjadwalanQuery = $this->trainingScheduleQueryService
                ->byKodeKaryawanWithPaketQuery($normalizedKode, 'p')
                ->select([
                    'p.Id_Penjadwalan',
                    'p.Id_Master_Paket',
                    'p.Id_Master_Rumus',
                    'p.Flag_Selesai',
                    'p.Waktu_Enroll',
                    'p.Created_At',
                    'mp.Nama_Paket',
                ])
                ->orderBy('p.Waktu_Enroll', 'desc');

            // Filter training agar konsisten dengan filter tanggal di list karyawan
            $this->applyEnrollDateFilter($penjadwalanQuery, $request, 'p');

            $penjadwalans = $penjadwalanQuery->get();

            // dd($penjadwalans);

            $trainings = $penjadwalans->map(function ($p) {
                // Get the main course details from the first item in the packet
                $materiData = $this->trainingScheduleQueryService->getFirstActiveMateriInPackageWithMetadata(
                    (int) $p->Id_Master_Paket,
                );

                $progressSnapshot = $this->trainingProgressReadService->computeProgressSnapshot(
                    (int) $p->Id_Penjadwalan,
                );
                $progressVal = (int) ($progressSnapshot['progress_percent'] ?? 0);
                $hasStartedActivity = $this->trainingProgressReadService->hasStartedActivity((int) $p->Id_Penjadwalan);
                $status = $p->Flag_Selesai === 'Y' ? 'completed' : 'in-progress';

                if (
                    $status === 'in-progress' &&
                    ! $hasStartedActivity &&
                    $p->Waktu_Enroll &&
                    Carbon::parse($p->Waktu_Enroll)->isFuture()
                ) {
                    $status = 'not-started';
                    $progressVal = 0;
                }

                $finalScoreSummary = $this->buildFinalScoreSummary(
                    (int) $p->Id_Penjadwalan,
                    (int) ($materiData->Id_Materi ?? 0),
                    $p->Id_Master_Rumus ? (int) $p->Id_Master_Rumus : null,
                );
                $trainingResultSummary = $this->buildTrainingResultSummary((int) $p->Id_Penjadwalan);
                $attendanceSummary = $this->buildAttendanceSummary((int) $p->Id_Penjadwalan);

                return [
                    'id_penjadwalan' => Hashids::connection('custom')->encode($p->Id_Penjadwalan),
                    'id_materi' => $materiData ? Hashids::connection('custom')->encode($materiData->Id_Materi) : null,
                    'judul' => $materiData->Judul_Training ?? $p->Nama_Paket,
                    'thumbnail_url' => $this->resolveThumbnailUrl($materiData->Thumbnail_Training ?? null),
                    'tanggal_enroll' => $p->Waktu_Enroll,
                    'tanggal_ditugaskan' => $p->Created_At,
                    'progress' => $progressVal,
                    'status' => $status,
                    'total_score' => $trainingResultSummary['total_score'] ?? null,
                    'total_score_display' => $trainingResultSummary['total_score_display'] ?? null,
                    'score_learning_gain' => $trainingResultSummary['score_learning_gain'] ?? null,
                    'score_learning_gain_display' => $trainingResultSummary['score_learning_gain_display'] ?? null,
                    'final_score' => $finalScoreSummary['final_score'] ?? null,
                    'final_score_display' => $trainingResultSummary['total_score_display'] ??
                        ($finalScoreSummary['final_score_display'] ?? null),
                    'final_score_tooltip' => $this->buildFinalScoreTooltip($finalScoreSummary),
                    'attendance' => $attendanceSummary,
                ];
            });

            // dd($trainings);

            return ResponseHelper::success([
                'employee' => [
                    'kode_karyawan' => $karyawan->Kode_Karyawan,
                    'nama' => $karyawan->Nama,
                    'no_induk' => $karyawan->No_Induk_Karyawan,
                ],
                'trainings' => $trainings,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getEmployeeTrainings: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Render Halaman Detail Test (Dummy Page)
     */
    public function showTestDetail(string $penjadwalanDetId)
    {
        $this->ensureMonitoringAdminAuthorized();

        $realId = Hashids::connection('custom')->decode($penjadwalanDetId)[0] ?? null;
        if (! $realId) {
            abort(404);
        }

        // Fetch complete info including training, section, and activity names
        $detInfo = DB::table('N_LMS_Penjadwalan_Det as pdet')
            ->join('N_LMS_Penjadwalan_Detail as pd', 'pdet.Id_Penjadwalan_Detail', '=', 'pd.Id_Penjadwalan_Detail')
            ->join('N_LMS_Penjadwalan as p', 'pd.Id_Penjadwalan', '=', 'p.Id_Penjadwalan')
            ->join('Karyawan as k', 'p.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->join('N_LMS_Materi_Detail as md', 'pd.Id_Materi_Detail', '=', 'md.Id_Materi_Detail')
            ->join('N_LMS_Materi as m', 'md.Id_Master_Training', '=', 'm.Id_Materi')
            ->join('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
            ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
            ->leftJoin('N_LMS_Penjadwalan_Integrasi as pi', 'pdet.Id_Penjadwalan_Det', '=', 'pi.Id_Penjadwalan_Det')
            ->leftJoin('N_LMS_Transaksi_Training_Detail as trx_detail', function ($join) {
                $join
                    ->on('trx_detail.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
                    ->on('trx_detail.Id_Materi', '=', 'det.Id_Materi')
                    ->on('trx_detail.Id_Materi_Det', '=', 'pdet.Id_Materi_Det');
            })
            ->leftJoin(
                'N_LMS_Transaksi_Training_Det as trx_det',
                'trx_det.Id_Transaksi_Training_Detail',
                '=',
                'trx_detail.Id_Transaksi_Training_Detail',
            )
            ->where('pdet.Id_Penjadwalan_Det', $realId)
            ->select([
                'k.Nama as nama_karyawan',
                'pdet.Waktu_Mulai_Akses',
                'pdet.Waktu_Batas_Akses',
                'pdet.Flag_Selesai',
                'm.Id_Materi',
                'm.Judul_Training as nama_training',
                'md.Id_Materi_Detail',
                'md.Nama_Sub as nama_section',
                'akt.Nama_Aktivitas as nama_aktivitas',
                'akt.Kode_Aktivitas',
                'pi.Waktu_Mulai as integrasi_mulai',
                'pi.Waktu_Akhir as integrasi_akhir',
                'p.Id_Penjadwalan',
                'p.Kode_Karyawan',
                'pd.Id_Penjadwalan_Detail',
                'trx_det.Skor_Akhir as skor_akhir',
            ]);

        $this->trainingScheduleQueryService->applyActiveFilter($detInfo, 'p');
        $detInfo = $detInfo->first();

        return inertia('LMS/Training/Monitoring/AdminTrainingMonitoring/TestDetailPage', [
            'detInfo' => $detInfo
                ? [
                    ...(array) $detInfo,
                    'encoded_materi_id' => Hashids::connection('custom')->encode($detInfo->Id_Materi),
                    'encoded_kode_karyawan' => $detInfo->Kode_Karyawan,
                ]
                : null,
            'penjadwalanDetId' => $penjadwalanDetId,
        ]);
    }

    /**
     * API: Detail jawaban peserta test dari third-party (proxy + signed headers)
     */
    public function getTestAnswerDetail(string $penjadwalanDetId)
    {
        try {
            $this->ensureMonitoringAdminAuthorized();

            $realId = Hashids::connection('custom')->decode($penjadwalanDetId)[0] ?? null;
            if (! $realId) {
                return ResponseHelper::error('ID test detail tidak valid', 400);
            }

            $detInfo = DB::table('N_LMS_Penjadwalan_Det as pdet')
                ->join('N_LMS_Penjadwalan_Detail as pd', 'pdet.Id_Penjadwalan_Detail', '=', 'pd.Id_Penjadwalan_Detail')
                ->join('N_LMS_Penjadwalan as p', 'pd.Id_Penjadwalan', '=', 'p.Id_Penjadwalan')
                ->join('Karyawan as k', 'p.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin('N_LMS_Penjadwalan_Integrasi as pi', 'pdet.Id_Penjadwalan_Det', '=', 'pi.Id_Penjadwalan_Det')
                ->join('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
                ->leftJoin('N_LMS_Transaksi_Training_Detail as trx_detail', function ($join) {
                    $join
                        ->on('trx_detail.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
                        ->on('trx_detail.Id_Materi', '=', 'det.Id_Materi')
                        ->on('trx_detail.Id_Materi_Det', '=', 'pdet.Id_Materi_Det');
                })
                ->leftJoin(
                    'N_LMS_Transaksi_Training_Det as trx_det',
                    'trx_det.Id_Transaksi_Training_Detail',
                    '=',
                    'trx_detail.Id_Transaksi_Training_Detail',
                )
                ->where('pdet.Id_Penjadwalan_Det', $realId)
                ->select([
                    'k.Nama as nama_karyawan',
                    'k.Kode_Karyawan as kode_karyawan_target',
                    'pdet.Waktu_Mulai_Akses',
                    'pdet.Waktu_Batas_Akses',
                    'pdet.Flag_Selesai',
                    'pi.Id_Ujian_Token',
                    'pi.Waktu_Mulai',
                    'pi.Waktu_Akhir',
                    'trx_det.Skor_Akhir as skor_akhir',
                ]);

            $this->trainingScheduleQueryService->applyActiveFilter($detInfo, 'p');
            $detInfo = $detInfo->first();

            if (! $detInfo) {
                return ResponseHelper::error('Detail test tidak ditemukan', 404);
            }

            if (! filled($detInfo->Id_Ujian_Token)) {
                return ResponseHelper::error('Token ujian tidak tersedia untuk aktivitas ini', 422);
            }

            $endpoint =
                'api/v1/detail/jawaban-peserta/hasil-akhir/'.
                trim((string) $detInfo->Id_Ujian_Token).
                '/gambar-jawaban/third-party';
            $baseDomain = trim((string) config('services.frans.hclearn_api.domain'));
            if (Str::startsWith($baseDomain, 'http://')) {
                $baseDomain = 'https://'.ltrim(substr($baseDomain, strlen('http://')), '/');
            }
            $url = rtrim($baseDomain, '/').'/'.ltrim($endpoint, '/');
            $appId = Auth::user()->User_Key ?? null;
            $kodeKaryawanHeader = Auth::user()->Kode_Users ?? null;
            $auth = SignatureHelper::getDetail($endpoint, $appId, $kodeKaryawanHeader);

            $response = Http::withHeaders($auth['headers'])->get($url);
            if (! $response->successful()) {
                $statusCode = $response->status();
                $contentType = (string) $response->header('Content-Type');

                if (str_contains($contentType, 'application/json')) {
                    $apiData = $response->json();
                    $messageFromApi =
                        $apiData['message'] ?? ($apiData['result'] ?? 'Gagal mengambil detail jawaban dari API.');

                    return ResponseHelper::error((string) $messageFromApi, $statusCode);
                }

                return response($response->body(), $statusCode)->header('Content-Type', 'text/html');
            }

            $apiData = $response->json();
            $payload = $apiData['result'] ?? ($apiData['data'] ?? $apiData);
            $normalized = $this->normalizeTestAnswerPayload($payload);

            return ResponseHelper::success([
                'meta' => [
                    'nama_karyawan' => $detInfo->nama_karyawan,
                    'kode_karyawan_target' => $detInfo->kode_karyawan_target,
                    'waktu_mulai_akses' => $detInfo->Waktu_Mulai ?? $detInfo->Waktu_Mulai_Akses,
                    'waktu_batas_akses' => $detInfo->Waktu_Akhir ?? $detInfo->Waktu_Batas_Akses,
                    'flag_selesai' => $detInfo->Flag_Selesai,
                    'id_ujian_token' => $detInfo->Id_Ujian_Token,
                    'skor_akhir' => $detInfo->skor_akhir,
                ],
                'summary' => $normalized['summary'],
                'questions' => $normalized['questions'],
                'pagination' => [
                    'page' => $apiData['page'] ?? 1,
                    'total_page' => $apiData['total_page'] ?? 1,
                    'total_data' => $apiData['total_data'] ?? count($normalized['questions']),
                ],
                'raw' => $normalized['raw'],
            ]);
        } catch (\Exception $e) {
            Log::error('Error getTestAnswerDetail: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Render training detail untuk admin.
     * Reuses trainingDetail.vue from User interface but passing viewMode='admin'
     */
    public function showTrainingDetail(string $materiId, string $kodeKaryawan)
    {
        // dd($materiId, $kodeKaryawan);
        return inertia('LMS/Training/Catalog/Detail/detail/trainingDetail', [
            'courseId' => $materiId,
            'viewMode' => 'admin',
            'targetKodeKaryawan' => $kodeKaryawan,
            'penjadwalanId' => null,
        ]);
    }

    public function showTrainingDetailBySchedule(string $penjadwalanId, string $kodeKaryawan)
    {
        $context = $this->resolveScheduleTrainingContext($penjadwalanId, $kodeKaryawan);

        if (! $context) {
            abort(404, 'Penjadwalan training tidak ditemukan.');
        }

        return inertia('LMS/Training/Catalog/Detail/detail/trainingDetail', [
            'courseId' => Hashids::connection('custom')->encode((int) $context['materi']->Id_Materi),
            'viewMode' => 'admin',
            'targetKodeKaryawan' => $kodeKaryawan,
            'penjadwalanId' => $penjadwalanId,
        ]);
    }

    /**
     * API: Progress spesifik training per karyawan (Untuk admin detail viewer)
     */
    public function getTrainingProgressBySchedule(string $penjadwalanId, string $kodeKaryawan)
    {
        $context = $this->resolveScheduleTrainingContext($penjadwalanId, $kodeKaryawan);

        if (! $context) {
            return ResponseHelper::error('Penjadwalan training tidak ditemukan', 404);
        }

        request()->query->set('penjadwalan_id', $penjadwalanId);

        return $this->getTrainingProgress(
            Hashids::connection('custom')->encode((int) $context['materi']->Id_Materi),
            $kodeKaryawan,
        );
    }

    public function getTrainingProgress(string $materiId, string $kodeKaryawan)
    {
        try {
            $realMateriId = Hashids::connection('custom')->decode($materiId)[0] ?? (int) $materiId;
            $decryptedKode = $kodeKaryawan;

            $materi = DB::table('N_LMS_Materi')->where('Id_Materi', $realMateriId)->where('Flag_Aktif', 'Y')->first();

            if (! $materi) {
                return ResponseHelper::error('Materi tidak ditemukan', 404);
            }

            // Resolve Penjadwalan for targeted employee
            $penjadwalanId = request()->query('penjadwalan_id');
            $penjadwalan = filled($penjadwalanId)
                ? $this->resolvePenjadwalanByIdAndKodeKaryawan((string) $penjadwalanId, $realMateriId, $decryptedKode)
                : $this->resolvePenjadwalanByKodeKaryawan($realMateriId, $decryptedKode);

            if (filled($penjadwalanId) && ! $penjadwalan) {
                return ResponseHelper::error('Penjadwalan training tidak ditemukan', 404);
            }

            if ($penjadwalan) {
                $sections = DB::table('N_LMS_Penjadwalan_Detail as pd')
                    ->join('N_LMS_Materi_Detail as md', 'pd.Id_Materi_Detail', '=', 'md.Id_Materi_Detail')
                    ->where('pd.Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
                    ->where('md.Flag_Aktif', 'Y')
                    ->where('md.Id_Master_Training', $realMateriId)
                    ->orderBy('md.Urutan_Sub_Training', 'asc')
                    ->select('md.Id_Materi_Detail', 'md.Nama_Sub', 'md.Urutan_Sub_Training', 'pd.Id_Penjadwalan_Detail')
                    ->get();
            } else {
                $sections = DB::table('N_LMS_Materi_Detail')
                    ->where('Id_Master_Training', $realMateriId)
                    ->where('Flag_Aktif', 'Y')
                    ->orderBy('Urutan_Sub_Training', 'asc')
                    ->get();
            }

            $prasyaratMap = $this->getMateriDetPrerequisiteMap(
                (int) $realMateriId,
                $penjadwalan->Id_Penjadwalan ?? null,
            );
            $prasyaratLabelMap = $this->getMateriDetPrerequisiteLabelMap((int) $realMateriId, $prasyaratMap);

            $sectionsWithItems = $sections->map(function ($section) use (
                $realMateriId,
                $penjadwalan,
                $prasyaratMap,
                $prasyaratLabelMap,
            ) {
                $query = DB::table('N_LMS_Materi_Det as det')
                    ->leftJoin(
                        'N_LMS_Master_Aktivitas as akt',
                        'det.Id_Master_Aktivitas',
                        '=',
                        'akt.Id_Master_Aktivitas',
                    )
                    ->leftJoin('N_LMS_Master_Konten as kon', 'det.Id_Master_Konten', '=', 'kon.Id_Master_Konten')
                    ->leftJoin(
                        'N_LMS_Master_Konten_Berkas as berkas',
                        'kon.Id_Master_Konten',
                        '=',
                        'berkas.Id_Master_Konten',
                    )
                    ->leftJoin(
                        'N_LMS_Master_Konten_Media as media',
                        'kon.Id_Master_Konten',
                        '=',
                        'media.Id_Master_Konten',
                    );

                if ($penjadwalan && isset($section->Id_Penjadwalan_Detail)) {
                    $query->join('N_LMS_Penjadwalan_Det as pdet', function ($join) use ($section) {
                        $join
                            ->on('det.Id_Materi_Det', '=', 'pdet.Id_Materi_Det')
                            ->where('pdet.Id_Penjadwalan_Detail', '=', $section->Id_Penjadwalan_Detail);
                    });
                    $query->leftJoin(
                        'N_LMS_Penjadwalan_Integrasi as pint',
                        'pdet.Id_Penjadwalan_Det',
                        '=',
                        'pint.Id_Penjadwalan_Det',
                    );

                    $query->leftJoin('N_LMS_Transaksi_Training_Detail as trx_detail', function ($join) {
                        $join
                            ->on('trx_detail.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
                            ->on('trx_detail.Id_Materi', '=', 'det.Id_Materi')
                            ->on('trx_detail.Id_Materi_Det', '=', 'det.Id_Materi_Det');
                    });
                    $query->leftJoin(
                        'N_LMS_Transaksi_Training_Det as trx_det',
                        'trx_det.Id_Transaksi_Training_Detail',
                        '=',
                        'trx_detail.Id_Transaksi_Training_Detail',
                    );

                    $query->addSelect([
                        'pdet.Id_Penjadwalan_Det',
                        'pdet.Flag_Selesai as Det_Flag_Selesai',
                        'pdet.Flag_Checkin as Det_Flag_Checkin',
                        'pdet.Waktu_Mulai_Akses',
                        'pdet.Waktu_Batas_Akses',
                        'pint.Id_Penjadwalan_Integrasi',
                        'pint.Waktu_Mulai as Integrasi_Waktu_Mulai',
                        'pint.Waktu_Akhir as Integrasi_Waktu_Akhir',
                        'pint.Payload_Json as Integrasi_Payload_Json',
                        'pint.Id_Master_Ujian as Integrasi_Id_Master_Ujian',
                        'trx_det.Skor_Akhir',
                    ]);
                }

                $query
                    ->where('det.Id_Materi', $realMateriId)
                    ->where('det.Id_Materi_Detail', $section->Id_Materi_Detail)
                    ->where('det.Flag_Aktif', 'Y')
                    ->orderBy('det.Urutan_Materi', 'asc')
                    ->addSelect([
                        'det.Id_Materi_Det',
                        'det.Id_Materi',
                        'det.Id_Materi_Detail',
                        'det.Id_Master_Aktivitas',
                        'det.Id_Master_Konten',
                        'det.Id_Master_Rumus',
                        'det.Keterangan_Level',
                        'det.Urutan_Materi',
                        'det.Durasi',
                        'det.Flag_Download',
                        'akt.Kode_Aktivitas',
                        'akt.Nama_Aktivitas',
                        'akt.Flag_Third_Party as Aktivitas_Third_Party',
                        'kon.Id_Master_Konten as Konten_Id',
                        'kon.Judul_Konten',
                        'kon.Tipe_Konten',
                        'kon.Flag_Third_Party as Konten_Third_Party',
                        'berkas.Id_Master_Konten_Berkas',
                        'media.Provider_Media_Id',
                    ]);

                $items = $query
                    ->get()
                    ->map(function ($item) use ($penjadwalan, $section, $prasyaratMap, $prasyaratLabelMap) {
                        $konten = null;
                        if ($item->Konten_Id) {
                            $konten = [
                                'id' => Hashids::connection('custom')->encode($item->Konten_Id),
                                'judul' => $item->Judul_Konten,
                                'tipe' => $item->Tipe_Konten,
                                'flag_third_party' => $item->Konten_Third_Party,
                            ];
                        }

                        $status = 'not-started';
                        if (($item->Det_Flag_Selesai ?? '') === 'Y') {
                            $status = 'done';
                        } elseif (($item->Kode_Aktivitas ?? '') === 'ABS' && ($item->Det_Flag_Checkin ?? '') === 'Y') {
                            $status = 'done';
                        } elseif (($item->Det_Flag_Checkin ?? '') === 'Y') {
                            $status = 'in-progress';
                        }

                        $mapped = [
                            'id_materi_det' => Hashids::connection('custom')->encode($item->Id_Materi_Det),
                            'id_materi_det_syarat' => isset($prasyaratMap[$item->Id_Materi_Det])
                                ? Hashids::connection('custom')->encode($prasyaratMap[$item->Id_Materi_Det])
                                : null,
                            'label_materi_det_syarat' => $prasyaratLabelMap[$item->Id_Materi_Det] ?? null,
                            'id_materi' => Hashids::connection('custom')->encode($item->Id_Materi),
                            'id_materi_detail' => Hashids::connection('custom')->encode($item->Id_Materi_Detail),
                            'kode_aktivitas' => $item->Kode_Aktivitas,
                            'nama_aktivitas' => $item->Nama_Aktivitas,
                            'aktivitas_third_party' => $item->Aktivitas_Third_Party,
                            'konten' => $konten,
                            'keterangan_level' => $item->Keterangan_Level,
                            'urutan_materi' => $item->Urutan_Materi,
                            'durasi' => $item->Durasi,
                            'id_penjadwalan' => $penjadwalan
                                ? Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan)
                                : null,
                            'id_penjadwalan_detail' => isset($section->Id_Penjadwalan_Detail)
                                ? Hashids::connection('custom')->encode($section->Id_Penjadwalan_Detail)
                                : null,
                        ];

                        if (isset($item->Id_Penjadwalan_Det)) {
                            $mapped['id_penjadwalan_det'] = Hashids::connection('custom')->encode(
                                $item->Id_Penjadwalan_Det,
                            );
                            $mapped['status_penjadwalan'] = $status;
                            $mapped['waktu_mulai_akses'] = $item->Waktu_Mulai_Akses;
                            $mapped['waktu_batas_akses'] = $item->Waktu_Batas_Akses;
                            $mapped['flag_selesai'] = $item->Det_Flag_Selesai;
                            if (($item->Aktivitas_Third_Party ?? 'T') === 'Y') {
                                $mapped['skor_akhir'] = $item->Skor_Akhir ?? null;
                            }
                        }

                        return $mapped;
                    });

                return [
                    'id_materi_detail' => Hashids::connection('custom')->encode($section->Id_Materi_Detail),
                    'nama_sub' => $section->Nama_Sub ?? $section->Urutan_Sub_Training,
                    'urutan' => $section->Urutan_Sub_Training ?? $section->Urutan,
                    'items' => $items->values()->toArray(),
                ];
            });

            // Get Employee info
            $employee = DB::table('Karyawan')
                ->whereRaw('UPPER(LTRIM(RTRIM(Kode_Karyawan))) = ?', [strtoupper(trim($decryptedKode))])
                ->first();

            $finalScoreSummary = $this->buildFinalScoreSummary(
                (int) ($penjadwalan->Id_Penjadwalan ?? 0),
                (int) $realMateriId,
                $penjadwalan->Id_Master_Rumus ?? null,
            );
            $trainingResultSummary = $this->buildTrainingResultSummary(
                (int) ($penjadwalan->Id_Penjadwalan ?? 0),
                (int) $realMateriId,
            );
            $attendanceInsight = $this->buildAttendanceInsight(
                (int) ($penjadwalan->Id_Penjadwalan ?? 0),
                (int) $realMateriId,
            );

            return ResponseHelper::success([
                'employee_name' => $employee ? $employee->Nama : null,
                'materi' => [
                    'id' => Hashids::connection('custom')->encode($materi->Id_Materi),
                    'penjadwalan_id' => $penjadwalan
                        ? Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan)
                        : null,
                    'judul' => $materi->Judul_Training,
                    'deskripsi' => $materi->Deskripsi,
                    'outline_session' => $this->decodeMetaList($materi->Outline_Session ?? null),
                    'metode' => $this->decodeMetaList($materi->Metode ?? null),
                    'durasi' => $this->decodeMetaList($materi->Durasi ?? null),
                    'total_durasi' => $materi->Total_Durasi ?? null,
                    'sasaran_peserta' => $this->decodeMetaList($materi->Sasaran_Peserta ?? null),
                    'standar_kelulusan' => $materi->Standar_Kelulusan ?? null,
                    'tanggal' => $materi->Tanggal,
                    'thumbnail' => $this->resolveThumbnailUrl($materi->Thumbnail_Training),
                ],
                'sections' => $sectionsWithItems->values()->toArray(),
                'training_result' => $trainingResultSummary,
                'final_score_summary' => $finalScoreSummary,
                'attendance_insight' => $attendanceInsight,
            ]);
        } catch (\Exception $e) {
            Log::error('Error LmsMonitoring getTrainingProgress: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    // --- Helper Methods (Re-implemented for Modularity) ---

    private function resolvePenjadwalanByKodeKaryawan(int $materiId, string $kodeKaryawan): ?object
    {
        return $this->trainingScheduleQueryService->resolvePenjadwalanByMateriAndEmployee($materiId, $kodeKaryawan);
    }

    private function resolvePenjadwalanByIdAndKodeKaryawan(
        string $penjadwalanId,
        int $materiId,
        string $kodeKaryawan,
    ): ?object {
        $realPenjadwalanId = Hashids::connection('custom')->decode($penjadwalanId)[0] ?? (int) $penjadwalanId;
        $penjadwalan = $this->trainingScheduleQueryService->resolvePenjadwalanByIdAndEmployee(
            (int) $realPenjadwalanId,
            $kodeKaryawan,
        );

        if (
            ! $penjadwalan ||
            ! $this->trainingScheduleQueryService->penjadwalanContainsMateri(
                (int) $penjadwalan->Id_Penjadwalan,
                $materiId,
            )
        ) {
            return null;
        }

        return $penjadwalan;
    }

    private function resolveScheduleTrainingContext(string $penjadwalanId, string $kodeKaryawan): ?array
    {
        $realPenjadwalanId = Hashids::connection('custom')->decode($penjadwalanId)[0] ?? (int) $penjadwalanId;

        $decryptedKode = $kodeKaryawan;

        $penjadwalan = $this->trainingScheduleQueryService->resolvePenjadwalanByIdAndEmployee(
            (int) $realPenjadwalanId,
            $decryptedKode,
        );

        if (! $penjadwalan || empty($penjadwalan->Id_Master_Paket)) {
            return null;
        }

        $materi = $this->trainingScheduleQueryService->getFirstActiveMateriInPackageWithMetadata(
            (int) $penjadwalan->Id_Master_Paket,
        );

        if (! $materi || empty($materi->Id_Materi)) {
            return null;
        }

        return [
            'penjadwalan' => $penjadwalan,
            'materi' => $materi,
            'kode_karyawan' => $decryptedKode,
        ];
    }

    private function buildFinalScoreSummary(int $penjadwalanId, int $materiId, ?int $idMasterRumus): ?array
    {
        if ($penjadwalanId <= 0 || $materiId <= 0 || ! $idMasterRumus) {
            return null;
        }

        $formula = DB::table('N_LMS_Master_Rumus')->where('Id_Master_Rumus', $idMasterRumus)->first();

        if (! $formula) {
            return null;
        }

        preg_match_all('/\[(.*?)\]/', (string) $formula->Rumus, $matches);
        $tokens = collect($matches[1] ?? [])
            ->map(fn ($token) => strtoupper(trim((string) $token)))
            ->unique()
            ->values();

        $activityRows = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->join('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
            ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
            ->leftJoin('N_LMS_Penjadwalan_Integrasi as pint', 'pdet.Id_Penjadwalan_Det', '=', 'pint.Id_Penjadwalan_Det')
            ->leftJoin('N_LMS_Transaksi_Training as trx', function ($join) {
                $join->on('trx.Id_Penjadwalan', '=', 'pd.Id_Penjadwalan')->where('trx.Flag_Aktif', '=', 'Y');
            })
            ->leftJoin('N_LMS_Transaksi_Training_Detail as trx_detail', function ($join) {
                $join
                    ->on('trx_detail.Id_Transaksi_Training', '=', 'trx.Id_Transaksi_Training')
                    ->on('trx_detail.Id_Penjadwalan_Detail', '=', 'pd.Id_Penjadwalan_Detail')
                    ->on('trx_detail.Id_Materi', '=', 'det.Id_Materi')
                    ->on('trx_detail.Id_Materi_Det', '=', 'det.Id_Materi_Det');
            })
            ->leftJoin(
                'N_LMS_Transaksi_Training_Det as trx_det',
                'trx_det.Id_Transaksi_Training_Detail',
                '=',
                'trx_detail.Id_Transaksi_Training_Detail',
            )
            ->where('pd.Id_Penjadwalan', $penjadwalanId)
            ->where('det.Id_Materi', $materiId)
            ->select([
                'det.Id_Materi_Det',
                'akt.Kode_Aktivitas',
                'akt.Nama_Aktivitas',
                'pdet.Flag_Selesai',
                'pdet.Waktu_Batas_Akses',
                'pint.Waktu_Akhir as Integrasi_Waktu_Akhir',
                'trx_det.Skor_Akhir',
            ])
            ->get();

        $labelOverrides = [
            'ABS' => 'Absensi',
            'PRT' => 'Pre-Test',
            'PTT' => 'Post-Test',
            'QIZ' => 'Quiz',
            'ELS' => 'Evaluasi',
            'EVL' => 'Evaluasi',
            'MDL' => 'Modul',
        ];

        $components = $tokens
            ->map(function ($token) use ($activityRows, $labelOverrides) {
                $rows = $activityRows
                    ->filter(fn ($row) => strtoupper((string) $row->Kode_Aktivitas) === $token)
                    ->values();
                $label = $labelOverrides[$token] ?? $token;

                if ($rows->isEmpty()) {
                    return [
                        'token' => $token,
                        'name' => $label,
                        'value' => null,
                        'display_value' => '-',
                        'ready' => false,
                        'tooltip' => "{$label}: komponen ini tidak ditemukan pada training.",
                    ];
                }

                if (in_array($token, ['ABS', 'MDL'], true)) {
                    $total = $rows->count();
                    $completed = $rows->where('Flag_Selesai', 'Y')->count();
                    $ratio = $total > 0 ? round($completed / $total, 4) : null;

                    return [
                        'token' => $token,
                        'name' => $label,
                        'value' => $ratio,
                        'display_value' => $ratio === null ? '-' : number_format($ratio, 2),
                        'ready' => $total > 0,
                        'tooltip' => "{$label}: {$completed}/{$total} aktivitas selesai, nilai ".
                            ($ratio === null ? '-' : number_format($ratio, 2)),
                    ];
                }

                $total = $rows->count();
                $scores = $rows->map(fn ($row) => $row->Skor_Akhir !== null ? (float) $row->Skor_Akhir : null);
                $scoredCount = $scores->filter(fn ($score) => $score !== null)->count();

                if ($scoredCount > 0) {
                    $normalizedScores = $scores->map(fn ($score) => $score !== null ? (float) $score : 0.0);
                    $averageScore = $total > 0 ? round($normalizedScores->sum() / $total, 2) : null;

                    return [
                        'token' => $token,
                        'name' => $label,
                        'value' => $averageScore,
                        'display_value' => $averageScore === null ? '-' : number_format($averageScore, 2),
                        'ready' => $scoredCount === $total,
                        'tooltip' => "{$label}: {$scoredCount}/{$total} skor tersedia, nilai ".
                            ($averageScore === null ? '-' : number_format($averageScore, 2)),
                    ];
                }

                $completed = $rows->where('Flag_Selesai', 'Y')->count();
                $ratio = $total > 0 ? round($completed / $total, 4) : null;

                return [
                    'token' => $token,
                    'name' => $label,
                    'value' => $ratio,
                    'display_value' => $ratio === null ? '-' : number_format($ratio, 2),
                    'ready' => $total > 0 && $completed === $total,
                    'tooltip' => "{$label}: belum ada skor akhir, memakai rasio penyelesaian {$completed}/{$total} dengan nilai ".
                        ($ratio === null ? '-' : number_format($ratio, 2)),
                ];
            })
            ->values();

        $allComponentsReady = $components->every(fn ($component) => $component['ready'] === true);
        $finalScore = null;
        $status = 'pending';

        if ($allComponentsReady) {
            $resolvedFormula = (string) $formula->Rumus;

            foreach ($components as $component) {
                $resolvedFormula = str_replace(
                    '['.$component['token'].']',
                    (string) ($component['value'] ?? 0),
                    $resolvedFormula,
                );
            }

            $finalScore = PenilaiEkspresiNumerik::nilai($resolvedFormula);
            $status = 'ready';
        }

        $translatedFormula = preg_replace_callback(
            '/\[(.*?)\]/',
            function ($matches) use ($components) {
                $token = strtoupper(trim((string) ($matches[1] ?? '')));
                $component = $components->firstWhere('token', $token);

                return $component['name'] ?? $token;
            },
            (string) $formula->Rumus,
        );

        return [
            'nama_formula' => $formula->Nama_Formula,
            'translated_formula' => $translatedFormula,
            'components' => $components->all(),
            'final_score' => $finalScore !== null ? round((float) $finalScore, 2) : null,
            'final_score_display' => $finalScore !== null ? number_format((float) $finalScore, 2) : null,
            'status' => $status,
            'status_label' => $allComponentsReady ? 'Nilai akhir tersedia' : 'Menunggu komponen lengkap',
        ];
    }

    private function buildAttendanceSummary(int $penjadwalanId): array
    {
        if ($penjadwalanId <= 0) {
            return [
                'total' => 0,
                'checked_in' => 0,
                'status' => 'not-required',
                'label' => 'Absensi tidak tersedia',
            ];
        }

        $attendanceRows = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->join('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
            ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
            ->where('pd.Id_Penjadwalan', $penjadwalanId)
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(akt.Kode_Aktivitas, '')))) = 'ABS'")
            ->select('pdet.Flag_Checkin')
            ->get();

        $total = $attendanceRows->count();
        $checkedIn = $attendanceRows
            ->filter(fn ($row) => strtoupper((string) ($row->Flag_Checkin ?? '')) === 'Y')
            ->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'checked_in' => 0,
                'status' => 'not-required',
                'label' => 'Absensi tidak tersedia',
            ];
        }

        if ($checkedIn === 0) {
            $status = 'not-started';
            $label = 'Belum check-in';
        } elseif ($checkedIn < $total) {
            $status = 'partial';
            $label = "Check-in {$checkedIn}/{$total}";
        } else {
            $status = 'completed';
            $label = "Hadir {$checkedIn}/{$total}";
        }

        return [
            'total' => $total,
            'checked_in' => $checkedIn,
            'status' => $status,
            'label' => $label,
        ];
    }

    private function buildAttendanceInsight(int $penjadwalanId, int $materiId): array
    {
        if ($penjadwalanId <= 0 || $materiId <= 0) {
            return [
                'total_required' => 0,
                'total_checked_in' => 0,
                'completion_percent' => 0,
                'has_checkin_component' => false,
                'has_checkout_component' => false,
                'has_full_attendance_pair' => false,
                'status' => 'not-required',
                'status_label' => 'Absensi tidak tersedia',
                'records' => [],
            ];
        }

        $rows = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->join('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
            ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
            ->leftJoin('N_LMS_Penjadwalan_Integrasi as pint', 'pdet.Id_Penjadwalan_Det', '=', 'pint.Id_Penjadwalan_Det')
            ->where('pd.Id_Penjadwalan', $penjadwalanId)
            ->where('det.Id_Materi', $materiId)
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(akt.Kode_Aktivitas, '')))) = 'ABS'")
            ->select([
                'det.Id_Materi_Det',
                'det.Keterangan_Level',
                'pd.Id_Penjadwalan_Detail',
                'pdet.Flag_Checkin',
                'pdet.Waktu_Mulai_Akses',
                'pdet.Waktu_Batas_Akses',
                'pint.Waktu_Mulai as Integrasi_Waktu_Mulai',
                'pint.Waktu_Akhir as Integrasi_Waktu_Akhir',
                'pint.Payload_Json as Integrasi_Payload_Json',
            ])
            ->orderBy('det.Urutan_Materi', 'asc')
            ->get();

        $detailIds = $rows->pluck('Id_Penjadwalan_Detail')->filter()->unique()->values()->all();

        $checkinoutByDetail = collect();
        $checkinoutGlobal = collect();
        if (! empty($detailIds)) {
            $checkinoutRows = DB::table('N_LMS_CHECKINOUT')
                ->where('Id_Penjadwalan', $penjadwalanId)
                ->whereIn('Id_Penjadwalan_Detail', $detailIds)
                ->orderBy('Checktime', 'asc')
                ->select(['Id_Penjadwalan_Detail', 'Checktime', 'Output', 'File_Path', 'Latitude', 'Longitude'])
                ->get();

            $checkinoutByDetail = $checkinoutRows->groupBy('Id_Penjadwalan_Detail');
        }

        // Fallback global when detail-level records are not stored
        $checkinoutGlobal = DB::table('N_LMS_CHECKINOUT')
            ->where('Id_Penjadwalan', $penjadwalanId)
            ->orderBy('Checktime', 'asc')
            ->select(['Id_Penjadwalan_Detail', 'Checktime', 'Output', 'File_Path', 'Latitude', 'Longitude'])
            ->get();

        $globalOutputs = $checkinoutGlobal
            ->map(fn ($row) => strtoupper(trim((string) ($row->Output ?? ''))))
            ->filter()
            ->unique();
        $hasCheckinComponent = $globalOutputs->contains('IN');
        $hasCheckoutComponent = $globalOutputs->contains('OUT');
        $hasFullAttendancePair = $hasCheckinComponent && $hasCheckoutComponent;

        $records = $rows
            ->map(function ($row) use ($checkinoutByDetail, $checkinoutGlobal) {
                $payload = $this->decodeIntegrasiPayload($row->Integrasi_Payload_Json ?? null);

                $logs = collect();
                if (! empty($row->Id_Penjadwalan_Detail) && $checkinoutByDetail->has($row->Id_Penjadwalan_Detail)) {
                    $logs = collect($checkinoutByDetail->get($row->Id_Penjadwalan_Detail));
                }
                if ($logs->isEmpty()) {
                    $logs = $checkinoutGlobal;
                }

                $checkinLog = $logs
                    ->filter(fn ($log) => strtoupper((string) ($log->Output ?? '')) === 'IN')
                    ->sortBy('Checktime')
                    ->first();
                $checkoutLog = $logs
                    ->filter(fn ($log) => strtoupper((string) ($log->Output ?? '')) === 'OUT')
                    ->sortByDesc('Checktime')
                    ->first();

                $latitudeFromCheckin = $checkinLog?->Latitude !== null ? (string) $checkinLog->Latitude : null;
                $longitudeFromCheckin = $checkinLog?->Longitude !== null ? (string) $checkinLog->Longitude : null;

                $latitude = $this->extractPayloadValue($payload, [
                    'Latitude',
                    'Lat',
                    'lat',
                    'location_latitude',
                    'gps_lat',
                ]);
                $longitude = $this->extractPayloadValue($payload, [
                    'Longitude',
                    'Lng',
                    'lon',
                    'location_longitude',
                    'gps_lng',
                ]);

                $latitude = $latitudeFromCheckin ?: $latitude;
                $longitude = $longitudeFromCheckin ?: $longitude;

                $lokasi = $this->extractPayloadValue($payload, [
                    'Lokasi',
                    'Location',
                    'Address',
                    'Alamat',
                    'location_name',
                    'location_address',
                ]);

                if (! $lokasi && $latitude && $longitude) {
                    $lokasi = "{$latitude}, {$longitude}";
                }

                $fotoFromCheckin = $checkinLog?->File_Path ? (string) $checkinLog->File_Path : null;
                $fotoBuktiRaw = $this->extractPayloadValue($payload, [
                    'Foto',
                    'Photo',
                    'Image',
                    'Selfie',
                    'Bukti_Foto',
                    'Foto_Checkin',
                    'foto_url',
                    'photo_url',
                    'image_url',
                    'selfie_url',
                    'url_foto',
                    'checkin_photo',
                    'checkin_image',
                ]);
                $fotoBukti = $this->resolveEvidenceAssetUrl($fotoFromCheckin ?: $fotoBuktiRaw);

                $status = strtoupper((string) ($row->Flag_Checkin ?? '')) === 'Y' ? 'checked-in' : 'not-checked-in';

                return [
                    'id_materi_det' => Hashids::connection('custom')->encode($row->Id_Materi_Det),
                    'label' => $row->Keterangan_Level ?: 'Absensi',
                    'status' => $status,
                    'status_label' => $status === 'checked-in' ? 'Sudah Check-in' : 'Belum Check-in',
                    'waktu_checkin' => $checkinLog?->Checktime ?? ($row->Integrasi_Waktu_Mulai ?? $row->Waktu_Mulai_Akses),
                    'waktu_checkout' => $checkoutLog?->Checktime ?? ($row->Integrasi_Waktu_Akhir ?? null),
                    'has_checkin_log' => $checkinLog !== null,
                    'has_checkout_log' => $checkoutLog !== null,
                    'batas_akses' => $row->Waktu_Batas_Akses,
                    'lokasi' => $lokasi,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'foto_bukti' => $fotoBukti,
                    'koordinat' => $latitude && $longitude ? "{$latitude}, {$longitude}" : null,
                ];
            })
            ->values();

        $totalRequired = $records->count();
        $totalCheckedIn = $records->where('status', 'checked-in')->count();
        $completionPercent = $totalRequired > 0 ? (int) round(($totalCheckedIn / $totalRequired) * 100) : 0;

        $status = 'not-required';
        $statusLabel = 'Absensi tidak tersedia';
        if ($totalRequired > 0) {
            if ($totalCheckedIn === 0) {
                $status = 'not-started';
                $statusLabel = 'Belum ada check-in';
            } elseif ($totalCheckedIn < $totalRequired) {
                $status = 'partial';
                $statusLabel = "Check-in {$totalCheckedIn}/{$totalRequired}";
            } else {
                $status = 'completed';
                $statusLabel = "Semua absensi lengkap ({$totalCheckedIn}/{$totalRequired})";
            }
        }

        return [
            'total_required' => $totalRequired,
            'total_checked_in' => $totalCheckedIn,
            'completion_percent' => $completionPercent,
            'has_checkin_component' => $hasCheckinComponent,
            'has_checkout_component' => $hasCheckoutComponent,
            'has_full_attendance_pair' => $hasFullAttendancePair,
            'status' => $status,
            'status_label' => $statusLabel,
            'records' => $records->all(),
        ];
    }

    private function decodeIntegrasiPayload(?string $payloadJson): array
    {
        if (! filled($payloadJson)) {
            return [];
        }

        $decoded = json_decode($payloadJson, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function extractPayloadValue(array $payload, array $candidateKeys): ?string
    {
        if (empty($payload)) {
            return null;
        }

        $flat = [];
        $this->flattenPayload($payload, $flat);
        if (empty($flat)) {
            return null;
        }

        foreach ($candidateKeys as $candidate) {
            $key = strtolower((string) $candidate);
            if (array_key_exists($key, $flat) && $flat[$key] !== '') {
                return $flat[$key];
            }
        }

        return null;
    }

    private function flattenPayload(array $payload, array &$flat): void
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $this->flattenPayload($value, $flat);

                continue;
            }

            if (is_object($value)) {
                $this->flattenPayload((array) $value, $flat);

                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $normalizedKey = strtolower(trim((string) $key));
            if ($normalizedKey === '') {
                continue;
            }

            $flat[$normalizedKey] = trim((string) $value);
        }
    }

    private function resolveEvidenceAssetUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        try {
            return Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(20));
        } catch (\Throwable $e) {
            if (Str::startsWith($path, ['/'])) {
                return url($path);
            }

            return url('/'.ltrim($path, '/'));
        }
    }

    private function buildFinalScoreTooltip(?array $summary): ?string
    {
        if (! $summary || empty($summary['final_score_display'])) {
            return null;
        }

        $parts = collect($summary['components'] ?? [])
            ->map(function ($component) {
                return ($component['name'] ?? ($component['token'] ?? 'Komponen')).
                    ': '.
                    ($component['display_value'] ?? '-');
            })
            ->filter()
            ->values()
            ->all();

        return empty($parts) ? null : implode(' | ', $parts);
    }

    private function buildTrainingResultSummary(int $penjadwalanId, ?int $materiId = null): ?array
    {
        if ($penjadwalanId <= 0) {
            return null;
        }

        $transaction = DB::table('N_LMS_Transaksi_Training as trx')
            ->leftJoin(
                'N_LMS_Master_Keterangan_Kelulusan as kel',
                'trx.Id_Keterangan_Kelulusan',
                '=',
                'kel.Id_Keterangan_Kelulusan',
            )
            ->where('trx.Id_Penjadwalan', $penjadwalanId)
            ->where('trx.Flag_Aktif', 'Y')
            ->select([
                'trx.Total_Score',
                'trx.Score_Learning_Gain',
                'trx.Id_Keterangan_Kelulusan',
                'kel.Kode_Keterangan',
                'kel.Nama_Keterangan',
            ])
            ->first();

        if (! $transaction) {
            return null;
        }

        $standarKelulusanValue = null;
        if ($materiId && $materiId > 0) {
            $standarKelulusan = DB::table('N_LMS_Materi')->where('Id_Materi', $materiId)->value('Standar_Kelulusan');
            $standarKelulusanValue = $standarKelulusan !== null ? (float) $standarKelulusan : null;
        }

        $totalScore = $transaction->Total_Score !== null ? (float) $transaction->Total_Score : null;
        $learningGain = $transaction->Score_Learning_Gain !== null ? (float) $transaction->Score_Learning_Gain : null;
        $kelulusanStatus = 'unknown';

        if (
            (int) ($transaction->Kode_Keterangan ?? 0) === 1 ||
            strtolower((string) ($transaction->Nama_Keterangan ?? '')) === 'lulus'
        ) {
            $kelulusanStatus = 'passed';
        } elseif (
            (int) ($transaction->Kode_Keterangan ?? 0) === 2 ||
            strtolower((string) ($transaction->Nama_Keterangan ?? '')) === 'tidak lulus'
        ) {
            $kelulusanStatus = 'failed';
        } elseif ($standarKelulusanValue !== null && $totalScore !== null) {
            $kelulusanStatus = $totalScore >= $standarKelulusanValue ? 'passed' : 'failed';
        }

        if ($totalScore === null && $learningGain === null) {
            return null;
        }

        $komposisi = app(\App\Services\LMS\TrainingExecutionService::class)
            ->komposisiTes($penjadwalanId, $materiId);

        return [
            'total_score' => $totalScore !== null ? round($totalScore, 2) : null,
            'total_score_display' => $totalScore !== null ? number_format($totalScore, 2) : null,
            'score_learning_gain' => $learningGain !== null ? round($learningGain, 2) : null,
            'score_learning_gain_display' => $learningGain !== null ? number_format($learningGain, 2) : null,
            'id_keterangan_kelulusan' => $transaction->Id_Keterangan_Kelulusan ?? null,
            'kode_keterangan' => $transaction->Kode_Keterangan ?? null,
            'keterangan_kelulusan' => $transaction->Nama_Keterangan ?? null,
            'kelulusan_status' => $kelulusanStatus,
            'standar_kelulusan' => $standarKelulusanValue,
            // "Total_Score terisi" bukan lagi bukti adanya post-test: training
            // ber-pre-test-saja pun sekarang punya nilai akhir.
            'has_post_test' => $komposisi['ada_post_test'],
            'has_pre_test' => $komposisi['ada_pre_test'],
            'sumber_nilai' => $komposisi['sumber_nilai'],
        ];
    }

    private function getMateriDetPrerequisiteMap(int $materiId, ?int $penjadwalanId = null): array
    {
        try {
            $activeItemIds = DB::table('N_LMS_Materi_Det')
                ->where('Id_Materi', $materiId)
                ->where('Flag_Aktif', 'Y')
                ->pluck('Id_Materi_Det')
                ->toArray();

            if (empty($activeItemIds)) {
                return [];
            }

            $validItemIds = array_fill_keys($activeItemIds, true);

            if ($penjadwalanId && SkemaTersedia::tabel('N_LMS_Penjadwalan_Syarat')) {
                $snapshotRows = DB::table('N_LMS_Penjadwalan_Syarat as ps')
                    ->join(
                        'N_LMS_Penjadwalan_Detail as pd',
                        'ps.Id_Penjadwalan_Detail',
                        '=',
                        'pd.Id_Penjadwalan_Detail',
                    )
                    ->where('pd.Id_Penjadwalan', $penjadwalanId)
                    ->whereIn('ps.Id_Materi_Det', $activeItemIds)
                    ->when(
                        SkemaTersedia::kolom('N_LMS_Penjadwalan_Syarat', 'Flag_Aktif'),
                        function ($query) {
                            $query->where('ps.Flag_Aktif', 'Y');
                        },
                    )
                    ->select('ps.Id_Materi_Det', 'ps.Id_Materi_Det_Syarat')
                    ->get();

                if ($snapshotRows->isNotEmpty()) {
                    $map = [];
                    foreach ($snapshotRows as $syarat) {
                        if (
                            filled($syarat->Id_Materi_Det_Syarat) &&
                            ! isset($validItemIds[(int) $syarat->Id_Materi_Det_Syarat])
                        ) {
                            continue;
                        }
                        $map[$syarat->Id_Materi_Det] = filled($syarat->Id_Materi_Det_Syarat)
                            ? (int) $syarat->Id_Materi_Det_Syarat
                            : null;
                    }

                    return $map;
                }
            }

            $syarats = DB::table('N_LMS_Materi_Det_Syarat as sy')
                ->join('N_LMS_Materi_Det as current', 'sy.Id_Materi_Det', '=', 'current.Id_Materi_Det')
                ->where('current.Id_Materi', $materiId)
                ->where('current.Flag_Aktif', 'Y')
                ->select('sy.Id_Materi_Det', 'sy.Id_Materi_Det_Syarat')
                ->get();

            $map = [];
            foreach ($syarats as $syarat) {
                if (! isset($validItemIds[$syarat->Id_Materi_Det_Syarat])) {
                    continue;
                }
                $map[$syarat->Id_Materi_Det] = $syarat->Id_Materi_Det_Syarat;
            }

            return $map;
        } catch (\Exception $e) {
            // Table may not exist yet — gracefully return empty
            Log::warning('getMateriDetPrerequisiteMap: '.$e->getMessage());

            return [];
        }
    }

    private function getMateriDetPrerequisiteLabelMap(int $materiId, array $prasyaratMap): array
    {
        if (empty($prasyaratMap)) {
            return [];
        }

        $reqIds = array_values(array_unique($prasyaratMap));
        $syaratDetails = DB::table('N_LMS_Materi_Det as md')
            ->join('N_LMS_Master_Aktivitas as ma', 'md.Id_Master_Aktivitas', '=', 'ma.Id_Master_Aktivitas')
            ->leftJoin('N_LMS_Master_Konten as mk', 'md.Id_Master_Konten', '=', 'mk.Id_Master_Konten')
            ->whereIn('md.Id_Materi_Det', $reqIds)
            ->select([
                'md.Id_Materi_Det',
                'md.Keterangan_Level',
                'ma.Kode_Aktivitas',
                'ma.Nama_Aktivitas',
                'mk.Judul_Konten',
            ])
            ->get()
            ->keyBy('Id_Materi_Det');

        $labelMap = [];
        foreach ($prasyaratMap as $id_materi_det => $id_syarat) {
            if (isset($syaratDetails[$id_syarat])) {
                $item = $syaratDetails[$id_syarat];
                $labelMap[$id_materi_det] = $item->Keterangan_Level ?: ($item->Judul_Konten ?: $item->Nama_Aktivitas);
            } else {
                $labelMap[$id_materi_det] = 'Materi sebelumnya';
            }
        }

        return $labelMap;
    }

    private function decodeMetaList(?string $value): array
    {
        if (! filled($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return collect($decoded)->map(fn ($entry) => trim((string) $entry))->filter()->values()->all();
        }

        return collect(preg_split('/\r?\n|;/', (string) $value) ?: [])
            ->map(fn ($entry) => trim((string) $entry))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveThumbnailUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (! Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            try {
                return Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(20));
            } catch (\Throwable $e) {
                return null;
            }
        }

        return $path;
    }

    private function normalizeTestAnswerPayload($payload): array
    {
        $arrayPayload = is_array($payload) ? $payload : (is_object($payload) ? (array) $payload : []);

        $jawabanPeserta = $arrayPayload['jawaban_peserta'] ?? [];
        if (! is_array($jawabanPeserta)) {
            $jawabanPeserta = [];
        }

        $questionRows = $jawabanPeserta;

        $summary = [
            'score' => $this->findFirstScalar($arrayPayload, ['Nilai_Akhir', 'nilai_akhir', 'Final_Score', 'score']),
            'correct' => $this->findFirstScalar($arrayPayload, ['Benar', 'Jumlah_Benar', 'total_benar', 'correct']),
            'wrong' => $this->findFirstScalar($arrayPayload, ['Salah', 'Jumlah_Salah', 'total_salah', 'wrong']),
            'empty' => $this->findFirstScalar($arrayPayload, [
                'Kosong',
                'Tidak_Dijawab',
                'jumlah_kosong',
                'unanswered',
            ]),
            'total' => $this->findFirstScalar($arrayPayload, ['Total_Soal', 'jumlah_soal', 'total_question']),
            'status' => $this->findFirstScalar($arrayPayload, ['Status', 'status']),
        ];

        if (! $summary['total']) {
            $summary['total'] = count($questionRows);
        }

        if ($summary['correct'] === null) {
            $summary['correct'] = collect($questionRows)
                ->filter(function ($row) {
                    $item = is_array($row) ? $row : (is_object($row) ? (array) $row : []);
                    $scoreCandidate = $item['score_kandidat'] ?? null;
                    $scoreMax = $item['max_score'] ?? null;

                    if (is_numeric($scoreCandidate) && is_numeric($scoreMax)) {
                        return (float) $scoreCandidate >= (float) $scoreMax;
                    }

                    return false;
                })
                ->count();
        }

        if ($summary['wrong'] === null && is_numeric($summary['total']) && is_numeric($summary['correct'])) {
            $summary['wrong'] = max((int) $summary['total'] - (int) $summary['correct'], 0);
        }

        // Hitung empty (tidak dijawab) dari questions jika belum ada
        if ($summary['empty'] === null) {
            $emptyCount = collect($questionRows)
                ->filter(function ($row) {
                    $item = is_array($row) ? $row : (is_object($row) ? (array) $row : []);
                    $jawabanPengguna = $item['jawaban_pengguna'] ?? null;
                    $userAnswer = $jawabanPengguna['jawaban'] ?? null;
                    $userAnswerStatus = $jawabanPengguna['status'] ?? null;

                    // Soal dianggap kosong jika user tidak pilih jawaban dan tidak ada status jawaban
                    return ! $userAnswer && ! $userAnswerStatus;
                })
                ->count();

            $summary['empty'] = $emptyCount > 0 ? $emptyCount : 0;
        }

        $questions = collect($questionRows)
            ->map(function ($row, $index) {
                $item = is_array($row) ? $row : (is_object($row) ? (array) $row : []);

                $number = $this->pickValue($item, ['No', 'No_Soal', 'Nomor', 'nomor', 'number']);
                $questionText = $this->pickValue($item, [
                    'pertanyaan',
                    'Soal',
                    'Pertanyaan',
                    'question',
                    'Question_Text',
                ]);

                $jawabanPengguna = $item['jawaban_pengguna'] ?? null;
                if (is_object($jawabanPengguna)) {
                    $jawabanPengguna = (array) $jawabanPengguna;
                }
                $answerUser = $jawabanPengguna['jawaban'] ?? ($jawabanPengguna['status'] ?? null);

                $answerKey = $this->pickValue($item, ['Jawaban_Benar', 'Kunci_Jawaban', 'correct_answer']);
                if (! $answerKey && isset($item['jawaban_benar'])) {
                    $answerKey = $item['jawaban_benar'];
                }

                $scoreCandidate = $item['score_kandidat'] ?? null;
                $scoreMax = $item['max_score'] ?? null;
                $status = $this->pickValue($item, ['Status', 'status', 'Hasil']);
                if (! $status && is_numeric($scoreCandidate) && is_numeric($scoreMax)) {
                    $status = (float) $scoreCandidate >= (float) $scoreMax ? 'Benar' : 'Salah';
                }

                $score = $item['score_kandidat'] ?? $this->pickValue($item, ['Skor', 'Score', 'Nilai']);

                $imageAnswer = $this->pickValue($item, [
                    'Gambar_Jawaban',
                    'gambar_jawaban',
                    'Image_Answer',
                    'Answer_Image',
                    'url_gambar_jawaban',
                ]);
                if (! $imageAnswer && is_array($jawabanPengguna)) {
                    $imageAnswer = $jawabanPengguna['File_Berkas_Jawaban'] ?? null;
                }

                $imageQuestion = $this->pickValue($item, [
                    'Gambar_Soal',
                    'gambar_soal',
                    'Image_Question',
                    'Question_Image',
                    'url_gambar_soal',
                ]);
                if (! $imageQuestion) {
                    $imageQuestion = $item['File_Berkas_Pertanyaan'] ?? null;
                }

                $choices = $item['pilihan_jawaban'] ?? [];
                if (! is_array($choices)) {
                    $choices = [];
                }

                $normalizedChoices = collect($choices)
                    ->map(function ($choice) {
                        $choiceArray = is_array($choice) ? $choice : (is_object($choice) ? (array) $choice : []);

                        return [
                            'status' => $choiceArray['status'] ?? null,
                            'answer' => $choiceArray['jawaban'] ?? null,
                            'weight' => $choiceArray['bobot'] ?? null,
                            'is_correct' => isset($choiceArray['bobot']) && (float) $choiceArray['bobot'] > 0,
                            'file_url' => $this->normalizeExternalAssetUrl($choiceArray['File_Berkas_Jawaban'] ?? null),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'number' => $number !== null ? (string) $number : (string) ($index + 1),
                    'question' => $questionText,
                    'user_answer' => $answerUser,
                    'user_answer_status' => $jawabanPengguna['status'] ?? null,
                    'correct_answer' => $answerKey,
                    'status' => $status,
                    'score' => $score,
                    'max_score' => $scoreMax,
                    'score_correct_answer' => $item['skor_jawaban_benar'] ?? null,
                    'choices' => $normalizedChoices,
                    'answer_image_url' => $this->normalizeExternalAssetUrl($imageAnswer),
                    'question_image_url' => $this->normalizeExternalAssetUrl($imageQuestion),
                    'correct_answer_image_url' => $this->normalizeExternalAssetUrl(
                        $item['File_Berkas_Jawaban_Benar'] ?? null,
                    ),
                    'raw' => $item,
                ];
            })
            ->values()
            ->all();

        return [
            'summary' => $summary,
            'questions' => $questions,
            'raw' => $arrayPayload,
        ];
    }

    private function extractQuestionRows(array $payload): array
    {
        $directCandidates = [
            'detail_soal',
            'detail_jawaban',
            'soal',
            'questions',
            'data_soal',
            'jawaban',
            'items',
            'result',
        ];

        foreach ($directCandidates as $candidate) {
            if (! array_key_exists($candidate, $payload)) {
                continue;
            }

            $candidateValue = $payload[$candidate];
            if ($this->isQuestionList($candidateValue)) {
                return is_array($candidateValue) ? $candidateValue : [];
            }
        }

        if ($this->isQuestionList($payload)) {
            return $payload;
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $nested = $this->extractQuestionRows($value);
                if (! empty($nested)) {
                    return $nested;
                }
            }
        }

        return [];
    }

    private function isQuestionList($value): bool
    {
        if (! is_array($value) || empty($value)) {
            return false;
        }

        $first = $value[array_key_first($value)] ?? null;
        if (! is_array($first) && ! is_object($first)) {
            return false;
        }

        $firstArray = is_array($first) ? $first : (array) $first;
        $keys = collect(array_keys($firstArray))->map(fn ($key) => strtolower((string) $key))->all();

        foreach (['soal', 'pertanyaan', 'question', 'jawaban', 'jawaban_user', 'answer'] as $needle) {
            if (in_array($needle, $keys, true)) {
                return true;
            }
        }

        return false;
    }

    private function findFirstScalar(array $payload, array $keys)
    {
        foreach ($keys as $key) {
            $value = $this->pickValue($payload, [$key]);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $nested = $this->findFirstScalar($value, $keys);
                if ($nested !== null && $nested !== '') {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function pickValue(array $row, array $candidates)
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $row)) {
                return $row[$candidate];
            }
        }

        $lowerMap = [];
        foreach ($row as $key => $value) {
            $lowerMap[strtolower((string) $key)] = $value;
        }

        foreach ($candidates as $candidate) {
            $lower = strtolower((string) $candidate);
            if (array_key_exists($lower, $lowerMap)) {
                return $lowerMap[$lower];
            }
        }

        return null;
    }

    private function normalizeExternalAssetUrl($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        if (Str::startsWith($stringValue, ['http://', 'https://', 'data:'])) {
            return $stringValue;
        }

        return null;
    }

    // =====================================================================
    // EXPORT DATA TRAINING (modal + dispatch job + progress polling)
    // =====================================================================

    /**
     * Subquery Kode_Karyawan yang punya penjadwalan training cocok dengan filter
     * training-level (nama training / kategori / periode tanggal). Dipakai untuk
     * membatasi list karyawan agar hanya menampilkan yang ikut training tsb.
     */
    /**
     * Kolom tanggal periode untuk filter export sesuai mode:
     * 'created' => Created_At (tanggal ditugaskan), selain itu Waktu_Enroll.
     */
    private function periodeColumn(Request $request, string $alias = ''): string
    {
        $col = $request->query('periode_mode', 'enroll') === 'created' ? 'Created_At' : 'Waktu_Enroll';

        return $alias !== '' ? "{$alias}.{$col}" : $col;
    }

    /**
     * Ambil nilai filter multi-pilih dari request (bisa dikirim sebagai array
     * `key[]=a&key[]=b` maupun CSV `key=a,b`) menjadi array string bersih & unik.
     * Mengembalikan [] bila kosong.
     */
    private function multiValues(Request $request, string $key): array
    {
        $raw = $request->query($key, []);
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);

        return collect($values)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function trainingMatchedKodeSubquery(Request $request)
    {
        $namaTraining = $this->multiValues($request, 'nama_training');
        $kategori = $this->multiValues($request, 'kategori');
        $start = trim((string) $request->query('periode_start', ''));
        $end = trim((string) $request->query('periode_end', ''));

        $sub = DB::table('N_LMS_Penjadwalan as p2')
            ->join('N_LMS_Master_Paket_Detail as mpd', 'p2.Id_Master_Paket', '=', 'mpd.Id_Master_Paket')
            ->join('N_LMS_Materi as m', 'mpd.Id_Master_Training', '=', 'm.Id_Materi')
            ->leftJoin('N_LMS_Training_Kategori as tk', 'm.Id_Training_Kategori', '=', 'tk.Id_Training_Kategori')
            ->where('m.Flag_Aktif', 'Y')
            ->select('p2.Kode_Karyawan');

        $this->trainingScheduleQueryService->applyActiveFilter($sub, 'p2');

        if (! empty($namaTraining)) {
            $sub->whereIn('m.Judul_Training', $namaTraining);
        }
        if (! empty($kategori)) {
            $sub->whereIn(DB::raw("COALESCE(tk.Nama_Kategori, 'General')"), $kategori);
        }
        if ($start !== '') {
            $sub->whereDate($this->periodeColumn($request, 'p2'), '>=', $start);
        }
        if ($end !== '') {
            $sub->whereDate($this->periodeColumn($request, 'p2'), '<=', $end);
        }

        return $sub;
    }

    private function hasTrainingLevelFilter(Request $request): bool
    {
        return ! empty($this->multiValues($request, 'nama_training')) ||
            ! empty($this->multiValues($request, 'kategori')) ||
            $request->filled('periode_start') ||
            $request->filled('periode_end');
    }

    /**
     * Batasi query penjadwalan (alias 'p') hanya ke baris yang paketnya memuat materi
     * yang cocok dengan filter training-level (nama training / kategori / periode).
     * Dipakai bersama oleh ringkasan per-karyawan dan endpoint "pilih semua" agar
     * himpunan Id_Penjadwalan yang dipilih selalu identik.
     */
    private function applyExportTrainingExists($query, Request $request): void
    {
        $query->whereExists(function ($sub) use ($request) {
            $namaTraining = $this->multiValues($request, 'nama_training');
            $kategori = $this->multiValues($request, 'kategori');
            $start = trim((string) $request->query('periode_start', ''));
            $end = trim((string) $request->query('periode_end', ''));

            $sub->from('N_LMS_Master_Paket_Detail as mpd')
                ->join('N_LMS_Materi as m', 'mpd.Id_Master_Training', '=', 'm.Id_Materi')
                ->leftJoin('N_LMS_Training_Kategori as tk', 'm.Id_Training_Kategori', '=', 'tk.Id_Training_Kategori')
                ->whereColumn('mpd.Id_Master_Paket', 'p.Id_Master_Paket')
                ->where('m.Flag_Aktif', 'Y');

            if (! empty($namaTraining)) {
                $sub->whereIn('m.Judul_Training', $namaTraining);
            }
            if (! empty($kategori)) {
                $sub->whereIn(DB::raw("COALESCE(tk.Nama_Kategori, 'General')"), $kategori);
            }
            if ($start !== '') {
                $sub->whereDate($this->periodeColumn($request, 'p'), '>=', $start);
            }
            if ($end !== '') {
                $sub->whereDate($this->periodeColumn($request, 'p'), '<=', $end);
            }
        });
    }

    /**
     * API: List karyawan untuk modal export (mirip getEmployeeList tapi dengan
     * filter penuh + training-level). Angka berlangsung/selesai dihitung dari
     * training yang lolos filter agar konsisten dengan hasil export.
     */
    public function getExportEmployees(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 15);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('q', '');

            $query = DB::table('Karyawan as k')->whereIn(
                'k.Kode_Karyawan',
                $this->trainingScheduleQueryService->activeKodeKaryawanSubQuery(),
            );

            $query
                ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as lvl',
                    'k.ID_Level_Jabatan',
                    '=',
                    'lvl.ID_Level_Jabatan',
                );

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('k.Nama', 'LIKE', '%'.$search.'%')->orWhere(
                        'k.Kode_Karyawan',
                        'LIKE',
                        '%'.$search.'%',
                    );
                });
            }

            // Filter employee-level.
            $lokasi = $this->multiValues($request, 'lokasi');
            if (! empty($lokasi)) {
                $query->whereIn('k.Cabang_Untuk_Gaji', $lokasi);
            }
            $dept = $this->multiValues($request, 'dept');
            if (! empty($dept)) {
                $query->whereIn('div.nama_sub_divisi', $dept);
            }
            $divisi = $this->multiValues($request, 'divisi');
            if (! empty($divisi)) {
                $query->whereIn('div.nama_divisi', $divisi);
            }
            $level = $this->multiValues($request, 'level');
            if (! empty($level)) {
                $query->whereIn('lvl.nama_level', $level);
            }
            $jabatan = $this->multiValues($request, 'jabatan');
            if (! empty($jabatan)) {
                $query->whereIn('lvl.nama_jabatan', $jabatan);
            }

            // Filter training-level: batasi ke karyawan yang ikut training cocok.
            $hasTrainingFilter = $this->hasTrainingLevelFilter($request);
            if ($hasTrainingFilter) {
                $matchedSub = $this->trainingMatchedKodeSubquery($request);
                $query->whereIn('k.Kode_Karyawan', $matchedSub);
            }

            $totalData = $query->count();

            $query->select([
                'k.Kode_Karyawan',
                'k.Nama as nama_karyawan',
                'k.Cabang_Untuk_Gaji as lokasi_kerja',
                'div.nama_sub_divisi as departemen',
                'div.nama_divisi as divisi',
                'lvl.nama_level as level',
                'lvl.nama_jabatan as jabatan',
            ]);

            $query->orderBy('k.Nama', 'ASC')->offset($offset)->limit($limit);
            $employees = $query->get();

            // Hitung berlangsung/selesai + daftar id penjadwalan per karyawan,
            // dibatasi training yang lolos filter (bila ada filter training-level).
            /* Ringkasan training diambil SEKALI untuk seluruh halaman.
               exportEmployeeTrainingSummary() menembak satu kueri per karyawan,
               jadi halaman 15 orang menghasilkan 15 kueri — dan kuerinya
               non-sargable (UPPER/LTRIM/RTRIM membungkus kolom), sehingga tiap
               satu di antaranya memaksa pemindaian. */
            $ringkasanTraining = $this->exportEmployeeTrainingSummaryMassal(
                $employees->pluck('Kode_Karyawan')->all(),
                $request,
                $hasTrainingFilter,
            );

            $formatted = $employees->map(function ($emp) use ($ringkasanTraining) {
                $detail = $ringkasanTraining[strtoupper(trim((string) $emp->Kode_Karyawan))]
                    ?? ['berlangsung' => 0, 'selesai' => 0, 'ids' => []];

                return [
                    'kode_karyawan' => $emp->Kode_Karyawan,
                    'nama' => $emp->nama_karyawan,
                    'lokasi_kerja' => $emp->lokasi_kerja ?? '-',
                    'departemen' => $emp->departemen ?? '-',
                    'divisi' => $emp->divisi ?? '-',
                    'level' => $emp->level ?? '-',
                    'jabatan' => $emp->jabatan ?? '-',
                    'training_berlangsung' => $detail['berlangsung'],
                    'training_selesai' => $detail['selesai'],
                    'total_training' => $detail['berlangsung'] + $detail['selesai'],
                    'penjadwalan_ids' => $detail['ids'], // encrypted Id_Penjadwalan
                ];
            });

            $response = ResponseHelper::successWithPaginationV2(
                $formatted,
                $page,
                $limit,
                $totalData,
                'Data karyawan export berhasil dimuat',
            );

            return response()->json($response->getData(true));
        } catch (\Exception $e) {
            Log::error('Error getExportEmployees: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * API: Semua Id_Penjadwalan (terenkripsi) untuk SELURUH karyawan yang lolos filter
     * export, lintas halaman. Dipakai tombol "Pilih Semua Karyawan" di modal export
     * agar seleksi tidak terbatas pada halaman yang sedang tampil.
     *
     * Mengembalikan map { kode_karyawan: [id_penjadwalan_terenkripsi, ...] } dengan
     * himpunan id yang identik dengan penjumlahan per-halaman getExportEmployees.
     */
    public function getExportSelectionIds(Request $request)
    {
        try {
            // Satu query: join penjadwalan -> karyawan, terapkan filter yang sama seperti
            // getExportEmployees. Dibangun berbasis penjadwalan (bukan daftar kode + IN)
            // agar tidak menabrak batas ~2100 parameter saat karyawan sangat banyak.
            $query = DB::table('N_LMS_Penjadwalan as p')
                ->join('Karyawan as k', 'p.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as lvl',
                    'k.ID_Level_Jabatan',
                    '=',
                    'lvl.ID_Level_Jabatan',
                );

            // Gating "aktif" (sama seperti activeKodeKaryawanSubQuery, tapi di baris penjadwalan).
            $this->trainingScheduleQueryService->applyActiveFilter($query, 'p');

            $search = $request->query('q', '');
            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('k.Nama', 'LIKE', '%'.$search.'%')->orWhere(
                        'k.Kode_Karyawan',
                        'LIKE',
                        '%'.$search.'%',
                    );
                });
            }

            // Filter employee-level (identik dengan getExportEmployees).
            $lokasi = $this->multiValues($request, 'lokasi');
            if (! empty($lokasi)) {
                $query->whereIn('k.Cabang_Untuk_Gaji', $lokasi);
            }
            $dept = $this->multiValues($request, 'dept');
            if (! empty($dept)) {
                $query->whereIn('div.nama_sub_divisi', $dept);
            }
            $divisi = $this->multiValues($request, 'divisi');
            if (! empty($divisi)) {
                $query->whereIn('div.nama_divisi', $divisi);
            }
            $level = $this->multiValues($request, 'level');
            if (! empty($level)) {
                $query->whereIn('lvl.nama_level', $level);
            }
            $jabatan = $this->multiValues($request, 'jabatan');
            if (! empty($jabatan)) {
                $query->whereIn('lvl.nama_jabatan', $jabatan);
            }

            // Filter training-level: batasi ke penjadwalan yang cocok.
            if ($this->hasTrainingLevelFilter($request)) {
                $this->applyExportTrainingExists($query, $request);
            }

            // Batas aman: "pilih semua" memuat & mengenkripsi seluruh Id_Penjadwalan ke
            // memori sekaligus. Tanpa batas, dataset sangat besar bisa menghabiskan memori
            // PHP (worker crash) dan menghasilkan payload puluhan MB. Ambil MAX+1 untuk
            // mendeteksi kelebihan tanpa memuat semuanya.
            $maxSelection = 25000;
            $rows = $query->select('k.Kode_Karyawan', 'p.Id_Penjadwalan')
                ->limit($maxSelection + 1)
                ->get();

            if ($rows->count() > $maxSelection) {
                return response()->json([
                    'success' => false,
                    'over_limit' => true,
                    'max' => $maxSelection,
                    'message' => 'Data terlalu banyak untuk dipilih sekaligus (lebih dari '
                        .number_format($maxSelection)
                        .' training). Persempit filter (lokasi, kategori, atau periode) lalu coba lagi.',
                ], 422);
            }

            // Kelompokkan per kode karyawan (key harus sama dengan emp.kode_karyawan di modal)
            // lalu enkripsi tiap Id_Penjadwalan seperti format getExportEmployees.
            $map = [];
            foreach ($rows as $row) {
                $kode = $row->Kode_Karyawan;
                $map[$kode][] = Crypt::encryptString((string) $row->Id_Penjadwalan);
            }

            return response()->json([
                'success' => true,
                'result' => (object) $map,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getExportSelectionIds: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Ringkasan training satu karyawan untuk modal export: jumlah berlangsung /
     * selesai + daftar Id_Penjadwalan (terenkripsi) sesuai filter.
     */
    /**
     * Ringkasan training per karyawan untuk seluruh halaman sekaligus.
     *
     * Bentuk tunggalnya menembak satu kueri per karyawan, dan kuerinya
     * non-sargable — `UPPER(LTRIM(RTRIM(Kode_Karyawan)))` membungkus kolomnya,
     * sehingga tiap panggilan memaksa pemindaian. Halaman 15 orang berarti 15
     * pemindaian.
     *
     * Di sini semuanya diambil dengan SATU kueri lewat byKodeKaryawansQuery(),
     * bentuk jamak yang sudah lama ada di service yang sama, lalu dikelompokkan
     * di PHP. Aritmetikanya disalin apa adanya dari bentuk tunggal.
     *
     * CATATAN: kuerinya tetap non-sargable — `IN` atas kolom yang dibungkus
     * fungsi tetap tak bisa memakai index. Yang hilang adalah 14 pemindaian
     * dari 15, bukan pemindaiannya. Membuatnya sargable berarti menyentuh
     * bagaimana Kode_Karyawan dinormalkan di seluruh sistem, dan itu perubahan
     * tersendiri yang harus diuji sendiri.
     *
     * @param  array<int,string>  $kodeList
     * @return array<string,array{berlangsung:int,selesai:int,ids:array<int,string>}> berkunci kode ternormalkan
     */
    private function exportEmployeeTrainingSummaryMassal(array $kodeList, Request $request, bool $hasTrainingFilter): array
    {
        $kode = collect($kodeList)
            ->map(fn ($k) => strtoupper(trim((string) $k)))
            ->filter()
            ->unique()
            ->values();

        if ($kode->isEmpty()) {
            return [];
        }

        $query = $this->trainingScheduleQueryService
            ->byKodeKaryawansQuery($kode->all(), 'p')
            ->selectRaw('UPPER(LTRIM(RTRIM(p.Kode_Karyawan))) as kode_ternormalkan')
            ->addSelect(['p.Id_Penjadwalan', 'p.Flag_Selesai']);

        if ($hasTrainingFilter) {
            $this->applyExportTrainingExists($query, $request);
        }

        $hasil = [];
        foreach ($query->get() as $row) {
            $k = (string) $row->kode_ternormalkan;
            if (! isset($hasil[$k])) {
                $hasil[$k] = ['berlangsung' => 0, 'selesai' => 0, 'ids' => []];
            }

            if ($row->Flag_Selesai === 'Y') {
                $hasil[$k]['selesai']++;
            } else {
                $hasil[$k]['berlangsung']++;
            }

            $hasil[$k]['ids'][] = Crypt::encryptString((string) $row->Id_Penjadwalan);
        }

        return $hasil;
    }

    /* exportEmployeeTrainingSummary() DIHAPUS 5 Sep 2026.
       Satu-satunya pemanggilnya kini membaca dari bentuk jamak di atas.
       Membiarkannya berarti menyisakan jalan pintas yang mengembalikan satu
       kueri non-sargable per karyawan tanpa disadari — persis cara cacat ini
       lahir pertama kali. */

    /**
     * API: Detail training per karyawan untuk expand di modal export. Sama dengan
     * getEmployeeTrainings tapi menyertakan Id_Penjadwalan terenkripsi (selectable).
     */
    public function getExportEmployeeTrainings(string $kodeKaryawan, Request $request)
    {
        try {
            $decryptedKode = $kodeKaryawan;
            $normalizedKode = strtoupper(trim($decryptedKode));

            $hasTrainingFilter = $this->hasTrainingLevelFilter($request);

            $penjadwalans = $this->trainingScheduleQueryService
                ->byKodeKaryawanWithPaketQuery($normalizedKode, 'p')
                ->select([
                    'p.Id_Penjadwalan',
                    'p.Id_Master_Paket',
                    'p.Flag_Selesai',
                    'p.Waktu_Enroll',
                    'p.Created_At',
                    'mp.Nama_Paket',
                ])
                ->orderBy('p.Waktu_Enroll', 'desc')
                ->get();

            $trainings = $penjadwalans
                ->map(function ($p) use ($request, $hasTrainingFilter) {
                    $materiData = $this->trainingScheduleQueryService->getFirstActiveMateriInPackageWithMetadata(
                        (int) $p->Id_Master_Paket,
                    );

                    // Saat ada filter training-level, lewati yang tidak cocok.
                    if ($hasTrainingFilter && ! $this->trainingMatchesFilter($materiData, $p, $request)) {
                        return null;
                    }

                    $status = $p->Flag_Selesai === 'Y' ? 'completed' : 'in-progress';

                    return [
                        'id_penjadwalan' => Crypt::encryptString((string) $p->Id_Penjadwalan),
                        'judul' => $materiData->Judul_Training ?? $p->Nama_Paket,
                        'kategori' => $materiData->Nama_Kategori ?? 'General',
                        'tanggal_enroll' => $p->Waktu_Enroll,
                        'status' => $status,
                    ];
                })
                ->filter()
                ->values();

            return ResponseHelper::success(['trainings' => $trainings]);
        } catch (\Exception $e) {
            Log::error('Error getExportEmployeeTrainings: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    private function trainingMatchesFilter(?object $materiData, object $p, Request $request): bool
    {
        $namaTraining = $this->multiValues($request, 'nama_training');
        $kategori = $this->multiValues($request, 'kategori');
        $start = trim((string) $request->query('periode_start', ''));
        $end = trim((string) $request->query('periode_end', ''));

        if (! empty($namaTraining)) {
            $judul = (string) ($materiData->Judul_Training ?? '');
            if (! in_array($judul, $namaTraining, true)) {
                return false;
            }
        }
        if (! empty($kategori)) {
            $kat = (string) ($materiData->Nama_Kategori ?? 'General');
            if (! in_array($kat, $kategori, true)) {
                return false;
            }
        }
        $dateVal = $request->query('periode_mode', 'enroll') === 'created'
            ? ($p->Created_At ?? null)
            : ($p->Waktu_Enroll ?? null);
        if ($start !== '' && $dateVal && Carbon::parse($dateVal)->lt(Carbon::parse($start))) {
            return false;
        }
        if ($end !== '' && $dateVal && Carbon::parse($dateVal)->gt(Carbon::parse($end)->endOfDay())) {
            return false;
        }

        return true;
    }

    /**
     * API: Dispatch job export. Menerima daftar Id_Penjadwalan terenkripsi.
     */
    public function requestExport(Request $request, LmsTrainingExportService $exportService)
    {
        $validated = $request->validate([
            'format' => ['required', 'in:excel,csv,pdf'],
            'selection' => ['required', 'array', 'min:1'],
            'selection.*' => ['string'],
            'nama' => ['nullable', 'string', 'max:120'], // label opsional dari user
        ]);

        // Label opsional yang ditampilkan di panel aktivitas (mudah dikenali user).
        $nama = trim(strip_tags((string) ($request->input('nama') ?? '')));

        // Dekripsi & validasi Id_Penjadwalan.
        $ids = [];
        foreach ($validated['selection'] as $enc) {
            try {
                $ids[] = (int) Crypt::decryptString($enc);
            } catch (\Throwable $e) {
                // abaikan id tidak valid
            }
        }
        $ids = array_values(array_unique(array_filter($ids, fn ($v) => $v > 0)));

        if (empty($ids)) {
            return ResponseHelper::error('Tidak ada training valid yang dipilih.', 422);
        }

        $exportId = $exportService->makeExportId();
        $requestedBy = optional(optional(Auth::user())->karyawan)->Kode_Karyawan;

        $payload = ['selection' => $ids, 'filters' => $request->input('filters', [])];
        if ($nama !== '') {
            $payload['nama'] = $nama;
        }

        $exportService->startJob(
            $exportId,
            $validated['format'],
            $payload,
            count($ids),
            $requestedBy,
        );

        GenerateLmsTrainingExportJob::dispatch($exportId);

        Log::channel('UpdatePerformanceController')->info('LMS_EXPORT_REQUEST', [
            'export_id' => $exportId,
            'format' => $validated['format'],
            'total' => count($ids),
            'requested_by' => $requestedBy,
        ]);

        return ResponseHelper::success(
            ['export_id' => $exportId, 'total_records' => count($ids)],
            'Export dijadwalkan',
            202,
        );
    }

    public function getExportList(LmsTrainingExportService $exportService)
    {
        $requestedBy = optional(optional(Auth::user())->karyawan)->Kode_Karyawan;

        return ResponseHelper::success(
            $exportService->getList($requestedBy, 10),
            'Daftar export berhasil diambil',
        );
    }

    public function getExportStatus(string $exportId, LmsTrainingExportService $exportService)
    {
        $progress = $exportService->getProgress($exportId);

        if (! $progress) {
            return ResponseHelper::error('Export tidak ditemukan', 404);
        }

        return ResponseHelper::success($progress, 'Status export berhasil diambil');
    }

    public function retryExport(string $exportId, LmsTrainingExportService $exportService)
    {
        // "Coba lagi": hapus job gagal lalu buat & dispatch job BARU (export_id
        // baru) dgn payload sama. Tidak memakai retry otomatis Cloud Tasks.
        $newId = $exportService->retryAsNew($exportId);
        if (! $newId) {
            return ResponseHelper::error('Export tidak ditemukan atau sudah selesai.', 422);
        }

        GenerateLmsTrainingExportJob::dispatch($newId);

        return ResponseHelper::success(['export_id' => $newId], 'Export dijadwalkan ulang', 202);
    }

    public function downloadExport(string $exportId, LmsTrainingExportService $exportService)
    {
        $job = $exportService->markDownloaded($exportId);

        if (! $job || $job->Status !== LmsTrainingExportService::STATUS_READY || ! $job->File_Path) {
            return ResponseHelper::error('File export belum siap atau sudah dihapus.', 404);
        }

        if (! Storage::disk(LmsTrainingExportService::DISK)->exists($job->File_Path)) {
            return ResponseHelper::error('File export sudah tidak tersedia.', 410);
        }

        return Storage::disk(LmsTrainingExportService::DISK)->download($job->File_Path, $job->File_Name);
    }

    public function dismissExport(string $exportId, LmsTrainingExportService $exportService)
    {
        $exportService->dismiss($exportId);

        return ResponseHelper::success(null, 'Export disembunyikan dari panel');
    }

    /**
     * Jumlah maksimum opsi training yang dikirim per satu pencarian.
     *
     * Ini batas PER PENCARIAN, bukan plafon jumlah training. Frontend wajib
     * mengirim `q` (remote search) agar training di luar potongan pertama tetap
     * bisa dijangkau, dan sisa yang tidak terkirim dilaporkan lewat `truncated`.
     */
    private const TRAINING_SEARCH_LIMIT = 50;

    /**
     * API: Cari opsi nama training (untuk filter modal export — jumlahnya banyak
     * jadi di-fetch via search async).
     */
    public function searchTrainings(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));

            $base = DB::table('N_LMS_Materi')
                ->where('Flag_Aktif', 'Y')
                ->when($q !== '', fn ($query) => $query->where('Judul_Training', 'LIKE', '%'.$q.'%'));

            $total = (clone $base)->distinct()->count('Judul_Training');

            $trainings = $base
                ->distinct()
                ->orderBy('Judul_Training')
                ->limit(self::TRAINING_SEARCH_LIMIT)
                ->pluck('Judul_Training');

            return ResponseHelper::success(
                [
                    'items' => $trainings,
                    'total' => $total,
                    'truncated' => $total > $trainings->count(),
                ],
                'Daftar training berhasil dimuat',
            );
        } catch (\Exception $e) {
            Log::error('Error searchTrainings: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    private function ensureMonitoringAdminAuthorized(): void
    {
        if (! Auth::check()) {
            abort(401, 'Unauthorized');
        }

        // if (session('access_mode') !== 'admin') {
        //     abort(403, 'Akses hanya untuk mode admin monitoring.');
        // }

        $user = Auth::user();
        // dd($user);
        if (! $user) {
            abort(403, 'Anda tidak memiliki akses melihat detail test monitoring.');
        }
    }
}
