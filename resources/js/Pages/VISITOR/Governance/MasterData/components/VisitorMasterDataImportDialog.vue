<!--
    Import master data permit: pilih berkas -> dry run -> eksekusi.

    Tombol Import hanya hidup setelah server menilai berkasnya. Angka yang
    ditampilkan datang dari penilaian itu, bukan dari pembacaan berkas di
    browser: yang menentukan baris mana ditolak adalah isi basis data, dan
    browser tidak bisa mengetahuinya tanpa berbohong.
-->
<template>
    <HcisDialog
        :model-value="modelValue"
        :title="hasil ? 'Import selesai' : 'Import Master Data Permit'"
        :description="deskripsi"
        :kicker="hasil ? 'Hasil' : (pratinjau ? 'Pratinjau Dry Run' : 'Dry run')"
        :icon="hasil ? 'check-circle-fill' : 'upload'"
        sentiment="purple"
        variant="glass"
        size="lg"
        sticky-footer
        :close-on-overlay="!sibuk"
        :close-on-esc="!sibuk"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="visitor-master-import">
            <!-- Alert Galat Global -->
            <HcisAlert
                v-if="galat"
                variant="danger"
                :title="galat.message"
                :description="galat.requestId ? `Kode: ${galat.errorCode} · Request ID: ${galat.requestId}` : galat.errorCode"
            />

            <!-- Banner Hasil Eksekusi Selesai -->
            <div v-if="hasil" class="visitor-master-import__success-hero">
                <div class="visitor-master-import__success-icon">
                    <HcisIcon name="check-circle-fill" size="lg" />
                </div>
                <div class="visitor-master-import__success-content">
                    <h4 class="visitor-master-import__success-title">Import Data Berhasil Diselesaikan</h4>
                    <p class="visitor-master-import__success-desc">
                        Sebanyak <strong>{{ hasil.ringkas.ditambah }} baris</strong> baru berhasil ditambahkan ke master data.
                        Baris yang sudah terdaftar dilewati secara aman tanpa menimpa data yang ada.
                    </p>
                </div>
            </div>

            <!-- State 1: Dropzone Pemilihan Berkas -->
            <div v-if="!berkas" class="visitor-master-import__pilih">
                <label
                    class="visitor-master-import__drop"
                    :class="{ 'is-dragging': isDragging }"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="tanganiDrop"
                >
                    <input
                        ref="inputRef"
                        type="file"
                        accept=".xlsx"
                        class="visitor-master-import__input"
                        @change="pilihBerkas"
                    />
                    <div class="visitor-master-import__drop-icon-box">
                        <HcisIcon name="file-earmark-arrow-up" size="lg" class="visitor-master-import__drop-icon" />
                    </div>
                    <div class="visitor-master-import__drop-text-group">
                        <span class="visitor-master-import__drop-judul">Pilih berkas .xlsx</span>
                        <span class="visitor-master-import__drop-teks">
                            Tarik & lepas berkas ke sini, atau klik untuk menelusuri dari perangkat Anda.
                            Bentuknya sama dengan berkas hasil unduh: satu lembar per master, kolom "No" diabaikan. Maksimal 10 MB.
                        </span>
                    </div>

                    <div class="visitor-master-import__drop-capsules">
                        <span class="visitor-master-import__capsule">
                            <HcisIcon name="filetype-xlsx" size="2xs" /> Format .xlsx
                        </span>
                        <span class="visitor-master-import__capsule">
                            <HcisIcon name="hdd" size="2xs" /> Maks. 10 MB
                        </span>
                        <span class="visitor-master-import__capsule">
                            <HcisIcon name="shield-check" size="2xs" /> Dry-run otomatis
                        </span>
                    </div>
                </label>

                <!-- Kartu Promo / Tautan Templat -->
                <div class="visitor-master-import__template-card">
                    <div class="visitor-master-import__template-icon">
                        <HcisIcon name="file-earmark-spreadsheet" size="sm" />
                    </div>
                    <div class="visitor-master-import__template-content">
                        <p class="visitor-master-import__template-title">Belum memiliki berkas sesuai format?</p>
                        <p class="visitor-master-import__template-desc">
                            Unduh templat resmi Master Data Permit yang sudah memiliki nama lembar dan header kolom yang tepat.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="visitor-master-import__tautan"
                        @click="$emit('minta-template')"
                    >
                        <HcisIcon name="file-earmark-arrow-down" size="xs" />
                        <span>Belum punya berkasnya? Unduh templat dulu.</span>
                    </button>
                </div>
            </div>

            <!-- State 2 & 3: Berkas Terpilih (Pratinjau / Hasil) -->
            <template v-else>
                <!-- Header Kartu Berkas Terpilih -->
                <div class="visitor-master-import__berkas">
                    <div class="visitor-master-import__berkas-icon-box">
                        <HcisIcon name="file-earmark-spreadsheet-fill" size="md" />
                    </div>
                    <div class="visitor-master-import__berkas-detail">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="visitor-master-import__nama" :title="berkas.name">{{ berkas.name }}</span>
                            <HcisBadge v-if="memuat" variant="brand" size="xs" dot pulse>Memeriksa berkas</HcisBadge>
                            <HcisBadge v-else-if="galat" variant="danger" size="xs">Gagal dinilai</HcisBadge>
                            <HcisBadge v-else-if="hasil" variant="success" size="xs">Selesai dieksekusi</HcisBadge>
                            <HcisBadge v-else-if="pratinjau" variant="purple" size="xs">Dry-run terverifikasi</HcisBadge>
                        </div>
                        <span class="visitor-master-import__berkas-meta">
                            {{ formatUkuranBerkas(berkas.size) }} · Microsoft Excel (.xlsx)
                        </span>
                    </div>
                    <HcisButton
                        v-if="!hasil"
                        variant="ghost"
                        size="xs"
                        icon="arrow-repeat"
                        :disabled="sibuk"
                        @click="bersihkan"
                    >
                        Ganti berkas
                    </HcisButton>
                </div>

                <!-- Skeleton Loader saat Memuat Penilaian Server -->
                <div v-if="memuat" class="visitor-master-import__memuat">
                    <div class="visitor-master-import__memuat-status">
                        <HcisIcon name="arrow-repeat" size="sm" class="visitor-master-import__spin" />
                        <span>Sedang menilai lembar kerja dan memvalidasi relasi data...</span>
                    </div>
                    <div class="visitor-master-import__memuat-grid">
                        <HcisSkeleton v-for="baris in 3" :key="baris" height="58px" rounded="md" />
                    </div>
                    <HcisSkeleton height="160px" rounded="lg" />
                </div>

                <!-- Hasil Penilaian / Laporan -->
                <template v-else-if="laporan">
                    <!-- Kartu Ringkasan Metrik (KPI Pods) -->
                    <div class="visitor-master-import__ringkas">
                        <!-- Pod Ditambahkan -->
                        <div class="visitor-master-import__pil visitor-master-import__pil--tambah">
                            <div class="visitor-master-import__pil-icon">
                                <HcisIcon name="plus-circle-fill" size="sm" />
                            </div>
                            <div class="visitor-master-import__pil-info">
                                <span class="visitor-master-import__pil-label">
                                    {{ laporan.ringkas.ditambah }} {{ hasil ? 'ditambahkan' : 'akan ditambahkan' }}
                                </span>
                                <span class="visitor-master-import__pil-sub">Baris baru yang valid</span>
                            </div>
                        </div>

                        <!-- Pod Sudah Ada / Dilewati -->
                        <div class="visitor-master-import__pil visitor-master-import__pil--lewati">
                            <div class="visitor-master-import__pil-icon">
                                <HcisIcon name="check2-circle" size="sm" />
                            </div>
                            <div class="visitor-master-import__pil-info">
                                <span class="visitor-master-import__pil-label">
                                    {{ laporan.ringkas.dilewati }} sudah ada
                                </span>
                                <span class="visitor-master-import__pil-sub">Dilewati (tidak ditimpa)</span>
                            </div>
                        </div>

                        <!-- Pod Ditolak -->
                        <div
                            class="visitor-master-import__pil"
                            :class="laporan.ringkas.ditolak > 0 ? 'visitor-master-import__pil--tolak' : 'visitor-master-import__pil--aman'"
                        >
                            <div class="visitor-master-import__pil-icon">
                                <HcisIcon :name="laporan.ringkas.ditolak > 0 ? 'exclamation-octagon-fill' : 'shield-check'" size="sm" />
                            </div>
                            <div class="visitor-master-import__pil-info">
                                <span class="visitor-master-import__pil-label">
                                    {{ laporan.ringkas.ditolak }} ditolak
                                </span>
                                <span class="visitor-master-import__pil-sub">
                                    {{ laporan.ringkas.ditolak > 0 ? 'Terdapat masalah validasi' : 'Tidak ada galat' }}
                                </span>
                            </div>
                        </div>

                        <!-- Pod Dinonaktifkan (jika ada) -->
                        <div v-if="laporan.ringkas.dinonaktifkan" class="visitor-master-import__pil visitor-master-import__pil--nonaktif">
                            <div class="visitor-master-import__pil-icon">
                                <HcisIcon name="pause-circle-fill" size="sm" />
                            </div>
                            <div class="visitor-master-import__pil-info">
                                <span class="visitor-master-import__pil-label">
                                    {{ laporan.ringkas.dinonaktifkan }} masuk nonaktif
                                </span>
                                <span class="visitor-master-import__pil-sub">Status nonaktif dari berkas</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Pratinjau per Lembar -->
                    <div class="visitor-master-import__table-card">
                        <div class="visitor-master-import__table-card-header">
                            <div class="visitor-master-import__table-card-title">
                                <HcisIcon name="table" size="xs" />
                                <span>Evaluasi per Lembar Master</span>
                            </div>
                            <HcisBadge variant="neutral" size="xs">
                                {{ laporan.lembar.length }} Lembar
                            </HcisBadge>
                        </div>

                        <div class="visitor-master-import__table-scroll">
                            <table class="visitor-master-import__tabel">
                                <thead>
                                    <tr>
                                        <th scope="col">Lembar</th>
                                        <th scope="col" class="visitor-master-import__angka">Baris</th>
                                        <th scope="col" class="visitor-master-import__angka">Tambah</th>
                                        <th scope="col" class="visitor-master-import__angka">Lewati</th>
                                        <th scope="col" class="visitor-master-import__angka">Tolak</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="lembar in laporan.lembar" :key="lembar.master" class="visitor-master-import__table-row">
                                        <td>
                                            <div class="visitor-master-import__sheet-title-wrap">
                                                <HcisIcon name="file-earmark-text" size="2xs" class="visitor-master-import__sheet-icon" />
                                                <span class="visitor-master-import__sheet">{{ lembar.sheet }}</span>
                                            </div>
                                            <span v-if="!lembar.terbaca" class="visitor-master-import__catatan visitor-master-import__catatan--muted">
                                                <HcisIcon name="dash-circle" size="2xs" /> Tidak ada di berkas
                                            </span>
                                            <span v-else-if="lembar.galat" class="visitor-master-import__catatan visitor-master-import__catatan--tolak">
                                                <HcisIcon name="exclamation-circle-fill" size="2xs" /> {{ lembar.galat }}
                                            </span>
                                            <span v-else-if="lembar.contoh.length" class="visitor-master-import__catatan visitor-master-import__catatan--contoh">
                                                {{ lembar.contoh.join(', ') }}<span v-if="lembar.ditambah > lembar.contoh.length">, …</span>
                                            </span>
                                        </td>
                                        <td class="visitor-master-import__angka">
                                            <span class="visitor-master-import__num-chip">{{ lembar.baris }}</span>
                                        </td>
                                        <td class="visitor-master-import__angka">
                                            <span
                                                class="visitor-master-import__num-chip"
                                                :class="{ 'visitor-master-import__num-chip--tambah': lembar.ditambah > 0 }"
                                            >
                                                {{ lembar.ditambah }}
                                            </span>
                                        </td>
                                        <td class="visitor-master-import__angka">
                                            <span class="visitor-master-import__num-chip">{{ lembar.dilewati }}</span>
                                        </td>
                                        <td class="visitor-master-import__angka">
                                            <span
                                                class="visitor-master-import__num-chip"
                                                :class="{ 'visitor-master-import__num-chip--tolak': lembar.ditolak > 0 }"
                                            >
                                                {{ lembar.ditolak }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Panel Masalah / Baris Ditolak -->
                    <div v-if="masalah.length" class="visitor-master-import__masalah">
                        <div class="visitor-master-import__masalah-header">
                            <div class="visitor-master-import__masalah-icon">
                                <HcisIcon name="exclamation-triangle-fill" size="xs" />
                            </div>
                            <p class="visitor-master-import__masalah-judul">Baris yang ditolak</p>
                            <HcisBadge variant="danger" size="xs" class="ms-auto">
                                {{ laporan.ringkas.ditolak }} Baris
                            </HcisBadge>
                        </div>
                        <ul class="visitor-master-import__masalah-list">
                            <li
                                v-for="item in masalah"
                                :key="`${item.sheet}-${item.baris}-${item.jenis}`"
                                class="visitor-master-import__masalah-item"
                            >
                                <HcisIcon name="x-circle-fill" size="2xs" class="visitor-master-import__masalah-bullet" />
                                <span class="visitor-master-import__masalah-text">
                                    <strong>{{ item.sheet }} baris {{ item.baris }}</strong> — {{ item.pesan }}
                                </span>
                            </li>
                        </ul>
                        <p v-if="masalahLain" class="visitor-master-import__catatan visitor-master-import__catatan--lain">
                            dan {{ masalahLain }} baris lain dengan masalah serupa.
                        </p>
                    </div>

                    <!-- Catatan Nilai Bawaan Sistem -->
                    <div v-if="laporan.catatan.length" class="visitor-master-import__info-pod">
                        <div class="visitor-master-import__info-header">
                            <HcisIcon name="info-circle-fill" size="2xs" />
                            <span>Catatan Penyesuaian Kolom</span>
                        </div>
                        <ul class="visitor-master-import__info">
                            <li v-for="catatan in laporan.catatan" :key="catatan">{{ catatan }}</li>
                        </ul>
                    </div>

                    <!-- Lembar yang Diabaikan -->
                    <div v-if="laporan.lembar_diabaikan.length" class="visitor-master-import__diabaikan-pod">
                        <HcisIcon name="file-earmark-x" size="2xs" />
                        <p class="visitor-master-import__catatan mb-0">
                            Lembar yang diabaikan: {{ laporan.lembar_diabaikan.join(', ') }}.
                        </p>
                    </div>
                </template>
            </template>
        </div>

        <template #footer>
            <HcisButton variant="ghost" :disabled="sibuk" @click="tutup">
                {{ hasil ? 'Tutup' : 'Batal' }}
            </HcisButton>
            <HcisButton
                v-if="!hasil"
                variant="primary"
                icon="database-add"
                :loading="menjalankan"
                :disabled="!bolehImport"
                @click="jalankan"
            >
                {{ laporan ? `Import ${laporan.ringkas.ditambah} baris` : 'Import' }}
            </HcisButton>
            <HcisButton
                v-else
                variant="primary"
                icon="check2"
                @click="tutup"
            >
                Selesai
            </HcisButton>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { HcisAlert, HcisBadge, HcisButton, HcisDialog, HcisIcon, HcisSkeleton, hcisMessage } from '@/components/ui';
import { visitorMasterDataApi } from '../api/visitorMasterDataApi';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    baseUrl: { type: String, required: true },
    domain: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue', 'selesai', 'minta-template']);

const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

const inputRef = ref(null);
const berkas = ref(null);
const pratinjau = ref(null);
const hasil = ref(null);
const memuat = ref(false);
const menjalankan = ref(false);
const galat = ref(null);
const isDragging = ref(false);

const laporan = computed(() => hasil.value || pratinjau.value);
const sibuk = computed(() => memuat.value || menjalankan.value);
const bolehImport = computed(() => !!pratinjau.value && pratinjau.value.ringkas.ditambah > 0 && !sibuk.value);

const deskripsi = computed(() => (hasil.value
    ? 'Baris baru sudah masuk. Baris yang sudah ada tidak diubah sama sekali.'
    : 'Berkas dinilai dulu tanpa menulis apa pun. Baris yang sudah ada akan dilewati, bukan diperbarui.'));

/** Masalah seluruh lembar, diratakan supaya terbaca sebagai satu daftar kerja. */
const masalah = computed(() => (laporan.value?.lembar || []).flatMap(
    (lembar) => lembar.masalah.map((item) => ({ ...item, sheet: lembar.sheet })),
).slice(0, 20));

const masalahLain = computed(() => {
    const total = (laporan.value?.lembar || []).reduce((jumlah, lembar) => jumlah + lembar.ditolak, 0);

    return Math.max(0, total - masalah.value.length);
});

watch(() => props.modelValue, (terbuka) => {
    if (!terbuka) bersihkan();
});

function formatUkuranBerkas(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

function tanganiDrop(event) {
    isDragging.value = false;
    const files = event.dataTransfer?.files;
    if (files && files.length > 0) {
        pilihBerkas({ target: { files } });
    }
}

async function pilihBerkas(event) {
    const dipilih = event.target.files?.[0];
    if (!dipilih) return;

    if (dipilih.size > MAX_FILE_SIZE) {
        galat.value = {
            message: `Ukuran berkas (${formatUkuranBerkas(dipilih.size)}) melebihi batas maksimal 10 MB. Silakan gunakan berkas dengan ukuran lebih kecil.`,
            errorCode: 'FILE_TOO_LARGE',
        };
        berkas.value = dipilih;
        pratinjau.value = null;
        return;
    }

    berkas.value = dipilih;
    hasil.value = null;
    galat.value = null;
    memuat.value = true;

    try {
        const response = await visitorMasterDataApi.importPreview(props.baseUrl, props.domain, dipilih);
        pratinjau.value = response.data;
    } catch (error) {
        galat.value = error;
        pratinjau.value = null;
    } finally {
        memuat.value = false;
    }
}

async function jalankan() {
    if (!bolehImport.value) return;
    menjalankan.value = true;
    galat.value = null;

    try {
        const response = await visitorMasterDataApi.importRun(
            props.baseUrl,
            props.domain,
            berkas.value,
            pratinjau.value.sidik,
        );
        hasil.value = response.data;
        hcisMessage.success(response.message || 'Import selesai.');
        emit('selesai');
    } catch (error) {
        galat.value = error;
        hcisMessage.error(error.message);
    } finally {
        menjalankan.value = false;
    }
}

function bersihkan() {
    if (sibuk.value) return;
    berkas.value = null;
    pratinjau.value = null;
    hasil.value = null;
    galat.value = null;
    isDragging.value = false;
    if (inputRef.value) inputRef.value.value = '';
}

function tutup() {
    if (sibuk.value) return;
    emit('update:modelValue', false);
}
</script>

<style scoped>
.visitor-master-import {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
}

/* 1. Banner Sukses */
.visitor-master-import__success-hero {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    padding: 1rem 1.125rem;
    background: linear-gradient(135deg, rgba(240, 253, 244, 0.95) 0%, rgba(220, 252, 231, 0.75) 100%);
    border: 1px solid var(--hcis-success-200, rgb(187 247 208));
    border-radius: var(--hcis-radius-lg, 0.75rem);
    box-shadow: 0 2px 8px -2px rgba(22, 101, 52, 0.08);
}

.visitor-master-import__success-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 9999px;
    background: var(--hcis-success-100, rgb(220 252 231));
    color: var(--hcis-success-600, rgb(22 163 74));
    flex-shrink: 0;
}

.visitor-master-import__success-content {
    flex: 1;
}

.visitor-master-import__success-title {
    margin: 0 0 0.25rem 0;
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--hcis-success-900, rgb(20 83 45));
}

.visitor-master-import__success-desc {
    margin: 0;
    font-size: 0.8125rem;
    line-height: 1.45;
    color: var(--hcis-success-800, rgb(22 101 52));
}

/* 2. State Dropzone Pemilihan */
.visitor-master-import__pilih {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3, 0.75rem);
}

.visitor-master-import__drop {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    padding: var(--hcis-space-6, 1.75rem) var(--hcis-space-4, 1rem);
    text-align: center;
    cursor: pointer;
    border: 1.5px dashed var(--hcis-border-strong, rgb(148 163 184));
    border-radius: var(--hcis-radius-xl, 1rem);
    background: var(--hcis-surface-muted, rgb(248 250 252));
    transition: all 200ms cubic-bezier(0.16, 1, 0.3, 1);
}

.visitor-master-import__drop:hover,
.visitor-master-import__drop.is-dragging {
    border-color: var(--hcis-purple-500, rgb(168 85 247));
    background: var(--hcis-purple-50, rgb(250 245 255));
    box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.1);
    transform: translateY(-1px);
}

.visitor-master-import__drop-icon-box {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3.25rem;
    height: 3.25rem;
    border-radius: 9999px;
    background: linear-gradient(135deg, rgba(243, 232, 255, 0.9) 0%, rgba(233, 213, 255, 0.6) 100%);
    border: 1px solid var(--hcis-purple-200, rgb(233 213 255));
    color: var(--hcis-purple-700, rgb(126 34 206));
    box-shadow: 0 2px 6px -1px rgba(126, 34, 206, 0.12);
}

.visitor-master-import__drop-icon {
    font-size: 1.5rem;
}

.visitor-master-import__drop-text-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    max-width: 480px;
}

.visitor-master-import__input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}

.visitor-master-import__drop-judul {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--hcis-text-strong, rgb(15 23 42));
}

.visitor-master-import__drop-teks {
    font-size: 0.8125rem;
    line-height: 1.45;
    color: var(--hcis-text-muted, rgb(100 116 139));
}

.visitor-master-import__drop-capsules {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.25rem;
}

.visitor-master-import__capsule {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--hcis-text-muted, rgb(71 85 105));
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid var(--hcis-border-subtle, rgb(226 232 240));
    border-radius: 9999px;
    backdrop-filter: blur(4px);
}

/* 3. Kartu Template Promo */
.visitor-master-import__template-card {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem 1rem;
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.7) 0%, rgba(245, 243, 255, 0.9) 100%);
    border: 1px solid var(--hcis-purple-100, rgb(243 232 255));
    border-radius: var(--hcis-radius-lg, 0.75rem);
    flex-wrap: wrap;
}

.visitor-master-import__template-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-md, 0.5rem);
    background: var(--hcis-purple-100, rgb(243 232 255));
    color: var(--hcis-purple-700, rgb(126 34 206));
    flex-shrink: 0;
}

.visitor-master-import__template-content {
    flex: 1;
    min-width: 200px;
}

.visitor-master-import__template-title {
    margin: 0 0 0.125rem 0;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-purple-950, rgb(59 7 100));
}

.visitor-master-import__template-desc {
    margin: 0;
    font-size: 0.75rem;
    line-height: 1.4;
    color: var(--hcis-purple-800, rgb(107 33 168));
}

.visitor-master-import__tautan {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-purple-700, rgb(126 34 206));
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid var(--hcis-purple-200, rgb(233 213 255));
    border-radius: var(--hcis-radius-md, 0.5rem);
    cursor: pointer;
    text-decoration: none;
    transition: all 180ms ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.visitor-master-import__tautan:hover {
    background: var(--hcis-purple-600, rgb(147 51 234));
    color: var(--hcis-white, #ffffff);
    border-color: var(--hcis-purple-600, rgb(147 51 234));
    transform: translateY(-1px);
}

/* 4. Berkas Terpilih Header */
.visitor-master-import__berkas {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.75rem 1rem;
    border: 1px solid var(--hcis-border-subtle, rgb(226 232 240));
    border-radius: var(--hcis-radius-lg, 0.75rem);
    background: var(--hcis-surface-card, #ffffff);
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03);
}

.visitor-master-import__berkas-icon-box {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--hcis-radius-md, 0.5rem);
    background: linear-gradient(135deg, rgb(220 252 231) 0%, rgb(187 247 208) 100%);
    color: rgb(22 101 52);
    flex-shrink: 0;
}

.visitor-master-import__berkas-detail {
    flex: 1;
    min-width: 0;
}

.visitor-master-import__nama {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--hcis-text-strong, rgb(15 23 42));
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.visitor-master-import__berkas-meta {
    display: block;
    margin-top: 0.125rem;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, rgb(100 116 139));
}

/* 5. Skeleton Loader */
.visitor-master-import__memuat {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3, 0.75rem);
}

.visitor-master-import__memuat-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--hcis-purple-700, rgb(126 34 206));
    padding: 0.5rem 0.75rem;
    background: var(--hcis-purple-50, rgb(250 245 255));
    border-radius: var(--hcis-radius-md, 0.5rem);
    border: 1px solid var(--hcis-purple-100, rgb(243 232 255));
}

.visitor-master-import__memuat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 0.75rem;
}

.visitor-master-import__spin {
    animation: hcis-spin 1s linear infinite;
}

@keyframes hcis-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* 6. Ringkasan Metrik (KPI Pods) */
.visitor-master-import__ringkas {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: var(--hcis-space-3, 0.75rem);
}

.visitor-master-import__pil {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.875rem;
    border-radius: var(--hcis-radius-lg, 0.75rem);
    background: var(--hcis-surface-muted, rgb(248 250 252));
    border: 1px solid var(--hcis-border-subtle, rgb(226 232 240));
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.02);
    transition: transform 180ms ease, box-shadow 180ms ease;
}

.visitor-master-import__pil:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}

.visitor-master-import__pil-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 9999px;
    background: rgba(0, 0, 0, 0.04);
    flex-shrink: 0;
}

.visitor-master-import__pil-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.visitor-master-import__pil-label {
    font-size: 0.875rem;
    font-weight: 700;
    line-height: 1.25;
    color: var(--hcis-text-strong, rgb(15 23 42));
    font-variant-numeric: tabular-nums;
}

.visitor-master-import__pil-sub {
    font-size: 0.6875rem;
    font-weight: 500;
    color: var(--hcis-text-muted, rgb(100 116 139));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.visitor-master-import__pil--tambah {
    background: linear-gradient(135deg, rgba(240, 253, 244, 0.8) 0%, rgba(220, 252, 231, 0.5) 100%);
    border-color: var(--hcis-success-200, rgb(187 247 208));
}

.visitor-master-import__pil--tambah .visitor-master-import__pil-icon {
    background: var(--hcis-success-100, rgb(220 252 231));
    color: var(--hcis-success-700, rgb(21 128 61));
}

.visitor-master-import__pil--tambah .visitor-master-import__pil-label {
    color: var(--hcis-success-800, rgb(22 101 52));
}

.visitor-master-import__pil--lewati {
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.9) 0%, rgba(241, 245, 249, 0.7) 100%);
    border-color: var(--hcis-border-subtle, rgb(226 232 240));
}

.visitor-master-import__pil--lewati .visitor-master-import__pil-icon {
    background: var(--hcis-slate-100, rgb(241 245 249));
    color: var(--hcis-slate-600, rgb(71 85 105));
}

.visitor-master-import__pil--tolak {
    background: linear-gradient(135deg, rgba(254, 242, 242, 0.9) 0%, rgba(254, 226, 226, 0.6) 100%);
    border-color: var(--hcis-danger-200, rgb(254 202 202));
}

.visitor-master-import__pil--tolak .visitor-master-import__pil-icon {
    background: var(--hcis-danger-100, rgb(254 226 226));
    color: var(--hcis-danger-700, rgb(185 28 28));
}

.visitor-master-import__pil--tolak .visitor-master-import__pil-label {
    color: var(--hcis-danger-800, rgb(153 27 27));
}

.visitor-master-import__pil--aman .visitor-master-import__pil-icon {
    background: var(--hcis-slate-100, rgb(241 245 249));
    color: var(--hcis-slate-500, rgb(100 116 139));
}

.visitor-master-import__pil--nonaktif {
    background: linear-gradient(135deg, rgba(250, 245, 255, 0.8) 0%, rgba(243, 232, 255, 0.5) 100%);
    border-color: var(--hcis-purple-200, rgb(233 213 255));
}

.visitor-master-import__pil--nonaktif .visitor-master-import__pil-icon {
    background: var(--hcis-purple-100, rgb(243 232 255));
    color: var(--hcis-purple-700, rgb(126 34 206));
}

/* 7. Tabel Pratinjau per Lembar */
.visitor-master-import__table-card {
    border: 1px solid var(--hcis-border-subtle, rgb(226 232 240));
    border-radius: var(--hcis-radius-lg, 0.75rem);
    background: var(--hcis-surface-card, #ffffff);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.visitor-master-import__table-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.625rem 0.875rem;
    background: var(--hcis-surface-muted, rgb(248 250 252));
    border-bottom: 1px solid var(--hcis-border-subtle, rgb(226 232 240));
}

.visitor-master-import__table-card-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-text-strong, rgb(15 23 42));
}

.visitor-master-import__table-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.visitor-master-import__tabel {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
}

.visitor-master-import__tabel th,
.visitor-master-import__tabel td {
    padding: 0.625rem 0.75rem;
    text-align: left;
    vertical-align: middle;
    border-bottom: 1px solid var(--hcis-border-subtle, rgb(226 232 240));
}

.visitor-master-import__tabel tbody tr:last-child td {
    border-bottom: none;
}

.visitor-master-import__tabel th {
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-text-muted, rgb(100 116 139));
    background: rgba(248, 250, 252, 0.5);
}

.visitor-master-import__table-row {
    transition: background 150ms ease;
}

.visitor-master-import__table-row:hover {
    background: var(--hcis-surface-muted, rgb(248 250 252));
}

.visitor-master-import__sheet-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.visitor-master-import__sheet-icon {
    color: var(--hcis-purple-600, rgb(147 51 234));
}

.visitor-master-import__sheet {
    font-weight: 600;
    color: var(--hcis-text-strong, rgb(15 23 42));
}

.visitor-master-import__angka {
    text-align: right;
    white-space: nowrap;
}

.visitor-master-import__num-chip {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    border-radius: var(--hcis-radius-sm, 0.375rem);
    background: var(--hcis-surface-muted, rgb(241 245 249));
    color: var(--hcis-text-muted, rgb(71 85 105));
}

.visitor-master-import__num-chip--tambah {
    background: var(--hcis-success-100, rgb(220 252 231));
    color: var(--hcis-success-700, rgb(21 128 61));
    font-weight: 700;
}

.visitor-master-import__num-chip--tolak {
    background: var(--hcis-danger-100, rgb(254 226 226));
    color: var(--hcis-danger-700, rgb(185 28 28));
    font-weight: 700;
}

.visitor-master-import__catatan {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.75rem;
    line-height: 1.4;
    color: var(--hcis-text-muted, rgb(100 116 139));
}

.visitor-master-import__catatan--muted {
    color: var(--hcis-slate-500, rgb(100 116 139));
    font-style: italic;
}

.visitor-master-import__catatan--contoh {
    color: var(--hcis-slate-600, rgb(71 85 105));
}

.visitor-master-import__catatan--tolak {
    color: var(--hcis-danger-600, rgb(220 38 38));
    font-weight: 500;
}

.visitor-master-import__catatan--lain {
    margin-top: 0.5rem;
    font-style: italic;
}

/* 8. Panel Masalah */
.visitor-master-import__masalah {
    margin: 0;
    padding: var(--hcis-space-3, 0.875rem) var(--hcis-space-4, 1rem);
    border: 1px solid var(--hcis-danger-200, rgb(254 202 202));
    border-left: 4px solid var(--hcis-danger-500, rgb(239 68 68));
    border-radius: var(--hcis-radius-md, 0.5rem);
    background: linear-gradient(180deg, rgba(254, 242, 242, 0.7) 0%, rgba(254, 242, 242, 0.3) 100%);
}

.visitor-master-import__masalah-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.visitor-master-import__masalah-icon {
    color: var(--hcis-danger-600, rgb(220 38 38));
}

.visitor-master-import__masalah-judul {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-danger-900, rgb(127 29 29));
}

.visitor-master-import__masalah-list {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.visitor-master-import__masalah-item {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    font-size: 0.75rem;
    line-height: 1.45;
    color: var(--hcis-danger-950, rgb(69 10 10));
}

.visitor-master-import__masalah-bullet {
    color: var(--hcis-danger-500, rgb(239 68 68));
    margin-top: 0.125rem;
    flex-shrink: 0;
}

.visitor-master-import__masalah-text {
    flex: 1;
}

/* 9. Pod Info Catatan Sistem */
.visitor-master-import__info-pod {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    padding: 0.75rem 0.875rem;
    border: 1px solid var(--hcis-purple-200, rgb(233 213 255));
    border-radius: var(--hcis-radius-md, 0.5rem);
    background: var(--hcis-purple-50, rgb(250 245 255));
    font-size: 0.75rem;
    color: var(--hcis-purple-900, rgb(88 28 135));
}

.visitor-master-import__info-header {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-weight: 600;
    color: var(--hcis-purple-800, rgb(107 33 168));
}

.visitor-master-import__info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin: 0;
    padding-left: 1rem;
    list-style: disc;
}

/* 10. Pod Lembar Diabaikan */
.visitor-master-import__diabaikan-pod {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.75rem;
    background: var(--hcis-surface-muted, rgb(248 250 252));
    border: 1px dashed var(--hcis-border-subtle, rgb(226 232 240));
    border-radius: var(--hcis-radius-md, 0.5rem);
    font-size: 0.75rem;
    color: var(--hcis-text-muted, rgb(100 116 139));
}
</style>

