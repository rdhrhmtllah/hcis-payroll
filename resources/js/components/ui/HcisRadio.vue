<template>
    <div
        class="hcis-radio-group"
        :class="[`hcis-radio-group--${direction}`, `hcis-radio-group--${size}`, `hcis-radio-group--variant-${variant}`]"
        role="radiogroup"
    >
        <!-- Mode Array Options -->
        <template v-if="parsedOptions.length">
            <label
                v-for="opsi in parsedOptions"
                :key="opsi.value"
                class="hcis-choice hcis-choice--radio"
                :class="[
                    `hcis-choice--${size}`,
                    `hcis-choice--variant-${variant}`,
                    opsi.color || color ? `hcis-choice--color-${opsi.color || color}` : null,
                    {
                        'is-disabled': disabled || opsi.disabled,
                        'is-checked': opsi.value === modelValue,
                    },
                ]"
            >
                <span class="hcis-choice__box-wrap">
                    <input
                        type="radio"
                        class="hcis-choice__input"
                        :name="name"
                        :value="opsi.value"
                        :checked="opsi.value === modelValue"
                        :disabled="disabled || opsi.disabled"
                        v-bind="$attrs"
                        @change="$emit('update:modelValue', opsi.value)"
                    />
                    <span class="hcis-choice__radio-circle" aria-hidden="true">
                        <span class="hcis-choice__radio-dot" />
                    </span>
                </span>

                <span class="hcis-choice__body">
                    <span v-if="opsi.icon" class="hcis-choice__visual" aria-hidden="true">
                        <HcisIcon :name="opsi.icon" :color="opsi.iconColor || opsi.color || color" size="sm" />
                    </span>
                    <span class="hcis-choice__label">
                        <slot name="label" :option="opsi">{{ opsi.label }}</slot>
                    </span>
                    <span v-if="opsi.hint || opsi.description" class="hcis-choice__hint">
                        <slot name="description" :option="opsi">{{ opsi.hint || opsi.description }}</slot>
                    </span>
                </span>
            </label>
        </template>

        <!-- Standalone Radio Mode -->
        <template v-else>
            <label
                class="hcis-choice hcis-choice--radio"
                :class="[
                    `hcis-choice--${size}`,
                    `hcis-choice--variant-${variant}`,
                    color ? `hcis-choice--color-${color}` : null,
                    {
                        'is-disabled': disabled,
                        'is-checked': value === modelValue,
                    },
                ]"
            >
                <span class="hcis-choice__box-wrap">
                    <input
                        type="radio"
                        class="hcis-choice__input"
                        :name="name"
                        :value="value"
                        :checked="value === modelValue"
                        :disabled="disabled"
                        v-bind="$attrs"
                        @change="$emit('update:modelValue', value)"
                    />
                    <span class="hcis-choice__radio-circle" aria-hidden="true">
                        <span class="hcis-choice__radio-dot" />
                    </span>
                </span>

                <span v-if="label || $slots.default || description || $slots.description" class="hcis-choice__body">
                    <span v-if="label || $slots.default" class="hcis-choice__label">
                        <slot>{{ label }}</slot>
                    </span>
                    <span v-if="description || $slots.description" class="hcis-choice__hint">
                        <slot name="description">{{ description }}</slot>
                    </span>
                </span>
            </label>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisRadio', inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number, Boolean], default: '' },
    options: { type: Array, default: () => [] },
    value: { type: [String, Number, Boolean], default: '' },
    label: { type: String, default: '' },
    description: { type: String, default: '' },
    name: { type: String, default: 'hcis-radio' },
    disabled: { type: Boolean, default: false },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    variant: {
        type: String,
        default: 'plain',
        validator: (v) => ['plain', 'card', 'button'].includes(v),
    },
    color: {
        type: String,
        default: 'brand',
        validator: (v) => ['brand', 'success', 'warning', 'danger'].includes(v),
    },
    direction: {
        type: String,
        default: 'vertical',
        validator: (v) => ['vertical', 'horizontal'].includes(v),
    },
});

defineEmits(['update:modelValue']);

const parsedOptions = computed(() => {
    if (!props.options || !props.options.length) return [];
    return props.options.map((opt) => {
        if (typeof opt === 'object' && opt !== null) {
            return opt;
        }
        return { value: opt, label: String(opt) };
    });
});
</script>

<style>
.hcis-radio-group {
    display: flex;
    gap: 0.75rem;
    width: 100%;
}

.hcis-radio-group--vertical {
    flex-direction: column;
}

.hcis-radio-group--horizontal {
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
}

.hcis-choice--radio.is-checked .hcis-choice__radio-dot {
    transform: scale(1) !important;
}

.hcis-choice__visual {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    margin-bottom: 0.35rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
}

.hcis-choice--variant-card .hcis-choice__body {
    align-items: flex-start;
}
</style>
