<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO (UNIFIED DATE & RANGE)
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Unified Studio</span>
                <h3 class="ds-studio-title">HcisDatePicker Configurator (Single, Range & DateTime)</h3>
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
                <div style="width: 100%; max-width: 440px;">
                    <HcisField
                        id="studio-date-field"
                        :label="studioLabel"
                        :hint="studioHint"
                        :required="studioRequired"
                    >
                        <HcisDatePicker
                            v-if="studioType === 'date'"
                            v-model="studioDate"
                            type="date"
                            :placeholder="studioPlaceholder || 'YYYY-MM-DD'"
                            :size="studioSize"
                            :show-presets="studioShowPresets"
                            :clearable="studioClearable"
                            :disabled="studioDisabled"
                            :readonly="studioReadonly"
                        />
                        <HcisDatePicker
                            v-else-if="studioType === 'daterange'"
                            v-model="studioRange"
                            type="daterange"
                            :size="studioSize"
                            :show-presets="studioShowPresets"
                            :clearable="studioClearable"
                            :disabled="studioDisabled"
                            :readonly="studioReadonly"
                        />
                        <HcisDatePicker
                            v-else-if="studioType === 'datetime'"
                            v-model="studioDateTime"
                            type="datetime"
                            :size="studioSize"
                            :show-presets="studioShowPresets"
                            :clearable="studioClearable"
                            :disabled="studioDisabled"
                            :readonly="studioReadonly"
                        />
                    </HcisField>

                    <!-- Realtime Model Output Readout -->
                    <div class="mt-3 p-2.5 rounded-3 bg-white/80 border border-slate-200 text-xs shadow-sm">
                        <span class="text-slate-500 font-semibold">v-model Value:</span>
                        <code class="ms-1.5 font-bold text-brand-700">
                            {{ studioType === 'daterange' ? JSON.stringify(studioRange) : (studioType === 'datetime' ? studioDateTime : studioDate) }}
                        </code>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Tipe Pemilih (Unified Mode)</label>
                    <select v-model="studioType" class="form-select form-select-sm fw-bold text-brand-700">
                        <option value="date">type="date" (Tanggal Tunggal)</option>
                        <option value="daterange">type="daterange" (Rentang Tanggal)</option>
                        <option value="datetime">type="datetime" (Tanggal & Jam)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Label Field Pembungkus</label>
                    <input v-model="studioLabel" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="sm">Kecil (sm - 32px)</option>
                        <option value="md">Sedang (md - 38px)</option>
                        <option value="lg">Besar (lg - 44px)</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="ds-control-label">Fitur & Mode Interaktif</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="dp-pre" v-model="studioShowPresets" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dp-pre">Preset Cepat (Quick Chips)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="dp-clr" v-model="studioClearable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dp-clr">Tombol Bersihkan (Clearable)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="dp-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dp-dis">Nonaktif (Disabled)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="dp-ro" v-model="studioReadonly" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dp-ro">Hanya Baca (Readonly)</label>
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
         2. ENTERPRISE SCENARIOS & REAL-WORLD WORKFLOWS
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'variants'" class="ds-card-demo-wrap">
        <!-- Group 1: Unified Type Showcase -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Seluruh Kebutuhan Tanggal Cukup dengan Satu Komponen &lt;HcisDatePicker&gt;:</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisField label="Tanggal Mulai Tugas (Single)" hint="type='date' (default)" required>
                    <HcisDatePicker v-model="demoSingleDate" type="date" placeholder="YYYY-MM-DD" />
                </HcisField>

                <HcisField label="Periode Cuti Tahunan (Range)" hint="type='daterange'" required>
                    <HcisDatePicker v-model="demoRangeDates" type="daterange" />
                </HcisField>

                <HcisField label="Jadwal Wawancara HR (DateTime)" hint="type='datetime'" required>
                    <HcisDatePicker v-model="demoDateTime" type="datetime" />
                </HcisField>
            </div>
        </div>

        <!-- Group 2: Sizing Comparison -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Perbandingan Hierarki Ukuran DatePicker (sm / md / lg):</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisField label="Kecil (sm - 32px)">
                    <HcisDatePicker v-model="demoSizeSm" size="sm" />
                </HcisField>
                <HcisField label="Standar (md - 38px)">
                    <HcisDatePicker v-model="demoSizeMd" size="md" />
                </HcisField>
                <HcisField label="Besar (lg - 44px)">
                    <HcisDatePicker v-model="demoSizeLg" size="lg" />
                </HcisField>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Matriks Status Siklus Hidup DatePicker:</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisField label="Status Normal Terisi">
                    <HcisDatePicker :model-value="'2026-08-20'" />
                </HcisField>
                <HcisField label="Status Nonaktif (Disabled)">
                    <HcisDatePicker :model-value="'2026-08-20'" disabled />
                </HcisField>
                <HcisField label="Status Rentang Nonaktif">
                    <HcisDatePicker :model-value="['2026-08-20', '2026-08-25']" type="daterange" disabled />
                </HcisField>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisField, HcisDatePicker, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioType = ref('date');
const studioDate = ref('2026-08-20');
const studioRange = ref(['2026-08-20', '2026-08-27']);
const studioDateTime = ref('2026-08-20 09:30:00');

const studioLabel = ref('Tanggal Efektif Kontrak Kerja');
const studioHint = ref('Pilih tanggal atau rentang dengan popover kristal responsif');
const studioPlaceholder = ref('YYYY-MM-DD');
const studioSize = ref('md');
const studioShowPresets = ref(true);
const studioClearable = ref(true);
const studioRequired = ref(true);
const studioDisabled = ref(false);
const studioReadonly = ref(false);
const copied = ref(false);

// Scenarios State
const demoSingleDate = ref('2026-09-01');
const demoRangeDates = ref(['2026-09-10', '2026-09-15']);
const demoDateTime = ref('2026-09-01 14:00:00');
const demoSizeSm = ref('2026-08-20');
const demoSizeMd = ref('2026-08-20');
const demoSizeLg = ref('2026-08-20');

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    if (studioType.value === 'daterange') {
        props.push(`v-model="dateRange"`);
        props.push(`type="daterange"`);
    } else if (studioType.value === 'datetime') {
        props.push(`v-model="dateTime"`);
        props.push(`type="datetime"`);
    } else {
        props.push(`v-model="singleDate"`);
        if (studioPlaceholder.value && studioPlaceholder.value !== 'YYYY-MM-DD') {
            props.push(`placeholder="${studioPlaceholder.value}"`);
        }
    }

    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (!studioShowPresets.value) props.push(`:show-presets="false"`);
    if (!studioClearable.value) props.push(`:clearable="false"`);
    if (studioDisabled.value) props.push(`disabled`);
    if (studioReadonly.value) props.push(`readonly`);
    const propsStr = props.map((p) => `        ${p}`).join('\n');
    return `<HcisField label="${studioLabel.value}" hint="${studioHint.value}">\n    <HcisDatePicker\n${propsStr}\n    />\n</HcisField>`;
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
