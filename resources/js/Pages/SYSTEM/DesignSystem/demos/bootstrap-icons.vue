<template>
    <!-- Section: Anatomy -->
    <div v-if="section === 'anatomy'" class="ds-icon-demo">
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <i class="bi bi-card-image" /> HcisIcon (Fondasi Ikon Terpadu HCIS)
                </span>
                <span class="ds-card__badge">Format String, Hex, & Token</span>
            </div>

            <div class="ds-card__body">
                <div class="ds-icon-row">
                    <HcisIcon name="calendar-check" size="xl" color="brand" variant="circle" />
                    <HcisIcon name="people-fill" size="xl" color="primary" variant="boxed" />
                    <HcisIcon name="shield-check" size="xl" color="success" variant="circle" />
                    <HcisIcon name="exclamation-triangle-fill" size="xl" color="warning" variant="boxed" />
                    <HcisIcon name="heart-fill" size="xl" color="#ec4899" variant="circle" />
                    <HcisIcon name="arrow-repeat" size="xl" color="brand" spin />
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Variants -->
    <div v-else-if="section === 'variants'" class="ds-stack-demo">
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title"><i class="bi bi-box" /> Varian Container (Plain, Boxed & Circle)</span>
                <span class="ds-card__tag">variant="boxed | circle"</span>
            </div>
            <div class="ds-card__body">
                <div class="ds-icon-row">
                    <HcisIcon name="check-lg" size="lg" color="success" variant="plain" />
                    <HcisIcon name="check-lg" size="lg" color="success" variant="boxed" />
                    <HcisIcon name="check-lg" size="lg" color="success" variant="circle" />

                    <HcisIcon name="star-fill" size="lg" color="warning" variant="plain" />
                    <HcisIcon name="star-fill" size="lg" color="warning" variant="boxed" />
                    <HcisIcon name="star-fill" size="lg" color="warning" variant="circle" />

                    <HcisIcon name="person-fill" size="lg" color="brand" variant="plain" />
                    <HcisIcon name="person-fill" size="lg" color="brand" variant="boxed" />
                    <HcisIcon name="person-fill" size="lg" color="brand" variant="circle" />
                </div>
            </div>
        </div>

        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title"><i class="bi bi-pie-chart-fill" /> Statistik Penggunaan Set Ikon</span>
                <span class="ds-card__tag">HcisTable</span>
            </div>
            <div class="ds-card__body">
                <HcisTable :columns="kolom" :rows="baris" row-key="set">
                    <template #cell-status="{ value }">
                        <HcisBadge :label="value" :variant="varian[value]" size="sm" />
                    </template>
                </HcisTable>
            </div>
        </div>
    </div>

    <!-- Section: States -->
    <div v-else-if="section === 'states'" class="ds-stack-demo">
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title"><i class="bi bi-arrow-repeat" /> Animasi Spin & Pulse</span>
                <span class="ds-card__tag">spin pulse</span>
            </div>
            <div class="ds-card__body">
                <div class="ds-icon-row">
                    <HcisIcon name="arrow-repeat" size="lg" color="brand" spin />
                    <HcisIcon name="gear-fill" size="lg" color="muted" spin />
                    <HcisIcon name="bell-fill" size="lg" color="danger" pulse />
                    <HcisIcon name="heart-fill" size="lg" color="#ec4899" pulse />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { HcisBadge, HcisIcon, HcisTable } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

const kolom = [
    { key: 'set', label: 'Set Ikon' },
    { key: 'berkas', label: 'Berkas Digunakan', align: 'right' },
    { key: 'status', label: 'Status Integrasi' },
];

const baris = [
    { set: 'Bootstrap Icons (bi-*)', berkas: 450, status: 'Resmi' },
    { set: 'Font Awesome (fa-*)', berkas: 20, status: 'Didukung' },
    { set: 'Element Plus Icons', berkas: 0, status: 'Terhubung' },
];

const varian = {
    Resmi: 'success',
    Didukung: 'info',
    Terhubung: 'secondary',
};
</script>

<style scoped>
.ds-icon-demo,
.ds-stack-demo {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    width: 100%;
}

.ds-card {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.ds-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

.ds-card__title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ds-card__title i {
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.ds-card__badge,
.ds-card__tag {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 9999px;
}

.ds-card__badge {
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.ds-card__tag {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    font-family: monospace;
}

.ds-card__body {
    padding: 1rem;
}

.ds-icon-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
</style>
