<template>
    <div
        class="hcis-table-container"
        :class="[
            'hcis-table-container--' + variant,
            'hcis-table-container--sentiment-' + sentiment,
            'hcis-table-container--size-' + effectiveSize,
            'hcis-table-container--responsive-' + responsive,
            {
                'hcis-table-container--bordered': bordered,
                'hcis-table-container--sticky': stickyHeader,
                'hcis-table-container--sticky-first-col': stickyFirstCol,
                'hcis-table-container--glow': glow,
                'has-selection': selectedKeys.length > 0,
            },
        ]"
    >
        <!-- Floating Bulk Selection Action Banner -->
        <Transition name="hcis-table-banner">
            <div v-if="selectable && selectedKeys.length > 0" class="hcis-table__selection-banner">
                <div class="hcis-table__selection-info">
                    <span class="hcis-table__selection-badge">{{ selectedKeys.length }}</span>
                    <span class="hcis-table__selection-text">baris data terpilih</span>
                </div>
                <div class="hcis-table__selection-actions">
                    <slot name="bulk-actions" :selected-keys="selectedKeys" :clear="clearSelection">
                        <button type="button" class="hcis-table__selection-clear-btn" @click="clearSelection">
                            <HcisIcon name="x-circle" size="xs" />
                            <span>Batal Pilih</span>
                        </button>
                    </slot>
                </div>
            </div>
        </Transition>

        <div class="hcis-table-wrap" :class="{ 'is-loading-overlay': loading && sortedRows.length }">
            <!-- Non-destructive Frosted Glass Floating Loading Badge -->
            <transition name="hcis-loading-pop">
                <div v-if="loading && sortedRows.length" class="hcis-table__loading-badge" aria-live="polite">
                    <div class="hcis-table__spinner-box" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="hcis-table__spinner-svg">
                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                                fill="none"
                                stroke-width="2.5"
                                class="hcis-table__spinner-track"
                            />
                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                                fill="none"
                                stroke-width="2.5"
                                class="hcis-table__spinner-head"
                            />
                        </svg>
                    </div>
                    <span class="hcis-table__loading-label">Menyinkronkan data…</span>
                </div>
            </transition>

            <table
                class="hcis-table"
                :class="[
                    'hcis-table--size-' + effectiveSize,
                    {
                        'hcis-table--dense': effectiveSize === 'sm',
                        'hcis-table--striped': striped,
                        'hcis-table--hoverable': hoverable,
                        'hcis-table--interactive': interactive,
                        'hcis-table--sticky-first-col': stickyFirstCol,
                    },
                ]"
            >
                <thead
                    :class="[
                        'hcis-table__thead',
                        {
                            'hcis-table__thead--gradient': headerGradient,
                            'hcis-table__thead--pattern': headerPattern !== false,
                            ['hcis-table__thead--pattern-' +
                            (typeof headerPattern === 'string' ? headerPattern : 'dots')]: headerPattern !== false,
                        },
                    ]"
                >
                    <tr>
                        <!-- Select All Checkbox -->
                        <th v-if="selectable" class="hcis-table__check-col" style="width: 3.25rem; text-align: center">
                            <HcisCheckbox
                                :model-value="isAllSelected"
                                :indeterminate="isIndeterminate"
                                :disabled="loading || !barisBolehPilih.length"
                                aria-label="Pilih semua baris"
                                @update:model-value="toggleSelectAll"
                            />
                        </th>

                        <!-- Column Headers -->
                        <th
                            v-for="(col, colIdx) in columns"
                            :key="col.key"
                            :draggable="isDraggableColumns"
                            :class="[
                                col.class,
                                {
                                    'hcis-table__th--sortable': col.sortable,
                                    'hcis-table__th--sorted': currentSortKey === col.key,
                                    'hcis-table__th--draggable': isDraggableColumns,
                                    'hcis-table__th--dragging': draggedColIndex === colIdx,
                                    'hcis-table__th--drag-over': dragOverColIndex === colIdx,
                                },
                            ]"
                            :style="{ textAlign: col.align || 'left', width: col.width }"
                            @dragstart="onColDragStart($event, col, colIdx)"
                            @dragover.prevent="onColDragOver($event, col, colIdx)"
                            @dragenter.prevent="dragOverColIndex = colIdx"
                            @dragleave="onColDragLeave(colIdx)"
                            @drop="onColDrop($event, col, colIdx)"
                            @dragend="onColDragEnd"
                            @click="col.sortable ? handleSort(col.key) : null"
                        >
                            <div class="hcis-table__th-content" :style="{ justifyContent: flexAlign(col.align) }">
                                <span
                                    v-if="isDraggableColumns"
                                    class="hcis-table__col-drag-handle"
                                    title="Tarik untuk geser urutan kolom"
                                >
                                    <HcisIcon name="grip-vertical" size="xs" />
                                </span>
                                <slot :name="`header-${col.key}`" :column="col">
                                    <span>{{ col.label }}</span>
                                </slot>
                                <span v-if="col.sortable" class="hcis-table__sort-icon">
                                    <HcisIcon
                                        v-if="currentSortKey === col.key && currentSortOrder === 'asc'"
                                        name="arrow-up-short"
                                        size="sm"
                                    />
                                    <HcisIcon
                                        v-else-if="currentSortKey === col.key && currentSortOrder === 'desc'"
                                        name="arrow-down-short"
                                        size="sm"
                                    />
                                    <HcisIcon v-else name="chevron-expand" size="xs" class="hcis-table__sort-neutral" />
                                </span>
                            </div>
                        </th>
                    </tr>
                </thead>

                <!-- Initial Loading State (Clean proportional Skeleton Rows) -->
                <tbody v-if="loading && !sortedRows.length">
                    <tr v-for="n in loadingRowsCount" :key="n" class="hcis-table__skeleton-row">
                        <td v-if="selectable" style="text-align: center">
                            <HcisSkeleton width="1.125rem" height="1.125rem" rounded="sm" style="margin: 0 auto" />
                        </td>
                        <td
                            v-for="(col, colIndex) in columns"
                            :key="col.key"
                            :data-label="col.label"
                            :style="{ textAlign: col.align || 'left' }"
                        >
                            <HcisSkeleton
                                height="1rem"
                                :width="col.skeletonWidth || `${65 + ((n + colIndex) % 4) * 8}%`"
                                rounded="sm"
                            />
                        </td>
                    </tr>
                </tbody>

                <!-- Table Rows -->
                <tbody v-else-if="sortedRows.length" :class="{ 'hcis-table__tbody--dimmed': loading }">
                    <tr
                        v-for="(row, index) in sortedRows"
                        :key="getRowKeyValue(row, index)"
                        :class="[
                            row.rowClass,
                            {
                                'is-selected': isRowSelected(row, index),
                                'is-interactive': interactive || $attrs.onRowClick,
                            },
                        ]"
                        :tabindex="interactive || $attrs.onRowClick ? 0 : null"
                        @click="onRowClick($event, row, index)"
                        @keydown.enter="onRowClick($event, row, index)"
                    >
                        <!-- Row Checkbox -->
                        <td
                            v-if="selectable"
                            class="hcis-table__check-col"
                            style="text-align: center"
                            data-label="Pilih"
                            @click.stop
                        >
                            <HcisCheckbox
                                :model-value="isRowSelected(row, index)"
                                :disabled="!bolehPilihBaris(row, index)"
                                aria-label="Pilih baris ini"
                                @update:model-value="() => toggleSelectRow(row, index)"
                            />
                        </td>

                        <!-- Cell Content -->
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            :class="col.cellClass"
                            :data-label="col.label"
                            :style="{ textAlign: col.align || 'left' }"
                        >
                            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]" :index="index">
                                {{ row[col.key] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Empty State -->
            <Transition name="hcis-fade">
                <div v-if="!loading && !sortedRows.length" class="hcis-table__empty-wrap">
                    <slot name="empty">
                        <HcisEmptyState :title="emptyTitle" :description="emptyDescription" :icon="emptyIcon" />
                    </slot>
                </div>
            </Transition>
        </div>

        <!-- Footer / Pagination Slot -->
        <div v-if="$slots.footer" class="hcis-table__footer">
            <slot name="footer" />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import HcisSkeleton from './HcisSkeleton.vue';
import HcisEmptyState from './HcisEmptyState.vue';
import HcisCheckbox from './HcisCheckbox.vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisTable', inheritAttrs: false });

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    loading: { type: Boolean, default: false },
    loadingRowsCount: { type: Number, default: 4 },
    variant: {
        type: String,
        default: 'card',
        validator: (v) => ['card', 'glass', 'flat', 'obsidian'].includes(v),
    },
    sentiment: {
        type: String,
        default: 'primary',
        validator: (v) =>
            [
                'primary',
                'brand',
                'purple',
                'violet',
                'teal',
                'cyan',
                'danger',
                'warning',
                'success',
                'neutral',
                'obsidian',
            ].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg', 'xl', '2xl', '3xl', 'full'].includes(v),
    },
    responsive: {
        type: String,
        default: 'scroll',
        validator: (v) => ['scroll', 'cards', 'auto'].includes(v),
    },
    stickyFirstCol: { type: Boolean, default: false },
    headerGradient: { type: Boolean, default: true },
    headerPattern: {
        type: [Boolean, String],
        default: 'dots',
        validator: (v) => typeof v === 'boolean' || ['dots', 'grid', 'mesh'].includes(v),
    },
    glow: { type: Boolean, default: false },
    dense: { type: Boolean, default: false },
    striped: { type: Boolean, default: false },
    hoverable: { type: Boolean, default: true },
    bordered: { type: Boolean, default: false },
    interactive: { type: Boolean, default: false },
    selectable: { type: Boolean, default: false },
    /**
     * Penentu per-baris: `(row, index) => boolean`.
     *
     * `selectable` hanya menyalakan kolom centangnya; tanpa ini SETIAP baris
     * bisa dipilih, termasuk yang tindakan massalnya mustahil dikerjakan —
     * mis. permit yang aksesnya sudah dicabut. Server memang menolaknya diam,
     * jadi yang terjadi bukan kerusakan data melainkan hal yang lebih halus:
     * pemakainya mencentang sepuluh baris, menekan tombol, lalu dibalas
     * "3 berhasil" tanpa penjelasan ke mana tujuh sisanya.
     *
     * null = semua baris boleh dipilih (perilaku lama, tidak berubah).
     */
    rowSelectable: { type: Function, default: null },
    selectedKeys: { type: Array, default: () => [] },
    stickyHeader: { type: Boolean, default: false },
    sortKey: { type: String, default: '' },
    sortOrder: { type: String, default: 'asc' },
    remoteSort: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'Belum ada data' },
    emptyDescription: { type: String, default: 'Data akan muncul di sini setelah tersedia.' },
    emptyIcon: { type: String, default: 'inbox' },
    draggableColumns: { type: Boolean, default: false },
    draggable: { type: Boolean, default: false },
    reorderableColumns: { type: Boolean, default: false },
});

const emit = defineEmits([
    'update:selectedKeys',
    'update:sortKey',
    'update:sortOrder',
    'update:columns',
    'columnReorder',
    'sort',
    'rowClick',
    'selectAll',
]);

const isDraggableColumns = computed(() =>
    Boolean(props.draggableColumns || props.draggable || props.reorderableColumns),
);
const draggedColIndex = ref(null);
const dragOverColIndex = ref(null);

function onColDragStart(e, col, index) {
    if (!isDraggableColumns.value) return;
    draggedColIndex.value = index;
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(index));
    }
}

function onColDragOver(e, col, index) {
    if (!isDraggableColumns.value || draggedColIndex.value === null) return;
    dragOverColIndex.value = index;
}

function onColDragLeave(index) {
    if (dragOverColIndex.value === index) {
        dragOverColIndex.value = null;
    }
}

function onColDrop(e, targetCol, targetIndex) {
    e.preventDefault();
    if (!isDraggableColumns.value || draggedColIndex.value === null || draggedColIndex.value === targetIndex) {
        draggedColIndex.value = null;
        dragOverColIndex.value = null;
        return;
    }

    const fromIdx = draggedColIndex.value;
    const toIdx = targetIndex;
    const newCols = [...props.columns];
    const [moved] = newCols.splice(fromIdx, 1);
    newCols.splice(toIdx, 0, moved);

    emit('update:columns', newCols);
    emit('columnReorder', {
        fromIndex: fromIdx,
        toIndex: toIdx,
        movedColumn: moved,
        targetColumn: targetCol,
        columns: newCols,
    });

    draggedColIndex.value = null;
    dragOverColIndex.value = null;
}

function onColDragEnd() {
    draggedColIndex.value = null;
    dragOverColIndex.value = null;
}

const currentSortKey = ref(props.sortKey);
const currentSortOrder = ref(props.sortOrder);

watch(
    () => props.sortKey,
    (k) => {
        currentSortKey.value = k;
    },
);
watch(
    () => props.sortOrder,
    (o) => {
        currentSortOrder.value = o;
    },
);

const effectiveSize = computed(() => {
    if (props.dense) return 'sm';
    return props.size || 'md';
});

function getRowKeyValue(row, index) {
    if (typeof row === 'object' && row !== null && props.rowKey in row) {
        return row[props.rowKey];
    }
    return index;
}

function flexAlign(align) {
    if (align === 'center') return 'center';
    if (align === 'right') return 'flex-end';
    return 'flex-start';
}

/* Sorting logic */
function handleSort(key) {
    if (currentSortKey.value === key) {
        currentSortOrder.value = currentSortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        currentSortKey.value = key;
        currentSortOrder.value = 'asc';
    }
    emit('update:sortKey', currentSortKey.value);
    emit('update:sortOrder', currentSortOrder.value);
    emit('sort', { key: currentSortKey.value, order: currentSortOrder.value });
}

const sortedRows = computed(() => {
    if (props.remoteSort) return props.rows;
    if (!currentSortKey.value) return props.rows;
    const key = currentSortKey.value;
    const order = currentSortOrder.value === 'desc' ? -1 : 1;

    return [...props.rows].sort((a, b) => {
        const valA = a[key] ?? '';
        const valB = b[key] ?? '';

        if (typeof valA === 'number' && typeof valB === 'number') {
            return (valA - valB) * order;
        }
        return String(valA).localeCompare(String(valB), 'id', { numeric: true }) * order;
    });
});

/* Selection logic */
function isRowSelected(row, index) {
    const key = getRowKeyValue(row, index);
    return props.selectedKeys.includes(key);
}

function bolehPilihBaris(row, index) {
    if (typeof props.rowSelectable !== 'function') return true;

    return Boolean(props.rowSelectable(row, index));
}

/** Baris yang benar-benar boleh dipilih pada halaman ini. */
const barisBolehPilih = computed(() =>
    props.rows.map((row, index) => ({ row, index })).filter(({ row, index }) => bolehPilihBaris(row, index)),
);

const isAllSelected = computed(() => {
    // Diukur terhadap baris yang BOLEH dipilih, bukan seluruh baris. Tanpa itu
    // "pilih semua" tidak pernah tampak tercentang penuh pada halaman yang
    // memuat satu saja baris terkunci — dan pemakainya menekannya berulang kali
    // menyangka ada yang terlewat.
    if (!barisBolehPilih.value.length) return false;

    return barisBolehPilih.value.every(({ row, index }) => isRowSelected(row, index));
});

const isIndeterminate = computed(() => {
    if (!barisBolehPilih.value.length) return false;
    const count = barisBolehPilih.value.filter(({ row, index }) => isRowSelected(row, index)).length;

    return count > 0 && count < barisBolehPilih.value.length;
});

function toggleSelectAll(val) {
    let checked = false;
    if (typeof val === 'boolean') {
        checked = val;
    } else if (val && typeof val === 'object' && 'target' in val) {
        checked = Boolean(val.target.checked);
    } else {
        checked = !isAllSelected.value;
    }

    let newSelected = [];
    // Hanya baris yang boleh dipilih yang ikut tersapu "pilih semua".
    const allRowKeys = barisBolehPilih.value.map(({ row, index }) => getRowKeyValue(row, index));

    if (checked) {
        newSelected = Array.from(new Set([...props.selectedKeys, ...allRowKeys]));
    } else {
        const pageKeys = new Set(allRowKeys);
        newSelected = props.selectedKeys.filter((k) => !pageKeys.has(k));
    }

    emit('update:selectedKeys', newSelected);
    emit('selectAll', { checked, selectedKeys: newSelected });
}

function toggleSelectRow(row, index) {
    // Penjaga kedua. Centangnya sudah dinonaktifkan di template, tetapi
    // pemanggilan lewat ref atau keyboard tidak melewati template itu.
    if (!bolehPilihBaris(row, index)) return;

    const key = getRowKeyValue(row, index);
    let newSelected = [...props.selectedKeys];
    if (newSelected.includes(key)) {
        newSelected = newSelected.filter((k) => k !== key);
    } else {
        newSelected.push(key);
    }
    emit('update:selectedKeys', newSelected);
}

function clearSelection() {
    emit('update:selectedKeys', []);
}

function onRowClick(e, row, index) {
    emit('rowClick', { event: e, row, index });
}
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   HCIS TABLE — LUXURY CRYSTAL GRID ARCHITECTURE
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-table-container {
    position: relative;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    border-radius: var(--hcis-radius-xl, 16px);
    overflow: hidden;
    font-family: var(--hcis-font-body, inherit);
    box-sizing: border-box;
    transition:
        box-shadow 260ms ease,
        border-color 260ms ease,
        transform 200ms ease;
}

/* ── 1. CONTAINER VARIANTS (Card, Glass, Flat, Obsidian) ───────────────── */

/* Variant: Card (Solid Executive Crystal Surface) */
.hcis-table-container--card {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid rgb(var(--hcis-slate-200-rgb, 203 213 225) / 0.9);
    box-shadow:
        0 0 0 1px rgba(15, 23, 42, 0.03),
        0 4px 16px -2px rgba(15, 23, 42, 0.05),
        0 12px 28px -6px rgba(15, 23, 42, 0.07),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

/* Variant: Glass (Frosted Acrylic Crystal) */
.hcis-table-container--glass {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 250, 252, 0.82) 100%);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.9);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.7) inset,
        0 8px 24px -4px rgba(15, 23, 42, 0.08),
        0 16px 36px -8px rgba(15, 23, 42, 0.06);
}

/* Variant: Flat (Seamless Flush Grid) */
.hcis-table-container--flat {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

/* Variant: Obsidian (Cosmic Midnight Dark Mode) */
.hcis-table-container--obsidian,
.hcis-table-container--sentiment-obsidian {
    background: linear-gradient(180deg, var(--hcis-slate-800) 0%, var(--hcis-slate-900) 100%) !important;
    color: var(--hcis-slate-50) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.06) inset,
        0 12px 30px -5px rgba(0, 0, 0, 0.5),
        0 24px 50px -10px rgba(0, 0, 0, 0.4) !important;
}

/* ── 2. LUMINOUS AMBIENT GLOW GRADIENT MATRIX ────────────────────────── */

.hcis-table-container--glow.hcis-table-container--sentiment-primary,
.hcis-table-container--glow.hcis-table-container--sentiment-brand {
    border-color: rgba(199, 210, 254, 0.9);
    box-shadow:
        0 0 0 1px rgba(99, 102, 241, 0.18),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(99, 102, 241, 0.16),
        0 0 80px 10px rgba(79, 70, 229, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-table-container--glow.hcis-table-container--sentiment-purple,
.hcis-table-container--glow.hcis-table-container--sentiment-violet {
    border-color: rgba(221, 214, 254, 0.9);
    box-shadow:
        0 0 0 1px rgba(168, 85, 247, 0.18),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(168, 85, 247, 0.16),
        0 0 80px 10px rgba(124, 58, 237, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-table-container--glow.hcis-table-container--sentiment-teal,
.hcis-table-container--glow.hcis-table-container--sentiment-cyan {
    border-color: rgba(178, 242, 233, 0.9);
    box-shadow:
        0 0 0 1px rgba(20, 184, 166, 0.18),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(20, 184, 166, 0.16),
        0 0 80px 10px rgba(13, 148, 136, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-table-container--glow.hcis-table-container--sentiment-danger {
    border-color: rgba(254, 202, 202, 0.9);
    box-shadow:
        0 0 0 1px rgba(239, 68, 68, 0.18),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(239, 68, 68, 0.16),
        0 0 80px 10px rgba(220, 38, 38, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-table-container--glow.hcis-table-container--sentiment-warning {
    border-color: rgba(253, 230, 138, 0.9);
    box-shadow:
        0 0 0 1px rgba(245, 158, 11, 0.18),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(245, 158, 11, 0.16),
        0 0 80px 10px rgba(217, 119, 6, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-table-container--glow.hcis-table-container--sentiment-success {
    border-color: rgba(187, 247, 208, 0.9);
    box-shadow:
        0 0 0 1px rgba(16, 185, 129, 0.18),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(16, 185, 129, 0.16),
        0 0 80px 10px rgba(5, 150, 105, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-table-container--glow.hcis-table-container--sentiment-obsidian,
.hcis-table-container--glow.hcis-table-container--obsidian {
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.08) inset,
        0 12px 30px -5px rgba(0, 0, 0, 0.5),
        0 0 35px 0 rgba(99, 102, 241, 0.25),
        0 0 80px 10px rgba(124, 58, 237, 0.12) !important;
}

/* ── 3. FLOATING BULK SELECTION BANNER ────────────────────────────────── */

.hcis-table__selection-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.625rem 1.125rem;
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.98) 0%, rgba(224, 231, 255, 0.95) 100%);
    border-bottom: 1px solid rgba(199, 210, 254, 0.9);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.8),
        0 2px 8px rgba(79, 70, 229, 0.08);
    position: relative;
    z-index: 20;
}

.hcis-table-container--obsidian .hcis-table__selection-banner {
    background: linear-gradient(135deg, var(--hcis-indigo-900) 0%, var(--hcis-indigo-950) 100%);
    border-bottom-color: rgba(99, 102, 241, 0.4);
}

.hcis-table__selection-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-indigo-800);
}

.hcis-table-container--obsidian .hcis-table__selection-info {
    color: var(--hcis-indigo-100);
}

.hcis-table__selection-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.375rem;
    height: 1.375rem;
    padding: 0 0.375rem;
    border-radius: 9999px;
    background: var(--hcis-indigo-600);
    color: var(--hcis-white);
    font-size: 0.75rem;
    font-weight: 800;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35);
}

.hcis-table__selection-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.hcis-table__selection-clear-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3125rem;
    padding: 0.25rem 0.625rem;
    border-radius: 7px;
    border: 1px solid rgba(199, 210, 254, 0.8);
    background: var(--hcis-white);
    color: var(--hcis-indigo-700);
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-table__selection-clear-btn:hover {
    background: var(--hcis-red-50);
    border-color: var(--hcis-red-300);
    color: var(--hcis-red-700);
}

/* Banner Animation */
.hcis-table-banner-enter-active,
.hcis-table-banner-leave-active {
    transition-property: var(--hcis-transition-props);
    transition-duration: 220ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}
.hcis-table-banner-enter-from,
.hcis-table-banner-leave-to {
    opacity: 0;
    transform: translateY(-100%);
}

/* ── 4. TABLE WRAPPER & SCROLLBAR ────────────────────────────────────── */

.hcis-table-wrap {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(203, 213, 225, 0.8) transparent;
    -webkit-overflow-scrolling: touch;
    display: block;
    position: relative;
}

.hcis-table-wrap::-webkit-scrollbar {
    height: 6px;
}
.hcis-table-wrap::-webkit-scrollbar-thumb {
    background: rgba(203, 213, 225, 0.8);
    border-radius: 9999px;
}

/* ══ PREMIUM TABLE LOADING ANIMATIONS & LASER BAR ══ */
.hcis-table__laser-bar {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(99, 102, 241, 0.3) 15%,
        var(--hcis-indigo-600) 50%,
        var(--hcis-indigo-400) 70%,
        #c084fc 85%,
        transparent 100%
    );
    background-size: 200% 100%;
    animation: hcis-laser-glide 1.1s cubic-bezier(0.4, 0, 0.2, 1) infinite;
    z-index: 40;
    box-shadow:
        0 0 12px rgba(99, 102, 241, 0.7),
        0 0 20px rgba(168, 85, 247, 0.4);
}

@keyframes hcis-laser-glide {
    0% {
        background-position: 100% 0;
    }
    100% {
        background-position: -100% 0;
    }
}

.hcis-table__loading-badge {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 35;
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.45rem 1rem 0.45rem 0.85rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(199, 210, 254, 0.85);
    box-shadow:
        0 14px 34px -4px rgba(15, 23, 42, 0.14),
        0 0 0 1px rgba(99, 102, 241, 0.12),
        0 4px 12px rgba(99, 102, 241, 0.14);
    pointer-events: none;
    user-select: none;
}

.hcis-table__spinner-box {
    width: 1.1rem;
    height: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.hcis-table__spinner-svg {
    width: 100%;
    height: 100%;
    animation: hcis-spin 0.85s linear infinite;
}

.hcis-table__spinner-track {
    stroke: rgba(199, 210, 254, 0.6);
}

.hcis-table__spinner-head {
    stroke: var(--hcis-indigo-600);
    stroke-linecap: round;
    stroke-dasharray: 28 60;
}

@keyframes hcis-spin {
    100% {
        transform: rotate(360deg);
    }
}

.hcis-table__loading-label {
    font-size: 0.785rem;
    font-weight: 700;
    color: var(--hcis-indigo-700);
    letter-spacing: 0.01em;
    white-space: nowrap;
}

.hcis-table__tbody--dimmed {
    opacity: 0.4;
    filter: blur(0.5px);
    pointer-events: none;
    transition:
        opacity 180ms ease,
        filter 180ms ease;
}

/* Laser & Loading Transitions */
.hcis-laser-fade-enter-active,
.hcis-laser-fade-leave-active {
    transition: opacity 200ms ease;
}
.hcis-laser-fade-enter-from,
.hcis-laser-fade-leave-to {
    opacity: 0;
}

.hcis-loading-pop-enter-active,
.hcis-loading-pop-leave-active {
    transition-property: var(--hcis-transition-props);
    transition-duration: 200ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}
.hcis-loading-pop-enter-from,
.hcis-loading-pop-leave-to {
    opacity: 0;
    transform: translate(-50%, -50%) scale(0.9);
}

/* ── 5. TABLE PRIMITIVE & GRID STRUCTURE ─────────────────────────────── */

.hcis-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.875rem;
    color: var(--hcis-text, var(--hcis-slate-700));
}

/* ── 6. HEADER THEAD & CELLS (GRADIENT & PATTERNS) ───────────────────── */

.hcis-table thead {
    background: linear-gradient(180deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
    position: relative;
}

/* Gradient Variants by Sentiment */
.hcis-table-container--sentiment-primary .hcis-table thead,
.hcis-table-container--sentiment-brand .hcis-table thead {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.98) 0%, rgba(238, 242, 255, 0.92) 100%);
}

.hcis-table-container--sentiment-purple .hcis-table thead,
.hcis-table-container--sentiment-violet .hcis-table thead {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.98) 0%, rgba(243, 232, 255, 0.92) 100%);
}

.hcis-table-container--sentiment-teal .hcis-table thead,
.hcis-table-container--sentiment-cyan .hcis-table thead {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.98) 0%, rgba(204, 251, 241, 0.92) 100%);
}

.hcis-table-container--sentiment-danger .hcis-table thead {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.98) 0%, rgba(254, 226, 226, 0.92) 100%);
}

.hcis-table-container--sentiment-warning .hcis-table thead {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.98) 0%, rgba(254, 243, 199, 0.92) 100%);
}

.hcis-table-container--sentiment-success .hcis-table thead {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.98) 0%, rgba(209, 250, 229, 0.92) 100%);
}

.hcis-table-container--glass .hcis-table thead {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, rgba(241, 245, 249, 0.8) 100%);
    backdrop-filter: blur(16px);
}

.hcis-table-container--obsidian .hcis-table thead,
.hcis-table-container--sentiment-obsidian .hcis-table thead {
    background: linear-gradient(180deg, var(--hcis-slate-800) 0%, var(--hcis-slate-900) 100%) !important;
}

/* ── Micro-Pattern Overlays on thead ─────────────────────────────────── */

/* 1. Dot-Matrix Pattern (Subtle Crystal Stippling with Ambient Gradient) */
.hcis-table__thead--pattern-dots {
    background-image:
        radial-gradient(ellipse at top left, rgba(99, 102, 241, 0.12) 0%, transparent 60%),
        radial-gradient(rgba(99, 102, 241, 0.22) 1.25px, transparent 1.25px),
        radial-gradient(rgba(148, 163, 184, 0.18) 1px, transparent 1px);
    background-size:
        100% 100%,
        16px 16px,
        8px 8px;
}

.hcis-table-container--sentiment-primary .hcis-table__thead--pattern-dots {
    background-image:
        radial-gradient(ellipse at top left, rgba(99, 102, 241, 0.15) 0%, transparent 60%),
        radial-gradient(rgba(99, 102, 241, 0.25) 1.25px, transparent 1.25px),
        radial-gradient(rgba(148, 163, 184, 0.18) 1px, transparent 1px);
    background-size:
        100% 100%,
        16px 16px,
        8px 8px;
}

.hcis-table-container--sentiment-purple .hcis-table__thead--pattern-dots {
    background-image:
        radial-gradient(ellipse at top left, rgba(168, 85, 247, 0.15) 0%, transparent 60%),
        radial-gradient(rgba(168, 85, 247, 0.25) 1.25px, transparent 1.25px),
        radial-gradient(rgba(148, 163, 184, 0.18) 1px, transparent 1px);
    background-size:
        100% 100%,
        16px 16px,
        8px 8px;
}

.hcis-table-container--sentiment-teal .hcis-table__thead--pattern-dots {
    background-image:
        radial-gradient(ellipse at top left, rgba(20, 184, 166, 0.15) 0%, transparent 60%),
        radial-gradient(rgba(20, 184, 166, 0.25) 1.25px, transparent 1.25px),
        radial-gradient(rgba(148, 163, 184, 0.18) 1px, transparent 1px);
    background-size:
        100% 100%,
        16px 16px,
        8px 8px;
}

.hcis-table-container--obsidian .hcis-table__thead--pattern-dots,
.hcis-table-container--sentiment-obsidian .hcis-table__thead--pattern-dots {
    background-image:
        radial-gradient(ellipse at top left, rgba(99, 102, 241, 0.2) 0%, transparent 60%),
        radial-gradient(rgba(129, 140, 248, 0.3) 1.25px, transparent 1.25px),
        radial-gradient(rgba(255, 255, 255, 0.15) 1px, transparent 1px) !important;
    background-size:
        100% 100%,
        16px 16px,
        8px 8px;
}

/* 2. Micro-Grid Geometric Pattern with Ambient Gradient */
.hcis-table__thead--pattern-grid {
    background-image:
        radial-gradient(ellipse at top right, rgba(99, 102, 241, 0.1) 0%, transparent 60%),
        linear-gradient(to right, rgba(99, 102, 241, 0.12) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(148, 163, 184, 0.1) 1px, transparent 1px);
    background-size:
        100% 100%,
        14px 14px,
        14px 14px;
}

.hcis-table-container--obsidian .hcis-table__thead--pattern-grid,
.hcis-table-container--sentiment-obsidian .hcis-table__thead--pattern-grid {
    background-image:
        radial-gradient(ellipse at top right, rgba(99, 102, 241, 0.2) 0%, transparent 60%),
        linear-gradient(to right, rgba(255, 255, 255, 0.08) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(255, 255, 255, 0.08) 1px, transparent 1px) !important;
    background-size:
        100% 100%,
        14px 14px,
        14px 14px;
}

/* 3. Subtle Isometric Mesh / Diagonal Pattern with Multi-Tone Gradient */
.hcis-table__thead--pattern-mesh {
    background-image:
        radial-gradient(ellipse at top left, rgba(99, 102, 241, 0.16) 0%, transparent 60%),
        radial-gradient(ellipse at bottom right, rgba(236, 72, 153, 0.1) 0%, transparent 60%),
        radial-gradient(rgba(148, 163, 184, 0.14) 1px, transparent 1px);
    background-size:
        100% 100%,
        100% 100%,
        10px 10px;
}

.hcis-table-container--obsidian .hcis-table__thead--pattern-mesh,
.hcis-table-container--sentiment-obsidian .hcis-table__thead--pattern-mesh {
    background-image:
        radial-gradient(ellipse at top left, rgba(99, 102, 241, 0.25) 0%, transparent 60%),
        radial-gradient(ellipse at bottom right, rgba(236, 72, 153, 0.18) 0%, transparent 60%),
        radial-gradient(rgba(255, 255, 255, 0.15) 1px, transparent 1px) !important;
    background-size:
        100% 100%,
        100% 100%,
        10px 10px;
}

.hcis-table th {
    padding: 0.875rem 1rem;
    background: #f8fafc;
    color: var(--hcis-slate-700);
    font-weight: 700;
    font-size: 0.71875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid rgba(203, 213, 225, 0.9);
    border-right: 1px solid rgba(226, 232, 240, 0.6);
    white-space: nowrap;
    user-select: none;
    transition:
        background-color 150ms ease,
        color 150ms ease;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-table-container--sentiment-primary .hcis-table th,
.hcis-table-container--sentiment-brand .hcis-table th {
    background: #f8fafc;
    background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
}

.hcis-table-container--sentiment-purple .hcis-table th,
.hcis-table-container--sentiment-violet .hcis-table th {
    background: #f8fafc;
    background: linear-gradient(180deg, #f8fafc 0%, #f3e8ff 100%);
}

.hcis-table-container--sentiment-teal .hcis-table th,
.hcis-table-container--sentiment-cyan .hcis-table th {
    background: #f8fafc;
    background: linear-gradient(180deg, #f8fafc 0%, #ccfbf1 100%);
}

.hcis-table-container--sentiment-danger .hcis-table th {
    background: #f8fafc;
    background: linear-gradient(180deg, #f8fafc 0%, #fee2e2 100%);
}

.hcis-table-container--sentiment-warning .hcis-table th {
    background: #f8fafc;
    background: linear-gradient(180deg, #f8fafc 0%, #fef3c7 100%);
}

.hcis-table-container--sentiment-success .hcis-table th {
    background: #f8fafc;
    background: linear-gradient(180deg, #f8fafc 0%, #d1fae5 100%);
}

.hcis-table th:last-child {
    border-right: none;
}

.hcis-table-container--glass th {
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(12px);
}

.hcis-table-container--obsidian th {
    background: #1e293b !important;
    color: var(--hcis-slate-300) !important;
    border-bottom-color: rgba(255, 255, 255, 0.12) !important;
    border-right-color: rgba(255, 255, 255, 0.06) !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
}

.hcis-table-container--sticky th {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: var(--hcis-surface-subtle, #f8fafc);
    box-shadow:
        0 2px 8px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.hcis-table__th-content {
    display: flex;
    align-items: center;
    gap: 0.4375rem;
}

.hcis-table__th--sortable {
    cursor: pointer;
}

.hcis-table__th--sortable:hover {
    background-color: rgba(255, 255, 255, 0.45);
    color: var(--hcis-indigo-700);
}

.hcis-table-container--obsidian .hcis-table__th--sortable:hover {
    background-color: rgba(255, 255, 255, 0.08) !important;
    color: var(--hcis-indigo-400) !important;
}

.hcis-table__th--sorted {
    color: var(--hcis-indigo-700);
    background-color: rgba(238, 242, 255, 0.6);
}

.hcis-table__sort-icon {
    display: inline-flex;
    align-items: center;
    color: var(--hcis-indigo-600);
    transition: transform 150ms ease;
}

.hcis-table__sort-neutral {
    opacity: 0.35;
    transition: opacity 150ms ease;
}

.hcis-table__th--sortable:hover .hcis-table__sort-neutral {
    opacity: 0.85;
}

/* ── Draggable Columns Styling ── */
.hcis-table__th--draggable {
    cursor: grab;
    user-select: none;
    transition:
        background-color 150ms ease,
        border-color 150ms ease,
        opacity 150ms ease,
        transform 150ms ease;
}

.hcis-table__th--draggable:active {
    cursor: grabbing;
}

.hcis-table__col-drag-handle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-text-muted, var(--hcis-slate-400));
    margin-right: 0.25rem;
    cursor: grab;
    opacity: 0.55;
    transition:
        opacity 150ms ease,
        color 150ms ease;
    flex-shrink: 0;
}

.hcis-table__th--draggable:hover .hcis-table__col-drag-handle {
    opacity: 1;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.hcis-table__th--dragging {
    opacity: 0.35;
    background-color: rgba(241, 245, 249, 0.8) !important;
}

.hcis-table__th--drag-over {
    border-left: 3px solid var(--hcis-brand-600, var(--hcis-indigo-600)) !important;
    background-color: rgba(238, 242, 255, 0.85) !important;
}

/* ── 7. TABLE BODY CELLS (TBODY) ─────────────────────────────────────── */

.hcis-table td {
    padding: 0.875rem 1rem;
    color: var(--hcis-slate-700);
    border-bottom: 1px solid rgba(241, 245, 249, 0.95);
    transition: background-color 140ms ease;
    vertical-align: middle;
}

.hcis-table-container--obsidian td {
    color: var(--hcis-slate-200);
    border-bottom-color: rgba(255, 255, 255, 0.06);
}

.hcis-table tr:last-child td {
    border-bottom: none;
}

/* Size Tiers: Compact (sm), Balanced (md), Executive (lg) */
.hcis-table--size-sm td,
.hcis-table--size-sm th {
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
}

.hcis-table--size-lg td,
.hcis-table--size-lg th {
    padding: 1.125rem 1.25rem;
    font-size: 0.9375rem;
}

.hcis-table--size-xl td,
.hcis-table--size-xl th,
.hcis-table--size-2xl td,
.hcis-table--size-2xl th {
    padding: 1.25rem 1.5rem;
    font-size: 0.95rem;
}

.hcis-table--size-3xl td,
.hcis-table--size-3xl th,
.hcis-table--size-full td,
.hcis-table--size-full th {
    padding: 1.45rem 1.75rem;
    font-size: 1rem;
    line-height: 1.5;
}

.hcis-table--size-3xl .hcis-table__thead th,
.hcis-table--size-full .hcis-table__thead th {
    font-size: 0.92rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

/* Zebra Striping */
.hcis-table--striped tbody tr:nth-child(even) td {
    background-color: var(--hcis-row-striped);
}

.hcis-table-container--obsidian .hcis-table--striped tbody tr:nth-child(even) td {
    background-color: rgba(255, 255, 255, 0.02);
}

/* Hoverable Rows */
.hcis-table--hoverable tbody tr:hover td {
    background-color: var(--hcis-row-hover) !important;
}

.hcis-table-container--obsidian .hcis-table--hoverable tbody tr:hover td {
    background-color: rgba(255, 255, 255, 0.05) !important;
}

/* Selected Row State */
.hcis-table tr.is-selected td {
    background-color: var(--hcis-row-selected) !important;
}

.hcis-table tr.is-selected td:first-child {
    box-shadow: inset 3px 0 0 var(--hcis-indigo-600);
}

.hcis-table-container--obsidian tr.is-selected td {
    background-color: rgba(99, 102, 241, 0.18) !important;
}

.hcis-table tr.is-interactive {
    cursor: pointer;
}

/* ── 8. FOOTER & EMPTY STATES ────────────────────────────────────────── */

.hcis-table__footer {
    padding: 0.875rem 1.25rem;
    background: var(--hcis-slate-50);
    border-top: 1px solid rgba(226, 232, 240, 0.9);
}

.hcis-table-container--obsidian .hcis-table__footer {
    background: var(--hcis-slate-900);
    border-top-color: rgba(255, 255, 255, 0.08);
}

.hcis-table__empty-wrap {
    padding: 3rem 1.5rem;
    text-align: center;
}

/* ── 9. STICKY FIRST COLUMN (PINNED KEY COLUMN) ──────────────────────── */

.hcis-table--sticky-first-col th:first-child,
.hcis-table--sticky-first-col td:first-child {
    position: sticky;
    left: 0;
    z-index: 12;
    background-color: var(--hcis-white);
    box-shadow: 2px 0 8px -2px rgba(15, 23, 42, 0.08);
}

.hcis-table--sticky-first-col thead th:first-child {
    z-index: 15;
    background: linear-gradient(180deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
}

.hcis-table-container--obsidian .hcis-table--sticky-first-col th:first-child,
.hcis-table-container--obsidian .hcis-table--sticky-first-col td:first-child {
    background-color: var(--hcis-slate-900);
    box-shadow: 2px 0 8px -2px rgba(0, 0, 0, 0.5);
}

.hcis-table-container--obsidian .hcis-table--sticky-first-col thead th:first-child {
    background: linear-gradient(180deg, var(--hcis-slate-800) 0%, var(--hcis-slate-900) 100%);
}

/* ── 10. TABLET & MOBILE RESPONSIVE ADAPTATIONS ───────────────────────── */

@media (max-width: 1024px) {
    .hcis-table td,
    .hcis-table th {
        padding: 0.75rem 0.875rem;
    }
}

@media (max-width: 768px) {
    /* Touch Compact Table Padding & Minimum width for scroll mode */
    .hcis-table-container--responsive-scroll .hcis-table,
    .hcis-table {
        min-width: 580px;
    }

    .hcis-table td,
    .hcis-table th {
        padding: 0.625rem 0.75rem;
        font-size: 0.8125rem;
        white-space: nowrap;
    }

    /* Mobile Selection Banner */
    .hcis-table__selection-banner {
        flex-direction: column;
        align-items: stretch;
        gap: 0.625rem;
        padding: 0.625rem 0.875rem;
    }

    .hcis-table__selection-actions {
        width: 100%;
        justify-content: flex-start;
        flex-wrap: wrap;
        gap: 0.375rem;
    }

    .hcis-table__selection-actions button {
        flex: 1;
        justify-content: center;
    }

    /* Responsive Cards Transformation Mode (Luxury Executive Crystal Feed) */
    .hcis-table-container--responsive-cards .hcis-table-wrap,
    .hcis-table-container--responsive-auto .hcis-table-wrap {
        overflow-x: visible;
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    .hcis-table-container--responsive-cards .hcis-table,
    .hcis-table-container--responsive-auto .hcis-table {
        display: block;
        width: 100%;
        min-width: 0 !important;
        border-collapse: collapse;
    }

    .hcis-table-container--responsive-cards .hcis-table thead,
    .hcis-table-container--responsive-auto .hcis-table thead {
        display: none;
    }

    .hcis-table-container--responsive-cards .hcis-table tbody,
    .hcis-table-container--responsive-auto .hcis-table tbody {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        padding: 0.875rem;
        width: 100%;
        box-sizing: border-box;
    }

    .hcis-table-container--responsive-cards .hcis-table tbody tr,
    .hcis-table-container--responsive-auto .hcis-table tbody tr {
        display: flex;
        flex-direction: column;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.92) 100%);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: var(--hcis-radius-lg, 14px);
        padding: 1rem 1.125rem;
        box-shadow:
            0 0 0 1px rgba(255, 255, 255, 0.8) inset,
            0 4px 16px -2px rgba(15, 23, 42, 0.05),
            0 12px 24px -4px rgba(15, 23, 42, 0.04);
        gap: 0.5rem;
        width: 100%;
        box-sizing: border-box;
        transition:
            transform 200ms ease,
            box-shadow 200ms ease,
            border-color 200ms ease;
        position: relative;
        overflow: hidden;
    }

    .hcis-table-container--responsive-cards .hcis-table tbody tr:hover,
    .hcis-table-container--responsive-auto .hcis-table tbody tr:hover {
        transform: translateY(-2px);
        border-color: rgba(199, 210, 254, 0.9);
        box-shadow:
            0 0 0 1px rgba(255, 255, 255, 0.9) inset,
            0 8px 24px -4px rgba(15, 23, 42, 0.08),
            0 16px 36px -8px rgba(79, 70, 229, 0.06);
    }

    /* Left Accent Indicator Strip on Selected/Active */
    .hcis-table-container--responsive-cards .hcis-table tbody tr::before,
    .hcis-table-container--responsive-auto .hcis-table tbody tr::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-400) 100%);
        opacity: 0;
        transition: opacity 180ms ease;
    }

    .hcis-table-container--responsive-cards .hcis-table tbody tr.is-selected::before,
    .hcis-table-container--responsive-auto .hcis-table tbody tr.is-selected::before,
    .hcis-table-container--responsive-cards .hcis-table tbody tr:hover::before,
    .hcis-table-container--responsive-auto .hcis-table tbody tr:hover::before {
        opacity: 1;
    }

    .hcis-table-container--obsidian.hcis-table-container--responsive-cards .hcis-table tbody tr,
    .hcis-table-container--obsidian.hcis-table-container--responsive-auto .hcis-table tbody tr {
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.92) 100%);
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow:
            0 0 0 1px rgba(255, 255, 255, 0.06) inset,
            0 8px 24px -4px rgba(0, 0, 0, 0.4),
            0 16px 36px -8px rgba(0, 0, 0, 0.3);
    }

    .hcis-table-container--responsive-cards .hcis-table tbody td,
    .hcis-table-container--responsive-auto .hcis-table tbody td {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.375rem 0;
        border-bottom: 1px solid rgba(241, 245, 249, 0.9);
        white-space: normal;
        font-size: 0.8125rem;
        width: 100%;
        box-sizing: border-box;
        gap: 0.75rem;
    }

    .hcis-table-container--obsidian.hcis-table-container--responsive-cards .hcis-table tbody td,
    .hcis-table-container--obsidian.hcis-table-container--responsive-auto .hcis-table tbody td {
        border-bottom-color: rgba(255, 255, 255, 0.05);
    }

    /* Executive Label Capsule Badge */
    .hcis-table-container--responsive-cards .hcis-table tbody td::before,
    .hcis-table-container--responsive-auto .hcis-table tbody td::before {
        content: attr(data-label);
        font-weight: 700;
        font-size: 0.6875rem;
        color: var(--hcis-slate-500);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        background: rgba(241, 245, 249, 0.85);
        padding: 0.15rem 0.5rem;
        border-radius: 6px;
        border: 1px solid rgba(226, 232, 240, 0.6);
        flex-shrink: 0;
    }

    .hcis-table-container--obsidian.hcis-table-container--responsive-cards .hcis-table tbody td::before,
    .hcis-table-container--obsidian.hcis-table-container--responsive-auto .hcis-table tbody td::before {
        background: rgba(255, 255, 255, 0.06);
        border-color: rgba(255, 255, 255, 0.08);
        color: var(--hcis-slate-400);
    }

    /* First Record Row Header */
    .hcis-table-container--responsive-cards .hcis-table tbody td:first-child:not(.hcis-table__check-col),
    .hcis-table-container--responsive-auto .hcis-table tbody td:first-child:not(.hcis-table__check-col),
    .hcis-table-container--responsive-cards .hcis-table tbody td.hcis-table__check-col + td,
    .hcis-table-container--responsive-auto .hcis-table tbody td.hcis-table__check-col + td {
        font-size: 0.875rem;
        font-weight: 750;
        color: var(--hcis-slate-900);
        padding-bottom: 0.4375rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.95);
    }

    .hcis-table-container--obsidian.hcis-table-container--responsive-cards
        .hcis-table
        tbody
        td:first-child:not(.hcis-table__check-col),
    .hcis-table-container--obsidian.hcis-table-container--responsive-auto
        .hcis-table
        tbody
        td:first-child:not(.hcis-table__check-col),
    .hcis-table-container--obsidian.hcis-table-container--responsive-cards
        .hcis-table
        tbody
        td.hcis-table__check-col
        + td,
    .hcis-table-container--obsidian.hcis-table-container--responsive-auto
        .hcis-table
        tbody
        td.hcis-table__check-col
        + td {
        color: var(--hcis-slate-50);
        border-bottom-color: rgba(255, 255, 255, 0.08);
    }

    /* Checkbox Row */
    .hcis-table-container--responsive-cards .hcis-table tbody td.hcis-table__check-col,
    .hcis-table-container--responsive-auto .hcis-table tbody td.hcis-table__check-col {
        justify-content: flex-start;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    }

    /* Action Row (Last Column) */
    .hcis-table-container--responsive-cards .hcis-table tbody td:last-child,
    .hcis-table-container--responsive-auto .hcis-table tbody td:last-child {
        border-bottom: none;
        padding-top: 0.625rem;
        margin-top: 0.25rem;
        border-top: 1px solid rgba(226, 232, 240, 0.8);
        justify-content: space-between;
    }

    .hcis-table-container--obsidian.hcis-table-container--responsive-cards .hcis-table tbody td:last-child,
    .hcis-table-container--obsidian.hcis-table-container--responsive-auto .hcis-table tbody td:last-child {
        border-top-color: rgba(255, 255, 255, 0.08);
    }
}
</style>
