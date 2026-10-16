<template>
    <label
        class="hcis-field hcis-textarea"
        :class="[
            {
                'hcis-field--error': error,
                'hcis-field--success': success,
                'is-disabled': disabled,
                'is-focused': isFocused,
            }
        ]"
        :for="id"
    >
        <span class="hcis-field__label">
            {{ label }} <span v-if="required" class="hcis-field__required" aria-hidden="true">*</span>
        </span>

        <div class="hcis-textarea__wrapper">
            <textarea
                ref="textareaRef"
                :id="id"
                class="hcis-field__control hcis-textarea__control"
                :class="[
                    `hcis-textarea--resize-${resize}`,
                    { 'hcis-textarea--autoresize': autoResize }
                ]"
                :value="modelValue"
                :rows="rows"
                :maxlength="maxlength || undefined"
                :placeholder="placeholder"
                :disabled="disabled"
                v-bind="$attrs"
                @input="onInput"
                @focus="isFocused = true"
                @blur="isFocused = false"
            ></textarea>

            <button
                v-if="clearable && modelValue && !disabled"
                type="button"
                class="hcis-textarea__clear"
                aria-label="Bersihkan teks"
                @click="onClear"
            >
                <HcisIcon name="x-circle-fill" size="sm" />
            </button>
        </div>

        <div class="hcis-textarea__foot">
            <p v-if="error" class="hcis-field__error" role="alert">{{ error }}</p>
            <p v-else-if="success" class="hcis-field__success">{{ success }}</p>
            <p v-else-if="hint" class="hcis-field__hint">{{ hint }}</p>
            <span v-else></span>

            <span
                v-if="maxlength"
                class="hcis-textarea__count"
                :class="{
                    'is-near-limit': countPercent >= 90 && countPercent < 100,
                    'is-at-limit': countPercent >= 100
                }"
            >
                {{ currentLength }}/{{ maxlength }}
            </span>
        </div>
    </label>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisTextarea', inheritAttrs: false });

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: String, default: '' },
    rows: { type: Number, default: 4 },
    maxlength: { type: Number, default: 0 },
    placeholder: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    success: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false },
    autoResize: { type: Boolean, default: false },
    resize: {
        type: String,
        default: 'vertical',
        validator: (v) => ['vertical', 'none', 'horizontal', 'both', 'auto'].includes(v),
    },
});

const emit = defineEmits(['update:modelValue', 'clear']);

const isFocused = ref(false);
const textareaRef = ref(null);

const currentLength = computed(() => (props.modelValue ? String(props.modelValue).length : 0));
const countPercent = computed(() => (props.maxlength ? (currentLength.value / props.maxlength) * 100 : 0));

function adjustHeight() {
    if (!props.autoResize || !textareaRef.value) return;
    textareaRef.value.style.height = 'auto';
    textareaRef.value.style.height = `${textareaRef.value.scrollHeight}px`;
}

function onInput(event) {
    emit('update:modelValue', event.target.value);
    adjustHeight();
}

function onClear() {
    emit('update:modelValue', '');
    emit('clear');
    nextTick(() => {
        adjustHeight();
        textareaRef.value?.focus();
    });
}

watch(() => props.modelValue, () => {
    nextTick(adjustHeight);
});

onMounted(() => {
    nextTick(adjustHeight);
});
</script>
