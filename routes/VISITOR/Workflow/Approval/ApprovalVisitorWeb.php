<?php

use App\Helpers\ResponseHelper;
use App\Http\Controllers\VISITOR\Workflow\Approval\ApprovalVisitorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'check.jabatan:visitorApprovalPage'])->group(function () {
    // only(): controller cuma implement show - create/store/edit/update/destroy
    // tidak pernah ada, dan approve/reject/updateChecklist sudah dihapus.
    Route::resource('approvalvisitor', ApprovalVisitorController::class)->only(['show']);

    // Deprecated (2026-07-29): halaman lama digantikan pool verifikator gabungan (permit+tamu).
    // Data usage riil menunjukkan halaman ini praktis tidak pernah dipakai user sungguhan
    // (lihat memory visitor-module-audit-findings).
    //
    // Yang masih menjawab hanya PEMBACAAN: -list, -stats, dan resource show().
    // Seluruh mutasinya membalas 410 lewat closure di bawah, dan metodenya di
    // controller sudah dihapus — jadi menyambung kembali salah satu rute ini
    // ke controller TIDAK akan berhasil tanpa menulis ulang metodenya, yang
    // memang disengaja: versi lamanya menulis dalam dua langkah tanpa
    // transaksi. Gunakan VerifikatorVisitorController.
    Route::get('approvalvisitor', function () {
        return redirect('/verifikator-visitor');
    })->name('approvalvisitor.index');

    Route::get('approvalvisitor-list', [ApprovalVisitorController::class, 'getList']);
    $retiredMutation = fn () => ResponseHelper::errorWithCode(
        'Endpoint approval Visitor lama sudah dinonaktifkan. Gunakan halaman Verifikator Visitor.',
        'VISITOR_LEGACY_ENDPOINT_RETIRED',
        410,
    );
    Route::post('approvalvisitor-approve/{noTransaksi}', $retiredMutation);
    Route::post('approvalvisitor-reject/{noTransaksi}', $retiredMutation);
    Route::post('approvalvisitor/{noTransaksi}/{action}', $retiredMutation)
        ->whereIn('action', ['approve', 'reject']);
    Route::post('approvalvisitor-checklist/{noTransaksi}', $retiredMutation);
    Route::get('approvalvisitor-stats', [ApprovalVisitorController::class, 'getStats']);
});
