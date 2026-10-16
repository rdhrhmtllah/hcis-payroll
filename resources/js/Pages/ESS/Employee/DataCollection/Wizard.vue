<template>
    <div class="pd-halaman">
        <HcisAlert
            v-if="status.terkunci"
            variant="warning"
            appearance="soft"
            icon="bi bi-shield-lock-fill"
            title="Lengkapi dulu untuk membuka kembali HCIS"
            class="pd-halaman__gerbang"
        >
            Jatah penundaanmu sudah habis, jadi HCIS mengantarmu ke sini. Dua menit — dan urusan ini selesai selamanya.
        </HcisAlert>

        <HcisCard
            :title="diPeninjauan ? 'Tinjau & Konfirmasi Data' : langkahAktif.judul"
            :description="keteranganLangkah"
            :icon="diPeninjauan ? 'bi bi-clipboard-check-fill' : 'bi bi-person-vcard'"
            variant="glass"
            pattern="grid"
            size="lg"
            divider
            class="pd-halaman__kartu"
        >
            <template #actions>
                <div class="pd-halaman__header-badges">
                    <HcisStatusBadge
                        status="success"
                        label="Draf tersimpan"
                        icon="bi bi-cloud-check-fill"
                        size="sm"
                    />
                    <HcisBadge
                        :variant="diPeninjauan ? 'success' : 'brand'"
                        :label="diPeninjauan ? 'Peninjauan' : `Langkah ${indeks + 1} dari ${skema.langkah.length}`"
                        size="xs"
                    />
                </div>
            </template>

            <!-- Desktop & Tablet Stepper -->
            <div class="pd-halaman__langkah-desktop">
                <HcisSteps
                    :steps="langkahUntukIndikator"
                    :current="diPeninjauan ? skema.langkah.length : indeks"
                    size="sm"
                    clickable
                    class="pd-halaman__langkah"
                    @change="onStepClick"
                />
            </div>

            <!-- Mobile-Optimized Segmented Stepper -->
            <div class="pd-mobile-stepper" aria-label="Progress Pengisian Formulir">
                <div class="pd-mobile-stepper__bar" role="progressbar" :aria-valuenow="diPeninjauan ? skema.langkah.length + 1 : indeks + 1" :aria-valuemin="1" :aria-valuemax="skema.langkah.length + 1">
                    <button
                        v-for="(st, sIdx) in langkahUntukIndikator"
                        :key="sIdx"
                        type="button"
                        class="pd-mobile-stepper__segment"
                        :class="{
                            'is-active': (diPeninjauan ? skema.langkah.length : indeks) === sIdx,
                            'is-done': (diPeninjauan ? skema.langkah.length : indeks) > sIdx,
                            'is-clickable': sIdx <= (diPeninjauan ? skema.langkah.length : indeks)
                        }"
                        :aria-label="st.label"
                        :title="st.label"
                        @click="onStepClick({ index: sIdx })"
                    >
                        <span class="pd-mobile-stepper__fill"></span>
                    </button>
                </div>
                <div class="pd-mobile-stepper__meta">
                    <div class="pd-mobile-stepper__info">
                        <span class="pd-mobile-stepper__counter">
                            {{ diPeninjauan ? 'Tinjauan' : `Langkah ${indeks + 1} / ${skema.langkah.length}` }}
                        </span>
                        <span class="pd-mobile-stepper__dot">•</span>
                        <span class="pd-mobile-stepper__step-name">
                            {{ diPeninjauan ? 'Konfirmasi Akhir' : langkahAktif.judul }}
                        </span>
                    </div>
                    <span v-if="!diPeninjauan && indeks < skema.langkah.length - 1" class="pd-mobile-stepper__hint">
                        Lanjut: {{ skema.langkah[indeks + 1]?.judul }}
                    </span>
                </div>
            </div>

            <Transition :name="arahTransisi" mode="out-in">
                <div v-if="!diPeninjauan" :key="indeks" class="pd-halaman__isi">
                    <!-- Context Banner Prefill Master Data -->
                    <HcisAlert
                        v-if="indeks === 0 && adaDataMaster"
                        variant="info"
                        appearance="soft"
                        icon="bi bi-magic"
                        title="Sebagian data Anda telah diisi otomatis"
                        class="mb-3"
                    >
                        Sebagian data identitas telah kami isikan otomatis dari data kepegawaian Anda di sistem. Anda dapat langsung memeriksa dan mengoreksi data yang kurang sesuai.
                    </HcisAlert>

                    <BidangIsian
                        v-for="kunci in fieldLangkahAktif"
                        :key="kunci"
                        :kunci="kunci"
                        :definisi="skema.field[kunci]"
                        :model-value="nilai[kunci] ?? ''"
                        :nilai-master="dataMaster[kunci]"
                        :galat="galat[kunci] ?? null"
                        :pilihan-dinamis="pilihanDivisi"
                        :karyawan-terpilih="namaKaryawanDikenal[nilai[kunci]] ?? null"
                        @nama="ingatNama"
                        @update:model-value="(v) => setNilai(kunci, v)"
                    />
                </div>

                <PanelPeninjauan
                    v-else
                    key="tinjau"
                    :skema="skema"
                    :nilai="nilai"
                    :data-master="dataMaster"
                    :nama-karyawan="namaKaryawanDikenal"
                    v-model:konfirmasi="konfirmasi"
                    @lompat="lompatKe"
                />
            </Transition>

            <HcisAlert
                v-if="galatKirim"
                variant="danger"
                appearance="soft"
                icon="bi bi-exclamation-triangle-fill"
                :title="galatKirim"
                class="pd-halaman__galat-kirim"
            />

            <template #footer>
                <div class="pd-halaman__aksi">
                    <HcisButton
                        v-if="indeks > 0 || diPeninjauan"
                        variant="secondary"
                        label="Kembali"
                        leading-icon="bi bi-arrow-left"
                        :disabled="sedangMengirim"
                        data-uji="kembali"
                        class="pd-btn-kembali"
                        @click="mundur"
                    />

                    <div class="pd-halaman__aksi-kanan">
                        <HcisButton
                            v-if="!diPeninjauan"
                            variant="primary"
                            :label="indeks === skema.langkah.length - 1 ? 'Tinjau' : 'Lanjut'"
                            trailing-icon="bi bi-arrow-right"
                            data-uji="lanjut"
                            class="pd-btn-lanjut"
                            @click="maju"
                        />

                        <HcisButton
                            v-else
                            variant="primary"
                            :label="sedangMengirim ? 'Mengirim…' : 'Kirim Sekarang'"
                            :loading="sedangMengirim"
                            :disabled="sedangMengirim || !konfirmasi"
                            trailing-icon="bi bi-send-fill"
                            glow
                            data-uji="kirim"
                            class="pd-btn-kirim"
                            @click="kirim"
                        />
                    </div>
                </div>
            </template>
        </HcisCard>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import BidangIsian from './components/BidangIsian.vue';
import PanelPeninjauan from './components/PanelPeninjauan.vue';
import { HcisAlert, HcisBadge, HcisButton, HcisCard, HcisStatusBadge, HcisSteps } from '@/components/ui';
import { bidangTampil, galatFormat, kunciTampil } from '@/composables/ESS/Employee/DataCollection/formatBidang.js';

const props = defineProps({
    skema: { type: Object, required: true },
    status: { type: Object, required: true },
    draft: { type: Object, required: true },
    dataMaster: { type: Object, default: () => ({}) },
    pilihanDivisi: { type: Array, default: () => [] },
    /** Kode_Karyawan → { nama, keterangan } untuk jawaban 'cari_karyawan' yang sudah terisi. */
    namaKaryawan: { type: Object, default: () => ({}) },
});

// Bertambah saat orang memilih atasan baru, supaya peninjauan menampilkan nama.
const namaKaryawanDikenal = ref({ ...props.namaKaryawan });
const ingatNama = (kode, karyawan) => {
    namaKaryawanDikenal.value = { ...namaKaryawanDikenal.value, [kode]: karyawan };
};

const filterNilaiValid = (obj) => {
    return Object.fromEntries(
        Object.entries(obj || {}).filter(([_, v]) => {
            if (v === undefined || v === null) return false;
            const str = String(v).trim();
            return str !== '' && !/^[\s\-–—]+$/.test(str);
        }),
    );
};

const adaDataMaster = computed(() => Object.keys(filterNilaiValid(props.dataMaster)).length > 0);

/**
 * Jawaban pilihan yang sudah tidak ada di daftar dibuang saat halaman dibuka.
 *
 * Contohnya 'D4/S1' dari draf sebelum skema versi 4 memisahnya. Dibiarkan,
 * kotaknya tampak terisi tetapi server menolaknya saat Kirim — orangnya baru
 * tahu di ujung. Dikosongkan, ia diminta memilih di langkahnya sendiri.
 */
const buangPilihanUsang = (data) => {
    const bersih = { ...data };
    Object.entries(props.skema.field ?? {}).forEach(([kunci, def]) => {
        const daftar = def?.pilihan;
        const v = bersih[kunci];
        if (Array.isArray(daftar) && v !== undefined && v !== null && v !== '' && !daftar.includes(v)) {
            delete bersih[kunci];
        }
    });
    return bersih;
};

const nilai = ref(
    buangPilihanUsang({
        ...filterNilaiValid(props.dataMaster),
        ...props.draft.data,
    }),
);
const indeks = ref(Math.min(Math.max(props.draft.langkah ?? 0, 0), props.skema.langkah.length - 1));
const diPeninjauan = ref(false);
const galat = ref({});
const galatKirim = ref(null);
const sedangMengirim = ref(false);
/**
 * Pernyataan di panel peninjauan. Server ikut menuntutnya, jadi tombol yang
 * terkunci di sini hanya kenyamanan — bukan satu-satunya penjaga.
 */
const konfirmasi = ref(false);
// Keluar dari peninjauan berarti datanya bisa berubah: pernyataan lama tidak
// lagi menjamin apa yang nanti dikirim, jadi harus dicentang ulang.
watch(diPeninjauan, (aktif) => {
    if (!aktif) konfirmasi.value = false;
});
const arahTransisi = ref('pd-geser-maju');

const langkahAktif = computed(() => props.skema.langkah[indeks.value]);

/**
 * Pertanyaan yang benar-benar tampil di langkah ini.
 *
 * Syaratnya dibaca dari skema, bukan disimpulkan di sini — server membaca
 * tulisan yang sama untuk memutuskan menuntut atau tidak.
 */
const fieldLangkahAktif = computed(() =>
    kunciTampil(langkahAktif.value.field, props.skema.field, nilai.value),
);

/**
 * Membuang jawaban milik pertanyaan yang sudah tidak tampil.
 *
 * Angka yang sudah diketik lalu syaratnya berubah adalah nilai SISA: di layar
 * tidak ada lagi kotak yang menunjukkannya, jadi membiarkannya ikut terkirim
 * berarti mengirim data yang tidak pernah dilihat orangnya. Server membuangnya
 * juga — ini supaya draf dan layar tidak lebih dulu berbohong.
 */
const buangYangTersembunyi = () => {
    Object.keys(props.skema.field ?? {}).forEach((kunci) => {
        if (bidangTampil(props.skema.field[kunci], nilai.value)) return;

        delete nilai.value[kunci];
        delete galat.value[kunci];
    });
};

// Draf yang dipulihkan bisa membawa jawaban dari syarat yang sudah diganti.
buangYangTersembunyi();

/**
 * Penunjuk langkah memakai HcisSteps. Layar peninjauan dihitung sebagai langkah
 * terakhir supaya jalurnya terlihat utuh dari awal sampai konfirmasi.
 */
const langkahUntukIndikator = computed(() => [
    ...props.skema.langkah.map((l) => ({ label: l.judul })),
    { label: 'Tinjau' },
]);

const keteranganLangkah = computed(() => {
    if (diPeninjauan.value) return 'Periksa kembali data Anda sebelum dikirim';

    const l = langkahAktif.value;

    return l.deskripsi
        ? `${l.deskripsi} (Langkah ${indeks.value + 1} dari ${props.skema.langkah.length})`
        : `Langkah ${indeks.value + 1} dari ${props.skema.langkah.length}`;
});

const setNilai = (kunci, v) => {
    nilai.value[kunci] = v;
    if (galat.value[kunci]) delete galat.value[kunci];

    // Menjawab satu pertanyaan bisa MENUTUP pertanyaan lain.
    buangYangTersembunyi();
};

// Pemeriksaan di browser mencegah maju bila field belum terisi atau format salah.
const periksaSatu = (kunci) => {
    const def = props.skema.field?.[kunci] ?? {};
    const v = nilai.value[kunci];

    if (v === undefined || v === null || String(v).trim() === '') return 'Wajib diisi';

    return galatFormat(def, v);
};

const kumpulkanGalat = (daftarKunci) => {
    const kurang = {};

    daftarKunci.forEach((kunci) => {
        const pesan = periksaSatu(kunci);
        if (pesan) kurang[kunci] = pesan;
    });

    return kurang;
};

const langkahIniLengkap = () => {
    galat.value = kumpulkanGalat(fieldLangkahAktif.value);
    const keys = Object.keys(galat.value);

    if (keys.length > 0) {
        if (typeof document !== 'undefined') {
            setTimeout(() => {
                const firstEl = document.getElementById(`pd-${keys[0]}`);
                if (firstEl) {
                    firstEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstEl.focus?.();
                }
            }, 50);
        }
        return false;
    }

    return true;
};

const semuaLangkahValid = () => {
    const kurang = kumpulkanGalat(
        kunciTampil(Object.keys(props.skema.field), props.skema.field, nilai.value),
    );

    if (Object.keys(kurang).length > 0) {
        galat.value = kurang;

        const kunciPertama = Object.keys(kurang)[0];
        const langkahnya = props.skema.langkah.findIndex((l) => l.field.includes(kunciPertama));

        if (langkahnya !== -1) {
            indeks.value = langkahnya;
            diPeninjauan.value = false;
        }

        if (typeof document !== 'undefined') {
            setTimeout(() => {
                const firstEl = document.getElementById(`pd-${kunciPertama}`);
                if (firstEl) {
                    firstEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstEl.focus?.();
                }
            }, 50);
        }

        return false;
    }

    return true;
};

// Endpoint draf mengembalikan JSON biasa
const simpanDraf = () => {
    axios
        .post('/pengumpulan-data/draft', { data: nilai.value, langkah: indeks.value })
        .catch(() => {});
};

const maju = () => {
    if (!langkahIniLengkap()) return;

    arahTransisi.value = 'pd-geser-maju';

    if (indeks.value < props.skema.langkah.length - 1) {
        indeks.value += 1;
    } else {
        diPeninjauan.value = true;
    }

    simpanDraf();
};

const mundur = () => {
    arahTransisi.value = 'pd-geser-mundur';

    if (diPeninjauan.value) {
        diPeninjauan.value = false;

        return;
    }

    if (indeks.value > 0) indeks.value -= 1;
};

const lompatKe = (i) => {
    arahTransisi.value = 'pd-geser-mundur';
    diPeninjauan.value = false;
    indeks.value = i;
};

const onStepClick = ({ index }) => {
    if (index === props.skema.langkah.length) {
        // Klik tahap tinjau
        if (indeks.value === props.skema.langkah.length - 1 && langkahIniLengkap()) {
            maju();
        }
        return;
    }

    if (index < indeks.value) {
        lompatKe(index);
    } else if (index === indeks.value + 1 && langkahIniLengkap()) {
        maju();
    }
};

const kirim = () => {
    if (sedangMengirim.value || !konfirmasi.value) return;

    buangYangTersembunyi();

    if (!semuaLangkahValid()) return;

    sedangMengirim.value = true;
    galatKirim.value = null;

    router.post(
        '/pengumpulan-data/kirim',
        { data: nilai.value, konfirmasi: konfirmasi.value },
        {
            onError: (galatServer) => {
                sedangMengirim.value = false;
                galatKirim.value = galatServer?.konfirmasi
                    ? 'Centang pernyataan kebenaran data sebelum mengirim.'
                    : 'Ada isian yang belum sesuai. Periksa kembali jawabanmu.';

                const terpetakan = {};
                Object.entries(galatServer ?? {}).forEach(([kunci, pesan]) => {
                    terpetakan[kunci] = Array.isArray(pesan) ? pesan[0] : pesan;
                });
                galat.value = terpetakan;

                const kunciBermasalah = Object.keys(terpetakan);
                if (kunciBermasalah.length) {
                    const langkahBermasalah = props.skema.langkah.findIndex((l) =>
                        l.field.some((f) => kunciBermasalah.includes(f)),
                    );
                    if (langkahBermasalah >= 0) {
                        diPeninjauan.value = false;
                        indeks.value = langkahBermasalah;
                    }
                }
            },
            onFinish: () => {
                sedangMengirim.value = false;
            },
        },
    );
};
</script>

<style scoped>
.pd-halaman {
    max-width: 48rem;
    margin: 0 auto;
    padding: var(--hcis-space-6) var(--hcis-space-4);
}

.pd-halaman__gerbang,
.pd-halaman__galat-kirim {
    margin-bottom: var(--hcis-space-5);
}

.pd-halaman__kartu {
    box-shadow: var(--hcis-shadow-lg), 0 0 0 1px rgb(var(--hcis-slate-200-rgb) / 0.85);
    border-radius: var(--hcis-radius-xl, 16px);
}

.pd-halaman__header-badges {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
    flex-wrap: wrap;
}

/* Stepper - Desktop */
.pd-halaman__langkah-desktop {
    display: block;
    width: 100%;
}

.pd-halaman__langkah {
    margin-bottom: 2.25rem;
    width: 100%;
}

.pd-halaman__langkah :deep(.hcis-steps-wrapper) {
    width: 100%;
}

.pd-halaman__langkah :deep(.hcis-steps__list--horizontal) {
    display: flex;
    width: 100%;
    align-items: flex-start;
    padding: 0 var(--hcis-space-2);
}

.pd-halaman__langkah :deep(.hcis-steps__item) {
    position: relative;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.pd-halaman__langkah :deep(.hcis-steps__head) {
    position: relative;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.5rem;
}

.pd-halaman__langkah :deep(.hcis-steps__marker) {
    position: relative;
    z-index: 2;
    margin: 0 auto;
}

.pd-halaman__langkah :deep(.hcis-steps__connector-track) {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 100%;
    height: 3px;
    margin: 0;
    transform: translateY(-50%);
    z-index: 1;
}

.pd-halaman__langkah :deep(.hcis-steps__content) {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    width: 100%;
    padding-right: 0;
}

.pd-halaman__langkah :deep(.hcis-steps__title) {
    text-align: center;
    font-size: 0.8125rem;
    font-weight: 750;
}

/* Stepper - Mobile */
.pd-mobile-stepper {
    display: none;
}

.pd-mobile-stepper__bar {
    display: flex;
    align-items: center;
    gap: 6px;
    width: 100%;
}

.pd-mobile-stepper__segment {
    flex: 1;
    height: 6px;
    border-radius: 999px;
    background: var(--hcis-slate-200, #e2e8f0);
    border: none;
    padding: 0;
    position: relative;
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    cursor: default;
    touch-action: manipulation;
}

.pd-mobile-stepper__segment.is-clickable {
    cursor: pointer;
}

.pd-mobile-stepper__segment.is-done {
    background: var(--hcis-brand-500, #6366f1);
}

.pd-mobile-stepper__segment.is-active {
    background: linear-gradient(90deg, var(--hcis-brand-500, #6366f1), var(--hcis-brand-600, #4f46e5));
    box-shadow: 0 0 6px rgba(99, 102, 241, 0.45);
}

.pd-mobile-stepper__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.78125rem;
    line-height: 1.35;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.pd-mobile-stepper__info {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pd-mobile-stepper__counter {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    font-weight: 750;
}

.pd-mobile-stepper__dot {
    color: var(--hcis-slate-400);
    font-size: 0.75rem;
}

.pd-mobile-stepper__step-name {
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    font-weight: 700;
}

.pd-mobile-stepper__hint {
    font-size: 0.71875rem;
    color: var(--hcis-text-muted, var(--hcis-slate-400));
}

.pd-halaman__isi {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-5);
    width: 100%;
    padding: var(--hcis-space-1) 0 var(--hcis-space-3);
}

.pd-halaman__aksi {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: var(--hcis-space-3);
}

.pd-halaman__aksi-kanan {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3);
    margin-left: auto;
}

.pd-halaman__kartu :deep(.hcis-card__body) {
    overflow: hidden;
}

.pd-geser-maju-enter-active,
.pd-geser-maju-leave-active,
.pd-geser-mundur-enter-active,
.pd-geser-mundur-leave-active {
    transition: opacity 200ms cubic-bezier(0.16, 1, 0.3, 1),
        transform 200ms cubic-bezier(0.16, 1, 0.3, 1);
    will-change: transform, opacity;
}

.pd-geser-maju-enter-from {
    opacity: 0;
    transform: translateX(14px);
}

.pd-geser-maju-leave-to {
    opacity: 0;
    transform: translateX(-14px);
}

.pd-geser-mundur-enter-from {
    opacity: 0;
    transform: translateX(-14px);
}

.pd-geser-mundur-leave-to {
    opacity: 0;
    transform: translateX(14px);
}

@media (max-width: 640px) {
    .pd-halaman {
        padding: var(--hcis-space-3) var(--hcis-space-2) var(--hcis-space-6);
    }

    .pd-halaman__langkah-desktop {
        display: none;
    }

    .pd-mobile-stepper {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
        width: 100%;
    }

    .pd-halaman__aksi {
        display: flex;
        flex-direction: row;
        gap: var(--hcis-space-2);
        width: 100%;
    }

    .pd-btn-kembali {
        flex: 1;
        min-height: 44px;
    }

    .pd-halaman__aksi-kanan {
        flex: 1.4;
        width: auto;
        margin-left: 0;
    }

    .pd-halaman__aksi:has(.pd-btn-kembali:only-child) .pd-halaman__aksi-kanan,
    .pd-halaman__aksi > .pd-halaman__aksi-kanan:only-child {
        flex: 1;
        width: 100%;
    }

    .pd-btn-lanjut,
    .pd-btn-kirim {
        width: 100%;
        min-height: 44px;
    }

    /* Header di kartu utama wizard (tidak merusak kartu peninjauan di dalamnya) */
    .pd-halaman__kartu > :deep(.hcis-card__header) {
        grid-template-columns: auto minmax(0, 1fr);
        grid-template-areas:
            "icon title"
            "desc desc"
            "actions actions";
        padding: var(--hcis-space-3);
    }

    .pd-halaman__kartu > :deep(.hcis-card__actions) {
        justify-self: start;
        margin-top: var(--hcis-space-1);
    }

    .pd-halaman__kartu > :deep(.hcis-card__body) {
        padding: var(--hcis-space-3);
    }

    .pd-halaman__kartu > :deep(.hcis-card__footer) {
        padding: var(--hcis-space-3);
    }
}
</style>
