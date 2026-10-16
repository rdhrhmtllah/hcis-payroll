<template>
    <div
        class="hcis-collapse"
        :class="[
            `hcis-collapse--${variant}`,
            { 'hcis-collapse--accordion': accordion }
        ]"
    >
        <slot />
    </div>
</template>

<script setup>
import { ref, computed, watch, provide } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Array, String, Number],
        default: () => [],
    },
    accordion: {
        type: Boolean,
        default: false,
    },
    variant: {
        type: String,
        default: 'card', // 'card' | 'bordered' | 'ghost'
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const activeNames = ref([]);

const syncActiveNames = (val) => {
    if (props.accordion) {
        activeNames.value = val !== null && val !== undefined && val !== '' ? [val] : [];
    } else {
        if (Array.isArray(val)) {
            activeNames.value = [...val];
        } else if (val !== null && val !== undefined && val !== '') {
            activeNames.value = [val];
        } else {
            activeNames.value = [];
        }
    }
};

watch(() => props.modelValue, syncActiveNames, { immediate: true, deep: true });

const handleItemClick = (name) => {
    let nextNames = [];
    if (props.accordion) {
        nextNames = activeNames.value.includes(name) ? [] : [name];
        const emitVal = nextNames.length > 0 ? nextNames[0] : '';
        emit('update:modelValue', emitVal);
        emit('change', emitVal);
    } else {
        const index = activeNames.value.indexOf(name);
        if (index > -1) {
            nextNames = activeNames.value.filter((item) => item !== name);
        } else {
            nextNames = [...activeNames.value, name];
        }
        emit('update:modelValue', nextNames);
        emit('change', nextNames);
    }
    activeNames.value = nextNames;
};

provide('hcisCollapse', {
    activeNames,
    handleItemClick,
    variant: computed(() => props.variant),
});
</script>

<style>
.hcis-collapse {
    display: flex;
    flex-direction: column;
    width: 100%;
    font-family: var(--hcis-font-body, inherit);
}

.hcis-collapse--card {
    gap: 0.75rem;
}

.hcis-collapse--bordered {
    gap: 0;
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-lg);
    overflow: hidden;
    background-color: var(--hcis-surface, var(--hcis-white));
}

.hcis-collapse--bordered .hcis-collapse-item:not(:last-child) {
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
}

.hcis-collapse--ghost {
    gap: 0.5rem;
}
</style>
