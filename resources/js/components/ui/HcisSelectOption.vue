<template>
    <div
        v-show="isVisible"
        class="hcis-select-option"
        :class="{
            'is-selected': isSelected,
            'is-disabled': disabled,
            'has-description': Boolean(description),
        }"
        :title="resolvedLabel !== undefined ? String(resolvedLabel) : String(value)"
        role="option"
        :tabindex="disabled ? -1 : 0"
        :aria-selected="isSelected"
        :aria-disabled="disabled || undefined"
        @click="handleClick"
        @keydown.enter.prevent="handleClick"
        @keydown.space.prevent="handleClick"
    >
        <div class="hcis-select-option__content">
            <span v-if="icon" class="hcis-select-option__icon">
                <HcisIcon :name="icon" :color="iconColor" size="xs" />
            </span>
            <div class="hcis-select-option__text">
                <span class="hcis-select-option__label">
                    <slot>{{ resolvedLabel }}</slot>
                </span>
                <span v-if="description" class="hcis-select-option__desc">
                    {{ description }}
                </span>
            </div>
        </div>

        <div class="hcis-select-option__meta">
            <span v-if="badge" class="hcis-select-option__badge" :class="`is-${badgeVariant}`">
                {{ badge }}
            </span>
            <span v-if="isSelected" class="hcis-select-option__check">
                <HcisIcon name="check2" size="xs" />
            </span>
        </div>
    </div>
</template>

<script setup>
import { computed, inject, onMounted, onUnmounted, useSlots, watch } from 'vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisSelectOption' });

const props = defineProps({
    value: { type: [String, Number, Boolean, Object], required: true },
    label: { type: [String, Number], default: undefined },
    description: { type: String, default: '' },
    icon: { type: String, default: '' },
    iconColor: { type: String, default: '' },
    badge: { type: [String, Number], default: '' },
    badgeVariant: { type: String, default: 'neutral' },
    disabled: { type: Boolean, default: false },
});

const slots = useSlots();
const select = inject('hcisSelect', null);

function getSlotText() {
    if (!slots.default) return '';
    try {
        const vnodes = slots.default();
        return vnodes
            .map((vn) => (typeof vn.children === 'string' ? vn.children : ''))
            .join('')
            .trim();
    } catch {
        return '';
    }
}

const resolvedLabel = computed(() => {
    if (props.label !== undefined && props.label !== null && props.label !== '') {
        return props.label;
    }
    const slotText = getSlotText();
    if (slotText) return slotText;
    return props.value !== undefined && props.value !== null ? props.value : '';
});

const isSelected = computed(() => {
    if (!select || select.modelValue.value === undefined || select.modelValue.value === null || select.modelValue.value === '') return false;
    if (select.props.multiple && Array.isArray(select.modelValue.value)) {
        return select.modelValue.value.some((v) => String(v) === String(props.value));
    }
    return String(select.modelValue.value) === String(props.value);
});

const isVisible = computed(() => {
    if (!select || !select.searchQuery || !select.searchQuery.value) return true;
    const q = select.searchQuery.value.toLowerCase().trim();
    if (!q) return true;
    const labelText = String(resolvedLabel.value).toLowerCase();
    const valText = String(props.value !== undefined && props.value !== null ? props.value : '').toLowerCase();
    const descText = String(props.description || '').toLowerCase();
    return labelText.includes(q) || valText.includes(q) || descText.includes(q);
});

function handleClick() {
    if (props.disabled) return;
    if (select) {
        select.selectOption({
            value: props.value,
            label: resolvedLabel.value,
            description: props.description,
            icon: props.icon,
            iconColor: props.iconColor,
            badge: props.badge,
            badgeVariant: props.badgeVariant,
        });
    }
}

function syncRegistration() {
    if (select && select.registerOption) {
        select.registerOption({
            value: props.value,
            label: resolvedLabel.value,
            description: props.description,
            icon: props.icon,
            iconColor: props.iconColor,
            badge: props.badge,
            badgeVariant: props.badgeVariant,
        });
    }
}

onMounted(() => {
    syncRegistration();
});

watch(
    () => [props.value, props.label, props.description, props.icon, props.iconColor, props.badge, props.badgeVariant],
    () => {
        syncRegistration();
    },
    { deep: true },
);

onUnmounted(() => {
    if (select && select.unregisterOption) {
        select.unregisterOption(props.value);
    }
});
</script>

<style scoped>
.hcis-select-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
    font-weight: 550;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    border-radius: var(--hcis-radius-md, 8px);
    cursor: pointer;
    user-select: none;
    transition-property: var(--hcis-transition-props), font-weight;
    transition-duration: 140ms;
    transition-timing-function: ease;
    gap: 0.75rem;
    margin: 1px 0;
}

.hcis-select-option:hover:not(.is-disabled) {
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
}

.hcis-select-option.is-selected {
    background: rgba(238, 242, 255, 0.95);
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    font-weight: 700;
}

.hcis-select-option.is-disabled {
    opacity: 0.45;
    cursor: not-allowed;
    background: transparent !important;
}

.hcis-select-option__content {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    min-width: 0;
    flex: 1;
}

.hcis-select-option__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    flex-shrink: 0;
}

.hcis-select-option.is-selected .hcis-select-option__icon {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.hcis-select-option__text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.hcis-select-option__label {
    white-space: normal;
    line-height: 1.35;
    word-break: break-word;
}

.hcis-select-option__desc {
    font-size: 0.725rem;
    font-weight: 450;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    white-space: normal;
    line-height: 1.3;
    word-break: break-word;
    margin-top: 2px;
}

.hcis-select-option.is-selected .hcis-select-option__desc {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.hcis-select-option__meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.hcis-select-option__badge {
    display: inline-flex;
    align-items: center;
    padding: 0.1rem 0.4rem;
    font-size: 0.675rem;
    font-weight: 700;
    border-radius: 999px;
    letter-spacing: 0.02em;
}

.hcis-select-option__badge.is-neutral {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}
.hcis-select-option__badge.is-primary {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-800);
}
.hcis-select-option__badge.is-success {
    background: var(--hcis-green-100);
    color: #166534;
}
.hcis-select-option__badge.is-warning {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
}
.hcis-select-option__badge.is-danger {
    background: var(--hcis-red-100);
    color: var(--hcis-red-800);
}

.hcis-select-option__check {
    display: flex;
    align-items: center;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}
</style>
