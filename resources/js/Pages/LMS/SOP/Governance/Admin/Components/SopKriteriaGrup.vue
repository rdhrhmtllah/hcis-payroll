<template>
    <div class="sop-kriteria-grup">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h6 class="text-xs uppercase font-extrabold tracking-wider mb-1" :class="warnaJudul">
                    {{ judul }}
                </h6>
                <small class="text-slate-500">{{ keterangan }}</small>
            </div>

            <HcisButton
                variant="secondary"
                size="xs"
                leading-icon="plus-lg"
                :label="labelTambah"
                :title="labelTambah"
                @click="tambah"
            />
        </div>

        <HcisEmptyState
            v-if="!modelValue.length"
            :title="judulKosong"
            :description="keteranganKosong"
            icon="funnel"
            size="sm"
        />

        <div v-else class="d-flex flex-column gap-3">
            <HcisCard
                v-for="(group, index) in modelValue"
                :key="index"
                variant="flat"
                :sentiment="sentiment"
                size="sm"
                divider
                class="sop-cohort-card"
            >
                <template #header>
                    <div class="d-flex justify-content-between align-items-center w-100 gap-2">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <HcisBadge
                                :label="`${labelGrup} #${index + 1}`"
                                :variant="sentiment === 'danger' ? 'danger' : 'brand'"
                                size="xs"
                            />
                            <span class="text-xs text-slate-600 font-medium text-truncate">
                                {{ ringkasan(group) }}
                            </span>
                        </div>

                        <HcisButton
                            v-if="modelValue.length > minimal"
                            variant="danger"
                            size="xs"
                            leading-icon="trash3"
                            label="Hapus Grup"
                            :aria-label="`Hapus ${labelGrup} ${index + 1}`"
                            @click="hapus(index)"
                        />
                    </div>
                </template>

                <div class="row g-2.5">
                    <div v-for="field in fields" :key="field.key" class="col-12 col-sm-6 col-md-4">
                        <HcisField :id="`${idPrefix}-${index}-${field.key}`" :label="field.label">
                            <HcisSelect
                                :model-value="group[field.key] || []"
                                multiple
                                collapse-tags
                                :max-collapse-tags="1"
                                clearable
                                :placeholder="`Semua ${field.label}`"
                                @update:model-value="ubah(index, field.key, $event)"
                            >
                                <HcisSelectOption
                                    v-for="value in pilihan(field.key)"
                                    :key="value"
                                    :label="value"
                                    :value="value"
                                />
                            </HcisSelect>
                        </HcisField>
                    </div>

                    <div class="col-12 col-sm-6 col-md-4">
                        <HcisField :id="`${idPrefix}-${index}-tipe`" label="Tipe Pekerja">
                            <HcisSelect
                                :model-value="group.tipe || 'Semua'"
                                placeholder="Pilih Tipe"
                                @update:model-value="ubah(index, 'tipe', $event)"
                            >
                                <HcisSelectOption
                                    v-for="value in ['Semua', 'Karyawan', 'PHL']"
                                    :key="value"
                                    :label="value"
                                    :value="value"
                                />
                            </HcisSelect>
                        </HcisField>
                    </div>
                </div>
            </HcisCard>
        </div>
    </div>
</template>

<script>
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisEmptyState,
    HcisField,
    HcisSelect,
    HcisSelectOption,
} from '@/components/ui';

export const GROUP_FIELDS = [
    { key: 'divisi', label: 'Divisi' },
    { key: 'dept', label: 'Departemen' },
    { key: 'level', label: 'Level Jabatan' },
    { key: 'jabatan', label: 'Jabatan Spesifik' },
    { key: 'lokasi', label: 'Lokasi Kerja' },
];

export const blankGroup = () => ({
    divisi: [],
    dept: [],
    level: [],
    jabatan: [],
    lokasi: [],
    tipe: 'Semua',
});

/*
 * Satu kartu kriteria, dipakai DUA tempat: grup target dan grup pengecualian.
 *
 * Sebelum diangkat jadi komponen, markup-nya hidup langsung di Seksi 2. Begitu
 * pengecualian butuh bentuk yang sama, menyalinnya berarti dua tempat yang bisa
 * berbeda diam-diam — dan yang paling gampang tertinggal justru saringan yang
 * baru ditambahkan belakangan.
 */
export default {
    name: 'SopKriteriaGrup',
    components: {
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisEmptyState,
        HcisField,
        HcisSelect,
        HcisSelectOption,
    },
    props: {
        modelValue: { type: Array, default: () => [] },
        /** { divisi: [...], departemen: [...], level: [...], jabatan: [...], lokasi: [...] } */
        options: { type: Object, default: () => ({}) },
        judul: { type: String, required: true },
        keterangan: { type: String, default: '' },
        labelGrup: { type: String, default: 'Grup' },
        labelTambah: { type: String, default: 'Tambah Grup (OR)' },
        judulKosong: { type: String, default: 'Belum Ada Grup' },
        keteranganKosong: { type: String, default: '' },
        sentiment: { type: String, default: 'neutral' },
        /** Jumlah grup yang tak boleh dikurangi lagi. Target minimal 1; pengecualian boleh 0. */
        minimal: { type: Number, default: 0 },
        idPrefix: { type: String, default: 'sop-grup' },
    },
    emits: ['update:modelValue'],
    computed: {
        fields: () => GROUP_FIELDS,
        warnaJudul() {
            return this.sentiment === 'danger' ? 'text-danger-700' : 'text-brand-700';
        },
    },
    methods: {
        pilihan(key) {
            const map = {
                dept: 'departemen',
                divisi: 'divisi',
                level: 'level',
                jabatan: 'jabatan',
                lokasi: 'lokasi',
            };

            return this.options?.[map[key]] || [];
        },
        ringkasan(group) {
            const bagian = [];
            GROUP_FIELDS.forEach((f) => {
                const n = (group[f.key] || []).length;
                if (n) bagian.push(`${n} ${f.label}`);
            });
            if (group.tipe && group.tipe !== 'Semua') bagian.push(group.tipe);

            /* Grup tanpa kriteria ditolak server. Menyebutnya di sini membuat
               penolakan itu tak datang sebagai kejutan saat Simpan ditekan. */
            return bagian.length ? bagian.join(' · ') : 'Belum ada kriteria — wajib diisi';
        },
        tambah() {
            this.$emit('update:modelValue', [...this.modelValue, blankGroup()]);
        },
        hapus(index) {
            const daftar = [...this.modelValue];
            daftar.splice(index, 1);
            this.$emit('update:modelValue', daftar);
        },
        ubah(index, kunci, nilai) {
            const daftar = this.modelValue.map((g, i) =>
                i === index ? { ...g, [kunci]: nilai } : { ...g },
            );
            this.$emit('update:modelValue', daftar);
        },
    },
};
</script>

<style scoped>
.sop-kriteria-grup .min-w-0 {
    min-width: 0;
}
</style>
