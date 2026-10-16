<?php

namespace App\Http\Controllers\ESS\Attendance\Leave\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\SYSTEM\Application\Security\GerbangToken;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use App\Services\SYSTEM\TaskProcessing\CloudTaskService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CutiBersamaAdvancedController extends Controller
{
    protected $transactionService;

    protected $cloudTaskService;

    /** Jawaban Schema::hasColumn diingat sekali per permintaan — lihat hasSelectedDatesColumn(). */
    private ?bool $adaKolomSelectedDates = null;

    const ID_JENIS_CUTI_TAHUNAN = 1;

    const ID_JENIS_CUTI_LOYALTY = 2;

    const EXCLUDED_LEVEL_IDS = [21];

    const ROLLBACK_ELIGIBLE_STATUSES = ['SUCCESS', 'COMPLETED'];

    public function __construct(
        TransactionService $transactionService,
        CloudTaskService $cloudTaskService,
        private readonly AttendanceDirtyResolverService $attendanceDirtyResolver,
    ) {
        $this->transactionService = $transactionService;
        $this->cloudTaskService = $cloudTaskService;
    }

    /**
     * Render halaman Vue
     */
    public function index()
    {
        return inertia('ESS/Attendance/Leave/Cuti/Admin/CutiBersamaAdvanced');
    }

    // =========================================================================
    // CRUD CUTI BERSAMA
    // =========================================================================

    /**
     * Get list cuti bersama + status eksekusi
     */
    public function list(Request $request)
    {
        try {
            $search = trim((string) $request->input('search', ''));
            $status = strtolower(trim((string) $request->input('status', '')));
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $page = max(1, (int) $request->input('page', 1));
            $perPage = 10;

            /* Id_Cuti_Bersama jadi pemutus seri, dan itu bukan kerapian.
               Created_At tidak unik — di dev saja sudah ada satu pasang yang
               kembar. Tanpa pemutus seri, urutan baris kembar ditentukan basis
               data dan boleh berbeda antar permintaan; begitu daftarnya
               dipotong per halaman, baris yang sama bisa muncul dua kali di
               halaman berbeda sementara baris lain tak pernah muncul. */
            $query = DB::table('N_HRIS_Cuti_Bersama')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Created_At', 'desc')
                ->orderBy('Id_Cuti_Bersama', 'desc');

            if ($search !== '') {
                $query->where('Nama_Cuti_Bersama', 'like', "%{$search}%");
            }

            if ($startDate || $endDate) {
                $from = $startDate ?: $endDate;
                $to = $endDate ?: $startDate;

                if ($from && $to) {
                    if ($from > $to) {
                        [$from, $to] = [$to, $from];
                    }

                    // Ambil event cuti bersama yang overlap dengan rentang filter.
                    $query->whereDate('Tanggal_Mulai', '<=', $to)->whereDate('Tanggal_Selesai', '>=', $from);
                }
            }

            $list = $query->get();

            /* Semua data pelengkap diambil SEKALI untuk seluruh daftar.
               Sebelumnya tiap baris menembak enam kueri sendiri — 16 baris
               menghasilkan 102 kueri, dan angkanya tumbuh lurus mengikuti
               jumlah acara cuti bersama. */
            $pelengkap = $this->ambilPelengkapDaftar($list);

            $list = $list->map(function ($item) use ($pelengkap) {
                $id = (int) $item->Id_Cuti_Bersama;

                $item->detail_counts = $pelengkap['detail'][$id] ?? $this->detailKosong();
                $item->exclusion_count = $pelengkap['exclusion'][$id] ?? 0;
                $item->selected_dates_parsed = $this->extractSelectedDatesFromRecord($item);
                $item->Keterangan = $this->extractPlainKeterangan($item->Keterangan ?? null);

                // Parse JSON filters for display
                $item->filter_cabang_parsed = $item->Filter_Cabang ? json_decode($item->Filter_Cabang) : null;
                $item->filter_divisi_parsed = $item->Filter_Divisi ? json_decode($item->Filter_Divisi) : null;
                $item->filter_sub_divisi_parsed = $item->Filter_Sub_Divisi
                    ? json_decode($item->Filter_Sub_Divisi)
                    : null;
                $item->filter_kode_karyawan_parsed = $item->Filter_Kode_Karyawan
                    ? json_decode($item->Filter_Kode_Karyawan)
                    : null;
                $item->latest_execute_task = $pelengkap['execute'][$id] ?? null;
                $item->latest_rollback_task = $pelengkap['rollback'][$id] ?? null;
                $item->execution_snapshot = $this->buildExecutionSnapshot(
                    $item,
                    $item->latest_execute_task,
                    $item->latest_rollback_task,
                    $pelengkap['ringkasan'][$id] ?? $this->ringkasanKosong(),
                );

                return $item;
            });

            if ($status !== '') {
                $statusMap = [
                    'draft' => ['DRAFT'],
                    'queued' => ['QUEUED'],
                    'scheduled' => ['SCHEDULED'],
                    'cancelled' => ['CANCELLED'],
                    'processing' => ['PROCESSING', 'ROLLBACK_PROCESSING'],
                    'executed' => ['EXECUTED'],
                    'completed' => ['COMPLETED'],
                    'failed' => ['FAILED', 'ROLLBACK_FAILED'],
                    'rolled_back' => ['ROLLED_BACK', 'PARTIAL_ROLLBACK'],
                ];

                if (isset($statusMap[$status])) {
                    $allowedStatuses = array_fill_keys($statusMap[$status], true);
                    $list = $list
                        ->filter(function ($item) use ($allowedStatuses) {
                            $snapshotStatus = strtoupper((string) ($item->execution_snapshot['status'] ?? 'DRAFT'));

                            return isset($allowedStatuses[$snapshotStatus]);
                        })
                        ->values();
                }
            }

            $total = $list->count();
            $list = $list->slice(max(0, ($page - 1) * $perPage), $perPage)->values();

            return ResponseHelper::success(
                [
                    'list' => $list,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'total_pages' => max(1, (int) ceil($total / $perPage)),
                    ],
                ],
                'Data berhasil diambil',
                200,
            );
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced list error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil data', 500);
        }
    }

    /**
     * Ambil semua data pelengkap daftar dalam tiga kueri, bukan enam per baris.
     *
     * Bentuk yang dikembalikan sengaja dibuat sama persis dengan yang dahulu
     * dihasilkan jalur per-baris, supaya jawaban yang dikirim ke layar tidak
     * berubah sedikit pun:
     *
     *   detail        -> object bergaya SELECT per baris (total_detail, total_success, …)
     *   exclusion     -> int
     *   execute       -> baris N_HRIS_Cloud_Task_Log terbaru bertipe EXECUTE
     *   rollback      -> idem, bertipe ROLLBACK
     *   ringkasan     -> keluaran getExecutionDetailSummary(), sudah jadi
     *
     * @param  \Illuminate\Support\Collection<int,object>  $baris
     * @return array{detail:array,exclusion:array,execute:array,rollback:array,ringkasan:array}
     */
    private function ambilPelengkapDaftar($baris): array
    {
        $kosong = ['detail' => [], 'exclusion' => [], 'execute' => [], 'rollback' => [], 'ringkasan' => []];

        $ids = $baris
            ->pluck('Id_Cuti_Bersama')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return $kosong;
        }

        $detail = [];
        $ringkasan = [];
        $agregat = DB::table('N_HRIS_Cuti_Bersama_Detail')
            ->whereIn('Id_Cuti_Bersama', $ids)
            ->groupBy('Id_Cuti_Bersama')
            ->selectRaw(
                "
                Id_Cuti_Bersama,
                COUNT(*) as total_detail,
                SUM(CASE WHEN Status = 'SUCCESS' THEN 1 ELSE 0 END) as total_success,
                SUM(CASE WHEN Status = 'COMPLETED' THEN 1 ELSE 0 END) as total_completed,
                SUM(CASE WHEN Status = 'FAILED' THEN 1 ELSE 0 END) as total_failed,
                SUM(CASE WHEN Status = 'ROLLED_BACK' THEN 1 ELSE 0 END) as total_rolled_back,
                MAX(CASE WHEN Status = 'ROLLED_BACK' THEN Rolled_Back_At END) as last_rolled_back_at
            ",
            )
            ->get();

        foreach ($agregat as $a) {
            $id = (int) $a->Id_Cuti_Bersama;

            /* Urutan field ditulis ulang satu per satu, bukan meneruskan baris
               agregatnya apa adanya: baris itu membawa Id_Cuti_Bersama dan
               last_rolled_back_at yang TIDAK pernah ada di jawaban lama. */
            $hitungan = (object) [
                'total_detail' => $a->total_detail,
                'total_success' => $a->total_success,
                'total_completed' => $a->total_completed,
                'total_failed' => $a->total_failed,
                'total_rolled_back' => $a->total_rolled_back,
            ];

            $detail[$id] = $hitungan;
            $ringkasan[$id] = [
                'total_detail' => (int) $a->total_detail,
                'total_rolled_back' => (int) $a->total_rolled_back,
                'remaining_active_eligible' => (int) $a->total_success + (int) $a->total_completed,
                'last_rolled_back_at' => $a->last_rolled_back_at,
            ];
        }

        $exclusion = DB::table('N_HRIS_Cuti_Bersama_Exclusion')
            ->whereIn('Id_Cuti_Bersama', $ids)
            ->groupBy('Id_Cuti_Bersama')
            ->selectRaw('Id_Cuti_Bersama, COUNT(*) as jumlah')
            ->pluck('jumlah', 'Id_Cuti_Bersama')
            ->mapWithKeys(fn ($n, $id) => [(int) $id => (int) $n])
            ->all();

        /* Satu baris terbaru per (acara, jenis tugas). ROW_NUMBER dipakai supaya
           pemilihannya terjadi di basis data; menariknya semua lalu memilih di
           PHP berarti memindahkan seluruh riwayat tugas ke memori. Urutannya
           sama persis dengan getLatestExecutionTaskLog: Created_At lalu Id_Log. */
        $log = DB::query()
            ->fromSub(
                DB::table('N_HRIS_Cloud_Task_Log')
                    ->select('*')
                    ->selectRaw(
                        'ROW_NUMBER() OVER ('
                        .'PARTITION BY Reference_Id, Task_Type '
                        .'ORDER BY Created_At DESC, Id_Log DESC) as urutan',
                    )
                    ->whereIn('Reference_Id', $ids)
                    ->whereIn('Task_Type', ['CUTI_BERSAMA_EXECUTE', 'CUTI_BERSAMA_ROLLBACK']),
                'log',
            )
            ->where('urutan', 1)
            ->get();

        $execute = [];
        $rollback = [];
        foreach ($log as $l) {
            $id = (int) $l->Reference_Id;
            $jenis = $l->Task_Type;

            // 'urutan' hanya alat pemilih; ia tak boleh ikut terkirim ke layar.
            unset($l->urutan);

            if ($jenis === 'CUTI_BERSAMA_EXECUTE') {
                $execute[$id] = $l;
            } elseif ($jenis === 'CUTI_BERSAMA_ROLLBACK') {
                $rollback[$id] = $l;
            }
        }

        return [
            'detail' => $detail,
            'exclusion' => $exclusion,
            'execute' => $execute,
            'rollback' => $rollback,
            'ringkasan' => $ringkasan,
        ];
    }

    /**
     * Bentuk detail_counts untuk acara yang belum punya satu pun baris detail.
     *
     * SUM atas himpunan kosong bernilai NULL di SQL Server, dan jalur per-baris
     * dahulu mengembalikan tepat bentuk ini. Ditiru apa adanya supaya jawaban
     * lama dan baru identik.
     *
     * total_detail sengaja STRING '0', bukan angka: driver PDO mengembalikan
     * COUNT(*) sebagai string, dan itulah yang selama ini sampai ke layar.
     * Mengubahnya jadi angka adalah keputusan tersendiri — kalau memang mau
     * dirapikan, rapikan untuk semua nilai sekaligus, bukan diam-diam ikut
     * terbawa perbaikan performa ini.
     */
    private function detailKosong(): object
    {
        return (object) [
            'total_detail' => '0',
            'total_success' => null,
            'total_completed' => null,
            'total_failed' => null,
            'total_rolled_back' => null,
        ];
    }

    /** Ringkasan eksekusi untuk acara tanpa baris detail sama sekali. */
    private function ringkasanKosong(): array
    {
        return [
            'total_detail' => 0,
            'total_rolled_back' => 0,
            'remaining_active_eligible' => 0,
            'last_rolled_back_at' => null,
        ];
    }

    /**
     * Store cuti bersama baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'selected_dates' => ['nullable', 'array', 'min:1'],
            'selected_dates.*' => ['required', 'date'],
            'potong_ke_tipe_cuti' => ['nullable', 'string'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            // Filters
            'filter_cabang' => ['nullable', 'array'],
            'filter_divisi' => ['nullable', 'array'],
            'filter_sub_divisi' => ['nullable', 'array'],
            'filter_kode_karyawan' => ['nullable', 'array'],
            // Exclusions
            'exclusions' => ['nullable', 'array'],
            'exclusions.*.kode_karyawan' => ['required_with:exclusions', 'string'],
            'exclusions.*.alasan' => ['nullable', 'string'],
            'execution_mode' => ['nullable', 'in:draft,now,scheduled'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $executionMode = $validated['execution_mode'] ?? 'draft';
        $scheduledAt = $this->resolveExecutionSchedule($executionMode, $validated['scheduled_at'] ?? null);

        DB::beginTransaction();
        try {
            $selectedDates = $this->normalizeSelectedDates(
                $validated['selected_dates'] ?? [],
                $validated['tanggal_mulai'] ?? null,
                $validated['tanggal_selesai'] ?? null,
            );

            if (empty($selectedDates)) {
                throw ValidationException::withMessages([
                    'selected_dates' => ['Pilih minimal satu tanggal cuti bersama.'],
                ]);
            }

            $payloadExclusionKodes = collect($validated['exclusions'] ?? [])
                ->pluck('kode_karyawan')
                ->filter()
                ->values()
                ->all();

            $overlapSummary = $this->buildOverlapSummary(
                $this->detectOverlapConflicts(
                    $selectedDates,
                    $this->resolveTargetKaryawanCodes(
                        $validated['filter_cabang'] ?? [],
                        $validated['filter_divisi'] ?? [],
                        $validated['filter_sub_divisi'] ?? [],
                        $validated['filter_kode_karyawan'] ?? [],
                        $payloadExclusionKodes,
                    ),
                ),
            );

            $jumlahHari = count($selectedDates);
            $storedKeterangan = $this->buildStoredKeterangan($validated['keterangan'] ?? null, $selectedDates);

            $adminKode = $this->resolveAuditActorId();

            $insertData = [
                'Nama_Cuti_Bersama' => $validated['nama'],
                'Tanggal_Mulai' => $selectedDates[0],
                'Tanggal_Selesai' => $selectedDates[$jumlahHari - 1],
                'Jumlah_Hari' => $jumlahHari,
                'Potong_Ke_Tipe_Cuti' => $validated['potong_ke_tipe_cuti'] ?? 'Tahunan',
                'Flag_Aktif' => 'Y',
                'Flag_Executed' => 'T',
                'Filter_Cabang' => ! empty($validated['filter_cabang'])
                    ? json_encode($validated['filter_cabang'])
                    : null,
                'Filter_Divisi' => ! empty($validated['filter_divisi'])
                    ? json_encode($validated['filter_divisi'])
                    : null,
                'Filter_Sub_Divisi' => ! empty($validated['filter_sub_divisi'])
                    ? json_encode($validated['filter_sub_divisi'])
                    : null,
                'Filter_Kode_Karyawan' => ! empty($validated['filter_kode_karyawan'])
                    ? json_encode($validated['filter_kode_karyawan'])
                    : null,
                'Keterangan' => $storedKeterangan,
                'Created_At' => now(),
                'Created_By' => $adminKode,
            ];

            if ($this->hasSelectedDatesColumn()) {
                $insertData['Selected_Dates'] = json_encode($selectedDates);
            }

            $id = DB::table('N_HRIS_Cuti_Bersama')->insertGetId($insertData);

            // Insert exclusions
            if (! empty($validated['exclusions'])) {
                $exclusionRows = [];
                foreach ($validated['exclusions'] as $exc) {
                    $exclusionRows[] = [
                        'Id_Cuti_Bersama' => $id,
                        'Kode_Karyawan' => $exc['kode_karyawan'],
                        'Alasan' => $exc['alasan'] ?? null,
                        'Created_At' => now(),
                        'Created_By' => $adminKode,
                    ];
                }
                DB::table('N_HRIS_Cuti_Bersama_Exclusion')->insert($exclusionRows);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CutiBersamaAdvanced store error: '.$e->getMessage());

            return ResponseHelper::error('Gagal membuat cuti bersama: '.$e->getMessage(), 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }

        $responseData = ['id' => $id, 'overlap_summary' => $overlapSummary ?? $this->buildOverlapSummary([])];
        $message = 'Cuti bersama berhasil dibuat';

        if (! empty($responseData['overlap_summary']['has_conflict'])) {
            $message .= '. Perhatian: terdapat overlap target/tanggal dengan cuti bersama lain.';
        }

        if ($executionMode !== 'draft') {
            try {
                $dispatchResult = $this->dispatchExecutionTaskById($id, $adminKode, $scheduledAt);
                $responseData = array_merge($responseData, $dispatchResult);
                $message .= '. '.$dispatchResult['message'];
            } catch (\Exception $e) {
                Log::error('CutiBersamaAdvanced store dispatch error: '.$e->getMessage());
                $responseData['dispatch_failed'] = true;
                $message .= '. Data tersimpan, tetapi dispatch gagal: '.$e->getMessage();
            }
        }

        return ResponseHelper::success($responseData, $message, 200);
    }

    /**
     * Update cuti bersama (hanya jika belum executed)
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'selected_dates' => ['nullable', 'array', 'min:1'],
            'selected_dates.*' => ['required', 'date'],
            'potong_ke_tipe_cuti' => ['nullable', 'string'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'filter_cabang' => ['nullable', 'array'],
            'filter_divisi' => ['nullable', 'array'],
            'filter_sub_divisi' => ['nullable', 'array'],
            'filter_kode_karyawan' => ['nullable', 'array'],
            'exclusions' => ['nullable', 'array'],
            'exclusions.*.kode_karyawan' => ['required_with:exclusions', 'string'],
            'exclusions.*.alasan' => ['nullable', 'string'],
            'execution_mode' => ['nullable', 'in:draft,now,scheduled'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $executionMode = $validated['execution_mode'] ?? 'draft';
        $scheduledAt = $this->resolveExecutionSchedule($executionMode, $validated['scheduled_at'] ?? null);
        $adminKode = $this->resolveAuditActorId();

        try {
            $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->first();

            if (! $cb) {
                return ResponseHelper::error('Cuti bersama tidak ditemukan', 404);
            }
            if ($cb->Flag_Executed === 'Y') {
                return ResponseHelper::error('Tidak bisa mengubah cuti bersama yang sudah dieksekusi', 400);
            }
            if ($this->hasActiveExecutionTask((int) $id)) {
                return ResponseHelper::error(
                    'Tidak bisa mengubah cuti bersama yang sudah dijadwalkan atau sedang diproses',
                    400,
                );
            }

            $selectedDates = $this->normalizeSelectedDates(
                $validated['selected_dates'] ?? [],
                $validated['tanggal_mulai'] ?? null,
                $validated['tanggal_selesai'] ?? null,
            );

            if (empty($selectedDates)) {
                throw ValidationException::withMessages([
                    'selected_dates' => ['Pilih minimal satu tanggal cuti bersama.'],
                ]);
            }

            $payloadExclusionKodes = collect($validated['exclusions'] ?? [])
                ->pluck('kode_karyawan')
                ->filter()
                ->values()
                ->all();

            $overlapSummary = $this->buildOverlapSummary(
                $this->detectOverlapConflicts(
                    $selectedDates,
                    $this->resolveTargetKaryawanCodes(
                        $validated['filter_cabang'] ?? [],
                        $validated['filter_divisi'] ?? [],
                        $validated['filter_sub_divisi'] ?? [],
                        $validated['filter_kode_karyawan'] ?? [],
                        $payloadExclusionKodes,
                    ),
                    (int) $id,
                ),
            );

            $jumlahHari = count($selectedDates);
            $updateData = [
                'Nama_Cuti_Bersama' => $validated['nama'],
                'Tanggal_Mulai' => $selectedDates[0],
                'Tanggal_Selesai' => $selectedDates[$jumlahHari - 1],
                'Jumlah_Hari' => $jumlahHari,
                'Potong_Ke_Tipe_Cuti' => $validated['potong_ke_tipe_cuti'],
                'Filter_Cabang' => ! empty($validated['filter_cabang'])
                    ? json_encode($validated['filter_cabang'])
                    : null,
                'Filter_Divisi' => ! empty($validated['filter_divisi'])
                    ? json_encode($validated['filter_divisi'])
                    : null,
                'Filter_Sub_Divisi' => ! empty($validated['filter_sub_divisi'])
                    ? json_encode($validated['filter_sub_divisi'])
                    : null,
                'Filter_Kode_Karyawan' => ! empty($validated['filter_kode_karyawan'])
                    ? json_encode($validated['filter_kode_karyawan'])
                    : null,
                'Keterangan' => $this->buildStoredKeterangan($validated['keterangan'] ?? null, $selectedDates),
                'Updated_At' => now(),
                'Updated_By' => $adminKode,
            ];

            if ($this->hasSelectedDatesColumn()) {
                $updateData['Selected_Dates'] = json_encode($selectedDates);
            }

            DB::beginTransaction();

            DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->update($updateData);

            DB::table('N_HRIS_Cuti_Bersama_Exclusion')->where('Id_Cuti_Bersama', $id)->delete();

            if (! empty($validated['exclusions'])) {
                $exclusionRows = [];
                foreach ($validated['exclusions'] as $exc) {
                    $exclusionRows[] = [
                        'Id_Cuti_Bersama' => $id,
                        'Kode_Karyawan' => $exc['kode_karyawan'],
                        'Alasan' => $exc['alasan'] ?? null,
                        'Created_At' => now(),
                        'Created_By' => $adminKode,
                    ];
                }

                DB::table('N_HRIS_Cuti_Bersama_Exclusion')->insert($exclusionRows);
            }

            DB::commit();
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('CutiBersamaAdvanced update error: '.$e->getMessage());

            return ResponseHelper::error('Gagal memperbarui: '.$e->getMessage(), 500);
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

        $responseData = ['id' => (int) $id, 'overlap_summary' => $overlapSummary ?? $this->buildOverlapSummary([])];
        $message = 'Cuti bersama berhasil diperbarui';

        if (! empty($responseData['overlap_summary']['has_conflict'])) {
            $message .= '. Perhatian: terdapat overlap target/tanggal dengan cuti bersama lain.';
        }

        if ($executionMode !== 'draft') {
            try {
                $dispatchResult = $this->dispatchExecutionTaskById((int) $id, $adminKode, $scheduledAt);
                $responseData = array_merge($responseData, $dispatchResult);
                $message .= '. '.$dispatchResult['message'];
            } catch (\Exception $e) {
                Log::error('CutiBersamaAdvanced update dispatch error: '.$e->getMessage());
                $responseData['dispatch_failed'] = true;
                $message .= '. Data berhasil diperbarui, tetapi dispatch gagal: '.$e->getMessage();
            }
        } else {
            DB::table('N_HRIS_Cuti_Bersama')
                ->where('Id_Cuti_Bersama', $id)
                ->update([
                    'Scheduled_At' => null,
                    'Updated_At' => now(),
                    'Updated_By' => $adminKode,
                ]);
        }

        return ResponseHelper::success($responseData, $message, 200);
    }

    /**
     * Soft delete cuti bersama
     */
    public function delete($id)
    {
        try {
            $adminKode = $this->resolveAuditActorId();
            $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->first();

            if (! $cb) {
                return ResponseHelper::error('Cuti bersama tidak ditemukan', 404);
            }
            if ($cb->Flag_Executed === 'Y') {
                return ResponseHelper::error(
                    'Tidak bisa menghapus cuti bersama yang sudah dieksekusi. Gunakan rollback terlebih dahulu.',
                    400,
                );
            }
            if ($this->hasActiveExecutionTask((int) $id)) {
                return ResponseHelper::error(
                    'Tidak bisa menghapus cuti bersama yang sudah dijadwalkan atau sedang diproses',
                    400,
                );
            }

            DB::table('N_HRIS_Cuti_Bersama')
                ->where('Id_Cuti_Bersama', $id)
                ->update([
                    'Flag_Aktif' => 'T',
                    'Updated_At' => now(),
                    'Updated_By' => $adminKode,
                ]);

            return ResponseHelper::success(null, 'Cuti bersama berhasil dihapus', 200);
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced delete error: '.$e->getMessage());

            return ResponseHelper::error('Gagal menghapus', 500);
        }
    }

    // =========================================================================
    // DETAIL & FILTER OPTIONS
    // =========================================================================

    /**
     * Detail cuti bersama + karyawan terdampak
     */
    public function detail(Request $request, $id)
    {
        try {
            $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->first();

            if (! $cb) {
                return ResponseHelper::error('Cuti bersama tidak ditemukan', 404);
            }

            $cb->selected_dates_parsed = $this->extractSelectedDatesFromRecord($cb);
            $cb->Keterangan = $this->extractPlainKeterangan($cb->Keterangan ?? null);
            $cb->latest_execute_task = $this->getLatestExecutionTaskLog((int) $id);
            $cb->latest_rollback_task = $this->getLatestRollbackTaskLog((int) $id);
            $cb->execution_snapshot = $this->buildExecutionSnapshot(
                $cb,
                $cb->latest_execute_task,
                $cb->latest_rollback_task,
            );
            $canManageExclusions = (bool) ($cb->execution_snapshot['can_modify'] ?? false);

            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 15);
            $searchDetail = $request->input('search', '');
            $statusFilter = $request->input('status', '');

            // Get exclusions
            $exclusions = DB::table('N_HRIS_Cuti_Bersama_Exclusion as e')
                ->leftJoin('Karyawan as k', 'e.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->where('e.Id_Cuti_Bersama', $id)
                ->select(
                    'e.*',
                    'k.Nama as Nama_Karyawan',
                    'vd.nama_divisi as Divisi',
                    'vd.nama_sub_divisi as Sub_Divisi',
                )
                ->get();

            $exclusionConflictsByKode = [];
            if ($canManageExclusions && $exclusions->isNotEmpty()) {
                $selectedDates = $this->extractSelectedDatesFromRecord($cb);

                foreach ($exclusions as $exclusion) {
                    $kodeKaryawan = $exclusion->Kode_Karyawan;
                    if (! $kodeKaryawan) {
                        continue;
                    }

                    $conflicts = $this->detectOverlapConflicts($selectedDates, [$kodeKaryawan], (int) $id);
                    $summary = $this->buildOverlapSummary($conflicts);

                    $exclusionConflictsByKode[$kodeKaryawan] = [
                        'has_conflict' => (bool) ($summary['has_conflict'] ?? false),
                        'has_blocking_conflict' => (bool) ($summary['has_blocking_conflict'] ?? false),
                        'total_conflicts' => (int) ($summary['total_conflicts'] ?? 0),
                        'blocking_conflicts' => (int) ($summary['blocking_conflicts'] ?? 0),
                    ];
                }
            }

            $exclusionKodes = $exclusions->pluck('Kode_Karyawan')->filter()->values()->all();
            $hasRecordedDetails = DB::table('N_HRIS_Cuti_Bersama_Detail')->where('Id_Cuti_Bersama', $id)->exists();

            if ($cb->Flag_Executed === 'Y' || $hasRecordedDetails) {
                $detailQuery = DB::table('N_HRIS_Cuti_Bersama_Detail')->where('Id_Cuti_Bersama', $id);

                if ($searchDetail) {
                    $detailQuery->where(function ($q) use ($searchDetail) {
                        $q->where('Nama_Karyawan', 'like', "%{$searchDetail}%")->orWhere(
                            'Kode_Karyawan',
                            'like',
                            "%{$searchDetail}%",
                        );
                    });
                }

                if ($statusFilter) {
                    $detailQuery->where('Status', $statusFilter);
                }

                $totalDetail = $detailQuery->count();
                $details = $detailQuery
                    ->orderBy('Created_At', 'desc')
                    ->offset(($page - 1) * $perPage)
                    ->limit($perPage)
                    ->get();
            } else {
                [$details, $totalDetail] = $this->buildDraftImpactDetails(
                    $cb,
                    $exclusionKodes,
                    $searchDetail,
                    $statusFilter,
                    (int) $page,
                    (int) $perPage,
                );
            }

            // Get cloud task logs
            $taskLogs = DB::table('N_HRIS_Cloud_Task_Log')
                ->where('Reference_Id', $id)
                ->orderBy('Created_At', 'desc')
                ->get();

            $details = $this->decorateDetailsWithRollbackProgress($details, $cb->latest_rollback_task ?? null);
            $taskLogs = $this->mapTaskLogsForAdmin($taskLogs);

            return ResponseHelper::success(
                [
                    'cuti_bersama' => $cb,
                    'details' => $details,
                    'detail_pagination' => [
                        'current_page' => (int) $page,
                        'per_page' => (int) $perPage,
                        'total' => $totalDetail,
                        'total_pages' => ceil($totalDetail / $perPage),
                    ],
                    'exclusions' => $exclusions,
                    'can_manage_exclusions' => $canManageExclusions,
                    'exclusion_conflicts_by_kode' => $exclusionConflictsByKode,
                    'task_logs' => $taskLogs,
                ],
                'Detail berhasil diambil',
                200,
            );
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced detail error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil detail', 500);
        }
    }

    /**
     * Get filter options for dropdowns
     */
    public function filterOptions()
    {
        try {
            // Cabang dari Karyawan
            $cabang = DB::table('Karyawan')
                ->where('Aktif', 'Y')
                ->whereNotNull('Cabang_Untuk_Gaji')
                ->where('Cabang_Untuk_Gaji', '!=', '')
                ->select('Cabang_Untuk_Gaji')
                ->distinct()
                ->orderBy('Cabang_Untuk_Gaji')
                ->pluck('Cabang_Untuk_Gaji');

            // Divisi
            $divisi = DB::table('View_Divisi_Sub_Divisi')
                ->select('ID_Divisi', 'nama_divisi')
                ->groupBy('ID_Divisi', 'nama_divisi')
                ->orderBy('nama_divisi')
                ->get();

            // Sub Divisi
            $subDivisi = DB::table('View_Divisi_Sub_Divisi')
                ->select('ID_Sub_Divisi', 'nama_sub_divisi', 'ID_Divisi')
                ->groupBy('ID_Sub_Divisi', 'nama_sub_divisi', 'ID_Divisi')
                ->orderBy('nama_sub_divisi')
                ->get();

            // Jenis Cuti
            $jenisCuti = DB::table('N_HRIS_Jenis_Cuti')
                ->where('Flag_Aktif', 'Y')
                ->select('Id_Jenis_Cuti', 'Nama_Jenis_Cuti')
                ->orderBy('Nama_Jenis_Cuti')
                ->get();

            return ResponseHelper::success(
                [
                    'cabang' => $cabang,
                    'divisi' => $divisi,
                    'sub_divisi' => $subDivisi,
                    'jenis_cuti' => $jenisCuti,
                ],
                'Filter options berhasil diambil',
                200,
            );
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced filterOptions error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil filter options', 500);
        }
    }

    // =========================================================================
    // EXCLUSION MANAGEMENT
    // =========================================================================

    /**
     * Add exclusion
     */
    public function addExclusion(Request $request, $id)
    {
        $validated = $request->validate([
            'kode_karyawan' => ['required', 'string'],
            'alasan' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $adminKode = $this->resolveAuditActorId();
            $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->first();
            if (! $cb) {
                return ResponseHelper::error('Cuti bersama tidak ditemukan', 404);
            }

            // Cek duplikat
            $exists = DB::table('N_HRIS_Cuti_Bersama_Exclusion')
                ->where('Id_Cuti_Bersama', $id)
                ->where('Kode_Karyawan', $validated['kode_karyawan'])
                ->exists();

            if ($exists) {
                return ResponseHelper::error('Karyawan sudah ada di daftar pengecualian', 400);
            }

            DB::table('N_HRIS_Cuti_Bersama_Exclusion')->insert([
                'Id_Cuti_Bersama' => $id,
                'Kode_Karyawan' => $validated['kode_karyawan'],
                'Alasan' => $validated['alasan'] ?? null,
                'Created_At' => now(),
                'Created_By' => $adminKode,
            ]);

            return ResponseHelper::success(null, 'Pengecualian berhasil ditambahkan', 200);
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced addExclusion error: '.$e->getMessage());

            return ResponseHelper::error('Gagal menambahkan pengecualian', 500);
        }
    }

    /**
     * Remove exclusion
     */
    public function removeExclusion($id, $kodeKaryawan)
    {
        try {
            $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->first();

            if (! $cb) {
                return ResponseHelper::error('Cuti bersama tidak ditemukan', 404);
            }
            if ($cb->Flag_Executed === 'Y') {
                return ResponseHelper::error(
                    'Tidak bisa mengubah pengecualian untuk cuti bersama yang sudah dieksekusi',
                    400,
                );
            }
            if ($this->hasActiveExecutionTask((int) $id)) {
                return ResponseHelper::error(
                    'Tidak bisa mengubah pengecualian saat task eksekusi sudah dijadwalkan atau sedang diproses',
                    400,
                );
            }

            $selectedDates = $this->extractSelectedDatesFromRecord($cb);
            $overlapSummary = $this->buildOverlapSummary(
                $this->detectOverlapConflicts($selectedDates, [$kodeKaryawan], (int) $id),
            );

            if (! empty($overlapSummary['has_conflict'])) {
                return ResponseHelper::error(
                    'Karyawan tidak bisa disertakan kembali karena masih memiliki konflik overlap dengan cuti bersama lain.',
                    400,
                );
            }

            DB::table('N_HRIS_Cuti_Bersama_Exclusion')
                ->where('Id_Cuti_Bersama', $id)
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->delete();

            return ResponseHelper::success(null, 'Pengecualian berhasil dihapus', 200);
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced removeExclusion error: '.$e->getMessage());

            return ResponseHelper::error('Gagal menghapus pengecualian', 500);
        }
    }

    /**
     * Bulk add exclusions from impacted employee list (draft only)
     */
    public function bulkAddExclusions(Request $request, $id)
    {
        $validated = $request->validate([
            'kode_karyawan' => ['required', 'array', 'min:1'],
            'kode_karyawan.*' => ['required', 'string'],
            'alasan' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->first();

            if (! $cb) {
                return ResponseHelper::error('Cuti bersama tidak ditemukan', 404);
            }
            if ($cb->Flag_Executed === 'Y') {
                return ResponseHelper::error(
                    'Tidak bisa mengubah pengecualian untuk cuti bersama yang sudah dieksekusi',
                    400,
                );
            }
            if ($this->hasActiveExecutionTask((int) $id)) {
                return ResponseHelper::error(
                    'Tidak bisa mengubah pengecualian saat task eksekusi sudah dijadwalkan atau sedang diproses',
                    400,
                );
            }

            $targetKodes = $this->resolveTargetKaryawanCodesFromRecord($cb);
            $targetSet = array_fill_keys($targetKodes, true);
            $requestKodes = collect($validated['kode_karyawan'])
                ->filter()
                ->map(fn ($kode) => trim((string) $kode))
                ->unique()
                ->values();

            $validKodes = $requestKodes->filter(fn ($kode) => isset($targetSet[$kode]))->values()->all();

            if (empty($validKodes)) {
                return ResponseHelper::error('Tidak ada karyawan valid yang bisa ditambahkan ke pengecualian', 400);
            }

            $existingKodes = DB::table('N_HRIS_Cuti_Bersama_Exclusion')
                ->where('Id_Cuti_Bersama', $id)
                ->whereIn('Kode_Karyawan', $validKodes)
                ->pluck('Kode_Karyawan')
                ->all();

            $existingSet = array_fill_keys($existingKodes, true);
            $insertKodes = array_values(array_filter($validKodes, fn ($kode) => ! isset($existingSet[$kode])));

            if (empty($insertKodes)) {
                return ResponseHelper::error('Semua karyawan yang dipilih sudah ada di daftar pengecualian', 400);
            }

            $adminKode = $this->resolveAuditActorId();
            $rows = [];
            foreach ($insertKodes as $kode) {
                $rows[] = [
                    'Id_Cuti_Bersama' => $id,
                    'Kode_Karyawan' => $kode,
                    'Alasan' => $validated['alasan'] ?? 'Dikecualikan dari tab detail karyawan terdampak',
                    'Created_At' => now(),
                    'Created_By' => $adminKode,
                ];
            }

            DB::table('N_HRIS_Cuti_Bersama_Exclusion')->insert($rows);

            return ResponseHelper::success(
                [
                    'inserted' => count($insertKodes),
                    'requested' => $requestKodes->count(),
                ],
                'Pengecualian berhasil ditambahkan',
                200,
            );
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced bulkAddExclusions error: '.$e->getMessage());

            return ResponseHelper::error('Gagal menambahkan pengecualian', 500);
        }
    }

    /**
     * Bulk remove exclusions and include back to target (draft only, conflict-safe)
     */
    public function bulkRemoveExclusions(Request $request, $id)
    {
        $validated = $request->validate([
            'kode_karyawan' => ['required', 'array', 'min:1'],
            'kode_karyawan.*' => ['required', 'string'],
        ]);

        try {
            $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->first();

            if (! $cb) {
                return ResponseHelper::error('Cuti bersama tidak ditemukan', 404);
            }
            if ($cb->Flag_Executed === 'Y') {
                return ResponseHelper::error(
                    'Tidak bisa mengubah pengecualian untuk cuti bersama yang sudah dieksekusi',
                    400,
                );
            }
            if ($this->hasActiveExecutionTask((int) $id)) {
                return ResponseHelper::error(
                    'Tidak bisa mengubah pengecualian saat task eksekusi sudah dijadwalkan atau sedang diproses',
                    400,
                );
            }

            $requestKodes = collect($validated['kode_karyawan'])
                ->filter()
                ->map(fn ($kode) => trim((string) $kode))
                ->unique()
                ->values()
                ->all();

            $existingExclusions = DB::table('N_HRIS_Cuti_Bersama_Exclusion')
                ->where('Id_Cuti_Bersama', $id)
                ->whereIn('Kode_Karyawan', $requestKodes)
                ->pluck('Kode_Karyawan')
                ->all();

            if (empty($existingExclusions)) {
                return ResponseHelper::error('Tidak ada data pengecualian yang cocok untuk disertakan kembali', 400);
            }

            $selectedDates = $this->extractSelectedDatesFromRecord($cb);
            $blocked = [];
            $allowed = [];

            foreach ($existingExclusions as $kode) {
                $summary = $this->buildOverlapSummary(
                    $this->detectOverlapConflicts($selectedDates, [$kode], (int) $id),
                );

                if (! empty($summary['has_conflict'])) {
                    $blocked[] = [
                        'kode_karyawan' => $kode,
                        'total_conflicts' => (int) ($summary['total_conflicts'] ?? 0),
                        'blocking_conflicts' => (int) ($summary['blocking_conflicts'] ?? 0),
                    ];
                } else {
                    $allowed[] = $kode;
                }
            }

            if (! empty($allowed)) {
                DB::table('N_HRIS_Cuti_Bersama_Exclusion')
                    ->where('Id_Cuti_Bersama', $id)
                    ->whereIn('Kode_Karyawan', $allowed)
                    ->delete();
            }

            $message = 'Pengecualian berhasil disertakan kembali.';
            if (! empty($blocked)) {
                $message .= ' Sebagian data ditolak karena konflik overlap.';
            }

            return ResponseHelper::success(
                [
                    'included' => count($allowed),
                    'blocked' => count($blocked),
                    'blocked_karyawan' => $blocked,
                ],
                $message,
                200,
            );
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced bulkRemoveExclusions error: '.$e->getMessage());

            return ResponseHelper::error('Gagal menyertakan kembali pengecualian', 500);
        }
    }

    // =========================================================================
    // SEARCH KARYAWAN
    // =========================================================================

    /**
     * Search karyawan untuk exclusion/filter picker
     */
    public function searchKaryawan(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'target_only' => ['nullable'],
            'filter_cabang' => ['nullable', 'array'],
            'filter_divisi' => ['nullable', 'array'],
            'filter_sub_divisi' => ['nullable', 'array'],
            'filter_kode_karyawan' => ['nullable', 'array'],
            'exclude_kode_karyawan' => ['nullable', 'array'],
        ]);

        try {
            $search = trim((string) ($validated['q'] ?? ''));
            $targetOnly = filter_var($request->input('target_only', false), FILTER_VALIDATE_BOOLEAN);
            $excludeKodes = collect($validated['exclude_kode_karyawan'] ?? [])
                ->filter()
                ->values()
                ->all();

            if ($targetOnly) {
                $query = $this->buildFilteredKaryawanQuery(
                    $validated['filter_cabang'] ?? [],
                    $validated['filter_divisi'] ?? [],
                    $validated['filter_sub_divisi'] ?? [],
                    $validated['filter_kode_karyawan'] ?? [],
                );
            } else {
                $query = $this->buildFilteredKaryawanQuery();
            }

            if (! $targetOnly && $search === '') {
                return ResponseHelper::success([], 'Masukkan kata kunci pencarian', 200);
            }

            if (! empty($excludeKodes)) {
                $query->whereNotIn('k.Kode_Karyawan', $excludeKodes);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('k.Nama', 'like', "%{$search}%")->orWhere('k.Kode_Karyawan', 'like', "%{$search}%");
                });
            }

            $result = $query->orderBy('k.Nama')->limit(20)->get();

            return ResponseHelper::success($result, 'Karyawan ditemukan', 200);
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced searchKaryawan error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mencari karyawan', 500);
        }
    }

    // =========================================================================
    // PREVIEW
    // =========================================================================

    /**
     * Preview dampak cuti bersama sebelum eksekusi
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'id_cuti_bersama' => ['nullable', 'integer'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'selected_dates' => ['nullable', 'array', 'min:1'],
            'selected_dates.*' => ['required', 'date'],
            'jumlah_hari' => ['nullable', 'numeric', 'min:1'],
            'filter_cabang' => ['nullable', 'array'],
            'filter_divisi' => ['nullable', 'array'],
            'filter_sub_divisi' => ['nullable', 'array'],
            'filter_kode_karyawan' => ['nullable', 'array'],
            'exclusions' => ['nullable', 'array'],
        ]);

        try {
            // dd($validated);
            $existingCutiBersama = null;
            $selectedDates = $this->normalizeSelectedDates(
                $validated['selected_dates'] ?? [],
                $validated['tanggal_mulai'] ?? null,
                $validated['tanggal_selesai'] ?? null,
            );

            // dd($selectedDates);

            if (empty($selectedDates) && ! empty($validated['id_cuti_bersama'])) {
                $existingCutiBersama = DB::table('N_HRIS_Cuti_Bersama')
                    ->where('Id_Cuti_Bersama', $validated['id_cuti_bersama'])
                    ->first();

                if ($existingCutiBersama) {
                    $selectedDates = $this->extractSelectedDatesFromRecord($existingCutiBersama);
                }
            }

            if (! $existingCutiBersama && ! empty($validated['id_cuti_bersama'])) {
                $existingCutiBersama = DB::table('N_HRIS_Cuti_Bersama')
                    ->where('Id_Cuti_Bersama', $validated['id_cuti_bersama'])
                    ->first();
            }

            if (empty($selectedDates)) {
                throw ValidationException::withMessages([
                    'selected_dates' => ['Pilih minimal satu tanggal cuti bersama.'],
                ]);
            }

            $jumlahHari = count($selectedDates);

            // Build karyawan query with filters
            $karyawanList = $this->buildFilteredKaryawanQuery(
                $validated['filter_cabang'] ?? [],
                $validated['filter_divisi'] ?? [],
                $validated['filter_sub_divisi'] ?? [],
                $validated['filter_kode_karyawan'] ?? [],
            )->get();

            // dd($karyawanList);

            // Get exclusion list
            $exclusionKodes = collect($validated['exclusions'] ?? [])
                ->pluck('kode_karyawan')
                ->toArray();

            // Also get from DB if existing cuti bersama
            if (! empty($validated['id_cuti_bersama'])) {
                $dbExclusions = DB::table('N_HRIS_Cuti_Bersama_Exclusion')
                    ->where('Id_Cuti_Bersama', $validated['id_cuti_bersama'])
                    ->pluck('Kode_Karyawan')
                    ->toArray();
                $exclusionKodes = array_unique(array_merge($exclusionKodes, $dbExclusions));
            }

            $targetKodes = $karyawanList->pluck('Kode_Karyawan')->filter()->values()->all();

            if (! empty($exclusionKodes)) {
                $excludedSet = array_fill_keys($exclusionKodes, true);
                $targetKodes = array_values(array_filter($targetKodes, fn ($kode) => ! isset($excludedSet[$kode])));
            }

            $overlapSummary = $this->buildOverlapSummary(
                $this->detectOverlapConflicts(
                    $selectedDates,
                    $targetKodes,
                    ! empty($validated['id_cuti_bersama']) ? (int) $validated['id_cuti_bersama'] : null,
                ),
            );

            $preview = [
                'selected_dates' => $selectedDates,
                'total_karyawan' => 0,
                'total_excluded' => count($exclusionKodes),
                'total_terdampak' => 0,
                'saldo_cukup' => 0,
                'saldo_kurang' => 0,
                'sudah_hutang' => 0,
                'sample_detail' => [],
                'overlap_summary' => $overlapSummary,
            ];

            // Jika preview untuk item existing draft, saldo view biasanya sudah termasuk potongan item ini.
            // Untuk payload baru (belum tersimpan), saldo view belum termasuk item ini.
            $isExistingDraftPreview =
                $existingCutiBersama &&
                ($existingCutiBersama->Flag_Aktif ?? null) === 'Y' &&
                ($existingCutiBersama->Flag_Executed ?? null) !== 'Y' &&
                ! empty($existingCutiBersama->Tanggal_Selesai) &&
                Carbon::parse($existingCutiBersama->Tanggal_Selesai)->gte(Carbon::today());

            $terdampak = [];
            foreach ($karyawanList as $k) {
                $preview['total_karyawan']++;

                if (in_array($k->Kode_Karyawan, $exclusionKodes)) {
                    continue;
                }

                $preview['total_terdampak']++;

                // Get saldo from view
                $saldo = DB::table('N_HRIS_VW_Saldo_Cuti_Karyawan')->where('Kode_Karyawan', $k->Kode_Karyawan)->first();

                $sisaCuti = (float) ($saldo->Sisa_Cuti_Tampil ?? 0);
                $hutangCuti = (float) ($saldo->Hutang_Cuti_Tampil ?? 0);

                $saldoDariView = $hutangCuti > 0 ? -$hutangCuti : $sisaCuti;

                if ($isExistingDraftPreview) {
                    $saldoSetelahCurrent = $saldoDariView;
                    $saldoSebelumCurrent = $saldoSetelahCurrent + $jumlahHari;
                } else {
                    $saldoSebelumCurrent = $saldoDariView;
                    $saldoSetelahCurrent = $saldoSebelumCurrent - $jumlahHari;
                }

                if ($saldoSebelumCurrent < 0) {
                    $preview['sudah_hutang']++;
                } elseif ($saldoSetelahCurrent < 0) {
                    $preview['saldo_kurang']++;
                } else {
                    $preview['saldo_cukup']++;
                }

                // Collect sample (max 50)
                if (count($terdampak) < 50) {
                    $terdampak[] = [
                        'kode_karyawan' => $k->Kode_Karyawan,
                        'nama' => $k->Nama,
                        'cabang' => $k->Cabang_Untuk_Gaji,
                        'divisi' => $k->nama_divisi ?? '-',
                        'sub_divisi' => $k->nama_sub_divisi ?? '-',
                        'sisa_cuti' => $saldoSebelumCurrent,
                        'hutang_cuti' => $saldoSebelumCurrent < 0 ? abs($saldoSebelumCurrent) : 0,
                        'saldo_setelah' => $saldoSetelahCurrent,
                    ];
                }
            }

            $preview['sample_detail'] = $terdampak;

            return ResponseHelper::success($preview, 'Preview berhasil', 200);
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced preview error: '.$e->getMessage());

            return ResponseHelper::error('Gagal membuat preview: '.$e->getMessage(), 500);
        }
    }

    // =========================================================================
    // EXECUTE (DISPATCH CLOUD TASK)
    // =========================================================================

    /**
     * Dispatch execution via Cloud Task
     */
    public function execute(Request $request, $id)
    {
        $validated = $request->validate([
            'scheduled_at' => ['nullable', 'date'], // NULL = langsung, isi = dijadwalkan
        ]);

        $scheduledAt = ! empty($validated['scheduled_at'])
            ? $this->resolveExecutionSchedule('scheduled', $validated['scheduled_at'])
            : null;

        try {
            $adminKode = $this->resolveAuditActorId();
            $dispatchResult = null;

            DB::transaction(function () use ($id, $adminKode, $scheduledAt, &$dispatchResult) {
                $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->lockForUpdate()->first();

                if (! $cb) {
                    throw new \RuntimeException('Cuti bersama tidak ditemukan', 404);
                }
                if ($cb->Flag_Executed === 'Y') {
                    throw new \RuntimeException('Cuti bersama sudah dieksekusi');
                }
                if ($this->hasActiveExecutionTask((int) $id)) {
                    throw new \RuntimeException('Cuti bersama ini sudah memiliki task aktif');
                }

                $selectedDates = $this->extractSelectedDatesFromRecord($cb);
                $targetKodes = $this->resolveTargetKaryawanCodesFromRecord($cb);
                $overlapSummary = $this->buildOverlapSummary(
                    $this->detectOverlapConflicts($selectedDates, $targetKodes, (int) $id),
                );

                if (! empty($overlapSummary['has_blocking_conflict'])) {
                    throw new \RuntimeException($this->buildOverlapBlockMessage($overlapSummary));
                }

                /* KLAIM saja di sini. Panggilan jaringannya menyusul di luar
                   transaksi — dulu ia dilakukan tepat di titik ini, sementara
                   lockForUpdate di atas menahan baris cuti bersama selama
                   Google menjawab. Itu temuan #18. */
                $dispatchResult = $this->klaimExecutionTaskById((int) $id, $adminKode, $scheduledAt);
            });

            /* Di luar transaksi: kuncinya sudah lepas. Kegagalan di sini
               meninggalkan klaim berstatus DISPATCHED yang tak punya task —
               keadaan yang sama persis dengan bentuk lama ketika createTask()
               gagal, dan sudah ditangani jalur pemantauan task. */
            $this->cloudTaskService->kirim($dispatchResult['cloud_task_log_id']);

            return ResponseHelper::success($dispatchResult, $dispatchResult['message'], 200);
        } catch (\RuntimeException $e) {
            $code = (int) $e->getCode();
            if ($code === 404) {
                return ResponseHelper::error($e->getMessage(), 404);
            }

            return ResponseHelper::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced execute error: '.$e->getMessage());

            return ResponseHelper::error('Gagal dispatch eksekusi: '.$e->getMessage(), 500);
        }
    }

    /**
     * Cancel scheduled execution task.
     */
    public function cancelExecute($id)
    {
        try {
            $adminKode = $this->resolveAuditActorId();
            $result = $this->cancelScheduledExecutionTaskById((int) $id, $adminKode);

            return ResponseHelper::success($result, $result['message'], 200);
        } catch (\RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced cancelExecute error: '.$e->getMessage());

            return ResponseHelper::error('Gagal membatalkan jadwal eksekusi: '.$e->getMessage(), 500);
        }
    }

    /**
     * Cloud Task handler — called by GCP Cloud Task
     */
    public function handleCloudTaskExecute(Request $request)
    {
        // Verify bearer token
        /*
         * Gerbang GAGAL-TERTUTUP dan berwaktu-tetap. Bentuk lamanya
         * membandingkan langsung terhadap config() bernilai cadangan
         * literal; lihat GerbangToken untuk kenapa keduanya bermasalah.
         */
        if (! GerbangToken::sah($request->bearerToken(), 'services.project_config.SCHEDULER_BEARER_TOKEN')) {
            Log::warning('CutiBersama CloudTask: Unauthorized attempt');
            abort(403, 'Unauthorized');
        }

        $idCutiBersama = $request->input('id_cuti_bersama');
        $adminKode = $request->input('admin_kode');
        $logId = $request->input('_cloud_task_log_id');

        if ($logId) {
            $currentLogStatus = strtoupper(
                (string) (DB::table(CloudTaskService::LOG_TABLE)->where('Id_Log', $logId)->value('Status') ?? ''),
            );

            // Cloud Task can still hit callback after cancellation (race condition).
            if ($currentLogStatus === 'CANCELLED') {
                $currentSummary = $this->decodeJsonObjectValue(
                    DB::table(CloudTaskService::LOG_TABLE)->where('Id_Log', $logId)->value('Result_Summary') ?? null,
                );

                $updatedSummary = array_merge($currentSummary, [
                    'skipped' => true,
                    'reason' => 'cancelled',
                    'callback_received_after_cancelled' => true,
                    'callback_received_at' => now()->toDateTimeString(),
                ]);

                DB::table(CloudTaskService::LOG_TABLE)
                    ->where('Id_Log', $logId)
                    ->update([
                        'Result_Summary' => json_encode($updatedSummary),
                    ]);

                return response()->json(
                    [
                        'status' => 'skipped',
                        'reason' => 'cancelled',
                    ],
                    200,
                );
            }
        }

        $cbHead = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $idCutiBersama)->first();

        if ($cbHead && ($cbHead->Flag_Aktif ?? null) === 'T') {
            if ($logId) {
                CloudTaskService::markCompleted(
                    $logId,
                    totalProcessed: 0,
                    totalSuccess: 0,
                    totalFailed: 0,
                    resultSummary: [
                        'skipped' => true,
                        'reason' => 'soft_deleted',
                        'audit_note' => 'Transaksi sudah dibatalkan (Flag_Aktif=T), callback di-skip secara idempotent.',
                    ],
                );
            }

            return response()->json(
                [
                    'status' => 'skipped',
                    'reason' => 'soft_deleted',
                ],
                200,
            );
        }

        if ($cbHead && ($cbHead->Flag_Executed ?? null) === 'Y') {
            if ($logId) {
                CloudTaskService::markCompleted(
                    $logId,
                    totalProcessed: 0,
                    totalSuccess: 0,
                    totalFailed: 0,
                    resultSummary: [
                        'skipped' => true,
                        'reason' => 'already_executed',
                    ],
                );
            }

            return response()->json(
                [
                    'status' => 'skipped',
                    'reason' => 'already_executed',
                ],
                200,
            );
        }

        // Update log: PROCESSING — via CloudTaskService helper
        DB::beginTransaction();
        try {
            $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $idCutiBersama)->lockForUpdate()->first();

            if (! $cb) {
                throw new \Exception("Cuti bersama ID {$idCutiBersama} tidak ditemukan");
            }

            if (($cb->Flag_Aktif ?? null) === 'T') {
                DB::commit();

                if ($logId) {
                    CloudTaskService::markCompleted(
                        $logId,
                        totalProcessed: 0,
                        totalSuccess: 0,
                        totalFailed: 0,
                        resultSummary: [
                            'skipped' => true,
                            'reason' => 'soft_deleted',
                            'audit_note' => 'Transaksi sudah dibatalkan (Flag_Aktif=T), callback di-skip secara idempotent.',
                        ],
                    );
                }

                return response()->json(['status' => 'skipped', 'reason' => 'soft_deleted'], 200);
            }

            if (($cb->Flag_Executed ?? null) === 'Y') {
                DB::commit();

                if ($logId) {
                    CloudTaskService::markCompleted(
                        $logId,
                        totalProcessed: 0,
                        totalSuccess: 0,
                        totalFailed: 0,
                        resultSummary: [
                            'skipped' => true,
                            'reason' => 'already_executed',
                        ],
                    );
                }

                return response()->json(['status' => 'skipped', 'reason' => 'already_executed'], 200);
            }

            if ($logId) {
                CloudTaskService::markProcessing($logId);
            }

            $selectedDates = $this->extractSelectedDatesFromRecord($cb);
            $jumlahHari = count($selectedDates);

            if ($jumlahHari === 0) {
                throw new \Exception("Cuti bersama ID {$idCutiBersama} belum memiliki tanggal yang valid");
            }

            $targetKodes = $this->resolveTargetKaryawanCodesFromRecord($cb);
            $overlapSummary = $this->buildOverlapSummary(
                $this->detectOverlapConflicts($selectedDates, $targetKodes, (int) $idCutiBersama),
            );

            if (! empty($overlapSummary['has_blocking_conflict'])) {
                throw new \Exception($this->buildOverlapBlockMessage($overlapSummary));
            }

            // Parse filters from header
            $filterCabang = $cb->Filter_Cabang ? json_decode($cb->Filter_Cabang, true) : [];
            $filterDivisi = $cb->Filter_Divisi ? json_decode($cb->Filter_Divisi, true) : [];
            $filterSubDivisi = $cb->Filter_Sub_Divisi ? json_decode($cb->Filter_Sub_Divisi, true) : [];
            $filterKodeKaryawan = $cb->Filter_Kode_Karyawan ? json_decode($cb->Filter_Kode_Karyawan, true) : [];

            // Get filtered karyawan
            $karyawanList = $this->buildFilteredKaryawanQuery(
                $filterCabang,
                $filterDivisi,
                $filterSubDivisi,
                $filterKodeKaryawan,
            )->get();

            // Get exclusion list
            $exclusionKodes = DB::table('N_HRIS_Cuti_Bersama_Exclusion')
                ->where('Id_Cuti_Bersama', $idCutiBersama)
                ->pluck('Kode_Karyawan')
                ->toArray();

            $successCount = 0;
            $failedCount = 0;
            $excludedCount = count($exclusionKodes);
            $dirtyTargets = [];

            foreach ($karyawanList as $karyawan) {
                Log::info('Processing CB Execute for: '.$karyawan->Kode_Karyawan);

                // Skip excluded
                if (in_array($karyawan->Kode_Karyawan, $exclusionKodes)) {
                    Log::info('Skipped (Excluded): '.$karyawan->Kode_Karyawan);

                    continue;
                }

                $alreadySuccess = DB::table('N_HRIS_Cuti_Bersama_Detail')
                    ->where('Id_Cuti_Bersama', $idCutiBersama)
                    ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
                    ->where('Status', 'SUCCESS')
                    ->exists();

                if ($alreadySuccess) {
                    continue;
                }

                // Gunakan saldo fisik buku cuti agar detail eksekusi tidak ikut menghitung proyeksi CB aktif.
                $saldoSebelum = $this->getSaldoKaryawanFisik($karyawan->Kode_Karyawan);

                // Gunakan Sub-Transaction per karyawan agar jika 1 gagal, yang lain tetap jalan
                DB::beginTransaction();
                try {
                    // Deduct from wallets (FIFO)
                    $allocFragments = $this->consumeCutiBersamaWallets(
                        $idCutiBersama,
                        $karyawan->Kode_Karyawan,
                        $jumlahHari,
                        "Pemotongan Cuti Bersama: {$cb->Nama_Cuti_Bersama}",
                        $adminKode,
                    );

                    // Ambil saldo fisik sesudah pemotongan agar before/after sesuai mutasi wallet yang nyata.
                    $saldoSesudah = $this->getSaldoKaryawanFisik($karyawan->Kode_Karyawan);

                    // Record detail
                    $idDetail = DB::table('N_HRIS_Cuti_Bersama_Detail')->insertGetId([
                        'Id_Cuti_Bersama' => $idCutiBersama,
                        'Kode_Karyawan' => $karyawan->Kode_Karyawan,
                        'Nama_Karyawan' => $karyawan->Nama,
                        'Cabang' => $karyawan->Cabang_Untuk_Gaji,
                        'Divisi' => $karyawan->nama_divisi ?? null,
                        'Sub_Divisi' => $karyawan->nama_sub_divisi ?? null,
                        'Jumlah_Hari_Dipotong' => $jumlahHari,
                        'Saldo_Sebelum' => $saldoSebelum,
                        'Saldo_Sesudah' => $saldoSesudah,
                        'Status' => 'SUCCESS',
                        'Created_At' => now(),
                        'Created_By' => $adminKode,
                    ]);

                    // Link alloc fragments
                    if (! empty($allocFragments)) {
                        foreach ($allocFragments as &$frag) {
                            $frag['Id_Detail'] = $idDetail;
                        }
                        unset($frag);
                        DB::table('N_HRIS_Cuti_Bersama_Detail_Alloc')->insert($allocFragments);
                    }

                    DB::commit();
                    foreach ($selectedDates as $selectedDate) {
                        $dirtyTargets[] = [
                            'kode_karyawan' => $karyawan->Kode_Karyawan,
                            'tanggal' => $selectedDate,
                        ];
                    }
                    $successCount++;
                } catch (\Exception $e) {
                    DB::rollBack(); // Rollback hanya untuk karyawan ini
                    Log::warning("CutiBersama execute failed for {$karyawan->Kode_Karyawan}: ".$e->getMessage());

                    // Catat kegagalan ke database (di luar transaksi yang di-rollback tadi)
                    DB::table('N_HRIS_Cuti_Bersama_Detail')->insert([
                        'Id_Cuti_Bersama' => $idCutiBersama,
                        'Kode_Karyawan' => $karyawan->Kode_Karyawan,
                        'Nama_Karyawan' => $karyawan->Nama,
                        'Cabang' => $karyawan->Cabang_Untuk_Gaji,
                        'Divisi' => $karyawan->nama_divisi ?? null,
                        'Sub_Divisi' => $karyawan->nama_sub_divisi ?? null,
                        'Jumlah_Hari_Dipotong' => $jumlahHari,
                        'Saldo_Sebelum' => $saldoSebelum,
                        'Saldo_Sesudah' => $saldoSebelum, // Tidak berubah karena rollback
                        'Status' => 'FAILED',
                        'Error_Message' => substr($e->getMessage(), 0, 500),
                        'Created_At' => now(),
                        'Created_By' => $adminKode,
                    ]);

                    $failedCount++;
                } catch (\Throwable $e) {
                    /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
                       jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
                       supaya handler global tetap mencatat stack trace-nya dan responsnya
                       tak berubah sedikit pun. */
                    DB::rollBack();

                    throw $e;
                }
            }

            // Update header
            DB::table('N_HRIS_Cuti_Bersama')
                ->where('Id_Cuti_Bersama', $idCutiBersama)
                ->update([
                    'Flag_Executed' => 'Y',
                    'Executed_At' => now(),
                    'Executed_By' => $adminKode,
                    'Total_Karyawan_Terdampak' => $successCount,
                    'Total_Karyawan_Dikecualikan' => $excludedCount,
                    'Updated_At' => now(),
                ]);

            DB::commit();

            // Update cloud task log: COMPLETED — via CloudTaskService helper
            if ($logId) {
                CloudTaskService::markCompleted(
                    $logId,
                    totalProcessed: $successCount + $failedCount,
                    totalSuccess: $successCount,
                    totalFailed: $failedCount,
                    resultSummary: [
                        'success' => $successCount,
                        'failed' => $failedCount,
                        'excluded' => $excludedCount,
                    ],
                );
            }

            $this->attendanceDirtyResolver->markMany(
                $dirtyTargets,
                'CUTI_BERSAMA_EXECUTED',
                'CUTI_BERSAMA_ADVANCED_EXECUTE',
                4,
            );

            Log::info(
                "CutiBersama execute completed: success={$successCount}, failed={$failedCount}, excluded={$excludedCount}",
            );

            return response()->json(
                [
                    'status' => 'completed',
                    'success' => $successCount,
                    'failed' => $failedCount,
                    'excluded' => $excludedCount,
                ],
                200,
            );
        } catch (\Exception $e) {
            DB::rollBack();

            if ($logId) {
                CloudTaskService::markFailed($logId, $e->getMessage());
            }

            Log::error('CutiBersama CloudTask execute error: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    // =========================================================================
    // ROLLBACK
    // =========================================================================

    /**
     * Get rollback options sourced from executed detail target only (Status SUCCESS)
     */
    public function rollbackOptions($id)
    {
        try {
            $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->first();

            if (! $cb) {
                return ResponseHelper::error('Cuti bersama tidak ditemukan', 404);
            }
            if ($cb->Flag_Executed !== 'Y') {
                return ResponseHelper::error('Cuti bersama belum dieksekusi', 400);
            }

            $successDetails = DB::table('N_HRIS_Cuti_Bersama_Detail')
                ->where('Id_Cuti_Bersama', $id)
                ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(ISNULL(Status, \'\'))))'), self::ROLLBACK_ELIGIBLE_STATUSES)
                ->select('Kode_Karyawan', 'Nama_Karyawan', 'Divisi', 'Sub_Divisi')
                ->orderBy('Nama_Karyawan')
                ->get();

            $karyawan = $successDetails
                ->map(function ($row) {
                    return [
                        'kode_karyawan' => $row->Kode_Karyawan,
                        'nama_karyawan' => $row->Nama_Karyawan,
                        'divisi' => $row->Divisi,
                        'sub_divisi' => $row->Sub_Divisi,
                    ];
                })
                ->filter(fn ($row) => ! empty($row['kode_karyawan']))
                ->values();

            $divisi = $successDetails
                ->pluck('Divisi')
                ->filter(fn ($item) => ! is_null($item) && trim((string) $item) !== '')
                ->unique()
                ->sort()
                ->values();

            $subDivisi = $successDetails
                ->pluck('Sub_Divisi')
                ->filter(fn ($item) => ! is_null($item) && trim((string) $item) !== '')
                ->unique()
                ->sort()
                ->values();

            return ResponseHelper::success(
                [
                    'total_success' => $successDetails->count(),
                    'karyawan' => $karyawan,
                    'divisi' => $divisi,
                    'sub_divisi' => $subDivisi,
                ],
                'Rollback options berhasil diambil',
                200,
            );
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced rollbackOptions error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil rollback options', 500);
        }
    }

    /**
     * Rollback cuti bersama (granular)
     */
    public function rollback(Request $request, $id)
    {
        $validated = $request->validate([
            'scope' => ['required', 'in:all,karyawan,divisi,sub_divisi'],
            'kode_karyawan' => ['required_if:scope,karyawan', 'nullable', 'array', 'min:1'],
            'kode_karyawan.*' => ['required_with:kode_karyawan', 'string'],
            'divisi' => ['required_if:scope,divisi', 'nullable', 'array', 'min:1'],
            'divisi.*' => ['required_with:divisi', 'string'],
            'sub_divisi' => ['required_if:scope,sub_divisi', 'nullable', 'array', 'min:1'],
            'sub_divisi.*' => ['required_with:sub_divisi', 'string'],
        ]);

        try {
            $adminKode = $this->resolveAuditActorId();
            $dispatchResult = null;

            DB::transaction(function () use ($id, $validated, $adminKode, &$dispatchResult) {
                $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->lockForUpdate()->first();

                if (! $cb) {
                    throw new \RuntimeException('Cuti bersama tidak ditemukan', 404);
                }
                if ($cb->Flag_Executed !== 'Y') {
                    throw new \RuntimeException('Cuti bersama belum dieksekusi');
                }
                if ($this->hasActiveRollbackTask((int) $id)) {
                    throw new \RuntimeException(
                        'Rollback untuk cuti bersama ini sedang berjalan atau sudah masuk antrean.',
                    );
                }

                $this->validateRollbackSelection((int) $id, $validated);
                /* Klaim saja; kirimnya di luar transaksi (temuan #18). */
                $dispatchResult = $this->klaimRollbackTaskById((int) $id, $validated, $adminKode);
            });

            $this->cloudTaskService->kirim($dispatchResult['cloud_task_log_id']);

            return ResponseHelper::success($dispatchResult, $dispatchResult['message'], 200);
        } catch (\RuntimeException $e) {
            $code = (int) $e->getCode();
            if ($code === 404) {
                return ResponseHelper::error($e->getMessage(), 404);
            }

            return ResponseHelper::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced rollback dispatch error: '.$e->getMessage());

            return ResponseHelper::error('Gagal menjadwalkan rollback: '.$e->getMessage(), 500);
        }
    }

    public function handleCloudTaskRollback(Request $request)
    {
        /*
         * Gerbang GAGAL-TERTUTUP dan berwaktu-tetap. Bentuk lamanya
         * membandingkan langsung terhadap config() bernilai cadangan
         * literal; lihat GerbangToken untuk kenapa keduanya bermasalah.
         */
        if (! GerbangToken::sah($request->bearerToken(), 'services.project_config.SCHEDULER_BEARER_TOKEN')) {
            Log::warning('CutiBersama Rollback CloudTask: Unauthorized attempt');
            abort(403, 'Unauthorized');
        }

        $idCutiBersama = (int) $request->input('id_cuti_bersama');
        $adminKode = (string) $request->input('admin_kode');
        $payload = $this->decodeJsonObjectValue($request->input('rollback_payload'));
        $logId = $request->input('_cloud_task_log_id');

        if ($logId) {
            $currentLogStatus = strtoupper(
                (string) (DB::table(CloudTaskService::LOG_TABLE)->where('Id_Log', $logId)->value('Status') ?? ''),
            );

            if ($currentLogStatus === 'CANCELLED') {
                return response()->json(['status' => 'skipped', 'reason' => 'cancelled'], 200);
            }
        }

        DB::beginTransaction();
        try {
            $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $idCutiBersama)->lockForUpdate()->first();

            if (! $cb) {
                throw new \Exception("Cuti bersama ID {$idCutiBersama} tidak ditemukan");
            }

            if (($cb->Flag_Executed ?? null) !== 'Y') {
                DB::commit();

                if ($logId) {
                    CloudTaskService::markCompleted(
                        $logId,
                        totalProcessed: 0,
                        totalSuccess: 0,
                        totalFailed: 0,
                        resultSummary: [
                            'skipped' => true,
                            'reason' => 'not_executed',
                        ],
                    );
                }

                return response()->json(['status' => 'skipped', 'reason' => 'not_executed'], 200);
            }

            if ($logId) {
                CloudTaskService::markProcessing((int) $logId);
            }

            $result = $this->processRollbackNow($idCutiBersama, $payload, $adminKode);
            DB::commit();

            if ($logId) {
                CloudTaskService::markCompleted(
                    (int) $logId,
                    totalProcessed: (int) (($result['rolled_back'] ?? 0) + ($result['failed'] ?? 0)),
                    totalSuccess: (int) ($result['rolled_back'] ?? 0),
                    totalFailed: (int) ($result['failed'] ?? 0),
                    resultSummary: [
                        'scope' => $payload['scope'] ?? null,
                        'rolled_back' => (int) ($result['rolled_back'] ?? 0),
                        'failed' => (int) ($result['failed'] ?? 0),
                        'remaining_active' => (int) ($result['remaining_active'] ?? 0),
                    ],
                );
            }

            return response()->json(
                [
                    'status' => 'completed',
                    'rolled_back' => (int) ($result['rolled_back'] ?? 0),
                    'failed' => (int) ($result['failed'] ?? 0),
                    'remaining_active' => (int) ($result['remaining_active'] ?? 0),
                ],
                200,
            );
        } catch (\Exception $e) {
            DB::rollBack();

            if ($logId) {
                CloudTaskService::markFailed((int) $logId, $e->getMessage());
            }

            Log::error('CutiBersamaAdvanced rollback task error: '.$e->getMessage());

            return response()->json(
                [
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ],
                200,
            );
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    private function processRollbackNow(int $id, array $validated, string $adminKode): array
    {
        $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->lockForUpdate()->first();

        if (! $cb) {
            throw new \RuntimeException('Cuti bersama tidak ditemukan');
        }

        $idJenisCuti = $this->resolveJenisCutiId($cb->Potong_Ke_Tipe_Cuti);
        [$detailQuery] = $this->buildRollbackDetailQuery($id, $validated);
        $detailsToRollback = $detailQuery->get();

        if ($detailsToRollback->isEmpty()) {
            throw new \RuntimeException('Tidak ada data yang bisa di-rollback');
        }

        $successCount = 0;
        $failedCount = 0;
        $dirtyTargets = [];
        $selectedDates = $this->extractSelectedDatesFromRecord($cb);

        foreach ($detailsToRollback as $detail) {
            try {
                $allocRestored = $this->rollbackCutiBersamaByAlloc(
                    (int) $detail->Id_Detail,
                    "Rollback Cuti Bersama: {$cb->Nama_Cuti_Bersama}",
                    $adminKode,
                );

                if ($allocRestored === 0.0) {
                    $this->transactionService->manualAdjustment(
                        $detail->Kode_Karyawan,
                        $idJenisCuti,
                        'ADD',
                        $detail->Jumlah_Hari_Dipotong,
                        "Rollback Cuti Bersama: {$cb->Nama_Cuti_Bersama}",
                        $adminKode,
                    );
                }

                DB::table('N_HRIS_Cuti_Bersama_Detail')
                    ->where('Id_Detail', $detail->Id_Detail)
                    ->update([
                        'Status' => 'ROLLED_BACK',
                        'Rolled_Back_At' => now(),
                        'Rolled_Back_By' => $adminKode,
                    ]);

                $successCount++;
                foreach ($selectedDates as $selectedDate) {
                    $dirtyTargets[] = [
                        'kode_karyawan' => $detail->Kode_Karyawan,
                        'tanggal' => $selectedDate,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("CutiBersama rollback failed for {$detail->Kode_Karyawan}: ".$e->getMessage());
                $failedCount++;
            }
        }

        $remainingActive = DB::table('N_HRIS_Cuti_Bersama_Detail')
            ->where('Id_Cuti_Bersama', $id)
            ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(ISNULL(Status, \'\'))))'), self::ROLLBACK_ELIGIBLE_STATUSES)
            ->count();

        if ($remainingActive === 0) {
            DB::table('N_HRIS_Cuti_Bersama')
                ->where('Id_Cuti_Bersama', $id)
                ->update([
                    'Updated_At' => now(),
                    'Updated_By' => $adminKode,
                ]);
        }

        DB::table('N_HRIS_Cuti_Bersama')
            ->where('Id_Cuti_Bersama', $id)
            ->update([
                'Total_Karyawan_Terdampak' => $remainingActive,
                'Updated_At' => now(),
                'Updated_By' => $adminKode,
            ]);

        $this->attendanceDirtyResolver->markMany(
            $dirtyTargets,
            'CUTI_BERSAMA_ROLLBACK',
            'CUTI_BERSAMA_ADVANCED_ROLLBACK',
            4,
        );

        return [
            'rolled_back' => $successCount,
            'failed' => $failedCount,
            'remaining_active' => $remainingActive,
        ];
    }

    private function buildRollbackDetailQuery(int $id, array $validated): array
    {
        $eligibleDetails = DB::table('N_HRIS_Cuti_Bersama_Detail')
            ->where('Id_Cuti_Bersama', $id)
            ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(ISNULL(Status, \'\'))))'), self::ROLLBACK_ELIGIBLE_STATUSES)
            ->select('Id_Detail', 'Kode_Karyawan', 'Jumlah_Hari_Dipotong', 'Divisi', 'Sub_Divisi')
            ->lockForUpdate()
            ->get();

        if ($eligibleDetails->isEmpty()) {
            throw new \RuntimeException('Tidak ada target eksekusi yang bisa di-rollback');
        }

        $eligibleKodes = $eligibleDetails->pluck('Kode_Karyawan')->filter()->unique()->values()->all();
        $eligibleDivisi = $eligibleDetails
            ->pluck('Divisi')
            ->filter(fn ($item) => ! is_null($item) && trim((string) $item) !== '')
            ->unique()
            ->values()
            ->all();
        $eligibleSubDivisi = $eligibleDetails
            ->pluck('Sub_Divisi')
            ->filter(fn ($item) => ! is_null($item) && trim((string) $item) !== '')
            ->unique()
            ->values()
            ->all();

        $detailQuery = DB::table('N_HRIS_Cuti_Bersama_Detail')
            ->where('Id_Cuti_Bersama', $id)
            ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(ISNULL(Status, \'\'))))'), self::ROLLBACK_ELIGIBLE_STATUSES)
            ->lockForUpdate();

        switch ($validated['scope'] ?? 'all') {
            case 'karyawan':
                $selectedKodes = collect($validated['kode_karyawan'] ?? [])
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $invalidKodes = array_values(array_diff($selectedKodes, $eligibleKodes));
                if (! empty($invalidKodes)) {
                    throw new \RuntimeException(
                        'Sebagian karyawan tidak termasuk target eksekusi transaksi ini: '.
                            implode(', ', $invalidKodes),
                    );
                }
                $detailQuery->whereIn('Kode_Karyawan', $selectedKodes);
                break;
            case 'divisi':
                $selectedDivisi = collect($validated['divisi'] ?? [])
                    ->filter(fn ($item) => ! is_null($item) && trim((string) $item) !== '')
                    ->unique()
                    ->values()
                    ->all();
                $invalidDivisi = array_values(array_diff($selectedDivisi, $eligibleDivisi));
                if (! empty($invalidDivisi)) {
                    throw new \RuntimeException(
                        'Sebagian divisi tidak termasuk target eksekusi transaksi ini: '.
                            implode(', ', $invalidDivisi),
                    );
                }
                $detailQuery->whereIn('Divisi', $selectedDivisi);
                break;
            case 'sub_divisi':
                $selectedSubDivisi = collect($validated['sub_divisi'] ?? [])
                    ->filter(fn ($item) => ! is_null($item) && trim((string) $item) !== '')
                    ->unique()
                    ->values()
                    ->all();
                $invalidSubDivisi = array_values(array_diff($selectedSubDivisi, $eligibleSubDivisi));
                if (! empty($invalidSubDivisi)) {
                    throw new \RuntimeException(
                        'Sebagian sub divisi tidak termasuk target eksekusi transaksi ini: '.
                            implode(', ', $invalidSubDivisi),
                    );
                }
                $detailQuery->whereIn('Sub_Divisi', $selectedSubDivisi);
                break;
            case 'all':
            default:
                break;
        }

        return [$detailQuery, $eligibleDetails];
    }

    private function validateRollbackSelection(int $id, array $validated): void
    {
        [$detailQuery] = $this->buildRollbackDetailQuery($id, $validated);

        if (! $detailQuery->exists()) {
            throw new \RuntimeException('Tidak ada data yang bisa di-rollback');
        }
    }

    private function decorateDetailsWithRollbackProgress($details, ?object $latestRollbackTask)
    {
        $items = collect($details);

        if ($items->isEmpty() || ! $latestRollbackTask) {
            return $details;
        }

        $taskStatus = strtoupper(trim((string) ($latestRollbackTask->Status ?? '')));
        if (! in_array($taskStatus, ['DISPATCHED', 'PROCESSING'], true)) {
            return $details;
        }

        $payload = $this->decodeJsonObjectValue($latestRollbackTask->Payload ?? null);
        $rollbackPayload = $this->decodeJsonObjectValue($payload['rollback_payload'] ?? null);
        $scope = strtolower(trim((string) ($rollbackPayload['scope'] ?? 'all')));

        $selectedKodes = collect($rollbackPayload['kode_karyawan'] ?? [])
            ->filter()
            ->map(fn ($value) => trim((string) $value))
            ->values()
            ->all();
        $selectedDivisi = collect($rollbackPayload['divisi'] ?? [])
            ->filter(fn ($value) => ! is_null($value) && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->values()
            ->all();
        $selectedSubDivisi = collect($rollbackPayload['sub_divisi'] ?? [])
            ->filter(fn ($value) => ! is_null($value) && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->values()
            ->all();

        return $items
            ->map(function ($detail) use ($taskStatus, $scope, $selectedKodes, $selectedDivisi, $selectedSubDivisi) {
                $currentStatus = strtoupper(trim((string) ($detail->Status ?? '')));
                $isEligible = in_array($currentStatus, self::ROLLBACK_ELIGIBLE_STATUSES, true);

                if (! $isEligible) {
                    return $detail;
                }

                $matchesScope = match ($scope) {
                    'karyawan' => in_array(trim((string) ($detail->Kode_Karyawan ?? '')), $selectedKodes, true),
                    'divisi' => in_array(trim((string) ($detail->Divisi ?? '')), $selectedDivisi, true),
                    'sub_divisi' => in_array(trim((string) ($detail->Sub_Divisi ?? '')), $selectedSubDivisi, true),
                    'all' => true,
                    default => false,
                };

                if (! $matchesScope) {
                    return $detail;
                }

                $detail->Runtime_Status = $taskStatus === 'PROCESSING' ? 'ROLLBACK_PROCESSING' : 'ROLLBACK_QUEUED';
                $detail->Runtime_Status_Reason =
                    $taskStatus === 'PROCESSING'
                        ? 'Karyawan ini sedang diproses untuk rollback.'
                        : 'Karyawan ini sudah masuk antrean rollback.';

                return $detail;
            })
            ->values();
    }

    // =========================================================================
    // CLOUD TASK LOGS
    // =========================================================================

    /**
     * Get Cloud Task logs for a cuti bersama
     */
    public function cloudTaskLogs($id)
    {
        try {
            $logs = DB::table('N_HRIS_Cloud_Task_Log')
                ->where('Reference_Id', $id)
                ->orderBy('Created_At', 'desc')
                ->get();

            return ResponseHelper::success($this->mapTaskLogsForAdmin($logs), 'Logs berhasil diambil', 200);
        } catch (\Exception $e) {
            Log::error('CutiBersamaAdvanced cloudTaskLogs error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil logs', 500);
        }
    }

    private function mapTaskLogsForAdmin($logs)
    {
        return collect($logs)->map(fn ($log) => $this->formatTaskLogForAdmin($log))->values();
    }

    private function decodeJsonObjectValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function cleanTaskLogErrorMessage(?string $message): ?string
    {
        $text = trim((string) $message);

        if ($text === '') {
            return null;
        }

        $replacements = [
            'Dispatch failed:' => 'Pengiriman proses gagal:',
            'Task terakhir gagal.' => 'Proses terakhir gagal.',
            'Task sedang diproses.' => 'Proses sedang berjalan.',
            'Task sudah didispatch ke Cloud Task.' => 'Proses sudah dikirim ke antrean sistem.',
            'APP_URL belum dikonfigurasi untuk Cloud Task callback.' => 'Konfigurasi callback sistem belum lengkap. Hubungi tim teknis.',
            'URL callback Cloud Task tidak valid. Pastikan APP_URL memakai http:// atau https://.' => 'Konfigurasi URL callback sistem tidak valid. Hubungi tim teknis.',
        ];

        $text = strtr($text, $replacements);

        return mb_substr($text, 0, 250);
    }

    private function formatTaskLogForAdmin(object $log): array
    {
        $taskType = strtoupper(trim((string) ($log->Task_Type ?? '')));
        $status = strtoupper(trim((string) ($log->Status ?? '')));
        $resultSummary = $this->decodeJsonObjectValue($log->Result_Summary ?? null);
        $payload = $this->decodeJsonObjectValue($log->Payload ?? null);

        $totalProcessed = (int) ($log->Total_Processed ?? 0);
        $totalSuccess = (int) ($log->Total_Success ?? 0);
        $totalFailed = (int) ($log->Total_Failed ?? 0);
        $remainingActive = isset($resultSummary['remaining_active']) ? (int) $resultSummary['remaining_active'] : null;

        $actionLabel = match ($taskType) {
            'CUTI_BERSAMA_ROLLBACK' => 'Rollback Cuti Bersama',
            'CUTI_BERSAMA_EXECUTE' => 'Eksekusi Cuti Bersama',
            default => 'Proses Cuti Bersama',
        };

        $scopeLabel = match (strtolower((string) ($resultSummary['scope'] ?? ($payload['scope'] ?? '')))) {
            'all' => 'Semua target aktif',
            'karyawan' => 'Karyawan terpilih',
            'divisi' => 'Divisi terpilih',
            'sub_divisi' => 'Sub divisi terpilih',
            default => null,
        };

        $actionDetail =
            $taskType === 'CUTI_BERSAMA_ROLLBACK'
                ? ($scopeLabel
                    ? 'Target rollback: '.$scopeLabel
                    : 'Pengembalian saldo cuti untuk target yang dipilih.')
                : ($log->Scheduled_At
                    ? 'Eksekusi dijadwalkan dari panel admin.'
                    : 'Eksekusi dikirim dari panel admin.');

        $statusLabel = 'Draft';
        $badgeClass = 'cb-pill-muted';
        $summary = 'Belum ada proses yang tercatat.';

        if ($status === 'DISPATCHED') {
            $statusLabel = $log->Scheduled_At ? 'Menunggu Jadwal' : 'Dalam Antrean';
            $badgeClass = 'cb-pill-info';
            $summary = $log->Scheduled_At
                ? 'Proses sudah dijadwalkan dan menunggu waktu eksekusi.'
                : 'Permintaan sudah diterima sistem dan menunggu diproses.';
        } elseif ($status === 'CANCELLED') {
            $statusLabel = 'Dibatalkan';
            $badgeClass = 'cb-pill-warning';
            $summary = 'Jadwal task dibatalkan dari panel admin.';
        } elseif ($status === 'PROCESSING') {
            $statusLabel = 'Sedang Diproses';
            $badgeClass = 'cb-pill-primary';
            $summary =
                $taskType === 'CUTI_BERSAMA_ROLLBACK'
                    ? 'Sistem sedang mengembalikan saldo cuti karyawan.'
                    : 'Sistem sedang memproses pemotongan cuti karyawan.';
        } elseif ($status === 'FAILED') {
            $statusLabel = $taskType === 'CUTI_BERSAMA_ROLLBACK' ? 'Rollback Gagal' : 'Gagal';
            $badgeClass = 'cb-pill-danger';
            $summary =
                $taskType === 'CUTI_BERSAMA_ROLLBACK'
                    ? 'Rollback tidak berhasil dijalankan.'
                    : 'Eksekusi tidak berhasil dijalankan.';
        } elseif ($status === 'COMPLETED') {
            if ($taskType === 'CUTI_BERSAMA_ROLLBACK') {
                if ($totalFailed > 0 && $totalSuccess > 0) {
                    $statusLabel = 'Rollback Sebagian';
                    $badgeClass = 'cb-pill-warning';
                    $summary = 'Sebagian rollback berhasil, tetapi ada data yang gagal dikembalikan.';
                } elseif ($totalFailed > 0 && $totalSuccess === 0) {
                    $statusLabel = 'Rollback Gagal';
                    $badgeClass = 'cb-pill-danger';
                    $summary = 'Rollback selesai dicoba, tetapi tidak ada data yang berhasil dikembalikan.';
                } else {
                    $statusLabel = 'Rollback Selesai';
                    $badgeClass = 'cb-pill-info';
                    $summary = 'Saldo cuti berhasil dikembalikan sesuai target rollback.';
                }
            } else {
                if ($totalFailed > 0 && $totalSuccess > 0) {
                    $statusLabel = 'Selesai Sebagian';
                    $badgeClass = 'cb-pill-warning';
                    $summary = 'Eksekusi selesai, tetapi ada sebagian data yang gagal diproses.';
                } elseif ($totalFailed > 0 && $totalSuccess === 0) {
                    $statusLabel = 'Gagal';
                    $badgeClass = 'cb-pill-danger';
                    $summary = 'Eksekusi selesai dicoba, tetapi tidak ada data yang berhasil diproses.';
                } else {
                    $statusLabel = 'Berhasil';
                    $badgeClass = 'cb-pill-success';
                    $summary = 'Eksekusi cuti bersama selesai diproses.';
                }
            }
        }

        $progressText = '-';
        if ($taskType === 'CUTI_BERSAMA_ROLLBACK' && ($totalProcessed > 0 || $totalSuccess > 0 || $totalFailed > 0)) {
            $progressText = $totalSuccess.' karyawan berhasil di-rollback';
            if ($totalFailed > 0) {
                $progressText .= ', '.$totalFailed.' gagal';
            }
            if ($remainingActive !== null) {
                $progressText .= '. Sisa target aktif: '.$remainingActive;
            }
        } elseif ($totalProcessed > 0 || $totalSuccess > 0 || $totalFailed > 0) {
            $progressText = $totalSuccess.' berhasil, '.$totalFailed.' gagal dari '.$totalProcessed.' data';
        } elseif ($status === 'DISPATCHED' && $log->Scheduled_At) {
            $progressText = 'Menunggu waktu eksekusi yang dijadwalkan.';
        } elseif ($status === 'DISPATCHED') {
            $progressText = 'Menunggu giliran proses dari sistem.';
        } elseif ($status === 'CANCELLED') {
            $progressText = 'Task dijadwalkan ulang jika dibutuhkan.';
        }

        $note = $this->cleanTaskLogErrorMessage($log->Error_Message ?? null);

        if (! $note && $taskType === 'CUTI_BERSAMA_ROLLBACK' && $remainingActive !== null) {
            $note =
                $remainingActive > 0
                    ? 'Masih ada '.$remainingActive.' target yang tetap aktif pada transaksi ini.'
                    : 'Semua target pada transaksi ini sudah selesai di-rollback.';
        }

        if (! $note && $totalFailed > 0) {
            $note = 'Ada data yang gagal diproses. Cek tab karyawan terdampak bila perlu verifikasi lebih lanjut.';
        }

        return array_merge((array) $log, [
            'admin_action_label' => $actionLabel,
            'admin_action_detail' => $actionDetail,
            'admin_status_label' => $statusLabel,
            'admin_badge_class' => $badgeClass,
            'admin_summary' => $summary,
            'admin_progress_text' => $progressText,
            'admin_note' => $note,
        ]);
    }

    // =========================================================================
    // HELPER FUNCTIONS
    // =========================================================================

    private function resolveAuditActorId(): string
    {
        $user = Auth::user();

        $userIdWeb = $user->karyawan->UserID_Web ?? null;
        if (! is_null($userIdWeb) && $userIdWeb !== '') {
            return (string) $userIdWeb;
        }

        $idUsers = $user->Id_Users ?? null;
        if (! is_null($idUsers) && $idUsers !== '') {
            return (string) $idUsers;
        }

        throw new \RuntimeException('User audit identity tidak tersedia (UserID_Web / Id_Users).');
    }

    /**
     * Build filtered karyawan query
     */
    private function buildFilteredKaryawanQuery(
        array $filterCabang = [],
        array $filterDivisi = [],
        array $filterSubDivisi = [],
        array $filterKodeKaryawan = [],
    ) {
        $query = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->distinct()
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'k.Cabang_Untuk_Gaji',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                'vd.ID_Divisi',
                'vd.ID_Sub_Divisi',
            );

        if (! empty(self::EXCLUDED_LEVEL_IDS)) {
            $query->whereNotExists(function ($subQuery) {
                $subQuery
                    ->select(DB::raw(1))
                    ->from('View_Golongan_Sub_Golongan_Level_Jabatan as g')
                    ->whereColumn('g.ID_Level_Jabatan', 'k.ID_Level_Jabatan')
                    ->whereIn('g.ID_Level', self::EXCLUDED_LEVEL_IDS);
            });
        }

        $hasUnitFilter = ! empty($filterCabang) || ! empty($filterDivisi) || ! empty($filterSubDivisi);
        $hasSpecificFilter = ! empty($filterKodeKaryawan);

        // Apply union semantics:
        // - unit filter only: use unit criteria
        // - specific only: use specific karyawan list
        // - both provided: (unit criteria) OR (specific list)
        if ($hasUnitFilter && $hasSpecificFilter) {
            $query->where(function ($outer) use ($filterCabang, $filterDivisi, $filterSubDivisi, $filterKodeKaryawan) {
                $outer
                    ->where(function ($unit) use ($filterCabang, $filterDivisi, $filterSubDivisi) {
                        if (! empty($filterCabang)) {
                            $unit->whereIn('k.Cabang_Untuk_Gaji', $filterCabang);
                        }

                        if (! empty($filterDivisi)) {
                            $unit->whereIn('vd.ID_Divisi', $filterDivisi);
                        }

                        if (! empty($filterSubDivisi)) {
                            $unit->whereIn('vd.ID_Sub_Divisi', $filterSubDivisi);
                        }
                    })
                    ->orWhereIn('k.Kode_Karyawan', $filterKodeKaryawan);
            });

            return $query;
        }

        if ($hasSpecificFilter) {
            $query->whereIn('k.Kode_Karyawan', $filterKodeKaryawan);

            return $query;
        }

        if (! empty($filterCabang)) {
            $query->whereIn('k.Cabang_Untuk_Gaji', $filterCabang);
        }

        if (! empty($filterDivisi)) {
            $query->whereIn('vd.ID_Divisi', $filterDivisi);
        }

        if (! empty($filterSubDivisi)) {
            $query->whereIn('vd.ID_Sub_Divisi', $filterSubDivisi);
        }

        return $query;
    }

    private function decodeArrayValue($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn ($item) => $item !== null && $item !== ''));
    }

    private function resolveTargetKaryawanCodes(
        array $filterCabang = [],
        array $filterDivisi = [],
        array $filterSubDivisi = [],
        array $filterKodeKaryawan = [],
        array $exclusionKodes = [],
    ): array {
        $kodes = $this->buildFilteredKaryawanQuery($filterCabang, $filterDivisi, $filterSubDivisi, $filterKodeKaryawan)
            ->pluck('Kode_Karyawan')
            ->filter()
            ->values()
            ->all();

        if (! empty($exclusionKodes)) {
            $excluded = array_fill_keys(array_values($exclusionKodes), true);
            $kodes = array_values(array_filter($kodes, fn ($kode) => ! isset($excluded[$kode])));
        }

        return array_values(array_unique($kodes));
    }

    private function resolveTargetKaryawanCodesFromRecord(object $cutiBersama): array
    {
        $filterCabang = $this->decodeArrayValue($cutiBersama->Filter_Cabang ?? null);
        $filterDivisi = $this->decodeArrayValue($cutiBersama->Filter_Divisi ?? null);
        $filterSubDivisi = $this->decodeArrayValue($cutiBersama->Filter_Sub_Divisi ?? null);
        $filterKodeKaryawan = $this->decodeArrayValue($cutiBersama->Filter_Kode_Karyawan ?? null);

        $exclusionKodes = DB::table('N_HRIS_Cuti_Bersama_Exclusion')
            ->where('Id_Cuti_Bersama', $cutiBersama->Id_Cuti_Bersama)
            ->pluck('Kode_Karyawan')
            ->filter()
            ->values()
            ->all();

        return $this->resolveTargetKaryawanCodes(
            $filterCabang,
            $filterDivisi,
            $filterSubDivisi,
            $filterKodeKaryawan,
            $exclusionKodes,
        );
    }

    /**
     * Resolve effective target for overlap detection.
     * If execution detail exists, use active executed details only (SUCCESS/COMPLETED)
     * so karyawan that already ROLLED_BACK are not treated as conflicts.
     */
    private function resolveConflictTargetKaryawanCodesFromRecord(object $cutiBersama): array
    {
        $activeExecutedKodes = DB::table('N_HRIS_Cuti_Bersama_Detail')
            ->where('Id_Cuti_Bersama', $cutiBersama->Id_Cuti_Bersama)
            ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(ISNULL(Status, \'\'))))'), self::ROLLBACK_ELIGIBLE_STATUSES)
            ->pluck('Kode_Karyawan')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($activeExecutedKodes)) {
            return $activeExecutedKodes;
        }

        return $this->resolveTargetKaryawanCodesFromRecord($cutiBersama);
    }

    private function detectOverlapConflicts(
        array $selectedDates,
        array $targetKaryawanKodes,
        ?int $ignoreId = null,
    ): array {
        $selectedDates = array_values(array_unique(array_filter($selectedDates)));
        $targetKaryawanKodes = array_values(array_unique(array_filter($targetKaryawanKodes)));

        // dd($selectedDates, $targetKaryawanKodes);

        if (empty($selectedDates) || empty($targetKaryawanKodes)) {
            return [];
        }

        sort($selectedDates);
        $selectedDateSet = array_fill_keys($selectedDates, true);
        $targetSet = array_fill_keys($targetKaryawanKodes, true);

        // dd($selectedDateSet, $targetSet);

        $minDate = $selectedDates[0];
        $maxDate = $selectedDates[count($selectedDates) - 1];

        $candidateQuery = DB::table('N_HRIS_Cuti_Bersama')
            ->where('Flag_Aktif', 'Y')
            ->where('Tanggal_Mulai', '<=', $maxDate)
            ->where('Tanggal_Selesai', '>=', $minDate)
            ->orderBy('Tanggal_Mulai', 'asc');

        if ($ignoreId) {
            $candidateQuery->where('Id_Cuti_Bersama', '!=', $ignoreId);
        }

        $candidates = $candidateQuery->get();
        // dd($candidates);
        $conflicts = [];

        foreach ($candidates as $candidate) {
            $candidateDates = $this->extractSelectedDatesFromRecord($candidate);
            $overlapDates = array_values(array_filter($candidateDates, fn ($date) => isset($selectedDateSet[$date])));

            if (empty($overlapDates)) {
                continue;
            }

            $latestTask = $this->getLatestExecutionTaskLog((int) $candidate->Id_Cuti_Bersama);
            $latestRollbackTask = $this->getLatestRollbackTaskLog((int) $candidate->Id_Cuti_Bersama);
            $snapshot = $this->buildExecutionSnapshot($candidate, $latestTask, $latestRollbackTask);

            // Skip fully rolled-back cuti bersama — balances have been restored, no real conflict.
            if (($snapshot['status'] ?? '') === 'ROLLED_BACK') {
                continue;
            }

            $candidateTargetKodes = $this->resolveConflictTargetKaryawanCodesFromRecord($candidate);

            if (empty($candidateTargetKodes)) {
                continue;
            }

            $overlapKodes = [];
            foreach ($candidateTargetKodes as $kode) {
                if (isset($targetSet[$kode])) {
                    $overlapKodes[] = $kode;
                }
            }

            if (empty($overlapKodes)) {
                continue;
            }

            $blockingStates = ['EXECUTED', 'SCHEDULED', 'QUEUED', 'PROCESSING'];
            $isBlocking = in_array($snapshot['status'] ?? 'DRAFT', $blockingStates, true);

            $sampleKodes = array_slice($overlapKodes, 0, 5);
            $sampleKaryawan = DB::table('Karyawan')
                ->whereIn('Kode_Karyawan', $sampleKodes)
                ->select('Kode_Karyawan', 'Nama')
                ->orderBy('Nama')
                ->get()
                ->map(
                    fn ($row) => [
                        'kode_karyawan' => $row->Kode_Karyawan,
                        'nama' => $row->Nama,
                    ],
                )
                ->values()
                ->all();

            $conflicts[] = [
                'id_cuti_bersama' => (int) $candidate->Id_Cuti_Bersama,
                'nama_cuti_bersama' => $candidate->Nama_Cuti_Bersama,
                'status' => $snapshot['status'] ?? 'DRAFT',
                'status_label' => $snapshot['label'] ?? 'Draft',
                'blocking' => $isBlocking,
                'overlap_dates' => array_values($overlapDates),
                'overlap_karyawan_count' => count($overlapKodes),
                'overlap_karyawan_kodes' => array_values($overlapKodes),
                'sample_karyawan' => $sampleKaryawan,
                'scheduled_at' => $candidate->Scheduled_At ?? null,
                'executed_at' => $candidate->Executed_At ?? null,
            ];
        }

        usort($conflicts, function ($a, $b) {
            if ($a['blocking'] !== $b['blocking']) {
                return $a['blocking'] ? -1 : 1;
            }

            return $b['overlap_karyawan_count'] <=> $a['overlap_karyawan_count'];
        });

        return $conflicts;
    }

    private function buildOverlapSummary(array $conflicts): array
    {
        $blockingCount = count(array_filter($conflicts, fn ($item) => ! empty($item['blocking'])));

        return [
            'has_conflict' => ! empty($conflicts),
            'has_blocking_conflict' => $blockingCount > 0,
            'total_conflicts' => count($conflicts),
            'blocking_conflicts' => $blockingCount,
            'conflicts' => array_values($conflicts),
        ];
    }

    private function buildOverlapBlockMessage(array $overlapSummary): string
    {
        if (empty($overlapSummary['has_blocking_conflict'])) {
            return '';
        }

        $firstBlocking = collect($overlapSummary['conflicts'] ?? [])->first(fn ($item) => ! empty($item['blocking']));

        if (! $firstBlocking) {
            return 'Eksekusi ditolak karena terdapat overlap target dan tanggal dengan cuti bersama lain.';
        }

        $dates = implode(', ', array_slice($firstBlocking['overlap_dates'] ?? [], 0, 3));
        $suffixDates = ! empty($dates) ? " pada tanggal {$dates}" : '';

        return "Eksekusi ditolak karena overlap dengan \"{$firstBlocking['nama_cuti_bersama']}\"{$suffixDates}.";
    }

    /**
     * Get saldo cuti karyawan (from view)
     */
    private function getSaldoKaryawan(string $kodeKaryawan): float
    {
        $saldo = DB::table('N_HRIS_VW_Saldo_Cuti_Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->first();

        if (! $saldo) {
            return 0;
        }

        $sisa = $saldo->Sisa_Cuti_Tampil ?? 0;
        $hutang = $saldo->Hutang_Cuti_Tampil ?? 0;

        return $hutang > 0 ? -$hutang : $sisa;
    }

    /**
     * Saldo fisik untuk pencatatan mutasi (tidak termasuk proyeksi pending/CB future).
     */
    /**
     * Deduct $jumlahHari from jenis cuti 1 (Tahunan) and 2 (Loyalty) wallets
     * using FIFO by tanggal_expired across both types.
     *
     * Does NOT insert into N_HRIS_Cuti_Bersama_Detail_Alloc — returns the fragment
     * data array so the caller can set Id_Detail after inserting the detail row,
     * then bulk-insert all alloc records atomically.
     *
     * @return array alloc fragment rows (Id_Detail = 0 placeholder, caller must set)
     */
    private function consumeCutiBersamaWallets(
        int $idCutiBersama,
        string $kodeKaryawan,
        int $jumlahHari,
        string $keterangan,
        string $adminKode,
    ): array {
        $sisaPotong = (float) $jumlahHari;
        $fragments = [];

        // All active wallets for jenis cuti 1 & 2, FIFO by expiry
        $dompets = DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->whereIn('Id_Jenis_Cuti', [self::ID_JENIS_CUTI_TAHUNAN, self::ID_JENIS_CUTI_LOYALTY])
            ->where('sisa_cuti', '>', 0)
            ->where('Flag_Aktif', 'Y')
            ->whereDate('Activated_At', '<=', now())
            ->whereDate('tanggal_expired', '>=', now())
            ->orderBy('tanggal_expired', 'asc')
            ->lockForUpdate()
            ->get();

        foreach ($dompets as $d) {
            if ($sisaPotong <= 0) {
                break;
            }

            $ambil = min($sisaPotong, (float) $d->sisa_cuti);
            $saldoSblm = (float) $d->sisa_cuti;
            $saldoSsdh = $saldoSblm - $ambil;

            DB::table('HRIS_Buku_Cuti')
                ->where('Id_Buku_Cuti', $d->Id_Buku_Cuti)
                ->update([
                    'sisa_cuti' => DB::raw("sisa_cuti - $ambil"),
                    'jumlah_terpakai' => DB::raw("jumlah_terpakai + $ambil"),
                    'updated_at' => now(),
                ]);

            $this->transactionService->recordBalanceMutation(
                $d->Id_Buku_Cuti,
                null,
                'DEBIT',
                'CUTI_BERSAMA',
                $ambil,
                $saldoSblm,
                $saldoSsdh,
                $keterangan,
                $adminKode,
                null,
                now(),
            );

            $fragments[] = [
                'Id_Detail' => 0, // placeholder — caller sets this after detail insert
                'Id_Cuti_Bersama' => $idCutiBersama,
                'Kode_Karyawan' => $kodeKaryawan,
                'Id_Buku_Cuti' => $d->Id_Buku_Cuti,
                'Id_Jenis_Cuti' => $d->Id_Jenis_Cuti,
                'Periode' => $d->Periode,
                'Tanggal_Expired' => $d->tanggal_expired,
                'Jumlah_Potong' => $ambil,
                'Saldo_Sebelum_Wallet' => $saldoSblm,
                'Saldo_Sesudah_Wallet' => $saldoSsdh,
                'Is_Rolled_Back' => 0,
                'Created_At' => now(),
                'Created_By' => $adminKode,
            ];

            $sisaPotong -= $ambil;
        }

        // If saldo insufficient, push debt into jenis cuti 1 (Tahunan)
        if ($sisaPotong > 0) {
            $debtRow = DB::table('HRIS_Buku_Cuti')
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->where('Id_Jenis_Cuti', self::ID_JENIS_CUTI_TAHUNAN)
                ->where('sisa_cuti', '<', 0)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('tanggal_expired', 'asc')
                ->lockForUpdate()
                ->first();

            if ($debtRow) {
                $saldoSblm = (float) $debtRow->sisa_cuti;
                $saldoSsdh = $saldoSblm - $sisaPotong;

                DB::table('HRIS_Buku_Cuti')
                    ->where('Id_Buku_Cuti', $debtRow->Id_Buku_Cuti)
                    ->update([
                        'sisa_cuti' => DB::raw("sisa_cuti - $sisaPotong"),
                        'updated_at' => now(),
                    ]);

                $this->transactionService->recordBalanceMutation(
                    $debtRow->Id_Buku_Cuti,
                    null,
                    'DEBIT',
                    'CUTI_BERSAMA',
                    $sisaPotong,
                    $saldoSblm,
                    $saldoSsdh,
                    $keterangan,
                    $adminKode,
                    null,
                    now(),
                );

                $fragments[] = [
                    'Id_Detail' => 0,
                    'Id_Cuti_Bersama' => $idCutiBersama,
                    'Kode_Karyawan' => $kodeKaryawan,
                    'Id_Buku_Cuti' => $debtRow->Id_Buku_Cuti,
                    'Id_Jenis_Cuti' => self::ID_JENIS_CUTI_TAHUNAN,
                    'Periode' => $debtRow->Periode,
                    'Tanggal_Expired' => $debtRow->tanggal_expired,
                    'Jumlah_Potong' => $sisaPotong,
                    'Saldo_Sebelum_Wallet' => $saldoSblm,
                    'Saldo_Sesudah_Wallet' => $saldoSsdh,
                    'Is_Rolled_Back' => 0,
                    'Created_At' => now(),
                    'Created_By' => $adminKode,
                ];
            } else {
                // No existing debt row — create new debt wallet for jenis cuti Tahunan
                $today = now()->toDateString();
                $tanggalExpired = Carbon::now()->addYear()->endOfYear()->toDateString();

                $debtId = $this->transactionService->createOrUpdateDebtBalance(
                    $kodeKaryawan,
                    self::ID_JENIS_CUTI_TAHUNAN,
                    (float) $sisaPotong,
                    'CB-'.$idCutiBersama,
                    'CUTI_BERSAMA',
                    $keterangan,
                    $adminKode,
                    'Tahunan',
                    $tanggalExpired,
                    now()->year,
                    $today,
                    'Hutang_Cuti',
                );

                $fragments[] = [
                    'Id_Detail' => 0,
                    'Id_Cuti_Bersama' => $idCutiBersama,
                    'Kode_Karyawan' => $kodeKaryawan,
                    'Id_Buku_Cuti' => $debtId,
                    'Id_Jenis_Cuti' => self::ID_JENIS_CUTI_TAHUNAN,
                    'Periode' => now()->year,
                    'Tanggal_Expired' => $tanggalExpired,
                    'Jumlah_Potong' => $sisaPotong,
                    'Saldo_Sebelum_Wallet' => 0,
                    'Saldo_Sesudah_Wallet' => -$sisaPotong,
                    'Is_Rolled_Back' => 0,
                    'Created_At' => now(),
                    'Created_By' => $adminKode,
                ];
            }
        }

        return $fragments;
    }

    /**
     * Restore saldo by reversing each alloc fragment to its exact original wallet (Id_Buku_Cuti).
     * This works even if the wallet has since expired.
     *
     * Returns total amount restored, or 0.0 if no alloc records exist (legacy execution).
     */
    private function rollbackCutiBersamaByAlloc(int $idDetail, string $keterangan, string $adminKode): float
    {
        $allocs = DB::table('N_HRIS_Cuti_Bersama_Detail_Alloc')
            ->where('Id_Detail', $idDetail)
            ->where('Is_Rolled_Back', 0)
            ->lockForUpdate()
            ->get();

        if ($allocs->isEmpty()) {
            return 0.0;
        }

        $totalRestored = 0.0;

        foreach ($allocs as $alloc) {
            $jumlah = (float) $alloc->Jumlah_Potong;
            $currentSaldo =
                (float) (DB::table('HRIS_Buku_Cuti')
                    ->where('Id_Buku_Cuti', $alloc->Id_Buku_Cuti)
                    ->lockForUpdate()
                    ->value('sisa_cuti') ?? $alloc->Saldo_Sesudah_Wallet);

            $walletUpdate = [
                'sisa_cuti' => DB::raw("sisa_cuti + $jumlah"),
                'updated_at' => now(),
            ];

            if ((float) $alloc->Saldo_Sebelum_Wallet >= 0) {
                $walletUpdate['jumlah_terpakai'] = DB::raw("jumlah_terpakai - $jumlah");
            }

            DB::table('HRIS_Buku_Cuti')
                ->where('Id_Buku_Cuti', $alloc->Id_Buku_Cuti)
                ->update($walletUpdate);

            $this->transactionService->recordBalanceMutation(
                $alloc->Id_Buku_Cuti,
                null,
                'CREDIT',
                'CUTI_BERSAMA_ROLLBACK',
                $jumlah,
                $currentSaldo,
                $currentSaldo + $jumlah,
                $keterangan,
                $adminKode,
                null,
                now(),
            );

            DB::table('N_HRIS_Cuti_Bersama_Detail_Alloc')
                ->where('Id_Alloc', $alloc->Id_Alloc)
                ->update([
                    'Is_Rolled_Back' => 1,
                    'Rolled_Back_At' => now(),
                    'Rolled_Back_By' => $adminKode,
                ]);

            $totalRestored += $jumlah;
        }

        return $totalRestored;
    }

    private function getSaldoKaryawanFisik(string $kodeKaryawan): float
    {
        $row = DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Flag_Aktif', 'Y')
            ->whereIn('Id_Jenis_Cuti', [self::ID_JENIS_CUTI_TAHUNAN, self::ID_JENIS_CUTI_LOYALTY])
            ->where(function ($q) {
                $q->where(function ($qDate) {
                    $today = now()->toDateString();
                    $qDate->whereDate('tanggal_expired', '>=', $today)->whereDate('Activated_At', '<=', $today);
                })->orWhere('sisa_cuti', '<', 0);
            })
            ->selectRaw('COALESCE(SUM(sisa_cuti), 0) as Total_Saldo_DB')
            ->first();

        return (float) ($row->Total_Saldo_DB ?? 0);
    }

    private function buildDraftImpactDetails(
        object $cutiBersama,
        array $exclusionKodes = [],
        string $searchDetail = '',
        string $statusFilter = '',
        int $page = 1,
        int $perPage = 50,
    ): array {
        $selectedDates = $this->extractSelectedDatesFromRecord($cutiBersama);
        $jumlahHari = count($selectedDates);

        $filterCabang = $cutiBersama->Filter_Cabang ? json_decode($cutiBersama->Filter_Cabang, true) : [];
        $filterDivisi = $cutiBersama->Filter_Divisi ? json_decode($cutiBersama->Filter_Divisi, true) : [];
        $filterSubDivisi = $cutiBersama->Filter_Sub_Divisi ? json_decode($cutiBersama->Filter_Sub_Divisi, true) : [];
        $filterKodeKaryawan = $cutiBersama->Filter_Kode_Karyawan
            ? json_decode($cutiBersama->Filter_Kode_Karyawan, true)
            : [];

        $detailQuery = $this->buildFilteredKaryawanQuery(
            is_array($filterCabang) ? $filterCabang : [],
            is_array($filterDivisi) ? $filterDivisi : [],
            is_array($filterSubDivisi) ? $filterSubDivisi : [],
            is_array($filterKodeKaryawan) ? $filterKodeKaryawan : [],
        )
            ->leftJoin('N_HRIS_VW_Saldo_Cuti_Karyawan as saldo', 'k.Kode_Karyawan', '=', 'saldo.Kode_Karyawan')
            ->selectRaw(
                '
                k.Kode_Karyawan,
                k.Nama as Nama_Karyawan,
                k.Cabang_Untuk_Gaji as Cabang,
                vd.nama_divisi as Divisi,
                vd.nama_sub_divisi as Sub_Divisi,
                CASE
                    WHEN ISNULL(saldo.Hutang_Cuti_Tampil, 0) > 0
                        THEN (-ISNULL(saldo.Hutang_Cuti_Tampil, 0) + ?)
                    ELSE (ISNULL(saldo.Sisa_Cuti_Tampil, 0) + ?)
                END as Saldo_Sebelum,
                CASE
                    WHEN ISNULL(saldo.Hutang_Cuti_Tampil, 0) > 0
                        THEN -ISNULL(saldo.Hutang_Cuti_Tampil, 0)
                    ELSE ISNULL(saldo.Sisa_Cuti_Tampil, 0)
                END as Saldo_Sesudah
            ',
                [$jumlahHari, $jumlahHari],
            );

        if (! empty($exclusionKodes)) {
            $detailQuery->whereNotIn('k.Kode_Karyawan', $exclusionKodes);
        }

        if ($searchDetail) {
            $detailQuery->where(function ($q) use ($searchDetail) {
                $q->where('k.Nama', 'like', "%{$searchDetail}%")->orWhere(
                    'k.Kode_Karyawan',
                    'like',
                    "%{$searchDetail}%",
                );
            });
        }

        if ($statusFilter && strtoupper($statusFilter) !== 'PREVIEW') {
            return [collect(), 0];
        }

        $totalDetail = (clone $detailQuery)->count();
        $rows = $detailQuery
            ->orderBy('k.Nama')
            ->offset(max(0, ($page - 1) * $perPage))
            ->limit($perPage)
            ->get()
            ->map(function ($row, $index) use ($page, $perPage, $jumlahHari) {
                $row->Id_Detail = 'draft-'.(($page - 1) * $perPage + $index + 1);
                $row->Jumlah_Hari_Dipotong = $jumlahHari;
                $row->Status = 'PREVIEW';
                $row->Created_At = null;
                $row->Created_By = null;

                return $row;
            });

        return [$rows, $totalDetail];
    }

    private function resolveExecutionSchedule(string $executionMode, $scheduledAtInput = null): ?Carbon
    {
        if ($executionMode !== 'scheduled') {
            return null;
        }

        if (empty($scheduledAtInput)) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['Pilih tanggal dan jam eksekusi untuk mode terjadwal.'],
            ]);
        }

        $scheduledAt = Carbon::parse($scheduledAtInput);

        if ($scheduledAt->lte(now())) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['Tanggal dan jam eksekusi harus lebih besar dari waktu sekarang.'],
            ]);
        }

        return $scheduledAt;
    }

    private function getLatestExecutionTaskLog(int $idCutiBersama): ?object
    {
        return DB::table('N_HRIS_Cloud_Task_Log')
            ->where('Reference_Id', $idCutiBersama)
            ->where('Task_Type', 'CUTI_BERSAMA_EXECUTE')
            ->orderByDesc('Created_At')
            ->orderByDesc('Id_Log')
            ->first();
    }

    private function getLatestRollbackTaskLog(int $idCutiBersama): ?object
    {
        return DB::table('N_HRIS_Cloud_Task_Log')
            ->where('Reference_Id', $idCutiBersama)
            ->where('Task_Type', 'CUTI_BERSAMA_ROLLBACK')
            ->orderByDesc('Created_At')
            ->orderByDesc('Id_Log')
            ->first();
    }

    private function hasActiveExecutionTask(int $idCutiBersama): bool
    {
        return DB::table('N_HRIS_Cloud_Task_Log')
            ->where('Reference_Id', $idCutiBersama)
            ->where('Task_Type', 'CUTI_BERSAMA_EXECUTE')
            ->whereIn('Status', ['DISPATCHED', 'PROCESSING'])
            ->exists();
    }

    private function hasActiveRollbackTask(int $idCutiBersama): bool
    {
        return DB::table('N_HRIS_Cloud_Task_Log')
            ->where('Reference_Id', $idCutiBersama)
            ->where('Task_Type', 'CUTI_BERSAMA_ROLLBACK')
            ->whereIn('Status', ['DISPATCHED', 'PROCESSING'])
            ->exists();
    }

    private function cancelScheduledExecutionTaskById(int $idCutiBersama, string $adminKode): array
    {
        $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $idCutiBersama)->first();

        if (! $cb) {
            throw new \RuntimeException('Cuti bersama tidak ditemukan');
        }

        if (($cb->Flag_Executed ?? null) === 'Y') {
            throw new \RuntimeException('Cuti bersama sudah dieksekusi dan tidak bisa dibatalkan jadwalnya.');
        }

        if (($cb->Flag_Aktif ?? null) === 'T') {
            return [
                'cloud_task_log_id' => null,
                'cloud_task_deleted' => false,
                'cloud_task_delete_status' => 'skipped_by_design',
                'cloud_task_name' => null,
                'cloud_task_note' => 'Transaksi sudah dibatalkan sebelumnya.',
                'message' => 'Transaksi sudah dibatalkan sebelumnya.',
            ];
        }

        $latestTask = $this->getLatestExecutionTaskLog($idCutiBersama);

        if (! $latestTask) {
            DB::table('N_HRIS_Cuti_Bersama')
                ->where('Id_Cuti_Bersama', $idCutiBersama)
                ->update([
                    'Flag_Aktif' => 'T',
                    'Scheduled_At' => null,
                    'Updated_At' => now(),
                    'Updated_By' => $adminKode,
                ]);

            return [
                'cloud_task_log_id' => null,
                'cloud_task_deleted' => false,
                'cloud_task_delete_status' => 'skipped_by_design',
                'cloud_task_name' => null,
                'cloud_task_note' => 'Tidak ada Cloud Task aktif. Transaksi dibatalkan secara langsung.',
                'message' => 'Transaksi berhasil dibatalkan.',
            ];
        }

        $status = strtoupper(trim((string) ($latestTask->Status ?? '')));

        if ($status === 'PROCESSING') {
            throw new \RuntimeException('Task sedang diproses, tidak bisa dibatalkan.');
        }

        if (! in_array($status, ['DISPATCHED', 'CANCELLED', 'FAILED'], true)) {
            throw new \RuntimeException('Task terakhir tidak dalam status aktif yang bisa dibatalkan.');
        }

        $payload = $this->decodeJsonObjectValue($latestTask->Payload ?? null);
        $cloudTaskName = trim((string) ($payload['_gcp_task_name'] ?? ''));

        $cloudTaskDeleteStatus = 'skipped_by_design';
        $cloudDeleteMessage =
            'Cloud Task tidak dihapus by design untuk mencegah race condition. Callback akan skip idempotent saat transaksi sudah dibatalkan.';

        $resultSummary = [
            'cancelled' => true,
            'cancelled_by' => $adminKode,
            'cancelled_at' => now()->toDateTimeString(),
            'cloud_task_deleted' => false,
            'cloud_task_delete_status' => $cloudTaskDeleteStatus,
            'cloud_task_name' => $cloudTaskName !== '' ? $cloudTaskName : null,
            'cancel_mode' => 'logical_only',
            'cancel_reason' => 'soft_deleted_flag',
        ];

        DB::table('N_HRIS_Cloud_Task_Log')
            ->where('Id_Log', $latestTask->Id_Log)
            ->update([
                'Status' => 'CANCELLED',
                'Completed_At' => now(),
                'Result_Summary' => json_encode($resultSummary),
                'Error_Message' => $cloudDeleteMessage ? substr($cloudDeleteMessage, 0, 2000) : null,
            ]);

        DB::table('N_HRIS_Cuti_Bersama')
            ->where('Id_Cuti_Bersama', $idCutiBersama)
            ->update([
                'Flag_Aktif' => 'T',
                'Scheduled_At' => null,
                'Updated_At' => now(),
                'Updated_By' => $adminKode,
            ]);

        return [
            'cloud_task_log_id' => (int) $latestTask->Id_Log,
            'cloud_task_deleted' => false,
            'cloud_task_delete_status' => $cloudTaskDeleteStatus,
            'cloud_task_name' => $cloudTaskName !== '' ? $cloudTaskName : null,
            'cloud_task_note' => $cloudDeleteMessage,
            'message' => 'Transaksi berhasil dibatalkan. Cloud Task tidak dihapus dan akan di-skip secara aman saat callback berjalan.',
        ];
    }

    /**
     * Klaim + kirim. Dipakai pemanggil yang memang SUDAH di luar transaksi.
     */
    private function dispatchExecutionTaskById(
        int $idCutiBersama,
        string $adminKode,
        ?Carbon $scheduledAt = null,
    ): array {
        $hasil = $this->klaimExecutionTaskById($idCutiBersama, $adminKode, $scheduledAt);

        $this->cloudTaskService->kirim($hasil['cloud_task_log_id']);

        return $hasil;
    }

    /**
     * Hanya MENGKLAIM: validasi di bawah kunci, lalu catat niatnya. Tanpa
     * jaringan, sehingga aman dipanggil di dalam transaksi ber-lockForUpdate —
     * justru kuncinya yang membuat klaim ini tak bisa kembar.
     *
     * Pengirimannya WAJIB dilakukan pemanggil sesudah commit.
     */
    private function klaimExecutionTaskById(
        int $idCutiBersama,
        string $adminKode,
        ?Carbon $scheduledAt = null,
    ): array {
        $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $idCutiBersama)->first();

        if (! $cb) {
            throw new \RuntimeException('Cuti bersama tidak ditemukan');
        }

        if ($cb->Flag_Executed === 'Y') {
            throw new \RuntimeException('Cuti bersama sudah dieksekusi');
        }

        if ($this->hasActiveExecutionTask($idCutiBersama)) {
            throw new \RuntimeException('Cuti bersama ini sudah memiliki task aktif');
        }

        $selectedDates = $this->extractSelectedDatesFromRecord($cb);
        $targetKodes = $this->resolveTargetKaryawanCodesFromRecord($cb);
        $overlapSummary = $this->buildOverlapSummary(
            $this->detectOverlapConflicts($selectedDates, $targetKodes, $idCutiBersama),
        );

        if (! empty($overlapSummary['has_blocking_conflict'])) {
            throw new \RuntimeException($this->buildOverlapBlockMessage($overlapSummary));
        }

        DB::table('N_HRIS_Cuti_Bersama')
            ->where('Id_Cuti_Bersama', $idCutiBersama)
            ->update([
                'Scheduled_At' => $scheduledAt,
                'Updated_At' => now(),
                'Updated_By' => $adminKode,
            ]);

        $payload = [
            'id_cuti_bersama' => $idCutiBersama,
            'admin_kode' => $adminKode,
        ];

        $url = $this->resolveCloudTaskExecuteUrl();
        // dd($url);
        $queueName = 'cuti-bersama-queue';

        $logId = $this->cloudTaskService->catat(
            url: $url,
            queue: $queueName,
            payload: $payload,
            taskType: 'CUTI_BERSAMA_EXECUTE',
            referenceId: $idCutiBersama,
            referenceNo: $cb->Nama_Cuti_Bersama,
            dispatchedBy: $adminKode,
            scheduledAt: $scheduledAt,
        );

        return [
            'cloud_task_log_id' => $logId,
            'scheduled_at' => $scheduledAt,
            'message' => $scheduledAt
                ? 'Eksekusi dijadwalkan pada '.$scheduledAt->format('d/m/Y H:i')
                : 'Eksekusi berhasil di-dispatch ke Cloud Task',
        ];
    }

    /** Klaim + kirim, untuk pemanggil yang sudah di luar transaksi. */
    private function dispatchRollbackTaskById(int $idCutiBersama, array $rollbackPayload, string $adminKode): array
    {
        $hasil = $this->klaimRollbackTaskById($idCutiBersama, $rollbackPayload, $adminKode);

        $this->cloudTaskService->kirim($hasil['cloud_task_log_id']);

        return $hasil;
    }

    /** Hanya mengklaim; pengirimannya wajib dilakukan pemanggil sesudah commit. */
    private function klaimRollbackTaskById(int $idCutiBersama, array $rollbackPayload, string $adminKode): array
    {
        $cb = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $idCutiBersama)->first();

        if (! $cb) {
            throw new \RuntimeException('Cuti bersama tidak ditemukan');
        }

        if (($cb->Flag_Executed ?? null) !== 'Y') {
            throw new \RuntimeException('Cuti bersama belum dieksekusi');
        }

        if ($this->hasActiveRollbackTask($idCutiBersama)) {
            throw new \RuntimeException('Rollback untuk cuti bersama ini sudah memiliki task aktif');
        }

        $payload = [
            'id_cuti_bersama' => $idCutiBersama,
            'admin_kode' => $adminKode,
            'rollback_payload' => $rollbackPayload,
        ];

        $logId = $this->cloudTaskService->catat(
            url: $this->resolveCloudTaskRollbackUrl(),
            queue: 'cuti-bersama-queue',
            payload: $payload,
            taskType: 'CUTI_BERSAMA_ROLLBACK',
            referenceId: $idCutiBersama,
            referenceNo: $cb->Nama_Cuti_Bersama,
            dispatchedBy: $adminKode,
            delayInSeconds: 0,
        );

        return [
            'cloud_task_log_id' => $logId,
            'message' => 'Rollback berhasil dikirim ke Cloud Task dan langsung masuk antrean proses.',
        ];
    }

    /**
     * @param  array|null  $ringkasanDetail  Ringkasan yang SUDAH diambil massal oleh
     *                                       pemanggil. Bila null, diambil sendiri per
     *                                       baris — jalur lama yang masih dipakai
     *                                       detail() dan pemeriksaan bentrok.
     */
    private function buildExecutionSnapshot(
        object $cutiBersama,
        ?object $latestTask,
        ?object $latestRollbackTask = null,
        ?array $ringkasanDetail = null,
    ): array {
        $snapshot = [
            'status' => 'DRAFT',
            'label' => 'Draft',
            'time' => null,
            'time_label' => null,
            'has_active_task' => false,
            'can_execute' => true,
            'can_modify' => true,
            'reason' => null,
        ];

        $detailSummary = $ringkasanDetail ?? $this->getExecutionDetailSummary($cutiBersama);

        if (
            ($detailSummary['total_rolled_back'] ?? 0) > 0 &&
            ($detailSummary['remaining_active_eligible'] ?? 0) === 0
        ) {
            $snapshot['status'] = 'ROLLED_BACK';
            $snapshot['label'] = 'Rolled Back';
            $snapshot['time'] = $detailSummary['last_rolled_back_at'] ?? null;
            $snapshot['time_label'] = 'Rollback terakhir';
            $snapshot['can_execute'] = false;
            $snapshot['can_modify'] = false;
            $snapshot['reason'] = 'Cuti bersama ini sudah di roll back semua.';

            return $snapshot;
        }

        if (($detailSummary['total_rolled_back'] ?? 0) > 0 && ($detailSummary['remaining_active_eligible'] ?? 0) > 0) {
            $snapshot['status'] = 'PARTIAL_ROLLBACK';
            $snapshot['label'] = 'Partial Rollback';
            $snapshot['time'] = $detailSummary['last_rolled_back_at'] ?? null;
            $snapshot['time_label'] = 'Rollback terakhir';
            $snapshot['can_execute'] = false;
            $snapshot['can_modify'] = false;
            $snapshot['reason'] =
                'Sebagian target sudah di-rollback. Masih ada '.
                (int) ($detailSummary['remaining_active_eligible'] ?? 0).
                ' target aktif.';
        }

        $rollbackTaskStatus = strtoupper((string) ($latestRollbackTask->Status ?? ''));
        if (($cutiBersama->Flag_Executed ?? null) === 'Y' && $rollbackTaskStatus === 'PROCESSING') {
            $snapshot['status'] = 'ROLLBACK_PROCESSING';
            $snapshot['label'] = 'Rollback Processing';
            $snapshot['time'] = $latestRollbackTask->Started_At ?? $latestRollbackTask->Created_At;
            $snapshot['time_label'] = 'Rollback diproses';
            $snapshot['has_active_task'] = true;
            $snapshot['can_execute'] = false;
            $snapshot['can_modify'] = false;
            $snapshot['reason'] = 'Rollback sedang diproses di background.';

            return $snapshot;
        }

        if (($cutiBersama->Flag_Executed ?? null) === 'Y' && $rollbackTaskStatus === 'DISPATCHED') {
            $snapshot['status'] = 'ROLLBACK_QUEUED';
            $snapshot['label'] = 'Rollback Queued';
            $snapshot['time'] = $latestRollbackTask->Created_At;
            $snapshot['time_label'] = 'Rollback didispatch';
            $snapshot['has_active_task'] = true;
            $snapshot['can_execute'] = false;
            $snapshot['can_modify'] = false;
            $snapshot['reason'] = 'Rollback sudah dikirim ke Cloud Task dan menunggu diproses.';

            return $snapshot;
        }

        if (($cutiBersama->Flag_Executed ?? null) === 'Y' && $rollbackTaskStatus === 'FAILED') {
            $snapshot['status'] = 'ROLLBACK_FAILED';
            $snapshot['label'] = 'Rollback Failed';
            $snapshot['time'] = $latestRollbackTask->Completed_At ?? $latestRollbackTask->Created_At;
            $snapshot['time_label'] = 'Rollback gagal';
            $snapshot['can_execute'] = false;
            $snapshot['can_modify'] = false;
            $snapshot['reason'] = $latestRollbackTask->Error_Message ?? 'Rollback terakhir gagal diproses.';

            return $snapshot;
        }

        if (($cutiBersama->Flag_Executed ?? null) === 'Y') {
            $snapshot['status'] = $snapshot['status'] === 'PARTIAL_ROLLBACK' ? 'PARTIAL_ROLLBACK' : 'EXECUTED';
            $snapshot['label'] = $snapshot['status'] === 'PARTIAL_ROLLBACK' ? 'Partial Rollback' : 'Executed';
            $snapshot['time'] =
                $snapshot['status'] === 'PARTIAL_ROLLBACK'
                    ? $detailSummary['last_rolled_back_at'] ??
                        ($cutiBersama->Executed_At ?? ($latestTask->Completed_At ?? null))
                    : $cutiBersama->Executed_At ?? ($latestTask->Completed_At ?? null);
            $snapshot['time_label'] = $snapshot['status'] === 'PARTIAL_ROLLBACK' ? 'Rollback terakhir' : 'Dieksekusi';
            $snapshot['can_execute'] = false;
            $snapshot['can_modify'] = false;
            $snapshot['reason'] =
                $snapshot['status'] === 'PARTIAL_ROLLBACK' ? $snapshot['reason'] : 'Cuti bersama ini sudah dieksekusi.';

            return $snapshot;
        }

        if (! $latestTask) {
            return $snapshot;
        }

        $status = strtoupper((string) ($latestTask->Status ?? 'DRAFT'));
        $scheduledAt = $latestTask->Scheduled_At ?? ($cutiBersama->Scheduled_At ?? null);

        if ($status === 'PROCESSING') {
            $snapshot['status'] = 'PROCESSING';
            $snapshot['label'] = 'Processing';
            $snapshot['time'] = $latestTask->Started_At ?? $latestTask->Created_At;
            $snapshot['time_label'] = 'Mulai diproses';
            $snapshot['has_active_task'] = true;
            $snapshot['can_execute'] = false;
            $snapshot['can_modify'] = false;
            $snapshot['reason'] = 'Task sedang diproses.';

            return $snapshot;
        }

        if ($status === 'DISPATCHED') {
            $snapshot['status'] = $scheduledAt ? 'SCHEDULED' : 'QUEUED';
            $snapshot['label'] = $scheduledAt ? 'Scheduled' : 'Queued';
            $snapshot['time'] = $scheduledAt ?? $latestTask->Created_At;
            $snapshot['time_label'] = $scheduledAt ? 'Dijadwalkan' : 'Didispatch';
            $snapshot['has_active_task'] = true;
            $snapshot['can_execute'] = false;
            $snapshot['can_modify'] = false;
            $snapshot['reason'] = $scheduledAt
                ? 'Task sudah dijadwalkan dan menunggu waktu eksekusi.'
                : 'Task sudah didispatch ke Cloud Task.';

            return $snapshot;
        }

        if ($status === 'CANCELLED') {
            $snapshot['status'] = 'CANCELLED';
            $snapshot['label'] = 'Cancelled';
            $snapshot['time'] = $latestTask->Completed_At ?? $latestTask->Created_At;
            $snapshot['time_label'] = 'Dibatalkan pada';
            $snapshot['has_active_task'] = false;
            $snapshot['can_execute'] = true;
            $snapshot['can_modify'] = true;
            $snapshot['reason'] = 'Jadwal task sebelumnya sudah dibatalkan.';

            return $snapshot;
        }

        if ($status === 'FAILED') {
            $snapshot['status'] = 'FAILED';
            $snapshot['label'] = 'Failed';
            $snapshot['time'] = $latestTask->Completed_At ?? $latestTask->Created_At;
            $snapshot['time_label'] = 'Gagal pada';
            $snapshot['reason'] = $latestTask->Error_Message ?? 'Task terakhir gagal.';

            return $snapshot;
        }

        if ($status === 'COMPLETED') {
            $snapshot['status'] = 'COMPLETED';
            $snapshot['label'] = 'Completed';
            $snapshot['time'] = $latestTask->Completed_At ?? $latestTask->Created_At;
            $snapshot['time_label'] = 'Selesai';
            $snapshot['can_execute'] = false;
            $snapshot['can_modify'] = false;
            $snapshot['reason'] = 'Task terakhir sudah selesai.';
        }

        return $snapshot;
    }

    private function getExecutionDetailSummary(object $cutiBersama): array
    {
        if (isset($cutiBersama->detail_counts) && is_object($cutiBersama->detail_counts)) {
            $detailCounts = $cutiBersama->detail_counts;
            $lastRolledBackAt = DB::table('N_HRIS_Cuti_Bersama_Detail')
                ->where('Id_Cuti_Bersama', $cutiBersama->Id_Cuti_Bersama)
                ->where('Status', 'ROLLED_BACK')
                ->max('Rolled_Back_At');

            return [
                'total_detail' => (int) ($detailCounts->total_detail ?? 0),
                'total_rolled_back' => (int) ($detailCounts->total_rolled_back ?? 0),
                'remaining_active_eligible' => (int) (($detailCounts->total_success ?? 0) + ($detailCounts->total_completed ?? 0)),
                'last_rolled_back_at' => $lastRolledBackAt,
            ];
        }

        $summary = DB::table('N_HRIS_Cuti_Bersama_Detail')
            ->where('Id_Cuti_Bersama', $cutiBersama->Id_Cuti_Bersama)
            ->selectRaw(
                "
                COUNT(*) as total_detail,
                SUM(
                    CASE
                        WHEN UPPER(LTRIM(RTRIM(ISNULL(Status, '')))) IN ('SUCCESS', 'COMPLETED') THEN 1
                        ELSE 0
                    END
                ) as remaining_active_eligible,
                SUM(CASE WHEN Status = 'ROLLED_BACK' THEN 1 ELSE 0 END) as total_rolled_back,
                MAX(Rolled_Back_At) as last_rolled_back_at
            ",
            )
            ->first();

        return [
            'total_detail' => (int) ($summary->total_detail ?? 0),
            'total_rolled_back' => (int) ($summary->total_rolled_back ?? 0),
            'remaining_active_eligible' => (int) ($summary->remaining_active_eligible ?? 0),
            'last_rolled_back_at' => $summary->last_rolled_back_at ?? null,
        ];
    }

    private function resolveCloudTaskExecuteUrl(): string
    {
        $configuredBaseUrl = trim((string) config('app.url', ''));

        if ($configuredBaseUrl === '' && request()) {
            $configuredBaseUrl = request()->getSchemeAndHttpHost();
        }

        if ($configuredBaseUrl === '') {
            throw new \RuntimeException('APP_URL belum dikonfigurasi untuk Cloud Task callback.');
        }

        if (! preg_match('~^https?://~i', $configuredBaseUrl)) {
            $scheme = request() ? request()->getScheme() : 'https';
            $configuredBaseUrl = $scheme.'://'.ltrim($configuredBaseUrl, '/');
        }

        $fullUrl = rtrim($configuredBaseUrl, '/').'/api/cloud-task/cuti-bersama-execute';

        if (! filter_var($fullUrl, FILTER_VALIDATE_URL) || ! preg_match('~^https?://~i', $fullUrl)) {
            throw new \RuntimeException(
                'URL callback Cloud Task tidak valid. Pastikan APP_URL memakai http:// atau https://.',
            );
        }

        return $fullUrl;
    }

    private function resolveCloudTaskRollbackUrl(): string
    {
        $configuredBaseUrl = trim((string) config('app.url', ''));

        if ($configuredBaseUrl === '' && request()) {
            $configuredBaseUrl = request()->getSchemeAndHttpHost();
        }

        if ($configuredBaseUrl === '') {
            throw new \RuntimeException('APP_URL belum dikonfigurasi untuk Cloud Task callback.');
        }

        if (! preg_match('~^https?://~i', $configuredBaseUrl)) {
            $scheme = request() ? request()->getScheme() : 'https';
            $configuredBaseUrl = $scheme.'://'.ltrim($configuredBaseUrl, '/');
        }

        $fullUrl = rtrim($configuredBaseUrl, '/').'/api/cloud-task/cuti-bersama-rollback';

        if (! filter_var($fullUrl, FILTER_VALIDATE_URL) || ! preg_match('~^https?://~i', $fullUrl)) {
            throw new \RuntimeException(
                'URL callback Cloud Task rollback tidak valid. Pastikan APP_URL memakai http:// atau https://.',
            );
        }

        return $fullUrl;
    }

    /**
     * Resolve jenis cuti ID from tipe string
     */
    private function resolveJenisCutiId(?string $tipeCuti): int
    {
        if (! $tipeCuti || $tipeCuti === 'Tahunan') {
            return self::ID_JENIS_CUTI_TAHUNAN;
        }

        $jenisCuti = DB::table('N_HRIS_Jenis_Cuti')
            ->where('Nama_Jenis_Cuti', 'like', "%{$tipeCuti}%")
            ->where('Flag_Aktif', 'Y')
            ->first();

        return $jenisCuti ? $jenisCuti->Id_Jenis_Cuti : self::ID_JENIS_CUTI_TAHUNAN;
    }

    /**
     * Schema::hasColumn() menembak katalog sistem tiap kali dipanggil.
     *
     * Pemanggilnya ada di jalur per-baris (extractSelectedDatesFromRecord),
     * sehingga daftar 16 baris menghasilkan 16 kueri katalog yang jawabannya
     * pasti sama — bentuk tabel tidak berubah di tengah satu permintaan.
     * Diingat sekali per instance controller, yaitu sekali per permintaan.
     */
    private function hasSelectedDatesColumn(): bool
    {
        return $this->adaKolomSelectedDates ??= Schema::hasColumn('N_HRIS_Cuti_Bersama', 'Selected_Dates');
    }

    private function normalizeSelectedDates(
        array $selectedDates = [],
        ?string $tanggalMulai = null,
        ?string $tanggalSelesai = null,
    ): array {
        $normalized = collect($selectedDates)
            ->filter()
            ->map(function ($date) {
                return Carbon::parse($date)->toDateString();
            })
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (! empty($normalized)) {
            return $normalized;
        }

        if ($tanggalMulai && $tanggalSelesai) {
            return $this->buildDateRange(Carbon::parse($tanggalMulai), Carbon::parse($tanggalSelesai));
        }

        if ($tanggalMulai) {
            return [Carbon::parse($tanggalMulai)->toDateString()];
        }

        return [];
    }

    private function buildDateRange(Carbon $start, Carbon $end): array
    {
        $from = $start->copy()->startOfDay();
        $to = $end->copy()->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $dates = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    private function extractSelectedDatesFromRecord(object $record): array
    {
        if ($this->hasSelectedDatesColumn() && ! empty($record->Selected_Dates)) {
            $decoded = json_decode($record->Selected_Dates, true);
            if (is_array($decoded)) {
                return $this->normalizeSelectedDates($decoded);
            }
        }

        $meta = $this->parseStoredKeterangan($record->Keterangan ?? null);
        if (! empty($meta['selected_dates']) && is_array($meta['selected_dates'])) {
            return $this->normalizeSelectedDates($meta['selected_dates']);
        }

        return $this->normalizeSelectedDates([], $record->Tanggal_Mulai ?? null, $record->Tanggal_Selesai ?? null);
    }

    private function extractPlainKeterangan(?string $keterangan): ?string
    {
        $meta = $this->parseStoredKeterangan($keterangan);

        if ($meta !== null) {
            return $meta['text'] ?? null;
        }

        return $keterangan;
    }

    private function buildStoredKeterangan(?string $keterangan, array $selectedDates): ?string
    {
        if ($this->hasSelectedDatesColumn()) {
            return $keterangan;
        }

        $payload = json_encode(
            [
                '__cuti_bersama_meta' => true,
                'text' => $keterangan,
                'selected_dates' => array_values($selectedDates),
            ],
            JSON_UNESCAPED_UNICODE,
        );

        if ($payload !== false && strlen($payload) > 500) {
            throw ValidationException::withMessages([
                'selected_dates' => [
                    'Kolom Selected_Dates belum tersedia. Tambahkan kolom tersebut di SQL Server terlebih dahulu.',
                ],
            ]);
        }

        return $payload ?: $keterangan;
    }

    private function parseStoredKeterangan(?string $keterangan): ?array
    {
        if (! $keterangan) {
            return null;
        }

        $decoded = json_decode($keterangan, true);

        if (! is_array($decoded) || empty($decoded['__cuti_bersama_meta'])) {
            return null;
        }

        return $decoded;
    }
}
