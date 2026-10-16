<template>
    <div class="rs-panel">
        <div class="rs-panel-head">
            <div class="rs-panel-head-title">
                <span class="sop-eyebrow">{{ eyebrow }}</span>
                <h6 class="sop-h mb-0">{{ judul }}</h6>
            </div>
            <div class="rs-count-wrap">
                <HcisBadge
                    :label="maks ? `${modelValue.length}/${maks}` : `${modelValue.length}`"
                    :variant="maks && modelValue.length >= maks ? 'warning' : 'brand'"
                    size="sm"
                    class="rs-count-pill"
                />
            </div>
        </div>

        <p v-if="keterangan" class="rs-hint">{{ keterangan }}</p>

        <HcisTransfer
            :model-value="modelValue"
            :data="opsi"
            :titles="['Tersedia', 'Dipilih']"
            :filterable="true"
            :draggable="true"
            :disabled="disabled"
            :show-order-badge="true"
            @update:model-value="$emit('update:modelValue', $event)"
        />

        <!-- Galat validasi muncul DI PANEL PENYEBABNYA, bukan sebagai toast di
             pojok layar yang tak menuntun ke mana pun. -->
        <div v-if="galat" class="rs-galat" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>{{ galat }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisTransfer from '@/components/ui/HcisTransfer.vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    /** Entri katalog: { key, label, grup, sensitif, ... } */
    daftar: { type: Array, default: () => [] },
    judul: { type: String, required: true },
    eyebrow: { type: String, default: '' },
    keterangan: { type: String, default: '' },
    maks: { type: Number, default: 0 },
    berkelompok: { type: Boolean, default: false },
    galat: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const opsi = computed(() =>
    props.daftar
        .filter((d) => !d.sensitif && !d.dikunci && !d.locked)
        .map((d) => ({
            key: d.key,
            label: d.label,
        }))
);

/* Diekspos untuk uji komponen; induk tidak memakainya. */
defineExpose({ opsi });

const labelDari = (kunci) => props.daftar.find((d) => d.key === kunci)?.label ?? kunci;
</script>

<style scoped>
.rs-panel {
    margin-bottom: 1.15rem;
}

.rs-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.35rem;
}

.rs-panel-head-title {
    display: flex;
    flex-direction: column;
}

.rs-count-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.55rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: var(--hcis-surface-soft, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    font-size: 0.76rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--hcis-brand-700, #4338ca);
}

.rs-count-pill.is-limit {
    background: #fffbeb;
    border-color: #fde68a;
    color: #b45309;
}

.rs-hint {
    font-size: 0.78rem;
    line-height: 1.4;
    color: var(--hcis-text-muted, #64748b);
    margin: 0 0 0.5rem;
}

:deep(.hcis-transfer) {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

:deep(.hcis-transfer-panel) {
    flex: 1;
    min-width: 0;
    height: 210px;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--hcis-border-medium, #cbd5e1);
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-surface, #ffffff);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
}

:deep(.hcis-transfer-panel__header) {
    padding: 0.35rem 0.6rem;
    font-size: 0.78rem;
    font-weight: 600;
    background: var(--hcis-surface-soft, #f8fafc);
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

:deep(.hcis-transfer-panel__filter) {
    padding: 0.3rem 0.5rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

:deep(.hcis-filter-input) {
    font-size: 0.76rem;
    padding: 0.2rem 0.4rem 0.2rem 1.6rem;
}

:deep(.hcis-transfer-panel__body) {
    flex: 1;
    overflow-y: auto;
    padding: 0.3rem 0.45rem;
    font-size: 0.78rem;
}

:deep(.hcis-transfer-item) {
    padding: 0.2rem 0.3rem;
    font-size: 0.78rem;
}

:deep(.hcis-transfer-actions) {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

:deep(.hcis-transfer-btn) {
    width: 1.75rem;
    height: 1.75rem;
    padding: 0;
}

.rs-galat {
    margin-top: 0.6rem;
    padding: 0.45rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #fef2f2;
    border: 1px solid #fecaca;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--hcis-danger-700, #b91c1c);
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
</style>
