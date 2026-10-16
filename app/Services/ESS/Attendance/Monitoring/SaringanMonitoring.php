<?php

namespace App\Services\ESS\Attendance\Monitoring;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Normalisasi saringan bersama kedua tab. Nilai asing dibuang diam-diam,
 * kecuali rentang tanggal: rentang yang terbalik/terlalu panjang ditolak
 * (422) karena diam-diam memotongnya akan menampilkan angka periode lain.
 */
final class SaringanMonitoring
{
    /** Saringan organisasi (multi-pilih) => kolom yang dicocokkan persis. */
    public const ORGANISASI = [
        'divisi' => 'vd.nama_divisi',
        'departemen' => 'vd.nama_sub_divisi',
        'level' => 'lvl.nama_level',
        'jabatan' => 'lvl.nama_jabatan',
        'lokasi' => 'k.Cabang_Untuk_Gaji',
        'shift' => 's.Nama_Shift',
    ];

    /** 6 saringan x 200 nilai tetap jauh di bawah batas 2.100 parameter SQL Server. */
    public const MAKS_NILAI = 200;

    public const MAKS_HARI = 62;

    /** @return array<string, mixed> */
    public static function normalisasi(array $f, array $urutBoleh = []): array
    {
        $hasil = [];
        foreach (array_keys(self::ORGANISASI) as $kunci) {
            $hasil[$kunci] = self::daftar($f[$kunci] ?? []);
        }

        $kategori = (string) ($f['kategori'] ?? '');
        $urut = (string) ($f['urut'] ?? '');

        return $hasil + [
            'search' => mb_substr(trim((string) ($f['search'] ?? '')), 0, 100),
            'kategori' => in_array($kategori, KategoriKehadiran::URUTAN, true) ? $kategori : null,
            'page' => max(1, (int) ($f['page'] ?? 1)),
            'limit' => max(10, min(100, (int) ($f['limit'] ?? 25))),
            'urut' => in_array($urut, $urutBoleh, true) ? $urut : ($urutBoleh[0] ?? null),
            'arah' => strtolower((string) ($f['arah'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }

    /** Tanggal Y-m-d yang benar-benar ada di kalender, selain itu null. */
    public static function tanggal(mixed $v): ?string
    {
        $v = trim((string) $v);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $v)->toDateString() === $v ? $v : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{dari: string, sampai: string}
     *
     * @throws InvalidArgumentException
     */
    public static function rentang(mixed $dari, mixed $sampai): array
    {
        $d = self::tanggal($dari);
        $s = self::tanggal($sampai);

        if ($d === null || $s === null) {
            throw new InvalidArgumentException('Tanggal awal dan akhir wajib diisi dengan format yang benar.');
        }
        if ($d > $s) {
            throw new InvalidArgumentException('Tanggal awal tidak boleh setelah tanggal akhir.');
        }
        if (Carbon::parse($d)->diffInDays(Carbon::parse($s)) + 1 > self::MAKS_HARI) {
            throw new InvalidArgumentException('Rentang tanggal maksimal '.self::MAKS_HARI.' hari.');
        }

        return ['dari' => $d, 'sampai' => $s];
    }

    /** @return list<string> */
    private static function daftar(mixed $v): array
    {
        $hasil = [];
        foreach ((array) $v as $x) {
            if (is_scalar($x) && ($x = trim((string) $x)) !== '') {
                $hasil[$x] = true;
            }
        }

        return array_slice(array_keys($hasil), 0, self::MAKS_NILAI);
    }
}
