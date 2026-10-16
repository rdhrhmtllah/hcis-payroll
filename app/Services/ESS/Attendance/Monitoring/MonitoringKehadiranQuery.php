<?php

namespace App\Services\ESS\Attendance\Monitoring;

use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Basis kueri bersama Monitoring Kehadiran: snapshot absensi + atribut
 * organisasi + cakupan admin + saringan. Semua angka di halaman lewat sini,
 * jadi kartu, daftar, rekap dan ekspor membaca populasi yang sama.
 *
 * Alias tetap: s = snapshot, k = Karyawan, vd = divisi/departemen, lvl = jabatan.
 */
final class MonitoringKehadiranQuery
{
    public const PERUSAHAAN = '001';

    public static function basis(string $dari, string $sampai, array $f, FunctionalRole $role): Builder
    {
        $q = DB::table('N_HRIS_Absen_Snapshot as s')
            // RCSI mati di Web_HRIS: tanpa NOLOCK pembacaan pantauan antre di
            // belakang pemrosesan absensi yang terus menulis tabel ini. Halaman
            // ini murni baca, jadi baris setengah-jadi cukup ditandai "sedang
            // diperbarui" lewat Flag_Generate_Ulang.
            ->lock('WITH (NOLOCK)')
            ->leftJoin('Karyawan as k', function ($j) {
                // Kode_Karyawan unik hanya per perusahaan (PK komposit).
                $j->on('k.Kode_Karyawan', '=', 's.Kode_Karyawan')->on('k.Kode_Perusahaan', '=', 's.Kode_Perusahaan');
            })
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_Divisi_Sub_Divisi', '=', 'k.ID_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'lvl.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('s.Kode_Perusahaan', self::PERUSAHAAN);

        $dari === $sampai
            ? $q->where('s.Tanggal', $dari)
            : $q->whereBetween('s.Tanggal', [$dari, $sampai]);

        self::terapkanCakupan($q, 's.Kode_Karyawan', $role);
        self::terapkanSaringan($q, $f);

        return $q;
    }

    /**
     * Cakupan admin diterapkan pada subkueri `Karyawan` tanpa alias: kondisi
     * scope di N_HRIS_Scope_Definition menulis kolom sebagai `Karyawan.Aktif`,
     * `Kode_Karyawan`, dst., yang ambigu atau salah bila ditempel ke kueri ber-join.
     */
    public static function terapkanCakupan(Builder $q, string $kolomKode, FunctionalRole $role): void
    {
        $karyawan = DB::table('Karyawan')
            ->select('Karyawan.Kode_Karyawan')
            ->where('Karyawan.Kode_Perusahaan', self::PERUSAHAAN);

        $sebelum = count($karyawan->wheres);
        $role->applyScope($karyawan);

        // Tanpa scope / scope GLOBAL: applyScope tak menambah syarat apa pun,
        // jadi subkueri hanya akan memperlambat tanpa menyaring.
        if (count($karyawan->wheres) > $sebelum) {
            $q->whereIn($kolomKode, $karyawan);
        }
    }

    public static function terapkanSaringan(Builder $q, array $f): void
    {
        foreach (SaringanMonitoring::ORGANISASI as $kunci => $kolom) {
            if (! empty($f[$kunci])) {
                $q->whereIn($kolom, $f[$kunci]);
            }
        }

        if (($f['search'] ?? '') !== '') {
            $pola = '%'.strtr($f['search'], ['[' => '[[]', '%' => '[%]', '_' => '[_]']).'%';
            $q->where(function ($w) use ($pola) {
                $w->where('k.Nama', 'LIKE', $pola)->orWhere('s.Kode_Karyawan', 'LIKE', $pola);
            });
        }
    }

    /**
     * Pilihan saringan dari karyawan aktif dalam cakupan admin; shift dari
     * snapshot rentang yang sedang dilihat. Satu kueri organisasi, satu shift.
     *
     * @return array{divisi: list<string>, departemen: list<array{nama: string, divisi: string}>, level: list<string>, jabatan: list<string>, lokasi: list<string>, shift: list<string>}
     */
    public static function opsiSaringan(FunctionalRole $role, string $dari, string $sampai): array
    {
        $q = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_Divisi_Sub_Divisi', '=', 'k.ID_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'lvl.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Kode_Perusahaan', self::PERUSAHAAN)
            ->where('k.Aktif', 'Y');
        self::terapkanCakupan($q, 'k.Kode_Karyawan', $role);

        $baris = $q->distinct()->get([
            'vd.nama_divisi', 'vd.nama_sub_divisi', 'lvl.nama_level', 'lvl.nama_jabatan', 'k.Cabang_Untuk_Gaji',
        ]);

        $unik = function (string $kolom) use ($baris): array {
            $nilai = $baris->pluck($kolom)
                ->map(fn ($v) => trim((string) $v))->filter(fn ($v) => $v !== '')
                ->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

            return $nilai;
        };

        $departemen = $baris
            ->filter(fn ($r) => trim((string) $r->nama_sub_divisi) !== '')
            ->map(fn ($r) => ['nama' => trim((string) $r->nama_sub_divisi), 'divisi' => trim((string) $r->nama_divisi)])
            ->unique(fn ($d) => $d['nama'].'|'.$d['divisi'])
            ->sortBy(fn ($d) => mb_strtolower($d['nama'].' '.$d['divisi']))
            ->values()->all();

        $shift = DB::table('N_HRIS_Absen_Snapshot as s')->lock('WITH (NOLOCK)')
            ->where('s.Kode_Perusahaan', self::PERUSAHAAN)
            ->whereBetween('s.Tanggal', [$dari, $sampai])
            ->whereNotNull('s.Nama_Shift')->where('s.Nama_Shift', '<>', '')
            ->distinct()->orderBy('s.Nama_Shift')->pluck('s.Nama_Shift')
            ->map(fn ($v) => (string) $v)->values()->all();

        return [
            'divisi' => $unik('nama_divisi'),
            'departemen' => $departemen,
            'level' => $unik('nama_level'),
            'jabatan' => $unik('nama_jabatan'),
            'lokasi' => $unik('Cabang_Untuk_Gaji'),
            'shift' => $shift,
        ];
    }

    /** SQL "nomor transaksi ada" (bukan NULL, '' atau '-'). */
    public static function adaSql(string $kolom): string
    {
        return "NULLIF(NULLIF(LTRIM(RTRIM({$kolom})), ''), '-') IS NOT NULL";
    }
}
