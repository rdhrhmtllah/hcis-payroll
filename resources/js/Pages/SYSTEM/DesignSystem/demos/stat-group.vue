<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisStatGroup Dashboard Configurator</h3>
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
                <div style="width: 100%; max-width: 900px;">
                    <HcisStatGroup
                        :stats="demoStats"
                        :cols="studioCols"
                        :layout="studioLayout"
                        :gap="studioGap"
                        :appearance="studioAppearance"
                        @item-click="handleItemClick"
                    />

                    <div v-if="lastClick" class="mt-3 p-2 bg-slate-900 text-slate-100 rounded-lg text-xs font-mono d-flex align-items-center">
                        <HcisIcon name="info-circle-fill" size="2xs" class="text-indigo-400 me-2" />
                        <span><strong>Kartu Terpilih:</strong> {{ lastClick.label }} (Nilai: {{ lastClick.value }} {{ lastClick.suffix || '' }})</span>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Jumlah Kolom (Cols)</label>
                    <select v-model.number="studioCols" class="form-select form-select-sm">
                        <option :value="2">2 Kolom</option>
                        <option :value="3">3 Kolom</option>
                        <option :value="4">4 Kolom</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Tata Letak (Layout)</label>
                    <select v-model="studioLayout" class="form-select form-select-sm">
                        <option value="default">Default (Vertikal)</option>
                        <option value="horizontal">Horizontal (Side-by-Side)</option>
                        <option value="compact">Compact (Mini)</option>
                        <option value="hero">Hero (High Impact)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Tampilan (Appearance)</label>
                    <select v-model="studioAppearance" class="form-select form-select-sm">
                        <option value="crystal">Crystal Glassmorphism</option>
                        <option value="solid">Gemstone Solid</option>
                        <option value="glass">Frosted Glass</option>
                        <option value="obsidian">Obsidian Dark</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Jarak Grid (Gap)</label>
                    <select v-model="studioGap" class="form-select form-select-sm">
                        <option value="sm">Kecil (sm - 10px)</option>
                        <option value="md">Sedang (md - 16px)</option>
                        <option value="lg">Besar (lg - 24px)</option>
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
            <span class="ds-group-label">1. Skenario Matriks Statistik Kepegawaian & Presensi:</span>
            <HcisStatGroup :stats="demoStats" :cols="4" appearance="crystal" gap="md" />
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Grid Mode Slot Kustom (&lt;HcisStatCard /&gt; Komposisi Campuran):</span>
            <HcisStatGroup :cols="3" gap="md">
                <HcisStatCard
                    label="Efisiensi Jam Lembur"
                    value="94.2"
                    suffix="%"
                    trend="up"
                    delta="+4.8%"
                    icon="lightning-charge-fill"
                    sentiment="success"
                    caption="Peningkatan efisiensi operasional"
                    :progress="94.2"
                    target="Target 90%"
                />
                <HcisStatCard
                    label="Ketidakhadiran (Absen)"
                    value="2.1"
                    suffix="%"
                    trend="down"
                    delta="-0.5%"
                    icon="person-x-fill"
                    sentiment="danger"
                    caption="Penurunan tingkat mangkir"
                    :progress="2.1"
                    target="Toleransi Maks 3%"
                />
                <HcisStatCard
                    label="Kompilasi Laporan Bulanan"
                    value=""
                    loading
                    icon="arrow-repeat"
                />
            </HcisStatGroup>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisStatGroup, HcisStatCard, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioCols = ref(4);
const studioLayout = ref('default');
const studioAppearance = ref('crystal');
const studioGap = ref('md');
const lastClick = ref(null);
const copied = ref(false);

const demoStats = [
    { label: 'Total Kuota Cuti', value: '12', suffix: 'Hari', icon: 'calendar-check-fill', sentiment: 'primary', caption: 'Periode Tahun 2026', clickable: true },
    { label: 'Sisa Kuota Cuti', value: '8', suffix: 'Hari', icon: 'clock-history', sentiment: 'success', progress: 66, target: 'Tersisa 66%', caption: 'Dapat digunakan s/d Des 2026', clickable: true },
    { label: 'Pengajuan Pending', value: '2', suffix: 'Berkas', icon: 'hourglass-split', sentiment: 'warning', delta: '+1 baru', trend: 'up', caption: 'Menunggu review atasan', clickable: true },
    { label: 'Total Izin Terpakai', value: '2', suffix: 'Hari', icon: 'person-badge-fill', sentiment: 'danger', caption: 'Izin Sakit / Alasan Penting', clickable: true },
];

function handleItemClick(item) {
    lastClick.value = item;
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`:stats="statsData"`);
    if (studioCols.value !== 4) props.push(`:cols="${studioCols.value}"`);
    if (studioLayout.value !== 'default') props.push(`layout="${studioLayout.value}"`);
    if (studioAppearance.value !== 'crystal') props.push(`appearance="${studioAppearance.value}"`);
    if (studioGap.value !== 'md') props.push(`gap="${studioGap.value}"`);

    return `<HcisStatGroup\n    ${props.join('\n    ')}\n    @item-click="onStatClick"\n/>`;
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

.ds-studio-panel { border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200)); border-radius: 12px; overflow: hidden; }
.ds-studio-header { padding: 1rem; border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200)); display: flex; justify-content: space-between; align-items: center; }
.ds-studio-badge { background: var(--hcis-indigo-500); color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
.ds-stage-switcher { display: flex; gap: 4px; background: var(--hcis-slate-100); padding: 4px; border-radius: 8px; }
.ds-stage-btn { border: none; padding: 4px 12px; font-size: 0.75rem; border-radius: 6px; cursor: pointer; transition: 0.2s; }
.ds-stage-btn.is-active { background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.1); font-weight: 600; }
.ds-preview-stage { padding: 2rem; display: flex; justify-content: center; background: var(--hcis-slate-50); }
.ds-control-label { font-size: 0.75rem; font-weight: 600; color: var(--hcis-slate-500); margin-bottom: 0.25rem; display: block; }
.ds-code-generator { margin-top: 1.5rem; background: var(--hcis-slate-900); border-radius: 8px; overflow: hidden; }
.ds-code-header { padding: 0.5rem 1rem; background: var(--hcis-slate-800); display: flex; justify-content: space-between; align-items: center; }
.ds-code-lang { color: var(--hcis-slate-400); font-size: 0.7rem; font-family: monospace; }
.ds-copy-btn { color: #fff; font-size: 0.7rem; border: none; background: transparent; cursor: pointer; }
.ds-code-block { padding: 1rem; color: var(--hcis-slate-200); font-size: 0.85rem; overflow-x: auto; margin: 0; }
</style>
