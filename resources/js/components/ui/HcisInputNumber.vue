<template>
    <div
        class="hcis-input-number"
        :class="[
            `hcis-input-number--controls-${controlsPosition}`,
            `hcis-input-number--${size}`,
            {
                'hcis-input-number--disabled': disabled,
                'hcis-input-number--focused': isFocused,
                'hcis-input-number--at-min': isAtMin,
                'hcis-input-number--at-max': isAtMax,
            },
        ]"
    >
        <!-- Standard Left Decrement Button (default layout) -->
        <button
            v-if="controlsPosition === 'default' && controls"
            type="button"
            class="hcis-input-number__btn hcis-input-number__btn--decrease"
            :disabled="disabled || isAtMin"
            tabindex="-1"
            title="Kurangi nilai"
            @pointerdown="startHold(-1)"
            @pointerup="stopHold"
            @pointerleave="stopHold"
            @click="handleStepClick(-1)"
        >
            <HcisIcon name="dash" size="sm" />
        </button>

        <!-- Input Box Shell -->
        <div class="hcis-input-number__input-wrapper">
            <span v-if="prefix" class="hcis-input-number__prefix">{{ prefix }}</span>

            <input
                ref="inputRef"
                type="text"
                role="spinbutton"
                class="hcis-input-number__input"
                :class="`hcis-input-number__input--align-${align}`"
                :style="{ textAlign: align }"
                :value="displayValue"
                :disabled="disabled"
                :readonly="readonly"
                :placeholder="placeholder"
                :aria-valuemin="min"
                :aria-valuemax="max"
                :aria-valuenow="currentValue"
                @focus="handleFocus"
                @blur="handleBlur"
                @input="handleInput"
                @keydown="handleKeyDown"
                @wheel="handleWheel"
            />

            <span v-if="suffix" class="hcis-input-number__suffix">{{ suffix }}</span>
        </div>

        <!-- Standard Right Increment Button (default layout) -->
        <button
            v-if="controlsPosition === 'default' && controls"
            type="button"
            class="hcis-input-number__btn hcis-input-number__btn--increase"
            :disabled="disabled || isAtMax"
            tabindex="-1"
            title="Tambah nilai"
            @pointerdown="startHold(1)"
            @pointerup="stopHold"
            @pointerleave="stopHold"
            @click="handleStepClick(1)"
        >
            <HcisIcon name="plus" size="sm" />
        </button>

        <!-- Stacked Right Buttons (controls-position="right") -->
        <div v-if="controlsPosition === 'right' && controls" class="hcis-input-number__controls-right">
            <button
                type="button"
                class="hcis-input-number__btn-right hcis-input-number__btn-right--increase"
                :disabled="disabled || isAtMax"
                tabindex="-1"
                title="Tambah"
                @pointerdown="startHold(1)"
                @pointerup="stopHold"
                @pointerleave="stopHold"
                @click="handleStepClick(1)"
            >
                <HcisIcon name="chevron-up" size="xs" />
            </button>
            <button
                type="button"
                class="hcis-input-number__btn-right hcis-input-number__btn-right--decrease"
                :disabled="disabled || isAtMin"
                tabindex="-1"
                title="Kurangi"
                @pointerdown="startHold(-1)"
                @pointerup="stopHold"
                @pointerleave="stopHold"
                @click="handleStepClick(-1)"
            >
                <HcisIcon name="chevron-down" size="xs" />
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import HcisIcon from './HcisIcon.vue';

const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    min: { type: Number, default: -Infinity },
    max: { type: Number, default: Infinity },
    step: { type: Number, default: 1 },
    precision: { type: Number, default: undefined },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    controls: { type: Boolean, default: true },
    controlsPosition: { type: String, default: 'default' }, // 'default' | 'right' | 'none'
    size: { type: String, default: 'md' }, // 'sm' | 'md' | 'lg'
    placeholder: { type: String, default: '' },
    prefix: { type: String, default: '' },
    suffix: { type: String, default: '' },
    align: { type: String, default: 'center' }, // 'center' | 'left' | 'right'
});

const emit = defineEmits(['update:modelValue', 'change', 'input', 'focus', 'blur']);

const inputRef = ref(null);
const isFocused = ref(false);
const rawInput = ref('');
let holdTimer = null;
let holdInterval = null;

const currentValue = computed(() => {
    if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') {
        return null;
    }
    const num = Number(props.modelValue);
    return isNaN(num) ? null : num;
});

const isAtMin = computed(() => currentValue.value !== null && currentValue.value <= props.min);
const isAtMax = computed(() => currentValue.value !== null && currentValue.value >= props.max);

const formatNumber = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '';
    if (props.precision !== undefined && props.precision >= 0) {
        return val.toFixed(props.precision);
    }
    return String(val);
};

const displayValue = computed(() => {
    if (isFocused.value) {
        return rawInput.value;
    }
    return formatNumber(currentValue.value);
});

watch(
    () => props.modelValue,
    (val) => {
        if (!isFocused.value) {
            rawInput.value = formatNumber(val);
        }
    },
    { immediate: true }
);

const clampValue = (val) => {
    if (val === null || val === undefined || isNaN(val)) return null;
    let num = Number(val);
    if (props.precision !== undefined && props.precision >= 0) {
        const factor = Math.pow(10, props.precision);
        num = Math.round(num * factor) / factor;
    }
    if (num < props.min) num = props.min;
    if (num > props.max) num = props.max;
    return num;
};

const updateValue = (newVal) => {
    const clamped = clampValue(newVal);
    if (clamped !== props.modelValue) {
        emit('update:modelValue', clamped);
        emit('change', clamped);
    }
};

const stepBy = (dir) => {
    if (props.disabled || props.readonly) return;
    const base = currentValue.value !== null ? currentValue.value : 0;
    const next = base + dir * props.step;
    updateValue(next);
    rawInput.value = formatNumber(clampValue(next));
};

const handleStepClick = (dir) => {
    stepBy(dir);
};

// Long-press hold to repeat
const startHold = (dir) => {
    if (props.disabled || props.readonly) return;
    stopHold();
    holdTimer = setTimeout(() => {
        holdInterval = setInterval(() => {
            if ((dir > 0 && isAtMax.value) || (dir < 0 && isAtMin.value)) {
                stopHold();
                return;
            }
            stepBy(dir);
        }, 60);
    }, 300);
};

const stopHold = () => {
    if (holdTimer) clearTimeout(holdTimer);
    if (holdInterval) clearInterval(holdInterval);
    holdTimer = null;
    holdInterval = null;
};

const handleFocus = (e) => {
    isFocused.value = true;
    rawInput.value = currentValue.value !== null ? String(currentValue.value) : '';
    emit('focus', e);
};

const handleBlur = (e) => {
    isFocused.value = false;
    stopHold();
    if (rawInput.value === '' || rawInput.value === '-') {
        updateValue(null);
    } else {
        const parsed = parseFloat(rawInput.value);
        if (isNaN(parsed)) {
            updateValue(null);
        } else {
            updateValue(parsed);
        }
    }
    emit('blur', e);
};

const handleInput = (e) => {
    const val = e.target.value;
    rawInput.value = val;
    emit('input', val);
    if (val === '' || val === '-') {
        emit('update:modelValue', null);
        return;
    }
    const parsed = parseFloat(val);
    if (!isNaN(parsed)) {
        const clamped = clampValue(parsed);
        emit('update:modelValue', clamped);
    }
};

const handleKeyDown = (e) => {
    if (props.disabled || props.readonly) return;

    if (e.key === 'ArrowUp') {
        e.preventDefault();
        stepBy(1);
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        stepBy(-1);
    } else if (e.key === 'PageUp') {
        e.preventDefault();
        stepBy(10);
    } else if (e.key === 'PageDown') {
        e.preventDefault();
        stepBy(-10);
    } else if (e.key === 'Home' && props.min !== -Infinity) {
        e.preventDefault();
        updateValue(props.min);
    } else if (e.key === 'End' && props.max !== Infinity) {
        e.preventDefault();
        updateValue(props.max);
    }
};

const handleWheel = (e) => {
    if (!isFocused.value || props.disabled || props.readonly) return;
    e.preventDefault();
    if (e.deltaY < 0) {
        stepBy(1);
    } else if (e.deltaY > 0) {
        stepBy(-1);
    }
};

onUnmounted(() => {
    stopHold();
});
</script>

<style>
.hcis-input-number {
    position: relative;
    display: inline-flex;
    align-items: center;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    font-family: var(--hcis-font-body, inherit);
    background-color: var(--hcis-surface);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-md);
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
    box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.04), inset 0 1px 1px 0 rgba(15, 23, 42, 0.02);
    user-select: none;
}

.hcis-input-number:hover:not(.hcis-input-number--disabled) {
    border-color: var(--hcis-brand-400);
    box-shadow: 0 1px 3px 0 rgb(var(--hcis-brand-600-rgb) / 0.08), inset 0 1px 1.5px 0 rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-input-number--focused {
    border-color: var(--hcis-brand-600) !important;
    box-shadow: 0 0 0 3px var(--hcis-focus-ring), 0 1px 2px 0 rgb(var(--hcis-brand-600-rgb) / 0.1) !important;
    background-color: var(--hcis-surface) !important;
}

.hcis-input-number--disabled {
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300)) !important;
    background: rgba(241, 245, 249, 0.85) !important;
    backdrop-filter: blur(4px);
    cursor: not-allowed;
    box-shadow: inset 0 1px 2px 0 rgba(15, 23, 42, 0.05) !important;
}

.hcis-input-number--disabled .hcis-input-number__input {
    color: var(--hcis-slate-500, var(--hcis-slate-500)) !important;
    font-weight: 500 !important;
    cursor: not-allowed;
    -webkit-text-fill-color: var(--hcis-slate-500, var(--hcis-slate-500));
}

.hcis-input-number--disabled .hcis-input-number__prefix,
.hcis-input-number--disabled .hcis-input-number__suffix {
    color: var(--hcis-slate-400, var(--hcis-slate-400)) !important;
}

.hcis-input-number--disabled .hcis-input-number__btn {
    opacity: 0.35;
    cursor: not-allowed;
    pointer-events: none;
}

/* Sizes */
.hcis-input-number--sm {
    height: 2rem;
    font-size: 0.8125rem;
}

.hcis-input-number--sm .hcis-input-number__btn {
    width: 1.5rem;
    font-size: 0.875rem;
}

.hcis-input-number--sm .hcis-input-number__input-wrapper {
    padding: 0 0.25rem;
}

.hcis-input-number--md {
    height: 2.375rem;
    font-size: 0.875rem;
}

.hcis-input-number--md .hcis-input-number__btn {
    width: 1.875rem;
    font-size: 1rem;
}

.hcis-input-number--md .hcis-input-number__input-wrapper {
    padding: 0 0.375rem;
}

.hcis-input-number--lg {
    height: 2.75rem;
    font-size: 1rem;
}

.hcis-input-number--lg .hcis-input-number__btn {
    width: 2.25rem;
    font-size: 1.15rem;
}

.hcis-input-number--lg .hcis-input-number__input-wrapper {
    padding: 0 0.5rem;
}

/* Standard Stepper Buttons (Left/Right) */
.hcis-input-number__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.875rem;
    height: 100%;
    background: transparent;
    border: none;
    color: var(--hcis-text-strong, var(--hcis-slate-700));
    font-size: 1rem;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 120ms;
    transition-timing-function: var(--hcis-ease);
    flex-shrink: 0;
}

.hcis-input-number__btn--decrease {
    border-right: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-top-left-radius: 7px;
    border-bottom-left-radius: 7px;
}

.hcis-input-number__btn--increase {
    border-left: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-top-right-radius: 7px;
    border-bottom-right-radius: 7px;
}

.hcis-input-number__btn:hover:not(:disabled) {
    background-color: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.hcis-input-number__btn:active:not(:disabled) {
    background-color: var(--hcis-brand-100, var(--hcis-brand-100));
    transform: scale(0.95);
}

.hcis-input-number__btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
    color: var(--hcis-text-muted, var(--hcis-slate-400));
}

/* Input Wrapper & Text Field */
.hcis-input-number__input-wrapper {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.375rem;
    height: 100%;
    min-width: 0;
}

.hcis-input-number__prefix,
.hcis-input-number__suffix {
    font-size: 0.775rem;
    font-weight: 700;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    white-space: nowrap;
    user-select: none;
}

.hcis-input-number__prefix {
    margin-right: 0.375rem;
}

.hcis-input-number__suffix {
    margin-left: 0.375rem;
}

.hcis-input-number__input {
    flex: 1;
    width: 100%;
    height: 100%;
    border: none;
    outline: none;
    background: transparent;
    text-align: center;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font-size: inherit;
    font-weight: 700;
    letter-spacing: 0.02em;
    padding: 0;
    min-width: 0;
}

.hcis-input-number__input--align-center {
    text-align: center !important;
}

.hcis-input-number__input--align-left {
    text-align: left !important;
}

.hcis-input-number__input--align-right {
    text-align: right !important;
}

/* Controls Position = Right Layout */
.hcis-input-number--controls-right .hcis-input-number__input-wrapper {
    text-align: center;
    padding-left: 0.5rem;
}

.hcis-input-number--controls-right .hcis-input-number__input:not(.hcis-input-number__input--align-left):not(.hcis-input-number__input--align-right) {
    text-align: center;
}

.hcis-input-number__controls-right {
    display: flex;
    flex-direction: column;
    width: 1.75rem;
    height: 100%;
    border-left: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    flex-shrink: 0;
}

.hcis-input-number__btn-right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    font-size: 0.7rem;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 120ms;
}

.hcis-input-number__btn-right--increase {
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
    border-top-right-radius: 7px;
}

.hcis-input-number__btn-right--decrease {
    border-bottom-right-radius: 7px;
}

.hcis-input-number__btn-right:hover:not(:disabled) {
    background-color: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.hcis-input-number__btn-right:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
</style>
