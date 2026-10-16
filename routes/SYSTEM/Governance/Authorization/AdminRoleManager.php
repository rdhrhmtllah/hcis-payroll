<?php

use App\Http\Controllers\SYSTEM\Governance\Authorization\AdminRoleManagerController;
use App\Http\Controllers\SYSTEM\Governance\Authorization\SystemAuthController;
use Illuminate\Support\Facades\Route;

// Admin Role Manager (SYSTEM_ADMIN only)
Route::middleware([
    'auth',
    'check.jabatan:superAdminManajemenPeranPage,systemAuthManagerPage',
    'audit.admin.transaksi',
    'authorize.resource',
])->group(function () {
    Route::get('/admin/role-manager', [AdminRoleManagerController::class, 'index']);
    Route::get('/admin/system-manager', [AdminRoleManagerController::class, 'indexSystem']);
    Route::prefix('/api/v1/admin/role-manager')->group(function () {
        // Legacy endpoints
        Route::get('/initial-data', [AdminRoleManagerController::class, 'getInitialData']);
        Route::get('/permissions/{userId}', [AdminRoleManagerController::class, 'getAdminPermissions']);
        Route::post('/permissions/{userId}', [AdminRoleManagerController::class, 'saveAdminPermissions']);

        // New: Dashboard & Users
        Route::get('/dashboard-stats', [AdminRoleManagerController::class, 'getDashboardStats']);
        Route::get('/users', [AdminRoleManagerController::class, 'getUsers']);
        Route::get('/user-roles/{userId}', [AdminRoleManagerController::class, 'getUserRoles']);
        Route::post('/user-roles/{userId}', [AdminRoleManagerController::class, 'saveUserRoles']);

        // New: CRUD Roles
        Route::get('/roles', [AdminRoleManagerController::class, 'getRoles']);
        Route::post('/roles', [AdminRoleManagerController::class, 'createRole']);
        Route::put('/roles/{id}', [AdminRoleManagerController::class, 'updateRole']);
        Route::delete('/roles/{id}', [AdminRoleManagerController::class, 'deleteRole']);
        Route::get('/roles/{id}/detail', [AdminRoleManagerController::class, 'getRoleDetail']);

        // New: CRUD Scopes
        Route::get('/scopes', [AdminRoleManagerController::class, 'getScopes']);
        Route::post('/scopes', [AdminRoleManagerController::class, 'createScope']);
        Route::put('/scopes/{id}', [AdminRoleManagerController::class, 'updateScope']);
        Route::delete('/scopes/{id}', [AdminRoleManagerController::class, 'deleteScope']);

        // New: Permissions & Activity Log
        Route::get('/permissions-list', [AdminRoleManagerController::class, 'getPermissions']);
        Route::get('/activity-log', [AdminRoleManagerController::class, 'getActivityLog']);

        // PHASE 2.1: Scope Metadata — for frontend authorization gating
        Route::get('/scope-metadata', [AdminRoleManagerController::class, 'getScopeMetadata']);
    });

    // System Auth — Module Governance, Access Distribution, Permission Matrix
    Route::prefix('/api/v1/system-auth')->group(function () {
        // Module Governance — Grup Fitur CRUD
        Route::get('/grup-fitur', [SystemAuthController::class, 'getGrupFitur']);
        Route::post('/grup-fitur', [SystemAuthController::class, 'createGrupFitur']);
        Route::put('/grup-fitur/{id}', [SystemAuthController::class, 'updateGrupFitur']);
        Route::delete('/grup-fitur/{id}', [SystemAuthController::class, 'deleteGrupFitur']);

        // Module Governance — Menu Binding
        Route::get('/menu-binding/{grupId}', [SystemAuthController::class, 'getMenuBinding']);
        Route::post('/menu-binding/{grupId}', [SystemAuthController::class, 'saveMenuBinding']);
        Route::get('/menu-delegation-policies/{grupId}', [SystemAuthController::class, 'getMenuDelegationPolicies']);
        Route::post('/menu-delegation-policies/{grupId}', [SystemAuthController::class, 'saveMenuDelegationPolicies']);
        Route::get('/target-scope-readiness', [SystemAuthController::class, 'getTargetScopeReadiness']);

        // Module Governance — Menu Capabilities
        Route::get('/menu-capabilities/{jenisPage}', [SystemAuthController::class, 'getMenuCapabilities']);
        Route::post('/menu-capabilities/{jenisPage}', [SystemAuthController::class, 'saveMenuCapabilities']);

        // Module Governance — Role Resource Access
        Route::get('/resource-access/filters', [SystemAuthController::class, 'getResourceAccessFilters']);
        Route::get('/resource-access', [SystemAuthController::class, 'getResourceAccess']);
        Route::post('/resource-access', [SystemAuthController::class, 'saveResourceAccess']);
        Route::put('/resource-access/{id}', [SystemAuthController::class, 'saveResourceAccess']);
        Route::delete('/resource-access/{id}', [SystemAuthController::class, 'deleteResourceAccess']);

        // Access Distribution
        Route::get('/distribution/filters', [SystemAuthController::class, 'getDistributionFilters']);
        Route::get('/distribution/targets', [SystemAuthController::class, 'getDistributionTargets']);
        Route::post('/distribution/current-access', [SystemAuthController::class, 'getDistributionCurrentAccess']);
        Route::post('/distribution/preview', [SystemAuthController::class, 'previewDistribution']);
        Route::post('/distribution/execute', [SystemAuthController::class, 'executeDistribution']);
        Route::post('/distribution/revoke', [SystemAuthController::class, 'revokeDistribution']);
        Route::post('/distribution/seed-existing', [SystemAuthController::class, 'seedExistingAccess']);

        // Page Access Manager
        Route::get('/page-access/menus', [SystemAuthController::class, 'getPageAccessMenus']);
        Route::get('/page-access/headers', [SystemAuthController::class, 'getPageAccessHeaders']);
        Route::put('/page-access/headers/{headerCode}', [SystemAuthController::class, 'updatePageAccessHeader']);
        Route::post('/page-access/menus', [SystemAuthController::class, 'createPageAccessMenu']);
        Route::put('/page-access/menus/{id}', [SystemAuthController::class, 'updatePageAccessMenu']);
        Route::post('/page-access/sort/validate', [SystemAuthController::class, 'validatePageAccessSort']);
        Route::post('/page-access/sort/apply', [SystemAuthController::class, 'applyPageAccessSort']);

        // Permission Matrix
        Route::get('/permission-matrix', [SystemAuthController::class, 'getPermissionMatrix']);
        Route::post('/permission-matrix/toggle', [SystemAuthController::class, 'togglePermission']);
        Route::post('/permission-matrix/bulk-save', [SystemAuthController::class, 'bulkSavePermissions']);
        Route::post('/permission-matrix/reset-to-default', [SystemAuthController::class, 'resetPermissionToDefault']);

        // Monitoring
        Route::get('/monitoring', [SystemAuthController::class, 'getMonitoringOverview']);
    });
});
