@php
    $layoutPayload = $layout
        ?? (app(\App\Services\SYSTEM\Application\Shell\InertiaShellService::class)->build(request(), Auth::user()));

    $brand = $brand ?? data_get($layoutPayload, 'brand', []);
    $navigation = $navigation ?? data_get($layoutPayload, 'navigation', ['home' => null, 'modules' => []]);
    $shellMeta = data_get($layoutPayload, 'shell', []);

    $user = $user ?? Auth::user();
    $karyawan = Auth::user()->karyawan;
    $userName = data_get($user, 'name')
        ?? data_get($user, 'Username')
        ?? 'User';
    $userInitials = data_get($user, 'initials');
    $departmentLabel = data_get($karyawan->division, 'nama_sub_divisi')
        ?? data_get($user, 'job_title')
        ?? 'Department belum diatur';

    $buildInitials = function (string $name): string {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn ($part) => strtoupper(substr((string) $part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'US';
    };

    $userInitials = $userInitials ?: $buildInitials((string) $userName);

    $isMobile = (bool) data_get($shellMeta, 'isMobile', false);
    $isSidebarVisible = $isMobile
        ? (bool) data_get($shellMeta, 'mobileSidebarOpen', false)
        : (bool) data_get($shellMeta, 'sidebarExpanded', false);

    $homeItem = array_merge(
        [
            'url' => '/homepage',
            'title' => 'Home',
            'subtitle' => 'Halaman Utama',
            'icon' => 'bi bi-house-door-fill',
            'isActive' => false,
        ],
        (array) data_get($navigation, 'home', []),
    );

    $modules = collect(data_get($navigation, 'modules', []));
    $activeModuleCode = data_get($shellMeta, 'activeModule');
    $activeSubMenu = data_get($shellMeta, 'activeSubMenu');

    $activeModule = $activeModuleCode === 'HOME'
        ? null
        : $modules->first(fn ($module) => data_get($module, 'code') === $activeModuleCode || data_get($module, 'isActive'));

    $activeGroupId = null;
    if ($activeModule) {
        $activeGroup = collect(data_get($activeModule, 'groups', []))->first(function ($group) use ($activeSubMenu) {
            return collect(data_get($group, 'items', []))->contains(function ($item) use ($activeSubMenu) {
                return $activeSubMenu === data_get($item, 'jenisPage') || (bool) data_get($item, 'isActive');
            });
        });

        $activeGroupId = data_get($activeGroup, 'id');
    }

    $isHomeActive = $activeModuleCode === 'HOME' || (bool) data_get($homeItem, 'isActive', false);
    $resolvedBrandLogo = data_get($brand, 'mainLogo');
    $resolvedBrandAlt = data_get($brand, 'mainLogoAlt') ?: data_get($brand, 'appName') ?: 'EVO Group';
@endphp

<div
    class="shell-sidebar-backdrop{{ $isSidebarVisible ? ' is-visible' : '' }}"
    id="sidebarBackdrop"
></div>

<aside
    ref="sidebarRoot"
    id="sidebar"
    class="shell-sidebar{{ $isSidebarVisible ? ' is-expanded' : '' }}{{ $isMobile && $isSidebarVisible ? ' is-mobile-open' : '' }}"
>
    <div class="shell-sidebar__inner">
        <div class="shell-sidebar__brand">
            <button
                class="shell-brand-card shell-btn"
                type="button"
                id="sidebarBrandBtn"
                aria-label="{{ data_get($brand, 'appName') ?: 'Open sidebar' }}"
                data-shell-action="brand"
            >
                <div class="shell-brand-card__logo{{ $isSidebarVisible ? '' : ' is-small' }}">
                    @if($resolvedBrandLogo)
                        <img
                            src="{{ $resolvedBrandLogo }}"
                            alt="{{ $resolvedBrandAlt }}"
                            class="shell-brand-image"
                            id="sidebarBrandLogo"
                            onerror="this.style.display='none'; var f=this.parentElement.querySelector('.shell-brand-fallback'); if (f) f.style.display='inline-flex';"
                        />
                        <span class="shell-brand-fallback" style="display: none;">EVO</span>
                    @else
                        <span class="shell-brand-fallback">EVO</span>
                    @endif
                </div>
            </button>

            <div class="shell-mini-brand-row{{ $isSidebarVisible ? ' is-visible' : '' }}" id="sidebarSubsidiaries">
                @foreach(data_get($brand, 'subsidiaries', []) as $subsidiary)
                    <div class="shell-mini-brand">
                        @if(data_get($subsidiary, 'src'))
                            <img
                                src="{{ data_get($subsidiary, 'src') }}"
                                alt="{{ data_get($subsidiary, 'name') }}"
                                class="shell-mini-brand__image"
                                onerror="this.style.display='none'; var f=this.parentElement.querySelector('.shell-mini-brand__fallback'); if (f) f.style.display='inline-flex';"
                            />
                            <span class="shell-mini-brand__fallback" style="display: none;">
                                {{ data_get($subsidiary, 'fallback', 'EVO') }}
                            </span>
                        @else
                            <span class="shell-mini-brand__fallback">{{ data_get($subsidiary, 'fallback', 'EVO') }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <nav class="shell-nav shell-scrollbar" id="sidebarNav">
            <button
                class="shell-nav-item shell-btn{{ $isHomeActive ? ' is-active' : '' }}"
                type="button"
                data-shell-action="home"
                data-url="{{ data_get($homeItem, 'url', '/homepage') }}"
            >
                <span class="shell-nav-icon">
                    <i class="{{ data_get($homeItem, 'icon', 'bi bi-house-door-fill') }}"></i>
                </span>

                <span class="shell-nav-content{{ $isSidebarVisible ? ' is-visible' : '' }}">
                    <span class="shell-nav-title">{{ data_get($homeItem, 'title', 'Home') }}</span>
                    <span class="shell-nav-subtitle">{{ data_get($homeItem, 'subtitle', 'Halaman Utama') }}</span>
                </span>

                @if($isHomeActive)
                    <span class="shell-active-bar"></span>
                @endif
            </button>

            <div class="shell-section-label-wrap{{ $isSidebarVisible ? ' is-visible' : '' }}">
                <div class="shell-section-label">Platform Utama</div>
            </div>

            @foreach($modules as $module)
                @php
                    $moduleId = data_get($module, 'id');
                    $moduleCode = data_get($module, 'code');
                    $moduleName = data_get($module, 'name', 'MODULE');
                    $moduleLabel = data_get($module, 'label', $moduleName);
                    $moduleIcon = data_get($module, 'icon', 'bi bi-grid-1x2-fill');
                    $moduleLandingUrl = data_get($module, 'landingUrl', '');
                    $moduleIsActive = $activeModuleCode === $moduleCode || (bool) data_get($module, 'isActive');
                    $moduleIsOpen = $moduleIsActive;
                @endphp

                <div class="shell-module" data-shell-module="{{ $moduleId }}">
                    <div class="shell-module-row">
                        <button
                            class="shell-nav-item shell-btn{{ $moduleIsActive ? ' is-active' : '' }}"
                            type="button"
                            data-shell-action="module"
                            data-module-id="{{ $moduleId }}"
                            data-module-code="{{ $moduleCode }}"
                            data-landing-url="{{ $moduleLandingUrl }}"
                        >
                            <span class="shell-nav-icon">
                                <i class="{{ $moduleIcon }}"></i>
                            </span>

                            <span class="shell-nav-content{{ $isSidebarVisible ? ' is-visible' : '' }}">
                                <span class="shell-nav-title">{{ $moduleName }}</span>
                                <span class="shell-nav-subtitle">{{ $moduleLabel }}</span>
                            </span>

                            @if($moduleIsActive)
                                <span class="shell-active-bar"></span>
                            @endif
                        </button>

                        <button
                            class="shell-expand-btn shell-btn{{ $isSidebarVisible ? ' is-visible' : '' }}"
                            type="button"
                            aria-label="Toggle {{ $moduleName }}"
                            data-shell-action="toggle-module"
                            data-module-id="{{ $moduleId }}"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                                focusable="false"
                                class="shell-rotate shell-chevron-icon{{ $moduleIsOpen ? ' is-rotated' : '' }}"
                            >
                                <path
                                    d="M6 9l6 6 6-6"
                                    fill="none"
                                    stroke="#4f46e5"
                                    stroke-width="2.1"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    vector-effect="non-scaling-stroke"
                                />
                            </svg>
                        </button>
                    </div>

                    <div
                        class="shell-submenu-wrapper{{ $isSidebarVisible && $moduleIsOpen ? ' is-open' : '' }}"
                        data-shell-submenu-wrapper="{{ $moduleId }}"
                    >
                        <div class="shell-submenu-wrapper__inner">
                            @foreach(data_get($module, 'groups', []) as $group)
                                @php
                                    $groupId = data_get($group, 'id');
                                    $groupTitle = data_get($group, 'title');
                                    $groupItems = collect(data_get($group, 'items', []));
                                    $groupIsActive = $moduleIsActive && $activeGroupId === $groupId;
                                    $groupIsOpen = $groupIsActive;
                                @endphp

                                <div class="shell-submenu-group" data-shell-group="{{ $groupId }}">
                                    <button
                                        class="shell-submenu-toggle shell-btn{{ $groupIsOpen ? ' is-open' : '' }}"
                                        type="button"
                                        data-shell-action="toggle-group"
                                        data-module-id="{{ $moduleId }}"
                                        data-group-id="{{ $groupId }}"
                                    >
                                        <span class="text-truncate">{{ $groupTitle }}</span>
                                        <i
                                            class="bi bi-chevron-right shell-rotate{{ $groupIsOpen ? ' is-rotated-right' : '' }}"
                                        ></i>
                                    </button>

                                    <div
                                        class="shell-submenu-items{{ $isSidebarVisible && $groupIsOpen ? ' is-open' : '' }}"
                                        data-shell-submenu-items="{{ $groupId }}"
                                    >
                                        <div class="shell-submenu-items__inner">
                                            @foreach($groupItems as $item)
                                                @php
                                                    $itemIsActive = $activeSubMenu === data_get($item, 'jenisPage')
                                                        || (bool) data_get($item, 'isActive');
                                                @endphp

                                                <button
                                                    class="shell-submenu-item shell-btn{{ $itemIsActive ? ' is-active' : '' }}"
                                                    type="button"
                                                    data-shell-action="submenu"
                                                    data-module-id="{{ $moduleId }}"
                                                    data-group-id="{{ $groupId }}"
                                                    data-url="{{ data_get($item, 'url', '') }}"
                                                >
                                                    <span class="shell-submenu-dot"></span>
                                                    <span class="text-truncate">{{ data_get($item, 'title') }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="shell-sidebar__profile" id="sidebarProfileContainer">
            <div
                class="shell-profile-menu"
                id="sidebarProfileMenu"
            >
                <button
                    type="button"
                    class="shell-profile-menu__item shell-btn"
                    data-shell-action="profile"
                    data-url="/user-profile"
                >
                    <i class="bi bi-person"></i>
                    <span>Profil Saya</span>
                </button>

                <div class="shell-profile-divider"></div>

                <button
                    type="button"
                    class="shell-profile-menu__item shell-btn is-danger"
                    data-shell-action="logout"
                    data-url="/logout"
                >
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Keluar Sesi</span>
                </button>
            </div>

            <button
                class="shell-profile-trigger shell-btn"
                type="button"
                id="sidebarProfileToggle"
            >
                <div class="shell-profile-avatar">
                    {{ $userInitials }}
                </div>

                <div class="shell-profile-content{{ $isSidebarVisible ? ' is-visible' : '' }}">
                    <div class="shell-profile-name">{{ $userName }}</div>
                    <div class="shell-profile-meta">{{ $departmentLabel }}</div>
                </div>

                <i
                    class="bi bi-chevron-up shell-rotate shell-profile-chevron"
                    id="sidebarProfileChevron"
                ></i>
            </button>
        </div>
    </div>
</aside>
