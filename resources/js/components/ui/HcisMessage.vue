<template>
    <div
        v-if="inline && !isClosed"
        class="hcis-message"
        :class="[
            `hcis-message--${effectiveVariant}`,
            `hcis-message--${normalType}`,
            `hcis-message--size-${effectiveSize}`,
            `hcis-message--rounded-${rounded}`,
            {
                'hcis-message--compact': isCompact,
                'hcis-message--glow': glow,
                'hcis-message--dark': theme === 'dark' || effectiveVariant === 'obsidian',
                'hcis-message--has-pattern': pattern !== false,
                [`hcis-message--pattern-${typeof pattern === 'string' ? pattern : 'dots'}`]: pattern !== false,
            },
            customClass
        ]"
    >
        <!-- Luminous Ambient Halo Glow Layer -->
        <div v-if="glow" class="hcis-message__glow" aria-hidden="true" />

        <!-- 3D Jewel Icon Badge -->
        <div class="hcis-message__icon-badge">
            <slot name="icon">
                <HcisIcon :name="resolvedIcon" :size="isCompact ? 'xs' : 'sm'" />
            </slot>
        </div>

        <!-- Notification Content Body -->
        <div class="hcis-message__body">
            <template v-if="isCompact">
                <div class="hcis-message__compact-content">
                    <strong v-if="title" class="hcis-message__title">{{ title }}: </strong>
                    <span class="hcis-message__text"><slot>{{ message }}</slot></span>
                    <span v-if="badge" class="hcis-message__badge">{{ badge }}</span>
                </div>
            </template>
            <template v-else>
                <div v-if="title" class="hcis-message__header">
                    <h5 class="hcis-message__title">{{ title }}</h5>
                    <span v-if="badge" class="hcis-message__badge">{{ badge }}</span>
                </div>
                <div class="hcis-message__content">
                    <slot>
                        {{ message }}
                    </slot>
                </div>
            </template>
        </div>

        <!-- Optional Action CTA Button -->
        <div v-if="actionText || $slots.action" class="hcis-message__action">
            <slot name="action">
                <button type="button" class="hcis-message__action-btn" @click="$emit('action')">
                    {{ actionText }}
                </button>
            </slot>
        </div>

        <!-- Close Button -->
        <button
            v-if="closable"
            type="button"
            class="hcis-message__close-btn"
            aria-label="Tutup notifikasi"
            @click="handleClose"
        >
            <HcisIcon name="x-lg" size="2xs" />
        </button>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisMessage' });

const props = defineProps({
    message: { type: String, default: '' },
    title: { type: String, default: '' },
    badge: { type: String, default: '' },
    type: {
        type: String,
        default: 'info',
        validator: (v) => ['info', 'primary', 'success', 'warning', 'error', 'danger', 'purple', 'teal'].includes(v),
    },
    variant: {
        type: String,
        default: 'card',
        validator: (v) => ['card', 'glass', 'hero', 'flat', 'obsidian'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['compact', 'sm', 'md', 'lg'].includes(v),
    },
    compact: { type: Boolean, default: false },
    rounded: {
        type: String,
        default: 'md',
        validator: (v) => ['none', 'sm', 'md', 'lg', 'full'].includes(v),
    },
    theme: { type: String, default: 'light' }, // light | dark
    glow: { type: Boolean, default: false },
    pattern: {
        type: [Boolean, String],
        default: 'dots',
        validator: (v) => typeof v === 'boolean' || ['dots', 'grid', 'mesh'].includes(v),
    },
    closable: { type: Boolean, default: false },
    actionText: { type: String, default: '' },
    customIcon: { type: String, default: '' },
    inline: { type: Boolean, default: true },
    customClass: { type: String, default: '' },
});

const emit = defineEmits(['close', 'action']);

const isClosed = ref(false);

const isCompact = computed(() => props.compact || props.size === 'compact' || props.size === 'sm');

const effectiveSize = computed(() => {
    if (props.compact) return 'compact';
    return props.size;
});

const effectiveVariant = computed(() => {
    if (props.theme === 'dark') return 'obsidian';
    return props.variant;
});

const normalType = computed(() => {
    if (props.type === 'danger') return 'error';
    if (props.type === 'primary') return 'info';
    return props.type;
});

const resolvedIcon = computed(() => {
    if (props.customIcon) return props.customIcon;
    switch (normalType.value) {
        case 'success':
            return 'check-circle-fill';
        case 'error':
            return 'x-octagon-fill';
        case 'warning':
            return 'exclamation-triangle-fill';
        case 'purple':
            return 'stars';
        case 'teal':
            return 'shield-check';
        case 'info':
        default:
            return 'info-circle-fill';
    }
});

function handleClose() {
    isClosed.value = true;
    emit('close');
}
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   HCIS MESSAGE — LUXURY CRYSTAL NOTIFICATION ARCHITECTURE
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-message {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.875rem;
    width: 100%;
    padding: 0.9375rem 1.125rem;
    border-radius: var(--hcis-radius-lg, 10px);
    font-family: var(--hcis-font-body, inherit);
    box-sizing: border-box;
    overflow: hidden;
    transition: transform 220ms cubic-bezier(0.16, 1, 0.3, 1),
                box-shadow 220ms cubic-bezier(0.16, 1, 0.3, 1),
                border-color 200ms ease,
                background 200ms ease;
}

/* ── 1. VARIANTS (Card, Glass, Hero, Flat, Obsidian) ──────────────────── */

/* Variant: Card (Solid Executive Crystal Surface) */
.hcis-message--card {
    background: var(--hcis-white);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow:
        0 0 0 1px rgba(15, 23, 42, 0.03),
        0 4px 16px -2px rgba(15, 23, 42, 0.04),
        0 12px 24px -6px rgba(15, 23, 42, 0.04),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

/* Variant: Glass (Frosted Acrylic Translucent) */
.hcis-message--glass {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(248, 250, 252, 0.82) 100%);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.9);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.8) inset,
        0 8px 24px -4px rgba(15, 23, 42, 0.07),
        0 16px 36px -8px rgba(15, 23, 42, 0.05);
}

/* Variant: Hero (Luminescent Gradient Sheen) */
.hcis-message--hero {
    border: 1px solid transparent;
    box-shadow:
        0 4px 16px -2px rgba(15, 23, 42, 0.05),
        0 12px 28px -6px rgba(15, 23, 42, 0.06),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.hcis-message--hero.hcis-message--success {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-emerald-50) 100%);
    border-color: var(--hcis-emerald-200);
}
.hcis-message--hero.hcis-message--error {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-red-50) 100%);
    border-color: var(--hcis-red-200);
}
.hcis-message--hero.hcis-message--warning {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-amber-50) 100%);
    border-color: var(--hcis-amber-200);
}
.hcis-message--hero.hcis-message--info {
    background: linear-gradient(135deg, var(--hcis-white) 0%, var(--hcis-indigo-50) 100%);
    border-color: var(--hcis-indigo-200);
}
.hcis-message--hero.hcis-message--purple {
    background: linear-gradient(135deg, var(--hcis-white) 0%, #faf5ff 100%);
    border-color: #e9d5ff;
}
.hcis-message--hero.hcis-message--teal {
    background: linear-gradient(135deg, var(--hcis-white) 0%, #f0fdfa 100%);
    border-color: #99f6e4;
}

/* Variant: Flat (Minimalist Flush Banner) */
.hcis-message--flat {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    box-shadow: none;
}

/* Variant: Obsidian (Cosmic Dark Mode) */
.hcis-message--obsidian {
    background: linear-gradient(135deg, var(--hcis-slate-800) 0%, var(--hcis-slate-900) 100%) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    color: var(--hcis-slate-50) !important;
    box-shadow:
        0 8px 24px -4px rgba(0, 0, 0, 0.4),
        0 16px 36px -8px rgba(0, 0, 0, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.1) !important;
}

/* ── 2. GRADIENT MICRO-PATTERNS ────────────────────────────────────────── */

.hcis-message--pattern-dots::before,
.hcis-message--pattern-grid::before,
.hcis-message--pattern-mesh::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    border-radius: inherit;
    -webkit-mask-image: linear-gradient(135deg, rgba(0, 0, 0, 0.95) 0%, rgba(0, 0, 0, 0.6) 45%, rgba(0, 0, 0, 0.1) 85%, transparent 100%);
    mask-image: linear-gradient(135deg, rgba(0, 0, 0, 0.95) 0%, rgba(0, 0, 0, 0.6) 45%, rgba(0, 0, 0, 0.1) 85%, transparent 100%);
    opacity: 0.85;
    z-index: 0;
    transition: opacity 220ms ease;
}

.hcis-message--pattern-dots::before {
    background-image:
        radial-gradient(rgba(99, 102, 241, 0.2) 1.25px, transparent 1.25px),
        radial-gradient(rgba(148, 163, 184, 0.18) 1px, transparent 1px);
    background-size: 16px 16px, 8px 8px;
}

.hcis-message--pattern-grid::before {
    background-image:
        linear-gradient(to right, rgba(99, 102, 241, 0.12) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(148, 163, 184, 0.12) 1px, transparent 1px);
    background-size: 14px 14px;
}

.hcis-message--pattern-mesh::before {
    background:
        radial-gradient(ellipse at top left, rgba(99, 102, 241, 0.18) 0%, transparent 60%),
        radial-gradient(ellipse at bottom right, rgba(236, 72, 153, 0.12) 0%, transparent 60%),
        radial-gradient(rgba(148, 163, 184, 0.12) 1px, transparent 1px);
    background-size: 100% 100%, 100% 100%, 10px 10px;
}

.hcis-message--obsidian.hcis-message--pattern-dots::before {
    background-image:
        radial-gradient(rgba(129, 140, 248, 0.3) 1.25px, transparent 1.25px),
        radial-gradient(rgba(255, 255, 255, 0.2) 1px, transparent 1px);
    background-size: 16px 16px, 8px 8px;
    opacity: 0.35;
}

.hcis-message--obsidian.hcis-message--pattern-grid::before {
    background-image:
        linear-gradient(to right, rgba(255, 255, 255, 0.08) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
    opacity: 0.35;
}

/* ── 3. AMBIENT RADIANT HALO GLOW ──────────────────────────────────────── */

.hcis-message__glow {
    position: absolute;
    inset: -30px;
    border-radius: inherit;
    pointer-events: none;
    z-index: 0;
    opacity: 0.85;
    transition: opacity 300ms ease;
}

.hcis-message--glow.hcis-message--success {
    box-shadow:
        0 4px 16px -2px rgba(16, 185, 129, 0.15),
        0 12px 28px -4px rgba(16, 185, 129, 0.12),
        0 24px 48px -8px rgba(16, 185, 129, 0.08);
}

.hcis-message--glow.hcis-message--error {
    box-shadow:
        0 4px 16px -2px rgba(239, 68, 68, 0.15),
        0 12px 28px -4px rgba(239, 68, 68, 0.12),
        0 24px 48px -8px rgba(239, 68, 68, 0.08);
}

.hcis-message--glow.hcis-message--warning {
    box-shadow:
        0 4px 16px -2px rgba(245, 158, 11, 0.15),
        0 12px 28px -4px rgba(245, 158, 11, 0.12),
        0 24px 48px -8px rgba(245, 158, 11, 0.08);
}

.hcis-message--glow.hcis-message--info {
    box-shadow:
        0 4px 16px -2px rgba(99, 102, 241, 0.15),
        0 12px 28px -4px rgba(99, 102, 241, 0.12),
        0 24px 48px -8px rgba(99, 102, 241, 0.08);
}

.hcis-message--glow.hcis-message--purple {
    box-shadow:
        0 4px 16px -2px rgba(168, 85, 247, 0.15),
        0 12px 28px -4px rgba(168, 85, 247, 0.12),
        0 24px 48px -8px rgba(168, 85, 247, 0.08);
}

.hcis-message--glow.hcis-message--teal {
    box-shadow:
        0 4px 16px -2px rgba(20, 184, 166, 0.15),
        0 12px 28px -4px rgba(20, 184, 166, 0.12),
        0 24px 48px -8px rgba(20, 184, 166, 0.08);
}

/* ── 4. 3D JEWEL ICON BADGE ────────────────────────────────────────────── */

.hcis-message__icon-badge {
    position: relative;
    z-index: 1;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: center;
    flex-shrink: 0;
    color: var(--hcis-white);
    line-height: 1;
    box-shadow:
        0 2px 8px rgba(0, 0, 0, 0.15),
        inset 0 1px 0 rgba(255, 255, 255, 0.4);
    transition: transform 220ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.hcis-message__icon-badge :deep(svg),
.hcis-message__icon-badge :deep(.hcis-icon) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    line-height: 1;
}

.hcis-message:hover .hcis-message__icon-badge {
    transform: scale(1.08) rotate(3deg);
}

.hcis-message--success .hcis-message__icon-badge {
    background: linear-gradient(135deg, var(--hcis-emerald-400) 0%, var(--hcis-emerald-600) 100%);
}
.hcis-message--error .hcis-message__icon-badge {
    background: linear-gradient(135deg, var(--hcis-red-400) 0%, var(--hcis-red-600) 100%);
}
.hcis-message--warning .hcis-message__icon-badge {
    background: linear-gradient(135deg, var(--hcis-amber-400) 0%, var(--hcis-amber-600) 100%);
}
.hcis-message--info .hcis-message__icon-badge {
    background: linear-gradient(135deg, var(--hcis-indigo-400) 0%, var(--hcis-indigo-600) 100%);
}
.hcis-message--purple .hcis-message__icon-badge {
    background: linear-gradient(135deg, #c084fc 0%, var(--hcis-purple-700) 100%);
}
.hcis-message--teal .hcis-message__icon-badge {
    background: linear-gradient(135deg, #2dd4bf 0%, #0f766e 100%);
}

/* ── 5. BODY TYPOGRAPHY & BADGES ───────────────────────────────────────── */

.hcis-message__body {
    position: relative;
    z-index: 1;
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-self: center;
}

.hcis-message__header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.hcis-message__title {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    line-height: 1.3;
    color: var(--hcis-slate-900);
}

.hcis-message--obsidian .hcis-message__title {
    color: var(--hcis-slate-50);
}

.hcis-message__badge {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 0.125rem 0.4375rem;
    border-radius: 9999px;
    background: rgba(15, 23, 42, 0.06);
    color: var(--hcis-slate-600);
}

.hcis-message--obsidian .hcis-message__badge {
    background: rgba(255, 255, 255, 0.12);
    color: var(--hcis-slate-300);
}

.hcis-message__content {
    font-size: 0.8125rem;
    font-weight: 400;
    line-height: 1.5;
    color: var(--hcis-slate-700);
    word-break: break-word;
}

.hcis-message--obsidian .hcis-message__content {
    color: var(--hcis-slate-300);
}

/* ── 6. ACTIONS & CLOSE BUTTON ─────────────────────────────────────────── */

.hcis-message__action {
    position: relative;
    z-index: 1;
    flex-shrink: 0;
    align-self: center;
    margin-left: 0.5rem;
}

.hcis-message__action-btn {
    appearance: none;
    border: 1px solid rgba(203, 213, 225, 0.9);
    background: var(--hcis-white);
    color: var(--hcis-slate-900);
    padding: 0.3125rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: ease;
}

.hcis-message__action-btn:hover {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-400);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
}

.hcis-message--obsidian .hcis-message__action-btn {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    color: var(--hcis-white);
}

.hcis-message--obsidian .hcis-message__action-btn:hover {
    background: rgba(255, 255, 255, 0.18);
    border-color: rgba(255, 255, 255, 0.35);
}

.hcis-message__close-btn {
    position: relative;
    z-index: 1;
    appearance: none;
    border: none;
    background: transparent;
    color: var(--hcis-slate-500);
    padding: 0.25rem;
    border-radius: var(--hcis-radius-md, 6px);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    align-self: flex-start;
    margin-left: auto;
    transition: color 150ms ease, background 150ms ease, transform 150ms ease;
}

.hcis-message__close-btn:hover {
    color: var(--hcis-red-500);
    background: rgba(239, 68, 68, 0.08);
    transform: rotate(90deg);
}

.hcis-message--obsidian .hcis-message__close-btn {
    color: var(--hcis-slate-400);
}

.hcis-message--obsidian .hcis-message__close-btn:hover {
    color: var(--hcis-white);
    background: rgba(255, 255, 255, 0.12);
}

/* ── 7. COMPACT SIZE & BALANCED SQUIRCLE GEOMETRY ────────────────────── */

.hcis-message--compact {
    padding: 0.4375rem 0.8125rem;
    gap: 0.5625rem;
    border-radius: 8px;
}

.hcis-message--compact .hcis-message__icon-badge {
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
}

.hcis-message--compact .hcis-message__body {
    gap: 0;
}

.hcis-message__compact-content {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.8125rem;
    line-height: 1.4;
    white-space: normal;
}

.hcis-message--compact .hcis-message__title {
    font-size: 0.8125rem;
    font-weight: 700;
}

.hcis-message--compact .hcis-message__text {
    font-size: 0.8125rem;
    color: var(--hcis-slate-700);
}

.hcis-message--obsidian.hcis-message--compact .hcis-message__text {
    color: var(--hcis-slate-300);
}

.hcis-message--compact .hcis-message__badge {
    font-size: 0.625rem;
    padding: 0.0625rem 0.375rem;
}

.hcis-message--compact .hcis-message__action-btn {
    padding: 0.1875rem 0.5625rem;
    font-size: 0.6875rem;
    border-radius: 5px;
}

.hcis-message--compact .hcis-message__close-btn {
    padding: 0.125rem;
    align-self: center;
}

/* Rounded Corner Modifiers */
.hcis-message--rounded-none { border-radius: 0 !important; }
.hcis-message--rounded-none .hcis-message__icon-badge { border-radius: 0 !important; }
.hcis-message--rounded-sm { border-radius: 6px !important; }
.hcis-message--rounded-sm .hcis-message__icon-badge { border-radius: 5px !important; }
.hcis-message--rounded-md { border-radius: 10px !important; }
.hcis-message--rounded-md .hcis-message__icon-badge { border-radius: 8px !important; }
.hcis-message--rounded-lg { border-radius: 14px !important; }
.hcis-message--rounded-lg .hcis-message__icon-badge { border-radius: 10px !important; }
.hcis-message--rounded-full { border-radius: 9999px !important; }
.hcis-message--rounded-full .hcis-message__icon-badge { border-radius: 9999px !important; }
</style>
