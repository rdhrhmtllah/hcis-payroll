<template>
    <div class="container-fluid px-0 sop-scope visitor-export-studio">
        <!-- ══ 1. UNIFIED HCIS HEADER (IDENTICAL TO SOP EXPORT STUDIO) ══ -->
        <HcisHeader
            variant="glass"
            sentiment="purple"
            size="md"
            icon="bi-sliders"
            kicker="Visitor Report Builder · Governance"
            :title="judulHalaman"
            :subtitle="subjudulHalaman"
            class="mb-4"
        >
            <template #actions>
                <div class="ses-header-actions">
                    <!-- Dataset Source Switcher (Buku Tamu vs Permit Kendaraan).
                         Hilang ketika datasetnya dipaku server: menekannya akan
                         memanggil endpoint domain lain dan dibalas 403 tanpa
                         sebab yang terbaca pemakainya. -->
                    <div
                        v-if="!datasetTerkunci"
                        class="ses-dataset-switcher"
                        role="group"
                        aria-label="Sumber Data Laporan"
                    >
                        <button
                            type="button"
                            class="ses-dataset-btn"
                            :class="{ 'is-active': datasetAktif === 'tamu' }"
                            title="Sumber data transaksi kunjungan tamu"
                            @click="gantiDataset('tamu')"
                        >
                            <i class="bi bi-person-vcard-fill ses-dataset-icon"></i>
                            <span>Buku Tamu</span>
                        </button>
                        <button
                            type="button"
                            class="ses-dataset-btn"
                            :class="{ 'is-active': datasetAktif === 'permit' }"
                            title="Sumber data transaksi permit kendaraan"
                            @click="gantiDataset('permit')"
                        >
                            <i class="bi bi-car-front-fill ses-dataset-icon"></i>
                            <span>Permit Kendaraan</span>
                        </button>
                    </div>

                    <!-- Active Template Indicator Pill -->
                    <div v-if="templateAktif" class="rs-template-aktif-pill">
                        <i class="bi bi-stars rs-template-aktif-star"></i>
                        <span class="rs-template-aktif-text">{{ templateAktif.nama }}</span>
                        <span class="rs-template-aktif-mode">{{ templateAktif.mode === 'rinci' ? 'Rinci' : 'Ringkasan' }}</span>
                        <button
                            type="button"
                            class="btn btn-link rs-template-reset-btn"
                            title="Lepaskan template aktif"
                            @click="resetTemplateAktif"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Live Studio Metric Chips -->
                    <div class="ses-chips-group">
                        <span class="ses-chip" :title="`Bentuk laporan: ${ringkas.mode}`">
                            <i class="bi" :class="ringkas.mode === 'rinci' ? 'bi-list-columns-reverse' : 'bi-pie-chart-fill'"></i>
                            <span>{{ ringkas.mode === 'rinci' ? 'Rinci' : 'Ringkasan' }}</span>
                        </span>
                        <span class="ses-chip" title="Jumlah kolom aktif">
                            <i class="bi bi-columns-gap"></i>
                            <span>{{ ringkas.kolom }} kolom</span>
                        </span>
                        <span class="ses-chip" title="Jumlah baris pratinjau">
                            <i class="bi bi-table"></i>
                            <span>{{ ringkas.baris !== null ? `${ringkas.baris} baris` : 'Baris: —' }}</span>
                        </span>
                    </div>

                    <!-- Live System Date Pill -->
                    <div class="ses-tanggal" title="Tanggal sistem saat ini">
                        <i class="bi bi-calendar3-event-fill ses-tanggal-icon"></i>
                        <span class="ses-tanggal-label">Hari ini</span>
                        <span class="ses-tanggal-dot">·</span>
                        <span class="ses-tanggal-text">{{ tanggal }}</span>
                    </div>
                </div>
            </template>

            <!-- ══ INTEGRATED HEADER TABS (GALERI TEMPLATE VS STUDIO PERAKIT) ══ -->
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

        <!-- ══ 2. CORE REPORT STUDIO ENGINE (MATCHING SOP PARITY) ══ -->
        <div class="mb-5 sop-stack">
            <ReportStudio
                :key="datasetAktif"
                ref="studioRef"
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
 * Mengimpor SOP design tokens & utility classes yang menjadi fondasi
 * perakit ReportStudio (.sop-scope, .sop-stack, .stitch-card, .sop-eyebrow, .sop-h).
 */
import '@/Pages/LMS/SOP/Shared/support/sopTheme.css';

defineOptions({ name: 'ExportStudioVisitorPage' });

const props = defineProps({
    baseUrl: { type: String, default: '/api/v1/visitor/export-studio' },
    datasetAwal: { type: String, default: 'tamu' },
    /*
     * Dipaku server pada halaman per dataset (DML-2026-106). Komponennya satu
     * untuk kedua halaman: isinya ratusan baris gaya perakit yang identik, dan
     * salinan kedua akan menua sendiri-sendiri.
     */
    datasetTerkunci: { type: Boolean, default: false },
});

const datasetAktif = ref(props.datasetAwal);

const judulHalaman = computed(() => {
    if (!props.datasetTerkunci) return 'Export Studio Visitor';

    return datasetAktif.value === 'permit' ? 'Export Studio Permit' : 'Export Studio Tamu';
});

const subjudulHalaman = computed(() => {
    if (!props.datasetTerkunci) {
        return 'Rakit laporan tamu dan permit sendiri, lihat hasilnya sebelum diunduh.';
    }

    return datasetAktif.value === 'permit'
        ? 'Rakit laporan permit kendaraan sendiri, lihat hasilnya sebelum diunduh.'
        : 'Rakit laporan kunjungan tamu sendiri, lihat hasilnya sebelum diunduh.';
});
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

const ringkas = ref({ mode: 'ringkasan', kolom: 0, baris: null });

const tanggal = computed(() =>
    new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
);

function memilikiDraft() {
    const studio = studioRef.value;
    const pilihan = studio?.pilihan;
    if (!pilihan) return false;

    return Boolean(studio?.templateAktif)
        || ['dimensi', 'ukuran', 'kolom', 'saringan'].some((key) => Array.isArray(pilihan[key]) && pilihan[key].length > 0);
}

async function gantiDataset(datasetBaru) {
    // Penjaga di fungsinya, bukan hanya pada tombolnya: tombol yang hilang dari
    // layar tidak menghalangi pemanggilan lewat konsol atau komponen lain.
    if (props.datasetTerkunci) return;
    if (!['tamu', 'permit'].includes(datasetBaru) || datasetBaru === datasetAktif.value) return;

    if (memilikiDraft()) {
        const lanjut = await hcisConfirm({
            title: 'Ganti sumber data laporan?',
            description: 'Pilihan laporan yang belum disimpan pada sumber saat ini akan dikosongkan.',
            kicker: 'Export Studio Visitor',
            icon: 'bi-arrow-left-right',
            sentiment: 'warning',
            confirmText: 'Ganti Sumber Data',
            cancelText: 'Tetap di Sini',
        });
        if (!lanjut) return;
    }

    datasetAktif.value = datasetBaru;
    templateAktif.value = null;
    ringkas.value = { mode: 'ringkasan', kolom: 0, baris: null };
}

function resetTemplateAktif() {
    if (studioRef.value) {
        studioRef.value.templateAktif = null;
    }
    templateAktif.value = null;
}
</script>

<style scoped>
.visitor-export-studio {
    min-width: 0;
    width: 100%;
}

.ses-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Dataset Switcher Segmented Control */
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

/* Live Metric Chips Group */
.ses-chips-group {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.ses-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.32rem 0.65rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    font-variant-numeric: tabular-nums;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    user-select: none;
}

.ses-chip i {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    font-size: 0.75rem;
}

/* Live System Date Pill */
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

.ses-header-tabs-wrap {
    display: flex;
    align-items: center;
    width: 100%;
}

/* Active Template Pill */
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
    line-height: 1;
    text-decoration: none;
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
</style>
