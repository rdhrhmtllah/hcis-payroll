<template>
    <div class="pa-comp-view">
        <!-- Filter Bar Component -->
        <HcisFilterBar
            :search="search"
            :search-placeholder="isMobile ? 'Cari kompetensi...' : 'Cari kode (mis. C01, M02), nama kompetensi, atau deskripsi...'"
            :search-debounce="200"
            :quick-chips="quickChips"
            :active-chip-value="activeFilter"
            :filters="filterBarDefinitions"
            :filter-values="filterBarValues"
            :show-view-toggle="true"
            :view-mode="viewMode"
            variant="card"
            sentiment="brand"
            size="sm"
            glow
            class="mb-3"
            :results-count="filteredRows.length"
            @update:search="search = $event"
            @chip-click="(c) => activeFilter = (c?.value ?? c)"
            @filter-change="onFilterChange"
            @update:view-mode="viewMode = $event"
            @reset="resetFilters"
        />

        <!-- Error State -->
        <HcisResultState
            v-if="error"
            status="danger"
            variant="glass"
            layout="compact"
            title="Gagal memuat daftar kompetensi"
            :description="error"
            :show-retry="false"
        />

        <!-- Loading Skeletons -->
        <div v-else-if="loading && !rows.length" class="pa-comp-loading">
            <div v-if="viewMode === 'grid'" class="pa-comp-skeleton-grid">
                <div v-for="n in 6" :key="`comp-skel-${n}`" class="pa-comp-skeleton-card">
                    <div class="pa-comp-skel-top">
                        <HcisSkeleton width="60px" height="1.25rem" rounded="sm" />
                        <HcisSkeleton width="50px" height="1.25rem" rounded="pill" />
                    </div>
                    <HcisSkeleton width="80%" height="1.35rem" rounded="sm" />
                    <div class="pa-comp-skel-badges">
                        <HcisSkeleton width="110px" height="1.3rem" rounded="pill" />
                    </div>
                    <HcisSkeleton width="100%" height="3.2rem" rounded="sm" />
                    <div class="pa-comp-skel-footer">
                        <HcisSkeleton width="60px" height="1.5rem" rounded="sm" />
                        <HcisSkeleton width="100px" height="1.5rem" rounded="sm" />
                    </div>
                </div>
            </div>
            <div v-else class="pa-comp-skeleton-table">
                <div v-for="n in 5" :key="`table-skel-${n}`" class="pa-comp-skel-row">
                    <HcisSkeleton width="28px" height="1rem" rounded="sm" />
                    <HcisSkeleton width="60px" height="1.25rem" rounded="sm" />
                    <HcisSkeleton width="55%" height="1.2rem" rounded="sm" />
                    <HcisSkeleton width="120px" height="1.25rem" rounded="pill" />
                    <HcisSkeleton width="60px" height="1.25rem" rounded="pill" />
                    <HcisSkeleton width="80px" height="1.6rem" rounded="sm" />
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
            title="Belum Ada Data Kompetensi"
            description="Belum ada data kompetensi yang terdaftar di dalam sistem. Tambahkan data kompetensi pertama untuk menyusun indikator penilaian."
            icon="bullseye"
            class="pa-comp-empty-state"
        >
            <template #actions>
                <HcisButton
                    label="Tambah Kompetensi Pertama"
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
            title="Tidak Ada Kompetensi yang Cocok"
            :description="search ? `Tidak ditemukan kompetensi dengan kata kunci '${search}'.` : 'Tidak ditemukan kompetensi yang sesuai dengan kombinasi penyaring yang aktif.'"
            icon="search"
            class="pa-comp-empty-state"
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
        <div v-else class="pa-comp-content">
            <!-- Mode 1: HcisCard Grid -->
            <div v-if="viewMode === 'grid'" class="pa-comp-grid">
                <HcisCard
                    v-for="item in filteredRows"
                    :key="`comp-card-${item.Id_PA_Competency}`"
                    variant="default"
                    :sentiment="getDimensionTheme(item.PA_Competency_Type_Id)"
                    interactive
                    class="pa-comp-card"
                    :class="{ 'is-inactive': item.Flag_Aktif === 'T' }"
                    @click="$emit('detail', item)"
                >
                    <template #header>
                        <div class="pa-comp-card__header-top">
                            <div class="pa-comp-card__codes">
                                <span class="pa-comp-code-badge">{{ item.Kode_Competency }}</span>
                                <span v-if="item.Urutan_Tampil" class="pa-comp-order-pill">#{{ item.Urutan_Tampil }}</span>
                            </div>
                            <HcisStatusBadge
                                :status="item.Flag_Aktif === 'Y' ? 'ACTIVE' : 'INACTIVE'"
                                :label="item.Flag_Aktif === 'Y' ? (isMobile ? 'Aktif' : 'Active') : (isMobile ? 'Nonaktif' : 'Inactive')"
                                size="xs"
                            />
                        </div>
                        <h3 class="pa-comp-card__title">{{ item.Nama_Competency }}</h3>
                    </template>

                    <div class="pa-comp-card__body">
                        <!-- Badges Row -->
                        <div class="pa-comp-card__badges">
                            <!-- Dimension Badge -->
                            <HcisBadge
                                :label="getDimensionName(item.PA_Competency_Type_Id)"
                                :variant="getDimensionTheme(item.PA_Competency_Type_Id)"
                                appearance="subtle"
                                size="xs"
                            >
                                <template #leading>
                                    <HcisIcon name="diagram-2" size="2xs" />
                                </template>
                            </HcisBadge>

                            <!-- Sumbu radar -->
                            <HcisBadge
                                v-if="diRadar(item)"
                                :label="isMobile ? 'Radar' : 'Sumbu radar'"
                                variant="purple"
                                appearance="subtle"
                                size="xs"
                            >
                                <template #leading>
                                    <HcisIcon name="bullseye" size="2xs" />
                                </template>
                            </HcisBadge>

                            <!-- KKM -->
                            <HcisBadge
                                v-if="punyaAmbang(item)"
                                :label="`KKM ${formatAmbang(item.Nilai_Ambang)}`"
                                variant="success"
                                appearance="subtle"
                                size="xs"
                            >
                                <template #leading>
                                    <HcisIcon name="flag" size="2xs" />
                                </template>
                            </HcisBadge>
                            <HcisBadge
                                v-else-if="item.Flag_Berskor !== 'T' && diRadar(item)"
                                :label="isMobile ? 'Tanpa KKM' : 'KKM belum ditetapkan'"
                                variant="warning"
                                appearance="subtle"
                                size="xs"
                            >
                                <template #leading>
                                    <HcisIcon name="flag" size="2xs" />
                                </template>
                            </HcisBadge>
                        </div>

                        <!-- Description -->
                        <p class="pa-comp-card__desc">
                            {{ item.Deskripsi || 'Definisi dan indikator perilaku untuk kompetensi ini belum dicatat.' }}
                        </p>
                    </div>

                    <!-- Footer Actions -->
                    <template #footer>
                        <!-- Tiga aksi utama sama lebar (teks tidak terpotong); aktif/nonaktif jadi ikon di ujung. -->
                        <div class="pa-comp-card__footer" :class="{ 'has-toggle': canDeactivate }" @click.stop>
                            <HcisButton
                                label="Detail"
                                variant="secondary"
                                size="xs"
                                leading-icon="info-circle"
                                block
                                data-uji="detail-kompetensi"
                                @click="$emit('detail', item)"
                            />
                            <HcisButton
                                label="Tingkat"
                                variant="secondary"
                                size="xs"
                                leading-icon="bar-chart-steps"
                                block
                                data-uji="tingkat-kompetensi"
                                @click="$emit('tingkat', item)"
                            />
                            <HcisButton
                                label="Edit"
                                variant="secondary"
                                size="xs"
                                leading-icon="pencil"
                                block
                                data-uji="edit-kompetensi"
                                @click="$emit('edit', item)"
                            />
                            <HcisButton
                                v-if="canDeactivate"
                                icon-only
                                :icon="item.Flag_Aktif === 'Y' ? 'slash-circle' : 'check-circle'"
                                :variant="item.Flag_Aktif === 'Y' ? 'danger-quiet' : 'secondary'"
                                size="xs"
                                :title="item.Flag_Aktif === 'Y' ? 'Nonaktifkan kompetensi' : 'Aktifkan kompetensi'"
                                :aria-label="item.Flag_Aktif === 'Y' ? `Nonaktifkan ${item.Nama_Competency}` : `Aktifkan ${item.Nama_Competency}`"
                                data-uji="toggle-kompetensi"
                                @click="$emit('deactivate', item)"
                            />
                        </div>
                    </template>
                </HcisCard>
            </div>

            <!-- Mode 2: Clean Structured Table -->
            <div v-else class="pa-comp-table-card">
                <div class="pa-comp-table-wrap">
                    <table class="pa-comp-table">
                        <thead>
                            <tr>
                                <th scope="col" class="pa-th-order">#</th>
                                <th scope="col" class="pa-th-code">Kode</th>
                                <th scope="col" class="pa-th-name">Nama Kompetensi &amp; Indikator</th>
                                <th scope="col" class="pa-th-dim">Dimensi / Jenis</th>
                                <th scope="col" class="pa-th-kkm">KKM</th>
                                <th scope="col" class="pa-th-radar">Radar</th>
                                <th scope="col" class="pa-th-status">Status</th>
                                <th scope="col" class="pa-th-action">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in filteredRows"
                                :key="`comp-row-${item.Id_PA_Competency}`"
                                class="pa-comp-row"
                                :class="{ 'is-inactive': item.Flag_Aktif === 'T' }"
                            >
                                <td class="pa-td-order">
                                    <span class="pa-comp-order-pill">#{{ item.Urutan_Tampil || '—' }}</span>
                                </td>
                                <td class="pa-td-code">
                                    <span class="pa-comp-code-badge">{{ item.Kode_Competency }}</span>
                                </td>
                                <td class="pa-td-name">
                                    <div class="pa-comp-name-meta">
                                        <strong class="pa-comp-name-text">{{ item.Nama_Competency }}</strong>
                                        <p class="pa-comp-desc-text">
                                             {{ item.Deskripsi || 'Belum ada deskripsi / indikator perilaku.' }}
                                        </p>
                                    </div>
                                </td>
                                <td class="pa-td-dim">
                                    <HcisBadge
                                        :label="getDimensionName(item.PA_Competency_Type_Id)"
                                        :variant="getDimensionTheme(item.PA_Competency_Type_Id)"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                </td>
                                <td class="pa-td-kkm">
                                    <span v-if="punyaAmbang(item)" class="pa-comp-kkm-value">
                                        {{ formatAmbang(item.Nilai_Ambang) }}
                                    </span>
                                    <span v-else-if="item.Flag_Berskor === 'T'" class="pa-comp-kkm-none">—</span>
                                    <span v-else class="pa-comp-kkm-empty" title="Ambang nilai belum ditetapkan">
                                        Belum diatur
                                    </span>
                                </td>
                                <td class="pa-td-radar">
                                    <HcisIcon
                                        v-if="diRadar(item)"
                                        name="bullseye"
                                        size="xs"
                                        class="pa-comp-radar-on"
                                        label="Menjadi sumbu radar"
                                    />
                                    <span v-else class="pa-comp-kkm-none" title="Tidak menjadi sumbu radar">—</span>
                                </td>
                                <td class="pa-td-status">
                                    <HcisStatusBadge
                                        :status="item.Flag_Aktif === 'Y' ? 'ACTIVE' : 'INACTIVE'"
                                        :label="item.Flag_Aktif === 'Y' ? 'Active' : 'Inactive'"
                                        size="xs"
                                    />
                                </td>
                                <td class="pa-td-action">
                                    <div class="pa-comp-table-actions">
                                        <HcisButton
                                            variant="secondary"
                                            size="xs"
                                            icon-only
                                            icon="info-circle"
                                            title="Lihat detail kompetensi"
                                            @click="$emit('detail', item)"
                                        />
                                        <HcisButton
                                            variant="secondary"
                                            size="xs"
                                            icon-only
                                            icon="pencil"
                                            title="Edit data kompetensi"
                                            @click="$emit('edit', item)"
                                        />
                                        <HcisButton
                                            variant="secondary"
                                            size="xs"
                                            icon-only
                                            icon="bar-chart-steps"
                                            title="Tingkat & Expected Behaviour per tingkat"
                                            data-uji="tingkat-kompetensi"
                                            @click="$emit('tingkat', item)"
                                        />
                                        <HcisButton
                                            v-if="canDeactivate"
                                            :variant="item.Flag_Aktif === 'Y' ? 'danger-quiet' : 'secondary'"
                                            size="xs"
                                            icon-only
                                            :icon="item.Flag_Aktif === 'Y' ? 'slash-circle' : 'check-circle'"
                                            :title="item.Flag_Aktif === 'Y' ? 'Nonaktifkan kompetensi' : 'Aktifkan kembali kompetensi'"
                                            @click="$emit('deactivate', item)"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
} from '@/components/ui';

defineOptions({ name: 'PaKompetensiView' });

const props = defineProps({
    rows: { type: Array, default: () => [] },
    references: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    searchQuery: { type: String, default: '' },
    canDeactivate: { type: Boolean, default: true },
});

defineEmits(['edit', 'add', 'deactivate', 'detail', 'tingkat']);

const search = ref(props.searchQuery || '');
const activeFilter = ref('ALL'); // 'ALL' | 'ACTIVE' | 'INACTIVE' | 'RADAR' | 'NO_KKM'
const selectedDimension = ref('');
const viewMode = ref('grid'); // 'grid' | 'table'

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

// Helper dimension map
const dimensionMap = computed(() => {
    const map = new Map();
    const list = props.references?.jenis_kompetensi || [];
    for (const item of list) {
        map.set(item.Id_PA_Competency_Type, item);
    }
    return map;
});

const dimensionOptions = computed(() => {
    const list = props.references?.jenis_kompetensi || [];
    return list.map((dim) => {
        const count = props.rows.filter((r) => r.PA_Competency_Type_Id === dim.Id_PA_Competency_Type).length;
        return {
            id: dim.Id_PA_Competency_Type,
            kode: dim.Kode_Type,
            nama: dim.Nama_Type,
            count,
        };
    });
});

function getDimensionName(typeId) {
    const dim = dimensionMap.value.get(typeId);
    return dim?.Nama_Type || dim?.Kode_Type || `Dimensi #${typeId}`;
}

function getDimensionTheme(typeId) {
    const dim = dimensionMap.value.get(typeId);
    const code = String(dim?.Kode_Type || '').toUpperCase();
    if (code.includes('MANAJER')) return 'purple';
    if (code.includes('CORE') || code.includes('NILAI')) return 'success';
    if (code.includes('BEHAVIOR') || code.includes('PERILAKU')) return 'teal';
    if (code.includes('KUALITATIF') || code.includes('QUAL')) return 'brand';
    return 'neutral';
}

const activeCount = computed(() => props.rows.filter((r) => r.Flag_Aktif === 'Y').length);
const inactiveCount = computed(() => props.rows.filter((r) => r.Flag_Aktif === 'T').length);

function diRadar(row) {
    return (row?.Flag_Radar ?? 'Y') === 'Y';
}

function punyaAmbang(row) {
    return row?.Nilai_Ambang !== null && row?.Nilai_Ambang !== undefined && row?.Nilai_Ambang !== '';
}

function formatAmbang(nilai) {
    const n = Number(nilai);
    if (!Number.isFinite(n)) return '—';
    return n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

const radarCount = computed(() => props.rows.filter((r) => r.Flag_Aktif === 'Y' && diRadar(r)).length);

const tanpaAmbangCount = computed(
    () =>
        props.rows.filter(
            (r) => r.Flag_Aktif === 'Y' && r.Flag_Berskor !== 'T' && diRadar(r) && !punyaAmbang(r),
        ).length,
);

const quickChips = computed(() => {
    const chips = [
        { value: 'ALL', label: 'Semua', badge: props.rows.length, color: 'brand' },
        { value: 'ACTIVE', label: 'Aktif', badge: activeCount.value, color: 'success' },
        { value: 'RADAR', label: isMobile.value ? 'Radar' : 'Sumbu radar', badge: radarCount.value, color: 'purple' },
    ];
    if (tanpaAmbangCount.value > 0) {
        chips.push({
            value: 'NO_KKM',
            label: isMobile.value ? 'Tanpa KKM' : 'Belum ber-KKM',
            badge: tanpaAmbangCount.value,
            color: 'warning',
        });
    }
    if (inactiveCount.value > 0) {
        chips.push({ value: 'INACTIVE', label: 'Nonaktif', badge: inactiveCount.value, color: 'warning' });
    }
    return chips;
});

const filterBarDefinitions = computed(() => {
    const defs = [];
    if (dimensionOptions.value.length > 0) {
        defs.push({
            key: 'dimension',
            label: 'Dimensi',
            type: 'select',
            placeholder: 'Semua Dimensi',
            options: [
                { value: '', label: 'Semua Dimensi' },
                ...dimensionOptions.value.map((d) => ({
                    value: String(d.id),
                    label: `${d.nama} (${d.count})`,
                })),
            ],
        });
    }
    return defs;
});

const filterBarValues = computed(() => ({
    dimension: selectedDimension.value,
}));

function onFilterChange({ key, value }) {
    if (key === 'dimension') {
        selectedDimension.value = value || '';
    }
}

const filteredRows = computed(() => {
    let list = props.rows || [];

    // Filter by dimension
    if (selectedDimension.value) {
        list = list.filter((r) => String(r.PA_Competency_Type_Id) === selectedDimension.value);
    }

    // Filter by quick chips
    if (activeFilter.value === 'ACTIVE') {
        list = list.filter((r) => r.Flag_Aktif === 'Y');
    } else if (activeFilter.value === 'INACTIVE') {
        list = list.filter((r) => r.Flag_Aktif === 'T');
    } else if (activeFilter.value === 'RADAR') {
        list = list.filter((r) => r.Flag_Aktif === 'Y' && diRadar(r));
    } else if (activeFilter.value === 'NO_KKM') {
        list = list.filter(
            (r) => r.Flag_Aktif === 'Y' && r.Flag_Berskor !== 'T' && diRadar(r) && !punyaAmbang(r),
        );
    }

    // Search query filter
    const q = search.value.trim().toLowerCase();
    if (q) {
        list = list.filter((r) => {
            const code = String(r.Kode_Competency || '').toLowerCase();
            const name = String(r.Nama_Competency || '').toLowerCase();
            const desc = String(r.Deskripsi || '').toLowerCase();
            const dimName = String(getDimensionName(r.PA_Competency_Type_Id)).toLowerCase();
            return code.includes(q) || name.includes(q) || desc.includes(q) || dimName.includes(q);
        });
    }

    return list;
});

function resetFilters() {
    search.value = '';
    activeFilter.value = 'ALL';
    selectedDimension.value = '';
}
</script>

<style scoped>
.pa-comp-view {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

/* Loading Skeletons */
.pa-comp-loading {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-comp-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}

.pa-comp-skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1.25rem;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
}

.pa-comp-skel-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-comp-skel-badges {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-comp-skel-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.75rem;
    border-top: 1px dashed #e2e8f0;
    margin-top: auto;
}

.pa-comp-skeleton-table {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pa-comp-skel-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1.25rem;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.pa-comp-empty-state {
    margin: 1.5rem 0;
}

.pa-empty-actions-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Toolbar & Segment Switcher */
.pa-comp-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.2rem 0.25rem;
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
    flex-wrap: wrap;
}

.pa-comp-toolbar__count {
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

/* Cards Grid */
.pa-comp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.pa-comp-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    transition: transform 150ms ease, box-shadow 150ms ease;
}

.pa-comp-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
}

.pa-comp-card.is-inactive {
    opacity: 0.75;
    background: #f8fafc;
}

.pa-comp-card__header-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
    min-width: 0;
}

.pa-comp-card__codes {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.pa-comp-code-badge {
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

.pa-comp-order-pill {
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

.pa-comp-card__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.35;
    word-break: break-word;
}

.pa-comp-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    flex: 1;
    padding: 0.25rem 0;
    min-width: 0;
}

.pa-comp-card__badges {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    min-width: 0;
}

.pa-comp-card__desc {
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

.pa-comp-card__footer {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: center;
    gap: 0.4rem;
    width: 100%;
    padding-top: 0.65rem;
    border-top: 1px dashed #e2e8f0;
    min-width: 0;
}

.pa-comp-card__footer.has-toggle {
    grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
}

.pa-comp-card__footer :deep(.hcis-btn) {
    min-width: 0;
    justify-content: center;
    white-space: nowrap;
    padding-inline: 0.45rem;
    gap: 0.3rem;
}

/* Isi kartu meregang setinggi kartu, sehingga baris tombol sejajar di dasar semua kartu
   walau panjang deskripsinya berbeda. */
.pa-comp-card :deep(.hcis-card__body) {
    flex: 1 1 auto;
}

.pa-comp-card :deep(.hcis-card__footer) {
    margin-top: auto;
}

/* Kepala kartu berisi slot kustom (kode, status, judul). Di layar ≤640px HcisCard menyusun
   kepalanya sebagai grid ikon/judul/aksi, dan slot ini ikut tergeser — judul terdorong ke
   kanan sebaris dengan kode. Di sini kepalanya tetap kolom biasa. */
.pa-comp-card :deep(.hcis-card__header),
.pa-comp-card :deep(.hcis-card__header-main) {
    display: flex !important;
    width: 100%;
}

.pa-comp-card :deep(.hcis-card__title-wrap) {
    display: flex !important; /* HcisCard ponsel: display: contents → kode & judul berjajar ke samping */
    flex-direction: column;
    width: 100%;
}

/* Table View */
.pa-comp-table-card {
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #cbd5e1);
    border-radius: var(--hcis-radius-md, 12px);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-comp-table-wrap {
    overflow-x: auto;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.pa-comp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
    text-align: left;
    min-width: 650px;
}

.pa-comp-table thead th {
    padding: 0.65rem 0.75rem;
    background: #f8fafc;
    border-bottom: 2px solid var(--hcis-border-subtle, #e2e8f0);
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    white-space: nowrap;
}

.pa-th-order {
    width: 5%;
}

.pa-th-code {
    width: 10%;
}

.pa-th-name {
    width: 42%;
}

.pa-th-dim {
    width: 15%;
}

.pa-th-kkm {
    width: 10%;
}

.pa-th-radar {
    width: 6%;
    text-align: center;
}

.pa-th-status {
    width: 7%;
}

.pa-td-radar {
    text-align: center;
}

.pa-comp-kkm-value {
    font-size: 0.78rem;
    font-family: ui-monospace, monospace;
    font-weight: 750;
    color: #047857;
}

.pa-comp-kkm-empty {
    font-size: 0.7rem;
    font-weight: 650;
    color: #b45309;
    background: #fffbeb;
    border: 1px solid #fde68a;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    white-space: nowrap;
}

.pa-comp-kkm-none {
    color: #94a3b8;
}

.pa-comp-radar-on {
    color: #7c3aed;
}

.pa-th-action {
    width: 5%;
    text-align: right;
}

.pa-comp-row {
    border-bottom: 1px solid #f1f5f9;
    transition: background-color 100ms ease;
}

.pa-comp-row:hover {
    background: #f8faff;
}

.pa-comp-row.is-inactive {
    opacity: 0.75;
    background: #f8fafc;
}

.pa-comp-row td {
    padding: 0.65rem 0.75rem;
    vertical-align: middle;
}

.pa-comp-name-meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-comp-name-text {
    font-size: 0.85rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-comp-desc-text {
    margin: 0;
    font-size: 0.72rem;
    color: #64748b;
    line-height: 1.35;
}

.pa-comp-table-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
}

/* ════ RESPONSIVE BREAKPOINTS (Desktop, Tablet, Mobile) ════ */

/* Tablet Breakpoint (768px – 1023px) */
@media (min-width: 768px) and (max-width: 1023px) {
    .pa-comp-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.875rem;
    }
}

/* Mobile Breakpoint (< 768px) */
@media (max-width: 767px) {
    .pa-comp-view {
        gap: 0.75rem;
    }

    .pa-comp-toolbar {
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

    .pa-comp-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .pa-comp-card__title {
        font-size: 0.95rem;
    }

    .pa-comp-card__body {
        gap: 0.45rem;
    }

    .pa-comp-card__desc {
        -webkit-line-clamp: 2;
        font-size: 0.75rem;
        line-height: 1.45;
    }

    .pa-comp-card__footer {
        padding-top: 0.55rem;
        gap: 0.3rem;
    }
}

/* Extra Small Phones (< 420px) */
@media (max-width: 420px) {
    .pa-comp-code-badge {
        font-size: 0.68rem;
        padding: 0.1rem 0.35rem;
    }

    .pa-comp-order-pill {
        font-size: 0.65rem;
        padding: 0.08rem 0.28rem;
    }
}
</style>
