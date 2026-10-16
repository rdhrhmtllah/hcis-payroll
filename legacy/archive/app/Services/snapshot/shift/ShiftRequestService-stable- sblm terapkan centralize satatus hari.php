<?php

namespace App\Services;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\WhatsappLogHelper;
use App\Services\Approval\ApprovalFlowService;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use App\Services\Notification\WhatsappMessageBuilder;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for Self Shift Request feature
 *
 * Handles shift request submission, approval checking, and shift application.
 */
class ShiftRequestService
{
    protected ApprovalFlowService $approvalFlowService;

    protected WhatsappLogHelper $whatsappLogHelper;

    protected WhatsappMessageBuilder $whatsappMessageBuilder;

    protected array $config = [];

    protected array $swapConfig = [];
    // Configuration
    // protected array $config = [
    //     'backdate_needs_approval' => config('services.hcis.shift_request.backdate_needs_approval', true),
    //     'same_day_needs_approval' => config('services.hcis.shift_request.same_day_needs_approval', true),
    //     'future_needs_approval' => config('services.hcis.shift_request.future_needs_approval', true),
    //     'backdate_max_days' => config('services.hcis.shift_request.backdate_max_days', 7),
    //     'future_max_days' => config('services.hcis.shift_request.future_max_days', 30)
    // ];

    public function __construct(ApprovalFlowService $approvalFlowService, WhatsappLogHelper $whatsappLogHelper, WhatsappMessageBuilder $whatsappMessageBuilder)
    {
        $this->approvalFlowService = $approvalFlowService;
        $this->whatsappLogHelper = $whatsappLogHelper;
        $this->whatsappMessageBuilder = $whatsappMessageBuilder;

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
     * Submit shift request
     *
     * @param  array  $data  Request data containing target_table, alasan, dates[]
     * @param  string  $kodeKaryawan  Requester's kode
     * @return array Result with applied and pending counts
     */
    public function submit(array $data, ?string $kodeKaryawan): array
    {
        return DB::transaction(function () use ($data, $kodeKaryawan) {
            // dd($data, $kodeKaryawan);
            $namaRequester = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->value('Nama');
            $hpRequester = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->value('HP');
            $targetTable = $data['target_table'];
            $alasan = $data['alasan'] ?? null;
            $dates = $data['dates'];

            // Check for pending requests on these dates
            $pendingDates = $this->checkPendingRequest($dates, $kodeKaryawan);
            if (! empty($pendingDates)) {
                $dateList = implode(', ', $pendingDates);
                throw new \Exception("Tanggal berikut masih memiliki pengajuan shift (Status Pending/On Process): $dateList");
            }

            $approvers = DB::table('N_HRIS_Approval_Flow_Shift')
                ->where('Kode_Karyawan_Requester', $kodeKaryawan)
                ->whereNull('Status')
                ->orderBy('order_flow', 'asc')
                ->get();

            if ($approvers->isEmpty()) {
                throw new \Exception('Anda tidak memiliki approval flow untuk pengajuan shift');
            }

            $needsApprovalDates = [];
            $directApplyDates = [];

            // Classify dates by approval requirement
            foreach ($dates as $item) {
                $tanggal = Carbon::parse($item['tanggal']);
                $jenisPengajuan = $this->determineJenisPengajuan($tanggal);
                $needsApproval = $this->checkNeedsApproval($jenisPengajuan);

                $item['jenis_pengajuan'] = $jenisPengajuan;
                $item['needs_approval'] = $needsApproval;

                if ($needsApproval) {
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
            // dd($directApplyDates, $needsApprovalDates);
            $noTransaksi = $this->createTransaction($kodeKaryawan, $targetTable, $alasan);
            // 1. Apply direct dates (no approval needed)
            if (! empty($directApplyDates)) {
                $details = $this->createTransactionDetail($noTransaksi, $directApplyDates, 'Y', 'Y');

                foreach ($directApplyDates as $item) {
                    // dd($item);
                    $this->applyShiftDirect($kodeKaryawan, $item, $targetTable);
                    $result['applied_directly']['count']++;
                    $result['applied_directly']['dates'][] = $item['tanggal'];
                }

                if (empty($needsApprovalDates)) {
                    DB::table('N_HRIS_Transaksi_Shift')->where('No_Transaksi', $noTransaksi)->update([
                        'Flag_Selesai' => 'Y',
                    ]);
                }
            }
            // 2. Create transaction for approval-needed dates
            if (count($needsApprovalDates) > 0) {
                $details = $this->createTransactionDetail($noTransaksi, $needsApprovalDates);

                // =========================================================
                // INTEGRATION WITH APPROVAL SYSTEM
                // =========================================================
                // Create approval flow using existing service (reusing HRIS_Approval_Request)
                // Default ID Jenis Approval for Shift is assumed to be 8 (Atasan Langsung) or similar.
                // Adjust if needed. Using 8 for now as standard supervisor approval.
                $flowResult = $this->approvalFlowService->createFlowForLegacySingleAppro($noTransaksi, $kodeKaryawan, 3);
                if ($flowResult->success == false) {
                    throw new \Exception($flowResult->error);
                }
                $result['pending_approval']['count'] = count($needsApprovalDates);
                $result['pending_approval']['no_transaksi'] = $noTransaksi;
                $result['pending_approval']['dates'] = array_column($needsApprovalDates, 'tanggal');

                $firstApprover = $this->getKaryawanName($flowResult->getFirstApproverKode());
                $onlyDate = array_column($needsApprovalDates, 'tanggal');
                $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($onlyDate);
                // dd($tanggalFormated);
                $hpApprover = $this->getKaryawanHp($flowResult->getFirstApproverKode());

                $pesan = $this->whatsappMessageBuilder->approverShiftNotification($hpApprover, $noTransaksi, $namaRequester, $tanggalFormated);
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
                $this->whatsappLogHelper->sendAndLog($flowResult->getFirstApproverKode(), $firstApprover, $hpApprover, $noTransaksi, $pesan, $updateWaCounter);

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
            $kodePerusahaan = DB::table('Karyawan')
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->value('Kode_Perusahaan') ?? '001';

            // Check for pending requests on these dates
            $pendingDates = $this->checkPendingRequest(
                [['tanggal' => $tanggalBekerja], ['tanggal' => $tanggalOff]],
                $kodeKaryawan
            );
            if (! empty($pendingDates)) {
                $dateList = implode(', ', $pendingDates);
                throw new \Exception("Tanggal berikut masih memiliki pengajuan shift (Status Pending/On Process): $dateList");
            }

            // Verify approval flow exists
            $approvers = DB::table('N_HRIS_Approval_Flow_Shift')
                ->where('Kode_Karyawan_Requester', $kodeKaryawan)
                ->whereNull('Status')
                ->orderBy('order_flow', 'asc')
                ->get();

            if ($approvers->isEmpty()) {
                throw new \Exception('Anda tidak memiliki approval flow untuk pengajuan shift');
            }

            // Determine if approval is needed based on dates
            $tanggalBekerjaCarbon = Carbon::parse($tanggalBekerja);
            $tanggalOffCarbon = Carbon::parse($tanggalOff);
            $needsApprovalBekerja = $this->checkNeedsApprovalSwap($this->determineJenisPengajuan($tanggalBekerjaCarbon));
            $needsApprovalOff = $this->checkNeedsApprovalSwap($this->determineJenisPengajuan($tanggalOffCarbon));
            $needsApproval = $needsApprovalBekerja || $needsApprovalOff;

            // Generate transaction number
            $noTransaksi = $this->generateNoTransaksiTukarHari();

            // Create header in N_HRIS_Transaksi_Tukar_Hari
            DB::table('N_HRIS_Transaksi_Tukar_Hari')->insert([
                'No_Transaksi' => $noTransaksi,
                'Kode_Perusahaan' => $kodePerusahaan,
                'Kode_Karyawan' => $kodeKaryawan,
                'Alasan' => $alasan,
                'Created_By' => $kodeKaryawan,
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
                    'SEMENTARA'
                );
                $result['status'] = 'approved_and_applied';
            } else {
                // Create approval flow if approval is needed
                $flowResult = $this->approvalFlowService->createFlowForLegacySingleAppro(
                    $noTransaksi,
                    $kodeKaryawan,
                    3 // Approval type for shift
                );

                if ($flowResult->success == false) {
                    throw new \Exception($flowResult->error);
                }

                // Send WhatsApp notification to first approver
                $firstApproverKode = $flowResult->getFirstApproverKode();
                $firstApprover = $this->getKaryawanName($firstApproverKode);
                $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple([$tanggalBekerja, $tanggalOff]);
                $hpApprover = $this->getKaryawanHp($firstApproverKode);

                $pesan = $this->whatsappMessageBuilder->approverShiftNotification(
                    $hpApprover,
                    $noTransaksi,
                    $namaRequester,
                    $tanggalFormated
                );

                $updateWaCounter = function () use ($noTransaksi, $firstApproverKode) {
                    DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Approver_Karyawan_Kode', $firstApproverKode)
                        ->update([
                            'angka_wa' => DB::raw('ISNULL(angka_wa, 0) + 1'),
                        ]);
                };

                try {
                    $this->whatsappLogHelper->sendAndLog(
                        $firstApproverKode,
                        $firstApprover,
                        $hpApprover,
                        $noTransaksi,
                        $pesan,
                        $updateWaCounter
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
    protected function generateNoTransaksi(): string
    {
        $prefix = 'SH';
        $kodePerusahaan = '01';
        $tahun = date('y');
        $bulan = date('m');

        // Get last sequence
        $lastTrans = DB::table('N_HRIS_Transaksi_Shift')
            ->where('No_Transaksi', 'like', "{$prefix}{$bulan}-{$tahun}-%")
            ->orderBy('Id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastTrans) {
            $parts = explode('-', $lastTrans->No_Transaksi);
            $sequence = intval(end($parts)) + 1;
        }

        return sprintf('%s%s-%s-%04d', $prefix, $bulan, $tahun, $sequence);
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
    protected function createHeader(string $jenisTransaksi, ?string $alasan = null): string
    {
        $noTransaksi = $this->generateNoTransaksi();
        $userId = Auth::id() ?? 'system';

        DB::table('N_HRIS_Transaksi_Shift')->insert([
            'No_Transaksi' => $noTransaksi,
            'Kode_Perusahaan' => '001',
            'Alasan' => $alasan,
            'Jenis_Transaksi' => $jenisTransaksi,
            'Status' => null,
            'Flag_Selesai' => null,
            'Created_At' => now(),
            'Created_By' => $userId,
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
        ?string $filePath = null
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
        foreach ($dates as $item) {
            DB::table('N_HRIS_Transaksi_Shift_Det')->insert([
                'Kode_Perusahaan' => '001',
                'Id_Detail' => $idDetail,
                'No_Transaksi' => $noTransaksi,
                'Tanggal_Shift' => $item['tanggal'],
                'Id_Shift_Sebelumnya' => $item['id_shift_sebelumnya'] ?? null,
                'Id_Shift_Diminta' => $item['id_shift'],
                'Jenis_Pengajuan' => $item['jenis_pengajuan'], // BACKDATE/FUTURE/HARI_H
                'Alasan' => $item['alasan'] ?? null,
                'Needs_Approval' => ($item['needs_approval'] ?? false) ? 'Y' : 'T',
                'Flag_Applied' => $item['flag_applied'] ?? null,
                'Flag_Approval' => $item['flag_approval'] ?? null,
                'Note_Tolak' => null,
            ]);
        }
    }

    /**
     * Update Detail (Tier 2) approval status based on its Det (Tier 3) records
     * A Detail is approved if ALL its Dets are approved
     */
    protected function updateDetailApprovalStatus(string $noTransaksi): void
    {
        $details = DB::table('N_HRIS_Transaksi_Shift_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->get();

        foreach ($details as $detail) {
            $allDets = DB::table('N_HRIS_Transaksi_Shift_Det')
                ->where('Id_Detail', $detail->Id)
                ->get();

            if ($allDets->isEmpty()) {
                continue;
            }

            // Check if all Dets are approved
            $allApproved = $allDets->every(fn ($det) => $det->Flag_Approval === 'Y');
            $anyRejected = $allDets->contains(fn ($det) => $det->Flag_Approval === 'T');

            if ($allApproved) {
                DB::table('N_HRIS_Transaksi_Shift_Detail')
                    ->where('Id', $detail->Id)
                    ->update(['Flag_Approval' => 'Y']);
            } elseif ($anyRejected) {
                DB::table('N_HRIS_Transaksi_Shift_Detail')
                    ->where('Id', $detail->Id)
                    ->update(['Flag_Approval' => 'T']);
            }
        }
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
        $userId = Auth::id() ?? 'system';

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
    protected function createTransactionDetail(string $noTransaksi, array $dates, ?string $flagApproval = null, ?string $flagApplied = null): void
    {
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
            ];
        }, $dates);

        // Use stored Detail ID or fetch it
        $idDetail = $this->lastCreatedDetailId;
        if (! $idDetail) {
            // Fallback: get the latest detail for this transaction
            $detail = DB::table('N_HRIS_Transaksi_Shift_Detail')
                ->where('No_Transaksi', $noTransaksi)
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
    protected function applyShiftDirect(string $kodeKaryawan, array $item, string $targetTable, ?string $noTransaksiReference = null): void
    {
        $userId = Auth::id() ?? 'system';

        if ($targetTable === 'SEMENTARA') {
            // Check if exists
            // $existing = DB::table('HRIS_Shift_Sementara')
            //     ->where('Kode_Karyawan', $kodeKaryawan)
            //     ->where('Tanggal', $item['tanggal'])
            //     ->first();

            DB::table('HRIS_Shift_Sementara')->insert([
                'Kode_Perusahaan' => '001',
                'Kode_Karyawan' => $kodeKaryawan,
                'ID_Shift' => $item['id_shift'],
                'Tanggal' => $item['tanggal'],
                'UserID' => $kodeKaryawan,
                'No_Transaksi_Reference' => $noTransaksiReference,
                'created_at' => now(),
            ]);

        } else {
            // PERMANEN - insert to HRIS_Shift_Per_Karyawan
            DB::table('HRIS_Shift_Per_Karyawan')->insert([
                'Kode_Perusahaan' => '001',
                'Kode_Karyawan' => $kodeKaryawan,
                'ID_Shift' => $item['id_shift'],
                'Periode' => $item['tanggal'],
                'UserID' => $kodeKaryawan,
                'No_Transaksi_Reference' => $noTransaksiReference,
                'created_at' => now(),
            ]);
        }
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
        $header = DB::table('N_HRIS_Transaksi_Shift')
            ->where('No_Transaksi', $noTransaksi)
            ->first();

        if (! $header) {
            return;
        }

        // Get all approved Dets directly (supports partial approval)
        // Join to Detail for Kode_Karyawan and Target_Table
        $approvedDets = DB::table('N_HRIS_Transaksi_Shift_Det as det')
            ->join('N_HRIS_Transaksi_Shift_Detail as d', 'det.Id_Detail', '=', 'd.Id')
            ->where('det.No_Transaksi', $noTransaksi)
            ->where('det.Flag_Approval', 'Y')
            ->where(function ($q) {
                $q->whereNull('det.Flag_Applied')
                    ->orWhere('det.Flag_Applied', '!=', 'Y');
            })
            ->select('det.*', 'd.Kode_Karyawan', 'd.Target_Table')
            ->get();

        foreach ($approvedDets as $det) {
            $item = [
                'tanggal' => $det->Tanggal_Shift,
                'id_shift' => $det->Id_Shift_Diminta,
            ];

            $this->applyShiftDirect($det->Kode_Karyawan, $item, $det->Target_Table, $noTransaksi);

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
                'Updated_At' => now(),
            ]);
    }

    /**
     * Apply shifts and mark tukar hari transaction as completed after approval
     * For TukarHari: Only applies shift for Tanggal_Bekerja (working date)
     * Tanggal_Off is just recorded, no shift application needed
     */
    public function applyTukarHariAfterApproval(string $noTransaksi): void
    {
        $header = DB::table('N_HRIS_Transaksi_Tukar_Hari')
            ->where('No_Transaksi', $noTransaksi)
            ->first();

        if (! $header) {
            return;
        }

        $details = DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Flag_Approval', 'Y')
            ->get();

        foreach ($details as $detail) {

            // Insert OFF date
            if (! empty($detail->Tanggal_Off)) {
                DB::table('N_HRIS_Tukar_Hari_Efektif')->insert([
                    'Kode_Perusahaan' => $header->Kode_Perusahaan,
                    'Kode_Karyawan' => $header->Kode_Karyawan,
                    'Tanggal' => $detail->Tanggal_Off,
                    'No_Transaksi' => $noTransaksi,
                    'Status_Hari' => 'OFF-Tukar_Hari',
                    'Created_At' => now(),
                ]);
            }

            // Insert WORK date
            if (! empty($detail->Tanggal_Bekerja)) {
                DB::table('N_HRIS_Tukar_Hari_Efektif')->insert([
                    'Kode_Perusahaan' => $header->Kode_Perusahaan,
                    'Kode_Karyawan' => $header->Kode_Karyawan,
                    'Tanggal' => $detail->Tanggal_Bekerja,
                    'No_Transaksi' => $noTransaksi,
                    'Id_Shift_Bekerja' => $detail->Id_Shift_Bekerja,
                    'Status_Hari' => 'WORK',
                    'Created_At' => now(),
                ]);

                // Apply shift to HRIS_Shift_Sementara
                $item = [
                    'tanggal' => $detail->Tanggal_Bekerja,
                    'id_shift' => $detail->Id_Shift_Bekerja,
                ];

                $this->applyShiftDirect($header->Kode_Karyawan, $item, 'SEMENTARA', $noTransaksi);
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

        // === QUERY 1: SHIFT APPROVALS ===
        $queryShift = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Shift as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('N_HRIS_Transaksi_Shift_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'kr.Id_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->where('ar.Approver_Karyawan_Kode', $kodeApprover)
            ->whereNull('h.Status') // Exclude cancelled
            ->whereNull('ar.Flag_Approval'); // Pending only

        // === QUERY 2: TUKAR HARI APPROVALS ===
        $queryTukarHari = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Tukar_Hari as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->join('Karyawan as k', 'h.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'kr.Id_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->where('ar.Approver_Karyawan_Kode', $kodeApprover)
            ->whereNull('h.Status') // Not cancelled (Status NULL = aktif)
            ->whereNull('ar.Flag_Approval'); // Pending only

        // === APPLY FILTERS TO BOTH ===
        // Filter: Jabatan
        if (! empty($filters['jabatan'])) {
            $queryShift->where('vg.Id_Jabatan', $filters['jabatan']);
            $queryTukarHari->where('vg.Id_Jabatan', $filters['jabatan']);
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
        }

        // Filter: Date Range
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta');
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta');

            $queryShift->whereExists(function ($subquery) use ($startDate, $endDate) {
                $subquery->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Detail as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '>=', $startDate)
                    ->whereDate('detail.Tanggal_Shift', '<=', $endDate);
            });

            $queryTukarHari->where(function ($q) use ($startDate, $endDate) {
                $q->whereDate('d.Tanggal_Bekerja', '>=', $startDate)
                    ->whereDate('d.Tanggal_Bekerja', '<=', $endDate);
            });
        } elseif (! empty($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta');
            $queryShift->whereExists(function ($subquery) use ($startDate) {
                $subquery->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Detail as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '>=', $startDate);
            });
            $queryTukarHari->whereDate('d.Tanggal_Bekerja', '>=', $startDate);
        } elseif (! empty($filters['end_date'])) {
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta');
            $queryShift->whereExists(function ($subquery) use ($endDate) {
                $subquery->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Detail as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '<=', $endDate);
            });
            $queryTukarHari->whereDate('d.Tanggal_Bekerja', '<=', $endDate);
        }

        // === GET DATA ===
        $itemsShift = $queryShift
            ->select(
                'h.*',
                'd.Target_Table',
                'ar.Id as approval_id',
                'ar.Id_Jenis_Approval',
                'ar.No_Transaksi',
                'ar.Request_Order_Flow',
                'ar.Created_At as Request_Date',
                'k.Nama as Nama_Karyawan',
                'd.Kode_Karyawan',
                'k.UserID_Absen as NIK',
                'vg.nama_level',
                'vg.nama_jabatan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                DB::raw("'SHIFT' as transaction_type")
            )
            ->orderBy('ar.created_at', 'desc')
            ->get();

        $itemsTukarHari = $queryTukarHari
            ->select(
                'h.*',
                DB::raw('NULL as Target_Table'),
                'ar.Id as approval_id',
                'ar.Id_Jenis_Approval',
                'ar.No_Transaksi',
                'ar.Request_Order_Flow',
                'ar.Created_At as Request_Date',
                'k.Nama as Nama_Karyawan',
                'd.Kode_Karyawan',
                'k.UserID_Absen as NIK',
                'vg.nama_level',
                'vg.nama_jabatan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                DB::raw("'TUKAR_HARI' as transaction_type")
            )
            ->orderBy('ar.created_at', 'desc')
            ->get();

        // Merge and sort
        $allItems = $itemsShift
            ->merge($itemsTukarHari)
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
            ->where('ar.Approver_Karyawan_Kode', $kodeApprover)
            ->whereNull('h.Status');

        $statsQueryTukarHari = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Tukar_Hari as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('ar.Approver_Karyawan_Kode', $kodeApprover);

        $stats = [
            'total' => (clone $statsQueryShift)->count() + (clone $statsQueryTukarHari)->count(),
            'pending' => (clone $statsQueryShift)->whereNull('ar.Flag_Approval')->count()
                       + (clone $statsQueryTukarHari)->whereNull('ar.Flag_Approval')->count(),
            'approved' => (clone $statsQueryShift)->whereNotNull('ar.Flag_Approval')->count()
                        + (clone $statsQueryTukarHari)->whereNotNull('ar.Flag_Approval')->count(),
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
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'kr.Id_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->where('ar.Approver_Karyawan_Kode', $kodeApprover)
            ->whereNotNull('ar.Flag_Approval'); // ONLY Processed

        // === QUERY 2: TUKAR HARI HISTORY ===
        $queryTukarHari = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Tukar_Hari as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->join('Karyawan as k', 'h.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'kr.Id_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->where('ar.Approver_Karyawan_Kode', $kodeApprover)
            ->where('h.Flag_Selesai', 'Y') // Completed only
            ->whereNotNull('ar.Flag_Approval'); // ONLY Processed

        // === APPLY FILTERS ===
        // Filter: Status (Approved/Rejected)
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'approved') {
                $queryShift->where('ar.Flag_Approval', 'Y');
                $queryTukarHari->where('ar.Flag_Approval', 'Y');
            } elseif ($filters['status'] === 'rejected') {
                $queryShift->where('ar.Flag_Approval', 'T');
                $queryTukarHari->where('ar.Flag_Approval', 'T');
            }
        }

        // Filter: Jabatan
        if (! empty($filters['jabatan'])) {
            $queryShift->where('vg.Id_Jabatan', $filters['jabatan']);
            $queryTukarHari->where('vg.Id_Jabatan', $filters['jabatan']);
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
        }

        // Filter: Date Range
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta');
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta');

            $queryShift->whereExists(function ($subquery) use ($startDate, $endDate) {
                $subquery->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Detail as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '>=', $startDate)
                    ->whereDate('detail.Tanggal_Shift', '<=', $endDate);
            });

            $queryTukarHari->where(function ($q) use ($startDate, $endDate) {
                $q->whereDate('d.Tanggal_Bekerja', '>=', $startDate)
                    ->whereDate('d.Tanggal_Bekerja', '<=', $endDate);
            });
        } elseif (! empty($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta');
            $queryShift->whereExists(function ($subquery) use ($startDate) {
                $subquery->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Detail as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '>=', $startDate);
            });
            $queryTukarHari->whereDate('d.Tanggal_Bekerja', '>=', $startDate);
        } elseif (! empty($filters['end_date'])) {
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta');
            $queryShift->whereExists(function ($subquery) use ($endDate) {
                $subquery->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Detail as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '<=', $endDate);
            });
            $queryTukarHari->whereDate('d.Tanggal_Bekerja', '<=', $endDate);
        }

        // === GET DATA ===
        $itemsShift = $queryShift
            ->select(
                'h.*',
                'd.Target_Table',
                'ar.Id as approval_id',
                'ar.Flag_Approval as Approval_Status',
                'ar.Tanggal_Approval',
                'ar.No_Transaksi',
                'ar.Catatan as Approval_Notes',
                'k.Nama as Nama_Karyawan',
                'k.UserID_Absen as NIK',
                'vg.nama_level',
                'vg.nama_jabatan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                DB::raw("'SHIFT' as transaction_type")
            )
            ->get();

        $itemsTukarHari = $queryTukarHari
            ->select(
                'h.*',
                DB::raw('NULL as Target_Table'),
                'ar.Id as approval_id',
                'ar.Flag_Approval as Approval_Status',
                'ar.Tanggal_Approval',
                'ar.No_Transaksi',
                'ar.Catatan as Approval_Notes',
                'k.Nama as Nama_Karyawan',
                'k.UserID_Absen as NIK',
                'vg.nama_level',
                'vg.nama_jabatan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                DB::raw("'TUKAR_HARI' as transaction_type")
            )
            ->get();

        // Merge and sort
        $allItems = $itemsShift
            ->merge($itemsTukarHari)
            ->unique('No_Transaksi')
            ->sortByDesc('Tanggal_Approval')
            ->values();

        $totalItems = $allItems->count();

        // Manual pagination
        $items = $allItems->slice($offset, $limit)->values();

        foreach ($items as $item) {
            $this->enrichTransactionWithDetails($item);
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
        if ($item->transaction_type === 'TUKAR_HARI') {
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
                    'd.Flag_Approval'
                )
                ->first();

            $item->total_karyawan = 1;
            $item->total_days = 1; // Tukar hari always 1 swap
            $item->karyawan = collect([$detail]);

            $item->dates_summary = Carbon::parse($detail->Tanggal_Bekerja)->format('d M').' ↔ '.Carbon::parse($detail->Tanggal_Off)->format('d M');

            $item->details = collect([$detail]);
        } else {
            // === SHIFT ENRICHMENT (EXISTING) ===
            // Get karyawan details first
            $karyawanDetails = DB::table('N_HRIS_Transaksi_Shift_Detail as d')
                ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->where('d.No_Transaksi', $item->No_Transaksi)
                ->select('d.Id', 'd.Kode_Karyawan', 'k.Nama as Nama_Karyawan', 'd.Target_Table', 'd.Flag_Approval as Detail_Approval')
                ->get();

            // Get all shift dates via Det table
            $shiftDets = DB::table('N_HRIS_Transaksi_Shift_Det as det')
                ->join('N_HRIS_Transaksi_Shift_Detail as d', 'det.Id_Detail', '=', 'd.Id')
                ->join('HRIS_Shift_Kerja as s', 'det.Id_Shift_Diminta', '=', 's.ID_Shift')
                ->leftJoin('HRIS_Shift_Kerja as s_old', 'det.Id_Shift_Sebelumnya', '=', 's_old.ID_Shift')
                ->where('det.No_Transaksi', $item->No_Transaksi)
                ->where('det.Needs_Approval', 'Y')
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
                    'det.Jenis_Pengajuan'
                )
                ->orderBy('det.Tanggal_Shift')
                ->get();

            $item->total_karyawan = $karyawanDetails->count();
            $item->total_days = $shiftDets->count();
            $item->karyawan = $karyawanDetails;

            $item->dates_summary = $shiftDets->map(function ($d) {
                return Carbon::parse($d->Tanggal_Shift)->format('d M');
            })->unique()->take(3)->implode(', ');

            if ($shiftDets->unique('Tanggal_Shift')->count() > 3) {
                $item->dates_summary .= ', +'.($shiftDets->unique('Tanggal_Shift')->count() - 3).' more';
            }

            $item->details = $shiftDets;
        }
    }

    /**
     * Process approval (Approve/Reject)
     * Supports partial approval by updating details first
     */
    public function processApproval(string $kodeApprover, string $noTransaksi, string $action, array $detailApprovals = [], ?string $notes = null): array
    {
        // Detect transaction type by prefix and route to appropriate handler
        if (str_starts_with($noTransaksi, 'TH')) {
            // TUKAR HARI transaction
            return $this->processTukarHariApproval($kodeApprover, $noTransaksi, $action, $notes);
        }

        // SHIFT transaction (existing logic)
        return DB::transaction(function () use ($kodeApprover, $noTransaksi, $action, $detailApprovals, $notes) {
            // 1. Validate Approval Request
            $approvalReq = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Approver_Karyawan_Kode', $kodeApprover)
                ->whereNull('Flag_Approval')
                ->first();
            // dd($approvalReq, $detailApprovals, $action);
            if (! $approvalReq) {
                throw new \Exception('Approval request not found or already processed.');
            }

            // 2. Handle Partial Detail Approvals (now works with Det table - Tier 3)
            // If details provided, update Flag_Approval in N_HRIS_Transaksi_Shift_Det
            if (! empty($detailApprovals)) {
                foreach ($detailApprovals as $detId => $status) { // $status: 'Y' or 'T'
                    DB::table('N_HRIS_Transaksi_Shift_Det')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Id', $detId)
                        ->update(['Flag_Approval' => $status]);
                }

                // Also update Detail (Tier 2) based on all Det approvals
                $this->updateDetailApprovalStatus($noTransaksi);
            } else {
                // Global Action: Update ALL Det records that need approval
                $detailStatus = ($action === 'APPROVE') ? 'Y' : 'T';
                DB::table('N_HRIS_Transaksi_Shift_Det')
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Needs_Approval', 'Y')
                    ->update(['Flag_Approval' => $detailStatus]);

                // Update all Details (Tier 2) as well
                DB::table('N_HRIS_Transaksi_Shift_Detail')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update(['Flag_Approval' => $detailStatus]);
            }

            // Get tanggal from Det table for WhatsApp notification
            $getTanggal = [];
            if (! empty($detailApprovals)) {
                $getTanggal = DB::table('N_HRIS_Transaksi_Shift_Det')
                    ->whereIn('Id', array_keys($detailApprovals))
                    ->pluck('Tanggal_Shift')->toArray();
            } else {
                // Global approve/reject - get all dets that need approval
                $getTanggal = DB::table('N_HRIS_Transaksi_Shift_Det')
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Needs_Approval', 'Y')
                    ->pluck('Tanggal_Shift')->toArray();
            }
            // dd($getTanggal);
            $tanggalFormated = FormatTanggalHelper::formatTanggalMultiple($getTanggal);
            $hpRequester = $this->getKaryawanHp($approvalReq->Kode_Karyawan);
            $namaRequester = $this->getKaryawanName($approvalReq->Kode_Karyawan);
            // dd($hpRequester);
            // 3. Update Approval Request Status
            $statusApproval = ($action === 'APPROVE') ? 'Y' : 'T';
            DB::table('HRIS_Approval_Request')
                ->where('id', $approvalReq->id)
                ->update([
                    'Flag_Approval' => $statusApproval,
                    'Approved_By_Kode' => $kodeApprover,
                    'Catatan' => $notes,
                    'Tanggal_Approval' => now(),
                ]);

            // 4. Check Flow
            // If REJECTED -> Stop Flow? Or just continue?
            // Usually if one rejects, the whole request is considered rejected OR we continue but those items are dead.
            // For Shift: If Partial Rejection, we might still want to proceed with the Approved ones.
            // If Global Rejection -> Stop.

            if ($action === 'REJECT') {
                // If main action is REJECT, we typically stop the flow and mark Header as Rejected
                DB::table('N_HRIS_Transaksi_Shift')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update(['Flag_Selesai' => 'Y']);

                try {
                    $pesan = $this->whatsappMessageBuilder->requesterShiftNotification($hpRequester, $noTransaksi, $tanggalFormated);
                    $this->whatsappLogHelper->sendAndLog($approvalReq->Kode_Karyawan, $namaRequester, $hpRequester, $noTransaksi, $pesan);
                } catch (\Throwable $e) {
                    Log::error('WA Notification Failed (Reject): '.$e->getMessage());
                }

                return ['status' => 'REJECTED'];
            }

            // 5. Check if next approver exists
            // $nextApprover = DB::table('HRIS_Approval_Request')
            //     ->where('No_Transaksi', $noTransaksi)
            //     ->where('Request_Order_Flow', '>', $approvalReq->Request_Order_Flow)
            //     ->orderBy('Request_Order_Flow', 'asc')
            //     ->first();

            // Multiple Hirarchy Approval Handling
            // if ($nextApprover) {
            //     // Waiting for next approver
            //     return ['status' => 'WAITING_NEXT'];
            // } else {
            //     // Final Approver!
            //     // Apply the shifts!
            //     $this->applyShiftAfterApproval($noTransaksi);
            //     return ['status' => 'COMPLETED'];
            // }

            // Simplified: Always check if all approvers are done
            $this->applyShiftAfterApproval($noTransaksi);

            try {
                $pesan = $this->whatsappMessageBuilder->requesterShiftNotification($hpRequester, $noTransaksi, $tanggalFormated);
                $this->whatsappLogHelper->sendAndLog($approvalReq->Kode_Karyawan, $namaRequester, $hpRequester, $noTransaksi, $pesan);
            } catch (\Throwable $e) {
                Log::error('WA Notification Failed (Approve): '.$e->getMessage());
            }

            return ['status' => 'COMPLETED'];
        });
    }

    /**
     * Process approval for tukar hari (swap day) transaction
     * Sets Flag_Approval = Y and applies working date shift immediately
     */
    public function processTukarHariApproval(string $kodeApprover, string $noTransaksi, string $action, ?string $notes = null): array
    {
        // dd($kodeApprover, $noTransaksi, $action, $notes);
        return DB::transaction(function () use ($kodeApprover, $noTransaksi, $action, $notes) {
            // 1. Validate Approval Request
            $approvalReq = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Approver_Karyawan_Kode', $kodeApprover)
                ->whereNull('Flag_Approval')
                ->first();

            if (! $approvalReq) {
                throw new \Exception('Approval request not found or already processed.');
            }

            // 2. Get tukar hari header and details
            $header = DB::table('N_HRIS_Transaksi_Tukar_Hari')
                ->where('No_Transaksi', $noTransaksi)
                ->first();

            if (! $header) {
                throw new \Exception('Tukar hari transaction not found.');
            }

            // Get requester info
            $hpRequester = $this->getKaryawanHp($approvalReq->Kode_Karyawan);
            $namaRequester = $this->getKaryawanName($approvalReq->Kode_Karyawan);

            // 3. Update Approval Request Status
            $statusApproval = ($action === 'APPROVE') ? 'Y' : 'T';
            DB::table('HRIS_Approval_Request')
                ->where('id', $approvalReq->id)
                ->update([
                    'Flag_Approval' => $statusApproval,
                    'Approved_By_Kode' => $kodeApprover,
                    'Catatan' => $notes,
                    'Tanggal_Approval' => now(),
                ]);

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
                    $pesan = $this->whatsappMessageBuilder->requesterShiftNotification($hpRequester, $noTransaksi, $tanggalFormated);
                    $this->whatsappLogHelper->sendAndLog($approvalReq->Kode_Karyawan, $namaRequester, $hpRequester, $noTransaksi, $pesan);
                } catch (\Throwable $e) {
                    Log::error('WA Notification Failed (Tukar Hari Reject): '.$e->getMessage());
                }

                return ['status' => 'REJECTED'];
            }

            // 4. If APPROVED: Set Flag_Approval = Y and apply shifts
            // Update all details with Flag_Approval = Y
            DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->update(['Flag_Approval' => 'Y']);

            // Apply shifts and mark as completed
            $this->applyTukarHariAfterApproval($noTransaksi);

            try {
                $pesan = $this->whatsappMessageBuilder->requesterShiftNotification($hpRequester, $noTransaksi, $tanggalFormated);
                $this->whatsappLogHelper->sendAndLog($approvalReq->Kode_Karyawan, $namaRequester, $hpRequester, $noTransaksi, $pesan);
            } catch (\Throwable $e) {
                Log::error('WA Notification Failed (Tukar Hari Approve): '.$e->getMessage());
            }

            return ['status' => 'COMPLETED'];
        });
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
        $orderBy = 'CASE '
            ."WHEN Nama = 'NO SHIFT' THEN 1 "
            ."WHEN Nama = 'LIBUR' THEN 2 "
            ."WHEN Nama = 'HARI MINGGU' THEN 3 "
            ."WHEN Nama = 'OFF' THEN 4 "
            .'ELSE 5 END';

        $shift = DB::table('HRIS_Shift_Kerja')
            ->whereIn('Nama', $candidates)
            ->where(function ($query) {
                $query->whereNull('Non_Shift')
                    ->orWhere('Non_Shift', '!=', 'T');
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
    protected function getKaryawanName(string $kodeKaryawan): ?string
    {
        $karyawan = DB::table('Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->first();

        return $karyawan?->Nama;
    }

    /**
     * Get current approver for a employee
     */
    public function getCurrentApprover(string $kodeKaryawan): ?object
    {
        return DB::table('N_HRIS_Approval_Flow_Shift as afs')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'afs.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.Id_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'k.Id_Level_Jabatan')
            ->where('afs.Kode_Karyawan_Requester', $kodeKaryawan)
            ->whereNull('afs.Status')
            ->select('k.Nama', 'k.Kode_Karyawan', 'vg.nama_jabatan', 'vd.nama_divisi')
            ->first();
    }

    protected function getKaryawanHp(string $kodeKaryawan): ?string
    {
        $karyawan = DB::table('Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->first();

        return $karyawan?->HP;
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

        // === QUERY 1: Transaksi Shift ===
        $queryShift = DB::table('N_HRIS_Transaksi_Shift as h')
            ->join('N_HRIS_Transaksi_Shift_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('h.Status'); // Exclude cancelled

        // === QUERY 2: Transaksi Tukar Hari ===
        // Tukar Hari uses Flag_Selesai in header for review status
        $queryTukarHari = DB::table('N_HRIS_Transaksi_Tukar_Hari as h')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('h.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('h.Status');

        // Apply filters to both queries
        if (! empty($filters['status'])) {
            if ($filters['status'] === 'Y') {
                // Sudah Direview
                $queryShift->where('h.Flag_Selesai', 'Y');
                $queryTukarHari->where('h.Flag_Selesai', 'Y');
            } elseif ($filters['status'] === 'null') {
                // Belum Direview
                $queryShift->where(function ($q) {
                    $q->whereNull('h.Flag_Selesai')
                        ->orWhere('h.Flag_Selesai', '!=', 'Y');
                });
                $queryTukarHari->where(function ($q) {
                    $q->whereNull('h.Flag_Selesai')
                        ->orWhere('h.Flag_Selesai', '!=', 'Y');
                });
            }
        }

        // Date range filter
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta')->startOfDay();
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta')->endOfDay();
            $queryShift->whereBetween('h.Created_At', [$startDate, $endDate]);
            $queryTukarHari->whereBetween('h.Created_At', [$startDate, $endDate]);
        } elseif (! empty($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta')->startOfDay();
            $queryShift->where('h.Created_At', '>=', $startDate);
            $queryTukarHari->where('h.Created_At', '>=', $startDate);
        } elseif (! empty($filters['end_date'])) {
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta')->endOfDay();
            $queryShift->where('h.Created_At', '<=', $endDate);
            $queryTukarHari->where('h.Created_At', '<=', $endDate);
        }

        // Search filter
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $queryShift->where('h.No_Transaksi', 'like', "%{$search}%");
            $queryTukarHari->where('h.No_Transaksi', 'like', "%{$search}%");
        }

        if (! empty($filters['periode'])) {
            $queryShift->whereYear('h.Created_At', $filters['periode']);
            $queryTukarHari->whereYear('h.Created_At', $filters['periode']);
        }

        // Get distinct transactions from both tables
        $transactionsShift = $queryShift
            ->select('h.*', 'd.Target_Table', 'd.Kode_Karyawan', DB::raw("'SHIFT' as transaction_type"))
            ->distinct()
            ->get();

        $transactionsTukarHari = $queryTukarHari
            ->select('h.*', DB::raw('NULL as Target_Table'), 'h.Kode_Karyawan', DB::raw("'TUKAR_HARI' as transaction_type"))
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

        // Enrich with details
        foreach ($transactions as $trans) {
            if ($trans->transaction_type === 'SHIFT') {
                // Get Det entries (shift dates) joined with shift info
                $trans->details = DB::table('N_HRIS_Transaksi_Shift_Det as det')
                    ->join('N_HRIS_Transaksi_Shift_Detail as d', 'det.Id_Detail', '=', 'd.Id')
                    ->leftJoin('HRIS_Shift_Kerja as s', 'det.Id_Shift_Diminta', '=', 's.ID_Shift')
                    ->where('det.No_Transaksi', $trans->No_Transaksi)
                    ->select('det.*', 's.Nama as Nama_Shift', 'd.Target_Table')
                    ->get();

                $trans->tanggal_count = $trans->details->count();
                $trans->shift_display = $trans->details->count() > 1
                    ? 'Mixed'
                    : ($trans->details->first()?->Nama_Shift ?? '-');
            } else {
                // Tukar Hari
                $trans->details = DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail as d')
                    ->leftJoin('HRIS_Shift_Kerja as s', 'd.Id_Shift_Bekerja', '=', 's.ID_Shift')
                    ->where('d.No_Transaksi', $trans->No_Transaksi)
                    ->select('d.*', 's.Nama as Nama_Shift')
                    ->get();

                $trans->tanggal_count = $trans->details->count();
                $trans->shift_display = $trans->details->first()?->Nama_Shift ?? '-';
            }
        }

        // Calculate stats - both transaction types
        $statsQueryShift = DB::table('N_HRIS_Transaksi_Shift as h')
            ->join('N_HRIS_Transaksi_Shift_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('h.Status');

        $statsQueryTukarHari = DB::table('N_HRIS_Transaksi_Tukar_Hari as h')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('d.Kode_Karyawan', $kodeKaryawan);

        $stats = [
            'total' => (clone $statsQueryShift)->distinct()->count('h.No_Transaksi')
                     + (clone $statsQueryTukarHari)->distinct()->count('h.No_Transaksi'),
            'pending' => (clone $statsQueryShift)->where(function ($q) {
                $q->whereNull('h.Flag_Selesai')->orWhere('h.Flag_Selesai', '!=', 'Y');
            })->distinct()->count('h.No_Transaksi')
                    + (clone $statsQueryTukarHari)->where(function ($q) {
                        $q->whereNull('d.Flag_Approval')->orWhere('d.Flag_Approval', '!=', 'Y');
                    })->distinct()->count('h.No_Transaksi'),
            'approved' => (clone $statsQueryShift)->where('h.Flag_Selesai', 'Y')->distinct()->count('h.No_Transaksi')
                        + (clone $statsQueryTukarHari)->where('d.Flag_Approval', 'Y')->distinct()->count('h.No_Transaksi'),
        ];

        return [
            'items' => $transactions,
            'total' => $countTransactions,
            'page' => $page,
            'limit' => $limit,
            'stats' => $stats,
        ];
    }

    /**
     * Get transaction detail
     */
    public function getDetail(string $noTransaksi): ?array
    {
        // Detect transaction type by prefix: TH = Tukar Hari, SH = Shift
        $transactionType = str_starts_with($noTransaksi, 'TH') ? 'TUKAR_HARI' : 'SHIFT';

        if ($transactionType === 'TUKAR_HARI') {
            // === TUKAR HARI TRANSACTION ===
            $header = DB::table('N_HRIS_Transaksi_Tukar_Hari')
                ->where('No_Transaksi', $noTransaksi)
                ->first();

            if (! $header) {
                return null;
            }

            // Get detail (only 1 record for tukar hari)
            $detail = DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail as d')
                ->leftJoin('HRIS_Shift_Kerja as s', 'd.Id_Shift_Bekerja', '=', 's.ID_Shift')
                ->leftJoin('HRIS_Shift_Kerja_Detail as sd', 's.ID_Shift', '=', 'sd.ID_Shift')
                ->leftJoin('HRIS_Waktu_Kerja as w', 'sd.ID_Waktu_Kerja', '=', 'w.ID_Waktu_Kerja')
                ->where('d.No_Transaksi', $noTransaksi)
                ->select(
                    'd.*',
                    's.Nama as Nama_Shift',
                    'w.Jam_Masuk',
                    'w.Jam_Keluar'
                )
                ->first();

            // Get approval steps
            $approvalSteps = DB::table('HRIS_Approval_Request as ar')
                ->leftJoin('Karyawan as k', 'ar.Approver_Karyawan_Kode', '=', 'k.Kode_Karyawan')
                ->where('ar.No_Transaksi', $noTransaksi)
                ->orderBy('ar.Request_Order_Flow')
                ->select('ar.*', 'k.Nama')
                ->get();

            return [
                'transaction_type' => 'TUKAR_HARI',
                'header' => $header,
                'detail' => $detail, // Single object, not array
                'approval_steps' => $approvalSteps,
            ];
        } else {
            // === SHIFT TRANSACTION ===
            $header = DB::table('N_HRIS_Transaksi_Shift')
                ->where('No_Transaksi', $noTransaksi)
                ->first();

            if (! $header) {
                return null;
            }

            // 3-tier: Det (det) has shift info, Detail (d) has karyawan info
            $detailsRaw = DB::table('N_HRIS_Transaksi_Shift_Det as det')
                ->join('N_HRIS_Transaksi_Shift_Detail as d', 'det.Id_Detail', '=', 'd.Id')
                ->leftJoin('HRIS_Shift_Kerja as s', 'det.Id_Shift_Diminta', '=', 's.ID_Shift')
                ->leftJoin('HRIS_Shift_Kerja_Detail as sd', 's.ID_Shift', '=', 'sd.ID_Shift')
                ->leftJoin('HRIS_Waktu_Kerja as w', 'sd.ID_Waktu_Kerja', '=', 'w.ID_Waktu_Kerja')
                ->leftJoin('HRIS_Shift_Kerja as sOld', 'det.Id_Shift_Sebelumnya', '=', 'sOld.ID_Shift')
                ->where('det.No_Transaksi', $noTransaksi)
                ->select(
                    'det.*',
                    'd.Kode_Karyawan',
                    'd.Target_Table',
                    's.Nama as Nama_Shift',
                    'w.Jam_Masuk',
                    'w.Jam_Keluar',
                    'sOld.Nama as Nama_Shift_Sebelumnya',
                    'sd.Hari'
                )
                ->get();

            $details = $detailsRaw->filter(function ($item) {
                $date = Carbon::parse($item->Tanggal_Shift);
                $isoDay = $date->isoWeekday();
                // Map ISO (1=Mon..7=Sun) to HRIS (1=Sun..7=Sat)
                $hrisDay = $isoDay == 7 ? 1 : $isoDay + 1;

                return $item->Hari == $hrisDay;
            })->values();

            // Get approval steps
            $approvalSteps = DB::table('HRIS_Approval_Request as ar')
                ->leftJoin('Karyawan as k', 'ar.Approver_Karyawan_Kode', '=', 'k.Kode_Karyawan')
                ->where('ar.No_Transaksi', $noTransaksi)
                ->orderBy('ar.Request_Order_Flow')
                ->select('ar.*', 'k.Nama')
                ->get();

            return [
                'transaction_type' => 'SHIFT',
                'header' => $header,
                'details' => $details, // Array of date entries
                'approval_steps' => $approvalSteps,
            ];
        }
    }

    /**
     * Get available shifts for dropdown
     */
    public function getAvailableShifts(): array
    {
        // Join dengan HRIS_Shift_Kerja_Detail dan HRIS_Waktu_Kerja untuk dapat jam
        return DB::table('HRIS_Shift_Kerja as sk')
            ->join('HRIS_Shift_Kerja_Detail as sd', 'sk.ID_Shift', '=', 'sd.ID_Shift')
            ->join('HRIS_Waktu_Kerja as w', 'sd.ID_Waktu_Kerja', '=', 'w.ID_Waktu_Kerja')
            // ->whereNull('sk.Status')
            ->where('sk.Non_Shift', 'T')
            ->groupBy('sk.ID_Shift', 'sk.Nama', 'w.Jam_Masuk', 'w.Jam_Keluar', 'w.Durasi_Lembur', 'w.Flag_Longshift')
            ->orderBy('sk.Nama')
            ->select(
                'sk.ID_Shift as id',
                'sk.Nama as nama',
                'w.Jam_Masuk as jam_masuk',
                'w.Jam_Keluar as jam_keluar',
                'w.Flag_Longshift as flag_longshift',
                'w.Durasi_Lembur as durasi_longshift'
            )
            ->get()
            ->map(function ($shift) {
                // Format jam dengan null-safe
                $jamMasuk = $shift->jam_masuk ? substr($shift->jam_masuk, 0, 5) : '--:--';
                $jamKeluar = $shift->jam_keluar ? substr($shift->jam_keluar, 0, 5) : '--:--';

                $shift->jam_masuk = $jamMasuk;
                $shift->jam_keluar = $jamKeluar;
                // EMI-S1 (07:00 - 13:00) | Longshift : 4 Jam lembur
                // EMI-S1 (07:00 - 13:00) | Regular
                $shift->display = sprintf('%s (%s - %s)%s',
                    $shift->nama,
                    $jamMasuk,
                    $jamKeluar,
                    $shift->flag_longshift ? ' | Longshift : '.(int) $shift->durasi_longshift.' Jam' : ' | Regular'
                );

                // $shift->display = sprintf('%s (%s - %s) | %s ', $shift->nama, $jamMasuk, $jamKeluar, $shift->flag_longshift ? 'Longshift' : 'Regular');
                return $shift;
            })
            ->toArray();
    }

    public function getShiftsForDay($tanggal): array
    {
        $hari = Carbon::parse($tanggal)->isoWeekday();
        $hariHRIS = $hari == 7 ? 1 : $hari + 1;
        $list = DB::table('HRIS_Shift_Kerja as sk')
            ->join('HRIS_Shift_Kerja_Detail as sd', 'sk.ID_Shift', '=', 'sd.ID_Shift')
            ->join('HRIS_Waktu_Kerja as w', 'sd.ID_Waktu_Kerja', '=', 'w.ID_Waktu_Kerja')
            // ->whereNull('sk.Status')
            ->where('sk.Non_Shift', 'T')
            ->where('sd.Hari', $hariHRIS)
            ->groupBy('sk.ID_Shift', 'sk.Nama', 'w.Jam_Masuk', 'w.Jam_Keluar', 'w.Durasi_Lembur', 'w.Flag_Longshift')
            ->orderBy('sk.Nama')
            ->select(
                'sk.ID_Shift as id',
                'sk.Nama as nama',
                'w.Jam_Masuk as jam_masuk',
                'w.Jam_Keluar as jam_keluar',
                'w.Flag_Longshift as flag_longshift',
                'w.Durasi_Lembur as durasi_longshift'
            )
            ->get()
            ->map(function ($shift) {
                // Format jam dengan null-safe
                $jamMasuk = $shift->jam_masuk ? substr($shift->jam_masuk, 0, 5) : '--:--';
                $jamKeluar = $shift->jam_keluar ? substr($shift->jam_keluar, 0, 5) : '--:--';

                $shift->jam_masuk = $jamMasuk;
                $shift->jam_keluar = $jamKeluar;
                $shift->display = sprintf('%s (%s - %s)%s',
                    $shift->nama,
                    $jamMasuk,
                    $jamKeluar,
                    $shift->flag_longshift ? ' | Longshift : '.(int) $shift->durasi_longshift.' Jam' : ' | Regular'
                );

                return $shift;
            })
            ->toArray();

        // dd($list);
        return $list;
    }

    /**
     * Get user's current week shift schedule
     */
    public function getWeekSchedule(string $kodeKaryawan, Carbon $startDate): array
    {
        $endDate = $startDate->copy()->addDays(6);

        $userid = DB::table('Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->first();
        if (! $userid) {
            return [];
        }

        $query = "Exec HRIS_SP_GET_ABSEN_NEW_V3 '001', ?, ?, ?, '', ''";
        $select = DB::select($query, [
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $userid->UserID_Absen,
        ]);
        // log transaksi
        // log transaksi (3-tier: Header → Detail → Det)
        $transaksi = DB::table('N_HRIS_Transaksi_Shift as a')
            ->join('N_HRIS_Transaksi_Shift_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->join('N_HRIS_Transaksi_Shift_Det as c', 'b.Id', '=', 'c.Id_Detail')
            ->where('b.Kode_Karyawan', $kodeKaryawan)  // Kode_Karyawan is now in Detail (Tier 2)
            ->whereNull('a.Status')
            ->where(function ($q) {
                $q->whereNull('a.Flag_Selesai')
                    ->orWhere('a.Flag_Selesai', '!=', 'Y');
            })
            ->whereBetween('c.Tanggal_Shift', [$startDate, $endDate])  // Tanggal is now in Det (Tier 3)
            ->select(
                'a.Flag_Selesai',
                'b.Target_Table',
                'c.Id as urut_detail',  // Use Det.Id for ordering
                'c.Tanggal_Shift',
                'c.Id_Shift_Diminta',
                'c.Jenis_Pengajuan',
                'c.Needs_approval',
                'c.Flag_Applied',
                'a.Created_At',  // Use header's Created_At since Det doesn't have it
                'c.Flag_Approval'
            )
            ->orderBy('c.Tanggal_Shift', 'asc')
            ->orderBy('c.Id', 'desc')
            ->get();

        // Preload transaksi tukar hari
        $transaksiTukarHari = DB::table('N_HRIS_Transaksi_Tukar_Hari as h')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('h.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('h.Status') // Status NULL = aktif, Y = dibatalkan
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('d.Tanggal_Bekerja', [$startDate, $endDate])
                    ->orWhereBetween('d.Tanggal_Off', [$startDate, $endDate]);
            })
            ->select(
                'h.No_Transaksi',
                'h.Alasan',
                'h.Created_At',
                'h.Flag_Selesai',
                'd.Tanggal_Bekerja',
                'd.Tanggal_Off',
                'd.Id_Shift_Bekerja',
                'd.Flag_Approval'
            )
            ->orderBy('h.Created_At', 'desc')
            ->get();

        // dd($transaksi);

        $schedule = collect($select)->map(function ($item) use ($transaksi, $transaksiTukarHari, $kodeKaryawan) {

            $hari = Carbon::parse($item->Tanggal)->isoWeekday();
            $hariHRIS = $hari == 7 ? 1 : $hari + 1;

            // Get status hari using function
            $statusHari = DB::selectOne(
                'SELECT dbo.N_HRIS_FN_GetStatusHari(?, ?, ?) as status_hari',
                ['001', $kodeKaryawan, Carbon::parse($item->Tanggal)->format('Y-m-d')]
            );
            $statusHariValue = $statusHari->status_hari ?? 'WORK';

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

            // dd($logTran);

            $usedShift = $logTran ? $logTran->Id_Shift_Diminta : ($item->Id_Shift ?? null);

            $jenisShift = $logTran ? $logTran->Target_Table : trim(explode('|', $item->JENIS_SHIFT_DIPILIH ?? '')[0]);

            $tempShift = DB::table('HRIS_Shift_Kerja as sk')
                ->join('HRIS_Shift_Kerja_Detail as sd', function ($join) use ($hariHRIS) {
                    $join->on('sk.ID_Shift', '=', 'sd.ID_Shift')
                        ->where('sd.Hari', $hariHRIS);
                })
                ->join('HRIS_Waktu_Kerja as w', 'sd.ID_Waktu_Kerja', '=', 'w.ID_Waktu_Kerja')
                ->select(
                    'sk.ID_Shift',
                    'sk.Nama',
                    'w.Jam_Masuk',
                    'w.Jam_Keluar',
                    DB::raw("'".($jenisShift === 'SHIFT SEMENTARA' || $jenisShift === 'SEMENTARA' ? 'SEMENTARA' : 'PERMANEN')."' as source")
                )
                ->where('sk.ID_Shift', $usedShift)
                ->first();

            $ApprovalShift = $logTran ? $logTran->Flag_Approval : 'Y';

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

            // dd($logTran);
            return $this->formatShiftForCalendar(
                Carbon::parse($item->Tanggal),
                $tempShift,
                $ApprovalShift,
                $isLocked,
                $tukarHariData,
                $statusHariValue
            );
        })->values()->toArray();

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

        $endDate = $startDate->copy()->addDays($days - 1);

        // 1. Fetch all UserID_Absen in ONE query
        $users = DB::table('Karyawan')
            ->whereIn('Kode_Karyawan', $kodeKaryawanList)
            ->select('Kode_Karyawan', 'UserID_Absen')
            ->get()
            ->keyBy('Kode_Karyawan');

        // Build comma-separated UserIDs for batch SP call
        $userIdList = $users->pluck('UserID_Absen')->filter()->implode(',');
        // dd($userIdList);

        if (empty($userIdList)) {
            return [];
        }

        // 2. Single SP call with ALL UserIDs at once
        $query = "Exec HRIS_SP_GET_ABSEN_NEW_V3 '001', ?, ?, ?, '', ''";
        $absenData = collect(DB::select($query, [
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $userIdList,
        ]));

        // 3. Batch fetch transactions for ALL members (3-tier)
        $transaksi = DB::table('N_HRIS_Transaksi_Shift as a')
            ->join('N_HRIS_Transaksi_Shift_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->join('N_HRIS_Transaksi_Shift_Det as c', 'b.Id', '=', 'c.Id_Detail')
            ->whereIn('b.Kode_Karyawan', $kodeKaryawanList)
            ->whereNull('a.Status')
            ->where(function ($q) {
                $q->whereNull('a.Flag_Selesai')
                    ->orWhere('a.Flag_Selesai', '!=', 'Y');
            })
            ->whereBetween('c.Tanggal_Shift', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->select(
                'b.Kode_Karyawan',
                'a.Flag_Selesai',
                'b.Target_Table',
                'c.Id as urut_detail',
                'c.Tanggal_Shift',
                'c.Id_Shift_Diminta',
                'c.Jenis_Pengajuan',
                'c.Needs_approval',
                'c.Flag_Applied',
                'a.Created_At',
                'c.Flag_Approval'
            )
            ->orderBy('c.Tanggal_Shift', 'asc')
            ->orderBy('c.Id', 'desc')
            ->get()
            ->groupBy('Kode_Karyawan');

        // 4. Pre-fetch all needed shifts to avoid N+1 queries
        $allShiftIds = $absenData->pluck('Id_Shift')
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
                'w.Jam_Keluar'
            )
            ->get();

        $shiftDetailCache = $shiftDetails
            ->mapWithKeys(function ($row) {
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

        $absenDataByKode = $absenData->groupBy('Kode_Karyawan');

        // 5. Process each member's schedule
        $result = [];
        foreach ($kodeKaryawanList as $kodeKaryawan) {
            // SP returns Kode_Karyawan directly - filter by it
            $memberAbsen = $absenDataByKode->get($kodeKaryawan, collect());
            $memberTransaksi = $transaksi->get($kodeKaryawan, collect());
            $memberTransaksiByDate = $memberTransaksi->groupBy('Tanggal_Shift');

            $schedule = $memberAbsen->map(function ($item) use ($memberTransaksiByDate, $shiftDetailCache, $holidayMap) {
                $tanggal = Carbon::parse($item->Tanggal);
                $dateStr = $tanggal->format('Y-m-d');

                $hari = $tanggal->isoWeekday();
                $hariHRIS = $hari == 7 ? 1 : $hari + 1;

                $logTran = $memberTransaksiByDate->get($dateStr)?->first();

                $usedShift = $logTran ? $logTran->Id_Shift_Diminta : $item->Id_Shift;
                $jenisShift = $logTran ? $logTran->Target_Table : trim(explode('|', $item->JENIS_SHIFT_DIPILIH)[0]);
                $approvalShift = $logTran ? $logTran->Flag_Approval : 'Y';
                $isLocked = (bool) $logTran;

                $shiftKey = $usedShift.'-'.$hariHRIS;
                $shiftRow = $shiftDetailCache->get($shiftKey);

                $tempShift = null;
                if ($shiftRow) {
                    $tempShift = (object) [
                        'ID_Shift' => $shiftRow->ID_Shift,
                        'Nama' => $shiftRow->Nama,
                        'Jam_Masuk' => $shiftRow->Jam_Masuk,
                        'Jam_Keluar' => $shiftRow->Jam_Keluar,
                        'source' => ($jenisShift === 'SHIFT SEMENTARA' || $jenisShift === 'SEMENTARA')
                            ? 'SEMENTARA'
                            : 'PERMANEN',
                    ];
                }

                $statusHariValue = $item->StatusHari ?? 'WORK';
                $statusHariLabel = null;
                if (str_starts_with($statusHariValue, 'OFF-Hari_Libur')) {
                    $holidayName = $holidayMap->get($dateStr) ?: 'Libur Nasional';
                    $statusHariLabel = 'OFF ('.$holidayName.')';
                }

                return $this->formatShiftForCalendar(
                    $tanggal,
                    $tempShift,
                    $approvalShift,
                    $isLocked,
                    null,
                    $statusHariValue,
                    $statusHariLabel
                );
            })->keyBy('date')->toArray();

            $result[$kodeKaryawan] = $schedule;
        }

        return $result;
    }

    /**
     * Create offday records for leader (single, range, or permanent)
     */
    public function createOffdayForMembers(
        string $leaderKode,
        array $members,
        string $mode,
        string $startDate,
        ?string $endDate,
        ?int $hari,
        string $alasan = ''
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();

        if ($mode === 'PERMANEN') {
            $end = Carbon::create(9999, 12, 31)->startOfDay();
        } else {
            $end = $endDate ? Carbon::parse($endDate)->startOfDay() : $start->copy();
        }

        if ($end->lt($start)) {
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
                'Hari' => $hariValue,
                'Tgl_Mulai' => $start->format('Y-m-d'),
                'Tgl_Selesai' => $end->format('Y-m-d'),
                'Flag_Aktif' => 'Y',
                'No_Transaksi_Reference' => null,
                'Keterangan' => $alasan,
                'Created_At' => Carbon::now(),
                'Created_By' => $leaderKode,
            ];
        }

        if (! empty($rows)) {
            DB::table('N_HRIS_Offday_Karyawan')->insert($rows);
        }

        return [
            'mode' => $mode,
            'inserted' => count($rows),
            'holiday_dates' => $holidayDates,
        ];
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
    protected function formatShiftForCalendar(Carbon $date, ?object $shift, ?string $approvalShift, bool $isLocked = false, ?object $tukarHariData = null, ?string $statusHari = 'WORK', ?string $statusHariLabel = null): array
    {
        $isDayOff = str_starts_with($statusHari, 'OFF');

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

            'shift' => $shift ? [
                'id' => $shift->ID_Shift,
                'nama' => $shift->Nama,
                'jam_masuk' => substr($shift->Jam_Masuk, 0, 5),
                'jam_keluar' => substr($shift->Jam_Keluar, 0, 5),
            ] : null,
            'Flag_Approval' => $approvalShift ? $approvalShift : 'N',
            'source' => $shift->source ?? null,
        ];

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

        $query = "Exec HRIS_SP_GET_ABSEN_NEW_V3 '001', ?, ?, ?, '', ''";
        $select = DB::select($query, [
            $date->format('Y-m-d'),
            $date->format('Y-m-d'),
            $userid->UserID_Absen,
        ]);

        if (empty($select)) {
            return null;
        }

        return $select[0]->Id_Shift;
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

        // 3-tier: Header (a) -> Detail (b) -> Det (c)
        // Kode_Karyawan is in Detail (b), Tanggal_Shift is in Det (c)
        $lockedShift = DB::table('N_HRIS_Transaksi_Shift as a')
            ->join('N_HRIS_Transaksi_Shift_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->join('N_HRIS_Transaksi_Shift_Det as c', 'b.Id', '=', 'c.Id_Detail')
            ->where('b.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('a.Status') // Not Cancelled
            ->where(function ($q) {
                $q->whereNull('a.Flag_Selesai')
                    ->orWhere('a.Flag_Selesai', '!=', 'Y');
            })
            ->whereIn('c.Tanggal_Shift', $dateValues)
            ->pluck('c.Tanggal_Shift')
            ->toArray();

        // Also check tukar hari
        $lockedTukarHari = DB::table('N_HRIS_Transaksi_Tukar_Hari as h')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('d.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('h.Status') // Not Cancelled
            ->where(function ($q) {
                $q->whereNull('h.Flag_Selesai')
                    ->orWhere('h.Flag_Selesai', '!=', 'Y');
            })
            ->where(function ($q) use ($dateValues) {
                $q->whereIn('d.Tanggal_Bekerja', $dateValues)
                    ->orWhereIn('d.Tanggal_Off', $dateValues);
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
        $lockedShiftDates = DB::table('N_HRIS_Transaksi_Shift as a')
            ->join('N_HRIS_Transaksi_Shift_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->join('N_HRIS_Transaksi_Shift_Det as c', 'b.Id', '=', 'c.Id_Detail')
            ->where('b.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('a.Status') // Not Cancelled
            ->where(function ($q) {
                $q->whereNull('a.Flag_Selesai')
                    ->orWhere('a.Flag_Selesai', '!=', 'Y');
            })
            ->where('c.Needs_Approval', 'Y')
            ->where('c.Flag_Applied', '!=', 'Y')
            ->pluck('c.Tanggal_Shift')
            ->toArray();

        // Get locked dates from Tukar Hari pending requests
        $lockedTukarHariDates = DB::table('N_HRIS_Transaksi_Tukar_Hari as h')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('h.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('h.Status') // Not cancelled (Status NULL = aktif, Y = dibatalkan)
            ->where(function ($q) {
                $q->whereNull('h.Flag_Selesai')
                    ->orWhere('h.Flag_Selesai', '!=', 'Y');
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
    public function getLeaderTeamMembers(string $leaderKode): array
    {
        return DB::table('Karyawan as k')
            ->join('HRIS_Karyawan_Team as kt', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'k.ID_Level_Jabatan', '=', 'v.ID_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as d', 'k.Id_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
            ->where('kt.Kode_Karyawan_Team', $leaderKode)
            ->where('k.Aktif', 'Y')
            ->select(
                'k.Kode_Karyawan',
                'k.UserId_Web as NIK',
                'k.Nama',
                'k.UserID_Absen',
                'v.nama_jabatan',
                'd.nama_divisi'
            )
            ->orderBy('k.Nama')
            ->get()
            ->toArray();
    }

    /**
     * Get member info by Kode_Karyawan array
     */
    public function getMemberInfoByKodes(array $kodes): array
    {
        if (empty($kodes)) {
            return [];
        }

        return DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'k.ID_Level_Jabatan', '=', 'v.ID_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as d', 'k.Id_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
            ->whereIn('k.Kode_Karyawan', $kodes)
            ->select(
                'k.Kode_Karyawan',
                'k.UserId_Web as NIK',
                'k.Nama',
                'k.UserID_Absen',
                'v.nama_jabatan',
                'd.nama_divisi'
            )
            ->orderBy('k.Nama')
            ->get()
            ->toArray();
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
        string $alasan = ''
    ): void {
        $previousShift = $this->getUserShiftForDate($memberKode, Carbon::parse($tanggal));
        $jenisPengajuan = $this->determineJenisPengajuan(Carbon::parse($tanggal));

        // Tier 1: Create Header
        $noTransaksi = $this->createHeader('LEADER_SHIFT', $alasan);

        // Update Created_By to leaderKode
        DB::table('N_HRIS_Transaksi_Shift')
            ->where('No_Transaksi', $noTransaksi)
            ->update([
                'Created_By' => $leaderKode,
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
     * Create leader shift request (for backdate that needs approval)
     * Uses 3-tier structure: 1 Header → N Details (per Karyawan) → M Dets (per Tanggal)
     */
    public function createLeaderShiftRequest(
        string $leaderKode,
        array $members,
        array $dates,
        string $jenis,
        string $alasan = ''
    ): string {
        // Tier 1: Create single header for entire batch
        $noTransaksi = $this->createHeader('LEADER_SHIFT', $alasan);

        // Update Created_By to leaderKode
        DB::table('N_HRIS_Transaksi_Shift')
            ->where('No_Transaksi', $noTransaksi)
            ->update(['Created_By' => $leaderKode]);

        // Tier 2 & 3: Create Detail per member, Det per date
        foreach ($members as $memberKode) {
            // Tier 2: Create Detail for this member
            $idDetail = $this->createKaryawanDetail($noTransaksi, $memberKode, $jenis, $alasan);

            // Prepare dates with previous shift info
            $formattedDates = [];
            foreach ($dates as $dateItem) {
                $previousShift = $this->getUserShiftForDate($memberKode, Carbon::parse($dateItem['tanggal']));
                $formattedDates[] = [
                    'tanggal' => $dateItem['tanggal'],
                    'id_shift' => $dateItem['id_shift'],
                    'id_shift_sebelumnya' => $previousShift,
                    'jenis_pengajuan' => 'BACKDATE',
                    'needs_approval' => true,
                    'flag_approval' => null,
                    'flag_applied' => 'T',
                ];
            }

            // Tier 3: Create Det for each date
            $this->createShiftDet($idDetail, $noTransaksi, $formattedDates);
        }

        // Create approval request for the leader (single approval for entire batch)
        $this->approvalFlowService->createFlowForLegacySingleAppro(
            $noTransaksi,
            $leaderKode,
            3 // Approval type for shift
        );

        return $noTransaksi;
    }

    /**
     * Get leader shift history
     */
    public function getLeaderShiftHistory(string $leaderKode, array $params): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        $filters = $params['filters'] ?? [];

        $query = DB::table('N_HRIS_Transaksi_Shift as h')
            ->where('h.Created_By', $leaderKode)
            ->where('h.Jenis_Transaksi', 'LEADER_SHIFT')
            ->whereNull('h.Status');

        // Apply filters
        if (! empty($filters['status'])) {
            if ($filters['status'] === 'pending') {
                $query->where(function ($q) {
                    $q->whereNull('h.Flag_Selesai')->orWhere('h.Flag_Selesai', '!=', 'Y');
                });
            } elseif ($filters['status'] === 'approved') {
                $query->where('h.Flag_Selesai', 'Y');
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('h.No_Transaksi', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->startOfDay();
            $endDate = Carbon::parse($filters['end_date'])->endOfDay();
            $query->whereBetween('h.Created_At', [$startDate, $endDate]);
        }

        $total = $query->count();

        $transactions = $query
            ->select('h.*')
            ->offset($offset)
            ->limit($limit)
            ->orderBy('h.Created_At', 'desc')
            ->get();

        // Enrich with details
        foreach ($transactions as $trans) {
            // Get member info from N_HRIS_Transaksi_Shift_Detail (Tier 2)
            $members = DB::table('N_HRIS_Transaksi_Shift_Detail as d')
                ->join('Karyawan as k', 'd.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->where('d.No_Transaksi', $trans->No_Transaksi)
                ->select('k.Nama', 'k.NIK', 'd.Kode_Karyawan')
                ->distinct()
                ->get();

            $trans->members = $members;
            $trans->total_members = $members->count();

            // Format list of members string
            if ($members->count() > 0) {
                $names = $members->take(3)->pluck('Nama')->toArray();
                $trans->member_names = implode(', ', $names);
                if ($members->count() > 3) {
                    $trans->member_names .= ', +'.($members->count() - 3).' others';
                }

                // COMPATIBILITY FIX: Frontend expects Nama_Member and NIK_Member
                if ($members->count() === 1) {
                    $trans->Nama_Member = $members->first()->Nama;
                    $trans->NIK_Member = $members->first()->NIK;
                } else {
                    $trans->Nama_Member = $members->first()->Nama.', +'.($members->count() - 1).' others';
                    $trans->NIK_Member = 'Multiple';
                }
            } else {
                $trans->member_names = '-';
                $trans->Nama_Member = '-';
                $trans->NIK_Member = '-';
            }

            // Also enrich with full details for dates/shifts
            $this->enrichTransactionWithDetails($trans);
            $trans->tanggal_count = $trans->total_days ?? 0;
            $trans->is_pending = empty($trans->Flag_Selesai) || $trans->Flag_Selesai !== 'Y';
        }

        return [
            'items' => $transactions,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Generate transaction number for tukar hari (swap day)
     * Format: TH + MM (month) + YY (year) + XXXX (sequence)
     * Example: TH02260001 (Tukar Hari, Feb 2026, sequence 0001)
     */
    protected function generateNoTransaksiTukarHari(): string
    {
        $prefix = 'TH';
        $kodePerusahaan = '01';
        $tahun = date('y');
        $bulan = date('m');

        // Get last sequence
        $lastTrans = DB::table('N_HRIS_Transaksi_Tukar_Hari')
            ->where('No_Transaksi', 'like', "{$prefix}{$bulan}-{$tahun}-%")
            ->orderBy('Id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastTrans) {
            $parts = explode('-', $lastTrans->No_Transaksi);
            $sequence = intval(end($parts)) + 1;
        }

        return sprintf('%s%s-%s-%04d', $prefix, $bulan, $tahun, $sequence);
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
        $result = DB::select('SELECT TOP 1 StatusHari FROM dbo.N_HRIS_FN_GetStatusHariRange(?,?,?,?)', [
            '001',
            $kodeKaryawan,
            $date,
            $date,
            $date,
        ]);

        $decision = ! empty($result) && str_starts_with($result[0]->StatusHari, 'OFF');

        return $decision;
    }

    public function getHariSwapDates(string $kodeKaryawan, string $date, ?string $type = null): array
    {
        $batasBackDateTukarHari = config('services.hcis.swap_shift_request.backdate_max_days') ?? 0;
        $batasFutureDateTukarHari = config('services.hcis.swap_shift_request.future_max_days') ?? 0;
        // Ambil Tanggal Hari Day off
        // dd($batasBackDateTukarHari);
        $startDate = Carbon::parse($date)->subDays($batasBackDateTukarHari)->format('Y-m-d');
        $endDate = Carbon::parse($date)->addDays($batasFutureDateTukarHari)->format('Y-m-d');
        // dd($startDate, $endDate, $kodeKaryawan);
        $RawDates = [];
        if (! empty($type) && $type == 'WORKING_DAY') {
            $RawDates = DB::select("SELECT * FROM dbo.N_HRIS_FN_GetStatusHariRange(?,?,?,?) where StatusHari like 'OFF%'", [
                '001',
                $kodeKaryawan,
                $startDate,
                $endDate,
            ]);
        } elseif (! empty($type) && $type == 'DAY_OFF') {
            $RawDates = DB::select("SELECT * FROM dbo.N_HRIS_FN_GetStatusHariRange(?,?,?,?) where StatusHari like 'WORK%'", [
                '001',
                $kodeKaryawan,
                $startDate,
                $endDate,
            ]);
        } else {
            $RawDates = DB::select('SELECT * FROM dbo.N_HRIS_FN_GetStatusHariRange(?,?,?,?)', [
                '001',
                $kodeKaryawan,
                $startDate,
                $endDate,
            ]);
        }
        $finalDates = collect($RawDates)->map(function ($item) {
            $explodeStatus = explode('-', $item->StatusHari);
            // dd($explodeStatus);
            $keteranganItem = $explodeStatus[1] ?? null;
            $statusHariItem = $explodeStatus[0];

            return [
                'tanggal' => Carbon::parse($item->Tanggal)->format('Y-m-d'),
                'status_hari' => $statusHariItem,
                'keterangan' => $keteranganItem,
                'isActive' => $keteranganItem == 'Tukar_Hari' ? false : true,
            ];
        })->values()->toArray();

        // dd($finalDates);
        return $finalDates;
    }
}
