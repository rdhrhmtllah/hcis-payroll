<?php

namespace App\Services\SYSTEM\Governance\Authorization\Shared;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AuthorizationScopeResolver
 *
 * Resolves user's authorization scope for the scoped Super Admin feature.
 *
 * Returns 3 components:
 * 1. isSystemAdmin: bool - explicit identity flag
 * 2. manageableGroupIds: array|null - feature groups user owns (via roles)
 * 3. usablePageTypes: array|null - pages user can access/use
 *
 * Critical: Ownership (delegation) uses manageableGroupIds only.
 *           Usage (access) uses usablePageTypes.
 *           Never mix them up!
 */
class AuthorizationScopeResolver
{
    /**
     * Per-request cache only (in-memory, reset every HTTP request)
     * Prevents repeated scope queries inside one request without stale cross-request data.
     *
     * @var array<int, array{isSystemAdmin: bool, manageableGroupIds: array|null, usablePageTypes: array|null}>
     */
    protected static array $requestScopeCache = [];

    /**
     * Per-request decision cache for allow/deny checks.
     *
     * @var array<string, array{allowed: bool, reason: string, source: string, userId: int, contextCode: ?string, pageType: ?string, resourceType: ?string, resourceCode: ?string, action: int|string|null}>
     */
    protected static array $requestDecisionCache = [];

    /**
     * Per-request cache for table existence checks.
     * Menghindari query berat ke sys.tables yang diulang-ulang.
     *
     * @var array<string, bool>
     */
    protected static array $tableExistsCache = [];

    /**
     * Resolve user's complete authorization scope
     *
     * @return array{isSystemAdmin: bool, manageableGroupIds: array|null, usablePageTypes: array|null}
     */
    public static function resolve(User $user): array
    {
        // Per-request cache to avoid repeated queries in the same request lifecycle
        $userId = self::getUserId($user);
        if (! isset(self::$requestScopeCache[$userId])) {
            self::$requestScopeCache[$userId] = self::resolveUncached($user);
        }

        return self::$requestScopeCache[$userId];
    }

    /**
     * Generic resource evaluator.
     *
     * Input keys:
     * - contextCode
     * - pageType
     * - resourceType
     * - resourceCode
     * - action (int|string|null)
     */
    public static function evaluateAccess(User $user, array $input): array
    {
        $normalized = self::normalizeInput($input);
        $cacheKey = self::buildDecisionCacheKey($user, $normalized);

        if (isset(self::$requestDecisionCache[$cacheKey])) {
            return self::$requestDecisionCache[$cacheKey];
        }

        $cachedCrossRequest = self::loadCrossRequestDecisionCache($user, $cacheKey);
        if ($cachedCrossRequest !== null) {
            self::$requestDecisionCache[$cacheKey] = $cachedCrossRequest;

            return $cachedCrossRequest;
        }

        $result = self::resolveDecision($user, $normalized);

        self::$requestDecisionCache[$cacheKey] = $result;
        self::persistCrossRequestDecisionCache($cacheKey, $result);
        AuthorizationAuditLogger::logDecision($result);

        return $result;
    }

    public static function authorizeResource(User $user, array $input): bool
    {
        return self::evaluateAccess($user, $input)['allowed'];
    }

    /**
     * Unified visibility payload for menus and tabs.
     */
    public static function resolveVisibility(User $user, array $input = []): array
    {
        $normalized = self::normalizeInput($input);
        $scope = self::resolve($user);
        $tabs = self::resolveTabVisibility($user, $normalized['contextCode'], $normalized['pageType'], $scope);

        $menuQuery = DB::table('N_HRIS_Menus')
            ->whereNotNull('Jenis_page')
            ->select('Jenis_page', 'Nama_Menu', 'Header_Label')
            ->orderBy('Header_Label')
            ->orderBy('Nama_Menu');

        // Keep compatibility across environments where active-flag column naming differs.
        if (SkemaTersedia::kolom('N_HRIS_Menus', 'Flag_Aktif')) {
            $menuQuery->where('Flag_Aktif', 'Y');
        } elseif (SkemaTersedia::kolom('N_HRIS_Menus', 'Flag_Active')) {
            $menuQuery->where('Flag_Active', 'Y');
        }

        $menuRows = $menuQuery->get();

        $visibleMenus = [];
        $deniedMenus = [];

        foreach ($menuRows as $menu) {
            $pageKey = self::normalizePageKey((string) $menu->Jenis_page);
            $allowed = self::canAccessPage($user, $pageKey);

            $item = [
                'pageType' => $pageKey,
                'menuName' => $menu->Nama_Menu,
                'headerName' => $menu->Header_Label,
            ];

            if ($allowed) {
                $visibleMenus[] = $item;
            } else {
                $deniedMenus[] = $item;
            }
        }

        return [
            'visibleTabs' => array_keys(array_filter($tabs)),
            'allTabs' => array_keys($tabs),
            'tabVisibility' => $tabs,
            'visibleMenus' => $visibleMenus,
            'deniedMenus' => $deniedMenus,
        ];
    }

    /**
     * Standard capability payload for frontend consumers.
     */
    public static function buildCapabilityPayload(User $user, array $input = []): array
    {
        $normalized = self::normalizeInput($input);
        $scope = self::resolve($user);

        $canView = self::authorizeResource($user, [
            'contextCode' => $normalized['contextCode'],
            'pageType' => $normalized['pageType'],
            'resourceType' => $normalized['resourceType'],
            'resourceCode' => $normalized['resourceCode'],
            'action' => 'VIEW',
        ]);

        $canEdit = self::authorizeResource($user, [
            'contextCode' => $normalized['contextCode'],
            'pageType' => $normalized['pageType'],
            'resourceType' => $normalized['resourceType'],
            'resourceCode' => $normalized['resourceCode'],
            'action' => 'EDIT',
        ]);

        $canAssign = self::authorizeResource($user, [
            'contextCode' => $normalized['contextCode'],
            'pageType' => $normalized['pageType'],
            'resourceType' => $normalized['resourceType'],
            'resourceCode' => $normalized['resourceCode'],
            'action' => 'ASSIGN',
        ]);

        return [
            'canView' => $canView,
            'canEdit' => $canEdit,
            'canAssign' => $canAssign,
            'canDelegate' => ! empty($scope['manageableGroupIds']) || (bool) $scope['isSystemAdmin'],
            'allowedResources' => self::getAllowedResourceCodes(
                $user,
                $normalized['contextCode'],
                $normalized['pageType'],
                $normalized['resourceType'],
            ),
        ];
    }

    /**
     * Metadata payload intended for page bootstrapping in controllers.
     */
    public static function buildScopeMetadata(User $user, array $input = []): array
    {
        $scope = self::resolve($user);
        $scopeTier = self::getScopeTier($user);
        $adminTier = self::mapScopeTierToAdminTier($scopeTier);
        $visibility = self::resolveVisibility($user, $input);
        $normalized = self::normalizeInput($input);

        return [
            'adminTier' => $adminTier,
            'scopeTier' => $scopeTier,
            'isSystemAdmin' => $scope['isSystemAdmin'],
            'manageableGroupIds' => $scope['manageableGroupIds'],
            'usablePageTypes' => $scope['usablePageTypes'],
            'visibleTabs' => $visibility['visibleTabs'],
            'allTabs' => $visibility['allTabs'],
            'tabVisibility' => $visibility['tabVisibility'],
            'visibleMenus' => $visibility['visibleMenus'],
            'deniedMenus' => $visibility['deniedMenus'],
            'capabilities' => self::buildCapabilityPayload($user, [
                'contextCode' => $normalized['contextCode'],
                'pageType' => $normalized['pageType'],
                'resourceType' => 'tab',
            ]),
        ];
    }

    public static function buildPageAuthorizationPayload(User $user, array $input = []): array
    {
        $normalized = self::normalizeInput($input);
        $scope = self::resolve($user);

        $actionCodes = self::getActionCodes();

        $actions = [];

        foreach ($actionCodes as $actionCode) {
            $actions[$actionCode] = self::authorizeResource($user, [
                'contextCode' => $normalized['contextCode'],
                'pageType' => $normalized['pageType'],
                'resourceType' => $normalized['resourceType'],
                'resourceCode' => $normalized['resourceCode'],
                'action' => $actionCode,
            ]);
        }

        return [
            'contextCode' => $normalized['contextCode'],
            'pageType' => $normalized['pageType'],
            'resourceType' => $normalized['resourceType'],
            'resourceCode' => $normalized['resourceCode'],

            'scopeTier' => self::getScopeTier($user),
            'isSystemAdmin' => $scope['isSystemAdmin'],
            'manageableGroupIds' => $scope['manageableGroupIds'],
            'usablePageTypes' => $scope['usablePageTypes'],

            'actions' => $actions,

            'capabilities' => [
                'canCreate' => $actions['CREATE'] ?? false,
                'canView' => $actions['VIEW'] ?? false,
                'canEdit' => $actions['EDIT'] ?? false,
                'canDelete' => $actions['DELETE'] ?? false,
                'canApprove' => $actions['APPROVE'] ?? false,
                'canPrint' => $actions['PRINT'] ?? false,
                'canSummary' => $actions['SUMMARY'] ?? false,
                'canDelegate' => ! empty($scope['manageableGroupIds']) || (bool) $scope['isSystemAdmin'],
            ],

            'allowedResources' => self::getAllowedResourceCodes(
                $user,
                $normalized['contextCode'],
                $normalized['pageType'],
                $normalized['resourceType'],
            ),
        ];
    }

    private static function getActionCodes(): array
    {
        if (! self::cachedTableExists('N_HRIS_Klasifikasi_Aksi')) {
            return ['CREATE', 'VIEW', 'EDIT', 'DELETE', 'APPROVE', 'PRINT', 'SUMMARY'];
        }

        return DB::table('N_HRIS_Klasifikasi_Aksi')
            ->orderBy('Id_Klasifikasi_Actions')
            ->pluck('Nama_Aksi')
            ->map(fn ($action) => self::normalizeActionCode((string) $action))
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    public static function canSeeMenu(User $user, string $pageType, bool $isGlobalMenu = false): bool
    {
        if ($isGlobalMenu) {
            return true;
        }

        return self::canAccessPage($user, $pageType);
    }

    /**
     * Resolve scope WITHOUT caching (internal use)
     */
    private static function resolveUncached(User $user): array
    {
        // Layer 1: Check identity flag
        if (self::isSystemAdmin($user)) {
            // System Admin: full access, no restrictions
            return [
                'isSystemAdmin' => true,
                'manageableGroupIds' => null, // null = all
                'usablePageTypes' => null, // null = all
            ];
        }

        // Layer 2: Get ownership - feature groups user owns via roles
        $manageableGroupIds = self::getManageableGroupIds($user);

        // Layer 3: Get usage - pages user can access
        $usablePageTypes = self::getUsablePageTypes($user, $manageableGroupIds);

        return [
            'isSystemAdmin' => false,
            'manageableGroupIds' => $manageableGroupIds,
            'usablePageTypes' => $usablePageTypes,
        ];
    }

    /**
     * Get feature group IDs that user owns via role assignments
     * Source: N_HRIS_Role_User → N_HRIS_Role_Functional.Id_Grup_Fitur
     *
     * This determines what user can DELEGATE to others (ownership authority)
     */
    private static function getManageableGroupIds(User $user): array
    {
        $userId = self::getUserId($user);

        // Fetch user's active role IDs
        $roleIds = DB::table('N_HRIS_Role_User')
            ->where('UserId_Web', $userId)
            ->where('Flag_Aktif', 'Y')
            ->tap(fn ($q) => \App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac::saringPenugasanAktif($q))
            ->distinct()
            ->pluck('Id_Role')
            ->filter()
            ->values()
            ->toArray();

        if (empty($roleIds)) {
            return [];
        }

        // Fetch which of these roles have active, allowed resource access entries
        // for any administrative/management page (e.g., menuApprovalMatrixPage, systemAuthManagerPage).
        // If a role doesn't have resource access, it is a regular/operational role, so it does not grant group management rights.
        $adminRoleIds = DB::table('N_HRIS_Role_Resource_Access')
            ->whereIn('Id_Role_Functional', $roleIds)
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Diizinkan', 'Y')
            ->whereIn('Jenis_page', ['menuApprovalMatrixPage', 'systemAuthManagerPage'])
            ->distinct()
            ->pluck('Id_Role_Functional')
            ->toArray();

        // If we found specific admin roles, we restrict the manageable groups to only
        // those associated with these admin roles.
        // This prevents standard operational roles (which have no resource access entries)
        // from leaking their group IDs into the manageable groups list.
        $targetRoleIds = ! empty($adminRoleIds) ? $adminRoleIds : $roleIds;

        $groupIds = DB::table('N_HRIS_Role_Functional')
            ->whereIn('Id_Role', $targetRoleIds)
            ->where('Flag_Aktif', 'Y')
            ->distinct()
            ->pluck('Id_Grup_Fitur')
            ->filter() // Remove null values
            ->values()
            ->toArray();

        return $groupIds;
    }

    /**
     * Get page types user can access/use
     * Source:
     *   1. Pages from owned groups (via role mapping)
     *   2. Plus: Explicit HRIS_Page_Access with Flag_Aktif='Y' only
     *
     * This determines what user can ACCESS/USE (usage authority)
     * NOT for delegation - that uses manageableGroupIds only!
     */
    private static function getUsablePageTypes(User $user, array $manageableGroupIds): array
    {
        $pageTypes = [];

        // 1. Get pages from owned groups (via role mapping)
        //
        // Hanya baris yang MEMBERI HIBAH (DDL-2026-110). Inilah jalur yang
        // dipakai sidebar (InertiaShellService::menuIsVisible -> canAccessPage);
        // tanpa penyaring ini pemegang ADMIN_PERMIT tetap melihat seluruh menu
        // Visitor walau gerbang rutenya (User::memilikiAksesBaru) sudah menolak.
        // Penyaring kembarannya ada di User::memilikiAksesBaru dan wajib
        // berubah bersamaan.
        if (! empty($manageableGroupIds)) {
            $pageTypes = DB::table('N_HRIS_Grup_Fitur_Map')
                ->whereIn('Id_Grup_Fitur', $manageableGroupIds)
                ->where('Flag_Hibah_Role', '=', 'Y')
                ->distinct()
                ->pluck('Jenis_page')
                ->toArray();

            // RBAC Fase 0: kembaran penyaring di User::memilikiAksesBaru.
            $lewatAdmin = HibahTataKelola::halamanLewatPeranAdmin(self::getUserId($user));
            $pageTypes = array_values(array_filter(
                $pageTypes,
                fn ($p) => ! HibahTataKelola::apakahHalamanTataKelola((string) $p)
                    || in_array(HibahTataKelola::normalisasi((string) $p), $lewatAdmin, true),
            ));
        }

        // 2. Get explicit page access (legacy, still active, Flag_Aktif='Y' ONLY)
        $explicitPages = DB::table('HRIS_Page_Access')
            ->where('UserID_Web', self::getUserId($user))
            ->where('Flag_Aktif', 'Y')
            ->when(\App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac::punya('kedaluwarsa_grant'), fn ($q) => $q->where(fn ($w) => $w->whereNull('Berlaku_Sampai')->orWhere('Berlaku_Sampai', '>', now())))
            ->distinct()
            ->pluck('Jenis_page')
            ->toArray();

        // 3. Merge both sources
        $pageTypes = array_unique(array_merge($pageTypes, $explicitPages));

        return $pageTypes;
    }

    // ========================================================================
    // Delegation Validation Helpers
    // ========================================================================

    /**
     * Check if user can delegate (manage) a specific role
     *
     * Used before: assigning role to user, modifying role, deleting role
     *
     * Validates against OWNERSHIP layer (manageableGroupIds) only
     * NOT against usage layer!
     */
    public static function canDelegateRole(User $user, int $roleId): bool
    {
        $scope = self::resolve($user);

        // System Admin can delegate anything
        if ($scope['isSystemAdmin']) {
            return true;
        }

        // Super Admin: must own the group
        if (empty($scope['manageableGroupIds'])) {
            return false; // scoped but no groups
        }

        // Get role's group
        $role = DB::table('N_HRIS_Role_Functional')->where('Id_Role', $roleId)->where('Flag_Aktif', 'Y')->first();

        if (! $role) {
            return false; // role not found or inactive
        }

        // Check if role's group is in user's managed groups
        return in_array($role->Id_Grup_Fitur, $scope['manageableGroupIds'], true);
    }

    /**
     * Check if user can delegate (manage) a specific page
     *
     * Used before: assigning page access, modifying permissions, distributing access
     *
     * Validates against OWNERSHIP layer (groups) only
     * NOT against usage layer!
     *
     * @param  int|string  $pageId
     */
    public static function canDelegatePage(User $user, $pageId): bool
    {
        $scope = self::resolve($user);

        // System Admin can delegate anything
        if ($scope['isSystemAdmin']) {
            return true;
        }

        // Super Admin: must own the group
        if (empty($scope['manageableGroupIds'])) {
            return false; // scoped but no groups
        }

        // Get page's group by page type or page access id
        if (is_numeric($pageId)) {
            $pageGroup = DB::table('HRIS_Page_Access as pa')
                ->join('N_HRIS_Grup_Fitur_Map as gfm', 'gfm.Jenis_page', '=', 'pa.Jenis_page')
                ->where('pa.Id_Page_Access', (int) $pageId)
                ->select('gfm.Id_Grup_Fitur')
                ->first();
        } else {
            $pageGroup = DB::table('N_HRIS_Grup_Fitur_Map')
                ->where('Jenis_page', $pageId)
                ->select('Id_Grup_Fitur')
                ->first();
        }

        if (! $pageGroup) {
            return false; // page not found
        }

        // Check if page's group is in user's managed groups
        return in_array($pageGroup->Id_Grup_Fitur, $scope['manageableGroupIds'], true);
    }

    /**
     * Check if user can access (use) a specific page
     *
     * Used for: displaying page in menu, allowing page access
     *
     * Validates against USAGE layer (usablePageTypes)
     * This is for ACCESS checks, not DELEGATION checks!
     */
    /**
     * Lapis halaman (authorize.resource, ApprovalMatrixService, canSeeMenu).
     * RBAC Fase 4: satu keputusan dengan gerbang route — KeputusanAkses::boleh,
     * yang mengikuti mode modul. Di mode LAMA hasilnya sama dengan
     * usablePageTypes (plus menu global, yang memang terbuka bagi semua).
     */
    public static function canAccessPage(User $user, string $pageType): bool
    {
        return app(KeputusanAkses::class)->boleh($user, $pageType);
    }

    /**
     * Classify user in neutral scope tier.
     *
     * SYSTEM: identity-level global access (system admin)
     * SCOPED: has explicit ownership scope (manageable groups)
     * UNSCOPED: no ownership scope
     */
    public static function getScopeTier(User $user): string
    {
        $scope = self::resolve($user);

        if ($scope['isSystemAdmin']) {
            return 'SYSTEM';
        }

        if (! empty($scope['manageableGroupIds'])) {
            return 'SCOPED';
        }

        return 'UNSCOPED';
    }

    /**
     * Whether user is allowed to access authorization management area.
     */
    public static function canManageAuthorization(User $user): bool
    {
        $tier = self::getScopeTier($user);

        if (in_array($tier, ['SYSTEM', 'SCOPED'], true)) {
            return true;
        }

        // Backward-compatible access aliases for authorization manager entry pages.
        // This prevents false 403 when page access is already granted but role ownership mapping
        // is not fully synchronized yet.
        $entryPages = ['superAdminManajemenPeranPage', 'systemAuthManagerPage'];
        foreach ($entryPages as $page) {
            if (self::canAccessPage($user, $page)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear cached scope for a user
     *
     * Call this after:
     * - User role assignment changes
     * - User page access changes
     * - System Admin flag changes
     */
    public static function clearCache(User $user): void
    {
        $userId = self::getUserId($user);
        unset(self::$requestScopeCache[$userId]);

        $versionKey = self::crossRequestVersionKey($userId);
        $version = (int) Cache::get($versionKey, 1);
        Cache::forever($versionKey, $version + 1);

        foreach (array_keys(self::$requestDecisionCache) as $key) {
            if (str_starts_with($key, 'u'.$userId.':')) {
                unset(self::$requestDecisionCache[$key]);
            }
        }
    }

    /**
     * Resolve user id across schema variants
     */
    private static function getUserId(User $user): int
    {
        return (int) ($user->Id_Users ?? ($user->id_user ?? ($user->id ?? 0)));
    }

    /**
     * Resolve system admin flag across schema variants
     */
    private static function isSystemAdmin(User $user): bool
    {
        $flag = $user->Is_System_Admin ?? ($user->is_system_admin ?? null);

        return $flag === 'Y';
    }

    /**
     * Clear cached scope for all users
     *
     * Call this after bulk role/permission changes
     */
    public static function clearAllCache(): void
    {
        self::$requestScopeCache = [];
        self::$requestDecisionCache = [];
        self::$tableExistsCache = [];
    }

    /**
     * Clear access decision cache for a user while keeping scope cache intact.
     */
    public static function clearAccessDecisionCache(User $user): void
    {
        $userId = self::getUserId($user);
        $versionKey = self::crossRequestVersionKey($userId);
        $version = (int) Cache::get($versionKey, 1);
        Cache::forever($versionKey, $version + 1);

        foreach (array_keys(self::$requestDecisionCache) as $key) {
            if (str_starts_with($key, 'u'.$userId.':')) {
                unset(self::$requestDecisionCache[$key]);
            }
        }
    }

    private static function resolveDecision(User $user, array $normalized): array
    {
        $userId = self::getUserId($user);

        if (! empty($normalized['resourceCodeProvided'])) {
            app(AuthorizationResourceRegistryService::class)->assertRegisteredIfGoverned(
                $user,
                $normalized['pageType'],
                $normalized['resourceType'],
                $normalized['resourceCode'],
                'authorize-resource',
            );
        }

        if (self::isSystemAdmin($user)) {
            return self::decision($userId, $normalized, true, 'system-admin', 'identity-layer');
        }

        if (! empty($normalized['pageType']) && ! self::canAccessPage($user, $normalized['pageType'])) {
            return self::decision($userId, $normalized, false, 'page-not-accessible', 'usage-layer');
        }

        if (! self::isResourceAllowed($user, $normalized)) {
            return self::decision($userId, $normalized, false, 'resource-denied', 'resource-layer');
        }

        if (! self::isActionAllowed($user, $normalized)) {
            return self::decision($userId, $normalized, false, 'action-denied', 'action-layer');
        }

        return self::decision($userId, $normalized, true, 'allowed', 'unified-evaluator');
    }

    private static function normalizeInput(array $input): array
    {
        $defaultContext = (string) config('authorization.resource_access.default_context', 'admin');
        $defaultPage = (string) config('authorization.resource_access.default_page', 'systemAuthManagerPage');

        $pageType = self::normalizePageKey((string) ($input['pageType'] ?? $defaultPage));
        $resourceCode = self::normalizeResourceCode((string) ($input['resourceCode'] ?? $pageType));
        $action = $input['action'] ?? null;

        return [
            'contextCode' => strtolower(trim((string) ($input['contextCode'] ?? $defaultContext))),
            'pageType' => $pageType,
            'resourceType' => strtolower(trim((string) ($input['resourceType'] ?? 'tab'))),
            'resourceCode' => $resourceCode,
            'resourceCodeProvided' => array_key_exists('resourceCode', $input) && trim((string) $input['resourceCode']) !== '',
            'action' => is_string($action) ? self::normalizeActionCode($action) : $action,
        ];
    }

    private static function normalizePageKey(string $pageType): string
    {
        $trimmed = trim($pageType);
        if ($trimmed === '') {
            return '';
        }

        $aliases = (array) config('authorization.aliases.pages', []);
        $key = strtolower($trimmed);

        return (string) ($aliases[$key] ?? $trimmed);
    }

    private static function normalizeResourceCode(string $resourceCode): string
    {
        $trimmed = trim($resourceCode);
        if ($trimmed === '') {
            return '';
        }

        $aliases = (array) config('authorization.aliases.resources', []);
        $key = strtolower($trimmed);

        return (string) ($aliases[$key] ?? $trimmed);
    }

    /**
     * Return canonical and legacy names for one capability lookup.
     * Database rows using UPDATE/INSERT remain valid while new code uses
     * EDIT/CREATE consistently.
     */
    private static function actionCompatibilityCodes(string $canonicalAction): array
    {
        $canonicalAction = strtoupper(trim($canonicalAction));
        $aliases = (array) config('authorization.aliases.actions', []);
        $codes = [$canonicalAction];

        foreach ($aliases as $alias => $target) {
            if (strtoupper(trim((string) $target)) === $canonicalAction) {
                $codes[] = strtoupper(trim((string) $alias));
            }
        }

        return array_values(array_unique(array_filter($codes)));
    }

    private static function normalizeActionCode(string $action): string
    {
        $trimmed = strtoupper(trim($action));
        if ($trimmed === '') {
            return '';
        }

        $aliases = (array) config('authorization.aliases.actions', []);
        $key = strtolower($trimmed);

        return strtoupper((string) ($aliases[$key] ?? $trimmed));
    }

    private static function resolveTabVisibility(User $user, string $contextCode, string $pageType, array $scope): array
    {
        $defaultTabs = (array) config('authorization.resource_access.default_tab_codes', []);
        if (empty($defaultTabs)) {
            $defaultTabs = ['dashboard', 'users', 'master', 'governance', 'distribution', 'matrix', 'monitoring'];
        }

        $fromResource = self::resolveTabVisibilityFromResourceAccess($user, $contextCode, $pageType, $defaultTabs);
        if ($fromResource !== null) {
            return $fromResource;
        }

        return self::buildLegacyTabVisibility($scope, $defaultTabs);
    }

    private static function resolveTabVisibilityFromResourceAccess(
        User $user,
        string $contextCode,
        string $pageType,
        array $defaultTabs,
    ): ?array {
        if (! self::cachedTableExists('N_HRIS_Role_Resource_Access')) {
            return null;
        }

        $userRoleIds = self::getUserRoleIds($user);
        if (empty($userRoleIds)) {
            return null;
        }

        $tabCodes = DB::table('N_HRIS_Role_Resource_Access as rra')
            ->where('rra.Context_Code', $contextCode)
            ->where('rra.Jenis_page', $pageType)
            ->where('rra.Resource_Type', 'tab')
            ->where('rra.Flag_Aktif', 'Y')
            ->where('rra.Flag_Diizinkan', 'Y')
            ->whereIn('rra.Id_Role_Functional', $userRoleIds)
            ->orderBy('rra.Sort_Order')
            ->orderBy('rra.Resource_Code')
            ->pluck('rra.Resource_Code')
            ->map(static fn ($code) => strtolower(trim((string) $code)))
            ->unique()
            ->values()
            ->toArray();

        if (empty($tabCodes)) {
            return null;
        }

        $visibility = array_fill_keys($defaultTabs, false);
        foreach ($tabCodes as $tabCode) {
            if (array_key_exists($tabCode, $visibility)) {
                $visibility[$tabCode] = true;
            }
        }

        return $visibility;
    }

    private static function buildLegacyTabVisibility(array $scope, array $defaultTabs): array
    {
        $visibility = array_fill_keys($defaultTabs, false);
        $isSystemAdmin = (bool) ($scope['isSystemAdmin'] ?? false);
        $hasScopedGroups = ! empty($scope['manageableGroupIds']);

        foreach ($visibility as $tab => $value) {
            if (in_array($tab, ['dashboard', 'users'], true)) {
                $visibility[$tab] = true;

                continue;
            }

            if (in_array($tab, ['distribution', 'matrix'], true)) {
                $visibility[$tab] = $isSystemAdmin || $hasScopedGroups;

                continue;
            }

            $visibility[$tab] = $isSystemAdmin;
        }

        return $visibility;
    }

    private static function isResourceAllowed(User $user, array $normalized): bool
    {
        if (! self::cachedTableExists('N_HRIS_Role_Resource_Access')) {
            return true;
        }

        $roleIds = self::getUserRoleIds($user);
        if (empty($roleIds)) {
            return false;
        }

        $rows = DB::table('N_HRIS_Role_Resource_Access as rra')
            ->whereIn('rra.Id_Role_Functional', $roleIds)
            ->where('rra.Context_Code', $normalized['contextCode'])
            ->where('rra.Jenis_page', $normalized['pageType'])
            ->where('rra.Resource_Type', $normalized['resourceType'])
            ->where('rra.Resource_Code', $normalized['resourceCode'])
            ->where('rra.Flag_Aktif', 'Y')
            ->pluck('rra.Flag_Diizinkan')
            ->map(static fn ($flag) => strtoupper(trim((string) $flag)))
            ->values();

        if ($rows->isEmpty()) {
            // Check if this page has granular resource records in N_HRIS_Role_Resource_Access
            $pageHasResources = DB::table('N_HRIS_Role_Resource_Access')
                ->where('Context_Code', $normalized['contextCode'])
                ->where('Jenis_page', $normalized['pageType'])
                ->where('Flag_Aktif', 'Y')
                ->exists();

            if ($pageHasResources) {
                return false;
            }

            return self::canAccessPage($user, $normalized['pageType']);
        }

        return $rows->contains('Y');
    }

    private static function isActionAllowed(User $user, array $normalized): bool
    {
        $action = $normalized['action'];
        if ($action === null || $action === '') {
            return true;
        }

        // RBAC Fase 5: modul BARU — aksi dari matriks hak peran (KeputusanAkses).
        if (! is_numeric($action) && ! empty($normalized['pageType'])
            && app(ModeAkses::class)->modeHalaman($normalized['pageType']) === ModeAkses::BARU) {
            return app(KeputusanAkses::class)
                ->boleh($user, $normalized['pageType'], self::normalizeActionCode((string) $action));
        }

        $roleIds = self::getUserRoleIds($user);
        if (empty($roleIds)) {
            return false;
        }

        if (is_numeric($action)) {
            return self::isActionIdAllowed((int) $action, $user, $normalized['pageType']);
        }

        $actionCode = self::normalizeActionCode((string) $action);
        $actionCodes = self::actionCompatibilityCodes($actionCode);

        $hasPermissionCode = DB::table('N_HRIS_Role_Permission')
            ->whereIn('Id_Role', $roleIds)
            ->whereIn(DB::raw("UPPER(COALESCE(Permission_Code, ''))"), $actionCodes)
            ->exists();

        if ($hasPermissionCode) {
            return true;
        }

        if (! self::cachedTableExists('N_HRIS_Role_Menu_Access')) {
            return false;
        }

        return DB::table('N_HRIS_Role_Menu_Access as rma')
            ->join('N_HRIS_Klasifikasi_Aksi as ka', 'ka.Id_Klasifikasi_Actions', '=', 'rma.Id_Aksi')
            ->join('HRIS_Page_Access as pa', 'pa.Id_Page_Access', '=', 'rma.Id_Page_Access')
            ->where('pa.UserID_Web', self::getUserId($user))
            ->where('pa.Flag_Aktif', 'Y')
            ->where('pa.Jenis_page', $normalized['pageType'])
            ->where('rma.Flag_Aktif', 'Y')
            ->where('rma.Flag_Diizinkan', 'Y')
            ->whereIn(DB::raw("UPPER(COALESCE(ka.Nama_Aksi, ''))"), $actionCodes)
            ->exists();
    }

    private static function isActionIdAllowed(int $actionId, User $user, string $pageType): bool
    {
        if (! SkemaTersedia::tabel('N_HRIS_Role_Menu_Access')) {
            return false;
        }

        return DB::table('N_HRIS_Role_Menu_Access as rma')
            ->join('HRIS_Page_Access as pa', 'pa.Id_Page_Access', '=', 'rma.Id_Page_Access')
            ->where('pa.UserID_Web', self::getUserId($user))
            ->where('pa.Flag_Aktif', 'Y')
            ->where('pa.Jenis_page', $pageType)
            ->where('rma.Id_Aksi', $actionId)
            ->where('rma.Flag_Aktif', 'Y')
            ->where('rma.Flag_Diizinkan', 'Y')
            ->exists();
    }

    private static function getAllowedResourceCodes(
        User $user,
        string $contextCode,
        string $pageType,
        string $resourceType,
    ): array {
        if (! self::cachedTableExists('N_HRIS_Role_Resource_Access')) {
            return [];
        }

        if (self::isSystemAdmin($user)) {
            return DB::table('N_HRIS_Role_Resource_Access')
                ->where('Context_Code', $contextCode)
                ->where('Jenis_page', $pageType)
                ->where('Resource_Type', $resourceType)
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Diizinkan', 'Y')
                ->orderBy('Sort_Order')
                ->orderBy('Resource_Code')
                ->pluck('Resource_Code')
                ->map(static fn ($code) => trim((string) $code))
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        }

        $roleIds = self::getUserRoleIds($user);
        if (empty($roleIds)) {
            return [];
        }

        return DB::table('N_HRIS_Role_Resource_Access')
            ->whereIn('Id_Role_Functional', $roleIds)
            ->where('Context_Code', $contextCode)
            ->where('Jenis_page', $pageType)
            ->where('Resource_Type', $resourceType)
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Diizinkan', 'Y')
            ->orderBy('Sort_Order')
            ->orderBy('Resource_Code')
            ->pluck('Resource_Code')
            ->map(static fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    private static function getUserRoleIds(User $user): array
    {
        return DB::table('N_HRIS_Role_User')
            ->where('UserId_Web', self::getUserId($user))
            ->where('Flag_Aktif', 'Y')
            ->tap(fn ($q) => \App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac::saringPenugasanAktif($q))
            ->distinct()
            ->pluck('Id_Role')
            ->filter()
            ->values()
            ->toArray();
    }

    private static function decision(
        int $userId,
        array $normalized,
        bool $allowed,
        string $reason,
        string $source,
    ): array {
        return [
            'userId' => $userId,
            'contextCode' => $normalized['contextCode'],
            'pageType' => $normalized['pageType'],
            'resourceType' => $normalized['resourceType'],
            'resourceCode' => $normalized['resourceCode'],
            'action' => $normalized['action'],
            'allowed' => $allowed,
            'reason' => $reason,
            'source' => $source,
        ];
    }

    private static function buildDecisionCacheKey(User $user, array $normalized): string
    {
        $userId = self::getUserId($user);
        $serialized = implode('|', [
            $normalized['contextCode'],
            $normalized['pageType'],
            $normalized['resourceType'],
            $normalized['resourceCode'],
            (string) ($normalized['action'] ?? ''),
            // RBAC: mode modul halaman ikut kunci — pindah mode tak memakai keputusan basi.
            $normalized['pageType'] !== '' ? app(ModeAkses::class)->modeHalaman($normalized['pageType']) : '',
        ]);

        return 'u'.$userId.':'.sha1($serialized);
    }

    private static function persistCrossRequestDecisionCache(string $cacheKey, array $result): void
    {
        if (! config('authorization.cache.cross_request_enabled', true)) {
            return;
        }

        $ttlSeconds = (int) config('authorization.cache.ttl_seconds', 60);
        if ($ttlSeconds <= 0) {
            return;
        }

        $userId = (int) ($result['userId'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $version = (int) Cache::get(self::crossRequestVersionKey($userId), 1);
        $fullCacheKey = self::crossRequestDecisionKey($userId, $version, $cacheKey);
        Cache::put($fullCacheKey, $result, now()->addSeconds($ttlSeconds));
    }

    private static function loadCrossRequestDecisionCache(User $user, string $cacheKey): ?array
    {
        if (! config('authorization.cache.cross_request_enabled', true)) {
            return null;
        }

        $userId = self::getUserId($user);
        if ($userId <= 0) {
            return null;
        }

        $version = (int) Cache::get(self::crossRequestVersionKey($userId), 1);
        $fullCacheKey = self::crossRequestDecisionKey($userId, $version, $cacheKey);
        $value = Cache::get($fullCacheKey);

        return is_array($value) ? $value : null;
    }

    private static function crossRequestVersionKey(int $userId): string
    {
        return 'authz:ver:'.$userId;
    }

    private static function crossRequestDecisionKey(int $userId, int $version, string $cacheKey): string
    {
        return 'authz:decision:'.$userId.':'.$version.':'.$cacheKey;
    }

    private static function mapScopeTierToAdminTier(string $scopeTier): string
    {
        if ($scopeTier === 'SYSTEM') {
            return 'SYSTEM_ADMIN';
        }

        if ($scopeTier === 'SCOPED') {
            return 'SUPER_ADMIN';
        }

        return 'ADMIN_BIASA';
    }

    /**
     * Cached table existence check.
     * Menghindari query berat ke sys.tables yang diulang-ulang per-request.
     * Cache berlaku selama 1 request lifecycle.
     */
    private static function cachedTableExists(string $table): bool
    {
        if (! isset(self::$tableExistsCache[$table])) {
            self::$tableExistsCache[$table] = Schema::hasTable($table);
        }

        return self::$tableExistsCache[$table];
    }
}
