<template>
    <div
        class="hcis-container"
        :class="[
            `hcis-container--${variant}`,
            `hcis-container--${size}`,
            `hcis-container--pad-${padding}`,
            {
                'hcis-container--fluid': fluid,
                'hcis-container--centered': centered,
                'hcis-container--sticky-footer': stickyFooter,
                'hcis-container--loading': loading,
                'hcis-container--interactive': interactive,
            }
        ]"
    >
        <!-- Loading Overlay -->
        <div v-if="loading" class="hcis-container__loading-overlay" aria-hidden="true">
            <div class="hcis-container__loading-spinner">
                <svg viewBox="0 0 50 50" class="hcis-spinner-svg">
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4" class="hcis-spinner-track" />
                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4" class="hcis-spinner-head" />
                </svg>
            </div>
        </div>

        <!-- Optional Header Section -->
        <header
            v-if="$slots.header || title || subtitle || $slots.actions"
            class="hcis-container__header"
            :class="{ 'hcis-container__header--divider': headerDivider }"
        >
            <slot name="header">
                <div class="hcis-container__header-main">
                    <div v-if="icon || $slots.icon" class="hcis-container__header-icon">
                        <slot name="icon">
                            <HcisIcon :name="icon" size="md" />
                        </slot>
                    </div>
                    <div class="hcis-container__header-text">
                        <div class="hcis-container__header-title-row">
                            <h3 v-if="title" class="hcis-container__title">{{ title }}</h3>
                            <HcisBadge v-if="badge" :label="badge" :variant="badgeVariant" size="xs" />
                        </div>
                        <p v-if="subtitle" class="hcis-container__subtitle">{{ subtitle }}</p>
                    </div>
                </div>
                <div v-if="$slots.actions" class="hcis-container__header-actions">
                    <slot name="actions" />
                </div>
            </slot>
        </header>

        <!-- Main Body Content Slot -->
        <div class="hcis-container__body">
            <slot />
        </div>

        <!-- Optional Footer Section -->
        <footer
            v-if="$slots.footer"
            class="hcis-container__footer"
            :class="{ 'hcis-container__footer--divider': footerDivider }"
        >
            <slot name="footer" />
        </footer>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisBadge from './HcisBadge.vue';

defineOptions({ name: 'HcisContainer' });

const props = defineProps({
    variant: {
        type: String,
        default: 'card',
        validator: (v) => ['card', 'glass', 'flat', 'bordered', 'hero', 'ghost'].includes(v),
    },
    size: {
        type: String,
        default: 'xl',
        validator: (v) => ['sm', 'md', 'lg', 'xl', '2xl', 'full'].includes(v),
    },
    padding: {
        type: String,
        default: 'md',
        validator: (v) => ['none', 'xs', 'sm', 'md', 'lg', 'xl'].includes(v),
    },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    icon: { type: String, default: '' },
    badge: { type: String, default: '' },
    badgeVariant: { type: String, default: 'brand' },
    fluid: { type: Boolean, default: false },
    centered: { type: Boolean, default: false },
    headerDivider: { type: Boolean, default: true },
    footerDivider: { type: Boolean, default: true },
    stickyFooter: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    interactive: { type: Boolean, default: false },
});
</script>

<style scoped>
.hcis-container {
    position: relative;
    width: 100%;
    margin-left: auto;
    margin-right: auto;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    transition: box-shadow var(--hcis-duration-fast) var(--hcis-ease), border-color var(--hcis-duration-fast) var(--hcis-ease), background var(--hcis-duration-fast) var(--hcis-ease);
}

/* Size Max Width Breakpoints */
.hcis-container--sm { max-width: 640px; }
.hcis-container--md { max-width: 848px; }
.hcis-container--lg { max-width: 1080px; }
.hcis-container--xl { max-width: 1280px; }
.hcis-container--2xl { max-width: 1536px; }
.hcis-container--full,
.hcis-container--fluid { max-width: 100%; }

/* Centered Modifier */
.hcis-container--centered {
    margin-top: auto;
    margin-bottom: auto;
}

/* Variant Visual Styles */
.hcis-container--card {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
}

.hcis-container--glass {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.1), 0 4px 12px -2px rgba(15, 23, 42, 0.04);
}

.hcis-container--flat {
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-xl, 14px);
}

.hcis-container--bordered {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-xl, 14px);
}

.hcis-container--hero {
    background: linear-gradient(180deg, var(--hcis-brand-50, var(--hcis-indigo-50)) 0%, var(--hcis-surface, var(--hcis-white)) 100%);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-top: 3px solid var(--hcis-brand-600, var(--hcis-indigo-600));
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow: 0 10px 30px -4px rgb(var(--hcis-brand-600-rgb, 79 70 229) / 0.08), inset 0 1px 0 0 rgb(255 255 255 / 0.9);
}

.hcis-container--ghost {
    background: transparent;
    border: none;
    box-shadow: none;
}

/* Interactive Hover State */
.hcis-container--interactive:hover {
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    box-shadow: 0 16px 36px -6px rgb(var(--hcis-slate-900-rgb, 15 23 42) / 0.1), 0 4px 12px -2px rgb(var(--hcis-slate-900-rgb, 15 23 42) / 0.04);
    transform: translateY(-2px);
}

/* Padding System */
.hcis-container--pad-none .hcis-container__body { padding: 0; }
.hcis-container--pad-xs .hcis-container__body { padding: 0.75rem; }
.hcis-container--pad-sm .hcis-container__body { padding: 1rem; }
.hcis-container--pad-md .hcis-container__body { padding: 1.5rem; }
.hcis-container--pad-lg .hcis-container__body { padding: 2rem; }
.hcis-container--pad-xl .hcis-container__body { padding: 2.5rem; }

/* Header Section */
.hcis-container__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.5rem;
    gap: 1rem;
    flex-wrap: wrap;
}

.hcis-container__header--divider {
    border-bottom: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
}

.hcis-container__header-main {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    min-width: 0;
}

.hcis-container__header-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--hcis-radius-lg);
    background: var(--hcis-brand-50);
    color: var(--hcis-brand-600);
    border: 1px solid var(--hcis-brand-200);
    flex-shrink: 0;
}

.hcis-container__header-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.hcis-container__header-title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.hcis-container__title {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--hcis-text-strong);
    margin: 0;
    letter-spacing: -0.015em;
    line-height: 1.3;
}

.hcis-container__subtitle {
    font-size: 0.825rem;
    color: var(--hcis-text-muted);
    margin: 0.25rem 0 0 0;
    line-height: 1.4;
}

.hcis-container__header-actions {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    flex-wrap: wrap;
}

/* Body Content */
.hcis-container__body {
    position: relative;
    width: 100%;
    flex: 1 1 auto;
    box-sizing: border-box;
}

/* Footer Section */
.hcis-container__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 1rem 1.5rem;
    gap: 0.75rem;
    flex-wrap: wrap;
    background: var(--hcis-surface-soft);
    border-bottom-left-radius: inherit;
    border-bottom-right-radius: inherit;
}

.hcis-container__footer--divider {
    border-top: 1px solid var(--hcis-border-subtle);
}

.hcis-container--sticky-footer .hcis-container__footer {
    position: sticky;
    bottom: 0;
    z-index: 20;
    backdrop-filter: blur(var(--hcis-glass-blur));
}

/* Loading Overlay */
.hcis-container__loading-overlay {
    position: absolute;
    inset: 0;
    background: var(--hcis-surface);
    opacity: 0.85;
    backdrop-filter: blur(var(--hcis-glass-blur-sm));
    z-index: 30;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: inherit;
}

.hcis-container__loading-spinner {
    width: 2.75rem;
    height: 2.75rem;
}

.hcis-spinner-svg {
    width: 100%;
    height: 100%;
    animation: hcis-icon-spin 1.2s linear infinite;
    transform-origin: center center;
    transform-box: fill-box;
}

.hcis-spinner-track { stroke: var(--hcis-border-subtle); }
.hcis-spinner-head {
    stroke: var(--hcis-brand-600);
    stroke-dasharray: 90, 150;
    stroke-linecap: round;
}

/* Responsive Mobile Layouts */
@media (max-width: 768px) {
    .hcis-container {
        border-radius: var(--hcis-radius-lg);
    }

    .hcis-container__header {
        padding: 1rem;
        flex-direction: column;
        align-items: flex-start;
    }

    .hcis-container__header-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .hcis-container--pad-md .hcis-container__body { padding: 1rem; }
    .hcis-container--pad-lg .hcis-container__body { padding: 1.25rem; }
    .hcis-container--pad-xl .hcis-container__body { padding: 1.5rem; }

    .hcis-container__footer {
        padding: 0.875rem 1rem;
    }
}
</style>
