<template>
    <div class="sop-stack tdp-container">
        <!-- ══ 1. HCIS COMPACT STAT GROUP WIDGET (STRUKTUR DOKUMEN) ══ -->
        <div class="sop-fade-up mb-3">
            <HcisStatGroup
                :stats="statDokumen"
                :cols="4"
                gap="sm"
                layout="compact"
                appearance="crystal"
            />
        </div>

        <!-- ══ 2. HCIS FILTER BAR (SEARCH, CHIPS, ADVANCED FILTERS & EXPORT) ══ -->
        <div class="sop-fade-up mb-3">
            <HcisFilterBar
                :search="filterState.search"
                :filters="filterBarConfig"
                :filter-values="filterState"
                :quick-chips="quickChipsConfig"
                :active-chip-value="filterState.tipe"
                :results-count="filteredAndSortedItems.length"
                search-placeholder="Cari judul dokumen, nomor resmi, atau bagian section..."
                variant="glass"
                sentiment="brand"
                collapsible
                allow-export
                show-active-tags
                @update:search="onSearchChange"
                @chip-click="onChipClick"
                @filter-change="onFilterChange"
                @reset="resetFilters"
                @export="exportCsv"
            />
        </div>

        <!-- ══ 3. MAIN CONTENT CONTAINER (TABEL MATRIKS DOKUMEN) ══ -->
        <div class="sop-fade-up">
            <HcisCard
                variant="glass"
                sentiment="brand"
                size="sm"
                class="tdp-main-card"
            >
                <HcisTable
                    :columns="tableColumns"
                    :rows="paginatedItems"
                    row-key="Id_SOP_Paket_Item"
                    :loading="loading"
                    hoverable
                    interactive
                    variant="glass"
                    sentiment="brand"
                    :empty-title="itemsList.length === 0 ? 'Belum Ada Dokumen' : 'Tidak Ada Dokumen yang Cocok'"
                    :empty-description="itemsList.length === 0 ? 'Paket Dokumen ini belum memiliki daftar item prosedur atau dokumen.' : 'Silakan sesuaikan kata kunci pencarian atau filter yang aktif.'"
                    @row-click="bukaDetailModal"
                >
                    <!-- Kolom Urutan Item -->
                    <template #cell-urutan="{ row, index }">
                        <div class="d-flex align-items-center justify-content-center">
                            <HcisBadge
                                :label="String(row.Urutan || index + 1)"
                                variant="neutral"
                                appearance="subtle"
                                size="xs"
                                class="font-monospace fw-bold"
                            />
                        </div>
                    </template>

                    <!-- Kolom Judul & Metadata Prosedur -->
                    <template #cell-judul="{ row }">
                        <div class="d-flex align-items-center gap-3 py-1.5 min-w-0">
                            <div class="flex-shrink-0 tdp-icon-wrapper">
                                <ItemTypeIcon :tipe="row.Tipe_Item || 'DOKUMEN'" />
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div
                                    class="fw-bold text-dark text-truncate tdp-judul-link"
                                    :title="row.Judul_Item"
                                    @click.stop="bukaDetailModal(row)"
                                >
                                    {{ row.Judul_Item }}
                                </div>
                                <div class="d-flex align-items-center gap-1.5 mt-1 flex-wrap font-scale-xs">
                                    <HcisBadge
                                        :label="formatVersi(row.Versi_Ke)"
                                        variant="brand"
                                        appearance="subtle"
                                        size="2xs"
                                    />
                                    <HcisBadge
                                        v-if="row.Nomor_SOP_Resmi"
                                        :label="row.Nomor_SOP_Resmi"
                                        variant="neutral"
                                        appearance="subtle"
                                        size="2xs"
                                        icon="file-earmark-text"
                                    />
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Kolom Bagian / Section -->
                    <template #cell-section="{ row }">
                        <div class="min-w-0">
                            <HcisBadge
                                :label="row.Nama_Section || 'Umum / Tanpa Bagian'"
                                variant="purple"
                                appearance="subtle"
                                size="xs"
                                icon="folder2-open"
                                class="text-truncate max-w-100"
                            />
                        </div>
                    </template>

                    <!-- Kolom Target Pembaca -->
                    <template #cell-target="{ row }">
                        <div class="d-flex flex-column align-items-center justify-content-center">
                            <span class="fw-bold text-dark font-monospace">{{ row.Total_Target || 0 }}</span>
                            <span class="font-scale-2xs text-muted">Karyawan</span>
                        </div>
                    </template>

                    <!-- Kolom Dibuka -->
                    <template #cell-dibuka="{ row }">
                        <div class="d-flex flex-column align-items-center justify-content-center gap-0.5">
                            <HcisBadge
                                :label="`${row.Jumlah_Dibuka || 0}`"
                                variant="info"
                                appearance="subtle"
                                size="xs"
                                icon="eye"
                            />
                            <span v-if="row.Total_Target" class="font-scale-2xs text-muted font-monospace">
                                {{ Math.round(((row.Jumlah_Dibuka || 0) / (row.Total_Target || 1)) * 100) }}%
                            </span>
                        </div>
                    </template>

                    <!-- Kolom Dikonfirmasi -->
                    <template #cell-dikonfirmasi="{ row }">
                        <div class="d-flex flex-column align-items-center justify-content-center gap-0.5">
                            <HcisBadge
                                :label="`${row.Jumlah_Dikonfirmasi || 0}`"
                                variant="success"
                                appearance="subtle"
                                size="xs"
                                icon="check2-circle"
                            />
                            <span v-if="row.Total_Target" class="font-scale-2xs text-muted font-monospace">
                                {{ Math.round(((row.Jumlah_Dikonfirmasi || 0) / (row.Total_Target || 1)) * 100) }}%
                            </span>
                        </div>
                    </template>

                    <!-- Kolom Kepatuhan Selesai -->
                    <template #cell-kepatuhan="{ row }">
                        <div class="d-flex flex-column gap-1 min-w-0" style="max-width: 170px;">
                            <div class="d-flex align-items-center justify-content-between font-scale-xs">
                                <span class="text-muted">
                                    <strong class="text-dark">{{ row.Jumlah_Dikonfirmasi || 0 }}</strong> / {{ row.Total_Target || 0 }}
                                </span>
                                <span class="fw-bold font-monospace" :class="persenTextClass(row.Persen_Dikonfirmasi)">
                                    {{ formatPersen(row.Persen_Dikonfirmasi) }}%
                                </span>
                            </div>
                            <HcisProgress
                                :value="Number(row.Persen_Dikonfirmasi) || 0"
                                :show-value="false"
                                :sentiment="progressSentiment(row.Persen_Dikonfirmasi)"
                                size="xs"
                                animated
                            />
                        </div>
                    </template>

                    <!-- Kolom Aksi Cepat -->
                    <template #cell-aksi="{ row }">
                        <div class="tdp-action-btn-group" @click.stop>
                            <HcisButton
                                variant="secondary"
                                size="xs"
                                leading-icon="info-circle"
                                label="Rincian"
                                title="Lihat rincian metrik kepatuhan dokumen ini"
                                @click="bukaDetailModal(row)"
                            />
                        </div>
                    </template>
                </HcisTable>

                <!-- Integrated Card Footer Pagination -->
                <template v-if="filteredAndSortedItems.length > 0" #footer>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 w-100 py-1">
                        <span class="text-muted small">
                            Menampilkan <strong>{{ paginationInfo.start }}–{{ paginationInfo.end }}</strong> dari <strong>{{ filteredAndSortedItems.length }}</strong> dokumen
                        </span>
                        <HcisPagination
                            :total="filteredAndSortedItems.length"
                            :page-size="pageSize"
                            :current-page="currentPage"
                            :page-sizes="[5, 10, 20, 50]"
                            layout="sizes, prev, pager, next"
                            background
                            size="sm"
                            @update:current-page="currentPage = $event"
                            @update:page-size="pageSize = $event"
                        />
                    </div>
                </template>
            </HcisCard>
        </div>

        <!-- ══ 4. MODAL DIALOG DETAIL METRIK DOKUMEN ══ -->
        <HcisDialog
            :model-value="dialogDetailOpen"
            title="Rincian Metrik Dokumen"
            description="Informasi metadata prosedur dan capaian konfirmasi pembacaan"
            kicker="STRUKTUR DOKUMEN"
            icon="file-earmark-text-fill"
            sentiment="brand"
            variant="glass"
            pattern="grid"
            size="lg"
            @update:model-value="dialogDetailOpen = $event"
        >
            <div v-if="selectedItemForModal" class="d-flex flex-column gap-3">
                <!-- 1. Metadata Info Card (Clean HCIS Card) -->
                <HcisCard size="sm" variant="flat" sentiment="brand" class="mb-0">
                    <div class="d-flex align-items-start gap-3">
                        <div class="flex-shrink-0 mt-0.5">
                            <ItemTypeIcon :tipe="selectedItemForModal.Tipe_Item || 'DOKUMEN'" />
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1.5 flex-wrap">
                                <div class="fw-bold text-dark fs-6 text-truncate" :title="selectedItemForModal.Judul_Item">
                                    {{ selectedItemForModal.Judul_Item }}
                                </div>
                                <HcisBadge
                                    :label="`Urutan #${selectedItemForModal.Urutan || 1}`"
                                    variant="neutral"
                                    appearance="subtle"
                                    size="2xs"
                                    class="font-monospace"
                                />
                            </div>
                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                <HcisBadge
                                    :label="selectedItemForModal.Tipe_Item || 'DOKUMEN'"
                                    variant="brand"
                                    appearance="subtle"
                                    size="2xs"
                                />
                                <HcisBadge
                                    :label="formatVersi(selectedItemForModal.Versi_Ke)"
                                    variant="neutral"
                                    appearance="subtle"
                                    size="2xs"
                                />
                                <HcisBadge
                                    v-if="selectedItemForModal.Nomor_SOP_Resmi"
                                    :label="selectedItemForModal.Nomor_SOP_Resmi"
                                    variant="neutral"
                                    appearance="subtle"
                                    size="2xs"
                                    icon="file-earmark-text"
                                />
                                <HcisBadge
                                    :label="`Bagian: ${selectedItemForModal.Nama_Section || 'Umum'}`"
                                    variant="purple"
                                    appearance="subtle"
                                    size="2xs"
                                    icon="folder2-open"
                                />
                            </div>
                        </div>
                    </div>
                </HcisCard>

                <!-- 2. Kepatuhan Progress Card -->
                <HcisCard size="sm" variant="bordered" sentiment="brand" class="mb-0">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <HcisIcon name="speedometer2" size="xs" class="text-indigo-600" />
                            <span class="small fw-semibold text-dark">Tingkat Kepatuhan Pembacaan</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <HcisBadge
                                :label="complianceStatusBadge(selectedItemForModal.Persen_Dikonfirmasi).label"
                                :variant="complianceStatusBadge(selectedItemForModal.Persen_Dikonfirmasi).variant"
                                appearance="subtle"
                                size="xs"
                                :icon="complianceStatusBadge(selectedItemForModal.Persen_Dikonfirmasi).icon"
                            />
                            <span class="fw-bold font-monospace fs-6" :class="persenTextClass(selectedItemForModal.Persen_Dikonfirmasi)">
                                {{ formatPersen(selectedItemForModal.Persen_Dikonfirmasi) }}%
                            </span>
                        </div>
                    </div>

                    <HcisProgress
                        :value="Number(selectedItemForModal.Persen_Dikonfirmasi) || 0"
                        :show-value="false"
                        :sentiment="progressSentiment(selectedItemForModal.Persen_Dikonfirmasi)"
                        size="sm"
                        animated
                    />

                    <div class="d-flex align-items-center justify-content-between font-scale-xs text-muted mt-2 pt-1 border-top">
                        <span>
                            <strong class="text-dark">{{ selectedItemForModal.Jumlah_Dikonfirmasi || 0 }}</strong> dari {{ selectedItemForModal.Total_Target || 0 }} Karyawan Selesai
                        </span>
                        <span v-if="sisaTarget(selectedItemForModal) > 0" class="text-amber-600 fw-medium">
                            Sisa {{ sisaTarget(selectedItemForModal) }} karyawan belum konfirmasi
                        </span>
                        <span v-else class="text-emerald-600 fw-semibold">
                            100% Seluruh Target Telah Tuntas
                        </span>
                    </div>
                </HcisCard>

                <!-- 3. Stat Trio Grid -->
                <div class="row g-2">
                    <div class="col-4">
                        <HcisCard size="sm" variant="flat" class="text-center h-100 mb-0">
                            <div class="font-scale-2xs text-muted mb-1">Target Karyawan</div>
                            <div class="fw-bold fs-5 text-dark font-monospace">{{ selectedItemForModal.Total_Target || 0 }}</div>
                            <div class="font-scale-2xs text-muted mt-0.5">Penerima Paket</div>
                        </HcisCard>
                    </div>
                    <div class="col-4">
                        <HcisCard size="sm" variant="flat" sentiment="info" class="text-center h-100 mb-0">
                            <div class="font-scale-2xs text-muted mb-1">Telah Dibuka</div>
                            <div class="fw-bold fs-5 text-info font-monospace">{{ selectedItemForModal.Jumlah_Dibuka || 0 }}</div>
                            <div class="font-scale-2xs text-muted mt-0.5">
                                {{ rasioPersen(selectedItemForModal.Jumlah_Dibuka, selectedItemForModal.Total_Target) }}% Diakses
                            </div>
                        </HcisCard>
                    </div>
                    <div class="col-4">
                        <HcisCard size="sm" variant="flat" sentiment="success" class="text-center h-100 mb-0">
                            <div class="font-scale-2xs text-muted mb-1">Dikonfirmasi</div>
                            <div class="fw-bold fs-5 text-emerald-600 font-monospace">{{ selectedItemForModal.Jumlah_Dikonfirmasi || 0 }}</div>
                            <div class="font-scale-2xs text-muted mt-0.5">
                                {{ rasioPersen(selectedItemForModal.Jumlah_Dikonfirmasi, selectedItemForModal.Total_Target) }}% Tuntas
                            </div>
                        </HcisCard>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="d-flex align-items-center justify-content-end w-100">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Tutup"
                        @click="dialogDetailOpen = false"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script>
import { ElMessage } from 'element-plus';
import {
    HcisButton,
    HcisBadge,
    HcisFilterBar,
    HcisPagination,
    HcisCard,
    HcisTable,
    HcisIcon,
    HcisProgress,
    HcisDialog,
    HcisEmptyState,
    HcisStatGroup,
} from '@/components/ui';
import ItemTypeIcon from '@/Pages/LMS/SOP/Shared/components/ui/ItemTypeIcon.vue';

export default {
    name: 'TabDokumenPaket',
    components: {
        HcisButton,
        HcisBadge,
        HcisFilterBar,
        HcisPagination,
        HcisCard,
        HcisTable,
        HcisIcon,
        HcisProgress,
        HcisDialog,
        HcisEmptyState,
        HcisStatGroup,
        ItemTypeIcon,
    },
    props: {
        itemsList: {
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
            pageSize: 10,
            dialogDetailOpen: false,
            selectedItemForModal: null,
            filterState: {
                search: '',
                tipe: 'all',
                section: [],
                kepatuhan: 'all',
                sort_by: 'urutan_asc',
            },
            tableColumns: [
                { key: 'urutan', label: '#', width: '60px', align: 'center', sortable: true },
                { key: 'judul', label: 'Judul Dokumen Prosedur', width: '320px', sortable: true },
                { key: 'section', label: 'Bagian / Section', width: '180px', sortable: true },
                { key: 'target', label: 'Target', width: '100px', align: 'center', sortable: true },
                { key: 'dibuka', label: 'Dibuka', width: '100px', align: 'center', sortable: true },
                { key: 'dikonfirmasi', label: 'Dikonfirmasi', width: '120px', align: 'center', sortable: true },
                { key: 'kepatuhan', label: 'Tingkat Kepatuhan', width: '190px', sortable: true },
                { key: 'aksi', label: 'Aksi', width: '100px', align: 'right' },
            ],
        };
    },
    computed: {
        statDokumen() {
            return [
                {
                    label: 'Total Prosedur',
                    value: this.itemsList.length,
                    suffix: 'Item',
                    sentiment: 'brand',
                    icon: 'file-earmark-text-fill',
                    caption: 'Item Dokumen terdaftar',
                },
                {
                    label: 'Struktur Bagian',
                    value: this.daftarSectionUnik.length,
                    suffix: 'Bagian',
                    sentiment: 'purple',
                    icon: 'folder2-open',
                    caption: 'Pengelompokan bab / modul',
                },
                {
                    label: 'Total Akses Dibuka',
                    value: this.totalPembacaanDibuka,
                    suffix: 'Kali',
                    sentiment: 'info',
                    icon: 'eye-fill',
                    caption: 'Pembacaan materi dokumen',
                },
                {
                    label: 'Rata-Rata Kepatuhan',
                    value: `${this.rataRataKepatuhan}%`,
                    suffix: 'Tuntas',
                    sentiment: this.rataRataKepatuhan >= 80 ? 'success' : this.rataRataKepatuhan >= 50 ? 'warning' : 'danger',
                    icon: 'check-circle-fill',
                    progress: this.rataRataKepatuhan,
                    caption: 'Persentase penyelesaian',
                },
            ];
        },
        daftarSectionUnik() {
            const list = this.itemsList || [];
            const set = new Set(list.map((i) => i.Nama_Section || 'Umum / Tanpa Bagian'));
            return Array.from(set).filter(Boolean);
        },
        totalPembacaanDibuka() {
            return (this.itemsList || []).reduce((acc, cur) => acc + (Number(cur.Jumlah_Dibuka) || 0), 0);
        },
        rataRataKepatuhan() {
            const list = this.itemsList || [];
            if (!list.length) return 0;
            const sum = list.reduce((acc, cur) => acc + (Number(cur.Persen_Dikonfirmasi) || 0), 0);
            return Math.round(sum / list.length);
        },
        quickChipsConfig() {
            const list = this.itemsList || [];
            const hitungTipe = (t) => list.filter((i) => (i.Tipe_Item || 'DOKUMEN').toUpperCase() === t.toUpperCase()).length;

            return [
                { label: 'Semua Item', value: 'all', badge: list.length, icon: 'collection' },
                { label: 'Dokumen', value: 'DOKUMEN', badge: hitungTipe('DOKUMEN'), color: 'brand', icon: 'file-earmark-text' },
                { label: 'Formulir', value: 'FORM', badge: hitungTipe('FORM'), color: 'success', icon: 'file-earmark-check' },
                { label: 'Video', value: 'VIDEO', badge: hitungTipe('VIDEO'), color: 'info', icon: 'play-btn' },
                { label: 'Kuis', value: 'KUIS', badge: hitungTipe('KUIS'), color: 'warning', icon: 'patch-question' },
            ];
        },
        filterBarConfig() {
            return [
                {
                    key: 'section',
                    label: 'Bagian / Section',
                    type: 'select',
                    multiple: true,
                    filterable: true,
                    clearable: true,
                    placeholder: 'Semua Bagian / Section',
                    icon: 'folder2-open',
                    col: 4,
                    options: this.daftarSectionUnik.map((s) => ({ label: s, value: s })),
                },
                {
                    key: 'kepatuhan',
                    label: 'Status Capaian Kepatuhan',
                    type: 'select',
                    clearable: true,
                    placeholder: 'Semua Capaian',
                    icon: 'speedometer2',
                    col: 4,
                    options: [
                        { label: 'Semua Capaian', value: 'all' },
                        { label: 'Tuntas Sempurna (100%)', value: 'tuntas' },
                        { label: 'Kepatuhan Tinggi (≥ 75%)', value: 'tinggi' },
                        { label: 'Sedang Berjalan (1 - 74%)', value: 'progress' },
                        { label: 'Belum Ada Konfirmasi (0%)', value: 'nol' },
                    ],
                },
                {
                    key: 'sort_by',
                    label: 'Urutan Tampilan',
                    type: 'select',
                    clearable: false,
                    placeholder: 'Urutan Tampilan',
                    icon: 'sort-down',
                    col: 4,
                    options: [
                        { label: 'Nomor Urut (1 .. N)', value: 'urutan_asc' },
                        { label: 'Judul Prosedur (A - Z)', value: 'judul_asc' },
                        { label: 'Judul Prosedur (Z - A)', value: 'judul_desc' },
                        { label: 'Kepatuhan Tertinggi', value: 'kepatuhan_desc' },
                        { label: 'Kepatuhan Terendah', value: 'kepatuhan_asc' },
                        { label: 'Target Terbanyak', value: 'target_desc' },
                    ],
                },
            ];
        },
        filteredAndSortedItems() {
            let list = [...(this.itemsList || [])];

            // 1. Search Query
            if (this.filterState.search) {
                const q = this.filterState.search.toLowerCase().trim();
                list = list.filter(
                    (i) =>
                        (i.Judul_Item || '').toLowerCase().includes(q) ||
                        (i.Nomor_SOP_Resmi || '').toLowerCase().includes(q) ||
                        (i.Nama_Section || '').toLowerCase().includes(q) ||
                        (i.Tipe_Item || '').toLowerCase().includes(q),
                );
            }

            // 2. Chip Tipe Filter
            if (this.filterState.tipe && this.filterState.tipe !== 'all') {
                const t = this.filterState.tipe.toUpperCase();
                list = list.filter((i) => (i.Tipe_Item || 'DOKUMEN').toUpperCase() === t);
            }

            // 3. Section Filter
            if (Array.isArray(this.filterState.section) && this.filterState.section.length > 0) {
                list = list.filter((i) => {
                    const sec = i.Nama_Section || 'Umum / Tanpa Bagian';
                    return this.filterState.section.includes(sec);
                });
            }

            // 4. Kepatuhan Filter
            if (this.filterState.kepatuhan && this.filterState.kepatuhan !== 'all') {
                const k = this.filterState.kepatuhan;
                if (k === 'tuntas') {
                    list = list.filter((i) => (Number(i.Persen_Dikonfirmasi) || 0) >= 100);
                } else if (k === 'tinggi') {
                    list = list.filter((i) => (Number(i.Persen_Dikonfirmasi) || 0) >= 75);
                } else if (k === 'progress') {
                    list = list.filter((i) => {
                        const p = Number(i.Persen_Dikonfirmasi) || 0;
                        return p > 0 && p < 100;
                    });
                } else if (k === 'nol') {
                    list = list.filter((i) => (Number(i.Persen_Dikonfirmasi) || 0) === 0);
                }
            }

            // 5. Sorting
            const sortBy = this.filterState.sort_by;
            if (sortBy === 'urutan_asc') {
                list.sort((a, b) => (Number(a.Urutan) || 0) - (Number(b.Urutan) || 0));
            } else if (sortBy === 'judul_asc') {
                list.sort((a, b) => (a.Judul_Item || '').localeCompare(b.Judul_Item || ''));
            } else if (sortBy === 'judul_desc') {
                list.sort((a, b) => (b.Judul_Item || '').localeCompare(a.Judul_Item || ''));
            } else if (sortBy === 'kepatuhan_desc') {
                list.sort((a, b) => (Number(b.Persen_Dikonfirmasi) || 0) - (Number(a.Persen_Dikonfirmasi) || 0));
            } else if (sortBy === 'kepatuhan_asc') {
                list.sort((a, b) => (Number(a.Persen_Dikonfirmasi) || 0) - (Number(b.Persen_Dikonfirmasi) || 0));
            } else if (sortBy === 'target_desc') {
                list.sort((a, b) => (Number(b.Total_Target) || 0) - (Number(a.Total_Target) || 0));
            }

            return list;
        },
        paginatedItems() {
            const start = (this.currentPage - 1) * this.pageSize;
            return this.filteredAndSortedItems.slice(start, start + this.pageSize);
        },
        paginationInfo() {
            const start = (this.currentPage - 1) * this.pageSize + 1;
            const end = Math.min(this.currentPage * this.pageSize, this.filteredAndSortedItems.length);
            return { start, end };
        },
    },
    watch: {
        'filterState.search'() {
            this.currentPage = 1;
        },
        'filterState.tipe'() {
            this.currentPage = 1;
        },
        'filterState.section'() {
            this.currentPage = 1;
        },
        'filterState.kepatuhan'() {
            this.currentPage = 1;
        },
        'filterState.sort_by'() {
            this.currentPage = 1;
        },
    },
    methods: {
        onSearchChange(val) {
            this.filterState.search = val;
        },
        onChipClick(chip) {
            this.filterState.tipe = chip.value || chip.id || 'all';
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
        resetFilters() {
            this.filterState = {
                search: '',
                tipe: 'all',
                section: [],
                kepatuhan: 'all',
                sort_by: 'urutan_asc',
            };
            this.currentPage = 1;
        },
        bukaDetailModal(item) {
            this.selectedItemForModal = item;
            this.dialogDetailOpen = true;
        },
        formatVersi(v) {
            const num = parseInt(v, 10);
            return isNaN(num) || num < 1 ? 'Versi v1.0' : `Versi v${num}.0`;
        },
        formatPersen(p) {
            const num = Number(p);
            return isNaN(num) ? 0 : Math.round(num);
        },
        sisaTarget(item) {
            const total = Number(item?.Total_Target) || 0;
            const selesai = Number(item?.Jumlah_Dikonfirmasi) || 0;
            return Math.max(0, total - selesai);
        },
        rasioPersen(val, total) {
            const t = Number(total) || 0;
            const v = Number(val) || 0;
            return t > 0 ? Math.round((v / t) * 100) : 0;
        },
        complianceStatusBadge(persen) {
            const val = Number(persen) || 0;
            if (val >= 100) return { label: 'Tuntas Sempurna', variant: 'success', icon: 'check-circle-fill' };
            if (val >= 75) return { label: 'Kepatuhan Tinggi', variant: 'brand', icon: 'arrow-up-right-circle-fill' };
            if (val > 0) return { label: 'Sedang Berjalan', variant: 'warning', icon: 'hourglass-split' };
            return { label: 'Belum Ada Konfirmasi', variant: 'danger', icon: 'exclamation-circle-fill' };
        },
        persenTextClass(p) {
            const val = Number(p) || 0;
            return val >= 80 ? 'text-emerald-600' : val >= 40 ? 'text-indigo-600' : 'text-rose-600';
        },
        progressSentiment(persen) {
            const p = Number(persen) || 0;
            if (p >= 100) return 'emerald';
            if (p >= 60) return 'brand';
            if (p > 0) return 'amber';
            return 'danger';
        },
        exportCsv() {
            const list = this.filteredAndSortedItems;
            if (!list.length) {
                ElMessage.warning('Tidak ada data dokumen untuk diekspor.');
                return;
            }

            const header = [
                'No Urut',
                'Judul Item Dokumen',
                'Tipe Item',
                'Nomor Dokumen Resmi',
                'Versi',
                'Bagian / Section',
                'Total Target',
                'Jumlah Dibuka',
                'Jumlah Dikonfirmasi',
                'Persentase Kepatuhan (%)',
            ];

            const escapeCsv = (val) => {
                if (val === null || val === undefined) return '""';
                const s = String(val).replace(/"/g, '""');
                return `"${s}"`;
            };

            const rows = list.map((i, idx) => [
                i.Urutan || idx + 1,
                escapeCsv(i.Judul_Item),
                escapeCsv(i.Tipe_Item || 'DOKUMEN'),
                escapeCsv(i.Nomor_SOP_Resmi),
                escapeCsv(`v${i.Versi_Ke || 1}.0`),
                escapeCsv(i.Nama_Section || 'Umum'),
                i.Total_Target || 0,
                i.Jumlah_Dibuka || 0,
                i.Jumlah_Dikonfirmasi || 0,
                i.Persen_Dikonfirmasi || 0,
            ]);

            const csvContent = '\uFEFF' + [header.join(','), ...rows.map((r) => r.join(','))].join('\r\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            const safeName = (this.paket?.Nama_Paket || 'Paket_SOP').replace(/[^a-zA-Z0-9_-]/g, '_');
            a.href = url;
            a.download = `Struktur_Dokumen_${safeName}_${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            ElMessage.success('Data struktur dokumen berhasil diekspor ke format CSV.');
        },
    },
};
</script>

<style scoped>
.tdp-container {
    width: 100%;
}

.tdp-summary-mini-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.tdp-summary-mini-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -4px rgba(0, 0, 0, 0.06);
}

.tdp-mini-icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.625rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tdp-mini-icon.brand {
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}

.tdp-mini-icon.purple {
    background: var(--hcis-violet-50);
    color: var(--hcis-violet-600);
}

.tdp-mini-icon.info {
    background: #eff6ff;
    color: var(--hcis-sky-600);
}

.tdp-mini-icon.success {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-600);
}

.tdp-icon-wrapper {
    width: 2.25rem;
    height: 2.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-100);
}

.tdp-judul-link {
    cursor: pointer;
    transition: color 0.15s ease;
}

.tdp-judul-link:hover {
    color: var(--hcis-indigo-600) !important;
}

.tdp-action-btn-group {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    white-space: nowrap;
}

/* Grouped Section Styles */
.tdp-section-card {
    border-radius: 0.75rem;
}

.tdp-section-items-list {
    display: flex;
    flex-direction: column;
}

.tdp-section-item-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 0.5rem;
    border-bottom: 1px solid var(--hcis-slate-100);
    border-radius: 0.5rem;
    cursor: pointer;
    transition: background-color 0.15s ease;
}

.tdp-section-item-row:hover {
    background-color: var(--hcis-slate-50);
}

.tdp-section-item-row.is-last {
    border-bottom: none;
}

.tdp-item-number {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
    width: 1.5rem;
    text-align: center;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

/* Grid Card Styles */
.tdp-grid-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 0.875rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.tdp-grid-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.07);
    border-color: var(--hcis-slate-300);
}

.tdp-grid-card-header {
    padding: 1rem 1.125rem 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--hcis-slate-100);
}

.tdp-grid-card-body {
    padding: 0.875rem 1.125rem;
    flex-grow: 1;
}

.tdp-metric-box {
    padding: 0.5rem 0.25rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-100);
    border-radius: 0.5rem;
}

.tdp-grid-card-footer {
    padding: 0.75rem 1.125rem;
    background-color: var(--hcis-slate-50);
    border-top: 1px solid var(--hcis-slate-100);
}

.font-scale-xs {
    font-size: 0.8125rem;
}

.font-scale-2xs {
    font-size: 0.75rem;
}

.tnum {
    font-variant-numeric: tabular-nums;
}
</style>
