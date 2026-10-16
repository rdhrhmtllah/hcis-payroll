<?php

namespace App\Services\SYSTEM\Governance\Authorization\Shared;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthorizationResourceRegistryService
{
    public function definitions(): array
    {
        return (array) config('authorization.resource_registry.definitions', []);
    }

    public function options(?string $pageType = null, ?string $resourceType = null): array
    {
        $definitions = $this->definitions();
        $pages = $pageType ? [$pageType => ($definitions[$pageType] ?? [])] : $definitions;
        $items = [];

        foreach ($pages as $page => $types) {
            foreach ((array) $types as $type => $codes) {
                if ($resourceType && $type !== $resourceType) {
                    continue;
                }
                foreach ((array) $codes as $code => $label) {
                    $items[] = [
                        'jenis_page' => $page,
                        'resource_type' => $type,
                        'resource_code' => is_int($code) ? (string) $label : (string) $code,
                        'resource_name' => is_int($code) ? (string) $label : (string) $label,
                    ];
                }
            }
        }

        return $items;
    }

    public function isGovernedPage(?string $pageType): bool
    {
        $pageType = trim((string) $pageType);
        if ($pageType === '') {
            return false;
        }

        $governedPages = (array) config('authorization.governed_pages', []);

        return in_array($pageType, $governedPages, true)
            || array_key_exists($pageType, $this->definitions());
    }

    public function assertRegisteredIfGoverned(
        ?User $actor,
        ?string $pageType,
        string $resourceType,
        string $resourceCode,
        string $operation = 'authorization-check',
    ): void {
        if (! $this->isGovernedPage($pageType)) {
            return;
        }

        $this->assertRegistered($actor, $pageType, $resourceType, $resourceCode, $operation);
    }

    public function isRegistered(?string $pageType, string $resourceType, string $resourceCode): bool
    {
        $pageType = trim((string) $pageType);
        $resourceType = strtolower(trim($resourceType));
        $resourceCode = trim($resourceCode);

        if ($pageType === '' || $resourceType === '' || $resourceCode === '') {
            return false;
        }

        // Internal aggregate lookup used while building page capability payloads.
        // This is never offered as a storable role resource in the admin UI.
        if ($resourceType === 'tab' && $resourceCode === $pageType) {
            return true;
        }

        $defined = collect($this->options($pageType, $resourceType))
            ->contains(fn (array $item) => $item['resource_code'] === $resourceCode);
        if (! $defined) {
            return false;
        }

        return DB::table('N_HRIS_Role_Resource_Access')
            ->where('Jenis_page', $pageType)
            ->where('Resource_Type', $resourceType)
            ->where('Resource_Code', $resourceCode)
            ->where('Flag_Aktif', 'Y')
            ->exists();
    }

    /**
     * Bentuk JAMAK dari isRegistered().
     *
     * isRegistered() menembak satu kueri tiap kali dipanggil, dan dua tempat
     * memanggilnya di dalam perulangan baris. Pada daftar resource-access itu
     * berarti 20 kueri `exists` untuk satu halaman — biayanya naik lurus
     * mengikuti ukuran halaman.
     *
     * Di sini keanggotaannya diambil dengan SATU kueri. Tabelnya memang kecil
     * (48 baris, 10 kombinasi aktif), jadi memuat himpunannya sekali jauh lebih
     * murah daripada bertanya per baris.
     *
     * SENGAJA BUKAN memoisasi di dalam isRegistered(): jalur tulis
     * (saveResourceAccess) mengubah tabel yang sama, dan ingatan yang dipasang
     * di sana bisa terbaca basi dalam satu permintaan. Bentuk jamak yang
     * dipanggil sekali di awal perulangan tak punya masalah itu.
     *
     * Aturannya disalin apa adanya dari bentuk tunggal — termasuk pemangkasan
     * spasi, penurunan huruf resource_type, jalan pintas 'tab' yang sama dengan
     * pageType, dan syarat harus terdefinisi di options().
     *
     * @param  array<int,array{0:?string,1:string,2:string}>  $permintaan  daftar [pageType, resourceType, resourceCode]
     * @return array<string,bool> berkunci "page|type|code" hasil normalisasi
     */
    public function terdaftarMassal(array $permintaan): array
    {
        $hasil = [];
        $perluTanya = [];

        foreach ($permintaan as $p) {
            $pageType = trim((string) ($p[0] ?? ''));
            $resourceType = strtolower(trim((string) ($p[1] ?? '')));
            $resourceCode = trim((string) ($p[2] ?? ''));
            $kunci = $pageType.'|'.$resourceType.'|'.$resourceCode;

            if (isset($hasil[$kunci])) {
                continue;
            }

            if ($pageType === '' || $resourceType === '' || $resourceCode === '') {
                $hasil[$kunci] = false;

                continue;
            }

            if ($resourceType === 'tab' && $resourceCode === $pageType) {
                $hasil[$kunci] = true;

                continue;
            }

            $defined = collect($this->options($pageType, $resourceType))
                ->contains(fn (array $item) => $item['resource_code'] === $resourceCode);

            if (! $defined) {
                $hasil[$kunci] = false;

                continue;
            }

            $perluTanya[$kunci] = [$pageType, $resourceType, $resourceCode];
        }

        if ($perluTanya === []) {
            return $hasil;
        }

        /* Kunci PENCOCOKAN sengaja dihuruf-kecilkan SELURUHNYA, terpisah dari
           kunci hasil. Bentuk tunggal membandingkan lewat SQL Server, yang
           dengan collation baku TIDAK peka huruf besar-kecil; mencocokkan di
           PHP secara peka huruf akan menjawab "tidak terdaftar" untuk baris
           yang menurut bentuk tunggal terdaftar. */
        $cocok = fn (string $page, string $type, string $code): string => strtolower(trim($page))
            .'|'.strtolower(trim($type))
            .'|'.strtolower(trim($code));

        $aktif = [];
        foreach (
            DB::table('N_HRIS_Role_Resource_Access')
                ->where('Flag_Aktif', 'Y')
                ->whereIn('Jenis_page', array_values(array_unique(array_column($perluTanya, 0))))
                ->get(['Jenis_page', 'Resource_Type', 'Resource_Code']) as $baris
        ) {
            $aktif[$cocok(
                (string) $baris->Jenis_page,
                (string) $baris->Resource_Type,
                (string) $baris->Resource_Code,
            )] = true;
        }

        foreach ($perluTanya as $kunci => [$page, $type, $code]) {
            $hasil[$kunci] = isset($aktif[$cocok($page, $type, $code)]);
        }

        return $hasil;
    }

    /** Kunci yang dipakai terdaftarMassal(), supaya pemanggil tak menebak bentuknya. */
    public static function kunciTerdaftar(?string $pageType, string $resourceType, string $resourceCode): string
    {
        return trim((string) $pageType)
            .'|'.strtolower(trim($resourceType))
            .'|'.trim($resourceCode);
    }

    public function assertRegistered(
        ?User $actor,
        ?string $pageType,
        string $resourceType,
        string $resourceCode,
        string $operation = 'authorization-check',
    ): void {
        if ($this->isSystemAdmin($actor)) {
            return;
        }

        if ($this->isRegistered($pageType, $resourceType, $resourceCode)) {
            return;
        }

        $this->logInvalid($actor, $pageType, $resourceType, $resourceCode, $operation);
        abort(403, 'Resource capability tidak terdaftar atau tidak aktif pada struktur authorization.');
    }

    public function assertStorableRegistered(
        ?User $actor,
        ?string $pageType,
        string $resourceType,
        string $resourceCode,
        string $operation = 'save-resource-access',
    ): void {
        if ($this->isSystemAdmin($actor)) {
            return;
        }

        $isInternalAggregate = strtolower(trim($resourceType)) === 'tab'
            && trim($resourceCode) === trim((string) $pageType);

        if (! $isInternalAggregate && $this->isRegistered($pageType, $resourceType, $resourceCode)) {
            return;
        }

        $this->logInvalid($actor, $pageType, $resourceType, $resourceCode, $operation);
        abort(403, 'Resource capability tidak terdaftar atau tidak aktif pada struktur authorization.');
    }

    public function logInvalid(
        ?User $actor,
        ?string $pageType,
        string $resourceType,
        string $resourceCode,
        string $operation,
    ): void {
        $suggestedInsertSql = $this->buildSuggestedInsertSql($pageType, $resourceType, $resourceCode);

        Log::channel((string) config('authorization.audit.channel', 'stack'))->warning(
            'authorization.resource_not_registered',
            [
                'actor_user_id' => $actor?->getKey(),
                'operation' => $operation,
                'jenis_page' => $pageType,
                'resource_type' => $resourceType,
                'resource_code' => $resourceCode,
                'suggested_insert_sql' => $suggestedInsertSql,
                'suggested_insert_note' => 'Review role ID, page, tipe, dan kode sebelum menjalankan query ke tabel resource existing.',
                'ip' => request()?->ip(),
                'url' => request()?->fullUrl(),
            ],
        );
    }

    private function buildSuggestedInsertSql(?string $pageType, string $resourceType, string $resourceCode): string
    {
        $page = $this->quoteSqlLiteral(trim((string) $pageType));
        $type = $this->quoteSqlLiteral(strtolower(trim($resourceType)));
        $code = $this->quoteSqlLiteral(trim($resourceCode));
        $name = $this->quoteSqlLiteral(strtoupper(str_replace(['.', '-', '_'], ' ', trim($resourceCode))));

        return 'INSERT INTO N_HRIS_Role_Resource_Access '
            .'(Id_Role_Functional, Context_Code, Jenis_page, Resource_Type, Resource_Code, Resource_Name, '
            .'Flag_Diizinkan, Flag_Aktif, Sort_Order, Created_At, Updated_At) VALUES '
            ."(<REVIEW_ROLE_ID>, 'admin', {$page}, {$type}, {$code}, {$name}, 'Y', 'Y', 0, GETDATE(), GETDATE());";
    }

    private function quoteSqlLiteral(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    private function isSystemAdmin(?User $actor): bool
    {
        return strtoupper(trim((string) ($actor?->Is_System_Admin ?? $actor?->is_system_admin ?? ''))) === 'Y';
    }
}
