<template>
    <div class="sop-kebijakan-stats mb-3">
        <HcisStatGroup :cols="4" gap="sm">
            <!-- 1. Aturan Aktif -->
            <HcisStatCard
                layout="compact"
                label="Aturan Aktif"
                :value="summary.active_rules || 0"
                suffix="Aturan"
                :caption="`${summary.total_rules || rulesCount || 0} Total aturan penugasan`"
                icon="diagram-3-fill"
                sentiment="brand"
                appearance="crystal"
                pattern="grid"
                :loading="loading"
            />

            <!-- 2. Proses Berjalan -->
            <HcisStatCard
                layout="compact"
                label="Proses Berjalan"
                :value="summary.active_runs || 0"
                suffix="Proses"
                caption="Otomasi & scheduler aktif"
                icon="lightning-charge-fill"
                sentiment="info"
                appearance="crystal"
                pattern="dots"
                :loading="loading"
            />

            <!-- 3. Perlu Perhatian -->
            <HcisStatCard
                layout="compact"
                label="Perlu Perhatian"
                :value="summary.attention_rules || 0"
                suffix="Aturan"
                :caption="summary.attention_rules > 0 ? 'Butuh evaluasi / error' : 'Semua aturan normal'"
                icon="exclamation-triangle-fill"
                :sentiment="summary.attention_rules > 0 ? 'danger' : 'success'"
                appearance="crystal"
                pattern="stripes"
                :interactive="Boolean(summary.attention_rules)"
                :loading="loading"
                @click="onAttentionClick"
            />

            <!-- 4. Riwayat Eksekusi -->
            <HcisStatCard
                layout="compact"
                label="Riwayat Eksekusi"
                :value="runsCount || 0"
                suffix="Log"
                caption="Catatan eksekusi otomasi"
                icon="list-check"
                sentiment="purple"
                appearance="crystal"
                pattern="rings"
                :loading="loading"
            />
        </HcisStatGroup>
    </div>
</template>

<script setup>
import { HcisStatGroup, HcisStatCard } from '@/components/ui';

defineOptions({ name: 'SopKebijakanStats' });

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            total_rules: 0,
            active_rules: 0,
            attention_rules: 0,
            active_runs: 0,
            schedulers: [],
        }),
    },
    rulesCount: {
        type: Number,
        default: 0,
    },
    runsCount: {
        type: Number,
        default: 0,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['filter-attention']);

function onAttentionClick() {
    if (props.summary?.attention_rules > 0) {
        emit('filter-attention');
    }
}
</script>
