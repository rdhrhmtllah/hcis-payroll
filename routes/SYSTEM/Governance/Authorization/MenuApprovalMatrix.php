<?php

use Illuminate\Support\Facades\Route;

// =============================================================================
// MENU, APPROVAL MATRIX & KEANGGOTAAN (per grup fitur)
// Page  : route langsung per modul, satu komponen Inertia
// Akses : page menu langsung per modul + granular via
//         N_HRIS_Role_Resource_Access (tab/menu, tab/approval-matrix,
//         tab/membership, capability/MENU_ACCESS.ASSIGN,
//         capability/APPROVAL_MATRIX.ASSIGN, capability/MEMBERSHIP.ASSIGN).
//         Aksi struktur (config/step/jenis approval/binding grup) khusus
//         System Admin (di-assert di ApprovalMatrixService).
// =============================================================================
Route::middleware([
    'auth',
    'check.jabatan:menuApprovalMatrixPage,menuApprovalMatrixEssPage,menuApprovalMatrixKpiPage,menuApprovalMatrixLmsPage,menuApprovalMatrixVisitorPage',
    'audit.admin.transaksi',
])->group(function () {
    Route::get('/admin/ess/menu-approval-matrix', [
        \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
        'indexEss',
    ]);
    Route::get('/admin/kpi/menu-approval-matrix', [
        \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
        'indexKpi',
    ]);
    Route::get('/admin/lms/menu-approval-matrix', [
        \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
        'indexLms',
    ]);
    Route::get('/admin/visitor/menu-approval-matrix', [
        \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
        'indexVisitor',
    ]);

    Route::prefix('/api/v1/menu-approval-matrix')->group(function () {
        Route::get('/bootstrap/{grupId}', [\App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class, 'bootstrap']);
        Route::get('/issue-counts/{grupId}', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'getIssueCounts',
        ]);
        // Tab Menu
        Route::get('/menu-binding/{grupId}', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'getMenuBinding',
        ]);
        Route::post('/menu-binding/{grupId}', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'saveMenuBinding',
        ]);
        Route::post('/menu-binding/{grupId}/bulk', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'bulkAssignMenu',
        ]);
        Route::get('/menu-binding/{grupId}/history', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'getMenuHistory',
        ]);
        Route::get('/menu-binding/{grupId}/summary', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'getMenuSummary',
        ]);
        Route::post('/menu-binding/{grupId}/purge-stale', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'purgeStaleMenuBinding',
        ]);
        Route::get('/menu-binding/{grupId}/header-users', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'getHeaderUsers',
        ]);
        // Matrix menu x karyawan: siapa yang belum di-assign menu apa + ekspor.
        Route::get('/menu-binding/{grupId}/matrix-karyawan', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'getMenuKaryawanMatrix',
        ]);
        Route::post('/menu-binding/{grupId}/matrix-karyawan/export', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'exportMenuKaryawanMatrix',
        ]);

        // Tab Keanggotaan
        Route::get('/membership/{grupId}', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'getMembershipOverview',
        ]);
        Route::get('/membership/{grupId}/employees', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'searchMembershipEmployees',
        ]);
        Route::post('/membership/{grupId}/preview', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'previewMembership',
        ]);
        Route::post('/membership/{grupId}/assign', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'assignMembership',
        ]);
        Route::post('/membership/{grupId}/remove', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'removeMembership',
        ]);
        Route::get('/membership/{grupId}/history', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'getMembershipHistory',
        ]);
        Route::post('/membership/{grupId}/history/{transactionNo}/rollback', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'rollbackMembership',
        ]);

        // Tab Approval Matrix
        Route::get('/configs', [\App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class, 'getConfigs']);
        Route::get('/approval-summary', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'getApprovalSummary',
        ]);
        Route::get('/configs/{id}/matrix', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'getConfigMatrix',
        ]);
        Route::get('/config-details/{configDetailId}/role-members', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'getRoleMembers',
        ]);
        Route::get('/assignments', [\App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class, 'getAssignments']);
        Route::post('/assignments/replace', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'replaceAssignment',
        ]);
        Route::post('/assignments/bulk', [\App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class, 'bulkAssign']);
        Route::post('/assignments/bulk-replace', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'bulkReplaceAssignments',
        ]);
        Route::post('/assignments/bulk-substitute', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'bulkSubstituteAssignment',
        ]);
        Route::get('/assignments/history', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'getAssignmentHistory',
        ]);
        Route::get('/karyawan/filter-options', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'getFilterOptions',
        ]);
        Route::post('/karyawan/filter-search', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'filterKaryawan',
        ]);
        Route::get('/karyawan/search', [\App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class, 'searchKaryawan']);

        // System admin: struktur approval config
        Route::post('/configs', [\App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class, 'createConfig']);
        Route::put('/configs/{id}', [\App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class, 'updateConfig']);
        Route::delete('/configs/{id}', [\App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class, 'deleteConfig']);
        Route::put('/configs/{id}/grup-fitur', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'bindConfigGrupFitur',
        ]);
        Route::post('/configs/{configId}/details', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'createConfigDetail',
        ]);
        Route::put('/config-details/{id}', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'updateConfigDetail',
        ]);
        Route::delete('/config-details/{id}', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'deleteConfigDetail',
        ]);

        // System admin: jenis approval (akar resolusi approver)
        Route::get('/jenis-approval', [\App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class, 'getJenisApproval']);
        Route::post('/jenis-approval', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'createJenisApproval',
        ]);
        Route::put('/jenis-approval/{id}', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'updateJenisApproval',
        ]);
        Route::delete('/jenis-approval/{id}', [
            \App\Http\Controllers\SYSTEM\Governance\Authorization\MenuApprovalMatrix\MenuApprovalMatrixController::class,
            'deleteJenisApproval',
        ]);
    });
});
// ===== SOP Repository (base untuk tim) =====
