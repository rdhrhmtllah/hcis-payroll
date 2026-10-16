<?php

use App\Http\Controllers\ESS\Attendance\Shift\Approval\ApprovalFlowShiftController;
use Illuminate\Support\Facades\Route;

// Approval Flow Shift Management
Route::middleware(['auth', 'check.jabatan:approvalFlowShift'])->group(function () {
    Route::get('/approval-flow-shift', [ApprovalFlowShiftController::class, 'index'])->name(
        'approval-flow-shift.index',
    );
    Route::get('/approval-flow-shift/stats', [ApprovalFlowShiftController::class, 'getStats'])->name(
        'approval-flow-shift.stats',
    );
    Route::get('/approval-flow-shift/approvers', [ApprovalFlowShiftController::class, 'getApprovers'])->name(
        'approval-flow-shift.approvers',
    );
    Route::get('/approval-flow-shift/members', [ApprovalFlowShiftController::class, 'getMembers'])->name(
        'approval-flow-shift.members',
    );
    Route::get('/approval-flow-shift/assignments', [ApprovalFlowShiftController::class, 'getAssignments'])->name(
        'approval-flow-shift.assignments',
    );
    Route::get('/approval-flow-shift/history', [ApprovalFlowShiftController::class, 'getTransactionHistory'])->name(
        'approval-flow-shift.history',
    );
    Route::get('/approval-flow-shift/filter-options', [ApprovalFlowShiftController::class, 'getFilterOptions'])->name(
        'approval-flow-shift.filter-options',
    );

    Route::post('/approval-flow-shift/bulk-assign', [ApprovalFlowShiftController::class, 'bulkAssign'])->name(
        'approval-flow-shift.bulk-assign',
    );
    Route::put('/approval-flow-shift/update/{id}', [ApprovalFlowShiftController::class, 'updateAssignment'])->name(
        'approval-flow-shift.update',
    );
    Route::delete('/approval-flow-shift/remove/{id}', [ApprovalFlowShiftController::class, 'removeAssignment'])->name(
        'approval-flow-shift.remove',
    );
    Route::post('/approval-flow-shift/transfer', [ApprovalFlowShiftController::class, 'transferApprover'])->name(
        'approval-flow-shift.transfer',
    );

    // Excel Import/Export
    Route::get('/approval-flow-shift/template', [ApprovalFlowShiftController::class, 'downloadTemplate'])->name(
        'approval-flow-shift.template',
    );
    Route::post('/approval-flow-shift/import/validate', [ApprovalFlowShiftController::class, 'validateImport'])->name(
        'approval-flow-shift.validate-import',
    );
    Route::post('/approval-flow-shift/import', [ApprovalFlowShiftController::class, 'importExcel'])->name(
        'approval-flow-shift.import',
    );
    Route::post('/approval-flow-shift/import/preview', [ApprovalFlowShiftController::class, 'importFromPreview'])->name(
        'approval-flow-shift.import-preview',
    );
    Route::post('/approval-flow-shift/export', [ApprovalFlowShiftController::class, 'exportData'])->name(
        'approval-flow-shift.export',
    );
});
