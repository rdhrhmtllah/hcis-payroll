<template>
    <HcisField
        :id="id"
        :label="label"
        :hint="hint"
        :error="error"
        :success="success"
        :required="required"
        :disabled="disabled"
        :size="size"
        :variant="variant"
    >
        <div
            class="hcis-plate-input__control"
            :class="[
                `hcis-plate-input__control--${size}`,
                {
                    'hcis-plate-input__control--disabled': disabled,
                },
            ]"
            role="group"
            :aria-label="ariaLabel || label || 'Nomor plat kendaraan'"
        >
            <div class="hcis-plate-input__prefix">
                <HcisSelect
                    :id="`${id}-prefix`"
                    v-model="prefixValue"
                    :placeholder="prefixPlaceholder"
                    :disabled="disabled"
                    :aria-label="prefixAriaLabel"
                    :teleport-to="teleportTo"
                    :size="size"
                    @update:model-value="updateSegment('prefix', $event)"
                >
                    <HcisSelectOption
                        v-for="option in normalizedOptions"
                        :key="option.value"
                        :value="option.value"
                        :label="option.label"
                    />
                </HcisSelect>
            </div>

            <span class="hcis-plate-input__divider" aria-hidden="true" />

            <input
                :id="id"
                v-model="numberValue"
                class="hcis-plate-input__segment hcis-plate-input__number"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                :placeholder="numberPlaceholder"
                :disabled="disabled"
                :readonly="readonly"
                :maxlength="numberMaxLength"
                aria-label="Nomor utama plat kendaraan"
                @input="sanitizeNumber"
            />

            <span class="hcis-plate-input__divider" aria-hidden="true" />

            <input
                v-model="suffixValue"
                class="hcis-plate-input__segment hcis-plate-input__suffix"
                type="text"
                inputmode="text"
                autocomplete="off"
                :placeholder="suffixPlaceholder"
                :disabled="disabled"
                :readonly="readonly"
                :maxlength="suffixMaxLength"
                aria-label="Kode belakang plat kendaraan"
                @input="sanitizeSuffix"
            />
        </div>
    </HcisField>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import HcisField from './HcisField.vue';
import HcisSelect from './HcisSelect.vue';
import HcisSelectOption from './HcisSelectOption.vue';

defineOptions({ name: 'HcisPlateInput' });

const props = defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: '' },
    prefixOptions: { type: Array, default: () => [] },
    label: { type: String, default: 'Nomor plat kendaraan' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    success: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    size: { type: String, default: 'md' },
    variant: { type: String, default: 'outline' },
    ariaLabel: { type: String, default: '' },
    prefixAriaLabel: { type: String, default: 'Kode wilayah plat kendaraan' },
    prefixPlaceholder: { type: String, default: 'Kode' },
    numberPlaceholder: { type: String, default: '1234' },
    suffixPlaceholder: { type: String, default: 'XYZ' },
    numberMaxLength: { type: Number, default: 4 },
    suffixMaxLength: { type: Number, default: 3 },
    teleportTo: { type: [String, Boolean], default: 'body' },
});

const emit = defineEmits(['update:modelValue', 'change', 'update:parts']);
const prefixValue = ref('');
const numberValue = ref('');
const suffixValue = ref('');

const normalizedOptions = computed(() =>
    props.prefixOptions
        .map((option) => {
            if (typeof option === 'string' || typeof option === 'number') {
                return { value: String(option), label: String(option) };
            }

            const value = option?.value ?? option?.Kode ?? option?.kode ?? option?.code ?? '';
            const label = option?.label ?? option?.Nama ?? option?.name ?? value;
            return { value: String(value), label: String(label) };
        })
        .filter((option) => option.value),
);

function normalize(value) {
    return String(value || '')
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, ' ')
        .trim();
}

function parse(value) {
    const raw = normalize(value);
    if (!raw) return { prefix: '', number: '', suffix: '' };

    const compact = raw.replace(/\s+/g, '');
    const compactMatch = compact.match(/^([A-Z]{1,3})(\d{1,4})([A-Z]{0,3})$/);
    if (compactMatch) {
        return {
            prefix: compactMatch[1],
            number: compactMatch[2],
            suffix: compactMatch[3],
        };
    }

    const parts = raw.split(/\s+/).filter(Boolean);
    return {
        prefix: parts[0] || '',
        number: parts[1] || '',
        suffix: parts.slice(2).join(' '),
    };
}

function compose() {
    return [prefixValue.value, numberValue.value, suffixValue.value].filter(Boolean).join(' ');
}

function emitValue() {
    const value = compose();
    const parts = {
        prefix: prefixValue.value,
        angka: numberValue.value,
        suffix: suffixValue.value,
    };
    emit('update:modelValue', value);
    emit('update:parts', parts);
    emit('change', { value, ...parts });
}

function updateSegment(segment, value) {
    if (segment === 'prefix') prefixValue.value = String(value || '').toUpperCase();
    if (segment === 'number')
        numberValue.value = String(value || '')
            .replace(/[^0-9]/g, '')
            .slice(0, props.numberMaxLength);
    if (segment === 'suffix')
        suffixValue.value = String(value || '')
            .toUpperCase()
            .replace(/[^A-Z]/g, '')
            .slice(0, props.suffixMaxLength);
    emitValue();
}

function sanitizeNumber(event) {
    updateSegment('number', event.target.value);
}

function sanitizeSuffix(event) {
    updateSegment('suffix', event.target.value);
}

watch(
    () => props.modelValue,
    (value) => {
        const next = parse(value);
        prefixValue.value = next.prefix;
        numberValue.value = next.number.slice(0, props.numberMaxLength);
        suffixValue.value = next.suffix.slice(0, props.suffixMaxLength);
    },
    { immediate: true },
);
</script>

<style scoped>
.hcis-plate-input__control {
    display: flex;
    align-items: stretch;
    width: 100%;
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
    border-radius: var(--hcis-radius-md, 9px);
    background: var(--hcis-surface, var(--hcis-white));
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.04),
        inset 0 1px 1px rgba(15, 23, 42, 0.02);
    transition:
        border-color 180ms ease,
        box-shadow 180ms ease,
        background-color 180ms ease;
}

.hcis-plate-input__control:focus-within {
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    box-shadow:
        0 0 0 3px var(--hcis-focus-ring),
        0 1px 2px rgba(79, 70, 229, 0.1);
}

.hcis-plate-input__control--disabled {
    cursor: not-allowed;
    opacity: 0.65;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
}

.hcis-plate-input__prefix {
    flex: 0 0 5.25rem;
    min-width: 0;
}

.hcis-plate-input__prefix :deep(.hcis-select),
.hcis-plate-input__prefix :deep(.hcis-select__trigger) {
    width: 100%;
    height: 100%;
}

.hcis-plate-input__prefix :deep(.hcis-select__trigger) {
    min-height: 2.375rem;
    padding: 0 0.35rem 0 0.55rem !important;
    border: 0;
    border-radius: 0;
    box-shadow: none;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2px;
}

.hcis-plate-input__prefix :deep(.hcis-select__icon) {
    display: none !important;
}

.hcis-plate-input__prefix :deep(.hcis-select__display) {
    min-width: 0;
    flex: 1 1 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 !important;
}

.hcis-plate-input__prefix :deep(.hcis-select__selected-text),
.hcis-plate-input__prefix :deep(.hcis-select__placeholder) {
    font-weight: 750;
    font-size: 0.875rem;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    text-align: center;
    letter-spacing: 0.05em;
    white-space: nowrap;
    overflow: visible;
    text-overflow: clip;
}

.hcis-plate-input__prefix :deep(.hcis-select__arrow) {
    margin-left: 2px;
    flex-shrink: 0;
    font-size: 0.75rem;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
}

.hcis-plate-input__prefix :deep(.hcis-select__trigger:hover),
.hcis-plate-input__prefix :deep(.hcis-select__trigger--focused),
.hcis-plate-input__prefix :deep(.hcis-select__trigger--open) {
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
}

.hcis-plate-input__divider {
    flex: 0 0 1px;
    margin: 0.5rem 0;
    background: var(--hcis-border, var(--hcis-slate-200));
}

.hcis-plate-input__segment {
    min-width: 0;
    height: 2.375rem;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font: inherit;
    font-size: 0.875rem;
    font-weight: 650;
    transition: background-color 180ms ease;
}

.hcis-plate-input__segment::placeholder {
    color: var(--hcis-text-muted, var(--hcis-slate-400));
    font-weight: 500;
}

.hcis-plate-input__segment:focus {
    background: rgba(99, 102, 241, 0.04);
}

.hcis-plate-input__number {
    flex: 1 1 7rem;
    padding: 0 0.75rem;
    text-align: center;
    letter-spacing: 0.08em;
}

.hcis-plate-input__suffix {
    flex: 0 1 5.5rem;
    padding: 0 0.65rem;
    text-align: center;
    letter-spacing: 0.08em;
}

.hcis-plate-input__control--sm .hcis-plate-input__segment,
.hcis-plate-input__control--sm .hcis-plate-input__prefix :deep(.hcis-select__trigger) {
    height: 2rem;
    min-height: 2rem;
    font-size: 0.8125rem;
}

.hcis-plate-input__control--lg .hcis-plate-input__segment,
.hcis-plate-input__control--lg .hcis-plate-input__prefix :deep(.hcis-select__trigger) {
    height: 2.75rem;
    min-height: 2.75rem;
    font-size: 0.9375rem;
}

@media (max-width: 480px) {
    .hcis-plate-input__prefix {
        flex: 0 0 4.5rem;
    }

    .hcis-plate-input__prefix :deep(.hcis-select__trigger) {
        padding: 0 0.2rem 0 0.35rem !important;
    }

    .hcis-plate-input__number {
        flex: 1 1 auto;
        padding: 0 0.35rem;
        min-width: 0;
    }

    .hcis-plate-input__suffix {
        flex: 0 0 3.75rem;
        padding: 0 0.35rem;
    }
}

@media (max-width: 360px) {
    .hcis-plate-input__prefix {
        flex: 0 0 4rem;
    }

    .hcis-plate-input__prefix :deep(.hcis-select__selected-text),
    .hcis-plate-input__prefix :deep(.hcis-select__placeholder) {
        font-size: 0.8125rem;
    }

    .hcis-plate-input__number {
        padding: 0 0.25rem;
        font-size: 0.8125rem;
    }

    .hcis-plate-input__suffix {
        flex: 0 0 3.25rem;
        padding: 0 0.25rem;
        font-size: 0.8125rem;
    }
}
</style>
