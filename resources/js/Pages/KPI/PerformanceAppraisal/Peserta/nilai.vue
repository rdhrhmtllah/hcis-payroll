<template>
    <main class="pa-nilai-page">
        <!-- Unified Header -->
        <HcisHeader
            title="Lembar Penilaian Kinerja"
            kicker="PERFORMANCE APPRAISAL · KUESIONER EVALUASI"
            :subtitle="lembar
                ? `Evaluasi untuk ${lembar.peserta.nama} (${lembar.penugasan.nama_kategori})`
                : 'Memuat lembar penilaian…'"
            icon="clipboard-check-fill"
            sentiment="brand"
            variant="glass"
            size="md"
            animated
            :loading="memuat"
            class="mb-4"
        >
            <template #actions>
                <div class="pa-nilai-header-actions">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        label="Kembali ke Ruang Kerja"
                        leading-icon="arrow-left"
                        @click="kembali"
                    />
                    <HcisButton
                        variant="glass"
                        size="sm"
                        label="Segarkan"
                        leading-icon="arrow-clockwise"
                        :loading="memuat"
                        @click="muat"
                    />
                </div>
            </template>
        </HcisHeader>

        <!-- Simulation Banner Note -->
        <div v-if="lembar" class="pa-nilai-simulasi-notice" role="note">
            <div class="pa-nilai-simulasi-notice__icon">
                <HcisIcon name="beaker" size="sm" />
            </div>
            <div class="pa-nilai-simulasi-notice__body">
                <strong>Lembar Evaluasi Mandiri</strong>
                <span>
                    Pengisian kuesioner ini terhubung langsung ke sistem perhitungan Performance Appraisal. Pastikan jawaban yang Anda berikan mencerminkan observasi objektif.
                </span>
            </div>
        </div>

        <!-- Error State -->
        <HcisResultState
            v-if="galat"
            status="danger"
            variant="glass"
            title="Lembar penilaian tidak bisa dibuka"
            :description="galat"
            :show-retry="true"
            @retry="muat"
        />

        <!-- Loading State -->
        <div v-else-if="memuat" class="pa-nilai-loading" aria-live="polite">
            <HcisIcon name="arrow-repeat" size="lg" animation="spin" color="brand" />
            <span>Memuat lembar penilaian…</span>
        </div>

        <!-- Content -->
        <template v-else-if="lembar">
            <!-- Already Completed State -->
            <HcisResultState
                v-if="lembar.sudah_selesai"
                status="success"
                variant="glass"
                layout="inline"
                title="Penilaian Sudah Selesai &amp; Terkirim"
                description="Jawaban evaluasi ini telah berhasil dikirim dan tersimpan di sistem. Terima kasih atas kontribusi objektif Anda."
                :show-retry="false"
                class="mb-3"
            />

            <!-- Subject Profile Card -->
            <HcisCard variant="glass" sentiment="brand" class="pa-nilai-subjek-card mb-4">
                <div class="pa-nilai-subjek">
                    <div class="pa-nilai-subjek__profile">
                        <HcisAvatar
                            :name="lembar.peserta.nama"
                            size="lg"
                            shape="rounded"
                        />
                        <div class="pa-nilai-subjek__info">
                            <span class="pa-eyebrow">KARYAWAN YANG DINILAI</span>
                            <h3 class="pa-nilai-subjek__name">{{ lembar.peserta.nama }}</h3>
                            <p class="pa-nilai-subjek__role">
                                {{ lembar.peserta.jabatan || '—' }} · {{ lembar.peserta.divisi || '—' }}
                                <span v-if="lembar.peserta.kode_karyawan" class="pa-nilai-subjek__code">
                                    ({{ lembar.peserta.kode_karyawan }})
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="pa-nilai-subjek__badges">
                        <HcisBadge
                            :label="lembar.penugasan.nama_kategori"
                            variant="brand"
                            appearance="glass"
                            size="md"
                        />
                        <HcisBadge
                            v-if="lembar.penugasan.diri_sendiri"
                            label="Self Appraisal"
                            variant="teal"
                            appearance="subtle"
                            size="md"
                            dot
                        />
                        <HcisBadge
                            :label="`${lembar.butir.length} Butir Pertanyaan`"
                            variant="neutral"
                            appearance="subtle"
                            size="md"
                        />
                        <HcisBadge
                            :label="'Tenggat: ' + tanggal(lembar.penugasan.due_at)"
                            variant="warning"
                            appearance="subtle"
                            size="md"
                        />
                    </div>
                </div>
            </HcisCard>

            <!-- Sticky Progress Tracker -->
            <div class="pa-nilai-tracker mb-4">
                <div class="pa-nilai-tracker__content">
                    <div class="pa-nilai-tracker__meta">
                        <div class="pa-nilai-tracker__title-group">
                            <HcisIcon name="check2-circle" :color="persenTerisi === 100 ? 'success' : 'brand'" />
                            <span class="pa-nilai-tracker__label">Kelengkapan Kuesioner:</span>
                            <strong>{{ jumlahTerisi }} dari {{ lembar.butir.length }} Terjawab</strong>
                        </div>
                        <HcisStatusBadge
                            :label="persenTerisi === 100 ? 'Siap Dikirim (100%)' : `${persenTerisi}% Selesai`"
                            :variant="persenTerisi === 100 ? 'success' : 'brand'"
                            dot
                        />
                    </div>
                    <HcisProgress
                        :value="persenTerisi"
                        :sentiment="persenTerisi === 100 ? 'success' : 'brand'"
                        size="sm"
                    />
                </div>
            </div>

            <!-- Form Questions -->
            <form class="pa-nilai-form" @submit.prevent="kirim">
                <div class="pa-nilai-form__list">
                    <article
                        v-for="(b, i) in lembar.butir"
                        :key="b.kode_soal"
                        class="pa-question-card pa-butir"
                        :class="{
                            'is-terisi': terisi(b),
                            'is-kurang': tandaiKurang && !terisi(b),
                        }"
                    >
                        <header class="pa-question-card__header">
                            <div class="pa-question-card__number">
                                {{ String(i + 1).padStart(2, '0') }}
                            </div>
                            <div class="pa-question-card__heading">
                                <div class="pa-question-card__meta">
                                    <span class="pa-question-card__code">{{ b.kode_soal }}</span>
                                    <span class="pa-question-card__type">
                                        {{ b.berskor ? `Skala Rating (1–${b.skor_maksimal})` : 'Uraian Kualitatif' }}
                                    </span>
                                </div>
                                <h4 class="pa-question-card__question">{{ b.pertanyaan }}</h4>
                            </div>
                        </header>

                        <!-- Rating scale selection -->
                        <div v-if="b.berskor" class="pa-rating-section pa-skala" role="radiogroup" :aria-label="b.pertanyaan">
                            <div class="pa-rating-options">
                                <label
                                    v-for="n in pilihanSkala(b)"
                                    :key="n"
                                    class="pa-rating-btn pa-skala__opsi"
                                    :class="{ 'is-selected is-dipilih': Number(jawaban[b.kode_soal]) === n }"
                                >
                                    <input
                                        type="radio"
                                        :name="b.kode_soal"
                                        :value="n"
                                        :disabled="terkunci"
                                        :checked="Number(jawaban[b.kode_soal]) === n"
                                        @change="jawaban[b.kode_soal] = n"
                                    />
                                    <span class="pa-rating-btn__number">{{ n }}</span>
                                    <span v-if="getScaleLabel(n, b.skor_maksimal)" class="pa-rating-btn__caption">
                                        {{ getScaleLabel(n, b.skor_maksimal) }}
                                    </span>
                                </label>
                            </div>
                            <div class="pa-rating-legend">
                                <span>1 = Sangat Kurang</span>
                                <span>{{ b.skor_maksimal }} = Sangat Baik / Istimewa</span>
                            </div>
                        </div>

                        <!-- Qualitative narrative essay -->
                        <div v-else class="pa-essay-section">
                            <textarea
                                v-model="jawaban[b.kode_soal]"
                                class="pa-essay-textarea pa-uraian"
                                rows="3"
                                :disabled="terkunci"
                                :placeholder="`Tuliskan uraian penilaian, masukan konkret, atau catatan untuk butir ${b.kode_soal}…`"
                            ></textarea>
                        </div>
                    </article>
                </div>

                <!-- Alert Messages -->
                <HcisResultState
                    v-if="galatKirim"
                    status="danger"
                    layout="inline"
                    variant="glass"
                    title="Penilaian belum bisa dikirim"
                    :description="galatKirim"
                    :show-retry="false"
                    class="my-3"
                />

                <HcisResultState
                    v-if="kabarKirim"
                    status="success"
                    layout="inline"
                    variant="glass"
                    title="Penilaian berhasil dikirim"
                    :description="kabarKirim"
                    :show-retry="false"
                    class="my-3"
                />

                <!-- Action Footer -->
                <footer class="pa-nilai-footer">
                    <div class="pa-nilai-footer__status">
                        <span v-if="waktuDraf" class="pa-nilai-footer__draft">
                            <HcisIcon name="cloud-check" size="sm" />
                            <span>Draft tersimpan di browser: {{ waktuDraf }}</span>
                        </span>
                    </div>
                    <div class="pa-nilai-footer__actions">
                        <HcisButton
                            type="button"
                            variant="secondary"
                            label="Simpan Draft"
                            leading-icon="save"
                            :disabled="terkunci"
                            @click="simpanDraf"
                        />
                        <HcisButton
                            type="submit"
                            variant="primary"
                            label="Kirim Penilaian"
                            trailing-icon="send-check-fill"
                            :loading="mengirim"
                            :disabled="terkunci || mengirim"
                        />
                    </div>
                </footer>
            </form>
        </template>
    </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import paApi from '../api/paApi.js';
import {
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisHeader,
    HcisIcon,
    HcisProgress,
    HcisResultState,
    HcisStatusBadge,
} from '@/components/ui';

defineOptions({ name: 'PerformanceAppraisalNilaiPage' });

const props = defineProps({
    token: { type: String, required: true },
});

const lembar = ref(null);
const memuat = ref(true);
const galat = ref('');
const mengirim = ref(false);
const galatKirim = ref('');
const kabarKirim = ref('');
const tandaiKurang = ref(false);
const waktuDraf = ref('');

const jawaban = reactive({});

/* Terkunci ketika sudah selesai atau sedang mengirim */
const terkunci = computed(() => Boolean(lembar.value?.sudah_selesai) || mengirim.value);

const KUNCI_DRAF = computed(() => `pa-nilai-draf:${props.token}`);

function terisi(b) {
    const v = jawaban[b.kode_soal];

    return b.berskor ? (v !== null && v !== undefined && v !== '') : String(v ?? '').trim() !== '';
}

const jumlahTerisi = computed(
    () => (lembar.value?.butir || []).filter((b) => terisi(b)).length,
);

const persenTerisi = computed(() => {
    const n = lembar.value?.butir?.length || 0;

    return n === 0 ? 0 : Math.round((jumlahTerisi.value / n) * 100);
});

function pilihanSkala(b) {
    const maks = Math.round(Number(b.skor_maksimal) || 0);

    return Array.from({ length: Math.max(0, maks) }, (_, i) => i + 1);
}

function getScaleLabel(n, max) {
    const total = Number(max) || 5;
    if (total === 5) {
        switch (Number(n)) {
            case 1: return 'Sangat Kurang';
            case 2: return 'Kurang';
            case 3: return 'Cukup';
            case 4: return 'Baik';
            case 5: return 'Sangat Baik';
            default: return '';
        }
    }
    return '';
}

function tanggal(iso) {
    if (! iso) {
        return 'tanpa tenggat';
    }

    const d = new Date(`${String(iso).slice(0, 10)}T00:00:00`);

    return Number.isNaN(d.getTime())
        ? String(iso)
        : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

function simpanDraf() {
    try {
        window.localStorage.setItem(KUNCI_DRAF.value, JSON.stringify({
            pada: new Date().toISOString(),
            jawaban: { ...jawaban },
        }));
        waktuDraf.value = 'baru saja';
    } catch {
        waktuDraf.value = '';
    }
}

function muatDraf() {
    try {
        const mentah = window.localStorage.getItem(KUNCI_DRAF.value);

        if (! mentah) {
            return;
        }

        const d = JSON.parse(mentah);

        Object.assign(jawaban, d?.jawaban || {});
        waktuDraf.value = d?.pada
            ? new Date(d.pada).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
            : '';
    } catch {
        waktuDraf.value = '';
    }
}

function buangDraf() {
    try {
        window.localStorage.removeItem(KUNCI_DRAF.value);
    } catch {
        // Draf gagal dibuang tidak mengganggu
    }

    waktuDraf.value = '';
}

async function muat() {
    memuat.value = true;
    galat.value = '';

    try {
        lembar.value = await paApi.lembarPenilaian(props.token);

        for (const b of lembar.value?.butir || []) {
            if (! (b.kode_soal in jawaban)) {
                jawaban[b.kode_soal] = b.berskor ? null : '';
            }
        }

        if (! lembar.value?.sudah_selesai) {
            muatDraf();
        }
    } catch (e) {
        galat.value = e?.message || 'Lembar penilaian tidak bisa dimuat.';
    } finally {
        memuat.value = false;
    }
}

async function kirim() {
    galatKirim.value = '';
    kabarKirim.value = '';

    const kurang = (lembar.value?.butir || []).filter((b) => ! terisi(b));

    if (kurang.length > 0) {
        tandaiKurang.value = true;
        galatKirim.value = `Masih ada ${kurang.length} butir yang belum diisi. `
            + 'Butir yang belum lengkap ditandai dengan warna merah.';

        return;
    }

    mengirim.value = true;

    try {
        const hasil = await paApi.kirimPenilaian(props.token, { ...jawaban });

        kabarKirim.value = hasil?.pesan || 'Penilaian berhasil dikirim.';
        buangDraf();
        await muat();
    } catch (e) {
        galatKirim.value = e?.message || 'Penilaian gagal dikirim.';
    } finally {
        mengirim.value = false;
    }
}

function kembali() {
    window.location.href = '/performance-appraisal/saya';
}

onMounted(muat);
</script>

<style scoped>
.pa-nilai-page {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1.25rem 1.5rem 3rem;
    max-width: 64rem;
    margin: 0 auto;
}

.pa-nilai-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-nilai-simulasi-notice {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1.15rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(59, 130, 246, 0.06);
    border: 1px solid rgba(59, 130, 246, 0.2);
    font-size: 0.84rem;
    color: var(--hcis-color-text-main, #1e293b);
}

.pa-nilai-simulasi-notice__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    background: rgba(59, 130, 246, 0.12);
    color: var(--hcis-color-brand, #2563eb);
    flex-shrink: 0;
}

.pa-nilai-simulasi-notice__body {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    line-height: 1.45;
}

.pa-nilai-simulasi-notice__body strong {
    font-size: 0.88rem;
    color: var(--hcis-color-brand-text, #1e40af);
}

.pa-nilai-simulasi-notice__body span {
    font-size: 0.8rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-nilai-loading {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 3rem;
    justify-content: center;
    color: var(--hcis-color-text-muted, #64748b);
    font-size: 0.92rem;
}

/* Subjek Card */
.pa-nilai-subjek-card {
    padding: 1.25rem 1.5rem;
}

.pa-nilai-subjek {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.pa-nilai-subjek__profile {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.pa-nilai-subjek__info {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-nilai-subjek__name {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-nilai-subjek__role {
    margin: 0;
    font-size: 0.84rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-nilai-subjek__code {
    color: var(--hcis-color-text-soft, #94a3b8);
}

.pa-nilai-subjek__badges {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Sticky Progress Tracker */
.pa-nilai-tracker {
    position: sticky;
    top: 1rem;
    z-index: 10;
    padding: 0.85rem 1.15rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
    border: 1px solid var(--hcis-color-border, #e2e8f0);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

.pa-nilai-tracker__content {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pa-nilai-tracker__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.pa-nilai-tracker__title-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
}

.pa-nilai-tracker__label {
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-nilai-tracker__title-group strong {
    color: var(--hcis-color-text-main, #0f172a);
}

/* Question List */
.pa-nilai-form__list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-question-card {
    padding: 1.25rem 1.5rem;
    border-radius: var(--hcis-radius-xl, 16px);
    background: var(--hcis-color-surface, #ffffff);
    border: 1px solid var(--hcis-color-border, #e2e8f0);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.pa-question-card:hover {
    border-color: rgba(37, 99, 235, 0.3);
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.04);
}

.pa-question-card.is-terisi {
    border-color: rgba(34, 197, 94, 0.35);
    background: linear-gradient(180deg, #ffffff 0%, rgba(240, 253, 244, 0.3) 100%);
}

.pa-question-card.is-kurang {
    border-color: var(--hcis-color-danger-border, #fecaca);
    background: var(--hcis-color-danger-surface, #fef2f2);
}

.pa-question-card__header {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    margin-bottom: 1.1rem;
}

.pa-question-card__number {
    flex-shrink: 0;
    width: 2.2rem;
    height: 2.2rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: var(--hcis-color-brand-surface, #eff6ff);
    color: var(--hcis-color-brand-text, #1e40af);
    font-size: 0.84rem;
    font-weight: 700;
    border: 1px solid rgba(37, 99, 235, 0.15);
}

.pa-question-card.is-terisi .pa-question-card__number {
    background: #dcfce7;
    color: #166534;
    border-color: rgba(34, 197, 94, 0.3);
}

.pa-question-card__heading {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-question-card__meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-question-card__code {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--hcis-color-text-muted, #64748b);
    background: var(--hcis-color-surface-soft, #f1f5f9);
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
}

.pa-question-card__type {
    font-size: 0.72rem;
    color: var(--hcis-color-text-soft, #94a3b8);
}

.pa-question-card__question {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.5;
    color: var(--hcis-color-text-main, #0f172a);
}

/* Rating scale */
.pa-rating-section {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding-left: 3.05rem;
}

.pa-rating-options {
    display: flex;
    align-items: stretch;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-rating-btn {
    position: relative;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 3.8rem;
    padding: 0.6rem 0.8rem;
    border-radius: var(--hcis-radius-md, 10px);
    border: 1px solid var(--hcis-color-border, #e2e8f0);
    background: var(--hcis-color-surface, #ffffff);
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
}

.pa-rating-btn:hover {
    border-color: var(--hcis-color-brand, #2563eb);
    background: rgba(37, 99, 235, 0.03);
    transform: translateY(-1px);
}

.pa-rating-btn.is-selected {
    background: var(--hcis-color-brand, #2563eb);
    border-color: var(--hcis-color-brand, #2563eb);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    transform: translateY(-1px);
}

.pa-rating-btn input {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    cursor: pointer;
}

.pa-rating-btn__number {
    font-size: 1.05rem;
    font-weight: 700;
}

.pa-rating-btn__caption {
    font-size: 0.68rem;
    line-height: 1.2;
    margin-top: 0.2rem;
    opacity: 0.85;
    white-space: nowrap;
}

.pa-rating-legend {
    display: flex;
    justify-content: space-between;
    font-size: 0.74rem;
    color: var(--hcis-color-text-soft, #94a3b8);
    padding-top: 0.25rem;
}

/* Essay Textarea */
.pa-essay-section {
    padding-left: 3.05rem;
}

.pa-essay-textarea {
    width: 100%;
    padding: 0.75rem 0.9rem;
    border-radius: var(--hcis-radius-md, 10px);
    border: 1px solid var(--hcis-color-border, #e2e8f0);
    background: var(--hcis-color-surface, #ffffff);
    font: inherit;
    font-size: 0.88rem;
    color: var(--hcis-color-text-main, #0f172a);
    resize: vertical;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.pa-essay-textarea:focus {
    outline: none;
    border-color: var(--hcis-color-brand, #2563eb);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

/* Footer Action Bar */
.pa-nilai-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 1.25rem 1.5rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: var(--hcis-color-surface, #ffffff);
    border: 1px solid var(--hcis-color-border, #e2e8f0);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.pa-nilai-footer__status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-nilai-footer__draft {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-nilai-footer__actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-left: auto;
}

@media (max-width: 640px) {
    .pa-nilai-page {
        padding: 0.75rem;
    }

    .pa-rating-section,
    .pa-essay-section {
        padding-left: 0;
    }

    .pa-rating-btn {
        min-width: 2.8rem;
        padding: 0.5rem;
    }

    .pa-rating-btn__caption {
        display: none;
    }

    .pa-nilai-footer {
        flex-direction: column;
        align-items: stretch;
    }

    .pa-nilai-footer__actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>
