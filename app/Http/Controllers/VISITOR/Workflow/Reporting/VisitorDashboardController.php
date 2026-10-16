<?php

namespace App\Http\Controllers\VISITOR\Workflow\Reporting;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Workflow\Errors\VisitorApiFailure;
use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardAnalitikService;
use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardOperasionalService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard admin Visitor: dua endpoint baca yang dipisah menurut sifat
 * kesegarannya, bukan menurut blok layar.
 *
 * Module: VISITOR · Submodule: Workflow · Feature: Reporting Dashboard.
 */
class VisitorDashboardController extends Controller
{
    private const KUNCI_SEKARANG = 'visitor.dashboard.sekarang';

    private const UMUR_SEKARANG = 60;

    private const UMUR_ANALITIK = 600;

    public function __construct(
        protected VisitorDashboardOperasionalService $operasional,
    ) {}

    public function sekarang(Request $request)
    {
        try {
            // Domain ikut ke dalam KUNCI cache. Tanpa itu, hasil tamu dan permit
            // saling menimpa di kunci yang sama dan tab yang dibuka belakangan
            // menampilkan angka milik tab sebelumnya.
            $domain = in_array($request->input('domain'), ['tamu', 'permit'], true)
                ? (string) $request->input('domain')
                : null;
            $this->pastikanBolehAnalitik($request, $domain, null);
            $kunci = self::KUNCI_SEKARANG.($domain ? '.'.$domain : '');

            if ($request->boolean('segarkan')) {
                Cache::forget($kunci);
            }

            $hasil = Cache::remember($kunci, self::UMUR_SEKARANG, function () use ($domain) {
                return array_merge(
                    $this->operasional->ringkasan(null, $domain),
                    ['dihitung_pada' => Carbon::now()->toIso8601String()],
                );
            });

            return ResponseHelper::success($hasil, 'Berhasil');
        } catch (AuthorizationException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_FORBIDDEN', 403);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'dashboard_operational', 'VISITOR_DASHBOARD_OPERATIONAL_FAILED', 'Keadaan Visitor saat ini gagal dimuat.');
        }
    }

    public function analitik(Request $request, VisitorDashboardAnalitikService $analitik)
    {
        try {
            [$dari, $sampai] = $this->rentang($request);

            // Domain ikut ke dalam KUNCI cache, sama seperti sekarang(). Tanpa
            // itu, muatan tamu dan permit saling menimpa di kunci yang sama dan
            // tab yang dibuka belakangan menampilkan blok milik tab sebelumnya.
            $domain = in_array($request->input('domain'), ['tamu', 'permit'], true)
                ? (string) $request->input('domain')
                : null;
            // Tipe tamu hanya bermakna untuk ranah tamu; halaman Regular dan B2B
            // masing-masing melihat grafik tipenya sendiri (audit ulang 28 Sep).
            $tipe = $domain === 'tamu' ? TipeTamu::sah((string) $request->input('tipe', TipeTamu::REGULAR)) : null;
            $this->pastikanBolehAnalitik($request, $domain, $tipe);

            $kunci = "visitor.dashboard.analitik.{$dari}.{$sampai}".($domain ? '.'.$domain : '').($tipe ? '.'.$tipe : '');

            if ($request->boolean('segarkan')) {
                Cache::forget($kunci);
            }

            $hasil = Cache::remember($kunci, self::UMUR_ANALITIK, function () use ($analitik, $dari, $sampai, $domain, $tipe) {
                return array_merge(
                    $analitik->ringkasan($dari, $sampai, $domain, $tipe),
                    ['dihitung_pada' => Carbon::now()->toIso8601String(), 'dari' => $dari, 'sampai' => $sampai],
                );
            });

            return ResponseHelper::success($hasil, 'Berhasil');
        } catch (AuthorizationException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_FORBIDDEN', 403);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'dashboard_analytics', 'VISITOR_DASHBOARD_ANALYTICS_FAILED', 'Analitik Visitor gagal dimuat.');
        }
    }

    /**
     * Ranah & tipe analitik harus sesuai kunci halaman pemakai: rutenya dijaga
     * beberapa kunci sekaligus (ATAU), jadi `domain`/`tipe` dari permintaan
     * tidak boleh dipercaya begitu saja.
     *
     * @throws AuthorizationException
     */
    private function pastikanBolehAnalitik(Request $request, ?string $domain, ?string $tipe): void
    {
        $user = $request->user();
        $punya = fn (string $kunci) => $user instanceof User
            && app(KeputusanAkses::class)->boleh($user, $kunci);

        $boleh = match (true) {
            $domain === 'permit' => $punya('visitorMonitoringPermitPage'),
            $domain === 'tamu' && $tipe === TipeTamu::B2B => $punya('visitorMonitoringTamuB2BPage'),
            $domain === 'tamu' => $punya('visitorMonitoringTamuPage'),
            default => $punya('visitorMonitoringTamuPage') && $punya('visitorMonitoringPermitPage'),
        };

        if (! $boleh) {
            throw new AuthorizationException('Anda tidak memiliki akses ke analitik ini.');
        }
    }

    /** @return array{0: string, 1: string} */
    private function rentang(Request $request): array
    {
        $dari = $this->tanggal($request->input('dari'), Carbon::today()->subDays(13));
        $sampai = $this->tanggal($request->input('sampai'), Carbon::today());

        if ($dari->gt($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        return [$dari->toDateString(), $sampai->toDateString()];
    }

    private function tanggal(mixed $nilai, Carbon $baku): Carbon
    {
        if (! is_string($nilai) || trim($nilai) === '') {
            return $baku->copy();
        }

        try {
            return Carbon::parse($nilai)->startOfDay();
        } catch (\Throwable) {
            return $baku->copy();
        }
    }
}
