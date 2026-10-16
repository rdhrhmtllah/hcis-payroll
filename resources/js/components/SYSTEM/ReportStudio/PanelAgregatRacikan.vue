<template>
    <div class="rs-agregat">
        <p class="rs-agregat__intro">
            Susun angka Anda sendiri: pilih kolomnya, lalu cara menghitungnya. Fungsi yang ditawarkan
            menyesuaikan tipe kolom — kolom teks hanya bisa dihitung, bukan dijumlahkan.
        </p>

        <div v-if="!daftar.length" class="rs-agregat__kosong">
            <HcisIcon name="calculator" size="sm" />
            <span>Belum ada agregat. Tambahkan bila ukuran bawaan belum menjawab pertanyaan Anda.</span>
        </div>

        <div v-for="(baris, i) in daftar" :key="i" class="rs-agregat__baris">
            <HcisField :id="`agregat-kolom-${i}`" label="Kolom" class="rs-agregat__kolom">
                <HcisSelect
                    :model-value="baris.kolom"
                    placeholder="Pilih kolom..."
                    filterable
                    size="sm"
                    :disabled="disabled"
                    @update:model-value="setKolom(i, $event)"
                >
                    <HcisSelectOption
                        v-for="dim in dimensiBisaDiagregasi"
                        :key="dim.key"
                        :value="dim.key"
                        :label="dim.label"
                    />
                </HcisSelect>
            </HcisField>

            <HcisField :id="`agregat-fungsi-${i}`" label="Cara hitung" class="rs-agregat__fungsi">
                <HcisSelect
                    :model-value="baris.fungsi"
                    placeholder="Pilih fungsi..."
                    size="sm"
                    :disabled="disabled || !baris.kolom"
                    @update:model-value="setFungsi(i, $event)"
                >
                    <!-- Hanya fungsi yang SAH untuk kolom itu, dan daftarnya
                         datang dari server. Layar yang menyimpulkannya sendiri
                         akan menyimpang dari validator, dan yang menyimpang di
                         sini berarti laporan baru ditolak setelah ditekan. -->
                    <HcisSelectOption
                        v-for="f in fungsiUntuk(baris.kolom)"
                        :key="f.value"
                        :value="f.value"
                        :label="f.label"
                    />
                </HcisSelect>
            </HcisField>

            <!-- HcisField tanpa slot = input berlabel, pola yang sama dipakai
                 "Nama template" di ReportStudio.vue. Tidak ada komponen
                 HcisInput di kit ini. -->
            <HcisField
                :id="`agregat-label-${i}`"
                :model-value="baris.label"
                label="Nama kolom (opsional)"
                :placeholder="labelBawaan(baris)"
                :disabled="disabled"
                class="rs-agregat__label"
                @update:model-value="setLabel(i, $event)"
            />

            <HcisButton
                variant="danger-quiet"
                size="xs"
                leading-icon="trash"
                :disabled="disabled"
                class="rs-agregat__hapus"
                :title="`Hapus agregat baris ${i + 1}`"
                @click="hapus(i)"
            />

            <!-- Peringatan yang JUJUR, bukan larangan. Rata-rata dan hitungan
                 unik memang berguna per kelompok; yang salah hanya bila
                 dijumlahkan di baris total, dan itu sudah dicegah server. -->
            <p v-if="baris.fungsi && !bisaDijumlah(baris.fungsi)" class="rs-agregat__catatan">
                <HcisIcon name="info-circle" size="2xs" />
                Tidak dijumlahkan di baris GRAND TOTAL — hasilnya akan menyesatkan bila ditotal.
            </p>
        </div>

        <HcisButton
            variant="secondary"
            size="sm"
            leading-icon="plus-lg"
            label="Tambah agregat"
            :disabled="disabled || daftar.length >= maks"
            class="mt-2"
            @click="tambah"
        />

        <p v-if="galat" class="rs-agregat__galat mt-2">{{ galat }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisButton, HcisField, HcisIcon, HcisSelect, HcisSelectOption } from '@/components/ui';

defineOptions({ name: 'PanelAgregatRacikan' });

const props = defineProps({
    /** [{ kolom, fungsi, label }] */
    modelValue: { type: Array, default: () => [] },
    /** Dimensi katalog — sumber kolom yang boleh diagregasi. */
    dimensi: { type: Array, default: () => [] },
    /** { fungsi: [{value,label,bisa_dijumlah}], per_kolom: { key: [fungsi] } } */
    katalogAgregat: { type: Object, default: () => ({ fungsi: [], per_kolom: {} }) },
    maks: { type: Number, default: 6 },
    disabled: { type: Boolean, default: false },
    galat: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const daftar = computed(() => props.modelValue ?? []);

/** Kolom yang punya setidaknya satu fungsi sah. */
const dimensiBisaDiagregasi = computed(() =>
    props.dimensi.filter((d) => (props.katalogAgregat?.per_kolom?.[d.key] ?? []).length > 0),
);

const petaFungsi = computed(() => {
    const peta = {};
    (props.katalogAgregat?.fungsi ?? []).forEach((f) => {
        peta[f.value] = f;
    });

    return peta;
});

function fungsiUntuk(kolom) {
    const diizinkan = props.katalogAgregat?.per_kolom?.[kolom] ?? [];

    return diizinkan.map((k) => petaFungsi.value[k]).filter(Boolean);
}

function bisaDijumlah(fungsi) {
    return petaFungsi.value[fungsi]?.bisa_dijumlah !== false;
}

function labelBawaan(baris) {
    if (!baris.kolom || !baris.fungsi) return 'Nama otomatis';
    const dim = props.dimensi.find((d) => d.key === baris.kolom);

    return `${petaFungsi.value[baris.fungsi]?.label ?? ''} ${dim?.label ?? ''}`.trim();
}

function ubah(i, patch) {
    const baru = daftar.value.map((b, idx) => (idx === i ? { ...b, ...patch } : b));
    emit('update:modelValue', baru);
}

function setKolom(i, kolom) {
    // Fungsi yang sudah dipilih DIBUANG bila tidak berlaku untuk kolom baru.
    // Membiarkannya menghasilkan pasangan terlarang yang baru ditolak server —
    // padahal layar sudah tahu ia mustahil sejak kolomnya diganti.
    const masihSah = (props.katalogAgregat?.per_kolom?.[kolom] ?? []).includes(daftar.value[i]?.fungsi);

    ubah(i, { kolom, fungsi: masihSah ? daftar.value[i].fungsi : '' });
}

function setFungsi(i, fungsi) {
    ubah(i, { fungsi });
}

function setLabel(i, label) {
    ubah(i, { label });
}

function tambah() {
    emit('update:modelValue', [...daftar.value, { kolom: '', fungsi: '', label: '' }]);
}

function hapus(i) {
    emit('update:modelValue', daftar.value.filter((_, idx) => idx !== i));
}
</script>

<style scoped>
.rs-agregat__intro {
    font-size: 0.75rem;
    line-height: 1.55;
    color: var(--hcis-text-muted, #64748b);
    margin-bottom: 0.75rem;
}

.rs-agregat__kosong {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border: 1px dashed var(--hcis-slate-300, #cbd5e1);
    border-radius: 0.5rem;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.rs-agregat__baris {
    display: grid;
    grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) minmax(0, 1fr) auto;
    gap: 0.5rem;
    align-items: end;
    padding: 0.625rem;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
    background: var(--hcis-slate-50, #f8fafc);
}

.rs-agregat__catatan {
    grid-column: 1 / -1;
    margin: 0.25rem 0 0;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.6875rem;
    color: #92400e;
}

.rs-agregat__galat {
    margin: 0;
    font-size: 0.75rem;
    color: var(--hcis-danger-600, #dc2626);
}

@media (max-width: 767.98px) {
    .rs-agregat__baris {
        grid-template-columns: 1fr;
    }

    .rs-agregat__hapus {
        justify-self: end;
    }
}
</style>
