<template>
    <div class="hcis-filter-field">
        <label v-if="filter.label" :for="idKendali" class="hcis-filter-field__label">
            <HcisIcon v-if="filter.icon" :name="filter.icon" size="2xs" class="hcis-filter-field__label-icon" />
            {{ filter.label }}
        </label>

        <!-- Teks bebas -->
        <HcisField
            v-if="jenis === 'input'"
            :id="idKendali"
            :model-value="nilai"
            :placeholder="filter.placeholder || ''"
            :disabled="filter.disabled"
            clearable
            @update:model-value="kirim"
        />

        <!-- Angka -->
        <HcisInputNumber
            v-else-if="jenis === 'number'"
            :model-value="nilai"
            :min="filter.min"
            :max="filter.max"
            :step="filter.step"
            :placeholder="filter.placeholder || ''"
            :disabled="filter.disabled"
            @update:model-value="kirim"
        />

        <!-- Pilihan tunggal / ganda -->
        <HcisSelect
            v-else-if="jenis === 'select'"
            :model-value="nilai"
            :placeholder="filter.placeholder || 'Pilih…'"
            :multiple="filter.multiple === true"
            :filterable="filter.filterable !== false"
            :clearable="filter.clearable !== false"
            :collapse-tags="filter.collapseTags !== false"
            :max-collapse-tags="filter.maxCollapseTags || 1"
            :collapse-tags-tooltip="filter.collapseTagsTooltip !== false"
            :disabled="filter.disabled"
            size="sm"
            @update:model-value="kirim"
        >
            <HcisSelectOption
                v-for="opt in filter.options || []"
                :key="opt.value"
                :value="opt.value"
                :label="opt.label"
            />
        </HcisSelect>

        <!-- Tanggal tunggal -->
        <HcisDatePicker
            v-else-if="jenis === 'date'"
            :model-value="nilai"
            :placeholder="filter.placeholder || 'Pilih tanggal'"
            :clearable="filter.clearable !== false"
            :disabled="filter.disabled"
            @update:model-value="kirim"
        />

        <!-- Rentang tanggal -->
        <HcisDatePicker
            v-else-if="jenis === 'daterange'"
            type="daterange"
            :model-value="nilai"
            :placeholder="filter.placeholder || 'Pilih rentang'"
            :clearable="filter.clearable !== false"
            :disabled="filter.disabled"
            @update:model-value="kirim"
        />

        <!-- Bertingkat -->
        <HcisCascader
            v-else-if="jenis === 'cascader'"
            :model-value="nilai"
            :options="filter.options || []"
            :placeholder="filter.placeholder || 'Pilih…'"
            :clearable="filter.clearable !== false"
            :disabled="filter.disabled"
            @update:model-value="kirim"
        />

        <!-- Pilihan berganda mendatar -->
        <div v-else-if="jenis === 'checkbox'" class="hcis-filter-field__kelompok">
            <HcisCheckbox
                v-for="opt in filter.options || []"
                :key="opt.value"
                :model-value="Array.isArray(nilai) && nilai.includes(opt.value)"
                :label="opt.label"
                :disabled="filter.disabled"
                @update:model-value="(on) => tukarCentang(opt.value, on)"
            />
        </div>

        <!-- Pilihan tunggal -->
        <HcisRadio
            v-else-if="jenis === 'radio'"
            :model-value="nilai"
            :options="filter.options || []"
            :disabled="filter.disabled"
            :direction="filter.direction || 'horizontal'"
            @update:model-value="kirim"
        />

        <!-- Hidup / mati -->
        <HcisSwitch
            v-else-if="jenis === 'switch'"
            :model-value="nilai === true"
            :active-text="filter.activeText"
            :inactive-text="filter.inactiveText"
            :disabled="filter.disabled"
            @update:model-value="kirim"
        />

        <!--
            Tipe yang tidak dikenali TIDAK diam-diam jatuh ke input teks.

            Perilaku lama modernFilter memakai input teks sebagai cadangan, dan
            itu menyembunyikan salah ketik: `type: 'slect'` tetap merender
            sesuatu yang terlihat wajar, lalu nilainya tidak pernah cocok dengan
            apa pun dan tidak ada yang tahu kenapa.
        -->
        <p v-else class="hcis-filter-field__galat">
            Tipe filter <code>{{ filter.type }}</code> tidak dikenali.
        </p>

        <small v-if="filter.hint" class="hcis-filter-field__hint">{{ filter.hint }}</small>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisField from './HcisField.vue';
import HcisInputNumber from './HcisInputNumber.vue';
import HcisSelect from './HcisSelect.vue';
import HcisSelectOption from './HcisSelectOption.vue';
import HcisDatePicker from './HcisDatePicker.vue';
import HcisCascader from './HcisCascader.vue';
import HcisCheckbox from './HcisCheckbox.vue';
import HcisRadio from './HcisRadio.vue';
import HcisSwitch from './HcisSwitch.vue';

defineOptions({ name: 'HcisFilterField' });

/**
 * Satu kolom saringan. Semua tipe dirender lewat komponen Hcis yang sudah ada
 * — bukan lewat Element Plus mentah — supaya isian saringan tampil persis sama
 * dengan isian di formulir mana pun.
 */
const props = defineProps({
    filter: { type: Object, required: true },
    modelValue: { type: [String, Number, Boolean, Array, Object], default: '' },
});

const emit = defineEmits(['update:modelValue']);

const idKendali = computed(() => 'hcis-filter-' + props.filter.key);

/*
 * Nama lama dipetakan ke nama baku, supaya konfigurasi modernFilter yang sudah
 * ada bisa dipindah apa adanya tanpa harus diketik ulang.
 */
const ALIAS = {
    text: 'input',
    multiselect: 'select',
    dateselect: 'date',
    monthrange: 'daterange',
};

const jenis = computed(() => {
    const t = props.filter.type || 'input';
    return ALIAS[t] ?? t;
});

const nilai = computed(() => props.modelValue);

function kirim(v) {
    emit('update:modelValue', v);
}

function tukarCentang(nilaiOpsi, aktif) {
    const sekarang = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
    const i = sekarang.indexOf(nilaiOpsi);

    if (aktif && i === -1) sekarang.push(nilaiOpsi);
    if (!aktif && i > -1) sekarang.splice(i, 1);

    emit('update:modelValue', sekarang);
}
</script>

<style scoped>
.hcis-filter-field {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    min-width: 0;
}

.hcis-filter-field__label {
    display: inline-flex;
    align-items: center;
    gap: 0.3125rem;
    color: var(--hcis-text-strong, var(--hcis-slate-700));
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: -0.01em;
}

.hcis-filter-field__label-icon {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.hcis-filter-field__kelompok {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    padding-top: 0.125rem;
}

.hcis-filter-field__hint {
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    font-size: 0.6875rem;
    line-height: 1.4;
}

.hcis-filter-field__galat {
    margin: 0;
    color: var(--hcis-red-600);
    font-size: 0.75rem;
    font-weight: 600;
}

.hcis-filter-field__galat code {
    font-family: var(--hcis-font-mono, monospace);
    background: var(--hcis-red-50);
    padding: 0.125rem 0.25rem;
    border-radius: 4px;
}
</style>
