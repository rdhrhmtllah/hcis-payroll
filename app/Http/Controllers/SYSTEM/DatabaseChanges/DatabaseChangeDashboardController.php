<?php

namespace App\Http\Controllers\SYSTEM\DatabaseChanges;

use App\Http\Controllers\Controller;
use App\Models\SYSTEM\DatabaseChanges\Models\SystemDatabaseChange;
use App\Services\SYSTEM\DatabaseChanges\DatabaseChangeDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class DatabaseChangeDashboardController extends Controller
{
    public function __construct(private readonly DatabaseChangeDashboardService $service) {}

    public function page()
    {
        return Inertia::render('SYSTEM/DatabaseChanges/DatabaseChangeDashboardPage');
    }

    public function index(Request $request)
    {
        return response()->json($this->service->list($request->only('type', 'stage')));
    }

    public function sql(SystemDatabaseChange $databaseChange)
    {
        try {
            return response()->json($this->service->sql($databaseChange));
        } catch (Throwable $exception) {
            // 422, bukan 404. normalizeApiError() di frontend MEMBUANG pesan
            // server pada 404 dan selalu menampilkan "Data yang diminta tidak
            // ditemukan" — sehingga sebab sebenarnya (berkas belum ada di
            // worktree ini, atau jalurnya tidak diizinkan) tidak pernah terbaca.
            // Cabang 422 adalah satu-satunya yang meneruskan data.message.
            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => 'VALIDATION_FAILED',
            ], 422);
        }
    }

    public function bulkSql(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        try {
            return response()->json($this->service->bulkSql($validated['ids']));
        } catch (Throwable $exception) {
            // 422 supaya pesannya sampai ke layar; normalizeApiError() membuang
            // data.message pada 404.
            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => 'VALIDATION_FAILED',
            ], 422);
        }
    }

    public function update(Request $request, SystemDatabaseChange $databaseChange)
    {
        $validated = $request->validate(['stage' => ['required', 'string'], 'status' => ['nullable', 'string'], 'reference' => ['nullable', 'string', 'max:100']]);
        try {
            return response()->json(['data' => $this->service->updateStage($databaseChange, $validated['stage'], $validated)]);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function batch(Request $request)
    {
        $validated = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer'], 'stage' => ['required', 'string'], 'status' => ['nullable', 'string'], 'reference' => ['nullable', 'string', 'max:100']]);
        try {
            return response()->json(['data' => $this->service->updateBatch($validated['ids'], $validated['stage'], $validated)]);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
