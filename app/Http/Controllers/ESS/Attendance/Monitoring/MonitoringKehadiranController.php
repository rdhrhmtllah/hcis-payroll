<?php

namespace App\Http\Controllers\ESS\Attendance\Monitoring;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Monitoring\KategoriKehadiran;
use App\Services\ESS\Attendance\Monitoring\MonitoringHariIniService;
use App\Services\ESS\Attendance\Monitoring\MonitoringKehadiranQuery;
use App\Services\ESS\Attendance\Monitoring\PeriodeCutoff;
use App\Services\ESS\Attendance\Monitoring\RekapKehadiranExportService;
use App\Services\ESS\Attendance\Monitoring\RekapPeriodeService;
use App\Services\ESS\Attendance\Monitoring\SaringanMonitoring;
use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use InvalidArgumentException;
use Throwable;

/**
 * Monitoring Kehadiran untuk admin HC Personel — murni baca. Setiap aksi
 * dilakukan lewat tautan ke halaman admin terkait.
 */
class MonitoringKehadiranController extends Controller
{
    /** Tautan aksi => halaman admin yang dituju; hanya yang boleh dibuka pengguna yang dikirim. */
    private const TAUTAN = [
        'perbaikan_absensi' => ['page' => 'perbaikanCheckinOut', 'url' => '/perbaiki-checkinout'],
        'izin' => ['page' => 'IzinPageAdmin', 'url' => '/izinAdmin'],
        'cuti' => ['page' => 'adminHCCutiManagementPage', 'url' => '/admin/cuti'],
        'lembur' => ['page' => 'OvertimeManagementAdmin', 'url' => '/lembur-management/admin'],
    ];

    public function __construct(
        private MonitoringHariIniService $hariIni,
        private RekapPeriodeService $rekap,
    ) {}

    public function index(Request $request, FunctionalRole $role)
    {
        $daftarPeriode = PeriodeCutoff::daftar(now(), 12);

        try {
            $opsi = MonitoringKehadiranQuery::opsiSaringan($role, $daftarPeriode[0]['mulai'], $daftarPeriode[0]['akhir']);
        } catch (Throwable $e) {
            // Saringan hilang lebih baik daripada seluruh halaman gagal dibuka.
            report($e);
            $opsi = array_fill_keys(array_keys(SaringanMonitoring::ORGANISASI), []);
        }

        $user = $request->user();
        $tautan = [];
        foreach (self::TAUTAN as $kunci => $t) {
            if ($user->memilikiJabatan($t['page'])) {
                $tautan[$kunci] = $t['url'];
            }
        }

        return Inertia::render('ESS/Attendance/Monitoring/MonitoringKehadiran', [
            'opsiSaringan' => $opsi,
            'daftarPeriode' => $daftarPeriode,
            'hariIni' => now()->toDateString(),
            'tautan' => $tautan,
            'kategori' => collect(KategoriKehadiran::URUTAN)->map(fn ($k) => ['kunci' => $k, 'label' => KategoriKehadiran::LABEL[$k]])->all(),
            'maksHari' => SaringanMonitoring::MAKS_HARI,
        ]);
    }

    public function hariIni(Request $request, FunctionalRole $role): JsonResponse
    {
        $tanggal = SaringanMonitoring::tanggal($request->query('tanggal')) ?? now()->toDateString();

        try {
            return ResponseHelper::success([
                'ringkasan' => $this->hariIni->ringkasan($tanggal, $request->query(), $role),
                'analitik' => $this->hariIni->analitik($tanggal, $request->query(), $role),
                'daftar' => $this->hariIni->daftar($tanggal, $request->query(), $role),
                'grafik' => $this->hariIni->grafik($tanggal, $request->query(), $role),
            ]);
        } catch (Throwable $e) {
            return ResponseHelper::galatServer($e, 'Data kehadiran hari ini gagal dimuat.');
        }
    }

    public function rekap(Request $request, FunctionalRole $role): JsonResponse
    {
        return $this->denganRentang($request, fn ($r) => ResponseHelper::success(
            $this->rekap->rekapKaryawan($r['dari'], $r['sampai'], $request->query(), $role)
        ), 'Rekap periode gagal dimuat.');
    }

    public function rekapKelompok(Request $request, FunctionalRole $role): JsonResponse
    {
        $dimensi = (string) $request->query('dimensi', 'divisi');

        return $this->denganRentang($request, fn ($r) => ResponseHelper::success([
            'dimensi' => array_key_exists($dimensi, RekapPeriodeService::DIMENSI) ? $dimensi : 'divisi',
            'items' => $this->rekap->rekapKelompok($r['dari'], $r['sampai'], $dimensi, $request->query(), $role),
        ]), 'Rekap per kelompok gagal dimuat.');
    }

    public function grafik(Request $request, FunctionalRole $role): JsonResponse
    {
        return $this->denganRentang($request, fn ($r) => ResponseHelper::success(
            $this->rekap->grafik($r['dari'], $r['sampai'], (string) $request->query('dimensi', 'departemen'), $request->query(), $role)
        ), 'Data grafik gagal dimuat.');
    }

    public function rincian(Request $request, string $kode, FunctionalRole $role): JsonResponse
    {
        return $this->denganRentang($request, function ($r) use ($kode, $role) {
            $rincian = $this->rekap->rincian($kode, $r['dari'], $r['sampai'], $role);

            return $rincian
                ? ResponseHelper::success($rincian)
                : ResponseHelper::errorWithCode('Data karyawan tidak ditemukan pada periode ini.', 'ESS_MONITORING_RINCIAN_NOT_FOUND', 404);
        }, 'Rincian harian gagal dimuat.');
    }

    public function mintaEkspor(Request $request, RekapKehadiranExportService $ekspor): JsonResponse
    {
        try {
            $rentang = SaringanMonitoring::rentang($request->input('dari'), $request->input('sampai'));
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'ESS_MONITORING_RENTANG_INVALID', 422);
        }

        $filter = SaringanMonitoring::normalisasi($request->all(), RekapPeriodeService::URUT);
        $filter = array_intersect_key($filter, SaringanMonitoring::ORGANISASI + ['search' => true, 'urut' => true, 'arah' => true]);

        try {
            $exportId = $ekspor->makeExportId();
            $ekspor->startJob($exportId, 'xlsx', $rentang + [
                'filter' => $filter,
                'user_id' => (int) Auth::user()->Id_Users,
            ], 0, $this->pelaku());
            $ekspor->dispatchJob($exportId);
        } catch (Throwable $e) {
            return ResponseHelper::galatServer($e, 'Ekspor gagal dimulai.');
        }

        return ResponseHelper::success(['export_id' => $exportId], 'Ekspor sedang diproses.');
    }

    public function daftarEkspor(RekapKehadiranExportService $ekspor): JsonResponse
    {
        return ResponseHelper::success(['items' => $ekspor->getList($this->pelaku(), 5)]);
    }

    public function statusEkspor(string $exportId, RekapKehadiranExportService $ekspor): JsonResponse
    {
        $progres = $ekspor->progresMilik($exportId, $this->pelaku());

        return $progres
            ? ResponseHelper::success($progres)
            : ResponseHelper::errorWithCode('Ekspor tidak ditemukan.', 'ESS_MONITORING_EXPORT_NOT_FOUND', 404);
    }

    public function unduhEkspor(string $exportId, RekapKehadiranExportService $ekspor)
    {
        $job = $ekspor->jobMilik($exportId, $this->pelaku());

        if (! $job || $job->Status !== RekapKehadiranExportService::STATUS_READY || ! $job->File_Path) {
            abort(404, 'Berkas ekspor belum siap.');
        }
        if (! Storage::disk(RekapKehadiranExportService::DISK)->exists($job->File_Path)) {
            abort(404, 'Berkas ekspor sudah tidak tersedia.');
        }

        $ekspor->markDownloaded($exportId);

        return Storage::disk(RekapKehadiranExportService::DISK)->download($job->File_Path, $job->File_Name);
    }

    /** Rentang terbalik / > 62 hari = 422, bukan dipotong diam-diam. */
    private function denganRentang(Request $request, \Closure $aksi, string $pesanGagal): JsonResponse
    {
        try {
            $rentang = SaringanMonitoring::rentang($request->query('dari'), $request->query('sampai'));
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'ESS_MONITORING_RENTANG_INVALID', 422);
        }

        try {
            return $aksi($rentang);
        } catch (Throwable $e) {
            return ResponseHelper::galatServer($e, $pesanGagal);
        }
    }

    private function pelaku(): string
    {
        $user = Auth::user();

        return (string) ($user?->Username ?? $user?->Kode_Users ?? 'tidak diketahui');
    }
}
