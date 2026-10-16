<template>
    <div class="ap-pengguna-layout" :class="{ 'is-catalog-collapsed': catalogCollapsed }">
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ══ LEFT PANE: KATALOG KARYAWAN (USER SELECTOR / NAVIGATOR)      ══ -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <aside v-show="!catalogCollapsed && (mobileView === 'list' || isDesktop)" class="ap-catalog-pane">
            <div class="ap-catalog-card">
                <!-- Header Katalog -->
                <div class="ap-catalog-header">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <span class="ap-kicker">Katalog Otorisasi</span>
                            <h6 class="ap-catalog-title mb-0">
                                {{ semuaKaryawan ? 'Pencarian Karyawan' : 'Daftar Anggota Modul' }}
                            </h6>
                        </div>
                        <div class="d-flex align-items-center gap-1.5">
                            <HcisBadge
                                :label="semuaKaryawan ? `${baris.length} Ditemukan` : `${total} Anggota`"
                                :variant="semuaKaryawan ? 'primary' : 'brand'"
                                appearance="subtle"
                                size="xs"
                            />
                            <!-- Desktop Collapse Toggle Button -->
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                leading-icon="layout-sidebar-inset"
                                title="Ciutkan daftar untuk ruang inspeksi lebih lega"
                                class="d-none d-lg-inline-flex"
                                @click="catalogCollapsed = true"
                            />
                        </div>
                    </div>

                    <!-- Segmented Mode Switcher: Anggota Modul vs Cari Baru -->
                    <div class="ap-mode-tabs mb-2">
                        <button
                            type="button"
                            class="ap-mode-tab"
                            :class="{ 'ap-mode-tab--aktif': !semuaKaryawan }"
                            @click="pilihMode(false)"
                        >
                            <HcisIcon name="people-fill" size="xs" />
                            <span>Anggota Modul</span>
                            <span v-if="total > 0 && !semuaKaryawan" class="ap-mode-badge">
                                {{ total }}
                            </span>
                        </button>
                        <button
                            type="button"
                            class="ap-mode-tab"
                            :class="{ 'ap-mode-tab--aktif': semuaKaryawan }"
                            @click="pilihMode(true)"
                        >
                            <HcisIcon name="person-plus-fill" size="xs" />
                            <span>Cari Karyawan Baru</span>
                        </button>
                    </div>

                    <!-- Official HcisFilterBar Component (Konsisten dengan Tab Peran) -->
                    <HcisFilterBar
                        :search="saring.cari"
                        :search-placeholder="
                            semuaKaryawan ? 'Ketik nama atau NIK karyawan perusahaan…' : 'Cari nama atau NIK anggota…'
                        "
                        :quick-chips="semuaKaryawan ? [] : quickChipsKaryawan"
                        :active-chip-value="chipTingkatAktif"
                        :filters="semuaKaryawan ? [] : filterDefinitions"
                        :filter-values="filterValues"
                        :collapsible="true"
                        variant="flat"
                        size="sm"
                        sentiment="brand"
                        class="ap-katalog-filterbar"
                        @update:search="onSearchChange"
                        @chip-click="onChipClick"
                        @filter-change="onFilterChange"
                        @reset="resetFilter"
                    />
                </div>

                <!-- Scrollable User Card List -->
                <div class="ap-catalog-list">
                    <!-- Loading Skeleton -->
                    <HcisSkeleton v-if="memuat && !baris.length" :lines="6" class="p-2" />

                    <!-- Empty State -->
                    <HcisEmptyState
                        v-else-if="!baris.length"
                        :icon="semuaKaryawan && saring.cari.trim().length < 2 ? 'search' : 'person-x'"
                        :title="
                            semuaKaryawan && saring.cari.trim().length < 2
                                ? 'Ketik Nama atau NIK Karyawan'
                                : 'Tidak Ada Karyawan Ditemukan'
                        "
                        :description="
                            semuaKaryawan && saring.cari.trim().length < 2
                                ? 'Ketik minimal 2 karakter untuk menelusuri data karyawan di direktori perusahaan.'
                                : 'Tidak ada karyawan yang cocok dengan saringan atau kata kunci pencarian.'
                        "
                        size="sm"
                        class="my-4"
                    >
                        <template v-if="!semuaKaryawan && (saring.cari || saring.peran || saring.tingkat)" #actions>
                            <HcisButton
                                variant="secondary"
                                size="xs"
                                leading-icon="arrow-counterclockwise"
                                label="Reset Saringan"
                                @click="resetFilter"
                            />
                        </template>
                    </HcisEmptyState>

                    <!-- User Card Item (HcisCard variant="glass") -->
                    <HcisCard
                        v-for="u in baris"
                        :key="u.id_user"
                        variant="glass"
                        size="sm"
                        interactive
                        class="ap-user-card-item"
                        :sentiment="
                            u.tingkat === 'SUPER_ADMIN' ? 'purple' : u.tingkat === 'ADMIN' ? 'brand' : 'neutral'
                        "
                        :class="{ 'is-selected': dipilih === u.id_user }"
                        @click="pilihUser(u)"
                    >
                        <div class="d-flex align-items-center gap-2.5 w-100">
                            <!-- Avatar Karyawan -->
                            <HcisAvatar :name="u.nama" size="sm" class="flex-shrink-0" />

                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between gap-1 mb-0.5">
                                    <div class="ap-user-nama text-truncate" :title="u.nama">
                                        {{ u.nama }}
                                    </div>
                                    <HcisBadge
                                        v-if="u.tingkat"
                                        :label="infoTingkat[u.tingkat]?.label || u.tingkat"
                                        :variant="infoTingkat[u.tingkat]?.varian || 'neutral'"
                                        appearance="subtle"
                                        size="xs"
                                        class="flex-shrink-0"
                                    />
                                </div>

                                <div class="d-flex align-items-center gap-1.5 ap-user-meta-row text-muted">
                                    <span class="font-monospace fw-semibold">{{ u.kode_karyawan || '—' }}</span>
                                    <template v-if="u.jumlah_grant > 0">
                                        <span>·</span>
                                        <span class="ap-grant-badge"> {{ u.jumlah_grant }} grant langsung </span>
                                    </template>
                                </div>

                                <!-- Ringkasan Peran yang Dipegang -->
                                <div v-if="u.peran && u.peran.length" class="d-flex flex-wrap gap-1 mt-1.5">
                                    <span
                                        v-for="pr in u.peran.slice(0, 2)"
                                        :key="pr.id_penugasan"
                                        class="ap-role-pill"
                                        :title="pr.nama"
                                    >
                                        {{ pr.nama }}
                                    </span>
                                    <span v-if="u.peran.length > 2" class="ap-role-pill-more">
                                        +{{ u.peran.length - 2 }}
                                    </span>
                                </div>
                                <div v-else-if="semuaKaryawan" class="text-muted ap-kecil mt-1 fst-italic">
                                    Bukan anggota modul · Klik untuk menugaskan peran
                                </div>
                            </div>

                            <!-- Indicator Chevron -->
                            <HcisIcon
                                name="chevron-right"
                                size="xs"
                                class="ap-card-arrow align-self-center flex-shrink-0"
                            />
                        </div>
                    </HcisCard>
                </div>

                <!-- Footer Pagination (HcisPagination Sederhana) -->
                <div v-if="total > perHalaman && !semuaKaryawan" class="ap-catalog-footer">
                    <HcisPagination
                        :current-page="halaman"
                        :page-size="perHalaman"
                        :total="total"
                        size="xs"
                        layout="prev, pager, next"
                        @update:current-page="muat"
                    />
                </div>
            </div>
        </aside>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ══ RIGHT PANE: WORKSPACE INSPEKSI PENGGUNA                      ══ -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <main v-show="mobileView === 'detail' || isDesktop" class="ap-workspace-pane">
            <div class="ap-workspace-card">
                <!-- Mobile Navigation Bar (Kembali ke Daftar Karyawan) -->
                <div class="ap-mobile-nav d-lg-none">
                    <HcisButton
                        variant="secondary"
                        size="xs"
                        leading-icon="arrow-left"
                        label="Daftar Karyawan"
                        @click="mobileView = 'list'"
                    />
                    <div class="ap-mobile-title text-truncate fw-bold small text-dark">
                        {{ userDipilihObj?.nama || 'Inspeksi Karyawan' }}
                    </div>
                </div>

                <!-- Desktop Bar Saat Katalog Diciutkan -->
                <div v-if="catalogCollapsed" class="ap-uncollapse-bar d-none d-lg-flex">
                    <HcisButton
                        variant="ghost"
                        size="xs"
                        leading-icon="layout-sidebar"
                        :label="`Tampilkan Daftar Karyawan (${total} Anggota)`"
                        @click="catalogCollapsed = false"
                    />
                    <div class="text-muted ap-kecil">
                        Daftar karyawan disembunyikan untuk memberikan ruang inspeksi otorisasi maksimal.
                    </div>
                </div>

                <!-- Empty State (Belum ada yang dipilih) -->
                <div v-if="!dipilih" class="ap-workspace-empty">
                    <HcisEmptyState
                        icon="person-bounding-box"
                        title="Pilih Karyawan di Sebelah Kiri"
                        description="Pilih salah satu anggota modul atau gunakan pencarian untuk memeriksa profil, peran aktif, dispensasi akses langsung, dan menelusuri matriks izin efektifnya."
                        size="md"
                    />
                </div>

                <!-- Panel Inspeksi Pengguna Terpilih -->
                <PanelPengguna v-else :key="dipilih" :id-user="dipilih" embedded @berubah="muat(halaman)" />
            </div>
        </main>
    </div>
</template>

<script setup>
import { computed, inject, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import {
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisEmptyState,
    HcisFilterBar,
    HcisIcon,
    HcisPagination,
    HcisSkeleton,
    hcisMessage,
} from '@/components/ui';
import PanelPengguna from './PanelPengguna.vue';
import { aksesPeranApi, infoTingkat } from '../api/aksesPeranApi.js';

const props = defineProps({
    peran: { type: Array, default: () => [] },
    filterAwal: { type: Object, default: () => ({}) },
});
const aksi = inject('aksiAkses');

// ── Layout & Responsive States (Seragam dengan TabPeran) ──
const catalogCollapsed = ref(false);
const mobileView = ref('list'); // 'list' | 'detail'
const isDesktop = ref(window.innerWidth >= 992);

const handleResize = () => {
    isDesktop.value = window.innerWidth >= 992;
};
onMounted(() => window.addEventListener('resize', handleResize));
onUnmounted(() => window.removeEventListener('resize', handleResize));

// ── Saringan & Data States ──
const saring = reactive({ cari: '', peran: null, tingkat: null });
const chipTingkatAktif = ref('semua');
const semuaKaryawan = ref(false);

const baris = ref([]);
const total = ref(0);
const halaman = ref(1);
const perHalaman = ref(20);
const memuat = ref(false);
const dipilih = ref(null);
let penunda = null;

const userDipilihObj = computed(() => baris.value.find((b) => b.id_user === dipilih.value));

// ── Quick Chips untuk Tingkat Peran ──
const quickChipsKaryawan = [
    {
        id: 'semua',
        value: 'semua',
        label: 'Semua',
        icon: 'people-fill',
    },
    {
        id: 'super',
        value: 'SUPER_ADMIN',
        label: 'Super Admin',
        icon: 'shield-lock-fill',
        color: 'purple',
    },
    {
        id: 'admin',
        value: 'ADMIN',
        label: 'Admin Modul',
        icon: 'shield-check',
        color: 'primary',
    },
    {
        id: 'pengguna',
        value: 'PENGGUNA',
        label: 'Pelaksana',
        icon: 'person',
        color: 'neutral',
    },
];

// ── Dropdown Filter Definitions untuk HcisFilterBar ──
const opsiPeran = computed(() =>
    (props.peran || []).map((p) => ({
        label: p.nama,
        value: p.id,
    })),
);

const filterDefinitions = computed(() => {
    const list = [];
    if (opsiPeran.value.length > 0) {
        list.push({
            key: 'peran',
            label: 'Peran Modul',
            type: 'select',
            placeholder: 'Semua peran',
            options: opsiPeran.value,
        });
    }
    return list;
});

const filterValues = computed(() => ({
    peran: saring.peran,
}));

function onSearchChange(v) {
    saring.cari = v;
    jadwalkan();
}

function onChipClick(chip) {
    chipTingkatAktif.value = chip.value;
    saring.tingkat = chip.value === 'semua' ? null : chip.value;
    muat(1);
}

function onFilterChange({ key, value }) {
    if (key === 'peran') {
        saring.peran = value || null;
        muat(1);
    }
}

function resetFilter() {
    saring.cari = '';
    saring.peran = null;
    saring.tingkat = null;
    chipTingkatAktif.value = 'semua';
    muat(1);
}

function pilihMode(semua) {
    semuaKaryawan.value = semua;
    saring.cari = '';
    saring.peran = null;
    saring.tingkat = null;
    chipTingkatAktif.value = 'semua';
    baris.value = [];
    muat(1);
}

function pilihUser(u) {
    dipilih.value = u.id_user;
    if (!isDesktop.value) {
        mobileView.value = 'detail';
    }
}

// ── Ambil Data API ──
async function muat(h = 1) {
    memuat.value = true;
    try {
        if (semuaKaryawan.value) {
            baris.value =
                saring.cari.trim().length >= 2 ? (await aksesPeranApi.cariPengguna(saring.cari.trim())) || [] : [];
            if (baris.value.length && (!dipilih.value || !baris.value.some((b) => b.id_user === dipilih.value))) {
                dipilih.value = baris.value[0].id_user;
            }
            return;
        }
        const r = await aksesPeranApi.anggota({
            cari: saring.cari.trim() || undefined,
            peran: saring.peran || undefined,
            tingkat: saring.tingkat || undefined,
            halaman: h,
        });
        baris.value = r?.data || [];
        total.value = r?.total || 0;
        halaman.value = r?.halaman || h;
        perHalaman.value = r?.per_halaman || 20;

        // Otomatis pilih item pertama jika belum terpilih
        if (baris.value.length && (!dipilih.value || !baris.value.some((b) => b.id_user === dipilih.value))) {
            dipilih.value = baris.value[0].id_user;
        }
    } catch {
        hcisMessage.error('Gagal memuat daftar karyawan.');
    } finally {
        memuat.value = false;
    }
}

function jadwalkan() {
    clearTimeout(penunda);
    penunda = setTimeout(() => muat(1), 300);
}

watch(
    () => props.filterAwal,
    (fa) => {
        if (fa && fa.hanyaSegeraBerakhir) {
            semuaKaryawan.value = false;
            muat(1);
        }
    },
    { immediate: true },
);

onMounted(() => muat(1));
</script>

<style scoped>
/* ══ HCIS TYPOGRAPHY HIERARCHY ══ */
.ap-kicker {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--hcis-brand-600, #0284c7);
    line-height: 1.25;
    display: inline-block;
}

.ap-catalog-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    letter-spacing: -0.015em;
    line-height: 1.35;
}

.ap-kecil {
    font-size: 0.75rem;
    line-height: 1.45;
    color: var(--hcis-text-secondary, #64748b);
}

/* ══ COCKPIT DUAL-PANE LAYOUT (SERAGAM DENGAN TAB PERAN) ══ */
.ap-pengguna-layout {
    display: flex;
    gap: 1rem;
    align-items: stretch;
    min-height: 650px;
    height: calc(100vh - 220px);
    width: 100%;
}

/* ══ LEFT CATALOG PANE ══ */
.ap-catalog-pane {
    width: 360px;
    flex-shrink: 0;
    height: 100%;
    transition: all 0.25s ease;
}

.ap-catalog-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--hcis-surface, #ffffff);
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow:
        inset 0 1px 0 var(--hcis-white, #ffffff),
        0 4px 20px -2px rgba(15, 23, 42, 0.08),
        0 1px 3px 0 rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.ap-catalog-header {
    padding: var(--hcis-space-3, 0.75rem);
    background: var(--hcis-surface, #ffffff);
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
    flex-shrink: 0;
}

/* ══ FILTER BAR STYLING DI SIDEBAR (PERSIS DENGAN TAB PERAN) ══ */
.ap-katalog-filterbar {
    padding: 0 !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

.ap-katalog-filterbar :deep(.hcis-filter-bar__main) {
    padding: 0 !important;
    margin-bottom: 0.35rem;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    width: 100%;
}

.ap-katalog-filterbar :deep(.hcis-filter-bar__search) {
    width: 100%;
    flex: 1 1 100%;
}

.ap-katalog-filterbar :deep(.hcis-filter-bar__actions) {
    display: flex;
    width: 100%;
    gap: 0.35rem;
}

.ap-katalog-filterbar :deep(.hcis-filter-toggle-btn),
.ap-katalog-filterbar :deep(.hcis-filter-reset-btn) {
    flex: 1 1 0%;
    width: 100%;
    justify-content: center;
    text-align: center;
    height: 2rem;
    border-radius: var(--hcis-radius-md, 6px);
}

.ap-katalog-filterbar :deep(.hcis-filter-bar__chips) {
    margin-bottom: 0.25rem;
    padding-bottom: 0.25rem;
    border-bottom: none !important;
}

.ap-katalog-filterbar :deep(.hcis-filter-chips-wrapper) {
    display: flex !important;
    flex-wrap: nowrap !important;
    overflow-x: auto !important;
    white-space: nowrap !important;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
    gap: 0.35rem;
    padding-bottom: 0.25rem;
    -webkit-overflow-scrolling: touch;
}

.ap-katalog-filterbar :deep(.hcis-filter-chips-wrapper::-webkit-scrollbar) {
    display: none !important;
    height: 0 !important;
}

.ap-katalog-filterbar :deep(.hcis-chip-btn) {
    flex-shrink: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

/* Mode Switcher Tabs */
.ap-mode-tabs {
    display: flex;
    padding: 2px;
    background: var(--hcis-surface-subtle, #f1f5f9);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 0.5rem);
}

.ap-mode-tab {
    flex: 1 1 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.35rem 0.5rem;
    border: none;
    background: transparent;
    border-radius: var(--hcis-radius-sm, 0.375rem);
    font-size: var(--hcis-font-size-xs, 0.75rem);
    font-weight: 600;
    color: var(--hcis-text-secondary, #475569);
    cursor: pointer;
    transition: all 0.15s ease;
}

.ap-mode-tab:hover {
    color: var(--hcis-brand-700, #0369a1);
}

.ap-mode-tab--aktif {
    background: var(--hcis-surface, #ffffff);
    color: var(--hcis-brand-700, #0369a1);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.ap-mode-badge {
    padding: 0.05rem 0.35rem;
    border-radius: 9999px;
    background: #e0f2fe;
    color: var(--hcis-brand-700, #0369a1);
    font-size: 0.6875rem;
    font-weight: 700;
}

/* Scrollable User List */
.ap-catalog-list {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: var(--hcis-space-2, 0.5rem);
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    background: var(--hcis-surface-subtle, #f8fafc);
}

/* User Item Card (HcisCard variant="glass") */
.ap-user-card-item {
    cursor: pointer;
    flex-shrink: 0 !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    margin-bottom: 0.4rem;
    border-radius: var(--hcis-radius-lg, 10px);
    position: relative;
}

.ap-user-card-item :deep(.hcis-card__body) {
    padding: 0.65rem 0.75rem !important;
}

.ap-user-card-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.08) !important;
}

.ap-user-card-item.is-selected {
    box-shadow:
        0 0 0 2px var(--hcis-brand-500, #0284c7),
        0 6px 18px -2px rgba(2, 132, 199, 0.25) !important;
}

.ap-user-card-item.hcis-card--sentiment-purple.is-selected {
    box-shadow:
        0 0 0 2px #8b5cf6,
        0 6px 18px -2px rgba(139, 92, 246, 0.25) !important;
}

.ap-user-nama {
    font-size: 0.85rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.35;
    letter-spacing: -0.015em;
}

.ap-user-meta-row {
    font-size: 0.725rem;
    line-height: 1.3;
}

.ap-grant-badge {
    color: #b45309;
    font-weight: 600;
    background: #fef3c7;
    padding: 0.05rem 0.35rem;
    border-radius: 3px;
    font-size: 0.6875rem;
}

.ap-role-pill {
    font-size: 0.6875rem;
    font-weight: 500;
    padding: 0.12rem 0.45rem;
    background: #ffffff;
    color: #475569;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    max-width: 10rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ap-role-pill-more {
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-brand-700, #0369a1);
    padding: 0.12rem 0.35rem;
    background: #e0f2fe;
    border-radius: 4px;
}

.ap-card-arrow {
    color: var(--hcis-text-subtle, #94a3b8);
    opacity: 0.5;
    transition: all 0.2s ease;
}

.ap-user-card-item:hover .ap-card-arrow,
.ap-user-card-item.is-selected .ap-card-arrow {
    opacity: 1;
    transform: translateX(3px);
    color: var(--hcis-brand-600, #0284c7) !important;
}

.ap-catalog-footer {
    padding: 0.5rem 0.75rem;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: center;
    flex-shrink: 0;
}

/* ══ RIGHT WORKSPACE PANE ══ */
.ap-workspace-pane {
    flex: 1 1 0%;
    min-width: 0;
    height: 100%;
}

.ap-workspace-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--hcis-surface, #ffffff);
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow:
        inset 0 1px 0 var(--hcis-white, #ffffff),
        0 4px 20px -2px rgba(15, 23, 42, 0.08),
        0 1px 3px 0 rgba(15, 23, 42, 0.04);
    overflow-y: auto;
    padding: var(--hcis-space-4, 1rem) var(--hcis-space-5, 1.25rem);
}

.ap-uncollapse-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.35rem 0.65rem;
    margin-bottom: 0.75rem;
    background: var(--hcis-surface-subtle, #f8fafc);
    border: 1px dashed var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
}

.ap-mobile-nav {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 0.75rem;
    margin-bottom: 0.75rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.ap-workspace-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 420px;
}

.min-w-0 {
    min-width: 0;
}
</style>
