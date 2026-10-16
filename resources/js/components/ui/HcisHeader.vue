<template>
    <section
        class="hcis-header"
        :class="[
            `hcis-header--layout-${layout}`,
            `hcis-header--size-${size}`,
            `hcis-header--variant-${variant}`,
            `hcis-header--sentiment-${sentiment}`,
            {
                'hcis-header--bordered': bordered,
                'hcis-header--animated': animated,
                'hcis-header--loading': loading,
            }
        ]"
    >
        <!-- Background Ambient Lighting & Subtle Grid -->
        <div v-if="layout !== 'minimal'" class="hcis-header__aurora" aria-hidden="true" />
        <div v-if="layout !== 'minimal'" class="hcis-header__grid-overlay" aria-hidden="true" />

        <div class="hcis-header__inner">
            <!-- Optional Breadcrumb Row -->
            <div v-if="$slots.breadcrumb" class="hcis-header__breadcrumb-row">
                <slot name="breadcrumb" />
            </div>

            <!-- Top Row: Icon + Title & Description + Actions -->
            <div class="hcis-header__top">
                <!-- Skeleton State -->
                <div
                    v-if="loading"
                    class="hcis-header__identity hcis-header__identity--loading"
                    :class="{
                        'hcis-header__identity--has-icon': !!icon || !!$slots.icon,
                        'hcis-header__identity--has-kicker': !!kicker || !!$slots.kicker,
                    }"
                >
                    <!-- Jewel Icon Prism Loading Badge -->
                    <div v-if="icon || $slots.icon" class="hcis-header__icon-badge hcis-header__icon-badge--loading" aria-hidden="true">
                        <div class="hcis-header__shimmer-prism">
                            <i class="bi bi-hourglass-split hcis-header__shimmer-spin-icon" />
                        </div>
                    </div>

                    <!-- Shimmer Kicker Pill -->
                    <div v-if="kicker || $slots.kicker" class="hcis-header__kicker">
                        <div class="hcis-header__shimmer-bar hcis-header__shimmer-bar--kicker" />
                    </div>

                    <!-- Shimmer Title -->
                    <div class="hcis-header__title-row">
                        <div class="hcis-header__shimmer-bar hcis-header__shimmer-bar--title" />
                    </div>

                    <!-- Shimmer Subtitle (2 Realistic Multi-line Staggered Bars) -->
                    <div class="hcis-header__subtitle">
                        <div class="hcis-header__shimmer-bar hcis-header__shimmer-bar--sub-1" />
                        <div class="hcis-header__shimmer-bar hcis-header__shimmer-bar--sub-2" />
                    </div>
                </div>

                <!-- Normal Identity Group -->
                <div
                    v-else
                    class="hcis-header__identity"
                    :class="{
                        'hcis-header__identity--has-icon': !!icon || !!$slots.icon,
                        'hcis-header__identity--has-kicker': !!kicker || !!$slots.kicker,
                    }"
                >
                    <!-- Icon Badge -->
                    <div v-if="icon || $slots.icon" class="hcis-header__icon-badge">
                        <slot name="icon">
                            <HcisIcon :name="icon" size="lg" />
                        </slot>
                    </div>

                    <!-- Optional Kicker / Eyebrow Label -->
                    <div v-if="kicker || $slots.kicker" class="hcis-header__kicker">
                        <slot name="kicker">{{ kicker }}</slot>
                    </div>

                    <!-- Title & Badge Row -->
                    <div class="hcis-header__title-row">
                        <h1
                            v-if="title || $slots.title"
                            class="hcis-header__title"
                            :class="{ 'hcis-header__title--truncate': truncate }"
                            :title="truncate && typeof title === 'string' ? title : undefined"
                        >
                            <slot name="title">{{ title }}</slot>
                        </h1>
                        <slot name="badge">
                            <HcisBadge
                                v-if="badge"
                                :label="badge"
                                :variant="resolvedBadgeVariant"
                                size="sm"
                                class="hcis-header__badge"
                            />
                        </slot>
                    </div>

                    <!-- Subtitle (Full-width underneath on Mobile) -->
                    <p
                        v-if="subtitle || description || $slots.subtitle || $slots.description"
                        class="hcis-header__subtitle"
                        :class="{
                            'hcis-header__subtitle--truncate': truncateSubtitle === true || truncate === true,
                            'hcis-header__subtitle--clamped': !!lines || !!clamp || typeof truncateSubtitle === 'number',
                        }"
                        :style="subtitleStyle"
                        :title="(truncateSubtitle || truncate) && (subtitle || description) ? (subtitle || description) : undefined"
                    >
                        <slot name="subtitle">
                            <slot name="description">{{ subtitle || description }}</slot>
                        </slot>
                    </p>
                </div>

                <!-- Right Action Buttons / Controls -->
                <div v-if="$slots.actions || $slots.right" class="hcis-header__actions">
                    <slot name="actions">
                        <slot name="right" />
                    </slot>
                </div>
            </div>

            <!-- Bottom Console / Stats / Filter Bar Slot -->
            <div v-if="$slots.console || $slots.stats || $slots.footer || $slots.default" class="hcis-header__console">
                <slot name="console">
                    <slot name="stats">
                        <slot name="footer">
                            <slot />
                        </slot>
                    </slot>
                </slot>
            </div>

            <!-- Integrated Bottom Header Tabs Navigation Slot -->
            <div v-if="$slots.tabs || $slots.nav" class="hcis-header__tabs-bar">
                <slot name="tabs">
                    <slot name="nav" />
                </slot>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisBadge from './HcisBadge.vue';
import HcisSkeleton from './HcisSkeleton.vue';

defineOptions({ name: 'HcisHeader' });

const props = defineProps({
    title: { type: String, default: '' },
    kicker: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    description: { type: String, default: '' },
    icon: { type: String, default: '' },
    badge: { type: String, default: '' },
    badgeVariant: { type: String, default: '' },
    sentiment: {
        type: String,
        default: 'brand',
        validator: (v) => ['brand', 'purple', 'violet', 'teal', 'cyan', 'success', 'warning', 'danger', 'info', 'neutral'].includes(v),
    },
    layout: {
        type: String,
        default: 'default',
        validator: (v) => ['default', 'compact', 'centered', 'stacked', 'inline', 'minimal'].includes(v),
    },
    variant: {
        type: String,
        default: 'glass',
        validator: (v) => ['glass', 'surface', 'default', 'minimal'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    bordered: { type: Boolean, default: true },
    animated: { type: Boolean, default: true },
    loading: { type: Boolean, default: false },
    truncate: { type: Boolean, default: false },
    truncateSubtitle: { type: [Boolean, Number], default: false },
    clamp: { type: [Number, String], default: null },
    lines: { type: [Number, String], default: null },
});

const resolvedBadgeVariant = computed(() => {
    if (props.badgeVariant) return props.badgeVariant;
    if (['brand', 'success', 'warning', 'danger', 'info', 'purple', 'teal'].includes(props.sentiment)) {
        return props.sentiment;
    }
    if (props.sentiment === 'violet') return 'purple';
    if (props.sentiment === 'cyan') return 'teal';
    return 'brand';
});

const subtitleStyle = computed(() => {
    const rawLines = props.lines || props.clamp || (typeof props.truncateSubtitle === 'number' ? props.truncateSubtitle : null);
    const parsedLines = Number(rawLines);
    if (parsedLines > 0) {
        return {
            display: '-webkit-box',
            WebkitLineClamp: parsedLines,
            WebkitBoxOrient: 'vertical',
            overflow: 'hidden',
        };
    }
    return {};
});
</script>

<style>
/* ==========================================================================
   HCIS HEADER COMPONENT (Enterprise Suite Signature - Sentiment Harmonized)
   ========================================================================== */

.hcis-header {
    position: relative;
    width: 100%;
    border-radius: var(--hcis-radius-xl);
    overflow: hidden;
    isolation: isolate;
    font-family: var(--hcis-font-body, inherit);
    color: var(--hcis-text-strong);
    box-sizing: border-box;
    transition-property: var(--hcis-transition-props), font-weight;
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

.hcis-header__inner {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    width: 100%;
}

/* Sizing Padding - Tightened & Ultra-Compact */
.hcis-header--size-sm .hcis-header__inner { padding: 0.75rem 1rem; }
.hcis-header--size-md .hcis-header__inner { padding: 1rem 1.35rem; }
.hcis-header--size-lg .hcis-header__inner { padding: 1.35rem 1.75rem; }

/* Static Ambient Aurora Lighting */
.hcis-header__aurora {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
}

/* Grid Pattern Overlay */
.hcis-header__grid-overlay {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    opacity: 0.22;
    -webkit-mask-image: linear-gradient(225deg, var(--hcis-black) 0%, rgba(0, 0, 0, 0.5) 40%, transparent 85%);
    mask-image: linear-gradient(225deg, var(--hcis-black) 0%, rgba(0, 0, 0, 0.5) 40%, transparent 85%);
    background-image: linear-gradient(rgb(var(--hcis-white-rgb) / 0.15) 1px, transparent 1px),
        linear-gradient(90deg, rgb(var(--hcis-white-rgb) / 0.15) 1px, transparent 1px);
    background-size: 32px 32px;
}

/* ═══════════════════════════════════════════════════════════════════════════
   SENTIMENT-ADAPTIVE AURORA LIGHTING & SURFACES
   ═══════════════════════════════════════════════════════════════════════════ */

/* 1. Sentiment: Brand (Signature Indigo) */
.hcis-header--sentiment-brand,
.hcis-header {
    background:
        radial-gradient(ellipse 750px 420px at 105% -15%, rgb(var(--hcis-brand-500-rgb) / 0.16), transparent 55%),
        radial-gradient(ellipse 650px 380px at -5% 115%, rgb(var(--hcis-brand-600-rgb) / 0.11), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-brand-50-rgb) / 0.95) 0%, var(--hcis-white) 35%, var(--hcis-white) 65%, rgb(var(--hcis-brand-50-rgb) / 0.55) 100%);
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.22);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 4px 20px -2px rgb(var(--hcis-slate-900-rgb) / 0.05);
}

.hcis-header--sentiment-brand .hcis-header__aurora {
    background:
        radial-gradient(500px 350px at 110% 20%, rgb(var(--hcis-brand-500-rgb) / 0.16), transparent 60%),
        radial-gradient(550px 380px at -10% 80%, rgb(var(--hcis-brand-600-rgb) / 0.12), transparent 60%),
        radial-gradient(400px 260px at 55% -20%, rgb(var(--hcis-brand-400-rgb) / 0.1), transparent 55%);
}

.hcis-header--sentiment-brand .hcis-header__icon-badge {
    background: linear-gradient(135deg, var(--hcis-brand-50), var(--hcis-brand-100));
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.25);
    color: var(--hcis-brand-600);
    box-shadow: 0 4px 14px rgb(var(--hcis-brand-500-rgb) / 0.14), inset 0 1px 0 var(--hcis-white);
}

.hcis-header--sentiment-brand .hcis-header__kicker { color: var(--hcis-brand-600); }

/* 2. Sentiment: Purple / Violet (Amethyst SOP Assesmen) */
.hcis-header--sentiment-purple,
.hcis-header--sentiment-violet {
    background:
        radial-gradient(ellipse 750px 420px at 105% -15%, rgba(124, 58, 237, 0.16), transparent 55%),
        radial-gradient(ellipse 650px 380px at -5% 115%, rgba(109, 40, 217, 0.11), transparent 55%),
        linear-gradient(225deg, rgba(245, 243, 255, 0.95) 0%, var(--hcis-white) 35%, var(--hcis-white) 65%, rgba(245, 243, 255, 0.55) 100%);
    border: 1px solid rgba(124, 58, 237, 0.24);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 4px 20px -2px rgba(124, 58, 237, 0.06);
}

.hcis-header--sentiment-purple .hcis-header__aurora,
.hcis-header--sentiment-violet .hcis-header__aurora {
    background:
        radial-gradient(500px 350px at 110% 20%, rgba(124, 58, 237, 0.16), transparent 60%),
        radial-gradient(550px 380px at -10% 80%, rgba(109, 40, 217, 0.12), transparent 60%),
        radial-gradient(400px 260px at 55% -20%, rgba(139, 92, 246, 0.1), transparent 55%);
}

.hcis-header--sentiment-purple .hcis-header__icon-badge,
.hcis-header--sentiment-violet .hcis-header__icon-badge {
    background: linear-gradient(135deg, var(--hcis-violet-50), #ede9fe);
    border: 1px solid rgba(124, 58, 237, 0.28);
    color: var(--hcis-violet-600);
    box-shadow: 0 4px 14px rgba(124, 58, 237, 0.15), inset 0 1px 0 var(--hcis-white);
}

.hcis-header--sentiment-purple .hcis-header__kicker,
.hcis-header--sentiment-violet .hcis-header__kicker { color: var(--hcis-violet-600); }

/* 3. Sentiment: Teal / Cyan (Mint Toska SOP Referensi) */
.hcis-header--sentiment-teal,
.hcis-header--sentiment-cyan {
    background:
        radial-gradient(ellipse 750px 420px at 105% -15%, rgba(13, 148, 136, 0.16), transparent 55%),
        radial-gradient(ellipse 650px 380px at -5% 115%, rgba(15, 118, 110, 0.11), transparent 55%),
        linear-gradient(225deg, rgba(240, 253, 250, 0.95) 0%, var(--hcis-white) 35%, var(--hcis-white) 65%, rgba(240, 253, 250, 0.55) 100%);
    border: 1px solid rgba(13, 148, 136, 0.24);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 4px 20px -2px rgba(13, 148, 136, 0.06);
}

.hcis-header--sentiment-teal .hcis-header__aurora,
.hcis-header--sentiment-cyan .hcis-header__aurora {
    background:
        radial-gradient(500px 350px at 110% 20%, rgba(13, 148, 136, 0.16), transparent 60%),
        radial-gradient(550px 380px at -10% 80%, rgba(15, 118, 110, 0.12), transparent 60%),
        radial-gradient(400px 260px at 55% -20%, rgba(20, 184, 166, 0.1), transparent 55%);
}

.hcis-header--sentiment-teal .hcis-header__icon-badge,
.hcis-header--sentiment-cyan .hcis-header__icon-badge {
    background: linear-gradient(135deg, #f0fdfa, #ccfbf1);
    border: 1px solid rgba(13, 148, 136, 0.28);
    color: #0d9488;
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.15), inset 0 1px 0 var(--hcis-white);
}

.hcis-header--sentiment-teal .hcis-header__kicker,
.hcis-header--sentiment-cyan .hcis-header__kicker { color: #0d9488; }

/* 4. Sentiment: Success (Emerald Zamrud) */
.hcis-header--sentiment-success {
    background:
        radial-gradient(ellipse 750px 420px at 105% -15%, rgb(var(--hcis-success-500-rgb) / 0.16), transparent 55%),
        radial-gradient(ellipse 650px 380px at -5% 115%, rgb(var(--hcis-success-600-rgb) / 0.11), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-success-50-rgb) / 0.95) 0%, var(--hcis-white) 35%, var(--hcis-white) 65%, rgb(var(--hcis-success-50-rgb) / 0.55) 100%);
    border: 1px solid rgb(var(--hcis-success-500-rgb) / 0.24);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 4px 20px -2px rgb(var(--hcis-success-500-rgb) / 0.06);
}

.hcis-header--sentiment-success .hcis-header__aurora {
    background:
        radial-gradient(500px 350px at 110% 20%, rgb(var(--hcis-success-500-rgb) / 0.16), transparent 60%),
        radial-gradient(550px 380px at -10% 80%, rgb(var(--hcis-success-600-rgb) / 0.12), transparent 60%),
        radial-gradient(400px 260px at 55% -20%, rgb(var(--hcis-success-400-rgb) / 0.1), transparent 55%);
}

.hcis-header--sentiment-success .hcis-header__icon-badge {
    background: linear-gradient(135deg, var(--hcis-success-50), var(--hcis-success-100));
    border: 1px solid rgb(var(--hcis-success-500-rgb) / 0.28);
    color: var(--hcis-success-600);
    box-shadow: 0 4px 14px rgb(var(--hcis-success-500-rgb) / 0.15), inset 0 1px 0 var(--hcis-white);
}

.hcis-header--sentiment-success .hcis-header__kicker { color: var(--hcis-success-700); }

/* 5. Sentiment: Warning (Amber Gold) */
.hcis-header--sentiment-warning {
    background:
        radial-gradient(ellipse 750px 420px at 105% -15%, rgb(var(--hcis-warning-500-rgb) / 0.16), transparent 55%),
        radial-gradient(ellipse 650px 380px at -5% 115%, rgb(var(--hcis-warning-600-rgb) / 0.11), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-warning-50-rgb) / 0.95) 0%, var(--hcis-white) 35%, var(--hcis-white) 65%, rgb(var(--hcis-warning-50-rgb) / 0.55) 100%);
    border: 1px solid rgb(var(--hcis-warning-500-rgb) / 0.26);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 4px 20px -2px rgb(var(--hcis-warning-500-rgb) / 0.06);
}

.hcis-header--sentiment-warning .hcis-header__aurora {
    background:
        radial-gradient(500px 350px at 110% 20%, rgb(var(--hcis-warning-500-rgb) / 0.16), transparent 60%),
        radial-gradient(550px 380px at -10% 80%, rgb(var(--hcis-warning-600-rgb) / 0.12), transparent 60%),
        radial-gradient(400px 260px at 55% -20%, rgb(var(--hcis-warning-400-rgb) / 0.1), transparent 55%);
}

.hcis-header--sentiment-warning .hcis-header__icon-badge {
    background: linear-gradient(135deg, var(--hcis-warning-50), var(--hcis-warning-100));
    border: 1px solid rgb(var(--hcis-warning-500-rgb) / 0.28);
    color: var(--hcis-warning-700);
    box-shadow: 0 4px 14px rgb(var(--hcis-warning-500-rgb) / 0.15), inset 0 1px 0 var(--hcis-white);
}

.hcis-header--sentiment-warning .hcis-header__kicker { color: var(--hcis-warning-800); }

/* 6. Sentiment: Danger (Rose Crimson) */
.hcis-header--sentiment-danger {
    background:
        radial-gradient(ellipse 750px 420px at 105% -15%, rgb(var(--hcis-danger-500-rgb) / 0.16), transparent 55%),
        radial-gradient(ellipse 650px 380px at -5% 115%, rgb(var(--hcis-danger-600-rgb) / 0.11), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-danger-50-rgb) / 0.95) 0%, var(--hcis-white) 35%, var(--hcis-white) 65%, rgb(var(--hcis-danger-50-rgb) / 0.55) 100%);
    border: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.24);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 4px 20px -2px rgb(var(--hcis-danger-500-rgb) / 0.06);
}

.hcis-header--sentiment-danger .hcis-header__aurora {
    background:
        radial-gradient(500px 350px at 110% 20%, rgb(var(--hcis-danger-500-rgb) / 0.16), transparent 60%),
        radial-gradient(550px 380px at -10% 80%, rgb(var(--hcis-danger-600-rgb) / 0.12), transparent 60%),
        radial-gradient(400px 260px at 55% -20%, rgb(var(--hcis-danger-400-rgb) / 0.1), transparent 55%);
}

.hcis-header--sentiment-danger .hcis-header__icon-badge {
    background: linear-gradient(135deg, var(--hcis-danger-50), var(--hcis-danger-100));
    border: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.28);
    color: var(--hcis-danger-600);
    box-shadow: 0 4px 14px rgb(var(--hcis-danger-500-rgb) / 0.15), inset 0 1px 0 var(--hcis-white);
}

.hcis-header--sentiment-danger .hcis-header__kicker { color: var(--hcis-danger-700); }

/* 7. Sentiment: Info (Sky Blue) */
.hcis-header--sentiment-info {
    background:
        radial-gradient(ellipse 750px 420px at 105% -15%, rgb(var(--hcis-info-500-rgb) / 0.16), transparent 55%),
        radial-gradient(ellipse 650px 380px at -5% 115%, rgb(var(--hcis-info-600-rgb) / 0.11), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-info-50-rgb) / 0.95) 0%, var(--hcis-white) 35%, var(--hcis-white) 65%, rgb(var(--hcis-info-50-rgb) / 0.55) 100%);
    border: 1px solid rgb(var(--hcis-info-500-rgb) / 0.24);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 4px 20px -2px rgb(var(--hcis-info-500-rgb) / 0.06);
}

.hcis-header--sentiment-info .hcis-header__aurora {
    background:
        radial-gradient(500px 350px at 110% 20%, rgb(var(--hcis-info-500-rgb) / 0.16), transparent 60%),
        radial-gradient(550px 380px at -10% 80%, rgb(var(--hcis-info-600-rgb) / 0.12), transparent 60%),
        radial-gradient(400px 260px at 55% -20%, rgb(var(--hcis-info-400-rgb) / 0.1), transparent 55%);
}

.hcis-header--sentiment-info .hcis-header__icon-badge {
    background: linear-gradient(135deg, var(--hcis-info-50), var(--hcis-info-100));
    border: 1px solid rgb(var(--hcis-info-500-rgb) / 0.28);
    color: var(--hcis-info-600);
    box-shadow: 0 4px 14px rgb(var(--hcis-info-500-rgb) / 0.15), inset 0 1px 0 var(--hcis-white);
}

.hcis-header--sentiment-info .hcis-header__kicker { color: var(--hcis-info-700); }

/* 8. Sentiment: Neutral (Pure Clean Frosted Glass) */
.hcis-header--sentiment-neutral {
    background:
        radial-gradient(ellipse 750px 420px at 105% -15%, rgb(var(--hcis-slate-200-rgb) / 0.28), transparent 55%),
        radial-gradient(ellipse 650px 380px at -5% 115%, rgb(var(--hcis-slate-300-rgb) / 0.18), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-white-rgb) / 0.98) 0%, var(--hcis-white) 35%, var(--hcis-white) 65%, rgb(var(--hcis-slate-50-rgb) / 0.7) 100%);
    border: 1px solid rgb(var(--hcis-slate-200-rgb) / 0.85);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 4px 16px -2px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-header--sentiment-neutral .hcis-header__icon-badge {
    background: var(--hcis-surface-soft);
    border: 1px solid rgb(var(--hcis-slate-300-rgb) / 0.6);
    color: var(--hcis-slate-600);
    box-shadow: 0 2px 6px rgb(var(--hcis-slate-900-rgb) / 0.04), inset 0 1px 0 var(--hcis-white);
}

.hcis-header--sentiment-neutral .hcis-header__kicker { color: var(--hcis-slate-500); }

/* Compact & Small Header Variants: Balanced Corner Radii & Luminous Center Body */
.hcis-header--size-sm,
.hcis-header--layout-compact {
    background:
        radial-gradient(circle 280px at 105% -20%, rgb(var(--hcis-brand-500-rgb) / 0.15), transparent 70%),
        radial-gradient(circle 250px at -10% 120%, rgb(var(--hcis-brand-600-rgb) / 0.1), transparent 70%),
        linear-gradient(225deg, rgb(var(--hcis-brand-50-rgb) / 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-brand-50-rgb) / 0.45) 100%);
}

.hcis-header--size-sm.hcis-header--sentiment-purple,
.hcis-header--size-sm.hcis-header--sentiment-violet,
.hcis-header--layout-compact.hcis-header--sentiment-purple,
.hcis-header--layout-compact.hcis-header--sentiment-violet {
    background:
        radial-gradient(circle 280px at 105% -20%, rgba(124, 58, 237, 0.15), transparent 70%),
        radial-gradient(circle 250px at -10% 120%, rgba(109, 40, 217, 0.1), transparent 70%),
        linear-gradient(225deg, rgba(245, 243, 255, 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgba(245, 243, 255, 0.45) 100%);
}

.hcis-header--size-sm.hcis-header--sentiment-teal,
.hcis-header--size-sm.hcis-header--sentiment-cyan,
.hcis-header--layout-compact.hcis-header--sentiment-teal,
.hcis-header--layout-compact.hcis-header--sentiment-cyan {
    background:
        radial-gradient(circle 280px at 105% -20%, rgba(13, 148, 136, 0.15), transparent 70%),
        radial-gradient(circle 250px at -10% 120%, rgba(15, 118, 110, 0.1), transparent 70%),
        linear-gradient(225deg, rgba(240, 253, 250, 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgba(240, 253, 250, 0.45) 100%);
}

.hcis-header--size-sm.hcis-header--sentiment-success,
.hcis-header--layout-compact.hcis-header--sentiment-success {
    background:
        radial-gradient(circle 280px at 105% -20%, rgb(var(--hcis-success-500-rgb) / 0.15), transparent 70%),
        radial-gradient(circle 250px at -10% 120%, rgb(var(--hcis-success-600-rgb) / 0.1), transparent 70%),
        linear-gradient(225deg, rgb(var(--hcis-success-50-rgb) / 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-success-50-rgb) / 0.45) 100%);
}

.hcis-header--size-sm.hcis-header--sentiment-warning,
.hcis-header--layout-compact.hcis-header--sentiment-warning {
    background:
        radial-gradient(circle 280px at 105% -20%, rgb(var(--hcis-warning-500-rgb) / 0.15), transparent 70%),
        radial-gradient(circle 250px at -10% 120%, rgb(var(--hcis-warning-600-rgb) / 0.1), transparent 70%),
        linear-gradient(225deg, rgb(var(--hcis-warning-50-rgb) / 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-warning-50-rgb) / 0.45) 100%);
}

.hcis-header--size-sm.hcis-header--sentiment-danger,
.hcis-header--layout-compact.hcis-header--sentiment-danger {
    background:
        radial-gradient(circle 280px at 105% -20%, rgb(var(--hcis-danger-500-rgb) / 0.15), transparent 70%),
        radial-gradient(circle 250px at -10% 120%, rgb(var(--hcis-danger-600-rgb) / 0.1), transparent 70%),
        linear-gradient(225deg, rgb(var(--hcis-danger-50-rgb) / 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-danger-50-rgb) / 0.45) 100%);
}

.hcis-header--size-sm.hcis-header--sentiment-info,
.hcis-header--layout-compact.hcis-header--sentiment-info {
    background:
        radial-gradient(circle 280px at 105% -20%, rgb(var(--hcis-info-500-rgb) / 0.15), transparent 70%),
        radial-gradient(circle 250px at -10% 120%, rgb(var(--hcis-info-600-rgb) / 0.1), transparent 70%),
        linear-gradient(225deg, rgb(var(--hcis-info-50-rgb) / 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-info-50-rgb) / 0.45) 100%);
}

.hcis-header--size-sm.hcis-header--sentiment-neutral,
.hcis-header--layout-compact.hcis-header--sentiment-neutral {
    background:
        radial-gradient(circle 280px at 105% -20%, rgb(var(--hcis-slate-200-rgb) / 0.25), transparent 70%),
        radial-gradient(circle 250px at -10% 120%, rgb(var(--hcis-slate-300-rgb) / 0.15), transparent 70%),
        linear-gradient(225deg, rgb(var(--hcis-white-rgb) / 0.98) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-slate-50-rgb) / 0.6) 100%);
}

.hcis-header--size-sm .hcis-header__aurora,
.hcis-header--layout-compact .hcis-header__aurora {
    opacity: 0.5;
}

/* ═══════════════════════════════════════════════════════════════════════════
   HEADER SECTIONS LAYOUT & TYPOGRAPHY
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-header__breadcrumb-row {
    margin-bottom: 0.75rem;
}

.hcis-header__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    width: 100%;
}

.hcis-header__identity {
    display: grid;
    grid-template-columns: auto 1fr;
    grid-template-rows: auto auto auto;
    grid-template-areas:
        "icon kicker"
        "icon title"
        "icon subtitle";
    align-items: center;
    column-gap: 1rem;
    row-gap: 0.15rem;
    min-width: 0;
    flex: 1;
}

.hcis-header__identity:not(.hcis-header__identity--has-icon) {
    grid-template-columns: 1fr;
    grid-template-areas:
        "kicker"
        "title"
        "subtitle";
    column-gap: 0;
}

.hcis-header__identity:not(.hcis-header__identity--has-kicker) {
    grid-template-areas:
        "icon title"
        "icon subtitle";
}

.hcis-header__identity:not(.hcis-header__identity--has-icon):not(.hcis-header__identity--has-kicker) {
    grid-template-areas:
        "title"
        "subtitle";
}

/* ═══════════════════════════════════════════════════════════════════════════
   PREMIUM ULTRA-LUMINOUS LOADING SYSTEM
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-header--loading {
    position: relative;
    overflow: hidden;
}

/* Shimmer Bars Base & Specular Wave */
.hcis-header__shimmer-bar {
    position: relative;
    overflow: hidden;
    background: rgb(var(--hcis-slate-300-rgb) / 0.45);
    border-radius: var(--hcis-radius-sm);
    box-shadow: inset 0 1px 2px rgb(0 0 0 / 0.04);
}

.hcis-header__shimmer-bar::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.95) 50%, transparent 100%);
    transform: translateX(-100%);
    animation: hcis-shimmer-sweep 1.5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

@keyframes hcis-shimmer-sweep {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Element Specific Dimensions */
.hcis-header__shimmer-bar--kicker {
    width: 110px;
    height: 0.65rem;
    border-radius: var(--hcis-radius-pill);
    margin-bottom: 0.05rem;
}

.hcis-header__shimmer-bar--title {
    width: 320px;
    height: 1.45rem;
    max-width: 85%;
    border-radius: var(--hcis-radius-md);
}

.hcis-header__shimmer-bar--sub-1 {
    width: 460px;
    height: 0.8rem;
    max-width: 95%;
    margin-bottom: 0.3rem;
}

.hcis-header__shimmer-bar--sub-2 {
    width: 280px;
    height: 0.8rem;
    max-width: 70%;
}

/* Staggered Delay for Organic Flow */
.hcis-header__shimmer-bar--kicker::after { animation-delay: 0.05s; }
.hcis-header__shimmer-bar--title::after { animation-delay: 0.15s; }
.hcis-header__shimmer-bar--sub-1::after { animation-delay: 0.25s; }
.hcis-header__shimmer-bar--sub-2::after { animation-delay: 0.35s; }

/* 3. Jewel Icon Prism Loading Badge */
.hcis-header__icon-badge--loading {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    padding: 0 !important;
}

.hcis-header__shimmer-prism {
    width: 100%;
    height: 100%;
    border-radius: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, rgb(var(--hcis-slate-200-rgb) / 0.7), rgb(var(--hcis-slate-300-rgb) / 0.5));
    box-shadow: inset 0 1px 1px var(--hcis-white), 0 4px 12px rgb(0 0 0 / 0.04);
}

.hcis-header__shimmer-prism::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.95) 50%, transparent 100%);
    transform: translateX(-100%) rotate(25deg);
    animation: hcis-shimmer-sweep 1.5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

.hcis-header__shimmer-spin-icon {
    font-size: 1.15rem;
    color: var(--hcis-brand-600);
    opacity: 0.8;
    animation: hcis-spin-pulse 2.2s ease-in-out infinite;
    position: relative;
    z-index: 2;
}

.hcis-header--sentiment-purple .hcis-header__shimmer-spin-icon,
.hcis-header--sentiment-violet .hcis-header__shimmer-spin-icon { color: var(--hcis-violet-600); }
.hcis-header--sentiment-teal .hcis-header__shimmer-spin-icon,
.hcis-header--sentiment-cyan .hcis-header__shimmer-spin-icon { color: #0d9488; }
.hcis-header--sentiment-success .hcis-header__shimmer-spin-icon { color: var(--hcis-success-600); }
.hcis-header--sentiment-warning .hcis-header__shimmer-spin-icon { color: var(--hcis-warning-700); }
.hcis-header--sentiment-danger .hcis-header__shimmer-spin-icon { color: var(--hcis-danger-600); }
.hcis-header--sentiment-info .hcis-header__shimmer-spin-icon { color: var(--hcis-info-600); }

@keyframes hcis-spin-pulse {
    0% { transform: rotate(0deg) scale(0.9); opacity: 0.5; }
    50% { transform: rotate(180deg) scale(1.1); opacity: 0.95; }
    100% { transform: rotate(360deg) scale(0.9); opacity: 0.5; }
}

.hcis-header__identity--loading .hcis-header__title-row,
.hcis-header__identity--loading .hcis-header__subtitle {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    width: 100%;
}

.hcis-header--layout-centered .hcis-header__identity--loading .hcis-header__title-row,
.hcis-header--layout-centered .hcis-header__identity--loading .hcis-header__subtitle {
    align-items: center;
    justify-content: center;
}

/* Jewel-Glass Icon Badge - Compact & Proportional */
.hcis-header__icon-badge {
    grid-area: icon;
    align-self: center;
    flex-shrink: 0;
    width: 2.85rem;
    height: 2.85rem;
    border-radius: var(--hcis-radius-lg);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    transition: box-shadow var(--hcis-duration-fast) var(--hcis-ease), transform var(--hcis-duration-fast) var(--hcis-ease);
}

.hcis-header--size-sm .hcis-header__icon-badge {
    width: 2.25rem;
    height: 2.25rem;
    font-size: 1.15rem;
    border-radius: var(--hcis-radius-md);
}

.hcis-header--size-lg .hcis-header__icon-badge {
    width: 3.35rem;
    height: 3.35rem;
    font-size: 1.65rem;
    border-radius: var(--hcis-radius-xl);
}

.hcis-header__icon-badge:hover {
    transform: scale(1.04);
}

/* Kicker / Eyebrow Label */
.hcis-header__kicker {
    grid-area: kicker;
    font-size: 0.68rem;
    font-weight: 750;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    line-height: 1.2;
    margin-bottom: 0.05rem;
}

.hcis-header__title-row {
    grid-area: title;
    display: flex;
    align-items: center;
    gap: 0.625rem;
    flex-wrap: wrap;
    min-width: 0;
}

.hcis-header .hcis-header__title,
.hcis-header h1.hcis-header__title {
    font-size: 1.35rem;
    font-weight: 750;
    margin: 0;
    line-height: 1.25;
    letter-spacing: -0.02em;
    color: var(--hcis-slate-800);
}

.hcis-header--size-sm .hcis-header__title { font-size: 1.15rem; }
.hcis-header--size-lg .hcis-header__title { font-size: 1.65rem; }

.hcis-header .hcis-header__subtitle,
.hcis-header p.hcis-header__subtitle {
    grid-area: subtitle;
    font-size: 0.8125rem;
    margin: 0;
    line-height: 1.45;
    letter-spacing: -0.01em;
    color: var(--hcis-slate-500);
}

/* Truncation & Line-Clamping Rules */
.hcis-header__title--truncate,
.hcis-header--truncate .hcis-header__title {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.hcis-header .hcis-header__subtitle,
.hcis-header p.hcis-header__subtitle {
    font-size: 0.8125rem;
    margin: 0;
    line-height: 1.45;
    letter-spacing: -0.01em;
    color: var(--hcis-slate-500);
}

.hcis-header__subtitle--truncate {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.hcis-header--layout-compact .hcis-header__subtitle {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.hcis-header__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

/* ═══════════════════════════════════════════════════════════════════════════
   DUAL-LAYER CRYSTAL ETCHED DIVIDERS & CONSOLE
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-header__console {
    margin-top: 1rem;
    padding-top: 0.85rem;
    border-top: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.22);
    box-shadow: inset 0 1px 0 rgb(var(--hcis-white-rgb) / 0.8);
}

.hcis-header--sentiment-purple .hcis-header__console,
.hcis-header--sentiment-violet .hcis-header__console {
    border-color: rgba(124, 58, 237, 0.2);
}

.hcis-header--sentiment-teal .hcis-header__console,
.hcis-header--sentiment-cyan .hcis-header__console {
    border-color: rgba(13, 148, 136, 0.2);
}

.hcis-header--sentiment-success .hcis-header__console {
    border-color: rgb(var(--hcis-success-500-rgb) / 0.2);
}

.hcis-header--sentiment-warning .hcis-header__console {
    border-color: rgb(var(--hcis-warning-500-rgb) / 0.2);
}

.hcis-header--sentiment-danger .hcis-header__console {
    border-color: rgb(var(--hcis-danger-500-rgb) / 0.2);
}

.hcis-header--sentiment-info .hcis-header__console {
    border-color: rgb(var(--hcis-info-500-rgb) / 0.2);
}

.hcis-header--sentiment-neutral .hcis-header__console {
    border-color: rgb(var(--hcis-slate-200-rgb) / 0.85);
}

/* ═══════════════════════════════════════════════════════════════════════════
   INTEGRATED BOTTOM HEADER TABS DOCK
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-header__tabs-bar {
    background: rgb(var(--hcis-brand-500-rgb) / 0.03);
    margin-top: 1rem;
    margin-bottom: -1rem;
    margin-left: -1.35rem;
    margin-right: -1.35rem;
    padding-left: 1.35rem;
    padding-right: 1.35rem;
    padding-top: 0.25rem;
    border-top: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.22);
    box-shadow: inset 0 1px 0 rgb(var(--hcis-white-rgb) / 0.8);
    display: flex;
    align-items: flex-end;
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: none;
    -ms-overflow-style: none;
    -webkit-overflow-scrolling: touch;
}

.hcis-header--size-sm .hcis-header__tabs-bar {
    margin-bottom: -0.75rem;
    margin-left: -1rem;
    margin-right: -1rem;
    padding-left: 1rem;
    padding-right: 1rem;
}

.hcis-header--size-lg .hcis-header__tabs-bar {
    margin-bottom: -1.35rem;
    margin-left: -1.75rem;
    margin-right: -1.75rem;
    padding-left: 1.75rem;
    padding-right: 1.75rem;
}

.hcis-header__tabs-bar::-webkit-scrollbar {
    display: none;
}

.hcis-header--sentiment-purple .hcis-header__tabs-bar,
.hcis-header--sentiment-violet .hcis-header__tabs-bar {
    background: rgba(124, 58, 237, 0.03);
    border-color: rgba(124, 58, 237, 0.2);
}

.hcis-header--sentiment-teal .hcis-header__tabs-bar,
.hcis-header--sentiment-cyan .hcis-header__tabs-bar {
    background: rgba(13, 148, 136, 0.03);
    border-color: rgba(13, 148, 136, 0.2);
}

.hcis-header--sentiment-success .hcis-header__tabs-bar {
    background: rgb(var(--hcis-success-500-rgb) / 0.03);
    border-color: rgb(var(--hcis-success-500-rgb) / 0.2);
}

.hcis-header--sentiment-warning .hcis-header__tabs-bar {
    background: rgb(var(--hcis-warning-500-rgb) / 0.03);
    border-color: rgb(var(--hcis-warning-500-rgb) / 0.2);
}

.hcis-header--sentiment-danger .hcis-header__tabs-bar {
    background: rgb(var(--hcis-danger-500-rgb) / 0.03);
    border-color: rgb(var(--hcis-danger-500-rgb) / 0.2);
}

.hcis-header--sentiment-info .hcis-header__tabs-bar {
    background: rgb(var(--hcis-info-500-rgb) / 0.03);
    border-color: rgb(var(--hcis-info-500-rgb) / 0.2);
}

.hcis-header--sentiment-neutral .hcis-header__tabs-bar {
    background: transparent;
    border-color: rgb(var(--hcis-slate-200-rgb) / 0.85);
}

.hcis-header .hcis-tabs__tab::before,
.hcis-header .hcis-tabs__tab:hover::before,
.hcis-header .hcis-tabs--variant-header .hcis-tabs__tab::before,
.hcis-header .hcis-tabs--variant-header .hcis-tabs__tab:hover::before {
    display: none !important;
    content: none !important;
    opacity: 0 !important;
}

.hcis-header .hcis-tabs--variant-header .hcis-tabs__tab {
    padding: 0.7rem 1.2rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--hcis-slate-600);
    border-radius: var(--hcis-radius-md) var(--hcis-radius-md) 0 0;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    min-height: 2.6rem;
    box-shadow: none !important;
    transition: color var(--hcis-duration-fast) var(--hcis-ease);
}

.hcis-header .hcis-tabs--variant-header .hcis-tabs__tab .hcis-tabs__icon {
    color: var(--hcis-slate-400);
    transition: color var(--hcis-duration-fast) var(--hcis-ease);
}

.hcis-header .hcis-tabs--variant-header .hcis-tabs__tab,
.hcis-header .hcis-tabs--variant-header .hcis-tabs__tab:hover,
.hcis-header .hcis-tabs--variant-header .hcis-tabs__tab.is-active {
    box-shadow: none !important;
    text-shadow: none !important;
}

.hcis-header .hcis-tabs--variant-header .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) {
    color: var(--hcis-brand-600) !important;
    background: transparent !important;
    box-shadow: none !important;
    transform: none !important;
}

.hcis-header--sentiment-purple .hcis-tabs--variant-header .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active),
.hcis-header--sentiment-violet .hcis-tabs--variant-header .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) {
    color: var(--hcis-violet-600) !important;
}

.hcis-header--sentiment-teal .hcis-tabs--variant-header .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active),
.hcis-header--sentiment-cyan .hcis-tabs--variant-header .hcis-tabs__tab:hover:not(.is-disabled):not(.is-active) {
    color: #0d9488 !important;
}

.hcis-header .hcis-tabs--variant-header .hcis-tabs__tab.is-active {
    color: var(--hcis-brand-700);
    font-weight: 700;
    background-color: transparent !important;
}

.hcis-header--sentiment-purple .hcis-tabs--variant-header .hcis-tabs__tab.is-active,
.hcis-header--sentiment-violet .hcis-tabs--variant-header .hcis-tabs__tab.is-active {
    color: var(--hcis-violet-700);
}

.hcis-header--sentiment-teal .hcis-tabs--variant-header .hcis-tabs__tab.is-active,
.hcis-header--sentiment-cyan .hcis-tabs--variant-header .hcis-tabs__tab.is-active {
    color: #0f766e;
}

.hcis-header .hcis-tabs--variant-header .hcis-tabs__tab.is-active .hcis-tabs__icon {
    color: var(--hcis-brand-600);
}

.hcis-header--sentiment-purple .hcis-tabs--variant-header .hcis-tabs__tab.is-active .hcis-tabs__icon,
.hcis-header--sentiment-violet .hcis-tabs--variant-header .hcis-tabs__tab.is-active .hcis-tabs__icon {
    color: var(--hcis-violet-600);
}

.hcis-header--sentiment-teal .hcis-tabs--variant-header .hcis-tabs__tab.is-active .hcis-tabs__icon,
.hcis-header--sentiment-cyan .hcis-tabs--variant-header .hcis-tabs__tab.is-active .hcis-tabs__icon {
    color: #0d9488;
}

.hcis-header .hcis-tabs--variant-header .hcis-tabs__indicator {
    background: var(--hcis-brand-600);
    height: 2.5px;
    border-radius: var(--hcis-radius-pill);
    box-shadow: none !important;
}

.hcis-header--sentiment-purple .hcis-tabs--variant-header .hcis-tabs__indicator,
.hcis-header--sentiment-violet .hcis-tabs--variant-header .hcis-tabs__indicator {
    background: var(--hcis-violet-600);
}

.hcis-header--sentiment-teal .hcis-tabs--variant-header .hcis-tabs__indicator,
.hcis-header--sentiment-cyan .hcis-tabs--variant-header .hcis-tabs__indicator {
    background: #0d9488;
}

.hcis-header--size-sm .hcis-header__tabs-bar {
    margin-bottom: -1rem;
    margin-left: -1.25rem;
    margin-right: -1.25rem;
    padding-left: 1.25rem;
    padding-right: 1.25rem;
}

.hcis-header--size-lg .hcis-header__tabs-bar {
    margin-bottom: -1.85rem;
    margin-left: -2.25rem;
    margin-right: -2.25rem;
    padding-left: 2.25rem;
    padding-right: 2.25rem;
}

/* ═══════════════════════════════════════════════════════════════════════════
   LAYOUT VARIATIONS
   ═══════════════════════════════════════════════════════════════════════════ */

/* Layout 1: Compact */
.hcis-header--layout-compact .hcis-header__inner {
    padding: 0.65rem 1.15rem;
}

.hcis-header--layout-compact .hcis-header__identity {
    column-gap: 0.75rem;
    row-gap: 0.05rem;
}

.hcis-header--layout-compact .hcis-header__icon-badge {
    width: 2.15rem;
    height: 2.15rem;
    font-size: 1.1rem;
    border-radius: var(--hcis-radius-md);
}

.hcis-header--layout-compact .hcis-header__title,
.hcis-header--layout-compact h1.hcis-header__title {
    font-size: 1.1rem;
    line-height: 1.25;
}

.hcis-header--layout-compact .hcis-header__subtitle {
    font-size: 0.775rem;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hcis-header--layout-compact .hcis-header__console {
    margin-top: 0.65rem;
    padding-top: 0.65rem;
}

.hcis-header--layout-compact .hcis-header__tabs-bar {
    margin-top: 0.65rem;
    margin-bottom: -0.65rem;
    margin-left: -1.15rem;
    margin-right: -1.15rem;
    padding-left: 1.15rem;
    padding-right: 1.15rem;
}

/* Layout 2: Centered / Hero Spotlight */
.hcis-header--layout-centered .hcis-header__top {
    flex-direction: column;
    align-items: center;
    text-align: center;
    justify-content: center;
    gap: 0.75rem;
}

.hcis-header--layout-centered .hcis-header__identity {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.35rem;
    width: 100%;
}

.hcis-header--layout-centered .hcis-header__icon-badge {
    width: 3.5rem;
    height: 3.5rem;
    font-size: 1.75rem;
    border-radius: var(--hcis-radius-xl, 1rem);
    margin-bottom: 0.25rem;
}

.hcis-header--layout-centered .hcis-header__kicker {
    text-align: center;
    margin-bottom: 0.05rem;
}

.hcis-header--layout-centered .hcis-header__title-row {
    justify-content: center;
    text-align: center;
}

.hcis-header--layout-centered .hcis-header__title,
.hcis-header--layout-centered h1.hcis-header__title {
    text-align: center;
    font-size: 1.5rem;
}

.hcis-header--layout-centered .hcis-header__subtitle,
.hcis-header--layout-centered p.hcis-header__subtitle {
    max-width: 680px;
    margin-left: auto;
    margin-right: auto;
    text-align: center;
    font-size: 0.875rem;
    line-height: 1.5;
}

.hcis-header--layout-centered .hcis-header__actions {
    justify-content: center;
    margin-top: 0.5rem;
}

.hcis-header--layout-centered .hcis-header__tabs-bar {
    justify-content: center;
}

/* Layout 3: Stacked Toolbar */
.hcis-header--layout-stacked .hcis-header__top {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.85rem;
}

.hcis-header--layout-stacked .hcis-header__actions {
    width: 100%;
    justify-content: flex-start;
    border-top: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.22);
    box-shadow: inset 0 1px 0 rgb(var(--hcis-white-rgb) / 0.8);
    padding-top: 0.85rem;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* Layout 4: Inline Single Row */
.hcis-header--layout-inline .hcis-header__identity {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}

.hcis-header--layout-inline .hcis-header__subtitle,
.hcis-header--layout-inline p.hcis-header__subtitle {
    margin: 0;
    display: inline-flex;
    align-items: center;
}

.hcis-header--layout-inline .hcis-header__subtitle::before {
    content: '—';
    margin-right: 0.6rem;
    color: var(--hcis-brand-500);
    font-weight: 600;
}

/* Layout 5: Minimal Seamless */
.hcis-header--layout-minimal {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    border-radius: 0;
}

.hcis-header--layout-minimal .hcis-header__inner {
    padding: 0.5rem 0;
}

.hcis-header--layout-minimal .hcis-header__aurora,
.hcis-header--layout-minimal .hcis-header__grid-overlay {
    display: none;
}

.hcis-header--layout-minimal .hcis-header__title {
    text-shadow: none;
}

.hcis-header--layout-minimal .hcis-header__subtitle {
    text-shadow: none;
}

/* ═══════════════════════════════════════════════════════════════════════════
   RESPONSIVE TABLET & MOBILE BEHAVIOR (Ergonomic, Compact, & Touch-Friendly)
   ═══════════════════════════════════════════════════════════════════════════ */

/* 1. Tablet Viewports (768px to 1024px) -------------------------------------- */
@media (max-width: 1024px) {
    .hcis-header--size-sm .hcis-header__inner { padding: 0.85rem 1.15rem; }
    .hcis-header--size-md .hcis-header__inner { padding: 1.15rem 1.35rem; }
    .hcis-header--size-lg .hcis-header__inner { padding: 1.45rem 1.75rem; }

    .hcis-header__icon-badge {
        width: 3rem;
        height: 3rem;
        font-size: 1.45rem;
        border-radius: var(--hcis-radius-lg);
    }

    .hcis-header__title,
    .hcis-header h1.hcis-header__title {
        font-size: 1.35rem;
    }

    .hcis-header--size-sm .hcis-header__title { font-size: 1.2rem; }
    .hcis-header--size-lg .hcis-header__title { font-size: 1.65rem; }

    .hcis-header__tabs-bar {
        margin-left: -1.35rem;
        margin-right: -1.35rem;
        padding-left: 1.35rem;
        padding-right: 1.35rem;
        margin-bottom: -1.15rem;
    }
}

/* 2. Mobile Viewports (<= 768px) --------------------------------------------- */
@media (max-width: 768px) {
    .hcis-header {
        border-radius: var(--hcis-radius-lg);
        background:
            radial-gradient(circle 280px at 105% -20%, rgb(var(--hcis-brand-500-rgb) / 0.15), transparent 70%),
            radial-gradient(circle 250px at -10% 120%, rgb(var(--hcis-brand-600-rgb) / 0.1), transparent 70%),
            linear-gradient(225deg, rgb(var(--hcis-brand-50-rgb) / 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-brand-50-rgb) / 0.45) 100%) !important;
    }

    .hcis-header--sentiment-purple,
    .hcis-header--sentiment-violet {
        background:
            radial-gradient(circle 280px at 105% -20%, rgba(124, 58, 237, 0.15), transparent 70%),
            radial-gradient(circle 250px at -10% 120%, rgba(109, 40, 217, 0.1), transparent 70%),
            linear-gradient(225deg, rgba(245, 243, 255, 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgba(245, 243, 255, 0.45) 100%) !important;
    }

    .hcis-header--sentiment-teal,
    .hcis-header--sentiment-cyan {
        background:
            radial-gradient(circle 280px at 105% -20%, rgba(13, 148, 136, 0.15), transparent 70%),
            radial-gradient(circle 250px at -10% 120%, rgba(15, 118, 110, 0.1), transparent 70%),
            linear-gradient(225deg, rgba(240, 253, 250, 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgba(240, 253, 250, 0.45) 100%) !important;
    }

    .hcis-header--sentiment-success {
        background:
            radial-gradient(circle 280px at 105% -20%, rgb(var(--hcis-success-500-rgb) / 0.15), transparent 70%),
            radial-gradient(circle 250px at -10% 120%, rgb(var(--hcis-success-600-rgb) / 0.1), transparent 70%),
            linear-gradient(225deg, rgb(var(--hcis-success-50-rgb) / 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-success-50-rgb) / 0.45) 100%) !important;
    }

    .hcis-header--sentiment-warning {
        background:
            radial-gradient(circle 280px at 105% -20%, rgb(var(--hcis-warning-500-rgb) / 0.15), transparent 70%),
            radial-gradient(circle 250px at -10% 120%, rgb(var(--hcis-warning-600-rgb) / 0.1), transparent 70%),
            linear-gradient(225deg, rgb(var(--hcis-warning-50-rgb) / 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-warning-50-rgb) / 0.45) 100%) !important;
    }

    .hcis-header--sentiment-danger {
        background:
            radial-gradient(circle 280px at 105% -20%, rgb(var(--hcis-danger-500-rgb) / 0.15), transparent 70%),
            radial-gradient(circle 250px at -10% 120%, rgb(var(--hcis-danger-600-rgb) / 0.1), transparent 70%),
            linear-gradient(225deg, rgb(var(--hcis-danger-50-rgb) / 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-danger-50-rgb) / 0.45) 100%) !important;
    }

    .hcis-header--sentiment-info {
        background:
            radial-gradient(circle 280px at 105% -20%, rgb(var(--hcis-info-500-rgb) / 0.15), transparent 70%),
            radial-gradient(circle 250px at -10% 120%, rgb(var(--hcis-info-600-rgb) / 0.1), transparent 70%),
            linear-gradient(225deg, rgb(var(--hcis-info-50-rgb) / 0.85) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-info-50-rgb) / 0.45) 100%) !important;
    }

    .hcis-header--sentiment-neutral {
        background:
            radial-gradient(circle 280px at 105% -20%, rgb(var(--hcis-slate-200-rgb) / 0.25), transparent 70%),
            radial-gradient(circle 250px at -10% 120%, rgb(var(--hcis-slate-300-rgb) / 0.15), transparent 70%),
            linear-gradient(225deg, rgb(var(--hcis-white-rgb) / 0.98) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-slate-50-rgb) / 0.6) 100%) !important;
    }

    .hcis-header__aurora {
        opacity: 0.5;
    }

    .hcis-header--size-sm .hcis-header__inner { padding: 0.75rem 0.875rem; }
    .hcis-header--size-md .hcis-header__inner { padding: 0.85rem 1rem; }
    .hcis-header--size-lg .hcis-header__inner { padding: 1rem 1.125rem; }

    .hcis-header__top {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }

    /* Mobile Grid: Kicker on top, Icon directly beside Title, Subtitle 100% full-width below */
    .hcis-header__identity {
        display: grid;
        grid-template-columns: auto 1fr;
        grid-template-rows: auto auto auto;
        grid-template-areas:
            "kicker kicker"
            "icon title"
            "subtitle subtitle";
        column-gap: 0.6rem;
        row-gap: 0.25rem;
        align-items: center;
        width: 100%;
    }

    .hcis-header__identity:not(.hcis-header__identity--has-icon) {
        grid-template-columns: 1fr;
        grid-template-areas:
            "kicker"
            "title"
            "subtitle";
        column-gap: 0;
    }

    .hcis-header__identity:not(.hcis-header__identity--has-kicker) {
        grid-template-areas:
            "icon title"
            "subtitle subtitle";
    }

    .hcis-header__identity:not(.hcis-header__identity--has-icon):not(.hcis-header__identity--has-kicker) {
        grid-template-areas:
            "title"
            "subtitle";
    }

    .hcis-header__icon-badge {
        grid-area: icon;
        align-self: center;
        width: 2.35rem;
        height: 2.35rem;
        border-radius: var(--hcis-radius-md);
        font-size: 1.15rem;
        margin: 0;
    }

    .hcis-header__kicker {
        grid-area: kicker;
        font-size: 0.65rem;
        font-weight: 750;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        line-height: 1.2;
        margin-bottom: 0.05rem;
    }

    .hcis-header__title-row {
        grid-area: title;
        display: flex;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: nowrap;
        min-width: 0;
        width: 100%;
    }

    .hcis-header__title,
    .hcis-header h1.hcis-header__title {
        font-size: 1.125rem;
        font-weight: 750;
        line-height: 1.25;
        letter-spacing: -0.02em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
        margin: 0;
    }

    .hcis-header--size-sm .hcis-header__title { font-size: 1rem; }
    .hcis-header--size-lg .hcis-header__title { font-size: 1.15rem; }

    .hcis-header__badge {
        font-size: 0.65625rem;
        padding: 2px 7px;
        border-radius: var(--hcis-radius-full, 9999px);
        flex-shrink: 0;
        white-space: nowrap;
    }

    .hcis-header__subtitle,
    .hcis-header p.hcis-header__subtitle {
        grid-area: subtitle;
        width: 100%;
        font-size: 0.75rem;
        line-height: 1.38;
        margin-top: 0.25rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-word;
        color: var(--hcis-slate-500, #64748b);
    }

    .hcis-header__actions {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 0.375rem;
        flex-wrap: wrap;
        margin-top: 0.25rem;
        padding-top: 0.65rem;
        border-top: 1px dashed rgb(var(--hcis-brand-500-rgb) / 0.15);
    }

    .hcis-header__actions .hcis-btn {
        flex: 1 1 auto;
        justify-content: center;
        padding: 0.35rem 0.65rem;
        font-size: 0.775rem;
        min-height: 2rem;
    }

    .hcis-header__console {
        margin-top: 0.75rem;
        padding-top: 0.75rem;
    }

    /* Seamless Touch-Scroll Tabs Bar */
    .hcis-header__tabs-bar {
        margin-top: 0.75rem;
        padding-top: 0.2rem;
        -webkit-overflow-scrolling: touch;
    }

    .hcis-header--size-sm .hcis-header__tabs-bar {
        margin-left: -0.875rem;
        margin-right: -0.875rem;
        padding-left: 0.875rem;
        padding-right: 0.875rem;
        margin-bottom: -0.75rem;
    }

    .hcis-header--size-md .hcis-header__tabs-bar {
        margin-left: -1rem;
        margin-right: -1rem;
        padding-left: 1rem;
        padding-right: 1rem;
        margin-bottom: -0.85rem;
    }

    .hcis-header--size-lg .hcis-header__tabs-bar {
        margin-left: -1.125rem;
        margin-right: -1.125rem;
        padding-left: 1.125rem;
        padding-right: 1.125rem;
        margin-bottom: -1rem;
    }

    .hcis-header .hcis-tabs--variant-header .hcis-tabs__tab {
        padding: 0.55rem 0.85rem;
        font-size: 0.8125rem;
        min-height: 2.35rem;
        white-space: nowrap;
        flex-shrink: 0;
    }
}

/* 3. Small Mobile Viewports (<= 480px) ---------------------------------------- */
@media (max-width: 480px) {
    .hcis-header--size-sm .hcis-header__inner,
    .hcis-header--size-md .hcis-header__inner,
    .hcis-header--size-lg .hcis-header__inner {
        padding: 0.75rem 0.875rem;
    }

    .hcis-header__identity {
        column-gap: 0.5rem;
        row-gap: 0.2rem;
    }

    .hcis-header__icon-badge {
        width: 2.15rem;
        height: 2.15rem;
        font-size: 1.1rem;
        border-radius: var(--hcis-radius-md);
    }

    .hcis-header__kicker {
        font-size: 0.625rem;
        letter-spacing: 0.06em;
    }

    .hcis-header__title,
    .hcis-header h1.hcis-header__title {
        font-size: 1.05rem;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
        margin: 0;
    }

    .hcis-header__title-row {
        gap: 0.35rem;
        flex-wrap: nowrap;
        min-width: 0;
        width: 100%;
    }

    .hcis-header__badge {
        font-size: 0.625rem;
        padding: 1px 6px;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .hcis-header__subtitle,
    .hcis-header p.hcis-header__subtitle {
        font-size: 0.75rem;
        line-height: 1.38;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .hcis-header__actions {
        gap: 0.35rem;
        padding-top: 0.5rem;
    }

    .hcis-header__actions .hcis-btn {
        font-size: 0.75rem;
        padding: 0.3rem 0.55rem;
        min-height: 1.9rem;
    }

    .hcis-header__tabs-bar {
        margin-left: -0.875rem !important;
        margin-right: -0.875rem !important;
        padding-left: 0.875rem !important;
        padding-right: 0.875rem !important;
        margin-bottom: -0.75rem !important;
    }

    .hcis-header .hcis-tabs--variant-header .hcis-tabs__tab {
        padding: 0.5rem 0.75rem;
        font-size: 0.775rem;
        min-height: 2.2rem;
    }
}
</style>

