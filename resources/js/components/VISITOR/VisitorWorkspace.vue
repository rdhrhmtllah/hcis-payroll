<template>
    <main class="visitor-workspace">
        <HcisHeader
            :title="title"
            :subtitle="subtitle"
            :kicker="kicker"
            :icon="icon"
            :sentiment="sentiment"
            variant="glass"
            size="lg"
        >
            <template v-if="$slots.actions" #actions>
                <slot name="actions" />
            </template>
        </HcisHeader>

        <div v-if="normalizedStats.length" class="visitor-workspace__stats">
            <HcisStatGroup
                :cols="resolvedStatColumns"
                gap="sm"
                appearance="crystal"
            >
                <HcisStatCard
                    v-for="(stat, index) in normalizedStats"
                    :key="stat.id || stat.key || index"
                    :label="stat.label"
                    :value="stat.value"
                    :prefix="stat.prefix"
                    :suffix="stat.suffix"
                    :caption="stat.caption || stat.subtext"
                    :icon="stat.icon"
                    :icon-animation="stat.iconAnimation"
                    :sentiment="stat.sentiment"
                    :delta="stat.delta || stat.trendValue"
                    :trend="stat.trend"
                    :progress="stat.progress"
                    :target="stat.target"
                    :loading="stat.loading"
                    :interactive="Boolean(stat.clickable || stat.interactive || stat.onClick || stat.targetTab)"
                    :pattern="stat.pattern"
                    :layout="stat.layout || 'compact'"
                    :appearance="stat.appearance || 'crystal'"
                    @click="handleStatClick(stat)"
                />
            </HcisStatGroup>
        </div>

        <div v-if="$slots.context" class="visitor-workspace__context">
            <slot name="context" />
        </div>

        <HcisCard
            :title="sectionTitle"
            :description="sectionDescription"
            :icon="sectionIcon"
            :sentiment="sentiment"
            variant="glass"
            size="lg"
            divider
            class="visitor-workspace__surface"
        >
            <HcisTabs
                v-if="normalizedTabs.length"
                :model-value="modelValue"
                :tabs="normalizedTabs"
                variant="header"
                size="md"
                block
                :aria-label="tabsAriaLabel"
                @update:model-value="$emit('update:modelValue', $event)"
            />

            <section
                class="visitor-workspace__panel"
                :class="{ 'visitor-workspace__panel--with-tabs': normalizedTabs.length > 0 }"
                :aria-label="activeTab?.label || sectionTitle"
            >
                <slot :name="`panel-${modelValue}`" :active-tab="activeTab">
                    <slot />
                </slot>
            </section>
        </HcisCard>
    </main>
</template>

<script setup>
import { computed } from 'vue';
import { HcisCard, HcisHeader, HcisStatCard, HcisStatGroup, HcisTabs } from '@/components/ui';

defineOptions({ name: 'VisitorWorkspace' });

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    kicker: { type: String, default: 'Visitor Management System' },
    icon: { type: String, default: 'person-vcard' },
    sentiment: { type: String, default: 'brand' },
    stats: { type: Array, default: () => [] },
    statColumns: { type: [Number, String], default: null },
    tabs: { type: Array, default: () => [] },
    modelValue: { type: String, default: '' },
    tabsAriaLabel: { type: String, default: 'Navigasi fitur Visitor' },
    sectionTitle: { type: String, default: '' },
    sectionDescription: { type: String, default: '' },
    sectionIcon: { type: String, default: 'layout-text-window-reverse' },
});

const emit = defineEmits(['update:modelValue', 'stat-click']);

function handleStatClick(stat) {
    emit('stat-click', stat);
    if (typeof stat.onClick === 'function') {
        stat.onClick();
    } else if (stat.targetTab) {
        emit('update:modelValue', stat.targetTab);
    }
}

const resolvedStatColumns = computed(() => {
    if (props.statColumns !== null && props.statColumns !== undefined) {
        return props.statColumns;
    }
    const count = normalizedStats.value.length;
    if (count >= 4) return 4;
    if (count === 3) return 3;
    if (count === 2) return 2;
    if (count === 1) return 1;
    return 3;
});

const normalizedStats = computed(() =>
    props.stats.map((item, idx) => ({
        ...item,
        label: item.label || item.title || '',
        suffix: item.suffix || item.unit || '',
        sentiment: item.sentiment || item.variant || sentimentFromLegacyClass(item.bgIcon),
        pattern: item.pattern || (idx % 2 === 0 ? 'dots' : 'grid'),
        layout: item.layout || 'compact',
        appearance: item.appearance || 'crystal',
    })),
);

const normalizedTabs = computed(() =>
    props.tabs.map((tab) => ({
        ...tab,
        key: tab.key || tab.id,
        badge: tab.badge ?? tab.count ?? null,
    })),
);

const activeTab = computed(
    () => normalizedTabs.value.find((tab) => tab.key === props.modelValue) || normalizedTabs.value[0] || null,
);

function sentimentFromLegacyClass(value = '') {
    if (value.includes('success')) return 'success';
    if (value.includes('warning')) return 'warning';
    if (value.includes('danger')) return 'danger';
    if (value.includes('primary')) return 'brand';
    return 'neutral';
}
</script>

<style scoped>
.visitor-workspace {
    display: grid;
    width: 100%;
    gap: var(--hcis-space-4, 1rem);
}

.visitor-workspace__context {
    min-width: 0;
}

.visitor-workspace__stats {
    width: 100%;
    min-width: 0;
}

.visitor-workspace__surface {
    min-width: 0;
    /* Allow nested desktop panels (e.g. Permit readiness) to stick to the page scroll. */
    overflow: visible;
}

.visitor-workspace__panel {
    min-width: 0;
    padding-top: 0;
}

.visitor-workspace__panel--with-tabs {
    padding-top: var(--hcis-space-4, 1.15rem);
}

.visitor-workspace__surface :deep(.hcis-card__header) {
    padding: 1.25rem 1.65rem 1.15rem !important;
}

.visitor-workspace__surface :deep(.hcis-card__body) {
    padding: 1.35rem 1.65rem !important;
}

@media (max-width: 991.98px) {
    .visitor-workspace {
        padding-bottom: calc(2.5rem + env(safe-area-inset-bottom, 0px));
    }

    .visitor-workspace :deep(.hcis-card__body) {
        padding: 0.875rem !important;
    }

    .visitor-workspace :deep(.hcis-card__header) {
        padding: 0.875rem 0.875rem 0.625rem !important;
    }

    .visitor-workspace__surface {
        border-radius: 12px;
    }
}

@media (max-width: 640px) {
    .visitor-workspace {
        gap: 0.75rem;
    }

    .visitor-workspace :deep(.hcis-card__body) {
        padding: 0.65rem 0.5rem !important;
    }

    .visitor-workspace :deep(.hcis-card__header) {
        padding: 0.65rem 0.65rem 0.5rem !important;
    }

    .visitor-workspace :deep(.hcis-card__header-description) {
        font-size: 0.71875rem !important;
        line-height: 1.35 !important;
    }

    .visitor-workspace :deep(.hcis-header) {
        border-radius: 14px;
    }

    .visitor-workspace :deep(.hcis-header__inner) {
        padding: 0.85rem 1rem !important;
    }

    .visitor-workspace :deep(.hcis-header__kicker) {
        font-size: 0.625rem;
        letter-spacing: 0.08em;
        font-weight: 800;
        color: var(--hcis-brand-600, #4f46e5);
    }

    .visitor-workspace :deep(.hcis-header__title) {
        font-size: 1.15rem !important;
        font-weight: 850;
        letter-spacing: -0.02em;
        line-height: 1.25;
    }

    .visitor-workspace :deep(.hcis-header__subtitle) {
        font-size: 0.78125rem !important;
        line-height: 1.45;
        color: var(--hcis-slate-600, #475569) !important;
    }

    .visitor-workspace :deep(.hcis-header__icon-badge) {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    }

    .visitor-workspace :deep(.hcis-header__actions) {
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px dashed rgba(203, 213, 225, 0.8);
    }

    .visitor-workspace :deep(.hcis-header__actions .hcis-btn) {
        width: 100%;
        border-radius: 8px;
        font-weight: 700;
        height: 2.35rem;
    }

    .visitor-workspace__stats :deep(.hcis-stat-group) {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        gap: 0.65rem !important;
        padding: 2px 2px 0.35rem 2px !important;
        scrollbar-width: none !important;
        -webkit-overflow-scrolling: touch !important;
        scroll-snap-type: x mandatory !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .visitor-workspace__stats :deep(.hcis-stat-group)::-webkit-scrollbar {
        display: none !important;
    }

    .visitor-workspace__stats :deep(.hcis-stat) {
        flex: 0 0 142px !important;
        min-width: 142px !important;
        max-width: 142px !important;
        scroll-snap-align: start !important;
        padding: 0.65rem 0.75rem !important;
        min-height: 5rem !important;
        height: auto !important;
        border-radius: 12px !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
    }

    .visitor-workspace__stats :deep(.hcis-stat__compact-wrap) {
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        height: 100% !important;
        width: 100% !important;
        overflow: hidden !important;
    }

    .visitor-workspace__stats :deep(.hcis-stat__compact-wrap > div:first-child) {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.4rem !important;
        width: 100% !important;
        margin-bottom: 0.35rem !important;
        overflow: hidden !important;
    }

    .visitor-workspace__stats :deep(.hcis-stat__icon-box) {
        width: 1.65rem !important;
        height: 1.65rem !important;
        font-size: 0.8125rem !important;
        border-radius: 6px !important;
        flex-shrink: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .visitor-workspace__stats :deep(.hcis-stat__label) {
        font-size: 0.6875rem !important;
        font-weight: 700 !important;
        line-height: 1.25 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        color: var(--hcis-slate-700, #334155) !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }

    .visitor-workspace__stats :deep(.hcis-stat__value-row) {
        display: flex !important;
        align-items: baseline !important;
        justify-content: flex-start !important;
        margin-top: auto !important;
        width: 100% !important;
    }

    .visitor-workspace__stats :deep(.hcis-stat__value) {
        font-size: 1.2rem !important;
        font-weight: 850 !important;
        line-height: 1 !important;
    }

    .visitor-workspace__stats :deep(.hcis-stat__suffix) {
        display: inline !important;
        font-size: 0.65625rem !important;
        font-weight: 600 !important;
        color: var(--hcis-slate-500, #64748b) !important;
        margin-left: 0.25rem !important;
    }

    .visitor-workspace__panel {
        padding-top: 0.75rem;
    }

    /* Adaptive Segmented Native Tab Switcher on Mobile (Works for 2, 3, or many tabs) */
    .visitor-workspace :deep(.hcis-tabs-wrapper) {
        width: 100%;
        margin-bottom: 0.5rem;
        position: relative;
        touch-action: pan-x pan-y !important;
    }

    .visitor-workspace :deep(.hcis-tabs__track) {
        display: flex !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        scrollbar-width: none !important;
        -webkit-overflow-scrolling: touch !important;
        overscroll-behavior-x: contain !important;
        scroll-behavior: smooth !important;
        touch-action: pan-x pan-y !important;
        background: #f1f5f9 !important;
        border: 1.5px solid var(--hcis-slate-200, #e2e8f0) !important;
        border-radius: 12px !important;
        padding: 4px !important;
        width: 100% !important;
        box-sizing: border-box !important;
        gap: 4px !important;
    }

    .visitor-workspace :deep(.hcis-tabs__track::-webkit-scrollbar) {
        display: none !important;
    }

    .visitor-workspace :deep(.hcis-tabs) {
        display: flex !important;
        align-items: stretch !important;
        gap: 4px !important;
        width: 100% !important;
        min-width: 100% !important;
        touch-action: pan-x pan-y !important;
    }

    .visitor-workspace :deep(.hcis-tabs__tab) {
        position: relative !important;
        flex: 1 1 0px !important;
        min-width: max-content !important;
        display: inline-flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        padding: 0.45rem 0.75rem !important;
        min-height: 2.6rem !important;
        border-radius: 8px !important;
        font-size: 0.78125rem !important;
        font-weight: 700 !important;
        color: var(--hcis-slate-600, #475569) !important;
        gap: 0.4rem !important;
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
        white-space: nowrap !important;
        line-height: 1 !important;
        touch-action: pan-x pan-y !important;
        user-select: none !important;
        -webkit-user-select: none !important;
        -webkit-tap-highlight-color: transparent !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
    }

    .visitor-workspace :deep(.hcis-tabs__tab .hcis-tabs__icon),
    .visitor-workspace :deep(.hcis-tabs__tab .hcis-icon) {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 1.45rem !important;
        height: 1.45rem !important;
        min-width: 1.45rem !important;
        border-radius: 6px !important;
        font-size: 0.875rem !important;
        border: 1px solid var(--hcis-slate-200, #e2e8f0) !important;
        background: #ffffff !important;
        color: var(--hcis-slate-600, #475569) !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
        margin: 0 !important;
        flex-shrink: 0 !important;
        line-height: 1 !important;
    }

    .visitor-workspace :deep(.hcis-tabs__tab.is-active) {
        background: #ffffff !important;
        color: var(--hcis-brand-600, #4f46e5) !important;
        font-weight: 800 !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    }

    .visitor-workspace :deep(.hcis-tabs__tab.is-active .hcis-tabs__icon),
    .visitor-workspace :deep(.hcis-tabs__tab.is-active .hcis-icon) {
        background: var(--hcis-brand-600, #4f46e5) !important;
        color: #ffffff !important;
        border-color: var(--hcis-brand-600, #4f46e5) !important;
        box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35) !important;
    }

    .visitor-workspace :deep(.hcis-tabs__label-text) {
        display: inline-flex !important;
        align-items: center !important;
        line-height: 1 !important;
    }

    .visitor-workspace :deep(.hcis-tabs__indicator) {
        display: none !important;
    }

    .visitor-workspace :deep(.hcis-tabs__badge) {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 0.625rem !important;
        font-weight: 750 !important;
        padding: 0.1rem 0.4rem !important;
        border-radius: 9999px !important;
        margin-left: 0.2rem !important;
        flex-shrink: 0 !important;
        line-height: 1 !important;
    }
}

@media (max-width: 380px) {
    .visitor-workspace {
        gap: 0.5rem;
    }
    .visitor-workspace :deep(.hcis-header__inner) {
        padding: 0.65rem 0.75rem !important;
    }
    .visitor-workspace :deep(.hcis-header__title) {
        font-size: 1rem !important;
    }
    .visitor-workspace :deep(.hcis-header__subtitle) {
        font-size: 0.71875rem !important;
    }
    .visitor-workspace :deep(.hcis-header__icon-badge) {
        width: 2.15rem;
        height: 2.15rem;
    }
    .visitor-workspace__stats :deep(.hcis-stat-group) {
        gap: 0.45rem !important;
    }
    .visitor-workspace__stats :deep(.hcis-stat) {
        flex: 0 0 132px !important;
        min-width: 132px !important;
        max-width: 132px !important;
        padding: 0.55rem 0.65rem !important;
        min-height: 4.65rem !important;
    }
    .visitor-workspace__stats :deep(.hcis-stat__icon-box) {
        width: 1.5rem !important;
        height: 1.5rem !important;
        font-size: 0.75rem !important;
    }
    .visitor-workspace__stats :deep(.hcis-stat__value) {
        font-size: 1.15rem !important;
    }
    .visitor-workspace__stats :deep(.hcis-stat__label) {
        font-size: 0.65625rem !important;
    }
    .visitor-workspace__stats :deep(.hcis-stat__suffix) {
        font-size: 0.59375rem !important;
    }
    .visitor-workspace :deep(.hcis-tabs__tab) {
        padding: 0.35rem 0.45rem !important;
        min-height: 2.35rem !important;
        font-size: 0.6875rem !important;
        gap: 0.25rem !important;
    }
    .visitor-workspace :deep(.hcis-tabs__tab .hcis-tabs__icon),
    .visitor-workspace :deep(.hcis-tabs__tab .hcis-icon) {
        width: 1.25rem !important;
        height: 1.25rem !important;
        min-width: 1.25rem !important;
        font-size: 0.75rem !important;
        border-radius: 5px !important;
    }
    .visitor-workspace :deep(.hcis-card__body) {
        padding: 0.65rem !important;
    }
}
</style>
