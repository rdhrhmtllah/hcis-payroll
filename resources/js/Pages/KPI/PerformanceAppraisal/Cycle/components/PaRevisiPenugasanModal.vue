<template>
    <!--
     | REVISI PENUGASAN — pintu perubahan SESUDAH siklus dikunci, langsung dari kartu siklus.
     |
     | Wizard penyiapan adalah tempat MENYIAPKAN; sesudah dikunci admin tidak perlu kembali ke
     | sana. Di sini: keadaan singkat (penjadwalan HC Learn, penilai tidak aktif, peserta kurang
     | penilai) lalu dua jalan — revisi orang (dialog revisi) atau kelola penilai per peserta.
     | Saat dialog revisi terbuka, modal ini menyingkir supaya tidak bertumpuk.
     -->
    <HcisDialog
        :model-value="modelValue && !revisiTerbuka"
        title="Revisi Penugasan"
        kicker="SESUDAH SIKLUS DIKUNCI"
        icon="person-gear"
        size="lg"
        data-uji="modal-revisi-penugasan"
        @update:model-value="(v) => !v && tutup()"
    >
        <div class="pa-rpn">
            <!-- Siklus Header Banner -->
            <div class="pa-rpn__header-banner">
                <div class="pa-rpn__header-info">
                    <div class="pa-rpn__cycle-badge">
                        <HcisIcon name="lock-fill" size="2xs" />
                        <span>Siklus Terkunci</span>
                    </div>
                    <h4 class="pa-rpn__nama">{{ cycle?.Nama_Cycle }}</h4>
                    <p class="pa-rpn__header-desc">
                        Siklus ini telah dikunci. Anda dapat memantau kesehatan sinkronisasi akses HC Learn, menangani penilai tidak aktif, atau menyesuaikan rater tanpa mengulang wizard penyiapan.
                    </p>
                </div>
            </div>

            <!-- KEADAAN KESEHATAN PENUGASAN (Bento Metrics Grid) -->
            <div class="pa-rpn__status-container">
                <div class="pa-rpn__status-header">
                    <div class="pa-rpn__status-title">
                        <HcisIcon name="activity" size="xs" color="brand" />
                        <span>Kesehatan Penugasan &amp; Jadwal Akses</span>
                    </div>
                    <HcisButton
                        variant="ghost"
                        size="xs"
                        leading-icon="arrow-repeat"
                        :loading="memuat"
                        label="Segarkan"
                        @click="muat"
                    />
                </div>

                <div class="pa-rpn__keadaan-grid" data-uji="rpn-keadaan">
                    <!-- Kolom 1: Penjadwalan HC Learn -->
                    <div class="pa-rpn__stat-box is-primary">
                        <div class="pa-rpn__stat-top">
                            <div class="pa-rpn__stat-icon is-blue">
                                <HcisIcon name="mortarboard-fill" size="xs" />
                            </div>
                            <span class="pa-rpn__stat-label">Penilai terjadwal di HC Learn</span>
                        </div>
                        <div class="pa-rpn__stat-value-row">
                            <strong class="pa-rpn__stat-main-number">
                                {{ jadwal ? `${jadwal.terjadwal} dari ${jadwal.total}` : (memuat ? '…' : '-') }}
                            </strong>
                            <span v-if="jadwal && jadwal.total > 0" class="pa-rpn__stat-chip is-blue">
                                {{ Math.round((jadwal.terjadwal / jadwal.total) * 100) }}%
                            </span>
                        </div>
                        <!-- Progress Bar -->
                        <div v-if="jadwal && jadwal.total > 0" class="pa-rpn__stat-progress">
                            <div
                                class="pa-rpn__stat-progress-bar"
                                :style="{ width: `${Math.round((jadwal.terjadwal / jadwal.total) * 100)}%` }"
                            />
                        </div>
                        <div v-if="tugasJadwalBerjalan" class="pa-rpn__stat-footnote is-running">
                            <HcisIcon name="arrow-repeat" size="2xs" animation="spin" />
                            <span>Penjadwalan sedang berjalan di latar ({{ tugasJadwal.persen ?? 0 }}%)</span>
                        </div>
                        <div v-else class="pa-rpn__stat-footnote">
                            <span>Sinkronisasi akun &amp; form di HC Learn</span>
                        </div>
                    </div>

                    <!-- Kolom 2: Peserta Kurang Penilai -->
                    <div class="pa-rpn__stat-box" :class="{ 'is-warning': pesertaKurang > 0, 'is-success': pesertaKurang === 0 }">
                        <div class="pa-rpn__stat-top">
                            <div class="pa-rpn__stat-icon" :class="pesertaKurang > 0 ? 'is-amber' : 'is-slate'">
                                <HcisIcon :name="pesertaKurang > 0 ? 'exclamation-triangle-fill' : 'people-fill'" size="xs" />
                            </div>
                            <span class="pa-rpn__stat-label">Peserta kurang penilai</span>
                        </div>
                        <div class="pa-rpn__stat-value-row">
                            <strong class="pa-rpn__stat-main-number" :class="{ 'is-text-amber': pesertaKurang > 0 }">
                                {{ pesertaKurang }}
                            </strong>
                            <span class="pa-rpn__stat-chip" :class="pesertaKurang > 0 ? 'is-amber' : 'is-green'">
                                {{ pesertaKurang > 0 ? 'Kurang Kuota' : 'Lengkap' }}
                            </span>
                        </div>
                        <div class="pa-rpn__stat-footnote">
                            <span>{{ pesertaKurang > 0 ? 'Perlu penambahan rater agar nilai valid' : 'Semua peserta memenuhi kuota minimum' }}</span>
                        </div>
                    </div>

                    <!-- Kolom 3: Penilai Tidak Aktif -->
                    <div class="pa-rpn__stat-box" :class="{ 'is-danger': penilaiTidakAktif > 0, 'is-success': penilaiTidakAktif === 0 }">
                        <div class="pa-rpn__stat-top">
                            <div class="pa-rpn__stat-icon" :class="penilaiTidakAktif > 0 ? 'is-rose' : 'is-slate'">
                                <HcisIcon :name="penilaiTidakAktif > 0 ? 'person-x-fill' : 'person-check-fill'" size="xs" />
                            </div>
                            <span class="pa-rpn__stat-label">Penilai Tidak Aktif</span>
                        </div>
                        <div class="pa-rpn__stat-value-row">
                            <strong class="pa-rpn__stat-main-number" :class="{ 'is-text-rose': penilaiTidakAktif > 0 }">
                                {{ penilaiTidakAktif }}
                            </strong>
                            <span class="pa-rpn__stat-chip" :class="penilaiTidakAktif > 0 ? 'is-rose' : 'is-green'">
                                {{ penilaiTidakAktif > 0 ? 'Resign / Non-Aktif' : 'Semua Aktif' }}
                            </span>
                        </div>
                        <div class="pa-rpn__stat-footnote">
                            <span>{{ penilaiTidakAktif > 0 ? 'Masih ditugaskan menilai peserta' : 'Seluruh rater aktif di sistem HRIS' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <HcisAlert
                v-if="!bolehRevisi"
                variant="info"
                appearance="subtle"
                size="sm"
                description="Pengisian siklus ini sudah ditutup — keadaan penugasan masih bisa dilihat, tetapi revisi tidak lagi bisa dijalankan."
                icon="lock-fill"
                data-uji="rpn-hanya-baca"
            />

            <!-- Penilai baru hasil revisi memakai jendela pengisian yang sama. -->
            <HcisAlert
                v-if="bolehRevisi && jendela && ['HAMPIR', 'LEWAT', 'KOSONG'].includes(jendela.status)"
                :variant="jendela.status === 'HAMPIR' ? 'warning' : 'danger'"
                appearance="subtle"
                size="sm"
                :description="jendela.pesan"
                icon="clock-history"
                data-uji="rpn-jendela"
            />

            <!-- PERINGATAN KONDISI KHUSUS (Smart Action Callouts) -->
            <div v-if="(belumTerjadwal > 0 && !tugasJadwalBerjalan) || penilaiTidakAktif > 0" class="pa-rpn__alerts-group">
                <HcisAlert
                    v-if="belumTerjadwal > 0 && !tugasJadwalBerjalan"
                    variant="warning"
                    appearance="glass"
                    size="sm"
                    :title="`${belumTerjadwal} penilai belum punya akses di HC Learn`"
                    description="Biasanya penilai baru hasil revisi yang penjadwalannya gagal atau belum dijalankan."
                    icon="exclamation-triangle-fill"
                    data-uji="rpn-belum-terjadwal"
                >
                    <div class="pa-rpn__alert-action">
                        <HcisButton
                            label="Jadwalkan sisanya"
                            variant="warning"
                            size="xs"
                            leading-icon="play-fill"
                            :loading="menjadwalkan"
                            data-uji="rpn-jadwalkan" :disabled="!bolehRevisi"
                            @click="jadwalkanSisa"
                        />
                    </div>
                </HcisAlert>

                <HcisAlert
                    v-if="penilaiTidakAktif > 0"
                    variant="danger"
                    appearance="glass"
                    size="sm"
                    :title="`${penilaiTidakAktif} penilai sudah tidak aktif di HRIS masih punya tugas menilai`"
                    description="Tugas mereka tidak akan pernah diisi. Lepas mereka sebagai penilai supaya pengganti dibentuk dari relasi organisasi."
                    icon="person-x-fill"
                    data-uji="rpn-tidak-aktif"
                >
                    <div class="pa-rpn__alert-action">
                        <HcisButton
                            label="Lepas & ganti penilai"
                            variant="danger"
                            size="xs"
                            leading-icon="person-dash"
                            data-uji="rpn-lepas" :disabled="!bolehRevisi"
                            @click="bukaRevisi({ tindakan: 'LEPAS_PENILAI', status: 'TIDAK_AKTIF' })"
                        />
                    </div>
                </HcisAlert>
            </div>

            <!-- JALUR TINDAKAN (ACTION CARDS HUB) -->
            <div class="pa-rpn__section-actions">
                <div class="pa-rpn__section-title-wrap">
                    <span class="pa-rpn__section-label">PILIH JALUR REVISI</span>
                    <p class="pa-rpn__section-desc">Pilih metode penyesuaian yang sesuai dengan kebutuhan Anda:</p>
                </div>

                <div class="pa-rpn__jalan">
                    <!-- Kartu 1: Revisi Peserta & Penilai (Massal / Terstruktur) -->
                    <button
                        type="button"
                        class="pa-rpn__card-action is-revisi"
                        data-uji="rpn-revisi-peserta"
                        :disabled="!bolehRevisi"
                        @click="bukaRevisi(null)"
                    >
                        <div class="pa-rpn__card-head">
                            <div class="pa-rpn__card-avatar is-brand">
                                <HcisIcon name="person-gear" size="md" />
                            </div>
                            <div class="pa-rpn__card-title-group">
                                <div class="pa-rpn__card-badge is-brand">Massal / Individu</div>
                                <h5 class="pa-rpn__card-title">Revisi Peserta &amp; Penilai</h5>
                            </div>
                        </div>

                        <p class="pa-rpn__card-desc">
                            Terapkan Level PA terbaru, ikutkan kembali, kecualikan, peserta susulan, selaraskan dengan relasi terbaru, atau lepas penilai — satu orang atau banyak sekaligus.
                        </p>

                        <div class="pa-rpn__card-bullets">
                            <div class="pa-rpn__card-bullet">
                                <HcisIcon name="check-circle-fill" size="2xs" color="brand" />
                                <span>Perubahan Level PA &amp; peserta susulan</span>
                            </div>
                            <div class="pa-rpn__card-bullet">
                                <HcisIcon name="check-circle-fill" size="2xs" color="brand" />
                                <span>Sinkronisasi massal relasi atasan / bawahan</span>
                            </div>
                            <div class="pa-rpn__card-bullet">
                                <HcisIcon name="check-circle-fill" size="2xs" color="brand" />
                                <span>Lepas penilai non-aktif / mutasi</span>
                            </div>
                        </div>

                        <div class="pa-rpn__card-cta is-brand">
                            <span>Buka Panel Revisi Peserta</span>
                            <HcisIcon name="arrow-right" size="xs" />
                        </div>
                    </button>

                    <!-- Kartu 2: Kelola Penilai per Peserta (Detail Rater) -->
                    <button
                        type="button"
                        class="pa-rpn__card-action is-kelola"
                        data-uji="rpn-kelola-rater"
                        :disabled="!bolehRevisi"
                        @click="kelolaRater"
                    >
                        <div class="pa-rpn__card-head">
                            <div class="pa-rpn__card-avatar is-teal">
                                <HcisIcon name="sliders" size="md" />
                            </div>
                            <div class="pa-rpn__card-title-group">
                                <div class="pa-rpn__card-badge is-teal">Detail Rater</div>
                                <h5 class="pa-rpn__card-title">Kelola Penilai per Peserta</h5>
                            </div>
                        </div>

                        <p class="pa-rpn__card-desc">
                            Ganti, tambah, atau cabut penilai satu peserta, termasuk penilai manual dan bobotnya.
                        </p>

                        <div class="pa-rpn__card-bullets">
                            <div class="pa-rpn__card-bullet">
                                <HcisIcon name="check-circle-fill" size="2xs" color="teal" />
                                <span>Tuning penilai individu per karyawan</span>
                            </div>
                            <div class="pa-rpn__card-bullet">
                                <HcisIcon name="check-circle-fill" size="2xs" color="teal" />
                                <span>Tambah penilai manual &amp; atur bobot %</span>
                            </div>
                            <div class="pa-rpn__card-bullet">
                                <HcisIcon name="check-circle-fill" size="2xs" color="teal" />
                                <span>Inspeksi matriks penilai lengkap</span>
                            </div>
                        </div>

                        <div class="pa-rpn__card-cta is-teal">
                            <span>Buka Daftar Peserta &amp; Rater</span>
                            <HcisIcon name="arrow-right" size="xs" />
                        </div>
                    </button>
                </div>
            </div>

            <HcisAlert v-if="galat" variant="danger" appearance="subtle" size="sm" :description="galat" icon="exclamation-triangle-fill" />
        </div>

        <template #footer>
            <div class="pa-rpn__footer-wrap">
                <span class="pa-rpn__footer-note">
                    <HcisIcon name="shield-check" size="2xs" color="muted" />
                    Penyesuaian penugasan tetap mematuhi guardrail siklus terkunci.
                </span>
                <HcisButton label="Tutup" variant="secondary" size="sm" @click="tutup" />
            </div>
        </template>
    </HcisDialog>

    <PaRevisiPesertaMassalDialog
        v-if="cycle"
        v-model="revisiTerbuka"
        :cycle-id="cycle.Id_PA_Cycle"
        :karyawan-awal="awalRevisi"
        @selesai="sesudahRevisi"
    />
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { HcisAlert, HcisButton, HcisDialog, HcisIcon } from '@/components/ui';
import { useActivityCenter } from '@/composables/useActivityCenter';
import paApi from '../../api/paApi.js';
import PaRevisiPesertaMassalDialog from './PaRevisiPesertaMassalDialog.vue';

defineOptions({ name: 'PaRevisiPenugasanModal' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    cycle: { type: Object, default: null },
    /* false sesudah pengisian ditutup: server menolak revisi, modal hanya menunjukkan keadaan. */
    bolehRevisi: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'kelola-rater', 'berubah']);

const memuat = ref(false);
const galat = ref('');
const jadwal = ref(null);
const tugasJadwal = ref(null);
const penilaiTidakAktif = ref(0);
const pesertaKurang = ref(0);
const jendela = ref(null);
const menjadwalkan = ref(false);
const revisiTerbuka = ref(false);
const awalRevisi = ref(null);

const tugasJadwalBerjalan = computed(() => ['queued', 'processing'].includes(tugasJadwal.value?.status));
const belumTerjadwal = computed(() => Number(jadwal.value?.belum ?? 0) + Number(jadwal.value?.gagal ?? 0));

let pemantau = null;

async function muat() {
    const id = props.cycle?.Id_PA_Cycle;
    if (!id) return;
    memuat.value = true;
    galat.value = '';
    /* Masing-masing berdiri sendiri: satu yang gagal tidak mengosongkan yang lain. */
    const [j, p, k] = await Promise.allSettled([
        paApi.statusPenjadwalanLatar(id),
        paApi.cariRevisiPeserta(id, { sumber: 'penilai', status: 'TIDAK_AKTIF' }),
        paApi.kesehatanPenugasan(id),
    ]);
    if (j.status === 'fulfilled') {
        jadwal.value = j.value?.ringkasan ?? null;
        tugasJadwal.value = j.value?.tugas ?? null;
    }
    if (p.status === 'fulfilled') {
        penilaiTidakAktif.value = (p.value?.baris ?? []).length;
        jendela.value = p.value?.jendela ?? null;
    }
    if (k.status === 'fulfilled') pesertaKurang.value = Number(k.value?.total_peserta_kurang ?? 0);
    memuat.value = false;
    pantauBila();
}

function pantauBila() {
    clearTimeout(pemantau);
    if (tugasJadwalBerjalan.value && props.modelValue) pemantau = setTimeout(muat, 5000);
}

watch(() => [props.modelValue, props.cycle?.Id_PA_Cycle], ([buka]) => {
    if (buka) muat();
    else clearTimeout(pemantau);
}, { immediate: true });

onBeforeUnmount(() => clearTimeout(pemantau));

async function jadwalkanSisa() {
    menjadwalkan.value = true;
    galat.value = '';
    try {
        await paApi.mulaiPenjadwalanLatar(props.cycle.Id_PA_Cycle);
        useActivityCenter().notifyNewTask();
        await muat();
    } catch (e) {
        galat.value = e?.message || 'Penjadwalan gagal dimulai.';
    } finally {
        menjadwalkan.value = false;
    }
}

function bukaRevisi(awal) {
    awalRevisi.value = awal;
    revisiTerbuka.value = true;
}

function sesudahRevisi() {
    useActivityCenter().notifyNewTask();
    emit('berubah');
}

/* Dialog revisi ditutup → modal ini muncul lagi dengan keadaan terbaru. */
watch(revisiTerbuka, (buka) => {
    if (!buka && props.modelValue) {
        awalRevisi.value = null;
        muat();
    }
});

function kelolaRater() {
    emit('kelola-rater', props.cycle);
    tutup();
}

function tutup() {
    clearTimeout(pemantau);
    emit('update:modelValue', false);
}
</script>

<style scoped>
.pa-rpn {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Header Banner */
.pa-rpn__header-banner {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
    border: 1px solid #e2e8f0;
}

.pa-rpn__header-info {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.pa-rpn__cycle-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.2rem 0.5rem;
    width: fit-content;
    background: #e2e8f0;
    border-radius: 6px;
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #334155;
}

.pa-rpn__nama {
    margin: 0;
    color: #0f172a;
    font-size: 1.125rem;
    font-weight: 800;
    letter-spacing: -0.015em;
}

.pa-rpn__header-desc {
    margin: 0;
    font-size: 0.8125rem;
    color: #64748b;
    line-height: 1.5;
}

/* Status Bento Container */
.pa-rpn__status-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-rpn__status-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-rpn__status-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475569;
}

.pa-rpn__keadaan-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.875rem;
}

.pa-rpn__stat-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.pa-rpn__stat-box:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04);
}

.pa-rpn__stat-box.is-warning {
    border-color: #fde68a;
    background: #fffdf5;
}

.pa-rpn__stat-box.is-danger {
    border-color: #fecdd3;
    background: #fff5f5;
}

.pa-rpn__stat-top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-rpn__stat-icon {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-rpn__stat-icon.is-blue {
    background: #eff6ff;
    color: #2563eb;
}

.pa-rpn__stat-icon.is-amber {
    background: #fef3c7;
    color: #d97706;
}

.pa-rpn__stat-icon.is-rose {
    background: #ffe4e6;
    color: #e11d48;
}

.pa-rpn__stat-icon.is-slate {
    background: #f1f5f9;
    color: #64748b;
}

.pa-rpn__stat-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    line-height: 1.3;
}

.pa-rpn__stat-value-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: 0.125rem;
}

.pa-rpn__stat-main-number {
    font-size: 1.125rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.01em;
}

.pa-rpn__stat-main-number.is-text-amber {
    color: #d97706;
}

.pa-rpn__stat-main-number.is-text-rose {
    color: #e11d48;
}

.pa-rpn__stat-chip {
    padding: 0.125rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 700;
    border-radius: 9999px;
}

.pa-rpn__stat-chip.is-blue {
    background: #dbeafe;
    color: #1e40af;
}

.pa-rpn__stat-chip.is-amber {
    background: #fef3c7;
    color: #92400e;
}

.pa-rpn__stat-chip.is-rose {
    background: #ffe4e6;
    color: #9f1239;
}

.pa-rpn__stat-chip.is-green {
    background: #dcfce7;
    color: #166534;
}

.pa-rpn__stat-progress {
    width: 100%;
    height: 6px;
    background: #e2e8f0;
    border-radius: 9999px;
    overflow: hidden;
    margin-top: 0.25rem;
}

.pa-rpn__stat-progress-bar {
    height: 100%;
    background: #2563eb;
    border-radius: 9999px;
    transition: width 0.4s ease;
}

.pa-rpn__stat-footnote {
    font-size: 0.6875rem;
    color: #64748b;
    line-height: 1.4;
    margin-top: auto;
}

.pa-rpn__stat-footnote.is-running {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    color: #1d4ed8;
    font-weight: 600;
}

/* Alerts */
.pa-rpn__alerts-group {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-rpn__alert-action {
    margin-top: 0.625rem;
}

/* Action Paths / Hub */
.pa-rpn__section-actions {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.pa-rpn__section-title-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.pa-rpn__section-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475569;
}

.pa-rpn__section-desc {
    margin: 0;
    font-size: 0.8125rem;
    color: #64748b;
}

.pa-rpn__jalan {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.pa-rpn__card-action {
    display: flex;
    flex-direction: column;
    padding: 1.25rem;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #ffffff;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    position: relative;
}

.pa-rpn__card-action:hover {
    border-color: #cbd5e1;
    background: #fafafa;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.04);
    transform: translateY(-2px);
}

.pa-rpn__card-action:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

.pa-rpn__card-head {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    margin-bottom: 0.75rem;
}

.pa-rpn__card-avatar {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-rpn__card-avatar.is-brand {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #dbeafe;
}

.pa-rpn__card-avatar.is-teal {
    background: #f0fdf4;
    color: #059669;
    border: 1px solid #dcfce7;
}

.pa-rpn__card-title-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-rpn__card-badge {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-radius: 4px;
    width: fit-content;
}

.pa-rpn__card-badge.is-brand {
    background: #dbeafe;
    color: #1e40af;
}

.pa-rpn__card-badge.is-teal {
    background: #d1fae5;
    color: #065f46;
}

.pa-rpn__card-title {
    margin: 0;
    color: #0f172a;
    font-size: 0.9375rem;
    font-weight: 800;
    line-height: 1.25;
}

.pa-rpn__card-desc {
    margin: 0 0 0.875rem 0;
    color: #64748b;
    font-size: 0.78125rem;
    line-height: 1.5;
}

.pa-rpn__card-bullets {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    margin-bottom: 1.125rem;
    padding: 0.625rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
}

.pa-rpn__card-bullet {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.71875rem;
    font-weight: 500;
    color: #334155;
}

.pa-rpn__card-cta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
    padding-top: 0.75rem;
    border-top: 1px solid #f1f5f9;
    font-size: 0.8125rem;
    font-weight: 700;
    transition: color 0.2s ease;
}

.pa-rpn__card-cta.is-brand {
    color: #2563eb;
}

.pa-rpn__card-cta.is-teal {
    color: #059669;
}

.pa-rpn__card-action:hover .pa-rpn__card-cta {
    text-decoration: underline;
}

/* Footer Wrap */
.pa-rpn__footer-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.pa-rpn__footer-note {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.75rem;
    color: #64748b;
}

@media (max-width: 768px) {
    .pa-rpn__keadaan-grid {
        grid-template-columns: 1fr;
    }

    .pa-rpn__jalan {
        grid-template-columns: 1fr;
    }

    .pa-rpn__footer-wrap {
        flex-direction: column-reverse;
        gap: 0.75rem;
        align-items: stretch;
    }

    .pa-rpn__footer-note {
        justify-content: center;
        text-align: center;
    }
}

/* Hanya-baca sesudah pengisian ditutup. */
.pa-rpn button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
</style>
