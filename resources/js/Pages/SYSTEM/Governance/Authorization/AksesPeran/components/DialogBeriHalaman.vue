<template>
    <HcisDialog
        :model-value="modelValue"
        :title="`Beri akses halaman — ${akses.nama}`"
        icon="key"
        sentiment="brand"
        size="md"
        header-divider
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="d-flex flex-column gap-3">
            <HcisAlert
                variant="warning"
                appearance="subtle"
                title="Pengecualian"
                description="Grant langsung melewati peran. Pakai bila memang perlu, beri alasan dan sebaiknya tanggal berakhir."
            />
            <div>
                <label class="form-label small fw-semibold" for="bh-halaman">Halaman</label>
                <HcisSelect
                    id="bh-halaman"
                    v-model="form.jenis_page"
                    placeholder="Pilih halaman"
                    filterable
                    aria-label="Halaman"
                >
                    <HcisSelectOption
                        v-for="h in akses.halaman_tersedia"
                        :key="h.jenis_page"
                        :value="h.jenis_page"
                        :label="h.nama_menu || h.jenis_page"
                        :description="h.header || ''"
                    />
                </HcisSelect>
                <div v-if="sudahBisa" class="text-muted ap-kecil mt-1">
                    Karyawan ini sudah bisa membuka halaman ini lewat peran/grant lain.
                </div>
            </div>
            <div>
                <label class="form-label small fw-semibold" for="bh-sampai">Masa Berlaku Akses Halaman</label>
                <HcisDatePicker
                    id="bh-sampai"
                    v-model="form.berlaku_sampai"
                    type="date"
                    placeholder="Tanpa batas (permanen)"
                />
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <HcisButton
                        v-for="p in pintasan"
                        :key="p.label"
                        variant="ghost"
                        size="xs"
                        :label="p.label"
                        @click="form.berlaku_sampai = p.nilai()"
                    />
                </div>
            </div>
            <HcisTextarea
                id="bh-alasan"
                v-model="form.alasan"
                label="Alasan Pemberian Akses Langsung (wajib, tercatat di audit)"
                placeholder="Contoh: Dispensasi persetujuan dokumen tender khusus"
                :rows="2"
            />
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
                    label="Beri Akses Halaman"
                    :loading="simpan"
                    :disabled="!form.jenis_page || form.alasan.trim().length < 5"
                    @click="kirim"
                />
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import {
    HcisAlert,
    HcisButton,
    HcisDatePicker,
    HcisDialog,
    HcisSelect,
    HcisSelectOption,
    HcisTextarea,
    hcisMessage,
} from '@/components/ui';
import { aksesPeranApi, pesanGalat } from '../api/aksesPeranApi.js';

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

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    akses: { type: Object, required: true },
});
const emit = defineEmits(['update:modelValue', 'selesai']);
const form = reactive({ jenis_page: null, berlaku_sampai: '', alasan: '' });
const sudahBisa = ref(false);
const simpan = ref(false);

watch(
    () => props.modelValue,
    (buka) => {
        if (buka) Object.assign(form, { jenis_page: null, berlaku_sampai: '', alasan: '' });
        sudahBisa.value = false;
    },
);
let urutan = 0;
watch(
    () => form.jenis_page,
    async (p) => {
        sudahBisa.value = false;
        const u = ++urutan; // sebelum return: respons lama tak boleh mengisi dialog yang dibuka ulang
        if (!p) return;
        try {
            const r = await aksesPeranApi.pratinjau({ jenis: 'grant', id_user: props.akses.id_user, jenis_page: p });
            if (u === urutan) sudahBisa.value = !!r?.sudah_bisa;
        } catch (e) {
            /* pratinjau tidak wajib */
        }
    },
);

async function kirim() {
    simpan.value = true;
    try {
        await aksesPeranApi.beriGrant({
            id_user: props.akses.id_user,
            jenis_page: form.jenis_page,
            berlaku_sampai: form.berlaku_sampai || null,
            alasan: form.alasan.trim(),
        });
        hcisMessage.success('Akses halaman diberikan.');
        emit('update:modelValue', false);
        emit('selesai');
    } catch (e) {
        hcisMessage.error(pesanGalat(e));
    } finally {
        simpan.value = false;
    }
}
</script>

<style scoped>
.ap-kecil {
    font-size: var(--hcis-font-size-xs, 0.75rem);
}
</style>
