<template>
    <section
        class="hcis-context-banner"
        :class="[
            `hcis-context-banner--variant-${variant}`,
            `hcis-context-banner--sentiment-${sentimentNormal}`,
            {
                'hcis-context-banner--glow': glow,
                'hcis-context-banner--bordered': bordered,
            }
        ]"
        aria-label="Konteks otorisasi dan hak akses modul"
    >
        <!-- Signature Geometric Background Pattern Layer -->
        <div v-if="pattern === 'grid'" class="hcis-pattern-grid hcis-pattern-grid--diagonal" aria-hidden="true" />
        <div v-else-if="pattern === 'dots'" class="hcis-pattern-dots" aria-hidden="true" />
        <div v-else-if="pattern === 'crosshair'" class="hcis-pattern-crosshair" aria-hidden="true" />

        <div class="hcis-context-banner__inner">
            <!-- ═══════════════════════════════════════════════════════════
                 LEFT: ACTIVE FEATURE GROUP / IDENTITY
                 ═══════════════════════════════════════════════════════════ -->
            <div class="hcis-context-banner__identity">
                <!-- Concentric Jewel Icon Box -->
                <slot name="icon">
                    <div class="hcis-context-banner__icon-box" :class="`hcis-context-banner__icon-box--${sentimentNormal}`">
                        <HcisIcon :name="icon" size="md" />
                    </div>
                </slot>

                <div class="hcis-context-banner__copy">
                    <slot name="identity">
                        <div v-if="kicker || code" class="hcis-context-banner__kicker-row">
                            <span v-if="kicker" class="hcis-context-banner__kicker">{{ kicker }}</span>
                            <span v-if="code" class="hcis-context-banner__code">{{ code }}</span>
                        </div>
                        <h2 class="hcis-context-banner__title">{{ title }}</h2>
                        <p v-if="subtitle" class="hcis-context-banner__subtitle">{{ subtitle }}</p>
                    </slot>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════
                 RIGHT: ROLE, SCOPE & CAPABILITIES CHIPS
                 ═══════════════════════════════════════════════════════════ -->
            <div class="hcis-context-banner__meta">
                <!-- Role / Persona Badge -->
                <span v-if="roleLabel" class="hcis-context-banner__badge hcis-context-banner__badge--role">
                    <HcisIcon :name="roleIcon" size="2xs" class="me-1" />
                    {{ roleLabel }}
                </span>

                <!-- Scope Authorization Badge -->
                <slot name="scope">
                    <HcisBadge
                        v-if="scopeLabel"
                        :label="scopeLabel"
                        :variant="scopeVariant"
                        :radar="scopeRadar"
                        size="sm"
                        class="hcis-context-banner__badge--scope"
                    >
                        <template #leading>
                            <HcisIcon :name="scopeIcon" size="2xs" />
                        </template>
                    </HcisBadge>
                </slot>

                <!-- Functional Capabilities List -->
                <div v-if="capabilities.length || $slots.capabilities" class="hcis-context-banner__capabilities">
                    <slot name="capabilities">
                        <span
                            v-for="(cap, idx) in capabilities"
                            :key="cap.id || cap.label || idx"
                            class="hcis-context-banner__cap-chip"
                            :class="{ 'is-active': cap.active !== false }"
                        >
                            <HcisIcon v-if="cap.icon" :name="cap.icon" size="2xs" class="me-1" />
                            <span>{{ cap.label }}</span>
                        </span>
                    </slot>
                </div>

                <!-- Custom Action Buttons Slot (e.g. Switch Role / Unit) -->
                <div v-if="$slots.actions" class="hcis-context-banner__actions">
                    <slot name="actions" />
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisBadge from './HcisBadge.vue';

defineOptions({ name: 'HcisContextBanner' });

const props = defineProps({
    title: { type: String, required: true },
    code: { type: String, default: '' },
    kicker: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    icon: { type: String, default: 'collection' },
    roleLabel: { type: String, default: '' },
    roleIcon: { type: String, default: 'person-badge' },
    scopeLabel: { type: String, default: '' },
    scopeIcon: { type: String, default: 'people' },
    scopeVariant: {
        type: String,
        default: 'brand',
        validator: (v) => ['brand', 'primary', 'success', 'warning', 'danger', 'purple', 'teal', 'neutral', 'legacy'].includes(v),
    },
    scopeRadar: { type: Boolean, default: false },
    capabilities: {
        type: Array,
        default: () => [],
    },
    variant: {
        type: String,
        default: 'glass',
        validator: (v) => ['glass', 'surface', 'brand', 'flat', 'obsidian'].includes(v),
    },
    sentiment: {
        type: String,
        default: 'brand',
        validator: (v) => ['brand', 'primary', 'purple', 'violet', 'teal', 'cyan', 'success', 'warning', 'danger', 'info', 'neutral'].includes(v),
    },
    pattern: {
        type: String,
        default: 'grid',
        validator: (v) => ['none', 'grid', 'dots', 'crosshair'].includes(v),
    },
    glow: { type: Boolean, default: true },
    bordered: { type: Boolean, default: true },
});

const sentimentNormal = computed(() => {
    if (props.sentiment === 'good' || props.sentiment === 'success') return 'success';
    if (props.sentiment === 'bad' || props.sentiment === 'danger') return 'danger';
    if (props.sentiment === 'primary' || props.sentiment === 'brand') return 'brand';
    if (props.sentiment === 'violet' || props.sentiment === 'purple') return 'purple';
    if (props.sentiment === 'cyan' || props.sentiment === 'teal') return 'teal';
    return props.sentiment;
});
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   HCIS ENTERPRISE SUITE - CONTEXT BANNER
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-context-banner {
    position: relative;
    width: 100%;
    border-radius: var(--hcis-radius-xl, 16px);
    overflow: hidden;
    font-family: var(--hcis-font-body, inherit);
    box-sizing: border-box;
    transition-property: var(--hcis-transition-props);
    transition-duration: 250ms;
    transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-context-banner__inner {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    padding: 0.875rem 1.25rem;
    flex-wrap: wrap;
    z-index: 2;
}

/* ═══════════════════════════════════════════════════════════════════════════
   VARIANTS
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-context-banner--variant-glass {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.05);
}

.hcis-context-banner--variant-surface {
    background: var(--hcis-white);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.hcis-context-banner--variant-brand {
    background: linear-gradient(135deg, var(--hcis-indigo-800) 0%, var(--hcis-indigo-600) 50%, var(--hcis-indigo-700) 100%);
    color: var(--hcis-white);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 24px -4px rgba(79, 70, 229, 0.35);
}

.hcis-context-banner--variant-brand .hcis-context-banner__title {
    color: var(--hcis-white) !important;
}

.hcis-context-banner--variant-brand .hcis-context-banner__kicker {
    color: rgba(224, 231, 255, 0.85) !important;
}

.hcis-context-banner--variant-brand .hcis-context-banner__code {
    background: rgba(255, 255, 255, 0.15);
    color: var(--hcis-white);
    border-color: rgba(255, 255, 255, 0.25);
}

.hcis-context-banner--variant-obsidian {
    background: var(--hcis-slate-900);
    color: var(--hcis-white);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
}

.hcis-context-banner--variant-obsidian .hcis-context-banner__title {
    color: var(--hcis-white) !important;
}

.hcis-context-banner--variant-obsidian .hcis-context-banner__kicker {
    color: var(--hcis-slate-400) !important;
}

.hcis-context-banner--variant-flat {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}

/* ═══════════════════════════════════════════════════════════════════════════
   IDENTITY BLOCK
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-context-banner__identity {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    min-width: 0;
}

/* Jewel Icon Box */
.hcis-context-banner__icon-box {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}

.hcis-context-banner__icon-box--brand {
    background: linear-gradient(135deg, var(--hcis-indigo-50) 0%, var(--hcis-indigo-100) 100%);
    color: var(--hcis-indigo-600);
    border: 1px solid rgba(199, 210, 254, 0.8);
}

.hcis-context-banner--variant-brand .hcis-context-banner__icon-box {
    background: rgba(255, 255, 255, 0.15);
    color: var(--hcis-white);
    border-color: rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.hcis-context-banner__icon-box--success {
    background: linear-gradient(135deg, var(--hcis-emerald-50) 0%, var(--hcis-emerald-100) 100%);
    color: var(--hcis-emerald-600);
    border: 1px solid rgba(167, 243, 208, 0.8);
}

.hcis-context-banner__icon-box--warning {
    background: linear-gradient(135deg, var(--hcis-amber-50) 0%, var(--hcis-amber-100) 100%);
    color: var(--hcis-amber-600);
    border: 1px solid rgba(253, 230, 138, 0.8);
}

.hcis-context-banner__icon-box--danger {
    background: linear-gradient(135deg, var(--hcis-red-50) 0%, var(--hcis-red-100) 100%);
    color: var(--hcis-red-600);
    border: 1px solid rgba(254, 202, 202, 0.8);
}

.hcis-context-banner__icon-box--purple {
    background: linear-gradient(135deg, var(--hcis-violet-50) 0%, #ede9fe 100%);
    color: var(--hcis-violet-600);
    border: 1px solid rgba(221, 214, 254, 0.8);
}

.hcis-context-banner__icon-box--teal {
    background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%);
    color: #0d9488;
    border: 1px solid rgba(153, 246, 228, 0.8);
}

/* Copy */
.hcis-context-banner__copy {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.hcis-context-banner__kicker-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.hcis-context-banner__kicker {
    font-size: 0.625rem;
    font-weight: 850;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.hcis-context-banner--sentiment-warning .hcis-context-banner__kicker {
    color: var(--hcis-amber-600) !important;
}

.hcis-context-banner--sentiment-danger .hcis-context-banner__kicker {
    color: var(--hcis-red-600) !important;
}

.hcis-context-banner__code {
    font-size: 0.625rem;
    font-weight: 800;
    font-family: var(--hcis-font-mono, monospace);
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    padding: 1px 5px;
    border-radius: 4px;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.hcis-context-banner__title {
    font-size: 0.9375rem;
    font-weight: 850;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    margin: 0;
    line-height: 1.25;
}

.hcis-context-banner__subtitle {
    font-size: 0.775rem;
    font-weight: 500;
    color: var(--hcis-text-subtle, var(--hcis-slate-500));
    margin: 0.15rem 0 0;
    line-height: 1.4;
}

/* ═══════════════════════════════════════════════════════════════════════════
   META & BADGES BLOCK
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-context-banner__meta {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    flex-wrap: wrap;
}

.hcis-context-banner__badge--role {
    display: inline-flex;
    align-items: center;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 750;
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.hcis-context-banner--variant-brand .hcis-context-banner__badge--role {
    background: rgba(255, 255, 255, 0.15);
    color: var(--hcis-white);
    border-color: rgba(255, 255, 255, 0.25);
}

.hcis-context-banner__capabilities {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}

.hcis-context-banner__cap-chip {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 0.65625rem;
    font-weight: 700;
    background: rgba(241, 245, 249, 0.85);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    border: 1px solid rgba(226, 232, 240, 0.85);
}

.hcis-context-banner--variant-brand .hcis-context-banner__cap-chip {
    background: rgba(255, 255, 255, 0.12);
    color: var(--hcis-white);
    border-color: rgba(255, 255, 255, 0.2);
}

.hcis-context-banner__actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ═══════════════════════════════════════════════════════════════════════════
   PATTERN OVERLAYS
   ═══════════════════════════════════════════════════════════════════════════ */
.hcis-pattern-grid,
.hcis-pattern-dots,
.hcis-pattern-crosshair {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 1;
    opacity: 0.55;
}

.hcis-pattern-grid--diagonal {
    background-image: 
        linear-gradient(to right, rgba(99, 102, 241, 0.08) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(99, 102, 241, 0.08) 1px, transparent 1px);
    background-size: 20px 20px;
}

.hcis-pattern-dots {
    background-image: radial-gradient(rgba(99, 102, 241, 0.15) 1px, transparent 1px);
    background-size: 14px 14px;
}

.hcis-context-banner--variant-brand .hcis-pattern-grid--diagonal {
    background-image: 
        linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
}
</style>
