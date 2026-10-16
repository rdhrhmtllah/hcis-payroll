<?php

namespace App\Http\Controllers\KPI\Monitoring\Enforcement;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\KPI\Monitoring\Enforcement\BackfillCapParameterBatchJob;
use App\Services\KPI\KpiCapParameterBackfillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Backfill cap KPI per parameter — operasi migrasi yang di-hit dari Postman.
 *
 * Di luar grup auth dan dikecualikan dari CSRF; dijaga header X-Backfill-Token.
 * Gagal-tertutup: tanpa KPI_BACKFILL_TOKEN di env, semua permintaan ditolak.
 * Pratinjau adalah mode baku — menjalankan harus diminta dengan sengaja.
 */
class KpiBackfillCapController extends Controller
{
    /**
     * Eksekusi & rollback DIKUNCI (12 Okt 2026). Pratinjau dev menunjukkan kolom
     * Score/Achievement tertukar pada sebagian data, sehingga aturan "terdampak"
     * saat ini memotong record yang sah. Kunci dibuka di kode — bukan lewat env
     * yang bisa dinyalakan tanpa sengaja — sesudah aturan final ditetapkan dari
     * `diagnosis_kolom` pratinjau produksi.
     */
    private const EKSEKUSI_DIBUKA = false;

    public function __construct(private KpiCapParameterBackfillService $svc) {}

    private function dikunci()
    {
        return ResponseHelper::error(
            'Eksekusi backfill dikunci sampai aturan final ditetapkan dari hasil pratinjau produksi (diagnosis_kolom).',
            423,
        );
    }

    private function tokenSah(Request $request): bool
    {
        $harap = config('services.kpi.backfill_token');
        $kirim = $request->header('X-Backfill-Token');

        return is_string($harap) && $harap !== '' && is_string($kirim) && hash_equals($harap, $kirim);
    }

    public function jalankan(Request $request)
    {
        if (! $this->tokenSah($request)) {
            return ResponseHelper::error('Token tidak valid.', 401);
        }
        $v = $request->validate([
            'mode' => ['nullable', 'in:pratinjau,jalankan'],
            'requested_by' => ['nullable', 'string', 'max:30'],
        ]);

        if (($v['mode'] ?? 'pratinjau') === 'pratinjau') {
            return ResponseHelper::success($this->svc->pratinjau(), 'Pratinjau backfill cap (tidak ada perubahan ditulis).');
        }
        if (! self::EKSEKUSI_DIBUKA) {
            return $this->dikunci();
        }

        return $this->mulai('jalankan', $v['requested_by'] ?? null);
    }

    public function rollback(Request $request, string $batchId)
    {
        if (! $this->tokenSah($request)) {
            return ResponseHelper::error('Token tidak valid.', 401);
        }
        $request->validate(['requested_by' => ['nullable', 'string', 'max:30']]);
        if (! self::EKSEKUSI_DIBUKA) {
            return $this->dikunci();
        }

        $s = $this->svc->status($batchId);
        if (! $s || $s['mode'] !== 'jalankan') {
            return ResponseHelper::error('Batch backfill tidak ditemukan.', 404);
        }

        return $this->mulai('rollback', $request->input('requested_by'), $batchId);
    }

    public function status(Request $request, string $batchId)
    {
        if (! $this->tokenSah($request)) {
            return ResponseHelper::error('Token tidak valid.', 401);
        }
        $s = $this->svc->status($batchId);

        return $s
            ? ResponseHelper::success($s, 'Status batch backfill cap.')
            : ResponseHelper::error('Batch tidak ditemukan.', 404);
    }

    private function mulai(string $mode, ?string $requestedBy, ?string $sumber = null)
    {
        if (! $this->svc->tabelCadanganAda()) {
            return ResponseHelper::error('Tabel cadangan (DDL-2026-167) belum ada. Jalankan DDL-nya dulu.', 422);
        }
        if ($aktif = $this->svc->batchAktif()) {
            return ResponseHelper::error("Masih ada batch backfill berjalan: {$aktif->Export_Id}.", 409);
        }

        $batchId = $this->svc->mulai($mode, $requestedBy, $sumber);
        BackfillCapParameterBatchJob::dispatch($batchId);
        Log::channel('UpdatePerformanceController')->info('KPI_BACKFILL_CAP_PARAMETER', [
            'batch_id' => $batchId, 'mode' => $mode, 'requested_by' => $requestedBy, 'sumber' => $sumber,
        ]);

        return ResponseHelper::success(['batch_id' => $batchId], 'Backfill dijadwalkan.', 202);
    }

    /** Endpoint lama (cap TOTAL) — metodenya sudah diganti cap per parameter. */
    public function ditarik()
    {
        return ResponseHelper::error(
            'Endpoint ini menjepit TOTAL (metode lama) dan sudah ditarik. Pakai POST /api/v1/kpi-enforcement/backfill-cap-parameter.',
            410,
        );
    }
}
