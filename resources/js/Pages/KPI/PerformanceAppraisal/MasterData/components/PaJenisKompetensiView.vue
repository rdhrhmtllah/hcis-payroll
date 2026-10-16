<template>
    <div class="pa-type-view">
        <!-- Filter Bar Component -->
        <HcisFilterBar
            :search="search"
            :search-placeholder="isMobile ? 'Cari jenis kompetensi...' : 'Cari nama dimensi, kode tipe, atau deskripsi...'"
            :search-debounce="200"
            :quick-chips="quickChips"
            :active-chip-value="activeFilter"
            :show-view-toggle="true"
            :view-mode="viewMode"
            variant="card"
            sentiment="purple"
            size="sm"
            glow
            class="mb-3"
            :results-count="filteredRows.length"
            @update:search="search = $event"
            @chip-click="(c) => activeFilter = (c?.value ?? c)"
            @update:view-mode="viewMode = $event"
            @reset="resetFilters"
        />

        <!-- Error State -->
        <HcisResultState
            v-if="error"
            status="danger"
            variant="glass"
            layout="compact"
            title="Gagal memuat jenis kompetensi"
            :description="error"
            :show-retry="false"
        />

        <!-- Loading Skeletons -->
        <div v-else-if="loading && !rows.length" class="pa-type-loading">
            <div v-if="viewMode === 'grid'" class="pa-type-skeleton-grid">
                <div v-for="n in 4" :key="`type-skel-${n}`" class="pa-type-skeleton-card">
                    <div class="pa-type-skel-top">
                        <HcisSkeleton width="60px" height="1.25rem" rounded="sm" />
                        <HcisSkeleton width="50px" height="1.25rem" rounded="pill" />
                    </div>
                    <HcisSkeleton width="80%" height="1.35rem" rounded="sm" />
                    <div class="pa-type-skel-badges">
                        <HcisSkeleton width="110px" height="1.3rem" rounded="pill" />
                        <HcisSkeleton width="125px" height="1.3rem" rounded="pill" />
                    </div>
                    <HcisSkeleton width="100%" height="3.2rem" rounded="sm" />
                    <div class="pa-type-skel-footer">
                        <HcisSkeleton width="60px" height="1.5rem" rounded="sm" />
                        <HcisSkeleton width="100px" height="1.5rem" rounded="sm" />
                    </div>
                </div>
            </div>
            <div v-else class="pa-type-skeleton-table">
                <div v-for="n in 4" :key="`table-skel-${n}`" class="pa-type-skel-row">
                    <HcisSkeleton width="28px" height="1rem" rounded="sm" />
                    <HcisSkeleton width="36px" height="36px" rounded="md" />
                    <HcisSkeleton width="70px" height="1.25rem" rounded="sm" />
                    <HcisSkeleton width="35%" height="1.25rem" rounded="sm" />
                    <HcisSkeleton width="130px" height="1.25rem" rounded="pill" />
                    <HcisSkeleton width="90px" height="1.25rem" rounded="pill" />
                    <HcisSkeleton width="60px" height="1.25rem" rounded="pill" />
                    <HcisSkeleton width="90px" height="1.6rem" rounded="sm" />
                </div>
            </div>
        </div>

        <!-- Initial Empty State (When master has 0 records) -->
        <HcisEmptyState
            v-else-if="!rows.length"
            variant="card"
            sentiment="info"
            size="lg"
            animated
            title="Belum Ada Data Jenis Kompetensi"
            description="Belum ada master dimensi atau kelompok jenis kompetensi yang terdaftar. Tambahkan jenis kompetensi pertama untuk menyusun struktur penilaian kinerja."
            icon="diagram-2"
            class="pa-type-empty-state"
        >
            <template #actions>
                <HcisButton
                    label="Tambah Jenis Pertama"
                    variant="primary"
                    size="md"
                    leading-icon="plus-lg"
                    @click="$emit('add')"
                />
            </template>
        </HcisEmptyState>

        <!-- Filtered Empty State (Search / Filter yields 0 results) -->
        <HcisEmptyState
            v-else-if="!filteredRows.length"
            variant="card"
            sentiment="neutral"
            size="md"
            animated
            title="Tidak Ada Jenis yang Cocok"
            :description="search ? `Tidak ditemukan jenis kompetensi dengan kata kunci '${search}'.` : 'Tidak ditemukan dimensi kompetensi dengan status penyaring yang dipilih.'"
            icon="search"
            class="pa-type-empty-state"
        >
            <template #actions>
                <div class="pa-empty-actions-row">
                    <HcisButton
                        label="Reset Filter"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-counterclockwise"
                        @click="resetFilters"
                    />
                    <HcisButton
                        label="Tambah Data"
                        variant="primary"
                        size="sm"
                        leading-icon="plus-lg"
                        @click="$emit('add')"
                    />
                </div>
            </template>
        </HcisEmptyState>

        <!-- Main Content -->
        <div v-else class="pa-type-content">
            <!-- Mode 1: HcisCard Grid View -->
            <div v-if="viewMode === 'grid'" class="pa-type-grid">
                <HcisCard
                    v-for="item in filteredRows"
                    :key="`type-card-${item.Id_PA_Competency_Type}`"
                    variant="default"
                    :sentiment="getTypeTheme(item.Kode_Type)"
                    interactive
                    class="pa-type-card"
                    :class="{ 'is-inactive': item.Flag_Aktif === 'T' }"
                    @click="$emit('detail', item)"
                >
                    <!-- Card Header -->
                    <template #header>
                        <div class="pa-type-card__header-top">
                            <div class="pa-type-card__codes">
                                <span class="pa-type-code-badge">{{ item.Kode_Type }}</span>
                                <span v-if="item.Urutan_Tampil" class="pa-type-order-pill">#{{ item.Urutan_Tampil }}</span>
                            </div>
                            <HcisStatusBadge
                                :status="item.Flag_Aktif === 'Y' ? 'ACTIVE' : 'INACTIVE'"
                                :label="item.Flag_Aktif === 'Y' ? (isMobile ? 'Aktif' : 'Active') : (isMobile ? 'Nonaktif' : 'Inactive')"
                                size="xs"
                            />
                        </div>
                        <h3 class="pa-type-card__title">{{ item.Nama_Type || item.Kode_Type }}</h3>
                    </template>

                    <!-- Body Content -->
                    <div class="pa-type-card__body">
                        <!-- Badges Row -->
                        <div class="pa-type-card__badges">
                            <!-- Category Role Badge -->
                            <HcisBadge
                                :label="isMobile ? getTypeShortRole(item.Kode_Type) : getTypeCategoryRole(item.Kode_Type)"
                                :variant="getTypeTheme(item.Kode_Type)"
                                appearance="subtle"
                                size="xs"
                            >
                                <template #leading>
                                    <HcisIcon :name="getTypeIcon(item.Kode_Type)" size="2xs" />
                                </template>
                            </HcisBadge>

                            <!-- Evaluation Method Badge -->
                            <HcisBadge
                                :label="isQualitativeType(item.Kode_Type) ? (isMobile ? 'Kualitatif' : 'Qualitative (Esai)') : (isMobile ? 'Skala 1–5' : 'Scored (Skala 1–5)')"
                                :variant="isQualitativeType(item.Kode_Type) ? 'warning' : 'brand'"
                                appearance="subtle"
                                size="xs"
                            >
                                <template #leading>
                                    <HcisIcon
                                        :name="isQualitativeType(item.Kode_Type) ? 'chat-quote-fill' : 'speedometer2'"
                                        size="2xs"
                                    />
                                </template>
                            </HcisBadge>

                            <!-- Sub-Competency Count Badge -->
                            <HcisBadge
                                :label="`${getCompetenciesForType(item.Id_PA_Competency_Type).length} ${isMobile ? 'Butir' : 'Kompetensi'}`"
                                variant="neutral"
                                appearance="subtle"
                                size="xs"
                            >
                                <template #leading>
                                    <HcisIcon name="bullseye" size="2xs" />
                                </template>
                            </HcisBadge>
                        </div>

                        <!-- Description -->
                        <p class="pa-type-card__desc">
                            {{ item.Deskripsi || 'Dimensi kompetensi dasar dalam pembobotan penilaian kinerja 360 derajat.' }}
                        </p>

                        <!-- Associated Sub-Competencies Box -->
                        <div class="pa-type-sub-box">
                            <div class="pa-type-sub-box__head">
                                <div class="pa-type-sub-box__title">
                                    <HcisIcon name="bullseye" size="2xs" />
                                    <span>{{ isMobile ? 'Kompetensi' : 'Kompetensi Terdaftar' }} ({{ getCompetenciesForType(item.Id_PA_Competency_Type).length }})</span>
                                </div>
                                <a
                                    :href="`/performance-appraisal/master/kompetensi?jenis=${item.Id_PA_Competency_Type}`"
                                    class="pa-type-sub-box__link"
                                    @click.stop
                                >
                                    <span>{{ isMobile ? 'Kamus' : 'Buka Kamus' }}</span>
                                    <HcisIcon name="arrow-right" size="2xs" />
                                </a>
                            </div>

                            <!-- Sub-competencies preview pills -->
                            <div
                                v-if="getCompetenciesForType(item.Id_PA_Competency_Type).length"
                                class="pa-type-sub-pills"
                            >
                                <span
                                    v-for="comp in getCompetenciesForType(item.Id_PA_Competency_Type).slice(0, isMobile ? 3 : 4)"
                                    :key="comp.Id_PA_Competency"
                                    class="pa-type-sub-pill"
                                    :title="comp.Nama_Competency"
                                >
                                    {{ comp.Nama_Competency }}
                                </span>
                                <span
                                    v-if="getCompetenciesForType(item.Id_PA_Competency_Type).length > (isMobile ? 3 : 4)"
                                    class="pa-type-sub-pill pa-type-sub-pill--more"
                                >
                                    +{{ getCompetenciesForType(item.Id_PA_Competency_Type).length - (isMobile ? 3 : 4) }} {{ isMobile ? '' : 'lainnya' }}
                                </span>
                            </div>
                            <span v-else class="pa-type-sub-empty">
                                Belum ada kompetensi terdaftar di bawah jenis ini.
                            </span>
                        </div>
                    </div>

                    <!-- Card Action Footer -->
                    <template #footer>
                        <div class="pa-type-card__footer" @click.stop>
                            <HcisButton
                                label="Detail"
                                variant="secondary"
                                size="xs"
                                leading-icon="info-circle"
                                @click="$emit('detail', item)"
                            />
                            <div class="pa-type-card__right-actions">
                                <HcisButton
                                    label="Edit"
                                    variant="secondary"
                                    size="xs"
                                    leading-icon="pencil"
                                    @click="$emit('edit', item)"
                                />
                                <HcisButton
                                    v-if="canDeactivate"
                                    :label="item.Flag_Aktif === 'Y' ? (isMobile ? 'Nonaktif' : 'Nonaktifkan') : (isMobile ? 'Aktif' : 'Aktifkan')"
                                    :variant="item.Flag_Aktif === 'Y' ? 'danger-quiet' : 'secondary'"
                                    size="xs"
                                    :leading-icon="item.Flag_Aktif === 'Y' ? 'slash-circle' : 'check-circle'"
                                    @click="$emit('deactivate', item)"
                                />
                            </div>
                        </div>
                    </template>
                </HcisCard>
            </div>

            <!-- Mode 2: Dense Table View -->
            <div v-else class="pa-type-table-wrap">
                <HcisTable
                    :columns="tableColumns"
                    :rows="filteredRows"
                    row-key="Id_PA_Competency_Type"
                    responsive="scroll"
                    hoverable
                    interactive
                    variant="glass"
                    sentiment="purple"
                    @row-click="({ row }) => $emit('detail', row)"
                >
                    <template #cell-urutan="{ row }">
                        <span class="pa-table-order-badge">#{{ row.Urutan_Tampil || '—' }}</span>
                    </template>

                    <template #cell-nama_type="{ row }">
                        <div class="pa-table-identity-cell">
                            <div class="pa-table-icon-wrap" :class="`pa-type-icon--${getTypeTheme(row.Kode_Type)}`">
                                <HcisIcon :name="getTypeIcon(row.Kode_Type)" size="xs" />
                            </div>
                            <div class="pa-table-name-wrap">
                                <strong>{{ row.Nama_Type || row.Kode_Type }}</strong>
                                <span class="pa-type-code-badge pa-type-code-badge--sm">{{ row.Kode_Type }}</span>
                            </div>
                        </div>
                    </template>

                    <template #cell-metode_evaluasi="{ row }">
                        <HcisBadge
                            :label="isQualitativeType(row.Kode_Type) ? 'Qualitative' : 'Scored (1–5)'"
                            :variant="isQualitativeType(row.Kode_Type) ? 'warning' : 'brand'"
                            appearance="subtle"
                            size="xs"
                        >
                            <template #leading>
                                <HcisIcon
                                    :name="isQualitativeType(row.Kode_Type) ? 'chat-quote-fill' : 'speedometer2'"
                                    size="2xs"
                                />
                            </template>
                        </HcisBadge>
                    </template>

                    <template #cell-kompetensi_terdaftar="{ row }">
                        <div class="pa-table-comp-cell">
                            <span class="pa-comp-count-pill">
                                {{ getCompetenciesForType(row.Id_PA_Competency_Type).length }} Butir
                            </span>
                            <small class="pa-comp-preview-text" :title="getCompetenciesPreviewText(row.Id_PA_Competency_Type)">
                                {{ getCompetenciesPreviewText(row.Id_PA_Competency_Type) }}
                            </small>
                        </div>
                    </template>

                    <template #cell-deskripsi="{ row }">
                        <span class="pa-table-desc-text" :title="row.Deskripsi">
                            {{ row.Deskripsi || '—' }}
                        </span>
                    </template>

                    <template #cell-status="{ row }">
                        <HcisStatusBadge
                            :status="row.Flag_Aktif === 'Y' ? 'ACTIVE' : 'INACTIVE'"
                            :label="row.Flag_Aktif === 'Y' ? 'Active' : 'Inactive'"
                            size="xs"
                        />
                    </template>

                    <template #cell-aksi="{ row }">
                        <div class="pa-table-actions" @click.stop>
                            <HcisButton
                                variant="secondary"
                                size="xs"
                                label="Detail"
                                leading-icon="info-circle"
                                @click="$emit('detail', row)"
                            />
                            <HcisButton
                                variant="secondary"
                                size="xs"
                                label="Edit"
                                leading-icon="pencil"
                                @click="$emit('edit', row)"
                            />
                            <HcisButton
                                v-if="canDeactivate"
                                :variant="row.Flag_Aktif === 'Y' ? 'danger-quiet' : 'secondary'"
                                size="xs"
                                :label="row.Flag_Aktif === 'Y' ? 'Nonaktif' : 'Aktif'"
                                :leading-icon="row.Flag_Aktif === 'Y' ? 'slash-circle' : 'check-circle'"
                                @click="$emit('deactivate', row)"
                            />
                        </div>
                    </template>
                </HcisTable>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisEmptyState,
    HcisFilterBar,
    HcisIcon,
    HcisResultState,
    HcisSkeleton,
    HcisStatusBadge,
    HcisTable,
} from '@/components/ui';

defineOptions({ name: 'PaJenisKompetensiView' });

const props = defineProps({
    rows: { type: Array, default: () => [] },
    references: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    searchQuery: { type: String, default: '' },
    canDeactivate: { type: Boolean, default: true },
});

defineEmits(['edit', 'add', 'detail', 'deactivate']);

const search = ref(props.searchQuery || '');
const activeFilter = ref('ALL');
const viewMode = ref('grid');

// Responsive Breakpoint Detection
const isMobile = ref(false);
const isTablet = ref(false);

function updateBreakpoints() {
    if (typeof window === 'undefined') return;
    const w = window.innerWidth;
    isMobile.value = w < 768;
    isTablet.value = w >= 768 && w < 1024;
}

onMounted(() => {
    updateBreakpoints();
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', updateBreakpoints, { passive: true });
        // Mobile defaults to Grid view
        if (window.innerWidth < 768) {
            viewMode.value = 'grid';
        }
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', updateBreakpoints);
    }
});

const activeCount = computed(() => props.rows.filter((r) => r.Flag_Aktif === 'Y').length);
const inactiveCount = computed(() => props.rows.filter((r) => r.Flag_Aktif === 'T').length);
const scoredCount = computed(() => props.rows.filter((r) => !isQualitativeType(r.Kode_Type)).length);
const qualitativeCount = computed(() => props.rows.filter((r) => isQualitativeType(r.Kode_Type)).length);

const quickChips = computed(() => {
    const chips = [
        { value: 'ALL', label: 'Semua', shortLabel: 'Semua', badge: props.rows.length, color: 'brand' },
        { value: 'ACTIVE', label: 'Aktif', shortLabel: 'Aktif', badge: activeCount.value, color: 'success' },
    ];
    if (inactiveCount.value > 0) {
        chips.push({ value: 'INACTIVE', label: 'Nonaktif', shortLabel: 'Nonaktif', badge: inactiveCount.value, color: 'warning' });
    }
    chips.push(
        { value: 'SCORED', label: isMobile.value ? 'Scored' : 'Scored (Skala 1–5)', badge: scoredCount.value, icon: 'speedometer2', color: 'purple' },
        { value: 'QUALITATIVE', label: isMobile.value ? 'Kualitatif' : 'Qualitative (Esai)', badge: qualitativeCount.value, icon: 'chat-quote', color: 'warning' },
    );
    return chips;
});

const filteredRows = computed(() => {
    let list = props.rows;

    if (activeFilter.value === 'ACTIVE') {
        list = list.filter((r) => r.Flag_Aktif === 'Y');
    } else if (activeFilter.value === 'INACTIVE') {
        list = list.filter((r) => r.Flag_Aktif === 'T');
    } else if (activeFilter.value === 'SCORED') {
        list = list.filter((r) => !isQualitativeType(r.Kode_Type));
    } else if (activeFilter.value === 'QUALITATIVE') {
        list = list.filter((r) => isQualitativeType(r.Kode_Type));
    }

    if (search.value.trim()) {
        const q = search.value.toLowerCase().trim();
        list = list.filter((r) => {
            const nama = (r.Nama_Type || '').toLowerCase();
            const kode = (r.Kode_Type || '').toLowerCase();
            const desc = (r.Deskripsi || '').toLowerCase();
            return nama.includes(q) || kode.includes(q) || desc.includes(q);
        });
    }

    return list;
});

const tableColumns = [
    { key: 'urutan', label: '#', width: '52px', align: 'center' },
    { key: 'nama_type', label: 'Jenis Kompetensi & Kode', minWidth: '220px' },
    { key: 'metode_evaluasi', label: 'Metode Penilaian', width: '160px' },
    { key: 'kompetensi_terdaftar', label: 'Kamus Butir', minWidth: '200px' },
    { key: 'deskripsi', label: 'Deskripsi', minWidth: '220px' },
    { key: 'status', label: 'Status', width: '100px', align: 'center' },
    { key: 'aksi', label: 'Aksi', width: '180px', align: 'right' },
];

function isQualitativeType(kode) {
    const k = (kode || '').toUpperCase();
    return k === 'KUALITATIF' || k === 'QUALITATIVE' || k.includes('KUALI') || k.includes('ESSAY');
}

function getTypeTheme(kode) {
    const k = (kode || '').toUpperCase();
    if (k.includes('MANAJERIAL') || k.includes('LEADERSHIP')) return 'purple';
    if (k.includes('CORE') || k.includes('VALUE')) return 'brand';
    if (k.includes('BEHAVIOR') || k.includes('PERILAKU')) return 'teal';
    if (k.includes('KUALI') || k.includes('QUALITATIVE')) return 'warning';
    return 'neutral';
}

function getTypeIcon(kode) {
    const k = (kode || '').toUpperCase();
    if (k.includes('MANAJERIAL') || k.includes('LEADERSHIP')) return 'diagram-3-fill';
    if (k.includes('CORE') || k.includes('VALUE')) return 'award-fill';
    if (k.includes('BEHAVIOR') || k.includes('PERILAKU')) return 'person-check-fill';
    if (k.includes('KUALI') || k.includes('QUALITATIVE')) return 'chat-quote-fill';
    return 'diagram-2-fill';
}

function getTypeCategoryRole(kode) {
    const k = (kode || '').toUpperCase();
    if (k.includes('MANAJERIAL')) return 'Kepemimpinan & Tim';
    if (k.includes('CORE')) return 'Nilai Budaya Perusahaan';
    if (k.includes('BEHAVIOR')) return 'Perilaku Kerja Nyata';
    if (k.includes('KUALI')) return 'Ulasan Esai & Catatan';
    return 'Dimensi Kompetensi Khusus';
}

function getTypeShortRole(kode) {
    const k = (kode || '').toUpperCase();
    if (k.includes('MANAJERIAL') || k.includes('LEADERSHIP')) return 'Manajerial';
    if (k.includes('CORE') || k.includes('VALUE')) return 'Core Value';
    if (k.includes('BEHAVIOR') || k.includes('PERILAKU')) return 'Perilaku';
    if (k.includes('KUALI') || k.includes('QUALITATIVE')) return 'Kualitatif';
    return 'Spesifik';
}

function getCompetenciesForType(typeId) {
    if (!typeId || !props.references.kompetensi) return [];
    return props.references.kompetensi.filter((c) => Number(c.PA_Competency_Type_Id) === Number(typeId));
}

function getCompetenciesPreviewText(typeId) {
    const comps = getCompetenciesForType(typeId);
    if (!comps.length) return 'Belum ada kompetensi terdaftar';
    const names = comps.slice(0, 3).map((c) => c.Nama_Competency).join(', ');
    return comps.length > 3 ? `${names}, +${comps.length - 3} lainnya` : names;
}

function resetFilters() {
    search.value = '';
    activeFilter.value = 'ALL';
}
</script>

<style scoped>
.pa-type-view {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

/* Toolbar & Segment Switcher */
.pa-type-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.2rem 0.25rem;
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
    flex-wrap: wrap;
}

.pa-type-toolbar__count {
    font-size: 0.78rem;
}

.pa-view-switch-pills {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 8px);
    gap: 0.2rem;
}

.pa-view-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border: none;
    background: transparent;
    border-radius: 6px;
    font-size: 0.74rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-view-pill:hover {
    color: #0f172a;
}

.pa-view-pill.is-active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

/* Card Grid Layout */
.pa-type-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.pa-type-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    transition: transform 150ms ease, box-shadow 150ms ease;
}

.pa-type-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
}

.pa-type-card.is-inactive {
    opacity: 0.75;
    background: #f8fafc;
}

.pa-type-card__header-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
    min-width: 0;
}

.pa-type-card__codes {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.pa-type-code-badge {
    font-size: 0.74rem;
    font-family: ui-monospace, monospace;
    font-weight: 750;
    color: #4f46e5;
    background: #eef2ff;
    padding: 0.12rem 0.45rem;
    border-radius: 4px;
    border: 1px solid #c7d2fe;
    white-space: nowrap;
}

.pa-type-code-badge--sm {
    font-size: 0.68rem;
    padding: 0.08rem 0.35rem;
}

.pa-type-order-pill {
    font-size: 0.68rem;
    font-family: ui-monospace, monospace;
    font-weight: 700;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    white-space: nowrap;
}

.pa-type-card__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.35;
    word-break: break-word;
}

.pa-type-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    flex: 1;
    padding: 0.25rem 0;
    min-width: 0;
}

.pa-type-card__badges {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    min-width: 0;
}

.pa-type-card__desc {
    margin: 0;
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}

/* Sub-Competencies Box */
.pa-type-sub-box {
    margin-top: auto;
    padding: 0.55rem 0.7rem;
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.8);
    min-width: 0;
}

.pa-type-sub-box__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
    min-width: 0;
}

.pa-type-sub-box__title {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--hcis-text-primary, #0f172a);
    font-size: 0.73rem;
    font-weight: 650;
}

.pa-type-sub-box__link {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    color: #4f46e5;
    font-size: 0.72rem;
    font-weight: 650;
    text-decoration: none;
    transition: color 0.15s ease;
    white-space: nowrap;
}

.pa-type-sub-box__link:hover {
    color: #3730a3;
    text-decoration: underline;
}

.pa-type-sub-pills {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
    min-width: 0;
}

.pa-type-sub-pill {
    padding: 0.12rem 0.4rem;
    border-radius: var(--hcis-radius-sm, 5px);
    background: #ffffff;
    border: 1px solid rgba(203, 213, 225, 0.8);
    color: var(--hcis-text-secondary, #334155);
    font-size: 0.7rem;
    white-space: nowrap;
    max-width: 140px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-type-sub-pill--more {
    background: rgba(79, 70, 229, 0.08);
    border-color: rgba(79, 70, 229, 0.2);
    color: #4338ca;
    font-weight: 650;
}

.pa-type-sub-empty {
    display: block;
    color: var(--hcis-text-muted, #94a3b8);
    font-size: 0.72rem;
    font-style: italic;
}

/* Card Action Footer */
.pa-type-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 0.65rem;
    border-top: 1px dashed #e2e8f0;
    margin-top: auto;
    min-width: 0;
}

.pa-type-card__right-actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

/* Table View Styles */
.pa-type-table-wrap {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    border-radius: var(--hcis-radius-lg, 12px);
    overflow: hidden;
}

.pa-table-order-badge {
    display: inline-block;
    padding: 0.1rem 0.4rem;
    border-radius: var(--hcis-radius-xs, 4px);
    background: rgba(100, 116, 139, 0.1);
    color: var(--hcis-text-secondary, #475569);
    font-size: 0.72rem;
    font-weight: 700;
}

.pa-table-identity-cell {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.pa-table-icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-md, 8px);
    flex-shrink: 0;
}

.pa-type-icon--purple {
    color: #7c3aed;
    background: rgba(124, 58, 237, 0.12);
}

.pa-type-icon--brand {
    color: #4f46e5;
    background: rgba(79, 70, 229, 0.12);
}

.pa-type-icon--teal {
    color: #0d9488;
    background: rgba(13, 148, 136, 0.12);
}

.pa-type-icon--warning {
    color: #d97706;
    background: rgba(245, 158, 11, 0.12);
}

.pa-type-icon--neutral {
    color: #64748b;
    background: #f1f5f9;
}

.pa-table-name-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.pa-table-name-wrap strong {
    color: var(--hcis-text-primary, #0f172a);
    font-size: 0.84rem;
}

.pa-table-comp-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-comp-count-pill {
    display: inline-flex;
    padding: 0.1rem 0.45rem;
    border-radius: var(--hcis-radius-full, 9999px);
    background: rgba(79, 70, 229, 0.08);
    color: #4338ca;
    font-size: 0.72rem;
    font-weight: 600;
    width: fit-content;
}

.pa-comp-preview-text {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-table-desc-text {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    color: var(--hcis-text-secondary, #475569);
    font-size: 0.76rem;
    line-height: 1.4;
}

.pa-table-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.3rem;
}

/* Skeletons */
.pa-type-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}

.pa-type-skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1.25rem;
    border-radius: var(--hcis-radius-xl, 16px);
    background: #ffffff;
    border: 1px solid #e2e8f0;
}

.pa-type-skel-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-type-skel-badges {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-type-skel-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.75rem;
    border-top: 1px dashed #e2e8f0;
    margin-top: auto;
}

.pa-type-skeleton-table {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pa-type-skel-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1.25rem;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.pa-type-empty-state {
    margin: 1.5rem 0;
}

.pa-empty-actions-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* ════ RESPONSIVE BREAKPOINTS (Desktop, Tablet, Mobile) ════ */

/* Tablet Breakpoint (768px – 1023px) */
@media (min-width: 768px) and (max-width: 1023px) {
    .pa-type-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.875rem;
    }
}

/* Mobile Breakpoint (< 768px) */
@media (max-width: 767px) {
    .pa-type-view {
        gap: 0.75rem;
    }

    .pa-type-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.4rem;
    }

    .pa-view-switch-pills {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .pa-view-pill {
        justify-content: center;
    }

    .pa-type-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .pa-type-card__title {
        font-size: 0.95rem;
    }

    .pa-type-card__body {
        gap: 0.45rem;
    }

    .pa-type-card__desc {
        -webkit-line-clamp: 2;
        font-size: 0.75rem;
        line-height: 1.45;
    }

    .pa-type-sub-box {
        padding: 0.45rem 0.6rem;
    }

    .pa-type-sub-pill {
        max-width: 110px;
        font-size: 0.68rem;
    }

    .pa-type-card__footer {
        padding-top: 0.55rem;
        gap: 0.35rem;
    }

    .pa-type-card__right-actions {
        gap: 0.25rem;
    }
}

/* Extra Small Phones (< 420px) */
@media (max-width: 420px) {
    .pa-type-code-badge {
        font-size: 0.68rem;
        padding: 0.1rem 0.35rem;
    }

    .pa-type-order-pill {
        font-size: 0.65rem;
        padding: 0.08rem 0.28rem;
    }
}
</style>
