<template>
    <div
        class="hcis-matrix-grid-container"
        :class="[
            `hcis-matrix-grid-container--${variant}`,
            { 'is-readonly': readonly },
        ]"
    >
        <!-- Header Controls & Live Permission Stats -->
        <div v-if="title || $slots.header" class="hcis-matrix-grid__header">
            <slot name="header">
                <div class="hcis-matrix-grid__header-left">
                    <div class="hcis-matrix-grid__icon-glow">
                        <HcisIcon v-if="icon" :name="icon" size="sm" />
                    </div>
                    <div>
                        <h6 class="hcis-matrix-grid__title">{{ title }}</h6>
                        <small v-if="subtitle" class="hcis-matrix-grid__subtitle">{{ subtitle }}</small>
                    </div>
                </div>

                <div class="hcis-matrix-grid__header-right">
                    <!-- Live Permission Coverage Gauge -->
                    <div class="hcis-matrix-stats-pill">
                        <div class="hcis-matrix-stats-meta">
                            <span class="hcis-matrix-stats-label">Cakupan Otorisasi:</span>
                            <strong class="hcis-matrix-stats-val">
                                {{ stats.grantedCount }} / {{ stats.totalCells }} ({{ stats.percentage }}%)
                            </strong>
                        </div>
                        <div class="hcis-matrix-stats-bar">
                            <div
                                class="hcis-matrix-stats-fill"
                                :style="{ width: `${stats.percentage}%` }"
                            />
                        </div>
                    </div>

                    <!-- Row Search Quick Filter Input -->
                    <div v-if="searchable" class="hcis-matrix-search">
                        <HcisField
                            id="hcis-matrix-search-field"
                            v-model="rowSearchQuery"
                            placeholder="Cari modul / izin..."
                            type="search"
                            size="sm"
                            clearable
                        >
                            <template #prefix>
                                <HcisIcon name="search" size="2xs" class="text-muted" />
                            </template>
                        </HcisField>
                    </div>

                    <slot name="headerActions" />
                </div>
            </slot>
        </div>

        <!-- Matrix Table Container with Crosshair & Glassmorphic Sticky -->
        <div class="hcis-matrix-table-wrap">
            <table class="hcis-matrix-table">
                <thead>
                    <tr>
                        <!-- First Column Header (Row Title / Select All Master) -->
                        <th class="hcis-matrix-th hcis-matrix-th--first">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <HcisCheckbox
                                        v-if="showMasterCheckboxes && !readonly"
                                        :model-value="isGlobalAllSelected"
                                        title="Pilih / Batal Seluruh Matriks"
                                        @update:model-value="toggleGlobalSelectAll"
                                    />
                                    <span>Modul / Fitur Menu</span>
                                </div>
                                <span class="hcis-matrix-th-badge">{{ filteredRows.length }} Baris</span>
                            </div>
                        </th>

                        <!-- Dynamic Matrix Columns -->
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            class="hcis-matrix-th text-center"
                            :class="{ 'is-hover-col': hoveredCol === col.key }"
                            :style="{ width: col.width || 'auto' }"
                        >
                            <div class="d-flex flex-column align-items-center gap-1">
                                <HcisCheckbox
                                    v-if="showMasterCheckboxes && !readonly"
                                    :model-value="isColAllSelected(col.key)"
                                    title="Pilih / Batal Seluruh Kolom Ini"
                                    @update:model-value="toggleColSelectAll(col.key, $event)"
                                />
                                <span class="hcis-matrix-col-title" :title="col.label">{{ col.label }}</span>
                                <small v-if="col.subtitle" class="hcis-matrix-col-subtext">{{ col.subtitle }}</small>
                            </div>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <template v-for="(groupRows, groupName) in groupedData" :key="groupName">
                        <!-- Module Group Header Row with Expand Toggle & Quick Group Actions -->
                        <tr v-if="groupBy && groupName !== '__ungrouped__'" class="hcis-matrix-group-row">
                            <td :colspan="columns.length + 1" class="hcis-matrix-group-td">
                                <div class="d-flex align-items-center justify-content-between">
                                    <button
                                        type="button"
                                        class="hcis-matrix-group-toggle"
                                        @click="toggleGroup(groupName)"
                                    >
                                        <HcisIcon
                                            :name="collapsedGroups.has(groupName) ? 'chevron-right' : 'chevron-down'"
                                            size="xs"
                                            class="text-indigo-600"
                                        />
                                        <HcisIcon name="folder2-open" size="xs" class="text-indigo-600 me-1" />
                                        <strong class="hcis-matrix-group-title">{{ groupName }}</strong>
                                        <span class="hcis-matrix-group-count">({{ groupRows.length }} Fitur)</span>
                                    </button>

                                    <!-- Group Quick Actions -->
                                    <div v-if="showMasterCheckboxes && !readonly" class="d-flex align-items-center gap-2">
                                        <button
                                            type="button"
                                            class="hcis-matrix-group-action-btn text-emerald-600"
                                            title="Beri Seluruh Akses di Grup Ini"
                                            @click.stop="toggleGroupSelectAll(groupRows, true)"
                                        >
                                            <HcisIcon name="check-all" size="2xs" />
                                            <span>Setujui Grup</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="hcis-matrix-group-action-btn text-rose-600"
                                            title="Cabut Seluruh Akses di Grup Ini"
                                            @click.stop="toggleGroupSelectAll(groupRows, false)"
                                        >
                                            <HcisIcon name="x-lg" size="2xs" />
                                            <span>Kosongkan Grup</span>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <!-- Matrix Item Rows -->
                        <template v-if="!collapsedGroups.has(groupName)">
                            <tr
                                v-for="row in groupRows"
                                :key="row[rowKey] || row.id"
                                class="hcis-matrix-row"
                                :class="{ 'is-hover-row': hoveredRowId === row[rowKey] }"
                                @mouseenter="hoveredRowId = row[rowKey]"
                                @mouseleave="hoveredRowId = null"
                            >
                                <!-- Row Title & Master Row Checkbox -->
                                <td class="hcis-matrix-td hcis-matrix-td--first">
                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <HcisCheckbox
                                            v-if="showMasterCheckboxes && !readonly"
                                            :model-value="isRowAllSelected(row)"
                                            title="Pilih / Batal Seluruh Baris Ini"
                                            @update:model-value="toggleRowSelectAll(row, $event)"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <div class="d-flex align-items-center gap-1.5">
                                                <strong class="hcis-matrix-row-title text-truncate" :title="row.title || row.label">
                                                    {{ row.title || row.label }}
                                                </strong>
                                                <HcisBadge
                                                    v-if="row.badge"
                                                    :label="row.badge"
                                                    size="xs"
                                                    variant="brand"
                                                />
                                            </div>
                                            <small v-if="row.code || row.subtitle" class="hcis-matrix-row-subtext text-truncate d-block">
                                                {{ row.code || row.subtitle }}
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <!-- High-Tech Interactive LED Permission Cells -->
                                <td
                                    v-for="col in columns"
                                    :key="col.key"
                                    class="hcis-matrix-td text-center"
                                    :class="{ 'is-hover-col': hoveredCol === col.key }"
                                    @mouseenter="hoveredCol = col.key"
                                    @mouseleave="hoveredCol = null"
                                >
                                    <slot
                                        name="cell"
                                        :row="row"
                                        :col="col"
                                        :value="getCellValue(row, col.key)"
                                        :toggle="() => toggleCell(row, col.key)"
                                    >
                                        <button
                                            type="button"
                                            class="hcis-matrix-cell-pill"
                                            :class="[
                                                getCellValue(row, col.key) ? 'is-granted' : 'is-denied',
                                                { 'is-readonly': readonly }
                                            ]"
                                            :disabled="readonly"
                                            :title="getCellValue(row, col.key) ? `${col.label}: Disetujui (Granted)` : `${col.label}: Tidak Ada Akses (Denied)`"
                                            @click="toggleCell(row, col.key)"
                                        >
                                            <span class="hcis-matrix-cell-led" />
                                            <HcisIcon
                                                :name="getCellValue(row, col.key) ? 'check-lg' : 'dash-lg'"
                                                size="2xs"
                                            />
                                            <span class="hcis-matrix-cell-text">
                                                {{ getCellValue(row, col.key) ? 'Akses' : 'Tidak' }}
                                            </span>
                                        </button>
                                    </slot>
                                </td>
                            </tr>
                        </template>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisCheckbox from './HcisCheckbox.vue';
import HcisBadge from './HcisBadge.vue';
import HcisField from './HcisField.vue';

defineOptions({ name: 'HcisMatrixGrid' });

const props = defineProps({
    title: { type: String, default: 'Matriks Otorisasi & Hak Akses' },
    subtitle: { type: String, default: 'Pengaturan Matriks Otorisasi Hak Akses Modul' },
    icon: { type: String, default: 'grid-3x3-gap' },
    columns: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    groupBy: { type: String, default: '' },
    variant: {
        type: String,
        default: 'card',
        validator: (v) => ['card', 'flat', 'glass'].includes(v),
    },
    showMasterCheckboxes: { type: Boolean, default: true },
    searchable: { type: Boolean, default: true },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits([
    'cell-change',
    'row-select-all',
    'col-select-all',
    'group-select-all',
    'global-select-all',
]);

const rowSearchQuery = ref('');
const collapsedGroups = ref(new Set());
const hoveredRowId = ref(null);
const hoveredCol = ref(null);

const filteredRows = computed(() => {
    if (!rowSearchQuery.value.trim()) return props.rows;
    const q = rowSearchQuery.value.toLowerCase().trim();
    return props.rows.filter((row) => {
        const title = (row.title || row.label || '').toLowerCase();
        const code = (row.code || row.subtitle || '').toLowerCase();
        const group = (row.group || '').toLowerCase();
        return title.includes(q) || code.includes(q) || group.includes(q);
    });
});

const groupedData = computed(() => {
    if (!props.groupBy) {
        return { __ungrouped__: filteredRows.value };
    }
    const map = {};
    filteredRows.value.forEach((row) => {
        const group = row[props.groupBy] || 'Lainnya';
        if (!map[group]) map[group] = [];
        map[group].push(row);
    });
    return map;
});

const stats = computed(() => {
    let grantedCount = 0;
    const totalCells = props.rows.length * props.columns.length;
    props.rows.forEach((row) => {
        props.columns.forEach((col) => {
            if (getCellValue(row, col.key)) grantedCount++;
        });
    });
    const percentage = totalCells > 0 ? Math.round((grantedCount / totalCells) * 100) : 0;
    return { grantedCount, totalCells, percentage };
});

function getCellValue(row, colKey) {
    if (row.permissions && row.permissions[colKey] !== undefined) {
        return Boolean(row.permissions[colKey]);
    }
    return Boolean(row[colKey]);
}

function toggleCell(row, colKey) {
    if (props.readonly) return;
    const current = getCellValue(row, colKey);
    const newValue = !current;
    if (row.permissions) {
        row.permissions[colKey] = newValue;
    } else {
        row[colKey] = newValue;
    }
    emit('cell-change', { row, colKey, value: newValue });
}

function isRowAllSelected(row) {
    if (!props.columns.length) return false;
    return props.columns.every((col) => getCellValue(row, col.key));
}

function toggleRowSelectAll(row, value) {
    if (props.readonly) return;
    props.columns.forEach((col) => {
        if (row.permissions) row.permissions[col.key] = value;
        else row[col.key] = value;
    });
    emit('row-select-all', { row, value });
}

function isColAllSelected(colKey) {
    if (!props.rows.length) return false;
    return props.rows.every((row) => getCellValue(row, colKey));
}

function toggleColSelectAll(colKey, value) {
    if (props.readonly) return;
    props.rows.forEach((row) => {
        if (row.permissions) row.permissions[colKey] = value;
        else row[colKey] = value;
    });
    emit('col-select-all', { colKey, value });
}

function toggleGroup(groupName) {
    if (collapsedGroups.value.has(groupName)) {
        collapsedGroups.value.delete(groupName);
    } else {
        collapsedGroups.value.add(groupName);
    }
}

function toggleGroupSelectAll(groupRows, value) {
    if (props.readonly) return;
    groupRows.forEach((row) => {
        props.columns.forEach((col) => {
            if (row.permissions) row.permissions[col.key] = value;
            else row[col.key] = value;
        });
    });
    emit('group-select-all', { groupRows, value });
}

const isGlobalAllSelected = computed(() => {
    if (!props.rows.length || !props.columns.length) return false;
    return props.rows.every((row) => isRowAllSelected(row));
});

function toggleGlobalSelectAll(value) {
    if (props.readonly) return;
    props.rows.forEach((row) => {
        props.columns.forEach((col) => {
            if (row.permissions) row.permissions[col.key] = value;
            else row[col.key] = value;
        });
    });
    emit('global-select-all', value);
}
</script>

<style scoped>
.hcis-matrix-grid-container {
    width: 100%;
    padding: 1.25rem;
    border-radius: var(--hcis-radius-xl);
    font-family: var(--hcis-font-body, inherit);
    box-sizing: border-box;
}

.hcis-matrix-grid-container--card {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    box-shadow: 0 2px 8px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-matrix-grid-container--glass {
    background: rgb(var(--hcis-white-rgb) / 0.85);
    backdrop-filter: blur(var(--hcis-glass-blur));
    border: 1px solid rgb(var(--hcis-slate-200-rgb) / 0.8);
}

.hcis-matrix-grid-container--flat {
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

/* Header & Stats */
.hcis-matrix-grid__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
    flex-wrap: wrap;
}

.hcis-matrix-grid__header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.hcis-matrix-grid__icon-glow {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 10px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgb(var(--hcis-brand-600-rgb) / 0.15);
}

.hcis-matrix-grid__title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    margin: 0;
}

.hcis-matrix-grid__subtitle {
    font-size: 0.725rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.hcis-matrix-grid__header-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* Stats Pill Bar */
.hcis-matrix-stats-pill {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 0.375rem 0.75rem;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 10px;
    min-width: 11rem;
}

.hcis-matrix-stats-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.hcis-matrix-stats-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--hcis-slate-500);
}

.hcis-matrix-stats-val {
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-brand-700, var(--hcis-brand-700));
}

.hcis-matrix-stats-bar {
    width: 100%;
    height: 4px;
    background: var(--hcis-slate-200);
    border-radius: 9999px;
    overflow: hidden;
}

.hcis-matrix-stats-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--hcis-success-500) 0%, var(--hcis-success-600) 100%);
    border-radius: 9999px;
    transition: width 300ms ease;
}

.hcis-matrix-search {
    width: 13rem;
}

/* Table Wrap */
.hcis-matrix-table-wrap {
    width: 100%;
    max-height: 28rem;
    overflow: auto;
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 12px;
    box-shadow: inset 0 0 0 1px rgb(var(--hcis-white-rgb) / 0.5);
}

.hcis-matrix-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: var(--hcis-white);
    font-size: 0.8rem;
}

/* Sticky Headers & Glassmorphic Sticky Left Column */
.hcis-matrix-th {
    position: sticky;
    top: 0;
    padding: 0.75rem 1rem;
    background: rgb(var(--hcis-slate-50-rgb) / 0.95);
    backdrop-filter: blur(var(--hcis-glass-blur-sm));
    border-bottom: 2px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    z-index: 10;
    transition: background 150ms ease;
}

.hcis-matrix-th.is-hover-col {
    background: rgb(var(--hcis-brand-50-rgb) / 0.95);
}

.hcis-matrix-th--first {
    left: 0;
    z-index: 20;
    text-align: left;
    min-width: 16rem;
    border-right: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
}

.hcis-matrix-th-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 9999px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-700, var(--hcis-brand-700));
}

.hcis-matrix-col-title {
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--hcis-slate-700);
    white-space: nowrap;
}

.hcis-matrix-col-subtext {
    font-size: 0.625rem;
    color: var(--hcis-slate-400);
}

/* Group Header Row */
.hcis-matrix-group-row {
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    position: sticky;
    top: 2.75rem;
    z-index: 15;
}

.hcis-matrix-group-td {
    padding: 0.5rem 1rem;
    border-bottom: 1px solid var(--hcis-brand-200, var(--hcis-brand-200));
}

.hcis-matrix-group-toggle {
    border: none;
    background: transparent;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 2px 6px;
    border-radius: 6px;
    transition: background 150ms ease;
}

.hcis-matrix-group-toggle:hover {
    background: rgb(var(--hcis-brand-500-rgb) / 0.1);
}

.hcis-matrix-group-title {
    font-size: 0.775rem;
    font-weight: 800;
    color: var(--hcis-brand-800, var(--hcis-brand-800));
}

.hcis-matrix-group-count {
    font-size: 0.7rem;
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.hcis-matrix-group-action-btn {
    border: none;
    background: rgb(var(--hcis-white-rgb) / 0.8);
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    transition-property: var(--hcis-transition-props);
    transition-duration: 120ms;
    transition-timing-function: ease;
}

.hcis-matrix-group-action-btn:hover {
    background: var(--hcis-white);
    box-shadow: 0 1px 3px rgb(var(--hcis-black-rgb) / 0.1);
}

/* Rows & Crosshair Axis Highlight */
.hcis-matrix-row {
    transition: background 150ms ease;
}

.hcis-matrix-row:hover,
.hcis-matrix-row.is-hover-row {
    background: rgb(var(--hcis-slate-50-rgb) / 0.95);
}

.hcis-matrix-td {
    padding: 0.625rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
    transition: background 150ms ease;
}

.hcis-matrix-td.is-hover-col {
    background: rgb(var(--hcis-brand-50-rgb) / 0.4);
}

.hcis-matrix-row:hover .hcis-matrix-td.is-hover-col {
    background: rgb(var(--hcis-brand-100-rgb) / 0.7);
}

.hcis-matrix-td--first {
    position: sticky;
    left: 0;
    background: var(--hcis-white);
    z-index: 5;
    text-align: left;
    border-right: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
}

.hcis-matrix-row:hover .hcis-matrix-td--first {
    background: var(--hcis-slate-50);
}

.hcis-matrix-row-title {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
}

.hcis-matrix-row-subtext {
    font-size: 0.675rem;
    color: var(--hcis-slate-500);
}

/* High-Tech LED Pill Permission Cells */
.hcis-matrix-cell-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 3px 10px;
    border-radius: 9999px;
    font-size: 0.675rem;
    font-weight: 700;
    border: 1px solid transparent;
    cursor: pointer;
    outline: none;
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
    user-select: none;
}

.hcis-matrix-cell-pill.is-granted {
    background: var(--hcis-success-50);
    color: var(--hcis-success-700);
    border-color: var(--hcis-success-200);
    box-shadow: 0 1px 3px rgb(var(--hcis-success-500-rgb) / 0.12);
}

.hcis-matrix-cell-pill.is-granted:hover {
    background: var(--hcis-success-100);
    transform: scale(1.08);
    box-shadow: 0 4px 10px rgb(var(--hcis-success-500-rgb) / 0.22);
}

.hcis-matrix-cell-pill.is-denied {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-400);
    border-color: var(--hcis-slate-200);
}

.hcis-matrix-cell-pill.is-denied:hover {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    transform: scale(1.08);
}

.hcis-matrix-cell-led {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    flex-shrink: 0;
    transition: transform 180ms ease;
}

.hcis-matrix-cell-pill.is-granted .hcis-matrix-cell-led {
    box-shadow: 0 0 8px var(--hcis-success-500);
}

.hcis-matrix-cell-pill.is-readonly {
    cursor: default;
    pointer-events: none;
    opacity: 0.85;
}
</style>
