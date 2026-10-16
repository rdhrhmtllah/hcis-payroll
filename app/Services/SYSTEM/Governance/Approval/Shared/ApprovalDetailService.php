<?php

namespace App\Services\SYSTEM\Governance\Approval\Shared;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ApprovalDetailService
 *
 * Centralized service for managing HRIS_Approval_Request_Detail
 * Generic architecture: Uses Source_Row_Key + Source_View pattern
 *
 * One approval request can have many detail items.
 * Each detail item represents ONE approval decision granule.
 *
 * Example:
 * - Request: SHIFT transaction with 5 dates
 * - Details: 5 rows, one per date, each can be approved/rejected independently
 */
class ApprovalDetailService
{
    const TABLE = 'HRIS_Approval_Request_Detail';

    /**
     * Create approval details from source view data
     *
     * Automatically pulls items from source view and creates tracking records
     *
     * @param  string  $sourceView  (e.g., 'N_HRIS_VW_SRC_SHIFT')
     * @return int Number of details created
     */
    public function createFromSourceView(
        int $idApprovalRequest,
        string $noTransaksi,
        string $sourceView,
        int $idJenisApproval
    ): int {
        try {
            // Query source view to get all items for this transaction
            $sourceItems = DB::table($sourceView)
                ->where('No_Transaksi', $noTransaksi)
                ->select('Source_Row_Key', 'Kode_Karyawan', 'Tanggal_Item', 'Status_Saat_Ini', 'Json_Context')
                ->get();

            if ($sourceItems->isEmpty()) {
                Log::channel('ApprovalDetailService')->warning('No items found in source view', [
                    'source_view' => $sourceView,
                    'no_transaksi' => $noTransaksi,
                ]);

                return 0;
            }

            $now = Carbon::now();
            $insertData = [];

            foreach ($sourceItems as $item) {
                $insertData[] = [
                    'Id_Approval_Request' => $idApprovalRequest,
                    'Id_Jenis_Approval' => $idJenisApproval,
                    'Source_Row_Key' => $item->Source_Row_Key,
                    'Source_View' => $sourceView,
                    'Kode_Karyawan' => $item->Kode_Karyawan,
                    'Tanggal_Item' => $item->Tanggal_Item,
                    'Status_Snapshot' => $item->Status_Saat_Ini,
                    'Json_Context' => $item->Json_Context,
                    'Flag_Approval' => null,  // Not approved yet
                    'Processed_By_Kode' => null,
                    'Processed_At' => null,
                    'Request_Order_Flow' => null,
                    'Notes' => null,
                    'Created_At' => $now,
                ];
            }

            DB::table(self::TABLE)->insert($insertData);

            Log::channel('ApprovalDetailService')->info('Created approval details from source', [
                'id_approval_request' => $idApprovalRequest,
                'no_transaksi' => $noTransaksi,
                'source_view' => $sourceView,
                'count' => count($insertData),
            ]);

            return count($insertData);
        } catch (\Exception $e) {
            Log::channel('ApprovalDetailService')->error('Error creating details from source view', [
                'error' => $e->getMessage(),
                'source_view' => $sourceView,
            ]);
            throw $e;
        }
    }

    /**
     * Update approval decision on a detail item
     *
     * @param  string  $flagApproval  (Y or T)
     */
    public function updateApprovalDecision(
        int $detailId,
        string $flagApproval,
        string $processedByKode,
        int $requestOrderFlow,
        ?string $notes = null
    ): bool {
        try {
            // Get detail record before update for logging
            $detailBefore = DB::table(self::TABLE)->find($detailId);

            if (! $detailBefore) {
                Log::channel('ApprovalDetailService')->warning('Detail record not found for update', [
                    'detail_id' => $detailId,
                ]);

                return false;
            }

            $updated = DB::table(self::TABLE)
                ->where('Id', $detailId)
                ->update([
                    'Flag_Approval' => $flagApproval,
                    'Processed_By_Kode' => $processedByKode,
                    'Processed_At' => Carbon::now(),
                    'Request_Order_Flow' => $requestOrderFlow,
                    'Notes' => $notes,
                    'Updated_At' => Carbon::now(),
                ]);

            if ($updated > 0) {
                Log::channel('ApprovalDetailService')->info('Updated approval decision successfully', [
                    'detail_id' => $detailId,
                    'id_approval_request' => $detailBefore->Id_Approval_Request,
                    'source_row_key' => $detailBefore->Source_Row_Key,
                    'flag_approval_from' => $detailBefore->Flag_Approval,
                    'flag_approval_to' => $flagApproval,
                    'processed_by' => $processedByKode,
                    'request_order_flow' => $requestOrderFlow,
                    'notes' => $notes ? 'SET' : 'NULL',
                ]);
            } else {
                Log::channel('ApprovalDetailService')->warning('No rows updated for approval decision', [
                    'detail_id' => $detailId,
                    'id_approval_request' => $detailBefore->Id_Approval_Request,
                ]);
            }

            return $updated > 0;
        } catch (\Exception $e) {
            Log::channel('ApprovalDetailService')->error('Exception updating approval decision', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'detail_id' => $detailId,
                'flag_approval' => $flagApproval,
                'processed_by' => $processedByKode,
            ]);
            throw $e;
        }
    }

    /**
     * Bulk update approval decisions
     *
     * @param  array  $updates  [detail_id => {flag, notes}, ...]
     * @return int Number of updates
     */
    public function bulkUpdateApprovalDecisions(
        array $updates,
        string $processedByKode,
        int $requestOrderFlow
    ): int {
        $count = 0;
        $now = Carbon::now();

        foreach ($updates as $detailId => $decision) {
            DB::table(self::TABLE)
                ->where('Id', $detailId)
                ->update([
                    'Flag_Approval' => $decision['flag'],
                    'Processed_By_Kode' => $processedByKode,
                    'Processed_At' => $now,
                    'Request_Order_Flow' => $requestOrderFlow,
                    'Notes' => $decision['notes'] ?? null,
                    'Updated_At' => $now,
                ]);
            $count++;
        }

        Log::channel('ApprovalDetailService')->info('Bulk updated approval decisions', [
            'count' => $count,
            'by' => $processedByKode,
        ]);

        return $count;
    }

    /**
     * Get all details for an approval request
     *
     * @return \Illuminate\Support\Collection
     */
    public function getByApprovalRequest(int $idApprovalRequest)
    {
        return DB::table(self::TABLE)
            ->where('Id_Approval_Request', $idApprovalRequest)
            ->orderBy('Created_At')
            ->get();
    }

    /**
     * Get pending details (not yet approved/rejected) for an approval request
     *
     * @return \Illuminate\Support\Collection
     */
    public function getPendingByApprovalRequest(int $idApprovalRequest)
    {
        return DB::table(self::TABLE)
            ->where('Id_Approval_Request', $idApprovalRequest)
            ->whereNull('Flag_Approval')
            ->orderBy('Tanggal_Item')
            ->get();
    }

    /**
     * Get details processed by specific approver for a transaction
     * Useful for tracking what approver 1 decided vs approver 2
     *
     * @return \Illuminate\Support\Collection
     */
    public function getProcessedByApprover(string $noTransaksi, string $processedByKode)
    {
        return DB::table(self::TABLE)
            ->where('No_Transaksi', $noTransaksi)
            ->where('Processed_By_Kode', $processedByKode)
            ->orderBy('Tanggal_Item')
            ->get();
    }

    /**
     * Check if all details for request are decided
     */
    public function isAllDecided(int $idApprovalRequest): bool
    {
        $pending = DB::table(self::TABLE)
            ->where('Id_Approval_Request', $idApprovalRequest)
            ->whereNull('Flag_Approval')
            ->count();

        return $pending === 0;
    }

    /**
     * Get approval summary for a request
     *
     * @return array [approved_count, rejected_count, total_count]
     */
    public function getApprovalSummary(int $idApprovalRequest): array
    {
        $stats = DB::table(self::TABLE)
            ->where('Id_Approval_Request', $idApprovalRequest)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN Flag_Approval = 'Y' THEN 1 ELSE 0 END) as approved"),
                DB::raw("SUM(CASE WHEN Flag_Approval = 'T' THEN 1 ELSE 0 END) as rejected")
            )
            ->first();

        return [
            'total' => $stats->total ?? 0,
            'approved' => $stats->approved ?? 0,
            'rejected' => $stats->rejected ?? 0,
            'pending' => ($stats->total ?? 0) - (($stats->approved ?? 0) + ($stats->rejected ?? 0)),
        ];
    }

    /**
     * Get approval history per approver
     */
    /**
     * Baris tracking approver untuk BANYAK transaksi sekaligus, tanpa hitungan total.
     *
     * ── Kenapa metode ini ada ────────────────────────────────────────────────
     *
     * getApproverHistory() menjalankan DUA kueri: satu count() untuk paginasi
     * dan satu get() untuk datanya. Halaman riwayat shift memanggilnya sekali
     * PER TRANSAKSI yang ditampilkan, dan membuang nilai `total`-nya — jadi
     * separuh kueri itu dikerjakan untuk jawaban yang tak pernah dibaca.
     *
     * Terukur di production 9 September 2026: `shift-approval/history`
     * menjalankan rata-rata 52 kueri per permintaan dengan 11.394 ms waktu DB —
     * 100% waktu permintaannya habis di basis data, dan tersebar merata (kueri
     * terlambat hanya 14% dari total, jadi tak ada satu kueri berat yang bisa
     * disalahkan). `count(*)` atas N_HRIS_VW_Approval_Tracking_Generic sendiri
     * tercatat 32 kali dengan puncak 2,5 detik.
     *
     * Di sini satu kueri melayani seluruh transaksi pada satu halaman.
     *
     * @param  array<int,string>|string  $noTransaksi
     * @return \Illuminate\Support\Collection<int,object>
     */
    public function itemsForApprover(
        string $processedByKode,
        array|string $noTransaksi,
        ?string $jenis = null,
    ): \Illuminate\Support\Collection {
        $daftar = array_values(array_unique(array_filter((array) $noTransaksi)));

        if ($daftar === []) {
            return collect();
        }

        $query = DB::table('N_HRIS_VW_Approval_Tracking_Generic')
            ->where('Processed_By_Kode', $processedByKode)
            ->whereIn('No_Transaksi', $daftar);

        if ($jenis !== null && $jenis !== '') {
            $query->where('Jenis', $jenis);
        }

        return $query->orderBy('Processed_At', 'desc')->get();
    }

    public function getApproverHistory(
        string $processedByKode,
        array $filters = [],
        int $page = 1,
        int $limit = 10
    ): array {
        $query = DB::table('N_HRIS_VW_Approval_Tracking_Generic')
            ->where('Processed_By_Kode', $processedByKode);

        // Apply filters
        if (! empty($filters['start_date'])) {
            $query->whereDate('Processed_At', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('Processed_At', '<=', $filters['end_date']);
        }

        if (! empty($filters['jenis'])) {
            $query->where('Jenis', $filters['jenis']);
        }

        if (! empty($filters['no_transaksi'])) {
            $query->where('No_Transaksi', $filters['no_transaksi']);
        }

        if (! empty($filters['flag_approval'])) {
            $query->where('Item_Approval_Status', $filters['flag_approval']);
        }

        $total = $query->count();

        $data = $query
            ->orderBy('Processed_At', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'data' => $data,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ];
    }

    /**
     * Delete details by approval request
     * (for rollback/cancel scenarios)
     */
    public function deleteByApprovalRequest(int $idApprovalRequest): int
    {
        $deleted = DB::table(self::TABLE)
            ->where('Id_Approval_Request', $idApprovalRequest)
            ->delete();

        Log::channel('ApprovalDetailService')->info('Deleted approval details', [
            'id_approval_request' => $idApprovalRequest,
            'count' => $deleted,
        ]);

        return $deleted;
    }
}
