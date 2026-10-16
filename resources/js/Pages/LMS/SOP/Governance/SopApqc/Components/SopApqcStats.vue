<template>
    <div class="sop-apqc-stats mb-4">
        <HcisStatGroup :cols="3" gap="md">
            <!-- 1. Total Kategori Proses APQC -->
            <HcisStatCard
                label="Total Kategori Proses"
                :value="total"
                suffix="Proses"
                caption="Klasifikasi taksonomi APQC PCF aktif"
                icon="bounding-box"
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
                :caption="`${usedCount} dari ${total} proses telah dipetakan dokumen`"
                icon="pie-chart-fill"
                sentiment="teal"
                appearance="crystal"
                pattern="dots"
                :loading="loading"
            />

            <!-- 3. Kesiapan Tipe Item Sah -->
            <HcisStatCard
                label="Aturan Tipe Item Sah"
                :value="configuredCaptionValue"
                :caption="configuredCaptionSubtitle"
                :icon="withTypesCount === total && total > 0 ? 'check-circle-fill' : 'layers-fill'"
                :sentiment="withTypesCount === total && total > 0 ? 'success' : 'purple'"
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

defineOptions({ name: 'SopApqcStats' });

const props = defineProps({
    total: {
        type: Number,
        default: 0,
    },
    usedCount: {
        type: Number,
        default: 0,
    },
    withTypesCount: {
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

const configuredCaptionValue = computed(() => {
    if (props.total <= 0) return '0 / 0';
    return `${props.withTypesCount} / ${props.total}`;
});

const configuredCaptionSubtitle = computed(() => {
    if (props.total <= 0) return 'Belum ada data proses APQC';
    if (props.withTypesCount === props.total) {
        return 'Seluruh proses telah memiliki tipe item sah';
    }
    const diff = props.total - props.withTypesCount;
    return `${diff} proses belum dihubungkan dengan tipe item`;
});
</script>

<style scoped>
.sop-apqc-stats {
    width: 100%;
}
</style>
