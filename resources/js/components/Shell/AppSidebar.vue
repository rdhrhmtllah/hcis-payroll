<template>
    <div class="shell-sidebar-backdrop" :class="{ 'is-visible': isSidebarVisible }" @click="handleOutsideClick"></div>

    <aside
        ref="sidebarRoot"
        class="shell-sidebar"
        data-tour="sidebar"
        :class="{
            'is-expanded': isSidebarVisible,
            'is-mobile-open': shell.state.mobileSidebarOpen,
        }"
        @mouseleave="handleMouseLeave"
        @click="handleSidebarBodyClick"
    >
        <div class="shell-sidebar__inner">
            <!-- BRAND -->
            <div class="shell-sidebar__brand" data-tour="sidebar-brand">
                <button
                    class="shell-brand-card shell-btn"
                    type="button"
                    @click.stop="handleBrandClick"
                    :aria-label="brand.appName || 'Open sidebar'"
                >
                    <div class="shell-brand-card__logo" :class="{ 'is-small': !isSidebarVisible }">
                        <template v-if="showImage('mainLogo', brand.mainLogo)">
                            <img
                                :src="brand.mainLogo"
                                :alt="brand.mainLogoAlt || brand.appName || 'EVO Group'"
                                class="shell-brand-image"
                                @error="markImageError('mainLogo')"
                            />
                        </template>
                        <span v-else class="shell-brand-fallback">EVO</span>
                    </div>

                    <!-- <div class="shell-brand-card__content" :class="{ 'is-hidden': !isSidebarVisible }">
                        <div class="shell-eyebrow">Unified Platform</div>
                        <div class="shell-brand-title">{{ brand.appShortName || 'EVO' }}</div>
                        <div class="shell-brand-subtitle">{{ brand.appName || 'EVO Group Unified Platform' }}</div>
                    </div> -->
                </button>

                <div class="shell-mini-brand-row" :class="{ 'is-visible': isSidebarVisible }" @click.stop>
                    <div v-for="subsidiary in subsidiaries" :key="subsidiary.id" class="shell-mini-brand">
                        <template v-if="showImage(`subsidiary-${subsidiary.id}`, subsidiary.src)">
                            <img
                                :src="subsidiary.src"
                                :alt="subsidiary.name"
                                class="shell-mini-brand__image"
                                @error="markImageError(`subsidiary-${subsidiary.id}`)"
                            />
                        </template>
                        <span v-else class="shell-mini-brand__fallback">{{ subsidiary.fallback }}</span>
                    </div>
                </div>
            </div>

            <!-- NAVIGATION -->
            <nav class="shell-nav shell-scrollbar" data-tour="sidebar-nav" @mouseenter="handleMouseEnter" @click.stop>
                <button
                    class="shell-nav-item shell-btn"
                    :class="{ 'is-active': isHomeActive }"
                    type="button"
                    :aria-label="homeItem.title || 'Home'"
                    @click.stop="handleHomeClick"
                >
                    <span class="shell-nav-icon">
                        <i :class="homeItem.icon || 'bi bi-house-door-fill'"></i>
                    </span>

                    <span class="shell-nav-content" :class="{ 'is-hidden': !isSidebarVisible }">
                        <span class="shell-nav-title">{{ homeItem.title || 'Home' }}</span>
                        <span class="shell-nav-subtitle">{{ homeItem.subtitle || 'Halaman Utama' }}</span>
                    </span>

                    <span v-if="isHomeActive" class="shell-active-bar"></span>
                </button>

                <div class="shell-section-label-wrap" :class="{ 'is-visible': isSidebarVisible }">
                    <div class="shell-section-label">{{ navigationSectionLabel }}</div>
                </div>

                <div v-for="module in filteredModules" :key="module.id" class="shell-module">
                    <div class="shell-module-row">
                        <button
                            class="shell-nav-item shell-btn"
                            :class="{ 'is-active': isModuleActive(module) }"
                            type="button"
                            :aria-label="module.name || module.label || 'Modul'"
                            @click.stop="handleModuleSingleClick(module)"
                        >
                            <span class="shell-nav-icon">
                                <i :class="module.icon"></i>
                            </span>

                            <span class="shell-nav-content" :class="{ 'is-hidden': !isSidebarVisible }">
                                <span class="shell-nav-title">{{ module.name }}</span>
                                <span class="shell-nav-subtitle">{{ module.subtitle || module.label }}</span>
                            </span>

                            <span v-if="isModuleActive(module)" class="shell-active-bar"></span>
                        </button>

                        <button
                            class="shell-expand-btn shell-btn"
                            :class="{ 'is-visible': isSidebarVisible }"
                            type="button"
                            :aria-label="`Toggle ${module.name}`"
                            @click.stop="toggleModule(module.id)"
                        >
                            <i
                                class="bi bi-chevron-down shell-rotate"
                                :class="{ 'is-rotated': Boolean(openModules[module.id]) }"
                            ></i>
                        </button>
                    </div>

                    <div
                        class="shell-submenu-wrapper"
                        :class="{
                            'is-open': isSidebarVisible && openModules[module.id],
                        }"
                    >
                        <div class="shell-submenu-wrapper__inner">
                            <!-- Tingkat "bagian" opsional; modul tanpa bagian = satu kelompok tanpa judul (tampilan lama). -->
                            <template v-for="bagian in bagianModul(module)" :key="bagian.id || '_'">
                            <div v-if="bagian.title" class="shell-submenu-section">
                                <span v-if="bagian.icon" class="shell-submenu-section__icon-box">
                                    <i :class="bagian.icon" aria-hidden="true"></i>
                                </span>
                                <span class="shell-submenu-section__title">{{ bagian.title }}</span>
                            </div>
                            <div
                                v-for="group in bagian.groups"
                                :key="group.id"
                                class="shell-submenu-group"
                                :class="{ 'is-in-section': Boolean(bagian.title) }"
                            >
                                <button
                                    class="shell-submenu-toggle shell-btn"
                                    type="button"
                                    :class="{
                                        'is-open': Boolean(openGroups[group.id]),
                                        'is-has-active': groupHasActive(group),
                                    }"
                                    @click.stop="toggleGroup(module.id, group.id)"
                                >
                                    <span class="shell-submenu-toggle__label text-truncate">{{ formatGroupTitle(group.title) }}</span>
                                    <i
                                        class="bi bi-chevron-right shell-rotate shell-submenu-chevron"
                                        :class="{ 'is-rotated-right': Boolean(openGroups[group.id]) }"
                                    ></i>
                                </button>

                                <div class="shell-submenu-items" :class="{ 'is-open': Boolean(openGroups[group.id]) }">
                                    <div class="shell-submenu-items__inner">
                                        <button
                                            v-for="item in group.items"
                                            :key="item.id"
                                            class="shell-submenu-item shell-btn"
                                            :class="{ 'is-active': isSubMenuActive(item) }"
                                            type="button"
                                            @click.stop="activateSubMenu(module, group, item)"
                                        >
                                            <span class="shell-submenu-dot"></span>
                                            <span class="text-truncate">{{ item.title }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            </template>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- PROFILE -->
            <div class="shell-sidebar__profile" data-tour="sidebar-profile-button" @click.stop>
                <div
                    class="shell-profile-menu"
                    data-tour="sidebar-profile-menu"
                    :class="{
                        'is-visible': shell.state.showProfileMenu && isSidebarVisible,
                    }"
                    @click.stop
                    @mousedown.stop
                    @pointerdown.stop
                >
                    <button
                        class="shell-profile-menu__item shell-btn"
                        type="button"
                        @click.stop="handleProfileAction('/user-profile')"
                    >
                        <i class="bi bi-person"></i>
                        <span>Profil Saya</span>
                    </button>

                    <button
                        class="shell-profile-menu__item shell-btn"
                        :class="{ 'is-active': isAboutActive }"
                        type="button"
                        @click.stop="handleProfileAction('/about')"
                    >
                        <i class="bi bi-info-circle"></i>
                        <span>Tentang</span>
                    </button>

                    <div class="shell-profile-divider"></div>

                    <button
                        class="shell-profile-menu__item shell-btn is-danger"
                        type="button"
                        @click.stop="handleProfileAction('/logout')"
                    >
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Keluar Sesi</span>
                    </button>
                </div>

                <button
                    class="shell-profile-trigger shell-btn"
                    :class="{ 'is-active': shell.state.showProfileMenu && isSidebarVisible }"
                    type="button"
                    @click.stop="toggleProfileMenu"
                    @mousedown.stop
                    @pointerdown.stop
                >
                    <div class="shell-profile-avatar">
                        <img
                            v-if="profileAvatarUrl && !profileAvatarError"
                            v-show="profileAvatarLoaded"
                            :src="profileAvatarUrl"
                            :alt="userName"
                            @load="profileAvatarLoaded = true"
                            @error="profileAvatarError = true"
                        />
                        <span
                            v-if="profileAvatarUrl && !profileAvatarLoaded && !profileAvatarError"
                            class="shell-avatar-skeleton"
                            aria-hidden="true"
                        ></span>
                        <span v-if="!profileAvatarUrl || profileAvatarError">{{ userInitials }}</span>
                    </div>

                    <div class="shell-profile-content" :class="{ 'is-hidden': !isSidebarVisible }">
                        <div class="shell-profile-name">{{ userName }}</div>
                        <div class="shell-profile-meta">{{ departmentLabel }}</div>
                    </div>

                    <i
                        v-if="isSidebarVisible"
                        class="bi bi-chevron-up shell-rotate shell-profile-chevron"
                        :class="{ 'is-rotated': shell.state.showProfileMenu }"
                    ></i>
                </button>
            </div>
        </div>

    </aside>

</template>

<script setup>
import { bagianModul } from './bagianMenu';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useShellState } from '../../composables/useShellState';
import { runLogoutTransition } from '../../utils/logout-transition.js';

const shell = useShellState();
const page = usePage();
const sidebarRoot = ref(null);

const props = defineProps({
    user: {
        type: Object,
        default: () => ({}),
    },
    brand: {
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

const openModules = reactive({});
const openGroups = reactive({});
const moduleMemory = reactive({});
const imageErrors = reactive({});
const PROFILE_PHOTO_CACHE_KEY = 'shellProfilePhotoSnapshot';
const profileAvatarUrl = ref('');
const profileAvatarLoaded = ref(false);
const profileAvatarError = ref(false);

const userName = computed(() => props.user?.name || props.user?.username || 'User');
const userInitials = computed(() => props.user?.initials || buildInitials(userName.value));
const departmentLabel = computed(() => props.user?.department || props.user?.job_title || 'Department belum diatur');
const modules = computed(() => props.navigation?.modules || []);
const homeItem = computed(() => props.navigation?.home || { url: '/homepage', title: 'Home' });
const navigationSectionLabel = computed(() => props.navigation?.sectionLabel || 'Platform Utama');
const subsidiaries = computed(() => props.brand?.subsidiaries || []);
const profileAvatarVersion = computed(() => props.user?.avatar_version || props.user?.avatar_updated_at || '');
const profileCacheUserId = computed(() => props.user?.nik || props.user?.id || props.user?.username || '');

const isSidebarVisible = computed(() =>
    shell.state.isMobile ? shell.state.mobileSidebarOpen : shell.state.sidebarExpanded,
);

const isHomeActive = computed(() => shell.state.activeModule === 'HOME' || Boolean(homeItem.value?.isActive));

const filteredModules = computed(() => {
    const query = String(shell.state.searchQuery || '')
        .trim()
        .toLowerCase();

    if (!query) {
        return modules.value;
    }

    return modules.value
        .map((module) => {
            const moduleMatches = [module.code, module.name, module.label, module.subtitle].some((value) =>
                String(value || '')
                    .toLowerCase()
                    .includes(query),
            );

            const groups = (module.groups || [])
                .map((group) => {
                    const groupMatches = [group.title, group.sectionTitle].some((value) =>
                        String(value || '')
                            .toLowerCase()
                            .includes(query),
                    );

                    const items = (group.items || []).filter((item) =>
                        [item.title, item.subtitle, item.jenisPage].some((value) =>
                            String(value || '')
                                .toLowerCase()
                                .includes(query),
                        ),
                    );

                    if (moduleMatches || groupMatches) {
                        return group;
                    }

                    if (!items.length) {
                        return null;
                    }

                    return {
                        ...group,
                        items,
                    };
                })
                .filter(Boolean);

            if (moduleMatches || groups.length) {
                return {
                    ...module,
                    groups,
                };
            }

            return null;
        })
        .filter(Boolean);
});

const isAboutActive = computed(() => String(page.url || '').startsWith('/about'));

function buildInitials(name) {
    return (
        String(name || 'User')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || 'US'
    );
}

function showImage(key, src) {
    return Boolean(src) && !imageErrors[key];
}

function markImageError(key) {
    imageErrors[key] = true;
}

function readProfilePhotoCache() {
    try {
        const raw = window.localStorage.getItem(PROFILE_PHOTO_CACHE_KEY);
        if (!raw) {
            return null;
        }

        const cached = JSON.parse(raw);
        const isExpired = Number(cached.expiresAt || 0) <= Date.now();
        const isSameUser =
            !cached.userId || !profileCacheUserId.value || String(cached.userId) === String(profileCacheUserId.value);
        const isSameVersion =
            !profileAvatarVersion.value ||
            !cached.version ||
            String(cached.version) === String(profileAvatarVersion.value);

        if (isExpired || !isSameUser || !isSameVersion || !cached.url) {
            window.localStorage.removeItem(PROFILE_PHOTO_CACHE_KEY);
            return null;
        }

        return cached;
    } catch (error) {
        return null;
    }
}

function writeProfilePhotoCache(
    url = props.user?.avatar_snapshot_url || props.user?.avatar_url,
    version = profileAvatarVersion.value,
) {
    if (!url) {
        profileAvatarUrl.value = '';
        return;
    }

    const expiresAt = props.user?.avatar_expires_at
        ? new Date(props.user.avatar_expires_at).getTime()
        : Date.now() + 6 * 60 * 60 * 1000;
    const payload = {
        userId: profileCacheUserId.value,
        version: version || Date.now(),
        url,
        expiresAt,
    };

    profileAvatarUrl.value = url;

    try {
        window.localStorage.setItem(PROFILE_PHOTO_CACHE_KEY, JSON.stringify(payload));
    } catch (error) {
        console.warn('Failed to persist sidebar profile photo:', error);
    }
}

function hydrateProfilePhotoFromCache() {
    const cached = readProfilePhotoCache();
    if (cached?.url) {
        profileAvatarUrl.value = cached.url;
        return;
    }

    writeProfilePhotoCache();
}

function handleProfilePhotoSnapshotUpdated(event) {
    const payload = event.detail || {};
    const isSameUser =
        !payload.userId || !profileCacheUserId.value || String(payload.userId) === String(profileCacheUserId.value);

    if (!isSameUser || !payload.url) {
        return;
    }

    profileAvatarUrl.value = payload.url;

    try {
        window.localStorage.setItem(PROFILE_PHOTO_CACHE_KEY, JSON.stringify(payload));
    } catch (error) {
        console.warn('Failed to update sidebar profile photo cache:', error);
    }
}

function clearOpenState(target) {
    Object.keys(target).forEach((key) => {
        delete target[key];
    });
}

function rememberModuleGroup(moduleId, groupId) {
    if (!moduleId) {
        return;
    }

    moduleMemory[moduleId] = {
        groupId: groupId || null,
    };
}

function restoreModuleState(module) {
    if (!module) {
        return;
    }

    clearOpenState(openModules);
    clearOpenState(openGroups);

    openModules[module.id] = true;

    const activeGroup = (module.groups || []).find((group) =>
        (group.items || []).some((item) => isSubMenuActive(item)),
    );

    if (activeGroup) {
        openGroups[activeGroup.id] = true;
        rememberModuleGroup(module.id, activeGroup.id);
        return;
    }

    const rememberedGroupId = moduleMemory[module.id]?.groupId;
    if (!rememberedGroupId) {
        return;
    }

    const rememberedGroup = (module.groups || []).find((group) => group.id === rememberedGroupId);
    if (rememberedGroup) {
        openGroups[rememberedGroup.id] = true;
    }
}

function syncOpenState() {
    const activeModule = modules.value.find((module) => isModuleActive(module));

    if (!activeModule) {
        clearOpenState(openModules);
        clearOpenState(openGroups);
        return;
    }

    restoreModuleState(activeModule);
}

function isModuleActive(module) {
    return shell.state.activeModule === module.code || Boolean(module.isActive);
}

function isSubMenuActive(item) {
    return shell.state.activeSubMenu === item.jenisPage || Boolean(item.isActive);
}

function groupHasActive(group) {
    return (group?.items || []).some((item) => isSubMenuActive(item));
}

function formatGroupTitle(title) {
    if (!title) {
        return '';
    }

    const str = String(title).trim();
    if (str === str.toUpperCase() && /[A-Z]/.test(str)) {
        const acronyms = new Set([
            'KPI',
            'PA',
            'CEO',
            'LMS',
            'ESS',
            'DMS',
            'HRIS',
            'SOP',
            'IT',
            'ID',
            'HC',
            'EVO',
            'PKWT',
            'PKWTT',
        ]);

        return str
            .split(/\s+/)
            .map((word) => {
                const clean = word.replace(/[^A-Za-z0-9]/g, '');
                if (acronyms.has(clean.toUpperCase())) {
                    return word.toUpperCase();
                }
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            })
            .join(' ');
    }

    return str;
}

function handleMouseEnter() {
    if (!shell.state.isMobile) {
        shell.setSidebarExpandedByHover(true);
    }
}

function handleMouseLeave() {
    if (!shell.state.isMobile) {
        shell.setSidebarExpandedByHover(false);
    }
}

function handleSidebarBodyClick() {
    return;
}

function toggleModule(moduleId) {
    if (shell.state.sidebarTourLocked) {
        return;
    }

    const nextState = !openModules[moduleId];
    clearOpenState(openModules);
    clearOpenState(openGroups);

    if (nextState) {
        openModules[moduleId] = true;
        const module = modules.value.find((entry) => entry.id === moduleId);
        if (!module) {
            return;
        }

        const rememberedGroupId = moduleMemory[moduleId]?.groupId;
        if (!rememberedGroupId) {
            return;
        }

        const rememberedGroup = (module.groups || []).find((group) => group.id === rememberedGroupId);
        if (rememberedGroup) {
            openGroups[rememberedGroup.id] = true;
        }
    }
}

function toggleGroup(moduleId, groupId) {
    if (shell.state.sidebarTourLocked) {
        return;
    }

    openModules[moduleId] = true;
    const nextState = !openGroups[groupId];
    clearOpenState(openGroups);

    if (nextState) {
        openGroups[groupId] = true;
    }

    rememberModuleGroup(moduleId, groupId);
}

function handleHomeClick() {
    if (shell.state.sidebarTourLocked) {
        return;
    }

    shell.setActiveModule('HOME');
    shell.setActiveSubMenu(null);
    shell.closeAllPanels();
    router.visit(homeItem.value?.url || '/homepage');
}

function visitUrl(url, target = 'self') {
    if (shell.state.sidebarTourLocked) {
        return;
    }

    if (!url) {
        return;
    }

    shell.closeAllPanels();

    if (target === 'blank' || target === '_blank') {
        window.open(url, '_blank', 'noopener,noreferrer');
        return;
    }

    if (/^https?:\/\//i.test(url)) {
        window.location.assign(url);
        return;
    }

    router.visit(url);
}

function handleModuleSingleClick(module) {
    if (shell.state.sidebarTourLocked) {
        return;
    }

    if (!isSidebarVisible.value) {
        if (shell.state.isMobile) {
            shell.openMobileSidebar();
        } else {
            shell.toggleDesktopSidebarLock();
        }
        openModules[module.id] = true;
        return;
    }

    toggleModule(module.id);
}


function activateSubMenu(module, group, item) {
    if (shell.state.sidebarTourLocked) {
        return;
    }

    shell.setActiveModule(module.code);
    shell.setActiveSubMenu(item.jenisPage);
    openModules[module.id] = true;
    openGroups[group.id] = true;
    rememberModuleGroup(module.id, group.id);
    visitUrl(item.url, item.target);
}

function handleBrandClick() {
    if (shell.state.sidebarTourLocked) {
        return;
    }

    if (shell.state.isMobile) {
        shell.toggleMobileSidebar();
        return;
    }

    if (!shell.state.sidebarLocked) {
        shell.resetInteractionState();
        shell.state.sidebarLocked = true;
        shell.state.sidebarExpanded = true;
        return;
    }

    handleHomeClick();
}

function toggleProfileMenu() {
    if (shell.state.sidebarTourLocked) {
        return;
    }

    if (!isSidebarVisible.value) {
        if (shell.state.isMobile) {
            shell.openMobileSidebar();
        } else {
            shell.toggleDesktopSidebarLock();
        }

        return;
    }

    const nextValue = !shell.state.showProfileMenu;
    shell.resetInteractionState();
    shell.state.showProfileMenu = nextValue;
}

function handleProfileAction(url) {
    if (shell.state.sidebarTourLocked) {
        return;
    }

    shell.resetInteractionState();
    shell.closeMobileSidebar();
    shell.state.sidebarLocked = false;
    shell.state.sidebarExpanded = false;

    if (!url) {
        return;
    }

    if (url === '/logout') {
        runLogoutTransition(userName.value, () => {
            // Navigasi browser biasa, BUKAN kunjungan Inertia. /logout selalu
            // berakhir dengan muat-ulang penuh (Inertia::location), jadi tidak ada
            // yang hilang — dan jalur ini tetap bekerja ketika justru SPA-nya yang
            // bermasalah (sesi kadung habis, 419, 5xx, jaringan putus). Kunjungan
            // Inertia yang tak pernah selesai adalah penyebab utama orang
            // tersangkut di balik lapisan logout.
            window.location.assign('/logout');
        });
        return;
    }

    router.visit(url, {
        preserveScroll: true,
        preserveState: false,
    });
}

function handleOutsideClick(event) {
    if (shell.state.sidebarTourLocked) {
        return;
    }

    if (!sidebarRoot.value) {
        return;
    }

    const isFloatingToggle = event.target.closest('.shell-floating-toggle');
    if (isFloatingToggle) {
        return;
    }

    if (!sidebarRoot.value.contains(event.target)) {
        shell.resetInteractionState();

        if (shell.state.isMobile) {
            shell.closeMobileSidebar();
        } else if (shell.state.sidebarLocked) {
            shell.toggleDesktopSidebarLock();
        }
    }
}

watch(
    modules,
    () => {
        syncOpenState();
    },
    {
        immediate: true,
        deep: true,
    },
);

watch(
    () => [shell.state.activeModule, shell.state.activeSubMenu],
    () => {
        syncOpenState();
    },
);

watch(
    () => shell.state.searchQuery,
    (query) => {
        if (!query) {
            syncOpenState();
            return;
        }

        clearOpenState(openModules);
        clearOpenState(openGroups);

        filteredModules.value.forEach((module) => {
            openModules[module.id] = true;

            (module.groups || []).forEach((group) => {
                openGroups[group.id] = true;
            });
        });
    },
);

watch(profileAvatarUrl, () => {
    profileAvatarLoaded.value = false;
    profileAvatarError.value = false;
});

watch(
    () => [
        props.user?.avatar_snapshot_url,
        props.user?.avatar_url,
        profileAvatarVersion.value,
        profileCacheUserId.value,
    ],
    () => {
        hydrateProfilePhotoFromCache();
    },
    {
        immediate: true,
    },
);

onMounted(() => {
    hydrateProfilePhotoFromCache();
    document.addEventListener('mousedown', handleOutsideClick);
    window.addEventListener('profile-photo:snapshot-updated', handleProfilePhotoSnapshotUpdated);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleOutsideClick);
    window.removeEventListener('profile-photo:snapshot-updated', handleProfilePhotoSnapshotUpdated);
});
</script>

<style scoped>
.bi {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    line-height: 1 !important;
    vertical-align: 0 !important;
}

.bi::before {
    display: inline-block !important;
    line-height: 1 !important;
    vertical-align: 0 !important;
}
</style>

<style scoped>
.shell-sidebar,
.shell-sidebar * {
    box-sizing: border-box;
}

.shell-sidebar:not(.is-expanded):not(.is-mobile-open) .shell-brand-card__logo::before,
.shell-sidebar:not(.is-expanded):not(.is-mobile-open) .shell-brand-card__logo::after {
    animation-play-state: paused;
}

/* Matikan animasi skeleton jika tidak terlihat */
.shell-sidebar:not(.is-expanded):not(.is-mobile-open) .shell-avatar-skeleton {
    animation-play-state: paused;
}

.shell-sidebar {
    --sidebar-collapsed-width: 4.5rem;
    --sidebar-expanded-width: 20rem;
    --ease-shell: cubic-bezier(0.2, 0.8, 0.2, 1);
    --blue: #6366f1;
    --blue-50: #eff6ff;
    --blue-100: #dbeafe;
    --blue-700: #4f46e5;
    --slate-50: #f8fafc;
    --slate-100: #f1f5f9;
    --slate-200: #e2e8f0;
    --slate-300: #cbd5e1;
    --slate-400: #94a3b8;
    --slate-500: #64748b;
    --slate-600: #475569;
    --slate-700: #334155;
    --slate-900: #0f172a;

    position: fixed;
    left: 0;
    top: 0;
    z-index: 1030;
    width: var(--sidebar-collapsed-width);
    height: 100vh;
    height: 100dvh;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.98) 100%);
    border-right: 1px solid rgba(226, 232, 240, 0.72);
    /* backdrop-filter: blur(16px); */
    /* -webkit-backdrop-filter: blur(16px); */
    box-shadow: 10px 0 30px rgba(15, 23, 42, 0.08);
    transition: width 380ms var(--ease-shell);
    /* will-change: transform; removed to prevent texture re-upload on width animation */
    cursor: pointer;
    color: var(--slate-900);
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    contain: layout;
    overscroll-behavior: contain;
}

.shell-sidebar__inner {
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 1;
    overflow: hidden;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.65), rgba(255, 255, 255, 0.45));
}

.shell-sidebar::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at top left, rgba(59, 130, 246, 0.14), transparent 32%),
        radial-gradient(circle at bottom center, rgba(99, 102, 241, 0.08), transparent 40%);
    pointer-events: none;
}

.shell-sidebar.is-expanded,
.shell-sidebar.is-mobile-open {
    width: var(--sidebar-expanded-width);
}

.shell-sidebar-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1030;
    background: rgba(15, 23, 42, 0.2);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition:
        opacity 400ms ease,
        visibility 400ms ease;
}

.shell-sidebar-backdrop.is-visible {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.shell-sidebar__inner {
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 1;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.42), rgba(255, 255, 255, 0.22));
}

.shell-btn {
    border: 0;
    outline: 0;
    background: transparent;
    font: inherit;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

.shell-sidebar__brand {
    position: relative;
    min-height: 6.875rem;
    padding: 1rem 0.75rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.6);
    display: flex;
    flex-direction: column;
    justify-content: center;
}

/* ================= FLOATING TOGGLE ================= */
.shell-floating-toggle {
    position: fixed;
    top: 3.4rem;
    left: calc(4.5rem - 0.75rem);
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    background: white;
    color: var(--slate-500);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.06);
    z-index: 1031;
    transition:
        left 380ms cubic-bezier(0.2, 0.8, 0.2, 1),
        transform 200ms ease,
        background 200ms ease,
        color 200ms ease,
        opacity 300ms ease,
        box-shadow 200ms ease;
}

.shell-floating-toggle.is-expanded {
    left: calc(20rem - 0.75rem);
}

.shell-floating-toggle:hover {
    background: var(--blue-50);
    color: var(--blue-700);
    border-color: rgba(191, 219, 254, 0.8);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.12);
    transform: scale(1.1);
}

.shell-floating-toggle:active {
    transform: scale(0.95);
}

.shell-brand-card {
    position: relative;
    width: 100%;
    height: 4rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 0;
    border-radius: 1rem;
    transition:
        background 300ms ease,
        transform 300ms ease,
        filter 300ms ease;
}

.shell-sidebar.is-expanded .shell-brand-card,
.shell-sidebar.is-mobile-open .shell-brand-card {
    justify-content: center;
}

.shell-brand-card:hover {
    background: rgba(15, 23, 42, 0.04);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4);
}

.shell-sidebar:not(.is-expanded):not(.is-mobile-open) .shell-brand-card:hover {
    background: transparent;
    box-shadow: none;
    filter: drop-shadow(0 10px 16px rgba(37, 99, 235, 0.12));
}

.shell-brand-card__logo {
    position: relative;
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 1rem;
    background:
        radial-gradient(
            circle at 30% 20%,
            rgba(255, 255, 255, 0.9),
            rgba(255, 255, 255, 0.18) 48%,
            rgba(255, 255, 255, 0.08) 100%
        ),
        linear-gradient(135deg, rgba(255, 255, 255, 0.36), rgba(219, 234, 254, 0.22));
    border: 1px solid rgba(191, 219, 254, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow:
        0 8px 18px rgba(15, 23, 42, 0.05),
        inset 0 1px 0 rgba(255, 255, 255, 0.45);
    transition:
        width 380ms var(--ease-shell),
        height 380ms var(--ease-shell),
        border-radius 380ms var(--ease-shell),
        transform 380ms var(--ease-shell),
        background 380ms var(--ease-shell),
        box-shadow 380ms var(--ease-shell),
        border-color 380ms var(--ease-shell);
    isolation: isolate;
}

.shell-sidebar:not(.is-expanded):not(.is-mobile-open) .shell-brand-card:hover .shell-brand-card__logo {
    background:
        radial-gradient(circle at 28% 18%, rgba(255, 255, 255, 1), rgba(255, 255, 255, 0.22) 46%, transparent 70%),
        linear-gradient(145deg, rgba(255, 255, 255, 0.82), rgba(239, 246, 255, 0.62));
    border-color: rgba(147, 197, 253, 0.78);
    box-shadow:
        0 10px 22px rgba(37, 99, 235, 0.14),
        0 0 0 3px rgba(219, 234, 254, 0.26),
        0 0 22px rgba(245, 158, 11, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.92),
        inset 0 -10px 18px rgba(99, 102, 241, 0.08);
    transform: translateY(-2px);
}

.shell-brand-card__logo::before {
    content: '';
    position: absolute;
    top: -30%;
    bottom: -30%;
    left: 0;
    width: 72%;
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0) 40%,
        rgba(255, 255, 255, 0.6) 50%,
        rgba(255, 255, 255, 0) 60%,
        rgba(255, 255, 255, 0) 100%
    );
    opacity: 0;
    pointer-events: none;
    /* mix-blend-mode: screen; */
    z-index: 0;
}

.shell-brand-card:hover .shell-brand-card__logo::before {
    animation: brandShimmer 1.15s ease-out both;
}

.shell-sidebar:not(.is-expanded):not(.is-mobile-open) .shell-brand-card:hover .shell-brand-card__logo::before,
.shell-sidebar:not(.is-expanded):not(.is-mobile-open) .shell-brand-card:hover .shell-brand-card__logo::after {
    animation-play-state: running;
}

.shell-brand-card__logo::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.65),
        inset 0 -10px 20px rgba(79, 70, 229, 0.08),
        0 0 0 1px rgba(255, 255, 255, 0.18),
        0 10px 24px rgba(79, 70, 229, 0.14),
        0 0 24px rgba(99, 102, 241, 0.15);
    opacity: 0.95;
    pointer-events: none;
    z-index: 0;
}

.shell-sidebar:not(.is-expanded):not(.is-mobile-open) .shell-brand-card:hover .shell-brand-card__logo::after {
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.94),
        inset 0 -12px 22px rgba(79, 70, 229, 0.1),
        0 0 0 1px rgba(255, 255, 255, 0.32),
        0 10px 22px rgba(37, 99, 235, 0.12),
        0 0 20px rgba(245, 158, 11, 0.1);
    opacity: 1;
}

.shell-brand-card__logo > * {
    position: relative;
    z-index: 1;
}

.shell-brand-card__logo.is-small {
    width: 3rem;
    height: 3rem;
    border-radius: 0.82rem;
}

/* Animations */
@keyframes gradientMove {
    0%,
    100% {
        transform: scale(1) rotate(0deg);
    }
    50% {
        transform: scale(1.05) rotate(1deg);
    }
}

@keyframes shimmer {
    0% {
        left: -100%;
    }
    100% {
        left: 100%;
    }
}

@keyframes brandShimmer {
    0% {
        transform: translateX(-160%) skewX(-18deg);
        opacity: 0;
    }
    8% {
        opacity: 0.2;
    }
    26% {
        opacity: 0.9;
    }
    46% {
        opacity: 1;
    }
    66% {
        opacity: 0.55;
    }
    100% {
        transform: translateX(230%) skewX(-18deg);
        opacity: 0;
    }
}

/* @keyframes brandGlow {
    0%,
    100% {
        opacity: 0.72;
        filter: blur(0px);
        transform: scale(1);
    }
    50% {
        opacity: 1;
        filter: blur(0.2px);
        transform: scale(1.03);
    }
} */

@keyframes brandGlow {
    0%,
    100% {
        opacity: 0.72;
        transform: scale(1);
    }
    50% {
        opacity: 1;
        transform: scale(1.03);
    }
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.shell-brand-image,
.shell-mini-brand__image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    /* mix-blend-mode: multiply; */
}

.shell-brand-image {
    padding: 3px;
    border: none !important;
    transition:
        filter 320ms var(--ease-shell),
        transform 320ms var(--ease-shell);
}

.shell-sidebar:not(.is-expanded):not(.is-mobile-open) .shell-brand-card:hover .shell-brand-image {
    filter: none;
    transform: none;
}

.shell-brand-card__content,
.shell-nav-content,
.shell-profile-content {
    min-width: 0;
    opacity: 1;
    transform: translateX(0);
    max-width: 12rem;
    white-space: nowrap;
    transition:
        opacity 380ms var(--ease-shell),
        transform 380ms var(--ease-shell),
        max-width 380ms var(--ease-shell);
}

.shell-brand-card__content.is-hidden,
.shell-nav-content.is-hidden,
.shell-profile-content.is-hidden {
    opacity: 0;
    transform: translateX(-0.9rem);
    max-width: 0;
    pointer-events: none;
}

.shell-eyebrow {
    font-size: 0.625rem;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: var(--slate-400);
    font-weight: 950;
}

.shell-brand-title {
    margin-top: 0.28rem;
    font-size: 0.98rem;
    line-height: 1;
    font-weight: 950;
    color: var(--slate-900);
    letter-spacing: -0.02em;
}

.shell-brand-subtitle {
    margin-top: 0.28rem;
    max-width: 13rem;
    font-size: 0.64rem;
    line-height: 1;
    font-weight: 650;
    color: var(--slate-500);
    overflow: hidden;
    text-overflow: ellipsis;
}

.shell-brand-fallback,
.shell-mini-brand__fallback {
    color: var(--blue);
    font-size: 0.68rem;
    font-weight: 950;
    letter-spacing: 0.04em;
}

.shell-mini-brand-row {
    max-height: 0;
    opacity: 0;
    pointer-events: none;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 0;
    margin-top: 0;
    padding: 0.12rem;
    border-radius: 0.85rem;
    border: 1px solid rgba(191, 219, 254, 0.32);
    background:
        linear-gradient(90deg, rgba(255, 255, 255, 0.24), rgba(248, 250, 252, 0.88), rgba(255, 255, 255, 0.24)),
        linear-gradient(180deg, rgba(255, 255, 255, 0.76), rgba(239, 246, 255, 0.48));
    box-shadow:
        0 10px 22px rgba(15, 23, 42, 0.04),
        inset 0 1px 0 rgba(255, 255, 255, 0.72),
        inset 0 -8px 18px rgba(37, 99, 235, 0.04);
    transform: translateY(-0.35rem);
    will-change: transform, opacity;
    transition:
        max-height 380ms var(--ease-shell),
        opacity 300ms ease,
        margin-top 380ms var(--ease-shell),
        transform 380ms var(--ease-shell);
}

.shell-mini-brand-row.is-visible {
    max-height: 2.35rem;
    opacity: 1;
    pointer-events: auto;
    margin-top: 0.5rem;
    transform: translateY(0);
    transition:
        max-height 380ms var(--ease-shell),
        opacity 300ms ease 80ms,
        margin-top 380ms var(--ease-shell),
        transform 380ms var(--ease-shell);
}

.shell-mini-brand {
    position: relative;
    flex: 1;
    height: 2rem;
    border-radius: 0.7rem;
    border: 0;
    background: transparent;
    /* backdrop-filter: blur(14px) saturate(1.12);
    -webkit-backdrop-filter: blur(14px) saturate(1.12); */
    box-shadow: none;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
    transition:
        border-color 300ms ease,
        transform 300ms ease,
        background-color 300ms ease;
}

.shell-mini-brand::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background:
        radial-gradient(
            circle at 20% 15%,
            rgba(255, 255, 255, 0.68),
            rgba(255, 255, 255, 0.1) 46%,
            rgba(219, 234, 254, 0.08) 100%
        ),
        linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(191, 219, 254, 0.08));
    opacity: 0.54;
    transition:
        opacity 300ms ease,
        transform 300ms ease;
    pointer-events: none;
}

.shell-mini-brand:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 0.38rem;
    right: 0;
    bottom: 0.38rem;
    width: 1px;
    background: linear-gradient(
        180deg,
        rgba(148, 163, 184, 0),
        rgba(148, 163, 184, 0.24) 46%,
        rgba(148, 163, 184, 0)
    );
    pointer-events: none;
}

.shell-mini-brand > * {
    position: relative;
    z-index: 1;
}

.shell-mini-brand:hover {
    box-shadow:
        inset 0 0 0 1px rgba(147, 197, 253, 0.22),
        inset 0 8px 18px rgba(255, 255, 255, 0.38);
    background: rgba(255, 255, 255, 0.42);
    transform: none;
}

.shell-mini-brand:hover::before {
    opacity: 1;
    transform: scale(1.02);
}

/* ================= NAV ================= */
.shell-nav {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 0 0.5rem;
    margin: 1rem 0;
}

.shell-nav-item {
    position: relative;
    width: 100%;
    min-width: 0;
    height: 3rem;
    border-radius: 0.9rem;
    display: flex;
    align-items: center;
    color: var(--slate-600);
    transition:
        background 300ms ease,
        color 300ms ease,
        transform 300ms ease;
    overflow: hidden;
}

.shell-nav-item:hover {
    background: rgba(15, 23, 42, 0.04);
    color: var(--blue-700);
}

.shell-nav-item.is-active {
    background: linear-gradient(135deg, rgba(219, 234, 254, 0.9), rgba(239, 246, 255, 0.6));
    color: var(--blue-800);
    box-shadow:
        0 8px 20px rgba(37, 99, 235, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.8),
        inset 0 0 0 1px rgba(191, 219, 254, 0.6);
}

.shell-nav-icon {
    width: 3rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--slate-400);
    font-size: 1.2rem;
    transition: color 300ms ease;
}

.shell-nav-item:hover .shell-nav-icon,
.shell-nav-item.is-active .shell-nav-icon {
    color: var(--blue);
}

.shell-nav-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    overflow: hidden;
}

.shell-nav-title {
    max-width: 11.5rem;
    color: var(--slate-900);
    font-size: 0.875rem;
    line-height: 1;
    font-weight: 850;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: -0.015em;
}

.shell-nav-item.is-active .shell-nav-title {
    color: #1e40af;
}

.shell-nav-subtitle {
    margin-top: 0.3rem;
    max-width: 11.5rem;
    color: var(--slate-500);
    font-size: 0.625rem;
    line-height: 1;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.shell-active-bar {
    position: absolute;
    left: 0;
    top: 50%;
    width: 0.25rem;
    height: 1.5rem;
    border-radius: 0 999px 999px 0;
    background: var(--blue);
    transform: translateY(-50%);
}

.shell-section-label-wrap {
    height: 0;
    opacity: 0;
    overflow: hidden;
    transform: translateY(-0.4rem);
    transition:
        opacity 300ms ease,
        transform 380ms var(--ease-shell);
}

.shell-section-label-wrap.is-visible {
    height: 2rem;
    opacity: 1;
    margin-top: 0.9rem;
    margin-bottom: 0.35rem;
    transform: translateY(0);
    transition:
        opacity 300ms ease 110ms,
        transform 380ms var(--ease-shell);
}

.shell-section-label {
    padding: 0 1rem;
    font-size: 0.625rem;
    line-height: 1;
    font-weight: 950;
    text-transform: uppercase;
    letter-spacing: 0.18em;
    color: var(--slate-400);
    white-space: nowrap;
}

.shell-module {
    margin-bottom: 0.25rem;
}

.shell-module-row {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.shell-expand-btn {
    position: absolute;
    right: 0.3rem;
    top: 50%;
    width: 1.55rem;
    height: 1.55rem;
    border-radius: 0.72rem;
    color: var(--slate-500);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.68rem;
    background: rgba(255, 255, 255, 0.4);
    opacity: 0;
    transform: translateY(-50%) scale(0.85);
    pointer-events: none;
    transition:
        opacity 200ms ease,
        transform 360ms var(--ease-shell),
        background 200ms ease,
        color 200ms ease;
}

.shell-expand-btn.is-visible {
    opacity: 1;
    transform: translateY(-50%) scale(1);
    pointer-events: auto;
    transition:
        opacity 180ms ease 100ms,
        transform 360ms var(--ease-shell),
        background 200ms ease,
        color 200ms ease;
}

/* Saat hover seluruh row, chevron ikut "nyala" halus */
.shell-module-row:hover .shell-expand-btn.is-visible {
    background: rgba(255, 255, 255, 0.85);
    color: var(--slate-600);
}

/* Hover langsung di atas chevron */
.shell-expand-btn:hover {
    background: rgba(239, 246, 255, 0.78);
    color: var(--blue);
}

.shell-expand-btn:active {
    transform: translateY(-50%) scale(0.90);
}

/* Saat modul sudah dibuka */
.shell-expand-btn:has(.is-rotated) {
    color: var(--blue);
    background: rgba(239, 246, 255, 0.5);
}

/* ================= SUBMENU ================= */
.shell-submenu-wrapper {
    content-visibility: auto;
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transform: translateY(-0.3rem);
    overflow: hidden;
    margin-left: 1.5rem;
    padding-left: 0.65rem;
    margin-top: 0.2rem;
    margin-bottom: 0.25rem;
    border-left: 1.5px solid rgba(226, 232, 240, 0.85);
    transition:
        grid-template-rows 460ms var(--ease-shell),
        opacity 220ms ease,
        transform 460ms var(--ease-shell),
        border-color 300ms ease;
}

.shell-module:has(.shell-nav-item.is-active) .shell-submenu-wrapper {
    border-left-color: rgba(147, 197, 253, 0.9);
}

.shell-submenu-wrapper:not(.is-open) {
    visibility: hidden;
    transition:
        grid-template-rows 460ms var(--ease-shell),
        opacity 220ms ease,
        transform 460ms var(--ease-shell),
        visibility 0s linear 460ms;
}

.shell-submenu-wrapper.is-open {
    grid-template-rows: 1fr;
    opacity: 1;
    transform: translateY(0);
    transition:
        grid-template-rows 460ms var(--ease-shell),
        opacity 200ms ease 80ms,
        transform 460ms var(--ease-shell);
}

.shell-submenu-wrapper__inner {
    min-height: 0;
    overflow: hidden;
    padding-right: 0.15rem;
}

.shell-submenu-group {
    margin-bottom: 0.15rem;
}

/* Judul "bagian" (tingkat opsional di atas grup) — tidak dapat dilipat agar tak menambah klik. */
.shell-submenu-section {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 0.45rem 0.25rem 0.2rem;
    margin-top: 0.2rem;
}

.shell-submenu-section:not(:first-child) {
    margin-top: 0.75rem;
    padding-top: 0.65rem;
    border-top: 1px dashed rgba(203, 213, 225, 0.7);
}

.shell-submenu-section__icon-box {
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 0.38rem;
    background: rgba(239, 246, 255, 0.95);
    border: 1px solid rgba(191, 219, 254, 0.6);
    color: var(--blue-700);
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 0.72rem;
    flex-shrink: 0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    line-height: 1 !important;
}

.shell-submenu-section__icon-box i {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 100% !important;
    height: 100% !important;
    line-height: 1 !important;
    vertical-align: 0 !important;
    text-align: center !important;
    margin: 0 !important;
    padding: 0 !important;
}

.shell-submenu-section__icon-box i::before {
    display: inline-block !important;
    line-height: 1 !important;
    vertical-align: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
}

.shell-submenu-section__title {
    color: var(--slate-500);
    font-size: 0.67rem;
    font-weight: 750;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    line-height: 1;
    white-space: normal;
    overflow-wrap: break-word;
    display: inline-flex;
    align-items: center;
}

.shell-submenu-group.is-in-section {
    padding-left: 0.25rem;
}

.shell-submenu-toggle {
    width: 100%;
    min-height: 2.15rem;
    padding: 0.35rem 0.55rem 0.35rem 0.55rem;
    border-radius: 0.65rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    color: var(--slate-600);
    font-size: 0.78rem;
    line-height: 1.25;
    font-weight: 600;
    letter-spacing: -0.01em;
    transition:
        background 200ms ease,
        color 200ms ease,
        box-shadow 200ms ease;
}

.shell-submenu-toggle:hover {
    background: rgba(15, 23, 42, 0.04);
    color: var(--slate-900);
}

.shell-submenu-toggle.is-open {
    color: var(--blue-700);
    font-weight: 650;
    background: rgba(239, 246, 255, 0.75);
}

.shell-submenu-toggle.is-has-active {
    color: var(--blue-800);
    font-weight: 700;
}

.shell-submenu-toggle__label {
    min-width: 0;
    flex: 1;
    text-align: left;
}

.shell-submenu-chevron {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 1.15rem;
    height: 1.15rem;
    font-size: 0.65rem;
    color: var(--slate-400);
    flex-shrink: 0;
    line-height: 1 !important;
    vertical-align: 0 !important;
    text-align: center;
    transform-origin: center center !important;
    transition:
        color 200ms ease,
        transform 260ms var(--ease-shell);
}

.shell-submenu-chevron::before {
    display: block !important;
    line-height: 1 !important;
    vertical-align: 0 !important;
}

.shell-submenu-toggle:hover .shell-submenu-chevron {
    color: var(--slate-700);
}

.shell-submenu-toggle.is-open .shell-submenu-chevron {
    color: var(--blue-700);
}

.shell-submenu-items {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    overflow: hidden;
    margin-top: 0.1rem;
    transform: translateY(-0.18rem);
    transition:
        grid-template-rows 340ms var(--ease-shell),
        opacity 200ms ease,
        transform 340ms var(--ease-shell);
}

.shell-submenu-items.is-open {
    grid-template-rows: 1fr;
    opacity: 1;
    padding-bottom: 0.25rem;
    transform: translateY(0);
    transition:
        grid-template-rows 340ms var(--ease-shell),
        opacity 200ms ease 60ms,
        transform 340ms var(--ease-shell);
}

.shell-submenu-items__inner {
    min-height: 0;
    overflow: hidden;
    padding-left: 0.75rem;
    margin-left: 0.65rem;
    margin-top: 0.15rem;
    margin-bottom: 0.2rem;
    border-left: 1.5px solid rgba(226, 232, 240, 0.9);
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.shell-submenu-item {
    width: 100%;
    min-height: 2rem;
    padding: 0.3rem 0.65rem;
    border-radius: 0.55rem;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    color: var(--slate-500);
    font-size: 0.74rem;
    line-height: 1.25;
    font-weight: 550;
    transition:
        background 200ms ease,
        color 200ms ease;
}

.shell-submenu-item:hover {
    background: rgba(15, 23, 42, 0.04);
    color: var(--blue-700);
}

.shell-submenu-item.is-active {
    background: linear-gradient(135deg, rgba(219, 234, 254, 0.9), rgba(239, 246, 255, 0.65));
    color: #1e40af;
    font-weight: 700;
    box-shadow:
        0 2px 6px rgba(37, 99, 235, 0.08),
        inset 0 0 0 1px rgba(191, 219, 254, 0.65);
}

.shell-submenu-dot {
    width: 0.34rem;
    height: 0.34rem;
    border-radius: 999px;
    background: var(--slate-300);
    flex-shrink: 0;
    transition:
        background 200ms ease,
        transform 200ms ease,
        box-shadow 200ms ease;
}

.shell-submenu-item:hover .shell-submenu-dot {
    background: var(--blue);
    transform: scale(1.2);
}

.shell-submenu-item.is-active .shell-submenu-dot {
    background: var(--blue-700);
    transform: scale(1.3);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.22);
}

/* ================= PROFILE ================= */
.shell-sidebar__profile {
    position: relative;
    padding: 0.75rem;
    border-top: 1px solid rgba(226, 232, 240, 0.52);
    background: rgba(255, 255, 255, 0.65);
    flex-shrink: 0;
}

.shell-profile-trigger {
    width: 100%;
    height: 3.5rem;
    padding: 0.5rem;
    border-radius: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition:
        background 300ms ease,
        transform 300ms ease;
}

.shell-sidebar.is-expanded .shell-profile-trigger,
.shell-sidebar.is-mobile-open .shell-profile-trigger {
    padding-left: 0.6rem;
}

.shell-profile-trigger:hover,
.shell-profile-trigger.is-active {
    background: rgba(15, 23, 42, 0.04);
    box-shadow:
        0 12px 28px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.6);
    transform: translateY(-1px);
}

.shell-profile-avatar {
    --shell-avatar-card-bg:
        radial-gradient(circle at 30% 12%, rgba(255, 255, 255, 0.9), transparent 34%),
        linear-gradient(135deg, transparent 0 35%, rgba(207, 181, 105, 0.2) 35% 47%, transparent 47% 100%),
        linear-gradient(45deg, transparent 0 43%, rgba(222, 179, 47, 0.92) 43% 55%, transparent 55% 100%),
        linear-gradient(180deg, #fffdf7, #eee2c2);
    position: relative;
    width: 2.5rem;
    height: 2.5rem;
    padding: 0.15rem;
    border: 1px solid rgba(221, 178, 45, 0.54);
    border-radius: 0.95rem;
    background: var(--shell-avatar-card-bg);
    color: #2f2b22;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    line-height: 1;
    font-weight: 950;
    box-shadow:
        0 0 0 2px rgba(255, 255, 255, 0.68),
        0 9px 18px rgba(142, 106, 21, 0.16),
        inset 0 1px 0 rgba(255, 255, 255, 0.72);
    flex-shrink: 0;
    overflow: hidden;
}

.shell-profile-avatar img {
    position: relative;
    z-index: 1;
    width: 100%;
    height: 100%;
    border-radius: 0.72rem;
    background: var(--shell-avatar-card-bg);
    object-fit: cover;
    box-shadow: inset 0 0 0 1px rgba(142, 106, 21, 0.1);
}

.shell-profile-avatar span {
    position: relative;
    z-index: 1;
    display: grid;
    width: 100%;
    height: 100%;
    place-items: center;
    border-radius: 0.72rem;
    background: var(--shell-avatar-card-bg);
}

.shell-avatar-skeleton {
    position: absolute !important;
    inset: 0.15rem;
    z-index: 2 !important;
    overflow: hidden;
    border-radius: 0.72rem;
    background:
        linear-gradient(110deg, transparent 0 30%, rgba(255, 255, 255, 0.62) 42%, transparent 56%),
        linear-gradient(180deg, rgba(231, 218, 182, 0.52), rgba(248, 250, 252, 0.74));
    background-size:
        220% 100%,
        100% 100%;
    animation: shellAvatarSkeletonSweep 1.05s ease-in-out infinite;
}

@keyframes shellAvatarSkeletonSweep {
    from {
        background-position:
            140% 0,
            0 0;
    }
    to {
        background-position:
            -80% 0,
            0 0;
    }
}

.shell-profile-content {
    flex: 1;
    text-align: left;
    overflow: hidden;
}

.shell-profile-name {
    color: var(--slate-900);
    font-size: 0.875rem;
    line-height: 1;
    font-weight: 850;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.shell-profile-meta {
    margin-top: 0.28rem;
    color: var(--slate-500);
    font-size: 0.625rem;
    line-height: 1;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.shell-profile-chevron {
    margin-left: auto;
    color: var(--slate-400);
    font-size: 0.82rem;
    opacity: 0.88;
    transition:
        opacity 260ms ease,
        transform 380ms var(--ease-shell);
}

.shell-sidebar:not(.is-expanded):not(.is-mobile-open) .shell-profile-chevron {
    opacity: 0;
    transform: translateX(-0.35rem);
}

.shell-profile-menu {
    position: absolute;
    left: 0.75rem;
    right: 0.75rem;
    bottom: calc(100% + 0.55rem);
    z-index: 50;
    padding: 0.45rem;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.98);
    border: 1px solid rgba(226, 232, 240, 0.86);
    /* backdrop-filter: blur(18px); */
    /* -webkit-backdrop-filter: blur(18px); */
    box-shadow: 0 28px 60px rgba(15, 23, 42, 0.18);
    opacity: 0;
    visibility: hidden;
    transform: translateY(1rem) scale(0.95);
    transform-origin: bottom;
    pointer-events: none;
    transition:
        opacity 420ms var(--ease-shell),
        visibility 420ms var(--ease-shell),
        transform 420ms var(--ease-shell);
}

.shell-profile-menu.is-visible {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}

.shell-profile-menu__item {
    width: 100%;
    min-height: 2.5rem;
    padding: 0 0.8rem;
    border-radius: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--slate-700);
    font-size: 0.875rem;
    line-height: 1;
    font-weight: 600;
    text-align: left;
    transition:
        background 200ms ease,
        color 200ms ease;
}

.shell-profile-menu__item i {
    color: var(--slate-400);
    font-size: 1rem;
    transition: color 200ms ease;
}

.shell-profile-menu__item:hover {
    background: var(--slate-50);
    color: var(--blue);
}

.shell-profile-menu__item.is-active {
    background: rgba(239, 246, 255, 0.86);
    color: var(--blue-700);
    font-weight: 800;
}

.shell-profile-menu__item:hover i {
    color: var(--blue);
}

.shell-profile-menu__item.is-active i {
    color: var(--blue);
}

.shell-profile-menu__item.is-danger {
    color: #dc2626;
    font-weight: 750;
}

.shell-profile-menu__item.is-danger i {
    color: #dc2626;
}

.shell-profile-menu__item.is-danger:hover {
    background: #fef2f2;
    color: #dc2626;
}

.shell-profile-divider {
    height: 1px;
    background: var(--slate-100);
    margin: 0.35rem 0.45rem;
}

/* ================= UTILITIES ================= */
.shell-rotate {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    line-height: 1;
    transition: transform 360ms var(--ease-shell);
}

.shell-rotate.is-rotated {
    transform: rotate(180deg);
}

.shell-rotate.is-rotated-right {
    transform: rotate(90deg);
}

.shell-scrollbar::-webkit-scrollbar {
    width: 5px;
}

.shell-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.shell-scrollbar::-webkit-scrollbar-thumb {
    background: var(--slate-300);
    border-radius: 999px;
}

.shell-scrollbar::-webkit-scrollbar-thumb:hover {
    background: var(--slate-400);
}

.shell-sidebar button:focus-visible {
    outline: 2px solid rgba(37, 99, 235, 0.28);
    outline-offset: 2px;
}

@media (max-width: 991.98px) {
    .shell-sidebar {
        width: min(20rem, 86vw);
        transform: translateX(-100%);
        transition: transform 320ms ease;
    }

    .shell-sidebar.is-mobile-open {
        transform: translateX(0);
    }
}
</style>
