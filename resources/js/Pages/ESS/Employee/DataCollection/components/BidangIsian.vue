<template>
    <!--
        Tanggal dan pilihan memakai bingkai yang SAMA: label, petunjuk, dan
        pesan galat di tempat yang sama seperti isian teks.
    -->
    <div
        v-if="['tanggal', 'pilihan', 'cari_karyawan'].includes(definisi.tipe)"
        class="pd-bidang"
    >
        <div class="pd-bidang__header">
            <label :for="idInput" class="pd-bidang__label">
                <span class="pd-bidang__icon-badge">
                    <HcisIcon :name="ikon" size="2xs" class="pd-bidang__ikon" />
                </span>
                <span class="pd-bidang__label-text">{{ definisi.label }}</span>
                <span v-if="definisi.wajib" class="pd-bidang__required" aria-hidden="true">*</span>
            </label>

            <!-- Standardized Provenance Corner Badge -->
            <div v-if="adaDataMaster" class="pd-bidang__corner">
                <span
                    v-if="!isDiubahDariMaster"
                    class="pd-master-pill pd-master-pill--terdaftar"
                    title="Data sesuai data kepegawaian Anda di HCIS"
                >
                    <i class="bi bi-check-circle-fill me-1"></i> Data Terdaftar
                </span>
                <div v-else class="pd-master-group">
                    <span
                        class="pd-master-pill pd-master-pill--diubah"
                        title="Nilai telah Anda sesuaikan dari data sebelumnya"
                    >
                        <i class="bi bi-pencil-fill me-1"></i> Diperbarui
                    </span>
                    <button
                        type="button"
                        class="pd-master-reset-btn"
                        title="Kembalikan ke data awal di sistem"
                        @click.stop.prevent="pulihkanDataMaster"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span>Pulihkan</span>
                    </button>
                </div>
            </div>
        </div>

        <p v-if="definisi.petunjuk" :id="idPetunjuk" class="pd-bidang__petunjuk">
            <HcisIcon name="bi bi-info-circle" size="2xs" class="me-1" />
            {{ definisi.petunjuk }}
        </p>

        <div class="pd-bidang__control-wrap">
            <HcisDatePicker
                v-if="definisi.tipe === 'tanggal'"
                :id="idInput"
                :model-value="modelValue"
                :placeholder="`Pilih ${definisi.label.toLowerCase()}`"
                :disabled-date="tanggalTerlarang"
                :aria-invalid="galatTampil ? 'true' : 'false'"
                :aria-describedby="idBantuan"
                :year-first="format === 'tanggal_lahir'"
                :type="format === 'tanggal_lahir' ? 'birthdate' : 'date'"
                :show-presets="false"
                format="D MMMM YYYY"
                size="md"
                class="w-100 pd-bidang__datepicker"
                @update:model-value="(v) => $emit('update:modelValue', v ?? '')"
            />

            <!-- Atasan PHL: yang disimpan Kode_Karyawan, yang dibaca orang namanya. -->
            <HcisSelect
                v-else-if="definisi.tipe === 'cari_karyawan'"
                :id="idInput"
                :model-value="modelValue"
                placeholder="Cari nama karyawan"
                clearable
                filterable
                :aria-invalid="galatTampil ? 'true' : 'false'"
                :aria-describedby="idBantuan"
                size="md"
                class="w-100 pd-bidang__select"
                @search="cariKaryawan"
                @update:model-value="pilihKaryawan"
            >
                <!-- Di luar HcisSelectOption supaya tidak ikut disaring ketikan. -->
                <div v-if="keteranganCari" class="pd-cari__status" :class="{ 'is-memuat': sedangMencari }" aria-live="polite">
                    <span v-if="sedangMencari" class="pd-cari__spinner" aria-hidden="true"></span>
                    <span>{{ keteranganCari }}</span>
                </div>
                <HcisSelectOption
                    v-for="k in opsiKaryawan"
                    :key="k.kode"
                    :value="k.kode"
                    :label="k.nama"
                    :description="k.keterangan || undefined"
                />
            </HcisSelect>

            <HcisSelect
                v-else
                :id="idInput"
                :model-value="modelValue"
                :placeholder="`Pilih ${definisi.label.toLowerCase()}`"
                clearable
                :aria-invalid="galatTampil ? 'true' : 'false'"
                :aria-describedby="idBantuan"
                size="md"
                class="w-100 pd-bidang__select"
                @update:model-value="(v) => $emit('update:modelValue', v ?? '')"
            >
                <HcisSelectOption
                    v-for="opsi in daftarPilihan"
                    :key="opsi"
                    :value="opsi"
                    :label="definisi.label_pilihan?.[opsi] ?? opsi"
                />
            </HcisSelect>
        </div>

        <p v-if="galatTampil" :id="idGalat" class="pd-bidang__galat" role="alert">
            <HcisIcon name="exclamation-circle-fill" size="2xs" class="me-1" />
            <span>{{ galatTampil }}</span>
        </p>
    </div>

    <!-- Teks, NIK, HP, Email, dll. -->
    <HcisField
        v-else
        :id="idInput"
        :label="definisi.label"
        :model-value="nilaiTampil"
        :placeholder="petunjukKetik"
        :hint="definisi.petunjuk"
        :error="galatTampil ?? ''"
        :aria-invalid="galatTampil ? 'true' : 'false'"
        :max-length="batasKetik ?? undefined"
        :show-count="tampilkanHitungan"
        :numeric-only="batasKetik !== null"
        :inputmode="modeMasukan"
        size="md"
        class="pd-bidang"
        @update:model-value="terimaKetikan"
    >
        <template #label>
            <span class="pd-bidang__label">
                <span class="pd-bidang__icon-badge">
                    <HcisIcon :name="ikon" size="2xs" class="pd-bidang__ikon" />
                </span>
                <span class="pd-bidang__label-text">{{ definisi.label }}</span>
                <span v-if="definisi.wajib" class="pd-bidang__required" aria-hidden="true">*</span>
            </span>
        </template>

        <template #corner>
            <div v-if="adaDataMaster" class="pd-bidang__corner">
                <span
                    v-if="!isDiubahDariMaster"
                    class="pd-master-pill pd-master-pill--terdaftar"
                    title="Data sesuai data kepegawaian Anda di HCIS"
                >
                    <i class="bi bi-check-circle-fill me-1"></i> Data Terdaftar
                </span>
                <div v-else class="pd-master-group">
                    <span
                        class="pd-master-pill pd-master-pill--diubah"
                        title="Nilai telah Anda sesuaikan dari data sebelumnya"
                    >
                        <i class="bi bi-pencil-fill me-1"></i> Diperbarui
                    </span>
                    <button
                        type="button"
                        class="pd-master-reset-btn"
                        title="Kembalikan ke data awal di sistem"
                        @click.stop.prevent="pulihkanDataMaster"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span>Pulihkan</span>
                    </button>
                </div>
            </div>
        </template>

        <template v-if="isPhoneField" #prepend>
            <span class="pd-bidang__phone-prepend">
                <svg
                    class="pd-bidang__flag-svg"
                    viewBox="0 0 20 14"
                    width="20"
                    height="14"
                    aria-label="Bendera Indonesia"
                    role="img"
                >
                    <rect width="20" height="7" fill="#E11D48" />
                    <rect y="7" width="20" height="7" fill="#FFFFFF" />
                    <rect width="20" height="14" fill="none" stroke="rgba(0, 0, 0, 0.15)" stroke-width="1" rx="2" />
                </svg>
                <span class="pd-bidang__phone-code">+62</span>
            </span>
        </template>
    </HcisField>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import axios from 'axios';
import { HcisDatePicker, HcisField, HcisIcon, HcisSelect, HcisSelectOption } from '@/components/ui';
import {
    AWALAN_SIMPAN_HP,
    angkaDiketikHp,
    batasKetikan,
    batasTanggalLahir,
    galatSeketika,
    nilaiSimpanAngka,
    nilaiSimpanHp,
    nilaiSimpanNik,
} from '@/composables/ESS/Employee/DataCollection/formatBidang.js';

/**
 * Satu isian formulir, dirakit dari komponen bersama.
 *
 * Sifat field DIBACA dari skema; yang tinggal di sini hanya yang khas formulir
 * ini: penyaring angka, batas ketikan, dan jendela tanggal lahir.
 */
const props = defineProps({
    kunci: { type: String, required: true },
    definisi: { type: Object, required: true },
    modelValue: { type: [String, null], default: '' },
    nilaiMaster: { type: [String, Number, null], default: undefined },
    galat: { type: [String, null], default: null },
    pilihanDinamis: { type: Array, default: () => [] },
    /** { nama, keterangan } untuk nilai 'cari_karyawan' yang sudah terisi. */
    karyawanTerpilih: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'nama']);

// ── Pencarian karyawan (tipe 'cari_karyawan') ──────────────────────────────
const hasilCari = ref([]);
const keteranganCari = ref('');
const sedangMencari = ref(false);
let penundaCari = null;
let nomorPermintaan = 0;

/** Pilihan yang sedang terisi selalu ikut, supaya namanya tetap tampil. */
const opsiKaryawan = computed(() => {
    const daftar = [...hasilCari.value];
    const kode = String(props.modelValue ?? '').trim();
    if (kode && !daftar.some((k) => k.kode === kode)) {
        daftar.unshift({
            kode,
            nama: props.karyawanTerpilih?.nama || kode,
            keterangan: props.karyawanTerpilih?.keterangan || '',
        });
    }
    return daftar;
});

const cariKaryawan = (teks) => {
    const q = String(teks ?? '').trim();
    window.clearTimeout(penundaCari);

    if (q.length < 2) {
        nomorPermintaan += 1;
        sedangMencari.value = false;
        hasilCari.value = [];
        keteranganCari.value = q.length ? 'Ketik minimal 2 huruf nama atau kode karyawan.' : '';
        return;
    }

    // Penanda muncul SEKETIKA, bukan setelah jeda 300 ms: jeda itu bagian dari
    // menunggu yang dirasakan orang.
    sedangMencari.value = true;
    keteranganCari.value = 'Mencari karyawan…';
    penundaCari = window.setTimeout(async () => {
        const nomor = (nomorPermintaan += 1);
        try {
            const { data } = await axios.get('/pengumpulan-data/cari-karyawan', { params: { q } });
            // Jawaban yang datang terlambat untuk ketikan lama dibuang.
            if (nomor !== nomorPermintaan) return;
            sedangMencari.value = false;
            hasilCari.value = data?.data ?? [];
            keteranganCari.value = hasilCari.value.length ? '' : `Tidak ada karyawan aktif yang cocok dengan "${q}".`;
        } catch {
            if (nomor !== nomorPermintaan) return;
            sedangMencari.value = false;
            hasilCari.value = [];
            keteranganCari.value = 'Pencarian gagal. Coba lagi sebentar.';
        }
    }, 300);
};

const pilihKaryawan = (kode) => {
    const nilai = kode ?? '';
    const terpilih = opsiKaryawan.value.find((k) => k.kode === nilai);
    if (terpilih) emit('nama', terpilih.kode, { nama: terpilih.nama, keterangan: terpilih.keterangan ?? '' });
    keteranganCari.value = '';
    emit('update:modelValue', nilai);
};

onBeforeUnmount(() => window.clearTimeout(penundaCari));

const adaDataMaster = computed(() => {
    if (props.nilaiMaster === undefined || props.nilaiMaster === null) return false;
    const str = String(props.nilaiMaster).trim();
    return str !== '' && !/^[\s\-–—]+$/.test(str);
});

const isDiubahDariMaster = computed(() => {
    if (!adaDataMaster.value) return false;
    const strVal = String(props.modelValue ?? '').trim();
    const strMaster = String(props.nilaiMaster ?? '').trim();
    return strVal !== strMaster;
});

const pulihkanDataMaster = () => {
    if (adaDataMaster.value) {
        emit('update:modelValue', String(props.nilaiMaster).trim());
    }
};

const idInput = computed(() => `pd-${props.kunci}`);
const idPetunjuk = computed(() => `pd-${props.kunci}-petunjuk`);
const idGalat = computed(() => `pd-${props.kunci}-galat`);

/**
 * Sifat field DIBACA dari skema, tidak ditebak dari labelnya.
 */
const format = computed(() => props.definisi?.format ?? null);
const isPhoneField = computed(() => format.value === 'hp');
const isNikField = computed(() => format.value === 'nik');
const isAngkaField = computed(() => format.value === 'angka');

const batasKetik = computed(() => batasKetikan(props.definisi));
const batasTanggal = computed(() => batasTanggalLahir(props.definisi));

const modeMasukan = computed(() => (batasKetik.value !== null ? 'numeric' : undefined));

// Penghitung '2/16' menolong pada nomor panjang yang memang dihafal per digit.
// Pada cacahan dua digit ia hanya jadi angka kedua yang tidak berarti apa-apa
// di sebelah angka yang baru saja diketik.
const tampilkanHitungan = computed(() => isNikField.value || isPhoneField.value);

/** Menahan di kalender lebih baik daripada memarahi sesudahnya. */
const tanggalTerlarang = computed(() => {
    const batas = batasTanggal.value;
    if (!batas) return null;

    return (tanggal) => {
        const teks = [
            tanggal.getFullYear(),
            String(tanggal.getMonth() + 1).padStart(2, '0'),
            String(tanggal.getDate()).padStart(2, '0'),
        ].join('-');

        return teks > batas.paling_akhir || teks < batas.paling_awal;
    };
});

// Nomor HP ditampilkan tanpa awalan apa pun karena kotaknya sudah berawalan
// '+62'; yang disimpan berawalan '62' seperti tuntutan server. angkaDiketikHp()
// membuang '62' MAUPUN '0' di depan, jadi nilai lama berbentuk '08…' — draf
// yang ditulis sebelum 4 Sep 2026 — tetap tampil benar tanpa perlu dipugar.
const nilaiTampil = computed(() =>
    isPhoneField.value ? angkaDiketikHp(props.modelValue) : (props.modelValue ?? ''),
);

// Bentuk simpan DIBACA dari skema, bukan ditebak — satu sumber dengan pola
// validasi server, yang disusun dari nilai yang sama di config.
const awalanSimpanHp = computed(() => props.definisi?.prefix_simpan ?? AWALAN_SIMPAN_HP);

const petunjukKetik = computed(() => {
    if (isNikField.value) return `${batasKetik.value} digit NIK KTP…`;
    if (isPhoneField.value) return '8123456789…';
    if (isAngkaField.value) return `Contoh: ${props.definisi.nilai_min ?? 0}`;

    return `Masukkan ${props.definisi.label.toLowerCase()}…`;
});

const terimaKetikan = (masukan) => {
    if (isPhoneField.value) return emit('update:modelValue', nilaiSimpanHp(masukan, batasKetik.value, awalanSimpanHp.value));
    if (isNikField.value) return emit('update:modelValue', nilaiSimpanNik(masukan, batasKetik.value));
    if (isAngkaField.value) return emit('update:modelValue', nilaiSimpanAngka(masukan, batasKetik.value));

    return emit('update:modelValue', masukan);
};

const galatTampil = computed(() => props.galat ?? galatSeketika(props.definisi, props.modelValue));

const idBantuan = computed(() => {
    const bagian = [];
    if (props.definisi.petunjuk) bagian.push(idPetunjuk.value);
    if (galatTampil.value) bagian.push(idGalat.value);

    return bagian.length ? bagian.join(' ') : undefined;
});

const daftarPilihan = computed(() =>
    props.definisi.sumber_pilihan ? props.pilihanDinamis : (props.definisi.pilihan ?? []),
);

/** Ikon dideklarasikan skema, bukan dicocokkan dengan kata di label. */
const ikon = computed(() => props.definisi.ikon ?? 'bi bi-pencil-square');
</script>

<style scoped>
.pd-cari__status {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
    padding: 0.6rem 0.75rem;
    font-size: 0.8125rem;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.pd-cari__spinner {
    width: 0.9rem;
    height: 0.9rem;
    flex-shrink: 0;
    border: 2px solid rgb(var(--hcis-brand-500-rgb) / 0.2);
    border-top-color: var(--hcis-brand-500);
    border-radius: 50%;
    animation: pd-cari-putar 0.7s linear infinite;
}

@keyframes pd-cari-putar {
    to {
        transform: rotate(360deg);
    }
}

.pd-bidang {
    display: flex;
    flex-direction: column;
    width: 100%;
}

.pd-bidang__header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 0.375rem 0.5rem;
    width: 100%;
    margin-bottom: 0.4rem;
}

.pd-bidang__corner {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    flex-shrink: 0;
}

.pd-bidang__label {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0;
    font-size: 0.84375rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

:deep(.hcis-field__label .pd-bidang__label) {
    margin-bottom: 0;
}

.pd-bidang__icon-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.45rem;
    height: 1.45rem;
    border-radius: var(--hcis-radius-sm, 7px);
    background: linear-gradient(135deg, var(--hcis-brand-50, var(--hcis-indigo-50)), var(--hcis-brand-100, var(--hcis-indigo-100)));
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    border: 1px solid rgb(var(--hcis-brand-500-rgb, 99 102 241) / 0.22);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    flex-shrink: 0;
}

.pd-bidang__ikon {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.pd-bidang__label-text {
    line-height: 1.25;
}

.pd-bidang__required {
    color: var(--hcis-danger-500, var(--hcis-red-500));
    font-weight: 800;
    margin-left: 2px;
}

.pd-bidang__petunjuk {
    display: inline-flex;
    align-items: center;
    margin: 0 0 0.4rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    line-height: 1.35;
}

.pd-bidang__control-wrap {
    width: 100%;
}

.pd-bidang__galat {
    margin: 0.35rem 0 0;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-danger-500, var(--hcis-red-500));
    display: inline-flex;
    align-items: center;
    line-height: 1.3;
}

.pd-bidang__phone-prepend {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.84375rem;
    font-weight: 750;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    user-select: none;
    padding-right: 0.25rem;
}

.pd-bidang__flag-svg {
    display: block;
    width: 20px;
    height: 14px;
    border-radius: 2px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
    flex-shrink: 0;
    overflow: hidden;
}

.pd-bidang__phone-code {
    font-weight: 750;
    letter-spacing: -0.01em;
}

.pd-bidang__meta {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.pd-master-group {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.pd-master-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.71875rem;
    font-weight: 650;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    line-height: 1.25;
    letter-spacing: 0.01em;
    white-space: nowrap;
}

.pd-master-pill--terdaftar {
    background: rgba(99, 102, 241, 0.08);
    color: var(--hcis-indigo-600);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.pd-master-pill--terdaftar i {
    font-size: 0.65rem;
    color: var(--hcis-indigo-500);
}

.pd-master-pill--diubah {
    background: rgba(245, 158, 11, 0.12);
    color: var(--hcis-amber-700);
    border: 1px solid rgba(245, 158, 11, 0.28);
}

.pd-master-pill--diubah i {
    font-size: 0.65rem;
    color: var(--hcis-amber-600);
}

.pd-master-reset-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.71875rem;
    font-weight: 650;
    padding: 0.2rem 0.55rem;
    min-height: 28px;
    border-radius: 6px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-300);
    color: var(--hcis-slate-600);
    cursor: pointer;
    line-height: 1.25;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: all 0.15s ease-in-out;
    touch-action: manipulation;
}

.pd-master-reset-btn .bi {
    color: var(--hcis-indigo-500);
    font-size: 0.75rem;
}

.pd-master-reset-btn:hover {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-900);
    border-color: var(--hcis-slate-400);
    box-shadow: 0 2px 4px rgba(15, 23, 42, 0.08);
    transform: translateY(-0.5px);
}

.pd-master-reset-btn:active {
    background: var(--hcis-slate-200);
    transform: translateY(0);
    box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04);
}

/* Touch & iOS zoom optimization on Mobile */
@media (max-width: 640px) {
    :deep(input),
    :deep(select),
    :deep(.hcis-field__input),
    :deep(.hcis-select__trigger),
    :deep(.hcis-date-picker__input) {
        font-size: 16px !important;
        min-height: 44px;
    }

    .pd-master-reset-btn {
        min-height: 32px;
        padding: 0.25rem 0.6rem;
    }

    .pd-bidang__label {
        font-size: 0.8125rem;
    }
}
</style>
