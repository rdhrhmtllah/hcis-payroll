<?php

namespace App\Services\ESS\Attendance\Overtime\Request;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\WhatsappLogHelper;
use App\Models\ESS\Attendance\Overtime\Models\Lembur;
use App\Models\ESS\Attendance\Overtime\Models\LemburDet;
use App\Models\ESS\Attendance\Overtime\Models\LemburDetail;
use App\Models\ESS\Employee\Models\Karyawan;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use App\Services\SYSTEM\Governance\Authorization\Shared\AccessContext;
use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;
use App\Services\SYSTEM\Notification\WhatsappMessageBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class LemburRequestService
{
    protected FunctionalRole $role;

    protected AccessContext $accessContext;

    protected WhatsappLogHelper $whatsappLogHelper;

    protected WhatsappMessageBuilder $whatsappMessageBuilder;

    protected HrisAttendanceSnapshotService $attendanceService;

    protected AttendanceDirtyResolverService $attendanceDirtyResolver;

    public function __construct(
        FunctionalRole $role,
        AccessContext $accessContext,
        WhatsappLogHelper $whatsappLogHelper,
        WhatsappMessageBuilder $whatsappMessageBuilder,
        HrisAttendanceSnapshotService $attendanceSnapshotService,
        AttendanceDirtyResolverService $attendanceDirtyResolver,
    ) {
        $this->role = $role;
        $this->accessContext = $accessContext;
        $this->whatsappLogHelper = $whatsappLogHelper;
        $this->whatsappMessageBuilder = $whatsappMessageBuilder;
        $this->attendanceService = $attendanceSnapshotService;
        $this->attendanceDirtyResolver = $attendanceDirtyResolver;
    }

    public function submitFromRequest(Request $request, bool $isAdminRoute = false): array
    {
        $params = (array) $request->input('params', []);
        $rows = (array) ($params['dataUsers'] ?? []);

        return $this->submit(
            $rows,
            [
                'kode_perusahaan' => $params['Kode_Perusahaan'] ?? '001',
                'date' => $params['date'] ?? null,
                'type' => $params['type'] ?? null,
                'start_time' => $params['startTime'] ?? null,
                'end_time' => $params['endTime'] ?? ($params['time'] ?? null),
            ],
            $isAdminRoute,
        );
    }

    public function validateDraftFromRequest(Request $request, bool $isAdminRoute = false): array
    {
        $params = (array) $request->input('params', []);
        $rows = (array) ($params['dataUsers'] ?? []);

        return $this->validateDraft(
            $rows,
            [
                'kode_perusahaan' => $params['Kode_Perusahaan'] ?? '001',
                'date' => $params['date'] ?? null,
                'type' => $params['type'] ?? null,
                'start_time' => $params['startTime'] ?? null,
                'end_time' => $params['endTime'] ?? ($params['time'] ?? null),
            ],
            $isAdminRoute,
        );
    }

    public function validateDraft(array $rows, array $context = [], bool $isAdminRoute = false): array
    {
        if (empty($rows)) {
            throw new InvalidArgumentException('Data karyawan lembur tidak boleh kosong.');
        }

        $this->authorizeSubmit($isAdminRoute);

        $kodePerusahaan = $context['kode_perusahaan'] ?? '001';
        $validRows = [];
        $conflicts = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $validation = $this->validateSubmissionRow($row, $context, $kodePerusahaan, $isAdminRoute);
            if (! $validation['is_valid']) {
                $conflicts[] = [
                    'employee_id' => $row['id'] ?? null,
                    'employee_name' => $row['nama'] ?? null,
                    'code' => $validation['code'] ?? 'INVALID',
                    'reason' => $validation['reason'] ?? 'Data berubah, harap refresh.',
                ];

                continue;
            }

            $validRows[] = $row;
        }

        return [
            'valid_count' => count($validRows),
            'conflict_count' => count($conflicts),
            'has_conflict' => count($conflicts) > 0,
            'valid_rows' => $validRows,
            'conflicts' => $conflicts,
        ];
    }

    public function submit(array $rows, array $context = [], bool $isAdminRoute = false): array
    {
        if (empty($rows)) {
            throw new InvalidArgumentException('Data karyawan lembur tidak boleh kosong.');
        }

        $this->authorizeSubmit($isAdminRoute);

        // Antrian WA diisi di dalam transaksi tapi dikirim SETELAH commit —
        // HTTP eksternal (timeout 10s/pesan) tidak boleh menahan transaksi/lock DB.
        $waQueue = [];

        $result = DB::transaction(function () use ($rows, $context, $isAdminRoute, &$waQueue) {
            $kodePerusahaan = $context['kode_perusahaan'] ?? '001';
            $submitter = Auth::user()?->karyawan?->Kode_Karyawan;
            $updatedBy = $this->resolveUpdatedByUserWeb();
            $flagAdmin = $isAdminRoute ? 'Y' : 'T';

            if (! $submitter) {
                throw new InvalidArgumentException('User tidak terhubung ke data karyawan.');
            }

            $validRows = [];
            $conflicts = [];

            foreach ($rows as $row) {
                $row = (array) $row;
                $validation = $this->validateSubmissionRow($row, $context, $kodePerusahaan, $isAdminRoute);
                if (! $validation['is_valid']) {
                    $conflicts[] = [
                        'employee_id' => $row['id'] ?? null,
                        'employee_name' => $row['nama'] ?? null,
                        'code' => $validation['code'] ?? 'INVALID',
                        'reason' => $validation['reason'] ?? 'Data berubah, harap refresh.',
                    ];

                    continue;
                }

                $validRows[] = $row;
            }

            if (count($validRows) === 0) {
                return [
                    'no_transaksi' => null,
                    'inserted' => 0,
                    'has_conflict' => count($conflicts) > 0,
                    'conflicts' => $conflicts,
                ];
            }

            $noTransaksi = $this->generateNoTransaksi($kodePerusahaan);
            $now = $this->currentNow();

            Lembur::insert([
                'Kode_Perusahaan' => $kodePerusahaan,
                'No_Transaksi' => $noTransaksi,
                'Tanggal' => $now->format('Y-m-d'),
                'Jam' => $now->format('H:i:s'),
                'UserID' => $submitter,
                'Updated_By' => $updatedBy,
                'Flag_Admin' => $flagAdmin,
                'Flag_Selesai' => 'Y',
            ]);

            $inserted = 0;
            $dirtyTargets = [];
            foreach ($validRows as $row) {
                $payload = $this->buildDetailPayload((array) $row, $context, $kodePerusahaan, $noTransaksi);
                $detId = LemburDetail::insertGetId($payload['detail']);

                LemburDet::create([
                    'Kode_Perusahaan' => $kodePerusahaan,
                    'No_Transaksi' => $noTransaksi,
                    'Kode_Karyawan' => $payload['kode_karyawan'],
                    'Urut_Detail' => $detId,
                    'Tanggal_Lembur' => $payload['effective_work_date'],
                ]);

                $waQueue[] = $payload;
                $dirtyTargets[] = [
                    'kode_perusahaan' => $kodePerusahaan,
                    'kode_karyawan' => $payload['kode_karyawan'],
                    'tanggal' => $payload['effective_work_date'],
                ];
                $inserted++;
            }

            $this->attendanceDirtyResolver->markMany($dirtyTargets, 'LEMBUR_CREATED', 'LEMBUR_REQUEST_SERVICE', 1);

            return [
                'no_transaksi' => $noTransaksi,
                'inserted' => $inserted,
                'has_conflict' => count($conflicts) > 0,
                'conflicts' => $conflicts,
            ];
        });

        if ($result['no_transaksi'] !== null) {
            foreach ($waQueue as $payload) {
                $this->sendOvertimeAssignmentWhatsapp($result['no_transaksi'], $payload);
            }
        }

        return $result;
    }

    public function getTanggalOptions(bool $includeAll = false): array
    {
        $today = $this->currentNow()->copy()->startOfDay();

        // Opsi tanggal eksplisit untuk user:
        // H-1 (khusus kepemilikan hari kerja shift malam) + hari ini + H+1 + H+2.
        return [
            $today->copy()->subDay()->format('Y-m-d'),
            $today->format('Y-m-d'),
            $today->copy()->addDay()->format('Y-m-d'),
            $today->copy()->addDays(2)->format('Y-m-d'),
        ];
    }

    public function getActiveUsers(Request $request, bool $includeAll = false): array
    {
        $kodePerusahaan = '001';
        $tanggalRaw = $request->input('date');
        if (! $tanggalRaw) {
            throw new InvalidArgumentException('Tanggal wajib diisi.');
        }

        $tanggal = Carbon::parse($tanggalRaw)->format('Y-m-d');
        $requestedType = $this->normalizeRequestType((string) ($request->input('type') ?? 'LEMBUR_HK'));
        $requestedStart = $this->normalizeTime(
            (string) ($request->input('startTime') ??
                ($request->input('time') ?? ($request->input('endTime') ?? '00:00:00'))),
        );
        $requestedEnd = $this->normalizeTime(
            (string) ($request->input('endTime') ?? ($request->input('time') ?? $requestedStart)),
        );
        $jam = $this->normalizeTime((string) ($request->input('time') ?? ($request->input('endTime') ?? '00:00:00')));
        $dateTime = Carbon::parse($tanggal.' '.$jam)->format('Y-m-d H:i:s');
        $searchTerm = trim((string) $request->input('search', ''));
        $hasPagination = $request->has('page') || $request->has('per_page') || $request->has('perPage');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) ($request->input('per_page') ?? ($request->input('perPage') ?? 50));
        if ($perPage <= 0) {
            $perPage = 50;
        }
        $perPage = min($perPage, 200);

        $leaderKode = Auth::user()?->karyawan?->Kode_Karyawan;
        $isAdminContext = $includeAll || $this->accessContext->isAdmin();
        if (! $leaderKode && ! $isAdminContext) {
            throw new InvalidArgumentException('User tidak memiliki relasi karyawan.');
        }

        $whitelistLevel = implode(',', config('services.hcis.overtime_level_list', [1, 2, 21]));
        $teamJoin = $isAdminContext ? '' : 'LEFT JOIN HRIS_Karyawan_Team h ON h.Kode_Karyawan = e.Kode_Karyawan';
        $levelJoin = $isAdminContext
            ? ''
            : 'INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan g ON e.ID_Level_Jabatan = g.ID_Level_Jabatan';
        $teamFilter = $isAdminContext ? '' : 'AND h.Kode_Karyawan_Team = ?';
        $excludeFilter = $isAdminContext ? '' : 'AND e.Kode_Karyawan <> ?';
        $levelFilter = $isAdminContext
            ? ''
            : "AND g.ID_Level IN (SELECT CAST(value AS INT) FROM STRING_SPLIT(p.LevelWhitelist, ','))";
        $adminScopeFilter = '';
        $adminScopeBindings = [];
        $searchFilter = '';

        if ($isAdminContext) {
            $scopedEmployeeCodes = $this->resolveScopedKaryawanByFunctionalRole($kodePerusahaan);
            if (count($scopedEmployeeCodes) === 0) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($scopedEmployeeCodes), '?'));
            $adminScopeFilter = "AND e.Kode_Karyawan IN ({$placeholders})";
            $adminScopeBindings = $scopedEmployeeCodes;
        }

        if ($searchTerm !== '') {
            $searchFilter = 'AND e.Nama LIKE ?';
        }

        $selectClause = "
            SELECT DISTINCT
                CASE
                    WHEN sh.Jam_Keluar IS NULL THEN 'FALSE'
                    WHEN CONVERT(TIME, sh.Jam_Keluar)
                        <= CONVERT(TIME, sh.Jam_Masuk)
                    THEN
                        CASE
                            WHEN DATEADD(DAY, 1, p.RefDateTime) BETWEEN
                                DATEADD(HOUR, 1,
                                    CAST(CONVERT(VARCHAR(10), DATEADD(DAY,1,p.RefDate),120)
                                    + ' ' + sh.Jam_Keluar AS DATETIME))
                            AND
                                DATEADD(HOUR, -2,
                                    CAST(CONVERT(VARCHAR(10), DATEADD(DAY,1,p.RefDate),120)
                                    + ' ' + sh.Jam_Masuk AS DATETIME))
                            THEN 'TRUE' ELSE 'FALSE'
                        END
                    ELSE
                        CASE
                            WHEN p.RefDateTime BETWEEN
                                DATEADD(HOUR, 1,
                                    CAST(CONVERT(VARCHAR(10), p.RefDate,120)
                                    + ' ' + sh.Jam_Keluar AS DATETIME))
                            AND
                                DATEADD(HOUR, -2,
                                    CAST(CONVERT(VARCHAR(10), DATEADD(DAY,1,p.RefDate),120)
                                    + ' ' + sh.Jam_Masuk AS DATETIME))
                            THEN 'TRUE' ELSE 'FALSE'
                        END
                END AS JamComparison,

                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM Transaksi_Lembur_Detail a
                        JOIN Transaksi_Lembur_Det d
                          ON d.Kode_Perusahaan = a.Kode_Perusahaan
                         AND d.No_Transaksi = a.No_Transaksi
                         AND d.Kode_Karyawan = a.Kode_Karyawan
                         AND d.Urut_Detail = a.Urut_Oto
                        JOIN Transaksi_Lembur b
                          ON b.Kode_Perusahaan = a.Kode_Perusahaan
                         AND b.No_Transaksi = a.No_Transaksi
                        WHERE a.Kode_Perusahaan = p.KodePerusahaan
                          AND a.Kode_Karyawan = e.Kode_Karyawan
                          AND b.Status IS NULL
                                                    AND d.Tanggal_Lembur >= CAST(p.RefDate AS DATETIME)
                                                    AND d.Tanggal_Lembur < DATEADD(DAY, 1, CAST(p.RefDate AS DATETIME))
                    ) THEN 'TRUE' ELSE 'FALSE'
                END AS lemburPernah,

                e.Kode_Karyawan AS userId,
                e.UserID_Absen,
                f.nama_divisi AS Divisi,
                f.nama_sub_divisi AS Sub_Divisi,
                e.Nama AS name,
                sh.Jam_Masuk AS Jam_Masuk,
                sh.Jam_Keluar AS Jam_Keluar,
                sh.Id_Shift AS ID_Shift,
                sh.Nama_Shift AS Nama_Shift,
                sh.ECO AS Jam_selesai_absen,
                sh.JENIS_SHIFT_DIPILIH AS Jenis_Shift,
                COALESCE(
                    sh.StatusHari,
                    dbo.N_HRIS_FN_GetStatusHari(p.KodePerusahaan, e.Kode_Karyawan, p.RefDate)
                ) AS StatusHari
        ";

        $fromClause = "
            FROM Karyawan e
            CROSS JOIN (
                SELECT
                    CAST(? AS DATETIME) AS RefDateTime,
                    CAST(? AS DATE) AS RefDate,
                    CAST(? AS VARCHAR(3)) AS KodePerusahaan,
                    CAST(? AS VARCHAR(100)) AS LevelWhitelist
            ) p
            {$teamJoin}
            INNER JOIN View_Divisi_Sub_Divisi f ON e.ID_Divisi_Sub_Divisi = f.ID_DIVISI_SUB_DIVISI
            {$levelJoin}

            LEFT JOIN N_HRIS_Absen_Snapshot sh
                ON sh.Kode_Perusahaan = p.KodePerusahaan
               AND sh.Kode_Karyawan = e.Kode_Karyawan
               AND sh.Tanggal = p.RefDate

            WHERE e.Kode_Perusahaan = p.KodePerusahaan
              AND e.Aktif = 'Y'
                            {$levelFilter}
              {$teamFilter}
              {$excludeFilter}
              {$adminScopeFilter}
                            {$searchFilter}
        ";

        $orderClause = ' ORDER BY JamComparison DESC, name ASC ';

        $bindings = [$dateTime, $tanggal, $kodePerusahaan, $whitelistLevel];

        if (! $isAdminContext) {
            $bindings[] = $leaderKode;
            $bindings[] = $leaderKode;
        }
        foreach ($adminScopeBindings as $kode) {
            $bindings[] = $kode;
        }
        if ($searchTerm !== '') {
            $bindings[] = '%'.$searchTerm.'%';
        }

        if ($hasPagination) {
            $offset = max(0, ($page - 1) * $perPage);

            if ($searchTerm !== '') {
                // Search mode: hindari COUNT berat agar respons autocomplete lebih cepat.
                $pagedQuery = "
                    {$selectClause}
                    {$fromClause}
                    {$orderClause}
                    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
                ";

                $pagedBindings = array_merge($bindings, [$offset, $perPage + 1]);
                $rows = DB::select($pagedQuery, $pagedBindings);
                $hasMore = count($rows) > $perPage;
                $result = $hasMore ? array_slice($rows, 0, $perPage) : $rows;
                $totalRows = $hasMore ? $offset + $perPage + 1 : $offset + count($result);
            } else {
                $countQuery = "
                    SELECT COUNT(1) AS aggregate
                    FROM (
                        SELECT DISTINCT e.Kode_Karyawan AS userId
                        {$fromClause}
                    ) AS c
                ";

                $countRow = DB::selectOne($countQuery, $bindings);
                $totalRows = (int) ($countRow->aggregate ?? 0);

                $pagedQuery = "
                    {$selectClause}
                    {$fromClause}
                    {$orderClause}
                    OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
                ";

                $pagedBindings = array_merge($bindings, [$offset, $perPage]);
                $result = DB::select($pagedQuery, $pagedBindings);
            }
        } else {
            $fullQuery = "
                {$selectClause}
                {$fromClause}
                {$orderClause}
            ";

            $result = DB::select($fullQuery, $bindings);
            $totalRows = count($result);
        }

        $eligibilityCache = $this->buildActiveUsersEligibilityCache($kodePerusahaan, $result, $tanggal);

        $mapped = collect($result)
            ->map(function ($item) use (
                $requestedType,
                $requestedStart,
                $requestedEnd,
                $tanggal,
                $kodePerusahaan,
                $eligibilityCache,
                $isAdminContext,
            ) {
                // Backfill shift tanggal terpilih dari jadwal LIVE (TVF) bila snapshot
                // belum ter-generate/stale. TVF = sumber kebenaran jadwal: bila baris TVF
                // untuk tanggal ini ADA -> pakai sepenuhnya (termasuk OFF/null) agar
                // konsisten & realtime; bila TIDAK ada -> biarkan nilai snapshot (fallback).
                // Inilah yg membuat karyawan tanpa snapshot tetap dinilai & bisa dicentang.
                $tvfShift = $eligibilityCache['selected_shift'][(string) ($item->userId ?? '')] ?? null;
                if ($tvfShift !== null) {
                    $item->StatusHari = $tvfShift['status_hari'];
                    $item->Jam_Masuk = $tvfShift['jam_masuk'];
                    $item->Jam_Keluar = $tvfShift['jam_keluar'];
                    $item->ID_Shift = $tvfShift['id_shift'];
                    if (! empty($tvfShift['nama_shift'])) {
                        $item->Nama_Shift = $tvfShift['nama_shift'];
                    }
                }

                $eligibility = $this->evaluateUserEligibility(
                    $requestedType,
                    $requestedStart,
                    $requestedEnd,
                    $tanggal,
                    $item->StatusHari ?? null,
                    $item->Jam_Masuk ?? null,
                    $item->Jam_Keluar ?? null,
                    $item->ID_Shift ?? null,
                    (string) ($item->lemburPernah ?? 'FALSE'),
                    $kodePerusahaan,
                    (string) ($item->userId ?? ''),
                    $eligibilityCache,
                    $isAdminContext,
                );

                $item->isActive = $eligibility['is_active'];
                $item->inactiveReason = $eligibility['reason'];
                $item->eligibility_checked_at = $this->currentNow()->format('Y-m-d H:i:s');

                if (isset($item->userId)) {
                    // stableId: identik untuk Kode_Karyawan yang sama di setiap API call (deterministik)
                    // Dipakai frontend untuk dedup & comparison karena Crypt::encryptString menghasilkan nilai berbeda setiap call
                    $item->stableId = hash_hmac('sha256', (string) $item->userId, config('app.key'));
                    $item->userId = Crypt::encryptString($item->userId);
                }

                return $item;
            })
            ->values()
            ->all();

        if (! $hasPagination) {
            return $mapped;
        }

        return [
            'data' => $mapped,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalRows,
                'has_more' => $page * $perPage < $totalRows,
            ],
        ];
    }

    protected function buildActiveUsersEligibilityCache(
        string $kodePerusahaan,
        array $activeRows,
        string $requestedDate,
    ): array {
        $kodeKaryawanList = collect($activeRows)
            ->pluck('userId')
            ->filter(fn ($v) => ! empty($v))
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values();

        if ($kodeKaryawanList->isEmpty()) {
            return [
                'covered' => [],
                'existing_type_dates' => [],
                'overlap_intervals' => [],
                'next_shift_start' => [],
            ];
        }

        $existingTypeDates = [];
        $overlapIntervals = [];
        $cacheWindowStart = Carbon::parse($requestedDate)->startOfDay()->subDay()->format('Y-m-d H:i:s');
        $cacheWindowEnd = Carbon::parse($requestedDate)->startOfDay()->addDays(2)->format('Y-m-d H:i:s');

        $existingRows = DB::table('Transaksi_Lembur_Detail as d')
            ->join('Transaksi_Lembur as h', function ($join) {
                $join->on('h.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')->on('h.No_Transaksi', '=', 'd.No_Transaksi');
            })
            ->join('Transaksi_Lembur_Det as det', function ($join) {
                $join
                    ->on('det.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')
                    ->on('det.No_Transaksi', '=', 'd.No_Transaksi')
                    ->on('det.Kode_Karyawan', '=', 'd.Kode_Karyawan')
                    ->on('det.Urut_Detail', '=', 'd.Urut_Oto');
            })
            ->where('d.Kode_Perusahaan', $kodePerusahaan)
            ->whereIn('d.Kode_Karyawan', $kodeKaryawanList->all())
            ->whereNull('h.Status')
            ->where('d.Tanggal_Lembur_Dari', '<', $cacheWindowEnd)
            ->where('d.Tanggal_Lembur_Sampai', '>', $cacheWindowStart)
            ->get([
                'd.Kode_Karyawan',
                'd.Jenis',
                'd.Tanggal_Lembur_Dari',
                'd.Tanggal_Lembur_Sampai',
                'det.Tanggal_Lembur',
            ]);

        foreach ($existingRows as $row) {
            $kode = (string) ($row->Kode_Karyawan ?? '');
            if ($kode === '') {
                continue;
            }

            $overlapIntervals[$kode][] = [
                'start' => $row->Tanggal_Lembur_Dari,
                'end' => $row->Tanggal_Lembur_Sampai,
            ];

            $jenis = strtoupper((string) ($row->Jenis ?? ''));
            $tanggalLembur = $row->Tanggal_Lembur ?? null;
            if ($jenis !== '' && $tanggalLembur) {
                $existingTypeDates[$kode][$jenis][] = Carbon::parse((string) $tanggalLembur)->format('Y-m-d');
            }
        }

        foreach ($existingTypeDates as $kode => $typeMap) {
            foreach ($typeMap as $jenis => $dates) {
                $existingTypeDates[$kode][$jenis] = array_values(array_unique($dates));
            }
        }

        $nextShiftStart = [];
        $nextShiftName = [];
        $selectedShift = [];
        $nextDate = Carbon::parse($requestedDate)->addDay()->format('Y-m-d');
        $placeholders = implode(',', array_fill(0, $kodeKaryawanList->count(), '?'));

        // Satu panggilan TVF live per karyawan atas window SEMPIT [H .. H+1] (2 hari):
        //  - baris H   -> shift efektif tanggal terpilih. Dipakai untuk backfill saat
        //                 N_HRIS_Absen_Snapshot belum ter-generate/stale — TVF membaca
        //                 jadwal LIVE, jadi karyawan yg snapshot-nya belum ada tetap
        //                 dapat shift & bisa dipilih tanpa perlu buka leader-shift dulu.
        //  - baris H+1 -> next_shift_start (guard agar lembur tak menabrak shift besok).
        // Rentang WAJIB sempit: TVF berat hanya bila rentang lebar. Query ini memang
        // sudah dipanggil tiap search (dulu untuk next-shift saja), kini diperlebar 1 hari.
        $sql = "
            SELECT
                e.Kode_Karyawan,
                sh.Tanggal,
                sh.ID_Shift,
                sh.StatusHari,
                sh.Jam_Masuk,
                sh.Jam_Keluar,
                sh.Nama_Shift
            FROM Karyawan e
            CROSS APPLY dbo.N_HRIS_FN_GetStatusHariRange(?, e.Kode_Karyawan, ?, ?) sh
            WHERE e.Kode_Perusahaan = ?
              AND e.Kode_Karyawan IN ({$placeholders})
        ";

        $bindings = array_merge([$kodePerusahaan, $requestedDate, $nextDate, $kodePerusahaan], $kodeKaryawanList->all());

        // Micro-cache sebagai pengaman konkurensi. TVF ~2x lebih berat dari join
        // snapshot & skala per-user; saat banyak orang scan serentak, pemanggilan
        // berulang atas (user-set + tanggal) yang SAMA (autocomplete re-fire, buka-tutup
        // halaman, paginasi bolak-balik, banyak admin scan roster+tanggal sama) tidak
        // mengulang TVF. TTL 60s = lebih aman meredam lonjakan; kompromi: perubahan shift
        // terlihat ≤60s (masih cukup segar untuk penugasan lembur). Key = hasil TVF hanya
        // bergantung pada (perusahaan, karyawan, tanggal) → aman dipakai lintas pemohon.
        $shiftCacheKey = 'lembur_active_shift_'.md5(
            $kodePerusahaan.'|'.$requestedDate.'|'.$nextDate.'|'.$kodeKaryawanList->sort()->implode(','),
        );
        $shiftRows = \Illuminate\Support\Facades\Cache::remember(
            $shiftCacheKey,
            60,
            fn () => DB::select($sql, $bindings),
        );
        foreach ($shiftRows as $row) {
            $kode = (string) ($row->Kode_Karyawan ?? '');
            if ($kode === '') {
                continue;
            }

            $rowDate = $row->Tanggal ? Carbon::parse((string) $row->Tanggal)->format('Y-m-d') : null;
            $status = strtoupper((string) ($row->StatusHari ?? ''));
            $jamMasuk = $this->tryNormalizeTime((string) ($row->Jam_Masuk ?? ''));
            $namaShift = trim((string) ($row->Nama_Shift ?? ''));

            if ($rowDate === $requestedDate) {
                // Shift efektif tanggal terpilih (H) dari jadwal live. Simpan apa adanya
                // (termasuk OFF/null) — pemakai akan pilih TVF penuh bila baris ini ada.
                $selectedShift[$kode] = [
                    'id_shift' => $row->ID_Shift ?? null,
                    'status_hari' => $row->StatusHari ?? null,
                    'jam_masuk' => $row->Jam_Masuk ?? null,
                    'jam_keluar' => $row->Jam_Keluar ?? null,
                    'nama_shift' => $namaShift !== '' ? $namaShift : null,
                ];
            } elseif ($rowDate === $nextDate) {
                if ($status === 'WORK' && $jamMasuk) {
                    $nextShiftStart[$kode] = Carbon::parse($nextDate.' '.$jamMasuk)->format('Y-m-d H:i:s');
                    if ($namaShift !== '') {
                        $nextShiftName[$kode] = $namaShift;
                    }
                }
            }
        }

        return [
            // Siapa saja yang cache ini SUDAH periksa.
            //
            // Tanpa daftar ini, "tidak punya lembur" dan "tidak ada di cache"
            // tak bisa dibedakan, dan pemakainya bertanya ulang ke database
            // untuk setiap orang yang justru tidak punya apa-apa: 958 orang
            // menghasilkan 2.029 kueri yang hampir semuanya menjawab "tidak".
            // Kekosongan adalah data, dan di sini ia harus disimpan.
            'covered' => $kodeKaryawanList->flip()->map(fn () => true)->all(),
            'existing_type_dates' => $existingTypeDates,
            'overlap_intervals' => $overlapIntervals,
            'next_shift_start' => $nextShiftStart,
            'next_shift_name' => $nextShiftName,
            'selected_shift' => $selectedShift,
        ];
    }

    protected function resolveScopedKaryawanByFunctionalRole(string $kodePerusahaan): array
    {
        $query = DB::table('Karyawan')
            ->select('Kode_Karyawan')
            ->where('Kode_Perusahaan', $kodePerusahaan)
            ->where('Aktif', 'Y');

        $this->role->applyScope($query);

        // dd($query->toSql(), $query->getBindings());
        return $query->pluck('Kode_Karyawan')->map(fn ($v) => (string) $v)->filter(fn ($v) => $v !== '')->values()->all();
    }

    /**
     * Validasi permintaan + cakupan akses untuk riwayat lembur.
     *
     * Dipisah supaya getHistory() dan ringkasanHistory() berangkat dari
     * saringan yang PERSIS sama.
     */
    private function saringanHistory(Request $request, bool $isAdminRoute): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggalFilter' => ['nullable', 'string', 'max:100'],
            'tanggalDari' => ['nullable', 'date'],
            'tanggalSampai' => ['nullable', 'date'],
            'jenisFilter' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $canAccessAdminScope = $this->accessContext->isAdmin() || $isAdminRoute;
        $kodeKaryawan = Auth::user()?->karyawan?->Kode_Karyawan;

        if (! $canAccessAdminScope && ! $kodeKaryawan) {
            throw new InvalidArgumentException('User tidak terhubung ke data karyawan.');
        }

        return [
            'search' => strip_tags((string) ($validated['search'] ?? '')),
            'tanggalFilterRaw' => $validated['tanggalFilter'] ?? null,
            'tanggalDariRaw' => $validated['tanggalDari'] ?? null,
            'tanggalSampaiRaw' => $validated['tanggalSampai'] ?? null,
            'jenisFilterRaw' => strtoupper(trim((string) ($validated['jenisFilter'] ?? ''))),
            'page' => (int) ($validated['page'] ?? 1),
            'scopeIsAdmin' => $isAdminRoute,
            'kodeKaryawan' => $kodeKaryawan,
            // Scope 1 perusahaan (sistem single-company). Selain benar secara data, predikat
            // Kode_Perusahaan mengaktifkan reuse indeks existing (PK / IX ...KodePerusahaan_*)
            // untuk prefix No_Transaksi & seek subquery detail.
            'kodePerusahaan' => Auth::user()?->karyawan?->Kode_Perusahaan ?: '001',
        ];
    }

    public function getHistory(Request $request, bool $isAdminRoute = false): LengthAwarePaginator
    {
        $s = $this->saringanHistory($request, $isAdminRoute);
        $page = $s['page'];
        $perPage = 10;

        $query = DB::table('Transaksi_Lembur as h')
            ->selectRaw(
                "
                h.No_Transaksi,
                h.Kode_Perusahaan,
                h.Tanggal,
                h.Jam,
                h.UserID as Nama,
                h.Updated_By,
                (
                    SELECT TOP 1 k_upd.Nama
                    FROM Karyawan k_upd
                    WHERE k_upd.UserID_Web = h.Updated_By
                ) AS Updated_By_Nama,
                h.Updated_At,
                h.Flag_Admin,
                h.Flag_Selesai,
                h.Status,
                h.Alasan_Pembatalan,
                (
                    SELECT TOP 1 d4.Jenis
                    FROM Transaksi_Lembur_Detail d4
                    WHERE d4.Kode_Perusahaan = h.Kode_Perusahaan AND d4.No_Transaksi = h.No_Transaksi
                    ORDER BY d4.Urut_Oto ASC
                ) AS Jenis_Lembur,
                (
                    SELECT STRING_AGG(k2.Nama, ', ')
                    FROM Transaksi_Lembur_Detail d2
                    JOIN Karyawan k2 ON k2.Kode_Perusahaan = d2.Kode_Perusahaan AND k2.Kode_Karyawan = d2.Kode_Karyawan
                    WHERE d2.Kode_Perusahaan = h.Kode_Perusahaan AND d2.No_Transaksi = h.No_Transaksi
                ) AS Assignment,
                (
                    SELECT TOP 1 d3.Tanggal_Lembur_Sampai
                    FROM Transaksi_Lembur_Detail d3
                    WHERE d3.Kode_Perusahaan = h.Kode_Perusahaan AND d3.No_Transaksi = h.No_Transaksi
                    ORDER BY d3.Tanggal_Lembur_Sampai DESC
                ) AS Tanggal_Assign,
                (
                    SELECT STRING_AGG(CONVERT(VARCHAR(10), CAST(det2.Tanggal_Lembur AS DATE), 120), ', ')
                    FROM Transaksi_Lembur_Det det2
                    WHERE det2.Kode_Perusahaan = h.Kode_Perusahaan AND det2.No_Transaksi = h.No_Transaksi
                ) AS Effective_Work_Dates
            ",
            );

        $this->terapkanSaringanHistory($query, $s);

        $query->orderByDesc('h.Tanggal')->orderByDesc('h.Jam');
        $pager = $query->paginate($perPage, ['*'], 'page', $page);

        $pager->getCollection()->transform(function ($row) {
            $rawDates = (string) ($row->Effective_Work_Dates ?? '');
            if ($rawDates !== '') {
                $dates = collect(explode(',', $rawDates))
                    ->map(fn ($d) => trim((string) $d))
                    ->filter(fn ($d) => $d !== '')
                    ->values()
                    ->all();

                $row->Effective_Work_Dates = ! empty($dates) ? FormatTanggalHelper::formatTanggalMultiple($dates) : '-';
            } else {
                $row->Effective_Work_Dates = '-';
            }

            return $row;
        });

        return $pager;
    }

    /**
     * Seluruh penyaringan riwayat lembur, satu tempat.
     *
     * Dipakai bersama oleh getHistory() (yang mem-paginasi) dan
     * ringkasanHistory() (yang menghitung). Kalau keduanya menyusun WHERE-nya
     * sendiri-sendiri, angka widget dan isi tabel bisa menyimpang tanpa ada
     * yang menyadarinya.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function terapkanSaringanHistory($query, array $s): void
    {
        $kodePerusahaan = $s['kodePerusahaan'];
        $scopeIsAdmin = $s['scopeIsAdmin'];
        $kodeKaryawan = $s['kodeKaryawan'];
        $search = $s['search'];
        $jenisFilterRaw = $s['jenisFilterRaw'];
        $tanggalDariRaw = $s['tanggalDariRaw'];
        $tanggalSampaiRaw = $s['tanggalSampaiRaw'];
        $tanggalFilterRaw = $s['tanggalFilterRaw'];

        $query
            ->where('h.Kode_Perusahaan', $kodePerusahaan)
            ->where(function ($q) {
                $q->whereNull('h.Status')->orWhere('h.Status', 'Y');
            });

        if (! $scopeIsAdmin) {
            $query->where(function ($q) {
                $q->where('h.Flag_Admin', 'T')->orWhereNull('h.Flag_Admin');
            });
            $query->where('h.UserID', $kodeKaryawan);
        }

        // Pencarian HANYA berdasarkan No_Transaksi, prefix (sargable) agar bisa seek indeks
        // (Kode_Perusahaan sudah difilter di atas -> pakai PK / IX ...KodePerusahaan_NoTransaksi_Status).
        // Pencarian by nama karyawan sengaja dihapus (leading-wildcard scan = penyebab lambat).
        if ($search !== '') {
            $query->where('h.No_Transaksi', 'like', $search.'%');
        }

        if ($jenisFilterRaw !== '') {
            $allowedJenis = ['LEMBUR_HK', 'LEMBUR_AWAL', 'LEMBUR_LIBUR', 'LEMBUR'];
            if (in_array($jenisFilterRaw, $allowedJenis, true)) {
                $query->whereExists(function ($q) use ($jenisFilterRaw) {
                    $q->selectRaw('1')
                        ->from('Transaksi_Lembur_Detail as d')
                        ->whereColumn('d.No_Transaksi', 'h.No_Transaksi');

                    if ($jenisFilterRaw === 'LEMBUR_HK') {
                        $q->whereIn('d.Jenis', ['LEMBUR_HK', 'LEMBUR']);
                    } else {
                        $q->where('d.Jenis', $jenisFilterRaw);
                    }
                });
            }
        }

        if ($tanggalDariRaw && $tanggalSampaiRaw) {
            try {
                $tanggalDari = Carbon::parse((string) $tanggalDariRaw)->format('Y-m-d');
                $tanggalSampai = Carbon::parse((string) $tanggalSampaiRaw)->format('Y-m-d');

                if ($tanggalDari > $tanggalSampai) {
                    [$tanggalDari, $tanggalSampai] = [$tanggalSampai, $tanggalDari];
                }

                $query->whereExists(function ($q) use ($tanggalDari, $tanggalSampai) {
                    $q->selectRaw('1')
                        ->from('Transaksi_Lembur_Det as det')
                        ->whereColumn('det.No_Transaksi', 'h.No_Transaksi')
                        ->whereBetween(DB::raw('CAST(det.Tanggal_Lembur as DATE)'), [$tanggalDari, $tanggalSampai]);
                });
            } catch (\Throwable $e) {
                // ignore invalid range format
            }
        } elseif ($tanggalFilterRaw) {
            $tanggalFilterRaw = preg_replace('/\s*\(.*\)$/', '', trim((string) $tanggalFilterRaw));
            try {
                $tanggalSQL = Carbon::parse($tanggalFilterRaw)->format('Y-m-d');
                $query->whereExists(function ($q) use ($tanggalSQL) {
                    $q->selectRaw('1')
                        ->from('Transaksi_Lembur_Det as det')
                        ->whereColumn('det.No_Transaksi', 'h.No_Transaksi')
                        ->whereDate('det.Tanggal_Lembur', '=', $tanggalSQL);
                });
            } catch (\Throwable $e) {
                // ignore invalid filter format
            }
        }
    }

    /**
     * Angka untuk tiga widget di atas tabel riwayat lembur.
     *
     * Dihitung di server dan memakai saringan yang sama dengan tabelnya, jadi
     * angkanya selalu cocok dengan apa yang sedang ditampilkan.
     *
     * Sebelumnya "Hari Ini" dihitung di klien dari `this.data` — hanya SATU
     * halaman paginasi — dan membandingkan tanggal lewat toISOString(), yang
     * memakai UTC. Baris bertanda tengah malam waktu Jakarta (mis. "2026-09-10
     * 00:00:00") berubah menjadi "2026-09-09" saat dikonversi, sehingga tidak
     * pernah cocok dengan hari ini dan hasilnya selalu 0. "Pending" bahkan
     * tidak pernah dihitung sama sekali: nilainya hardcode 0.
     *
     * "Hari ini" ditentukan dari zona waktu aplikasi, bukan jam server basis
     * data, supaya batas harinya sama dengan yang dilihat pengguna.
     *
     * Transaksi yang DIBATALKAN (Status = 'Y') tidak ikut dihitung pada
     * "Hari Ini" maupun "Pending". Catatan yang perlu diketahui: "Total
     * Transaksi" di widget membaca `total` milik paginator, dan paginator itu
     * memang menyertakan yang dibatalkan karena barisnya tetap tampil di tabel
     * dengan label DIBATALKAN. Jadi ketiga angka tidak memakai aturan yang
     * seragam — itu disengaja agar tiap angka cocok dengan yang dilihat
     * pengguna, bukan kelalaian.
     */
    public function ringkasanHistory(Request $request, bool $isAdminRoute = false): array
    {
        $s = $this->saringanHistory($request, $isAdminRoute);

        $query = DB::table('Transaksi_Lembur as h');
        $this->terapkanSaringanHistory($query, $s);

        $hariIni = Carbon::now(config('app.timezone'))->toDateString();

        $baris = $query
            ->selectRaw(
                "COUNT(*) AS total,
                 SUM(CASE WHEN CAST(h.Tanggal AS DATE) = ? AND h.Status IS NULL THEN 1 ELSE 0 END) AS hari_ini,
                 SUM(CASE WHEN h.Status IS NULL
                            AND (h.Flag_Selesai IS NULL OR h.Flag_Selesai <> 'Y')
                          THEN 1 ELSE 0 END) AS pending",
                [$hariIni],
            )
            ->first();

        return [
            'total' => (int) ($baris->total ?? 0),
            'hari_ini' => (int) ($baris->hari_ini ?? 0),
            'pending' => (int) ($baris->pending ?? 0),
        ];
    }

    public function getDetails(Request $request, bool $isAdminRoute = false): array
    {
        $noTransaksi = (string) $request->input('No_Transaksi', '');
        if (trim($noTransaksi) === '') {
            throw new InvalidArgumentException('No transaksi wajib diisi.');
        }

        $canAccessAdminScope = $this->accessContext->isAdmin() || $isAdminRoute;
        $scopeIsAdmin = $isAdminRoute;
        $kodeKaryawan = Auth::user()?->karyawan?->Kode_Karyawan;

        if (! $canAccessAdminScope && ! $kodeKaryawan) {
            throw new InvalidArgumentException('User tidak terhubung ke data karyawan.');
        }

        $query = DB::table('Transaksi_Lembur as h')
            ->join('Transaksi_Lembur_Detail as d', function ($join) {
                $join->on('d.Kode_Perusahaan', '=', 'h.Kode_Perusahaan')->on('d.No_Transaksi', '=', 'h.No_Transaksi');
            })
            ->join('Transaksi_Lembur_Det as det', function ($join) {
                $join
                    ->on('det.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')
                    ->on('det.No_Transaksi', '=', 'd.No_Transaksi')
                    ->on('det.Kode_Karyawan', '=', 'd.Kode_Karyawan')
                    ->on('det.Urut_Detail', '=', 'd.Urut_Oto');
            })
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'k.ID_Divisi_Sub_Divisi', '=', 'vds.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('HRIS_Shift_Kerja as sk_req', 'sk_req.ID_Shift', '=', 'd.Id_Shift')
            ->leftJoin('HRIS_Shift_Kerja_Detail as skd_req', function ($join) {
                $join
                    ->on('skd_req.ID_Shift', '=', 'd.Id_Shift')
                    ->whereRaw('skd_req.Hari = DATEPART(WEEKDAY, det.Tanggal_Lembur)');
            })
            ->leftJoin('HRIS_Waktu_Kerja as wk_req', 'wk_req.ID_Waktu_Kerja', '=', 'skd_req.ID_Waktu_Kerja')
            // JANGAN filter h.Status di sini: daftar transaksi (getData) menampilkan
            // transaksi yang SUDAH DIBATALKAN (Status='Y') juga, jadi detail-nya harus
            // tetap bisa dibuka (menampilkan siapa saja yang di-assign). Filter Status
            // di sini membuat detail transaksi dibatalkan kosong.
            ->where('h.No_Transaksi', $noTransaksi);

        if (! $scopeIsAdmin) {
            $query->where(function ($q) {
                $q->where('h.Flag_Admin', 'T')->orWhereNull('h.Flag_Admin');
            });
            $query->where('h.UserID', $kodeKaryawan);
        }

        $rows = $query
            ->orderByDesc('d.Tanggal_Lembur_Dari')
            ->get([
                'h.Kode_Perusahaan',
                'k.Nama as name',
                'd.Kode_Karyawan',
                'k.UserID_Absen as user_id_absen',
                'd.Jenis as jenis_lembur',
                'd.Tanggal_Lembur_Dari as start_time',
                'd.Tanggal_Lembur_Sampai as end_time',
                'det.Tanggal_Lembur as effective_work_date',
                'd.Id_Shift',
                'd.Alasan as reason',
                'd.Urut_Oto',
                'sk_req.Nama as req_nama_shift',
                'wk_req.Jam_Masuk as req_jam_masuk',
                'wk_req.Jam_Keluar as req_jam_keluar',
                'vds.nama_divisi as dapartment',
                'vds.nama_sub_divisi as divisi',
                'h.Updated_By',
                'h.Flag_Admin',
                'h.Flag_Selesai',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $userIdList = $rows
            ->pluck('user_id_absen')
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->implode(',');

        $effectiveDates = $rows
            ->pluck('effective_work_date')
            ->filter()
            ->map(fn ($v) => Carbon::parse((string) $v)->format('Y-m-d'))
            ->values();

        $minDate = $effectiveDates->isNotEmpty() ? $effectiveDates->min() : Carbon::today()->format('Y-m-d');
        $maxDate = $effectiveDates->isNotEmpty() ? $effectiveDates->max() : Carbon::today()->format('Y-m-d');
        $kodePerusahaan = strtoupper((string) ($rows->first()->Kode_Perusahaan ?? '001'));
        if (! preg_match('/^[A-Z0-9]{1,3}$/', $kodePerusahaan)) {
            $kodePerusahaan = '001';
        }

        $absenIndexByUserId = [];
        $absenIndexByKaryawan = [];
        if ($userIdList !== '') {
            // $queryAbsen = "Exec HRIS_SP_GET_ABSEN_NEW_V3 '{$kodePerusahaan}', ?, ?, ?, '', ''";
            // $absenRows = collect(
            //     DB::select($queryAbsen, [$minDate, $maxDate, $userIdList]),
            // );

            $sourceReuqest = 'lembur-request-service-get-history-details';
            $tglMulai = $minDate;
            $tglSelesai = $maxDate;
            $listUserId = $userIdList;
            $response = $this->attendanceService->getAttendance(
                kodePerusahaan: '001',
                tglAwal: $tglMulai,
                tglAkhir: $tglSelesai,
                userIdList: $listUserId,
                executionMode: 'SYNC',

                requestSource: $sourceReuqest,
                requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest,
                priority: 1,
            );

            if (! $response['success']) {
                throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
            }

            $absenRows = collect($response['data']) ?? collect([]);

            foreach ($absenRows as $absen) {
                $uid = (string) ($absen->UserID ?? ($absen->UserId ?? ($absen->Userid ?? '')));
                $uid = trim($uid);
                if (preg_match('/^\d+$/', $uid)) {
                    $uid = (string) intval($uid);
                }

                $kodeKaryawanAbsen = (string) ($absen->Kode_Karyawan ?? '');
                $kodeKaryawanAbsen = trim($kodeKaryawanAbsen);
                $tanggalRaw = $absen->Tanggal ?? null;
                if (($uid === '' && $kodeKaryawanAbsen === '') || $tanggalRaw === null) {
                    continue;
                }

                $tanggal = Carbon::parse((string) $tanggalRaw)->format('Y-m-d');
                $payload = [
                    'check_in' => $absen->CheckIn ?? null,
                    'check_out' => $absen->CheckOut ?? null,
                    'nama_shift' => $absen->Nama_Shift ?? null,
                    'jam_masuk' => $absen->Jam_Masuk ?? null,
                    'jam_keluar' => $absen->Jam_Keluar ?? null,
                ];

                if ($uid !== '') {
                    $absenIndexByUserId[$uid.'|'.$tanggal] = $payload;
                }
                if ($kodeKaryawanAbsen !== '') {
                    $absenIndexByKaryawan[$kodeKaryawanAbsen.'|'.$tanggal] = $payload;
                }
            }
        }

        $shiftFallbackIndex = [];
        $pairs = $rows
            ->map(function ($row) {
                $kode = trim((string) ($row->Kode_Karyawan ?? ''));
                $tanggal = $row->effective_work_date
                    ? Carbon::parse((string) $row->effective_work_date)->format('Y-m-d')
                    : null;

                return $kode !== '' && $tanggal ? $kode.'|'.$tanggal : null;
            })
            ->filter()
            ->unique()
            ->values();

        foreach ($pairs as $pairKey) {
            [$kodePair, $tanggalPair] = explode('|', $pairKey, 2);
            $shiftRow = collect(
                DB::select(
                    'SELECT TOP 1 Nama_Shift, Jam_Masuk, Jam_Keluar, StatusHari
                 FROM dbo.N_HRIS_FN_GetStatusHariRange(?, ?, ?, ?)',
                    [$kodePerusahaan, $kodePair, $tanggalPair, $tanggalPair],
                ),
            )->first();

            if ($shiftRow) {
                $shiftFallbackIndex[$pairKey] = [
                    'nama_shift' => $shiftRow->Nama_Shift ?? null,
                    'jam_masuk' => $shiftRow->Jam_Masuk ?? null,
                    'jam_keluar' => $shiftRow->Jam_Keluar ?? null,
                    'status_hari' => $shiftRow->StatusHari ?? null,
                ];
            }
        }

        return $rows
            ->map(function ($row) use ($absenIndexByUserId, $absenIndexByKaryawan, $shiftFallbackIndex) {
                $uid = (string) ($row->user_id_absen ?? '');
                $uid = trim($uid);
                if (preg_match('/^\d+$/', $uid)) {
                    $uid = (string) intval($uid);
                }
                $kodeKaryawan = trim((string) ($row->Kode_Karyawan ?? ''));
                $tanggal = $row->effective_work_date
                    ? Carbon::parse((string) $row->effective_work_date)->format('Y-m-d')
                    : null;
                $keyByUser = $uid !== '' && $tanggal ? $uid.'|'.$tanggal : null;
                $keyByKaryawan = $kodeKaryawan !== '' && $tanggal ? $kodeKaryawan.'|'.$tanggal : null;

                $absen =
                    $keyByUser && isset($absenIndexByUserId[$keyByUser])
                        ? $absenIndexByUserId[$keyByUser]
                        : ($keyByKaryawan && isset($absenIndexByKaryawan[$keyByKaryawan])
                            ? $absenIndexByKaryawan[$keyByKaryawan]
                            : [
                                'check_in' => null,
                                'check_out' => null,
                                'nama_shift' => null,
                                'jam_masuk' => null,
                                'jam_keluar' => null,
                            ]);

                $fallbackKey = $kodeKaryawan !== '' && $tanggal ? $kodeKaryawan.'|'.$tanggal : null;
                $fallbackShift =
                    $fallbackKey && isset($shiftFallbackIndex[$fallbackKey])
                        ? $shiftFallbackIndex[$fallbackKey]
                        : [
                            'nama_shift' => null,
                            'jam_masuk' => null,
                            'jam_keluar' => null,
                            'status_hari' => null,
                        ];

                $row->Has_CheckIn = ! empty($absen['check_in']) ? 'Y' : 'T';
                $row->Has_CheckOut = ! empty($absen['check_out']) ? 'Y' : 'T';
                $row->Nama_Shift = $absen['nama_shift'] ?: $row->req_nama_shift ?? null ?: $fallbackShift['nama_shift'];
                $row->Jam_Masuk = $absen['jam_masuk'] ?: $row->req_jam_masuk ?? null ?: $fallbackShift['jam_masuk'];
                $row->Jam_Keluar = $absen['jam_keluar'] ?: $row->req_jam_keluar ?? null ?: $fallbackShift['jam_keluar'];
                $row->Status_Hari = $fallbackShift['status_hari'];

                unset($row->user_id_absen);
                unset($row->Kode_Perusahaan);
                unset($row->Id_Shift);
                unset($row->req_nama_shift);
                unset($row->req_jam_masuk);
                unset($row->req_jam_keluar);

                return (array) $row;
            })
            ->all();
    }

    protected function validateSubmissionRow(
        array $row,
        array $context,
        string $kodePerusahaan,
        bool $isAdminContext = false,
    ): array {
        try {
            $kodeKaryawan = $this->resolveKodeKaryawan($row['id'] ?? null);
            $effectiveDate = $this->resolveEffectiveWorkDate($row, $context, $this->resolveDate($row, $context));
            $requestedType = $this->normalizeRequestType((string) ($row['type'] ?? ($context['type'] ?? 'LEMBUR_HK')));
            $requestedStart = $this->normalizeTime(
                (string) ($row['startTime'] ?? ($context['start_time'] ?? ($context['end_time'] ?? '00:00:00'))),
            );
            $requestedEnd = $this->normalizeTime(
                (string) ($row['endTime'] ?? ($context['end_time'] ?? $requestedStart)),
            );

            $latest = $this->getLatestEligibilityContext(
                $kodePerusahaan,
                $kodeKaryawan,
                $effectiveDate->format('Y-m-d'),
                // getActiveUsers kini mengambil shift tanggal terpilih dari TVF jadwal LIVE
                // (N_HRIS_FN_GetStatusHariRange), bukan snapshot. Anti-tamper harus memakai
                // sumber yang SAMA agar tidak false-positive "STATUS/SHIFT_CHANGED" untuk
                // karyawan yang snapshot-nya belum ter-generate. useSnapshot=false = TVF.
                false,
            );

            $snapshotShift = isset($row['snapshot_shift_id']) ? (string) $row['snapshot_shift_id'] : null;
            $snapshotStatus = isset($row['snapshot_status_hari']) ? (string) $row['snapshot_status_hari'] : null;
            $snapshotMasuk = isset($row['snapshot_jam_masuk']) ? (string) $row['snapshot_jam_masuk'] : null;
            $snapshotKeluar = isset($row['snapshot_jam_keluar']) ? (string) $row['snapshot_jam_keluar'] : null;

            if (
                $snapshotShift !== null &&
                $snapshotShift !== '' &&
                $snapshotShift !== (string) ($latest['id_shift'] ?? '')
            ) {
                return [
                    'is_valid' => false,
                    'code' => 'SHIFT_CHANGED',
                    'reason' => 'Shift karyawan berubah. Harap refresh data.',
                ];
            }
            if (
                $snapshotStatus !== null &&
                $snapshotStatus !== '' &&
                strtoupper($snapshotStatus) !== strtoupper((string) ($latest['status_hari'] ?? ''))
            ) {
                return [
                    'is_valid' => false,
                    'code' => 'STATUS_CHANGED',
                    'reason' => 'Status hari kerja berubah. Harap refresh data.',
                ];
            }
            if (
                $snapshotMasuk !== null &&
                $snapshotMasuk !== '' &&
                $snapshotMasuk !== (string) ($latest['jam_masuk'] ?? '')
            ) {
                return [
                    'is_valid' => false,
                    'code' => 'SHIFT_TIME_CHANGED',
                    'reason' => 'Jam masuk/keluar shift berubah. Harap refresh data.',
                ];
            }
            if (
                $snapshotKeluar !== null &&
                $snapshotKeluar !== '' &&
                $snapshotKeluar !== (string) ($latest['jam_keluar'] ?? '')
            ) {
                return [
                    'is_valid' => false,
                    'code' => 'SHIFT_TIME_CHANGED',
                    'reason' => 'Jam masuk/keluar shift berubah. Harap refresh data.',
                ];
            }

            // Untuk lembur awal, bila end time tidak dikirim dari UI,
            // gunakan jam masuk shift agar range validasi tidak melebar 24 jam.
            $requestedEndRaw = $row['endTime'] ?? ($context['end_time'] ?? null);
            if (
                $requestedType === 'LEMBUR_AWAL' &&
                ($requestedEndRaw === null || trim((string) $requestedEndRaw) === '')
            ) {
                $shiftStartFallback = $this->tryNormalizeTime((string) ($latest['jam_masuk'] ?? ''));
                if ($shiftStartFallback) {
                    $requestedEnd = $shiftStartFallback;
                }
            }

            $eligibility = $this->evaluateUserEligibility(
                $requestedType,
                $requestedStart,
                $requestedEnd,
                $effectiveDate->format('Y-m-d'),
                $latest['status_hari'] ?? null,
                $latest['jam_masuk'] ?? null,
                $latest['jam_keluar'] ?? null,
                $latest['id_shift'] ?? null,
                $latest['lembur_pernah'] ?? 'FALSE',
                $kodePerusahaan,
                $kodeKaryawan,
                null,
                $isAdminContext,
            );

            if (! $eligibility['is_active']) {
                return [
                    'is_valid' => false,
                    'code' => 'INELIGIBLE',
                    'reason' => (string) ($eligibility['reason'] ?? 'Data berubah, harap refresh data karyawan.'),
                ];
            }

            return ['is_valid' => true];
        } catch (\Throwable $e) {
            return [
                'is_valid' => false,
                'code' => 'VALIDATION_ERROR',
                'reason' => 'Validasi submit gagal: '.$e->getMessage(),
            ];
        }
    }

    protected function getLatestEligibilityContext(
        string $kodePerusahaan,
        string $kodeKaryawan,
        string $effectiveDate,
        bool $useSnapshot = false,
    ): array {
        // Subquery EXISTS (lembur_pernah) identik untuk kedua sumber shift.
        $lemburPernahCase = "
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM Transaksi_Lembur_Detail a
                        JOIN Transaksi_Lembur_Det d
                          ON d.Kode_Perusahaan = a.Kode_Perusahaan
                         AND d.No_Transaksi = a.No_Transaksi
                         AND d.Kode_Karyawan = a.Kode_Karyawan
                         AND d.Urut_Detail = a.Urut_Oto
                        JOIN Transaksi_Lembur b
                          ON b.Kode_Perusahaan = a.Kode_Perusahaan
                         AND b.No_Transaksi = a.No_Transaksi
                        WHERE a.Kode_Perusahaan = ?
                          AND a.Kode_Karyawan = ?
                          AND b.Status IS NULL
                          AND d.Tanggal_Lembur >= CAST(? AS DATETIME)
                          AND d.Tanggal_Lembur < DATEADD(DAY, 1, CAST(? AS DATETIME))
                    ) THEN 'TRUE' ELSE 'FALSE'
                END AS lembur_pernah
        ";

        if ($useSnapshot) {
            // Sumber shift = N_HRIS_Absen_Snapshot (jalur lama). CATATAN: sejak
            // getActiveUsers beralih ke TVF live untuk shift tanggal terpilih, anti-tamper
            // submit TIDAK lagi memakai branch ini (pakai useSnapshot=false). Branch ini
            // dipertahankan untuk pemanggil lain yang memang butuh sumber snapshot.
            $sql = "
                SELECT TOP 1
                    COALESCE(sh.StatusHari, dbo.N_HRIS_FN_GetStatusHari(?, ?, ?)) AS status_hari,
                    sh.Jam_Masuk AS jam_masuk,
                    sh.Jam_Keluar AS jam_keluar,
                    sh.Id_Shift AS id_shift,
                    sh.Nama_Shift AS nama_shift,
                    {$lemburPernahCase}
                FROM (SELECT ? AS Kode_Karyawan, ? AS Kode_Perusahaan) e
                LEFT JOIN N_HRIS_Absen_Snapshot sh
                    ON sh.Kode_Perusahaan = e.Kode_Perusahaan
                   AND sh.Kode_Karyawan = e.Kode_Karyawan
                   AND sh.Tanggal = ?
            ";

            $bindings = [
                $kodePerusahaan,
                $kodeKaryawan,
                $effectiveDate,
                $kodePerusahaan,
                $kodeKaryawan,
                $effectiveDate,
                $effectiveDate,
                $kodeKaryawan,
                $kodePerusahaan,
                $effectiveDate,
            ];

            $row = DB::selectOne($sql, $bindings);

            return (array) ($row ?? []);
        }

        // Sumber shift = TVF real-time (N_HRIS_FN_GetStatusHariRange). Dipakai untuk
        // resolusi shift hari berikutnya (H+1) DAN untuk anti-tamper submit tanggal
        // terpilih — sumber yang SAMA dengan getActiveUsers (yang kini juga TVF), agar
        // validasi konsisten dan karyawan tanpa snapshot tetap bisa disubmit.
        $sql = "
            SELECT TOP 1
                COALESCE(sh.StatusHari, dbo.N_HRIS_FN_GetStatusHari(?, ?, ?)) AS status_hari,
                sh.Jam_Masuk AS jam_masuk,
                sh.Jam_Keluar AS jam_keluar,
                sh.ID_Shift AS id_shift,
                sk.Nama AS nama_shift,
                {$lemburPernahCase}
            FROM (SELECT ? AS Kode_Karyawan) e
            OUTER APPLY (
                SELECT TOP 1 *
                FROM dbo.N_HRIS_FN_GetStatusHariRange(?, e.Kode_Karyawan, ?, ?)
            ) sh
            LEFT JOIN HRIS_Shift_Kerja sk ON sk.ID_Shift = sh.ID_Shift
        ";

        $bindings = [
            $kodePerusahaan,
            $kodeKaryawan,
            $effectiveDate,
            $kodePerusahaan,
            $kodeKaryawan,
            $effectiveDate,
            $effectiveDate,
            $kodeKaryawan,
            $kodePerusahaan,
            $effectiveDate,
            $effectiveDate,
        ];

        $row = DB::selectOne($sql, $bindings);

        return (array) ($row ?? []);
    }

    protected function normalizeRequestType(string $rawType): string
    {
        $rawType = strtoupper(trim($rawType));
        $typeMap = [
            'LEMBUR_HK' => 'LEMBUR',
            'LEMBUR_HARI_KERJA' => 'LEMBUR',
            'LEMBUR' => 'LEMBUR',
            'LEMBUR_AWAL' => 'LEMBUR_AWAL',
            'LEMBUR_MINGGU' => 'LEMBUR_LIBUR',
            'LEMBUR_LIBUR' => 'LEMBUR_LIBUR',
        ];

        return $typeMap[$rawType] ?? 'LEMBUR';
    }

    protected function evaluateUserEligibility(
        string $requestedType,
        string $requestedStart,
        string $requestedEnd,
        string $requestedDate,
        ?string $statusHari,
        ?string $jamMasuk,
        ?string $jamKeluar,
        $idShift,
        string $lemburPernah,
        ?string $kodePerusahaan = null,
        ?string $kodeKaryawan = null,
        ?array $eligibilityCache = null,
        bool $isAdminContext = false,
    ): array {
        $requestStartAtGlobal = null;
        $requestEndAtGlobal = null;
        try {
            $requestStartAtGlobal = Carbon::parse($requestedDate.' '.$requestedStart);
            $requestEndAtGlobal = Carbon::parse($requestedDate.' '.$requestedEnd);
            if ($requestEndAtGlobal->lessThanOrEqualTo($requestStartAtGlobal)) {
                $requestEndAtGlobal->addDay();
            }
        } catch (\Throwable $e) {
            return [
                'is_active' => false,
                'reason' => 'Format waktu request lembur tidak valid.',
            ];
        }

        if (
            $kodePerusahaan &&
            $kodeKaryawan &&
            $this->hasExistingActiveOvertimeOfSameTypeOnEffectiveDateOptimized(
                $requestedType,
                $kodePerusahaan,
                $kodeKaryawan,
                $requestedDate,
                $eligibilityCache,
            )
        ) {
            return [
                'is_active' => false,
                'reason' => $this->buildSameTypeExistingOvertimeReason($requestedType),
            ];
        }

        $status = strtoupper((string) $statusHari);
        $isOffDay = str_starts_with($status, 'OFF');

        if ($requestedType === 'LEMBUR_LIBUR') {
            if (
                $kodePerusahaan &&
                $kodeKaryawan &&
                $this->hasOverlappingExistingOvertimeOptimized(
                    $kodePerusahaan,
                    $kodeKaryawan,
                    $requestStartAtGlobal,
                    $requestEndAtGlobal,
                    $eligibilityCache,
                )
            ) {
                return [
                    'is_active' => false,
                    'reason' => 'Range lembur bertabrakan dengan lembur aktif sebelumnya/berikutnya.',
                ];
            }

            if (! $isOffDay) {
                return [
                    'is_active' => false,
                    'reason' => 'Lembur libur hanya bisa saat status hari OFF.',
                ];
            }

            $minimalDurationHours = (float) config('services.hcis.lembur_request.minimal_durasi_lembur', 1);
            $durationHours = $requestStartAtGlobal->diffInMinutes($requestEndAtGlobal) / 60;
            if ($minimalDurationHours > 0 && $durationHours < $minimalDurationHours) {
                return [
                    'is_active' => false,
                    'reason' => "Durasi lembur minimal {$minimalDurationHours} jam.",
                ];
            }

            $maxDurationLiburHours = (float) config('services.hcis.lembur_request.batas_durasi_lembur_libur', 18);
            if ($maxDurationLiburHours > 0 && $durationHours > $maxDurationLiburHours) {
                return [
                    'is_active' => false,
                    'reason' => "Durasi lembur libur melebihi batas maksimal {$maxDurationLiburHours} jam.",
                ];
            }

            return [
                'is_active' => true,
                'reason' => null,
            ];
        }

        if ($status === '') {
            return [
                'is_active' => false,
                'reason' => 'Status hari kerja tidak ditemukan untuk tanggal ini.',
            ];
        }

        if ($status !== 'WORK' && $requestedType !== 'LEMBUR_LIBUR') {
            return [
                'is_active' => false,
                'reason' => 'Libur pada hari ini.',
            ];
        }

        $hasShiftIdentity = ! empty((string) $idShift);
        if (! $isOffDay && $requestedType !== 'LEMBUR_LIBUR' && ! $hasShiftIdentity) {
            return [
                'is_active' => false,
                'reason' => 'Shift kerja belum tersedia untuk tanggal ini (status hari WORK).',
            ];
        }

        if (! $isOffDay && $requestedType !== 'LEMBUR_LIBUR' && (! $jamMasuk || ! $jamKeluar)) {
            return [
                'is_active' => false,
                'reason' => 'Shift tidak ditemukan untuk tanggal ini.',
            ];
        }

        $normalizedJamMasuk = $this->tryNormalizeTime((string) $jamMasuk);
        $normalizedJamKeluar = $this->tryNormalizeTime((string) $jamKeluar);
        if (! $normalizedJamMasuk || ! $normalizedJamKeluar) {
            return [
                'is_active' => false,
                'reason' => 'Jam shift tidak valid untuk tanggal ini.',
            ];
        }

        if ($normalizedJamMasuk === $normalizedJamKeluar) {
            return [
                'is_active' => false,
                'reason' => 'Konfigurasi jam shift tidak valid (jam masuk = jam keluar).',
            ];
        }

        // Prioritas reason khusus lembur awal:
        // Jika jam mulai yang dipilih jatuh di dalam window jam kerja shift terpilih,
        // tampilkan reason "harus sebelum jam masuk shift" agar tidak ambigu.
        if ($requestedType === 'LEMBUR_AWAL') {
            $requestStartMinute = $this->timeToMinuteOfDay($requestedStart);
            $shiftStartMinute = $this->timeToMinuteOfDay($normalizedJamMasuk);
            $shiftEndMinute = $this->timeToMinuteOfDay($normalizedJamKeluar);
            if ($this->isMinuteInsideShiftWindow($requestStartMinute, $shiftStartMinute, $shiftEndMinute)) {
                return [
                    'is_active' => false,
                    'reason' => 'Jam mulai lembur awal harus sebelum jam masuk shift.',
                ];
            }
        }

        try {
            $shiftStart = Carbon::parse($requestedDate.' '.$normalizedJamMasuk);
            $shiftEndRaw = Carbon::parse($requestedDate.' '.$normalizedJamKeluar);
            $isOvernightShift = $shiftEndRaw->lessThanOrEqualTo($shiftStart);
            $shiftEnd = $shiftEndRaw->copy();
            if ($isOvernightShift) {
                $shiftEnd->addDay();
            }

            $requestStartAt = Carbon::parse($requestedDate.' '.$requestedStart);
            $requestEndAt = Carbon::parse($requestedDate.' '.$requestedEnd);

            // Untuk lembur awal, waktu request diproyeksikan relatif ke jam masuk shift.
            // Jika jam request >= jam masuk shift (clock-wise), artinya kemungkinan di H-1.
            if ($requestedType === 'LEMBUR_AWAL') {
                $requestStartAt = $this->resolveLemburAwalDateTime(
                    $requestedDate,
                    $requestedStart,
                    $normalizedJamMasuk,
                );
                $requestEndAt = $this->resolveLemburAwalDateTime($requestedDate, $requestedEnd, $normalizedJamMasuk);
            }

            // Untuk lembur normal pada shift lintas hari:
            // jam request yang <= jam masuk shift diasumsikan terjadi di hari berikutnya.
            if ($requestedType === 'LEMBUR' && $isOvernightShift) {
                if ($requestStartAt->lessThan($shiftStart)) {
                    $requestStartAt->addDay();
                }
                if ($requestEndAt->lessThan($shiftStart)) {
                    $requestEndAt->addDay();
                }
            }

            if ($requestEndAt->lessThanOrEqualTo($requestStartAt)) {
                $requestEndAt->addDay();
            }
        } catch (\Throwable $e) {
            return [
                'is_active' => false,
                'reason' => 'Format waktu shift/request tidak valid.',
            ];
        }

        if ($requestedType === 'LEMBUR_AWAL') {
            return $this->evaluateEligibilityLemburAwal(
                $requestStartAt,
                $shiftStart,
                $shiftEnd,
                $kodePerusahaan,
                $kodeKaryawan,
                $eligibilityCache,
                $isAdminContext,
            );
        }

        if ($requestedType === 'LEMBUR') {
            return $this->evaluateEligibilityLemburHariKerja(
                $requestedDate,
                $requestStartAt,
                $requestEndAt,
                $shiftStart,
                $shiftEnd,
                $kodePerusahaan,
                $kodeKaryawan,
                $eligibilityCache,
                $isAdminContext,
            );
        }

        return [
            'is_active' => false,
            'reason' => 'Tipe lembur tidak dikenali.',
        ];
    }

    protected function timeToMinuteOfDay(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }

    protected function isMinuteInsideShiftWindow(int $minute, int $shiftStart, int $shiftEnd): bool
    {
        if ($shiftEnd > $shiftStart) {
            return $minute >= $shiftStart && $minute < $shiftEnd;
        }

        return $minute >= $shiftStart || $minute < $shiftEnd;
    }

    protected function evaluateEligibilityLemburAwal(
        Carbon $requestStartAt,
        Carbon $shiftStart,
        Carbon $shiftEnd,
        ?string $kodePerusahaan,
        ?string $kodeKaryawan,
        ?array $eligibilityCache = null,
        bool $isAdminContext = false,
    ): array {
        // Strict policy: jam akhir selalu jam masuk shift.
        $requestEndAt = $shiftStart->copy();
        $previousShiftEnd = $shiftEnd->copy()->subDay();

        if ($requestStartAt->greaterThanOrEqualTo($requestEndAt)) {
            return [
                'is_active' => false,
                'reason' => 'Jam mulai lembur awal harus sebelum jam masuk shift.',
            ];
        }

        // Rule 1: lembur awal hanya boleh setelah jam pulang shift sebelumnya.
        if ($requestStartAt->lessThan($previousShiftEnd)) {
            return [
                'is_active' => false,
                'reason' => 'Jam mulai lembur awal harus setelah jam pulang shift sebelumnya.',
            ];
        }

        $awalLimitHours = (float) config('services.hcis.lembur_request.batas_lembur_awal_kerja_dari_jam_kerja', 5);
        if ($awalLimitHours > 0) {
            $earliestStartAllowed = $shiftStart->copy()->subMinutes((int) round($awalLimitHours * 60));
            if ($requestStartAt->lessThan($earliestStartAllowed)) {
                return [
                    'is_active' => false,
                    'reason' => "Jam mulai lembur awal maksimal {$awalLimitHours} jam sebelum jam masuk shift.",
                ];
            }
        }

        $minimalDurationHours = (float) config('services.hcis.lembur_request.minimal_durasi_lembur', 1);
        $durationHours = $requestStartAt->diffInMinutes($requestEndAt) / 60;
        if ($minimalDurationHours > 0 && $durationHours < $minimalDurationHours) {
            return [
                'is_active' => false,
                'reason' => "Durasi lembur minimal {$minimalDurationHours} jam.",
            ];
        }

        // Window pengajuan tetap berlaku: maksimal +N jam dari jam pulang shift.
        if (! $isAdminContext) {
            $hourLimit = (float) config('services.hcis.lembur_request.batas_jam_request_lembur', 4);
            $hourLimit = $hourLimit > 0 ? $hourLimit : 4;
            $windowEnd = $shiftEnd->copy()->addMinutes((int) round($hourLimit * 60));
            if ($this->currentNow()->greaterThan($windowEnd)) {
                return [
                    'is_active' => false,
                    'reason' => "Window pengajuan lembur sudah/tidak berlaku (hanya dari jam pulang sampai +{$hourLimit} jam).",
                ];
            }
        }

        if (
            $kodePerusahaan &&
            $kodeKaryawan &&
            $this->hasOverlappingExistingOvertimeOptimized(
                $kodePerusahaan,
                $kodeKaryawan,
                $requestStartAt,
                $requestEndAt,
                $eligibilityCache,
            )
        ) {
            return [
                'is_active' => false,
                'reason' => 'Range lembur bertabrakan dengan pengajuan lembur aktif lainnya.',
            ];
        }

        return [
            'is_active' => true,
            'reason' => null,
        ];
    }

    protected function evaluateEligibilityLemburHariKerja(
        string $requestedDate,
        Carbon $requestStartAt,
        Carbon $requestEndAt,
        Carbon $shiftStart,
        Carbon $shiftEnd,
        ?string $kodePerusahaan,
        ?string $kodeKaryawan,
        ?array $eligibilityCache = null,
        bool $isAdminContext = false,
    ): array {
        $hourLimit = (float) config('services.hcis.lembur_request.batas_jam_request_lembur', 4);
        $hourLimit = $hourLimit > 0 ? $hourLimit : 4;
        $windowEnd = $shiftEnd->copy()->addMinutes((int) round($hourLimit * 60));
        $requestedDay = Carbon::parse($requestedDate)->startOfDay();
        $today = $this->currentNow()->copy()->startOfDay();
        $isBackdate = $requestedDay->lessThan($today);

        // Untuk backdate, tampilkan reason window lebih dulu agar tidak ambigu.
        if (! $isAdminContext && $isBackdate && $this->currentNow()->greaterThan($windowEnd)) {
            return [
                'is_active' => false,
                'reason' => "Window pengajuan lembur sudah/tidak berlaku (hanya dari jam pulang sampai +{$hourLimit} jam).",
            ];
        }

        // PRIORITAS REASON LEMBUR_HK:
        // 1) Durasi minimal/maksimal
        // 2) Bentrok jam kerja shift
        // 3) Bentrok lembur aktif existing
        // 4) Terlalu dekat jam kerja berikutnya
        // 5) Window pengajuan lewat
        $maxDurationHours = (float) config('services.hcis.lembur_request.batas_durasi_lembur', 18);
        $durationHours = $requestStartAt->diffInMinutes($requestEndAt) / 60;
        $minimalDurationHours = (float) config('services.hcis.lembur_request.minimal_durasi_lembur', 1);
        if ($minimalDurationHours > 0 && $durationHours < $minimalDurationHours) {
            return [
                'is_active' => false,
                'reason' => "Durasi lembur minimal {$minimalDurationHours} jam.",
            ];
        }
        if ($maxDurationHours > 0 && $durationHours > $maxDurationHours) {
            return [
                'is_active' => false,
                'reason' => "Durasi lembur melebihi batas maksimal {$maxDurationHours} jam.",
            ];
        }

        $isOverlapShift = $requestStartAt->lessThan($shiftEnd) && $requestEndAt->greaterThan($shiftStart);
        if ($isOverlapShift) {
            return [
                'is_active' => false,
                'reason' => 'Range lembur bertabrakan dengan jam kerja shift.',
            ];
        }

        if (
            $kodePerusahaan &&
            $kodeKaryawan &&
            $this->hasOverlappingExistingOvertimeOptimized(
                $kodePerusahaan,
                $kodeKaryawan,
                $requestStartAt,
                $requestEndAt,
                $eligibilityCache,
            )
        ) {
            return [
                'is_active' => false,
                'reason' => 'Range lembur bertabrakan dengan pengajuan lembur aktif lainnya.',
            ];
        }

        $closenessHours = (float) config(
            'services.hcis.lembur_request.batas_kedekatan_akhir_lembur_dengan_jam_kerja',
            18,
        );
        $nextShiftStart = $this->resolveNextShiftStart(
            $requestedDate,
            $shiftStart,
            $kodePerusahaan,
            $kodeKaryawan,
            $eligibilityCache,
        );
        $latestEndAllowed = $nextShiftStart->copy()->subMinutes((int) round($closenessHours * 60));
        if ($requestEndAt->greaterThan($latestEndAllowed)) {
            $reqEndLabel = $requestEndAt->format('d/m H:i');
            $nextStartLabel = $nextShiftStart->format('d/m H:i');
            $latestEndLabel = $latestEndAllowed->format('d/m H:i');
            $nextShiftName = $this->resolveNextShiftName(
                $requestedDate,
                $kodePerusahaan,
                $kodeKaryawan,
                $eligibilityCache,
            );
            $nextShiftLabel = $nextShiftName ? "shift {$nextShiftName} ({$nextStartLabel})" : "jam {$nextStartLabel}";

            return [
                'is_active' => false,
                'reason' => "Jam akhir lembur ({$reqEndLabel}) terlalu dekat dengan jam masuk kerja berikutnya: {$nextShiftLabel}. Jeda minimal {$closenessHours} jam, jadi lembur paling lambat selesai jam {$latestEndLabel}.",
            ];
        }

        // Untuk hari aktif/non-backdate, window dipakai sebagai final gate.
        if (! $isAdminContext && $this->currentNow()->greaterThan($windowEnd)) {
            return [
                'is_active' => false,
                'reason' => "Window pengajuan lembur sudah/tidak berlaku (hanya dari jam pulang sampai +{$hourLimit} jam).",
            ];
        }

        return [
            'is_active' => true,
            'reason' => null,
        ];
    }

    protected function hasOverlappingExistingOvertime(
        string $kodePerusahaan,
        string $kodeKaryawan,
        Carbon $requestStartAt,
        Carbon $requestEndAt,
    ): bool {
        return DB::table('Transaksi_Lembur_Detail as d')
            ->join('Transaksi_Lembur as h', function ($join) {
                $join->on('h.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')->on('h.No_Transaksi', '=', 'd.No_Transaksi');
            })
            ->where('d.Kode_Perusahaan', $kodePerusahaan)
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('h.Status')
            ->where('d.Tanggal_Lembur_Dari', '<', $requestEndAt->format('Y-m-d H:i:s'))
            ->where('d.Tanggal_Lembur_Sampai', '>', $requestStartAt->format('Y-m-d H:i:s'))
            ->exists();
    }

    protected function hasOverlappingExistingOvertimeOptimized(
        string $kodePerusahaan,
        string $kodeKaryawan,
        Carbon $requestStartAt,
        Carbon $requestEndAt,
        ?array $eligibilityCache = null,
    ): bool {
        // Cache berlaku bila orangnya memang ikut diperiksa saat cache dibangun.
        // Jendela cache membentang H-1..H+2 dari tanggal permintaan, sedangkan
        // $requestStartAt/$requestEndAt selalu jatuh pada tanggal itu — jadi
        // cakupan cache selalu memuat rentang yang ditanyakan di sini.
        $dicakup = (bool) data_get($eligibilityCache, "covered.{$kodeKaryawan}", false);
        $intervals = (array) data_get($eligibilityCache, "overlap_intervals.{$kodeKaryawan}", []);

        if ($dicakup && empty($intervals)) {
            return false;
        }

        if (! empty($intervals)) {
            foreach ($intervals as $interval) {
                $start = isset($interval['start']) ? Carbon::parse((string) $interval['start']) : null;
                $end = isset($interval['end']) ? Carbon::parse((string) $interval['end']) : null;
                if (! $start || ! $end) {
                    continue;
                }

                if ($start->lt($requestEndAt) && $end->gt($requestStartAt)) {
                    return true;
                }
            }

            return false;
        }

        return $this->hasOverlappingExistingOvertime($kodePerusahaan, $kodeKaryawan, $requestStartAt, $requestEndAt);
    }

    protected function hasExistingActiveOvertimeOfSameTypeOnEffectiveDateOptimized(
        string $requestedType,
        string $kodePerusahaan,
        string $kodeKaryawan,
        string $effectiveDate,
        ?array $eligibilityCache = null,
    ): bool {
        $normalizedType = $this->normalizeStoredJenisForRequestType($requestedType);
        $employeeTypeDates = (array) data_get($eligibilityCache, "existing_type_dates.{$kodeKaryawan}", []);

        // Idem hasOverlappingExistingOvertimeOptimized(): orang yang sudah
        // diperiksa saat cache dibangun dan tidak punya baris apa pun memang
        // TIDAK punya, bukan belum ditanyakan.
        if (empty($employeeTypeDates) && data_get($eligibilityCache, "covered.{$kodeKaryawan}", false)) {
            return false;
        }

        if (! empty($employeeTypeDates)) {
            $dates = array_values(array_unique((array) ($employeeTypeDates[$normalizedType] ?? [])));

            return in_array($effectiveDate, $dates, true);
        }

        $effectiveStart = Carbon::parse($effectiveDate)->startOfDay()->format('Y-m-d H:i:s');
        $effectiveEnd = Carbon::parse($effectiveDate)->addDay()->startOfDay()->format('Y-m-d H:i:s');

        return DB::table('Transaksi_Lembur_Detail as d')
            ->join('Transaksi_Lembur as h', function ($join) {
                $join->on('h.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')->on('h.No_Transaksi', '=', 'd.No_Transaksi');
            })
            ->join('Transaksi_Lembur_Det as det', function ($join) {
                $join
                    ->on('det.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')
                    ->on('det.No_Transaksi', '=', 'd.No_Transaksi')
                    ->on('det.Kode_Karyawan', '=', 'd.Kode_Karyawan')
                    ->on('det.Urut_Detail', '=', 'd.Urut_Oto');
            })
            ->where('d.Kode_Perusahaan', $kodePerusahaan)
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->where('d.Jenis', $normalizedType)
            ->whereNull('h.Status')
            ->where('det.Tanggal_Lembur', '>=', $effectiveStart)
            ->where('det.Tanggal_Lembur', '<', $effectiveEnd)
            ->exists();
    }

    protected function normalizeStoredJenisForRequestType(string $requestedType): string
    {
        $normalized = $this->normalizeRequestType($requestedType);

        return match ($normalized) {
            'LEMBUR_AWAL' => 'LEMBUR_AWAL',
            'LEMBUR_LIBUR' => 'LEMBUR_LIBUR',
            default => 'LEMBUR',
        };
    }

    protected function buildSameTypeExistingOvertimeReason(string $requestedType): string
    {
        return match ($this->normalizeStoredJenisForRequestType($requestedType)) {
            'LEMBUR_AWAL' => 'Sudah ditugaskan lembur awal pada tanggal kerja ini.',
            'LEMBUR_LIBUR' => 'Sudah ditugaskan lembur libur pada tanggal ini.',
            default => 'Sudah ditugaskan lembur hari kerja pada tanggal kerja ini.',
        };
    }

    protected function resolveNextShiftStart(
        string $requestedDate,
        Carbon $fallbackShiftStart,
        ?string $kodePerusahaan,
        ?string $kodeKaryawan,
        ?array $eligibilityCache = null,
    ): Carbon {
        $default = $fallbackShiftStart->copy()->addDay();

        $cached = (string) data_get($eligibilityCache, "next_shift_start.{$kodeKaryawan}", '');
        if ($cached !== '') {
            try {
                return Carbon::parse($cached);
            } catch (\Throwable $e) {
                // fallback to dynamic resolver
            }
        }

        /* Kekosongan adalah data — pelajaran yang sama dengan 'covered' di atas.
           Cache hanya mengisi next_shift_start bila besok WORK, jadi bagi
           karyawan yang besok libur kuncinya memang TIDAK ADA. Selama ia
           tercakup cache, ketiadaan itu berarti "besok tidak bekerja", bukan
           "belum tahu" — dan bertanya ke basis data hanya mengulang jawaban
           yang sudah dipegang. Satu-satunya hasil yang berguna dari kueri itu
           (WORK + jam masuk) justru persis kasus yang sudah tercatat di cache. */
        if ($this->tercakupCacheKelayakan($eligibilityCache, $kodeKaryawan)) {
            return $default;
        }

        if (! $kodePerusahaan || ! $kodeKaryawan) {
            return $default;
        }

        try {
            $nextDate = Carbon::parse($requestedDate)->addDay()->format('Y-m-d');
            $nextContext = $this->getLatestEligibilityContext($kodePerusahaan, $kodeKaryawan, $nextDate);
            $nextStatus = strtoupper((string) ($nextContext['status_hari'] ?? ''));
            $nextJamMasuk = $this->tryNormalizeTime((string) ($nextContext['jam_masuk'] ?? ''));

            if ($nextStatus === 'WORK' && $nextJamMasuk) {
                return Carbon::parse($nextDate.' '.$nextJamMasuk);
            }
        } catch (\Throwable $e) {
            // fallback ke shift+1 hari jika gagal resolve
        }

        return $default;
    }

    /**
     * Apakah karyawan ini SUDAH diperiksa oleh cache kelayakan?
     *
     * Membedakan "cache bilang tidak ada" dari "cache tak tahu". Tanpa
     * pembedaan itu, tiap karyawan yang besok libur memicu satu kueri yang
     * jawabannya sudah dipegang — pada "cari orang" lembur itu 48 kueri dan
     * 5,9 detik dari 11 detik total.
     */
    private function tercakupCacheKelayakan(?array $eligibilityCache, ?string $kodeKaryawan): bool
    {
        if ($eligibilityCache === null || $kodeKaryawan === null || $kodeKaryawan === '') {
            return false;
        }

        return ! empty($eligibilityCache['covered'][$kodeKaryawan]);
    }

    protected function resolveNextShiftName(
        string $requestedDate,
        ?string $kodePerusahaan,
        ?string $kodeKaryawan,
        ?array $eligibilityCache = null,
    ): ?string {
        $cached = data_get($eligibilityCache, "next_shift_name.{$kodeKaryawan}");
        if (is_string($cached) && trim($cached) !== '') {
            return trim($cached);
        }

        // Lihat catatan di resolveNextShiftStart(): kekosongan pada karyawan
        // yang tercakup cache adalah jawaban, bukan ketidaktahuan.
        if ($this->tercakupCacheKelayakan($eligibilityCache, $kodeKaryawan)) {
            return null;
        }

        if (! $kodePerusahaan || ! $kodeKaryawan) {
            return null;
        }

        try {
            $nextDate = Carbon::parse($requestedDate)->addDay()->format('Y-m-d');
            $nextContext = $this->getLatestEligibilityContext($kodePerusahaan, $kodeKaryawan, $nextDate);
            $nextStatus = strtoupper((string) ($nextContext['status_hari'] ?? ''));
            $namaShift = trim((string) ($nextContext['nama_shift'] ?? ''));

            if ($nextStatus === 'WORK' && $namaShift !== '') {
                return $namaShift;
            }
        } catch (\Throwable $e) {
            // fallback: nama shift tidak tersedia
        }

        return null;
    }

    protected function resolveNextWorkShiftStartOnly(
        string $requestedDate,
        ?string $kodePerusahaan,
        ?string $kodeKaryawan,
    ): ?Carbon {
        if (! $kodePerusahaan || ! $kodeKaryawan) {
            return null;
        }

        try {
            $nextDate = Carbon::parse($requestedDate)->addDay()->format('Y-m-d');
            $nextContext = $this->getLatestEligibilityContext($kodePerusahaan, $kodeKaryawan, $nextDate);
            $nextStatus = strtoupper((string) ($nextContext['status_hari'] ?? ''));
            $nextJamMasuk = $this->tryNormalizeTime((string) ($nextContext['jam_masuk'] ?? ''));

            if ($nextStatus === 'WORK' && $nextJamMasuk) {
                return Carbon::parse($nextDate.' '.$nextJamMasuk);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    protected function authorizeSubmit(bool $isAdminRoute): void
    {
        $enforcePermission = (bool) config('services.hcis.lembur_request.enforce_permission', false);
        if (! $enforcePermission) {
            return;
        }

        $adminPermission = (string) config(
            'services.hcis.lembur_request.permissions.admin_submit',
            'LEMBUR_SUBMIT_ADMIN',
        );
        $userPermission = (string) config('services.hcis.lembur_request.permissions.user_submit', 'LEMBUR_SUBMIT');
        $isAdminContext = $this->accessContext->isAdmin() || $isAdminRoute;

        $allowed = $isAdminContext ? $this->role->can($adminPermission) : $this->role->can($userPermission);

        if (! $allowed) {
            throw new InvalidArgumentException('Anda tidak memiliki permission functional role untuk submit lembur.');
        }
    }

    protected function buildDetailPayload(
        array $row,
        array $context,
        string $kodePerusahaan,
        string $noTransaksi,
    ): array {
        $kodeKaryawan = $this->resolveKodeKaryawan($row['id'] ?? null);
        $date = $this->resolveDate($row, $context);
        $effectiveWorkDate = $this->resolveEffectiveWorkDate($row, $context, $date);
        $type = $this->resolveType($row, $context);
        $reason = trim(str_replace(["\n", "\t"], ' ', (string) ($row['reason'] ?? '')));
        $reason = $reason !== '' ? $reason : 'Lembur';
        // Table Transaksi_Lembur_Detail.Alasan is varchar(300)
        $reason = mb_substr($reason, 0, 300);

        $rawStart = $row['startTime'] ?? ($row['masuk'] ?? ($row['jamKeluar'] ?? ($context['start_time'] ?? null)));

        $rawEnd = $row['endTime'] ?? ($row['waktu'] ?? ($context['end_time'] ?? null));

        if (! $rawEnd && $type !== 'LEMBUR_AWAL') {
            throw new InvalidArgumentException('Jam selesai lembur wajib diisi.');
        }

        $anchorShiftStart = $row['Jam_MasukShift'] ?? ($row['jamMasuk'] ?? null);
        $anchorShiftEnd = $row['Jam_KeluarShift'] ?? ($row['jamKeluar'] ?? null);
        [$startAt, $endAt] = $this->resolveDetailDateRange(
            $type,
            $date,
            $rawStart,
            $rawEnd,
            $anchorShiftStart,
            $anchorShiftEnd,
        );

        if ($endAt->lessThanOrEqualTo($startAt)) {
            if ($type === 'LEMBUR_AWAL') {
                throw new InvalidArgumentException('Jam selesai lembur awal harus lebih besar dari jam mulai.');
            }
            $endAt = $endAt->copy()->addDay();
        }

        if ($type === 'LEMBUR_AWAL' && $anchorShiftStart) {
            $shiftStartAt = $this->combineDateTime($date, $this->normalizeTime($anchorShiftStart), null);
            if ($startAt->greaterThanOrEqualTo($shiftStartAt)) {
                throw new InvalidArgumentException('Lembur awal harus dimulai sebelum jam masuk shift.');
            }
        }

        if (! $this->isWorkingDayType($type) && ! $rawStart) {
            throw new InvalidArgumentException('Jam mulai lembur wajib diisi untuk tipe lembur ini.');
        }

        $this->validateGlobalMinimumDurationOrFail($startAt, $endAt);

        return [
            'kode_karyawan' => $kodeKaryawan,
            'effective_work_date' => $effectiveWorkDate->copy()->startOfDay()->format('Y-m-d H:i:s'),
            'detail' => [
                'Kode_Perusahaan' => $kodePerusahaan,
                'No_Transaksi' => $noTransaksi,
                'Kode_Karyawan' => $kodeKaryawan,
                'Jenis' => $type,
                'Tanggal_Lembur_Dari' => $startAt->format('Y-m-d H:i:s'),
                'Tanggal_Lembur_Sampai' => $endAt->format('Y-m-d H:i:s'),
                'Alasan' => $reason,
                'Id_Shift' => $row['idShift'] ?? ($row['ID_Shift'] ?? null),
            ],
        ];
    }

    protected function resolveDetailDateRange(
        string $type,
        Carbon $date,
        $rawStart,
        $rawEnd,
        $anchorShiftStart,
        $anchorShiftEnd = null,
    ): array {
        if ($type === 'LEMBUR_AWAL') {
            return $this->resolveDetailDateRangeLemburAwal(
                $date,
                $rawStart,
                $rawEnd,
                $anchorShiftStart,
                $anchorShiftEnd,
            );
        }

        // Lembur libur harus mengikuti tanggal yang dipilih user.
        // Jangan di-anchor ke jam masuk shift karena shift malam bisa
        // menggeser datetime ke H+1 walau lembur terjadi di tanggal libur itu sendiri.
        if ($type === 'LEMBUR_LIBUR') {
            $anchorShiftStart = null;
        }

        return $this->resolveDetailDateRangeLemburUmum($date, $rawStart, $rawEnd, $anchorShiftStart, $type);
    }

    protected function resolveDetailDateRangeLemburAwal(
        Carbon $date,
        $rawStart,
        $rawEnd,
        $anchorShiftStart,
        $anchorShiftEnd = null,
    ): array {
        if (! $anchorShiftStart) {
            throw new InvalidArgumentException('Jam masuk shift tidak ditemukan untuk lembur awal.');
        }

        $normalizedShiftStart = $this->normalizeTime((string) $anchorShiftStart);
        $normalizedStart = $this->normalizeTime((string) ($rawStart ?: ($rawEnd ?: $anchorShiftStart)));

        $startAt = $this->resolveLemburAwalDateTime($date->format('Y-m-d'), $normalizedStart, $normalizedShiftStart);
        $shiftStartAt = Carbon::parse($date->format('Y-m-d').' '.$normalizedShiftStart);
        $endAt = $shiftStartAt->copy();

        if ($anchorShiftEnd) {
            $normalizedShiftEnd = $this->normalizeTime((string) $anchorShiftEnd);
            $shiftEndRaw = Carbon::parse($date->format('Y-m-d').' '.$normalizedShiftEnd);
            if ($shiftEndRaw->lessThanOrEqualTo($shiftStartAt)) {
                $shiftEndRaw->addDay();
            }
            $previousShiftEnd = $shiftEndRaw->copy()->subDay();
            if ($startAt->lessThan($previousShiftEnd)) {
                throw new InvalidArgumentException('Jam mulai yang dipilih masih dalam jam kerja hari sebelumnya.');
            }
        }

        $awalLimitHours = (float) config('services.hcis.lembur_request.batas_lembur_awal_kerja_dari_jam_kerja', 5);
        if ($awalLimitHours > 0) {
            $earliestStartAllowed = $endAt->copy()->subMinutes((int) round($awalLimitHours * 60));
            if ($startAt->lessThan($earliestStartAllowed)) {
                throw new InvalidArgumentException(
                    "Jam mulai lembur awal maksimal {$awalLimitHours} jam sebelum jam masuk shift.",
                );
            }
        }

        if ($startAt->greaterThanOrEqualTo($endAt)) {
            throw new InvalidArgumentException('Jam mulai lembur awal harus sebelum jam masuk shift.');
        }

        return [$startAt, $endAt];
    }

    protected function resolveDetailDateRangeLemburUmum(
        Carbon $date,
        $rawStart,
        $rawEnd,
        $anchorShiftStart,
        string $type,
    ): array {
        $normalizedStart = $this->normalizeTime((string) ($rawStart ?: $rawEnd));
        $normalizedEnd = $this->normalizeTime((string) $rawEnd);

        $startAt = $this->combineDateTime($date, $normalizedStart, $anchorShiftStart);
        $endAt = $this->combineDateTime($date, $normalizedEnd, $anchorShiftStart);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            if ($type === 'LEMBUR_AWAL') {
                throw new InvalidArgumentException('Jam selesai lembur awal harus lebih besar dari jam mulai.');
            }
            $endAt = $endAt->copy()->addDay();
        }

        return [$startAt, $endAt];
    }

    protected function validateGlobalMinimumDurationOrFail(Carbon $startAt, Carbon $endAt): void
    {
        $minimalDurationHours = (float) config('services.hcis.lembur_request.minimal_durasi_lembur', 1);
        if ($minimalDurationHours <= 0) {
            return;
        }

        $durationHours = $startAt->diffInMinutes($endAt) / 60;
        if ($durationHours < $minimalDurationHours) {
            throw new InvalidArgumentException("Durasi lembur minimal {$minimalDurationHours} jam.");
        }
    }

    protected function resolveEffectiveWorkDate(array $row, array $context, Carbon $defaultDate): Carbon
    {
        $effectiveDateSource =
            $row['effectiveWorkDate'] ?? ($row['effective_work_date'] ?? ($context['effectiveWorkDate'] ?? null));

        if ($effectiveDateSource) {
            return Carbon::parse($effectiveDateSource)->startOfDay();
        }

        return $defaultDate->copy()->startOfDay();
    }

    protected function resolveKodeKaryawan($rawId): string
    {
        if (! $rawId) {
            throw new InvalidArgumentException('Data karyawan tidak valid.');
        }

        $candidate = (string) $rawId;
        try {
            $candidate = Crypt::decryptString((string) $rawId);
        } catch (\Throwable $e) {
            // fallback plain kode
        }

        $exists = Karyawan::where('Kode_Karyawan', $candidate)->exists();
        if (! $exists) {
            throw new InvalidArgumentException('Karyawan tidak ditemukan.');
        }

        return $candidate;
    }

    protected function resolveUpdatedByUserWeb(): string
    {
        $user = Auth::user();
        $fromKaryawan = $user?->karyawan?->UserID_Web;
        $fromAuth = $user?->Id_Users ?? $user?->id;

        $resolved = $fromKaryawan ?? $fromAuth;

        return (string) ($resolved ?? '');
    }

    protected function sendOvertimeAssignmentWhatsapp(string $noTransaksi, array $payload): void
    {
        try {
            $kodeKaryawan = (string) ($payload['kode_karyawan'] ?? '');
            if ($kodeKaryawan === '') {
                return;
            }

            $karyawan = DB::table('Karyawan')
                ->select('Kode_Karyawan', 'Nama', 'HP')
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->first();

            if (! $karyawan) {
                return;
            }

            $hp = trim((string) ($karyawan->HP ?? ''));
            if ($hp === '' || $hp === '-') {
                return;
            }

            $startAt = Carbon::parse((string) ($payload['detail']['Tanggal_Lembur_Dari'] ?? ''));
            $endAt = Carbon::parse((string) ($payload['detail']['Tanggal_Lembur_Sampai'] ?? ''));
            $rangeText = $this->formatLemburTanggalWaktu($startAt, $endAt);

            $pesan = $this->whatsappMessageBuilder->notifLemburNotification(
                $hp,
                (string) ($karyawan->Nama ?? '-'),
                $noTransaksi,
                $rangeText,
                (string) ($payload['detail']['Alasan'] ?? '-'),
            );

            $this->whatsappLogHelper->sendAndLog(
                (string) $karyawan->Kode_Karyawan,
                (string) ($karyawan->Nama ?? '-'),
                $hp,
                $noTransaksi,
                $pesan,
            );
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->warning('Gagal mengirim WA penugasan lembur: '.$e->getMessage(), [
                'no_transaksi' => $noTransaksi,
                'kode_karyawan' => $payload['kode_karyawan'] ?? null,
            ]);
        }
    }

    protected function formatLemburTanggalWaktu(Carbon $startAt, Carbon $endAt): string
    {
        $start = $startAt->copy()->locale('id');
        $end = $endAt->copy()->locale('id');

        if ($start->isSameDay($end)) {
            return $start->translatedFormat('d M Y').' '.$start->format('H:i').' - '.$end->format('H:i');
        }

        return $start->translatedFormat('d M Y H:i').' - '.$end->translatedFormat('d M Y H:i');
    }

    protected function resolveDate(array $row, array $context): Carbon
    {
        $dateSource = $row['tanggal'] ?? ($context['date'] ?? null);
        if (! $dateSource) {
            throw new InvalidArgumentException('Tanggal lembur wajib diisi.');
        }

        return Carbon::parse($dateSource)->startOfDay();
    }

    protected function resolveType(array $row, array $context): string
    {
        $rawType = strtoupper(trim((string) ($row['type'] ?? ($context['type'] ?? 'LEMBUR_HK'))));
        $typeMap = [
            'LEMBUR_HK' => 'LEMBUR',
            'LEMBUR_HARI_KERJA' => 'LEMBUR',
            'LEMBUR' => 'LEMBUR',
            'LEMBUR_AWAL' => 'LEMBUR_AWAL',
            'LEMBUR_MINGGU' => 'LEMBUR_LIBUR',
            'LEMBUR_LIBUR' => 'LEMBUR_LIBUR',
        ];

        return $typeMap[$rawType] ?? 'LEMBUR';
    }

    protected function isWorkingDayType(string $type): bool
    {
        return in_array($type, ['LEMBUR'], true);
    }

    protected function normalizeTime(string $time): string
    {
        $normalized = trim($time);
        if (preg_match('/^(\d{2}:\d{2})$/', $normalized, $matches)) {
            return $matches[1].':00';
        }
        if (preg_match('/^(\d{2}:\d{2}):\d{2}/', $normalized, $matches)) {
            // Selalu reset detik ke 00 — user tidak memilih detik di time picker
            return $matches[1].':00';
        }
        throw new InvalidArgumentException('Format jam tidak valid.');
    }

    protected function tryNormalizeTime(?string $time): ?string
    {
        try {
            if ($time === null) {
                return null;
            }

            return $this->normalizeTime((string) $time);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function combineDateTime(Carbon $date, string $time, ?string $shiftAnchor = null): Carbon
    {
        $base = Carbon::parse($date->format('Y-m-d').' '.$time);
        if (! $shiftAnchor) {
            return $base;
        }

        $anchor = Carbon::parse($date->format('Y-m-d').' '.$this->normalizeTime((string) $shiftAnchor));
        if ($base->lessThanOrEqualTo($anchor)) {
            $base->addDay();
        }

        return $base;
    }

    protected function resolveLemburAwalDateTime(string $workDate, string $requestTime, string $shiftStartTime): Carbon
    {
        $shiftStartAt = Carbon::parse($workDate.' '.$shiftStartTime);
        $requestAt = Carbon::parse($workDate.' '.$requestTime);

        // Jika jam request secara clock >= jam masuk shift, treat as H-1.
        if ($requestAt->greaterThanOrEqualTo($shiftStartAt)) {
            $requestAt->subDay();
        }

        return $requestAt;
    }

    protected function generateNoTransaksi(string $kodePerusahaan): string
    {
        $now = $this->currentNow();
        $prefix = 'LM'.$now->format('m-y').'-';
        $spName = (string) config('services.hcis.lembur_request.transaction_sn_sp', '');

        if ($spName !== '' && preg_match('/^[A-Za-z0-9_\.\[\]]+$/', $spName)) {
            try {
                $row = DB::selectOne("EXEC {$spName} ?, ?", [$kodePerusahaan, $now->format('Y-m-d')]);
                if ($row) {
                    $candidate = (string) collect((array) $row)->first();
                    if ($candidate !== '') {
                        return $candidate;
                    }
                }
            } catch (\Throwable $e) {
                Log::channel('lemburLog')->warning(
                    'SP nomor transaksi lembur gagal, fallback ke query manual: '.$e->getMessage(),
                );
            }
        }

        // Fallback (SP tak dikonfigurasi / gagal): counter atomik menggantikan scan
        // manual non-sargable + rawan duplikat.
        return app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
            $prefix,
            function () use ($prefix) {
                $last = DB::table('Transaksi_Lembur')
                    ->where('No_Transaksi', 'like', $prefix.'%')
                    ->orderBy('No_Transaksi', 'desc')
                    ->value('No_Transaksi');
                if (! $last) {
                    return 0;
                }
                $parts = explode('-', (string) $last);

                return (int) end($parts);
            },
            4,
        );
    }

    protected function currentNow(): Carbon
    {
        try {
            $now = FormatTanggalHelper::getCurrentTime('LemburRequest');
            // dd($now);
            if ($now instanceof Carbon) {
                return $now;
            }

            return Carbon::parse((string) $now);
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->warning(
                'Gagal ambil waktu dari FormatTanggalHelper::getCurrentTime(LemburRequest), fallback ke server time: '.
                    $e->getMessage(),
            );

            return Carbon::now((string) config('app.timezone', 'Asia/Jakarta'));
        }
    }
}
