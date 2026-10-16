<?php

use App\Helpers\FormatTanggalHelper;
use App\Http\Controllers\ESS\Attendance\Correction\CheckInOut\perbaikiCheckinoutController;
use App\Http\Controllers\ESS\Attendance\Legacy\AbsensiController;
use App\Http\Controllers\ESS\Attendance\Overtime\Conversion\KonversiLemburController;
use App\Http\Controllers\ESS\Attendance\Overtime\Management\OvertimeController;
use App\Http\Controllers\ESS\Attendance\Overtime\Request\LemburController;
use App\Http\Controllers\ESS\Attendance\Shift\Management\ShiftController;
use App\Http\Controllers\ESS\Dashboard\Homepage\HomepageController;
use App\Http\Controllers\ESS\Dashboard\Personal\DashboardController;
use App\Http\Controllers\ESS\Dashboard\Personal\uDashController;
use App\Http\Controllers\ESS\Employee\Profile\EmployeeController;
use App\Http\Controllers\ESS\Employee\Profile\GetFotoController;
use App\Http\Controllers\ESS\Employee\Profile\userController;
use App\Http\Controllers\ESS\Organization\Division\DivisionController;
use App\Http\Controllers\ESS\Organization\Level\LevelController;
use App\Http\Controllers\ESS\Organization\Team\karyawanTeamController;
use App\Http\Controllers\ESS\Workflow\Case\CaseController;
use App\Http\Controllers\ESS\Workflow\CrossDivision\CrossDivisionReviewController;
use App\Http\Controllers\ESS\Workflow\Evaluation\EvaluationController;
use App\Http\Controllers\ESS\Workflow\Project\ProjectController;
use App\Http\Controllers\ESS\Workflow\Review\ReviewController;
use App\Http\Controllers\ESS\Workflow\Task\TaskController;
use App\Http\Controllers\ESS\Workflow\Ticket\TicketController;
use App\Http\Controllers\KPI\Planning\Period\MasterPeriodeController;
use App\Http\Controllers\SYSTEM\Application\About\AboutController;
use App\Http\Controllers\SYSTEM\Application\News\NewsController;
use App\Http\Controllers\SYSTEM\Application\Recruitment\parseResume;
use App\Http\Controllers\SYSTEM\Authentication\Web\AuthController;
use App\Services\SYSTEM\Application\Security\GerbangToken;
use Carbon\Carbon;
// use Illuminate\Support\Facades\Request;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
})->middleware('guest');

Route::get('/testerror', function () {
    return Inertia::render('Error', [
        'status' => 404, // Anda bisa ganti dengan 403, 404, 419, 503, dll.
        'message' => 'Ini adalah pesan error tes untuk memeriksa tampilan baru Anda.',
    ]);
});

// Route::get('/test-419', function () {
//     abort(500);
// });

Route::get('/reset-user', function () {
    $user = Auth::user();
    if (! $user) {
        return redirect('/login')->with('status', 'Silakan login terlebih dahulu.');
    }

    $userId = $user->Id_Users;

    // --- Hapus session user ---
    if (config('session.driver') === 'database') {
        \DB::table('sessions')->where('user_id', $userId)->delete();
    }
    Session::flush();

    // --- Logout user ---
    Auth::logout();

    // --- Hapus semua cookies ---
    foreach (request()->cookies as $key => $value) {
        Cookie::queue(Cookie::forget($key));
    }

    // --- Hapus cache terkait user ---
    Cache::forget('user_'.$userId.'_some_data');

    return redirect('/login')->with('status', 'Status aplikasi Anda berhasil direset.');
});
// Route::get('/absensiSales', function () {
//     return inertia('ESS/Attendance/Personal/AbsensiSalesPage');
// });

Route::get('/getTime', function (Request $request) {
    $Tanggal = FormatTanggalHelper::getCurrentTime('global');

    $time =
        $Tanggal && ! empty($Tanggal)
            ? Carbon::parse($Tanggal)->setTimezone('Asia/Jakarta')
            : Carbon::now('Asia/Jakarta');

    return response()->json([
        // Kirim explicit timezone offset +07:00 agar tidak terbaca sebagai UTC (Z).
        'time' => $time->format('Y-m-d\TH:i:sP'),
        'timezone' => 'Asia/Jakarta',
    ]);
})->name('getTime');

Route::get('/login', [AuthController::class, 'login'])
    ->name('login')
    ->middleware('guest');
Route::get('/mulai/27738', [AuthController::class, 'loginRe'])
    ->name('loginRe')
    ->middleware('guest');
Route::post('/prosesLogin', [AuthController::class, 'prosesLogin'])->name('prosesLogin');

Route::middleware(['auth'])->group(function () {
    require __DIR__.'/KPI/Planning/Scheduling/PenjadwalanKpi.php';
    require __DIR__.'/KPI/MasterData/Scoring/Formula/FormulaKpi.php';
    require __DIR__.'/KPI/MasterData/Scoring/Unit/SatuanFormulaKpi.php';
    require __DIR__.'/KPI/MasterData/Target/TargetKpi.php';
    require __DIR__.'/KPI/Performance/Update/UpdatePerformance.php';
    require __DIR__.'/KPI/Performance/Dashboard/Executive/DashboardCeo.php';
    require __DIR__.'/KPI/Performance/Dashboard/StaffPerformanceDashboard.php';
    require __DIR__.'/KPI/Access/AksesDivisiLevel.php';
    require __DIR__.'/KPI/PerformanceAppraisal/Cycle/PerformanceAppraisalCycle.php';
    require __DIR__.'/KPI/PerformanceAppraisal/MasterData/PerformanceAppraisalMaster.php';
    require __DIR__.'/KPI/PerformanceAppraisal/Peserta/PerformanceAppraisalPeserta.php';
    require __DIR__.'/KPI/PerformanceAppraisal/Leader/PerformanceAppraisalLeader.php';
    require __DIR__.'/KPI/PerformanceAppraisal/Export/PerformanceAppraisalExport.php';
    require __DIR__.'/KPI/PerformanceAppraisal/Balancing/PerformanceAppraisalBalancing.php';

    require __DIR__.'/LMS/Training/MasterData/TrainingCategory/TrainingCategory.php';
    require __DIR__.'/LMS/Training/Scheduling/TrainingScheduling.php';

    require __DIR__.'/ESS/Attendance/Shift/SelfShift.php';
    require __DIR__.'/ESS/Attendance/Shift/PengajuanShiftAdmin.php';
    require __DIR__.'/ESS/Attendance/Shift/ShiftApproval.php';
    require __DIR__.'/ESS/Attendance/Shift/ApprovalFlowShift.php';
    require __DIR__.'/ESS/Attendance/Shift/ShiftManagement.php';
    require __DIR__.'/ESS/Attendance/Overtime/Overtime.php';
    require __DIR__.'/ESS/Attendance/Overtime/KonversiLemburAdmin.php';
    require __DIR__.'/ESS/Attendance/Monitoring/MonitoringKehadiran.php';
    require __DIR__.'/ESS/Attendance/Calendar/DayChange/PergantianHari.php';
    require __DIR__.'/ESS/Attendance/Leave/Personal/Cuti.php';
    require __DIR__.'/ESS/Attendance/Leave/Approval/IzinAtasanApproval.php';
    require __DIR__.'/ESS/Attendance/Leave/Admin/AdminCuti.php';
    require __DIR__.'/ESS/Attendance/Leave/Admin/KuotaManagement.php';
    require __DIR__.'/ESS/Attendance/Leave/Admin/CutiBersamaAdvanced.php';
    require __DIR__.'/ESS/Employee/DataCollection/DataCollection.php';

    require __DIR__.'/KPI/MasterData/BSC/BscKategoriKpi.php';
    require __DIR__.'/KPI/MasterData/Unit/SatuanKpi.php';
    require __DIR__.'/KPI/Performance/Parameter/FormKpi.php';
    require __DIR__.'/KPI/Performance/Team/KpiKaryawanTeam.php';
    require __DIR__.'/KPI/Approval/Workflow/KpiApproval.php';
    require __DIR__.'/KPI/Approval/Workflow/AdminApprovalKpi.php';
    require __DIR__.'/KPI/Approval/Verification/KpiVerifikasi.php';
    require __DIR__.'/KPI/Approval/Performance/PerformanceApproval.php';
    require __DIR__.'/KPI/Monitoring/Enforcement/KpiEnforcement.php';
    require __DIR__.'/KPI/Monitoring/Enforcement/KpiKoreksiTarget.php';

    require __DIR__.'/SYSTEM/Governance/Authorization/PageAccess.php';
    require __DIR__.'/SYSTEM/Governance/Authorization/AdminRoleManager.php';
    require __DIR__.'/SYSTEM/Governance/Authorization/MenuApprovalMatrix.php';

    require __DIR__.'/LMS/Content/ContentManagement.php';
    require __DIR__.'/LMS/Attendance/LmsModulAbsensiWeb.php';
    require __DIR__.'/LMS/Attendance/LmsModulAbsensiApi.php';
    require __DIR__.'/LMS/Training/Catalog/TrainingCatalog.php';
    require __DIR__.'/LMS/Content/Material/LmsMateriWeb.php';
    require __DIR__.'/LMS/Training/Content/AdminTraining/AdminTraining.php';
    require __DIR__.'/LMS/Training/Monitoring/AdminTrainingMonitoring/AdminTrainingMonitoring.php';
    require __DIR__.'/LMS/Monitoring/Pdf/LmsPdfMonitoringWeb.php';
    require __DIR__.'/LMS/SOP/Employee/SopEmployee.php';
    require __DIR__.'/LMS/SOP/Governance/SopGovernance.php';
    require __DIR__.'/LMS/SOP/Export/SopExportStudio.php';

    require __DIR__.'/VISITOR/Permit/VehiclePermit/VehiclePermit.php';
    require __DIR__.'/VISITOR/Permit/VehiclePermit/TransaksiPermitApi.php';
    require __DIR__.'/VISITOR/Permit/Inspection/AgendaInspeksi.php';
    require __DIR__.'/VISITOR/Permit/Nfc/NfcMonitoring.php';
    require __DIR__.'/VISITOR/Guest/Visit/GuestRegistration.php';
    require __DIR__.'/VISITOR/Guest/Visit/TransaksiTamuApi.php';
    require __DIR__.'/VISITOR/Guest/Schedule/JadwalKedatanganTamu.php';
    require __DIR__.'/VISITOR/Workflow/Reporting/LaporanVisitorWeb.php';
    require __DIR__.'/VISITOR/Workflow/Reporting/VisitorExportStudio.php';
    require __DIR__.'/VISITOR/Workflow/Reporting/VisitorDashboardApi.php';
    require __DIR__.'/VISITOR/Governance/MasterData/VisitorMasterData.php';
    require __DIR__.'/VISITOR/Workflow/Approval/ApprovalVisitorWeb.php';
    require __DIR__.'/VISITOR/Workflow/Approval/ApprovalVisitorApi.php';
    require __DIR__.'/VISITOR/Workflow/Verification/VerifikatorVisitorWeb.php';

    require __DIR__.'/ESS/Dashboard/Homepage/HomepageWeb.php';
    require __DIR__.'/ESS/Dashboard/Homepage/HomepageApi.php';

    require __DIR__.'/SYSTEM/DesignSystem.php';

    Route::get('/resetPassword', [AuthController::class, 'reset'])->name('password.reset');
    Route::post('/changeDataUser', [AuthController::class, 'changeData'])->name('change.data');
    Route::post('/updatePassword', [AuthController::class, 'update'])->name('password.update');

    Route::get('/about', [AboutController::class, 'index'])->name('about.index');

    Route::get('/tiket', [TicketController::class, 'index']);
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboardSuperUser', [DashboardController::class, 'superUser'])->middleware('system.admin')->name('superUser');

    Route::get('/master-periode', [MasterPeriodeController::class, 'index'])->middleware('system.admin')->name('master.periode');
    Route::get('/master-periode/display_tambah', [MasterPeriodeController::class, 'display_tambah_periode'])->middleware('system.admin')->name(
        'master.periode.display_tambah',
    );
    Route::post('master-periode/display_tambah', [MasterPeriodeController::class, 'tambah_periode'])->middleware('system.admin')->name(
        'master.periode.add.data',
    );

    Route::get('/user-profile', [userController::class, 'index'])->name('profileUser.index');
    Route::post('/user-profile/photo', [userController::class, 'uploadProfilePhoto'])->name('profileUser.photo.upload');

    // // Cross Division Review
    // route::get('/crossDivisionReview', [CrossDivisionReviewController::class, 'index'])->name('Cross.index');
    // route::get('/getReview/{id}', [CrossDivisionReviewController::class, 'getReviews'])->name('Cross.Reviews');
    // route::get('/getPeriode/{id}', [CrossDivisionReviewController::class, 'getPeriode'])->name('Cross.getPeriode');
    // route::post('/storeCrossReview', [CrossDivisionReviewController::class, 'store'])->name('Cross.store');
    // Route::get('/historyCrossReview', [CrossDivisionReviewController::class, 'history'])->name('cross.history');
    // Route::get('/crossReviewPeriode', [CrossDivisionReviewController::class, 'periode'])->name('cross.periode');
    // Route::get('/addCrossReviewPeriode', [CrossDivisionReviewController::class, 'addPeriode'])->name('cross.add.periode');
    // route::post('/storeCrossReviewPeriode', [CrossDivisionReviewController::class, 'storePeriode'])->name('cross.store.periode');
    // route::post('/destroyCrossPeriode', [CrossDivisionReviewController::class, 'destroyPeriode'])->name('cross.destroy.periode');

    // Cross Division Review
    route::get('/crossDivisionReview', [CrossDivisionReviewController::class, 'index'])->name('Cross.index');
    route::get('/getReview/{Periode}/{User}', [CrossDivisionReviewController::class, 'getReviews'])->name(
        'Cross.Reviews',
    );
    route::get('/getPeriode/{id}', [CrossDivisionReviewController::class, 'getPeriode'])->name('Cross.getPeriode');
    route::get('/getUser/{periode}/{divisi}', [CrossDivisionReviewController::class, 'getUser'])->name('Cross.getUser');
    route::post('/storeCrossReview', [CrossDivisionReviewController::class, 'store'])->name('Cross.store');
    Route::get('/historyCrossReview', [CrossDivisionReviewController::class, 'history'])->name('cross.history');
    Route::get('/crossFeedbackReview', [CrossDivisionReviewController::class, 'feedback'])->name('cross.feedback');
    /*
     * Rute ini pernah ada dengan nama metode yang tertimpa jalur view Inertia
     * ('ESS/Workflow/CrossDivision/feedbackDetail'), sehingga menunjuk metode
     * yang tidak ada. Pembersihan 635a239b menghapusnya -- benar, tapi itu
     * memindahkan kerusakan ke hulu: cross/feedback.blade.php memanggil
     * route('cross.feedback.Detail') di dalam @foreach, jadi halaman DAFTARnya
     * yang 500 begitu ada satu baris data. Dipasang kembali dengan nama metode
     * yang benar.
     */
    Route::get('/crossFeedbackDetail/{no_review}', [CrossDivisionReviewController::class, 'feedbackDetail'])->name(
        'cross.feedback.Detail',
    );
    Route::get('/crossReviewPeriode', [CrossDivisionReviewController::class, 'periode'])->middleware('system.admin')->name('cross.periode');
    Route::get('/addCrossReviewPeriode', [CrossDivisionReviewController::class, 'addPeriode'])->middleware('system.admin')->name(
        'cross.add.periode',
    );
    route::post('/storeCrossReviewPeriode', [CrossDivisionReviewController::class, 'storePeriode'])->middleware('system.admin')->name(
        'cross.store.periode',
    );
    route::post('/destroyCrossPeriode', [CrossDivisionReviewController::class, 'destroyPeriode'])->middleware('system.admin')->name(
        'cross.destroy.periode',
    );
    Route::get('/crossReviewSummary', [CrossDivisionReviewController::class, 'summary'])->name('cross.summary');
    Route::get('/summary-cross-review-detail/{Id_Periode}/{Id_Division}', [
        CrossDivisionReviewController::class,
        'summaryReview',
    ])->name('summary.cross.review.detail');

    // RBAC Fase 0: halaman admin warisan (Blade lama, tak ada di menu) — khusus System Admin.
    // Division
    Route::get('/divison', [DivisionController::class, 'index'])->middleware('system.admin')->name('division.index');

    // Level
    Route::get('/level', [LevelController::class, 'index'])->middleware('system.admin')->name('level.index');

    // Employee
    Route::get('/employee', [EmployeeController::class, 'index'])->middleware('system.admin')->name('employee.index');

    // Case
    Route::get('/case', [CaseController::class, 'index'])->name('case.index');
    Route::get('/case/create', [CaseController::class, 'create'])->name('case.create');
    Route::post('/storeCase', [CaseController::class, 'store'])->name('case.store');
    Route::post('/case/edit', [CaseController::class, 'edit'])->name('case.edit');
    Route::post('/case/delete', [CaseController::class, 'destroy'])->name('case.destroy');

    // Ticket
    Route::get('/ticket', [TicketController::class, 'index'])->name('ticket.index');
    Route::get('/displayTicket', [TicketController::class, 'displayTicket'])->name('displayTicket');
    Route::get('/takeTicket', [TicketController::class, 'takeTicket'])->name('takeTicket');
    Route::post('/responseTicket', [TicketController::class, 'responseTicket'])->name('responseTicket');
    Route::get('/finishTicket', [TicketController::class, 'finishTicket'])->name('finishTicket');
    Route::get('/TicketReview/{id_ticket}', [TicketController::class, 'display_review_ticket'])->name('ticket.review');
    Route::get('/DetailTicket/{id_ticket}/{from}', [TicketController::class, 'display_detail_ticket'])->name(
        'ticket.detail',
    );

    Route::post('/simpan_review_ticket', [TicketController::class, 'simpan_review_ticket'])->name(
        'simpan.review.ticket',
    );
    Route::post('/finishTicket', [TicketController::class, 'storeTicket'])->name('storeTicket');
    Route::post('approveFinishTicket/{id}', [TicketController::class, 'approveFinishTicket'])->name(
        'approveFinishTicket',
    );
    Route::post('revisionFinishTicket/{id}', [TicketController::class, 'revisionFinishTicket'])->name(
        'revisionFinishTicket',
    );

    Route::get('getPICTicket/{id}', [TicketController::class, 'getPICTicket']);
    Route::post('/simpanTicket', [TicketController::class, 'simpanTicket']);

    Route::get('/secure-file/{filename}', [GetFotoController::class, 'show'])->name('show.file');

    // Global Review
    Route::get('/globalReview', [ReviewController::class, 'index'])->name('globalReview.index');

    Route::get('/getPeriode-review/{id}', [ReviewController::class, 'getPeriode']);
    Route::get('/getReview-user/{id}', [ReviewController::class, 'getReviewUser']);

    Route::get('/globalReview2', [ReviewController::class, 'index2'])->name('globalReview2.index');
    Route::get('/summary-review-detail/{no_review}/{id}', [ReviewController::class, 'summaryReview'])->name(
        'summary.review.detail',
    );
    Route::get('/summary-review', [ReviewController::class, 'summaryReviewIndex'])->name('summary.review');
    Route::post('/assestmentGlobalReview', [ReviewController::class, 'store'])->name('kpi-global-review.store');

    // Evaluation
    Route::get('/evaluation', [EvaluationController::class, 'index'])->name('evaluation.index');
    Route::get('/evaluation/get', [EvaluationController::class, 'get'])->name('evaluation.get');
    Route::get('/evaluation/create', [EvaluationController::class, 'create'])->name('evaluation.create');
    Route::post('/storeEvaluation', [EvaluationController::class, 'store'])->name('evaluation.store');
    Route::post('/evaluation/delete', [EvaluationController::class, 'destroy'])->name('evaluation.destroy');

    // task
    Route::get('/task', [TaskController::class, 'index'])->name('task.index');
    Route::get('/task/create', [TaskController::class, 'create'])->name('task.create');
    Route::get('/task/getDivision', [TaskController::class, 'getUser'])->name('task.Division');
    Route::post('/storetask', [TaskController::class, 'store'])->name('task.store');
    Route::post('/task/edit', [TaskController::class, 'edit'])->name('task.edit');
    Route::post('/task/delete', [TaskController::class, 'destroy'])->name('task.destroy');
    Route::post('/task/updateProgress', [TaskController::class, 'progressUpdate'])->name('task.progress');

    // Route::get('/news', [NewsController::class, 'index'])->name('news.index');
    // Route::post('/news/store', [NewsController::class, 'store'])->name('news.store');

    // project
    Route::get('/project', [ProjectController::class, 'index'])->name('project.index');
    Route::get('/project/create', [ProjectController::class, 'create'])->name('project.create');
    Route::post('/storeproject', [ProjectController::class, 'store'])->name('project.store');
    Route::post('/project/edit', [ProjectController::class, 'edit'])->name('project.edit');
    Route::post('/project/delete', [ProjectController::class, 'destroy'])->name('project.destroy');

    // Absensi
    // Route::middleware(['auth', 'check.jabatan:IzinPage, IzinPageApprover'])->group(function(){

    //     Route::get('/lembur',[AbsensiController::class, 'indexLembur'])->name('absensi.lembur');
    //     Route::middleware(['auth','check.jabatan:IzinPage'])->group(function(){
    //         Route::get('/izin',[AbsensiController::class, 'indexIzin'])->name('absensi.izin');
    //         Route::get('/izin/getDataIzin',[AbsensiController::class, 'getDataIzin'])->name('absensi.izin.getData');
    //         Route::get('/izin/getKerja',[AbsensiController::class, 'getJamKerja'])->name('absensi.izin.getJamKerja');
    //         Route::post('/izin/submit',[AbsensiController::class, 'submitIzin'])->name('absensi.izin.submit');
    //         Route::post('/izin/getAttachmentUrl', [AbsensiController::class, 'getAttachmentUrl']);
    //         Route::post('/izin/destroy', [AbsensiController::class, 'destroyIzin']);

    //     Route::middleware(['check.jabatan:IzinPageApprover'])->group(function(){

    //         Route::post('/izin/confirmIzin',[AbsensiController::class, 'confirmIzin'])->name('absensi.izin.confirm');
    //         Route::get('/izin/getDataIzinDH',[AbsensiController::class, 'getDataIzinDH'])->name('absensi.izin.getDataDH');
    //         Route::get('/izinLevelUp',[AbsensiController::class, 'indexIzinDH'])->name('absensi.izinDH');
    //     });
    //     });
    // });
    // Absensi

    Route::middleware(['auth', 'check.jabatan:IzinPage,IzinPageApprover, IzinPageAdmin'])->group(function () {
        // Route umum untuk kedua jabatan
        Route::get('/lembur', [AbsensiController::class, 'indexLembur'])->name('absensi.lembur');
        Route::post('/izin/getAttachmentUrl', [AbsensiController::class, 'getAttachmentUrl']);

        // Route khusus IzinPage
        Route::middleware(['check.jabatan:IzinPage'])->group(function () {
            Route::get('/izin', [AbsensiController::class, 'indexIzin'])->name('absensi.izin');
            Route::get('/izin/getDataIzin', [AbsensiController::class, 'getDataIzin'])->name('absensi.izin.getData');
            Route::get('/izin/getKerja', [AbsensiController::class, 'getJamKerja'])->name('absensi.izin.getJamKerja');
            Route::post('/izin/submit', [AbsensiController::class, 'submitIzin'])->name('absensi.izin.submit');
            Route::post('/izin/destroy', [AbsensiController::class, 'destroyIzin']);
            Route::get('/izin/getExport', [AbsensiController::class, 'getExport'])->name('absensi.izin.export');
        });

        Route::middleware(['check.jabatan:IzinPageAdmin'])->group(function () {
            Route::get('/izinAdmin', [AbsensiController::class, 'indexIzinAdmin'])->name('absensi.izinAdmin');
            Route::get('/izin/getDataIzinAdmin', [AbsensiController::class, 'getDataIzinAdmin'])->name(
                'absensi.izin.getDataAdmin',
            );
            Route::get('/izin/getExportAdmin', [AbsensiController::class, 'getExportAdmin'])->name(
                'absensi.izin.exportAdmin',
            );
            Route::post('/izinAdmin/cancel/preview', [AbsensiController::class, 'previewCancelIzinAdmin']);
            Route::post('/izinAdmin/cancel', [AbsensiController::class, 'cancelIzinAdmin']);
        });

        // Route khusus IzinPageApprover
        Route::middleware(['check.jabatan:IzinPageApprover'])->group(function () {
            Route::post('/izin/confirmIzin', [AbsensiController::class, 'confirmIzin'])->name('absensi.izin.confirm');
            Route::get('/izin/getDataIzinDH', [AbsensiController::class, 'getDataIzinDH'])->name(
                'absensi.izin.getDataDH',
            );
            Route::get('/izinLevelUp', [AbsensiController::class, 'indexIzinDH'])->name('absensi.izinDH');
            Route::get('/izin/getExportDH', [AbsensiController::class, 'getExportDH'])->name('absensi.izin.exportDH');
        });
    });

    Route::middleware(['auth', 'check.jabatan:absensiPage'])->group(function () {
        Route::get('/absensiSales', [AbsensiController::class, 'indexAbsensi'])->name('absensi.absensi');
        Route::get('/absensi/getUserShift', [AbsensiController::class, 'getUserShift'])->name('absensi.shift');
        Route::get('/absensi/getDataToday', [AbsensiController::class, 'getDataToday'])->name('absensi.today');
        Route::post('/absensi/submitAbsen', [AbsensiController::class, 'submitAbsen'])->name('absensi.submit');
    });
    Route::post('/addLembur', [AbsensiController::class, 'addLembur'])->name('absensi.lembur.add');

    Route::get('/parseResume', [parseResume::class, 'index'])->name('SYSTEM/Application/Recruitment/parseResume');
    Route::post('/parseResume/preview', [parseResume::class, 'preview'])->name('parseResume.preview');
    Route::post('/parseResume/submit', [parseResume::class, 'submit'])->name('parseResume.submi');

    Route::middleware(['auth', 'check.jabatan:addKaryawanTeam'])->group(function () {
        Route::get('/add-karyawan-team', [karyawanTeamController::class, 'index'])->name('karyawanTeam.index');
        Route::get('/add-karyawan-team/getData', [karyawanTeamController::class, 'getData'])->name(
            'karyawanTeam.getData',
        );
        Route::get('/add-karyawan-team/getAllData', [karyawanTeamController::class, 'getDataAll'])->name(
            'karyawanTeam.getDataAll',
        );
        Route::post('/add-karyawan-team/simpanApproval', [karyawanTeamController::class, 'simpanApproval'])->name(
            'karyawanTeam.simpanApproval',
        );
        Route::post('/add-karyawan-team/simpanTeam', [karyawanTeamController::class, 'simpanTeam'])->name(
            'karyawanTeam.simpanTeam',
        );
        Route::post('/add-karyawan-team/simpanUser', [karyawanTeamController::class, 'simpanUser'])->name(
            'karyawanTeam.simpanUser',
        );
        Route::post('/add-karyawan-team/ubahApproval', [karyawanTeamController::class, 'ubahApproval'])->name(
            'karyawanTeam.ubahApproval',
        );
        Route::post('/add-karyawan-team/kirimUlangUser', [karyawanTeamController::class, 'kirimUlangUser'])->name(
            'karyawanTeam.kirimUlangUser',
        );
    });

    // user dashboard — redirected to new homepage
    Route::redirect('/uDash', '/homepage', 301)->name('Udash.index');
    Route::get('/uDash/getDateAbsen', [uDashController::class, 'getDateAbsen'])->name('Udash.dateAbsen');
    Route::get('/uDash/getDataShift', [uDashController::class, 'getDataShift'])->name('Udash.dataShift');
    Route::get('/uDash/getChartData', [uDashController::class, 'getChartData'])->name('Udash.chartData');
    Route::get('/uDash/getAllLembur', [uDashController::class, 'getAllLembur'])->name('Udash.allLembur');
    Route::get('/uDash/getAllIzin', [uDashController::class, 'getAllIzin'])->name('Udash.allIzin');
    Route::get('/uDash/getRiwayatAbsen', [uDashController::class, 'getRiwayatAbsen'])->name('Udash.riwayatAbsen');
    Route::get('/uDash/getAbsenDetail', [uDashController::class, 'getAbsenDetail'])->name('Udash.absenDetail');
    Route::get('/uDash/getChartLembur', [uDashController::class, 'getChartLembur'])->name('Udash.chartLembur');
    Route::get('/uDash/getRingkasanTeam', [uDashController::class, 'getRingkasanTeam'])->name('Udash.getRingkasanTeam');
    Route::get('/uDash/getCalendarData', [HomepageController::class, 'getCalendarData'])->name('homepage.calendarData');

    // Onboarding tour (driver.js) — generic, namespaced via `tour` body param
    Route::post('/tour/progress', [
        \App\Http\Controllers\ESS\Dashboard\Homepage\HomepageTourController::class,
        'updateProgress',
    ])->name('tour.progress');
    Route::post('/tour/skip', [\App\Http\Controllers\ESS\Dashboard\Homepage\HomepageTourController::class, 'skip'])->name(
        'tour.skip',
    );
    Route::post('/tour/reset', [\App\Http\Controllers\ESS\Dashboard\Homepage\HomepageTourController::class, 'reset'])->name(
        'tour.reset',
    );

    // //ROUTE GROUP AKSES Shift Management
    // Route::middleware(['auth','check.jabatan:ShiftManagement'])->group(function(){
    //     //Shift
    //     Route::get('/userShift',[ShiftController::class, 'index'])->name('shift.index');
    //     Route::get('/getWeek',[ShiftController::class, 'getWeek'])->name('shift.getWeek');
    //     Route::post('/userShift/submit',[ShiftController::class, 'submit'])->name('shift.submit');
    //     Route::post('/userShift/update',[ShiftController::class, 'update'])->name('shift.update');
    //     Route::get('/shift/getExport',[ShiftController::class, 'getExport'])->name('shift.export');
    //     Route::get('/shift/getShift',[ShiftController::class, 'getShift'])->name('shift.getShift');
    //     Route::get('/shift/isLembur',[ShiftController::class, 'isLembur'])->name('shift.isLembur');

    // });

    // Route::middleware(['auth','check.jabatan:ShiftManagementAdmin'])->group(function(){
    //     Route::get('/userShiftAdmin',[ShiftController::class, 'indexAdmin'])->name('shift.indexAdmin');
    //     Route::get('/getWeekAdmin',[ShiftController::class, 'getWeekAdmin'])->name('shift.getWeekAdmin');
    //     Route::post('/userShift/submit',[ShiftController::class, 'submit'])->name('shift.submit');
    //     Route::post('/userShift/update',[ShiftController::class, 'update'])->name('shift.update');
    //     Route::get('/shift/getExport',[ShiftController::class, 'getExport'])->name('shift.export');
    //     Route::get('/shift/getShift',[ShiftController::class, 'getShift'])->name('shift.getShift');
    //     Route::get('/shift/isLembur',[ShiftController::class, 'isLembur'])->name('shift.isLembur');
    // });
    Route::middleware(['auth', 'check.jabatan:perbaikanCheckinOut'])->group(function () {
        Route::get('/perbaiki-checkinout', [perbaikiCheckinoutController::class, 'index'])->name('perbaiki.index');
        Route::get('/perbaiki-checkinout/attendance', [perbaikiCheckinoutController::class, 'attendance'])->name(
            'perbaiki.attendance',
        );
        Route::get('/perbaiki-checkinout/getCheckInOut', [perbaikiCheckinoutController::class, 'getCheckInOut'])->name(
            'perbaiki.getCheckInOut',
        );
        Route::post('/perbaiki-checkinout/submit', [perbaikiCheckinoutController::class, 'submit'])->name(
            'perbaiki.submit',
        );
    });

    Route::middleware(['auth', 'check.jabatan:ShiftManagement,ShiftManagementAdmin'])->group(function () {
        // Route yang bisa diakses oleh kedua jabatan
        Route::post('/swapShift/submit', [ShiftController::class, 'submit'])->name('shift.submit');
        Route::post('/swapShift/update', [ShiftController::class, 'update'])->name('shift.update');
        Route::get('/swapShift/getExport', [ShiftController::class, 'getExport'])->name('shift.export');
        Route::get('/swapShift/getShift', [ShiftController::class, 'getShift'])->name('shift.getShift');
        Route::get('/swapShift/isLembur', [ShiftController::class, 'isLembur'])->name('shift.isLembur');

        // Route khusus ShiftManagement
        Route::middleware(['check.jabatan:ShiftManagement'])->group(function () {
            Route::get('/swapShift', [ShiftController::class, 'index'])->name('shift.index');
            Route::get('/swapShift/getWeek', [ShiftController::class, 'getWeek'])->name('shift.getWeek');
            Route::get('/swapShift/getTeam', [ShiftController::class, 'getTeam'])->name('shift.getTeam');
        });

        // Route khusus ShiftManagementAdmin
        Route::middleware(['check.jabatan:ShiftManagementAdmin'])->group(function () {
            Route::post('/swapShift/submitAdmin', [ShiftController::class, 'submitAdmin'])->name('shift.submitAdmin');
            Route::post('/swapShift/updateAdmin', [ShiftController::class, 'updateAdmin'])->name('shift.updateAdmin');
            Route::get('/swapShiftAdmin', [ShiftController::class, 'indexAdmin'])->name('shift.indexAdmin');
            Route::get('/swapShift/getWeekAdmin', [ShiftController::class, 'getWeekAdmin'])->name('shift.getWeekAdmin');
            Route::get('/swapShift/getTeamAdmin', [ShiftController::class, 'getTeamAdmin'])->name('shift.getTeamAdmin');
        });
    });

    // Route::middleware(['auth','check.jabatan:OvertimeManagementAdmin'])->group(function(){
    // });

    // Route::middleware(['auth','check.jabatan:OvertimeManagement'])->group(function(){
    //     // OVERTIME

    // });

    Route::middleware(['auth', 'check.jabatan:OvertimeManagement,OvertimeManagementAdmin'])->group(function () {
        // Route yang bisa diakses oleh kedua jabatan
        Route::get('/overtime/getWaktuShift', [OvertimeController::class, 'getWaktuShift'])->name(
            'overtime.getWaktuShift',
        );
        Route::post('/overtime/destroyOvertime', [OvertimeController::class, 'destroy'])->name('overtime.destroy');
        Route::post('/overtime/destroyUserOvertime', [OvertimeController::class, 'destroyUser'])->name(
            'overtime.destroyUser',
        );
        /* GET /getOffset DIHAPUS 5 Sep 2026.
           Rutenya menunjuk OvertimeController::getOffset yang tidak ada, jadi
           satu-satunya hal yang pernah dihasilkannya adalah 500. Tak ada satu
           pun kode layar yang memanggilnya, dan nama rutenya (overtime.offset)
           tak dirujuk di mana pun. Dihapus, bukan dibuatkan metode kosong:
           404 untuk jalur yang memang tak ada lebih jujur daripada 200 yang
           tak berarti apa-apa. */
        Route::get('/overtime/getDetails', [OvertimeController::class, 'getDetails'])->name('overtime.Details');
        Route::get('/overtime/getShift', [OvertimeController::class, 'getShift'])->name('overtime.getShift');

        // Route khusus OvertimeManagementAdmin
        Route::middleware(['check.jabatan:OvertimeManagementAdmin', 'access.context:admin'])->group(function () {
            Route::post('/overtime/submitAdmin', [LemburController::class, 'submitAdmin'])->name(
                'overtime.submitAdmin',
            );
            Route::get('/overtime/getUserActiveAll', [LemburController::class, 'getUserActiveAll'])->name(
                'overtime.userActiveAdmin',
            );
            Route::get('/overtime/getDataAdmin', [OvertimeController::class, 'getDataAdmin'])->name(
                'overtime.getDataAdmin',
            );
            Route::get('/overtimeAdmin', [OvertimeController::class, 'indexAdmin'])->name('overtime.indexAdmin');
            Route::get('/overtime/getExportAdmin', [OvertimeController::class, 'getExportAdmin'])->name(
                'overtime.exportAdmin',
            );
        });

        // Route khusus OvertimeManagement
        Route::middleware(['check.jabatan:OvertimeManagement', 'access.context:user'])->group(function () {
            Route::post('/overtime/submit', [LemburController::class, 'submit'])->name('overtime.submit');
            Route::get('/overtime', [OvertimeController::class, 'index2'])->name('overtime.index');
            Route::get('/overtime/getData', [OvertimeController::class, 'getData'])->name('overtime.getData');
            Route::get('/overtime/getUserActive', [LemburController::class, 'getUserActive'])->name(
                'overtime.userActive',
            );
            Route::get('/overtime/getExport', [OvertimeController::class, 'getExport'])->name('overtime.export');
        });
    });

    Route::prefix('lembur-management')
        ->name('lembur-management.')
        ->middleware(['auth'])
        ->group(function () {
            Route::middleware(['check.jabatan:OvertimeManagement', 'access.context:user'])->group(function () {
                Route::get('/', [LemburController::class, 'index'])->name('index');
                Route::get('/get-user-active', [LemburController::class, 'getUserActive'])->name('user.active');
                Route::get('/get-data', [LemburController::class, 'getData'])->name('data');
                Route::get('/get-details', [LemburController::class, 'getDetails'])->name('details');
                Route::post('/validate-draft', [LemburController::class, 'validateDraft'])->name('validate-draft');
                Route::post('/submit', [LemburController::class, 'submit'])->name('submit');
            });

            Route::prefix('admin')
                ->name('admin.')
                ->middleware(['check.jabatan:OvertimeManagementAdmin', 'access.context:admin'])
                ->group(function () {
                    Route::get('/', [LemburController::class, 'indexAdmin'])->name('index');
                    Route::get('/get-user-active', [LemburController::class, 'getUserActiveAll'])->name('user.active');
                    Route::get('/get-data', [LemburController::class, 'getDataAdmin'])->name('data');
                    Route::get('/get-details', [LemburController::class, 'getDetails'])->name('details');
                    Route::post('/validate-draft', [LemburController::class, 'validateDraftAdmin'])->name(
                        'validate-draft',
                    );
                    Route::post('/submit', [LemburController::class, 'submitAdmin'])->name('submit');
                });
        });

    // Konversi Lembur Routes
    Route::controller(KonversiLemburController::class)
        ->prefix('konversi-lembur')
        ->middleware(['auth', 'check.jabatan:konversiLembur'])
        ->group(function () {
            Route::get('/', 'index')->name('konversi-lembur.index');
            Route::get('/stats', 'getDashboardStats')->name('konversi-lembur.stats');
            Route::get('/calendar', 'getCalendarData')->name('konversi-lembur.calendar');
            Route::get('/conversion-context', 'getConversionContext')->name('konversi-lembur.conversion-context');
            Route::get('/logs', 'getMachineLogs')->name('konversi-lembur.logs');
            Route::get('/day-off-options', 'getDayOffOptions')->name('konversi-lembur.day-off-options');
            Route::get('/approval-preview', 'getApprovalPreview')->name('konversi-lembur.approval-preview');
            Route::post('/store', 'store')->name('konversi-lembur.store');
            Route::get('/history', 'history')->name('konversi-lembur.history');
            Route::post('/cancel/{no_transaksi}', 'cancel')->name('konversi-lembur.cancel');
            Route::get('/get-checktime', 'getChecktime')->name('konversi-lembur.get-checktime');
        });
});

// Backfill cap KPI per parameter — operasi migrasi, di-hit dari Postman.
// Sengaja DI LUAR grup ['auth'] di atas: diamankan secret token (X-Backfill-Token)
// di controller, bukan session login. Dikecualikan juga dari CSRF (VerifyCsrfToken).
// Rute lama backfill-cap (cap TOTAL) dibalas 410.
Route::controller(\App\Http\Controllers\KPI\Monitoring\Enforcement\KpiBackfillCapController::class)->group(function () {
    Route::post('/api/v1/kpi-enforcement/backfill-cap-parameter', 'jalankan');
    Route::get('/api/v1/kpi-enforcement/backfill-cap-parameter/{batchId}', 'status');
    Route::post('/api/v1/kpi-enforcement/backfill-cap-parameter/{batchId}/rollback', 'rollback');
    Route::post('/api/v1/kpi-enforcement/backfill-cap', 'ditarik');
});

// FIX: Scheduler route tanpa web middleware stack
// Menghindari ~10-15 query DB yang tidak perlu dari HandleInertiaRequests, Session, dll.
Route::post('/scheduler-T4sk-RuNn3r-sEcr3t', function (Request $request) {
    /*
     * Gerbang GAGAL-TERTUTUP dan berwaktu-tetap. Bentuk lamanya memakai
     * `!==` terhadap config() bernilai cadangan literal; lihat
     * GerbangToken untuk kenapa keduanya bermasalah.
     */
    if (! GerbangToken::sah($request->bearerToken(), 'services.project_config.SCHEDULER_BEARER_TOKEN')) {
        Log::channel('worker_schedular')->warning('Unauthorized scheduler execution attempt.');
        abort(403, 'Unauthorized');
    }

    // 2. Jalankan scheduler dalam blok try-catch untuk error handling
    try {
        $result = Artisan::call('schedule:run');
        Log::channel('worker_schedular')->info('Scheduler executed successfully.', [
            'result' => $result,
        ]);

        return response('Scheduler executed successfully.', 200);
    } catch (\Exception $e) {
        Log::channel('worker_schedular')->error('Scheduler execution failed.', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response('Scheduler execution failed.', 500);
    }
})->withoutMiddleware([
    \App\Http\Middleware\VerifyCsrfToken::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \App\Http\Middleware\HandleInertiaRequests::class,
    \Inertia\Middleware::class,
    \App\Http\Middleware\CheckMaintenanceMode::class,
    \App\Http\Middleware\NoCache::class,
]);

Route::get('/cek-time', function () {
    return [
        'now' => now()->format('Y-m-d H:i:s'),
        'timezone' => config('app.timezone'),
        'php_timezone' => date_default_timezone_get(),
        'server' => gethostname(),
    ];
});

Route::get('/test-db', function (Request $request) {
    // Gerbang GAGAL-TERTUTUP dan berwaktu-tetap; lihat GerbangToken.
    if (! GerbangToken::sah($request->bearerToken(), 'services.project_config.DB_TEST_BEARER_TOKEN')) {
        return abort(403);
    }

    try {
        // Coba paksa koneksi ke database default
        DB::connection()->getPdo();

        // Jika baris di atas tidak error, koneksi berhasil
        return '<h1 style="color:green;">✅ SUKSES! Koneksi ke database berjalan dengan baik.</h1>';
    } catch (\Exception $e) {
        // Jika ada error saat koneksi, tampilkan pesannya
        return '<h1 style="color:red;">❌ GAGAL! Tidak bisa terhubung ke database.</h1><p>Error: '.
            $e->getMessage().
            '</p>';
    }
});

// Liveness probe Cloud Run. Insiden 2 Jul 2026: satu instance kehilangan jalur
// ke Cloud SQL ±5 jam dan tidak pernah diganti karena service hanya punya
// startup TCP probe (port 80). Endpoint ini mengecek konektivitas DB dari
// INSTANCE INI; konfigurasikan liveness probe HTTP Cloud Run ke path ini
// dengan failureThreshold >= 3 agar gangguan DB sesaat tidak membunuh semua
// instance serentak. Tanpa session/Inertia supaya murah & tidak bergantung
// infra lain.
Route::get('/healthz', function () {
    try {
        DB::select('SELECT 1 AS ok');

        return response('ok', 200)->header('Cache-Control', 'no-store');
    } catch (\Throwable $e) {
        return response('db-unreachable', 503)->header('Cache-Control', 'no-store');
    }
})->withoutMiddleware([
    \App\Http\Middleware\VerifyCsrfToken::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \App\Http\Middleware\HandleInertiaRequests::class,
    \Inertia\Middleware::class,
    \App\Http\Middleware\CheckMaintenanceMode::class,
    \App\Http\Middleware\NoCache::class,
]);

/*
|--------------------------------------------------------------------------
| Activity Tracker — My Activity (user self-view)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('my/activity')
    ->name('my.activity.')
    ->group(function () {
        $c = \App\Http\Controllers\SYSTEM\Audit\Activity\MyActivityController::class;
        Route::get('/', [$c, 'index'])->name('index');
        Route::get('/timeline', [$c, 'timeline'])->name('timeline');
        Route::get('/events/{eventId}', [$c, 'eventDetail'])
            ->name('event.detail')
            ->whereNumber('eventId');
        Route::get('/sessions', [$c, 'sessions'])->name('sessions');
        Route::get('/sessions/{sessionId}', [$c, 'sessionDetail'])->name('session.detail');
        Route::get('/devices', [$c, 'devices'])->name('devices');
        Route::get('/security', [$c, 'security'])->name('security');
        Route::post('/sessions/{sessionId}/not-me', [$c, 'markNotMe'])->name('not-me');
        Route::post('/devices/{fingerprint}/label', [$c, 'labelDevice'])->name('devices.label');
    });

/*
|--------------------------------------------------------------------------
| Activity Tracker — Admin Monitor
| Akses dijaga via memilikiJabatan(ActivityMonitor) menggunakan
| middleware existing 'check.jabatan'. Sesuaikan page key di
| config/activity-tracker.php (admin_access.page_key) bila berbeda.
|--------------------------------------------------------------------------
*/
// AKSES: hanya System Admin (KPI_Users.Is_System_Admin='Y'). Sebelumnya pakai
// check.jabatan:ActivityMonitor yang ternyata permisif (bukan gate admin sistem).
Route::middleware(['auth', 'system.admin'])
    ->prefix('admin/activity')
    ->name('admin.activity.')
    ->group(function () {
        $c = \App\Http\Controllers\SYSTEM\Audit\Activity\AdminActivityController::class;
        Route::get('/', [$c, 'index'])->name('index');
        Route::get('/kpis', [$c, 'kpis'])->name('kpis');
        Route::get('/heatmap', [$c, 'heatmap'])->name('heatmap');
        Route::get('/top-modules', [$c, 'topModules'])->name('topModules');
        Route::get('/users', [$c, 'users'])->name('users');
        Route::get('/users/{kode}', [$c, 'userDetail'])->name('users.detail');
        Route::get('/anomalies', [$c, 'anomalies'])->name('anomalies');
        Route::get('/export', [$c, 'export'])->name('export');

        // Tab "Kesehatan Sistem" — diagnostik RequestDiagnostics + DB Health Snapshot.
        Route::get('/diagnostics/summary', [$c, 'diagnosticsSummary'])->name('diagnostics.summary');
        Route::get('/diagnostics/requests', [$c, 'diagnosticsRequests'])->name('diagnostics.requests');
        Route::get('/diagnostics/db-health', [$c, 'dbHealth'])->name('diagnostics.dbHealth');

        // Pemantauan lock REALTIME — digerbangi tiga kunci di
        // GerbangDiagnostikRealtime, dan baku MATI. Rutenya sengaja tetap
        // terdaftar di semua lingkungan: gerbangnya yang menolak, bukan
        // ketiadaan rute. Rute yang hilang menghasilkan 404 yang terbaca
        // seperti salah alamat; gerbang yang menolak menyebut ALASANNYA.
        Route::get('/diagnostics/live', [$c, 'diagnosticsLive'])->name('diagnostics.live');
        Route::get('/diagnostics/queries', [$c, 'diagnosticsQueries'])->name('diagnostics.queries');
        Route::get('/diagnostics/recording', [$c, 'diagnosticsRecording'])->name('diagnostics.recording');
    });

require base_path('routes/SYSTEM/DatabaseChanges.php');
require base_path('routes/SYSTEM/MasterData/Entitas.php');
require base_path('routes/SYSTEM/MasterData/Departemen.php');
require base_path('routes/SYSTEM/Organization/RelasiOrganisasi.php');
require base_path('routes/SYSTEM/MasterData/KontakBantuan.php');
require base_path('routes/SYSTEM/Application/Panduan.php');
require base_path('routes/SYSTEM/Governance/Authorization/AksesPeran.php');
require base_path('routes/SYSTEM/Application/Maintenance/ClientRecovery.php');
require base_path('routes/SYSTEM/Integration/OutboundContractsPage.php');
require base_path('routes/VISITOR/Workflow/Reporting/TautanBerkasVisitor.php');
