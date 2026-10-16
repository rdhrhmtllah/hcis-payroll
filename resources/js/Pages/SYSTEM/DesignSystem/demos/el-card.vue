<template>
    <div v-if="section === 'anatomy'">
        <HcisAlert
            variant="danger"
            title="Dipensiunkan — jangan dipakai untuk kode baru"
            description="Radius, bayangan, dan padding el-card tidak mengikuti token kita, sehingga ia terlihat asing begitu diletakkan bersebelahan dengan HcisCard."
        />
    </div>

    <div v-else-if="section === 'variants'" class="hcis-grid hcis-grid--2">
        <div class="ds-dont">
            <p class="ds-dont__label">Jangan — el-card</p>
            <el-card class="ds-preview">
                <template #header>Ringkasan bulan ini</template>
                <p class="ds-body">Radius dan bayangannya milik Element.</p>
            </el-card>
        </div>
        <div class="ds-do">
            <p class="ds-do__label">Lakukan — HcisCard</p>
            <HcisCard title="Ringkasan bulan ini">
                <p class="ds-body">Radius xl, bayangan sm, padding dari token.</p>
            </HcisCard>
        </div>
    </div>

    <div v-else-if="section === 'states'">
        <p class="ds-hint">
            Masih terpakai di <strong>11 berkas</strong>. Diganti saat halaman yang memakainya kebetulan disentuh.
        </p>
    </div>
</template>

<script setup>
import { HcisAlert, HcisCard } from '@/components/ui';

defineProps({ section: { type: String, required: true } });
</script>

<style scoped>
.ds-hint { margin: 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-sm); line-height: var(--hcis-line-normal); }
.ds-body { margin: 0; font-size: var(--hcis-font-size-sm); }
.ds-do, .ds-dont { border-radius: var(--hcis-radius-md); padding: var(--hcis-space-4); }
.ds-do { background: var(--hcis-success-50); }
.ds-dont { background: var(--hcis-danger-50); }
.ds-do__label, .ds-dont__label { margin: 0 0 var(--hcis-space-3); font-size: var(--hcis-font-size-xs); font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; }
.ds-do__label { color: var(--hcis-success-700); }
.ds-dont__label { color: var(--hcis-danger-700); }
</style>
