<?php

namespace App\Services\SYSTEM\Audit\Activity\Tracker;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IdentityResolver
{
    protected static array $cache = [];

    /**
     * Resolve identitas user untuk dipasang di kolom denormalisasi
     * tabel N_HRIS_Activity_*.
     *
     * @return array{kode_karyawan:?string,userid_web:?int,username:?string}
     */
    public function resolve(): array
    {
        $user = Auth::user();
        if (! $user) {
            return ['kode_karyawan' => null, 'userid_web' => null, 'username' => null];
        }

        $userId = (int) ($user->Id_Users ?? 0);
        if ($userId > 0 && isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }

        $kode = null;
        try {
            $kode = DB::table('Karyawan')
                ->where('UserID_Web', $userId)
                ->value('Kode_Karyawan');
            $kode = $kode !== null ? trim((string) $kode) : null;
        } catch (\Throwable $e) {
            $kode = null;
        }

        $result = [
            'kode_karyawan' => $kode ?: null,
            'userid_web' => $userId ?: null,
            'username' => (string) ($user->Username ?? '') ?: null,
        ];

        if ($userId > 0) {
            self::$cache[$userId] = $result;
        }

        return $result;
    }

    public function clearCache(): void
    {
        self::$cache = [];
    }
}
