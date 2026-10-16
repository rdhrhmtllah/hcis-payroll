<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisFileViewer Modal Lightbox Studio</h3>
            </div>
            <!-- Stage Background Switcher -->
            <div class="ds-stage-switcher" role="group" aria-label="Latar Panggung">
                <button
                    type="button"
                    class="ds-stage-btn"
                    :class="{ 'is-active': studioStageBg === 'light' }"
                    @click="studioStageBg = 'light'"
                >
                    Light
                </button>
                <button
                    type="button"
                    class="ds-stage-btn"
                    :class="{ 'is-active': studioStageBg === 'dark' }"
                    @click="studioStageBg = 'dark'"
                >
                    Dark 🌑
                </button>
                <button
                    type="button"
                    class="ds-stage-btn"
                    :class="{ 'is-active': studioStageBg === 'mesh' }"
                    @click="studioStageBg = 'mesh'"
                >
                    Mesh 🔮
                </button>
            </div>
        </div>

        <div class="ds-studio-body">
            <!-- Preview Stage Canvas -->
            <div
                class="ds-preview-stage"
                :class="[
                    `ds-stage-bg--${studioStageBg}`,
                    { 'ds-stage-dark-mode': studioStageBg === 'dark' }
                ]"
            >
                <div class="d-flex flex-column gap-3 w-100" style="max-width: 720px;">
                    <!-- Gallery Launch Banner -->
                    <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="fv-gallery-badge">
                                <HcisIcon name="images" size="md" />
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-slate-800 m-0">Galeri Berkas Terpadu HCIS ({{ sampleFiles.length }} Dokumen)</h4>
                                <small class="text-xs text-slate-500">Pratinjau dengan kontrol izin & watermark keamanan</small>
                            </div>
                        </div>
                        <HcisButton
                            label="Buka Galeri Penuh"
                            variant="primary"
                            size="sm"
                            leading-icon="arrows-fullscreen"
                            @click="openGallery"
                        />
                    </div>

                    <!-- Grid of Individual Files -->
                    <div class="row g-2">
                        <div
                            v-for="sample in sampleFiles"
                            :key="sample.name"
                            class="col-12 col-md-6"
                        >
                            <div class="fv-demo-card" @click="openSingleViewer(sample)">
                                <div class="fv-demo-card__icon" :class="sample.typeClass">
                                    <HcisIcon :name="sample.icon" size="sm" />
                                </div>
                                <div class="fv-demo-card__info">
                                    <div class="fv-demo-card__name" :title="sample.name">{{ sample.name }}</div>
                                    <div class="fv-demo-card__meta">
                                        {{ sample.typeLabel }} · {{ sample.sizeText }}
                                        <span v-if="sample.allowDownload === false" class="text-amber-600 font-bold ms-1">🔒 View-Only</span>
                                    </div>
                                </div>
                                <button type="button" class="fv-demo-card__btn" aria-label="Buka Pratinjau">
                                    <HcisIcon name="eye-fill" size="xs" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Studio Security Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="ds-control-label">Teks Watermark Keamanan</label>
                    <input v-model="studioWatermark" type="text" class="form-control form-control-sm" placeholder="Contoh: CONFIDENTIAL - AUDIT INTERNAL" />
                </div>
                <div class="col-12 col-md-6 d-flex align-items-center gap-3 pt-4">
                    <div class="form-check form-switch m-0">
                        <input id="sw-dl" v-model="studioAllowDownload" class="form-check-input" type="checkbox" />
                        <label class="form-check-label text-xs fw-semibold" for="sw-dl">Izinkan Unduh (allowDownload)</label>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input id="sw-pr" v-model="studioAllowPrint" class="form-check-input" type="checkbox" />
                        <label class="form-check-label text-xs fw-semibold" for="sw-pr">Izinkan Cetak (allowPrint)</label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch m-0">
                        <input id="sw-cf" v-model="studioConfidential" class="form-check-input" type="checkbox" />
                        <label class="form-check-label text-xs fw-semibold text-danger" for="sw-cf">Tandai sebagai Dokumen Rahasia (confidential = true / View-Only)</label>
                    </div>
                </div>
            </div>

            <!-- Code Generator -->
            <div class="ds-code-generator">
                <div class="ds-code-header">
                    <span class="ds-code-lang">Vue 3 SFC Template</span>
                    <button type="button" class="ds-copy-btn" @click="copySnippet">
                        <HcisIcon :name="copied ? 'check2' : 'clipboard'" size="xs" />
                        <span>{{ copied ? 'Tersalin!' : 'Salin Kode' }}</span>
                    </button>
                </div>
                <pre class="ds-code-block"><code>{{ generatedSnippet }}</code></pre>
            </div>
        </div>

        <!-- Lightbox File Viewer Component -->
        <HcisFileViewer
            v-model="showViewer"
            :files="viewerFiles"
            :file="activeFile"
            :allow-download="studioAllowDownload"
            :allow-print="studioAllowPrint"
            :confidential="studioConfidential"
            :watermark="studioWatermark"
        />
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         2. ENTERPRISE SCENARIOS & REAL-WORLD WORKFLOWS
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'variants'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Skenario Dokumen Rahasia & Sensitif (Hanya View, Tanpa Unduh / Cetak):</span>
            <div class="d-flex flex-wrap gap-2">
                <HcisButton
                    label="Pratinjau Slip Gaji Eksekutif (🔒 View-Only)"
                    leading-icon="shield-lock-fill"
                    variant="danger"
                    size="sm"
                    @click="openConfidentialDoc"
                />
                <HcisButton
                    label="Pratinjau Surat Keputusan Direksi (Boleh Unduh)"
                    leading-icon="file-earmark-pdf-fill"
                    variant="secondary"
                    size="sm"
                    @click="openSingleViewer(sampleFiles[0])"
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Pratinjau Berkas Tidak Dikenal / Office Spreadsheet:</span>
            <div class="d-flex gap-2">
                <HcisButton
                    label="Buka Format Spreadsheet (.XLSX)"
                    leading-icon="file-earmark-excel-fill"
                    variant="secondary"
                    size="sm"
                    @click="openSingleViewer(sampleFiles[4])"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisButton, HcisFileViewer, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const showViewer = ref(false);
const activeFile = ref(null);
const viewerFiles = ref([]);
const studioAllowDownload = ref(true);
const studioAllowPrint = ref(true);
const studioConfidential = ref(false);
const studioWatermark = ref('HCIS ENTERPRISE • DOKUMEN RAHASIA');
const copied = ref(false);

const sampleFiles = ref([
    {
        name: 'SK_Pengangkatan_Karyawan_2026.pdf',
        typeLabel: 'Dokumen Resmi PDF',
        typeClass: 'is-pdf',
        icon: 'file-earmark-pdf-fill',
        sizeText: '1.4 MB',
        size: 1468000,
        url: 'https://raw.githubusercontent.com/mozilla/pdf.js/ba2edeae/examples/learning/helloworld.pdf',
    },
    {
        name: 'Foto_Kuitansi_Rawat_Jalan_Klinik.jpg',
        typeLabel: 'Foto Bukti Pembayaran',
        typeClass: 'is-image',
        icon: 'file-earmark-image-fill',
        sizeText: '2.8 MB',
        size: 2936000,
        url: 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=1200&auto=format&fit=crop&q=80',
    },
    {
        name: 'Video_Panduan_Onboarding_HCIS.mp4',
        typeLabel: 'Video Tutorial Video',
        typeClass: 'is-video',
        icon: 'file-earmark-play-fill',
        sizeText: '14.2 MB',
        size: 14889000,
        url: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
    },
    {
        name: 'Rekaman_Briefing_Kinerja_Q3.mp3',
        typeLabel: 'Audio Rapat Direksi',
        typeClass: 'is-audio',
        icon: 'file-earmark-music-fill',
        sizeText: '4.6 MB',
        size: 4823000,
        url: 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
    },
    {
        name: 'Rekap_Alokasi_Budget_Payroll_2026.xlsx',
        typeLabel: 'Spreadsheet Excel',
        typeClass: 'is-generic',
        icon: 'file-earmark-excel-fill',
        sizeText: '840 KB',
        size: 860000,
        url: 'https://example.com/download/budget-payroll.xlsx',
    },
]);

function openSingleViewer(sample) {
    viewerFiles.value = [sample];
    activeFile.value = sample;
    showViewer.value = true;
}

function openGallery() {
    viewerFiles.value = sampleFiles.value;
    activeFile.value = sampleFiles.value[0];
    showViewer.value = true;
}

function openConfidentialDoc() {
    const confidentialFile = {
        name: 'Slip_Gaji_Eksekutif_Agustus_2026.pdf',
        typeLabel: 'Dokumen Gaji Rahasia',
        typeClass: 'is-pdf',
        icon: 'file-earmark-pdf-fill',
        sizeText: '520 KB',
        size: 532480,
        url: 'https://raw.githubusercontent.com/mozilla/pdf.js/ba2edeae/examples/learning/helloworld.pdf',
        allowDownload: false,
        allowPrint: false,
        confidential: true,
        watermark: 'RAHASIA • DIREKSI ONLY • NIK 91024',
    };
    viewerFiles.value = [confidentialFile];
    activeFile.value = confidentialFile;
    showViewer.value = true;
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="showPreview"`);
    props.push(`:files="documentList"`);
    if (!studioAllowDownload.value) props.push(`:allow-download="false"`);
    if (!studioAllowPrint.value) props.push(`:allow-print="false"`);
    if (studioConfidential.value) props.push(`confidential`);
    if (studioWatermark.value) props.push(`watermark="${studioWatermark.value}"`);

    return `<HcisFileViewer\n    ${props.join('\n    ')}\n/>`;
});

function copySnippet() {
    navigator.clipboard.writeText(generatedSnippet.value);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}
</script>

<style scoped>
.ds-card-demo-wrap { display: flex; flex-direction: column; gap: 1.5rem; }
.ds-demo-group { display: flex; flex-direction: column; gap: 0.75rem; }
.ds-group-label { font-size: 0.8125rem; font-weight: 700; color: var(--hcis-slate-800, var(--hcis-slate-800)); letter-spacing: -0.01em; }

.fv-gallery-badge {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 12px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.fv-demo-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 150ms;
    transition-timing-function: ease;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.fv-demo-card:hover {
    border-color: var(--hcis-indigo-300);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.08);
    transform: translateY(-1px);
}

.fv-demo-card__icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.fv-demo-card__icon.is-pdf { background: var(--hcis-red-50); color: var(--hcis-red-600); }
.fv-demo-card__icon.is-image { background: var(--hcis-emerald-50); color: var(--hcis-emerald-600); }
.fv-demo-card__icon.is-video { background: var(--hcis-violet-50); color: var(--hcis-violet-600); }
.fv-demo-card__icon.is-audio { background: var(--hcis-amber-50); color: var(--hcis-amber-600); }
.fv-demo-card__icon.is-generic { background: var(--hcis-slate-100); color: var(--hcis-slate-600); }

.fv-demo-card__info {
    flex: 1;
    min-width: 0;
}

.fv-demo-card__name {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-slate-900);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.fv-demo-card__meta {
    font-size: 0.6875rem;
    color: var(--hcis-slate-500);
    margin-top: 1px;
}

.fv-demo-card__btn {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 6px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    color: var(--hcis-slate-500);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.fv-demo-card:hover .fv-demo-card__btn {
    background: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-600);
    color: var(--hcis-white);
}
</style>
