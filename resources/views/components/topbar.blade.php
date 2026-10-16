@php
    $layoutPayload = $layout
        ?? (app(\App\Services\SYSTEM\Application\Shell\InertiaShellService::class)->build(request(), Auth::user()));

    $brand = $brand ?? data_get($layoutPayload, 'brand', []);
    $navigation = $navigation ?? data_get($layoutPayload, 'navigation', ['home' => null, 'modules' => []]);
    $shellMeta = data_get($layoutPayload, 'shell', []);

    $notifications = isset($notifications) && collect($notifications)->isNotEmpty()
        ? $notifications
        : data_get($layoutPayload, 'notifications', []);
    $messages = $messages ?? data_get($layoutPayload, 'messages', []);
    $helpItems = $helpItems ?? data_get($layoutPayload, 'help', []);
    $unreadCount = isset($unreadCount) && (int) $unreadCount > 0
        ? $unreadCount
        : data_get($layoutPayload, 'unreadNotificationsCount', 0);

    $user = Auth::user()->karyawan;
    $userName = data_get($user, 'Kode_Karyawan');
    $userNik = data_get($user, 'No_Induk_Karyawan') ?? '-';
    $firstName = trim(explode(' ', (string) $userName)[0] ?? $userName) ?: $userName;

    $activeModuleCode = data_get($shellMeta, 'activeModule') ?: ($activeModule ?? 'APP');
    $activeModuleUrl = $activeModuleUrl
        ?? data_get($shellMeta, 'activeModuleUrl')
        ?? data_get($navigation, 'home.url')
        ?? '/homepage';
    $activeModuleDisplay = $activeModuleCode === 'HOME' ? 'DASHBOARD' : $activeModuleCode;

    $modules = collect(data_get($navigation, 'modules', []));
    $activeModule = $activeModuleCode === 'HOME'
        ? null
        : $modules->first(fn ($module) => data_get($module, 'code') === $activeModuleCode);

    $activeModuleIcon = $activeModuleCode === 'HOME'
        ? 'bi bi-house-door-fill'
        : (data_get($activeModule, 'icon') ?: 'bi bi-grid-1x2-fill');

    $now = now();
    $greetingHour = (int) $now->format('G');

    if ($greetingHour < 11) {
        $greetingLabel = 'SELAMAT PAGI';
    } elseif ($greetingHour < 15) {
        $greetingLabel = 'SELAMAT SIANG';
    } elseif ($greetingHour < 19) {
        $greetingLabel = 'SELAMAT SORE';
    } else {
        $greetingLabel = 'SELAMAT MALAM';
    }

    $currentTimeText = $now->format('H:i');
    $currentDateText = $now->locale('id')->translatedFormat('j M Y');

    $app_version = config('services.project_config.app_version', '2.12.8');

    $resolvedHelpItems = collect($helpItems)->isNotEmpty()
        ? collect($helpItems)->map(fn ($item) => [
            'icon' => data_get($item, 'icon', 'bi bi-life-preserver'),
            'label' => data_get($item, 'label', ''),
            'description' => data_get($item, 'description', ''),
            'email' => data_get($item, 'email', ''),
        ])
        : collect([
            ['icon' => 'bi bi-headset', 'label' => 'Hubungi PIC HCIS', 'description' => 'Admin HCIS Absensi dan Perizinan', 'email' => 'hc.ops@evomanufacturingindonesia.co.id'],
            ['icon' => 'bi bi-headset', 'label' => 'Hubungi PIC KPI', 'description' => 'Admin KPI', 'email' => 'learning.development@evomanufacturingindonesia.co.id'],
            ['icon' => 'bi bi-headset', 'label' => 'Hubungi PIC LMS', 'description' => 'Admin LMS', 'email' => 'learning.development@evomanufacturingindonesia.co.id'],
            ['icon' => 'bi bi-headset', 'label' => 'Hubungi PIC IT HCIS', 'description' => 'Admin IT HCIS', 'email' => 'it.hcis@evomanufacturingindonesia.co.id'],
        ]);
@endphp

<header ref="topbarRoot" class="shell-topbar" id="topbarRoot">
    <div class="shell-topbar__inner">
        <div class="shell-topbar__left">
            <button
                class="shell-topbar-action shell-btn d-lg-none"
                type="button"
                aria-label="Toggle navigation"
                id="sidebarToggleBtn"
            >
                <i class="bi bi-list"></i>
            </button>

            <div class="shell-greeting">
                <div class="shell-greeting__eyebrow">
                    <i class="bi bi-stars"></i>
                    <span id="greetingLabel">{{ $greetingLabel }}</span>
                </div>
                <div class="shell-greeting__title">Halo, <span id="userFirstName">{{ $firstName }}</span></div>
            </div>
        </div>

        <div class="shell-topbar__right">
            <div class="shell-clock d-none d-md-flex">
                <div class="shell-clock__time">
                    <i class="bi bi-clock"></i>
                    <span id="currentTime">{{ $currentTimeText }}</span>
                </div>
                <div class="shell-clock__date">
                    <i class="bi bi-calendar3"></i>
                    <span id="currentDate">{{ $currentDateText }}</span>
                </div>
            </div>

            <div class="shell-topbar-divider d-none d-md-block"></div>

            <div class="shell-action-group">
                <!-- <div class="position-relative">
                    <button
                        class="shell-topbar-icon shell-btn"
                        id="messagesToggle"
                        type="button"
                        aria-label="Pesan"
                    >
                        <i class="bi bi-envelope"></i>
                        @if(count($messages) > 0)
                            <span class="shell-dot shell-dot--blue" id="messagesDot"></span>
                        @endif
                    </button>

                    <div class="shell-topbar-popover" id="messagesPopover" aria-hidden="true">
                        <div class="shell-popover-head">
                            <div>
                                <div class="shell-popover-title">Pesan Terbaru</div>
                            </div>
                            <i class="bi bi-chat-square-text text-primary"></i>
                        </div>

                        <div class="shell-popover-body shell-scrollbar" id="messagesBody">
                            @forelse($messages as $message)
                                <div class="shell-message-row">
                                    <div class="shell-message-avatar">{{ data_get($message, 'initial', 'M') }}</div>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="shell-message-meta">
                                            <span class="shell-message-sender text-truncate">
                                                {{ data_get($message, 'sender', 'Unknown') }}
                                            </span>
                                            <span class="shell-message-time">{{ data_get($message, 'time', 'now') }}</span>
                                        </div>
                                        <div class="shell-message-text text-truncate">
                                            {{ data_get($message, 'text', 'No text') }}
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="shell-empty-state">Tidak ada pesan baru.</div>
                            @endforelse
                        </div>

                        <button class="shell-popover-footer shell-btn" type="button">Buka Semua Pesan</button>
                    </div>
                </div> -->

                <div class="position-relative">
                    <button
                        class="shell-topbar-icon shell-btn"
                        id="notificationsToggle"
                        type="button"
                        aria-label="Notifikasi"
                    >
                        <i class="bi bi-bell"></i>
                        @if((int) $unreadCount > 0)
                            <span class="shell-badge" id="notificationsBadge">
                                {{ (int) $unreadCount > 9 ? '9+' : (int) $unreadCount }}
                            </span>
                        @endif
                    </button>

                    <div class="shell-topbar-popover" id="notificationsPopover" aria-hidden="true">
                        <div class="shell-popover-head">
                            <div>
                                <div class="shell-popover-title">Notifikasi Sistem</div>
                            </div>
                            <i class="bi bi-check-circle text-success"></i>
                        </div>

                        <div class="shell-popover-body shell-scrollbar" id="notificationsBody">
                            @forelse($notifications as $item)
                                @php
                                    $tone = strtolower((string) data_get($item, 'severity', data_get($item, 'type', 'info')));
                                    $notificationIcon = 'bi bi-bell';

                                    if (str_contains($tone, 'security')) {
                                        $notificationIcon = 'bi bi-shield-check';
                                    } elseif (str_contains($tone, 'warning') || str_contains($tone, 'alert')) {
                                        $notificationIcon = 'bi bi-exclamation-triangle';
                                    } elseif (str_contains($tone, 'success')) {
                                        $notificationIcon = 'bi bi-check-circle';
                                    }
                                @endphp

                                <div class="shell-notification-row">
                                    <div class="shell-notification-avatar tone-{{ $tone }}">
                                        <i class="{{ $notificationIcon }}"></i>
                                    </div>
                                    <div class="shell-notification-content min-w-0 flex-grow-1">
                                        <div class="shell-message-meta">
                                            <span class="shell-notification-title text-truncate">
                                                {{ data_get($item, 'title', 'Notifikasi') }}
                                            </span>
                                            <span class="shell-message-time">
                                                {{ data_get($item, 'created_at_human', data_get($item, 'time', 'Baru saja')) }}
                                            </span>
                                        </div>
                                        <div class="shell-message-text shell-message-text--wrap">
                                            {{ data_get($item, 'message', data_get($item, 'desc', 'Tidak ada detail tambahan.')) }}
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="shell-empty-state">Belum ada notifikasi baru.</div>
                            @endforelse
                        </div>

                        <button
                            class="shell-popover-footer is-red shell-btn"
                            type="button"
                            id="clearNotificationsBtn"
                        >
                            Bersihkan Riwayat
                        </button>
                    </div>
                </div>

                <div class="position-relative">
                    <button
                        class="shell-topbar-icon shell-btn"
                        id="helpToggle"
                        type="button"
                        aria-label="Bantuan"
                    >
                        <i class="bi bi-question-circle"></i>
                    </button>

                    <div class="shell-topbar-popover shell-topbar-popover--sm" id="helpPopover" aria-hidden="true">
                        <div class="shell-popover-head">
                            <div class="shell-popover-title">Pusat Bantuan</div>
                        </div>

                        <div class="shell-help-list">
                            @foreach($resolvedHelpItems as $item)
                                <button class="shell-help-row shell-btn help-pic-btn" type="button" data-email="{{ data_get($item, 'email') }}">
                                    <span class="shell-help-icon">
                                        <i class="{{ data_get($item, 'icon') }}" data-original-class="{{ data_get($item, 'icon') }}"></i>
                                    </span>
                                    <span class="shell-help-copy">
                                        <span class="shell-help-title text-truncate">{{ data_get($item, 'label') }}</span>
                                        <span class="shell-help-desc text-truncate">
                                            {{ data_get($item, 'description') }}
                                        </span>
                                    </span>
                                    <span class="shell-help-chevron">
                                        <i class="bi bi-chevron-right"></i>
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        <div class="shell-help-version">V.{{ $app_version }} EVO SYSTEMS</div>
                    </div>
                </div>
            </div>

            <div class="shell-topbar-divider d-none d-lg-block"></div>

            <button
                class="shell-module-pill shell-btn"
                type="button"
                id="userModuleBtn"
                data-url="{{ $activeModuleUrl }}"
            >
                <span class="shell-module-pill__left">
                    <span class="shell-module-pill__icon">
                        <i class="{{ $activeModuleIcon }}"></i>
                    </span>
                    <span class="shell-module-pill__code">{{ $activeModuleDisplay }}</span>
                </span>

                <span class="shell-module-pill__divider"></span>

                <span class="shell-module-pill__user-wrap">
                    <span class="shell-module-pill__user">{{ $userName }}</span>
                    <span class="shell-module-pill__nik">{{ $userNik }}</span>
                </span>
            </button>
        </div>
    </div>
</header>
