<?php

namespace App\Services\ESS\Dashboard\Homepage;

use App\Models\ESS\Employee\Models\Karyawan;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HomepageAbsensiService
{
    public function __construct(HrisAttendanceSnapshotService $attendanceSnapshotService)
    {
        $this->attendanceService = $attendanceSnapshotService;
    }

    /**
     * Dapatkan summary absensi untuk user saat ini di Homepage
     */
    public function getSummary(string $kodeKaryawan, string $userId): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $startOfWeek = Carbon::today()->subDays(6)->format('Y-m-d'); // 7 hari terakhir

        // 1. Ambil data absensi 7 hari terakhir dari SP
        // $absenData = DB::select(
        //     "Exec HRIS_SP_GET_ABSEN_NEW_V3 '001', ?, ?, ?, '', ''",
        //     [$startOfWeek, $today, $userId]
        // );
        $sourceReuqest = 'homepage-absensi-service-get-summary';
        $tglMulai = $startOfWeek;
        $tglSelesai = $today;
        $listUserId = $userId;
        $response = $this->attendanceService->getAttendance(
            kodePerusahaan: '001',
            tglAwal: $tglMulai,
            tglAkhir: $tglSelesai,
            userIdList: $listUserId,
            executionMode: 'SYNC',

            requestSource: $sourceReuqest,
            requestedBy: Auth::user()->karyawan->Kode_Karyawan ?? 'web-'.$sourceReuqest,
            priority: 1,
        );

        if (! $response['success']) {
            throw new \Exception($response['error'] ?? 'Gagal mengambil data absensi.');
        }

        $absenData = $response['data'] ?? [];

        $absenCollection = collect($absenData);
        $todayData = $absenCollection->firstWhere('Tanggal', $today);

        // 2. Ambil sisa cuti dari model Karyawan
        $karyawan = Karyawan::where('Kode_Karyawan', $kodeKaryawan)->first();
        $sisaCuti = $karyawan ? $karyawan->sisa_cuti() : 0;

        // 3. Kalkulasi metrik hari ini
        $statusHariIni = $todayData->Status ?? 'BELUM CHECK IN';
        $checkIn = $todayData->Check_In ?? '--:--';
        $checkOut = $todayData->Check_Out ?? '--:--';
        $lateMinutes = (int) ($todayData->RealLate ?? 0);

        // Jika hari ini libur atau belum ada jadwal, statusnya sesuaikan
        if (empty($todayData->Shift_Name)) {
            $statusHariIni = 'LIBUR';
        }

        // 4. Kalkulasi metrik mingguan (7 hari terakhir)
        $weeklyStats = [];
        $totalHadir = 0;
        $totalHariKerja = 0;
        $totalTerlambatMenit = 0;
        $totalLemburJam = 0;
        $totalSakit = 0;
        $totalIzin = 0;
        $totalCuti = 0;

        foreach ($absenCollection as $row) {
            if (! empty($row->Shift_Name) && strtoupper($row->Shift_Name) !== 'OFF') {
                $totalHariKerja++;
                if (strtoupper($row->Status) === 'HADIR' || strtoupper($row->Status) === 'TERLAMBAT') {
                    $totalHadir++;
                    $weeklyStats[] = 100; // Hadir = 100% (bisa disesuaikan logikanya)
                } else {
                    $weeklyStats[] = 0;
                }
            } else {
                // Jika libur, abaikan atau beri nilai default untuk chart
                $weeklyStats[] = 0;
            }

            $totalTerlambatMenit += (int) ($row->RealLate ?? 0);

            // Hitung status izin/sakit/cuti
            $statusToUpper = strtoupper($row->Status ?? '');
            if ($statusToUpper === 'SAKIT') {
                $totalSakit++;
            }
            if ($statusToUpper === 'IZIN') {
                $totalIzin++;
            }
            if ($statusToUpper === 'CUTI') {
                $totalCuti++;
            }
        }

        $attendanceRate = $totalHariKerja > 0 ? round(($totalHadir / $totalHariKerja) * 100) : 0;

        // 5. Ambil log CHECKINOUT hari ini untuk timeline
        $recentLogs = $this->getTodayCheckInOutLogs($userId, $today);

        return [
            'status' => $statusHariIni,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'late_minutes' => $lateMinutes,
            'leave_remaining' => $sisaCuti,
            'attendance_rate' => $attendanceRate,
            'total_hadir' => $totalHadir,
            'total_hari_kerja' => $totalHariKerja,
            'total_terlambat_menit' => $totalTerlambatMenit,
            'total_lembur_jam' => $totalLemburJam,
            'total_sakit' => $totalSakit,
            'total_izin' => $totalIzin,
            'total_cuti' => $totalCuti,
            'weekly_stats' => $weeklyStats,
            'recent_logs' => $recentLogs,
        ];
    }

    /**
     * Ambil data raw dari tabel CHECKINOUT untuk timeline hari ini
     */
    private function getTodayCheckInOutLogs(string $userId, string $today): array
    {
        // Range sargable [today 00:00 .. besok 00:00) menggantikan whereDate(CHECKTIME)
        // non-sargable (CHECKINOUT besar -> index CHECKTIME bisa terpakai). Subquery USERID
        // di-parameterisasi (sebelumnya $userId diinterpolasi ke string SQL = risiko injection
        // + recompile per nilai).
        $todayStart = \Carbon\Carbon::parse($today, 'Asia/Jakarta')->startOfDay()->toDateTimeString();
        $tomorrowStart = \Carbon\Carbon::parse($today, 'Asia/Jakarta')->addDay()->startOfDay()->toDateTimeString();

        $logs = DB::table('CHECKINOUT as c')
            ->join('Machines as m', 'c.sensorid', '=', 'm.MachineNumber')
            ->whereRaw('c.USERID = (SELECT USERID FROM USERINFO WHERE Badgenumber = ?)', [$userId])
            ->where('c.CHECKTIME', '>=', $todayStart)
            ->where('c.CHECKTIME', '<', $tomorrowStart)
            ->orderBy('c.CHECKTIME', 'asc')
            ->select('c.CHECKTIME', 'c.CHECKTYPE', 'm.MachineAlias')
            ->get();

        $formattedLogs = [];
        foreach ($logs as $log) {
            $time = Carbon::parse($log->CHECKTIME)->format('H:i');

            // Map checktype ke activity
            $type = 'activity';
            $activity = 'Absen';
            if ($log->CHECKTYPE === 'I') {
                $type = 'checkin';
                $activity = 'Check In';
            } elseif ($log->CHECKTYPE === 'O') {
                $type = 'checkout';
                $activity = 'Check Out';
            }

            $formattedLogs[] = [
                'time' => $time,
                'activity' => $activity.' - '.($log->MachineAlias ?? 'Mesin Absen'),
                'type' => $type,
                'status' => 'Tercatat',
            ];
        }

        return $formattedLogs;
    }
}
