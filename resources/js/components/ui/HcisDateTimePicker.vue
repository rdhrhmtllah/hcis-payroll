<template>
    <HcisDatePicker
        type="datetime"
        v-bind="$attrs"
        :model-value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :loading="loading"
        :clearable="clearable"
        :teleport-to="teleportTo"
        :show-presets="showPresets"
        @update:model-value="$emit('update:modelValue', $event)"
        @change="$emit('change', $event)"
        @clear="$emit('clear')"
    />
</template>

<script setup>
/**
 * HcisDateTimePicker (Compatibility Wrapper)
 * Seluruh kemampuan tanggal dan jam kini terintegrasi di <HcisDatePicker type="datetime">.
 * Komponen ini dipertahankan sebagai proxy backward-compatible untuk menjaga kompatibilitas impor lama.
 */
import HcisDatePicker from './HcisDatePicker.vue';

defineOptions({
    name: 'HcisDateTimePicker',
    inheritAttrs: false,
});

defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Pilih Tanggal & Jam' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    teleportTo: { type: [String, Boolean], default: 'body' },
    showPresets: { type: Boolean, default: true },
});

defineEmits(['update:modelValue', 'change', 'clear']);
</script>
