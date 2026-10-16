<template>
    <div
        class="hcis-collapse-item"
        :class="[
            {
                'hcis-collapse-item--active': isActive,
                'hcis-collapse-item--disabled': disabled,
            },
        ]"
    >
        <!-- Item Header Trigger -->
        <button
            type="button"
            class="hcis-collapse-item__header"
            :disabled="disabled"
            :aria-expanded="isActive"
            @click="onClick"
        >
            <span v-if="icon || $slots.icon" class="hcis-collapse-item__icon">
                <slot name="icon">
                    <HcisIcon :name="icon" size="md" />
                </slot>
            </span>

            <div class="hcis-collapse-item__header-content">
                <slot name="title">
                    <span class="hcis-collapse-item__title">{{ title }}</span>
                </slot>

                <span v-if="description" class="hcis-collapse-item__description">
                    {{ description }}
                </span>
            </div>

            <span v-if="badge || $slots.badge" class="hcis-collapse-item__badge">
                <slot name="badge">{{ badge }}</slot>
            </span>

            <span class="hcis-collapse-item__arrow">
                <HcisIcon name="chevron-down" size="sm" />
            </span>
        </button>

        <!-- Expandable Content Panel with Pure CSS Grid 0fr -> 1fr Animation -->
        <div
            class="hcis-collapse-item__wrap"
            role="tabpanel"
            :aria-hidden="!isActive"
        >
            <div class="hcis-collapse-item__content-inner">
                <div class="hcis-collapse-item__content">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, inject } from 'vue';
import HcisIcon from './HcisIcon.vue';

const props = defineProps({
    name: {
        type: [String, Number],
        required: true,
    },
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    icon: {
        type: String,
        default: '',
    },
    badge: {
        type: [String, Number],
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const collapseContext = inject('hcisCollapse', null);

const isActive = computed(() => {
    if (!collapseContext) return false;
    return collapseContext.activeNames.value.includes(props.name);
});

const onClick = () => {
    if (props.disabled) return;
    if (collapseContext) {
        collapseContext.handleItemClick(props.name);
    }
};
</script>

<style>
.hcis-collapse-item {
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

/* Card Variant Items */
.hcis-collapse--card .hcis-collapse-item {
    background-color: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-lg);
    overflow: hidden;
    box-shadow: 0 1px 3px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-collapse--card .hcis-collapse-item:hover:not(.hcis-collapse-item--disabled) {
    border-color: var(--hcis-brand-300, var(--hcis-brand-300));
    box-shadow: 0 4px 12px rgb(var(--hcis-slate-900-rgb) / 0.06);
}

.hcis-collapse--card .hcis-collapse-item--active {
    border-color: var(--hcis-brand-500, var(--hcis-brand-500)) !important;
    box-shadow: 0 4px 14px rgb(var(--hcis-brand-500-rgb) / 0.12) !important;
}

.hcis-collapse-item--disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

/* Header Button */
.hcis-collapse-item__header {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
    gap: 0.75rem;
}

.hcis-collapse-item__icon {
    font-size: 1.1rem;
    color: var(--hcis-brand-500, var(--hcis-brand-500));
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
}

.hcis-collapse-item__header-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.hcis-collapse-item__title {
    font-size: 0.925rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    transition: color 150ms;
}

.hcis-collapse-item--active .hcis-collapse-item__title {
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.hcis-collapse-item__description {
    font-size: 0.775rem;
    font-weight: 500;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.hcis-collapse-item__badge {
    padding: 2px 8px;
    border-radius: 9999px;
    font-size: 0.725rem;
    font-weight: 700;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
    border: 1px solid var(--hcis-brand-200, var(--hcis-brand-200));
}

/* Arrow Animation */
.hcis-collapse-item__arrow {
    font-size: 0.875rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 280ms var(--hcis-ease-spring);
    flex-shrink: 0;
}

.hcis-collapse-item--active .hcis-collapse-item__arrow {
    transform: rotate(180deg);
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

/* Content Wrap - Pure CSS Grid 0fr -> 1fr Smooth Expansion */
.hcis-collapse-item__wrap {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 280ms var(--hcis-ease),
                opacity 220ms var(--hcis-ease),
                visibility 280ms ease;
    opacity: 0;
    visibility: hidden;
}

.hcis-collapse-item--active .hcis-collapse-item__wrap {
    grid-template-rows: 1fr;
    opacity: 1;
    visibility: visible;
}

.hcis-collapse-item__content-inner {
    overflow: hidden;
    min-height: 0;
}

.hcis-collapse-item__content {
    padding: 0 1.25rem 1.25rem 1.25rem;
    font-size: 0.875rem;
    color: var(--hcis-text, var(--hcis-slate-700));
    line-height: 1.6;
    transform: translateY(-6px);
    transition: transform 280ms var(--hcis-ease);
}

.hcis-collapse-item--active .hcis-collapse-item__content {
    transform: translateY(0px);
}

.hcis-collapse--bordered .hcis-collapse-item__content {
    border-top: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
    padding-top: 1rem;
}
</style>
