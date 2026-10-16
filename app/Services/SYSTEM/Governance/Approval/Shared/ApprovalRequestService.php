<?php

namespace App\Services\SYSTEM\Governance\Approval\Shared;

use Illuminate\Support\Facades\DB;

/**
 * Service untuk manage approval request flow state dan transitions
 *
 * Handles:
 * - Current approver resolution
 * - Next approver logic based on Stop_If_Rejected & Is_Mandatory
 * - Flow state validation (CURRENT, FUTURE, VOID, HISTORY)
 * - Approval continuation after approve/reject
 *
 * Uses N_HRIS_VW_Approval_Request_V2 untuk state determination
 */
class ApprovalRequestService
{
    /**
     * Get current approver untuk transaksi
     *
     * Returns approval request dengan Flow_State = 'CURRENT'
     */
    public function getCurrentApprovalRequest(string $noTransaksi): ?object
    {
        return DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Flow_State', 'CURRENT')
            ->orderBy('Request_Order_Flow', 'asc')
            ->first();
    }

    /**
     * Get semua pending approvals (CURRENT + FUTURE)
     *
     * @return \Illuminate\Support\Collection
     */
    public function getPendingApprovals(string $noTransaksi)
    {
        return DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('No_Transaksi', $noTransaksi)
            ->whereIn('Flow_State', ['CURRENT', 'FUTURE'])
            ->orderBy('Request_Order_Flow', 'asc')
            ->get();
    }

    /**
     * Get pending approvals untuk specific approver (inbox/to-do list)
     *
     * Returns CURRENT approvals yang:
     * - SERIAL: Approver_Karyawan_Kode matches (direct assignment)
     * - GROUP: Approver is member of group (parallel approval)
     *
     * @return \Illuminate\Support\Collection
     */
    public function getPendingApprovalsForApprover(string $kodeApprover)
    {
        $query = DB::table('N_HRIS_VW_Approval_Request_V2 as ar')
            ->where('ar.Flow_State', 'CURRENT')
            ->select('ar.*');

        $this->applyApproverAccessFilter($query, $kodeApprover);

        return $query->orderBy('Request_Order_Flow', 'asc')->get();
    }

    /**
     * Count pending approvals untuk specific approver
     *
     * Used for dashboard stats
     */
    public function countPendingApprovalsForApprover(string $kodeApprover): int
    {
        $query = DB::table('N_HRIS_VW_Approval_Request_V2 as ar')
            ->where('ar.Flow_State', 'CURRENT');

        $this->applyApproverAccessFilter($query, $kodeApprover);

        return $query->count();
    }

    /**
     * Get processed approvals count untuk specific approver
     *
     * Historicals approvals dalam periode specified
     *
     * @param  string  $decision  'ALL' | 'APPROVE' | 'REJECT' (default: 'ALL')
     * @param  int|null  $offsetMonth  Months back from now (null = current month)
     */
    public function countProcessedApprovalsForApprover(
        string $kodeApprover,
        string $decision = 'ALL',
        ?int $offsetMonth = null,
    ): int {
        $query = DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('Flow_State', 'HISTORY')
            ->where('Approved_By_Kode', $kodeApprover);

        // Filter by decision type
        if ($decision === 'APPROVE') {
            $query->where('Flag_Approval', 'Y');
        } elseif ($decision === 'REJECT') {
            $query->where('Flag_Approval', 'T');
        }

        // Filter by month — RANGE sargable (>= awal bulan AND < awal bulan berikutnya)
        // menggantikan whereMonth/whereYear (fungsi pada kolom memblok predicate
        // pushdown ke tabel dasar view + mematikan index).
        $refDate = $offsetMonth === null ? now() : now()->subMonths($offsetMonth);
        $startOfMonth = $refDate->copy()->startOfMonth()->toDateTimeString();
        $startOfNextMonth = $refDate->copy()->startOfMonth()->addMonth()->toDateTimeString();
        $query
            ->where('Tanggal_Approval', '>=', $startOfMonth)
            ->where('Tanggal_Approval', '<', $startOfNextMonth);

        return $query->count();
    }

    /**
     * Determine next action setelah approve/reject decision
     *
     * Logic:
     * - APPROVAL: Is_Mandatory='Y' → mandatory, 'T' → optional audit
     * - REJECTION: Stop_If_Rejected='Y' → halt, 'T' → continue
     *
     * Return:
     * [
     *   'action' => 'CONTINUE'|'COMPLETE'|'REJECT_HALT'|'REJECT_CONTINUE'|'AUDIT_PASS',
     *   'nextApproval' => object|null,
     *   'isTerminal' => bool,
     *   'reason' => string
     * ]
     *
     * @param  string  $decision  'APPROVE' | 'REJECT'
     * @param  object|null  $processedApproval  Approval row context that was just processed (before state shift)
     */
    public function determineNextAction(
        string $noTransaksi,
        string $decision,
        ?object $processedApproval = null,
    ): array {
        $current =
            $processedApproval ?:
            $this->getCurrentApprovalRequest($noTransaksi);

        if (! $current) {
            return [
                'action' => 'COMPLETE',
                'nextApproval' => null,
                'isTerminal' => true,
                'reason' => 'Tidak ada approval yang current',
            ];
        }

        // ===== REJECTION CASE =====
        if ($decision === 'REJECT') {
            // Stop_If_Rejected = 'Y' → HALT ENTIRE FLOW
            if ($current->Stop_If_Rejected === 'Y') {
                return [
                    'action' => 'REJECT_HALT',
                    'nextApproval' => null,
                    'isTerminal' => true,
                    'reason' => 'Stop_If_Rejected=Y, transaksi ditolak dan tidak bisa lanjut ke approver berikutnya',
                    'current' => $current,
                ];
            }

            // Stop_If_Rejected = 'T' → CAN CONTINUE TO NEXT
            $next = $this->getNextMandatoryApproval(
                $noTransaksi,
                $current->Request_Order_Flow,
            );

            return [
                'action' => 'REJECT_CONTINUE',
                'nextApproval' => $next,
                'isTerminal' => $next === null,
                'reason' => 'Stop_If_Rejected=T, penolakan ini tidak stop flow, lanjut ke approver berikutnya',
                'current' => $current,
            ];
        }

        // ===== APPROVAL CASE =====
        if ($decision === 'APPROVE') {
            // Is_Mandatory = 'T' → OPTIONAL AUDIT STEP
            if ($current->Is_Mandatory === 'T') {
                return [
                    'action' => 'AUDIT_PASS',
                    'nextApproval' => null,
                    'isTerminal' => false,
                    'reason' => 'Is_Mandatory=T (optional audit), transaksi bisa langsung selesai tanpa approver berikutnya',
                    'current' => $current,
                ];
            }

            // Is_Mandatory = 'Y' → MANDATORY, FIND NEXT
            $next = $this->getNextMandatoryApproval(
                $noTransaksi,
                $current->Request_Order_Flow,
            );

            if (! $next) {
                return [
                    'action' => 'COMPLETE',
                    'nextApproval' => null,
                    'isTerminal' => true,
                    'reason' => 'Semua mandatory approvers sudah selesai, transaksi complete',
                    'current' => $current,
                ];
            }

            return [
                'action' => 'CONTINUE',
                'nextApproval' => $next,
                'isTerminal' => false,
                'reason' => 'Ada approver berikutnya, lanjut ke step '.
                    $next->Request_Order_Flow,
                'current' => $current,
            ];
        }

        return [
            'action' => 'UNKNOWN',
            'nextApproval' => null,
            'isTerminal' => false,
            'reason' => 'Unknown decision type',
        ];
    }

    /**
     * Get next mandatory approver (helper untuk determineNextAction dan ApprovalService)
     *
     * Cari approver dengan Request_Order_Flow > currentOrder yang:
     * - Flow_State != 'VOID'
     * - Preference: Is_Mandatory='Y' dulu, jika ada
     */
    public function getNextMandatoryApproval(
        string $noTransaksi,
        int $currentOrder,
    ): ?object {
        return DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Request_Order_Flow', '>', $currentOrder)
            ->where('Flow_State', '!=', 'VOID')
            ->orderBy('Is_Mandatory', 'desc') // Mandatory first
            ->orderBy('Request_Order_Flow', 'asc')
            ->first();
    }

    /**
     * Validate bahwa approver bisa approve transaksi ini
     *
     * Checks:
     * - Must be CURRENT state
     * - SERIAL: Approver_Karyawan_Kode harus match
     * - GROUP: Approver must be member of group
     *
     * @return array ['valid' => bool, 'reason' => string]
     */
    public function validateApproverAccess(
        string $noTransaksi,
        string $approverKode,
    ): array {
        $approval = $this->getCurrentApprovalRequest($noTransaksi);

        if (! $approval) {
            return [
                'valid' => false,
                'reason' => 'Tidak ada approval yang pending untuk transaksi ini',
            ];
        }

        // GROUP APPROVAL: Check membership
        if ($approval->Flag_Group === 'Y') {
            $jenisApproval = $this->getJenisApproval((int) ($approval->Id_Jenis_Approval ?? 0));
            if (strtoupper((string) ($jenisApproval->Source_Type ?? '')) === 'ROLE') {
                if (
                    $this->isRoleApprover(
                        (int) $approval->Group_Reference_Id,
                        $approverKode,
                        $jenisApproval->Source_Reference ?? null,
                    )
                ) {
                    return ['valid' => true, 'reason' => 'OK'];
                }

                return [
                    'valid' => false,
                    'reason' => 'Anda bukan anggota role approver untuk step ini',
                ];
            }

            /*
             * Anggota grup diambil lewat AnggotaApprovalResolver, bukan kueri
             * sendiri di sini.
             *
             * Kueri yang dulu berdiri di tempat ini MUSTAHIL berhasil:
             * N_HRIS_HC_Approval tidak punya kolom `Group_Reference_Id` maupun
             * `Kode_Karyawan` — yang benar `Group_Id` dan
             * `Kode_Karyawan_Approver`. Setiap langkah approval bergrup
             * ber-Source_Type selain 'ROLE' yang sampai ke sini ditolak SQL
             * Server dengan "Invalid column name", dan karena tak ada try/catch
             * di jalur ini, permintaannya berakhir 500.
             *
             * Kekeliruan yang sama sudah diperbaiki di AnggotaApprovalResolver
             * lengkap dengan catatannya; yang tertinggal hanya salinan di sini.
             * Karena itu ia dipanggil, bukan diperbaiki ulang — dua salinan
             * aturan yang sama adalah cara cacat ini bertahan sejak awal.
             */
            $isMember = app(AnggotaApprovalResolver::class)
                ->dariStepApproval($approval)
                ->contains(fn ($a) => trim((string) $a->Kode_Karyawan) === trim($approverKode));

            if ($isMember) {
                return ['valid' => true, 'reason' => 'OK'];
            }

            return [
                'valid' => false,
                'reason' => 'Anda bukan anggota dari group approver untuk step ini',
            ];
        }

        // SERIAL APPROVAL (legacy-safe): treat T/null as non-group approver.
        if ($approval->Current_Approver === $approverKode) {
            return ['valid' => true, 'reason' => 'OK'];
        }

        return [
            'valid' => false,
            'reason' => 'Anda bukan approver untuk step ini. Approver yang ditunjuk: '.
                ($approval->Current_Approver ?? '-'),
        ];
    }

    /**
     * Get approval summary untuk dashboard/status
     */
    public function getApprovalSummary(string $noTransaksi): array
    {
        $total = DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Flow_State', '!=', 'VOID')
            ->count();

        $completed = DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Flow_State', 'HISTORY')
            ->count();

        $current = $this->getCurrentApprovalRequest($noTransaksi);

        $status = 'PENDING';
        if ($current === null && $completed === $total) {
            $status = 'APPROVED';
        } elseif (
            DB::table('N_HRIS_VW_Approval_Request_V2')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Flow_State', 'VOID')
                ->exists()
        ) {
            $status = 'REJECTED';
        }

        return [
            'totalSteps' => $total,
            'completedSteps' => $completed,
            'remainingSteps' => max(0, $total - $completed),
            'currentStep' => $current,
            'currentStepOrder' => $current?->Request_Order_Flow,
            'status' => $status,
            'progressPercent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }

    /**
     * Get full approval chain history
     *
     * @return \Illuminate\Support\Collection
     */
    public function getApprovalHistory(string $noTransaksi)
    {
        return DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Flow_State', 'HISTORY')
            ->orderBy('Request_Order_Flow', 'asc')
            ->get();
    }

    /**
     * Check apakah transaksi bisa langsung diapprove tanpa seluruh chain
     *
     * True jika: tidak ada mandatory approval step (semua Is_Mandatory='T')
     */
    public function canAutoCompleteWithoutChain(string $noTransaksi): bool
    {
        return ! DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Is_Mandatory', 'Y')
            ->where('Flow_State', '!=', 'VOID')
            ->exists();
    }

    protected function applyApproverAccessFilter($query, string $kodeApprover): void
    {
        $query->where(function ($q) use ($kodeApprover) {
            // SERIAL approvals: Approver_Karyawan_Kode matches.
            $q->where(function ($serial) use ($kodeApprover) {
                $serial
                    ->where(function ($flag) {
                        $flag
                            ->where('ar.Flag_Group', 'T')
                            ->orWhereNull('ar.Flag_Group');
                    })
                    ->where('ar.Current_Approver', $kodeApprover);
            });

            // ROLE pool approvals: any active user with the configured role can process one shared step.
            $q->orWhere(function ($rolePool) use ($kodeApprover) {
                $rolePool
                    ->where('ar.Flag_Group', 'Y')
                    ->whereExists(function ($exists) use ($kodeApprover) {
                        $exists
                            ->select(DB::raw(1))
                            ->from('HRIS_Approval_Request as req')
                            ->join('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'req.Id_Jenis_Approval')
                            ->join('N_HRIS_Role_Functional as rf', function ($join) {
                                $join
                                    ->on('rf.Id_Role', '=', 'req.Group_Reference_Id')
                                    ->on('rf.Role_Code', '=', 'ja.Source_Reference');
                            })
                            ->join('N_HRIS_Role_User as ru', 'ru.Id_Role', '=', 'rf.Id_Role')
                            ->join('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
                            ->whereColumn('req.id', 'ar.Id_Approval_Request')
                            ->where('ja.Source_Type', 'ROLE')
                            ->where('ja.Flag_Aktif', 'Y')
                            ->where('rf.Flag_Aktif', 'Y')
                            ->where('ru.Flag_Aktif', 'Y')
                            ->where('k.Aktif', 'Y')
                            ->where('k.Kode_Karyawan', $kodeApprover);
                    });
            });

        });
    }

    protected function getJenisApproval(int $idJenisApproval): ?object
    {
        if ($idJenisApproval <= 0) {
            return null;
        }

        return DB::table('N_HRIS_Jenis_Approval')
            ->where('Id_Jenis_Approval', $idJenisApproval)
            ->first();
    }

    protected function isRoleApprover(int $roleId, string $kodeApprover, ?string $roleCode = null): bool
    {
        if ($roleId <= 0 || $kodeApprover === '') {
            return false;
        }

        $query = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
            ->where('rf.Id_Role', $roleId)
            ->where('rf.Flag_Aktif', 'Y')
            ->where('ru.Flag_Aktif', 'Y')
            ->where('k.Aktif', 'Y')
            ->where('k.Kode_Karyawan', $kodeApprover);

        if ($roleCode !== null && trim($roleCode) !== '') {
            $query->where('rf.Role_Code', trim($roleCode));
        }

        return $query->exists();
    }
}
