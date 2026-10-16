<template>
    <HcisDataTable
        :columns="normalizedColumns"
        :data="data"
        :loading="loading"
        :total="resolvedTotal"
        :page="currentPage"
        :page-size="resolvedPageSize"
        :show-pagination="showPagination"
        :responsive="fitToContainer ? 'auto' : 'scroll'"
        :empty-title="emptyMessage"
        empty-description="Coba ubah filter atau muat ulang data."
        :hoverable="clickable"
        :searchable="false"
        variant="flat"
        table-variant="flat"
        table-size="full"
        @row-click="forwardRowClick"
        @page-change="forwardPageChange"
    >
        <template v-for="column in normalizedColumns" :key="column.key" #[`cell-${column.key}`]="slotProps">
            <slot
                v-if="$slots[`column-${column.key}`]"
                :name="`column-${column.key}`"
                :item="slotProps.row"
                :value="slotProps.value"
                :index="slotProps.index"
            />
            <span v-else>{{ displayValue(slotProps.value) }}</span>
        </template>

        <template v-if="$slots.empty" #empty>
            <slot name="empty" />
        </template>
    </HcisDataTable>
</template>

<script setup>
import { computed } from 'vue';
import { HcisDataTable } from '@/components/ui';

defineOptions({ name: 'VisitorDataTable' });

const props = defineProps({
    data: { type: Array, default: () => [] },
    columns: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    clickable: { type: Boolean, default: false },
    showActions: { type: Boolean, default: true },
    showDelete: { type: Boolean, default: false },
    showPagination: { type: Boolean, default: true },
    currentPage: { type: Number, default: 1 },
    totalPages: { type: Number, default: 1 },
    totalData: { type: Number, default: 0 },
    pageSize: { type: Number, default: 15 },
    fullPagination: { type: Object, default: () => ({}) },
    emptyMessage: { type: String, default: 'Tidak ada data ditemukan.' },
    rowKey: { type: String, default: 'id' },
    fitToContainer: { type: Boolean, default: true },
    mobileColumns: { type: Array, default: () => [] },
    cardTitleKey: { type: String, default: '' },
    cardSubtitleKey: { type: String, default: '' },
    cardStatusKey: { type: String, default: '' },
});

const emit = defineEmits(['row-click', 'action-delete', 'page-change', 'empty-action']);
const normalizedColumns = computed(() =>
    props.columns.map((column) => ({
        ...column,
        label: column.label || column.title || column.key,
    })),
);

const resolvedTotal = computed(() => Number(props.fullPagination.total ?? props.totalData ?? props.data.length));
const resolvedPageSize = computed(() => {
    const explicit = Number(props.fullPagination.per_page);
    if (explicit > 0) return explicit;
    return Math.max(1, props.pageSize);
});

function displayValue(value) {
    return value === null || value === undefined || value === '' ? '-' : value;
}

function forwardRowClick(payload) {
    if (props.clickable) emit('row-click', payload);
}

function forwardPageChange(page) {
    const numericPage = typeof page === 'object' ? (page?.page ?? page?.currentPage) : page;
    emit('page-change', Number(numericPage));
}
</script>
