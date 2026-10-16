<template>
    <div
        class="hcis-progress-wrapper"
        :class="[
            `hcis-progress-wrapper--sentiment-${sentimentNormal}`,
            `hcis-progress-wrapper--type-${type}`,
            { 'hcis-progress-wrapper--standalone': !card }
        ]"
    >
        <!-- ═══════════════════════════════════════════════════════════════════
             MODE 1: WRAPPED INSIDE HCISCARD SUITE EKSEKUTIF
             ═══════════════════════════════════════════════════════════════════ -->
        <HcisCard
            v-if="card"
            :title="title"
            :kicker="kicker"
            :description="description"
            :badge="badge || `${aman}% Tercapai`"
            :badge-variant="badgeVariant || sentimentNormal"
            :badge-radar="badgeRadar"
            :icon="icon || 'speedometer2'"
            :sentiment="sentiment"
            :variant="cardVariant"
            :pattern="pattern"
            :pattern-placement="patternPlacement"
        >
            <!-- Header Actions Slot -->
            <template v-if="$slots.actions" #actions>
                <slot name="actions" />
            </template>

            <!-- Progress Core Body -->
            <div class="hcis-progress__inner-body">
                <!-- 1. CIRCLE SVG MODE -->
                <div v-if="type === 'circle'" class="hcis-progress-circle-wrap">
                    <div
                        class="hcis-progress-circle"
                        :class="[
                            `hcis-progress-circle--${size}`,
                            `hcis-progress-circle--${sentimentNormal}`,
                            {
                                'hcis-progress-circle--indeterminate': indeterminate,
                                'hcis-progress-circle--animated': animated,
                            }
                        ]"
                        role="progressbar"
                        :aria-valuenow="indeterminate ? undefined : aman"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-label="label || undefined"
                    >
                        <svg class="hcis-progress-circle__svg" viewBox="0 0 100 100">
                            <!-- Gradient Definitions -->
                            <defs>
                                <linearGradient :id="`circle-grad-${uid}`" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" :stop-color="gradientStops.start" />
                                    <stop offset="100%" :stop-color="gradientStops.end" />
                                </linearGradient>
                            </defs>
                            <!-- Runway Background Track -->
                            <circle
                                class="hcis-progress-circle__track-bg"
                                cx="50"
                                cy="50"
                                :r="circleRadius"
                                :stroke-width="circleStrokeWidth"
                            />
                            <!-- Radiant Progress Fill -->
                            <circle
                                class="hcis-progress-circle__fill"
                                cx="50"
                                cy="50"
                                :r="circleRadius"
                                :stroke-width="circleStrokeWidth"
                                :stroke="`url(#circle-grad-${uid})`"
                                :style="circleFillStyle"
                            />
                        </svg>

                        <!-- Center Metric Slot -->
                        <div class="hcis-progress-circle__content">
                            <slot :value="aman">
                                <HcisIcon
                                    v-if="aman >= 100 && !indeterminate"
                                    name="check-circle-fill"
                                    class="hcis-progress-circle__done-icon"
                                />
                                <span v-else-if="showValue && !indeterminate" class="hcis-progress-circle__value">
                                    {{ aman }}<span class="hcis-progress-circle__unit">%</span>
                                </span>
                            </slot>
                        </div>
                    </div>
                </div>

                <!-- 2. DASHBOARD ARC MODE -->
                <div v-else-if="type === 'dashboard'" class="hcis-progress-dashboard-wrap">
                    <div
                        class="hcis-progress-dashboard"
                        :class="[
                            `hcis-progress-dashboard--${size}`,
                            `hcis-progress-dashboard--${sentimentNormal}`,
                            {
                                'hcis-progress-dashboard--indeterminate': indeterminate,
                                'hcis-progress-dashboard--animated': animated,
                            }
                        ]"
                        role="progressbar"
                        :aria-valuenow="indeterminate ? undefined : aman"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-label="label || undefined"
                    >
                        <svg class="hcis-progress-dashboard__svg" viewBox="0 0 100 85">
                            <defs>
                                <linearGradient :id="`dash-grad-${uid}`" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" :stop-color="gradientStops.start" />
                                    <stop offset="100%" :stop-color="gradientStops.end" />
                                </linearGradient>
                            </defs>
                            <path
                                class="hcis-progress-dashboard__track-bg"
                                :d="dashboardPath"
                                :stroke-width="circleStrokeWidth"
                                stroke-linecap="round"
                            />
                            <path
                                class="hcis-progress-dashboard__fill"
                                :d="dashboardPath"
                                :stroke-width="circleStrokeWidth"
                                stroke-linecap="round"
                                :stroke="`url(#dash-grad-${uid})`"
                                :style="dashboardFillStyle"
                            />
                        </svg>

                        <div class="hcis-progress-dashboard__content">
                            <slot :value="aman">
                                <span class="hcis-progress-dashboard__value">{{ aman }}<span class="hcis-progress-dashboard__unit">%</span></span>
                                <span v-if="label" class="hcis-progress-dashboard__label">{{ label }}</span>
                            </slot>
                        </div>
                    </div>
                </div>

                <!-- 3. SEGMENTED MILESTONE BLOCKS -->
                <div
                    v-else-if="type === 'segments'"
                    class="hcis-progress-segments"
                    :class="[
                        `hcis-progress-segments--${size}`,
                        `hcis-progress-segments--${sentimentNormal}`
                    ]"
                >
                    <div v-if="(label || showValue || subtitle) && !insideValue" class="hcis-progress__head">
                        <div class="hcis-progress__head-main">
                            <span v-if="label" class="hcis-progress__label">{{ label }}</span>
                            <span v-if="subtitle" class="hcis-progress__subtitle">{{ subtitle }}</span>
                        </div>
                        <div v-if="showValue" class="hcis-progress__value-badge">
                            <span>{{ completedSegments }}/{{ totalSegments }}</span>
                            <span class="text-slate-400 ms-1 font-mono">({{ aman }}%)</span>
                        </div>
                    </div>

                    <div class="hcis-progress-segments__track">
                        <div
                            v-for="seg in totalSegments"
                            :key="seg"
                            class="hcis-progress-segments__block"
                            :class="{
                                'hcis-progress-segments__block--active': seg <= completedSegments,
                                'hcis-progress-segments__block--current': seg === completedSegments + 1 && animated
                            }"
                        />
                    </div>
                </div>

                <!-- 4. LINEAR CAPSULE RUNWAY (DEFAULT) -->
                <div
                    v-else
                    class="hcis-progress"
                    :class="[
                        `hcis-progress--${size}`,
                        `hcis-progress--sentiment-${sentimentNormal}`,
                        {
                            'hcis-progress--striped': striped,
                            'hcis-progress--animated': animated,
                            'hcis-progress--indeterminate': indeterminate,
                            'hcis-progress--glow': glow,
                        }
                    ]"
                >
                    <div v-if="(label || showValue || subtitle) && !insideValue" class="hcis-progress__head">
                        <div class="hcis-progress__head-main">
                            <span v-if="label" class="hcis-progress__label">{{ label }}</span>
                            <span v-if="subtitle" class="hcis-progress__subtitle">{{ subtitle }}</span>
                        </div>
                        <div v-if="showValue && !indeterminate" class="hcis-progress__value">
                            <HcisIcon v-if="aman >= 100" name="check-circle-fill" size="xs" class="hcis-progress__done-icon me-1" />
                            <span class="hcis-progress__number">{{ aman }}%</span>
                        </div>
                    </div>

                    <div
                        class="hcis-progress__track"
                        role="progressbar"
                        :aria-valuenow="indeterminate ? undefined : aman"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-label="label || undefined"
                    >
                        <div
                            class="hcis-progress__fill"
                            :class="[`hcis-progress__fill--${sentimentNormal}`]"
                            :style="lineFillStyle"
                        >
                            <div v-if="animated" class="hcis-progress__shimmer" />

                            <span
                                v-if="insideValue && (size === 'md' || size === 'lg' || size === 'xl') && !indeterminate"
                                class="hcis-progress__inner-text"
                            >
                                {{ aman }}%
                            </span>
                        </div>

                        <!-- Target Threshold Pin -->
                        <div
                            v-if="targetValue !== null && targetValue >= 0 && targetValue <= 100"
                            class="hcis-progress__target-pin"
                            :style="{ left: `${targetValue}%` }"
                            :title="`Target: ${targetValue}%`"
                        >
                            <div class="hcis-progress__target-line" />
                            <div class="hcis-progress__target-badge">{{ targetValue }}%</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions Slot -->
            <template v-if="$slots.footer" #footer>
                <slot name="footer" />
            </template>
        </HcisCard>

        <!-- ═══════════════════════════════════════════════════════════════════
             MODE 2: STANDALONE PROGRESS (Tanpa Card Wrapper)
             ═══════════════════════════════════════════════════════════════════ -->
        <div v-else class="hcis-progress__inner-body">
            <!-- 1. CIRCLE SVG MODE -->
            <div v-if="type === 'circle'" class="hcis-progress-circle-wrap">
                <div
                    class="hcis-progress-circle"
                    :class="[
                        `hcis-progress-circle--${size}`,
                        `hcis-progress-circle--${sentimentNormal}`,
                        {
                            'hcis-progress-circle--indeterminate': indeterminate,
                            'hcis-progress-circle--animated': animated,
                        }
                    ]"
                    role="progressbar"
                    :aria-valuenow="indeterminate ? undefined : aman"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="label || undefined"
                >
                    <svg class="hcis-progress-circle__svg" viewBox="0 0 100 100">
                        <defs>
                            <linearGradient :id="`circle-grad-${uid}`" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" :stop-color="gradientStops.start" />
                                <stop offset="100%" :stop-color="gradientStops.end" />
                            </linearGradient>
                        </defs>
                        <circle
                            class="hcis-progress-circle__track-bg"
                            cx="50"
                            cy="50"
                            :r="circleRadius"
                            :stroke-width="circleStrokeWidth"
                        />
                        <circle
                            class="hcis-progress-circle__fill"
                            cx="50"
                            cy="50"
                            :r="circleRadius"
                            :stroke-width="circleStrokeWidth"
                            :stroke="`url(#circle-grad-${uid})`"
                            :style="circleFillStyle"
                        />
                    </svg>

                    <div class="hcis-progress-circle__content">
                        <slot :value="aman">
                            <HcisIcon
                                v-if="aman >= 100 && !indeterminate"
                                name="check-circle-fill"
                                class="hcis-progress-circle__done-icon"
                            />
                            <span v-else-if="showValue && !indeterminate" class="hcis-progress-circle__value">
                                {{ aman }}<span class="hcis-progress-circle__unit">%</span>
                            </span>
                        </slot>
                    </div>
                </div>
            </div>

            <!-- 2. DASHBOARD ARC MODE -->
            <div v-else-if="type === 'dashboard'" class="hcis-progress-dashboard-wrap">
                <div
                    class="hcis-progress-dashboard"
                    :class="[
                        `hcis-progress-dashboard--${size}`,
                        `hcis-progress-dashboard--${sentimentNormal}`,
                        {
                            'hcis-progress-dashboard--indeterminate': indeterminate,
                            'hcis-progress-dashboard--animated': animated,
                        }
                    ]"
                    role="progressbar"
                    :aria-valuenow="indeterminate ? undefined : aman"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="label || undefined"
                >
                    <svg class="hcis-progress-dashboard__svg" viewBox="0 0 100 85">
                        <defs>
                            <linearGradient :id="`dash-grad-${uid}`" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" :stop-color="gradientStops.start" />
                                <stop offset="100%" :stop-color="gradientStops.end" />
                            </linearGradient>
                        </defs>
                        <path
                            class="hcis-progress-dashboard__track-bg"
                            :d="dashboardPath"
                            :stroke-width="circleStrokeWidth"
                            stroke-linecap="round"
                        />
                        <path
                            class="hcis-progress-dashboard__fill"
                            :d="dashboardPath"
                            :stroke-width="circleStrokeWidth"
                            stroke-linecap="round"
                            :stroke="`url(#dash-grad-${uid})`"
                            :style="dashboardFillStyle"
                        />
                    </svg>

                    <div class="hcis-progress-dashboard__content">
                        <slot :value="aman">
                            <span class="hcis-progress-dashboard__value">{{ aman }}<span class="hcis-progress-dashboard__unit">%</span></span>
                            <span v-if="label" class="hcis-progress-dashboard__label">{{ label }}</span>
                        </slot>
                    </div>
                </div>
            </div>

            <!-- 3. SEGMENTED MILESTONE BLOCKS -->
            <div
                v-else-if="type === 'segments'"
                class="hcis-progress-segments"
                :class="[
                    `hcis-progress-segments--${size}`,
                    `hcis-progress-segments--${sentimentNormal}`
                ]"
            >
                <div v-if="(label || showValue || subtitle) && !insideValue" class="hcis-progress__head">
                    <div class="hcis-progress__head-main">
                        <span v-if="label" class="hcis-progress__label">{{ label }}</span>
                        <span v-if="subtitle" class="hcis-progress__subtitle">{{ subtitle }}</span>
                    </div>
                    <div v-if="showValue" class="hcis-progress__value-badge">
                        <span>{{ completedSegments }}/{{ totalSegments }}</span>
                        <span class="text-slate-400 ms-1 font-mono">({{ aman }}%)</span>
                    </div>
                </div>

                <div class="hcis-progress-segments__track">
                    <div
                        v-for="seg in totalSegments"
                        :key="seg"
                        class="hcis-progress-segments__block"
                        :class="{
                            'hcis-progress-segments__block--active': seg <= completedSegments,
                            'hcis-progress-segments__block--current': seg === completedSegments + 1 && animated
                        }"
                    />
                </div>
            </div>

            <!-- 4. LINEAR CAPSULE RUNWAY (DEFAULT) -->
            <div
                v-else
                class="hcis-progress"
                :class="[
                    `hcis-progress--${size}`,
                    `hcis-progress--sentiment-${sentimentNormal}`,
                    {
                        'hcis-progress--striped': striped,
                        'hcis-progress--animated': animated,
                        'hcis-progress--indeterminate': indeterminate,
                        'hcis-progress--glow': glow,
                    }
                ]"
            >
                <div v-if="(label || showValue || subtitle) && !insideValue" class="hcis-progress__head">
                    <div class="hcis-progress__head-main">
                        <span v-if="label" class="hcis-progress__label">{{ label }}</span>
                        <span v-if="subtitle" class="hcis-progress__subtitle">{{ subtitle }}</span>
                    </div>
                    <div v-if="showValue && !indeterminate" class="hcis-progress__value">
                        <HcisIcon v-if="aman >= 100" name="check-circle-fill" size="xs" class="hcis-progress__done-icon me-1" />
                        <span class="hcis-progress__number">{{ aman }}%</span>
                    </div>
                </div>

                <div
                    class="hcis-progress__track"
                    role="progressbar"
                    :aria-valuenow="indeterminate ? undefined : aman"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="label || undefined"
                >
                    <div
                        class="hcis-progress__fill"
                        :class="[`hcis-progress__fill--${sentimentNormal}`]"
                        :style="lineFillStyle"
                    >
                        <div v-if="animated" class="hcis-progress__shimmer" />

                        <span
                            v-if="insideValue && (size === 'md' || size === 'lg' || size === 'xl') && !indeterminate"
                            class="hcis-progress__inner-text"
                        >
                            {{ aman }}%
                        </span>
                    </div>

                    <!-- Target Threshold Pin -->
                    <div
                        v-if="targetValue !== null && targetValue >= 0 && targetValue <= 100"
                        class="hcis-progress__target-pin"
                        :style="{ left: `${targetValue}%` }"
                        :title="`Target: ${targetValue}%`"
                    >
                        <div class="hcis-progress__target-line" />
                        <div class="hcis-progress__target-badge">{{ targetValue }}%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import HcisCard from './HcisCard.vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisProgress' });

const uid = Math.random().toString(36).substring(2, 9);

const props = defineProps({
    value: { type: Number, default: 0 },
    type: {
        type: String,
        default: 'line',
        validator: (v) => ['line', 'circle', 'dashboard', 'segments'].includes(v),
    },
    sentiment: {
        type: String,
        default: 'brand',
        validator: (v) => [
            'brand', 'primary', 'good', 'success', 'warning', 'bad', 'danger',
            'info', 'purple', 'violet', 'teal', 'cyan', 'neutral'
        ].includes(v),
    },
    label: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    showValue: { type: Boolean, default: true },
    insideValue: { type: Boolean, default: false },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['xs', 'sm', 'md', 'lg', 'xl'].includes(v),
    },
    striped: { type: Boolean, default: false },
    animated: { type: Boolean, default: false },
    indeterminate: { type: Boolean, default: false },
    glow: { type: Boolean, default: false },

    // Target Threshold Marker
    targetValue: { type: Number, default: null },

    // Segmented Mode Props
    segments: { type: Number, default: 5 },

    // HCIS Enterprise Suite Card Wrapper Props
    card: { type: Boolean, default: false },
    title: { type: String, default: '' },
    kicker: { type: String, default: '' },
    description: { type: String, default: '' },
    badge: { type: String, default: '' },
    badgeVariant: { type: String, default: '' },
    badgeRadar: { type: Boolean, default: false },
    icon: { type: String, default: '' },
    cardVariant: {
        type: String,
        default: 'glass',
        validator: (v) => ['glass', 'default', 'flat', 'raised', 'outlined', 'obsidian'].includes(v),
    },
    pattern: {
        type: String,
        default: 'none',
        validator: (v) => ['none', 'grid', 'dots', 'crosshair', 'stripes', 'rings', 'hex'].includes(v),
    },
    patternPlacement: { type: String, default: 'header' },
});

// Normalized sentiment mapping
const sentimentNormal = computed(() => {
    if (props.sentiment === 'good' || props.sentiment === 'success') return 'success';
    if (props.sentiment === 'bad' || props.sentiment === 'danger') return 'danger';
    if (props.sentiment === 'primary' || props.sentiment === 'brand') return 'brand';
    if (props.sentiment === 'violet' || props.sentiment === 'purple') return 'purple';
    if (props.sentiment === 'cyan' || props.sentiment === 'teal') return 'teal';
    return props.sentiment;
});

// Gradient stops for SVGs
const gradientStops = computed(() => {
    switch (sentimentNormal.value) {
        case 'success': return { start: '#059669', end: '#10b981' };
        case 'warning': return { start: '#d97706', end: '#f59e0b' };
        case 'danger': return { start: '#dc2626', end: '#ef4444' };
        case 'info': return { start: '#0284c7', end: '#0ea5e9' };
        case 'purple': return { start: '#7c3aed', end: '#8b5cf6' };
        case 'teal': return { start: '#0d9488', end: '#14b8a6' };
        case 'neutral': return { start: '#475569', end: '#64748b' };
        default: return { start: '#4f46e5', end: '#6366f1' };
    }
});

const aman = computed(() => Math.max(0, Math.min(100, Math.round(props.value))));

// Linear Fill Style
const lineFillStyle = computed(() => {
    if (props.indeterminate) {
        return { width: '100%' };
    }
    return {
        width: `${aman.value}%`,
    };
});

// Circular Progress Dimensions
const circleRadius = 42;
const circleStrokeWidth = computed(() => {
    if (props.size === 'xs') return 6;
    if (props.size === 'sm') return 7;
    if (props.size === 'lg') return 9;
    if (props.size === 'xl') return 10;
    return 8;
});

const circleFillStyle = computed(() => {
    const circumference = 2 * Math.PI * circleRadius; // 263.89
    const offset = circumference - (aman.value / 100) * circumference;
    return {
        strokeDasharray: `${circumference}`,
        strokeDashoffset: `${offset}`,
    };
});

// Dashboard Speedometer Arc Path (240 deg arc)
const dashboardPath = 'M 20 75 A 40 40 0 1 1 80 75';
const dashboardCircumference = 198;
const dashboardFillStyle = computed(() => {
    const offset = dashboardCircumference - (aman.value / 100) * dashboardCircumference;
    return {
        strokeDasharray: `${dashboardCircumference}`,
        strokeDashoffset: `${offset}`,
    };
});

// Segmented Milestone Mode
const totalSegments = computed(() => Math.max(2, props.segments));
const completedSegments = computed(() => {
    return Math.round((aman.value / 100) * totalSegments.value);
});
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   HCIS ENTERPRISE SUITE - PROGRESS COMPONENT
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-progress-wrapper {
    position: relative;
    width: 100%;
    font-family: var(--hcis-font-body, inherit);
}

.hcis-progress__inner-body {
    width: 100%;
}

.hcis-progress {
    position: relative;
    width: 100%;
}

/* Header Container */
.hcis-progress__head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 0.5rem;
}

.hcis-progress__head-main {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.hcis-progress__label {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    line-height: 1.25;
}

.hcis-progress__subtitle {
    font-size: 0.6875rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    font-weight: 500;
}

.hcis-progress__value {
    display: inline-flex;
    align-items: center;
    font-size: 0.8125rem;
    font-weight: 800;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

.hcis-progress__done-icon {
    color: var(--hcis-success-600, var(--hcis-emerald-600));
}

/* Runway Track */
.hcis-progress__track {
    position: relative;
    width: 100%;
    background: rgba(226, 232, 240, 0.85);
    border-radius: 999px;
    overflow: visible;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.06);
}

/* Track Heights per Size */
.hcis-progress--xs .hcis-progress__track { height: 0.25rem; }
.hcis-progress--sm .hcis-progress__track { height: 0.375rem; }
.hcis-progress--md .hcis-progress__track { height: 0.625rem; }
.hcis-progress--lg .hcis-progress__track { height: 1rem; }
.hcis-progress--xl .hcis-progress__track { height: 1.5rem; }

/* Active Progress Fill */
.hcis-progress__fill {
    position: relative;
    height: 100%;
    border-radius: 999px;
    transition: width 350ms cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 6px;
    overflow: hidden;
    box-shadow: none;
}

/* Sentiments Fill Solid Colors */
.hcis-progress__fill--brand {
    background: var(--hcis-indigo-600);
}
.hcis-progress--glow .hcis-progress__fill--brand {
    box-shadow: 0 0 8px rgba(99, 102, 241, 0.35);
}

.hcis-progress__fill--success {
    background: var(--hcis-emerald-600);
}
.hcis-progress--glow .hcis-progress__fill--success {
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.35);
}

.hcis-progress__fill--warning {
    background: var(--hcis-amber-600);
}
.hcis-progress--glow .hcis-progress__fill--warning {
    box-shadow: 0 0 8px rgba(245, 158, 11, 0.35);
}

.hcis-progress__fill--danger {
    background: var(--hcis-red-600);
}
.hcis-progress--glow .hcis-progress__fill--danger {
    box-shadow: 0 0 8px rgba(239, 68, 68, 0.35);
}

.hcis-progress__fill--info {
    background: var(--hcis-sky-600);
}
.hcis-progress--glow .hcis-progress__fill--info {
    box-shadow: 0 0 8px rgba(14, 165, 233, 0.35);
}

.hcis-progress__fill--purple {
    background: var(--hcis-violet-600);
}
.hcis-progress--glow .hcis-progress__fill--purple {
    box-shadow: 0 0 8px rgba(139, 92, 246, 0.35);
}

.hcis-progress__fill--teal {
    background: #0d9488;
}
.hcis-progress--glow .hcis-progress__fill--teal {
    box-shadow: 0 0 8px rgba(20, 184, 166, 0.35);
}

.hcis-progress__fill--neutral {
    background: var(--hcis-slate-600);
}

/* Inner Text inside Thick Bars */
.hcis-progress__inner-text {
    font-size: 0.6875rem;
    font-weight: 850;
    color: var(--hcis-white);
    line-height: 1;
    font-family: var(--hcis-font-mono, monospace);
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
}

/* Shimmer Light Bar Animation (Hanya jika .hcis-progress--animated aktif) */
.hcis-progress__shimmer {
    display: none;
}

.hcis-progress--animated .hcis-progress__shimmer {
    display: block;
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.45) 50%, transparent 100%);
    animation: hcis-progress-shimmer 2s infinite ease-in-out;
}

@keyframes hcis-progress-shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Striped Variant */
.hcis-progress--striped .hcis-progress__fill {
    background-image: linear-gradient(
        45deg,
        rgba(255, 255, 255, 0.2) 25%,
        transparent 25%,
        transparent 50%,
        rgba(255, 255, 255, 0.2) 50%,
        rgba(255, 255, 255, 0.2) 75%,
        transparent 75%,
        transparent
    );
    background-size: 1rem 1rem;
}

.hcis-progress--striped.hcis-progress--animated .hcis-progress__fill {
    animation: hcis-progress-stripes 1s linear infinite;
}

@keyframes hcis-progress-stripes {
    0% { background-position: 1rem 0; }
    100% { background-position: 0 0; }
}

/* Indeterminate Scanner Mode */
.hcis-progress--indeterminate .hcis-progress__fill {
    width: 40% !important;
    animation: hcis-progress-indeterminate 1.6s cubic-bezier(0.65, 0.815, 0.735, 0.395) infinite;
}

@keyframes hcis-progress-indeterminate {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(300%); }
}

/* Target Pin Marker */
.hcis-progress__target-pin {
    position: absolute;
    top: -4px;
    bottom: -4px;
    width: 2px;
    transform: translateX(-50%);
    z-index: 3;
    pointer-events: none;
}

.hcis-progress__target-line {
    width: 2px;
    height: 100%;
    background: var(--hcis-amber-500);
    box-shadow: 0 0 6px var(--hcis-amber-500);
    border-radius: 999px;
}

.hcis-progress__target-badge {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(-2px);
    font-size: 0.5625rem;
    font-weight: 850;
    font-family: var(--hcis-font-mono, monospace);
    background: var(--hcis-slate-900);
    color: var(--hcis-amber-500);
    padding: 1px 4px;
    border-radius: 4px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
    white-space: nowrap;
}

/* ═══════════════════════════════════════════════════════════════════════════
   CIRCULAR PROGRESS MODE
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-progress-circle-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 0;
}

.hcis-progress-circle {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.hcis-progress-circle--xs { width: 2.25rem; height: 2.25rem; }
.hcis-progress-circle--sm { width: 3.25rem; height: 3.25rem; }
.hcis-progress-circle--md { width: 4.75rem; height: 4.75rem; }
.hcis-progress-circle--lg { width: 6.5rem; height: 6.5rem; }
.hcis-progress-circle--xl { width: 8.5rem; height: 8.5rem; }

.hcis-progress-circle__svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.hcis-progress-circle__track-bg {
    fill: none;
    stroke: rgba(226, 232, 240, 0.85);
}

.hcis-progress-circle__fill {
    fill: none;
    stroke-linecap: round;
    transition: stroke-dashoffset 400ms cubic-bezier(0.16, 1, 0.3, 1);
    filter: drop-shadow(0 0 6px rgba(99, 102, 241, 0.45));
}

.hcis-progress-circle--success .hcis-progress-circle__fill {
    filter: drop-shadow(0 0 6px rgba(16, 185, 129, 0.45));
}
.hcis-progress-circle--warning .hcis-progress-circle__fill {
    filter: drop-shadow(0 0 6px rgba(245, 158, 11, 0.45));
}
.hcis-progress-circle--danger .hcis-progress-circle__fill {
    filter: drop-shadow(0 0 6px rgba(239, 68, 68, 0.45));
}

.hcis-progress-circle__content {
    position: absolute;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 850;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font-family: var(--hcis-font-heading, inherit);
}

.hcis-progress-circle--xs .hcis-progress-circle__content { font-size: 0.65rem; }
.hcis-progress-circle--sm .hcis-progress-circle__content { font-size: 0.78rem; }
.hcis-progress-circle--md .hcis-progress-circle__content { font-size: 1rem; }
.hcis-progress-circle--lg .hcis-progress-circle__content { font-size: 1.35rem; }
.hcis-progress-circle--xl .hcis-progress-circle__content { font-size: 1.75rem; }

.hcis-progress-circle__unit {
    font-size: 0.75em;
    font-weight: 650;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    margin-left: 1px;
}

.hcis-progress-circle__done-icon {
    color: var(--hcis-success-600, var(--hcis-emerald-600));
    font-size: 1.35em;
}

/* ═══════════════════════════════════════════════════════════════════════════
   DASHBOARD ARC MODE
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-progress-dashboard-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 0;
}

.hcis-progress-dashboard {
    position: relative;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.hcis-progress-dashboard--sm { width: 4.5rem; height: 3.8rem; }
.hcis-progress-dashboard--md { width: 6.5rem; height: 5.5rem; }
.hcis-progress-dashboard--lg { width: 8.5rem; height: 7.2rem; }

.hcis-progress-dashboard__svg {
    width: 100%;
    height: 100%;
}

.hcis-progress-dashboard__track-bg {
    fill: none;
    stroke: rgba(226, 232, 240, 0.85);
}

.hcis-progress-dashboard__fill {
    fill: none;
    stroke-linecap: round;
    transition: stroke-dashoffset 400ms cubic-bezier(0.16, 1, 0.3, 1);
    filter: drop-shadow(0 0 6px rgba(99, 102, 241, 0.45));
}

.hcis-progress-dashboard__content {
    position: absolute;
    bottom: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.hcis-progress-dashboard__value {
    font-size: 1.15rem;
    font-weight: 850;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font-family: var(--hcis-font-heading, inherit);
    line-height: 1;
}

.hcis-progress-dashboard__unit {
    font-size: 0.65em;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    margin-left: 1px;
}

.hcis-progress-dashboard__label {
    font-size: 0.625rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    letter-spacing: 0.06em;
    margin-top: 2px;
}

/* ═══════════════════════════════════════════════════════════════════════════
   SEGMENTED MILESTONE BLOCKS MODE
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-progress-segments {
    width: 100%;
}

.hcis-progress-segments__track {
    display: flex;
    gap: 5px;
    width: 100%;
}

.hcis-progress-segments__block {
    flex: 1;
    height: 0.5rem;
    border-radius: 4px;
    background: rgba(226, 232, 240, 0.85);
    transition-property: var(--hcis-transition-props);
    transition-duration: 250ms;
    transition-timing-function: ease;
}

.hcis-progress-segments--lg .hcis-progress-segments__block { height: 0.75rem; border-radius: 6px; }

.hcis-progress-segments--brand .hcis-progress-segments__block--active {
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    box-shadow: 0 0 8px rgba(79, 70, 229, 0.45);
}
.hcis-progress-segments--success .hcis-progress-segments__block--active {
    background: linear-gradient(135deg, var(--hcis-emerald-600) 0%, var(--hcis-emerald-500) 100%);
    box-shadow: 0 0 8px rgba(5, 150, 105, 0.45);
}
.hcis-progress-segments--warning .hcis-progress-segments__block--active {
    background: linear-gradient(135deg, var(--hcis-amber-600) 0%, var(--hcis-amber-500) 100%);
}
.hcis-progress-segments--danger .hcis-progress-segments__block--active {
    background: linear-gradient(135deg, var(--hcis-red-600) 0%, var(--hcis-red-500) 100%);
}

.hcis-progress-segments__block--current {
    animation: hcis-progress-segment-pulse 1.5s infinite ease-in-out;
}

@keyframes hcis-progress-segment-pulse {
    0%, 100% { opacity: 0.5; transform: scaleY(0.9); }
    50% { opacity: 1; transform: scaleY(1.1); }
}

.hcis-progress__value-badge {
    font-size: 0.75rem;
    font-weight: 750;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}
</style>
