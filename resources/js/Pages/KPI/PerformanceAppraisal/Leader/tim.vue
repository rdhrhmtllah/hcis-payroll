<template>
    <main class="pa-leader-page">
        <!-- Modern Executive Glass Header -->
        <HcisHeader
            :title="viewMode === 'detail' && detail ? `Evaluasi: ${detail.nama}` : 'Dashboard PA Tim'"
            :kicker="isMobile ? (viewMode === 'detail' ? 'PA · HASIL' : 'PA · TIM') : (viewMode === 'detail' ? 'PA · DETAIL HASIL ANGGOTA' : 'PA · DASHBOARD PA TIM')"
            :subtitle="viewMode === 'detail'
                ? `Pantau profil capaian kinerja resmi, 360° radar kompetensi, dan matriks 9-box untuk ${detail?.nama || 'anggota tim'}.`
                : 'Tinjau status publikasi dan buka hasil evaluasi resmi anggota tim dalam scope Anda.'"
            :icon="viewMode === 'detail' ? 'person-vcard-fill' : 'people-fill'"
            sentiment="teal"
            variant="glass"
            :size="isMobile ? 'sm' : 'md'"
            animated
            :clamp="isMobile ? 1 : null"
            :loading="memuatTim && !tim"
        >
            <template #actions>
                <div class="pa-leader-header-actions">
                    <!-- Period Selector Trigger Jewel (When on Team Dashboard) -->
                    <button
                        v-if="viewMode === 'daftar' && tim?.cycle"
                        type="button"
                        class="pa-period-trigger"
                        :class="{ 'is-loading': memuatTim }"
                        :disabled="memuatTim"
                        aria-haspopup="dialog"
                        :aria-expanded="bukaDialogPeriode"
                        title="Klik untuk memilih siklus & periode penilaian tim"
                        @click="bukaDialogPeriode = true"
                    >
                        <div class="pa-period-trigger__jewel">
                            <HcisIcon :name="memuatTim ? 'arrow-repeat' : 'calendar2-range-fill'" size="xs" :class="{ 'pa-spin-icon': memuatTim }" />
                        </div>
                        <div class="pa-period-trigger__body">
                            <span class="pa-period-trigger__label">
                                <span>PERIODE</span>
                                <span v-if="memuatTim" class="pa-period-trigger__syncing">SYNC</span>
                                <span v-else-if="tim.cycle.tahun" class="pa-period-trigger__year">{{ tim.cycle.tahun }}</span>
                            </span>
                            <span class="pa-period-trigger__value">
                                {{ memuatTim ? 'Memuat data periode…' : (tim.cycle.nama_cycle || tim.cycle.kode_cycle) }}
                            </span>
                        </div>
                        <div v-if="tim.daftar_cycle && tim.daftar_cycle.length > 1" class="pa-period-trigger__chevron">
                            <HcisIcon name="chevron-down" size="2xs" />
                        </div>
                    </button>

                    <HcisButton
                        v-if="viewMode === 'detail'"
                        label="Daftar Tim"
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-left"
                        :icon-only="isMobile"
                        aria-label="Kembali ke dashboard tim"
                        title="Kembali ke dashboard tim"
                        @click="kembaliKeDaftar"
                    />
                    <HcisButton
                        :label="viewMode === 'detail' ? 'Segarkan' : 'Muat ulang'"
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :icon-only="isMobile"
                        :loading="viewMode === 'detail' ? memuatDetail : memuatTim"
                        :aria-label="viewMode === 'detail' ? 'Segarkan detail anggota' : 'Muat ulang dashboard tim'"
                        :title="viewMode === 'detail' ? 'Segarkan detail' : 'Muat ulang data'"
                        @click="viewMode === 'detail' ? muatDetailTerpilih() : muatTimApi()"
                    />
                </div>
            </template>

            <template #console>
                <div class="pa-leader-header-console">
                    <div class="pa-leader-header-console__identity">
                        <span class="pa-leader-header-console__avatar" aria-hidden="true">{{ inisial(tim?.leader?.nama) }}</span>
                        <span class="pa-leader-header-console__name-wrap">
                            <small>Ruang kerja leader</small>
                            <strong>{{ tim?.leader?.nama || 'Menyiapkan profil…' }}</strong>
                        </span>
                    </div>
                    <div class="pa-leader-header-console__context">
                        <HcisStatusBadge
                            :label="tim?.tanpa_tim ? 'Bukan leader' : tim?.cycle ? 'Scope aktif' : 'Belum ada scope'"
                            :variant="tim?.tanpa_tim ? 'warning' : tim?.cycle ? 'success' : 'neutral'"
                            dot
                        />
                        <span class="pa-leader-header-console__cycle-code">{{ tim?.cycle?.kode_cycle || 'Cycle dipilih server berdasarkan tim' }}</span>
                    </div>
                </div>
            </template>

            <template v-if="viewMode === 'daftar' && tim?.cycle && !tim?.tanpa_tim" #tabs>
                <HcisTabs
                    v-model="tabAktif"
                    :tabs="tabs"
                    variant="header"
                    size="sm"
                    :block="!isMobile"
                    aria-label="Area Penilaian Tim"
                />
            </template>
        </HcisHeader>

        <!-- Skeleton Loader -->
        <section v-if="memuatTim && !tim" class="pa-leader-skeleton-grid" aria-live="polite" aria-label="Memuat dashboard tim">
            <HcisCard v-for="item in 3" :key="item" loading variant="glass" sentiment="teal" />
        </section>

        <!-- Error State -->
        <HcisResultState
            v-else-if="errorTim"
            :status="statusErrorTim === 403 ? 'warning' : 'danger'"
            variant="glass"
            title="Dashboard tim belum dapat dimuat"
            :description="errorTim"
            :error-code="kodeErrorTim"
            :request-id="requestIdTim"
            :show-retry="statusErrorTim !== 403"
            primary-text="Coba lagi"
            primary-icon="arrow-clockwise"
            @primary-click="muatTimApi"
        />

        <!-- Team Loaded -->
        <template v-else-if="tim">
            <!-- Non-Leader State -->
            <HcisResultState
                v-if="tim.tanpa_tim"
                status="info"
                variant="glass"
                title="Anda belum memiliki scope tim"
                description="Server tidak menemukan anggota tim yang dapat Anda pantau. Ini adalah keadaan akses yang sah, bukan kegagalan memuat data."
                icon="people"
                :show-retry="false"
            />

            <!-- No Cycle State -->
            <HcisResultState
                v-else-if="!tim.cycle"
                status="info"
                variant="glass"
                title="Belum ada cycle yang memuat tim Anda"
                description="Belum ada cycle Performance Appraisal yang memiliki anggota dalam scope Anda."
                icon="calendar-x"
                :show-retry="false"
            />

            <!-- Main Content Area -->
            <template v-else>
                <!-- VIEW 1: Full In-Page Member Detail View (Executive PA Saya Experience) -->
                <transition name="pa-fade" mode="out-in">
                    <div v-if="viewMode === 'detail'" key="detail-view" class="pa-leader-detail-container">
                        <PaLeaderMemberDetailView
                            :detail="detail"
                            :loading="memuatDetail"
                            :error="errorDetail"
                            :error-status="statusErrorDetail"
                            :error-code="kodeErrorDetail"
                            :request-id="requestIdDetail"
                            :team-members="tim.anggota || []"
                            :show-back-action="true"
                            @back="kembaliKeDaftar"
                            @select-member="gantiAnggotaDetail"
                            @retry="muatDetailTerpilih"
                        />
                    </div>

                    <!-- VIEW 2: Team Overview Register Dashboard -->
                    <div v-else-if="tabAktif === 'ringkasan'" key="ringkasan-view" class="pa-leader-overview-container">
                        <PaLeaderRingkasan
                            :tim="tim"
                            :loading="memuatTim"
                            @detail="bukaDetailAnggota"
                            @lihat-anggota="lihatAnggota"
                        />
                    </div>

                    <div v-else key="table-view" class="pa-leader-overview-container">
                        <!-- Executive 3-Stat Metric Group (Publication Status Quick Filters) -->
                        <section class="pa-leader-metrics" aria-label="Ringkasan status publikasi tim">
                            <HcisStatCard
                                label="Total Anggota"
                                :value="angka(tim.ringkasan?.total_anggota)"
                                caption="Anggota dalam tim aktif"
                                icon="people-fill"
                                sentiment="teal"
                                :layout="isMobile ? 'compact' : 'hero'"
                                role="button"
                                tabindex="0"
                                :loading="memuatTim"
                                :class="{ 'is-active-filter': activeTableFilter === 'ALL' }"
                                title="Klik untuk menampilkan semua anggota"
                                @click="pilihFilterTabel('ALL')"
                                @keydown.enter="pilihFilterTabel('ALL')"
                                @keydown.space.prevent="pilihFilterTabel('ALL')"
                            />
                            <HcisStatCard
                                label="Sudah Terbit"
                                :value="angka(tim.ringkasan?.hasil_terbit)"
                                caption="Hasil resmi sudah dipublikasi"
                                icon="award-fill"
                                sentiment="success"
                                :layout="isMobile ? 'compact' : 'hero'"
                                role="button"
                                tabindex="0"
                                :loading="memuatTim"
                                :class="{ 'is-active-filter': activeTableFilter === 'TERBIT' }"
                                title="Klik untuk memfilter hasil yang sudah terbit"
                                @click="pilihFilterTabel('TERBIT')"
                                @keydown.enter="pilihFilterTabel('TERBIT')"
                                @keydown.space.prevent="pilihFilterTabel('TERBIT')"
                            />
                            <HcisStatCard
                                label="Belum Terbit"
                                :value="angka(belumTerbitCount)"
                                caption="Hasil belum dipublikasikan"
                                icon="hourglass-split"
                                sentiment="brand"
                                :layout="isMobile ? 'compact' : 'hero'"
                                role="button"
                                tabindex="0"
                                :loading="memuatTim"
                                :class="{ 'is-active-filter': activeTableFilter === 'BELUM_DIHITUNG' }"
                                title="Klik untuk memfilter hasil yang belum terbit"
                                @click="pilihFilterTabel('BELUM_DIHITUNG')"
                                @keydown.enter="pilihFilterTabel('BELUM_DIHITUNG')"
                                @keydown.space.prevent="pilihFilterTabel('BELUM_DIHITUNG')"
                            />
                        </section>

                        <!-- Team Member Register Table -->
                        <PaLeaderMemberTable
                            v-model:status-filter="activeTableFilter"
                            :rows="tim.anggota || []"
                            :loading="memuatTim"
                            @detail="bukaDetailAnggota"
                        />
                    </div>
                </transition>
            </template>
        </template>

        <!-- Member Detail Modal Dialog (For programmatic / test modal invocations) -->
        <PaLeaderMemberDetailDialog
            :model-value="dialogDetail"
            :detail="detail"
            :loading="memuatDetail"
            :error="errorDetail"
            :error-status="statusErrorDetail"
            :error-code="kodeErrorDetail"
            :request-id="requestIdDetail"
            :team-members="tim?.anggota || []"
            @update:model-value="ubahDialogDetail"
            @select-member="gantiAnggotaDetail"
            @retry="muatDetailTerpilih"
        />

        <!-- Cycle & Period Selector Modal Dialog -->
        <HcisDialog
            v-if="tim?.daftar_cycle && tim.daftar_cycle.length > 0"
            v-model="bukaDialogPeriode"
            title="Pilih Siklus &amp; Periode Penilaian Tim"
            subtitle="Pilih assessment cycle untuk memuat data evaluasi dan kemajuan anggota tim pada periode tersebut."
            icon="calendar2-range-fill"
            sentiment="teal"
            variant="glass"
            size="lg"
            pattern="grid"
        >
            <div class="pa-period-dialog">
                <!-- Search Box if > 3 cycles -->
                <div v-if="tim.daftar_cycle.length > 3" class="pa-period-dialog__search-wrap">
                    <HcisIcon name="search" size="xs" class="pa-period-dialog__search-icon" />
                    <input
                        v-model="pencarianSiklus"
                        type="search"
                        class="pa-period-dialog__search-input"
                        placeholder="Cari nama siklus, kode, atau tahun…"
                        aria-label="Cari siklus penilaian"
                    />
                </div>

                <!-- Cycle Grid Cards -->
                <div class="pa-period-dialog__grid">
                    <div
                        v-for="c in filteredDaftarCycle"
                        :key="c.cycle_id"
                        role="button"
                        tabindex="0"
                        class="pa-period-card"
                        :class="{ 'pa-period-card--active': c.cycle_id === tim.cycle?.cycle_id }"
                        @click="gantiSiklus(c.cycle_id)"
                        @keydown.enter="gantiSiklus(c.cycle_id)"
                        @keydown.space.prevent="gantiSiklus(c.cycle_id)"
                    >
                        <div class="pa-period-card__header">
                            <span class="pa-period-card__year">{{ c.tahun || 'PA' }}</span>
                            <HcisBadge
                                :label="statusLabel(c.status_cycle)"
                                :status="c.status_cycle"
                                :variant="statusCycleVariant(c.status_cycle)"
                                appearance="glass"
                                size="xs"
                                dot
                            />
                        </div>

                        <strong class="pa-period-card__title">{{ c.nama_cycle || c.kode_cycle }}</strong>
                        <span class="pa-period-card__code">{{ c.kode_cycle }}</span>

                        <div class="pa-period-card__footer">
                            <div class="pa-period-card__dates">
                                <HcisIcon name="calendar-event" size="2xs" />
                                <span>{{ formatPeriod(c.periode_dinilai_dari, c.periode_dinilai_sampai) }}</span>
                            </div>
                            <span v-if="c.cycle_id === tim.cycle?.cycle_id" class="pa-period-card__active-pill">
                                <HcisIcon name="check-circle-fill" size="2xs" />
                                Aktif
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <HcisButton
                    variant="secondary"
                    label="Tutup"
                    size="sm"
                    @click="bukaDialogPeriode = false"
                />
            </template>
        </HcisDialog>
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDialog,
    HcisHeader,
    HcisIcon,
    HcisResultState,
    HcisStatCard,
    HcisStatusBadge,
    HcisTabs,
} from '@/components/ui';
import PaLeaderMemberDetailDialog from './components/PaLeaderMemberDetailDialog.vue';
import PaLeaderMemberDetailView from './components/PaLeaderMemberDetailView.vue';
import PaLeaderMemberTable from './components/PaLeaderMemberTable.vue';
import PaLeaderRingkasan from './components/PaLeaderRingkasan.vue';
import { usePaLeader } from './composables/usePaLeader.js';

defineOptions({ name: 'PerformanceAppraisalLeaderPage' });

const {
    tim,
    memuatTim,
    errorTim,
    statusErrorTim,
    kodeErrorTim,
    requestIdTim,
    detail,
    memuatDetail,
    errorDetail,
    statusErrorDetail,
    kodeErrorDetail,
    requestIdDetail,
    anggotaTerpilih,
    muatTim: muatTimApi,
    muatDetail: muatDetailApi,
    resetDetail,
} = usePaLeader();

const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth <= 768 : false);
let mediaQuery = null;

function updateIsMobile(e) {
    isMobile.value = e ? e.matches : (typeof window !== 'undefined' && window.innerWidth <= 768);
}

const viewMode = ref('daftar'); // 'daftar' | 'detail'
const dialogDetail = ref(false);
const activeTableFilter = ref('ALL');

/* Ringkasan dibuka lebih dulu: leader datang untuk tahu apa yang perlu ia lakukan, baru
   kemudian menelusuri daftar. */
const tabAktif = ref('ringkasan');
const tabs = computed(() => [
    { key: 'ringkasan', label: 'Ringkasan', icon: 'speedometer2' },
    {
        key: 'anggota',
        label: isMobile.value ? 'Anggota' : 'Daftar Anggota',
        icon: 'people',
        badge: tim.value?.anggota?.length ? String(tim.value.anggota.length) : null,
    },
]);

/* Dari kartu Ringkasan ke daftar yang sudah tersaring sesuai kartunya. */
function lihatAnggota(filterKey = 'ALL') {
    activeTableFilter.value = filterKey;
    tabAktif.value = 'anggota';
    scrollToTop();
}
const bukaDialogPeriode = ref(false);
const pencarianSiklus = ref('');

const belumTerbitCount = computed(() => {
    const list = tim.value?.anggota;
    if (Array.isArray(list) && list.length > 0) {
        return list.filter((r) => r.status_peserta !== 'DIKECUALIKAN' && !r.terbit).length;
    }
    const total = Number(tim.value?.ringkasan?.total_anggota) || 0;
    const terbit = Number(tim.value?.ringkasan?.hasil_terbit) || 0;
    return Math.max(0, total - terbit);
});

const filteredDaftarCycle = computed(() => {
    const list = tim.value?.daftar_cycle || [];
    const q = pencarianSiklus.value.trim().toLowerCase();
    if (!q) return list;
    return list.filter((c) => {
        const nama = String(c.nama_cycle || '').toLowerCase();
        const kode = String(c.kode_cycle || '').toLowerCase();
        const tahun = String(c.tahun || '');
        return nama.includes(q) || kode.includes(q) || tahun.includes(q);
    });
});

function scrollToTop() {
    if (typeof window !== 'undefined' && typeof window.scrollTo === 'function') {
        try {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch {
            // Safe fallback in test/non-browser env
        }
    }
}

async function gantiSiklus(cycleId) {
    bukaDialogPeriode.value = false;
    viewMode.value = 'daftar';
    resetDetail();
    scrollToTop();
    await muatTimApi(cycleId);
}

function pilihFilterTabel(filterKey) {
    activeTableFilter.value = filterKey;
    // Smooth scroll down to table if needed
    const tableEl = document.getElementById('pa-leader-members-title');
    if (tableEl && typeof tableEl.scrollIntoView === 'function') {
        try {
            tableEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch {
            // Safe fallback
        }
    }
}

async function bukaDetailAnggota(row) {
    anggotaTerpilih.value = row?.participant_id ?? null;
    viewMode.value = 'detail';
    scrollToTop();
    await muatDetailApi(row?.participant_id);
}

async function gantiAnggotaDetail(participantId) {
    anggotaTerpilih.value = participantId;
    scrollToTop();
    await muatDetailApi(participantId);
}

function kembaliKeDaftar() {
    viewMode.value = 'daftar';
    resetDetail();
    scrollToTop();
}

function muatDetailTerpilih() {
    if (anggotaTerpilih.value !== null && anggotaTerpilih.value !== undefined) {
        return muatDetailApi(anggotaTerpilih.value);
    }

    return Promise.resolve({ skipped: true });
}

function ubahDialogDetail(value) {
    dialogDetail.value = value;
    if (!value && viewMode.value === 'daftar') resetDetail();
}

function angka(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

function inisial(value) {
    return String(value || 'PA')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function statusLabel(value) {
    const labels = {
        DRAFT: 'Draft',
        PREPARED: 'Disiapkan',
        OPEN: 'Terbuka',
        CLOSED: 'Ditutup',
    };

    return labels[value] || String(value || 'Belum tersedia').replaceAll('_', ' ');
}

function statusCycleVariant(value) {
    if (value === 'OPEN') return 'success';
    if (value === 'PREPARED') return 'warning';
    if (value === 'CLOSED') return 'neutral';
    return 'brand';
}

function formatPeriod(from, to) {
    if (!from && !to) return 'Periode belum tersedia';
    return `${from || '—'} s.d. ${to || '—'}`;
}

onMounted(() => {
    muatTimApi();
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
</script>

<style scoped>
.pa-leader-page {
    min-width: 0;
    width: 100%;
    padding: 0 0 5rem 0;
    box-sizing: border-box;
    overflow-x: hidden;
}

.pa-leader-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Period Selector Trigger Jewel Button */
.pa-period-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.35rem 0.85rem 0.35rem 0.45rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.95);
    backdrop-filter: blur(12px);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    cursor: pointer;
    transition: all 160ms ease;
    text-align: left;
    outline: none;
}

.pa-period-trigger:hover {
    background: #ffffff;
    border-color: var(--hcis-teal-500);
    box-shadow: 0 4px 14px rgb(var(--hcis-teal-500-rgb) / 0.15);
    transform: translateY(-1px);
}

.pa-period-trigger.is-loading {
    border-color: rgba(13, 148, 136, 0.4);
    background: rgba(240, 253, 250, 0.9);
    cursor: wait;
}

.pa-period-trigger__syncing {
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: var(--hcis-teal-600);
    color: #ffffff;
    font-weight: 800;
    font-size: 0.58rem;
    letter-spacing: 0.04em;
}

.pa-spin-icon {
    animation: pa-spin 0.85s linear infinite;
}

@keyframes pa-spin {
    100% { transform: rotate(360deg); }
}

.pa-period-trigger__jewel {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.6rem;
    background: linear-gradient(145deg, var(--hcis-teal-50), var(--hcis-teal-100));
    color: var(--hcis-teal-700);
    flex-shrink: 0;
}

.pa-period-trigger:hover .pa-period-trigger__jewel {
    background: var(--hcis-teal-600);
    color: #ffffff;
}

.pa-period-trigger__body {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    min-width: 0;
    max-width: 14rem;
}

.pa-period-trigger__label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.62rem;
    font-weight: 800;
    color: var(--hcis-teal-700);
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.pa-period-trigger__year {
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: rgb(var(--hcis-teal-50-rgb) / 0.9);
    font-weight: 750;
    font-size: 0.6rem;
}

.pa-period-trigger__value {
    font-size: 0.78rem;
    font-weight: 750;
    color: var(--hcis-slate-800);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-period-trigger__chevron {
    color: var(--hcis-slate-400);
    display: grid;
    place-items: center;
    margin-left: 0.15rem;
}

/* Period Dialog Styles */
.pa-period-dialog {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 0.25rem 0;
}

.pa-period-dialog__search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.pa-period-dialog__search-icon {
    position: absolute;
    left: 0.85rem;
    color: var(--hcis-slate-400);
    pointer-events: none;
}

.pa-period-dialog__search-input {
    width: 100%;
    height: 2.35rem;
    padding: 0 1rem 0 2.25rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    color: var(--hcis-slate-800);
    font-size: 0.82rem;
    outline: none;
    transition: all 160ms ease;
}

.pa-period-dialog__search-input:focus {
    border-color: var(--hcis-teal-500);
    box-shadow: 0 0 0 3px rgb(var(--hcis-teal-500-rgb) / 0.15);
}

.pa-period-dialog__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

@media (max-width: 640px) {
    .pa-period-dialog__grid {
        grid-template-columns: 1fr;
    }
}

.pa-period-card {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 1rem 1.15rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: rgba(255, 255, 255, 0.85);
    border: 1.5px solid var(--hcis-slate-200);
    cursor: pointer;
    text-align: left;
    transition: all 160ms ease;
    user-select: none;
}

.pa-period-card:hover {
    background: #ffffff;
    border-color: var(--hcis-teal-500);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
}

.pa-period-card--active {
    background: linear-gradient(135deg, rgba(240, 253, 250, 0.98) 0%, rgba(204, 251, 241, 0.85) 100%);
    border-color: var(--hcis-teal-600);
    box-shadow: 0 6px 20px -2px rgb(var(--hcis-teal-500-rgb) / 0.22);
}

.pa-period-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-period-card__year {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--hcis-teal-700);
    padding: 0.12rem 0.45rem;
    border-radius: 4px;
    background: rgb(var(--hcis-teal-50-rgb) / 0.9);
}

.pa-period-card__title {
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--hcis-slate-800);
    line-height: 1.3;
}

.pa-period-card__code {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    font-family: var(--hcis-font-mono, monospace);
}

.pa-period-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: 0.35rem;
    padding-top: 0.45rem;
    border-top: 1px dashed var(--hcis-slate-200);
}

.pa-period-card__dates {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
}

.pa-period-card__active-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--hcis-teal-700);
    text-transform: uppercase;
}


.pa-leader-header-console {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    min-width: 0;
}

.pa-leader-header-console__identity,
.pa-leader-header-console__context {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.pa-leader-header-console__avatar {
    display: grid;
    flex: 0 0 2.35rem;
    place-items: center;
    width: 2.35rem;
    height: 2.35rem;
    border: 1px solid rgb(var(--hcis-teal-500-rgb) / 0.22);
    border-radius: 0.8rem;
    background: linear-gradient(145deg, var(--hcis-teal-50), var(--hcis-teal-100));
    color: var(--hcis-teal-700);
    font-size: 0.72rem;
    font-weight: 800;
}

.pa-leader-header-console__identity > span:last-child {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    min-width: 0;
}

.pa-leader-header-console small,
.pa-leader-header-console__context > span {
    color: var(--hcis-slate-500);
    font-size: 0.72rem;
}

.pa-leader-header-console strong {
    overflow: hidden;
    color: var(--hcis-slate-800);
    font-size: 0.84rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-leader-detail-container,
.pa-leader-overview-container {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    margin-top: 1.25rem;
}

.pa-leader-skeleton-grid,
.pa-leader-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.85rem;
}

.pa-leader-metrics :deep(.hcis-stat),
.pa-leader-metrics :deep(.hcis-stat-card) {
    cursor: pointer;
    transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
    user-select: none;
}

.pa-leader-metrics :deep(.hcis-stat:hover),
.pa-leader-metrics :deep(.hcis-stat-card:hover) {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(15, 23, 42, 0.08);
}

.pa-leader-metrics :deep(.hcis-stat.is-active-filter),
.pa-leader-metrics :deep(.hcis-stat-card.is-active-filter) {
    box-shadow: 0 0 0 2px var(--hcis-teal-500), 0 8px 20px -4px rgb(var(--hcis-teal-500-rgb) / 0.25);
    border-color: var(--hcis-teal-500) !important;
    transform: translateY(-2px);
}

.pa-leader-skeleton-grid {
    margin-top: 1rem;
}


/* Transitions */
.pa-fade-enter-active,
.pa-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.pa-fade-enter-from {
    opacity: 0;
    transform: translateY(6px);
}

.pa-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

@media (max-width: 767.98px) {
    .pa-leader-page {
        padding-bottom: 3.5rem;
    }

    /* Header Compact Top & Inner */
    :deep(.hcis-header--size-sm .hcis-header__inner) {
        padding: 0.65rem 0.75rem;
    }

    :deep(.hcis-header__top) {
        gap: 0.45rem;
    }

    :deep(.hcis-header__identity) {
        column-gap: 0.45rem;
        row-gap: 0.15rem;
    }

    :deep(.hcis-header__icon-badge) {
        width: 2rem;
        height: 2rem;
        font-size: 0.95rem;
    }

    :deep(.hcis-header__title, .hcis-header h1.hcis-header__title) {
        font-size: 1rem;
        line-height: 1.2;
    }

    :deep(.hcis-header__subtitle) {
        font-size: 0.7rem;
        line-height: 1.3;
        margin-top: 0.1rem;
    }

    /* Actions Row: Single Sleek Horizontal Toolbar */
    :deep(.hcis-header__actions) {
        margin-top: 0.15rem;
        padding-top: 0.45rem;
        border-top: 1px dashed rgba(13, 148, 136, 0.18);
    }

    .pa-leader-header-actions {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        width: 100%;
    }

    .pa-period-trigger {
        flex: 1 1 auto;
        min-width: 0;
        height: 2.25rem;
        padding: 0.2rem 0.55rem 0.2rem 0.35rem;
        gap: 0.4rem;
        border-radius: var(--hcis-radius-md, 8px);
    }

    .pa-period-trigger__jewel {
        width: 1.65rem;
        height: 1.65rem;
        border-radius: 0.4rem;
    }

    .pa-period-trigger__body {
        flex: 1;
        min-width: 0;
        max-width: none;
        gap: 0;
    }

    .pa-period-trigger__label {
        font-size: 0.54rem;
        line-height: 1;
        gap: 0.25rem;
    }

    .pa-period-trigger__year {
        font-size: 0.52rem;
        padding: 0.02rem 0.25rem;
    }

    .pa-period-trigger__value {
        font-size: 0.72rem;
        font-weight: 750;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pa-period-trigger__chevron {
        margin-left: 0.05rem;
    }

    .pa-leader-header-actions :deep(.hcis-btn) {
        flex: 0 0 auto;
        height: 2.25rem;
        min-height: 2.25rem;
        padding: 0 0.65rem;
        border-radius: var(--hcis-radius-md, 8px);
    }

    .pa-leader-header-actions :deep(.hcis-btn.hcis-btn--icon-only) {
        width: 2.25rem;
        padding: 0;
        display: grid;
        place-items: center;
    }

    /* Console Row: Single-line Sleek Status Strip */
    :deep(.hcis-header__console) {
        margin-top: 0.35rem;
        padding-top: 0.4rem;
        border-top: 1px dashed rgba(226, 232, 240, 0.75);
    }

    .pa-leader-header-console {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        width: 100%;
    }

    .pa-leader-header-console__identity {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        min-width: 0;
        flex: 1 1 auto;
    }

    .pa-leader-header-console__avatar {
        display: grid;
        place-items: center;
        width: 1.65rem;
        height: 1.65rem;
        flex: 0 0 1.65rem;
        border-radius: 0.45rem;
        font-size: 0.62rem;
    }

    .pa-leader-header-console__name-wrap {
        display: flex;
        flex-direction: column;
        gap: 0.05rem;
        min-width: 0;
    }

    .pa-leader-header-console small {
        font-size: 0.56rem;
        line-height: 1;
        color: var(--hcis-slate-400, #94a3b8);
    }

    .pa-leader-header-console strong {
        font-size: 0.75rem;
        line-height: 1.15;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pa-leader-header-console__context {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        flex: 0 0 auto;
    }

    .pa-leader-header-console__cycle-code {
        display: none;
    }

    /* Tabs Bar: Snug padding on mobile */
    :deep(.hcis-header__tabs-bar) {
        margin-top: 0.35rem;
        padding-top: 0.1rem;
    }

    :deep(.hcis-tabs--variant-header .hcis-tabs__tab) {
        padding: 0.45rem 0.75rem;
        font-size: 0.775rem;
        min-height: 2.15rem;
    }

    .pa-leader-detail-container,
    .pa-leader-overview-container {
        gap: 1rem;
        margin-top: 0.85rem;
    }

    .pa-leader-skeleton-grid,
    .pa-leader-metrics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.45rem;
    }

    .pa-leader-metrics :deep(.hcis-stat--layout-compact) {
        padding: 0.45rem 0.5rem;
        min-height: 0;
        border-radius: var(--hcis-radius-md, 10px);
    }

    .pa-leader-metrics :deep(.hcis-stat__label) {
        font-size: 0.625rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pa-leader-metrics :deep(.hcis-stat__value--compact) {
        font-size: 1.15rem;
        font-weight: 850;
        line-height: 1.1;
    }

    .pa-leader-metrics :deep(.hcis-stat__icon-box--sm) {
        width: 1.35rem;
        height: 1.35rem;
    }

    .pa-leader-metrics :deep(.hcis-stat__caption) {
        display: none;
    }

    /* Policy Alert Compact */
    :deep(.pa-leader-overview-container .hcis-alert) {
        padding: 0.55rem 0.75rem;
        font-size: 0.72rem;
        line-height: 1.4;
    }

    :deep(.pa-leader-overview-container .hcis-alert__title) {
        font-size: 0.75rem;
        font-weight: 750;
    }
}

@media (max-width: 480px) {
    .pa-period-trigger__label {
        font-size: 0.5rem;
    }

    .pa-period-trigger__value {
        font-size: 0.68rem;
    }

    .pa-leader-header-console strong {
        max-width: 11ch;
    }
}
</style>
