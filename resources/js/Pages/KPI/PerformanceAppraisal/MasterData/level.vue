<template>
    <main class="pa-lvm-page">
        <HcisHeader
            title="Level PA"
            kicker="MASTER DATA · LEVEL PA"
            description="Level yang dipakai seluruh matriks PA: salinan level HRIS dan level khusus PA (mis. Officer Specialist) yang tidak ada di HRIS. Orangnya ditetapkan di Master · Koreksi Level PA."
            icon="diagram-3"
            sentiment="brand"
            variant="glass"
            size="md"
            animated
            class="mb-4"
        >
            <template #actions>
                <div class="pa-lvm-actions">
                    <HcisButton
                        label="Koreksi Level PA"
                        variant="secondary"
                        size="sm"
                        leading-icon="person-badge"
                        href="/performance-appraisal/master/level-pa"
                    />
                </div>
            </template>
            <template #tabs>
                <HcisTabs
                    model-value="level"
                    :tabs="daftarTab"
                    variant="header"
                    size="sm"
                    aria-label="Navigasi Master Level"
                    @tab-click="gantiTab"
                />
            </template>
        </HcisHeader>

        <PaDaftarLevelMaster />
    </main>
</template>

<script setup>
import { HcisButton, HcisHeader, HcisTabs } from '@/components/ui';
import PaDaftarLevelMaster from './components/PaDaftarLevelMaster.vue';

defineOptions({ name: 'PerformanceAppraisalLevelMasterPage' });

const daftarTab = [
    { id: 'level', label: 'Master Level PA', icon: 'diagram-3' },
    { id: 'koreksi', label: 'Koreksi Level PA', icon: 'person-badge', href: '/performance-appraisal/master/level-pa' },
];

function gantiTab(tab) {
    if (tab.href) {
        window.location.href = tab.href;
    }
}
</script>

<style scoped>
.pa-lvm-page {
    padding-bottom: 2.5rem;
}

.pa-lvm-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
</style>

