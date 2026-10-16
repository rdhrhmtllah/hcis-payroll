<template>
    <div class="shell-sidebar-backdrop" :class="{ 'is-visible': isSidebarVisible }" @click="handleOutsideClick"></div>

    <aside
        ref="sidebarRoot"
        class="shell-sidebar"
        :class="{
            'is-expanded': isSidebarVisible,
            'is-mobile-open': shell.state.mobileSidebarOpen,
        }"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
        @click="handleSidebarBodyClick"
    >
        <div class="shell-sidebar__inner">
            <!-- BRAND -->
            <div class="shell-sidebar__brand">
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
            <nav class="shell-nav shell-scrollbar" @click.stop>
                <button
                    class="shell-nav-item shell-btn"
                    :class="{ 'is-active': isHomeActive }"
                    type="button"
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
                    <div class="shell-section-label">Platform Utama</div>
                </div>

                <div v-for="module in filteredModules" :key="module.id" class="shell-module">
                    <div class="shell-module-row">
                        <button
                            class="shell-nav-item shell-btn"
                            :class="{ 'is-active': isModuleActive(module) }"
                            type="button"
                            @click.stop="handleModuleSingleClick(module)"
                            @dblclick.stop="handleModuleDoubleClick(module)"
                        >
                            <span class="shell-nav-icon">
                                <i :class="module.icon"></i>
                            </span>

                            <span class="shell-nav-content" :class="{ 'is-hidden': !isSidebarVisible }">
                                <span class="shell-nav-title">{{ module.name }}</span>
                                <span class="shell-nav-subtitle">{{ module.label }}</span>
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
                            <div v-for="group in module.groups" :key="group.id" class="shell-submenu-group">
                                <button
                                    class="shell-submenu-toggle shell-btn"
                                    type="button"
                                    :class="{ 'is-open': Boolean(openGroups[group.id]) }"
                                    @click.stop="toggleGroup(module.id, group.id)"
                                >
                                    <span class="text-truncate">{{ group.title }}</span>
                                    <i
                                        class="bi bi-chevron-right shell-rotate"
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
                        </div>
                    </div>
                </div>
            </nav>

            <!-- PROFILE -->
            <div class="shell-sidebar__profile" @click.stop>
                <div
                    class="shell-profile-menu"
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
                        {{ userInitials }}
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
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useShellState } from '../../composables/useShellState';

const shell = useShellState();
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
let moduleClickTimer = null;

const userName = computed(() => props.user?.name || props.user?.username || 'User');
const userInitials = computed(() => props.user?.initials || buildInitials(userName.value));
const departmentLabel = computed(() => props.user?.department || props.user?.job_title || 'Department belum diatur');
const modules = computed(() => props.navigation?.modules || []);
const homeItem = computed(() => props.navigation?.home || { url: '/homepage', title: 'Home' });
const subsidiaries = computed(() => props.brand?.subsidiaries || []);

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
            const moduleMatches = [module.code, module.name, module.label].some((value) =>
                String(value || '')
                    .toLowerCase()
                    .includes(query),
            );

            const groups = (module.groups || [])
                .map((group) => {
                    const groupMatches = String(group.title || '')
                        .toLowerCase()
                        .includes(query);

                    const items = (group.items || []).filter((item) =>
                        [item.title, item.jenisPage].some((value) =>
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
    if (!shell.state.isMobile) {
        shell.toggleDesktopSidebarLock();
    }
}

function toggleModule(moduleId) {
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
    openModules[moduleId] = true;
    const nextState = !openGroups[groupId];
    clearOpenState(openGroups);

    if (nextState) {
        openGroups[groupId] = true;
    }

    rememberModuleGroup(moduleId, groupId);
}

function handleHomeClick() {
    shell.setActiveModule('HOME');
    shell.setActiveSubMenu(null);
    shell.closeAllPanels();
    router.visit(homeItem.value?.url || '/homepage');
}

function visitUrl(url) {
    if (!url) {
        return;
    }

    shell.closeAllPanels();
    router.visit(url);
}

function handleModuleSingleClick(module) {
    if (moduleClickTimer) {
        window.clearTimeout(moduleClickTimer);
    }

    moduleClickTimer = window.setTimeout(() => {
        const isSameModule = shell.state.activeModule === module.code;

        if (isSameModule) {
            restoreModuleState(module);
            moduleClickTimer = null;
            return;
        }

        shell.setActiveModule(module.code);
        shell.setActiveSubMenu(null);
        visitUrl(module.landingUrl);
        moduleClickTimer = null;
    }, 220);
}

function handleModuleDoubleClick(module) {
    if (moduleClickTimer) {
        window.clearTimeout(moduleClickTimer);
        moduleClickTimer = null;
    }

    shell.setActiveModule(module.code);
    toggleModule(module.id);
}

function activateSubMenu(module, group, item) {
    shell.setActiveModule(module.code);
    shell.setActiveSubMenu(item.jenisPage);
    openModules[module.id] = true;
    openGroups[group.id] = true;
    rememberModuleGroup(module.id, group.id);
    visitUrl(item.url);
}

function handleBrandClick() {
    if (shell.state.isMobile) {
        shell.toggleMobileSidebar();
        return;
    }

    handleHomeClick();
}

function toggleProfileMenu() {
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
    shell.resetInteractionState();
    shell.closeMobileSidebar();
    shell.state.sidebarLocked = false;
    shell.state.sidebarExpanded = false;

    if (!url) {
        return;
    }

    if (url === '/logout') {
        router.post(
            '/logout',
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    shell.resetInteractionState();
                    shell.closeMobileSidebar();
                },
            },
        );
        return;
    }

    router.visit(url, {
        preserveScroll: true,
        preserveState: false,
    });
}

function handleOutsideClick(event) {
    if (!sidebarRoot.value) {
        return;
    }

    if (!sidebarRoot.value.contains(event.target)) {
        shell.resetInteractionState();

        if (shell.state.isMobile) {
            shell.closeMobileSidebar();
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

onMounted(() => {
    document.addEventListener('mousedown', handleOutsideClick);
});

onBeforeUnmount(() => {
    if (moduleClickTimer) {
        window.clearTimeout(moduleClickTimer);
    }

    document.removeEventListener('mousedown', handleOutsideClick);
});
</script>

<style scoped>
.bi {
    display: inline-table !important;
}
</style>

<style scoped>
.shell-sidebar,
.shell-sidebar * {
    box-sizing: border-box;
}

.shell-sidebar {
    --sidebar-collapsed-width: 5rem;
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
    z-index: 1040;
    width: var(--sidebar-collapsed-width);
    height: 100vh;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.52) 0%, rgba(248, 250, 252, 0.52) 100%);
    border-right: 1px solid rgba(226, 232, 240, 0.72);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow:
        18px 0 48px rgba(37, 99, 235, 0.08),
        0 25px 70px rgba(15, 23, 42, 0.14);
    transition: width 620ms var(--ease-shell);
    will-change: width;
    overflow: hidden;
    cursor: pointer;
    color: var(--slate-900);
    font-size: 13px;
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
    backdrop-filter: blur(2px);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition:
        opacity 500ms ease,
        visibility 500ms ease;
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

/* ================= BRAND ================= */
.shell-sidebar__brand {
    min-height: 6.875rem;
    padding: 1rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.6);
    display: flex;
    flex-direction: column;
    justify-content: center;
    overflow: hidden;
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
        box-shadow 300ms ease;
}

.shell-sidebar.is-expanded .shell-brand-card,
.shell-sidebar.is-mobile-open .shell-brand-card {
    justify-content: center;
}

.shell-brand-card:hover {
    background: rgba(255, 255, 255, 0.42);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4);
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
        width 500ms ease,
        height 500ms ease,
        border-radius 500ms ease,
        transform 500ms ease,
        box-shadow 500ms ease,
        border-color 500ms ease;
    isolation: isolate;
}

.shell-brand-card__logo::before {
    content: '';
    position: absolute;
    inset: -30%;
    width: 72%;
    left: -85%;
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0%,
        /* Transparan di awal */ rgba(255, 255, 255, 0) 40%,
        /* Tetap transparan sampai hampir tengah */ rgba(255, 255, 255, 0.6) 50%,
        /* Cahaya shimmer yang tajam di tengah */ rgba(255, 255, 255, 0) 60%,
        /* Kembali transparan dengan cepat */ rgba(255, 255, 255, 0) 100% /* Transparan sampai akhir */
    );
    transform: skewX(-18deg);
    animation: brandShimmer 2.8s linear infinite;
    opacity: 1;
    pointer-events: none;
    mix-blend-mode: screen;
    filter: blur(0.5px);
    z-index: 0;
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
        0 0 24px rgba(99, 102, 241, 0.22);
    opacity: 0.95;
    animation: brandGlow 2.8s ease-in-out infinite;
    pointer-events: none;
    z-index: 0;
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
        left: -85%;
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
        left: 125%;
        opacity: 0;
    }
}

@keyframes brandGlow {
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
    mix-blend-mode: multiply;
}

.shell-brand-image {
    padding: 3px;
    border: none !important;
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
        opacity 620ms var(--ease-shell),
        transform 620ms var(--ease-shell),
        max-width 620ms var(--ease-shell);
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
    /* overflow: hidden; */
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0;
    transform: translateY(-0.35rem);
    will-change: max-height, opacity, transform, margin-top;
    transition:
        max-height 620ms var(--ease-shell),
        opacity 520ms ease,
        margin-top 620ms var(--ease-shell),
        transform 620ms var(--ease-shell);
}

.shell-mini-brand-row.is-visible {
    max-height: 2rem;
    opacity: 1;
    pointer-events: auto;
    margin-top: 0.5rem;
    transform: translateY(0);
    transition:
        max-height 620ms var(--ease-shell),
        opacity 520ms ease 80ms,
        margin-top 620ms var(--ease-shell),
        transform 620ms var(--ease-shell);
}

.shell-mini-brand {
    position: relative;
    flex: 1;
    height: 2rem;
    border-radius: 0.7rem;
    border: 1px solid rgba(191, 219, 254, 0.38);
    background: rgba(255, 255, 255, 0.24);
    backdrop-filter: blur(14px) saturate(1.12);
    -webkit-backdrop-filter: blur(14px) saturate(1.12);
    box-shadow:
        0 10px 22px rgba(15, 23, 42, 0.05),
        inset 0 1px 0 rgba(255, 255, 255, 0.6),
        inset 0 -10px 18px rgba(79, 70, 229, 0.06);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
    transition:
        border-color 300ms ease,
        box-shadow 300ms ease,
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
            rgba(255, 255, 255, 0.82),
            rgba(255, 255, 255, 0.18) 45%,
            rgba(219, 234, 254, 0.16) 100%
        ),
        linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(191, 219, 254, 0.14));
    opacity: 0.72;
    transition:
        opacity 300ms ease,
        transform 300ms ease;
    pointer-events: none;
}

.shell-mini-brand > * {
    position: relative;
    z-index: 1;
}

.shell-mini-brand:hover {
    border-color: rgba(147, 197, 253, 0.55);
    box-shadow:
        0 12px 24px rgba(15, 23, 42, 0.06),
        inset 0 1px 0 rgba(255, 255, 255, 0.72),
        inset 0 -10px 18px rgba(79, 70, 229, 0.08);
    transform: translateY(-1px);
}

.shell-mini-brand:hover::before {
    opacity: 1;
    transform: scale(1.02);
}

/* ================= NAV ================= */
.shell-nav {
    flex: 1;
    padding: 0.75rem;
    overflow-y: auto;
    overflow-x: hidden;
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
        box-shadow 300ms ease,
        transform 300ms ease;
    overflow: hidden;
}

.shell-nav-item:hover {
    background: rgba(255, 255, 255, 0.42);
    color: var(--blue);
}

.shell-nav-item.is-active {
    background: linear-gradient(135deg, rgba(239, 246, 255, 0.88), rgba(255, 255, 255, 0.62));
    color: var(--blue-700);
    box-shadow:
        0 10px 22px rgba(37, 99, 235, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.45);
}

.shell-nav-icon {
    width: 3.5rem;
    /* min-width: 3.5rem; */
    /* height: 3rem; */
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
    max-width: 0;
}

.shell-sidebar.is-expanded .shell-nav-content,
.shell-sidebar.is-mobile-open .shell-nav-content {
    max-width: 12rem;
}

.shell-nav-title {
    max-width: 11.5rem;
    color: var(--slate-900);
    font-size: 0.875rem;
    line-height: 1;
    font-weight: 850;
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
    transform: translateY(-0.45rem);
    overflow: hidden;
    transition:
        height 620ms var(--ease-shell),
        opacity 320ms ease,
        margin 620ms var(--ease-shell),
        transform 620ms var(--ease-shell);
}

.shell-section-label-wrap.is-visible {
    height: 2rem;
    opacity: 1;
    margin-top: 0.9rem;
    margin-bottom: 0.35rem;
    transition:
        height 620ms var(--ease-shell),
        opacity 320ms ease 110ms,
        margin 620ms var(--ease-shell),
        transform 620ms var(--ease-shell);
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
    right: 0.15rem;
    top: 50%;
    width: 1.75rem;
    height: 1.75rem;
    margin: 0;
    border-radius: 999px;
    color: var(--slate-400);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
    opacity: 0;
    transform: translateY(-50%) translateX(-0.35rem) scale(0.9);
    pointer-events: none;
    transition:
        opacity 240ms ease,
        transform 520ms var(--ease-shell),
        background 300ms ease,
        color 300ms ease,
        pointer-events 0s linear 520ms;
}

.shell-expand-btn.is-visible {
    opacity: 1;
    transform: translateY(-50%) translateX(0) scale(1);
    pointer-events: auto;
    transition:
        opacity 200ms ease 120ms,
        transform 520ms var(--ease-shell),
        background 300ms ease,
        color 300ms ease,
        pointer-events 0s;
}

.shell-expand-btn:hover,
.shell-expand-btn:has(.is-rotated) {
    background: var(--blue-100);
    color: var(--blue);
}

/* ================= SUBMENU ================= */
.shell-submenu-wrapper {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transform: translateY(-0.35rem);
    overflow: hidden;
    margin-left: 3.5rem;
    margin-top: 0;
    border-left: 1px solid var(--slate-100);
    transition:
        grid-template-rows 520ms var(--ease-shell),
        opacity 240ms ease,
        transform 520ms var(--ease-shell),
        margin-top 520ms var(--ease-shell);
}

.shell-submenu-wrapper.is-open {
    grid-template-rows: 1fr;
    opacity: 1;
    transform: translateY(0);
    margin-top: 0.25rem;
    transition:
        grid-template-rows 520ms var(--ease-shell),
        opacity 220ms ease 80ms,
        transform 520ms var(--ease-shell),
        margin-top 520ms var(--ease-shell);
}

.shell-submenu-wrapper__inner {
    min-height: 0;
    overflow: hidden;
}

.shell-submenu-group {
    margin-bottom: 0.2rem;
}

.shell-submenu-toggle {
    width: 100%;
    min-height: 2.25rem;
    padding: 0 0.7rem 0 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    color: var(--slate-400);
    font-size: 0.68rem;
    line-height: 1;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    transition: color 300ms ease;
}

.shell-submenu-toggle:hover,
.shell-submenu-toggle.is-open {
    color: var(--blue);
}

.shell-submenu-items {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    overflow: hidden;
    margin-top: 0;
    transform: translateY(-0.2rem);
    transition:
        grid-template-rows 380ms var(--ease-shell),
        opacity 220ms ease,
        margin-top 380ms var(--ease-shell),
        transform 380ms var(--ease-shell);
}

.shell-submenu-items.is-open {
    grid-template-rows: 1fr;
    opacity: 1;
    margin-top: 0.1rem;
    padding-bottom: 0.25rem;
    transform: translateY(0);
    transition:
        grid-template-rows 380ms var(--ease-shell),
        opacity 220ms ease 70ms,
        margin-top 380ms var(--ease-shell),
        transform 380ms var(--ease-shell);
}

.shell-submenu-items__inner {
    min-height: 0;
    overflow: hidden;
}

.shell-submenu-item {
    width: calc(100% - 0.35rem);
    min-height: 2rem;
    margin-left: 0.2rem;
    padding: 0 0.75rem 0 1.45rem;
    border-radius: 0.7rem;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    color: var(--slate-500);
    font-size: 0.75rem;
    line-height: 1;
    font-weight: 600;
    transition:
        background 300ms ease,
        color 300ms ease;
}

.shell-submenu-item:hover {
    background: var(--slate-50);
    color: var(--slate-900);
}

.shell-submenu-item.is-active {
    background: rgba(239, 246, 255, 0.74);
    color: var(--blue-700);
    font-weight: 800;
}

.shell-submenu-dot {
    width: 0.36rem;
    height: 0.36rem;
    border-radius: 999px;
    background: var(--slate-200);
    flex-shrink: 0;
    transition:
        background 300ms ease,
        transform 300ms ease;
}

.shell-submenu-item:hover .shell-submenu-dot {
    background: var(--slate-400);
}

.shell-submenu-item.is-active .shell-submenu-dot {
    background: var(--blue);
    transform: scale(1.25);
}

/* ================= PROFILE ================= */
.shell-sidebar__profile {
    position: relative;
    padding: 0.75rem;
    border-top: 1px solid rgba(226, 232, 240, 0.52);
    background: rgba(255, 255, 255, 0.42);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
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
        box-shadow 300ms ease,
        transform 300ms ease;
}

.shell-sidebar.is-expanded .shell-profile-trigger,
.shell-sidebar.is-mobile-open .shell-profile-trigger {
    padding-left: 0.6rem;
}

.shell-profile-trigger:hover,
.shell-profile-trigger.is-active {
    background: rgba(255, 255, 255, 0.68);
    box-shadow:
        0 12px 28px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.6);
    transform: translateY(-1px);
}

.shell-profile-avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #2563eb, #4f46e5);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    line-height: 1;
    font-weight: 950;
    box-shadow: 0 10px 22px rgba(37, 99, 235, 0.28);
    flex-shrink: 0;
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
    overflow: hidden;
    text-overflow: ellipsis;
}

.shell-profile-meta {
    margin-top: 0.28rem;
    color: var(--slate-500);
    font-size: 0.625rem;
    line-height: 1;
    font-weight: 600;
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
        transform 420ms var(--ease-shell);
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
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(226, 232, 240, 0.86);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
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

.shell-profile-menu__item:hover i {
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
    transition: transform 420ms var(--ease-shell);
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
