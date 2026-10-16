<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisDivider Studio</h3>
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
                <div class="p-4 bg-white rounded-xl shadow-sm border border-slate-200 w-100" style="max-width: 580px;">
                    <p class="text-xs text-slate-500 mb-0">Bagian Atas: Ringkasan Status & Evaluasi Karyawan</p>
                    <HcisDivider
                        :label="studioLabel"
                        :icon="studioIcon"
                        :variant="studioVariant"
                        :align="studioAlign"
                        :spacing="studioSpacing"
                        :direction="studioDirection"
                    />
                    <p class="text-xs text-slate-500 mb-0">Bagian Bawah: Riwayat Otorisasi & Log Audit</p>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Label Pemisah</label>
                    <input v-model="studioLabel" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-2">
                    <label class="ds-control-label">Ikon</label>
                    <input v-model="studioIcon" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-2">
                    <label class="ds-control-label">Gaya Garis</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="solid">Solid</option>
                        <option value="dashed">Dashed</option>
                        <option value="dotted">Dotted</option>
                        <option value="gradient">Gradient 🔮</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="ds-control-label">Posisi Teks</label>
                    <select v-model="studioAlign" class="form-select form-select-sm">
                        <option value="center">Center</option>
                        <option value="left">Left</option>
                        <option value="right">Right</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Jarak Spasi (Spacing)</label>
                    <select v-model="studioSpacing" class="form-select form-select-sm">
                        <option value="xs">xs (0.25rem)</option>
                        <option value="sm">sm (0.75rem)</option>
                        <option value="md">md (1.25rem)</option>
                        <option value="lg">lg (2rem)</option>
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
            <span class="ds-group-label">1. Berbagai Varian Visual & Pemisah Vertikal Toolbar:</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm d-flex flex-column gap-2">
                <HcisDivider label="Solid Standard" />
                <HcisDivider label="Dashed Separator" variant="dashed" />
                <HcisDivider label="Dotted Line" variant="dotted" />
                <HcisDivider label="Gradient Brand" variant="gradient" icon="stars" />

                <div class="d-flex align-items-center justify-content-center p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 mt-2">
                    <span>Menu Utama</span>
                    <HcisDivider direction="vertical" />
                    <span>Ekspor Laporan</span>
                    <HcisDivider direction="vertical" variant="dashed" />
                    <span>Pengaturan Role</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Rata Kiri, Tengah, dan Kanan:</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 d-flex flex-column gap-2">
                <HcisDivider align="left" label="Rata Kiri" variant="dashed" />
                <HcisDivider align="center" label="Rata Tengah" variant="dashed" />
                <HcisDivider align="right" label="Rata Kanan" variant="dashed" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisDivider, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioLabel = ref('Riwayat Aktivitas & Perubahan');
const studioIcon = ref('clock-history');
const studioVariant = ref('gradient');
const studioAlign = ref('center');
const studioSpacing = ref('md');
const studioDirection = ref('horizontal');
const copied = ref(false);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    if (studioLabel.value) props.push(`label="${studioLabel.value}"`);
    if (studioIcon.value) props.push(`icon="${studioIcon.value}"`);
    if (studioVariant.value !== 'solid') props.push(`variant="${studioVariant.value}"`);
    if (studioAlign.value !== 'center') props.push(`align="${studioAlign.value}"`);
    if (studioSpacing.value !== 'md') props.push(`spacing="${studioSpacing.value}"`);
    if (studioDirection.value !== 'horizontal') props.push(`direction="${studioDirection.value}"`);

    return `<HcisDivider ${props.join(' ')} />`;
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
