<template>
    <section class="pa-leader-member-table" aria-label="Daftar penilaian anggota tim">
        <!-- Filter & Search Toolbar (Official Design System HcisFilterBar) -->
        <HcisFilterBar
            :search="searchQuery"
            search-placeholder="Cari nama, kode, atau jabatan anggota…"
            :search-debounce="150"
            :quick-chips="statusFilterOptions"
            :active-chip-value="internalStatusFilter"
            variant="card"
            sentiment="teal"
            size="sm"
            :results-count="filteredRows.length"
            class="pa-member-filter-bar"
            @update:search="onSearchChange"
            @chip-click="onChipClick"
            @reset="resetFilters"
        />

        <!-- ═══════════════════════════════════════════════════════════════════
             MOBILE VIEW (<= 768px): Executive Member Card Feed using HcisCard
             ═══════════════════════════════════════════════════════════════════ -->
        <div v-if="isMobile" class="pa-member-mobile-deck" role="feed" aria-label="Daftar anggota tim">
            <!-- Non-destructive Floating Sync Badge -->
            <transition name="hcis-loading-pop">
                <div v-if="loading && filteredRows.length" class="pa-member-syncing-badge" aria-live="polite">
                    <div class="pa-member-spinner-box" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="pa-member-spinner-svg">
                            <circle cx="12" cy="12" r="9" fill="none" stroke-width="2.5" class="pa-member-spinner-track" />
                            <circle cx="12" cy="12" r="9" fill="none" stroke-width="2.5" class="pa-member-spinner-head" />
                        </svg>
                    </div>
                    <span class="pa-member-syncing-label">Memperbarui data anggota…</span>
                </div>
            </transition>

            <!-- Loading Skeleton Cards using HcisCard -->
            <div v-if="loading && !filteredRows.length" class="pa-member-mobile-loading">
                <HcisCard v-for="n in 3" :key="n" loading variant="glass" sentiment="teal" size="sm" class="pa-member-skeleton-card" />
            </div>

            <!-- Empty State using HcisEmptyState Component -->
            <HcisEmptyState
                v-else-if="!filteredRows.length"
                :title="searchQuery || internalStatusFilter !== 'ALL' ? 'Anggota tidak ditemukan' : emptyTitle"
                :description="searchQuery || internalStatusFilter !== 'ALL' ? 'Tidak ada anggota tim yang sesuai dengan kriteria pencarian atau filter yang dipilih.' : emptyDescription"
                icon="people"
                variant="card"
                sentiment="neutral"
                size="sm"
                class="pa-member-mobile-empty"
            >
                <template v-if="searchQuery || internalStatusFilter !== 'ALL'" #actions>
                    <HcisButton
                        label="Reset Filter & Pencarian"
                        variant="secondary"
                        size="xs"
                        leading-icon="arrow-counterclockwise"
                        @click="resetFilters"
                    />
                </template>
            </HcisEmptyState>

            <!-- Card Feed using HcisCard Component -->
            <div v-else class="pa-member-card-feed" :class="{ 'is-loading-dimmed': loading }">
                <HcisCard
                    v-for="row in filteredRows"
                    :key="row.participant_id"
                    variant="glass"
                    sentiment="neutral"
                    size="sm"
                    interactive
                    class="pa-member-hcis-card"
                    :class="{ 'is-loading-card': loading }"
                    @click="$emit('detail', row)"
                >
                    <template #header>
                        <div class="pa-member-card-header">
                            <div class="pa-member-card-identity">
                                <span class="pa-member-card-avatar" aria-hidden="true">
                                    {{ inisial(row.nama) }}
                                </span>
                                <div class="pa-member-card-info">
                                    <strong class="pa-member-card-name">{{ row.nama || 'Nama belum tersedia' }}</strong>
                                    <div class="pa-member-card-meta">
                                        <span class="pa-member-card-code">{{ row.kode_karyawan || '—' }}</span>
                                        <span class="pa-member-card-dot" aria-hidden="true">•</span>
                                        <span class="pa-member-card-job">{{ row.nama_jabatan || 'Jabatan belum tersedia' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="pa-member-card-badge">
                                <HcisBadge
                                    v-if="row.terbit"
                                    label="Sudah Terbit"
                                    status="TERBIT"
                                    variant="success"
                                    appearance="subtle"
                                    size="xs"
                                    dot
                                />
                                <HcisBadge
                                    v-else-if="row.dirilis_ke_leader"
                                    label="Rilis Leader"
                                    variant="brand"
                                    appearance="subtle"
                                    size="xs"
                                    dot
                                />
                                <HcisBadge
                                    v-else
                                    label="Belum Terbit"
                                    variant="neutral"
                                    appearance="subtle"
                                    size="xs"
                                    dot
                                />
                            </div>
                        </div>
                    </template>

                    <!-- Card Body Content: Bento Dual Metric Strip & Notices -->
                    <div class="pa-member-card-body">
                        <!-- Bento Dual Metric Strip -->
                        <div class="pa-member-card-bento">
                            <!-- Bento Box 1: Nilai & Grade -->
                            <div class="pa-member-bento-box is-score">
                                <div class="pa-member-bento-label">
                                    <HcisIcon name="award-fill" size="2xs" />
                                    <span>NILAI & GRADE</span>
                                </div>
                                <template v-if="row.terbit || row.dirilis_ke_leader">
                                    <div class="pa-member-bento-score-row">
                                        <strong class="pa-member-bento-score-num">{{ formatScore(row.nilai_akhir) }}</strong>
                                        <HcisBadge
                                            v-if="row.kode_grade"
                                            :label="`Grade ${displayGrade(row.kode_grade)}`"
                                            :variant="getGradeSentiment(row.kode_grade)"
                                            appearance="glass"
                                            size="2xs"
                                        />
                                    </div>
                                    <span v-if="row.kode_kuadran" class="pa-member-bento-quadrant">
                                        <HcisIcon name="grid-3x3-gap-fill" size="2xs" />
                                        {{ formatQuadrantName(row.kode_kuadran) }}
                                    </span>
                                </template>
                                <template v-else>
                                    <div class="pa-member-bento-score-empty">
                                        <span class="pa-member-bento-dash">—</span>
                                        <small class="pa-member-bento-caption">Belum terbit</small>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Notices / Pills (If Calibrated, Held, or Unreleased to Employee) -->
                        <div
                            v-if="row.alasan_tertahan || (row.dirilis_ke_leader && !row.terbit) || (row.terbit && row.dikalibrasi)"
                            class="pa-member-card-notices"
                        >
                            <span v-if="row.alasan_tertahan" class="pa-member-notice-pill is-warning">
                                <HcisIcon name="exclamation-circle-fill" size="2xs" />
                                {{ row.alasan_tertahan }}
                            </span>
                            <span
                                v-else-if="row.dirilis_ke_leader && !row.terbit"
                                class="pa-member-notice-pill is-info"
                                data-uji="hanya-leader"
                            >
                                <HcisIcon name="info-circle-fill" size="2xs" />
                                Karyawan belum diberi tahu
                            </span>
                            <span v-if="row.terbit && row.dikalibrasi === true" class="pa-member-notice-pill is-purple">
                                <HcisIcon name="sliders" size="2xs" />
                                Hasil telah dikalibrasi
                            </span>
                        </div>
                    </div>

                    <!-- Footer Action Button -->
                    <template #footer>
                        <HcisButton
                            :label="row.terbit || row.dirilis_ke_leader ? 'Lihat Hasil Detail' : 'Buka Detail Anggota'"
                            :variant="row.terbit ? 'primary' : 'secondary'"
                            size="sm"
                            :leading-icon="row.terbit ? 'eye-fill' : 'arrow-up-right-square'"
                            block
                            @click.stop="$emit('detail', row)"
                        />
                    </template>
                </HcisCard>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
             DESKTOP VIEW (> 768px): Full Standard Table (HcisTable)
             ═══════════════════════════════════════════════════════════════════ -->
        <HcisTable
            v-else
            :columns="columns"
            :rows="filteredRows"
            row-key="participant_id"
            :loading="loading"
            responsive="scroll"
            size="sm"
            variant="glass"
            sentiment="teal"
            :empty-title="searchQuery || internalStatusFilter !== 'ALL' ? 'Anggota tidak ditemukan' : emptyTitle"
            :empty-description="searchQuery || internalStatusFilter !== 'ALL' ? 'Tidak ada anggota tim yang sesuai dengan kriteria pencarian atau filter yang dipilih.' : emptyDescription"
            empty-icon="people"
        >
            <template #cell-nama="{ row }">
                <div class="pa-member-cell">
                    <span class="pa-member-cell__avatar" aria-hidden="true">{{ inisial(row.nama) }}</span>
                    <span class="pa-member-cell__copy">
                        <strong>{{ row.nama || 'Nama belum tersedia' }}</strong>
                        <div class="pa-member-cell__meta">
                            <span class="pa-member-cell__code">{{ row.kode_karyawan || 'Kode belum tersedia' }}</span>
                            <span class="pa-member-cell__dot" aria-hidden="true">•</span>
                            <small class="pa-member-cell__job">{{ row.nama_jabatan || 'Jabatan belum tersedia' }}</small>
                        </div>
                    </span>
                </div>
            </template>

            <template #cell-status_hasil="{ row }">
                <div class="pa-member-status">
                    <div class="pa-member-status__badge-row">
                        <HcisBadge
                            v-if="row.terbit"
                            label="Sudah Terbit"
                            status="TERBIT"
                            variant="success"
                            appearance="subtle"
                            size="xs"
                            dot
                        />
                        <HcisBadge
                            v-else-if="row.dirilis_ke_leader"
                            label="Rilis Leader"
                            variant="brand"
                            appearance="subtle"
                            size="xs"
                            dot
                        />
                        <HcisBadge
                            v-else
                            label="Belum Terbit"
                            variant="neutral"
                            appearance="subtle"
                            size="xs"
                            dot
                        />
                        <HcisBadge
                            v-if="row.terbit && row.dikalibrasi === true"
                            label="Dikalibrasi"
                            variant="purple"
                            appearance="subtle"
                            size="2xs"
                            dot
                        />
                    </div>
                    <span v-if="row.alasan_tertahan" class="pa-member-status__reason">
                        <HcisIcon name="info-circle" size="2xs" color="warning" />
                        {{ row.alasan_tertahan }}
                    </span>
                    <span v-else-if="row.dirilis_ke_leader && !row.terbit" class="pa-member-status__hint-subtle">
                        Karyawan belum diberi tahu
                    </span>
                    <span v-else-if="row.terbit" class="pa-member-status__hint-published">
                        Dapat dilihat karyawan
                    </span>
                </div>
            </template>


            <template #cell-nilai_akhir="{ row }">
                <div class="pa-member-score">
                    <template v-if="row.terbit || row.dirilis_ke_leader">
                        <div class="pa-member-score__top">
                            <strong class="pa-member-score__value">{{ formatScore(row.nilai_akhir) }}</strong>
                            <HcisBadge
                                v-if="row.kode_grade"
                                :label="`Grade ${displayGrade(row.kode_grade)}`"
                                :variant="getGradeSentiment(row.kode_grade)"
                                appearance="subtle"
                                size="xs"
                            />
                        </div>
                        <span v-if="row.kode_kuadran" class="pa-member-score__quadrant">
                            <HcisIcon name="grid-3x3-gap-fill" size="2xs" />
                            {{ formatQuadrantName(row.kode_kuadran) }}
                        </span>
                        <small v-if="!row.terbit" class="pa-member-score__hint" data-uji="hanya-leader">
                            karyawan belum diberi tahu
                        </small>
                    </template>
                    <template v-else>
                        <div class="pa-member-score__empty">
                            <span class="pa-member-score__dash">—</span>
                            <small class="pa-member-score__hint">Belum terbit</small>
                        </div>
                    </template>
                </div>
            </template>

            <template #cell-aksi="{ row }">
                <HcisButton
                    :label="row.terbit || row.dirilis_ke_leader ? 'Lihat Hasil' : 'Buka Detail'"
                    :variant="row.terbit ? 'primary' : 'secondary'"
                    size="xs"
                    :leading-icon="row.terbit ? 'eye-fill' : 'arrow-up-right-square'"
                    :aria-label="`Lihat detail ${row.nama || 'anggota'}`"
                    @click="$emit('detail', row)"
                />
            </template>
        </HcisTable>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisEmptyState,
    HcisFilterBar,
    HcisIcon,
    HcisProgress,
    HcisTable,
} from '@/components/ui';

defineOptions({ name: 'PaLeaderMemberTable' });

const props = defineProps({
    rows: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    statusFilter: { type: String, default: 'ALL' },
    emptyTitle: { type: String, default: 'Belum ada anggota tim' },
    emptyDescription: { type: String, default: 'Belum ada anggota yang masuk dalam scope tim pada cycle ini.' },
});

const emit = defineEmits(['detail', 'update:statusFilter']);

const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth <= 768 : false);
let mediaQuery = null;

function updateIsMobile(e) {
    isMobile.value = e ? e.matches : (typeof window !== 'undefined' && window.innerWidth <= 768);
}

onMounted(() => {
    if (typeof window !== 'undefined' && window.matchMedia) {
        mediaQuery = window.matchMedia('(max-width: 768px)');
        isMobile.value = mediaQuery.matches;
        if (mediaQuery.addEventListener) {
            mediaQuery.addEventListener('change', updateIsMobile);
        } else if (mediaQuery.addListener) {
            mediaQuery.addListener(updateIsMobile);
        }
    }
});

onBeforeUnmount(() => {
    if (mediaQuery) {
        if (mediaQuery.removeEventListener) {
            mediaQuery.removeEventListener('change', updateIsMobile);
        } else if (mediaQuery.removeListener) {
            mediaQuery.removeListener(updateIsMobile);
        }
    }
});

const searchQuery = ref('');
const internalStatusFilter = ref(props.statusFilter || 'ALL');

watch(() => props.statusFilter, (newVal) => {
    if (newVal) internalStatusFilter.value = newVal;
});

function setFilter(val) {
    internalStatusFilter.value = val;
    emit('update:statusFilter', val);
}

function onSearchChange(val) {
    searchQuery.value = val || '';
}

function onChipClick(chip) {
    setFilter(chip?.value || chip?.key || 'ALL');
}

function resetFilters() {
    searchQuery.value = '';
    setFilter('ALL');
}

/* Kelengkapan penilai 360° SENGAJA tidak ditampilkan ke leader, baik di tabel maupun kartu
   (keputusan user 14 Okt 2026). Server masih mengirim progres_persen; jangan dipasang kembali. */
const columns = [
    { key: 'nama', label: 'Anggota Tim', width: '38%' },
    { key: 'status_hasil', label: 'Status Publikasi', width: '26%' },
    { key: 'nilai_akhir', label: 'Nilai & Grade', width: '22%' },
    { key: 'aksi', label: 'Aksi', width: '14%', align: 'right' },
];

const activeRows = computed(() => {
    return (props.rows || []).filter((r) => r.status_peserta !== 'DIKECUALIKAN');
});

const statusFilterOptions = computed(() => {
    const list = activeRows.value;
    const countAll = list.length;
    const countTerbit = list.filter((r) => r.status_hasil === 'TERBIT' || r.terbit === true).length;
    const countBelum = list.filter((r) => !r.terbit).length;
    const countTerlambat = list.filter((r) => Number(r.penugasan_terlambat) > 0).length;

    return [
        { key: 'ALL', value: 'ALL', label: 'Semua Anggota', badge: countAll, count: countAll, icon: 'people-fill' },
        { key: 'TERBIT', value: 'TERBIT', label: 'Sudah Terbit', badge: countTerbit, count: countTerbit, icon: 'award-fill', color: 'success' },
        { key: 'BELUM_DIHITUNG', value: 'BELUM_DIHITUNG', label: 'Belum Terbit', badge: countBelum, count: countBelum, icon: 'hourglass-split' },
        ...(countTerlambat ? [{ key: 'TERLAMBAT', value: 'TERLAMBAT', label: 'Penilai Terlambat', badge: countTerlambat, count: countTerlambat, icon: 'alarm', color: 'danger' }] : []),
    ];
});

const filteredRows = computed(() => {
    let result = activeRows.value;

    // Filter by status
    if (internalStatusFilter.value === 'TERBIT') {
        result = result.filter((r) => r.status_hasil === 'TERBIT' || r.terbit === true);
    } else if (internalStatusFilter.value === 'BELUM_DIHITUNG' || internalStatusFilter.value === 'BELUM_TERBIT' || internalStatusFilter.value === 'TERTAHAN') {
        result = result.filter((r) => !r.terbit);
    } else if (internalStatusFilter.value === 'TERLAMBAT') {
        result = result.filter((r) => Number(r.penugasan_terlambat) > 0);
    } else if (internalStatusFilter.value === 'LENGKAP') {
        result = result.filter((r) => Number(r.progres_persen) >= 100 || (Number(r.penugasan_selesai) > 0 && r.penugasan_selesai === r.penugasan_total));
    }

    // Filter by search query
    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        result = result.filter((r) => {
            const nama = String(r.nama || '').toLowerCase();
            const kode = String(r.kode_karyawan || '').toLowerCase();
            const jabatan = String(r.nama_jabatan || '').toLowerCase();
            return nama.includes(q) || kode.includes(q) || jabatan.includes(q);
        });
    }

    return result;
});

function angka(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

function formatScore(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed)
        ? parsed.toLocaleString('id-ID', { maximumFractionDigits: 2 })
        : '—';
}

function displayGrade(g) {
    if (!g || g === '—') return '—';
    return String(g)
        .replace(/_PLUS/gi, '+')
        .replace(/_MINUS/gi, '-')
        .replace(/\b([A-E])\s*PLUS\b/gi, '$1+')
        .replace(/\b([A-E])\s*MINUS\b/gi, '$1-')
        .trim();
}

function formatQuadrantName(k) {
    if (!k) return '';
    return String(k).replace(/_/g, ' ');
}

function getGradeSentiment(g) {
    const s = String(g || '').toUpperCase();
    if (s.startsWith('A')) return 'success';
    if (s.startsWith('B')) return 'brand';
    if (s.startsWith('C')) return 'warning';
    if (s.startsWith('D') || s.startsWith('E')) return 'danger';
    return 'neutral';
}

function inisial(value) {
    return String(value || 'PA')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}
</script>

<style scoped>
.pa-leader-member-table,
.pa-leader-member-table :deep(.hcis-table-container) {
    min-width: 0;
    max-width: 100%;
}

.pa-member-filter-bar {
    margin-bottom: 0.85rem;
}

/* ═══════════════════════════════════════════════════════════════════════════
   MOBILE VIEW (<= 768px): BESPOKE EXECUTIVE CARD FEED USING HCISCARD
   ═══════════════════════════════════════════════════════════════════════════ */
.pa-member-mobile-deck {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 100%;
}

/* Syncing Badge & Loading Animations */
.pa-member-syncing-badge {
    position: sticky;
    top: 4.25rem;
    z-index: 20;
    align-self: center;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.9rem;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(13, 148, 136, 0.25);
    box-shadow: 0 8px 20px -4px rgba(13, 148, 136, 0.18), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    margin-bottom: -0.25rem;
    pointer-events: none;
}

.pa-member-spinner-box {
    width: 0.95rem;
    height: 0.95rem;
    position: relative;
    flex-shrink: 0;
}

.pa-member-spinner-svg {
    width: 100%;
    height: 100%;
    animation: pa-spin 0.8s linear infinite;
}

.pa-member-spinner-track {
    stroke: rgba(13, 148, 136, 0.2);
}

.pa-member-spinner-head {
    stroke: var(--hcis-teal-600, #0d9488);
    stroke-linecap: round;
    stroke-dasharray: 28 60;
}

.pa-member-syncing-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-teal-800, #115e59);
    letter-spacing: 0.01em;
    white-space: nowrap;
}

.pa-member-card-feed.is-loading-dimmed {
    opacity: 0.52;
    filter: blur(0.35px);
    pointer-events: none;
    transition: opacity 220ms ease, filter 220ms ease;
}

.pa-member-hcis-card.is-loading-card {
    position: relative;
    overflow: hidden;
}

.pa-member-hcis-card.is-loading-card::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.6) 50%,
        rgba(255, 255, 255, 0) 100%
    );
    transform: translateX(-100%);
    animation: pa-shimmer 1.4s infinite;
    pointer-events: none;
}

@keyframes pa-spin {
    100% { transform: rotate(360deg); }
}

@keyframes pa-shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Loading Pop Transition */
.hcis-loading-pop-enter-active,
.hcis-loading-pop-leave-active {
    transition: opacity 200ms cubic-bezier(0.16, 1, 0.3, 1), transform 200ms cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-loading-pop-enter-from,
.hcis-loading-pop-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.94);
}

.pa-member-mobile-loading,
.pa-member-card-feed {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 100%;
}

.pa-member-skeleton-card {
    min-height: 10.5rem;
}

.pa-member-mobile-empty {
    margin-top: 0.25rem;
}

/* HcisCard Item Customizations */
.pa-member-hcis-card {
    transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.pa-member-hcis-card:active {
    transform: scale(0.985);
}

.pa-member-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
    width: 100%;
}

.pa-member-card-identity {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
    flex: 1;
}

.pa-member-card-avatar {
    display: grid;
    place-items: center;
    flex: 0 0 2.35rem;
    width: 2.35rem;
    height: 2.35rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(20, 184, 166, 0.15) 0%, rgba(13, 148, 136, 0.08) 100%);
    color: var(--hcis-teal-800, #115e59);
    border: 1px solid rgba(20, 184, 166, 0.25);
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: -0.01em;
}

.pa-member-card-info {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    min-width: 0;
    flex: 1;
}

.pa-member-card-name {
    color: var(--hcis-slate-800, #1e293b);
    font-size: 0.92rem;
    font-weight: 800;
    line-height: 1.25;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-member-card-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--hcis-slate-500, #64748b);
    font-size: 0.7rem;
    line-height: 1.2;
    min-width: 0;
}

.pa-member-card-code {
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-slate-400, #94a3b8);
    font-size: 0.68rem;
    flex-shrink: 0;
}

.pa-member-card-dot {
    color: var(--hcis-slate-300, #cbd5e1);
    font-size: 0.65rem;
    flex-shrink: 0;
}

.pa-member-card-job {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-member-card-badge {
    flex-shrink: 0;
}

.pa-member-card-body {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
}

/* Single Metric Strip inside HcisCard */
.pa-member-card-bento {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.45rem;
    width: 100%;
}

.pa-member-bento-box {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.55rem 0.65rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.85);
    min-height: 4.65rem;
}

.pa-member-bento-label,
.pa-member-bento-progress-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.3rem;
}

.pa-member-bento-label {
    color: var(--hcis-slate-400, #94a3b8);
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-member-bento-score-row {
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
    margin-top: 0.15rem;
}

.pa-member-bento-score-num {
    color: var(--hcis-slate-800, #1e293b);
    font-size: 1.15rem;
    font-weight: 850;
    letter-spacing: -0.02em;
    line-height: 1;
}

.pa-member-bento-quadrant {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--hcis-purple-600, #7c3aed);
    font-size: 0.65rem;
    font-weight: 700;
    margin-top: 0.15rem;
}

.pa-member-bento-score-empty {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
    margin-top: 0.15rem;
}

.pa-member-bento-dash {
    color: var(--hcis-slate-300, #cbd5e1);
    font-size: 1.15rem;
    font-weight: 800;
    line-height: 1;
}

.pa-member-bento-caption {
    color: var(--hcis-slate-400, #94a3b8);
    font-size: 0.68rem;
}

.pa-member-bento-percent {
    color: var(--hcis-slate-800, #1e293b);
    font-size: 0.88rem;
    font-weight: 850;
    line-height: 1;
}

.pa-member-bento-progress-track {
    width: 100%;
    height: 5px;
    border-radius: 9999px;
    background: rgba(226, 232, 240, 0.95);
    overflow: hidden;
    margin: 0.35rem 0 0.25rem;
}

.pa-member-bento-progress-fill {
    height: 100%;
    border-radius: 9999px;
    background: linear-gradient(90deg, var(--hcis-slate-400, #94a3b8) 0%, var(--hcis-slate-500, #64748b) 100%);
    transition: width 300ms ease;
}

.pa-member-bento-progress-fill.is-complete {
    background: linear-gradient(90deg, var(--hcis-teal-500, #14b8a6) 0%, var(--hcis-emerald-400, #34d399) 100%);
}

.pa-member-bento-progress-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.3rem;
    font-size: 0.65rem;
    color: var(--hcis-slate-400, #94a3b8);
}

.pa-member-bento-late {
    color: var(--hcis-rose-600, #e11d48);
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}

/* Notices / Pills */
.pa-member-card-notices {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    margin-top: 0.15rem;
}

.pa-member-notice-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.6rem;
    border-radius: var(--hcis-radius-sm, 6px);
    font-size: 0.7rem;
    line-height: 1.35;
    font-weight: 600;
}

.pa-member-notice-pill.is-warning {
    background: rgba(254, 243, 199, 0.85);
    color: var(--hcis-amber-800, #92400e);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.pa-member-notice-pill.is-info {
    background: rgba(238, 242, 255, 0.85);
    color: var(--hcis-indigo-800, #3730a3);
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.pa-member-notice-pill.is-purple {
    background: rgba(243, 232, 255, 0.85);
    color: var(--hcis-purple-800, #6b21a8);
    border: 1px solid rgba(168, 85, 247, 0.3);
}

/* ═══════════════════════════════════════════════════════════════════════════
   DESKTOP TABLE CELLS
   ═══════════════════════════════════════════════════════════════════════════ */
.pa-member-cell,
.pa-member-cell__copy,
.pa-member-status,
.pa-member-progress,
.pa-member-score {
    min-width: 0;
}

.pa-member-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-member-cell__avatar {
    display: grid;
    flex: 0 0 2.25rem;
    place-items: center;
    width: 2.25rem;
    height: 2.25rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.65rem;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-700);
    font-size: 0.72rem;
    font-weight: 700;
}

.pa-member-cell__copy,
.pa-member-status,
.pa-member-score {
    display: flex;
    flex-direction: column;
    gap: 0.16rem;
}

.pa-member-cell__copy strong {
    overflow: hidden;
    color: var(--hcis-slate-800);
    font-size: 0.88rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-member-cell__code {
    color: var(--hcis-slate-400);
    font-size: 0.72rem;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-member-cell__job {
    overflow: hidden;
    color: var(--hcis-slate-500);
    font-size: 0.7rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-member-cell__meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.pa-member-cell__dot {
    color: var(--hcis-slate-300, #cbd5e1);
    font-size: 0.65rem;
    line-height: 1;
}

.pa-member-status__badge-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-member-status__reason {
    display: flex;
    align-items: flex-start;
    gap: 0.28rem;
    max-width: 19rem;
    color: var(--hcis-warning-700, #a16207);
    font-size: 0.7rem;
    line-height: 1.4;
    margin-top: 0.15rem;
}

.pa-member-status__hint-published {
    font-size: 0.6875rem;
    color: var(--hcis-emerald-600, #059669);
    font-weight: 550;
    margin-top: 0.15rem;
}

.pa-member-status__hint-subtle {
    font-size: 0.6875rem;
    color: var(--hcis-indigo-600, #4f46e5);
    font-weight: 550;
    margin-top: 0.15rem;
}

.pa-member-progress__headline {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.pa-member-progress__percent {
    color: var(--hcis-slate-800);
    font-size: 0.88rem;
    font-weight: 800;
}

.pa-member-progress__count {
    color: var(--hcis-slate-400);
    font-size: 0.7rem;
}

.pa-member-progress__late {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-top: 0.28rem;
    color: var(--hcis-warning-700, #a16207);
    font-size: 0.7rem;
    font-weight: 600;
}

.pa-member-score__top {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-member-score__value {
    font-size: 1.05rem;
    font-weight: 850;
    color: var(--hcis-slate-800);
    letter-spacing: -0.02em;
}

.pa-member-score__quadrant {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--hcis-purple-600, #7c3aed);
    font-size: 0.7rem;
    font-weight: 650;
    margin-top: 0.1rem;
}

.pa-member-score__empty {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
}

.pa-member-score__dash {
    color: var(--hcis-slate-300);
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1;
}

.pa-member-score__hint {
    color: var(--hcis-slate-400);
    font-size: 0.68rem;
}
</style>
