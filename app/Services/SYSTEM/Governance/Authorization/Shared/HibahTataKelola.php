<?php

namespace App\Services\SYSTEM\Governance\Authorization\Shared;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * RBAC Fase 0: hibah role ke halaman tata kelola hanya untuk role admin sah.
 *
 * Satu rumah untuk gerbang route (User::memilikiAksesBaru) dan sidebar
 * (AuthorizationScopeResolver::getUsablePageTypes) — keduanya WAJIB memakai
 * kelas ini supaya menu dan gerbang tidak berselisih lagi.
 */
class HibahTataKelola
{
    public static function normalisasi(?string $page): string
    {
        return strtolower(str_replace(' ', '', trim((string) $page)));
    }

    /** @return array<string> */
    public static function halaman(): array
    {
        return array_values(array_unique(array_filter(array_map(
            [self::class, 'normalisasi'],
            (array) config('authorization.tata_kelola.halaman', []),
        ))));
    }

    /** @return array<string> */
    public static function peranAdmin(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($k) => strtoupper(trim((string) $k)),
            (array) config('authorization.tata_kelola.peran_admin', []),
        ))));
    }

    public static function apakahHalamanTataKelola(string $page): bool
    {
        // Layar Akses & Peran (SYSTEM & per modul) juga pengatur akses: tak pernah lewat hibah/grant/matriks.
        return in_array(self::normalisasi($page), self::halaman(), true) || str_ends_with(self::normalisasi($page), 'aksesperanpage');
    }

    /**
     * Saring kueri hibah: baris halaman tata kelola hanya lolos bila role-nya
     * admin sah. Halaman lain tidak tersentuh.
     */
    public static function saringHibah(Builder $q, string $kolomHalaman, string $kolomKodeRole): Builder
    {
        $halaman = self::halaman();
        if ($halaman === []) {
            return $q;
        }
        $peran = self::peranAdmin();
        $tandaHalaman = implode(',', array_fill(0, count($halaman), '?'));
        $normal = "LOWER(REPLACE(LTRIM(RTRIM({$kolomHalaman})), ' ', ''))";

        if ($peran === []) {
            return $q->whereRaw("{$normal} NOT IN ({$tandaHalaman})", $halaman);
        }
        $tandaPeran = implode(',', array_fill(0, count($peran), '?'));

        return $q->whereRaw(
            "({$normal} NOT IN ({$tandaHalaman}) OR UPPER({$kolomKodeRole}) IN ({$tandaPeran}))",
            array_merge($halaman, $peran),
        );
    }

    /** @return array<string> halaman tata kelola (ternormalisasi) lewat role admin sah */
    public static function halamanLewatPeranAdmin(int $idUser): array
    {
        $peran = self::peranAdmin();
        if ($peran === [] || self::halaman() === []) {
            return [];
        }

        return DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', function ($j) {
                $j->on('rf.Id_Role', '=', 'ru.Id_Role')->where('rf.Flag_Aktif', 'Y');
            })
            ->join('N_HRIS_Grup_Fitur_Map as gfm', function ($j) {
                $j->on('gfm.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')->where('gfm.Flag_Hibah_Role', '=', 'Y');
            })
            ->where('ru.UserId_Web', $idUser)
            ->where('ru.Flag_Aktif', 'Y')
            ->when(\App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac::punya('kedaluwarsa_penugasan'), fn ($q) => $q->where(fn ($w) => $w->whereNull('ru.Berlaku_Sampai')->orWhere('ru.Berlaku_Sampai', '>', now())))
            ->whereIn(DB::raw('UPPER(rf.Role_Code)'), $peran)
            ->pluck('gfm.Jenis_page')
            ->map(fn ($p) => self::normalisasi((string) $p))
            ->filter(fn ($p) => in_array($p, self::halaman(), true))
            ->unique()
            ->values()
            ->all();
    }
}
