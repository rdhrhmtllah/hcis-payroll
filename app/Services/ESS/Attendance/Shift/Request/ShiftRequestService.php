<?php

namespace App\Services\ESS\Attendance\Shift\Request;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\ESS\Attendance\Shift\Leadership\LeaderShiftController;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use App\Services\ESS\Attendance\Shift\Admin\PengajuanShiftAdminService;
use App\Services\SYSTEM\Application\Database\PenyisipanBerbatasParameter;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalDetailService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService;
use App\Services\SYSTEM\Governance\Authorization\Shared\AccessContext;
use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;
use App\Services\SYSTEM\Notification\WhatsappMessageBuilder;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Service for Self Shift Request feature
 *
 * Handles shift request submission, approval checking, and shift application.
 */
class ShiftRequestService
{
    protected ApprovalFlowService $approvalFlowService;

    protected ApprovalRequestService $approvalRequestService;

    protected ApprovalDetailService $approvalDetailService;

    protected WhatsappLogHelper $whatsappLogHelper;

    protected WhatsappMessageBuilder $whatsappMessageBuilder;

    protected FunctionalRole $role;

    protected AccessContext $accessContext;

    protected AttendanceDirtyResolverService $attendanceDirtyResolver;

    protected array $config = [];

    protected array $swapConfig = [];

    protected array $shiftRuleMemberCache = [];
    // Configuration
    // protected array $config = [
    //     'backdate_needs_approval' => config('services.hcis.shift_request.backdate_needs_approval', true),
    //     'same_day_needs_approval' => config('services.hcis.shift_request.same_day_needs_approval', true),
    //     'future_needs_approval' => config('services.hcis.shift_request.future_needs_approval', true),
    //     'backdate_max_days' => config('services.hcis.shift_request.backdate_max_days', 7),
    //     'future_max_days' => config('services.hcis.shift_request.future_max_days', 30)
    // ];

    public function __construct(
        ApprovalFlowService $approvalFlowService,
        ApprovalRequestService $approvalRequestService,
        ApprovalDetailService $approvalDetailService,
        WhatsappLogHelper $whatsappLogHelper,
        WhatsappMessageBuilder $whatsappMessageBuilder,
        FunctionalRole $role,
        AccessContext $accessContext,
        HrisAttendanceSnapshotService $attendanceSnapshotService,
        AttendanceDirtyResolverService $attendanceDirtyResolver,
    ) {
        $this->approvalFlowService = $approvalFlowService;
        $this->approvalRequestService = $approvalRequestService;
        $this->approvalDetailService = $approvalDetailService;
        $this->whatsappLogHelper = $whatsappLogHelper;
        $this->whatsappMessageBuilder = $whatsappMessageBuilder;
        $this->role = $role;
        $this->accessContext = $accessContext;

        $this->attendanceService = $attendanceSnapshotService;
        $this->attendanceDirtyResolver = $attendanceDirtyResolver;
        $this->config = [
            'backdate_needs_approval' => config('services.hcis.shift_request.backdate_needs_approval', true),
            'same_day_needs_approval' => config('services.hcis.shift_request.same_day_needs_approval', true),
            'future_needs_approval' => config('services.hcis.shift_request.future_needs_approval', true),
            'backdate_max_days' => (int) config('services.hcis.shift_request.backdate_max_days', 7),
            'future_max_days' => (int) config('services.hcis.shift_request.future_max_days', 30),
        ];

        $this->swapConfig = [
            'backdate_needs_approval' => config('services.hcis.swap_shift_request.backdate_needs_approval', true),
            'same_day_needs_approval' => config('services.hcis.swap_shift_request.same_day_needs_approval', true),
            'future_needs_approval' => config('services.hcis.swap_shift_request.future_needs_approval', true),
            'backdate_max_days' => (int) config('services.hcis.swap_shift_request.backdate_max_days', 7),
            'future_max_days' => (int) config('services.hcis.swap_shift_request.future_max_days', 30),
        ];
    }

    /**
     * Submit self shift request (REFACTORED)
     * Uses 3-tier structure + dynamic approval config + Is_Mandatory support
     *
     * @param  array  $data  Request data containing target_table, alasan, dates[]
     * @param  string  $kodeKaryawan  Requester's kode
     * @return array Result with applied and pending counts
     */
    public function submit(array $data, ?string $kodeKaryawan): array
    {
        if (! $kodeKaryawan) {
            throw new \Exception('Karyawan tidak ditemukan.');
        }

        // === PRA-TRANSAKSI (di LUAR lock) ===
        // 1) Prakomputasi shift-sebelumnya: getUserShiftForDate menjalankan SP absen
        //    SYNC (berat) per tanggal — jangan di dalam transaksi (menahan lock).
        $previousShiftMap = [];
        foreach (($data['dates'] ?? []) as $item) {
            $tgl = $item['tanggal'] ?? null;
            if ($tgl === null || array_key_exists($tgl, $previousShiftMap)) {
                continue;
            }
            $previousShiftMap[$tgl] = $this->getUserShiftForDate($kodeKaryawan, Carbon::parse($tgl));
        }

        // 2) Reserve No_Transaksi via counter atomik SEBELUM transaksi (kunci baris
        //    counter tak tertahan sampai commit).
        $reservedNoTransaksi = $this->reserveNoTransaksi();

        return DB::transaction(function () use ($data, $kodeKaryawan, $previousShiftMap, $reservedNoTransaksi) {
            if (! $kodeKaryawan) {
                throw new \Exception('Karyawan tidak ditemukan.');
            }

            $namaRequester = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->value('Nama');
            $hpRequester = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->value('HP');
            $targetTable = $data['target_table'];
            $alasan = $data['alasan'] ?? null;
            $overwriteSementara = (bool) ($data['overwrite_sementara'] ?? false);
            $dates = $data['dates'];

            // Check for pending requests on these dates
            $pendingDates = $this->checkPendingRequest($dates, $kodeKaryawan);
            if (! empty($pendingDates)) {
                $dateList = implode(', ', $pendingDates);
                throw new \Exception(
                    "Tanggal berikut masih memiliki pengajuan shift (Status Pending/On Process): $dateList",
                );
            }

            $ruleViolations = $this->validateShiftAssignmentsAgainstRules(
                collect($dates)
                    ->map(function ($item) use ($kodeKaryawan) {
                        return [
                            'kode_karyawan' => $kodeKaryawan,
                            'tanggal' => $item['tanggal'] ?? null,
                            'id_shift' => $item['id_shift'] ?? null,
                        ];
                    })
                    ->toArray(),
            );
            if (! empty($ruleViolations)) {
                $first = $ruleViolations[0];
                throw new \Exception(
                    "Shift {$first['id_shift']} tidak tersedia untuk {$first['kode_karyawan']} pada tanggal {$first['tanggal']} (sesuai rule shifting).",
                );
            }

            $needsApprovalDates = [];
            $directApplyDates = [];

            // Classify dates using approval engine (same as leader shift)
            foreach ($dates as $item) {
                $tanggal = Carbon::parse($item['tanggal']);
                $classification = $this->classifyDateForSelfShift(
                    $kodeKaryawan,
                    $tanggal,
                    null, // No specific config override for self shift
                );

                $item['jenis_pengajuan'] = $classification['jenis_pengajuan'];
                $item['needs_approval'] = $classification['needs_approval'];
                $item['decision_notes'] = $classification['decision_notes'] ?? null;

                if ($classification['needs_approval']) {
                    $needsApprovalDates[] = $item;
                } else {
                    $directApplyDates[] = $item;
                }
            }

            $result = [
                'applied_directly' => [
                    'count' => 0,
                    'dates' => [],
                ],
                'pending_approval' => [
                    'count' => 0,
                    'no_transaksi' => null,
                    'dates' => [],
                    'first_approver' => null,
                ],
            ];

            // === NEW 3-TIER STRUCTURE ===
            // Tier 1: Create Header (No_Transaksi sudah di-reserve di luar transaksi)
            $noTransaksi = $this->createHeader('SELF_SHIFT', $alasan, null, $overwriteSementara, $reservedNoTransaksi);

            // Tier 2: Create Detail for this single karyawan
            $idDetail = $this->createKaryawanDetail($noTransaksi, $kodeKaryawan, $targetTable, $alasan);

            // Tier 3: Create Dets for all dates (both direct apply + needs approval)
            $allFormattedDates = [];

            // 1. Direct apply dates (no approval needed)
            foreach ($directApplyDates as $item) {
                $previousShift = $previousShiftMap[$item['tanggal']] ?? $this->getUserShiftForDate($kodeKaryawan, Carbon::parse($item['tanggal']));
                $allFormattedDates[] = [
                    'tanggal' => $item['tanggal'],
                    'id_shift' => $item['id_shift'],
                    'id_shift_sebelumnya' => $previousShift,
                    'jenis_pengajuan' => $item['jenis_pengajuan'],
                    'needs_approval' => false,
                    'flag_approval' => 'Y', // Already approved
                    'flag_applied' => 'T', // Will be applied below
                ];
            }

            // 2. Approval-needed dates
            foreach ($needsApprovalDates as $item) {
                $previousShift = $previousShiftMap[$item['tanggal']] ?? $this->getUserShiftForDate($kodeKaryawan, Carbon::parse($item['tanggal']));
                $allFormattedDates[] = [
                    'tanggal' => $item['tanggal'],
                    'id_shift' => $item['id_shift'],
                    'id_shift_sebelumnya' => $previousShift,
                    'jenis_pengajuan' => $item['jenis_pengajuan'],
                    'needs_approval' => true,
                    'flag_approval' => null, // Pending approval
                    'flag_applied' => 'T', // Not yet applied
                    'Decision_Approval_Notes' => $item['decision_notes'] ?? null,
                ];
            }

            // Create all Dets at once
            $this->createShiftDet($idDetail, $noTransaksi, $allFormattedDates);

            // Update Detail flag based on all Dets
            if (empty($needsApprovalDates)) {
                // All dates are direct apply → Detail = 'Y'
                DB::table('N_HRIS_Transaksi_Shift_Detail')
                    ->where('Id', $idDetail)
                    ->whereNull('Status')
                    ->update(['Flag_Approval' => 'Y']);
            }

            // Apply direct dates immediately
            if (! empty($directApplyDates)) {
                foreach ($directApplyDates as $item) {
                    $this->applyShiftDirect($kodeKaryawan, $item, $targetTable, null, $overwriteSementara);
                    $result['applied_directly']['count']++;
                    $result['applied_directly']['dates'][] = $item['tanggal'];
                }

                // Update Det Flag_Applied for direct dates
                DB::table('N_HRIS_Transaksi_Shift_Det')
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Needs_Approval', 'T')
                    ->whereNull('Status')
                    ->update(['Flag_Applied' => 'Y']);

                // If all dates are direct apply → Mark header as complete
                if (empty($needsApprovalDates)) {
                    DB::table('N_HRIS_Transaksi_Shift')
                        ->where('No_Transaksi', $noTransaksi)
                        ->update([
                            'Flag_Selesai' => 'Y',
                            'Updated_At' => now(),
                            'Updated_By' => Auth::id(),
                        ]);
                }
            }

            // Create approval flow for pending dates
            if (! empty($needsApprovalDates)) {
                // Determine approval config based on date types
                $hasBackdate = collect($needsApprovalDates)->contains(
                    fn ($item) => ($item['jenis_pengajuan'] ?? null) === 'BACKDATE',
                );
                $configCode = $hasBackdate ? 'SELF_SHIFT_BACKDATE' : 'SELF_SHIFT';

                // Create approval flow using dynamic config
                $flowResult = $this->approvalFlowService->createFlowFromApprovalConfig(
                    $noTransaksi,
                    $kodeKaryawan,
                    $configCode,
                );

                // VALIDATION: Fail transaction if approval config not found
                if (! $flowResult->success) {
                    throw new \Exception(
                        'Konfigurasi approval untuk '.$configCode.' tidak ditemukan. '.$flowResult->error,
                    );
                }

                // ✅ CREATE APPROVAL DETAILS FROM SOURCE VIEW (Generic Approval Architecture)
                // Each approver gets THEIR OWN copy of detail records (ISOLATED model)
                // This allows independent approval decisions while maintaining audit trail
                try {
                    // Get all approval requests created by flow (one per approver step)
                    $approvalReqs = DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Status')
                        ->orderBy('Request_Order_Flow', 'asc')
                        ->get();

                    if ($approvalReqs->isNotEmpty()) {
                        foreach ($approvalReqs as $approvalReq) {
                            // Check if THIS APPROVER already has detail records (per-approver isolation)
                            $exists = DB::table('HRIS_Approval_Request_Detail')
                                ->where('Id_Approval_Request', $approvalReq->id)
                                ->exists();

                            if ($exists) {
                                Log::channel('ApprovalDetailService')->info(
                                    'Details already created for this approver',
                                    [
                                        'id_approval_request' => $approvalReq->id,
                                        'approver' => $approvalReq->Approver_Karyawan_Kode,
                                        'flow_step' => $approvalReq->Request_Order_Flow,
                                    ],
                                );

                                continue;
                            }

                            $idJenisApproval = (int) ($approvalReq->Id_Jenis_Approval ?? 0);
                            if ($idJenisApproval <= 0) {
                                Log::channel('ApprovalDetailService')->warning(
                                    'Id_Jenis_Approval missing on approval request',
                                    [
                                        'no_transaksi' => $noTransaksi,
                                        'id_approval_request' => $approvalReq->id,
                                        'approver' => $approvalReq->Approver_Karyawan_Kode,
                                    ],
                                );

                                continue;
                            }

                            // Create details FOR THIS APPROVER (isolated per approval request)
                            $createdCount = $this->approvalDetailService->createFromSourceView(
                                $approvalReq->id,
                                $noTransaksi,
                                'N_HRIS_VW_SRC_SHIFT',
                                $idJenisApproval,
                            );

                            Log::channel('ApprovalDetailService')->info('Approval details created for approver', [
                                'no_transaksi' => $noTransaksi,
                                'jenis' => 'SHIFT',
                                'id_approval_request' => $approvalReq->id,
                                'approver' => $approvalReq->Approver_Karyawan_Kode,
                                'flow_step' => $approvalReq->Request_Order_Flow,
                                'created_count' => $createdCount,
                            ]);
                        }
                    } else {
                        Log::channel('ApprovalDetailService')->warning('No approval requests found to create details', [
                            'no_transaksi' => $noTransaksi,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Error creating approval details from source view: '.$e->getMessage());
                    Log::channel('ApprovalDetailService')->error('Exception creating approval details', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'no_transaksi' => $noTransaksi,
                    ]);
                    // Don't fail transaction, just log warning
                }

                // CHECK: If all approvers are Is_Mandatory='T' (optional audit only)
                // → Auto-complete immediately but keep approval records for audit trail
                $canAutoComplete = $this->approvalRequestService->canAutoCompleteWithoutChain($noTransaksi);

                if ($canAutoComplete) {
                    // All approvers are optional (Is_Mandatory='T')
                    // → Auto-approve all pending Dets and apply shifts immediately
                    DB::table('N_HRIS_Transaksi_Shift_Det')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Needs_Approval', 'Y')
                        ->whereNull('Status')

                        ->whereNull('Flag_Approval')
                        ->update([
                            'Flag_Approval' => 'Y',
                            'Flag_Applied' => 'T',
                        ]);

                    // Update Detail to 'Y' as well
                    $this->updateDetailApprovalStatusSmart($noTransaksi);

                    // Apply shifts immediately
                    $this->applyShiftAfterApproval($noTransaksi);

                    // Mark header as complete
                    DB::table('N_HRIS_Transaksi_Shift')
                        ->where('No_Transaksi', $noTransaksi)
                        ->update([
                            'Flag_Selesai' => 'Y',
                            'Updated_At' => now(),
                            'Updated_By' => Auth::id(),
                        ]);

                    // Still send WA notification to all optional approvers (audit trail)
                    $allApprovers = DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Status')
                        ->get();

                    foreach ($allApprovers as $approver) {
                        try {
                            $namaApprover = $this->getKaryawanName($approver->Approver_Karyawan_Kode);
                            $hpApprover = $this->getKaryawanHp($approver->Approver_Karyawan_Kode);
                            $onlyDate = array_column($needsApprovalDates, 'tanggal');
                            $tipeTransaksi = 'Pergantian Shift (Audit Only)';
                            $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($onlyDate);

                            $pesan = $this->whatsappMessageBuilder->approverShiftNotification(
                                $hpApprover,
                                $noTransaksi,
                                $namaRequester,
                                $tanggalFormated,
                            );
                            $this->whatsappLogHelper->sendAndLog(
                                $approver->Approver_Karyawan_Kode,
                                $namaApprover,
                                $hpApprover,
                                $noTransaksi,
                                $pesan,
                            );
                        } catch (\Throwable $e) {
                            Log::error(
                                'WA Notification Failed (Optional Approver Audit - Self Shift): '.$e->getMessage(),
                            );
                        }
                    }

                    $result['pending_approval']['count'] = 0; // Auto-completed
                    $result['pending_approval']['no_transaksi'] = $noTransaksi;
                    $result['pending_approval']['first_approver'] = 'Auto-Completed (All Optional)';

                    // Return early
                    return $result;
                }

                // Normal flow: Send notification to first mandatory approver
                $result['pending_approval']['count'] = count($needsApprovalDates);
                $result['pending_approval']['no_transaksi'] = $noTransaksi;
                $result['pending_approval']['dates'] = array_column($needsApprovalDates, 'tanggal');

                $firstApprover = $this->getKaryawanName($flowResult->getFirstApproverKode());
                $onlyDate = array_column($needsApprovalDates, 'tanggal');
                $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($onlyDate);
                $hpApprover = $this->getKaryawanHp($flowResult->getFirstApproverKode());

                $pesan = $this->whatsappMessageBuilder->approverShiftNotification(
                    $hpApprover,
                    $noTransaksi,
                    $namaRequester,
                    $tanggalFormated,
                );
                $updateWaCounter = function () use ($noTransaksi, $flowResult) {
                    DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Approver_Karyawan_Kode', $flowResult->getFirstApproverKode())
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

                $result['pending_approval']['first_approver'] = $firstApprover;
            }

            return $result;
        });
    }

    /**
     * Submit swap day request
     *
     * @param  array  $data  Request data containing date, swap_date, make_dayoff, shift_id, alasan
     * @param  string  $kodeKaryawan  Requester's kode
     * @return array Result with applied and pending counts
     */
    /**
     * Submit tukar hari (swap day) request
     * Creates transaction in N_HRIS_Transaksi_Tukar_Hari with separate header + detail records
     *
     * @param  array  $data  Contains: date, swap_date, shift_id, make_dayoff, alasan
     * @param  string  $kodeKaryawan  Requester's employee code
     * @return array Result with transaction number and approval status
     */
    public function submitSwapHari(array $data, ?string $kodeKaryawan): array
    {
        return DB::transaction(function () use ($data, $kodeKaryawan) {
            if (! $kodeKaryawan) {
                throw new \Exception('Karyawan tidak ditemukan.');
            }

            // Extract request data
            $originDate = $data['date'];
            $swapDate = $data['swap_date'];
            $makeDayoff = filter_var($data['make_dayoff'], FILTER_VALIDATE_BOOLEAN);
            $shiftId = (int) $data['shift_id'];
            $alasan = $data['alasan'] ?? null;

            // Determine working date and off date
            $tanggalBekerja = $makeDayoff ? $originDate : $swapDate;
            $tanggalOff = $makeDayoff ? $swapDate : $originDate;

            // Get karyawan info
            $namaRequester = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->value('Nama');
            $hpRequester = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->value('HP');

            // Get company code from karyawan
            $kodePerusahaan =
                DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->value('Kode_Perusahaan') ?? '001';

            // Check for pending requests on these dates
            $pendingDates = $this->checkPendingRequest(
                [['tanggal' => $tanggalBekerja], ['tanggal' => $tanggalOff]],
                $kodeKaryawan,
            );
            if (! empty($pendingDates)) {
                $dateList = implode(', ', $pendingDates);
                throw new \Exception(
                    "Tanggal berikut masih memiliki pengajuan shift (Status Pending/On Process): $dateList",
                );
            }

            // Determine if approval is needed based on dates
            $tanggalBekerjaCarbon = Carbon::parse($tanggalBekerja);
            $tanggalOffCarbon = Carbon::parse($tanggalOff);
            $needsApprovalBekerja = $this->checkNeedsApprovalSwap(
                $this->determineJenisPengajuan($tanggalBekerjaCarbon),
            );
            $needsApprovalOff = $this->checkNeedsApprovalSwap($this->determineJenisPengajuan($tanggalOffCarbon));
            $needsApproval = $needsApprovalBekerja || $needsApprovalOff;

            // Generate transaction number
            $noTransaksi = $this->generateNoTransaksiTukarHari();

            $useridweb = Auth::id();

            // Create header in N_HRIS_Transaksi_Tukar_Hari
            DB::table('N_HRIS_Transaksi_Tukar_Hari')->insert([
                'No_Transaksi' => $noTransaksi,
                'Kode_Perusahaan' => $kodePerusahaan,
                'Kode_Karyawan' => $kodeKaryawan,
                'Alasan' => $alasan,
                'Created_By' => $useridweb,
            ]);

            // Create detail 1: Tanggal_Bekerja (working date with shift)
            DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')->insert([
                'Kode_Perusahaan' => $kodePerusahaan,
                'No_Transaksi' => $noTransaksi,
                'Kode_Karyawan' => $kodeKaryawan,
                'Tanggal_Bekerja' => $tanggalBekerja,
                'Tanggal_Off' => $tanggalOff,
                'Id_Shift_Bekerja' => $shiftId,
                'Flag_Approval' => $needsApproval ? null : 'Y',
            ]);

            $result = [
                'no_transaksi' => $noTransaksi,
                'status' => 'submitted',
                'needs_approval' => $needsApproval,
                'applied_directly' => ! $needsApproval,
                'first_approver' => null,
            ];

            // If no approval needed, apply shifts immediately
            if (! $needsApproval) {
                // Apply only working date shift to HRIS_Shift_Sementara
                $this->applyShiftDirect(
                    $kodeKaryawan,
                    [
                        'tanggal' => $tanggalBekerja,
                        'id_shift' => $shiftId,
                    ],
                    'SEMENTARA',
                );

                // Mark header as complete
                DB::table('N_HRIS_Transaksi_Tukar_Hari')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update(['Flag_Selesai' => 'Y']);

                $result['status'] = 'approved_and_applied';
            } else {
                // Create approval flow using V2 dynamic config (same as shift)
                // Determine config based on dates
                $jenisPengajuanBekerja = $this->determineJenisPengajuan($tanggalBekerjaCarbon);
                $jenisPengajuanOff = $this->determineJenisPengajuan($tanggalOffCarbon);
                $hasBackdate = $jenisPengajuanBekerja === 'BACKDATE' || $jenisPengajuanOff === 'BACKDATE';
                $configCode = $hasBackdate ? 'TUKAR_HARI_BACKDATE' : 'TUKAR_HARI';

                // Create approval flow using dynamic config (V2 pattern)
                $flowResult = $this->approvalFlowService->createFlowFromApprovalConfig(
                    $noTransaksi,
                    $kodeKaryawan,
                    $configCode,
                );

                // VALIDATION: Fail transaction if approval config not found
                if (! $flowResult->success) {
                    throw new \Exception(
                        'Konfigurasi approval untuk '.$configCode.' tidak ditemukan. '.$flowResult->error,
                    );
                }

                // ✅ CREATE APPROVAL DETAILS FROM SOURCE VIEW (Generic Approval Architecture)
                // Each approver gets THEIR OWN copy of detail records (ISOLATED model)
                try {
                    // Get all approval requests created by flow (one per approver step)
                    $approvalReqs = DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Status')
                        ->orderBy('Request_Order_Flow', 'asc')
                        ->get();

                    if ($approvalReqs->isNotEmpty()) {
                        foreach ($approvalReqs as $approvalReq) {
                            // Check if THIS APPROVER already has detail records
                            $exists = DB::table('HRIS_Approval_Request_Detail')
                                ->where('Id_Approval_Request', $approvalReq->id)
                                ->exists();

                            if ($exists) {
                                Log::channel('ApprovalDetailService')->info(
                                    'Details already created for Tukar Hari approver',
                                    [
                                        'id_approval_request' => $approvalReq->id,
                                        'approver' => $approvalReq->Approver_Karyawan_Kode,
                                        'flow_step' => $approvalReq->Request_Order_Flow,
                                    ],
                                );

                                continue;
                            }

                            $idJenisApproval = (int) ($approvalReq->Id_Jenis_Approval ?? 0);
                            if ($idJenisApproval <= 0) {
                                Log::channel('ApprovalDetailService')->warning(
                                    'Id_Jenis_Approval missing on Tukar Hari approval request',
                                    [
                                        'no_transaksi' => $noTransaksi,
                                        'id_approval_request' => $approvalReq->id,
                                    ],
                                );

                                continue;
                            }

                            // Create details FOR THIS APPROVER (isolated per approval request)
                            $createdCount = $this->approvalDetailService->createFromSourceView(
                                $approvalReq->id,
                                $noTransaksi,
                                'N_HRIS_VW_SRC_TUKAR_HARI',
                                $idJenisApproval,
                            );

                            Log::channel('ApprovalDetailService')->info(
                                'Approval details created for Tukar Hari approver',
                                [
                                    'no_transaksi' => $noTransaksi,
                                    'jenis' => 'TUKAR_HARI',
                                    'id_approval_request' => $approvalReq->id,
                                    'approver' => $approvalReq->Approver_Karyawan_Kode,
                                    'flow_step' => $approvalReq->Request_Order_Flow,
                                    'created_count' => $createdCount,
                                ],
                            );
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error creating Tukar Hari approval details: '.$e->getMessage());
                    Log::channel('ApprovalDetailService')->error('Exception creating Tukar Hari approval details', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'no_transaksi' => $noTransaksi,
                    ]);
                }

                // CHECK: If all approvers are optional (Is_Mandatory='T')
                // → Auto-complete immediately but keep approval records for audit trail
                $canAutoComplete = $this->approvalRequestService->canAutoCompleteWithoutChain($noTransaksi);

                if ($canAutoComplete) {
                    // All approvers are optional → Auto-approve and apply immediately
                    DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Flag_Approval')
                        ->update(['Flag_Approval' => 'Y']);

                    // Apply shift
                    $this->applyShiftDirect(
                        $kodeKaryawan,
                        [
                            'tanggal' => $tanggalBekerja,
                            'id_shift' => $shiftId,
                        ],
                        'SEMENTARA',
                    );

                    // Mark header as complete
                    DB::table('N_HRIS_Transaksi_Tukar_Hari')
                        ->where('No_Transaksi', $noTransaksi)
                        ->update(['Flag_Selesai' => 'Y']);

                    // Still send WA to all optional approvers (audit trail)
                    $allApprovers = DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Status')
                        ->get();

                    foreach ($allApprovers as $approver) {
                        try {
                            $namaApprover = $this->getKaryawanName($approver->Approver_Karyawan_Kode);
                            $hpApprover = $this->getKaryawanHp($approver->Approver_Karyawan_Kode);
                            $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple([
                                $tanggalBekerja,
                                $tanggalOff,
                            ]);

                            $pesan = $this->whatsappMessageBuilder->approverShiftNotification(
                                $hpApprover,
                                $noTransaksi,
                                $namaRequester,
                                $tanggalFormated,
                            );
                            $this->whatsappLogHelper->sendAndLog(
                                $approver->Approver_Karyawan_Kode,
                                $namaApprover,
                                $hpApprover,
                                $noTransaksi,
                                $pesan,
                            );
                        } catch (\Throwable $e) {
                            Log::error('WA Notification Failed (Optional Approver - Tukar Hari): '.$e->getMessage());
                        }
                    }

                    $result['status'] = 'approved_and_applied';
                    $result['first_approver'] = 'Auto-Completed (All Optional)';

                    return $result;
                }

                // Normal flow: Send notification to first mandatory approver
                $firstApprover = $this->getKaryawanName($flowResult->getFirstApproverKode());
                $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple([$tanggalBekerja, $tanggalOff]);
                $hpApprover = $this->getKaryawanHp($flowResult->getFirstApproverKode());

                $pesan = $this->whatsappMessageBuilder->approverShiftNotification(
                    $hpApprover,
                    $noTransaksi,
                    $namaRequester,
                    $tanggalFormated,
                );

                $updateWaCounter = function () use ($noTransaksi, $flowResult) {
                    DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Approver_Karyawan_Kode', $flowResult->getFirstApproverKode())
                        ->update([
                            'angka_wa' => DB::raw('ISNULL(angka_wa, 0) + 1'),
                        ]);
                };

                try {
                    $this->whatsappLogHelper->sendAndLog(
                        $flowResult->getFirstApproverKode(),
                        $firstApprover,
                        $hpApprover,
                        $noTransaksi,
                        $pesan,
                        $updateWaCounter,
                    );
                } catch (\Throwable $e) {
                    Log::error('WA Notification Failed (Tukar Hari): '.$e->getMessage());
                }

                $result['first_approver'] = $firstApprover;
                $result['status'] = 'waiting_approval';
            }

            return $result;
        });
    }

    /**
     * Generate transaction number
     */
    /**
     * Reserve nomor transaksi shift secara ATOMIK (anti-duplikat, sargable).
     *
     * PENTING: panggil SEBELUM membuka transaksi bisnis. Counter memakai autocommit
     * (kunci baris lepas seketika); bila dipanggil di dalam transaksi caller, kunci
     * baris counter ikut tertahan sampai commit → menserialisasi semua submit shift.
     * Karena autocommit, nomor yang sudah diambil TIDAK dikembalikan bila transaksi
     * caller rollback (boleh ada gap — gap wajar, duplikat tidak).
     */
    public function reserveNoTransaksi(): string
    {
        return $this->generateNoTransaksi();
    }

    protected function generateNoTransaksi(): string
    {
        // Prefix penuh: "SH{mm}-{yy}-" → nomor akhir "SHmm-yy-0001" (format lama dipertahankan).
        $prefix = sprintf('SH%s-%s-', date('m'), date('y'));

        return app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
            $prefix,
            function () use ($prefix) {
                // Self-seeding: ambil urut tertinggi yang sudah ada untuk prefix ini (sekali per prefix).
                $last = DB::table('N_HRIS_Transaksi_Shift')
                    ->where('No_Transaksi', 'like', $prefix.'%')
                    ->orderBy('Id', 'desc')
                    ->value('No_Transaksi');

                if (! $last) {
                    return 0;
                }

                $parts = explode('-', $last);

                return intval(end($parts));
            },
            4,
        );
    }

    // =========================================================================
    // NEW 3-TIER TRANSACTION METHODS
    // =========================================================================

    /**
     * Create transaction header (Tier 1)
     * No Kode_Karyawan - employees are in Detail tier
     *
     * @param  string  $jenisTransaksi  'SELF_SHIFT' | 'LEADER_SHIFT'
     * @return string No_Transaksi
     */
    protected function createHeader(
        string $jenisTransaksi,
        ?string $alasan = null,
        ?AccessContext $context = null,
        bool $overwriteSementara = false,
        ?string $preReservedNoTransaksi = null,
    ): string {
        // Bila caller sudah me-reserve nomor DI LUAR transaksi (agar kunci counter tak
        // tertahan sampai commit), pakai itu; kalau tidak, reserve sekarang (fallback lama).
        $noTransaksi = $preReservedNoTransaksi ?? $this->generateNoTransaksi();
        $userId = Auth::id();
        if (! $userId) {
            throw new \Exception('User tidak terautentikasi.');
        }
        $flagOverwrite = $overwriteSementara ? 'Y' : 'T';

        DB::table('N_HRIS_Transaksi_Shift')->insert([
            'No_Transaksi' => $noTransaksi,
            'Kode_Perusahaan' => '001',
            'Alasan' => $alasan,
            'Jenis_Transaksi' => $jenisTransaksi,
            'Status' => null,
            'Flag_Selesai' => null,
            'Created_At' => now(),
            'Created_By' => $userId,
            'Flag_Admin' => $context?->isAdmin() ? 'Y' : 'T',
            'Flag_Overwrite_Sementara' => $flagOverwrite,
        ]);

        return $noTransaksi;
    }

    /**
     * Create karyawan detail record (Tier 2)
     *
     * @param  string  $noTransaksi  FK to Header
     * @param  string  $targetTable  'SEMENTARA' | 'PERMANEN'
     * @return int Id of created record (for FK to Tier 3)
     */
    protected function createKaryawanDetail(
        string $noTransaksi,
        string $kodeKaryawan,
        string $targetTable,
        ?string $alasan = null,
        ?string $filePath = null,
    ): int {
        return DB::table('N_HRIS_Transaksi_Shift_Detail')->insertGetId([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan' => $kodeKaryawan,
            'Target_Table' => $targetTable,
            'Alasan' => $alasan,
            'FilePath' => $filePath,
            'Flag_Approval' => null,
            'Note_Tolak' => null,
            'Status' => null,
        ]);
    }

    /**
     * Create shift date records (Tier 3)
     *
     * @param  int  $idDetail  FK to Detail (Tier 2)
     * @param  string  $noTransaksi  FK to Header
     * @param  array  $dates  Array of [{tanggal, id_shift, id_shift_sebelumnya?, jenis_pengajuan, needs_approval}]
     */
    protected function createShiftDet(int $idDetail, string $noTransaksi, array $dates): void
    {
        /*
         * Satu INSERT per tanggal, di dalam transaksi pengajuan. Pengajuan shift
         * sebulan penuh berarti tiga puluh perjalanan bolak-balik yang menahan
         * kunci berturut-turut. Barisnya dikumpulkan lalu ditulis sekali;
         * PenyisipanBerbatasParameter memotong sendiri agar batas 2.100
         * parameter SQL Server tidak ditabrak.
         */
        $baris = [];

        foreach ($dates as $item) {
            $baris[] = [
                'Kode_Perusahaan' => '001',
                'Id_Detail' => $idDetail,
                'No_Transaksi' => $noTransaksi,
                'Tanggal_Shift' => $item['tanggal'],
                'Id_Shift_Sebelumnya' => $item['id_shift_sebelumnya'] ?? null,
                'Id_Shift_Diminta' => $item['id_shift'],
                'Jenis_Pengajuan' => $item['jenis_pengajuan'], // BACKDATE/FUTURE/HARI_H
                'Alasan' => $item['alasan'] ?? null,
                'Needs_Approval' => $item['needs_approval'] ?? false ? 'Y' : 'T',
                'Flag_Applied' => $item['flag_applied'] ?? null,
                'Flag_Approval' => $item['flag_approval'] ?? null,
                'Note_Tolak' => null,
                'Decision_Approval_Notes' => $item['decision_notes'] ?? null,
            ];
        }

        PenyisipanBerbatasParameter::sisipkan('N_HRIS_Transaksi_Shift_Det', $baris);
    }

    protected function updateDetailApprovalStatusSmart(string $noTransaksi): void
    {
        $details = DB::table('N_HRIS_Transaksi_Shift_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->whereNull('Status')
            ->get();

        if ($details->isEmpty()) {
            return;
        }

        /*
         * Sebelumnya setiap detail menanyakan Det-nya sendiri lalu memperbarui
         * dirinya sendiri: 1 + 2N kueri untuk N detail, seluruhnya di dalam
         * transaksi approval. Dengan RCSI mati di Web_HRIS itu menahan kunci
         * selama seluruh perulangan, bukan selama satu barisnya.
         *
         * Sekarang seluruh Det diambil sekali, keputusannya dihitung di memori
         * dengan aturan yang sama persis, lalu detail dikelompokkan menurut flag
         * tujuannya -- sehingga paling banyak ada tiga UPDATE (null, Y, T),
         * berapa pun jumlah detailnya.
         */
        $detsPerDetail = $this->detsButuhApprovalPerDetail($details->pluck('Id')->all());

        /** @var array<string, list<int|string>> $perFlag kunci: 'null' | 'Y' | 'T' */
        $perFlag = [];

        foreach ($details as $detail) {
            [$perluDiubah, $flag] = self::flagTujuanDetail($detsPerDetail[$detail->Id] ?? collect());

            if ($perluDiubah) {
                $perFlag[$flag ?? 'null'][] = $detail->Id;
            }
        }

        foreach ($perFlag as $flag => $ids) {
            foreach (array_chunk($ids, PenyisipanBerbatasParameter::BATAS_AMAN) as $potongan) {
                DB::table('N_HRIS_Transaksi_Shift_Detail')
                    ->whereIn('Id', $potongan)
                    ->whereNull('Status')
                    ->update(['Flag_Approval' => $flag === 'null' ? null : $flag]);
            }
        }
    }

    /**
     * Flag tujuan sebuah Detail, dihitung dari Det-nya yang menuntut approval.
     *
     * Murni: tidak menyentuh basis data, sehingga aturannya bisa diuji langsung
     * tanpa koneksi. Aturannya sama persis dengan versi per-baris sebelumnya --
     * yang berubah hanya cara mengambil datanya dan cara menuliskan hasilnya.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $dets
     * @return array{0: bool, 1: string|null} [perlu diubah, flag tujuan]
     */
    public static function flagTujuanDetail(\Illuminate\Support\Collection $dets): array
    {
        if ($dets->isEmpty()) {
            // Tak ada Det yang menuntut approval -> detail dibiarkan apa adanya.
            return [false, null];
        }

        if ($dets->contains(fn ($det) => $det->Flag_Approval === null)) {
            // Masih menunggu approver berikutnya.
            return [true, null];
        }

        if ($dets->contains(fn ($det) => $det->Flag_Approval === 'Y')) {
            // Satu approve saja sudah cukup.
            return [true, 'Y'];
        }

        if ($dets->every(fn ($det) => $det->Flag_Approval === 'T')) {
            // Reject hanya kalau semua sudah vote dan semuanya T.
            return [true, 'T'];
        }

        return [false, null];
    }

    /**
     * Det yang menuntut approval, dikelompokkan per Id_Detail.
     *
     * whereIn dipotong pada BATAS_AMAN yang sama dengan penyisipan berbatas,
     * supaya batas 2.100 parameter SQL Server tidak ditabrak saat sebuah
     * transaksi memuat ribuan detail.
     *
     * @param  list<int|string>  $idDetail
     * @return \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, object>>
     */
    private function detsButuhApprovalPerDetail(array $idDetail): \Illuminate\Support\Collection
    {
        $semua = collect();

        foreach (array_chunk($idDetail, PenyisipanBerbatasParameter::BATAS_AMAN) as $potongan) {
            $semua = $semua->concat(
                DB::table('N_HRIS_Transaksi_Shift_Det')
                    ->whereIn('Id_Detail', $potongan)
                    ->whereNull('Status')
                    ->where('Needs_Approval', 'Y')
                    ->get()
            );
        }

        return $semua->groupBy('Id_Detail');
    }

    // =========================================================================
    // LEGACY METHODS (kept for backward compatibility during migration)
    // =========================================================================

    /**
     * Create transaction records (LEGACY - 2-tier)
     *
     * @deprecated Use createHeader + createKaryawanDetail + createShiftDet instead
     */
    protected function createTransaction(string $kodeKaryawan, string $targetTable, ?string $alasan): string
    {
        $noTransaksi = $this->generateNoTransaksi();
        $userId = Auth::id();

        // Insert header
        DB::table('N_HRIS_Transaksi_Shift')->insert([
            'No_Transaksi' => $noTransaksi,
            'Kode_Perusahaan' => '001',
            'Alasan' => $alasan,
            'Jenis_Transaksi' => 'SELF_SHIFT', // Default for legacy
            'Status' => null,
            'Created_At' => now(),
            'Created_By' => $userId,
        ]);

        // Create Detail (Tier 2) for this single karyawan
        $idDetail = $this->createKaryawanDetail($noTransaksi, $kodeKaryawan, $targetTable, $alasan);

        // Store for later use in createTransactionDetail
        $this->lastCreatedDetailId = $idDetail;

        return $noTransaksi;
    }

    /** @var int|null Temporary storage for legacy flow */
    protected ?int $lastCreatedDetailId = null;

    /**
     * @deprecated Use createShiftDet instead
     */
    protected function createTransactionDetail(
        string $noTransaksi,
        array $dates,
        ?string $flagApproval = null,
        ?string $flagApplied = null,
    ): void {
        // dd($noTransaksi, $dates, $flagApproval, $flagApplied);
        // Convert to new format and use createShiftDet
        $formattedDates = array_map(function ($item) use ($flagApproval, $flagApplied) {
            return [
                'tanggal' => $item['tanggal'],
                'id_shift' => $item['id_shift'],
                'id_shift_sebelumnya' => $item['id_shift_sebelumnya'] ?? null,
                'jenis_pengajuan' => $item['jenis_pengajuan'],
                'needs_approval' => $item['needs_approval'] ?? false,
                'flag_approval' => $flagApproval,
                'flag_applied' => $flagApplied,
                'alasan' => $item['alasan'] ?? null,
            ];
        }, $dates);

        // Use stored Detail ID or fetch it
        $idDetail = $this->lastCreatedDetailId;
        if (! $idDetail) {
            // Fallback: get the latest detail for this transaction
            $detail = DB::table('N_HRIS_Transaksi_Shift_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNull('Status')
                ->orderBy('Id', 'desc')
                ->first();
            $idDetail = $detail?->Id ?? 0;
        }

        if ($idDetail) {
            $this->createShiftDet($idDetail, $noTransaksi, $formattedDates);
        }
    }

    /**
     * Apply shift directly without approval
     */
    // protected function applyShiftDirect(string $kodeKaryawan, array $item, string $targetTable, string $noTransaksiReference = null): void
    // {
    //     $userId = Auth::id() ;

    //     // ============================================
    //     // 1. CEK STATUS HARI
    //     // ============================================
    //     $status = DB::selectOne(
    //         "SELECT N_HRIS_FN_GetStatusHari(?, ?, ?) as status_hari",
    //         ['001', $kodeKaryawan, $item['tanggal']]
    //     );

    //     $statusValue = $status->status_hari ?? 'WORK';

    //     if ($targetTable === 'SEMENTARA') {

    //             DB::table('HRIS_Shift_Sementara')->insert([
    //                 'Kode_Perusahaan' => '001',
    //                 'Kode_Karyawan' => $kodeKaryawan,
    //                 'ID_Shift' => $item['id_shift'],
    //                 'Tanggal' => $item['tanggal'],
    //                 'UserID' => $kodeKaryawan,
    //                 'No_Transaksi_Reference' => $noTransaksiReference,
    //                 'created_at' => now(),
    //             ]);

    //     } else {
    //         // PERMANEN - insert to HRIS_Shift_Per_Karyawan
    //         DB::table('HRIS_Shift_Per_Karyawan')->insert([
    //             'Kode_Perusahaan' => '001',
    //             'Kode_Karyawan' => $kodeKaryawan,
    //             'ID_Shift' => $item['id_shift'],
    //             'Periode' => $item['tanggal'],
    //             'UserID' => $kodeKaryawan,
    //             'No_Transaksi_Reference' => $noTransaksiReference,
    //             'created_at' => now(),
    //         ]);
    //     }
    // }

    /**
     * Ambil status hari (WORK/OFF/...) untuk 1 karyawan-tanggal via scalar UDF
     * N_HRIS_FN_GetStatusHari. Dipakai untuk PRA-HITUNG di luar transaksi lalu
     * dikirim ke applyShiftDirect($preComputedStatusHari) — agar UDF tidak jalan
     * di dalam lock.
     */
    public function getStatusHari(string $kodeKaryawan, string $tanggal, string $kodePerusahaan = '001'): ?string
    {
        $row = DB::selectOne('SELECT dbo.N_HRIS_FN_GetStatusHari(?, ?, ?) as status_hari', [
            $kodePerusahaan,
            $kodeKaryawan,
            $tanggal,
        ]);

        return $row->status_hari ?? null;
    }

    /**
     * Tandai snapshot absen "kotor" untuk satu karyawan pada satu RENTANG tanggal.
     *
     * ── Kenapa rentang, dan kenapa ini penting ───────────────────────────────
     *
     * Penandaan ini menjalankan EXEC N_HRIS_SP_MARK_ABSEN_DIRTY. Dulu ia
     * dipanggil dari dalam applyShiftDirect() — yaitu SEKALI PER TANGGAL — dan
     * seluruh perulangannya berada di dalam satu transaksi tulis.
     *
     * Terbukti di production 9 September 2026 pukul 20:26-20:28. Rantai lengkap,
     * terkunci lewat Context_Info yang sama antara N_HRIS_DB_Health_Snapshot dan
     * N_HRIS_Request_Diagnostics:
     *
     *   sesi 62  = leader-shift/submit, 3.289 kueri, 114 detik, program apache2,
     *              statement terakhirnya N_HRIS_SP_MARK_ABSEN_DIRTY
     *     -> sesi 71 menunggu 53.999 ms pada LCK_M_S
     *        -> tujuh sesi lain menunggu di belakang 71 (satu 49.957 ms dengan
     *           CPU 12 ms — murni menunggu)
     *
     * Semua timeout dan deadlock di log jam itu — getApprovedTransactionsForCalendar,
     * countPendingApprovalsV2, getPersonalUpcomingSchedule,
     * getFullyApprovedTransactionMap — adalah korban rantai ini, bukan penyebab.
     *
     * MarkAttendanceDirtyJob sudah dibuat persis untuk mencegah hal ini, dengan
     * docblock yang menyebut LCK_M_S secara harfiah — tetapi ia hanya dipasang di
     * jalur APPROVAL (TransactionService), sementara jalur penerapan shift ini
     * memanggil resolver-nya langsung. Aturan yang sama hanya terpasang di satu
     * dari dua pintu yang setara.
     *
     * Perbaikan di sini menutup pintu kedua semurah mungkin tanpa mengubah
     * semantik: resolver memang sudah menerima (tglAwal, tglAkhir), jadi seluruh
     * tanggal satu karyawan cukup satu panggilan. Memindahkannya ke luar
     * transaksi sepenuhnya adalah langkah berikutnya, bukan bagian ini.
     */
    private function tandaiAbsenKotor(
        string $kodeKaryawan,
        string $targetTable,
        string $tglAwal,
        string $tglAkhir,
        string $kodePerusahaan = '001',
    ): void {
        if ($targetTable === 'PERMANEN') {
            $this->attendanceDirtyResolver->markPermanentEffectByKodeKaryawan(
                $kodeKaryawan,
                $tglAwal,
                'SHIFT_PERMANEN_CHANGED',
                'SHIFT_REQUEST_APPLY_DIRECT',
                1,
                $kodePerusahaan,
            );

            return;
        }

        $this->attendanceDirtyResolver->markByKodeKaryawan(
            $kodeKaryawan,
            $tglAwal,
            $tglAkhir,
            'SHIFT_SEMENTARA_CHANGED',
            'SHIFT_REQUEST_APPLY_DIRECT',
            1,
            $kodePerusahaan,
        );
    }

    /**
     * @param  bool  $tundaDirtyMark  Lewati penandaan absen-kotor di sini; pemanggil
     *                                yang menerapkan BANYAK tanggal untuk satu karyawan menandainya sekali
     *                                sebagai RENTANG. Baku false supaya semua pemanggil lama tak berubah.
     */
    protected function applyShiftDirect(
        string $kodeKaryawan,
        array $item,
        string $targetTable,
        ?string $noTransaksiReference = null,
        bool $overwriteSementara = true,
        ?string $preComputedStatusHari = null,
        bool $tundaDirtyMark = false,
    ): void {
        $this->pastikanTargetShiftValid($targetTable);

        $kodePerusahaan = '001';
        $userId = Auth::id();
        $tanggal = $item['tanggal'];

        DB::transaction(function () use (
            $kodePerusahaan,
            $kodeKaryawan,
            $item,
            $targetTable,
            $noTransaksiReference,
            $userId,
            $tanggal,
            $overwriteSementara,
            $preComputedStatusHari,
        ) {
            /*
            =====================================================
            1. HANDLE STATUS HARI (AUTO WORK OVERRIDE)
            =====================================================
            */

            // Status hari bisa dipra-hitung DI LUAR transaksi (N_HRIS_FN_GetStatusHari
            // scalar UDF — jangan di dalam lock). Fallback ke FN bila tak disediakan.
            $statusHari = $preComputedStatusHari !== null
                ? $preComputedStatusHari
                : (DB::selectOne('SELECT dbo.N_HRIS_FN_GetStatusHari(?, ?, ?) as status_hari', [
                    $kodePerusahaan,
                    $kodeKaryawan,
                    $tanggal,
                ])->status_hari ?? null);

            if ($statusHari !== null && str_starts_with($statusHari, 'OFF')) {
                // CHANGE 2: gunakan helper insertWorkOverlay (DRY)
                $this->insertWorkOverlay($kodePerusahaan, $kodeKaryawan, $tanggal, $userId, $noTransaksiReference);
            }

            /*
            =====================================================
            2. JIKA SHIFT SEMENTARA
            =====================================================
            */

            if ($targetTable === 'SEMENTARA') {
                // Nonaktifkan sementara lama di tanggal yg sama
                DB::table('HRIS_Shift_Sementara')
                    ->where('Kode_Perusahaan', $kodePerusahaan)
                    ->where('Kode_Karyawan', $kodeKaryawan)
                    ->where('Tanggal', $tanggal)
                    ->where('Flag_Aktif', 'Y')
                    ->update([
                        'Flag_Aktif' => 'T',
                        'Nonaktif_By' => $userId,
                        'Nonaktif_At' => now(),
                        'Nonaktif_Reason' => 'Replaced by new temporary shift',
                    ]);

                DB::table('HRIS_Shift_Sementara')->insert([
                    'Kode_Perusahaan' => $kodePerusahaan,
                    'Kode_Karyawan' => $kodeKaryawan,
                    'ID_Shift' => $item['id_shift'],
                    'Tanggal' => $tanggal,
                    'UserID' => $kodeKaryawan,
                    'Flag_Aktif' => 'Y',
                    'No_Transaksi_Reference' => $noTransaksiReference,
                    'created_at' => now(),
                ]);
            } /*
            =====================================================
            3. JIKA SHIFT PERMANEN
            =====================================================
            */ else {
                // ⚠️ NONAKTIFKAN SHIFT SEMENTARA >= tanggal ini
                if ($overwriteSementara) {
                    DB::table('HRIS_Shift_Sementara')
                        ->where('Kode_Perusahaan', $kodePerusahaan)
                        ->where('Kode_Karyawan', $kodeKaryawan)
                        ->where('Tanggal', '>=', $tanggal)
                        ->where('Flag_Aktif', 'Y')
                        ->update([
                            'Flag_Aktif' => 'T',
                            'Nonaktif_By' => $userId,
                            'Nonaktif_At' => now(),
                            'Nonaktif_Reason' => 'Replaced by permanent shift',
                        ]);
                }

                $this->akhiriCustomOffYangTumpangTindih(
                    $kodePerusahaan,
                    $kodeKaryawan,
                    $tanggal,
                    $userId === null ? null : (string) $userId,
                );

                // Insert permanen baru
                DB::table('HRIS_Shift_Per_Karyawan')->insert([
                    'Kode_Perusahaan' => $kodePerusahaan,
                    'Kode_Karyawan' => $kodeKaryawan,
                    'ID_Shift' => $item['id_shift'],
                    'Periode' => $tanggal,
                    'UserID' => $kodeKaryawan,
                    'Flag_Aktif' => 'Y',
                    'No_Transaksi_Reference' => $noTransaksiReference,
                    'created_at' => now(),
                ]);
            }
        });

        if (! $tundaDirtyMark) {
            $this->tandaiAbsenKotor($kodeKaryawan, $targetTable, $tanggal, $tanggal, $kodePerusahaan);
        }
    }

    public function checkPermanentConflict($kodeKaryawan, $tanggal)
    {
        return DB::table('HRIS_Shift_Sementara')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Tanggal', '>=', $tanggal)
            ->where('Flag_Aktif', 'Y')
            ->count();
    }

    /**
     * Konflik shift permanen untuk beberapa karyawan x beberapa tanggal.
     *
     * checkPermanentConflict() menjawab SATU pasangan dengan satu COUNT.
     * Dipakai dari perulangan anggota x tanggal — dan layar "cek konflik"
     * memang memakainya begitu — biayanya berlipat: 40 anggota x 30 tanggal =
     * 1.200 COUNT ke tabel yang sama.
     *
     * Predikatnya `Tanggal >= $tanggal`, jadi satu pengambilan sejak tanggal
     * PALING AWAL yang ditanyakan sudah memuat semua yang dibutuhkan tanggal
     * lainnya; sisanya hitungan di memori. Hasilnya identik karena Y-m-d
     * terurut secara leksikografis sama dengan urutan kronologisnya.
     *
     * @param  list<string>  $kodeKaryawan
     * @param  list<string>  $tanggal
     * @return array<string,array<string,int>> [kode][tanggal asli] => jumlah
     */
    public function checkPermanentConflicts(array $kodeKaryawan, array $tanggal): array
    {
        $kode = array_values(array_unique(array_filter(array_map('strval', $kodeKaryawan))));

        /* Bentuk tanggal APA ADANYA dipertahankan sebagai kunci: pemanggil
           mencarinya kembali dengan string yang sama seperti yang ia kirim. */
        $normal = [];
        foreach ($tanggal as $t) {
            if ($t === null || $t === '') {
                continue;
            }
            $normal[(string) $t] = Carbon::parse($t)->format('Y-m-d');
        }

        if ($kode === [] || $normal === []) {
            return [];
        }

        $hasil = [];
        foreach ($kode as $k) {
            foreach ($normal as $asli => $_) {
                $hasil[$k][$asli] = 0;
            }
        }

        $paling_awal = min(array_values($normal));

        /* Batas 2.100 parameter SQL Server: daftar karyawan dipotong, bukan
           diandalkan tetap kecil. */
        $perKode = [];
        foreach (array_chunk($kode, 1000) as $potongan) {
            foreach (DB::table('HRIS_Shift_Sementara')
                ->whereIn('Kode_Karyawan', $potongan)
                ->where('Tanggal', '>=', $paling_awal)
                ->where('Flag_Aktif', 'Y')
                ->get(['Kode_Karyawan', 'Tanggal']) as $baris) {
                $k = trim((string) $baris->Kode_Karyawan);
                if ($k === '' || $baris->Tanggal === null) {
                    continue;
                }
                $perKode[$k][] = Carbon::parse($baris->Tanggal)->format('Y-m-d');
            }
        }

        foreach ($hasil as $k => $pasangan) {
            $punya = $perKode[$k] ?? [];
            if ($punya === []) {
                continue;
            }
            foreach ($pasangan as $asli => $_) {
                $batas = $normal[$asli];
                $hasil[$k][$asli] = count(array_filter($punya, fn ($d) => $d >= $batas));
            }
        }

        return $hasil;
    }

    /**
     * Apply shift after approval complete
     */
    /**
     * Apply shift after approval complete
     * Uses 3-tier structure: Header → Detail (per Karyawan) → Det (per Tanggal)
     * Supports partial approval - applies only approved Dets regardless of Detail status
     */
    public function applyShiftAfterApproval(string $noTransaksi): void
    {
        $header = DB::table('N_HRIS_Transaksi_Shift')->where('No_Transaksi', $noTransaksi)->first();

        if (! $header) {
            return;
        }

        // Get all approved Dets directly (supports partial approval)
        // Join to Detail for Kode_Karyawan and Target_Table
        $approvedDets = DB::table('N_HRIS_Transaksi_Shift_Det as det')
            ->join('N_HRIS_Transaksi_Shift_Detail as d', 'det.Id_Detail', '=', 'd.Id')
            ->where('det.No_Transaksi', $noTransaksi)
            ->where('det.Flag_Approval', 'Y')
            ->whereNull('d.Status')
            ->whereNull('det.Status')
            ->where(function ($q) {
                $q->whereNull('det.Flag_Applied')->orWhere('det.Flag_Applied', '!=', 'Y');
            })
            ->select('det.*', 'd.Kode_Karyawan', 'd.Target_Table')
            ->get();

        $header = DB::table('N_HRIS_Transaksi_Shift')->where('No_Transaksi', $noTransaksi)->first();
        $overwriteSementara = $header && $header->Flag_Overwrite_Sementara === 'Y';

        foreach ($approvedDets as $det) {
            $item = [
                'tanggal' => $det->Tanggal_Shift,
                'id_shift' => $det->Id_Shift_Diminta,
            ];

            $this->applyShiftDirect($det->Kode_Karyawan, $item, $det->Target_Table, $noTransaksi, $overwriteSementara);

            // Mark Det as applied
            DB::table('N_HRIS_Transaksi_Shift_Det')
                ->where('Id', $det->Id)
                ->update(['Flag_Applied' => 'Y']);
        }

        // Update header - Mark as completed
        DB::table('N_HRIS_Transaksi_Shift')
            ->where('No_Transaksi', $noTransaksi)
            ->update([
                'Flag_Selesai' => 'Y',
                'Updated_By' => Auth::id(),
                'Updated_At' => now(),
            ]);
    }

    /**
     * Apply shifts and mark tukar hari transaction as completed after approval
     * Inserts override ke N_HRIS_Status_Hari_Karyawan (master tunggal status hari)
     * Shift tetap di-apply ke HRIS_Shift_Sementara untuk tanggal bekerja
     */
    public function applyTukarHariAfterApproval(string $noTransaksi): void
    {
        $header = DB::table('N_HRIS_Transaksi_Tukar_Hari')->where('No_Transaksi', $noTransaksi)->first();

        if (! $header) {
            return;
        }

        $details = DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Flag_Approval', 'Y')
            ->get();

        foreach ($details as $detail) {
            // Insert OFF date → N_HRIS_Status_Hari_Karyawan
            if (! empty($detail->Tanggal_Off)) {
                DB::table('N_HRIS_Status_Hari_Karyawan')->insert([
                    'Kode_Perusahaan' => $header->Kode_Perusahaan,
                    'Kode_Karyawan' => $header->Kode_Karyawan,
                    'Status_Hari' => 'OFF',
                    'Tgl_Mulai' => $detail->Tanggal_Off,
                    'Tgl_Selesai' => $detail->Tanggal_Off,
                    'Flag_Aktif' => 'Y',
                    'No_Transaksi_Reference' => $noTransaksi,
                    'Created_At' => now(),
                    'Created_By' => Auth::id(),
                ]);

                $this->attendanceDirtyResolver->markByKodeKaryawan(
                    $header->Kode_Karyawan,
                    $detail->Tanggal_Off,
                    $detail->Tanggal_Off,
                    'TUKAR_HARI_APPROVED',
                    'SHIFT_TUKAR_HARI_APPLY',
                    1,
                    $header->Kode_Perusahaan ?? '001',
                );
            }

            // Insert WORK date → N_HRIS_Status_Hari_Karyawan + apply shift sementara
            if (! empty($detail->Tanggal_Bekerja)) {
                DB::table('N_HRIS_Status_Hari_Karyawan')->insert([
                    'Kode_Perusahaan' => $header->Kode_Perusahaan,
                    'Kode_Karyawan' => $header->Kode_Karyawan,
                    'Status_Hari' => 'WORK',
                    'Tgl_Mulai' => $detail->Tanggal_Bekerja,
                    'Tgl_Selesai' => $detail->Tanggal_Bekerja,
                    'Flag_Aktif' => 'Y',
                    'No_Transaksi_Reference' => $noTransaksi,
                    'Created_At' => now(),
                    'Created_By' => Auth::id(),
                ]);

                // Tetap apply shift ke HRIS_Shift_Sementara
                $this->applyShiftDirect(
                    $header->Kode_Karyawan,
                    [
                        'tanggal' => $detail->Tanggal_Bekerja,
                        'id_shift' => $detail->Id_Shift_Bekerja,
                    ],
                    'SEMENTARA',
                    $noTransaksi,
                );
            }

            DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')
                ->where('Id', $detail->Id)
                ->update(['Flag_Applied' => 'Y']);
        }

        DB::table('N_HRIS_Transaksi_Tukar_Hari')
            ->where('No_Transaksi', $noTransaksi)
            ->update([
                'Flag_Selesai' => 'Y',
                'Updated_At' => now(),
                'Updated_By' => Auth::id(),
            ]);
    }

    /**
     * Get inbox for approver
     */
    /**
     * Get inbox for approver
     */
    public function getApproverInbox(string $kodeApprover, array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;
        $filters = $params['filters'] ?? [];
        $offset = ($page - 1) * $limit;

        $currentApprovalNos = DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('Flow_State', 'CURRENT')
            ->where(function ($q) use ($kodeApprover) {
                $q->where('Current_Approver', $kodeApprover)->where(function ($flag) {
                    $flag->where('Flag_Group', '!=', 'Y')->orWhereNull('Flag_Group');
                });
            })
            ->pluck('No_Transaksi')
            ->unique()
            ->values()
            ->all();

        if (empty($currentApprovalNos)) {
            return [
                'data' => collect(),
                'page' => $page,
                'limit' => $limit,
                'total' => 0,
                'stats' => [
                    'total' => 0,
                    'pending' => 0,
                    'approved' => 0,
                ],
            ];
        }

        // === QUERY 1: SHIFT APPROVALS ===
        $queryShift = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Shift as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('N_HRIS_Transaksi_Shift_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'vg.Id_Level_Jabatan',
                '=',
                'kr.Id_Level_Jabatan',
            )
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->whereIn('ar.No_Transaksi', $currentApprovalNos)
            ->whereNull('ar.Status')
            ->whereNull('h.Status') // Exclude cancelled
            ->whereNull('ar.Flag_Approval'); // Pending only

        // === QUERY 2: TUKAR HARI APPROVALS ===
        $queryTukarHari = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Tukar_Hari as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->join('Karyawan as k', 'h.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'vg.Id_Level_Jabatan',
                '=',
                'kr.Id_Level_Jabatan',
            )
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->whereIn('ar.No_Transaksi', $currentApprovalNos)
            ->whereNull('ar.Status')
            ->whereNull('h.Status') // Not cancelled (Status NULL = aktif)
            ->whereNull('ar.Flag_Approval'); // Pending only

        // === QUERY 3: STATUS HARI APPROVALS ===
        $queryStatusHari = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Status_Hari as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join(
                'Karyawan as k',
                DB::raw('CAST(h.Created_By AS VARCHAR)'),
                '=',
                DB::raw('CAST(k.UserID_Web AS VARCHAR)'),
            )
            ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'vg.Id_Level_Jabatan',
                '=',
                'kr.Id_Level_Jabatan',
            )
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->whereIn('ar.No_Transaksi', $currentApprovalNos)
            ->whereNull('ar.Status')
            ->whereNull('h.Status')
            ->whereNull('ar.Flag_Approval');

        // === QUERY 4: KONVERSI LEMBUR APPROVALS ===
        $queryKonversiLembur = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Konversi_Lembur as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('N_HRIS_Transaksi_Konversi_Lembur_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->leftJoin('N_HRIS_Transaksi_Konversi_Lembur_Det as det', 'd.Id', '=', 'det.Id_Detail')
            ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'vg.Id_Level_Jabatan',
                '=',
                'kr.Id_Level_Jabatan',
            )
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->whereIn('ar.No_Transaksi', $currentApprovalNos)
            ->whereNull('ar.Status')
            ->whereNull('ar.Flag_Approval')
            ->whereNull('h.Status');

        // === APPLY FILTERS TO BOTH ===
        // Filter: Jabatan
        if (! empty($filters['jabatan'])) {
            $queryShift->where('vg.Id_Jabatan', $filters['jabatan']);
            $queryTukarHari->where('vg.Id_Jabatan', $filters['jabatan']);
            $queryStatusHari->where('vg.Id_Jabatan', $filters['jabatan']);
            $queryKonversiLembur->where('vg.Id_Jabatan', $filters['jabatan']);
        }

        // Filter: Search
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $queryShift->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('k.UserID_Absen', 'like', "%{$search}%")
                    ->orWhere('ar.No_Transaksi', 'like', "%{$search}%");
            });
            $queryTukarHari->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('k.UserID_Absen', 'like', "%{$search}%")
                    ->orWhere('ar.No_Transaksi', 'like', "%{$search}%");
            });
            $queryStatusHari->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")->orWhere('ar.No_Transaksi', 'like', "%{$search}%");
            });
            $queryKonversiLembur->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('k.UserID_Absen', 'like', "%{$search}%")
                    ->orWhere('ar.No_Transaksi', 'like', "%{$search}%");
            });
        }

        // Filter: Transaction Type
        if (! empty($filters['transaction_type'])) {
            $transactionType = strtoupper((string) $filters['transaction_type']);

            if ($transactionType === 'SHIFT') {
                $queryTukarHari->whereRaw('1 = 0');
                $queryStatusHari->whereRaw('1 = 0');
                $queryKonversiLembur->whereRaw('1 = 0');
            } elseif ($transactionType === 'TUKAR_HARI') {
                $queryShift->whereRaw('1 = 0');
                $queryStatusHari->whereRaw('1 = 0');
                $queryKonversiLembur->whereRaw('1 = 0');
            } elseif ($transactionType === 'STATUS_HARI') {
                $queryShift->whereRaw('1 = 0');
                $queryTukarHari->whereRaw('1 = 0');
                $queryKonversiLembur->whereRaw('1 = 0');
            } elseif ($transactionType === 'KONVERSI_LEMBUR') {
                $queryShift->whereRaw('1 = 0');
                $queryTukarHari->whereRaw('1 = 0');
                $queryStatusHari->whereRaw('1 = 0');
            }
        }

        // Filter: Date Range
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta');
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta');

            $queryShift->whereExists(function ($subquery) use ($startDate, $endDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Det as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '>=', $startDate)
                    ->whereDate('detail.Tanggal_Shift', '<=', $endDate);
            });

            $queryTukarHari->where(function ($q) use ($startDate, $endDate) {
                $q->whereDate('d.Tanggal_Bekerja', '>=', $startDate)
                    ->whereDate('d.Tanggal_Bekerja', '<=', $endDate)
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->whereDate('d.Tanggal_Off', '>=', $startDate)->whereDate('d.Tanggal_Off', '<=', $endDate);
                    });
            });

            $queryStatusHari->whereExists(function ($subquery) use ($startDate, $endDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Status_Hari_Det as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal', '>=', $startDate)
                    ->whereDate('detail.Tanggal', '<=', $endDate);
            });
            $queryKonversiLembur->where(function ($q) use ($startDate, $endDate) {
                $q->whereDate('det.Tanggal_Lembur', '>=', $startDate)
                    ->whereDate('det.Tanggal_Lembur', '<=', $endDate)
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->whereNotNull('det.Target_Off')
                            ->whereDate('det.Target_Off', '>=', $startDate)
                            ->whereDate('det.Target_Off', '<=', $endDate);
                    });
            });
        } elseif (! empty($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta');
            $queryShift->whereExists(function ($subquery) use ($startDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Det as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '>=', $startDate);
            });
            $queryTukarHari->where(function ($q) use ($startDate) {
                $q->whereDate('d.Tanggal_Bekerja', '>=', $startDate)->orWhereDate('d.Tanggal_Off', '>=', $startDate);
            });
            $queryStatusHari->whereExists(function ($subquery) use ($startDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Status_Hari_Det as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal', '>=', $startDate);
            });
            $queryKonversiLembur->where(function ($q) use ($startDate) {
                $q->whereDate('det.Tanggal_Lembur', '>=', $startDate)->orWhere(function ($q2) use ($startDate) {
                    $q2->whereNotNull('det.Target_Off')->whereDate('det.Target_Off', '>=', $startDate);
                });
            });
        } elseif (! empty($filters['end_date'])) {
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta');
            $queryShift->whereExists(function ($subquery) use ($endDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Det as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '<=', $endDate);
            });
            $queryTukarHari->where(function ($q) use ($endDate) {
                $q->whereDate('d.Tanggal_Bekerja', '<=', $endDate)->orWhereDate('d.Tanggal_Off', '<=', $endDate);
            });
            $queryStatusHari->whereExists(function ($subquery) use ($endDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Status_Hari_Det as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal', '<=', $endDate);
            });
            $queryKonversiLembur->where(function ($q) use ($endDate) {
                $q->whereDate('det.Tanggal_Lembur', '<=', $endDate)->orWhere(function ($q2) use ($endDate) {
                    $q2->whereNotNull('det.Target_Off')->whereDate('det.Target_Off', '<=', $endDate);
                });
            });
        }

        // === GET DATA ===
        $itemsShift = $queryShift
            ->select(
                'h.*',
                'd.Target_Table',
                'h.Jenis_Transaksi',
                'ar.Id as approval_id',
                'ar.Id_Jenis_Approval',
                'ar.No_Transaksi',
                'ar.Request_Order_Flow',
                'ar.Created_At as Request_Date',
                'k.Nama as Nama_Karyawan',
                'kr.Nama as Nama_Pengaju',
                'd.Kode_Karyawan',
                'k.UserID_Absen as NIK',
                'kr.UserID_Absen as NIK_Pengaju',
                'vg.nama_level',
                'vg.nama_jabatan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                DB::raw("'SHIFT' as transaction_type"),
            )
            ->orderBy('ar.created_at', 'desc')
            ->get();

        $itemsTukarHari = $queryTukarHari
            ->select(
                'h.*',
                DB::raw('NULL as Target_Table'),
                'h.Jenis_Transaksi',
                'ar.Id as approval_id',
                'ar.Id_Jenis_Approval',
                'ar.No_Transaksi',
                'ar.Request_Order_Flow',
                'ar.Created_At as Request_Date',
                'k.Nama as Nama_Karyawan',
                'kr.Nama as Nama_Pengaju',
                'd.Kode_Karyawan',
                'k.UserID_Absen as NIK',
                'kr.UserID_Absen as NIK_Pengaju',
                'vg.nama_level',
                'vg.nama_jabatan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                DB::raw("'TUKAR_HARI' as transaction_type"),
            )
            ->orderBy('ar.created_at', 'desc')
            ->get();

        $itemsStatusHari = $queryStatusHari
            ->select(
                'h.*',
                DB::raw(
                    '(select top 1 d.Target_Table from N_HRIS_Transaksi_Status_Hari_Detail d where d.No_Transaksi = h.No_Transaksi and d.Status is null) as Target_Table',
                ),
                'h.Jenis_Transaksi',
                'ar.Id as approval_id',
                'ar.Id_Jenis_Approval',
                'ar.No_Transaksi',
                'ar.Request_Order_Flow',
                'ar.Created_At as Request_Date',
                'k.Nama as Nama_Karyawan',
                'kr.Nama as Nama_Pengaju',
                DB::raw('NULL as Kode_Karyawan'),
                'k.UserID_Absen as NIK',
                'kr.UserID_Absen as NIK_Pengaju',
                'vg.nama_level',
                'vg.nama_jabatan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                DB::raw("'STATUS_HARI' as transaction_type"),
            )
            ->orderBy('ar.created_at', 'desc')
            ->get();

        $itemsKonversiLembur = $queryKonversiLembur
            ->select(
                'h.*',
                DB::raw('NULL as Target_Table'),
                'h.Jenis_Transaksi',
                'ar.Id as approval_id',
                'ar.Id_Jenis_Approval',
                'ar.No_Transaksi',
                'ar.Request_Order_Flow',
                'ar.Created_At as Request_Date',
                'k.Nama as Nama_Karyawan',
                'kr.Nama as Nama_Pengaju',
                'd.Kode_Karyawan',
                'k.UserID_Absen as NIK',
                'kr.UserID_Absen as NIK_Pengaju',
                'vg.nama_level',
                'vg.nama_jabatan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                'd.Note as Alasan',
                'det.Tanggal_Lembur',
                'det.Target_Off',
                'det.Jenis_Konversi',
                'det.Durasi_Jam',
                DB::raw("'KONVERSI_LEMBUR' as transaction_type"),
            )
            ->orderBy('ar.created_at', 'desc')
            ->get();

        // Merge and sort
        $allItems = $itemsShift
            ->merge($itemsTukarHari)
            ->merge($itemsStatusHari)
            ->merge($itemsKonversiLembur)
            ->unique('No_Transaksi')
            ->sortByDesc('Request_Date')
            ->values();

        $countItems = $allItems->count();

        // Manual pagination
        $items = $allItems->slice($offset, $limit)->values();

        // Enrich with details summary
        foreach ($items as $item) {
            $this->enrichTransactionWithDetails($item);
        }

        // Calculate stats
        $statsQueryShift = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Shift as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->whereIn('ar.No_Transaksi', $currentApprovalNos)
            ->whereNull('ar.Status')
            ->whereNull('h.Status');

        $statsQueryTukarHari = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Tukar_Hari as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->whereIn('ar.No_Transaksi', $currentApprovalNos)
            ->whereNull('ar.Status');

        $statsQueryStatusHari = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Status_Hari as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->whereIn('ar.No_Transaksi', $currentApprovalNos)
            ->whereNull('ar.Status')
            ->whereNull('h.Status');

        $statsQueryKonversiLembur = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Konversi_Lembur as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->whereIn('ar.No_Transaksi', $currentApprovalNos)
            ->whereNull('ar.Status')
            ->whereNull('h.Status');

        // Tiga angka ini dulu diambil lewat DUA BELAS COUNT() terpisah: empat
        // tabel x tiga status, padahal ketiganya bisa dihitung sekali jalan atas
        // baris yang sama. Terekam di production 9 September 2026:
        // shift-approval/inbox rata-rata 36 kueri dengan 5.552 ms waktu DB —
        // 100% waktu permintaannya — dan kueri terlambatnya hanya 18% dari itu,
        // jadi bebannya memang tersebar di banyak kueri kecil, bukan satu berat.
        //
        // Agregasi bersyarat memulangkan angka yang sama persis: total = semua
        // baris, pending = Flag_Approval NULL, approved = NOT NULL. Empat kueri.
        $hitung = static function ($query): object {
            $baris = (clone $query)->select(DB::raw(
                'COUNT(*) AS total,
                 SUM(CASE WHEN ar.Flag_Approval IS NULL THEN 1 ELSE 0 END) AS pending,
                 SUM(CASE WHEN ar.Flag_Approval IS NOT NULL THEN 1 ELSE 0 END) AS approved',
            ))->first();

            return (object) [
                'total' => (int) ($baris->total ?? 0),
                'pending' => (int) ($baris->pending ?? 0),
                'approved' => (int) ($baris->approved ?? 0),
            ];
        };

        $rekap = array_map($hitung, [
            $statsQueryShift,
            $statsQueryTukarHari,
            $statsQueryStatusHari,
            $statsQueryKonversiLembur,
        ]);

        $stats = [
            'total' => array_sum(array_column($rekap, 'total')),
            'pending' => array_sum(array_column($rekap, 'pending')),
            'approved' => array_sum(array_column($rekap, 'approved')),
        ];

        return [
            'data' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $countItems,
            'stats' => $stats,
        ];
    }

    /**
     * Get history for approver (Processed requests)
     */
    public function getApproverHistory(string $kodeApprover, array $params = []): array
    {
        $filters = $params['filters'] ?? [];

        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;
        $offset = ($page - 1) * $limit;

        // === QUERY 1: SHIFT HISTORY ===
        $queryShift = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Shift as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('N_HRIS_Transaksi_Shift_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'vg.Id_Level_Jabatan',
                '=',
                'kr.Id_Level_Jabatan',
            )
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->where('ar.Approved_By_Kode', $kodeApprover)
            ->whereNull('ar.Status')
            ->whereNotNull('ar.Flag_Approval'); // ONLY Processed

        // === QUERY 2: TUKAR HARI HISTORY ===
        $queryTukarHari = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Tukar_Hari as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->join('Karyawan as k', 'h.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'vg.Id_Level_Jabatan',
                '=',
                'kr.Id_Level_Jabatan',
            )
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->where('ar.Approved_By_Kode', $kodeApprover)
            ->whereNull('ar.Status')
            ->whereNotNull('ar.Flag_Approval'); // ONLY Processed

        // === QUERY 3: STATUS HARI HISTORY ===
        $queryStatusHari = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Status_Hari as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join(
                'Karyawan as k',
                DB::raw('CAST(h.Created_By AS VARCHAR)'),
                '=',
                DB::raw('CAST(k.UserID_Web AS VARCHAR)'),
            )
            ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'vg.Id_Level_Jabatan',
                '=',
                'kr.Id_Level_Jabatan',
            )
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->where('ar.Approved_By_Kode', $kodeApprover)
            ->whereNull('ar.Status')
            ->whereNotNull('ar.Flag_Approval');

        // === QUERY 4: KONVERSI LEMBUR HISTORY ===
        $queryKonversiLembur = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Konversi_Lembur as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('N_HRIS_Transaksi_Konversi_Lembur_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->leftJoin('N_HRIS_Transaksi_Konversi_Lembur_Det as det', 'd.Id', '=', 'det.Id_Detail')
            ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'vg.Id_Level_Jabatan',
                '=',
                'kr.Id_Level_Jabatan',
            )
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->where('ar.Approved_By_Kode', $kodeApprover)
            ->whereNull('ar.Status')
            ->whereNotNull('ar.Flag_Approval');

        // === APPLY FILTERS ===
        // Filter: Status (Approved/Rejected)
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'approved') {
                $queryShift->where('ar.Flag_Approval', 'Y');
                $queryTukarHari->where('ar.Flag_Approval', 'Y');
                $queryStatusHari->where('ar.Flag_Approval', 'Y');
                $queryKonversiLembur->where('ar.Flag_Approval', 'Y');
            } elseif ($filters['status'] === 'rejected') {
                $queryShift->where('ar.Flag_Approval', 'T');
                $queryTukarHari->where('ar.Flag_Approval', 'T');
                $queryStatusHari->where('ar.Flag_Approval', 'T');
                $queryKonversiLembur->where('ar.Flag_Approval', 'T');
            }
        }

        // Filter: Jabatan
        if (! empty($filters['jabatan'])) {
            $queryShift->where('vg.Id_Jabatan', $filters['jabatan']);
            $queryTukarHari->where('vg.Id_Jabatan', $filters['jabatan']);
            $queryStatusHari->where('vg.Id_Jabatan', $filters['jabatan']);
            $queryKonversiLembur->where('vg.Id_Jabatan', $filters['jabatan']);
        }

        // Filter: Search
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $queryShift->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('k.UserID_Absen', 'like', "%{$search}%")
                    ->orWhere('ar.No_Transaksi', 'like', "%{$search}%");
            });
            $queryTukarHari->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('k.UserID_Absen', 'like', "%{$search}%")
                    ->orWhere('ar.No_Transaksi', 'like', "%{$search}%");
            });
            $queryStatusHari->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")->orWhere('ar.No_Transaksi', 'like', "%{$search}%");
            });
            $queryKonversiLembur->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('k.UserID_Absen', 'like', "%{$search}%")
                    ->orWhere('ar.No_Transaksi', 'like', "%{$search}%");
            });
        }

        // Filter: Transaction Type
        if (! empty($filters['transaction_type'])) {
            $transactionType = strtoupper((string) $filters['transaction_type']);

            if ($transactionType === 'SHIFT') {
                $queryTukarHari->whereRaw('1 = 0');
                $queryStatusHari->whereRaw('1 = 0');
                $queryKonversiLembur->whereRaw('1 = 0');
            } elseif ($transactionType === 'TUKAR_HARI') {
                $queryShift->whereRaw('1 = 0');
                $queryStatusHari->whereRaw('1 = 0');
                $queryKonversiLembur->whereRaw('1 = 0');
            } elseif ($transactionType === 'STATUS_HARI') {
                $queryShift->whereRaw('1 = 0');
                $queryTukarHari->whereRaw('1 = 0');
                $queryKonversiLembur->whereRaw('1 = 0');
            } elseif ($transactionType === 'KONVERSI_LEMBUR') {
                $queryShift->whereRaw('1 = 0');
                $queryTukarHari->whereRaw('1 = 0');
                $queryStatusHari->whereRaw('1 = 0');
            }
        }

        // Filter: Date Range
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta');
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta');

            $queryShift->whereExists(function ($subquery) use ($startDate, $endDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Det as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '>=', $startDate)
                    ->whereDate('detail.Tanggal_Shift', '<=', $endDate);
            });

            $queryTukarHari->where(function ($q) use ($startDate, $endDate) {
                $q->whereDate('d.Tanggal_Bekerja', '>=', $startDate)
                    ->whereDate('d.Tanggal_Bekerja', '<=', $endDate)
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->whereDate('d.Tanggal_Off', '>=', $startDate)->whereDate('d.Tanggal_Off', '<=', $endDate);
                    });
            });

            $queryStatusHari->whereExists(function ($subquery) use ($startDate, $endDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Status_Hari_Det as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal', '>=', $startDate)
                    ->whereDate('detail.Tanggal', '<=', $endDate);
            });
            $queryKonversiLembur->where(function ($q) use ($startDate, $endDate) {
                $q->whereDate('det.Tanggal_Lembur', '>=', $startDate)
                    ->whereDate('det.Tanggal_Lembur', '<=', $endDate)
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->whereNotNull('det.Target_Off')
                            ->whereDate('det.Target_Off', '>=', $startDate)
                            ->whereDate('det.Target_Off', '<=', $endDate);
                    });
            });
        } elseif (! empty($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta');
            $queryShift->whereExists(function ($subquery) use ($startDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Det as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '>=', $startDate);
            });
            $queryTukarHari->where(function ($q) use ($startDate) {
                $q->whereDate('d.Tanggal_Bekerja', '>=', $startDate)->orWhereDate('d.Tanggal_Off', '>=', $startDate);
            });
            $queryStatusHari->whereExists(function ($subquery) use ($startDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Status_Hari_Det as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal', '>=', $startDate);
            });
            $queryKonversiLembur->where(function ($q) use ($startDate) {
                $q->whereDate('det.Tanggal_Lembur', '>=', $startDate)->orWhere(function ($q2) use ($startDate) {
                    $q2->whereNotNull('det.Target_Off')->whereDate('det.Target_Off', '>=', $startDate);
                });
            });
        } elseif (! empty($filters['end_date'])) {
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta');
            $queryShift->whereExists(function ($subquery) use ($endDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Det as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '<=', $endDate);
            });
            $queryTukarHari->where(function ($q) use ($endDate) {
                $q->whereDate('d.Tanggal_Bekerja', '<=', $endDate)->orWhereDate('d.Tanggal_Off', '<=', $endDate);
            });
            $queryStatusHari->whereExists(function ($subquery) use ($endDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Status_Hari_Det as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal', '<=', $endDate);
            });
            $queryKonversiLembur->where(function ($q) use ($endDate) {
                $q->whereDate('det.Tanggal_Lembur', '<=', $endDate)->orWhere(function ($q2) use ($endDate) {
                    $q2->whereNotNull('det.Target_Off')->whereDate('det.Target_Off', '<=', $endDate);
                });
            });
        }

        // === GET DATA ===
        $itemsShift = $queryShift
            ->select(
                'h.*',
                'd.Target_Table',
                'h.Jenis_Transaksi',
                'ar.Id as approval_id',
                'ar.Flag_Approval as Approval_Status',
                'ar.Tanggal_Approval',
                'ar.No_Transaksi',
                'ar.Catatan as Approval_Notes',
                'k.Nama as Nama_Karyawan',
                'kr.Nama as Nama_Pengaju',
                'k.UserID_Absen as NIK',
                'kr.UserID_Absen as NIK_Pengaju',
                'vg.nama_level',
                'vg.nama_jabatan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                DB::raw("'SHIFT' as transaction_type"),
            )
            ->get();

        $itemsTukarHari = $queryTukarHari
            ->select(
                'h.*',
                DB::raw('NULL as Target_Table'),
                'h.Jenis_Transaksi',
                'ar.Id as approval_id',
                'ar.Flag_Approval as Approval_Status',
                'ar.Tanggal_Approval',
                'ar.No_Transaksi',
                'ar.Catatan as Approval_Notes',
                'k.Nama as Nama_Karyawan',
                'kr.Nama as Nama_Pengaju',
                'k.UserID_Absen as NIK',
                'kr.UserID_Absen as NIK_Pengaju',
                'vg.nama_level',
                'vg.nama_jabatan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                DB::raw("'TUKAR_HARI' as transaction_type"),
            )
            ->get();

        $itemsStatusHari = $queryStatusHari
            ->select(
                'h.*',
                DB::raw(
                    '(select top 1 d.Target_Table from N_HRIS_Transaksi_Status_Hari_Detail d where d.No_Transaksi = h.No_Transaksi and d.Status is null) as Target_Table',
                ),
                'h.Jenis_Transaksi',
                'ar.Id as approval_id',
                'ar.Flag_Approval as Approval_Status',
                'ar.Tanggal_Approval',
                'ar.No_Transaksi',
                'ar.Catatan as Approval_Notes',
                'k.Nama as Nama_Karyawan',
                'kr.Nama as Nama_Pengaju',
                'k.UserID_Absen as NIK',
                'kr.UserID_Absen as NIK_Pengaju',
                'vg.nama_level',
                'vg.nama_jabatan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                DB::raw("'STATUS_HARI' as transaction_type"),
            )
            ->get();

        $itemsKonversiLembur = $queryKonversiLembur
            ->select(
                'h.*',
                DB::raw('NULL as Target_Table'),
                'h.Jenis_Transaksi',
                'ar.Id as approval_id',
                'ar.Flag_Approval as Approval_Status',
                'ar.Tanggal_Approval',
                'ar.No_Transaksi',
                'ar.Catatan as Approval_Notes',
                'k.Nama as Nama_Karyawan',
                'kr.Nama as Nama_Pengaju',
                'k.UserID_Absen as NIK',
                'kr.UserID_Absen as NIK_Pengaju',
                'vg.nama_level',
                'vg.nama_jabatan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                'd.Note as Alasan',
                'det.Tanggal_Lembur',
                'det.Target_Off',
                'det.Jenis_Konversi',
                'det.Durasi_Jam',
                DB::raw("'KONVERSI_LEMBUR' as transaction_type"),
            )
            ->get();

        // Merge and sort
        $allItems = $itemsShift
            ->merge($itemsTukarHari)
            ->merge($itemsStatusHari)
            ->merge($itemsKonversiLembur)
            ->unique('No_Transaksi')
            ->sortByDesc('Tanggal_Approval')
            ->values();

        // Fallback: if join-based queries return empty, use approval request history directly
        if ($allItems->isEmpty()) {
            $fallbackQuery = DB::table('HRIS_Approval_Request as ar')
                ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'vg.Id_Level_Jabatan',
                    '=',
                    'kr.Id_Level_Jabatan',
                )
                ->where('ar.Approved_By_Kode', $kodeApprover)
                ->whereNotNull('ar.Flag_Approval')
                ->where(function ($q) {
                    $q->where('ar.No_Transaksi', 'like', 'SH%')
                        ->orWhere('ar.No_Transaksi', 'like', 'TH%')
                        ->orWhere('ar.No_Transaksi', 'like', 'TSH%')
                        ->orWhere('ar.No_Transaksi', 'like', 'KL%');
                });

            if (! empty($filters['status']) && $filters['status'] !== 'all') {
                if ($filters['status'] === 'approved') {
                    $fallbackQuery->where('ar.Flag_Approval', 'Y');
                } elseif ($filters['status'] === 'rejected') {
                    $fallbackQuery->where('ar.Flag_Approval', 'T');
                }
            }

            if (! empty($filters['search'])) {
                $search = $filters['search'];
                $fallbackQuery->where(function ($q) use ($search) {
                    $q->where('ar.No_Transaksi', 'like', "%{$search}%")
                        ->orWhere('kr.Nama', 'like', "%{$search}%")
                        ->orWhere('kr.UserID_Absen', 'like', "%{$search}%");
                });
            }

            if (! empty($filters['jabatan'])) {
                $fallbackQuery->where('vg.Id_Jabatan', $filters['jabatan']);
            }

            if (! empty($filters['transaction_type'])) {
                $transactionType = strtoupper((string) $filters['transaction_type']);
                if ($transactionType === 'SHIFT') {
                    $fallbackQuery->where('ar.No_Transaksi', 'like', 'SH%');
                } elseif ($transactionType === 'TUKAR_HARI') {
                    $fallbackQuery->where('ar.No_Transaksi', 'like', 'TH%');
                } elseif ($transactionType === 'STATUS_HARI') {
                    $fallbackQuery->where('ar.No_Transaksi', 'like', 'TSH%');
                } elseif ($transactionType === 'KONVERSI_LEMBUR') {
                    $fallbackQuery->where('ar.No_Transaksi', 'like', 'KL%');
                }
            }

            if (! empty($filters['start_date'])) {
                $fallbackQuery->whereDate(
                    'ar.Tanggal_Approval',
                    '>=',
                    Carbon::parse($filters['start_date'])->toDateString(),
                );
            }

            if (! empty($filters['end_date'])) {
                $fallbackQuery->whereDate(
                    'ar.Tanggal_Approval',
                    '<=',
                    Carbon::parse($filters['end_date'])->toDateString(),
                );
            }

            $fallbackItems = $fallbackQuery
                ->select(
                    'ar.No_Transaksi',
                    'ar.Flag_Approval as Approval_Status',
                    'ar.Tanggal_Approval',
                    'ar.Catatan as Approval_Notes',
                    'kr.Nama as Nama_Pengaju',
                    'kr.UserID_Absen as NIK_Pengaju',
                    'vg.nama_jabatan',
                )
                ->orderByDesc('ar.Tanggal_Approval')
                ->get()
                ->map(function ($row) {
                    $transactionType = str_starts_with($row->No_Transaksi, 'TSH')
                        ? 'STATUS_HARI'
                        : (str_starts_with($row->No_Transaksi, 'KL')
                            ? 'KONVERSI_LEMBUR'
                            : (str_starts_with($row->No_Transaksi, 'TH')
                                ? 'TUKAR_HARI'
                                : 'SHIFT'));

                    $headerTable =
                        $transactionType === 'SHIFT'
                            ? 'N_HRIS_Transaksi_Shift'
                            : ($transactionType === 'TUKAR_HARI'
                                ? 'N_HRIS_Transaksi_Tukar_Hari'
                                : ($transactionType === 'KONVERSI_LEMBUR'
                                    ? 'N_HRIS_Transaksi_Konversi_Lembur'
                                    : 'N_HRIS_Transaksi_Status_Hari'));

                    $header = DB::table($headerTable)->where('No_Transaksi', $row->No_Transaksi)->first();

                    $item = (object) array_merge((array) ($header ?? []), [
                        'No_Transaksi' => $row->No_Transaksi,
                        'Approval_Status' => $row->Approval_Status,
                        'Tanggal_Approval' => $row->Tanggal_Approval,
                        'Approval_Notes' => $row->Approval_Notes,
                        'Nama_Pengaju' => $row->Nama_Pengaju,
                        'NIK_Pengaju' => $row->NIK_Pengaju,
                        'nama_jabatan' => $row->nama_jabatan,
                        'transaction_type' => $transactionType,
                    ]);

                    return $item;
                });

            $allItems = $fallbackItems->unique('No_Transaksi')->sortByDesc('Tanggal_Approval')->values();
        }

        $totalItems = $allItems->count();

        // Manual pagination
        $items = $allItems->slice($offset, $limit)->values();

        // Baris tracking approver diambil SEKALI untuk seluruh transaksi di
        // halaman ini. Sebelumnya tiap transaksi memanggil getApproverHistory()
        // sendiri, dan panggilan itu berharga DUA kueri (count + get) sementara
        // `total`-nya dibuang — jadi separuhnya dikerjakan untuk jawaban yang tak
        // pernah dibaca. Terukur di production: halaman ini rata-rata 52 kueri
        // dengan 11.394 ms waktu DB, 100% waktu permintaannya.
        $trackingPerTransaksi = $this->approvalDetailService
            ->itemsForApprover($kodeApprover, $items->pluck('No_Transaksi')->all())
            ->groupBy('No_Transaksi');

        // Semua baris tracking di riwayat ini diputuskan oleh $kodeApprover. View
        // tracking tidak membawa nama approver — Karyawan_Nama di sana adalah
        // karyawan yang diajukan — jadi namanya diambil sekali di sini.
        $namaApprover = DB::table('Karyawan')->where('Kode_Karyawan', $kodeApprover)->value('Nama');

        // Enrich with details FOR HISTORY (show approved/rejected by this approver)
        foreach ($items as $item) {
            $this->enrichTransactionWithDetailsForHistory(
                $item,
                $kodeApprover,
                $trackingPerTransaksi[$item->No_Transaksi] ?? collect(),
                $namaApprover,
            );
        }

        return [
            'data' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $totalItems,
        ];
    }

    /**
     * Enrich transaction with details from 3-tier structure
     * Joins: Detail (per Karyawan) → Det (per Tanggal) → Shift info
     */
    private function enrichTransactionWithDetails($item)
    {
        if ($item->transaction_type === 'KONVERSI_LEMBUR') {
            $details = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Detail as d')
                ->join('N_HRIS_Transaksi_Konversi_Lembur_Det as det', 'd.Id', '=', 'det.Id_Detail')
                ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as j',
                    'k.Id_Level_Jabatan',
                    '=',
                    'j.Id_Level_Jabatan',
                )
                ->where('d.No_Transaksi', $item->No_Transaksi)
                ->whereNull('d.Status')
                ->whereNull('det.Status')
                ->select(
                    'd.Id',
                    'det.Id as Id_Det',
                    'd.Kode_Karyawan',
                    'k.Nama as Nama_Karyawan',
                    'k.NIK',
                    'j.nama_jabatan as Nama_Jabatan',
                    'd.Note as Alasan',
                    'd.File_Path as Lampiran',
                    'd.Flag_Approval as Detail_Approval',
                    'det.Tanggal_Lembur as Tanggal_Shift',
                    'det.Tanggal_Lembur',
                    'det.Target_Off',
                    'det.CheckIn_Pilih',
                    'det.CheckOut_Pilih',
                    'det.Jenis_Konversi',
                    'det.Durasi_Jam',
                    'det.Flag_Approval',
                    'det.Needs_Approval',
                    'det.Note',
                    'det.Note as Det_Note',
                )
                ->orderBy('det.Tanggal_Lembur')
                ->get()
                ->map(function ($detail) {
                    $detail->Lampiran_Url = $this->resolveStoredAttachmentUrl($detail->Lampiran ?? null);

                    if (empty($detail->Target_Off)) {
                        $detail->Target_Off = $this->extractKonversiTargetOffDate(
                            $detail->Det_Note ?? ($detail->Note ?? null),
                        );
                    }

                    return $detail;
                });

            $karyawanDetails = $details
                ->groupBy('Kode_Karyawan')
                ->map(function ($rows) {
                    $first = $rows->first();

                    return (object) [
                        'Id' => $first->Id ?? null,
                        'Kode_Karyawan' => $first->Kode_Karyawan ?? null,
                        'Nama_Karyawan' => $first->Nama_Karyawan ?? '-',
                        'NIK' => $first->NIK ?? null,
                        'Nama_Jabatan' => $first->Nama_Jabatan ?? null,
                        'Detail_Approval' => $first->Detail_Approval ?? null,
                    ];
                })
                ->values();

            $item->total_karyawan = $karyawanDetails->count();
            $item->total_days = $details->count();
            $item->karyawan = $karyawanDetails;
            $item->dates_summary = $details
                ->map(function ($d) {
                    return Carbon::parse($d->Tanggal_Lembur)->format('d M');
                })
                ->unique()
                ->take(3)
                ->implode(', ');
            $item->details = $details;
        } elseif ($item->transaction_type === 'TUKAR_HARI') {
            // === TUKAR HARI ENRICHMENT ===
            $detail = DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail as d')
                ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin('HRIS_Shift_Kerja as s', 'd.Id_Shift_Bekerja', '=', 's.ID_Shift')
                ->where('d.No_Transaksi', $item->No_Transaksi)
                ->select(
                    'd.Kode_Karyawan',
                    'k.Nama as Nama_Karyawan',
                    'd.Tanggal_Bekerja',
                    'd.Tanggal_Off',
                    'd.Id_Shift_Bekerja',
                    's.Nama as Nama_Shift',
                    'd.Flag_Approval',
                )
                ->whereNull('d.Status')
                ->first();

            $item->total_karyawan = 1;
            $item->total_days = 1;
            $item->karyawan = collect($detail ? [$detail] : []);
            $item->dates_summary = $detail
                ? Carbon::parse($detail->Tanggal_Bekerja)->format('d M').
                    ' ↔ '.
                    Carbon::parse($detail->Tanggal_Off)->format('d M')
                : 'N/A';
            $item->details = collect($detail ? [$detail] : []);
        } elseif ($item->transaction_type === 'STATUS_HARI') {
            // === STATUS HARI ENRICHMENT (3-tier) ===
            $karyawanDetails = DB::table('N_HRIS_Transaksi_Status_Hari_Detail as d')
                ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as j',
                    'k.Id_Level_Jabatan',
                    '=',
                    'j.Id_Level_Jabatan',
                )
                ->where('d.No_Transaksi', $item->No_Transaksi)
                ->whereNull('d.Status')
                ->select(
                    'd.Id',
                    'd.Kode_Karyawan',
                    'k.Nama as Nama_Karyawan',
                    'k.NIK',
                    'j.nama_jabatan as Nama_Jabatan',
                    'd.Target_Table',
                    'd.Flag_Approval as Detail_Approval',
                )
                ->get();

            $statusDets = DB::table('N_HRIS_Transaksi_Status_Hari_Det as det')
                ->join('N_HRIS_Transaksi_Status_Hari_Detail as d', 'det.Id_Detail', '=', 'd.Id')
                ->where('det.No_Transaksi', $item->No_Transaksi)
                ->where('det.Needs_Approval', 'Y')
                ->whereNull('d.Status')
                ->whereNull('det.Status')
                ->whereNull('det.Flag_Approval') // Only pending (not yet approved/rejected)
                ->select(
                    'det.Id as Id_Det',
                    'd.Id as Id_Detail',
                    'd.Kode_Karyawan',
                    'det.Tanggal as Tanggal_Shift',
                    'det.Status_Hari',
                    'det.Flag_Approval',
                    'det.Needs_Approval',
                    'det.Jenis_Pengajuan',
                )
                ->orderBy('det.Tanggal')
                ->get();

            $item->total_karyawan = $karyawanDetails->count();
            $item->total_days = $statusDets->count();
            $item->karyawan = $karyawanDetails;

            $item->dates_summary = $statusDets
                ->map(function ($d) {
                    return Carbon::parse($d->Tanggal_Shift)->format('d M');
                })
                ->unique()
                ->take(3)
                ->implode(', ');

            if ($statusDets->unique('Tanggal_Shift')->count() > 3) {
                $item->dates_summary .= ', +'.($statusDets->unique('Tanggal_Shift')->count() - 3).' more';
            }

            $item->details = $statusDets;
        } else {
            // === SHIFT ENRICHMENT (EXISTING) ===
            // Get karyawan details first
            $karyawanDetails = DB::table('N_HRIS_Transaksi_Shift_Detail as d')
                ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as j',
                    'k.Id_Level_Jabatan',
                    '=',
                    'j.Id_Level_Jabatan',
                )
                ->where('d.No_Transaksi', $item->No_Transaksi)
                ->whereNull('d.Status')
                ->select(
                    'd.Id',
                    'd.Kode_Karyawan',
                    'k.Nama as Nama_Karyawan',
                    'k.NIK',
                    'j.nama_jabatan as Nama_Jabatan',
                    'd.Target_Table',
                    'd.Flag_Approval as Detail_Approval',
                )
                ->get();

            // Get all shift dates via Det table
            // IMPORTANT: Only show Dets that need approval AND are still pending (Flag_Approval IS NULL)
            // This ensures next approver only sees items they need to review, not ones already decided
            $shiftDets = DB::table('N_HRIS_Transaksi_Shift_Det as det')
                ->join('N_HRIS_Transaksi_Shift_Detail as d', 'det.Id_Detail', '=', 'd.Id')
                ->join('HRIS_Shift_Kerja as s', 'det.Id_Shift_Diminta', '=', 's.ID_Shift')
                ->leftJoin('HRIS_Shift_Kerja as s_old', 'det.Id_Shift_Sebelumnya', '=', 's_old.ID_Shift')
                ->where('det.No_Transaksi', $item->No_Transaksi)
                ->where('det.Needs_Approval', 'Y')
                ->whereNull('d.Status')
                ->whereNull('det.Status')
                ->whereNull('det.Flag_Approval') // Only pending (not yet approved/rejected)
                ->select(
                    'det.Id as Id_Det',
                    'd.Id as Id_Detail',
                    'd.Kode_Karyawan',
                    'det.Tanggal_Shift',
                    'det.Id_Shift_Sebelumnya',
                    's.Nama as Nama_Shift',
                    's_old.Nama as Nama_Shift_Sebelumnya',
                    'det.Flag_Approval',
                    'det.Needs_Approval',
                    'det.Jenis_Pengajuan',
                )
                ->orderBy('det.Tanggal_Shift')
                ->get();

            $item->total_karyawan = $karyawanDetails->count();
            $item->total_days = $shiftDets->count();
            $item->karyawan = $karyawanDetails;

            $item->dates_summary = $shiftDets
                ->map(function ($d) {
                    return Carbon::parse($d->Tanggal_Shift)->format('d M');
                })
                ->unique()
                ->take(3)
                ->implode(', ');

            if ($shiftDets->unique('Tanggal_Shift')->count() > 3) {
                $item->dates_summary .= ', +'.($shiftDets->unique('Tanggal_Shift')->count() - 3).' more';
            }

            $item->details = $shiftDets;
        }
    }

    /**
     * Enrich transaction with details FOR HISTORY
     * Shows only Dets that were approved/rejected by specific approver
     *
     * @param  object  $item  Transaction item
     * @param  string  $kodeApprover  Approver code to filter by
     */
    /**
     * @param  \Illuminate\Support\Collection<int,object>|null  $trackingItem
     *                                                                         Baris tracking milik transaksi ini, sudah diambil massal oleh pemanggil.
     *                                                                         Null berarti "ambil sendiri" — jalur itu dipertahankan supaya metode ini
     *                                                                         tetap benar bila kelak dipanggil di luar perulangan halaman.
     */
    /**
     * Baris tracking untuk satu transaksi + jenis.
     *
     * Memakai yang sudah diambil massal bila ada; kalau tidak, mengambil sendiri
     * supaya metode ini tetap benar di luar perulangan halaman. Penyaringan
     * `Jenis` dilakukan di sini agar hasilnya identik dengan versi per-transaksi
     * yang dulu menyaringnya di SQL.
     *
     * @param  \Illuminate\Support\Collection<int,object>|null  $trackingItem
     * @return \Illuminate\Support\Collection<int,object>
     */
    private function barisTracking($trackingItem, string $kodeApprover, string $noTransaksi, string $jenis)
    {
        $baris = $trackingItem
            ?? $this->approvalDetailService->itemsForApprover($kodeApprover, $noTransaksi, $jenis);

        return collect($baris)->filter(fn ($t) => ($t->Jenis ?? null) === $jenis)->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int,object>|null  $trackingItem
     *                                                                         Baris tracking milik transaksi ini, sudah diambil massal oleh pemanggil.
     *                                                                         Null berarti "ambil sendiri" — jalur itu dipertahankan supaya metode ini
     *                                                                         tetap benar bila kelak dipanggil di luar perulangan halaman.
     */
    private function enrichTransactionWithDetailsForHistory(
        $item,
        string $kodeApprover,
        $trackingItem = null,
        ?string $namaApprover = null,
    ) {
        if ($item->transaction_type === 'KONVERSI_LEMBUR') {
            $details = DB::table('N_HRIS_Transaksi_Konversi_Lembur_Detail as d')
                ->join('N_HRIS_Transaksi_Konversi_Lembur_Det as det', 'd.Id', '=', 'det.Id_Detail')
                ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as j',
                    'k.Id_Level_Jabatan',
                    '=',
                    'j.Id_Level_Jabatan',
                )
                ->where('d.No_Transaksi', $item->No_Transaksi)
                ->select(
                    'd.Id',
                    'det.Id as Id_Det',
                    'd.Kode_Karyawan',
                    'k.Nama as Nama_Karyawan',
                    'k.NIK',
                    'j.nama_jabatan as Nama_Jabatan',
                    'd.Note as Alasan',
                    'd.File_Path as Lampiran',
                    'd.Flag_Approval as Detail_Approval',
                    'det.Tanggal_Lembur as Tanggal_Shift',
                    'det.Tanggal_Lembur',
                    'det.Target_Off',
                    'det.CheckIn_Pilih',
                    'det.CheckOut_Pilih',
                    'det.Jenis_Konversi',
                    'det.Durasi_Jam',
                    'det.Flag_Approval',
                    'det.Needs_Approval',
                    'det.Note',
                    'det.Note as Det_Note',
                )
                ->orderBy('det.Tanggal_Lembur')
                ->get()
                ->map(function ($detail) {
                    $detail->Lampiran_Url = $this->resolveStoredAttachmentUrl($detail->Lampiran ?? null);

                    if (empty($detail->Target_Off)) {
                        $detail->Target_Off = $this->extractKonversiTargetOffDate(
                            $detail->Det_Note ?? ($detail->Note ?? null),
                        );
                    }

                    return $detail;
                });

            $karyawanDetails = $details
                ->groupBy('Kode_Karyawan')
                ->map(function ($rows) {
                    $first = $rows->first();

                    return (object) [
                        'Id' => $first->Id ?? null,
                        'Kode_Karyawan' => $first->Kode_Karyawan ?? null,
                        'Nama_Karyawan' => $first->Nama_Karyawan ?? '-',
                        'NIK' => $first->NIK ?? null,
                        'Nama_Jabatan' => $first->Nama_Jabatan ?? null,
                        'Detail_Approval' => $first->Detail_Approval ?? null,
                    ];
                })
                ->values();

            $item->total_karyawan = $karyawanDetails->count();
            $item->total_days = $details->count();
            $item->karyawan = $karyawanDetails;
            $item->dates_summary = $details
                ->map(function ($d) {
                    return Carbon::parse($d->Tanggal_Lembur)->format('d M');
                })
                ->unique()
                ->take(3)
                ->implode(', ');
            $item->details = $details;
        } elseif ($item->transaction_type === 'TUKAR_HARI') {
            // === TUKAR HARI ENRICHMENT (same as inbox) ===
            $detail = DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail as d')
                ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin('HRIS_Shift_Kerja as s', 'd.Id_Shift_Bekerja', '=', 's.ID_Shift')
                ->where('d.No_Transaksi', $item->No_Transaksi)
                ->whereNull('d.Status')
                ->select(
                    'd.Kode_Karyawan',
                    'k.Nama as Nama_Karyawan',
                    'd.Tanggal_Bekerja',
                    'd.Tanggal_Off',
                    'd.Id_Shift_Bekerja',
                    's.Nama as Nama_Shift',
                    'd.Flag_Approval',
                )
                ->first();

            $item->total_karyawan = 1;
            $item->total_days = 1;
            $item->karyawan = collect($detail ? [$detail] : []);
            $item->dates_summary = $detail
                ? Carbon::parse($detail->Tanggal_Bekerja)->format('d M').
                    ' ↔ '.
                    Carbon::parse($detail->Tanggal_Off)->format('d M')
                : 'N/A';
            $item->details = collect($detail ? [$detail] : []);
        } elseif ($item->transaction_type === 'STATUS_HARI') {
            // === STATUS HARI ENRICHMENT (3-tier) ===
            $karyawanDetails = DB::table('N_HRIS_Transaksi_Status_Hari_Detail as d')
                ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as j',
                    'k.Id_Level_Jabatan',
                    '=',
                    'j.Id_Level_Jabatan',
                )
                ->where('d.No_Transaksi', $item->No_Transaksi)
                ->whereNull('d.Status')
                ->select(
                    'd.Id',
                    'd.Kode_Karyawan',
                    'k.Nama as Nama_Karyawan',
                    'k.NIK',
                    'j.nama_jabatan as Nama_Jabatan',
                    'd.Target_Table',
                    'd.Flag_Approval as Detail_Approval',
                )
                ->get();

            // ✅ USE GENERIC TRACKING VIEW TO GET APPROVER HISTORY
            try {
                $baris = $this->barisTracking($trackingItem, $kodeApprover, $item->No_Transaksi, 'STATUS_HARI');

                $statusDetsTracked = $baris->map(function ($track) use ($namaApprover) {
                    $context = json_decode($track->Json_Context ?? '{}', true) ?: [];

                    return (object) [
                        'Id_Det' => null,
                        'Id_Detail' => null,
                        'Kode_Karyawan' => $track->Kode_Karyawan ?? null,
                        'Tanggal_Shift' => $track->Tanggal_Item ?? null,
                        'Status_Hari' => $context['Status_Hari'] ?? null,
                        'Target_Table' => $context['Target_Table'] ?? null,
                        'Flag_Approval' => $track->Item_Approval_Status ?? null,
                        'Needs_Approval' => 'Y',
                        'Jenis_Pengajuan' => $track->Deskripsi ?? null,
                        'Processed_By_Kode' => $track->Processed_By_Kode ?? null,
                        'Processed_By_Nama' => $namaApprover,
                        'Processed_At' => $track->Processed_At ?? null,
                        // Sama seperti pemetaan di bawah: view memakai Detail_Notes,
                        // bukan Notes. Di sini `??` menekan peringatannya, sehingga
                        // catatannya bukan galat melainkan diam-diam SELALU kosong —
                        // bentuk kegagalan yang lebih sulit dilihat daripada yang di bawah.
                        'Notes' => $track->Detail_Notes ?? ($track->Flow_Catatan ?? null),
                        'Source_Row_Key' => $track->Source_Row_Key ?? null,
                    ];
                });

                // Deskripsi di view tracking adalah Alasan, bukan Jenis_Pengajuan.
                // Id_Det, Jenis_Pengajuan, dan Needs_Approval diambil dari baris
                // Det-nya; Source_Row_Key berbentuk "No_Transaksi|Id_Det". Tanggal
                // yang langsung diterapkan (Needs_Approval = T) tetap punya baris
                // tracking, tetapi tidak pernah diputuskan approver — disaring
                // seperti di jalur cadangan.
                $idDets = $statusDetsTracked
                    ->map(fn ($det) => (int) last(explode('|', (string) $det->Source_Row_Key)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $detById = empty($idDets)
                    ? collect()
                    : DB::table('N_HRIS_Transaksi_Status_Hari_Det as det')
                        ->join('N_HRIS_Transaksi_Status_Hari_Detail as d', 'det.Id_Detail', '=', 'd.Id')
                        ->whereIn('det.Id', $idDets)
                        ->select(
                            'det.Id as Id_Det',
                            'det.Id_Detail',
                            'd.Target_Table',
                            'det.Status_Hari',
                            'det.Jenis_Pengajuan',
                            'det.Needs_Approval',
                        )
                        ->get()
                        ->keyBy('Id_Det');

                $statusDetsTracked = $statusDetsTracked
                    ->map(function ($det) use ($detById) {
                        $sumber = $detById->get((int) last(explode('|', (string) $det->Source_Row_Key)));
                        if ($sumber) {
                            $det->Id_Det = $sumber->Id_Det;
                            $det->Id_Detail = $sumber->Id_Detail;
                            $det->Target_Table = $sumber->Target_Table;
                            $det->Status_Hari = $sumber->Status_Hari ?? $det->Status_Hari;
                            $det->Jenis_Pengajuan = $sumber->Jenis_Pengajuan;
                            $det->Needs_Approval = $sumber->Needs_Approval;
                        }
                        unset($det->Source_Row_Key);

                        return $det;
                    })
                    ->filter(fn ($det) => $det->Needs_Approval === 'Y')
                    ->sortBy('Tanggal_Shift')
                    ->values();
            } catch (\Exception $e) {
                Log::error('Error fetching status hari approver history: '.$e->getMessage());
                $statusDetsTracked = collect([]);
            }

            // Fallback for old transactions without tracking rows
            $statusDets = $statusDetsTracked;
            if ($statusDets->isEmpty()) {
                $statusDets = DB::table('N_HRIS_Transaksi_Status_Hari_Det as det')
                    ->join('N_HRIS_Transaksi_Status_Hari_Detail as d', 'det.Id_Detail', '=', 'd.Id')
                    ->where('det.No_Transaksi', $item->No_Transaksi)
                    ->where('det.Needs_Approval', 'Y')
                    ->whereNull('d.Status')
                    ->whereNull('det.Status')
                    ->select(
                        'det.Id as Id_Det',
                        'd.Id as Id_Detail',
                        'd.Kode_Karyawan',
                        'd.Target_Table',
                        'det.Tanggal as Tanggal_Shift',
                        'det.Status_Hari',
                        'det.Flag_Approval',
                        'det.Needs_Approval',
                        'det.Jenis_Pengajuan',
                    )
                    ->orderBy('det.Tanggal')
                    ->get();

                // Tanpa baris tracking tidak ada catatan siapa yang menolak tiap
                // tanggal. Bila transaksinya hanya punya satu langkah approval,
                // penolaknya pasti approver itu; bila bertingkat, tolakan bisa
                // datang dari langkah mana pun, jadi namanya dibiarkan kosong
                // daripada menyebut orang yang salah.
                $langkah = DB::table('HRIS_Approval_Request')
                    ->where('No_Transaksi', $item->No_Transaksi)
                    ->whereNull('Status')
                    ->pluck('Approved_By_Kode');

                if ($langkah->count() === 1 && trim((string) $langkah->first()) === trim($kodeApprover)) {
                    $statusDets->each(function ($det) use ($kodeApprover, $namaApprover) {
                        if ($det->Flag_Approval === 'T') {
                            $det->Processed_By_Kode = $kodeApprover;
                            $det->Processed_By_Nama = $namaApprover;
                        }
                    });
                }
            }

            // For history: show all det items (not just decided ones)
            // TSH approval is often done at header level, so Det Flag_Approval may remain null
            $item->total_days = $statusDets->count();

            // Build karyawan list from Detail table; if empty, derive from distinct Kode_Karyawan in Det data
            if ($karyawanDetails->isEmpty() && $statusDets->isNotEmpty()) {
                $karyawanDetails = $statusDets
                    ->unique('Kode_Karyawan')
                    ->map(function ($det) {
                        return (object) [
                            'Id' => $det->Id_Detail ?? null,
                            'Kode_Karyawan' => $det->Kode_Karyawan,
                            'Nama_Karyawan' => $det->Kode_Karyawan, // fallback; will be enriched below
                            'NIK' => null,
                            'Nama_Jabatan' => null,
                            'Target_Table' => $det->Target_Table ?? null,
                            'Detail_Approval' => property_exists($det, 'Flag_Approval') ? $det->Flag_Approval : null,
                        ];
                    })
                    ->values();

                // Try to enrich names from Karyawan table
                $kodes = $karyawanDetails->pluck('Kode_Karyawan')->filter()->unique()->all();
                if (! empty($kodes)) {
                    $karyawanMap = DB::table('Karyawan as k')
                        ->leftJoin(
                            'View_Golongan_Sub_Golongan_Level_Jabatan as j',
                            'k.Id_Level_Jabatan',
                            '=',
                            'j.Id_Level_Jabatan',
                        )
                        ->whereIn('k.Kode_Karyawan', $kodes)
                        ->select('k.Kode_Karyawan', 'k.Nama', 'k.NIK', 'j.nama_jabatan as Nama_Jabatan')
                        ->get()
                        ->keyBy('Kode_Karyawan');

                    $karyawanDetails = $karyawanDetails->map(function ($detail) use ($karyawanMap) {
                        $info = $karyawanMap->get($detail->Kode_Karyawan);
                        if ($info) {
                            $detail->Nama_Karyawan = $info->Nama;
                            $detail->NIK = $info->NIK;
                            $detail->Nama_Jabatan = $info->Nama_Jabatan;
                        }

                        return $detail;
                    });
                }
            }

            $item->total_karyawan = $karyawanDetails->count();
            $item->karyawan = $karyawanDetails;

            $item->dates_summary = $statusDets
                ->map(function ($d) {
                    return Carbon::parse($d->Tanggal_Shift)->format('d M');
                })
                ->unique()
                ->take(3)
                ->implode(', ');

            if ($statusDets->unique('Tanggal_Shift')->count() > 3) {
                $item->dates_summary .= ', +'.($statusDets->unique('Tanggal_Shift')->count() - 3).' more';
            }

            $item->details = $statusDets; // Show all for detail view
        } else {
            // === SHIFT ENRICHMENT FOR HISTORY (Generic Approval Architecture) ===
            $karyawanDetails = DB::table('N_HRIS_Transaksi_Shift_Detail as d')
                ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as j',
                    'k.Id_Level_Jabatan',
                    '=',
                    'j.Id_Level_Jabatan',
                )
                ->where('d.No_Transaksi', $item->No_Transaksi)
                ->whereNull('d.Status')
                ->select(
                    'd.Id',
                    'd.Kode_Karyawan',
                    'k.Nama as Nama_Karyawan',
                    'k.NIK',
                    'j.nama_jabatan as Nama_Jabatan',
                    'd.Target_Table',
                    'd.Flag_Approval as Detail_Approval',
                )
                ->get();

            // ✅ USE GENERIC TRACKING VIEW TO GET APPROVER HISTORY
            try {
                $baris = $this->barisTracking($trackingItem, $kodeApprover, $item->No_Transaksi, 'SHIFT');

                // Transform tracking data to match frontend expectations
                $shiftDets = $baris->map(function ($track) use ($namaApprover) {
                    return (object) [
                        'Id_Det' => null,
                        'Id_Detail' => null,
                        'Kode_Karyawan' => $track->Kode_Karyawan,
                        'Tanggal_Shift' => $track->Tanggal_Item,
                        'Id_Shift_Sebelumnya' => null,
                        'Nama_Shift' => $track->Deskripsi,
                        'Nama_Shift_Sebelumnya' => null,
                        'Flag_Approval' => $track->Item_Approval_Status,
                        'Needs_Approval' => 'Y',
                        'Jenis_Pengajuan' => 'REQUEST',
                        'Processed_By_Kode' => $track->Processed_By_Kode,
                        'Processed_By_Nama' => $namaApprover,
                        'Processed_At' => $track->Processed_At,
                        // View N_HRIS_VW_Approval_Tracking_Generic memakai
                        // Detail_Notes (catatan per item) dan Flow_Catatan (catatan
                        // langkah). Tidak ada kolom bernama 'Notes'.
                        //
                        // Membaca properti bernama Notes melempar "Undefined
                        // property", tertangkap catch di bawah, lalu SELURUH riwayat
                        // approver dipulangkan sebagai koleksi kosong. Layarnya tidak
                        // menampilkan galat apa pun — riwayatnya sekadar tidak ada,
                        // dan itu terbaca seperti "memang belum ada yang memproses".
                        // Muncul 21 kali pada log 10 September 2026.
                        'Notes' => $track->Detail_Notes ?? ($track->Flow_Catatan ?? null),
                        'Source_Row_Key' => $track->Source_Row_Key ?? null,
                    ];
                });

                // Deskripsi di view tracking adalah Alasan milik baris Det, bukan
                // nama shift — hampir selalu NULL, sehingga modal riwayat hanya
                // menampilkan "-". Nama shift lama/baru dan Jenis_Pengajuan diambil
                // dari baris Det-nya; Source_Row_Key berbentuk
                // "Kode_Perusahaan|No_Transaksi|Id_Det". Keputusan (Flag_Approval)
                // tetap dari tracking karena itulah keputusan approver ini.
                $idDets = $shiftDets
                    ->map(fn ($det) => (int) last(explode('|', (string) $det->Source_Row_Key)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $detById = empty($idDets)
                    ? collect()
                    : DB::table('N_HRIS_Transaksi_Shift_Det as det')
                        ->leftJoin('HRIS_Shift_Kerja as s', 'det.Id_Shift_Diminta', '=', 's.ID_Shift')
                        ->leftJoin('HRIS_Shift_Kerja as s_old', 'det.Id_Shift_Sebelumnya', '=', 's_old.ID_Shift')
                        ->whereIn('det.Id', $idDets)
                        ->select(
                            'det.Id as Id_Det',
                            'det.Id_Detail',
                            'det.Id_Shift_Sebelumnya',
                            's.Nama as Nama_Shift',
                            's_old.Nama as Nama_Shift_Sebelumnya',
                            'det.Jenis_Pengajuan',
                        )
                        ->get()
                        ->keyBy('Id_Det');

                $shiftDets = $shiftDets
                    ->map(function ($det) use ($detById) {
                        $sumber = $detById->get((int) last(explode('|', (string) $det->Source_Row_Key)));
                        if ($sumber) {
                            $det->Id_Det = $sumber->Id_Det;
                            $det->Id_Detail = $sumber->Id_Detail;
                            $det->Id_Shift_Sebelumnya = $sumber->Id_Shift_Sebelumnya;
                            $det->Nama_Shift = $sumber->Nama_Shift;
                            $det->Nama_Shift_Sebelumnya = $sumber->Nama_Shift_Sebelumnya;
                            $det->Jenis_Pengajuan = $sumber->Jenis_Pengajuan;
                        }
                        unset($det->Source_Row_Key);

                        return $det;
                    })
                    ->sortBy('Tanggal_Shift')
                    ->values();
            } catch (\Exception $e) {
                Log::error('Error fetching approver history: '.$e->getMessage());
                // Fallback to empty array
                $shiftDets = collect([]);
            }

            if ($shiftDets->isEmpty()) {
                // Fallback for old transactions without tracking rows (backward compatibility)
                $shiftDets = DB::table('N_HRIS_Transaksi_Shift_Det as det')
                    ->join('N_HRIS_Transaksi_Shift_Detail as d', 'det.Id_Detail', '=', 'd.Id')
                    ->join('HRIS_Shift_Kerja as s', 'det.Id_Shift_Diminta', '=', 's.ID_Shift')
                    ->leftJoin('HRIS_Shift_Kerja as s_old', 'det.Id_Shift_Sebelumnya', '=', 's_old.ID_Shift')
                    ->where('det.No_Transaksi', $item->No_Transaksi)
                    ->where('det.Needs_Approval', 'Y')
                    ->whereNull('det.Status')
                    ->whereNull('d.Status')
                    ->select(
                        'det.Id as Id_Det',
                        'd.Id as Id_Detail',
                        'd.Kode_Karyawan',
                        'det.Tanggal_Shift',
                        'det.Id_Shift_Sebelumnya',
                        's.Nama as Nama_Shift',
                        's_old.Nama as Nama_Shift_Sebelumnya',
                        'det.Flag_Approval',
                        'det.Needs_Approval',
                        'det.Jenis_Pengajuan',
                    )
                    ->orderBy('det.Tanggal_Shift')
                    ->get();
            }

            // Count only items that were decided (not NULL)
            $decidedDets = $shiftDets->filter(fn ($det) => $det->Flag_Approval !== null);

            $item->total_karyawan = $karyawanDetails->count();
            $item->total_days = $decidedDets->count(); // Count decided items
            $item->karyawan = $karyawanDetails;

            $item->dates_summary = $decidedDets
                ->map(function ($d) {
                    return Carbon::parse($d->Tanggal_Shift)->format('d M');
                })
                ->unique()
                ->take(3)
                ->implode(', ');

            if ($decidedDets->unique('Tanggal_Shift')->count() > 3) {
                $item->dates_summary .= ', +'.($decidedDets->unique('Tanggal_Shift')->count() - 3).' more';
            }

            $item->details = $shiftDets; // Show all for detail view
        }
    }

    protected function resolveStoredAttachmentUrl(?string $filePath): ?string
    {
        $path = trim((string) ($filePath ?? ''));
        if ($path === '') {
            return null;
        }

        if (preg_match('/^https?:\\/\\//i', $path)) {
            return $path;
        }

        try {
            if (Storage::disk('gcs')->exists($path)) {
                return Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(20));
            }
        } catch (\Throwable $th) {
            // Fall through to raw path if signed URL generation fails.
        }

        return $path;
    }

    /**
     * Process approval (Approve/Reject)
     * Supports partial approval by updating details first
     */
    public function processApproval(
        string $kodeApprover,
        string $noTransaksi,
        string $action,
        array $detailApprovals = [],
        ?string $notes = null,
        bool $approvalAlreadyProcessed = false,
    ): array {
        // dd($detailApprovals);
        // Detect transaction type by prefix and route to appropriate handler
        if (str_starts_with($noTransaksi, 'KL')) {
            return $this->processKonversiLemburApproval($noTransaksi, $action);
        }

        if (str_starts_with($noTransaksi, 'TH')) {
            // TUKAR HARI transaction
            return $this->processTukarHariApproval(
                $kodeApprover,
                $noTransaksi,
                $action,
                $notes,
                $approvalAlreadyProcessed,
            );
        }

        if (str_starts_with($noTransaksi, 'TSH')) {
            // STATUS HARI transaction
            return $this->processStatusHariApproval(
                $kodeApprover,
                $noTransaksi,
                $action,
                $detailApprovals,
                $notes,
                $approvalAlreadyProcessed,
            );
        }

        // SHIFT transaction (existing logic)
        return DB::transaction(function () use (
            $kodeApprover,
            $noTransaksi,
            $action,
            $detailApprovals,
            $notes,
            $approvalAlreadyProcessed,
        ) {
            // 1. Validate Approval Request
            // dd($detailApprovals, $noTransaksi, $action, $approvalAlreadyProcessed);
            // dd($kodeApprover, $noTransaksi, $action, $approvalAlreadyProcessed);
            if ($approvalAlreadyProcessed) {
                $statusApproval = $action === 'APPROVE' ? 'Y' : 'T';
                $approvalReq = DB::table('HRIS_Approval_Request')
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Approved_By_Kode', $kodeApprover)
                    ->where('Flag_Approval', $statusApproval)
                    ->whereNotNull('Tanggal_Approval')
                    ->orderBy('Tanggal_Approval', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();
            } else {
                $approvalReq = DB::table('HRIS_Approval_Request')
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Approver_Karyawan_Kode', $kodeApprover)
                    ->whereNull('Status')
                    ->whereNull('Flag_Approval')
                    ->first();
            }

            if (! $approvalReq) {
                throw new \Exception('Approval request not found or already processed.');
            }

            // Get current approver info including Is_Mandatory
            $currentApproverInfo = DB::table('N_HRIS_VW_Approval_Request_V2')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Flow_State', 'CURRENT')
                ->where('Request_Order_Flow', $approvalReq->Request_Order_Flow)
                ->first();

            $isMandatory = $currentApproverInfo?->Is_Mandatory ?? 'Y';

            // 2. Handle Partial Detail Approvals (Tier 3 Det level)
            if (! empty($detailApprovals)) {
                // Check if there's a next approver
                $hasNextApprover = $this->hasCurrentApproverInFlow($noTransaksi);

                $updatedCount = 0;
                $detailUpdateErrorCount = 0;

                // Partial approval: update each Det based on decision
                foreach ($detailApprovals as $detId => $approvalAction) {
                    // $approvalAction: 'Y' (approve) or 'T' (reject)
                    // For APPROVE action:
                    // - If there's a next approver: Flag_Approval = NULL (stays pending for next approver)
                    // - If there's NO next approver: Flag_Approval = 'Y' (final approval)
                    // For REJECT action: Flag_Approval = 'T' (always, regardless of flow)
                    if ($approvalAction === 'Y' && $hasNextApprover) {
                        // Intermediate approver: Keep NULL for next approver to validate
                        $detStatus = null;
                    } else {
                        // Final approver or reject: Set Y or T
                        $detStatus = $approvalAction;
                    }

                    if ($detStatus !== null) {
                        DB::table('N_HRIS_Transaksi_Shift_Det')
                            ->where('No_Transaksi', $noTransaksi)
                            ->whereNull('Status')
                            ->where('Id', $detId)
                            ->where('Needs_Approval', 'Y')
                            ->update(['Flag_Approval' => $detStatus]);
                        $updatedCount++;
                    }

                    // ✅ UPDATE APPROVAL DETAIL RECORD (Generic Architecture)
                    try {
                        // Find corresponding approval detail record by Source_Row_Key
                        $sourceRowKey = $this->buildSourceRowKeyForShiftDet($detId, $noTransaksi);

                        if (! $sourceRowKey) {
                            Log::channel('ApprovalDetailService')->warning(
                                'Could not build Source_Row_Key for Shift Det',
                                [
                                    'det_id' => $detId,
                                    'no_transaksi' => $noTransaksi,
                                ],
                            );
                            $detailUpdateErrorCount++;

                            continue;
                        }

                        // Debug: Log info sebelum search
                        Log::channel('ApprovalDetailService')->debug('Searching approval detail record', [
                            'id_approval_request' => $approvalReq->id,
                            'approver' => $approvalReq->Approver_Karyawan_Kode,
                            'flow_step' => $approvalReq->Request_Order_Flow,
                            'source_row_key' => $sourceRowKey,
                            'det_id' => $detId,
                        ]);

                        $detailRecord = DB::table('HRIS_Approval_Request_Detail')
                            ->where('Id_Approval_Request', $approvalReq->id)
                            ->where('Source_Row_Key', $sourceRowKey)
                            ->first();

                        if (! $detailRecord) {
                            // Check if record exists with same Source_Row_Key but different Id_Approval_Request
                            $otherApproverRecord = DB::table('HRIS_Approval_Request_Detail')
                                ->where('Source_Row_Key', $sourceRowKey)
                                ->first();

                            Log::channel('ApprovalDetailService')->warning(
                                'Approval detail record not found for this approver',
                                [
                                    'id_approval_request_searched' => $approvalReq->id,
                                    'approver' => $approvalReq->Approver_Karyawan_Kode,
                                    'flow_step' => $approvalReq->Request_Order_Flow,
                                    'source_row_key' => $sourceRowKey,
                                    'det_id' => $detId,
                                    'no_transaksi' => $noTransaksi,
                                    'found_record_for_other_approver' => $otherApproverRecord
                                        ? [
                                            'id' => $otherApproverRecord->Id,
                                            'id_approval_request' => $otherApproverRecord->Id_Approval_Request,
                                        ]
                                        : null,
                                ],
                            );
                            $detailUpdateErrorCount++;

                            continue;
                        }

                        // For intermediate approve: Record as 'Y' in approval detail, but don't update Flag_Approval on Det
                        $approvalDetailStatus = $approvalAction === 'Y' ? 'Y' : 'T';

                        Log::channel('ApprovalDetailService')->info('Updating approval detail record', [
                            'id_approval_request_detail' => $detailRecord->Id,
                            'id_approval_request' => $approvalReq->id,
                            'approver' => $approvalReq->Approver_Karyawan_Kode,
                            'flow_step' => $approvalReq->Request_Order_Flow,
                            'source_row_key' => $sourceRowKey,
                            'detail_status' => $approvalDetailStatus,
                        ]);

                        $this->approvalDetailService->updateApprovalDecision(
                            $detailRecord->Id,
                            $approvalDetailStatus,
                            $kodeApprover,
                            (int) ($approvalReq->Request_Order_Flow ?? 0),
                            $approvalDetailStatus === 'T' ? $notes : null,
                        );
                    } catch (\Exception $e) {
                        Log::channel('ApprovalDetailService')->error('Exception updating approval decision', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'det_id' => $detId,
                            'no_transaksi' => $noTransaksi,
                            'approver' => $approvalReq->Approver_Karyawan_Kode ?? 'unknown',
                            'flow_step' => $approvalReq->Request_Order_Flow ?? 'unknown',
                        ]);
                        $detailUpdateErrorCount++;
                    }
                }

                // Log partial approval completion
                Log::channel('ApprovalDetailService')->info('Partial approval processed', [
                    'no_transaksi' => $noTransaksi,
                    'approver' => $kodeApprover,
                    'det_count' => count($detailApprovals),
                    'updated_det_count' => $updatedCount,
                    'detail_update_errors' => $detailUpdateErrorCount,
                    'has_next_approver' => $hasNextApprover,
                ]);

                // Auto-update Detail (Tier 2) flags based on Det results:
                // - If all Dets with Needs_Approval='Y' have Flag_Approval (no nulls), check outcome:
                //   - All 'Y' → Detail Flag_Approval='Y'
                //   - Any 'T' → Detail Flag_Approval='T'
                //   - Mix of Y/T → Detail Flag_Approval='T' (partial rejection)
                // - If still have NULL Dets → keep Detail Flag_Approval=NULL (waiting next approver)
                $this->updateDetailApprovalStatusSmart($noTransaksi);
            } else {
                // Global Action: Update ALL Det records that need approval
                // CRITICAL: Determine if approver is intermediate or final
                $hasNextApprover = $this->hasCurrentApproverInFlow($noTransaksi);

                // For APPROVE action:
                // - If there's a next approver: Flag_Approval = NULL (stays pending for next approver)
                // - If there's NO next approver: Flag_Approval = 'Y' (final approval)
                // For REJECT action: Flag_Approval = 'T' (always, regardless of flow)
                if ($action === 'APPROVE' && $hasNextApprover) {
                    // Intermediate approver: Keep NULL for next approver to validate
                    $detailStatus = null;
                } else {
                    // Final approver or reject: Set Y or T
                    $detailStatus = $action === 'APPROVE' ? 'Y' : 'T';
                }

                $pendingDets = DB::table('N_HRIS_Transaksi_Shift_Det as det')
                    ->join('N_HRIS_Transaksi_Shift_Detail as detail', 'det.Id_Detail', '=', 'detail.Id')
                    ->where('det.No_Transaksi', $noTransaksi)
                    ->where('det.Needs_Approval', 'Y')
                    ->whereNull('det.Flag_Approval')
                    ->whereNull('detail.Status')
                    ->whereNull('det.Status')
                    ->select('det.Id', 'det.Tanggal_Shift', 'detail.Kode_Karyawan')
                    ->get();

                if ($detailStatus !== null) {
                    // Update to 'Y' or 'T' only
                    DB::table('N_HRIS_Transaksi_Shift_Det')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Needs_Approval', 'Y')
                        ->whereNull('Status')
                        ->whereNull('Flag_Approval')
                        ->update(['Flag_Approval' => $detailStatus]);
                } else {
                    // For intermediate APPROVE, we still need to mark that this approver processed them
                    // Create approval detail records but don't change Flag_Approval yet
                }

                // ✅ UPDATE ALL APPROVAL DETAIL RECORDS (Generic Architecture)
                $detailUpdateCount = 0;
                $detailUpdateErrorCount = 0;

                if ($pendingDets->isNotEmpty()) {
                    foreach ($pendingDets as $det) {
                        try {
                            // Find corresponding approval detail record by Source_Row_Key
                            $sourceRowKey = $this->buildSourceRowKeyForShiftDet((int) $det->Id, $noTransaksi);

                            if (! $sourceRowKey) {
                                Log::channel('ApprovalDetailService')->warning(
                                    'Could not build Source_Row_Key for Shift Det',
                                    [
                                        'det_id' => $det->Id,
                                        'no_transaksi' => $noTransaksi,
                                    ],
                                );
                                $detailUpdateErrorCount++;

                                continue;
                            }

                            // Debug: Log info sebelum search
                            Log::channel('ApprovalDetailService')->debug(
                                'Searching approval detail record (global approval)',
                                [
                                    'id_approval_request' => $approvalReq->id,
                                    'approver' => $approvalReq->Approver_Karyawan_Kode,
                                    'flow_step' => $approvalReq->Request_Order_Flow,
                                    'source_row_key' => $sourceRowKey,
                                    'det_id' => $det->Id,
                                ],
                            );

                            $detailRecord = DB::table('HRIS_Approval_Request_Detail')
                                ->where('Id_Approval_Request', $approvalReq->id)
                                ->where('Source_Row_Key', $sourceRowKey)
                                ->first();

                            if (! $detailRecord) {
                                // Check if record exists with same Source_Row_Key but different Id_Approval_Request
                                $otherApproverRecord = DB::table('HRIS_Approval_Request_Detail')
                                    ->where('Source_Row_Key', $sourceRowKey)
                                    ->first();

                                Log::channel('ApprovalDetailService')->warning(
                                    'Approval detail record not found for this approver (global approval)',
                                    [
                                        'id_approval_request_searched' => $approvalReq->id,
                                        'approver' => $approvalReq->Approver_Karyawan_Kode,
                                        'flow_step' => $approvalReq->Request_Order_Flow,
                                        'source_row_key' => $sourceRowKey,
                                        'det_id' => $det->Id,
                                        'no_transaksi' => $noTransaksi,
                                        'found_record_for_other_approver' => $otherApproverRecord
                                            ? [
                                                'id' => $otherApproverRecord->Id,
                                                'id_approval_request' => $otherApproverRecord->Id_Approval_Request,
                                            ]
                                            : null,
                                    ],
                                );
                                $detailUpdateErrorCount++;

                                continue;
                            }

                            // For intermediate approve: Record as 'Y' in approval detail, but don't update Flag_Approval on Det
                            // This tracks that this approver approved it for audit trail
                            $approvalDetailStatus = $action === 'APPROVE' ? 'Y' : 'T';

                            Log::channel('ApprovalDetailService')->info(
                                'Updating approval detail record (global approval)',
                                [
                                    'id_approval_request_detail' => $detailRecord->Id,
                                    'id_approval_request' => $approvalReq->id,
                                    'approver' => $approvalReq->Approver_Karyawan_Kode,
                                    'flow_step' => $approvalReq->Request_Order_Flow,
                                    'source_row_key' => $sourceRowKey,
                                    'detail_status' => $approvalDetailStatus,
                                ],
                            );

                            $this->approvalDetailService->updateApprovalDecision(
                                $detailRecord->Id,
                                $approvalDetailStatus,
                                $kodeApprover,
                                (int) ($approvalReq->Request_Order_Flow ?? 0),
                                $approvalDetailStatus === 'T' ? $notes : null,
                            );

                            $detailUpdateCount++;
                        } catch (\Exception $e) {
                            Log::channel('ApprovalDetailService')->error(
                                'Exception updating approval decision (global approval)',
                                [
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                    'det_id' => $det->Id,
                                    'no_transaksi' => $noTransaksi,
                                    'approver' => $approvalReq->Approver_Karyawan_Kode ?? 'unknown',
                                    'flow_step' => $approvalReq->Request_Order_Flow ?? 'unknown',
                                ],
                            );
                            $detailUpdateErrorCount++;
                        }
                    }
                }

                // Log global approval completion
                Log::channel('ApprovalDetailService')->info('Global approval processed', [
                    'no_transaksi' => $noTransaksi,
                    'approver' => $kodeApprover,
                    'approver_id' => $approvalReq->Approver_Karyawan_Kode,
                    'approval_request_id' => $approvalReq->id,
                    'flow_step' => $approvalReq->Request_Order_Flow,
                    'action' => $action,
                    'det_count' => $pendingDets->count(),
                    'detail_update_count' => $detailUpdateCount,
                    'detail_update_errors' => $detailUpdateErrorCount,
                    'has_next_approver' => $hasNextApprover,
                    'flag_approval_set' => $detailStatus,
                ]);

                // Update Details accordingly
                $this->updateDetailApprovalStatusSmart($noTransaksi);
            }

            // 3. Update Approval Request Status
            if (! $approvalAlreadyProcessed) {
                $statusApproval = $action === 'APPROVE' ? 'Y' : 'T';

                Log::channel('ApprovalDetailService')->info('Updating approval request', [
                    'id_approval_request' => $approvalReq->id,
                    'approver' => $approvalReq->Approver_Karyawan_Kode,
                    'flow_step' => $approvalReq->Request_Order_Flow,
                    'no_transaksi' => $noTransaksi,
                    'flag_approval' => $statusApproval,
                    'approved_by' => $kodeApprover,
                ]);

                DB::table('HRIS_Approval_Request')
                    ->where('id', $approvalReq->id)
                    ->update([
                        'Flag_Approval' => $statusApproval,
                        'Approved_By_Kode' => $kodeApprover,
                        'Catatan' => $notes,
                        'update_at' => now(),
                        'Tanggal_Approval' => now(),
                    ]);

                Log::channel('ApprovalDetailService')->info('Approval request updated successfully', [
                    'id_approval_request' => $approvalReq->id,
                    'approver' => $approvalReq->Approver_Karyawan_Kode,
                    'flow_step' => $approvalReq->Request_Order_Flow,
                ]);
            }

            // 4. Check if ALL Dets in transaction are now rejected (full rejection)
            $anyPendingOrApproved = DB::table('N_HRIS_Transaksi_Shift_Det')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Needs_Approval', 'Y')
                ->whereNull('Status')
                ->where(function ($q) {
                    $q->whereNull('Flag_Approval') // still pending
                        ->orWhere('Flag_Approval', 'Y'); // approved
                })
                ->exists();

            if (! $anyPendingOrApproved) {
                // All Dets that need approval are rejected → Stop flow immediately
                DB::table('N_HRIS_Transaksi_Shift')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update([
                        'Updated_At' => now(),
                        'Updated_By' => Auth::id(),
                        'Flag_Selesai' => 'Y',
                    ]);

                // Get tanggal for WA notification
                $getTanggal = DB::table('N_HRIS_Transaksi_Shift_Det')
                    ->where('No_Transaksi', $noTransaksi)
                    ->whereNull('Status')
                    ->where('Needs_Approval', 'Y')
                    ->pluck('Tanggal_Shift')
                    ->toArray();
                $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($getTanggal);
                $hpRequester = $this->getKaryawanHp($approvalReq->Kode_Karyawan);
                $namaRequester = $this->getKaryawanName($approvalReq->Kode_Karyawan);

                try {
                    $pesan = $this->whatsappMessageBuilder->requesterShiftNotification(
                        $hpRequester,
                        $noTransaksi,
                        $tanggalFormated,
                    );
                    $this->whatsappLogHelper->sendAndLog(
                        $approvalReq->Kode_Karyawan,
                        $namaRequester,
                        $hpRequester,
                        $noTransaksi,
                        $pesan,
                    );
                } catch (\Throwable $e) {
                    Log::error('WA Notification Failed (Full Reject): '.$e->getMessage());
                }

                return ['status' => 'REJECTED'];
            }

            // 5. Check if next approver exists in flow
            $hasNextApprover = $this->hasCurrentApproverInFlow($noTransaksi);

            if ($hasNextApprover) {
                // Still have pending approver in chain
                // If current approver is Is_Mandatory='T' (optional), auto-approve remaining NULL Dets
                if ($isMandatory === 'T') {
                    // Auto-approve all remaining Dets that are still NULL
                    DB::table('N_HRIS_Transaksi_Shift_Det')
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Status')
                        ->where('Needs_Approval', 'Y')
                        ->whereNull('Flag_Approval')
                        ->update(['Flag_Approval' => 'Y']);

                    // Recalc Detail flags after auto-approve
                    $this->updateDetailApprovalStatusSmart($noTransaksi);

                    // Send WA notification to optional approver (audit trail)
                    $getTanggal = DB::table('N_HRIS_Transaksi_Shift_Det')
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Status')
                        ->where('Needs_Approval', 'Y')
                        ->pluck('Tanggal_Shift')
                        ->toArray();
                    $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($getTanggal);
                    $hpRequester = $this->getKaryawanHp($approvalReq->Kode_Karyawan);
                    $namaRequester = $this->getKaryawanName($approvalReq->Kode_Karyawan);

                    try {
                        $pesan = $this->whatsappMessageBuilder->requesterShiftNotification(
                            $hpRequester,
                            $noTransaksi,
                            $tanggalFormated,
                        );
                        $this->whatsappLogHelper->sendAndLog(
                            $approvalReq->Kode_Karyawan,
                            $namaRequester,
                            $hpRequester,
                            $noTransaksi,
                            $pesan,
                        );
                    } catch (\Throwable $e) {
                        Log::error('WA Notification Failed (Optional Auto-Approve): '.$e->getMessage());
                    }
                }

                // 🔔 SEND NOTIFICATION TO NEXT APPROVER
                try {
                    // Get next approver in flow
                    $nextApproverReq = DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Request_Order_Flow', $approvalReq->Request_Order_Flow + 1)
                        ->first();

                    if ($nextApproverReq) {
                        // Get next approver info
                        $hpNextApprover = $this->getKaryawanHp($nextApproverReq->Approver_Karyawan_Kode);
                        $namaNextApprover = $this->getKaryawanName($nextApproverReq->Approver_Karyawan_Kode);

                        // Get dates that are still NULL (waiting for next approver validation)
                        $tanggalDetsForNextApprover = DB::table('N_HRIS_Transaksi_Shift_Det')
                            ->where('No_Transaksi', $noTransaksi)
                            ->whereNull('Status')
                            ->where('Needs_Approval', 'Y')
                            ->whereNull('Flag_Approval') // Only NULL dates go to next approver
                            ->pluck('Tanggal_Shift')
                            ->toArray();

                        if (! empty($tanggalDetsForNextApprover)) {
                            $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($tanggalDetsForNextApprover);
                            $namaRequester = $this->getKaryawanName($approvalReq->Kode_Karyawan);

                            // Send approver notification (same as first approver)
                            $pesan = $this->whatsappMessageBuilder->approverShiftNotification(
                                $hpNextApprover,
                                $noTransaksi,
                                $namaRequester,
                                $tanggalFormated,
                            );
                            $this->whatsappLogHelper->sendAndLog(
                                $nextApproverReq->Approver_Karyawan_Kode,
                                $namaNextApprover,
                                $hpNextApprover,
                                $noTransaksi,
                                $pesan,
                            );

                            // Update angka_wa counter for next approver
                            DB::table('HRIS_Approval_Request')
                                ->where('id', $nextApproverReq->id)
                                ->update([
                                    'angka_wa' => DB::raw('ISNULL(angka_wa, 0) + 1'),
                                ]);

                            Log::channel('ApprovalDetailService')->info(
                                'Next approver notification sent and angka_wa updated',
                                [
                                    'current_approver' => $approvalReq->Approver_Karyawan_Kode,
                                    'next_approver' => $nextApproverReq->Approver_Karyawan_Kode,
                                    'next_approver_request_id' => $nextApproverReq->id,
                                    'next_flow_step' => $nextApproverReq->Request_Order_Flow,
                                    'no_transaksi' => $noTransaksi,
                                    'dates_for_next' => $tanggalFormated,
                                ],
                            );
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to send next approver notification: '.$e->getMessage(), [
                        'no_transaksi' => $noTransaksi,
                        'current_flow_step' => $approvalReq->Request_Order_Flow,
                    ]);
                }

                // Wait for next approver (they will only see Needs_Approval='Y' AND Flag_Approval IS NULL Dets)
                return ['status' => 'WAITING_NEXT'];
            }

            // 6. No next approver → Final approval complete
            // Apply shifts for all approved Dets
            $this->applyShiftAfterApproval($noTransaksi);

            // Mark header as complete
            DB::table('N_HRIS_Transaksi_Shift')
                ->where('No_Transaksi', $noTransaksi)
                ->update([
                    'Updated_At' => now(),
                    'Updated_By' => Auth::id(),
                    'Flag_Selesai' => 'Y',
                ]);

            // Get tanggal for final WA notification
            $getTanggal = DB::table('N_HRIS_Transaksi_Shift_Det')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNull('Status')
                ->where('Needs_Approval', 'Y')
                ->where('Flag_Approval', 'Y') // only approved dates
                ->pluck('Tanggal_Shift')
                ->toArray();
            $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($getTanggal);
            $hpRequester = $this->getKaryawanHp($approvalReq->Kode_Karyawan);
            $namaRequester = $this->getKaryawanName($approvalReq->Kode_Karyawan);

            $tipeTransaksi = DB::table('N_HRIS_Transaksi_Shift')
                ->where('No_Transaksi', $noTransaksi)
                ->select('Jenis_Transaksi', 'Flag_Admin')
                ->first();

            // dd($tipeTransaksi);
            if (
                $tipeTransaksi &&
                $tipeTransaksi->Jenis_Transaksi == 'LEADER_SHIFT' &&
                $tipeTransaksi->Flag_Admin == 'T'
            ) {
                $getKaryawanDetail = DB::table('N_HRIS_Transaksi_Shift_Detail as dt')
                    ->join('Karyawan as k', 'dt.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                    ->where('dt.No_Transaksi', $noTransaksi)
                    ->where('dt.Flag_Approval', 'Y')
                    ->whereNull('dt.Status')
                    ->where('k.Aktif', 'Y')
                    ->select('k.Nama', 'k.HP', 'k.Kode_Karyawan', 'dt.Id', 'dt.Target_Table')
                    ->distinct()
                    ->get();
                // dd($getKaryawanDetail->pluck('Tanggal_Shift')->toArray());
                // dd($getKaryawanDetail);
                /*
                 * Tanggal yang disetujui diambil SEKALI untuk seluruh karyawan,
                 * lalu dikelompokkan per Id_Detail. Sebelumnya satu kueri per
                 * karyawan, tepat sebelum tiap pengiriman WhatsApp.
                 */
                $tanggalPerDetail = DB::table('N_HRIS_Transaksi_Shift_Det as det')
                    ->join('HRIS_Shift_Kerja as s', 'det.Id_Shift_Diminta', '=', 's.ID_Shift')
                    ->where('No_Transaksi', $noTransaksi)
                    ->whereIn('Id_Detail', $getKaryawanDetail->pluck('Id')->all())
                    ->where('Needs_Approval', 'Y')
                    ->where('Flag_Applied', 'Y')
                    ->whereNull('det.Status')
                    ->where('Flag_Approval', 'Y') // only approved dates
                    ->select('det.Id_Detail', 'Tanggal_Shift', 's.Nama')
                    ->get()
                    ->groupBy('Id_Detail');

                foreach ($getKaryawanDetail as $emp) {
                    $getTanggal = $tanggalPerDetail[$emp->Id] ?? collect();

                    // dd($getTanggal->pluck('Tanggal_Shift')->toArray());

                    $namaEmployeeTarget = $emp->Nama;
                    $HPEmployeeTarget = $emp->HP;
                    $tanggal = FormatTanggalHelper::formatTanggalMultiple(
                        $getTanggal->pluck('Tanggal_Shift')->toArray(),
                    );
                    $tanggalFormatted =
                        $emp->Target_Table == 'PERMANEN'
                            ? FormatTanggalHelper::formatTanggalMultiple(
                                $getTanggal->pluck('Tanggal_Shift')->toArray(),
                            ).' s/d Seterusnya'
                            : FormatTanggalHelper::formatTanggalMultiple(
                                $getTanggal->pluck('Tanggal_Shift')->toArray(),
                            );
                    $shift = $getTanggal->pluck('Nama')->unique()->implode(', ');
                    // Send employee notification for each affected employee
                    try {
                        $pesan = $this->whatsappMessageBuilder->Notif_Karyawan_Perubahan_Shift(
                            $HPEmployeeTarget,
                            $namaEmployeeTarget,
                            $emp->Target_Table,
                            $shift,
                            $namaRequester,
                            $tanggalFormatted,
                        );
                        // dd($pesan);
                        $this->whatsappLogHelper->sendAndLog(
                            $emp->Kode_Karyawan,
                            $namaEmployeeTarget,
                            $HPEmployeeTarget,
                            $noTransaksi,
                            $pesan,
                        );
                    } catch (\Throwable $e) {
                        Log::error('WA Notification Failed (Final Approve - Employee): '.$e->getMessage(), [
                            'kode_karyawan' => $emp->Kode_Karyawan,
                            'nama_karyawan' => $namaEmployeeTarget,
                            'hp_karyawan' => $HPEmployeeTarget,
                        ]);
                    }
                }
            }

            // Send Leader Or RequesterNotification
            try {
                // $pesan = $this->whatsappMessageBuilder->requesterShiftNotification($hpRequester, $noTransaksi, $tanggalFormated);
                $pesan = $this->whatsappMessageBuilder->Notif_Selesai_Transaksi(
                    $hpRequester,
                    $namaRequester,
                    'Perubahan Shift Tim',
                    $noTransaksi,
                    $tanggalFormated,
                );
                $this->whatsappLogHelper->sendAndLog(
                    $approvalReq->Kode_Karyawan,
                    $namaRequester,
                    $hpRequester,
                    $noTransaksi,
                    $pesan,
                );
            } catch (\Throwable $e) {
                Log::error('WA Notification Failed (Final Approve): '.$e->getMessage());
            }

            return ['status' => 'COMPLETED'];
        });
    }

    private function processKonversiLemburApproval(string $noTransaksi, string $action): array
    {
        if (strtoupper($action) === 'APPROVE' && $this->hasCurrentApproverInFlow($noTransaksi)) {
            return ['status' => 'WAITING_NEXT'];
        }

        return [
            'status' => strtoupper($action) === 'APPROVE' ? 'APPROVED' : 'REJECTED',
        ];
    }

    /**
     * Process approval for status hari transaction (3-tier)
     */
    public function processStatusHariApproval(
        string $kodeApprover,
        string $noTransaksi,
        string $action,
        array $detailApprovals = [],
        ?string $notes = null,
        bool $approvalAlreadyProcessed = false,
    ): array {
        return DB::transaction(function () use (
            $kodeApprover,
            $noTransaksi,
            $action,
            $detailApprovals,
            $notes,
            $approvalAlreadyProcessed,
        ) {
            // dd($kodeApprover, $noTransaksi, $action, $detailApprovals, $notes, $approvalAlreadyProcessed);
            $tipeTransaksi = 'Perubahan Status Hari Tim';
            // 1. Validate Approval Request
            if ($approvalAlreadyProcessed) {
                $statusApproval = $action === 'APPROVE' ? 'Y' : 'T';
                $approvalReq = DB::table('HRIS_Approval_Request')
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Approved_By_Kode', $kodeApprover)
                    ->where('Flag_Approval', $statusApproval)
                    ->whereNotNull('Tanggal_Approval')
                    ->orderBy('Tanggal_Approval', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();
            } else {
                $approvalReq = DB::table('HRIS_Approval_Request')
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Approver_Karyawan_Kode', $kodeApprover)
                    ->whereNull('Flag_Approval')
                    ->first();
            }

            if (! $approvalReq) {
                throw new \Exception('Approval request not found or already processed.');
            }

            if (! empty($detailApprovals)) {
                // Check if there's a next approver
                $hasNextApprover = $this->hasCurrentApproverInFlow($noTransaksi);

                $updatedCount = 0;
                $detailUpdateErrorCount = 0;

                foreach ($detailApprovals as $detId => $approvalAction) {
                    // 'Y' or 'T'
                    // For APPROVE action:
                    // - If there's a next approver: Flag_Approval = NULL (stays pending for next approver)
                    // - If there's NO next approver: Flag_Approval = 'Y' (final approval)
                    // For REJECT action: Flag_Approval = 'T' (always, regardless of flow)
                    if ($approvalAction === 'Y' && $hasNextApprover) {
                        // Intermediate approver: Keep NULL for next approver to validate
                        $detStatus = null;
                    } else {
                        // Final approver or reject: Set Y or T
                        $detStatus = $approvalAction;
                    }

                    if ($detStatus !== null) {
                        DB::table('N_HRIS_Transaksi_Status_Hari_Det')
                            ->whereNull('Status')
                            ->where('No_Transaksi', $noTransaksi)
                            ->where('Id', $detId)
                            ->update(['Flag_Approval' => $detStatus]);
                        $updatedCount++;
                    }

                    // ✅ UPDATE APPROVAL DETAIL RECORD (Generic Architecture)
                    try {
                        $sourceRowKey = $this->buildSourceRowKeyForStatusHariDet($detId, $noTransaksi);

                        if (! $sourceRowKey) {
                            Log::channel('ApprovalDetailService')->warning(
                                'Could not build Source_Row_Key for Status Hari Det',
                                [
                                    'det_id' => $detId,
                                    'no_transaksi' => $noTransaksi,
                                ],
                            );
                            $detailUpdateErrorCount++;

                            continue;
                        }

                        $detailRecord = DB::table('HRIS_Approval_Request_Detail')
                            ->where('Id_Approval_Request', $approvalReq->id)
                            ->where('Source_Row_Key', $sourceRowKey)
                            ->first();

                        if (! $detailRecord) {
                            Log::channel('ApprovalDetailService')->warning(
                                'Approval detail record not found for update',
                                [
                                    'id_approval_request' => $approvalReq->id,
                                    'source_row_key' => $sourceRowKey,
                                    'det_id' => $detId,
                                    'no_transaksi' => $noTransaksi,
                                ],
                            );
                            $detailUpdateErrorCount++;

                            continue;
                        }

                        // For intermediate approve: Record as 'Y' in approval detail
                        $approvalDetailStatus = $approvalAction === 'Y' ? 'Y' : 'T';

                        $this->approvalDetailService->updateApprovalDecision(
                            $detailRecord->Id,
                            $approvalDetailStatus,
                            $kodeApprover,
                            (int) ($approvalReq->Request_Order_Flow ?? 0),
                            $approvalDetailStatus === 'T' ? $notes : null,
                        );
                    } catch (\Exception $e) {
                        Log::channel('ApprovalDetailService')->error('Error updating status hari approval decision', [
                            'error' => $e->getMessage(),
                            'det_id' => $detId,
                            'no_transaksi' => $noTransaksi,
                        ]);
                        $detailUpdateErrorCount++;
                    }
                }

                // Log partial approval completion
                Log::channel('ApprovalDetailService')->info('Partial status hari approval processed', [
                    'no_transaksi' => $noTransaksi,
                    'approver' => $kodeApprover,
                    'det_count' => count($detailApprovals),
                    'updated_det_count' => $updatedCount,
                    'detail_update_errors' => $detailUpdateErrorCount,
                    'has_next_approver' => $hasNextApprover,
                ]);

                $detailIds = DB::table('N_HRIS_Transaksi_Status_Hari_Det')
                    ->where('No_Transaksi', $noTransaksi)
                    ->select('Id_Detail')
                    ->distinct()
                    ->pluck('Id_Detail');

                foreach ($detailIds as $detailId) {
                    $this->syncStatusHariDetailApproval($detailId);
                }
            } else {
                // Global Action: Update ALL Det records that need approval
                // CRITICAL: Determine if approver is intermediate or final
                $hasNextApprover = $this->hasCurrentApproverInFlow($noTransaksi);

                // For APPROVE action:
                // - If there's a next approver: Flag_Approval = NULL (stays pending for next approver)
                // - If there's NO next approver: Flag_Approval = 'Y' (final approval)
                // For REJECT action: Flag_Approval = 'T' (always, regardless of flow)
                if ($action === 'APPROVE' && $hasNextApprover) {
                    // Intermediate approver: Keep NULL for next approver to validate
                    $detailStatus = null;
                } else {
                    // Final approver or reject: Set Y or T
                    $detailStatus = $action === 'APPROVE' ? 'Y' : 'T';
                }

                $pendingDets = DB::table('N_HRIS_Transaksi_Status_Hari_Det as det')
                    ->join('N_HRIS_Transaksi_Status_Hari_Detail as detail', 'det.Id_Detail', '=', 'detail.Id')
                    ->where('det.No_Transaksi', $noTransaksi)
                    ->where('det.Needs_Approval', 'Y')
                    ->whereNull('det.Status')
                    ->whereNull('detail.Status')
                    ->whereNull('det.Flag_Approval')
                    ->select('det.Id', 'det.Tanggal', 'detail.Kode_Karyawan')
                    ->get();

                if ($detailStatus !== null) {
                    // Update to 'Y' or 'T' only
                    DB::table('N_HRIS_Transaksi_Status_Hari_Det')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Needs_Approval', 'Y')
                        ->whereNull('Status')
                        ->whereNull('Flag_Approval')
                        ->update(['Flag_Approval' => $detailStatus]);
                }

                $detailUpdateCount = 0;
                $detailUpdateErrorCount = 0;

                if ($pendingDets->isNotEmpty()) {
                    foreach ($pendingDets as $det) {
                        try {
                            $sourceRowKey = $this->buildSourceRowKeyForStatusHariDet((int) $det->Id, $noTransaksi);

                            if (! $sourceRowKey) {
                                Log::channel('ApprovalDetailService')->warning(
                                    'Could not build Source_Row_Key for Status Hari Det',
                                    [
                                        'det_id' => $det->Id,
                                        'no_transaksi' => $noTransaksi,
                                    ],
                                );
                                $detailUpdateErrorCount++;

                                continue;
                            }

                            $detailRecord = DB::table('HRIS_Approval_Request_Detail')
                                ->where('Id_Approval_Request', $approvalReq->id)
                                ->where('Source_Row_Key', $sourceRowKey)
                                ->first();

                            if (! $detailRecord) {
                                Log::channel('ApprovalDetailService')->warning(
                                    'Approval detail record not found for update',
                                    [
                                        'id_approval_request' => $approvalReq->id,
                                        'source_row_key' => $sourceRowKey,
                                        'det_id' => $det->Id,
                                        'no_transaksi' => $noTransaksi,
                                    ],
                                );
                                $detailUpdateErrorCount++;

                                continue;
                            }

                            // For intermediate approve: Record as 'Y' in approval detail
                            $approvalDetailStatus = $action === 'APPROVE' ? 'Y' : 'T';

                            $this->approvalDetailService->updateApprovalDecision(
                                $detailRecord->Id,
                                $approvalDetailStatus,
                                $kodeApprover,
                                (int) ($approvalReq->Request_Order_Flow ?? 0),
                                $approvalDetailStatus === 'T' ? $notes : null,
                            );

                            $detailUpdateCount++;
                        } catch (\Exception $e) {
                            Log::channel('ApprovalDetailService')->error(
                                'Error updating status hari approval decision',
                                [
                                    'error' => $e->getMessage(),
                                    'det_id' => $det->Id,
                                    'no_transaksi' => $noTransaksi,
                                ],
                            );
                            $detailUpdateErrorCount++;
                        }
                    }
                }

                // Log global approval completion
                Log::channel('ApprovalDetailService')->info('Global status hari approval processed', [
                    'no_transaksi' => $noTransaksi,
                    'approver' => $kodeApprover,
                    'action' => $action,
                    'det_count' => $pendingDets->count(),
                    'detail_update_count' => $detailUpdateCount,
                    'detail_update_errors' => $detailUpdateErrorCount,
                    'has_next_approver' => $hasNextApprover,
                    'flag_approval_set' => $detailStatus,
                ]);

                if ($detailStatus !== null) {
                    DB::table('N_HRIS_Transaksi_Status_Hari_Detail')
                        ->where('No_Transaksi', $noTransaksi)
                        ->update(['Flag_Approval' => $detailStatus]);
                }
            }

            $hpRequester = $this->getKaryawanHp($approvalReq->Kode_Karyawan);
            $namaRequester = $this->getKaryawanName($approvalReq->Kode_Karyawan);
            $tanggalDets = DB::table('N_HRIS_Transaksi_Status_Hari_Det')
                ->where('No_Transaksi', $noTransaksi)
                ->pluck('Tanggal')
                ->toArray();
            $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($tanggalDets);

            if (! $approvalAlreadyProcessed) {
                $statusApproval = $action === 'APPROVE' ? 'Y' : 'T';
                DB::table('HRIS_Approval_Request')
                    ->where('id', $approvalReq->id)
                    ->update([
                        'Flag_Approval' => $statusApproval,
                        'Approved_By_Kode' => $kodeApprover,
                        'Catatan' => $notes,
                        'update_at' => now(),
                        'Tanggal_Approval' => now(),
                    ]);
            }

            if ($action === 'REJECT') {
                if ($this->hasCurrentApproverInFlow($noTransaksi)) {
                    return ['status' => 'WAITING_NEXT'];
                }

                DB::table('N_HRIS_Transaksi_Status_Hari')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update([
                        'Flag_Selesai' => 'Y',
                        'Updated_At' => now(),
                        'Updated_By' => Auth::id(),
                    ]);

                try {
                    $pesan = $this->whatsappMessageBuilder->Notif_Selesai_Transaksi(
                        $hpRequester,
                        $namaRequester,
                        $tipeTransaksi,
                        $noTransaksi,
                        $tanggalFormated,
                    );
                    $this->whatsappLogHelper->sendAndLog(
                        $approvalReq->Kode_Karyawan,
                        $namaRequester,
                        $hpRequester,
                        $noTransaksi,
                        $pesan,
                    );
                } catch (\Throwable $e) {
                    Log::error('WA Notification Failed (Status Hari Reject): '.$e->getMessage());
                }

                return ['status' => 'REJECTED'];
            }

            if ($this->hasCurrentApproverInFlow($noTransaksi)) {
                // 🔔 SEND NOTIFICATION TO NEXT APPROVER (Status Hari)
                try {
                    // Get next approver in flow
                    $nextApproverReq = DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Request_Order_Flow', $approvalReq->Request_Order_Flow + 1)
                        ->first();

                    if ($nextApproverReq) {
                        // Get next approver info
                        $hpNextApprover = $this->getKaryawanHp($nextApproverReq->Approver_Karyawan_Kode);
                        $namaNextApprover = $this->getKaryawanName($nextApproverReq->Approver_Karyawan_Kode);

                        // Get dates that are still NULL (waiting for next approver validation)
                        $tanggalDetsForNextApprover = DB::table('N_HRIS_Transaksi_Status_Hari_Det')
                            ->where('No_Transaksi', $noTransaksi)
                            ->where('Needs_Approval', 'Y')
                            ->whereNull('Status')
                            ->whereNull('Flag_Approval') // Only NULL dates go to next approver
                            ->pluck('Tanggal')
                            ->toArray();

                        if (! empty($tanggalDetsForNextApprover)) {
                            $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($tanggalDetsForNextApprover);
                            $namaRequester = $this->getKaryawanName($approvalReq->Kode_Karyawan);

                            // Send approver notification (same pattern as shift)
                            // Note: Using approverShiftNotification for now (generic message)
                            $pesan = $this->whatsappMessageBuilder->approverShiftNotification(
                                $hpNextApprover,
                                $noTransaksi,
                                $namaRequester,
                                $tanggalFormated,
                            );
                            $this->whatsappLogHelper->sendAndLog(
                                $nextApproverReq->Approver_Karyawan_Kode,
                                $namaNextApprover,
                                $hpNextApprover,
                                $noTransaksi,
                                $pesan,
                            );

                            // Update angka_wa counter for next approver
                            DB::table('HRIS_Approval_Request')
                                ->where('id', $nextApproverReq->id)
                                ->update([
                                    'angka_wa' => DB::raw('ISNULL(angka_wa, 0) + 1'),
                                ]);

                            Log::channel('ApprovalDetailService')->info(
                                'Next approver notification sent and angka_wa updated (Status Hari)',
                                [
                                    'current_approver' => $approvalReq->Approver_Karyawan_Kode,
                                    'next_approver' => $nextApproverReq->Approver_Karyawan_Kode,
                                    'next_approver_request_id' => $nextApproverReq->id,
                                    'next_flow_step' => $nextApproverReq->Request_Order_Flow,
                                    'no_transaksi' => $noTransaksi,
                                    'dates_for_next' => $tanggalFormated,
                                ],
                            );
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to send next approver notification (Status Hari): '.$e->getMessage(), [
                        'no_transaksi' => $noTransaksi,
                        'current_flow_step' => $approvalReq->Request_Order_Flow,
                    ]);
                }

                return ['status' => 'WAITING_NEXT'];
            }

            $approvedDets = DB::table('N_HRIS_Transaksi_Status_Hari_Det as det')
                ->join('N_HRIS_Transaksi_Status_Hari_Detail as d', 'det.Id_Detail', '=', 'd.Id')
                ->where('det.No_Transaksi', $noTransaksi)
                ->where('det.Flag_Approval', 'Y')
                ->whereNull('det.Status')
                ->whereNull('d.Status')
                ->where(function ($q) {
                    $q->whereNull('det.Flag_Applied')->orWhere('det.Flag_Applied', '!=', 'Y');
                })
                ->select('det.Tanggal', 'det.Status_Hari', 'd.Kode_Karyawan', 'd.Alasan', 'd.Target_Table')
                ->get();

            foreach ($approvedDets as $det) {
                // dd(Carbon::parse($det->Tanggal)->dayOfWeek);
                $hariHris = $det->Target_Table == 'PERMANEN' ? $this->mapDayToHris(Carbon::parse($det->Tanggal)) : null;
                $this->applyStatusHariDirectForExistingTransaction(
                    $noTransaksi,
                    $det->Kode_Karyawan,
                    $det->Tanggal,
                    $det->Status_Hari,
                    $hariHris,
                    $det->Alasan,
                    $det->Target_Table,
                );
            }

            $grouped = [];
            foreach ($approvedDets as $det) {
                $grouped[$det->Kode_Karyawan]['nama'] = $this->getKaryawanName($det->Kode_Karyawan);
                $grouped[$det->Kode_Karyawan]['hp'] = $this->getKaryawanHp($det->Kode_Karyawan);
                $grouped[$det->Kode_Karyawan]['tanggal'][] = $det->Tanggal;
                $grouped[$det->Kode_Karyawan]['status_hari'][] = $det->Status_Hari;
                $grouped[$det->Kode_Karyawan]['target_table'][] = $det->Target_Table;
            }

            foreach ($grouped as $kodeKaryawan => $data) {
                // $tanggalFormatted = FormatTanggalHelper::formatTanggalMultiple($data['tanggal']);
                $statusHari = implode(', ', array_unique($data['status_hari']));
                // dd($kodeKaryawan, $data['nama'], $noTransaksi,$data['nama'], $data['hp'], $data['tanggal']);
                // dd($data['target_table']);
                $this->notifEmployeeForStatusHari(
                    $kodeKaryawan,
                    $noTransaksi,
                    $namaRequester,
                    $data['nama'],
                    $data['hp'],
                    $data['tanggal'],
                    'OFF',
                    $data['target_table'][0],
                );
            }

            DB::table('N_HRIS_Transaksi_Status_Hari')
                ->where('No_Transaksi', $noTransaksi)
                ->update([
                    'Flag_Selesai' => 'Y',
                    'Updated_At' => now(),
                    'Updated_By' => Auth::id(),
                ]);

            $tipeTransaksiTarget = DB::table('N_HRIS_Transaksi_Status_Hari')
                ->where('No_Transaksi', $noTransaksi)
                ->select('Jenis_Transaksi', 'Flag_Admin')
                ->first();
            // dd($tipeTransaksiTarget);

            // if($tipeTransaksiTarget && $tipeTransaksiTarget->Jenis_Transaksi == 'STATUS_HARI' && $tipeTransaksiTarget->Flag_Admin == 'T') {
            //     $getKaryawanDetail = DB::table('N_HRIS_Transaksi_Status_Hari_Detail as dt')
            //         ->join('Karyawan as k', 'dt.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            //         ->where('dt.No_Transaksi', $noTransaksi)
            //         ->where('dt.Flag_Approval', 'Y')
            //         ->whereNull('dt.Status')
            //         ->where('k.Aktif', 'Y')
            //         ->select('k.Nama', 'k.HP', 'k.Kode_Karyawan', 'dt.Id')
            //         ->distinct()
            //         ->get();
            //     // dd($getKaryawanDetail->pluck('Tanggal_Shift')->toArray());
            //     // dd($getKaryawanDetail);
            //     foreach($getKaryawanDetail as $emp) {
            //         $getTanggal = DB::table('N_HRIS_Transaksi_Status_Hari_Det as det')
            //         ->where('No_Transaksi', $noTransaksi)
            //         ->where('Id_Detail', $emp->Id)
            //         ->where('Needs_Approval', 'Y')
            //         ->where('Flag_Applied', 'Y')
            //         ->where('Flag_Approval', 'Y') // only approved dates
            //         ->select('Tanggal', 'Status_Hari')->get();

            //         // dd($getTanggal->pluck('Tanggal_Shift')->toArray());
            //         // dd($getTanggal);
            //         $namaEmployeeTarget = $emp->Nama;
            //         $HPEmployeeTarget = $emp->HP;
            //         $tanggal = FormatTanggalHelper::formatTanggalMultiple($getTanggal->pluck('Tanggal')->toArray());
            //         $tanggalFormatted = $tanggal;
            //         $shift = $getTanggal->pluck('Status_Hari')->unique()->implode(', ');
            //         // Send employee notification for each affected employee
            //         try {
            //             $pesan = $this->whatsappMessageBuilder->Notif_Karyawan_Perubahan_Shift($HPEmployeeTarget, $namaEmployeeTarget, 'Sementara', $shift, $namaRequester, $tanggalFormatted);
            //             // dd($pesan);
            //             $this->whatsappLogHelper->sendAndLog($emp->Kode_Karyawan, $namaEmployeeTarget, $HPEmployeeTarget, $noTransaksi, $pesan);
            //         } catch (\Throwable $e) {
            //             Log::error("WA Notification Failed (Final Approve - Employee): " . $e->getMessage(), [
            //                 'kode_karyawan' => $emp->Kode_Karyawan,
            //                 'nama_karyawan' => $namaEmployeeTarget,
            //                 'hp_karyawan' => $HPEmployeeTarget
            //             ]);
            //         }
            //     }
            // }

            try {
                // dd($hpRequester, $namaRequester, $tipeTransaksi, $noTransaksi, $tanggalFormated);
                $pesan = $this->whatsappMessageBuilder->Notif_Selesai_Transaksi(
                    $hpRequester,
                    $namaRequester,
                    $tipeTransaksi,
                    $noTransaksi,
                    $tanggalFormated,
                );
                // dd($pesan);
                $this->whatsappLogHelper->sendAndLog(
                    $approvalReq->Kode_Karyawan,
                    $namaRequester,
                    $hpRequester,
                    $noTransaksi,
                    $pesan,
                );
            } catch (\Throwable $e) {
                Log::error('WA Notification Failed (Status Hari Accept): '.$e->getMessage());
            }

            return ['status' => 'COMPLETED'];
        });
    }

    /**
     * Process approval for tukar hari (swap day) transaction
     * Sets Flag_Approval = Y and applies working date shift immediately
     */
    public function processTukarHariApproval(
        string $kodeApprover,
        string $noTransaksi,
        string $action,
        ?string $notes = null,
        bool $approvalAlreadyProcessed = false,
    ): array {
        // dd($kodeApprover, $noTransaksi, $action, $notes);
        return DB::transaction(function () use (
            $kodeApprover,
            $noTransaksi,
            $action,
            $notes,
            $approvalAlreadyProcessed,
        ) {
            $tipeTransaksi = 'Tukar Hari';
            // 1. Validate Approval Request
            if ($approvalAlreadyProcessed) {
                $statusApproval = $action === 'APPROVE' ? 'Y' : 'T';
                $approvalReq = DB::table('HRIS_Approval_Request')
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Approved_By_Kode', $kodeApprover)
                    ->where('Status', null)
                    ->where('Flag_Approval', $statusApproval)
                    ->whereNotNull('Tanggal_Approval')
                    ->orderBy('Tanggal_Approval', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();
            } else {
                $approvalReq = DB::table('HRIS_Approval_Request')
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Approver_Karyawan_Kode', $kodeApprover)
                    ->whereNull('Status')
                    ->whereNull('Flag_Approval')
                    ->first();
            }

            if (! $approvalReq) {
                throw new \Exception('Approval request not found or already processed.');
            }

            // 2. Get tukar hari header and details
            $header = DB::table('N_HRIS_Transaksi_Tukar_Hari')->where('No_Transaksi', $noTransaksi)->first();

            if (! $header) {
                throw new \Exception('Tukar hari transaction not found.');
            }

            // Get requester info
            $hpRequester = $this->getKaryawanHp($approvalReq->Kode_Karyawan);
            $namaRequester = $this->getKaryawanName($approvalReq->Kode_Karyawan);

            // 3. Update Approval Request Status (legacy path only)
            if (! $approvalAlreadyProcessed) {
                $statusApproval = $action === 'APPROVE' ? 'Y' : 'T';
                DB::table('HRIS_Approval_Request')
                    ->where('id', $approvalReq->id)
                    ->update([
                        'Flag_Approval' => $statusApproval,
                        'Approved_By_Kode' => $kodeApprover,
                        'Catatan' => $notes,
                        'update_at' => now(),
                        'Tanggal_Approval' => now(),
                    ]);
            }

            // Get dates for notification
            $tanggalBekerja = DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNotNull('Tanggal_Bekerja')
                ->value('Tanggal_Bekerja');

            $tanggalOff = DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNotNull('Tanggal_Off')
                ->value('Tanggal_Off');

            $allDates = array_filter([$tanggalBekerja, $tanggalOff]);
            $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($allDates);

            if ($action === 'REJECT') {
                if ($this->hasCurrentApproverInFlow($noTransaksi)) {
                    return ['status' => 'WAITING_NEXT'];
                }

                // If rejected, mark transaction as completed but don't apply shifts
                DB::table('N_HRIS_Transaksi_Tukar_Hari')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update([
                        'Flag_Selesai' => 'Y',
                        'Updated_At' => now(),
                        'Updated_By' => Auth::id(),
                    ]);

                DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update(['Flag_Approval' => 'T']);

                try {
                    $pesan = $this->whatsappMessageBuilder->Notif_Selesai_Transaksi(
                        $hpRequester,
                        $namaRequester,
                        $tipeTransaksi,
                        $noTransaksi,
                        $tanggalFormated,
                    );
                    $this->whatsappLogHelper->sendAndLog(
                        $approvalReq->Kode_Karyawan,
                        $namaRequester,
                        $hpRequester,
                        $noTransaksi,
                        $pesan,
                    );
                } catch (\Throwable $e) {
                    Log::error('WA Notification Failed (Tukar Hari Reject): '.$e->getMessage());
                }

                return ['status' => 'REJECTED'];
            }

            if ($this->hasCurrentApproverInFlow($noTransaksi)) {
                // 🔔 SEND NOTIFICATION TO NEXT APPROVER (Tukar Hari)
                try {
                    // Get next approver in flow
                    $nextApproverReq = DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Status')
                        ->where('Request_Order_Flow', $approvalReq->Request_Order_Flow + 1)
                        ->first();

                    if ($nextApproverReq) {
                        // Get next approver info
                        $hpNextApprover = $this->getKaryawanHp($nextApproverReq->Approver_Karyawan_Kode);
                        $namaNextApprover = $this->getKaryawanName($nextApproverReq->Approver_Karyawan_Kode);
                        $namaRequester = $this->getKaryawanName($approvalReq->Kode_Karyawan);

                        // Send approver notification (same pattern as shift)
                        $pesan = $this->whatsappMessageBuilder->approverShiftNotification(
                            $hpNextApprover,
                            $noTransaksi,
                            $namaRequester,
                            $tanggalFormated,
                        );
                        $this->whatsappLogHelper->sendAndLog(
                            $nextApproverReq->Approver_Karyawan_Kode,
                            $namaNextApprover,
                            $hpNextApprover,
                            $noTransaksi,
                            $pesan,
                        );

                        // Update angka_wa counter for next approver
                        DB::table('HRIS_Approval_Request')
                            ->where('id', $nextApproverReq->id)
                            ->update([
                                'angka_wa' => DB::raw('ISNULL(angka_wa, 0) + 1'),
                            ]);

                        Log::channel('ApprovalDetailService')->info(
                            'Next approver notification sent and angka_wa updated (Tukar Hari)',
                            [
                                'current_approver' => $approvalReq->Approver_Karyawan_Kode,
                                'next_approver' => $nextApproverReq->Approver_Karyawan_Kode,
                                'next_approver_request_id' => $nextApproverReq->id,
                                'next_flow_step' => $nextApproverReq->Request_Order_Flow,
                                'no_transaksi' => $noTransaksi,
                                'dates_for_next' => $tanggalFormated,
                            ],
                        );
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to send next approver notification (Tukar Hari): '.$e->getMessage(), [
                        'no_transaksi' => $noTransaksi,
                        'current_flow_step' => $approvalReq->Request_Order_Flow,
                    ]);
                }

                return ['status' => 'WAITING_NEXT'];
            }

            // 4. If APPROVED: Set Flag_Approval = Y and apply shifts
            // Update all details with Flag_Approval = Y
            DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->update(['Flag_Approval' => 'Y']);

            // Apply shifts and mark as completed
            $this->applyTukarHariAfterApproval($noTransaksi);

            // $tipeTransaksi = DB::table('N_HRIS_Transaksi_Shift')
            // ->where('No_Transaksi', $noTransaksi)
            // ->select('Jenis_Transaksi', 'Flag_Admin')
            // ->first();

            // if($tipeTransaksi && $tipeTransaksi->Jenis_Transaksi == 'LEADER_SHIFT' && $tipeTransaksi->Flag_Admin == 'T') {
            //     $getKaryawanDetail = DB::table('N_HRIS_Transaksi_Shift_Detail as dt')
            //         ->join('Karyawan as k', 'dt.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            //         ->where('dt.No_Transaksi', $noTransaksi)
            //         ->where('dt.Flag_Approval', 'Y')
            //         ->whereNull('dt.Status')
            //         ->where('k.Aktif', 'Y')
            //         ->select('k.Nama', 'k.HP', 'k.Kode_Karyawan', 'dt.Id', 'dt.Target_Table')
            //         ->distinct()
            //         ->get();
            //     // dd($getKaryawanDetail->pluck('Tanggal_Shift')->toArray());
            //     // dd($getKaryawanDetail);
            //     foreach($getKaryawanDetail as $emp) {
            //         $getTanggal = DB::table('N_HRIS_Transaksi_Shift_Det as det')
            //         ->join('HRIS_Shift_Kerja as s', 'det.Id_Shift_Diminta', '=', 's.ID_Shift')
            //         ->where('No_Transaksi', $noTransaksi)
            //         ->where('Id_Detail', $emp->Id)
            //         ->where('Needs_Approval', 'Y')
            //         ->where('Flag_Applied', 'Y')
            //         ->where('Flag_Approval', 'Y') // only approved dates
            //         ->select('Tanggal_Shift', 's.Nama')->get();

            //         // dd($getTanggal->pluck('Tanggal_Shift')->toArray());

            //         $namaEmployeeTarget = $emp->Nama;
            //         $HPEmployeeTarget = $emp->HP;
            //         $tanggal = FormatTanggalHelper::formatTanggalMultiple($getTanggal->pluck('Tanggal_Shift')->toArray());
            //         $tanggalFormatted = $emp->Target_Table == 'PERMANEN' ? FormatTanggalHelper::formatTanggalMultiple($getTanggal->pluck('Tanggal_Shift')->toArray()) . ' s/d Seterusnya' : FormatTanggalHelper::formatTanggalMultiple($getTanggal->pluck('Tanggal_Shift')->toArray());
            //         $shift = $getTanggal->pluck('Nama')->unique()->implode(', ');
            //         // Send employee notification for each affected employee
            //         try {
            //             $pesan = $this->whatsappMessageBuilder->Notif_Karyawan_Perubahan_Shift($HPEmployeeTarget, $namaEmployeeTarget, $emp->Target_Table, $shift, $namaRequester, $tanggalFormatted);
            //             // dd($pesan);
            //             $this->whatsappLogHelper->sendAndLog($emp->Kode_Karyawan, $namaEmployeeTarget, $HPEmployeeTarget, $noTransaksi, $pesan);
            //         } catch (\Throwable $e) {
            //             Log::error("WA Notification Failed (Final Approve - Employee): " . $e->getMessage(), [
            //                 'kode_karyawan' => $emp->Kode_Karyawan,
            //                 'nama_karyawan' => $namaEmployeeTarget,
            //                 'hp_karyawan' => $HPEmployeeTarget
            //             ]);
            //         }
            //     }
            // }

            try {
                $pesan = $this->whatsappMessageBuilder->Notif_Selesai_Transaksi(
                    $hpRequester,
                    $namaRequester,
                    $tipeTransaksi,
                    $noTransaksi,
                    $tanggalFormated,
                );
                $this->whatsappLogHelper->sendAndLog(
                    $approvalReq->Kode_Karyawan,
                    $namaRequester,
                    $hpRequester,
                    $noTransaksi,
                    $pesan,
                );
            } catch (\Throwable $e) {
                Log::error('WA Notification Failed (Tukar Hari Approve): '.$e->getMessage());
            }

            return ['status' => 'COMPLETED'];
        });
    }

    /**
     * Classify date untuk leader shift requests
     * Pure function yang determine approval requirements berdasarkan date type
     * dan approval engine decision (jika requester diberikan)
     *
     * PHASE 1: SCOPE & CLASSIFICATION FIX
     * Menggabungkan logic dari LeaderShiftController.classifyDate()
     * dengan support untuk fallback error handling
     *
     * @param  string  $tanggal  Date to classify (Y-m-d format)
     * @param  string  $today  Current date (Y-m-d format)
     * @param  string|null  $requesterKode  Optional requester code for approval engine
     * @param  array|null  $leaderConfig  Optional config override
     * @return array [
     *               'jenis_pengajuan' => 'BACKDATE'|'SAME_DAY'|'FUTURE',
     *               'needs_approval' => bool,
     *               'decision_notes' => string|null,
     *               'decision_action' => string|null,
     *               'decision_config_name' => string|null
     *               ]
     */
    public function classifyDateForLeaderShift(
        string $tanggal,
        string $today,
        ?string $requesterKode = null,
        ?array $leaderConfig = null,
        ?AccessContext $context = null,
    ): array {
        // Use provided config or fallback to standard leader config
        $config = $leaderConfig ?? [
            'backdate_needs_approval' => config('services.hcis.leader_shift_request.backdate_needs_approval', true),
            'same_day_needs_approval' => config('services.hcis.leader_shift_request.same_day_needs_approval', true),
            'future_needs_approval' => config('services.hcis.leader_shift_request.future_needs_approval', true),
        ];

        // 1️⃣ Determine date type
        $dateParsed = Carbon::parse($tanggal)->startOfDay();
        $todayParsed = Carbon::parse($today)->startOfDay();

        if ($dateParsed->lt($todayParsed)) {
            $jenis = 'BACKDATE';
            $defaultNeedsApproval = (bool) $config['backdate_needs_approval'];
        } elseif ($dateParsed->eq($todayParsed)) {
            $jenis = 'SAME_DAY';
            $defaultNeedsApproval = (bool) $config['same_day_needs_approval'];
        } else {
            $jenis = 'FUTURE';
            $defaultNeedsApproval = (bool) $config['future_needs_approval'];
        }

        $approvalType = $jenis === 'BACKDATE' ? 'LEADER_SHIFT_BACKDATE' : 'LEADER_SHIFT';

        // dd($context, $context?->isAdmin());
        if ($context && $context?->isAdmin()) {
            return [
                'jenis_pengajuan' => $jenis,
                'needs_approval' => false,
                'decision_notes' => null,
                'decision_action' => 'DIRECT',
                'decision_config_name' => null,
            ];
        }

        // 2️⃣ If no requester provided, return default classification
        if (! $requesterKode) {
            return [
                'jenis_pengajuan' => $jenis,
                'needs_approval' => $defaultNeedsApproval,
                'decision_notes' => null,
                'decision_action' => $defaultNeedsApproval ? 'APPROVAL' : 'DIRECT',
                'decision_config_name' => null,
            ];
        }

        // 3️⃣ Consult approval engine for final decision (with fallback)
        try {
            // dd($approvalType, $requesterKode, $tanggal, $jenis, $defaultNeedsApproval);
            $decision = $this->approvalFlowService->decide([
                'approval_type' => $approvalType,
                'requester_kode' => $requesterKode,
                'tanggal' => $tanggal,
                'classification' => [
                    'jenis_pengajuan' => $jenis,
                    'needs_approval' => $defaultNeedsApproval,
                ],
            ]);

            // dd($decision);

            return [
                'jenis_pengajuan' => $jenis,
                'needs_approval' => $decision['action'] === 'APPROVAL',
                'decision_notes' => $decision['notes'] ?? null,
                'decision_action' => $decision['action'] ?? null,
                'decision_config_name' => $decision['config_name'] ?? null,
            ];
        } catch (Exception $e) {
            // Fallback on approval engine error - use default classification
            Log::warning('Approval engine error in classifyDateForLeaderShift: '.$e->getMessage(), [
                'requester_kode' => $requesterKode,
                'tanggal' => $tanggal,
            ]);

            return [
                'jenis_pengajuan' => $jenis,
                'needs_approval' => $defaultNeedsApproval,
                'decision_notes' => 'Fallback to default (approval engine unavailable)',
                'decision_action' => $defaultNeedsApproval ? 'APPROVAL' : 'DIRECT',
                'decision_config_name' => null,
            ];
        }
    }

    /**
     * Classify a date for self shift request using approval engine
     * Similar to classifyDateForLeaderShift but for SELF_SHIFT context
     *
     * @param  array|null  $configOverride  Optional config override
     * @return array ['jenis_pengajuan', 'needs_approval', 'decision_notes', 'decision_action', 'decision_config_name']
     */
    protected function classifyDateForSelfShift(
        string $requesterKode,
        Carbon $tanggal,
        ?array $configOverride = null,
    ): array {
        // 1️⃣ Determine jenis pengajuan based on date
        $jenis = $this->determineJenisPengajuan($tanggal);

        // Get default needs approval from config
        $defaultNeedsApproval = $this->checkNeedsApproval($jenis);

        // Determine approval type for self shift
        $approvalType = $jenis === 'BACKDATE' ? 'SELF_SHIFT_BACKDATE' : 'SELF_SHIFT';

        // 2️⃣ If no requester provided, return default classification
        if (! $requesterKode) {
            return [
                'jenis_pengajuan' => $jenis,
                'needs_approval' => $defaultNeedsApproval,
                'decision_notes' => null,
                'decision_action' => $defaultNeedsApproval ? 'APPROVAL' : 'DIRECT',
                'decision_config_name' => null,
            ];
        }

        // 3️⃣ Consult approval engine for final decision (with fallback)
        try {
            $decision = $this->approvalFlowService->decide([
                'approval_type' => $approvalType,
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
                'decision_notes' => $decision['notes'] ?? null,
                'decision_action' => $decision['action'] ?? null,
                'decision_config_name' => $decision['config_name'] ?? null,
            ];
        } catch (Exception $e) {
            // Fallback on approval engine error - use default classification
            Log::warning('Approval engine error in classifyDateForSelfShift: '.$e->getMessage(), [
                'requester_kode' => $requesterKode,
                'tanggal' => $tanggal,
            ]);

            return [
                'jenis_pengajuan' => $jenis,
                'needs_approval' => $defaultNeedsApproval,
                'decision_notes' => 'Fallback to default (approval engine unavailable)',
                'decision_action' => $defaultNeedsApproval ? 'APPROVAL' : 'DIRECT',
                'decision_config_name' => null,
            ];
        }
    }

    /**
     * Determine jenis pengajuan based on date
     */
    protected function determineJenisPengajuan(Carbon $tanggal): string
    {
        $today = Carbon::today();

        if ($tanggal->lt($today)) {
            return 'BACKDATE';
        } elseif ($tanggal->eq($today)) {
            return 'HARI_H';
        } else {
            return 'FUTURE';
        }
    }

    /**
     * Check if jenis pengajuan needs approval
     */
    protected function checkNeedsApproval(string $jenisPengajuan): bool
    {
        return match ($jenisPengajuan) {
            'BACKDATE' => $this->config['backdate_needs_approval'],
            'HARI_H' => $this->config['same_day_needs_approval'],
            'FUTURE' => $this->config['future_needs_approval'],
            default => true,
        };
    }

    protected function checkNeedsApprovalWithConfig(string $jenisPengajuan, array $config): bool
    {
        return match ($jenisPengajuan) {
            'BACKDATE' => $config['backdate_needs_approval'] ?? true,
            'HARI_H' => $config['same_day_needs_approval'] ?? true,
            'FUTURE' => $config['future_needs_approval'] ?? true,
            default => true,
        };
    }

    protected function checkNeedsApprovalSwap(string $jenisPengajuan): bool
    {
        return $this->checkNeedsApprovalWithConfig($jenisPengajuan, $this->swapConfig);
    }

    protected function getOffShiftId(): ?int
    {
        $candidates = ['NO SHIFT', 'LIBUR', 'HARI MINGGU', 'OFF'];
        $orderBy =
            'CASE '.
            "WHEN Nama = 'NO SHIFT' THEN 1 ".
            "WHEN Nama = 'LIBUR' THEN 2 ".
            "WHEN Nama = 'HARI MINGGU' THEN 3 ".
            "WHEN Nama = 'OFF' THEN 4 ".
            'ELSE 5 END';

        $shift = DB::table('HRIS_Shift_Kerja')
            ->whereIn('Nama', $candidates)
            ->where(function ($query) {
                $query->whereNull('Non_Shift')->orWhere('Non_Shift', '!=', 'T');
            })
            ->orderByRaw($orderBy)
            ->select('ID_Shift')
            ->first();

        if (! $shift) {
            $shift = DB::table('HRIS_Shift_Kerja')
                ->whereIn('Nama', $candidates)
                ->orderByRaw($orderBy)
                ->select('ID_Shift')
                ->first();
        }

        return $shift?->ID_Shift;
    }

    /**
     * Get karyawan name by kode
     */
    public function getKaryawanName(string $kodeKaryawan): ?string
    {
        $karyawan = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->first();

        return $karyawan?->Nama;
    }

    public function getKaryawanJabatan(string $kodeKaryawan): ?string
    {
        $karyawan = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->select('vg.nama_jabatan', 'k.Nama', 'k.Kode_Karyawan')
            ->first();

        return $karyawan?->nama_jabatan;
    }

    /**
     * Get current approver for a employee
     */
    public function getCurrentApprover(string $kodeKaryawan): ?object
    {
        return DB::table('N_HRIS_Approval_Flow_Shift as afs')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'afs.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.Id_Divisi_Sub_Divisi')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'vg.Id_Level_Jabatan',
                '=',
                'k.Id_Level_Jabatan',
            )
            ->where('afs.Kode_Karyawan_Requester', $kodeKaryawan)
            ->whereNull('afs.Status')
            ->select('k.Nama', 'k.Kode_Karyawan', 'vg.nama_jabatan', 'vd.nama_divisi')
            ->first();
    }

    protected function getKaryawanHp(string $kodeKaryawan): ?string
    {
        $karyawan = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->first();

        return $karyawan?->HP;
    }

    protected function hasCurrentApproverInFlow(string $noTransaksi): bool
    {
        try {
            return DB::table('N_HRIS_VW_Approval_Request_V2')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Flow_State', 'CURRENT')
                ->exists();
        } catch (\Throwable $e) {
            return DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNull('Status')
                ->whereNull('Flag_Approval')
                ->exists();
        }
    }

    protected function extractKonversiTargetOffDate(?string $detNote): ?string
    {
        if (! $detNote) {
            return null;
        }

        $decoded = json_decode((string) $detNote, true);
        if (! is_array($decoded)) {
            return null;
        }

        $targetOff = isset($decoded['target_off']) ? (string) $decoded['target_off'] : '';
        if ($targetOff === '') {
            return null;
        }

        try {
            return Carbon::parse($targetOff)->toDateString();
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * Get user's shift request history
     */
    public function getHistory(string $kodeKaryawan, array $params): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;
        $filters = $params['filters'] ?? [];
        $offset = ($page - 1) * $limit;
        $userid = Auth::id();

        // === QUERY 1: Transaksi Shift ===
        $queryShift = DB::table('N_HRIS_Transaksi_Shift as h')
            ->join('N_HRIS_Transaksi_Shift_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->where('h.Created_By', $userid)
            ->where('Jenis_Transaksi', 'SELF_SHIFT')
            // Batal oleh karyawan sendiri disembunyikan; batal oleh ADMIN tetap
            // tampil supaya pengajuannya tidak hilang tanpa penjelasan.
            ->where(fn ($q) => $q->whereNull('h.Status')->orWhereExists(
                fn ($e) => $e->selectRaw('1')->from('N_HRIS_Transaksi_Shift_Det as bd')
                    ->whereColumn('bd.No_Transaksi', 'h.No_Transaksi')
                    ->where('bd.Note_Tolak', 'like', PengajuanShiftAdminService::PENANDA_BATAL_ADMIN.'%'),
            ));

        // === QUERY 2: Transaksi Tukar Hari ===
        // Tukar Hari uses Flag_Selesai in header for review status
        $queryTukarHari = DB::table('N_HRIS_Transaksi_Tukar_Hari as h')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('h.Created_By', $userid)
            ->where('h.Kode_Karyawan', $kodeKaryawan)
            ->where(fn ($q) => $q->whereNull('h.Status')->orWhereExists(
                fn ($e) => $e->selectRaw('1')->from('N_HRIS_Transaksi_Tukar_Hari_Detail as bd')
                    ->whereColumn('bd.No_Transaksi', 'h.No_Transaksi')
                    ->where('bd.Note_Tolak', 'like', PengajuanShiftAdminService::PENANDA_BATAL_ADMIN.'%'),
            ));

        // Apply filters to both queries
        if (! empty($filters['status'])) {
            if ($filters['status'] === 'Y') {
                // Sudah Direview
                $queryShift->where('h.Flag_Selesai', 'Y');
                $queryTukarHari->where('h.Flag_Selesai', 'Y');
            } elseif ($filters['status'] === 'null') {
                // Belum Direview
                $queryShift->where(function ($q) {
                    $q->whereNull('h.Flag_Selesai')->orWhere('h.Flag_Selesai', '!=', 'Y');
                });
                $queryTukarHari->where(function ($q) {
                    $q->whereNull('h.Flag_Selesai')->orWhere('h.Flag_Selesai', '!=', 'Y');
                });
            }
        }

        // Transaction type filter
        if (! empty($filters['transaction_type'])) {
            $transactionType = strtoupper((string) $filters['transaction_type']);

            if ($transactionType === 'TUKAR_HARI') {
                // Only Tukar Hari
                $queryShift->whereRaw('1 = 0');
            } elseif (in_array($transactionType, ['PERMANEN', 'SEMENTARA'], true)) {
                // Only Shift with selected target table
                $queryShift->where('d.Target_Table', $transactionType);
                $queryTukarHari->whereRaw('1 = 0');
            }
        }

        // Date range filter (based on transaction date details, not header Created_At)
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta')->startOfDay();
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta')->endOfDay();

            $queryShift->whereExists(function ($subquery) use ($startDate, $endDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Det as det')
                    ->join('N_HRIS_Transaksi_Shift_Detail as dt', 'det.Id_Detail', '=', 'dt.Id')
                    ->whereColumn('dt.No_Transaksi', 'h.No_Transaksi')
                    ->whereBetween('det.Tanggal_Shift', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
            });

            $queryTukarHari->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('d.Tanggal_Bekerja', [
                    $startDate->format('Y-m-d'),
                    $endDate->format('Y-m-d'),
                ])->orWhereBetween('d.Tanggal_Off', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
            });
        } elseif (! empty($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta')->startOfDay();

            $queryShift->whereExists(function ($subquery) use ($startDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Det as det')
                    ->join('N_HRIS_Transaksi_Shift_Detail as dt', 'det.Id_Detail', '=', 'dt.Id')
                    ->whereColumn('dt.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('det.Tanggal_Shift', '>=', $startDate->format('Y-m-d'));
            });

            $queryTukarHari->where(function ($q) use ($startDate) {
                $q->whereDate('d.Tanggal_Bekerja', '>=', $startDate->format('Y-m-d'))->orWhereDate(
                    'd.Tanggal_Off',
                    '>=',
                    $startDate->format('Y-m-d'),
                );
            });
        } elseif (! empty($filters['end_date'])) {
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta')->endOfDay();

            $queryShift->whereExists(function ($subquery) use ($endDate) {
                $subquery
                    ->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Det as det')
                    ->join('N_HRIS_Transaksi_Shift_Detail as dt', 'det.Id_Detail', '=', 'dt.Id')
                    ->whereColumn('dt.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('det.Tanggal_Shift', '<=', $endDate->format('Y-m-d'));
            });

            $queryTukarHari->where(function ($q) use ($endDate) {
                $q->whereDate('d.Tanggal_Bekerja', '<=', $endDate->format('Y-m-d'))->orWhereDate(
                    'd.Tanggal_Off',
                    '<=',
                    $endDate->format('Y-m-d'),
                );
            });
        }

        // Search filter
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $queryShift->where('h.No_Transaksi', 'like', "%{$search}%");
            $queryTukarHari->where('h.No_Transaksi', 'like', "%{$search}%");
        }

        if (! empty($filters['periode'])) {
            /* Rentang setengah-terbuka: YEAR(h.Created_At) mematikan indeks. */
            $awalPeriode = Carbon::create((int) $filters['periode'], 1, 1)->startOfDay();
            $akhirPeriode = $awalPeriode->copy()->addYear();

            $queryShift->where('h.Created_At', '>=', $awalPeriode)->where('h.Created_At', '<', $akhirPeriode);
            $queryTukarHari->where('h.Created_At', '>=', $awalPeriode)->where('h.Created_At', '<', $akhirPeriode);
        }

        // Get distinct transactions from both tables
        $transactionsShift = $queryShift
            ->select('h.*', 'd.Target_Table', 'd.Kode_Karyawan', DB::raw("'SHIFT' as transaction_type"))
            ->distinct()
            ->get();

        $transactionsTukarHari = $queryTukarHari
            ->select(
                'h.*',
                DB::raw('NULL as Target_Table'),
                'h.Kode_Karyawan',
                DB::raw("'TUKAR_HARI' as transaction_type"),
            )
            ->distinct()
            ->get();

        // Merge both collections
        $allTransactions = $transactionsShift->merge($transactionsTukarHari);

        // Sort by Created_At desc
        $allTransactions = $allTransactions->sortByDesc('Created_At')->values();

        // Count total
        $countTransactions = $allTransactions->count();

        // Paginate manually
        $transactions = $allTransactions->slice($offset, $limit)->values();

        /* Pelengkap satu halaman diambil SEKALI, bukan tiga kueri per transaksi.
           Satu halaman 10 transaksi dulu berarti 10 kueri rincian + 10 kueri
           `exists`; biayanya naik lurus mengikuti `limit` yang diminta. */
        $noShift = $this->nomorTransaksiPer($transactions, 'SHIFT');
        $noTukarHari = $this->nomorTransaksiPer($transactions, 'TUKAR_HARI');

        $rincianShift = $this->rincianShiftMassal($noShift);
        $rincianTukarHari = $this->rincianTukarHariMassal($noTukarHari);
        $sudahDitindak = $this->sudahAdaTindakanApproval(array_merge($noShift, $noTukarHari));
        $catatanBatalAdmin = $this->catatanBatalAdminMassal([
            'N_HRIS_Transaksi_Shift_Det' => $noShift,
            'N_HRIS_Transaksi_Tukar_Hari_Detail' => $noTukarHari,
        ]);

        foreach ($transactions as $trans) {
            $kunci = $this->kunciNomorTransaksi($trans->No_Transaksi);

            if ($trans->transaction_type === 'SHIFT') {
                $trans->details = $rincianShift[$kunci] ?? collect();

                $trans->tanggal_count = $trans->details->count();
                $trans->shift_display =
                    $trans->details->count() > 1 ? 'Mixed' : $trans->details->first()?->Nama_Shift ?? '-';
            } else {
                $trans->details = $rincianTukarHari[$kunci] ?? collect();

                $trans->tanggal_count = $trans->details->count();
                $trans->shift_display = $trans->details->first()?->Nama_Shift ?? '-';
            }

            $trans->has_approval_action = isset($sudahDitindak[$kunci]);
            // Status 'Y' di header = seluruh pengajuan dibatalkan; catatan tanpa
            // Status 'Y' = hanya tanggal yang belum diterapkan yang dibatalkan.
            $trans->catatan_batal_admin = $catatanBatalAdmin[$kunci] ?? null;
            $trans->dibatalkan_admin = $trans->Status === 'Y' && $trans->catatan_batal_admin !== null;
        }

        return [
            'items' => $transactions,
            'total' => $countTransactions,
            'page' => $page,
            'limit' => $limit,
            'stats' => $this->getHistoryStats($kodeKaryawan),
        ];
    }

    /**
     * Get summary stats for self-shift widgets.
     */
    /**
     * Batas parameter satu pernyataan di SQL Server 2.100; `limit` datang dari
     * permintaan, jadi daftarnya dipotong sebelum dikirim.
     */
    private const POTONGAN_NOMOR = 500;

    /**
     * Nomor transaksi pada satu halaman untuk satu jenis, sudah dinormalkan.
     *
     * @return array<int,string>
     */
    private function nomorTransaksiPer(\Illuminate\Support\Collection $transactions, string $jenis): array
    {
        return $transactions
            ->where('transaction_type', $jenis)
            ->pluck('No_Transaksi')
            ->filter(fn ($n) => $n !== null && $n !== '')
            ->map(fn ($n) => (string) $n)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Kunci pencocokan hasil massal.
     *
     * Disamakan huruf besarnya karena SQL Server membandingkan tanpa peduli
     * besar-kecil huruf, sedangkan array PHP peduli — nomor yang tersimpan
     * berbeda kapitalisasi antar tabel akan meleset diam-diam.
     */
    private function kunciNomorTransaksi(?string $noTransaksi): string
    {
        return strtoupper(trim((string) $noTransaksi));
    }

    /**
     * Rincian tanggal shift untuk sederet transaksi sekaligus.
     *
     * @param  array<int,string>  $noTransaksi
     * @return array<string,\Illuminate\Support\Collection>
     */
    private function rincianShiftMassal(array $noTransaksi): array
    {
        if ($noTransaksi === []) {
            return [];
        }

        $hasil = collect();
        foreach (array_chunk($noTransaksi, self::POTONGAN_NOMOR) as $potongan) {
            $hasil = $hasil->concat(
                DB::table('N_HRIS_Transaksi_Shift_Det as det')
                    ->join('N_HRIS_Transaksi_Shift_Detail as d', 'det.Id_Detail', '=', 'd.Id')
                    ->leftJoin('HRIS_Shift_Kerja as s', 'det.Id_Shift_Diminta', '=', 's.ID_Shift')
                    ->whereNull('det.Status')
                    ->whereNull('d.Status')
                    ->whereIn('det.No_Transaksi', $potongan)
                    ->select('det.*', 's.Nama as Nama_Shift', 'd.Target_Table')
                    ->get(),
            );
        }

        return $hasil
            ->groupBy(fn ($baris) => $this->kunciNomorTransaksi($baris->No_Transaksi))
            ->all();
    }

    /**
     * Rincian tukar hari untuk sederet transaksi sekaligus.
     *
     * @param  array<int,string>  $noTransaksi
     * @return array<string,\Illuminate\Support\Collection>
     */
    private function rincianTukarHariMassal(array $noTransaksi): array
    {
        if ($noTransaksi === []) {
            return [];
        }

        $hasil = collect();
        foreach (array_chunk($noTransaksi, self::POTONGAN_NOMOR) as $potongan) {
            $hasil = $hasil->concat(
                DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail as d')
                    ->leftJoin('HRIS_Shift_Kerja as s', 'd.Id_Shift_Bekerja', '=', 's.ID_Shift')
                    ->whereIn('d.No_Transaksi', $potongan)
                    ->select('d.*', 's.Nama as Nama_Shift')
                    ->get(),
            );
        }

        return $hasil
            ->groupBy(fn ($baris) => $this->kunciNomorTransaksi($baris->No_Transaksi))
            ->all();
    }

    /**
     * Transaksi yang approver-nya sudah pernah bertindak, sekali kueri.
     *
     * @param  array<int,string>  $noTransaksi
     * @return array<string,bool> berkunci nomor transaksi
     */
    private function sudahAdaTindakanApproval(array $noTransaksi): array
    {
        if ($noTransaksi === []) {
            return [];
        }

        $kunci = [];
        foreach (array_chunk($noTransaksi, self::POTONGAN_NOMOR) as $potongan) {
            $baris = DB::table('HRIS_Approval_Request_Detail as d')
                ->join('HRIS_Approval_Request as ar', 'd.Id_Approval_Request', '=', 'ar.id')
                ->whereIn('ar.No_Transaksi', $potongan)
                ->whereNotNull('d.Flag_Approval')
                ->distinct()
                ->pluck('ar.No_Transaksi');

            foreach ($baris as $n) {
                $kunci[$this->kunciNomorTransaksi($n)] = true;
            }
        }

        return $kunci;
    }

    /**
     * Alasan pembatalan oleh admin per transaksi pada satu halaman riwayat.
     *
     * @param  array<string,array<int,string>>  $nomorPerTabel  tabel ber-Note_Tolak => nomor transaksi
     * @return array<string,string> berkunci nomor transaksi
     */
    private function catatanBatalAdminMassal(array $nomorPerTabel): array
    {
        $pola = PengajuanShiftAdminService::PENANDA_BATAL_ADMIN.'%';
        $hasil = [];
        foreach ($nomorPerTabel as $tabel => $nomor) {
            foreach (array_chunk($nomor, self::POTONGAN_NOMOR) as $potongan) {
                DB::table($tabel)->whereIn('No_Transaksi', $potongan)->where('Note_Tolak', 'like', $pola)
                    ->get(['No_Transaksi', 'Note_Tolak'])
                    ->each(function ($r) use (&$hasil) {
                        $hasil[$this->kunciNomorTransaksi($r->No_Transaksi)] = (string) $r->Note_Tolak;
                    });
            }
        }

        return $hasil;
    }

    public function getHistoryStats(string $kodeKaryawan): array
    {
        $userid = Auth::id();

        $statsQueryShift = DB::table('N_HRIS_Transaksi_Shift as h')
            ->join('N_HRIS_Transaksi_Shift_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('d.Status')
            ->whereNull('h.Status')
            ->where('h.Created_By', $userid)
            ->where('Jenis_Transaksi', 'SELF_SHIFT');

        $statsQueryTukarHari = DB::table('N_HRIS_Transaksi_Tukar_Hari as h')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->where('h.Created_By', $userid)
            ->whereNull('h.Status');

        /* Tiga angka diambil dalam satu lintasan per tabel, bukan tiga kueri
           masing-masing: 6 kueri jadi 2. Syarat 'pending' ditulis persis
           seperti bentuk lamanya — `<> 'Y'` sendirian membuang baris NULL,
           jadi NULL harus disebut terpisah. */
        $agregat = 'count(distinct h.No_Transaksi) as total, '
            ."count(distinct case when h.Flag_Selesai is null or h.Flag_Selesai <> 'Y' then h.No_Transaksi end) as pending, "
            ."count(distinct case when h.Flag_Selesai = 'Y' then h.No_Transaksi end) as approved";

        $shift = (clone $statsQueryShift)->selectRaw($agregat)->first();
        $tukarHari = (clone $statsQueryTukarHari)->selectRaw($agregat)->first();

        return [
            'total' => (int) ($shift->total ?? 0) + (int) ($tukarHari->total ?? 0),
            'pending' => (int) ($shift->pending ?? 0) + (int) ($tukarHari->pending ?? 0),
            'approved' => (int) ($shift->approved ?? 0) + (int) ($tukarHari->approved ?? 0),
        ];
    }

    /**
     * Get transaction detail
     */
    public function getDetail(string $noTransaksi): ?array
    {
        // Detect transaction type by prefix: TSH = Status Hari, TH = Tukar Hari, else = Shift
        if (str_starts_with($noTransaksi, 'TSH')) {
            $transactionType = 'STATUS_HARI';
        } elseif (str_starts_with($noTransaksi, 'TH')) {
            $transactionType = 'TUKAR_HARI';
        } else {
            $transactionType = 'SHIFT';
        }

        if ($transactionType === 'STATUS_HARI') {
            // === STATUS HARI TRANSACTION (3-tier) ===
            $header = DB::table('N_HRIS_Transaksi_Status_Hari')->where('No_Transaksi', $noTransaksi)->first();

            if (! $header) {
                return null;
            }

            // Tier 2: Detail per employee
            $details = DB::table('N_HRIS_Transaksi_Status_Hari_Detail as d')
                ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as j',
                    'k.Id_Level_Jabatan',
                    '=',
                    'j.Id_Level_Jabatan',
                )
                ->where('d.No_Transaksi', $noTransaksi)
                ->select(
                    'd.Id',
                    'd.Kode_Karyawan',
                    'k.Nama',
                    'k.NIK',
                    'j.nama_jabatan as Nama_Jabatan',
                    'd.Target_Table',
                    'd.Alasan',
                    'd.Flag_Approval',
                    'd.Status',
                )
                ->get();

            // Tier 3: Det per date for each detail
            $members = $details->map(function ($detail) use ($noTransaksi) {
                $dates = DB::table('N_HRIS_Transaksi_Status_Hari_Det as det')
                    ->where('det.Id_Detail', $detail->Id)
                    ->where('det.No_Transaksi', $noTransaksi)
                    ->select(
                        'det.Id',
                        'det.Alasan',
                        'det.Tanggal',
                        'det.Status_Hari',
                        'det.Flag_Approval',
                        'det.Flag_Applied',
                        'det.Needs_Approval',
                        'det.Jenis_Pengajuan',
                        'det.Status',
                    )
                    ->orderBy('det.Tanggal')
                    ->get();

                $sourceKeys = $dates
                    ->map(function ($date) use ($noTransaksi) {
                        return implode('|', [$noTransaksi, $date->Id]);
                    })
                    ->toArray();

                $rejectionMap = $this->getRejectionInfoMapBySourceKeys($sourceKeys);

                $dates = $dates->map(function ($date) use ($noTransaksi, $rejectionMap) {
                    $sourceKey = implode('|', [$noTransaksi, $date->Id]);
                    $rejectionInfo = $rejectionMap[$sourceKey] ?? null;

                    $date->Processed_By_Kode = $rejectionInfo['Processed_By_Kode'] ?? null;
                    $date->Processed_By_Nama = $rejectionInfo['Processed_By_Nama'] ?? null;
                    $date->Processed_At = $rejectionInfo['Processed_At'] ?? null;

                    return $date;
                });

                return [
                    'Id' => $detail->Id,
                    'Kode_Karyawan' => $detail->Kode_Karyawan,
                    'Nama' => $detail->Nama,
                    'NIK' => $detail->NIK,
                    'Nama_Jabatan' => $detail->Nama_Jabatan,
                    'Target_Table' => $detail->Target_Table,
                    'Alasan' => $detail->Alasan,
                    'Flag_Approval' => $detail->Flag_Approval,
                    'Status' => $detail->Status,
                    'dates' => $dates,
                ];
            });

            // Get approval steps
            $approvalSteps = DB::table('HRIS_Approval_Request as ar')
                ->leftJoin('Karyawan as k', 'ar.Approver_Karyawan_Kode', '=', 'k.Kode_Karyawan')
                ->where('ar.No_Transaksi', $noTransaksi)
                ->whereNull('ar.Status')
                ->orderBy('ar.Request_Order_Flow')
                ->select('ar.*', 'k.Nama')
                ->get();

            $approvalSteps = $approvalSteps->map(function ($step) {
                if ($step->Flag_Approval === 'T') {
                    $approvalRequestId = $step->Id_Approval_Request ?? ($step->id ?? null);
                    if (! $approvalRequestId) {
                        return $step;
                    }

                    $detail = DB::table('HRIS_Approval_Request_Detail as d')
                        ->where('d.Id_Approval_Request', $approvalRequestId)
                        ->whereNotNull('d.Processed_By_Kode')
                        ->orderByDesc('d.Created_At')
                        ->first(['d.Processed_By_Kode', 'd.Processed_At']);

                    if ($detail) {
                        $processedBy = DB::table('Karyawan')
                            ->where('Kode_Karyawan', $detail->Processed_By_Kode)
                            ->first(['Kode_Karyawan', 'Nama']);

                        $step->Processed_By_Kode = $detail->Processed_By_Kode;
                        $step->Processed_By_Nama = $processedBy->Nama ?? $detail->Processed_By_Kode;
                        $step->Processed_At = $detail->Processed_At;
                    }
                }

                return $step;
            });

            return [
                'transaction_type' => 'STATUS_HARI',
                'header' => $header,
                'members' => $members,
                'approval_steps' => $approvalSteps,
            ];
        } elseif ($transactionType === 'TUKAR_HARI') {
            // === TUKAR HARI TRANSACTION ===
            $header = DB::table('N_HRIS_Transaksi_Tukar_Hari')->where('No_Transaksi', $noTransaksi)->first();

            if (! $header) {
                return null;
            }

            $detail = DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail as d')
                ->leftJoin('HRIS_Shift_Kerja as s', 'd.Id_Shift_Bekerja', '=', 's.ID_Shift')
                ->leftJoin('HRIS_Shift_Kerja_Detail as sd', 's.ID_Shift', '=', 'sd.ID_Shift')
                ->leftJoin('HRIS_Waktu_Kerja as w', 'sd.ID_Waktu_Kerja', '=', 'w.ID_Waktu_Kerja')
                ->where('d.No_Transaksi', $noTransaksi)
                ->select('d.*', 's.Nama as Nama_Shift', 'w.Jam_Masuk', 'w.Jam_Keluar')
                ->first();

            $approvalSteps = DB::table('HRIS_Approval_Request as ar')
                ->leftJoin('Karyawan as k', 'ar.Approver_Karyawan_Kode', '=', 'k.Kode_Karyawan')
                ->where('ar.No_Transaksi', $noTransaksi)
                ->whereNull('ar.Status')
                ->orderBy('ar.Request_Order_Flow')
                ->select('ar.*', 'k.Nama')
                ->get();

            $approvalSteps = $approvalSteps->map(function ($step) {
                if ($step->Flag_Approval === 'T') {
                    $approvalRequestId = $step->Id_Approval_Request ?? ($step->id ?? null);
                    if (! $approvalRequestId) {
                        return $step;
                    }

                    $detail = DB::table('HRIS_Approval_Request_Detail as d')
                        ->where('d.Id_Approval_Request', $approvalRequestId)
                        ->whereNotNull('d.Processed_By_Kode')
                        ->orderByDesc('d.Created_At')
                        ->first(['d.Processed_By_Kode', 'd.Processed_At']);

                    if ($detail) {
                        $processedBy = DB::table('Karyawan')
                            ->where('Kode_Karyawan', $detail->Processed_By_Kode)
                            ->first(['Kode_Karyawan', 'Nama']);

                        $step->Processed_By_Kode = $detail->Processed_By_Kode;
                        $step->Processed_By_Nama = $processedBy->Nama ?? $detail->Processed_By_Kode;
                        $step->Processed_At = $detail->Processed_At;
                    }
                }

                return $step;
            });

            return [
                'transaction_type' => 'TUKAR_HARI',
                'header' => $header,
                'detail' => $detail,
                'approval_steps' => $approvalSteps,
            ];
        } else {
            // === SHIFT TRANSACTION (3-tier, grouped by member) ===
            $header = DB::table('N_HRIS_Transaksi_Shift')->where('No_Transaksi', $noTransaksi)->first();

            if (! $header) {
                return null;
            }

            // Tier 2: Detail per employee
            $karyawanDetails = DB::table('N_HRIS_Transaksi_Shift_Detail as d')
                ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as j',
                    'k.Id_Level_Jabatan',
                    '=',
                    'j.Id_Level_Jabatan',
                )
                ->where('d.No_Transaksi', $noTransaksi)
                ->whereNull('d.Status')
                ->select(
                    'd.Id',
                    'd.Kode_Karyawan',
                    'k.Nama',
                    'k.NIK',
                    'j.nama_jabatan as Nama_Jabatan',
                    'd.Target_Table',
                    'd.Flag_Approval',
                    'd.Status',
                )
                ->get();

            // Tier 3: Det per date, grouped by member
            $members = $karyawanDetails->map(function ($detail) use ($noTransaksi) {
                $dates = DB::table('N_HRIS_Transaksi_Shift_Det as det')
                    ->leftJoin('HRIS_Shift_Kerja as s', 'det.Id_Shift_Diminta', '=', 's.ID_Shift')
                    ->leftJoin('HRIS_Shift_Kerja as sOld', 'det.Id_Shift_Sebelumnya', '=', 'sOld.ID_Shift')
                    ->where('det.Id_Detail', $detail->Id)
                    ->whereNull('det.Status')
                    ->where('det.No_Transaksi', $noTransaksi)
                    ->select(
                        'det.Id',
                        'det.Kode_Perusahaan',
                        'det.Alasan',
                        'det.Tanggal_Shift',
                        'det.Id_Shift_Diminta',
                        'det.Id_Shift_Sebelumnya',
                        's.Nama as Nama_Shift_Baru',
                        'sOld.Nama as Nama_Shift_Lama',
                        'det.Flag_Approval',
                        'det.Flag_Applied',
                        'det.Needs_Approval',
                        'det.Jenis_Pengajuan',
                        'det.Status',
                    )
                    ->orderBy('det.Tanggal_Shift')
                    ->get();

                $sourceKeys = $dates
                    ->map(function ($date) use ($noTransaksi) {
                        return implode('|', [$date->Kode_Perusahaan ?? '001', $noTransaksi, $date->Id]);
                    })
                    ->toArray();

                $rejectionMap = $this->getRejectionInfoMapBySourceKeys($sourceKeys);

                $dates = $dates->map(function ($date) use ($noTransaksi, $rejectionMap) {
                    $sourceKey = implode('|', [$date->Kode_Perusahaan ?? '001', $noTransaksi, $date->Id]);
                    $rejectionInfo = $rejectionMap[$sourceKey] ?? null;

                    $date->Processed_By_Kode = $rejectionInfo['Processed_By_Kode'] ?? null;
                    $date->Processed_By_Nama = $rejectionInfo['Processed_By_Nama'] ?? null;
                    $date->Processed_At = $rejectionInfo['Processed_At'] ?? null;

                    return $date;
                });

                return [
                    'Id' => $detail->Id,
                    'Kode_Karyawan' => $detail->Kode_Karyawan,
                    'Nama' => $detail->Nama,
                    'NIK' => $detail->NIK,
                    'Nama_Jabatan' => $detail->Nama_Jabatan,
                    'Target_Table' => $detail->Target_Table,
                    'Flag_Approval' => $detail->Flag_Approval,
                    'Status' => $detail->Status,
                    'dates' => $dates,
                ];
            });

            $approvalSteps = DB::table('HRIS_Approval_Request as ar')
                ->leftJoin('Karyawan as k', 'ar.Approver_Karyawan_Kode', '=', 'k.Kode_Karyawan')
                ->where('ar.No_Transaksi', $noTransaksi)
                ->whereNull('ar.Status')
                ->orderBy('ar.Request_Order_Flow')
                ->select('ar.*', 'k.Nama')
                ->get();

            $approvalSteps = $approvalSteps->map(function ($step) {
                if ($step->Flag_Approval === 'T') {
                    $approvalRequestId = $step->Id_Approval_Request ?? ($step->id ?? null);
                    if (! $approvalRequestId) {
                        return $step;
                    }

                    $detail = DB::table('HRIS_Approval_Request_Detail as d')
                        ->where('d.Id_Approval_Request', $approvalRequestId)
                        ->whereNotNull('d.Processed_By_Kode')
                        ->orderByDesc('d.Created_At')
                        ->first(['d.Processed_By_Kode', 'd.Processed_At']);

                    if ($detail) {
                        $processedBy = DB::table('Karyawan')
                            ->where('Kode_Karyawan', $detail->Processed_By_Kode)
                            ->first(['Kode_Karyawan', 'Nama']);

                        $step->Processed_By_Kode = $detail->Processed_By_Kode;
                        $step->Processed_By_Nama = $processedBy->Nama ?? $detail->Processed_By_Kode;
                        $step->Processed_At = $detail->Processed_At;
                    }
                }

                return $step;
            });

            return [
                'transaction_type' => 'SHIFT',
                'header' => $header,
                'members' => $members,
                'approval_steps' => $approvalSteps,
            ];
        }
    }

    /**
     * Cancel leader transaction (header/detail/det) if all active dets are still pending.
     * For TUKAR_HARI (2-tier), uses Detail only. For SHIFT/STATUS_HARI (3-tier), uses Det.
     */
    public function cancelLeaderShiftTransaction(
        string $noTransaksi,
        string $scope,
        ?int $detailId = null,
        ?int $detId = null,
    ): array {
        return DB::transaction(function () use ($noTransaksi, $scope, $detailId, $detId) {
            // Detect transaction type by prefix: TSH = Status Hari, TH = Tukar Hari, else = Shift
            if (str_starts_with($noTransaksi, 'TSH')) {
                $transactionType = 'STATUS_HARI';
            } elseif (str_starts_with($noTransaksi, 'TH')) {
                $transactionType = 'TUKAR_HARI';
            } else {
                $transactionType = 'SHIFT';
            }

            $headerTable =
                $transactionType === 'STATUS_HARI'
                    ? 'N_HRIS_Transaksi_Status_Hari'
                    : ($transactionType === 'TUKAR_HARI'
                        ? 'N_HRIS_Transaksi_Tukar_Hari'
                        : 'N_HRIS_Transaksi_Shift');
            $detailTable =
                $transactionType === 'STATUS_HARI'
                    ? 'N_HRIS_Transaksi_Status_Hari_Detail'
                    : ($transactionType === 'TUKAR_HARI'
                        ? 'N_HRIS_Transaksi_Tukar_Hari_Detail'
                        : 'N_HRIS_Transaksi_Shift_Detail');

            // Det table only for SHIFT/STATUS_HARI (3-tier). TUKAR_HARI is 2-tier.
            $detTable = null;
            if ($transactionType === 'STATUS_HARI') {
                $detTable = 'N_HRIS_Transaksi_Status_Hari_Det';
            } elseif ($transactionType === 'SHIFT') {
                $detTable = 'N_HRIS_Transaksi_Shift_Det';
            }
            // For TUKAR_HARI, $detTable remains null (2-tier structure)

            $header = DB::table($headerTable)->where('No_Transaksi', $noTransaksi)->first();

            if (! $header) {
                throw new Exception('Transaksi tidak ditemukan.');
            }

            if ($header->Status === 'Y') {
                throw new Exception('Transaksi sudah dibatalkan.');
            }

            $syncApprovalStateAfterCancel = function () use ($detTable, $headerTable, $noTransaksi) {
                $hasPendingApproval = null;

                // For 3-tier structures, check Det table
                if ($detTable) {
                    $hasPendingApproval = DB::table($detTable)
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Status')
                        ->where('Needs_Approval', 'Y')
                        ->whereNull('Flag_Approval')
                        ->exists();
                } else {
                    // For 2-tier structures (TUKAR_HARI), check Detail table directly
                    // But Tukar Hari Detail doesn't have Needs_Approval, so just check if pending
                    $hasPendingApproval = DB::table(
                        str_replace(
                            'N_HRIS_Transaksi_Shift',
                            'N_HRIS_Transaksi_Tukar_Hari',
                            str_replace('_Detail', '', 'N_HRIS_Transaksi_Tukar_Hari_Detail'),
                        ).'_Detail',
                    )
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Status')
                        ->whereNull('Flag_Approval')
                        ->exists();
                }

                if (! $hasPendingApproval) {
                    DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Flag_Approval')
                        ->whereNull('Status')
                        ->update([
                            'Status' => 'Y',
                            'update_at' => now(),
                        ]);

                    DB::table($headerTable)
                        ->where('No_Transaksi', $noTransaksi)
                        ->update([
                            'Flag_Selesai' => 'Y',
                            'Updated_At' => now(),
                        ]);
                }
            };

            $updateKolomUpdatedAtTransaksi = function () use ($headerTable, $noTransaksi) {
                DB::table($headerTable)
                    ->where('No_Transaksi', $noTransaksi)
                    ->update(['Updated_At' => now()]);
            };

            $assertAllPending = function ($baseQuery) {
                $activeCount = (clone $baseQuery)->whereNull('Status')->count();
                if ($activeCount === 0) {
                    throw new Exception('Tidak ada data aktif untuk dibatalkan.');
                }

                // "Pending" = belum disetujui/ditolak DAN belum diterapkan.
                $pendingCount = (clone $baseQuery)
                    ->whereNull('Status')
                    ->where(function ($q) {
                        // Case 1: perlu approval, belum disetujui, belum diterapkan
                        $q->where(function ($q1) {
                            $q1->where('Needs_Approval', 'Y')
                                ->whereNull('Flag_Approval')
                                ->where(function ($q2) {
                                    $q2->whereNull('Flag_Applied')->orWhere('Flag_Applied', '!=', 'Y');
                                });
                        })
                            // Case 2: tidak perlu approval, belum diterapkan
                            ->orWhere(function ($q1) {
                                $q1->where(function ($q2) {
                                    $q2->where('Needs_Approval', 'N')->orWhereNull('Needs_Approval');
                                })->where(function ($q2) {
                                    $q2->whereNull('Flag_Applied')->orWhere('Flag_Applied', '!=', 'Y');
                                });
                            });
                    })
                    ->count();

                if ($pendingCount !== $activeCount) {
                    throw new Exception(
                        'Tidak bisa membatalkan karena ada yang sudah disetujui atau sudah diterapkan.',
                    );
                }
            };

            if ($scope === 'header') {
                // For 3-tier structures, assert all dets are pending
                if ($detTable) {
                    $assertAllPending(DB::table($detTable)->where('No_Transaksi', $noTransaksi));

                    DB::table($detTable)
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Status')
                        ->update(['Status' => 'Y']);
                }

                // For both 2-tier and 3-tier: mark detail and header as cancelled
                DB::table($detailTable)
                    ->where('No_Transaksi', $noTransaksi)
                    ->whereNull('Status')
                    ->update(['Status' => 'Y']);

                DB::table($headerTable)
                    ->where('No_Transaksi', $noTransaksi)
                    ->update(['Status' => 'Y']);

                $syncApprovalStateAfterCancel();
                $updateKolomUpdatedAtTransaksi();
            } elseif ($scope === 'detail') {
                // Detail scope only works for 3-tier structures (SHIFT/STATUS_HARI), not TUKAR_HARI
                if (! $detTable) {
                    throw new Exception(
                        'Pembatalan detail tidak didukung untuk transaksi tukar hari. Gunakan pembatalan header.',
                    );
                }

                if (! $detailId) {
                    throw new Exception('Detail transaksi tidak ditemukan.');
                }

                $detail = DB::table($detailTable)->where('No_Transaksi', $noTransaksi)->where('Id', $detailId)->first();

                if (! $detail) {
                    throw new Exception('Detail transaksi tidak ditemukan.');
                }

                if ($detail->Status === 'Y') {
                    throw new Exception('Detail sudah dibatalkan.');
                }

                $assertAllPending(
                    DB::table($detTable)->where('No_Transaksi', $noTransaksi)->where('Id_Detail', $detailId),
                );

                DB::table($detTable)
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Id_Detail', $detailId)
                    ->whereNull('Status')
                    ->update(['Status' => 'Y']);

                DB::table($detailTable)
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Id', $detailId)
                    ->update(['Status' => 'Y']);

                $remainingDetails = DB::table($detailTable)
                    ->where('No_Transaksi', $noTransaksi)
                    ->whereNull('Status')
                    ->count();

                if ($remainingDetails === 0) {
                    DB::table($headerTable)
                        ->where('No_Transaksi', $noTransaksi)
                        ->update(['Status' => 'Y']);
                }

                $syncApprovalStateAfterCancel();
                $updateKolomUpdatedAtTransaksi();
            } elseif ($scope === 'det') {
                // Det scope only for 3-tier structures
                if (! $detTable) {
                    throw new Exception('Pembatalan tanggal tidak didukung untuk transaksi tukar hari.');
                }

                if (! $detId) {
                    throw new Exception('Tanggal transaksi tidak ditemukan.');
                }

                $det = DB::table($detTable)->where('No_Transaksi', $noTransaksi)->where('Id', $detId)->first();

                if (! $det) {
                    throw new Exception('Tanggal transaksi tidak ditemukan.');
                }

                if ($det->Status === 'Y') {
                    throw new Exception('Tanggal sudah dibatalkan.');
                }

                $isPending =
                    $det->Needs_Approval === 'Y' &&
                    $det->Flag_Approval === null &&
                    ($det->Flag_Applied === null || $det->Flag_Applied !== 'Y');

                if (! $isPending) {
                    throw new Exception(
                        'Tidak bisa membatalkan tanggal yang sudah disetujui atau otomatis diterapkan.',
                    );
                }

                DB::table($detTable)
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Id', $detId)
                    ->update(['Status' => 'Y']);

                $remainingDets = DB::table($detTable)
                    ->where('Id_Detail', $det->Id_Detail)
                    ->whereNull('Status')
                    ->count();

                if ($remainingDets === 0) {
                    DB::table($detailTable)
                        ->where('Id', $det->Id_Detail)
                        ->update(['Status' => 'Y']);
                }

                $remainingDetails = DB::table($detailTable)
                    ->where('No_Transaksi', $noTransaksi)
                    ->whereNull('Status')
                    ->count();

                if ($remainingDetails === 0) {
                    DB::table($headerTable)
                        ->where('No_Transaksi', $noTransaksi)
                        ->update(['Status' => 'Y']);
                }

                $syncApprovalStateAfterCancel();
                $updateKolomUpdatedAtTransaksi();
            } else {
                throw new Exception('Scope pembatalan tidak valid.');
            }

            return ['status' => 'CANCELLED'];
        });
    }

    /**
     * Get available shifts for dropdown.
     * Optional per-member filtering via N_HRIS_Shift_Rule.
     */
    public function getAvailableShifts(?string $kodeKaryawan = null, bool $bypassShiftRule = false): array
    {
        $query = DB::table('HRIS_Shift_Kerja as sk')
            ->join('HRIS_Shift_Kerja_Detail as sd', 'sk.ID_Shift', '=', 'sd.ID_Shift')
            ->join('HRIS_Waktu_Kerja as w', 'sd.ID_Waktu_Kerja', '=', 'w.ID_Waktu_Kerja')
            ->where('sk.Non_Shift', 'T');

        if (! $bypassShiftRule) {
            $this->applyShiftRuleFilterForMember($query, $kodeKaryawan);
        }

        return $query
            ->groupBy('sk.ID_Shift', 'sk.Nama', 'w.Jam_Masuk', 'w.Jam_Keluar', 'w.Durasi_Lembur', 'w.Flag_Longshift')
            ->orderBy('sk.Nama')
            ->select(
                'sk.ID_Shift as id',
                'sk.Nama as nama',
                'w.Jam_Masuk as jam_masuk',
                'w.Jam_Keluar as jam_keluar',
                'w.Flag_Longshift as flag_longshift',
                'w.Durasi_Lembur as durasi_longshift',
            )
            ->get()
            ->map(function ($shift) {
                $jamMasuk = $shift->jam_masuk ? substr($shift->jam_masuk, 0, 5) : '--:--';
                $jamKeluar = $shift->jam_keluar ? substr($shift->jam_keluar, 0, 5) : '--:--';

                $shift->jam_masuk = $jamMasuk;
                $shift->jam_keluar = $jamKeluar;
                $shift->display = sprintf(
                    '%s (%s - %s)%s',
                    $shift->nama,
                    $jamMasuk,
                    $jamKeluar,
                    $shift->flag_longshift
                        ? ' | Longshift : up to '.(int) $shift->durasi_longshift.' Jam'
                        : ' | Regular',
                );

                return $shift;
            })
            ->toArray();
    }

    public function getShiftsForDay($tanggal, ?string $kodeKaryawan = null, bool $bypassShiftRule = false): array
    {
        $hari = Carbon::parse($tanggal)->isoWeekday();
        $hariHRIS = $hari == 7 ? 1 : $hari + 1;

        $query = DB::table('HRIS_Shift_Kerja as sk')
            ->join('HRIS_Shift_Kerja_Detail as sd', 'sk.ID_Shift', '=', 'sd.ID_Shift')
            ->join('HRIS_Waktu_Kerja as w', 'sd.ID_Waktu_Kerja', '=', 'w.ID_Waktu_Kerja')
            ->where('sk.Non_Shift', 'T')
            ->where('sd.Hari', $hariHRIS);

        if (! $bypassShiftRule) {
            $this->applyShiftRuleFilterForMember($query, $kodeKaryawan);
        }

        return $query
            ->groupBy('sk.ID_Shift', 'sk.Nama', 'w.Jam_Masuk', 'w.Jam_Keluar', 'w.Durasi_Lembur', 'w.Flag_Longshift')
            ->orderBy('sk.Nama')
            ->select(
                'sk.ID_Shift as id',
                'sk.Nama as nama',
                'w.Jam_Masuk as jam_masuk',
                'w.Jam_Keluar as jam_keluar',
                'w.Flag_Longshift as flag_longshift',
                'w.Durasi_Lembur as durasi_longshift',
            )
            ->get()
            ->map(function ($shift) {
                $jamMasuk = $shift->jam_masuk ? substr($shift->jam_masuk, 0, 5) : '--:--';
                $jamKeluar = $shift->jam_keluar ? substr($shift->jam_keluar, 0, 5) : '--:--';

                $shift->jam_masuk = $jamMasuk;
                $shift->jam_keluar = $jamKeluar;
                $shift->display = sprintf(
                    '%s (%s - %s)%s',
                    $shift->nama,
                    $jamMasuk,
                    $jamKeluar,
                    $shift->flag_longshift
                        ? ' | Longshift : up to '.(int) $shift->durasi_longshift.' Jam'
                        : ' | Regular',
                );

                return $shift;
            })
            ->toArray();
    }

    public function getCommonAvailableShiftsForMembers(
        array $memberKodes,
        ?string $tanggal = null,
        bool $bypassShiftRule = false,
    ): array {
        $memberKodes = array_values(array_filter(array_unique($memberKodes)));
        if (empty($memberKodes)) {
            return $tanggal
                ? $this->getShiftsForDay($tanggal, null, $bypassShiftRule)
                : $this->getAvailableShifts(null, $bypassShiftRule);
        }

        $idSet = null;
        foreach ($memberKodes as $kode) {
            $rows = $tanggal
                ? $this->getShiftsForDay($tanggal, $kode, $bypassShiftRule)
                : $this->getAvailableShifts($kode, $bypassShiftRule);
            $ids = collect($rows)->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->toArray();

            if ($idSet === null) {
                $idSet = $ids;
            } else {
                $idSet = array_values(array_intersect($idSet, $ids));
            }

            if (empty($idSet)) {
                return [];
            }
        }

        $rows = $tanggal
            ? $this->getShiftsForDay($tanggal, null, $bypassShiftRule)
            : $this->getAvailableShifts(null, $bypassShiftRule);

        return collect($rows)->filter(fn ($row) => in_array((int) $row->id, $idSet, true))->values()->toArray();
    }

    public function isShiftAllowedForMemberOnDate(string $kodeKaryawan, string $tanggal, int $idShift): bool
    {
        $allowed = collect($this->getShiftsForDay($tanggal, $kodeKaryawan))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        return in_array((int) $idShift, $allowed, true);
    }

    public function validateShiftAssignmentsAgainstRules(array $assignments): array
    {
        $violations = [];
        $allowedCache = [];

        foreach ($assignments as $item) {
            $kode = $item['kode_karyawan'] ?? null;
            $tanggal = $item['tanggal'] ?? null;
            $idShift = isset($item['id_shift']) ? (int) $item['id_shift'] : null;

            if (! $kode || ! $tanggal || ! $idShift) {
                continue;
            }

            $cacheKey = $kode.'|'.$tanggal;
            if (! array_key_exists($cacheKey, $allowedCache)) {
                $allowedCache[$cacheKey] = collect($this->getShiftsForDay($tanggal, $kode))
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();
            }

            if (! in_array($idShift, $allowedCache[$cacheKey], true)) {
                $violations[] = [
                    'kode_karyawan' => $kode,
                    'tanggal' => $tanggal,
                    'id_shift' => $idShift,
                ];
            }
        }

        return $violations;
    }

    private function applyShiftRuleFilterForMember($query, ?string $kodeKaryawan): void
    {
        if (! $kodeKaryawan) {
            return;
        }

        $allowedShiftIds = $this->getAllowedShiftIdsFromFn($kodeKaryawan, '001');
        if ($allowedShiftIds === null) {
            return;
        }

        if (empty($allowedShiftIds)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('sk.ID_Shift', $allowedShiftIds);
    }

    private function getAllowedShiftIdsFromFn(string $kodeKaryawan, string $kodePerusahaan): ?array
    {
        $cacheKey = $kodePerusahaan.'|'.$kodeKaryawan;
        if (array_key_exists($cacheKey, $this->shiftRuleMemberCache)) {
            return $this->shiftRuleMemberCache[$cacheKey];
        }

        try {
            $rows = DB::select('SELECT ID_Shift FROM dbo.N_HRIS_FN_GetAvailableShift(?, ?)', [
                $kodePerusahaan,
                $kodeKaryawan,
            ]);

            $ids = collect($rows)->pluck('ID_Shift')->map(fn ($id) => (int) $id)->unique()->values()->toArray();

            return $this->shiftRuleMemberCache[$cacheKey] = $ids;
        } catch (\Throwable $e) {
            Log::warning('N_HRIS_FN_GetAvailableShift not available, fallback to legacy shift query.', [
                'kode_perusahaan' => $kodePerusahaan,
                'kode_karyawan' => $kodeKaryawan,
                'error' => $e->getMessage(),
            ]);

            return $this->shiftRuleMemberCache[$cacheKey] = null;
        }
    }

    /**
     * Get user's current week shift schedule
     */
    /**
     * Status hari sepanjang rentang, satu panggilan.
     *
     * `N_HRIS_FN_GetStatusHariRange` adalah bentuk jamak yang sudah lama ada di
     * basis data dan sudah dipakai di tempat lain pada berkas ini; ia menjawab
     * SAMA PERSIS dengan `N_HRIS_FN_GetStatusHari` yang dipanggil per hari.
     * Diperiksa lebih dulu pada 25 karyawan x 4 rentang x 7 hari: 700 cocok,
     * nol beda, nol tanggal yang hilang dari hasil rentang.
     *
     * @return array<string,string> berkunci tanggal Y-m-d
     */
    private function statusHariRentang(string $kodeKaryawan, Carbon $awal, Carbon $akhir): array
    {
        $peta = [];

        $baris = DB::select('SELECT Tanggal, StatusHari FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?)', [
            '001',
            $kodeKaryawan,
            $awal->format('Y-m-d'),
            $akhir->format('Y-m-d'),
        ]);

        foreach ($baris as $row) {
            $peta[Carbon::parse($row->Tanggal)->format('Y-m-d')] = $row->StatusHari;
        }

        return $peta;
    }

    private function kunciShiftHari($idShift, $hariHRIS): string
    {
        return ((string) $idShift).'|'.((string) $hariHRIS);
    }

    /**
     * Jadwal shift untuk seluruh pasangan (shift, hari) yang mungkin dipakai
     * satu rentang, dalam satu kueri.
     *
     * Himpunannya sengaja diambil sebagai SUPERSET — seluruh shift yang muncul
     * di data absensi maupun di transaksi pengajuan — bukan dengan menghitung
     * ulang shift mana yang akhirnya terpilih per hari. Menghitung ulang berarti
     * logika pemilihan hidup di dua tempat, dan dua tempat itu akan menyimpang
     * cepat atau lambat. Kelebihannya murah: seluruh tabel detail shift hanya
     * 114 baris.
     *
     * Pasangan (ID_Shift, Hari) terbukti unik, jadi peta ini menjawab persis
     * seperti `->first()` yang digantikannya.
     *
     * @return array<string,object>
     */
    private function petaShiftPerHari(array $select, $transaksi): array
    {
        $hari = [];
        $idShift = [];

        foreach ($select as $item) {
            $iso = Carbon::parse($item->Tanggal)->isoWeekday();
            $hari[$iso == 7 ? 1 : $iso + 1] = true;

            if (! empty($item->Id_Shift)) {
                $idShift[$item->Id_Shift] = true;
            }
        }

        foreach ($transaksi as $baris) {
            if (! empty($baris->Id_Shift_Diminta)) {
                $idShift[$baris->Id_Shift_Diminta] = true;
            }
        }

        if ($hari === [] || $idShift === []) {
            return [];
        }

        $rows = DB::table('HRIS_Shift_Kerja as sk')
            ->join('HRIS_Shift_Kerja_Detail as sd', 'sk.ID_Shift', '=', 'sd.ID_Shift')
            ->join('HRIS_Waktu_Kerja as w', 'sd.ID_Waktu_Kerja', '=', 'w.ID_Waktu_Kerja')
            ->whereIn('sk.ID_Shift', array_keys($idShift))
            ->whereIn('sd.Hari', array_keys($hari))
            ->select(
                'sk.ID_Shift',
                'sk.Nama',
                'w.Jam_Masuk',
                'w.Jam_Keluar',
                DB::raw('ISNULL(w.Durasi_Istirahat, 0) as Durasi_Istirahat'),
                DB::raw("ISNULL(w.Flag_Longshift, 'N') as Flag_Longshift"),
                DB::raw('ISNULL(w.Durasi_Lembur, 0) as Durasi_Lembur'),
                'sd.Hari as hari_kunci',
            )
            ->get();

        $peta = [];
        foreach ($rows as $row) {
            $kunci = $this->kunciShiftHari($row->ID_Shift, $row->hari_kunci);
            unset($row->hari_kunci);
            $peta[$kunci] = $row;
        }

        return $peta;
    }

    public function getWeekSchedule(string $kodeKaryawan, Carbon $startDate): array
    {
        $endDate = $startDate->copy()->addDays(6);

        $userid = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->first();
        if (! $userid) {
            return [];
        }

        // Karyawan ADA tetapi tanpa UserID_Absen bukan kasus langka: 900 dari 965
        // baris di salinan development tidak memilikinya. getAttendance() menuntut
        // string, jadi null di sini melempar TypeError dan halaman menjawab 500 —
        // terekam di production untuk karyawan LIUS lewat
        // getPersonalUpcomingSchedule. Ketiadaan jadwal absen bukan galat;
        // jawabannya sama dengan ketiadaan karyawan: tak ada jadwal.
        if (empty($userid->UserID_Absen) || trim((string) $userid->UserID_Absen) === '') {
            return [];
        }

        $useridWeb = Auth::id();

        // $query = "Exec HRIS_SP_GET_ABSEN_NEW_V3 '001', ?, ?, ?, '', ''";
        // $select = DB::select($query, [$startDate->format('Y-m-d'), $endDate->format('Y-m-d'), $userid->UserID_Absen]);
        $sourceReuqest = 'shift-request-service-get-week-schedule';
        $tglMulai = $startDate->format('Y-m-d');
        $tglSelesai = $endDate->format('Y-m-d');
        $listUserId = $userid->UserID_Absen;
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

        $select = $response['data'] ?? [];
        // log transaksi
        // log transaksi (3-tier: Header → Detail → Det)
        $transaksi = DB::table('N_HRIS_Transaksi_Shift as a')
            ->join('N_HRIS_Transaksi_Shift_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->join('N_HRIS_Transaksi_Shift_Det as c', 'b.Id', '=', 'c.Id_Detail')
            ->where('b.Kode_Karyawan', $kodeKaryawan) // Kode_Karyawan is now in Detail (Tier 2)
            ->where('a.Created_By', $useridWeb)
            ->where('a.Jenis_Transaksi', 'SELF_SHIFT')
            ->whereNull('a.Status')
            ->whereNull('a.Status')
            ->whereNull('b.Status')
            ->whereNull('c.Status')
            ->where(function ($q) {
                $q->whereNull('a.Flag_Selesai')->orWhere('a.Flag_Selesai', '!=', 'Y');
            })
            ->whereBetween('c.Tanggal_Shift', [$startDate, $endDate]) // Tanggal is now in Det (Tier 3)
            ->select(
                'a.No_Transaksi',
                'a.Flag_Selesai',
                'b.Target_Table',
                'c.Id as urut_detail', // Use Det.Id for ordering
                'c.Tanggal_Shift',
                'c.Id_Shift_Diminta',
                'c.Jenis_Pengajuan',
                'c.Needs_approval',
                'c.Flag_Applied',
                'a.Created_At',
                'c.Flag_Approval',
            )
            ->orderBy('c.Tanggal_Shift', 'asc')
            ->orderBy('c.Id', 'desc')
            ->get();

        // Preload transaksi tukar hari
        $transaksiTukarHari = DB::table('N_HRIS_Transaksi_Tukar_Hari as h')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('h.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('h.Status') // Status NULL = aktif, Y = dibatalkan
            ->where(function ($q) {
                // Rejected tukar hari must not lock/override calendar day rendering.
                $q->whereNull('d.Flag_Approval')->orWhere('d.Flag_Approval', 'Y')->orWhere('d.Flag_Approval', '');
            })
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('d.Tanggal_Bekerja', [$startDate, $endDate])->orWhereBetween('d.Tanggal_Off', [
                    $startDate,
                    $endDate,
                ]);
            })
            ->where('h.Created_By', $useridWeb)
            ->select(
                'h.No_Transaksi',
                'h.Alasan',
                'h.Created_At',
                'h.Flag_Selesai',
                'd.Tanggal_Bekerja',
                'd.Tanggal_Off',
                'd.Id_Shift_Bekerja',
                'd.Flag_Approval',
                'h.Jenis_Transaksi',
            )
            ->orderBy('h.Created_At', 'desc')
            ->get();

        $holidayMap = DB::table('HRIS_Hari_Libur')
            ->where('Kode_Perusahaan', '001')
            ->whereBetween('Tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->select('Tanggal', 'Nama_Hari_Libur')
            ->get()
            ->mapWithKeys(function ($row) {
                $dateKey = Carbon::parse($row->Tanggal)->format('Y-m-d');

                return [$dateKey => $row->Nama_Hari_Libur];
            });

        /* Status hari dan jadwal shift diambil SEKALI untuk seluruh rentang,
           bukan dua kueri per hari. Seminggu berarti 14 kueri yang biayanya
           naik lurus mengikuti panjang rentang. */
        $statusHariMap = $this->statusHariRentang($kodeKaryawan, $startDate, $endDate);
        $petaShift = $this->petaShiftPerHari($select, $transaksi);

        $schedule = collect($select)
            ->map(function ($item) use (
                $transaksi,
                $transaksiTukarHari,
                $holidayMap,
                $statusHariMap,
                $petaShift
            ) {
                $hari = Carbon::parse($item->Tanggal)->isoWeekday();
                $hariHRIS = $hari == 7 ? 1 : $hari + 1;

                $statusHariValue = $statusHariMap[Carbon::parse($item->Tanggal)->format('Y-m-d')] ?? 'WORK';

                $logTran = $transaksi
                    ->where('Tanggal_Shift', Carbon::parse($item->Tanggal)->format('Y-m-d'))
                    ->sortByDesc('Created_At')
                    ->sortByDesc('urut_detail')
                    ->first();

                // Check tukar hari untuk tanggal ini
                $tanggalFormatted = Carbon::parse($item->Tanggal)->format('Y-m-d');
                $tukarHariData = $transaksiTukarHari->first(function ($th) use ($tanggalFormatted) {
                    return $th->Tanggal_Bekerja === $tanggalFormatted || $th->Tanggal_Off === $tanggalFormatted;
                });

                // Defensive guard: do not treat rejected/cancelled swap as active calendar transaction.
                if ($tukarHariData && strtoupper((string) ($tukarHariData->Flag_Approval ?? '')) === 'T') {
                    $tukarHariData = null;
                }

                // dd($logTran);

                $usedShift = $logTran ? $logTran->Id_Shift_Diminta : $item->Id_Shift ?? null;

                $jenisShift = $logTran
                    ? $logTran->Target_Table
                    : trim(explode('|', $item->JENIS_SHIFT_DIPILIH ?? '')[0]);

                /* `source` bukan kolom tabel — ia diturunkan dari $jenisShift
                   yang bisa berbeda per hari, jadi barisnya disalin dulu sebelum
                   ditempeli. Hari ini salinan itu jaga-jaga, bukan penentu:
                   jendelanya tujuh hari sehingga tiap hari punya kunci sendiri
                   dan tak ada baris peta yang dipakai dua kali. Ia menjadi
                   penentu begitu rentangnya melebar atau sumber absensi
                   mengembalikan tanggal kembar. */
                $tempShift = $petaShift[$this->kunciShiftHari($usedShift, $hariHRIS)] ?? null;
                if ($tempShift) {
                    $tempShift = clone $tempShift;
                    $tempShift->source =
                        $jenisShift === 'SHIFT SEMENTARA' || $jenisShift === 'SEMENTARA'
                            ? 'SEMENTARA'
                            : 'PERMANEN';
                }
                // dd($tempShift);

                $ApprovalShift = $logTran ? $logTran->Flag_Approval : 'Y';
                $pendingNoTransaksi = $logTran->No_Transaksi ?? ($tukarHariData->No_Transaksi ?? null);

                // Determine if Locked: If there is a pending transaction (Flag_Selesai != Y)
                $isLocked = false;
                if ($logTran) {
                    // Assuming logTran is the latest transaction for this date which is NOT finished found by query above
                    $isLocked = true;
                }

                // Also check if tukar hari is pending for this date
                // Pending = Flag_Selesai is NULL or != 'Y'
                if ($tukarHariData && ($tukarHariData->Flag_Selesai !== 'Y' || is_null($tukarHariData->Flag_Selesai))) {
                    $isLocked = true;
                }

                $statusHariLabel = null;
                $holidayName = $holidayMap->get($tanggalFormatted);
                if (str_starts_with($statusHariValue, 'OFF') && $holidayName) {
                    $statusHariLabel = $holidayName;
                }

                // dd($logTran);
                return $this->formatShiftForCalendar(
                    Carbon::parse($item->Tanggal),
                    $tempShift,
                    $ApprovalShift,
                    $isLocked,
                    $tukarHariData,
                    $statusHariValue,
                    $statusHariLabel,
                    false,
                    false,
                    null,
                    null,
                    null,
                    $pendingNoTransaksi,
                );
            })
            ->values()
            ->toArray();

        // dd($schedule );
        return $schedule;
    }

    /**
     * Get schedule for multiple members in bulk (optimized)
     *
     * @param  array  $kodeKaryawanList  Array of Kode_Karyawan
     * @param  Carbon  $startDate  Start date
     * @param  int  $days  Number of days from start date (default 7 for week view)
     * @return array Keyed by Kode_Karyawan => [date => schedule]
     */
    public function getWeekScheduleBulk(array $kodeKaryawanList, Carbon $startDate, int $days = 7): array
    {
        if (empty($kodeKaryawanList)) {
            return [];
        }

        // Normalize member codes so lookup keys stay consistent across DB/SP sources.
        $kodeKaryawanList = collect($kodeKaryawanList)
            ->map(fn ($kode) => strtoupper(trim((string) $kode)))
            ->filter(fn ($kode) => $kode !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($kodeKaryawanList)) {
            return [];
        }

        $endDate = $startDate->copy()->addDays($days - 1);

        // 1. Fetch all UserID_Absen in ONE query
        $users = DB::table('Karyawan')
            ->whereIn('Kode_Karyawan', $kodeKaryawanList)
            ->select('Kode_Karyawan', 'UserID_Absen')
            ->get()
            ->keyBy('Kode_Karyawan');

        $requestedKodeLookup = array_fill_keys($kodeKaryawanList, true);
        $userIdToKodes = [];
        foreach ($users as $row) {
            $rowUserId = ! empty($row->UserID_Absen) ? trim((string) $row->UserID_Absen) : '';
            $rowKode = ! empty($row->Kode_Karyawan) ? strtoupper(trim((string) $row->Kode_Karyawan)) : '';

            if ($rowUserId === '' || $rowKode === '') {
                continue;
            }

            if (! isset($userIdToKodes[$rowUserId])) {
                $userIdToKodes[$rowUserId] = [];
            }

            $userIdToKodes[$rowUserId][$rowKode] = true;
        }

        $userIdToKodes = collect($userIdToKodes)->map(fn ($kodes) => array_values(array_keys($kodes)))->all();

        // Build comma-separated UserIDs for batch SP call
        $userIdList = $users->pluck('UserID_Absen')->filter()->implode(',');
        // dd($userIdList);

        if (empty($userIdList)) {
            return [];
        }

        // 2. Single SP call with ALL UserIDs at once
        // $query = "Exec HRIS_SP_GET_ABSEN_NEW_V3 '001', ?, ?, ?, '', ''";
        // $absenData = collect(DB::select($query, [$startDate->format('Y-m-d'), $endDate->format('Y-m-d'), $userIdList]));

        $sourceReuqest = 'shift-request-service-get-week-schedule';
        $tglMulai = $startDate->format('Y-m-d');
        $tglSelesai = $endDate->format('Y-m-d');
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

        $absenData = collect($response['data'] ?? []);
        // Align SP Kode_Karyawan with app keys (fallback via UserId if needed).
        $absenData = $absenData
            ->flatMap(function ($row) use ($requestedKodeLookup, $userIdToKodes) {
                $normalizedKode = isset($row->Kode_Karyawan) ? strtoupper(trim((string) $row->Kode_Karyawan)) : '';

                $rawUserId = null;
                if (isset($row->UserId)) {
                    $rawUserId = trim((string) $row->UserId);
                } elseif (isset($row->UserID_Absen)) {
                    $rawUserId = trim((string) $row->UserID_Absen);
                }

                // 1) Prioritas: jika Kode_Karyawan dari SP valid dan termasuk requested list.
                if ($normalizedKode !== '' && isset($requestedKodeLookup[$normalizedKode])) {
                    $row->Kode_Karyawan = $normalizedKode;

                    return [$row];
                }

                // 2) Fallback aman via UserID_Absen (hanya jika tidak ambigu).
                if (! empty($rawUserId) && isset($userIdToKodes[$rawUserId])) {
                    $candidateKodes = $userIdToKodes[$rawUserId];

                    // Jika hanya 1 kandidat, pakai langsung.
                    if (count($candidateKodes) === 1) {
                        $row->Kode_Karyawan = $candidateKodes[0];

                        return [$row];
                    }

                    // Jika lebih dari 1 kandidat (duplikat UserID_Absen), jangan mapping paksa.
                    // Tujuannya agar unique schedule tetap berbasis Kode_Karyawan yang valid.
                    \Log::warning('LeaderShift bulk schedule: ambiguous UserID_Absen mapping skipped', [
                        'user_id_absen' => $rawUserId,
                        'candidate_kodes' => $candidateKodes,
                        'sp_kode_karyawan' => $normalizedKode,
                    ]);

                    return [];
                }

                // 3) Last resort: keep normalized code if present.
                if ($normalizedKode !== '') {
                    $row->Kode_Karyawan = $normalizedKode;

                    return [$row];
                }

                return [];
            })
            ->filter(fn ($row) => ! empty($row->Kode_Karyawan));

        // 3. Batch fetch transactions for ALL members (3-tier)
        $transaksiRaw = DB::table('N_HRIS_Transaksi_Shift as a')
            ->join('N_HRIS_Transaksi_Shift_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->join('N_HRIS_Transaksi_Shift_Det as c', 'b.Id', '=', 'c.Id_Detail')
            ->leftJoinSub(
                DB::table('HRIS_Approval_Request')
                    ->select('No_Transaksi', DB::raw('MAX(Kode_Karyawan) as Kode_Karyawan'))
                    ->groupBy('No_Transaksi'),
                'req_ar',
                'a.No_Transaksi',
                '=',
                'req_ar.No_Transaksi',
            )
            ->leftJoin('Karyawan as req', 'req_ar.Kode_Karyawan', '=', 'req.Kode_Karyawan')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as req_jabatan',
                'req.Id_Level_Jabatan',
                '=',
                'req_jabatan.ID_Level_Jabatan',
            )
            ->whereIn('b.Kode_Karyawan', $kodeKaryawanList)
            ->whereNull('a.Status')
            ->whereNull('b.Status')
            ->whereNull('c.Status')
            ->where(function ($q) {
                $q->whereNull('a.Flag_Selesai')->orWhere('a.Flag_Selesai', '!=', 'Y');
            })
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($q2) use ($startDate, $endDate) {
                    $q2->where('b.Target_Table', 'SEMENTARA')->whereBetween('c.Tanggal_Shift', [
                        $startDate->format('Y-m-d'),
                        $endDate->format('Y-m-d'),
                    ]);
                })->orWhere(function ($q3) use ($endDate) {
                    $q3->where('b.Target_Table', 'PERMANEN')->where('c.Tanggal_Shift', '<=', $endDate->format('Y-m-d'));
                });
            })
            ->select(
                'b.Kode_Karyawan',
                'a.No_Transaksi',
                'a.Flag_Selesai',
                'b.Target_Table',
                'c.Id as urut_detail',
                'c.Tanggal_Shift',
                'c.Id_Shift_Diminta',
                'c.Jenis_Pengajuan',
                'c.Needs_approval',
                'c.Flag_Applied',
                'a.Created_At',
                'c.Flag_Approval',
                'req_ar.Kode_Karyawan as Requester_Kode',
                'req.Nama as Requester_Nama',
                'req_jabatan.nama_jabatan as Requester_Jabatan',
            )
            ->orderBy('c.Tanggal_Shift', 'asc')
            ->orderBy('c.Id', 'desc')
            ->get();

        $transaksiExpanded = collect();
        foreach ($transaksiRaw as $item) {
            $transaksiExpanded->push(clone $item);
            if ($item->Target_Table === 'PERMANEN') {
                $start = Carbon::parse($item->Tanggal_Shift);
                if ($start->lt($endDate)) {
                    $current = $start->copy()->addDay();
                    if ($current->lt($startDate)) {
                        $current = $startDate->copy();
                    }
                    while ($current->lte($endDate)) {
                        $newItem = clone $item;
                        $newItem->Tanggal_Shift = $current->format('Y-m-d');
                        $transaksiExpanded->push($newItem);
                        $current->addDay();
                    }
                }
            }
        }
        $transaksi = $transaksiExpanded->groupBy('Kode_Karyawan');

        // 4. Pre-fetch all needed shifts to avoid N+1 queries
        $allShiftIds = $absenData
            ->pluck('Id_Shift')
            ->merge($transaksi->flatten()->pluck('Id_Shift_Diminta'))
            ->filter()
            ->unique();

        $shiftDetails = DB::table('HRIS_Shift_Kerja as sk')
            ->join('HRIS_Shift_Kerja_Detail as sd', 'sk.ID_Shift', '=', 'sd.ID_Shift')
            ->join('HRIS_Waktu_Kerja as w', 'sd.ID_Waktu_Kerja', '=', 'w.ID_Waktu_Kerja')
            ->whereIn('sk.ID_Shift', $allShiftIds)
            ->select(
                'sk.ID_Shift',
                'sk.Nama',
                'sd.Hari',
                'w.Jam_Masuk',
                'w.Jam_Keluar',
                DB::raw('ISNULL(w.Durasi_Istirahat, 0) as Durasi_Istirahat'),
                DB::raw("ISNULL(w.Flag_Longshift, 'N') as Flag_Longshift"),
                DB::raw('ISNULL(w.Durasi_Lembur, 0) as Durasi_Lembur'),
            )
            ->get();

        $shiftDetailCache = $shiftDetails->mapWithKeys(function ($row) {
            $key = $row->ID_Shift.'-'.$row->Hari;

            return [$key => $row];
        });

        $holidayMap = DB::table('HRIS_Hari_Libur')
            ->where('Kode_Perusahaan', '001')
            ->whereBetween('Tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->select('Tanggal', 'Nama_Hari_Libur')
            ->get()
            ->mapWithKeys(function ($row) {
                $dateKey = Carbon::parse($row->Tanggal)->format('Y-m-d');

                return [$dateKey => $row->Nama_Hari_Libur];
            });

        $absenDataByKode = $absenData->groupBy(function ($row) {
            return strtoupper(trim((string) $row->Kode_Karyawan));
        });

        // 4b. Batch fetch pending status hari transactions for ALL members
        $pendingStatusHariRaw = DB::table('N_HRIS_Transaksi_Status_Hari as a')
            ->join('N_HRIS_Transaksi_Status_Hari_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->join('N_HRIS_Transaksi_Status_Hari_Det as c', 'b.Id', '=', 'c.Id_Detail')
            ->leftJoinSub(
                DB::table('HRIS_Approval_Request')
                    ->select('No_Transaksi', DB::raw('MAX(Kode_Karyawan) as Kode_Karyawan'))
                    ->groupBy('No_Transaksi'),
                'req_ar',
                'a.No_Transaksi',
                '=',
                'req_ar.No_Transaksi',
            )
            ->leftJoin('Karyawan as req', 'req_ar.Kode_Karyawan', '=', 'req.Kode_Karyawan')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as req_jabatan',
                'req.Id_Level_Jabatan',
                '=',
                'req_jabatan.ID_Level_Jabatan',
            )
            ->whereIn('b.Kode_Karyawan', $kodeKaryawanList)
            ->whereNull('a.Status')
            ->whereNull('b.Status')
            ->whereNull('c.Status')
            ->where(function ($q) {
                $q->whereNull('a.Flag_Selesai')->orWhere('a.Flag_Selesai', '!=', 'Y');
            })
            ->where('c.Needs_Approval', 'Y')
            ->whereNull('c.Flag_Approval')
            ->where(function ($q) {
                $q->whereNull('c.Flag_Applied')->orWhere('c.Flag_Applied', '!=', 'Y');
            })
            ->where('c.Tanggal', '<=', $endDate->format('Y-m-d'))
            ->select(
                'b.Kode_Karyawan',
                'a.No_Transaksi',
                'c.Tanggal',
                'c.Status_Hari',
                'c.Jenis_Pengajuan',
                'c.Flag_Approval as SH_Flag_Approval',
                'req_ar.Kode_Karyawan as Requester_Kode',
                'req.Nama as Requester_Nama',
                'req_jabatan.nama_jabatan as Requester_Jabatan',
            )
            ->orderBy('c.Tanggal', 'asc')
            ->orderBy('c.Id', 'desc')
            ->get();

        $pendingStatusHariExpanded = collect();
        foreach ($pendingStatusHariRaw as $item) {
            $pendingStatusHariExpanded->push(clone $item);
            // Treat STATUS_HARI as PERMANEN if Target_Table is PERMANEN or if the UI implies so.
            // But wait, some status hari don't have Target_Table in b? Let's assume PERMANEN if it exists.
            if (($item->Target_Table ?? '') === 'PERMANEN') {
                $start = Carbon::parse($item->Tanggal);
                if ($start->lt($endDate)) {
                    $current = $start->copy()->addDay();
                    if ($current->lt($startDate)) {
                        $current = $startDate->copy();
                    }
                    while ($current->lte($endDate)) {
                        $newItem = clone $item;
                        $newItem->Tanggal = $current->format('Y-m-d');
                        $pendingStatusHariExpanded->push($newItem);
                        $current->addDay();
                    }
                }
            }
        }
        $pendingStatusHari = $pendingStatusHariExpanded->groupBy('Kode_Karyawan');

        // 5. Process each member's schedule
        $result = [];
        foreach ($kodeKaryawanList as $kodeKaryawan) {
            // SP returns Kode_Karyawan directly - filter by it
            $memberAbsen = $absenDataByKode->get($kodeKaryawan, collect());
            $memberTransaksi = $transaksi->get($kodeKaryawan, collect());
            $memberTransaksiByDate = $memberTransaksi->groupBy('Tanggal_Shift');

            // Pending status hari by date
            $memberPendingSH = $pendingStatusHari->get($kodeKaryawan, collect());
            $memberPendingSHByDate = $memberPendingSH->groupBy(function ($row) {
                return Carbon::parse($row->Tanggal)->format('Y-m-d');
            });

            $schedule = $memberAbsen
                ->map(function ($item) use (
                    $memberTransaksiByDate,
                    $memberPendingSHByDate,
                    $shiftDetailCache,
                    $holidayMap,
                ) {
                    $tanggal = Carbon::parse($item->Tanggal);
                    $dateStr = $tanggal->format('Y-m-d');

                    $hari = $tanggal->isoWeekday();
                    $hariHRIS = $hari == 7 ? 1 : $hari + 1;

                    $logTran = $memberTransaksiByDate->get($dateStr)?->first();

                    $usedShift = $logTran ? $logTran->Id_Shift_Diminta : $item->Id_Shift;
                    $jenisShift = $logTran ? $logTran->Target_Table : trim(explode('|', $item->JENIS_SHIFT_DIPILIH)[0]);
                    $approvalShift = $logTran ? $logTran->Flag_Approval : 'Y';
                    $isLocked = (bool) $logTran;

                    // Determine if shift change is pending approval
                    $isPendingShift = false;
                    $pendingRequesterKode = null;
                    $pendingRequesterNama = null;
                    $pendingRequesterJabatan = null;
                    $pendingNoTransaksi = null;
                    if ($logTran && $logTran->Needs_approval === 'Y' && ($logTran->Flag_Applied ?? '') !== 'Y') {
                        $isPendingShift = true;
                        $pendingNoTransaksi = $logTran->No_Transaksi ?? null;
                        $pendingRequesterKode = $logTran->Requester_Kode ?? null;
                        $pendingRequesterNama = $logTran->Requester_Nama ?? null;
                        $pendingRequesterJabatan = $logTran->Requester_Jabatan ?? null;
                    }

                    $shiftKey = $usedShift.'-'.$hariHRIS;
                    $shiftRow = $shiftDetailCache->get($shiftKey);

                    $tempShift = null;
                    if ($shiftRow) {
                        $tempShift = (object) [
                            'ID_Shift' => $shiftRow->ID_Shift,
                            'Nama' => $shiftRow->Nama,
                            'Jam_Masuk' => $shiftRow->Jam_Masuk,
                            'Jam_Keluar' => $shiftRow->Jam_Keluar,
                            'Durasi_Istirahat' => (float) ($shiftRow->Durasi_Istirahat ?? 0),
                            'Flag_Longshift' => $shiftRow->Flag_Longshift ?? 'N',
                            'Durasi_Lembur' => $shiftRow->Durasi_Lembur ?? 0,
                            'source' => $jenisShift === 'SHIFT SEMENTARA' || $jenisShift === 'SEMENTARA'
                                    ? 'SEMENTARA'
                                    : 'PERMANEN',
                        ];
                    }

                    $statusHariValue = $item->StatusHari ?? 'WORK';
                    $statusHariLabel = null;
                    $holidayName = $holidayMap->get($dateStr);
                    if (str_starts_with($statusHariValue, 'OFF') && $holidayName) {
                        $statusHariLabel = $holidayName;
                    }

                    // Check for pending status hari transaction
                    $pendingSH = $memberPendingSHByDate->get($dateStr)?->first();
                    $isPendingStatusHari = false;
                    if ($pendingSH) {
                        $isPendingStatusHari = true;
                        $pendingNoTransaksi = $pendingSH->No_Transaksi ?? $pendingNoTransaksi;
                        // Override status_hari with the requested value
                        $statusHariValue = $pendingSH->Status_Hari;
                        $pendingRequesterKode = $pendingSH->Requester_Kode ?? $pendingRequesterKode;
                        $pendingRequesterNama = $pendingSH->Requester_Nama ?? $pendingRequesterNama;
                        $pendingRequesterJabatan = $pendingSH->Requester_Jabatan ?? $pendingRequesterJabatan;
                    }

                    return $this->formatShiftForCalendar(
                        $tanggal,
                        $tempShift,
                        $approvalShift,
                        $isLocked,
                        null,
                        $statusHariValue,
                        $statusHariLabel,
                        $isPendingShift,
                        $isPendingStatusHari,
                        $pendingRequesterKode,
                        $pendingRequesterNama,
                        $pendingRequesterJabatan,
                        $pendingNoTransaksi,
                    );
                })
                ->keyBy('date')
                ->toArray();

            $result[$kodeKaryawan] = $schedule;
        }

        return $result;
    }

    protected function normalizeTimeToHm(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        $value = trim($time);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return substr($value, 0, 5);
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return null;
    }

    protected function sanitizeDurasiLembur($value): float
    {
        // Convert NULL atau non-numeric ke 0
        $numeric = is_numeric($value) ? (float) $value : 0.0;

        // Barrier: Durasi_Lembur minimum 0, tidak boleh negatif
        return max(0.0, $numeric);
    }

    protected function calculateShiftDurationHours(
        ?string $jamMasuk,
        ?string $jamKeluar,
        $durasiIstirahat = 0,
        ?string $flagLongshift = 'N',
        $durasiLembur = 0,
    ): float {
        $start = $this->normalizeTimeToHm($jamMasuk);
        $end = $this->normalizeTimeToHm($jamKeluar);

        if (! $start || ! $end) {
            return 0.0;
        }

        $startMinutes = (int) substr($start, 0, 2) * 60 + (int) substr($start, 3, 2);
        $endMinutes = (int) substr($end, 0, 2) * 60 + (int) substr($end, 3, 2);

        if ($endMinutes <= $startMinutes) {
            $endMinutes += 24 * 60;
        }

        $durasiKerja = ($endMinutes - $startMinutes) / 60;
        $durasiIstirahatNumeric = is_numeric($durasiIstirahat) ? (float) $durasiIstirahat : 0.0;
        $finalDuration = max(0.0, $durasiKerja - max(0.0, $durasiIstirahatNumeric));

        // Jika Flag_Longshift = 'Y', kurangi Durasi_Lembur dari final duration
        if (strtoupper((string) $flagLongshift) === 'Y') {
            // Sanitize: barrier Durasi_Lembur >= 0, convert NULL ke 0
            $durasiLemburSafe = $this->sanitizeDurasiLembur($durasiLembur);
            $finalDuration = max(0.0, $finalDuration - $durasiLemburSafe);
        }

        return round($finalDuration, 2);
    }

    protected function isEquivalentEightHoursStatus(?string $statusHari): bool
    {
        if (! $statusHari) {
            return false;
        }

        $normalized = strtoupper(str_replace(' ', '_', $statusHari));

        if (str_contains($normalized, 'HARI_LIBUR')) {
            return true;
        }

        return str_contains($normalized, 'CUTI') ||
            str_contains($normalized, 'IZIN') ||
            str_contains($normalized, 'SAKIT') ||
            str_starts_with($normalized, 'CT') ||
            str_starts_with($normalized, 'IS');
    }

    protected function calculateScheduleHours(array $schedule, array $forceOffDates = []): float
    {
        $total = 0.0;

        foreach ($schedule as $date => $item) {
            if (isset($forceOffDates[$date])) {
                continue;
            }

            $statusHari = strtoupper((string) ($item['status_hari'] ?? 'WORK'));

            if (str_starts_with($statusHari, 'WORK')) {
                $shift = $item['shift'] ?? null;
                if (! is_array($shift)) {
                    continue;
                }

                $total += $this->calculateShiftDurationHours(
                    $shift['jam_masuk'] ?? null,
                    $shift['jam_keluar'] ?? null,
                    $shift['durasi_istirahat'] ?? 0,
                    $shift['flag_longshift'] ?? 'N',
                    $shift['durasi_lembur'] ?? 0,
                );

                continue;
            }

            if ($this->isEquivalentEightHoursStatus($statusHari)) {
                $total += 8.0;
            }
        }

        return round($total, 2);
    }

    public function buildWeeklyHourSummary(array $schedules, float $targetHours = 0.0): array
    {
        $summary = [];

        foreach ($schedules as $kodeKaryawan => $schedule) {
            // dd($schedule);
            $totalHours = $this->calculateScheduleHours((array) $schedule);
            $delta = round($totalHours - $targetHours, 2);

            $status = 'MEET';
            if ($totalHours < $targetHours) {
                $status = 'UNDER';
            } elseif ($totalHours > $targetHours) {
                $status = 'OVER';
            }

            $summary[$kodeKaryawan] = [
                'total_hours' => $totalHours,
                'target_hours' => $targetHours,
                'delta_hours' => $delta,
                'status' => $status,
            ];
        }

        return $summary;
    }

    public function validateMinimumWeeklyHoursAfterOffday(
        array $memberKodes,
        array $offdayDates,
        float $minimumHours = 40.0,
    ): array {
        $memberKodes = array_values(array_filter(array_unique($memberKodes)));
        $offdayDates = array_values(
            array_filter(
                array_unique(
                    array_map(function ($date) {
                        return Carbon::parse($date)->format('Y-m-d');
                    }, $offdayDates),
                ),
            ),
        );

        if (empty($memberKodes) || empty($offdayDates)) {
            return [];
        }

        $weekStarts = collect($offdayDates)
            ->map(function ($date) {
                return Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
            })
            ->unique()
            ->values()
            ->toArray();

        $violations = [];
        foreach ($weekStarts as $weekStart) {
            $weekStartDate = Carbon::parse($weekStart);
            $weekEndDate = $weekStartDate->copy()->addDays(6);
            $scheduleByMember = $this->getWeekScheduleBulk($memberKodes, $weekStartDate, 7);

            $forcedOffDates = collect($offdayDates)
                ->filter(function ($date) use ($weekStartDate, $weekEndDate) {
                    $parsed = Carbon::parse($date);

                    return $parsed->betweenIncluded($weekStartDate, $weekEndDate);
                })
                ->values()
                ->toArray();

            if (empty($forcedOffDates)) {
                continue;
            }

            $forcedOffMap = array_fill_keys($forcedOffDates, true);

            foreach ($memberKodes as $memberKode) {
                $schedule = (array) ($scheduleByMember[$memberKode] ?? []);
                $currentHours = $this->calculateScheduleHours($schedule);
                $projectedHours = $this->calculateScheduleHours($schedule, $forcedOffMap);

                if ($projectedHours < $minimumHours) {
                    $violations[] = [
                        'member_kode' => $memberKode,
                        'week_start' => $weekStartDate->format('Y-m-d'),
                        'week_end' => $weekEndDate->format('Y-m-d'),
                        'current_hours' => $currentHours,
                        'projected_hours' => $projectedHours,
                        'minimum_hours' => $minimumHours,
                        'deficit_hours' => round($minimumHours - $projectedHours, 2),
                        'offday_dates' => $forcedOffDates,
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * Create offday records for leader (single, range, or permanent)
     * Insert ke N_HRIS_Status_Hari_Karyawan (master tunggal status hari)
     */
    public function createOffdayForMembers(
        string $leaderKode,
        array $members,
        string $mode,
        string $startDate,
        ?string $endDate,
        ?int $hari,
        string $alasan = '',
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $useridWeb = DB::table('Karyawan')->where('Kode_Karyawan', $leaderKode)->value('UserID_Absen');

        if ($mode === 'PERMANEN') {
            $end = null; // open-ended, dihandle resolver SQL via (Tgl_Selesai IS NULL OR @Tgl <= Tgl_Selesai)
        } else {
            $end = $endDate ? Carbon::parse($endDate)->startOfDay() : $start->copy();
        }

        if ($end !== null && $end->lt($start)) {
            throw new Exception('Tanggal selesai tidak boleh lebih kecil dari tanggal mulai.');
        }

        $hariValue = $hari;
        if ($mode === 'PERMANEN' && empty($hariValue)) {
            $hariValue = $this->mapDayToHris($start);
        }

        $holidayDates = [];
        if ($mode !== 'PERMANEN') {
            $holidayDates = $this->getHolidayDates($start, $end);
            if (! empty($holidayDates)) {
                throw new Exception('Tanggal libur nasional tidak bisa diubah.');
            }
        }

        $rows = [];
        foreach ($members as $kodeKaryawan) {
            $rows[] = [
                'Kode_Perusahaan' => '001',
                'Kode_Karyawan' => $kodeKaryawan,
                'Status_Hari' => 'OFF',
                'Hari' => $hariValue,
                'Tgl_Mulai' => $start->format('Y-m-d'),
                'Tgl_Selesai' => $end ? $end->format('Y-m-d') : null,
                'Flag_Aktif' => 'Y',
                'No_Transaksi_Reference' => null,
                'Keterangan' => $alasan,
                'Created_At' => now(),
                'Created_By' => $useridWeb,
            ];
        }

        if (! empty($rows)) {
            DB::table('N_HRIS_Status_Hari_Karyawan')->insert($rows);
        }

        return [
            'mode' => $mode,
            'inserted' => count($rows),
            'holiday_dates' => $holidayDates,
        ];
    }

    // =========================================================================
    // STATUS HARI OVERLAY HELPERS
    // =========================================================================

    /**
     * CHANGE 2: Helper untuk insert WORK overlay saat shift diapply ke hari yang OFF.
     * Dipisahkan dari applyShiftDirect agar bisa dipakai ulang (DRY).
     *
     * Logic: insert record WORK 1 hari (Tgl_Mulai = Tgl_Selesai = $tanggal)
     * sehingga resolver fn_GetStatusHari mengembalikan WORK untuk tanggal tersebut
     * karena ORDER BY Tgl_Mulai DESC (WORK lebih spesifik dari OFF range panjang).
     */
    private function insertWorkOverlay(
        string $kodePerusahaan,
        string $kodeKaryawan,
        string $tanggal,
        string $userId,
        ?string $noTransaksiReference = null,
    ): void {
        DB::table('N_HRIS_Status_Hari_Karyawan')->insert([
            'Kode_Perusahaan' => $kodePerusahaan,
            'Kode_Karyawan' => $kodeKaryawan,
            'Status_Hari' => 'WORK',
            'Hari' => null,
            'Tgl_Mulai' => $tanggal,
            'Tgl_Selesai' => $tanggal,
            'Flag_Aktif' => 'Y',
            'No_Transaksi_Reference' => $noTransaksiReference,
            'Keterangan' => 'Auto override: shift applied on OFF day',
            'Created_At' => now(),
            'Created_By' => $userId,
        ]);
    }

    private function pastikanTargetShiftValid(string $targetTable): void
    {
        if (! in_array($targetTable, ['SEMENTARA', 'PERMANEN'], true)) {
            throw new InvalidArgumentException('Target shift harus SEMENTARA atau PERMANEN.');
        }
    }

    /**
     * Akhiri custom OFF yang masih berlaku ketika shift permanen mulai efektif.
     *
     * OFF satu hari yang memang berakhir pada tanggal efektif tidak perlu diubah.
     * WORK overlay dan transaksi tukar hari tidak termasuk scope penutupan ini.
     */
    private function akhiriCustomOffYangTumpangTindih(
        string $kodePerusahaan,
        string $kodeKaryawan,
        string $tanggalEfektif,
        ?string $userId,
    ): int {
        $records = DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('Kode_Perusahaan', $kodePerusahaan)
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Hari', 'OFF')
            ->where('No_Transaksi_Reference', 'not like', 'TH%')
            ->where('Tgl_Mulai', '<=', $tanggalEfektif)
            ->where(function ($query) use ($tanggalEfektif) {
                $query->whereNull('Tgl_Selesai')->orWhere('Tgl_Selesai', '>', $tanggalEfektif);
            })
            ->select([
                'Id',
                'Kode_Perusahaan',
                'Kode_Karyawan',
                'Status_Hari',
                'Hari',
                'Tgl_Mulai',
                'No_Transaksi_Reference',
                'Keterangan',
                'Created_At',
                'Created_By',
            ])
            ->lockForUpdate()
            ->get();

        if ($records->isEmpty()) {
            return 0;
        }

        $mutatedAt = FormatTanggalHelper::getCurrentTime('shift-request-status-hari')
            ->format('Y-m-d H:i:s.v');
        $ids = $records->pluck('Id')->all();
        $deactivated = DB::table('N_HRIS_Status_Hari_Karyawan')
            ->whereIn('Id', $ids)
            ->where('Flag_Aktif', 'Y')
            ->update([
                'Flag_Aktif' => 'T',
                'Updated_At' => $mutatedAt,
                'Updated_By' => $userId,
            ]);

        if ($deactivated !== $records->count()) {
            throw new \RuntimeException('Status hari berubah saat shift permanen diterapkan.');
        }

        $tanggalEfektifCarbon = Carbon::parse($tanggalEfektif)->startOfDay();
        $replacementRows = $records->map(function (object $record) use (
            $tanggalEfektif,
            $tanggalEfektifCarbon,
            $mutatedAt,
            $userId,
        ): array {
            $tanggalMulai = Carbon::parse($record->Tgl_Mulai)->startOfDay();
            $tanggalSelesai = $tanggalMulai->lt($tanggalEfektifCarbon)
                ? $tanggalEfektifCarbon->copy()->subDay()->toDateString()
                : $tanggalEfektif;

            return [
                'Kode_Perusahaan' => $record->Kode_Perusahaan,
                'Kode_Karyawan' => $record->Kode_Karyawan,
                'Status_Hari' => $record->Status_Hari,
                'Hari' => $record->Hari,
                'Tgl_Mulai' => Carbon::parse($record->Tgl_Mulai)->toDateString(),
                'Tgl_Selesai' => $tanggalSelesai,
                'Flag_Aktif' => 'Y',
                'No_Transaksi_Reference' => $record->No_Transaksi_Reference,
                'Keterangan' => $record->Keterangan,
                // Jangan mengubah tie-breaker resolver N_HRIS_FN_GetStatusHari.
                'Created_At' => $record->Created_At,
                'Created_By' => $record->Created_By,
                'Updated_At' => $mutatedAt,
                'Updated_By' => $userId,
            ];
        })->all();

        PenyisipanBerbatasParameter::sisipkan('N_HRIS_Status_Hari_Karyawan', $replacementRows);

        return $deactivated;
    }

    /**
     * CHANGE 4: Ambil override aktif yang berlaku untuk karyawan pada tanggal tertentu.
     * Meniru logika resolver fn_GetStatusHari layer 1.
     * Digunakan oleh quick modal di LeaderShift untuk menampilkan tombol "Akhiri Override".
     *
     * @param  string  $tanggal  Format: Y-m-d
     * @return object|null Record N_HRIS_Status_Hari_Karyawan yang aktif, atau null jika tidak ada.
     */
    public function getActiveOverrideForDate(string $kodeKaryawan, string $tanggal): ?object
    {
        return DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('Kode_Perusahaan', '001')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Flag_Aktif', 'Y')
            ->where('Tgl_Mulai', '<=', $tanggal)
            ->where(function ($q) use ($tanggal) {
                $q->whereNull('Tgl_Selesai')->orWhere('Tgl_Selesai', '>=', $tanggal);
            })
            ->where(function ($q) use ($tanggal) {
                $q->whereNull('Hari')->orWhereRaw('Hari = DATEPART(WEEKDAY, ?)', [$tanggal]);
            })
            ->orderBy('Tgl_Mulai', 'desc')
            ->orderBy('Created_At', 'desc')
            ->orderBy('Id', 'desc')
            ->select([
                'Id',
                'Status_Hari',
                'Tgl_Mulai',
                'Tgl_Selesai',
                'Hari',
                'Keterangan',
                'Created_At',
                'Created_By',
            ])
            ->first();
    }

    /**
     * Akhiri override aktif dengan menonaktifkan versi lama dan membuat versi pengganti.
     * Dipanggil dari UI "Akhiri Override" dan flow status-hari permanen.
     * Created_At/By dipertahankan supaya urutan resolver tidak berubah; waktu dan actor
     * perubahan dicatat di Updated_At/By pada kedua versi.
     *
     * @param  int  $idStatusHari  PK dari N_HRIS_Status_Hari_Karyawan
     * @param  string  $stopDate  Tanggal akhir baru (Y-m-d). Record masih cover tanggal ini.
     */
    public function stopActiveOverride(int $idStatusHari, string $stopDate): void
    {
        $userId = Auth::id();

        DB::transaction(function () use ($idStatusHari, $stopDate, $userId): void {
            $record = DB::table('N_HRIS_Status_Hari_Karyawan')
                ->where('Id', $idStatusHari)
                ->where('Flag_Aktif', 'Y')
                ->lockForUpdate()
                ->first();

            if (! $record) {
                throw new \Exception('Override tidak ditemukan atau sudah tidak aktif.');
            }

            $tanggalMulai = Carbon::parse($record->Tgl_Mulai)->toDateString();
            if ($stopDate < $tanggalMulai) {
                throw new \Exception(
                    "Tanggal akhir tidak boleh sebelum tanggal mulai override ({$tanggalMulai}) {$stopDate} di {$record->Id}.",
                );
            }

            $mutatedAt = FormatTanggalHelper::getCurrentTime('shift-request-status-hari')
                ->format('Y-m-d H:i:s.v');
            $deactivated = DB::table('N_HRIS_Status_Hari_Karyawan')
                ->where('Id', $idStatusHari)
                ->where('Flag_Aktif', 'Y')
                ->update([
                    'Flag_Aktif' => 'T',
                    'Updated_At' => $mutatedAt,
                    'Updated_By' => $userId,
                ]);

            if ($deactivated !== 1) {
                throw new \RuntimeException('Override berubah saat proses pengakhiran.');
            }

            DB::table('N_HRIS_Status_Hari_Karyawan')->insert([
                'Kode_Perusahaan' => $record->Kode_Perusahaan,
                'Kode_Karyawan' => $record->Kode_Karyawan,
                'Status_Hari' => $record->Status_Hari,
                'Hari' => $record->Hari,
                'Tgl_Mulai' => $tanggalMulai,
                'Tgl_Selesai' => $stopDate,
                'Flag_Aktif' => 'Y',
                'No_Transaksi_Reference' => $record->No_Transaksi_Reference,
                'Keterangan' => $record->Keterangan,
                'Created_At' => $record->Created_At,
                'Created_By' => $record->Created_By,
                'Updated_At' => $mutatedAt,
                'Updated_By' => $userId,
            ]);
        });
    }

    protected function mapDayToHris(Carbon $date): int
    {
        return $date->dayOfWeek + 1;
    }

    protected function getHolidayDates(Carbon $start, Carbon $end): array
    {
        $dates = DB::table('HRIS_Hari_Libur')
            ->whereBetween('Tanggal', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->pluck('Tanggal')
            ->map(function ($tanggal) {
                return Carbon::parse($tanggal)->format('Y-m-d');
            })
            ->toArray();

        return array_values(array_unique($dates));
    }

    /**
     * Format shift for calendar display
     */
    // protected function formatShiftForCalendar(Carbon $date, object $shift): array
    // {
    //     return [
    //         'date' => $date->format('Y-m-d'),
    //         'day_name' => $date->translatedFormat('l'),
    //         'day_short' => $date->format('d'),
    //         'month_short' => $date->translatedFormat('M'),
    //         'is_today' => $date->isToday(),
    //         'shift' => [
    //             'id' => $shift->ID_Shift,
    //             'nama' => $shift->Nama,
    //             'jam_masuk' => substr($shift->Jam_Masuk, 0, 5),
    //             'jam_keluar' => substr($shift->Jam_Keluar, 0, 5),
    //         ],
    //         'source' => $shift->source,
    //     ];
    // }
    protected function formatShiftForCalendar(
        Carbon $date,
        ?object $shift,
        ?string $approvalShift,
        bool $isLocked = false,
        ?object $tukarHariData = null,
        ?string $statusHari = 'WORK',
        ?string $statusHariLabel = null,
        bool $isPendingShift = false,
        bool $isPendingStatusHari = false,
        ?string $pendingRequesterKode = null,
        ?string $pendingRequesterNama = null,
        ?string $pendingRequesterJabatan = null,
        ?string $pendingNoTransaksi = null,
    ): array {
        $isDayOff = str_starts_with($statusHari, 'OFF');
        $flagLongshift = $shift->Flag_Longshift ?? 'N';
        $durasiLembur = $this->sanitizeDurasiLembur($shift->Durasi_Lembur ?? 0);

        $result = [
            'date' => $date->format('Y-m-d'),
            'day_name' => $date->translatedFormat('l'),
            'day_short' => $date->format('d'),
            'month_short' => $date->translatedFormat('M'),
            'is_today' => $date->isToday(),
            'is_locked' => $isLocked,
            'status_hari' => $statusHari,
            'status_hari_label' => $statusHariLabel,
            'is_day_off' => $isDayOff,
            'is_pending_shift' => $isPendingShift,
            'pending_status_hari' => $isPendingStatusHari,
            'pending_requester_kode' => $pendingRequesterKode,
            'pending_requester_nama' => $pendingRequesterNama,
            'pending_requester_jabatan' => $pendingRequesterJabatan,
            'pending_no_transaksi' => $pendingNoTransaksi,
            'flag_longshift' => $flagLongshift,
            'durasi_lembur' => $durasiLembur,

            'shift' => $shift
                ? [
                    'id' => $shift->ID_Shift,
                    'nama' => $shift->Nama,
                    'jam_masuk' => substr($shift->Jam_Masuk, 0, 5),
                    'jam_keluar' => substr($shift->Jam_Keluar, 0, 5),
                    'durasi_istirahat' => (float) ($shift->Durasi_Istirahat ?? 0),
                    'flag_longshift' => $flagLongshift,
                    'durasi_lembur' => $durasiLembur,
                ]
                : null,
            'Flag_Approval' => $approvalShift ? $approvalShift : 'N',
            'source' => $shift->source ?? null,
        ];

        // DEBUG: Log shift object
        // if ($shift && isset($shift->Flag_Longshift)) {
        //     \Log::debug('formatShiftForCalendar - Shift Object', [
        //         'shift_id' => $shift->ID_Shift ?? null,
        //         'shift_nama' => $shift->Nama ?? null,
        //         'raw_Flag_Longshift_value' => $shift->Flag_Longshift,
        //         'raw_Flag_Longshift_type' => gettype($shift->Flag_Longshift),
        //     ]);
        // }

        // Add tukar hari information ONLY if completed (Flag_Selesai == 'Y')
        // For pending tukar hari (Flag_Selesai NULL or != Y), just mark as is_locked (already done above)
        if ($tukarHariData && $tukarHariData->Flag_Selesai === 'Y') {
            $tanggalFormatted = $date->format('Y-m-d');
            $isTanggalBekerja = $tukarHariData->Tanggal_Bekerja === $tanggalFormatted;
            $isTanggalOff = $tukarHariData->Tanggal_Off === $tanggalFormatted;

            $result['tukar_hari'] = [
                'no_transaksi' => $tukarHariData->No_Transaksi,
                'type' => $isTanggalBekerja ? 'working' : 'off',
                'tanggal_bekerja' => $tukarHariData->Tanggal_Bekerja,
                'tanggal_off' => $tukarHariData->Tanggal_Off,
                'id_shift_bekerja' => $tukarHariData->Id_Shift_Bekerja,
                'flag_approval' => $tukarHariData->Flag_Approval,
                'alasan' => $tukarHariData->Alasan,
            ];
        } else {
            $result['tukar_hari'] = null;
        }

        return $result;
    }

    /**
     * Get user's current shift for a specific date
     */
    public function getUserShiftForDate(?string $kodeKaryawan, Carbon $date)
    {
        if (! $kodeKaryawan) {
            return null;
        }

        $userid = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->first();
        if (! $userid) {
            return null;
        }

        // Lubang yang sama dengan getWeekSchedule(): karyawan ada, UserID_Absen
        // tidak, getAttendance() menuntut string. Lihat catatan di sana.
        if (empty($userid->UserID_Absen) || trim((string) $userid->UserID_Absen) === '') {
            return null;
        }

        // $query = "Exec HRIS_SP_GET_ABSEN_NEW_V3 '001', ?, ?, ?, '', ''";
        // $select = DB::select($query, [$date->format('Y-m-d'), $date->format('Y-m-d'), $userid->UserID_Absen]);

        $sourceReuqest = 'shift-request-service-getUserShiftForDate';
        $tglMulai = $date->format('Y-m-d');
        $tglSelesai = $date->format('Y-m-d');
        $listUserId = $userid->UserID_Absen;
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

        $select = $response['data'] ?? [];

        if (empty($select)) {
            return null;
        }

        return $select[0]->Id_Shift;
    }

    /**
     * Shift beberapa karyawan untuk beberapa tanggal — SATU eksekusi.
     *
     * getUserShiftForDate() menjawab untuk SATU orang pada SATU tanggal, dan
     * tiap panggilannya berharga tiga kueri: satu `SELECT *` di Karyawan, satu
     * eksekusi N_HRIS_SP_GET_ABSEN_WEB_WRAPPER, satu lagi di dalamnya. Dipakai
     * dari perulangan anggota x tanggal, biayanya berlipat: 40 anggota x 30
     * tanggal = 1.200 panggilan, 3.600 kueri, terukur ~7 menit.
     *
     * Padahal SP-nya SUDAH menerima daftar user dan RENTANG tanggal, dan tiap
     * barisnya sudah membawa Kode_Karyawan, Tanggal, dan Id_Shift. Terukur pada
     * 3 orang x 3 hari: 27 kueri / ~3 detik jadi 1 kueri / 0,01 detik.
     *
     * Rentangnya dari tanggal paling awal sampai paling akhir yang diminta,
     * lalu hasilnya disaring kembali ke pasangan yang benar-benar ditanyakan —
     * SP menagih rentang, bukan daftar tanggal.
     *
     * @param  list<string>  $kodeKaryawan
     * @param  list<string>  $tanggal  format bebas, dinormalkan ke Y-m-d
     * @return array<string,array<string,mixed>> [kode][Y-m-d] => Id_Shift|null
     */
    public function getUserShiftsForDates(array $kodeKaryawan, array $tanggal): array
    {
        $kode = array_values(array_unique(array_filter(array_map('strval', $kodeKaryawan))));
        $hari = [];
        foreach ($tanggal as $t) {
            if ($t === null || $t === '') {
                continue;
            }
            $hari[Carbon::parse($t)->format('Y-m-d')] = true;
        }
        $hari = array_keys($hari);

        if ($kode === [] || $hari === []) {
            return [];
        }

        /* Baku null untuk SETIAP pasangan yang ditanyakan.
           Bentuk lama mengembalikan null saat karyawannya tak ditemukan atau
           absensinya kosong; peta ini harus menjawab sama, bukan menghilangkan
           kuncinya — pemanggil membedakan "tak ada shift" dari "tak ditanya". */
        $peta = [];
        foreach ($kode as $k) {
            foreach ($hari as $h) {
                $peta[$k][$h] = null;
            }
        }

        $karyawan = DB::table('Karyawan')
            ->whereIn('Kode_Karyawan', $kode)
            ->whereNotNull('UserID_Absen')
            ->pluck('UserID_Absen')
            ->filter()
            ->unique()
            ->values();

        if ($karyawan->isEmpty()) {
            return $peta;
        }

        sort($hari);
        $sumber = 'shift-request-service-getUserShiftsForDates';

        $response = $this->attendanceService->getAttendance(
            kodePerusahaan: '001',
            tglAwal: $hari[0],
            tglAkhir: $hari[count($hari) - 1],
            userIdList: $karyawan->implode(','),
            executionMode: 'SYNC',
            requestSource: $sumber,
            requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sumber,
            priority: 1,
        );

        if (! ($response['success'] ?? false)) {
            throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
        }

        $diminta = array_flip($hari);

        foreach ($response['data'] ?? [] as $baris) {
            $k = trim((string) ($baris->Kode_Karyawan ?? ''));
            $t = (string) ($baris->Tanggal ?? '');

            if ($k === '' || $t === '') {
                continue;
            }

            $t = Carbon::parse($t)->format('Y-m-d');

            // Rentang membawa hari yang tak diminta; itu dibuang di sini.
            if (! isset($diminta[$t]) || ! array_key_exists($k, $peta)) {
                continue;
            }

            $peta[$k][$t] = $baris->Id_Shift ?? null;
        }

        return $peta;
    }

    /**
     * Check if dates have pending requests
     *
     * @param  array  $dates  Array of items with 'tanggal'
     * @return array List of locked dates
     */
    public function checkPendingRequest(array $dates, string $kodeKaryawan): array
    {
        $dateValues = array_column($dates, 'tanggal');
        $userid_web = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->value('UserID_Absen');

        // 3-tier: Header (a) -> Detail (b) -> Det (c)
        // Kode_Karyawan is in Detail (b), Tanggal_Shift is in Det (c)
        $lockedShift = DB::table('N_HRIS_Transaksi_Shift as a')
            ->join('N_HRIS_Transaksi_Shift_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->join('N_HRIS_Transaksi_Shift_Det as c', 'b.Id', '=', 'c.Id_Detail')
            ->where('b.Kode_Karyawan', $kodeKaryawan)
            ->where('a.Created_By', $userid_web)
            ->where('a.Jenis_Transaksi', 'SELF_SHIFT')
            ->whereNull('a.Status') // Not Cancelled
            ->whereNull('b.Status')
            ->whereNull('c.Status')
            ->where(function ($q) {
                $q->whereNull('a.Flag_Selesai')->orWhere('a.Flag_Selesai', '!=', 'Y');
            })
            ->whereIn('c.Tanggal_Shift', $dateValues)
            ->pluck('c.Tanggal_Shift')
            ->toArray();

        // Also check tukar hari
        $lockedTukarHari = DB::table('N_HRIS_Transaksi_Tukar_Hari as h')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->where('h.Created_By', $userid_web)
            ->whereNull('h.Status') // Not Cancelled
            ->where(function ($q) {
                $q->whereNull('h.Flag_Selesai')->orWhere('h.Flag_Selesai', '!=', 'Y');
            })
            ->where(function ($q) use ($dateValues) {
                $q->whereIn('d.Tanggal_Bekerja', $dateValues)->orWhereIn('d.Tanggal_Off', $dateValues);
            })
            ->select('d.Tanggal_Bekerja', 'd.Tanggal_Off')
            ->get()
            ->flatMap(function ($row) {
                $dates = [];
                if ($row->Tanggal_Bekerja) {
                    $dates[] = Carbon::parse($row->Tanggal_Bekerja)->format('Y-m-d');
                }
                if ($row->Tanggal_Off) {
                    $dates[] = Carbon::parse($row->Tanggal_Off)->format('Y-m-d');
                }

                return $dates;
            })
            ->toArray();

        return array_values(array_unique(array_merge($lockedShift, $lockedTukarHari)));
    }

    /**
     * Get all locked dates (pending request) for a user
     */
    public function getAllLockedDates(string $kodeKaryawan): array
    {
        // 3-tier: Header (a) -> Detail (b) -> Det (c)
        $userid_web = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->value('UserID_Absen');
        $lockedShiftDates = DB::table('N_HRIS_Transaksi_Shift as a')
            ->join('N_HRIS_Transaksi_Shift_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->join('N_HRIS_Transaksi_Shift_Det as c', 'b.Id', '=', 'c.Id_Detail')
            ->where('b.Kode_Karyawan', $kodeKaryawan)
            ->where('a.Created_By', $userid_web) // Only check requests created by the user themselves
            ->whereNull('a.Status') // Not Cancelled
            ->whereNull('b.Status')
            ->whereNull('c.Status')
            ->where(function ($q) {
                $q->whereNull('a.Flag_Selesai')->orWhere('a.Flag_Selesai', '!=', 'Y');
            })
            ->where('c.Needs_Approval', 'Y')
            ->where('c.Flag_Applied', '!=', 'Y')
            ->pluck('c.Tanggal_Shift')
            ->toArray();

        // Get locked dates from Tukar Hari pending requests
        $lockedTukarHariDates = DB::table('N_HRIS_Transaksi_Tukar_Hari as h')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('h.Kode_Karyawan', $kodeKaryawan)
            ->where('h.Created_By', $userid_web) // Only check requests created by the user themselves
            ->whereNull('h.Status') // Not cancelled (Status NULL = aktif, Y = dibatalkan)
            ->where(function ($q) {
                $q->whereNull('h.Flag_Selesai')->orWhere('h.Flag_Selesai', '!=', 'Y');
            }) // Pending (Flag_Selesai NULL or != Y)
            ->select('d.Tanggal_Bekerja', 'd.Tanggal_Off')
            ->get()
            ->flatMap(function ($row) {
                return array_filter([
                    $row->Tanggal_Bekerja ? Carbon::parse($row->Tanggal_Bekerja)->format('Y-m-d') : null,
                    $row->Tanggal_Off ? Carbon::parse($row->Tanggal_Off)->format('Y-m-d') : null,
                ]);
            })
            ->toArray();

        // Merge and return unique dates
        return array_values(array_unique(array_merge($lockedShiftDates, $lockedTukarHariDates)));
    }

    // =====================================================
    // LEADER SHIFT METHODS (SPV to Member Shift Assignment)
    // =====================================================

    /**
     * Get team members for a leader/SPV
     */
    public function getLeaderTeamMembers(
        string $leaderKode,
        FunctionalRole $role,
        ?AccessContext $context = null,
    ): array {
        $query = DB::table('Karyawan')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as v',
                'Karyawan.ID_Level_Jabatan',
                '=',
                'v.ID_Level_Jabatan',
            )
            ->leftJoin('View_Divisi_Sub_Divisi as d', 'Karyawan.Id_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
            ->where('Karyawan.Aktif', 'Y')
            ->select(
                'Karyawan.Kode_Karyawan',
                'Karyawan.UserId_Web as NIK',
                'Karyawan.Nama',
                'Karyawan.UserID_Absen',
                'v.nama_jabatan',
                'd.nama_divisi',
            );
        // dd(
        //     auth()->user()->UserId_Web,
        //     $this->role->can('SHIFT_BYPASS_APPROVAL')
        // );
        if ($context && $context?->isAdmin() && $role->can('SHIFT_BYPASS_APPROVAL')) {
            $role->applyScope($query);
        } else {
            $query->join('HRIS_Karyawan_Team as kt', 'Karyawan.Kode_Karyawan', '=', 'kt.Kode_Karyawan');
            $query->where('kt.Kode_Karyawan_Team', $leaderKode);
        }

        $role->applyScope($query);

        // dd($query->toSql(), $query->getBindings());

        $members = $query
            ->orderBy('Karyawan.Nama')
            ->groupBy(
                'Karyawan.Kode_Karyawan',
                'Karyawan.UserId_Web ',
                'Karyawan.Nama',
                'Karyawan.UserID_Absen',
                'v.nama_jabatan',
                'd.nama_divisi',
            )
            ->get()
            ->toArray();

        return $this->enrichMembersWithPermanentShiftStatus($members);
    }

    /**
     * Get member info by Kode_Karyawan array
     */
    public function getMemberInfoByKodes(array $kodes): array
    {
        if (empty($kodes)) {
            return [];
        }

        $members = DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'k.ID_Level_Jabatan', '=', 'v.ID_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as d', 'k.Id_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
            ->whereIn('k.Kode_Karyawan', $kodes)
            ->select(
                'k.Kode_Karyawan',
                'k.UserId_Web as NIK',
                'k.Nama',
                'k.UserID_Absen',
                'v.nama_jabatan',
                'd.nama_divisi',
            )
            ->orderBy('k.Nama')
            ->get()
            ->toArray();

        return $this->enrichMembersWithPermanentShiftStatus($members);
    }

    private function enrichMembersWithPermanentShiftStatus(array $members): array
    {
        if (empty($members)) {
            return $members;
        }

        $memberKodes = collect($members)
            ->pluck('Kode_Karyawan')
            ->filter()
            ->map(fn ($kode) => (string) $kode)
            ->unique()
            ->values();

        if ($memberKodes->isEmpty()) {
            return $members;
        }

        $nowDate = FormatTanggalHelper::getCurrentTime('leader-shift')->format('Y-m-d');

        $activePermanentKodes = DB::table('HRIS_Shift_Per_Karyawan')
            ->whereIn('Kode_Karyawan', $memberKodes->all())
            ->where('Flag_Aktif', 'Y')
            ->whereDate('Periode', '<=', $nowDate)
            ->pluck('Kode_Karyawan')
            ->map(fn ($kode) => (string) $kode)
            ->unique()
            ->values()
            ->all();

        $activePermanentLookup = array_fill_keys($activePermanentKodes, true);

        return array_map(function ($member) use ($activePermanentLookup) {
            $kode = (string) ($member->Kode_Karyawan ?? '');
            $member->has_permanent_shift = isset($activePermanentLookup[$kode]);

            return $member;
        }, $members);
    }

    public function getStatsForLeader(
        ?string $leaderKode,
        FunctionalRole $role,
        ?AccessContext $context = null,
    ): array {
        $UseridWeb = DB::table('Karyawan')->where('Kode_Karyawan', $leaderKode)->value('UserId_Web');
        // dd($leaderKode, $role->can('SHIFT_BYPASS_APPROVAL'), $context?->isAdmin());
        $queryTeam = DB::table('Karyawan')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as v',
                'Karyawan.ID_Level_Jabatan',
                '=',
                'v.ID_Level_Jabatan',
            )
            ->leftJoin('View_Divisi_Sub_Divisi as d', 'Karyawan.Id_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
            ->where('Karyawan.Aktif', 'Y')
            ->select(
                'Karyawan.Kode_Karyawan',
                'Karyawan.UserId_Web as NIK',
                'Karyawan.Nama',
                'Karyawan.UserID_Absen',
                'v.nama_jabatan',
                'd.nama_divisi',
            );

        // dd($context?->isAdmin(), $role->can('SHIFT_BYPASS_APPROVAL'));
        if ($context && $context?->isAdmin()) {
            $role->applyScope($queryTeam);
        } else {
            $queryTeam->join('HRIS_Karyawan_Team as kt', 'Karyawan.Kode_Karyawan', '=', 'kt.Kode_Karyawan');
            $queryTeam->where('kt.Kode_Karyawan_Team', $leaderKode);
        }

        $teamCount = $queryTeam->count();

        $flagAdmin = $context && $context?->isAdmin() ? 'Y' : 'T';

        $baseShift = DB::table('N_HRIS_Transaksi_Shift as h')
            ->where('h.Created_By', $UseridWeb)
            ->where('h.Jenis_Transaksi', 'LEADER_SHIFT')
            ->where('h.Flag_Admin', $flagAdmin);

        $baseStatusHari = DB::table('N_HRIS_Transaksi_Status_Hari as h')
            ->where('h.Created_By', $UseridWeb)
            ->where('h.Jenis_Transaksi', 'STATUS_HARI')
            ->where('h.Flag_Admin', $flagAdmin);

        $pendingShift = (clone $baseShift)
            ->whereNull('h.Status')
            ->where(function ($q) {
                $q->whereNull('h.Flag_Selesai')->orWhere('h.Flag_Selesai', '!=', 'Y');
            })
            ->count();

        $pendingStatusHari = (clone $baseStatusHari)
            ->whereNull('h.Status')
            ->where(function ($q) {
                $q->whereNull('h.Flag_Selesai')->orWhere('h.Flag_Selesai', '!=', 'Y');
            })
            ->count();

        $approvedShift = (clone $baseShift)
            ->where(function ($q) {
                $q->whereNull('h.Status')->orWhere('h.Status', 'Y');
            })
            ->where('h.Flag_Selesai', 'Y')
            ->count();

        $approvedStatusHari = (clone $baseStatusHari)
            ->where(function ($q) {
                $q->whereNull('h.Status')->orWhere('h.Status', 'Y');
            })
            ->where('h.Flag_Selesai', 'Y')
            ->count();

        return [
            'team_count' => $teamCount,
            'pending_count' => $pendingShift + $pendingStatusHari,
            'approved_month' => $approvedShift + $approvedStatusHari,
        ];
    }

    /**
     * Apply shift change directly (for non-backdate: today and future)
     * Uses 3-tier structure: Header → Detail (per Karyawan) → Det (per Tanggal)
     */
    public function applyDirectShiftChange(
        string $memberKode,
        string $tanggal,
        int $idShift,
        string $jenis,
        string $leaderKode,
        string $alasan = '',
        ?AccessContext $context = null,
    ): void {
        $this->pastikanTargetShiftValid($jenis);

        $previousShift = $this->getUserShiftForDate($memberKode, Carbon::parse($tanggal));
        $jenisPengajuan = $this->determineJenisPengajuan(Carbon::parse($tanggal));

        $useridWeb = DB::table('Karyawan')->where('Kode_Karyawan', $leaderKode)->value('UserID_Absen');
        // Tier 1: Create Header
        $noTransaksi = $this->createHeader('LEADER_SHIFT', $alasan, $context);

        // Update Created_By to leaderKode
        DB::table('N_HRIS_Transaksi_Shift')
            ->where('No_Transaksi', $noTransaksi)
            ->update([
                'Created_By' => $useridWeb,
                'Flag_Selesai' => 'Y', // Already completed
            ]);

        // Tier 2: Create Detail for this member
        $idDetail = $this->createKaryawanDetail($noTransaksi, $memberKode, $jenis, $alasan);

        // Update Detail as approved
        DB::table('N_HRIS_Transaksi_Shift_Detail')
            ->where('Id', $idDetail)
            ->update(['Flag_Approval' => 'Y']);

        // Tier 3: Create Det for this date
        $this->createShiftDet($idDetail, $noTransaksi, [
            [
                'tanggal' => $tanggal,
                'id_shift' => $idShift,
                'id_shift_sebelumnya' => $previousShift,
                'jenis_pengajuan' => $jenisPengajuan,
                'needs_approval' => false,
                'flag_approval' => 'Y',
                'flag_applied' => 'Y',
            ],
        ]);

        // Apply to master table
        $item = [
            'tanggal' => $tanggal,
            'id_shift' => $idShift,
        ];
        $this->applyShiftDirect($memberKode, $item, $jenis);
    }

    /**
     * Apply shift to master table for an EXISTING transaction (no new header created).
     * Used by bulk/leader submit when dates are non-backdate (direct apply).
     * Updates the existing Det record as applied + applies to master table.
     *
     * @param  string  $noTransaksi  Existing transaction number
     * @param  string  $memberKode  Employee code
     * @param  string  $tanggal  Date
     * @param  int  $idShift  Shift ID
     * @param  string  $jenis  SEMENTARA|PERMANEN
     */
    public function applyShiftDirectForExistingTransaction(
        string $noTransaksi,
        string $memberKode,
        string $tanggal,
        int $idShift,
        string $jenis,
        ?string $decision_notes = null,
        bool $overwriteSementara = true,
        ?string $preComputedStatusHari = null,
    ): void {
        // Satu pasangan = kasus khusus dari batch satu-tanggal. Implementasinya
        // sengaja HANYA ada di versi batch supaya tak ada dua salinan aturan.
        $this->applyShiftDirectBatchForExistingTransaction(
            $noTransaksi,
            $memberKode,
            [['tanggal' => $tanggal, 'id_shift' => $idShift]],
            $jenis,
            $decision_notes,
            $overwriteSementara,
            [$tanggal => $preComputedStatusHari],
        );
    }

    /**
     * Terapkan langsung SEMUA tanggal milik satu karyawan dalam satu transaksi.
     *
     * ── Kenapa versi batch ini ada ───────────────────────────────────────────
     *
     * Versi per-pasangan menjalankan dua kueri yang TIDAK bergantung pada
     * tanggal: mencari Id_Detail (hanya bergantung pada No_Transaksi +
     * Kode_Karyawan) dan menandai Det. Dipanggil dari perulangan bersarang
     * anggota × tanggal, keduanya terulang untuk setiap tanggal.
     *
     * Terekam di production 9 September 2026: satu permintaan
     * `leader-shift/submit` menjalankan 3.289 kueri dalam 114 detik, dengan
     * 108.849 ms waktu DB — yaitu ~33 ms per kueri. Angka itu penting: SQL-nya
     * tidak lambat, yang mahal adalah 3.289 kali bolak-balik jaringan. Dan
     * karena semuanya berada di dalam satu transaksi sementara RCSI mati di
     * basis data ini, selama itu pula pembaca tabel shift ikut mengantre.
     *
     * Di sini pencarian Id_Detail dilakukan SEKALI per karyawan dan penandaan
     * Det memakai satu UPDATE ber-`whereIn` tanggal. Penerapan ke tabel master
     * (`applyShiftDirect`) tetap per tanggal karena memang menulis baris
     * berbeda per tanggal — itu kerja nyata, bukan pengulangan.
     *
     * @param  array<int,array{tanggal:string,id_shift:int}>  $dateItems
     * @param  array<string,string|null>  $statusHariPerTanggal  status hari pra-hitung, dikunci tanggal
     */
    public function applyShiftDirectBatchForExistingTransaction(
        string $noTransaksi,
        string $memberKode,
        array $dateItems,
        string $jenis,
        ?string $decision_notes = null,
        bool $overwriteSementara = true,
        array $statusHariPerTanggal = [],
    ): void {
        $this->pastikanTargetShiftValid($jenis);

        if ($dateItems === []) {
            return;
        }

        // Bergantung pada (No_Transaksi, Kode_Karyawan) saja — sekali cukup.
        $detailIds = DB::table('N_HRIS_Transaksi_Shift_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Kode_Karyawan', $memberKode)
            ->pluck('Id');

        if ($detailIds->isNotEmpty()) {
            $tanggalList = array_values(array_unique(array_filter(
                array_map(fn (array $d) => $d['tanggal'] ?? null, $dateItems),
            )));

            // Dipotong: `Id_Detail IN (...)` dan `Tanggal_Shift IN (...)` sama-sama
            // menyumbang parameter, dan SQL Server berhenti di 2.100.
            foreach (array_chunk($tanggalList, 500) as $bagian) {
                DB::table('N_HRIS_Transaksi_Shift_Det')
                    ->whereIn('Id_Detail', $detailIds)
                    ->whereIn('Tanggal_Shift', $bagian)
                    ->whereNull('Status')
                    ->update([
                        'Flag_Applied' => 'Y',
                        'Flag_Approval' => 'Y',
                    ]);
            }
        }

        $diterapkan = $jenis === 'SEMENTARA'
            ? $this->terapkanShiftSementaraSekaligus(
                $memberKode,
                $dateItems,
                $noTransaksi,
                $statusHariPerTanggal,
            )
            : $this->terapkanShiftSatuPerSatu(
                $memberKode,
                $dateItems,
                $jenis,
                $noTransaksi,
                $overwriteSementara,
                $statusHariPerTanggal,
            );

        // SEKALI per karyawan, bukan sekali per tanggal. Lihat catatan panjang di
        // tandaiAbsenKotor(): bentuk per-tanggal inilah yang menjadikan
        // leader-shift/submit pemblokir akar bagi delapan sesi di production.
        if ($diterapkan !== []) {
            $this->tandaiAbsenKotor($memberKode, $jenis, min($diterapkan), max($diterapkan));
        }
    }

    /**
     * Jalur lama, satu tanggal satu panggilan. Dipakai untuk PERMANEN, yang
     * semantiknya memang per-tanggal (menonaktifkan sementara `Tanggal >= x` dan
     * memotong custom-OFF yang tumpang tindih) sehingga tak bisa disatukan tanpa
     * mengubah artinya. Di jalur itu pun satu karyawan lazimnya hanya satu tanggal.
     *
     * @param  array<int,array{tanggal:string,id_shift:int}>  $dateItems
     * @param  array<string,string|null>  $statusHariPerTanggal
     * @return array<int,string> tanggal yang benar-benar diterapkan
     */
    private function terapkanShiftSatuPerSatu(
        string $memberKode,
        array $dateItems,
        string $jenis,
        string $noTransaksi,
        bool $overwriteSementara,
        array $statusHariPerTanggal,
    ): array {
        $diterapkan = [];

        foreach ($dateItems as $item) {
            $tanggal = $item['tanggal'] ?? null;
            if ($tanggal === null) {
                continue;
            }

            $this->applyShiftDirect(
                $memberKode,
                ['tanggal' => $tanggal, 'id_shift' => $item['id_shift'] ?? null],
                $jenis,
                $noTransaksi,
                $overwriteSementara,
                $statusHariPerTanggal[$tanggal] ?? null,
                true, // dirty-mark ditunda: ditandai sekali sebagai rentang oleh pemanggil
            );

            $diterapkan[] = $tanggal;
        }

        return $diterapkan;
    }

    /**
     * Shift SEMENTARA untuk banyak tanggal sekaligus.
     *
     * ── Kenapa jalur ini dipisah ─────────────────────────────────────────────
     *
     * applyShiftDirect() mengerjakan satu tanggal per panggilan, dan tiap
     * panggilan membuka DB::transaction()-nya sendiri (savepoint bila sudah di
     * dalam transaksi), lalu satu UPDATE penonaktifan dan satu INSERT. Untuk 45
     * karyawan x 6 tanggal itu 270 kali seluruh urutan tersebut, di dalam satu
     * transaksi tulis yang menahan lock.
     *
     * Terukur di production 9 September 2026: submitMetrics mencatat
     * `member_count: 45, date_count: 6` selesai dalam 113.239 ms, dan sesi itu
     * menjadi pemblokir akar bagi delapan sesi lain.
     *
     * Untuk SEMENTARA ketiganya bisa disatukan tanpa mengubah arti: penonaktifan
     * menyaring `Tanggal` tepat sama sehingga aman jadi satu `whereIn`, dan
     * penyisipan barisnya saling bebas sehingga aman jadi satu INSERT massal.
     * Overlay hari-OFF tetap per tanggal karena hanya menyangkut sebagian kecil
     * tanggal — dan hanya tanggal yang statusnya memang OFF.
     *
     * @param  array<int,array{tanggal:string,id_shift:int}>  $dateItems
     * @param  array<string,string|null>  $statusHariPerTanggal
     * @return array<int,string> tanggal yang benar-benar diterapkan
     */
    private function terapkanShiftSementaraSekaligus(
        string $memberKode,
        array $dateItems,
        string $noTransaksi,
        array $statusHariPerTanggal,
    ): array {
        $kodePerusahaan = '001';
        $userId = Auth::id();

        $perTanggal = [];
        foreach ($dateItems as $item) {
            $tanggal = $item['tanggal'] ?? null;
            if ($tanggal !== null) {
                $perTanggal[$tanggal] = $item['id_shift'] ?? null;
            }
        }

        if ($perTanggal === []) {
            return [];
        }

        $tanggalList = array_keys($perTanggal);

        DB::transaction(function () use (
            $kodePerusahaan, $memberKode, $userId, $noTransaksi, $perTanggal, $tanggalList, $statusHariPerTanggal
        ) {
            // Hari OFF: overlay WORK. Hanya untuk tanggal yang memang OFF, dan
            // status harinya sudah dipra-hitung DI LUAR transaksi oleh pemanggil.
            foreach ($tanggalList as $tanggal) {
                $status = $statusHariPerTanggal[$tanggal] ?? null;
                if ($status !== null && str_starts_with($status, 'OFF')) {
                    $this->insertWorkOverlay($kodePerusahaan, $memberKode, $tanggal, $userId, $noTransaksi);
                }
            }

            // Dipotong demi batas 2.100 parameter SQL Server.
            foreach (array_chunk($tanggalList, 500) as $bagian) {
                DB::table('HRIS_Shift_Sementara')
                    ->where('Kode_Perusahaan', $kodePerusahaan)
                    ->where('Kode_Karyawan', $memberKode)
                    ->whereIn('Tanggal', $bagian)
                    ->where('Flag_Aktif', 'Y')
                    ->update([
                        'Flag_Aktif' => 'T',
                        'Nonaktif_By' => $userId,
                        'Nonaktif_At' => now(),
                        'Nonaktif_Reason' => 'Replaced by new temporary shift',
                    ]);
            }

            $baris = [];
            foreach ($perTanggal as $tanggal => $idShift) {
                $baris[] = [
                    'Kode_Perusahaan' => $kodePerusahaan,
                    'Kode_Karyawan' => $memberKode,
                    'ID_Shift' => $idShift,
                    'Tanggal' => $tanggal,
                    'UserID' => $memberKode,
                    'Flag_Aktif' => 'Y',
                    'No_Transaksi_Reference' => $noTransaksi,
                    'created_at' => now(),
                ];
            }

            // Ukuran potongan diturunkan dari jumlah kolom yang SEBENARNYA, bukan
            // konstanta yang dihitung sekali lalu jadi basi begitu kolomnya
            // bertambah. Penjaga InfrastrukturSqlServerSatuRumahTest menuntut ini.
            PenyisipanBerbatasParameter::sisipkan('HRIS_Shift_Sementara', $baris);
        });

        return $tanggalList;

        // (dead read $tipeTransaksi dihapus — hanya dipakai blok kode yang di-comment
        //  di bawah; membebani transaksi tanpa guna.)

        //     if($tipeTransaksi && $tipeTransaksi->Jenis_Transaksi == 'LEADER_SHIFT' && $tipeTransaksi->Flag_Admin == 'T') {
        //     $getKaryawanDetail = DB::table('N_HRIS_Transaksi_Shift_Detail as dt')
        //         ->join('Karyawan as k', 'dt.Kode_Karyawan', '=', 'k.Kode_Karyawan')
        //         ->where('dt.No_Transaksi', $noTransaksi)
        //         ->where('dt.Kode_Karyawan', $memberKode)
        //         ->where('dt.Flag_Approval', 'Y')
        //         ->whereNull('dt.Status')
        //         ->where('k.Aktif', 'Y')
        //         ->select('k.Nama', 'k.HP', 'k.Kode_Karyawan', 'dt.Id', 'dt.Target_Table')
        //         ->distinct()
        //         ->get();
        //     // dd($getKaryawanDetail->pluck('Tanggal_Shift')->toArray());
        //     // dd($getKaryawanDetail);
        //     foreach($getKaryawanDetail as $emp) {
        //         $getTanggal = DB::table('N_HRIS_Transaksi_Shift_Det as det')
        //         ->join('HRIS_Shift_Kerja as s', 'det.Id_Shift_Diminta', '=', 's.ID_Shift')
        //         ->where('No_Transaksi', $noTransaksi)
        //         ->where('Id_Detail', $emp->Id)
        //         ->where('Needs_Approval', 'T')
        //         ->where('Flag_Applied', 'Y')
        //         ->where('Flag_Approval', 'Y') // only approved dates
        //         ->select('Tanggal_Shift', 's.Nama')->get();

        //         // dd($getTanggal);
        //         // dd($getTanggal->pluck('Tanggal_Shift')->toArray());

        //         $namaEmployeeTarget = $emp->Nama;
        //         $namaRequester = Auth::user()->karyawan->Nama;
        //         $HPEmployeeTarget = $emp->HP;
        //         $tanggal = FormatTanggalHelper::formatTanggalMultiple($getTanggal->pluck('Tanggal_Shift')->toArray());
        //         $tanggalFormatted = $emp->Target_Table == 'PERMANEN' ? FormatTanggalHelper::formatTanggalMultiple($getTanggal->pluck('Tanggal_Shift')->toArray()) . ' s/d Seterusnya' : FormatTanggalHelper::formatTanggalMultiple($getTanggal->pluck('Tanggal_Shift')->toArray());
        //         $shift = $getTanggal->pluck('Nama')->unique()->implode(', ');
        //         // Send employee notification for each affected employee
        //         try {
        //             // dd($HPEmployeeTarget, $namaEmployeeTarget, $emp->Target_Table, $shift, $namaRequester, $tanggalFormatted);
        //             $pesan = $this->whatsappMessageBuilder->Notif_Karyawan_Perubahan_Shift($HPEmployeeTarget, $namaEmployeeTarget, $emp->Target_Table, $shift, $namaRequester, $tanggalFormatted);
        //             // dd($pesan);
        //             $this->whatsappLogHelper->sendAndLog($emp->Kode_Karyawan, $namaEmployeeTarget, $HPEmployeeTarget, $noTransaksi, $pesan);
        //         } catch (\Throwable $e) {
        //             Log::error("WA Notification Failed (Final Approve - Employee): " . $e->getMessage(), [
        //                 'kode_karyawan' => $emp->Kode_Karyawan,
        //                 'nama_karyawan' => $namaEmployeeTarget,
        //                 'hp_karyawan' => $HPEmployeeTarget
        //             ]);
        //         }
        //     }
        // }
    }

    /**
     * Create leader shift request (for backdate that needs approval)
     * Uses 3-tier structure: 1 Header → N Details (per Karyawan) → M Dets (per Tanggal)
     *
     * @param  array  $members  Flat array of kode strings OR array of {kode, dates} objects
     * @param  array  $dates  Shared dates (used when $isMemberDateMatrix = false)
     * @param  string  $jenis  SEMENTARA|PERMANEN
     * @param  bool  $isMemberDateMatrix  When true, each $members item has 'kode' and 'dates' keys
     * @return string No_Transaksi
     */
    public function createLeaderShiftRequest(
        string $leaderKode,
        array $members,
        array $dates,
        string $jenis,
        string $alasan = '',
        bool $isMemberDateMatrix = false,
        ?AccessContext $context = null,
        bool $overwriteSementara = false,
        ?string $preReservedNoTransaksi = null,
        ?array $previousShiftMap = null,
    ): string {
        return DB::transaction(function () use (
            $leaderKode,
            $members,
            $dates,
            $jenis,
            $alasan,
            $isMemberDateMatrix,
            $context,
            $overwriteSementara,
            $preReservedNoTransaksi,
            $previousShiftMap,
        ) {
            // Tier 1: Create single header for entire batch
            $noTransaksi = $this->createHeader('LEADER_SHIFT', $alasan, $context, $overwriteSementara, $preReservedNoTransaksi);

            // // Update Created_By to leaderKode
            // DB::table('N_HRIS_Transaksi_Shift')
            //     ->where('No_Transaksi', $noTransaksi)
            //     ->update(['Created_By' => $leaderKode]);
            // dd($members);
            $hasAnyApprovalNeeded = false;
            $needsApprovalDates = [];

            if ($isMemberDateMatrix) {
                foreach ($members as $memberItem) {
                    $memberKode = $memberItem['kode'];
                    $memberDates = $memberItem['dates'] ?? [];

                    // Tier 2: Create Detail for this member
                    $idDetail = $this->createKaryawanDetail($noTransaksi, $memberKode, $jenis, $alasan);

                    // Prepare dates with previous shift info
                    $formattedDates = [];
                    foreach ($memberDates as $dateItem) {
                        $memberNeedsApproval = false;
                        // Pakai map yang sudah dihitung DI LUAR transaksi (getUserShiftForDate
                        // menjalankan SP absen sinkron — berat; jangan di dalam lock). Fallback
                        // ke pemanggilan langsung bila map tak disediakan (caller lama).
                        $previousShift = $previousShiftMap !== null
                            ? ($previousShiftMap[$memberKode][$dateItem['tanggal']] ?? null)
                            : $this->getUserShiftForDate($memberKode, Carbon::parse($dateItem['tanggal']));
                        $needsApproval = $dateItem['needs_approval'] ?? true;
                        if ($needsApproval) {
                            $memberNeedsApproval = true; // per member
                            $hasAnyApprovalNeeded = true; // global

                            $needsApprovalDates[] = [
                                'kode_karyawan' => $memberKode,
                                'tanggal' => $dateItem['tanggal'],
                                'jenis_pengajuan' => $dateItem['jenis_pengajuan'] ?? 'BACKDATE',
                            ];
                        }
                        $formattedDates[] = [
                            'tanggal' => $dateItem['tanggal'],
                            'id_shift' => $dateItem['id_shift'],
                            'id_shift_sebelumnya' => $previousShift,
                            'jenis_pengajuan' => $dateItem['jenis_pengajuan'] ?? 'BACKDATE',
                            'needs_approval' => $needsApproval,
                            'flag_approval' => $needsApproval ? null : 'Y',
                            'flag_applied' => $needsApproval ? 'T' : 'Y',
                        ];
                    }

                    if (! $memberNeedsApproval) {
                        DB::table('N_HRIS_Transaksi_Shift_Detail')
                            ->where('Id', $idDetail)
                            ->whereNull('Status')
                            ->update(['Flag_Approval' => 'Y']);
                    }

                    // Tier 3: Create Det for each date
                    $this->createShiftDet($idDetail, $noTransaksi, $formattedDates);
                }
            } else {
                // ORIGINAL: Flat member codes + shared dates for all members
                foreach ($members as $memberKode) {
                    // Tier 2: Create Detail for this member
                    $idDetail = $this->createKaryawanDetail($noTransaksi, $memberKode, $jenis, $alasan);
                    $memberNeedsApproval = false;

                    // Prepare dates with previous shift info
                    $formattedDates = [];
                    foreach ($dates as $dateItem) {
                        $previousShift = $previousShiftMap !== null
                            ? ($previousShiftMap[$memberKode][$dateItem['tanggal']] ?? null)
                            : $this->getUserShiftForDate($memberKode, Carbon::parse($dateItem['tanggal']));
                        $needsApproval = $dateItem['needs_approval'] ?? true;
                        if ($needsApproval) {
                            $memberNeedsApproval = true;
                            $needsApprovalDates[] = [
                                'kode_karyawan' => $memberKode,
                                'tanggal' => $dateItem['tanggal'],
                                'jenis_pengajuan' => $dateItem['jenis_pengajuan'] ?? 'BACKDATE',
                            ];
                            $hasAnyApprovalNeeded = true;
                        }
                        $formattedDates[] = [
                            'tanggal' => $dateItem['tanggal'],
                            'id_shift' => $dateItem['id_shift'],
                            'id_shift_sebelumnya' => $previousShift,
                            'jenis_pengajuan' => $dateItem['jenis_pengajuan'] ?? 'BACKDATE',
                            'needs_approval' => $needsApproval,
                            'flag_approval' => $needsApproval ? null : 'Y',
                            'flag_applied' => $needsApproval ? 'T' : 'Y',
                            'Decision_Approval_Notes' => $dateItem['decision_notes'] ?? null,
                        ];
                    }

                    if (! $memberNeedsApproval) {
                        DB::table('N_HRIS_Transaksi_Shift_Detail')
                            ->where('Id', $idDetail)
                            ->whereNull('Status')
                            ->update(['Flag_Approval' => 'Y']);
                    }

                    // Tier 3: Create Det for each date
                    $this->createShiftDet($idDetail, $noTransaksi, $formattedDates);
                }
            }

            if ($hasAnyApprovalNeeded) {
                $hasBackdateApproval = collect($needsApprovalDates)->contains(
                    fn ($item) => ($item['jenis_pengajuan'] ?? null) === 'BACKDATE',
                );
                $configCode = $hasBackdateApproval ? 'LEADER_SHIFT_BACKDATE' : 'LEADER_SHIFT';

                // Create approval request for the leader (single approval for entire batch)
                // $flowResult = $this->approvalFlowService->createFlowForLegacySingleAppro(
                //     $noTransaksi,
                //     $leaderKode,
                //     3 // Approval type for shift
                // );

                $flowResult = $this->approvalFlowService->createFlowFromApprovalConfig(
                    $noTransaksi,
                    $leaderKode,
                    $configCode,
                );

                // VALIDATION: Fail transaction if approval config not found
                if (! $flowResult->success) {
                    throw new \Exception(
                        'Konfigurasi approval untuk '.$configCode.' tidak ditemukan. '.$flowResult->error,
                    );
                }

                // ✅ CREATE APPROVAL DETAILS FROM SOURCE VIEW (Generic Approval Architecture)
                // Each approver gets THEIR OWN copy of detail records (ISOLATED model)
                // This allows independent approval decisions while maintaining audit trail
                try {
                    // Get all approval requests created by flow (one per approver step)
                    $approvalReqs = DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Status')
                        ->orderBy('Request_Order_Flow', 'asc')
                        ->get();

                    if ($approvalReqs->isNotEmpty()) {
                        foreach ($approvalReqs as $approvalReq) {
                            // Check if THIS APPROVER already has detail records (per-approver isolation)
                            $exists = DB::table('HRIS_Approval_Request_Detail')
                                ->where('Id_Approval_Request', $approvalReq->id)
                                ->exists();

                            if ($exists) {
                                Log::channel('ApprovalDetailService')->info(
                                    'Details already created for this approver',
                                    [
                                        'id_approval_request' => $approvalReq->id,
                                        'approver' => $approvalReq->Approver_Karyawan_Kode,
                                        'flow_step' => $approvalReq->Request_Order_Flow,
                                    ],
                                );

                                continue;
                            }

                            $idJenisApproval = (int) ($approvalReq->Id_Jenis_Approval ?? 0);
                            if ($idJenisApproval <= 0) {
                                Log::channel('ApprovalDetailService')->warning(
                                    'Id_Jenis_Approval missing on approval request',
                                    [
                                        'no_transaksi' => $noTransaksi,
                                        'id_approval_request' => $approvalReq->id,
                                        'approver' => $approvalReq->Approver_Karyawan_Kode,
                                    ],
                                );

                                continue;
                            }

                            // Create details FOR THIS APPROVER (isolated per approval request)
                            $createdCount = $this->approvalDetailService->createFromSourceView(
                                $approvalReq->id,
                                $noTransaksi,
                                'N_HRIS_VW_SRC_SHIFT',
                                $idJenisApproval,
                            );

                            Log::channel('ApprovalDetailService')->info('Approval details created for approver', [
                                'no_transaksi' => $noTransaksi,
                                'jenis' => 'SHIFT (LEADER)',
                                'id_approval_request' => $approvalReq->id,
                                'approver' => $approvalReq->Approver_Karyawan_Kode,
                                'flow_step' => $approvalReq->Request_Order_Flow,
                                'created_count' => $createdCount,
                            ]);
                        }
                    } else {
                        Log::channel('ApprovalDetailService')->warning('No approval requests found to create details', [
                            'no_transaksi' => $noTransaksi,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Error creating approval details from source view: '.$e->getMessage());
                }

                // CHECK: If all approvers are Is_Mandatory='T' (optional audit only)
                // → Auto-complete immediately but keep approval records for audit trail
                $canAutoComplete = $this->approvalRequestService->canAutoCompleteWithoutChain($noTransaksi);

                if ($canAutoComplete) {
                    // All approvers are optional (Is_Mandatory='T')
                    // → Auto-approve all pending Dets and apply shifts immediately
                    DB::table('N_HRIS_Transaksi_Shift_Det')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Needs_Approval', 'Y')
                        ->whereNull('Flag_Approval')
                        ->whereNull('Status')
                        ->update([
                            'Flag_Approval' => 'Y',
                            'Flag_Applied' => 'T',
                        ]);

                    // Update Details to 'Y' as well
                    $this->updateDetailApprovalStatusSmart($noTransaksi);

                    // Apply shifts immediately
                    $this->applyShiftAfterApproval($noTransaksi);

                    // Mark header as complete
                    DB::table('N_HRIS_Transaksi_Shift')
                        ->where('No_Transaksi', $noTransaksi)
                        ->update([
                            'Flag_Selesai' => 'Y',
                            'Updated_At' => now(),
                            'Updated_By' => Auth::id(),
                        ]);

                    // Still send WA notification to all optional approvers (audit trail)
                    $allApprovers = DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->whereNull('Status')
                        ->get();

                    foreach ($allApprovers as $approver) {
                        try {
                            $namaApprover = $this->getKaryawanName($approver->Approver_Karyawan_Kode);
                            $hpApprover = $this->getKaryawanHp($approver->Approver_Karyawan_Kode);
                            $onlyDate = array_column($needsApprovalDates, 'tanggal');
                            $tipeTransaksi = 'Pergantian Shift oleh Leader (Audit Only)';
                            $namaRequester = 'Tim '.$this->getKaryawanName($leaderKode);
                            $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($onlyDate);

                            $pesan = $this->whatsappMessageBuilder->Notif_Request_Transaksi_Tanggal(
                                $hpApprover,
                                $namaApprover,
                                $tipeTransaksi,
                                $noTransaksi,
                                $namaRequester,
                                $tanggalFormated,
                            );
                            $this->whatsappLogHelper->sendAndLog(
                                $approver->Approver_Karyawan_Kode,
                                $namaApprover,
                                $hpApprover,
                                $noTransaksi,
                                $pesan,
                            );
                        } catch (\Throwable $e) {
                            Log::error('WA Notification Failed (Optional Approver Audit): '.$e->getMessage());
                        }
                    }

                    // Return early - no need to send first approver notification below
                    return $noTransaksi;
                }

                // Normal flow: Send notification to first mandatory approver only
                $firstApprover = $this->getKaryawanName($flowResult->getFirstApproverKode());
                $namaApprover = $this->getKaryawanName($flowResult->getFirstApproverKode());
                $onlyDate = array_column($needsApprovalDates, 'tanggal');
                $tipeTransaksi = 'Pergantian Shift oleh Leader';
                $namaRequester = 'Tim '.$this->getKaryawanName($leaderKode);
                $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($onlyDate);
                $hpApprover = $this->getKaryawanHp($flowResult->getFirstApproverKode());

                $pesan = $this->whatsappMessageBuilder->Notif_Request_Transaksi_Tanggal(
                    $hpApprover,
                    $namaApprover,
                    $tipeTransaksi,
                    $noTransaksi,
                    $namaRequester,
                    $tanggalFormated,
                );
                // dd($pesan);
                // dd($flowResult->getFirstApproverKode());
                $updateWaCounter = function () use ($noTransaksi, $flowResult) {
                    DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Approver_Karyawan_Kode', $flowResult->getFirstApproverKode())
                        ->update([
                            'angka_wa' => DB::raw('ISNULL(angka_wa, 0) + 1'),
                        ]);
                };

                // dd($updateWaCounter());
                $this->whatsappLogHelper->sendAndLog(
                    $flowResult->getFirstApproverKode(),
                    $firstApprover,
                    $hpApprover,
                    $noTransaksi,
                    $pesan,
                    $updateWaCounter,
                );
            }

            return $noTransaksi;
        });
    }

    /**
     * Get leader shift history
     */
    public function getLeaderShiftHistory(
        string $leaderKode,
        int $leaderUserId,
        array $params,
        ?AccessContext $context = null,
    ): array {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        $filters = $params['filters'] ?? [];
        $userid_web = DB::table('Karyawan')->where('Kode_Karyawan', $leaderKode)->value('UserId_Web');

        // Query 1: Shift Transactions
        $queryShift = DB::table('N_HRIS_Transaksi_Shift as h')
            ->select(
                'h.No_Transaksi',
                'h.Created_At',
                'h.Alasan as Keterangan', // Standardize as Keterangan
                'h.Flag_Selesai',
                DB::raw("'SHIFT' as transaction_type"),
                'h.Status', // Allow nullable
            )
            ->where('h.Created_By', $userid_web)
            ->where('h.Jenis_Transaksi', 'LEADER_SHIFT');

        // Query 2: Status Hari Transactions
        $queryStatusHari = DB::table('N_HRIS_Transaksi_Status_Hari as h')
            ->select(
                'h.No_Transaksi',
                'h.Created_At',
                // DB::raw("NULL as Keterangan"), // Will fetch generic label or first detail's reason
                'h.Alasan as Keterangan',
                'h.Flag_Selesai',
                DB::raw("'STATUS_HARI' as transaction_type"),
                'h.Status',
            )
            ->where('h.Created_By', $userid_web)
            ->where('h.Jenis_Transaksi', 'STATUS_HARI');

        // Default "all": include active transactions only (exclude cancelled/rejected).
        // Pengecualian: yang dibatalkan ADMIN tetap tampil supaya pengajuan leader
        // tidak hilang tanpa penjelasan.
        $aktifAtauBatalAdmin = fn (string $tabelDet) => fn ($q) => $q->whereNull('h.Status')->orWhereExists(
            fn ($e) => $e->selectRaw('1')->from($tabelDet.' as bd')
                ->whereColumn('bd.No_Transaksi', 'h.No_Transaksi')
                ->where('bd.Note_Tolak', 'like', PengajuanShiftAdminService::PENANDA_BATAL_ADMIN.'%'),
        );
        $queryShift->where($aktifAtauBatalAdmin('N_HRIS_Transaksi_Shift_Det'));
        $queryStatusHari->where($aktifAtauBatalAdmin('N_HRIS_Transaksi_Status_Hari_Det'));

        // Apply filters to both queries (simplistic approach via closure if possible, but for Union we might need to wrap)
        // Better approach: Union first then apply filters?
        // Laravel's union builder supports adding where clauses to individual queries.
        if (! empty($filters['transaction_type']) && $filters['transaction_type'] !== 'all') {
            if ($filters['transaction_type'] === 'STATUS_HARI') {
                // User wants STATUS_HARI only, so exclude Shift
                $queryShift->whereRaw('1 = 0');
                // queryStatusHari already queries STATUS_HARI
            } elseif ($filters['transaction_type'] === 'LEADER_SHIFT') {
                // User wants SHIFT only, so exclude Status Hari
                $queryStatusHari->whereRaw('1 = 0');
                // queryShift already queries LEADER_SHIFT
            }
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'pending') {
                $queryShift
                    ->where(function ($q) {
                        $q->whereNull('h.Flag_Selesai')->orWhere('h.Flag_Selesai', '!=', 'Y');
                    })
                    ->whereNull('h.Status'); // Ensure not rejected

                $queryStatusHari
                    ->where(function ($q) {
                        $q->whereNull('h.Flag_Selesai')->orWhere('h.Flag_Selesai', '!=', 'Y');
                    })
                    ->whereNull('h.Status');
            } elseif ($filters['status'] === 'approved') {
                $queryShift->where('h.Flag_Selesai', 'Y')->whereNull('h.Status');
                $queryStatusHari->where('h.Flag_Selesai', 'Y')->whereNull('h.Status');
            } elseif ($filters['status'] === 'rejected') {
                $queryShift->whereNotNull('h.Status')->where('h.Status', '!=', 'Y');
                $queryStatusHari->whereNotNull('h.Status')->where('h.Status', '!=', 'Y');
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $searchLike = "%{$search}%";

            $queryShift->where(function ($q) use ($searchLike) {
                $q->where('h.No_Transaksi', 'like', $searchLike)->orWhereExists(function ($sub) use ($searchLike) {
                    $sub->select(DB::raw(1))
                        ->from('N_HRIS_Transaksi_Shift_Detail as d')
                        ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                        ->whereColumn('d.No_Transaksi', 'h.No_Transaksi')
                        ->where('k.Nama', 'like', $searchLike);
                });
            });

            $queryStatusHari->where(function ($q) use ($searchLike) {
                $q->where('h.No_Transaksi', 'like', $searchLike)->orWhereExists(function ($sub) use ($searchLike) {
                    $sub->select(DB::raw(1))
                        ->from('N_HRIS_Transaksi_Status_Hari_Detail as d')
                        ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                        ->whereColumn('d.No_Transaksi', 'h.No_Transaksi')
                        ->where('k.Nama', 'like', $searchLike);
                });
            });
        }

        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->startOfDay();
            $endDate = Carbon::parse($filters['end_date'])->endOfDay();
            $queryShift->whereBetween('h.Created_At', [$startDate, $endDate]);
            $queryStatusHari->whereBetween('h.Created_At', [$startDate, $endDate]);
        }

        if (! empty($filters['created_at'])) {
            $startDate = Carbon::parse($filters['created_at'])->startOfDay();
            $endDate = Carbon::parse($filters['created_at'])->endOfDay();
            $queryShift->whereBetween('h.Created_At', [$startDate, $endDate]);
            $queryStatusHari->whereBetween('h.Created_At', [$startDate, $endDate]);
        }

        // dd($context->isAdmin());
        if ($context && $context->isAdmin()) {
            $queryShift->where('h.Flag_Admin', 'Y');
            $queryStatusHari->where('h.Flag_Admin', 'Y');
        } else {
            $queryShift->where('h.Flag_Admin', 'T');
            $queryStatusHari->where('h.Flag_Admin', 'T');
        }

        // Union
        $combinedQuery = $queryShift->unionAll($queryStatusHari);

        // Count total (need to wrap in subquery or count separately? Union count is sum of counts)
        // Or simply:
        $total = DB::table(DB::raw("({$combinedQuery->toSql()}) as combined"))
            ->mergeBindings($combinedQuery)
            ->count();

        // Fetch paginated Items
        $transactions = DB::table(DB::raw("({$combinedQuery->toSql()}) as combined"))
            ->mergeBindings($combinedQuery)
            ->orderBy('Created_At', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        /* Tiga hitungan pelengkap diambil SEKALI untuk seluruh halaman.
           Sebelumnya tiap baris menembak sampai tiga kueri sendiri, jadi satu
           halaman sepuluh baris bisa menghasilkan tiga puluh kueri yang isinya
           sama-sama "hitung baris detail milik transaksi ini". */
        $pelengkap = $this->ambilHitunganRiwayat($transactions);
        $catatanBatalAdmin = $this->catatanBatalAdminMassal([
            'N_HRIS_Transaksi_Shift_Det' => $this->nomorTransaksiPer(collect($transactions), 'SHIFT'),
            'N_HRIS_Transaksi_Status_Hari_Det' => $this->nomorTransaksiPer(collect($transactions), 'STATUS_HARI'),
        ]);

        // Set Keterangan default for STATUS_HARI and mark pending, and add tanggal_count
        foreach ($transactions as $trans) {
            $no = (string) $trans->No_Transaksi;
            $tipe = (string) $trans->transaction_type;

            if ($trans->transaction_type === 'STATUS_HARI') {
                if (empty($trans->Keterangan)) {
                    $trans->Keterangan = 'Pengajuan Offday/Libur';
                }
                $trans->tanggal_count = $pelengkap['tanggal'][$tipe][$no] ?? 0;
            } elseif ($trans->transaction_type === 'SHIFT') {
                $trans->tanggal_count = $pelengkap['tanggal'][$tipe][$no] ?? 0;
            }
            $trans->is_pending = empty($trans->Flag_Selesai) || $trans->Flag_Selesai !== 'Y';
            $trans->catatan_batal_admin = $catatanBatalAdmin[$this->kunciNomorTransaksi($no)] ?? null;
            $trans->dibatalkan_admin = $trans->Status === 'Y' && $trans->catatan_batal_admin !== null;

            if ($trans->Status === 'Y' || $trans->Flag_Selesai === 'Y') {
                $trans->can_cancel = false;

                continue;
            }

            $activeCount = $pelengkap['aktif'][$tipe][$no] ?? 0;
            if ($activeCount === 0) {
                $trans->can_cancel = false;

                continue;
            }

            $pendingCount = $pelengkap['menunggu'][$tipe][$no] ?? 0;

            $trans->can_cancel = $pendingCount === $activeCount;
        }

        return [
            'items' => $transactions,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Hitungan pelengkap riwayat untuk SELURUH halaman, bukan per baris.
     *
     * Tiga hitungan yang sama dulu diambil satu per satu untuk tiap transaksi:
     * jumlah tanggal berbeda, jumlah baris detail yang masih aktif, dan berapa
     * di antaranya yang masih menunggu persetujuan. Satu halaman sepuluh baris
     * berarti sampai tiga puluh kueri; biayanya tumbuh lurus mengikuti ukuran
     * halaman.
     *
     * Dikelompokkan per JENIS transaksi karena tabel dan kolom tanggalnya
     * berbeda — STATUS_HARI memakai Tanggal, SHIFT memakai Tanggal_Shift.
     * Paling banyak enam kueri, berapa pun isi halamannya.
     *
     * Transaksi tanpa satu pun baris detail sengaja TIDAK muncul di hasil
     * kelompok; pemanggil membacanya sebagai 0, sama persis dengan yang dulu
     * dikembalikan count() atas himpunan kosong.
     *
     * @param  iterable<int,object>  $transactions
     * @return array{tanggal:array,aktif:array,menunggu:array}
     */
    private function ambilHitunganRiwayat($transactions): array
    {
        $peta = [
            'STATUS_HARI' => ['tabel' => 'N_HRIS_Transaksi_Status_Hari_Det', 'kolom' => 'Tanggal'],
            'SHIFT' => ['tabel' => 'N_HRIS_Transaksi_Shift_Det', 'kolom' => 'Tanggal_Shift'],
        ];

        $perJenis = [];
        foreach ($transactions as $t) {
            $jenis = (string) ($t->transaction_type ?? '');
            if (! isset($peta[$jenis])) {
                continue;
            }
            $perJenis[$jenis][] = (string) $t->No_Transaksi;
        }

        $hasil = ['tanggal' => [], 'aktif' => [], 'menunggu' => []];

        foreach ($perJenis as $jenis => $noList) {
            $noList = array_values(array_unique($noList));
            $tabel = $peta[$jenis]['tabel'];
            $kolom = $peta[$jenis]['kolom'];

            $hasil['tanggal'][$jenis] = DB::table($tabel)
                ->whereIn('No_Transaksi', $noList)
                ->groupBy('No_Transaksi')
                ->selectRaw('No_Transaksi, COUNT(DISTINCT ['.$kolom.']) as jumlah')
                ->pluck('jumlah', 'No_Transaksi')
                ->map(fn ($n) => (int) $n)
                ->all();

            $hasil['aktif'][$jenis] = DB::table($tabel)
                ->whereIn('No_Transaksi', $noList)
                ->whereNull('Status')
                ->groupBy('No_Transaksi')
                ->selectRaw('No_Transaksi, COUNT(*) as jumlah')
                ->pluck('jumlah', 'No_Transaksi')
                ->map(fn ($n) => (int) $n)
                ->all();

            $hasil['menunggu'][$jenis] = DB::table($tabel)
                ->whereIn('No_Transaksi', $noList)
                ->whereNull('Status')
                ->where('Needs_Approval', 'Y')
                ->whereNull('Flag_Approval')
                ->where(function ($q) {
                    $q->whereNull('Flag_Applied')->orWhere('Flag_Applied', '!=', 'Y');
                })
                ->groupBy('No_Transaksi')
                ->selectRaw('No_Transaksi, COUNT(*) as jumlah')
                ->pluck('jumlah', 'No_Transaksi')
                ->map(fn ($n) => (int) $n)
                ->all();
        }

        return $hasil;
    }

    /**
     * Generate transaction number for tukar hari (swap day)
     * Format: TH + MM (month) + YY (year) + XXXX (sequence)
     * Example: TH02260001 (Tukar Hari, Feb 2026, sequence 0001)
     */
    protected function generateNoTransaksiTukarHari(): string
    {
        // Counter atomik (sargable, anti-duplikat) menggantikan LIKE + orderBy scan.
        $prefix = sprintf('TH%s-%s-', date('m'), date('y'));

        return app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
            $prefix,
            function () use ($prefix) {
                $last = DB::table('N_HRIS_Transaksi_Tukar_Hari')
                    ->where('No_Transaksi', 'like', $prefix.'%')
                    ->orderBy('Id', 'desc')
                    ->value('No_Transaksi');
                if (! $last) {
                    return 0;
                }
                $parts = explode('-', $last);

                return intval(end($parts));
            },
            4,
        );
    }

    /**
     * Generate transaction number
     */
    protected function generateTransactionNumber(): string
    {
        $prefix = 'SH';
        $date = Carbon::now('Asia/Jakarta')->format('ymd');
        $random = strtoupper(substr(uniqid(), -4));

        return "{$prefix}{$date}{$random}";
    }

    public function isOffDay(string $kodeKaryawan, string $date): bool
    {
        $result = DB::select('SELECT TOP 1 StatusHari FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?)', [
            '001',
            $kodeKaryawan,
            $date,
            $date,
        ]);

        $decision = ! empty($result) && str_starts_with($result[0]->StatusHari, 'OFF');

        return $decision;
    }

    public function getOffDatesInRange(string $kodeKaryawan, string $startDate, string $endDate): array
    {
        // TVF GetStatusHariRange berat & sering dipanggil ulang utk rentang sama;
        // status-hari deterministik per hari -> cache pendek per (perusahaan, karyawan, rentang).
        $rawDates = \Illuminate\Support\Facades\Cache::remember(
            'statushari.range:001:'.$kodeKaryawan.':'.$startDate.':'.$endDate.':OFF',
            300,
            fn () => DB::select("SELECT * FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?) where StatusHari like 'OFF%'", [
                '001',
                $kodeKaryawan,
                $startDate,
                $endDate,
            ]),
        );

        return collect($rawDates)
            ->map(function ($item) {
                return Carbon::parse($item->Tanggal)->format('Y-m-d');
            })
            ->values()
            ->toArray();
    }

    // public function getHariSwapDates(string $kodeKaryawan, string $date, string $type = null): array
    // {
    //     $batasBackDateTukarHari = config('services.hcis.swap_shift_request.backdate_max_days') ?? 0;
    //     $batasFutureDateTukarHari = config('services.hcis.swap_shift_request.future_max_days') ?? 0;
    //     // Ambil Tanggal Hari Day off
    //     // dd($batasBackDateTukarHari);
    //     $startDate = Carbon::parse($date)->subDays($batasBackDateTukarHari)->format('Y-m-d');
    //     $endDate = Carbon::parse($date)->addDays($batasFutureDateTukarHari)->format('Y-m-d');
    //     // dd($startDate, $endDate, $kodeKaryawan);
    //     $RawDates = [];
    //     if(!empty($type) && $type == 'WORKING_DAY') {
    //         $RawDates = DB::select("SELECT * FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?) where StatusHari like 'OFF%'", [
    //                     '001',
    //                     $kodeKaryawan,
    //                     $startDate,
    //                     $endDate
    //                 ]);
    //     } else if(!empty($type) && $type == 'DAY_OFF'){
    //     $RawDates = DB::select("SELECT * FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?) where StatusHari like 'WORK%'", [
    //                     '001',
    //                     $kodeKaryawan,
    //                     $startDate,
    //                     $endDate
    //                 ]);
    //     }else{
    //         $RawDates = DB::select("SELECT * FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?)", [
    //                     '001',
    //                     $kodeKaryawan,
    //                     $startDate,
    //                     $endDate
    //                 ]);
    //     }

    // $transaksi = DB::table('N_HRIS_Transaksi_Tukar_Hari_detail')
    //     ->where('Kode_Karyawan', $kodeKaryawan)
    //     ->whereBetween('Tanggal', [$startDate, $endDate])
    //     ->pluck('Tanggal')
    //     ->map(fn($tgl) => Carbon::parse($tgl)->format('Y-m-d')) // samakan format
    //     ->toArray();

    // $finalDates = collect($RawDates)->map(function ($item) use ($transaksi) {

    //     $explodeStatus = explode('-', $item->StatusHari);
    //     $keteranganItem = $explodeStatus[1] ?? null;
    //     $statusHariItem = $explodeStatus[0];

    //     $tanggalItem = Carbon::parse($item->Tanggal)->format('Y-m-d');

    //     // ❗ cek apakah tanggal ada di transaksi tukar hari
    //     $isTanggalTukar = in_array($tanggalItem, $transaksi);

    //     return [
    //         'tanggal'      => $tanggalItem,
    //         'status_hari'  => $statusHariItem,
    //         'keterangan'   => $keteranganItem,
    //         'isActive'     => ($keteranganItem == 'Tukar_Hari' || $isTanggalTukar) ? false : true,
    //     ];
    // })->values()->toArray();
    //     // dd($finalDates);
    //     return $finalDates;
    // }
    public function getHariSwapDates(string $kodeKaryawan, string $date, ?string $type = null): array
    {
        $batasBackDateTukarHari = config('services.hcis.swap_shift_request.backdate_max_days') ?? 0;
        $batasFutureDateTukarHari = config('services.hcis.swap_shift_request.future_max_days') ?? 0;

        // Tentukan window tanggal
        $startDate = Carbon::parse($date)->subDays($batasBackDateTukarHari)->format('Y-m-d');
        $endDate = Carbon::parse($date)->addDays($batasFutureDateTukarHari)->format('Y-m-d');

        /**
         * ============================================================
         * Ambil data kalender dari function SQL
         * ============================================================
         */
        if (! empty($type) && $type == 'WORKING_DAY') {
            $RawDates = DB::select(
                "
                SELECT * FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?)
                WHERE StatusHari LIKE 'OFF%'
            ",
                ['001', $kodeKaryawan, $startDate, $endDate],
            );
        } elseif (! empty($type) && $type == 'DAY_OFF') {
            $RawDates = DB::select(
                "
                SELECT * FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?)
                WHERE StatusHari LIKE 'WORK%'
            ",
                ['001', $kodeKaryawan, $startDate, $endDate],
            );
        } else {
            $RawDates = DB::select(
                '
                SELECT * FROM N_HRIS_FN_GetStatusHariRange(?,?,?,?)
            ',
                ['001', $kodeKaryawan, $startDate, $endDate],
            );
        }

        $transaksi = DB::table('N_HRIS_Transaksi_Tukar_Hari_detail as d')
            ->join('N_HRIS_Transaksi_Tukar_Hari as h', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->where(function ($q) use ($startDate, $endDate) {
                // Filter record yang relevan saja (bukan menyatukan tanggal!)
                $q->whereDate('d.Tanggal_Off', '>=', $startDate)
                    ->whereDate('d.Tanggal_Off', '<=', $endDate)
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->whereDate('d.Tanggal_Bekerja', '>=', $startDate)->whereDate(
                            'd.Tanggal_Bekerja',
                            '<=',
                            $endDate,
                        );
                    });
            })
            ->whereNull('h.Status') // Pastikan hanya transaksi aktif
            ->whereNull('h.Flag_Selesai')
            ->get(['d.Tanggal_Off', 'd.Tanggal_Bekerja'])
            ->flatMap(function ($row) {
                // Diperlakukan sebagai 2 tanggal TERPISAH
                return [
                    Carbon::parse($row->Tanggal_Off)->format('Y-m-d'),
                    Carbon::parse($row->Tanggal_Bekerja)->format('Y-m-d'),
                ];
            })
            ->unique()
            ->values()
            ->toArray();

        /**
         * ============================================================
         * Mapping final
         * ============================================================
         */
        $finalDates = collect($RawDates)
            ->map(function ($item) use ($transaksi) {
                $explodeStatus = explode('-', $item->StatusHari);
                $keteranganItem = $explodeStatus[1] ?? null;
                $statusHariItem = $explodeStatus[0];

                $tanggalItem = Carbon::parse($item->Tanggal)->format('Y-m-d');

                // cek exact tanggal (bukan range)
                $isTanggalTukar = in_array($tanggalItem, $transaksi);

                return [
                    'tanggal' => $tanggalItem,
                    'status_hari' => $statusHariItem,
                    'keterangan' => $isTanggalTukar ? 'Tukar_Hari' : $keteranganItem,
                    'isActive' => $keteranganItem == 'Tukar_Hari' || $isTanggalTukar ? false : true,
                ];
            })
            ->values()
            ->toArray();

        return $finalDates;
    }

    // ==========================================
    // Status Hari 3-Tier Transaction Methods
    // ==========================================

    /**
     * Generate transaction number for Status Hari
     * Format: TSH02-26-0001
     */
    protected function generateNoTransaksiStatusHari(): string
    {
        // Counter atomik (sargable, anti-duplikat) menggantikan LIKE + orderBy scan.
        $prefix = sprintf('TSH%s-%s-', date('m'), date('y'));

        return app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
            $prefix,
            function () use ($prefix) {
                $last = DB::table('N_HRIS_Transaksi_Status_Hari')
                    ->where('No_Transaksi', 'like', $prefix.'%')
                    ->orderBy('Id', 'desc')
                    ->value('No_Transaksi');
                if (! $last) {
                    return 0;
                }
                $parts = explode('-', $last);

                return intval(end($parts));
            },
            4,
        );
    }

    /**
     * Create Status Hari transaction header (Tier 1)
     */
    protected function createStatusHariHeader(
        string $jenisTransaksi = 'STATUS_HARI',
        ?string $AlasanHeader = null,
        ?AccessContext $context = null,
    ): string {
        $noTransaksi = $this->generateNoTransaksiStatusHari();
        $userId = Auth::id();

        DB::table('N_HRIS_Transaksi_Status_Hari')->insert([
            'No_Transaksi' => $noTransaksi,
            'Kode_Perusahaan' => '001',
            'Jenis_Transaksi' => $jenisTransaksi,
            'Alasan' => $AlasanHeader,
            'Status' => null,
            'Flag_Selesai' => null,
            'Created_At' => now(),
            'Created_By' => $userId,
            'Flag_Admin' => $context?->isAdmin() ? 'Y' : 'T',
        ]);

        return $noTransaksi;
    }

    /**
     * Create Status Hari detail record (Tier 2) — one per employee
     */
    protected function createStatusHariDetail(
        string $noTransaksi,
        string $kodeKaryawan,
        string $mode = 'SEMENTARA',
        ?string $alasan = null,
        ?string $filePath = null,
    ): int {
        // dd($noTransaksi, $kodeKaryawan, $mode, $alasan, $filePath);
        return DB::table('N_HRIS_Transaksi_Status_Hari_Detail')->insertGetId([
            'No_Transaksi' => $noTransaksi,
            'Kode_Perusahaan' => '001',
            'Kode_Karyawan' => $kodeKaryawan,
            'Alasan' => $alasan,
            'Target_Table' => $mode,
            'File_Path' => $filePath,
            'Flag_Approval' => null,
            'Note_Tolak' => null,
            'Status' => null,
        ]);
    }

    /**
     * Create Status Hari Det records (Tier 3) — one per date
     *
     * @param  int  $idDetail  FK to Detail (Tier 2)
     * @param  string  $noTransaksi  FK to Header
     * @param  array  $dates  Array of [{tanggal, status_hari, jenis_pengajuan, needs_approval, flag_applied?, flag_approval?}]
     */
    protected function createStatusHariDet(int $idDetail, string $noTransaksi, array $dates): void
    {
        /*
         * Cermin createShiftDet: barisnya dikumpulkan lalu ditulis sekali.
         * Sebelumnya satu INSERT per tanggal, di dalam transaksi pengajuan.
         */
        $baris = [];

        foreach ($dates as $item) {
            $needsApproval = $item['needs_approval'] ?? true;
            $baris[] = [
                'Id_Detail' => $idDetail,
                'No_Transaksi' => $noTransaksi,
                'Tanggal' => $item['tanggal'],
                'Status_Hari' => $item['status_hari'] ?? 'OFF',
                'Jenis_Pengajuan' => $item['jenis_pengajuan'] ?? 'BACKDATE',
                'Needs_Approval' => $needsApproval ? 'Y' : 'T',
                'Flag_Approval' => $needsApproval ? null : 'Y',
                'Flag_Applied' => $needsApproval ? 'T' : 'Y',
                'Note_Tolak' => null,
                'Status' => null,
            ];
        }

        PenyisipanBerbatasParameter::sisipkan('N_HRIS_Transaksi_Status_Hari_Det', $baris);
    }

    /**
     * Create leader status hari request using 3-tier structure.
     * 1 Header → N Details (per member) → M Dets (per date)
     *
     * @param  array  $members  [{kode, dates: [{tanggal, status_hari, jenis_pengajuan, needs_approval}]}]
     * @return string No_Transaksi
     */
    public function createLeaderStatusHariRequest(
        string $leaderKode,
        array $members,
        string $alasan = '',
        string $mode = 'SEMENTARA',
        ?AccessContext $context = null,
    ): string {
        // Tier 1: Header
        $noTransaksi = $this->createStatusHariHeader('STATUS_HARI', $alasan, $context);

        $allNoApproval = true;

        foreach ($members as $member) {
            $memberKode = $member['kode'];
            $memberDates = $member['dates'] ?? [];

            if (empty($memberDates)) {
                continue;
            }

            // Tier 2: Detail per employee
            $detailId = $this->createStatusHariDetail($noTransaksi, $memberKode, $mode, $alasan);

            // Build formatted dates
            $formattedDates = [];
            foreach ($memberDates as $dateItem) {
                $needsApproval = $dateItem['needs_approval'] ?? true;
                if ($needsApproval) {
                    $allNoApproval = false;
                }
                $formattedDates[] = [
                    'tanggal' => $dateItem['tanggal'],
                    'status_hari' => $dateItem['status_hari'] ?? 'OFF',
                    'jenis_pengajuan' => $dateItem['jenis_pengajuan'] ?? 'BACKDATE',
                    'needs_approval' => $needsApproval,
                ];
            }

            // Tier 3: Det per date
            $this->createStatusHariDet($detailId, $noTransaksi, $formattedDates);

            // Update Detail approval based on its Det records
            $this->syncStatusHariDetailApproval($detailId);
        }

        // If all dates don't need approval, mark header as done
        if ($allNoApproval) {
            DB::table('N_HRIS_Transaksi_Status_Hari')
                ->where('No_Transaksi', $noTransaksi)
                ->update([
                    'Flag_Selesai' => 'Y',
                    'Updated_At' => now(),
                ]);
        }

        return $noTransaksi;
    }

    /**
     * Sync Detail (Tier 2) approval status based on its Det (Tier 3) records
     */
    protected function syncStatusHariDetailApproval(int $detailId): void
    {
        $dets = DB::table('N_HRIS_Transaksi_Status_Hari_Det')
            ->where('Id_Detail', $detailId)
            ->whereNull('Status')
            ->get();

        if ($dets->isEmpty()) {
            return;
        }

        $allApproved = $dets->every(fn ($d) => $d->Flag_Approval === 'Y');
        $anyRejected = $dets->contains(fn ($d) => $d->Flag_Approval === 'T');

        if ($allApproved) {
            DB::table('N_HRIS_Transaksi_Status_Hari_Detail')
                ->where('Id', $detailId)
                ->update(['Flag_Approval' => 'Y']);
        } elseif ($anyRejected) {
            DB::table('N_HRIS_Transaksi_Status_Hari_Detail')
                ->where('Id', $detailId)
                ->update(['Flag_Approval' => 'T']);
        }
    }

    /**
     * Build Source_Row_Key for Shift Det record
     * Format: Kode_Perusahaan|No_Transaksi|Id
     *
     * @param  int  $detId  N_HRIS_Transaksi_Shift_Det.Id
     * @param  string  $noTransaksi  Transaction number
     * @return string|null Source_Row_Key or null if not found
     */
    private function buildSourceRowKeyForShiftDet(int $detId, string $noTransaksi): ?string
    {
        try {
            $shiftDet = DB::table('N_HRIS_Transaksi_Shift_Det as det')
                ->join('N_HRIS_Transaksi_Shift as h', 'det.No_Transaksi', '=', 'h.No_Transaksi')
                ->where('det.Id', $detId)
                ->where('det.No_Transaksi', $noTransaksi)
                ->whereNull('h.Status') // Pastikan transaksi aktif
                ->whereNull('det.Status') // Pastikan det aktif
                ->select('det.Kode_Perusahaan', 'det.No_Transaksi', 'det.Id')
                ->first();

            if ($shiftDet) {
                return implode('|', [$shiftDet->Kode_Perusahaan, $shiftDet->No_Transaksi, $shiftDet->Id]);
            }
        } catch (\Exception $e) {
            Log::error('Error building Source_Row_Key: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Build Source_Row_Key for Status Hari Det record
     * Format: No_Transaksi|Id
     *
     * @param  int  $detId  N_HRIS_Transaksi_Status_Hari_Det.Id
     * @param  string  $noTransaksi  Transaction number
     * @return string|null Source_Row_Key or null if not found
     */
    /**
     * Buat baris HRIS_Approval_Request_Detail untuk setiap langkah approval
     * transaksi ini, satu salinan per approver (model terisolasi, sama seperti
     * SHIFT di submit()). Langkah yang sudah punya baris dilewati,
     * jadi aman dipanggil ulang. Kegagalan hanya dicatat: pengajuan tetap sah
     * tanpa baris tracking, hanya riwayatnya kehilangan rincian per tanggal.
     */
    public function buatDetailTrackingApproval(string $noTransaksi, string $sourceView): void
    {
        try {
            $approvalReqs = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNull('Status')
                ->orderBy('Request_Order_Flow', 'asc')
                ->get();

            foreach ($approvalReqs as $approvalReq) {
                $exists = DB::table('HRIS_Approval_Request_Detail')
                    ->where('Id_Approval_Request', $approvalReq->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $idJenisApproval = (int) ($approvalReq->Id_Jenis_Approval ?? 0);
                if ($idJenisApproval <= 0) {
                    Log::channel('ApprovalDetailService')->warning('Id_Jenis_Approval missing on approval request', [
                        'no_transaksi' => $noTransaksi,
                        'id_approval_request' => $approvalReq->id,
                        'source_view' => $sourceView,
                    ]);

                    continue;
                }

                $this->approvalDetailService->createFromSourceView(
                    $approvalReq->id,
                    $noTransaksi,
                    $sourceView,
                    $idJenisApproval,
                );
            }
        } catch (\Exception $e) {
            Log::channel('ApprovalDetailService')->error('Exception creating approval details', [
                'error' => $e->getMessage(),
                'no_transaksi' => $noTransaksi,
                'source_view' => $sourceView,
            ]);
        }
    }

    private function buildSourceRowKeyForStatusHariDet(int $detId, string $noTransaksi): ?string
    {
        try {
            $statusDet = DB::table('N_HRIS_Transaksi_Status_Hari_Det as det')
                ->where('det.Id', $detId)
                ->where('det.No_Transaksi', $noTransaksi)
                ->select('det.No_Transaksi', 'det.Id')
                ->first();

            if ($statusDet) {
                return implode('|', [$statusDet->No_Transaksi, $statusDet->Id]);
            }
        } catch (\Exception $e) {
            Log::error('Error building Status Hari Source_Row_Key: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Build map of latest rejection info by Source_Row_Key.
     *
     * @return array<string, array{Processed_By_Kode:?string,Processed_By_Nama:?string,Processed_At:mixed}>
     */
    private function getRejectionInfoMapBySourceKeys(array $sourceRowKeys): array
    {
        $sourceRowKeys = array_values(array_filter(array_unique($sourceRowKeys)));

        if (empty($sourceRowKeys)) {
            return [];
        }

        $rows = DB::table('HRIS_Approval_Request_Detail as ard')
            ->leftJoin('Karyawan as k', 'ard.Processed_By_Kode', '=', 'k.Kode_Karyawan')
            ->whereIn('ard.Source_Row_Key', $sourceRowKeys)
            ->where('ard.Flag_Approval', 'T')
            ->whereNotNull('ard.Processed_By_Kode')
            ->orderBy('ard.Processed_At', 'desc')
            ->orderBy('ard.Id', 'desc')
            ->select('ard.Source_Row_Key', 'ard.Processed_By_Kode', 'k.Nama as Processed_By_Nama', 'ard.Processed_At')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            if (! isset($map[$row->Source_Row_Key])) {
                $map[$row->Source_Row_Key] = [
                    'Processed_By_Kode' => $row->Processed_By_Kode,
                    'Processed_By_Nama' => $row->Processed_By_Nama,
                    'Processed_At' => $row->Processed_At,
                ];
            }
        }

        return $map;
    }

    /**
     * Apply status hari to master table for an existing transaction
     * Used when dates don't need approval (direct apply)
     */
    public function applyStatusHariDirectForExistingTransaction(
        string $noTransaksi,
        string $memberKode,
        string $tanggal,
        string $statusHari,
        ?int $hari = null,
        ?string $alasan = '',
        ?string $mode = 'SEMENTARA',
    ): void {
        $mode = strtoupper(trim((string) $mode));
        $this->pastikanTargetShiftValid($mode);

        // dd($noTransaksi, $memberKode, $tanggal, $statusHari, $hari, $alasan, $mode);
        // Mark existing Det record as applied
        $detailIds = DB::table('N_HRIS_Transaksi_Status_Hari_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Kode_Karyawan', $memberKode)
            ->pluck('Id');

        if ($detailIds->isNotEmpty()) {
            DB::table('N_HRIS_Transaksi_Status_Hari_Det')
                ->whereIn('Id_Detail', $detailIds)
                ->where('Tanggal', $tanggal)
                ->update([
                    'Flag_Applied' => 'Y',
                    'Flag_Approval' => 'Y',
                    // 'Needs_Approval' => 'T',
                ]);
        }

        // Apply to master table (N_HRIS_Status_Hari_Karyawan)
        $tglParsed = Carbon::parse($tanggal);
        $hariValue = $hari ?? $this->mapDayToHris($tglParsed);

        if ($mode === 'PERMANEN') {
            $cekOffPermanen = DB::table('N_HRIS_Status_Hari_Karyawan')
                ->where('Kode_Karyawan', $memberKode)
                ->where('Tgl_Mulai', '<=', $tanggal)
                ->where(function ($q) use ($tanggal) {
                    $q->whereNull('Tgl_Selesai')->orWhere('Tgl_Selesai', '>=', $tanggal);
                })
                ->where('No_Transaksi_Reference', 'not like', 'TH%') // pastikan bukan transaksi tukar hari
                ->where('Status_Hari', 'OFF')
                ->where('Flag_Aktif', 'Y')
                ->first();

            // dd($cekOffPermanen);
            if ($cekOffPermanen) {
                $tglAkhirMingguKmrn = $tglParsed->copy()->previous(Carbon::SUNDAY);
                $tglMulaiOff = Carbon::parse($cekOffPermanen->Tgl_Mulai)->startOfDay();
                if ($tglAkhirMingguKmrn->lt($tglMulaiOff)) {
                    $tglAkhirMingguKmrn = $tglMulaiOff;
                }

                $tglAkhirMingguKmrn = $tglAkhirMingguKmrn->format('Y-m-d');
                $this->stopActiveOverride($cekOffPermanen->Id, $tglAkhirMingguKmrn);
            }
            // dd($tglAkhirMingguKmrn);

            // Timpa Hari Libur Default Shift nya
            $getShiftPermanen = DB::table('HRIS_Shift_Per_Karyawan as spk')
                ->join('HRIS_Shift_Kerja_Detail as skd', 'spk.Id_Shift', '=', 'skd.Id_Shift')
                ->where('spk.Kode_Karyawan', $memberKode)
                ->where('spk.Periode', '<=', $tanggal)
                ->where('skd.Flag_Default_Off', 'Y')
                ->select('skd.Hari')
                ->orderBy('spk.created_at', 'desc')
                ->orderBy('spk.Urut', 'desc')
                ->first();

            // dd($getShiftPermanen);
            if ($getShiftPermanen) {
                $hariTimpa = $getShiftPermanen->Hari;
                DB::table('N_HRIS_Status_Hari_Karyawan')->insert([
                    'Kode_Perusahaan' => '001',
                    'Kode_Karyawan' => $memberKode,
                    'Status_Hari' => 'WORK',
                    'Hari' => $hariTimpa,
                    'Tgl_Mulai' => $tanggal,
                    'Tgl_Selesai' => null, // open-ended for permanent
                    'Flag_Aktif' => 'Y',
                    'No_Transaksi_Reference' => $noTransaksi,
                    'Keterangan' => "Timpa Hari Libur Default Shift ($hariTimpa) dengan $statusHari - $alasan",
                    'Created_At' => now(),
                    'Created_By' => Auth::id(),
                ]);
            }

            // For permanent change, we might want to set a future end date or handle differently
            DB::table('N_HRIS_Status_Hari_Karyawan')->insert([
                'Kode_Perusahaan' => '001',
                'Kode_Karyawan' => $memberKode,
                'Status_Hari' => $statusHari,
                'Hari' => $hariValue,
                'Tgl_Mulai' => $tanggal,
                'Tgl_Selesai' => null, // open-ended for permanent
                'Flag_Aktif' => 'Y',
                'No_Transaksi_Reference' => $noTransaksi,
                'Keterangan' => $alasan,
                'Created_At' => now(),
                'Created_By' => Auth::id(),
            ]);
        } else {
            DB::table('N_HRIS_Status_Hari_Karyawan')->insert([
                'Kode_Perusahaan' => '001',
                'Kode_Karyawan' => $memberKode,
                'Status_Hari' => $statusHari,
                'Hari' => $hariValue,
                'Tgl_Mulai' => $tanggal,
                'Tgl_Selesai' => $tanggal,
                'Flag_Aktif' => 'Y',
                'No_Transaksi_Reference' => $noTransaksi,
                'Keterangan' => $alasan,
                'Created_At' => now(),
                'Created_By' => Auth::id(),
            ]);
        }

        if ($mode === 'PERMANEN') {
            $this->attendanceDirtyResolver->markPermanentEffectByKodeKaryawan(
                $memberKode,
                $tanggal,
                'STATUS_HARI_PERMANEN_CHANGED',
                'SHIFT_STATUS_HARI_DIRECT_APPLY',
                1,
            );
        } else {
            $this->attendanceDirtyResolver->markByKodeKaryawan(
                $memberKode,
                $tanggal,
                $tanggal,
                'STATUS_HARI_CHANGED',
                'SHIFT_STATUS_HARI_DIRECT_APPLY',
                1,
            );
        }
    }

    public function getRequesterFromTrasaction($noTransaksi)
    {
        $Kode_Karyawan = DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $noTransaksi)
            ->value('Kode_Karyawan');

        return DB::table('Karyawan')->where('Kode_Karyawan', $Kode_Karyawan)->first();
    }

    public function notifEmployeeForStatusHari(
        string $kodeKaryawan,
        string $noTransaksi,
        string $namaRequester,
        string $namaKaryawan,
        string $hpKaryawan,
        array $tanggal,
        string $statusHari,
        string $mode = 'SEMENTARA',
    ): void {
        $cekAdmin = DB::table('N_HRIS_Transaksi_Status_Hari')->where('No_Transaksi', $noTransaksi)->value('Flag_Admin');

        if ($cekAdmin == 'Y') {
            return; // Skip notification for admin transactions
        }

        try {
            $tanggalFormatted =
                $mode == 'PERMANEN'
                    ? 'Libur default diubah ke Hari *'.
                        Carbon::parse($tanggal[0])->locale('id')->isoFormat('dddd').
                        '*'
                    : FormatTanggalHelper::formatTanggalMultiple($tanggal);
            // dd($tanggalFormatted);
            $pesan = $this->whatsappMessageBuilder->Notif_Karyawan_Perubahan_Shift(
                $hpKaryawan,
                $namaKaryawan,
                'DAY OFF',
                $statusHari,
                $namaRequester,
                $tanggalFormatted,
            );
            $this->whatsappLogHelper->sendAndLog($kodeKaryawan, $namaKaryawan, $hpKaryawan, $noTransaksi, $pesan);
        } catch (\Throwable $e) {
            Log::error('WA Notification Failed (Status Hari Apply): '.$e->getMessage(), [
                'kode_karyawan' => $kodeKaryawan,
                'nama_karyawan' => $namaKaryawan,
                'hp_karyawan' => $hpKaryawan,
            ]);
        }
    }

    /**
     * Apakah baris detail ini SUDAH dibatalkan (Status terisi)?
     *
     * Dulu metode ini mencatat warning "Detail record not found for cancellation
     * check" setiap kali tak menemukan baris. Itu keliru dua kali: kueri di sini
     * menyaring `Status IS NOT NULL`, jadi "tidak ketemu" adalah jawaban NORMAL
     * dan benar — barisnya belum dibatalkan — bukan anomali; dan pesannya
     * mengatakan barisnya tak ada, padahal yang tak ada hanyalah baris
     * ber-Status. Terekam di production 9 September 2026 sebagai peringatan
     * berulang untuk detail_id yang sehat, mengubur peringatan yang sungguhan.
     */
    public function isRowCancelled(string $noTransaksi, int $detailId, string $namaTable): bool
    {
        return $this->barisDibatalkan($noTransaksi, [$detailId], $namaTable) !== [];
    }

    /**
     * Versi banyak baris: satu kueri untuk seluruh detail dalam satu keputusan
     * approval, bukan satu kueri per baris di dalam filter pemanggilnya.
     *
     * @param  array<int,int|string>  $detailIds
     * @return array<int,int> id yang SUDAH dibatalkan
     */
    public function barisDibatalkan(string $noTransaksi, array $detailIds, string $namaTable): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($detailIds, 'is_numeric'))));

        if ($ids === []) {
            return [];
        }

        $dibatalkan = [];

        // Dipotong: SQL Server berhenti di 2.100 parameter, dan satu keputusan
        // approval massal bisa membawa ratusan tanggal.
        foreach (array_chunk($ids, 1000) as $bagian) {
            $dibatalkan = array_merge($dibatalkan, DB::table($namaTable)
                ->whereNotNull('Status')
                ->where('No_Transaksi', $noTransaksi)
                ->whereIn('id', $bagian)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all());
        }

        return $dibatalkan;
    }
}
