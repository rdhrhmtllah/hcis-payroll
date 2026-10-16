<?php

namespace App\Services\SYSTEM\Governance\Approval\Shared;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service to resolve approvers from N_HRIS_Jenis_Approval configuration
 *
 * Supports 5 source types:
 * - TABLE: Resolve from a table like HRIS_Approval_Flow (serial approval)
 * - KPI_TABLE: Resolve from legacy KPI flow tables (Kode_Karyawan_Approver / Id_Approval_Flow)
 * - GROUP_TABLE: Resolve group for parallel approval
 * - DIRECT_EMPLOYEE: Direct employee kode
 * - ROLE: Resolve a functional role as a single approval pool
 */
class ApproverResolverService
{
    /**
     * Hasil Schema::hasTable(), di-memo selama proses berjalan.
     *
     * STATIC, bukan properti instance: service ini tidak terdaftar sebagai
     * singleton, jadi setiap app(ApprovalFlowService::class) membuat resolver
     * baru dan cache tingkat-instance tidak akan pernah kena.
     *
     * Alasan memo ini penting: di SQL Server, Schema::hasTable() dikompilasi
     * menjadi query yang mengagregasi ukuran SELURUH tabel di database
     * (sum(total_pages)), sekitar 175 ms sekali panggil di DB dev. Dipanggil
     * satu kali per requester di dalam perulangan — misalnya grid tim KPI
     * dengan 51 anggota — biayanya mencapai 7,4 detik hanya untuk menanyakan
     * hal yang jawabannya selalu sama.
     *
     * Aman di-cache selama proses: tabel tidak muncul atau hilang di
     * tengah request, dan worker antrean pun tidak mengubah skema.
     *
     * @var array<string, bool>
     */
    private static array $tableExistsCache = [];

    /**
     * Schema::hasTable() yang hanya menyentuh database sekali per nama tabel.
     */
    private static function tableExistsCached(?string $table): bool
    {
        if (! $table) {
            return false;
        }

        if (! array_key_exists($table, self::$tableExistsCache)) {
            self::$tableExistsCache[$table] = Schema::hasTable($table);
        }

        return self::$tableExistsCache[$table];
    }

    /**
     * Kosongkan memo. Hanya untuk test yang membuat/menghapus tabel.
     */
    public static function lupakanCacheTabel(): void
    {
        self::$tableExistsCache = [];
    }

    /**
     * Resolve approvers based on jenis approval configuration
     *
     * @param  int  $idJenisApproval  ID dari N_HRIS_Jenis_Approval
     * @param  string  $sourceType  TABLE, GROUP_TABLE, DIRECT_EMPLOYEE, ROLE
     * @param  string|null  $sourceReference  Nama table atau kode karyawan
     * @param  string  $kodeRequester  Kode karyawan yang request
     * @param  int|null  $orderApproverLimit  Max number of approval levels to retrieve (e.g., limit 3 = get order_flow 1,2,3)
     * @return Collection Collection of resolved approvers
     */
    public function resolve(
        int $idJenisApproval,
        string $sourceType,
        ?string $sourceReference,
        string $kodeRequester,
        ?int $orderApproverLimit = null
    ): Collection {
        return match (strtoupper(trim($sourceType))) {
            'TABLE' => $this->resolveFromTable($idJenisApproval, $sourceReference, $kodeRequester, $orderApproverLimit),
            'KPI_TABLE' => $this->resolveFromKpiTable($idJenisApproval, $sourceReference, $kodeRequester, $orderApproverLimit),
            'GROUP_TABLE' => $this->resolveFromGroupTable($idJenisApproval, $sourceReference),
            'DIRECT_EMPLOYEE' => $this->resolveDirectEmployee($idJenisApproval, $sourceReference),
            'ROLE' => $this->resolveFromRole($idJenisApproval, $sourceReference),
            default => collect(),
        };
    }

    /**
     * resolve() untuk BANYAK requester sekaligus.
     *
     * Hasilnya identik dengan memanggil resolve() satu per satu, tapi jumlah
     * query tidak lagi tumbuh mengikuti jumlah requester. Dari lima source
     * type, hanya TABLE dan KPI_TABLE yang membaca kode requester; tiga
     * sisanya menghasilkan approver yang sama untuk semua orang, jadi cukup
     * diselesaikan sekali lalu dibagikan.
     *
     * @param  array<int, string>  $kodeRequesters
     * @return array<string, Collection> kode requester => approver miliknya
     */
    public function resolveBatch(
        int $idJenisApproval,
        string $sourceType,
        ?string $sourceReference,
        array $kodeRequesters,
        ?int $orderApproverLimit = null
    ): array {
        $kodeRequesters = array_values(array_unique(array_filter($kodeRequesters)));

        if (empty($kodeRequesters)) {
            return [];
        }

        $tipe = strtoupper(trim($sourceType));

        // Tidak bergantung requester: selesaikan sekali, bagikan ke semua.
        if (! in_array($tipe, ['TABLE', 'KPI_TABLE'], true)) {
            $bersama = $this->resolve($idJenisApproval, $sourceType, $sourceReference, $kodeRequesters[0], $orderApproverLimit);

            return array_fill_keys($kodeRequesters, $bersama);
        }

        $kosong = array_fill_keys($kodeRequesters, collect());

        if (! self::tableExistsCached($sourceReference)) {
            return $kosong;
        }

        // Nama kolom approver berbeda antara tabel HRIS dan tabel KPI lama;
        // sisa bentuk barisnya sama persis (lihat resolveFromTable /
        // resolveFromKpiTable, yang tetap dipertahankan untuk pemanggil tunggal).
        $kolomApprover = $tipe === 'KPI_TABLE' ? 'Kode_Karyawan_Approver' : 'Kode_Karyawan';
        $kolomFlowId = $tipe === 'KPI_TABLE' ? 'Id_Approval_Flow' : 'id';

        $query = DB::table($sourceReference.' as source')
            ->join('Karyawan as k', 'source.'.$kolomApprover, '=', 'k.Kode_Karyawan')
            ->whereIn('source.Kode_Karyawan_Requester', $kodeRequesters)
            ->whereNull('source.Status')
            ->where('k.Aktif', 'Y');

        if ($orderApproverLimit !== null && $orderApproverLimit > 0) {
            $query->where('source.order_flow', '<=', $orderApproverLimit);
        }

        $baris = $query
            ->orderBy('source.order_flow', 'asc')
            ->select(
                'source.'.$kolomFlowId.' as flow_id',
                'source.'.$kolomApprover.' as Kode_Karyawan',
                'source.Kode_Karyawan_Requester as requester',
                'source.order_flow',
            )
            ->get();

        foreach ($baris->groupBy('requester') as $requester => $miliknya) {
            $kosong[$requester] = $miliknya->map(fn ($row) => (object) [
                'type' => 'SERIAL',
                'id_jenis_approval' => $idJenisApproval,
                'group_id' => null,
                'kode_karyawan' => $row->Kode_Karyawan,
                'flow_id' => $row->flow_id,
                'order_flow' => $row->order_flow,
            ])->values();
        }

        return $kosong;
    }

    /**
     * Resolve from legacy KPI flow table.
     *
     * Identik konsep dengan resolveFromTable(), tapi memakai nama kolom asli
     * tabel KPI (tanpa view alias):
     *   - Id_Approval_Flow        (PK / flow_id)
     *   - Kode_Karyawan_Approver  (approver)
     *   - Kode_Karyawan_Requester (requester)
     *   - order_flow, Status
     *
     * @param  string|null  $tableName  'KPI_Approval_Flow_Parameter' | 'KPI_Approval_Flow_Performance'
     * @param  int|null  $orderApproverLimit  Hanya ambil order_flow <= nilai ini
     */
    protected function resolveFromKpiTable(int $idJenisApproval, ?string $tableName, string $kodeRequester, ?int $orderApproverLimit = null): Collection
    {
        if (! self::tableExistsCached($tableName)) {
            return collect();
        }

        $query = DB::table($tableName.' as source')
            ->join('Karyawan as k', 'source.Kode_Karyawan_Approver', '=', 'k.Kode_Karyawan')
            ->where('source.Kode_Karyawan_Requester', $kodeRequester)
            ->whereNull('source.Status')
            ->where('k.Aktif', 'Y');

        if ($orderApproverLimit !== null && $orderApproverLimit > 0) {
            $query->where('source.order_flow', '<=', $orderApproverLimit);
        }

        return $query
            ->orderBy('source.order_flow', 'asc')
            ->select('source.Id_Approval_Flow as flow_id', 'source.Kode_Karyawan_Approver as Kode_Karyawan', 'source.order_flow')
            ->get()
            ->map(fn ($row) => (object) [
                'type' => 'SERIAL',
                'id_jenis_approval' => $idJenisApproval,
                'group_id' => null,
                'kode_karyawan' => $row->Kode_Karyawan,
                'flow_id' => $row->flow_id,
                'order_flow' => $row->order_flow,
            ]);
    }

    /**
     * Resolve from legacy flow table (e.g., HRIS_Approval_Flow)
     *
     * This handles hierarchical/serial approval where each approver
     * is a specific employee in sequence.
     *
     * @param  string  $tableName  Table name (e.g., 'HRIS_Approval_Flow')
     * @param  int|null  $orderApproverLimit  Only get approvers with order_flow <= this value
     */
    protected function resolveFromTable(int $idJenisApproval, ?string $tableName, string $kodeRequester, ?int $orderApproverLimit = null): Collection
    {
        if (! self::tableExistsCached($tableName)) {
            return collect();
        }

        $query = DB::table($tableName.' as source')
            ->join('Karyawan as k', 'source.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->where('source.Kode_Karyawan_Requester', $kodeRequester)
            ->whereNull('source.Status')
            ->where('k.Aktif', 'Y');

        // Apply order flow limit if specified
        if ($orderApproverLimit !== null && $orderApproverLimit > 0) {
            $query->where('source.order_flow', '<=', $orderApproverLimit);
        }

        return $query
            ->orderBy('source.order_flow', 'asc')
            ->select('source.id as flow_id', 'source.Kode_Karyawan', 'source.order_flow')
            ->get()
            ->map(fn ($row) => (object) [
                'type' => 'SERIAL',
                'id_jenis_approval' => $idJenisApproval,
                'group_id' => null,
                'kode_karyawan' => $row->Kode_Karyawan,
                'flow_id' => $row->flow_id,
                'order_flow' => $row->order_flow,
            ]);
    }

    /**
     * Resolve from group table (parallel approval)
     *
     * This handles group-based approval where any member of the group
     * can approve (e.g., HC Ops team).
     *
     * @param  string|null  $tableName  Table name for group reference
     */
    protected function resolveFromGroupTable(int $idJenisApproval, ?string $tableName): Collection
    {
        // Default group ID for HC Ops
        $groupId = 100;

        // Try to get group from table if provided
        if (self::tableExistsCached($tableName)) {
            $group = DB::table($tableName)->where('Flag_Aktif', 'Y')->first();
            if ($group) {
                $groupId = $group->Group_Id ?? $group->id ?? 100;
            }
        }

        return collect([
            (object) [
                'type' => 'GROUP',
                'id_jenis_approval' => $idJenisApproval,
                'group_id' => $groupId,
                'kode_karyawan' => null,
                'flow_id' => null,
            ],
        ]);
    }

    /**
     * Resolve direct employee
     *
     * This handles cases where a specific employee is designated as approver.
     *
     * @param  string|null  $kodeKaryawan  Direct employee code
     */
    protected function resolveDirectEmployee(int $idJenisApproval, ?string $kodeKaryawan): Collection
    {
        if (! $kodeKaryawan) {
            return collect();
        }

        $karyawan = DB::table('Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Aktif', 'Y')
            ->first();

        if (! $karyawan) {
            return collect();
        }

        return collect([
            (object) [
                'type' => 'SERIAL',
                'id_jenis_approval' => $idJenisApproval,
                'group_id' => null,
                'kode_karyawan' => $karyawan->Kode_Karyawan,
                'flow_id' => null,
            ],
        ]);
    }

    /**
     * Resolve a functional role as one approval pool.
     *
     * Source_Reference must contain N_HRIS_Role_Functional.Role_Code.
     * The request row stores Id_Role in Group_Reference_Id; membership is
     * checked when listing/approving the request.
     */
    protected function resolveFromRole(int $idJenisApproval, ?string $roleCode): Collection
    {
        $roleCode = trim((string) $roleCode);
        if ($roleCode === '') {
            return collect();
        }

        $role = DB::table('N_HRIS_Role_Functional')
            ->where('Role_Code', $roleCode)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $role) {
            return collect();
        }

        return collect([
            (object) [
                'type' => 'GROUP',
                'id_jenis_approval' => $idJenisApproval,
                'group_id' => $role->Id_Role,
                'kode_karyawan' => null,
                'flow_id' => null,
                'source_type' => 'ROLE',
                'source_reference' => $role->Role_Code,
            ],
        ]);
    }
}
