<template>
    <div class="bkt">
        <!-- Official HcisFilterBar Cockpit Component -->
        <div class="mb-3">
            <HcisFilterBar
                :search="q"
                :filters="filtersConfig"
                :filter-values="saringan"
                :quick-chips="chipsConfig"
                :active-chip-value="status"
                :results-count="total"
                search-placeholder="Cari nama karyawan atau kode..."
                variant="glass"
                sentiment="purple"
                size="sm"
                collapsible
                @update:search="onSearch"
                @chip-click="onChipClick"
                @filter-change="onFilterChange"
                @reset="resetSaringan"
            />
        </div>

        <!-- Empty State Khusus saat pencarian/filter kosong -->
        <HcisEmptyState
            v-if="!loading && !rows.length"
            title="Tidak Ada Karyawan yang Cocok"
            description="Tidak ada data karyawan yang sesuai dengan saringan atau kata kunci pencarian."
            icon="people"
            variant="dashed"
            sentiment="neutral"
            class="my-3 bkt-empty"
        >
            <template #actions>
                <HcisButton
                    v-if="jumlahSaringan || q || status !== 'SEMUA'"
                    variant="secondary"
                    size="xs"
                    leading-icon="arrow-counterclockwise"
                    label="Reset Semua Saringan"
                    @click="resetSaringan"
                />
            </template>
        </HcisEmptyState>

        <!-- Komponen Tabel Resmi HcisTable dengan Shadow & Backend Pagination Terintegrasi -->
        <div v-else class="bkt-table-container">
            <HcisTable
                :columns="kolomTabel"
                :rows="rows"
                :loading="loading"
                row-key="Kode_Karyawan"
                selectable
                v-model:selected-keys="terpilih"
                variant="flat"
                size="sm"
                hoverable
            >
                <!-- Bulk Actions Slot -->
                <template #bulk-actions>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small fw-semibold text-primary">
                            <strong>{{ terpilih.length }}</strong> dipilih
                        </span>
                        <HcisButton
                            v-if="total > rows.length"
                            variant="ghost"
                            size="xs"
                            :loading="memuatSemua"
                            :label="`Pilih Semua ${total} Hasil`"
                            @click="pilihSemua"
                        />
                        <HcisButton
                            variant="ghost"
                            size="xs"
                            label="Batal"
                            @click="terpilih = []"
                        />
                        <HcisButton
                            variant="danger"
                            size="xs"
                            leading-icon="person-dash"
                            label="Hapus Akses Batch"
                            :loading="mencabut"
                            @click="cabutMassal"
                        />
                    </div>
                </template>

                <!-- Cell: Karyawan -->
                <template #cell-karyawan="{ row }">
                    <div class="bkt-karyawan-cell">
                        <HcisAvatar :name="row.Nama" size="sm" sentiment="purple" class="flex-shrink-0" />
                        <div class="min-w-0">
                            <div class="fw-bold text-dark fs-7 text-truncate" :title="row.Nama">{{ row.Nama }}</div>
                            <div class="d-flex align-items-center gap-1.5 mt-0.5">
                                <span class="badge bg-light text-secondary border font-monospace px-1.5 py-0.5" style="font-size: 0.68rem;">
                                    {{ row.Kode_Karyawan }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Cell: Unit & Jabatan -->
                <template #cell-unit="{ row }">
                    <div class="py-1 min-w-0">
                        <div class="fw-semibold text-dark fs-7 text-truncate" :title="row.Departemen || '—'">
                            {{ row.Departemen || '—' }}
                        </div>
                        <div class="small text-muted text-truncate" :title="row.Jabatan || '—'">
                            {{ row.Jabatan || '—' }}
                        </div>
                        <div v-if="row.Divisi || row.Lokasi" class="d-flex align-items-center gap-1.5 mt-1 flex-wrap">
                            <span v-if="row.Divisi" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-1.5 py-0.5" style="font-size: 0.65rem;">
                                {{ row.Divisi }}
                            </span>
                            <span v-if="row.Lokasi" class="small text-muted d-inline-flex align-items-center gap-0.5 text-truncate" style="font-size: 0.7rem;" :title="row.Lokasi">
                                <i class="bi bi-geo-alt-fill text-danger" style="font-size: 0.65rem;"></i>{{ row.Lokasi }}
                            </span>
                        </div>
                    </div>
                </template>

                <!-- Cell: Progres Item -->
                <template #cell-progres="{ row }">
                    <div class="py-1" style="min-width: 130px; max-width: 155px;">
                        <HcisProgress
                            :value="row.Persen || 0"
                            :label="`${row.Item_Selesai || 0}/${row.Item_Total || 0} item`"
                            :sentiment="progressSentiment(row)"
                            size="xs"
                            show-value
                            :glow="row.Persen >= 100"
                        />
                    </div>
                </template>

                <!-- Cell: Kuis Evaluasi -->
                <template #cell-kuis="{ row }">
                    <div class="d-flex flex-column align-items-center justify-content-center py-1">
                        <template v-if="row.Skor_Quiz !== null">
                            <span class="fw-bold font-mono tnum" :class="row.Status_Lulus === 'LULUS' ? 'text-success' : 'text-danger'">
                                {{ row.Skor_Quiz }} pts
                            </span>
                            <span class="badge px-1.5 py-0.5 mt-0.5" :class="row.Status_Lulus === 'LULUS' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'" style="font-size: 0.65rem;">
                                {{ row.Status_Lulus === 'LULUS' ? 'Lulus' : 'Remedial' }}
                            </span>
                        </template>
                        <span v-else class="text-muted small">—</span>
                    </div>
                </template>

                <!-- Cell: Waktu & Kepatuhan -->
                <template #cell-waktu="{ row }">
                    <div class="py-1 min-w-0" style="font-size: 0.72rem;">
                        <div v-if="row.Tanggal_Selesai_Format" class="text-success fw-semibold text-truncate" title="Waktu Penyelesaian">
                            <i class="bi bi-check-circle-fill me-1"></i>{{ row.Tanggal_Selesai_Format }}
                        </div>
                        <div v-else-if="row.Terakhir_Buka_Format" class="text-primary fw-medium text-truncate" title="Terakhir Dibuka">
                            <i class="bi bi-clock-history me-1"></i>Buka: {{ row.Terakhir_Buka_Format }}
                        </div>
                        <div v-else class="text-muted text-truncate" title="Tanggal Penugasan">
                            <i class="bi bi-calendar-plus me-1"></i>Assign: {{ row.Tanggal_Assign_Format || '—' }}
                        </div>
                        <div v-if="row.Jumlah_Reminder > 0" class="text-warning-emphasis small mt-0.5">
                            <i class="bi bi-bell-fill me-0.5"></i>{{ row.Jumlah_Reminder }}x diingatkan
                        </div>
                    </div>
                </template>

                <!-- Cell: Status -->
                <template #cell-status="{ row }">
                    <div class="d-flex justify-content-center py-1">
                        <HcisBadge :variant="badgeVariant(row)" :icon="badgeIcon(row)" size="xs">
                            {{ label(row) }}
                        </HcisBadge>
                    </div>
                </template>

                <!-- Cell: Aksi (Direct Link) -->
                <template #cell-aksi="{ row }">
                    <div class="d-flex justify-content-center py-1">
                        <button
                            type="button"
                            class="bkt-action-btn"
                            :class="{ 'is-loading': navigasiToken === row.Token }"
                            :disabled="navigasiToken === row.Token"
                            title="Lihat Detail Kepatuhan Karyawan pada dokumen ini"
                            @click="bukaMonitoring(row)"
                        >
                            <span v-if="navigasiToken === row.Token" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            <i v-else class="bi bi-box-arrow-up-right"></i>
                        </button>
                    </div>
                </template>

                <!-- Footer Slot: Backend Pagination HcisPagination -->
                <template v-if="total > 0" #footer>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3 w-100 bg-light border-top">
                        <span class="small text-muted">
                            Menampilkan <strong>{{ (page - 1) * limit + 1 }}</strong> -
                            <strong>{{ Math.min(page * limit, total) }}</strong> dari
                            <strong>{{ total }}</strong> karyawan
                        </span>
                        <HcisPagination
                            :model-value="page"
                            :page-size="limit"
                            :total="total"
                            :page-sizes="[10, 25, 50, 100]"
                            size="sm"
                            @update:model-value="muat"
                            @update:page-size="onLimitChange"
                            @size-change="onLimitChange"
                        />
                    </div>
                </template>
            </HcisTable>
        </div>

        <SopCabutAksesDialog
            :show="cabutBuka"
            :sibuk="mencabut"
            :subjudul="`${terpilih.length} karyawan pada batch ini`"
            @close="cabutBuka = false"
            @konfirmasi="kirimCabut"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import {
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisEmptyState,
    HcisFilterBar,
    HcisPagination,
    HcisProgress,
    HcisTable,
} from '@/components/ui';
import SopCabutAksesDialog from '../../../Shared/components/ui/SopCabutAksesDialog.vue';

const API = '/api/v1/sop/admin/penjadwalan';

export default {
    name: 'BatchKaryawanTable',
    components: {
        HcisAvatar,
        HcisBadge,
        HcisButton,
        HcisEmptyState,
        HcisFilterBar,
        HcisPagination,
        HcisProgress,
        HcisTable,
        SopCabutAksesDialog,
    },
    props: {
        idBatch: { type: String, required: true },
        idPaket: { type: String, default: '' },
        opsiFilter: { type: Object, default: () => ({}) },
    },
    emits: ['berubah'],
    data() {
        return {
            rows: [],
            total: 0,
            page: 1,
            limit: 25,
            loading: false,
            mencabut: false,
            cabutBuka: false,
            memuatSemua: false,
            q: '',
            cariTimer: null,
            status: 'SEMUA',
            terpilih: [],
            navigasiToken: null,
            saringan: { divisi: [], dept: [], level: [], jabatan: [], lokasi: [] },
        };
    },
    computed: {
        chipsConfig() {
            return [
                { value: 'SEMUA', label: 'Semua Status', color: 'brand' },
                { value: 'BELUM', label: 'Belum Mulai', color: 'neutral' },
                { value: 'PROGRESS', label: 'Berjalan', color: 'primary' },
                { value: 'SELESAI', label: 'Tuntas', color: 'success' },
                { value: 'DICABUT', label: 'Dihapus', color: 'danger' },
            ];
        },
        filtersConfig() {
            return [
                {
                    key: 'divisi',
                    label: 'Divisi',
                    type: 'select',
                    multiple: true,
                    placeholder: 'Semua Divisi',
                    options: (this.opsiFilter.divisi || []).map((v) => ({ label: v, value: v })),
                },
                {
                    key: 'dept',
                    label: 'Sub Divisi',
                    type: 'select',
                    multiple: true,
                    placeholder: 'Semua Sub Divisi',
                    options: (this.opsiFilter.departemen || []).map((v) => ({ label: v, value: v })),
                },
                {
                    key: 'jabatan',
                    label: 'Jabatan',
                    type: 'select',
                    multiple: true,
                    placeholder: 'Semua Jabatan',
                    options: (this.opsiFilter.jabatan || []).map((v) => ({ label: v, value: v })),
                },
                {
                    key: 'level',
                    label: 'Level',
                    type: 'select',
                    multiple: true,
                    placeholder: 'Semua Level',
                    options: (this.opsiFilter.level || []).map((v) => ({ label: v, value: v })),
                },
                {
                    key: 'lokasi',
                    label: 'Lokasi Kerja',
                    type: 'select',
                    multiple: true,
                    placeholder: 'Semua Lokasi',
                    options: (this.opsiFilter.lokasi || []).map((v) => ({ label: v, value: v })),
                },
            ];
        },
        kolomTabel() {
            return [
                { key: 'karyawan', label: 'Karyawan', minWidth: '220px' },
                { key: 'unit', label: 'Unit & Jabatan', minWidth: '170px' },
                { key: 'progres', label: 'Progres', width: '150px' },
                { key: 'kuis', label: 'Kuis Evaluasi', width: '120px', align: 'center' },
                { key: 'waktu', label: 'Waktu Kepatuhan', width: '160px' },
                { key: 'status', label: 'Status', width: '115px', align: 'center' },
                { key: 'aksi', label: 'Aksi', width: '50px', align: 'center' },
            ];
        },
        totalHalaman() {
            return Math.max(1, Math.ceil(this.total / this.limit));
        },
        jumlahSaringan() {
            return Object.values(this.saringan).reduce((n, v) => n + (v?.length || 0), 0);
        },
    },
    mounted() {
        this.muat(1);
    },
    methods: {
        tautanMonitoring(row) {
            if (this.idPaket) {
                return `/sop/admin/monitoring/karyawan/${row.Token}/paket/${this.idPaket}`;
            }
            return `/sop/admin/monitoring/karyawan/${row.Token}`;
        },
        bukaMonitoring(row) {
            this.navigasiToken = row.Token;
            const url = this.tautanMonitoring(row);
            if (this.$inertia) {
                this.$inertia.visit(url, {
                    onFinish: () => {
                        this.navigasiToken = null;
                    },
                });
            } else {
                window.location.href = url;
            }
        },
        progressSentiment(r) {
            if (r.Persen >= 100) return 'success';
            if (r.Persen > 0) return 'brand';
            return 'neutral';
        },
        badgeVariant(r) {
            if (r.Flag_Cabut_Akses === 'Y') return 'danger';
            return { SELESAI: 'success', PROGRESS: 'brand', BELUM: 'neutral' }[r.Status_Paket] || 'neutral';
        },
        badgeIcon(r) {
            if (r.Flag_Cabut_Akses === 'Y') return 'person-slash';
            return { SELESAI: 'check-circle-fill', PROGRESS: 'arrow-clockwise', BELUM: 'dash-circle' }[r.Status_Paket] || 'dash-circle';
        },
        label(r) {
            if (r.Flag_Cabut_Akses === 'Y') return 'Dihapus';
            return { SELESAI: 'Tuntas', PROGRESS: 'Berjalan', BELUM: 'Belum Mulai' }[r.Status_Paket] || r.Status_Paket;
        },
        params() {
            const s = {};
            const keys = ['divisi', 'dept', 'level', 'jabatan', 'lokasi'];
            for (const k of keys) {
                if (this.saringan[k] && Array.isArray(this.saringan[k]) && this.saringan[k].length) {
                    s[k] = this.saringan[k];
                }
            }

            return { q: this.q || undefined, status: this.status, ...s };
        },
        onChipClick(chip) {
            const val = typeof chip === 'object' && chip !== null ? (chip.value ?? chip.id) : chip;
            this.status = val || 'SEMUA';
            this.terpilih = [];
            this.muat(1);
        },
        onFilterChange(payload) {
            if (payload && payload.values) {
                this.saringan = { ...this.saringan, ...payload.values };
            } else if (payload && payload.key) {
                this.saringan = { ...this.saringan, [payload.key]: payload.value };
            } else if (typeof payload === 'object') {
                this.saringan = { ...this.saringan, ...payload };
            }
            this.terpilih = [];
            this.muat(1);
        },
        onSearch(val) {
            this.q = val;
            clearTimeout(this.cariTimer);
            this.cariTimer = setTimeout(() => this.muat(1), 400);
        },
        resetSaringan() {
            this.saringan = { divisi: [], dept: [], level: [], jabatan: [], lokasi: [] };
            this.status = 'SEMUA';
            this.q = '';
            this.terpilih = [];
            this.muat(1);
        },
        terapkanSaringan() {
            this.terpilih = [];
            return this.muat(1);
        },
        onLimitChange(newLimit) {
            this.limit = Number(newLimit);
            this.muat(1);
        },
        async muat(page = 1) {
            this.loading = true;
            this.page = page;
            try {
                const r = await axios.get(`${API}/batch/${this.idBatch}/karyawan`, {
                    params: { ...this.params(), page, limit: this.limit },
                });
                if (r.data.success) {
                    this.rows = r.data.result || [];
                    this.total = r.data.pagination?.total ?? this.rows.length;
                }
            } catch (e) {
                this.rows = [];
                this.total = 0;
            } finally {
                this.loading = false;
            }
        },
        async pilihSemua() {
            this.memuatSemua = true;
            try {
                const r = await axios.get(`${API}/batch/${this.idBatch}/karyawan-kodes`, { params: this.params() });
                if (r.data.success) this.terpilih = r.data.result || [];
            } finally {
                this.memuatSemua = false;
            }
        },
        async cabutMassal() {
            this.cabutBuka = true;
        },
        async kirimCabut({ sebab, catatan }) {
            this.mencabut = true;
            try {
                const r = await axios.post(`${API}/batch/${this.idBatch}/bulk-cabut`, {
                    kode_karyawan: this.terpilih,
                    sebab,
                    catatan,
                });
                if (r.data.success) {
                    ElMessage.success(r.data.message);
                    this.cabutBuka = false;
                    this.terpilih = [];
                    this.muat(this.page);
                    this.$emit('berubah');
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menghapus akses');
            } finally {
                this.mencabut = false;
            }
        },
    },
};
</script>

<style scoped>
.bkt-table-container {
    border-radius: 12px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.08), 0 2px 8px -2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.bkt-karyawan-cell {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.25rem 0;
}

.bkt-action-btn {
    display: inline-grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-indigo-600);
    text-decoration: none;
    cursor: pointer;
    padding: 0;
    transition: all 0.15s ease;
}
.bkt-action-btn:hover:not(:disabled) {
    background: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-600);
    color: var(--hcis-white);
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
}
.bkt-action-btn:disabled,
.bkt-action-btn.is-loading {
    opacity: 0.85;
    cursor: wait;
    transform: none;
    box-shadow: none;
    background: var(--hcis-slate-200);
    border-color: var(--hcis-slate-300);
    color: var(--hcis-indigo-600);
}
.bkt-action-btn .spinner-border-sm {
    width: 0.82rem;
    height: 0.82rem;
    border-width: 0.14em;
}
</style>
