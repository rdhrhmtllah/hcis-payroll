<?php

namespace App\Services\ESS\Attendance\Processing;

use Illuminate\Support\Facades\DB;

/**
 * Satu pintu ke N_HRIS_SP_PLAN_ABSEN_REFRESH_QUEUE.
 *
 * ── Kenapa ini punya rumah ───────────────────────────────────────────────────
 *
 * Tiga perintah artisan memanggil prosedur yang sama —
 * hris:absen-plan-custom, hris:absen-plan-cutoff, hris:absen-plan-rolling —
 * dan ketiganya menuliskan sendiri EXEC sembilan parameter POSISIONAL:
 *
 *     KodePerusahaan, TglAwal, TglAkhir, UserIDList, RefreshMode,
 *     Request_Source, Requested_By, Priority, Mode
 *
 * Itu bagian paling berbahaya untuk disalin. Nilai-nilainya diikat menurut
 * URUTAN, dan tipenya berdekatan: UserIDList, RefreshMode, Request_Source,
 * Requested_By, dan Mode semuanya string. Tertukar satu pasang tidak
 * menghasilkan galat — SP-nya menerima, lalu merencanakan hal yang berbeda dari
 * yang dimaksud, diam-diam.
 *
 * Karena itu urutannya dibangun oleh satu metode MURNI, parameter(), yang bisa
 * diuji tanpa menyentuh basis data.
 *
 * ── Dua normalisasi yang dulu juga tersalin tiga kali ────────────────────────
 *
 * Refresh mode di luar daftar dijatuhkan ke MISSING_OR_DIRTY, dan prioritas di
 * luar 1–9 dijatuhkan ke bakunya. Keduanya sengaja MEMAAFKAN, bukan menolak:
 * perintah ini dijalankan penjadwal, dan penjadwal yang gagal karena salah ketik
 * satu opsi berarti satu malam tanpa perencanaan sama sekali.
 */
class PerencanaAntreanAbsen
{
    /** Nilai RefreshMode yang diterima SP; selain ini ditolak dengan RAISERROR. */
    public const REFRESH_MODE_SAH = ['MISSING_ONLY', 'DIRTY_ONLY', 'MISSING_OR_DIRTY', 'FORCE'];

    public const REFRESH_MODE_BAKU = 'MISSING_OR_DIRTY';

    /**
     * Mode hanya biner menurut SP-nya: ROLLING_7_DAYS membuat SP menghitung
     * tanggalnya sendiri (hari ini-6 s/d hari ini); nilai lain berarti TglAwal
     * dan TglAkhir WAJIB diisi.
     */
    public const MODE_RENTANG = 'CUSTOM';

    public const MODE_ROLLING = 'ROLLING_7_DAYS';

    private const SQL = 'EXEC dbo.N_HRIS_SP_PLAN_ABSEN_REFRESH_QUEUE
                    @KodePerusahaan = ?,
                    @TglAwal = ?,
                    @TglAkhir = ?,
                    @UserIDList = ?,
                    @RefreshMode = ?,
                    @Request_Source = ?,
                    @Requested_By = ?,
                    @Priority = ?,
                    @Mode = ?';

    /**
     * Kesembilan nilai dalam URUTAN yang dituntut SQL.
     *
     * Dipisah dari rencanakan() supaya urutannya bisa ditegaskan uji tanpa
     * basis data — lihat catatan kelas.
     *
     * $priority boleh null, dan itu DISENGAJA: prosedurnya punya cabang
     * `IF @Priority IS NULL` yang menurunkan prioritas dari Request_Source.
     * Ketiga perintah artisan selalu mengirim angka sehingga cabang itu tak
     * pernah jalan bagi mereka; endpoint planRefresh yang memakainya.
     *
     * @return array{0:string,1:?string,2:?string,3:?string,4:string,5:string,6:string,7:?int,8:string}
     */
    public static function parameter(
        string $kodePerusahaan,
        ?string $tglAwal,
        ?string $tglAkhir,
        ?string $userIdList,
        string $refreshMode,
        string $requestSource,
        string $requestedBy,
        ?int $priority,
        string $mode,
    ): array {
        return [
            $kodePerusahaan,
            $tglAwal,
            $tglAkhir,
            $userIdList,
            $refreshMode,
            $requestSource,
            $requestedBy,
            $priority,
            $mode,
        ];
    }

    /** Mode yang tak dikenal dijatuhkan ke bakunya, bukan ditolak. */
    public static function normalkanRefreshMode(?string $mode): string
    {
        $mode = strtoupper(trim((string) $mode));

        return in_array($mode, self::REFRESH_MODE_SAH, true) ? $mode : self::REFRESH_MODE_BAKU;
    }

    /** Prioritas di luar 1–9 dijatuhkan ke baku milik perintah pemanggil. */
    public static function normalkanPrioritas(int $priority, int $baku): int
    {
        return ($priority < 1 || $priority > 9) ? $baku : $priority;
    }

    /**
     * @param  string  $mode  MODE_RENTANG atau MODE_ROLLING
     * @return array hasil mentah dari SP
     */
    public function rencanakan(
        string $kodePerusahaan,
        ?string $tglAwal,
        ?string $tglAkhir,
        ?string $userIdList,
        string $refreshMode,
        string $requestSource,
        string $requestedBy,
        ?int $priority,
        string $mode,
    ): array {
        return DB::select(self::SQL, self::parameter(
            $kodePerusahaan,
            $tglAwal,
            $tglAkhir,
            $userIdList,
            $refreshMode,
            $requestSource,
            $requestedBy,
            $priority,
            $mode,
        ));
    }
}
