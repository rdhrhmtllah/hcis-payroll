<?php

namespace App\Http\Controllers;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\WhatsappLogHelper;
use App\Services\Approval\ApprovalFlowService;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use App\Services\Notification\WhatsappMessageBuilder;
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
    protected ShiftRequestService $shiftService;

    protected ApprovalFlowService $approvalFlowService;

    protected WhatsappLogHelper $whatsappLogHelper;

    protected WhatsappMessageBuilder $whatsappMessageBuilder;

    protected array $leaderConfig;

    public function __construct(ShiftRequestService $shiftService, ApprovalFlowService $approvalFlowService, WhatsappLogHelper $whatsappLogHelper, WhatsappMessageBuilder $whatsappMessageBuilder)
    {
        $this->shiftService = $shiftService;
        $this->whatsappLogHelper = $whatsappLogHelper;
        $this->whatsappMessageBuilder = $whatsappMessageBuilder;
        $this->approvalFlowService = $approvalFlowService;

        $this->leaderConfig = [
            'backdate_needs_approval' => config('services.hcis.leader_shift_request.backdate_needs_approval', true),
            'same_day_needs_approval' => config('services.hcis.leader_shift_request.same_day_needs_approval', true),
            'future_needs_approval' => config('services.hcis.leader_shift_request.future_needs_approval', true),
            'backdate_max_days' => (int) config('services.hcis.leader_shift_request.backdate_max_days', 7),
            'same_days' => (bool) config('services.hcis.leader_shift_request.same_days', true),
            'future_max_days' => (int) config('services.hcis.leader_shift_request.future_max_days', 30),
        ];
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
            return redirect()->back()->with('error', 'Anda tidak terdaftar sebagai karyawan.');
        }

        $disabledDates = [];

        // dd($this->getStats($karyawan->Kode_Karyawan));
        // Get approver info for this leader (for backdate approval)
        $approverInfo = $this->getLeaderApprover($karyawan->Kode_Karyawan);

        return Inertia::render('LeaderShift', [
            'serverTime' => $now->toISOString(),
            'serverDate' => $now->format('Y-m-d'),
            'approverInfo' => $approverInfo,
            'initialStats' => $this->getStats($karyawan->Kode_Karyawan),
            'config' => [
                'backdate_max_days' => config('services.hcis.leader_shift_request.backdate_max_days', 0),
                'future_max_days' => config('services.hcis.leader_shift_request.future_max_days', 60),
                'same_days' => config('services.hcis.leader_shift_request.same_days', true),
                'backdate_needs_approval' => config('services.hcis.leader_shift_request.backdate_needs_approval', true),
                'same_day_needs_approval' => config('services.hcis.leader_shift_request.same_day_needs_approval', true),
                'future_needs_approval' => config('services.hcis.leader_shift_request.future_needs_approval', true),
            ],
            'disabledDates' => $disabledDates,
        ]);
    }

    /**
     * Get team members for the leader
     */
    public function getTeam()
    {
        try {
            $user = Auth::user();
            $karyawan = $user->karyawan;

            if (! $karyawan) {
                return ResponseHelper::error('Karyawan tidak ditemukan', 404);
            }

            $team = $this->shiftService->getLeaderTeamMembers($karyawan->Kode_Karyawan);

            return ResponseHelper::success($team, 'Data tim berhasil dimuat');
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error('Error getTeam: '.$e->getMessage());

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

            $startDate = $request->input('start_date')
                ? Carbon::parse($request->input('start_date'))
                : FormatTanggalHelper::getCurrentTime('leader-shift')->startOfWeek();

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
                $allMembers = $this->shiftService->getLeaderTeamMembers($karyawan->Kode_Karyawan);

                // Apply search filter if provided
                if ($search) {
                    $searchLower = strtolower($search);
                    $allMembers = array_filter($allMembers, function ($m) use ($searchLower) {
                        return str_contains(strtolower($m->Nama ?? ''), $searchLower) ||
                               str_contains(strtolower($m->NIK ?? ''), $searchLower);
                    });
                    $allMembers = array_values($allMembers); // Re-index
                }

                $totalMembers = count($allMembers);

                // Paginate
                $paginatedMembers = array_slice($allMembers, ($page - 1) * $limit, $limit);
                $memberKodes = array_column($paginatedMembers, 'Kode_Karyawan');
            } else {
                $totalMembers = count($memberKodes);
                // Fetch member info for explicit member codes
                $paginatedMembers = $this->shiftService->getMemberInfoByKodes($memberKodes);
            }

            // Single bulk call instead of N individual calls
            $schedules = $this->shiftService->getWeekScheduleBulk($memberKodes, $startDate, $days);
            $workSummary = $this->shiftService->buildWeeklyHourSummary($schedules, 40.0);

            return ResponseHelper::successWithPaginationV2([
                'members' => $paginatedMembers, // Member info for this page
                'schedules' => $schedules,
                'work_summary' => $workSummary,
                'week_start' => $startDate->format('Y-m-d'),
                'week_end' => $startDate->copy()->addDays($days - 1)->format('Y-m-d'),
            ], $page, $limit, $totalMembers, 'Data jadwal berhasil dimuat');
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error('Error getWeekSchedule: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat jadwal', 500);
        }
    }

    /**
     * Get available shifts for a specific date
     */
    public function getShifts(Request $request)
    {
        try {
            $tanggal = $request->input('tanggal');

            if ($tanggal) {
                $shifts = $this->shiftService->getShiftsForDay($tanggal);
            } else {
                $shifts = $this->shiftService->getAvailableShifts();
            }

            return ResponseHelper::success($shifts, 'Data shift berhasil dimuat');
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error('Error getShifts: '.$e->getMessage());

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
            $memberKode = $validate['member'];

            if (filter_var($request->input('all'), FILTER_VALIDATE_BOOLEAN) === true) {
                $shiftsList = $this->shiftService->getAvailableShifts();
            } else {
                $shiftsList = $this->shiftService->getShiftsForDay($validate['date']);
            }

            $currentShift = $this->shiftService->getUserShiftForDate(
                $memberKode,
                Carbon::parse($validate['date'])
            );
            $isOffDay = $this->shiftService->isOffDay($memberKode, $validate['date']);

            return ResponseHelper::success([
                'shifts' => $shiftsList,
                'current_shift' => $currentShift,
                'is_off_day' => $isOffDay,
            ]);
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error('Error shiftForDay: '.$e->getMessage());

            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * Classify a date as BACKDATE, SAME_DAY, or FUTURE
     * and determine if it needs approval based on leaderConfig.
     *
     * @param  string  $today  Y-m-d
     * @return array{jenis_pengajuan: string, needs_approval: bool}
     */
    // protected function classifyDate(string $tanggal, string $today): array
    // {
    //     $dateParsed  = Carbon::parse($tanggal)->startOfDay();
    //     $todayParsed = Carbon::parse($today)->startOfDay();

    //     if ($dateParsed->lt($todayParsed)) {
    //         return [
    //             'jenis_pengajuan' => 'BACKDATE',
    //             'needs_approval'  => (bool) $this->leaderConfig['backdate_needs_approval'],
    //         ];
    //     } elseif ($dateParsed->eq($todayParsed)) {
    //         return [
    //             'jenis_pengajuan' => 'SAME_DAY',
    //             'needs_approval'  => (bool) $this->leaderConfig['same_day_needs_approval'],
    //         ];
    //     } else {
    //         return [
    //             'jenis_pengajuan' => 'FUTURE',
    //             'needs_approval'  => (bool) $this->leaderConfig['future_needs_approval'],
    //         ];
    //     }
    // }

    protected function classifyDate(string $tanggal, string $today, ?string $requesterKode = null): array
    {
        $dateParsed = Carbon::parse($tanggal)->startOfDay();
        $todayParsed = Carbon::parse($today)->startOfDay();
        $typeApprovalLeader = 'LEADER_SHIFT';

        // 1️⃣ Tentukan jenis tanggal (INI TETAP PUNYA SHIFT SERVICE)
        if ($dateParsed->lt($todayParsed)) {
            $jenis = 'BACKDATE';
            $defaultNeedsApproval = (bool) $this->leaderConfig['backdate_needs_approval'];
        } elseif ($dateParsed->eq($todayParsed)) {
            $jenis = 'SAME_DAY';
            $defaultNeedsApproval = (bool) $this->leaderConfig['same_day_needs_approval'];
        } else {
            $jenis = 'FUTURE';
            $defaultNeedsApproval = (bool) $this->leaderConfig['future_needs_approval'];
        }

        // 2️⃣ Kalau tidak ada requester (dipakai di tempat lain), pakai default saja
        if (! $requesterKode) {
            return [
                'jenis_pengajuan' => $jenis,
                'needs_approval' => $defaultNeedsApproval,
            ];
        }

        // 3️⃣ Tanya Approval Engine untuk final decision
        $decision = $this->approvalFlowService->decide([
            'approval_type' => $typeApprovalLeader,
            'requester_kode' => $requesterKode,
            'tanggal' => $tanggal,
            'classification' => [
                'jenis_pengajuan' => $jenis,
                'needs_approval' => $defaultNeedsApproval,
            ],
        ]);

        return [
            'jenis_pengajuan' => $jenis,
            'needs_approval' => $decision['action'] === 'APPROVAL',
            'decision_notes' => $decision['notes'],
            'decision_action' => $decision['action'],
            'decision_config_name' => $decision['config_name'],
        ];
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

        $user = Auth::user();
        $karyawan = $user->karyawan;

        if (! $karyawan) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 404);
        }

        // cek approval flow leader
        $approvers = DB::table('N_HRIS_Approval_Flow_Shift')
            ->where('Kode_Karyawan_Requester', $karyawan->Kode_Karyawan)
            ->whereNull('Status')
            ->orderBy('order_flow', 'asc')
            ->get();

        if ($approvers->isEmpty()) {
            return ResponseHelper::error('Tidak ditemukan approver untuk pengajuan shift Anda. Mohon hubungi HRD.', 403);
        }

        $leaderKode = $karyawan->Kode_Karyawan;
        $members = $request->input('members', []);
        $dates = $request->input('dates', []);
        $jenis = $request->input('jenis');
        $alasan = $request->input('alasan');
        $alasan = $alasan === null ? '' : $alasan;

        $hasMemberDateMatrix = $jenis === 'SEMENTARA'
            && ! empty($members)
            && is_array($members[0])
            && array_key_exists('dates', $members[0]);

        if ($jenis === 'PERMANEN') {
            $dates = array_slice($dates, 0, 1);
            foreach ($members as $memberItem) {
                if (! is_array($memberItem) || empty($memberItem['id_shift'])) {
                    return ResponseHelper::error(
                        'Shift permanen wajib diisi per karyawan.',
                        422
                    );
                }
            }
        } elseif ($hasMemberDateMatrix) {
            foreach ($members as $memberItem) {
                if (! is_array($memberItem) || empty($memberItem['kode'])) {
                    return ResponseHelper::error(
                        'Data karyawan tidak valid untuk shift sementara.',
                        422
                    );
                }
                $memberDates = $memberItem['dates'] ?? [];
                if (! is_array($memberDates) || empty($memberDates)) {
                    return ResponseHelper::error(
                        'Tanggal shift sementara wajib diisi per karyawan.',
                        422
                    );
                }
                foreach ($memberDates as $dateItem) {
                    if (empty($dateItem['tanggal']) || empty($dateItem['id_shift'])) {
                        return ResponseHelper::error(
                            'Shift sementara wajib diisi untuk setiap tanggal.',
                            422
                        );
                    }
                }
            }
        } else {
            if (empty($dates)) {
                return ResponseHelper::error(
                    'Tanggal shift sementara wajib diisi.',
                    422
                );
            }
            foreach ($dates as $dateItem) {
                if (empty($dateItem['id_shift'])) {
                    return ResponseHelper::error(
                        'Shift sementara wajib diisi untuk setiap tanggal.',
                        422
                    );
                }
            }
        }

        try {
            DB::beginTransaction();

            $now = FormatTanggalHelper::getCurrentTime('leader-shift');
            $today = $now->format('Y-m-d');

            $results = [
                'direct_applied' => [],
                'needs_approval' => [],
                'needs_approval_count' => 0,
            ];

            if ($jenis === 'SEMENTARA' && $hasMemberDateMatrix) {
                // Per-member date matrix: each member has their own dates with shifts
                $directApplyMembers = []; // Dates that DON'T need approval → apply directly
                $validMembers = [];       // Members with classified dates for transaction
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
                        $classification = $this->classifyDate($dateItem['tanggal'], $today, $leaderKode);
                        $classifiedDates[] = array_merge($dateItem, $classification);

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
                    $noTransaksi = $this->shiftService->createLeaderShiftRequest(
                        $leaderKode,
                        $validMembers,
                        [], // dates not used in matrix mode
                        $jenis,
                        $alasan,
                        true // isMemberDateMatrix
                    );

                    // If NO dates need approval, mark entire transaction as completed
                    if (! $hasApprovalNeeded) {
                        DB::table('N_HRIS_Transaksi_Shift')->where('No_Transaksi', $noTransaksi)->update([
                            'Flag_Selesai' => 'Y',
                        ]);

                    }

                    // Apply dates that don't need approval directly (reuse existing transaction)
                    foreach ($directApplyMembers as $memberKode => $directDates) {
                        foreach ($directDates as $dateItem) {
                            $this->shiftService->applyShiftDirectForExistingTransaction(
                                $noTransaksi,
                                $memberKode,
                                $dateItem['tanggal'],
                                $dateItem['id_shift'],
                                $jenis
                            );
                            $results['direct_applied'][] = [
                                'member' => $memberKode,
                                'tanggal' => $dateItem['tanggal'],
                            ];
                        }
                    }

                    $totalDates = array_sum(array_map(fn ($m) => count($m['dates'] ?? []), $validMembers));
                    $results['needs_approval'] = [
                        'no_transaksi' => $noTransaksi,
                        'member_count' => count($validMembers),
                        'date_count' => $totalDates,
                    ];
                    $results['needs_approval_count'] = $totalDates;
                }
            } else {
                // SEMENTARA with flat members array OR PERMANEN (both old pattern)
                // Refactor to: 1 transaction for all members, like SelfShift

                Log::channel('LeaderShiftController')->info('Processing non-matrix path', [
                    'jenis' => $jenis,
                    'hasMemberDateMatrix' => $hasMemberDateMatrix,
                    'member_count' => count($members),
                    'date_count' => count($dates),
                ]);

                if ($jenis === 'PERMANEN') {
                    Log::channel('LeaderShiftController')->info('PERMANEN processing started', [
                        'members' => $members,
                        'dates' => $dates,
                    ]);

                    // PERMANEN: members array has {kode, id_shift} structure
                    // dates array has 1 item {tanggal}
                    $tanggalPermanen = $dates[0]['tanggal'] ?? null;
                    if (! $tanggalPermanen) {
                        throw new \Exception('Tanggal permanen tidak ditemukan');
                    }

                    // Classify the permanen date using config
                    $classification = $this->classifyDate($tanggalPermanen, $today, $leaderKode);

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
                                    'decision_notes' => $classification['decision_notes'] ?? null,
                                ],
                            ],
                        ];
                    }

                    Log::channel('LeaderShiftController')->info('PERMANEN collected members', [
                        'member_count' => count($permanenMembers),
                        'tanggal_permanen' => $tanggalPermanen,
                        'classification' => $classification,
                    ]);

                    // Create ONE transaction with per-member shift assignments
                    if (! empty($permanenMembers)) {
                        Log::channel('LeaderShiftController')->info('Creating PERMANEN transaction', [
                            'member_count' => count($permanenMembers),
                        ]);

                        $noTransaksi = $this->shiftService->createLeaderShiftRequest(
                            $leaderKode,
                            $permanenMembers,
                            [], // dates not used in matrix mode
                            $jenis,
                            $alasan,
                            true // isMemberDateMatrix
                        );

                        Log::channel('LeaderShiftController')->info('PERMANEN transaction created', [
                            'no_transaksi' => $noTransaksi,
                        ]);

                        // If this date doesn't need approval, mark as completed and apply directly
                        if (! $classification['needs_approval']) {
                            DB::table('N_HRIS_Transaksi_Shift')->where('No_Transaksi', $noTransaksi)->update([
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
                                    $jenis
                                );
                                $results['direct_applied'][] = [
                                    'member' => $memberItem['kode'],
                                    'tanggal' => $tanggalPermanen,
                                ];
                            }
                        }

                        $results['needs_approval'] = [
                            'no_transaksi' => $noTransaksi,
                            'member_count' => count($permanenMembers),
                            'date_count' => 1,
                        ];
                        $results['needs_approval_count'] = count($permanenMembers);
                    }
                } else {
                    // SEMENTARA with flat members array (old pattern - still supported)
                    // Classify dates using config
                    $approvalDates = [];
                    $directApplyDates = [];
                    $classifiedDates = [];

                    foreach ($dates as $dateItem) {
                        $classification = $this->classifyDate($dateItem['tanggal'], $today, $leaderKode);
                        $classifiedDate = array_merge($dateItem, $classification);
                        $classifiedDates[] = $classifiedDate;

                        if ($classification['needs_approval']) {
                            $approvalDates[] = $classifiedDate;
                        } else {
                            $directApplyDates[] = $dateItem;
                        }
                    }

                    $noTransaksi = $this->shiftService->createLeaderShiftRequest(
                        $leaderKode,
                        $members,
                        $classifiedDates, // Now includes jenis_pengajuan + needs_approval per date
                        $jenis,
                        $alasan
                    );

                    // If NO dates need approval, mark entire transaction as completed
                    if (empty($approvalDates)) {
                        DB::table('N_HRIS_Transaksi_Shift')->where('No_Transaksi', $noTransaksi)->update([
                            'Flag_Selesai' => 'Y',
                        ]);
                    }

                    // Apply dates that don't need approval directly (reuse existing transaction)
                    if (! empty($directApplyDates)) {
                        foreach ($members as $memberKode) {
                            foreach ($directApplyDates as $dateItem) {
                                $this->shiftService->applyShiftDirectForExistingTransaction(
                                    $noTransaksi,
                                    $memberKode,
                                    $dateItem['tanggal'],
                                    $dateItem['id_shift'],
                                    $jenis
                                );
                                $results['direct_applied'][] = [
                                    'member' => $memberKode,
                                    'tanggal' => $dateItem['tanggal'],
                                ];
                            }
                        }
                    }

                    $results['needs_approval'] = [
                        'no_transaksi' => $noTransaksi,
                        'member_count' => count($members),
                        'date_count' => count($classifiedDates),
                    ];
                    $results['needs_approval_count'] = count($members) * count($classifiedDates);
                }
            }

            DB::commit();

            $message = '';
            if (! empty($results['direct_applied']) && ! empty($results['needs_approval'])) {
                $needsApprovalCount = $results['needs_approval_count']
                    ?? (is_array($results['needs_approval'])
                        ? (isset($results['needs_approval']['count'])
                            ? $results['needs_approval']['count']
                            : count($results['needs_approval']))
                        : 0);

                $message = sprintf(
                    '%d shift langsung diterapkan, %d shift menunggu approval atasan.',
                    count($results['direct_applied']),
                    $needsApprovalCount
                );
            } elseif (! empty($results['direct_applied'])) {
                $message = 'Semua shift berhasil diterapkan.';
            } else {
                $message = 'Pengajuan shift backdate berhasil dibuat, menunggu approval atasan.';
            }

            return ResponseHelper::success($results, $message);

        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('LeaderShiftController')->error('Error submit: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return ResponseHelper::error('Gagal memproses pengajuan: '.$e->getMessage(), 500);
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

        if (! $karyawan) {
            return ResponseHelper::error('Karyawan tidak ditemukan', 404);
        }

        $leaderKode = $karyawan->Kode_Karyawan;
        $memberKodes = $request->input('members');
        $mode = $request->input('mode');
        $alasan = $request->input('alasan', '');

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
                    throw new \Exception('Tanggal selesai tidak boleh lebih kecil dari tanggal mulai.');
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

            $weeklyViolations = $this->shiftService->validateMinimumWeeklyHoursAfterOffday(
                $memberKodes,
                $dateList,
                40.0
            );

            if (! empty($weeklyViolations)) {
                return ResponseHelper::error(
                    $this->formatWeeklyHoursViolationMessage($weeklyViolations),
                    422
                );
            }

            DB::beginTransaction();

            // Classify dates using config
            $classifiedDates = [];
            foreach ($dateList as $tanggal) {
                $classification = $this->classifyDate($tanggal, $today, $karyawan->Kode_Karyawan);
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
                $alasan
            );

            // Direct apply for dates that don't need approval
            $results = [
                'direct_applied' => [],
                'needs_approval' => [],
            ];

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
                                $alasan
                            );
                        } else {
                            $this->shiftService->applyStatusHariDirectForExistingTransaction(
                                $noTransaksi,
                                $kode,
                                $dateItem['tanggal'],
                                'OFF',
                                null,
                                $alasan
                            );
                        }
                        $results['direct_applied'][] = [
                            'kode' => $kode,
                            'tanggal' => $dateItem['tanggal'],
                        ];
                    } else {

                        $results['needs_approval'][] = [
                            'kode' => $kode,
                            'tanggal' => $dateItem['tanggal'],
                        ];
                    }
                }
            }

            if (! empty($results['needs_approval'])) {
                $flowResult = $this->approvalFlowService->createFlowForLegacySingleAppro(
                    $noTransaksi,
                    $karyawan->Kode_Karyawan,
                    3
                );
                $firstApprover = $this->getKaryawanName($flowResult->getFirstApproverKode());
                $onlyDate = array_column($results['needs_approval'], 'tanggal');
                $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($onlyDate);
                // dd($flowResult->getFirstApproverKode());
                $hpApprover = $this->getKaryawanHp($flowResult->getFirstApproverKode());
                // dd($hpApprover);
                $namaApprover = $this->getKaryawanName($flowResult->getFirstApproverKode());
                $tipeTraksaksi = 'STATUS HARI Oleh Leader';
                $namaRequester = 'Tim '.$karyawan->Nama;

                $pesan = $this->whatsappMessageBuilder->Notif_Request_Transaksi_Tanggal($hpApprover, $namaApprover, $tipeTraksaksi, $noTransaksi, $namaRequester, $tanggalFormated);
                $updateWaCounter = function () use ($noTransaksi, $flowResult) {
                    DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Approver_Karyawan_Kode', $flowResult->getFirstApproverKode())
                        ->update([
                            'angka_wa' => DB::raw('ISNULL(angka_wa, 0) + 1'),
                        ]);
                };

                $this->whatsappLogHelper->sendAndLog($flowResult->getFirstApproverKode(), $firstApprover, $hpApprover, $noTransaksi, $pesan, $updateWaCounter);
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

            DB::commit();

            return ResponseHelper::success([
                'no_transaksi' => $noTransaksi,
                'mode' => $mode,
                'total_members' => count($memberKodes),
                'total_dates' => count($classifiedDates),
                'direct_applied' => count($results['direct_applied']),
                'needs_approval_count' => count($results['needs_approval']),
            ], 'Offday berhasil disimpan');
        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::channel('LeaderShiftController')->error('Error submitOffday: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return ResponseHelper::error('Gagal menyimpan offday: '.$e->getMessage(), 500);
        }
    }

    protected function formatWeeklyHoursViolationMessage(array $violations): string
    {
        $memberNames = DB::table('Karyawan')
            ->whereIn('Kode_Karyawan', collect($violations)->pluck('member_kode')->unique()->values()->toArray())
            ->pluck('Nama', 'Kode_Karyawan');

        $first = $violations[0];
        $memberName = $memberNames[$first['member_kode']] ?? $first['member_kode'];
        $weekLabel = Carbon::parse($first['week_start'])->format('d M').' - '.Carbon::parse($first['week_end'])->format('d M Y');

        return sprintf(
            'Pengajuan offday ditolak. Jam kerja mingguan %s pada periode %s menjadi %.2f jam (kurang %.2f jam dari minimum 40 jam).',
            $memberName,
            $weekLabel,
            $first['projected_hours'],
            $first['deficit_hours']
        );
    }

    protected function getKaryawanName(string $kodeKaryawan): ?string
    {
        $karyawan = DB::table('Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->first();

        return $karyawan?->Nama;
    }

    protected function getKaryawanHp(string $kodeKaryawan): ?string
    {
        $karyawan = DB::table('Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->first();

        return $karyawan?->HP;
    }

    /**
     * Get history of leader shift changes
     */
    public function getHistory(Request $request)
    {
        try {
            $user = Auth::user();
            $karyawan = $user->karyawan;

            $filters = [
                'status' => $request->input('status', 'all'),
                'search' => $request->input('search'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'transaction_type' => $request->input('transaction_type', 'all'),
                'created_at' => $request->input('created_at'),
            ];

            $params = [
                'page' => $request->input('page', 1),
                'limit' => $request->input('limit', 10),
                'filters' => $filters,
            ];

            $result = $this->shiftService->getLeaderShiftHistory($karyawan->Kode_Karyawan, $user->Id_Users, $params);

            return ResponseHelper::successWithPaginationV2(
                $result['items'],
                $result['page'] ?? 1,
                $result['limit'] ?? 10,
                $result['total'],
                'Data riwayat berhasil dimuat'
            );
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error('Error getHistory: '.$e->getMessage());

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
            Log::channel('LeaderShiftController')->error('Error getDetail: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat detail', 500);
        }
    }

    /**
     * Cancel leader transaction (header/detail/det)
     */
    public function cancelHistory(Request $request)
    {
        try {
            $validated = $request->validate([
                'no_transaksi' => 'required|string',
                'scope' => 'required|in:header,detail,det',
                'detail_id' => 'nullable|integer',
                'det_id' => 'nullable|integer',
            ]);

            $result = $this->shiftService->cancelLeaderShiftTransaction(
                $validated['no_transaksi'],
                $validated['scope'],
                $validated['detail_id'] ?? null,
                $validated['det_id'] ?? null
            );

            return ResponseHelper::success($result, 'Pengajuan berhasil dibatalkan');
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error('Error cancelHistory: '.$e->getMessage());

            return ResponseHelper::error($e->getMessage(), 422);
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
                        ->join('Lembur_Header as lh', 'ld.No_Transaksi', '=', 'lh.No_Transaksi')
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

            return ResponseHelper::success($conflicts, 'Data konflik berhasil dicek');
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error('Error checkConflicts: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengecek konflik', 500);
        }
    }

    public function checkPermanentConflicts(Request $request)
    {
        try {
            $request->validate([
                'members' => 'required|array|min:1',
                'members.*' => 'string',
                'date' => 'nullable|date',
                'dates' => 'nullable|array',
                'dates.*' => 'date',
            ]);

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

            foreach ($members as $memberKode) {
                foreach ($dates as $tanggal) {
                    $count = $this->shiftService->checkPermanentConflict(
                        $memberKode,
                        $tanggal
                    );
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
                'Data konflik permanen berhasil dicek'
            );
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error('Error checkPermanentConflicts: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengecek konflik permanen', 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    protected function getStats(string $leaderKode): array
    {
        try {
            // Get team count
            $teamCount = DB::table('Karyawan as k')
                ->join('HRIS_Karyawan_Team as kt', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan')
                ->where('kt.Kode_Karyawan_Team', $leaderKode)
                ->count();
            // dd($teamCount);

            // Get pending approval count (for this leader's requests)
            $pendingCount = DB::table('N_HRIS_Transaksi_Shift')
                ->where('Created_By', $leaderKode)
                ->where('Jenis_Transaksi', 'LEADER_SHIFT')
                ->whereNull('Status')
                ->where(function ($q) {
                    $q->whereNull('Flag_Selesai')
                        ->orWhere('Flag_Selesai', '!=', 'Y');
                })
                ->count();

            $pendingCountStatusHari = DB::table('N_HRIS_Transaksi_Status_Hari')
                ->where('Created_By', $leaderKode)
                ->where('Jenis_Transaksi', 'STATUS_HARI')
                ->whereNull('Status')
                ->where(function ($q) {
                    $q->whereNull('Flag_Selesai')
                        ->orWhere('Flag_Selesai', '!=', 'Y');
                })
                ->count();

            // Get approved this month
            $now = FormatTanggalHelper::getCurrentTime('leader-shift');
            $approvedCount = DB::table('N_HRIS_Transaksi_Shift')
                ->where('Created_By', $leaderKode)
                ->where('Flag_Selesai', 'Y')
                ->whereNull('Status')
                ->count();

            $approvedCountStatusHari = DB::table('N_HRIS_Transaksi_Status_Hari')
                ->where('Created_By', $leaderKode)
                ->where('Flag_Selesai', 'Y')
                ->whereNull('Status')
                ->count();
            // dd($teamCount);

            $result = [
                'team_count' => $teamCount,
                'pending_count' => ($pendingCount + $pendingCountStatusHari) ?? 0,
                'approved_month' => ($approvedCount + $approvedCountStatusHari) ?? 0,
            ];
            // dd($teamCount, $pendingCount, $approvedCount);

            Log::channel('LeaderShiftController')->info('getStats Result', $result);

            return $result;
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error('Error getStats: '.$e->getMessage());

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
            $approver = DB::table('N_HRIS_Approval_Flow_Shift as afs')
                ->join('Karyawan as k', 'afs.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'k.Id_Level_Jabatan', '=', 'v.Id_Level_Jabatan')
                ->where('afs.Kode_Karyawan_Requester', $leaderKode)
                ->whereNull('afs.Status')
                ->select('k.Kode_Karyawan', 'k.Nama', 'v.nama_jabatan')
                ->first();

            if ($approver) {
                return [
                    'kode' => $approver->Kode_Karyawan,
                    'nama' => $approver->Nama,
                    'jabatan' => $approver->nama_jabatan,
                ];
            }

            return null;
        } catch (Exception $e) {
            Log::channel('LeaderShiftController')->error('Error getLeaderApprover: '.$e->getMessage());

            return null;
        }
    }
}
