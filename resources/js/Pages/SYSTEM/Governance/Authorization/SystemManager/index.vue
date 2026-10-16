<template>
    <div class="system-manager-page">
        <!-- Header -->
        <HeaderKpi
            title="System Authorization Manager"
            subtitle="Kelola seluruh role, scope, dan permission di semua modul"
            :dayNight="dayNight"
            :formattedDate="formattedDate"
        />
        <BannerAksesPeran :modul-baru="modulBaru" :tautan="tautanAksesPeran" />

        <!-- Section Navigation -->
        <ToggleSection :tabs="sectionTabs" :activeTab="activeSection" @tab-change="onTabChange">
            <!-- Dashboard -->
            <template #dashboard>
                <DashboardOverview :modules="modules" :scope="scope" :loading="loading.dashboard" />
            </template>

            <!-- User & Roles -->
            <template #users>
                <UserRoleManager
                    :allowedModules="modules"
                    :scope="scope"
                    :showModuleFilter="true"
                    @manage-user="openUserDetail"
                    @save-roles="onRolesSaved"
                />
            </template>

            <!-- Master Data -->
            <template #master>
                <MasterDataManager :allowedModules="modules" :scope="scope" :loading="loading.dashboard" />
            </template>

            <!-- Monitoring -->
            <template #monitoring>
                <MonitoringPanel :modules="modules" :legacyCount="legacyCount" :recentActivity="recentActivity" />
            </template>

            <!-- Module Governance -->
            <template #governance>
                <ModuleGovernancePanel :scope="scope" />
            </template>

            <!-- Access Distribution -->
            <template #distribution>
                <AccessDistributionPanel :scope="scope" />
            </template>

            <!-- Permission Matrix -->
            <template #matrix>
                <PermissionMatrixPanel :scope="scope" />
            </template>
        </ToggleSection>
    </div>
</template>

<script>
import BannerAksesPeran from '../AksesPeran/components/BannerAksesPeran.vue';
import HeaderKpi from '@/components/legacy/header.vue';
import ToggleSection from '@/components/legacy/toggleSectionV2.vue';
import DashboardOverview from '../role-manager/DashboardOverview.vue';
import UserRoleManager from '../role-manager/UserRoleManager.vue';
import MasterDataManager from '../role-manager/MasterDataManager.vue';
import MonitoringPanel from '../role-manager/MonitoringPanel.vue';

// New Components
import ModuleGovernancePanel from '../role-manager/ModuleGovernancePanel.vue';
import AccessDistributionPanel from '../role-manager/AccessDistributionPanel.vue';
import PermissionMatrixPanel from '../role-manager/PermissionMatrixPanel.vue';

export default {
    name: 'SystemManager',
    props: {
        modulBaru: { type: Array, default: () => [] },
        tautanAksesPeran: { type: String, default: null },
        scopeMetadata: {
            type: Object,
            default: () => null,
        },
    },
    components: {
        BannerAksesPeran,
        HeaderKpi,
        ToggleSection,
        DashboardOverview,
        UserRoleManager,
        MasterDataManager,
        MonitoringPanel,
        ModuleGovernancePanel,
        AccessDistributionPanel,
        PermissionMatrixPanel,
    },
    data() {
        return {
            dayNight: new Date().getHours() >= 6 && new Date().getHours() < 18 ? 'Siang' : 'Malam',
            formattedDate: new Date().toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            }),
            activeSection: 'dashboard',
            // PHASE 2.1: All available tabs (will be filtered based on scope)
            allTabs: [
                { id: 'dashboard', label: 'Dashboard', icon: 'bi bi-grid-1x2', count: null },
                { id: 'users', label: 'User & Roles', icon: 'bi bi-people', count: null },
                { id: 'master', label: 'Master Data', icon: 'bi bi-database-gear', count: null },
                { id: 'governance', label: 'Module Governance', icon: 'bi bi-shield-check', count: null },
                { id: 'monitoring', label: 'Monitoring', icon: 'bi bi-activity', count: null },
                { id: 'distribution', label: 'Access Distribution', icon: 'bi bi-send', count: null },
                { id: 'matrix', label: 'Permission Matrix', icon: 'bi bi-grid-3x3-gap', count: null },
            ],
            sectionTabs: [], // Will be populated based on scope
            modules: [],
            legacyCount: 0,
            recentActivity: [],
            // PHASE 2.1: Authorization scope metadata
            scope: this.scopeMetadata || {
                adminTier: 'ADMIN_BIASA',
                isSystemAdmin: false,
                manageableGroupIds: [],
                usablePageTypes: [],
                visibleTabs: [],
                tabVisibility: {},
            },
            loading: {
                dashboard: false,
                scope: true,
            },
        };
    },
    methods: {
        getAllowedTabIds() {
            const availableTabIds = new Set(this.allTabs.map((tab) => tab.id));
            const visibleTabs = Array.isArray(this.scope?.visibleTabs) ? this.scope.visibleTabs : [];

            // Keep only known tabs and remove duplicates while preserving order.
            return [...new Set(visibleTabs)].filter((tabId) => availableTabIds.has(tabId));
        },

        isTabAllowed(tabId) {
            return this.getAllowedTabIds().includes(tabId);
        },

        /**
         * PHASE 2.1: Fetch authorization scope metadata
         * Determines which tabs and features are visible for the current user
         */
        async fetchScopeMetadata() {
            this.loading.scope = true;
            try {
                const res = await fetch('/api/v1/admin/role-manager/scope-metadata');
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }
                const data = await res.json();

                // Validate payload shape before applying
                if (typeof data?.isSystemAdmin !== 'boolean' || !Array.isArray(data?.visibleTabs)) {
                    throw new Error('Invalid scope metadata payload');
                }

                this.scope = data;

                // Filter visible tabs based on scope
                this.filterVisibleTabs();
            } catch (e) {
                console.error('Scope metadata fetch error:', e);
                // Safe fallback: never escalate privilege on fetch failure
                this.scope = this.scopeMetadata || {
                    adminTier: 'ADMIN_BIASA',
                    isSystemAdmin: false,
                    manageableGroupIds: [],
                    usablePageTypes: [],
                    visibleTabs: ['dashboard', 'users'],
                    tabVisibility: {
                        dashboard: true,
                        users: true,
                        master: false,
                        governance: false,
                        monitoring: false,
                        distribution: false,
                        matrix: false,
                    },
                };
                this.filterVisibleTabs();
            } finally {
                this.loading.scope = false;
            }
        },

        /**
         * Filter tabs based on user's authorization scope
         * System Admin sees all tabs; Super Admin sees limited tabs
         */
        filterVisibleTabs() {
            const allowedTabIds = this.getAllowedTabIds();

            // Filter allTabs to only show tabs explicitly allowed by scope.
            this.sectionTabs = this.allTabs.filter((tab) => allowedTabIds.includes(tab.id));

            // Secure fallback: never auto-show all tabs when scope payload is empty/invalid.
            if (!this.sectionTabs.length) {
                this.sectionTabs = this.allTabs.filter((tab) => ['dashboard', 'users'].includes(tab.id));
            }

            // If activeSection is not in visible tabs, switch to first visible tab
            if (this.sectionTabs.length > 0 && !this.sectionTabs.some((tab) => tab.id === this.activeSection)) {
                this.activeSection = this.sectionTabs[0].id;
            }
        },

        onTabChange(tabId) {
            if (!this.isTabAllowed(tabId) && !this.sectionTabs.some((tab) => tab.id === tabId)) {
                this.activeSection = this.sectionTabs[0]?.id || 'dashboard';
                return;
            }

            this.activeSection = tabId;
            if ((tabId === 'dashboard' || tabId === 'monitoring') && this.modules.length === 0) {
                this.fetchDashboard();
            }
        },

        async fetchDashboard() {
            this.loading.dashboard = true;
            try {
                const res = await fetch('/api/v1/admin/role-manager/dashboard-stats');
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }
                const data = await res.json();
                this.modules = data.modules || [];
                this.legacyCount = data.legacyOnlyCount || 0;
                this.recentActivity = data.recentActivity || [];

                // Update tab counts
                const dashboardTab = this.sectionTabs.find((t) => t.id === 'dashboard');
                const usersTab = this.sectionTabs.find((t) => t.id === 'users');
                if (dashboardTab) {
                    dashboardTab.count = this.modules.length;
                }
                const totalUsers = this.modules.reduce((s, m) => s + (m.users || 0), 0);
                if (usersTab) {
                    usersTab.count = totalUsers;
                }
            } catch (e) {
                console.error('Dashboard fetch error:', e);
            } finally {
                this.loading.dashboard = false;
            }
        },

        openUserDetail(user) {
            // UserRoleManager handles detail modal internally
        },

        onRolesSaved(payload) {
            // Refresh dashboard after roles saved
            this.fetchDashboard();
        },
    },
    mounted() {
        // Use initial scope prop when available, then refresh from API
        if (Array.isArray(this.scope?.visibleTabs) && this.scope.visibleTabs.length > 0) {
            this.filterVisibleTabs();
        } else {
            // Ensure initial UI has a safe tab set before async fetch completes.
            this.filterVisibleTabs();
        }

        this.fetchScopeMetadata();
        if (this.activeSection === 'dashboard' || this.activeSection === 'monitoring') {
            this.fetchDashboard();
        }
    },
};
</script>
<style scoped>
.bi {
    display: contents !important;
}
</style>
<style scoped>
@media (max-width: 768px) {
    .system-manager-page {
        padding: 0.75rem;
    }
}
</style>
