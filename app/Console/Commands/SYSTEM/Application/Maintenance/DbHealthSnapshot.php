<?php

namespace App\Console\Commands\SYSTEM\Application\Maintenance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Layer 3 diagnostics: snapshot blocking chain + wait stats SQL Server.
 * OUT-OF-BAND (proses terjadwal terpisah) → TIDAK menyentuh request user.
 * Menulis HANYA saat ADA blocking / request aktif tinggi. Menjawab "transaksi
 * MANA yang nge-lock": baris Blocking_Session_Id<>0 = terblok; sesi yang jadi
 * blocker orang lain = penahan lock. Context_Info = Trace_Id (bila Layer 2 ON).
 *
 * Adaptasi dari staging-loadtest/11b-diagnose-compact.sql.
 */
class DbHealthSnapshot extends Command
{
    protected $signature = 'db:health-snapshot';

    protected $description = 'Snapshot blocking chain + wait stats SQL Server (Layer 3 diagnostics, out-of-band).';

    public function handle(): int
    {
        if (! (bool) config('request-diagnostics.snapshot.enabled', true)) {
            return self::SUCCESS;
        }

        $channels = (array) config('request-diagnostics.snapshot.channels', ['stderr']);

        // Ambil potret request aktif + rantai blocking. Bila DB tak terjangkau,
        // itu SENDIRI sinyal (gagal koneksi) — catat lalu keluar.
        try {
            $rows = DB::select(
                'SELECT r.session_id AS Session_Id,
                        r.status AS Status,
                        ISNULL(r.wait_type, \'\') AS Wait_Type,
                        r.wait_time AS Wait_Time_Ms,
                        r.blocking_session_id AS Blocking_Session_Id,
                        r.cpu_time AS Cpu_Ms,
                        s.login_name AS Login_Name,
                        s.host_name AS Host_Name,
                        s.program_name AS Program_Name,
                        LTRIM(RTRIM(REPLACE(CONVERT(varchar(128), s.context_info), CHAR(0), \'\'))) AS Context_Info,
                        CAST(SUBSTRING(st.text, 1, 600) AS varchar(600)) AS Sql_Text
                 FROM sys.dm_exec_requests r
                 JOIN sys.dm_exec_sessions s ON r.session_id = s.session_id
                 OUTER APPLY sys.dm_exec_sql_text(r.sql_handle) st
                 WHERE s.is_user_process = 1 AND r.session_id <> @@SPID'
            );
        } catch (\Throwable $e) {
            foreach ($channels as $ch) {
                Log::channel($ch)->error('DB_HEALTH_SNAPSHOT_UNREACHABLE', ['error' => $e->getMessage()]);
            }

            return self::SUCCESS;
        }

        $activeCount = count($rows);
        $blocked = array_values(array_filter($rows, fn ($r) => (int) $r->Blocking_Session_Id !== 0));
        $hasBlocking = count($blocked) > 0;
        $threshold = (int) config('request-diagnostics.snapshot.active_threshold', 20);

        // GATE: hanya menarik bila ada blocking atau lonjakan request aktif.
        if (! $hasBlocking && $activeCount < $threshold) {
            return self::SUCCESS;
        }

        $capturedAt = now()->format('Y-m-d H:i:s');

        // Ringkasan + top waits (BLOK 4 skrip lama).
        $waits = [];
        try {
            $waits = DB::select(
                'SELECT TOP 6 wait_type AS Wait_Type, waiting_tasks_count AS Tasks, wait_time_ms AS Wait_Ms
                 FROM sys.dm_os_wait_stats
                 WHERE wait_type IN (
                     \'SOS_SCHEDULER_YIELD\',\'CXPACKET\',\'CXCONSUMER\',
                     \'PAGEIOLATCH_SH\',\'PAGEIOLATCH_EX\',
                     \'LCK_M_S\',\'LCK_M_X\',\'LCK_M_U\',
                     \'WRITELOG\',\'RESOURCE_SEMAPHORE\')
                 ORDER BY wait_time_ms DESC'
            );
        } catch (\Throwable $e) {
            // best-effort
        }

        foreach ($channels as $ch) {
            Log::channel($ch)->warning('DB_HEALTH_SNAPSHOT', [
                'captured_at' => $capturedAt,
                'active_user_requests' => $activeCount,
                'blocked_sessions' => count($blocked),
                'top_waits' => array_map(fn ($w) => [
                    'wait_type' => $w->Wait_Type,
                    'wait_ms' => (int) $w->Wait_Ms,
                ], $waits),
            ]);
        }

        // Baris yang disimpan: bila ada blocking → terblok + para penahannya;
        // selain itu → 15 teratas berdasar wait_time.
        if ($hasBlocking) {
            $blockerIds = array_map(fn ($r) => (int) $r->Blocking_Session_Id, $blocked);
            $capture = array_values(array_filter($rows, fn ($r) => (int) $r->Blocking_Session_Id !== 0
                || in_array((int) $r->Session_Id, $blockerIds, true)));

            $capture = array_merge($capture, $this->penahanYangMenganggur($blockerIds, $capture));
        } else {
            usort($rows, fn ($a, $b) => (int) $b->Wait_Time_Ms <=> (int) $a->Wait_Time_Ms);
            $capture = array_slice($rows, 0, 15);
        }

        $insertRows = [];
        foreach ($capture as $r) {
            $mapped = [
                'Captured_At' => $capturedAt,
                'Session_Id' => (int) $r->Session_Id,
                'Blocking_Session_Id' => (int) $r->Blocking_Session_Id,
                'Status' => $r->Status,
                'Wait_Type' => $r->Wait_Type,
                'Wait_Time_Ms' => (int) $r->Wait_Time_Ms,
                'Cpu_Ms' => (int) $r->Cpu_Ms,
                'Login_Name' => $r->Login_Name,
                'Host_Name' => $r->Host_Name,
                'Program_Name' => $r->Program_Name,
                'Context_Info' => $r->Context_Info,
                'Sql_Text' => $r->Sql_Text !== null ? substr($r->Sql_Text, 0, 600) : null,
            ];
            $insertRows[] = $mapped;

            foreach ($channels as $ch) {
                Log::channel($ch)->warning('DB_HEALTH_ROW', $mapped);
            }
        }

        if ((bool) config('request-diagnostics.snapshot.table', true) && ! empty($insertRows)) {
            try {
                DB::table('N_HRIS_DB_Health_Snapshot')->insert($insertRows);
            } catch (\Throwable $e) {
                // stderr sudah dapat; jangan gagalkan command.
            }
        }

        return self::SUCCESS;
    }

    /**
     * Sesi penahan yang TIDAK punya baris di sys.dm_exec_requests.
     *
     * ── Kenapa ini pernah hilang ─────────────────────────────────────────────
     *
     * Penyaringan penahan di atas mencarinya di dalam $rows, dan $rows berasal
     * dari dm_exec_requests — yang hanya memuat sesi yang SEDANG menjalankan
     * sesuatu. Penahan paling khas justru sebaliknya: ia menganggur di dalam
     * transaksi terbuka sementara PHP mengerjakan hal lain, jadi ia tak punya
     * baris di sana sama sekali dan tak pernah terekam.
     *
     * Akibatnya terlihat di production 9 September 2026 pukul 20:27: dua puluhan
     * permintaan tercatat suspended pada LCK_M_S — satu menunggu 23.234 ms
     * dengan CPU 1 ms, murni menunggu — sementara sesi penahannya (71 dan 62)
     * tidak terekam sama sekali. Pelakunya baru bisa ditemukan dengan
     * mencocokkan stempel waktu ke tabel diagnostik secara manual.
     *
     * most_recent_sql_handle memulangkan statement TERAKHIR sesi itu, yang untuk
     * penahan yang menganggur adalah persis perintah yang membuka locknya.
     *
     * @param  array<int,int>  $blockerIds
     * @param  array<int,object>  $sudahAda
     * @return array<int,object>
     */
    private function penahanYangMenganggur(array $blockerIds, array $sudahAda): array
    {
        $terekam = array_map(fn ($r) => (int) $r->Session_Id, $sudahAda);
        $kurang = array_values(array_unique(array_filter(
            $blockerIds,
            fn ($id) => $id > 0 && ! in_array($id, $terekam, true),
        )));

        if ($kurang === []) {
            return [];
        }

        // Nilai berasal dari kolom int DMV, bukan masukan pengguna; dicetak
        // sebagai integer supaya tetap literal yang aman.
        $daftar = implode(',', array_map('intval', $kurang));

        try {
            return DB::select(
                'SELECT s.session_id AS Session_Id,
                        \'idle-in-transaction\' AS Status,
                        \'\' AS Wait_Type,
                        0 AS Wait_Time_Ms,
                        0 AS Blocking_Session_Id,
                        s.cpu_time AS Cpu_Ms,
                        s.login_name AS Login_Name,
                        s.host_name AS Host_Name,
                        s.program_name AS Program_Name,
                        LTRIM(RTRIM(REPLACE(CONVERT(varchar(128), s.context_info), CHAR(0), \'\'))) AS Context_Info,
                        CAST(SUBSTRING(st.text, 1, 600) AS varchar(600)) AS Sql_Text
                   FROM sys.dm_exec_sessions s
                   LEFT JOIN sys.dm_exec_connections c ON c.session_id = s.session_id
                  OUTER APPLY sys.dm_exec_sql_text(c.most_recent_sql_handle) st
                  WHERE s.session_id IN ('.$daftar.')'
            );
        } catch (\Throwable $e) {
            // Pemantau tidak boleh menjatuhkan dirinya sendiri.
            return [];
        }
    }
}
