<template>
    <div
        class="hcis-field"
        :class="[
            `hcis-field--size-${size}`,
            `hcis-field--variant-${variant}`,
            {
                'hcis-field--error': !!error,
                'hcis-field--success': !!success,
                'hcis-field--disabled': disabled,
                'hcis-field--focused': isFocused,
                'hcis-field--has-leading': leadingIcon || $slots.leading || prefix || $slots.prefix,
                'hcis-field--has-prefix': !!prefix || !!$slots.prefix,
                'hcis-field--has-trailing': trailingIcon || $slots.trailing || suffix || $slots.suffix || (clearable && modelValue) || showPasswordToggle,
                'hcis-field--has-prepend': prepend || $slots.prepend,
                'hcis-field--has-append': append || $slots.append,
                'hcis-field--textarea': type === 'textarea',
            },
        ]"
    >
        <!-- Header Label Row -->
        <div v-if="label || $slots.label || $slots.corner" class="hcis-field__label-row">
            <label v-if="label || $slots.label" class="hcis-field__label" :for="id">
                <slot name="label">{{ label }}</slot>
                <span v-if="required" class="hcis-field__required" aria-hidden="true">*</span>
            </label>
            <slot name="corner" />
        </div>

        <!-- Input Group Shell (Supports Segmented Prepend / Append Addons) -->
        <div
            class="hcis-field__group"
            :class="{
                'hcis-field__group--has-prepend': prepend || $slots.prepend,
                'hcis-field__group--has-append': append || $slots.append,
            }"
        >
            <!-- Prepend Segment (Static text, Select, or Icon) -->
            <div v-if="prepend || $slots.prepend" class="hcis-field__addon hcis-field__addon--prepend">
                <slot name="prepend">{{ prepend }}</slot>
            </div>

            <!-- Custom Default Slot Wrapper (if provided) -->
            <div v-if="$slots.default" class="hcis-field__custom-wrap">
                <slot />
            </div>

            <!-- Standard Input Wrapper Control Shell -->
            <div v-else class="hcis-field__control-wrap">
                <!-- Leading Icon / Prefix (Positioned Absolute Left) -->
                <div
                    v-if="leadingIcon || $slots.leading || prefix || $slots.prefix"
                    class="hcis-field__icon hcis-field__icon--leading"
                >
                    <span v-if="prefix || $slots.prefix" class="hcis-field__prefix">
                        <slot name="prefix">{{ prefix }}</slot>
                    </span>
                    <slot v-else name="leading">
                        <HcisIcon :name="leadingIcon" size="sm" />
                    </slot>
                </div>

                <!-- Textarea Control -->
                <textarea
                    v-if="type === 'textarea'"
                    :id="id"
                    class="hcis-field__control hcis-field__control--textarea"
                    :value="modelValue"
                    :placeholder="placeholder"
                    :disabled="disabled"
                    :readonly="readonly"
                    :rows="rows"
                    :maxlength="maxLength"
                    v-bind="$attrs"
                    @input="onInput"
                    @focus="handleFocus"
                    @blur="handleBlur"
                />

                <!-- Standard Input Control -->
                <input
                    v-else
                    :id="id"
                    class="hcis-field__control"
                    :value="modelValue"
                    :type="computedType"
                    :placeholder="placeholder"
                    :disabled="disabled"
                    :readonly="readonly"
                    :maxlength="maxLength"
                    v-bind="$attrs"
                    @keydown="onKeydown"
                    @paste="onPaste"
                    @input="onInput"
                    @focus="handleFocus"
                    @blur="handleBlur"
                />

                <!-- Trailing Controls (Positioned Absolute Right) -->
                <div
                    v-if="trailingIcon || $slots.trailing || suffix || $slots.suffix || (clearable && modelValue) || showPasswordToggle"
                    class="hcis-field__icon hcis-field__icon--trailing"
                >
                    <span v-if="suffix || $slots.suffix" class="hcis-field__suffix">
                        <slot name="suffix">{{ suffix }}</slot>
                    </span>

                    <button
                        v-else-if="clearable && modelValue && !disabled"
                        type="button"
                        class="hcis-field__clear-btn"
                        aria-label="Bersihkan input"
                        @click="clearInput"
                    >
                        <HcisIcon name="x-circle-fill" size="xs" />
                    </button>

                    <button
                        v-else-if="showPasswordToggle && !disabled"
                        type="button"
                        class="hcis-field__toggle-btn"
                        :aria-label="isPasswordVisible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        @click="isPasswordVisible = !isPasswordVisible"
                    >
                        <HcisIcon :name="isPasswordVisible ? 'eye-slash' : 'eye'" size="sm" />
                    </button>

                    <slot v-else name="trailing">
                        <HcisIcon :name="trailingIcon" size="sm" />
                    </slot>
                </div>
            </div>

            <!-- Append Segment (Static text, Select, or Action Button) -->
            <div v-if="append || $slots.append" class="hcis-field__addon hcis-field__addon--append">
                <slot name="append">{{ append }}</slot>
            </div>
        </div>

        <!-- Helper Row (Error / Success / Hint & Character Count) -->
        <div v-if="error || success || hint || (showCount && maxLength)" class="hcis-field__helper-row">
            <p v-if="error" class="hcis-field__error" role="alert">
                <HcisIcon name="exclamation-circle-fill" color="danger" size="xs" class="me-1" />
                <span>{{ error }}</span>
            </p>
            <p v-else-if="success" class="hcis-field__success">
                <HcisIcon name="check-circle-fill" color="success" size="xs" class="me-1" />
                <span>{{ success }}</span>
            </p>
            <p v-else-if="hint" class="hcis-field__hint">{{ hint }}</p>

            <span v-if="showCount && maxLength" class="hcis-field__count">
                {{ String(modelValue || '').length }}/{{ maxLength }}
            </span>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisField', inheritAttrs: false });

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, default: '' },
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    success: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false },
    showPasswordToggle: { type: Boolean, default: false },
    leadingIcon: { type: String, default: '' },
    trailingIcon: { type: String, default: '' },
    prefix: { type: String, default: '' },
    suffix: { type: String, default: '' },
    prepend: { type: String, default: '' },
    append: { type: String, default: '' },
    rows: { type: [Number, String], default: 3 },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    variant: {
        type: String,
        default: 'outline',
        validator: (v) => ['outline', 'filled', 'flushed'].includes(v),
    },
    maxLength: { type: [Number, String], default: null },
    showCount: { type: Boolean, default: false },
    numericOnly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'clear', 'focus', 'blur']);

const isFocused = ref(false);
const isPasswordVisible = ref(false);

const computedType = computed(() => {
    if (props.showPasswordToggle) {
        return isPasswordVisible.value ? 'text' : 'password';
    }
    return props.type;
});

function onKeydown(e) {
    if (!props.numericOnly) return;

    // Allow control and navigation keys
    const allowedKeys = [
        'Backspace', 'Tab', 'Enter', 'Escape', 'Delete',
        'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
        'Home', 'End',
    ];

    if (allowedKeys.includes(e.key)) return;

    // Allow keyboard shortcuts (Ctrl/Cmd + A, C, V, X, Z, etc.)
    if (e.ctrlKey || e.metaKey) return;

    // Prevent non-digit keys
    if (!/^[0-9]$/.test(e.key)) {
        e.preventDefault();
    }
}

function onPaste(e) {
    if (!props.numericOnly) return;

    const pastedText = e.clipboardData?.getData('text') ?? '';
    if (/\D/.test(pastedText)) {
        e.preventDefault();
        const digitsOnly = pastedText.replace(/\D/g, '');
        const target = e.target;
        const start = target.selectionStart ?? target.value.length;
        const end = target.selectionEnd ?? target.value.length;
        const currentValue = target.value;
        let nextValue = currentValue.slice(0, start) + digitsOnly + currentValue.slice(end);
        if (props.maxLength) {
            nextValue = nextValue.slice(0, Number(props.maxLength));
        }
        target.value = nextValue;
        emit('update:modelValue', nextValue);
    }
}

function onInput(e) {
    let val = e.target.value;
    if (props.numericOnly) {
        val = val.replace(/\D/g, '');
        if (props.maxLength) {
            val = val.slice(0, Number(props.maxLength));
        }
        if (e.target.value !== val) {
            e.target.value = val;
        }
    }
    emit('update:modelValue', val);
}

function clearInput() {
    emit('update:modelValue', '');
    emit('clear');
}

function handleFocus(e) {
    isFocused.value = true;
    emit('focus', e);
}

function handleBlur(e) {
    isFocused.value = false;
    emit('blur', e);
}
</script>

<style>
/* Synchronized with design-system.css to prevent any layout collisions */
.hcis-field {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    font-family: var(--hcis-font-body, inherit);
}

.hcis-field__label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.hcis-field__label {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

.hcis-field__required {
    color: var(--hcis-danger-500);
    font-weight: 800;
}

/* Group Shell (Flex container for Prepend / Input / Append) */
.hcis-field__group {
    display: flex;
    align-items: stretch;
    width: 100%;
    position: relative;
}

.hcis-field__custom-wrap {
    flex: 1;
    min-width: 0;
    width: 100%;
}

.hcis-field__control-wrap {
    position: relative;
    display: block;
    flex: 1;
    min-width: 0;
    width: 100%;
    border-radius: var(--hcis-radius-md, 9px);
    background-color: var(--hcis-surface, var(--hcis-white));
}

/* Addon Segments (Prepend & Append) */
.hcis-field__addon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.875rem;
    background: linear-gradient(180deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    font-size: 0.8125rem;
    font-weight: 650;
    white-space: nowrap;
    user-select: none;
    z-index: 1;
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: ease;
    box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.04);
}

.hcis-field__addon--prepend {
    border-right: none;
    border-top-left-radius: var(--hcis-radius-md, 9px);
    border-bottom-left-radius: var(--hcis-radius-md, 9px);
}

.hcis-field__addon--append {
    border-left: none;
    border-top-right-radius: var(--hcis-radius-md, 9px);
    border-bottom-right-radius: var(--hcis-radius-md, 9px);
}

/* Corner radius adjustments when addons exist */
.hcis-field__group--has-prepend .hcis-field__control-wrap,
.hcis-field__group--has-prepend .hcis-field__control,
.hcis-field__group--has-prepend .hcis-select__trigger,
.hcis-field__group--has-prepend .hcis-input-number {
    border-top-left-radius: 0 !important;
    border-bottom-left-radius: 0 !important;
}

.hcis-field__group--has-append .hcis-field__control-wrap,
.hcis-field__group--has-append .hcis-field__control,
.hcis-field__group--has-append .hcis-select__trigger,
.hcis-field__group--has-append .hcis-input-number {
    border-top-right-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
}

/* Sizes for Addons */
.hcis-field--size-sm .hcis-field__addon {
    padding: 0 0.65rem;
    font-size: 0.75rem;
    border-radius: 8px;
}
.hcis-field--size-sm .hcis-field__addon--prepend {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}
.hcis-field--size-sm .hcis-field__addon--append {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.hcis-field--size-lg .hcis-field__addon {
    padding: 0 1.1rem;
    font-size: 0.9rem;
    border-radius: 11px;
}
.hcis-field--size-lg .hcis-field__addon--prepend {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}
.hcis-field--size-lg .hcis-field__addon--append {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

/* Integrated HcisButton & HcisDropdown inside Addons */
.hcis-field__addon:has(.hcis-btn),
.hcis-field__addon:has(.hcis-dropdown) {
    padding: 0 !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

.hcis-field__addon--prepend .hcis-btn,
.hcis-field__addon--prepend .hcis-dropdown__trigger .hcis-btn {
    border-top-right-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
    border-top-left-radius: var(--hcis-radius-md, 9px) !important;
    border-bottom-left-radius: var(--hcis-radius-md, 9px) !important;
    height: 100% !important;
    white-space: nowrap;
}

.hcis-field__addon--append .hcis-btn,
.hcis-field__addon--append .hcis-dropdown__trigger .hcis-btn {
    border-top-left-radius: 0 !important;
    border-bottom-left-radius: 0 !important;
    border-top-right-radius: var(--hcis-radius-md, 9px) !important;
    border-bottom-right-radius: var(--hcis-radius-md, 9px) !important;
    height: 100% !important;
    white-space: nowrap;
}

.hcis-field--size-sm .hcis-field__addon .hcis-btn {
    height: 2rem !important;
    min-height: 2rem !important;
    padding: 0 0.75rem !important;
    font-size: 0.75rem !important;
}

.hcis-field--size-md .hcis-field__addon .hcis-btn {
    height: 2.375rem !important;
    min-height: 2.375rem !important;
    padding: 0 0.95rem !important;
    font-size: 0.8125rem !important;
}

.hcis-field--size-lg .hcis-field__addon .hcis-btn {
    height: 2.75rem !important;
    min-height: 2.75rem !important;
    padding: 0 1.25rem !important;
    font-size: 0.875rem !important;
}

/* Control Element */
.hcis-field__control {
    width: 100%;
    height: 2.375rem;
    padding: 0 0.75rem;
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-md);
    background-color: var(--hcis-surface);
    color: var(--hcis-text-strong);
    font-size: 0.875rem;
    font-weight: 600;
    font-family: inherit;
    transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
    box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.04), inset 0 1px 1px 0 rgba(15, 23, 42, 0.02);
}

.hcis-field__control::placeholder {
    color: var(--hcis-text-muted);
    font-weight: 500;
}

.hcis-field__control:hover:not(:disabled) {
    border-color: var(--hcis-brand-400);
    box-shadow: 0 1px 3px 0 rgb(var(--hcis-brand-600-rgb) / 0.08), inset 0 1px 1.5px 0 rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-field__control:focus {
    border-color: var(--hcis-brand-600) !important;
    box-shadow: 0 0 0 3px var(--hcis-focus-ring), 0 1px 2px 0 rgb(var(--hcis-brand-600-rgb) / 0.1) !important;
    background-color: var(--hcis-surface) !important;
    outline: none !important;
}

/* Variant: Filled (Solid Filled Background with Smooth Focus Elevation) */
.hcis-field--variant-filled .hcis-field__control {
    background-color: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border);
    box-shadow: inset 0 1px 2px 0 rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.hcis-field--variant-filled .hcis-field__control:hover:not(:disabled) {
    background-color: var(--hcis-slate-200);
    border-color: var(--hcis-brand-400);
}

.hcis-field--variant-filled .hcis-field__control:focus {
    background-color: var(--hcis-surface) !important;
    border-color: var(--hcis-brand-600) !important;
    box-shadow: 0 0 0 3px var(--hcis-focus-ring), 0 1px 2px 0 rgb(var(--hcis-brand-600-rgb) / 0.1) !important;
}

/* Variant: Flushed (Clean Underline Only, No Container Box) */
.hcis-field--variant-flushed .hcis-field__control-wrap {
    background-color: transparent;
    border-radius: 0;
    box-shadow: none;
}

.hcis-field--variant-flushed .hcis-field__control {
    background-color: transparent;
    border-top: none;
    border-left: none;
    border-right: none;
    border-bottom: 2px solid var(--hcis-border-strong);
    border-radius: 0;
    padding-left: 0;
    padding-right: 0;
    box-shadow: none;
}

.hcis-field--variant-flushed.hcis-field--has-leading .hcis-field__control {
    padding-left: 1.75rem;
}

.hcis-field--variant-flushed.hcis-field--has-trailing .hcis-field__control {
    padding-right: 1.75rem;
}

.hcis-field--variant-flushed .hcis-field__icon--leading {
    left: 0;
}

.hcis-field--variant-flushed .hcis-field__icon--trailing {
    right: 0;
}

.hcis-field--variant-flushed .hcis-field__control:hover:not(:disabled) {
    border-bottom-color: var(--hcis-brand-400);
}

.hcis-field--variant-flushed .hcis-field__control:focus {
    border-bottom-color: var(--hcis-brand-600) !important;
    box-shadow: none !important;
}

.hcis-field--size-sm .hcis-field__control {
    height: 2rem;
    font-size: 0.8125rem;
    padding: 0 0.625rem;
}

.hcis-field--size-lg .hcis-field__control {
    height: 2.75rem;
    font-size: 1rem;
    padding: 0 1rem;
}

.hcis-field__control--textarea {
    height: auto;
    padding: 0.625rem 0.75rem;
    resize: vertical;
    min-height: 4.5rem;
    border-radius: var(--hcis-radius-md);
}

/* Icons Padding Alignment */
.hcis-field--has-leading .hcis-field__control {
    padding-left: 2.5rem !important;
}

.hcis-field--size-sm.hcis-field--has-leading .hcis-field__control {
    padding-left: 2.125rem !important;
}

.hcis-field--size-lg.hcis-field--has-leading .hcis-field__control {
    padding-left: 2.875rem !important;
}

.hcis-field--has-prefix .hcis-field__control {
    padding-left: 3.125rem !important;
}

.hcis-field--size-sm.hcis-field--has-prefix .hcis-field__control {
    padding-left: 2.75rem !important;
}

.hcis-field--size-lg.hcis-field--has-prefix .hcis-field__control {
    padding-left: 3.5rem !important;
}

.hcis-field--has-trailing .hcis-field__control {
    padding-right: 2.5rem !important;
}

.hcis-field--size-sm.hcis-field--has-trailing .hcis-field__control {
    padding-right: 2.125rem !important;
}

.hcis-field--size-lg.hcis-field--has-trailing .hcis-field__control {
    padding-right: 2.875rem !important;
}

/* Position Absolute Icons Overlay */
.hcis-field__icon {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    z-index: 2;
    line-height: 1;
}

.hcis-field__icon--leading {
    left: 0.75rem;
    pointer-events: none;
}

.hcis-field__icon--trailing {
    right: 0.75rem;
    pointer-events: auto;
}

.hcis-field__prefix {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    user-select: none;
    display: inline-flex;
    align-items: center;
    padding-right: 0.35rem;
    border-right: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    line-height: 1;
}

.hcis-field__suffix {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    user-select: none;
}

.hcis-field__clear-btn,
.hcis-field__toggle-btn {
    background: transparent;
    border: none;
    padding: 0;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: color 150ms;
}

.hcis-field__clear-btn:hover {
    color: var(--hcis-danger-500);
}

.hcis-field__toggle-btn:hover {
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

/* Validation States */
.hcis-field--error .hcis-field__control {
    border-color: var(--hcis-danger-500) !important;
    box-shadow: 0 0 0 3px rgb(var(--hcis-danger-500-rgb) / 0.15) !important;
}

.hcis-field--success .hcis-field__control {
    border-color: var(--hcis-success-500) !important;
    box-shadow: 0 0 0 3px rgb(var(--hcis-success-500-rgb) / 0.15) !important;
}

.hcis-field--disabled {
    cursor: not-allowed;
    user-select: none;
}

.hcis-field--disabled .hcis-field__label {
    color: var(--hcis-slate-700, var(--hcis-slate-700));
}

.hcis-field--disabled .hcis-field__addon {
    background: var(--hcis-slate-100) !important;
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300)) !important;
    color: var(--hcis-slate-400, var(--hcis-slate-400)) !important;
    cursor: not-allowed !important;
}

.hcis-field--disabled .hcis-field__icon {
    color: var(--hcis-slate-400, var(--hcis-slate-400)) !important;
}

.hcis-field__control:disabled {
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300)) !important;
    background: rgba(241, 245, 249, 0.85) !important;
    backdrop-filter: blur(4px);
    color: var(--hcis-slate-500, var(--hcis-slate-500)) !important;
    font-weight: 500 !important;
    cursor: not-allowed !important;
    box-shadow: inset 0 1px 2px 0 rgba(15, 23, 42, 0.05) !important;
    -webkit-text-fill-color: var(--hcis-slate-500, var(--hcis-slate-500));
}

/* Helper Row */
.hcis-field__helper-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.775rem;
    gap: 0.5rem;
}

.hcis-field__error {
    color: var(--hcis-danger-500);
    font-weight: 600;
    margin: 0;
    display: inline-flex;
    align-items: center;
}

.hcis-field__success {
    color: var(--hcis-success-500);
    font-weight: 600;
    margin: 0;
    display: inline-flex;
    align-items: center;
}

.hcis-field__hint {
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    margin: 0;
    font-weight: 500;
}

.hcis-field__count {
    color: var(--hcis-text-muted, var(--hcis-slate-400));
    font-size: 0.725rem;
    font-weight: 600;
    margin-left: auto;
}
</style>
