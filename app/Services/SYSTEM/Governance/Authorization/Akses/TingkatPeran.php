<?php

namespace App\Services\SYSTEM\Governance\Authorization\Akses;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Master tingkat peran (DDL-2026-181): System Admin › Super Admin › Admin ›
 * Pengguna. Aturan delegasi membaca peringkat & kemampuan dari sini — hanya
 * boleh mengangkat/mengubah tingkat yang lebih rendah, di modul yang sama.
 *
 * Tanpa tabel (DDL belum dijalankan) dipakai nilai bawaan yang sama, dan
 * tingkat peran diturunkan dari Jenis_Role + pola nama *_SUPER_ADMIN.
 */
class TingkatPeran
{
    public const SYSTEM_ADMIN = 'SYSTEM_ADMIN';

    public const SUPER_ADMIN = 'SUPER_ADMIN';

    public const ADMIN = 'ADMIN';

    public const PENGGUNA = 'PENGGUNA';

    /** Kemampuan: mengangkat/mencabut peran setingkat admin di bawahnya. */
    public const ANGKAT = 'Boleh_Angkat';

    /** Kemampuan: mengatur matriks hak (menu, tambah/ubah/hapus). */
    public const UBAH_MATRIKS = 'Boleh_Ubah_Matriks';

    /** Kemampuan: menugaskan peran pengguna & grant. */
    public const TUGASKAN = 'Boleh_Tugaskan';

    private const BAWAAN = [
        self::SYSTEM_ADMIN => ['Nama_Tingkat' => 'System Admin', 'Peringkat' => 100, self::ANGKAT => 'Y', self::UBAH_MATRIKS => 'Y', self::TUGASKAN => 'Y'],
        self::SUPER_ADMIN => ['Nama_Tingkat' => 'Super Admin', 'Peringkat' => 80, self::ANGKAT => 'Y', self::UBAH_MATRIKS => 'Y', self::TUGASKAN => 'Y'],
        self::ADMIN => ['Nama_Tingkat' => 'Admin', 'Peringkat' => 60, self::ANGKAT => 'T', self::UBAH_MATRIKS => 'T', self::TUGASKAN => 'Y'],
        self::PENGGUNA => ['Nama_Tingkat' => 'Pengguna', 'Peringkat' => 10, self::ANGKAT => 'T', self::UBAH_MATRIKS => 'T', self::TUGASKAN => 'T'],
    ];

    private const KUNCI = 'rbac:tingkat';

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $master = null;

    /** Buang cache dalam proses & lintas-request — wajib setelah master diubah. */
    public static function lupakan(): void
    {
        self::$master = null;
        Cache::forget(self::KUNCI);
    }

    /** @return array<string, array<string, mixed>> kode → baris, urut peringkat menurun */
    public function semua(): array
    {
        return self::$master ??= Cache::remember(self::KUNCI, 3600, function () {
            $baris = self::BAWAAN;
            if (SkemaRbac::punya('tingkat')) {
                foreach (DB::table('N_HRIS_Tingkat_Peran')->where('Flag_Aktif', 'Y')->get() as $r) {
                    $baris[strtoupper($r->Kode_Tingkat)] = [
                        'Nama_Tingkat' => $r->Nama_Tingkat, 'Peringkat' => (int) $r->Peringkat,
                        self::ANGKAT => $r->Boleh_Angkat, self::UBAH_MATRIKS => $r->Boleh_Ubah_Matriks, self::TUGASKAN => $r->Boleh_Tugaskan,
                        'Keterangan' => $r->Keterangan,
                    ];
                }
            }
            uasort($baris, fn ($a, $b) => $b['Peringkat'] <=> $a['Peringkat']);

            return $baris;
        });
    }

    public function peringkat(?string $kode): int
    {
        return (int) ($this->semua()[strtoupper((string) $kode)]['Peringkat'] ?? 0);
    }

    public function boleh(?string $kode, string $kemampuan): bool
    {
        // System Admin tak bisa dikurangi lewat master (bendera identitas).
        if (strtoupper((string) $kode) === self::SYSTEM_ADMIN) {
            return true;
        }

        return ($this->semua()[strtoupper((string) $kode)][$kemampuan] ?? 'T') === 'Y';
    }

    /** Peran setingkat admin (Admin, Super Admin) — boleh membawa tanggung jawab & diangkat. */
    public function setingkatAdmin(?string $kode): bool
    {
        return $this->peringkat($kode) >= $this->peringkat(self::ADMIN);
    }

    /** Tingkat sebuah peran: kolom Kode_Tingkat, atau diturunkan dari data lama. */
    /**
     * Peran admin warisan yang memecah satu fungsi menurut tanggung jawab (MENU/
     * APPROVAL/MEMBERSHIP) atau PHL — kini cukup Admin modul + cakupan. Masih
     * dipakai jalur lama, jadi hanya DITANDAI; digabung saat modulnya mode BARU.
     */
    public static function peranLama(?string $kode): bool
    {
        return (bool) preg_match('/_(MENU|APPROVAL|MEMBERSHIP|MENU_APPROVAL)_ADMINISTRATOR(_PHL)?$|_ADMIN_PHL$/i', (string) $kode);
    }

    public function dariPeran(object $peran): string
    {
        $kode = strtoupper(trim((string) ($peran->Kode_Tingkat ?? '')));
        // System Admin hanya bendera identitas — peran tak boleh membawa tingkat itu.
        if ($kode !== '' && $kode !== self::SYSTEM_ADMIN && isset($this->semua()[$kode])) {
            return $kode;
        }
        if (($peran->Jenis_Role ?? null) === 'ADMIN' || in_array(strtoupper((string) ($peran->Role_Code ?? '')), \App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola::peranAdmin(), true)) {
            return preg_match('/_SUPER_ADMIN$/i', (string) ($peran->Role_Code ?? '')) && ($peran->Id_Grup_Fitur ?? null) !== null
                ? self::SUPER_ADMIN : self::ADMIN;
        }

        return self::PENGGUNA;
    }

    /** Tertinggi di antara beberapa kode tingkat. */
    public function tertinggi(iterable $kode): string
    {
        $hasil = self::PENGGUNA;
        foreach ($kode as $k) {
            if ($this->peringkat($k) > $this->peringkat($hasil)) {
                $hasil = strtoupper((string) $k);
            }
        }

        return $hasil;
    }
}
