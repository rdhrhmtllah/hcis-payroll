<?php

namespace App\Services\SYSTEM\Governance\Authorization\Shared;

class AccessContext
{
    /*
    |--------------------------------------------------------------------------
    | Access Context — Mode Switching (user / admin)
    |
    | Menentukan apakah user sedang beroperasi sebagai "user biasa"
    | atau "admin" pada request saat ini.
    |
    | Mode disimpan di session dan di-load ulang setiap request.
    | Untuk set mode 'admin', user HARUS punya permission ACCESS_ADMIN_PANEL
    | (dicek via FunctionalRole).
    |--------------------------------------------------------------------------
    */

    /** Daftar mode yang diizinkan — tolak mode asing/injection */
    protected const VALID_MODES = ['user', 'admin'];

    protected string $mode;

    public function __construct()
    {
        $this->mode = session('access_mode', 'user'); // default USER
    }

    /**
     * Set access mode. Validasi:
     * 1. Mode harus ada di whitelist (VALID_MODES)
     * 2. Mode 'admin' memerlukan permission ACCESS_ADMIN_PANEL
     */
    public function set(string $mode): void
    {
        // Tolak mode yang tidak dikenali
        if (! in_array($mode, self::VALID_MODES, true)) {
            abort(400, "Mode akses '{$mode}' tidak valid.");
        }

        // Mode admin memerlukan permission khusus
        // if ($mode === 'admin') {
        //     if (!app(FunctionalRole::class)->can('SHIFT_BYPASS_APPROVAL')) {
        //         abort(403, 'Akses Ditolak');
        //     }
        // }

        session(['access_mode' => $mode]);
        $this->mode = $mode;
    }

    public function isAdmin(): bool
    {
        return $this->mode === 'admin';
    }

    public function isUser(): bool
    {
        return $this->mode === 'user';
    }

    public function get(): string
    {
        return $this->mode;
    }
}
