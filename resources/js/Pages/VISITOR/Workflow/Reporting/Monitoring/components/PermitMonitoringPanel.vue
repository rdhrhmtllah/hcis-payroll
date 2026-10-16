<template>
    <div class="panel-domain">
        <!-- ══ SINGLE RESPONSIVE TABS NAVIGATION ══
             Desktop (>= 768px): variant="header", orientation="horizontal", block (persis Pengajuan Permit)
             Mobile (< 768px): variant="segmented", orientation="vertical" -> useMobilePicker (persis Verifikator Permit)
        -->
        <div class="panel-domain__tabs-wrapper mb-3">
            <HcisTabs
                v-model="subTab"
                :tabs="subTabs"
                :variant="isMobile ? 'segmented' : 'header'"
                :orientation="isMobile ? 'vertical' : 'horizontal'"
                :block="!isMobile"
                size="md"
                aria-label="Navigasi Monitoring Permit"
            />
        </div>

        <Transition name="panel-fade" mode="out-in">
            <div
                v-if="subTab === 'dashboard'"
                id="panel-permit-dashboard"
                key="dashboard"
                role="tabpanel"
                aria-labelledby="tab-dashboard"
            >
                <PermitDashboard />
            </div>
            <div
                v-else
                id="panel-permit-semua"
                key="semua"
                role="tabpanel"
                aria-labelledby="tab-semua"
            >
                <PermitAllMonitoring />
            </div>
        </Transition>
    </div>
</template>

<script>
import { HcisTabs } from '@/components/ui';
import PermitAllMonitoring from './PermitAllMonitoring.vue';
import PermitDashboard from './PermitDashboard.vue';

export default {
    name: 'PermitMonitoringPanel',
    components: { HcisTabs, PermitAllMonitoring, PermitDashboard },
    data() {
        return {
            subTab: 'dashboard',
            isMobile: typeof window !== 'undefined' && window.innerWidth < 768,
            subTabs: [
                {
                    key: 'dashboard',
                    label: 'Dashboard Harian',
                    icon: 'speedometer2',
                },
                {
                    key: 'semua',
                    label: 'All Monitoring',
                    icon: 'list-columns-reverse',
                },
            ],
            mediaQueryList: null,
            handleResize: null,
        };
    },
    mounted() {
        if (typeof window !== 'undefined' && window.matchMedia) {
            this.mediaQueryList = window.matchMedia('(max-width: 767.98px)');
            this.isMobile = this.mediaQueryList.matches;
            this.handleResize = (e) => {
                this.isMobile = e.matches;
            };
            if (this.mediaQueryList.addEventListener) {
                this.mediaQueryList.addEventListener('change', this.handleResize);
            } else if (this.mediaQueryList.addListener) {
                this.mediaQueryList.addListener(this.handleResize);
            }
        }
    },
    beforeUnmount() {
        if (this.mediaQueryList && this.handleResize) {
            if (this.mediaQueryList.removeEventListener) {
                this.mediaQueryList.removeEventListener('change', this.handleResize);
            } else if (this.mediaQueryList.removeListener) {
                this.mediaQueryList.removeListener(this.handleResize);
            }
        }
    },
};
</script>

<style scoped>
.panel-domain {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.panel-domain__tabs-wrapper {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.panel-domain__tabs-wrapper :deep(.hcis-tabs--variant-header) {
    border-bottom: 1px solid var(--hcis-slate-200, #e2e8f0);
}

.panel-fade-enter-active,
.panel-fade-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}

.panel-fade-enter-from {
    opacity: 0;
    transform: translateY(4px);
}

.panel-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
