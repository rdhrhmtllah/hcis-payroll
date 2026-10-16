<template>
    <div class="pa-wizard">
        <!-- 1. Initial Loading Skeleton State -->
        <div v-if="memuat && !wizard" class="pa-wizard-loading-grid">
            <HcisSkeleton height="11rem" rounded="xl" />
            <HcisSkeleton height="4rem" rounded="xl" />
            <div class="pa-wizard-loading-cards">
                <HcisSkeleton v-for="n in 3" :key="`skel-${n}`" height="9rem" rounded="xl" />
            </div>
        </div>

        <!-- 2. Error State -->
        <HcisResultState
            v-else-if="galat"
            status="danger"
            variant="glass"
            layout="compact"
            title="Tugas penilaian gagal dimuat"
            :description="galat.pesan"
            :error-code="galat.kode"
            :request-id="galat.requestId"
            primary-text="Coba lagi"
            primary-icon="arrow-clockwise"
            @primary-click="muat()"
        />

        <!-- 3. Empty State -->
        <HcisEmptyState
            v-else-if="kosong || (wizard && !wizard.siklus)"
            variant="card"
            sentiment="neutral"
            size="md"
            icon="clipboard2-check"
            title="Belum Ada Tugas Penilaian"
            description="Tidak ada siklus evaluasi aktif yang memuat tugas penilaian untuk akun Anda saat ini."
        >
            <template #actions>
                <HcisButton
                    label="Segarkan Data"
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-clockwise"
                    :loading="memuat"
                    @click="muat()"
                />
            </template>
        </HcisEmptyState>

        <!-- 4. Main Executive Flow -->
        <template v-else-if="wizard">
            <!-- Unified Executive Context Header: Cycle + Stepper Pipeline (Menggabungkan header dan alur evaluasi dalam 1 section terpadu tanpa widget berulang) -->
            <HcisCard
                variant="glass"
                pattern="rings"
                class="pa-wizard-unified-card"
                :class="{ 'is-tuntas': isSemuaTuntas }"
            >
                <div class="pa-unified-header">
                    <!-- Sisi Kiri: Info Cycle & Status Progres Ringkas -->
                    <div class="pa-unified-header__main">
                        <div class="pa-unified-icon-box" :class="{ 'is-done': sudahTerkirim || isSemuaTuntas }">
                            <HcisIcon :name="sudahTerkirim ? 'patch-check-fill' : 'clipboard2-check-fill'" size="sm" />
                        </div>
                        <div class="pa-unified-header__titles">
                            <div class="pa-unified-kicker-row">
                                <span class="pa-unified-kicker">{{ siklus?.kode_cycle || 'EVALUASI 360°' }}</span>
                                <HcisBadge
                                    :label="sudahTerkirim ? 'Sudah Terkirim' : isSemuaTuntas ? 'Siap Dikirim' : `${totalTerisi}/${totalLembar} Lembar`"
                                    :variant="sudahTerkirim || isSemuaTuntas ? 'success' : 'warning'"
                                    appearance="subtle"
                                    size="xs"
                                    dot
                                />
                                <span v-if="totalTerkalibrasi > 0" class="pa-unified-calib-pill">
                                    <HcisIcon name="sliders" size="2xs" />
                                    <span>{{ totalTerkalibrasi }} kalibrasi</span>
                                </span>
                            </div>
                            <h2 class="pa-unified-title">{{ siklus?.nama_cycle || 'Tugas Penilaian 360°' }}</h2>
                            <p class="pa-unified-desc">{{ deskripsiCockpit }}</p>
                        </div>
                    </div>

                    <!-- Sisi Kanan: Aksi & Ganti Cycle -->
                    <div class="pa-unified-actions">
                        <HcisSelect
                            v-if="(wizard.siklus_tersedia || []).length > 1"
                            :model-value="siklus?.cycle_id"
                            size="sm"
                            :clearable="false"
                            aria-label="Pilih cycle penilaian"
                            class="pa-wizard-pilih-siklus"
                            data-uji="pilih-siklus"
                            @update:model-value="(id) => denganKonfirmasi((o) => gantiSiklus(id, o))"
                        >
                            <HcisSelectOption
                                v-for="k in wizard.siklus_tersedia"
                                :key="k.cycle_id"
                                :value="k.cycle_id"
                                :label="`${k.nama_cycle || k.kode_cycle}${k.sudah_terkirim_semua ? ' · terkirim' : ''}`"
                            />
                        </HcisSelect>
                        <HcisButton
                            label="Segarkan"
                            variant="secondary"
                            size="xs"
                            leading-icon="arrow-clockwise"
                            :loading="memuat"
                            aria-label="Perbarui tugas penilaian"
                            title="Perbarui tugas penilaian"
                            class="pa-btn-refresh"
                            @click="denganKonfirmasi((o) => perbarui(o))"
                        />
                    </div>
                </div>

                <!-- Divider pemisah halus -->
                <div class="pa-unified-divider" />

                <!-- Embedded Interactive Stepper -->
                <div class="pa-unified-stepper-wrap">
                    <PaWizardStepper
                        :langkah="langkah"
                        :kode-dilihat="tampilan === 'ringkasan' ? null : kodeDilihat"
                        :ringkasan-terbuka="wizard.langkah_aktif === 'RINGKASAN' || Boolean(wizard.boleh_kirim) || Boolean(wizard.pengiriman?.sudah_terkirim_semua)"
                        @pilih="pilihLangkah"
                    />
                </div>
            </HcisCard>

            <!-- Banner Terkirim di-hide karena informasi sudah ditampilkan di kartu rekapitulasi/ringkasan di bawahnya -->


            <!-- Category Sub-Header & Dual-Segmented Capsule Switcher (Menyatu dengan Kalibrasi Bar bila aktif) -->
            <div
                v-if="tampilan !== 'ringkasan' && langkahDilihat"
                class="pa-category-view-switcher-bar"
                :class="{ 'is-kalibrasi-mode': tampilan === 'pratinjau' && pratinjau }"
            >
                <div class="pa-category-switcher-top">
                    <div class="pa-view-switcher-meta">
                        <div class="pa-view-kicker-row">
                            <span class="pa-view-kicker">TAHAP EVALUASI</span>
                            <HcisBadge
                                :label="`Langkah ${langkahDilihat.langkah_ke} dari ${langkah.length}`"
                                variant="brand"
                                appearance="glass"
                                size="xs"
                            />
                            <HcisBadge
                                v-if="langkahDilihat.status === 'TUNTAS'"
                                label="Kategori Tuntas"
                                variant="success"
                                appearance="glass"
                                size="xs"
                                dot
                            />
                        </div>
                        <h3 class="pa-view-category-name">{{ langkahDilihat.nama_kategori }}</h3>
                    </div>

                    <!-- Capsule Switcher between Lembar & Kalibrasi — hanya untuk kategori yang boleh dikalibrasi -->
                    <div v-if="bolehPratinjau(langkahDilihat)" class="pa-view-capsule-switch" role="tablist">
                        <button
                            type="button"
                            role="tab"
                            class="pa-capsule-btn"
                            :class="{ 'is-active': tampilan === 'lembar' }"
                            :aria-selected="tampilan === 'lembar'"
                            @click="denganKonfirmasi((o) => gantiTampilan('lembar', o))"
                        >
                            <HcisIcon name="list-check" size="2xs" />
                            <span class="pa-capsule-label-full">Daftar Lembar</span>
                            <span class="pa-capsule-label-short">Daftar</span>
                            <span class="pa-capsule-badge">{{ langkahDilihat.jumlah_terisi }}/{{ langkahDilihat.jumlah_lembar }}</span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            class="pa-capsule-btn"
                            :class="{
                                'is-active': tampilan === 'pratinjau',
                                'is-disabled': langkahDilihat.status === 'TERKUNCI'
                            }"
                            :disabled="langkahDilihat.status === 'TERKUNCI'"
                            :aria-selected="tampilan === 'pratinjau'"
                            @click="denganKonfirmasi((o) => gantiTampilan('pratinjau', o))"
                        >
                            <HcisIcon name="sliders" size="2xs" />
                            <span class="pa-capsule-label-full">Matriks Kalibrasi</span>
                            <span class="pa-capsule-label-short">Matriks</span>
                            <span v-if="langkahDilihat.jumlah_terkalibrasi > 0" class="pa-capsule-badge is-brand">
                                {{ langkahDilihat.jumlah_terkalibrasi }}
                            </span>
                            <span v-else class="pa-capsule-subtag">
                                {{ langkahDilihat.boleh_kalibrasi ? 'Siap' : 'Tinjau' }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Bagian Bawah Terpadu: Toolbar Konteks Kalibrasi (Hanya muncul saat Matriks Kalibrasi aktif) -->
                <div v-if="tampilan === 'pratinjau' && pratinjau" class="pa-unified-kalibrasi-bar">
                    <div class="pa-unified-kalibrasi-context">
                        <p class="pa-unified-kalibrasi-desc">
                            <HcisIcon name="info-circle" size="2xs" class="pa-unified-desc-icon" />
                            <span>
                                {{
                                    pratinjau.kategori?.boleh_kalibrasi
                                        ? 'Bandingkan skor penilaian antar peserta secara objektif. Anda dapat langsung menyesuaikan skor pada tabel di bawah, lalu menyimpannya per orang beserta alasan perubahan.'
                                        : 'Berikut rekapitulasi skor penilaian pada kategori ini (mode tinjau, tanpa penyesuaian skor).'
                                }}
                            </span>
                        </p>
                    </div>

                    <div class="pa-unified-kalibrasi-actions">
                        <div class="pa-unified-stat-pills">
                            <HcisBadge
                                :label="`${(pratinjau.orang || []).length} Peserta`"
                                variant="neutral"
                                appearance="subtle"
                                size="xs"
                                leading-icon="people"
                            />
                            <HcisBadge
                                :label="`${(pratinjau.kompetensi || []).length} Dimensi Kompetensi`"
                                variant="neutral"
                                appearance="subtle"
                                size="xs"
                                leading-icon="diagram-3"
                            />
                            <HcisBadge
                                v-if="kotor"
                                label="Ada Draf Belum Disimpan"
                                variant="warning"
                                appearance="glass"
                                size="xs"
                                dot
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step Content Area -->
            <div class="pa-wizard-content-wrap">
                <HcisSkeleton v-if="memuatIsi" :lines="4" />

                <HcisResultState
                    v-else-if="galatIsi"
                    status="danger"
                    variant="glass"
                    layout="compact"
                    title="Isi langkah gagal dimuat"
                    :description="galatIsi.pesan"
                    :error-code="galatIsi.kode"
                    :request-id="galatIsi.requestId"
                    primary-text="Coba lagi"
                    primary-icon="arrow-clockwise"
                    @primary-click="muatIsi()"
                />

                <Transition v-else name="pa-view-fade" mode="out-in">
                    <PaWizardLembarList
                        v-if="tampilan === 'lembar' && lembar"
                        key="lembar"
                        :langkah="lembar.langkah"
                        :lembar="lembar.lembar"
                        :total="lembar.total"
                        :dipangkas="lembar.dipangkas"
                        :label-lanjut="labelLanjut"
                        @ke-pratinjau="denganKonfirmasi((o) => gantiTampilan('pratinjau', o))"
                        @lanjut="denganKonfirmasi((o) => lanjut(o))"
                    />

                    <PaWizardPratinjauKalibrasi
                        v-else-if="tampilan === 'pratinjau' && pratinjau"
                        key="pratinjau"
                        :pratinjau="pratinjau"
                        :menyimpan-id="menyimpanId"
                        :progres-simpan="progresSimpan"
                        :galat-simpan="galatSimpan"
                        :label-lanjut="labelLanjut"
                        @kotor="(v) => (kotor = v)"
                        @simpan="simpan"
                        @simpan-banyak="simpanBanyak"
                        @lanjut="denganKonfirmasi((o) => lanjut(o))"
                        @ke-lembar="denganKonfirmasi((o) => gantiTampilan('lembar', o))"
                    />

                    <PaWizardRingkasanKirim
                        v-else-if="tampilan === 'ringkasan'"
                        key="ringkasan"
                        :langkah="langkah"
                        :pengiriman="wizard.pengiriman"
                        :boleh-kirim="wizard.boleh_kirim"
                        :mengirim="mengirim"
                        :galat-kirim="galatKirim"
                        @buka="(kode) => buka(kode, 'pratinjau')"
                        @kirim="kirimSemua"
                    />
                </Transition>
            </div>
        </template>

        <!-- Unsaved Changes Prompt Dialog -->
        <HcisDialog
            v-model="konfirmasiTerbuka"
            title="Perubahan Belum Disimpan"
            size="sm"
            variant="glass"
            sentiment="warning"
        >
            <div class="pa-dialog-warning-body">
                <div class="pa-dialog-warning-icon">
                    <HcisIcon name="exclamation-triangle-fill" size="md" />
                </div>
                <div>
                    <h4 class="pa-warning-title">Ada Perubahan Skor Belum Disimpan</h4>
                    <p class="pa-wizard__konfirmasi">
                        Skor atau uraian yang Anda sesuaikan di tahapan ini belum disimpan. Apakah Anda yakin ingin berpindah dan membuang perubahan tersebut?
                    </p>
                </div>
            </div>
            <template #footer>
                <div class="pa-wizard__aksi">
                    <HcisButton label="Tetap di Sini" variant="secondary" @click="konfirmasiTerbuka = false" />
                    <HcisButton label="Buang Perubahan" variant="danger" @click="jalankanTertunda" />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDialog,
    HcisEmptyState,
    HcisIcon,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisStatCard,
    hcisMessage,
} from '@/components/ui';

import { LANGKAH_RINGKASAN, bolehPratinjau, usePaWizardPenilaian } from '../composables/usePaWizardPenilaian.js';
import PaWizardLembarList from './PaWizardLembarList.vue';
import PaWizardPratinjauKalibrasi from './PaWizardPratinjauKalibrasi.vue';
import PaWizardRingkasanKirim from './PaWizardRingkasanKirim.vue';
import PaWizardStepper from './PaWizardStepper.vue';

defineOptions({ name: 'PaSayaWizardPenilaian' });

const props = defineProps({
    /** Kategori penilai yang dibuka pertama kali, dari `?kategori=` di URL. */
    kategoriAwal: { type: String, default: null },
});

const emit = defineEmits(['terkirim']);

const {
    wizard,
    siklus,
    langkah,
    langkahDilihat,
    langkahBerikut,
    langkahSebelum,
    kodeDilihat,
    tampilan,
    kosong,
    memuat,
    galat,
    lembar,
    pratinjau,
    memuatIsi,
    galatIsi,
    menyimpanId,
    galatSimpan,
    kotor,
    mengirim,
    galatKirim,
    muat,
    muatIsi,
    buka,
    gantiTampilan,
    bukaRingkasan,
    gantiSiklus,
    lanjut,
    simpanKalibrasi,
    simpanKalibrasiBanyak,
    progresSimpan,
    kirim,
} = usePaWizardPenilaian({ kategoriAwal: props.kategoriAwal });

const konfirmasiTerbuka = ref(false);
let tertunda = null;

const totalLembar = computed(() => Number(wizard.value?.pengiriman?.total) || 0);
/* Sama dengan stepper: lembar yang menunggu verifikasi HC Learn SUDAH diisi. Tanpa itu kartu
   atas berkata "tersisa 1" sementara seluruh kategori sudah berstatus tuntas. */
const totalTerisi = computed(() => {
    const p = wizard.value?.pengiriman ?? {};
    return (Number(p.terkumpul) || 0) + (Number(p.terkirim) || 0) + (Number(p.menunggu_verifikasi) || 0);
});
const totalSisa = computed(() => Math.max(0, totalLembar.value - totalTerisi.value));
const persenSelesai = computed(() => {
    if (totalLembar.value <= 0) return 0;
    return Math.min(100, Math.round((totalTerisi.value / totalLembar.value) * 100));
});

const isSemuaTuntas = computed(() => totalTerisi.value >= totalLembar.value && totalLembar.value > 0);

const totalTerkalibrasi = computed(() =>
    (langkah.value || []).reduce((acc, curr) => acc + (Number(curr.jumlah_terkalibrasi) || 0), 0),
);

const totalKategoriTuntas = computed(() =>
    (langkah.value || []).filter((l) => l.status === 'TUNTAS').length,
);

const sudahTerkirim = computed(() => Boolean(wizard.value?.pengiriman?.sudah_terkirim_semua));

const deskripsiCockpit = computed(() => {
    if (sudahTerkirim.value) {
        return 'Semua penilaian Anda sudah terkirim.';
    }
    if (isSemuaTuntas.value) {
        return 'Seluruh lembar penilaian telah selesai diisi. Silakan lakukan kalibrasi dan kirim seluruh penilaian.';
    }
    return `${totalTerisi.value} dari ${totalLembar.value} lembar terisi · Tersisa ${totalSisa.value} lembar yang perlu diselesaikan.`;
});

const labelLanjut = computed(() =>
    langkahBerikut.value ? `Lanjut ke ${langkahBerikut.value.nama_kategori}` : 'Lanjut ke Ringkasan & Kirim',
);

/** Menjalankan aksi berpindah; bila ditolak karena ada perubahan belum tersimpan, minta konfirmasi. */
function denganKonfirmasi(aksi) {
    if (aksi({ paksa: false }) === false) {
        tertunda = aksi;
        konfirmasiTerbuka.value = true;
    }
}

function jalankanTertunda() {
    konfirmasiTerbuka.value = false;
    tertunda?.({ paksa: true });
    tertunda = null;
}

/* Memuat ulang menimpa pratinjau dari server, jadi ia pun berpindah: draf yang sedang
   diketik ikut hilang dan harus dikonfirmasi seperti berpindah langkah. */
function perbarui({ paksa = false } = {}) {
    if (kotor.value && !paksa) return false;
    kotor.value = false;
    muat({ pertahankanPosisi: true });
    return true;
}

function pilihLangkah(kode) {
    if (kode === LANGKAH_RINGKASAN) denganKonfirmasi((o) => bukaRingkasan(o));
    else denganKonfirmasi((o) => buka(kode, null, o));
}

async function simpan(payload) {
    if (await simpanKalibrasi(payload)) hcisMessage.success('Kalibrasi berhasil tersimpan.');
}

async function simpanBanyak(daftar, saatTersimpan) {
    const { berhasil, gagal } = await simpanKalibrasiBanyak(daftar, { saatTersimpan });
    if (!gagal) hcisMessage.success(`Kalibrasi ${berhasil} peserta berhasil tersimpan.`);
}

async function kirimSemua() {
    if (await kirim()) {
        hcisMessage.success('Seluruh lembar penilaian Anda berhasil dikirim secara resmi.');
        emit('terkirim');
    }
}
</script>

<style scoped>
.pa-wizard {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1.25rem);
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-wizard-loading-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-wizard-loading-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
}

.pa-wizard-terkirim {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.14) 0%, rgba(16, 185, 129, 0.06) 100%);
    border: 1.5px solid rgba(16, 185, 129, 0.35);
    border-radius: var(--hcis-radius-xl, 16px);
    color: #065f46;
}

.pa-wizard-terkirim__ikon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    flex-shrink: 0;
    background: #10b981;
    color: #fff;
    border-radius: 50%;
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
}

.pa-wizard-terkirim__teks {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    font-size: 0.9rem;
    line-height: 1.5;
}

.pa-wizard-terkirim__teks strong {
    font-size: 1.05rem;
    color: #064e3b;
}

@media (max-width: 768px) {
    .pa-wizard-terkirim {
        align-items: flex-start;
        padding: 0.9rem 1rem;
    }

    .pa-wizard-terkirim__ikon {
        width: 2.5rem;
        height: 2.5rem;
    }
}

.pa-wizard-unified-card {
    border-radius: var(--hcis-radius-xl, 16px);
}

.pa-wizard-unified-card :deep(.hcis-card__body) {
    padding: 1.15rem 1.35rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-unified-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    flex-wrap: wrap;
}

.pa-unified-header__main {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
    flex: 1;
}

.pa-unified-icon-box {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #eef2ff;
    color: var(--hcis-color-brand-600, #4f46e5);
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.12);
}

.pa-unified-icon-box.is-done {
    background: #ecfdf5;
    color: #059669;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.15);
}

.pa-unified-header__titles {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pa-unified-kicker-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-unified-kicker {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: var(--hcis-color-brand-600, #4f46e5);
    text-transform: uppercase;
}

.pa-unified-calib-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    background: #faf5ff;
    border: 1px solid #f3e8ff;
    color: #7e22ce;
    font-size: 0.68rem;
    font-weight: 700;
}

.pa-unified-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    line-height: 1.25;
}

.pa-unified-desc {
    margin: 0;
    font-size: 0.8rem;
    color: var(--hcis-color-text-muted, #64748b);
    line-height: 1.4;
}

.pa-unified-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-unified-divider {
    height: 1px;
    width: 100%;
    background: linear-gradient(90deg, rgba(226, 232, 240, 0.2) 0%, rgba(226, 232, 240, 0.9) 30%, rgba(226, 232, 240, 0.9) 70%, rgba(226, 232, 240, 0.2) 100%);
}

.pa-unified-stepper-wrap {
    width: 100%;
}

.pa-unified-stepper-wrap :deep(.pa-compact-stepper) {
    padding: 0;
    background: transparent;
    border: none;
    box-shadow: none;
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
}

.pa-wizard-pilih-siklus {
    min-width: 11rem;
    max-width: 100%;
}

.pa-category-view-switcher-bar {
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    overflow: hidden;
}

.pa-category-switcher-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 0.85rem 1.15rem;
}

.pa-unified-kalibrasi-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.65rem 1.15rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    flex-wrap: wrap;
}

.pa-unified-kalibrasi-context {
    display: flex;
    align-items: center;
    min-width: 0;
    flex: 1;
}

.pa-unified-kalibrasi-desc {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    color: var(--hcis-color-text-muted, #64748b);
    line-height: 1.45;
}

.pa-unified-desc-icon {
    width: 0.95rem;
    height: 0.95rem;
    color: var(--hcis-color-brand-600, #4f46e5);
    flex-shrink: 0;
}

.pa-unified-kalibrasi-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
    flex-wrap: wrap;
}

.pa-unified-stat-pills {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-view-switcher-meta {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-view-kicker-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-view-kicker {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: var(--hcis-color-brand-600, #4f46e5);
    text-transform: uppercase;
}

.pa-view-category-name {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    letter-spacing: -0.01em;
    line-height: 1.25;
}

.pa-view-capsule-switch {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-lg, 12px);
    gap: 0.25rem;
}

.pa-capsule-label-short {
    display: none;
}

.pa-capsule-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.95rem;
    border-radius: var(--hcis-radius-md, 9px);
    border: none;
    background: transparent;
    color: var(--hcis-color-text-muted, #64748b);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 160ms cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
}

.pa-capsule-btn:hover:not(.is-disabled) {
    color: var(--hcis-color-text-main, #0f172a);
    background: rgba(255, 255, 255, 0.7);
}

.pa-capsule-btn.is-active {
    background: #ffffff;
    color: var(--hcis-color-brand-600, #4f46e5);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}

.pa-capsule-btn.is-disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.pa-capsule-badge {
    padding: 0.08rem 0.45rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 750;
    background: rgba(0, 0, 0, 0.06);
    color: inherit;
}

.pa-capsule-badge.is-brand {
    background: rgba(79, 70, 229, 0.12);
    color: var(--hcis-color-brand-600, #4f46e5);
}

.pa-capsule-subtag {
    font-size: 0.68rem;
    padding: 0.08rem 0.4rem;
    border-radius: 4px;
    background: rgba(0, 0, 0, 0.05);
    color: var(--hcis-color-text-muted, #94a3b8);
}

/* Step Content Transitions */
.pa-wizard-content-wrap {
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-view-fade-enter-active,
.pa-view-fade-leave-active {
    transition: opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1), transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.pa-view-fade-enter-from {
    opacity: 0;
    transform: translateY(6px);
}

.pa-view-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

.pa-dialog-warning-body {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
}

.pa-dialog-warning-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--hcis-radius-lg, 10px);
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    flex-shrink: 0;
}

.pa-warning-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-wizard__konfirmasi {
    margin: 0.25rem 0 0;
    color: var(--hcis-color-text-muted, #64748b);
    font-size: 0.825rem;
    line-height: 1.45;
}

.pa-wizard__aksi {
    display: flex;
    justify-content: flex-end;
    gap: var(--hcis-space-2, 0.5rem);
    width: 100%;
}

@media (max-width: 768px) {
    .pa-wizard {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .pa-wizard-unified-card {
        border-radius: var(--hcis-radius-lg, 12px);
    }

    .pa-wizard-unified-card :deep(.hcis-card__body) {
        padding: 0.65rem 0.75rem;
        gap: 0.5rem;
    }

    .pa-unified-header {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 0.45rem;
        flex-wrap: nowrap;
    }

    .pa-unified-header__main {
        gap: 0.55rem;
        min-width: 0;
        flex: 1;
    }

    .pa-unified-icon-box {
        width: 2rem;
        height: 2rem;
        border-radius: 8px;
    }

    .pa-unified-header__titles {
        gap: 0.1rem;
        min-width: 0;
    }

    .pa-unified-kicker-row {
        gap: 0.3rem;
    }

    .pa-unified-kicker {
        font-size: 0.6rem;
    }

    .pa-unified-title {
        font-size: 0.9rem;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pa-unified-desc {
        display: none !important;
    }

    .pa-unified-actions {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .pa-wizard-pilih-siklus {
        min-width: 7.5rem;
        max-width: 8.5rem;
    }

    .pa-btn-refresh :deep(.hcis-btn__text) {
        display: none !important;
    }

    .pa-btn-refresh {
        padding: 0.35rem 0.45rem !important;
        min-width: 2rem;
        justify-content: center;
    }

    .pa-unified-divider {
        margin: 0;
        opacity: 0.5;
    }

    .pa-unified-stepper-wrap {
        padding-top: 0.1rem;
    }

    .pa-category-view-switcher-bar {
        border-radius: var(--hcis-radius-lg, 12px);
    }

    .pa-category-switcher-top {
        flex-direction: column;
        align-items: stretch;
        padding: 0.55rem 0.75rem;
        gap: 0.45rem;
    }

    .pa-view-switcher-meta {
        gap: 0.1rem;
        min-width: 0;
    }

    .pa-view-kicker-row {
        display: none !important;
    }

    .pa-view-category-name {
        font-size: 0.875rem;
        font-weight: 750;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pa-view-capsule-switch {
        display: grid;
        grid-template-columns: 1fr 1fr;
        width: 100%;
        padding: 0.15rem;
        gap: 0.2rem;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }

    .pa-capsule-label-full {
        display: none !important;
    }

    .pa-capsule-label-short {
        display: inline !important;
    }

    .pa-capsule-btn {
        justify-content: center;
        padding: 0.3rem 0.45rem;
        font-size: 0.75rem;
        gap: 0.3rem;
        border-radius: 6px;
    }

    .pa-capsule-badge,
    .pa-capsule-subtag {
        font-size: 0.65rem;
        padding: 0.05rem 0.35rem;
    }

    .pa-category-view-switcher-bar.is-kalibrasi-mode .pa-unified-kalibrasi-bar {
        display: none !important;
    }

    .pa-unified-kalibrasi-desc {
        display: none !important;
    }

    .pa-wizard__aksi {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
}
</style>
