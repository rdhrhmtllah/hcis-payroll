<template>
    <div class="sop-stack">
        <!-- KPI Global Stat Tiles (HCIS Design System) -->
        <div class="sop-fade-up mb-3.5">
            <HcisStatGroup :stats="hcisStats" :cols="4" gap="md" appearance="crystal" />
        </div>

        <!-- Filter Bar Interaktif & Collapsible Dropdown Drawer (HCIS Design System) -->
        <div class="sop-fade-up mb-3.5">
            <HcisFilterBar
                :search="filterState.search"
                :filters="hcisFilters"
                :filter-values="filterState"
                :quick-chips="quickChips"
                :active-chip-value="filterState.status"
                :results-count="pagination.total"
                search-placeholder="Cari nama karyawan, NIK, kode..."
                variant="glass"
                sentiment="brand"
                collapsible
                allow-export
                @update:search="onSearch"
                @chip-click="onChipClick"
                @filter-change="onFilterChange"
                @reset="resetFilter"
                @export="onExport"
            />
        </div>

        <!-- Table Card Section (HCIS Design System) -->
        <div class="sop-fade-up">
            <HcisCard
                variant="card"
                sentiment="brand"
                size="md"
                class="tk-table-card"
            >
                <HcisTable
                    :columns="columns"
                    :rows="rows"
                    row-key="Kode_Karyawan"
                    :loading="loading"
                    hoverable
                    interactive
                    selectable
                    :selected-keys="terpilih"
                    variant="flat"
                    sentiment="brand"
                    empty-title="Tidak ada karyawan yang cocok"
                    empty-description="Coba sesuaikan filter atau kata kunci pencarian Anda."
                    @update:selected-keys="terpilih = $event"
                    @row-click="bukaDrawer"
                >
                    <!-- Bulk Actions in Table Header -->
                    <template #bulk-actions="{ selectedKeys: keys, clear }">
                        <div class="d-flex align-items-center gap-2">
                            <HcisButton
                                variant="secondary"
                                size="xs"
                                label="Pilih Halaman Ini"
                                @click="pilihSemuaHalaman"
                            />
                            <HcisButton
                                variant="warning"
                                size="xs"
                                leading-icon="bell-fill"
                                :label="`Kirim Pengingat (${terpilih.length})`"
                                :loading="mengingatkan"
                                @click="ingatkan"
                            />
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                leading-icon="x-lg"
                                label="Batal"
                                @click="clear"
                            />
                        </div>
                    </template>

                    <!-- Column: Nama Karyawan -->
                    <template #cell-Nama="{ row }">
                        <div class="d-flex align-items-center py-1.5 ps-1">
                            <div class="tk-avatar-box">
                                <HcisAvatar :name="row.Nama || row.Kode_Karyawan" size="md" shape="squircle" class="shadow-2xs" />
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-bold text-dark text-truncate d-flex align-items-center font-scale-sm" :title="row.Nama || row.Kode_Karyawan">
                                    <span class="me-2">{{ row.Nama || row.Kode_Karyawan }}</span>
                                    <span
                                        v-if="row.terlambat > 0"
                                        class="tk-pulse-dot danger"
                                        title="Ada penugasan melewati batas tenggat"
                                    ></span>
                                </div>
                                <div class="small text-muted mt-0.5 font-scale-xs">
                                    <span class="fw-semibold">{{ row.Kode_Karyawan }}</span><span v-if="row.No_Induk_Karyawan"> · {{ row.No_Induk_Karyawan }}</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Column: Departemen / Divisi -->
                    <template #cell-Departemen="{ row }">
                        <div class="fw-bold text-dark font-scale-sm text-truncate" :title="row.Departemen">{{ row.Departemen || '—' }}</div>
                        <div v-if="row.Divisi" class="small text-muted font-scale-xs text-truncate" :title="row.Divisi">{{ row.Divisi }}</div>
                    </template>

                    <!-- Column: Jabatan -->
                    <template #cell-Jabatan="{ row }">
                        <span class="text-secondary font-scale-sm fw-medium text-truncate d-block" :title="row.Jabatan">{{ row.Jabatan || '—' }}</span>
                    </template>

                    <!-- Column: Total Dokumen -->
                    <template #cell-total_sop="{ row }">
                        <span class="tk-sop-count">{{ row.total_sop ?? row.total ?? 0 }}</span>
                    </template>

                    <!-- Column: Selesai -->
                    <template #cell-selesai="{ row }">
                        <span class="tk-selesai-pill">
                            <HcisIcon name="check-circle-fill" size="2xs" class="me-1 text-emerald-600" />
                            {{
                                row.total_selesai ??
                                row.selesai ??
                                Math.round(((row.persen || 0) / 100) * (row.total_sop || 0))
                            }}
                        </span>
                    </template>

                    <!-- Column: Terlambat -->
                    <template #cell-terlambat="{ row }">
                        <HcisBadge
                            v-if="row.terlambat > 0"
                            :label="`${row.terlambat} Telat`"
                            variant="danger"
                            size="xs"
                            icon="exclamation-triangle-fill"
                            pulse
                        />
                        <span v-else class="tk-zero-pill">
                            <HcisIcon name="check2" size="2xs" class="me-1 text-slate-400" /> 0
                        </span>
                    </template>

                    <!-- Column: Kepatuhan (%) -->
                    <template #cell-persen="{ row }">
                        <div class="tk-persen-col">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span
                                    class="tk-pct-text fw-bold font-scale-sm"
                                    :class="row.persen >= 80 ? 'text-emerald-600' : row.persen >= 40 ? 'text-indigo-600' : 'text-amber-600'"
                                >
                                    {{ row.persen }}%
                                </span>
                                <span v-if="row.total_sop" class="small text-muted font-scale-xs">
                                    {{ row.total_selesai ?? Math.round(((row.persen || 0) / 100) * row.total_sop) }}/{{ row.total_sop }} dokumen
                                </span>
                            </div>
                            <div class="tk-meter">
                                <div
                                    class="tk-meter-bar"
                                    :class="
                                        row.persen >= 100
                                            ? 'is-emerald'
                                            : row.persen >= 80
                                              ? 'is-indigo'
                                              : row.persen >= 40
                                                ? 'is-amber'
                                                : 'is-danger'
                                    "
                                    :style="{ width: `${Math.min(row.persen || 0, 100)}%` }"
                                ></div>
                            </div>
                        </div>
                    </template>

                    <!-- Column: Aksi -->
                    <template #cell-actions="{ row }">
                        <HcisButton
                            variant="ghost"
                            size="xs"
                            icon-only
                            leading-icon="chevron-right"
                            title="Buka detail kepatuhan"
                            @click.stop="bukaDrawer(row)"
                        />
                    </template>
                </HcisTable>

                <!-- Integrated Card Footer Pagination -->
                <template v-if="pagination.total > 0" #footer>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100 py-1">
                        <span class="text-muted small">
                            Menampilkan <strong>{{ (pagination.page - 1) * pagination.per_page + 1 }}–{{ Math.min(pagination.page * pagination.per_page, pagination.total) }}</strong> dari <strong>{{ pagination.total.toLocaleString('id-ID') }}</strong> karyawan
                        </span>
                        <HcisPagination
                            :total="pagination.total"
                            :page-size="pagination.per_page"
                            :current-page="pagination.page"
                            :page-sizes="[10, 25, 50, 100]"
                            layout="sizes, prev, pager, next, jumper"
                            background
                            size="sm"
                            @page-change="fetch($event)"
                            @update:current-page="fetch($event)"
                            @size-change="onPageSizeChange"
                            @update:page-size="onPageSizeChange"
                        />
                    </div>
                </template>
            </HcisCard>
        </div>

        <!-- Floating Selection Action Dock (Bulk Reminders) -->
        <transition name="sop-fade-up">
            <div v-if="terpilih.length > 0" class="tk-dock-floating">
                <div class="tk-dock-inner">
                    <div class="tk-dock-info">
                        <span class="tk-dock-badge">{{ terpilih.length }}</span>
                        <span class="tk-dock-text">Karyawan dipilih</span>
                    </div>
                    <div class="tk-dock-actions">
                        <HcisButton
                            variant="secondary"
                            size="sm"
                            label="Pilih Halaman Ini"
                            @click="pilihSemuaHalaman"
                        />
                        <HcisButton
                            variant="warning"
                            size="sm"
                            leading-icon="bell-fill"
                            :label="`Kirim Pengingat (${terpilih.length})`"
                            :loading="mengingatkan"
                            @click="ingatkan"
                        />
                        <button
                            type="button"
                            class="tk-dock-btn-close"
                            title="Batal pilih"
                            @click="terpilih = []"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <KaryawanDrawer
            :show="drawer.show"
            :kode="drawer.kode"
            :token="drawer.token"
            :nama="drawer.nama"
            @close="drawer.show = false"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import { HcisStatGroup, HcisButton, HcisBadge, HcisAvatar, HcisFilterBar, HcisPagination, HcisCard, HcisTable, HcisIcon } from '@/components/ui';
import ProgressBar from '../../../Shared/components/ui/ProgressBar.vue';
import SopRing from '../../../Shared/components/ui/SopRing.vue';
import KaryawanDrawer from './KaryawanDrawer.vue';

const API = '/api/v1/sop/admin/monitoring';

/**
 * Tab Karyawan — padanan daftar karyawan di monitoring LMS, dengan dua
 * perbaikan yang disengaja:
 *  1. Saringan status ditegakkan di SQL, jadi `total` selalu jujur. Di LMS
 *     saringan itu dijalankan setelah paginasi sehingga halaman bisa berisi
 *     3 baris sementara total menyebut 200.
 *  2. Baris bisa dipilih untuk dikirimi pengingat — monitoring LMS murni baca.
 */
export default {
    name: 'TabKaryawan',
    components: { HcisStatGroup, HcisButton, HcisBadge, HcisAvatar, HcisFilterBar, HcisPagination, HcisCard, HcisTable, HcisIcon, ProgressBar, SopRing, KaryawanDrawer },
    props: {
        filterOptions: { type: Object, default: () => ({}) },
        awal: { type: Object, default: () => ({}) },
    },
    emits: ['export', 'filter-berubah'],
    data() {
        return {
            rows: [],
            loading: false,
            mengingatkan: false,
            terpilih: [],
            summary: {
                coverage: 0,
                total_karyawan: 0,
                total_selesai: 0,
                total_progress: 0,
                total_belum: 0,
                total_terlambat: 0,
            },
            pagination: { page: 1, per_page: 25, total_page: 1, total: 0 },
            searchTimeout: null,
            filterState: {
                search: '',
                status: 'all',
                dept: [],
                divisi: [],
                lokasi: [],
                jabatan: [],
                level: [],
                paket: [],
                prioritas: '',
                date_mode: 'assign',
                date_range: [],
            },
            drawer: { show: false, kode: '', token: '', nama: '' },
            columns: [
                { key: 'Nama', label: 'Karyawan', width: '320px' },
                { key: 'Departemen', label: 'Departemen / Divisi', width: '200px' },
                { key: 'Jabatan', label: 'Jabatan', width: '160px' },
                { key: 'total_sop', label: 'Total Dokumen', align: 'center', width: '100px' },
                { key: 'selesai', label: 'Selesai', align: 'center', width: '100px' },
                { key: 'terlambat', label: 'Telat', align: 'center', width: '100px' },
                { key: 'persen', label: 'Kepatuhan', width: '190px' },
                { key: 'actions', label: '', align: 'center', width: '50px' },
            ],
        };
    },
    computed: {
        hcisStats() {
            return [
                {
                    label: 'Total Karyawan',
                    value: Number(this.summary.total_karyawan) || 0,
                    suffix: 'Orang',
                    sentiment: 'brand',
                    icon: 'people-fill',
                    pattern: 'grid',
                    caption: 'Karyawan terdaftar dalam distribusi dokumen',
                },
                {
                    label: 'Penugasan Selesai',
                    value: Number(this.summary.total_selesai) || 0,
                    suffix: 'Penugasan',
                    sentiment: 'success',
                    icon: 'check-circle-fill',
                    pattern: 'dots',
                    caption: 'Konfirmasi tuntas dibaca & dipahami',
                },
                {
                    label: 'Belum Tuntas',
                    value: (Number(this.summary.total_progress) || 0) + (Number(this.summary.total_belum) || 0),
                    suffix: 'Penugasan',
                    sentiment: 'warning',
                    icon: 'hourglass-split',
                    pattern: 'stripes',
                    caption: 'Sedang berjalan atau belum dibuka',
                },
                {
                    label: 'Terlambat',
                    value: Number(this.summary.total_terlambat) || 0,
                    suffix: 'Penugasan',
                    sentiment: Number(this.summary.total_terlambat) > 0 ? 'danger' : 'neutral',
                    icon: 'exclamation-octagon-fill',
                    pattern: 'crosshair',
                    caption: 'Sudah melewati batas tanggal tenggat',
                },
            ];
        },
        quickChips() {
            return [
                { label: 'Semua', value: 'all', badge: Number(this.summary.total_karyawan) || 0, icon: 'people-fill' },
                { label: 'Tuntas Semua', value: 'selesai', badge: Number(this.summary.total_selesai) || 0, icon: 'check-circle-fill', color: 'success' },
                { label: 'Sedang Berjalan', value: 'progress', badge: Number(this.summary.total_progress) || 0, icon: 'hourglass-split', color: 'warning' },
                { label: 'Belum Mulai', value: 'belum', badge: Number(this.summary.total_belum) || 0, icon: 'dash-circle', color: 'neutral' },
                { label: 'Ada Terlambat', value: 'terlambat', badge: Number(this.summary.total_terlambat) || 0, icon: 'exclamation-triangle-fill', color: 'danger', ping: Number(this.summary.total_terlambat) > 0 },
            ];
        },
        hcisFilters() {
            const o = this.filterOptions || {};
            const opsi = (arr) => (arr || []).map((v) => ({ label: v, value: v }));

            return [
                {
                    key: 'dept',
                    label: 'Sub Divisi / Departemen',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    placeholder: 'Semua Sub Divisi',
                    icon: 'building',
                    col: 3,
                    options: opsi(o.departemen),
                },
                {
                    key: 'divisi',
                    label: 'Divisi',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    placeholder: 'Semua Divisi',
                    icon: 'diagram-3',
                    col: 3,
                    options: opsi(o.divisi),
                },
                {
                    key: 'paket',
                    label: 'Paket Dokumen',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    placeholder: 'Semua Paket Dokumen',
                    icon: 'collection',
                    col: 3,
                    options: opsi(o.paket),
                },
                {
                    key: 'prioritas',
                    label: 'Prioritas',
                    type: 'select',
                    clearable: true,
                    placeholder: 'Semua Prioritas',
                    icon: 'flag',
                    col: 3,
                    options: opsi(o.prioritas),
                },
                {
                    key: 'jabatan',
                    label: 'Jabatan',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    placeholder: 'Semua Jabatan',
                    icon: 'briefcase',
                    col: 3,
                    options: opsi(o.jabatan),
                },
                {
                    key: 'level',
                    label: 'Level',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    placeholder: 'Semua Level',
                    icon: 'layers',
                    col: 3,
                    options: opsi(o.level),
                },
                {
                    key: 'lokasi',
                    label: 'Lokasi Kerja',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    placeholder: 'Semua Lokasi',
                    icon: 'geo-alt',
                    col: 3,
                    options: opsi(o.lokasi),
                },
                {
                    key: 'date_range',
                    label: 'Rentang Tanggal Penugasan',
                    type: 'daterange',
                    icon: 'calendar-range',
                    col: 6,
                    placeholder: 'Pilih rentang tanggal penugasan',
                },
            ];
        },
        params() {
            const f = this.filterState || {};
            const [mulai, akhir] = Array.isArray(f.date_range) ? f.date_range : [null, null];

            return {
                q: f.search || undefined,
                status: f.status || 'all',
                dept: f.dept && f.dept.length ? f.dept : undefined,
                divisi: f.divisi && f.divisi.length ? f.divisi : undefined,
                lokasi: f.lokasi && f.lokasi.length ? f.lokasi : undefined,
                jabatan: f.jabatan && f.jabatan.length ? f.jabatan : undefined,
                level: f.level && f.level.length ? f.level : undefined,
                paket: f.paket && f.paket.length ? f.paket : undefined,
                prioritas: f.prioritas || undefined,
                date_mode: f.date_mode || 'assign',
                date_start: mulai || undefined,
                date_end: akhir || undefined,
            };
        },
    },
    mounted() {
        const a = this.awal || {};
        this.filterState = { ...this.$options.data().filterState, ...a, search: a.search || a.q || '' };
        this.fetch(1);
    },
    watch: {
        // Deep-link dari kartu antrean Ringkasan membawa saringan siap pakai.
        awal: {
            deep: true,
            handler(v) {
                const a = v || {};
                this.filterState = { ...this.$options.data().filterState, ...a, search: a.search || a.q || '' };
                this.fetch(1);
            },
        },
    },
    methods: {
        initials(n) {
            return String(n || '?')
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map((w) => w[0])
                .join('')
                .toUpperCase();
        },
        persenClass(p) {
            return p >= 80 ? 'text-success' : p >= 40 ? 'text-warning' : 'text-danger';
        },
        ckStyle(c) {
            return { '--ck': c };
        },
        tenggatClass(h) {
            return h === null ? '' : h < 0 ? 'text-danger fw-bold' : h <= 7 ? 'text-warning fw-bold' : 'text-muted';
        },
        labelHari(h) {
            if (h === null || h === undefined) return '';

            return h < 0 ? `lewat ${Math.abs(h)} hr` : h === 0 ? 'hari ini' : `${h} hr lagi`;
        },
        onChipClick(chip) {
            this.filterState.status = chip.value || 'all';
            this.terpilih = [];
            this.fetch(1);
        },
        onFilterChange(payload) {
            if (payload && payload.values) {
                this.filterState = { ...this.filterState, ...payload.values };
            } else if (payload && payload.key) {
                this.filterState[payload.key] = payload.value;
            }
            this.terpilih = [];
            this.fetch(1);
        },
        onSearch(q) {
            this.filterState.search = q || '';
            this.terpilih = [];
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.fetch(1);
            }, 300);
        },
        onExport(format) {
            this.$emit('export', { format, ...this.params });
        },
        resetFilter() {
            this.filterState = {
                search: '',
                status: 'all',
                dept: [],
                divisi: [],
                lokasi: [],
                jabatan: [],
                level: [],
                paket: [],
                prioritas: '',
                date_mode: 'assign',
                date_range: [],
            };
            this.terpilih = [];
            this.fetch(1);
        },
        onPageSizeChange(newSize) {
            this.pagination.per_page = newSize;
            this.fetch(1);
        },
        bukaDrawer(payload) {
            const item = payload?.row || payload;
            if (!item || !item.Kode_Karyawan) return;
            this.drawer = {
                show: true,
                kode: item.Kode_Karyawan,
                token: item.Token,
                nama: item.Nama || item.Kode_Karyawan,
            };
        },
        async fetch(page = 1) {
            if (page < 1) return;
            // Studio export memakai saringan yang PERSIS sedang tampil di sini.
            // Sebelumnya modal export dipasang tanpa prop filters sama sekali,
            // jadi ia selalu mengekspor seluruh basis data sambil menuliskan
            // "mengikuti saringan yang sedang aktif di layar".
            this.$emit('filter-berubah', { ...this.params });
            this.loading = true;
            try {
                const r = await axios.get(`${API}/karyawan`, {
                    params: { ...this.params, page, limit: this.pagination.per_page },
                });
                const pag = r.data.pagination || {};
                this.rows = r.data.result || [];
                this.pagination.page = pag.current_page || r.data.page || page;
                this.pagination.per_page = pag.per_page || this.pagination.per_page;
                this.pagination.total_page = pag.total_pages || r.data.total_page || 1;
                this.pagination.total = pag.total ?? r.data.total_data ?? r.data.total ?? this.rows.length;
                if (r.data.summary) this.summary = r.data.summary;
            } catch (e) {
                this.rows = [];
                ElMessage.error('Gagal memuat daftar karyawan');
            } finally {
                this.loading = false;
            }
        },
        pilihSemuaHalaman() {
            const kodes = (this.rows || []).map((r) => r.Kode_Karyawan).filter(Boolean);
            this.terpilih = Array.from(new Set([...this.terpilih, ...kodes]));
        },
        async ingatkan() {
            try {
                await ElMessageBox.confirm(
                    `Kirim pengingat ke ${this.terpilih.length} karyawan untuk seluruh dokumen yang belum tuntas?`,
                    'Kirim pengingat',
                    { confirmButtonText: 'Kirim', cancelButtonText: 'Batal', type: 'warning' },
                );
            } catch (e) {
                return;
            }

            this.mengingatkan = true;
            try {
                const r = await axios.post(`${API}/ingatkan`, { kode_karyawan: this.terpilih });
                ElMessage.success(
                    `${r.data.result.terkirim} pengingat terkirim ke ${r.data.result.karyawan} karyawan.`,
                );
                this.terpilih = [];
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
/* ============ FLOATING SELECTION DOCK ============ */
.tk-dock-floating {
    position: fixed;
    bottom: 1.5rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 100;
    width: auto;
    max-width: 90vw;
}

.tk-dock-inner {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.6rem 0.85rem 0.6rem 1.1rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.94);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    box-shadow:
        0 20px 40px -12px rgba(15, 23, 42, 0.5),
        0 0 0 1px rgba(255, 255, 255, 0.08);
    color: var(--hcis-white);
}

.tk-dock-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tk-dock-badge {
    display: inline-grid;
    place-items: center;
    min-width: 24px;
    height: 24px;
    padding: 0 6px;
    border-radius: 999px;
    background: var(--hcis-amber-500);
    color: var(--hcis-slate-900);
    font-size: 0.78rem;
    font-weight: 800;
}

.tk-dock-text {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--hcis-slate-100);
}

.tk-dock-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tk-dock-btn-close {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    border: 0;
    background: rgba(255, 255, 255, 0.12);
    color: var(--hcis-slate-300);
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.16s ease;
}

.tk-dock-btn-close:hover {
    background: rgba(239, 68, 68, 0.3);
    color: var(--hcis-white);
}

.tk-card {
    position: relative;
    border-radius: 20px;
    background: var(--hcis-white);
    border: 1px solid rgba(226, 232, 240, 0.85);
    box-shadow: 0 10px 28px -8px rgba(15, 23, 42, 0.07);
    padding: 1.4rem 1.6rem;
}

.tk-eyebrow {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: var(--hcis-slate-500);
    margin-bottom: 0.15rem;
}

.tk-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    letter-spacing: -0.01em;
}

/* Stat Cards */
.tk-stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.1rem 1.25rem;
    border-radius: 18px;
    background: var(--hcis-white);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 6px 18px -6px rgba(15, 23, 42, 0.06);
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}
.tk-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -8px rgba(15, 23, 42, 0.12);
    border-color: rgba(99, 102, 241, 0.3);
}

.tk-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.tk-stat-icon.is-primary {
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
    border: 1px solid rgba(99, 102, 241, 0.2);
}
.tk-stat-icon.is-success {
    background: rgba(16, 185, 129, 0.12);
    color: var(--hcis-emerald-600);
    border: 1px solid rgba(16, 185, 129, 0.2);
}
.tk-stat-icon.is-warning {
    background: rgba(245, 158, 11, 0.12);
    color: var(--hcis-amber-600);
    border: 1px solid rgba(245, 158, 11, 0.2);
}
.tk-stat-icon.is-danger {
    background: rgba(239, 68, 68, 0.12);
    color: var(--hcis-red-600);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.tk-stat-content {
    min-width: 0;
}
.tk-stat-val {
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1;
    color: var(--hcis-slate-900);
    font-variant-numeric: tabular-nums;
}
.tk-stat-label {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    margin-top: 0.25rem;
}

/* Action Buttons */
.tk-btn-secondary {
    display: inline-flex;
    align-items: center;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-300);
    color: var(--hcis-slate-700);
    font-size: 0.78rem;
    font-weight: 700;
    border-radius: 10px;
    padding: 0.42rem 0.9rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}
.tk-btn-secondary:hover {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-600);
    transform: translateY(-1px);
}

.tk-btn-warning {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(135deg, var(--hcis-amber-500), var(--hcis-amber-600));
    border: none;
    color: var(--hcis-white);
    font-size: 0.78rem;
    font-weight: 800;
    border-radius: 10px;
    padding: 0.42rem 0.9rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}
.tk-btn-warning:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4);
}

/* Checkbox */
.tk-pick {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    margin-right: 0.35rem;
}
.tk-pick input {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: var(--hcis-indigo-500);
    border-radius: 4px;
}

/* Avatar */
.tk-avatar {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    font-size: 0.78rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-600));
    color: var(--hcis-white);
    display: grid;
    place-items: center;
    flex-shrink: 0;
    box-shadow: 0 3px 8px rgba(79, 70, 229, 0.22);
}

.tk-pulse-dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--hcis-red-500);
    box-shadow: 0 0 6px var(--hcis-red-500);
}

.tk-sop-count {
    font-weight: 800;
    color: var(--hcis-slate-900);
    font-variant-numeric: tabular-nums;
    font-size: 0.88rem;
}

/* Late & Zero Pills */
.tk-late-pill {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.14), rgba(220, 38, 38, 0.08));
    color: var(--hcis-red-700);
    border: 1px solid rgba(239, 68, 68, 0.28);
    font-size: 0.72rem;
    font-weight: 800;
    border-radius: 8px;
    padding: 0.22rem 0.65rem;
    font-variant-numeric: tabular-nums;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.1);
}

.tk-zero-pill {
    display: inline-flex;
    align-items: center;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.72rem;
    font-weight: 700;
    border-radius: 8px;
    padding: 0.18rem 0.55rem;
    font-variant-numeric: tabular-nums;
}

.tk-selesai-pill {
    display: inline-flex;
    align-items: center;
    background: rgba(16, 185, 129, 0.12);
    color: var(--hcis-emerald-700);
    border: 1px solid rgba(16, 185, 129, 0.25);
    font-size: 0.72rem;
    font-weight: 800;
    border-radius: 8px;
    padding: 0.2rem 0.6rem;
    font-variant-numeric: tabular-nums;
}

.tk-avatar-box {
    margin-right: 14px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
}

/* Day Badges */
.tk-day-badge {
    font-size: 0.68rem;
    font-weight: 800;
    border-radius: 6px;
    padding: 0.12rem 0.5rem;
    display: inline-flex;
    align-items: center;
    line-height: 1.25;
}
.tk-day-badge.is-danger {
    background: rgba(239, 68, 68, 0.12);
    color: var(--hcis-red-600);
    border: 1px solid rgba(239, 68, 68, 0.25);
}
.tk-day-badge.is-warning {
    background: rgba(245, 158, 11, 0.14);
    color: var(--hcis-amber-700);
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.tk-day-badge.is-amber {
    background: rgba(245, 158, 11, 0.1);
    color: var(--hcis-amber-600);
    border: 1px solid rgba(245, 158, 11, 0.2);
}
.tk-day-badge.is-muted {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-200);
}

/* Custom Progress Meter & Kepatuhan Column */
.tk-persen-col {
    min-width: 150px;
    padding-right: 0.25rem;
}

.tk-pct-text {
    font-size: 0.85rem;
    font-weight: 800;
    line-height: 1;
}
.tk-pct-text.is-emerald {
    color: var(--hcis-emerald-600);
}
.tk-pct-text.is-amber {
    color: var(--hcis-amber-600);
}
.tk-pct-text.is-danger {
    color: var(--hcis-red-600);
}

.tk-pct-sub {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
}

.tk-meter {
    height: 8px;
    background: var(--hcis-slate-200);
    border-radius: 999px;
    overflow: hidden;
    position: relative;
    width: 100%;
}
.tk-meter-bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.tk-meter-bar.is-emerald {
    background: linear-gradient(90deg, var(--hcis-emerald-500), var(--hcis-emerald-600));
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.35);
}
.tk-meter-bar.is-indigo {
    background: linear-gradient(90deg, var(--hcis-indigo-400), var(--hcis-indigo-600));
    box-shadow: 0 0 8px rgba(99, 102, 241, 0.35);
}
.tk-meter-bar.is-amber {
    background: linear-gradient(90deg, var(--hcis-amber-400), var(--hcis-amber-500));
}
.tk-meter-bar.is-danger {
    background: linear-gradient(90deg, var(--hcis-red-400), var(--hcis-red-500));
    box-shadow: 0 0 8px rgba(239, 68, 68, 0.35);
}

/* Table Card Flush Layout */
.tk-table-card {
    border-radius: 16px;
    overflow: hidden;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
}
.tk-table-card :deep(.hcis-card__body) {
    padding: 0 !important;
}
.tk-table-card :deep(.hcis-table-container) {
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}
.tk-table-card :deep(.hcis-card__footer) {
    border-top: 1px solid var(--hcis-slate-100);
    padding: 0.85rem 1.25rem;
    background: var(--hcis-white);
}
</style>
