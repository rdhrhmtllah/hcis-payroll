<?php

namespace App\Services\ESS\Dashboard\Homepage;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HomepageVisitorService
{
    private const T_HEADER = 'N_EMI_VISITOR_Transaksi_Tamu';

    private const T_DETAIL = 'N_EMI_VISITOR_Transaksi_Tamu_Detail';

    public function __construct(
        private VisitorKehadiranQueryService $kehadiran,
    ) {}

    /**
     * Dapatkan summary visitor untuk Homepage
     */
    public function getSummary(string $kodeKaryawanPIC): array
    {
        $today = Carbon::today()->toDateString();

        // Query base untuk hari ini khusus untuk PIC (atau seluruhnya jika super admin, tp default ke PIC)
        $baseQuery = DB::table(self::T_HEADER)
            ->where('Flag_Aktif', 'Y')
            ->whereDate('Tanggal_Kunjungan', $today)
            // Asumsi: karyawan melihat visitor untuk departemen/dirinya sendiri
            // Kalau untuk global bisa di-comment baris di bawah ini
            ->where('Kode_Karyawan_PIC', $kodeKaryawanPIC);

        $totalToday = (clone $baseQuery)->count();

        // Kehadiran DITURUNKAN dari waktu masuk/keluar, bukan dari
        // Status_Kunjungan. Kolom status hanya ditulis tombol manual HCIS;
        // aplikasi palang di gerbang menulis waktunya ke N_VISITOR_Tamu_Efektif
        // dan tidak menyentuh status, sehingga tamu yang benar-benar berada di
        // gedung dulu tidak pernah terhitung di kartu ini.
        // Query tersendiri, bukan clone dari $baseQuery: begitu tabel efektif
        // ikut di-join, kolom tak berkualifikasi seperti Tanggal_Kunjungan jadi
        // ambigu karena tabel itu memilikinya juga.
        $kehadiranQuery = DB::table(self::T_HEADER.' as h')
            ->join(self::T_DETAIL.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi');

        $activeNow = $this->kehadiran
            ->join($kehadiranQuery)
            ->where('h.Flag_Aktif', 'Y')
            ->whereDate('h.Tanggal_Kunjungan', $today)
            ->where('h.Kode_Karyawan_PIC', $kodeKaryawanPIC)
            ->whereRaw($this->kehadiran->diDalamSql())
            ->count();

        $scheduledAppointments = (clone $baseQuery)
            ->whereIn('Status_Kunjungan', [StatusKunjunganTamu::SCHEDULED, StatusKunjunganTamu::PENDING_APPROVAL])
            ->count();

        // Hitung kepadatan per jam (chart)
        $hourlyData = (clone $baseQuery)
            ->select(DB::raw('DATEPART(HOUR, Created_At) as hour'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('DATEPART(HOUR, Created_At)'))
            ->pluck('count', 'hour')
            ->toArray();

        // Format chart array untuk jam kerja (08:00 - 17:00)
        $hourlyVisitors = [];
        for ($i = 8; $i <= 17; $i++) {
            $hourlyVisitors[] = $hourlyData[$i] ?? 0;
        }

        // Ambil 3 visitor terbaru
        $recentVisitors = (clone $baseQuery)
            ->join(self::T_DETAIL.' as d', self::T_HEADER.'.Id_Transaksi_Tamu', '=', 'd.Id_Transaksi_Tamu')
            ->orderBy('Created_At', 'desc')
            ->limit(3)
            ->select('d.Nama_Lengkap_Tamu', 'd.Instansi_Atau_Asal', 'Created_At', 'Status_Kunjungan')
            ->get()
            ->map(function ($visitor) {
                return [
                    'name' => $visitor->Nama_Lengkap_Tamu ?? 'Unknown',
                    'company' => $visitor->Instansi_Atau_Asal ?? 'Unknown',
                    'time' => Carbon::parse($visitor->Created_At)->format('H:i'),
                    'status' => $this->formatStatus($visitor->Status_Kunjungan),
                ];
            })->toArray();

        return [
            'total_today' => $totalToday,
            'active_now' => $activeNow,
            'scheduled_appointments' => $scheduledAppointments,
            'top_purpose' => 'Business/Meeting', // Bisa di-query GROUP BY Deksripsi_Keperluan jika perlu
            'average_duration' => '45 min', // Mock default
            'hourly_visitors' => $hourlyVisitors,
            'recent_visitors' => $recentVisitors,
        ];
    }

    private function formatStatus(?string $status): string
    {
        // Status TERSIMPAN (keputusan manusia); kehadiran dihitung terpisah.
        return match ($status) {
            StatusKunjunganTamu::SCHEDULED => 'Scheduled',
            StatusKunjunganTamu::PENDING_APPROVAL => 'Waiting',
            StatusKunjunganTamu::REJECTED => 'Rejected',
            StatusKunjunganTamu::WITHDRAWN => 'Withdrawn',
            StatusKunjunganTamu::REVOKED => 'Revoked',
            default => 'Unknown'
        };
    }
}
