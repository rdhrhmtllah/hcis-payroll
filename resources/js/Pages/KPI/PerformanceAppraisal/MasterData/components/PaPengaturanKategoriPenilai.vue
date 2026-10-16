<template>
    <section class="pa-atur-kategori" aria-labelledby="pa-atur-kategori-judul">
        <header class="pa-atur-kategori__header">
            <div class="pa-atur-kategori__title-group">
                <div class="pa-atur-kategori__icon-box">
                    <HcisIcon name="diagram-3-fill" size="xs" />
                </div>
                <div>
                    <div class="pa-atur-kategori__kicker-row">
                        <span class="pa-atur-kategori__kicker">KONFIGURASI ALUR PENILAI</span>
                        <HcisBadge label="Berlaku Seketika" variant="success" appearance="glass" size="xs" dot />
                    </div>
                    <h3 id="pa-atur-kategori-judul" class="pa-atur-kategori__title">
                        Urutan pengisian &amp; Kalibrasi Penilai
                    </h3>
                    <p class="pa-atur-kategori__desc">
                        Urutan menentukan langkah wizard penilai. Kalibrasi Penilai mengizinkan penilai mengubah skornya sebelum
                        mengirim, dengan skor asli tetap tercatat. Keduanya
                        <strong>berlaku seketika</strong>, termasuk pada siklus yang sedang berjalan.
                    </p>
                </div>
            </div>
        </header>

        <HcisAlert v-if="galat" variant="danger" icon="exclamation-octagon-fill" :title="galat" />

        <ol class="pa-atur-kategori__daftar">
            <li v-for="(k, i) in urut" :key="k.id" class="pa-atur-kategori__baris">
                <!-- Step Order Medallion -->
                <div class="pa-atur-kategori__step-badge">
                    <span class="pa-atur-kategori__nomor">{{ i + 1 }}</span>
                </div>

                <!-- Category Icon & Details -->
                <div class="pa-atur-kategori__info-col">
                    <div class="pa-atur-kategori__avatar" :class="avatarClass(k.kode)">
                        <HcisIcon :name="ikonKategori(k.kode)" size="2xs" />
                    </div>

                    <div class="pa-atur-kategori__meta">
                        <div class="pa-atur-kategori__name-row">
                            <span class="pa-atur-kategori__nama">{{ k.nama }}</span>
                            <HcisBadge :label="k.kode" variant="neutral" appearance="subtle" size="2xs" />
                        </div>
                        <span class="pa-atur-kategori__sub">
                            {{ k.deskripsi || deskripsiOtomatis(k.kode) }}
                        </span>
                    </div>
                </div>

                <!-- Re-order Up/Down Controls -->
                <div class="pa-atur-kategori__geser">
                    <HcisButton
                        icon-only
                        leading-icon="arrow-up"
                        size="sm"
                        variant="secondary"
                        :disabled="i === 0 || sibuk"
                        :aria-label="`Naikkan ${k.nama}`"
                        title="Geser urutan ke atas"
                        @click="geser(i, -1)"
                    />
                    <HcisButton
                        icon-only
                        leading-icon="arrow-down"
                        size="sm"
                        variant="secondary"
                        :disabled="i === urut.length - 1 || sibuk"
                        :aria-label="`Turunkan ${k.nama}`"
                        title="Geser urutan ke bawah"
                        @click="geser(i, 1)"
                    />
                </div>

                <!-- Calibration Switcher -->
                <div class="pa-atur-kategori__kalibrasi">
                    <div class="pa-atur-kategori__kalibrasi-label">
                        <span class="pa-kalibrasi-text">{{ k.boleh_kalibrasi ? 'Boleh kalibrasi' : 'Hanya tinjau' }}</span>
                        <small class="pa-kalibrasi-hint">
                            {{ k.boleh_kalibrasi ? 'Skor dapat disesuaikan penilai' : 'Hanya melihat skor akhir' }}
                        </small>
                    </div>
                    <HcisSwitch
                        :model-value="k.boleh_kalibrasi"
                        :disabled="sibuk"
                        size="sm"
                        :aria-label="`Kalibrasi Penilai untuk ${k.nama}`"
                        @update:model-value="(v) => setelKalibrasi(k, v)"
                    />
                </div>
            </li>
        </ol>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';

import { HcisAlert, HcisBadge, HcisButton, HcisIcon, HcisSwitch } from '@/components/ui';

import paApi from '../../api/paApi.js';

defineOptions({ name: 'PaPengaturanKategoriPenilai' });

const props = defineProps({
    kategori: { type: Array, default: () => [] },
});

const emit = defineEmits(['berubah']);

const sibuk = ref(false);
const galat = ref('');

// Sama dengan server: tanpa urutan jatuh ke belakang, lalu menurut id.
const urut = computed(() =>
    [...props.kategori].sort(
        (a, b) =>
            (a.urutan_pengisian ?? Number.MAX_SAFE_INTEGER) - (b.urutan_pengisian ?? Number.MAX_SAFE_INTEGER) ||
            a.id - b.id,
    ),
);

function ikonKategori(kode) {
    const peta = {
        ATASAN: 'person-badge',
        BAWAHAN: 'diagram-3-fill',
        PEER: 'people-fill',
        REKAN: 'people-fill',
        SELF: 'person-circle',
        DIRI_SENDIRI: 'person-circle',
    };
    return peta[String(kode).toUpperCase()] || 'person-fill';
}

function avatarClass(kode) {
    const k = String(kode).toUpperCase();
    if (k === 'SELF' || k === 'DIRI_SENDIRI') return 'is-self';
    if (k === 'ATASAN') return 'is-atasan';
    if (k === 'BAWAHAN') return 'is-bawahan';
    if (k === 'PEER' || k === 'REKAN') return 'is-peer';
    return 'is-brand';
}

function deskripsiOtomatis(kode) {
    const k = String(kode).toUpperCase();
    if (k === 'SELF' || k === 'DIRI_SENDIRI') return 'Evaluasi mandiri atas target dan kompetensi sendiri';
    if (k === 'ATASAN') return 'Evaluasi vertikal ke atas terhadap atasan langsung';
    if (k === 'PEER' || k === 'REKAN') return 'Evaluasi horizontal rekan sejawat dalam divisi/fungsi';
    if (k === 'BAWAHAN') return 'Evaluasi manajerial terhadap bawahan langsung dalam tim';
    return 'Kategori penilai evaluasi kinerja 360°';
}

async function jalankan(kerja) {
    sibuk.value = true;
    galat.value = '';

    try {
        await kerja();
        emit('berubah');
    } catch (e) {
        galat.value = e?.message || 'Perubahan gagal disimpan.';
    } finally {
        sibuk.value = false;
    }
}

/** Mengirim DAFTAR LENGKAP: server menulisnya dalam satu pernyataan supaya indeks unik tidak tertabrak. */
function geser(indeks, arah) {
    const daftar = urut.value.map((k) => k.kode);
    const tujuan = indeks + arah;
    if (tujuan < 0 || tujuan >= daftar.length) return Promise.resolve();
    [daftar[indeks], daftar[tujuan]] = [daftar[tujuan], daftar[indeks]];

    return jalankan(() => paApi.tataUrutanKategoriPenilai(daftar));
}

function setelKalibrasi(k, boleh) {
    return jalankan(() => paApi.setelBolehKalibrasiKategori(k.id, Boolean(boleh)));
}

defineExpose({ geser, setelKalibrasi });
</script>

<style scoped>
.pa-atur-kategori {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
    padding: 1.25rem 1.5rem;
    border: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
    border-radius: var(--hcis-radius-xl, 16px);
    background: var(--hcis-surface, #ffffff);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
}

.pa-atur-kategori__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.pa-atur-kategori__title-group {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
}

.pa-atur-kategori__icon-box {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: var(--hcis-surface-brand-subtle, rgba(37, 99, 235, 0.1));
    color: var(--hcis-brand, #2563eb);
    flex-shrink: 0;
}

.pa-atur-kategori__kicker-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-atur-kategori__kicker {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: var(--hcis-brand, #2563eb);
    text-transform: uppercase;
}

.pa-atur-kategori__title {
    margin: 0.15rem 0 0;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.3;
}

.pa-atur-kategori__desc {
    margin: 0.25rem 0 0;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.8rem;
    line-height: 1.45;
}

.pa-atur-kategori__daftar {
    display: grid;
    gap: 0.75rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pa-atur-kategori__baris {
    display: grid;
    grid-template-columns: 2.5rem minmax(0, 1fr) auto auto;
    gap: 1rem;
    align-items: center;
    padding: 0.875rem 1.15rem;
    background: var(--hcis-surface-soft, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.06));
    border-radius: var(--hcis-radius-lg, 12px);
    transition: all 0.2s ease;
}

.pa-atur-kategori__baris:hover {
    background: #ffffff;
    border-color: var(--hcis-brand-subtle, rgba(37, 99, 235, 0.3));
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.pa-atur-kategori__step-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: var(--hcis-surface, #ffffff);
    border: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.1));
    font-weight: 800;
    font-size: 0.825rem;
    color: var(--hcis-brand, #2563eb);
}

.pa-atur-kategori__info-col {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.pa-atur-kategori__avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    color: #ffffff;
    flex-shrink: 0;
}

.pa-atur-kategori__avatar.is-self {
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
}

.pa-atur-kategori__avatar.is-atasan {
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
}

.pa-atur-kategori__avatar.is-bawahan {
    background: linear-gradient(135deg, #10b981, #059669);
}

.pa-atur-kategori__avatar.is-peer {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.pa-atur-kategori__avatar.is-brand {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
}

.pa-atur-kategori__meta {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.pa-atur-kategori__name-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-atur-kategori__nama {
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.9rem;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.pa-atur-kategori__sub {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
    overflow-wrap: anywhere;
}

.pa-atur-kategori__geser {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-atur-kategori__kalibrasi {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding-left: 0.875rem;
    border-left: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
}

.pa-atur-kategori__kalibrasi-label {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    text-align: right;
}

.pa-kalibrasi-text {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-kalibrasi-hint {
    font-size: 0.675rem;
    color: var(--hcis-text-muted, #64748b);
}

@media (max-width: 768px) {
    .pa-atur-kategori__baris {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .pa-atur-kategori__kalibrasi {
        border-left: none;
        border-top: 1px solid var(--hcis-border-subtle, rgba(0, 0, 0, 0.08));
        padding-left: 0;
        padding-top: 0.5rem;
        justify-content: space-between;
    }

    .pa-atur-kategori__kalibrasi-label {
        align-items: flex-start;
        text-align: left;
    }
}
</style>
