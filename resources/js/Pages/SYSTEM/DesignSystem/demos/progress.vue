<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisProgress Dynamic Studio</h3>
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
                <div class="p-4 bg-white rounded-xl shadow-sm border border-slate-200" style="width: 100%; max-width: 560px;">
                    <!-- Circular Mode -->
                    <div v-if="studioType === 'circle'" class="d-flex align-items-center justify-content-center py-3">
                        <HcisProgress
                            :value="studioValue"
                            type="circle"
                            :size="studioSize"
                            :sentiment="studioSentiment"
                            :animated="studioAnimated"
                            :indeterminate="studioIndeterminate"
                        />
                    </div>

                    <!-- Dashboard Arc Mode -->
                    <div v-else-if="studioType === 'dashboard'" class="d-flex align-items-center justify-content-center py-3">
                        <HcisProgress
                            :value="studioValue"
                            type="dashboard"
                            :size="studioSize"
                            :sentiment="studioSentiment"
                            :label="studioLabel"
                            :animated="studioAnimated"
                            :indeterminate="studioIndeterminate"
                        />
                    </div>

                    <!-- Segments Milestone Mode -->
                    <div v-else-if="studioType === 'segments'">
                        <HcisProgress
                            :value="studioValue"
                            type="segments"
                            :label="studioLabel"
                            :subtitle="studioSubtitle"
                            :size="studioSize"
                            :sentiment="studioSentiment"
                            :segments="studioSegments"
                        />
                    </div>

                    <!-- Linear Capsule Runway Bar (Default) -->
                    <div v-else>
                        <HcisProgress
                            :value="studioValue"
                            type="line"
                            :label="studioLabel"
                            :subtitle="studioSubtitle"
                            :size="studioSize"
                            :sentiment="studioSentiment"
                            :striped="studioStriped"
                            :animated="studioAnimated"
                            :inside-value="studioInsideValue"
                            :indeterminate="studioIndeterminate"
                            :glow="studioGlow"
                            :target-value="studioShowTarget ? studioTargetValue : null"
                        />
                    </div>

                    <!-- Simulation Controls Footer -->
                    <div class="d-flex align-items-center justify-content-between mt-3 pt-3 border-top border-slate-100">
                        <div class="d-flex align-items-center gap-2">
                            <HcisButton
                                size="sm"
                                variant="secondary"
                                :leading-icon="isSimulating ? 'arrow-repeat' : 'play-fill'"
                                :label="isSimulating ? 'Simulasi...' : 'Mulai Simulasi'"
                                :disabled="isSimulating"
                                @click="mulaiSimulasi"
                            />
                            <HcisButton v-if="studioValue > 0" size="sm" variant="ghost" label="Reset" @click="studioValue = 0" />
                        </div>
                        <span class="text-xs font-monospace fw-bold text-slate-700">{{ studioValue }}% Selesai</span>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Persentase ({{ studioValue }}%)</label>
                    <input v-model.number="studioValue" type="range" min="0" max="100" class="form-range" :disabled="studioIndeterminate" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Tipe (Type)</label>
                    <select v-model="studioType" class="form-select form-select-sm">
                        <option value="line">Line (Capsule Runway)</option>
                        <option value="circle">Circle (Radial Gauge)</option>
                        <option value="dashboard">Dashboard (Speedometer Arc)</option>
                        <option value="segments">Segments (Milestone Blocks)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Sentimen Warna</label>
                    <select v-model="studioSentiment" class="form-select form-select-sm">
                        <option value="brand">Brand (Royal Indigo)</option>
                        <option value="success">Success / Good (Emerald)</option>
                        <option value="warning">Warning (Amber Gold)</option>
                        <option value="danger">Danger / Bad (Crimson)</option>
                        <option value="info">Info (Ocean Sky)</option>
                        <option value="purple">Purple / Violet</option>
                        <option value="teal">Teal / Cyan</option>
                        <option value="neutral">Neutral (Steel Slate)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="xs">xs (Extra Small)</option>
                        <option value="sm">sm (Small)</option>
                        <option value="md">md (Standard)</option>
                        <option value="lg">lg (Large)</option>
                        <option value="xl">xl (Extra Large)</option>
                    </select>
                </div>

                <!-- Feature Switches -->
                <div class="col-12">
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0" v-if="studioType === 'line'">
                            <input id="pg-glow" v-model="studioGlow" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="pg-glow">Luminous Glow</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="pg-ani" v-model="studioAnimated" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="pg-ani">Animated Shimmer</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0" v-if="studioType === 'line'">
                            <input id="pg-str" v-model="studioStriped" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="pg-str">Striped Texture</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0" v-if="studioType === 'line'">
                            <input id="pg-ins" v-model="studioInsideValue" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="pg-ins">Label Dalam Bar</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0" v-if="studioType === 'line'">
                            <input id="pg-tar" v-model="studioShowTarget" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="pg-tar">Target Pin ({{ studioTargetValue }}%)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="pg-ind" v-model="studioIndeterminate" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="pg-ind">Indeterminate Scanner</label>
                        </div>
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
         2. ENTERPRISE SCENARIOS & REAL-WORLD SHOWCASE
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'variants'" class="ds-card-demo-wrap">
        <!-- Showcase 1: Dashboard Speedometer & Radial Gauge Meters -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Skenario Dashboard Radial & Speedometer Arc:</span>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm d-flex flex-column align-items-center justify-content-center">
                        <HcisProgress type="dashboard" :value="88" size="md" sentiment="brand" label="Capaian KPI" />
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm d-flex flex-column align-items-center justify-content-center">
                        <HcisProgress type="dashboard" :value="95" size="md" sentiment="success" label="Presensi On-Time" />
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm d-flex align-items-center justify-content-around">
                        <HcisProgress type="circle" :value="45" size="sm" sentiment="info" />
                        <HcisProgress type="circle" :value="80" size="md" sentiment="brand" />
                        <HcisProgress type="circle" :value="100" size="md" sentiment="success" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Showcase 2: Segmented Milestones & Target Pin Thresholds -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Skenario Segmented Milestones & Target Threshold:</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm d-flex flex-column gap-3">
                <HcisProgress
                    type="segments"
                    :value="60"
                    label="Tahapan Onboarding Karyawan Baru"
                    subtitle="3 dari 5 checklist modul telah diselesaikan"
                    sentiment="brand"
                    size="md"
                    :segments="5"
                />
                <HcisProgress
                    type="line"
                    :value="85"
                    label="Realisasi Anggaran Pelatihan SDM"
                    subtitle="Target kuartal Q3: 80%"
                    sentiment="purple"
                    size="lg"
                    animated
                    :target-value="80"
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Indeterminate Loading (Background Sync & Data Processing):</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm d-flex flex-column gap-3" style="max-width: 560px;">
                <HcisProgress label="Memproses sinkronisasi data master karyawan..." sentiment="brand" indeterminate size="md" />
                <HcisProgress label="Mengunggah berkas asesmen talenta..." sentiment="teal" indeterminate size="sm" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisProgress, HcisButton, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioValue = ref(75);
const studioType = ref('line');
const studioSentiment = ref('brand');
const studioSize = ref('md');
const studioLabel = ref('Kelengkapan Berkas Onboarding Talenta');
const studioSubtitle = ref('Tahap verifikasi dokumen HR');
const studioStriped = ref(false);
const studioAnimated = ref(true);
const studioInsideValue = ref(false);
const studioIndeterminate = ref(false);
const studioGlow = ref(true);
const studioShowTarget = ref(true);
const studioTargetValue = ref(80);
const studioSegments = ref(5);
const isSimulating = ref(false);
const copied = ref(false);

function mulaiSimulasi() {
    studioValue.value = 0;
    isSimulating.value = true;
    const interval = setInterval(() => {
        if (studioValue.value < 100) {
            studioValue.value += 5;
        } else {
            clearInterval(interval);
            isSimulating.value = false;
        }
    }, 100);
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`:value="${studioValue.value}"`);
    if (studioType.value !== 'line') props.push(`type="${studioType.value}"`);
    if (studioLabel.value && (studioType.value === 'line' || studioType.value === 'segments' || studioType.value === 'dashboard')) {
        props.push(`label="${studioLabel.value}"`);
    }
    if (studioSubtitle.value && (studioType.value === 'line' || studioType.value === 'segments')) {
        props.push(`subtitle="${studioSubtitle.value}"`);
    }
    if (studioSentiment.value !== 'brand') props.push(`sentiment="${studioSentiment.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioAnimated.value) props.push(`animated`);
    if (studioStriped.value && studioType.value === 'line') props.push(`striped`);
    if (studioInsideValue.value && studioType.value === 'line') props.push(`inside-value`);
    if (studioShowTarget.value && studioType.value === 'line') props.push(`:target-value="${studioTargetValue.value}"`);
    if (studioIndeterminate.value) props.push(`indeterminate`);
    if (studioType.value === 'segments') props.push(`:segments="${studioSegments.value}"`);

    return `<HcisProgress\n    ${props.join('\n    ')}\n/>`;
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
