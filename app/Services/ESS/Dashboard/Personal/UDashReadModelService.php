<?php

namespace App\Services\ESS\Dashboard\Personal;

use App\Services\ESS\Attendance\Processing\CutoffService;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApproverResolverService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UDashReadModelService
{
    protected HrisAttendanceSnapshotService $attendanceService;

    public function __construct(
        protected ApproverResolverService $approverResolverService,
        protected HrisAttendanceSnapshotService $attendanceSnapshotService,
        protected CutoffService $cutoffService,
    ) {
        $this->batasHariPengajuan = config('services.hcis.batas_approve_izin');
        $this->attendanceService = $attendanceSnapshotService;
    }

    public function getCutoffConfig(): array
    {
        try {
            $period = $this->cutoffService->getCurrentPeriod();
            $startDay = (int) ($period['Start_Day'] ?? 0);
            $endDay = (int) ($period['End_Day'] ?? 0);

            if ($startDay > 0 && $endDay > 0) {
                return [
                    'start_day' => max(1, min(31, $startDay)),
                    'end_day' => max(1, min(31, $endDay)),
                ];
            }
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->warning('CutoffService config fallback: '.$e->getMessage());
        }

        return $this->fallbackCutoffConfig();
    }

    public function getCutoffRange(?Carbon $now = null, int $offset = 0): array
    {
        $reference = $now ? $now->copy()->startOfDay() : Carbon::now()->startOfDay();
        if ($offset === 0) {
            try {
                return $this->normalizeCutoffPeriod($this->cutoffService->getCurrentPeriod($reference), $reference);
            } catch (\Throwable $e) {
                Log::channel('uDashLog')->warning('CutoffService range fallback: '.$e->getMessage());
            }
        }

        return $this->fallbackCutoffRange($reference, $offset);
    }

    public function getCutoffRangeForMonth(int $year, int $month): array
    {
        try {
            return $this->normalizeCutoffPeriod($this->cutoffService->getPeriodForStartMonth($year, $month));
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->warning('CutoffService month range fallback: '.$e->getMessage());
        }

        return $this->fallbackCutoffRangeForMonth($year, $month);
    }

    public function getCutoffPeriodKey(Carbon|string $date): string
    {
        $parsedDate = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

        return $this->getCutoffRange($parsedDate)['start_date']->format('Y-m');
    }

    public function formatCutoffLabel(Carbon $start, Carbon $end): string
    {
        return sprintf(
            '%s Periode, %d %s %d - %d %s %d',
            $this->indonesianMonthName($start),
            $start->day,
            $this->indonesianMonthName($start),
            $start->year,
            $end->day,
            $this->indonesianMonthName($end),
            $end->year,
        );
    }

    public function serializeCutoffRange(array $range): array
    {
        return [
            'start_date' => $range['start_date']->toDateString(),
            'end_date' => $range['end_date']->toDateString(),
            'start_day' => (int) $range['start_day'],
            'end_day' => (int) $range['end_day'],
            'label' => $range['label'],
            'config_name' => $range['config_name'] ?? null,
            'source' => $range['source'] ?? null,
        ];
    }

    protected function normalizeCutoffPeriod(array $period, ?Carbon $reference = null): array
    {
        $startDate = Carbon::parse($period['Start_Date'] ?? $period['start_date'] ?? null)->startOfDay();
        $endDate = Carbon::parse($period['End_Date'] ?? $period['end_date'] ?? null)->startOfDay();
        $startDay = (int) ($period['Start_Day'] ?? $period['start_day'] ?? $startDate->day);
        $endDay = (int) ($period['End_Day'] ?? $period['end_day'] ?? $endDate->day);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_day' => max(1, min(31, $startDay)),
            'end_day' => max(1, min(31, $endDay)),
            'label' => (string) ($period['Label'] ?? $period['label'] ?? $this->formatCutoffLabel($startDate, $endDate)),
            'config_name' => $period['Config_Name'] ?? $period['config_name'] ?? null,
            'source' => $period['Source'] ?? $period['source'] ?? null,
        ];
    }

    protected function fallbackCutoffConfig(): array
    {
        $startDay = (int) config('services.hcis.cutoff_payroll_date.start_day', 16);
        $endDay = (int) config('services.hcis.cutoff_payroll_date.end_day', 15);

        return [
            'start_day' => max(1, min(31, $startDay ?: 16)),
            'end_day' => max(1, min(31, $endDay ?: 15)),
        ];
    }

    protected function fallbackCutoffRange(?Carbon $now = null, int $offset = 0): array
    {
        $reference = $now ? $now->copy()->startOfDay() : Carbon::now()->startOfDay();
        $config = $this->fallbackCutoffConfig();
        $startDay = $config['start_day'];
        $endDay = $config['end_day'];

        if ($startDay <= $endDay) {
            $startDate = $this->safeCreateDate($reference->year, $reference->month, $startDay);
            $endDate = $this->safeCreateDate($reference->year, $reference->month, $endDay);

            if ($reference->lt($startDate)) {
                $base = $reference->copy()->subMonthNoOverflow();
                $startDate = $this->safeCreateDate($base->year, $base->month, $startDay);
                $endDate = $this->safeCreateDate($base->year, $base->month, $endDay);
            } elseif ($reference->gt($endDate)) {
                $base = $reference->copy()->addMonthNoOverflow();
                $startDate = $this->safeCreateDate($base->year, $base->month, $startDay);
                $endDate = $this->safeCreateDate($base->year, $base->month, $endDay);
            }
        } else {
            $startDate = $this->safeCreateDate($reference->year, $reference->month, $startDay);
            $endBase = $reference->copy()->addMonthNoOverflow();
            $endDate = $this->safeCreateDate($endBase->year, $endBase->month, $endDay);

            if ($reference->lt($startDate)) {
                $startBase = $reference->copy()->subMonthNoOverflow();
                $startDate = $this->safeCreateDate($startBase->year, $startBase->month, $startDay);
                $endDate = $this->safeCreateDate($reference->year, $reference->month, $endDay);
            }
        }

        if ($offset !== 0) {
            $startBase = $startDate->copy()->addMonthsNoOverflow($offset);
            $startDate = $this->safeCreateDate($startBase->year, $startBase->month, $startDay);

            $endBase = $startDay <= $endDay ? $startBase->copy() : $startBase->copy()->addMonthNoOverflow();
            $endDate = $this->safeCreateDate($endBase->year, $endBase->month, $endDay);
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_day' => $startDay,
            'end_day' => $endDay,
            'label' => $this->formatCutoffLabel($startDate, $endDate),
        ];
    }

    protected function fallbackCutoffRangeForMonth(int $year, int $month): array
    {
        $config = $this->fallbackCutoffConfig();
        $startDate = $this->safeCreateDate($year, $month, $config['start_day']);
        $endBase =
            $config['start_day'] <= $config['end_day'] ? $startDate->copy() : $startDate->copy()->addMonthNoOverflow();
        $endDate = $this->safeCreateDate($endBase->year, $endBase->month, $config['end_day']);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_day' => $config['start_day'],
            'end_day' => $config['end_day'],
            'label' => $this->formatCutoffLabel($startDate, $endDate),
        ];
    }

    private function safeCreateDate(int $year, int $month, int $day): Carbon
    {
        $base = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $safeDay = min(max(1, $day), $base->copy()->endOfMonth()->day);

        return $base->day($safeDay);
    }

    private function indonesianMonthName(Carbon $date): string
    {
        $monthNames = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $monthNames[$date->month] ?? $date->format('F');
    }

    public function getApproverChainFromConfig(
        string $requesterKode,
        array $configCodes = ['LEADER_SHIFT', 'LEADER_SHIFT_BACKDATE'],
    ): Collection {
        try {
            $configs = DB::table('N_HRIS_Approval_Config')
                ->whereIn('Kode_Config', $configCodes)
                ->where('Flag_Aktif', 'Y')
                ->select('Id', 'Kode_Config')
                ->get();

            if ($configs->isEmpty()) {
                return collect([]);
            }

            $details = DB::table('N_HRIS_Approval_Config_Detail as d')
                ->leftJoin('N_HRIS_Jenis_Approval as ja', 'd.Id_Jenis_Approval', '=', 'ja.Id_Jenis_Approval')
                ->whereIn('d.Id_Approval_Config', $configs->pluck('Id')->all())
                ->where('d.Flag_Aktif', 'Y')
                ->where(function ($query) {
                    $query->whereNotNull('d.Custom_Approver_Kode')->orWhere(function ($sub) {
                        $sub->whereNotNull('d.Id_Jenis_Approval')->where('ja.Flag_Aktif', 'Y');
                    });
                })
                ->select(
                    'd.Id_Approval_Config',
                    'd.Approval_Order',
                    'd.Order_Approver_Limit',
                    'd.Custom_Approver_Kode',
                    'd.Id_Jenis_Approval',
                    'ja.Source_Type',
                    'ja.Source_Reference',
                )
                ->get();

            if ($details->isEmpty()) {
                return collect([]);
            }

            $selectedDetail = $details
                ->sortByDesc(function ($detail) {
                    $limit = (int) ($detail->Order_Approver_Limit ?? 0);
                    $approvalOrder = (int) ($detail->Approval_Order ?? 0);

                    return $limit * 100000 + $approvalOrder;
                })
                ->first();

            $selectedConfigId = $selectedDetail->Id_Approval_Config;
            $selectedDetails = $details
                ->filter(fn ($detail) => (int) $detail->Id_Approval_Config === (int) $selectedConfigId)
                ->sortBy('Approval_Order')
                ->values();

            $resolvedApprovers = collect();
            foreach ($selectedDetails as $detail) {
                if (! empty($detail->Custom_Approver_Kode)) {
                    $resolvedApprovers->push(
                        (object) [
                            'kode_karyawan' => $detail->Custom_Approver_Kode,
                            'approval_order' => (int) ($detail->Approval_Order ?? 0),
                        ],
                    );

                    continue;
                }

                if (empty($detail->Id_Jenis_Approval)) {
                    continue;
                }

                $resolved = collect(
                    $this->approverResolverService->resolve(
                        (int) $detail->Id_Jenis_Approval,
                        (string) ($detail->Source_Type ?? ''),
                        $detail->Source_Reference,
                        $requesterKode,
                        ! empty($detail->Order_Approver_Limit) ? (int) $detail->Order_Approver_Limit : null,
                    ),
                )->map(function ($approver) use ($detail) {
                    $approver->approval_order = (int) ($detail->Approval_Order ?? 0);

                    return $approver;
                });

                $resolvedApprovers = $resolvedApprovers->merge($resolved);
            }

            $resolvedApprovers = $resolvedApprovers
                ->filter(function ($approver) {
                    return ! empty($approver->kode_karyawan) ||
                        strtoupper((string) ($approver->source_type ?? '')) === 'ROLE';
                })
                ->unique(function ($approver) {
                    $key =
                        $approver->kode_karyawan ??
                        ($approver->source_type ?? 'POOL').
                            ':'.
                            ($approver->source_reference ?? ($approver->group_id ?? ''));

                    return ($approver->approval_order ?? 0).'|'.$key;
                })
                ->sortBy(function ($approver) {
                    return ($approver->approval_order ?? 0).
                        '|'.
                        ($approver->kode_karyawan ?? ($approver->source_reference ?? ''));
                })
                ->values();

            if ($resolvedApprovers->isEmpty()) {
                return collect([]);
            }

            $karyawanMap = DB::table('Karyawan as k')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as v',
                    'k.Id_Level_Jabatan',
                    '=',
                    'v.Id_Level_Jabatan',
                )
                ->whereIn('k.Kode_Karyawan', $resolvedApprovers->pluck('kode_karyawan')->filter()->all())
                ->select('k.Kode_Karyawan', 'k.Nama', 'v.nama_jabatan')
                ->get()
                ->keyBy('Kode_Karyawan');

            return $resolvedApprovers->values()->map(function ($approver, $index) use ($karyawanMap) {
                $kode = $approver->kode_karyawan ?? null;
                $isRolePool = strtoupper((string) ($approver->source_type ?? '')) === 'ROLE';
                $profile = $kode ? $karyawanMap->get($kode) : null;

                return [
                    'kode' => $kode ?? ($approver->source_reference ?? null),
                    'Nama' => $profile->Nama ?? ($isRolePool ? $approver->source_reference ?? 'Role Approval' : $kode),
                    'order_flow' => $index + 1,
                    'approval_order' => (int) ($approver->approval_order ?? $index + 1),
                    'jabatan' => $profile->nama_jabatan ?? ($isRolePool ? 'Role Approval Pool' : null),
                ];
            });
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->warning('getApproverChainFromConfig failed: '.$e->getMessage());

            return collect([]);
        }
    }

    /**
     * TTL cache hitungan pending approval per approver (detik). Query di
     * baliknya union-9-view + join approval — terlalu mahal untuk jalan fresh
     * di tiap page-load homepage/uDash leader. 60s cukup segar untuk badge.
     */
    private const PENDING_APPROVAL_COUNT_CACHE_TTL = 60;

    public function countPendingApprovalsV2(string $approverKode): ?int
    {
        $cacheKey = 'udash.pending-approval-count.v2:'.$approverKode;

        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if (is_int($cached)) {
            return $cached;
        }

        $count = $this->countPendingApprovalsV2Uncached($approverKode);

        // Jangan cache null (fallback error) supaya kegagalan sementara
        // tidak terkunci selama TTL.
        if (is_int($count)) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, $count, self::PENDING_APPROVAL_COUNT_CACHE_TTL);
        }

        return $count;
    }

    private function countPendingApprovalsV2Uncached(string $approverKode): ?int
    {
        try {
            // Sumber tunggal "pending approval" (AllTransactionsReadService::pendingForApprover)
            // dipakai bersama antrian Leader Dashboard supaya kedua angka selalu selaras:
            // filter jendela umur (hanya SAKIT_IZIN/terlambat/pulangCepat), Expired_At, dan
            // approver ada di sana. Di sini cukup buang item yang di-expire via Flag_Approval='P'
            // (Is_Item_Expired) lalu hitung DISTINCT per transaksi.
            // Bungkus subquery: `Is_Item_Expired` adalah alias SELECT di pendingForApprover,
            // tak bisa dirujuk di WHERE pada level yang sama (SQL Server).
            $pending = (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)
                ->pendingForApprover($approverKode);

            return DB::query()
                ->fromSub($pending, 'p')
                ->where('Is_Item_Expired', 0)
                ->distinct()
                ->count('No_Transaksi');
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->warning('countPendingApprovalsV2 fallback to legacy: '.$e->getMessage());

            return null;
        }
    }

    /**
     * TTL cache transaksi-approved untuk kalender (detik). Method ini dipanggil dari
     * BANYAK tempat (homepage, kalender mingguan, uDash) dengan (requester,start,end)
     * yang sering identik -> tanpa cache, query union-9-view (group by + join approval)
     * jalan fresh tiap kali = penyumbang CPU utama. Cache pendek memangkas eksekusi.
     */
    private const APPROVED_CALENDAR_CACHE_TTL = 90;

    public function getApprovedTransactionsForCalendar(
        string $kodeRequester,
        string $startDate,
        string $endDate,
    ): ?Collection {
        // REALTIME: bila rentang menyentuh KEMARIN/HARI INI, jangan pakai/isi cache.
        // Transaksi approved (cuti/izin) bisa berubah real-time (atasan baru ACC hari
        // ini -> warna kalender harus langsung berubah). Rentang yang seluruhnya LAMPAU
        // (berakhir < kemarin) stabil -> boleh di-cache.
        $yesterday = \Carbon\Carbon::now('Asia/Jakarta')->subDay()->toDateString();
        $touchesRealtime = $endDate >= $yesterday;

        $cacheKey = 'udash.approved-calendar.v1:'.$kodeRequester.':'.$startDate.':'.$endDate;

        if (! $touchesRealtime) {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if ($cached instanceof Collection) {
                return $cached;
            }
        }

        $result = $this->getApprovedTransactionsForCalendarUncached($kodeRequester, $startDate, $endDate);

        // Hanya cache hasil sukses (Collection) & rentang STABIL. Jangan cache null
        // (fallback error) supaya kegagalan sementara tidak terkunci selama TTL.
        if (! $touchesRealtime && $result instanceof Collection) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, $result, self::APPROVED_CALENDAR_CACHE_TTL);
        }

        return $result;
    }

    private function getApprovedTransactionsForCalendarUncached(
        string $kodeRequester,
        string $startDate,
        string $endDate,
    ): ?Collection {
        try {
            // Tanggal di-pushdown ke hulu (forRequesterBetween) agar union 9 view tidak
            // ter-materialize penuh; hanya Dashboard_Status (computed) tersisa di hilir.
            $txSource = (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)
                ->forRequesterBetween($kodeRequester, $startDate, $endDate);

            $rows = DB::query()
                ->fromSub($txSource, 'tx')
                ->where('Dashboard_Status', 'APPROVED')
                ->select('No_Transaksi', 'Jenis', 'Deskripsi', 'Source_View', 'Tanggal_Item', 'Json_Context')
                ->get();

            if ($rows->isEmpty()) {
                return collect([]);
            }

            $noTransaksiList = $rows->pluck('No_Transaksi')->filter()->unique()->values()->all();

            $sakitIzinJenisMap = DB::table('Transaksi_Sakit_Izin_Det as det')
                ->join('Transaksi_Sakit_Izin_Detail as detail', 'detail.No_Transaksi', '=', 'det.No_Transaksi')
                ->whereIn('det.No_Transaksi', $noTransaksiList)
                ->whereBetween(DB::raw('CAST(det.Tanggal_Sakit_Izin AS DATE)'), [$startDate, $endDate])
                ->selectRaw(
                    '
                    det.No_Transaksi,
                    CAST(det.Tanggal_Sakit_Izin AS DATE) as Tanggal_Item,
                    MAX(detail.Jenis) as Jenis
                ',
                )
                ->groupBy('det.No_Transaksi', DB::raw('CAST(det.Tanggal_Sakit_Izin AS DATE)'))
                ->get()
                ->keyBy(fn ($row) => $row->No_Transaksi.'|'.Carbon::parse($row->Tanggal_Item)->format('Y-m-d'));

            $cutiJenisMap = DB::table('Transaksi_Cuti_Det as det')
                ->join('Transaksi_Cuti_Detail as detail', 'detail.No_Transaksi', '=', 'det.No_Transaksi')
                ->whereIn('det.No_Transaksi', $noTransaksiList)
                ->whereBetween(DB::raw('CAST(det.Tanggal_Cuti AS DATE)'), [$startDate, $endDate])
                ->selectRaw(
                    '
                    det.No_Transaksi,
                    CAST(det.Tanggal_Cuti AS DATE) as Tanggal_Item,
                    MAX(detail.Jenis) as Jenis
                ',
                )
                ->groupBy('det.No_Transaksi', DB::raw('CAST(det.Tanggal_Cuti AS DATE)'))
                ->get()
                ->keyBy(fn ($row) => $row->No_Transaksi.'|'.Carbon::parse($row->Tanggal_Item)->format('Y-m-d'));

            return $rows->map(function ($row) use ($sakitIzinJenisMap, $cutiJenisMap) {
                $tanggalItem = Carbon::parse($row->Tanggal_Item)->format('Y-m-d');
                $key = $row->No_Transaksi.'|'.$tanggalItem;
                $jenisUpper = strtoupper((string) ($row->Jenis ?? ''));

                $tipeTransaksi = 'Lainnya';
                $subTipe = null;

                if ($jenisUpper === 'CUTI') {
                    $tipeTransaksi = 'Cuti';
                    $subTipe = strtolower((string) ($cutiJenisMap[$key]->Jenis ?? ($row->Deskripsi ?? ''))) ?: null;
                } elseif ($jenisUpper === 'SAKIT_IZIN') {
                    $tipeTransaksi = 'Izin';
                    $subTipe = strtolower((string) ($sakitIzinJenisMap[$key]->Jenis ?? '')) ?: null;
                } elseif ($jenisUpper === 'KONVERSI_LEMBUR') {
                    $tipeTransaksi = 'Lembur';
                } elseif (strtoupper((string) $row->Source_View) === 'N_HRIS_VW_SRC_TERLAMBAT_PULANG') {
                    $tipeTransaksi = 'Terlambat_Pulang_Cepat';
                    $subTipe = strtolower((string) ($row->Jenis ?? '')) ?: null;
                }

                return (object) [
                    'No_Transaksi' => $row->No_Transaksi,
                    'Tipe_Transaksi' => $tipeTransaksi,
                    'Sub_Tipe' => $subTipe,
                    // Pertahankan Jenis & Deskripsi mentah agar konsumen (mis. ringkasan
                    // transaksi di dashboard) bisa menampilkan judul & keterangan yang jelas,
                    // bukan fallback generik "TRANSAKSI".
                    'Jenis' => $row->Jenis,
                    'Deskripsi' => $row->Deskripsi,
                    'Source_View' => $row->Source_View,
                    'Tanggal_Item' => $tanggalItem,
                    'Tanggal_Mulai' => $tanggalItem,
                    'Tanggal_Selesai' => $tanggalItem,
                ];
            });
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->warning(
                'getApprovedTransactionsForCalendar fallback to legacy: '.$e->getMessage(),
            );

            return null;
        }
    }

    /**
     * Tentukan No_Transaksi yang BENAR-BENAR selesai disetujui (semua langkah flow approved).
     *
     * View N_HRIS_VW_All_Transactions2 mengembalikan satu baris per langkah approval flow.
     * Sebuah transaksi multi-approver bisa punya baris APPROVED (langkah yang sudah ACC) dan
     * baris PENDING (langkah berikutnya) sekaligus. Jadi memfilter Dashboard_Status='APPROVED'
     * per baris menyesatkan. Transaksi baru dianggap selesai bila TIDAK ADA langkah yang
     * masih PENDING/FUTURE dan tidak ada yang REJECTED/VOID.
     *
     * @param  array<int,string>  $noTransaksiList
     * @return array<string,bool> map No_Transaksi => true (hanya yang fully approved)
     */
    public function getFullyApprovedTransactionMap(array $noTransaksiList): array
    {
        $noTransaksiList = array_values(array_filter(array_unique($noTransaksiList)));
        if (empty($noTransaksiList)) {
            return [];
        }

        // Deadlock BUKAN kegagalan tetap: SQL Server memilih satu pihak sebagai
        // korban dan menyuruh mengulang ("Rerun the transaction"). Terekam di
        // production 7 September 2026 pada kueri union 9 view ini — sebuah SELECT
        // yang kalah dari penulis karena RCSI mati di basis data ini, sehingga
        // pembaca tetap mengambil shared lock.
        //
        // Tanpa percobaan ulang, tiap deadlock jatuh ke fail-open di bawah, yang
        // menganggap SEMUA transaksi sudah disetujui penuh. Itu keputusan sadar
        // agar data tetap tampil, tapi jawaban tebakan; sekali ulang jauh lebih
        // sering menghasilkan jawaban yang benar.
        for ($percobaan = 1; ; $percobaan++) {
            try {
                return $this->petaTransaksiDisetujuiPenuh($noTransaksiList);
            } catch (\Throwable $e) {
                if ($percobaan < 2 && $this->karenaDeadlock($e)) {
                    usleep(150_000);

                    continue;
                }

                Log::channel('uDashLog')->warning(
                    'getFullyApprovedTransactionMap failed: '.$e->getMessage(),
                );

                // Saat error, jangan menyembunyikan apa pun (fail-open agar data tetap tampil).
                return array_fill_keys($noTransaksiList, true);
            }
        }
    }

    /** Korban deadlock: SQLSTATE 40001, atau galat 1205 dari driver. */
    private function karenaDeadlock(\Throwable $e): bool
    {
        $kode = $e instanceof \Illuminate\Database\QueryException ? (string) $e->getCode() : '';

        return $kode === '40001' || str_contains(strtolower($e->getMessage()), 'deadlocked');
    }

    /**
     * @param  array<int,string>  $noTransaksiList
     * @return array<string,bool>
     */
    private function petaTransaksiDisetujuiPenuh(array $noTransaksiList): array
    {
        $statuses = (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)
            ->statusByNoTransaksi($noTransaksiList)
            ->map(fn ($r) => (object) [
                'No_Transaksi' => $r->No_Transaksi,
                'Dashboard_Status' => $r->Dashboard_Status,
            ])
            ->groupBy('No_Transaksi');

        $result = [];
        foreach ($statuses as $noTransaksi => $rows) {
            $allStatuses = $rows
                ->map(fn ($r) => strtoupper((string) ($r->Dashboard_Status ?? '')))
                ->all();

            $hasBlocker = collect($allStatuses)->contains(
                fn ($s) => in_array($s, ['PENDING', 'FUTURE', 'REJECTED', 'VOID'], true),
            );
            $hasApproved = collect($allStatuses)->contains('APPROVED');

            if ($hasApproved && ! $hasBlocker) {
                $result[$noTransaksi] = true;
            }
        }

        return $result;
    }

    /**
     * Window lookback panel izin/cuti/terlambat (bulan). Widget "Status pengajuanmu"
     * di homepage uDash adalah RINGKASAN (paginasi prev/next, 4-terbaru), bukan riwayat
     * lengkap & tidak punya filter histori. Membatasi rentang men-PUSHDOWN filter tanggal
     * ke 9 view sumber (forRequesterBetween) -> tidak memindai seluruh riwayat sejak 2004.
     * Sebelumnya forRequester() polos = ~229 juta logical reads (biang CPU teratas).
     */
    private const IZIN_LOOKBACK_MONTHS = 6;

    /**
     * TTL cache hasil halaman izin (detik). Pagination prev/next berulang tidak perlu
     * mengeksekusi ulang union-9-view. getAllIzinV2 sebelumnya menjalankan groupedBase 2x
     * (count $total + $items) tiap panggilan -> cache memangkas keduanya.
     */
    private const IZIN_CACHE_TTL = 60;

    public function getAllIzinV2(string $kodeKaryawan, int $page = 1, int $perPage = 4): ?LengthAwarePaginator
    {
        $cacheKey = 'udash.izin.v2:'.$kodeKaryawan.':p'.$page.':n'.$perPage;

        return \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            self::IZIN_CACHE_TTL,
            fn () => $this->getAllIzinV2Uncached($kodeKaryawan, $page, $perPage),
        );
    }

    protected function getAllIzinV2Uncached(string $kodeKaryawan, int $page = 1, int $perPage = 4): ?LengthAwarePaginator
    {
        try {
            // Rentang dibatasi IZIN_LOOKBACK_MONTHS terakhir, di-pushdown ke 9 view sumber
            // via forRequesterBetween (bukan forRequester polos yang memindai sejak 2004).
            // $toDate +1 hari agar tepi hari ini inklusif (kolom sumber ada yang DATETIME),
            // selaras pola buildSubmissions di HomepagePersonalDashboardService.
            $fromDate = Carbon::now('Asia/Jakarta')
                ->subMonths(self::IZIN_LOOKBACK_MONTHS)->format('Y-m-d');
            $toDate = Carbon::now('Asia/Jakarta')->addDay()->format('Y-m-d');

            $txSource = (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)
                ->forRequesterBetween($kodeKaryawan, $fromDate, $toDate);

            $groupedBase = DB::query()
                ->fromSub($txSource, 'tx')
                ->whereIn('Jenis', ['CUTI', 'SAKIT_IZIN', 'TERLAMBAT'])
                ->selectRaw(
                    '
                    No_Transaksi,
                    MAX(Tanggal_Request) as tanggal_create,
                    MAX(Kode_Karyawan) as Kode_Karyawan,
                    MAX(Karyawan_Nama) as Nama,
                    MIN(Tanggal_Item) as Tanggal_Mulai,
                    MAX(Tanggal_Item) as Tanggal_Selesai,
                    MAX(Deskripsi) as Alasan,
                    MAX(Json_Context) as Json_Context,
                    MAX(Jenis) as Jenis,
                    MAX(Dashboard_Status) as Dashboard_Status
                ',
                )
                ->groupBy('No_Transaksi');

            $total = DB::query()->fromSub($groupedBase, 't')->count();
            if ($total === 0) {
                return new LengthAwarePaginator(collect([]), 0, $perPage, $page, [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]);
            }

            $items = DB::query()
                ->fromSub($groupedBase, 't')
                ->orderByDesc('tanggal_create')
                ->forPage($page, $perPage)
                ->get();

            $transactionIds = $items->pluck('No_Transaksi')->filter()->values()->all();

            $approvals = DB::table('N_HRIS_VW_Approval_Request_V2')
                ->whereIn('No_Transaksi', $transactionIds)
                ->orderBy('Request_Order_Flow', 'ASC')
                ->get()
                ->groupBy('No_Transaksi');

            $sakitIzinMap = DB::table('Transaksi_Sakit_Izin_Det')
                ->whereIn('No_Transaksi', $transactionIds)
                ->select('No_Transaksi', DB::raw("'SAKIT_IZIN' AS Jenis"))
                ->get()
                ->groupBy('No_Transaksi')
                ->map(fn ($rows) => (string) ($rows->first()->Jenis ?? 'izinFull'));

            $cutiMap = DB::table('Transaksi_Cuti_Detail')
                ->whereIn('No_Transaksi', $transactionIds)
                ->select('No_Transaksi', DB::raw("'CUTI' AS Jenis"))
                ->get()
                ->groupBy('No_Transaksi')
                ->map(fn ($rows) => (string) ($rows->first()->Jenis ?? 'cuti'));

            $terlambatMap = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
                ->whereIn('No_Transaksi', $transactionIds)
                ->select('No_Transaksi', DB::raw("'TERLAMBAT' AS Jenis"))
                ->get()
                ->groupBy('No_Transaksi')
                ->map(fn ($rows) => (string) ($rows->first()->Jenis ?? 'terlambat'));

            $formattedData = $items->map(function ($item) use ($approvals, $sakitIzinMap, $cutiMap, $terlambatMap) {
                $currApprovals = $approvals->get($item->No_Transaksi, collect());

                $maxFlow = (int) ($currApprovals->max('Request_Order_Flow') ?? 0);
                $lastAction = $currApprovals->whereNotNull('Flag_Approval')->sortByDesc('Request_Order_Flow')->first();
                $currentFlow = (int) ($lastAction->Request_Order_Flow ?? 0);

                $rejector = $currApprovals->firstWhere('Flag_Approval', 'T');
                $isFullyApproved =
                    $currApprovals->isNotEmpty() && $currApprovals->every(fn ($a) => $a->Flag_Approval === 'Y');
                $pendingApprover = $currApprovals->sortBy('Request_Order_Flow')->first(function ($row) {
                    return $row->Flag_Approval === null && ($row->Flow_State ?? null) === 'CURRENT';
                });

                $dashboardStatus = strtoupper((string) ($item->Dashboard_Status ?? 'UNKNOWN'));
                $statusText = match ($dashboardStatus) {
                    'APPROVED' => 'Selesai',
                    'REJECTED' => 'Ditolak',
                    'PENDING', 'FUTURE' => 'Diproses',
                    'VOID' => 'Ditolak',
                    default => 'NO',
                };

                if ($statusText === 'NO') {
                    if ($isFullyApproved) {
                        $statusText = 'Selesai';
                    } elseif ($rejector) {
                        $statusText = 'Ditolak';
                    } elseif (
                        $pendingApprover ||
                        $currApprovals->whereIn('Flow_State', ['CURRENT', 'FUTURE'])->isNotEmpty()
                    ) {
                        $statusText = 'Diproses';
                    }
                }

                $approverField = null;
                if ($statusText === 'Selesai') {
                    $approverField = 'DiSetujui';
                } elseif ($rejector) {
                    $approverField =
                        $rejector->Current_Approver ??
                        ($rejector->Current_Approver_Kode ?? ($rejector->Approver_Karyawan_Kode ?? null));
                } elseif ($pendingApprover) {
                    $approverField =
                        $pendingApprover->Current_Approver ??
                        ($pendingApprover->Current_Approver_Kode ?? ($pendingApprover->Approver_Karyawan_Kode ?? null));
                }

                $statusList = $currApprovals
                    ->map(function ($row) {
                        $flag = $row->Flag_Approval ?? 'N';
                        $approver =
                            $row->Current_Approver ??
                            ($row->Current_Approver_Kode ?? ($row->Approver_Karyawan_Kode ?? 'Approver'));

                        return "{$approver}: {$flag}";
                    })
                    ->implode(', ');

                $jenis = strtoupper((string) ($item->Jenis ?? ''));
                $tipeIzin = match ($jenis) {
                    'CUTI' => $cutiMap->get($item->No_Transaksi, 'cuti'),
                    'SAKIT_IZIN' => $sakitIzinMap->get($item->No_Transaksi, 'izinFull'),
                    'TERLAMBAT' => $terlambatMap->get($item->No_Transaksi, 'terlambat'),
                    default => strtolower((string) ($item->Alasan ?? 'izinFull')),
                };

                $jamMulai = null;
                $json = json_decode((string) ($item->Json_Context ?? '{}'), true) ?: [];
                if (isset($json['Jam'])) {
                    $jamMulai = $json['Jam'];
                }

                return [
                    'tanggal_create' => $item->tanggal_create,
                    'Kode_Karyawan' => $item->Kode_Karyawan,
                    'Nama' => $item->Nama,
                    'No_Transaksi' => $item->No_Transaksi,
                    'Tipe_Izin' => $tipeIzin,
                    'Tanggal_Mulai' => $item->Tanggal_Mulai,
                    'Tanggal_Selesai' => $item->Tanggal_Selesai,
                    'Alasan' => $item->Alasan,
                    'Lampiran' => null,
                    'currentFlow' => $currentFlow,
                    'MaxFlow' => $maxFlow,
                    'status' => $statusText,
                    'Approver' => $approverField,
                    'Jam_Mulai' => $jamMulai,
                    'StatusList' => $statusList,
                ];
            });

            return new LengthAwarePaginator($formattedData, $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->warning('getAllIzinV2 fallback to legacy: '.$e->getMessage());

            return null;
        }
    }

    public function getRiwayatAbsenData(
        string $kodeKaryawan,
        ?string $tanggal = null,
        int $page = 1,
        int $perPage = 7,
    ): ?LengthAwarePaginator {
        try {
            $offset = ($page - 1) * $perPage;

            $queryCount = '
                SELECT COUNT(DISTINCT CAST(a.CheckTime AS DATE)) AS total
                FROM CHECKINOUT a
                JOIN Karyawan b ON a.USERID = b.UserID_Absen
                WHERE b.Kode_Karyawan = ?
            ';
            $paramsCount = [$kodeKaryawan];

            if (! empty($tanggal)) {
                $queryCount .= ' AND CAST(a.CheckTime AS DATE) = ? ';
                $paramsCount[] = $tanggal;
            }

            $total = (int) (DB::selectOne($queryCount, $paramsCount)->total ?? 0);

            $queryData = '
                SELECT
                    CAST(a.CheckTime AS DATE) AS Tanggal,
                    COUNT(*) AS JumlahAbsensi
                FROM CHECKINOUT a
                JOIN Karyawan b ON a.USERID = b.UserID_Absen
                WHERE b.Kode_Karyawan = ?
            ';
            $paramsData = [$kodeKaryawan];

            if (! empty($tanggal)) {
                $queryData .= ' AND CAST(a.CheckTime AS DATE) = ? ';
                $paramsData[] = $tanggal;
            }

            $queryData .= '
                GROUP BY CAST(a.CheckTime AS DATE)
                ORDER BY Tanggal DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
            ';

            $paramsData[] = $offset;
            $paramsData[] = $perPage;

            $results = DB::select($queryData, $paramsData);

            return new LengthAwarePaginator(collect($results), $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->warning('getRiwayatAbsenData fallback to legacy: '.$e->getMessage());

            return null;
        }
    }

    public function getAbsenDetailData(string $kodeKaryawan, string $userIdAbsen, string $tanggal): ?array
    {
        try {
            $query = "
                SELECT
                    a.CheckTime,
                    a.SENSORID,
                    a.Serial_Number,
                    ISNULL(sn.Lokasi, '-') AS Lokasi_Absen,
                    CASE
                        WHEN a.SENSORID = '18' THEN 'ABSEN ONLINE'
                        ELSE 'ABSEN OFFICE'
                    END AS Keterangan_Absen
                FROM CHECKINOUT a
                JOIN Karyawan b ON a.USERID = b.UserID_Absen
                LEFT JOIN Absen_SN sn ON a.Serial_Number = sn.Kode_SN
                WHERE b.Kode_Karyawan = ?
                AND CAST(a.CheckTime AS DATE) = ?
                ORDER BY a.CheckTime ASC
            ";
            $results = DB::select($query, [$kodeKaryawan, $tanggal]);

            // $queryCheckInOut = 'exec HRIS_SP_GET_ABSEN_NEW_V3 ?, ?, ?, ?, ?, ?';
            // $resultCheckInOut = DB::select($queryCheckInOut, ['001', $tanggal, $tanggal, $userIdAbsen, '', '']);

            $sourceReuqest = 'uDash-service-getAbsenDetailData';
            $tglMulai = $tanggal;
            $tglSelesai = $tanggal;
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

            $resultCheckInOut = collect($rows)->map(function ($item) {
                $tanggal = $item->Tanggal ?? null;
                $jamMasuk = ! empty($tanggal) && ! empty($item->Jam_Masuk ?? null)
                    ? Carbon::parse(
                        Carbon::parse($tanggal)->format('Y-m-d').' '.Carbon::parse($item->Jam_Masuk)->format('H:i:s'),
                    )
                    : null;
                $jamKeluar = ! empty($tanggal) && ! empty($item->Jam_Keluar ?? null)
                    ? Carbon::parse(
                        Carbon::parse($tanggal)->format('Y-m-d').' '.Carbon::parse($item->Jam_Keluar)->format('H:i:s'),
                    )
                    : null;

                // Shift yang melewati tengah malam: jam keluar < jam masuk -> tambah satu hari.
                if ($jamMasuk && $jamKeluar && $jamKeluar->lt($jamMasuk)) {
                    $jamKeluar = $jamKeluar->copy()->addDay();
                }

                return [
                    'Nama_Shift' => $item->Nama_Shift ?? null,
                    'CheckIn' => $item->CheckIn ?? null,
                    'CheckOut' => $item->CheckOut ?? null,
                    'Jam_Masuk' => $jamMasuk,
                    'Jam_Keluar' => $jamKeluar,
                    'Tanggal' => $tanggal,
                ];
            });

            return [
                'data' => $results,
                'checkInOut' => $resultCheckInOut,
            ];
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->warning('getAbsenDetailData fallback to legacy: '.$e->getMessage());

            return null;
        }
    }
}
