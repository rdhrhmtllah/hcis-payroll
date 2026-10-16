<?php

namespace App\Services\ESS\Attendance\Processing;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Menamai sebab sebuah keterlambatan hilang dari angka resmi.
 *
 * SP absensi (HRIS_SP_GET_ABSEN_NEW_V3) menghitung keterlambatan mentah lalu
 * MENIMPANYA di tempat:
 *
 *     @RealLate = DATEDIFF(Minute, @Temp_JamMasuk, @CheckIn)
 *     IF @RealLate < @TLate  -> @RealLate = 0      -- di bawah toleransi
 *     ELSE IF @RealLate > 50 -> @RealLate = 50     -- plafon
 *
 * Angka mentahnya tidak disimpan di kolom mana pun, dan dashboard membaca
 * RealLate. Akibatnya orang yang datang 07:10 dari jadwal 07:00 dengan
 * toleransi 30 menit tampil PERSIS SAMA dengan yang datang tepat waktu —
 * tidak masuk hitungan terlambat, total menitnya nol, tanpa jejak apa pun.
 *
 * Kelas ini menghitung ulang angka mentah itu dari bahan yang MEMANG SUDAH
 * dikirim wrapper (Jam_Masuk, CheckIn, TLate, No_Transaksi_Terlambat) lalu
 * menyatakan sebabnya. Ia sengaja TIDAK mengubah RealLate: angka resmi tetap
 * milik SP, yang bertambah hanya keterangannya.
 *
 * Dipakai bersama oleh semua pembaca RealLate. Menulis ulang rumus ini di
 * tiap pemanggil adalah cara paling pasti membuat salah satunya tertinggal.
 */
final class KlasifikasiKeterlambatan
{
    /**
     * Plafon milik SP, bukan milik kelas ini.
     *
     * Nilainya dipatri di V3 (`IF @RealLate > 50 SET @RealLate = 50`), jadi
     * angka di sini hanya CERMIN untuk bisa menyatakan "tercatat mentok".
     * Kalau plafon di SP berubah, angka ini harus ikut.
     */
    public const PLAFON_MENIT = 50;

    public const TANPA_CHECK_IN = 'tanpa_check_in';

    public const TEPAT_WAKTU = 'tepat_waktu';

    public const TERLAMBAT = 'terlambat';

    public const BERIZIN = 'terlambat_berizin';

    public const TOLERANSI = 'terlambat_toleransi';

    public const DINOLKAN = 'terlambat_dinolkan';

    /**
     * @param  object|array<string,mixed>  $baris  satu baris apa adanya dari wrapper absen
     * @return array{
     *     kategori:string, mentah_menit:int, tercatat_menit:int, toleransi_menit:int,
     *     tak_tercatat_menit:int, kena_plafon:bool, terlambat_sebenarnya:bool
     * }
     */
    public static function dari(object|array $baris): array
    {
        $ambil = static function (string $kunci) use ($baris) {
            $nilai = is_array($baris) ? ($baris[$kunci] ?? null) : ($baris->{$kunci} ?? null);

            return $nilai === '' ? null : $nilai;
        };

        $tercatat = (int) round((float) ($ambil('RealLate') ?? 0));
        $toleransi = (int) round((float) ($ambil('TLate') ?? 0));

        $mentah = self::menitTerlambatMentah($ambil('Tanggal'), $ambil('Jam_Masuk'), $ambil('CheckIn'));

        // Tanpa check-in atau tanpa jadwal tidak ada yang bisa dibandingkan.
        // Mengarang keterlambatan dari ketiadaan acuan lebih buruk daripada
        // diam — hari OFF akan terlihat seperti pelanggaran.
        if ($mentah === null) {
            return self::hasil(self::TANPA_CHECK_IN, 0, $tercatat, $toleransi);
        }

        if ($mentah === 0) {
            return self::hasil(self::TEPAT_WAKTU, 0, $tercatat, $toleransi);
        }

        // Urutan di bawah ini menentukan kebenaran labelnya.
        //
        // Angka SP diperiksa LEBIH DULU daripada izin dan toleransi: orang bisa
        // punya izin terlambat namun tetap dihitung terlambat oleh SP. Kalau
        // urutannya dibalik, orang yang tetap dihukum akan salah dilabeli
        // 'dimaafkan' — persis kebalikan dari yang ingin ditampilkan.
        if ($tercatat > 0) {
            return self::hasil(self::TERLAMBAT, $mentah, $tercatat, $toleransi);
        }

        if (self::adaIsinya($ambil('No_Transaksi_Terlambat'))) {
            return self::hasil(self::BERIZIN, $mentah, $tercatat, $toleransi);
        }

        // `<` tegas, meniru SP. Di batasnya SP menghitung PENUH, tidak dikurangi
        // toleransi (baris pengurangan itu ada di SP tapi dikomentari), jadi
        // menit ke-29 dimaafkan sedangkan menit ke-30 dihitung 30.
        if ($toleransi > 0 && $mentah < $toleransi) {
            return self::hasil(self::TOLERANSI, $mentah, $tercatat, $toleransi);
        }

        // Terlambat, dinolkan, tanpa izin dan tanpa toleransi. Sebabnya belum
        // diketahui — dan justru itu yang perlu diselidiki, bukan disamarkan
        // jadi "dimaafkan".
        return self::hasil(self::DINOLKAN, $mentah, $tercatat, $toleransi);
    }

    /**
     * Keterlambatan mentah dalam menit, atau null bila tak ada acuan.
     *
     * CheckIn adalah datetime PENUH dan membawa tanggalnya sendiri, termasuk
     * ketika shift malam melewati tengah malam (jadwal 2026-05-20 23:00,
     * check-in 2026-05-21 00:00). Karena itu selisihnya cukup dihitung apa
     * adanya — membandingkan bagian JAM saja akan menghasilkan -1380 menit.
     */
    private static function menitTerlambatMentah(mixed $tanggal, mixed $jamMasuk, mixed $checkIn): ?int
    {
        if ($checkIn === null || $jamMasuk === null || $tanggal === null) {
            return null;
        }

        try {
            $tanggalHari = CarbonImmutable::parse(self::keTeks($tanggal))->format('Y-m-d');
            $jam = substr(self::keTeks($jamMasuk), 0, 8);

            $jadwal = CarbonImmutable::parse($tanggalHari.' '.$jam);
            $masuk = CarbonImmutable::parse(self::keTeks($checkIn));
        } catch (Throwable) {
            return null;
        }

        // Datang lebih awal bukan keterlambatan negatif.
        return max(0, (int) $jadwal->diffInMinutes($masuk, false));
    }

    /**
     * "Ada isinya" menurut kebiasaan tabel absen, bukan menurut PHP.
     *
     * Kolom transaksi di snapshot memakai '-' sebagai penanda TIDAK ADA, bukan
     * NULL — No_Transaksi_Terlambat, No_Transaksi_Izin, Alasan_Cuti, semuanya
     * begitu. Bagi PHP '-' adalah string tak kosong, sehingga empty() membacanya
     * sebagai "ada izin" dan setiap keterlambatan yang dimaafkan toleransi akan
     * salah dilabeli 'berizin'.
     *
     * Cacat itu nyata dan sempat lolos: uji fixture-nya tidak menyertakan kolom
     * ini sama sekali, jadi hijau — sampai dijalankan atas baris sungguhan.
     */
    private static function adaIsinya(mixed $nilai): bool
    {
        $teks = trim((string) ($nilai ?? ''));

        return $teks !== '' && $teks !== '-';
    }

    private static function keTeks(mixed $nilai): string
    {
        return $nilai instanceof \DateTimeInterface
            ? $nilai->format('Y-m-d H:i:s')
            : trim((string) $nilai);
    }

    /**
     * @return array{
     *     kategori:string, mentah_menit:int, tercatat_menit:int, toleransi_menit:int,
     *     tak_tercatat_menit:int, kena_plafon:bool, terlambat_sebenarnya:bool
     * }
     */
    private static function hasil(string $kategori, int $mentah, int $tercatat, int $toleransi): array
    {
        return [
            'kategori' => $kategori,
            'mentah_menit' => $mentah,
            'tercatat_menit' => $tercatat,
            'toleransi_menit' => $toleransi,

            // Menit yang benar-benar terlambat tetapi tidak ikut terhitung —
            // baik karena dimaafkan maupun karena kena plafon.
            'tak_tercatat_menit' => max(0, $mentah - $tercatat),

            'kena_plafon' => $mentah > self::PLAFON_MENIT,

            'terlambat_sebenarnya' => $mentah > 0,
        ];
    }
}
