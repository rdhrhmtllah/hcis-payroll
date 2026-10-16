<template>
    <v-select
        :model-value="value"
        @update:modelValue="$emit('update:value', $event)"
        :options="filter.options"
        :reduce="(option) => option.id || option.value"
        :label="(option) => option.name || option.label"
        multiple
        :placeholder="`Select ${filter.label}...`"
        class="filter-multiselect"
    ></v-select>
</template>

<script>
export default {
    name: "MultiSelectFilter",
    props: {
        filter: {
            type: Object,
            required: true,
        },
        value: {
            type: Array,
            default: () => [],
        },
    },
    emits: ["update:value"],
};
</script>

<style scoped>
:deep(.filter-multiselect .vs__dropdown-toggle) {
    padding: 0.75rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
}

:deep(.filter-multiselect .vs__dropdown-toggle:focus-within) {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

:deep(.filter-multiselect .vs__selected) {
    background: var(--primary-light);
    color: var(--primary-dark);
    border: none;
    border-radius: 6px;
    font-weight: 500;
}
</style>
