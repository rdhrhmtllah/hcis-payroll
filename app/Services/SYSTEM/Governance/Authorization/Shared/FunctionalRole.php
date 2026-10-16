<?php

namespace App\Services\SYSTEM\Governance\Authorization\Shared;

use Illuminate\Support\Facades\DB;

class FunctionalRole
{
    protected ?int $userId = null;

    /*
    |--------------------------------------------------------------------------
    | Static Cache — Hidup HANYA selama 1 HTTP request
    |
    | Mencegah N+1 query problem saat fungsi dipanggil berulang kali
    | (misal di sidebar 30+ kali, atau di controller beberapa kali).
    |
    | Jika admin mencabut akses di database, request BERIKUTNYA
    | akan langsung mengambil data terbaru. Zero stale data risk.
    |--------------------------------------------------------------------------
    */
    protected static array $scopeCache = [];

    protected static array $permissionCache = [];

    protected static array $userPropsCache = [];

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve User ID lazily
    |--------------------------------------------------------------------------
    */
    protected function resolveUserId(): int
    {
        if ($this->userId) {
            return $this->userId;
        }

        $user = auth()->user();

        if (! $user) {
            throw new \RuntimeException('FunctionalRole digunakan sebelum user authenticated.');
        }

        return $user->Id_Users;
    }

    /** Pengguna yang cakupannya diterapkan (mis. untuk kunci cache per cakupan). */
    public function currentUserId(): int
    {
        return $this->resolveUserId();
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve User Properties (Kode_Karyawan & ID_Divisi)
    |
    | PENTING: Model User (KPI_Users) TIDAK memiliki kolom
    | Kode_Karyawan dan ID_Divisi secara langsung.
    |
    | Harus diambil melalui:
    | - tabel Karyawan (FK: UserID_Web → KPI_Users.Id_Users)
    | - JOIN View_Divisi_Sub_Divisi untuk mendapatkan ID_Divisi
    |
    | Hasil di-cache statically per userId agar tidak N+1.
    |--------------------------------------------------------------------------
    */
    protected function resolveUserProperties(): array
    {
        $userId = $this->resolveUserId();

        if (isset(static::$userPropsCache[$userId])) {
            return static::$userPropsCache[$userId];
        }

        $props = DB::table('Karyawan as k')
            ->leftJoin(
                'View_Divisi_Sub_Divisi as v',
                'k.ID_Divisi_Sub_Divisi',
                '=',
                'v.ID_DIVISI_SUB_DIVISI'
            )
            ->where('k.UserID_Web', $userId)
            ->select('k.Kode_Karyawan', 'v.ID_Divisi')
            ->first();

        static::$userPropsCache[$userId] = [
            'Kode_Karyawan' => (string) ($props->Kode_Karyawan ?? ''),
            'ID_Divisi' => (string) ($props->ID_Divisi ?? ''),
        ];

        return static::$userPropsCache[$userId];
    }

    /*
    |--------------------------------------------------------------------------
    | GET SCOPES — Ambil semua Scope Definition milik user
    |
    | Alur query:
    | N_HRIS_Role_User (userId)
    |   → JOIN N_HRIS_Role_Scope (role → scope mapping, Flag_Aktif = Y)
    |     → JOIN N_HRIS_Scope_Definition (detail scope, Flag_Aktif = Y)
    |
    | Hasil di-cache per userId agar 1 request hanya 1x query.
    |--------------------------------------------------------------------------
    */
    protected function getScopes()
    {
        $userId = $this->resolveUserId();

        if (isset(static::$scopeCache[$userId])) {
            return static::$scopeCache[$userId];
        }

        $scopes = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Scope as rs', function ($join) {
                $join->on('rs.Id_Role', '=', 'ru.Id_Role')
                    ->where('rs.Flag_Aktif', 'Y');
            })
            ->join('N_HRIS_Scope_Definition as sd', function ($join) {
                $join->on('sd.Id_Scope', '=', 'rs.Id_Scope')
                    ->where('sd.Flag_Aktif', 'Y');
            })
            ->where('ru.UserId_Web', $userId)
            ->where('ru.Flag_Aktif', 'Y')
            ->tap(fn ($q) => \App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac::saringPenugasanAktif($q, 'ru.'))
            ->select(
                'sd.Scope_Code',
                'sd.Custom_Condition',
                'sd.Filter_Column',
                'sd.Filter_Operator',
                'sd.Scope_Value'
            )
            ->get();

        static::$scopeCache[$userId] = $scopes;

        return $scopes;
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK PERMISSION
    |
    | Cek apakah user memiliki permission tertentu.
    | Contoh: FunctionalRole::check('SHIFT_BYPASS_APPROVAL')
    |         FunctionalRole::check('ACCESS_ADMIN_PANEL')
    |
    | Query: N_HRIS_Role_User JOIN N_HRIS_Role_Permission
    | Hasil di-cache per userId + permissionCode.
    |--------------------------------------------------------------------------
    */
    public function can(string $permissionCode): bool
    {
        $userId = $this->resolveUserId();
        $cacheKey = $userId.'|'.$permissionCode;

        if (isset(static::$permissionCache[$cacheKey])) {
            return static::$permissionCache[$cacheKey];
        }

        $result = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Permission as rp', 'rp.Id_Role', '=', 'ru.Id_Role')
            ->where('ru.UserId_Web', $userId)
            ->where('ru.Flag_Aktif', 'Y')
            ->tap(fn ($q) => \App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac::saringPenugasanAktif($q, 'ru.'))
            ->where('rp.Permission_Code', $permissionCode)
            ->exists();

        static::$permissionCache[$cacheKey] = $result;

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | APPLY DATA SCOPE
    |
    | Filter query berdasarkan scope yang dimiliki user melalui role-nya.
    | Menerima Query Builder maupun Eloquent Builder.
    |
    | Logika:
    | 1. Ambil semua scope definition (dari cache atau DB)
    | 2. Jika TIDAK ada scope → return query apa adanya (tanpa filter)
    | 3. Jika ada scope GLOBAL_ALL → return query apa adanya (full access)
    | 4. Resolve user properties (Kode_Karyawan, ID_Divisi)
    | 5. Build nested WHERE:
    |    WHERE (...existing conditions...)
    |    AND (
    |      scope_role_A_condition    ← misal: DIVISIONAL_FILTER
    |      OR scope_role_B_condition ← misal: LEADER_DYNAMIC_TEAM
    |      OR scope_role_C_condition ← misal: ...
    |    )
    |
    | Multi-role bersifat ADDITIVE (orWhere) — bukan pembatasan.
    | Contoh: Jika user punya role Leader DAN Admin,
    | maka data yang bisa diakses = data tim leader ATAU data admin (gabungan).
    |
    | Placeholder yang di-replace: :Kode_Karyawan, :ID_Divisi
    |--------------------------------------------------------------------------
    */
    public function applyScope($query)
    {
        $scopes = $this->getScopes();

        /*
        |------------------------------------------------------------
        | Tidak ada scope → return query tanpa perubahan
        |------------------------------------------------------------
        */
        if ($scopes->isEmpty()) {
            return $query;
        }

        /*
        |------------------------------------------------------------
        | Deteksi GLOBAL scope → user punya full access, skip filter
        | Cek: Scope_Code mengandung 'GLOBAL' ATAU Scope_Value = '*'
        |------------------------------------------------------------
        */
        $hasGlobalScope = $scopes->contains(function ($scope) {
            return stripos($scope->Scope_Code ?? '', 'GLOBAL') !== false
                || ($scope->Scope_Value ?? '') === '*';
        });

        if ($hasGlobalScope) {
            return $query;
        }

        /*
        |------------------------------------------------------------
        | Resolve properti user yang dibutuhkan untuk placeholder
        |------------------------------------------------------------
        */
        $userProps = $this->resolveUserProperties();

        /*
        |------------------------------------------------------------
        | Build WHERE clause dengan Nested orWhere
        |
        | Hasilnya: WHERE (existing) AND (scope_A OR scope_B OR ...)
        |------------------------------------------------------------
        */
        return $query->where(function ($q) use ($scopes, $userProps) {
            foreach ($scopes as $scope) {
                $q->orWhere(function ($sub) use ($scope, $userProps) {

                    // ─── CASE 1: Custom SQL Raw ───────────────────
                    // Contoh: LEADER_DYNAMIC_TEAM
                    // Custom_Condition: "Kode_Karyawan IN (SELECT Kode_Karyawan
                    //   FROM Karyawan_Team WHERE Kode_Karyawan_Team = ':Kode_Karyawan')"
                    if (! empty($scope->Custom_Condition)) {
                        $condition = str_replace(
                            [':Kode_Karyawan', ':ID_Divisi'],
                            [$userProps['Kode_Karyawan'], $userProps['ID_Divisi']],
                            $scope->Custom_Condition
                        );
                        $sub->whereRaw("({$condition})");
                    }

                    // ─── CASE 2: Filter Kolom Standar ─────────────
                    // Contoh: DIVISIONAL_FILTER
                    // Filter_Column: "ID_Divisi", Operator: "=", Scope_Value: ":ID_Divisi"
                    elseif (! empty($scope->Filter_Column)) {
                        // Scope_Value juga bisa mengandung placeholder
                        $value = str_replace(
                            [':Kode_Karyawan', ':ID_Divisi'],
                            [$userProps['Kode_Karyawan'], $userProps['ID_Divisi']],
                            $scope->Scope_Value ?? ''
                        );
                        $sub->where(
                            $scope->Filter_Column,
                            $scope->Filter_Operator ?? '=',
                            $value
                        );
                    }
                });
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR CACHE — Untuk testing atau force-refresh dalam request
    |--------------------------------------------------------------------------
    */
    public static function clearCache(): void
    {
        static::$scopeCache = [];
        static::$permissionCache = [];
        static::$userPropsCache = [];
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER SHORTCUT — Static entry points
    |
    | Penggunaan:
    | - FunctionalRole::check('SHIFT_BYPASS_APPROVAL') → bool
    | - FunctionalRole::scope($query) → filtered query
    |--------------------------------------------------------------------------
    */
    public static function check(string $permissionCode): bool
    {
        return app(self::class)->can($permissionCode);
    }

    public static function scope($query)
    {
        return app(self::class)->applyScope($query);
    }
}
