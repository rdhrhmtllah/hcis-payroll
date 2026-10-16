<template>
    <section class="pa-wizard-lembar" :aria-label="`Lembar penilaian ${langkah?.nama_kategori ?? ''}`">
        <!-- Locked Category Warning Banner -->
        <HcisAlert
            v-if="langkah?.status === 'TERKUNCI'"
            variant="info"
            icon="lock-fill"
            :title="langkah.sebab_terkunci || 'Kategori ini masih terkunci'"
            description="Tautan lembar evaluasi pada tahapan ini akan terbuka secara otomatis setelah Anda menuntaskan tahapan sebelumnya."
            class="pa-lembar-alert"
        />

        <!-- Truncated List Notice -->
        <HcisAlert
            v-if="dipangkas"
            variant="warning"
            icon="exclamation-circle"
            :title="`Menampilkan ${lembar.length} dari ${total} lembar penilaian.`"
            description="Sebagian daftar dibatasi untuk optimalisasi performa."
            class="pa-lembar-alert"
        />

        <!-- Category Overview & Interactive Filter Bar -->
        <div class="pa-lembar-toolbar">
            <div v-if="petunjukKategori" class="pa-lembar-context">
                <p class="pa-lembar-desc">
                    <HcisIcon name="info-circle" size="2xs" class="pa-desc-icon" />
                    <span>{{ petunjukKategori }}</span>
                </p>
            </div>

            <!-- Search and Filter Controls -->
            <div class="pa-lembar-controls">
                <!-- Smart Filter Pills -->
                <div class="pa-filter-pills" role="tablist">
                    <button
                        type="button"
                        class="pa-filter-pill"
                        :class="{ 'is-active': filterAktif === 'all' }"
                        @click="filterAktif = 'all'"
                    >
                        <span>Semua</span>
                        <span class="pa-filter-count">{{ lembar.length }}</span>
                    </button>

                    <button
                        type="button"
                        class="pa-filter-pill"
                        :class="{ 'is-active': filterAktif === 'pending' }"
                        @click="filterAktif = 'pending'"
                    >
                        <span>Perlu Dinilai</span>
                        <span class="pa-filter-count is-pending">{{ pendingCount }}</span>
                    </button>

                    <button
                        type="button"
                        class="pa-filter-pill"
                        :class="{ 'is-active': filterAktif === 'done' }"
                        @click="filterAktif = 'done'"
                    >
                        <span>Selesai</span>
                        <span class="pa-filter-count is-done">{{ lembarTerisiCount }}</span>
                    </button>

                    <button
                        v-if="terlambatCount > 0"
                        type="button"
                        class="pa-filter-pill is-overdue"
                        :class="{ 'is-active': filterAktif === 'overdue' }"
                        @click="filterAktif = 'overdue'"
                    >
                        <span>Terlambat</span>
                        <span class="pa-filter-count is-danger">{{ terlambatCount }}</span>
                    </button>
                </div>

                <!-- Search Input with Clear Button -->
                <div class="pa-search-input-wrap">
                    <HcisIcon name="search" size="xs" class="pa-search-icon" />
                    <input
                        v-model="kataKunci"
                        type="text"
                        placeholder="Cari nama, jabatan, kode..."
                        class="pa-search-input"
                        aria-label="Cari peserta evaluasi"
                        @keydown.esc="kataKunci = ''"
                    />
                    <button
                        v-if="kataKunci"
                        type="button"
                        class="pa-search-clear"
                        aria-label="Hapus pencarian"
                        @click="kataKunci = ''"
                    >
                        <HcisIcon name="x-circle-fill" size="2xs" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty Search & Filter State -->
        <HcisEmptyState
            v-if="lembarTersaring.length === 0"
            variant="card"
            sentiment="neutral"
            size="sm"
            icon="search"
            title="Tidak Ada Peserta yang Sesuai"
            :description="kataKunci ? `Tidak ditemukan peserta dengan kata kunci '${kataKunci}'.` : 'Tidak ada peserta pada filter yang dipilih.'"
            class="pa-lembar-empty"
        >
            <template #actions>
                <HcisButton
                    label="Reset Pencarian &amp; Filter"
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-clockwise"
                    @click="resetFilter"
                />
            </template>
        </HcisEmptyState>

        <!-- Participant Cards Grid -->
        <ul v-else class="pa-wizard-lembar__daftar">
            <li
                v-for="b in lembarTersaring"
                :key="b.assignment_id"
                class="pa-wizard-lembar__kartu"
                :class="{
                    'is-terisi': terisi(b),
                    'is-terlambat': b.terlambat,
                    'is-locked': langkah?.status === 'TERKUNCI',
                }"
            >
                <!-- Card Header with Avatar & Details -->
                <div class="pa-card-person-header">
                    <div class="pa-person-avatar" :class="avatarClass(b)">
                        <span>{{ inisial(b.peserta?.nama) }}</span>
                    </div>

                    <div class="pa-wizard-lembar__orang">
                        <div class="pa-person-name-row">
                            <strong class="pa-person-name" :title="b.peserta?.nama">{{ b.peserta?.nama || 'Peserta' }}</strong>
                            <HcisBadge
                                v-if="b.diri_sendiri"
                                label="Diri Sendiri"
                                variant="brand"
                                appearance="subtle"
                                size="xs"
                            />
                            <span v-else-if="b.peserta?.kode_karyawan" class="pa-person-code">
                                Kode: {{ b.peserta.kode_karyawan }}
                            </span>
                        </div>

                        <span class="pa-wizard-lembar__sub">
                            {{
                                b.diri_sendiri
                                    ? 'Penilaian performa diri sendiri'
                                    : [b.peserta?.jabatan, b.peserta?.divisi].filter(Boolean).join(' · ') || 'Staff'
                            }}
                        </span>
                    </div>

                    <div class="pa-card-status-col">
                        <HcisStatusBadge
                            :label="statusTampil(b).label"
                            :variant="statusTampil(b).variant"
                            size="sm"
                            :dot="terisi(b)"
                        />
                    </div>
                </div>

                <!-- Card Meta Bar: Deadlines & Credentials -->
                <div class="pa-card-meta-row">
                    <!-- Deadline info -->
                    <div v-if="b.terlambat" class="pa-meta-chip is-danger">
                        <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                        <span class="pa-wizard-lembar__tenggat is-terlambat">Terlambat {{ b.hari_terlambat }} hari</span>
                    </div>
                    <div v-else-if="b.tenggat" class="pa-meta-chip">
                        <HcisIcon name="calendar-event" size="2xs" />
                        <span class="pa-wizard-lembar__tenggat">Tenggat: {{ tanggal(b.tenggat) }}</span>
                    </div>

                    <!-- Token/OTP credentials if not filled with copy action -->
                    <div v-if="!terisi(b) && !b.kiriman_tertahan && (b.short_token || b.akses_otp)" class="pa-meta-chip is-code">
                        <HcisIcon name="key-fill" size="2xs" />
                        <span class="pa-wizard-lembar__kode">
                            Kode: <code>{{ b.short_token }}</code>
                            <template v-if="b.akses_otp"> · OTP: <code>{{ b.akses_otp }}</code></template>
                        </span>
                        <button
                            type="button"
                            class="pa-btn-copy-code"
                            title="Salin kode akses ke clipboard"
                            aria-label="Salin kode akses"
                            @click="salinKode(b)"
                        >
                            <HcisIcon name="clipboard" size="2xs" />
                        </button>
                    </div>
                </div>

                <!-- Card Action Footer -->
                <div class="pa-wizard-lembar__aksi">
                    <div v-if="b.kiriman_tertahan" class="pa-badge-tertahan" data-uji="kiriman-tertahan">
                        <HcisIcon name="hourglass-split" size="xs" />
                        <span>Jawaban sudah diterima dan sedang diperiksa admin. Tidak perlu mengisi ulang.</span>
                    </div>

                    <HcisButton
                        v-else-if="!terisi(b) && urlAman(b.url_ujian)"
                        :label="membukaId === b.assignment_id ? 'Membuka lembar...' : 'Mulai Menilai'"
                        variant="primary"
                        size="sm"
                        leading-icon="pencil-square"
                        trailing-icon="arrow-right"
                        :loading="membukaId === b.assignment_id"
                        :disabled="membukaId !== null && membukaId !== b.assignment_id"
                        data-uji="mulai-menilai"
                        class="pa-btn-mulai"
                        @click="mulaiMenilai(b)"
                    />

                    <div v-else-if="terisi(b)" class="pa-badge-terisi">
                        <HcisIcon name="check-circle-fill" size="xs" />
                        <span>{{ b.status_penugasan === 'SELESAI' ? 'Sudah terkirim' : 'Sudah selesai diisi' }}</span>
                    </div>

                    <span v-else-if="langkah?.status !== 'TERKUNCI'" class="pa-wizard-lembar__sub pa-text-muted">
                        <HcisIcon name="hourglass-split" size="2xs" />
                        Tautan lembar belum tersedia
                    </span>
                </div>
            </li>
        </ul>

        <!-- Calibration / Transition Card When Step is Complete (Menggunakan HcisCard Identik Tab Hasil) -->
        <HcisCard
            v-if="langkah?.status === 'TUNTAS'"
            :title="langkah.boleh_kalibrasi ? 'Kategori Ini Selesai Diisi — Siap Kalibrasi!' : 'Kategori Ini Selesai Diisi!'"
            :description="langkah.boleh_kalibrasi
                ? 'Seluruh lembar pada tahapan ini telah lengkap. Anda dapat meninjau matriks perbandingan skor dan melakukan kalibrasi sebelum beralih ke tahapan berikutnya.'
                : 'Seluruh lembar pada tahapan ini telah tuntas. Silakan lanjut ke tahapan evaluasi berikutnya.'"
            :icon="langkah.boleh_kalibrasi ? 'sliders' : 'check2-all'"
            :sentiment="langkah.boleh_kalibrasi ? 'purple' : 'success'"
            variant="glass"
            pattern="rings"
            class="pa-wizard-lembar__lanjut-card"
        >
            <template #actions>
                <HcisButton
                    v-if="langkah.boleh_kalibrasi"
                    label="Buka Matriks Kalibrasi"
                    variant="primary"
                    size="md"
                    leading-icon="sliders"
                    data-uji="ke-pratinjau"
                    class="pa-btn-kalibrasi"
                    @click="emit('ke-pratinjau')"
                />
                <!-- Kategori tanpa kalibrasi tidak punya Matriks: langsung lanjut. -->
                <HcisButton
                    v-else
                    :label="labelLanjut || 'Lanjut ke Tahap Berikutnya'"
                    variant="primary"
                    size="md"
                    trailing-icon="arrow-right"
                    data-uji="lanjut"
                    class="pa-btn-kalibrasi"
                    @click="emit('lanjut')"
                />
            </template>
        </HcisCard>

        <!-- Layar tunggu selagi HC Learn dimuat di tab yang sama: tanpa ini halaman terlihat
             diam (atau kosong) beberapa detik dan penilai menekan tombolnya berulang kali. -->
        <Teleport to="body">
            <Transition name="pa-membuka-fade">
                <div v-if="membukaId !== null" class="pa-membuka-overlay" role="status" aria-live="polite" data-uji="membuka-lembar">
                    <div class="pa-membuka-kartu">
                        <span class="pa-membuka-spinner" aria-hidden="true" />
                        <strong>Membuka lembar penilaian</strong>
                        <span>{{ namaDibuka ? `Untuk ${namaDibuka}. ` : '' }}Mohon tunggu sebentar...</span>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisEmptyState,
    HcisIcon,
    HcisStatusBadge,
    hcisMessage,
} from '@/components/ui';
import { urlAman } from '@/components/ui/urlAman.js';

import { SUDAH_DIKERJAKAN, statusLembar, tanggal } from '../../utils/statusLembar.js';

defineOptions({ name: 'PaWizardLembarList' });

const props = defineProps({
    langkah: { type: Object, default: null },
    lembar: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    dipangkas: { type: Boolean, default: false },
    labelLanjut: { type: String, default: '' },
});

const emit = defineEmits(['ke-pratinjau', 'lanjut']);

/* Lembar yang sedang dibuka. HC Learn dibuka di TAB YANG SAMA: tab baru menampilkan halaman
   kosong selama HC Learn dimuat, dan sesudah selesai penilai dipulangkan ke HCIS — di tab
   baru itu, sehingga tab HCIS yang lama tertinggal dengan status basi. */
const membukaId = ref(null);
const namaDibuka = ref('');

function mulaiMenilai(b) {
    const url = urlAman(b.url_ujian);
    if (!url || membukaId.value !== null) return;

    membukaId.value = b.assignment_id;
    namaDibuka.value = b.diri_sendiri ? 'diri sendiri' : (b.peserta?.nama || '');
    window.location.assign(url);
}

/* Tombol Kembali peramban memulihkan halaman ini dari cache apa adanya — lengkap dengan
   layar tunggunya. `pageshow` ber-`persisted` menandai pemulihan itu. */
function saatHalamanTampil(e) {
    if (e.persisted) membukaId.value = null;
}

onMounted(() => window.addEventListener('pageshow', saatHalamanTampil));
onBeforeUnmount(() => window.removeEventListener('pageshow', saatHalamanTampil));

const kataKunci = ref('');
const filterAktif = ref('all'); // 'all' | 'pending' | 'done' | 'overdue'

const lembarTerisiCount = computed(() => props.lembar.filter((b) => terisi(b)).length);
const pendingCount = computed(() => props.lembar.filter((b) => !terisi(b)).length);
const terlambatCount = computed(() => props.lembar.filter((b) => Boolean(b.terlambat)).length);

const lembarTersaring = computed(() => {
    let list = props.lembar;

    // Filter by status pill
    if (filterAktif.value === 'pending') {
        list = list.filter((b) => !terisi(b));
    } else if (filterAktif.value === 'done') {
        list = list.filter((b) => terisi(b));
    } else if (filterAktif.value === 'overdue') {
        list = list.filter((b) => Boolean(b.terlambat));
    }

    // Filter by search keyword
    if (!kataKunci.value.trim()) return list;

    const q = kataKunci.value.toLowerCase().trim();
    return list.filter((b) => {
        const nama = (b.peserta?.nama || '').toLowerCase();
        const jabatan = (b.peserta?.jabatan || '').toLowerCase();
        const divisi = (b.peserta?.divisi || '').toLowerCase();
        const kodeKaryawan = (b.peserta?.kode_karyawan || '').toLowerCase();
        return nama.includes(q) || jabatan.includes(q) || divisi.includes(q) || kodeKaryawan.includes(q);
    });
});

const petunjukKategori = computed(() => {
    const kode = props.langkah?.kode_kategori?.toUpperCase();
    if (kode === 'SELF' || kode === 'DIRI_SENDIRI') {
        return 'Lakukan penilaian mandiri secara objektif mengenai pencapaian target kerja dan implementasi kompetensi Anda selama cycle evaluasi berlangsung.';
    }
    if (kode === 'ATASAN') {
        return 'Berikan evaluasi terhadap atasan langsung Anda secara profesional dan konstruktif untuk mendukung kepemimpinan yang lebih baik.';
    }
    if (kode === 'PEER' || kode === 'REKAN') {
        return 'Evaluasi rekan kerja sejawat berdasarkan kolaborasi harian, keandalan, dan komunikasi profesional tim.';
    }
    if (kode === 'BAWAHAN' || kode === 'SUBORDINATE') {
        return 'Evaluasi performa dan kompetensi bawahan langsung Anda untuk mendukung perkembangan karier serta akurasi penilaian tim.';
    }
    return 'Isi seluruh lembar evaluasi pada kategori ini. Tautan lembar penilaian dapat diakses melalui tombol Mulai Menilai.';
});

function terisi(b) {
    return SUDAH_DIKERJAKAN.includes(b.status_penugasan);
}

/* Kiriman tertahan: HC Learn sudah mengirim jawaban, tetapi butirnya tidak cocok dengan paket
   soal sehingga statusnya belum naik. Tanpa label ini lembarnya terbaca "Belum dimulai" dan
   penilai mengira jawabannya hilang. */
function statusTampil(b) {
    if (b.kiriman_tertahan) return { label: 'Diterima, menunggu admin', variant: 'warning' };
    return statusLembar(b.status_penugasan);
}

function inisial(nama) {
    return String(nama || 'P')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p.charAt(0).toUpperCase())
        .join('');
}

function avatarClass(b) {
    if (b.diri_sendiri) return 'is-self';
    const kode = props.langkah?.kode_kategori?.toUpperCase();
    if (kode === 'ATASAN') return 'is-atasan';
    if (kode === 'BAWAHAN') return 'is-bawahan';
    return 'is-peer';
}

function resetFilter() {
    kataKunci.value = '';
    filterAktif.value = 'all';
}

async function salinKode(b) {
    const teks = [b.short_token, b.akses_otp ? `OTP: ${b.akses_otp}` : null].filter(Boolean).join(' · ');
    if (!teks) return;

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(teks);
        } else {
            const input = document.createElement('textarea');
            input.value = teks;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        }
        hcisMessage.success(`Kode akses ${b.peserta?.nama || ''} disalin ke clipboard.`);
    } catch {
        hcisMessage.info(`Kode: ${teks}`);
    }
}
</script>

<style scoped>
.pa-wizard-lembar {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-lembar-alert {
    border-radius: var(--hcis-radius-xl, 14px);
}

.pa-lembar-toolbar {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1.15rem 1.35rem;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
}

.pa-lembar-context {
    display: flex;
    align-items: center;
    max-width: 52rem;
}

.pa-lembar-desc {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-size: 0.825rem;
    color: var(--hcis-color-text-muted, #64748b);
    line-height: 1.45;
}

.pa-desc-icon {
    color: var(--hcis-color-brand-600, #4f46e5);
    flex-shrink: 0;
}

.pa-lembar-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding-top: 0.85rem;
    border-top: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-filter-pills {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.85rem;
    border-radius: 999px;
    border: 1.5px solid rgba(226, 232, 240, 0.9);
    background: rgba(248, 250, 252, 0.9);
    color: var(--hcis-color-text-main, #0f172a);
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 160ms cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
}

.pa-filter-pill:hover {
    background: #ffffff;
    border-color: rgba(99, 102, 241, 0.4);
    transform: translateY(-1px);
}

.pa-filter-pill.is-active {
    background: var(--hcis-color-brand-600, #4f46e5);
    color: #ffffff;
    border-color: var(--hcis-color-brand-600, #4f46e5);
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
}

.pa-filter-count {
    padding: 0.05rem 0.45rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 750;
    background: rgba(0, 0, 0, 0.06);
    color: inherit;
}

.pa-filter-pill.is-active .pa-filter-count {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

.pa-filter-pill.is-overdue {
    border-color: rgba(239, 68, 68, 0.35);
}

.pa-filter-pill.is-overdue.is-active {
    background: var(--hcis-danger, #ef4444);
    border-color: var(--hcis-danger, #ef4444);
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.pa-search-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 17.5rem;
    max-width: 100%;
}

.pa-search-icon {
    position: absolute;
    left: 0.85rem;
    color: var(--hcis-color-text-muted, #94a3b8);
    pointer-events: none;
}

.pa-search-input {
    width: 100%;
    padding: 0.5rem 2rem 0.5rem 2.4rem;
    font-size: 0.825rem;
    color: var(--hcis-color-text-main, #0f172a);
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 10px);
    outline: none;
    transition: all 0.2s ease;
}

.pa-search-input:focus {
    background: #ffffff;
    border-color: var(--hcis-color-brand-600, #4f46e5);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

.pa-search-clear {
    position: absolute;
    right: 0.6rem;
    background: transparent;
    border: none;
    color: var(--hcis-color-text-muted, #94a3b8);
    cursor: pointer;
    padding: 0.2rem;
    display: flex;
    align-items: center;
}

.pa-search-clear:hover {
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-lembar-empty {
    border-radius: var(--hcis-radius-xl, 16px);
}

/* Participant Cards Grid */
.pa-wizard-lembar__daftar {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: var(--hcis-space-3, 0.875rem);
    list-style: none;
    margin: 0;
    padding: 0;
    min-width: 0;
}

.pa-wizard-lembar__kartu {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.95rem;
    padding: 1.25rem;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
    transition: all 180ms cubic-bezier(0.4, 0, 0.2, 1);
    min-width: 0;
}

.pa-wizard-lembar__kartu:hover {
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.08);
    transform: translateY(-2px);
    background: rgba(255, 255, 255, 0.98);
}

/* Distinct Emerald Green Active Status Identification */
.pa-wizard-lembar__kartu.is-terisi {
    background: radial-gradient(ellipse at 100% 0%, rgba(209, 250, 229, 0.35) 0%, rgba(255, 255, 255, 0.95) 70%);
    border: 1px solid rgba(16, 185, 129, 0.35);
    border-left: 4.5px solid var(--hcis-success, #10b981);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.12);
}

.pa-card-person-header {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}

.pa-person-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-size: 0.9rem;
    font-weight: 850;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}

.pa-person-avatar.is-self {
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25);
}

.pa-person-avatar.is-atasan {
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.25);
}

.pa-person-avatar.is-bawahan {
    background: linear-gradient(135deg, #10b981, #059669);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
}

.pa-person-avatar.is-peer {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
}

.pa-wizard-lembar__orang {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex: 1;
    min-width: 0;
}

.pa-person-name-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-person-name {
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 14rem;
    line-height: 1.25;
}

.pa-person-code {
    font-size: 0.72rem;
    color: var(--hcis-color-text-muted, #94a3b8);
    font-weight: 600;
}

.pa-wizard-lembar__sub {
    font-size: 0.78rem;
    color: var(--hcis-color-text-muted, #64748b);
    line-height: 1.35;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-card-status-col {
    flex-shrink: 0;
}

.pa-card-meta-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.45rem;
    padding: 0.55rem 0.85rem;
    background: rgba(248, 250, 252, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    font-size: 0.75rem;
}

.pa-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-meta-chip.is-danger {
    color: var(--hcis-danger, #dc2626);
    font-weight: 600;
}

.pa-meta-chip.is-code {
    color: var(--hcis-color-text-main, #334155);
}

.pa-wizard-lembar__tenggat.is-terlambat {
    color: var(--hcis-danger, #dc2626);
    font-weight: 650;
}

.pa-wizard-lembar__kode code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.75rem;
    padding: 0.1rem 0.35rem;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 4px;
    font-weight: 700;
    color: var(--hcis-color-brand-600, #4f46e5);
}

.pa-btn-copy-code {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.2rem 0.35rem;
    border-radius: 4px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    background: #ffffff;
    color: var(--hcis-color-text-muted, #64748b);
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-btn-copy-code:hover {
    color: var(--hcis-color-brand-600, #4f46e5);
    border-color: var(--hcis-color-brand-600, #4f46e5);
}

.pa-wizard-lembar__aksi {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    padding-top: 0.45rem;
    border-top: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-btn-mulai {
    width: 100%;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.25);
    border-radius: 10px;
    font-weight: 700;
}

.pa-badge-terisi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    width: 100%;
    padding: 0.5rem 0.85rem;
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.82rem;
    font-weight: 700;
}

.pa-badge-tertahan {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    width: 100%;
    padding: 0.5rem 0.85rem;
    background: rgba(245, 158, 11, 0.1);
    color: #b45309;
    border: 1px solid rgba(245, 158, 11, 0.25);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.8rem;
    font-weight: 600;
    line-height: 1.35;
}

.pa-wizard-lembar__lanjut-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    flex-wrap: wrap;
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.95) 0%, rgba(245, 243, 255, 0.95) 100%);
    border: 1.5px solid rgba(99, 102, 241, 0.3);
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.08);
}

.pa-lanjut-card-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
    min-width: 240px;
}

.pa-lanjut-icon-box {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.85rem;
    height: 2.85rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
}

.pa-lanjut-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    letter-spacing: -0.01em;
}

.pa-lanjut-desc {
    margin: 0.25rem 0 0;
    font-size: 0.825rem;
    color: var(--hcis-color-text-muted, #475569);
    line-height: 1.45;
}

.pa-btn-kalibrasi {
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
    white-space: nowrap;
    border-radius: 10px;
    font-weight: 700;
}

@media (max-width: 768px) {
    .pa-wizard-lembar__daftar {
        grid-template-columns: 1fr;
        gap: 0.65rem;
    }

    .pa-lembar-toolbar {
        padding: 0.75rem 0.85rem;
        gap: 0.65rem;
    }

    .pa-lembar-desc,
    .pa-lanjut-desc {
        display: none !important;
    }

    .pa-lembar-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .pa-filter-pills {
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 0.25rem;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }

    .pa-filter-pills::-webkit-scrollbar {
        display: none;
    }

    .pa-filter-pill {
        flex-shrink: 0;
        padding: 0.35rem 0.65rem;
        font-size: 0.75rem;
    }

    .pa-search-input-wrap {
        width: 100%;
    }

    .pa-search-input {
        font-size: 16px;
        height: 2.35rem;
    }

    .pa-wizard-lembar__kartu {
        padding: 0.75rem 0.85rem;
        gap: 0.65rem;
        border-radius: var(--hcis-radius-lg, 12px);
    }

    .pa-person-avatar {
        width: 2.25rem;
        height: 2.25rem;
        font-size: 0.75rem;
        border-radius: 10px;
    }

    .pa-person-name {
        font-size: 0.875rem;
        max-width: 100%;
    }

    .pa-card-meta-row {
        padding: 0.4rem 0.6rem;
        font-size: 0.7rem;
    }

    .pa-btn-mulai {
        height: 2.25rem;
        font-size: 0.8rem;
    }

    .pa-badge-terisi {
        padding: 0.4rem 0.6rem;
        font-size: 0.75rem;
    }

    .pa-wizard-lembar__lanjut-card {
        flex-direction: column;
        align-items: stretch;
        padding: 0.85rem 1rem;
        gap: 0.75rem;
    }

    .pa-lanjut-icon-box {
        width: 2.35rem;
        height: 2.35rem;
        border-radius: 10px;
    }

    .pa-lanjut-title {
        font-size: 0.925rem;
    }

    .pa-lanjut-desc {
        font-size: 0.775rem;
    }

    .pa-wizard-lembar__lanjut,
    .pa-wizard-lembar__lanjut > * {
        width: 100%;
    }
}

.pa-membuka-overlay {
    position: fixed;
    inset: 0;
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(3px);
}

.pa-membuka-kartu {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    width: min(320px, 100%);
    padding: 1.5rem 1.25rem;
    background: var(--hcis-surface, #fff);
    color: var(--hcis-text-muted, #475569);
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.2);
    font-size: 0.85rem;
    text-align: center;
}

.pa-membuka-kartu strong {
    color: var(--hcis-text, #0f172a);
    font-size: 0.95rem;
}

.pa-membuka-spinner {
    width: 2.25rem;
    height: 2.25rem;
    margin-bottom: 0.25rem;
    border: 3px solid rgba(79, 70, 229, 0.2);
    border-top-color: #4f46e5;
    border-radius: 50%;
    animation: pa-membuka-putar 0.8s linear infinite;
}

@keyframes pa-membuka-putar {
    to {
        transform: rotate(360deg);
    }
}

.pa-membuka-fade-enter-active,
.pa-membuka-fade-leave-active {
    transition: opacity 0.15s ease;
}

.pa-membuka-fade-enter-from,
.pa-membuka-fade-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .pa-membuka-spinner {
        animation-duration: 2s;
    }
}
</style>
