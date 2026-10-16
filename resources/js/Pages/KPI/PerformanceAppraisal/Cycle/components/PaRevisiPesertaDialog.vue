<template>
    <!--
     | REVISI PESERTA SESUDAH DIKUNCI — cari orangnya, baca dampaknya, pilih satu tindakan,
     | tulis alasannya. Server yang memutuskan tindakan mana yang boleh (dan kenapa tidak);
     | layar ini tidak menebak.
     -->
    <HcisDialog
        :model-value="modelValue"
        title="Revisi Peserta"
        kicker="SESUDAH SIKLUS DIKUNCI"
        icon="person-gear"
        size="md"
        data-uji="dialog-revisi"
        @update:model-value="tutup"
    >
        <div class="pa-rv">
            <!-- STEP 1: PENCARIAN KARYAWAN -->
            <template v-if="!terpilih">
                <div class="pa-rv__search-section">
                    <div class="pa-rv__search-header">
                        <span class="pa-rv__step-pill">Langkah 1 dari 2</span>
                        <h4 class="pa-rv__step-title">Pilih Karyawan yang Ingin Direvisi</h4>
                        <p class="pa-rv__step-desc">
                            Cari karyawan untuk menyesuaikan Level PA terbaru, mengikutkan kembali yang dikecualikan, atau menambahkan peserta susulan.
                        </p>
                    </div>

                    <HcisField
                        id="revisi-cari"
                        v-model="kata"
                        size="sm"
                        clearable
                        placeholder="Ketik nama atau kode NIK karyawan…"
                        leading-icon="search"
                        data-uji="revisi-cari"
                    />

                    <div v-if="mencari" class="pa-rv__loading-state">
                        <HcisIcon name="arrow-repeat" size="xs" animation="spin" color="brand" />
                        <span>Mencari data karyawan…</span>
                    </div>

                    <div v-else-if="kata.trim().length >= 2 && !hasil.length" class="pa-rv__empty-search">
                        <HcisIcon name="search" size="sm" color="muted" />
                        <span>Tidak ada karyawan yang cocok dengan kata kunci "{{ kata }}".</span>
                    </div>

                    <ul v-if="hasil.length" class="pa-rv__hasil">
                        <li v-for="k in hasil" :key="`${k.kode_perusahaan}|${k.kode_karyawan}`">
                            <button
                                type="button"
                                class="pa-rv__orang-btn"
                                :data-uji="`revisi-pilih-${k.kode_karyawan}`"
                                @click="pilih(k)"
                            >
                                <div class="pa-rv__avatar">
                                    {{ (k.nama || k.kode_karyawan || '?').charAt(0).toUpperCase() }}
                                </div>
                                <div class="pa-rv__orang-info">
                                    <strong class="pa-rv__orang-nama">{{ k.nama || k.kode_karyawan }}</strong>
                                    <div class="pa-rv__orang-meta">
                                        <span class="pa-code-pill">{{ k.kode_karyawan }}</span>
                                        <span v-if="k.nama_level" class="pa-level-tag">{{ k.nama_level }}</span>
                                    </div>
                                </div>
                                <HcisIcon name="chevron-right" size="2xs" color="muted" class="pa-rv__arrow" />
                            </button>
                        </li>
                    </ul>
                </div>
            </template>

            <!-- STEP 2: DETAIL KEADAAN & PILIHAN TINDAKAN -->
            <template v-else>
                <!-- Profile Banner Card -->
                <div class="pa-rv__profile-card">
                    <div class="pa-rv__profile-main">
                        <div class="pa-rv__avatar is-lg">
                            {{ (terpilih.nama || terpilih.kode_karyawan || '?').charAt(0).toUpperCase() }}
                        </div>
                        <div class="pa-rv__profile-info">
                            <strong class="pa-rv__profile-name">{{ terpilih.nama || terpilih.kode_karyawan }}</strong>
                            <div class="pa-rv__profile-tags">
                                <span class="pa-code-pill">{{ terpilih.kode_karyawan }}</span>
                                <span v-if="terpilih.nama_level" class="pa-level-tag">{{ terpilih.nama_level }}</span>
                            </div>
                        </div>
                    </div>
                    <HcisButton
                        label="Ganti orang"
                        variant="ghost"
                        size="xs"
                        leading-icon="arrow-left"
                        @click="ulangi"
                    />
                </div>

                <div v-if="memuatPratinjau" class="pa-rv__loading-state">
                    <HcisIcon name="arrow-repeat" size="xs" animation="spin" color="brand" />
                    <span>Menganalisis status &amp; penugasan peserta…</span>
                </div>

                <template v-else-if="pratinjau">
                    <!-- Diagnostic State Card -->
                    <div class="pa-rv__status-box">
                        <div class="pa-rv__status-header">
                            <HcisIcon name="info-circle-fill" size="xs" color="brand" />
                            <span class="pa-rv__status-label">Kondisi Peserta Saat Ini</span>
                        </div>
                        <p class="pa-rv__keadaan" data-uji="revisi-keadaan">{{ kalimatKeadaan }}</p>
                    </div>

                    <!-- Action Radios -->
                    <div v-if="!hasilRevisi" class="pa-rv__opsi-wrap">
                        <div class="pa-rv__opsi-header">
                            <span class="pa-rv__step-pill">Langkah 2 dari 2</span>
                            <h4 class="pa-rv__step-title">Pilih Tindakan Revisi</h4>
                        </div>

                        <div class="pa-rv__opsi" role="radiogroup" aria-label="Tindakan revisi">
                            <button
                                v-for="o in opsi"
                                :key="o.kode"
                                type="button"
                                class="pa-rv__pilihan"
                                :class="{ 'is-dipilih': tindakan === o.kode, 'is-mati': !o.boleh }"
                                :disabled="!o.boleh"
                                role="radio"
                                :aria-checked="tindakan === o.kode"
                                :data-uji="`revisi-opsi-${o.kode}`"
                                @click="tindakan = o.kode"
                            >
                                <div class="pa-rv__pilihan-top">
                                    <div class="pa-rv__radio-indicator">
                                        <span v-if="tindakan === o.kode" class="pa-rv__radio-dot" />
                                    </div>
                                    <div class="pa-rv__pilihan-head">
                                        <strong class="pa-rv__pilihan-title">{{ o.judul }}</strong>
                                        <span v-if="!o.boleh" class="pa-rv__badge-disabled">Tidak Tersedia</span>
                                    </div>
                                </div>
                                <div class="pa-rv__pilihan-desc">
                                    <span>{{ o.boleh ? o.dampak : o.sebab }}</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Input Alasan -->
                    <div v-if="tindakan && !hasilRevisi" class="pa-rv__alasan-wrap">
                        <label class="pa-rv__alasan-label" for="revisi-alasan-input">
                            <span>Alasan Revisi <strong class="text-danger">*</strong></span>
                            <small>Wajib dicatat untuk audit log sistem (min. 5 karakter)</small>
                        </label>
                        <HcisTextarea
                            id="revisi-alasan-input"
                            v-model="alasan"
                            :rows="2"
                            placeholder="Contoh: Koreksi level dari HR per SK Direksi tanggal 10 Oktober…"
                            data-uji="revisi-alasan"
                        />
                    </div>

                    <!-- Hasil Revisi Success Alert -->
                    <HcisAlert
                        v-if="hasilRevisi"
                        variant="success"
                        appearance="glass"
                        size="sm"
                        title="Revisi Berhasil Disimpan"
                        :description="kalimatHasil"
                        icon="check-circle-fill"
                        data-uji="revisi-hasil"
                    />
                </template>

                <!-- Galat Alert -->
                <HcisAlert
                    v-if="galat"
                    variant="danger"
                    appearance="subtle"
                    size="sm"
                    :description="galat"
                    icon="exclamation-triangle-fill"
                    data-uji="revisi-galat"
                />
            </template>
        </div>

        <template #footer>
            <HcisButton :label="hasilRevisi ? 'Tutup' : 'Batal'" variant="secondary" size="sm" @click="tutup(false)" />
            <HcisButton
                v-if="hasilRevisi"
                label="Revisi orang lain"
                variant="primary"
                size="sm"
                leading-icon="person-gear"
                @click="ulangi"
            />
            <HcisButton
                v-else-if="terpilih"
                label="Jalankan Revisi"
                variant="primary"
                size="sm"
                leading-icon="play-fill"
                :disabled="!tindakan || alasan.trim().length < 5"
                :loading="menjalankan"
                data-uji="revisi-jalankan"
                @click="jalankan"
            />
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { HcisAlert, HcisButton, HcisDialog, HcisField, HcisIcon, HcisTextarea } from '@/components/ui';
import paApi from '../../api/paApi.js';

defineOptions({ name: 'PaRevisiPesertaDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    cycleId: { type: [Number, String], required: true },
    karyawanAwal: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'selesai']);

watch(
    () => props.modelValue,
    (buka) => {
        if (buka && props.karyawanAwal) {
            pilih(props.karyawanAwal);
        }
    },
    { immediate: true }
);

const kata = ref('');
const hasil = ref([]);
const mencari = ref(false);
const terpilih = ref(null);
const pratinjau = ref(null);
const memuatPratinjau = ref(false);
const tindakan = ref('');
const alasan = ref('');
const menjalankan = ref(false);
const hasilRevisi = ref(null);
const galat = ref('');

let penundaCari = null;
watch(kata, (v) => {
    clearTimeout(penundaCari);
    hasil.value = [];
    if (v.trim().length < 2) return;
    penundaCari = setTimeout(() => cari(v.trim()), 350);
});

async function cari(q) {
    mencari.value = true;
    try {
        const r = await paApi.cariKaryawanPraKecuali(props.cycleId, { q, limit: 20 });
        hasil.value = r?.baris ?? [];
    } catch (e) {
        galat.value = e?.message || 'Pencarian gagal.';
    } finally {
        mencari.value = false;
    }
}

async function pilih(k) {
    terpilih.value = k;
    tindakan.value = '';
    galat.value = '';
    memuatPratinjau.value = true;
    try {
        pratinjau.value = await paApi.pratinjauRevisiPeserta(props.cycleId, k.kode_perusahaan, k.kode_karyawan);
        /* Satu-satunya yang boleh langsung terpilih — KECUALI Kecualikan: tindakan yang
           membatalkan penugasan tidak boleh terpilih tanpa admin memilihnya sendiri. */
        const boleh = Object.entries(pratinjau.value?.tindakan ?? {}).filter(([k, t]) => t.boleh && k !== 'KECUALIKAN');
        if (boleh.length === 1) tindakan.value = boleh[0][0];
    } catch (e) {
        galat.value = e?.message || 'Keadaan peserta tidak bisa dibaca.';
    } finally {
        memuatPratinjau.value = false;
    }
}

const kalimatKeadaan = computed(() => {
    const p = pratinjau.value;
    if (!p) return '';
    const kar = p.karyawan;
    if (!p.peserta) {
        return `Belum menjadi peserta siklus ini.${kar ? ` Level PA saat ini: ${kar.nama_level ?? '-'}.` : ' Tidak aktif di HRIS.'}`;
    }
    const bagian = [`Status: ${p.peserta.status === 'DIIKUTKAN' ? 'diikutkan' : 'dikecualikan'}`, `level di siklus: ${p.peserta.nama_level ?? '-'}`];
    if (kar && kar.id_level !== p.peserta.id_level) bagian.push(`Level PA terbaru: ${kar.nama_level ?? '-'}`);
    const d = p.dampak;
    bagian.push(`${d.penugasan_aktif} penugasan aktif (${d.penugasan_diisi} sudah diisi)`);
    return `${bagian.join(' · ')}.`;
});

const opsi = computed(() => {
    const p = pratinjau.value;
    if (!p) return [];
    const t = p.tindakan ?? {};
    const d = p.dampak ?? {};
    const level = p.karyawan?.nama_level ?? '-';
    return [
        {
            kode: 'TERAPKAN_LEVEL',
            judul: 'Terapkan Level PA terbaru',
            dampak: `${d.penugasan_aktif ?? 0} penugasan dibatalkan (jadwal HC Learn-nya dihapus), lalu dibentuk ulang dengan aturan & paket soal level ${level} dan dijadwalkan.`,
        },
        {
            kode: 'IKUTKAN_KEMBALI',
            judul: 'Ikutkan kembali',
            dampak: 'Dinilai lagi di siklus ini: penilainya dibentuk dari relasi organisasi, lalu dijadwalkan ke HC Learn.',
        },
        {
            kode: 'KECUALIKAN',
            judul: 'Kecualikan dari siklus',
            dampak: `${(d.penugasan_aktif ?? 0) - (d.penugasan_diisi ?? 0)} penugasan yang belum diisi dibatalkan (jadwal HC Learn-nya dihapus)`
                + ((d.penugasan_diisi ?? 0) > 0 ? `; ${d.penugasan_diisi} yang sudah diisi tetap tercatat.` : '.'),
        },
        {
            kode: 'TAMBAH_SUSULAN',
            judul: 'Tambah sebagai peserta susulan',
            dampak: `Masuk sebagai peserta level ${level}: penilainya dibentuk dari relasi organisasi, lalu dijadwalkan ke HC Learn.`,
        },
    ].map((o) => ({ ...o, boleh: Boolean(t[o.kode]?.boleh), sebab: t[o.kode]?.sebab || '' }))
        /* Yang tidak berlaku untuk keadaan orang ini disembunyikan, bukan dimatikan:
           susulan untuk yang sudah peserta, kecualikan untuk yang belum peserta. */
        .filter((o) => o.boleh
            || ((o.kode === 'TAMBAH_SUSULAN' ? !p.peserta : true)
                && (o.kode === 'KECUALIKAN' ? Boolean(p.peserta) : true)));
});

const kalimatHasil = computed(() => {
    const h = hasilRevisi.value;
    if (!h) return '';
    const bagian = [];
    if (h.dibatalkan) bagian.push(`${h.dibatalkan} penugasan dibatalkan`);
    if (h.tindakan !== 'KECUALIKAN') bagian.push(`${h.dibentuk} penugasan dibentuk`);
    return `${bagian.join(' · ')}. ${h.catatan}`;
});

async function jalankan() {
    menjalankan.value = true;
    galat.value = '';
    try {
        hasilRevisi.value = await paApi.jalankanRevisiPeserta(props.cycleId, {
            tindakan: tindakan.value,
            kode_perusahaan: terpilih.value.kode_perusahaan,
            kode_karyawan: terpilih.value.kode_karyawan,
            alasan: alasan.value.trim(),
        });
        emit('selesai', hasilRevisi.value);
    } catch (e) {
        galat.value = e?.message || 'Revisi gagal.';
    } finally {
        menjalankan.value = false;
    }
}

function ulangi() {
    terpilih.value = null;
    pratinjau.value = null;
    tindakan.value = '';
    alasan.value = '';
    hasilRevisi.value = null;
    galat.value = '';
}

function tutup(v) {
    if (v) return;
    emit('update:modelValue', false);
    ulangi();
    kata.value = '';
}
</script>

<style scoped>
.pa-rv {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

/* Step Header */
.pa-rv__search-section {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-rv__search-header {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-rv__step-pill {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    font-size: 0.7rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    background: #e0e7ff;
    color: #4338ca;
}

.pa-rv__step-title {
    margin: 0;
    font-size: 0.98rem;
    font-weight: 750;
    color: #0f172a;
}

.pa-rv__step-desc {
    margin: 0;
    font-size: 0.8rem;
    color: #64748b;
    line-height: 1.45;
}

/* Loading & Empty States */
.pa-rv__loading-state {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.85rem 1rem;
    background: #f8fafc;
    border-radius: 8px;
    font-size: 0.82rem;
    color: #475569;
}

.pa-rv__empty-search {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 1.25rem 1rem;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    font-size: 0.82rem;
    color: #64748b;
    justify-content: center;
}

/* Search Result List */
.pa-rv__hasil {
    display: flex;
    flex-direction: column;
    max-height: 18rem;
    margin: 0;
    padding: 0.25rem;
    overflow-y: auto;
    list-style: none;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    gap: 0.35rem;
}

.pa-rv__orang-btn {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    width: 100%;
    padding: 0.75rem 0.95rem;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    background: #ffffff;
    text-align: left;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-rv__orang-btn:hover {
    border-color: #c7d2fe;
    background: #f8faff;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.06);
}

.pa-rv__avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #e0e7ff;
    color: #4338ca;
    font-weight: 750;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-rv__avatar.is-lg {
    width: 44px;
    height: 44px;
    font-size: 1.05rem;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
}

.pa-rv__orang-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
}

.pa-rv__orang-nama {
    color: #0f172a;
    font-size: 0.9rem;
    font-weight: 700;
}

.pa-rv__orang-meta {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-code-pill {
    font-size: 0.72rem;
    font-weight: 650;
    color: #475569;
    background: #f1f5f9;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-level-tag {
    font-size: 0.72rem;
    font-weight: 650;
    color: #0369a1;
    background: #e0f2fe;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
}

.pa-rv__arrow {
    flex-shrink: 0;
}

/* Profile Banner Card */
.pa-rv__profile-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.95rem 1.15rem;
    background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
    border: 1px solid #c7d2fe;
    border-radius: 12px;
}

.pa-rv__profile-main {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.pa-rv__profile-info {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-rv__profile-name {
    font-size: 1rem;
    font-weight: 750;
    color: #0f172a;
}

.pa-rv__profile-tags {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

/* Status Box */
.pa-rv__status-box {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 0.85rem 1.05rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #3b82f6;
    border-radius: 10px;
}

.pa-rv__status-header {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-rv__status-label {
    font-size: 0.74rem;
    font-weight: 750;
    color: #3b82f6;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-rv__keadaan {
    margin: 0;
    color: #334155;
    font-size: 0.84rem;
    line-height: 1.5;
}

/* Action Radios Section */
.pa-rv__opsi-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-rv__opsi-header {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-rv__opsi {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-rv__pilihan {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.85rem 1.05rem;
    border: 1px solid #e2e8f0;
    border-radius: 11px;
    background: #ffffff;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s ease;
}

.pa-rv__pilihan:hover:not(:disabled) {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.pa-rv__pilihan.is-dipilih {
    border-color: #6366f1;
    background: #f8faff;
    border-left: 4px solid #4f46e5;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.08);
}

.pa-rv__pilihan.is-mati {
    cursor: not-allowed;
    background: #f8fafc;
    border-color: #e2e8f0;
    opacity: 0.65;
}

.pa-rv__pilihan-top {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-rv__radio-indicator {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.15s ease;
}

.pa-rv__pilihan.is-dipilih .pa-rv__radio-indicator {
    border-color: #4f46e5;
}

.pa-rv__radio-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #4f46e5;
}

.pa-rv__pilihan-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex: 1;
}

.pa-rv__pilihan-title {
    color: #0f172a;
    font-size: 0.9rem;
    font-weight: 750;
}

.pa-rv__badge-disabled {
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.pa-rv__pilihan-desc {
    padding-left: 1.8rem;
    font-size: 0.8rem;
    color: #64748b;
    line-height: 1.45;
}

.pa-rv__pilihan.is-dipilih .pa-rv__pilihan-desc {
    color: #4338ca;
}

.pa-rv__notice-tip {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.4;
    padding: 0.4rem 0.6rem;
    background: #f8fafc;
    border-radius: 6px;
}

/* Alasan Wrap */
.pa-rv__alasan-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-rv__alasan-label {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    font-size: 0.82rem;
    font-weight: 700;
    color: #0f172a;
}

.pa-rv__alasan-label small {
    font-size: 0.74rem;
    font-weight: 400;
    color: #64748b;
}

@media (max-width: 640px) {
    .pa-rv__profile-card {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
