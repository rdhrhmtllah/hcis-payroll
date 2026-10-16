<?php

namespace App\Services\SYSTEM\Governance\Approval\Shared;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Scope\EmployeeTargetScopeService;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationResourceRegistryService;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use App\Services\SYSTEM\MasterData\GrupFitur\KodeGrupFitur;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service halaman "Menu & Approval Matrix" (/admin/{grupCode}/menu-approval-matrix).
 *
 * Two-tier management:
 * - System admin  : CRUD N_HRIS_Jenis_Approval, N_HRIS_Approval_Config (+detail),
 *                   binding config -> grup fitur (Id_Grup_Fitur).
 * - Admin module  : melihat matrix config milik grup kelolaannya (read-only)
 *                   dan mengelola ASSIGNMENT approver (requester -> approver)
 *                   untuk step ber-Source_Type TABLE/KPI_TABLE.
 *
 * Keamanan edit source table: nama tabel TIDAK pernah diterima dari client.
 * Service me-resolve dari N_HRIS_Jenis_Approval.Source_Reference lalu validasi
 * whitelist config('approval_matrix.editable_source_tables') + cek kolom Schema.
 */
class ApprovalMatrixService
{
    public const PAGE_TYPE = 'menuApprovalMatrixPage';

    public const CONTEXT_CODE = 'admin';

    /**
     * Tab yang memang TIDAK BERLAKU pada sebuah grup fitur.
     *
     * Berbeda dari hak akses: ini bukan soal siapa boleh membukanya, melainkan
     * bahwa tabnya tidak punya arti di sana. Karena itu ia dihormati bahkan oleh
     * pintasan System Admin — pintasan itu menjawab "berhak melihat segalanya",
     * bukan "melihat hal yang tidak ada".
     *
     * Visitor tidak memakai keanggotaan: keanggotaan role Visitor diberikan
     * lewat change basis data yang tercatat, bukan lewat layar ini. Tab yang
     * tetap dirender hanya mengundang orang mengubahnya dari tempat yang tidak
     * meninggalkan jejak.
     */
    private const TAB_TAK_BERLAKU = [
        'VISITOR' => ['membership'],
    ];

    private array $sourceResolutionCache = [];

    private array $schemaColumnsCache = [];

    private array $tableExistsCache = [];

    public function __construct(
        private readonly EmployeeTargetScopeService $targetScopeService,
        private readonly AuthorizationResourceRegistryService $resourceRegistryService,
    ) {}

    // ========================================================================
    // CAPABILITIES & SCOPE
    // ========================================================================

    /**
     * Payload capability untuk frontend: visibilitas tab + hak assign per tab.
     * Granularitas di-encode pada Resource_Code (action layer page-wide).
     */
    public function buildMatrixCapabilities(User $user, int $grupId): array
    {
        $scope = AuthorizationScopeResolver::resolve($user);
        $this->assertCanManageGrup($user, $grupId);
        $isSystemAdmin = (bool) ($scope['isSystemAdmin'] ?? false);

        $check = function (string $resourceType, string $resourceCode) use ($isSystemAdmin, $user, $grupId): bool {
            $this->resourceRegistryService->assertRegistered(
                $user,
                self::PAGE_TYPE,
                $resourceType,
                $resourceCode,
                'build-matrix-capability',
            );

            return $isSystemAdmin || $this->resourceAllowedForGrup($user, $grupId, $resourceType, $resourceCode);
        };

        $takBerlaku = $this->tabTakBerlaku($grupId);

        return [
            'isSystemAdmin' => $isSystemAdmin,
            'activeGroupId' => $grupId,
            'manageableGroupIds' => array_values($scope['manageableGroupIds'] ?? []),
            'menu' => [
                'canView' => $check('tab', 'menu'),
                'canAssign' => $check('capability', 'MENU_ACCESS.ASSIGN'),
            ],
            'approvalMatrix' => [
                'canView' => $check('tab', 'approval-matrix'),
                'canAssign' => $check('capability', 'APPROVAL_MATRIX.ASSIGN'),
            ],
            'membership' => [
                'canView' => ! in_array('membership', $takBerlaku, true)
                    && $check('tab', 'membership'),
                'canAssign' => $check('capability', 'MEMBERSHIP.ASSIGN'),
                'canConfigure' => $isSystemAdmin
                    && $check('capability', 'MEMBERSHIP.CONFIGURE'),
            ],
            'targetScope' => $this->targetScopeService->metadata($user, $grupId),
        ];
    }

    /**
     * Tab yang tidak berlaku pada grup ini.
     *
     * @return list<string>
     */
    private function tabTakBerlaku(int $grupId): array
    {
        $kode = (string) DB::table('N_HRIS_Master_Grup_Fitur')
            ->where('Id_Grup_Fitur', $grupId)
            ->value('Kode_Grup_Fitur');

        return self::TAB_TAK_BERLAKU[KodeGrupFitur::kanonik($kode)] ?? [];
    }

    private function resourceAllowedForGrup(User $user, int $grupId, string $resourceType, string $resourceCode): bool
    {
        if (! $this->tableExistsCached('N_HRIS_Role_Resource_Access')) {
            return AuthorizationScopeResolver::canAccessPage($user, self::PAGE_TYPE);
        }

        $flags = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', function ($join) use ($grupId) {
                $join->on('rf.Id_Role', '=', 'ru.Id_Role')
                    ->where('rf.Flag_Aktif', 'Y')
                    ->where('rf.Id_Grup_Fitur', $grupId);
            })
            ->join('N_HRIS_Role_Resource_Access as rra', 'rra.Id_Role_Functional', '=', 'rf.Id_Role')
            ->where('ru.UserId_Web', $user->getKey())
            ->where('ru.Flag_Aktif', 'Y')
            ->where('rra.Context_Code', self::CONTEXT_CODE)
            ->where('rra.Jenis_page', self::PAGE_TYPE)
            ->where('rra.Resource_Type', $resourceType)
            ->where('rra.Resource_Code', $resourceCode)
            ->where('rra.Flag_Aktif', 'Y')
            ->pluck('rra.Flag_Diizinkan')
            ->map(fn ($flag) => strtoupper(trim((string) $flag)));

        return $flags->contains('Y');
    }

    public function isSystemAdmin(User $user): bool
    {
        return (bool) (AuthorizationScopeResolver::resolve($user)['isSystemAdmin'] ?? false);
    }

    /**
     * Id grup fitur yang boleh dikelola user. null = semua (system admin).
     */
    public function manageableGroupIds(User $user): ?array
    {
        $scope = AuthorizationScopeResolver::resolve($user);
        if (! empty($scope['isSystemAdmin'])) {
            return null;
        }

        return array_values(array_map('intval', $scope['manageableGroupIds'] ?? []));
    }

    public function assertCanManageGrup(User $user, int $grupId): void
    {
        $allowed = $this->manageableGroupIds($user);
        if ($allowed !== null && ! in_array($grupId, $allowed, true)) {
            abort(403, 'Anda tidak berwenang mengelola grup fitur ini.');
        }
    }

    public function assertSystemAdmin(User $user): void
    {
        if (! $this->isSystemAdmin($user)) {
            abort(403, 'Aksi ini hanya untuk System Admin.');
        }
    }

    /**
     * Opsi grup fitur untuk GroupPicker, di-scope per user.
     */
    public function getGrupOptions(User $user): Collection
    {
        $allowed = $this->manageableGroupIds($user);

        $query = DB::table('N_HRIS_Master_Grup_Fitur')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Sort_Order')
            ->orderBy('Kode_Grup_Fitur')
            ->select('Id_Grup_Fitur', 'Kode_Grup_Fitur', 'Nama_Grup_Fitur', 'Icon_Grup_Fitur');

        if ($allowed !== null) {
            $query->whereIn('Id_Grup_Fitur', empty($allowed) ? [-1] : $allowed);
        }

        return $query->get();
    }

    // ========================================================================
    // APPROVAL CONFIG — LISTING & MATRIX (module admin + system admin)
    // ========================================================================

    private function configHasGrupColumn(): bool
    {
        return $this->hasColumnCached('N_HRIS_Approval_Config', 'Id_Grup_Fitur');
    }

    public function getConfigs(User $user, ?int $grupId = null, ?string $search = null): array
    {
        abort_if($grupId === null || $grupId <= 0, 422, 'grup_id route aktif wajib diisi.');
        $this->assertCanManageGrup($user, $grupId);

        $hasGrupColumn = $this->configHasGrupColumn();
        $allowed = $this->manageableGroupIds($user);

        // Tanpa kolom binding, scoping per module tidak mungkin:
        // module admin tidak melihat apa pun, system admin melihat semua.
        if (! $hasGrupColumn && $allowed !== null) {
            return [
                'configs' => [],
                'hasGrupColumn' => false,
                'notice' => 'Kolom Id_Grup_Fitur belum tersedia di N_HRIS_Approval_Config. Hubungi System Admin (jalankan DDL menu_approval_matrix).',
            ];
        }

        $query = DB::table('N_HRIS_Approval_Config as c')
            ->leftJoin('N_HRIS_Approval_Config_Detail as d', function ($join) {
                $join->on('d.Id_Approval_Config', '=', 'c.Id')->where('d.Flag_Aktif', 'Y');
            })
            ->groupBy('c.Id', 'c.Kode_Config', 'c.Flag_Aktif')
            ->orderBy('c.Kode_Config');

        $select = ['c.Id', 'c.Kode_Config', 'c.Flag_Aktif', DB::raw('COUNT(d.Id) as total_step')];

        foreach (['Nama_Config', 'Deskripsi'] as $optionalColumn) {
            if ($this->hasColumnCached('N_HRIS_Approval_Config', $optionalColumn)) {
                $select[] = 'c.'.$optionalColumn;
                $query->groupBy('c.'.$optionalColumn);
            }
        }

        if ($hasGrupColumn) {
            $query
                ->leftJoin('N_HRIS_Master_Grup_Fitur as gf', 'gf.Id_Grup_Fitur', '=', 'c.Id_Grup_Fitur')
                ->groupBy('c.Id_Grup_Fitur', 'gf.Nama_Grup_Fitur', 'gf.Kode_Grup_Fitur');
            $select[] = 'c.Id_Grup_Fitur';
            $select[] = 'gf.Nama_Grup_Fitur';
            $select[] = 'gf.Kode_Grup_Fitur';

            if ($allowed !== null) {
                $query->whereIn('c.Id_Grup_Fitur', empty($allowed) ? [-1] : $allowed);
            } elseif ($grupId !== null) {
                $query->where('c.Id_Grup_Fitur', $grupId);
            }

            if ($allowed !== null && $grupId !== null) {
                $query->where('c.Id_Grup_Fitur', $grupId);
            }
        }

        if ($search !== null && trim($search) !== '') {
            $query->where('c.Kode_Config', 'like', '%'.trim($search).'%');
        }

        $configs = $query
            ->select($select)
            ->get()
            ->map(function ($row) use ($hasGrupColumn) {
                $row->unassigned = $hasGrupColumn ? $row->Id_Grup_Fitur === null : true;

                return $row;
            });

        $this->attachSourceTableUsage($user, $configs);
        $this->attachFlowDetails($configs);

        return [
            'configs' => $configs,
            'unboundConfigs' => $this->isSystemAdmin($user) && $hasGrupColumn
                ? $this->getUnboundConfigs($user, $search)
                : [],
            'hasGrupColumn' => $hasGrupColumn,
            'notice' => null,
        ];
    }

    private function getUnboundConfigs(User $user, ?string $search): Collection
    {
        $query = DB::table('N_HRIS_Approval_Config as c')
            ->leftJoin('N_HRIS_Approval_Config_Detail as d', function ($join) {
                $join->on('d.Id_Approval_Config', '=', 'c.Id')->where('d.Flag_Aktif', 'Y');
            })
            ->whereNull('c.Id_Grup_Fitur')
            ->groupBy('c.Id', 'c.Kode_Config', 'c.Flag_Aktif', 'c.Id_Grup_Fitur')
            ->orderBy('c.Kode_Config');

        $select = ['c.Id', 'c.Kode_Config', 'c.Flag_Aktif', 'c.Id_Grup_Fitur', DB::raw('COUNT(d.Id) as total_step')];
        foreach (['Nama_Config', 'Deskripsi'] as $column) {
            if ($this->hasColumnCached('N_HRIS_Approval_Config', $column)) {
                $select[] = 'c.'.$column;
                $query->groupBy('c.'.$column);
            }
        }
        if ($search !== null && trim($search) !== '') {
            $query->where('c.Kode_Config', 'like', '%'.trim($search).'%');
        }

        $configs = $query->select($select)->get()->each(fn ($config) => $config->unassigned = true);
        $this->attachSourceTableUsage($user, $configs);
        $this->attachFlowDetails($configs);

        return $configs;
    }

    /**
     * Flow memakai seluruh detail config sekaligus. Menyertakannya pada response
     * listing menghindari satu request matrix tambahan untuk setiap config.
     */
    private function attachFlowDetails(Collection $configs): void
    {
        $configIds = $configs->pluck('Id')->map(fn ($id) => (int) $id)->all();
        if (empty($configIds)) {
            return;
        }

        $detailsByConfig = DB::table('N_HRIS_Approval_Config_Detail as d')
            ->leftJoin('N_HRIS_Jenis_Approval as ja', 'd.Id_Jenis_Approval', '=', 'ja.Id_Jenis_Approval')
            ->whereIn('d.Id_Approval_Config', $configIds)
            ->where('d.Flag_Aktif', 'Y')
            ->orderBy('d.Id_Approval_Config')
            ->orderBy('d.Approval_Order')
            ->select('d.*', 'ja.Nama_Approval', 'ja.Source_Type', 'ja.Source_Reference', 'ja.Flag_Aktif as Jenis_Flag_Aktif')
            ->get()
            ->map(function ($detail) {
                $sourceMeta = $this->resolveSourceTableForDetail($detail);
                $detail->editable = $sourceMeta !== null;
                $detail->source_table_valid = $sourceMeta !== null;
                $detail->approver_mode = $this->describeApproverMode($detail);

                return $detail;
            })
            ->groupBy('Id_Approval_Config');

        $configs->each(function ($config) use ($detailsByConfig) {
            $config->flow_details = $detailsByConfig->get($config->Id, collect())->values()->all();
        });
    }

    /**
     * Tambahkan konteks source table ke daftar config agar admin dapat
     * membedakan alur config walaupun beberapa config memakai tabel yang sama.
     */
    private function attachSourceTableUsage(User $user, Collection $configs): void
    {
        $configIds = $configs->pluck('Id')->map(fn ($id) => (int) $id)->all();
        if (empty($configIds)) {
            return;
        }

        $editableTypes = array_map('strtoupper', (array) config('approval_matrix.editable_source_types', []));
        $baseSourceQuery = fn () => DB::table('N_HRIS_Approval_Config_Detail as d')
            ->join('N_HRIS_Jenis_Approval as ja', 'd.Id_Jenis_Approval', '=', 'ja.Id_Jenis_Approval')
            ->where('d.Flag_Aktif', 'Y')
            ->whereIn(DB::raw('UPPER(ja.Source_Type)'), $editableTypes)
            ->whereNotNull('ja.Source_Reference')
            ->where('ja.Source_Reference', '<>', '');

        // Cari source milik config yang tampil, lalu cek pemakaian global
        // hanya untuk source-source tersebut.
        $visibleSourceRows = $baseSourceQuery()
            ->whereIn('d.Id_Approval_Config', $configIds)
            ->select('d.Id_Approval_Config', 'ja.Source_Reference')
            ->distinct()
            ->get();

        $sourceReferences = $visibleSourceRows->pluck('Source_Reference')->unique()->values()->all();
        $sourceRows = empty($sourceReferences)
            ? collect()
            : $baseSourceQuery()
                ->whereIn('ja.Source_Reference', $sourceReferences)
                ->select('d.Id_Approval_Config', 'ja.Source_Reference')
                ->distinct()
                ->get();

        $sourcesByConfig = $visibleSourceRows
            ->groupBy('Id_Approval_Config')
            ->map(fn (Collection $rows) => $rows->pluck('Source_Reference')->unique()->values()->all());

        $configIdsBySource = $sourceRows
            ->groupBy('Source_Reference')
            ->map(fn (Collection $rows) => $rows->pluck('Id_Approval_Config')->map(fn ($id) => (int) $id)->unique()->values());

        $allReferencedConfigIds = $sourceRows->pluck('Id_Approval_Config')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $configCodeQuery = DB::table('N_HRIS_Approval_Config')->whereIn('Id', $allReferencedConfigIds);
        $allowed = $this->manageableGroupIds($user);
        if ($allowed !== null && $this->configHasGrupColumn()) {
            $configCodeQuery->whereIn('Id_Grup_Fitur', empty($allowed) ? [-1] : $allowed);
        }
        $configCodeById = $configCodeQuery->pluck('Kode_Config', 'Id');

        $configs->each(function ($config) use ($sourcesByConfig, $configIdsBySource, $configCodeById) {
            $sourceTables = $sourcesByConfig->get($config->Id, []);
            $sharedSources = collect($sourceTables)
                ->map(function ($table) use ($configIdsBySource, $configCodeById) {
                    $configIds = $configIdsBySource->get($table, collect());
                    if ($configIds->count() <= 1) {
                        return null;
                    }

                    $visibleConfigCodes = $configIds
                        ->map(fn ($id) => $configCodeById->get($id))
                        ->filter()
                        ->values()
                        ->all();

                    return [
                        'table' => $table,
                        'config_count' => $configIds->count(),
                        'config_codes' => $visibleConfigCodes,
                        'hidden_config_count' => $configIds->count() - count($visibleConfigCodes),
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $config->source_tables = $sourceTables;
            $config->shared_source_tables = $sharedSources;
            $config->uses_shared_source = ! empty($sharedSources);
        });
    }

    /**
     * Header + step matrix satu config, dengan flag editable per step.
     */
    public function getConfigMatrix(User $user, int $idConfig): array
    {
        $config = $this->findConfigForUser($user, $idConfig);

        $details = DB::table('N_HRIS_Approval_Config_Detail as d')
            ->leftJoin('N_HRIS_Jenis_Approval as ja', 'd.Id_Jenis_Approval', '=', 'ja.Id_Jenis_Approval')
            ->where('d.Id_Approval_Config', $idConfig)
            ->orderBy('d.Approval_Order')
            ->select('d.*', 'ja.Nama_Approval', 'ja.Source_Type', 'ja.Source_Reference', 'ja.Flag_Aktif as Jenis_Flag_Aktif')
            ->get()
            ->map(function ($detail) {
                $sourceMeta = $this->resolveSourceTableForDetail($detail);
                $detail->editable = $sourceMeta !== null;
                $detail->source_table_valid = $sourceMeta !== null;
                $detail->approver_mode = $this->describeApproverMode($detail);

                return $detail;
            });

        return [
            'config' => $config,
            'details' => $details,
        ];
    }

    /**
     * Tampilkan anggota aktif dari role approver pada satu step.
     */
    public function getRoleMembers(User $user, int $idConfigDetail): array
    {
        $detail = DB::table('N_HRIS_Approval_Config_Detail as d')
            ->join('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'd.Id_Jenis_Approval')
            ->where('d.Id', $idConfigDetail)
            ->where('d.Flag_Aktif', 'Y')
            ->select('d.Id_Approval_Config', 'd.Approval_Order', 'ja.Nama_Approval', 'ja.Source_Type', 'ja.Source_Reference')
            ->first();

        if (! $detail) {
            abort(404, 'Step approval tidak ditemukan.');
        }

        $this->findConfigForUser($user, (int) $detail->Id_Approval_Config);

        if (strtoupper(trim((string) $detail->Source_Type)) !== 'ROLE') {
            abort(422, 'Step ini bukan approval berbasis role.');
        }

        $role = DB::table('N_HRIS_Role_Functional')
            ->where('Role_Code', trim((string) $detail->Source_Reference))
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $role) {
            abort(404, 'Role approver tidak ditemukan atau sudah nonaktif.');
        }

        $members = DB::table('N_HRIS_Role_User as ru')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
            ->where('ru.Id_Role', $role->Id_Role)
            ->where('ru.Flag_Aktif', 'Y')
            ->where('k.Aktif', 'Y')
            ->orderBy('k.Nama')
            ->select(
                'k.UserID_Web as user_id',
                'k.Kode_Karyawan as kode',
                'k.Nama as nama',
                'k.UserId_Absen as nik',
                'ru.Assigned_At as assigned_at',
            )
            ->get();

        return [
            'role' => [
                'code' => $role->Role_Code,
                'name' => $role->Role_Name,
                'member_count' => $members->count(),
            ],
            'step' => [
                'name' => $detail->Nama_Approval,
                'order' => $detail->Approval_Order,
            ],
            'members' => $members,
        ];
    }

    private function describeApproverMode(object $detail): string
    {
        if (! empty($detail->Custom_Approver_Kode)) {
            return 'CUSTOM';
        }

        return strtoupper(trim((string) ($detail->Source_Type ?? ''))) ?: 'UNKNOWN';
    }

    /**
     * Ambil config + enforce scope grup user. System admin bebas;
     * module admin hanya config milik grup kelolaannya.
     */
    public function findConfigForUser(User $user, int $idConfig): object
    {
        $config = DB::table('N_HRIS_Approval_Config')->where('Id', $idConfig)->first();
        if (! $config) {
            abort(404, 'Approval config tidak ditemukan.');
        }

        $allowed = $this->manageableGroupIds($user);
        if ($allowed !== null) {
            $grupId = $this->configHasGrupColumn() ? $config->Id_Grup_Fitur : null;
            if ($grupId === null || ! in_array((int) $grupId, $allowed, true)) {
                abort(403, 'Anda tidak berwenang mengakses approval config ini.');
            }
        }

        return $config;
    }

    public function assertConfigBelongsToGrup(User $user, int $idConfig, int $grupId, bool $allowUnbound = false): object
    {
        $this->assertCanManageGrup($user, $grupId);
        $config = $this->findConfigForUser($user, $idConfig);
        $configGrupId = $this->configHasGrupColumn() ? $config->Id_Grup_Fitur : null;

        if ((int) $configGrupId !== $grupId && ! ($allowUnbound && $configGrupId === null && $this->isSystemAdmin($user))) {
            abort(404, 'Approval config tidak ditemukan pada grup aktif.');
        }

        return $config;
    }

    public function assertConfigDetailBelongsToGrup(User $user, int $idConfigDetail, int $grupId): object
    {
        $detail = DB::table('N_HRIS_Approval_Config_Detail')->where('Id', $idConfigDetail)->first();
        if (! $detail) {
            abort(404, 'Approval config detail tidak ditemukan.');
        }

        $this->assertConfigBelongsToGrup($user, (int) $detail->Id_Approval_Config, $grupId);

        return $detail;
    }

    // ========================================================================
    // SOURCE TABLE RESOLUTION (keamanan inti)
    // ========================================================================

    /**
     * Resolve meta tabel sumber yang BOLEH diedit untuk satu config detail.
     * Return null jika step tidak editable (custom approver, source type lain,
     * tabel di luar whitelist, atau kolom wajib tidak ada).
     *
     * @return array{table: string, approverCol: string, pkCol: string}|null
     */
    public function resolveSourceTableForDetail(object $detail): ?array
    {
        if (! empty($detail->Custom_Approver_Kode)) {
            return null;
        }

        $sourceType = strtoupper(trim((string) ($detail->Source_Type ?? '')));
        $editableTypes = array_map('strtoupper', (array) config('approval_matrix.editable_source_types', []));
        if (! in_array($sourceType, $editableTypes, true)) {
            return null;
        }

        $table = trim((string) ($detail->Source_Reference ?? ''));
        $whitelist = (array) config('approval_matrix.editable_source_tables', []);
        if ($table === '' || ! in_array($table, $whitelist, true)) {
            return null;
        }

        if (array_key_exists($table, $this->sourceResolutionCache)) {
            return $this->sourceResolutionCache[$table];
        }

        $tableColumns = $this->columnListingCached($table);
        if (empty($tableColumns)) {
            return $this->sourceResolutionCache[$table] = null;
        }

        // Deteksi dialek kolom, identik dengan ApproverResolverService.
        $approverCol = $this->hasColumnCached($table, 'Kode_Karyawan_Approver') ? 'Kode_Karyawan_Approver' : 'Kode_Karyawan';
        $pkCol = $this->hasColumnCached($table, 'Id_Approval_Flow') ? 'Id_Approval_Flow' : 'id';

        foreach (['Kode_Karyawan_Requester', 'order_flow', 'Status', $approverCol] as $requiredColumn) {
            if (! $this->hasColumnCached($table, $requiredColumn)) {
                return $this->sourceResolutionCache[$table] = null;
            }
        }

        return $this->sourceResolutionCache[$table] = [
            'table' => $table,
            'approverCol' => $approverCol,
            'pkCol' => $pkCol,
        ];
    }

    /**
     * Load config detail + jenis approval + enforce scope; abort jika step
     * tidak editable.
     *
     * @return array{config: object, detail: object, source: array}
     */
    private function resolveEditableContext(User $user, int $idConfigDetail): array
    {
        $detail = DB::table('N_HRIS_Approval_Config_Detail as d')
            ->leftJoin('N_HRIS_Jenis_Approval as ja', 'd.Id_Jenis_Approval', '=', 'ja.Id_Jenis_Approval')
            ->where('d.Id', $idConfigDetail)
            ->select('d.*', 'ja.Nama_Approval', 'ja.Source_Type', 'ja.Source_Reference')
            ->first();

        if (! $detail) {
            abort(404, 'Approval config detail tidak ditemukan.');
        }

        $config = $this->findConfigForUser($user, (int) $detail->Id_Approval_Config);

        $source = $this->resolveSourceTableForDetail($detail);
        if ($source === null) {
            abort(422, 'Step ini tidak mendukung pengelolaan assignment approver dari halaman ini.');
        }

        return ['config' => $config, 'detail' => $detail, 'source' => $source];
    }

    // ========================================================================
    // ASSIGNMENTS (module admin)
    // ========================================================================

    /**
     * Daftar requester + approver bertingkat untuk satu step (paginated).
     */
    public function getAssignments(User $user, int $idConfigDetail, array $filters = []): array
    {
        $context = $this->resolveEditableContext($user, $idConfigDetail);
        $source = $context['source'];

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $search = trim((string) ($filters['search'] ?? ''));

        $requesterQuery = DB::table($source['table'].' as source')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'source.Kode_Karyawan_Requester')
            ->whereNull('source.Status')
            ->where('k.Aktif', 'Y')
            ->select('source.Kode_Karyawan_Requester as kode', 'k.Nama as nama')
            ->distinct();
        $this->targetScopeService->applyToQuery(
            $requesterQuery,
            $user,
            (int) $context['config']->Id_Grup_Fitur,
            'k',
        );

        if ($search !== '') {
            $requesterQuery->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('source.Kode_Karyawan_Requester', 'like', "%{$search}%")
                    ->orWhere('k.UserId_Absen', 'like', "%{$search}%");
            });
        }

        $total = DB::query()->fromSub($requesterQuery, 'requesters')->count();

        $requesters = $requesterQuery
            ->orderBy('k.Nama')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $approverRows = collect();
        if ($requesters->isNotEmpty()) {
            $approverRows = DB::table($source['table'].' as source')
                ->leftJoin('Karyawan as a', 'a.Kode_Karyawan', '=', 'source.'.$source['approverCol'])
                ->whereIn('source.Kode_Karyawan_Requester', $requesters->pluck('kode')->all())
                ->whereNull('source.Status')
                ->orderBy('source.order_flow')
                ->select(
                    'source.'.$source['pkCol'].' as pk',
                    'source.Kode_Karyawan_Requester as requester_kode',
                    'source.'.$source['approverCol'].' as approver_kode',
                    'a.Nama as approver_nama',
                    'a.Aktif as approver_aktif',
                    'source.order_flow',
                )
                ->get()
                ->groupBy('requester_kode');
        }

        $items = $requesters->map(fn ($requester) => [
            'requester' => $requester,
            'approvers' => ($approverRows[$requester->kode] ?? collect())->values(),
        ]);

        return [
            'detail' => [
                'Id' => $context['detail']->Id,
                'Approval_Order' => $context['detail']->Approval_Order,
                'Nama_Approval' => $context['detail']->Nama_Approval,
                'Source_Table' => $source['table'],
            ],
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Ringkasan operasional approval per grup. Data assignment dihitung sekali
     * per source table agar source yang dipakai beberapa flow tidak terduplikasi.
     */
    public function getApprovalSummary(User $user, int $grupId): array
    {
        $configResult = $this->getConfigs($user, $grupId);
        $configs = collect($configResult['configs'] ?? []);
        $activeConfigs = $configs->where('Flag_Aktif', 'Y')->values();
        $editableTypes = array_map('strtoupper', (array) config('approval_matrix.editable_source_types', []));

        $details = $activeConfigs
            ->flatMap(function ($config) {
                return collect($config->flow_details ?? [])->map(function ($detail) use ($config) {
                    $detail->summary_config_id = (int) $config->Id;
                    $detail->summary_config_code = $config->Kode_Config;

                    return $detail;
                });
            })
            ->values();

        $issues = collect();
        $coverage = collect();
        $activeApprovers = collect();
        $activeEmployeesQuery = DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('k.Kode_Karyawan')
            ->select('k.Kode_Karyawan', 'k.Nama', 'k.UserId_Absen as NIK')
            ->orderBy('k.Nama');
        $this->targetScopeService->applyToQuery($activeEmployeesQuery, $user, $grupId, 'k');
        $activeEmployees = $activeEmployeesQuery
            ->get()
            ->keyBy(fn ($employee) => trim((string) $employee->Kode_Karyawan));
        $expectedEmployeeCodes = $activeEmployees->keys();

        foreach ($activeConfigs as $config) {
            if ((int) ($config->total_step ?? 0) === 0) {
                $issues->push([
                    'type' => 'config_without_steps',
                    'severity' => 'warning',
                    'title' => 'Alur aktif belum memiliki posisi approval',
                    'description' => "{$config->Kode_Config} aktif tetapi belum memiliki step approval.",
                    'config_id' => (int) $config->Id,
                    'config_detail_id' => null,
                    'source_reference' => null,
                    'action' => 'config',
                ]);
            }
        }

        $invalidDetails = $details->filter(function ($detail) use ($editableTypes) {
            $sourceType = strtoupper(trim((string) ($detail->Source_Type ?? '')));

            return empty($detail->Custom_Approver_Kode)
                && in_array($sourceType, $editableTypes, true)
                && $this->resolveSourceTableForDetail($detail) === null;
        });

        foreach ($invalidDetails as $detail) {
            $reference = trim((string) ($detail->Source_Reference ?? ''));
            $issues->push([
                'type' => 'invalid_source',
                'severity' => 'critical',
                'title' => $reference === '' ? 'Source assignment belum ditentukan' : 'Source assignment tidak valid',
                'description' => "{$detail->summary_config_code} · Posisi {$detail->Approval_Order} tidak dapat membaca source assignment.",
                'config_id' => (int) $detail->summary_config_id,
                'config_detail_id' => (int) $detail->Id,
                'source_reference' => $reference ?: null,
                'action' => 'flow',
            ]);
        }

        $validDetailsBySource = $details
            ->filter(fn ($detail) => $this->resolveSourceTableForDetail($detail) !== null)
            ->groupBy(fn ($detail) => $this->resolveSourceTableForDetail($detail)['table']);

        foreach ($validDetailsBySource as $table => $sourceDetails) {
            $representative = $sourceDetails->first();
            $source = $this->resolveSourceTableForDetail($representative);
            $activeRows = DB::table($table.' as source')
                ->join('Karyawan as requester', 'requester.Kode_Karyawan', '=', 'source.Kode_Karyawan_Requester')
                ->whereNull('source.Status');
            $this->targetScopeService->applyToQuery($activeRows, $user, $grupId, 'requester');

            $requesterCodes = (clone $activeRows)
                ->whereNotNull('source.Kode_Karyawan_Requester')
                ->distinct()
                ->pluck('source.Kode_Karyawan_Requester')
                ->map(fn ($kode) => trim((string) $kode))
                ->filter()
                ->unique()
                ->values();
            $coveredEmployeeCodes = $expectedEmployeeCodes->intersect($requesterCodes)->values();
            $missingEmployeeCodes = $expectedEmployeeCodes->diff($requesterCodes)->values();
            $missingEmployees = $missingEmployeeCodes
                ->map(function ($kode) use ($activeEmployees) {
                    $employee = $activeEmployees->get($kode);

                    return [
                        'kode' => $kode,
                        'nama' => $employee?->Nama ?: $kode,
                        'nik' => $employee?->NIK,
                    ];
                })
                ->values();
            $expectedEmployeeCount = $expectedEmployeeCodes->count();
            $coveredEmployeeCount = $coveredEmployeeCodes->count();
            $missingEmployeeCount = $missingEmployees->count();
            $coveragePercent = $expectedEmployeeCount > 0
                ? round(($coveredEmployeeCount / $expectedEmployeeCount) * 100, 1)
                : 100.0;

            $approverCodes = (clone $activeRows)
                ->whereNotNull('source.'.$source['approverCol'])
                ->distinct()
                ->pluck('source.'.$source['approverCol'])
                ->map(fn ($kode) => trim((string) $kode))
                ->filter()
                ->values();

            $activeApproverCodes = $approverCodes->isEmpty()
                ? collect()
                : DB::table('Karyawan')
                    ->whereIn('Kode_Karyawan', $approverCodes->all())
                    ->where('Aktif', 'Y')
                    ->pluck('Kode_Karyawan');
            $inactiveApproverCount = $approverCodes->diff($activeApproverCodes)->count();
            $activeApprovers = $activeApprovers->merge($activeApproverCodes);

            $flowHealth = DB::table($table.' as source')
                ->whereNull('source.Status')
                ->whereNotNull('source.Kode_Karyawan_Requester')
                ->groupBy('source.Kode_Karyawan_Requester')
                ->select(
                    'source.Kode_Karyawan_Requester',
                    DB::raw('MIN(source.order_flow) as min_order'),
                    DB::raw('MAX(source.order_flow) as max_order'),
                    DB::raw('COUNT(*) as row_count'),
                    DB::raw('COUNT(DISTINCT source.order_flow) as level_count'),
                );

            $orderIssueCount = DB::query()
                ->fromSub($flowHealth, 'flow_health')
                ->where(function ($query) {
                    $query
                        ->where('min_order', '<>', 1)
                        ->orWhereColumn('max_order', '<>', 'level_count')
                        ->orWhereColumn('row_count', '<>', 'level_count');
                })
                ->count();

            $maxOrder = (int) ((clone $activeRows)->max('source.order_flow') ?? 0);
            $configIds = $sourceDetails->pluck('summary_config_id')->unique()->values();
            $configCodes = $sourceDetails->pluck('summary_config_code')->unique()->values();
            $status = $inactiveApproverCount > 0 || $orderIssueCount > 0 || $missingEmployeeCount > 0
                ? 'attention'
                : ($coveredEmployeeCount > 0 ? 'healthy' : 'empty');

            $coverage->push([
                'source_reference' => $table,
                'source_type' => strtoupper((string) ($representative->Source_Type ?? 'TABLE')),
                'config_id' => (int) $representative->summary_config_id,
                'config_detail_id' => (int) $representative->Id,
                'config_codes' => $configCodes->all(),
                'flow_count' => $configIds->count(),
                'position_count' => $sourceDetails->count(),
                'requester_count' => $requesterCodes->count(),
                'expected_employee_count' => $expectedEmployeeCount,
                'covered_employee_count' => $coveredEmployeeCount,
                'missing_employee_count' => $missingEmployeeCount,
                'coverage_percent' => $coveragePercent,
                'missing_employees' => $missingEmployees->all(),
                'approver_count' => $approverCodes->unique()->count(),
                'max_order' => $maxOrder,
                'inactive_approver_count' => $inactiveApproverCount,
                'order_issue_count' => $orderIssueCount,
                'status' => $status,
            ]);

            if ($missingEmployeeCount > 0) {
                $sampleNames = $missingEmployees->take(3)->pluck('nama')->implode(', ');
                $remaining = max(0, $missingEmployeeCount - 3);
                $sampleSuffix = $remaining > 0 ? ", dan {$remaining} lainnya" : '';
                $issues->push([
                    'type' => 'missing_coverage',
                    'severity' => $coveredEmployeeCount === 0 ? 'critical' : 'warning',
                    'title' => "{$missingEmployeeCount} karyawan belum memiliki approval",
                    'description' => "{$table} baru mencakup {$coveragePercent}% karyawan aktif. Belum tercakup: {$sampleNames}{$sampleSuffix}.",
                    'config_id' => (int) $representative->summary_config_id,
                    'config_detail_id' => (int) $representative->Id,
                    'source_reference' => $table,
                    'action' => 'assignment',
                ]);
            }

            if ($inactiveApproverCount > 0) {
                $issues->push([
                    'type' => 'inactive_approver',
                    'severity' => 'critical',
                    'title' => "{$inactiveApproverCount} approver tidak aktif",
                    'description' => "{$table} masih mereferensikan approver yang tidak aktif atau tidak ditemukan.",
                    'config_id' => (int) $representative->summary_config_id,
                    'config_detail_id' => (int) $representative->Id,
                    'source_reference' => $table,
                    'action' => 'assignment',
                ]);
            }

            if ($orderIssueCount > 0) {
                $issues->push([
                    'type' => 'order_issue',
                    'severity' => 'critical',
                    'title' => "{$orderIssueCount} alur memiliki urutan bermasalah",
                    'description' => "{$table} memiliki level approval yang lompat atau duplikat.",
                    'config_id' => (int) $representative->summary_config_id,
                    'config_detail_id' => (int) $representative->Id,
                    'source_reference' => $table,
                    'action' => 'assignment',
                ]);
            }

        }

        $composition = $details
            ->groupBy(fn ($detail) => strtoupper(trim((string) ($detail->Source_Type ?? 'CUSTOM'))) ?: 'CUSTOM')
            ->map(fn (Collection $items, string $type) => ['type' => $type, 'count' => $items->count()])
            ->values();

        $recentActivity = $grupId > 0
            ? collect($this->getAssignmentHistory($user, null, ['grup_id' => $grupId, 'limit' => 8])['items'] ?? [])
            : collect();

        $severityOrder = ['critical' => 1, 'warning' => 2, 'info' => 3];

        return [
            'metrics' => [
                'total_configs' => $configs->count(),
                'active_configs' => $activeConfigs->count(),
                'total_positions' => $details->count(),
                'editable_positions' => $details->filter(fn ($detail) => $this->resolveSourceTableForDetail($detail) !== null)->count(),
                'active_employees' => $activeEmployees->count(),
                'unique_sources' => $coverage->count(),
                'shared_sources' => $coverage->where('flow_count', '>', 1)->count(),
                'assigned_requesters' => $coverage->sum('covered_employee_count'),
                'expected_assignments' => $coverage->sum('expected_employee_count'),
                'missing_assignments' => $coverage->sum('missing_employee_count'),
                'employee_coverage_percent' => $coverage->sum('expected_employee_count') > 0
                    ? round(($coverage->sum('covered_employee_count') / $coverage->sum('expected_employee_count')) * 100, 1)
                    : 100.0,
                'active_approvers' => $activeApprovers->unique()->count(),
                'issue_count' => $issues->count(),
            ],
            'coverage' => $coverage
                ->sortBy(fn ($item) => ['attention' => 1, 'empty' => 2, 'healthy' => 3][$item['status']].'|'.$item['source_reference'])
                ->values(),
            'issues' => $issues
                ->sortBy(fn ($issue) => ($severityOrder[$issue['severity']] ?? 9).'|'.$issue['title'])
                ->values(),
            'composition' => $composition->sortByDesc('count')->values(),
            'recent_activity' => $recentActivity,
        ];
    }

    public function getApprovalIssueCount(User $user, int $grupId): int
    {
        $configResult = $this->getConfigs($user, $grupId);
        $activeConfigs = collect($configResult['configs'] ?? [])->where('Flag_Aktif', 'Y')->values();
        $details = $activeConfigs->flatMap(fn ($config) => collect($config->flow_details ?? []))->values();
        $editableTypes = array_map('strtoupper', (array) config('approval_matrix.editable_source_types', []));
        $issueCount = $activeConfigs->filter(fn ($config) => (int) ($config->total_step ?? 0) === 0)->count();

        $issueCount += $details->filter(function ($detail) use ($editableTypes) {
            return empty($detail->Custom_Approver_Kode)
                && in_array(strtoupper(trim((string) ($detail->Source_Type ?? ''))), $editableTypes, true)
                && $this->resolveSourceTableForDetail($detail) === null;
        })->count();

        $activeEmployeeQuery = DB::table('Karyawan as employee')
            ->where('employee.Aktif', 'Y')
            ->whereNotNull('employee.Kode_Karyawan');
        $this->targetScopeService->applyToQuery($activeEmployeeQuery, $user, $grupId, 'employee');
        $activeEmployeeCodes = $activeEmployeeQuery
            ->pluck('employee.Kode_Karyawan')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique();

        $details
            ->filter(fn ($detail) => $this->resolveSourceTableForDetail($detail) !== null)
            ->groupBy(fn ($detail) => $this->resolveSourceTableForDetail($detail)['table'])
            ->each(function (Collection $sourceDetails, string $table) use (&$issueCount, $activeEmployeeCodes, $user, $grupId) {
                $source = $this->resolveSourceTableForDetail($sourceDetails->first());
                $activeRows = DB::table($table.' as source')
                    ->join('Karyawan as requester', 'requester.Kode_Karyawan', '=', 'source.Kode_Karyawan_Requester')
                    ->whereNull('source.Status');
                $this->targetScopeService->applyToQuery($activeRows, $user, $grupId, 'requester');
                $requesterCodes = (clone $activeRows)
                    ->whereNotNull('source.Kode_Karyawan_Requester')
                    ->distinct()
                    ->pluck('source.Kode_Karyawan_Requester')
                    ->map(fn ($code) => trim((string) $code))
                    ->filter()
                    ->unique();
                if ($activeEmployeeCodes->diff($requesterCodes)->isNotEmpty()) {
                    $issueCount++;
                }

                $approverCodes = (clone $activeRows)
                    ->whereNotNull('source.'.$source['approverCol'])
                    ->distinct()
                    ->pluck('source.'.$source['approverCol'])
                    ->map(fn ($code) => trim((string) $code))
                    ->filter()
                    ->unique();
                $activeApprovers = $approverCodes->isEmpty()
                    ? collect()
                    : DB::table('Karyawan')
                        ->whereIn('Kode_Karyawan', $approverCodes->all())
                        ->where('Aktif', 'Y')
                        ->pluck('Kode_Karyawan');
                if ($approverCodes->diff($activeApprovers)->isNotEmpty()) {
                    $issueCount++;
                }

                $flowHealth = DB::table($table.' as source')
                    ->join('Karyawan as requester', 'requester.Kode_Karyawan', '=', 'source.Kode_Karyawan_Requester')
                    ->whereNull('source.Status')
                    ->whereNotNull('source.Kode_Karyawan_Requester')
                    ->groupBy('source.Kode_Karyawan_Requester')
                    ->select(
                        'source.Kode_Karyawan_Requester',
                        DB::raw('MIN(source.order_flow) as min_order'),
                        DB::raw('MAX(source.order_flow) as max_order'),
                        DB::raw('COUNT(*) as row_count'),
                        DB::raw('COUNT(DISTINCT source.order_flow) as level_count'),
                    );
                $this->targetScopeService->applyToQuery($flowHealth, $user, $grupId, 'requester');
                $hasOrderIssue = DB::query()->fromSub($flowHealth, 'flow_health')
                    ->where(function ($query) {
                        $query->where('min_order', '<>', 1)
                            ->orWhereColumn('max_order', '<>', 'level_count')
                            ->orWhereColumn('row_count', '<>', 'level_count');
                    })
                    ->exists();
                if ($hasOrderIssue) {
                    $issueCount++;
                }
            });

        return $issueCount;
    }

    /**
     * Ganti seluruh flow approver milik satu requester pada step ini.
     * $approvers: [['kode' => 'A01', 'order_flow' => 1], ...]
     * Array kosong = hapus flow (REMOVE).
     */
    public function replaceRequesterFlow(
        User $user,
        int $idConfigDetail,
        string $kodeRequester,
        array $approvers,
        ?string $catatan = null,
    ): array {
        $context = $this->resolveEditableContext($user, $idConfigDetail);
        $source = $context['source'];
        $kodeRequester = trim($kodeRequester);

        $requester = DB::table('Karyawan')
            ->where('Kode_Karyawan', $kodeRequester)
            ->where('Aktif', 'Y')
            ->first(['Kode_Karyawan', 'Nama']);
        if (! $requester) {
            abort(422, 'Requester tidak ditemukan atau tidak aktif: '.$kodeRequester);
        }
        $this->targetScopeService->assertEmployeeCodesAllowed(
            $user,
            (int) $context['config']->Id_Grup_Fitur,
            [$kodeRequester],
        );

        $normalized = $this->validateApprovers($approvers, $kodeRequester);

        return DB::transaction(function () use ($context, $source, $kodeRequester, $normalized, $catatan, $user) {
            $before = DB::table($source['table'])
                ->where('Kode_Karyawan_Requester', $kodeRequester)
                ->whereNull('Status')
                ->orderBy('order_flow')
                ->get();

            DB::table($source['table'])
                ->where('Kode_Karyawan_Requester', $kodeRequester)
                ->whereNull('Status')
                ->delete();

            $tableColumns = collect($this->columnListingCached($source['table']));
            $inserted = [];

            foreach ($normalized as $approver) {
                $payload = [
                    'Kode_Karyawan_Requester' => $kodeRequester,
                    $source['approverCol'] => $approver['kode'],
                    'order_flow' => $approver['order_flow'],
                ];

                $hasDiv = $tableColumns->contains(fn ($c) => strtolower($c) === 'division_id');
                $hasLvl = $tableColumns->contains(fn ($c) => strtolower($c) === 'level_id');
                if ($hasDiv || $hasLvl) {
                    $approverOrg = DB::table('Karyawan as k')
                        ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                        ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
                        ->where('k.Kode_Karyawan', $approver['kode'])
                        ->select('div.ID_Divisi', 'lvl.ID_Level')
                        ->first();
                    if ($approverOrg) {
                        if ($hasDiv) {
                            $exactDivCol = $tableColumns->first(fn ($c) => strtolower($c) === 'division_id');
                            $payload[$exactDivCol] = $approverOrg->ID_Divisi;
                        }
                        if ($hasLvl) {
                            $exactLvlCol = $tableColumns->first(fn ($c) => strtolower($c) === 'level_id');
                            $payload[$exactLvlCol] = $approverOrg->ID_Level;
                        }
                    }
                }

                foreach (['Created_At' => now(), 'created_at' => now(), 'Created_By' => $this->actorKode($user)] as $column => $value) {
                    if ($tableColumns->contains($column) && ! array_key_exists($column, $payload)) {
                        $payload[$column] = $value;
                    }
                }

                DB::table($source['table'])->insert($payload);
                $inserted[] = $payload;
            }

            $this->writeAudit(
                $context,
                empty($normalized) ? 'REMOVE' : 'REPLACE_FLOW',
                $kodeRequester,
                $before->toArray(),
                $inserted,
                $catatan,
                $user,
            );

            return [
                'requester' => $kodeRequester,
                'total_approver' => count($normalized),
            ];
        });
    }

    /**
     * Pasang satu approver di level tertentu untuk banyak requester sekaligus.
     */
    public function bulkAssign(
        User $user,
        int $idConfigDetail,
        string $approverKode,
        array $requesterKodes,
        int $orderFlow,
        ?string $catatan = null,
    ): array {
        $context = $this->resolveEditableContext($user, $idConfigDetail);
        $source = $context['source'];

        $maxOrder = (int) config('approval_matrix.max_order_flow', 10);
        if ($orderFlow < 1 || $orderFlow > $maxOrder) {
            abort(422, "order_flow harus antara 1 dan {$maxOrder}.");
        }

        $approver = DB::table('Karyawan')
            ->where('Kode_Karyawan', trim($approverKode))
            ->where('Aktif', 'Y')
            ->first(['Kode_Karyawan', 'Nama']);
        if (! $approver) {
            abort(422, 'Approver tidak ditemukan atau tidak aktif: '.$approverKode);
        }

        $requesterKodes = collect($requesterKodes)
            ->map(fn ($kode) => trim((string) $kode))
            ->filter()
            ->unique()
            ->values();
        if ($requesterKodes->isEmpty()) {
            abort(422, 'Daftar requester tidak boleh kosong.');
        }

        $activeRequesters = DB::table('Karyawan')
            ->whereIn('Kode_Karyawan', $requesterKodes->all())
            ->where('Aktif', 'Y')
            ->pluck('Kode_Karyawan');

        $missing = $requesterKodes->diff($activeRequesters);
        if ($missing->isNotEmpty()) {
            abort(422, 'Requester tidak ditemukan/tidak aktif: '.$missing->implode(', '));
        }
        $this->targetScopeService->assertEmployeeCodesAllowed(
            $user,
            (int) $context['config']->Id_Grup_Fitur,
            $requesterKodes->all(),
        );

        return DB::transaction(function () use ($context, $source, $approver, $requesterKodes, $orderFlow, $catatan, $user) {
            $tableColumns = collect($this->columnListingCached($source['table']));
            $skipped = [];
            $applied = [];

            foreach ($requesterKodes as $kodeRequester) {
                if ($kodeRequester === $approver->Kode_Karyawan) {
                    $skipped[] = ['requester' => $kodeRequester, 'alasan' => 'Approver sama dengan requester'];

                    continue;
                }

                $existing = DB::table($source['table'])
                    ->where('Kode_Karyawan_Requester', $kodeRequester)
                    ->whereNull('Status')
                    ->orderBy('order_flow')
                    ->get();

                $current = $existing->firstWhere('order_flow', $orderFlow);
                if ($current && trim((string) $current->{$source['approverCol']}) === $approver->Kode_Karyawan) {
                    $skipped[] = ['requester' => $kodeRequester, 'alasan' => 'Sudah ter-assign di level yang sama'];

                    continue;
                }

                // Replace-at-order: hapus baris level ini lalu insert approver baru.
                DB::table($source['table'])
                    ->where('Kode_Karyawan_Requester', $kodeRequester)
                    ->whereNull('Status')
                    ->where('order_flow', $orderFlow)
                    ->delete();

                $payload = [
                    'Kode_Karyawan_Requester' => $kodeRequester,
                    $source['approverCol'] => $approver->Kode_Karyawan,
                    'order_flow' => $orderFlow,
                ];

                $hasDiv = $tableColumns->contains(fn ($c) => strtolower($c) === 'division_id');
                $hasLvl = $tableColumns->contains(fn ($c) => strtolower($c) === 'level_id');
                if ($hasDiv || $hasLvl) {
                    $approverOrg = DB::table('Karyawan as k')
                        ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                        ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
                        ->where('k.Kode_Karyawan', $approver->Kode_Karyawan)
                        ->select('div.ID_Divisi', 'lvl.ID_Level')
                        ->first();
                    if ($approverOrg) {
                        if ($hasDiv) {
                            $exactDivCol = $tableColumns->first(fn ($c) => strtolower($c) === 'division_id');
                            $payload[$exactDivCol] = $approverOrg->ID_Divisi;
                        }
                        if ($hasLvl) {
                            $exactLvlCol = $tableColumns->first(fn ($c) => strtolower($c) === 'level_id');
                            $payload[$exactLvlCol] = $approverOrg->ID_Level;
                        }
                    }
                }

                foreach (['Created_At' => now(), 'created_at' => now(), 'Created_By' => $this->actorKode($user)] as $column => $value) {
                    if ($tableColumns->contains($column) && ! array_key_exists($column, $payload)) {
                        $payload[$column] = $value;
                    }
                }

                DB::table($source['table'])->insert($payload);

                $after = DB::table($source['table'])
                    ->where('Kode_Karyawan_Requester', $kodeRequester)
                    ->whereNull('Status')
                    ->orderBy('order_flow')
                    ->get()
                    ->toArray();

                $this->writeAudit(
                    $context,
                    'BULK_ASSIGN',
                    $kodeRequester,
                    $existing->toArray(),
                    $after,
                    $catatan,
                    $user,
                );

                $applied[] = $kodeRequester;
            }

            return [
                'approver' => $approver->Kode_Karyawan,
                'applied' => $applied,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * Ganti seluruh flow approver milik beberapa requester sekaligus (bulk replace flow).
     */
    public function bulkReplaceRequesterFlows(
        User $user,
        int $idConfigDetail,
        array $requesterKodes,
        array $approvers,
        ?string $catatan = null,
    ): array {
        $context = $this->resolveEditableContext($user, $idConfigDetail);

        $requesterKodes = collect($requesterKodes)
            ->map(fn ($kode) => trim((string) $kode))
            ->filter()
            ->unique()
            ->values();

        if ($requesterKodes->isEmpty()) {
            abort(422, 'Daftar requester tidak boleh kosong.');
        }

        // Validate all requesters exist and are active
        $activeRequesters = DB::table('Karyawan')
            ->whereIn('Kode_Karyawan', $requesterKodes->all())
            ->where('Aktif', 'Y')
            ->pluck('Kode_Karyawan');

        $missing = $requesterKodes->diff($activeRequesters);
        if ($missing->isNotEmpty()) {
            abort(422, 'Requester tidak ditemukan/tidak aktif: '.$missing->implode(', '));
        }
        $this->targetScopeService->assertEmployeeCodesAllowed(
            $user,
            (int) $context['config']->Id_Grup_Fitur,
            $requesterKodes->all(),
        );

        // Run the replacement for each requester inside a single transaction
        return DB::transaction(function () use ($user, $idConfigDetail, $requesterKodes, $approvers, $catatan) {
            $details = [];
            foreach ($requesterKodes as $kodeRequester) {
                $details[] = $this->replaceRequesterFlow($user, $idConfigDetail, $kodeRequester, $approvers, $catatan);
            }

            return [
                'updated_count' => count($details),
                'details' => $details,
            ];
        });
    }

    /**
     * Mengganti approver X dengan approver Y untuk semua requester pada step yang dipilih.
     */
    public function bulkSubstituteApprover(
        User $user,
        int $idConfigDetail,
        string $oldApproverKode,
        string $newApproverKode,
        ?string $catatan = null,
    ): array {
        $context = $this->resolveEditableContext($user, $idConfigDetail);
        $source = $context['source'];

        // Validate old approver (must exist in system)
        $oldApprover = DB::table('Karyawan')
            ->where('Kode_Karyawan', trim($oldApproverKode))
            ->first(['Kode_Karyawan', 'Nama']);
        if (! $oldApprover) {
            abort(422, 'Approver lama tidak ditemukan.');
        }

        // Validate new approver (must exist and be active)
        $newApprover = DB::table('Karyawan')
            ->where('Kode_Karyawan', trim($newApproverKode))
            ->where('Aktif', 'Y')
            ->first(['Kode_Karyawan', 'Nama']);
        if (! $newApprover) {
            abort(422, 'Approver baru tidak ditemukan atau tidak aktif: '.$newApproverKode);
        }

        if ($oldApprover->Kode_Karyawan === $newApprover->Kode_Karyawan) {
            abort(422, 'Approver lama dan baru tidak boleh sama.');
        }

        return DB::transaction(function () use ($context, $source, $oldApprover, $newApprover, $catatan, $user) {
            $rowsQuery = DB::table($source['table'].' as source')
                ->join('Karyawan as requester', 'requester.Kode_Karyawan', '=', 'source.Kode_Karyawan_Requester')
                ->where('source.'.$source['approverCol'], $oldApprover->Kode_Karyawan)
                ->whereNull('source.Status')
                ->select('source.*');
            $this->targetScopeService->applyToQuery(
                $rowsQuery,
                $user,
                (int) $context['config']->Id_Grup_Fitur,
                'requester',
            );
            $rows = $rowsQuery->get();

            $updatedCount = 0;
            $skipped = [];
            $applied = [];

            // Group by requester
            $requesterKodes = $rows->pluck('Kode_Karyawan_Requester')->unique();
            $tableColumns = collect($this->columnListingCached($source['table']));

            foreach ($requesterKodes as $kodeRequester) {
                // Get all active flow rows for this requester
                $fullFlow = DB::table($source['table'])
                    ->where('Kode_Karyawan_Requester', $kodeRequester)
                    ->whereNull('Status')
                    ->orderBy('order_flow')
                    ->get();

                // If new approver is the requester themselves, skip it
                if ($kodeRequester === $newApprover->Kode_Karyawan) {
                    $skipped[] = [
                        'requester' => $kodeRequester,
                        'alasan' => 'Approver baru sama dengan requester',
                    ];

                    continue;
                }

                // Check if new approver already exists in other levels of the flow
                $existingOtherLevel = $fullFlow->contains(function ($row) use ($source, $newApprover, $oldApprover) {
                    return $row->{$source['approverCol']} === $newApprover->Kode_Karyawan
                        && $row->{$source['approverCol']} !== $oldApprover->Kode_Karyawan;
                });

                if ($existingOtherLevel) {
                    $skipped[] = [
                        'requester' => $kodeRequester,
                        'alasan' => 'Approver baru sudah terdaftar di level flow lain',
                    ];

                    continue;
                }

                $before = $fullFlow->toArray();

                $updatePayload = [
                    $source['approverCol'] => $newApprover->Kode_Karyawan,
                ];
                foreach (['Updated_At' => now(), 'updated_at' => now(), 'Updated_By' => $this->actorKode($user), 'updated_by' => $this->actorKode($user)] as $column => $value) {
                    if ($tableColumns->contains($column)) {
                        $updatePayload[$column] = $value;
                    }
                }

                // Perform update
                DB::table($source['table'])
                    ->where('Kode_Karyawan_Requester', $kodeRequester)
                    ->where($source['approverCol'], $oldApprover->Kode_Karyawan)
                    ->whereNull('Status')
                    ->update($updatePayload);

                // Query the new flow state for audit log
                $after = DB::table($source['table'])
                    ->where('Kode_Karyawan_Requester', $kodeRequester)
                    ->whereNull('Status')
                    ->orderBy('order_flow')
                    ->get()
                    ->toArray();

                $this->writeAudit(
                    $context,
                    'BULK_SUBSTITUTE',
                    $kodeRequester,
                    $before,
                    $after,
                    $catatan,
                    $user,
                );

                $applied[] = $kodeRequester;
                $updatedCount++;
            }

            return [
                'updated_count' => $updatedCount,
                'applied' => $applied,
                'skipped' => $skipped,
            ];
        });
    }

    public function getAssignmentHistory(User $user, ?int $idConfigDetail, array $filters = []): array
    {
        $grupId = (int) ($filters['grup_id'] ?? 0);
        abort_if($grupId <= 0, 422, 'grup_id wajib diisi.');
        $capabilities = $this->buildMatrixCapabilities($user, $grupId);
        if (! $capabilities['approvalMatrix']['canView']) {
            abort(403, 'Anda tidak memiliki akses untuk melihat riwayat approval.');
        }

        if (! $this->tableExistsCached('N_HRIS_Transaksi_Matrix_Approver')) {
            return ['items' => [], 'notice' => 'Tabel audit N_HRIS_Transaksi_Matrix_Approver belum tersedia.'];
        }

        $query = DB::table('N_HRIS_Transaksi_Matrix_Approver as t')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 't.Created_By')
            ->leftJoin('Karyawan as requester', 'requester.Kode_Karyawan', '=', 't.Kode_Karyawan_Requester')
            ->leftJoin('N_HRIS_Approval_Config_Detail as d', 'd.Id', '=', 't.Id_Approval_Config_Detail')
            ->leftJoin('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'd.Id_Jenis_Approval')
            ->leftJoin('N_HRIS_Approval_Config as c', 'c.Id', '=', 't.Id_Approval_Config');
        $this->targetScopeService->applyToQuery($query, $user, $grupId, 'requester');

        if ($idConfigDetail) {
            $this->assertConfigDetailBelongsToGrup($user, $idConfigDetail, $grupId);
            $query->where('t.Id_Approval_Config_Detail', $idConfigDetail);
        } elseif (! empty($filters['grup_id'])) {
            $allowed = $this->manageableGroupIds($user);
            if (! $this->isSystemAdmin($user) && ($allowed === null || ! in_array($grupId, $allowed, true))) {
                abort(403, 'Anda tidak memiliki akses ke grup fitur ini.');
            }
            if ($grupId === 0) {
                $query->whereNull('c.Id_Grup_Fitur');
            } else {
                $query->where('c.Id_Grup_Fitur', $grupId);
            }
        } else {
            abort(422, 'Salah satu dari config_detail_id atau grup_id wajib diisi.');
        }

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 30)));

        $rows = $query
            ->orderByDesc('t.Created_At')
            ->limit($limit * 20)
            ->select(
                't.*',
                'k.Nama as Created_By_Nama',
                'requester.Nama as Requester_Nama',
                'ja.Source_Type',
                'c.Kode_Config',
                'c.Nama_Config',
                'ja.Nama_Approval'
            )
            ->get();

        $approverCodes = $rows
            ->flatMap(fn ($row) => array_merge(
                $this->approvalAuditApproverCodes($this->decodeAuditValue($row->Data_Sebelum)),
                $this->approvalAuditApproverCodes($this->decodeAuditValue($row->Data_Sesudah)),
            ))
            ->unique()
            ->values();
        $approverNames = $approverCodes->isEmpty()
            ? collect()
            : DB::table('Karyawan')->whereIn('Kode_Karyawan', $approverCodes)->pluck('Nama', 'Kode_Karyawan');

        $items = $rows
            ->groupBy(function ($row) {
                return implode('|', [
                    $row->Created_By,
                    date('Y-m-d H:i:s', strtotime((string) $row->Created_At)),
                    $row->Jenis_Transaksi,
                    $row->Id_Approval_Config_Detail,
                    $row->Catatan,
                ]);
            })
            ->map(function ($transactionRows, $groupKey) use ($approverNames) {
                $first = $transactionRows->first();
                $targets = $transactionRows
                    ->map(fn ($row) => [
                        'kode' => $row->Kode_Karyawan_Requester,
                        'nama' => $row->Requester_Nama ?: $row->Kode_Karyawan_Requester,
                        'changes' => [[
                            'field' => $row->Source_Table,
                            'before' => $this->decodeAuditValue($row->Data_Sebelum),
                            'after' => $this->decodeAuditValue($row->Data_Sesudah),
                            'approver_names' => $approverNames->all(),
                            'transaction_type' => $row->Jenis_Transaksi,
                        ]],
                    ])
                    ->values();
                $isBulk = $targets->count() > 1
                    || str_contains(strtoupper((string) $first->Jenis_Transaksi), 'BULK');

                return [
                    'id' => 'APR-'.substr(sha1($groupKey), 0, 12),
                    'transaction_id' => 'APR-'.substr(sha1($groupKey), 0, 12),
                    'type' => $isBulk ? 'Bulk Assignment Approval' : 'Perubahan Assignment Approval',
                    'actor_code' => $first->Created_By,
                    'actor_name' => $first->Created_By_Nama ?: $first->Created_By,
                    'occurred_at' => (string) $first->Created_At,
                    'target_count' => $targets->count(),
                    'change_count' => $transactionRows->count(),
                    'summary' => ($first->Kode_Config ?: 'Approval config')
                        .' · '
                        .($first->Nama_Approval ?: $first->Source_Table)
                        .($first->Catatan ? " · {$first->Catatan}" : ''),
                    'targets' => $targets->all(),
                ];
            })
            ->take($limit)
            ->values();

        return ['items' => $items, 'notice' => null];
    }

    private function approvalAuditApproverCodes($value): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn ($row) => is_array($row) ? $row : (array) $row)
            ->map(fn ($row) => trim((string) ($row['Kode_Karyawan_Approver'] ?? $row['Kode_Karyawan'] ?? '')))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Riwayat gabungan Menu & Approval pada seluruh grup yang boleh dikelola
     * admin. Satu item merepresentasikan satu transaksi induk.
     */
    public function getUnifiedAuditHistory(User $user, int $grupId, int $limit = 100): array
    {
        $capabilities = $this->buildMatrixCapabilities($user, $grupId);
        if (! $capabilities['menu']['canView'] && ! $capabilities['approvalMatrix']['canView']) {
            abort(403, 'Anda tidak memiliki akses untuk melihat riwayat Menu & Approval.');
        }

        $this->assertCanManageGrup($user, $grupId);
        $limit = max(1, min(200, $limit));
        $items = collect();

        if ($capabilities['menu']['canView'] && $this->tableExistsCached('HRIS_Transaksi_Log_Admin')) {
            $menuRows = DB::table('HRIS_Transaksi_Log_Admin as t')
                ->join('HRIS_Transaksi_Log_Admin_Detail as d', 't.No_Transaksi', '=', 'd.No_Transaksi')
                ->leftJoin('HRIS_Transaksi_Log_Admin_Det as det', 'd.Urut_Oto', '=', 'det.Urut_Detail')
                ->leftJoin('Karyawan as actor', 't.UserID', '=', 'actor.Kode_Karyawan')
                ->leftJoin('Karyawan as target', 'd.Kode_Karyawan', '=', 'target.Kode_Karyawan')
                ->where(function ($query) {
                    $query
                        ->where('d.Deskripsi', 'like', '%/menu-approval-matrix/menu-binding/%')
                        ->orWhere('d.Deskripsi', 'like', '%/system-auth/menu-binding/%');
                })
                ->orderByDesc('t.Tanggal')
                ->orderByDesc('t.Jam')
                ->limit($limit * 10)
                ->select(
                    't.No_Transaksi',
                    't.Tanggal',
                    't.Jam',
                    't.UserID as Actor_Kode',
                    'actor.Nama as Actor_Nama',
                    'd.Kode_Karyawan as Target_Kode',
                    'target.Nama as Target_Nama',
                    'd.Jenis',
                    'd.Deskripsi as Action_Path',
                    'det.Field_Diubah',
                    'det.Nilai_Awal',
                    'det.Nilai_Setelah',
                );
            $this->targetScopeService->applyToQuery($menuRows, $user, $grupId, 'target');
            $menuRows = $menuRows->get()
                ->map(function ($row) {
                    preg_match('#/menu-binding/(\d+)#i', (string) $row->Action_Path, $matches);
                    $row->Grup_Id = isset($matches[1]) ? (int) $matches[1] : null;

                    return $row;
                })
                ->filter(fn ($row) => (int) $row->Grup_Id === $grupId)
                ->groupBy('No_Transaksi')
                ->map(function (Collection $rows, string $transactionId) {
                    $first = $rows->first();
                    $targets = $rows
                        ->groupBy('Target_Kode')
                        ->map(function (Collection $targetRows, $targetKode) {
                            $target = $targetRows->first();

                            return [
                                'kode' => $targetKode,
                                'nama' => $target->Target_Nama ?: $targetKode,
                                'changes' => $targetRows
                                    ->map(fn ($row) => [
                                        'field' => $row->Field_Diubah,
                                        'before' => $this->decodeAuditValue($row->Nilai_Awal),
                                        'after' => $this->decodeAuditValue($row->Nilai_Setelah),
                                    ])
                                    ->values()
                                    ->all(),
                            ];
                        })
                        ->values();
                    $isBulk = $targets->count() > 1 || str_contains(strtolower((string) $first->Action_Path), 'bulk');

                    return [
                        'id' => 'menu-'.$transactionId,
                        'transaction_id' => $transactionId,
                        'module' => 'menu',
                        'type' => $isBulk ? 'Bulk Assignment Menu' : 'Perubahan Akses Menu',
                        'actor_code' => $first->Actor_Kode,
                        'actor_name' => $first->Actor_Nama ?: $first->Actor_Kode,
                        'occurred_at' => $this->combineAuditDateTime($first->Tanggal, $first->Jam),
                        'group_id' => $first->Grup_Id,
                        'target_count' => $targets->count(),
                        'change_count' => $rows->count(),
                        'summary' => $isBulk
                            ? "Mengubah akses menu untuk {$targets->count()} karyawan."
                            : 'Mengubah akses menu seorang karyawan.',
                        'targets' => $targets->all(),
                    ];
                })
                ->values();

            $items = $items->concat($menuRows);
        }

        if ($capabilities['approvalMatrix']['canView'] && $this->tableExistsCached('N_HRIS_Transaksi_Matrix_Approver')) {
            $approvalQuery = DB::table('N_HRIS_Transaksi_Matrix_Approver as t')
                ->leftJoin('Karyawan as actor', 'actor.Kode_Karyawan', '=', 't.Created_By')
                ->leftJoin('Karyawan as requester', 'requester.Kode_Karyawan', '=', 't.Kode_Karyawan_Requester')
                ->leftJoin('N_HRIS_Approval_Config_Detail as d', 'd.Id', '=', 't.Id_Approval_Config_Detail')
                ->leftJoin('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'd.Id_Jenis_Approval')
                ->leftJoin('N_HRIS_Approval_Config as c', 'c.Id', '=', 't.Id_Approval_Config')
                ->orderByDesc('t.Created_At')
                ->limit($limit * 10);

            if ($this->configHasGrupColumn()) {
                $approvalQuery->where('c.Id_Grup_Fitur', $grupId);
            }
            $this->targetScopeService->applyToQuery($approvalQuery, $user, $grupId, 'requester');

            $approvalRows = $approvalQuery
                ->select(
                    't.*',
                    'actor.Nama as Actor_Nama',
                    'requester.Nama as Requester_Nama',
                    'c.Id_Grup_Fitur',
                    'c.Kode_Config',
                    'c.Nama_Config',
                    'ja.Nama_Approval',
                )
                ->get()
                ->groupBy(function ($row) {
                    $createdAt = date('Y-m-d H:i:s', strtotime((string) $row->Created_At));

                    return implode('|', [
                        $row->Created_By,
                        $createdAt,
                        $row->Jenis_Transaksi,
                        $row->Id_Approval_Config_Detail,
                        $row->Catatan,
                    ]);
                })
                ->map(function (Collection $rows, string $groupKey) {
                    $first = $rows->first();
                    $targets = $rows->map(fn ($row) => [
                        'kode' => $row->Kode_Karyawan_Requester,
                        'nama' => $row->Requester_Nama ?: $row->Kode_Karyawan_Requester,
                        'changes' => [[
                            'field' => $row->Source_Table,
                            'before' => $this->decodeAuditValue($row->Data_Sebelum),
                            'after' => $this->decodeAuditValue($row->Data_Sesudah),
                        ]],
                    ])->values();
                    $isBulk = $targets->count() > 1 || str_contains(strtoupper((string) $first->Jenis_Transaksi), 'BULK');
                    $transactionId = 'APR-'.substr(sha1($groupKey), 0, 12);

                    return [
                        'id' => 'approval-'.$transactionId,
                        'transaction_id' => $transactionId,
                        'module' => 'approval',
                        'type' => $isBulk ? 'Bulk Assignment Approval' : 'Perubahan Assignment Approval',
                        'actor_code' => $first->Created_By,
                        'actor_name' => $first->Actor_Nama ?: $first->Created_By,
                        'occurred_at' => (string) $first->Created_At,
                        'group_id' => $first->Id_Grup_Fitur,
                        'target_count' => $targets->count(),
                        'change_count' => $rows->count(),
                        'summary' => ($first->Kode_Config ?: 'Approval config')
                            .' · '
                            .($first->Nama_Approval ?: $first->Source_Table)
                            .($first->Catatan ? " · {$first->Catatan}" : ''),
                        'targets' => $targets->all(),
                    ];
                })
                ->values();

            $items = $items->concat($approvalRows);
        }

        return [
            'items' => $items
                ->sortByDesc(fn ($item) => strtotime((string) $item['occurred_at']))
                ->take($limit)
                ->values()
                ->all(),
        ];
    }

    private function decodeAuditValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function combineAuditDateTime($date, $time): string
    {
        $dateString = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);

        return trim($dateString.' '.substr((string) $time, 0, 8));
    }

    /**
     * Pencarian karyawan aktif untuk picker approver/requester.
     */
    public function searchKaryawan(User $user, int $grupId, string $purpose, ?string $search, int $limit = 20): Collection
    {
        $capabilities = $this->buildMatrixCapabilities($user, $grupId);
        abort_unless(in_array($purpose, ['target', 'approver'], true), 422, 'Purpose pencarian karyawan tidak valid.');
        abort_if($purpose === 'approver' && ! $capabilities['approvalMatrix']['canView'], 403, 'Pencarian approver hanya tersedia pada tab Approval.');

        $query = DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')
            ->select('k.Kode_Karyawan', 'k.Nama', 'k.UserId_Absen as NIK', 'k.UserID_Web as id', 'k.UserID_Web')
            ->orderBy('k.Nama')
            ->limit(max(1, min(50, $limit)));
        if ($purpose === 'target') {
            $this->targetScopeService->applyToQuery($query, $user, $grupId, 'k');
        }

        $search = trim((string) $search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('k.Kode_Karyawan', 'like', "%{$search}%")
                    ->orWhere('k.UserId_Absen', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * @return array<int, array{kode: string, order_flow: int}>
     */
    private function validateApprovers(array $approvers, string $kodeRequester): array
    {
        $maxOrder = (int) config('approval_matrix.max_order_flow', 10);

        $normalized = collect($approvers)
            ->map(fn ($item) => [
                'kode' => trim((string) ($item['kode'] ?? '')),
                'order_flow' => (int) ($item['order_flow'] ?? 0),
            ])
            ->values();

        if ($normalized->isEmpty()) {
            return [];
        }

        if ($normalized->contains(fn ($item) => $item['kode'] === '')) {
            abort(422, 'Kode approver tidak boleh kosong.');
        }

        if ($normalized->contains(fn ($item) => $item['kode'] === $kodeRequester)) {
            abort(422, 'Approver tidak boleh sama dengan requester.');
        }

        if ($normalized->pluck('kode')->duplicates()->isNotEmpty()) {
            abort(422, 'Approver tidak boleh duplikat dalam satu flow.');
        }

        $orders = $normalized->pluck('order_flow')->sort()->values();
        if ($orders->duplicates()->isNotEmpty()) {
            abort(422, 'order_flow tidak boleh duplikat.');
        }
        if ($orders->first() !== 1 || $orders->last() !== $orders->count()) {
            abort(422, 'order_flow harus berurutan mulai dari 1 tanpa lompatan.');
        }
        if ($orders->last() > $maxOrder) {
            abort(422, "Maksimal {$maxOrder} level approver.");
        }

        $kodes = $normalized->pluck('kode');
        $active = DB::table('Karyawan')
            ->whereIn('Kode_Karyawan', $kodes->all())
            ->where('Aktif', 'Y')
            ->pluck('Kode_Karyawan');

        $missing = $kodes->diff($active);
        if ($missing->isNotEmpty()) {
            abort(422, 'Approver tidak ditemukan/tidak aktif: '.$missing->implode(', '));
        }

        return $normalized->sortBy('order_flow')->values()->all();
    }

    private function actorKode(User $user): string
    {
        return (string) ($user->nik ?? ($user->Kode_Karyawan ?? ($user->username ?? ('UID:'.($user->id ?? Auth::id())))));
    }

    /**
     * @param  array{config: object, detail: object, source: array}  $context
     */
    private function writeAudit(
        array $context,
        string $jenisTransaksi,
        string $kodeRequester,
        array $before,
        array $after,
        ?string $catatan,
        User $user,
    ): void {
        if (! $this->tableExistsCached('N_HRIS_Transaksi_Matrix_Approver')) {
            return;
        }

        DB::table('N_HRIS_Transaksi_Matrix_Approver')->insert([
            'Id_Approval_Config' => $context['config']->Id,
            'Id_Approval_Config_Detail' => $context['detail']->Id,
            'Source_Table' => $context['source']['table'],
            'Jenis_Transaksi' => $jenisTransaksi,
            'Kode_Karyawan_Requester' => $kodeRequester,
            'Data_Sebelum' => json_encode($before),
            'Data_Sesudah' => json_encode($after),
            'Catatan' => $catatan,
            'Created_By' => $this->actorKode($user),
            'Created_At' => now(),
        ]);
    }

    // ========================================================================
    // SYSTEM ADMIN — CRUD APPROVAL CONFIG
    // ========================================================================

    public function createConfig(User $user, array $data): int
    {
        $this->assertSystemAdmin($user);

        $kodeConfig = strtoupper(trim((string) ($data['Kode_Config'] ?? '')));
        if ($kodeConfig === '') {
            abort(422, 'Kode_Config wajib diisi.');
        }

        $exists = DB::table('N_HRIS_Approval_Config')->where('Kode_Config', $kodeConfig)->exists();
        if ($exists) {
            abort(422, 'Kode_Config sudah digunakan: '.$kodeConfig);
        }

        $payload = $this->buildConfigPayload($data, $kodeConfig);

        return DB::table('N_HRIS_Approval_Config')->insertGetId($payload);
    }

    public function updateConfig(User $user, int $id, array $data): void
    {
        $this->assertSystemAdmin($user);

        $config = DB::table('N_HRIS_Approval_Config')->where('Id', $id)->first();
        if (! $config) {
            abort(404, 'Approval config tidak ditemukan.');
        }

        $kodeConfig = strtoupper(trim((string) ($data['Kode_Config'] ?? $config->Kode_Config)));
        if ($kodeConfig !== $config->Kode_Config) {
            $duplicate = DB::table('N_HRIS_Approval_Config')
                ->where('Kode_Config', $kodeConfig)
                ->where('Id', '!=', $id)
                ->exists();
            if ($duplicate) {
                abort(422, 'Kode_Config sudah digunakan: '.$kodeConfig);
            }
        }

        DB::table('N_HRIS_Approval_Config')->where('Id', $id)->update($this->buildConfigPayload($data, $kodeConfig));
    }

    private function buildConfigPayload(array $data, string $kodeConfig): array
    {
        $columns = collect($this->columnListingCached('N_HRIS_Approval_Config'));

        $payload = [
            'Kode_Config' => $kodeConfig,
            'Flag_Aktif' => in_array($data['Flag_Aktif'] ?? 'Y', ['Y', 'T'], true) ? $data['Flag_Aktif'] : 'Y',
        ];

        $optional = [
            'Nama_Config' => $data['Nama_Config'] ?? null,
            'Deskripsi' => $data['Deskripsi'] ?? null,
            'Skip_If_Requester_Level_Id' => $data['Skip_If_Requester_Level_Id'] ?? null,
        ];

        foreach ($optional as $column => $value) {
            if ($columns->contains($column) && array_key_exists($column, $data)) {
                $payload[$column] = $value;
            }
        }

        if ($columns->contains('Id_Grup_Fitur') && array_key_exists('Id_Grup_Fitur', $data)) {
            $payload['Id_Grup_Fitur'] = $data['Id_Grup_Fitur'] !== null ? (int) $data['Id_Grup_Fitur'] : null;
        }

        return $payload;
    }

    /**
     * Soft-disable config (Flag_Aktif='T'). Hard delete ditolak jika masih
     * punya detail aktif — konsisten pola deleteGrupFitur.
     */
    public function deleteConfig(User $user, int $id): void
    {
        $this->assertSystemAdmin($user);

        $config = DB::table('N_HRIS_Approval_Config')->where('Id', $id)->first();
        if (! $config) {
            abort(404, 'Approval config tidak ditemukan.');
        }

        $detailCount = DB::table('N_HRIS_Approval_Config_Detail')
            ->where('Id_Approval_Config', $id)
            ->where('Flag_Aktif', 'Y')
            ->count();

        if ($detailCount > 0) {
            abort(422, "Config masih memiliki {$detailCount} step aktif. Nonaktifkan step terlebih dahulu atau gunakan nonaktifkan config.");
        }

        DB::table('N_HRIS_Approval_Config')->where('Id', $id)->update(['Flag_Aktif' => 'T']);
    }

    public function bindConfigGrupFitur(User $user, int $id, ?int $grupId): void
    {
        $this->assertSystemAdmin($user);

        if (! $this->configHasGrupColumn()) {
            abort(422, 'Kolom Id_Grup_Fitur belum tersedia. Jalankan DDL menu_approval_matrix terlebih dahulu.');
        }

        $exists = DB::table('N_HRIS_Approval_Config')->where('Id', $id)->exists();
        if (! $exists) {
            abort(404, 'Approval config tidak ditemukan.');
        }

        if ($grupId !== null) {
            $grupExists = DB::table('N_HRIS_Master_Grup_Fitur')
                ->where('Id_Grup_Fitur', $grupId)
                ->where('Flag_Aktif', 'Y')
                ->exists();
            if (! $grupExists) {
                abort(422, 'Grup fitur tidak ditemukan atau tidak aktif.');
            }
        }

        DB::table('N_HRIS_Approval_Config')->where('Id', $id)->update(['Id_Grup_Fitur' => $grupId]);
    }

    // ========================================================================
    // SYSTEM ADMIN — CRUD CONFIG DETAIL (step)
    // ========================================================================

    public function createConfigDetail(User $user, int $idConfig, array $data): int
    {
        $this->assertSystemAdmin($user);

        $exists = DB::table('N_HRIS_Approval_Config')->where('Id', $idConfig)->exists();
        if (! $exists) {
            abort(404, 'Approval config tidak ditemukan.');
        }

        $payload = $this->buildConfigDetailPayload($idConfig, $data, null);

        return DB::table('N_HRIS_Approval_Config_Detail')->insertGetId($payload);
    }

    public function updateConfigDetail(User $user, int $id, array $data): void
    {
        $this->assertSystemAdmin($user);

        $detail = DB::table('N_HRIS_Approval_Config_Detail')->where('Id', $id)->first();
        if (! $detail) {
            abort(404, 'Approval config detail tidak ditemukan.');
        }

        $payload = $this->buildConfigDetailPayload((int) $detail->Id_Approval_Config, $data, $id);

        DB::table('N_HRIS_Approval_Config_Detail')->where('Id', $id)->update($payload);
    }

    public function deleteConfigDetail(User $user, int $id): void
    {
        $this->assertSystemAdmin($user);

        $detail = DB::table('N_HRIS_Approval_Config_Detail')->where('Id', $id)->first();
        if (! $detail) {
            abort(404, 'Approval config detail tidak ditemukan.');
        }

        DB::table('N_HRIS_Approval_Config_Detail')->where('Id', $id)->update(['Flag_Aktif' => 'T']);
    }

    private function buildConfigDetailPayload(int $idConfig, array $data, ?int $excludeId): array
    {
        $approvalOrder = (int) ($data['Approval_Order'] ?? 0);
        if ($approvalOrder < 1) {
            abort(422, 'Approval_Order minimal 1.');
        }

        $customApprover = trim((string) ($data['Custom_Approver_Kode'] ?? ''));
        $idJenisApproval = $data['Id_Jenis_Approval'] ?? null;

        if ($customApprover === '' && empty($idJenisApproval)) {
            abort(422, 'Step harus punya Custom_Approver_Kode ATAU Id_Jenis_Approval.');
        }
        if ($customApprover !== '' && ! empty($idJenisApproval)) {
            abort(422, 'Pilih salah satu: Custom_Approver_Kode atau Id_Jenis_Approval, tidak keduanya.');
        }

        if ($customApprover !== '') {
            $karyawanAktif = DB::table('Karyawan')
                ->where('Kode_Karyawan', $customApprover)
                ->where('Aktif', 'Y')
                ->exists();
            if (! $karyawanAktif) {
                abort(422, 'Custom approver tidak ditemukan atau tidak aktif: '.$customApprover);
            }
        }

        if (! empty($idJenisApproval)) {
            $jenisAktif = DB::table('N_HRIS_Jenis_Approval')
                ->where('Id_Jenis_Approval', (int) $idJenisApproval)
                ->where('Flag_Aktif', 'Y')
                ->exists();
            if (! $jenisAktif) {
                abort(422, 'Jenis approval tidak ditemukan atau tidak aktif.');
            }
        }

        $duplicateOrder = DB::table('N_HRIS_Approval_Config_Detail')
            ->where('Id_Approval_Config', $idConfig)
            ->where('Approval_Order', $approvalOrder)
            ->where('Flag_Aktif', 'Y')
            ->when($excludeId !== null, fn ($q) => $q->where('Id', '!=', $excludeId))
            ->when(($data['Flag_Aktif'] ?? 'Y') === 'Y', fn ($q) => $q)
            ->exists();
        if ($duplicateOrder && ($data['Flag_Aktif'] ?? 'Y') === 'Y') {
            abort(422, "Approval_Order {$approvalOrder} sudah dipakai step aktif lain di config ini.");
        }

        $columns = collect($this->columnListingCached('N_HRIS_Approval_Config_Detail'));

        $payload = [
            'Id_Approval_Config' => $idConfig,
            'Approval_Order' => $approvalOrder,
            'Custom_Approver_Kode' => $customApprover !== '' ? $customApprover : null,
            'Id_Jenis_Approval' => ! empty($idJenisApproval) ? (int) $idJenisApproval : null,
            'Order_Approver_Limit' => isset($data['Order_Approver_Limit']) && $data['Order_Approver_Limit'] !== null && $data['Order_Approver_Limit'] !== ''
                ? (int) $data['Order_Approver_Limit']
                : null,
            'Is_Mandatory' => in_array($data['Is_Mandatory'] ?? 'Y', ['Y', 'T'], true) ? $data['Is_Mandatory'] : 'Y',
            'Stop_If_Rejected' => in_array($data['Stop_If_Rejected'] ?? 'Y', ['Y', 'T'], true) ? $data['Stop_If_Rejected'] : 'Y',
            'Flag_Aktif' => in_array($data['Flag_Aktif'] ?? 'Y', ['Y', 'T'], true) ? $data['Flag_Aktif'] : 'Y',
        ];

        if ($columns->contains('Auto_Approve_After_Days') && array_key_exists('Auto_Approve_After_Days', $data)) {
            $payload['Auto_Approve_After_Days'] = $data['Auto_Approve_After_Days'] !== null && $data['Auto_Approve_After_Days'] !== ''
                ? (int) $data['Auto_Approve_After_Days']
                : null;
        }

        return array_filter(
            $payload,
            fn ($value, $key) => $columns->contains($key),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    // ========================================================================
    // SYSTEM ADMIN — CRUD JENIS APPROVAL
    // ========================================================================

    public function getJenisApproval(User $user, ?string $search = null): Collection
    {
        $this->assertSystemAdmin($user);

        $query = DB::table('N_HRIS_Jenis_Approval')->orderBy('Nama_Approval');

        if ($search !== null && trim($search) !== '') {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('Nama_Approval', 'like', "%{$search}%")
                    ->orWhere('Source_Reference', 'like', "%{$search}%");
            });
        }

        return $query->get()->map(function ($jenis) {
            $jenis->source_validation = $this->validateJenisSource($jenis->Source_Type, $jenis->Source_Reference);

            return $jenis;
        });
    }

    public function createJenisApproval(User $user, array $data): int
    {
        $this->assertSystemAdmin($user);

        $payload = $this->buildJenisApprovalPayload($data);
        $payload['Created_At'] = now();
        $payload['Created_By'] = $this->actorKode($user);

        return DB::table('N_HRIS_Jenis_Approval')->insertGetId($payload);
    }

    public function updateJenisApproval(User $user, int $id, array $data): void
    {
        $this->assertSystemAdmin($user);

        $exists = DB::table('N_HRIS_Jenis_Approval')->where('Id_Jenis_Approval', $id)->exists();
        if (! $exists) {
            abort(404, 'Jenis approval tidak ditemukan.');
        }

        $payload = $this->buildJenisApprovalPayload($data);
        $payload['Updated_At'] = now();
        $payload['Updated_By'] = $this->actorKode($user);

        DB::table('N_HRIS_Jenis_Approval')->where('Id_Jenis_Approval', $id)->update($payload);
    }

    public function deleteJenisApproval(User $user, int $id): void
    {
        $this->assertSystemAdmin($user);

        $usedCount = DB::table('N_HRIS_Approval_Config_Detail')
            ->where('Id_Jenis_Approval', $id)
            ->where('Flag_Aktif', 'Y')
            ->count();

        if ($usedCount > 0) {
            abort(422, "Jenis approval masih dipakai {$usedCount} step config aktif.");
        }

        DB::table('N_HRIS_Jenis_Approval')
            ->where('Id_Jenis_Approval', $id)
            ->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => $this->actorKode($user)]);
    }

    private function buildJenisApprovalPayload(array $data): array
    {
        $nama = trim((string) ($data['Nama_Approval'] ?? ''));
        if ($nama === '') {
            abort(422, 'Nama_Approval wajib diisi.');
        }

        $sourceType = strtoupper(trim((string) ($data['Source_Type'] ?? '')));
        $validTypes = ['TABLE', 'KPI_TABLE', 'GROUP_TABLE', 'DIRECT_EMPLOYEE', 'ROLE'];
        if (! in_array($sourceType, $validTypes, true)) {
            abort(422, 'Source_Type tidak valid. Pilihan: '.implode(', ', $validTypes));
        }

        return [
            'Nama_Approval' => $nama,
            'Source_Type' => $sourceType,
            'Source_Reference' => trim((string) ($data['Source_Reference'] ?? '')) ?: null,
            'Auto_Approve' => in_array($data['Auto_Approve'] ?? 'T', ['Y', 'T'], true) ? $data['Auto_Approve'] : 'T',
            'Keterangan' => $data['Keterangan'] ?? null,
            'Flag_Aktif' => in_array($data['Flag_Aktif'] ?? 'Y', ['Y', 'T'], true) ? $data['Flag_Aktif'] : 'Y',
        ];
    }

    /**
     * Status validasi source untuk ditampilkan di UI (non-blocking).
     */
    public function validateJenisSource(?string $sourceType, ?string $sourceReference): array
    {
        $sourceType = strtoupper(trim((string) $sourceType));
        $reference = trim((string) $sourceReference);

        if (in_array($sourceType, ['TABLE', 'KPI_TABLE'], true)) {
            $whitelist = (array) config('approval_matrix.editable_source_tables', []);
            if (! in_array($reference, $whitelist, true)) {
                return ['valid' => false, 'editable' => false, 'pesan' => 'Tabel di luar whitelist approval_matrix — assignment tidak bisa dikelola dari UI.'];
            }
            if (! $this->tableExistsCached($reference)) {
                return ['valid' => false, 'editable' => false, 'pesan' => 'Tabel tidak ditemukan di database.'];
            }

            return ['valid' => true, 'editable' => true, 'pesan' => null];
        }

        if ($sourceType === 'ROLE') {
            $exists = DB::table('N_HRIS_Role_Functional')->where('Role_Code', $reference)->where('Flag_Aktif', 'Y')->exists();

            return [
                'valid' => $exists,
                'editable' => false,
                'pesan' => $exists ? null : 'Role_Code tidak ditemukan di N_HRIS_Role_Functional.',
            ];
        }

        if ($sourceType === 'DIRECT_EMPLOYEE') {
            $exists = DB::table('Karyawan')->where('Kode_Karyawan', $reference)->where('Aktif', 'Y')->exists();

            return [
                'valid' => $exists,
                'editable' => false,
                'pesan' => $exists ? null : 'Kode karyawan tidak ditemukan atau tidak aktif.',
            ];
        }

        return ['valid' => true, 'editable' => false, 'pesan' => null];
    }

    public function getSourceTableOptions(): array
    {
        return collect((array) config('approval_matrix.editable_source_tables', []))
            ->map(fn ($table) => [
                'table' => $table,
                'exists' => $this->tableExistsCached($table),
            ])
            ->values()
            ->all();
    }

    private function columnListingCached(string $table): array
    {
        if (! array_key_exists($table, $this->schemaColumnsCache)) {
            $this->schemaColumnsCache[$table] = Schema::getColumnListing($table);
        }

        return $this->schemaColumnsCache[$table];
    }

    private function hasColumnCached(string $table, string $column): bool
    {
        $needle = strtolower($column);

        return collect($this->columnListingCached($table))
            ->contains(fn ($candidate) => strtolower((string) $candidate) === $needle);
    }

    private function tableExistsCached(string $table): bool
    {
        if (! array_key_exists($table, $this->tableExistsCache)) {
            $this->tableExistsCache[$table] = Schema::hasTable($table);
        }

        return $this->tableExistsCache[$table];
    }
}
