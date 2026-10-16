<?php

namespace App\Console\Commands\ESS\Attendance\Leave\Admin;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Deteksi perubahan sisa_cuti yang MELEWATI aplikasi (out-of-band) — READ ONLY.
 *
 * Mengoperasionalkan trigger audit `database/sql/cuti/05_audit_trigger_saldo.sql`.
 * Trigger merekam SETIAP perubahan sisa_cuti ke N_HRIS_Cuti_Saldo_Audit; command
 * ini membandingkannya terhadap ledger untuk menemukan perubahan yang tidak
 * berpasangan dengan baris ledger (indikasi edit langsung SSMS/script/bug).
 *
 * Gate Opsi A: `cuti:verify` (tip-drift) menangkap HASIL-AKHIR yang menyimpang;
 * command ini menangkap AKSI perubahan out-of-band + forensik pelakunya, bahkan
 * bila kemudian "dikoreksi" sehingga drift hilang.
 *
 * Prasyarat: trigger/tabel audit sudah dipasang (script 05). Bila belum → skip.
 *
 * Exit code: FAILURE (1) bila ada temuan out-of-band (untuk alert scheduler).
 *
 * Contoh:
 *   php artisan cuti:audit-outofband
 *   php artisan cuti:audit-outofband --days=7 --window=10 --json
 */
class AuditCutiOutOfBand extends Command
{
    protected $signature = 'cuti:audit-outofband
        {--days=30 : Lihat N hari terakhir}
        {--window=10 : Toleransi detik korelasi audit<->ledger}
        {--limit=50 : Batas baris ditampilkan/diarsip}
        {--json : Output JSON}';

    protected $description = 'Deteksi perubahan sisa_cuti out-of-band (melewati aplikasi) dari trigger audit — read-only';

    private const AUDIT_TABLE = 'N_HRIS_Cuti_Saldo_Audit';

    private const LEDGER_TABLE = 'N_HRIS_Log_Buku_Cuti';

    public function handle(): int
    {
        if (! Schema::hasTable(self::AUDIT_TABLE)) {
            $this->warn('Tabel audit belum ada. Jalankan database/sql/cuti/05_audit_trigger_saldo.sql dulu. Dilewati.');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $window = max(0, (int) $this->option('window'));
        $limit = max(1, (int) $this->option('limit'));

        $sinceRaw = DB::selectOne('SELECT CONVERT(varchar(19), DATEADD(DAY, ?, GETDATE()), 120) AS d', [-$days])->d;

        $outOfBand = $this->outOfBand($sinceRaw, $window, $limit);
        $nonApp = $this->nonAppChanges($sinceRaw, $limit);

        $result = [
            'generated_at' => now()->toDateTimeString(),
            'window_days' => $days,
            'correlation_window_sec' => $window,
            'high_severity_count' => count($outOfBand),
            'summary' => [
                'OUT_OF_BAND_CHANGE' => count($outOfBand),
                'NON_APP_SOURCE' => count($nonApp),
            ],
            'findings' => [
                'OUT_OF_BAND_CHANGE' => $outOfBand,
                'NON_APP_SOURCE' => $nonApp,
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->render($result);
        }

        return $result['high_severity_count'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Perubahan saldo tanpa baris ledger berpasangan dalam ±window detik.
     * Kandidat perubahan yang melewati aplikasi.
     */
    private function outOfBand(string $since, int $window, int $limit): array
    {
        $rows = DB::select('
            SELECT TOP ('.$limit.') a.Id_Audit, a.Id_Buku_Cuti, a.Kode_Karyawan,
                   a.Sisa_Sebelum, a.Sisa_Sesudah, a.Delta, a.Changed_At, a.App_Name, a.Login_Name, a.Host_Name
            FROM '.self::AUDIT_TABLE.' a
            WHERE a.Changed_At >= ?
              AND NOT EXISTS (
                  SELECT 1 FROM '.self::LEDGER_TABLE.' l
                  WHERE l.Id_Buku_Cuti = a.Id_Buku_Cuti
                    AND ABS(DATEDIFF(SECOND, l.Created_At, a.Changed_At)) <= ?
              )
            ORDER BY a.Changed_At DESC', [$since, $window]);

        return array_map(fn ($r) => (array) $r, $rows);
    }

    /** Perubahan dari sumber non-aplikasi (mis. SSMS/Azure Data Studio). */
    private function nonAppChanges(string $since, int $limit): array
    {
        $rows = DB::select('
            SELECT TOP ('.$limit.') a.Id_Audit, a.Id_Buku_Cuti, a.Kode_Karyawan,
                   a.Sisa_Sebelum, a.Sisa_Sesudah, a.Delta, a.Changed_At, a.App_Name, a.Login_Name, a.Host_Name
            FROM '.self::AUDIT_TABLE.' a
            WHERE a.Changed_At >= ?
              AND (a.App_Name LIKE \'%Management Studio%\' OR a.App_Name LIKE \'%Azure Data%\')
            ORDER BY a.Changed_At DESC', [$since]);

        return array_map(fn ($r) => (array) $r, $rows);
    }

    private function render(array $result): void
    {
        $this->line('');
        $this->info('cuti:audit-outofband');
        $this->line("Waktu               : {$result['generated_at']}");
        $this->line("Jendela             : {$result['window_days']} hari (korelasi ±{$result['correlation_window_sec']} detik)");
        $this->line('');

        foreach ($result['summary'] as $klas => $count) {
            $tag = $count > 0 ? '<fg=red;options=bold>' : '<fg=green>';
            $this->line(sprintf('  %s%-22s: %d</>', $tag, $klas, $count));
        }
        $this->line('');

        if ($result['high_severity_count'] > 0) {
            $this->error("Ditemukan {$result['high_severity_count']} perubahan OUT-OF-BAND. Exit code = 1.");
            $this->table(
                ['Id_Buku', 'Kode', 'Sebelum', 'Sesudah', 'Delta', 'Kapan', 'App', 'Login'],
                array_map(fn ($r) => [
                    $r['Id_Buku_Cuti'], $r['Kode_Karyawan'], $r['Sisa_Sebelum'], $r['Sisa_Sesudah'],
                    $r['Delta'], $r['Changed_At'], Str::limit((string) ($r['App_Name'] ?? ''), 22), $r['Login_Name'] ?? '',
                ], array_slice($result['findings']['OUT_OF_BAND_CHANGE'], 0, 20)),
            );
        } else {
            $this->info('Bersih — tidak ada perubahan out-of-band. Exit code = 0.');
        }
    }
}
