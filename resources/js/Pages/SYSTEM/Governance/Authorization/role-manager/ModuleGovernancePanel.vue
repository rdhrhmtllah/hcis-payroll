<template>
    <div class="module-governance-panel">
        <ToggleSection :tabs="subTabs" :activeTab="activeSubTab" @tab-change="activeSubTab = $event">
            <template #grupFitur>
                <GrupFiturCrud />
            </template>
            <template #menuBinding>
                <MenuBindingPanel />
            </template>
            <template #capabilities>
                <CapabilityBinderPanel />
            </template>
            <template #resourceAccess>
                <ResourceAccessPanel :scope="scope" />
            </template>
            <template #pageAccessManager>
                <PageAccessManagerPanel />
            </template>
            <template #delegationScope>
                <DelegationScopePanel />
            </template>
        </ToggleSection>
    </div>
</template>

<script>
import ToggleSection from '@/components/legacy/toggleSectionV2.vue';
import GrupFiturCrud from './GrupFiturCrud.vue';
import MenuBindingPanel from './MenuBindingPanel.vue';
import CapabilityBinderPanel from './CapabilityBinderPanel.vue';
import ResourceAccessPanel from './ResourceAccessPanel.vue';
import PageAccessManagerPanel from './PageAccessManagerPanel.vue';
import DelegationScopePanel from './DelegationScopePanel.vue';

export default {
    name: 'ModuleGovernancePanel',
    components: {
        ToggleSection,
        GrupFiturCrud,
        MenuBindingPanel,
        CapabilityBinderPanel,
        ResourceAccessPanel,
        PageAccessManagerPanel,
        DelegationScopePanel,
    },
    props: {
        scope: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        return {
            activeSubTab: 'grupFitur',
            subTabs: [
                { id: 'grupFitur', label: 'Grup Fitur', icon: 'bi bi-collection', count: null },
                { id: 'menuBinding', label: 'Menu Binding', icon: 'bi bi-link-45deg', count: null },
                { id: 'capabilities', label: 'Capability Binder', icon: 'bi bi-toggles', count: null },
                { id: 'resourceAccess', label: 'Role & Capability', icon: 'bi bi-shield-lock', count: null },
                { id: 'pageAccessManager', label: 'Page Access Manager', icon: 'bi bi-menu-button-wide', count: null },
                { id: 'delegationScope', label: 'Delegasi & Scope', icon: 'bi bi-person-lock', count: null },
            ],
        };
    },
};
</script>

<style scoped>
.module-governance-panel {
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
