<template>
    <HcisContainer
        :variant="variant"
        :size="size"
        :sentiment="sentiment"
        :glow="glow"
        :title="title"
        :subtitle="subtitle"
        :icon="icon"
        :badge="badge"
        :badge-variant="badgeVariant"
        :loading="loading"
    >
        <template v-if="$slots.headerActions || allowAdd" #actions>
            <slot name="headerActions">
                <HcisButton
                    v-if="allowAdd"
                    size="sm"
                    variant="primary"
                    leading-icon="plus-lg"
                    @click="$emit('add')"
                >
                    {{ addLabel }}
                </HcisButton>
            </slot>
        </template>

        <!-- Top Filter Bar -->
        <div v-if="searchable || filters.length || quickChips.length" class="hcis-data-table__filter mb-3">
            <HcisFilterBar
                :search="search"
                :search-placeholder="searchPlaceholder"
                :filters="filters"
                :filter-values="filterValues"
                :quick-chips="quickChips"
                :active-chip-value="activeChipValue"
                :command-key="commandKey"
                :allow-export="allowExport"
                :show-view-toggle="showViewToggle"
                :view-mode="viewMode"
                :variant="filterBarVariant"
                :sentiment="sentiment"
                @update:search="$emit('update:search', $event)"
                @update:view-mode="$emit('update:viewMode', $event)"
                @filter-change="$emit('filter-change', $event)"
                @chip-click="$emit('chip-click', $event)"
                @reset="$emit('reset')"
                @export="$emit('export', $event)"
            />
        </div>

        <!-- Table / Grid Data View -->
        <div class="hcis-data-table__wrapper">
            <slot v-if="$slots.grid && viewMode === 'grid'" name="grid" :data="data" :loading="loading" />
            <slot v-else-if="$slots.body" name="body" :data="data" :loading="loading" />
            <HcisTable
                v-else
                :columns="columns"
                :rows="data"
                :loading="loading"
                :hoverable="hoverable"
                :striped="striped"
                :size="resolvedTableSize"
                :variant="tableVariant"
                :sentiment="sentiment"
                :selectable="selectable"
                :selected-keys="selectedKeys"
                :sticky-header="stickyHeader"
                :sticky-first-col="stickyFirstCol"
                :responsive="responsive"
                :sort-key="sortKey"
                :sort-order="sortOrder"
                :remote-sort="remoteSort"
                :empty-title="emptyTitle"
                :empty-description="emptyDescription"
                :draggable-columns="draggableColumns || draggable"
                @update:columns="$emit('update:columns', $event)"
                @column-reorder="$emit('columnReorder', $event)"
                @update:selected-keys="$emit('update:selectedKeys', $event)"
                @update:sort-key="$emit('update:sortKey', $event)"
                @update:sort-order="$emit('update:sortOrder', $event)"
                @sort="$emit('sort', $event)"
                @row-click="$emit('row-click', $event)"
                @select-all="$emit('selectAll', $event)"
            >
                <template v-for="col in slotColumns" :key="col.key" #[`cell-${col.key}`]="slotProps">
                    <slot :name="`cell-${col.key}`" v-bind="slotProps" />
                </template>

                <template v-for="col in slotColumns" :key="`header-${col.key}`" #[`header-${col.key}`]="slotProps">
                    <slot :name="`header-${col.key}`" v-bind="slotProps" />
                </template>

                <template v-if="$slots['bulk-actions']" #bulk-actions="slotProps">
                    <slot name="bulk-actions" v-bind="slotProps" />
                </template>

                <template v-if="$slots.empty" #empty>
                    <slot name="empty" />
                </template>
            </HcisTable>
        </div>

        <!-- Pagination Footer -->
        <template v-if="total > 0 && showPagination" #footer>
            <div class="hcis-data-table__pagination">
                <!--
                    SATU peristiwa disimak, bukan semuanya.

                    goToPage() memancarkan enam peristiwa untuk satu klik
                    (update:modelValue, update:currentPage, update:page, change,
                    current-change, page-change) dan selectPageSize() tiga. Itu
                    demi kenyamanan pemakai yang menamainya berbeda-beda —
                    tetapi menyimak lebih dari satu berarti satu klik halaman
                    memancarkan 'page-change' berkali-kali, dan pemakainya
                    menembakkan permintaan sebanyak itu pula ke server.
                -->
                <HcisPagination
                    :total="total"
                    :current-page="page"
                    :page-size="pageSize"
                    @current-change="$emit('page-change', $event)"
                    @size-change="$emit('size-change', $event)"
                />
            </div>
        </template>
    </HcisContainer>
</template>

<script setup>
import { computed } from 'vue';
import HcisContainer from './HcisContainer.vue';
import HcisButton from './HcisButton.vue';
import HcisFilterBar from './HcisFilterBar.vue';
import HcisTable from './HcisTable.vue';
import HcisPagination from './HcisPagination.vue';

defineOptions({ name: 'HcisDataTable' });

const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    icon: { type: String, default: '' },
    badge: { type: String, default: '' },
    badgeVariant: { type: String, default: 'brand' },
    columns: { type: Array, default: () => [] },
    data: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    search: { type: String, default: '' },
    searchPlaceholder: { type: String, default: 'Cari data...' },
    filters: { type: Array, default: () => [] },
    filterValues: { type: Object, default: () => ({}) },
    quickChips: { type: Array, default: () => [] },
    activeChipValue: { type: [String, Number], default: null },
    total: { type: Number, default: 0 },
    page: { type: Number, default: 1 },
    pageSize: { type: Number, default: 10 },
    allowAdd: { type: Boolean, default: false },
    addLabel: { type: String, default: 'Tambah Data' },
    allowExport: { type: Boolean, default: false },
    searchable: { type: Boolean, default: true },
    showPagination: { type: Boolean, default: true },
    showViewToggle: { type: Boolean, default: false },
    viewMode: { type: String, default: 'table' },
    commandKey: { type: Boolean, default: false },
    hoverable: { type: Boolean, default: true },
    striped: { type: Boolean, default: false },
    selectable: { type: Boolean, default: false },
    selectedKeys: { type: Array, default: () => [] },
    stickyHeader: { type: Boolean, default: false },
    stickyFirstCol: { type: Boolean, default: false },
    responsive: { type: String, default: 'scroll' },
    sortKey: { type: String, default: '' },
    sortOrder: { type: String, default: 'asc' },
    remoteSort: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'Tidak ada data ditemukan' },
    emptyDescription: { type: String, default: 'Silakan ubah filter atau kata kunci pencarian.' },
    variant: { type: String, default: 'card' },
    tableVariant: { type: String, default: 'flat' },
    tableSize: { type: String, default: '' },
    filterBarVariant: { type: String, default: 'flat' },
    sentiment: { type: String, default: 'primary' },
    glow: { type: Boolean, default: false },
    size: { type: String, default: 'full' },
    draggableColumns: { type: Boolean, default: false },
    draggable: { type: Boolean, default: false },
});

defineEmits([
    'update:search',
    'update:viewMode',
    'update:selectedKeys',
    'update:sortKey',
    'update:sortOrder',
    'update:columns',
    'columnReorder',
    'sort',
    'filter-change',
    'chip-click',
    'reset',
    'export',
    'add',
    'row-click',
    'selectAll',
    'page-change',
    'size-change',
]);

const slotColumns = computed(() => props.columns.filter((c) => c.key));

const resolvedTableSize = computed(() => {
    if (['sm', 'md', 'lg', 'xl', '2xl', '3xl', 'full'].includes(props.tableSize)) return props.tableSize;
    if (['sm', 'md', 'lg', 'xl', '2xl', '3xl', 'full'].includes(props.size)) return props.size;
    return '3xl';
});
</script>

<style scoped>
.hcis-data-table__wrapper {
    width: 100%;
}

.hcis-data-table__pagination {
    width: 100%;
    display: flex;
    justify-content: flex-end;
}
</style>
