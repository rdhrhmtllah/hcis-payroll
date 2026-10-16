<template>
    <div class="inc-grid">
        <div v-for="j in jenisDaftar" :key="j.kode" class="inc-field">
            <label class="inc-label" :for="`inc-${uid}-${j.kode}`">{{ j.label }}</label>
            <HcisSelect
                :id="`inc-${uid}-${j.kode}`"
                :model-value="modelValue[j.kode] || []"
                multiple
                collapse-tags
                filterable
                clearable
                size="sm"
                :placeholder="j.placeholder"
                :aria-label="j.label"
                @update:model-value="ubah(j.kode, $event)"
            >
                <HcisSelectOption v-for="o in opsi[j.kode] || []" :key="o.nilai" :value="o.nilai" :label="o.label" />
            </HcisSelect>
        </div>
        <div class="inc-field">
            <label class="inc-label" :for="`inc-${uid}-KARYAWAN`">Karyawan tertentu</label>
            <HcisSelect
                :id="`inc-${uid}-KARYAWAN`"
                :model-value="modelValue.KARYAWAN || []"
                multiple
                collapse-tags
                filterable
                clearable
                size="sm"
                :loading="mencari"
                placeholder="Ketik nama atau NIK…"
                aria-label="Karyawan tertentu"
                @search="cariKaryawan"
                @update:model-value="ubah('KARYAWAN', $event)"
            >
                <HcisSelectOption v-for="k in opsiKaryawan" :key="k.nilai" :value="k.nilai" :label="k.label" />
            </HcisSelect>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { HcisSelect, HcisSelectOption } from '@/components/ui';
import { aksesPeranApi } from '../api/aksesPeranApi.js';

/**
 * Isian nilai konkret cakupan: { DIVISI: [...], DEPARTEMEN: [...], ..., KARYAWAN: [...] }.
 * Dipakai pemilih cakupan penugasan dan pembuat template cakupan.
 */
const props = defineProps({
    modelValue: { type: Object, required: true },
    /** nilai_cakupan dari server: { DIVISI: [{nilai,label}], ... } */
    opsi: { type: Object, default: () => ({}) },
    /** label karyawan yang sudah dikenal (mis. dari baris cakupan tersimpan) { id: nama } */
    labelKaryawan: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue']);
const uid = Math.random().toString(36).slice(2, 8);

const jenisDaftar = [
    { kode: 'DIVISI', label: 'Divisi', placeholder: 'Pilih divisi…' },
    { kode: 'DEPARTEMEN', label: 'Departemen', placeholder: 'Pilih departemen…' },
    { kode: 'LOKASI', label: 'Lokasi', placeholder: 'Pilih lokasi…' },
    { kode: 'LEVEL', label: 'Level', placeholder: 'Pilih level…' },
    { kode: 'JABATAN', label: 'Jabatan', placeholder: 'Pilih jabatan…' },
];

function ubah(kode, nilai) {
    emit('update:modelValue', { ...props.modelValue, [kode]: nilai });
}

/* Karyawan: dicari ke server (terlalu banyak untuk didaftar semua). */
const kenal = reactive({});
const hasil = ref([]);
const mencari = ref(false);
let penunda = null;
let urutan = 0;
const opsiKaryawan = computed(() => {
    const peta = new Map(hasil.value.map((k) => [k.nilai, k]));
    for (const id of props.modelValue.KARYAWAN || []) {
        if (!peta.has(id))
            peta.set(id, { nilai: id, label: kenal[id] || props.labelKaryawan[id] || `Karyawan #${id}` });
    }
    return [...peta.values()];
});
function cariKaryawan(q) {
    clearTimeout(penunda);
    if (!q || q.trim().length < 2) return;
    penunda = setTimeout(async () => {
        const u = ++urutan;
        mencari.value = true;
        try {
            const r = await aksesPeranApi.pilihKaryawan({ cari: q.trim(), per_halaman: 20 });
            if (u !== urutan) return;
            hasil.value = (r?.data || []).map((k) => {
                const label = `${k.nama} · ${k.kode_karyawan || '—'}`;
                kenal[String(k.id_user)] = label;
                return { nilai: String(k.id_user), label };
            });
        } catch {
            /* pencarian tidak wajib */
        } finally {
            if (u === urutan) mencari.value = false;
        }
    }, 300);
}
</script>

<style scoped>
.inc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));
    gap: var(--hcis-space-3, 0.75rem);
}
.inc-field {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-1, 0.25rem);
    min-width: 0;
}
.inc-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-muted);
}
</style>
