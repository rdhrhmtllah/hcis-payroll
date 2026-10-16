<template>
    <div class="pa-pillar-view">
        <!-- Executive Distribution & Readiness Stats -->
        <HcisStatGroup :cols="4" gap="sm" :layout="isMobile ? 'dense' : 'compact'" class="pa-pillar-stats">
            <HcisStatCard
                :layout="isMobile ? 'dense' : 'compact'"
                label="Total Level Jabatan"
                :value="formatAngka(allLevelsList.length)"
                caption="Total jenjang level"
                icon="diagram-3-fill"
                sentiment="brand"
            />
            <HcisStatCard
                :layout="isMobile ? 'dense' : 'compact'"
                label="Level Terkonfigurasi"
                :value="`${formatAngka(configuredCount)} / ${formatAngka(allLevelsList.length)}`"
                caption="Siap dinilai pada cycle"
                icon="patch-check-fill"
                sentiment="success"
            />
            <HcisStatCard
                :layout="isMobile ? 'dense' : 'compact'"
                label="Belum Diatur"
                :value="formatAngka(unconfiguredCount)"
                :caption="unconfiguredCount > 0 ? 'Perlu konfigurasi bobot' : 'Semua level lengkap'"
                icon="exclamation-circle-fill"
                :sentiment="unconfiguredCount > 0 ? 'warning' : 'neutral'"
            />
            <HcisStatCard
                :layout="isMobile ? 'dense' : 'compact'"
                label="Rata-rata Proporsi"
                :value="`${avgKpi}% : ${avgKomp}%`"
                caption="Rata-rata KPI vs Kompetensi"
                icon="pie-chart-fill"
                sentiment="purple"
            />
        </HcisStatGroup>

        <!-- Unconfigured Alert Banner if any -->
        <div v-if="unconfiguredCount > 0" class="pa-pillar-alert-banner" role="alert">
            <div class="pa-pillar-alert-banner__body">
                <HcisIcon name="info-circle-fill" size="sm" color="warning" />
                <div>
                    <strong>Terdapat {{ unconfiguredCount }} level yang belum memiliki konfigurasi bobot KPI &amp; Kompetensi</strong>
                    <p>
                        Setiap level jabatan wajib memiliki pembagian bobot agar penilaian pada cycle baru dapat
                        dihitung tanpa galat.
                    </p>
                </div>
            </div>
            <HcisButton
                v-if="firstUnconfiguredLevel"
                label="Atur Level Pertama"
                variant="primary"
                size="xs"
                leading-icon="plus-lg"
                @click="$emit('add', firstUnconfiguredLevel)"
            />
        </div>

        <!-- Corporate Hierarchy Progression Curve Visualizer -->
        <section class="pa-pillar-ladder-card" aria-label="Tangga Distribusi Bobot Karir">
            <div class="pa-pillar-ladder-card__header">
                <div class="pa-pillar-ladder-card__title">
                    <span class="pa-eyebrow">TANGGA HIERARKI BOBOT KARIR</span>
                    <h4>Progresi Proporsi KPI vs Kompetensi Antar Level</h4>
                </div>
                <div class="pa-pillar-ladder-card__legend">
                    <div class="pa-legend-item">
                        <span class="pa-legend-dot pa-legend-dot--kpi" />
                        <span>Bobot KPI (Output)</span>
                    </div>
                    <div class="pa-legend-item">
                        <span class="pa-legend-dot pa-legend-dot--komp" />
                        <span>Bobot Kompetensi (Perilaku)</span>
                    </div>
                </div>
            </div>

            <div class="pa-ladder-bars-container">
                <div
                    v-for="item in sortedByHierarchyLevels"
                    :key="`ladder-bar-${item.ID_Level}`"
                    class="pa-ladder-item"
                    :class="{ 'pa-ladder-item--empty': !item.hasConfig }"
                    @click="item.hasConfig ? $emit('edit', item.rawRow, item) : $emit('add', item)"
                >
                    <div class="pa-ladder-item__meta">
                        <span class="pa-ladder-level-name" :title="item.Nama_Level">{{ item.Nama_Level }}</span>
                        <span class="pa-ladder-rank">Tk. {{ item.Level_Hierarchy || item.ID_Level }}</span>
                    </div>

                    <div class="pa-ladder-track">
                        <template v-if="item.hasConfig">
                            <div
                                class="pa-ladder-seg pa-ladder-seg--kpi"
                                :style="{ width: `${item.Bobot_KPI}%` }"
                                :title="`KPI ${item.Bobot_KPI}%`"
                            >
                                <span v-if="item.Bobot_KPI >= 20">{{ item.Bobot_KPI }}%</span>
                            </div>
                            <div
                                class="pa-ladder-seg pa-ladder-seg--komp"
                                :style="{ width: `${item.Bobot_Kompetensi}%` }"
                                :title="`Kompetensi ${item.Bobot_Kompetensi}%`"
                            >
                                <span v-if="item.Bobot_Kompetensi >= 20">{{ item.Bobot_Kompetensi }}%</span>
                            </div>
                        </template>
                        <div v-else class="pa-ladder-empty-track">
                            <span>Belum Diatur</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Standard Design System Filter Bar Component -->
        <HcisFilterBar
            :search="search"
            :search-placeholder="isMobile ? 'Cari level jabatan...' : 'Cari level jabatan / catatan...'"
            :quick-chips="statusQuickChips"
            :active-chip-value="activeSegmentTab"
            :results-count="filteredLevels.length"
            :show-view-toggle="true"
            :view-mode="viewMode"
            variant="card"
            sentiment="brand"
            size="sm"
            glow
            class="pa-pillar-filter-bar mb-3"
            @update:search="search = $event"
            @chip-click="activeSegmentTab = $event"
            @update:view-mode="viewMode = $event"
            @reset="resetFilters"
        />

        <!-- Loading Shimmer State -->
        <div v-if="loading" class="pa-pillar-loading">
            <div class="pa-pillar-skeleton-grid">
                <div v-for="n in 6" :key="`skel-pillar-${n}`" class="pa-pillar-skeleton-card">
                    <div class="pa-pillar-skeleton-card__head">
                        <HcisSkeleton width="36px" height="36px" rounded="md" />
                        <div>
                            <HcisSkeleton width="130px" height="0.9rem" rounded="sm" />
                            <HcisSkeleton width="80px" height="0.65rem" rounded="pill" />
                        </div>
                    </div>
                    <HcisSkeleton width="100%" height="1.75rem" rounded="md" />
                    <div class="pa-pillar-skeleton-card__footer">
                        <HcisSkeleton width="100px" height="0.7rem" rounded="sm" />
                        <HcisSkeleton width="80px" height="1.6rem" rounded="md" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Initial Empty State (When no level data exists) -->
        <HcisEmptyState
            v-else-if="!allLevelsList.length"
            variant="card"
            sentiment="warning"
            size="lg"
            animated
            title="Belum Ada Level Jabatan"
            description="Master level jabatan belum memiliki data untuk penataan bobot KPI dan Kompetensi."
            icon="layers-fill"
            class="pa-pillar-empty-state"
        />

        <!-- Filtered Empty State (Search / Segment filter yields 0 results) -->
        <HcisEmptyState
            v-else-if="!filteredLevels.length"
            variant="card"
            sentiment="neutral"
            size="md"
            animated
            title="Tidak Ada Level yang Cocok"
            :description="(search || searchQuery) ? `Tidak ditemukan level jabatan dengan kata kunci '${search || searchQuery}'.` : 'Tidak ditemukan level jabatan yang sesuai dengan filter saat ini.'"
            icon="search"
            class="pa-pillar-empty-state"
        >
            <template #actions>
                <HcisButton
                    label="Reset Filter"
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-counterclockwise"
                    @click="resetFilters"
                />
            </template>
        </HcisEmptyState>

        <!-- Loaded Content -->
        <div v-else class="pa-pillar-content">
            <!-- Mode 1: Grid Cards -->
            <div v-if="viewMode === 'grid'" class="pa-pillar-grid">
                <article
                    v-for="item in filteredLevels"
                    :key="`level-card-${item.ID_Level}`"
                    class="pa-pillar-card"
                    :class="{
                        'pa-pillar-card--configured': item.hasConfig,
                        'pa-pillar-card--unconfigured': !item.hasConfig,
                    }"
                >
                    <!-- Card Header -->
                    <div class="pa-pillar-card__header">
                        <div class="pa-pillar-card__identity">
                            <div class="pa-pillar-card__icon" :class="{ configured: item.hasConfig }">
                                <HcisIcon :name="item.hasConfig ? 'pie-chart-fill' : 'diagram-3-fill'" size="sm" />
                            </div>
                            <div class="pa-pillar-card__heading">
                                <div class="pa-pillar-card__meta-tags">
                                    <span class="pa-pillar-level-pill">Level {{ item.ID_Level }}</span>
                                    <span v-if="item.Level_Hierarchy" class="pa-pillar-rank-pill">Hierarki {{ item.Level_Hierarchy }}</span>
                                </div>
                                <h3 class="pa-pillar-card__title" :title="item.Nama_Level">{{ item.Nama_Level }}</h3>
                            </div>
                        </div>

                        <div class="pa-pillar-card__badge-wrap">
                            <HcisBadge
                                v-if="item.hasConfig"
                                label="100% Pas"
                                variant="success"
                                appearance="subtle"
                                size="xs"
                                dot
                            />
                            <HcisBadge
                                v-else
                                label="Belum Diatur"
                                variant="warning"
                                appearance="subtle"
                                size="xs"
                                dot
                            />
                        </div>
                    </div>

                    <!-- Dual Split Bar Visualizer -->
                    <div class="pa-pillar-card__visual">
                        <div v-if="item.hasConfig" class="pa-pillar-card__track">
                            <div
                                class="pa-pillar-card__bar pa-pillar-card__bar--kpi"
                                :style="{ width: `${item.Bobot_KPI}%` }"
                                :title="`Bobot KPI: ${item.Bobot_KPI}%`"
                            >
                                <span v-if="item.Bobot_KPI >= 15">{{ item.Bobot_KPI }}%</span>
                            </div>
                            <div
                                class="pa-pillar-card__bar pa-pillar-card__bar--komp"
                                :style="{ width: `${item.Bobot_Kompetensi}%` }"
                                :title="`Bobot Kompetensi: ${item.Bobot_Kompetensi}%`"
                            >
                                <span v-if="item.Bobot_Kompetensi >= 15">{{ item.Bobot_Kompetensi }}%</span>
                            </div>
                        </div>
                        <div v-else class="pa-pillar-card__unconfigured-track">
                            <HcisIcon name="exclamation-circle" size="xs" />
                            <span>Bobot belum diatur (0% KPI / 0% Komp)</span>
                        </div>

                        <!-- Metrics Legend Pills -->
                        <div v-if="item.hasConfig" class="pa-pillar-card__metrics">
                            <div class="pa-pillar-metric-chip pa-pillar-metric-chip--kpi">
                                <span class="pa-pillar-metric-dot pa-pillar-metric-dot--kpi"></span>
                                <span class="pa-pillar-metric-label">KPI</span>
                                <strong>{{ item.Bobot_KPI }}%</strong>
                            </div>
                            <div class="pa-pillar-metric-chip pa-pillar-metric-chip--komp">
                                <span class="pa-pillar-metric-dot pa-pillar-metric-dot--komp"></span>
                                <span class="pa-pillar-metric-label">Kompetensi</span>
                                <strong>{{ item.Bobot_Kompetensi }}%</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Preset Stepper Buttons -->
                    <div class="pa-pillar-card__quick-presets">
                        <div class="pa-quick-header">
                            <span class="pa-quick-label">Preset Cepat</span>
                            <span class="pa-quick-sublabel">KPI / Komp</span>
                        </div>
                        <div class="pa-quick-chips-grid">
                            <button
                                v-for="preset in quickPresets"
                                :key="`preset-${item.ID_Level}-${preset.kpi}`"
                                type="button"
                                class="pa-card-preset-btn"
                                :class="{ active: item.hasConfig && item.Bobot_KPI === preset.kpi }"
                                :title="`Terapkan ${preset.kpi}% KPI / ${preset.komp}% Kompetensi`"
                                @click="applyQuickPresetToLevel(item, preset.kpi, preset.komp)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Notes Snippet if any -->
                    <div v-if="item.Catatan" class="pa-pillar-card__note">
                        <HcisIcon name="chat-square-text" size="2xs" />
                        <p>{{ item.Catatan }}</p>
                    </div>

                    <!-- Action Footer -->
                    <div class="pa-pillar-card__footer">
                        <span class="pa-pillar-card__footer-hint" :class="{ 'pa-pillar-card__footer-hint--warning': !item.hasConfig }">
                            <template v-if="item.hasConfig">
                                <HcisIcon name="check2-circle" size="2xs" /> Total 100% Valid
                            </template>
                            <template v-else>
                                <HcisIcon name="exclamation-circle" size="2xs" /> Wajib dikonfigurasi
                            </template>
                        </span>

                        <div class="pa-pillar-card__actions">
                            <HcisButton
                                v-if="item.hasConfig"
                                variant="ghost"
                                size="xs"
                                label="Detail"
                                leading-icon="eye"
                                class="pa-pillar-card__btn"
                                @click="$emit('detail', item.rawRow, item)"
                            />
                            <HcisButton
                                :variant="item.hasConfig ? 'secondary' : 'primary'"
                                size="xs"
                                :label="item.hasConfig ? 'Ubah' : 'Atur Bobot'"
                                :leading-icon="item.hasConfig ? 'pencil-square' : 'plus-lg'"
                                class="pa-pillar-card__btn"
                                @click="item.hasConfig ? $emit('edit', item.rawRow, item) : $emit('add', item)"
                            />
                        </div>
                    </div>
                </article>
            </div>

            <!-- Mode 2: Matrix Table -->
            <div v-else class="pa-pillar-table-wrap">
                <HcisTable
                    :columns="tableColumns"
                    :rows="filteredLevels"
                    row-key="ID_Level"
                    responsive="scroll"
                    hoverable
                    variant="glass"
                    sentiment="brand"
                    @row-click="({ row }) => (row.hasConfig ? $emit('detail', row.rawRow, row) : $emit('add', row))"
                >
                    <template #cell-Nama_Level="{ row }">
                        <div class="pa-table-level-cell">
                            <strong>{{ row.Nama_Level }}</strong>
                            <small>Level {{ row.ID_Level }}</small>
                        </div>
                    </template>

                    <template #cell-Level_Hierarchy="{ row }">
                        <span class="pa-mono-text">{{ row.Level_Hierarchy || '—' }}</span>
                    </template>

                    <template #cell-Proporsi="{ row }">
                        <div v-if="row.hasConfig" class="pa-table-proporsi-cell">
                            <div class="pa-table-mini-track">
                                <div
                                    class="pa-table-mini-bar pa-table-mini-bar--kpi"
                                    :style="{ width: `${row.Bobot_KPI}%` }"
                                    :title="`KPI ${row.Bobot_KPI}%`"
                                />
                                <div
                                    class="pa-table-mini-bar pa-table-mini-bar--komp"
                                    :style="{ width: `${row.Bobot_Kompetensi}%` }"
                                    :title="`Kompetensi ${row.Bobot_Kompetensi}%`"
                                />
                            </div>
                            <div class="pa-table-proporsi-labels">
                                <span class="pa-kpi-text">KPI {{ row.Bobot_KPI }}%</span>
                                <span class="pa-komp-text">Komp {{ row.Bobot_Kompetensi }}%</span>
                            </div>
                        </div>
                        <span v-else class="pa-text-muted-italic">Belum diatur</span>
                    </template>

                    <template #cell-Catatan="{ row }">
                        <span class="pa-table-notes">{{ row.Catatan || '—' }}</span>
                    </template>

                    <template #cell-Status="{ row }">
                        <HcisStatusBadge
                            v-if="row.hasConfig"
                            status="ACTIVE"
                            label="Terkonfigurasi"
                            size="xs"
                        />
                        <HcisBadge
                            v-else
                            label="Belum Diatur"
                            variant="warning"
                            appearance="subtle"
                            size="xs"
                            dot
                        />
                    </template>

                    <template #cell-aksi="{ row }">
                        <div class="pa-row-actions">
                            <HcisButton
                                v-if="row.hasConfig"
                                variant="ghost"
                                size="xs"
                                label="Detail"
                                leading-icon="eye"
                                @click.stop="$emit('detail', row.rawRow, row)"
                            />
                            <HcisButton
                                :variant="row.hasConfig ? 'secondary' : 'primary'"
                                size="xs"
                                :label="row.hasConfig ? 'Ubah' : 'Atur'"
                                :leading-icon="row.hasConfig ? 'pencil-square' : 'plus-lg'"
                                @click.stop="row.hasConfig ? $emit('edit', row.rawRow, row) : $emit('add', row)"
                            />
                        </div>
                    </template>
                </HcisTable>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisEmptyState,
    HcisFilterBar,
    HcisIcon,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    HcisStatusBadge,
    HcisTable,
} from '@/components/ui';

defineOptions({ name: 'PaBobotPilarView' });

const props = defineProps({
    rows: { type: Array, default: () => [] },
    references: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    searchQuery: { type: String, default: '' },
    selectedLevelFilter: { type: [String, Number], default: '' },
});

const emit = defineEmits(['edit', 'add', 'detail', 'save-quick']);

const isMobile = ref(false);
const isTablet = ref(false);

function cekUkuranLayar() {
    if (typeof window === 'undefined') return;
    const w = window.innerWidth;
    isMobile.value = w < 768;
    isTablet.value = w >= 768 && w < 1024;
}

onMounted(() => {
    cekUkuranLayar();
    window.addEventListener('resize', cekUkuranLayar, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', cekUkuranLayar);
});

const viewMode = ref('grid');
const activeSegmentTab = ref('all');
const search = ref(props.searchQuery || '');

watch(() => props.searchQuery, (newVal) => {
    if (newVal !== undefined) {
        search.value = newVal || '';
    }
});

function resetFilters() {
    activeSegmentTab.value = 'all';
    search.value = '';
}

const quickPresets = [
    { label: '80/20', kpi: 80, komp: 20 },
    { label: '70/30', kpi: 70, komp: 30 },
    { label: '60/40', kpi: 60, komp: 40 },
    { label: '50/50', kpi: 50, komp: 50 },
];

const tableColumns = [
    { key: 'Nama_Level', label: 'Level Jabatan', minWidth: '180px' },
    { key: 'Level_Hierarchy', label: 'Hierarki', width: '90px' },
    { key: 'Proporsi', label: 'Proporsi KPI vs Kompetensi', minWidth: '220px' },
    { key: 'Catatan', label: 'Catatan', minWidth: '180px' },
    { key: 'Status', label: 'Status', width: '130px' },
    { key: 'aksi', label: 'Aksi', align: 'right', width: '150px' },
];

const allLevelsList = computed(() => {
    const refLevels = props.references?.level || [];
    if (!refLevels.length && props.rows.length) {
        return props.rows.map((row) => ({
            ID_Level: Number(row.ID_Level),
            Nama_Level: `Level ${row.ID_Level}`,
            Level_Hierarchy: null,
            Bobot_KPI: Number(row.Bobot_KPI ?? 0),
            Bobot_Kompetensi: Number(row.Bobot_Kompetensi ?? 0),
            Catatan: row.Catatan ?? '',
            hasConfig: true,
            rawRow: row,
        }));
    }

    return refLevels.map((lvl) => {
        const id = Number(lvl.ID_Level);
        const matchRow = props.rows.find((r) => Number(r.ID_Level) === id);
        return {
            ID_Level: id,
            Nama_Level: lvl.Nama_Level || `Level ${id}`,
            Level_Hierarchy: lvl.Level_Hierarchy || null,
            Bobot_KPI: matchRow ? Number(matchRow.Bobot_KPI ?? 0) : 0,
            Bobot_Kompetensi: matchRow ? Number(matchRow.Bobot_Kompetensi ?? 0) : 0,
            Catatan: matchRow?.Catatan || '',
            hasConfig: Boolean(matchRow),
            rawRow: matchRow || null,
        };
    });
});

const sortedByHierarchyLevels = computed(() => {
    return [...allLevelsList.value].sort((a, b) => {
        const rankA = Number(a.Level_Hierarchy) || Number(a.ID_Level) || 999;
        const rankB = Number(b.Level_Hierarchy) || Number(b.ID_Level) || 999;
        return rankA - rankB;
    });
});

const configuredCount = computed(() => allLevelsList.value.filter((l) => l.hasConfig).length);
const unconfiguredCount = computed(() => allLevelsList.value.filter((l) => !l.hasConfig).length);
const kpiDominantCount = computed(() => allLevelsList.value.filter((l) => l.hasConfig && l.Bobot_KPI > 50).length);
const kompDominantCount = computed(() => allLevelsList.value.filter((l) => l.hasConfig && l.Bobot_Kompetensi > 50).length);
const balancedCount = computed(() => allLevelsList.value.filter((l) => l.hasConfig && l.Bobot_KPI === 50).length);

const statusQuickChips = computed(() => [
    {
        id: 'all',
        value: 'all',
        label: isMobile.value ? 'Semua' : 'Semua Level',
        badge: allLevelsList.value.length,
        icon: 'diagram-3-fill',
    },
    {
        id: 'configured',
        value: 'configured',
        label: isMobile.value ? 'Selesai' : 'Terkonfigurasi',
        badge: configuredCount.value,
        color: 'success',
        icon: 'patch-check-fill',
    },
    ...(unconfiguredCount.value > 0 ? [{
        id: 'unconfigured',
        value: 'unconfigured',
        label: isMobile.value ? 'Belum' : 'Belum Diatur',
        badge: unconfiguredCount.value,
        color: 'warning',
        icon: 'exclamation-circle-fill',
    }] : []),
    {
        id: 'kpi-dominant',
        value: 'kpi-dominant',
        label: isMobile.value ? 'KPI >50%' : 'Dominan KPI (>50%)',
        badge: kpiDominantCount.value,
        color: 'brand',
        icon: 'graph-up-arrow',
    },
    {
        id: 'komp-dominant',
        value: 'komp-dominant',
        label: isMobile.value ? 'Komp >50%' : 'Dominan Kompetensi',
        badge: kompDominantCount.value,
        color: 'purple',
        icon: 'bullseye',
    },
    {
        id: 'balanced',
        value: 'balanced',
        label: isMobile.value ? '50:50' : 'Seimbang (50:50)',
        badge: balancedCount.value,
        color: 'info',
        icon: 'pie-chart-fill',
    },
]);

const filteredLevels = computed(() => {
    let list = allLevelsList.value;

    if (activeSegmentTab.value === 'configured') {
        list = list.filter((item) => item.hasConfig);
    } else if (activeSegmentTab.value === 'unconfigured') {
        list = list.filter((item) => !item.hasConfig);
    } else if (activeSegmentTab.value === 'kpi-dominant') {
        list = list.filter((item) => item.hasConfig && item.Bobot_KPI > 50);
    } else if (activeSegmentTab.value === 'komp-dominant') {
        list = list.filter((item) => item.hasConfig && item.Bobot_Kompetensi > 50);
    } else if (activeSegmentTab.value === 'balanced') {
        list = list.filter((item) => item.hasConfig && item.Bobot_KPI === 50);
    }

    if (props.selectedLevelFilter) {
        const filterId = Number(props.selectedLevelFilter);
        list = list.filter((item) => item.ID_Level === filterId);
    }

    const q = (search.value || props.searchQuery || '').toLowerCase().trim();
    if (q) {
        list = list.filter(
            (item) =>
                item.Nama_Level.toLowerCase().includes(q) ||
                String(item.ID_Level).includes(q) ||
                String(item.Catatan || '').toLowerCase().includes(q),
        );
    }

    return list;
});

const firstUnconfiguredLevel = computed(() => allLevelsList.value.find((l) => !l.hasConfig) || null);

const avgKpi = computed(() => {
    const configured = allLevelsList.value.filter((l) => l.hasConfig);
    if (!configured.length) return 80;
    const sum = configured.reduce((acc, c) => acc + c.Bobot_KPI, 0);
    return Math.round(sum / configured.length);
});

const avgKomp = computed(() => 100 - avgKpi.value);

function applyQuickPresetToLevel(levelItem, kpi, komp) {
    emit('edit', {
        ...(levelItem.rawRow || {}),
        ID_Level: levelItem.ID_Level,
        Bobot_KPI: kpi,
        Bobot_Kompetensi: komp,
        Catatan: levelItem.Catatan || `Proporsi ${kpi}/${komp}`,
    }, levelItem);
}

function formatAngka(value) {
    return Number(value || 0).toLocaleString('id-ID');
}
</script>

<style scoped>
.pa-pillar-view {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    min-width: 0;
    width: 100%;
}

/* Cockpit Stat Cards: 4 Equal Columns Grid on Desktop & Tablet */
.pa-pillar-stats {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 0.75rem !important;
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    margin: 0 !important;
}

.pa-pillar-stats :deep(.hcis-stat),
.pa-pillar-stats .hcis-stat {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    height: 100% !important;
    box-sizing: border-box !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
}

.pa-pillar-stats :deep(.hcis-stat__compact-wrap) {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    min-width: 0;
}

.pa-pillar-alert-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1.1rem;
    border: 1px solid rgba(245, 158, 11, 0.35);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.65), rgba(255, 255, 255, 0.85));
}

.pa-pillar-alert-banner__body {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
}

.pa-pillar-alert-banner__body strong {
    display: block;
    color: #92400e;
    font-size: 0.82rem;
    margin-bottom: 0.15rem;
}

.pa-pillar-alert-banner__body p {
    margin: 0;
    color: #b45309;
    font-size: 0.74rem;
    line-height: 1.45;
}

/* Corporate Ladder Card */
.pa-pillar-ladder-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1.1rem;
    border: 1px solid rgba(139, 92, 246, 0.22);
    border-radius: var(--hcis-radius-xl, 16px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(245, 243, 255, 0.4));
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
}

.pa-pillar-ladder-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
}

.pa-pillar-ladder-card__title h4 {
    margin: 0.15rem 0 0;
    font-size: 0.94rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-pillar-ladder-card__legend {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.pa-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 650;
}

.pa-legend-dot {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: var(--hcis-radius-xs, 4px);
}

.pa-legend-dot--kpi {
    background: #4f46e5;
}

.pa-legend-dot--komp {
    background: #7c3aed;
}

.pa-ladder-bars-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 0.65rem;
}

.pa-ladder-item {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.65rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    cursor: pointer;
    transition: all 180ms ease;
}

.pa-ladder-item:hover {
    border-color: #7c3aed;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.pa-ladder-item--empty {
    border-style: dashed;
    border-color: rgba(245, 158, 11, 0.5);
    background: rgba(254, 243, 199, 0.3);
}

.pa-ladder-item__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.3rem;
}

.pa-ladder-level-name {
    font-size: 0.74rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-ladder-rank {
    font-size: 0.65rem;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-text-muted, #64748b);
}

.pa-ladder-track {
    display: flex;
    height: 1.35rem;
    border-radius: var(--hcis-radius-xs, 4px);
    overflow: hidden;
    background: #e2e8f0;
}

.pa-ladder-seg {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 0.65rem;
    font-weight: 850;
    font-family: var(--hcis-font-mono, monospace);
    transition: width 200ms ease;
}

.pa-ladder-seg--kpi {
    background: #4f46e5;
}

.pa-ladder-seg--komp {
    background: #7c3aed;
}

.pa-ladder-empty-track {
    display: grid;
    place-items: center;
    width: 100%;
    color: #b45309;
    font-size: 0.65rem;
    font-weight: 700;
}

/* Filter Bar Container */
.pa-pillar-filter-bar {
    width: 100%;
    min-width: 0;
}

/* Grid Cards */
.pa-pillar-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.95rem;
}

.pa-pillar-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xl, 14px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.85));
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
    min-width: 0;
    box-sizing: border-box;
}

.pa-pillar-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
    border-color: rgba(99, 102, 241, 0.35);
}

.pa-pillar-card--configured {
    border-left: 3.5px solid #10b981;
}

.pa-pillar-card--unconfigured {
    border-left: 3.5px solid #f59e0b;
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.25), rgba(255, 255, 255, 0.95));
}

.pa-pillar-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-pillar-card__identity {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    min-width: 0;
    flex: 1 1 auto;
}

.pa-pillar-card__icon {
    display: grid;
    place-items: center;
    width: 2.3rem;
    height: 2.3rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f1f5f9;
    color: #64748b;
    flex-shrink: 0;
}

.pa-pillar-card__icon.configured {
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
}

.pa-pillar-card__heading {
    min-width: 0;
    flex: 1 1 auto;
}

.pa-pillar-card__meta-tags {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.2rem;
    flex-wrap: wrap;
}

.pa-pillar-level-pill {
    font-size: 0.65rem;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 750;
    padding: 0.08rem 0.35rem;
    border-radius: var(--hcis-radius-xs, 4px);
    background: #f1f5f9;
    color: var(--hcis-text-strong, #334155);
    line-height: 1.2;
}

.pa-pillar-rank-pill {
    font-size: 0.65rem;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.2;
}

.pa-pillar-card__title {
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}

.pa-pillar-card__badge-wrap {
    flex-shrink: 0;
}

/* Card Visual Track */
.pa-pillar-card__visual {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pa-pillar-card__track {
    display: flex;
    height: 1.65rem;
    border-radius: var(--hcis-radius-md, 8px);
    overflow: hidden;
    background: #e2e8f0;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
}

.pa-pillar-card__bar {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 850;
    font-family: var(--hcis-font-mono, monospace);
    transition: width 200ms ease;
    overflow: hidden;
    white-space: nowrap;
}

.pa-pillar-card__bar--kpi {
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
}

.pa-pillar-card__bar--komp {
    background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
}

.pa-pillar-card__unconfigured-track {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    height: 1.65rem;
    border: 1px dashed rgba(245, 158, 11, 0.5);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(254, 243, 199, 0.35);
    color: #b45309;
    font-size: 0.7rem;
    font-weight: 650;
}

.pa-pillar-card__metrics {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.45rem;
    width: 100%;
}

.pa-pillar-metric-chip {
    display: flex;
    align-items: center;
    padding: 0.3rem 0.55rem;
    border-radius: var(--hcis-radius-sm, 6px);
    font-size: 0.72rem;
    box-sizing: border-box;
}

.pa-pillar-metric-chip--kpi {
    background: rgba(99, 102, 241, 0.08);
    color: #4338ca;
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.pa-pillar-metric-chip--komp {
    background: rgba(139, 92, 246, 0.08);
    color: #6d28d9;
    border: 1px solid rgba(139, 92, 246, 0.15);
}

.pa-pillar-metric-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-right: 0.35rem;
}

.pa-pillar-metric-dot--kpi {
    background: #4f46e5;
}

.pa-pillar-metric-dot--komp {
    background: #7c3aed;
}

.pa-pillar-metric-label {
    margin-right: auto;
    font-weight: 600;
}

.pa-pillar-metric-chip strong {
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 850;
    font-size: 0.76rem;
}

/* Quick Presets on Card */
.pa-pillar-card__quick-presets {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.45rem 0.55rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.85);
}

.pa-quick-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-quick-label {
    font-size: 0.65rem;
    font-weight: 750;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pa-quick-sublabel {
    font-size: 0.62rem;
    color: var(--hcis-text-subtle, #94a3b8);
    font-family: var(--hcis-font-mono, monospace);
}

.pa-quick-chips-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.35rem;
    width: 100%;
}

.pa-card-preset-btn {
    width: 100%;
    padding: 0.3rem 0.2rem;
    border: 1px solid rgba(203, 213, 225, 0.9);
    border-radius: var(--hcis-radius-xs, 6px);
    background: #ffffff;
    color: var(--hcis-text-strong, #334155);
    font-size: 0.7rem;
    font-weight: 800;
    font-family: var(--hcis-font-mono, monospace);
    cursor: pointer;
    transition: all 140ms ease;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    box-sizing: border-box;
}

.pa-card-preset-btn:hover {
    border-color: #4f46e5;
    background: #f5f3ff;
    color: #4338ca;
}

.pa-card-preset-btn.active {
    background: #4f46e5;
    border-color: #4f46e5;
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.3);
}

/* Card Notes */
.pa-pillar-card__note {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    padding: 0.45rem 0.6rem;
    border-radius: var(--hcis-radius-md, 6px);
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.8);
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
    word-break: break-word;
    overflow-wrap: anywhere;
}

.pa-pillar-card__note p {
    margin: 0;
    line-height: 1.35;
}

/* Card Footer */
.pa-pillar-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: auto;
    padding-top: 0.65rem;
    border-top: 1px solid rgba(226, 232, 240, 0.75);
}

.pa-pillar-card__footer-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: #10b981;
    font-size: 0.68rem;
    font-weight: 700;
}

.pa-pillar-card__footer-hint--warning {
    color: #d97706;
}

.pa-pillar-card__actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

/* Table View Styles */
.pa-pillar-table-wrap {
    width: 100%;
    min-width: 0;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xl, 16px);
    overflow: hidden;
    background: rgba(255, 255, 255, 0.8);
}

.pa-table-level-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-table-level-cell strong {
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.82rem;
}

.pa-table-level-cell small {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
}

.pa-mono-text {
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.78rem;
    color: var(--hcis-text-strong, #334155);
}

.pa-table-proporsi-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 12rem;
}

.pa-table-mini-track {
    display: flex;
    height: 0.6rem;
    border-radius: 999px;
    overflow: hidden;
    background: #e2e8f0;
}

.pa-table-mini-bar--kpi {
    background: #4f46e5;
}

.pa-table-mini-bar--komp {
    background: #7c3aed;
}

.pa-table-proporsi-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.68rem;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 700;
}

.pa-kpi-text {
    color: #4338ca;
}

.pa-komp-text {
    color: #6d28d9;
}

.pa-table-notes {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-row-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.3rem;
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

/* Skeletons */
.pa-pillar-loading {
    padding: 1rem 0;
}

.pa-pillar-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.9rem;
}

.pa-pillar-skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
}

.pa-pillar-skeleton-card__head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

@media (max-width: 1024px) {
    .pa-pillar-grid,
    .pa-pillar-skeleton-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .pa-pillar-view {
        gap: 0.85rem;
    }

    /* Single-row Horizontal Scroll for Cockpit Stat Cards */
    .pa-pillar-stats {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        gap: 0.5rem !important;
        padding: 2px 2px 0.35rem 2px !important;
        scrollbar-width: none !important;
        -webkit-overflow-scrolling: touch !important;
        scroll-snap-type: x mandatory !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .pa-pillar-stats::-webkit-scrollbar {
        display: none !important;
    }

    .pa-pillar-stats :deep(.hcis-stat),
    .pa-pillar-stats .hcis-stat {
        flex: 0 0 160px !important;
        min-width: 160px !important;
        max-width: 180px !important;
        scroll-snap-align: start !important;
        box-sizing: border-box !important;
    }

    .pa-pillar-grid,
    .pa-pillar-skeleton-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .pa-pillar-ladder-card {
        padding: 0.85rem;
    }

    .pa-pillar-ladder-card__header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .pa-ladder-bars-container {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        gap: 0.5rem !important;
        padding-bottom: 0.35rem !important;
        scrollbar-width: none !important;
        -webkit-overflow-scrolling: touch !important;
    }

    .pa-ladder-bars-container::-webkit-scrollbar {
        display: none !important;
    }

    .pa-ladder-item {
        flex: 0 0 145px !important;
        min-width: 145px !important;
    }

    .pa-pillar-alert-banner {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }

    .pa-pillar-alert-banner .hcis-button {
        width: 100%;
        justify-content: center;
    }

    .pa-pillar-card {
        padding: 0.85rem;
        gap: 0.7rem;
    }

    .pa-pillar-card__header {
        align-items: flex-start;
    }

    .pa-pillar-card__footer {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .pa-pillar-card__footer-hint {
        justify-content: center;
    }

    .pa-pillar-card__actions {
        width: 100%;
        display: flex;
        gap: 0.45rem;
    }

    .pa-pillar-card__actions :deep(.hcis-button),
    .pa-pillar-card__actions .hcis-button {
        flex: 1 1 0;
        justify-content: center;
        width: auto;
    }
}
</style>
