<?php

namespace App\Services\ESS\Attendance\Shift\Approval;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service for Shift Approval Flow Management
 *
 * Manages approver assignments for shift requests.
 * Master table: N_HRIS_Approval_Flow_Shift
 * Transaction tables: N_HRIS_Transaksi_Approver_Shift, N_HRIS_Transaksi_Approver_Shift_Detail
 */
class ApprovalFlowShiftService
{
    /**
     * Get dashboard statistics
     */
    public function getStats(): array
    {
        $totalApprovers = DB::table('N_HRIS_Approval_Flow_Shift')
            ->whereNull('Status')
            ->distinct('Kode_Karyawan')
            ->count('Kode_Karyawan');

        $totalAssignments = DB::table('N_HRIS_Approval_Flow_Shift')
            ->whereNull('Status')
            ->count();

        $membersWithoutApprover = DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('N_HRIS_Approval_Flow_Shift as afs')
                    ->whereColumn('afs.Kode_Karyawan_Requester', 'k.Kode_Karyawan')
                    ->whereNull('afs.Status');
            })
            ->count();

        return [
            'total_approvers' => $totalApprovers,
            'total_assignments' => $totalAssignments,
            'members_without_approver' => $membersWithoutApprover,
        ];
    }

    /**
     * Get list of approvers for dropdown
     */
    public function getApprovers(array $filters = []): array
    {
        $query = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.Id_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'k.Id_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'k.UserId_Absen as NIK',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
                'vg.nama_jabatan',
                DB::raw('(SELECT COUNT(*) FROM N_HRIS_Approval_Flow_Shift WHERE Kode_Karyawan = k.Kode_Karyawan AND Status IS NULL) as member_count')
            );

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('k.UserId_Absen', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['divisi'])) {
            $query->where('vd.ID_DIVISI', $filters['divisi']);
        }

        return $query->orderBy('k.Nama')->get()->toArray();
    }

    /**
     * Get members for assignment (with filter by those without approver)
     */
    public function getMembers(array $filters = []): array
    {
        $query = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.Id_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'k.Id_Level_Jabatan')
            ->leftJoin('N_HRIS_Approval_Flow_Shift as afs', function ($join) {
                $join->on('afs.Kode_Karyawan_Requester', '=', 'k.Kode_Karyawan')
                    ->whereNull('afs.Status');
            })
            ->leftJoin('Karyawan as approver', 'approver.Kode_Karyawan', '=', 'afs.Kode_Karyawan')
            ->where('k.Aktif', 'Y')
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'k.UserId_Absen as NIK',
                'vd.nama_divisi',
                'vd.ID_DIVISI as divisi_id',
                'vd.nama_sub_divisi',
                'vg.nama_jabatan',
                'vg.Id_Level as level_id',
                'afs.Kode_Karyawan as current_approver_kode',
                'approver.Nama as current_approver_nama'
            );

        // Filter by assignment status
        if (! empty($filters['status'])) {
            if ($filters['status'] === 'unassigned') {
                $query->whereNull('afs.Kode_Karyawan');
            } elseif ($filters['status'] === 'assigned') {
                $query->whereNotNull('afs.Kode_Karyawan');
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('k.UserId_Absen', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['divisi'])) {
            $query->where('vd.ID_DIVISI', $filters['divisi']);
        }

        if (! empty($filters['jabatan'])) {
            $query->where('vg.Id_Jabatan', $filters['jabatan']);
        }

        return $query->orderBy('k.Nama')->get()->toArray();
    }

    /**
     * Get all assignments with pagination
     */
    public function getAssignments(array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 15;
        $filters = $params['filters'] ?? [];
        $offset = ($page - 1) * $limit;

        $query = DB::table('N_HRIS_Approval_Flow_Shift as afs')
            ->join('Karyawan as requester', 'requester.Kode_Karyawan', '=', 'afs.Kode_Karyawan_Requester')
            ->join('Karyawan as approver', 'approver.Kode_Karyawan', '=', 'afs.Kode_Karyawan')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vga', 'vga.Id_Level_Jabatan', '=', 'approver.Id_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'requester.Id_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'requester.Id_Level_Jabatan')
            ->whereNull('afs.Status')
            ->select(
                'afs.id',
                'afs.Kode_Karyawan as approver_kode',
                'afs.Kode_Karyawan_Requester as requester_kode',
                'afs.order_flow',
                'afs.Division_Id',
                'afs.level_Id',
                'requester.Nama as requester_nama',
                'requester.UserId_Absen as requester_nik',
                'approver.Nama as approver_nama',
                'approver.UserId_Absen as approver_nik',
                'vd.nama_divisi',
                'vg.nama_jabatan',
                'vga.nama_jabatan as approver_jabatan'
            );

        // Filters
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('requester.Nama', 'like', "%{$search}%")
                    ->orWhere('requester.UserId_Absen', 'like', "%{$search}%")
                    ->orWhere('approver.Nama', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['approver'])) {
            $query->where('afs.Kode_Karyawan', $filters['approver']);
        }

        if (! empty($filters['divisi'])) {
            $query->where('vd.ID_DIVISI', $filters['divisi']);
        }

        $total = (clone $query)->count();

        $items = $query
            ->orderBy('requester.Nama')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return [
            'data' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ];
    }

    /**
     * Bulk assign members to an approver
     */
    public function bulkAssign(string $approverKode, array $memberKodes, ?string $catatan = null): array
    {
        $userId = Auth::id() ?? 'system';

        // Batch-load assignment aktif semua member SEKALI. Sebelumnya loop ini
        // menjalankan 2-3 query per member (tim 100 orang ≈ 300 query/request).
        $currentAssignments = DB::table('N_HRIS_Approval_Flow_Shift')
            ->whereIn('Kode_Karyawan_Requester', $memberKodes)
            ->whereNull('Status')
            ->get()
            ->keyBy('Kode_Karyawan_Requester');

        // VALIDATION: Check for pending requests before assigning
        $approverByMember = [];
        foreach ($memberKodes as $memberKode) {
            $currentAssignment = $currentAssignments->get($memberKode);

            if ($currentAssignment && $currentAssignment->Kode_Karyawan !== $approverKode) {
                $approverByMember[$memberKode] = $currentAssignment->Kode_Karyawan;
            }
        }

        $pendingMembers = $this->membersWithPendingRequests($approverByMember);
        if (! empty($pendingMembers)) {
            $pendingErrors = DB::table('Karyawan')->whereIn('Kode_Karyawan', $pendingMembers)->pluck('Nama')->all();
            $names = implode(', ', array_unique($pendingErrors));
            throw new \Exception("Action blocked: Karyawan berikut masih memiliki pengajuan shift pending dengan approver saat ini: $names.");
        }

        $noTransaksi = $this->generateNoTransaksi('ASN');

        // Get approver's division & level for the master table
        $approver = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.Id_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'k.Id_Level_Jabatan')
            ->where('k.Kode_Karyawan', $approverKode)
            ->select('vd.ID_DIVISI', 'vg.Id_Level')
            ->first();

        $divisionId = $approver->ID_DIVISI ?? 0;
        $levelId = $approver->Id_Level ?? 0;

        $successCount = 0;
        $errorCount = 0;
        $details = [];

        DB::beginTransaction();
        try {
            // Create transaction header
            DB::table('N_HRIS_Transaksi_Approver_Shift')->insert([
                'No_Transaksi' => $noTransaksi,
                'Jenis_Transaksi' => 'ASSIGN',
                'Kode_Approver_Baru' => $approverKode,
                'Total_Affected' => count($memberKodes),
                'Catatan' => $catatan,
                'Created_At' => now(),
                'Created_By' => $userId,
            ]);

            foreach ($memberKodes as $memberKode) {
                // Check if member already has an approver (dari batch load di atas)
                $existing = $currentAssignments->get($memberKode);

                $action = $existing ? 'UPDATE' : 'INSERT';
                $oldApprover = $existing->Kode_Karyawan ?? null;

                try {
                    if ($existing) {
                        // Update existing assignment
                        DB::table('N_HRIS_Approval_Flow_Shift')
                            ->where('id', $existing->id)
                            ->update([
                                'Kode_Karyawan' => $approverKode,
                                'Division_Id' => $divisionId,
                                'level_Id' => $levelId,
                            ]);
                    } else {
                        // Insert new assignment
                        DB::table('N_HRIS_Approval_Flow_Shift')->insert([
                            'Kode_Karyawan' => $approverKode,
                            'Kode_Karyawan_Requester' => $memberKode,
                            'Division_Id' => $divisionId,
                            'level_Id' => $levelId,
                            'order_flow' => 1,
                            'Status' => null,
                        ]);
                    }

                    // Log detail
                    $details[] = [
                        'No_Transaksi' => $noTransaksi,
                        'Kode_Karyawan_Requester' => $memberKode,
                        'Kode_Approver_Lama' => $oldApprover,
                        'Kode_Approver_Baru' => $approverKode,
                        'Action' => $action,
                        'Status' => null,
                        'Created_At' => now(),
                    ];
                    $successCount++;
                } catch (\Exception $e) {
                    $details[] = [
                        'No_Transaksi' => $noTransaksi,
                        'Kode_Karyawan_Requester' => $memberKode,
                        'Kode_Approver_Lama' => $oldApprover,
                        'Kode_Approver_Baru' => $approverKode,
                        'Action' => $action,
                        'Status' => 'Y',
                        'Error_Message' => $e->getMessage(),
                        'Created_At' => now(),
                    ];
                    $errorCount++;
                }
            }

            // Insert all details
            DB::table('N_HRIS_Transaksi_Approver_Shift_Detail')->insert($details);

            DB::commit();

            return [
                'success' => true,
                'no_transaksi' => $noTransaksi,
                'total_success' => $successCount,
                'total_error' => $errorCount,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Transfer all members from one approver to another
     */
    public function transferApprover(string $oldApproverKode, string $newApproverKode, ?string $catatan = null): array
    {
        $userId = Auth::id() ?? 'system';
        $noTransaksi = $this->generateNoTransaksi('TRF');

        // Get affected members
        $affectedMembers = DB::table('N_HRIS_Approval_Flow_Shift')
            ->where('Kode_Karyawan', $oldApproverKode)
            ->whereNull('Status')
            ->get();

        if ($affectedMembers->isEmpty()) {
            throw new \Exception('Tidak ada anggota yang ditemukan untuk approver ini.');
        }

        // VALIDATION: Check for pending requests (batch, 1 query untuk semua member)
        $approverByMember = [];
        foreach ($affectedMembers as $member) {
            $approverByMember[$member->Kode_Karyawan_Requester] = $oldApproverKode;
        }

        $pendingMembers = $this->membersWithPendingRequests($approverByMember);
        if (! empty($pendingMembers)) {
            $pendingErrors = DB::table('Karyawan')->whereIn('Kode_Karyawan', $pendingMembers)->pluck('Nama')->all();
            $names = implode(', ', array_unique($pendingErrors));
            throw new \Exception("Transfer dibatalkan: Karyawan berikut masih memiliki pengajuan shift pending: $names.");
        }

        DB::beginTransaction();
        try {
            // Create transaction header
            DB::table('N_HRIS_Transaksi_Approver_Shift')->insert([
                'No_Transaksi' => $noTransaksi,
                'Jenis_Transaksi' => 'TRANSFER',
                'Kode_Approver_Lama' => $oldApproverKode,
                'Kode_Approver_Baru' => $newApproverKode,
                'Total_Affected' => $affectedMembers->count(),
                'Catatan' => $catatan,
                'Created_At' => now(),
                'Created_By' => $userId,
            ]);

            // Update all assignments
            DB::table('N_HRIS_Approval_Flow_Shift')
                ->where('Kode_Karyawan', $oldApproverKode)
                ->whereNull('Status')
                ->update(['Kode_Karyawan' => $newApproverKode]);

            // Log details
            $details = [];
            foreach ($affectedMembers as $member) {
                $details[] = [
                    'No_Transaksi' => $noTransaksi,
                    'Kode_Karyawan_Requester' => $member->Kode_Karyawan_Requester,
                    'Kode_Approver_Lama' => $oldApproverKode,
                    'Kode_Approver_Baru' => $newApproverKode,
                    'Action' => 'UPDATE',
                    'Status' => null,
                    'Created_At' => now(),
                ];
            }
            DB::table('N_HRIS_Transaksi_Approver_Shift_Detail')->insert($details);

            DB::commit();

            return [
                'success' => true,
                'no_transaksi' => $noTransaksi,
                'total_transferred' => $affectedMembers->count(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Remove assignment (soft delete by setting Status = 'Y')
     */
    public function removeAssignment(int $id): bool
    {
        $userId = Auth::id() ?? 'system';
        $kodeKaryawan =
        $noTransaksi = $this->generateNoTransaksi('DEL');

        $existing = DB::table('N_HRIS_Approval_Flow_Shift')
            ->where('id', $id)
            ->whereNull('Status')
            ->first();

        if (! $existing) {
            throw new \Exception('Assignment tidak ditemukan.');
        }

        if ($this->hasPendingRequests($existing->Kode_Karyawan_Requester, $existing->Kode_Karyawan)) {
            $memberName = DB::table('Karyawan')->where('Kode_Karyawan', $existing->Kode_Karyawan_Requester)->value('Nama');
            throw new \Exception('Karyawan '.$memberName.' masih memiliki pengajuan shift pending.');
        }

        DB::beginTransaction();
        try {
            // Log transaction
            DB::table('N_HRIS_Transaksi_Approver_Shift')->insert([
                'No_Transaksi' => $noTransaksi,
                'Jenis_Transaksi' => 'DELETE',
                'Kode_Approver_Lama' => $existing->Kode_Karyawan,
                'Total_Affected' => 1,
                'Created_At' => now(),
                'Created_By' => $userId,
            ]);

            DB::table('N_HRIS_Transaksi_Approver_Shift_Detail')->insert([
                'No_Transaksi' => $noTransaksi,
                'Kode_Karyawan_Requester' => $existing->Kode_Karyawan_Requester,
                'Kode_Approver_Lama' => $existing->Kode_Karyawan,
                'Kode_Approver_Baru' => '',
                'Action' => 'DELETE',
                'Status' => null,
                'Created_At' => now(),
            ]);

            // Soft delete
            DB::table('N_HRIS_Approval_Flow_Shift')
                ->where('id', $id)
                ->update(['Status' => 'Y']);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Get transaction history
     */
    public function getTransactionHistory(array $params = []): array
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;
        $offset = ($page - 1) * $limit;

        $query = DB::table('N_HRIS_Transaksi_Approver_Shift as t')
            ->leftJoin('Karyawan as old_approver', 'old_approver.Kode_Karyawan', '=', 't.Kode_Approver_Lama')
            ->leftJoin('Karyawan as new_approver', 'new_approver.Kode_Karyawan', '=', 't.Kode_Approver_Baru')
            ->select(
                't.*',
                'old_approver.Nama as old_approver_nama',
                'new_approver.Nama as new_approver_nama'
            )
            ->orderBy('t.Created_At', 'desc');

        $total = (clone $query)->count();

        $items = $query->offset($offset)->limit($limit)->get();

        return [
            'data' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ];
    }

    /**
     * Get filter options
     */
    public function getFilterOptions(): array
    {
        $divisi = DB::table('View_Divisi_Sub_Divisi')
            ->select('ID_DIVISI as value', 'nama_divisi as label')
            ->distinct()
            ->orderBy('nama_divisi')
            ->get();

        $jabatan = DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')
            ->select('Id_Jabatan as value', 'nama_jabatan as label')
            ->distinct()
            ->orderBy('nama_jabatan')
            ->get();

        $approvers = DB::table('N_HRIS_Approval_Flow_Shift as afs')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'afs.Kode_Karyawan')
            ->whereNull('afs.Status')
            ->select('k.Kode_Karyawan as value', 'k.Nama as label')
            ->distinct()
            ->orderBy('k.Nama')
            ->get();

        return [
            'divisi' => $divisi,
            'jabatan' => $jabatan,
            'approvers' => $approvers,
        ];
    }

    /**
     * Generate transaction number
     */
    private function generateNoTransaksi(string $prefix): string
    {
        // Counter atomik per-prefix (sargable, anti-duplikat). Menggantikan LIKE +
        // orderBy scan tanpa lock (4 alur konkuren ASN/TRF/DEL/IMP → rawan duplikat).
        $fullPrefix = sprintf('%s%s-%s-', $prefix, date('m'), date('y'));

        return app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
            $fullPrefix,
            function () use ($fullPrefix) {
                $last = DB::table('N_HRIS_Transaksi_Approver_Shift')
                    ->where('No_Transaksi', 'like', $fullPrefix.'%')
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
     * Check if member has pending requests with specific approver
     */
    /**
     * Versi batch hasPendingRequests(): terima peta requester => approver saat
     * ini, kembalikan daftar requester yang masih punya pengajuan shift pending
     * pada approver tersebut. Satu query untuk semua member — menggantikan
     * 2 query per member di loop validasi bulkAssign()/transferApprover().
     */
    private function membersWithPendingRequests(array $approverByMember): array
    {
        $approverByMember = array_filter($approverByMember);
        if (empty($approverByMember)) {
            return [];
        }

        $rows = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Shift_Detail as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('N_HRIS_Transaksi_Shift as t', 'h.No_Transaksi', '=', 't.No_Transaksi')
            ->whereIn('h.Kode_Karyawan', array_keys($approverByMember))
            ->whereIn('ar.Approver_Karyawan_Kode', array_values(array_unique($approverByMember)))
            ->whereNull('ar.Flag_Approval') // Pending approval
            ->whereNull('t.Flag_Selesai')
            ->whereNull('t.Status') // Not cancelled
            ->select('h.Kode_Karyawan', 'ar.Approver_Karyawan_Kode')
            ->distinct()
            ->get();

        // whereIn di atas mencocokkan silang; pastikan pasangan member-approver
        // benar-benar sesuai peta sebelum dinyatakan pending.
        $pending = [];
        foreach ($rows as $row) {
            if (($approverByMember[$row->Kode_Karyawan] ?? null) === $row->Approver_Karyawan_Kode) {
                $pending[] = $row->Kode_Karyawan;
            }
        }

        return array_values(array_unique($pending));
    }

    private function hasPendingRequests(string $memberKode, ?string $approverKode): bool
    {
        if (! $approverKode) {
            return false;
        }

        return DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Transaksi_Shift_Detail as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('N_HRIS_Transaksi_Shift as t', 'h.No_Transaksi', '=', 't.No_Transaksi')
            ->where('ar.Approver_Karyawan_Kode', $approverKode)
            ->whereNull('ar.Flag_Approval') // Pending approval
            ->whereNull('t.Flag_Selesai')
            ->where('h.Kode_Karyawan', $memberKode)
            ->whereNull('t.Status') // Not cancelled
            ->exists();
    }
}
