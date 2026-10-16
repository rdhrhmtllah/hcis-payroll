<template>
    <div class="tap-audit-root">
        <!-- ══ 1. STAT GROUP EKSEKUTIF (COMPACT CRYSTAL STRIP) ══ -->
        <HcisStatGroup
            :stats="statAudit"
            :cols="4"
            gap="sm"
            layout="compact"
            appearance="crystal"
            class="mb-3"
        />

        <!-- ══ 2. HCIS FILTER BAR RESMI (SEARCH, QUICK CHIPS, ADVANCED FILTERS, EXPORT & NATIVE VIEW TOGGLE) ══ -->
        <div class="mb-3">
            <HcisFilterBar
                :search="filterState.search"
                :filters="filterBarConfig"
                :filter-values="filterState"
                :quick-chips="quickChipsConfig"
                :active-chip-value="filterState.quickStatus"
                :results-count="filteredAktivitas.length"
                search-placeholder="Cari nama karyawan, NIK, judul item dokumen, departemen, atau IP..."
                variant="glass"
                sentiment="brand"
                collapsible
                allow-export
                show-active-tags
                :show-view-toggle="true"
                :view-mode="tampilanMode === 'timeline' ? 'grid' : 'table'"
                @update:search="onSearchChange"
                @chip-click="onChipClick"
                @filter-change="onFilterChange"
                @reset="resetFilters"
                @export="exportCsv"
                @view-mode-change="onViewModeChange"
                @update:view-mode="onViewModeChange"
            />
        </div>

        <!-- ══ 3. KARTU UTAMA LOG AUDIT & JEJAK AKTIVITAS ══ -->
        <HcisCard
            size="sm"
            variant="glass"
            sentiment="brand"
            icon="journal-check"
            title="Jejak Audit & Log Interaksi Karyawan"
            description="Riwayat pembacaan per item, durasi aktif, skor kuis, dan konfirmasi digital karyawan"
            :badge="`${filteredAktivitas.length} Catatan Aktivitas`"
            badge-variant="brand"
            class="tap-main-card"
        >

            <!-- ══ 4. KONTEN DATA UTAMA ══ -->
            <div v-if="loading" class="text-center py-5">
                <div class="spinner-border spinner-border-sm text-brand-600 me-2" role="status"></div>
                <span class="font-scale-xs text-muted">Menyinkronkan data log audit...</span>
            </div>

            <HcisEmptyState
                v-else-if="!filteredAktivitas.length"
                title="Tidak Ada Catatan Log Audit"
                :description="aktivitasList.length ? 'Tidak ada data log yang cocok dengan kombinasi saringan saat ini.' : 'Belum ada catatan aktivitas pembacaan dokumen untuk paket dokumen ini.'"
                icon="journal-x"
                variant="dashed"
                sentiment="neutral"
                class="my-4"
            >
                <template #actions>
                    <HcisButton
                        v-if="isFilterActive"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        label="Reset Saringan"
                        @click="resetFilters"
                    />
                </template>
            </HcisEmptyState>

            <!-- ══ 4A. TAMPILAN TABEL AUDIT ══ -->
            <div v-else-if="tampilanMode === 'table'" class="tap-table-wrapper">
                <HcisTable
                    :columns="kolomAktivitas"
                    :rows="paginatedAktivitas"
                    row-key="Id_SOP_Progress"
                    hoverable
                    interactive
                    size="sm"
                    variant="glass"
                    sentiment="brand"
                    @row-click="bukaDetailModal"
                >
                    <!-- Kolom 1: Waktu Peristiwa -->
                    <template #cell-waktu="{ row }">
                        <div class="d-flex flex-column">
                            <span class="font-monospace fw-bold font-scale-xs text-dark">
                                {{ row.Waktu_Konfirmasi || row.Waktu_Buka || '-' }}
                            </span>
                            <span class="font-scale-2xs text-muted mt-0.5">
                                {{ row.Waktu_Konfirmasi ? 'Konfirmasi Digital' : 'Akses Membaca' }}
                            </span>
                        </div>
                    </template>

                    <!-- Kolom 2: Karyawan -->
                    <template #cell-karyawan="{ row }">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <HcisAvatar
                                :name="row.Nama || row.Kode_Karyawan"
                                size="xs"
                                shape="squircle"
                                variant="brand"
                                class="flex-shrink-0"
                            />
                            <div class="min-w-0">
                                <div class="fw-bold text-dark font-scale-xs text-truncate" :title="row.Nama">
                                    {{ row.Nama || row.Kode_Karyawan }}
                                </div>
                                <div class="d-flex align-items-center gap-1 font-scale-2xs text-muted font-monospace text-truncate">
                                    <span>{{ row.No_Induk_Karyawan || row.Kode_Karyawan }}</span>
                                    <span v-if="row.Departemen || row.Divisi">· {{ row.Departemen || row.Divisi }}</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Kolom 3: Item Prosedur -->
                    <template #cell-item="{ row }">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <ItemTypeIcon :tipe="row.Tipe_Item || 'DOKUMEN'" class="flex-shrink-0" />
                            <div class="min-w-0">
                                <div class="fw-semibold text-dark font-scale-xs text-truncate" :title="row.Judul_Item">
                                    {{ row.Judul_Item || 'Item Dokumen' }}
                                </div>
                                <div class="d-flex align-items-center gap-1.5 font-scale-2xs text-muted mt-0.5 flex-wrap">
                                    <HcisBadge
                                        v-if="row.Tipe_Item"
                                        :label="row.Tipe_Item"
                                        variant="neutral"
                                        appearance="subtle"
                                        size="2xs"
                                    />
                                    <span v-if="row.Nomor_SOP_Resmi" class="text-truncate">{{ row.Nomor_SOP_Resmi }}</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Kolom 4: Durasi Baca -->
                    <template #cell-durasi="{ row }">
                        <div class="d-flex align-items-center justify-content-center gap-1 font-monospace font-scale-xs text-slate-700">
                            <HcisIcon name="stopwatch" size="2xs" class="text-slate-400" />
                            <span>{{ formatDurasi(row.Durasi_Detik) }}</span>
                        </div>
                    </template>

                    <!-- Kolom 5: Skor Kuis -->
                    <template #cell-kuis="{ row }">
                        <div class="text-center">
                            <HcisBadge
                                v-if="row.Skor_Kuis !== null && row.Skor_Kuis !== undefined"
                                :label="`${row.Skor_Kuis}`"
                                :variant="row.Skor_Kuis >= 80 ? 'success' : 'warning'"
                                appearance="subtle"
                                size="xs"
                                class="font-monospace fw-bold"
                            />
                            <span v-else class="text-slate-300 font-scale-xs">—</span>
                        </div>
                    </template>

                    <!-- Kolom 6: Status -->
                    <template #cell-status="{ row }">
                        <div class="text-center">
                            <HcisBadge
                                :label="row.Status === 'SELESAI' ? 'Dikonfirmasi' : 'Dibuka'"
                                :variant="row.Status === 'SELESAI' ? 'success' : 'warning'"
                                appearance="subtle"
                                size="xs"
                                :icon="row.Status === 'SELESAI' ? 'patch-check-fill' : 'book-half'"
                            />
                        </div>
                    </template>

                    <!-- Kolom 7: Alamat IP -->
                    <template #cell-ip="{ row }">
                        <div class="d-flex align-items-center gap-1 font-monospace font-scale-2xs text-muted text-truncate" :title="row.IP_Address">
                            <HcisIcon name="hdd-network" size="2xs" class="text-slate-400 flex-shrink-0" />
                            <span class="text-truncate">{{ row.IP_Address || '—' }}</span>
                        </div>
                    </template>

                    <!-- Kolom 8: Aksi -->
                    <template #cell-aksi="{ row }">
                        <div class="text-center">
                            <HcisButton
                                variant="secondary"
                                size="xs"
                                icon-only
                                leading-icon="eye"
                                title="Lihat rincian lengkap log audit"
                                @click.stop="bukaDetailModal(row)"
                            />
                        </div>
                    </template>
                </HcisTable>

                <!-- Footer Pagination Table -->
                <div
                    v-if="filteredAktivitas.length > 0"
                    class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-3 px-3 pb-2 bg-white rounded-3 border border-top-0"
                >
                    <span class="font-scale-2xs text-muted">
                        Menampilkan <strong>{{ paginationInfo.start }}–{{ paginationInfo.end }}</strong> dari <strong>{{ filteredAktivitas.length }}</strong> catatan audit
                    </span>
                    <HcisPagination
                        :total="filteredAktivitas.length"
                        :page-size="pageSize"
                        :current-page="currentPage"
                        :page-sizes="[10, 15, 25, 50]"
                        layout="sizes, prev, pager, next"
                        background
                        size="sm"
                        @update:current-page="currentPage = $event"
                        @update:page-size="pageSize = $event"
                    />
                </div>
            </div>

            <!-- ══ 4B. TAMPILAN LINIMASA KRONOLOGIS ══ -->
            <div v-else class="tap-timeline-wrapper">
                <div class="tap-timeline-feed">
                    <div
                        v-for="(item, idx) in paginatedAktivitas"
                        :key="item.Id_SOP_Progress || idx"
                        class="tap-timeline-item"
                    >
                        <div class="tap-timeline-marker">
                            <HcisIcon
                                :name="item.Status === 'SELESAI' ? 'check-circle-fill' : 'book-fill'"
                                :class="item.Status === 'SELESAI' ? 'text-success' : 'text-warning'"
                                size="xs"
                            />
                        </div>
                        <div class="tap-timeline-content">
                            <div class="tap-timeline-header">
                                <div class="d-flex align-items-center gap-2">
                                    <HcisAvatar
                                        :name="item.Nama || item.Kode_Karyawan"
                                        size="xs"
                                        shape="squircle"
                                        variant="brand"
                                    />
                                    <div>
                                        <div class="fw-bold font-scale-xs text-dark">{{ item.Nama || item.Kode_Karyawan }}</div>
                                        <div class="font-scale-2xs text-muted font-monospace">{{ item.No_Induk_Karyawan || item.Kode_Karyawan }} · {{ item.Departemen || item.Divisi || 'Semua Unit' }}</div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="font-monospace fw-semibold font-scale-2xs text-dark">{{ item.Waktu_Konfirmasi || item.Waktu_Buka }}</div>
                                    <HcisBadge
                                        :label="item.Status === 'SELESAI' ? 'Selesai Dibaca' : 'Sedang Dibaca'"
                                        :variant="item.Status === 'SELESAI' ? 'success' : 'warning'"
                                        appearance="subtle"
                                        size="2xs"
                                    />
                                </div>
                            </div>
                            <div class="tap-timeline-body mt-2 p-2 bg-slate-50 rounded-2 border border-slate-100 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <ItemTypeIcon :tipe="item.Tipe_Item" size="sm" />
                                    <div>
                                        <div class="font-scale-xs fw-semibold text-dark">{{ item.Judul_Item }}</div>
                                        <div class="font-scale-2xs text-muted">{{ item.Nomor_SOP_Resmi || 'Tanpa Kode' }}</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span v-if="item.Skor_Kuis !== null && item.Skor_Kuis !== undefined" class="badge bg-indigo-50 text-indigo-700 border border-indigo-200 font-scale-2xs">
                                        Skor: {{ item.Skor_Kuis }}
                                    </span>
                                    <span class="font-scale-2xs text-muted font-monospace">
                                        <i class="bi bi-stopwatch me-0.5"></i>{{ formatDurasi(item.Durasi_Detik) }}
                                    </span>
                                    <HcisButton
                                        variant="secondary"
                                        size="2xs"
                                        leading-icon="eye"
                                        label="Rincian"
                                        @click="bukaDetailModal(item)"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Pagination Timeline -->
                <div
                    v-if="filteredAktivitas.length > 0"
                    class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-3 px-3 pb-2 bg-white rounded-3 border"
                >
                    <span class="font-scale-2xs text-muted">
                        Menampilkan <strong>{{ paginationInfo.start }}–{{ paginationInfo.end }}</strong> dari <strong>{{ filteredAktivitas.length }}</strong> linimasa
                    </span>
                    <HcisPagination
                        :total="filteredAktivitas.length"
                        :page-size="pageSize"
                        :current-page="currentPage"
                        :page-sizes="[10, 15, 25, 50]"
                        layout="sizes, prev, pager, next"
                        background
                        size="sm"
                        @update:current-page="currentPage = $event"
                        @update:page-size="pageSize = $event"
                    />
                </div>
            </div>
        </HcisCard>

        <!-- ══ 5. MODAL DIALOG DETAIL AUDIT FORENSIK ══ -->
        <HcisDialog
            :model-value="modalDetail.show"
            :title="`Jejak Audit Forensik · ${modalDetail.item?.Nama || modalDetail.item?.Kode_Karyawan || 'Karyawan'}`"
            description="Rincian rekaman aktivitas pembacaan dokumen, waktu stempel digital, dan verifikasi perangkat"
            kicker="AUDIT TRAIL DIGITAL"
            icon="journal-check"
            sentiment="brand"
            variant="glass"
            size="lg"
            @update:model-value="modalDetail.show = $event"
        >
            <div v-if="modalDetail.item" class="d-flex flex-column gap-3">
                <!-- Card 1: Identitas Karyawan -->
                <HcisCard size="xs" variant="flat" sentiment="brand" class="mb-0">
                    <div class="font-scale-2xs fw-bold text-uppercase text-brand tracking-wider mb-2">
                        Profil Karyawan Terkait
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <HcisAvatar
                            :name="modalDetail.item.Nama || modalDetail.item.Kode_Karyawan"
                            size="md"
                            shape="squircle"
                            variant="brand"
                        />
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold font-scale-sm text-dark">{{ modalDetail.item.Nama || modalDetail.item.Kode_Karyawan }}</div>
                            <div class="font-scale-2xs text-muted font-monospace mb-1">
                                NIK: {{ modalDetail.item.No_Induk_Karyawan || modalDetail.item.Kode_Karyawan }}
                            </div>
                            <div class="d-flex align-items-center gap-2 font-scale-2xs text-muted flex-wrap">
                                <span v-if="modalDetail.item.Departemen || modalDetail.item.Divisi">
                                    {{ [modalDetail.item.Departemen, modalDetail.item.Divisi].filter(Boolean).join(' · ') }}
                                </span>
                                <span v-if="modalDetail.item.Jabatan">| {{ modalDetail.item.Jabatan }}</span>
                            </div>
                        </div>
                    </div>
                </HcisCard>

                <!-- Card 2: Rincian Item Prosedur -->
                <HcisCard size="xs" variant="bordered" sentiment="neutral" class="mb-0">
                    <div class="font-scale-2xs fw-bold text-uppercase text-secondary tracking-wider mb-2">
                        Dokumen Prosedur yang Diakses
                    </div>
                    <div class="d-flex align-items-start gap-2.5">
                        <ItemTypeIcon :tipe="modalDetail.item.Tipe_Item || 'DOKUMEN'" />
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold font-scale-xs text-dark">{{ modalDetail.item.Judul_Item || 'Item Dokumen' }}</div>
                            <div class="d-flex align-items-center gap-1.5 font-scale-2xs text-muted mt-1 flex-wrap">
                                <HcisBadge
                                    v-if="modalDetail.item.Tipe_Item"
                                    :label="modalDetail.item.Tipe_Item"
                                    variant="neutral"
                                    appearance="subtle"
                                    size="2xs"
                                />
                                <span v-if="modalDetail.item.Nomor_SOP_Resmi">No: {{ modalDetail.item.Nomor_SOP_Resmi }}</span>
                                <span v-if="modalDetail.item.Urutan !== undefined">Urutan Ke-{{ modalDetail.item.Urutan }}</span>
                            </div>
                        </div>
                    </div>
                </HcisCard>

                <!-- Card 3: Telemetri Waktu & Verifikasi Jaringan -->
                <HcisCard size="xs" variant="flat" sentiment="neutral" class="mb-0">
                    <div class="font-scale-2xs fw-bold text-uppercase text-secondary tracking-wider mb-2">
                        Stempel Waktu & Telemetri Digital
                    </div>
                    <div class="row g-2 font-scale-xs">
                        <div class="col-12 col-sm-6">
                            <div class="p-2 bg-white rounded border border-slate-200">
                                <div class="font-scale-2xs text-muted mb-0.5">Pertama Kali Dibuka</div>
                                <div class="font-monospace fw-semibold text-dark">{{ modalDetail.item.Waktu_Buka || 'Belum dibuka' }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="p-2 bg-white rounded border border-slate-200">
                                <div class="font-scale-2xs text-muted mb-0.5">Waktu Konfirmasi Selesai</div>
                                <div class="font-monospace fw-semibold" :class="modalDetail.item.Waktu_Konfirmasi ? 'text-success' : 'text-muted'">
                                    {{ modalDetail.item.Waktu_Konfirmasi || 'Belum dikonfirmasi' }}
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="p-2 bg-white rounded border border-slate-200">
                                <div class="font-scale-2xs text-muted mb-0.5">Durasi Interaksi Aktif</div>
                                <div class="font-monospace fw-semibold text-dark">{{ formatDurasi(modalDetail.item.Durasi_Detik) }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="p-2 bg-white rounded border border-slate-200">
                                <div class="font-scale-2xs text-muted mb-0.5">Skor Pemahaman / Kuis</div>
                                <div class="font-monospace fw-semibold text-dark">
                                    {{ modalDetail.item.Skor_Kuis !== null && modalDetail.item.Skor_Kuis !== undefined ? `${modalDetail.item.Skor_Kuis} / 100` : 'Tanpa Kuis' }}
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-2 bg-white rounded border border-slate-200 d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="font-scale-2xs text-muted mb-0.5">Alamat IP Gateway</div>
                                    <div class="font-monospace text-slate-700">{{ modalDetail.item.IP_Address || '—' }}</div>
                                </div>
                                <HcisBadge
                                    :label="modalDetail.item.Status === 'SELESAI' ? 'Status: Selesai Tuntas' : 'Status: Dalam Pembacaan'"
                                    :variant="modalDetail.item.Status === 'SELESAI' ? 'success' : 'warning'"
                                    appearance="subtle"
                                    size="xs"
                                />
                            </div>
                        </div>
                    </div>
                </HcisCard>
            </div>

            <template #footer>
                <div class="d-flex align-items-center justify-content-end w-100">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Tutup"
                        @click="modalDetail.show = false"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script>
import {
    HcisButton,
    HcisBadge,
    HcisCard,
    HcisTable,
    HcisIcon,
    HcisAvatar,
    HcisDialog,
    HcisEmptyState,
    HcisPagination,
    HcisStatGroup,
    HcisFilterBar,
} from '@/components/ui';
import ItemTypeIcon from '@/Pages/LMS/SOP/Shared/components/ui/ItemTypeIcon.vue';

export default {
    name: 'TabAuditPaket',
    components: {
        HcisButton,
        HcisBadge,
        HcisCard,
        HcisTable,
        HcisIcon,
        HcisAvatar,
        HcisDialog,
        HcisEmptyState,
        HcisPagination,
        HcisStatGroup,
        HcisFilterBar,
        ItemTypeIcon,
    },
    props: {
        aktivitasList: {
            type: Array,
            default: () => [],
        },
        paket: {
            type: Object,
            default: () => ({}),
        },
        idPaket: {
            type: String,
            required: true,
        },
        loading: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            currentPage: 1,
            pageSize: 15,
            tampilanMode: 'timeline', // 'timeline' | 'table'
            modalDetail: {
                show: false,
                item: null,
            },
            filterState: {
                search: '',
                quickStatus: 'all',
                status: 'all',
                tipe: 'all',
                departemen: [],
                sort_by: 'terbaru',
            },
            kolomAktivitas: [
                { key: 'waktu', label: 'Waktu Peristiwa', width: '170px' },
                { key: 'karyawan', label: 'Karyawan', width: '250px' },
                { key: 'item', label: 'Item Prosedur & Nomor Dokumen' },
                { key: 'durasi', label: 'Durasi Baca', width: '120px', align: 'center' },
                { key: 'kuis', label: 'Skor Kuis', width: '100px', align: 'center' },
                { key: 'status', label: 'Status', width: '130px', align: 'center' },
                { key: 'ip', label: 'Alamat IP', width: '140px' },
                { key: 'aksi', label: 'Aksi', width: '60px', align: 'center' },
            ],
        };
    },
    computed: {
        statAudit() {
            const list = this.aktivitasList || [];
            const total = list.length;
            const selesai = list.filter((a) => a.Status === 'SELESAI').length;
            const dibuka = list.filter((a) => a.Status !== 'SELESAI').length;
            const persenSelesai = total > 0 ? Math.round((selesai / total) * 100) : 0;

            const totalDetik = list.reduce((acc, a) => acc + (Number(a.Durasi_Detik) || 0), 0);
            const avgDetik = total > 0 ? Math.round(totalDetik / total) : 0;

            return [
                {
                    label: 'Total Catatan Audit',
                    value: total,
                    suffix: 'Log',
                    sentiment: 'brand',
                    icon: 'journal-text',
                    caption: 'Total riwayat pembacaan & interaksi',
                },
                {
                    label: 'Dikonfirmasi Selesai',
                    value: selesai,
                    suffix: `(${persenSelesai}%)`,
                    sentiment: 'success',
                    icon: 'check-circle-fill',
                    caption: 'Item yang telah ditandatangani/konfirmasi',
                },
                {
                    label: 'Sedang Dibaca / Akses',
                    value: dibuka,
                    suffix: 'Log',
                    sentiment: 'warning',
                    icon: 'book-half',
                    caption: 'Masih dalam proses membaca item',
                },
                {
                    label: 'Rata-rata Durasi Baca',
                    value: this.formatDurasi(avgDetik),
                    suffix: '',
                    sentiment: 'teal',
                    icon: 'stopwatch',
                    caption: 'Durasi aktif per interaksi item',
                },
            ];
        },
        quickChipsConfig() {
            const list = this.aktivitasList || [];
            const total = list.length;
            const selesai = list.filter((a) => a.Status === 'SELESAI').length;
            const dibuka = list.filter((a) => a.Status !== 'SELESAI').length;
            const kuis = list.filter((a) => a.Skor_Kuis !== null && a.Skor_Kuis !== undefined).length;

            return [
                { label: 'Semua Log', value: 'all', badge: total, icon: 'journal-text' },
                { label: 'Dikonfirmasi', value: 'SELESAI', badge: selesai, color: 'success', icon: 'patch-check-fill' },
                { label: 'Sedang Dibaca', value: 'BUKA', badge: dibuka, color: 'warning', icon: 'book-half' },
                { label: 'Ujian / Kuis', value: 'KUIS', badge: kuis, color: 'info', icon: 'patch-question' },
            ];
        },
        opsiTipeItem() {
            const list = this.aktivitasList || [];
            const set = new Set();
            list.forEach((a) => {
                if (a.Tipe_Item) set.add(a.Tipe_Item);
            });
            return Array.from(set).map((t) => ({ label: t, value: t }));
        },
        opsiDepartemen() {
            const list = this.aktivitasList || [];
            const set = new Set();
            list.forEach((a) => {
                const d = a.Departemen || a.Divisi;
                if (d) set.add(d);
            });
            return Array.from(set).sort().map((d) => ({ label: d, value: d }));
        },
        filterBarConfig() {
            return [
                {
                    key: 'status',
                    label: 'Status Konfirmasi',
                    type: 'select',
                    clearable: true,
                    placeholder: 'Semua Status',
                    icon: 'check-circle',
                    col: 3,
                    options: [
                        { label: 'Semua Status', value: 'all' },
                        { label: 'Dikonfirmasi Selesai', value: 'SELESAI' },
                        { label: 'Sedang Dibaca / Dibuka', value: 'BUKA' },
                    ],
                },
                {
                    key: 'tipe',
                    label: 'Tipe Dokumen / Item',
                    type: 'select',
                    clearable: true,
                    placeholder: 'Semua Tipe Item',
                    icon: 'file-earmark-text',
                    col: 3,
                    options: [
                        { label: 'Semua Tipe Item', value: 'all' },
                        ...this.opsiTipeItem,
                    ],
                },
                {
                    key: 'departemen',
                    label: 'Departemen / Unit Kerja',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    placeholder: 'Semua Departemen',
                    icon: 'building',
                    col: 3,
                    options: this.opsiDepartemen,
                },
                {
                    key: 'sort_by',
                    label: 'Urutan Log Audit',
                    type: 'select',
                    placeholder: 'Waktu Terbaru',
                    icon: 'sort-down',
                    col: 3,
                    options: [
                        { label: 'Waktu Peristiwa Terbaru', value: 'terbaru' },
                        { label: 'Waktu Peristiwa Terlama', value: 'terlama' },
                        { label: 'Durasi Interaksi Tertinggi', value: 'durasi_desc' },
                        { label: 'Skor Kuis Tertinggi', value: 'skor_desc' },
                    ],
                },
            ];
        },
        isFilterActive() {
            return (
                Boolean(this.filterState.search) ||
                this.filterState.quickStatus !== 'all' ||
                (this.filterState.status && this.filterState.status !== 'all') ||
                (this.filterState.tipe && this.filterState.tipe !== 'all') ||
                (Array.isArray(this.filterState.departemen) && this.filterState.departemen.length > 0) ||
                (this.filterState.sort_by && this.filterState.sort_by !== 'terbaru')
            );
        },
        filteredAktivitas() {
            let list = [...(this.aktivitasList || [])];

            // 1. Search Query
            if (this.filterState.search) {
                const q = this.filterState.search.toLowerCase().trim();
                list = list.filter(
                    (a) =>
                        (a.Nama || '').toLowerCase().includes(q) ||
                        (a.Kode_Karyawan || '').toLowerCase().includes(q) ||
                        (a.No_Induk_Karyawan || '').toLowerCase().includes(q) ||
                        (a.Judul_Item || '').toLowerCase().includes(q) ||
                        (a.Nomor_SOP_Resmi || '').toLowerCase().includes(q) ||
                        (a.Departemen || '').toLowerCase().includes(q) ||
                        (a.Divisi || '').toLowerCase().includes(q) ||
                        (a.IP_Address || '').toLowerCase().includes(q),
                );
            }

            // 2. Quick Status Chips
            if (this.filterState.quickStatus === 'SELESAI') {
                list = list.filter((a) => a.Status === 'SELESAI');
            } else if (this.filterState.quickStatus === 'BUKA') {
                list = list.filter((a) => a.Status !== 'SELESAI');
            } else if (this.filterState.quickStatus === 'KUIS') {
                list = list.filter((a) => a.Skor_Kuis !== null && a.Skor_Kuis !== undefined);
            }

            // 3. Status Filter Dropdown
            if (this.filterState.status && this.filterState.status !== 'all') {
                if (this.filterState.status === 'SELESAI') {
                    list = list.filter((a) => a.Status === 'SELESAI');
                } else if (this.filterState.status === 'BUKA') {
                    list = list.filter((a) => a.Status !== 'SELESAI');
                }
            }

            // 4. Tipe Item
            if (this.filterState.tipe && this.filterState.tipe !== 'all') {
                list = list.filter((a) => (a.Tipe_Item || '').toUpperCase() === this.filterState.tipe.toUpperCase());
            }

            // 5. Departemen Filter
            if (Array.isArray(this.filterState.departemen) && this.filterState.departemen.length > 0) {
                list = list.filter((a) => {
                    const dept = a.Departemen || a.Divisi;
                    return this.filterState.departemen.includes(dept);
                });
            }

            // 6. Sorting
            const sortBy = this.filterState.sort_by || 'terbaru';
            list.sort((a, b) => {
                if (sortBy === 'terbaru') {
                    const timeA = new Date(a.Waktu_Konfirmasi || a.Waktu_Buka || 0).getTime();
                    const timeB = new Date(b.Waktu_Konfirmasi || b.Waktu_Buka || 0).getTime();
                    return timeB - timeA;
                }
                if (sortBy === 'terlama') {
                    const timeA = new Date(a.Waktu_Konfirmasi || a.Waktu_Buka || 0).getTime();
                    const timeB = new Date(b.Waktu_Konfirmasi || b.Waktu_Buka || 0).getTime();
                    return timeA - timeB;
                }
                if (sortBy === 'durasi_desc') {
                    return (Number(b.Durasi_Detik) || 0) - (Number(a.Durasi_Detik) || 0);
                }
                if (sortBy === 'skor_desc') {
                    return (Number(b.Skor_Kuis) || 0) - (Number(a.Skor_Kuis) || 0);
                }
                return 0;
            });

            return list;
        },
        paginatedAktivitas() {
            const start = (this.currentPage - 1) * this.pageSize;
            return this.filteredAktivitas.slice(start, start + this.pageSize);
        },
        paginationInfo() {
            const total = this.filteredAktivitas.length;
            if (!total) return { start: 0, end: 0, total: 0 };
            const start = (this.currentPage - 1) * this.pageSize + 1;
            const end = Math.min(start + this.pageSize - 1, total);
            return { start, end, total };
        },
    },
    watch: {
        filterState: {
            deep: true,
            handler() {
                this.currentPage = 1;
            },
        },
    },
    methods: {
        onSearchChange(val) {
            this.filterState.search = val;
        },
        onChipClick(chip) {
            this.filterState.quickStatus = chip.value || chip.id || 'all';
        },
        onFilterChange(payload) {
            if (payload && payload.key) {
                this.filterState[payload.key] = payload.value;
            } else if (payload && payload.values) {
                this.filterState = { ...this.filterState, ...payload.values };
            } else if (typeof payload === 'object') {
                this.filterState = { ...this.filterState, ...payload };
            }
            this.currentPage = 1;
        },
        onViewModeChange(mode) {
            this.tampilanMode = mode === 'grid' || mode === 'timeline' ? 'timeline' : 'table';
        },
        formatDurasi(detik) {
            if (!detik || detik <= 0) return '0 dtk';
            const m = Math.floor(detik / 60);
            const s = detik % 60;
            return m > 0 ? `${m}m ${s}s` : `${s}s`;
        },
        bukaDetailModal(row) {
            this.modalDetail = {
                show: true,
                item: row,
            };
        },
        resetFilters() {
            this.filterState = {
                search: '',
                quickStatus: 'all',
                status: 'all',
                tipe: 'all',
                departemen: [],
                sort_by: 'terbaru',
            };
            this.currentPage = 1;
        },
        exportCsv() {
            const list = this.filteredAktivitas;
            if (!list.length) return;

            const headers = [
                'Waktu Peristiwa',
                'Kode Karyawan',
                'Nama Karyawan',
                'NIK',
                'Departemen',
                'Divisi',
                'Jabatan',
                'Judul Item',
                'Tipe Item',
                'Nomor Dokumen Resmi',
                'Durasi (Detik)',
                'Skor Kuis',
                'Status',
                'IP Address',
            ];

            const rows = list.map((a) => [
                `"${a.Waktu_Konfirmasi || a.Waktu_Buka || ''}"`,
                `"${a.Kode_Karyawan || ''}"`,
                `"${a.Nama || ''}"`,
                `"${a.No_Induk_Karyawan || ''}"`,
                `"${a.Departemen || ''}"`,
                `"${a.Divisi || ''}"`,
                `"${a.Jabatan || ''}"`,
                `"${a.Judul_Item || ''}"`,
                `"${a.Tipe_Item || ''}"`,
                `"${a.Nomor_SOP_Resmi || ''}"`,
                `"${a.Durasi_Detik || 0}"`,
                `"${a.Skor_Kuis !== null && a.Skor_Kuis !== undefined ? a.Skor_Kuis : ''}"`,
                `"${a.Status || ''}"`,
                `"${a.IP_Address || ''}"`,
            ]);

            const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map((e) => e.join(','))].join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', `Log_Audit_SOP_${this.idPaket}_${new Date().toISOString().slice(0, 10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },
    },
};
</script>

<style scoped>
.tap-audit-root {
    width: 100%;
}

.tap-view-switcher .btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.6rem;
}

.tap-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

/* ══ Timeline View ══ */
.tap-timeline-feed {
    display: flex;
    flex-direction: column;
    padding: 0.5rem 0;
}

.tap-timeline-item {
    display: flex;
    gap: 1rem;
    position: relative;
}

.tap-timeline-marker {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 24px;
    flex-shrink: 0;
}

.tap-marker-dot {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border: 2px solid var(--hcis-slate-200);
    z-index: 2;
}

.tap-marker-dot.is-success {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
    border-color: var(--hcis-emerald-200);
}

.tap-marker-dot.is-warning {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-600);
    border-color: var(--hcis-amber-200);
}

.tap-marker-line {
    width: 2px;
    flex-grow: 1;
    background: var(--hcis-slate-200);
    margin: 4px 0;
}

.tap-timeline-content-card {
    flex-grow: 1;
    min-width: 0;
}

.tracking-wider {
    letter-spacing: 0.04em;
}
</style>
