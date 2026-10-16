<template>
    <section
        class="hcis-card"
        :class="[
            `hcis-card--variant-${variant}`,
            `hcis-card--sentiment-${sentiment}`,
            `hcis-card--size-${size}`,
            {
                'hcis-card--interactive': interactive,
                'hcis-card--divider': divider,
                'hcis-card--loading': loading,
            }
        ]"
        :tabindex="interactive ? 0 : undefined"
        :role="interactive ? 'button' : undefined"
        @click="onClick"
        @keydown.enter.space="onKeydown"
    >
        <!-- Signature Background Pattern Layer -->
        <div v-if="pattern === 'grid'" class="hcis-pattern-grid hcis-pattern-grid--diagonal" aria-hidden="true" />
        <div v-else-if="pattern === 'dots'" class="hcis-pattern-dots" aria-hidden="true" />
        <div v-else-if="pattern === 'crosshair'" class="hcis-pattern-crosshair" aria-hidden="true" />
        <div v-else-if="pattern === 'stripes'" class="hcis-pattern-stripes" aria-hidden="true" />
        <div v-else-if="pattern === 'rings'" class="hcis-pattern-rings" aria-hidden="true" />
        <div v-else-if="pattern === 'hex'" class="hcis-pattern-hex" aria-hidden="true" />

        <!-- Header -->
        <header v-if="$slots.header || title || $slots.title || icon || $slots.icon || badge || $slots.badge || $slots.actions" class="hcis-card__header">
            <div class="hcis-card__header-main">
                <div v-if="icon || $slots.icon" class="hcis-card__icon-box">
                    <slot name="icon">
                        <HcisIcon :name="icon" :animation="iconAnimation" size="md" />
                    </slot>
                </div>
                <div class="hcis-card__title-wrap">
                    <div class="hcis-card__title-row">
                        <h2 v-if="title || $slots.title" class="hcis-card__title">
                            <slot name="title">{{ title }}</slot>
                        </h2>
                        <slot name="badge">
                            <HcisBadge
                                v-if="badge"
                                :label="badge"
                                :variant="resolvedBadgeVariant"
                                :size="badgeSize"
                                :radar="badgeRadar"
                                :glow="badgeGlow"
                                :dot="badgeDot"
                            />
                        </slot>
                    </div>
                    <p v-if="description || $slots.description" class="hcis-card__description">
                        <slot name="description">{{ description }}</slot>
                    </p>
                    <slot name="header" />
                </div>
            </div>
            <div v-if="$slots.actions" class="hcis-card__actions">
                <slot name="actions" />
            </div>
        </header>

        <!-- Body -->
        <div class="hcis-card__body">
            <template v-if="loading">
                <div class="hcis-card__skeleton-wrap">
                    <HcisSkeleton width="42%" height="1.15rem" rounded="sm" />
                    <HcisSkeleton width="92%" height="0.85rem" rounded="sm" />
                    <HcisSkeleton width="68%" height="0.85rem" rounded="sm" />
                </div>
            </template>
            <slot v-else />
        </div>

        <!-- Footer -->
        <footer v-if="$slots.footer" class="hcis-card__footer">
            <slot name="footer" />
        </footer>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import HcisSkeleton from './HcisSkeleton.vue';
import HcisIcon from './HcisIcon.vue';
import HcisBadge from './HcisBadge.vue';

defineOptions({ name: 'HcisCard' });

const props = defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    badge: { type: String, default: '' },
    badgeVariant: { type: String, default: '' },
    sentiment: {
        type: String,
        default: 'brand',
        validator: (v) => ['brand', 'purple', 'violet', 'teal', 'cyan', 'success', 'warning', 'danger', 'info', 'neutral', 'none'].includes(v),
    },
    pattern: {
        type: String,
        default: 'none',
        validator: (v) => ['none', 'dots', 'grid', 'crosshair', 'stripes', 'rings', 'hex'].includes(v),
    },
    interactive: { type: Boolean, default: false },
    variant: {
        type: String,
        default: 'glass',
        validator: (v) => ['glass', 'default', 'raised', 'flat', 'outlined'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['xs', 'sm', 'md', 'lg'].includes(v),
    },
    divider: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    icon: { type: String, default: '' },
    iconAnimation: {
        type: String,
        default: '',
        validator: (v) => ['', 'float', 'spin', 'gear', 'pulse', 'ring', 'sparkle'].includes(v),
    },
    badgeRadar: { type: Boolean, default: false },
    badgeGlow: { type: Boolean, default: false },
    badgeDot: { type: Boolean, default: false },
    badgeSize: { type: String, default: 'sm' },
});

const emit = defineEmits(['click']);

const resolvedBadgeVariant = computed(() => {
    if (props.badgeVariant) return props.badgeVariant;
    if (['brand', 'success', 'warning', 'danger', 'info', 'purple', 'teal'].includes(props.sentiment)) {
        return props.sentiment;
    }
    if (props.sentiment === 'violet') return 'purple';
    if (props.sentiment === 'cyan') return 'teal';
    return 'brand';
});

function onClick(e) {
    if (props.interactive) emit('click', e);
}

function onKeydown(e) {
    if (props.interactive) {
        e.preventDefault();
        emit('click', e);
    }
}
</script>

<style>
/* ═══════════════════════════════════════════════════════════════════════════
   HCIS CARD - ENTERPRISE SUITE SIGNATURE
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-card {
    position: relative;
    width: 100%;
    overflow: hidden;
    font-family: var(--hcis-font-body, inherit);
    color: var(--hcis-text-strong);
    border-radius: var(--hcis-radius-xl);
    background: var(--hcis-surface);
    border: 1px solid rgb(var(--hcis-slate-200-rgb) / 0.85);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 1px 3px 0 rgb(var(--hcis-slate-900-rgb) / 0.04);
    box-sizing: border-box;
}

.hcis-card--interactive {
    transition: transform var(--hcis-duration-fast) var(--hcis-ease),
                box-shadow var(--hcis-duration-fast) var(--hcis-ease),
                border-color var(--hcis-duration-fast) var(--hcis-ease);
}

/* ═══════════════════════════════════════════════════════════════════════════
   VARIANTS (Default Signature: Clean Surface)
   ═══════════════════════════════════════════════════════════════════════════ */

/* Variant 1: Glass (Clean Sentiment Adaptive Surface) */
.hcis-card--variant-glass {
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 2px 8px -2px rgb(var(--hcis-slate-900-rgb) / 0.05);
}

/* Glass Sentiment: Brand (Signature Indigo) */
.hcis-card--variant-glass.hcis-card--sentiment-brand,
.hcis-card--variant-glass {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-brand-500-rgb) / 0.14), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-brand-600-rgb) / 0.09), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-brand-50-rgb) / 0.88) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-brand-50-rgb) / 0.5) 100%);
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.2);
}

/* Glass Sentiment: Purple / Violet (Amethyst SOP Assesmen) */
.hcis-card--variant-glass.hcis-card--sentiment-purple,
.hcis-card--variant-glass.hcis-card--sentiment-violet {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgba(124, 58, 237, 0.14), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgba(109, 40, 217, 0.09), transparent 55%),
        linear-gradient(225deg, rgba(245, 243, 255, 0.88) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgba(237, 233, 254, 0.5) 100%);
    border: 1px solid rgba(124, 58, 237, 0.24);
}

/* Glass Sentiment: Teal / Cyan (Mint SOP Referensi) */
.hcis-card--variant-glass.hcis-card--sentiment-teal,
.hcis-card--variant-glass.hcis-card--sentiment-cyan {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgba(13, 148, 136, 0.14), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgba(15, 118, 110, 0.09), transparent 55%),
        linear-gradient(225deg, rgba(240, 253, 250, 0.88) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgba(204, 251, 241, 0.5) 100%);
    border: 1px solid rgba(13, 148, 136, 0.24);
}

/* Glass Sentiment: Success (Emerald Green) */
.hcis-card--variant-glass.hcis-card--sentiment-success {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-success-500-rgb) / 0.16), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-success-600-rgb) / 0.1), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-success-50-rgb) / 0.88) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-success-100-rgb) / 0.5) 100%);
    border: 1px solid rgb(var(--hcis-success-500-rgb) / 0.28);
}

/* Glass Sentiment: Warning (Amber / Gold) */
.hcis-card--variant-glass.hcis-card--sentiment-warning {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-warning-500-rgb) / 0.16), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-warning-600-rgb) / 0.1), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-warning-50-rgb) / 0.88) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-warning-100-rgb) / 0.5) 100%);
    border: 1px solid rgb(var(--hcis-warning-500-rgb) / 0.28);
}

/* Glass Sentiment: Danger (Rose / Crimson) */
.hcis-card--variant-glass.hcis-card--sentiment-danger {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-danger-500-rgb) / 0.16), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-danger-600-rgb) / 0.1), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-danger-50-rgb) / 0.88) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-danger-100-rgb) / 0.5) 100%);
    border: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.28);
}

/* Glass Sentiment: Info (Sky Blue / Cyan) */
.hcis-card--variant-glass.hcis-card--sentiment-info {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-info-500-rgb) / 0.16), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-info-600-rgb) / 0.1), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-info-50-rgb) / 0.88) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-info-100-rgb) / 0.5) 100%);
    border: 1px solid rgb(var(--hcis-info-500-rgb) / 0.28);
}

/* Glass Sentiment: Neutral / None (Pure Frosted Clean) */
.hcis-card--variant-glass.hcis-card--sentiment-neutral,
.hcis-card--variant-glass.hcis-card--sentiment-none {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-slate-200-rgb) / 0.22), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-slate-300-rgb) / 0.15), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-white-rgb) / 0.98) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-slate-50-rgb) / 0.6) 100%);
    border: 1px solid rgb(var(--hcis-slate-200-rgb) / 0.85);
}

/* Variant 2: Default (Clean White Surface) */
.hcis-card--variant-default {
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 2px 10px -2px rgb(var(--hcis-slate-900-rgb) / 0.06);
}

/* Variant 3: Raised (Elevated Hero) */
.hcis-card--variant-raised {
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 12px 30px -4px rgb(var(--hcis-slate-900-rgb) / 0.08), 0 4px 12px -2px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

/* Variant 4: Flat (Muted Velvet) */
.hcis-card--variant-flat {
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 2px 6px -1px rgba(15, 23, 42, 0.05), 0 1px 3px -1px rgba(15, 23, 42, 0.03);
}

/* Variant 5: Outlined (Bordered Grid) */
.hcis-card--variant-outlined {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

/* ═══════════════════════════════════════════════════════════════════════════
   SIGNATURE PATTERN GRADIENT VIGNETTE MASKS
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-card .hcis-pattern-grid,
.hcis-card .hcis-pattern-dots,
.hcis-card .hcis-pattern-crosshair,
.hcis-card .hcis-pattern-stripes,
.hcis-card .hcis-pattern-rings,
.hcis-card .hcis-pattern-hex {
    -webkit-mask-image: radial-gradient(ellipse 90% 75% at 100% 0%, black 0%, rgba(0, 0, 0, 0.4) 38%, transparent 75%);
    mask-image: radial-gradient(ellipse 90% 75% at 100% 0%, black 0%, rgba(0, 0, 0, 0.4) 38%, transparent 75%);
}

.hcis-card--sentiment-brand .hcis-pattern-grid,
.hcis-card--sentiment-brand .hcis-pattern-dots,
.hcis-card--sentiment-brand .hcis-pattern-crosshair,
.hcis-card--sentiment-brand .hcis-pattern-stripes,
.hcis-card--sentiment-brand .hcis-pattern-rings,
.hcis-card--sentiment-brand .hcis-pattern-hex {
    color: var(--hcis-brand-600);
}

.hcis-card--sentiment-purple .hcis-pattern-grid,
.hcis-card--sentiment-purple .hcis-pattern-dots,
.hcis-card--sentiment-purple .hcis-pattern-crosshair,
.hcis-card--sentiment-purple .hcis-pattern-stripes,
.hcis-card--sentiment-purple .hcis-pattern-rings,
.hcis-card--sentiment-purple .hcis-pattern-hex,
.hcis-card--sentiment-violet .hcis-pattern-grid,
.hcis-card--sentiment-violet .hcis-pattern-dots,
.hcis-card--sentiment-violet .hcis-pattern-crosshair,
.hcis-card--sentiment-violet .hcis-pattern-stripes,
.hcis-card--sentiment-violet .hcis-pattern-rings,
.hcis-card--sentiment-violet .hcis-pattern-hex {
    color: var(--hcis-violet-600);
}

.hcis-card--sentiment-teal .hcis-pattern-grid,
.hcis-card--sentiment-teal .hcis-pattern-dots,
.hcis-card--sentiment-teal .hcis-pattern-crosshair,
.hcis-card--sentiment-teal .hcis-pattern-stripes,
.hcis-card--sentiment-teal .hcis-pattern-rings,
.hcis-card--sentiment-teal .hcis-pattern-hex,
.hcis-card--sentiment-cyan .hcis-pattern-grid,
.hcis-card--sentiment-cyan .hcis-pattern-dots,
.hcis-card--sentiment-cyan .hcis-pattern-crosshair,
.hcis-card--sentiment-cyan .hcis-pattern-stripes,
.hcis-card--sentiment-cyan .hcis-pattern-rings,
.hcis-card--sentiment-cyan .hcis-pattern-hex {
    color: #0d9488;
}

.hcis-card--sentiment-success .hcis-pattern-grid,
.hcis-card--sentiment-success .hcis-pattern-dots,
.hcis-card--sentiment-success .hcis-pattern-crosshair,
.hcis-card--sentiment-success .hcis-pattern-stripes,
.hcis-card--sentiment-success .hcis-pattern-rings,
.hcis-card--sentiment-success .hcis-pattern-hex {
    color: var(--hcis-success-600);
}

.hcis-card--sentiment-warning .hcis-pattern-grid,
.hcis-card--sentiment-warning .hcis-pattern-dots,
.hcis-card--sentiment-warning .hcis-pattern-crosshair,
.hcis-card--sentiment-warning .hcis-pattern-stripes,
.hcis-card--sentiment-warning .hcis-pattern-rings,
.hcis-card--sentiment-warning .hcis-pattern-hex {
    color: var(--hcis-warning-600);
}

.hcis-card--sentiment-danger .hcis-pattern-grid,
.hcis-card--sentiment-danger .hcis-pattern-dots,
.hcis-card--sentiment-danger .hcis-pattern-crosshair,
.hcis-card--sentiment-danger .hcis-pattern-stripes,
.hcis-card--sentiment-danger .hcis-pattern-rings,
.hcis-card--sentiment-danger .hcis-pattern-hex {
    color: var(--hcis-danger-600);
}

.hcis-card--sentiment-info .hcis-pattern-grid,
.hcis-card--sentiment-info .hcis-pattern-dots,
.hcis-card--sentiment-info .hcis-pattern-crosshair,
.hcis-card--sentiment-info .hcis-pattern-stripes,
.hcis-card--sentiment-info .hcis-pattern-rings,
.hcis-card--sentiment-info .hcis-pattern-hex {
    color: var(--hcis-info-600);
}

.hcis-card--sentiment-neutral .hcis-pattern-grid,
.hcis-card--sentiment-neutral .hcis-pattern-dots,
.hcis-card--sentiment-neutral .hcis-pattern-crosshair,
.hcis-card--sentiment-neutral .hcis-pattern-stripes,
.hcis-card--sentiment-neutral .hcis-pattern-rings,
.hcis-card--sentiment-neutral .hcis-pattern-hex,
.hcis-card--sentiment-none .hcis-pattern-grid,
.hcis-card--sentiment-none .hcis-pattern-dots,
.hcis-card--sentiment-none .hcis-pattern-crosshair,
.hcis-card--sentiment-none .hcis-pattern-stripes,
.hcis-card--sentiment-none .hcis-pattern-rings,
.hcis-card--sentiment-none .hcis-pattern-hex {
    color: var(--hcis-slate-500);
}

/* ═══════════════════════════════════════════════════════════════════════════
   JEWEL ICON BOXES (Sentiment Adaptive)
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-card__icon-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.375rem;
    height: 2.375rem;
    flex-shrink: 0;
    border-radius: 10px;
    font-size: 1.05rem;
    transition: transform var(--hcis-duration-fast) var(--hcis-ease), box-shadow var(--hcis-duration-fast) var(--hcis-ease);
}

.hcis-card--size-sm .hcis-card__icon-box {
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    font-size: 0.95rem;
}

.hcis-card--size-lg .hcis-card__icon-box {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 12px;
    font-size: 1.25rem;
}

/* Brand Icon Box */
.hcis-card--sentiment-brand .hcis-card__icon-box,
.hcis-card__icon-box {
    background: linear-gradient(135deg, var(--hcis-brand-50), var(--hcis-brand-100));
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.22);
    color: var(--hcis-brand-600);
    box-shadow: 0 2px 8px rgb(var(--hcis-brand-500-rgb) / 0.1), inset 0 1px 0 var(--hcis-white);
}

/* Purple / Violet Icon Box */
.hcis-card--sentiment-purple .hcis-card__icon-box,
.hcis-card--sentiment-violet .hcis-card__icon-box {
    background: linear-gradient(135deg, var(--hcis-violet-50), #ede9fe);
    border: 1px solid rgba(124, 58, 237, 0.25);
    color: var(--hcis-violet-600);
    box-shadow: 0 2px 8px rgba(124, 58, 237, 0.12), inset 0 1px 0 var(--hcis-white);
}

/* Teal / Cyan Icon Box */
.hcis-card--sentiment-teal .hcis-card__icon-box,
.hcis-card--sentiment-cyan .hcis-card__icon-box {
    background: linear-gradient(135deg, #f0fdfa, #ccfbf1);
    border: 1px solid rgba(13, 148, 136, 0.25);
    color: #0d9488;
    box-shadow: 0 2px 8px rgba(13, 148, 136, 0.12), inset 0 1px 0 var(--hcis-white);
}

/* Success Icon Box */
.hcis-card--sentiment-success .hcis-card__icon-box {
    background: linear-gradient(135deg, var(--hcis-success-50), var(--hcis-success-100));
    border: 1px solid rgb(var(--hcis-success-500-rgb) / 0.25);
    color: var(--hcis-success-600);
    box-shadow: 0 2px 8px rgb(var(--hcis-success-500-rgb) / 0.1), inset 0 1px 0 var(--hcis-white);
}

/* Warning Icon Box */
.hcis-card--sentiment-warning .hcis-card__icon-box {
    background: linear-gradient(135deg, var(--hcis-warning-50), var(--hcis-warning-100));
    border: 1px solid rgb(var(--hcis-warning-500-rgb) / 0.25);
    color: var(--hcis-warning-700);
    box-shadow: 0 2px 8px rgb(var(--hcis-warning-500-rgb) / 0.1), inset 0 1px 0 var(--hcis-white);
}

/* Danger Icon Box */
.hcis-card--sentiment-danger .hcis-card__icon-box {
    background: linear-gradient(135deg, var(--hcis-danger-50), var(--hcis-danger-100));
    border: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.25);
    color: var(--hcis-danger-600);
    box-shadow: 0 2px 8px rgb(var(--hcis-danger-500-rgb) / 0.1), inset 0 1px 0 var(--hcis-white);
}

/* Info Icon Box */
.hcis-card--sentiment-info .hcis-card__icon-box {
    background: linear-gradient(135deg, var(--hcis-info-50), var(--hcis-info-100));
    border: 1px solid rgb(var(--hcis-info-500-rgb) / 0.25);
    color: var(--hcis-info-600);
    box-shadow: 0 2px 8px rgb(var(--hcis-info-500-rgb) / 0.1), inset 0 1px 0 var(--hcis-white);
}

/* Neutral Icon Box */
.hcis-card--sentiment-neutral .hcis-card__icon-box,
.hcis-card--sentiment-none .hcis-card__icon-box {
    background: var(--hcis-surface-soft);
    border: 1px solid rgb(var(--hcis-slate-300-rgb) / 0.6);
    color: var(--hcis-slate-600);
    box-shadow: 0 2px 6px rgb(var(--hcis-slate-900-rgb) / 0.04), inset 0 1px 0 var(--hcis-white);
}

/* ═══════════════════════════════════════════════════════════════════════════
   SIZING PADDING
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-card--size-xs .hcis-card__header,
.hcis-card--size-xs .hcis-card__body,
.hcis-card--size-xs .hcis-card__footer {
    padding: 0.65rem 0.85rem;
}

.hcis-card--size-sm .hcis-card__header,
.hcis-card--size-sm .hcis-card__body,
.hcis-card--size-sm .hcis-card__footer {
    padding: 0.75rem 1rem;
}

.hcis-card--size-md .hcis-card__header,
.hcis-card--size-md .hcis-card__body,
.hcis-card--size-md .hcis-card__footer {
    padding: 1.1rem 1.35rem;
}

.hcis-card--size-lg .hcis-card__header,
.hcis-card--size-lg .hcis-card__body,
.hcis-card--size-lg .hcis-card__footer {
    padding: 1.4rem 1.65rem;
}

/* Collapse vertical gap between adjacent sections */
.hcis-card:not(.hcis-card--divider) .hcis-card__header + .hcis-card__body,
.hcis-card:not(.hcis-card--divider) .hcis-card__body + .hcis-card__footer {
    padding-top: 0;
}

/* ═══════════════════════════════════════════════════════════════════════════
   CRYSTAL ETCHED DIVIDERS
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-card--divider .hcis-card__header {
    border-bottom: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.14);
    box-shadow: inset 0 -1px 0 rgb(var(--hcis-white-rgb) / 0.8);
}

.hcis-card--divider .hcis-card__footer {
    border-top: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.14);
    box-shadow: inset 0 1px 0 rgb(var(--hcis-white-rgb) / 0.8);
}

.hcis-card--sentiment-purple.hcis-card--divider .hcis-card__header,
.hcis-card--sentiment-purple.hcis-card--divider .hcis-card__footer,
.hcis-card--sentiment-violet.hcis-card--divider .hcis-card__header,
.hcis-card--sentiment-violet.hcis-card--divider .hcis-card__footer {
    border-color: rgba(124, 58, 237, 0.16);
}

.hcis-card--sentiment-teal.hcis-card--divider .hcis-card__header,
.hcis-card--sentiment-teal.hcis-card--divider .hcis-card__footer,
.hcis-card--sentiment-cyan.hcis-card--divider .hcis-card__header,
.hcis-card--sentiment-cyan.hcis-card--divider .hcis-card__footer {
    border-color: rgba(13, 148, 136, 0.16);
}

.hcis-card--sentiment-success.hcis-card--divider .hcis-card__header,
.hcis-card--sentiment-success.hcis-card--divider .hcis-card__footer {
    border-color: rgb(var(--hcis-success-500-rgb) / 0.16);
}

.hcis-card--sentiment-warning.hcis-card--divider .hcis-card__header,
.hcis-card--sentiment-warning.hcis-card--divider .hcis-card__footer {
    border-color: rgb(var(--hcis-warning-500-rgb) / 0.16);
}

.hcis-card--sentiment-danger.hcis-card--divider .hcis-card__header,
.hcis-card--sentiment-danger.hcis-card--divider .hcis-card__footer {
    border-color: rgb(var(--hcis-danger-500-rgb) / 0.16);
}

.hcis-card--sentiment-info.hcis-card--divider .hcis-card__header,
.hcis-card--sentiment-info.hcis-card--divider .hcis-card__footer {
    border-color: rgb(var(--hcis-info-500-rgb) / 0.16);
}

.hcis-card--sentiment-neutral.hcis-card--divider .hcis-card__header,
.hcis-card--sentiment-neutral.hcis-card--divider .hcis-card__footer,
.hcis-card--sentiment-none.hcis-card--divider .hcis-card__header,
.hcis-card--sentiment-none.hcis-card--divider .hcis-card__footer {
    border-color: rgb(var(--hcis-slate-200-rgb) / 0.85);
}

/* ═══════════════════════════════════════════════════════════════════════════
   HEADER STRUCTURE & TYPOGRAPHY
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.875rem;
    position: relative;
    z-index: 2;
}

.hcis-card__header-main {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    min-width: 0;
    flex: 1;
}

.hcis-card__title-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    flex: 1;
}

.hcis-card__title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.hcis-card .hcis-card__title,
.hcis-card h2.hcis-card__title {
    margin: 0;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.3;
    letter-spacing: -0.015em;
}

.hcis-card--size-sm .hcis-card__title { font-size: 0.875rem; font-weight: 650; }
.hcis-card--size-lg .hcis-card__title { font-size: 1.1rem; font-weight: 700; }

.hcis-card .hcis-card__description,
.hcis-card p.hcis-card__description {
    margin: 0.2rem 0 0;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    font-size: 0.78125rem;
    font-weight: 450;
    line-height: 1.4;
}

.hcis-card--size-sm .hcis-card__description { font-size: 0.725rem; }
.hcis-card--size-lg .hcis-card__description { font-size: 0.84rem; }

.hcis-card__actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}

/* ═══════════════════════════════════════════════════════════════════════════
   BODY & FOOTER
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-card__body {
    position: relative;
    z-index: 2;
    font-size: 0.8125rem;
    font-weight: 400;
    line-height: 1.5;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

.hcis-card__skeleton-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.hcis-card__footer {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 1rem;
    background: transparent;
    font-size: 0.8125rem;
}

/* ═══════════════════════════════════════════════════════════════════════════
   INTERACTIVE HOVER LIFT
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-card--interactive {
    cursor: pointer;
    user-select: none;
}

.hcis-card--interactive:hover {
    transform: translateY(-3px);
    border-color: rgb(var(--hcis-brand-500-rgb) / 0.35);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 12px 28px -4px rgb(var(--hcis-brand-500-rgb) / 0.12), 0 4px 10px -2px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-card--interactive.hcis-card--sentiment-purple:hover,
.hcis-card--interactive.hcis-card--sentiment-violet:hover {
    border-color: rgba(124, 58, 237, 0.4);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 12px 28px -4px rgba(124, 58, 237, 0.14), 0 4px 10px -2px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-card--interactive.hcis-card--sentiment-teal:hover,
.hcis-card--interactive.hcis-card--sentiment-cyan:hover {
    border-color: rgba(13, 148, 136, 0.4);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 12px 28px -4px rgba(13, 148, 136, 0.14), 0 4px 10px -2px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-card--interactive.hcis-card--sentiment-success:hover {
    border-color: rgb(var(--hcis-success-500-rgb) / 0.4);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 12px 28px -4px rgb(var(--hcis-success-500-rgb) / 0.12), 0 4px 10px -2px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-card--interactive.hcis-card--sentiment-warning:hover {
    border-color: rgb(var(--hcis-warning-500-rgb) / 0.45);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 12px 28px -4px rgb(var(--hcis-warning-500-rgb) / 0.12), 0 4px 10px -2px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-card--interactive.hcis-card--sentiment-danger:hover {
    border-color: rgb(var(--hcis-danger-500-rgb) / 0.4);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 12px 28px -4px rgb(var(--hcis-danger-500-rgb) / 0.12), 0 4px 10px -2px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-card--interactive.hcis-card--sentiment-info:hover {
    border-color: rgb(var(--hcis-info-500-rgb) / 0.4);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 12px 28px -4px rgb(var(--hcis-info-500-rgb) / 0.12), 0 4px 10px -2px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-card--interactive.hcis-card--sentiment-neutral:hover,
.hcis-card--interactive.hcis-card--sentiment-none:hover {
    border-color: rgb(var(--hcis-slate-400-rgb) / 0.6);
    box-shadow: inset 0 1px 0 var(--hcis-white), 0 12px 28px -4px rgb(var(--hcis-slate-900-rgb) / 0.08), 0 4px 10px -2px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-card--interactive:hover .hcis-card__icon-box {
    transform: scale(1.06);
    box-shadow: 0 4px 14px rgb(var(--hcis-brand-500-rgb) / 0.18);
}

.hcis-card--interactive.hcis-card--sentiment-purple:hover .hcis-card__icon-box,
.hcis-card--interactive.hcis-card--sentiment-violet:hover .hcis-card__icon-box {
    box-shadow: 0 4px 14px rgba(124, 58, 237, 0.22);
}

.hcis-card--interactive.hcis-card--sentiment-teal:hover .hcis-card__icon-box,
.hcis-card--interactive.hcis-card--sentiment-cyan:hover .hcis-card__icon-box {
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.22);
}

.hcis-card--interactive.hcis-card--sentiment-success:hover .hcis-card__icon-box {
    box-shadow: 0 4px 14px rgb(var(--hcis-success-500-rgb) / 0.2);
}

.hcis-card--interactive.hcis-card--sentiment-warning:hover .hcis-card__icon-box {
    box-shadow: 0 4px 14px rgb(var(--hcis-warning-500-rgb) / 0.2);
}

.hcis-card--interactive.hcis-card--sentiment-danger:hover .hcis-card__icon-box {
    box-shadow: 0 4px 14px rgb(var(--hcis-danger-500-rgb) / 0.2);
}

.hcis-card--interactive.hcis-card--sentiment-info:hover .hcis-card__icon-box {
    box-shadow: 0 4px 14px rgb(var(--hcis-info-500-rgb) / 0.2);
}

.hcis-card--interactive:focus-visible {
    outline: 2px solid var(--hcis-brand-400);
    outline-offset: 2px;
    box-shadow: 0 0 0 4px rgb(var(--hcis-brand-500-rgb) / 0.15);
}

.hcis-card--interactive:active {
    transform: translateY(-1px) scale(0.992);
}

/* ═══════════════════════════════════════════════════════════════════════════
   MOBILE COMPACT & STREAMLINED RESPONSIVENESS (≤ 640px)
   ═══════════════════════════════════════════════════════════════════════════ */

@media (max-width: 640px) {
    /* Compact Padding */
    .hcis-card--size-xs .hcis-card__header,
    .hcis-card--size-xs .hcis-card__body,
    .hcis-card--size-xs .hcis-card__footer {
        padding: 0.5rem 0.75rem;
    }

    .hcis-card--size-sm .hcis-card__header,
    .hcis-card--size-sm .hcis-card__body,
    .hcis-card--size-sm .hcis-card__footer {
        padding: 0.65rem 0.85rem;
    }

    .hcis-card--size-md .hcis-card__header,
    .hcis-card--size-md .hcis-card__body,
    .hcis-card--size-md .hcis-card__footer {
        padding: 0.85rem 1rem;
    }

    .hcis-card--size-lg .hcis-card__header,
    .hcis-card--size-lg .hcis-card__body,
    .hcis-card--size-lg .hcis-card__footer {
        padding: 1.05rem 1.15rem;
    }

    /* 2-Row Streamlined Grid Header:
       Row 1: [ Icon ] [ Title + Badge ] [ Actions ]
       Row 2: [ Full-width Subtitle Description ] */
    .hcis-card__header {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        grid-template-areas:
            "icon title actions"
            "desc desc desc";
        column-gap: 0.5rem;
        row-gap: 0.25rem;
        align-items: center;
    }

    .hcis-card__header-main {
        display: contents;
    }

    .hcis-card__icon-box {
        grid-area: icon;
        width: 2rem;
        height: 2rem;
        font-size: 0.95rem;
        border-radius: 8px;
    }

    .hcis-card--size-sm .hcis-card__icon-box {
        width: 1.75rem;
        height: 1.75rem;
        font-size: 0.85rem;
        border-radius: 6px;
    }

    .hcis-card--size-lg .hcis-card__icon-box {
        width: 2.25rem;
        height: 2.25rem;
        font-size: 1.05rem;
        border-radius: 9px;
    }

    .hcis-card__title-wrap {
        display: contents;
    }

    .hcis-card__title-row {
        grid-area: title;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        flex-wrap: wrap;
        min-width: 0;
    }

    .hcis-card .hcis-card__title,
    .hcis-card h2.hcis-card__title {
        font-size: 0.875rem;
        line-height: 1.25;
    }

    .hcis-card--size-sm .hcis-card__title { font-size: 0.8125rem; }
    .hcis-card--size-lg .hcis-card__title { font-size: 0.95rem; }

    .hcis-card .hcis-card__description,
    .hcis-card p.hcis-card__description {
        grid-area: desc;
        width: 100%;
        margin-top: 0.15rem;
        font-size: 0.75rem;
        line-height: 1.4;
        color: var(--hcis-slate-500);
    }

    .hcis-card__actions {
        grid-area: actions;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        align-self: center;
    }

    /* Footer wrap on mobile */
    .hcis-card__footer {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
}
</style>

