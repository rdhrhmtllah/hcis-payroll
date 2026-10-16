<?php

namespace App\Http\Controllers\LMS\Attendance;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\TrainingExecutionService;
use App\Services\SYSTEM\Application\Database\GalatDeadlock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use Vinkla\Hashids\Facades\Hashids;

class LmsModulAbsensiController extends Controller
{
    public function __construct(private TrainingExecutionService $trainingExecutionService) {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $input = $request->all();

            // Decode Hashids
            if (isset($input['Id_Penjadwalan'])) {
                $input['Id_Penjadwalan'] =
                    Hashids::connection('custom')->decode($input['Id_Penjadwalan'])[0] ?? $input['Id_Penjadwalan'];
            }
            if (isset($input['Id_Penjadwalan_Detail'])) {
                $input['Id_Penjadwalan_Detail'] =
                    Hashids::connection('custom')->decode($input['Id_Penjadwalan_Detail'])[0] ??
                    $input['Id_Penjadwalan_Detail'];
            }
            if (isset($input['Id_Penjadwalan_Det'])) {
                $input['Id_Penjadwalan_Det'] =
                    Hashids::connection('custom')->decode($input['Id_Penjadwalan_Det'])[0] ??
                    $input['Id_Penjadwalan_Det'];
            }
            if (isset($input['Id_Materi'])) {
                $input['Id_Materi'] =
                    Hashids::connection('custom')->decode($input['Id_Materi'])[0] ?? $input['Id_Materi'];
            }
            if (isset($input['Id_Materi_Det'])) {
                $input['Id_Materi_Det'] =
                    Hashids::connection('custom')->decode($input['Id_Materi_Det'])[0] ?? $input['Id_Materi_Det'];
            }

            $request->replace($input);

            $validated = $request->validate([
                'Id_Penjadwalan' => 'required|integer',
                'Id_Penjadwalan_Detail' => 'required|integer',
                'Id_Penjadwalan_Det' => 'nullable|integer',
                'Id_Materi' => 'required|integer',
                'Id_Materi_Det' => 'required|integer',
                'Urutan' => 'nullable|integer',
                'Flag_Third_Party' => 'nullable|string|size:1',
                'Output' => 'required|in:In,Out',
                'photo' => 'required|string', // Base64 image
                'Latitude' => 'nullable|numeric',
                'Longitude' => 'nullable|numeric',
            ]);

            // Check if record already exists to prevent duplicate check-in/out
            $existingRecord = DB::table('N_LMS_CHECKINOUT')
                ->where('Id_Penjadwalan', $validated['Id_Penjadwalan'])
                ->where('Output', $validated['Output'])
                ->first();

            if ($existingRecord) {
                return ResponseHelper::error(
                    'Anda sudah melakukan absensi '.
                        ($validated['Output'] == 'In' ? 'Check-in' : 'Check-out').
                        ' untuk training ini.',
                    400,
                );
            }

            $filePath = null;

            // Handle Base64 photo upload to GCS
            if (! empty($validated['photo'])) {
                $image_parts = explode(';base64,', $validated['photo']);
                if (count($image_parts) == 2) {
                    $image_type_aux = explode('image/', $image_parts[0]);
                    $image_type = $image_type_aux[1];
                    $image_base64 = base64_decode($image_parts[1]);

                    $fileName = Str::uuid().'.'.$image_type;
                    $folderPath = 'lms/absensi/'.date('Y/m');
                    $filePath = $folderPath.'/'.$fileName;

                    // Upload to GCS
                    Storage::disk('gcs')->put($filePath, $image_base64);
                }
            }

            $result = $this->trainingExecutionService->bootstrapAttendance([...$validated, 'File_Path' => $filePath]);

            $fileUrl = $filePath ? Storage::disk('gcs')->temporaryUrl($filePath, now()->addMinutes(15)) : null;

            return ResponseHelper::success(
                [
                    'Output' => $validated['Output'],
                    'Checktime' => $result['checktime']->toDateTimeString(),
                    'File_Url' => $fileUrl,
                    'Id_Transaksi_Training' => Hashids::connection('custom')->encode($result['transaksi_training_id']),
                    'Id_Transaksi_Training_Detail' => Hashids::connection('custom')->encode(
                        $result['transaksi_training_detail_id'],
                    ),
                    'Id_Transaksi_Training_Det' => Hashids::connection('custom')->encode(
                        $result['transaksi_training_det_id'],
                    ),
                    'Id_Training_Progress' => Hashids::connection('custom')->encode($result['training_progress_id']),
                ],
                'Berhasil menyimpan absensi.',
            );
        } catch (ValidationException $e) {
            /* Validasi tak bisa dinaikkan ke luar try: aturannya berlaku pada
               $request SESUDAH replace() menaruh hasil decode Hashids. Tanpa
               klausa ini ValidationException tertangkap catch Throwable di
               bawah dan payload yang salah dilaporkan 500, bukan 422. */
            throw $e;
        } catch (Throwable $e) {
            Log::channel('lmsLog')->error('Gagal menyimpan absensi LMS: '.$e->getMessage());

            if (GalatDeadlock::adalah($e)) {
                return ResponseHelper::error(
                    'Absensi sedang diproses bersamaan. Silakan coba lagi beberapa detik lagi.',
                    409,
                );
            }

            return ResponseHelper::error('Terjadi kesalahan saat menyimpan absensi. Silakan coba lagi.', 500);
        }
    }

    public function recordActivity(Request $request)
    {
        try {
            $input = $request->all();

            // Decode Hashids
            if (isset($input['Id_Penjadwalan'])) {
                $input['Id_Penjadwalan'] =
                    Hashids::connection('custom')->decode($input['Id_Penjadwalan'])[0] ?? $input['Id_Penjadwalan'];
            }
            if (isset($input['Id_Penjadwalan_Detail'])) {
                $input['Id_Penjadwalan_Detail'] =
                    Hashids::connection('custom')->decode($input['Id_Penjadwalan_Detail'])[0] ??
                    $input['Id_Penjadwalan_Detail'];
            }
            if (isset($input['Id_Penjadwalan_Det'])) {
                $input['Id_Penjadwalan_Det'] =
                    Hashids::connection('custom')->decode($input['Id_Penjadwalan_Det'])[0] ??
                    $input['Id_Penjadwalan_Det'];
            }
            if (isset($input['Id_Materi'])) {
                $input['Id_Materi'] =
                    Hashids::connection('custom')->decode($input['Id_Materi'])[0] ?? $input['Id_Materi'];
            }
            if (isset($input['Id_Materi_Det'])) {
                $input['Id_Materi_Det'] =
                    Hashids::connection('custom')->decode($input['Id_Materi_Det'])[0] ?? $input['Id_Materi_Det'];
            }

            $request->replace($input);

            $validated = $request->validate([
                'Id_Penjadwalan' => 'required|integer',
                'Id_Penjadwalan_Detail' => 'required|integer',
                'Id_Penjadwalan_Det' => 'nullable|integer',
                'Id_Materi' => 'required|integer',
                'Id_Materi_Det' => 'required|integer',
                'Urutan' => 'nullable|integer',
                'Flag_Third_Party' => 'nullable|string|size:1',
                'Status_Progress' => 'nullable|in:MENUNGGU,PROGRESS,SELESAI',
                'MarkStarted' => 'nullable|boolean',
                'MarkCompleted' => 'nullable|boolean',
            ]);

            $result = $this->trainingExecutionService->recordActivityProgress($validated);

            return ResponseHelper::success($result, 'Berhasil merekam progres aktivitas training.');
        } catch (ValidationException $e) {
            /* Lihat catatan di store(): validasi bergantung pada $request
               sesudah replace(), jadi tak bisa dinaikkan ke luar try. */
            throw $e;
        } catch (Throwable $e) {
            Log::channel('lmsLog')->error('Gagal merekam progres aktivitas LMS: '.$e->getMessage(), [
                'payload' => $request->all(),
            ]);

            if (GalatDeadlock::adalah($e)) {
                return ResponseHelper::error(
                    'Progress training sedang diproses bersamaan. Silakan coba lagi beberapa detik lagi.',
                    409,
                );
            }

            return ResponseHelper::error('Terjadi kesalahan saat merekam progres aktivitas training.', 500);
        }
    }

    public function completeActivity(Request $request)
    {
        try {
            $input = $request->all();

            // Decode Hashids
            if (isset($input['Id_Penjadwalan'])) {
                $input['Id_Penjadwalan'] =
                    Hashids::connection('custom')->decode($input['Id_Penjadwalan'])[0] ?? $input['Id_Penjadwalan'];
            }
            if (isset($input['Id_Penjadwalan_Detail'])) {
                $input['Id_Penjadwalan_Detail'] =
                    Hashids::connection('custom')->decode($input['Id_Penjadwalan_Detail'])[0] ??
                    $input['Id_Penjadwalan_Detail'];
            }
            if (isset($input['Id_Penjadwalan_Det'])) {
                $input['Id_Penjadwalan_Det'] =
                    Hashids::connection('custom')->decode($input['Id_Penjadwalan_Det'])[0] ??
                    $input['Id_Penjadwalan_Det'];
            }
            if (isset($input['Id_Materi'])) {
                $input['Id_Materi'] =
                    Hashids::connection('custom')->decode($input['Id_Materi'])[0] ?? $input['Id_Materi'];
            }
            if (isset($input['Id_Materi_Det'])) {
                $input['Id_Materi_Det'] =
                    Hashids::connection('custom')->decode($input['Id_Materi_Det'])[0] ?? $input['Id_Materi_Det'];
            }

            $request->replace($input);

            $validated = $request->validate([
                'Id_Penjadwalan' => 'required|integer',
                'Id_Penjadwalan_Detail' => 'required|integer',
                'Id_Penjadwalan_Det' => 'nullable|integer',
                'Id_Materi' => 'required|integer',
                'Id_Materi_Det' => 'required|integer',
                'Urutan' => 'nullable|integer',
                'Flag_Third_Party' => 'nullable|string|size:1',
                'Score' => 'nullable|numeric',
                'Fallback_Referensi' => 'nullable|string|max:255',
            ]);

            $result = $this->trainingExecutionService->completeActivity($validated);

            return ResponseHelper::success($result, 'Berhasil menyelesaikan aktivitas training.');
        } catch (ValidationException $e) {
            /* Lihat catatan di store(): validasi bergantung pada $request
               sesudah replace(), jadi tak bisa dinaikkan ke luar try. */
            throw $e;
        } catch (Throwable $e) {
            Log::channel('lmsLog')->error('Gagal menyelesaikan aktivitas LMS: '.$e->getMessage(), [
                'payload' => $request->all(),
            ]);

            if (GalatDeadlock::adalah($e)) {
                return ResponseHelper::error(
                    'Progress training sedang diproses bersamaan. Silakan coba lagi beberapa detik lagi.',
                    409,
                );
            }

            return ResponseHelper::error('Terjadi kesalahan saat menyelesaikan aktivitas training.', 500);
        }
    }

    /**
     * Get attendance status for a specific schedule
     */
    public function getStatus($penjadwalanId)
    {
        try {
            $realId = Hashids::connection('custom')->decode($penjadwalanId)[0] ?? (int) $penjadwalanId;

            $records = DB::table('N_LMS_CHECKINOUT')->where('Id_Penjadwalan', $realId)->get();

            $status = [
                'checkin' => ['done' => false, 'time' => null, 'file_url' => null],
                'checkout' => ['done' => false, 'time' => null, 'file_url' => null],
            ];

            foreach ($records as $record) {
                $fileUrl = $record->File_Path
                    ? Storage::disk('gcs')->temporaryUrl($record->File_Path, now()->addMinutes(15))
                    : null;

                if ($record->Output === 'In') {
                    $status['checkin'] = [
                        'done' => true,
                        'time' => Carbon::parse($record->Checktime)->format('Y-m-d H:i:s'),
                        'file_url' => $fileUrl,
                    ];
                } elseif ($record->Output === 'Out') {
                    $status['checkout'] = [
                        'done' => true,
                        'time' => Carbon::parse($record->Checktime)->format('Y-m-d H:i:s'),
                        'file_url' => $fileUrl,
                    ];
                }
            }

            return ResponseHelper::success($status, 'Berhasil mengambil status absensi.');
        } catch (Throwable $e) {
            Log::channel('lmsLog')->error('Gagal mengambil status absensi LMS: '.$e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan saat mengambil status absensi.', 500);
        }
    }
}
