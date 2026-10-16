<template>
    <div class="master-data-manager">
        <div v-if="!isSystemAdmin" class="alert alert-warning py-2 px-3 mb-3">
            Master Data hanya dapat diakses oleh System Admin.
        </div>

        <ToggleSection v-if="isSystemAdmin" :tabs="masterTabs" :activeTab="activeTab" @tab-change="onTabChange">
            <!-- Roles Tab -->
            <template #roles>
                <RoleCrudPanel :allowedModules="allowedModules" :loading="loading" />
            </template>

            <!-- Scopes Tab -->
            <template #scopes>
                <ScopeCrudPanel :loading="loading" />
            </template>

            <!-- Permissions Tab -->
            <template #permissions>
                <PermissionCrudPanel :loading="loading" />
            </template>
        </ToggleSection>
    </div>
</template>

<script>
import ToggleSection from '@/components/legacy/toggleSectionV2.vue';
import RoleCrudPanel from './RoleCrudPanel.vue';
import ScopeCrudPanel from './ScopeCrudPanel.vue';
import PermissionCrudPanel from './PermissionCrudPanel.vue';

export default {
    name: 'MasterDataManager',
    components: { ToggleSection, RoleCrudPanel, ScopeCrudPanel, PermissionCrudPanel },
    props: {
        allowedModules: { type: Array, default: () => [] },
        loading: { type: Boolean, default: false },
        scope: { type: Object, default: () => ({}) },
    },
    computed: {
        isSystemAdmin() {
            return Boolean(this.scope?.isSystemAdmin);
        },
    },
    data() {
        return {
            activeTab: 'roles',
            masterTabs: [
                { id: 'roles', label: 'Roles', icon: 'bi bi-shield-check', count: null },
                { id: 'scopes', label: 'Data Scopes', icon: 'bi bi-funnel', count: null },
                { id: 'permissions', label: 'Permissions', icon: 'bi bi-key', count: null },
            ],
        };
    },
    methods: {
        onTabChange(tabId) {
            this.activeTab = tabId;
        },
    },
};
</script>

<style scoped>
.master-data-manager {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
