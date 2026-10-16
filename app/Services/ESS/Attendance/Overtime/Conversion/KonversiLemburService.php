<?php

namespace App\Services\ESS\Attendance\Overtime\Conversion;

use App\Helpers\WhatsappLogHelper;
use App\Models\ESS\Employee\Models\Karyawan;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowResult;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalService;
use App\Services\SYSTEM\Notification\WhatsappMessageBuilder;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KonversiLemburService
{
    protected ApprovalFlowService $approvalFlowService;

    protected TransactionService $transactionService;

    protected HrisAttendanceSnapshotService $attendanceService;

    protected AttendanceDirtyResolverService $attendanceDirtyResolver;

    protected array $requesterMetaCache = [];

    protected array $leaveDebtContextCache = [];

    protected array $forcedConversionContextCache = [];

    protected array $approvalFlowPreviewCache = [];

    protected array $statusHariRangeCache = [];

    public function __construct(
        ApprovalFlowService $approvalFlowService,
        TransactionService $transactionService,
        HrisAttendanceSnapshotService $attendanceSnapshotService,
        AttendanceDirtyResolverService $attendanceDirtyResolver,
    ) {
        $this->approvalFlowService = $approvalFlowService;
        $this->transactionService = $transactionService;
        $this->attendanceService = $attendanceSnapshotService;
        $this->attendanceDirtyResolver = $attendanceDirtyResolver;
    }

    public function getRequesterMeta(string $kodeKaryawan): array
    {
        if (isset($this->requesterMetaCache[$kodeKaryawan])) {
            return $this->requesterMetaCache[$kodeKaryawan];
        }

        $row = DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'k.ID_Level_Jabatan', '=', 'v.ID_Level_Jabatan')
            ->where('k.Kode_Karyawan', $kodeKaryawan)
            ->select(
                'k.Kode_Perusahaan',
                'k.Kode_Karyawan',
                'k.Nama',
                'k.UserID_Absen',
                'k.UserID_Web',
                DB::raw('ISNULL(v.ID_Level, 0) as ID_Level'),
            )
            ->first();

        if (! $row) {
            throw new \Exception('Data karyawan tidak ditemukan.');
        }

        return $this->requesterMetaCache[$kodeKaryawan] = [
            'kode_perusahaan' => (string) ($row->Kode_Perusahaan ?: '001'),
            'kode_karyawan' => (string) $row->Kode_Karyawan,
            'nama' => (string) ($row->Nama ?? ''),
            'user_id_absen' => (string) ($row->UserID_Absen ?? ''),
            'user_id_web' => (string) ($row->UserID_Web ?? ''),
            'level_id' => (int) ($row->ID_Level ?? 0),
        ];
    }

    protected function requireRequesterUserIdWeb(array $meta): string
    {
        $userIdWeb = trim((string) ($meta['user_id_web'] ?? ''));
        if ($userIdWeb === '') {
            $kodeKaryawan = (string) ($meta['kode_karyawan'] ?? '-');
            throw new \Exception(
                "UserID_Web requester tidak ditemukan untuk karyawan {$kodeKaryawan}. Proses dihentikan agar audit Created_By tetap akurat.",
            );
        }

        return $userIdWeb;
    }

    public function getConversionConfig(): array
    {
        return [
            'enable' => (bool) config('services.hcis.konversi_lembur.enable', true),
            'max_days' => (int) config('services.hcis.konversi_lembur.max_days', 30),
            'needs_approval' => (bool) config('services.hcis.konversi_lembur.needs_approval', true),
            'day_conversion_needs_approval' => (bool) config(
                'services.hcis.konversi_lembur.day_conversion_needs_approval',
                true,
            ),
            'material_conversion_needs_approval' => (bool) config(
                'services.hcis.konversi_lembur.material_conversion_needs_approval',
                true,
            ),
            'day_conversion_code' => (string) config(
                'services.hcis.konversi_lembur.day_conversion_code',
                'DAY_CONVERSION',
            ),
            'material_conversion_code' => (string) config(
                'services.hcis.konversi_lembur.material_conversion_code',
                'MATERIAL_CONVERSION',
            ),
            'to_cuti_conversion_code' => (string) config(
                'services.hcis.konversi_lembur.to_cuti_conversion_code',
                'KONVERSI_TO_CUTI',
            ),
            'conversion_level_whitelist' => $this->normalizeConversionLevelWhitelist(
                'services.hcis.konversi_lembur.conversion_level_whitelist',
            ),
            'conversion_level_material_whitelist' => $this->normalizeConversionLevelWhitelist(
                'services.hcis.konversi_lembur.conversion_level_material_whitelist',
            ),
            'conversion_level_day_off_whitelist' => $this->normalizeConversionLevelWhitelist(
                'services.hcis.konversi_lembur.conversion_level_day_off_whitelist',
            ),
        ];
    }

    protected function normalizeConversionLevelWhitelist(string $configKey): array
    {
        return collect(config($configKey, []))->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->values()->all();
    }

    public function getChecktime(string $date, string $kodeKaryawan): array
    {
        $meta = $this->getRequesterMeta($kodeKaryawan);
        $userIdAbsen = $meta['user_id_absen'];
        $row = DB::table('Checktime')
            ->whereDate('Tanggal', $date)
            ->where('UserID_Absen', $userIdAbsen)
            ->select('Checktime')
            ->first();

        return [
            'checktime' => $row ? $row->Checktime : null,
        ];
    }

    public function ensureConversionAccessAllowed(string $kodeKaryawan): void
    {
        $config = $this->getConversionConfig();
        if (! $config['enable']) {
            throw new \Exception('Fitur konversi lembur belum diaktifkan.');
        }

        $meta = $this->getRequesterMeta($kodeKaryawan);
        if (! $this->isLevelAllowed($meta['level_id'], $config['conversion_level_whitelist'])) {
            throw new \Exception(
                'Level Anda tidak termasuk whitelist konversi lembur. Level saat ini: '.$meta['level_id'],
            );
        }
    }

    public function ensureConversionTypeAllowed(string $kodeKaryawan, string $jenisKonversi): void
    {
        $normalizedType = $this->normalizeConversionType($jenisKonversi);
        $context = $this->getForcedConversionContext($kodeKaryawan);

        if (($context['is_forced_to_cuti'] ?? false) === true) {
            if ($normalizedType !== 'TO_CUTI') {
                throw new \Exception(
                    'Anda masih memiliki hutang cuti. Konversi lembur saat ini hanya bisa dipakai untuk bayar hutang cuti.',
                );
            }

            return;
        }

        if ($normalizedType === 'TO_CUTI') {
            throw new \Exception('Bayar hutang cuti hanya tersedia ketika Anda masih memiliki hutang cuti.');
        }

        $config = $this->getConversionConfig();
        $meta = $this->getRequesterMeta($kodeKaryawan);

        $whitelist =
            $normalizedType === 'MATERIAL'
                ? $config['conversion_level_material_whitelist']
                : $config['conversion_level_day_off_whitelist'];

        if (! $this->isLevelAllowed($meta['level_id'], $whitelist)) {
            throw new \Exception(
                'Level Anda tidak diizinkan untuk tipe konversi '.
                    $normalizedType.
                    '. Level saat ini: '.
                    $meta['level_id'],
            );
        }
    }

    public function getAllowedConversionTypesForLevel(int $levelId): array
    {
        $config = $this->getConversionConfig();
        $allowedTypes = [];

        if ($this->isLevelAllowed($levelId, $config['conversion_level_day_off_whitelist'])) {
            $allowedTypes[] = 'DAY_OFF';
        }

        if ($this->isLevelAllowed($levelId, $config['conversion_level_material_whitelist'])) {
            $allowedTypes[] = 'MATERIAL';
        }

        return $allowedTypes;
    }

    public function getLeaveDebtContext(string $kodeKaryawan): array
    {
        if (isset($this->leaveDebtContextCache[$kodeKaryawan])) {
            return $this->leaveDebtContextCache[$kodeKaryawan];
        }

        $karyawan = Karyawan::query()->find($kodeKaryawan);
        if (! $karyawan) {
            throw new \Exception('Data karyawan tidak ditemukan.');
        }

        $hutangCuti = (int) $karyawan->hutang_cuti();

        return $this->leaveDebtContextCache[$kodeKaryawan] = [
            'hutang_cuti' => $hutangCuti,
            'has_hutang_cuti' => $hutangCuti > 0,
        ];
    }

    public function getForcedConversionContext(string $kodeKaryawan): array
    {
        if (isset($this->forcedConversionContextCache[$kodeKaryawan])) {
            return $this->forcedConversionContextCache[$kodeKaryawan];
        }

        $meta = $this->getRequesterMeta($kodeKaryawan);
        $debtContext = $this->getLeaveDebtContext($kodeKaryawan);
        $allowedTypes = $this->getAllowedConversionTypesForLevel((int) ($meta['level_id'] ?? 0));
        $isForcedToCuti = ($debtContext['has_hutang_cuti'] ?? false) === true;

        if ($isForcedToCuti) {
            $allowedTypes = ['TO_CUTI'];
        }

        return $this->forcedConversionContextCache[$kodeKaryawan] = [
            'hutang_cuti' => (int) ($debtContext['hutang_cuti'] ?? 0),
            'has_hutang_cuti' => $isForcedToCuti,
            'is_forced_to_cuti' => $isForcedToCuti,
            'forced_conversion_type' => $isForcedToCuti ? 'TO_CUTI' : null,
            'available_conversion_types' => $allowedTypes,
            'forced_message' => $isForcedToCuti
                ? 'Anda masih memiliki hutang cuti, sehingga konversi lembur saat ini diprioritaskan untuk mengurangi hutang cuti terlebih dahulu.'
                : null,
        ];
    }

    protected function isLevelAllowed(int $levelId, array $whitelist): bool
    {
        if (empty($whitelist)) {
            return false;
        }

        return in_array($levelId, $whitelist, true);
    }

    protected function normalizeConversionType(?string $jenisKonversi): string
    {
        $normalized = strtoupper(trim((string) $jenisKonversi));

        if ($normalized === 'TO_CUTI' || $normalized === 'KONVERSI_TO_CUTI') {
            return 'TO_CUTI';
        }

        if ($normalized === 'MATERIAL' || $normalized === 'MATERIAL_CONVERSION') {
            return 'MATERIAL';
        }

        return 'DAY_OFF';
    }

    protected function resolveApprovalCode(string $jenisKonversi): string
    {
        $config = $this->getConversionConfig();
        $code = match ($jenisKonversi) {
            'MATERIAL' => $config['material_conversion_code'],
            'TO_CUTI' => $config['to_cuti_conversion_code'],
            default => $config['day_conversion_code'],
        };

        return trim((string) $code);
    }

    protected function resolveNeedsApproval(string $jenisKonversi): bool
    {
        $config = $this->getConversionConfig();
        if (! $config['needs_approval']) {
            return false;
        }

        return $jenisKonversi === 'MATERIAL'
            ? $config['material_conversion_needs_approval']
            : $config['day_conversion_needs_approval'];
    }

    protected function notifyFirstApproverForConversion(
        string $noTransaksi,
        string $namaRequester,
        string $tanggalLembur,
        ApprovalFlowResult $flowResult,
    ): void {
        if (! $flowResult->hasFirstApprover()) {
            Log::info('Konversi lembur tidak memiliki first approver', [
                'no_transaksi' => $noTransaksi,
            ]);

            return;
        }

        if ($flowResult->isGroupFirst()) {
            Log::info('Konversi lembur first approver berupa group, notifikasi diskip sesuai kebijakan.', [
                'no_transaksi' => $noTransaksi,
                'group_id' => $flowResult->getFirstApproverGroupId(),
            ]);

            return;
        }

        $approverKode = (string) ($flowResult->getFirstApproverKode() ?? '');
        if ($approverKode === '') {
            Log::warning('Konversi lembur first approver serial kosong, notifikasi tidak dikirim.', [
                'no_transaksi' => $noTransaksi,
            ]);

            return;
        }

        $approver = DB::table('Karyawan')->where('Kode_Karyawan', $approverKode)->select('Nama', 'HP')->first();

        $approverNama = (string) ($approver->Nama ?? $approverKode);
        $approverHp = (string) ($approver->HP ?? '');

        if ($approverHp === '') {
            Log::warning('Konversi lembur approver tidak memiliki nomor HP, notifikasi tidak dikirim.', [
                'no_transaksi' => $noTransaksi,
                'approver_kode' => $approverKode,
            ]);

            return;
        }

        $tanggalFormatted = Carbon::parse($tanggalLembur)->locale('id')->translatedFormat('d F Y');

        $message = WhatsappMessageBuilder::Notif_Request_Transaksi_Tanggal(
            $approverHp,
            $approverNama,
            'Konversi Lembur',
            $noTransaksi,
            $namaRequester,
            $tanggalFormatted,
        );

        $onSuccess = function () use ($noTransaksi, $approverKode) {
            DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Approver_Karyawan_Kode', $approverKode)
                ->whereNull('Flag_Approval')
                ->update([
                    'angka_wa' => DB::raw('ISNULL(angka_wa, 0) + 1'),
                    'update_at' => now(),
                ]);
        };

        WhatsappLogHelper::sendAndLog($approverKode, $approverNama, $approverHp, $noTransaksi, $message, $onSuccess);
    }

    protected function buildDetNotePayload(?string $userNote, ?string $targetOffDate): ?string
    {
        $payload = [
            'target_off' => $targetOffDate,
            'note' => $userNote,
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    protected function extractTargetOffDateFromDetNote(?string $detNote): ?string
    {
        if (! $detNote) {
            return null;
        }

        $decoded = json_decode((string) $detNote, true);
        if (! is_array($decoded)) {
            return null;
        }

        $targetOff = isset($decoded['target_off']) ? (string) $decoded['target_off'] : '';
        if (! $targetOff) {
            return null;
        }

        try {
            return Carbon::parse($targetOff)->toDateString();
        } catch (\Throwable $th) {
            return null;
        }
    }

    protected function getStatusHari(string $kodePerusahaan, string $kodeKaryawan, string $tanggal): string
    {
        $row = DB::selectOne('SELECT dbo.N_HRIS_FN_GetStatusHari(?, ?, ?) as Status_Hari', [
            $kodePerusahaan,
            $kodeKaryawan,
            $tanggal,
        ]);

        return strtoupper((string) ($row->Status_Hari ?? 'WORK'));
    }

    protected function getResolvedStatusHariForDate(
        string $kodePerusahaan,
        string $kodeKaryawan,
        string $tanggal,
    ): string {
        $statusHari = $this->getStatusHari($kodePerusahaan, $kodeKaryawan, $tanggal);

        // Keep consistency with flows that rely on status range output.
        try {
            $range = DB::select('SELECT TOP 1 StatusHari FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?)', [
                $kodePerusahaan,
                $kodeKaryawan,
                $tanggal,
                $tanggal,
            ]);

            if (! empty($range) && ! empty($range[0]->StatusHari)) {
                return strtoupper((string) $range[0]->StatusHari);
            }
        } catch (\Throwable $th) {
            // Fall back to N_HRIS_FN_GetStatusHari when range call fails.
        }

        return $statusHari;
    }

    protected function getResolvedStatusHariMapForRange(
        string $kodePerusahaan,
        string $kodeKaryawan,
        string $startDate,
        string $endDate,
    ): array {
        $cacheKey = implode('|', [$kodePerusahaan, $kodeKaryawan, $startDate, $endDate]);
        if (isset($this->statusHariRangeCache[$cacheKey])) {
            return $this->statusHariRangeCache[$cacheKey];
        }

        try {
            $rows = DB::select('SELECT Tanggal, StatusHari FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?)', [
                $kodePerusahaan,
                $kodeKaryawan,
                $startDate,
                $endDate,
            ]);

            $statusMap = collect($rows)
                ->filter(fn ($row) => ! empty($row->Tanggal))
                ->mapWithKeys(function ($row) {
                    try {
                        $dateKey = Carbon::parse((string) $row->Tanggal)->toDateString();
                    } catch (\Throwable $th) {
                        return [];
                    }

                    return [
                        $dateKey => strtoupper((string) ($row->StatusHari ?? 'WORK')),
                    ];
                })
                ->toArray();

            return $this->statusHariRangeCache[$cacheKey] = $statusMap;
        } catch (\Throwable $th) {
            return $this->statusHariRangeCache[$cacheKey] = [];
        }
    }

    protected function normalizeTransactionJenisLabel(?string $jenis): string
    {
        $normalized = strtoupper(trim((string) $jenis));

        return match ($normalized) {
            'SHIFT' => 'Perubahan Shift',
            'STATUS_HARI' => 'Status Hari',
            'TUKAR_HARI' => 'Tukar Hari',
            'CUTI' => 'Cuti',
            'SAKIT_IZIN' => 'Sakit / Izin',
            // "TERLAMBAT" => "Terlambat / Pulang Cepat",
            'KONVERSI_LEMBUR' => 'Konversi Lembur',
            default => $normalized !== '' ? ucwords(strtolower(str_replace('_', ' ', $normalized))) : 'Transaksi',
        };
    }

    protected function normalizeDashboardStatusLabel(?string $status): string
    {
        return match (strtoupper(trim((string) $status))) {
            'PENDING', 'CURRENT' => 'Pending',
            'APPROVED', 'APPLIED' => 'Approved',
            'REJECTED' => 'Rejected',
            'VOID' => 'Void',
            default => 'Unknown',
        };
    }

    protected function buildConversionSummaryLabel(string $jenisKonversi, ?string $targetOffDate = null): string
    {
        $normalizedType = $this->normalizeConversionType($jenisKonversi);

        if ($normalizedType === 'TO_CUTI') {
            return 'Bayar Hutang Cuti';
        }

        if ($normalizedType === 'MATERIAL') {
            return 'Konversi ke Material / Uang';
        }

        $label = 'Konversi ke Day Off';
        if (! empty($targetOffDate)) {
            try {
                $label .= ' ('.Carbon::parse($targetOffDate)->locale('id')->translatedFormat('d M').')';
            } catch (\Throwable $th) {
                // keep generic label
            }
        }

        return $label;
    }

    protected function getCalendarRelatedTransactions(string $kodeKaryawan, string $startDate, string $endDate): array
    {
        $txSource = (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)
            ->forRequesterBetween($kodeKaryawan, $startDate, $endDate);

        return DB::query()
            ->fromSub($txSource, 'tx')
            ->whereNotIn('Jenis', ['SHIFT', 'pulangCepat', 'terlambat'])
            ->whereNotIn('Dashboard_Status', ['REJECTED', 'VOID', 'FUTURE'])
            ->whereNotExists(function ($query) {
                $query
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Konversi_Lembur_Detail as d')
                    ->whereColumn('d.No_Transaksi', 'tx.No_Transaksi')
                    ->where('d.Flag_Approval', 'T');
            })
            ->select('No_Transaksi', 'Tanggal_Item', 'Jenis', 'Dashboard_Status', 'Deskripsi')
            ->orderBy('Tanggal_Item')
            ->orderByDesc('Source_Created_At')
            ->get()
            ->groupBy(function ($row) {
                return Carbon::parse($row->Tanggal_Item)->format('Y-m-d');
            })
            ->map(function ($rows) {
                return collect($rows)
                    ->take(1)
                    ->map(function ($row) {
                        $jenis = strtoupper((string) ($row->Jenis ?? ''));
                        $status = strtoupper((string) ($row->Dashboard_Status ?? 'UNKNOWN'));

                        return [
                            'no_transaksi' => (string) ($row->No_Transaksi ?? ''),
                            'jenis' => $jenis,
                            'jenis_label' => $this->normalizeTransactionJenisLabel($jenis),
                            'status' => $status,
                            'status_label' => $this->normalizeDashboardStatusLabel($status),
                            'summary' => (string) ($row->Deskripsi ?? ''),
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->toArray();
    }

    protected function getDayOffDateConflicts(string $kodeKaryawan, string $startDate, string $endDate): array
    {
        $txSource = (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)
            ->forRequesterBetween($kodeKaryawan, $startDate, $endDate);

        $rows = DB::query()
            ->fromSub($txSource, 'tx')
            ->whereNotIn('Jenis', ['SHIFT', 'pulangCepat', 'terlambat'])
            ->whereNotIn('Dashboard_Status', ['REJECTED', 'VOID', 'FUTURE'])
            ->whereNotExists(function ($query) {
                $query
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Konversi_Lembur_Detail as d')
                    ->whereColumn('d.No_Transaksi', 'tx.No_Transaksi')
                    ->where('d.Flag_Approval', 'T');
            })
            ->select('No_Transaksi', 'Tanggal_Item', 'Jenis', 'Dashboard_Status', 'Deskripsi', 'Source_View')
            ->orderBy('Tanggal_Item')
            ->orderByDesc('Source_Created_At')
            ->get()
            ->groupBy(function ($row) {
                return Carbon::parse($row->Tanggal_Item)->format('Y-m-d');
            });

        return $rows
            ->map(function ($items, $dateKey) {
                $entries = collect($items)
                    ->take(1)
                    ->map(function ($row) {
                        $jenis = strtoupper((string) ($row->Jenis ?? ''));
                        $status = strtoupper((string) ($row->Dashboard_Status ?? 'UNKNOWN'));
                        $label = $this->normalizeTransactionJenisLabel($jenis);

                        return [
                            'no_transaksi' => (string) ($row->No_Transaksi ?? ''),
                            'jenis' => $jenis,
                            'jenis_label' => $label,
                            'status' => $status,
                            'description' => (string) ($row->Deskripsi ?? ''),
                            'source_view' => (string) ($row->Source_View ?? ''),
                        ];
                    })
                    ->values();

                $tooltip = $entries
                    ->map(function ($entry) {
                        $statusLabel = $entry['status'] !== '' ? ' ('.$entry['status'].')' : '';

                        return $entry['jenis_label'].$statusLabel;
                    })
                    ->unique()
                    ->implode(', ');

                return [
                    'date' => $dateKey,
                    'disabled' => $entries->isNotEmpty(),
                    'tooltip' => $tooltip ?: 'Tanggal dipakai transaksi lain.',
                    'transactions' => $entries->all(),
                    'primary_type' => (string) ($entries->first()['jenis'] ?? ''),
                    'primary_type_label' => (string) ($entries->first()['jenis_label'] ?? 'Transaksi'),
                ];
            })
            ->toArray();
    }

    protected function ensureDayOffTargetDateAvailable(string $kodeKaryawan, string $tanggalOffTarget): void
    {
        $conflicts = $this->getDayOffDateConflicts($kodeKaryawan, $tanggalOffTarget, $tanggalOffTarget);

        $conflict = $conflicts[$tanggalOffTarget] ?? null;
        if (! $conflict || empty($conflict['disabled'])) {
            return;
        }

        throw new \Exception(
            'Tanggal target OFF sudah dipakai transaksi lain: '.($conflict['tooltip'] ?? 'tidak tersedia').'.',
        );
    }

    /**
     * Daftar tanggal Target_Off dari konversi lembur -> Day Off yang masih PENDING
     * (belum di-approve/reject) untuk 1 karyawan dalam rentang tanggal.
     *
     * Dipakai kalender izin & cuti untuk men-disable tanggal kandidat libur yang
     * sedang menunggu approval. Tanggal yang sudah approved TIDAK termasuk di sini
     * karena sudah muncul sebagai OFF lewat status-hari (N_HRIS_FN_GetStatusHariRange).
     *
     * @return array<int, array{date: string, tooltip: string}>
     */
    public function getPendingDayOffTargetDates(string $kodeKaryawan, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $query = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Det as det')
            ->join('N_HRIS_Transaksi_Konversi_Lembur_Detail as d', 'd.Id', '=', 'det.Id_Detail')
            ->join('N_HRIS_Transaksi_Konversi_Lembur as h', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->where('det.Jenis_Konversi', 'DAY_OFF')
            ->whereNotNull('det.Target_Off')
            // Range setengah-terbuka [start 00:00, endHari+1 00:00) supaya SARGable
            // (tanpa CAST/fungsi di kolom -> index pada Target_Off bisa dipakai).
            ->where('det.Target_Off', '>=', $start->toDateTimeString())
            ->where('det.Target_Off', '<', $end->copy()->addDay()->toDateTimeString())
            // pending = belum ada keputusan approval (bukan approved 'Y', bukan reject 'T')
            ->whereNull('d.Flag_Approval');

        // Buang transaksi yang dibatalkan (header.Status = 'Y'/'T').
        $this->applyNotCancelledFilter($query, 'h.Status');

        return $query
            ->select('det.Target_Off', 'd.No_Transaksi')
            ->orderBy('det.Target_Off')
            ->get()
            ->map(function ($row) {
                if (empty($row->Target_Off)) {
                    return null;
                }

                return [
                    'date' => Carbon::parse($row->Target_Off)->format('Y-m-d'),
                    'no_transaksi' => (string) ($row->No_Transaksi ?? ''),
                ];
            })
            ->filter()
            ->unique('date')
            ->values()
            ->map(fn (array $item) => [
                'date' => $item['date'],
                'no_transaksi' => $item['no_transaksi'],
                'tooltip' => 'Konversi Lembur Day off'
                    .($item['no_transaksi'] !== '' ? ' - '.$item['no_transaksi'] : ''),
            ])
            ->all();
    }

    public function getDayOffDateOptions(string $kodeKaryawan, string $startDate, string $endDate): array
    {
        $this->ensureConversionAccessAllowed($kodeKaryawan);

        $meta = $this->getRequesterMeta($kodeKaryawan);
        $kodePerusahaan = (string) ($meta['kode_perusahaan'] ?: '001');

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw new \Exception('Range tanggal tidak valid.');
        }

        // Safety cap to avoid very heavy status_hari calls in one request.
        if ($start->diffInDays($end) > 370) {
            throw new \Exception('Range maksimal 370 hari.');
        }

        // Keep logic consistent with self-shift endpoint (/self-shift/off-days)
        // by querying OFF/WORK directly from status-hari range function.
        // TVF GetStatusHariRange berat (status-hari deterministik per hari) & sering dipanggil
        // ulang utk rentang sama -> cache pendek per (perusahaan, karyawan, rentang, filter).
        $statusHariCacheKey = fn (string $filter) => 'statushari.range:'.$kodePerusahaan.':'.$kodeKaryawan
            .':'.$start->toDateString().':'.$end->toDateString().':'.$filter;

        $offRows = \Illuminate\Support\Facades\Cache::remember(
            $statusHariCacheKey('OFF'),
            300,
            fn () => DB::select("SELECT * FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?) WHERE StatusHari LIKE 'OFF%'", [
                $kodePerusahaan,
                $kodeKaryawan,
                $start->toDateString(),
                $end->toDateString(),
            ]),
        );

        $workRows = \Illuminate\Support\Facades\Cache::remember(
            $statusHariCacheKey('WORK'),
            300,
            fn () => DB::select("SELECT * FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?) WHERE StatusHari LIKE 'WORK%'", [
                $kodePerusahaan,
                $kodeKaryawan,
                $start->toDateString(),
                $end->toDateString(),
            ]),
        );

        $offDates = collect($offRows)->map(fn ($row) => Carbon::parse($row->Tanggal)->format('Y-m-d'))->values()->all();

        $workDates = collect($workRows)
            ->map(fn ($row) => Carbon::parse($row->Tanggal)->format('Y-m-d'))
            ->values()
            ->all();

        // Fallback: if WORK rows are unexpectedly empty, derive from all rows minus OFF.
        if (empty($workDates)) {
            $allRows = DB::select('SELECT * FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?)', [
                $kodePerusahaan,
                $kodeKaryawan,
                $start->toDateString(),
                $end->toDateString(),
            ]);

            $workDates = collect($allRows)
                ->filter(function ($row) {
                    $statusHari = strtoupper((string) ($row->StatusHari ?? ''));

                    return ! str_starts_with($statusHari, 'OFF');
                })
                ->map(fn ($row) => Carbon::parse($row->Tanggal)->format('Y-m-d'))
                ->values()
                ->all();
        }

        $workDates = array_values(array_unique($workDates));
        $offDates = array_values(array_unique($offDates));
        $conflictMap = $this->getDayOffDateConflicts($kodeKaryawan, $start->toDateString(), $end->toDateString());

        $workDateItems = collect($workDates)
            ->map(function ($date) use ($conflictMap) {
                $conflict = $conflictMap[$date] ?? null;

                return [
                    'date' => $date,
                    'disabled' => (bool) ($conflict['disabled'] ?? false),
                    'tooltip' => (string) ($conflict['tooltip'] ?? ''),
                    'primary_type' => (string) ($conflict['primary_type'] ?? ''),
                    'primary_type_label' => (string) ($conflict['primary_type_label'] ?? ''),
                    'transactions' => $conflict['transactions'] ?? [],
                ];
            })
            ->values()
            ->all();

        $availableWorkDates = collect($workDateItems)
            ->filter(fn ($item) => ($item['disabled'] ?? false) !== true)
            ->pluck('date')
            ->values()
            ->all();

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'work_dates' => $availableWorkDates,
            'work_date_items' => $workDateItems,
            'off_dates' => $offDates,
        ];
    }

    public function getConversionRule()
    {
        $config = $this->getConversionConfig();

        $defaultRule = [
            'minimal_hours' => 6,
            'day_off_reward' => 1,
            'valid_days' => max(1, (int) $config['max_days']),
            'label' => 'Min. 6 jam lembur = 1 hari libur',
        ];

        try {
            /*
             * Diurutkan menurut Id, bukan Created_At: tabel ini TIDAK punya
             * kolom Created_At (kolomnya Id, Kode_Konversi, Jam_Minimal,
             * Jumlah_Hari_Off, Aktif -- diperiksa di katalog dev dan production).
             *
             * Bentuk lama selalu ditolak SQL Server dengan "Invalid column
             * name", lalu galatnya ditelan catch di bawah dan fungsi ini
             * mengembalikan $defaultRule. Hari ini akibatnya tak terlihat karena
             * tabelnya kosong di kedua lingkungan, sehingga cabang `! $rule`
             * memberi hasil yang sama. Yang berbahaya adalah nanti: begitu admin
             * mengisi satu aturan, Jam_Minimal dan Jumlah_Hari_Off yang ia
             * tetapkan tetap TIDAK terpakai, dan tak ada satu pun tanda bahwa
             * pengaturannya diabaikan.
             *
             * Kolom Aktif sengaja TIDAK dijadikan saringan di sini: tabelnya
             * kosong, jadi tak ada data untuk memastikan nilai mana yang berarti
             * aktif. Menebaknya berarti menambahkan aturan bisnis baru diam-diam.
             */
            $rule = DB::table('N_HRIS_Master_Konversi_Lembur')->orderByDesc('Id')->first();

            if (! $rule) {
                return $defaultRule;
            }

            $minimalHours = (float) ($rule->Jam_Minimal ?? $defaultRule['minimal_hours']);
            $dayOffReward = (float) ($rule->Jumlah_Hari_Off ?? $defaultRule['day_off_reward']);

            return [
                'minimal_hours' => $minimalHours,
                'day_off_reward' => $dayOffReward,
                'valid_days' => $defaultRule['valid_days'],
                'label' => 'Min. '.
                    rtrim(rtrim(number_format($minimalHours, 1, '.', ''), '0'), '.').
                    ' jam lembur = '.
                    rtrim(rtrim(number_format($dayOffReward, 1, '.', ''), '0'), '.').
                    ' hari libur',
            ];
        } catch (\Throwable $th) {
            return $defaultRule;
        }
    }

    protected function mapApprovalStepStatus(?string $flagApprovalOrStatus, int $idx): string
    {
        $normalized = strtoupper((string) $flagApprovalOrStatus);
        if ($normalized === 'Y' || $normalized === 'APPROVED' || $normalized === 'APPLIED') {
            return 'approved';
        }

        return $idx === 0 ? 'pending' : 'upcoming';
    }

    protected function getApprovalStepsForTransaction(string $noTransaksi, array $fallbackSteps = []): array
    {
        try {
            $rows = DB::table('N_HRIS_VW_Approval_Request_V2')
                ->where('No_Transaksi', $noTransaksi)
                ->orderBy('Request_Order_Flow')
                ->get([
                    'Request_Order_Flow',
                    'Current_Approver',
                    'Approved_By_Kode',
                    'Flag_Approval',
                    'Tanggal_Approval',
                    'Catatan',
                    'Flow_State',
                ]);

            if ($rows->isEmpty()) {
                return $fallbackSteps;
            }

            $kodeList = $rows
                ->flatMap(function ($row) {
                    return [(string) ($row->Current_Approver ?? ''), (string) ($row->Approved_By_Kode ?? '')];
                })
                ->filter(fn ($kode) => $kode !== '')
                ->unique()
                ->values()
                ->all();

            $names = $this->getKaryawanNames($kodeList);

            return $rows
                ->values()
                ->map(function ($row, $idx) use ($names) {
                    $actorCode = (string) ($row->Current_Approver ?? '');
                    $approvedByCode = (string) ($row->Approved_By_Kode ?? '');
                    $status = 'upcoming';
                    $flagApproval = strtoupper((string) ($row->Flag_Approval ?? ''));
                    $flowState = strtoupper((string) ($row->Flow_State ?? ''));

                    if ($flagApproval === 'Y') {
                        $status = 'approved';
                    } elseif ($flagApproval === 'T') {
                        $status = 'rejected';
                    } elseif ($flowState === 'CURRENT') {
                        $status = 'pending';
                    }

                    $displayCode = $actorCode ?: $approvedByCode;
                    $actorName = $names[$displayCode] ?? ($displayCode !== '' ? $displayCode : 'Approver');

                    return [
                        'step' => 'Step '.((int) ($row->Request_Order_Flow ?? $idx + 1)),
                        'actor' => $actorName,
                        'status' => $status,
                        'status_label' => ucfirst($status),
                        'approved_at' => ! empty($row->Tanggal_Approval)
                            ? Carbon::parse((string) $row->Tanggal_Approval)->translatedFormat('d M Y')
                            : null,
                        'note' => ! empty($row->Catatan) ? (string) $row->Catatan : null,
                    ];
                })
                ->all();
        } catch (\Throwable $th) {
            return $fallbackSteps;
        }
    }

    public function getApprovalStepsMapForTransactions(array $fallbackStepsByTransaction): array
    {
        $transactionNumbers = collect(array_keys($fallbackStepsByTransaction))
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->unique()
            ->values();

        if ($transactionNumbers->isEmpty()) {
            return [];
        }

        try {
            $rows = DB::table('N_HRIS_VW_Approval_Request_V2')
                ->whereIn('No_Transaksi', $transactionNumbers->all())
                ->orderBy('No_Transaksi')
                ->orderBy('Request_Order_Flow')
                ->get([
                    'No_Transaksi',
                    'Request_Order_Flow',
                    'Current_Approver',
                    'Approved_By_Kode',
                    'Flag_Approval',
                    'Tanggal_Approval',
                    'Catatan',
                    'Flow_State',
                ]);

            if ($rows->isEmpty()) {
                return $fallbackStepsByTransaction;
            }

            $kodeList = $rows
                ->flatMap(function ($row) {
                    return [(string) ($row->Current_Approver ?? ''), (string) ($row->Approved_By_Kode ?? '')];
                })
                ->filter(fn ($kode) => $kode !== '')
                ->unique()
                ->values()
                ->all();

            $names = $this->getKaryawanNames($kodeList);
            $mapped = [];

            foreach ($rows->groupBy('No_Transaksi') as $noTransaksi => $groupedRows) {
                $mapped[(string) $noTransaksi] = collect($groupedRows)
                    ->values()
                    ->map(function ($row, $idx) use ($names) {
                        $actorCode = (string) ($row->Current_Approver ?? '');
                        $approvedByCode = (string) ($row->Approved_By_Kode ?? '');
                        $status = 'upcoming';
                        $flagApproval = strtoupper((string) ($row->Flag_Approval ?? ''));
                        $flowState = strtoupper((string) ($row->Flow_State ?? ''));

                        if ($flagApproval === 'Y') {
                            $status = 'approved';
                        } elseif ($flagApproval === 'T') {
                            $status = 'rejected';
                        } elseif ($flowState === 'CURRENT') {
                            $status = 'pending';
                        }

                        $displayCode = $actorCode ?: $approvedByCode;
                        $actorName = $names[$displayCode] ?? ($displayCode !== '' ? $displayCode : 'Approver');

                        return [
                            'step' => 'Step '.((int) ($row->Request_Order_Flow ?? $idx + 1)),
                            'actor' => $actorName,
                            'status' => $status,
                            'status_label' => ucfirst($status),
                            'approved_at' => ! empty($row->Tanggal_Approval)
                                ? Carbon::parse((string) $row->Tanggal_Approval)->translatedFormat('d M Y')
                                : null,
                            'note' => ! empty($row->Catatan) ? (string) $row->Catatan : null,
                        ];
                    })
                    ->all();
            }

            return array_replace($fallbackStepsByTransaction, $mapped);
        } catch (\Throwable $th) {
            return $fallbackStepsByTransaction;
        }
    }

    protected function getKaryawanNames(array $kodeList): array
    {
        if (empty($kodeList)) {
            return [];
        }

        return DB::table('Karyawan')->whereIn('Kode_Karyawan', $kodeList)->pluck('Nama', 'Kode_Karyawan')->toArray();
    }

    protected function getKaryawanIdentityMap(array $kodeList): array
    {
        if (empty($kodeList)) {
            return [];
        }

        return DB::table('Karyawan as k')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'vg.ID_Level_Jabatan',
                '=',
                'k.ID_Level_Jabatan',
            )
            ->whereIn('k.Kode_Karyawan', $kodeList)
            ->select('k.Kode_Karyawan', 'k.Nama', 'vg.nama_jabatan')
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    (string) $row->Kode_Karyawan => [
                        'nama' => (string) ($row->Nama ?? ''),
                        'jabatan' => (string) ($row->nama_jabatan ?? '-'),
                    ],
                ];
            })
            ->toArray();
    }

    public function getSubmissionApprovalPreview(string $kodeKaryawan, ?string $jenisKonversi = null): array
    {
        $this->ensureConversionAccessAllowed($kodeKaryawan);
        $forcedContext = $this->getForcedConversionContext($kodeKaryawan);

        $normalizedType = $this->normalizeConversionType(
            $forcedContext['forced_conversion_type'] ?? ($jenisKonversi ?? 'DAY_OFF'),
        );
        $this->ensureConversionTypeAllowed($kodeKaryawan, $normalizedType);
        $needsApproval = $this->resolveNeedsApproval($normalizedType);
        $approvalCode = $this->resolveApprovalCode($normalizedType);

        if (! $needsApproval) {
            return [
                'jenis_konversi' => $normalizedType,
                'approval_code' => $approvalCode,
                'needs_approval' => false,
                'approvers' => [],
                'approver_policy' => null,
                'hide_approver_policy_section' => true,
                'force_to_cuti' => (bool) ($forcedContext['is_forced_to_cuti'] ?? false),
                'forced_message' => $forcedContext['forced_message'] ?? null,
            ];
        }

        $package = $this->approvalFlowService->getApproverFlow($kodeKaryawan, $approvalCode, true);
        $approvers = $package['approvers'] ?? collect();
        $policy = $package['policy'] ?? null;

        $kodeList = $approvers->pluck('kode_karyawan')->filter(fn ($v) => ! empty($v))->values()->all();
        $identityMap = $this->getKaryawanIdentityMap($kodeList);

        $formattedApprovers = $approvers
            ->values()
            ->map(function ($step, $idx) use ($identityMap) {
                $kode = (string) ($step->kode_karyawan ?? '');
                $identity = $identityMap[$kode] ?? null;

                return [
                    'order' => $idx + 1,
                    'approval_order' => (int) ($step->approval_order ?? $idx + 1),
                    'kode' => $kode,
                    'nama' => $identity['nama'] ?? ($kode ?: 'Approver Group'),
                    'jabatan' => $identity['jabatan'] ?? '-',
                ];
            })
            ->all();

        return [
            'jenis_konversi' => $normalizedType,
            'approval_code' => $approvalCode,
            'needs_approval' => true,
            'approvers' => $formattedApprovers,
            'approver_policy' => $policy,
            'hide_approver_policy_section' => (bool) ($policy['is_skipped_by_requester_level'] ?? false),
            'force_to_cuti' => (bool) ($forcedContext['is_forced_to_cuti'] ?? false),
            'forced_message' => $forcedContext['forced_message'] ?? null,
        ];
    }

    public function getApprovalFlowPreview(
        string $kodeKaryawan,
        string $jenisKonversi = 'DAY_OFF',
        ?string $flagApprovalOrStatus = null,
    ): array {
        $cacheKey = implode('|', [
            $kodeKaryawan,
            $this->normalizeConversionType($jenisKonversi),
            strtoupper((string) ($flagApprovalOrStatus ?? '')),
        ]);

        if (isset($this->approvalFlowPreviewCache[$cacheKey])) {
            return $this->approvalFlowPreviewCache[$cacheKey];
        }

        $approvalCode = $this->resolveApprovalCode($this->normalizeConversionType($jenisKonversi));

        try {
            $package = $this->approvalFlowService->getApproverFlow($kodeKaryawan, $approvalCode, true);
            $approvers = $package['approvers'] ?? collect();

            if ($approvers instanceof Collection && $approvers->isNotEmpty()) {
                $kodeList = $approvers->pluck('kode_karyawan')->filter(fn ($v) => ! empty($v))->values()->all();
                $names = $this->getKaryawanNames($kodeList);

                return $this->approvalFlowPreviewCache[$cacheKey] = $approvers
                    ->values()
                    ->map(function ($step, $idx) use ($flagApprovalOrStatus, $names) {
                        $kode = (string) ($step->kode_karyawan ?? '');
                        $actorName = $names[$kode] ?? ($kode ?: 'Approver Group');

                        return [
                            'step' => 'Step '.($idx + 1),
                            'actor' => $actorName,
                            'status' => $this->mapApprovalStepStatus($flagApprovalOrStatus, (int) $idx),
                        ];
                    })
                    ->all();
            }
        } catch (\Throwable $th) {
            // fallback below
        }

        return $this->approvalFlowPreviewCache[$cacheKey] = [
            [
                'step' => 'Review',
                'actor' => 'Atasan',
                'status' => $this->mapApprovalStepStatus($flagApprovalOrStatus, 0),
            ],
            [
                'step' => 'Finalisasi',
                'actor' => 'HC / HR',
                'status' => $this->mapApprovalStepStatus($flagApprovalOrStatus, 1),
            ],
        ];
    }

    public function getDashboardStats($kodeKaryawan)
    {
        $history = $this->getHistoryForUser($kodeKaryawan);

        $totalPengajuan = $history->count();
        $menungguReview = $history->where('Status', 'SUBMITTED')->count();
        $selesai = $history
            ->filter(function ($item) {
                $status = strtoupper((string) ($item['Status'] ?? ''));

                return in_array($status, ['APPROVED', 'APPLIED'], true);
            })
            ->count();

        return [
            'total_pengajuan' => $totalPengajuan,
            'menunggu_review' => $menungguReview,
            'selesai' => $selesai,
        ];
    }

    protected function getMachineLogsFromWindow(string $userIdAbsen, string $date)
    {
        [$windowStart, $windowEnd] = $this->getOffDateChecktimeWindow($date);

        $uidRaw = trim((string) $userIdAbsen);
        $uidCandidates = collect([$uidRaw]);
        if ($uidRaw !== '' && preg_match('/^0+\d+$/', $uidRaw)) {
            $uidCandidates->push((string) intval($uidRaw));
        }

        $uidCandidates = $uidCandidates->filter(fn ($v) => $v !== null && $v !== '')->unique()->values()->all();

        $logs = DB::table('CHECKINOUT')
            ->select('CHECKTIME', 'Hasil')
            ->where('CHECKTIME', '>=', $windowStart->format('Y-m-d H:i:s'))
            ->where('CHECKTIME', '<=', $windowEnd->format('Y-m-d H:i:s'))
            ->where(function ($q) use ($uidRaw, $uidCandidates) {
                if (! empty($uidCandidates)) {
                    $q->whereIn('USERID', $uidCandidates);
                }

                // Fallback for mixed DB USERID types (numeric/varchar with spaces).
                $q->orWhereRaw('LTRIM(RTRIM(CAST(USERID as varchar(50)))) = ?', [$uidRaw]);
            })
            ->orderBy('CHECKTIME', 'asc')
            ->get();

        return $logs->map(function ($log) {
            return [
                'time' => Carbon::parse($log->CHECKTIME)->format('H:i'),
                'datetime' => Carbon::parse($log->CHECKTIME)->format('Y-m-d H:i:s'),
                'type' => $log->Hasil,
            ];
        });
    }

    protected function getOffDateChecktimeWindow(string $date): array
    {
        $baseDate = Carbon::parse($date)->startOfDay();

        // OFF-day overtime can start late at night and finish the next morning.
        // Keep a small backward buffer and extend into the next day so overnight
        // check-outs remain selectable for the original OFF date.
        $windowStart = $baseDate->copy();
        $windowEnd = $baseDate->copy()->addDay()->addHours(10);

        return [$windowStart, $windowEnd];
    }

    protected function getMachineLogsFromSp(string $kodePerusahaan, string $userIdAbsen, string $date)
    {
        // $rows = DB::select("EXEC HRIS_SP_GET_ABSEN_NEW_V3 ?, ?, ?, ?, '', ''", [
        //     $kodePerusahaan,
        //     $date,
        //     $date,
        //     $userIdAbsen,
        // ]);
        $sourceReuqest = 'konversiLembur-service-getMachineLogsFromSp';
        $tglMulai = $date;
        $tglSelesai = $date;
        $listUserId = $userIdAbsen;
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

        $rows = $response['data'] ?? [];

        $firstValid = collect($rows)
            ->map(fn ($item) => (array) $item)
            ->first(function (array $row) {
                $checkInRaw = $row['CheckIn'] ?? ($row['CHECKIN'] ?? ($row['check_in'] ?? null));

                return ! empty($checkInRaw);
            });

        if (! is_array($firstValid)) {
            return collect();
        }

        $checkInRaw = $firstValid['CheckIn'] ?? ($firstValid['CHECKIN'] ?? ($firstValid['check_in'] ?? null));
        $checkOutRaw = $firstValid['CheckOut'] ?? ($firstValid['CHECKOUT'] ?? ($firstValid['check_out'] ?? null));

        $logs = collect();

        if (! empty($checkInRaw)) {
            try {
                $checkIn = Carbon::parse((string) $checkInRaw);
                $logs->push([
                    'time' => $checkIn->format('H:i'),
                    'datetime' => $checkIn->format('Y-m-d H:i:s'),
                    'type' => 'In',
                ]);
            } catch (\Throwable $th) {
            }
        }

        if (! empty($checkOutRaw)) {
            try {
                $checkOut = Carbon::parse((string) $checkOutRaw);
                $logs->push([
                    'time' => $checkOut->format('H:i'),
                    'datetime' => $checkOut->format('Y-m-d H:i:s'),
                    'type' => 'Out',
                ]);
            } catch (\Throwable $th) {
            }
        }

        return $logs->sortBy('datetime')->values();
    }

    public function getMachineLogs(
        string $userIdAbsen,
        string $date,
        ?string $kodeKaryawan = null,
        ?string $kodePerusahaan = null,
        bool $forceWindow = false,
    ) {
        if ($forceWindow) {
            Log::info('KonversiLembur logs source: CHECKINOUT window (forced)', [
                'tanggal' => $date,
                'kode_karyawan' => $kodeKaryawan,
            ]);

            return $this->getMachineLogsFromWindow($userIdAbsen, $date);
        }

        if (! empty($kodeKaryawan) && ! empty($kodePerusahaan)) {
            $statusHari = $this->getResolvedStatusHariForDate((string) $kodePerusahaan, (string) $kodeKaryawan, $date);

            if (str_starts_with($statusHari, 'OFF')) {
                Log::info('KonversiLembur logs source: CHECKINOUT window', [
                    'kode_karyawan' => $kodeKaryawan,
                    'tanggal' => $date,
                    'status_hari' => $statusHari,
                ]);

                return $this->getMachineLogsFromWindow($userIdAbsen, $date);
            }

            Log::info('KonversiLembur logs source: HRIS_SP_GET_ABSEN_NEW_V3', [
                'kode_karyawan' => $kodeKaryawan,
                'tanggal' => $date,
                'status_hari' => $statusHari,
            ]);

            return $this->getMachineLogsFromSp((string) $kodePerusahaan, $userIdAbsen, $date);
        }

        Log::info('KonversiLembur logs source: CHECKINOUT window (fallback no employee/company context)', [
            'tanggal' => $date,
        ]);

        return $this->getMachineLogsFromWindow($userIdAbsen, $date);
    }

    public function getEligibleLogPairs(
        string $userIdAbsen,
        string $date,
        ?string $kodeKaryawan = null,
        ?string $kodePerusahaan = null,
        bool $forceWindow = false,
    ) {
        $logs = $this->getMachineLogs($userIdAbsen, $date, $kodeKaryawan, $kodePerusahaan, $forceWindow);
        $rule = $this->getConversionRule();
        $minimumHours = (float) $rule['minimal_hours'];

        $pairs = [];
        $currentIn = null;

        foreach ($logs as $log) {
            $isCheckIn = $log['type'] === 'In' || ($log['type'] === null && $currentIn === null);
            $isCheckOut = $log['type'] === 'Out' || ($log['type'] === null && $currentIn !== null);

            if ($isCheckIn && $currentIn === null) {
                $currentIn = $log;
            } elseif ($isCheckOut && $currentIn !== null) {
                $inTime = Carbon::parse($currentIn['datetime']);
                $outTime = Carbon::parse($log['datetime']);
                $diffMinutes = $inTime->diffInMinutes($outTime);
                $diffHours = round($diffMinutes / 60, 1);
                $isEligible = $diffHours >= $minimumHours;

                $pairs[] = [
                    'check_in' => $currentIn['datetime'],
                    'check_out' => $log['datetime'],
                    'durasi' => $diffHours.' Jam',
                    'durasi_menit' => $diffMinutes,
                    'eligible' => $isEligible,
                    'label' => $currentIn['time'].' - '.$log['time'],
                ];

                $currentIn = null;
            }
        }

        return $pairs;
    }

    protected function pickFirstAvailableValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function parseShiftEndForDate(?string $shiftEndRaw, string $date, Carbon $checkIn): ?Carbon
    {
        if (! $shiftEndRaw) {
            return null;
        }

        try {
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $shiftEndRaw)) {
                $shiftEnd = Carbon::parse($date.' '.$shiftEndRaw);
            } else {
                $shiftEnd = Carbon::parse($shiftEndRaw);
            }
        } catch (\Throwable $th) {
            return null;
        }

        // Handle overnight shift where shift end time is on the next day.
        if ($shiftEnd->lessThan($checkIn)) {
            $shiftEnd->addDay();
        }

        return $shiftEnd;
    }

    protected function applyNotCancelledFilter($query, string $column): void
    {
        $query->where(function ($q) use ($column) {
            $q->whereNull($column)
                ->orWhere($column, '')
                ->orWhereNotIn($column, ['Y', 'T']);
        });
    }

    public function getCalendarStatus($kodeKaryawan, $userIdAbsen, $month, $year)
    {
        $this->ensureConversionAccessAllowed($kodeKaryawan);

        $meta = $this->getRequesterMeta($kodeKaryawan);
        $kodePerusahaan = $meta['kode_perusahaan'];

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $today = Carbon::today();
        $rule = $this->getConversionRule();
        $minimumEligibleMinutes = (int) round(((float) $rule['minimal_hours']) * 60);
        $windowDays = (int) $rule['valid_days'];
        $windowStart = $today->copy()->subDays(max($windowDays - 1, 0));

        $appliedConversions = DB::table('N_HRIS_Konversi_Lembur_Efektif')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Flag_Aktif', 'Y')
            ->whereBetween('Tanggal_Lembur_Asal', [$startDate, $endDate])
            ->select('Tanggal_Lembur_Asal', 'Tanggal_Off', 'Jenis_Konversi', 'No_Transaksi_Reference')
            ->orderByDesc('Id')
            ->get()
            ->groupBy(function ($item) {
                return Carbon::parse($item->Tanggal_Lembur_Asal)->format('Y-m-d');
            });

        $transactions = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Det')
            ->join(
                'N_HRIS_Transaksi_Konversi_Lembur_Detail',
                'N_HRIS_Transaksi_Konversi_Lembur_Det.Id_Detail',
                '=',
                'N_HRIS_Transaksi_Konversi_Lembur_Detail.Id',
            )
            ->join(
                'N_HRIS_Transaksi_Konversi_Lembur as h',
                'N_HRIS_Transaksi_Konversi_Lembur_Detail.No_Transaksi',
                '=',
                'h.No_Transaksi',
            )
            ->where('N_HRIS_Transaksi_Konversi_Lembur_Detail.Kode_Karyawan', $kodeKaryawan)
            ->whereBetween('N_HRIS_Transaksi_Konversi_Lembur_Det.Tanggal_Lembur', [$startDate, $endDate])
            ->where(function ($q) {
                $this->applyNotCancelledFilter($q, 'h.Status');
            })
            ->where(function ($q) {
                $this->applyNotCancelledFilter($q, 'N_HRIS_Transaksi_Konversi_Lembur_Detail.Status');
            })
            ->where(function ($q) {
                $this->applyNotCancelledFilter($q, 'N_HRIS_Transaksi_Konversi_Lembur_Det.Status');
            })
            ->where(function ($q) {
                $q->whereNull('N_HRIS_Transaksi_Konversi_Lembur_Detail.Flag_Approval')->orWhere(
                    'N_HRIS_Transaksi_Konversi_Lembur_Detail.Flag_Approval',
                    '!=',
                    'T',
                );
            })
            ->select(
                'N_HRIS_Transaksi_Konversi_Lembur_Detail.No_Transaksi',
                'N_HRIS_Transaksi_Konversi_Lembur_Det.Tanggal_Lembur',
                'N_HRIS_Transaksi_Konversi_Lembur_Det.Jenis_Konversi',
                'N_HRIS_Transaksi_Konversi_Lembur_Det.Target_Off',
                'N_HRIS_Transaksi_Konversi_Lembur_Det.Note as det_note',
                'N_HRIS_Transaksi_Konversi_Lembur_Detail.Flag_Approval as flag_approval',
                'N_HRIS_Transaksi_Konversi_Lembur_Det.Flag_Applied as flag_applied',
                'N_HRIS_Transaksi_Konversi_Lembur_Det.Durasi_Jam',
            )
            ->orderByDesc('N_HRIS_Transaksi_Konversi_Lembur_Det.Id')
            ->get()
            ->groupBy(function ($item) {
                return Carbon::parse($item->Tanggal_Lembur)->format('Y-m-d');
            });

        // $attendanceByDate = DB::table('CHECKINOUT')
        //     ->selectRaw('CAST(CHECKTIME as date) as tanggal_log, MIN(CHECKTIME) as jam_masuk, MAX(CHECKTIME) as jam_keluar, COUNT(1) as log_count, DATEDIFF(minute, MIN(CHECKTIME), MAX(CHECKTIME)) as durasi_menit')
        //     ->where('USERID', $userIdAbsen)
        //     ->whereBetween('CHECKTIME', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()->addHours(12)])
        //     ->groupBy(DB::raw('CAST(CHECKTIME as date)'))
        //     ->get()
        //     ->keyBy(function ($item) {
        //         return Carbon::parse($item->tanggal_log)->format('Y-m-d');
        //     });

        // WORK days: use attendance from SP.
        // $queryAttendence = DB::select("EXEC HRIS_SP_GET_ABSEN_NEW_V3 ?, ?, ?, ?, '', ''", [
        //     $kodePerusahaan,
        //     $startDate->format('Y-m-d'),
        //     $endDate->format('Y-m-d'),
        //     $userIdAbsen,
        // ]);
        $sourceReuqest = 'konversi-lembur-getCalendarStatus';
        $tglMulai = $startDate->format('Y-m-d');
        $tglSelesai = $endDate->format('Y-m-d');
        $listUserId = $userIdAbsen;
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

        $queryAttendence = $response['data'] ?? [];

        $workAttendanceByDate = collect($queryAttendence)
            ->map(function ($item) {
                $row = (array) $item;

                $checkInRaw = $row['CheckIn'] ?? ($row['CHECKIN'] ?? ($row['check_in'] ?? null));
                $checkOutRaw = $row['CheckOut'] ?? ($row['CHECKOUT'] ?? ($row['check_out'] ?? null));

                if (empty($checkInRaw) || empty($checkOutRaw)) {
                    return null;
                }

                try {
                    $checkIn = Carbon::parse($checkInRaw);
                    $checkOut = Carbon::parse($checkOutRaw);
                } catch (\Throwable $th) {
                    return null;
                }

                if ($checkOut->lessThanOrEqualTo($checkIn)) {
                    return null;
                }

                $shiftEndRaw = $this->pickFirstAvailableValue($row, [
                    'Jam_Pulang',
                    'JAM_PULANG',
                    'jam_pulang',
                    'Shift_End',
                    'SHIFT_END',
                    'shift_end',
                    'Jam_Keluar',
                    'JAM_KELUAR',
                ]);

                $shiftEnd = $this->parseShiftEndForDate($shiftEndRaw, $checkIn->toDateString(), $checkIn);

                $durationTotalMinutes = $checkIn->diffInMinutes($checkOut);
                $durationFromShiftEndMinutes = $shiftEnd ? max($shiftEnd->diffInMinutes($checkOut, false), 0) : 0;

                return [
                    'date' => $checkIn->toDateString(),
                    'jam_masuk' => $checkIn->format('Y-m-d H:i:s'),
                    'jam_keluar' => $checkOut->format('Y-m-d H:i:s'),
                    'jam_pulang' => $shiftEnd ? $shiftEnd->format('Y-m-d H:i:s') : null,
                    'durasi_total_menit' => $durationTotalMinutes,
                    'durasi_dari_pulang_menit' => $durationFromShiftEndMinutes,
                    'log_count' => 2,
                ];
            })
            ->filter()
            ->keyBy('date');

        // OFF days: a date becomes selectable as long as there is at least one
        // machine log on the OFF date itself. The actual selectable log window
        // can extend into the next morning for overnight overtime cases.
        $uidRaw = trim((string) $userIdAbsen);
        $uidCandidates = collect([$uidRaw]);
        if ($uidRaw !== '' && preg_match('/^0+\d+$/', $uidRaw)) {
            $uidCandidates->push((string) intval($uidRaw));
        }
        $uidCandidates = $uidCandidates->filter(fn ($v) => $v !== null && $v !== '')->unique()->values()->all();

        $offLogsRaw = DB::table('CHECKINOUT')
            ->select('CHECKTIME', 'Hasil')
            ->where('CHECKTIME', '>=', $startDate->copy()->subHours(2))
            ->where('CHECKTIME', '<=', $endDate->copy()->addDay()->addHours(10))
            ->where(function ($q) use ($uidRaw, $uidCandidates) {
                if (! empty($uidCandidates)) {
                    $q->whereIn('USERID', $uidCandidates);
                }

                $q->orWhereRaw('LTRIM(RTRIM(CAST(USERID as varchar(50)))) = ?', [$uidRaw]);
            })
            ->orderBy('CHECKTIME', 'asc')
            ->get()
            ->map(function ($row) {
                try {
                    $time = Carbon::parse((string) $row->CHECKTIME);
                } catch (\Throwable $th) {
                    return null;
                }

                return [
                    'datetime' => $time->format('Y-m-d H:i:s'),
                    'date' => $time->toDateString(),
                    'type' => $row->Hasil,
                ];
            })
            ->filter()
            ->values();

        $statusHariMap = $this->getResolvedStatusHariMapForRange(
            $kodePerusahaan,
            $kodeKaryawan,
            $startDate->toDateString(),
            $endDate->toDateString(),
        );

        $dates = [];
        $forcedContext = $this->getForcedConversionContext($kodeKaryawan);
        $defaultJenisKonversi = (string) ($forcedContext['forced_conversion_type'] ?? 'DAY_OFF');
        $approvalStepsDayOff = $this->getApprovalFlowPreview($kodeKaryawan, 'DAY_OFF');
        $approvalStepsMaterial = $this->getApprovalFlowPreview($kodeKaryawan, 'MATERIAL');
        $approvalStepsToCuti = $this->getApprovalFlowPreview($kodeKaryawan, 'TO_CUTI');
        $calendarRelatedTransactions = $this->getCalendarRelatedTransactions(
            $kodeKaryawan,
            $startDate->toDateString(),
            $endDate->toDateString(),
        );
        $fallbackApprovalStepsByTransaction = [];

        foreach ($appliedConversions as $rows) {
            $latestApplied = $rows->first();
            $referenceNo = trim((string) ($latestApplied->No_Transaksi_Reference ?? ''));
            if ($referenceNo === '') {
                continue;
            }

            $appliedJenisKonversi = (string) ($latestApplied->Jenis_Konversi ?? 'DAY_OFF');
            $fallbackApprovalStepsByTransaction[$referenceNo] = match (
                $this->normalizeConversionType($appliedJenisKonversi)
            ) {
                'MATERIAL' => $approvalStepsMaterial,
                'TO_CUTI' => $approvalStepsToCuti,
                default => $approvalStepsDayOff,
            };
        }

        foreach ($transactions as $rows) {
            $currentTrans = $rows->first();
            $transactionNo = trim((string) ($currentTrans->No_Transaksi ?? ''));
            if ($transactionNo === '') {
                continue;
            }

            $transactionJenisKonversi = (string) ($currentTrans->Jenis_Konversi ?? 'DAY_OFF');
            $fallbackApprovalStepsByTransaction[$transactionNo] = match (
                $this->normalizeConversionType($transactionJenisKonversi)
            ) {
                'MATERIAL' => $approvalStepsMaterial,
                'TO_CUTI' => $approvalStepsToCuti,
                default => $approvalStepsDayOff,
            };
        }

        $approvalStepsByTransaction = $this->getApprovalStepsMapForTransactions($fallbackApprovalStepsByTransaction);

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->format('Y-m-d');
            $statusHari = $statusHariMap[$dateKey] ?? $this->getStatusHari($kodePerusahaan, $kodeKaryawan, $dateKey);
            $isOff = str_starts_with($statusHari, 'OFF');

            $offAttendance = null;
            if ($isOff) {
                [$offWindowStart, $offWindowEnd] = $this->getOffDateChecktimeWindow($dateKey);

                $logsInWindow = $offLogsRaw
                    ->filter(function ($row) use ($offWindowStart, $offWindowEnd) {
                        $logTime = Carbon::parse((string) ($row['datetime'] ?? ''));

                        return $logTime->betweenIncluded($offWindowStart, $offWindowEnd);
                    })
                    ->values();

                $logsOnSelectedOffDate = $logsInWindow
                    ->filter(fn ($row) => ($row['date'] ?? null) === $dateKey)
                    ->values();

                if ($logsOnSelectedOffDate->isNotEmpty()) {
                    $firstLog = $logsInWindow->first();
                    $lastLog = $logsInWindow->last();

                    if ($firstLog && $lastLog) {
                        $checkIn = Carbon::parse((string) $firstLog['datetime']);
                        $checkOut = Carbon::parse((string) $lastLog['datetime']);
                        $offAttendance = [
                            'date' => $dateKey,
                            'jam_masuk' => $checkIn->format('Y-m-d H:i:s'),
                            'jam_keluar' => $checkOut->format('Y-m-d H:i:s'),
                            'jam_pulang' => null,
                            'durasi_total_menit' => max($checkIn->diffInMinutes($checkOut, false), 0),
                            'durasi_dari_pulang_menit' => 0,
                            'log_count' => $logsInWindow->count(),
                            'off_date_log_count' => $logsOnSelectedOffDate->count(),
                        ];
                    }
                }
            }

            $attendance = $isOff ? $offAttendance : $workAttendanceByDate->get($dateKey);
            $durationTotalMinutes = $attendance ? max((int) ($attendance['durasi_total_menit'] ?? 0), 0) : 0;
            $durationFromShiftEndMinutes = $attendance
                ? max((int) ($attendance['durasi_dari_pulang_menit'] ?? 0), 0)
                : 0;
            $durationMinutes = $isOff ? $durationTotalMinutes : $durationFromShiftEndMinutes;
            $logCount = $attendance ? (int) ($attendance['log_count'] ?? 0) : 0;
            $hasAttendance = $attendance !== null;
            $hasCompleteChecktime =
                $attendance !== null && ! empty($attendance['jam_masuk']) && ! empty($attendance['jam_keluar']);
            $overtimeMinutes = $durationFromShiftEndMinutes;
            $isWithinWindow = $date->betweenIncluded($windowStart, $today);
            $isEligibleWorkday =
                ! $isOff &&
                $date->lte($today) &&
                $hasAttendance &&
                $durationMinutes >= $minimumEligibleMinutes &&
                $isWithinWindow;
            // For OFF days: selectable as long as date has passed and has attendance.
            // The overtime window restriction applies to WORK days, not OFF days.
            $canSelectLogOff =
                $isOff && $date->lte($today) && $hasAttendance && (int) ($attendance['off_date_log_count'] ?? 1) >= 1;
            $isExpired = false;

            // Only WORK days can expire. OFF days with attendance are always submittable.
            if (! $isOff && $date->lte($today)) {
                if (! $isWithinWindow && ($isEligibleWorkday || $hasCompleteChecktime)) {
                    $isExpired = true;
                }
            }

            $appliedRows = $appliedConversions->get($dateKey, collect());
            $transactionRows = $transactions->get($dateKey, collect());
            $appliedTransactionRows =
                $transactionRows instanceof Collection
                    ? $transactionRows
                        ->filter(function ($row) {
                            return strtoupper((string) ($row->flag_applied ?? '')) === 'Y';
                        })
                        ->values()
                    : collect();
            $submittedRows =
                $transactionRows instanceof Collection
                    ? $transactionRows
                        ->filter(function ($row) {
                            return strtoupper((string) ($row->flag_applied ?? '')) !== 'Y';
                        })
                        ->values()
                    : collect();
            $isApplied =
                ($appliedRows instanceof Collection && $appliedRows->isNotEmpty()) ||
                ($appliedTransactionRows instanceof Collection && $appliedTransactionRows->isNotEmpty());
            $isSubmitted = ! $isApplied && $submittedRows instanceof Collection && $submittedRows->isNotEmpty();
            $currentAppliedTrans = $appliedTransactionRows->first();
            $currentTrans = $isSubmitted ? $submittedRows->first() : ($currentAppliedTrans ?: null);

            $status = $isOff ? 'off' : 'work';
            $approvalLabel = null;
            $jenisKonversi = $isSubmitted
                ? (string) ($currentTrans->Jenis_Konversi ?? $defaultJenisKonversi)
                : $defaultJenisKonversi;
            $targetOffDate = null;
            $conversionSummary = null;
            $approvalSteps = match ($this->normalizeConversionType($jenisKonversi)) {
                'MATERIAL' => $approvalStepsMaterial,
                'TO_CUTI' => $approvalStepsToCuti,
                default => $approvalStepsDayOff,
            };

            if ($isApplied) {
                $status = 'applied';
                $approvalLabel = 'Berhasil Dikonversi';
                $latestApplied = $appliedRows->first() ?: $currentAppliedTrans;
                $jenisKonversi = (string) ($latestApplied->Jenis_Konversi ?? ($jenisKonversi ?? 'DAY_OFF'));
                if (! empty($latestApplied->Tanggal_Off)) {
                    $targetOffDate = Carbon::parse((string) $latestApplied->Tanggal_Off)->toDateString();
                } elseif (
                    $this->normalizeConversionType($jenisKonversi) === 'DAY_OFF' &&
                    ! empty($latestApplied->Target_Off)
                ) {
                    $targetOffDate = Carbon::parse((string) $latestApplied->Target_Off)->toDateString();
                } elseif (
                    $this->normalizeConversionType($jenisKonversi) === 'DAY_OFF' &&
                    ! empty($latestApplied->det_note)
                ) {
                    $targetOffDate = $this->extractTargetOffDateFromDetNote((string) $latestApplied->det_note);
                }
                $conversionSummary = $this->buildConversionSummaryLabel($jenisKonversi, $targetOffDate);
                if (! empty($latestApplied->No_Transaksi_Reference)) {
                    $approvalSteps =
                        $approvalStepsByTransaction[(string) $latestApplied->No_Transaksi_Reference] ?? $approvalSteps;
                } elseif (! empty($latestApplied->No_Transaksi)) {
                    $approvalSteps =
                        $approvalStepsByTransaction[(string) $latestApplied->No_Transaksi] ?? $approvalSteps;
                } else {
                    $approvalSteps = array_map(function ($step) {
                        $step['status'] = 'approved';
                        $step['status_label'] = 'Approved';

                        return $step;
                    }, $approvalSteps);
                }
                $approvalLabel = $conversionSummary;
            } elseif ($isSubmitted) {
                $transaksiApproval = strtoupper((string) ($currentTrans->flag_approval ?? ''));
                $status = 'submitted';
                $durationMinutes = (int) round(((float) ($currentTrans->Durasi_Jam ?? 0)) * 60);

                if ($this->normalizeConversionType($jenisKonversi) === 'DAY_OFF') {
                    if (! empty($currentTrans->Target_Off)) {
                        $targetOffDate = Carbon::parse((string) $currentTrans->Target_Off)->toDateString();
                    } elseif (! empty($currentTrans->det_note)) {
                        $targetOffDate = $this->extractTargetOffDateFromDetNote((string) $currentTrans->det_note);
                    }
                }

                $conversionSummary = $this->buildConversionSummaryLabel($jenisKonversi, $targetOffDate);
                if (! empty($currentTrans->No_Transaksi)) {
                    $approvalSteps =
                        $approvalStepsByTransaction[(string) $currentTrans->No_Transaksi] ?? $approvalSteps;
                }
                $approvalLabel =
                    $transaksiApproval === 'Y'
                        ? $conversionSummary.' - Disetujui'
                        : $conversionSummary.' - Pending';
            } elseif ($isExpired) {
                $status = 'expired';
                $approvalLabel = 'Melewati Batas Waktu';
            } elseif ($isEligibleWorkday) {
                $status = 'eligible';
                $approvalLabel = 'Siap Diajukan';
            }

            $relatedTransactions = collect($calendarRelatedTransactions[$dateKey] ?? [])
                ->reject(function ($item) {
                    // Selalu hapus KONVERSI_LEMBUR bawaan dari view agar yang ter-reject tidak muncul.
                    // Kita hanya akan menampilkan yang aktif dari logika isApplied/isSubmitted.
                    return ($item['jenis'] ?? '') === 'KONVERSI_LEMBUR';
                })
                ->values();

            if ($isApplied || $isSubmitted) {
                $currentConversionStatus = $isApplied ? 'APPROVED' : 'PENDING';
                $currentConversionStatusLabel = $isApplied ? 'Approved' : 'Pending';

                $relatedTransactions = $relatedTransactions
                    ->prepend([
                        'no_transaksi' => (string) ($currentTrans->No_Transaksi ?? ($latestApplied->No_Transaksi_Reference ?? '')),
                        'jenis' => 'KONVERSI_LEMBUR',
                        'jenis_label' => $this->normalizeTransactionJenisLabel('KONVERSI_LEMBUR'),
                        'status' => $currentConversionStatus,
                        'status_label' => $currentConversionStatusLabel,
                        'summary' => $conversionSummary ?: $this->buildConversionSummaryLabel($jenisKonversi, $targetOffDate),
                    ])
                    ->values();
            }

            $validUntil = $date->copy()->addDays($windowDays);

            $dates[] = [
                'date' => $dateKey,
                'status' => $status,
                'is_off' => $isOff,
                'is_work' => ! $isOff,
                'status_hari' => $statusHari,
                'is_applied' => $isApplied,
                'is_submitted' => $isSubmitted,
                'is_eligible' => $isEligibleWorkday,
                'can_select_log_off' => $canSelectLogOff,
                'is_expired' => $isExpired,
                'has_attendance' => $hasAttendance,
                'log_count' => $logCount,
                'duration_minutes' => $durationMinutes,
                'duration_label' => round($durationMinutes / 60, 1).' Jam',
                'overtime_minutes' => $overtimeMinutes,
                'overtime_label' => round($overtimeMinutes / 60, 1).' Jam',
                'check_in_auto' => $attendance ? $attendance['jam_masuk'] ?? null : null,
                'check_out_auto' => $attendance ? $attendance['jam_keluar'] ?? null : null,
                'time_range' => $attendance
                    ? ($isOff
                        ? Carbon::parse($attendance['jam_masuk'])->format('H:i').
                            ' - '.
                            Carbon::parse($attendance['jam_keluar'])->format('H:i')
                        : ($attendance['jam_pulang']
                            ? Carbon::parse($attendance['jam_pulang'])->format('H:i').
                                ' - '.
                                Carbon::parse($attendance['jam_keluar'])->format('H:i')
                            : Carbon::parse($attendance['jam_masuk'])->format('H:i').
                                ' - '.
                                Carbon::parse($attendance['jam_keluar'])->format('H:i')))
                    : '-',
                'valid_until' => $validUntil->format('Y-m-d'),
                'valid_until_label' => $validUntil->translatedFormat('d F Y'),
                'days_to_expire' => $today->diffInDays($validUntil, false),
                'rule_label' => $rule['label'],
                'estimated_day_off' => (float) $rule['day_off_reward'],
                'jenis_konversi' => $jenisKonversi,
                'target_off' => $targetOffDate,
                'conversion_summary' => $conversionSummary,
                'approval_label' => $approvalLabel,
                'approval_steps' => $approvalSteps,
                'related_transactions' => $relatedTransactions->all(),
            ];
        }

        return [
            'rule' => $rule,
            'config' => [
                'max_days' => $windowDays,
            ],
            'conversion_context' => $forcedContext,
            'dates' => $dates,
        ];
    }

    public function createTransaction($data, $user)
    {
        // Transaksi dibuka NANTI (tepat sebelum write). Seluruh validasi + baca berat
        // — UDF getStatusHari, TVF N_HRIS_FN_GetStatusHariRange, SP absen getAttendance('SYNC')
        // — dijalankan DI LUAR transaksi agar tidak menahan lock (akar "Login timeout").
        try {
            if (! $user || ! $user->karyawan) {
                throw new \Exception('User tidak memiliki data karyawan.');
            }

            $kodeKaryawan = (string) $user->karyawan->Kode_Karyawan;
            $this->ensureConversionAccessAllowed($kodeKaryawan);

            $meta = $this->getRequesterMeta($kodeKaryawan);
            $kodePerusahaan = $meta['kode_perusahaan'];
            $namaUser = $meta['nama'] ?: $kodeKaryawan;
            $useridWeb = $this->requireRequesterUserIdWeb($meta);
            $config = $this->getConversionConfig();
            $rule = $this->getConversionRule();
            $minimumHours = (float) $rule['minimal_hours'];
            $minimumMinutes = (int) round($minimumHours * 60);

            $forcedContext = $this->getForcedConversionContext($kodeKaryawan);
            $jenisKonversi = $this->normalizeConversionType(
                $forcedContext['forced_conversion_type'] ?? ($data['jenis_konversi'] ?? 'DAY_OFF'),
            );
            $this->ensureConversionTypeAllowed($kodeKaryawan, $jenisKonversi);
            $needsApproval = $this->resolveNeedsApproval($jenisKonversi);
            $approvalCode = $this->resolveApprovalCode($jenisKonversi);

            if ($needsApproval && $approvalCode === '') {
                throw new \Exception(
                    'Kode approval flow untuk jenis konversi '.$jenisKonversi.' belum dikonfigurasi.',
                );
            }

            $tanggalLembur = Carbon::parse((string) ($data['tanggal_lembur'] ?? ''))->toDateString();
            $checkIn = Carbon::parse((string) ($data['check_in'] ?? ''));
            $checkOut = Carbon::parse((string) ($data['check_out'] ?? ''));
            if ($checkOut->lessThanOrEqualTo($checkIn)) {
                throw new \Exception('Check-out harus lebih besar dari check-in.');
            }

            if ($checkIn->toDateString() !== $tanggalLembur) {
                throw new \Exception('Tanggal lembur harus sama dengan tanggal check-in yang dipilih.');
            }

            $durationMinutes = $checkIn->diffInMinutes($checkOut);
            $durationHours = round($durationMinutes / 60, 2);

            $today = Carbon::today();
            // dd($tanggalLembur);
            // if (Carbon::parse($tanggalLembur)->greaterThan($today)) {
            //     throw new \Exception(
            //         "Tanggal lembur tidak boleh di masa depan.",
            //     );
            // }

            $windowStart = $today->copy()->subDays(max(((int) $config['max_days']) - 1, 0));
            if (Carbon::parse($tanggalLembur)->lt($windowStart)) {
                throw new \Exception(
                    'Tanggal lembur sudah melewati batas konversi '.(int) $config['max_days'].' hari.',
                );
            }

            $tanggalOffTarget = null;
            if ($jenisKonversi === 'DAY_OFF') {
                $tanggalOffRaw = (string) ($data['tanggal_off'] ?? '');
                if (! $tanggalOffRaw) {
                    throw new \Exception('Tanggal target OFF wajib diisi untuk konversi DAY_OFF.');
                }

                $tanggalOffTarget = Carbon::parse($tanggalOffRaw)->toDateString();
                if (Carbon::parse($tanggalOffTarget)->lt($today)) {
                    throw new \Exception('Tanggal target OFF tidak boleh sebelum hari ini.');
                }

                $targetStatusHari = $this->getStatusHari($kodePerusahaan, $kodeKaryawan, $tanggalOffTarget);

                // Fallback to status-range function for consistency with
                // self-shift/off-days behavior on future dates.
                try {
                    $statusRange = DB::select('SELECT TOP 1 StatusHari FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?)', [
                        $kodePerusahaan,
                        $kodeKaryawan,
                        $tanggalOffTarget,
                        $tanggalOffTarget,
                    ]);
                    if (! empty($statusRange) && ! empty($statusRange[0]->StatusHari)) {
                        $targetStatusHari = strtoupper((string) $statusRange[0]->StatusHari);
                    }
                } catch (\Throwable $th) {
                    // keep existing value from getStatusHari()
                }

                if (str_starts_with($targetStatusHari, 'OFF')) {
                    throw new \Exception('Tanggal target OFF harus hari kerja (WORK), tidak boleh pilih hari OFF.');
                }

                $this->ensureDayOffTargetDateAvailable($kodeKaryawan, $tanggalOffTarget);
            }

            $statusHari = $this->getStatusHari($kodePerusahaan, $kodeKaryawan, $tanggalLembur);
            if (! ($statusHari === 'WORK' || str_starts_with($statusHari, 'OFF'))) {
                throw new \Exception('Status hari tidak valid untuk konversi.');
            }

            if (str_starts_with($statusHari, 'OFF')) {
                if ($durationMinutes < $minimumMinutes) {
                    throw new \Exception('Durasi aktual hari OFF kurang dari minimal '.$minimumHours.' jam.');
                }
            } else {
                $sourceReuqest = 'konversi-lembur-service-create-transaction';
                $tglMulai = $tanggalLembur;
                $tglSelesai = $tanggalLembur;
                $listUserId = $meta['user_id_absen'];
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

                $rows = $response['data'] ?? [];
                $workDayAttendance = collect(
                    // DB::select("EXEC HRIS_SP_GET_ABSEN_NEW_V3 ?, ?, ?, ?, '', ''", [
                    //     $kodePerusahaan,
                    //     $tanggalLembur,
                    //     $tanggalLembur,
                    //     $meta['user_id_absen'],
                    // ]),
                    $rows,
                )
                    ->map(function ($item) {
                        return (array) $item;
                    })
                    ->first(function ($row) use ($tanggalLembur) {
                        $checkInRaw = $row['CheckIn'] ?? ($row['CHECKIN'] ?? ($row['check_in'] ?? null));

                        if (! $checkInRaw) {
                            return false;
                        }

                        try {
                            return Carbon::parse($checkInRaw)->toDateString() === $tanggalLembur;
                        } catch (\Throwable $th) {
                            return false;
                        }
                    });

                $shiftEndRaw = is_array($workDayAttendance)
                    ? $this->pickFirstAvailableValue($workDayAttendance, [
                        'Jam_Pulang',
                        'JAM_PULANG',
                        'jam_pulang',
                        'Shift_End',
                        'SHIFT_END',
                        'shift_end',
                        'Jam_Keluar',
                        'JAM_KELUAR',
                    ])
                    : null;
                $shiftEnd = $this->parseShiftEndForDate($shiftEndRaw, $tanggalLembur, $checkIn);

                if (! $shiftEnd) {
                    throw new \Exception(
                        'Jam pulang dari SP tidak ditemukan, tidak dapat menghitung durasi lembur hari kerja.',
                    );
                }

                $durationMinutes = max($shiftEnd->diffInMinutes($checkOut, false), 0);
                $durationHours = round($durationMinutes / 60, 2);

                if ($durationMinutes < $minimumMinutes) {
                    throw new \Exception(
                        'Durasi lembur (jam pulang ke checkout) pada hari kerja kurang dari minimal '.
                            $minimumHours.
                            ' jam.',
                    );
                }
            }

            $existingActive = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Det as det')
                ->join('N_HRIS_Transaksi_Konversi_Lembur_Detail as d', 'det.Id_Detail', '=', 'd.Id')
                ->join('N_HRIS_Transaksi_Konversi_Lembur as h', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                ->where('d.Kode_Karyawan', $kodeKaryawan)
                ->whereDate('det.Tanggal_Lembur', $tanggalLembur)
                ->where(function ($q) {
                    $this->applyNotCancelledFilter($q, 'h.Status');
                })
                ->where(function ($q) {
                    $this->applyNotCancelledFilter($q, 'd.Status');
                })
                ->where(function ($q) {
                    $this->applyNotCancelledFilter($q, 'det.Status');
                })
                ->where(function ($q) {
                    $q->whereNull('d.Flag_Approval')->orWhere('d.Flag_Approval', 'Y');
                })
                ->exists();

            if ($existingActive) {
                throw new \Exception('Tanggal lembur ini sudah memiliki pengajuan aktif.');
            }

            // ===== [KONVERSI_MATERIAL_PERIODE] DIPARKIR — buka saat metode siap =====
            // Info: klaim MATERIAL untuk tanggal lembur yang sudah melewati
            // periode gaji berjalan akan dibayar pada periode berjalan (saat
            // disetujui), bukan periode tanggal lemburnya. Catat untuk audit.
            // $materialLewatPeriode = false;
            // if ($jenisKonversi === 'MATERIAL') {
            //     $periodeBerjalan = $this->payrollPeriodFor(now());
            //     $materialLewatPeriode = Carbon::parse($tanggalLembur)->startOfDay()->lt($periodeBerjalan['start']);
            //     if ($materialLewatPeriode) {
            //         Log::info('Konversi MATERIAL diajukan untuk tanggal lewat periode gaji berjalan', [
            //             'kode_karyawan' => $kodeKaryawan,
            //             'tanggal_lembur' => $tanggalLembur,
            //             'periode_bayar_start' => $periodeBerjalan['start']->toDateString(),
            //             'periode_bayar_end' => $periodeBerjalan['end']->toDateString(),
            //         ]);
            //     }
            // }
            // ===== [/KONVERSI_MATERIAL_PERIODE] =====

            // No_Transaksi via counter atomik (menggantikan LIKE + lockForUpdate scan yang
            // non-sargable & rawan duplikat saat konkuren). Di-reserve DI LUAR transaksi
            // agar kunci baris counter tak tertahan sampai commit.
            $prefix = sprintf('KL%s-%s-', date('y'), date('m'));
            $noTransaksi = app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
                $prefix,
                function () use ($prefix) {
                    $last = DB::table('N_HRIS_Transaksi_Konversi_Lembur')
                        ->where('No_Transaksi', 'like', $prefix.'%')
                        ->orderBy('No_Transaksi', 'desc')
                        ->value('No_Transaksi');
                    if (! $last) {
                        return 0;
                    }
                    $parts = explode('-', $last);

                    return intval(end($parts));
                },
                4,
            );

            // ===== Mulai TRANSAKSI: hanya membungkus write =====
            DB::beginTransaction();

            DB::table('N_HRIS_Transaksi_Konversi_Lembur')->insert([
                'No_Transaksi' => $noTransaksi,
                'Kode_Perusahaan' => $kodePerusahaan,
                'Jenis_Transaksi' => 'KONVERSI_LEMBUR',
                'Status' => null,
                'Flag_Selesai' => null,
                'Created_By' => $useridWeb,
                'Created_At' => now(),
            ]);

            $totalHari = in_array($jenisKonversi, ['DAY_OFF', 'TO_CUTI'], true)
                ? max(1, (int) round((float) $rule['day_off_reward']))
                : 0;

            $detailId = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Detail')->insertGetId([
                'Kode_Perusahaan' => $kodePerusahaan,
                'No_Transaksi' => $noTransaksi,
                'Kode_Karyawan' => $kodeKaryawan,
                'Note' => $data['alasan'] ?? null,
                'File_Path' => $data['lampiran'] ?? null,
                'Total_Hari' => $totalHari,
                'Flag_Approval' => null,
                'Status' => null,
                'Created_By' => $useridWeb,
                'Created_At' => now(),
            ]);

            DB::table('N_HRIS_Transaksi_Konversi_Lembur_Det')->insert([
                'Kode_Perusahaan' => $kodePerusahaan,
                'No_Transaksi' => $noTransaksi,
                'Id_Detail' => $detailId,
                'Tanggal_Lembur' => $tanggalLembur,
                'CheckIn_Pilih' => $checkIn->format('Y-m-d H:i:s'),
                'CheckOut_Pilih' => $checkOut->format('Y-m-d H:i:s'),
                'Durasi_Jam' => $durationHours,
                'Hari_Dikonversi' => $totalHari,
                'Jenis_Pengajuan' => 'KONVERSI_'.$jenisKonversi,
                'Needs_Approval' => $needsApproval ? 'Y' : 'T',
                'Flag_Approval' => null,
                'Flag_Applied' => null,
                'Jenis_Konversi' => $jenisKonversi,
                'Target_Off' => $jenisKonversi === 'DAY_OFF' && ! empty($tanggalOffTarget)
                        ? Carbon::parse($tanggalOffTarget)->format('Y-m-d H:i:s')
                        : null,
                'Status' => null,
                'Note' => $data['alasan'] ?? null,
                'Created_By' => $useridWeb,
                'Created_At' => now(),
            ]);

            if ($needsApproval) {
                Log::info('Memulai pembuatan approval flow konversi lembur', [
                    'no_transaksi' => $noTransaksi,
                    'kode_karyawan' => $kodeKaryawan,
                    'jenis_konversi' => $jenisKonversi,
                    'approval_code' => $approvalCode,
                ]);

                $flowResult = $this->approvalFlowService->createFlowFromApprovalConfig(
                    $noTransaksi,
                    $kodeKaryawan,
                    $approvalCode,
                );

                if (! $flowResult->success) {
                    Log::warning('Approval flow konversi lembur gagal', [
                        'no_transaksi' => $noTransaksi,
                        'kode_karyawan' => $kodeKaryawan,
                        'approval_code' => $approvalCode,
                        'error' => $flowResult->error,
                    ]);
                    throw new \Exception('Konfigurasi approval gagal: '.$flowResult->error);
                }

                if ($flowResult->insertedIds->isEmpty()) {
                    Log::warning('Approval flow konversi lembur sukses tetapi tanpa inserted request.', [
                        'no_transaksi' => $noTransaksi,
                        'approval_code' => $approvalCode,
                    ]);
                    throw new \Exception('Approval request tidak terbentuk untuk transaksi konversi lembur.');
                }

                Log::info('Approval flow konversi lembur berhasil dibuat', [
                    'no_transaksi' => $noTransaksi,
                    'approval_code' => $approvalCode,
                    'approval_steps' => $flowResult->insertedIds->count(),
                    'first_approver' => $flowResult->getFirstApproverKode(),
                    'first_is_group' => $flowResult->isGroupFirst(),
                ]);

                try {
                    $this->notifyFirstApproverForConversion($noTransaksi, $namaUser, $tanggalLembur, $flowResult);
                } catch (\Throwable $notifyEx) {
                    Log::warning('Notifikasi approver pertama konversi lembur gagal dikirim.', [
                        'no_transaksi' => $noTransaksi,
                        'error' => $notifyEx->getMessage(),
                    ]);
                }
            } else {
                Log::info('Konversi lembur direct apply tanpa approval', [
                    'no_transaksi' => $noTransaksi,
                    'kode_karyawan' => $kodeKaryawan,
                    'jenis_konversi' => $jenisKonversi,
                ]);
                $this->applyApprovedTransaction($noTransaksi, $kodeKaryawan);
            }

            DB::commit();
            // Dirty-mark absen dipindah ke worker (SP tak jalan di request thread).
            \App\Jobs\ESS\Attendance\Processing\MarkAttendanceDirtyJob::dispatch(
                $noTransaksi,
                'KONVERSI_LEMBUR_SUBMITTED',
                'KONVERSI_LEMBUR_STORE',
                3,
                $this->attendanceDirtyResolver->requestedBy(),
            );

            return [
                'status' => true,
                'message' => $needsApproval
                    ? 'Pengajuan berhasil, menunggu persetujuan.'
                    : 'Pengajuan berhasil dan langsung diterapkan.',
                'no_transaksi' => $noTransaksi,
                'detail_id' => $detailId,
                'needs_approval' => $needsApproval,
                // [KONVERSI_MATERIAL_PERIODE] DIPARKIR: 'lewat_periode' => $materialLewatPeriode,
            ];
        } catch (\Throwable $e) {
            $this->safeRollback();
            throw $e;
        }
    }

    public function applyApprovedTransaction(string $noTransaksi, ?string $approverKode = null): array
    {
        DB::beginTransaction();
        try {
            $detail = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->where(function ($q) {
                    $this->applyNotCancelledFilter($q, 'Status');
                })
                ->first();

            if (! $detail) {
                throw new \Exception('Detail transaksi konversi tidak ditemukan.');
            }

            $isCancelled = DB::table('N_HRIS_Transaksi_Konversi_Lembur')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNotNull('Status')
                ->exists();

            if ($isCancelled) {
                throw new \Exception('Transaksi konversi lembur sudah dibatalkan.');
            }

            DB::table('N_HRIS_Transaksi_Konversi_Lembur')
                ->where('No_Transaksi', $noTransaksi)
                ->where(function ($q) {
                    $this->applyNotCancelledFilter($q, 'Status');
                })
                ->update([
                    'Flag_Selesai' => 'Y',
                    'Updated_At' => now(),
                    'Updated_By' => $approverKode ?: 'SYSTEM',
                ]);

            DB::table('N_HRIS_Transaksi_Konversi_Lembur_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->where(function ($q) {
                    $this->applyNotCancelledFilter($q, 'Status');
                })
                ->update([
                    'Flag_Approval' => 'Y',
                ]);

            DB::table('N_HRIS_Transaksi_Konversi_Lembur_Det')
                ->where('No_Transaksi', $noTransaksi)
                ->where(function ($q) {
                    $this->applyNotCancelledFilter($q, 'Status');
                })
                ->update([
                    'Flag_Approval' => 'Y',
                    'Flag_Applied' => 'Y',
                ]);

            $detRows = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Det')
                ->where('No_Transaksi', $noTransaksi)
                ->where(function ($q) {
                    $this->applyNotCancelledFilter($q, 'Status');
                })
                ->get();

            foreach ($detRows as $det) {
                $tanggalLembur = Carbon::parse($det->Tanggal_Lembur)->toDateString();
                $requesterMeta = $this->getRequesterMeta((string) $detail->Kode_Karyawan);
                $createdByRequester = $this->requireRequesterUserIdWeb($requesterMeta);
                $jenisKonversi = $this->normalizeConversionType((string) ($det->Jenis_Konversi ?? 'DAY_OFF'));
                $targetOffFromColumn = null;
                if (! empty($det->Target_Off)) {
                    try {
                        $targetOffFromColumn = Carbon::parse((string) $det->Target_Off)->toDateString();
                    } catch (\Throwable $th) {
                        $targetOffFromColumn = null;
                    }
                }

                $tanggalOff =
                    $jenisKonversi === 'DAY_OFF'
                        ? ($targetOffFromColumn ?:
                        ($this->extractTargetOffDateFromDetNote((string) ($det->Note ?? '')) ?:
                        $tanggalLembur))
                        : $tanggalLembur;

                if ($jenisKonversi === 'TO_CUTI') {
                    $jumlahBayar = (int) round((float) ($det->Hari_Dikonversi ?? 0));
                    if ($jumlahBayar <= 0) {
                        $jumlahBayar = (int) round((float) ($detail->Total_Hari ?? 0));
                    }

                    $totalKonversi = max($jumlahBayar, 1);

                    // Bayar hutang FISIK dulu (baris HRIS_Buku_Cuti sisa_cuti < 0). Boleh 0
                    // bila hutang sudah lunas via jalur lain / hutang cuma efektif (cuti pending).
                    $terbayar = $this->transactionService->repayDebt(
                        (string) $detail->Kode_Karyawan,
                        $totalKonversi,
                        $noTransaksi,
                        'KONVERSI_TO_CUTI',
                        'Pembayaran hutang cuti dari konversi lembur',
                        'SYSTEM',
                    );

                    // Sisa hari lembur (termasuk saat hutang = 0) tidak boleh hangus:
                    // kreditkan sebagai tambahan jatah Cuti Tahunan.
                    $sisaKredit = max(0, $totalKonversi - (int) round((float) $terbayar));
                    if ($sisaKredit > 0) {
                        $this->transactionService->creditLeaveBalance(
                            (string) $detail->Kode_Karyawan,
                            1, // Cuti Tahunan
                            $sisaKredit,
                            $noTransaksi,
                            'KONVERSI_TO_CUTI',
                            'Kredit jatah cuti dari sisa konversi lembur',
                            'SYSTEM',
                        );
                    }

                    continue;
                }

                // ===== [KONVERSI_MATERIAL_PERIODE] DIPARKIR — buka saat metode siap =====
                // MATERIAL ditandai per klaim. SP meng-COUNT klaim aktif per
                // Tanggal_Klaim. Jika tanggal lembur masih dalam periode gaji
                // berjalan, Tanggal_Klaim = tanggal lembur asli. Jika sudah lewat
                // periode (diajukan/disetujui setelah cutoff), geser ke tanggal
                // approve (hari ini) agar tetap masuk hitungan periode berjalan
                // dan tidak hangus. Beberapa klaim boleh menumpuk di tanggal yang
                // sama karena SP menghitung COUNT, bukan TOP 1.
                // NOTE: kolom Tanggal_Klaim & Flag_Lewat_Periode belum ada di DB.
                // Saat metode diaktifkan: uncomment blok ini DAN ganti insert/update
                // di bawah memakai array_merge(..., $materialColumns).
                // $materialColumns = [];
                // if ($jenisKonversi === 'MATERIAL') {
                //     $periode = $this->payrollPeriodFor(now());
                //     $tanggalLemburCarbon = Carbon::parse($tanggalLembur)->startOfDay();
                //     $lewatPeriode = $tanggalLemburCarbon->lt($periode['start']);
                //     $tanggalKlaim = $lewatPeriode
                //         ? Carbon::today()->toDateString()
                //         : $tanggalLemburCarbon->toDateString();
                //
                //     $materialColumns = [
                //         'Tanggal_Klaim' => $tanggalKlaim,
                //         'Flag_Lewat_Periode' => $lewatPeriode ? 'Y' : 'T',
                //     ];
                // }
                // ===== [/KONVERSI_MATERIAL_PERIODE] =====

                $existing = DB::table('N_HRIS_Konversi_Lembur_Efektif')
                    ->where('Kode_Perusahaan', $detail->Kode_Perusahaan)
                    ->where('Kode_Karyawan', $detail->Kode_Karyawan)
                    ->where('No_Transaksi_Reference', $noTransaksi)
                    ->whereDate('Tanggal_Lembur_Asal', $tanggalLembur)
                    ->first();

                if ($existing) {
                    DB::table('N_HRIS_Konversi_Lembur_Efektif')
                        ->where('Id', $existing->Id)
                        ->update([
                            'Tanggal_Off' => $tanggalOff,
                            'Jenis_Konversi' => $jenisKonversi,
                            'Flag_Aktif' => 'Y',
                            'Created_By' => $createdByRequester,
                        ]);
                } else {
                    DB::table('N_HRIS_Konversi_Lembur_Efektif')->insert([
                        'Kode_Perusahaan' => $detail->Kode_Perusahaan,
                        'Kode_Karyawan' => $detail->Kode_Karyawan,
                        'No_Transaksi_Reference' => $noTransaksi,
                        'Tanggal_Off' => $tanggalOff,
                        'Tanggal_Lembur_Asal' => $tanggalLembur,
                        'Jenis_Konversi' => $jenisKonversi,
                        'Flag_Aktif' => 'Y',
                        'Created_By' => $createdByRequester,
                        'Created_At' => now(),
                    ]);
                }
            }

            DB::commit();
            // Dirty-mark via job: applyApprovedTransaction sering dipanggil NESTED (dari
            // createTransaction direct-apply & dari ApprovalService final-approve), jadi
            // markFromTransaction sinkron akan jalan sebelum commit TERLUAR = menahan lock.
            // Job (after_commit) menunggu commit terluar.
            \App\Jobs\ESS\Attendance\Processing\MarkAttendanceDirtyJob::dispatch(
                $noTransaksi,
                'KONVERSI_LEMBUR_APPROVED',
                'KONVERSI_LEMBUR_APPLY_APPROVED',
                1,
                $this->attendanceDirtyResolver->requestedBy(),
            );

            return [
                'status' => true,
                'message' => 'Persetujuan konversi lembur berhasil diterapkan.',
            ];
        } catch (\Throwable $e) {
            $this->safeRollback();
            throw $e;
        }
    }

    public function rejectTransaction(string $noTransaksi, ?string $rejectorKode = null, ?string $note = null): array
    {
        DB::beginTransaction();
        try {
            DB::table('N_HRIS_Transaksi_Konversi_Lembur')
                ->where('No_Transaksi', $noTransaksi)
                ->where(function ($q) {
                    $this->applyNotCancelledFilter($q, 'Status');
                })
                ->update([
                    'Flag_Selesai' => 'Y',
                    'Note' => $note,
                    'Updated_At' => now(),
                    'Updated_By' => $rejectorKode ?: 'SYSTEM',
                ]);

            DB::table('N_HRIS_Transaksi_Konversi_Lembur_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->where(function ($q) {
                    $this->applyNotCancelledFilter($q, 'Status');
                })
                ->update([
                    'Flag_Approval' => 'T',
                ]);

            DB::table('N_HRIS_Transaksi_Konversi_Lembur_Det')
                ->where('No_Transaksi', $noTransaksi)
                ->where(function ($q) {
                    $this->applyNotCancelledFilter($q, 'Status');
                })
                ->update([
                    'Flag_Approval' => 'T',
                    'Flag_Applied' => null,
                    'Note_Tolak' => $note ?: 'Ditolak',
                ]);

            DB::table('N_HRIS_Konversi_Lembur_Efektif')
                ->where('No_Transaksi_Reference', $noTransaksi)
                ->update(['Flag_Aktif' => 'T']);

            DB::commit();
            \App\Jobs\ESS\Attendance\Processing\MarkAttendanceDirtyJob::dispatch(
                $noTransaksi,
                'KONVERSI_LEMBUR_REJECTED',
                'KONVERSI_LEMBUR_REJECT',
                1,
                $this->attendanceDirtyResolver->requestedBy(),
            );

            return [
                'status' => true,
                'message' => 'Pengajuan konversi lembur ditolak.',
            ];
        } catch (\Throwable $th) {
            $this->safeRollback();
            throw $th;
        }
    }

    public function cancelTransaction(string $noTransaksi, string $kodeKaryawan): array
    {
        DB::beginTransaction();
        try {
            $transaksi = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->first();

            if (! $transaksi) {
                throw new \Exception('Transaksi tidak ditemukan atau bukan milik Anda.');
            }

            // Check if already approved by ANY approver
            $hasApproved = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Flag_Approval', 'Y')
                ->exists();

            if ($hasApproved) {
                throw new \Exception('Transaksi sudah disetujui, tidak dapat dibatalkan.');
            }

            DB::table('N_HRIS_Transaksi_Konversi_Lembur')
                ->where('No_Transaksi', $noTransaksi)
                ->update([
                    'Status' => 'Y',
                    'Updated_At' => now(),
                    'Updated_By' => $kodeKaryawan,
                ]);

            // DB::table("N_HRIS_Transaksi_Konversi_Lembur_Detail")
            //     ->where("No_Transaksi", $noTransaksi)
            //     ->update([
            //         "Status" => "Y",
            //         "Flag_Applied" => null,
            //     ]);

            // DB::table("N_HRIS_Transaksi_Konversi_Lembur_Det")
            //     ->where("No_Transaksi", $noTransaksi)
            //     ->update([
            //         "Status" => "Y",
            //         "Note_Tolak" => "Dibatalkan Karyawan",
            //     ]);

            DB::table('N_HRIS_Konversi_Lembur_Efektif')
                ->where('No_Transaksi_Reference', $noTransaksi)
                ->update(['Flag_Aktif' => 'T']);

            // Mark pending requests as canceled
            DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNull('Flag_Approval')
                ->update(['Status' => 'Y']);

            DB::commit();
            \App\Jobs\ESS\Attendance\Processing\MarkAttendanceDirtyJob::dispatch(
                $noTransaksi,
                'KONVERSI_LEMBUR_CANCELLED',
                'KONVERSI_LEMBUR_CANCEL',
                1,
                $this->attendanceDirtyResolver->requestedBy(),
            );

            return [
                'status' => true,
                'message' => 'Pengajuan konversi lembur berhasil dibatalkan.',
            ];
        } catch (\Throwable $th) {
            $this->safeRollback();
            throw $th;
        }
    }

    /**
     * Pembatalan oleh admin HC — boleh di status apa pun yang masih hidup
     * (menunggu, disetujui, sudah diterapkan), alasan wajib (keputusan user 13 Okt 2026).
     *
     * Mencerminkan rejectTransaction() + cancelTransaction(): header ditandai batal,
     * hasil konversi dinonaktifkan, approval yang masih menunggu ditutup, absensi
     * ditandai untuk dihitung ulang. Untuk Jadi Cuti yang sudah diterapkan, saldo
     * cuti yang dikreditkan ditarik kembali di dompet yang sama (bagian yang sudah
     * terpakai menjadi hutang) — di DALAM transaksi yang sama.
     *
     * Karyawan TIDAK dikabari lewat WhatsApp: belum ada template Meta untuk
     * "pengajuan dibatalkan". Ia melihat status Dibatalkan beserta alasannya di
     * riwayat pengajuannya (getHistoryForUser).
     *
     * @return array{status: bool, message: string, saldo_cuti: array|null}
     */
    public function adminCancelTransaction(string $noTransaksi, string $adminKode, string $alasan): array
    {
        $catatan = KonversiLemburAdminService::catatanBatal($alasan);

        [, $saldo] = DB::transaction(function () use ($noTransaksi, $adminKode, $catatan) {
            $header = DB::table('N_HRIS_Transaksi_Konversi_Lembur')
                ->where('No_Transaksi', $noTransaksi)
                ->lockForUpdate()
                ->first();
            if (! $header) {
                throw new \DomainException('Transaksi konversi lembur tidak ditemukan.');
            }
            if (in_array((string) $header->Status, ['Y', 'T'], true)) {
                throw new \DomainException('Transaksi ini sudah dibatalkan sebelumnya.');
            }

            $detail = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Detail')->where('No_Transaksi', $noTransaksi)->first();
            $ditolak = ($detail->Flag_Approval ?? null) === 'T' || DB::table('N_HRIS_Transaksi_Konversi_Lembur_Det')
                ->where('No_Transaksi', $noTransaksi)->where('Flag_Approval', 'T')->exists();
            if ($ditolak) {
                throw new \DomainException('Transaksi ini sudah ditolak; tidak ada yang perlu dibatalkan.');
            }

            $kode = (string) ($detail->Kode_Karyawan ?? '');

            // Idempoten & no-op bila belum pernah diterapkan (tidak ada kredit di jurnal).
            $saldo = $this->transactionService->balikKreditKonversiCuti($noTransaksi, $kode, $adminKode, $catatan);

            DB::table('N_HRIS_Transaksi_Konversi_Lembur')->where('No_Transaksi', $noTransaksi)->update([
                'Status' => 'Y',
                'Flag_Selesai' => 'Y',
                'Updated_At' => now(),
                'Updated_By' => $adminKode,
            ]);

            DB::table('N_HRIS_Transaksi_Konversi_Lembur_Det')->where('No_Transaksi', $noTransaksi)->update([
                'Flag_Applied' => null,
                'Note_Tolak' => $catatan,
            ]);

            DB::table('N_HRIS_Konversi_Lembur_Efektif')
                ->where('No_Transaksi_Reference', $noTransaksi)
                ->update(['Flag_Aktif' => 'T']);

            DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNull('Flag_Approval')
                ->update(['Status' => 'Y']);

            return [$kode, $saldo];
        });

        \App\Jobs\ESS\Attendance\Processing\MarkAttendanceDirtyJob::dispatch(
            $noTransaksi,
            'KONVERSI_LEMBUR_CANCELLED_ADMIN',
            'KONVERSI_LEMBUR_CANCEL_ADMIN',
            1,
            $adminKode,
        );

        return [
            'status' => true,
            'message' => 'Pengajuan konversi lembur dibatalkan.',
            'saldo_cuti' => ($saldo['jatah_ditarik'] + $saldo['hutang_dipulihkan'] + $saldo['hutang_baru']) > 0 ? $saldo : null,
        ];
    }

    private function safeRollback(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    // ===== [KONVERSI_MATERIAL_PERIODE] DIPARKIR — buka saat metode siap =====
    // Helper periode gaji + query audit klaim MATERIAL per Tanggal_Klaim.
    // Bergantung pada kolom Tanggal_Klaim & Flag_Lewat_Periode yang BELUM ada
    // di N_HRIS_Konversi_Lembur_Efektif. Jalankan DDL di
    // docs/KONVERSI_LEMBUR_MATERIAL_PERIODE_BAYAR.sql lalu uncomment blok ini.
    //
    // /**
    //  * Hitung periode gaji (cutoff) yang memuat tanggal referensi.
    //  * Mengikuti pola AttendanceDirtyResolverService::currentCutoffEndDate()
    //  * dan membaca config services.hcis.cutoff_payroll_date (default 16 -> 15).
    //  *
    //  * @return array{start: Carbon, end: Carbon}
    //  */
    // protected function payrollPeriodFor(Carbon $reference): array
    // {
    //     $startDay = max(1, (int) config('services.hcis.cutoff_payroll_date.start_day', 16));
    //     $endDay = max(1, (int) config('services.hcis.cutoff_payroll_date.end_day', 15));
    //
    //     $ref = $reference->copy()->startOfDay();
    //
    //     if ($startDay > $endDay) {
    //         // Periode lintas-bulan (mis. 16 bulan ini s/d 15 bulan depan).
    //         if ($ref->day >= $startDay) {
    //             $startMonth = $ref->copy();
    //             $endMonth = $ref->copy()->addMonthNoOverflow();
    //         } else {
    //             $startMonth = $ref->copy()->subMonthNoOverflow();
    //             $endMonth = $ref->copy();
    //         }
    //     } else {
    //         // Periode dalam bulan yang sama (mis. 1 s/d 28).
    //         $startMonth = $ref->copy();
    //         $endMonth = $ref->copy();
    //     }
    //
    //     $start = $startMonth->copy()->day(min($startDay, $startMonth->daysInMonth))->startOfDay();
    //     $end = $endMonth->copy()->day(min($endDay, $endMonth->daysInMonth))->startOfDay();
    //
    //     return ['start' => $start, 'end' => $end];
    // }
    //
    // /**
    //  * Daftar klaim MATERIAL aktif yang ditandai (Tanggal_Klaim) pada periode
    //  * gaji tertentu (default: periode yang memuat $reference / hari ini).
    //  * Dipakai untuk audit & transparansi, termasuk penanda klaim lewat-periode.
    //  */
    // public function getMaterialClaimsByPeriode(string $kodeKaryawan, ?Carbon $reference = null): Collection
    // {
    //     $periode = $this->payrollPeriodFor($reference ?: now());
    //
    //     return DB::table('N_HRIS_Konversi_Lembur_Efektif')
    //         ->where('Kode_Karyawan', $kodeKaryawan)
    //         ->where('Jenis_Konversi', 'MATERIAL')
    //         ->where('Flag_Aktif', 'Y')
    //         ->whereBetween('Tanggal_Klaim', [$periode['start']->toDateString(), $periode['end']->toDateString()])
    //         ->select(
    //             'No_Transaksi_Reference',
    //             'Tanggal_Lembur_Asal',
    //             'Tanggal_Klaim',
    //             'Flag_Lewat_Periode',
    //         )
    //         ->orderBy('Tanggal_Klaim')
    //         ->get()
    //         ->map(function ($row) {
    //             return [
    //                 'no_transaksi' => (string) ($row->No_Transaksi_Reference ?? ''),
    //                 'tanggal_lembur_asal' => ! empty($row->Tanggal_Lembur_Asal)
    //                     ? Carbon::parse((string) $row->Tanggal_Lembur_Asal)->toDateString()
    //                     : null,
    //                 'tanggal_klaim' => ! empty($row->Tanggal_Klaim)
    //                     ? Carbon::parse((string) $row->Tanggal_Klaim)->toDateString()
    //                     : null,
    //                 'lewat_periode' => strtoupper((string) ($row->Flag_Lewat_Periode ?? '')) === 'Y',
    //             ];
    //         });
    // }
    // ===== [/KONVERSI_MATERIAL_PERIODE] =====

    /**
     * Berkas mana yang benar-benar ada di GCS, ditanyakan per direktori.
     *
     * Lampiran konversi lembur disimpan di `konversi-lembur/YYYY/MM/`, jadi satu
     * halaman riwayat biasanya hanya menyentuh beberapa direktori — jauh lebih
     * sedikit daripada jumlah barisnya.
     *
     * Kegagalan mendaftar TIDAK dianggap "berkasnya tidak ada": direktori yang
     * gagal dibaca dikembalikan sebagai tak-diketahui dan barisnya jatuh ke
     * pemeriksaan tunggal. Menganggapnya tidak ada akan membuat lampiran yang
     * sebenarnya utuh menghilang dari layar hanya karena penyimpanan sedang
     * tersendat.
     *
     * @param  array<int,string>  $jalur
     * @return array<string,bool> berkunci jalur berkas
     */
    private function petaKeberadaanLampiran(array $jalur): array
    {
        if ($jalur === []) {
            return [];
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('gcs');

        $perDirektori = [];
        foreach ($jalur as $j) {
            $perDirektori[dirname($j)][] = $j;
        }

        $hasil = [];

        foreach ($perDirektori as $direktori => $isinya) {
            try {
                $terdaftar = array_flip($disk->files($direktori));
            } catch (\Throwable $e) {
                /* Tak bisa mendaftar: jatuh ke pemeriksaan satu per satu untuk
                   direktori ini saja, supaya sisanya tetap murah. */
                foreach ($isinya as $j) {
                    try {
                        $hasil[$j] = $disk->exists($j);
                    } catch (\Throwable $e2) {
                        $hasil[$j] = false;
                    }
                }

                continue;
            }

            foreach ($isinya as $j) {
                $hasil[$j] = isset($terdaftar[$j]);
            }
        }

        return $hasil;
    }

    public function getHistoryForUser(string $kodeKaryawan)
    {
        $rows = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Detail as d')
            ->join('N_HRIS_Transaksi_Konversi_Lembur as h', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('N_HRIS_Transaksi_Konversi_Lembur_Det as log', 'd.Id', '=', 'log.Id_Detail')
            ->join('N_HRIS_Transaksi_Konversi_Lembur as header', function ($join) {
                // Yang dibatalkan admin tetap tampil (berstatus Dibatalkan + alasannya):
                // karyawan perlu tahu kenapa pengajuannya hilang dari hitungan.
                $join->on('header.No_Transaksi', '=', 'd.No_Transaksi')->where(function ($w) {
                    $w->whereNull('header.Status')
                        ->orWhere('log.Note_Tolak', 'like', KonversiLemburAdminService::PENANDA_BATAL_ADMIN.'%');
                });
            })
            ->leftJoin('N_HRIS_Konversi_Lembur_Efektif as eff', function ($join) {
                $join->on('eff.No_Transaksi_Reference', '=', 'd.No_Transaksi')->where('eff.Flag_Aktif', 'Y');
            })
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->where(function ($q) {
                $q->where(function ($a) {
                    $this->applyNotCancelledFilter($a, 'h.Status');
                })->orWhere('log.Note_Tolak', 'like', KonversiLemburAdminService::PENANDA_BATAL_ADMIN.'%');
            })
            ->where(function ($q) {
                $this->applyNotCancelledFilter($q, 'd.Status');
            })
            ->where(function ($q) {
                $q->whereNull('log.Id')->orWhere(function ($q2) {
                    $this->applyNotCancelledFilter($q2, 'log.Status');
                });
            })
            ->select(
                'd.No_Transaksi',
                'd.Flag_Approval',
                'log.Flag_Applied',
                'd.Created_At',
                'd.Note',
                'd.File_Path',
                'h.Note as Header_Note',
                'h.Flag_Selesai',
                'log.Tanggal_Lembur',
                'log.Target_Off',
                'eff.Tanggal_Off as Effective_Target_Off',
                // [KONVERSI_MATERIAL_PERIODE] DIPARKIR — kolom belum ada di DB:
                // 'eff.Flag_Lewat_Periode as Flag_Lewat_Periode',
                // 'eff.Tanggal_Klaim as Tanggal_Klaim',
                'log.Durasi_Jam',
                'log.Jenis_Konversi',
                'log.Note_Tolak',
                'h.Status as Header_Status',
            )
            ->orderByDesc('d.Created_At')
            ->get();

        /* Langkah approval diambil SEKALI untuk seluruh riwayat.
           Sebelumnya getApprovalStepsForTransaction() dipanggil di dalam map()
           — satu kueri per baris. Bentuk massalnya sudah ada dan dipakai
           getCalendarStatus(); di sini tinggal dipakai juga.

           Nilai baku [] per transaksi meniru persis pemanggilan lama, yang juga
           mengoper [] sebagai fallback: transaksi tanpa baris approval tetap
           menghasilkan array kosong lewat array_replace di dalamnya. */
        $langkahPerTransaksi = $this->getApprovalStepsMapForTransactions(
            array_fill_keys(
                $rows->pluck('No_Transaksi')
                    ->map(fn ($v) => (string) $v)
                    ->filter(fn ($v) => $v !== '')
                    ->unique()
                    ->all(),
                []
            )
        );

        /* Keberadaan lampiran diperiksa per DIREKTORI, bukan per baris.
           `Storage::disk('gcs')->exists()` adalah permintaan metadata ke
           penyimpanan objek — diukur 100 ms per panggilan, dan penghitung kueri
           tak bisa melihatnya karena ia bukan SQL. Satu daftar per direktori
           menjawab pertanyaan yang sama untuk seluruh berkas di dalamnya.

           Semantiknya tetap sama persis: sebuah berkas ada bila namanya muncul
           di daftar direktorinya. Yang hilang cuma pengulangannya. */
        $adaBerkas = $this->petaKeberadaanLampiran(
            $rows->pluck('File_Path')->map(fn ($v) => trim((string) $v))->filter()->unique()->all(),
        );

        return $rows
            ->map(function ($row) use ($langkahPerTransaksi, $adaBerkas) {
                $status = 'SUBMITTED';
                $filePath = trim((string) ($row->File_Path ?? ''));
                $batalAdmin = in_array((string) ($row->Header_Status ?? ''), ['Y', 'T'], true)
                    && KonversiLemburAdminService::apakahBatalAdmin($row->Note_Tolak ?? null);
                if ($batalAdmin) {
                    $status = 'CANCELED';
                } elseif (($row->Flag_Approval ?? null) === 'T') {
                    $noteCek = strtolower((string) ($row->Note ?? ($row->Header_Note ?? '')));
                    if (str_contains($noteCek, 'dibatalkan karyawan')) {
                        $status = 'CANCELED';
                    } else {
                        $status = 'REJECTED';
                    }
                } elseif (($row->Flag_Applied ?? null) === 'Y') {
                    $status = 'APPLIED';
                } elseif (($row->Flag_Approval ?? null) === 'Y') {
                    $status = 'APPROVED';
                }

                return [
                    'Jenis_Konversi' => $this->normalizeConversionType((string) ($row->Jenis_Konversi ?? 'DAY_OFF')),
                    'No_Transaksi' => $row->No_Transaksi,
                    'Tanggal_Lembur' => $row->Tanggal_Lembur,
                    'Target_Off' => ! empty($row->Target_Off)
                        ? Carbon::parse((string) $row->Target_Off)->toDateString()
                        : (! empty($row->Effective_Target_Off)
                            ? Carbon::parse((string) $row->Effective_Target_Off)->toDateString()
                            : $this->extractTargetOffDateFromDetNote((string) ($row->Note ?? ''))),
                    'Durasi_Jam' => (float) ($row->Durasi_Jam ?? 0),
                    'Status' => $status,
                    // [KONVERSI_MATERIAL_PERIODE] DIPARKIR — kolom belum ada di DB:
                    // 'Lewat_Periode' => strtoupper((string) ($row->Flag_Lewat_Periode ?? '')) === 'Y',
                    // 'Tanggal_Klaim' => ! empty($row->Tanggal_Klaim)
                    //     ? Carbon::parse((string) $row->Tanggal_Klaim)->toDateString()
                    //     : null,
                    'Flag_Selesai' => (string) ($row->Flag_Selesai ?? ''),
                    'Lampiran' => $filePath !== '' && ! empty($adaBerkas[$filePath])
                            ? \Illuminate\Support\Facades\Storage::disk('gcs')->temporaryUrl(
                                $filePath,
                                now()->addMinutes(20),
                            )
                            : $filePath,
                    'Created_At' => $row->Created_At,
                    'Note' => $batalAdmin ? $row->Note_Tolak : ($row->Note ?: $row->Header_Note),
                    'approval_steps' => $langkahPerTransaksi[(string) ($row->No_Transaksi ?? '')] ?? [],
                ];
            })
            ->values();
    }
}
