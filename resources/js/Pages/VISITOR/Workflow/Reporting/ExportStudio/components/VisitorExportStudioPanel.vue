<template>
    <div class="visitor-export-studio">
        <HcisCard variant="glass" sentiment="brand" size="md" class="visitor-source-card mb-4">
            <template #title>
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-database-fill-gear text-brand-600" aria-hidden="true"></i>
                    <span>Pilih sumber data laporan</span>
                </div>
            </template>
            <template #description>
                Buku Tamu dan Permit dipisahkan agar pilihan kolom, saringan, dan template selalu sesuai konteks.
            </template>

            <HcisTabs
                :model-value="datasetAktif"
                variant="segmented"
                size="sm"
                :tabs="datasetTabs"
                aria-label="Sumber data Export Studio Visitor"
                @update:model-value="gantiDataset"
            />
        </HcisCard>

        <div class="visitor-dataset-context mb-3" role="status">
            <span class="visitor-dataset-icon" aria-hidden="true">
                <i class="bi" :class="datasetInfo.icon"></i>
            </span>
            <div>
                <strong>{{ datasetInfo.label }}</strong>
                <p class="mb-0">{{ datasetInfo.description }}</p>
            </div>
        </div>

        <ReportStudio
            :key="datasetAktif"
            ref="studioRef"
            :base-url="`${baseUrl}/${datasetAktif}`"
            :dataset-awal="datasetAktif"
        />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import ReportStudio from '@/components/SYSTEM/ReportStudio/ReportStudio.vue';
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisTabs from '@/components/ui/HcisTabs.vue';
import { hcisConfirm } from '@/components/ui/hcisDialog';

defineProps({
    baseUrl: { type: String, required: true },
});

const datasetAktif = ref('tamu');
const studioRef = ref(null);

const datasetTabs = [
    { key: 'tamu', label: 'Buku Tamu', icon: 'bi-person-vcard-fill' },
    { key: 'permit', label: 'Permit Kendaraan', icon: 'bi-car-front-fill' },
];

const datasetInfo = computed(() => datasetAktif.value === 'tamu'
    ? {
        label: 'Buku Tamu Efektif',
        description: 'Kunjungan yang telah approved/auto-approved, termasuk status dan waktu aktual gate.',
        icon: 'bi-person-vcard-fill',
    }
    : {
        label: 'Permit Kendaraan Efektif',
        description: 'Permit yang telah approved/auto-approved, termasuk kendaraan, masa berlaku, dan lifecycle.',
        icon: 'bi-car-front-fill',
    });

function memilikiDraft() {
    const studio = studioRef.value;
    const pilihan = studio?.pilihan;
    if (!pilihan) return false;

    return Boolean(studio?.templateAktif)
        || ['dimensi', 'ukuran', 'kolom', 'saringan'].some((key) => Array.isArray(pilihan[key]) && pilihan[key].length > 0);
}

async function gantiDataset(datasetBaru) {
    if (!['tamu', 'permit'].includes(datasetBaru) || datasetBaru === datasetAktif.value) return;

    if (memilikiDraft()) {
        const lanjut = await hcisConfirm({
            title: 'Ganti sumber data?',
            description: 'Pilihan laporan yang belum disimpan pada sumber saat ini akan dikosongkan.',
            kicker: 'Export Studio Visitor',
            icon: 'bi-arrow-left-right',
            sentiment: 'warning',
            confirmText: 'Ganti sumber',
            cancelText: 'Tetap di sini',
        });
        if (!lanjut) return;
    }

    datasetAktif.value = datasetBaru;
}
</script>

<style scoped>
.visitor-export-studio {
    min-width: 0;
}

.visitor-source-card :deep(.hcis-card-body) {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

.visitor-dataset-context {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.8rem 1rem;
    border: 1px solid rgba(99, 102, 241, 0.18);
    border-radius: var(--hcis-radius-lg, 14px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.9), rgba(255, 255, 255, 0.96));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.visitor-dataset-context p {
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    font-size: 0.8125rem;
}

.visitor-dataset-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    flex: 0 0 2.5rem;
    border-radius: var(--hcis-radius-lg, 12px);
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    background: linear-gradient(135deg, var(--hcis-indigo-50), var(--hcis-indigo-100));
    border: 1px solid rgba(99, 102, 241, 0.22);
    box-shadow: inset 0 1px 0 #fff;
}

@media (max-width: 575.98px) {
    .visitor-dataset-context {
        align-items: flex-start;
    }
}
</style>
