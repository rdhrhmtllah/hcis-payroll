<template>
    <HcisDatePicker
        type="daterange"
        v-bind="$attrs"
        :model-value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :loading="loading"
        :clearable="clearable"
        :teleport-to="teleportTo"
        :show-presets="showPresets"
        :presets="presets"
        @update:model-value="$emit('update:modelValue', $event)"
        @change="$emit('change', $event)"
        @clear="$emit('clear')"
    />
</template>

<script setup>
/**
 * HcisDateRangePicker (Compatibility Wrapper)
 * Seluruh kemampuan rentang tanggal kini terintegrasi di <HcisDatePicker type="daterange">.
 * Komponen ini dipertahankan sebagai proxy backward-compatible untuk menjaga kompatibilitas impor lama.
 */
import HcisDatePicker from './HcisDatePicker.vue';

defineOptions({
    name: 'HcisDateRangePicker',
    inheritAttrs: false,
});

defineProps({
    modelValue: { type: Array, default: () => ['', ''] },
    placeholder: { type: String, default: 'Pilih Rentang Tanggal' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    teleportTo: { type: [String, Boolean], default: 'body' },
    showPresets: { type: Boolean, default: true },
    presets: { type: Array, default: null },
});

defineEmits(['update:modelValue', 'change', 'clear']);
</script>
