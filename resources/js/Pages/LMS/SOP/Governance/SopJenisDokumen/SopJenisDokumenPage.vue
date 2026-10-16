<template>
    <div class="container-fluid px-0 sop-scope sop-jenis-dokumen-page">
        <!-- ══ 1. EXECUTIVE HEADER ══ -->
        <HcisHeader
            title="Jenis Dokumen"
            kicker="Tata Kelola · Master Data"
            description="Tingkat hirarki mutu: Kebijakan → Prosedur → Instruksi Kerja → Formulir. Menentukan posisi lapis hirarki dokumen perusahaan."
            icon="diagram-3-fill"
            sentiment="brand"
            size="md"
            animated
            class="mb-4"
        >
            <template #badge>
                <HcisBadge v-if="!loading" :label="`${pagination.total} Jenis`" variant="brand" appearance="glass" size="sm" dot />
            </template>

            <template #actions>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton
                        variant="glass"
                        size="md"
                        leading-icon="arrow-clockwise"
                        :loading="loading"
                        label="Segarkan"
                        title="Segarkan data jenis dokumen"
                        aria-label="Segarkan data jenis dokumen"
                        @click="fetchData(pagination.current_page)"
                    />
                    <HcisButton
                        variant="secondary"
                        size="md"
                        leading-icon="arrow-down-up"
                        label="Susun Urutan"
                        title="Atur susunan urutan hirarki mutu"
                        aria-label="Atur susunan urutan hirarki mutu"
                        :disabled="loading || listData.length <= 1"
                        @click="openReorderModal()"
                    />
                    <HcisButton
                        variant="primary"
                        size="md"
                        leading-icon="plus-lg"
                        label="Tambah Jenis"
                        title="Tambah jenis dokumen baru"
                        aria-label="Tambah jenis dokumen baru"
                        @click="openModal()"
                    />
                </div>
            </template>
        </HcisHeader>

        <!-- ══ 2. MAIN PAGE NAVIGATION TABS (MASTER VS STRUKTUR HIRARKI) ══ -->
        <section class="sop-main-tabs-section mb-4" aria-label="Navigasi Halaman Jenis Dokumen">
            <HcisTabs
                v-model="activeMainTab"
                :tabs="mainPageTabs"
                variant="segmented"
                size="md"
                block
            />
        </section>

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- ══ TAB 1: DAFTAR MASTER JENIS DOKUMEN ══ -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-if="activeMainTab === 'master'" class="sop-tab-panel-master">
            <!-- 2.1. Executive Telemetry Stats -->
            <SopJenisDokumenStats
                :total="statsSummary.total"
                :used-count="statsSummary.used"
                :with-prefix="statsSummary.withPrefix"
                :usage-rate="statsSummary.usageRate"
                :loading="loading"
            />

            <!-- 2.2. Filter Bar with View Mode Toggle & Quick Chips -->
            <section class="sop-filter-section mb-4" aria-label="Bilah Penyaringan Jenis Dokumen">
                <HcisFilterBar
                    :search="searchQuery"
                    search-placeholder="Cari nama jenis, kode mesin, atau prefix cetak… (/)"
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

            <!-- 2.3. Main Workspace Container -->
            <HcisCard variant="glass" sentiment="brand" size="lg" class="sop-content-card mb-5">
                <template #header>
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <h2 class="hcis-card__title">Daftar Master Jenis Dokumen</h2>
                            <p class="hcis-card__description">
                                Master data klasifikasi jenis dokumen untuk pengaturan penomoran, workflow, dan hak akses
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
                    title="Gagal Memuat Jenis Dokumen"
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
                                    <HcisSkeleton width="38px" height="38px" rounded="md" />
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
                                :key="item.Id_SOP_Jenis_Dokumen"
                                class="col-12 col-sm-6 col-lg-4 col-xl-3"
                            >
                                <SopJenisDokumenCard
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
                            <!-- Custom Cell: Nama Jenis & Tingkat -->
                            <template #cell-Nama_Jenis="{ row }">
                                <div class="d-flex align-items-center gap-2.5 py-1 min-w-0" style="max-width: 100%;">
                                    <span class="jd-level-pill" :style="{ '--jc': getLevelColor(row.Level_Hirarki) }">
                                        L{{ row.Level_Hirarki }}
                                    </span>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="fw-bold text-dark sop-line-clamp-2" :title="row.Nama_Jenis" style="font-size: 0.875rem; line-height: 1.3;">
                                            {{ row.Nama_Jenis }}
                                        </div>
                                        <div class="d-flex align-items-center gap-1.5 small font-monospace mt-0.5" style="font-size: 0.72rem;">
                                            <span class="text-muted">{{ row.Kode_Jenis }}</span>
                                            <span v-if="row.Kode_Jenis_Dokumen" class="jd-kode-cetak" title="Prefix Cetak Nomor Dokumen">{{ row.Kode_Jenis_Dokumen }}</span>
                                            <span v-else class="jd-kode-kosong" title="Nomor dokumen tak bisa terbit sebelum kode ini diisi">belum ada prefix</span>
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
                                        title="Lihat Detail Jenis"
                                        aria-label="Lihat Detail Jenis"
                                        @click.stop="openDetail(row)"
                                    />
                                    <HcisButton
                                        variant="ghost"
                                        size="xs"
                                        label="Ubah"
                                        leading-icon="pencil-square"
                                        title="Ubah Jenis"
                                        aria-label="Ubah Jenis"
                                        @click.stop="openModal(row)"
                                    />
                                    <HcisButton
                                        variant="danger-quiet"
                                        size="xs"
                                        label="Nonaktifkan"
                                        leading-icon="slash-circle"
                                        :disabled="row.Jumlah_Dokumen > 0"
                                        :title="row.Jumlah_Dokumen > 0 ? `Masih dipakai ${row.Jumlah_Dokumen} dokumen` : 'Nonaktifkan jenis ini'"
                                        aria-label="Nonaktifkan Jenis"
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
                        icon="diagram-3"
                        title="Jenis Dokumen Tidak Ditemukan"
                        :description="searchQuery || activeChip !== 'ALL' ? `Tidak ada jenis dokumen yang cocok dengan filter '${searchQuery || activeChip}'.` : 'Belum ada data jenis dokumen.'"
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
                                label="Tambah Jenis Pertama"
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
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <!-- ══ TAB 2: STRUKTUR HIRARKI MUTU (PIRAMID DOKUMEN ISO INTERAKTIF) ══ -->
        <!-- ══════════════════════════════════════════════════════════════════════ -->
        <div v-else-if="activeMainTab === 'hirarki'" class="sop-tab-panel-hirarki mb-5">
            <SopHirarkiInteractivePyramid
                :tiers="hirarkiTiers"
                @view-detail="openDetail"
                @switch-to-master="handleSwitchToMaster"
            />
        </div>

        <!-- ══ MODAL DETAIL JENIS DOKUMEN (READ-FIRST) ══ -->
        <SopJenisDokumenDetailDialog
            :show="showDetailModal"
            :item="itemForDetail"
            @close="closeDetail"
            @edit="handleDetailEdit"
            @delete="handleDetailDelete"
        />

        <!-- ══ MODAL TAMBAH / UBAH JENIS DOKUMEN ══ -->
        <SopJenisDokumenModal
            :show="showModal"
            :item="activeItem"
            :suggested-level="suggestedLevel"
            :saving="saving"
            :tipe-opsi="tipeOpsi"
            @close="closeModal"
            @submit="handleSubmit"
        />

        <!-- ══ MODAL SUSUN URUTAN HIRARKI (METODE PENYUSUNAN) ══ -->
        <SopJenisDokumenReorderModal
            :show="showReorderModal"
            :list="listData"
            :saving="savingReorder"
            @close="closeReorderModal"
            @submit="handleReorderSubmit"
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
    HcisTabs,
    HcisFilterBar,
    HcisTable,
    HcisPagination,
    HcisSkeleton,
    HcisEmptyState,
    HcisResultState,
    hcisConfirm,
    hcisMessage,
} from '@/components/ui';
import SopJenisDokumenCard from './Components/SopJenisDokumenCard.vue';
import SopJenisDokumenStats from './Components/SopJenisDokumenStats.vue';
import SopHirarkiInteractivePyramid from './Components/SopHirarkiInteractivePyramid.vue';
import SopJenisDokumenModal from './Components/SopJenisDokumenModal.vue';
import SopJenisDokumenDetailDialog from './Components/SopJenisDokumenDetailDialog.vue';
import SopJenisDokumenReorderModal from './Components/SopJenisDokumenReorderModal.vue';
import { sopJenisDokumenApi } from './api/sopJenisDokumenApi.js';

defineOptions({ name: 'SopJenisDokumenPage' });

const props = defineProps({
    tipeOpsi: { type: Array, default: () => [] },
});

// ── Main Page State ──
const activeMainTab = ref('master');
const viewMode = ref('grid');
const listData = ref([]);
const searchQuery = ref('');
const activeChip = ref('ALL');
const loading = ref(false);
const saving = ref(false);
const savingReorder = ref(false);
const errorState = ref(false);
const errorMessage = ref('');

const showDetailModal = ref(false);
const itemForDetail = ref(null);
const showModal = ref(false);
const activeItem = ref(null);
const showReorderModal = ref(false);

const pagination = reactive({ current_page: 1, per_page: 12, total: 0, total_pages: 1 });

let searchTimeout = null;

// ── 2 Main Page Navigation Tabs ──
const mainPageTabs = computed(() => [
    {
        key: 'master',
        label: 'Daftar Master Jenis',
        icon: 'table',
        badge: pagination.total || listData.value.length || undefined,
    },
    {
        key: 'hirarki',
        label: 'Struktur Hirarki Mutu',
        icon: 'diagram-3-fill',
    },
]);

// ── Quick Filter Chips ──
const quickChips = computed(() => [
    {
        id: 'ALL',
        value: 'ALL',
        label: 'Semua Jenis',
        badge: statsSummary.value.total || 0,
        icon: 'grid-fill',
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
    {
        id: 'HAS_PREFIX',
        value: 'HAS_PREFIX',
        label: 'Lengkap Prefix',
        badge: statsSummary.value.withPrefix || 0,
        icon: 'hash',
    },
]);

// ── Filtered List ──
const filteredList = computed(() => {
    let result = [...listData.value];

    if (activeChip.value === 'USED') {
        result = result.filter((item) => (item.Jumlah_Dokumen || 0) > 0);
    } else if (activeChip.value === 'UNUSED') {
        result = result.filter((item) => !item.Jumlah_Dokumen || item.Jumlah_Dokumen === 0);
    } else if (activeChip.value === 'HAS_PREFIX') {
        result = result.filter((item) => !!item.Kode_Jenis_Dokumen && item.Kode_Jenis_Dokumen.trim().length > 0);
    }

    return result;
});

// ── Telemetry Stats Summary ──
const statsSummary = computed(() => {
    const total = pagination.total || listData.value.length || 0;
    const used = listData.value.filter((i) => (i.Jumlah_Dokumen || 0) > 0).length;
    const unused = Math.max(0, total - used);
    const withPrefix = listData.value.filter((i) => !!i.Kode_Jenis_Dokumen && i.Kode_Jenis_Dokumen.trim().length > 0).length;
    const usageRate = total > 0 ? Math.round((used / total) * 100) : 0;

    return { total, used, unused, withPrefix, usageRate };
});

// ── Table Columns Contract ──
const tableColumns = [
    { key: 'Nama_Jenis', label: 'Jenis & Tingkat', sortable: true, width: '28%' },
    { key: 'Tipe_Item', label: 'Tipe Item Sah', width: '22%' },
    { key: 'Deskripsi', label: 'Deskripsi', width: '30%' },
    { key: 'Jumlah_Dokumen', label: 'Dokumen Terkait', align: 'center', width: '10%' },
    { key: 'aksi', label: 'Aksi', align: 'right', width: '10%' },
];

const getLevelColor = (level) => {
    const colors = {
        1: '#2563eb',
        2: '#3b82f6',
        3: '#0ea5e9',
        4: '#06b6d4',
        5: '#14b8a6',
        6: '#10b981',
        7: '#6366f1',
    };
    return colors[level] || '#64748b';
};

// ── Hirarki Tiers Structure for Tab 2 ──
const hirarkiTiers = computed(() => {
    const defaultMeta = {
        1: { role: 'Menjawab APA & MENGAPA (Arah Strategis)', desc: 'Pernyataan arah kebijakan strategis dan komitmen manajemen puncak organisasi.', color: '#2563eb' },
        2: { role: 'Keputusan Formal & Ketetapan', desc: 'Surat keputusan dan ketetapan manajemen yang mengikat seluruh unit operasional.', color: '#3b82f6' },
        3: { role: 'Menjawab SIAPA, APA & KAPAN (Alur Bisnis)', desc: 'Alur kerja baku lintas divisi/fungsi yang menurunkan kebijakan menjadi proses bisnis terstruktur.', color: '#0ea5e9' },
        4: { role: 'Menjawab BAGAIMANA (Instruksi Lapangan)', desc: 'Instruksi langkah teknis rinci yang dijalankan pada satu posisi jabatan atau pengoperasian alat/mesin.', color: '#06b6d4' },
        5: { role: 'Kriteria Mutu & Spesifikasi Teknis', desc: 'Pedoman standar baku mutu, toleransi, dan parameter kualitas kerja.', color: '#14b8a6' },
        6: { role: 'Bukti Rekaman Pelaksanaan (Checklist & Form)', desc: 'Formulir, template checklist, dan lembar isian yang menjadi rekaman bukti pelaksanaan prosedur.', color: '#10b981' },
        7: { role: 'Panduan Referensi Menyeluruh (Manual)', desc: 'Pedoman manual referensi komprehensif sistem manajemen mutu perusahaan.', color: '#6366f1' },
    };

    const map = new Map();
    for (const r of listData.value) {
        const lvl = Number(r.Level_Hirarki);
        if (!map.has(lvl)) {
            const meta = defaultMeta[lvl] || { role: 'Tingkat Mutu', desc: 'Standar klasifikasi dokumen.', color: '#64748b' };
            map.set(lvl, {
                level: lvl,
                nama: r.Nama_Jenis,
                pertanyaan: meta.role,
                deskripsi: r.Deskripsi || meta.desc,
                color: meta.color,
                dokumen: 0,
                items: [],
            });
        }
        const t = map.get(lvl);
        t.dokumen += Number(r.Jumlah_Dokumen || 0);
        t.items.push(r);
        if (t.items.length > 1) {
            t.nama = t.items.map((it) => it.Nama_Jenis).join(' · ');
        }
    }

    return [...map.values()].sort((a, b) => a.level - b.level);
});

// ── Data Fetching ──
const fetchData = async (page = 1) => {
    if (page < 1) return;
    loading.value = true;
    errorState.value = false;
    errorMessage.value = '';

    try {
        const res = await sopJenisDokumenApi.list({ page, limit: pagination.per_page, q: searchQuery.value });
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

const handleSwitchToMaster = (level) => {
    activeMainTab.value = 'master';
    if (level) {
        const match = listData.value.find((i) => Number(i.Level_Hirarki) === Number(level));
        if (match) {
            searchQuery.value = match.Nama_Jenis;
            fetchData(1);
        }
    }
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

// ── Suggested Level for New Types ──
const suggestedLevel = computed(() => {
    if (!listData.value.length) return 1;
    const maxLvl = Math.max(...listData.value.map((i) => Number(i.Level_Hirarki) || 0));
    return maxLvl > 0 ? maxLvl + 1 : 1;
});

// ── Reorder Modal Actions (Penyusunan Hirarki) ──
const openReorderModal = () => {
    showReorderModal.value = true;
};

const closeReorderModal = () => {
    showReorderModal.value = false;
};

const handleReorderSubmit = async (ids) => {
    if (!ids || !ids.length) return;
    savingReorder.value = true;
    try {
        const res = await sopJenisDokumenApi.reorder(ids);
        if (res.success) {
            hcisMessage.success(res.message);
            closeReorderModal();
            fetchData(1);
        }
    } catch (err) {
        hcisMessage.error(err.message || 'Gagal memperbarui susunan urutan');
    } finally {
        savingReorder.value = false;
    }
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
            Kode_Jenis: payload.Kode_Jenis,
            Kode_Jenis_Dokumen: payload.Kode_Jenis_Dokumen,
            Nama_Jenis: payload.Nama_Jenis,
            Level_Hirarki: payload.Level_Hirarki,
            Urutan: payload.Urutan,
            Deskripsi: payload.Deskripsi,
            Tipe_Item: payload.Tipe_Item,
        };

        const res = payload.isEditing
            ? await sopJenisDokumenApi.update(payload.Id, body)
            : await sopJenisDokumenApi.store(body);

        if (res.success) {
            hcisMessage.success(res.message);
            closeModal();
            fetchData(payload.isEditing ? pagination.current_page : 1);
        }
    } catch (err) {
        hcisMessage.error(err.message || 'Gagal menyimpan jenis dokumen');
    } finally {
        saving.value = false;
    }
};

const confirmHapus = async (item) => {
    const ok = await hcisConfirm({
        title: 'Nonaktifkan jenis dokumen?',
        description: `"${item.Nama_Jenis}" tidak akan lagi bisa dipilih untuk dokumen baru. Dokumen yang sudah memakainya tidak berubah.`,
        confirmText: 'Nonaktifkan',
        sentiment: 'danger',
    });
    if (!ok) return;

    try {
        const res = await sopJenisDokumenApi.destroy(item.Id_SOP_Jenis_Dokumen);
        if (res.success) {
            hcisMessage.success(res.message);
            fetchData(pagination.current_page);
        }
    } catch (err) {
        hcisMessage.error(err.message || 'Gagal menonaktifkan jenis dokumen');
    }
};

const tipeOpsi = computed(() => props.tipeOpsi);

onMounted(() => fetchData(1));
</script>

<style scoped>
.sop-jenis-dokumen-page {
    width: 100%;
}

.sop-main-tabs-section {
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
.jd-level-pill {
    flex: 0 0 auto;
    display: inline-grid;
    place-items: center;
    min-width: 2rem;
    padding: 0.15rem 0.45rem;
    border-radius: 0.4rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(135deg, var(--jc, var(--hcis-blue-600)) 0%, color-mix(in srgb, var(--jc, var(--hcis-blue-600)) 80%, #000) 100%);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.jd-kode-cetak {
    display: inline-block;
    padding: 0.05rem 0.35rem;
    border-radius: 0.3rem;
    font-weight: 700;
    color: #fff;
    background: var(--hcis-slate-700);
}

.jd-kode-kosong {
    color: var(--hcis-warning, var(--hcis-amber-700));
    font-style: italic;
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
