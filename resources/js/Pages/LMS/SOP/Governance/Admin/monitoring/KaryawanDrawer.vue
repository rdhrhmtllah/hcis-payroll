<template>
    <HcisDrawer
        :model-value="show"
        size="xl"
        sentiment="primary"
        direction="rtl"
        :show-close="false"
        @update:model-value="(v) => { if (!v) $emit('close'); }"
    >
        <!-- Slim Executive Header -->
        <template #header>
            <div class="d-flex align-items-center justify-content-between w-100 py-1">
                <div class="d-flex align-items-center min-w-0 flex-grow-1 pe-3">
                    <div class="kd-avatar-box me-3 flex-shrink-0">
                        <HcisAvatar
                            :name="profilNama"
                            size="md"
                            shape="squircle"
                        />
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <div class="d-flex align-items-center flex-wrap">
                            <span class="kd-emp-name me-2.5 text-truncate" :title="profilNama">
                                {{ profilNama }}
                            </span>
                            <span v-if="profilKode" class="kd-emp-nik-badge me-2">{{ profilKode }}</span>
                            <span class="kd-emp-jabatan-badge">{{ profilJabatan || profilDept || 'Karyawan' }}</span>
                        </div>
                        <div class="kd-header-sub-meta mt-1">
                            <span class="fw-semibold text-slate-500">
                                <HcisIcon name="collection" size="2xs" class="me-1 text-indigo-500" />
                                {{ kpiSummary.total_sop }} Paket Dokumen Ditugaskan
                            </span>
                            <span class="mx-2 text-slate-300">•</span>
                            <span class="fw-bold" :class="meterColorClass(kpiSummary.persen)">
                                <HcisIcon name="check-circle-fill" size="2xs" class="me-1 text-emerald-500" />
                                {{ kpiSummary.persen }}% Kepatuhan ({{ kpiSummary.selesai }}/{{ kpiSummary.total_sop }})
                            </span>
                        </div>
                    </div>
                </div>

                <HcisButton
                    variant="ghost"
                    size="sm"
                    icon-only
                    leading-icon="x-lg"
                    aria-label="Tutup"
                    class="kd-close-btn flex-shrink-0"
                    @click="$emit('close')"
                />
            </div>
        </template>

        <!-- Body Content: Spacious Scrollable KPI & Penugasan Dokumen -->
        <div class="kd-body-wrap">
            <!-- Loading Skeleton Shimmer State -->
            <div v-if="loading" class="kd-skeleton-list">
                <HcisSkeleton type="card" />
                <HcisSkeleton type="card" />
                <HcisSkeleton type="card" />
            </div>

            <template v-else>
                <!-- 1. KPI Dashboard Summary Box (Scrolls with Body) -->
                <div class="kd-kpi-summary-box mb-4">
                    <div class="kd-kpi-grid">
                        <div class="kd-kpi-cell">
                            <span class="kd-kpi-lbl"><HcisIcon name="collection" size="2xs" class="text-indigo-600 me-1" /> Total</span>
                            <span class="kd-kpi-num">{{ kpiSummary.total_sop }}</span>
                        </div>
                        <div class="kd-kpi-cell">
                            <span class="kd-kpi-lbl"><HcisIcon name="check-circle-fill" size="2xs" class="text-emerald-600 me-1" /> Selesai</span>
                            <span class="kd-kpi-num text-emerald-600">{{ kpiSummary.selesai }}</span>
                        </div>
                        <div class="kd-kpi-cell">
                            <span class="kd-kpi-lbl"><HcisIcon name="hourglass-split" size="2xs" class="text-amber-600 me-1" /> Berjalan</span>
                            <span class="kd-kpi-num text-amber-600">{{ kpiSummary.progress }}</span>
                        </div>
                        <div class="kd-kpi-cell" :class="{ 'is-danger-alert': kpiSummary.terlambat > 0 }">
                            <span class="kd-kpi-lbl"><HcisIcon name="exclamation-triangle-fill" size="2xs" :class="kpiSummary.terlambat > 0 ? 'text-rose-600' : 'text-slate-400'" class="me-1" /> Telat</span>
                            <span class="kd-kpi-num" :class="kpiSummary.terlambat > 0 ? 'text-rose-600' : 'text-slate-500'">{{ kpiSummary.terlambat }}</span>
                        </div>
                    </div>

                    <!-- Overall Compliance Meter Bar -->
                    <div class="kd-kpi-compliance-bar">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="small fw-semibold text-secondary">Tingkat Kepatuhan Dokumen Keseluruhan</span>
                            <span class="small fw-bold" :class="meterColorClass(kpiSummary.persen)">
                                {{ kpiSummary.persen }}% Selesai
                            </span>
                        </div>
                        <div class="kd-meter-track">
                            <div
                                class="kd-meter-fill"
                                :class="meterFillClass(kpiSummary.persen)"
                                :style="{ width: `${Math.min(kpiSummary.persen || 0, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- 2. Sticky Sub-Filter Box inside Drawer -->
                <div v-if="paket.length > 1" class="kd-subfilter-box">
                    <div class="kd-subfilter-search">
                        <HcisField
                            v-model="searchSop"
                            type="search"
                            placeholder="Cari nama paket dokumen..."
                            clearable
                            size="sm"
                            class="w-100"
                        >
                            <template #prefix>
                                <HcisIcon name="search" size="xs" class="text-muted" />
                            </template>
                        </HcisField>
                    </div>

                    <div class="kd-chip-list">
                        <button
                            v-for="st in statusChips"
                            :key="st.value"
                            type="button"
                            class="kd-mini-chip"
                            :class="{ 'is-active': filterStatusSop === st.value }"
                            @click="filterStatusSop = st.value"
                        >
                            <span>{{ st.label }}</span>
                            <span v-if="st.count !== undefined" class="kd-mini-chip-badge">{{ st.count }}</span>
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <HcisEmptyState
                    v-if="!filteredPaket.length"
                    :title="paket.length ? 'Tidak Ada Paket yang Cocok' : 'Belum Ada Penugasan Dokumen'"
                    :description="paket.length ? 'Silakan ubah kata kunci pencarian atau status filter.' : 'Karyawan ini belum memiliki paket dokumen yang terdistribusi.'"
                    :icon="paket.length ? 'search' : 'journal-x'"
                    size="sm"
                    sentiment="neutral"
                    class="my-4"
                />

                <!-- 3. List of Roomy Document Package Cards -->
                <div v-else class="kd-card-list">
                    <div
                        v-for="p in filteredPaket"
                        :key="p.Id_SOP_Paket"
                        class="kd-item-card"
                    >
                        <!-- Card Header: Title, Category & Status Badge -->
                        <div class="kd-card-head">
                            <div class="kd-card-title-col">
                                <div class="kd-item-title" :title="p.Nama_Paket">
                                    {{ p.Nama_Paket }}
                                </div>
                                <div class="kd-card-meta-row">
                                    <span v-if="p.Total_Item || p.total_item" class="kd-item-count-badge">
                                        <HcisIcon name="file-earmark-text" size="2xs" class="me-1 opacity-70" />
                                        {{ p.Total_Item || p.total_item }} Dokumen
                                    </span>
                                </div>
                            </div>
                            <div class="kd-card-status-col">
                                <HcisBadge
                                    :label="statusLabel(p.Status_Paket)"
                                    :variant="statusBadgeVariant(p.Status_Paket)"
                                    size="xs"
                                    dot
                                />
                            </div>
                        </div>

                        <!-- Progress Meter -->
                        <div class="kd-meter-block">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="kd-meter-label">Progres Kepatuhan</span>
                                <span class="kd-meter-val" :class="meterColorClass(p.Persen)">
                                    {{ p.Persen || 0 }}%
                                </span>
                            </div>
                            <div class="kd-meter-track">
                                <div
                                    class="kd-meter-fill"
                                    :class="meterFillClass(p.Persen)"
                                    :style="{ width: `${Math.min(p.Persen || 0, 100)}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Card Footer Meta Tags & Action Link -->
                        <div class="kd-card-footer">
                            <div class="kd-card-footer-tags">
                                <span class="kd-meta-item me-2">
                                    <HcisIcon name="calendar3" size="2xs" class="opacity-70 me-1" />
                                    {{ p.Tenggat || 'Tanpa batas tenggat' }}
                                </span>
                                <HcisBadge
                                    v-if="p.Is_Terlambat"
                                    label="Terlambat"
                                    variant="danger"
                                    size="xs"
                                    icon="exclamation-triangle-fill"
                                    pulse
                                    class="me-2"
                                />
                                <HcisBadge
                                    v-if="p.Is_Diperbarui"
                                    label="Versi Baru"
                                    variant="purple"
                                    size="xs"
                                    icon="stars"
                                    class="me-2"
                                />
                            </div>

                            <HcisButton
                                :href="`/sop/admin/monitoring/karyawan/${profilToken}/paket/${p.Id_SOP_Paket}`"
                                variant="secondary"
                                size="xs"
                                trailing-icon="chevron-right"
                                label="Detail Paket"
                            />
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Footer Actions -->
        <template #footer>
            <div class="kd-footer-row">
                <HcisButton
                    variant="secondary"
                    size="md"
                    label="Tutup"
                    @click="$emit('close')"
                />
                <div class="kd-footer-actions">
                    <HcisButton
                        v-if="kpiSummary.terlambat > 0 || kpiSummary.progress > 0"
                        variant="warning"
                        size="md"
                        leading-icon="bell-fill"
                        label="Kirim Pengingat"
                        :loading="mengingatkan"
                        @click="ingatkan"
                    />
                    <HcisButton
                        :href="`/sop/admin/monitoring/karyawan/${profilToken}`"
                        variant="brand"
                        size="md"
                        leading-icon="box-arrow-up-right"
                        label="Buka Layar Penuh"
                    />
                </div>
            </div>
        </template>
    </HcisDrawer>
</template>

<script>
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    HcisDrawer,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisEmptyState,
    HcisIcon,
    HcisSkeleton,
    HcisField,
} from '@/components/ui';
import { STATUS_PAKET } from '../../../Shared/support/constants.js';

const API = '/api/v1/sop/admin/monitoring';

export default {
    name: 'KaryawanDrawer',
    components: {
        HcisDrawer,
        HcisAvatar,
        HcisBadge,
        HcisButton,
        HcisEmptyState,
        HcisIcon,
        HcisSkeleton,
        HcisField,
    },
    props: {
        show: { type: Boolean, default: false },
        kode: { type: String, default: '' },
        token: { type: String, default: '' },
        nama: { type: String, default: '' },
    },
    emits: ['close'],
    data() {
        return {
            loading: false,
            mengingatkan: false,
            searchSop: '',
            filterStatusSop: 'all',
            ringkas: {},
            paket: [],
        };
    },
    computed: {
        profilNama() {
            return this.ringkas.Nama || this.nama || this.kode;
        },
        profilKode() {
            return this.ringkas.Kode_Karyawan || this.kode || '';
        },
        profilNik() {
            return this.ringkas.No_Induk_Karyawan || '';
        },
        profilDept() {
            return this.ringkas.Departemen || '';
        },
        profilDivisi() {
            return this.ringkas.Divisi || '';
        },
        profilJabatan() {
            return this.ringkas.Jabatan || '';
        },
        profilToken() {
            return this.ringkas.Token || this.token || this.kode;
        },
        kpiSummary() {
            const r = this.ringkas || {};
            const list = this.paket || [];
            const totalSop = r.total_sop ?? list.length ?? 0;
            const selesai = r.total_selesai ?? r.selesai ?? list.filter((p) => (p.Status_Paket || '').toUpperCase() === 'SELESAI').length;
            const terlambat = r.total_terlambat ?? r.terlambat ?? list.filter((p) => p.Is_Terlambat).length;
            const progress = r.total_progress ?? r.progress ?? Math.max(0, totalSop - selesai);
            const persen = r.persen ?? (totalSop > 0 ? Math.round((selesai / totalSop) * 100) : 0);

            return { total_sop: totalSop, selesai, progress, terlambat, persen };
        },
        statusChips() {
            const list = this.paket || [];
            return [
                { label: 'Semua', value: 'all', count: list.length },
                { label: 'Selesai', value: 'selesai', count: list.filter((p) => (p.Status_Paket || '').toUpperCase() === 'SELESAI').length },
                { label: 'Sedang Berjalan', value: 'progress', count: list.filter((p) => (p.Status_Paket || '').toUpperCase() !== 'SELESAI' && !p.Is_Terlambat).length },
                { label: 'Terlambat', value: 'terlambat', count: list.filter((p) => p.Is_Terlambat).length },
            ];
        },
        filteredPaket() {
            let list = this.paket || [];
            if (this.searchSop) {
                const q = this.searchSop.toLowerCase();
                list = list.filter((p) => (p.Nama_Paket || '').toLowerCase().includes(q));
            }
            if (this.filterStatusSop !== 'all') {
                if (this.filterStatusSop === 'selesai') {
                    list = list.filter((p) => (p.Status_Paket || '').toUpperCase() === 'SELESAI');
                } else if (this.filterStatusSop === 'terlambat') {
                    list = list.filter((p) => p.Is_Terlambat);
                } else if (this.filterStatusSop === 'progress') {
                    list = list.filter((p) => (p.Status_Paket || '').toUpperCase() !== 'SELESAI' && !p.Is_Terlambat);
                }
            }
            return list;
        },
    },
    watch: {
        show(val) {
            if (val && (this.token || this.kode || this.profilToken)) {
                this.fetchData();
            }
        },
    },
    methods: {
        async fetchData() {
            const target = this.token || this.kode || this.profilToken;
            if (!target) return;
            this.loading = true;
            try {
                const r = await axios.get(`${API}/karyawan/${encodeURIComponent(target)}`);
                if (r.data.success) {
                    this.ringkas = r.data.result.ringkas || r.data.result.karyawan || r.data.result || {};
                    const rawPaket = r.data.result.paket;
                    if (Array.isArray(rawPaket)) {
                        this.paket = rawPaket;
                    } else if (rawPaket && Array.isArray(rawPaket.items)) {
                        this.paket = rawPaket.items;
                    } else {
                        this.paket = [];
                    }
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal memuat rincian karyawan');
            } finally {
                this.loading = false;
            }
        },
        statusLabel(s) {
            return (STATUS_PAKET[s] || {}).label || s || 'Belum';
        },
        statusBadgeVariant(s) {
            const raw = String(s || '').toUpperCase();
            if (raw === 'SELESAI' || raw === 'LULUS') return 'success';
            if (raw === 'PROGRESS' || raw === 'PROGRES' || raw === 'PARTIAL') return 'warning';
            if (raw === 'TERLAMBAT') return 'danger';
            return 'neutral';
        },
        meterColorClass(persen) {
            const p = Number(persen) || 0;
            return p >= 80 ? 'text-emerald-600' : p >= 40 ? 'text-amber-600' : 'text-rose-600';
        },
        meterFillClass(persen) {
            const p = Number(persen) || 0;
            return p >= 100 ? 'is-emerald' : p >= 80 ? 'is-indigo' : p >= 40 ? 'is-amber' : 'is-rose';
        },
        async ingatkan() {
            try {
                await ElMessageBox.confirm(
                    `Kirim notifikasi pengingat penugasan dokumen kepada ${this.profilNama}?`,
                    'Konfirmasi Pengingat',
                    { confirmButtonText: 'Kirim', cancelButtonText: 'Batal', type: 'warning' },
                );
            } catch (e) {
                return;
            }

            this.mengingatkan = true;
            try {
                const r = await axios.post(`${API}/karyawan/${this.profilToken}/ingatkan`);
                if (r.data.success) {
                    ElMessage.success(r.data.message || 'Pengingat berhasil dikirim');
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal mengirim pengingat');
            } finally {
                this.mengingatkan = false;
            }
        },
    },
};
</script>

<style scoped>
/* ============ DRAWER HEADER ============ */
.kd-avatar-box {
    margin-right: 14px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
}

.kd-emp-name {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    letter-spacing: -0.01em;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kd-emp-nik-badge {
    font-size: 0.72rem;
    font-weight: 750;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
    padding: 1px 6px;
    border-radius: 6px;
    font-family: var(--bs-font-monospace);
}

.kd-emp-jabatan-badge {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 6px;
    padding: 1px 7px;
}

.kd-header-sub-meta {
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    display: flex;
    align-items: center;
}

.kd-close-btn {
    color: var(--hcis-slate-500) !important;
}
.kd-close-btn:hover {
    color: var(--hcis-slate-900) !important;
    background: var(--hcis-slate-100) !important;
}

/* ============ KPI SUMMARY DASHBOARD BOX ============ */
.kd-kpi-summary-box {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    padding: 16px 18px;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
}

.kd-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    grid-gap: 12px;
    gap: 12px;
}

.kd-kpi-cell {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 10px 12px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 0.2s ease;
}
.kd-kpi-cell:hover {
    background: var(--hcis-white);
    border-color: var(--hcis-slate-300);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}
.kd-kpi-cell.is-danger-alert {
    background: var(--hcis-red-50);
    border-color: var(--hcis-red-200);
    box-shadow: 0 1px 4px rgba(239, 68, 68, 0.1);
}

.kd-kpi-lbl {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    display: flex;
    align-items: center;
    justify-content: center;
}

.kd-kpi-num {
    font-size: 1.18rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    line-height: 1.2;
    margin-top: 4px;
    font-variant-numeric: tabular-nums;
}

.kd-kpi-compliance-bar {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid var(--hcis-slate-100);
}

/* ============ BODY WRAPPER ============ */
.kd-body-wrap {
    padding: 0 0 18px;
}

/* ============ SKELETON LIST ============ */
.kd-skeleton-list > * + * {
    margin-top: 16px;
}

/* ============ STICKY SUB-FILTER TOOLBAR CONTAINER ============ */
.kd-subfilter-box {
    position: sticky;
    top: -1.25rem;
    margin: -1.25rem -1.375rem 1.5rem -1.375rem;
    padding: 1.15rem 1.375rem 1.15rem 1.375rem;
    background: var(--hcis-white);
    border-bottom: 1px solid var(--hcis-slate-200);
    box-shadow: 0 6px 18px -4px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
    z-index: 25;
}

.kd-subfilter-search {
    margin-bottom: 14px;
}

.kd-chip-list {
    display: flex;
    align-items: center;
    overflow-x: auto;
    padding-bottom: 2px;
}
.kd-chip-list .kd-mini-chip {
    margin-right: 8px;
    flex-shrink: 0;
}
.kd-chip-list .kd-mini-chip:last-child {
    margin-right: 0;
}

.kd-mini-chip {
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    font-size: 0.74rem;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    transition: all 0.18s ease;
}
.kd-mini-chip:hover {
    color: var(--hcis-slate-900);
    border-color: var(--hcis-slate-300);
    background: var(--hcis-white);
}
.kd-mini-chip.is-active {
    background: var(--hcis-indigo-600);
    color: var(--hcis-white);
    border-color: var(--hcis-indigo-600);
}
.kd-mini-chip-badge {
    background: rgba(0, 0, 0, 0.08);
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 999px;
    margin-left: 6px;
}
.kd-mini-chip.is-active .kd-mini-chip-badge {
    background: rgba(255, 255, 255, 0.25);
    color: var(--hcis-white);
}

/* ============ ITEM CARDS ============ */
.kd-card-list {
    display: flex;
    flex-direction: column;
}
.kd-card-list .kd-item-card {
    margin-bottom: 14px;
}
.kd-card-list .kd-item-card:last-child {
    margin-bottom: 0;
}

.kd-item-card {
    display: block;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    padding: 16px 20px;
    color: inherit;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.kd-item-card:hover {
    border-color: var(--hcis-indigo-200);
    box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.12), 0 8px 10px -6px rgba(99, 102, 241, 0.06);
}

.kd-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 12px;
}

.kd-card-title-col {
    min-width: 0;
    flex-grow: 1;
    padding-right: 12px;
}

.kd-card-status-col {
    flex-shrink: 0;
    margin-left: 8px;
}

.kd-item-title {
    font-size: 0.96rem;
    font-weight: 750;
    color: var(--hcis-slate-900);
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kd-card-meta-row {
    display: flex;
    align-items: center;
    margin-top: 5px;
    flex-wrap: wrap;
}

.kd-item-count-badge {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}

/* ============ PROGRESS METER ============ */
.kd-meter-block {
    margin-bottom: 12px;
}

.kd-meter-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.kd-meter-val {
    font-size: 0.84rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.kd-meter-track {
    height: 7px;
    background: var(--hcis-slate-200);
    border-radius: 999px;
    overflow: hidden;
    position: relative;
    width: 100%;
}

.kd-meter-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.4s ease;
}
.kd-meter-fill.is-emerald {
    background: linear-gradient(90deg, var(--hcis-emerald-500), var(--hcis-emerald-600));
}
.kd-meter-fill.is-indigo {
    background: linear-gradient(90deg, var(--hcis-indigo-400), var(--hcis-indigo-600));
}
.kd-meter-fill.is-amber {
    background: linear-gradient(90deg, var(--hcis-amber-400), var(--hcis-amber-500));
}
.kd-meter-fill.is-rose {
    background: linear-gradient(90deg, var(--hcis-red-400), var(--hcis-red-500));
}

/* ============ CARD FOOTER ============ */
.kd-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 10px;
    border-top: 1px solid var(--hcis-slate-50);
}

.kd-card-footer-tags {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
}

.kd-meta-item {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}

/* ============ FOOTER ACTIONS ============ */
.kd-footer-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.kd-footer-actions {
    display: flex;
    align-items: center;
}
.kd-footer-actions > * + * {
    margin-left: 10px;
}
</style>
