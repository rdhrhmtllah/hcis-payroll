<template>
    <div class="container-fluid px-0 sop-scope sop-apqc-page">
        <!-- ══ 1. EXECUTIVE HEADER ══ -->
        <HcisHeader
            title="Master APQC"
            kicker="Tata Kelola · Master Data"
            description="Kerangka Klasifikasi Proses Bisnis (APQC Process Classification Framework). Memetakan dokumen ke dalam taksonomi proses operasional dan manajemen."
            icon="bounding-box"
            sentiment="brand"
            size="md"
            animated
            class="mb-4"
        >
            <template #badge>
                <HcisBadge v-if="!loading" :label="`${pagination.total} Proses`" variant="brand" appearance="glass" size="sm" dot />
            </template>

            <template #actions>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton
                        variant="glass"
                        size="md"
                        leading-icon="arrow-clockwise"
                        :loading="loading"
                        label="Segarkan"
                        title="Segarkan data proses APQC"
                        aria-label="Segarkan data proses APQC"
                        @click="fetchData(pagination.current_page)"
                    />
                    <HcisButton
                        variant="primary"
                        size="md"
                        leading-icon="plus-lg"
                        label="Tambah Proses"
                        title="Tambah proses APQC baru"
                        aria-label="Tambah proses APQC baru"
                        @click="openModal()"
                    />
                </div>
            </template>
        </HcisHeader>

        <!-- ══ 2. EXECUTIVE TELEMETRY STATS ══ -->
        <SopApqcStats
            :total="statsSummary.total"
            :used-count="statsSummary.used"
            :with-types-count="statsSummary.withTypesCount"
            :usage-rate="statsSummary.usageRate"
            :loading="loading"
        />

        <!-- ══ 3. FILTER BAR (SEARCH + VIEW MODE TOGGLE + QUICK CHIPS) ══ -->
        <section class="sop-filter-section mb-4" aria-label="Bilah Penyaringan Proses APQC">
            <HcisFilterBar
                :search="searchQuery"
                search-placeholder="Cari nomor atau nama proses APQC… (/)"
                :quick-chips="quickChips"
                :active-chip-value="activeChip"
                :show-view-toggle="true"
                :view-mode="viewMode"
                variant="card"
                sentiment="brand"
                glow
                @update:search="onSearchChange"
                @update:view-mode="viewMode = $event"
                @chip-click="onChipClick"
                @reset="resetFilter"
            />
        </section>

        <!-- ══ 4. MAIN WORKSPACE CONTAINER ══ -->
        <HcisCard variant="glass" sentiment="brand" size="lg" class="sop-content-card mb-5">
            <template #header>
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div>
                        <h2 class="hcis-card__title">Daftar Proses Bisnis APQC</h2>
                        <p class="hcis-card__description">
                            Taksonomi proses bisnis terstandarisasi untuk pemetaan dokumen, alur workflow, dan audit kepatuhan
                        </p>
                    </div>
                    <HcisBadge
                        v-if="!loading"
                        :label="`${filteredList.length} Ditampilkan`"
                        variant="neutral"
                        appearance="subtle"
                        size="sm"
                    />
                </div>
            </template>

            <!-- State A: Error Result -->
            <HcisResultState
                v-if="errorState"
                sentiment="danger"
                size="md"
                title="Gagal Memuat Proses APQC"
                :description="errorMessage || 'Terjadi gangguan saat memuat data.'"
            >
                <template #actions>
                    <HcisButton variant="primary" size="md" leading-icon="arrow-clockwise" label="Coba Lagi" @click="fetchData(pagination.current_page)" />
                </template>
            </HcisResultState>

            <!-- State B: Loading Skeletons -->
            <div v-else-if="loading" class="p-3">
                <!-- Loading Grid Skeleton -->
                <div v-if="viewMode === 'grid'" class="row g-2 g-md-3">
                    <div v-for="n in 8" :key="`skel-grid-${n}`" class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <div class="sop-card-skel">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <HcisSkeleton width="36px" height="36px" rounded="md" />
                                <div class="flex-grow-1">
                                    <HcisSkeleton width="75%" height="1rem" rounded="sm" class="mb-1" />
                                    <HcisSkeleton width="50px" height="14px" rounded="pill" />
                                </div>
                            </div>
                            <HcisSkeleton width="90%" height="0.75rem" rounded="sm" class="mb-1" />
                            <HcisSkeleton width="60%" height="0.75rem" rounded="sm" class="mb-3" />
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <HcisSkeleton width="55px" height="14px" rounded="sm" />
                                <div class="d-flex gap-1">
                                    <HcisSkeleton width="36px" height="22px" rounded="md" />
                                    <HcisSkeleton width="36px" height="22px" rounded="md" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loading Table Skeleton -->
                <div v-else class="sop-table-skel p-2 bg-white rounded-3">
                    <div v-for="n in 5" :key="`skel-tbl-${n}`" class="d-flex align-items-center gap-3 py-2.5 border-bottom">
                        <HcisSkeleton width="38px" height="28px" rounded="md" />
                        <HcisSkeleton width="22%" height="1rem" rounded="sm" />
                        <HcisSkeleton width="30%" height="0.85rem" rounded="sm" />
                        <HcisSkeleton width="90px" height="22px" rounded="pill" />
                        <HcisSkeleton width="80px" height="26px" rounded="md" class="ms-auto" />
                    </div>
                </div>
            </div>

            <!-- State C: Populated Data -->
            <div v-else-if="filteredList.length > 0">
                <!-- Mode 1: Grid Matrix View -->
                <div v-if="viewMode === 'grid'" class="sop-grid-container">
                    <div class="row g-2 g-md-3 sop-stagger-grid">
                        <div
                            v-for="item in filteredList"
                            :key="item.Id_SOP_APQC"
                            class="col-12 col-sm-6 col-lg-4 col-xl-3"
                        >
                            <SopApqcCard
                                :item="item"
                                @click="openDetail(item)"
                                @edit="openModal(item)"
                                @delete="confirmHapus(item)"
                            />
                        </div>
                    </div>
                </div>

                <!-- Mode 2: Table List View -->
                <div v-else class="sop-table-wrap">
                    <HcisTable
                        :columns="tableColumns"
                        :rows="filteredList"
                        hoverable
                        effective-size="sm"
                        sentiment="brand"
                        @row-click="openDetail"
                    >
                        <!-- Custom Cell: Nama Proses & Kode -->
                        <template #cell-Nama_Proses="{ row }">
                            <div class="d-flex align-items-center gap-2.5 py-1 min-w-0" style="max-width: 100%;">
                                <span class="apqc-table-pill" :style="{ '--apqc-c': getProcessColor(row.Kode_Proses) }">
                                    {{ row.Kode_Proses }}
                                </span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-bold text-dark sop-line-clamp-2" :title="row.Nama_Proses" style="font-size: 0.875rem; line-height: 1.3;">
                                        {{ row.Nama_Proses }}
                                    </div>
                                    <div class="d-flex align-items-center gap-1.5 small mt-0.5" style="font-size: 0.72rem;">
                                        <span class="apqc-domain-badge" :class="isOperating(row.Kode_Proses) ? 'is-op' : 'is-sup'">
                                            {{ isOperating(row.Kode_Proses) ? 'Operasional' : 'Manajemen' }}
                                        </span>
                                        <span class="text-muted font-monospace">PCF #{{ row.Kode_Proses }}</span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Custom Cell: Tipe Item Sah -->
                        <template #cell-Tipe_Item="{ row }">
                            <div v-if="row.Tipe_Item && row.Tipe_Item.length" class="d-flex flex-wrap gap-1">
                                <HcisBadge v-for="t in row.Tipe_Item" :key="t" :label="t" variant="neutral" appearance="subtle" size="xs" />
                            </div>
                            <span v-else class="text-danger small">
                                <i class="bi bi-exclamation-triangle me-1"></i>belum ada tipe sah
                            </span>
                        </template>

                        <!-- Custom Cell: Deskripsi -->
                        <template #cell-Deskripsi="{ row }">
                            <div class="text-secondary sop-line-clamp-2" :title="row.Deskripsi || ''" style="font-size: 0.8125rem; line-height: 1.4;">
                                {{ row.Deskripsi || '—' }}
                            </div>
                        </template>

                        <!-- Custom Cell: Jumlah Dokumen -->
                        <template #cell-Jumlah_Dokumen="{ row }">
                            <HcisBadge
                                :label="`${row.Jumlah_Dokumen || 0} Dokumen`"
                                :variant="(row.Jumlah_Dokumen || 0) > 0 ? 'brand' : 'neutral'"
                                appearance="subtle"
                                size="xs"
                                :leading-icon="(row.Jumlah_Dokumen || 0) > 0 ? 'file-earmark-check-fill' : 'file-earmark'"
                            />
                        </template>

                        <!-- Custom Cell: Aksi -->
                        <template #cell-aksi="{ row }">
                            <div class="d-flex align-items-center justify-content-end gap-1">
                                <HcisButton
                                    variant="ghost"
                                    size="xs"
                                    label="Detail"
                                    leading-icon="eye"
                                    title="Lihat Detail Proses"
                                    aria-label="Lihat Detail Proses"
                                    @click.stop="openDetail(row)"
                                />
                                <HcisButton
                                    variant="ghost"
                                    size="xs"
                                    label="Ubah"
                                    leading-icon="pencil-square"
                                    title="Ubah Proses"
                                    aria-label="Ubah Proses"
                                    @click.stop="openModal(row)"
                                />
                                <HcisButton
                                    variant="danger-quiet"
                                    size="xs"
                                    label="Nonaktifkan"
                                    leading-icon="slash-circle"
                                    :disabled="row.Jumlah_Dokumen > 0"
                                    :title="row.Jumlah_Dokumen > 0 ? `Masih dipakai ${row.Jumlah_Dokumen} dokumen` : 'Nonaktifkan proses ini'"
                                    aria-label="Nonaktifkan Proses"
                                    @click.stop="confirmHapus(row)"
                                />
                            </div>
                        </template>
                    </HcisTable>
                </div>
            </div>

            <!-- State D: Empty Search Results -->
            <div v-else class="sop-empty-wrap">
                <HcisEmptyState
                    icon="bounding-box"
                    title="Proses APQC Tidak Ditemukan"
                    :description="searchQuery || activeChip !== 'ALL' ? `Tidak ada proses APQC yang cocok dengan filter '${searchQuery || activeChip}'.` : 'Belum ada data proses APQC.'"
                    variant="flat"
                    sentiment="neutral"
                >
                    <template #actions>
                        <HcisButton
                            v-if="searchQuery || activeChip !== 'ALL'"
                            variant="secondary"
                            size="md"
                            leading-icon="arrow-counterclockwise"
                            label="Reset Filter"
                            @click="resetFilter"
                        />
                        <HcisButton
                            v-else
                            variant="primary"
                            size="md"
                            leading-icon="plus-lg"
                            label="Tambah Proses Pertama"
                            @click="openModal()"
                        />
                    </template>
                </HcisEmptyState>
            </div>

            <!-- Pagination Footer -->
            <template v-if="pagination.total > 0 && !loading && !errorState" #footer>
                <div class="sop-pagination-dock">
                    <HcisPagination
                        :current-page="pagination.current_page"
                        :total="pagination.total"
                        :page-size="pagination.per_page"
                        :page-sizes="[12, 25, 50]"
                        :total-pages="pagination.total_pages"
                        :disabled="loading"
                        layout="total, sizes, prev, pager, next"
                        @update:current-page="fetchData"
                        @update:page-size="handlePageSizeChange"
                    />
                </div>
            </template>
        </HcisCard>

        <!-- ══ MODAL DETAIL PROSES APQC (READ-FIRST) ══ -->
        <SopApqcDetailDialog
            :show="showDetailModal"
            :item="itemForDetail"
            @close="closeDetail"
            @edit="handleDetailEdit"
            @delete="handleDetailDelete"
        />

        <!-- ══ MODAL TAMBAH / UBAH PROSES APQC ══ -->
        <SopApqcModal
            :show="showModal"
            :item="activeItem"
            :saving="saving"
            :tipe-opsi="tipeOpsi"
            @close="closeModal"
            @submit="handleSubmit"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import {
    HcisHeader,
    HcisCard,
    HcisButton,
    HcisBadge,
    HcisFilterBar,
    HcisTable,
    HcisPagination,
    HcisSkeleton,
    HcisEmptyState,
    HcisResultState,
    hcisConfirm,
    hcisMessage,
} from '@/components/ui';
import SopApqcCard from './Components/SopApqcCard.vue';
import SopApqcStats from './Components/SopApqcStats.vue';
import SopApqcModal from './Components/SopApqcModal.vue';
import SopApqcDetailDialog from './Components/SopApqcDetailDialog.vue';
import { sopApqcApi } from './api/sopApqcApi.js';

defineOptions({ name: 'SopApqcPage' });

const props = defineProps({
    tipeOpsi: { type: Array, default: () => [] },
});

// ── Main Page State ──
const viewMode = ref('grid');
const listData = ref([]);
const searchQuery = ref('');
const activeChip = ref('ALL');
const loading = ref(false);
const saving = ref(false);
const errorState = ref(false);
const errorMessage = ref('');

const showDetailModal = ref(false);
const itemForDetail = ref(null);
const showModal = ref(false);
const activeItem = ref(null);

const pagination = reactive({ current_page: 1, per_page: 12, total: 0, total_pages: 1 });

let searchTimeout = null;

// ── Quick Filter Chips ──
const quickChips = computed(() => [
    {
        id: 'ALL',
        value: 'ALL',
        label: 'Semua Proses',
        badge: statsSummary.value.total || 0,
        icon: 'grid-fill',
    },
    {
        id: 'OPERATING',
        value: 'OPERATING',
        label: 'Operasional Inti (01-06)',
        badge: operatingCount.value,
        icon: 'gear-fill',
    },
    {
        id: 'SUPPORT',
        value: 'SUPPORT',
        label: 'Manajemen & Support (07-13)',
        badge: supportCount.value,
        icon: 'building-fill',
    },
    {
        id: 'USED',
        value: 'USED',
        label: 'Sudah Terpakai',
        badge: statsSummary.value.used || 0,
        icon: 'check2-circle',
    },
    {
        id: 'UNUSED',
        value: 'UNUSED',
        label: 'Belum Terpakai',
        badge: statsSummary.value.unused || 0,
        icon: 'exclamation-circle',
    },
]);

const parseProcNum = (kode) => {
    const raw = String(kode || '').replace(/\D/g, '');
    return parseInt(raw, 10) || 0;
};

const isOperating = (kode) => {
    const num = parseProcNum(kode);
    return num >= 1 && num <= 6;
};

const operatingCount = computed(() => {
    return listData.value.filter((i) => isOperating(i.Kode_Proses)).length;
});

const supportCount = computed(() => {
    return listData.value.filter((i) => !isOperating(i.Kode_Proses)).length;
});

// ── Filtered List ──
const filteredList = computed(() => {
    let result = [...listData.value];

    if (activeChip.value === 'OPERATING') {
        result = result.filter((item) => isOperating(item.Kode_Proses));
    } else if (activeChip.value === 'SUPPORT') {
        result = result.filter((item) => !isOperating(item.Kode_Proses));
    } else if (activeChip.value === 'USED') {
        result = result.filter((item) => (item.Jumlah_Dokumen || 0) > 0);
    } else if (activeChip.value === 'UNUSED') {
        result = result.filter((item) => !item.Jumlah_Dokumen || item.Jumlah_Dokumen === 0);
    }

    return result;
});

// ── Telemetry Stats Summary ──
const statsSummary = computed(() => {
    const total = pagination.total || listData.value.length || 0;
    const used = listData.value.filter((i) => (i.Jumlah_Dokumen || 0) > 0).length;
    const unused = Math.max(0, total - used);
    const withTypesCount = listData.value.filter((i) => i.Tipe_Item && i.Tipe_Item.length > 0).length;
    const usageRate = total > 0 ? Math.round((used / total) * 100) : 0;

    return { total, used, unused, withTypesCount, usageRate };
});

// ── Table Columns Contract ──
const tableColumns = [
    { key: 'Nama_Proses', label: 'Proses & Domain APQC', sortable: true, width: '28%' },
    { key: 'Tipe_Item', label: 'Tipe Item Sah', width: '22%' },
    { key: 'Deskripsi', label: 'Deskripsi', width: '30%' },
    { key: 'Jumlah_Dokumen', label: 'Dokumen Terkait', align: 'center', width: '10%' },
    { key: 'aksi', label: 'Aksi', align: 'right', width: '10%' },
];

const getProcessColor = (kode) => {
    const num = parseProcNum(kode);
    const colors = {
        1: '#2563eb', // Strategy
        2: '#0284c7', // Products
        3: '#0ea5e9', // Sales
        4: '#06b6d4', // Supply Chain
        5: '#0d9488', // Services
        6: '#10b981', // Customer Service
        7: '#8b5cf6', // HR
        8: '#6366f1', // IT
        9: '#f59e0b', // Finance
        10: '#d97706', // Assets
        11: '#dc2626', // Risk & Compliance
        12: '#ec4899', // External
        13: '#7c3aed', // Capabilities
    };
    return colors[num] || '#0284c7';
};

// ── Data Fetching ──
const fetchData = async (page = 1) => {
    if (page < 1) return;
    loading.value = true;
    errorState.value = false;
    errorMessage.value = '';

    try {
        const res = await sopApqcApi.list({ page, limit: pagination.per_page, q: searchQuery.value });
        if (res.success) {
            listData.value = res.data || [];
            pagination.current_page = res.page || 1;
            pagination.total_pages = res.total_page || 1;
            pagination.total = res.total_data || 0;
        } else {
            listData.value = [];
            pagination.total = 0;
            pagination.total_pages = 1;
        }
    } catch (err) {
        errorState.value = true;
        errorMessage.value = err.message || 'Gagal terhubung ke server';
        hcisMessage.error(errorMessage.value);
    } finally {
        loading.value = false;
    }
};

const onSearchChange = (val) => {
    searchQuery.value = val;
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchData(1), 400);
};

const onChipClick = (chip) => {
    activeChip.value = chip.value || chip.id || 'ALL';
};

const resetFilter = () => {
    searchQuery.value = '';
    activeChip.value = 'ALL';
    fetchData(1);
};

const handlePageSizeChange = (newSize) => {
    pagination.per_page = newSize;
    fetchData(1);
};

// ── Detail Modal Actions (Read-First Pattern) ──
const openDetail = (item) => {
    itemForDetail.value = item ? { ...item } : null;
    showDetailModal.value = true;
};

const closeDetail = () => {
    showDetailModal.value = false;
    itemForDetail.value = null;
};

const handleDetailEdit = (item) => {
    closeDetail();
    openModal(item);
};

const handleDetailDelete = (item) => {
    closeDetail();
    confirmHapus(item);
};

// ── Modal Actions (Create / Edit) ──
const openModal = (item = null) => {
    activeItem.value = item ? { ...item } : null;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    activeItem.value = null;
};

const handleSubmit = async (payload) => {
    saving.value = true;
    try {
        const body = {
            Kode_Proses: payload.Kode_Proses,
            Nama_Proses: payload.Nama_Proses,
            Urutan: payload.Urutan,
            Deskripsi: payload.Deskripsi,
            Tipe_Item: payload.Tipe_Item,
        };

        const res = payload.isEditing
            ? await sopApqcApi.update(payload.Id, body)
            : await sopApqcApi.store(body);

        if (res.success) {
            hcisMessage.success(res.message);
            closeModal();
            fetchData(payload.isEditing ? pagination.current_page : 1);
        }
    } catch (err) {
        hcisMessage.error(err.message || 'Gagal menyimpan proses APQC');
    } finally {
        saving.value = false;
    }
};

const confirmHapus = async (item) => {
    const ok = await hcisConfirm({
        title: 'Nonaktifkan proses APQC?',
        description: `"${item.Nama_Proses}" tidak akan lagi bisa dipilih untuk dokumen baru. Dokumen yang sudah memakainya tidak berubah.`,
        confirmText: 'Nonaktifkan',
        sentiment: 'danger',
    });
    if (!ok) return;

    try {
        const res = await sopApqcApi.destroy(item.Id_SOP_APQC);
        if (res.success) {
            hcisMessage.success(res.message);
            fetchData(pagination.current_page);
        }
    } catch (err) {
        hcisMessage.error(err.message || 'Gagal menonaktifkan proses APQC');
    }
};

const tipeOpsi = computed(() => props.tipeOpsi);

onMounted(() => fetchData(1));
</script>

<style scoped>
.sop-apqc-page {
    width: 100%;
}

/* Skeleton Loading Card */
.sop-card-skel {
    padding: 1.25rem 1.35rem;
    border-radius: var(--hcis-radius-xl, 16px);
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.sop-stagger-grid {
    animation: sopFadeIn 0.3s ease-out;
}

@keyframes sopFadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Table styles */
.apqc-table-pill {
    flex: 0 0 auto;
    display: inline-grid;
    place-items: center;
    min-width: 2.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 0.4rem;
    font-size: 0.725rem;
    font-weight: 800;
    font-family: var(--hcis-font-mono, monospace);
    color: #fff;
    background: linear-gradient(135deg, var(--apqc-c, var(--hcis-sky-600)) 0%, color-mix(in srgb, var(--apqc-c, var(--hcis-sky-600)) 80%, #000) 100%);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.apqc-domain-badge {
    font-size: 0.68rem;
    font-weight: 700;
    border-radius: 4px;
    padding: 0.05rem 0.35rem;
}

.apqc-domain-badge.is-op {
    color: var(--hcis-sky-700);
    background: var(--hcis-sky-100);
}

.apqc-domain-badge.is-sup {
    color: var(--hcis-violet-700);
    background: var(--hcis-purple-100);
}

.sop-line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: normal;
}

.sop-table-wrap {
    background: #fff;
    border-radius: var(--hcis-radius-lg, 12px);
    border: 1px solid var(--hcis-slate-200);
    overflow: hidden;
}

.sop-pagination-dock {
    display: flex;
    justify-content: center;
    width: 100%;
    padding: 0.25rem 0;
}
</style>
