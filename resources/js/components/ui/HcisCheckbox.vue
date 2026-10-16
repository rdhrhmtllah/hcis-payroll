<template>
    <label
        class="hcis-choice"
        :class="[
            `hcis-choice--${size}`,
            `hcis-choice--variant-${variant}`,
            color ? `hcis-choice--color-${color}` : null,
            {
                'is-disabled': disabled,
                'is-checked': tercentang,
                'is-indeterminate': indeterminate,
            }
        ]"
    >
        <span class="hcis-choice__box-wrap">
            <input
                ref="kotak"
                type="checkbox"
                class="hcis-choice__input"
                :checked="tercentang"
                :disabled="disabled"
                v-bind="$attrs"
                @change="ubah($event.target.checked)"
            />
            <span class="hcis-choice__box" aria-hidden="true">
                <!-- Checkmark Icon (SVG Always in DOM, animated via CSS on :checked / .is-checked) -->
                <svg
                    class="hcis-choice__svg-check"
                    viewBox="0 0 16 16"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M3 8.5L6.5 12L13 4"
                        stroke="#ffffff"
                        stroke-width="2.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>

                <!-- Indeterminate Dash Icon -->
                <svg
                    class="hcis-choice__svg-dash"
                    viewBox="0 0 16 16"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M3.5 8h9"
                        stroke="#ffffff"
                        stroke-width="2.6"
                        stroke-linecap="round"
                    />
                </svg>
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

<script setup>
import { computed, onMounted, ref, watch } from 'vue';

defineOptions({ name: 'HcisCheckbox', inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [Boolean, Array, String, Number], default: undefined },
    value: { type: [String, Number], default: '' },
    label: { type: String, default: '' },
    description: { type: String, default: '' },
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
    indeterminate: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

const kotak = ref(null);
const localChecked = ref(false);

const berkelompok = computed(() => Array.isArray(props.modelValue));

const tercentang = computed(() => {
    if (props.modelValue !== undefined && props.modelValue !== null) {
        if (berkelompok.value) {
            return props.modelValue.includes(props.value);
        }
        if (typeof props.modelValue === 'string') {
            return ['true', '1', 'y', 'yes', 'on'].includes(props.modelValue.toLowerCase());
        }
        return Boolean(props.modelValue);
    }
    return localChecked.value;
});

function ubah(baru) {
    localChecked.value = baru;
    if (!berkelompok.value) {
        emit('update:modelValue', baru);
        emit('change', baru);
        return;
    }

    const daftar = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
    const posisi = daftar.indexOf(props.value);

    if (baru && posisi === -1) daftar.push(props.value);
    if (!baru && posisi !== -1) daftar.splice(posisi, 1);

    emit('update:modelValue', daftar);
    emit('change', daftar);
}

function terapkanIndeterminate() {
    if (kotak.value) kotak.value.indeterminate = props.indeterminate;
}

onMounted(terapkanIndeterminate);
watch(() => props.indeterminate, terapkanIndeterminate);
</script>

<style>
/* ══ HCIS Checkbox Icon Rendering ══ */
.hcis-choice__box {
    position: relative !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.hcis-choice__svg-check,
.hcis-choice__svg-dash {
    position: absolute !important;
    top: 50% !important;
    left: 50% !important;
    width: 68% !important;
    height: 68% !important;
    pointer-events: none !important;
    opacity: 0 !important;
    transform: translate(-50%, -50%) scale(0) !important;
    transition: transform 150ms cubic-bezier(0.16, 1, 0.3, 1), opacity 120ms ease !important;
}

/* Show checkmark when checked via CSS :checked or class .is-checked */
.hcis-choice__input:checked ~ .hcis-choice__box .hcis-choice__svg-check,
.hcis-choice.is-checked .hcis-choice__box .hcis-choice__svg-check {
    opacity: 1 !important;
    transform: translate(-50%, -50%) scale(1) !important;
}

/* Indeterminate state */
.hcis-choice.is-indeterminate .hcis-choice__box .hcis-choice__svg-check,
.hcis-choice__input:indeterminate ~ .hcis-choice__box .hcis-choice__svg-check {
    opacity: 0 !important;
    transform: translate(-50%, -50%) scale(0) !important;
}

.hcis-choice.is-indeterminate .hcis-choice__box .hcis-choice__svg-dash,
.hcis-choice__input:indeterminate ~ .hcis-choice__box .hcis-choice__svg-dash {
    opacity: 1 !important;
    transform: translate(-50%, -50%) scale(1) !important;
}
</style>
