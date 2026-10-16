<template>
    <div class="sop-jenis-stats mb-4">
        <HcisStatGroup :cols="3" gap="md">
            <!-- 1. Total Jenis Dokumen -->
            <HcisStatCard
                label="Total Jenis Dokumen"
                :value="total"
                suffix="Jenis"
                caption="Klasifikasi hirarki mutu aktif dalam sistem"
                icon="diagram-3-fill"
                sentiment="brand"
                appearance="crystal"
                pattern="grid"
                :loading="loading"
            />

            <!-- 2. Sebaran Terpakai (Utilisasi) -->
            <HcisStatCard
                label="Sebaran Terpakai"
                :value="computedUsageRate"
                suffix="%"
                :caption="`${usedCount} dari ${total} jenis telah digunakan dokumen`"
                icon="pie-chart-fill"
                sentiment="teal"
                appearance="crystal"
                pattern="dots"
                :loading="loading"
            />

            <!-- 3. Kelengkapan Prefix Penomoran -->
            <HcisStatCard
                label="Standar Penomoran"
                :value="prefixCaptionValue"
                :caption="prefixCaptionSubtitle"
                :icon="withPrefix === total && total > 0 ? 'check-circle-fill' : 'hash'"
                :sentiment="withPrefix === total && total > 0 ? 'success' : 'purple'"
                appearance="crystal"
                pattern="rings"
                :loading="loading"
            />
        </HcisStatGroup>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisStatGroup, HcisStatCard } from '@/components/ui';

defineOptions({ name: 'SopJenisDokumenStats' });

const props = defineProps({
    total: {
        type: Number,
        default: 0,
    },
    usedCount: {
        type: Number,
        default: 0,
    },
    withPrefix: {
        type: Number,
        default: 0,
    },
    usageRate: {
        type: Number,
        default: 0,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const computedUsageRate = computed(() => {
    if (props.usageRate !== undefined && props.usageRate !== null && props.usageRate > 0) {
        return props.usageRate;
    }
    if (!props.total || props.total <= 0) return 0;
    return Math.round((props.usedCount / props.total) * 100);
});

const prefixCaptionValue = computed(() => {
    if (props.total <= 0) return '0 / 0';
    return `${props.withPrefix} / ${props.total}`;
});

const prefixCaptionSubtitle = computed(() => {
    if (props.total <= 0) return 'Belum ada data jenis dokumen';
    if (props.withPrefix === props.total) {
        return 'Seluruh jenis memiliki prefix kode cetak';
    }
    const diff = props.total - props.withPrefix;
    return `${diff} jenis belum memiliki prefix nomor dokumen`;
});
</script>

<style scoped>
.sop-jenis-stats {
    width: 100%;
}
</style>
