<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisTimePicker Configurator</h3>
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
                <div style="width: 100%; max-width: 380px;">
                    <HcisField
                        id="studio-time-field"
                        :label="studioLabel"
                        :hint="studioHint"
                        :required="studioRequired"
                    >
                        <HcisTimePicker
                            v-model="studioTime"
                            :placeholder="studioPlaceholder"
                            :size="studioSize"
                            :show-presets="studioShowPresets"
                            :use-seconds="studioUseSeconds"
                            :clearable="studioClearable"
                            :disabled="studioDisabled"
                            :readonly="studioReadonly"
                        />
                    </HcisField>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Label Field Pembungkus</label>
                    <input v-model="studioLabel" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Placeholder Format</label>
                    <input v-model="studioPlaceholder" type="text" class="form-control form-control-sm" />
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
                            <input id="tp-pre" v-model="studioShowPresets" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tp-pre">Preset Jam Cepat</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="tp-sec" v-model="studioUseSeconds" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tp-sec">Gunakan Kolom Detik (:ss)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="tp-clr" v-model="studioClearable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tp-clr">Tombol Hapus (Clearable)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="tp-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tp-dis">Nonaktif (Disabled)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="tp-ro" v-model="studioReadonly" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tp-ro">Hanya Baca (Readonly)</label>
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
        <!-- Group 1: Shift Jam Kerja Operasional -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Konfigurasi Jam Giliran Shift Kerja Pabrik:</span>
            <div class="hcis-grid hcis-grid--2">
                <HcisField label="Jadwal Shift 1 (Pagi)" hint="Masuk kerja standar pagi" required>
                    <HcisTimePicker
                        v-model="demoShift1"
                        :presets="[
                            { label: '07:00 (Pagi Masuk)', value: '07:00' },
                            { label: '15:00 (Pagi Selesai)', value: '15:00' }
                        ]"
                    />
                </HcisField>

                <HcisField label="Jadwal Shift 2 (Siang - Malam)" hint="Giliran kerja operasional produksi" required>
                    <HcisTimePicker
                        v-model="demoShift2"
                        :presets="[
                            { label: '15:00 (Siang Masuk)', value: '15:00' },
                            { label: '23:00 (Malam Selesai)', value: '23:00' }
                        ]"
                    />
                </HcisField>
            </div>
        </div>

        <!-- Group 2: Sizing Comparison -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Perbandingan Hierarki Ukuran TimePicker (sm / md / lg):</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisField label="Kecil (sm - 32px)">
                    <HcisTimePicker v-model="demoSizeSm" size="sm" />
                </HcisField>
                <HcisField label="Standar (md - 38px)">
                    <HcisTimePicker v-model="demoSizeMd" size="md" />
                </HcisField>
                <HcisField label="Besar (lg - 44px)">
                    <HcisTimePicker v-model="demoSizeLg" size="lg" />
                </HcisField>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Matriks Status Siklus Hidup TimePicker:</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisField label="Status Normal Terisi">
                    <HcisTimePicker :model-value="'08:30'" />
                </HcisField>
                <HcisField label="Status Nonaktif (Disabled)">
                    <HcisTimePicker :model-value="'08:30'" disabled />
                </HcisField>
                <HcisField label="Status Presisi Detik">
                    <HcisTimePicker :model-value="'08:30:45'" use-seconds />
                </HcisField>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisField, HcisTimePicker, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioTime = ref('08:00');
const studioLabel = ref('Jam Masuk Kerja Karyawan');
const studioHint = ref('Pilih jam dari panel spinner atau ketik langsung format HH:mm');
const studioPlaceholder = ref('HH:mm');
const studioSize = ref('md');
const studioShowPresets = ref(true);
const studioUseSeconds = ref(false);
const studioClearable = ref(true);
const studioRequired = ref(true);
const studioDisabled = ref(false);
const studioReadonly = ref(false);
const copied = ref(false);

// Scenarios State
const demoShift1 = ref('07:00');
const demoShift2 = ref('15:00');
const demoSizeSm = ref('08:00');
const demoSizeMd = ref('08:00');
const demoSizeLg = ref('08:00');

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="selectedTime"`);
    if (studioPlaceholder.value !== 'HH:mm') props.push(`placeholder="${studioPlaceholder.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (!studioShowPresets.value) props.push(`:show-presets="false"`);
    if (studioUseSeconds.value) props.push(`use-seconds`);
    if (!studioClearable.value) props.push(`:clearable="false"`);
    if (studioDisabled.value) props.push(`disabled`);
    if (studioReadonly.value) props.push(`readonly`);

    return `<HcisField label="${studioLabel.value}" hint="${studioHint.value}">\n    <HcisTimePicker\n        ${props.join('\n        ')}\n    />\n</HcisField>`;
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
