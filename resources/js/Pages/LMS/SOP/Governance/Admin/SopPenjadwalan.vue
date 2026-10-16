<template>
    <div class="container-fluid px-0 sop-scope">
        <!-- ══ 1. HEADER UTAMA PENJADWALAN DOKUMEN (HcisHeader) ══ -->
        <HcisHeader
            title="Penjadwalan & Distribusi Dokumen"
            subtitle="Distribusikan paket dokumen resmi kepada karyawan, tentukan tenggat waktu, dan pantau kepatuhan real-time."
            kicker="DISTRIBUSI & GOVERNANCE DOKUMEN"
            icon="calendar-event-fill"
            class="mb-4"
        >
            <template #actions>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton
                        label="Jadwalkan Dokumen"
                        variant="primary"
                        size="sm"
                        leading-icon="plus-lg"
                        @click="bukaWizard = true"
                    />
                </div>
            </template>
        </HcisHeader>

        <!-- ══ 2. KPI METRICS STRIP (HcisStatGroup - Compact Crystal) ══ -->
        <HcisStatGroup
            :stats="statistik"
            :cols="ringkas.diproses > 0 ? 5 : 4"
            gap="sm"
            layout="compact"
            appearance="crystal"
            class="mb-4"
        />

        <!-- ══ 3. TAB NAVIGASI DISTRIBUSI (HcisTabs Segmented) ══ -->
        <div class="mb-4">
            <HcisTabs
                v-model="tabSumber"
                :tabs="tabsSumber"
                variant="segmented"
                size="md"
                @update:model-value="onTabSumberChange"
                @change="onTabSumberChange"
            />
        </div>

        <!-- ══ 4. COCKPIT FILTER & SEARCH (HcisFilterBar) ══ -->
        <div class="mb-4">
            <HcisFilterBar
                v-model:search="filterState.search"
                v-model:active-chip="activeChip"
                :chips="quickChips"
                :filters="filtersConfig"
                :filter-values="filterValues"
                :search-placeholder="tabSumber === 'MANDIRI' ? 'Cari pendaftaran mandiri, nama paket, karyawan…' : 'Cari nama paket, karyawan, atau kode batch…'"
                @chip-click="onChipClick"
                @filter-change="onFilterChange"
                @reset="onResetFilters"
                @search="onSearch"
            />
        </div>

        <!-- ══ 5. KARTU UTAMA DAFTAR PENJADWALAN (HcisCard) ══ -->
        <HcisCard
            variant="glass"
            sentiment="purple"
            size="sm"
            :icon="tabSumber === 'MANDIRI' ? 'person-raised-hand' : 'collection-fill'"
            :title="tabSumber === 'MANDIRI' ? 'Daftar Pendaftaran Mandiri Karyawan' : 'Daftar Penjadwalan Resmi & Kebijakan'"
            :description="tabSumber === 'MANDIRI' ? 'Daftar penugasan mandiri yang diinisiasi langsung oleh karyawan dari perpustakaan dokumen publik' : 'Batch penugasan resmi dari Admin & Otomasi Kebijakan organisasi kepada karyawan'"
            :badge="`Total ${pagination.total} Batch`"
            badge-variant="brand"
            :loading="loading"
            class="mb-4"
        >
            <template #headerActions>
                <HcisButton
                    variant="ghost"
                    size="xs"
                    leading-icon="arrow-clockwise"
                    :loading="loading"
                    label="Segarkan"
                    @click="muatSemua"
                />
            </template>

            <!-- Loading Skeleton Realistis yang Menyerupai BatchCard -->
            <div v-if="loading" class="batch-list-wrap">
                <div v-for="n in Math.min(pagination.limit, 4)" :key="n" class="bc-skel-card">
                    <div class="bc-skel-head">
                        <!-- Icon Box Placeholder -->
                        <div class="bc-skel-ico"></div>

                        <!-- Main Info Placeholder -->
                        <div class="bc-skel-main">
                            <div class="bc-skel-line title" :style="{ width: n % 2 === 0 ? '42%' : '58%' }"></div>
                            <div class="bc-skel-line sub" :style="{ width: n % 2 === 0 ? '30%' : '38%' }"></div>
                            <div class="bc-skel-pills">
                                <div class="bc-skel-pill pill-sm"></div>
                                <div class="bc-skel-pill pill-md"></div>
                                <div v-if="n % 2 === 1" class="bc-skel-pill pill-sm"></div>
                            </div>
                        </div>

                        <!-- Stats Placeholder -->
                        <div class="bc-skel-stats d-none d-md-flex">
                            <div class="bc-skel-stat">
                                <div class="bc-skel-line num"></div>
                                <div class="bc-skel-line lbl"></div>
                            </div>
                            <div class="bc-skel-stat">
                                <div class="bc-skel-line num"></div>
                                <div class="bc-skel-line lbl"></div>
                            </div>
                            <div class="bc-skel-stat">
                                <div class="bc-skel-line num"></div>
                                <div class="bc-skel-line lbl"></div>
                            </div>
                        </div>

                        <!-- Progress Placeholder -->
                        <div class="bc-skel-prog d-none d-lg-flex">
                            <div class="bc-skel-bar"></div>
                            <div class="bc-skel-line num" style="width: 28px;"></div>
                        </div>

                        <!-- Chevron Placeholder -->
                        <div class="bc-skel-chev"></div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <HcisEmptyState
                v-else-if="!batches.length"
                title="Belum Ada Distribusi yang Cocok"
                description="Coba sesuaikan kata kunci pencarian atau ubah filter status tenggat di atas."
                icon="calendar-x"
                variant="dashed"
                sentiment="neutral"
                class="my-4"
            >
                <template #actions>
                    <div class="d-flex gap-2">
                        <HcisButton
                            v-if="hasActiveFilter"
                            variant="secondary"
                            size="sm"
                            leading-icon="arrow-counterclockwise"
                            label="Reset Filter"
                            @click="onResetFilters"
                        />
                        <HcisButton
                            variant="primary"
                            size="sm"
                            leading-icon="plus-lg"
                            label="Buat Jadwal Baru"
                            @click="bukaWizard = true"
                        />
                    </div>
                </template>
            </HcisEmptyState>

            <!-- Daftar Batch Cards -->
            <div v-else class="batch-list-wrap">
                <BatchCard
                    v-for="b in batches"
                    :key="b.Id_SOP_Penjadwalan"
                    :batch="b"
                    :opsi-filter="opsiFilter"
                    :terbuka="terbuka === b.Id_SOP_Penjadwalan"
                    @toggle="toggleKartu(b)"
                    @edit-tenggat="mulaiEditTenggat(b)"
                    @tambah-orang="mulaiTambahOrang(b)"
                    @berubah="muatSemua"
                />
            </div>

            <!-- Pagination Footer (HcisPagination) -->
            <template v-if="pagination.total > 0" #footer>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 py-1">
                    <span class="small text-muted">
                        Menampilkan <strong>{{ (pagination.page - 1) * pagination.limit + 1 }}</strong> -
                        <strong>{{ Math.min(pagination.page * pagination.limit, pagination.total) }}</strong> dari
                        <strong>{{ pagination.total }}</strong> batch
                    </span>
                    <HcisPagination
                        :model-value="pagination.page"
                        :page-size="pagination.limit"
                        :total="pagination.total"
                        :page-sizes="[10, 20, 50, 100]"
                        :disabled="loading"
                        size="sm"
                        @update:model-value="muat"
                        @update:page-size="onPageSizeChange"
                        @size-change="onPageSizeChange"
                    />
                </div>
            </template>
        </HcisCard>

        <!-- Dialog Edit Tenggat Batch -->
        <EditTenggatDialog v-model="bukaTenggat" :batch="batchAktif" @tersimpan="muatSemua" />

        <!-- Wizard Tambah Distribusi Baru -->
        <WizardPenjadwalan
            v-model="bukaWizard"
            :paket-options="paketOptions"
            :opsi-filter="opsiFilter"
            :paket-terpotong="paketTerpotong"
            :paket-awal="paketAwal"
            @selesai="setelahJadwal"
        />

        <!-- Dialog Tambah Karyawan ke Batch -->
        <TambahKaryawanDialog
            v-model="bukaTambah"
            :batch="batchAktif"
            :opsi-filter="opsiFilter"
            @tersimpan="muatSemua"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import {
    HcisButton,
    HcisCard,
    HcisEmptyState,
    HcisFilterBar,
    HcisHeader,
    HcisPagination,
    HcisSkeleton,
    HcisStatGroup,
    HcisTabs,
} from '@/components/ui';
import BatchCard from './penjadwalan/BatchCard.vue';
import WizardPenjadwalan from './penjadwalan/WizardPenjadwalan.vue';
import EditTenggatDialog from './penjadwalan/EditTenggatDialog.vue';
import TambahKaryawanDialog from './penjadwalan/TambahKaryawanDialog.vue';
import pageMeta from '../../Shared/support/pageMeta.js';
import '../../Shared/support/sopTheme.css';

const API = '/api/v1/sop/admin/penjadwalan';

export default {
    name: 'SopPenjadwalan',
    components: {
        HcisButton,
        HcisCard,
        HcisEmptyState,
        HcisFilterBar,
        HcisHeader,
        HcisPagination,
        HcisSkeleton,
        HcisStatGroup,
        HcisTabs,
        BatchCard,
        WizardPenjadwalan,
        EditTenggatDialog,
        TambahKaryawanDialog,
    },
    mixins: [pageMeta],
    data() {
        return {
            tabSumber: 'DITUGASKAN',
            batches: [],
            ringkas: { batch: 0, target: 0, selesai: 0, persen: 0, terlambat: 0, diproses: 0, tab_ditugaskan: 0, tab_mandiri: 0 },
            pagination: { page: 1, limit: 10, total: 0 },
            loading: false,
            terbuka: null,
            paketOptions: [],
            /** Server memotong daftar paket karena melewati batas aman. */
            paketTerpotong: false,
            opsiFilter: {},
            activeChip: 'ALL',
            filterState: {
                search: '',
                paket: [],
                subSumber: '',
                tenggat: 'SEMUA',
                date_range: null,
            },
            bukaWizard: false,
            bukaTenggat: false,
            bukaTambah: false,
            batchAktif: null,
            paketAwal: '',
            pollTimer: null,
        };
    },
    computed: {
        tabsSumber() {
            return [
                {
                    key: 'DITUGASKAN',
                    value: 'DITUGASKAN',
                    label: 'Penjadwalan Resmi & Kebijakan',
                    icon: 'calendar2-check-fill',
                    badge: this.ringkas.tab_ditugaskan > 0 ? this.ringkas.tab_ditugaskan : null,
                },
                {
                    key: 'MANDIRI',
                    value: 'MANDIRI',
                    label: 'Pendaftaran Mandiri Karyawan',
                    icon: 'person-raised-hand',
                    badge: this.ringkas.tab_mandiri > 0 ? this.ringkas.tab_mandiri : null,
                },
            ];
        },
        totalHalaman() {
            return Math.max(1, Math.ceil(this.pagination.total / this.pagination.limit));
        },
        hasActiveFilter() {
            const f = this.filterState;
            return !!(f.search || (f.paket && f.paket.length) || f.subSumber || f.tenggat !== 'SEMUA' || f.date_range);
        },
        statistik() {
            const stats = [
                {
                    label: this.tabSumber === 'MANDIRI' ? 'Pendaftaran Mandiri' : 'Batch Distribusi',
                    value: this.ringkas.batch || 0,
                    suffix: 'batch',
                    caption: this.tabSumber === 'MANDIRI' ? 'Total cohort mandiri' : 'Total batch penugasan',
                    icon: this.tabSumber === 'MANDIRI' ? 'person-raised-hand' : 'collection-fill',
                    sentiment: 'purple',
                },
                {
                    label: 'Karyawan Tertarget',
                    value: this.ringkas.target || 0,
                    suffix: 'orang',
                    caption: 'Sasaran alokasi karyawan',
                    icon: 'people-fill',
                    sentiment: 'info',
                },
                {
                    label: 'Tuntas Dipelajari',
                    value: `${this.ringkas.persen || 0}%`,
                    suffix: '',
                    caption: `${this.ringkas.selesai || 0}/${this.ringkas.target || 0} orang selesai`,
                    progress: this.ringkas.persen || 0,
                    icon: 'check2-circle',
                    sentiment: 'success',
                },
                {
                    label: 'Terlambat Pelajari',
                    value: this.ringkas.terlambat || 0,
                    suffix: 'orang',
                    caption: 'Melewati batas deadline',
                    icon: 'alarm-fill',
                    sentiment: 'danger',
                },
            ];

            if (this.ringkas.diproses > 0) {
                stats.push({
                    label: 'Sedang Diproses',
                    value: this.ringkas.diproses,
                    suffix: 'batch',
                    caption: 'Latar belakang sistem',
                    icon: 'arrow-repeat',
                    sentiment: 'warning',
                });
            }

            return stats;
        },
        quickChips() {
            if (this.tabSumber === 'MANDIRI') {
                return [
                    {
                        value: 'ALL',
                        label: 'Semua Pendaftaran Mandiri',
                        icon: 'grid-1x2-fill',
                        color: 'brand',
                        badge: this.pagination.total,
                    },
                    {
                        value: 'DEADLINE',
                        label: 'Berdeadline',
                        icon: 'calendar-event',
                        color: 'primary',
                    },
                    {
                        value: 'SAMPAI_DICABUT',
                        label: 'Tanpa Tenggat',
                        icon: 'infinity',
                        color: 'purple',
                    },
                    {
                        value: 'TERLAMBAT',
                        label: 'Terlambat',
                        icon: 'alarm-fill',
                        color: 'danger',
                        badge: this.ringkas.terlambat > 0 ? this.ringkas.terlambat : null,
                    },
                ];
            }
            return [
                {
                    value: 'ALL',
                    label: 'Semua Penjadwalan Resmi',
                    icon: 'grid-1x2-fill',
                    color: 'brand',
                    badge: this.pagination.total,
                },
                {
                    value: 'DEADLINE',
                    label: 'Berdeadline',
                    icon: 'calendar-event',
                    color: 'primary',
                },
                {
                    value: 'SAMPAI_DICABUT',
                    label: 'Tanpa Tenggat',
                    icon: 'infinity',
                    color: 'purple',
                },
                {
                    value: 'TERLAMBAT',
                    label: 'Terlambat',
                    icon: 'alarm-fill',
                    color: 'danger',
                    badge: this.ringkas.terlambat > 0 ? this.ringkas.terlambat : null,
                },
                {
                    value: 'AUTO_RULE',
                    label: 'Otomasi Kebijakan',
                    icon: 'lightning-charge-fill',
                    color: 'brand',
                },
                {
                    value: 'MANUAL',
                    label: 'Ditugaskan Admin',
                    icon: 'person-badge-fill',
                    color: 'neutral',
                },
            ];
        },
        filtersConfig() {
            const configs = [
                {
                    key: 'paket',
                    label: 'Paket Dokumen',
                    type: 'select',
                    multiple: true,
                    placeholder: 'Semua Paket',
                    options: this.paketOptions.map((p) => ({
                        label: (p.Kode_Paket ? `[${p.Kode_Paket}] ` : '') + p.Nama_Paket,
                        value: p.Id_SOP_Paket,
                    })),
                },
            ];

            if (this.tabSumber === 'DITUGASKAN') {
                configs.push({
                    key: 'subSumber',
                    label: 'Tipe Penugasan',
                    type: 'select',
                    placeholder: 'Semua (Admin & Kebijakan)',
                    options: [
                        { label: 'Semua (Admin & Kebijakan)', value: '' },
                        { label: 'Ditugaskan Admin (Manual)', value: 'MANUAL' },
                        { label: 'Otomasi Kebijakan (Auto Rule)', value: 'AUTO_RULE' },
                    ],
                });
            }

            configs.push(
                {
                    key: 'tenggat',
                    label: 'Status Tenggat',
                    type: 'select',
                    options: [
                        { label: 'Semua Status', value: 'SEMUA' },
                        { label: 'Berdeadline', value: 'DEADLINE' },
                        { label: 'Tanpa Tenggat', value: 'SAMPAI_DICABUT' },
                        { label: 'Terlambat', value: 'TERLAMBAT' },
                    ],
                },
                {
                    key: 'date_range',
                    label: 'Rentang Tanggal Penugasan',
                    type: 'daterange',
                }
            );

            return configs;
        },
        filterValues() {
            return {
                paket: this.filterState.paket,
                subSumber: this.filterState.subSumber,
                tenggat: this.filterState.tenggat,
                date_range: this.filterState.date_range,
            };
        },
        params() {
            const f = this.filterState;
            const [dari, sampai] = Array.isArray(f.date_range) ? f.date_range : [null, null];
            const sumberParam = this.tabSumber === 'MANDIRI'
                ? 'MANDIRI'
                : (f.subSumber || 'DITUGASKAN');

            return {
                q: f.search || undefined,
                paket: f.paket?.length ? f.paket : undefined,
                sumber: sumberParam,
                tenggat: f.tenggat || 'SEMUA',
                dari: dari || undefined,
                sampai: sampai || undefined,
            };
        },
    },
    watch: {
        tabSumber(val) {
            this.filterState.subSumber = '';
            this.activeChip = 'ALL';
            this.pagination.page = 1;
            this.tulisUrl();
            this.muat(1);
            this.muatRingkas();
        },
    },
    mounted() {
        this.muatOpsi();
        this.bacaUrl();
        this.muatSemua();
    },
    beforeUnmount() {
        clearInterval(this.pollTimer);
    },
    methods: {
        /** Deep-link: ?batch={hash}, ?tab={DITUGASKAN|MANDIRI}, ?wizard=1&paket= */
        bacaUrl() {
            const u = new URLSearchParams(window.location.search);
            const b = u.get('batch');
            if (b) this.terbuka = b;
            const t = u.get('tab') || u.get('sumber');
            if (t && ['DITUGASKAN', 'MANDIRI'].includes(t.toUpperCase())) {
                this.tabSumber = t.toUpperCase();
            }
            if (u.get('wizard') === '1') {
                this.paketAwal = u.get('paket') || '';
                this.bukaWizard = true;
            }
        },
        tulisUrl() {
            const u = new URLSearchParams(window.location.search);
            if (this.tabSumber) u.set('tab', this.tabSumber);
            const baru = `${window.location.pathname}?${u.toString()}`;
            window.history.replaceState({}, '', baru);
        },
        onTabSumberChange(val) {
            const key = typeof val === 'object' && val !== null ? val.key : val;
            if (key && this.tabSumber !== key) {
                this.tabSumber = key;
            }
        },
        async muatOpsi() {
            try {
                const [p, f] = await Promise.all([
                    axios.get(`${API}/options/paket`),
                    axios.get(`${API}/options/filter`),
                ]);
                if (p.data.success) {
                    this.paketOptions = p.data.result || [];
                    this.paketTerpotong = !!p.data.terpotong;
                }
                if (f.data.success) this.opsiFilter = f.data.result || {};
            } catch (e) {
                /* diam — halaman tetap berguna tanpa opsi saringan */
            }
        },
        muatSemua() {
            this.muat(this.pagination.page);
            this.muatRingkas();
        },
        onPageSizeChange(newSize) {
            this.pagination.limit = Number(newSize);
            this.muat(1);
        },
        async muat(page = 1) {
            this.loading = true;
            this.pagination.page = page;
            try {
                const r = await axios.get(`${API}/list`, {
                    params: { ...this.params, page, limit: this.pagination.limit },
                });
                if (r.data.success) {
                    this.batches = r.data.result || [];
                    this.pagination.total = r.data.pagination?.total ?? this.batches.length;
                    this.aturPolling();
                }
            } catch (e) {
                ElMessage.error('Gagal memuat daftar penjadwalan');
                this.batches = [];
            } finally {
                this.loading = false;
            }
        },
        async muatRingkas() {
            try {
                const r = await axios.get(`${API}/ringkas`, { params: this.params });
                if (r.data.success) this.ringkas = r.data.result;
            } catch (e) {
                /* diam */
            }
        },
        /**
         * Selama ada batch yang sedang diproses job, segarkan berkala supaya pill
         * "Memproses" dan angkanya bergerak. Berhenti sendiri saat tak ada lagi —
         * bukan polling abadi.
         */
        aturPolling() {
            clearInterval(this.pollTimer);
            if (!this.batches.some((b) => b.Status_Proses)) return;
            this.pollTimer = setInterval(() => {
                if (document.visibilityState === 'visible') this.muatSemua();
            }, 5000);
        },
        onChipClick(chip) {
            const val = typeof chip === 'object' ? chip.value : chip;
            this.activeChip = val;
            if (val === 'ALL') {
                this.filterState.tenggat = 'SEMUA';
                this.filterState.subSumber = '';
            } else if (val === 'DEADLINE') {
                this.filterState.tenggat = 'DEADLINE';
                this.filterState.subSumber = '';
            } else if (val === 'SAMPAI_DICABUT') {
                this.filterState.tenggat = 'SAMPAI_DICABUT';
                this.filterState.subSumber = '';
            } else if (val === 'TERLAMBAT') {
                this.filterState.tenggat = 'TERLAMBAT';
                this.filterState.subSumber = '';
            } else if (val === 'AUTO_RULE') {
                this.filterState.subSumber = 'AUTO_RULE';
                this.filterState.tenggat = 'SEMUA';
            } else if (val === 'MANUAL') {
                this.filterState.subSumber = 'MANUAL';
                this.filterState.tenggat = 'SEMUA';
            }
            this.muat(1);
            this.muatRingkas();
        },
        onFilterChange(payload) {
            const values = payload?.values ?? payload;
            this.filterState = { ...this.filterState, ...values };
            if (this.filterState.subSumber === 'AUTO_RULE') {
                this.activeChip = 'AUTO_RULE';
            } else if (this.filterState.subSumber === 'MANUAL') {
                this.activeChip = 'MANUAL';
            } else if (this.filterState.tenggat === 'DEADLINE') {
                this.activeChip = 'DEADLINE';
            } else if (this.filterState.tenggat === 'SAMPAI_DICABUT') {
                this.activeChip = 'SAMPAI_DICABUT';
            } else if (this.filterState.tenggat === 'TERLAMBAT') {
                this.activeChip = 'TERLAMBAT';
            } else {
                this.activeChip = 'ALL';
            }
            this.muat(1);
            this.muatRingkas();
        },
        onSearch(q) {
            this.filterState.search = q;
            this.muat(1);
            this.muatRingkas();
        },
        onResetFilters() {
            this.filterState = {
                search: '',
                paket: [],
                subSumber: '',
                tenggat: 'SEMUA',
                date_range: null,
            };
            this.activeChip = 'ALL';
            this.muat(1);
            this.muatRingkas();
        },
        toggleKartu(b) {
            this.terbuka = this.terbuka === b.Id_SOP_Penjadwalan ? null : b.Id_SOP_Penjadwalan;
        },
        mulaiEditTenggat(b) {
            this.batchAktif = b;
            this.bukaTenggat = true;
        },
        mulaiTambahOrang(b) {
            this.batchAktif = b;
            this.bukaTambah = true;
        },
        setelahJadwal(res) {
            this.paketAwal = '';
            if (res?.penjadwalan_id) this.terbuka = res.penjadwalan_id;
            this.muat(1);
            this.muatRingkas();
        },
    },
};
</script>

<style scoped>
.batch-list-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    margin: 0.5rem 0;
}

/* Realistic BatchCard Skeleton */
.bc-skel-card {
    border-radius: 12px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.05);
    overflow: hidden;
}

.bc-skel-head {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr) auto 150px 20px;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1.15rem;
}

@media (max-width: 991px) {
    .bc-skel-head {
        grid-template-columns: 42px minmax(0, 1fr) auto 20px;
    }
}
@media (max-width: 767px) {
    .bc-skel-head {
        grid-template-columns: 42px minmax(0, 1fr) 20px;
    }
}

.bc-skel-ico {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    background: linear-gradient(90deg, var(--hcis-slate-100) 25%, var(--hcis-slate-200) 50%, var(--hcis-slate-100) 75%);
    background-size: 200% 100%;
    animation: hcis-skel-shimmer 1.5s infinite;
}

.bc-skel-main {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
}

.bc-skel-line {
    border-radius: 6px;
    background: linear-gradient(90deg, var(--hcis-slate-100) 25%, var(--hcis-slate-200) 50%, var(--hcis-slate-100) 75%);
    background-size: 200% 100%;
    animation: hcis-skel-shimmer 1.5s infinite;
}

.bc-skel-line.title {
    height: 15px;
}

.bc-skel-line.sub {
    height: 11px;
}

.bc-skel-pills {
    display: flex;
    gap: 0.35rem;
    margin-top: 2px;
}

.bc-skel-pill {
    height: 18px;
    border-radius: 9999px;
    background: linear-gradient(90deg, var(--hcis-slate-100) 25%, var(--hcis-slate-200) 50%, var(--hcis-slate-100) 75%);
    background-size: 200% 100%;
    animation: hcis-skel-shimmer 1.5s infinite;
}
.bc-skel-pill.pill-sm { width: 55px; }
.bc-skel-pill.pill-md { width: 80px; }

.bc-skel-stats {
    display: flex;
    gap: 0.9rem;
    align-items: center;
}

.bc-skel-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.bc-skel-stat .num {
    width: 24px;
    height: 16px;
}

.bc-skel-stat .lbl {
    width: 32px;
    height: 9px;
}

.bc-skel-prog {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    width: 150px;
}

.bc-skel-bar {
    flex: 1;
    height: 8px;
    border-radius: 9999px;
    background: linear-gradient(90deg, var(--hcis-slate-100) 25%, var(--hcis-slate-200) 50%, var(--hcis-slate-100) 75%);
    background-size: 200% 100%;
    animation: hcis-skel-shimmer 1.5s infinite;
}

.bc-skel-chev {
    width: 14px;
    height: 14px;
    border-radius: 4px;
    background: linear-gradient(90deg, var(--hcis-slate-100) 25%, var(--hcis-slate-200) 50%, var(--hcis-slate-100) 75%);
    background-size: 200% 100%;
    animation: hcis-skel-shimmer 1.5s infinite;
}

@keyframes hcis-skel-shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>
