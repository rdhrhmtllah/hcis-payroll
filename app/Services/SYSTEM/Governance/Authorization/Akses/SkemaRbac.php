<?php

namespace App\Services\SYSTEM\Governance\Authorization\Akses;

use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use Illuminate\Support\Facades\Cache;

/**
 * Bendera keberadaan objek DDL RBAC, dibaca SEKALI per jam lintas-request.
 * Dipakai di jalur panas (gerbang & sidebar): Schema::hasTable/hasColumn di
 * prod dibaca ulang tiap request dan mahal (review Fase 3a).
 */
class SkemaRbac
{
    private const KUNCI = 'rbac:skema';

    /** @var array<string, bool>|null */
    private static ?array $bendera = null;

    public static function lupakan(): void
    {
        self::$bendera = null;
        Cache::forget(self::KUNCI);
    }

    public static function punya(string $fitur): bool
    {
        self::$bendera ??= Cache::remember(self::KUNCI, 3600, fn () => [
            // DDL-2026-165: model baru + kolom kedaluwarsa
            'model_baru' => SkemaTersedia::tabel('N_HRIS_Role_Hak'),
            'kedaluwarsa_penugasan' => SkemaTersedia::kolom('N_HRIS_Role_User', 'Berlaku_Sampai'),
            'kedaluwarsa_grant' => SkemaTersedia::kolom('HRIS_Page_Access', 'Berlaku_Sampai'),
            // DDL-2026-164: tanpa tabel audit, perubahan akses dari layar baru ditolak.
            'audit' => SkemaTersedia::tabel('N_HRIS_Akses_Audit'),
            // DDL-2026-181: master tingkat peran + kolom tingkat setiap peran.
            'tingkat' => SkemaTersedia::tabel('N_HRIS_Tingkat_Peran') && SkemaTersedia::kolom('N_HRIS_Role_Functional', 'Kode_Tingkat'),
            // DDL-2026-184: template cakupan bernama.
            'template_cakupan' => SkemaTersedia::tabel('N_HRIS_Cakupan_Template') && SkemaTersedia::tabel('N_HRIS_Cakupan_Template_Syarat'),
        ]);

        return (bool) (self::$bendera[$fitur] ?? false);
    }

    /**
     * Saring penugasan peran yang sudah kedaluwarsa (Berlaku_Sampai lewat) —
     * satu helper untuk semua pembaca N_HRIS_Role_User. $alias = 'ru.' atau ''.
     */
    public static function saringPenugasanAktif($kueri, string $alias = '')
    {
        return self::punya('kedaluwarsa_penugasan')
            ? $kueri->where(fn ($w) => $w->whereNull($alias.'Berlaku_Sampai')->orWhere($alias.'Berlaku_Sampai', '>', now()))
            : $kueri;
    }
}
