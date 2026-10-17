<?php

namespace App\Http\Middleware;

use App\Services\SYSTEM\Application\Shell\InertiaShellService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = Auth::user();

        return array_merge(parent::share($request), [
            // PERF: 'auth' di-LAZY-kan (closure) — alasannya sama dengan 'layout'.
            // Sebelumnya EAGER: loadMissing(karyawan.division.level) = 3 query + resolve
            // avatar dihitung di SETIAP request grup web, termasuk polling
            // /homepage/notifications/unread (semua user, semua halaman) & XHR JSON
            // non-Inertia (via axios) yang TIDAK memakai prop ini. Closure hanya
            // dievaluasi saat response Inertia dirender (full visit / partial reload),
            // jadi poll & XHR JSON tak lagi menyeret 3 query relasi + avatar.
            'auth' => function () use ($user) {
                if (! $user) {
                    return ['user' => null];
                }

                $user->loadMissing(['karyawan.division', 'karyawan.level']);
                $karyawan = $user->karyawan;

                $displayName = (string) ($user->Username ?? $user->name ?? 'User');
                $department = data_get($karyawan, 'division.nama_divisi')
                    ?? data_get($karyawan, 'division.Nama_Divisi')
                    ?? data_get($karyawan, 'division.nama_sub_divisi')
                    ?? data_get($karyawan, 'division.Nama_Sub_Divisi')
                    ?? '';
                $jobTitle = data_get($karyawan, 'level.nama_jabatan')
                    ?? data_get($karyawan, 'level.Nama_Jabatan')
                    ?? '';
                $avatarSnapshot = $this->resolveProfileAvatarSnapshot((string) ($karyawan?->Kode_Karyawan ?? ''));

                return [
                    'user' => [
                        'id' => $user->Id_Users,
                        'name' => $displayName,
                        'username' => $user->Username ?? $displayName,
                        'nik' => (string) ($karyawan?->No_Induk_Karyawan ?? $karyawan?->Kode_Karyawan ?? $user->Kode_Users ?? ''),
                        'kode_karyawan' => (string) ($karyawan?->Kode_Karyawan ?? $user->Kode_Users ?? ''),
                        'role' => (string) ($user->Role ?? 'employee'),
                        'department' => (string) $department,
                        'job_title' => (string) $jobTitle,
                        'initials' => $this->buildInitials($displayName),
                        'avatar_url' => $avatarSnapshot['url'] ?? null,
                        'avatar_snapshot_url' => $avatarSnapshot['url'] ?? null,
                        'avatar_version' => $avatarSnapshot['version'] ?? null,
                        'avatar_expires_at' => $avatarSnapshot['expires_at'] ?? null,
                    ],
                ];
            },
            // PERF: shell layout (menu nav + notifikasi + cache + avatar) di-LAZY-kan
            // sebagai closure Inertia. build() berat & sebelumnya jalan EAGER di SETIAP
            // request grup web — termasuk polling /homepage/notifications/unread (60s,
            // semua user, semua halaman) & XHR data-fetch yang TIDAK memakai prop ini.
            // Closure hanya dievaluasi saat response Inertia dirender (full visit / partial
            // yang meminta 'layout'), jadi poll & XHR JSON tak lagi menyeret build().
            'layout' => fn () => app(InertiaShellService::class)->build($request, $user),
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
            ],
        ]);
    }

    private function buildInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn ($part) => strtoupper(substr((string) $part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'US';
    }

    private function resolveProfileAvatarSnapshot(string $kodeKaryawan): ?array
    {
        if ($kodeKaryawan === '') {
            return null;
        }

        try {
            $photo = Cache::remember("profile-photo-sidebar:{$kodeKaryawan}", now()->addMinutes(5), function () use ($kodeKaryawan) {
                return DB::table('N_HRIS_Profile_Photos')
                    ->where('Kode_Karyawan', $kodeKaryawan)
                    ->where('Flag_Aktif', 'Y')
                    ->orderByDesc('Updated_At')
                    ->orderByDesc('Id')
                    ->first();
            });

            if (! $photo) {
                return null;
            }

            $path = $this->profilePhotoTableHasColumn('Sidebar_Path')
                ? ($photo->Sidebar_Path ?? $photo->Avatar_Path ?? null)
                : ($photo->Avatar_Path ?? null);

            if (! $path) {
                return null;
            }

            $expiresAt = now()->addHours(6);

            return [
                'url' => Storage::disk('gcs')->temporaryUrl($path, $expiresAt),
                'version' => (string) ($photo->Updated_At ?? $photo->Id ?? ''),
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Gagal membaca avatar profil untuk shared Inertia.', [
                'kode_karyawan' => $kodeKaryawan,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function profilePhotoTableHasColumn(string $column): bool
    {
        // static = memo intra-request; Cache = lintas-request supaya Schema::hasColumn
        // (query ke sys.columns) tidak jalan tiap page-load. Skema jarang berubah ->
        // TTL panjang aman. Pola sama dgn column-exists cache di InertiaShellService.
        static $columns = [];

        if (! array_key_exists($column, $columns)) {
            try {
                $columns[$column] = Cache::remember(
                    "schema-has-col:N_HRIS_Profile_Photos:{$column}",
                    now()->addHours(12),
                    fn () => Schema::hasColumn('N_HRIS_Profile_Photos', $column),
                );
            } catch (\Throwable $e) {
                $columns[$column] = false;
            }
        }

        return $columns[$column];
    }
}
