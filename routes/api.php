<?php

use App\Http\Controllers\ESS\Attendance\Processing\Queue\AttendanceQueueWorkerController;
use App\Http\Controllers\LMS\Integration\CloudTaskWebhookController;
use App\Http\Controllers\SYSTEM\Application\Maintenance\RuntimeRecoveryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

require __DIR__.'/SYSTEM/TaskProcessing.php';
require __DIR__.'/LMS/Integration/LmsThirdPartyApi.php';
require __DIR__.'/KPI/PerformanceAppraisal/Integration/PaThirdPartyApi.php';
require __DIR__.'/ESS/Attendance/Calendar/OperationalCalendarApi.php';
require __DIR__.'/ESS/Attendance/Shift/RepairApi.php';
require __DIR__.'/LMS/SOP/Governance/SopImporRegisterApi.php';
require __DIR__.'/VISITOR/Permit/VehiclePermit/PermitTamuApi.php';
require __DIR__.'/VISITOR/Permit/VehiclePermit/KartuPermitPublikApi.php';
require __DIR__.'/VISITOR/Guest/Visit/TamuB2BApi.php';
require __DIR__.'/SYSTEM/Integration/ApiDocs.php';
require __DIR__.'/SYSTEM/Integration/OutboundContracts.php';
// Callback hasil kuis SOP TIDAK memakai endpoint sendiri: HC Learn memakai satu
// URL callback tetap (endpoint LMS). LmsThirdPartyCallbackController mendeteksi token
// SOP (via LmsApiGuard) lalu mendelegasikan ke SopCompletionService. Endpoint terpisah
// dinonaktifkan agar tidak ada "double endpoint" di production.
// require __DIR__ . '/LMS/SOP/Integration/SopThirdPartyApi.php';

/*
 * POST /trigger-task DICABUT.
 *
 * Closure tanpa gerbang apa pun — middleware-nya hanya 'api', yang di
 * aplikasi ini tidak memuat autentikasi — yang mengantre satu Cloud Task ke
 * URL webhook.site publik dengan muatan tetap bertanda TEST_TASK.
 *
 * Siapa pun yang tahu jalurnya bisa mengantre tugas tanpa batas. Tidak ada
 * pemanggil nyata di repositori; satu-satunya rujukan adalah snapshot daftar
 * rute di plans/.
 */

// Cloud Task Handler: Cuti Bersama Execute
Route::post('/cloud-task/cuti-bersama-execute', [
    \App\Http\Controllers\ESS\Attendance\Leave\Admin\CutiBersamaAdvancedController::class,
    'handleCloudTaskExecute',
]);

// Cloud Task Handler: Cuti Bersama Rollback
Route::post('/cloud-task/cuti-bersama-rollback', [
    \App\Http\Controllers\ESS\Attendance\Leave\Admin\CutiBersamaAdvancedController::class,
    'handleCloudTaskRollback',
]);

// Pastikan route ini TIDAK terhalang middleware auth user (karena yang hit adalah GCP Cloud Tasks)
Route::prefix('v1/tasks/lms')->group(function () {
    Route::post('/process-pdf', [CloudTaskWebhookController::class, 'processPdfToImage']);
    // Test manual via Postman (dijaga bearer token). Pre-render semua halaman.
    Route::post('/test-render-pdf', [CloudTaskWebhookController::class, 'testRenderPdf']);
});

// SOP render webhook (dijaga bearer token, hit dari GCP Cloud Tasks — tanpa auth/CSRF)
Route::prefix('v1/tasks/sop')->group(function () {
    Route::post('/process-pdf', [\App\Http\Controllers\LMS\SOP\Integration\SopCloudTaskWebhookController::class, 'processPdf']);
});

/*
 * Verifikasi mendalam berkas Library Konten (dipakai bersama LMS & SOP).
 * Dijaga bearer token, hit dari GCP Cloud Tasks — tanpa auth/CSRF.
 */
Route::prefix('v1/tasks/konten')->group(function () {
    Route::post('/verifikasi', [\App\Http\Controllers\LMS\Content\Verification\KontenVerifikasiWebhookController::class, 'verifikasi']);
});

Route::prefix('ops/runtime')->group(function () {
    Route::get('/db-health', [RuntimeRecoveryController::class, 'dbHealth']);
    Route::post('/db-reconnect', [RuntimeRecoveryController::class, 'dbReconnect']);
    Route::post('/laravel-refresh', [RuntimeRecoveryController::class, 'laravelRefresh']);
});

Route::post('/internal/hris/absen/process-queue', [AttendanceQueueWorkerController::class, 'process']);
Route::post('/internal/hris/absen/process-people', [AttendanceQueueWorkerController::class, 'peopleProcess']);
Route::post('/internal/hris/absen/plan-refresh', [AttendanceQueueWorkerController::class, 'planRefresh']);

/*
|--------------------------------------------------------------------------
| Activity Tracker
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth'])
    ->prefix('v1/activity')
    ->group(function () {
        Route::post('/handshake', [
            \App\Http\Controllers\SYSTEM\Audit\Activity\ActivityHandshakeController::class,
            'handshake',
        ])->name('activity.handshake');
    });
