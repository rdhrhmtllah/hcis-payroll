<template>
    <main class="pa-dashboard-page">
        <!-- Unified Header -->
        <HcisHeader
            title="Dashboard Administrator PA"
            kicker="PERFORMANCE APPRAISAL · CONTROL TOWER"
            description="Pusat komando dan analisis kinerja terpadu. Pantau kesiapan peserta, pergerakan penilaian, distribusi hasil, dan riwayat multi-tahun."
            icon="speedometer2"
            sentiment="brand"
            variant="glass"
            size="md"
            animated
            class="mb-4"
        >
            <template #actions>
                <div class="pa-dashboard-header-actions">
                    <HcisButton
                        href="/performance-appraisal"
                        label="Assessment Cycle"
                        variant="secondary"
                        size="sm"
                        leading-icon="calendar-range"
                    />
                </div>
            </template>

            <template #console>
                <nav class="pa-admin-switcher" aria-label="Navigasi Performance Appraisal">
                    <a href="/performance-appraisal" class="pa-admin-switcher__item">
                        <HcisIcon name="grid-fill" size="2xs" />
                        <span>Assessment Cycle</span>
                    </a>
                    <a href="/performance-appraisal/pemantauan" class="pa-admin-switcher__item">
                        <HcisIcon name="hourglass-split" size="2xs" />
                        <span>Monitoring &amp; Verifikasi</span>
                    </a>
                    <a href="/performance-appraisal/hav-map" class="pa-admin-switcher__item">
                        <HcisIcon name="grid-3x3" size="2xs" />
                        <span>9-Box HAV Map</span>
                    </a>
                    <a href="/performance-appraisal/distribusi" class="pa-admin-switcher__item">
                        <HcisIcon name="bar-chart-fill" size="2xs" />
                        <span>Distribusi Performance</span>
                    </a>
                    <a href="/performance-appraisal/riwayat" class="pa-admin-switcher__item">
                        <HcisIcon name="clock-history" size="2xs" />
                        <span>Riwayat Penilaian</span>
                    </a>
                    <a href="/performance-appraisal/dashboard" class="pa-admin-switcher__item active pa-admin-switcher__item--active">
                        <HcisIcon name="speedometer2" size="2xs" />
                        <span>Dashboard PA</span>
                    </a>
                    <a href="/performance-appraisal/master/syarat-kompetensi" class="pa-admin-switcher__item">
                        <HcisIcon name="diagram-3-fill" size="2xs" />
                        <span>Master Data</span>
                    </a>
                </nav>
            </template>
        </HcisHeader>

        <!-- Main Control Tower Workspace -->
        <PaAdminControlTower
            :cycles="siklusList"
            :cycles-loading="loadingSiklus"
            :initial-cycle-id="initialCycleId"
            @refresh-cycles="muatSiklus"
            @select-participant="onSelectParticipant"
        />

        <!-- Participant Detail Result Dialog -->
        <PaResultDialog
            v-if="selectedParticipant"
            v-model="participantResultOpen"
            :participant="selectedParticipant"
            :result="participantResultState.detail.value"
            :loading="participantResultState.loading.value"
            :error="participantResultState.error.value"
            :error-status="participantResultState.errorStatus.value"
            :error-code="participantResultState.errorCode.value"
            :request-id="participantResultState.requestId.value"
            :calculation="participantResultState.hasilHitung.value"
            :recalculating="participantResultState.recalculating.value"
            :acting="participantResultState.acting.value"
            :action-error="participantResultState.actionError.value"
            :action-error-status="participantResultState.actionErrorStatus.value"
            :action-error-code="participantResultState.actionErrorCode.value"
            :action-request-id="participantResultState.actionRequestId.value"
            :action-success="participantResultState.actionSuccess.value"
            :can-calibrate="true"
            :can-calculate="true"
            @retry="muatDetailHasilPeserta"
            @calibrate="kalibrasiPeserta"
            @calculate="hitungUlangPeserta"
        />
    </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import {
    HcisButton,
    HcisHeader,
    HcisIcon,
} from '@/components/ui';
import PaAdminControlTower from '../Cycle/components/PaAdminControlTower.vue';
import PaResultDialog from '../Cycle/components/PaResultDialog.vue';
import { usePaResult } from '../Cycle/composables/usePaResult.js';
import paApi from '../api/paApi.js';

defineOptions({ name: 'PerformanceAppraisalDashboardPage' });

const props = defineProps({
    cycleId: { type: [String, Number], default: null },
});

const initialCycleId = ref(props.cycleId ? String(props.cycleId) : '');
const siklusList = ref([]);
const loadingSiklus = ref(false);

const selectedParticipant = ref(null);
const participantResultOpen = ref(false);

const participantResultState = usePaResult(paApi);

async function muatSiklus() {
    loadingSiklus.value = true;
    try {
        const response = await paApi.daftarSiklus();
        const data = response?.result ?? response;
        siklusList.value = data?.siklus || [];
    } catch (e) {
        console.error('Gagal memuat daftar siklus untuk dashboard:', e);
    } finally {
        loadingSiklus.value = false;
    }
}

async function onSelectParticipant(participant) {
    selectedParticipant.value = participant;
    participantResultOpen.value = true;
    await muatDetailHasilPeserta();
}

async function muatDetailHasilPeserta() {
    if (!selectedParticipant.value?.Id_PA_Participant) return;
    await participantResultState.muat(selectedParticipant.value.Id_PA_Participant);
}

async function kalibrasiPeserta(data) {
    const pid = selectedParticipant.value?.Id_PA_Participant;
    const resultId = data?.resultId || selectedParticipant.value?.Id_PA_Result;
    const payload = data?.payload || data;
    if (!pid || !resultId) return;
    await participantResultState.kalibrasi(pid, resultId, payload);
}

async function hitungUlangPeserta() {
    const pid = selectedParticipant.value?.Id_PA_Participant;
    if (!pid) return;
    await participantResultState.hitung(pid);
}

onMounted(() => {
    muatSiklus();
});
</script>

<style scoped>
.pa-dashboard-page {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    padding: 0 0 5rem 0;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

.pa-dashboard-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-admin-switcher {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(241, 245, 249, 0.75);
    overflow-x: auto;
}

.pa-admin-switcher__item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.65rem;
    border-radius: var(--hcis-radius-sm, 6px);
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.74rem;
    font-weight: 650;
    text-decoration: none;
    transition: all 150ms ease;
    white-space: nowrap;
}

.pa-admin-switcher__item:hover {
    color: #4f46e5;
    background: rgba(255, 255, 255, 0.6);
}

.pa-admin-switcher__item.active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

@media (max-width: 640px) {
    .pa-dashboard-page {
        padding: 0 0 5rem 0;
    }
}
</style>
