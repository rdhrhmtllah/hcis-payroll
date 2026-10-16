<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisDateTimePicker Studio</h3>
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
                <div class="w-100" style="max-width: 380px;">
                    <HcisDateTimePicker
                        v-model="studioValue"
                        :show-presets="studioShowPresets"
                        :disabled="studioDisabled"
                        :loading="studioLoading"
                        :clearable="studioClearable"
                    />

                    <div class="mt-3 p-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs d-flex align-items-center justify-content-between">
                        <span class="text-slate-500">Nilai Terpilih:</span>
                        <strong class="text-indigo-700 font-mono">{{ studioValue || '(Belum dipilih)' }}</strong>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12">
                    <label class="ds-control-label">Opsi Mode & State</label>
                    <div class="d-flex flex-wrap align-items-center gap-3 pt-1">
                        <div class="form-check form-switch m-0">
                            <input id="dt-pre" v-model="studioShowPresets" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dt-pre">Quick Presets</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="dt-clr" v-model="studioClearable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dt-clr">Clearable</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="dt-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dt-dis">Disabled</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="dt-load" v-model="studioLoading" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dt-load">Loading</label>
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
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Skenario Tanpa Quick Preset Bar:</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm" style="max-width: 380px;">
                <HcisDateTimePicker v-model="studioValue" :show-presets="false" />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">State Disabled & Loading:</span>
            <div class="d-flex flex-column gap-3" style="max-width: 380px;">
                <HcisDateTimePicker model-value="2026-08-15 14:30" :disabled="true" />
                <HcisDateTimePicker model-value="2026-08-15 14:30" :loading="true" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisDateTimePicker, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioValue = ref('2026-08-15 14:30');
const studioShowPresets = ref(true);
const studioClearable = ref(true);
const studioDisabled = ref(false);
const studioLoading = ref(false);
const copied = ref(false);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="scheduledDateTime"`);
    if (!studioShowPresets.value) props.push(`:show-presets="false"`);
    if (!studioClearable.value) props.push(`:clearable="false"`);
    if (studioDisabled.value) props.push(`disabled`);
    if (studioLoading.value) props.push(`:loading="true"`);

    return `<HcisDateTimePicker\n    ${props.join('\n    ')}\n/>`;
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
