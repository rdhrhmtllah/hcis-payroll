<?php

namespace App\Http\Controllers\ESS\Attendance\Overtime\Conversion;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Overtime\Conversion\KonversiLemburService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class KonversiLemburController extends Controller
{
    protected $service;

    public function __construct(KonversiLemburService $service)
    {
        $this->service = $service;
    }

    protected function controllerLogger()
    {
        return Log::channel('KonversiLemburController');
    }

    protected function logControllerError(
        string $functionName,
        \Throwable $throwable,
        array $context = [],
    ): void {
        $this->controllerLogger()->error(
            sprintf('[%s] %s', $functionName, $throwable->getMessage()),
            array_merge($context, [
                'function' => $functionName,
                'error' => $throwable->getMessage(),
                'exception' => get_class($throwable),
            ]),
        );
    }

    protected function uploadLampiranKonversi(UploadedFile $file): string
    {
        if (! $file->isValid()) {
            throw new \RuntimeException(
                'File lampiran tidak valid. Kode error upload: '.
                    $file->getError(),
            );
        }

        $extension =
            $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $fileName = Str::uuid().'.'.$extension;
        $folderPath = 'konversi-lembur/'.date('Y/m');
        $targetPath = $folderPath.'/'.$fileName;

        try {
            $storedPath = $file->storeAs($folderPath, $fileName, 'gcs');
            if (! is_string($storedPath) || trim($storedPath) === '') {
                throw new \RuntimeException(
                    'Path file hasil upload tidak valid.',
                );
            }

            return trim($storedPath);
        } catch (\ValueError $e) {
            if (stripos($e->getMessage(), 'Path cannot be empty') === false) {
                throw $e;
            }

            $temporaryPath = $file->getRealPath() ?: $file->getPathname();
            if (! is_string($temporaryPath) || trim($temporaryPath) === '') {
                throw new \RuntimeException(
                    'File upload tidak memiliki temporary path yang valid.',
                );
            }

            $stream = fopen($temporaryPath, 'rb');
            if ($stream === false) {
                throw new \RuntimeException(
                    'Gagal membaca file temporary untuk upload.',
                );
            }

            try {
                $stored = Storage::disk('gcs')->put($targetPath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (! $stored) {
                throw new \RuntimeException(
                    'Gagal menyimpan file lampiran ke storage.',
                );
            }

            return $targetPath;
        }
    }

    public function index()
    {
        $user = Auth::user();
        if (! $user || ! $user->karyawan) {
            abort(403, 'Data karyawan tidak ditemukan.');
        }

        try {
            $kodeKaryawan = (string) $user->karyawan->Kode_Karyawan;
            $this->service->ensureConversionAccessAllowed($kodeKaryawan);
            $meta = $this->service->getRequesterMeta($kodeKaryawan);
            $config = $this->service->getConversionConfig();
            $conversionContext = $this->service->getForcedConversionContext($kodeKaryawan);
        } catch (\Throwable $th) {
            $this->logControllerError('index', $th, [
                'user_id' => $user->id ?? null,
                'kode_karyawan' => $user->karyawan->Kode_Karyawan ?? null,
            ]);
            abort(403, $th->getMessage());
        }

        return Inertia::render('ESS/Attendance/Overtime/Conversion/konversiLembur', [
            'user' => [
                'name' => $meta['nama'],
                'kode' => $meta['kode_karyawan'],
                'level_id' => $meta['level_id'],
                'hutang_cuti' => $conversionContext['hutang_cuti'] ?? 0,
            ],
            'config' => $config,
            'conversionContext' => $conversionContext,
        ]);
    }

    public function getDashboardStats()
    {
        $user = Auth::user();
        if (! $user || ! $user->karyawan) {
            return response()->json(
                ['status' => 'error', 'message' => 'User not found'],
                404,
            );
        }

        try {
            $kodeKaryawan = (string) $user->karyawan->Kode_Karyawan;
            $this->service->ensureConversionAccessAllowed($kodeKaryawan);

            return response()->json([
                'status' => 'success',
                'data' => $this->service->getDashboardStats($kodeKaryawan),
            ]);
        } catch (\Throwable $th) {
            $this->logControllerError('getDashboardStats', $th, [
                'user_id' => $user->id ?? null,
                'kode_karyawan' => $user->karyawan->Kode_Karyawan ?? null,
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Terjadi error di fungsi getDashboardStats: '.
                        $th->getMessage(),
                ],
                422,
            );
        }
    }

    public function getCalendarData(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
        ]);

        $user = Auth::user();
        if (! $user || ! $user->karyawan) {
            return response()->json(
                ['status' => 'error', 'message' => 'User not found'],
                404,
            );
        }

        try {
            $data = $this->service->getCalendarStatus(
                (string) $user->karyawan->Kode_Karyawan,
                (string) $user->karyawan->UserID_Absen,
                (int) $request->month,
                (int) $request->year,
            );
        } catch (\Throwable $th) {
            $this->logControllerError('getCalendarData', $th, [
                'user_id' => $user->id ?? null,
                'kode_karyawan' => $user->karyawan->Kode_Karyawan ?? null,
                'month' => $request->month,
                'year' => $request->year,
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Terjadi error di fungsi getCalendarData: '.
                        $th->getMessage(),
                ],
                422,
            );
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function getChecktime(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);
        try {
            $data = $this->service->getChecktime(
                (string) $request->date,
                (string) Auth::user()->karyawan->Kode_Karyawan,
            );

            return ResponseHelper::success($data);
        } catch (\Throwable $th) {
            $this->logControllerError('getChecktime', $th, [
                'date' => $request->date,
                'kode_karyawan' => Auth::user()?->karyawan?->Kode_Karyawan,
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Terjadi error di fungsi getChecktime: '.
                        $th->getMessage(),
                ],
                422,
            );
        }
    }

    public function getMachineLogs(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'conversion_type' => 'nullable|string|in:DAY_OFF,MATERIAL',
        ]);

        $user = Auth::user();
        if (! $user || ! $user->karyawan) {
            return response()->json(
                ['status' => 'error', 'message' => 'User not found'],
                404,
            );
        }

        $date = $request->date;
        $userIdAbsen = (string) $user->karyawan->UserID_Absen;
        $kodeKaryawan = (string) $user->karyawan->Kode_Karyawan;
        $conversionType = strtoupper(
            (string) $request->input('conversion_type', 'DAY_OFF'),
        );
        $forceWindow = $conversionType === 'DAY_OFF';

        $meta = $this->service->getRequesterMeta($kodeKaryawan);
        $kodePerusahaan = (string) ($meta['kode_perusahaan'] ?? '001');

        try {
            $this->service->ensureConversionAccessAllowed(
                (string) $user->karyawan->Kode_Karyawan,
            );
        } catch (\Throwable $th) {
            $this->logControllerError('getMachineLogs', $th, [
                'user_id' => $user->id ?? null,
                'kode_karyawan' => $kodeKaryawan,
                'date' => $date,
                'conversion_type' => $conversionType,
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Terjadi error di fungsi getMachineLogs: '.
                        $th->getMessage(),
                ],
                422,
            );
        }
        try {
            // OFF-day flow requires raw checktime points first, then client picks in/out.
            $rawLogs = $this->service->getMachineLogs(
                $userIdAbsen,
                (string) $date,
                $kodeKaryawan,
                $kodePerusahaan,
                $forceWindow,
            );
            $pairCandidates = $this->service->getEligibleLogPairs(
                $userIdAbsen,
                (string) $date,
                $kodeKaryawan,
                $kodePerusahaan,
                $forceWindow,
            );
            $rule = $this->service->getConversionRule();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'raw_logs' => $rawLogs,
                    'pairs' => $pairCandidates,
                ],
                'meta' => [
                    'rule' => $rule,
                    'approval_steps' => $this->service->getApprovalFlowPreview(
                        (string) $user->karyawan->Kode_Karyawan,
                        $conversionType,
                    ),
                ],
            ]);
        } catch (\Throwable $th) {
            $this->logControllerError('getMachineLogs', $th, [
                'user_id' => $user->id ?? null,
                'kode_karyawan' => $kodeKaryawan,
                'date' => $date,
                'conversion_type' => $conversionType,
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Terjadi error di fungsi getMachineLogs: '.
                        $th->getMessage(),
                ],
                422,
            );
        }
    }

    public function getDayOffOptions(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $user = Auth::user();
        if (! $user || ! $user->karyawan) {
            return response()->json(
                ['status' => 'error', 'message' => 'User not found'],
                404,
            );
        }

        $startDate = $request->input('start_date', now()->toDateString());
        $endDate = $request->input(
            'end_date',
            now()->addMonths(6)->toDateString(),
        );

        try {
            $options = $this->service->getDayOffDateOptions(
                (string) $user->karyawan->Kode_Karyawan,
                (string) $startDate,
                (string) $endDate,
            );

            return response()->json([
                'status' => 'success',
                'data' => $options,
            ]);
        } catch (\Throwable $th) {
            $this->logControllerError('getDayOffOptions', $th, [
                'user_id' => $user->id ?? null,
                'kode_karyawan' => $user->karyawan->Kode_Karyawan ?? null,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Terjadi error di fungsi getDayOffOptions: '.
                        $th->getMessage(),
                ],
                422,
            );
        }
    }

    public function getConversionContext()
    {
        $user = Auth::user();
        if (! $user || ! $user->karyawan) {
            return response()->json(
                ['status' => 'error', 'message' => 'User not found'],
                404,
            );
        }

        try {
            $kodeKaryawan = (string) $user->karyawan->Kode_Karyawan;
            $this->service->ensureConversionAccessAllowed($kodeKaryawan);

            return response()->json([
                'status' => 'success',
                'data' => $this->service->getForcedConversionContext(
                    $kodeKaryawan,
                ),
            ]);
        } catch (\Throwable $th) {
            $this->logControllerError('getConversionContext', $th, [
                'user_id' => $user->id ?? null,
                'kode_karyawan' => $user->karyawan->Kode_Karyawan ?? null,
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Terjadi error di fungsi getConversionContext: '.
                        $th->getMessage(),
                ],
                422,
            );
        }
    }

    public function getApprovalPreview(Request $request)
    {
        $request->validate([
            'jenis_konversi' => 'nullable|string|in:DAY_OFF,MATERIAL,TO_CUTI',
        ]);

        $user = Auth::user();
        if (! $user || ! $user->karyawan) {
            return response()->json(
                ['status' => 'error', 'message' => 'User not found'],
                404,
            );
        }

        try {
            $preview = $this->service->getSubmissionApprovalPreview(
                (string) $user->karyawan->Kode_Karyawan,
                (string) $request->input('jenis_konversi', 'DAY_OFF'),
            );

            return response()->json([
                'status' => 'success',
                'data' => $preview,
            ]);
        } catch (\Throwable $th) {
            $this->logControllerError('getApprovalPreview', $th, [
                'user_id' => $user->id ?? null,
                'kode_karyawan' => $user->karyawan->Kode_Karyawan ?? null,
                'jenis_konversi' => $request->input(
                    'jenis_konversi',
                    'DAY_OFF',
                ),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Terjadi error di fungsi getApprovalPreview: '.
                        $th->getMessage(),
                ],
                422,
            );
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'tanggal_lembur' => 'required|date',
            'tanggal_off' => 'nullable|date|required_if:jenis_konversi,DAY_OFF',
            'check_in' => 'required|date',
            'check_out' => 'required|date',
            'durasi_jam' => 'required|numeric|min:0',
            'jenis_konversi' => 'required|string|in:DAY_OFF,MATERIAL,TO_CUTI',
            'alasan' => 'nullable|string|max:300',
            'lampiran' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:10240',
        ]);

        $wantsJson = $request->expectsJson() || $request->ajax();

        $user = Auth::user();
        if (! $user || ! $user->karyawan) {
            if ($wantsJson) {
                return response()->json(
                    [
                        'status' => 'error',
                        'message' => 'Data karyawan tidak ditemukan.',
                    ],
                    404,
                );
            }

            return redirect()
                ->back()
                ->with('error', 'Data karyawan tidak ditemukan.');
        }

        try {
            $payload = $request->except('lampiran');
            if ($request->hasFile('lampiran')) {
                $payload['lampiran'] = $this->uploadLampiranKonversi(
                    $request->file('lampiran'),
                );
            }

            $result = $this->service->createTransaction($payload, $user);

            if ($wantsJson) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => $result,
                ]);
            }

            return redirect()->back()->with('success', $result['message']);
        } catch (\Throwable $th) {
            $this->logControllerError('store', $th, [
                'user_id' => $user->id ?? null,
                'kode_karyawan' => $user->karyawan->Kode_Karyawan ?? null,
                'payload' => $request->except(['lampiran']),
            ]);

            if ($wantsJson) {
                return response()->json(
                    [
                        'status' => 'error',
                        'message' => 'Terjadi error di fungsi store: '.
                            $th->getMessage(),
                    ],
                    422,
                );
            }

            return redirect()
                ->back()
                ->with(
                    'error',
                    'Terjadi error di fungsi store: '.$th->getMessage(),
                );
        }
    }

    public function history(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->karyawan) {
            return response()->json(
                ['status' => 'error', 'message' => 'User not found'],
                404,
            );
        }

        try {
            $kodeKaryawan = (string) $user->karyawan->Kode_Karyawan;
            $this->service->ensureConversionAccessAllowed($kodeKaryawan);
            $history = $this->service->getHistoryForUser($kodeKaryawan);
        } catch (\Throwable $th) {
            $this->logControllerError('history', $th, [
                'user_id' => $user->id ?? null,
                'kode_karyawan' => $user->karyawan->Kode_Karyawan ?? null,
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Terjadi error di fungsi history: '.$th->getMessage(),
                ],
                422,
            );
        }

        return response()->json([
            'status' => 'success',
            'data' => $history,
        ]);
    }

    public function cancel(Request $request, $no_transaksi)
    {
        $user = Auth::user();
        if (! $user || ! $user->karyawan) {
            return response()->json(
                ['status' => 'error', 'message' => 'User not found'],
                404,
            );
        }

        try {
            $kodeKaryawan = (string) $user->karyawan->Kode_Karyawan;
            $this->service->ensureConversionAccessAllowed($kodeKaryawan);

            $result = $this->service->cancelTransaction(
                $no_transaksi,
                $kodeKaryawan,
            );

            return response()->json($result);
        } catch (\Throwable $th) {
            $this->logControllerError('cancel', $th, [
                'user_id' => $user->id ?? null,
                'kode_karyawan' => $user->karyawan->Kode_Karyawan ?? null,
                'no_transaksi' => $no_transaksi,
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Terjadi error di fungsi cancel: '.$th->getMessage(),
                ],
                422,
            );
        }
    }
}
