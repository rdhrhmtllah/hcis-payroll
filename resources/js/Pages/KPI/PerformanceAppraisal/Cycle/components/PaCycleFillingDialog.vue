<template>
    <HcisDialog
        :model-value="modelValue"
        :title="mode === 'open' ? 'Buka Pengisian Cycle' : 'Tutup Pengisian Cycle'"
        kicker="CYCLE LIFECYCLE"
        :description="cycle ? `${cycle.Nama_Cycle} · ${cycle.Kode_Cycle}` : ''"
        :icon="mode === 'open' ? 'unlock-fill' : 'lock-fill'"
        :sentiment="mode === 'open' ? 'success' : 'warning'"
        variant="glass"
        size="lg"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="pa-modal-flow">
            <!-- 1. Hero Identity Header -->
            <div class="pa-hero-strip" :class="mode === 'open' ? 'is-open' : 'is-close'">
                <div class="pa-hero-icon-box">
                    <HcisIcon :name="mode === 'open' ? 'unlock-fill' : 'lock-fill'" size="md" />
                </div>
                <div class="pa-hero-content">
                    <div class="pa-hero-badges-row">
                        <span class="pa-hero-tag">{{ mode === 'open' ? 'AKTIVASI PENGISIAN' : 'PENUTUPAN PENGISIAN' }}</span>
                        <span class="pa-hero-cycle-code">{{ cycle?.Kode_Cycle || 'PA CYCLE' }}</span>
                    </div>
                    <p class="pa-hero-summary" v-if="mode === 'open'">
                        Buka akses evaluasi kuesioner bagi seluruh rater pada cycle ini.
                    </p>
                    <p class="pa-hero-summary" v-else>
                        Tutup akses pengisian evaluasi bagi seluruh rater pada cycle ini.
                    </p>
                </div>
            </div>

            <!-- Conflict Barrier Alert -->
            <div
                v-if="mode === 'open' && terhalangSiklusLain"
                class="pa-conflict-banner"
                role="alert"
            >
                <HcisIcon name="exclamation-triangle-fill" size="sm" color="warning" />
                <div>
                    <strong>Cycle {{ siklusTerbukaLain.kode }} Masih Terbuka</strong>
                    <span>Tutup pengisian cycle {{ siklusTerbukaLain.kode }} terlebih dahulu sebelum membuka cycle ini.</span>
                </div>
            </div>

            <!-- 2. Periode Pengisian Card -->
            <div v-if="mode === 'open'" class="pa-section-card">
                <div class="pa-card-top-bar">
                    <div class="pa-card-title-wrap">
                        <HcisIcon name="calendar-event" size="xs" color="brand" />
                        <span class="pa-card-title">Periode Pengisian</span>
                    </div>
                    <HcisBadge
                        :label="jendelaLengkap || (jendelaDari && jendelaSampai) ? 'Tersedia' : 'Wajib Diisi'"
                        :variant="jendelaLengkap || (jendelaDari && jendelaSampai) ? 'success' : 'warning'"
                        appearance="subtle"
                        size="xs"
                        dot
                    />
                </div>

                <!-- Preview Date Range -->
                <div v-if="jendelaLengkap" class="pa-dates-display">
                    <div class="pa-date-item">
                        <span class="pa-date-kicker">Mulai</span>
                        <strong class="pa-date-text">{{ dateLabel(cycle?.Pengisian_Dari) }}</strong>
                    </div>
                    <div class="pa-date-arrow">
                        <HcisIcon name="arrow-right" size="xs" color="brand" />
                        <span v-if="durasiHari" class="pa-durasi-chip">{{ durasiHari }} Hari</span>
                    </div>
                    <div class="pa-date-item">
                        <span class="pa-date-kicker">Selesai</span>
                        <strong class="pa-date-text">{{ dateLabel(cycle?.Pengisian_Sampai) }}</strong>
                    </div>
                </div>

                <!-- Form Inputs if Empty -->
                <div v-else class="pa-dates-form-wrap">
                    <div class="pa-dates-inputs-grid">
                        <HcisField id="pa-filling-from" label="Mulai Dari">
                            <HcisDatePicker
                                v-model="jendelaDari"
                                type="date"
                                placeholder="YYYY-MM-DD"
                                :disabled="acting"
                                :show-presets="false"
                                aria-label="Mulai dari"
                            />
                        </HcisField>
                        <HcisField id="pa-filling-to" label="Sampai Tanggal">
                            <HcisDatePicker
                                v-model="jendelaSampai"
                                type="date"
                                placeholder="YYYY-MM-DD"
                                :disabled="acting"
                                :show-presets="false"
                                aria-label="Sampai tanggal"
                            />
                        </HcisField>
                    </div>
                    <p v-if="galatJendela" class="pa-inline-error" role="alert">
                        <HcisIcon name="exclamation-circle-fill" size="2xs" />
                        <span>{{ galatJendela }}</span>
                    </p>
                </div>
            </div>

            <!-- 3. Kesiapan Akses Rater Card -->
            <div v-if="mode === 'open'" class="pa-section-card">
                <div class="pa-card-top-bar">
                    <div class="pa-card-title-wrap">
                        <HcisIcon name="person-check-fill" size="xs" color="brand" />
                        <span class="pa-card-title">Kesiapan Akses Rater</span>
                    </div>
                    <HcisBadge
                        :label="adaRaterBelumDijadwalkan ? `${formatNumber(penjadwalanAktif?.belum)} Belum Siap` : (penjadwalanAktif?.antre > 0 ? `${formatNumber(penjadwalanAktif.antre)} Dalam Antrean` : '100% Siap')"
                        :variant="adaRaterBelumDijadwalkan ? 'warning' : (penjadwalanAktif?.antre > 0 ? 'info' : 'success')"
                        appearance="subtle"
                        size="xs"
                        dot
                    />
                </div>

                <!-- Progress Strip -->
                <div class="pa-progress-strip">
                    <div class="pa-progress-label-row">
                        <span>Kesiapan Akses Pengisian</span>
                        <strong>{{ persenTerjadwal }}%</strong>
                    </div>
                    <div class="pa-track-bg">
                        <div
                            class="pa-track-bar"
                            :class="adaRaterBelumDijadwalkan ? 'is-warn' : 'is-done'"
                            :style="{ width: `${persenTerjadwal}%` }"
                        />
                    </div>
                </div>

                <!-- 3 Compact Stat Boxes -->
                <div class="pa-stats-3col">
                    <div class="pa-stat-unit">
                        <span class="pa-stat-lbl">Total Rater</span>
                        <strong class="pa-stat-val">{{ formatNumber(penjadwalanAktif?.total || 0) }}</strong>
                    </div>
                    <div class="pa-stat-unit">
                        <span class="pa-stat-lbl">Terjadwal</span>
                        <strong class="pa-stat-val text-success">{{ formatNumber(penjadwalanAktif?.terjadwal || 0) }}</strong>
                    </div>
                    <div class="pa-stat-unit" :class="{ 'is-pending': adaRaterBelumDijadwalkan }">
                        <span class="pa-stat-lbl">Belum Siap</span>
                        <strong class="pa-stat-val" :class="adaRaterBelumDijadwalkan ? 'text-warning' : 'text-muted'">
                            {{ formatNumber(penjadwalanAktif?.belum || 0) }}
                        </strong>
                    </div>
                </div>

                <!-- Action CTA if Unscheduled -->
                <div v-if="adaRaterBelumDijadwalkan" class="pa-action-callout">
                    <div class="pa-action-callout__text">
                        <HcisIcon name="exclamation-circle-fill" size="xs" color="warning" />
                        <span>
                            <strong>{{ formatNumber(penjadwalanAktif?.belum) }} rater belum dijadwalkan</strong>.
                            Akses harus disiapkan sebelum pengisian dibuka. Bila menjadwalkan
                            ulang tidak mengubah angkanya, orangnya memang ditolak HC Learn —
                            ganti penilainya lewat Revisi penugasan.
                        </span>
                    </div>
                    <div class="pa-action-callout__actions">
                        <HcisButton
                            variant="primary"
                            size="sm"
                            leading-icon="lightning-charge-fill"
                            :label="`Jadwalkan sisanya (${formatNumber(penjadwalanAktif.belum)})`"
                            :loading="sedangMenjadwalkanLocal || menjadwalkan"
                            :disabled="sedangMenjadwalkanLocal || menjadwalkan"
                            class="pa-action-callout__btn"
                            @click="bukaKonfirmasiJadwal"
                        />

                        <!--
                         | JALAN KELUAR, dan ia harus ada di sini.
                         |
                         | Sebagian sisa tidak bisa dijadwalkan berapa kali pun tombol di
                         | sebelahnya ditekan — HC Learn menolak orangnya, misalnya karena sudah
                         | resign di sisi mereka. Tanpa tautan ini admin berdiri di dialog yang
                         | menawarkan satu tombol yang tak pernah bisa menepatinya, dan tidak ada
                         | apa pun di layar yang mengatakan ke mana ia harus pergi. Sesudah dikunci
                         | jalannya modal Revisi Penugasan (bukan wizard penyiapan); halaman yang
                         | tidak memasangnya tetap mendapat tautan ke Langkah 5.
                         -->
                        <HcisButton
                            variant="ghost"
                            size="sm"
                            leading-icon="wrench-adjustable"
                            label="Revisi penugasan"
                            :href="adaRevisiPenugasan ? undefined : alamatPerbaikanPenilai"
                            class="pa-action-callout__btn"
                            data-uji="pengisian-revisi-penugasan"
                            @click="adaRevisiPenugasan && emit('revisi-penugasan', cycle)"
                        />
                    </div>
                </div>

                <div v-else-if="penjadwalanAktif?.total > 0 && !penjadwalanAktif?.antre" class="pa-success-callout">
                    <HcisIcon name="check-circle-fill" size="xs" color="success" />
                    <span>Semua <strong>{{ formatNumber(penjadwalanAktif.total) }} rater</strong> telah memiliki akses pengisian.</span>
                </div>

                <!--
                    ANTRE BUKAN GAGAL, dan bukan pula "sudah berpintu".

                    `store` milik HC Learn menjawab 202 lalu menggarap antreannya belakangan,
                    jadi ada jeda ketika kirimannya sudah diterima tetapi tautannya belum
                    terbit. Menyembunyikan keadaan ini membuat admin menekan "Jadwalkan
                    sisanya" berulang atas pekerjaan yang sedang berjalan — dan tiap tekanan
                    berarti kiriman baru.
                -->
                <div v-if="penjadwalanAktif?.antre > 0" class="pa-queue-callout">
                    <div class="pa-queue-callout__icon">
                        <HcisIcon name="hourglass-split" size="xs" color="info" />
                    </div>
                    <div class="pa-queue-callout__text">
                        <strong>{{ formatNumber(penjadwalanAktif.antre) }} akses sedang digarap HC Learn.</strong>
                        <span>Tautannya menyusul; tarik hasilnya dengan tombol di bawah.</span>
                    </div>
                    <HcisButton
                        variant="secondary"
                        size="xs"
                        leading-icon="arrow-repeat"
                        label="Tarik Hasil"
                        :loading="sedangMenarik"
                        class="pa-queue-callout__btn"
                        @click="tarikHasil"
                    />
                </div>

                <div v-if="penjadwalanAktif?.gagal > 0" class="pa-rejected-callout">
                    <HcisIcon name="exclamation-triangle-fill" size="xs" color="danger" />
                    <span>
                        <strong>{{ formatNumber(penjadwalanAktif.gagal) }} akses DITOLAK HC Learn.</strong>
                        Sebabnya tercatat per penilai di layar Monitoring Rater.
                    </span>
                </div>
            </div>

            <!-- Closing Summary when mode === 'close' -->
            <div v-else class="pa-section-card">
                <div class="pa-card-top-bar">
                    <div class="pa-card-title-wrap">
                        <HcisIcon name="info-circle-fill" size="xs" color="brand" />
                        <span class="pa-card-title">Ringkasan Periode Pengisian</span>
                    </div>
                </div>
                <div class="pa-dates-display">
                    <div class="pa-date-item">
                        <span class="pa-date-kicker">Mulai</span>
                        <strong class="pa-date-text">{{ dateLabel(cycle?.Pengisian_Dari) }}</strong>
                    </div>
                    <div class="pa-date-arrow">
                        <HcisIcon name="arrow-right" size="xs" color="brand" />
                    </div>
                    <div class="pa-date-item">
                        <span class="pa-date-kicker">Selesai</span>
                        <strong class="pa-date-text">{{ dateLabel(cycle?.Pengisian_Sampai) }}</strong>
                    </div>
                </div>

                <!-- Dampak penutupan: angka dari ringkasan pemantauan, dibaca SEBELUM memutuskan. -->
                <div v-if="ringkasPengisian && ringkasPengisian.total !== undefined" class="pa-tutup-dampak" data-uji="dampak-tutup">
                    <div class="pa-tutup-dampak__grid">
                        <div><span>Sudah dikirim</span><strong>{{ formatNumber(ringkasPengisian.selesai || 0) }}</strong><small>ikut dihitung</small></div>
                        <div class="is-ungu"><span>Terisi, belum dikirim</span><strong>{{ formatNumber(ringkasPengisian.terkumpul || 0) }}</strong><small>dilepas otomatis &amp; ikut dihitung</small></div>
                        <div class="is-amber"><span>Belum terisi</span><strong>{{ formatNumber(belumTerisi) }}</strong><small>tidak ikut dihitung</small></div>
                    </div>
                    <ul class="pa-tutup-dampak__daftar">
                        <li v-if="ringkasPengisian.terkumpul > 0">
                            {{ formatNumber(ringkasPengisian.terkumpul) }} lembar yang sudah terisi tetapi belum ditekan kirim akan <b>dilepas otomatis</b> atas nama Anda.
                        </li>
                        <li v-if="belumTerisi > 0">
                            {{ formatNumber(belumTerisi) }} penugasan belum terisi; penilainya tidak bisa mengisi lagi. Peserta yang kategori penilainya tidak memenuhi ambang akan <b>TERTAHAN</b> saat dihitung.
                        </li>
                        <li>Peserta yang lembarnya lengkap langsung <b>diantrekan untuk dihitung</b>.</li>
                        <li>Pengisian masih bisa dibuka ulang selama siklus belum ditutup penerbitannya.</li>
                    </ul>
                </div>
            </div>

            <!-- Error State -->
            <HcisResultState
                v-if="actionError"
                :status="actionErrorStatus === 403 ? 'warning' : 'danger'"
                variant="flat"
                layout="inline"
                :title="actionErrorStatus === 403 ? 'Aksi lifecycle tidak tersedia' : 'Perubahan lifecycle ditolak'"
                :description="actionError"
                :error-code="actionErrorCode"
                :request-id="actionRequestId"
                :show-retry="false"
            />
        </div>

        <!-- Sticky Footer -->
        <template #footer-start>
            <div class="pa-footer-hint-wrap">
                <span v-if="mode === 'open' && terhalangSiklusLain" class="pa-dialog-hint pa-dialog-hint--salah">
                    <HcisIcon name="x-circle-fill" size="2xs" />
                    Tutup cycle {{ siklusTerbukaLain?.kode }} terlebih dahulu
                </span>
                <span v-else-if="mode === 'open' && adaRaterBelumDijadwalkan" class="pa-dialog-hint pa-dialog-hint--salah">
                    <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                    {{ formatNumber(penjadwalanAktif.belum) }} rater belum dijadwalkan
                </span>
                <span v-else-if="mode === 'open' && !jendelaLengkap && (!jendelaDari || !jendelaSampai)" class="pa-dialog-hint pa-dialog-hint--salah">
                    <HcisIcon name="calendar-range" size="2xs" />
                    Lengkapi rentang tanggal pengisian
                </span>
                <span v-else-if="mode === 'open' && bolehBuka" class="pa-dialog-hint pa-dialog-hint--benar">
                    <HcisIcon name="check-circle-fill" size="2xs" />
                    Semua rater siap · Siap dibuka
                </span>
            </div>
        </template>
        <template #footer>
            <HcisButton
                variant="secondary"
                label="Batal"
                size="sm"
                :disabled="acting"
                @click="$emit('update:modelValue', false)"
            />
            <HcisButton
                :variant="mode === 'open' ? 'success' : 'warning'"
                :label="mode === 'open' ? 'Buka pengisian' : 'Tutup pengisian'"
                size="sm"
                :leading-icon="mode === 'open' ? 'unlock-fill' : 'lock-fill'"
                :loading="acting"
                :disabled="acting || (mode === 'open' && !bolehBuka)"
                :title="mode === 'open' && adaRaterBelumDijadwalkan ? 'Selesaikan penjadwalan akses rater terlebih dahulu' : null"
                @click="kirim"
            />
        </template>
    </HcisDialog>

    <!-- MODAL KONFIRMASI PENJADWALAN AKSES RATER -->
    <HcisDialog
        v-model="dialogKonfirmasiJadwalTerbuka"
        title="Konfirmasi Penjadwalan Akses"
        kicker="PENJADWALAN RATER"
        sentiment="brand"
        icon="person-check-fill"
        variant="glass"
        size="md"
        sticky-footer
    >
        <div class="pa-konfirmasi-jadwal-modal">
            <div class="pa-konfirmasi-jadwal-hero">
                <div class="pa-konfirmasi-jadwal-hero-icon">
                    <HcisIcon name="person-check-fill" size="lg" />
                </div>
                <div class="pa-konfirmasi-jadwal-hero-text">
                    <span class="pa-eyebrow">PROSES AKSES RATER</span>
                    <h4>Jadwalkan Akses untuk {{ formatNumber(penjadwalanAktif?.belum || 0) }} Rater</h4>
                    <p>
                        Sistem akan memproses dan mengaktifkan akses evaluasi secara bertahap bagi seluruh rater yang belum terjadwal.
                    </p>
                </div>
            </div>

            <!-- 3 Quick Stats Cards -->
            <div class="pa-konfirmasi-stats-grid">
                <div class="pa-konfirmasi-stat-card">
                    <span class="pa-stat-title">Total Rater</span>
                    <strong class="pa-stat-num">{{ formatNumber(penjadwalanAktif?.total || 0) }}</strong>
                </div>
                <div class="pa-konfirmasi-stat-card">
                    <span class="pa-stat-title">Terjadwal</span>
                    <strong class="pa-stat-num text-success">{{ formatNumber(penjadwalanAktif?.terjadwal || 0) }}</strong>
                </div>
                <div class="pa-konfirmasi-stat-card">
                    <span class="pa-stat-title">Akan Diproses</span>
                    <strong class="pa-stat-num text-brand">{{ formatNumber(penjadwalanAktif?.belum || 0) }}</strong>
                </div>
            </div>

            <!-- Notice -->
            <div class="pa-konfirmasi-notice">
                <HcisIcon name="info-circle-fill" size="xs" color="brand" />
                <span>Proses penjadwalan akses akan divisualisasikan melalui Progress Monitor.</span>
            </div>
        </div>

        <template #footer>
            <HcisButton
                variant="secondary"
                label="Batal"
                size="sm"
                @click="dialogKonfirmasiJadwalTerbuka = false"
            />
            <HcisButton
                variant="primary"
                label="Ya, Jadwalkan Sekarang"
                leading-icon="play-fill"
                size="sm"
                @click="eksekusiJadwalkanSisanya"
            />
        </template>
    </HcisDialog>

    <!-- MODAL LIQUID PROGRESS MONITOR -->
    <PaLiquidProgressDialog
        v-model="dialogProgresLiquidTerbuka"
        :status="statusProgresLiquid"
        :phase="faseProgresLiquid"
        :terjadwal="angkaProgresTerjadwal"
        :total="angkaProgresTotal"
        :sisa="angkaProgresSisa"
        :antre="angkaProgresAntre"
        :menarik="sedangMenarik"
        :batch-index="batchPutaranAktif"
        :error-message="galatJadwal"
        @retry="eksekusiJadwalkanSisanya"
        @tarik-hasil="tarikHasil"
        @finish="dialogProgresLiquidTerbuka = false"
    />
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisDatePicker,
    HcisDialog,
    HcisField,
    HcisIcon,
    HcisResultState,
    hcisMessage,
} from '@/components/ui';
import paApi from '../../api/paApi.js';
import PaLiquidProgressDialog from './PaLiquidProgressDialog.vue';

defineOptions({ name: 'PaCycleFillingDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    mode: { type: String, default: 'open' },
    cycle: { type: Object, default: null },
    acting: { type: Boolean, default: false },
    actionResult: { type: Object, default: null },
    actionError: { type: String, default: '' },
    actionErrorStatus: { type: Number, default: 0 },
    actionErrorCode: { type: String, default: '' },
    actionRequestId: { type: String, default: '' },

    /* Ringkasan pintu penilai. null selama belum dimuat — dibedakan dari nol, karena "belum
       tahu" dan "tidak ada satu pun" menuntut kalimat yang berbeda. */
    penjadwalan: { type: Object, default: null },

    /* Ringkasan pengisian dari pemantauan (`ringkasan`): dipakai mode tutup untuk
       menunjukkan dampaknya sebelum dikonfirmasi. */
    ringkasPengisian: { type: Object, default: null },
    menjadwalkan: { type: Boolean, default: false },

    /* Siklus LAIN yang sedang terbuka, bila ada — `{ id, kode, nama }`.
       Hanya satu siklus boleh terbuka pada satu waktu; lihat catatan di
       `PaCyclePreparationService::pastikanTidakAdaSiklusLainTerbuka()`. */
    siklusTerbukaLain: { type: Object, default: null },
    /* Pemasang mendengarkan `revisi-penugasan` (halaman siklus) — tombol perbaikan membuka modal. */
    adaRevisiPenugasan: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'confirm', 'jadwalkan', 'update:penjadwalan', 'jadwal-selesai', 'tarik-hasil', 'revisi-penugasan']);

/* Tanggal yang sedang diketik, hidup HANYA selama dialog terbuka. Dikosongkan saat dibuka
   ulang supaya sisa ketikan siklus sebelumnya tidak ikut terkirim ke siklus lain. */
const jendelaDari = ref('');
const jendelaSampai = ref('');
const galatJendela = ref('');

/* Live schedule override when batch scheduling completes inside this modal */
const penjadwalanLive = ref(null);
const belumTerisi = computed(() => Number(props.ringkasPengisian?.belum_mulai || 0) + Number(props.ringkasPengisian?.berjalan || 0));
const penjadwalanAktif = computed(() => penjadwalanLive.value ?? props.penjadwalan);

/* Liquid progress dialog and confirmation state */
const dialogKonfirmasiJadwalTerbuka = ref(false);
const dialogProgresLiquidTerbuka = ref(false);
const statusProgresLiquid = ref('running'); // 'running' | 'success' | 'error'
const faseProgresLiquid = ref('jadwal'); // 'jadwal' | 'selesai'
const batchPutaranAktif = ref(1);
const galatJadwal = ref('');
const sedangMenjadwalkanLocal = ref(false);

/* Menarik hasil TERPISAH dari menjadwalkan, dan itu mengikuti bentuk kontrak HC Learn:
   `store` menjawab 202 lalu menggarap antreannya belakangan. Menyatukan keduanya di satu
   tombol berarti tiap penarikan ikut mengirim kiriman baru. */
const sedangMenarik = ref(false);
const progresJadwal = ref(null);

watch(() => props.modelValue, (terbuka) => {
    if (terbuka) {
        jendelaDari.value = '';
        jendelaSampai.value = '';
        galatJendela.value = '';
        penjadwalanLive.value = null;
        dialogKonfirmasiJadwalTerbuka.value = false;
        dialogProgresLiquidTerbuka.value = false;
        galatJadwal.value = '';
    }
});

watch(() => props.penjadwalan, (baru) => {
    if (baru) {
        penjadwalanLive.value = baru;

        /* Angka segar dari induk (mis. sesudah tarik hasil) menggantikan sisa putaran kirim. */
        if (! sedangMenjadwalkanLocal.value) {
            progresJadwal.value = null;
        }
    }
});

const durasiHari = computed(() => {
    const dari = props.cycle?.Pengisian_Dari || jendelaDari.value;
    const sampai = props.cycle?.Pengisian_Sampai || jendelaSampai.value;
    if (!dari || !sampai) return null;
    const d1 = new Date(`${String(dari).slice(0, 10)}T00:00:00`);
    const d2 = new Date(`${String(sampai).slice(0, 10)}T00:00:00`);
    const diffTime = d2 - d1;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    return diffDays > 0 ? diffDays : null;
});

const persenTerjadwal = computed(() => {
    const total = Number(penjadwalanAktif.value?.total ?? 0);
    const terjadwal = Number(penjadwalanAktif.value?.terjadwal ?? 0);
    if (total <= 0) return 0;
    return Math.min(100, Math.round((terjadwal / total) * 100));
});

const angkaProgresTerjadwal = computed(() => {
    if (progresJadwal.value?.terjadwal !== undefined) {
        return Number(progresJadwal.value.terjadwal);
    }
    return Number(penjadwalanAktif.value?.terjadwal ?? 0);
});

const angkaProgresTotal = computed(() => {
    if (progresJadwal.value?.total !== undefined) {
        return Number(progresJadwal.value.total);
    }
    return Number(penjadwalanAktif.value?.total ?? 0);
});

const angkaProgresSisa = computed(() => {
    if (progresJadwal.value?.belum !== undefined) {
        return Number(progresJadwal.value.belum);
    }
    return Number(penjadwalanAktif.value?.belum ?? 0);
});

const angkaProgresAntre = computed(() => {
    if (progresJadwal.value?.antre !== undefined) {
        return Number(progresJadwal.value.antre);
    }
    return Number(penjadwalanAktif.value?.antre ?? 0);
});

function tarikHasil() {
    if (sedangMenarik.value) return;

    sedangMenarik.value = true;
    emit('tarik-hasil', { selesai: () => { sedangMenarik.value = false; } });
}

function bukaKonfirmasiJadwal() {
    if (sedangMenjadwalkanLocal.value || props.menjadwalkan) return;
    dialogKonfirmasiJadwalTerbuka.value = true;
}

async function eksekusiJadwalkanSisanya() {
    const cycleId = props.cycle?.Id_PA_Cycle;
    if (!cycleId || sedangMenjadwalkanLocal.value) return;

    dialogKonfirmasiJadwalTerbuka.value = false;
    dialogProgresLiquidTerbuka.value = true;
    sedangMenjadwalkanLocal.value = true;
    galatJadwal.value = '';
    statusProgresLiquid.value = 'running';
    faseProgresLiquid.value = 'jadwal';
    batchPutaranAktif.value = 1;
    progresJadwal.value = null;

    try {
        for (let putaran = 0; putaran < 60; putaran++) {
            batchPutaranAktif.value = putaran + 1;
            const hasil = await paApi.jadwalkanPenilaian(cycleId, 200);

            if (hasil?.ringkasan) {
                progresJadwal.value = hasil.ringkasan;
                penjadwalanLive.value = hasil.ringkasan;
            }

            if ((hasil?.gagal || 0) > 0) {
                galatJadwal.value = `${hasil.gagal} penugasan gagal dijadwalkan. ${hasil.contoh_galat?.[0] || ''}`;
                statusProgresLiquid.value = 'error';
                break;
            }

            if ((hasil?.diminta || 0) === 0 || (hasil?.ringkasan?.belum || 0) === 0) {
                break;
            }
        }

        const ringkasanTerbaru = await paApi.ringkasanPenjadwalan(cycleId);
        if (ringkasanTerbaru) {
            penjadwalanLive.value = ringkasanTerbaru;
            progresJadwal.value = ringkasanTerbaru;
            emit('update:penjadwalan', ringkasanTerbaru);
            emit('jadwal-selesai', ringkasanTerbaru);
        }

        /* SELESAI berarti SELURUHNYA BERTAUTAN, bukan "tidak ada lagi yang perlu diminta".
           `belum` menghitung penugasan yang belum dimintakan; yang sudah dititipkan ke antrean
           HC Learn punya `belum` nol dan tautan nol sekaligus. Menyebutnya selesai di sini
           adalah kalimat yang membuat admin membuka jendela pengisian bagi orang-orang yang
           belum punya satu tautan pun. */
        const antre = Number(ringkasanTerbaru?.antre ?? 0);
        const terjadwal = Number(ringkasanTerbaru?.terjadwal ?? 0);
        const total = Number(ringkasanTerbaru?.total ?? 0);

        if (galatJadwal.value) {
            statusProgresLiquid.value = 'error';
        } else if (antre > 0) {
            statusProgresLiquid.value = 'menunggu';
        } else if (total > 0 && terjadwal >= total) {
            statusProgresLiquid.value = 'success';
            faseProgresLiquid.value = 'selesai';
            hcisMessage.success('Penjadwalan akses rater berhasil diselesaikan 100%.');
        } else {
            statusProgresLiquid.value = 'error';
        }
    } catch (e) {
        galatJadwal.value = e?.response?.data?.message || e?.message || 'Penjadwalan akses rater gagal diproses.';
        statusProgresLiquid.value = 'error';
    } finally {
        sedangMenjadwalkanLocal.value = false;
    }
}

/* Boleh dibuka bila jendelanya SUDAH ada, atau bila keduanya baru saja diketik di sini.
   Syaratnya sama dengan yang ditegakkan server; ditulis berbeda berarti layar menjanjikan
   boleh lalu server menolak. */
/* Siklus lain yang terbuka MENGHALANGI, apa pun keadaan jendelanya. Diperiksa lebih dulu
   supaya formulir tanggal tidak ditawarkan untuk tindakan yang sudah pasti ditolak. */
const terhalangSiklusLain = computed(() => Boolean(props.siklusTerbukaLain?.kode));
const belumDijadwalkan = computed(() => Number(penjadwalanAktif.value?.belum ?? 0));
const adaRaterBelumDijadwalkan = computed(() => Boolean(penjadwalanAktif.value && belumDijadwalkan.value > 0));

/* Menunjuk LANGSUNG ke langkahnya, bukan ke wizardnya. Wizard tanpa `?langkah=` membuka
   langkah mana pun yang terakhir disinggahi admin, sehingga tautan "perbaiki di Langkah 5"
   bisa mendarat di Langkah 1 — dan admin kembali menebak, persis yang ingin dihentikan. */
const alamatPerbaikanPenilai = computed(
    () => `/performance-appraisal/siapkan/${props.cycle?.Id_PA_Cycle}?langkah=PENUGASAN`,
);

const bolehBuka = computed(() => {
    if (terhalangSiklusLain.value) {
        return false;
    }

    if (props.menjadwalkan || sedangMenjadwalkanLocal.value) {
        return false;
    }

    /* Rater yang belum dijadwalkan menghalangi pembukaan: seluruh rater wajib memiliki akses */
    if (adaRaterBelumDijadwalkan.value) {
        return false;
    }

    return jendelaLengkap.value || Boolean(jendelaDari.value && jendelaSampai.value);
});

function kirim() {
    galatJendela.value = '';

    if (props.mode === 'open' && ! jendelaLengkap.value) {
        if (! jendelaDari.value || ! jendelaSampai.value) {
            galatJendela.value = 'Isi kedua tanggalnya lebih dulu.';

            return;
        }

        /* Urutan tanggal diperiksa DI SINI JUGA. Server menolaknya dengan benar, tetapi
           penolakan yang datang sesudah perjalanan jaringan terbaca sebagai kegagalan
           sistem, padahal jawabannya sudah ada sebelum tombolnya ditekan. */
        if (jendelaSampai.value < jendelaDari.value) {
            galatJendela.value = 'Tanggal "sampai" tidak boleh lebih awal daripada "dari".';

            return;
        }
    }

    emit('confirm', jendelaLengkap.value ? {} : {
        pengisian_dari: jendelaDari.value,
        pengisian_sampai: jendelaSampai.value,
    });
}

/* Syarat yang SAMA dengan yang ditegakkan `bukaPengisian()` di server. Ditulis berbeda berarti
   layar menjanjikan boleh lalu server menolak - atau sebaliknya, dan yang kedua lebih buruk. */
const jendelaLengkap = computed(
    () => Boolean(props.cycle?.Pengisian_Dari) && Boolean(props.cycle?.Pengisian_Sampai),
);

function dateLabel(value) {
    if (!value) return 'Belum ditetapkan';
    return new Date(`${String(value).slice(0, 10)}T00:00:00`).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('id-ID');
}
</script>

<style scoped>
.pa-modal-flow {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

/* 1. Hero Strip */
.pa-hero-strip {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    transition: all 0.2s ease;
}

.pa-hero-strip.is-open {
    border: 1px solid rgba(16, 185, 129, 0.25);
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.9), rgba(255, 255, 255, 0.85));
}

.pa-hero-strip.is-close {
    border: 1px solid rgba(245, 158, 11, 0.25);
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.9), rgba(255, 255, 255, 0.85));
}

.pa-hero-icon-box {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    flex-shrink: 0;
    border-radius: 0.625rem;
}

.is-open .pa-hero-icon-box {
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.25);
}

.is-close .pa-hero-icon-box {
    background: rgba(245, 158, 11, 0.15);
    color: #d97706;
    border: 1px solid rgba(245, 158, 11, 0.25);
}

.pa-hero-content {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pa-hero-badges-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-hero-tag {
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #059669;
}

.is-close .pa-hero-tag {
    color: #d97706;
}

.pa-hero-cycle-code {
    font-size: 0.62rem;
    font-weight: 750;
    font-family: ui-monospace, monospace;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: rgba(0, 0, 0, 0.05);
    color: var(--hcis-text-strong, #0f172a);
}

.pa-hero-summary {
    margin: 0;
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.45;
}

/* Conflict Banner */
.pa-conflict-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.75rem 0.9rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(254, 243, 199, 0.75);
    border: 1px solid rgba(245, 158, 11, 0.3);
    font-size: 0.76rem;
    color: #78350f;
    line-height: 1.45;
}

.pa-conflict-banner strong {
    display: block;
    color: #92400e;
    margin-bottom: 0.15rem;
}

/* Section Cards */
.pa-section-card {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.85rem 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.pa-card-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.pa-card-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-card-title {
    font-size: 0.82rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    letter-spacing: -0.01em;
}

/* Date Display Strip */
.pa-dates-display {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.65rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.pa-date-item {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pa-date-kicker {
    font-size: 0.66rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-date-text {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-date-arrow {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
}

.pa-durasi-chip {
    font-size: 0.62rem;
    font-weight: 750;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    background: #e0e7ff;
    color: #4338ca;
}

/* Date Form */
.pa-dates-form-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #fffbeb;
    border: 1px solid #fde68a;
}

.pa-dates-inputs-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.6rem;
}

.pa-inline-error {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0;
    font-size: 0.72rem;
    color: #b91c1c;
}

/* Progress Strip */
.pa-progress-strip {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-progress-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-progress-label-row strong {
    font-size: 0.76rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-track-bg {
    width: 100%;
    height: 6px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-track-bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease;
}

.pa-track-bar.is-done {
    background: linear-gradient(90deg, #10b981, #059669);
}

.pa-track-bar.is-warn {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

/* 3 Compact Stats Grid */
.pa-stats-3col {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.45rem;
}

.pa-stat-unit {
    display: flex;
    flex-direction: column;
    padding: 0.5rem;
    border-radius: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    text-align: center;
    gap: 0.1rem;
}

.pa-stat-unit.is-pending {
    background: #fffbeb;
    border-color: #fde68a;
}

.pa-stat-lbl {
    font-size: 0.64rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
}

.pa-stat-val {
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-stat-val.text-success {
    color: #059669;
}

.pa-stat-val.text-warning {
    color: #b45309;
}

.pa-stat-val.text-muted {
    color: #64748b;
}

/* Action Callout */
.pa-action-callout {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    flex-wrap: wrap;
}

.pa-action-callout__text {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    font-size: 0.74rem;
    color: #92400e;
    line-height: 1.4;
    flex: 1;
    min-width: 12rem;
}

.pa-action-callout__btn {
    flex-shrink: 0;
}

.pa-action-callout__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.pa-success-callout {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 0.75rem;
    border-radius: 6px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    font-size: 0.74rem;
    color: #166534;
}

.pa-queue-callout {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 0.95rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 1px solid #bae6fd;
    box-shadow: 0 1px 2px rgba(14, 165, 233, 0.05);
    flex-wrap: wrap;
}

.pa-queue-callout__icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(14, 165, 233, 0.1);
}

.pa-queue-callout__text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 14rem;
}

.pa-queue-callout__text strong {
    font-size: 0.78rem;
    color: #0369a1;
}

.pa-queue-callout__text span {
    font-size: 0.72rem;
    color: #0284c7;
    line-height: 1.35;
}

.pa-queue-callout__btn {
    flex-shrink: 0;
}

.pa-rejected-callout {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #fef2f2;
    border: 1px solid #fecaca;
    font-size: 0.74rem;
    color: #b91c1c;
    line-height: 1.4;
}

.pa-sim-subtext {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    margin: 0;
    font-size: 0.68rem;
    color: #b45309;
    line-height: 1.4;
}

/* Footer Hints */
.pa-footer-hint-wrap {
    display: flex;
    align-items: center;
}

.pa-dialog-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    font-weight: 500;
}

.pa-dialog-hint--salah {
    color: var(--hcis-warning, #d97706);
}

.pa-dialog-hint--benar {
    color: var(--hcis-success, #059669);
}

/* Konfirmasi Jadwal Modal */
.pa-konfirmasi-jadwal-modal {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 0.2rem 0;
}

.pa-konfirmasi-jadwal-hero {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.85), rgba(255, 255, 255, 0.8));
    border: 1px solid rgba(99, 102, 241, 0.18);
    border-radius: var(--hcis-radius-lg, 12px);
}

.pa-konfirmasi-jadwal-hero-icon {
    width: 40px;
    height: 40px;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(79, 70, 229, 0.12);
    color: #4f46e5;
    border: 1px solid rgba(79, 70, 229, 0.25);
    flex-shrink: 0;
}

.pa-konfirmasi-jadwal-hero-text h4 {
    margin: 0 0 0.2rem;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-konfirmasi-jadwal-hero-text p {
    margin: 0;
    font-size: 0.76rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.45;
}

.pa-konfirmasi-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.6rem;
}

.pa-konfirmasi-stat-card {
    display: flex;
    flex-direction: column;
    padding: 0.65rem 0.5rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    text-align: center;
    gap: 0.2rem;
}

.pa-stat-title {
    font-size: 0.64rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-stat-num {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-stat-num.text-success {
    color: #059669;
}

.pa-stat-num.text-brand {
    color: #4f46e5;
}

.pa-konfirmasi-notice {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 0.75rem;
    background: rgba(79, 70, 229, 0.06);
    border: 1px solid rgba(79, 70, 229, 0.15);
    border-radius: 0.5rem;
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.4;
}

@media (max-width: 540px) {
    .pa-hero-strip {
        padding: 0.65rem 0.75rem;
        gap: 0.55rem;
    }
    .pa-hero-icon-box {
        width: 2.25rem;
        height: 2.25rem;
    }
    .pa-dates-display,
    .pa-dates-inputs-grid {
        grid-template-columns: 1fr;
    }
    .pa-dates-display {
        gap: 0.4rem;
        padding: 0.6rem;
    }
    .pa-date-arrow {
        transform: rotate(90deg);
        margin: 0.2rem 0;
    }
    .pa-action-callout {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    .pa-action-callout__actions {
        width: 100%;
    }
    .pa-action-callout__actions :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
    }
    .pa-stats-3col,
    .pa-konfirmasi-stats-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.35rem;
    }
    .pa-stat-unit,
    .pa-konfirmasi-stat-card {
        padding: 0.45rem 0.35rem;
        min-width: 0;
    }
    .pa-stat-val,
    .pa-stat-num {
        font-size: 0.92rem;
    }
    .pa-stat-lbl,
    .pa-stat-title {
        font-size: 0.60rem;
    }
}
.pa-tutup-dampak { display: flex; flex-direction: column; gap: 0.6rem; margin-top: 0.85rem; }
.pa-tutup-dampak__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; }
.pa-tutup-dampak__grid > div { display: flex; flex-direction: column; gap: 0.1rem; padding: 0.55rem 0.7rem; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; }
.pa-tutup-dampak__grid > div.is-ungu { border-color: #e9d5ff; background: #faf5ff; }
.pa-tutup-dampak__grid > div.is-amber { border-color: #fde68a; background: #fffbeb; }
.pa-tutup-dampak__grid span { font-size: 0.7rem; font-weight: 700; color: #64748b; }
.pa-tutup-dampak__grid strong { font-size: 1.15rem; color: #0f172a; font-variant-numeric: tabular-nums; }
.pa-tutup-dampak__grid small { font-size: 0.68rem; color: #94a3b8; }
.pa-tutup-dampak__daftar { margin: 0; padding-left: 1.1rem; font-size: 0.78rem; line-height: 1.5; color: #475569; }
@media (max-width: 560px) { .pa-tutup-dampak__grid { grid-template-columns: minmax(0, 1fr); } }
</style>
