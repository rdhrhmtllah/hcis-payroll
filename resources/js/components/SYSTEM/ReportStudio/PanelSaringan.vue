<template>
    <div class="rs-panel">
        <div class="rs-panel-head">
            <div class="rs-panel-head-title">
                <span class="sop-eyebrow">Persempit</span>
                <h6 class="sop-h mb-0">Filter Data</h6>
            </div>
            <div class="d-flex align-items-center gap-2">
                <HcisBadge
                    v-if="jumlahFilterLengkap"
                    :label="`${jumlahFilterLengkap} Filter Aktif`"
                    variant="brand"
                    size="sm"
                />
                <HcisButton
                    variant="secondary"
                    size="xs"
                    icon="bi-plus-lg"
                    label="Tambah Filter"
                    class="rs-tambah"
                    :disabled="disabled"
                    @click="tambah"
                />
            </div>
        </div>

        <!-- ══ STATUS KOSONG / BELUM ADA FILTER ══ -->
        <div v-if="!modelValue.length" class="rs-saringan-kosong">
            <i class="bi bi-info-circle-fill text-brand-600 me-2.5 fs-6"></i>
            <div>
                <strong class="d-block text-slate-700 mb-0.5">Belum ada filter aktif</strong>
                <span>Laporan akan mencakup seluruh baris data. Klik tombol <strong>«Tambah Filter»</strong> di kanan atas untuk menambahkan kriteria filter.</span>
            </div>
        </div>

        <!-- ══ DAFTAR FILTER AKTIF ══ -->
        <div v-else class="rs-saringan-list">
            <div
                v-for="(s, i) in modelValue"
                :key="i"
                class="rs-saringan-row-wrap"
            >
                <!-- AND Connector Badge between filter rows -->
                <div v-if="i > 0" class="rs-saringan-connector">
                    <HcisBadge label="DAN" variant="brand" size="xs" class="rs-connector-badge" />
                </div>

                <div class="rs-saringan">
                    <!-- KOLOM 1: PILIH FIELD -->
                    <div class="rs-saringan-col-field">
                        <HcisSelect
                            :model-value="s.field"
                            :filterable="true"
                            placeholder="Pilih field filter…"
                            size="sm"
                            @update:model-value="(v) => ubah(i, 'field', v)"
                        >
                            <HcisSelectOption
                                v-for="f in daftar"
                                :key="f.key"
                                :value="f.key"
                                :label="f.label"
                            />
                        </HcisSelect>
                    </div>

                    <!-- KOLOM 2: PILIH OPERATOR -->
                    <div class="rs-saringan-col-op">
                        <HcisSelect
                            :model-value="s.op"
                            :disabled="!s.field"
                            :clearable="false"
                            placeholder="Operator"
                            size="sm"
                            @update:model-value="(v) => ubah(i, 'op', v)"
                        >
                            <HcisSelectOption
                                v-for="op in operatorUntuk(s.field)"
                                :key="op"
                                :value="op"
                                :label="labelOp(op)"
                            />
                        </HcisSelect>
                    </div>

                    <!-- KOLOM 3: KONTROL NILAI SESUAI TIPE DATA & FIELD -->
                    <div class="rs-saringan-col-val">
                        <!-- A. TANGGAL / PERIODE -->
                        <template v-if="isDateField(s.field)">
                            <HcisDateRangePicker
                                v-if="s.op === 'antara'"
                                :model-value="nilaiSebagaiArray(s)"
                                placeholder="Pilih Rentang Tanggal..."
                                size="sm"
                                @update:model-value="(v) => ubah(i, 'nilai', v)"
                            />
                            <HcisDatePicker
                                v-else
                                :model-value="nilaiSebagaiString(s)"
                                placeholder="Pilih Tanggal..."
                                size="sm"
                                @update:model-value="(v) => ubah(i, 'nilai', v)"
                            />
                        </template>

                        <!-- B. PILIHAN DARI KATALOG
                             Satu cabang untuk SEMUA field berdaftar. Sebelumnya
                             ada sembilan cabang yang isinya sama persis, dan
                             tiap cabang menyebut kunci saringan SOP ('divisi',
                             'paket', 'kategori', …) — kunci modul bisnis yang
                             tertulis di dalam komponen milik SYSTEM. Daftarnya
                             kini datang dari katalog dataset, jadi modul
                             berikutnya tidak perlu menyentuh berkas ini. -->
                        <template v-else-if="opsiField(s.field).length">
                            <input
                                v-if="s.op === 'mengandung'"
                                :value="nilaiSebagaiString(s)"
                                type="text"
                                class="rs-saringan-input"
                                :placeholder="placeholderUntuk(s)"
                                @input="ubah(i, 'nilai', $event.target.value)"
                            />
                            <HcisSelect
                                v-else
                                :model-value="s.op === 'salah_satu' ? nilaiSebagaiArray(s) : nilaiTunggal(s)"
                                :multiple="s.op === 'salah_satu'"
                                :filterable="true"
                                :placeholder="`Pilih ${labelField(s.field)}...`"
                                size="sm"
                                @update:model-value="(v) => ubah(i, 'nilai', v)"
                            >
                                <HcisSelectOption
                                    v-for="o in opsiField(s.field)"
                                    :key="String(o.value)"
                                    :value="o.value"
                                    :label="o.label"
                                />
                            </HcisSelect>
                        </template>

                        <!-- L. DEFAULT / TEXT INPUT -->
                        <template v-else>
                            <input
                                :value="nilaiTeks(s)"
                                :disabled="!s.field"
                                class="rs-saringan-input"
                                :placeholder="placeholderUntuk(s)"
                                @input="ubah(i, 'nilai', pecah($event.target.value, s.op))"
                            />
                        </template>
                    </div>

                    <!-- TOMBOL HAPUS -->
                    <HcisButton
                        variant="ghost"
                        size="xs"
                        icon="bi-trash3"
                        class="rs-hapus"
                        title="Hapus filter ini"
                        aria-label="Hapus filter"
                        @click="hapus(i)"
                    />
                </div>
            </div>
        </div>

        <div v-if="galat" class="rs-galat" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>{{ galat }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisDatePicker from '@/components/ui/HcisDatePicker.vue';
import HcisDateRangePicker from '@/components/ui/HcisDateRangePicker.vue';
import HcisSelect from '@/components/ui/HcisSelect.vue';
import HcisSelectOption from '@/components/ui/HcisSelectOption.vue';
import { apakahSaringanLengkap } from './composables/useDefinisi';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    /** Entri katalog filter: { key, label, tipe, operator[], wajibSaatJalan } */
    daftar: { type: Array, default: () => [] },
    galat: { type: String, default: '' },
    opsiFilter: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const jumlahFilterLengkap = computed(() =>
    props.modelValue.filter(apakahSaringanLengkap).length
);

/**
 * Pilihan isian per kunci saringan, dinormalkan ke { value, label }.
 *
 * Dua bentuk diterima: daftar teks polos (nilai = labelnya sendiri) dan daftar
 * berpasangan. Pasangan itu yang penting — saringan paket dan kategori
 * menyaring kolom ID, jadi yang dikirim harus ID sementara yang dibaca orang
 * adalah namanya. Menyamakan keduanya (yang terjadi sebelum ini) mengirim teks
 * ke kolom int dan menggagalkan seluruh query dengan galat konversi.
 */
const opsi = computed(() => {
    const hasil = {};
    for (const [kunci, daftar] of Object.entries(props.opsiFilter ?? {})) {
        if (!Array.isArray(daftar)) continue;
        hasil[kunci] = daftar
            .map((o) =>
                o !== null && typeof o === 'object'
                    ? { value: o.value, label: String(o.label ?? o.value) }
                    : { value: o, label: String(o) }
            )
            .filter((o) => o.value !== null && o.value !== undefined && o.value !== '');
    }
    return hasil;
});

const opsiField = (field) => opsi.value[field] ?? [];

const labelField = (field) => props.daftar.find((f) => f.key === field)?.label ?? 'Nilai';

const LABEL_OP = {
    sama_dengan: 'Sama dengan (=)',
    tidak_sama: 'Tidak sama dengan (≠)',
    salah_satu: 'Salah satu dari (In)',
    antara: 'Rentang antara (Between)',
    mengandung: 'Mengandung teks (Like)',
    lebih_besar: 'Lebih dari (>)',
    lebih_besar_sama: 'Lebih dari sama dengan (≥)',
    lebih_kecil: 'Kurang dari (<)',
    lebih_kecil_sama: 'Kurang dari sama dengan (≤)',
};

const labelOp = (op) => LABEL_OP[op] ?? op;

function operatorUntuk(field) {
    return props.daftar.find((f) => f.key === field)?.operator ?? [];
}

/* Tipe dibaca dari katalog, bukan dari daftar kunci. Daftar kunci SOP yang
   dulu tertulis di sini berlebihan sekaligus salah tempat: ia mengunci
   komponen SYSTEM ke nama field satu modul. */
function isDateField(field) {
    return props.daftar.find((f) => f.key === field)?.tipe === 'tanggal';
}

function nilaiSebagaiArray(s) {
    if (Array.isArray(s.nilai)) return s.nilai;
    if (typeof s.nilai === 'string' && s.nilai) {
        return s.nilai.split(',').map((x) => x.trim()).filter(Boolean);
    }
    return [];
}

function nilaiSebagaiString(s) {
    if (Array.isArray(s.nilai)) return s.nilai.join(', ');
    return s.nilai || '';
}

/**
 * Nilai tunggal untuk HcisSelect, TANPA dikonversi ke teks: opsi paket dan
 * kategori bernilai angka, dan mencocokkan '12' dengan 12 membuat pilihan yang
 * tersimpan tidak pernah terlihat terpilih saat template dibuka kembali.
 */
function nilaiTunggal(s) {
    if (Array.isArray(s.nilai)) return s.nilai[0] ?? '';
    return s.nilai ?? '';
}

function placeholderUntuk(s) {
    if (!s.field) return 'Pilih field terlebih dahulu';
    if (s.op === 'antara') return 'mis. 2026-01-01, 2026-12-31';
    if (s.op === 'salah_satu') return 'Pilih satu atau lebih opsi...';
    return 'Ketik nilai filter…';
}

const nilaiTeks = (s) => (Array.isArray(s.nilai) ? s.nilai.join(', ') : (s.nilai ?? ''));

const pecah = (teks, op) =>
    op === 'salah_satu' || op === 'antara'
        ? teks.split(',').map((x) => x.trim()).filter(Boolean)
        : teks;

function tambah() {
    emit('update:modelValue', [...props.modelValue, { field: '', op: '', nilai: '' }]);
}

function hapus(i) {
    emit('update:modelValue', props.modelValue.filter((_, k) => k !== i));
}

function ubah(i, kunci, nilai) {
    const baru = props.modelValue.map((s, k) => (k === i ? { ...s, [kunci]: nilai } : s));

    if (kunci === 'field') {
        const ops = operatorUntuk(nilai);
        baru[i].op = ops[0] ?? '';
        if (baru[i].op === 'antara' || baru[i].op === 'salah_satu') {
            baru[i].nilai = [];
        } else {
            // Field berdaftar dua-tiga pilihan (mis. Wajib, Lulus) langsung
            // terisi pilihan pertama supaya saringan tidak lahir "kosong" dan
            // diam-diam diabaikan compiler. Diturunkan dari katalog, bukan dari
            // nama field yang dihafal komponen.
            const daftarOpsi = opsiField(nilai);
            baru[i].nilai = daftarOpsi.length > 0 && daftarOpsi.length <= 3 ? daftarOpsi[0].value : '';
        }
    } else if (kunci === 'op') {
        if (nilai === 'antara' || nilai === 'salah_satu') {
            if (!Array.isArray(baru[i].nilai)) {
                baru[i].nilai = baru[i].nilai ? [baru[i].nilai] : [];
            }
        }
    }

    emit('update:modelValue', baru);
}
</script>

<style scoped>
.rs-panel {
    margin-bottom: 1.35rem;
}

.rs-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}

.rs-panel-head-title {
    display: flex;
    flex-direction: column;
}

.rs-saringan-kosong {
    font-size: 0.8rem;
    color: var(--hcis-text-subtle, #475569);
    padding: 0.85rem 1rem;
    background: var(--hcis-surface-soft, #f8fafc);
    border: 1px dashed var(--hcis-border-medium, #cbd5e1);
    border-radius: var(--hcis-radius-lg, 12px);
    display: flex;
    align-items: flex-start;
    line-height: 1.45;
}

.rs-tambah {
    display: inline-flex;
    align-items: center;
    border: 1px solid var(--hcis-border-brand, #c7d2fe);
    background: var(--hcis-surface-brand, #eef2ff);
    color: var(--hcis-brand-700, #4338ca);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.74rem;
    font-weight: 600;
    padding: 0.25rem 0.65rem;
    cursor: pointer;
    transition: all 150ms ease;
}

.rs-tambah:hover {
    background: #e0e7ff;
    border-color: var(--hcis-brand-500, #6366f1);
}

.rs-saringan-list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    width: 100%;
}

.rs-saringan-row-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    width: 100%;
}

.rs-saringan-connector {
    display: flex;
    align-items: center;
    padding-left: 1.5rem;
    position: relative;
}

.rs-saringan-connector::before {
    content: '';
    position: absolute;
    left: 0.75rem;
    top: -0.45rem;
    bottom: -0.45rem;
    width: 2px;
    background: var(--hcis-border-subtle, #e2e8f0);
}

.rs-connector-badge {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
    background: var(--hcis-surface-brand, #eef2ff);
    color: var(--hcis-brand-700, #4338ca);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.rs-saringan {
    display: grid;
    grid-template-columns: minmax(105px, 1.15fr) minmax(85px, 0.95fr) minmax(125px, 1.5fr) auto;
    gap: 0.45rem;
    align-items: center;
    padding: 0.45rem 0.65rem;
    background: var(--hcis-surface, #ffffff);
    border: 1px solid var(--hcis-border-medium, #cbd5e1);
    border-radius: var(--hcis-radius-lg, 10px);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
    transition: border-color 150ms ease, box-shadow 150ms ease;
    min-width: 0;
    width: 100%;
}

.rs-saringan:hover {
    border-color: var(--hcis-brand-500, #6366f1);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
}

.rs-saringan-col-field,
.rs-saringan-col-op,
.rs-saringan-col-val {
    min-width: 0;
    width: 100%;
    overflow: hidden;
}

.rs-saringan-col-field :deep(.hcis-select__trigger),
.rs-saringan-col-op :deep(.hcis-select__trigger),
.rs-saringan-col-val :deep(.hcis-select__trigger),
.rs-saringan-col-val :deep(.hcis-date-picker-trigger) {
    width: 100%;
    min-width: 0;
}

.rs-saringan-col-field :deep(.hcis-display-text),
.rs-saringan-col-op :deep(.hcis-display-text),
.rs-saringan-col-val :deep(.hcis-display-text) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
    width: 100%;
}

.rs-saringan-input {
    width: 100%;
    height: 2.1rem;
    padding: 0.35rem 0.65rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid var(--hcis-border-medium, #cbd5e1);
    background-color: var(--hcis-surface, #ffffff);
    font-size: 0.8rem;
    color: var(--hcis-text-strong, #1e293b);
    outline: none;
    transition: border-color 150ms ease, box-shadow 150ms ease;
    text-overflow: ellipsis;
}

.rs-saringan-input:focus {
    border-color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 0 0 2px var(--hcis-focus-ring, rgba(99, 102, 241, 0.2));
}

.rs-saringan-input:disabled {
    background-color: var(--hcis-surface-soft, #f8fafc);
    color: var(--hcis-text-muted, #94a3b8);
    cursor: not-allowed;
}

.rs-hapus {
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-text-muted, #94a3b8);
    padding: 0.35rem 0.45rem;
    border-radius: var(--hcis-radius-md, 6px);
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 150ms ease;
    flex-shrink: 0;
}

.rs-hapus:hover {
    color: var(--hcis-danger-600, #dc2626);
    background: #fef2f2;
    border-color: #fecaca;
}

.rs-galat {
    margin-top: 0.6rem;
    padding: 0.45rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #fef2f2;
    border: 1px solid #fecaca;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--hcis-danger-700, #b91c1c);
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

@media (max-width: 720px) {
    .rs-saringan {
        grid-template-columns: 1fr;
    }
}
</style>
