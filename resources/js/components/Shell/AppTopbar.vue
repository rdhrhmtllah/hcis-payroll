<template>
    <header ref="topbarRoot" class="shell-topbar">
        <div class="shell-topbar__inner">
            <!-- LEFT: GREETING -->
            <div class="shell-topbar__left">
                <button
                    class="shell-topbar-action shell-btn d-lg-none"
                    type="button"
                    aria-label="Toggle navigation"
                    @click.stop="shell.togglePrimarySidebar()"
                >
                    <i class="bi bi-list"></i>
                </button>

                <div class="shell-greeting">
                    <div class="shell-greeting__eyebrow">
                        <i class="bi bi-stars"></i>
                        <span>{{ greetingLabel }}</span>
                    </div>
                    <div class="shell-greeting__title">Halo, {{ firstName }}</div>
                </div>
            </div>

            <!-- RIGHT: CLOCK + ACTIONS + ACTIVE USER MODULE -->
            <div class="shell-topbar__right">
                <div class="shell-clock d-none d-md-flex">
                    <div class="shell-clock__time">
                        <i class="bi bi-clock"></i>
                        <span>{{ currentTimeText }}</span>
                    </div>
                    <div class="shell-clock__date">
                        <i class="bi bi-calendar3"></i>
                        <span>{{ currentDateText }}</span>
                    </div>
                </div>

                <div class="shell-topbar-divider d-none d-md-block"></div>

                <div class="shell-action-group">
                    <!-- MESSAGES -->
                    <!-- <div class="position-relative">
                        <button
                            class="shell-topbar-icon shell-btn"
                            :class="{ 'is-active is-blue': shell.state.showMessages }"
                            type="button"
                            aria-label="Pesan"
                            @mousedown.stop
                            @click.stop="toggleMessages"
                        >
                            <i class="bi bi-envelope"></i>
                            <span
                                v-if="messages.length && !shell.state.showMessages"
                                class="shell-dot shell-dot--blue"
                            ></span>
                        </button>

                        <transition name="panel-pop">
                            <div v-if="shell.state.showMessages" class="shell-topbar-popover is-visible">
                                <div class="shell-popover-head">
                                    <div>
                                        <div class="shell-popover-title">Pesan Terbaru</div>
                                    </div>
                                    <i class="bi bi-chat-square-text text-primary"></i>
                                </div>

                                <div class="shell-popover-body shell-scrollbar">
                                    <div v-for="message in messages" :key="message.id" class="shell-message-row">
                                        <div class="shell-message-avatar">{{ message.initial || 'M' }}</div>
                                        <div class="min-w-0 flex-grow-1">
                                            <div class="shell-message-meta">
                                                <span class="shell-message-sender text-truncate">{{
                                                    message.sender
                                                }}</span>
                                                <span class="shell-message-time">{{ message.time }}</span>
                                            </div>
                                            <div class="shell-message-text text-truncate">{{ message.text }}</div>
                                        </div>
                                    </div>

                                    <div v-if="!messages.length" class="shell-empty-state">Tidak ada pesan baru.</div>
                                </div>

                                <button class="shell-popover-footer shell-btn" type="button">Buka Semua Pesan</button>
                            </div>
                        </transition>
                    </div> -->

                    <!-- ACTIVITY CENTER -->
                    <div class="position-relative" data-tour="topbar-activity">
                        <button
                            class="shell-topbar-icon shell-btn"
                            :class="{ 'is-active is-indigo': shell.state.showActivity }"
                            type="button"
                            aria-label="Pusat Aktivitas"
                            @mousedown.stop
                            @click.stop="toggleActivityCenter"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="16"
                                height="16"
                                fill="currentColor"
                                class="ac-icon-svg"
                                :class="{ 'is-loading': activity.state.activeCount > 0 }"
                                viewBox="0 0 16 16"
                            >
                                <path
                                    class="ac-svg-arrow"
                                    fill-rule="evenodd"
                                    d="M7.646 10.854a.5.5 0 0 0 .708 0l2-2a.5.5 0 0 0-.708-.708L8.5 9.293V5.5a.5.5 0 0 0-1 0v3.793L6.354 8.146a.5.5 0 1 0-.708.708z"
                                />
                                <path
                                    class="ac-svg-cloud"
                                    d="M4.406 3.342A5.53 5.53 0 0 1 8 2c2.69 0 4.923 2 5.166 4.579C14.758 6.804 16 8.137 16 9.773 16 11.569 14.502 13 12.687 13H3.781C1.708 13 0 11.366 0 9.318c0-1.763 1.266-3.223 2.942-3.593.143-.863.698-1.723 1.464-2.383m.653.757c-.757.653-1.153 1.44-1.153 2.056v.448l-.445.049C2.064 6.805 1 7.952 1 9.318 1 10.785 2.23 12 3.781 12h8.906C13.98 12 15 10.988 15 9.773c0-1.216-1.02-2.228-2.313-2.228h-.5v-.5C12.188 4.825 10.328 3 8 3a4.53 4.53 0 0 0-2.941 1.1z"
                                />
                            </svg>
                            <span
                                v-if="
                                    (activity.state.activeCount || activity.state.readyCount) &&
                                    !shell.state.showActivity
                                "
                                class="shell-badge"
                                :class="{ 'is-green': activity.state.activeCount === 0 }"
                            >
                                {{
                                    activity.state.activeCount + activity.state.readyCount > 9
                                        ? '9+'
                                        : activity.state.activeCount + activity.state.readyCount
                                }}
                            </span>
                        </button>

                        <transition name="panel-pop">
                            <div
                                v-show="shell.state.showActivity"
                                class="shell-topbar-popover shell-topbar-popover--large is-visible"
                                @mousedown.stop
                                @click.stop
                            >
                                <div class="shell-popover-head">
                                    <div class="shell-popover-title">
                                        <span class="shell-popover-title-icon">
                                            <i class="bi bi-lightning-charge-fill"></i>
                                        </span>
                                        <span>Tugas & Aktivitas</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <button
                                            v-if="hasClearableActivities"
                                            type="button"
                                            class="ac-clear-btn shell-btn"
                                            :disabled="isClearingActivities"
                                            @click.stop="handleClearAllActivities"
                                            title="Bersihkan semua aktivitas yang sudah selesai atau gagal"
                                        >
                                            <div v-if="isClearingActivities" class="ac-loading-spinner sm"></div>
                                            <template v-else>
                                                <i class="bi bi-trash3"></i>
                                                <span>Bersihkan</span>
                                            </template>
                                        </button>
                                        <span v-if="activity.state.activeCount" class="ac-head-badge">
                                            {{ activity.state.activeCount }} berjalan
                                        </span>
                                    </div>
                                </div>

                                <div class="shell-popover-body shell-scrollbar">
                                    <div v-if="!activity.state.items.length" class="shell-empty-state">
                                        <div class="shell-empty-state-icon">
                                            <i class="bi bi-cloud-check"></i>
                                        </div>
                                        <div class="shell-empty-state-text">Tidak ada aktivitas berjalan.</div>
                                    </div>

                                    <transition-group name="ac-list" tag="div">
                                        <div
                                            v-for="item in activity.state.items"
                                            :key="item.export_id || item.id"
                                            class="ac-item"
                                            :class="{
                                                'is-dismissing': item._dismissing,
                                                'is-ready': item.status === 'ready',
                                                'is-clickable':
                                                    item.type === 'upload' && item.status === 'ready' && item.open_url,
                                            }"
                                            @click="onActivityItemClick(item)"
                                        >
                                            <!-- Overlay loading saat item sedang dihapus / diunduh -->
                                            <div v-if="item._dismissing" class="ac-item-overlay">
                                                <div class="ac-loading-spinner"></div>
                                                <span>{{ item._downloading ? 'Menyelesaikan…' : 'Menghapus…' }}</span>
                                            </div>

                                            <div class="ac-item-top">
                                                <span class="ac-status" :class="`ac-st-${item.status}`">
                                                    <i
                                                        class="bi"
                                                        :class="[
                                                            activityStatusIcon(item.status),
                                                            {
                                                                'ac-download-icon-anim':
                                                                    item.status === 'processing' ||
                                                                    item.status === 'queued',
                                                            },
                                                        ]"
                                                    ></i>
                                                </span>
                                                <div class="ac-item-info">
                                                    <div class="ac-item-label" :title="item.label">
                                                        {{ item.label }}
                                                    </div>
                                                    <div class="ac-item-sub">
                                                        {{ item.format_label }}
                                                        <span v-if="item.file_size_label">
                                                            · {{ item.file_size_label }}</span
                                                        >
                                                        <span v-if="item.requested_at"> · {{ item.requested_at }}</span>
                                                    </div>
                                                </div>
                                                <button
                                                    v-if="item.abort_url || item.dismiss_url"
                                                    class="ac-dismiss shell-btn"
                                                    :title="
                                                        item.abort_url
                                                            ? item.type === 'kpi-auto-cap'
                                                                ? 'Batalkan eksekusi'
                                                                : item.type === 'export'
                                                                    ? 'Batalkan export'
                                                                    : 'Hentikan'
                                                            : 'Sembunyikan'
                                                    "
                                                    :disabled="item._dismissing || item._aborting"
                                                    @click.stop="
                                                        item.abort_url ? activity.abort(item) : activity.dismiss(item)
                                                    "
                                                >
                                                    <div
                                                        v-if="item._dismissing || item._aborting"
                                                        class="ac-loading-spinner sm"
                                                    ></div>
                                                    <i v-else class="bi bi-x"></i>
                                                </button>
                                            </div>

                                            <div
                                                v-if="item.status === 'queued' || item.status === 'processing'"
                                                class="ac-progress"
                                            >
                                                <div class="ac-bar-track">
                                                    <div
                                                        class="ac-bar-fill"
                                                        :class="{ 'is-indeterminate': item.percent === null }"
                                                        :style="
                                                            item.percent !== null ? { width: item.percent + '%' } : {}
                                                        "
                                                    ></div>
                                                </div>
                                                <span v-if="item.percent !== null" class="ac-pct"
                                                    >{{ item.percent }}%</span
                                                >
                                            </div>

                                            <!-- Posisi antrian: queue Cloud Tasks jalan satu per satu. -->
                                            <div
                                                v-if="
                                                    item.status === 'queued' &&
                                                    item.queue_position &&
                                                    item.queue_position > 1
                                                "
                                                class="ac-queue"
                                            >
                                                <i class="bi bi-hourglass-split"></i>
                                                Antrian #{{ item.queue_position }} dari {{ item.queue_total }}
                                            </div>
                                            <div v-else-if="item.status === 'processing'" class="ac-queue is-active">
                                                <i class="bi bi-gear-wide-connected"></i>
                                                Sedang diproses
                                            </div>

                                            <div class="ac-actions">
                                                <button
                                                    v-if="item.abort_url"
                                                    class="ac-act abort"
                                                    type="button"
                                                    :disabled="item._aborting"
                                                    @click.stop="activity.abort(item)"
                                                >
                                                    <span v-if="item._aborting" class="ac-loading-spinner sm"></span>
                                                    <i v-else class="bi bi-x-octagon"></i>
                                                    {{
                                                        item._aborting
                                                            ? 'Membatalkan…'
                                                            : item.type === 'export'
                                                              ? 'Batalkan'
                                                              : 'Hentikan'
                                                    }}
                                                </button>
                                                <!-- Tugas yang punya halaman asal (mis. penjadwalan rater PA → Langkah 5). -->
                                                <button
                                                    v-if="item.type !== 'upload' && item.open_url && item.open_label"
                                                    class="ac-act dl"
                                                    type="button"
                                                    @click.stop="activity.openItem(item)"
                                                >
                                                    <i class="bi bi-box-arrow-up-right"></i> {{ item.open_label }}
                                                </button>
                                                <button
                                                    v-if="item.status === 'ready' && item.download_url"
                                                    class="ac-act dl"
                                                    type="button"
                                                    @click.stop="activity.download(item)"
                                                >
                                                    <i class="bi bi-download"></i> Download
                                                </button>
                                                <button
                                                    v-if="
                                                        item.type === 'upload' &&
                                                        item.status === 'ready' &&
                                                        item.open_url
                                                    "
                                                    class="ac-act dl"
                                                    type="button"
                                                    @click.stop="onActivityItemClick(item)"
                                                >
                                                    <i class="bi bi-folder2-open"></i> Buka Folder
                                                </button>
                                                <button
                                                    v-if="item.status === 'failed' && item.retry_url"
                                                    class="ac-act retry"
                                                    type="button"
                                                    @click.stop="activity.retry(item)"
                                                >
                                                    <i class="bi bi-arrow-clockwise"></i> Coba Lagi
                                                </button>
                                                <span v-if="item.status === 'failed'" class="ac-err">
                                                    {{ item.error_message || 'Gagal.' }}
                                                </span>
                                                <span v-if="item.status === 'expired'" class="ac-expired"
                                                    >File kedaluwarsa</span
                                                >
                                            </div>
                                        </div>
                                    </transition-group>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <!-- NOTIFICATIONS -->
                    <div class="position-relative" data-tour="topbar-notifications">
                        <button
                            class="shell-topbar-icon shell-btn"
                            :class="{ 'is-active is-red': shell.state.showNotifications }"
                            type="button"
                            aria-label="Notifikasi"
                            @mousedown.stop
                            @click.stop="toggleNotifications"
                        >
                            <i class="bi bi-bell"></i>
                            <span v-if="unreadNotificationsCount && !shell.state.showNotifications" class="shell-badge">
                                {{ unreadNotificationsCount > 9 ? '9+' : unreadNotificationsCount }}
                            </span>
                        </button>

                        <transition name="panel-pop">
                            <div
                                v-show="shell.state.showNotifications"
                                class="shell-topbar-popover shell-topbar-popover--large is-visible"
                                @mousedown.stop
                                @click.stop
                            >
                                <div class="shell-popover-head shell-popover-head--tabs" data-tour="notif-tabs">
                                    <div class="shell-popover-tabs">
                                        <button
                                            class="shell-popover-tab shell-btn"
                                            data-tour="notif-tab-unread"
                                            :class="{ 'is-active': activeNotificationTab === 'unread' }"
                                            type="button"
                                            @click.stop.prevent="activeNotificationTab = 'unread'"
                                        >
                                            Belum Dibaca
                                            <span v-if="unreadNotificationsCount" class="shell-tab-badge">{{
                                                unreadNotificationsCount > 99 ? '99+' : unreadNotificationsCount
                                            }}</span>
                                        </button>
                                        <button
                                            class="shell-popover-tab shell-btn"
                                            data-tour="notif-tab-read"
                                            :class="{ 'is-active': activeNotificationTab === 'read' }"
                                            type="button"
                                            @click.stop.prevent="activeNotificationTab = 'read'"
                                        >
                                            Sudah Dibaca
                                        </button>
                                    </div>
                                    <div class="shell-popover-actions" data-tour="notif-mark-all">
                                        <button
                                            v-if="activeNotificationTab === 'unread' && activeNotifications.length"
                                            class="shell-popover-action shell-btn"
                                            data-tour="notif-mark-all"
                                            type="button"
                                            title="Tandai semua dibaca"
                                            @click="markAllAsRead"
                                        >
                                            <i class="bi bi-check-all text-primary"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="shell-popover-body shell-scrollbar" data-tour="notif-list">
                                    <div v-if="isLoadingNotifications" class="shell-popover-loading">
                                        <div class="spinner-border text-primary spinner-border-sm" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <span class="ms-2 text-muted small">Memuat data...</span>
                                    </div>
                                    <template v-else>
                                        <div
                                            v-for="(item, index) in activeNotifications"
                                            :key="item.id"
                                            class="shell-notification-row"
                                            :class="{
                                                'is-read': item.is_read || activeNotificationTab === 'read',
                                                'is-clickable': item.action_url,
                                            }"
                                            :data-tour="index === 0 ? 'notif-item-first' : undefined"
                                            @click="handleNotificationClick(item)"
                                        >
                                            <div
                                                class="shell-notification-avatar"
                                                :class="`tone-${item.severity || item.type || 'info'}`"
                                            >
                                                <i :class="notificationIcon(item)"></i>
                                            </div>
                                            <div class="shell-notification-content min-w-0 flex-grow-1">
                                                <div class="shell-message-meta">
                                                    <span
                                                        class="shell-notification-title text-truncate"
                                                        :title="item.title"
                                                        >{{ item.title }}</span
                                                    >
                                                    <span class="shell-message-time">{{
                                                        item.created_at_human ||
                                                        formatRelativeTime(item.created_at || item.time)
                                                    }}</span>
                                                </div>
                                                <div class="shell-message-text shell-message-text--wrap">
                                                    {{ item.message || item.desc || 'Tidak ada detail tambahan.' }}
                                                </div>
                                            </div>
                                            <div
                                                class="shell-notification-actions"
                                                v-if="activeNotificationTab === 'unread'"
                                            >
                                                <button
                                                    class="shell-btn shell-notification-check"
                                                    title="Tandai dibaca"
                                                    @click.stop="markSingleAsRead(item)"
                                                >
                                                    <i class="bi bi-check2"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div
                                            v-if="!activeNotifications.length"
                                            class="shell-empty-state"
                                            data-tour="notif-item-first"
                                        >
                                            <div class="shell-empty-state-icon">
                                                <i class="bi bi-bell-slash"></i>
                                            </div>
                                            <div class="shell-empty-state-text">Belum ada notifikasi di tab ini.</div>
                                        </div>
                                    </template>
                                </div>

                                <div class="shell-popover-footer-wrap" data-tour="notif-footer">
                                    <button
                                        class="shell-popover-footer shell-btn"
                                        type="button"
                                        @click="openNotificationModal"
                                    >
                                        Lihat Semua Notifikasi
                                    </button>
                                    <button
                                        v-if="activeNotificationTab === 'unread' && activeNotifications.length"
                                        class="shell-popover-footer is-red shell-btn"
                                        type="button"
                                        @click="markAllAsRead"
                                    >
                                        Bersihkan Riwayat
                                    </button>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <!-- HELP -->
                    <div class="position-relative" data-tour="topbar-help">
                        <button
                            class="shell-topbar-icon shell-btn"
                            :class="{ 'is-active is-dark': shell.state.showHelp, 'is-berdenyut': petunjukTampil }"
                            type="button"
                            :aria-label="panduanHalaman.length ? 'Bantuan (ada panduan & bantuan untuk halaman ini)' : 'Bantuan'"
                            @mousedown.stop
                            @click.stop="toggleHelp"
                        >
                            <i class="bi bi-question-circle"></i>
                            <span
                                v-if="panduanHalaman.length && !shell.state.showHelp"
                                class="shell-badge-dot"
                                data-testid="topbar-panduan-dot"
                                aria-hidden="true"
                            ></span>
                        </button>

                        <!-- Petunjuk "ada panduan di sini" — sekali per halaman per pengguna -->
                        <AppPanduanPetunjuk
                            ref="petunjuk"
                            :panduan="panduanHalaman"
                            :jenis-page="shellMeta?.activeSubMenu || ''"
                            :kunci-pengguna="kunciPenggunaPanduan"
                            :ditahan="shell.state.showHelp || tourBerjalan"
                            @buka-panduan="bukaPanduan"
                            @buka-bantuan="toggleHelp"
                        />

                        <transition name="panel-pop">
                            <div
                                v-show="shell.state.showHelp"
                                class="shell-topbar-popover shell-topbar-popover--sm shell-help-popover is-visible"
                                @mousedown.stop
                                @click.stop
                            >
                                <div class="shell-popover-head">
                                    <div class="shell-popover-title">
                                        <span class="shell-popover-title-icon">
                                            <i class="bi bi-question-circle-fill"></i>
                                        </span>
                                        <span>Pusat Bantuan</span>
                                    </div>
                                    <span v-if="panduanHalaman.length" class="shell-help-head-badge">
                                        <i class="bi bi-book"></i>
                                        <span>{{ panduanHalaman.length }} Panduan</span>
                                    </span>
                                </div>

                                <!-- Filter Segmented Bar -->
                                <div class="shell-help-filter-bar">
                                    <button
                                        type="button"
                                        class="shell-help-filter-pill"
                                        :class="{ 'is-active': activeHelpTab === 'all' }"
                                        @click="activeHelpTab = 'all'"
                                    >
                                        Semua
                                    </button>
                                    <button
                                        v-if="hasTour || panduanHalaman.length"
                                        type="button"
                                        class="shell-help-filter-pill"
                                        :class="{ 'is-active': activeHelpTab === 'guides' }"
                                        @click="activeHelpTab = 'guides'"
                                    >
                                        <span>Panduan Halaman</span>
                                        <span v-if="panduanHalaman.length" class="shell-help-pill-count">{{ cariBantuan ? panduanHalamanTersaring.length : panduanHalaman.length }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="shell-help-filter-pill"
                                        :class="{ 'is-active': activeHelpTab === 'contacts' }"
                                        @click="activeHelpTab = 'contacts'"
                                    >
                                        <span>Kontak</span>
                                        <span v-if="kelompokKontak.length" class="shell-help-pill-count">{{ cariBantuan ? kelompokKontakTersaring.length : kelompokKontak.length }}</span>
                                    </button>
                                </div>

                                <!-- Unified Search Bar -->
                                <div
                                    v-if="panduanHalaman.length > 2 || kelompokKontak.length > 2 || activeHelpTab !== 'all' || cariBantuan"
                                    class="shell-help-search-wrap shell-help-search-wrap--top"
                                >
                                    <i class="bi bi-search shell-help-search-icon"></i>
                                    <input
                                        v-model="cariBantuan"
                                        type="text"
                                        class="shell-help-search-input"
                                        :placeholder="placeholderCari"
                                        @click.stop
                                        @keydown.stop
                                    />
                                    <button
                                        v-if="cariBantuan"
                                        type="button"
                                        class="shell-help-search-clear"
                                        aria-label="Hapus pencarian"
                                        @click.stop="cariBantuan = ''"
                                    >
                                        <i class="bi bi-x-circle-fill"></i>
                                    </button>
                                </div>

                                <!-- Scrollable Body -->
                                <div class="shell-popover-body shell-scrollbar shell-help-body">
                                    <!-- Context-aware Tour CTA & Panduan Halaman -->
                                    <div v-show="activeHelpTab === 'all' || activeHelpTab === 'guides'" class="shell-help-section">
                                        <!-- Context-aware Tour CTA — hanya muncul jika page punya tour terdaftar -->
                                        <div v-if="hasTour" class="shell-tour-card" data-tour="help-tour-cta">
                                            <div class="shell-tour-card__glow" aria-hidden="true"></div>

                                            <div class="shell-tour-card__head">
                                                <span class="shell-tour-card__icon"><i class="bi bi-compass"></i></span>
                                                <div class="shell-tour-card__copy">
                                                    <span class="shell-tour-card__eyebrow">Tour Halaman Ini</span>
                                                    <h4>{{ tourHeadline }}</h4>
                                                    <p>{{ tourSubline }}</p>
                                                </div>
                                            </div>

                                            <div
                                                class="shell-tour-card__progress"
                                                :class="{ 'is-hidden': tourStatus === 'not_started' }"
                                            >
                                                <div class="shell-tour-card__bar">
                                                    <div
                                                        class="shell-tour-card__fill"
                                                        :style="{ width: `${tourPercent}%` }"
                                                    ></div>
                                                </div>
                                                <span class="shell-tour-card__progress-text">
                                                    {{ tourCompletedCount }} / {{ tourTotalSteps }} langkah
                                                </span>
                                            </div>

                                            <div class="shell-tour-card__actions">
                                                <button
                                                    type="button"
                                                    class="shell-tour-card__btn shell-tour-card__btn--primary"
                                                    @click="handleStartTour"
                                                >
                                                    <i class="bi bi-play-circle-fill"></i>
                                                    <span>{{ tourPrimaryLabel }}</span>
                                                </button>
                                                <button
                                                    v-if="tourStatus === 'in_progress'"
                                                    type="button"
                                                    class="shell-tour-card__btn shell-tour-card__btn--ghost"
                                                    @click="handleRestartTour"
                                                    title="Mulai ulang dari awal"
                                                >
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Disabled-state placeholder kalau tidak ada tour untuk page ini -->
                                        <div v-else class="shell-tour-card is-empty" data-tour="help-tour-cta">
                                            <span class="shell-tour-card__icon shell-tour-card__icon--muted">
                                                <i class="bi bi-compass"></i>
                                            </span>
                                            <div class="shell-tour-card__copy">
                                                <h4>Tour Belum Tersedia</h4>
                                                <p>Halaman ini belum punya panduan interaktif.</p>
                                            </div>
                                        </div>

                                        <!-- Panduan (manual) untuk halaman ini — SYSTEM › Manajemen Panduan -->
                                        <div v-if="panduanHalaman.length" class="shell-help-list shell-panduan-list" data-testid="topbar-panduan">
                                            <div class="shell-panduan-judul">
                                                <div class="d-flex align-items-center gap-1">
                                                    <i class="bi bi-book"></i>
                                                    <span>Panduan Halaman Ini</span>
                                                </div>
                                                <span v-if="cariBantuan && panduanHalamanTersaring.length !== panduanHalaman.length" class="shell-panduan-counter is-filtered">
                                                    {{ panduanHalamanTersaring.length }} ditemukan
                                                </span>
                                                <span v-else class="shell-panduan-counter">
                                                    {{ panduanHalaman.length }} {{ panduanHalaman.length > 1 ? 'Manual' : 'Panduan' }}
                                                </span>
                                            </div>

                                            <div class="shell-panduan-items-wrap" :class="{ 'is-scrollable': panduanHalamanDitampilkan.length > 4 }">
                                                <button
                                                    v-for="p in panduanHalamanDitampilkan"
                                                    :key="p.id"
                                                    class="shell-help-row shell-panduan-row shell-btn"
                                                    type="button"
                                                    @click="bukaPanduan(p)"
                                                >
                                                    <span class="shell-help-icon shell-panduan-icon" :class="{ 'is-baru': p.baru }">
                                                        <i :class="['bi', p.baru ? 'bi-stars' : 'bi-file-earmark-pdf']"></i>
                                                    </span>
                                                    <span class="shell-help-copy">
                                                        <span class="shell-help-title text-truncate">
                                                            {{ p.judul }}
                                                            <span v-if="p.baru" class="shell-panduan-baru" data-testid="topbar-panduan-baru">Diperbarui</span>
                                                        </span>
                                                        <span class="shell-help-desc text-truncate" :title="p.baru && p.catatan ? p.catatan : undefined">
                                                            {{ keteranganPanduan(p) }}
                                                        </span>
                                                    </span>
                                                    <span class="shell-help-chevron">
                                                        <i class="bi bi-chevron-right"></i>
                                                    </span>
                                                </button>
                                            </div>

                                            <!-- Tombol Show More / Less saat panduan > 3 di tab 'all' -->
                                            <div v-if="activeHelpTab === 'all' && !cariBantuan && panduanHalamanTersaring.length > 3" class="shell-panduan-footer-actions">
                                                <button
                                                    type="button"
                                                    class="shell-panduan-toggle-btn"
                                                    @click="tampilkanSemuaPanduan = !tampilkanSemuaPanduan"
                                                >
                                                    <span>{{ tampilkanSemuaPanduan ? 'Tampilkan lebih sedikit' : `+ Tampilkan ${panduanHalamanTersaring.length - 3} panduan lainnya` }}</span>
                                                    <i :class="['bi', tampilkanSemuaPanduan ? 'bi-chevron-up' : 'bi-chevron-down']"></i>
                                                </button>
                                            </div>

                                            <!-- Empty state pencarian di panduan -->
                                            <div v-if="cariBantuan && !panduanHalamanTersaring.length" class="shell-help-empty-search">
                                                <i class="bi bi-search"></i>
                                                <span>Tidak ada panduan untuk "{{ cariBantuan }}"</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Kontak bantuan: satu baris per MODUL, diklik membuka modal berisi kontak
                                         per fitur. Sumber: N_HRIS_Master_Kontak_Bantuan. -->
                                    <div v-show="activeHelpTab === 'all' || activeHelpTab === 'contacts'" class="shell-help-section">
                                        <div class="shell-help-list shell-help-contacts-list" data-tour="help-contacts" data-testid="topbar-kontak">
                                            <div class="shell-help-section-header">
                                                <div class="shell-help-section-title">
                                                    <i class="bi bi-headset"></i>
                                                    <span>Kontak Bantuan</span>
                                                </div>
                                                <span v-if="kelompokKontak.length" class="shell-help-section-count">{{ kelompokKontak.length }} modul</span>
                                            </div>

                                            <div v-if="kontakMemuat && !kelompokKontak.length" class="shell-help-empty-search">
                                                <i class="bi bi-arrow-repeat spin-animation"></i>
                                                <span>Memuat kontak…</span>
                                            </div>

                                            <button
                                                v-for="k in kelompokKontakTersaring"
                                                :key="k.kunci"
                                                class="shell-help-row shell-contact-row shell-btn"
                                                :class="{ 'is-relevan': k.relevan }"
                                                type="button"
                                                :data-kelompok="k.kunci"
                                                @click="bukaKontakBantuan(k)"
                                            >
                                                <span class="shell-help-icon shell-contact-icon">
                                                    <i class="bi bi-headset"></i>
                                                </span>
                                                <span class="shell-help-copy">
                                                    <span class="shell-help-title text-truncate">
                                                        {{ k.judul }}
                                                        <span v-if="k.relevan" class="shell-kontak-relevan">Modul ini</span>
                                                    </span>
                                                    <span class="shell-help-desc text-truncate">{{ ringkasanKontak(k) }}</span>
                                                </span>
                                                <span class="shell-help-chevron">
                                                    <i class="bi bi-chevron-right"></i>
                                                </span>
                                            </button>

                                            <div v-if="!kontakMemuat && kelompokKontak.length && !kelompokKontakTersaring.length" class="shell-help-empty-search">
                                                <i class="bi bi-search"></i>
                                                <span>Tidak ada kontak untuk "{{ cariBantuan }}"</span>
                                            </div>
                                            <div v-if="!kontakMemuat && !kelompokKontak.length" class="shell-help-empty-search">
                                                <i class="bi bi-headset"></i>
                                                <span>Kontak bantuan belum tersedia.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="shell-help-version">V.{{ appVersion }} EVO SYSTEMS</div>
                            </div>
                        </transition>
                    </div>
                </div>

                <div class="shell-topbar-divider d-none d-lg-block"></div>

                <div class="position-relative" data-tour="topbar-profile">
                    <button
                        class="shell-module-pill shell-btn"
                        :class="{ 'is-active': shell.state.showBreadcrumb }"
                        type="button"
                        @mousedown.stop
                        @click.stop="goToActiveModule"
                    >
                        <span class="shell-module-pill__left">
                            <span class="shell-module-pill__icon">
                                <i :class="activeModuleIcon"></i>
                            </span>
                            <span class="shell-module-pill__code d-none d-md-inline">{{ activeModuleDisplay }}</span>
                            <span class="shell-module-pill__code d-inline d-md-none">{{ userKodeKaryawan }}</span>
                        </span>

                        <span class="shell-module-pill__divider"></span>

                        <span class="shell-module-pill__user-wrap">
                            <span class="shell-module-pill__user">{{ userName }}</span>
                            <span class="shell-module-pill__nik">{{ userNik }}</span>
                        </span>
                    </button>

                    <div
                        :class="[
                            'shell-topbar-popover',
                            'shell-breadcrumb-popover',
                            { 'is-visible': shell.state.showBreadcrumb },
                        ]"
                        data-tour="breadcrumb-popover"
                        @mousedown.stop
                        @click.stop
                    >
                        <!-- User Identity Card -->
                        <div class="shell-user-card">
                            <div class="shell-user-card__avatar">
                                {{ userInitials }}
                            </div>
                            <div class="shell-user-card__info">
                                <div class="shell-user-card__name">{{ userName }}</div>
                                <div class="shell-user-card__meta">
                                    <span class="shell-user-card__badge shell-user-card__badge--nik">
                                        <i class="bi bi-person-badge"></i>
                                        {{ userNik }}
                                    </span>
                                    <span
                                        v-if="userDepartment"
                                        class="shell-user-card__badge shell-user-card__badge--dept"
                                    >
                                        <i class="bi bi-building"></i>
                                        {{ userDepartment }}
                                    </span>
                                </div>
                            </div>
                            <button
                                class="shell-user-card__logout shell-btn"
                                type="button"
                                title="Keluar dari Akun"
                                @click="handleLogout"
                            >
                                <i class="bi bi-box-arrow-right"></i>
                            </button>
                        </div>

                        <div class="shell-popover-head">
                            <div>
                                <div class="shell-popover-title">Jalur Navigasi Anda</div>
                                <div
                                    class="shell-popover-subtitle"
                                    style="font-size: 0.65rem; color: #64748b; margin-top: 0.15rem"
                                >
                                    Melacak posisi Anda saat ini di dalam aplikasi
                                </div>
                            </div>
                        </div>

                        <div class="shell-transit-map">
                            <!-- Platform Node -->
                            <div class="shell-transit-node is-completed">
                                <div class="shell-transit-station">
                                    <div class="shell-transit-dot"><i class="bi bi-house-door-fill"></i></div>
                                    <div class="shell-transit-line"></div>
                                </div>
                                <div class="shell-transit-info">
                                    <div class="shell-transit-label">Platform Utama</div>
                                    <button class="shell-transit-link" @click="handleModalNavigate(homeUrl)">
                                        Beranda EVO
                                        <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Module Node -->
                            <div class="shell-transit-node is-active">
                                <div class="shell-transit-station">
                                    <div class="shell-transit-dot"><i :class="activeModuleIcon"></i></div>
                                    <div class="shell-transit-line"></div>
                                </div>
                                <div class="shell-transit-info">
                                    <div class="shell-transit-label">Modul Aktif</div>
                                    <div class="shell-transit-name">{{ activeModuleDisplay }}</div>

                                    <!-- Quick Jump Siblings -->
                                    <div class="shell-transit-branches" v-if="breadcrumbSiblings.length">
                                        <div class="shell-transit-branches-title">Akses Cepat (Cabang Jalur):</div>
                                        <div class="shell-transit-chips">
                                            <button
                                                v-for="sib in breadcrumbSiblings"
                                                :key="sib.id"
                                                class="shell-transit-chip shell-btn"
                                                :title="sib.title"
                                                @click="handleModalNavigate(sib.url)"
                                            >
                                                <i :class="sib.icon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Current Page Node -->
                            <div class="shell-transit-node is-current">
                                <div class="shell-transit-station">
                                    <div class="shell-transit-dot-pulse"></div>
                                </div>
                                <div class="shell-transit-info">
                                    <div class="shell-transit-label">Titik Lokasi Anda</div>
                                    <div class="shell-transit-name" style="font-size: 1rem; color: #f59e0b">
                                        {{ activePageDisplay }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <AppNotificationModal
            v-model="showNotificationModal"
            @notification-read="onModalNotificationRead"
            @navigate="handleModalNavigate"
        />
    </header>

    <!-- ACTIVITY READY TOAST (pengganti SweetAlert) -->
    <!-- Teleport ke body: header punya backdrop-filter yang bikin containing-block
         baru sehingga position:fixed ke-clip di dalam header (toast kepotong). -->
    <Teleport to="body">
        <transition name="ac-toast">
            <div v-if="activity.state.toast" class="ac-toast" role="status" @mousedown.stop @click.stop>
                <span class="ac-toast__icon"><i class="bi bi-check-circle-fill"></i></span>
                <div class="ac-toast__body">
                    <div class="ac-toast__title">{{ toastTitle }}</div>
                    <div class="ac-toast__label" :title="activity.state.toast.label">
                        {{ activity.state.toast.label }}
                    </div>
                    <div
                        v-if="activity.state.toast.download_url || activity.state.toast.open_url"
                        class="ac-toast__actions"
                    >
                        <button class="ac-act dl" type="button" @click="activity.downloadToast()">
                            <i class="bi" :class="toastActionIcon"></i> {{ toastActionLabel }}
                        </button>
                    </div>
                </div>
                <button class="ac-dismiss" type="button" title="Tutup" @click="activity.dismissToast()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </transition>
    </Teleport>

    <HcisFileViewer
        v-if="panduanDibuka"
        v-model="panduanDibuka"
        :files="panduanBerkas"
        :allow-download="true"
        :allow-print="true"
    />

    <HcisDialog
        :model-value="!!kontakTerbuka"
        :title="kontakTerbuka?.judul || 'Kontak bantuan'"
        kicker="Pusat Bantuan Resmi"
        :description="kontakTerbuka ? `${kontakTerbuka.subjudul} — pilih salah satu kontak untuk bertanya.` : ''"
        :badge="kontakTerbuka ? `${kontakTerbuka.jumlah} PIC` : ''"
        badge-variant="info"
        :badge-dot="true"
        icon="headset"
        icon-variant="glass"
        sentiment="info"
        variant="glass"
        size="md"
        @update:model-value="(buka) => !buka && (kontakTerbuka = null)"
    >
        <!-- Per fitur (Umum dulu). Satu fitur saja: langsung daftar kontak tanpa judul. -->
        <div v-if="kontakTerbuka" class="shell-kontak-bagian-daftar" data-testid="kontak-modul-dialog">
            <!-- Filter search if contacts > 2 or multiple sections -->
            <div
                v-if="kontakTerbuka.jumlah > 2 || (kontakTerbuka.bagian && kontakTerbuka.bagian.length > 1)"
                class="shell-kontak-modal-search"
            >
                <i class="bi bi-search shell-kontak-modal-search-icon"></i>
                <input
                    v-model="cariKontakModal"
                    type="text"
                    class="shell-kontak-modal-search-input"
                    placeholder="Cari PIC, nama, atau fitur..."
                />
                <button
                    v-if="cariKontakModal"
                    type="button"
                    class="shell-kontak-modal-search-clear"
                    title="Hapus filter"
                    aria-label="Hapus filter"
                    @click="cariKontakModal = ''"
                >
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>

            <!-- Toolbar bar with toggle all button if multiple sections -->
            <div v-if="kontakTerbuka.bagian && kontakTerbuka.bagian.length > 1" class="shell-kontak-toolbar-meta">
                <span class="shell-kontak-toolbar-count">{{ kontakTerbuka.bagian.length }} Kategori Fitur</span>
                <button
                    type="button"
                    class="shell-kontak-toggle-all-btn"
                    @click="toggleSemuaBagian"
                >
                    <i :class="['bi', semuaBagianTerbuka ? 'bi-arrows-collapse' : 'bi-arrows-expand']"></i>
                    <span>{{ semuaBagianTerbuka ? 'Tutup Semua' : 'Buka Semua' }}</span>
                </button>
            </div>

            <section
                v-for="b in bagianKontakModalTersaring"
                :key="b.kode_fitur"
                class="shell-kontak-bagian"
                :class="{ 'is-collapsed': !isBagianTerbuka(b.kode_fitur) }"
                :data-fitur="b.kode_fitur"
            >
                <div
                    v-if="kontakTerbuka.bagian.length > 1"
                    class="shell-kontak-bagian__header shell-kontak-bagian__judul is-clickable"
                    role="button"
                    tabindex="0"
                    :aria-expanded="isBagianTerbuka(b.kode_fitur)"
                    @click="toggleBagian(b.kode_fitur)"
                    @keydown.enter.space.prevent="toggleBagian(b.kode_fitur)"
                >
                    <div class="shell-kontak-bagian__tag">
                        <i class="bi bi-layers-fill"></i>
                        <span>Fitur {{ b.label }}</span>
                        <span v-if="isBagianIniRelevan(b)" class="shell-kontak-bagian__badge-relevan">Halaman Ini</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="shell-kontak-bagian__jumlah">{{ b.kontak.length }} Kontak</span>
                        <i class="bi bi-chevron-down shell-kontak-bagian__chevron" :class="{ 'is-rotated': isBagianTerbuka(b.kode_fitur) }"></i>
                    </div>
                </div>
                <div v-show="isBagianTerbuka(b.kode_fitur)" class="shell-kontak-bagian__content">
                    <KontakBantuanDaftar :kontak="b.kontak" />
                </div>
            </section>

            <!-- Empty Search State -->
            <div v-if="!bagianKontakModalTersaring.length" class="shell-kontak-empty-search">
                <i class="bi bi-search"></i>
                <span>Tidak ada kontak yang cocok dengan "<strong>{{ cariKontakModal }}</strong>"</span>
            </div>

            <!-- Official Notice Callout -->
            <div class="shell-kontak-notice-card">
                <div class="shell-kontak-notice-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div class="shell-kontak-notice-body">
                    <span class="shell-kontak-notice-title">Dukungan Resmi Modul {{ kontakTerbuka.subjudul || kontakTerbuka.judul }}</span>
                    <p class="shell-kontak-notice-desc">
                        Pesan atau panggilan akan langsung terhubung ke PIC resmi modul ini. Gunakan saluran yang tersedia sesuai jam layanan kerja.
                    </p>
                </div>
            </div>
        </div>

        <template #footer-start>
            <div class="shell-kontak-footer-verified">
                <i class="bi bi-patch-check-fill"></i>
                <span>Layanan Resmi HCIS</span>
            </div>
        </template>

        <template #footer>
            <HcisButton variant="secondary" size="md" label="Tutup" @click="kontakTerbuka = null" />
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { useShellState } from '../../composables/useShellState';
import { useActivityCenter } from '../../composables/useActivityCenter';
import AppNotificationModal from './AppNotificationModal.vue';
import HcisButton from '../ui/HcisButton.vue';
import HcisDialog from '../ui/HcisDialog.vue';
import HcisFileViewer from '../ui/HcisFileViewer.vue';
import KontakBantuanDaftar from '../SYSTEM/KontakBantuan/KontakBantuanDaftar.vue';
import AppPanduanPetunjuk from './AppPanduanPetunjuk.vue';
import { catatTanggapan, tandaTangan } from './panduanPengingat.js';
import { useActiveTour } from '../../composables/tourRegistry.js';
import { runLogoutTransition } from '../../utils/logout-transition.js';

const shell = useShellState();
const activity = useActivityCenter();
const page = usePage();

function toggleActivityCenter() {
    if (shell.state.showActivity) {
        shell.state.showActivity = false;
    } else {
        shell.resetInteractionState();
        shell.state.showActivity = true;
        activity.refresh();
    }
}

// ── Toast: judul & aksi menyesuaikan tipe task (export vs upload) ──────────
const toastTitle = computed(() => {
    const t = activity.state.toast;
    if (!t) return '';
    return t.type === 'upload' ? 'Upload selesai' : 'Export siap diunduh';
});
const toastActionLabel = computed(() => (activity.state.toast?.type === 'upload' ? 'Buka Folder' : 'Download'));
const toastActionIcon = computed(() => (activity.state.toast?.type === 'upload' ? 'bi-folder2-open' : 'bi-download'));

/** Klik item panel: item upload yang ready → buka folder konten. */
function onActivityItemClick(item) {
    if (item.type === 'upload' && item.status === 'ready' && item.open_url) {
        shell.state.showActivity = false;
        activity.openItem(item);
    }
}

function activityStatusIcon(status) {
    switch (status) {
        case 'ready':
            return 'bi-check-lg';
        case 'failed':
            return 'bi-exclamation-triangle-fill';
        case 'expired':
            return 'bi-clock-history';
        default:
            return 'bi-arrow-down';
    }
}

// ── Clear all completed / failed activities ─────────────────────────────
const isClearingActivities = ref(false);
/*
 * Tombol "Bersihkan" hanya muncul bila ADA YANG BISA DIBERSIHKAN.
 *
 * Syarat "bukan queued/processing" saja tidak cukup: jenis pdf-render memang
 * tak mengenal sembunyikan (ia status sumber, bukan task) dan datang tanpa
 * dismiss_url. Panel yang hanya berisi satu pdf-render selesai karena itu
 * memperlihatkan tombol yang tak akan pernah melakukan apa pun.
 *
 * Syaratnya dijawab composable — satu definisi yang sama dengan yang dipakai
 * saat membersihkan, supaya tombolnya tak pernah menjanjikan lebih dari yang
 * dikerjakan aksinya.
 */
const hasClearableActivities = computed(() => activity.state.items.some(activity.isClearable));

async function handleClearAllActivities() {
    if (isClearingActivities.value) return;
    isClearingActivities.value = true;
    try {
        await activity.dismissAllCompletedOrFailed();
    } finally {
        isClearingActivities.value = false;
    }
}

// ── Context-aware tour CTA ─────────────────────────────────────────────
const { activeTour, hasTour } = useActiveTour();

const tourStatus = computed(() => activeTour.value?.progress?.value?.status || 'not_started');
const tourCompletedCount = computed(() => activeTour.value?.completedCount?.value ?? 0);
const tourTotalSteps = computed(() => activeTour.value?.totalSteps ?? 0);
const tourPercent = computed(() => activeTour.value?.progressPercent?.value ?? 0);

const tourHeadline = computed(() => {
    const s = tourStatus.value;
    if (s === 'completed') return 'Tour Sudah Selesai 🎉';
    if (s === 'skipped') return 'Tour Sebelumnya Dilewati';
    if (s === 'in_progress') return 'Lanjutkan Tour-mu';
    return activeTour.value?.label || 'Mulai Tour Interaktif';
});

const tourSubline = computed(() => {
    const s = tourStatus.value;
    if (s === 'completed') return 'Mau refresh ingatan? Bisa diulang kapan saja.';
    if (s === 'skipped') return 'Coba lagi yuk — singkat saja kok.';
    if (s === 'in_progress') return `${tourCompletedCount.value} dari ${tourTotalSteps.value} langkah selesai.`;
    return activeTour.value?.description || 'Pandu kamu mengenali halaman ini.';
});

const tourPrimaryLabel = computed(() => {
    const s = tourStatus.value;
    if (s === 'in_progress') return 'Lanjutkan';
    if (s === 'completed' || s === 'skipped') return 'Ulangi Tour';
    return 'Mulai Tour';
});

const handleStartTour = () => {
    const t = activeTour.value;
    if (!t) return;
    const fromBeginning = tourStatus.value !== 'in_progress';
    shell.state.showHelp = false;
    setTimeout(() => t.start({ fromBeginning }), 220);
};

const handleRestartTour = () => {
    const t = activeTour.value;
    if (!t) return;
    shell.state.showHelp = false;
    setTimeout(() => t.start({ fromBeginning: true }), 220);
};
const topbarRoot = ref(null);
const TOPBAR_NOTIFICATION_SOURCE = 'MAIN_NOTIF';
const showNotificationModal = ref(false);

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    brand: {
        type: Object,
        default: () => ({}),
    },
    layout: {
        type: Object,
        default: () => ({}),
    },
    navigation: {
        type: Object,
        default: () => ({
            home: null,
            modules: [],
        }),
    },
});

const localNotifications = ref(props.layout?.notifications || []);
const localUnreadNotificationsCount = ref(props.layout?.unreadNotificationsCount || 0);
const localReadNotifications = ref([]);
const activeNotificationTab = ref('unread');
const isLoadingNotifications = ref(false);

const notifications = computed(() => localNotifications.value || []);
const activeNotifications = computed(() => {
    return activeNotificationTab.value === 'unread' ? localNotifications.value : localReadNotifications.value;
});
const messages = computed(() => props.layout?.messages || []);
const shellMeta = computed(() => props.layout?.shell || {});
const unreadNotificationsCount = computed(() => localUnreadNotificationsCount.value || 0);
const appVersion = computed(() => props.layout?.appVersion || '2.12.8');
const userName = computed(() => props.user?.name || props.user?.username || 'User');
const userNik = computed(() => props.user?.nik || '-');
const userKodeKaryawan = computed(() => props.user?.kode_karyawan || '-');
const userDepartment = computed(() => props.user?.department || null);
const userInitials = computed(() => {
    const parts = String(userName.value).trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return String(userName.value).slice(0, 2).toUpperCase();
});
const firstName = computed(() => String(userName.value).split(' ')[0] || userName.value);
const homeUrl = computed(() => props.navigation?.home?.url || '/homepage');

const activeModuleCode = computed(() => {
    if (shellMeta.value?.activeModule === 'HOME') {
        return 'HOME';
    }

    return shellMeta.value?.activeModule || 'APP';
});

const activeModuleDisplay = computed(() => (activeModuleCode.value === 'HOME' ? 'DASHBOARD' : activeModuleCode.value));

const activeModuleData = computed(() => {
    return (props.navigation?.modules || []).find((module) => module.code === activeModuleCode.value);
});

const activePageData = computed(() => {
    if (!activeModuleData.value) return null;
    let found = null;
    const currentPath = page.url.split('?')[0];

    (activeModuleData.value.groups || []).forEach((group) => {
        (group.items || []).forEach((item) => {
            const isMatchByCode =
                (shellMeta.value?.activeSubMenu && shellMeta.value.activeSubMenu === item.jenisPage) ||
                (shell.state.activeSubMenu && shell.state.activeSubMenu === item.jenisPage);

            let isMatchByUrl = false;
            if (item.url) {
                try {
                    const itemPath = new URL(item.url, window.location.origin).pathname;
                    // Exact match or sub-route match (e.g., /hcis/kpi/form inside /hcis/kpi)
                    isMatchByUrl = currentPath === itemPath || currentPath.startsWith(itemPath + '/');
                } catch (e) {}
            }

            if (isMatchByCode || Boolean(item.isActive) || isMatchByUrl) {
                found = item;
            }
        });
    });
    return found;
});

const activePageDisplay = computed(() => {
    if (activeModuleCode.value === 'HOME') return 'Beranda EVO';
    // Prioritas 1: langsung dari server (shellMeta)
    if (shellMeta.value?.activeSubMenuLabel) return shellMeta.value.activeSubMenuLabel;
    // Prioritas 2: dari pencocokan manual (field = title, bukan label)
    if (activePageData.value?.title) return activePageData.value.title;
    // Prioritas 3: dari currentPageTitle server
    if (shellMeta.value?.currentPageTitle) return shellMeta.value.currentPageTitle;
    return 'Halaman Belum Terpetakan';
});

const activeModuleIcon = computed(() => {
    if (activeModuleCode.value === 'HOME') {
        return 'bi bi-house-door-fill';
    }
    return activeModuleData.value?.icon || 'bi bi-grid-1x2-fill';
});

const breadcrumbSiblings = computed(() => {
    if (!activeModuleData.value) return [];

    const siblings = [];
    (activeModuleData.value.groups || []).forEach((group) => {
        // Abaikan grup administrator agar tidak meramaikan akses cepat
        if (group.title && group.title.toLowerCase().includes('admin')) return;

        (group.items || []).forEach((item) => {
            const titleMatch =
                item.title &&
                (item.title.toLowerCase().includes('admin') || item.title.toLowerCase().includes('pengaturan'));
            const urlMatch = item.url && item.url.toLowerCase().includes('admin');

            if (!titleMatch && !urlMatch) {
                siblings.push({
                    id: item.id || item.title,
                    title: item.title,
                    url: item.url,
                    icon: item.icon || 'bi bi-circle',
                });
            }
        });
    });
    return siblings;
});

const activeModuleUrl = computed(() => shellMeta.value?.activeModuleUrl || homeUrl.value);


const activeHelpTab = ref('all');
const cariBantuan = ref('');
const tampilkanSemuaPanduan = ref(false);

const placeholderCari = computed(() => {
    if (activeHelpTab.value === 'guides') return 'Cari panduan halaman...';
    if (activeHelpTab.value === 'contacts') return 'Cari modul atau kontak bantuan...';
    return 'Cari panduan atau kontak bantuan...';
});

watch(
    () => activeHelpTab.value,
    () => {
        tampilkanSemuaPanduan.value = false;
    },
);

// ---------------- Kontak bantuan (Pusat Bantuan) ----------------
// Dimuat saat Pusat Bantuan dibuka; urutan mengikuti modul halaman aktif.
const kelompokKontak = ref([]);
const kontakMemuat = ref(false);
const kontakTerbuka = ref(null);
let halamanKontakDimuat = null;

async function muatKontakBantuan() {
    const halaman = shellMeta.value?.activeSubMenu || '';
    if (kontakMemuat.value || halamanKontakDimuat === halaman) return;
    kontakMemuat.value = true;
    try {
        const { data } = await axios.get('/bantuan/kontak', { params: halaman ? { halaman } : {} });
        kelompokKontak.value = Array.isArray(data?.result) ? data.result : [];
        halamanKontakDimuat = halaman;
    } catch {
        // Informasi, bukan syarat: kosong saja, coba lagi saat dibuka berikutnya.
    } finally {
        kontakMemuat.value = false;
    }
}

watch(
    () => shell.state.showHelp,
    (buka) => {
        if (buka) muatKontakBantuan();
    },
);

const kelompokKontakTersaring = computed(() => {
    const q = cariBantuan.value.toLowerCase().trim();
    if (!q) return kelompokKontak.value;
    return kelompokKontak.value.filter((k) =>
        [
            k.judul,
            k.subjudul,
            ...(k.bagian || []).flatMap((b) => [b.label, ...(b.kontak || []).flatMap((c) => [c.nama, c.keterangan, c.email])]),
        ]
            .filter(Boolean)
            .some((teks) => String(teks).toLowerCase().includes(q)),
    );
});

/** "4 kontak · Permit, Tamu" — fitur di dalam modul; tanpa fitur: nama modulnya. */
function ringkasanKontak(k) {
    const isi = k.fitur?.length ? k.fitur.join(', ') : k.subjudul;
    return `${k.jumlah} kontak · ${isi}`;
}

function bukaKontakBantuan(kelompok) {
    shell.state.showHelp = false;
    kontakTerbuka.value = kelompok;
}

const cariKontakModal = ref('');

const bagianKontakModalTersaring = computed(() => {
    if (!kontakTerbuka.value?.bagian) return [];
    const q = cariKontakModal.value.toLowerCase().trim();
    if (!q) return kontakTerbuka.value.bagian;

    return kontakTerbuka.value.bagian
        .map((b) => {
            const kontakCocok = (b.kontak || []).filter((k) =>
                [k.nama, k.keterangan, k.email, k.no_hp, b.label]
                    .filter(Boolean)
                    .some((teks) => String(teks).toLowerCase().includes(q))
            );
            return {
                ...b,
                kontak: kontakCocok,
            };
        })
        .filter((b) => b.kontak.length > 0);
});

const bagianTerbuka = ref(new Set());

function toggleBagian(kodeFitur) {
    const next = new Set(bagianTerbuka.value);
    if (next.has(kodeFitur)) {
        next.delete(kodeFitur);
    } else {
        next.add(kodeFitur);
    }
    bagianTerbuka.value = next;
}

function isBagianTerbuka(kodeFitur) {
    if (!kontakTerbuka.value?.bagian || kontakTerbuka.value.bagian.length <= 1) return true;
    if (cariKontakModal.value.trim()) return true;
    return bagianTerbuka.value.has(kodeFitur);
}

const semuaBagianTerbuka = computed(() => {
    if (!kontakTerbuka.value?.bagian || kontakTerbuka.value.bagian.length <= 1) return false;
    return kontakTerbuka.value.bagian.every((b) => bagianTerbuka.value.has(b.kode_fitur));
});

function toggleSemuaBagian() {
    if (semuaBagianTerbuka.value) {
        bagianTerbuka.value = new Set();
    } else {
        const next = new Set();
        (kontakTerbuka.value?.bagian || []).forEach((b) => next.add(b.kode_fitur));
        bagianTerbuka.value = next;
    }
}

function cariBagianAktif(bagianList) {
    if (!Array.isArray(bagianList) || !bagianList.length) return null;

    const subMenu = String(shellMeta.value?.activeSubMenu || '').toUpperCase();
    const subLabel = String(shellMeta.value?.activeSubMenuLabel || '').toUpperCase();
    const url = String(page.url || '').toUpperCase();

    // 1. Cek apakah ada bagian fitur yang cocok dengan halaman/fitur aktif saat ini
    const cocok = bagianList.find((b) => {
        const kode = String(b.kode_fitur || '').toUpperCase();
        const label = String(b.label || '').toUpperCase();
        if (!kode || kode === 'UMUM') return false;

        return (
            (subMenu && (subMenu.includes(kode) || kode.includes(subMenu))) ||
            (subLabel && (subLabel.includes(label) || label.includes(subLabel))) ||
            (url && url.includes(kode))
        );
    });

    if (cocok) {
        return cocok.kode_fitur;
    }

    // 2. Jika tidak ada yang cocok spesifik, buka bagian yang paling atas
    return bagianList[0]?.kode_fitur || null;
}

function isBagianIniRelevan(b) {
    if (!kontakTerbuka.value?.relevan) return false;
    const kode = String(b.kode_fitur || '').toUpperCase();
    const subMenu = String(shellMeta.value?.activeSubMenu || '').toUpperCase();
    const url = String(page.url || '').toUpperCase();
    if (!kode || kode === 'UMUM') return false;
    return (subMenu && (subMenu.includes(kode) || kode.includes(subMenu))) || (url && url.includes(kode));
}

watch(
    () => kontakTerbuka.value,
    (val) => {
        cariKontakModal.value = '';
        const initial = new Set();
        if (val?.bagian?.length) {
            // Buka section fitur aktif saat ini, atau yang paling atas saja
            const aktifKode = cariBagianAktif(val.bagian);
            if (aktifKode) {
                initial.add(aktifKode);
            }
        }
        bagianTerbuka.value = initial;
    },
    { immediate: true }
);

const currentTime = ref(new Date());
let timer = null;
let notificationPollTimer = null;

const greetingLabel = computed(() => {
    const hour = currentTime.value.getHours();
    if (hour < 11) return 'SELAMAT PAGI';
    if (hour < 15) return 'SELAMAT SIANG';
    if (hour < 19) return 'SELAMAT SORE';
    return 'SELAMAT MALAM';
});

const currentTimeText = computed(() =>
    currentTime.value.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    }),
);

const currentDateText = computed(() =>
    currentTime.value.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }),
);

function isTourActive() {
    return document.documentElement.classList.contains('hcis-tour-active');
}

function notificationIcon(item) {
    const tone = String(item?.severity || item?.type || '').toLowerCase();

    if (tone.includes('security')) return 'bi bi-shield-check';
    if (tone.includes('warning') || tone.includes('alert')) return 'bi bi-exclamation-triangle';
    if (tone.includes('success')) return 'bi bi-check-circle';
    return 'bi bi-bell';
}

function formatRelativeTime(value) {
    if (!value) {
        return '';
    }

    if (typeof value === 'string' && value.match(/^\d+[mh]$/i)) {
        return value;
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return String(value);
    }

    return parsed.toLocaleString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: 'short',
    });
}

function toggleMessages() {
    if (shell.state.showMessages) {
        shell.state.showMessages = false;
    } else {
        shell.resetInteractionState();
        shell.state.showMessages = true;
    }
}

function toggleNotifications() {
    if (shell.state.showNotifications) {
        shell.state.showNotifications = false;
    } else {
        shell.resetInteractionState();
        shell.state.showNotifications = true;
        refreshTopbarNotifications(activeNotificationTab.value);
    }
}

watch(activeNotificationTab, (newTab) => {
    if (shell.state.showNotifications) {
        refreshTopbarNotifications(newTab);
    }
});

/*
 * Panduan halaman aktif (SYSTEM › Manajemen Panduan, DDL-2026-146).
 * Diambil ulang setiap halaman berganti; server hanya memulangkan panduan yang
 * boleh dilihat pengguna ini (hak buka halaman + role khusus). Gagal = kosong:
 * panduan hanya pelengkap, tidak boleh mengganggu topbar.
 */
const panduanHalaman = ref([]);
const panduanDibuka = ref(false);
const panduanBerkas = ref([]);

const petunjuk = ref(null);
const petunjukTampil = computed(() => !!petunjuk.value?.tampil);
const kunciPenggunaPanduan = computed(() => String(props.user?.kode_karyawan || props.user?.nik || props.user?.id || ''));
// Tour sedang berjalan: petunjuk menunggu (keduanya bersaing di sekitar topbar).
const tourBerjalan = computed(() => {
    const aktif = activeTour.value?.isActive;
    return !!(aktif && typeof aktif === 'object' && 'value' in aktif ? aktif.value : aktif);
});

// Pindah halaman cepat: jawaban permintaan lama tidak boleh menimpa yang baru.
let nomorMuatPanduan = 0;

async function muatPanduan(jenisPage) {
    const nomor = ++nomorMuatPanduan;
    panduanHalaman.value = [];
    if (!jenisPage) return;
    try {
        const { data } = await axios.get(`/panduan/halaman/${encodeURIComponent(jenisPage)}`);
        if (nomor !== nomorMuatPanduan) return;
        panduanHalaman.value = Array.isArray(data?.result) ? data.result : [];
    } catch {
        if (nomor === nomorMuatPanduan) panduanHalaman.value = [];
    }
}

watch(() => shellMeta.value?.activeSubMenu, (jenisPage) => muatPanduan(jenisPage), { immediate: true });

/** Baru diperbarui: tanggal + catatan perubahan; selain itu deskripsi biasa. */
function keteranganPanduan(p) {
    if (p.baru) {
        const d = p.diperbarui ? new Date(p.diperbarui) : null;
        const tgl = d && !Number.isNaN(d.getTime()) ? d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) : '';
        return [tgl && `Diperbarui ${tgl}`, p.catatan].filter(Boolean).join(' · ') || 'Versi baru tersedia';
    }
    return p.deskripsi || (p.halaman_mulai > 1 ? `Mulai halaman ${p.halaman_mulai}` : 'Buka panduan');
}

function bukaPanduan(p) {
    // Dibuka dari mana pun (petunjuk atau daftar Pusat Bantuan) = "sudah pernah
    // membuka": pengingat berikutnya baru setelah HARI_ULANG hari.
    catatTanggapan({
        pengguna: kunciPenggunaPanduan.value,
        halaman: shellMeta.value?.activeSubMenu || '',
        tanda: tandaTangan(panduanHalaman.value),
        jenis: 'dibuka',
    });
    shell.state.showHelp = false;
    panduanBerkas.value = [{
        url: `/panduan/${p.id}/berkas`,
        name: `${p.judul}.pdf`,
        title: p.versi ? `${p.judul} (${p.versi})` : p.judul,
        ext: 'pdf',
        halamanMulai: p.halaman_mulai || 1,
        allowDownload: true,
    }];
    panduanDibuka.value = true;
}

const panduanHalamanTersaring = computed(() => {
    // Prioritaskan yang baru diperbarui di urutan paling atas
    const list = [...panduanHalaman.value].sort((a, b) => {
        if (a.baru && !b.baru) return -1;
        if (!a.baru && b.baru) return 1;
        return 0;
    });
    const q = cariBantuan.value.toLowerCase().trim();
    if (!q) return list;
    return list.filter((p) => {
        const judul = String(p.judul || '').toLowerCase();
        const deskripsi = String(p.deskripsi || '').toLowerCase();
        const catatan = String(p.catatan || '').toLowerCase();
        return judul.includes(q) || deskripsi.includes(q) || catatan.includes(q);
    });
});

const panduanHalamanDitampilkan = computed(() => {
    const list = panduanHalamanTersaring.value;
    // Pada tab 'all' tanpa pencarian aktif, batasi 3 item jika belum di-expand
    if (activeHelpTab.value === 'all' && !cariBantuan.value && !tampilkanSemuaPanduan.value && list.length > 3) {
        return list.slice(0, 3);
    }
    return list;
});

function toggleHelp(tab = 'all') {
    if (shell.state.showHelp && (typeof tab !== 'string' || activeHelpTab.value === tab)) {
        shell.state.showHelp = false;
    } else {
        shell.resetInteractionState();
        activeHelpTab.value = typeof tab === 'string' ? tab : 'all';
        cariBantuan.value = '';
        tampilkanSemuaPanduan.value = false;
        shell.state.showHelp = true;
    }
}

async function markAllAsRead() {
    if (isTourActive()) {
        return;
    }

    shell.resetInteractionState();

    try {
        await axios.post(
            '/homepage/notifications/read-all',
            {
                source: TOPBAR_NOTIFICATION_SOURCE,
            },
            { headers: { 'X-Silent-419': '1' } },
        );
        localNotifications.value = [];
        localUnreadNotificationsCount.value = 0;
        if (activeNotificationTab.value === 'read') {
            refreshTopbarNotifications('read');
        }
    } catch (error) {
        console.error('Failed to mark notifications as read:', error);
    }
}

async function refreshTopbarNotifications(tab = 'unread') {
    if (isLoadingNotifications.value) return;
    isLoadingNotifications.value = true;
    try {
        const isRead = tab === 'read';
        const response = await axios.get('/homepage/notifications/all', {
            params: {
                source: TOPBAR_NOTIFICATION_SOURCE,
                limit: 50,
                is_read: isRead ? 1 : 0,
            },
        });

        const data = Array.isArray(response.data?.data) ? response.data.data : [];
        if (isRead) {
            localReadNotifications.value = data;
        } else {
            localNotifications.value = data;
        }
    } catch (error) {
        console.error('Failed to refresh topbar notifications:', error);
    } finally {
        isLoadingNotifications.value = false;
    }
}

async function pollUnreadNotifications() {
    try {
        const response = await axios.get('/homepage/notifications/unread', {
            params: {
                source: TOPBAR_NOTIFICATION_SOURCE,
                limit: 10,
            },
        });

        localNotifications.value = Array.isArray(response.data?.data) ? response.data.data : [];
        localUnreadNotificationsCount.value = Number(response.data?.count ?? localNotifications.value.length);
        // Sukses → reset backoff ke interval normal.
        notificationPollDelay = NOTIFICATION_POLL_INTERVAL;
    } catch (error) {
        // Gagal (mis. DB jenuh) → mundur eksponensial, cap 5 menit. Mencegah tiap
        // browser terus menembak DB yang sudah kepayahan (feedback loop badai koneksi).
        notificationPollDelay = Math.min(notificationPollDelay * 2, NOTIFICATION_POLL_MAX_INTERVAL);
        console.error('Failed to poll topbar notifications:', error);
    }
}

function handleNotificationClick(item) {
    if (isTourActive()) {
        return;
    }

    if (item.action_url) {
        shell.resetInteractionState();
        // Navigasi dulu, baru tandai dibaca di onBefore. Memutasi list notifikasi
        // SEBELUM router.visit meng-unmount baris yang di-klik dan mengganggu visit
        // pertama (gejala: klik pertama terpental, klik kedua baru jalan).
        router.visit(item.action_url, {
            onBefore: () => {
                if (!item.is_read) {
                    markSingleAsRead(item, { removeFromList: false });
                }
            },
        });
        return;
    }

    // Tidak ada tujuan navigasi -> cukup tandai dibaca.
    if (!item.is_read) {
        markSingleAsRead(item);
    }
}

function handleModalNavigate(url) {
    shell.resetInteractionState();
    router.visit(url);
}

function onModalNotificationRead(item) {
    // Kurangi counter badge
    if (localUnreadNotificationsCount.value > 0) {
        localUnreadNotificationsCount.value--;
    }
    // Update di list lokal
    const index = localNotifications.value.findIndex((n) => n.id === item.id);
    if (index !== -1) {
        localNotifications.value[index].is_read = true;
    }
    // Pindahkan ke read list
    if (!localReadNotifications.value.find((n) => n.id === item.id)) {
        localReadNotifications.value.unshift({ ...item, is_read: true });
    }
}

function openNotificationModal() {
    if (isTourActive()) {
        return;
    }

    shell.resetInteractionState();
    showNotificationModal.value = true;
}

async function markSingleAsRead(item, { removeFromList = true } = {}) {
    if (isTourActive()) {
        return;
    }

    try {
        await axios.post(`/homepage/notifications/${item.id}/read`, null, {
            headers: { 'X-Silent-419': '1' },
        });
        localUnreadNotificationsCount.value = Math.max(0, localUnreadNotificationsCount.value - 1);
        item.is_read = true;

        // Hanya hapus dari list unread saat dipakai sebagai aksi mandiri (tombol
        // "tandai dibaca"). Saat dipicu dari klik notifikasi yang melakukan
        // navigasi, JANGAN memutasi list -> baris ter-unmount & visit pertama gagal.
        if (removeFromList) {
            localNotifications.value = localNotifications.value.filter((n) => n.id !== item.id);
        }

        // Add to read list
        if (!localReadNotifications.value.find((n) => n.id === item.id)) {
            localReadNotifications.value.unshift(item);
        }
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
}

function handleLogout() {
    shell.resetInteractionState();
    runLogoutTransition(userName.value, () => {
        // Navigasi browser biasa, BUKAN kunjungan Inertia. /logout selalu
        // berakhir dengan muat-ulang penuh (Inertia::location), jadi tidak ada
        // yang hilang — dan jalur ini tetap bekerja ketika justru SPA-nya yang
        // bermasalah (sesi kadung habis, 419, 5xx, jaringan putus). Kunjungan
        // Inertia yang tak pernah selesai adalah penyebab utama orang
        // tersangkut di balik lapisan logout.
        window.location.assign('/logout');
    });
}

function toggleBreadcrumb() {
    if (shell.state.showBreadcrumb) {
        shell.state.showBreadcrumb = false;
    } else {
        shell.resetInteractionState();
        shell.state.showBreadcrumb = true;
    }
}

function goToActiveModule() {
    // if (activeModuleCode.value === 'HOME') {
    //     router.visit(homeUrl.value, { replace: true, preserveState: false });
    // } else {
    toggleBreadcrumb();
    // }
}

function handleOutsideClick(event) {
    if (shell.state.sidebarTourLocked || isTourActive()) {
        return;
    }

    if (!topbarRoot.value) {
        return;
    }

    if (!topbarRoot.value.contains(event.target)) {
        shell.resetInteractionState();
    }
}

// Interval polling badge notifikasi. Dinaikkan 30s -> 60s -> 120s: badge tak
// perlu real-time, & endpoint ini ditembak SEMUA user di SEMUA halaman —
// mengurangi beban koneksi konstan (jumlah_user_online × poll/menit) separuh.
const NOTIFICATION_POLL_INTERVAL = 120000;
// Batas atas backoff saat poll gagal beruntun (mis. DB jenuh): 5 menit.
const NOTIFICATION_POLL_MAX_INTERVAL = 300000;
// Delay berjalan; di-reset ke INTERVAL saat sukses, digandakan saat gagal.
let notificationPollDelay = NOTIFICATION_POLL_INTERVAL;
let notificationPollActive = false;

// Penjadwalan pakai setTimeout (bukan setInterval tetap) supaya delay bisa
// mengikuti backoff eksponensial per hasil poll terakhir.
function scheduleNextNotificationPoll() {
    if (!notificationPollActive) {
        return;
    }
    if (notificationPollTimer) {
        window.clearTimeout(notificationPollTimer);
    }
    notificationPollTimer = window.setTimeout(runNotificationPoll, notificationPollDelay);
}

async function runNotificationPoll() {
    await pollUnreadNotifications();
    scheduleNextNotificationPoll();
}

function startNotificationPolling() {
    if (notificationPollActive) {
        return;
    }
    notificationPollActive = true;
    scheduleNextNotificationPoll();
}

function stopNotificationPolling() {
    notificationPollActive = false;
    if (notificationPollTimer) {
        window.clearTimeout(notificationPollTimer);
        notificationPollTimer = null;
    }
}

// Hentikan polling saat tab tidak terlihat (background/minimized) supaya tab HRIS
// yang dibiarkan terbuka seharian tidak terus menembak DB. Saat tab kembali aktif,
// poll sekali agar badge langsung fresh lalu lanjutkan interval.
function handleVisibilityChange() {
    if (document.hidden) {
        stopNotificationPolling();
    } else {
        pollUnreadNotifications();
        startNotificationPolling();
    }
}

onMounted(() => {
    timer = window.setInterval(() => {
        currentTime.value = new Date();
    }, 1000);
    pollUnreadNotifications();
    startNotificationPolling();

    activity.init();

    document.addEventListener('mousedown', handleOutsideClick);
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
    if (timer) {
        window.clearInterval(timer);
    }
    stopNotificationPolling();

    document.removeEventListener('mousedown', handleOutsideClick);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});

watch(
    () => props.layout?.notifications,
    (next) => {
        localNotifications.value = Array.isArray(next) ? next : [];
    },
);

watch(
    () => props.layout?.unreadNotificationsCount,
    (next) => {
        localUnreadNotificationsCount.value = Number(next || 0);
    },
);
</script>

<style scoped>
.bi {
    display: inline-table;
}

.shell-topbar,
.shell-topbar * {
    box-sizing: border-box;
}

.shell-topbar {
    position: sticky;
    top: 0;
    z-index: 1020;
    height: 4rem;
    background: rgba(255, 255, 255, 0.52);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(226, 232, 240, 0.62);
    color: #0f172a;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    transition: all 500ms cubic-bezier(0.2, 0.8, 0.2, 1);
}

.shell-topbar__inner {
    height: 100%;
    padding: 0 2.5rem 0 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
}

.shell-btn {
    border: 0;
    outline: 0;
    background: transparent;
    font: inherit;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

.shell-topbar__left,
.shell-topbar__right,
.shell-action-group {
    display: flex;
    align-items: center;
}

.shell-topbar__left {
    min-width: 0;
    gap: 1rem;
}

.shell-topbar__right {
    margin-left: auto;
    gap: 1.5rem;
    flex-shrink: 0;
}

.shell-action-group {
    gap: 0.25rem;
}

.shell-topbar-action {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.9rem;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 260ms ease;
}

.shell-topbar-action:hover {
    background: #f1f5f9;
    color: #4f46e5;
}

.shell-greeting {
    min-width: 0;
    display: flex;
    flex-direction: column;
    text-align: left;
}

.shell-greeting__eyebrow {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.32rem;
    color: #475569;
    font-size: 0.625rem;
    line-height: 1;
    font-weight: 950;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}

.shell-greeting__eyebrow i {
    color: #f59e0b;
    font-size: 0.88rem;
    letter-spacing: 0;
}

.shell-greeting__title {
    color: #0f172a;
    font-size: 1rem;
    line-height: 1;
    font-weight: 950;
    letter-spacing: -0.025em;
}

.shell-clock {
    flex-direction: column;
    align-items: flex-end;
}

.shell-clock__time {
    display: flex;
    align-items: center;
    gap: 0.42rem;
    color: #0f172a;
    font-size: 0.875rem;
    line-height: 1;
    font-weight: 950;
}

.shell-clock__time i {
    color: #4f46e5;
    font-size: 0.82rem;
}

.shell-clock__date {
    display: flex;
    align-items: center;
    gap: 0.34rem;
    margin-top: 0.38rem;
    color: #475569;
    font-size: 0.68rem;
    line-height: 1;
    font-weight: 800;
}

.shell-clock__date i {
    font-size: 0.68rem;
}

.shell-topbar-divider {
    width: 1px;
    height: 2.5rem;
    background: rgba(226, 232, 240, 0.78);
}

.shell-topbar-icon {
    position: relative;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.8rem;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.16rem;
    line-height: 1;
    transition:
        background 240ms ease,
        color 240ms ease,
        box-shadow 240ms ease,
        transform 240ms ease;
}

.shell-topbar-icon .bi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.shell-topbar-icon:hover {
    background: #f1f5f9;
    color: #4f46e5;
}

.shell-topbar-icon.is-active {
    color: #ffffff;
    box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
}

.shell-topbar-icon.is-blue {
    background: #4f46e5;
}

.shell-topbar-icon.is-red {
    background: #ef4444;
}

.shell-topbar-icon.is-dark {
    background: #0f172a;
}

.shell-dot {
    position: absolute;
    top: 0.72rem;
    right: 0.72rem;
    width: 0.42rem;
    height: 0.42rem;
    border-radius: 999px;
    border: 2px solid #ffffff;
    animation: pulse-dot 1.25s ease-in-out infinite;
}

.shell-dot--blue {
    background: #4f46e5;
}

.shell-badge {
    position: absolute;
    top: 0.35rem;
    right: 0.32rem;
    min-width: 1rem;
    height: 1rem;
    padding: 0 0.2rem;
    border-radius: 999px;
    border: 2px solid #ffffff;
    background: #b91c1c;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.56rem;
    line-height: 1;
    font-weight: 950;
}

.shell-topbar-popover {
    position: absolute;
    top: calc(100% + 0.75rem);
    right: 0;
    z-index: 1;
    width: min(22rem, calc(100vw - 1.5rem));
    border-radius: 1.25rem;
    border: 1px solid rgba(226, 232, 240, 0.95);
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(18px);
    box-shadow: 0 28px 60px rgba(15, 23, 42, 0.18);
    overflow: hidden;
}

.shell-topbar-popover--large {
    width: min(28rem, calc(100vw - 1.5rem));
}

.shell-topbar-popover--sm {
    width: min(21rem, calc(100vw - 1.5rem));
}

.shell-popover-head {
    min-height: 3.25rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.45) 0%, rgba(255, 255, 255, 0) 100%);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.shell-popover-head--tabs {
    padding: 0;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
}

.shell-popover-tabs {
    display: flex;
    gap: 1rem;
    padding: 0 1rem;
}

.shell-popover-tab {
    position: relative;
    padding: 1rem 0.5rem;
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    transition: color 200ms ease;
}

.shell-popover-tab:hover {
    color: #0f172a;
}

.shell-popover-tab.is-active {
    color: #4f46e5;
}

.shell-popover-tab.is-active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 2px;
    background: #4f46e5;
    border-radius: 2px 2px 0 0;
}

.shell-tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 0.3rem;
    padding: 0.15rem 0.4rem;
    background: #ef4444;
    color: white;
    font-size: 0.55rem;
    border-radius: 999px;
}

.shell-popover-actions {
    padding-right: 1rem;
}

.shell-popover-action {
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    transition: all 200ms ease;
}

.shell-popover-action:hover {
    background: #f1f5f9;
    color: #4f46e5;
}

.shell-popover-title {
    color: #0f172a;
    font-size: 0.825rem;
    line-height: 1.25;
    font-weight: 850;
    text-transform: none;
    letter-spacing: -0.015em;
    display: flex;
    align-items: center;
    gap: 0.55rem;
}
.shell-popover-title-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.6rem;
    height: 1.6rem;
    border-radius: 0.55rem;
    background: rgba(79, 70, 229, 0.08);
    color: #4f46e5;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.shell-popover-body {
    max-height: 24rem;
    overflow-y: auto;
    overflow-x: hidden;
}

.shell-message-row,
.shell-notification-row {
    display: flex;
    gap: 0.75rem;
    padding: 0.75rem;
    border-bottom: 1px solid #f8fafc;
    transition: all 250ms ease;
    min-width: 0;
    position: relative;
}

.shell-notification-row.is-clickable {
    cursor: pointer;
}

.shell-notification-row.is-clickable:hover {
    background: #f8fafc;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
    z-index: 1;
}

.shell-notification-row.is-read {
    opacity: 0.7;
}

.shell-notification-row.is-read:hover {
    opacity: 1;
}

.shell-notification-actions {
    position: absolute;
    top: 50%;
    right: 1rem;
    transform: translateY(-50%);
    opacity: 0;
    transition: opacity 200ms ease;
}

.shell-notification-row:hover .shell-notification-actions {
    opacity: 1;
}

.shell-notification-check {
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 200ms ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.shell-notification-check:hover {
    background: #4f46e5;
    color: #ffffff;
    box-shadow: 0 4px 8px rgba(79, 70, 229, 0.25);
}

.shell-message-row:hover,
.shell-notification-row:hover {
    background: #f8fafc;
}

.shell-message-avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    background: #dbeafe;
    color: #1d4ed8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.62rem;
    font-weight: 950;
}

.shell-message-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    min-width: 0;
    width: 100%;
}

.shell-message-sender,
.shell-notification-title {
    color: #0f172a;
    font-size: 0.7rem;
    line-height: 1;
    font-weight: 850;
    min-width: 0;
}

.shell-message-time {
    flex-shrink: 0;
    color: #94a3b8;
    font-size: 0.56rem;
    line-height: 1;
    font-weight: 700;
    white-space: nowrap;
}

.shell-message-text {
    margin-top: 0.3rem;
    color: #64748b;
    font-size: 0.64rem;
    line-height: 1.25;
    font-weight: 550;
    min-width: 0;
}

.shell-notification-content {
    min-width: 0;
    overflow: hidden;
}

.shell-message-text--wrap {
    display: -webkit-box;
    overflow: hidden;
    white-space: normal;
    overflow-wrap: anywhere;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.shell-notification-avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 0.75rem;
    background: #fee2e2;
    color: #dc2626;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.shell-notification-avatar.tone-security {
    background: #fef3c7;
    color: #d97706;
}

.shell-notification-avatar.tone-success {
    background: #dcfce7;
    color: #16a34a;
}

.shell-notification-avatar.tone-info {
    background: #dbeafe;
    color: #4f46e5;
}

.shell-empty-state {
    padding: 3.5rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
}

.shell-empty-state-icon {
    font-size: 2.25rem;
    color: #cbd5e1;
    margin-bottom: 0.75rem;
    opacity: 0.7;
}

.shell-empty-state-text {
    font-size: 0.75rem;
    font-weight: 700;
}

.shell-popover-footer-wrap {
    width: 100%;
    display: flex;
    border-top: 1px solid #f1f5f9;
}

.shell-popover-footer {
    flex: 1;
    min-height: 2.75rem;
    color: #94a3b8;
    background: #ffffff;
    font-size: 0.62rem;
    font-weight: 950;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    transition: all 200ms ease;
}

.shell-popover-footer:not(:last-child) {
    border-right: 1px solid #f1f5f9;
}

.shell-popover-footer:hover {
    background: #f8fafc;
    color: #4f46e5;
}

.shell-popover-footer.is-red:hover {
    color: #ef4444;
}

.shell-popover-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    color: #94a3b8;
}

/* Titik penanda: halaman ini punya panduan. */
/* Tombol bantuan berdenyut selama petunjuk panduan tampil. */
.shell-topbar-icon.is-berdenyut {
    box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.45);
    animation: shell-denyut-bantuan 1.6s ease-out infinite;
}

@keyframes shell-denyut-bantuan {
    0% {
        box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.45);
    }
    100% {
        box-shadow: 0 0 0 10px rgba(2, 132, 199, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .shell-topbar-icon.is-berdenyut {
        animation: none;
    }
}

.shell-badge-dot {
    position: absolute;
    top: 0.45rem;
    right: 0.45rem;
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 999px;
    border: 2px solid #ffffff;
    background: #0284c7;
}

.shell-panduan-list {
    margin: 0.5rem 0.75rem 0.25rem;
    padding: 0.75rem;
    border-radius: 14px;
    background: rgba(248, 250, 252, 0.75);
    border: 1px solid rgba(226, 232, 240, 0.85);
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.shell-panduan-judul {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 0.2rem 0.35rem;
    font-size: 0.68rem;
    font-weight: 750;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
}

.shell-panduan-counter {
    display: inline-flex;
    align-items: center;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: none;
    color: #4f46e5;
    background: rgba(79, 70, 229, 0.08);
    border: 1px solid rgba(79, 70, 229, 0.15);
}

.shell-panduan-row {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: 12px;
    padding: 0.65rem 0.75rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    transition: all 200ms cubic-bezier(0.16, 1, 0.3, 1);
}

.shell-panduan-row:hover {
    background: #ffffff;
    border-color: rgba(99, 102, 241, 0.35);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px -2px rgba(79, 70, 229, 0.12);
}

.shell-panduan-icon {
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    line-height: 1;
    background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
    color: #4f46e5;
    border: 1px solid rgba(79, 70, 229, 0.15);
}

.shell-panduan-icon.is-baru {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    color: #059669;
    border-color: rgba(16, 185, 129, 0.25);
}

.shell-panduan-row:hover .shell-panduan-icon {
    background: #4f46e5;
    color: #ffffff;
    box-shadow: 0 3px 8px rgba(79, 70, 229, 0.3);
}

.shell-panduan-row:hover .shell-panduan-icon.is-baru {
    background: #059669;
    color: #ffffff;
    box-shadow: 0 3px 8px rgba(16, 185, 129, 0.3);
}

.shell-panduan-baru {
    display: inline-flex;
    align-items: center;
    margin-left: 0.35rem;
    padding: 0.08rem 0.45rem;
    border-radius: 999px;
    font-size: 0.62rem;
    font-weight: 700;
    vertical-align: middle;
    color: var(--hcis-success-700, #15803d);
    background: var(--hcis-success-50, #f0fdf4);
    border: 1px solid var(--hcis-success-200, #bbf7d0);
    line-height: 1.2;
}

.shell-panduan-counter.is-filtered {
    background: rgba(79, 70, 229, 0.15);
    color: #4338ca;
    border-color: rgba(79, 70, 229, 0.3);
}

.shell-panduan-items-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.shell-panduan-items-wrap.is-scrollable {
    max-height: 280px;
    overflow-y: auto;
    padding-right: 0.15rem;
}

.shell-panduan-footer-actions {
    display: flex;
    justify-content: center;
    margin-top: 0.35rem;
    padding-top: 0.45rem;
    border-top: 1px dashed rgba(203, 213, 225, 0.8);
}

.shell-panduan-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #e0e7ff;
    background: #ffffff;
    color: #4f46e5;
    font-size: 0.72rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 180ms cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.shell-panduan-toggle-btn:hover {
    background: #eef2ff;
    border-color: #c7d2fe;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.15);
}

.shell-panduan-toggle-btn:focus-visible {
    outline: 2px solid #4f46e5;
    outline-offset: 2px;
}

.shell-help-list {
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.shell-help-row {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem;
    border-radius: 1rem;
    text-align: left;
    transition: all 250ms cubic-bezier(0.4, 0, 0.2, 1);
    background: transparent;
    position: relative;
    overflow: hidden;
}

.shell-help-row::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.05), rgba(79, 70, 229, 0));
    opacity: 0;
    transition: opacity 250ms ease;
}

.shell-help-row:hover {
    background: #f8fafc;
    transform: translateY(-1px);
}

.shell-help-row:hover::before {
    opacity: 1;
}

.shell-help-icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.75rem;
    background: #f1f5f9;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.1rem;
    transition: all 300ms cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    z-index: 1;
}

.shell-help-row:hover .shell-help-icon {
    background: #4f46e5;
    color: #ffffff;
    transform: scale(1.05) rotate(8deg);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}

.shell-help-copy {
    min-width: 0;
    display: flex;
    flex-direction: column;
    z-index: 1;
}

.shell-help-title {
    color: #0f172a;
    font-size: 0.75rem;
    line-height: 1.25;
    font-weight: 800;
    transition: color 250ms ease;
}

.shell-help-row:hover .shell-help-title {
    color: #4f46e5;
}

.shell-help-desc {
    margin-top: 0.25rem;
    color: #64748b;
    font-size: 0.65rem;
    line-height: 1.25;
    font-weight: 500;
}

.shell-help-chevron {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    flex-shrink: 0;
    color: #cbd5e1;
    font-size: 0.8rem;
    line-height: 1;
    transition: all 250ms cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateX(-2px);
    opacity: 0.6;
    z-index: 1;
}

.shell-help-row:hover .shell-help-chevron {
    transform: translateX(0);
    opacity: 1;
    color: #4f46e5;
}

/* ── Context-aware Tour CTA — Minimalist Glassmorphism ────────────────── */
.shell-tour-card {
    position: relative;
    margin: 0.75rem;
    padding: 0.875rem;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.55);
    backdrop-filter: blur(14px) saturate(180%);
    -webkit-backdrop-filter: blur(14px) saturate(180%);
    border: 1px solid rgba(124, 58, 237, 0.18);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.6) inset,
        0 6px 20px -10px rgba(79, 70, 229, 0.18);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.shell-tour-card.is-empty {
    flex-direction: row;
    align-items: center;
    gap: 10px;
    background: rgba(248, 250, 252, 0.65);
    border: 1px dashed rgba(148, 163, 184, 0.45);
    box-shadow: none;
}
.shell-tour-card.is-empty .shell-tour-card__copy h4 {
    color: #475569;
}
.shell-tour-card.is-empty .shell-tour-card__copy p {
    color: #94a3b8;
}

.shell-tour-card__glow {
    display: none;
} /* removed — keep it clean */

.shell-tour-card__head {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.shell-tour-card__icon {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: rgba(124, 58, 237, 0.08);
    color: #7c3aed;
    border: 1px solid rgba(124, 58, 237, 0.18);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.shell-tour-card__icon--muted {
    background: rgba(241, 245, 249, 0.7);
    color: #94a3b8;
    border-color: rgba(203, 213, 225, 0.6);
}

.shell-tour-card__copy {
    min-width: 0;
    flex: 1;
}

.shell-tour-card__eyebrow {
    display: inline-block;
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    color: #7c3aed;
    background: rgba(124, 58, 237, 0.08);
    padding: 2px 8px;
    border-radius: 999px;
    margin-bottom: 4px;
}

.shell-tour-card__copy h4 {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
    letter-spacing: -0.005em;
}
.shell-tour-card__copy p {
    margin: 2px 0 0;
    font-size: 0.7125rem;
    color: #64748b;
    line-height: 1.45;
}

.shell-tour-card__progress {
    position: relative;
}
.shell-tour-card__progress.is-hidden {
    display: none;
}

.shell-tour-card__bar {
    width: 100%;
    height: 4px;
    background: rgba(148, 163, 184, 0.2);
    border-radius: 999px;
    overflow: hidden;
}
.shell-tour-card__fill {
    height: 100%;
    border-radius: 999px;
    background: #7c3aed;
    transition: width 0.55s cubic-bezier(0.16, 1, 0.3, 1);
}
.shell-tour-card__progress-text {
    display: block;
    margin-top: 5px;
    font-size: 0.6875rem;
    color: #94a3b8;
    font-weight: 500;
}

.shell-tour-card__actions {
    position: relative;
    display: flex;
    gap: 6px;
}

.shell-tour-card__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    line-height: 1.1;
}
.shell-tour-card__btn--primary {
    flex: 1;
    background: #4f46e5;
    color: #fff;
    box-shadow: 0 4px 12px -4px rgba(79, 70, 229, 0.45);
}
.shell-tour-card__btn--primary:hover {
    background: #4338ca;
    transform: translateY(-1px);
    box-shadow: 0 8px 18px -6px rgba(79, 70, 229, 0.55);
}
.shell-tour-card__btn--primary i {
    font-size: 0.95rem;
}

.shell-tour-card__btn--ghost {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.55);
    color: #4f46e5;
    border-color: rgba(124, 58, 237, 0.22);
    line-height: 1;
}
.shell-tour-card__btn--ghost:hover {
    background: rgba(124, 58, 237, 0.08);
}

.shell-help-version {
    padding: 0.85rem;
    background: #f8fafc;
    color: #94a3b8;
    text-align: center;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    border-top: 1px solid #f1f5f9;
}

/* ── Pusat Bantuan Panel Optimization ─────────────────── */
.shell-help-popover {
    display: flex;
    flex-direction: column;
}

.shell-help-head-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 700;
    color: #4f46e5;
    background: rgba(79, 70, 229, 0.08);
    border: 1px solid rgba(79, 70, 229, 0.15);
}

.shell-help-filter-bar {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    background: rgba(248, 250, 252, 0.65);
}

.shell-help-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    border: 1px solid transparent;
    background: transparent;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 180ms ease;
    line-height: 1.2;
}

.shell-help-filter-pill:hover {
    color: #0f172a;
    background: rgba(255, 255, 255, 0.85);
}

.shell-help-filter-pill.is-active {
    background: #ffffff;
    color: #4f46e5;
    border-color: rgba(99, 102, 241, 0.25);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}

.shell-help-pill-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.15rem;
    height: 1.15rem;
    padding: 0 0.25rem;
    border-radius: 999px;
    font-size: 0.6rem;
    font-weight: 750;
    background: rgba(79, 70, 229, 0.1);
    color: #4f46e5;
}

.shell-help-filter-pill.is-active .shell-help-pill-count {
    background: #4f46e5;
    color: #ffffff;
}

.shell-help-body {
    max-height: min(26rem, calc(100vh - 12rem));
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
}

.shell-help-section {
    display: flex;
    flex-direction: column;
}

.shell-help-section + .shell-help-section {
    border-top: 1px solid rgba(241, 245, 249, 0.9);
}

.shell-help-contacts-list {
    padding-top: 0.5rem;
}

.shell-help-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.25rem 0.2rem 0.4rem;
}

.shell-help-section-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.68rem;
    font-weight: 750;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
}

.shell-help-section-count {
    font-size: 0.62rem;
    font-weight: 700;
    color: #94a3b8;
}

.shell-help-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    margin-bottom: 0.45rem;
}

.shell-help-search-wrap--top {
    margin: 0.45rem 0.75rem 0.25rem;
}

.shell-help-search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 0.75rem;
    pointer-events: none;
    line-height: 1;
}

.shell-help-search-input {
    width: 100%;
    height: 2rem;
    padding: 0 1.85rem 0 1.95rem;
    border-radius: 9px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #0f172a;
    font-size: 0.73rem;
    transition: all 180ms ease;
}

.shell-help-search-input:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

.shell-help-search-clear {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    width: 1.5rem;
    height: 1.5rem;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: #94a3b8;
    font-size: 0.8rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    line-height: 1;
}

.shell-help-search-clear:hover {
    color: #475569;
}

.shell-kontak-bagian-daftar {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    padding: 0.25rem 0;
}

/* Modal Search Bar */
.shell-kontak-modal-search {
    position: relative;
    display: flex;
    align-items: center;
    margin-bottom: 0.35rem;
}

.shell-kontak-modal-search-icon {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 0.825rem;
    pointer-events: none;
    line-height: 1;
}

.shell-kontak-modal-search-input {
    width: 100%;
    height: 2.25rem;
    padding: 0 2rem 0 2.25rem;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #0f172a;
    font-size: 0.78rem;
    transition: all 180ms ease;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.shell-kontak-modal-search-input:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

.shell-kontak-modal-search-clear {
    position: absolute;
    right: 0.65rem;
    top: 50%;
    transform: translateY(-50%);
    width: 1.5rem;
    height: 1.5rem;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: #94a3b8;
    font-size: 0.85rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    line-height: 1;
}

.shell-kontak-modal-search-clear:hover {
    color: #475569;
}

/* Toolbar Meta Bar */
.shell-kontak-toolbar-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.15rem 0.2rem 0.35rem;
    font-size: 0.72rem;
}

.shell-kontak-toolbar-count {
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-size: 0.68rem;
}

.shell-kontak-toggle-all-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #4f46e5;
    font-size: 0.7rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 160ms ease;
}

.shell-kontak-toggle-all-btn:hover {
    background: #eef2ff;
    border-color: #c7d2fe;
}

/* Section Accordion Header */
.shell-kontak-bagian__header,
.shell-kontak-bagian__judul {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.65rem;
    padding: 0.45rem 0.65rem;
    border-radius: 10px;
    border-bottom: 1.5px solid #f1f5f9;
    transition: all 180ms ease;
    user-select: none;
}

.shell-kontak-bagian__header.is-clickable {
    cursor: pointer;
}

.shell-kontak-bagian__header.is-clickable:hover {
    background-color: #f8fafc;
    border-bottom-color: #e2e8f0;
}

.shell-kontak-bagian__header.is-clickable:focus-visible {
    outline: 2px solid #4f46e5;
    outline-offset: -1px;
}

.shell-kontak-bagian__tag {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #475569;
}

.shell-kontak-bagian__tag i {
    color: #4f46e5;
    font-size: 0.75rem;
}

.shell-kontak-bagian__badge-relevan {
    display: inline-flex;
    align-items: center;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: none;
    color: #0369a1;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    padding: 0.05rem 0.45rem;
    border-radius: 999px;
    line-height: 1.2;
    margin-left: 0.25rem;
}

.shell-kontak-bagian__jumlah {
    font-size: 0.65rem;
    font-weight: 700;
    color: #4f46e5;
    background: rgba(79, 70, 229, 0.08);
    border: 1px solid rgba(79, 70, 229, 0.15);
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    line-height: 1.2;
}

.shell-kontak-bagian__chevron {
    font-size: 0.75rem;
    color: #94a3b8;
    transition: transform 220ms cubic-bezier(0.16, 1, 0.3, 1);
}

.shell-kontak-bagian__chevron.is-rotated {
    transform: rotate(180deg);
    color: #4f46e5;
}

.shell-kontak-bagian.is-collapsed .shell-kontak-bagian__header {
    margin-bottom: 0;
}

/* Empty search state */
.shell-kontak-empty-search {
    padding: 2rem 1rem;
    text-align: center;
    color: #94a3b8;
    font-size: 0.8rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.shell-kontak-empty-search i {
    font-size: 1.5rem;
    color: #cbd5e1;
}

/* Official Notice Callout */
.shell-kontak-notice-card {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 0.9rem;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(240, 249, 255, 0.8) 0%, rgba(238, 242, 255, 0.8) 100%);
    border: 1px solid rgba(186, 230, 253, 0.8);
    margin-top: 0.25rem;
}

.shell-kontak-notice-icon {
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 8px;
    background: #ffffff;
    color: #0284c7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}

.shell-kontak-notice-body {
    flex: 1;
    min-width: 0;
}

.shell-kontak-notice-title {
    display: block;
    font-size: 0.75rem;
    font-weight: 750;
    color: #0369a1;
    line-height: 1.2;
    margin-bottom: 0.15rem;
}

.shell-kontak-notice-desc {
    margin: 0;
    font-size: 0.7rem;
    color: #475569;
    line-height: 1.35;
}

/* Footer start verified badge */
.shell-kontak-footer-verified {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 650;
    color: #0284c7;
}

.shell-kontak-footer-verified i {
    font-size: 0.85rem;
    color: #0284c7;
}

.shell-kontak-relevan {
    display: inline-block;
    margin-left: 0.35rem;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    font-size: 0.62rem;
    font-weight: 700;
    vertical-align: middle;
    color: var(--hcis-info-700, #0369a1);
    background: var(--hcis-info-50, #f0f9ff);
    border: 1px solid var(--hcis-info-200, #bae6fd);
}

.shell-contact-row {
    padding: 0.55rem 0.75rem;
    border-radius: 12px;
    gap: 0.75rem;
}

.shell-contact-icon {
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 9px;
    font-size: 0.95rem;
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    line-height: 1;
}

.shell-contact-row:hover .shell-contact-icon {
    background: #4f46e5;
    color: #ffffff;
    border-color: #4f46e5;
    box-shadow: 0 3px 8px rgba(79, 70, 229, 0.25);
}

.shell-contact-row .shell-help-chevron {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 8px;
    flex-shrink: 0;
    color: #94a3b8;
    font-size: 0.85rem;
    opacity: 0.7;
    transform: none;
    transition: all 180ms ease;
    line-height: 1;
}

.shell-contact-row:hover .shell-help-chevron {
    opacity: 1;
    color: #4f46e5;
    background: rgba(79, 70, 229, 0.08);
}

.shell-topbar-popover i.bi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    vertical-align: 0;
}

.shell-topbar-popover i.bi::before {
    display: inline-block;
    line-height: 1;
    vertical-align: 0 !important;
}

.shell-help-empty-search {
    padding: 1.5rem 1rem;
    text-align: center;
    color: #94a3b8;
    font-size: 0.75rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
}

.shell-help-empty-search i {
    font-size: 1.25rem;
    color: #cbd5e1;
}

.shell-tour-card.is-empty {
    margin: 0.6rem 0.75rem;
    padding: 0.65rem 0.8rem;
    border-radius: 12px;
}

/* ── Breadcrumb User Card ──────────────────────────────────────────────── */
.shell-user-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1rem 1rem 0.85rem;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.04), rgba(99, 102, 241, 0.02));
    border-bottom: 1px solid #f1f5f9;
}

.shell-user-card__avatar {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.9rem;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 950;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.28);
    letter-spacing: 0.02em;
}

.shell-user-card__info {
    min-width: 0;
    flex: 1;
}

.shell-user-card__name {
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 900;
    line-height: 1.2;
    letter-spacing: -0.01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.shell-user-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.4rem;
}

.shell-user-card__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    padding: 0.22rem 0.55rem;
    border-radius: 999px;
    font-size: 0.58rem;
    font-weight: 800;
    line-height: 1;
    white-space: nowrap;
    max-width: 11rem;
    overflow: hidden;
    text-overflow: ellipsis;
}

.shell-user-card__badge i {
    font-size: 0.62rem;
    flex-shrink: 0;
}

.shell-user-card__badge--nik {
    background: rgba(79, 70, 229, 0.08);
    color: #4f46e5;
    border: 1px solid rgba(79, 70, 229, 0.14);
}

.shell-user-card__badge--dept {
    background: rgba(16, 185, 129, 0.08);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.14);
    /* max-width: 9rem; */
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── User Card Logout Icon Button ──────────────────────────────────────── */
.shell-user-card__logout {
    width: 2rem;
    height: 2rem;
    border-radius: 0.6rem;
    background: rgba(239, 68, 68, 0.08);
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    line-height: 1;
    flex-shrink: 0;
    transition: all 200ms ease;
}

.shell-user-card__logout:hover {
    background: #ef4444;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.shell-user-card__logout .bi {
    display: flex;
    align-items: center;
    justify-content: center;
}

.shell-module-pill {
    min-height: 3rem;
    padding: 0.5rem 0.95rem 0.5rem 0.75rem;
    border-radius: 1rem;
    border: 1px solid #dcfce7;
    background: rgba(240, 253, 244, 0.72);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 240ms ease;
}

.shell-module-pill:hover {
    background: rgba(220, 252, 231, 0.72);
}

.shell-module-pill__left {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding-right: 0.65rem;
    border-right: 1px solid rgba(187, 247, 208, 0.9);
}

.shell-module-pill__icon {
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 0.45rem;
    background: #ffffff;
    color: #4f46e5;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05);
}

.shell-module-pill__code {
    color: #15803d;
    font-size: 0.68rem;
    line-height: 1;
    font-weight: 950;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    max-width: 8rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.shell-module-pill__divider {
    display: none;
}

.shell-module-pill__user-wrap {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1;
}

.shell-module-pill__user {
    max-width: 8rem;
    color: #0f172a;
    font-size: 0.62rem;
    line-height: 1;
    font-weight: 950;
    text-transform: uppercase;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.shell-module-pill__nik {
    margin-top: 0.26rem;
    color: #64748b;
    font-size: 0.5rem;
    line-height: 1;
    font-weight: 800;
}

.panel-pop-enter-active,
.panel-pop-leave-active {
    transition:
        opacity 200ms ease,
        transform 200ms ease;
}

.panel-pop-enter-from,
.panel-pop-leave-to {
    opacity: 0;
    transform: translateY(-0.5rem) scale(0.98);
}

.shell-scrollbar::-webkit-scrollbar {
    width: 5px;
}

.shell-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.shell-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 999px;
}

.shell-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

@keyframes pulse-dot {
    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.45;
        transform: scale(1.2);
    }
}

.shell-topbar button:focus-visible {
    outline: 2px solid rgba(37, 99, 235, 0.28);
    outline-offset: 2px;
}

@media (max-width: 1199.98px) {
    .shell-topbar__inner {
        padding: 0 1.25rem 0 1rem;
    }

    .shell-topbar__right {
        gap: 0.75rem;
    }
}

@media (max-width: 767.98px) {
    .shell-topbar {
        height: auto;
        min-height: 3.5rem;
    }

    .shell-greeting {
        display: none;
    }

    .shell-greeting__eyebrow {
        gap: 0;
        margin: 0;
    }

    .shell-topbar__left {
        gap: 0.3rem;
    }

    .shell-topbar__inner {
        padding: 0.55rem 1rem;
        gap: 0.8rem;
    }

    .shell-greeting__title {
        font-size: 0.92rem;
    }

    .shell-topbar__right {
        gap: 0.55rem;
    }

    .shell-module-pill {
        padding: 0rem 0.35rem;
        min-height: 2rem;
    }

    .shell-module-pill__user-wrap {
        display: none;
    }

    .shell-module-pill__left {
        padding-right: 0;
        border-right: 0;
    }

    .shell-topbar-popover {
        position: fixed;
        top: 4.75rem;
        right: 1rem;
        width: min(24rem, calc(100vw - 2rem));
    }

    .shell-panduan-list {
        margin: 0.4rem 0.5rem 0.2rem;
        padding: 0.65rem;
    }

    .shell-panduan-row {
        padding: 0.6rem 0.65rem;
        min-height: 42px;
    }

    .shell-help-body {
        max-height: min(22rem, calc(100vh - 14rem));
    }

    .shell-help-filter-bar {
        padding: 0.4rem 0.5rem;
        gap: 0.25rem;
    }

    .shell-help-filter-pill {
        padding: 0.25rem 0.5rem;
        font-size: 0.68rem;
    }
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.spin-animation {
    animation: spin 1.2s linear infinite;
    display: inline-block;
    transform-origin: 50% 50%;
    line-height: 1;
    width: 1em;
    height: 1em;
}

.shell-help-row.is-loading .shell-help-icon {
    background: #4f46e5 !important;
    color: #ffffff !important;
    transform: scale(1.05) rotate(0deg) !important;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25) !important;
}

/* ===== Activity Center ===== */
.shell-topbar-icon.is-active.is-indigo {
    color: #4f46e5;
}
.shell-badge.is-green {
    background: #16a34a !important;
}
.ac-icon-svg {
    width: 1.25rem;
    height: 1.25rem;
    display: inline-block;
    vertical-align: middle;
    overflow: visible !important;
}
.ac-icon-svg.is-loading .ac-svg-arrow {
    animation: ac-svg-download-bounce 1.5s infinite ease-in-out;
}
@keyframes ac-svg-download-bounce {
    0% {
        transform: translateY(-5px);
        opacity: 0;
    }
    30% {
        transform: translateY(0);
        opacity: 1;
    }
    70% {
        transform: translateY(4px);
        opacity: 0;
    }
    100% {
        transform: translateY(-5px);
        opacity: 0;
    }
}
.shell-popover-title {
    font-weight: 600;
    font-size: 0.95rem;
    color: #1e293b;
    display: flex;
    align-items: center;
}
.ac-head-badge {
    font-size: 0.65rem;
    font-weight: 700;
    color: #4f46e5;
    background: rgba(79, 70, 229, 0.08);
    border: 1px solid rgba(79, 70, 229, 0.14);
    padding: 2px 8px;
    border-radius: 99px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.ac-clear-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 6px;
    padding: 0.25rem 0.6rem;
    cursor: pointer;
    transition: all 180ms ease;
    line-height: 1.2;
}
.ac-clear-btn:hover:not(:disabled) {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}
.ac-clear-btn:active:not(:disabled) {
    transform: scale(0.96);
}
.ac-clear-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.ac-clear-btn i {
    font-size: 0.75rem;
    color: inherit;
}
.ac-head-badge::before {
    content: '';
    width: 5px;
    height: 5px;
    background-color: #4f46e5;
    border-radius: 50%;
    display: inline-block;
    animation: pulse-dot-badge 1.5s infinite ease-in-out;
}
@keyframes pulse-dot-badge {
    0% {
        transform: scale(0.85);
        opacity: 0.5;
    }
    50% {
        transform: scale(1.3);
        opacity: 1;
    }
    100% {
        transform: scale(0.85);
        opacity: 0.5;
    }
}
.ac-item {
    position: relative;
    border: 1px solid #eef2f7;
    border-radius: 10px;
    padding: 10px;
    margin: 8px 10px;
    background: #fafbff;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.ac-item.is-ready {
    border-color: #bbf7d0;
    background: linear-gradient(135deg, rgba(240, 253, 244, 0.6) 0%, #fafbff 100%);
    box-shadow: 0 4px 14px rgba(22, 163, 74, 0.04);
}
.ac-item.is-dismissing {
    opacity: 0.85;
}
.ac-item.is-clickable {
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background 0.15s ease;
}
.ac-item.is-clickable:hover {
    border-color: #c7d2fe;
    background: #f5f7ff;
}

/* Overlay loading saat item sedang dihapus */
.ac-item-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #4f46e5;
    transition: all 0.2s ease;
}
.ac-loading-spinner {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(79, 70, 229, 0.15);
    border-top-color: #4f46e5;
    border-radius: 50%;
    animation: ac-spin-kf 0.65s linear infinite;
    display: inline-block;
    flex-shrink: 0;
}
.ac-loading-spinner.sm {
    width: 12px;
    height: 12px;
    border-width: 1.5px;
    border-color: rgba(148, 163, 184, 0.2);
    border-top-color: #64748b;
}
.ac-dismiss:disabled {
    cursor: default;
    color: #94a3b8;
}

/* Animasi keluar-masuk list item Pusat Aktivitas */
.ac-list-enter-active,
.ac-list-leave-active {
    transition:
        opacity 260ms ease,
        transform 260ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.ac-list-enter-from {
    opacity: 0;
    transform: translateY(-6px);
}
.ac-list-leave-to {
    opacity: 0;
    transform: translateX(14px);
}
/* Jaga layout saat item keluar agar yang lain bergeser mulus */
.ac-list-leave-active {
    position: absolute;
    left: 10px;
    right: 10px;
}
.ac-item-top {
    display: flex;
    align-items: flex-start;
    gap: 9px;
}
.ac-status {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
    position: relative;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.ac-st-ready {
    background: #dcfce7;
    color: #16a34a;
    box-shadow: 0 0 10px rgba(22, 163, 74, 0.15);
    animation: ac-pop-in 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.ac-st-failed {
    background: #fee2e2;
    color: #dc2626;
}
.ac-st-expired {
    background: #f1f5f9;
    color: #94a3b8;
}
.ac-st-queued,
.ac-st-processing {
    background: rgba(79, 70, 229, 0.08);
    border: 1.5px solid rgba(79, 70, 229, 0.15);
    color: #4f46e5;
    box-shadow: 0 0 12px rgba(79, 70, 229, 0.06);
}
.ac-st-queued::after,
.ac-st-processing::after {
    content: '';
    position: absolute;
    inset: -3px;
    border-radius: 50%;
    border: 2px solid transparent;
    border-top-color: #4f46e5;
    animation: ac-spin-kf 0.9s linear infinite;
}
.ac-download-icon-anim {
    animation: ac-download-slide 1.5s infinite ease-in-out;
    display: inline-block;
}
.ac-item-info {
    flex: 1;
    min-width: 0;
}
.ac-item-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #1e293b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ac-item-sub {
    font-size: 0.68rem;
    color: #94a3b8;
    margin-top: 1px;
}
.ac-dismiss {
    background: transparent;
    border: none;
    color: #cbd5e1;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    transition: all 0.15s ease;
}
.ac-dismiss:hover:not(:disabled) {
    color: #ef4444;
    background: rgba(239, 68, 68, 0.08);
    border-radius: 50%;
}
.ac-progress {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
}
.ac-bar-track {
    flex: 1;
    height: 6px;
    background: #f1f5f9;
    border-radius: 99px;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}
.ac-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #4f46e5, #6366f1, #4f46e5);
    background-size: 200% 100%;
    border-radius: 99px;
    transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    animation: ac-progress-glow 1.8s linear infinite;
}
.ac-bar-fill.is-indeterminate {
    width: 100%;
    background: linear-gradient(90deg, transparent 0%, rgba(79, 70, 229, 0.5) 50%, transparent 100%);
    animation: ac-indeterminate-slide 1.4s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}
.ac-pct {
    font-size: 0.68rem;
    font-weight: 700;
    color: #4f46e5;
    min-width: 28px;
    text-align: right;
}
.ac-queue {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 6px;
    font-size: 0.68rem;
    font-weight: 600;
    color: #b45309;
}
.ac-queue.is-active {
    color: #4f46e5;
}
.ac-queue i {
    font-size: 0.72rem;
}
.ac-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 9px;
    flex-wrap: wrap;
}
.ac-act {
    border: none;
    border-radius: 8px;
    padding: 6px 14px;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.ac-act.dl {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: #fff;
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
}
.ac-act.dl:hover {
    background: linear-gradient(135deg, #4338ca, #4f46e5);
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(79, 70, 229, 0.3);
}
.ac-act.dl:active {
    transform: translateY(0);
}
.ac-act.retry {
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
}
.ac-act.retry:hover {
    background: #ffedd5;
    transform: translateY(-1px);
}
.ac-act.retry:active {
    transform: translateY(0);
}
.ac-act.abort {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.ac-act.abort:hover:not(:disabled) {
    background: #fee2e2;
    transform: translateY(-1px);
}
.ac-act.abort:disabled {
    opacity: 0.6;
    cursor: default;
}
.ac-err {
    font-size: 0.68rem;
    color: #dc2626;
}
.ac-expired {
    font-size: 0.7rem;
    color: #94a3b8;
}
@keyframes ac-spin-kf {
    to {
        transform: rotate(360deg);
    }
}
@keyframes ac-progress-glow {
    0% {
        background-position: 0% 0%;
    }
    100% {
        background-position: -200% 0%;
    }
}
@keyframes ac-indeterminate-slide {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}
@keyframes ac-download-slide {
    0% {
        transform: translateY(-6px);
        opacity: 0;
    }
    40% {
        opacity: 1;
    }
    80% {
        transform: translateY(6px);
        opacity: 0;
    }
    100% {
        transform: translateY(6px);
        opacity: 0;
    }
}
@keyframes ac-pop-in {
    0% {
        transform: scale(0.85);
        box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.4);
    }
    50% {
        transform: scale(1.15);
        box-shadow: 0 0 0 6px rgba(22, 163, 74, 0);
    }
    100% {
        transform: scale(1);
        box-shadow: 0 0 10px rgba(22, 163, 74, 0.15);
    }
}
</style>

<!-- Toast di-teleport ke <body>, jadi style-nya TIDAK boleh scoped (selector
     [data-v-*] tidak menempel ke elemen di luar root komponen). -->
<style>
.ac-toast {
    position: fixed;
    bottom: 1.25rem;
    right: 1.25rem;
    z-index: 2000;
    width: min(22rem, calc(100vw - 2rem));
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.85rem 0.9rem;
    border-radius: 14px;
    border: 1px solid #eef2f7;
    background: #ffffff;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.16);
}
.ac-toast__icon {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #dcfce7;
    color: #16a34a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}
.ac-toast__body {
    flex: 1;
    min-width: 0;
}
.ac-toast__title {
    font-size: 0.8rem;
    font-weight: 600;
    color: #1e293b;
}
.ac-toast__label {
    font-size: 0.7rem;
    color: #475569;
    margin-top: 1px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ac-toast__actions {
    margin-top: 8px;
}
.ac-toast .ac-act {
    border: none;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.ac-toast .ac-act.dl {
    background: #4f46e5;
    color: #fff;
}
.ac-toast .ac-act.dl:hover {
    background: #4338ca;
}
.ac-toast .ac-dismiss {
    background: transparent;
    border: none;
    color: #cbd5e1;
    cursor: pointer;
    font-size: 15px;
    line-height: 1;
    padding: 0;
    flex-shrink: 0;
}
.ac-toast .ac-dismiss:hover {
    color: #64748b;
}

.ac-toast-enter-active,
.ac-toast-leave-active {
    transition:
        opacity 240ms ease,
        transform 240ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.ac-toast-enter-from,
.ac-toast-leave-to {
    opacity: 0;
    transform: translateY(0.75rem) translateX(0.5rem);
}
</style>
