<?php

use App\Helpers\FormatTanggalHelper;
use App\Http\Controllers\SYSTEM\Application\About\AboutController;
use App\Http\Controllers\SYSTEM\Authentication\Web\AuthController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes — HCIS Payroll
|--------------------------------------------------------------------------
|
| Rute utama aplikasi HCIS Payroll.
|
*/

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('payroll.dashboard');
    }
    return redirect()->route('login');
});

Route::get('/testerror', function () {
    return Inertia::render('Error', [
        'status' => 404,
        'message' => 'Ini adalah pesan error tes untuk memeriksa tampilan.',
    ]);
});

Route::get('/reset-user', function () {
    $user = Auth::user();
    if (! $user) {
        return redirect('/login')->with('status', 'Silakan login terlebih dahulu.');
    }

    $userId = $user->Id_Users;

    if (config('session.driver') === 'database') {
        \DB::table('sessions')->where('user_id', $userId)->delete();
    }
    Session::flush();
    Auth::logout();

    foreach (request()->cookies as $key => $value) {
        Cookie::queue(Cookie::forget($key));
    }

    Cache::forget('user_'.$userId.'_some_data');

    return redirect('/login')->with('status', 'Status aplikasi Anda berhasil direset.');
});

Route::get('/getTime', function (Request $request) {
    $Tanggal = class_exists(FormatTanggalHelper::class) ? FormatTanggalHelper::getCurrentTime('global') : null;

    $time = $Tanggal && ! empty($Tanggal)
        ? Carbon::parse($Tanggal)->setTimezone('Asia/Jakarta')
        : Carbon::now('Asia/Jakarta');

    return response()->json([
        'time' => $time->format('Y-m-d\TH:i:sP'),
        'timezone' => 'Asia/Jakarta',
    ]);
})->name('getTime');

// Autentikasi
Route::get('/login', [AuthController::class, 'login'])
    ->name('login')
    ->middleware('guest');
Route::get('/mulai/27738', [AuthController::class, 'loginRe'])
    ->name('loginRe')
    ->middleware('guest');
Route::post('/prosesLogin', [AuthController::class, 'prosesLogin'])->name('prosesLogin');

// Rute Aplikasi Terproteksi
Route::middleware(['auth'])->group(function () {
    // Auth & Pengguna
    Route::get('/resetPassword', [AuthController::class, 'reset'])->name('password.reset');
    Route::post('/changeDataUser', [AuthController::class, 'changeData'])->name('change.data');
    Route::post('/updatePassword', [AuthController::class, 'update'])->name('password.update');
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/about', [AboutController::class, 'index'])->name('about.index');

    // Modul Utama: PAYROLL
    require __DIR__.'/PAYROLL/payroll.php';
});

// Modul Teknis: Design System Showcase (Katalog Publik)
require __DIR__.'/SYSTEM/DesignSystem.php';

// Modul Pendukung SYSTEM (bila ada)
if (file_exists(base_path('routes/SYSTEM/MasterData/Entitas.php'))) {
    require base_path('routes/SYSTEM/MasterData/Entitas.php');
}
if (file_exists(base_path('routes/SYSTEM/MasterData/Departemen.php'))) {
    require base_path('routes/SYSTEM/MasterData/Departemen.php');
}
if (file_exists(base_path('routes/SYSTEM/Organization/RelasiOrganisasi.php'))) {
    require base_path('routes/SYSTEM/Organization/RelasiOrganisasi.php');
}
if (file_exists(base_path('routes/SYSTEM/Governance/Authorization/AksesPeran.php'))) {
    require base_path('routes/SYSTEM/Governance/Authorization/AksesPeran.php');
}
