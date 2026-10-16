<template>
    <HcisDialog
        :model-value="modelValue"
        :title="`Ubah batas waktu — ${baris?.nama_peran || ''}`"
        icon="hourglass-split"
        sentiment="brand"
        size="sm"
        header-divider
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="d-flex flex-column gap-3">
            <p class="small text-muted mb-0">
                {{ baris?.nama || 'Karyawan' }} · sekarang
                {{ baris?.berlaku_sampai ? tanggal(baris.berlaku_sampai) : 'tanpa batas' }}
            </p>
            <div>
                <label class="form-label small fw-semibold" for="batas-sampai">Berlaku sampai</label>
                <HcisDatePicker id="batas-sampai" v-model="sampai" type="date" placeholder="Tanpa batas" />
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <HcisButton
                        v-for="p in pintasan"
                        :key="p.label"
                        variant="ghost"
                        size="sm"
                        :label="p.label"
                        @click="sampai = p.nilai()"
                    />
                </div>
            </div>
            <HcisTextarea id="batas-alasan" v-model="alasan" label="Alasan (wajib, tercatat di audit)" :rows="2" />
        </div>
        <template #footer>
            <div class="d-flex justify-content-end gap-2 w-100">
                <HcisButton
                    variant="secondary"
                    label="Batal"
                    :disabled="simpan"
                    @click="$emit('update:modelValue', false)"
                />
                <HcisButton
                    variant="primary"
                    label="Simpan"
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
import { HcisButton, HcisDatePicker, HcisDialog, HcisTextarea, hcisMessage } from '@/components/ui';
import { aksesPeranApi, pesanGalat, tanggal } from '../api/aksesPeranApi.js';

/** Perpanjang / ubah batas waktu sebuah penugasan (kosong = tanpa batas). */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** { id_penugasan, nama, nama_peran, berlaku_sampai } */
    baris: { type: Object, default: null },
});
const emit = defineEmits(['update:modelValue', 'selesai']);
const sampai = ref('');
const alasan = ref('');
const simpan = ref(false);

const tambahHari = (n) => {
    const d = new Date();
    d.setDate(d.getDate() + n);
    return d.toISOString().slice(0, 10);
};
const pintasan = [
    { label: '+30 hari', nilai: () => tambahHari(30) },
    { label: '+90 hari', nilai: () => tambahHari(90) },
    { label: '+1 tahun', nilai: () => tambahHari(365) },
    { label: 'Tanpa batas', nilai: () => '' },
];

watch(
    () => props.modelValue,
    (buka) => {
        if (buka) {
            sampai.value = '';
            alasan.value = '';
        }
    },
);

async function kirim() {
    simpan.value = true;
    try {
        await aksesPeranApi.ubahBatas(props.baris.id_penugasan, sampai.value || null, alasan.value.trim());
        hcisMessage.success('Batas waktu diperbarui.');
        emit('update:modelValue', false);
        emit('selesai');
    } catch (e) {
        hcisMessage.error(pesanGalat(e));
    } finally {
        simpan.value = false;
    }
}
</script>
