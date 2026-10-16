<?php

use App\Http\Controllers\ESS\Attendance\Calendar\DayChange\PergantianHariController;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

/*
 * `auth` dideklarasikan di sini, bukan diwariskan dari posisi `require` berkas
 * ini di routes/web.php. Ketergantungan itu pernah gagal: satu berkas route
 * yang ter-require dua kali membuat registrasi terakhir menang dan seluruh
 * endpoint di bawahnya berjalan tanpa autentikasi.
 */
Route::get('/pergantian-hari', [pergantianHariController::class, 'index'])
    ->middleware(['auth'])
    ->name('pergantianHari.index');

// Route::prefix('/dummy-date-time')->group(function () {
//     if ($request->bearerToken() !== config('services.project_config.SCHEDULER_BEARER_TOKEN', 'bdc43f93e7d057cd1dca14d5a6259eb45c5062cc4cf96641fee05b688b3d85d3')) {
//             Log::warning('Unauthorized scheduler execution attempt.');
//             abort(403, 'Unauthorized');
//     }

//     Route::get('/get-list', function() {
//         $data = DB::table('KPI_Get_Date_Dummy')
//             ->orderBy('Date', 'asc')
//             ->get();

//         return response()->json($data);
//     });

//     // Route nonaktif dummy datetime
//     Route::post('/deactivate/{id}', function($id) {
//         DB::table('KPI_Get_Date_Dummy')
//             ->where('Id', $id)
//             ->update(['Flag_Aktif' => 'T']);

//         return response()->json(['message' => "Dummy {$id} date deactivated successfully"]);
//     });

//     // Route untuk mengaktifkan dummy datetime
//     Route::post('/activate/{id}', function($id) {
//         DB::table('KPI_Get_Date_Dummy')
//             ->where('Id', $id)
//             ->update(['Flag_Aktif' => 'Y']);

//         return response()->json(['message' => "Dummy {$id} date activated successfully"]);
//     });

//     // Route untuk edit date dummy
//     Route::post('/update/{id}', function($id) {
//         $newDate = request()->input('date'); // Expecting 'date' in YYYY-MM-DD format

//         // Validate date format
//         if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
//             return response()->json(['message' => "Invalid date format. Expected YYYY-MM-DD."], 400);
//         }

//         DB::table('KPI_Get_Date_Dummy')
//             ->where('Id', $id)
//             ->update(['Date' => $newDate]);

//         return response()->json(['message' => "Dummy {$id} date updated successfully"]);
//     });
// });
