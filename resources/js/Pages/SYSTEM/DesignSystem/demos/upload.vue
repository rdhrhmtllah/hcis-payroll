<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisUpload Configurator</h3>
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
                <div class="p-3.5 bg-white rounded-2xl shadow-sm border border-slate-200" style="width: 100%; max-width: 580px;">
                    <HcisUpload
                        v-model="studioFileList"
                        :label="studioLabel"
                        :layout="studioLayout"
                        :border-style="studioBorderStyle"
                        :sentiment="studioSentiment"
                        :pattern="studioPattern"
                        :accept="studioAccept"
                        :limit="studioLimit"
                        :max-size="studioMaxSize"
                        :disabled="studioDisabled"
                        :hint="studioHint"
                    />
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Label Dokumen</label>
                    <input v-model="studioLabel" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-4">
                    <label class="ds-control-label">Tata Letak (Layout)</label>
                    <select v-model="studioLayout" class="form-select form-select-sm">
                        <option value="dropzone">dropzone (Area Kotak Luas)</option>
                        <option value="bar">bar (Bar Horizontal Ramping)</option>
                        <option value="button">button (Tombol Ringkas)</option>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label class="ds-control-label">Bentuk Garis (Border)</label>
                    <select v-model="studioBorderStyle" class="form-select form-select-sm">
                        <option value="solid">solid (Garis Lurus Presisi)</option>
                        <option value="dashed">dashed (Garis Putus-Putus)</option>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label class="ds-control-label">Sentimen Warna</label>
                    <select v-model="studioSentiment" class="form-select form-select-sm">
                        <option value="brand">brand (Royal Sapphire)</option>
                        <option value="purple">purple (Amethyst SOP)</option>
                        <option value="teal">teal (Mint SOP)</option>
                        <option value="success">success (Emerald)</option>
                        <option value="warning">warning (Amber)</option>
                        <option value="danger">danger (Crimson)</option>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label class="ds-control-label">Pola Signature (Pattern)</label>
                    <select v-model="studioPattern" class="form-select form-select-sm">
                        <option value="none">none (Tanpa Pola)</option>
                        <option value="grid">grid (Executive Blueprint Grid)</option>
                        <option value="dots">dots (Micro-Dot Matrix)</option>
                        <option value="crosshair">crosshair (Precision Crosshair)</option>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label class="ds-control-label">Format Diterima (Accept)</label>
                    <select v-model="studioAccept" class="form-select form-select-sm">
                        <option value=".pdf,.jpg,.png">PDF & Gambar (.pdf, .jpg, .png)</option>
                        <option value=".pdf">PDF Saja (.pdf)</option>
                        <option value=".xlsx,.csv">Spreadsheet (.xlsx, .csv)</option>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label class="ds-control-label">Batas Berkas (Limit)</label>
                    <select v-model.number="studioLimit" class="form-select form-select-sm">
                        <option :value="1">Maksimal 1 Berkas</option>
                        <option :value="3">Maksimal 3 Berkas</option>
                        <option :value="5">Maksimal 5 Berkas</option>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label class="ds-control-label">Ukuran Maksimal (MB)</label>
                    <select v-model.number="studioMaxSize" class="form-select form-select-sm">
                        <option :value="2">2 MB</option>
                        <option :value="5">5 MB</option>
                        <option :value="10">10 MB</option>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label class="ds-control-label">Status Interaktivitas</label>
                    <div class="form-check form-switch pt-1 m-0">
                        <input id="up-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                        <label class="form-check-label text-xs fw-semibold" for="up-dis">Disabled State</label>
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
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         2. ENTERPRISE SCENARIOS & REAL-WORLD WORKFLOWS
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'variants'" class="ds-card-demo-wrap">
        <!-- Showcase 1: Solid Border vs Dashed Dropzone -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Perbandingan Garis Lurus (Solid) vs Garis Putus-Putus (Dashed):</span>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="p-3.5 bg-white rounded-2xl border border-slate-200 shadow-sm h-100">
                        <span class="text-xs text-slate-700 fw-bold mb-2 d-flex align-items-center gap-1.5">
                            <HcisIcon name="bounding-box" size="xs" class="text-indigo-600" />
                            Garis Lurus Presisi (border-style="solid"):
                        </span>
                        <HcisUpload
                            v-model="berkasSolid"
                            label="Dokumen Kontrak Perjanjian Kerja"
                            border-style="solid"
                            sentiment="brand"
                            pattern="grid"
                            accept=".pdf"
                            :limit="2"
                            :max-size="10"
                            hint="Format PDF · Maksimal 10 MB"
                        />
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="p-3.5 bg-white rounded-2xl border border-slate-200 shadow-sm h-100">
                        <span class="text-xs text-slate-700 fw-bold mb-2 d-flex align-items-center gap-1.5">
                            <HcisIcon name="border-style" size="xs" class="text-purple-600" />
                            Garis Putus-Putus Standar (border-style="dashed"):
                        </span>
                        <HcisUpload
                            v-model="berkasDashed"
                            label="Lampiran Bukti Pengesahan SOP"
                            border-style="dashed"
                            sentiment="purple"
                            pattern="dots"
                            accept=".pdf,.docx"
                            :limit="2"
                            :max-size="10"
                            hint="Format PDF, DOCX · Maksimal 10 MB"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Showcase 2: Compact Horizontal Bar Layout -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Mode Bar Horizontal Ramping (layout="bar") untuk Formulir Padat:</span>
            <div class="p-3.5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                <div class="d-flex flex-column gap-3">
                    <HcisUpload
                        v-model="berkasBarPdf"
                        label="Lampiran SK Direksi (Bar Mode - Solid)"
                        layout="bar"
                        border-style="solid"
                        sentiment="brand"
                        accept=".pdf"
                        :limit="1"
                        hint="Format PDF · Maks 5 MB"
                    />
                    <HcisUpload
                        v-model="berkasBarExcel"
                        label="Data Rekap Nilai KPI (Bar Mode - Solid Teal)"
                        layout="bar"
                        border-style="solid"
                        sentiment="teal"
                        accept=".xlsx,.csv"
                        :limit="1"
                        hint="Format XLSX, CSV · Maks 10 MB"
                    />
                </div>
            </div>
        </div>

        <!-- Showcase 3: Multi-format File Cards with Lightbox Preview -->
        <div class="ds-demo-group">
            <span class="ds-group-label">3. Kartu Berkas Eksekutif Berbagai Format dengan Integrasi HcisFileViewer:</span>
            <div class="p-3.5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                <HcisUpload
                    v-model="berkasMulti"
                    label="Koleksi Berkas Lampiran Multi-Format"
                    border-style="solid"
                    sentiment="brand"
                    pattern="crosshair"
                    :limit="6"
                    hint="Klik ikon mata untuk pratinjau langsung via HcisFileViewer"
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">State Non-Aktif (Disabled):</span>
            <div class="p-3.5 bg-white rounded-2xl border border-slate-200" style="max-width: 580px;">
                <HcisUpload
                    v-model="berkasLengkap"
                    label="Unggah Berkas Cuti (Terkunci)"
                    border-style="solid"
                    disabled
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisUpload, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioLabel = ref('Lampiran Bukti Medis & Resep');
const studioLayout = ref('dropzone');
const studioBorderStyle = ref('solid');
const studioSentiment = ref('brand');
const studioPattern = ref('grid');
const studioAccept = ref('.pdf,.jpg,.png');
const studioLimit = ref(3);
const studioMaxSize = ref(5);
const studioDisabled = ref(false);
const studioHint = ref('Format PDF, JPG, atau PNG · Maksimal 5 MB per berkas');
const copied = ref(false);

const studioFileList = ref([
    { name: 'surat-keterangan-dokter-agustus.pdf', size: 1420000, status: 'success' },
]);

// Scenarios State
const berkasSolid = ref([
    { name: 'perjanjian-kerja-bersama-2026.pdf', size: 2850000, status: 'success' },
]);

const berkasDashed = ref([
    { name: 'sop-penilaian-kinerja-sdm.docx', size: 1150000, status: 'success' },
]);

const berkasBarPdf = ref([]);
const berkasBarExcel = ref([
    { name: 'rekapitulasi-bobot-kpi-q3.xlsx', size: 840000, status: 'success' },
]);

const berkasMulti = ref([
    { name: 'dokumen-evaluasi-direksi.pdf', size: 3400000, status: 'success' },
    { name: 'tabel-kalkulasi-skor-akhir.xlsx', size: 920000, status: 'success' },
    { name: 'foto-dokumentasi-assesmen.png', size: 1650000, status: 'success' },
    { name: 'arsip-backup-lampiran.zip', size: 5200000, status: 'success' },
]);

const berkasLengkap = ref([
    { name: 'laporan-kinerja-q3-2026.pdf', size: 2450000, status: 'success' },
    { name: 'foto-bukti-kegiatan-lapangan.png', size: 850000, status: 'success' },
]);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="fileList"`);
    if (studioLabel.value) props.push(`label="${studioLabel.value}"`);
    if (studioLayout.value !== 'dropzone') props.push(`layout="${studioLayout.value}"`);
    if (studioBorderStyle.value !== 'dashed') props.push(`border-style="${studioBorderStyle.value}"`);
    if (studioSentiment.value !== 'brand') props.push(`sentiment="${studioSentiment.value}"`);
    if (studioPattern.value !== 'none') props.push(`pattern="${studioPattern.value}"`);
    if (studioAccept.value) props.push(`accept="${studioAccept.value}"`);
    if (studioLimit.value > 0) props.push(`:limit="${studioLimit.value}"`);
    if (studioMaxSize.value > 0) props.push(`:max-size="${studioMaxSize.value}"`);
    if (studioDisabled.value) props.push(`disabled`);
    return `<HcisUpload\n    ${props.join('\n    ')}\n/>`;
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
</style>
