<?php

namespace App\Services\ESS\Attendance\Monitoring;

use Carbon\Carbon;

/**
 * Satu karyawan pada satu tanggal masuk TEPAT SATU kategori kehadiran.
 *
 * Aturan ditulis dua kali dengan isi yang sama: versi PHP murni (diuji unit)
 * dan versi SQL CASE (dipakai menghitung kartu dan menyaring daftar di server).
 * Kartu dan daftar sama-sama memakai versi SQL, jadi angkanya tak bisa berbeda.
 *
 * Urutan cek = prioritas: transaksi cuti mengalahkan day off dan check-in,
 * karena orang yang cuti tetap bisa tercatat tap (mis. datang mengambil barang).
 */
final class KategoriKehadiran
{
    public const CUTI = 'cuti';

    public const SAKIT_IZIN = 'sakit_izin';

    public const LIBUR = 'libur';

    public const TEPAT_WAKTU = 'tepat_waktu';

    public const TERLAMBAT = 'terlambat';

    public const BELUM_ABSEN = 'belum_absen';

    public const BELUM_JAM = 'belum_jam';

    /** StatusHari kosong: pemrosesan absensi belum menetapkan jadwal hari itu. */
    public const TANPA_JADWAL = 'tanpa_jadwal';

    public const URUTAN = [
        self::TEPAT_WAKTU,
        self::TERLAMBAT,
        self::BELUM_ABSEN,
        self::BELUM_JAM,
        self::SAKIT_IZIN,
        self::CUTI,
        self::LIBUR,
        self::TANPA_JADWAL,
    ];

    /** Urutan bawaan daftar: yang paling butuh perhatian di atas. */
    public const URUTAN_PERHATIAN = [
        self::BELUM_ABSEN,
        self::TERLAMBAT,
        self::TANPA_JADWAL,
        self::BELUM_JAM,
        self::SAKIT_IZIN,
        self::CUTI,
        self::TEPAT_WAKTU,
        self::LIBUR,
    ];

    public const LABEL = [
        self::TEPAT_WAKTU => 'Hadir tepat waktu',
        self::TERLAMBAT => 'Hadir terlambat',
        self::BELUM_ABSEN => 'Belum absen',
        self::BELUM_JAM => 'Belum jam masuk',
        self::SAKIT_IZIN => 'Sakit / Izin',
        self::CUTI => 'Cuti',
        self::LIBUR => 'Day off / Libur',
        self::TANPA_JADWAL => 'Jadwal belum ditetapkan',
    ];

    /** Nomor transaksi di snapshot memakai NULL, '' atau '-' untuk "tidak ada". */
    public static function ada(?string $nomor): bool
    {
        $nomor = trim((string) $nomor);

        return $nomor !== '' && $nomor !== '-';
    }

    /**
     * Jam pembanding "sudah lewat jam masuk": hari ini = jam sekarang,
     * tanggal lampau = akhir hari (semua jam masuk sudah lewat),
     * tanggal depan = awal hari (belum ada yang lewat).
     */
    public static function jamAcuan(string $tanggal, Carbon $sekarang): string
    {
        $hariIni = $sekarang->toDateString();

        if ($tanggal < $hariIni) {
            return '23:59:59';
        }

        return $tanggal > $hariIni ? '00:00:00' : $sekarang->format('H:i:s');
    }

    /** @param  array<string, mixed>  $r  satu baris snapshot */
    public static function tentukan(array $r, string $jamAcuan): string
    {
        if (self::ada($r['No_Transaksi_Cuti'] ?? null)) {
            return self::CUTI;
        }

        if (self::ada($r['No_Transaksi_Sakit'] ?? null) || self::ada($r['No_Transaksi_Izin'] ?? null)) {
            return self::SAKIT_IZIN;
        }

        $status = strtoupper(trim((string) ($r['StatusHari'] ?? '')));

        if ($status === '') {
            return self::TANPA_JADWAL;
        }

        // OFF, OFF-Default_Shift, OFF-Hari_Libur
        if (str_starts_with($status, 'OFF')) {
            return self::LIBUR;
        }

        if (! empty($r['CheckIn'])) {
            return ((int) ($r['RealLate'] ?? 0)) > 0 ? self::TERLAMBAT : self::TEPAT_WAKTU;
        }

        $masuk = self::normalJam((string) ($r['Jam_Masuk'] ?? ''));

        return $masuk !== null && $masuk > $jamAcuan ? self::BELUM_JAM : self::BELUM_ABSEN;
    }

    /**
     * Padanan SQL dari tentukan(). Satu binding `?` = jam acuan (H:i:s).
     * Jam masuk yang tak terbaca sebagai jam dianggap sudah lewat (belum absen),
     * sama seperti versi PHP.
     */
    public static function sqlCase(string $a = 's'): string
    {
        $ada = fn (string $kolom) => "NULLIF(NULLIF(LTRIM(RTRIM({$a}.{$kolom})), ''), '-') IS NOT NULL";

        return 'CASE'
            ." WHEN {$ada('No_Transaksi_Cuti')} THEN '".self::CUTI."'"
            ." WHEN {$ada('No_Transaksi_Sakit')} OR {$ada('No_Transaksi_Izin')} THEN '".self::SAKIT_IZIN."'"
            ." WHEN NULLIF(LTRIM(RTRIM({$a}.StatusHari)), '') IS NULL THEN '".self::TANPA_JADWAL."'"
            ." WHEN {$a}.StatusHari LIKE 'OFF%' THEN '".self::LIBUR."'"
            ." WHEN {$a}.CheckIn IS NOT NULL AND ISNULL({$a}.RealLate, 0) > 0 THEN '".self::TERLAMBAT."'"
            ." WHEN {$a}.CheckIn IS NOT NULL THEN '".self::TEPAT_WAKTU."'"
            ." WHEN TRY_CAST({$a}.Jam_Masuk AS time) > CAST(? AS time) THEN '".self::BELUM_JAM."'"
            ." ELSE '".self::BELUM_ABSEN."' END";
    }

    /** '07:00' / '07:00:00' / '7:00' → '07:00:00'; selain itu null. */
    public static function normalJam(string $jam): ?string
    {
        if (! preg_match('/^\s*(\d{1,2}):(\d{2})(?::(\d{2}))?\s*$/', $jam, $m)) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) ($m[3] ?? 0));
    }
}
