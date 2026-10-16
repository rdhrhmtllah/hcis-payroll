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
            class="hcis-identity-input__control"
            :class="[
                `hcis-identity-input__control--${size}`,
                {
                    'hcis-identity-input__control--disabled': disabled,
                },
            ]"
            role="group"
            :aria-label="ariaLabel || label || 'Nomor identitas tamu'"
        >
            <div class="hcis-identity-input__type">
                <HcisSelect
                    :id="`${id}-type`"
                    v-model="typeModel"
                    :placeholder="typePlaceholder"
                    :disabled="disabled"
                    :aria-label="typeAriaLabel"
                    :teleport-to="teleportTo"
                    :size="size"
                    leading-icon="card-heading"
                >
                    <HcisSelectOption
                        v-for="option in normalizedOptions"
                        :key="option.value"
                        :value="option.value"
                        :label="option.label"
                    />
                </HcisSelect>
            </div>

            <span class="hcis-identity-input__divider" aria-hidden="true" />

            <input
                :id="id"
                v-model="model"
                class="hcis-identity-input__number"
                :type="inputType"
                :inputmode="inputmode"
                autocomplete="off"
                :placeholder="numberPlaceholder"
                :disabled="disabled"
                :readonly="readonly"
                :maxlength="maxLength"
                :aria-label="numberAriaLabel"
            />
        </div>
    </HcisField>
</template>

<script setup>
import { computed } from 'vue';
import HcisField from './HcisField.vue';
import HcisSelect from './HcisSelect.vue';
import HcisSelectOption from './HcisSelectOption.vue';

defineOptions({ name: 'HcisIdentityInput' });

const props = defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: '' },
    typeValue: { type: String, default: '' },
    typeOptions: { type: Array, default: () => [] },
    label: { type: String, default: 'Nomor identitas' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    success: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    size: { type: String, default: 'md' },
    variant: { type: String, default: 'outline' },
    ariaLabel: { type: String, default: '' },
    typeAriaLabel: { type: String, default: 'Jenis identitas tamu' },
    numberAriaLabel: { type: String, default: 'Nomor identitas tamu' },
    typePlaceholder: { type: String, default: 'Jenis' },
    numberPlaceholder: { type: String, default: 'Masukkan nomor identitas' },
    inputType: { type: String, default: 'text' },
    inputmode: { type: String, default: 'text' },
    maxLength: { type: [Number, String], default: 100 },
    teleportTo: { type: [String, Boolean], default: 'body' },
});

const emit = defineEmits(['update:modelValue', 'update:typeValue', 'change']);

const model = computed({
    get: () => props.modelValue,
    set: (value) => {
        emit('update:modelValue', value);
        emit('change', { value, type: props.typeValue });
    },
});

const typeModel = computed({
    get: () => props.typeValue,
    set: (value) => {
        emit('update:typeValue', String(value || ''));
        emit('change', { value: props.modelValue, type: String(value || '') });
    },
});

const normalizedOptions = computed(() =>
    props.typeOptions
        .map((option) => {
            if (typeof option === 'string' || typeof option === 'number') {
                return { value: String(option), label: String(option) };
            }

            const value =
                option?.value ??
                option?.Kode ??
                option?.kode ??
                option?.code ??
                option?.Nama_Jenis_Identitas ??
                option?.Nama ??
                option?.name ??
                '';
            const label =
                option?.label ??
                option?.Nama_Jenis_Identitas ??
                option?.Nama ??
                option?.name ??
                value;

            return { value: String(value), label: String(label) };
        })
        .filter((option) => option.value),
);
</script>

<style scoped>
.hcis-identity-input__control {
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

.hcis-identity-input__control:focus-within {
    border-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    box-shadow:
        0 0 0 3px var(--hcis-focus-ring),
        0 1px 2px rgba(79, 70, 229, 0.1);
}

.hcis-identity-input__control--disabled {
    cursor: not-allowed;
    opacity: 0.65;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
}

.hcis-identity-input__type {
    flex: 0 0 7.25rem;
    min-width: 0;
}

.hcis-identity-input__type :deep(.hcis-select),
.hcis-identity-input__type :deep(.hcis-select__trigger) {
    width: 100%;
    height: 100%;
}

.hcis-identity-input__type :deep(.hcis-select__trigger) {
    min-height: 2.375rem;
    border: 0;
    border-radius: 0;
    box-shadow: none;
    background: transparent;
}

.hcis-identity-input__type :deep(.hcis-select__trigger:hover),
.hcis-identity-input__type :deep(.hcis-select__trigger--focused),
.hcis-identity-input__type :deep(.hcis-select__trigger--open) {
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
}

.hcis-identity-input__divider {
    flex: 0 0 1px;
    margin: 0.5rem 0;
    background: var(--hcis-border, var(--hcis-slate-200));
}

.hcis-identity-input__number {
    flex: 1 1 auto;
    min-width: 0;
    height: 2.375rem;
    padding: 0 0.75rem;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font: inherit;
    font-size: 0.875rem;
    font-weight: 650;
    transition: background-color 180ms ease;
}

.hcis-identity-input__number::placeholder {
    color: var(--hcis-text-muted, var(--hcis-slate-400));
    font-weight: 500;
}

.hcis-identity-input__number:focus {
    background: rgba(99, 102, 241, 0.04);
}

.hcis-identity-input__control--sm .hcis-identity-input__number,
.hcis-identity-input__control--sm .hcis-identity-input__type :deep(.hcis-select__trigger) {
    height: 2rem;
    min-height: 2rem;
    font-size: 0.8125rem;
}

.hcis-identity-input__control--lg .hcis-identity-input__number,
.hcis-identity-input__control--lg .hcis-identity-input__type :deep(.hcis-select__trigger) {
    height: 2.75rem;
    min-height: 2.75rem;
    font-size: 0.9375rem;
}

@media (max-width: 480px) {
    .hcis-identity-input__type {
        flex-basis: 6rem;
    }

    .hcis-identity-input__number {
        padding: 0 0.55rem;
    }
}
</style>
