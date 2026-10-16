<template>
    <div
        class="hcis-stat-group"
        :class="[
            `hcis-stat-group--cols-${cols}`,
            `hcis-stat-group--gap-${gap}`,
            variant ? `hcis-stat-group--${variant}` : '',
        ]"
    >
        <slot>
            <HcisStatCard
                v-for="(item, idx) in stats"
                :key="item.id || item.key || idx"
                :label="item.label"
                :value="item.value"
                :delta="item.delta || item.trendValue"
                :trend="item.trend"
                :sentiment="item.sentiment || item.variant || 'neutral'"
                :layout="item.layout || layout"
                :appearance="item.appearance || appearance"
                :prefix="item.prefix"
                :suffix="item.suffix"
                :progress="item.progress"
                :target="item.target"
                :caption="item.caption || item.subtext"
                :icon="item.icon"
                :icon-animation="item.iconAnimation"
                :loading="item.loading"
                :interactive="item.clickable || item.interactive"
                class="hcis-stat-group__item"
                @click="onItemClick(item, idx)"
            />
        </slot>
    </div>
</template>

<script setup>
import HcisStatCard from './HcisStatCard.vue';

defineOptions({ name: 'HcisStatGroup' });

const props = defineProps({
    stats: { type: Array, default: () => [] },
    cols: {
        type: [Number, String],
        default: 4,
        validator: (v) => [1, 2, 3, 4, 5, '1', '2', '3', '4', '5', 'auto'].includes(v),
    },
    gap: {
        type: String,
        default: 'md',
        validator: (v) => ['xs', 'sm', 'md', 'lg'].includes(v),
    },
    layout: {
        type: String,
        default: 'default',
        validator: (v) => ['default', 'horizontal', 'compact', 'dense', 'hero'].includes(v),
    },
    appearance: {
        type: String,
        default: 'crystal',
        validator: (v) => ['crystal', 'solid', 'glass', 'obsidian'].includes(v),
    },
    variant: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['item-click']);

function onItemClick(item, index) {
    emit('item-click', { item, index });
}
</script>

<style scoped>
.hcis-stat-group {
    display: grid;
    width: 100%;
    box-sizing: border-box;
}

/* Gap scales */
.hcis-stat-group--gap-xs { gap: 0.5rem; }
.hcis-stat-group--gap-sm { gap: 0.75rem; }
.hcis-stat-group--gap-md { gap: 1rem; }
.hcis-stat-group--gap-lg { gap: 1.5rem; }

/* Grid columns */
.hcis-stat-group--cols-1 { grid-template-columns: 1fr; }
.hcis-stat-group--cols-2 { grid-template-columns: repeat(2, 1fr); }
.hcis-stat-group--cols-3 { grid-template-columns: repeat(3, 1fr); }
.hcis-stat-group--cols-4 { grid-template-columns: repeat(4, 1fr); }
.hcis-stat-group--cols-5 { grid-template-columns: repeat(5, 1fr); }
.hcis-stat-group--cols-auto { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }

@media (max-width: 992px) {
    .hcis-stat-group--cols-4,
    .hcis-stat-group--cols-5 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
    .hcis-stat-group--cols-2,
    .hcis-stat-group--cols-3,
    .hcis-stat-group--cols-4,
    .hcis-stat-group--cols-5 {
        grid-template-columns: 1fr;
    }
}
</style>
