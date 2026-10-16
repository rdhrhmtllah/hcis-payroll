<template>
    <div
        class="hcis-stat"
        :class="[
            `hcis-stat--${normalizedSentiment}`,
            `hcis-stat--layout-${layout}`,
            appearance !== 'crystal' ? `hcis-stat--${appearance}` : '',
            {
                'is-interactive': interactive || $attrs.onClick,
                'is-loading': loading,
            },
        ]"
    >
        <!-- Ambient Radial Glow Bloom -->
        <div class="hcis-stat__ambient-glow" aria-hidden="true" />

        <!-- Signature Background Pattern Layer -->
        <div v-if="pattern === 'grid'" class="hcis-pattern-grid hcis-pattern-grid--diagonal" :class="{ 'hcis-pattern-grid--light': appearance === 'obsidian' || appearance === 'solid' }" aria-hidden="true" />
        <div v-else-if="pattern === 'dots'" class="hcis-pattern-dots" :class="{ 'hcis-pattern-dots--light': appearance === 'obsidian' || appearance === 'solid' }" aria-hidden="true" />
        <div v-else-if="pattern === 'crosshair'" class="hcis-pattern-crosshair" :class="{ 'hcis-pattern-crosshair--light': appearance === 'obsidian' || appearance === 'solid' }" aria-hidden="true" />
        <div v-else-if="pattern === 'stripes'" class="hcis-pattern-stripes" :class="{ 'hcis-pattern-stripes--light': appearance === 'obsidian' || appearance === 'solid' }" aria-hidden="true" />
        <div v-else-if="pattern === 'rings'" class="hcis-pattern-rings" :class="{ 'hcis-pattern-rings--light': appearance === 'obsidian' || appearance === 'solid' }" aria-hidden="true" />
        <div v-else-if="pattern === 'hex'" class="hcis-pattern-hex" :class="{ 'hcis-pattern-hex--light': appearance === 'obsidian' || appearance === 'solid' }" aria-hidden="true" />

        <!-- 1. Layout: Horizontal (Side-by-Side Icon & Value) -->
        <div v-if="layout === 'horizontal'" class="hcis-stat__horizontal-wrap">
            <div v-if="icon || $slots.icon" class="hcis-stat__icon-box">
                <slot name="icon">
                    <HcisIcon :name="icon" :animation="iconAnimation" size="md" />
                </slot>
            </div>
            <div class="hcis-stat__horizontal-content">
                <p class="hcis-stat__label">{{ label }}</p>
                <div class="hcis-stat__value-row">
                    <span v-if="prefix" class="hcis-stat__prefix">{{ prefix }}</span>
                    <span class="hcis-stat__value">{{ value }}</span>
                    <span v-if="suffix" class="hcis-stat__suffix">{{ suffix }}</span>
                    <HcisBadge
                        v-if="delta"
                        :label="formattedDelta"
                        :variant="sentimentVariant"
                        size="xs"
                        class="hcis-stat__delta-badge"
                    />
                </div>
                <p v-if="caption" class="hcis-stat__caption">{{ caption }}</p>
            </div>
        </div>

        <!-- 2. Layout: Compact & Dense (Dense Mini Widget) -->
        <div v-else-if="layout === 'compact' || layout === 'dense'" class="hcis-stat__compact-wrap" :class="{ 'hcis-stat__compact-wrap--dense': layout === 'dense' }">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                <p class="hcis-stat__label mb-0 text-truncate" :title="label">{{ label }}</p>
                <div v-if="icon || $slots.icon" class="hcis-stat__icon-box hcis-stat__icon-box--sm flex-shrink-0">
                    <slot name="icon">
                        <HcisIcon :name="icon" :animation="iconAnimation" :size="layout === 'dense' ? '2xs' : 'xs'" />
                    </slot>
                </div>
            </div>
            <div class="hcis-stat__value-row align-items-baseline">
                <span v-if="prefix" class="hcis-stat__prefix">{{ prefix }}</span>
                <span class="hcis-stat__value" :class="layout === 'dense' ? 'hcis-stat__value--dense' : 'hcis-stat__value--compact'">{{ value }}</span>
                <span v-if="suffix" class="hcis-stat__suffix ms-1">{{ suffix }}</span>
                <HcisBadge
                    v-if="delta"
                    :label="formattedDelta"
                    :variant="sentimentVariant"
                    :size="layout === 'dense' ? '2xs' : 'xs'"
                    class="hcis-stat__delta-badge ms-auto"
                />
            </div>
            <!-- Progress / Target Bar in Compact/Dense -->
            <div v-if="progress !== null && progress !== undefined" class="hcis-stat__progress-wrap mt-1">
                <div class="hcis-stat__progress-bar" :style="{ height: layout === 'dense' ? '3px' : '4px' }">
                    <div
                        class="hcis-stat__progress-fill"
                        :style="{ width: `${Math.min(Math.max(progress, 0), 100)}%` }"
                    />
                </div>
            </div>
            <p v-if="caption" class="hcis-stat__caption mt-1 mb-0 text-truncate" :title="caption">{{ caption }}</p>
        </div>

        <!-- 3. Layout: Default & Hero (Standard Executive Card) -->
        <template v-else>
            <div class="hcis-stat__header">
                <div class="hcis-stat__header-text">
                    <p class="hcis-stat__label">{{ label }}</p>
                    <p v-if="caption" class="hcis-stat__caption">{{ caption }}</p>
                </div>
                <div v-if="icon || $slots.icon" class="hcis-stat__icon-box" :class="{ 'hcis-stat__icon-box--hero': layout === 'hero' }">
                    <slot name="icon">
                        <HcisIcon :name="icon" :animation="iconAnimation" :size="layout === 'hero' ? 'lg' : 'md'" />
                    </slot>
                </div>
            </div>

            <!-- Transition: new values smoothly slide in -->
            <Transition name="hcis-fade" mode="out-in">
                <HcisSkeleton v-if="loading" key="memuat" width="65%" height="2.25rem" rounded="sm" class="mt-3" />
                <div v-else key="isi" class="hcis-stat__body">
                    <div class="hcis-stat__value-row">
                        <span v-if="prefix" class="hcis-stat__prefix">{{ prefix }}</span>
                        <span class="hcis-stat__value" :class="{ 'hcis-stat__value--hero': layout === 'hero' }">{{ value }}</span>
                        <span v-if="suffix" class="hcis-stat__suffix">{{ suffix }}</span>
                        <HcisBadge
                            v-if="delta"
                            :label="formattedDelta"
                            :variant="sentimentVariant"
                            size="xs"
                            class="hcis-stat__delta-badge"
                        />
                    </div>

                    <!-- Progress / Target Bar -->
                    <div v-if="progress !== null && progress !== undefined" class="hcis-stat__progress-wrap mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="hcis-stat__progress-label">Target: {{ target || `${progress}%` }}</span>
                            <span class="hcis-stat__progress-pct font-monospace">{{ progress }}%</span>
                        </div>
                        <div class="hcis-stat__progress-bar">
                            <div
                                class="hcis-stat__progress-fill"
                                :style="{ width: `${Math.min(Math.max(progress, 0), 100)}%` }"
                            />
                        </div>
                    </div>

                    <!-- Default Slot for charts/sparklines/custom content -->
                    <div v-if="$slots.default" class="hcis-stat__custom-slot mt-2">
                        <slot />
                    </div>
                </div>
            </Transition>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import HcisSkeleton from './HcisSkeleton.vue';
import HcisIcon from './HcisIcon.vue';
import HcisBadge from './HcisBadge.vue';

defineOptions({ name: 'HcisStatCard' });

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '' },
    delta: { type: String, default: '' },
    trend: { type: String, default: '' },
    sentiment: { type: String, default: 'neutral' },
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
    pattern: {
        type: String,
        default: 'none',
        validator: (v) => ['none', 'dots', 'grid', 'crosshair', 'stripes', 'rings', 'hex'].includes(v),
    },
    prefix: { type: String, default: '' },
    suffix: { type: String, default: '' },
    progress: { type: Number, default: null },
    target: { type: [String, Number], default: '' },
    caption: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    icon: { type: String, default: '' },
    iconAnimation: { type: String, default: '' },
    interactive: { type: Boolean, default: false },
});

const normalizedSentiment = computed(() => {
    switch (props.sentiment) {
        case 'good':
        case 'success':
            return 'success';
        case 'bad':
        case 'danger':
            return 'danger';
        case 'brand':
        case 'primary':
            return 'primary';
        case 'warning':
            return 'warning';
        case 'info':
            return 'info';
        case 'purple':
        case 'violet':
            return 'purple';
        case 'teal':
        case 'cyan':
            return 'teal';
        case 'obsidian':
        case 'dark':
            return 'obsidian';
        default:
            return 'neutral';
    }
});

const sentimentVariant = computed(() => {
    switch (normalizedSentiment.value) {
        case 'success':
            return 'success';
        case 'danger':
            return 'danger';
        case 'warning':
            return 'warning';
        case 'primary':
            return 'brand';
        case 'info':
            return 'info';
        case 'purple':
            return 'purple';
        case 'teal':
            return 'teal';
        case 'obsidian':
            return 'obsidian';
        default:
            return 'neutral';
    }
});

const formattedDelta = computed(() => {
    if (!props.delta) return '';
    if (props.trend === 'down') return `↓ ${props.delta}`;
    if (props.trend === 'up') return `↑ ${props.delta}`;
    return props.delta;
});
</script>
