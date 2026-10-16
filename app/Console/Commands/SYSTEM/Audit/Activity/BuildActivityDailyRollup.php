<?php

namespace App\Console\Commands\SYSTEM\Audit\Activity;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuildActivityDailyRollup extends Command
{
    protected $signature = 'activity:rollup
        {--date= : Tanggal target (YYYY-MM-DD). Default = kemarin}
        {--days=1 : Jumlah hari mundur yang di-rollup}';

    protected $description = 'Build harian rollup dari N_HRIS_Activity_Event/Session ke N_HRIS_Activity_Daily.';

    public function handle(): int
    {
        $conn = (string) config('activity-tracker.db_connection', config('database.default'));

        $startDate = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::yesterday()->startOfDay();

        $days = max(1, (int) $this->option('days'));

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->subDays($i);
            $this->rollupOneDay($conn, $date);
        }

        return self::SUCCESS;
    }

    protected function rollupOneDay(string $conn, Carbon $date): void
    {
        $from = $date->copy()->startOfDay();
        $to = $date->copy()->endOfDay();
        $this->info("Rolling up {$date->toDateString()} …");

        try {
            // Hitung agregat per Kode_Karyawan
            $eventAgg = DB::connection($conn)->table('N_HRIS_Activity_Event')
                ->select([
                    'Kode_Karyawan',
                    DB::raw('MAX(UserID_Web) as UserID_Web'),
                    DB::raw('COUNT(*) as Total_Events'),
                    DB::raw("SUM(CASE WHEN Event_Type = 'pageview' THEN 1 ELSE 0 END) as Total_Pageviews"),
                    DB::raw("SUM(CASE WHEN Event_Type = 'auth.login' THEN 1 ELSE 0 END) as Total_Login"),
                    DB::raw("SUM(CASE WHEN Event_Type = 'auth.failed' THEN 1 ELSE 0 END) as Total_Failed_Login"),
                    DB::raw('MIN(Created_At) as First_Login_At'),
                    DB::raw('MAX(Created_At) as Last_Activity_At'),
                    DB::raw('COUNT(DISTINCT Id_Grup_Fitur) as Distinct_Modules'),
                    DB::raw('COUNT(DISTINCT Ip_Address) as Distinct_Ips'),
                ])
                ->whereNotNull('Kode_Karyawan')
                ->whereBetween('Created_At', [$from, $to])
                ->groupBy('Kode_Karyawan')
                ->get();

            // Active seconds + distinct devices dari Session
            $sessionAgg = DB::connection($conn)->table('N_HRIS_Activity_Session')
                ->select([
                    'Kode_Karyawan',
                    DB::raw('SUM(Active_Seconds) as Active_Seconds'),
                    DB::raw('COUNT(DISTINCT Device_Fingerprint) as Distinct_Devices'),
                ])
                ->whereNotNull('Kode_Karyawan')
                ->whereBetween('Login_At', [$from, $to])
                ->groupBy('Kode_Karyawan')
                ->get()
                ->keyBy('Kode_Karyawan');

            // Top module per user
            $topModules = DB::connection($conn)
                ->table('N_HRIS_Activity_Event')
                ->select('Kode_Karyawan', 'Id_Grup_Fitur', DB::raw('COUNT(*) as Hits'))
                ->whereNotNull('Kode_Karyawan')
                ->whereNotNull('Id_Grup_Fitur')
                ->whereBetween('Created_At', [$from, $to])
                ->groupBy('Kode_Karyawan', 'Id_Grup_Fitur')
                ->orderByDesc('Hits')
                ->get()
                ->groupBy('Kode_Karyawan')
                ->map(fn ($g) => $g->first()->Id_Grup_Fitur)
                ->all();

            $upsertCount = 0;
            foreach ($eventAgg as $row) {
                $kode = $row->Kode_Karyawan;
                $sess = $sessionAgg[$kode] ?? null;
                $activeMinutes = $sess ? (int) round(((int) $sess->Active_Seconds) / 60) : 0;

                DB::connection($conn)->table('N_HRIS_Activity_Daily')->updateOrInsert(
                    [
                        'Activity_Date' => $from->toDateString(),
                        'Kode_Karyawan' => $kode,
                    ],
                    [
                        'UserID_Web' => $row->UserID_Web,
                        'Total_Login' => (int) $row->Total_Login,
                        'Total_Failed_Login' => (int) $row->Total_Failed_Login,
                        'Total_Events' => (int) $row->Total_Events,
                        'Total_Pageviews' => (int) $row->Total_Pageviews,
                        'Active_Minutes' => $activeMinutes,
                        'First_Login_At' => $row->First_Login_At,
                        'Last_Activity_At' => $row->Last_Activity_At,
                        'Top_Id_Grup_Fitur' => $topModules[$kode] ?? null,
                        'Distinct_Modules' => (int) $row->Distinct_Modules,
                        'Distinct_Devices' => $sess ? (int) $sess->Distinct_Devices : 0,
                        'Distinct_Ips' => (int) $row->Distinct_Ips,
                        'Updated_At' => now(),
                    ],
                );
                $upsertCount++;
            }

            $this->info("  upserted {$upsertCount} rows");
        } catch (\Throwable $e) {
            Log::error('BuildActivityDailyRollup failed', [
                'date' => $date->toDateString(),
                'error' => $e->getMessage(),
            ]);
            $this->error('Failed: '.$e->getMessage());
        }
    }
}
