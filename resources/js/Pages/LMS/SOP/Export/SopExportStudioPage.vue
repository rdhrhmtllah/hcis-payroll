<template>
    <div class="container-fluid px-0 sop-scope sop-export-studio">
        <HcisHeader
            variant="glass"
            sentiment="brand"
            size="md"
            icon="bi-sliders"
            kicker="DMS Report Builder"
            title="Export Studio"
            :subtitle="datasetAktif === 'dokumen'
                ? 'Rakit laporan register & tata kelola dokumen, lihat hasilnya sebelum diunduh.'
                : 'Rakit laporan dokumen sendiri, lihat hasilnya sebelum diunduh.'"
            :badge="`Sumber: ${datasetAktif === 'dokumen' ? 'Dokumen' : 'Kepatuhan'}`"
            class="mb-4"
        >
            <template #actions>
                <div class="ses-header-actions">
                    <!--
                        Pemindah sumber data. Keduanya BUKAN dua tampilan atas
                        data yang sama: Kepatuhan berbutir karyawan × paket ×
                        item, Dokumen berbutir satu dokumen. Judulnya karena itu
                        menyebut butiran, bukan sekadar nama tabel.
                    -->
                    <div class="ses-dataset-switcher" role="group" aria-label="Sumber Data Laporan">
                        <button
                            type="button"
                            class="ses-dataset-btn"
                            :class="{ 'is-active': datasetAktif === 'kepatuhan' }"
                            title="Satu baris = satu karyawan × paket × item"
                            @click="gantiDataset('kepatuhan')"
                        >
                            <i class="bi bi-people-fill ses-dataset-icon"></i>
                            <span>Kepatuhan</span>
                        </button>
                        <button
                            type="button"
                            class="ses-dataset-btn"
                            :class="{ 'is-active': datasetAktif === 'dokumen' }"
                            title="Satu baris = satu dokumen aktif"
                            @click="gantiDataset('dokumen')"
                        >
                            <i class="bi bi-journal-text ses-dataset-icon"></i>
                            <span>Dokumen</span>
                        </button>
                    </div>

                    <div class="ses-tanggal" title="Tanggal sistem saat ini">
                        <i class="bi bi-calendar3-event-fill ses-tanggal-icon"></i>
                        <span class="ses-tanggal-label">Hari ini</span>
                        <span class="ses-tanggal-dot">·</span>
                        <span class="ses-tanggal-text">{{ tanggal }}</span>
                    </div>
                </div>
            </template>

            <!-- ══ INTEGRATED HEADER TABS ══ -->
            <template #tabs>
                <div class="ses-header-tabs-wrap">
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
                :key MEMBUAT PERAKIT LAHIR ULANG saat sumber diganti.
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
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisHeader from '@/components/ui/HcisHeader.vue';
import HcisTabs from '@/components/ui/HcisTabs.vue';
import { hcisConfirm } from '@/components/ui/hcisDialog';
import ReportStudio from '@/components/SYSTEM/ReportStudio/ReportStudio.vue';

/*
 * sopTheme.css BUKAN hiasan: `.stitch-card`, `.sop-eyebrow`, `.sop-h`, dan
 * `.sop-stack` semuanya lahir di sana di bawah `.sop-scope`. Tanpa baris ini
 * halaman memakai kelas yang tidak pernah didefinisikan, dan itulah keadaannya
 * sebelum hari ini — kartu dan hero dirender polos.
 */
import '@/Pages/LMS/SOP/Shared/support/sopTheme.css';

/**
 * Halaman ini SENGAJA tipis.
 *
 * Seluruh perakit hidup di components/SYSTEM/ReportStudio supaya modul kedua
 * cukup menambah halaman setipis ini. Komponen bersama juga tak boleh tinggal
 * di dalam Pages/: resolver Inertia memetakan seluruh isinya menjadi nama
 * halaman.
 */
const props = defineProps({
    baseUrl: { type: String, required: true },
    datasetAwal: { type: String, default: 'kepatuhan' },
});

const DATASET = ['kepatuhan', 'dokumen'];
const datasetAktif = ref(props.datasetAwal);

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

function resetTemplateAktif() {
    if (studioRef.value) {
        studioRef.value.templateAktif = null;
    }
    templateAktif.value = null;
}

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
async function gantiDataset(datasetBaru) {
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

/* Diisi ReportStudio lewat emit `ringkas`. `baris: null` = belum ada pratinjau,
   dan chip-nya menulis "—" bukan "0". */
const ringkas = ref({ mode: 'ringkasan', kolom: 0, baris: null });
</script>

<style scoped>
.ses-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Pemindah sumber data — bentuknya sengaja sama dengan Export Studio Visitor:
   dua Studio yang bekerja dengan cara yang sama sebaiknya juga terlihat sama. */
.ses-dataset-switcher {
    display: inline-flex;
    align-items: center;
    padding: 3px;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: rgba(241, 245, 249, 0.95);
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
}

.ses-dataset-btn {
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

.ses-dataset-btn:hover {
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
}

.ses-dataset-btn.is-active {
    background: var(--hcis-white);
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.1), 0 1px 2px rgba(99, 102, 241, 0.06);
}

.ses-dataset-icon {
    font-size: 0.8125rem;
}

@media (max-width: 767.98px) {
    .ses-header-actions {
        width: 100%;
        justify-content: flex-start;
    }
    .ses-dataset-switcher {
        width: 100%;
    }
    .ses-dataset-btn {
        flex: 1 1 0;
        justify-content: center;
    }
}

.ses-tanggal {
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

.ses-tanggal:hover {
    border-color: var(--hcis-brand-400, var(--hcis-indigo-400));
    box-shadow: 0 4px 14px -2px rgba(99, 102, 241, 0.16);
    transform: translateY(-1px);
}

.ses-tanggal-icon {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    font-size: 0.82rem;
    display: flex;
    align-items: center;
}

.ses-tanggal-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
    background: rgba(99, 102, 241, 0.1);
    padding: 0.08rem 0.4rem;
    border-radius: 9999px;
}

.ses-tanggal-dot {
    color: var(--hcis-border-medium, var(--hcis-slate-300));
    font-size: 0.75rem;
    font-weight: 700;
}

.ses-tanggal-text {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-text-strong, var(--hcis-slate-800));
    font-variant-numeric: tabular-nums;
}

.ses-console-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    width: 100%;
}

.ses-header-tabs-wrap {
    display: flex;
    align-items: center;
    width: 100%;
}

.rs-template-aktif-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.7rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: linear-gradient(135deg, var(--hcis-yellow-50), var(--hcis-green-50));
    border: 1px solid #fde047;
    color: #854d0e;
    font-size: 0.76rem;
    font-weight: 600;
    box-shadow: 0 2px 6px rgba(234, 179, 8, 0.15);
}

.rs-template-aktif-star {
    color: #eab308;
    display: flex;
    align-items: center;
}

.rs-template-aktif-text {
    font-weight: 700;
    color: var(--hcis-yellow-900);
}

.rs-template-aktif-mode {
    font-size: 0.68rem;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: rgba(0, 0, 0, 0.06);
    text-transform: capitalize;
}

.rs-template-reset-btn {
    padding: 0.1rem;
    color: #854d0e;
}
</style>
