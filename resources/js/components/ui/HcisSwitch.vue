<template>
    <div
        class="hcis-switch"
        :class="[
            `hcis-switch--${size}`,
            `hcis-switch--variant-${variant}`,
            {
                'is-checked': isChecked,
                'is-disabled': disabled || loading,
                'is-loading': loading,
                'hcis-switch--has-label': activeText || inactiveText,
                'hcis-switch--custom-track': activeColor || inactiveColor,
            },
            customClass,
        ]"
    >
        <!-- Inactive Label (Left Side) -->
        <span
            v-if="inactiveText && !inlinePrompt"
            class="hcis-switch__label hcis-switch__label--inactive"
            :class="{ 'is-active': !isChecked }"
            @click="handleLabelClick(false)"
        >
            {{ inactiveText }}
        </span>

        <!-- Switch Control Core Track -->
        <button
            type="button"
            role="switch"
            :aria-checked="isChecked"
            :aria-label="ariaLabel || activeText || inactiveText || 'Toggle switch'"
            :disabled="disabled || loading"
            class="hcis-switch__core"
            :style="trackStyle"
            @click="toggle"
            @keydown.space.prevent="toggle"
            @keydown.enter.prevent="toggle"
        >
            <!-- Ambient Ripple / Flash Aura Effect -->
            <span class="hcis-switch__aura" />

            <!-- Track Content Inside (When inlinePrompt is true) -->
            <span v-if="inlinePrompt" class="hcis-switch__inner">
                <transition name="hcis-switch-fade" mode="out-in">
                    <template v-if="isChecked">
                        <HcisIcon v-if="activeIcon" :key="'active-icon'" :name="activeIcon" size="xs" class="hcis-switch__icon" />
                        <span v-else-if="activeText" :key="'active-text'" class="hcis-switch__inner-text">{{ activeText }}</span>
                    </template>
                    <template v-else>
                        <HcisIcon v-if="inactiveIcon" :key="'inactive-icon'" :name="inactiveIcon" size="xs" class="hcis-switch__icon" />
                        <span v-else-if="inactiveText" :key="'inactive-text'" class="hcis-switch__inner-text">{{ inactiveText }}</span>
                    </template>
                </transition>
            </span>

            <!-- Thumb Handle -->
            <span class="hcis-switch__thumb" :style="thumbStyle">
                <!-- Loading Spinner -->
                <span v-if="loading" class="hcis-switch__spinner" />
                <template v-else-if="!inlinePrompt && currentThumbIcon">
                    <transition name="hcis-thumb-icon-pop" mode="out-in">
                        <HcisIcon
                            :key="currentThumbIcon"
                            :name="currentThumbIcon"
                            size="xs"
                            class="hcis-switch__thumb-icon"
                            :style="currentThumbIconColor ? { color: currentThumbIconColor } : {}"
                        />
                    </transition>
                </template>
            </span>
        </button>

        <!-- Active Label (Right Side) -->
        <span
            v-if="activeText && !inlinePrompt"
            class="hcis-switch__label hcis-switch__label--active"
            :class="{ 'is-active': isChecked }"
            @click="handleLabelClick(true)"
        >
            {{ activeText }}
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisSwitch' });

const props = defineProps({
    modelValue: {
        type: [Boolean, String, Number],
        default: false,
    },
    activeValue: {
        type: [Boolean, String, Number],
        default: true,
    },
    inactiveValue: {
        type: [Boolean, String, Number],
        default: false,
    },
    activeText: { type: String, default: '' },
    inactiveText: { type: String, default: '' },
    activeIcon: { type: String, default: '' },
    inactiveIcon: { type: String, default: '' },
    activeThumbIcon: { type: String, default: '' },
    inactiveThumbIcon: { type: String, default: '' },
    activeThumbIconColor: { type: String, default: '' },
    inactiveThumbIconColor: { type: String, default: '' },
    activeColor: { type: String, default: '' },
    inactiveColor: { type: String, default: '' },
    thumbColor: { type: String, default: '' },
    inlinePrompt: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    variant: {
        type: String,
        default: 'brand',
        validator: (v) => ['brand', 'success', 'warning', 'danger', 'info'].includes(v),
    },
    ariaLabel: { type: String, default: '' },
    customClass: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const isChecked = computed(() => props.modelValue === props.activeValue);

const currentThumbIcon = computed(() => {
    if (isChecked.value) {
        return props.activeThumbIcon || props.activeIcon || '';
    }
    return props.inactiveThumbIcon || props.inactiveIcon || '';
});

const currentThumbIconColor = computed(() => {
    if (isChecked.value) {
        return props.activeThumbIconColor || '';
    }
    return props.inactiveThumbIconColor || '';
});

const trackStyle = computed(() => {
    const style = {};
    if (isChecked.value && props.activeColor) {
        style.background = props.activeColor;
        style.borderColor = 'transparent';
        if (!props.activeColor.includes('gradient')) {
            style.boxShadow = `0 4px 14px -2px ${props.activeColor}66, inset 0 1px 1.5px rgba(255, 255, 255, 0.3)`;
        } else {
            style.boxShadow = `0 4px 14px -2px rgba(99, 102, 241, 0.35), inset 0 1px 1.5px rgba(255, 255, 255, 0.3)`;
        }
    } else if (!isChecked.value && props.inactiveColor) {
        style.background = props.inactiveColor;
        if (props.inactiveColor.includes('gradient')) {
            style.borderColor = 'transparent';
        }
    }
    return style;
});

const thumbStyle = computed(() => {
    const style = {};
    if (props.thumbColor) {
        style.backgroundColor = props.thumbColor;
    }
    return style;
});

function toggle() {
    if (props.disabled || props.loading) return;
    const newValue = isChecked.value ? props.inactiveValue : props.activeValue;
    emit('update:modelValue', newValue);
    emit('change', newValue);
}

function handleLabelClick(targetState) {
    if (props.disabled || props.loading) return;
    if (isChecked.value !== targetState) {
        toggle();
    }
}
</script>

<style scoped>
.hcis-switch {
    display: inline-flex;
    align-items: center;
    gap: var(--hcis-space-2, 0.5rem);
    font-family: var(--hcis-font-body, 'Inter', sans-serif);
    user-select: none;
    vertical-align: middle;
}

.hcis-switch__core {
    position: relative;
    display: inline-flex;
    align-items: center;
    padding: 0;
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-pill);
    background-color: var(--hcis-slate-200, var(--hcis-slate-200));
    box-shadow: inset 0 1.5px 3px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(15, 23, 42, 0.02);
    cursor: pointer;
    outline: none;
    transition: background-color 200ms ease,
                border-color 200ms ease,
                box-shadow 200ms ease,
                transform 150ms ease;
    will-change: background-color, border-color, box-shadow, transform;
}

.hcis-switch__core:hover:not(:disabled) {
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    transform: scale(1.02);
    filter: brightness(1.02);
}

.hcis-switch__core:active:not(:disabled) {
    transform: scale(0.97);
}

.hcis-switch__core:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px var(--hcis-focus-ring, rgba(99, 102, 241, 0.35)),
                0 4px 12px rgba(99, 102, 241, 0.2);
}

.hcis-switch__aura {
    position: absolute;
    inset: -3px;
    border-radius: var(--hcis-radius-pill);
    pointer-events: none;
    opacity: 0;
    transition: opacity 250ms ease, transform 250ms ease;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, rgba(99, 102, 241, 0) 70%);
}

.hcis-switch.is-checked .hcis-switch__core {
    background-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    box-shadow: inset 0 1px 1.5px rgba(255, 255, 255, 0.35),
                0 3px 10px -1px rgba(79, 70, 229, 0.35);
}

.hcis-switch.is-disabled .hcis-switch__core {
    border-color: var(--hcis-border-medium, var(--hcis-slate-300)) !important;
    background-color: rgba(241, 245, 249, 0.85) !important;
    cursor: not-allowed !important;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.05) !important;
}

.hcis-switch.is-disabled .hcis-switch__thumb {
    background: var(--hcis-slate-50) !important;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08) !important;
}

.hcis-switch.is-disabled .hcis-switch__label {
    color: var(--hcis-slate-500, var(--hcis-slate-500)) !important;
    cursor: not-allowed !important;
}

/* Variant Specific Active Color */
.hcis-switch--variant-success.is-checked .hcis-switch__core {
    background-color: var(--hcis-success-500, var(--hcis-success-500));
    border-color: var(--hcis-success-500, var(--hcis-success-500));
    box-shadow: inset 0 1px 1.5px rgb(var(--hcis-white-rgb) / 0.3),
                0 3px 10px -1px rgb(var(--hcis-success-500-rgb) / 0.38);
}

.hcis-switch--variant-warning.is-checked .hcis-switch__core {
    background-color: var(--hcis-warning-500, var(--hcis-warning-500));
    border-color: var(--hcis-warning-500, var(--hcis-warning-500));
    box-shadow: inset 0 1px 1.5px rgb(var(--hcis-white-rgb) / 0.3),
                0 3px 10px -1px rgb(var(--hcis-warning-500-rgb) / 0.38);
}

.hcis-switch--variant-danger.is-checked .hcis-switch__core {
    background-color: var(--hcis-danger-500, var(--hcis-danger-500));
    border-color: var(--hcis-danger-500, var(--hcis-danger-500));
    box-shadow: inset 0 1px 1.5px rgb(var(--hcis-white-rgb) / 0.3),
                0 3px 10px -1px rgb(var(--hcis-danger-500-rgb) / 0.38);
}

.hcis-switch--variant-info.is-checked .hcis-switch__core {
    background-color: var(--hcis-info-500, var(--hcis-info-500));
    border-color: var(--hcis-info-500, var(--hcis-info-500));
    box-shadow: inset 0 1px 1.5px rgb(var(--hcis-white-rgb) / 0.3),
                0 3px 10px -1px rgb(var(--hcis-info-500-rgb) / 0.38);
}

/* Sizing Dimensions */
/* MD (Default) */
.hcis-switch--md .hcis-switch__core {
    width: 2.75rem;
    height: 1.5rem;
}
.hcis-switch--md .hcis-switch__thumb {
    width: 1.25rem;
    height: 1.25rem;
}
.hcis-switch--md.is-checked .hcis-switch__thumb {
    transform: translateX(1.25rem);
}

/* SM */
.hcis-switch--sm .hcis-switch__core {
    width: 2.25rem;
    height: 1.25rem;
}
.hcis-switch--sm .hcis-switch__thumb {
    width: 1rem;
    height: 1rem;
}
.hcis-switch--sm.is-checked .hcis-switch__thumb {
    transform: translateX(1rem);
}

/* LG */
.hcis-switch--lg .hcis-switch__core {
    width: 3.375rem;
    height: 1.75rem;
}
.hcis-switch--lg .hcis-switch__thumb {
    width: 1.5rem;
    height: 1.5rem;
}
.hcis-switch--lg.is-checked .hcis-switch__thumb {
    transform: translateX(1.625rem);
}

/* Light & Crisp 3D Specular Handle Thumb */
.hcis-switch__thumb {
    position: absolute;
    top: 1px;
    left: 1px;
    border-radius: var(--hcis-radius-pill);
    background: linear-gradient(180deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
    box-shadow: 0 3px 8px -1px rgb(var(--hcis-slate-900-rgb) / 0.22),
                0 1px 3px rgb(var(--hcis-slate-900-rgb) / 0.12),
                inset 0 1px 0 var(--hcis-white),
                inset 0 -1px 0 rgb(var(--hcis-black-rgb) / 0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 280ms var(--hcis-ease-spring),
                width 200ms ease,
                background-color 200ms ease,
                box-shadow 200ms ease;
    will-change: transform, width;
}

/* Light Micro-Interaction Squeeze */
.hcis-switch__core:active:not(:disabled) .hcis-switch__thumb {
    width: 1.38rem;
}
.hcis-switch--sm .hcis-switch__core:active:not(:disabled) .hcis-switch__thumb {
    width: 1.12rem;
}
.hcis-switch--lg .hcis-switch__core:active:not(:disabled) .hcis-switch__thumb {
    width: 1.65rem;
}

.hcis-switch__thumb-icon {
    font-size: 0.75rem;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    transition: color 220ms ease, transform 220ms ease;
}

.hcis-switch--sm .hcis-switch__thumb-icon {
    font-size: 0.625rem;
}

.hcis-switch--lg .hcis-switch__thumb-icon {
    font-size: 0.875rem;
}

.hcis-switch.is-checked .hcis-switch__thumb-icon {
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

/* Thumb Icon Heavy Settling Pop Transition */
.hcis-thumb-icon-pop-enter-active {
    animation: hcis-icon-pop-in 300ms cubic-bezier(0.22, 1, 0.36, 1.2);
}
.hcis-thumb-icon-pop-leave-active {
    animation: hcis-icon-pop-out 180ms ease forwards;
}

@keyframes hcis-icon-pop-in {
    0% {
        opacity: 0;
        transform: scale(0.3) rotate(-25deg);
    }
    75% {
        opacity: 1;
        transform: scale(1.15) rotate(5deg);
    }
    100% {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
}

@keyframes hcis-icon-pop-out {
    0% {
        opacity: 1;
        transform: scale(1);
    }
    100% {
        opacity: 0;
        transform: scale(0.5);
    }
}

/* Track Inner Text / Icon (When inlinePrompt is true) */
.hcis-switch__inner {
    width: 100%;
    height: 100%;
    padding: 0 6px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    font-size: var(--hcis-font-size-2xs, 0.6875rem);
    font-weight: var(--hcis-weight-bold, 600);
    color: var(--hcis-white);
    pointer-events: none;
    overflow: hidden;
}

.hcis-switch.is-checked .hcis-switch__inner {
    justify-content: flex-start;
}

.hcis-switch__inner-text {
    line-height: 1;
    white-space: nowrap;
}

.hcis-switch-fade-enter-active,
.hcis-switch-fade-leave-active {
    transition: opacity 180ms ease, transform 180ms cubic-bezier(0.4, 0, 0.2, 1);
}
.hcis-switch-fade-enter-from {
    opacity: 0;
    transform: translateY(2px) scale(0.9);
}
.hcis-switch-fade-leave-to {
    opacity: 0;
    transform: translateY(-2px) scale(0.9);
}

.hcis-switch__icon {
    font-size: 0.75rem;
}

/* Spinner Loader */
.hcis-switch__spinner {
    width: 0.75rem;
    height: 0.75rem;
    border: 2px solid var(--hcis-slate-400, var(--hcis-slate-400));
    border-right-color: transparent;
    border-radius: var(--hcis-radius-pill);
    animation: hcis-spin 750ms linear infinite;
}

/* Outside Label Styling */
.hcis-switch__label {
    font-size: var(--hcis-font-size-sm, 0.875rem);
    font-weight: var(--hcis-weight-medium, 500);
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    cursor: pointer;
    transition: color 200ms ease, font-weight 200ms ease;
}

.hcis-switch__label.is-active {
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font-weight: var(--hcis-weight-bold, 600);
}

/* Disabled & Loading States */
.hcis-switch.is-disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.hcis-switch.is-disabled .hcis-switch__core,
.hcis-switch.is-disabled .hcis-switch__label {
    cursor: not-allowed;
}

@keyframes hcis-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

