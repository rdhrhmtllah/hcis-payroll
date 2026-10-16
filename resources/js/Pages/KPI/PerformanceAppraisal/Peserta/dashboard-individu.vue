<template>
    <main class="pa-dashboard-individu-page">
        <!-- Modern Executive Glass Header -->
        <HcisHeader
            title="Dashboard PA Individu"
            kicker="PERFORMANCE APPRAISAL · HASIL EVALUASI"
            subtitle="Pantau profil capaian kinerja resmi, 360° radar kompetensi, matriks 9-box, dan riwayat performa Anda lintas cycle."
            icon="speedometer2"
            sentiment="brand"
            variant="glass"
            size="md"
            animated
            :loading="memuatHasil && !hasil"
            class="pa-dashboard-header"
        >
            <template #actions>
                <div class="pa-dashboard-actions">
                    <HcisButton
                        label="Segarkan"
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :icon-only="isMobile"
                        :loading="memuat"
                        aria-label="Segarkan data Dashboard PA Individu"
                        title="Segarkan data"
                        @click="muat"
                    />
                </div>
            </template>

            <template #tabs>
                <HcisTabs
                    v-model="tabAktif"
                    :tabs="tabs"
                    variant="header"
                    size="sm"
                    :block="!isMobile"
                    aria-label="Area Dashboard PA Individu"
                />
            </template>
        </HcisHeader>

        <!-- Dynamic Panel Content -->
        <section ref="kontenEl" class="pa-dashboard-content" aria-live="polite">
            <PaSayaResultPanel
                v-if="tabAktif === 'hasil'"
                :data="hasil"
                :history="riwayat"
                :loading="memuatHasil"
                :error="errorHasil"
                :error-status="statusErrorHasil"
                :error-code="kodeErrorHasil"
                :request-id="requestIdHasil"
                @retry="muatHasil"
                @cycle-change="(id) => muatHasil(id)"
            />

            <PaSayaHistoryPanel
                v-else
                :data="riwayat"
                :loading="memuatRiwayat"
                :error="errorRiwayat"
                :error-status="statusErrorRiwayat"
                :error-code="kodeErrorRiwayat"
                :request-id="requestIdRiwayat"
                @retry="muatRiwayat"
                @view-cycle="bukaCycleHasil"
                @switch-tab="(t) => tabAktif = t"
            />
        </section>
    </main>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import {
    HcisButton,
    HcisHeader,
    HcisTabs,
} from '@/components/ui';
import PaSayaHistoryPanel from './components/PaSayaHistoryPanel.vue';
import PaSayaResultPanel from './components/PaSayaResultPanel.vue';
import { usePaSaya } from './composables/usePaSaya.js';

defineOptions({ name: 'PaDashboardIndividuPage' });

const {
    hasil,
    memuatHasil,
    errorHasil,
    statusErrorHasil,
    kodeErrorHasil,
    requestIdHasil,
    riwayat,
    memuatRiwayat,
    errorRiwayat,
    statusErrorRiwayat,
    kodeErrorRiwayat,
    requestIdRiwayat,
    muatHasil,
    muatRiwayat,
    memuat,
} = usePaSaya();

const TAB_DARI_URL = {
    hasil: 'hasil',
    'hasil-saya': 'hasil',
    riwayat: 'riwayat',
};
const TAB_KE_URL = { hasil: 'hasil', riwayat: 'riwayat' };

function parameterUrl(nama) {
    if (typeof window === 'undefined') return null;
    return new URLSearchParams(window.location.search).get(nama);
}

const tabDariUrl = TAB_DARI_URL[String(parameterUrl('tab') || '').toLowerCase()] ?? null;
const tabAktif = ref(tabDariUrl ?? 'hasil');
const kontenEl = ref(null);
const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth <= 768 : false);
let mediaQuery = null;

function updateIsMobile(e) {
    isMobile.value = e ? e.matches : (typeof window !== 'undefined' && window.innerWidth <= 768);
}

const tabs = computed(() => [
    {
        key: 'hasil',
        label: isMobile.value ? 'Hasil' : 'Hasil Penilaian',
        icon: 'award',
        badge: hasil.value?.tersedia ? 'Terbit' : null,
        badgeVariant: 'success',
    },
    {
        key: 'riwayat',
        label: isMobile.value ? 'Riwayat' : 'Riwayat Cycle',
        icon: 'clock-history',
        badge: riwayat.value ? angka(riwayat.value.jumlah_periode) : null,
    },
]);

function angka(value, fallback = 0) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function bukaCycleHasil(cycleId) {
    tabAktif.value = 'hasil';
    if (cycleId) {
        muatHasil(cycleId);
    }
}

async function muat() {
    await Promise.all([muatHasil(), muatRiwayat()]);
}

onMounted(() => {
    muat().catch(() => {});

    if (tabDariUrl && tabDariUrl !== 'hasil') {
        nextTick(() => kontenEl.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' }));
    }

    if (typeof window !== 'undefined' && window.matchMedia) {
        mediaQuery = window.matchMedia('(max-width: 768px)');
        isMobile.value = mediaQuery.matches;
        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', updateIsMobile);
        } else if (typeof mediaQuery.addListener === 'function') {
            mediaQuery.addListener(updateIsMobile);
        }
    }
});

onBeforeUnmount(() => {
    if (mediaQuery) {
        if (typeof mediaQuery.removeEventListener === 'function') {
            mediaQuery.removeEventListener('change', updateIsMobile);
        } else if (typeof mediaQuery.removeListener === 'function') {
            mediaQuery.removeListener(updateIsMobile);
        }
    }
});

watch(tabAktif, (tab) => {
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', TAB_KE_URL[tab] ?? tab);
        window.history.replaceState(window.history.state, '', url);
    }
});
</script>

<style scoped>
.pa-dashboard-individu-page,
.pa-dashboard-individu-page > *,
.pa-dashboard-content {
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-dashboard-individu-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 0rem;
    overflow-x: hidden;
}

.pa-dashboard-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

@media (max-width: 768px) {
    .pa-dashboard-individu-page {
        gap: 1rem;
    }
}
</style>
