<template>
    <HcisFilterBar
        :search="values.search || ''"
        :search-placeholder="config.searchPlaceholder || 'Cari data...'"
        :filters="normalizedFilters"
        :filter-values="values"
        :results-count="resolvedResultsCount"
        :collapsible="config.collapsible !== false"
        :show-active-tags="config.showActiveTags === true"
        variant="card"
        sentiment="primary"
        @update:search="updateSearch"
        @filter-change="updateFilter"
        @reset="resetFilters"
    >
        <template v-if="$slots.actions" #actions>
            <slot name="actions" />
        </template>
    </HcisFilterBar>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { HcisFilterBar } from '@/components/ui';

defineOptions({ name: 'VisitorFilterBar' });

const props = defineProps({
    config: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    resultsCount: { type: Number, default: null },
});

const emit = defineEmits(['filters-update']);

function createInitialValues() {
    const defaults = Object.fromEntries(
        (props.config.filters || []).map((filter) => [filter.key, filter.default ?? '']),
    );

    return { search: '', ...defaults, ...props.filters };
}

const values = ref(createInitialValues());

const normalizedFilters = computed(() =>
    (props.config.filters || []).map((filter) => ({
        ...filter,
        type: filter.dateType === 'daterange' ? 'daterange' : filter.type,
    })),
);

const resolvedResultsCount = computed(() => (props.config.showResultsCount === false ? null : props.resultsCount));

watch(
    () => props.filters,
    (nextFilters) => {
        values.value = { ...values.value, ...nextFilters };
    },
    { deep: true },
);

function publish(nextValues) {
    values.value = nextValues;
    emit('filters-update', { ...nextValues });
}

function updateSearch(search) {
    publish({ ...values.value, search });
}

function updateFilter(event) {
    publish(event?.values ? { ...values.value, ...event.values } : { ...values.value, [event.key]: event.value });
}

function resetFilters() {
    const resetValues = Object.fromEntries(
        (props.config.filters || []).map((filter) => [filter.key, filter.default ?? '']),
    );
    publish({ search: '', ...resetValues });
}
</script>
