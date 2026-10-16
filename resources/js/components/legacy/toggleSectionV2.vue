<template>
    <div
        class="approval-tabs-container"
        :class="{
            'sidebar-layout': layout === 'sidebar',
            'sidebar-minimized': isMinimized && layout === 'sidebar',
        }"
    >
        <!-- LAYOUT 1: SIDEBAR TABS (Desktop only, width >= 768px) -->
        <div v-if="layout === 'sidebar' && !isMobile" class="desktop-sidebar border-end bg-white">
            <div class="sidebar-header">
                <span class="sidebar-title">{{ title }}</span>
                <button
                    class="btn-toggle-sidebar btn btn-link p-0 text-secondary text-decoration-none d-flex align-items-center justify-content-center"
                    @click="toggleMinimize"
                    :title="isMinimized ? 'Tampilkan Menu' : 'Sembunyikan Menu'"
                >
                    <i
                        class="bi"
                        :class="isMinimized ? 'bi-text-indent-left text-indigo fs-5' : 'bi-text-indent-right fs-5'"
                    ></i>
                </button>
            </div>
            <div class="sidebar-menu">
                <div v-for="(groupTabs, sectionName) in groupedTabs" :key="sectionName" class="sidebar-section mb-3">
                    <div v-if="showSectionHeaders" class="sidebar-section-title px-2 mb-2">
                        {{ sectionName }}
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <button
                            v-for="tab in groupTabs"
                            :key="tab.id"
                            class="sidebar-tab-btn"
                            :class="{ active: activeTab === tab.id }"
                            @click="$emit('tab-change', tab.id)"
                            :title="isMinimized ? tab.label : ''"
                        >
                            <i :class="tab.icon"></i>
                            <span class="sidebar-tab-label">{{ tab.label }}</span>
                            <span class="tab-badge" v-if="tab.count !== null">
                                {{ tab.count }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- LAYOUT 2: STANDARD DESKTOP TABS (Horizontal, default mode) -->
        <div v-else-if="layout !== 'sidebar' && !isMobile" class="desktop-tabs">
            <div class="tabs-header" :style="customHeaderBgColor">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    class="tab-button"
                    :class="{ active: activeTab === tab.id }"
                    @click="$emit('tab-change', tab.id)"
                >
                    <i :class="tab.icon"></i>
                    <span>{{ tab.label }}</span>
                    <span class="tab-badge" v-if="tab.count !== null">
                        {{ tab.count }}
                    </span>
                </button>
            </div>
        </div>

        <!-- LAYOUT FOR MOBILE -->
        <div v-else class="mobile-navigation-wrapper">
            <!-- Mobile Sidebar Mode (Trigger Bar + Bottom Sheet) -->
            <div v-if="layout === 'sidebar'" class="mobile-sidebar-layout">
                <div class="mobile-sidebar-trigger-bar" @click="isMobileMenuOpen = true">
                    <div class="active-tab-info">
                        <i :class="activeTabObj.icon" class="active-tab-icon"></i>
                        <span class="active-tab-label">{{ activeTabObj.label }}</span>
                    </div>
                    <button class="btn-open-mobile-menu">
                        <span>Pilih Menu</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                </div>

                <!-- Bottom Sheet Drawer -->
                <div class="mobile-bottom-sheet-wrapper" :class="{ 'is-open': isMobileMenuOpen }">
                    <div class="bottom-sheet-backdrop" @click="isMobileMenuOpen = false"></div>
                    <div class="bottom-sheet-content">
                        <div class="bottom-sheet-grabber"></div>
                        <div class="bottom-sheet-header">
                            <span class="bottom-sheet-title">{{ title }}</span>
                            <button class="btn-close-sheet" @click="isMobileMenuOpen = false">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="bottom-sheet-body">
                            <div
                                v-for="(groupTabs, sectionName) in groupedTabs"
                                :key="sectionName"
                                class="sheet-section mb-3"
                            >
                                <div v-if="showSectionHeaders" class="sheet-section-title px-2 mb-2">
                                    {{ sectionName }}
                                </div>
                                <div class="d-flex flex-column gap-1">
                                    <button
                                        v-for="tab in groupTabs"
                                        :key="tab.id"
                                        class="sheet-tab-btn"
                                        :class="{ active: activeTab === tab.id }"
                                        @click="selectTabMobile(tab.id)"
                                    >
                                        <i :class="tab.icon"></i>
                                        <span class="sheet-tab-label">{{ tab.label }}</span>
                                        <span class="tab-badge" v-if="tab.count !== null">
                                            {{ tab.count }}
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile Horizontal Mode (Scrollable tab strip) -->
            <div v-else class="mobile-horizontal-layout">
                <div class="mobile-scrollable-header" :style="customHeaderBgColor">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        class="mobile-scroll-button"
                        :class="{ active: activeTab === tab.id }"
                        @click="$emit('tab-change', tab.id)"
                    >
                        <i :class="tab.icon"></i>
                        <span>{{ tab.label }}</span>
                        <span class="tab-badge" v-if="tab.count !== null">
                            {{ tab.count }}
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tab Content Wrapper -->
        <div
            class="tab-content flex-grow-1"
            :class="{ 'sidebar-content': layout === 'sidebar' && !isMobile }"
            :style="customPaddingStyle"
        >
            <slot :name="activeTab"></slot>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ApprovalTabs',
    props: {
        tabs: {
            type: Array,
            required: true,
        },
        activeTab: {
            type: String,
            required: true,
        },
        layout: {
            type: String,
            default: 'horizontal',
        },
        title: {
            type: String,
            default: 'Opsi',
        },
        storageKey: {
            type: String,
            default: null,
        },
        contentPadding: {
            type: [String, Boolean],
            default: null,
        },
        headerBgColor: {
            type: [String, Boolean],
            default: null,
        },
    },
    data() {
        return {
            isMobile: window.innerWidth < 768,
            isMinimized: false,
            isMobileMenuOpen: false,
        };
    },
    computed: {
        resolvedStorageKey() {
            if (this.storageKey) {
                return `toggle_section_minimized_${this.storageKey}`;
            }
            const path = window.location.pathname.replace(/[^a-zA-Z0-9]/g, '_');
            return `toggle_section_minimized_${path}_sidebar`;
        },
        customPaddingStyle() {
            if (this.contentPadding === true) {
                return { padding: '1.5rem' };
            }
            if (this.contentPadding === false) {
                return { padding: '0' };
            }
            if (typeof this.contentPadding === 'string') {
                return { padding: this.contentPadding };
            }
            return {};
        },
        customHeaderBgColor() {
            if (this.headerBgColor === true) {
                return { backgroundColor: 'var(--surface-soft, #f8fafc)' };
            }
            if (this.headerBgColor === false) {
                return { backgroundColor: 'transparent' };
            }
            if (typeof this.headerBgColor === 'string') {
                return { backgroundColor: this.headerBgColor };
            }
            return {};
        },
        groupedTabs() {
            const groups = {};
            this.tabs.forEach((tab) => {
                const sec = tab.section || this.title || 'Opsi';
                if (!groups[sec]) {
                    groups[sec] = [];
                }
                groups[sec].push(tab);
            });
            return groups;
        },
        showSectionHeaders() {
            const keys = Object.keys(this.groupedTabs);
            if (keys.length > 1) return true;
            if (keys.length === 1 && keys[0] !== this.title) return true;
            return false;
        },
        activeTabObj() {
            return this.tabs.find((tab) => tab.id === this.activeTab) || this.tabs[0] || {};
        },
    },
    methods: {
        handleResize() {
            this.isMobile = window.innerWidth < 768;
        },
        toggleMinimize() {
            this.isMinimized = !this.isMinimized;
            localStorage.setItem(this.resolvedStorageKey, this.isMinimized ? 'true' : 'false');
        },
        selectTabMobile(tabId) {
            this.$emit('tab-change', tabId);
            this.isMobileMenuOpen = false;
        },
    },
    mounted() {
        window.addEventListener('resize', this.handleResize);
        if (this.layout === 'sidebar') {
            const stored = localStorage.getItem(this.resolvedStorageKey);
            if (stored !== null) {
                this.isMinimized = stored === 'true';
            }
        }
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
    },
    watch: {
        tabs: {
            immediate: true,
            handler(newTabs) {
                if (newTabs.length > 0 && !this.activeTab) {
                    this.$emit('tab-change', newTabs[0].id);
                }
            },
        },
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #a5b4fc;
    --secondary: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --light: #f8fafc;
    --dark: #1e293b;
    --surface: #ffffff;
    --surface-soft: #f1f5f9;
    --border: #e2e8f0;
    --text: #334155;
    --text-muted: #64748b;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 12px;
    --transition: all 0.3s ease;
    --radius-lg: 16px;
}

.bi {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
}

/* Base tabs wrapper */
.approval-tabs-container {
    background: var(--surface);
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

/* Sidebar mode specific overrides */
.approval-tabs-container.sidebar-layout {
    display: flex;
    flex-direction: row;
    align-items: stretch;
    background: transparent;
    border: none;
    box-shadow: none;
    border-radius: 0;
    margin-bottom: 0;
    overflow: visible;
}

/* Tab content defaults and overrides */
.tab-content {
    padding: 1.5rem;
    background: var(--surface);
    transition: all 0.3s ease;
}

.tab-content.sidebar-content {
    padding: 0;
    background: transparent;
    overflow-y: auto;
}

/* Desktop Horizontal Tabs */
.desktop-tabs {
    border-bottom: 1px solid var(--border);
}

.tabs-header {
    display: flex;
    background: var(--surface-soft);
    padding: 0.5rem;
    gap: 0.25rem;
    overflow: auto;
}

.tab-button {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border: none;
    background: transparent;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    color: var(--text-muted);
    position: relative;
    white-space: nowrap;
}

.tab-button:hover {
    background: rgba(99, 102, 241, 0.05);
    color: var(--primary);
}

.tab-button.active {
    background: var(--primary);
    color: white;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}

.tab-button i {
    font-size: 1rem;
    transition: transform 0.3s ease;
}

.tab-button.active i {
    transform: scale(1.1);
}

.tab-badge {
    background: rgba(255, 255, 255, 0.2);
    color: inherit;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 24px;
    text-align: center;
}

.tab-button:not(.active) .tab-badge {
    background: var(--surface);
    color: var(--text-muted);
    border: 1px solid var(--border);
}

/* Mobile Tabs dropdown wrapper */
.mobile-tabs {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    background: var(--surface-soft);
}

.mobile-tab-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-weight: 500;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236466f1'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1rem;
}

.mobile-tab-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Desktop Sidebar mode elements and animations */
.desktop-sidebar {
    width: 240px;
    flex-shrink: 0;
    overflow: hidden;
    min-height: 550px;
    background-color: #ffffff;
    transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-minimized .desktop-sidebar {
    width: 60px;
}

.sidebar-header {
    position: relative;
    height: 56px;
    margin-bottom: 1rem;
}

.sidebar-title {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.68rem;
    color: #64748b;
    font-family:
        var(--bs-font-sans-serif),
        system-ui,
        -apple-system,
        sans-serif;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.05em;
    opacity: 1;
    transition:
        opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1),
        transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-minimized .sidebar-title {
    opacity: 0;
    transform: translateY(-50%) translateX(-15px);
    pointer-events: none;
}

.btn-toggle-sidebar {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    border-radius: 50%;
    transition:
        right 0.25s cubic-bezier(0.4, 0, 0.2, 1),
        transform 0.25s cubic-bezier(0.4, 0, 0.2, 1),
        background-color 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-toggle-sidebar:hover {
    background-color: #f1f5f9;
}

.sidebar-minimized .btn-toggle-sidebar {
    right: 50%;
    transform: translateY(-50%) translateX(50%);
}

.sidebar-menu {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0 16px;
    transition: padding 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-minimized .sidebar-menu {
    padding: 0 10px;
}

.sidebar-tab-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    height: 40px;
    padding: 0 12px;
    gap: 12px;
    border: none;
    background: transparent;
    color: #475569;
    font-weight: 600;
    font-size: 0.82rem;
    border-radius: 20px;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-sizing: border-box;
}

.sidebar-minimized .sidebar-tab-btn {
    width: 40px;
    height: 40px;
    margin: 0 auto;
    padding: 0;
    border-radius: 50%;
    gap: 0;
    justify-content: center;
}

.sidebar-tab-btn i {
    font-size: 1.1rem;
    color: #64748b;
    transition:
        color 0.2s,
        font-size 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

.sidebar-tab-btn:hover {
    background-color: #f1f5f9;
    color: #1e293b;
}

.sidebar-tab-btn:hover i {
    color: #475569;
}

.sidebar-tab-btn.active {
    background-color: #eef2ff;
    color: #6366f1;
}

.sidebar-tab-btn.active i {
    color: #6366f1;
}

.sidebar-tab-label {
    opacity: 1;
    max-width: 150px;
    overflow: hidden;
    white-space: nowrap;
    transition:
        opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1),
        max-width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-block;
    vertical-align: middle;
}

.sidebar-minimized .sidebar-tab-label {
    opacity: 0;
    max-width: 0 !important;
    width: 0 !important;
    min-width: 0 !important;
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
    pointer-events: none;
}

.sidebar-tab-btn .tab-badge {
    opacity: 1;
    transform: scale(1);
    transition:
        opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1),
        transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-minimized .sidebar-tab-btn .tab-badge {
    opacity: 0;
    transform: scale(0);
    pointer-events: none;
    width: 0 !important;
    min-width: 0 !important;
    max-width: 0 !important;
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
}

.sidebar-minimized .sidebar-tab-btn i {
    font-size: 1.25rem;
}

.btn-toggle-sidebar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    transition: background-color 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-toggle-sidebar:hover {
    background-color: #f1f5f9;
}

.text-xs {
    font-size: 0.68rem;
}
.tracking-wider {
    letter-spacing: 0.05em;
}
.uppercase {
    text-transform: uppercase;
}

/* Responsive and animations */
@media (max-width: 1024px) {
    .tab-button {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
    }

    .tab-button i {
        font-size: 0.9rem;
    }
}

@media (max-width: 768px) {
    .approval-tabs-container.sidebar-layout {
        flex-direction: column;
    }
    .approval-tabs-container {
        border-radius: 12px;
        margin-bottom: 1rem;
    }
    .tab-content {
        padding: 1rem;
    }
}

@media (max-width: 480px) {
    .tab-content {
        padding: 0.75rem;
    }
}

/* Premium Mobile Responsive styles */
.mobile-navigation-wrapper {
    width: 100%;
}

.mobile-sidebar-layout {
    width: 100%;
}

.mobile-sidebar-trigger-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1.25rem;
    background: #ffffff;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.mobile-sidebar-trigger-bar:hover {
    background-color: #f8fafc;
}

.active-tab-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.active-tab-icon {
    font-size: 1.2rem;
    color: var(--primary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.active-tab-label {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text);
}

.btn-open-mobile-menu {
    border: 1px solid var(--border);
    background: #ffffff;
    border-radius: 8px;
    padding: 0.45rem 0.9rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.4rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-open-mobile-menu:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: var(--text);
}

/* Bottom Sheet Wrapper */
.mobile-bottom-sheet-wrapper {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 2000;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    pointer-events: none;
    visibility: hidden;
    transition: visibility 0.3s ease;
}

.mobile-bottom-sheet-wrapper.is-open {
    pointer-events: auto;
    visibility: visible;
}

/* Backdrop */
.bottom-sheet-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(4px);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.mobile-bottom-sheet-wrapper.is-open .bottom-sheet-backdrop {
    opacity: 1;
}

/* Sheet Content */
.bottom-sheet-content {
    position: relative;
    background: #ffffff;
    border-top-left-radius: 20px;
    border-top-right-radius: 20px;
    max-height: 75vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 -10px 25px -5px rgba(0, 0, 0, 0.15);
    transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 2;
    overflow: hidden;
}

.mobile-bottom-sheet-wrapper.is-open .bottom-sheet-content {
    transform: translateY(0);
}

/* Grabber */
.bottom-sheet-grabber {
    width: 40px;
    height: 5px;
    background: #cbd5e1;
    border-radius: 3px;
    margin: 10px auto 5px auto;
    flex-shrink: 0;
}

/* Bottom Sheet Header */
.bottom-sheet-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
}

.bottom-sheet-title {
    font-weight: 700;
    font-size: 0.85rem;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.btn-close-sheet {
    border: none;
    background: #f1f5f9;
    color: #64748b;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.85rem;
    transition: background-color 0.2s;
}

.btn-close-sheet:hover {
    background-color: #e2e8f0;
    color: #0f172a;
}

/* Bottom Sheet Body */
.bottom-sheet-body {
    padding: 1.25rem;
    overflow-y: auto;
    flex-grow: 1;
}

/* Sheet Section Title */
.sheet-section-title {
    font-size: 0.65rem;
    color: #94a3b8;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 0.75rem;
    margin-bottom: 0.5rem;
    padding-left: 0.5rem;
}

/* Tab buttons inside bottom sheet */
.sheet-tab-btn {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border: none;
    background: transparent;
    color: #475569;
    font-weight: 600;
    font-size: 0.9rem;
    border-radius: 12px;
    text-align: left;
    transition: all 0.2s ease-in-out;
    cursor: pointer;
}

.sheet-tab-btn i {
    font-size: 1.1rem;
    color: #64748b;
    width: 20px;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
}

.sheet-tab-btn:hover {
    background-color: #f1f5f9;
    color: #1e293b;
}

.sheet-tab-btn.active {
    background-color: #eef2ff;
    color: #6366f1;
}

.sheet-tab-btn.active i {
    color: #6366f1;
}

/* Mobile Horizontal Layout Styles */
.mobile-horizontal-layout {
    width: 100%;
    border-bottom: 1px solid var(--border);
}

.mobile-scrollable-header {
    display: flex;
    background: var(--surface-soft);
    padding: 0.5rem;
    gap: 0.25rem;
    overflow-x: auto;
    scrollbar-width: none;
}

.mobile-scrollable-header::-webkit-scrollbar {
    display: none;
}

.mobile-scroll-button {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.85rem;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--text-muted);
    white-space: nowrap;
}

.mobile-scroll-button.active {
    background: var(--primary);
    color: white;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.2);
}

.mobile-scroll-button.active i {
    color: white;
}

.mobile-scroll-button:not(.active) .tab-badge {
    background: var(--surface);
    color: var(--text-muted);
    border: 1px solid var(--border);
}

.tab-button {
    animation: fadeInUp 0.3s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.sidebar-section {
    transition: all 0.25s ease-in-out;
}

.sidebar-section-title {
    font-size: 0.65rem;
    color: #94a3b8;
    font-family:
        var(--bs-font-sans-serif),
        system-ui,
        -apple-system,
        sans-serif;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.05em;
    margin-top: 0.5rem;
    margin-bottom: 0.25rem;
    padding-left: 0.5rem;
    opacity: 1;
    max-height: 24px;
    overflow: hidden;
    transition:
        opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1),
        max-height 0.25s cubic-bezier(0.4, 0, 0.2, 1),
        margin 0.25s cubic-bezier(0.4, 0, 0.2, 1),
        padding 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-minimized .sidebar-section-title {
    opacity: 0;
    max-height: 0;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    pointer-events: none;
}

/* Ensure all sidebar, toggle, and tab icons are centered and not broken by global display: contents leaks */
.btn-toggle-sidebar i,
.tab-button i,
.mobile-scroll-button i,
.btn-open-mobile-menu i,
.btn-close-sheet i,
.active-tab-icon {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
}
</style>
