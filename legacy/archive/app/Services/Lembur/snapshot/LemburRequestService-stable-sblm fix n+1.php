<?php

namespace App\Services\Lembur;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\WhatsappLogHelper;
use App\Models\Karyawan;
use App\Models\Lembur;
use App\Models\LemburDet;
use App\Models\LemburDetail;
use App\Services\InternalConfig\AccessContext;
use App\Services\InternalConfig\FunctionalRole;
use App\Services\Notification\WhatsappMessageBuilder;
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

    public function __construct(
        FunctionalRole $role,
        AccessContext $accessContext,
        WhatsappLogHelper $whatsappLogHelper,
        WhatsappMessageBuilder $whatsappMessageBuilder
    ) {
        $this->role = $role;
        $this->accessContext = $accessContext;
        $this->whatsappLogHelper = $whatsappLogHelper;
        $this->whatsappMessageBuilder = $whatsappMessageBuilder;
    }

    public function submitFromRequest(Request $request, bool $isAdminRoute = false): array
    {
        $params = (array) $request->input('params', []);
        $rows = (array) ($params['dataUsers'] ?? []);

        return $this->submit($rows, [
            'kode_perusahaan' => $params['Kode_Perusahaan'] ?? '001',
            'date' => $params['date'] ?? null,
            'type' => $params['type'] ?? null,
            'start_time' => $params['startTime'] ?? null,
            'end_time' => $params['endTime'] ?? ($params['time'] ?? null),
        ], $isAdminRoute);
    }

    public function validateDraftFromRequest(Request $request, bool $isAdminRoute = false): array
    {
        $params = (array) $request->input('params', []);
        $rows = (array) ($params['dataUsers'] ?? []);

        return $this->validateDraft($rows, [
            'kode_perusahaan' => $params['Kode_Perusahaan'] ?? '001',
            'date' => $params['date'] ?? null,
            'type' => $params['type'] ?? null,
            'start_time' => $params['startTime'] ?? null,
            'end_time' => $params['endTime'] ?? ($params['time'] ?? null),
        ], $isAdminRoute);
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
            $validation = $this->validateSubmissionRow($row, $context, $kodePerusahaan);
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

        return DB::transaction(function () use ($rows, $context, $isAdminRoute) {
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
                $validation = $this->validateSubmissionRow($row, $context, $kodePerusahaan);
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

                $this->sendOvertimeAssignmentWhatsapp($noTransaksi, $payload);
                $inserted++;
            }

            return [
                'no_transaksi' => $noTransaksi,
                'inserted' => $inserted,
                'has_conflict' => count($conflicts) > 0,
                'conflicts' => $conflicts,
            ];
        });
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
        $requestedStart = $this->normalizeTime((string) ($request->input('startTime') ?? $request->input('time') ?? $request->input('endTime') ?? '00:00:00'));
        $requestedEnd = $this->normalizeTime((string) ($request->input('endTime') ?? $request->input('time') ?? $requestedStart));
        $jam = $this->normalizeTime((string) ($request->input('time') ?? $request->input('endTime') ?? '00:00:00'));
        $dateTime = Carbon::parse($tanggal.' '.$jam)->format('Y-m-d H:i:s');

        $leaderKode = Auth::user()?->karyawan?->Kode_Karyawan;
        $isAdminContext = $includeAll || $this->accessContext->isAdmin();
        if (! $leaderKode && ! $isAdminContext) {
            throw new InvalidArgumentException('User tidak memiliki relasi karyawan.');
        }

        $whitelistLevel = implode(',', config('services.hcis.overtime_level_list', [1, 2, 21]));
        $teamFilter = $isAdminContext ? '' : 'AND h.Kode_Karyawan_Team = ?';
        $excludeFilter = $isAdminContext ? '' : 'AND e.Kode_Karyawan <> ?';
        $adminScopeFilter = '';
        $adminScopeBindings = [];

        if ($isAdminContext) {
            $scopedEmployeeCodes = $this->resolveScopedKaryawanByFunctionalRole($kodePerusahaan);
            if (count($scopedEmployeeCodes) === 0) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($scopedEmployeeCodes), '?'));
            $adminScopeFilter = "AND e.Kode_Karyawan IN ({$placeholders})";
            $adminScopeBindings = $scopedEmployeeCodes;
        }

        $query = "
            SELECT
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
                          AND CAST(d.Tanggal_Lembur AS DATE) = p.RefDate
                    ) THEN 'TRUE' ELSE 'FALSE'
                END AS lemburPernah,

                e.Kode_Karyawan AS userId,
                e.UserID_Absen,
                f.nama_divisi AS Divisi,
                f.nama_sub_divisi AS Sub_Divisi,
                e.Nama AS name,
                sh.Jam_Masuk AS Jam_Masuk,
                sh.Jam_Keluar AS Jam_Keluar,
                sh.ID_Shift AS ID_Shift,
                sh.Nama_Shift AS Nama_Shift,
                sh.ECOFX AS Jam_selesai_absen,
                sh.JENIS_SHIFT_DIPILIH AS Jenis_Shift,
                COALESCE(
                    sh.StatusHari,
                    dbo.N_HRIS_FN_GetStatusHari(p.KodePerusahaan, e.Kode_Karyawan, p.RefDate)
                ) AS StatusHari

            FROM Karyawan e
            CROSS JOIN (
                SELECT
                    CAST(? AS DATETIME) AS RefDateTime,
                    CAST(? AS DATE) AS RefDate,
                    CAST(? AS VARCHAR(3)) AS KodePerusahaan,
                    CAST(? AS VARCHAR(100)) AS LevelWhitelist
            ) p
            LEFT JOIN HRIS_Karyawan_Team h ON h.Kode_Karyawan = e.Kode_Karyawan
            INNER JOIN View_Divisi_Sub_Divisi f ON e.ID_Divisi_Sub_Divisi = f.ID_DIVISI_SUB_DIVISI
            INNER JOIN View_Golongan_Sub_Golongan_Level_Jabatan g ON e.ID_Level_Jabatan = g.ID_Level_Jabatan

            OUTER APPLY (
                SELECT TOP 1 *
                FROM dbo.N_HRIS_FN_GetStatusHariRange(p.KodePerusahaan, e.Kode_Karyawan, p.RefDate, p.RefDate)
            ) sh

            WHERE e.Kode_Perusahaan = p.KodePerusahaan
              AND e.Aktif = 'Y'
              AND g.ID_Level IN (SELECT CAST(value AS INT) FROM STRING_SPLIT(p.LevelWhitelist, ','))
              {$teamFilter}
              {$excludeFilter}
              {$adminScopeFilter}
            ORDER BY JamComparison DESC, e.Nama ASC
        ";

        $bindings = [
            $dateTime,
            $tanggal,
            $kodePerusahaan,
            $whitelistLevel,
        ];

        if (! $isAdminContext) {
            $bindings[] = $leaderKode;
            $bindings[] = $leaderKode;
        }
        foreach ($adminScopeBindings as $kode) {
            $bindings[] = $kode;
        }

        $result = DB::select($query, $bindings);

        return collect($result)->map(function ($item) use ($requestedType, $requestedStart, $requestedEnd, $tanggal, $kodePerusahaan) {
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
                (string) ($item->userId ?? '')
            );

            $item->isActive = $eligibility['is_active'];
            $item->inactiveReason = $eligibility['reason'];
            $item->eligibility_checked_at = $this->currentNow()->format('Y-m-d H:i:s');

            if (isset($item->userId)) {
                $item->userId = Crypt::encryptString($item->userId);
            }

            return $item;
        })->values()->all();
    }

    protected function resolveScopedKaryawanByFunctionalRole(string $kodePerusahaan): array
    {
        $query = DB::table('Karyawan')
            ->select('Kode_Karyawan')
            ->where('Kode_Perusahaan', $kodePerusahaan)
            ->where('Aktif', 'Y');

        $this->role->applyScope($query);

        return $query
            ->pluck('Kode_Karyawan')
            ->map(fn ($v) => (string) $v)
            ->filter(fn ($v) => $v !== '')
            ->values()
            ->all();
    }

    public function getHistory(Request $request, bool $isAdminRoute = false): LengthAwarePaginator
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggalFilter' => ['nullable', 'string', 'max:100'],
            'tanggalDari' => ['nullable', 'date'],
            'tanggalSampai' => ['nullable', 'date'],
            'jenisFilter' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = strip_tags((string) ($validated['search'] ?? ''));
        $tanggalFilterRaw = $validated['tanggalFilter'] ?? null;
        $tanggalDariRaw = $validated['tanggalDari'] ?? null;
        $tanggalSampaiRaw = $validated['tanggalSampai'] ?? null;
        $jenisFilterRaw = strtoupper(trim((string) ($validated['jenisFilter'] ?? '')));
        $page = (int) ($validated['page'] ?? 1);
        $perPage = 10;

        $canAccessAdminScope = $this->accessContext->isAdmin() || $isAdminRoute;
        $scopeIsAdmin = $isAdminRoute;
        $kodeKaryawan = Auth::user()?->karyawan?->Kode_Karyawan;

        if (! $canAccessAdminScope && ! $kodeKaryawan) {
            throw new InvalidArgumentException('User tidak terhubung ke data karyawan.');
        }

        $query = DB::table('Transaksi_Lembur as h')
            ->selectRaw("
                h.No_Transaksi,
                h.Kode_Perusahaan,
                h.Tanggal,
                h.Jam,
                h.UserID as Nama,
                h.Updated_By,
                h.Flag_Admin,
                h.Flag_Selesai,
                (
                    SELECT TOP 1 d4.Jenis
                    FROM Transaksi_Lembur_Detail d4
                    WHERE d4.No_Transaksi = h.No_Transaksi
                    ORDER BY d4.Urut_Oto ASC
                ) AS Jenis_Lembur,
                (
                    SELECT STRING_AGG(k2.Nama, ', ')
                    FROM Transaksi_Lembur_Detail d2
                    JOIN Karyawan k2 ON k2.Kode_Karyawan = d2.Kode_Karyawan
                    WHERE d2.No_Transaksi = h.No_Transaksi
                ) AS Assignment,
                (
                    SELECT TOP 1 d3.Tanggal_Lembur_Sampai
                    FROM Transaksi_Lembur_Detail d3
                    WHERE d3.No_Transaksi = h.No_Transaksi
                    ORDER BY d3.Tanggal_Lembur_Sampai DESC
                ) AS Tanggal_Assign,
                (
                    SELECT STRING_AGG(CONVERT(VARCHAR(10), CAST(det2.Tanggal_Lembur AS DATE), 120), ', ')
                    FROM Transaksi_Lembur_Det det2
                    WHERE det2.No_Transaksi = h.No_Transaksi
                ) AS Effective_Work_Dates
            ")
            ->whereNull('h.Status');

        if ($scopeIsAdmin) {
            $query->where('h.Flag_Admin', 'Y');
        } else {
            $query->where(function ($q) {
                $q->where('h.Flag_Admin', 'T')
                    ->orWhereNull('h.Flag_Admin');
            });
            $query->where('h.UserID', $kodeKaryawan);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('h.No_Transaksi', 'like', '%'.$search.'%')
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->selectRaw('1')
                            ->from('Transaksi_Lembur_Detail as d')
                            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'd.Kode_Karyawan')
                            ->whereColumn('d.No_Transaksi', 'h.No_Transaksi')
                            ->where('k.Nama', 'like', '%'.$search.'%');
                    });
            });
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

                $row->Effective_Work_Dates = ! empty($dates)
                    ? FormatTanggalHelper::formatTanggalMultiple($dates)
                    : '-';
            } else {
                $row->Effective_Work_Dates = '-';
            }

            return $row;
        });

        return $pager;
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
                $join->on('d.Kode_Perusahaan', '=', 'h.Kode_Perusahaan')
                    ->on('d.No_Transaksi', '=', 'h.No_Transaksi');
            })
            ->join('Transaksi_Lembur_Det as det', function ($join) {
                $join->on('det.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')
                    ->on('det.No_Transaksi', '=', 'd.No_Transaksi')
                    ->on('det.Kode_Karyawan', '=', 'd.Kode_Karyawan')
                    ->on('det.Urut_Detail', '=', 'd.Urut_Oto');
            })
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'k.ID_Divisi_Sub_Divisi', '=', 'vds.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('HRIS_Shift_Kerja as sk_req', 'sk_req.ID_Shift', '=', 'd.Id_Shift')
            ->leftJoin('HRIS_Shift_Kerja_Detail as skd_req', function ($join) {
                $join->on('skd_req.ID_Shift', '=', 'd.Id_Shift')
                    ->whereRaw('skd_req.Hari = DATEPART(WEEKDAY, det.Tanggal_Lembur)');
            })
            ->leftJoin('HRIS_Waktu_Kerja as wk_req', 'wk_req.ID_Waktu_Kerja', '=', 'skd_req.ID_Waktu_Kerja')
            ->where('h.No_Transaksi', $noTransaksi)
            ->whereNull('h.Status');

        if ($scopeIsAdmin) {
            $query->where('h.Flag_Admin', 'Y');
        } else {
            $query->where(function ($q) {
                $q->where('h.Flag_Admin', 'T')
                    ->orWhereNull('h.Flag_Admin');
            });
            $query->where('h.UserID', $kodeKaryawan);
        }

        $rows = $query->orderByDesc('d.Tanggal_Lembur_Dari')
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

        $userIdList = $rows->pluck('user_id_absen')
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->implode(',');

        $effectiveDates = $rows->pluck('effective_work_date')
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
            $queryAbsen = "Exec HRIS_SP_GET_ABSEN_NEW_V3 '{$kodePerusahaan}', ?, ?, ?, '', ''";
            $absenRows = collect(DB::select($queryAbsen, [$minDate, $maxDate, $userIdList]));

            foreach ($absenRows as $absen) {
                $uid = (string) (
                    $absen->UserID
                    ?? $absen->UserId
                    ?? $absen->Userid
                    ?? ''
                );
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
        $pairs = $rows->map(function ($row) {
            $kode = trim((string) ($row->Kode_Karyawan ?? ''));
            $tanggal = $row->effective_work_date
                ? Carbon::parse((string) $row->effective_work_date)->format('Y-m-d')
                : null;

            return $kode !== '' && $tanggal ? ($kode.'|'.$tanggal) : null;
        })->filter()->unique()->values();

        foreach ($pairs as $pairKey) {
            [$kodePair, $tanggalPair] = explode('|', $pairKey, 2);
            $shiftRow = collect(DB::select(
                'SELECT TOP 1 Nama_Shift, Jam_Masuk, Jam_Keluar, StatusHari
                 FROM dbo.N_HRIS_FN_GetStatusHariRange(?, ?, ?, ?)',
                [$kodePerusahaan, $kodePair, $tanggalPair, $tanggalPair]
            ))->first();

            if ($shiftRow) {
                $shiftFallbackIndex[$pairKey] = [
                    'nama_shift' => $shiftRow->Nama_Shift ?? null,
                    'jam_masuk' => $shiftRow->Jam_Masuk ?? null,
                    'jam_keluar' => $shiftRow->Jam_Keluar ?? null,
                    'status_hari' => $shiftRow->StatusHari ?? null,
                ];
            }
        }

        return $rows->map(function ($row) use ($absenIndexByUserId, $absenIndexByKaryawan, $shiftFallbackIndex) {
            $uid = (string) ($row->user_id_absen ?? '');
            $uid = trim($uid);
            if (preg_match('/^\d+$/', $uid)) {
                $uid = (string) intval($uid);
            }
            $kodeKaryawan = trim((string) ($row->Kode_Karyawan ?? ''));
            $tanggal = $row->effective_work_date
                ? Carbon::parse((string) $row->effective_work_date)->format('Y-m-d')
                : null;
            $keyByUser = $uid !== '' && $tanggal ? ($uid.'|'.$tanggal) : null;
            $keyByKaryawan = $kodeKaryawan !== '' && $tanggal ? ($kodeKaryawan.'|'.$tanggal) : null;

            $absen = ($keyByUser && isset($absenIndexByUserId[$keyByUser]))
                ? $absenIndexByUserId[$keyByUser]
                : (($keyByKaryawan && isset($absenIndexByKaryawan[$keyByKaryawan]))
                    ? $absenIndexByKaryawan[$keyByKaryawan]
                : [
                    'check_in' => null,
                    'check_out' => null,
                    'nama_shift' => null,
                    'jam_masuk' => null,
                    'jam_keluar' => null,
                ]);

            $fallbackKey = $kodeKaryawan !== '' && $tanggal ? ($kodeKaryawan.'|'.$tanggal) : null;
            $fallbackShift = ($fallbackKey && isset($shiftFallbackIndex[$fallbackKey]))
                ? $shiftFallbackIndex[$fallbackKey]
                : ['nama_shift' => null, 'jam_masuk' => null, 'jam_keluar' => null, 'status_hari' => null];

            $row->Has_CheckIn = ! empty($absen['check_in']) ? 'Y' : 'T';
            $row->Has_CheckOut = ! empty($absen['check_out']) ? 'Y' : 'T';
            $row->Nama_Shift = $absen['nama_shift']
                ?: ($row->req_nama_shift ?? null)
                ?: $fallbackShift['nama_shift'];
            $row->Jam_Masuk = $absen['jam_masuk']
                ?: ($row->req_jam_masuk ?? null)
                ?: $fallbackShift['jam_masuk'];
            $row->Jam_Keluar = $absen['jam_keluar']
                ?: ($row->req_jam_keluar ?? null)
                ?: $fallbackShift['jam_keluar'];
            $row->Status_Hari = $fallbackShift['status_hari'];

            unset($row->user_id_absen);
            unset($row->Kode_Perusahaan);
            unset($row->Id_Shift);
            unset($row->req_nama_shift);
            unset($row->req_jam_masuk);
            unset($row->req_jam_keluar);

            return (array) $row;
        })->all();
    }

    protected function validateSubmissionRow(array $row, array $context, string $kodePerusahaan): array
    {
        try {
            $kodeKaryawan = $this->resolveKodeKaryawan($row['id'] ?? null);
            $effectiveDate = $this->resolveEffectiveWorkDate($row, $context, $this->resolveDate($row, $context));
            $requestedType = $this->normalizeRequestType((string) ($row['type'] ?? $context['type'] ?? 'LEMBUR_HK'));
            $requestedStart = $this->normalizeTime((string) ($row['startTime'] ?? $context['start_time'] ?? $context['end_time'] ?? '00:00:00'));
            $requestedEnd = $this->normalizeTime((string) ($row['endTime'] ?? $context['end_time'] ?? $requestedStart));

            $latest = $this->getLatestEligibilityContext($kodePerusahaan, $kodeKaryawan, $effectiveDate->format('Y-m-d'));

            $snapshotShift = isset($row['snapshot_shift_id']) ? (string) $row['snapshot_shift_id'] : null;
            $snapshotStatus = isset($row['snapshot_status_hari']) ? (string) $row['snapshot_status_hari'] : null;
            $snapshotMasuk = isset($row['snapshot_jam_masuk']) ? (string) $row['snapshot_jam_masuk'] : null;
            $snapshotKeluar = isset($row['snapshot_jam_keluar']) ? (string) $row['snapshot_jam_keluar'] : null;

            if ($snapshotShift !== null && $snapshotShift !== '' && $snapshotShift !== (string) ($latest['id_shift'] ?? '')) {
                return ['is_valid' => false, 'code' => 'SHIFT_CHANGED', 'reason' => 'Shift karyawan berubah. Harap refresh data.'];
            }
            if ($snapshotStatus !== null && $snapshotStatus !== '' && strtoupper($snapshotStatus) !== strtoupper((string) ($latest['status_hari'] ?? ''))) {
                return ['is_valid' => false, 'code' => 'STATUS_CHANGED', 'reason' => 'Status hari kerja berubah. Harap refresh data.'];
            }
            if ($snapshotMasuk !== null && $snapshotMasuk !== '' && $snapshotMasuk !== (string) ($latest['jam_masuk'] ?? '')) {
                return ['is_valid' => false, 'code' => 'SHIFT_TIME_CHANGED', 'reason' => 'Jam masuk/keluar shift berubah. Harap refresh data.'];
            }
            if ($snapshotKeluar !== null && $snapshotKeluar !== '' && $snapshotKeluar !== (string) ($latest['jam_keluar'] ?? '')) {
                return ['is_valid' => false, 'code' => 'SHIFT_TIME_CHANGED', 'reason' => 'Jam masuk/keluar shift berubah. Harap refresh data.'];
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
                $kodeKaryawan
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

    protected function getLatestEligibilityContext(string $kodePerusahaan, string $kodeKaryawan, string $effectiveDate): array
    {
        $sql = "
            SELECT TOP 1
                COALESCE(sh.StatusHari, dbo.N_HRIS_FN_GetStatusHari(?, ?, ?)) AS status_hari,
                sh.Jam_Masuk AS jam_masuk,
                sh.Jam_Keluar AS jam_keluar,
                sh.ID_Shift AS id_shift,
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
                          AND CAST(d.Tanggal_Lembur AS DATE) = ?
                    ) THEN 'TRUE' ELSE 'FALSE'
                END AS lembur_pernah
            FROM (SELECT ? AS Kode_Karyawan) e
            OUTER APPLY (
                SELECT TOP 1 *
                FROM dbo.N_HRIS_FN_GetStatusHariRange(?, e.Kode_Karyawan, ?, ?)
            ) sh
        ";

        $bindings = [
            $kodePerusahaan,
            $kodeKaryawan,
            $effectiveDate,
            $kodePerusahaan,
            $kodeKaryawan,
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
        ?string $kodeKaryawan = null
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

        if ($lemburPernah === 'TRUE') {
            return [
                'is_active' => false,
                'reason' => 'Sudah ditugaskan lembur di tanggal ini.',
            ];
        }

        // Guard global semua tipe:
        // Cek overlap interval real terhadap pengajuan lembur aktif lain,
        // termasuk kasus lembur hari sebelumnya yang spill ke hari ini.
        if (
            $kodePerusahaan &&
            $kodeKaryawan &&
            $this->hasOverlappingExistingOvertime($kodePerusahaan, $kodeKaryawan, $requestStartAtGlobal, $requestEndAtGlobal)
        ) {
            return [
                'is_active' => false,
                'reason' => 'Range lembur bertabrakan dengan lembur aktif sebelumnya/berikutnya.',
            ];
        }

        $status = strtoupper((string) $statusHari);
        $isOffDay = str_starts_with($status, 'OFF');

        if ($requestedType === 'LEMBUR_LIBUR') {
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
                $requestStartAt = $this->resolveLemburAwalDateTime($requestedDate, $requestedStart, $normalizedJamMasuk);
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
                $kodeKaryawan
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
                $kodeKaryawan
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

        return ($hour * 60) + $minute;
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
        ?string $kodeKaryawan
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
        $hourLimit = (float) config('services.hcis.lembur_request.batas_jam_request_lembur', 4);
        $hourLimit = $hourLimit > 0 ? $hourLimit : 4;
        $windowEnd = $shiftEnd->copy()->addMinutes((int) round($hourLimit * 60));
        if ($this->currentNow()->greaterThan($windowEnd)) {
            return [
                'is_active' => false,
                'reason' => "Window pengajuan lembur sudah/tidak berlaku (hanya dari jam pulang sampai +{$hourLimit} jam).",
            ];
        }

        if (
            $kodePerusahaan &&
            $kodeKaryawan &&
            $this->hasOverlappingExistingOvertime($kodePerusahaan, $kodeKaryawan, $requestStartAt, $requestEndAt)
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
        ?string $kodeKaryawan
    ): array {
        $hourLimit = (float) config('services.hcis.lembur_request.batas_jam_request_lembur', 4);
        $hourLimit = $hourLimit > 0 ? $hourLimit : 4;
        $windowEnd = $shiftEnd->copy()->addMinutes((int) round($hourLimit * 60));
        $requestedDay = Carbon::parse($requestedDate)->startOfDay();
        $today = $this->currentNow()->copy()->startOfDay();
        $isBackdate = $requestedDay->lessThan($today);

        // Untuk backdate, tampilkan reason window lebih dulu agar tidak ambigu.
        if ($isBackdate && $this->currentNow()->greaterThan($windowEnd)) {
            return [
                'is_active' => false,
                'reason' => "Window pengajuan lembur sudah/tidak berlaku (hanya dari jam pulang sampai +{$hourLimit} jam).",
            ];
        }

        // PRIORITAS REASON LEMBUR_HK:
        // 1) Bentrok jam kerja shift
        // 2) Bentrok lembur aktif existing
        // 3) Window pengajuan lewat
        // 4) Durasi melebihi batas
        // 5) Terlalu dekat jam kerja berikutnya
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
            $this->hasOverlappingExistingOvertime($kodePerusahaan, $kodeKaryawan, $requestStartAt, $requestEndAt)
        ) {
            return [
                'is_active' => false,
                'reason' => 'Range lembur bertabrakan dengan pengajuan lembur aktif lainnya.',
            ];
        }

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

        $closenessHours = (float) config('services.hcis.lembur_request.batas_kedekatan_akhir_lembur_dengan_jam_kerja', 18);
        $nextShiftStart = $this->resolveNextShiftStart(
            $requestedDate,
            $shiftStart,
            $kodePerusahaan,
            $kodeKaryawan
        );
        $latestEndAllowed = $nextShiftStart->copy()->subMinutes((int) round($closenessHours * 60));
        if ($requestEndAt->greaterThan($latestEndAllowed)) {
            return [
                'is_active' => false,
                'reason' => "Jam akhir lembur terlalu dekat dengan jam kerja berikutnya (minimal jeda {$closenessHours} jam).",
            ];
        }

        // Untuk hari aktif/non-backdate, window dipakai sebagai final gate.
        if ($this->currentNow()->greaterThan($windowEnd)) {
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
        Carbon $requestEndAt
    ): bool {
        return DB::table('Transaksi_Lembur_Detail as d')
            ->join('Transaksi_Lembur as h', function ($join) {
                $join->on('h.Kode_Perusahaan', '=', 'd.Kode_Perusahaan')
                    ->on('h.No_Transaksi', '=', 'd.No_Transaksi');
            })
            ->where('d.Kode_Perusahaan', $kodePerusahaan)
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('h.Status')
            ->where('d.Tanggal_Lembur_Dari', '<', $requestEndAt->format('Y-m-d H:i:s'))
            ->where('d.Tanggal_Lembur_Sampai', '>', $requestStartAt->format('Y-m-d H:i:s'))
            ->exists();
    }

    protected function resolveNextShiftStart(
        string $requestedDate,
        Carbon $fallbackShiftStart,
        ?string $kodePerusahaan,
        ?string $kodeKaryawan
    ): Carbon {
        $default = $fallbackShiftStart->copy()->addDay();

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

    protected function resolveNextWorkShiftStartOnly(
        string $requestedDate,
        ?string $kodePerusahaan,
        ?string $kodeKaryawan
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

        $adminPermission = (string) config('services.hcis.lembur_request.permissions.admin_submit', 'LEMBUR_SUBMIT_ADMIN');
        $userPermission = (string) config('services.hcis.lembur_request.permissions.user_submit', 'LEMBUR_SUBMIT');
        $isAdminContext = $this->accessContext->isAdmin() || $isAdminRoute;

        $allowed = $isAdminContext
            ? $this->role->can($adminPermission)
            : $this->role->can($userPermission);

        if (! $allowed) {
            throw new InvalidArgumentException('Anda tidak memiliki permission functional role untuk submit lembur.');
        }
    }

    protected function buildDetailPayload(array $row, array $context, string $kodePerusahaan, string $noTransaksi): array
    {
        $kodeKaryawan = $this->resolveKodeKaryawan($row['id'] ?? null);
        $date = $this->resolveDate($row, $context);
        $effectiveWorkDate = $this->resolveEffectiveWorkDate($row, $context, $date);
        $type = $this->resolveType($row, $context);
        $reason = trim(str_replace(["\n", "\t"], ' ', (string) ($row['reason'] ?? '')));
        $reason = $reason !== '' ? $reason : 'Lembur';
        // Table Transaksi_Lembur_Detail.Alasan is varchar(50)
        $reason = mb_substr($reason, 0, 50);

        $rawStart = $row['startTime']
            ?? $row['masuk']
            ?? $row['jamKeluar']
            ?? $context['start_time']
            ?? null;

        $rawEnd = $row['endTime']
            ?? $row['waktu']
            ?? $context['end_time']
            ?? null;

        if (! $rawEnd && $type !== 'LEMBUR_AWAL') {
            throw new InvalidArgumentException('Jam selesai lembur wajib diisi.');
        }

        $anchorShiftStart = $row['Jam_MasukShift'] ?? $row['jamMasuk'] ?? null;
        $anchorShiftEnd = $row['Jam_KeluarShift'] ?? $row['jamKeluar'] ?? null;
        [$startAt, $endAt] = $this->resolveDetailDateRange(
            $type,
            $date,
            $rawStart,
            $rawEnd,
            $anchorShiftStart,
            $anchorShiftEnd
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
                'Id_Shift' => $row['idShift'] ?? $row['ID_Shift'] ?? null,
            ],
        ];
    }

    protected function resolveDetailDateRange(
        string $type,
        Carbon $date,
        $rawStart,
        $rawEnd,
        $anchorShiftStart,
        $anchorShiftEnd = null
    ): array {
        if ($type === 'LEMBUR_AWAL') {
            return $this->resolveDetailDateRangeLemburAwal($date, $rawStart, $rawEnd, $anchorShiftStart, $anchorShiftEnd);
        }

        return $this->resolveDetailDateRangeLemburUmum($date, $rawStart, $rawEnd, $anchorShiftStart, $type);
    }

    protected function resolveDetailDateRangeLemburAwal(
        Carbon $date,
        $rawStart,
        $rawEnd,
        $anchorShiftStart,
        $anchorShiftEnd = null
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
                throw new InvalidArgumentException("Jam mulai lembur awal maksimal {$awalLimitHours} jam sebelum jam masuk shift.");
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
        string $type
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
        $effectiveDateSource = $row['effectiveWorkDate']
            ?? $row['effective_work_date']
            ?? $context['effectiveWorkDate']
            ?? null;

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
                (string) ($payload['detail']['Alasan'] ?? '-')
            );

            $this->whatsappLogHelper->sendAndLog(
                (string) $karyawan->Kode_Karyawan,
                (string) ($karyawan->Nama ?? '-'),
                $hp,
                $noTransaksi,
                $pesan
            );
        } catch (\Throwable $e) {
            Log::channel('lemburLog')->warning(
                'Gagal mengirim WA penugasan lembur: '.$e->getMessage(),
                ['no_transaksi' => $noTransaksi, 'kode_karyawan' => $payload['kode_karyawan'] ?? null]
            );
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
        $dateSource = $row['tanggal'] ?? $context['date'] ?? null;
        if (! $dateSource) {
            throw new InvalidArgumentException('Tanggal lembur wajib diisi.');
        }

        return Carbon::parse($dateSource)->startOfDay();
    }

    protected function resolveType(array $row, array $context): string
    {
        $rawType = strtoupper(trim((string) ($row['type'] ?? $context['type'] ?? 'LEMBUR_HK')));
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
        if (preg_match('/^\d{2}:\d{2}$/', $normalized)) {
            return $normalized.':00';
        }
        if (! preg_match('/^\d{2}:\d{2}:\d{2}$/', $normalized)) {
            throw new InvalidArgumentException('Format jam tidak valid.');
        }

        return $normalized;
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
                Log::channel('lemburLog')->warning('SP nomor transaksi lembur gagal, fallback ke query manual: '.$e->getMessage());
            }
        }

        $last = DB::table('Transaksi_Lembur')
            ->where('No_Transaksi', 'like', $prefix.'%')
            ->orderBy('No_Transaksi', 'desc')
            ->value('No_Transaksi');

        $seq = 1;
        if ($last) {
            $parts = explode('-', (string) $last);
            $seq = ((int) end($parts)) + 1;
        }

        return sprintf('%s%04d', $prefix, $seq);
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
                'Gagal ambil waktu dari FormatTanggalHelper::getCurrentTime(LemburRequest), fallback ke server time: '.$e->getMessage()
            );

            return Carbon::now((string) config('app.timezone', 'Asia/Jakarta'));
        }
    }
}
