<template>
    <select
        :value="value"
        @change="$emit('update:value', $event.target.value)"
        class="filter-select"
    >
        <option value="">All {{ filter.label }}</option>
        <option
            v-for="option in filter.options"
            :key="option.id || option.value"
            :value="option.id || option.value"
        >
            {{ option.name || option.label }}
        </option>
    </select>
</template>

<script>
export default {
    name: "SelectFilter",
    props: {
        filter: {
            type: Object,
            required: true,
        },
        value: {
            type: [String, Number],
            default: "",
        },
    },
    emits: ["update:value"],
};
</script>

<style scoped>
.filter-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}
</style>
