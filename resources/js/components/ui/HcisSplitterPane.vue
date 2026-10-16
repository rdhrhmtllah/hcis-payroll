<template>
    <div
        class="hcis-splitter-pane"
        :class="{ 'is-collapsed': collapsed }"
        :style="paneStyle"
    >
        <slot />
    </div>
</template>

<script setup>
import { computed } from 'vue';

defineOptions({ name: 'HcisSplitterPane' });

const props = defineProps({
    size: { type: [Number, String], default: null },
    min: { type: [Number, String], default: '10%' },
    max: { type: [Number, String], default: '90%' },
    collapsible: { type: Boolean, default: true },
    collapsed: { type: Boolean, default: false },
});

const paneStyle = computed(() => {
    if (props.collapsed) {
        return { flex: '0 0 0px', width: '0px', overflow: 'hidden', padding: 0 };
    }
    if (props.size !== null && props.size !== undefined) {
        const sz = typeof props.size === 'number' ? `${props.size}%` : props.size;
        return { flex: `0 0 ${sz}`, width: sz };
    }
    return { flex: '1 1 0%' };
});
</script>

<style scoped>
.hcis-splitter-pane {
    position: relative;
    height: 100%;
    min-width: 0;
    min-height: 0;
    overflow: auto;
    box-sizing: border-box;
    transition: flex 200ms ease, width 200ms ease;
}

.hcis-splitter-pane.is-collapsed {
    opacity: 0;
    pointer-events: none;
}
</style>
