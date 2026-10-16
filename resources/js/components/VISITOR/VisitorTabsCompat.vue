<template>
    <div
        class="visitor-tabs-compat"
        :class="{
            'visitor-tabs-compat--vertical': layout === 'sidebar',
            'is-collapsed': isCollapsed && layout === 'sidebar',
        }"
    >
        <HcisTabs
            :model-value="activeTab"
            :tabs="normalizedTabs"
            :orientation="layout === 'sidebar' ? 'vertical' : 'horizontal'"
            :aria-label="title"
            :collapsible="layout === 'sidebar'"
            :collapsed="isCollapsed"
            variant="segmented"
            @update:model-value="$emit('tab-change', $event)"
            @update:collapsed="isCollapsed = $event"
            @toggle-collapse="isCollapsed = $event"
        />

        <div class="visitor-tabs-compat__content" :style="contentStyle">
            <slot :name="activeTab" />
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { HcisTabs } from '@/components/ui';

defineOptions({ name: 'VisitorTabsCompat' });

const props = defineProps({
    tabs: { type: Array, required: true },
    activeTab: { type: String, required: true },
    layout: { type: String, default: 'horizontal' },
    title: { type: String, default: 'Navigasi' },
    storageKey: { type: String, default: null },
    contentPadding: { type: [String, Boolean], default: null },
    headerBgColor: { type: [String, Boolean], default: null },
});

defineEmits(['tab-change']);

const isCollapsed = ref(false);

if (props.storageKey && typeof window !== 'undefined') {
    try {
        const saved = localStorage.getItem(`visitor-tab-collapse-${props.storageKey}`);
        if (saved !== null) {
            isCollapsed.value = saved === 'true';
        }
    } catch (e) {
        // ignore
    }
}

watch(isCollapsed, (val) => {
    if (props.storageKey && typeof window !== 'undefined') {
        try {
            localStorage.setItem(`visitor-tab-collapse-${props.storageKey}`, String(val));
        } catch (e) {
            // ignore
        }
    }
});

const normalizedTabs = computed(() =>
    props.tabs.map((tab) => ({
        ...tab,
        key: tab.key || tab.id,
        badge: tab.badge ?? tab.count,
    })),
);

const contentStyle = computed(() => {
    if (props.contentPadding === true) return { padding: 'var(--hcis-space-6, 1.5rem)' };
    if (props.contentPadding === false) return { padding: 0 };
    if (typeof props.contentPadding === 'string') return { padding: props.contentPadding };
    return {};
});
</script>

<style scoped>
.visitor-tabs-compat {
    display: grid;
    gap: var(--hcis-space-5, 1.25rem);
    min-width: 0;
    width: 100%;
}

.visitor-tabs-compat--vertical {
    grid-template-columns: auto minmax(0, 1fr);
    align-items: start;
    gap: var(--hcis-space-6, 1.5rem);
}

.visitor-tabs-compat__content {
    min-width: 0;
    width: 100%;
}

@media (max-width: 768px) {
    .visitor-tabs-compat {
        gap: var(--hcis-space-3, 0.75rem);
    }

    .visitor-tabs-compat--vertical {
        grid-template-columns: minmax(0, 1fr);
        gap: var(--hcis-space-3, 0.75rem);
    }
}
</style>
