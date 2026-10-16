<?php

namespace App\Services\LMS\SOP\Uat;

use Illuminate\Support\Facades\DB;

/**
 * Penantian kunci SQL Server, diambil sebagai potret lalu diselisihkan.
 *
 * `LCK_M_S` adalah angka paling menentukan seluruh uji ini: ia penantian kunci
 * SHARED, yaitu PEMBACA yang mengantre. Dengan RCSI mati, itulah bentuk
 * konkret dari "operasi massal mengganggu orang yang sedang membaca".
 */
class UatBebanFase
{
    /** @return array<string,float> jenis wait => milidetik kumulatif */
    public static function potretWait(): array
    {
        $rows = DB::select(
            "SELECT wait_type, wait_time_ms FROM sys.dm_os_wait_stats WHERE wait_type LIKE 'LCK%'"
        );

        $out = [];
        foreach ($rows as $r) {
            $out[$r->wait_type] = (float) $r->wait_time_ms;
        }

        return $out;
    }

    /**
     * Selisih dua potret.
     *
     * PENURUNAN DIJEPIT KE NOL. `dm_os_wait_stats` bisa ter-reset di tengah
     * jalan (DBCC SQLPERF, restart), dan angka negatif akan terbaca sebagai
     * "beban justru menurunkan penantian" — mustahil, dan menyesatkan justru
     * ke arah yang menenangkan.
     *
     * @return array<string,float>
     */
    public static function deltaWait(array $sebelum, array $sesudah): array
    {
        $out = [];
        foreach ($sesudah as $jenis => $nilai) {
            $out[$jenis] = max(0.0, $nilai - ($sebelum[$jenis] ?? 0.0));
        }

        return $out;
    }

    /**
     * Rantai blocking terparah dalam satu jendela waktu.
     *
     * @return array{terlama_ms:int, jumlah:int, contoh:list<array>}
     */
    public static function blockingDalamJendela(string $mulai, string $selesai): array
    {
        $rows = DB::table('N_HRIS_DB_Health_Snapshot')
            ->whereBetween('Captured_At', [$mulai, $selesai])
            ->where('Blocking_Session_Id', '>', 0)
            ->orderByDesc('Wait_Time_Ms')
            ->limit(10)
            ->get(['Captured_At', 'Session_Id', 'Blocking_Session_Id', 'Wait_Type', 'Wait_Time_Ms', 'Sql_Text']);

        return [
            'terlama_ms' => (int) ($rows->max('Wait_Time_Ms') ?? 0),
            'jumlah' => DB::table('N_HRIS_DB_Health_Snapshot')
                ->whereBetween('Captured_At', [$mulai, $selesai])
                ->where('Blocking_Session_Id', '>', 0)
                ->count(),
            'contoh' => $rows->map(fn ($r) => [
                'waktu' => (string) $r->Captured_At,
                'sesi' => (int) $r->Session_Id,
                'diblokir_oleh' => (int) $r->Blocking_Session_Id,
                'wait' => (string) $r->Wait_Type,
                'ms' => (int) $r->Wait_Time_Ms,
                'sql' => mb_substr((string) $r->Sql_Text, 0, 160),
            ])->all(),
        ];
    }
}
