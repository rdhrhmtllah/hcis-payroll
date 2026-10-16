<?php

namespace App\Services\SYSTEM\Application\Shell;

use App\Http\Controllers\SYSTEM\Governance\Authorization\PageAccess\PageAccessController;
use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use App\Services\SYSTEM\Notification\NotificationService;
use App\Services\SYSTEM\Notification\WhatsappNotificationMirrorService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InertiaShellService
{
    /**
     * Per-request cache untuk Schema::hasColumn() agar tidak query sys.columns berulang kali.
     */
    protected static array $columnExistsCache = [];

    /** Prefix cache lintas-request untuk baris menu sidebar (per Kode_Perusahaan). */
    protected const MENU_ROWS_CACHE_PREFIX = 'shell.menu-rows.v2';

    protected const HAS_COLUMN_CACHE_PREFIX = 'shell.has-column.v1:';

    /** TTL cache baris menu (detik). Menu master jarang berubah; di-invalidate eksplisit saat diubah. */
    protected const MENU_ROWS_CACHE_TTL = 600;

    public function __construct(protected NotificationService $notificationService) {}

    /**
     * Hapus cache baris menu sidebar. Panggil saat master menu / grup fitur diubah
     * (mis. AdminRoleManagerController, PageAccessController) agar sidebar ter-refresh.
     */
    public static function flushMenuCache(?string $kodePerusahaan = null): void
    {
        // Kolom opsional menu ikut diperiksa ulang (mis. sesudah DDL-2026-181).
        foreach (['Section_Code', 'Flag_Aktif', 'Flag_Active'] as $kolom) {
            Cache::forget(self::HAS_COLUMN_CACHE_PREFIX.'N_HRIS_Menus.'.$kolom);
        }

        if ($kodePerusahaan !== null && $kodePerusahaan !== '') {
            Cache::forget(self::MENU_ROWS_CACHE_PREFIX.':'.$kodePerusahaan);

            return;
        }

        // Tanpa kode perusahaan spesifik: bersihkan kandidat umum.
        foreach (['001', '002', '003'] as $kode) {
            Cache::forget(self::MENU_ROWS_CACHE_PREFIX.':'.$kode);
        }
    }

    public function build(Request $request, ?User $user): array
    {
        $brand = $this->buildBrand();
        $navigation = $this->buildNavigation($request, $user);
        $shell = $this->buildShellMeta($request, $navigation);

        $kodeKaryawan = $user?->karyawan?->Kode_Karyawan ? (string) $user->karyawan->Kode_Karyawan : null;
        $topbarNotificationOptions = [
            'limit' => 5,
            'source' => WhatsappNotificationMirrorService::SOURCE,
        ];
        $notifications = $kodeKaryawan
            ? collect($this->notificationService->listUnread($kodeKaryawan, $topbarNotificationOptions))
                ->values()
                ->all()
            : [];

        return [
            'brand' => $brand,
            'navigation' => $navigation,
            'shell' => $shell,
            'unreadNotificationsCount' => $kodeKaryawan
                ? $this->notificationService->countUnread($kodeKaryawan, [
                    'source' => WhatsappNotificationMirrorService::SOURCE,
                ])
                : 0,
            'notifications' => $notifications,
            'messages' => $this->buildMessages(),
            'appVersion' => config('services.project_config.app_version', '2.12.8'),
        ];
    }

    protected function buildBrand(): array
    {
        return [
            'appName' => 'EVO Group Unified Platform',
            'appShortName' => 'EVO',
            'mainLogo' => asset('logo/EVOGROUP.png'),
            'mainLogoAlt' => 'EVO Group',
            'subsidiaries' => [
                [
                    'id' => 'emi',
                    'name' => 'PT Evo Manufacturing Indonesia',
                    'src' => asset('logo/EMI.png'),
                    'fallback' => 'EMI',
                ],
                [
                    'id' => 'enb',
                    'name' => 'PT Evo Nusa Bersaudara',
                    'src' => asset('logo/ENB.png'),
                    'fallback' => 'ENB',
                ],
                [
                    'id' => 'gmn',
                    'name' => 'PT Graha Maju Nusantara',
                    'src' => asset('logo/GMN.png'),
                    'fallback' => 'GMN',
                ],
            ],
        ];
    }

    // protected function buildNavigation(Request $request, ?User $user): array
    // {
    //     $currentPath = $this->normalizeRequestPath($request);
    //     $homeUrl = '/homepage';
    //     // $accessiblePages = $user ? $this->getAccessiblePages($user) : [];
    //     $visibleRows = $moduleRows->filter(fn($row) => $this->menuIsVisible($row, $user))->values();
    //     $menuRows = $this->getMappedMenuRows();

    //     $modules = $menuRows
    //         ->groupBy('module_code')
    //         ->map(function (Collection $moduleRows, string $moduleCode) use ($accessiblePages, $currentPath) {
    //             $visibleRows = $moduleRows->filter(fn($row) => $this->menuIsVisible($row, $accessiblePages))->values();

    //             if ($visibleRows->isEmpty()) {
    //                 return null;
    //             }

    //             $items = $visibleRows
    //                 ->map(fn($row) => $this->transformMenuRow($row, $currentPath))
    //                 ->filter(fn(array $item) => $item['url'] !== '')
    //                 ->values();

    //             if ($items->isEmpty()) {
    //                 return null;
    //             }

    //             $groups = $items
    //                 ->groupBy('groupKey')
    //                 ->map(function (Collection $groupItems) {
    //                     $firstItem = $groupItems->first();

    //                     return [
    //                         'id' => $firstItem['groupKey'],
    //                         'title' => $firstItem['groupTitle'],
    //                         'items' => $groupItems
    //                             ->map(
    //                                 fn(array $item) => [
    //                                     'id' => $item['id'],
    //                                     'jenisPage' => $item['jenisPage'],
    //                                     'title' => $item['title'],
    //                                     'url' => $item['url'],
    //                                     'icon' => $item['icon'],
    //                                     'isActive' => $item['isActive'],
    //                                 ],
    //                             )
    //                             ->values()
    //                             ->all(),
    //                     ];
    //                 })
    //                 ->values();

    //             $activeItem = $items->firstWhere('isActive', true);
    //             $landingItem = $this->resolveLandingItem($items);
    //             $firstRow = $visibleRows->first();

    //             return [
    //                 'id' => (string) ($firstRow->module_id ?? $moduleCode),
    //                 'code' => (string) $moduleCode,
    //                 'name' => (string) ($moduleCode ?: 'MODULE'),
    //                 'label' => (string) ($firstRow->module_name ?: $moduleCode),
    //                 'icon' => $this->resolveModuleIcon((string) $moduleCode),
    //                 'landingUrl' => $landingItem['url'] ?? '',
    //                 'isActive' => (bool) $activeItem,
    //                 'activeGroupId' => $activeItem['groupKey'] ?? null,
    //                 'groups' => $groups->all(),
    //             ];
    //         })
    //         ->filter()
    //         ->sortBy(fn(array $module) => $this->moduleSortWeight($module['code']))
    //         ->values()
    //         ->all();

    //     return [
    //         'home' => [
    //             'title' => 'Home',
    //             'subtitle' => 'Halaman Utama',
    //             'url' => $homeUrl,
    //             'icon' => 'bi bi-house-door-fill',
    //             'isActive' => $this->pathMatches($currentPath, $homeUrl),
    //         ],
    //         'modules' => $modules,
    //     ];
    // }

    protected function buildNavigation(Request $request, ?User $user): array
    {
        $currentPath = $this->normalizeRequestPath($request);
        $homeUrl = '/homepage';
        $menuRows = $this->getMappedMenuRows($user);

        $modules = $menuRows
            ->groupBy('module_code')
            ->map(function (Collection $moduleRows, string $moduleCode) use ($user, $currentPath) {
                $visibleRows = $moduleRows->filter(fn ($row) => $this->menuIsVisible($row, $user))->values();

                if ($visibleRows->isEmpty()) {
                    return null;
                }

                $items = $this->satuMenuAktif($visibleRows
                    ->map(fn ($row) => $this->transformMenuRow($row, $currentPath))
                    ->filter(fn (array $item) => $item['url'] !== '')
                    ->values());

                if ($items->isEmpty()) {
                    return null;
                }

                $groups = $items
                    ->groupBy('groupKey')
                    ->map(function (Collection $groupItems) {
                        $firstItem = $groupItems->first();

                        return [
                            'id' => $firstItem['groupKey'],
                            'title' => $firstItem['groupTitle'],
                            'sectionId' => $firstItem['sectionKey'],
                            'sectionTitle' => $firstItem['sectionTitle'],
                            'sectionIcon' => $firstItem['sectionIcon'],
                            'sortSection' => $firstItem['sortSection'],
                            'items' => $groupItems
                                ->map(
                                    fn (array $item) => [
                                        'id' => $item['id'],
                                        'jenisPage' => $item['jenisPage'],
                                        'title' => $item['title'],
                                        'subtitle' => $item['subtitle'],
                                        'url' => $item['url'],
                                        'icon' => $item['icon'],
                                        'target' => $item['target'],
                                        'isActive' => $item['isActive'],
                                    ],
                                )
                                ->values()
                                ->all(),
                        ];
                    })
                    ->values();

                [$orderedGroups, $sections] = self::susunBagian($groups->all());

                $activeItem = $items->firstWhere('isActive', true);
                $firstRow = $visibleRows->first();
                $landingItem = $this->resolveLandingItem(
                    $items,
                    (string) ($firstRow->module_landing_jenis_page ?? ''),
                );

                return [
                    'id' => (string) ($firstRow->module_id ?? $moduleCode),
                    'code' => (string) $moduleCode,
                    'name' => (string) ($moduleCode ?: 'MODULE'),
                    'label' => (string) ($firstRow->module_name ?: $moduleCode),
                    'subtitle' => (string) ($firstRow->module_subtitle ?: $firstRow->module_name ?: $moduleCode),
                    'icon' => $this->normalizeIcon($firstRow->module_icon ?? null, 'bi bi-grid-1x2-fill'),
                    'sortOrder' => (int) ($firstRow->module_sort_order ?? 999),
                    'landingJenisPage' => (string) ($firstRow->module_landing_jenis_page ?? ''),
                    'landingUrl' => $landingItem['url'] ?? '',
                    'landingTarget' => $landingItem['target'] ?? 'self',
                    'isActive' => (bool) $activeItem,
                    'activeGroupId' => $activeItem['groupKey'] ?? null,
                    'groups' => $orderedGroups,
                    // Kosong untuk modul tanpa "bagian": sidebar tampil 2 tingkat seperti semula.
                    'sections' => $sections,
                ];
            })
            ->filter()
            ->sortBy([['sortOrder', 'asc'], ['code', 'asc']])
            ->values()
            ->all();

        return [
            'sectionLabel' => 'Platform Utama',
            'home' => [
                'title' => 'Home',
                'subtitle' => 'Halaman Utama',
                'url' => $homeUrl,
                'icon' => 'bi bi-house-door-fill',
                'isActive' => $this->pathMatches($currentPath, $homeUrl),
            ],
            'modules' => $modules,
        ];
    }

    protected function buildShellMeta(Request $request, array $navigation): array
    {
        $currentPath = $this->normalizeRequestPath($request);
        $home = $navigation['home'] ?? [];
        $modules = collect($navigation['modules'] ?? []);
        $activeModule = $modules->first(fn (array $module) => ! empty($module['isActive']));
        $isHome = (bool) ($home['isActive'] ?? false);

        $activeGroup = null;
        $activeItem = null;

        if ($activeModule) {
            $activeGroup = collect($activeModule['groups'] ?? [])->first(function (array $group) {
                return collect($group['items'] ?? [])->contains('isActive', true);
            });

            if ($activeGroup) {
                $activeItem = collect($activeGroup['items'] ?? [])->firstWhere('isActive', true);
            }
        }

        $breadcrumbs = [
            [
                'label' => 'HOME',
                'url' => $home['url'] ?? '/homepage',
                'active' => (bool) ($home['isActive'] ?? false),
            ],
        ];

        if ($activeModule) {
            $breadcrumbs[] = [
                'label' => (string) $activeModule['code'],
                'url' => (string) ($activeModule['landingUrl'] ?? ''),
                'active' => ! $activeItem,
            ];
        }

        if ($activeItem) {
            $breadcrumbs[] = [
                'label' => (string) $activeItem['title'],
                'url' => (string) $activeItem['url'],
                'active' => true,
            ];
        }

        return [
            'currentUrl' => $currentPath,
            'activeModule' => $isHome ? 'HOME' : $activeModule['code'] ?? null,
            'activeModuleLabel' => $isHome
                ? 'Dashboard'
                : $activeModule['label'] ?? ($activeModule['code'] ?? 'Aplikasi'),
            'activeModuleUrl' => $isHome ? $home['url'] ?? '/homepage' : $activeModule['landingUrl'] ?? null,
            'activeSubMenu' => $activeItem['jenisPage'] ?? null,
            'activeSubMenuLabel' => $activeItem['title'] ?? null,
            'currentPageTitle' => $activeItem['title'] ?? ($isHome ? 'Homepage' : $activeModule['label'] ?? 'Aplikasi'),
            'breadcrumbs' => $breadcrumbs,
        ];
    }

    protected function getMappedMenuRows(?User $user): Collection
    {
        $kodePerusahaan = $this->resolveCompanyCode($user);

        // Baris menu identik untuk semua user dengan Kode_Perusahaan sama (tidak ada
        // filter per-user di query ini — penyaringan akses dilakukan di menuIsVisible()).
        // Cache lintas-request untuk memangkas query berulang yang menahan koneksi DB.
        $cacheKey = self::MENU_ROWS_CACHE_PREFIX.':'.$kodePerusahaan;
        $cached = Cache::get($cacheKey);
        if ($cached instanceof Collection) {
            return $cached;
        }

        $rows = $this->fetchMappedMenuRows($kodePerusahaan);
        Cache::put($cacheKey, $rows, self::MENU_ROWS_CACHE_TTL);

        return $rows;
    }

    protected function fetchMappedMenuRows(string $kodePerusahaan): Collection
    {
        $query = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Master_Grup_Fitur as gf', 'gf.Id_Grup_Fitur', '=', 'gfm.Id_Grup_Fitur')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->select(
                'gf.Id_Grup_Fitur as module_id',
                'gf.Kode_Grup_Fitur as module_code',
                'gf.Nama_Grup_Fitur as module_name',
                'gf.Icon_Grup_Fitur as module_icon',
                'gf.Subtitle_Grup_Fitur as module_subtitle',
                'gf.Sort_Order as module_sort_order',
                'gf.Landing_Jenis_Page as module_landing_jenis_page',
                'm.Id_Menu',
                'm.Jenis_page',
                'm.Nama_Menu',
                'm.Url_Menu',
                'm.Icon_Menu',
                'm.Active_Match_Pattern',
                'm.Target as Menu_Target',
                'm.Menu_Subtitle',
                'm.Header_Code',
                'm.Header_Label',
                'm.Header_Icon',
                'm.Sort_Header',
                'm.Sort_Menu',
                'm.Is_Global_Menu',
                'gfm.Sort_Order as map_sort_order',
                'gfm.Is_Default_Landing',
            )
            ->where('m.Kode_Perusahaan', $kodePerusahaan)
            ->where('gf.Is_Show_Sidebar', 'Y')
            ->where('gf.Flag_Aktif', 'Y')
            ->where('gfm.Is_Show_Sidebar', 'Y')
            ->where('gfm.Flag_Aktif', 'Y')
            ->where('m.Is_Show_Sidebar', 'Y')
            ->orderByRaw('COALESCE(NULLIF(gf.Sort_Order, 0), 999) ASC')
            ->orderBy('gf.Kode_Grup_Fitur')
            ->orderByRaw('COALESCE(NULLIF(m.Sort_Header, 0), 999) ASC')
            ->orderByRaw('CASE WHEN gfm.Sort_Order IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('gfm.Sort_Order')
            ->orderByRaw('CASE WHEN m.Sort_Menu IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('m.Sort_Menu')
            ->orderBy('m.Id_Menu');

        if (self::cachedHasColumn('N_HRIS_Menus', 'Section_Code')) {
            $query->addSelect('m.Section_Code', 'm.Section_Label', 'm.Section_Icon', 'm.Sort_Section');
        }

        if (self::cachedHasColumn('N_HRIS_Menus', 'Flag_Aktif')) {
            $query->where('m.Flag_Aktif', 'Y');
        } elseif (self::cachedHasColumn('N_HRIS_Menus', 'Flag_Active')) {
            $query->where('m.Flag_Active', 'Y');
        }

        // dd($query->toSql(), $query->getBindings());

        return $query->get();
    }

    protected function buildMessages(): array
    {
        return [
            [
                'id' => 1,
                'sender' => 'System HRIS',
                'text' => 'Silakan cek pembaruan dashboard terbaru Anda.',
                'time' => 'Baru saja',
                'initial' => 'SH',
            ],
            [
                'id' => 2,
                'sender' => 'Tim IT Development',
                'text' => 'Perbaikan minor UI shell sedang dipantau.',
                'time' => '1 jam lalu',
                'initial' => 'IT',
            ],
            [
                'id' => 3,
                'sender' => 'Admin',
                'text' => 'Ada pengumuman penting untuk seluruh user.',
                'time' => 'Kemarin',
                'initial' => 'AD',
            ],
        ];
    }

    /**
     * Tingkat "bagian" (opsional) di atas grup menu: KPI › Dashboard › menu.
     *
     * Grup tanpa bagian ditaruh paling atas dengan urutan asalnya; grup berbagian
     * dikumpulkan per bagian (Sort_Section, lalu urutan asal). Modul yang tak satu
     * pun grupnya berbagian dikembalikan apa adanya dengan daftar bagian kosong —
     * itulah yang membuat fitur ini tak mengubah modul lain.
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    public static function susunBagian(array $groups): array
    {
        $tanpaBagian = [];
        $perBagian = [];
        foreach (array_values($groups) as $urutan => $group) {
            $bagian = $group['sectionId'] ?? null;
            if ($bagian === null || $bagian === '') {
                $tanpaBagian[] = $group;

                continue;
            }
            $perBagian[$bagian] ??= ['urut' => (int) ($group['sortSection'] ?? 999), 'pertama' => $urutan, 'grup' => [], 'judul' => $group['sectionTitle'] ?? $bagian, 'ikon' => $group['sectionIcon'] ?? null];
            $perBagian[$bagian]['grup'][] = $group;
        }

        if ($perBagian === []) {
            return [array_values($groups), []];
        }

        uasort($perBagian, fn ($a, $b) => [$a['urut'], $a['pertama']] <=> [$b['urut'], $b['pertama']]);

        $hasil = $tanpaBagian;
        $sections = [];
        foreach ($perBagian as $id => $b) {
            array_push($hasil, ...$b['grup']);
            $sections[] = [
                'id' => (string) $id,
                'title' => (string) $b['judul'],
                'icon' => $b['ikon'],
                'groupIds' => array_column($b['grup'], 'id'),
            ];
        }

        return [$hasil, $sections];
    }

    protected function transformMenuRow(object $row, string $currentPath): array
    {
        $sectionRaw = trim((string) (($row->Section_Code ?? null) ?: ($row->Section_Label ?? '')));
        $sectionKey = $sectionRaw !== '' ? $this->normalizeGroupKey($sectionRaw) : null;
        $headerKey = $this->normalizeGroupKey($row->Header_Code ?: $row->Header_Label ?: $row->module_code);
        // Header yang sama di dua bagian berbeda tetap dua grup berbeda.
        $groupKey = $sectionKey ? $sectionKey.'__'.$headerKey : $headerKey;
        $groupTitle = trim((string) ($row->Header_Label ?: $row->Header_Code ?: 'Menu'));
        $url = $this->normalizeMenuUrl($row->Url_Menu);

        return [
            'id' => (string) ($row->Id_Menu ?? $row->Jenis_page),
            'jenisPage' => (string) $row->Jenis_page,
            'title' => trim((string) ($row->Nama_Menu ?: $row->Jenis_page)),
            'subtitle' => trim((string) ($row->Menu_Subtitle ?? '')),
            'url' => $url,
            'icon' => $this->normalizeIcon($row->Icon_Menu),
            'target' => $this->normalizeMenuTarget($row->Menu_Target ?? null),
            'isActive' => $this->menuPathMatches($currentPath, $url, $row->Active_Match_Pattern ?? null),
            'matchScore' => $this->menuMatchScore($currentPath, $url, $row->Active_Match_Pattern ?? null),
            'isDefaultLanding' => strtoupper(trim((string) ($row->Is_Default_Landing ?? 'T'))) === 'Y',
            'groupKey' => $groupKey,
            'groupTitle' => $groupTitle,
            'sectionKey' => $sectionKey,
            'sectionTitle' => $sectionKey ? trim((string) (($row->Section_Label ?? null) ?: $sectionRaw)) : null,
            'sectionIcon' => $sectionKey && ! empty($row->Section_Icon) ? $this->normalizeIcon($row->Section_Icon) : null,
            'sortSection' => (int) ($row->Sort_Section ?? 999),
            'sortHeader' => (int) ($row->Sort_Header ?? 999),
            'sortMenu' => $row->map_sort_order === null
                ? ($row->Sort_Menu === null ? 999 : (int) $row->Sort_Menu)
                : (int) $row->map_sort_order,
            'menuId' => (int) ($row->Id_Menu ?? 0),
        ];
    }

    protected function resolveLandingItem(Collection $items, ?string $landingJenisPage = null): ?array
    {
        $landingPageKey = $this->normalizePageKey($landingJenisPage);
        if ($landingPageKey !== '') {
            $configuredLanding = $items->first(
                fn (array $item) => $this->normalizePageKey($item['jenisPage'] ?? '') === $landingPageKey,
            );

            if ($configuredLanding) {
                return $configuredLanding;
            }
        }

        $defaultLanding = $items->firstWhere('isDefaultLanding', true);
        if ($defaultLanding) {
            return $defaultLanding;
        }

        $landingItem = $items->first(function (array $item) {
            $haystack = strtolower(implode(' ', [$item['jenisPage'] ?? '', $item['title'] ?? '', $item['url'] ?? '']));

            return str_contains($haystack, 'dashboard') || str_contains($haystack, 'home');
        });

        return $landingItem ?: $items->sortBy([['sortHeader', 'asc'], ['sortMenu', 'asc'], ['menuId', 'asc']])->first();
    }

    protected function menuIsVisible(object $row, ?User $user): bool
    {
        if ((string) ($row->Jenis_page ?? '') === 'systemDatabaseChangeDashboardPage') {
            return $user !== null
                && ! empty(AuthorizationScopeResolver::resolve($user)['isSystemAdmin']);
        }

        // Akses & Peran digerbang sendiri, bukan halaman: SYSTEM khusus System Admin;
        // menu per modul (DML-2026-183) bagi admin modul itu (dan System Admin).
        $halaman = (string) ($row->Jenis_page ?? '');
        if ($halaman === 'systemAksesPeranPage') {
            return $user !== null && app(KeputusanAkses::class)->apakahSystemAdmin($user);
        }
        if (preg_match('/^(ess|kpi|lms|visitor|dms)AksesPeranPage$/i', $halaman, $m)) {
            $modul = $user !== null ? app(KeputusanAkses::class)->idModul($m[1]) : null;

            return $modul !== null && app(KeputusanAkses::class)->bolehKelolaModul($user, $modul);
        }

        $isGlobalMenu = strtoupper(trim((string) ($row->Is_Global_Menu ?? ''))) === 'Y';

        if ($isGlobalMenu) {
            return true;
        }

        if (! $user) {
            return false;
        }

        // RBAC Fase 1: sumber yang sama dengan gerbang route (CheckJabatan).
        return app(KeputusanAkses::class)->boleh($user, (string) $row->Jenis_page);
    }

    protected function normalizeIcon(?string $icon, string $fallback = 'bi bi-circle-fill'): string
    {
        $icon = trim((string) $icon);

        if ($icon === '') {
            return $fallback;
        }

        if (str_starts_with($icon, 'bi bi-')) {
            return $icon;
        }

        if (str_starts_with($icon, 'bi-')) {
            return 'bi '.$icon;
        }

        return $icon;
    }

    protected function normalizeMenuTarget(?string $target): string
    {
        $target = strtolower(trim((string) $target));

        return in_array($target, ['blank', '_blank'], true) ? $target : 'self';
    }

    protected function menuPathMatches(string $currentPath, string $targetUrl, ?string $activePattern): bool
    {
        if ($targetUrl !== '' && $this->pathMatches($currentPath, $targetUrl)) {
            return true;
        }

        $patterns = preg_split('/[;,]+/', (string) $activePattern) ?: [];

        foreach ($patterns as $pattern) {
            if ($this->pathMatchesPattern($currentPath, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hanya SATU menu per modul yang aktif: yang cocoknya paling spesifik.
     *
     * Kecocokan memakai awalan, jadi menu ber-URL induk (mis. `/performance-appraisal`)
     * ikut cocok dengan setiap halaman di bawahnya (`/performance-appraisal/pemantauan`).
     * Tanpa pemilihan ini menu induk selalu ikut menyala, dan karena ia biasanya di urutan
     * pertama, judul halaman serta breadcrumb pun mengambil namanya.
     */
    protected function satuMenuAktif(Collection $items): Collection
    {
        $terbaik = $items->filter(fn (array $item) => $item['isActive'])
            ->sortByDesc('matchScore')
            ->first();

        return $items->map(fn (array $item) => [
            ...$item,
            'isActive' => $terbaik !== null && $item['id'] === $terbaik['id'],
        ])->values();
    }

    /**
     * Seberapa spesifik sebuah menu cocok dengan alamat sekarang; 0 = tidak cocok.
     * Sama persis mengalahkan awalan; di antara awalan, yang terpanjang menang.
     */
    protected function menuMatchScore(string $currentPath, string $targetUrl, ?string $activePattern): int
    {
        $skor = 0;
        $sekarang = rtrim($currentPath, '/') ?: '/';

        if ($targetUrl !== '' && $this->pathMatches($currentPath, $targetUrl)) {
            $target = rtrim(parse_url($targetUrl, PHP_URL_PATH) ?: '', '/') ?: '/';
            $skor = strlen($target) + (strcasecmp($target, $sekarang) === 0 ? 100000 : 0);
        }

        foreach (preg_split('/[;,]+/', (string) $activePattern) ?: [] as $pattern) {
            if ($this->pathMatchesPattern($currentPath, $pattern)) {
                $skor = max($skor, strlen(str_replace('*', '', trim((string) $pattern))));
            }
        }

        return $skor;
    }

    protected function pathMatchesPattern(string $currentPath, ?string $pattern): bool
    {
        $pattern = trim((string) $pattern);

        if ($pattern === '') {
            return false;
        }

        if (! str_contains($pattern, '*')) {
            return $this->pathMatches($currentPath, $pattern);
        }

        if (preg_match('/^https?:\/\//i', $pattern)) {
            $pattern = parse_url($pattern, PHP_URL_PATH) ?: '';
        }

        $pattern = '/'.ltrim($pattern, '/');
        $currentPath = rtrim($currentPath, '/') ?: '/';
        $regex = '#^'.str_replace('\*', '.*', preg_quote(rtrim($pattern, '/'), '#')).'$#i';

        return (bool) preg_match($regex, $currentPath);
    }

    protected function resolveCompanyCode(?User $user): string
    {
        return trim((string) ($user?->karyawan?->Kode_Perusahaan ?? '001')) ?: '001';
    }

    protected function normalizePageKey(?string $page): string
    {
        return strtolower(str_replace(' ', '', trim((string) $page)));
    }

    protected function normalizeGroupKey(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);

        return trim((string) $value, '-') ?: 'menu';
    }

    protected function normalizeMenuUrl(?string $url): string
    {
        $url = trim((string) $url);

        if ($url === '' || $url === '#') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        return '/'.ltrim($url, '/');
    }

    protected function normalizeRequestPath(Request $request): string
    {
        $path = '/'.ltrim($request->path(), '/');
        $normalizedPath = rtrim($path, '/');

        if ($path === '//') {
            return '/';
        }

        return $normalizedPath !== '' ? $normalizedPath : '/';
    }

    protected function pathMatches(string $currentPath, string $targetUrl): bool
    {
        if ($targetUrl === '') {
            return false;
        }

        if (preg_match('/^https?:\/\//i', $targetUrl)) {
            return false;
        }

        $targetPath = rtrim(parse_url($targetUrl, PHP_URL_PATH) ?: '', '/') ?: '/';
        $currentPath = rtrim($currentPath, '/') ?: '/';

        if ($targetPath === '/') {
            return $currentPath === '/';
        }

        return $currentPath === $targetPath || str_starts_with($currentPath, $targetPath.'/');
    }

    /**
     * Cached column existence check.
     * Menghindari query berat ke sys.columns yang diulang per-request.
     */
    protected static function cachedHasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";
        if (! isset(self::$columnExistsCache[$key])) {
            // Skema kolom statis: cache lintas-request untuk menghindari query
            // sys.columns berulang. "Ada" disimpan 24 jam; "belum ada" hanya
            // 5 menit — kode boleh rilis sebelum DDL-nya, dan kolom baru harus
            // terbaca tak lama setelah DDL dijalankan, bukan sehari kemudian.
            $cacheKey = self::HAS_COLUMN_CACHE_PREFIX.$key;
            $ada = Cache::get($cacheKey);
            if ($ada === null) {
                $ada = Schema::hasColumn($table, $column);
                Cache::put($cacheKey, $ada, $ada ? 86400 : 300);
            }
            self::$columnExistsCache[$key] = (bool) $ada;
        }

        return self::$columnExistsCache[$key];
    }
}
