<template>
    <div class="pa-policy-view">
        <!-- Executive Policy Readiness Stats (Ultra-Compact on Mobile) -->
        <HcisStatGroup
            :stats="policyStats"
            :cols="isMobile ? 2 : 4"
            gap="xs"
            :layout="isMobile ? 'dense' : 'compact'"
            appearance="crystal"
            class="pa-policy-stats mb-2"
        />

        <!-- Alert Banner if some levels lack active policy -->
        <div v-if="missingActiveLevels.length" class="pa-policy-alert-banner mb-2" role="alert">
            <div class="pa-policy-alert-banner__body">
                <HcisIcon name="exclamation-circle-fill" size="xs" color="warning" class="mt-0.5 shrink-0" />
                <div class="pa-policy-alert-banner__content">
                    <strong>{{ missingActiveLevels.length }} level jabatan belum memiliki Komposisi Aktif</strong>
                    <p>
                        <span class="d-none d-sm-inline">Level belum memiliki aturan ACTIVE: </span>
                        <span class="pa-missing-level-pills">
                            <span v-for="lvl in missingActiveLevels" :key="`missing-${lvl.ID_Level}`" class="pa-missing-pill">
                                {{ lvl.Nama_Level }}
                            </span>
                        </span>
                    </p>
                </div>
            </div>
            <HcisButton
                :label="isMobile ? 'Atur' : 'Atur Level Pertama'"
                variant="primary"
                size="xs"
                leading-icon="plus-lg"
                class="shrink-0"
                @click="$emit('create', missingActiveLevels[0]?.ID_Level)"
            />
        </div>

        <!-- Corporate Multi-Rater Ladder Widget (Collapsible & Compact) -->
        <section class="pa-policy-ladder-card mb-2" aria-label="Arsitektur Evaluasi Multi-Rater Perusahaan">
            <div class="pa-policy-ladder-card__header">
                <div class="pa-policy-ladder-card__title">
                    <span class="pa-eyebrow">MATRIKS MULTI-RATER PERUSAHAAN</span>
                    <h4>Kesiapan Evaluasi Penilai per Level</h4>
                </div>
                <div class="pa-policy-ladder-card__controls">
                    <HcisBadge
                        :label="`${levelsWithActivePolicy.length}/${allLevels.length} Siap`"
                        :variant="missingActiveLevels.length ? 'warning' : 'success'"
                        size="xs"
                        dot
                    />
                    <div class="pa-policy-ladder-card__legend d-none d-sm-flex">
                        <div class="pa-legend-item">
                            <span class="pa-legend-dot pa-legend-dot--active" />
                            <span>Aktif</span>
                        </div>
                        <div class="pa-legend-item">
                            <span class="pa-legend-dot pa-legend-dot--draft" />
                            <span>Draft</span>
                        </div>
                        <div class="pa-legend-item">
                            <span class="pa-legend-dot pa-legend-dot--none" />
                            <span>Kosong</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="pa-ladder-toggle-btn"
                        :title="isLadderCollapsed ? 'Tampilkan seluruh matriks level' : 'Ciutkan ringkasan matriks'"
                        @click="isLadderCollapsed = !isLadderCollapsed"
                    >
                        <HcisIcon :name="isLadderCollapsed ? 'chevron-down' : 'chevron-up'" size="2xs" />
                        <span>{{ isLadderCollapsed ? 'Buka' : 'Ciutkan' }}</span>
                    </button>
                </div>
            </div>

            <!-- Collapsed Mini Track Summary -->
            <div v-if="isLadderCollapsed" class="pa-ladder-collapsed-summary">
                <div class="pa-ladder-mini-track">
                    <div
                        class="pa-ladder-mini-seg pa-ladder-mini-seg--active"
                        :style="{ width: `${allLevels.length ? (levelsWithActivePolicy.length / allLevels.length) * 100 : 0}%` }"
                        :title="`${levelsWithActivePolicy.length} Level Aktif`"
                    />
                    <div
                        class="pa-ladder-mini-seg pa-ladder-mini-seg--draft"
                        :style="{ width: `${allLevels.length ? (draftCount / allLevels.length) * 100 : 0}%` }"
                        :title="`${draftCount} Level Draft`"
                    />
                </div>
                <div class="pa-ladder-mini-pills">
                    <span class="pa-mini-stat pa-mini-stat--active">{{ levelsWithActivePolicy.length }} Aktif</span>
                    <span v-if="draftCount" class="pa-mini-stat pa-mini-stat--draft">{{ draftCount }} Draft</span>
                    <span v-if="missingActiveLevels.length" class="pa-mini-stat pa-mini-stat--empty">{{ missingActiveLevels.length }} Belum Diatur</span>
                </div>
            </div>

            <!-- Expanded Matrix Grid -->
            <div v-else class="pa-ladder-grid">
                <div
                    v-for="group in sortedLevelGroups"
                    :key="`ladder-group-${group.level.ID_Level}`"
                    class="pa-ladder-card"
                    :class="{
                        'pa-ladder-card--active': Boolean(group.activePolicy),
                        'pa-ladder-card--draft': !group.activePolicy && group.draftPolicy,
                        'pa-ladder-card--empty': !group.activePolicy && !group.draftPolicy,
                    }"
                    @click="group.activePolicy ? $emit('policy', group.activePolicy) : $emit('create', group.level.ID_Level)"
                >
                    <div class="pa-ladder-card__head">
                        <span class="pa-ladder-level-name" :title="group.level.Nama_Level">{{ group.level.Nama_Level }}</span>
                        <span class="pa-ladder-rank">Tk. {{ group.level.Level_Hierarchy || group.level.ID_Level }}</span>
                    </div>

                    <div class="pa-ladder-card__body">
                        <template v-if="group.activePolicy">
                            <span class="pa-ladder-code" :title="group.activePolicy.Kode_Policy">{{ group.activePolicy.Kode_Policy }}</span>
                            <span class="pa-ladder-status pa-ladder-status--active">
                                <HcisIcon name="check-circle-fill" size="2xs" /> v{{ group.activePolicy.Versi }} Aktif
                            </span>
                        </template>
                        <template v-else-if="group.draftPolicy">
                            <span class="pa-ladder-code" :title="group.draftPolicy.Kode_Policy">{{ group.draftPolicy.Kode_Policy }}</span>
                            <span class="pa-ladder-status pa-ladder-status--draft">
                                <HcisIcon name="clock-history" size="2xs" /> v{{ group.draftPolicy.Versi }} Draft
                            </span>
                        </template>
                        <div v-else class="pa-ladder-empty-state">
                            <HcisIcon name="plus-circle" size="2xs" />
                            <span>Atur</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Standard Design System Filter Bar Component -->
        <HcisFilterBar
            :search="search"
            :search-placeholder="isMobile ? 'Cari komposisi...' : 'Cari kode policy, nama aturan, atau level jabatan...'"
            :quick-chips="statusQuickChips"
            :active-chip-value="activeSegmentTab"
            :results-count="filteredLevelGroups.length"
            :show-view-toggle="true"
            :view-mode="viewMode"
            variant="card"
            sentiment="purple"
            size="sm"
            glow
            class="pa-policy-filter-bar mb-2"
            @update:search="search = $event"
            @chip-click="(c) => activeSegmentTab = (c?.value ?? c)"
            @update:view-mode="viewMode = $event"
            @reset="resetFilters"
        >
            <template #actions>
                <HcisButton
                    label="Komposisi Baru"
                    variant="primary"
                    size="xs"
                    leading-icon="plus-lg"
                    @click="$emit('create', null)"
                />
            </template>
        </HcisFilterBar>

        <!-- Loading State -->
        <div v-if="loading" class="pa-policy-loading">
            <div class="pa-policy-skeleton-grid">
                <div v-for="n in 4" :key="`skel-policy-${n}`" class="pa-policy-skeleton-card">
                    <div class="pa-policy-skeleton-card__head">
                        <HcisSkeleton width="28px" height="28px" rounded="md" />
                        <div>
                            <HcisSkeleton width="120px" height="0.8rem" rounded="sm" />
                            <HcisSkeleton width="60px" height="0.6rem" rounded="pill" />
                        </div>
                    </div>
                    <HcisSkeleton width="100%" height="1.6rem" rounded="md" />
                    <HcisSkeleton width="70%" height="0.6rem" rounded="sm" />
                </div>
            </div>
        </div>

        <!-- Initial Empty State (When no policy rules exist in master) -->
        <HcisEmptyState
            v-else-if="!rows.length"
            variant="card"
            sentiment="info"
            size="md"
            animated
            title="Belum Ada Komposisi Penilai"
            description="Master komposisi penilai belum memiliki konfigurasi multi-rater. Buat versi kebijakan komposisi penilai pertama untuk menentukan alokasi bobot per level jabatan."
            icon="people-fill"
            class="pa-policy-empty-state"
        >
            <template #actions>
                <HcisButton
                    label="Buat Komposisi Penilai Pertama"
                    variant="primary"
                    size="sm"
                    leading-icon="plus-lg"
                    @click="$emit('create', null)"
                />
            </template>
        </HcisEmptyState>

        <!-- Filtered Empty State (Search / Tab filter yields 0 results) -->
        <HcisEmptyState
            v-else-if="!filteredLevelGroups.length"
            variant="card"
            sentiment="neutral"
            size="sm"
            animated
            title="Tidak Ada Komposisi Penilai yang Cocok"
            :description="search ? `Tidak ditemukan komposisi penilai dengan kata kunci '${search}'.` : 'Tidak ditemukan komposisi penilai yang sesuai dengan penyaring saat ini.'"
            icon="search"
            class="pa-policy-empty-state"
        >
            <template #actions>
                <div class="pa-empty-actions-row">
                    <HcisButton
                        label="Reset Filter"
                        variant="secondary"
                        size="xs"
                        leading-icon="arrow-counterclockwise"
                        @click="resetFilters"
                    />
                    <HcisButton
                        label="Tambah Aturan Baru"
                        variant="ghost"
                        size="xs"
                        leading-icon="plus-lg"
                        @click="$emit('create', null)"
                    />
                </div>
            </template>
        </HcisEmptyState>

        <!-- Loaded Content -->
        <div v-else class="pa-policy-content">
            <!-- Mode 1: Level Hierarchy Grouped Cards -->
            <div v-if="viewMode === 'level'" class="pa-policy-level-groups">
                <section
                    v-for="group in filteredLevelGroups"
                    :key="`level-group-${group.level.ID_Level}`"
                    class="pa-level-group"
                >
                    <!-- Level Section Header -->
                    <div class="pa-level-group__header">
                        <div class="pa-level-group__identity">
                            <div class="pa-level-group__icon">
                                <HcisIcon name="diagram-3-fill" size="xs" />
                            </div>
                            <div class="pa-level-group__titles">
                                <div class="pa-level-group__title-row">
                                    <h3>{{ group.level.Nama_Level }}</h3>
                                    <span class="pa-level-group__hierarchy-pill">
                                        Level {{ group.level.ID_Level }}
                                        <template v-if="group.level.Level_Hierarchy">
                                            &bull; Tk. {{ group.level.Level_Hierarchy }}
                                        </template>
                                    </span>
                                </div>
                                <span class="pa-level-group__status-subtext">
                                    <template v-if="group.activePolicy">
                                        Versi Aktif: <strong>{{ group.activePolicy.Kode_Policy }} (v{{ group.activePolicy.Versi }})</strong>
                                    </template>
                                    <template v-else>
                                        <span class="text-amber-700 font-semibold">Belum memiliki aturan aktif</span>
                                    </template>
                                </span>
                            </div>
                        </div>

                        <div class="pa-level-group__actions">
                            <HcisButton
                                variant="secondary"
                                size="xs"
                                :label="isMobile ? 'Tambah' : 'Tambah Versi'"
                                leading-icon="plus-lg"
                                @click="$emit('create', group.level.ID_Level)"
                            />
                        </div>
                    </div>

                    <!-- Policies for this level -->
                    <div v-if="group.policies.length" class="pa-level-group__policies-grid">
                        <article
                            v-for="policy in group.policies"
                            :key="`policy-card-${policy.Id_PA_Rater_Policy}`"
                            class="pa-policy-card"
                            :class="{
                                'pa-policy-card--active': policy.Status_Policy === 'ACTIVE',
                                'pa-policy-card--draft': policy.Status_Policy === 'DRAFT',
                                'pa-policy-card--inactive': policy.Status_Policy === 'INACTIVE',
                            }"
                        >
                            <!-- Card Head -->
                            <div class="pa-policy-card__head">
                                <div class="pa-policy-card__identity">
                                    <span class="pa-policy-card__code">{{ policy.Kode_Policy }}</span>
                                    <h4>{{ policy.Nama_Policy }}</h4>
                                </div>

                                <div class="pa-policy-card__badges">
                                    <span class="pa-policy-version-tag">v{{ policy.Versi }}</span>
                                    <HcisStatusBadge
                                        :status="policy.Status_Policy"
                                        :label="policy.Status_Policy === 'ACTIVE' ? 'Aktif' : policy.Status_Policy === 'DRAFT' ? 'Draft' : 'Nonaktif'"
                                        size="xs"
                                    />
                                </div>
                            </div>

                            <!-- Validity Period & Description -->
                            <div class="pa-policy-card__meta">
                                <div class="pa-policy-card__date-range">
                                    <HcisIcon name="calendar-event" size="2xs" />
                                    <span>
                                        <template v-if="policy.Berlaku_Dari">
                                            {{ formatDate(policy.Berlaku_Dari) }} s/d {{ policy.Berlaku_Sampai ? formatDate(policy.Berlaku_Sampai) : 'Seterusnya' }}
                                        </template>
                                        <template v-else>Berlaku permanen / tanpa batas</template>
                                    </span>
                                </div>

                                <p v-if="policy.Deskripsi" class="pa-policy-card__desc">
                                    {{ policy.Deskripsi }}
                                </p>
                            </div>

                            <!-- Catatan Versi if any -->
                            <div v-if="policy.Catatan_Versi" class="pa-policy-card__changelog">
                                <HcisIcon name="file-text" size="2xs" />
                                <span>{{ policy.Catatan_Versi }}</span>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="pa-policy-card__footer">
                                <div class="pa-policy-card__footer-left">
                                    <HcisButton
                                        variant="primary"
                                        size="xs"
                                        :label="isMobile ? 'Bobot' : 'Kelola Bobot'"
                                        leading-icon="pie-chart-fill"
                                        @click="$emit('policy', policy)"
                                    />
                                </div>

                                <div class="pa-policy-card__footer-right">
                                    <HcisButton
                                        v-if="policy.Status_Policy === 'DRAFT'"
                                        variant="success"
                                        size="xs"
                                        label="Aktifkan"
                                        leading-icon="check-circle"
                                        @click="$emit('activate', policy)"
                                    />
                                    <HcisButton
                                        variant="ghost"
                                        size="xs"
                                        :label="isMobile ? 'Ubah' : 'Ubah Info'"
                                        leading-icon="pencil"
                                        @click="$emit('edit', policy)"
                                    />
                                    <HcisButton
                                        variant="secondary"
                                        size="xs"
                                        :label="isMobile ? 'Revisi' : 'Versi Baru'"
                                        leading-icon="copy"
                                        title="Buat versi berikutnya dari aturan ini"
                                        @click="$emit('new-version', policy)"
                                    />
                                </div>
                            </div>
                        </article>
                    </div>

                    <!-- No policy yet prompt -->
                    <div v-else class="pa-level-group__empty-prompt">
                        <div class="pa-empty-prompt-body">
                            <HcisIcon name="info-circle" size="xs" color="warning" class="shrink-0" />
                            <span>Level ini belum memiliki komposisi penilai aktif.</span>
                        </div>
                        <HcisButton
                            label="Buat Komposisi Sekarang"
                            variant="primary"
                            size="xs"
                            leading-icon="plus-lg"
                            class="shrink-0"
                            @click="$emit('create', group.level.ID_Level)"
                        />
                    </div>
                </section>
            </div>

            <!-- Mode 2: Table Matrix Mode -->
            <div v-else class="pa-policy-table-wrap">
                <HcisTable
                    :columns="tableColumns"
                    :rows="filteredTableRows"
                    row-key="Id_PA_Rater_Policy"
                    responsive="scroll"
                    hoverable
                    variant="glass"
                    sentiment="purple"
                    @row-click="({ row }) => $emit('policy', row)"
                >
                    <template #cell-Kode_Policy="{ row }">
                        <div class="pa-table-code-cell">
                            <strong>{{ row.Kode_Policy }}</strong>
                            <small>{{ row.Nama_Policy }}</small>
                        </div>
                    </template>

                    <template #cell-Level="{ row }">
                        <div class="pa-table-level-cell">
                            <span>{{ getLevelName(row.ID_Level) }}</span>
                            <small class="pa-mono-text">Level {{ row.ID_Level }}</small>
                        </div>
                    </template>

                    <template #cell-Versi="{ row }">
                        <span class="pa-policy-version-tag">v{{ row.Versi }}</span>
                    </template>

                    <template #cell-Masa_Berlaku="{ row }">
                        <span class="pa-table-date-cell">
                            {{ row.Berlaku_Dari ? formatDate(row.Berlaku_Dari) : '—' }}
                            s/d
                            {{ row.Berlaku_Sampai ? formatDate(row.Berlaku_Sampai) : 'Seterusnya' }}
                        </span>
                    </template>

                    <template #cell-Status_Policy="{ row }">
                        <HcisStatusBadge
                            :status="row.Status_Policy"
                            :label="row.Status_Policy === 'ACTIVE' ? 'Aktif' : row.Status_Policy === 'DRAFT' ? 'Draft' : 'Nonaktif'"
                            size="xs"
                        />
                    </template>

                    <template #cell-aksi="{ row }">
                        <div class="pa-row-actions">
                            <HcisButton
                                variant="primary"
                                size="xs"
                                :label="isMobile ? '' : 'Bobot'"
                                :title="'Kelola Bobot'"
                                leading-icon="pie-chart-fill"
                                @click.stop="$emit('policy', row)"
                            />
                            <HcisButton
                                v-if="row.Status_Policy === 'DRAFT'"
                                variant="success"
                                size="xs"
                                :label="isMobile ? '' : 'Aktifkan'"
                                :title="'Aktifkan'"
                                leading-icon="check-circle"
                                @click.stop="$emit('activate', row)"
                            />
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                :label="isMobile ? '' : 'Ubah'"
                                :title="'Ubah Info'"
                                leading-icon="pencil"
                                @click.stop="$emit('edit', row)"
                            />
                            <HcisButton
                                variant="secondary"
                                size="xs"
                                :label="isMobile ? '' : 'Revisi'"
                                :title="'Buat Versi Baru'"
                                leading-icon="copy"
                                @click.stop="$emit('new-version', row)"
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

defineOptions({ name: 'PaAturanPenilaiView' });

const props = defineProps({
    rows: { type: Array, default: () => [] },
    references: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    searchQuery: { type: String, default: '' },
    selectedLevelFilter: { type: [String, Number], default: '' },
});

defineEmits(['create', 'edit', 'policy', 'activate', 'new-version']);

const isMobile = ref(false);
const isTablet = ref(false);
const isLadderCollapsed = ref(false);

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

const viewMode = ref('level');
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

const tableColumns = computed(() => [
    { key: 'Kode_Policy', label: 'Komposisi Penilai', minWidth: isMobile.value ? '150px' : '200px' },
    { key: 'Level', label: 'Level Jabatan', minWidth: isMobile.value ? '120px' : '150px' },
    { key: 'Versi', label: 'Versi', width: '75px' },
    { key: 'Masa_Berlaku', label: 'Masa Berlaku', minWidth: '160px' },
    { key: 'Status_Policy', label: 'Status', width: '100px' },
    { key: 'aksi', label: 'Aksi', align: 'right', width: isMobile.value ? '160px' : '260px' },
]);

const allLevels = computed(() => {
    const refLevels = props.references?.level || [];
    if (refLevels.length) return refLevels;

    // Fallback if references level not yet arrived
    const levelMap = new Map();
    props.rows.forEach((r) => {
        const id = Number(r.ID_Level);
        if (id && !levelMap.has(id)) {
            levelMap.set(id, { ID_Level: id, Nama_Level: `Level ${id}`, Level_Hierarchy: null });
        }
    });
    return Array.from(levelMap.values());
});

const levelGroups = computed(() => {
    return allLevels.value.map((lvl) => {
        const id = Number(lvl.ID_Level);
        const levelPolicies = props.rows.filter((r) => Number(r.ID_Level) === id);

        // Sort policies: ACTIVE first, then DRAFT, then latest version
        const sorted = [...levelPolicies].sort((a, b) => {
            if (a.Status_Policy === 'ACTIVE' && b.Status_Policy !== 'ACTIVE') return -1;
            if (b.Status_Policy === 'ACTIVE' && a.Status_Policy !== 'ACTIVE') return 1;
            return Number(b.Versi || 1) - Number(a.Versi || 1);
        });

        const activePolicy = sorted.find((p) => p.Status_Policy === 'ACTIVE') || null;
        const draftPolicy = sorted.find((p) => p.Status_Policy === 'DRAFT') || null;

        return {
            level: {
                ...lvl,
                ID_Level: id,
                Nama_Level: lvl.Nama_Level || `Level ${id}`,
                Level_Hierarchy: lvl.Level_Hierarchy || null,
            },
            policies: sorted,
            activePolicy,
            draftPolicy,
            hasActive: Boolean(activePolicy),
        };
    });
});

const sortedLevelGroups = computed(() => {
    return [...levelGroups.value].sort((a, b) => {
        const rankA = Number(a.level.Level_Hierarchy) || Number(a.level.ID_Level) || 999;
        const rankB = Number(b.level.Level_Hierarchy) || Number(b.level.ID_Level) || 999;
        return rankA - rankB;
    });
});

const levelsWithActivePolicy = computed(() => levelGroups.value.filter((g) => g.hasActive));
const missingActiveLevels = computed(() => levelGroups.value.filter((g) => !g.hasActive).map((g) => g.level));
const draftCount = computed(() => props.rows.filter((r) => r.Status_Policy === 'DRAFT').length);
const inactiveCount = computed(() => props.rows.filter((r) => r.Status_Policy === 'INACTIVE').length);

const policyStats = computed(() => [
    {
        id: 'total-policies',
        label: 'Total Versi Kebijakan',
        value: formatAngka(props.rows.length),
        caption: isMobile.value ? 'Total terdaftar' : 'Versi kebijakan terdaftar',
        icon: 'diagram-3-fill',
        sentiment: 'brand',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
    },
    {
        id: 'configured-levels',
        label: 'Level Terkonfigurasi',
        value: `${formatAngka(levelsWithActivePolicy.value.length)} / ${formatAngka(allLevels.value.length)}`,
        caption: isMobile.value ? 'Aturan aktif' : 'Level dengan aturan aktif',
        icon: 'patch-check-fill',
        sentiment: 'success',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
    },
    {
        id: 'draft-policies',
        label: 'Status Draft',
        value: formatAngka(draftCount.value),
        caption: draftCount.value > 0 ? (isMobile.value ? 'Belum aktif' : 'Perlu dilengkapi bobot') : 'Semua final',
        icon: 'clock-history',
        sentiment: draftCount.value > 0 ? 'warning' : 'neutral',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
    },
    {
        id: 'archive-policies',
        label: 'Arsip / Historis',
        value: formatAngka(inactiveCount.value),
        caption: isMobile.value ? 'Versi lama' : 'Arsip versi terdahulu',
        icon: 'archive-fill',
        sentiment: 'neutral',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
    },
]);

const statusQuickChips = computed(() => [
    {
        id: 'all',
        value: 'all',
        label: isMobile.value ? 'Semua' : 'Semua Level',
        badge: levelGroups.value.length,
        icon: 'diagram-3-fill',
    },
    {
        id: 'active',
        value: 'active',
        label: isMobile.value ? 'Aktif' : 'Ber-Aturan Aktif',
        badge: levelsWithActivePolicy.value.length,
        color: 'success',
        icon: 'patch-check-fill',
    },
    ...(missingActiveLevels.value.length > 0 ? [{
        id: 'missing',
        value: 'missing',
        label: isMobile.value ? 'Belum Diatur' : 'Belum Dikonfigurasi',
        badge: missingActiveLevels.value.length,
        color: 'warning',
        icon: 'exclamation-circle-fill',
    }] : []),
    ...(draftCount.value > 0 ? [{
        id: 'draft',
        value: 'draft',
        label: isMobile.value ? 'Draft' : 'Ada Draft',
        badge: draftCount.value,
        color: 'brand',
        icon: 'clock-history',
    }] : []),
]);

const filteredLevelGroups = computed(() => {
    let list = sortedLevelGroups.value;

    if (activeSegmentTab.value === 'active') {
        list = list.filter((g) => g.hasActive);
    } else if (activeSegmentTab.value === 'missing') {
        list = list.filter((g) => !g.hasActive);
    } else if (activeSegmentTab.value === 'draft') {
        list = list.filter((g) => Boolean(g.draftPolicy));
    }

    if (props.selectedLevelFilter) {
        const filterId = Number(props.selectedLevelFilter);
        list = list.filter((g) => g.level.ID_Level === filterId);
    }

    const query = String(search.value || props.searchQuery || '').toLowerCase().trim();
    if (query) {
        list = list.filter((g) => {
            const matchLevel = g.level.Nama_Level.toLowerCase().includes(query) || String(g.level.ID_Level).includes(query);
            const matchPolicy = g.policies.some(
                (p) =>
                    (p.Kode_Policy || '').toLowerCase().includes(query) ||
                    (p.Nama_Policy || '').toLowerCase().includes(query) ||
                    (p.Deskripsi || '').toLowerCase().includes(query) ||
                    (p.Catatan_Versi || '').toLowerCase().includes(query),
            );
            return matchLevel || matchPolicy;
        });
    }

    return list;
});

const filteredTableRows = computed(() => {
    let list = props.rows;

    if (props.selectedLevelFilter) {
        const filterId = Number(props.selectedLevelFilter);
        list = list.filter((r) => Number(r.ID_Level) === filterId);
    }

    if (activeSegmentTab.value === 'active') {
        list = list.filter((r) => r.Status_Policy === 'ACTIVE');
    } else if (activeSegmentTab.value === 'draft') {
        list = list.filter((r) => r.Status_Policy === 'DRAFT');
    }

    const query = String(search.value || props.searchQuery || '').toLowerCase().trim();
    if (query) {
        list = list.filter(
            (r) =>
                (r.Kode_Policy || '').toLowerCase().includes(query) ||
                (r.Nama_Policy || '').toLowerCase().includes(query) ||
                (r.Deskripsi || '').toLowerCase().includes(query) ||
                (r.Catatan_Versi || '').toLowerCase().includes(query) ||
                String(r.ID_Level).includes(query),
        );
    }

    return list;
});

function getLevelName(levelId) {
    const found = allLevels.value.find((l) => Number(l.ID_Level) === Number(levelId));
    return found ? found.Nama_Level : `Level ${levelId}`;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
}

function formatAngka(value) {
    return Number(value || 0).toLocaleString('id-ID');
}
</script>

<style scoped>
.pa-policy-view {
    display: flex;
    flex-direction: column;
    min-width: 0;
    width: 100%;
}

/* Executive Policy Readiness Stats */
.pa-policy-stats {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 0.6rem !important;
    width: 100% !important;
    box-sizing: border-box !important;
    margin: 0 0 0.5rem 0 !important;
}

.pa-policy-stats :deep(.hcis-stat),
.pa-policy-stats .hcis-stat {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    min-height: unset !important;
    height: 100% !important;
    box-sizing: border-box !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
    padding: 0.65rem 0.85rem !important;
    border-radius: var(--hcis-radius-md, 10px) !important;
}

.pa-policy-stats :deep(.hcis-stat__compact-wrap) {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    min-width: 0;
    width: 100%;
}

.pa-policy-stats :deep(.hcis-stat__value) {
    font-size: 1.35rem !important;
    font-weight: 800 !important;
    line-height: 1.15 !important;
}

.pa-policy-stats :deep(.hcis-stat__label) {
    font-size: 0.72rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.02em !important;
}

.pa-policy-stats :deep(.hcis-stat__caption) {
    font-size: 0.68rem !important;
    margin-top: 0.15rem !important;
    color: var(--hcis-slate-500, #64748b) !important;
}

.pa-policy-stats :deep(.hcis-stat__icon-box) {
    width: 1.75rem !important;
    height: 1.75rem !important;
}

/* Alert Banner */
.pa-policy-alert-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    padding: 0.55rem 0.8rem;
    border: 1px solid rgba(245, 158, 11, 0.35);
    border-radius: var(--hcis-radius-md, 8px);
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.7), rgba(255, 255, 255, 0.9));
}

.pa-policy-alert-banner__body {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    min-width: 0;
}

.pa-policy-alert-banner__content {
    min-width: 0;
}

.pa-policy-alert-banner__content strong {
    display: block;
    color: #92400e;
    font-size: 0.76rem;
    margin-bottom: 0.05rem;
}

.pa-policy-alert-banner__content p {
    margin: 0;
    color: #b45309;
    font-size: 0.7rem;
    line-height: 1.35;
}

.pa-missing-level-pills {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 0.2rem;
    margin-left: 0.2rem;
}

.pa-missing-pill {
    padding: 0.05rem 0.3rem;
    border-radius: var(--hcis-radius-xs, 4px);
    background: rgba(245, 158, 11, 0.25);
    color: #78350f;
    font-weight: 750;
    font-size: 0.66rem;
}

/* Corporate Multi-Rater Ladder Card */
.pa-policy-ladder-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    border: 1px solid rgba(139, 92, 246, 0.22);
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(245, 243, 255, 0.45));
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
}

.pa-policy-ladder-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-policy-ladder-card__title h4 {
    margin: 0.05rem 0 0;
    font-size: 0.84rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-policy-ladder-card__controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-policy-ladder-card__legend {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.66rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 650;
}

.pa-legend-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: var(--hcis-radius-xs, 3px);
}

.pa-legend-dot--active {
    background: #10b981;
}

.pa-legend-dot--draft {
    background: #f59e0b;
}

.pa-legend-dot--none {
    background: #cbd5e1;
}

.pa-ladder-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border: 1px solid rgba(139, 92, 246, 0.25);
    border-radius: var(--hcis-radius-xs, 4px);
    background: rgba(245, 243, 255, 0.8);
    color: #6d28d9;
    font-size: 0.64rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-ladder-toggle-btn:hover {
    background: #ede9fe;
}

/* Collapsed Mini Summary */
.pa-ladder-collapsed-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    padding: 0.35rem 0.5rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.8);
    flex-wrap: wrap;
}

.pa-ladder-mini-track {
    display: flex;
    height: 0.55rem;
    flex: 1;
    min-width: 8rem;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-ladder-mini-seg {
    height: 100%;
    transition: width 200ms ease;
}

.pa-ladder-mini-seg--active {
    background: #10b981;
}

.pa-ladder-mini-seg--draft {
    background: #f59e0b;
}

.pa-ladder-mini-pills {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.66rem;
}

.pa-mini-stat--active {
    color: #059669;
    font-weight: 750;
}

.pa-mini-stat--draft {
    color: #d97706;
    font-weight: 750;
}

.pa-mini-stat--empty {
    color: #e11d48;
    font-weight: 750;
}

/* Expanded Grid */
.pa-ladder-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(125px, 1fr));
    gap: 0.35rem;
}

.pa-ladder-card {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.4rem 0.5rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xs, 6px);
    background: #ffffff;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-ladder-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
    border-color: #7c3aed;
}

.pa-ladder-card--active {
    border-left: 3px solid #10b981;
}

.pa-ladder-card--draft {
    border-left: 3px solid #f59e0b;
    background: rgba(254, 243, 199, 0.25);
}

.pa-ladder-card--empty {
    border-style: dashed;
    border-color: rgba(245, 158, 11, 0.5);
    background: rgba(254, 243, 199, 0.2);
}

.pa-ladder-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.2rem;
}

.pa-ladder-level-name {
    font-size: 0.7rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-ladder-rank {
    font-size: 0.6rem;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-text-muted, #64748b);
    flex-shrink: 0;
}

.pa-ladder-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
}

.pa-ladder-code {
    font-size: 0.64rem;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 700;
    color: var(--hcis-text-strong, #334155);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-ladder-status {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.6rem;
    font-weight: 800;
}

.pa-ladder-status--active {
    color: #059669;
}

.pa-ladder-status--draft {
    color: #d97706;
}

.pa-ladder-empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.2rem;
    color: #b45309;
    font-size: 0.64rem;
    font-weight: 750;
    padding: 0.05rem 0;
}

/* Level Group Containers */
.pa-policy-level-groups {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-level-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.65rem 0.8rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.85));
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
}

.pa-level-group__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-bottom: 0.4rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.75);
}

.pa-level-group__identity {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}

.pa-level-group__icon {
    display: grid;
    place-items: center;
    width: 1.65rem;
    height: 1.65rem;
    border-radius: var(--hcis-radius-xs, 5px);
    background: rgba(139, 92, 246, 0.12);
    color: #7c3aed;
    flex-shrink: 0;
}

.pa-level-group__titles {
    min-width: 0;
}

.pa-level-group__title-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-level-group__title-row h3 {
    margin: 0;
    font-size: 0.84rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-level-group__hierarchy-pill {
    padding: 0.05rem 0.3rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.62rem;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 700;
}

.pa-level-group__status-subtext {
    font-size: 0.66rem;
    color: var(--hcis-text-muted, #64748b);
    display: block;
    margin-top: 0.05rem;
}

.pa-level-group__policies-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.pa-policy-card {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 0.65rem 0.75rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-sm, 8px);
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
    transition: transform 120ms ease, box-shadow 120ms ease, border-color 120ms ease;
}

.pa-policy-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    border-color: rgba(99, 102, 241, 0.35);
}

.pa-policy-card--active {
    border-left: 3.5px solid #10b981;
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.35), #ffffff);
}

.pa-policy-card--draft {
    border-left: 3.5px solid #f59e0b;
    border-style: dashed;
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.25), #ffffff);
}

.pa-policy-card--inactive {
    opacity: 0.75;
    background: #f8fafc;
    border-left: 3.5px solid #94a3b8;
}

.pa-policy-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.35rem;
}

.pa-policy-card__identity {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    min-width: 0;
}

.pa-policy-card__code {
    font-size: 0.66rem;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 800;
    color: #4f46e5;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-policy-card__identity h4 {
    margin: 0;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
}

.pa-policy-card__badges {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    flex-shrink: 0;
}

.pa-policy-version-tag {
    padding: 0.05rem 0.25rem;
    border-radius: var(--hcis-radius-xs, 3px);
    background: rgba(99, 102, 241, 0.1);
    color: #4338ca;
    font-size: 0.62rem;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 800;
}

.pa-policy-card__meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-policy-card__date-range {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.65rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-policy-card__desc {
    margin: 0;
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #475569);
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.pa-policy-card__changelog {
    display: flex;
    align-items: flex-start;
    gap: 0.25rem;
    padding: 0.2rem 0.35rem;
    border-radius: var(--hcis-radius-xs, 4px);
    background: rgba(241, 245, 249, 0.85);
    font-size: 0.64rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-policy-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    margin-top: auto;
    padding-top: 0.35rem;
    border-top: 1px solid rgba(226, 232, 240, 0.75);
    flex-wrap: wrap;
}

.pa-policy-card__footer-left {
    display: flex;
    align-items: center;
}

.pa-policy-card__footer-right {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    flex-wrap: wrap;
}

.pa-level-group__empty-prompt {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.55rem 0.75rem;
    border: 1px dashed rgba(245, 158, 11, 0.45);
    border-radius: var(--hcis-radius-xs, 6px);
    background: rgba(254, 243, 199, 0.25);
}

.pa-empty-prompt-body {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    color: #92400e;
}

/* Table Mode */
.pa-policy-table-wrap {
    width: 100%;
    min-width: 0;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-md, 10px);
    overflow: hidden;
    background: rgba(255, 255, 255, 0.9);
}

.pa-table-code-cell {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
}

.pa-table-code-cell strong {
    color: #4f46e5;
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.74rem;
}

.pa-table-code-cell small {
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.7rem;
}

.pa-table-level-cell {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
}

.pa-table-level-cell span {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-table-level-cell small {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.66rem;
}

.pa-table-date-cell {
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #475569);
}

.pa-row-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.15rem;
}

.pa-mono-text {
    font-family: var(--hcis-font-mono, monospace);
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.58rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

/* Skeletons */
.pa-policy-loading {
    padding: 0.5rem 0;
}

.pa-policy-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.pa-policy-skeleton-card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-sm, 8px);
    background: #ffffff;
}

.pa-policy-skeleton-card__head {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-policy-empty-state {
    margin: 0.75rem 0;
}

.pa-empty-actions-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Responsive Breakpoints */
@media (max-width: 900px) {
    .pa-level-group__policies-grid,
    .pa-policy-skeleton-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .pa-policy-stats {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.4rem !important;
        margin-bottom: 0.4rem !important;
    }

    .pa-policy-stats :deep(.hcis-stat),
    .pa-policy-stats .hcis-stat {
        min-height: unset !important;
        height: auto !important;
        padding: 0.45rem 0.55rem !important;
        border-radius: var(--hcis-radius-sm, 8px) !important;
    }

    .pa-policy-stats :deep(.hcis-stat__compact-wrap) {
        gap: 0.15rem;
    }

    .pa-policy-stats :deep(.hcis-stat__value) {
        font-size: 1.15rem !important;
        line-height: 1.15 !important;
        font-weight: 800 !important;
    }

    .pa-policy-stats :deep(.hcis-stat__label) {
        font-size: 0.64rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.02em !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .pa-policy-stats :deep(.hcis-stat__caption) {
        font-size: 0.58rem !important;
        margin-top: 0.05rem !important;
        color: var(--hcis-slate-500, #64748b) !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .pa-policy-stats :deep(.hcis-stat__icon-box) {
        width: 1.35rem !important;
        height: 1.35rem !important;
        border-radius: 5px !important;
    }

    .pa-policy-ladder-card {
        padding: 0.55rem 0.65rem;
    }

    .pa-policy-ladder-card__header {
        flex-direction: row;
        align-items: center;
        gap: 0.35rem;
    }

    .pa-ladder-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.3rem;
    }

    .pa-policy-alert-banner {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.4rem;
        padding: 0.45rem 0.65rem;
    }

    .pa-policy-filter-bar {
        margin-bottom: 0.4rem !important;
    }

    .pa-policy-level-groups {
        gap: 0.45rem !important;
    }

    .pa-level-group {
        padding: 0.55rem 0.65rem !important;
        gap: 0.35rem !important;
        border-radius: var(--hcis-radius-sm, 8px) !important;
    }

    .pa-level-group__header {
        padding-bottom: 0.35rem !important;
    }

    .pa-level-group__icon {
        width: 1.5rem !important;
        height: 1.5rem !important;
    }

    .pa-level-group__title-row h3 {
        font-size: 0.8rem !important;
    }

    .pa-policy-card {
        padding: 0.55rem 0.65rem !important;
        gap: 0.35rem !important;
    }

    .pa-policy-card__desc {
        -webkit-line-clamp: 1 !important;
    }

    .pa-policy-card__footer {
        flex-direction: column;
        align-items: stretch;
        gap: 0.35rem;
    }

    .pa-policy-card__footer-left,
    .pa-policy-card__footer-right {
        width: 100%;
    }

    .pa-policy-card__footer-left :deep(button),
    .pa-policy-card__footer-left :deep(a) {
        width: 100%;
        justify-content: center;
        height: 1.85rem !important;
    }

    .pa-policy-card__footer-right {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(65px, 1fr));
        gap: 0.2rem;
    }

    .pa-policy-card__footer-right :deep(button) {
        width: 100%;
        justify-content: center;
        padding-left: 0.25rem;
        padding-right: 0.25rem;
        height: 1.85rem !important;
        font-size: 0.68rem !important;
    }
}
</style>
