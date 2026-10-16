<?php

namespace App\Http\Controllers\ESS\Attendance\Shift\Leadership;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Jobs\SYSTEM\Notification\WhatsApp\SendWhatsappJob;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApproverResolverService;
use App\Services\SYSTEM\Governance\Authorization\Shared\AccessContext;
use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;
use App\Services\SYSTEM\Notification\WhatsappMessageBuilder;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller untuk fitur Leader Shift Assignment
 *
 * Fitur ini memungkinkan Leader/SPV untuk mengubah shift anggota tim-nya.
 * - Backdate (tanggal < hari ini): Memerlukan approval dari atasan Leader
 * - Hari H & Future: Langsung apply tanpa approval
 */
class LeaderShiftController extends Controller
{
    /**
     * Approval type constant untuk Leader Shift requests
     * Digunakan saat create approval flows
     *
     * @var int
     */
    private const APPROVAL_TYPE_LEADER_SHIFT = 3;

    protected ShiftRequestService $shiftService;

    protected ApprovalFlowService $approvalFlowService;

    protected WhatsappLogHelper $whatsappLogHelper;

    protected FunctionalRole $role;

    protected WhatsappMessageBuilder $whatsappMessageBuilder;

    protected array $leaderConfig;

    private array $approvalConfigCache = [];

    public function __construct(
        ShiftRequestService $shiftService,
        ApprovalFlowService $approvalFlowService,
        WhatsappLogHelper $whatsappLogHelper,
        WhatsappMessageBuilder $whatsappMessageBuilder,
        FunctionalRole $role,
    ) {
        $this->shiftService = $shiftService;
        $this->whatsappLogHelper = $whatsappLogHelper;
        $this->whatsappMessageBuilder = $whatsappMessageBuilder;
        $this->approvalFlowService = $approvalFlowService;
        $this->role = $role;

        $this->leaderConfig = [
            'target_hours_per_week' => (float) config(
                'services.hcis.leader_shift_request.target_hours_per_week',
                0,
            ),
            'backdate_needs_approval' => config(
                'services.hcis.leader_shift_request.backdate_needs_approval',
                true,
            ),
            'same_day_needs_approval' => config(
                'services.hcis.leader_shift_request.same_day_needs_approval',
                true,
            ),
            'future_needs_approval' => config(
                'services.hcis.leader_shift_request.future_needs_approval',
                true,
            ),
            'backdate_max_days' => (int) config(
                'services.hcis.leader_shift_request.backdate_max_days',
                7,
            ),
            'same_days' => (bool) config(
                'services.hcis.leader_shift_request.same_days',
                true,
            ),
            'future_max_days' => (int) config(
                'services.hcis.leader_shift_request.future_max_days',
                30,
            ),
        ];

        // PHASE 3: CONFIG CACHE
        // Cache approval config untuk query optimization
        $this->approvalConfigCache = [
            'approval_types' => [
                'LEADER_SHIFT' => self::APPROVAL_TYPE_LEADER_SHIFT,
            ],
            'backdate_needs_approval' => $this->leaderConfig['backdate_needs_approval'],
            'same_day_needs_approval' => $this->leaderConfig['same_day_needs_approval'],
            'future_needs_approval' => $this->leaderConfig['future_needs_approval'],
        ];

        $this->Kode_Approval_Config = ['LEADER_SHIFT', 'LEADER_SHIFT_BACKDATE'];
    }

    /**
     * Display the Leader Shift page
     */
    public function index()
    {
        $user = Auth::user();
        $now = FormatTanggalHelper::getCurrentTime('leader-shift');
        $karyawan = $user->karyawan;

        if (! $karyawan) {
            return redirect()
                ->back()
                ->with('error', 'Anda tidak terdaftar sebagai karyawan.');
        }

        if (count($this->Kode_Approval_Config) < 1) {
            Log::channel('LeaderShiftController')->error(
                'Error getWeekSchedule: Approval config untuk Leader Shift belum diatur.',
            );

            return redirect()
                ->back()
                ->with(
                    'error',
                    'Approval config untuk Leader Shift belum diatur. Silakan hubungi admin.',
                );
        }

        $approverFlows = [];
        $approverFlowPackages = [];
        foreach ($this->Kode_Approval_Config as $kodeConfig) {
            $flowPackage = $this->approvalFlowService->getApproverFlow(
                $karyawan->Kode_Karyawan,
                $kodeConfig,
                true,
            );
            $approverFlowPackages[$kodeConfig] = $flowPackage;
            $approverFlows[$kodeConfig] =
                $flowPackage['approvers'] ?? collect();
        }

        $selectedPackage = collect($approverFlowPackages)
            ->sortByDesc(function ($flowPackage) {
                $hasSkipPolicy = ! is_null(
                    $flowPackage['policy']['skip_if_requester_level_id'] ??
                        null,
                );
                $approverCount = (
                    $flowPackage['approvers'] ?? collect()
                )->count();

                return ($hasSkipPolicy ? 100000 : 0) + $approverCount;
            })
            ->first();

        $flowTerbanyak = $selectedPackage['approvers'] ?? collect();
        $selectedPolicy = $selectedPackage['policy'] ?? null;

        // $JenisApprovalConfigPalingBanyakDariConfig = collect($approverFlows)->map(fn($flow) => count($flow))->max();
        $JenisApprovalConfigPalingBanyakDariConfig = [];
        if ($flowTerbanyak) {
            // Satu kueri untuk seluruh approver, bukan satu per baris map().
            $this->siapkanKaryawan($flowTerbanyak->pluck('kode_karyawan')->filter()->all());

            $JenisApprovalConfigPalingBanyakDariConfig = $flowTerbanyak
                ->map(function ($e) {
                    $e->Nama = $this->getKaryawanName($e->kode_karyawan);
                    $e->Jabatan = $this->getKaryawanJabatan($e->kode_karyawan);

                    return $e;
                })
                ->values();
        }
        $approver = $this->approvalFlowService->getApproverFlow(
            $karyawan->Kode_Karyawan,
            $this->Kode_Approval_Config,
        );

        $disabledDates = [];
        // dd($JenisApprovalConfigPalingBanyakDariConfig);
        // dd($this->getStats($karyawan->Kode_Karyawan));
        // Get approver info for this leader (for backdate approval)
        $approverInfo = $this->getLeaderApprover($karyawan->Kode_Karyawan);

        $hideApproverPolicySection =
            (bool) ($selectedPolicy['is_skipped_by_requester_level'] ?? false);

        return Inertia::render('ESS/Attendance/Shift/Leadership/LeaderShift', [
            'serverTime' => $now->toISOString(),
            'serverDate' => $now->format('Y-m-d'),
            'loginKaryawanKode' => $karyawan->Kode_Karyawan,
            'approverInfo' => $approverInfo,
            'initialStats' => $this->getStats($karyawan->Kode_Karyawan),
            'config' => [
                'backdate_max_days' => config(
                    'services.hcis.leader_shift_request.backdate_max_days',
                    0,
                ),
                'future_max_days' => config(
                    'services.hcis.leader_shift_request.future_max_days',
                    60,
                ),
                'same_days' => config(
                    'services.hcis.leader_shift_request.same_days',
                    true,
                ),
                'backdate_needs_approval' => config(
                    'services.hcis.leader_shift_request.backdate_needs_approval',
                    true,
                ),
                'same_day_needs_approval' => config(
                    'services.hcis.leader_shift_request.same_day_needs_approval',
                    true,
                ),
                'future_needs_approval' => config(
                    'services.hcis.leader_shift_request.future_needs_approval',
                    true,
                ),
            ],
            'disabledDates' => $disabledDates,
            'approverFlows' => $JenisApprovalConfigPalingBanyakDariConfig,
            'approverPolicy' => $selectedPolicy,
            'hideApproverPolicySection' => $hideApproverPolicySection,
        ]);
    }

    /**
     * Get team members for the leader
     */
    public function getTeam(Request $request)
    {
        try {
            $user = Auth::user();
            $karyawan = $user->karyawan;

            if (! $karyawan) {
                return ResponseHelper::error('Karyawan tidak ditemukan', 404);
            }
            $context = app(AccessContext::class);
            // dd($context);
            // dd($this->accessContext->set('admin'), $this->accessContext);
            $team = $this->shiftService->getLeaderTeamMembers(
                $karyawan->Kode_Karyawan,
                $this->role,
                $context,
            );

            return ResponseHelper::success($team, 'Data tim berhasil dimuat');
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error getTeam: '.$e->getMessage(),
            );

            return ResponseHelper::error('Gagal memuat data tim', 500);
        }
    }

    /**
     * Get week schedule for specified members (optimized bulk)
     */
    public function getWeekSchedule(Request $request)
    {
        try {
            $user = Auth::user();
            $karyawan = $user->karyawan;
            $context = app(AccessContext::class);
            $startDate = $request->input('start_date')
                ? Carbon::parse($request->input('start_date'))
                : FormatTanggalHelper::getCurrentTime(
                    'leader-shift',
                )->startOfWeek();

            // Pagination params
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 10);
            $days = (int) $request->input('days', 7); // Flexible day range

            // Get members from request OR fetch team members with pagination
            $memberKodes = $request->input('members', []);
            $search = $request->input('search', '');
            $totalMembers = 0;
            $paginatedMembers = [];

            if (empty($memberKodes)) {
                // If no specific members requested, get paginated team members
                $allMembers = $this->shiftService->getLeaderTeamMembers(
                    $karyawan->Kode_Karyawan,
                    $this->role,
                    $context,
                );
                // Apply search filter if provided
                if ($search) {
                    $searchLower = strtolower($search);
                    $allMembers = array_filter($allMembers, function ($m) use (
                        $searchLower,
                    ) {
                        return str_contains(
                            strtolower($m->Nama ?? ''),
                            $searchLower,
                        ) ||
                            str_contains(
                                strtolower($m->NIK ?? ''),
                                $searchLower,
                            );
                    });
                    $allMembers = array_values($allMembers); // Re-index
                }

                $totalMembers = count($allMembers);

                // Paginate
                $paginatedMembers = array_slice(
                    $allMembers,
                    ($page - 1) * $limit,
                    $limit,
                );
                $memberKodes = array_column($paginatedMembers, 'Kode_Karyawan');
            } else {
                $totalMembers = count($memberKodes);
                // Fetch member info for explicit member codes
                $paginatedMembers = $this->shiftService->getMemberInfoByKodes(
                    $memberKodes,
                );
            }

            // Single bulk call instead of N individual calls
            $schedules = $this->shiftService->getWeekScheduleBulk(
                $memberKodes,
                $startDate,
                $days,
            );
            $workSummary = $this->shiftService->buildWeeklyHourSummary(
                $schedules,
                $this->getWeeklyTargetHours(),
            );
            // dd($workSummary);

            return ResponseHelper::successWithPaginationV2(
                [
                    'members' => $paginatedMembers, // Member info for this page
                    'schedules' => $schedules,
                    'work_summary' => $workSummary,
                    'week_start' => $startDate->format('Y-m-d'),
                    'week_end' => $startDate
                        ->copy()
                        ->addDays($days - 1)
                        ->format('Y-m-d'),
                ],
                $page,
                $limit,
                $totalMembers,
                'Data jadwal berhasil dimuat',
            );
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error getWeekSchedule: '.$e->getMessage(),
            );

            return ResponseHelper::error('Gagal memuat jadwal', 500);
        }
    }

    /**
     * Get available shifts for a specific date
     */
    public function getShifts(Request $request)
    {
        try {
            $context = app(AccessContext::class);
            $bypassShiftRule = (bool) ($context && $context->isAdmin());
            $tanggal = $request->input('tanggal');
            $memberKode = $request->input('member');
            $memberKodes = $request->input('members', []);
            if (! is_array($memberKodes)) {
                $memberKodes = [];
            }

            if (! empty($memberKodes)) {
                $shifts = $this->shiftService->getCommonAvailableShiftsForMembers(
                    $memberKodes,
                    $tanggal,
                    $bypassShiftRule,
                );
            } elseif ($tanggal) {
                $shifts = $this->shiftService->getShiftsForDay(
                    $tanggal,
                    $memberKode,
                    $bypassShiftRule,
                );
            } else {
                $shifts = $this->shiftService->getAvailableShifts(
                    $memberKode,
                    $bypassShiftRule,
                );
            }

            return ResponseHelper::success(
                $shifts,
                'Data shift berhasil dimuat',
            );
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error getShifts: '.$e->getMessage(),
            );

            return ResponseHelper::error('Gagal memuat data shift', 500);
        }
    }

    public function shiftForDay(Request $request)
    {
        $validate = $request->validate([
            'date' => 'required|date',
            'member' => 'required|string',
            'all' => 'required',
        ]);

        try {
            $context = app(AccessContext::class);
            $bypassShiftRule = (bool) ($context && $context->isAdmin());
            $memberKode = $validate['member'];

            if (
                filter_var($request->input('all'), FILTER_VALIDATE_BOOLEAN) ===
                true
            ) {
                $shiftsList = $this->shiftService->getAvailableShifts(
                    $memberKode,
                    $bypassShiftRule,
                );
            } else {
                $shiftsList = $this->shiftService->getShiftsForDay(
                    $validate['date'],
                    $memberKode,
                    $bypassShiftRule,
                );
            }

            $currentShift = $this->shiftService->getUserShiftForDate(
                $memberKode,
                Carbon::parse($validate['date']),
            );
            $isOffDay = $this->shiftService->isOffDay(
                $memberKode,
                $validate['date'],
            );

            return ResponseHelper::success([
                'shifts' => $shiftsList,
                'current_shift' => $currentShift,
                'is_off_day' => $isOffDay,
            ]);
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error shiftForDay: '.$e->getMessage(),
            );

            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * Classify date untuk shift approval requirements
     *
     * Delegates ke ShiftRequestService untuk centralized classification logic.
     * Maintains backward compatibility dengan existing callers.
     *
     * PHASE 1: SCOPE FIX
     * Menghindari scope issue dengan $leaderKode dalam closure
     *
     * @param  string  $tanggal  Date dalam Y-m-d format
     * @param  string  $today  Current date dalam Y-m-d format
     * @param  string|null  $requesterKode  Optional requester code untuk approval engine
     * @return array Classification result dengan jenis_pengajuan, needs_approval, dll
     */
    protected function classifyDate(
        string $tanggal,
        string $today,
        ?string $requesterKode = null,
    ): array {
        $context = app(AccessContext::class);

        // Delegate ke ShiftRequestService's pure function
        return $this->shiftService->classifyDateForLeaderShift(
            $tanggal,
            $today,
            $requesterKode,
            $this->leaderConfig, // Pass controller's config,
            $context,
        );
    }

    /**
     * Pra-hitung shift-sebelumnya (Id_Shift) per (kode karyawan, tanggal) DI LUAR
     * transaksi. getUserShiftForDate menjalankan SP absen SINKRON — berat — jadi
     * WAJIB dipanggil sebelum membuka transaksi agar lock tidak ditahan selama SP.
     *
     * @param  array  $members  Array item {kode, dates:[{tanggal, ...}]} (matrix/permanen).
     * @return array Map [kodeKaryawan][tanggal] => Id_Shift|null
     */
    protected function buildPreviousShiftMap(array $members): array
    {
        /*
         * SATU panggilan untuk seluruh anggota dan seluruh tanggal.
         *
         * Bentuk lama memanggil getUserShiftForDate() sekali per pasangan
         * (anggota, tanggal), dan tiap panggilan berharga tiga kueri termasuk
         * satu eksekusi N_HRIS_SP_GET_ABSEN_WEB_WRAPPER. Pada 40 anggota x 30
         * tanggal itu 1.200 panggilan dan 3.600 kueri — terukur sekitar tujuh
         * menit untuk membangun satu peta.
         *
         * SP-nya sendiri sudah menerima daftar user dan rentang tanggal, jadi
         * yang berubah hanya cara memanggilnya. Terukur pada 3 orang x 3 hari:
         * 27 kueri / ~3 detik menjadi 1 kueri / 0,01 detik.
         */
        $kode = [];
        $tanggal = [];
        $diminta = [];

        foreach ($members as $memberItem) {
            $k = is_array($memberItem) ? ($memberItem['kode'] ?? null) : null;
            if (! $k) {
                continue;
            }

            foreach ($memberItem['dates'] ?? [] as $dateItem) {
                $t = $dateItem['tanggal'] ?? null;
                if ($t === null) {
                    continue;
                }

                $kode[$k] = true;
                $tanggal[$t] = true;
                // Bentuk tanggal APA ADANYA dipertahankan sebagai kunci peta:
                // pemanggilnya mencari dengan string yang sama seperti yang ia
                // kirim, bukan dengan bentuk yang sudah dinormalkan.
                $diminta[$k][$t] = Carbon::parse($t)->format('Y-m-d');
            }
        }

        if ($diminta === []) {
            return [];
        }

        $peta = $this->shiftService->getUserShiftsForDates(
            array_keys($kode),
            array_keys($tanggal),
        );

        $map = [];
        foreach ($diminta as $k => $pasangan) {
            foreach ($pasangan as $asli => $normal) {
                $map[$k][$asli] = $peta[$k][$normal] ?? null;
            }
        }

        return $map;
    }

    /**
     * Pra-hitung status hari (WORK/OFF/...) per (kode, tanggal) DI LUAR transaksi,
     * untuk pasangan yang di-APPLY LANGSUNG. N_HRIS_FN_GetStatusHari adalah scalar
     * UDF — hindari eksekusinya di dalam lock.
     *
     * @param  array  $pairs  Daftar [$kode, $tanggal].
     * @return array Map [kode][tanggal] => status_hari|null
     */
    protected function buildStatusHariMap(array $pairs): array
    {
        $map = [];
        foreach ($pairs as [$kode, $tanggal]) {
            if (! $kode || $tanggal === null || isset($map[$kode][$tanggal])) {
                continue;
            }
            $map[$kode][$tanggal] = $this->shiftService->getStatusHari($kode, $tanggal);
        }

        return $map;
    }

    /**
     * Submit shift change (sementara/permanen) for members
     * Creates 3-tier structure: 1 Header → N Details → M Dets
     * ALL dates go into ONE transaction; dates that don't need approval are applied directly.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function submit(Request $request)
    {
        $request->validate([
            'members' => 'required|array|min:1',
            'members.*' => 'required',
            'dates' => 'nullable|array',
            'dates.*.tanggal' => 'required_with:dates|date',
            'dates.*.id_shift' => 'nullable|integer',
            'jenis' => 'required|in:SEMENTARA,PERMANEN',
            'alasan' => 'nullable|string|max:500',
        ]);
        $context = app(AccessContext::class);
        $modeInsert = $request->input('jenis');

        $user = Auth::user();
        $karyawan = $user->karyawan;

        if (! $karyawan) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 404);
        }

        $leaderKode = $karyawan->Kode_Karyawan;
        $members = $request->input('members', []);
        $dates = $request->input('dates', []);
        $jenis = $request->input('jenis');
        $alasan = $request->input('alasan');
        $overwriteSementara = (bool) $request->input(
            'overwrite_sementara',
            false,
        );
        $alasan = $alasan === null ? '' : $alasan;

        $hasMemberDateMatrix =
            $jenis === 'SEMENTARA' &&
            ! empty($members) &&
            is_array($members[0]) &&
            array_key_exists('dates', $members[0]);

        if ($jenis === 'PERMANEN') {
            $dates = array_slice($dates, 0, 1);
            foreach ($members as $memberItem) {
                if (! is_array($memberItem) || empty($memberItem['id_shift'])) {
                    return ResponseHelper::error(
                        'Shift permanen wajib diisi per karyawan.',
                        422,
                    );
                }
            }
        } elseif ($hasMemberDateMatrix) {
            foreach ($members as $memberItem) {
                if (! is_array($memberItem) || empty($memberItem['kode'])) {
                    return ResponseHelper::error(
                        'Data karyawan tidak valid untuk shift sementara.',
                        422,
                    );
                }
                $memberDates = $memberItem['dates'] ?? [];
                if (! is_array($memberDates) || empty($memberDates)) {
                    return ResponseHelper::error(
                        'Tanggal shift sementara wajib diisi per karyawan.',
                        422,
                    );
                }
                foreach ($memberDates as $dateItem) {
                    if (
                        empty($dateItem['tanggal']) ||
                        empty($dateItem['id_shift'])
                    ) {
                        return ResponseHelper::error(
                            'Shift sementara wajib diisi untuk setiap tanggal.',
                            422,
                        );
                    }
                }
            }
        } else {
            if (empty($dates)) {
                return ResponseHelper::error(
                    'Tanggal shift sementara wajib diisi.',
                    422,
                );
            }
            foreach ($dates as $dateItem) {
                if (empty($dateItem['id_shift'])) {
                    return ResponseHelper::error(
                        'Shift sementara wajib diisi untuk setiap tanggal.',
                        422,
                    );
                }
            }
        }

        $shiftAssignments = [];
        if ($jenis === 'PERMANEN') {
            $tanggalPermanen = $dates[0]['tanggal'] ?? null;
            foreach ($members as $memberItem) {
                if (is_array($memberItem)) {
                    $shiftAssignments[] = [
                        'kode_karyawan' => $memberItem['kode'] ?? null,
                        'tanggal' => $tanggalPermanen,
                        'id_shift' => $memberItem['id_shift'] ?? null,
                    ];
                }
            }
        } elseif ($hasMemberDateMatrix) {
            foreach ($members as $memberItem) {
                $kode = is_array($memberItem)
                    ? $memberItem['kode'] ?? null
                    : null;
                foreach ($memberItem['dates'] ?? [] as $dateItem) {
                    $shiftAssignments[] = [
                        'kode_karyawan' => $kode,
                        'tanggal' => $dateItem['tanggal'] ?? null,
                        'id_shift' => $dateItem['id_shift'] ?? null,
                    ];
                }
            }
        } else {
            foreach ($members as $memberKode) {
                foreach ($dates as $dateItem) {
                    $shiftAssignments[] = [
                        'kode_karyawan' => $memberKode,
                        'tanggal' => $dateItem['tanggal'] ?? null,
                        'id_shift' => $dateItem['id_shift'] ?? null,
                    ];
                }
            }
        }

        if (! ($context && $context->isAdmin())) {
            $ruleViolations = $this->shiftService->validateShiftAssignmentsAgainstRules(
                $shiftAssignments,
            );
            if (! empty($ruleViolations)) {
                $first = $ruleViolations[0];

                return ResponseHelper::error(
                    "Shift {$first['id_shift']} tidak tersedia untuk {$first['kode_karyawan']} pada tanggal {$first['tanggal']} (sesuai rule shifting).",
                    422,
                );
            }
        }

        // PHASE 3: PERFORMANCE LOGGING
        // Track metrics untuk monitoring dan troubleshooting
        $startTime = microtime(true);

        try {
            $now = FormatTanggalHelper::getCurrentTime('leader-shift');
            $today = $now->format('Y-m-d');

            $results = [
                'direct_applied' => [],
                'needs_approval' => [],
                'needs_approval_count' => 0,
            ];

            // PURE-WRITES: klasifikasi (baca rule/config), reserve nomor, dan build+kirim
            // WA dilakukan DI LUAR transaksi. Tiap cabang membuka transaksi HANYA untuk
            // membungkus write lalu commit secepatnya → lock cepat lepas (bukan ditahan
            // selama pembacaan/HTTP). Ini akar perbaikan "Login timeout expired" jalur shift.
            if ($jenis === 'SEMENTARA' && $hasMemberDateMatrix) {
                // Per-member date matrix: each member has their own dates with shifts
                $directApplyMembers = []; // Dates that DON'T need approval → apply directly
                $validMembers = []; // Members with classified dates for transaction
                $hasApprovalNeeded = false;

                foreach ($members as $memberItem) {
                    $memberKode = $memberItem['kode'] ?? null;
                    $memberDates = $memberItem['dates'] ?? [];
                    if (! $memberKode || empty($memberDates)) {
                        continue;
                    }

                    // Classify each date using config
                    $classifiedDates = [];
                    foreach ($memberDates as $dateItem) {
                        $classification = $this->classifyDate(
                            $dateItem['tanggal'],
                            $today,
                            $leaderKode,
                        );
                        $classifiedDates[] = array_merge(
                            $dateItem,
                            $classification,
                        );

                        if (! $classification['needs_approval']) {
                            // This date can be applied directly
                            if (! isset($directApplyMembers[$memberKode])) {
                                $directApplyMembers[$memberKode] = [];
                            }
                            $directApplyMembers[$memberKode][] = $dateItem;
                        } else {
                            $hasApprovalNeeded = true;
                        }
                    }

                    $validMembers[] = [
                        'kode' => $memberKode,
                        'dates' => $classifiedDates, // Now includes jenis_pengajuan + needs_approval
                    ];
                }

                // Create ONE transaction for all members with per-member dates
                if (! empty($validMembers)) {
                    // Reserve nomor DI LUAR transaksi (counter atomik; kunci baris lepas
                    // seketika — tak menserialisasi submit lain).
                    $noTransaksi = $this->shiftService->reserveNoTransaksi();

                    // Pra-hitung shift-sebelumnya DI LUAR transaksi: getUserShiftForDate
                    // menjalankan SP absen sinkron (berat) per member-tanggal — jangan di
                    // dalam lock. Hasilnya di-map lalu dipakai createLeaderShiftRequest.
                    $previousShiftMap = $this->buildPreviousShiftMap($validMembers);

                    // Pra-hitung status hari (UDF) untuk pasangan yang di-apply langsung.
                    $statusPairs = [];
                    foreach ($directApplyMembers as $mKode => $dItems) {
                        foreach ($dItems as $dItem) {
                            $statusPairs[] = [$mKode, $dItem['tanggal'] ?? null];
                        }
                    }
                    $statusHariMap = $this->buildStatusHariMap($statusPairs);

                    // Transaksi HANYA membungkus write.
                    DB::beginTransaction();

                    $this->shiftService->createLeaderShiftRequest(
                        $leaderKode,
                        $validMembers,
                        [], // dates not used in matrix mode
                        $jenis,
                        $alasan,
                        true, // isMemberDateMatrix
                        $context,
                        $overwriteSementara,
                        $noTransaksi, // pre-reserved
                        $previousShiftMap, // pra-hitung di luar tx
                    );

                    // If NO dates need approval, mark entire transaction as completed
                    if (! $hasApprovalNeeded) {
                        DB::table('N_HRIS_Transaksi_Shift')
                            ->where('No_Transaksi', $noTransaksi)
                            ->update([
                                'Flag_Selesai' => 'Y',
                            ]);
                    }

                    // Apply dates that don't need approval directly (reuse existing transaction)
                    // Satu panggilan per KARYAWAN, bukan per pasangan karyawan×tanggal:
                    // pencarian Id_Detail dan penandaan Det tidak bergantung pada
                    // tanggal, jadi mengulangnya tiap tanggal hanya menambah bolak-balik
                    // jaringan di dalam transaksi yang sedang menahan lock.
                    foreach (
                        $directApplyMembers as $memberKode => $directDates
                    ) {
                        $this->shiftService->applyShiftDirectBatchForExistingTransaction(
                            $noTransaksi,
                            $memberKode,
                            $directDates,
                            $jenis,
                            null,
                            $overwriteSementara,
                            $statusHariMap[$memberKode] ?? [],
                        );

                        foreach ($directDates as $dateItem) {
                            $results['direct_applied'][] = [
                                'member' => $memberKode,
                                'tanggal' => $dateItem['tanggal'],
                            ];
                        }
                    }

                    // Semua write selesai → commit sebelum I/O (baca nama/HP/shift + WA).
                    DB::commit();

                    // WA build+kirim SETELAH commit: pembacaan (nama/HP/shift) tidak lagi
                    // menahan lock, dan WA (sendAndLog → SendWhatsappJob afterCommit) async.
                    // Dibungkus try/catch: shift SUDAH commit, jadi kegagalan WA tak boleh
                    // mengubah respons sukses jadi error (jangan masuk catch utama).
                    try {
                        $this->siapkanKaryawan(array_keys($directApplyMembers));

                        foreach ($directApplyMembers as $kode => $dateItems) {
                            $tanggalArr = $this->normalizeDateStrings($dateItems);
                            if (empty($tanggalArr)) {
                                continue;
                            }

                            $namaKaryawan = $this->getKaryawanName($kode);
                            $hpKaryawan = $this->getKaryawanHp($kode);
                            if (! $namaKaryawan || ! $hpKaryawan) {
                                continue;
                            }

                            $shiftIds = collect($dateItems)
                                ->pluck('id_shift')
                                ->filter()
                                ->unique()
                                ->values()
                                ->all();

                            $shiftNames = [];
                            if (! empty($shiftIds)) {
                                $shiftNames = DB::table('HRIS_Shift_Kerja')
                                    ->whereIn('ID_Shift', $shiftIds)
                                    ->pluck('Nama')
                                    ->filter()
                                    ->unique()
                                    ->values()
                                    ->all();
                            }

                            $shiftLabel = ! empty($shiftNames)
                                ? implode(', ', $shiftNames)
                                : '-';

                            $tanggalFormatted =
                                $modeInsert === 'PERMANEN'
                                    ? FormatTanggalHelper::formatTanggalMultiple(
                                        $tanggalArr,
                                    ).' s/d Seterusnya'
                                    : FormatTanggalHelper::formatTanggalMultiple(
                                        $tanggalArr,
                                    );

                            $pesan = $this->whatsappMessageBuilder->Notif_Karyawan_Perubahan_Shift(
                                $hpKaryawan,
                                $namaKaryawan,
                                $modeInsert,
                                $shiftLabel,
                                $karyawan->Nama ?? '',
                                $tanggalFormatted,
                            );

                            $this->whatsappLogHelper->sendAndLog(
                                $kode,
                                $namaKaryawan,
                                $hpKaryawan,
                                $noTransaksi,
                                $pesan,
                            );
                        }
                    } catch (\Throwable $waErr) {
                        Log::channel('LeaderShiftController')->warning(
                            'WA notifikasi karyawan gagal (non-fatal, shift sudah tersimpan)',
                            [
                                'no_transaksi' => $noTransaksi,
                                'error' => $waErr->getMessage(),
                            ],
                        );
                    }

                    $approvalDateCount = collect($validMembers)->sum(
                        fn ($member) => collect($member['dates'] ?? [])->where('needs_approval', true)->count(),
                    );
                    if ($approvalDateCount > 0) {
                        $results['needs_approval'] = [
                            'no_transaksi' => $noTransaksi,
                            'member_count' => count($validMembers),
                            'date_count' => $approvalDateCount,
                        ];
                        $results['needs_approval_count'] = $approvalDateCount;
                    }
                }
            } else {
                // SEMENTARA with flat members array OR PERMANEN (both old pattern)
                // Refactor to: 1 transaction for all members, like SelfShift

                Log::channel('LeaderShiftController')->info(
                    'Processing non-matrix path',
                    [
                        'jenis' => $jenis,
                        'hasMemberDateMatrix' => $hasMemberDateMatrix,
                        'member_count' => count($members),
                        'date_count' => count($dates),
                    ],
                );

                if ($jenis === 'PERMANEN') {
                    Log::channel('LeaderShiftController')->info(
                        'PERMANEN processing started',
                        [
                            'members' => $members,
                            'dates' => $dates,
                        ],
                    );

                    // PERMANEN: members array has {kode, id_shift} structure
                    // dates array has 1 item {tanggal}
                    $tanggalPermanen = $dates[0]['tanggal'] ?? null;
                    if (! $tanggalPermanen) {
                        throw new \Exception(
                            'Tanggal permanen tidak ditemukan',
                        );
                    }

                    // Classify the permanen date using config
                    $classification = $this->classifyDate(
                        $tanggalPermanen,
                        $today,
                        $leaderKode,
                    );

                    // Build per-member dates: each member gets their own id_shift
                    $permanenMembers = [];

                    foreach ($members as $memberItem) {
                        if (! is_array($memberItem)) {
                            continue;
                        }
                        $memberKode = $memberItem['kode'] ?? null;
                        $idShift = $memberItem['id_shift'] ?? null;

                        if (! $memberKode || ! $idShift) {
                            continue;
                        }

                        $permanenMembers[] = [
                            'kode' => $memberKode,
                            'dates' => [
                                [
                                    'tanggal' => $tanggalPermanen,
                                    'id_shift' => $idShift,
                                    'jenis_pengajuan' => $classification['jenis_pengajuan'],
                                    'needs_approval' => $classification['needs_approval'],
                                    'decision_notes' => $classification['decision_notes'] ??
                                        null,
                                ],
                            ],
                        ];
                    }

                    Log::channel('LeaderShiftController')->info(
                        'PERMANEN collected members',
                        [
                            'member_count' => count($permanenMembers),
                            'tanggal_permanen' => $tanggalPermanen,
                            'classification' => $classification,
                        ],
                    );

                    // Create ONE transaction with per-member shift assignments
                    if (! empty($permanenMembers)) {
                        Log::channel('LeaderShiftController')->info(
                            'Creating PERMANEN transaction',
                            [
                                'member_count' => count($permanenMembers),
                            ],
                        );

                        // Reserve nomor DI LUAR transaksi, lalu buka transaksi hanya untuk write.
                        $noTransaksi = $this->shiftService->reserveNoTransaksi();

                        // Pra-hitung shift-sebelumnya (SP absen) DI LUAR transaksi.
                        $previousShiftMap = $this->buildPreviousShiftMap($permanenMembers);

                        // Pra-hitung status hari (UDF) hanya bila akan di-apply langsung.
                        $statusHariMap = [];
                        if (! $classification['needs_approval']) {
                            $statusPairs = [];
                            foreach ($permanenMembers as $memberItem) {
                                $statusPairs[] = [$memberItem['kode'], $tanggalPermanen];
                            }
                            $statusHariMap = $this->buildStatusHariMap($statusPairs);
                        }

                        DB::beginTransaction();

                        $this->shiftService->createLeaderShiftRequest(
                            $leaderKode,
                            $permanenMembers,
                            [], // dates not used in matrix mode
                            $jenis,
                            $alasan,
                            true, // isMemberDateMatrix
                            $context,
                            $overwriteSementara,
                            $noTransaksi, // pre-reserved
                            $previousShiftMap, // pra-hitung di luar tx
                        );

                        Log::channel('LeaderShiftController')->info(
                            'PERMANEN transaction created',
                            [
                                'no_transaksi' => $noTransaksi,
                            ],
                        );

                        // If this date doesn't need approval, mark as completed and apply directly
                        if (! $classification['needs_approval']) {
                            DB::table('N_HRIS_Transaksi_Shift')
                                ->where('No_Transaksi', $noTransaksi)
                                ->update([
                                    'Flag_Selesai' => 'Y',
                                    'Updated_At' => Carbon::now(),
                                ]);

                            // Apply each member's shift directly (reuse existing transaction)
                            foreach ($permanenMembers as $memberItem) {
                                $this->shiftService->applyShiftDirectForExistingTransaction(
                                    $noTransaksi,
                                    $memberItem['kode'],
                                    $tanggalPermanen,
                                    $memberItem['dates'][0]['id_shift'],
                                    $jenis,
                                    null,
                                    $overwriteSementara,
                                    $statusHariMap[$memberItem['kode']][$tanggalPermanen] ?? null,
                                );
                                $results['direct_applied'][] = [
                                    'member' => $memberItem['kode'],
                                    'tanggal' => $tanggalPermanen,
                                ];
                            }
                        }

                        // Semua write selesai → commit (tak ada I/O/WA di cabang ini).
                        DB::commit();

                        if ($classification['needs_approval']) {
                            $results['needs_approval'] = [
                                'no_transaksi' => $noTransaksi,
                                'member_count' => count($permanenMembers),
                                'date_count' => 1,
                            ];
                            $results['needs_approval_count'] = count(
                                $permanenMembers,
                            );
                        }
                    }
                } else {
                    // SEMENTARA with flat members array (old pattern - still supported)
                    // Classify dates using config
                    $approvalDates = [];
                    $directApplyDates = [];
                    $classifiedDates = [];

                    foreach ($dates as $dateItem) {
                        $classification = $this->classifyDate(
                            $dateItem['tanggal'],
                            $today,
                            $leaderKode,
                        );
                        $classifiedDate = array_merge(
                            $dateItem,
                            $classification,
                        );
                        $classifiedDates[] = $classifiedDate;

                        if ($classification['needs_approval']) {
                            $approvalDates[] = $classifiedDate;
                        } else {
                            $directApplyDates[] = $dateItem;
                        }
                    }

                    // Transaksi hanya membungkus write (klasifikasi sudah di luar).
                    DB::beginTransaction();

                    $noTransaksi = $this->shiftService->createLeaderShiftRequest(
                        $leaderKode,
                        $members,
                        $classifiedDates, // Now includes jenis_pengajuan + needs_approval per date
                        $jenis,
                        $alasan,
                        $context,
                        $overwriteSementara,
                    );

                    // If NO dates need approval, mark entire transaction as completed
                    if (empty($approvalDates)) {
                        DB::table('N_HRIS_Transaksi_Shift')
                            ->where('No_Transaksi', $noTransaksi)
                            ->update([
                                'Flag_Selesai' => 'Y',
                            ]);
                    }

                    // Apply dates that don't need approval directly (reuse existing transaction)
                    if (! empty($directApplyDates)) {
                        // Satu panggilan per KARYAWAN — lihat catatan pada jalur matriks
                        // di atas; alasannya sama.
                        foreach ($members as $memberKode) {
                            $this->shiftService->applyShiftDirectBatchForExistingTransaction(
                                $noTransaksi,
                                $memberKode,
                                $directApplyDates,
                                $jenis,
                                null,
                                $overwriteSementara,
                            );

                            foreach ($directApplyDates as $dateItem) {
                                $results['direct_applied'][] = [
                                    'member' => $memberKode,
                                    'tanggal' => $dateItem['tanggal'],
                                ];
                            }
                        }
                    }

                    // Semua write selesai → commit.
                    DB::commit();

                    if (! empty($approvalDates)) {
                        $results['needs_approval'] = [
                            'no_transaksi' => $noTransaksi,
                            'member_count' => count($members),
                            'date_count' => count($approvalDates),
                        ];
                        $results['needs_approval_count'] =
                            count($members) * count($approvalDates);
                    }
                }
            }

            // Setiap cabang sudah commit sendiri tepat setelah write-nya (pure-writes):
            // tak ada transaksi menggantung di sini. Rollback ditangani catch di bawah.

            $message = '';
            if (
                ! empty($results['direct_applied']) &&
                ! empty($results['needs_approval'])
            ) {
                $needsApprovalCount =
                    $results['needs_approval_count'] ??
                    (is_array($results['needs_approval'])
                        ? (isset($results['needs_approval']['count'])
                            ? $results['needs_approval']['count']
                            : count($results['needs_approval']))
                        : 0);

                $message = sprintf(
                    '%d shift langsung diterapkan, %d shift menunggu approval atasan.',
                    count($results['direct_applied']),
                    $needsApprovalCount,
                );
            } elseif (! empty($results['direct_applied'])) {
                $message = 'Semua shift berhasil diterapkan.';
            } else {
                $message =
                    'Pengajuan shift backdate berhasil dibuat, menunggu approval atasan.';
            }

            // PHASE 3: PERFORMANCE LOGGING
            // Log performance metrics untuk observability
            $duration = (microtime(true) - $startTime) * 1000; // milliseconds
            Log::channel('LeaderShiftController')->info('submitMetrics', [
                'method' => 'submit',
                'duration_ms' => round($duration, 2),
                'jenis' => $jenis,
                'member_count' => count($members),
                'date_count' => count($dates),
                'approval_needed' => ! empty($results['needs_approval']),
                'direct_applied_count' => count($results['direct_applied']),
            ]);

            return ResponseHelper::success($results, $message);
        } catch (Exception $e) {
            // PHASE 2: UNIFIED ERROR HANDLING
            // Safe rollback dengan transaction level check
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::channel('LeaderShiftController')->error('submitError', [
                'method' => 'submit',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'jenis' => $jenis ?? 'unknown',
            ]);

            $friendlyMessage = $this->toFriendlyApprovalErrorMessage(
                $e->getMessage(),
            );

            return ResponseHelper::error(
                $friendlyMessage ??
                    'Gagal memproses pengajuan shift: '.$e->getMessage(),
                $friendlyMessage ? 422 : 500,
            );
        }
    }

    /**
     * Submit leader offday request using 3-tier transactions
     * Accepts all dates in a single request from frontend
     */
    public function submitOffday(Request $request)
    {
        $request->validate([
            'members' => 'required|array|min:1',
            'members.*' => 'required|string',
            'mode' => 'required|in:SINGLE,RANGE,PERMANEN',
            'dates' => 'required_if:mode,SINGLE|array',
            'dates.*' => 'date',
            'start_date' => 'required_if:mode,RANGE,PERMANEN|nullable|date',
            'end_date' => 'required_if:mode,RANGE|nullable|date',
            'alasan' => 'nullable|string|max:500',
        ]);

        // dd($request->all());

        $user = Auth::user();
        $karyawan = $user->karyawan;
        $context = app(AccessContext::class);

        if (! $karyawan) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 404);
        }

        $leaderKode = $karyawan->Kode_Karyawan;
        $memberKodes = $request->input('members');
        $mode = $request->input('mode');
        $modeInsert =
            $request->input('mode') == 'PERMANEN' ? 'PERMANEN' : 'SEMENTARA';
        $alasan = $request->input('alasan', '');

        // PHASE 3: PERFORMANCE LOGGING
        // Track metrics untuk monitoring dan troubleshooting
        $startTime = microtime(true);

        try {
            $now = FormatTanggalHelper::getCurrentTime('leader-shift');
            $today = $now->format('Y-m-d');

            // Build date list based on mode
            $dateList = [];

            if ($mode === 'SINGLE') {
                $dateList = $request->input('dates', []);
            } elseif ($mode === 'RANGE') {
                $start = Carbon::parse($request->input('start_date'));
                $end = Carbon::parse($request->input('end_date'));
                if ($end->lt($start)) {
                    throw new \Exception(
                        'Tanggal selesai tidak boleh lebih kecil dari tanggal mulai.',
                    );
                }
                while ($start->lte($end)) {
                    $dateList[] = $start->format('Y-m-d');
                    $start->addDay();
                }
            } elseif ($mode === 'PERMANEN') {
                $startDate = $request->input('start_date') ?: $today;
                $dateList = [$startDate];
            }

            if (empty($dateList)) {
                throw new \Exception('Tidak ada tanggal yang dipilih.');
            }

            // PHASE 2: VALIDATION BEFORE TRANSACTION
            // Validate all conditions BEFORE starting transaction
            // Prevents unnecessary rollbacks for validation failures
            $minimumWeeklyHours = $this->getWeeklyTargetHours();

            $weeklyViolations = $this->shiftService->validateMinimumWeeklyHoursAfterOffday(
                $memberKodes,
                $dateList,
                $minimumWeeklyHours,
            );

            if (! empty($weeklyViolations)) {
                return ResponseHelper::error(
                    $this->formatWeeklyHoursViolationMessage(
                        $weeklyViolations,
                        $minimumWeeklyHours,
                    ),
                    422,
                );
            }

            // PHASE 2: ATOMIC TRANSACTION WRAPPING
            // Begin transaction AFTER all validation complete
            // Ensures atomicity: if ANY DB operation fails, everything rolls back
            DB::beginTransaction();

            // Classify dates using config
            $classifiedDates = [];
            foreach ($dateList as $tanggal) {
                $classification = $this->classifyDate(
                    $tanggal,
                    $today,
                    $karyawan->Kode_Karyawan,
                );
                $classifiedDates[] = [
                    'tanggal' => $tanggal,
                    'status_hari' => 'OFF',
                    'jenis_pengajuan' => $classification['jenis_pengajuan'],
                    'needs_approval' => $classification['needs_approval'],
                ];
            }

            // Build member-date matrix (same dates for all members)
            $membersPayload = [];
            foreach ($memberKodes as $kode) {
                $membersPayload[] = [
                    'kode' => $kode,
                    'dates' => $classifiedDates,
                ];
            }

            // Create 3-tier transaction
            $noTransaksi = $this->shiftService->createLeaderStatusHariRequest(
                $leaderKode,
                $membersPayload,
                $alasan,
                $modeInsert,
                $context,
            );

            // Direct apply for dates that don't need approval
            $results = [
                'direct_applied' => [],
                'needs_approval' => [],
            ];

            $hasBackdateApproval = false;
            $appliedDatesByKaryawan = [];

            foreach ($memberKodes as $kode) {
                foreach ($classifiedDates as $dateItem) {
                    if (! $dateItem['needs_approval']) {
                        // For PERMANEN mode, apply with end date 9999-12-31
                        if ($mode === 'PERMANEN') {
                            $this->shiftService->applyStatusHariDirectForExistingTransaction(
                                $noTransaksi,
                                $kode,
                                $dateItem['tanggal'],
                                'OFF',
                                null,
                                $alasan,
                                'PERMANEN',
                            );
                        } else {
                            $this->shiftService->applyStatusHariDirectForExistingTransaction(
                                $noTransaksi,
                                $kode,
                                $dateItem['tanggal'],
                                'OFF',
                                null,
                                $alasan,
                            );
                        }
                        $results['direct_applied'][] = [
                            'kode' => $kode,
                            'tanggal' => $dateItem['tanggal'],
                        ];
                        // Kumpulkan tanggal untuk notifikasi
                        $appliedDatesByKaryawan[$kode][] = $dateItem['tanggal'];
                    } else {
                        $results['needs_approval'][] = [
                            'kode' => $kode,
                            'tanggal' => $dateItem['tanggal'],
                        ];
                        if ($dateItem['jenis_pengajuan'] === 'BACKDATE') {
                            $hasBackdateApproval = true;
                        }
                    }
                }
            }

            // Kirim notifikasi satu kali per karyawan setelah apply selesai
            $this->siapkanKaryawan(array_keys($appliedDatesByKaryawan));

            foreach ($appliedDatesByKaryawan as $kode => $tanggalArr) {
                // dd($kode, $tanggalArr);
                $tanggalArr = $this->normalizeDateStrings($tanggalArr);
                if (empty($tanggalArr)) {
                    continue;
                }
                $namaKaryawan = $this->getKaryawanName($kode);
                $hpKaryawan = $this->getKaryawanHp($kode);
                // $tanggalFormatted = FormatTanggalHelper::formatTanggalMultiple($tanggalArr);
                // dd($namaKaryawan, $hpKaryawan, $tanggalFormatted);
                $this->shiftService->notifEmployeeForStatusHari(
                    $kode,
                    $noTransaksi,
                    $karyawan->Nama ?? '',
                    $namaKaryawan,
                    $hpKaryawan,
                    $tanggalArr,
                    'OFF',
                    $modeInsert,
                );
            }

            if (! empty($results['needs_approval'])) {
                $configCode = $hasBackdateApproval
                    ? 'LEADER_SHIFT_BACKDATE'
                    : 'LEADER_SHIFT';

                $flowResult = $this->approvalFlowService->createFlowFromApprovalConfig(
                    $noTransaksi,
                    $karyawan->Kode_Karyawan,
                    $configCode,
                );

                // VALIDATION: Fail transaction if approval config not found
                if (! $flowResult->success) {
                    DB::rollBack();
                    $friendlyFlowMessage = $this->toFriendlyApprovalErrorMessage(
                        $flowResult->error ?? '',
                    );

                    return ResponseHelper::error(
                        $friendlyFlowMessage ??
                            'Konfigurasi approval untuk '.
                                $configCode.
                                ' tidak ditemukan. '.
                                $flowResult->error,
                        422,
                    );
                }

                // Baris tracking per tanggal untuk tiap approver. Tanpanya
                // processStatusHariApproval() tidak punya tempat mencatat
                // keputusan per tanggal, dan riwayat tidak tahu siapa yang menolak.
                $this->shiftService->buatDetailTrackingApproval($noTransaksi, 'N_HRIS_VW_SRC_STATUS_HARI');

                // PHASE 3: N+1 PREVENTION
                // Eager load approver data in single query instead of separate calls
                $approverKode = $flowResult->getFirstApproverKode();
                $approverData = DB::table('Karyawan')
                    ->where('Kode_Karyawan', $approverKode)
                    ->select('Kode_Karyawan', 'Nama', 'HP')
                    ->first();

                $firstApprover = $approverData?->Nama ?? 'Unknown';
                $namaApprover = $approverData?->Nama ?? 'Approver';
                $hpApprover = $approverData?->HP;

                $onlyDate = array_column($results['needs_approval'], 'tanggal');
                $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple(
                    $onlyDate,
                );
                $tipeTraksaksi = 'STATUS HARI Oleh Leader';
                $namaRequester = 'Tim '.$karyawan->Nama;

                $pesan = $this->whatsappMessageBuilder->Notif_Request_Transaksi_Tanggal(
                    $hpApprover,
                    $namaApprover,
                    $tipeTraksaksi,
                    $noTransaksi,
                    $namaRequester,
                    $tanggalFormated,
                );
                // dd($pesan);
                $updateWaCounter = function () use ($noTransaksi, $flowResult) {
                    DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where(
                            'Approver_Karyawan_Kode',
                            $flowResult->getFirstApproverKode(),
                        )
                        ->update([
                            'angka_wa' => DB::raw('ISNULL(angka_wa, 0) + 1'),
                        ]);
                };

                $this->whatsappLogHelper->sendAndLog(
                    $flowResult->getFirstApproverKode(),
                    $firstApprover,
                    $hpApprover,
                    $noTransaksi,
                    $pesan,
                    $updateWaCounter,
                );
            }

            // Check if header should be flagged as complete
            if (empty($results['needs_approval'])) {
                DB::table('N_HRIS_Transaksi_Status_Hari')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update([
                        'Flag_Selesai' => 'Y',
                        'Updated_At' => now(),
                    ]);
            }

            // PHASE 2: UNIFIED ERROR HANDLING
            // Commit only AFTER all DB operations succeed
            DB::commit();

            // PHASE 3: PERFORMANCE LOGGING
            // Log performance metrics untuk observability
            $duration = (microtime(true) - $startTime) * 1000; // milliseconds
            Log::channel('LeaderShiftController')->info('submitOffDayMetrics', [
                'method' => 'submitOffday',
                'duration_ms' => round($duration, 2),
                'mode' => $mode,
                'member_count' => count($memberKodes),
                'date_count' => count($classifiedDates),
                'approval_needed' => ! empty($results['needs_approval']),
                'direct_applied_count' => count($results['direct_applied']),
            ]);

            return ResponseHelper::success(
                [
                    'no_transaksi' => $noTransaksi,
                    'mode' => $mode,
                    'total_members' => count($memberKodes),
                    'total_dates' => count($classifiedDates),
                    'direct_applied' => count($results['direct_applied']),
                    'needs_approval_count' => count($results['needs_approval']),
                ],
                'Offday berhasil disimpan',
            );
        } catch (Exception $e) {
            // PHASE 2: UNIFIED ERROR HANDLING
            // Safe rollback dengan transaction level check
            // Prevents rollback errors jika transaction tidak aktif
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::channel('LeaderShiftController')->error('submitError', [
                'method' => 'submitOffday',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'mode' => $mode ?? 'unknown',
                'member_count' => count($memberKodes ?? []),
            ]);

            $friendlyMessage = $this->toFriendlyApprovalErrorMessage(
                $e->getMessage(),
            );

            return ResponseHelper::error(
                $friendlyMessage ??
                    'Gagal menyimpan offday: '.$e->getMessage(),
                $friendlyMessage ? 422 : 500,
            );
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

    protected function formatWeeklyHoursViolationMessage(
        array $violations,
        ?float $minimumHours = null,
    ): string {
        $minimumHours = $minimumHours ?? $this->getWeeklyTargetHours();

        $memberNames = DB::table('Karyawan')
            ->whereIn(
                'Kode_Karyawan',
                collect($violations)
                    ->pluck('member_kode')
                    ->unique()
                    ->values()
                    ->toArray(),
            )
            ->pluck('Nama', 'Kode_Karyawan');

        $first = $violations[0];
        $memberName =
            $memberNames[$first['member_kode']] ?? $first['member_kode'];
        $weekLabel =
            Carbon::parse($first['week_start'])->format('d M').
            ' - '.
            Carbon::parse($first['week_end'])->format('d M Y');

        return sprintf(
            'Pengajuan offday ditolak. Jam kerja mingguan %s pada periode %s menjadi %.2f jam (kurang %.2f jam dari minimum %.2f jam).',
            $memberName,
            $weekLabel,
            $first['projected_hours'],
            $first['deficit_hours'],
            $minimumHours,
        );
    }

    protected function getWeeklyTargetHours(): float
    {
        return (float) ($this->leaderConfig['target_hours_per_week'] ?? 0);
    }

    protected function toFriendlyApprovalErrorMessage(string $message): ?string
    {
        $normalized = strtolower($message);

        $isMissingApproverError =
            str_contains($normalized, 'resolver gagal menemukan approver') ||
            str_contains($normalized, 'tidak ada approver ditemukan') ||
            str_contains($normalized, 'konfigurasi approval untuk') ||
            str_contains(
                $normalized,
                'detail approval config tidak ditemukan',
            ) ||
            str_contains(
                $normalized,
                'approval config tidak ditemukan untuk kode',
            );

        if (! $isMissingApproverError) {
            return null;
        }

        return 'Pengajuan ditolak karena approver untuk jenis pengajuan ini belum tersedia. Silakan hubungi admin untuk pengaturan approval flow.';
    }

    /**
     * Nama & HP karyawan, dibaca SEKALI per kode selama satu permintaan.
     *
     * Bentuk lama: dua metode, masing-masing `SELECT *` atas Karyawan untuk SATU
     * orang, dan keduanya dipanggil di dalam perulangan per karyawan. Pada satu
     * leader yang menetapkan shift untuk 40 anggotanya itu 80 kueri SELECT *
     * untuk dua kolom.
     *
     * Memo ini tidak bisa mengubah hasil: nama dan nomor HP tidak berubah di
     * tengah satu permintaan, dan controller lahir baru tiap permintaan. Yang
     * hilang hanya pengulangannya.
     *
     * @var array<string,object|null>
     */
    private array $memoKaryawan = [];

    /** Satu kueri untuk SEMUA kode sekaligus — dipanggil sebelum perulangan. */
    private function siapkanKaryawan(array $kode): void
    {
        $belum = array_values(array_diff(
            array_map('strval', $kode),
            array_keys($this->memoKaryawan),
        ));

        if ($belum === []) {
            return;
        }

        foreach (array_chunk($belum, 500) as $potongan) {
            $baris = DB::table('Karyawan')
                ->whereIn('Kode_Karyawan', $potongan)
                ->get(['Kode_Karyawan', 'Nama', 'HP'])
                ->keyBy(fn ($r) => trim((string) $r->Kode_Karyawan));

            foreach ($potongan as $k) {
                $this->memoKaryawan[$k] = $baris[trim($k)] ?? null;
            }
        }
    }

    private function karyawanRingkas(string $kodeKaryawan): ?object
    {
        if (! array_key_exists($kodeKaryawan, $this->memoKaryawan)) {
            $this->memoKaryawan[$kodeKaryawan] = DB::table('Karyawan')
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->first(['Kode_Karyawan', 'Nama', 'HP']);
        }

        return $this->memoKaryawan[$kodeKaryawan];
    }

    protected function getKaryawanName(string $kodeKaryawan): ?string
    {
        return $this->karyawanRingkas($kodeKaryawan)?->Nama;
    }

    protected function getKaryawanJabatan(string $kodeKaryawan): ?string
    {
        $karyawan = DB::table('Karyawan as k')
            ->join(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'vg.ID_Level_Jabatan',
                '=',
                'k.ID_Level_Jabatan',
            )
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->select('vg.nama_jabatan', 'k.Nama', 'k.Kode_Karyawan')
            ->first();

        return $karyawan?->nama_jabatan;
    }

    protected function getKaryawanHp(string $kodeKaryawan): ?string
    {
        return $this->karyawanRingkas($kodeKaryawan)?->HP;
    }

    /**
     * Normalize mixed date payload into valid Y-m-d strings.
     *
     * Accepts items in forms:
     * - "2026-03-04"
     * - ["tanggal" => "2026-03-04", ...]
     */
    protected function normalizeDateStrings(array $values): array
    {
        $normalized = array_map(function ($item) {
            if (is_array($item)) {
                return $item['tanggal'] ?? null;
            }

            return $item;
        }, $values);

        return array_values(
            array_filter($normalized, function ($tgl) {
                return is_string($tgl) &&
                    $tgl !== '' &&
                    strtotime($tgl) !== false;
            }),
        );
    }

    /**
     * Get history of leader shift changes
     */
    public function getHistory(Request $request)
    {
        try {
            $user = Auth::user();
            $karyawan = $user->karyawan;
            $context = app(AccessContext::class);
            $filters = [
                'status' => $request->input('status', 'all'),
                'search' => $request->input('search'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'transaction_type' => $request->input(
                    'transaction_type',
                    'all',
                ),
                'created_at' => $request->input('created_at'),
            ];

            $params = [
                'page' => $request->input('page', 1),
                'limit' => $request->input('limit', 10),
                'filters' => $filters,
            ];

            $result = $this->shiftService->getLeaderShiftHistory(
                $karyawan->Kode_Karyawan,
                $user->Id_Users,
                $params,
                $context,
            );

            return ResponseHelper::successWithPaginationV2(
                $result['items'],
                $result['page'] ?? 1,
                $result['limit'] ?? 10,
                $result['total'],
                'Data riwayat berhasil dimuat',
            );
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error getHistory: '.$e->getMessage(),
            );

            return ResponseHelper::error('Gagal memuat riwayat', 500);
        }
    }

    /**
     * Get transaction detail
     */
    public function getDetail(string $noTransaksi)
    {
        try {
            $detail = $this->shiftService->getDetail($noTransaksi);

            if (! $detail) {
                return ResponseHelper::error('Transaksi tidak ditemukan', 404);
            }

            return ResponseHelper::success($detail, 'Detail berhasil dimuat');
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error getDetail: '.$e->getMessage(),
            );

            return ResponseHelper::error('Gagal memuat detail', 500);
        }
    }

    /**
     * Cancel leader transaction (header/detail/det)
     */
    public function cancelHistory(Request $request)
    {
        $validated = $request->validate([
            'no_transaksi' => 'required|string',
            'scope' => 'required|in:header,detail,det',
            'detail_id' => 'nullable|integer',
            'det_id' => 'nullable|integer',
        ]);

        try {
            $result = $this->shiftService->cancelLeaderShiftTransaction(
                $validated['no_transaksi'],
                $validated['scope'],
                $validated['detail_id'] ?? null,
                $validated['det_id'] ?? null,
            );

            return ResponseHelper::success(
                $result,
                'Pengajuan berhasil dibatalkan',
            );
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error cancelHistory: '.$e->getMessage(),
            );

            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    /**
     * Get realtime dashboard statistics for Leader Shift widget.
     */
    public function stats()
    {
        try {
            $user = Auth::user();
            $karyawan = $user?->karyawan;

            if (! $karyawan) {
                return ResponseHelper::error(
                    'Data karyawan tidak ditemukan',
                    404,
                );
            }

            return ResponseHelper::success(
                $this->getStats($karyawan->Kode_Karyawan),
                'Data statistik berhasil dimuat',
            );
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error stats: '.$e->getMessage(),
            );

            return ResponseHelper::error('Gagal memuat statistik', 500);
        }
    }

    /**
     * Check if dates have conflicts (lembur, izin, etc)
     */
    public function checkConflicts(Request $request)
    {
        try {
            $members = $request->input('members', []);
            $dates = $request->input('dates', []);

            $conflicts = [];

            foreach ($members as $memberKode) {
                foreach ($dates as $tanggal) {
                    // Check lembur
                    $lembur = DB::table('Lembur_Detail as ld')
                        ->join(
                            'Lembur_Header as lh',
                            'ld.No_Transaksi',
                            '=',
                            'lh.No_Transaksi',
                        )
                        ->where('lh.Kode_Karyawan', $memberKode)
                        ->where('ld.Tanggal_Lembur_Dari', '<=', $tanggal)
                        ->where('ld.Tanggal_Lembur_Sampai', '>=', $tanggal)
                        ->whereNull('lh.Status')
                        ->first();

                    if ($lembur) {
                        $conflicts[] = [
                            'member' => $memberKode,
                            'tanggal' => $tanggal,
                            'type' => 'lembur',
                            'message' => 'Ada lembur pada tanggal ini',
                        ];
                    }

                    // Check izin
                    $izin = DB::table('Izin_Karyawan as ik')
                        ->where('ik.Kode_Karyawan', $memberKode)
                        ->where('ik.Tanggal_Masuk_Pulang', $tanggal)
                        ->whereNull('ik.Status')
                        ->first();

                    if ($izin) {
                        $conflicts[] = [
                            'member' => $memberKode,
                            'tanggal' => $tanggal,
                            'type' => 'izin',
                            'message' => 'Ada izin pada tanggal ini',
                        ];
                    }
                }
            }

            return ResponseHelper::success(
                $conflicts,
                'Data konflik berhasil dicek',
            );
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error checkConflicts: '.$e->getMessage(),
            );

            return ResponseHelper::error('Gagal mengecek konflik', 500);
        }
    }

    public function checkPermanentConflicts(Request $request)
    {
        $request->validate([
            'members' => 'required|array|min:1',
            'members.*' => 'string',
            'date' => 'nullable|date',
            'dates' => 'nullable|array',
            'dates.*' => 'date',
        ]);

        try {
            $members = $request->input('members', []);
            $dates = $request->input('dates', []);
            if (empty($dates) && $request->filled('date')) {
                $dates = [Carbon::parse($request->date)->format('Y-m-d')];
            }
            $dates = collect($dates)
                ->map(function ($date) {
                    return Carbon::parse($date)->format('Y-m-d');
                })
                ->values()
                ->toArray();

            if (empty($dates)) {
                return ResponseHelper::error('Tanggal tidak ditemukan', 422);
            }

            $details = [];
            $byDate = [];
            $byMember = [];
            $total = 0;

            /* Satu pengambilan untuk seluruh pasangan.
               Bentuk lama menembak satu COUNT per (anggota, tanggal): 40
               anggota x 30 tanggal = 1.200 COUNT ke tabel yang sama. */
            $konflik = $this->shiftService->checkPermanentConflicts($members, $dates);

            foreach ($members as $memberKode) {
                foreach ($dates as $tanggal) {
                    $count = (int) ($konflik[$memberKode][$tanggal] ?? 0);
                    $details[] = [
                        'member' => $memberKode,
                        'date' => $tanggal,
                        'count' => $count,
                    ];
                    $byDate[$tanggal] = ($byDate[$tanggal] ?? 0) + $count;
                    $byMember[$memberKode] =
                        ($byMember[$memberKode] ?? 0) + $count;
                    $total += $count;
                }
            }

            $byDateList = collect($byDate)
                ->map(function ($count, $date) {
                    return ['date' => $date, 'count' => $count];
                })
                ->values()
                ->toArray();

            $byMemberList = collect($byMember)
                ->map(function ($count, $member) {
                    return ['member' => $member, 'count' => $count];
                })
                ->values()
                ->toArray();

            return ResponseHelper::success(
                [
                    'total' => $total,
                    'details' => $details,
                    'by_date' => $byDateList,
                    'by_member' => $byMemberList,
                ],
                'Data konflik permanen berhasil dicek',
            );
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error checkPermanentConflicts: '.$e->getMessage(),
            );

            return ResponseHelper::error(
                'Gagal mengecek konflik permanen',
                500,
            );
        }
    }

    /**
     * Get dashboard statistics
     */
    protected function getStats(string $leaderKode): array
    {
        try {
            $user = Auth::user();
            $karyawan = $user->karyawan;
            $context = app(AccessContext::class);
            // For admin, we want to show stats for ALL teams, so we pass null or empty string
            $result = $this->shiftService->getStatsForLeader(
                $karyawan->Kode_Karyawan,
                $this->role,
                $context,
            );
            Log::channel('LeaderShiftController')->info(
                'getStats Result',
                $result,
            );

            return $result;
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error getStats: '.$e->getMessage(),
            );

            return [
                'team_count' => 0,
                'pending_count' => 0,
                'approved_month' => 0,
            ];
        }
    }

    /**
     * Get leader's approver (for backdate approval flow)
     */
    protected function getLeaderApprover(string $leaderKode): ?array
    {
        try {
            $featureConfigCodes = ['LEADER_SHIFT', 'LEADER_SHIFT_BACKDATE'];

            $configs = DB::table('N_HRIS_Approval_Config')
                ->whereIn('Kode_Config', $featureConfigCodes)
                ->where('Flag_Aktif', 'Y')
                ->select('Id', 'Kode_Config', 'Skip_If_Requester_Level_Id')
                ->get();

            if ($configs->isNotEmpty()) {
                $configIds = $configs->pluck('Id')->all();
                $configById = $configs->keyBy('Id');

                $details = DB::table('N_HRIS_Approval_Config_Detail as d')
                    ->leftJoin(
                        'N_HRIS_Jenis_Approval as ja',
                        'd.Id_Jenis_Approval',
                        '=',
                        'ja.Id_Jenis_Approval',
                    )
                    ->whereIn('d.Id_Approval_Config', $configIds)
                    ->where('d.Flag_Aktif', 'Y')
                    ->where(function ($query) {
                        $query
                            ->whereNotNull('d.Custom_Approver_Kode')
                            ->orWhere(function ($sub) {
                                $sub->whereNotNull(
                                    'd.Id_Jenis_Approval',
                                )->where('ja.Flag_Aktif', 'Y');
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

                if ($details->isNotEmpty()) {
                    $detailWithHighestLimit = $details
                        ->sortByDesc(function ($detail) {
                            $limit = (int) ($detail->Order_Approver_Limit ?? 0);
                            $approvalOrder =
                                (int) ($detail->Approval_Order ?? 0);

                            return $limit * 100000 + $approvalOrder;
                        })
                        ->first();

                    $selectedConfigId =
                        $detailWithHighestLimit->Id_Approval_Config;
                    $selectedConfig = $configById->get($selectedConfigId);

                    $selectedDetails = $details
                        ->filter(
                            fn ($detail) => (int) $detail->Id_Approval_Config ===
                                (int) $selectedConfigId,
                        )
                        ->sortBy('Approval_Order')
                        ->values();

                    $resolver = app(ApproverResolverService::class);
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
                            $resolver->resolve(
                                (int) $detail->Id_Jenis_Approval,
                                (string) ($detail->Source_Type ?? ''),
                                $detail->Source_Reference,
                                $leaderKode,
                                ! empty($detail->Order_Approver_Limit)
                                    ? (int) $detail->Order_Approver_Limit
                                    : null,
                            ),
                        )->map(function ($approver) use ($detail) {
                            $approver->approval_order =
                                (int) ($detail->Approval_Order ?? 0);

                            return $approver;
                        });

                        $resolvedApprovers = $resolvedApprovers->merge(
                            $resolved,
                        );
                    }

                    $resolvedApprovers = $resolvedApprovers
                        ->filter(
                            fn ($approver) => ! empty($approver->kode_karyawan),
                        )
                        ->unique(
                            fn ($approver) => ($approver->approval_order ?? 0).
                                '|'.
                                $approver->kode_karyawan,
                        )
                        ->sortBy(function ($approver) {
                            return ($approver->approval_order ?? 0).
                                '|'.
                                ($approver->kode_karyawan ?? '');
                        })
                        ->values();

                    if ($resolvedApprovers->isNotEmpty()) {
                        $kodeList = $resolvedApprovers
                            ->pluck('kode_karyawan')
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();

                        $karyawanMap = DB::table('Karyawan as k')
                            ->leftJoin(
                                'View_Golongan_Sub_Golongan_Level_Jabatan as v',
                                'k.Id_Level_Jabatan',
                                '=',
                                'v.Id_Level_Jabatan',
                            )
                            ->whereIn('k.Kode_Karyawan', $kodeList)
                            ->select(
                                'k.Kode_Karyawan',
                                'k.Nama',
                                'v.nama_jabatan',
                            )
                            ->get()
                            ->keyBy('Kode_Karyawan');

                        $approvers = $resolvedApprovers
                            ->map(function ($approver, $index) use (
                                $karyawanMap,
                            ) {
                                $profile = $karyawanMap->get(
                                    $approver->kode_karyawan,
                                );

                                return [
                                    'order' => $index + 1,
                                    'approval_order' => (int) ($approver->approval_order ??
                                            $index + 1),
                                    'kode' => $approver->kode_karyawan,
                                    'nama' => $profile->Nama ??
                                        $approver->kode_karyawan,
                                    'jabatan' => $profile->nama_jabatan ?? null,
                                ];
                            })
                            ->values();

                        $firstApprover = $approvers->first();

                        return [
                            'kode' => $firstApprover['kode'] ?? null,
                            'nama' => $firstApprover['nama'] ?? null,
                            'jabatan' => $firstApprover['jabatan'] ?? null,
                            'approvers' => $approvers,
                            'selected_config' => $selectedConfig->Kode_Config ?? null,
                            'skip_if_requester_level_id' => $selectedConfig->Skip_If_Requester_Level_Id ??
                                null,
                            'max_order_approver_limit' => (int) ($detailWithHighestLimit->Order_Approver_Limit ??
                                    0),
                            'config_codes' => $featureConfigCodes,
                        ];
                    }
                }
            }

            // Fallback: Legacy flow table
            $approver = DB::table('N_HRIS_Approval_Flow_Shift as afs')
                ->join(
                    'Karyawan as k',
                    'afs.Kode_Karyawan',
                    '=',
                    'k.Kode_Karyawan',
                )
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as v',
                    'k.Id_Level_Jabatan',
                    '=',
                    'v.Id_Level_Jabatan',
                )
                ->where('afs.Kode_Karyawan_Requester', $leaderKode)
                ->whereNull('afs.Status')
                ->orderBy('afs.order_flow', 'asc')
                ->select(
                    'k.Kode_Karyawan',
                    'k.Nama',
                    'v.nama_jabatan',
                    'afs.order_flow',
                )
                ->get();

            if ($approver->isNotEmpty()) {
                $approvers = $approver
                    ->map(
                        fn ($item, $index) => [
                            'order' => $index + 1,
                            'approval_order' => (int) ($item->order_flow ?? $index + 1),
                            'kode' => $item->Kode_Karyawan,
                            'nama' => $item->Nama,
                            'jabatan' => $item->nama_jabatan,
                        ],
                    )
                    ->values();

                $firstApprover = $approvers->first();

                return [
                    'kode' => $firstApprover['kode'] ?? null,
                    'nama' => $firstApprover['nama'] ?? null,
                    'jabatan' => $firstApprover['jabatan'] ?? null,
                    'approvers' => $approvers,
                    'selected_config' => null,
                    'skip_if_requester_level_id' => null,
                    'max_order_approver_limit' => null,
                    'config_codes' => ['LEGACY_FLOW'],
                ];
            }

            return [
                'approvers' => [],
                'selected_config' => null,
                'skip_if_requester_level_id' => null,
                'max_order_approver_limit' => null,
                'config_codes' => $featureConfigCodes,
            ];
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error getLeaderApprover: '.$e->getMessage(),
            );

            return [
                'approvers' => [],
                'selected_config' => null,
                'skip_if_requester_level_id' => null,
                'max_order_approver_limit' => null,
            ];
        }
    }

    /**
     * Get active Status Hari override for a specific member and date.
     * Used by quick modal to show "Active Override" card and enable
     * "Akhiri Override" / "Edit Periode" buttons.
     *
     * GET /leader-shift/override-for-date?member=KODE&tanggal=YYYY-MM-DD
     */
    public function getOverrideForDate(Request $request)
    {
        $request->validate([
            'member' => 'required|string',
            'tanggal' => 'required|date_format:Y-m-d',
        ]);

        try {
            $memberKode = $request->input('member');
            $tanggal = $request->input('tanggal');

            $override = $this->shiftService->getActiveOverrideForDate(
                $memberKode,
                $tanggal,
            );

            return ResponseHelper::success(
                [
                    'has_override' => ! is_null($override),
                    'active_override' => $override
                        ? [
                            'id' => $override->Id,
                            'status_hari' => $override->Status_Hari,
                            'tgl_mulai' => $override->Tgl_Mulai,
                            'tgl_selesai' => $override->Tgl_Selesai,
                            'hari' => $override->Hari,
                            'keterangan' => $override->Keterangan,
                            'created_at' => $override->Created_At,
                            'created_by' => $override->Created_By,
                        ]
                        : null,
                ],
                'Data override berhasil dimuat',
            );
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error getOverrideForDate: '.$e->getMessage(),
            );

            return ResponseHelper::error('Gagal memuat data override', 500);
        }
    }

    /**
     * Stop (end) an active Status Hari override by updating its Tgl_Selesai.
     * Called when user clicks "Akhiri Override" in the quick modal.
     * Safe for historical data — only affects dates AFTER stopDate.
     *
     * POST /leader-shift/stop-override
     * Body: { id: int, stop_date: "YYYY-MM-DD" }
     */
    public function stopOverride(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'stop_date' => 'required|date_format:Y-m-d',
        ]);

        try {
            $this->shiftService->stopActiveOverride(
                $request->input('id'),
                $request->input('stop_date'),
            );

            return ResponseHelper::success([], 'Override berhasil diakhiri');
        } catch (\Exception $e) {
            Log::channel('LeaderShiftController')->error(
                'Error stopOverride: '.$e->getMessage(),
            );

            return ResponseHelper::error($e->getMessage(), 422);
        }
    }
}
