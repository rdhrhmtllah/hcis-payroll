<?php

namespace App\Services;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\WhatsappLogHelper;
use App\Services\Approval\ApprovalFlowService;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use App\Services\Notification\WhatsappMessageBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    // Configuration
    protected array $config = [
        'backdate_needs_approval' => true,
        'same_day_needs_approval' => true,
        'future_needs_approval' => true,
        'backdate_max_days' => 7,
        'future_max_days' => 30,
    ];

    public function __construct(ApprovalFlowService $approvalFlowService, WhatsappLogHelper $whatsappLogHelper, WhatsappMessageBuilder $whatsappMessageBuilder)
    {
        $this->approvalFlowService = $approvalFlowService;
        $this->whatsappLogHelper = $whatsappLogHelper;
        $this->whatsappMessageBuilder = $whatsappMessageBuilder;
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
                $flowResult = $this->approvalFlowService->createFlowForLegacySingleAppro($noTransaksi, $kodeKaryawan, 8);
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

    /**
     * Create transaction records
     */
    protected function createTransaction(string $kodeKaryawan, string $targetTable, ?string $alasan): string
    {
        $noTransaksi = $this->generateNoTransaksi();
        $userId = Auth::id() ?? 'system';

        // Insert header
        DB::table('N_HRIS_Transaksi_Shift')->insert([
            'No_Transaksi' => $noTransaksi,
            'Kode_Perusahaan' => '001',
            'Kode_Karyawan' => $kodeKaryawan,
            'Target_Table' => $targetTable,
            'Alasan' => $alasan,
            // 'Flag_Approval' => null,
            'Status' => null,
            'Created_At' => now(),
            'Created_By' => $userId,
        ]);

        // Insert details

        return $noTransaksi;
    }

    protected function createTransactionDetail(string $noTransaksi, array $dates, ?string $flagApproval = null, ?string $flagApplied = null): void
    {
        // dd($noTransaksi, $dates, $flagApproval);
        foreach ($dates as $item) {
            // dd($item);
            DB::table('N_HRIS_Transaksi_Shift_Detail')->insert([
                'No_Transaksi' => $noTransaksi,
                'Tanggal_Shift' => $item['tanggal'],
                'Id_Shift_Diminta' => $item['id_shift'],
                'Id_Shift_Sebelumnya' => $item['id_shift_sebelumnya'] ?? null,
                'Jenis_Pengajuan' => $item['jenis_pengajuan'],
                'Needs_Approval' => $item['needs_approval'] ? 'Y' : 'T',
                'Flag_Applied' => $flagApplied,
                'Flag_Approval' => $flagApproval,
                'Created_At' => now(),
            ]);
        }
    }

    /**
     * Apply shift directly without approval
     */
    protected function applyShiftDirect(string $kodeKaryawan, array $item, string $targetTable): void
    {
        $userId = Auth::id() ?? 'system';

        if ($targetTable === 'SEMENTARA') {
            // Check if exists
            $existing = DB::table('HRIS_Shift_Sementara')
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->where('Tanggal', $item['tanggal'])
                ->first();

            if ($existing) {
                DB::table('HRIS_Shift_Sementara')
                    ->where('Urut', $existing->Urut)
                    ->update([
                        'ID_Shift' => $item['id_shift'],
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('HRIS_Shift_Sementara')->insert([
                    'Kode_Perusahaan' => '001',
                    'Kode_Karyawan' => $kodeKaryawan,
                    'ID_Shift' => $item['id_shift'],
                    'Tanggal' => $item['tanggal'],
                    'UserID' => $userId,
                    'created_at' => now(),
                ]);
            }
        } else {
            // PERMANEN - insert to HRIS_Shift_Per_Karyawan
            DB::table('HRIS_Shift_Per_Karyawan')->insert([
                'Kode_Perusahaan' => '001',
                'Kode_Karyawan' => $kodeKaryawan,
                'ID_Shift' => $item['id_shift'],
                'Periode' => $item['tanggal'],
                'UserID' => $userId,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Apply shift after approval complete
     */
    /**
     * Apply shift after approval complete
     */
    public function applyShiftAfterApproval(string $noTransaksi): void
    {
        $header = DB::table('N_HRIS_Transaksi_Shift')
            ->where('No_Transaksi', $noTransaksi)
            ->first();

        if (! $header) {
            return;
        }

        // Get details that are APPROVED (Flag_Approval = 'Y') and not yet applied
        $details = DB::table('N_HRIS_Transaksi_Shift_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Flag_Approval', 'Y') // ONLY apply fully approved details
            ->where(function ($q) {
                $q->whereNull('Flag_Applied')->orWhere('Flag_Applied', '!=', 'Y');
            })
            ->get();

        foreach ($details as $detail) {
            $item = [
                'tanggal' => $detail->Tanggal_Shift,
                'id_shift' => $detail->Id_Shift_Diminta,
            ];

            $this->applyShiftDirect($header->Kode_Karyawan, $item, $header->Target_Table);

            // Mark as applied
            DB::table('N_HRIS_Transaksi_Shift_Detail')
                ->where('Id', $detail->Id)
                ->update(['Flag_Applied' => 'Y']);
        }

        // Update header - Mark as completed (Approved)
        DB::table('N_HRIS_Transaksi_Shift')
            ->where('No_Transaksi', $noTransaksi)
            ->update([
                'Flag_Selesai' => 'Y', // Add status tracking
                'Updated_At' => now(),
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

        // 1. Get pending requests from HRIS_Approval_Request
        $query = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Shift as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('Karyawan as k', 'h.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'kr.Id_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->where('ar.Approver_Karyawan_Kode', $kodeApprover)
            ->whereNull('h.Status') // Exclude cancelled
            ->select(
                'h.*',
                'ar.Id as approval_id',
                'ar.Id_Jenis_Approval',
                'ar.No_Transaksi',
                'ar.Request_Order_Flow',
                'ar.Created_At as Request_Date',
                'k.Nama as Nama_Karyawan',
                'k.Kode_Karyawan',
                'k.UserID_Absen as NIK',
                'vg.nama_level',
                'vg.nama_jabatan',
                'vd.nama_divisi',
                'vd.nama_sub_divisi'
            );
        $stats = [
            'total' => (clone $query)->count(),
            // Pending: Flag_Approval IS NULL
            'pending' => (clone $query)->whereNull('ar.Flag_Approval')->count(),
            // Approved: Flag_Approval IS NOT NULL
            'approved' => (clone $query)->whereNotNull('ar.Flag_Approval')->count(),
        ];

        // Filter: Status (Inbox usually implies Pending, but method might be reused)
        // Default to pending if not specified or if specific 'pending' requested
        // if (empty($filters['status']) || $filters['status'] === 'pending') {
        //     $query->whereNull('ar.Flag_Approval');
        // }
        $query->whereNull('ar.Flag_Approval');

        // Filter: Jabatan (by Id_Jabatan)
        if (! empty($filters['jabatan'])) {
            $query->where('vg.Id_Jabatan', $filters['jabatan']);
        }

        // Filter: Search (Name or NIK)
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('k.UserID_Absen', 'like', "%{$search}%")
                    ->orWhere('ar.No_Transaksi', 'like', "%{$search}%");
            });
        }

        // Filter: Date Range - Based on Detail's Tanggal_Shift
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta');
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta');

            // Filter transactions that have at least one detail with Tanggal_Shift in range
            $query->whereExists(function ($subquery) use ($startDate, $endDate) {
                $subquery->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Detail as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '>=', $startDate)
                    ->whereDate('detail.Tanggal_Shift', '<=', $endDate);
            });
        } elseif (! empty($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta');
            $query->whereExists(function ($subquery) use ($startDate) {
                $subquery->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Detail as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '>=', $startDate);
            });
        } elseif (! empty($filters['end_date'])) {
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta');
            $query->whereExists(function ($subquery) use ($endDate) {
                $subquery->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Detail as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '<=', $endDate);
            });
        }

        $countItems = $query->count();
        $items = $query
            ->offset($offset)
            ->limit($limit)
            ->orderBy('ar.created_at', 'desc')
            ->get();

        // Enrich with details summary
        foreach ($items as $item) {
            $this->enrichTransactionWithDetails($item);
        }

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

        $query = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Shift as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('Karyawan as k', 'h.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('Karyawan as kr', 'ar.Kode_Karyawan', '=', 'kr.Kode_Karyawan')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'kr.Id_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'kr.Id_Divisi_Sub_Divisi')
            ->where('ar.Approver_Karyawan_Kode', $kodeApprover)
            ->whereNotNull('ar.Flag_Approval') // ONLY Processed
            ->select(
                'h.*',
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
                'vd.nama_sub_divisi'
            );

        // Filter: Status (Approved/Rejected)
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'approved') {
                $query->where('ar.Flag_Approval', 'Y');
            } elseif ($filters['status'] === 'rejected') {
                $query->where('ar.Flag_Approval', 'T');
            }
        }

        // Filter: Jabatan (by Id_Jabatan)
        if (! empty($filters['jabatan'])) {
            $query->where('vg.Id_Jabatan', $filters['jabatan']);
        }

        // Filter: Search (Name or NIK)
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('k.UserID_Absen', 'like', "%{$search}%")
                    ->orWhere('ar.No_Transaksi', 'like', "%{$search}%");
            });
        }

        // Filter: Date Range - Based on Detail's Tanggal_Shift
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta');
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta');

            // Filter transactions that have at least one detail with Tanggal_Shift in range
            $query->whereExists(function ($subquery) use ($startDate, $endDate) {
                $subquery->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Detail as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '>=', $startDate)
                    ->whereDate('detail.Tanggal_Shift', '<=', $endDate);
            });
        } elseif (! empty($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta');
            $query->whereExists(function ($subquery) use ($startDate) {
                $subquery->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Detail as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '>=', $startDate);
            });
        } elseif (! empty($filters['end_date'])) {
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta');
            $query->whereExists(function ($subquery) use ($endDate) {
                $subquery->select(DB::raw(1))
                    ->from('N_HRIS_Transaksi_Shift_Detail as detail')
                    ->whereColumn('detail.No_Transaksi', 'h.No_Transaksi')
                    ->whereDate('detail.Tanggal_Shift', '<=', $endDate);
            });
        }

        // Pagination
        $totalItems = $query->count();
        $pagination = $query
            ->offset($offset)
            ->limit($limit)
            ->orderBy('ar.Tanggal_Approval', 'desc')
            ->get();

        $items = $pagination;
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

    private function enrichTransactionWithDetails($item)
    {
        $details = DB::table('N_HRIS_Transaksi_Shift_Detail as d')
            ->join('HRIS_Shift_Kerja as s', 'd.Id_Shift_Diminta', '=', 's.ID_Shift')
            ->leftJoin('HRIS_Shift_Kerja as s_old', 'd.Id_Shift_Sebelumnya', '=', 's_old.ID_Shift')
            ->where('d.No_Transaksi', $item->No_Transaksi)
            ->where('d.Needs_Approval', 'Y')
            ->select(
                'd.Id_Shift_Sebelumnya',
                'd.Id as Id_Detail',
                'd.Tanggal_Shift',

                // yang lama (tetap)
                's.Nama as Nama_Shift',

                // tambahan baru
                's_old.Nama as Nama_Shift_Sebelumnya',

                'd.Flag_Approval',
                'd.Needs_Approval'
            )
            ->orderBy('d.Tanggal_Shift')
            ->get();

        $item->total_days = $details->count();
        $item->dates_summary = $details->map(function ($d) {
            return Carbon::parse($d->Tanggal_Shift)->format('d M');
        })->take(3)->implode(', ');

        if ($details->count() > 3) {
            $item->dates_summary .= ', +'.($details->count() - 3).' more';
        }

        $item->details = $details;
    }

    /**
     * Process approval (Approve/Reject)
     * Supports partial approval by updating details first
     */
    public function processApproval(string $kodeApprover, string $noTransaksi, string $action, array $detailApprovals = [], ?string $notes = null): array
    {
        // dd($kodeApprover, $noTransaksi, $action, $detailApprovals, $notes);
        return DB::transaction(function () use ($kodeApprover, $noTransaksi, $action, $detailApprovals, $notes) {
            // 1. Validate Approval Request
            $approvalReq = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Approver_Karyawan_Kode', $kodeApprover)
                ->whereNull('Flag_Approval')
                ->first();
            // dd($approvalReq);
            if (! $approvalReq) {
                throw new \Exception('Approval request not found or already processed.');
            }

            // 2. Handle Partial Detail Approvals
            // If details provided, update Flag_Approval in N_HRIS_Transaksi_Shift_Detail
            if (! empty($detailApprovals)) {
                foreach ($detailApprovals as $detailId => $status) { // $status: 'Y' or 'T'
                    DB::table('N_HRIS_Transaksi_Shift_Detail')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Id', $detailId) // Assuming external ID or match by Date if needed.
                        // Simplified: We assume passed array is [Tanggal => Status] or we update ALL if action is global
                        // Let's assume the frontend sends map of [Tanggal => 'Y'/'T']
                        // ->where('Tanggal_Shift', $detailId) // Using date as key might be safer if ID not propagated
                        ->update(['Flag_Approval' => $status]);
                }
            } else {
                // Global Action: Update ALL details that need approval
                $detailStatus = ($action === 'APPROVE') ? 'Y' : 'T';
                DB::table('N_HRIS_Transaksi_Shift_Detail')
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Needs_Approval', 'Y')
                    ->update(['Flag_Approval' => $detailStatus]);
            }

            // Get tanggal for WhatsApp notification
            $getTanggal = [];
            if (! empty($detailApprovals)) {
                $getTanggal = DB::table('N_HRIS_Transaksi_Shift_Detail')
                    ->whereIn('Id', array_keys($detailApprovals))
                    ->pluck('Tanggal_Shift')->toArray();
            } else {
                // Global approve/reject - get all details that need approval
                $getTanggal = DB::table('N_HRIS_Transaksi_Shift_Detail')
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

                $pesan = $this->whatsappMessageBuilder->requesterShiftNotification($hpRequester, $noTransaksi, $tanggalFormated);
                $this->whatsappLogHelper->sendAndLog($approvalReq->Kode_Karyawan, $namaRequester, $hpRequester, $noTransaksi, $pesan);

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

            $pesan = $this->whatsappMessageBuilder->requesterShiftNotification($hpRequester, $noTransaksi, $tanggalFormated);
            $this->whatsappLogHelper->sendAndLog($approvalReq->Kode_Karyawan, $namaRequester, $hpRequester, $noTransaksi, $pesan);

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

        $query = DB::table('N_HRIS_Transaksi_Shift as h')
            ->where('h.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('h.Status'); // Exclude cancelled

        // Apply filters
        if (! empty($filters['status'])) {
            if ($filters['status'] === 'Y') {
                // Sudah Direview
                $query->where('h.Flag_Selesai', 'Y');
            } elseif ($filters['status'] === 'null') {
                // Belum Direview
                $query->where(function ($q) {
                    $q->whereNull('h.Flag_Selesai')
                        ->orWhere('h.Flag_Selesai', '!=', 'Y');
                });
            }
        }

        // Date range filter - Handle ISO dates from frontend properly
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            // Parse and convert to local timezone to get correct date
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta')->startOfDay();
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta')->endOfDay();
            $query->whereBetween('h.Created_At', [$startDate, $endDate]);
        } elseif (! empty($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->timezone('Asia/Jakarta')->startOfDay();
            $query->where('h.Created_At', '>=', $startDate);
        } elseif (! empty($filters['end_date'])) {
            $endDate = Carbon::parse($filters['end_date'])->timezone('Asia/Jakarta')->endOfDay();
            $query->where('h.Created_At', '<=', $endDate);
        }

        // Search filter (by No_Transaksi)
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('h.No_Transaksi', 'like', "%{$search}%");
        }

        if (! empty($filters['periode'])) {
            $query->whereYear('h.Created_At', $filters['periode']);
        }

        $countTransactions = $query->count();

        $transactions = $query
            ->select('h.*')
            ->offset($offset)
            ->limit($limit)
            ->orderBy('h.Created_At', 'desc')
            ->get();

        // Enrich with details
        foreach ($transactions as $trans) {
            $trans->details = DB::table('N_HRIS_Transaksi_Shift_Detail as d')
                ->join('HRIS_Shift_Kerja as s', 'd.Id_Shift_Diminta', '=', 's.ID_Shift')
                ->where('d.No_Transaksi', $trans->No_Transaksi)
                ->select('d.*', 's.Nama as Nama_Shift')
                ->distinct()
                ->get();

            $trans->tanggal_count = $trans->details->count();
            $trans->shift_display = $trans->details->count() > 1
                ? 'Mixed'
                : ($trans->details->first()?->Nama_Shift ?? '-');
        }

        // Calculate stats (Global stats for the user)
        $statsQuery = DB::table('N_HRIS_Transaksi_Shift')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->whereNull('Status'); // Exclude cancelled

        $stats = [
            'total' => (clone $statsQuery)->count(),
            // Pending: Flag_Selesai IS NULL or != 'Y'
            'pending' => (clone $statsQuery)->where(function ($q) {
                $q->whereNull('Flag_Selesai')->orWhere('Flag_Selesai', '!=', 'Y');
            })->count(),
            // Approved: Flag_Selesai = 'Y'
            'approved' => (clone $statsQuery)->where('Flag_Selesai', 'Y')->count(),
        ];

        // $data = [
        //     'data' => $transactions,
        //     'stats' => $stats,
        // ];

        return [
            'items' => $transactions,
            'total' => $countTransactions,
            'page' => $page,
            'limit' => $limit,
            // 'pagination' => [
            //     'current_page' => $transactions->currentPage(),
            //     'last_page' => $transactions->lastPage(),
            //     'total' => $transactions->total(),
            // ],
            'stats' => $stats,
        ];
    }

    /**
     * Get transaction detail
     */
    public function getDetail(string $noTransaksi): ?array
    {
        $header = DB::table('N_HRIS_Transaksi_Shift')
            ->where('No_Transaksi', $noTransaksi)
            ->first();

        if (! $header) {
            return null;
        }

        $detailsRaw = DB::table('N_HRIS_Transaksi_Shift_Detail as d')
            ->join('HRIS_Shift_Kerja as s', 'd.Id_Shift_Diminta', '=', 's.ID_Shift')
            ->join('HRIS_Shift_Kerja_Detail as sd', 's.ID_Shift', '=', 'sd.ID_Shift')
            ->join('HRIS_Waktu_Kerja as w', 'sd.ID_Waktu_Kerja', '=', 'w.ID_Waktu_Kerja')
            ->leftJoin('HRIS_Shift_Kerja as sOld', 'd.Id_Shift_Sebelumnya', '=', 'sOld.ID_Shift') // LEFT JOIN for nullable previous shift
            ->where('d.No_Transaksi', $noTransaksi)
            ->select('d.*', 's.Nama as Nama_Shift', 'w.Jam_Masuk', 'w.Jam_Keluar', 'sOld.Nama as Nama_Shift_Sebelumnya', 'sd.Hari')
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
            'header' => $header,
            'details' => $details,
            'approval_steps' => $approvalSteps,
        ];
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
            ->groupBy('sk.ID_Shift', 'sk.Nama', 'w.Jam_Masuk', 'w.Jam_Keluar')
            ->orderBy('sk.Nama')
            ->select(
                'sk.ID_Shift as id',
                'sk.Nama as nama',
                'w.Jam_Masuk as jam_masuk',
                'w.Jam_Keluar as jam_keluar'
            )
            ->get()
            ->map(function ($shift) {
                // Format jam dengan null-safe
                $jamMasuk = $shift->jam_masuk ? substr($shift->jam_masuk, 0, 5) : '--:--';
                $jamKeluar = $shift->jam_keluar ? substr($shift->jam_keluar, 0, 5) : '--:--';

                $shift->jam_masuk = $jamMasuk;
                $shift->jam_keluar = $jamKeluar;
                $shift->display = sprintf('%s (%s - %s)', $shift->nama, $jamMasuk, $jamKeluar);

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
            ->groupBy('sk.ID_Shift', 'sk.Nama', 'w.Jam_Masuk', 'w.Jam_Keluar')
            ->orderBy('sk.Nama')
            ->select(
                'sk.ID_Shift as id',
                'sk.Nama as nama',
                'w.Jam_Masuk as jam_masuk',
                'w.Jam_Keluar as jam_keluar'
            )
            ->get()
            ->map(function ($shift) {
                // Format jam dengan null-safe
                $jamMasuk = $shift->jam_masuk ? substr($shift->jam_masuk, 0, 5) : '--:--';
                $jamKeluar = $shift->jam_keluar ? substr($shift->jam_keluar, 0, 5) : '--:--';

                $shift->jam_masuk = $jamMasuk;
                $shift->jam_keluar = $jamKeluar;
                $shift->display = sprintf('%s (%s - %s)', $shift->nama, $jamMasuk, $jamKeluar);

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
        // log transaksi
        $transaksi = DB::table('N_HRIS_Transaksi_Shift as a')
            ->join('N_HRIS_Transaksi_Shift_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->where('a.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('a.Status')
            ->where(function ($q) {
                $q->whereNull('a.Flag_Selesai')
                    ->orWhere('a.Flag_Selesai', '!=', 'Y');
            })
            ->whereBetween('b.Tanggal_Shift', [$startDate, $endDate])
            ->select('a.Flag_Selesai', 'a.Target_Table', 'b.Id as urut_detail', 'b.Tanggal_Shift', 'b.Id_Shift_Diminta', 'b.Jenis_Pengajuan', 'b.Needs_approval', 'b.Flag_Applied', 'b.Created_At', 'b.Flag_Approval')
            ->orderBy('b.Tanggal_Shift', 'asc')
            ->orderBy('b.Id', 'desc')
            ->get();

        // dd($transaksi);

        $schedule = collect($select)->map(function ($item) use ($transaksi) {

            $hari = Carbon::parse($item->Tanggal)->isoWeekday();
            $hariHRIS = $hari == 7 ? 1 : $hari + 1;

            $logTran = $transaksi
                ->where('Tanggal_Shift', Carbon::parse($item->Tanggal)->format('Y-m-d'))
                ->sortByDesc('Created_At')
                ->sortByDesc('urut_detail')
                ->first();
            // dd($logTran);

            $usedShift = $logTran ? $logTran->Id_Shift_Diminta : $item->Id_Shift;

            $jenisShift = $logTran ? $logTran->Target_Table : trim(explode('|', $item->JENIS_SHIFT_DIPILIH)[0]);

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

            // dd($logTran);
            return $this->formatShiftForCalendar(
                Carbon::parse($item->Tanggal),
                $tempShift,
                $ApprovalShift,
                $isLocked
            );
        })->values()->toArray();

        // dd($schedule );
        return $schedule;
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
    protected function formatShiftForCalendar(Carbon $date, ?object $shift, ?string $approvalShift, bool $isLocked = false): array
    {
        return [
            'date' => $date->format('Y-m-d'),
            'day_name' => $date->translatedFormat('l'),
            'day_short' => $date->format('d'),
            'month_short' => $date->translatedFormat('M'),
            'is_today' => $date->isToday(),
            'is_locked' => $isLocked,

            'shift' => $shift ? [
                'id' => $shift->ID_Shift,
                'nama' => $shift->Nama,
                'jam_masuk' => substr($shift->Jam_Masuk, 0, 5),
                'jam_keluar' => substr($shift->Jam_Keluar, 0, 5),
            ] : null,
            'Flag_Approval' => $approvalShift ? $approvalShift : 'N',
            'source' => $shift->source ?? null,
        ];
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

        // Find existing transactions for these dates that are NOT finished
        $locked = DB::table('N_HRIS_Transaksi_Shift as a')
            ->join('N_HRIS_Transaksi_Shift_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->where('a.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('a.Status') // Not Cancelled
            ->where(function ($q) {
                $q->whereNull('a.Flag_Selesai')
                    ->orWhere('a.Flag_Selesai', '!=', 'Y');
            })
            ->whereIn('b.Tanggal_Shift', $dateValues)
            ->pluck('b.Tanggal_Shift')
            ->toArray();

        return array_unique($locked);
    }

    /**
     * Get all locked dates (pending request) for a user
     */
    public function getAllLockedDates(string $kodeKaryawan): array
    {
        return DB::table('N_HRIS_Transaksi_Shift as a')
            ->join('N_HRIS_Transaksi_Shift_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->where('a.Kode_Karyawan', $kodeKaryawan)
            ->whereNull('a.Status') // Not Cancelled
            ->where(function ($q) {
                $q->whereNull('a.Flag_Selesai')
                    ->orWhere('a.Flag_Selesai', '!=', 'Y');
            })
            ->where('b.Needs_Approval', 'Y')
            ->where('b.Flag_applied', '!=', 'Y')
            ->pluck('b.Tanggal_Shift')
            ->unique()
            ->values()
            ->toArray();
    }
}
