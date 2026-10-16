<template>
    <Transition name="hcis-alert-fade">
        <div
            v-if="visible"
            class="hcis-alert"
            :class="[
                `hcis-alert--${normalizedVariant}`,
                `hcis-alert--appearance-${normalizedAppearance}`,
                `hcis-alert--size-${size}`,
                `hcis-alert--rounded-${rounded}`,
                {
                    'hcis-alert--glow': glow,
                    'hcis-alert--has-pattern': pattern !== false,
                    [`hcis-alert--pattern-${typeof pattern === 'string' ? pattern : 'dots'}`]: pattern !== false,
                    'hcis-alert--with-title': Boolean(title || $slots.title),
                }
            ]"
            role="status"
        >
            <!-- Luminous Ambient Halo Glow Layer -->
            <div v-if="glow" class="hcis-alert__glow" aria-hidden="true" />

            <!-- 3D Jewel Icon Badge -->
            <div v-if="computedIcon || $slots.icon" class="hcis-alert__icon-badge">
                <slot name="icon">
                    <HcisIcon
                        :name="computedIcon"
                        :size="size === 'sm' ? 'xs' : size === 'lg' ? 'md' : 'sm'"
                        class="hcis-alert__icon"
                    />
                </slot>
            </div>

            <!-- Notification Body -->
            <div class="hcis-alert__body">
                <div v-if="title || $slots.title || badge" class="hcis-alert__header">
                    <h5 class="hcis-alert__title">
                        <slot name="title">{{ title }}</slot>
                    </h5>
                    <span v-if="badge" class="hcis-alert__badge">{{ badge }}</span>
                </div>
                <div v-if="description || $slots.default" class="hcis-alert__desc">
                    <slot>{{ description }}</slot>
                </div>
                <div v-if="$slots.actions" class="hcis-alert__actions">
                    <slot name="actions" />
                </div>
            </div>

            <!-- Close Button -->
            <button
                v-if="closable"
                type="button"
                class="hcis-alert__close"
                aria-label="Tutup pesan"
                @click="onClose"
            >
                <HcisIcon name="x-lg" :size="size === 'sm' ? 'xs' : 'sm'" />
            </button>
        </div>
    </Transition>
</template>

<script setup>
import { computed, ref } from 'vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisAlert' });

const props = defineProps({
    variant: {
        type: String,
        default: 'info',
        validator: (v) => ['info', 'success', 'warning', 'danger', 'neutral', 'brand', 'primary', 'purple', 'teal', 'obsidian'].includes(v),
    },
    appearance: {
        type: String,
        default: 'soft',
        validator: (v) => ['soft', 'subtle', 'glass', 'solid', 'outline', 'obsidian'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    rounded: {
        type: String,
        default: 'md',
        validator: (v) => ['none', 'sm', 'md', 'lg', 'full'].includes(v),
    },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    icon: { type: [String, Boolean], default: null },
    badge: { type: String, default: '' },
    glow: { type: Boolean, default: false },
    pattern: {
        type: [Boolean, String],
        default: 'dots',
        validator: (v) => typeof v === 'boolean' || ['dots', 'grid', 'mesh'].includes(v),
    },
    closable: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const visible = ref(true);

const normalizedVariant = computed(() => {
    if (props.variant === 'primary') return 'brand';
    return props.variant;
});

const normalizedAppearance = computed(() => {
    if (props.appearance === 'subtle') return 'soft';
    return props.appearance || 'soft';
});

const ICONS = {
    info: 'info-circle-fill',
    success: 'check-circle-fill',
    warning: 'exclamation-triangle-fill',
    danger: 'x-circle-fill',
    neutral: 'bell-fill',
    brand: 'stars',
    purple: 'lightning-charge-fill',
    teal: 'shield-check',
    obsidian: 'moon-stars-fill',
};

const computedIcon = computed(() => {
    if (props.icon === false) return null;
    if (typeof props.icon === 'string' && props.icon.length > 0) return props.icon;
    return ICONS[normalizedVariant.value] ?? ICONS.info;
});

function onClose() {
    visible.value = false;
    emit('close');
}
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   HCIS ALERT — LUXURY CRYSTAL BANNER NOTIFICATION ARCHITECTURE
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-alert {
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

/* ── 1. APPEARANCES (Soft, Glass, Accent, Solid, Outline, Obsidian) ────── */

/* ── 1. APPEARANCES (Soft, Glass, Solid, Outline, Obsidian) ───────────── */

/* ── Appearance: Soft (Multi-Stop Iridescent Crystal Pastels) ─────────── */
.hcis-alert--appearance-soft.hcis-alert--brand {
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.98) 0%, rgba(224, 231, 255, 0.88) 45%, rgba(243, 232, 255, 0.92) 100%);
    border: 1px solid rgba(199, 210, 254, 0.95);
    color: var(--hcis-indigo-950);
    box-shadow: 0 4px 16px -2px rgba(79, 70, 229, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}
.hcis-alert--appearance-soft.hcis-alert--info {
    background: linear-gradient(135deg, rgba(240, 249, 255, 0.98) 0%, rgba(224, 242, 254, 0.88) 45%, rgba(238, 242, 255, 0.92) 100%);
    border: 1px solid rgba(186, 230, 253, 0.95);
    color: var(--hcis-sky-900);
    box-shadow: 0 4px 16px -2px rgba(2, 132, 199, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}
.hcis-alert--appearance-soft.hcis-alert--success {
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.98) 0%, rgba(209, 250, 229, 0.88) 45%, rgba(204, 251, 241, 0.92) 100%);
    border: 1px solid rgba(167, 243, 208, 0.95);
    color: var(--hcis-emerald-900);
    box-shadow: 0 4px 16px -2px rgba(16, 185, 129, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}
.hcis-alert--appearance-soft.hcis-alert--warning {
    background: linear-gradient(135deg, rgba(254, 252, 232, 0.98) 0%, rgba(254, 243, 199, 0.88) 45%, rgba(255, 237, 213, 0.92) 100%);
    border: 1px solid rgba(253, 230, 138, 0.95);
    color: var(--hcis-yellow-900);
    box-shadow: 0 4px 16px -2px rgba(245, 158, 11, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}
.hcis-alert--appearance-soft.hcis-alert--danger {
    background: linear-gradient(135deg, rgba(255, 241, 242, 0.98) 0%, rgba(254, 226, 226, 0.88) 45%, rgba(255, 228, 230, 0.92) 100%);
    border: 1px solid rgba(254, 202, 202, 0.95);
    color: var(--hcis-red-900);
    box-shadow: 0 4px 16px -2px rgba(239, 68, 68, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}
.hcis-alert--appearance-soft.hcis-alert--purple {
    background: linear-gradient(135deg, rgba(250, 245, 255, 0.98) 0%, rgba(243, 232, 255, 0.88) 45%, rgba(253, 242, 248, 0.92) 100%);
    border: 1px solid rgba(233, 213, 255, 0.95);
    color: #581c87;
    box-shadow: 0 4px 16px -2px rgba(168, 85, 247, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}
.hcis-alert--appearance-soft.hcis-alert--teal {
    background: linear-gradient(135deg, rgba(240, 253, 250, 0.98) 0%, rgba(204, 251, 241, 0.88) 45%, rgba(224, 242, 254, 0.92) 100%);
    border: 1px solid rgba(153, 246, 228, 0.95);
    color: #134e4a;
    box-shadow: 0 4px 16px -2px rgba(20, 184, 166, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}
.hcis-alert--appearance-soft.hcis-alert--neutral {
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.98) 0%, rgba(241, 245, 249, 0.9) 45%, rgba(226, 232, 240, 0.85) 100%);
    border: 1px solid rgba(226, 232, 240, 0.95);
    color: var(--hcis-slate-800);
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

/* ── Appearance: Glass (Translucent Frosted Crystal Multi-Glow) ──────── */
.hcis-alert--appearance-glass {
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.85) inset,
        0 8px 24px -4px rgba(15, 23, 42, 0.07),
        0 16px 36px -8px rgba(15, 23, 42, 0.05);
}
.hcis-alert--appearance-glass.hcis-alert--brand {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(238, 242, 255, 0.85) 50%, rgba(243, 232, 255, 0.78) 100%);
    border: 1px solid rgba(199, 210, 254, 0.8);
    color: var(--hcis-indigo-950);
}
.hcis-alert--appearance-glass.hcis-alert--info {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(224, 242, 254, 0.85) 50%, rgba(238, 242, 255, 0.78) 100%);
    border: 1px solid rgba(186, 230, 253, 0.8);
    color: var(--hcis-sky-900);
}
.hcis-alert--appearance-glass.hcis-alert--success {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(236, 253, 245, 0.85) 50%, rgba(204, 251, 241, 0.78) 100%);
    border: 1px solid rgba(167, 243, 208, 0.8);
    color: var(--hcis-emerald-900);
}
.hcis-alert--appearance-glass.hcis-alert--warning {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(254, 243, 199, 0.85) 50%, rgba(255, 237, 213, 0.78) 100%);
    border: 1px solid rgba(253, 230, 138, 0.8);
    color: var(--hcis-yellow-900);
}
.hcis-alert--appearance-glass.hcis-alert--danger {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(254, 226, 226, 0.85) 50%, rgba(255, 228, 230, 0.78) 100%);
    border: 1px solid rgba(254, 202, 202, 0.8);
    color: var(--hcis-red-900);
}
.hcis-alert--appearance-glass.hcis-alert--purple {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(243, 232, 255, 0.85) 50%, rgba(253, 242, 248, 0.78) 100%);
    border: 1px solid rgba(233, 213, 255, 0.8);
    color: #581c87;
}
.hcis-alert--appearance-glass.hcis-alert--teal {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(204, 251, 241, 0.85) 50%, rgba(224, 242, 254, 0.78) 100%);
    border: 1px solid rgba(153, 246, 228, 0.8);
    color: #134e4a;
}
.hcis-alert--appearance-glass.hcis-alert--neutral {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(241, 245, 249, 0.85) 50%, rgba(248, 250, 252, 0.78) 100%);
    border: 1px solid rgba(226, 232, 240, 0.8);
    color: var(--hcis-slate-800);
}

/* ── Appearance: Solid (Vibrant 3D Gradient Sheen) ────────────────────── */
.hcis-alert--appearance-solid {
    color: var(--hcis-white) !important;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow:
        0 4px 16px -2px rgba(15, 23, 42, 0.14),
        0 12px 28px -6px rgba(15, 23, 42, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.4);
}
.hcis-alert--appearance-solid.hcis-alert--brand {
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-700) 50%, var(--hcis-indigo-900) 100%);
}
.hcis-alert--appearance-solid.hcis-alert--info {
    background: linear-gradient(135deg, var(--hcis-sky-600) 0%, var(--hcis-sky-700) 50%, #075985 100%);
}
.hcis-alert--appearance-solid.hcis-alert--success {
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 50%, var(--hcis-emerald-700) 100%);
}
.hcis-alert--appearance-solid.hcis-alert--warning {
    background: linear-gradient(135deg, var(--hcis-amber-500) 0%, var(--hcis-amber-600) 50%, var(--hcis-amber-700) 100%);
}
.hcis-alert--appearance-solid.hcis-alert--danger {
    background: linear-gradient(135deg, var(--hcis-red-500) 0%, var(--hcis-red-600) 50%, var(--hcis-red-700) 100%);
}
.hcis-alert--appearance-solid.hcis-alert--purple {
    background: linear-gradient(135deg, var(--hcis-purple-500) 0%, var(--hcis-purple-600) 50%, var(--hcis-purple-700) 100%);
}
.hcis-alert--appearance-solid.hcis-alert--teal {
    background: linear-gradient(135deg, #14b8a6 0%, #0d9488 50%, #0f766e 100%);
}
.hcis-alert--appearance-solid.hcis-alert--neutral {
    background: linear-gradient(135deg, var(--hcis-slate-600) 0%, var(--hcis-slate-700) 50%, var(--hcis-slate-800) 100%);
}
.hcis-alert--appearance-solid .hcis-alert__title,
.hcis-alert--appearance-solid .hcis-alert__desc {
    color: var(--hcis-white) !important;
}

/* ── Appearance: Outline (Crisp Minimalist Border with Subtle Gradient Tint) */
.hcis-alert--appearance-outline {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.7) 0%, rgba(248, 250, 252, 0.3) 100%);
    border: 1.5px solid currentColor;
}
.hcis-alert--appearance-outline.hcis-alert--info,
.hcis-alert--appearance-outline.hcis-alert--brand { color: var(--hcis-indigo-700); border-color: rgba(99, 102, 241, 0.6); }
.hcis-alert--appearance-outline.hcis-alert--success { color: var(--hcis-emerald-700); border-color: rgba(16, 185, 129, 0.6); }
.hcis-alert--appearance-outline.hcis-alert--warning { color: var(--hcis-amber-700); border-color: rgba(245, 158, 11, 0.6); }
.hcis-alert--appearance-outline.hcis-alert--danger { color: var(--hcis-red-700); border-color: rgba(239, 68, 68, 0.6); }
.hcis-alert--appearance-outline.hcis-alert--purple { color: var(--hcis-purple-700); border-color: rgba(168, 85, 247, 0.6); }
.hcis-alert--appearance-outline.hcis-alert--teal { color: #0f766e; border-color: rgba(20, 184, 166, 0.6); }
.hcis-alert--appearance-outline.hcis-alert--neutral { color: var(--hcis-slate-700); border-color: rgba(100, 116, 139, 0.6); }

/* ── Appearance: Obsidian (Cosmic Midnight Dark Mode) ─────────────────── */
.hcis-alert--appearance-obsidian,
.hcis-alert--obsidian {
    background: linear-gradient(135deg, var(--hcis-slate-800) 0%, var(--hcis-slate-900) 50%, #090d16 100%) !important;
    color: var(--hcis-slate-50) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.08) inset,
        0 12px 28px -4px rgba(0, 0, 0, 0.5),
        0 24px 48px -8px rgba(0, 0, 0, 0.4) !important;
}
.hcis-alert--appearance-obsidian .hcis-alert__title { color: var(--hcis-white) !important; }
.hcis-alert--appearance-obsidian .hcis-alert__desc { color: var(--hcis-slate-300) !important; }

/* ── 2. GRADIENT MICRO-PATTERN OVERLAYS ──────────────────────────────── */

.hcis-alert--has-pattern::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    border-radius: inherit;
    -webkit-mask-image: linear-gradient(135deg, rgba(0, 0, 0, 0.95) 0%, rgba(0, 0, 0, 0.65) 45%, rgba(0, 0, 0, 0.15) 85%, transparent 100%);
    mask-image: linear-gradient(135deg, rgba(0, 0, 0, 0.95) 0%, rgba(0, 0, 0, 0.65) 45%, rgba(0, 0, 0, 0.15) 85%, transparent 100%);
    opacity: 0.85;
    transition: opacity 220ms ease;
}

.hcis-alert--pattern-dots::before {
    background-image:
        radial-gradient(rgba(99, 102, 241, 0.22) 1.25px, transparent 1.25px),
        radial-gradient(rgba(148, 163, 184, 0.18) 1px, transparent 1px);
    background-size: 16px 16px, 8px 8px;
}

.hcis-alert--pattern-grid::before {
    background-image:
        linear-gradient(to right, rgba(99, 102, 241, 0.12) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(148, 163, 184, 0.12) 1px, transparent 1px);
    background-size: 14px 14px;
}

.hcis-alert--pattern-mesh::before {
    background:
        radial-gradient(ellipse at top left, rgba(99, 102, 241, 0.18) 0%, transparent 60%),
        radial-gradient(ellipse at bottom right, rgba(236, 72, 153, 0.12) 0%, transparent 60%),
        radial-gradient(rgba(148, 163, 184, 0.12) 1px, transparent 1px);
    background-size: 100% 100%, 100% 100%, 10px 10px;
}

.hcis-alert--appearance-solid.hcis-alert--has-pattern::before {
    opacity: 0.2;
    -webkit-mask-image: linear-gradient(135deg, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.4) 60%, transparent 100%);
    mask-image: linear-gradient(135deg, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.4) 60%, transparent 100%);
    background-image: radial-gradient(rgba(255, 255, 255, 0.8) 1.2px, transparent 1.2px);
    background-size: 10px 10px;
}

.hcis-alert--appearance-obsidian.hcis-alert--has-pattern::before {
    opacity: 0.35;
    -webkit-mask-image: linear-gradient(135deg, rgba(0, 0, 0, 0.95) 0%, rgba(0, 0, 0, 0.5) 60%, transparent 100%);
    mask-image: linear-gradient(135deg, rgba(0, 0, 0, 0.95) 0%, rgba(0, 0, 0, 0.5) 60%, transparent 100%);
    background-image:
        radial-gradient(rgba(129, 140, 248, 0.3) 1.25px, transparent 1.25px),
        radial-gradient(rgba(255, 255, 255, 0.2) 1px, transparent 1px);
    background-size: 16px 16px, 8px 8px;
}

/* ── 3. LUMINOUS AMBIENT HALO GLOW ────────────────────────────────────── */

.hcis-alert--glow.hcis-alert--brand,
.hcis-alert--glow.hcis-alert--info {
    box-shadow:
        0 0 0 1px rgba(99, 102, 241, 0.2),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(99, 102, 241, 0.18),
        0 0 80px 10px rgba(79, 70, 229, 0.08);
}
.hcis-alert--glow.hcis-alert--success {
    box-shadow:
        0 0 0 1px rgba(16, 185, 129, 0.2),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(16, 185, 129, 0.18),
        0 0 80px 10px rgba(5, 150, 105, 0.08);
}
.hcis-alert--glow.hcis-alert--warning {
    box-shadow:
        0 0 0 1px rgba(245, 158, 11, 0.2),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(245, 158, 11, 0.18),
        0 0 80px 10px rgba(217, 119, 6, 0.08);
}
.hcis-alert--glow.hcis-alert--danger {
    box-shadow:
        0 0 0 1px rgba(239, 68, 68, 0.2),
        0 4px 16px -2px rgba(15, 23, 42, 0.06),
        0 12px 28px -6px rgba(15, 23, 42, 0.08),
        0 0 35px 0 rgba(239, 68, 68, 0.18),
        0 0 80px 10px rgba(220, 38, 38, 0.08);
}
.hcis-alert--glow.hcis-alert--purple {
    box-shadow:
        0 0 0 1px rgba(168, 85, 247, 0.2),
        0 0 35px 0 rgba(168, 85, 247, 0.18),
        0 0 80px 10px rgba(124, 58, 237, 0.08);
}
.hcis-alert--glow.hcis-alert--teal {
    box-shadow:
        0 0 0 1px rgba(20, 184, 166, 0.2),
        0 0 35px 0 rgba(20, 184, 166, 0.18),
        0 0 80px 10px rgba(13, 148, 136, 0.08);
}

/* ── 4. 3D JEWEL ICON BADGE ────────────────────────────────────────────── */

.hcis-alert__icon-badge {
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

.hcis-alert__icon-badge :deep(svg),
.hcis-alert__icon-badge :deep(.hcis-icon) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    line-height: 1;
}

.hcis-alert:hover .hcis-alert__icon-badge {
    transform: scale(1.08) rotate(3deg);
}

.hcis-alert--info .hcis-alert__icon-badge,
.hcis-alert--brand .hcis-alert__icon-badge {
    background: linear-gradient(135deg, var(--hcis-indigo-400) 0%, var(--hcis-indigo-600) 100%);
}
.hcis-alert--success .hcis-alert__icon-badge {
    background: linear-gradient(135deg, var(--hcis-emerald-400) 0%, var(--hcis-emerald-600) 100%);
}
.hcis-alert--warning .hcis-alert__icon-badge {
    background: linear-gradient(135deg, var(--hcis-amber-400) 0%, var(--hcis-amber-600) 100%);
}
.hcis-alert--danger .hcis-alert__icon-badge {
    background: linear-gradient(135deg, var(--hcis-red-400) 0%, var(--hcis-red-600) 100%);
}
.hcis-alert--purple .hcis-alert__icon-badge {
    background: linear-gradient(135deg, #c084fc 0%, var(--hcis-purple-700) 100%);
}
.hcis-alert--teal .hcis-alert__icon-badge {
    background: linear-gradient(135deg, #2dd4bf 0%, #0f766e 100%);
}
.hcis-alert--neutral .hcis-alert__icon-badge {
    background: linear-gradient(135deg, var(--hcis-slate-400) 0%, var(--hcis-slate-600) 100%);
}

/* In Solid Mode, Icon Badge becomes a glass pill */
.hcis-alert--appearance-solid .hcis-alert__icon-badge {
    background: rgba(255, 255, 255, 0.22) !important;
    border: 1px solid rgba(255, 255, 255, 0.35);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.5);
}

/* ── 5. BODY TYPOGRAPHY & BADGES ───────────────────────────────────────── */

.hcis-alert__body {
    position: relative;
    z-index: 1;
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-self: center;
}

.hcis-alert__header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.hcis-alert__title {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 750;
    letter-spacing: -0.01em;
    line-height: 1.35;
    color: var(--hcis-slate-900);
}

.hcis-alert__desc {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 450;
    line-height: 1.5;
    color: var(--hcis-slate-700);
    word-break: break-word;
}

.hcis-alert__badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.6875rem;
    font-weight: 700;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(0, 0, 0, 0.06);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

/* ── 6. ACTIONS & CLOSE BUTTON ─────────────────────────────────────────── */

.hcis-alert__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.375rem;
    flex-wrap: wrap;
}

.hcis-alert__close {
    position: relative;
    z-index: 1;
    appearance: none;
    border: none;
    background: transparent;
    color: var(--hcis-slate-500);
    padding: 0.3125rem;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    align-self: center;
    margin-left: auto;
    transition: color 150ms ease, background 150ms ease, transform 150ms ease;
}

.hcis-alert__close:hover {
    color: var(--hcis-red-500);
    background: rgba(239, 68, 68, 0.08);
    transform: rotate(90deg);
}

.hcis-alert--appearance-solid .hcis-alert__close {
    color: rgba(255, 255, 255, 0.85);
}
.hcis-alert--appearance-solid .hcis-alert__close:hover {
    color: var(--hcis-white);
    background: rgba(255, 255, 255, 0.18);
}

/* ── 7. SIZE TIERS (sm, md, lg) ───────────────────────────────────────── */

.hcis-alert--size-sm {
    padding: 0.625rem 0.875rem;
    gap: 0.625rem;
}
.hcis-alert--size-sm .hcis-alert__icon-badge {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
}
.hcis-alert--size-sm .hcis-alert__title {
    font-size: 0.8125rem;
}
.hcis-alert--size-sm .hcis-alert__desc {
    font-size: 0.75rem;
}

.hcis-alert--size-lg {
    padding: 1.125rem 1.375rem;
    gap: 1.125rem;
}
.hcis-alert--size-lg .hcis-alert__icon-badge {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 10px;
    font-size: 1.125rem;
}
.hcis-alert--size-lg .hcis-alert__title {
    font-size: 1rem;
}
.hcis-alert--size-lg .hcis-alert__desc {
    font-size: 0.875rem;
}

/* ── 8. GEOMETRICAL CORNER MODIFIERS ──────────────────────────────────── */

.hcis-alert--rounded-none { border-radius: 0 !important; }
.hcis-alert--rounded-none .hcis-alert__icon-badge { border-radius: 0 !important; }
.hcis-alert--rounded-sm { border-radius: 6px !important; }
.hcis-alert--rounded-sm .hcis-alert__icon-badge { border-radius: 5px !important; }
.hcis-alert--rounded-md { border-radius: 10px !important; }
.hcis-alert--rounded-md .hcis-alert__icon-badge { border-radius: 8px !important; }
.hcis-alert--rounded-lg { border-radius: 14px !important; }
.hcis-alert--rounded-lg .hcis-alert__icon-badge { border-radius: 10px !important; }
.hcis-alert--rounded-full { border-radius: 9999px !important; }
.hcis-alert--rounded-full .hcis-alert__icon-badge { border-radius: 9999px !important; }

/* ── 9. EXIT TRANSITION ANIMATION ─────────────────────────────────────── */

.hcis-alert-fade-leave-active {
    transition: opacity 200ms ease, transform 200ms ease, max-height 240ms ease;
}
.hcis-alert-fade-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.97);
}
</style>
