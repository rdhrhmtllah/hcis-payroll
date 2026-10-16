<template>
    <main class="pa-saya-page">
        <!-- Modern Executive Glass Header -->
        <HcisHeader
            title="Appraisal Individu"
            kicker="PERFORMANCE APPRAISAL"
            subtitle="Lembar evaluasi diri dan pengisian tugas penilaian 360° untuk atasan, rekan sejawat, dan bawahan."
            icon="clipboard2-check-fill"
            sentiment="brand"
            variant="glass"
            size="md"
            animated
            :loading="memuatRingkasan && !ringkasan"
            class="pa-saya-header"
        >
            <template #actions>
                <div class="pa-saya-actions">
                    <HcisButton
                        label="Segarkan"
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :icon-only="isMobile"
                        :loading="memuat"
                        aria-label="Segarkan data Performance Appraisal Saya"
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
                    aria-label="Area Performance Appraisal Saya"
                />
            </template>
        </HcisHeader>

        <!-- Dynamic Panel Content -->
        <section ref="kontenEl" class="pa-saya-content" aria-live="polite">
            <PaSayaSummaryPanel
                v-if="tabAktif === 'ringkasan'"
                :data="ringkasan"
                :progress="progres"
                :result="hasil"
                :history="riwayat"
                :loading="memuatRingkasan || memuatProgres"
                :error="errorRingkasan"
                :error-status="statusErrorRingkasan"
                :error-code="kodeErrorRingkasan"
                :request-id="requestIdRingkasan"
                @retry="muat"
                @switch-tab="gantiTab"
            />

            <!-- Wizard per kategori penilai (18 Sep 2026). PaSayaAssignmentPanel dan endpoint
                 /saya/assignment sengaja DIBIARKAN (spec wizard §10), hanya tidak dipasang lagi. -->
            <PaSayaWizardPenilaian
                v-else-if="tabAktif === 'assignment'"
                :kategori-awal="kategoriDariUrl"
                @terkirim="setelahTerkirim"
            />
        </section>
    </main>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

import {
    HcisBadge,
    HcisButton,
    HcisHeader,
    HcisStatusBadge,
    HcisTabs,
} from '@/components/ui';
import PaSayaSummaryPanel from './components/PaSayaSummaryPanel.vue';
import PaSayaWizardPenilaian from './components/PaSayaWizardPenilaian.vue';
import { usePaSaya } from './composables/usePaSaya.js';
import paApi from '../api/paApi.js';

defineOptions({ name: 'PerformanceAppraisalSayaPage' });

const {
    ringkasan,
    memuatRingkasan,
    errorRingkasan,
    statusErrorRingkasan,
    kodeErrorRingkasan,
    requestIdRingkasan,
    assignmentQuery,
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
    muatRingkasan,
    progres,
    memuatProgres,
    errorProgres,
    muatHasil,
    muatRiwayat,
    muatSemua,
    memuat,
} = usePaSaya();

/* TAB DARI URL. HC Learn memulangkan penilai ke `?tab=tugas-penilaian`; tanpa membaca
   parameter ini halaman selalu terbuka di Ringkasan dan penilai harus mencari sendiri
   lembarnya. Nama di URL memakai nama yang terbaca orang, bukan kunci internal tab. */
const TAB_DARI_URL = {
    ringkasan: 'ringkasan',
    'tugas-penilaian': 'assignment',
    tugas: 'assignment',
    assignment: 'assignment',
};
const TAB_KE_URL = { ringkasan: 'ringkasan', assignment: 'tugas-penilaian' };

function gantiTab(tab) {
    if (tab === 'hasil' || tab === 'riwayat') {
        router.visit('/performance-appraisal/dashboard-individu' + (tab === 'riwayat' ? '?tab=riwayat' : ''));
        return;
    }
    tabAktif.value = tab;
}

function parameterUrl(nama) {
    if (typeof window === 'undefined') return null;
    return new URLSearchParams(window.location.search).get(nama);
}

const tabDariUrl = TAB_DARI_URL[String(parameterUrl('tab') || '').toLowerCase()] ?? null;
const kategoriDariUrl = parameterUrl('kategori');

const tabAktif = ref(tabDariUrl ?? 'ringkasan');
const kontenEl = ref(null);
const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth <= 768 : false);
let mediaQuery = null;

function updateIsMobile(e) {
    isMobile.value = e ? e.matches : (typeof window !== 'undefined' && window.innerWidth <= 768);
}

const tabs = computed(() => [
    { key: 'ringkasan', label: isMobile.value ? 'Ringkasan' : 'Ringkasan Tugas', icon: 'speedometer2' },
    {
        key: 'assignment',
        label: isMobile.value ? 'Tugas' : 'Tugas Penilaian',
        icon: 'list-check',

        /* Kalau ada yang SIAP DIKIRIM, itu yang ditunjukkan lencananya - bukan cacah tugas.
           Pengiriman adalah satu-satunya langkah tersisa yang tidak terlihat dari tab mana
           pun selain tab ini, dan angka total di sini tidak menyuruh siapa pun berbuat apa. */
        badge: sudahTerkirimSemua.value
            ? '✓'
            : pengirimanAktif.value?.bisa_kirim
                ? (isMobile.value ? 'Siap' : 'Siap dikirim')
                : (ringkasan.value ? angka(ringkasan.value.total_assignment) : null),
        badgeVariant: sudahTerkirimSemua.value || pengirimanAktif.value?.bisa_kirim ? 'success' : undefined,
    },
]);

/* Semua penilaian cycle aktif sudah terkirim: angka tugas diganti centang, supaya tanda
   "selesai" terlihat tanpa membuka tabnya. */
const sudahTerkirimSemua = computed(() =>
    Boolean(pengirimanAktif.value?.sudah_terkirim_semua) && Number(pengirimanAktif.value?.total) > 0,
);

const statusRitmeLabel = computed(() => {
    if (!ringkasan.value) return 'Memeriksa status';
    if (angka(ringkasan.value.terlambat) > 0) return `${angka(ringkasan.value.terlambat)} Tugas Terlambat`;
    if (angka(ringkasan.value.deadline_dekat) > 0) return `${angka(ringkasan.value.deadline_dekat)} Mendekati Tenggat`;
    return 'Semua Jadwal Aman';
});

const statusRitmeVariant = computed(() => {
    if (!ringkasan.value) return 'neutral';
    if (angka(ringkasan.value.terlambat) > 0) return 'danger';
    if (angka(ringkasan.value.deadline_dekat) > 0) return 'warning';
    return 'success';
});

function nilaiRingkasan(key) {
    return ringkasan.value ? angka(ringkasan.value[key]) : '—';
}

function angka(value, fallback = 0) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function inisial(value) {
    return String(value || 'PA')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function bukaCycleHasil(cycleId) {
    router.visit('/performance-appraisal/dashboard-individu');
}

/* ------------------------------------------------------------------ *
 * PENGIRIMAN SATU PINTU                                               *
 * ------------------------------------------------------------------ */

const kemajuanPengiriman = ref([]);

/* Cycle yang seluruh penilaiannya SUDAH terkirim tidak ditampilkan.
   Kartu yang isinya "sudah selesai, tidak ada yang perlu Anda lakukan" menempati ruang
   paling atas halaman setiap kali dibuka, dan sesudah beberapa kali orang berhenti
   membacanya - termasuk ketika suatu hari isinya berubah menjadi tugas baru. */
/* Satu cycle, bukan daftar: tab Tugas Penilaian memang menampilkan satu cycle pada satu
   waktu. Yang dipilih adalah yang masih menuntut tindakan; kalau tidak ada, yang pertama —
   supaya barisnya tetap bisa mengatakan "sudah terkirim semua" alih-alih menghilang. */
const pengirimanAktif = computed(() => {
    if (assignmentQuery.value?.cycle_id) {
        const id = Number(assignmentQuery.value.cycle_id);
        const match = kemajuanPengiriman.value.find((k) => k.cycle_id === id);
        if (match) return match;
    }
    return kemajuanPengiriman.value.find((k) => k.total > 0 && !k.sudah_terkirim_semua)
        || kemajuanPengiriman.value[0]
        || null;
});

async function muatKemajuanPengiriman() {
    try {
        const r = await paApi.kemajuanPengirimanSaya();
        kemajuanPengiriman.value = Array.isArray(r) ? r : (r?.result ?? []);
    } catch (e) {
        /* Kegagalan di sini TIDAK boleh menjatuhkan halaman: yang bergantung padanya kini
           hanya lencana tab "Siap dikirim". Status pengiriman yang sebenarnya dibaca wizard
           dari `/saya/wizard` dan menampilkan galatnya sendiri. */
        kemajuanPengiriman.value = [];

        // Bertanda supaya bisa disaring di konsol saat melaporkan masalah.
        console.error('[PA Saya] kemajuan pengiriman gagal dimuat', e);
    }
}

/* Pengiriman kini lewat wizard (PaSayaWizardPenilaian). Sesudahnya SELURUH halaman dimuat
   ulang, bukan hanya tab itu: pengiriman mengubah status setiap lembar menjadi selesai
   sekaligus, jadi ringkasan dan lencana tab sudah tidak sama dengan yang tampil tadi. */
function setelahTerkirim() {
    muat().catch(() => {});
    muatKemajuanPengiriman();
}

async function muat() {
    await muatSemua({ assignment: assignmentQuery.value });
}

/* DIMUAT SENDIRI, tidak menumpang di belakang `muat()`.

   Sebelumnya baris ini `await muat()` lebih dulu — dan `muatSemua()` melempar bila salah
   satu dari empat permintaannya gagal, sehingga pemuatan kemajuan pengiriman TIDAK PERNAH
   berjalan dan barisnya hilang tanpa satu pun galat di layar maupun di log server: satu
   permintaan yang gagal menyembunyikan permintaan lain yang sama sekali tidak berhubungan.

   Sekarang keduanya berangkat bersamaan dan masing-masing menanggung galatnya sendiri. */
onMounted(() => {
    const tabUrl = String(parameterUrl('tab') || '').toLowerCase();
    if (tabUrl === 'hasil' || tabUrl === 'hasil-saya' || tabUrl === 'riwayat') {
        router.visit('/performance-appraisal/dashboard-individu' + (tabUrl === 'riwayat' ? '?tab=riwayat' : ''), { replace: true });
        return;
    }

    muat().catch(() => {});
    muatKemajuanPengiriman();

    // Datang dari tautan bertab (mis. pulang dari HC Learn): langsung ke isinya, bukan kepala.
    if (tabDariUrl && tabDariUrl !== 'ringkasan') {
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

/* Dimuat ulang saat tab tugas dibuka. Angkanya berubah setiap penilai menyelesaikan satu
   lembar di HC Learn — sering di tab peramban lain — jadi angka dari pemuatan pertama sudah
   basi pada saat orang kembali ke sini. */
watch(tabAktif, (tab) => {
    if (tab === 'assignment') muatKemajuanPengiriman();

    /* URL ikut tab yang dibuka, supaya dimuat ulang atau dibagikan tetap di tab yang sama.
       `history.state` dibawa apa adanya: di situ Inertia menyimpan halamannya. */
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', TAB_KE_URL[tab] ?? tab);
        url.searchParams.delete('kategori');
        window.history.replaceState(window.history.state, '', url);
    }
});
</script>

<style scoped>
.pa-saya-page,
.pa-saya-page > *,
.pa-saya-content {
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-saya-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 0rem;
    overflow-x: hidden;
}

.pa-saya-header {
    border-radius: var(--hcis-radius-xl, 16px);
}

.pa-saya-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-saya-content {
    width: 100%;
    min-width: 0;
    scroll-margin-top: 4.5rem;
}

@media (max-width: 768px) {
    .pa-saya-page {
        padding: 0rem;
        gap: 0.85rem;
    }

    .pa-saya-actions {
        width: auto;
    }

    /* Single-row header enforcement per Rule 19.2 (Strict No-Wrap Policy) */
    .pa-saya-header :deep(.hcis-header__top) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
        width: 100% !important;
    }

    .pa-saya-header :deep(.hcis-header__identity) {
        display: grid !important;
        grid-template-columns: auto 1fr !important;
        grid-template-rows: auto auto !important;
        grid-template-areas:
            "icon kicker"
            "icon title" !important;
        column-gap: 0.65rem !important;
        row-gap: 0.1rem !important;
        align-items: center !important;
        min-width: 0 !important;
        flex: 1 1 auto !important;
        width: auto !important;
    }

    .pa-saya-header :deep(.hcis-header__actions) {
        width: auto !important;
        margin-top: 0 !important;
        padding-top: 0 !important;
        border-top: none !important;
        flex: 0 0 auto !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-end !important;
    }

    .pa-saya-header :deep(.hcis-header__actions .hcis-btn) {
        flex: 0 0 auto !important;
        width: auto !important;
    }
}

@media (max-width: 768px) {
    .pa-saya-page {
        padding: 0rem;
        gap: 0.75rem;
    }

    .pa-saya-header :deep(.hcis-header__inner) {
        padding: 0.75rem 0.85rem !important;
        gap: 0.45rem !important;
    }

    /* Subtitle panjang di header kontainer disembunyikan per standard Rule 19.2 */
    .pa-saya-header :deep(.hcis-header__subtitle) {
        display: none !important;
    }

    .pa-saya-header :deep(.hcis-header__icon-badge) {
        grid-area: icon !important;
        width: 2.25rem !important;
        height: 2.25rem !important;
        min-width: 2.25rem !important;
        border-radius: 9px !important;
        font-size: 1.15rem !important;
        align-self: center !important;
        margin: 0 !important;
    }

    .pa-saya-header :deep(.hcis-header__kicker) {
        grid-area: kicker !important;
        font-size: 0.58rem !important;
        letter-spacing: 0.06em !important;
        font-weight: 750 !important;
        line-height: 1.15 !important;
        color: var(--hcis-brand-600) !important;
        margin: 0 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .pa-saya-header :deep(.hcis-header__title-row) {
        grid-area: title !important;
        width: 100% !important;
        min-width: 0 !important;
        margin: 0 !important;
        display: flex !important;
        align-items: center !important;
        flex-wrap: nowrap !important;
    }

    .pa-saya-header :deep(.hcis-header__title),
    .pa-saya-header :deep(h1.hcis-header__title) {
        font-size: 1.05rem !important;
        font-weight: 750 !important;
        line-height: 1.25 !important;
        letter-spacing: -0.015em !important;
        color: var(--hcis-slate-800) !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        min-width: 0 !important;
        margin: 0 !important;
    }

    /* Tombol Segarkan menjadi tombol ikon persegi kompak di mobile */
    .pa-saya-actions {
        display: inline-flex !important;
        align-items: center !important;
        margin: 0 !important;
    }

    .pa-saya-actions :deep(.hcis-btn) {
        width: 2.15rem !important;
        height: 2.15rem !important;
        min-width: 2.15rem !important;
        min-height: 2.15rem !important;
        max-width: 2.15rem !important;
        max-height: 2.15rem !important;
        padding: 0 !important;
        display: inline-grid !important;
        place-items: center !important;
        border-radius: var(--hcis-radius-md, 8px) !important;
        flex: 0 0 2.15rem !important;
    }

    .pa-saya-actions :deep(.hcis-btn__text) {
        display: none !important;
    }

    .pa-saya-actions :deep(.hcis-btn__icon) {
        margin: 0 !important;
        font-size: 0.95rem !important;
    }

    /* Integrated Bottom Header Tabs Bar on Mobile */
    .pa-saya-header :deep(.hcis-header__tabs-bar) {
        margin-top: 0.45rem !important;
        margin-left: -0.85rem !important;
        margin-right: -0.85rem !important;
        margin-bottom: -0.75rem !important;
        padding-left: 0.4rem !important;
        padding-right: 0.4rem !important;
        padding-top: 0.15rem !important;
        border-top: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.15) !important;
        background: rgb(var(--hcis-brand-500-rgb) / 0.03) !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        -webkit-overflow-scrolling: touch !important;
        scrollbar-width: none !important;
    }

    .pa-saya-header :deep(.hcis-header__tabs-bar::-webkit-scrollbar) {
        display: none !important;
    }

    /* Compact Tabs Dock on Mobile */
    .pa-saya-header :deep(.hcis-tabs-wrapper) {
        width: 100% !important;
        min-width: 0 !important;
    }

    .pa-saya-header :deep(.hcis-tabs__track) {
        width: 100% !important;
        min-width: 0 !important;
        padding: 0 !important;
        border: none !important;
        background: transparent !important;
        overflow-x: auto !important;
        scrollbar-width: none !important;
        -webkit-overflow-scrolling: touch !important;
    }

    .pa-saya-header :deep(.hcis-tabs) {
        display: flex !important;
        width: 100% !important;
        min-width: max-content !important;
        gap: 0.2rem !important;
        justify-content: space-between !important;
        align-items: flex-end !important;
    }

    .pa-saya-header :deep(.hcis-tabs__tab) {
        flex: 1 1 auto !important;
        min-width: max-content !important;
        padding: 0.5rem 0.45rem !important;
        font-size: 0.775rem !important;
        min-height: 2.35rem !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.3rem !important;
        white-space: nowrap !important;
        border-radius: var(--hcis-radius-md, 8px) var(--hcis-radius-md, 8px) 0 0 !important;
    }

    .pa-saya-header :deep(.hcis-tabs__icon) {
        width: auto !important;
        height: auto !important;
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
        font-size: 0.85rem !important;
        margin-right: 0 !important;
        flex-shrink: 0 !important;
        display: inline-flex !important;
    }

    .pa-saya-header :deep(.hcis-tabs__badge) {
        padding: 1px 5px !important;
        font-size: 0.625rem !important;
        font-weight: 700 !important;
        line-height: 1.2 !important;
        border-radius: var(--hcis-radius-pill, 9999px) !important;
        flex-shrink: 0 !important;
    }

    /* Clean up all card subtitles/descriptions across all panels per user instruction */
    .pa-saya-page :deep(.hcis-card__description),
    .pa-saya-page :deep(.hcis-card p.hcis-card__description),
    .pa-saya-page :deep(.hcis-stat__caption),
    .pa-saya-page :deep(.pa-summary-kpi-card__sub),
    .pa-saya-page :deep(.pa-summary-kpi-card__foot),
    .pa-saya-page :deep(.pa-summary-kpi-card__arrow),
    .pa-saya-page :deep(.pa-comp-sub),
    .pa-saya-page :deep(.pa-workload-status-bar),
    .pa-saya-page :deep(.pa-workload-counts span),
    .pa-saya-page :deep(.pa-workload-rate span),
    .pa-saya-page :deep(.pa-insights-box__tag),
    .pa-saya-page :deep(.pa-mini-kpi-sub),
    .pa-saya-page :deep(.pa-history-kpi-caption),
    .pa-saya-page :deep(.pa-history-kpi-card__foot),
    .pa-saya-page :deep(.pa-history-records-subtitle),
    .pa-saya-page :deep(.pa-wizard-kpi-card__foot),
    .pa-saya-page :deep(.pa-compact-caption),
    .pa-saya-page :deep(.pa-lembar-desc),
    .pa-saya-page :deep(.pa-lanjut-desc),
    .pa-saya-page :deep(.pa-kalibrasi-desc),
    .pa-saya-page :deep(.pa-naratif-subtitle),
    .pa-saya-page :deep(.pa-result-cycle-selector-subtitle),
    .pa-saya-page :deep(.pa-result-cycle-selector-hint),
    .pa-saya-page :deep(.pa-grade-medallion__sub),
    .pa-saya-page :deep(.pa-hero-score-caption),
    .pa-saya-page :deep(.pa-composition-caption) {
        display: none !important;
    }

    /* Standardized Card Headers, Body Padding & Jewel Icons across all panels */
    .pa-saya-page :deep(.hcis-card) {
        border-radius: var(--hcis-radius-lg, 12px) !important;
    }

    .pa-saya-page :deep(.hcis-card__header) {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
        padding: 0.75rem 0.85rem !important;
    }

    .pa-saya-page :deep(.hcis-card__body) {
        padding: 0.75rem 0.85rem !important;
    }

    .pa-saya-page :deep(.hcis-card:not(.hcis-card--divider) .hcis-card__header + .hcis-card__body) {
        padding-top: 0.25rem !important;
    }

    .pa-saya-page :deep(.hcis-card__header-main) {
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
        min-width: 0 !important;
        flex: 1 1 auto !important;
    }

    .pa-saya-page :deep(.hcis-card__icon-box) {
        width: 2rem !important;
        height: 2rem !important;
        min-width: 2rem !important;
        min-height: 2rem !important;
        font-size: 0.95rem !important;
        border-radius: 8px !important;
    }

    .pa-saya-page :deep(.hcis-card__title) {
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        font-size: 0.875rem !important;
        line-height: 1.25 !important;
    }

    /* Standardized Stat Cards across all panels */
    .pa-saya-page :deep(.hcis-stat) {
        padding: 0.65rem 0.75rem !important;
        border-radius: var(--hcis-radius-lg, 12px) !important;
    }

    .pa-saya-page :deep(.hcis-stat__icon-box) {
        width: 1.85rem !important;
        height: 1.85rem !important;
        font-size: 0.9rem !important;
        border-radius: 7px !important;
    }

    .pa-saya-page :deep(.hcis-stat__label) {
        font-size: 0.6875rem !important;
        letter-spacing: 0.02em !important;
    }

    .pa-saya-page :deep(.hcis-stat__value) {
        font-size: 1.25rem !important;
        margin-top: 0.2rem !important;
    }
}

@media (max-width: 390px) {
    .pa-saya-header :deep(.hcis-header__title),
    .pa-saya-header :deep(h1.hcis-header__title) {
        font-size: 0.95rem !important;
    }

    .pa-saya-header :deep(.hcis-tabs__tab) {
        padding: 0.45rem 0.35rem !important;
        font-size: 0.725rem !important;
        gap: 0.2rem !important;
    }

    .pa-saya-header :deep(.hcis-tabs__icon) {
        font-size: 0.75rem !important;
    }
}
</style>
