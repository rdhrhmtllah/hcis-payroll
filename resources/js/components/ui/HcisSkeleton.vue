<template>
    <div
        v-if="type === 'circle' || type === 'avatar'"
        class="hcis-skeleton hcis-skeleton--circle"
        :class="{ 'hcis-skeleton--pulse': animation === 'pulse' }"
        :style="{ width: width || height || '3rem', height: height || width || '3rem' }"
        aria-hidden="true"
    ></div>

    <div
        v-else-if="type === 'button'"
        class="hcis-skeleton hcis-skeleton--button"
        :class="[`hcis-skeleton--${rounded}`, { 'hcis-skeleton--pulse': animation === 'pulse' }]"
        :style="{ width: width || '7rem', height: height || '2.25rem' }"
        aria-hidden="true"
    ></div>

    <div
        v-else-if="type === 'image'"
        class="hcis-skeleton hcis-skeleton--image"
        :class="[`hcis-skeleton--${rounded}`, { 'hcis-skeleton--pulse': animation === 'pulse' }]"
        :style="{ width: width || '100%', height: height || '12rem' }"
        aria-hidden="true"
    ></div>

    <div
        v-else-if="type === 'card'"
        class="hcis-skeleton hcis-skeleton--card"
        aria-hidden="true"
    >
        <div class="hcis-skeleton__header">
            <div class="hcis-skeleton hcis-skeleton--circle" style="width: 2.5rem; height: 2.5rem;"></div>
            <div class="hcis-skeleton__header-text">
                <div class="hcis-skeleton__line hcis-skeleton__line--sm" style="width: 50%; height: 0.9rem;"></div>
                <div class="hcis-skeleton__line hcis-skeleton__line--sm" style="width: 30%; height: 0.7rem;"></div>
            </div>
        </div>
        <div class="hcis-skeleton__body">
            <div class="hcis-skeleton__line hcis-skeleton__line--sm" style="width: 100%; height: 0.8rem;"></div>
            <div class="hcis-skeleton__line hcis-skeleton__line--sm" style="width: 85%; height: 0.8rem;"></div>
            <div class="hcis-skeleton__line hcis-skeleton__line--sm" style="width: 60%; height: 0.8rem;"></div>
        </div>
    </div>

    <div
        v-else
        class="hcis-skeleton hcis-skeleton--text"
        :class="{ 'hcis-skeleton--pulse': animation === 'pulse' }"
        aria-hidden="true"
    >
        <div
            v-for="line in lines"
            :key="line"
            class="hcis-skeleton__line"
            :class="`hcis-skeleton__line--${rounded}`"
            :style="{
                width: line === lines && lines > 1 ? '70%' : width,
                height: height
            }"
        ></div>
    </div>
</template>

<script setup>
defineOptions({ name: 'HcisSkeleton' });

defineProps({
    width: { type: String, default: '100%' },
    height: { type: String, default: '1rem' },
    rounded: { type: String, default: 'sm' },
    lines: { type: Number, default: 1 },
    type: {
        type: String,
        default: 'text',
        validator: (v) => ['text', 'circle', 'avatar', 'button', 'image', 'card'].includes(v),
    },
    animation: {
        type: String,
        default: 'shimmer',
        validator: (v) => ['shimmer', 'pulse', 'none'].includes(v),
    },
});
</script>
