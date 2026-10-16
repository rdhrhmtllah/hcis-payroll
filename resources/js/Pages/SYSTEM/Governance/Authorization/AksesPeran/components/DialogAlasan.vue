<template>
    <HcisDialog
        :model-value="modelValue"
        :title="judul"
        icon="exclamation-triangle"
        sentiment="danger"
        size="sm"
        header-divider
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <p class="small text-muted">{{ keterangan }}</p>
        <HcisTextarea id="alasan-dialog" v-model="alasan" label="Alasan (wajib, tercatat di audit)" :rows="3" />
        <template #footer>
            <div class="d-flex justify-content-end gap-2 w-100">
                <HcisButton
                    variant="secondary"
                    label="Batal"
                    :disabled="simpan"
                    @click="$emit('update:modelValue', false)"
                />
                <HcisButton
                    variant="danger"
                    :label="label"
                    :loading="simpan"
                    :disabled="alasan.trim().length < 5"
                    @click="kirim"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import { HcisButton, HcisDialog, HcisTextarea, hcisMessage } from '@/components/ui';
import { pesanGalat } from '../api/aksesPeranApi.js';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    judul: { type: String, required: true },
    keterangan: { type: String, default: '' },
    label: { type: String, default: 'Cabut' },
    /** async (alasan) => void */
    aksi: { type: Function, required: true },
});
const emit = defineEmits(['update:modelValue', 'selesai']);
const alasan = ref('');
const simpan = ref(false);

watch(
    () => props.modelValue,
    (buka) => {
        if (buka) alasan.value = '';
    },
);

async function kirim() {
    simpan.value = true;
    try {
        await props.aksi(alasan.value.trim());
        emit('update:modelValue', false);
        emit('selesai');
    } catch (e) {
        hcisMessage.error(pesanGalat(e));
    } finally {
        simpan.value = false;
    }
}
</script>
