<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisSplitter Resizable Studio</h3>
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
                <div class="w-100 p-2 bg-white rounded-xl shadow-sm border border-slate-200" style="height: 380px;">
                    <HcisSplitter
                        :orientation="studioOrientation"
                        :initial-ratio="studioRatio"
                        :variant="studioVariant"
                        :pane-infos="[
                            { title: 'Navigasi Menu', icon: 'list-nested' },
                            { title: 'Konten Matriks', icon: 'grid-3x3-gap-fill' }
                        ]"
                        @resize="handleResize"
                        @collapse-change="handleCollapse"
                        @focus-change="handleFocusChange"
                    >
                        <template #first>
                            <div class="p-3 bg-slate-50 h-100 overflow-auto">
                                <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom border-slate-200">
                                    <HcisIcon name="list-nested" size="xs" class="text-indigo-600" />
                                    <span class="text-xs font-bold text-slate-800">Modul HCIS HR</span>
                                </div>
                                <ul class="list-unstyled d-flex flex-column gap-1 text-xs mb-0">
                                    <li class="p-2 rounded bg-indigo-50 text-indigo-700 font-bold d-flex align-items-center gap-2">
                                        <HcisIcon name="folder-fill" size="2xs" /> Operasional HR
                                    </li>
                                    <li class="p-2 text-slate-600 d-flex align-items-center gap-2">
                                        <HcisIcon name="file-earmark-text" size="2xs" /> Master Data Karyawan
                                    </li>
                                    <li class="p-2 text-slate-600 d-flex align-items-center gap-2">
                                        <HcisIcon name="file-earmark-text" size="2xs" /> Matriks Otorisasi
                                    </li>
                                </ul>
                            </div>
                        </template>

                        <template #second>
                            <div class="p-3 bg-white h-100 overflow-auto">
                                <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom border-slate-100">
                                    <HcisIcon name="grid-3x3-gap-fill" size="xs" class="text-indigo-600" />
                                    <span class="text-xs font-bold text-slate-800">Area Kerja Utama</span>
                                </div>
                                <p class="text-xs text-slate-600 mb-2">
                                    Splitter mendukung rasio instan (25:75, 50:50, 75:25), mode fokus penuh 100%, serta snap magnetik lembut.
                                </p>
                                <div v-if="lastEventLog" class="p-2 bg-slate-900 text-slate-100 rounded-lg text-2xs font-mono d-flex align-items-center">
                                    <HcisIcon name="terminal-fill" size="2xs" class="text-indigo-400 me-2" />
                                    <span><strong>Event:</strong> {{ lastEventLog }}</span>
                                </div>
                            </div>
                        </template>
                    </HcisSplitter>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Orientasi Pembagian</label>
                    <select v-model="studioOrientation" class="form-select form-select-sm">
                        <option value="horizontal">Horizontal (Kiri / Kanan)</option>
                        <option value="vertical">Vertical (Atas / Bawah)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Rasio Awal ({{ studioRatio }}%)</label>
                    <input v-model.number="studioRatio" type="range" min="15" max="85" class="form-range" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Varian Visual</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="card">Card (Kartu Elegan)</option>
                        <option value="glass">Glass (Kaca Blur)</option>
                        <option value="flat">Flat (Minimal Datar)</option>
                    </select>
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
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Skenario Split Vertikal (Statistik Atas & Tabel Bawah):</span>
            <div class="p-2 bg-white rounded-xl border border-slate-200 shadow-sm" style="height: 280px;">
                <HcisSplitter orientation="vertical" :initial-ratio="40" variant="card">
                    <template #first>
                        <div class="p-3 bg-slate-50 h-100">
                            <span class="text-xs font-bold text-slate-800">Panel Atas (40%)</span>
                            <p class="text-2xs text-slate-500 mb-0">Konsol Ringkasan Statistik KPI & Grafik</p>
                        </div>
                    </template>
                    <template #second>
                        <div class="p-3 bg-white h-100">
                            <span class="text-xs font-bold text-slate-800">Panel Bawah (60%)</span>
                            <p class="text-2xs text-slate-500 mb-0">Tabel Rincian Riwayat Transaksi Otorisasi</p>
                        </div>
                    </template>
                </HcisSplitter>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Penyesuaian Mobile:</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 text-xs text-slate-600">
                Pada resolusi ponsel (&lt;768px), <code>HcisSplitter</code> otomatis beralih menjadi <strong>Mobile Tab Switcher</strong> yang responsif dan nyaman disentuh.
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisSplitter, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioOrientation = ref('horizontal');
const studioRatio = ref(30);
const studioVariant = ref('card');
const lastEventLog = ref('');
const copied = ref(false);

function handleResize({ ratio }) {
    lastEventLog.value = `Rasio: ${ratio}% (Kiri) / ${Math.round(100 - ratio)}% (Kanan)`;
}

function handleCollapse({ pane, collapsed }) {
    lastEventLog.value = `Panel "${pane.toUpperCase()}" ${collapsed ? 'DILIPAT' : 'DIBUKA'}`;
}

function handleFocusChange({ paneIndex, isMaximized }) {
    lastEventLog.value = `Fokus Panel ${paneIndex + 1}: ${isMaximized ? '100% MAXIMIZED' : 'NORMAL'}`;
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    if (studioOrientation.value !== 'horizontal') props.push(`orientation="${studioOrientation.value}"`);
    if (studioRatio.value !== 50) props.push(`:initial-ratio="${studioRatio.value}"`);
    if (studioVariant.value !== 'card') props.push(`variant="${studioVariant.value}"`);

    return `<HcisSplitter\n    ${props.join('\n    ')}\n>\n    <template #first>Panel 1</template>\n    <template #second>Panel 2</template>\n</HcisSplitter>`;
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
