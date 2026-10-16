<template>
    <div class="container-fluid px-0 sop-scope pa-export-studio">
        <HcisHeader
            variant="glass"
            sentiment="brand"
            size="md"
            icon="bi-sliders"
            kicker="Performance Appraisal Report Builder"
            title="Export Studio"
            :subtitle="subtitleDataset"
            :badge="`Sumber: ${labelDataset(datasetAktif)}`"
            class="mb-4"
        >
            <template #actions>
                <div class="pes-header-actions">
                    <!--
                        Pemindah sumber data. Kedua dataset BUKAN dua tampilan
                        atas data yang sama: hasil berbutir hasil penilaian,
                        penugasan berbutir penugasan penilai. Judul lengkap
                        tiap dataset (label() dari PaHasilDataset/PaPenugasanDataset)
                        muncul di title/aria-label dan lencana hero.
                    -->
                    <div class="pes-dataset-switcher" role="group" aria-label="Sumber Data Laporan">
                        <button
                            type="button"
                            class="pes-dataset-btn"
                            :class="{ 'is-active': datasetAktif === 'hasil' }"
                            :title="labelDataset('hasil')"
                            @click="pilihDataset('hasil')"
                        >
                            <i class="bi bi-clipboard-data-fill pes-dataset-icon"></i>
                            <span>Hasil</span>
                        </button>
                        <button
                            type="button"
                            class="pes-dataset-btn"
                            :class="{ 'is-active': datasetAktif === 'penugasan' }"
                            :title="labelDataset('penugasan')"
                            @click="pilihDataset('penugasan')"
                        >
                            <i class="bi bi-person-check-fill pes-dataset-icon"></i>
                            <span>Penugasan</span>
                        </button>
                        <button
                            type="button"
                            class="pes-dataset-btn"
                            :class="{ 'is-active': datasetAktif === 'kompetensi' }"
                            :title="labelDataset('kompetensi')"
                            @click="pilihDataset('kompetensi')"
                        >
                            <i class="bi bi-star-fill pes-dataset-icon"></i>
                            <span>Kompetensi</span>
                        </button>
                        <button
                            type="button"
                            class="pes-dataset-btn"
                            :class="{ 'is-active': datasetAktif === 'peserta' }"
                            :title="labelDataset('peserta')"
                            @click="pilihDataset('peserta')"
                        >
                            <i class="bi bi-people-fill pes-dataset-icon"></i>
                            <span>Peserta</span>
                        </button>
                    </div>

                    <div class="pes-tanggal" title="Tanggal sistem saat ini">
                        <i class="bi bi-calendar3-event-fill pes-tanggal-icon"></i>
                        <span class="pes-tanggal-label">Hari ini</span>
                        <span class="pes-tanggal-dot">·</span>
                        <span class="pes-tanggal-text">{{ tanggal }}</span>
                    </div>
                </div>
            </template>

            <!-- ══ INTEGRATED HEADER TABS ══ -->
            <template #tabs>
                <div class="pes-header-tabs-wrap">
                    <HcisTabs
                        v-model="tabUtama"
                        variant="header"
                        size="md"
                        :tabs="daftarTabUtama"
                    />
                </div>
            </template>
        </HcisHeader>

        <div class="mb-5 sop-stack">
            <!--
                :key MEMBUAT PERAKIT LAHIR ULANG saat dataset diganti.
                Tanpanya komponen yang sama dipakai kembali dengan katalog yang
                sudah berganti, sementara pilihan dimensi/ukuran lama masih
                tersimpan di dalamnya — dan kunci yang tak ada di katalog baru
                lolos ke definisi sebagai kolom hantu.
            -->
            <ReportStudio
                ref="studioRef"
                :key="datasetAktif"
                v-model:tab-utama="tabUtama"
                :base-url="`${baseUrl}/${datasetAktif}`"
                :dataset-awal="datasetAktif"
                :tanpa-top-bar="true"
                @ringkas="(v) => (ringkas = v)"
                @update:template-aktif="(v) => (templateAktif = v)"
                @daftar-tab="(v) => (daftarTabUtama = v)"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import HcisHeader from '@/components/ui/HcisHeader.vue';
import HcisTabs from '@/components/ui/HcisTabs.vue';
import { hcisConfirm } from '@/components/ui/hcisDialog';
import ReportStudio from '@/components/SYSTEM/ReportStudio/ReportStudio.vue';

/*
 * sopTheme.css BUKAN hiasan: `.sop-scope`, `.sop-stack`, `.sop-eyebrow`, dan
 * `.sop-h` yang dipakai ReportStudio (galeri template & panel konfigurasi)
 * semuanya lahir di sana. Tanpa baris ini ReportStudio memakai kelas yang
 * tidak pernah didefinisikan di halaman ini.
 */
import '@/Pages/LMS/SOP/Shared/support/sopTheme.css';

defineOptions({ name: 'PaExportStudioPage' });

/**
 * Halaman ini SENGAJA tipis.
 *
 * Seluruh perakit hidup di components/SYSTEM/ReportStudio; halaman ini hanya
 * membungkusnya. `baseUrl` adalah satu-satunya prop yang benar-benar dikirim
 * PaExportStudioController::halaman(), dengan nilai persis sama dengan default
 * di sini — lihat routes/KPI/PerformanceAppraisal/Export/PerformanceAppraisalExport.php.
 * `datasetAwal` tidak dikirim controller sehingga defaultnya ditentukan di
 * sini ('hasil').
 */
const props = defineProps({
    baseUrl: { type: String, default: '/api/v1/performance-appraisal/export-studio' },
    datasetAwal: { type: String, default: 'hasil' },
});

const DATASET = ['hasil', 'penugasan', 'kompetensi', 'peserta'];

/* Label PERSIS dari PaHasilDataset::label(), PaPenugasanDataset::label(),
   PaKompetensiDataset::label(), dan PaPesertaDataset::label() — jangan
   diparafrase, teks ini yang dijanjikan katalog ke pengguna. */
const LABEL_DATASET = {
    hasil: 'Hasil Performance Appraisal',
    penugasan: 'Penugasan Penilai Performance Appraisal',
    kompetensi: 'Kompetensi Performance Appraisal',
    peserta: 'Peserta Performance Appraisal',
};

function labelDataset(kunci) {
    return LABEL_DATASET[kunci] ?? kunci;
}

/*
 * Dulu ternary dua-cabang (`datasetAktif === 'penugasan' ? ... : ...`): benar
 * untuk dua dataset, tapi diam-diam salah begitu dataset ketiga terdaftar
 * (mis. tahap 2: kompetensi) -- subtitle-nya akan ikut memakai teks dataset
 * hasil tanpa satu pun galat. Peta, mumpung baru dua entri.
 */
const SUBTITLE_DATASET = {
    hasil: 'Rakit laporan hasil performance appraisal, lihat hasilnya sebelum diunduh.',
    penugasan: 'Rakit laporan penugasan penilai performance appraisal, lihat hasilnya sebelum diunduh.',
    kompetensi: 'Rakit laporan kompetensi performance appraisal, lihat hasilnya sebelum diunduh.',
    peserta: 'Rakit laporan peserta performance appraisal, lihat hasilnya sebelum diunduh.',
};

const datasetAktif = ref(props.datasetAwal);

const subtitleDataset = computed(
    () => SUBTITLE_DATASET[datasetAktif.value] ?? 'Rakit laporan performance appraisal, lihat hasilnya sebelum diunduh.'
);

const tanggal = computed(() =>
    new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
);

const studioRef = ref(null);
const tabUtama = ref('galeri');
const templateAktif = ref(null);
const daftarTabUtama = ref([
    {
        key: 'galeri',
        label: 'Galeri Template',
        icon: 'bi-collection-play-fill',
    },
    {
        key: 'studio',
        label: 'Studio Perakit & Pratinjau',
        icon: 'bi-sliders',
    },
]);

/* Diisi ReportStudio lewat emit `ringkas`. */
const ringkas = ref({ mode: 'ringkasan', kolom: 0, baris: null });

/**
 * Ada rakitan yang belum disimpan?
 *
 * Diperiksa lewat isi pilihan, bukan lewat sebuah flag "kotor". Flag semacam
 * itu harus dipasang di setiap tempat yang mengubah pilihan, dan satu tempat
 * yang terlewat membuat pekerjaan orang hilang tanpa peringatan.
 */
function memilikiDraft() {
    const studio = studioRef.value;
    const pilihan = studio?.pilihan;
    if (!pilihan) return false;

    return Boolean(studio?.templateAktif)
        || ['dimensi', 'ukuran', 'kolom', 'saringan'].some(
            (key) => Array.isArray(pilihan[key]) && pilihan[key].length > 0
        );
}

/**
 * Pindah sumber data.
 *
 * Bertanya lebih dulu bila ada rakitan yang belum disimpan: dimensi dan ukuran
 * kedua dataset TIDAK saling berpasangan — butirannya berbeda — sehingga
 * pilihan lama tak mungkin dibawa serta, hanya bisa dikosongkan.
 */
async function pilihDataset(datasetBaru) {
    if (!DATASET.includes(datasetBaru) || datasetBaru === datasetAktif.value) return;

    if (memilikiDraft()) {
        const lanjut = await hcisConfirm({
            title: 'Ganti sumber data laporan?',
            description: 'Rakitan yang belum disimpan pada sumber saat ini akan dikosongkan.',
            kicker: 'Export Studio',
            icon: 'bi-arrow-left-right',
            sentiment: 'warning',
            confirmText: 'Ganti Sumber Data',
            cancelText: 'Tetap di Sini',
        });
        if (!lanjut) return;
    }

    datasetAktif.value = datasetBaru;
    templateAktif.value = null;
    tabUtama.value = 'galeri';
    ringkas.value = { mode: 'ringkasan', kolom: 0, baris: null };
}
</script>

<style scoped>
.pes-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pes-dataset-switcher {
    display: inline-flex;
    align-items: center;
    padding: 3px;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: rgba(241, 245, 249, 0.95);
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
}

.pes-dataset-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.75rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: none;
    background: transparent;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-size: 0.78125rem;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    transition: all 160ms cubic-bezier(0.16, 1, 0.3, 1);
    user-select: none;
}

.pes-dataset-btn:hover {
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
}

.pes-dataset-btn.is-active {
    background: var(--hcis-white);
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.1), 0 1px 2px rgba(99, 102, 241, 0.06);
}

.pes-dataset-icon {
    font-size: 0.8125rem;
}

.pes-tanggal {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.32rem 0.85rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(99, 102, 241, 0.2);
    box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(99, 102, 241, 0.06);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    transition: all 180ms cubic-bezier(0.16, 1, 0.3, 1);
    user-select: none;
}

.pes-tanggal:hover {
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    box-shadow: 0 4px 14px -2px rgba(99, 102, 241, 0.16);
    transform: translateY(-1px);
}

.pes-tanggal-icon {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    font-size: 0.82rem;
    display: flex;
    align-items: center;
}

.pes-tanggal-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    background: rgba(99, 102, 241, 0.1);
    padding: 0.08rem 0.4rem;
    border-radius: 9999px;
}

.pes-tanggal-dot {
    color: var(--hcis-border-medium, var(--hcis-slate-300));
    font-size: 0.75rem;
    font-weight: 700;
}

.pes-tanggal-text {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-text-strong, var(--hcis-slate-800));
    font-variant-numeric: tabular-nums;
}

.pes-header-tabs-wrap {
    display: flex;
    align-items: center;
    width: 100%;
}

@media (max-width: 767.98px) {
    .pes-header-actions {
        width: 100%;
        justify-content: flex-start;
    }
    .pes-dataset-switcher {
        width: 100%;
    }
    .pes-dataset-btn {
        flex: 1 1 0;
        justify-content: center;
    }
}
</style>
