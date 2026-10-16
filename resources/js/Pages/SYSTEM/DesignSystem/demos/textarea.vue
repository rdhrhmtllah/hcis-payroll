<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisTextarea Multi-line Field Studio</h3>
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
                <div class="w-100" style="max-width: 520px;">
                    <HcisTextarea
                        id="studio-ta-input"
                        v-model="studioValue"
                        :label="studioLabel"
                        :placeholder="studioPlaceholder"
                        :hint="studioHint"
                        :error="studioError"
                        :success="studioSuccess"
                        :rows="studioRows"
                        :maxlength="studioMaxlength"
                        :required="studioRequired"
                        :disabled="studioDisabled"
                        :clearable="studioClearable"
                        :auto-resize="studioAutoResize"
                        :resize="studioResize"
                    />
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Label</label>
                    <input v-model="studioLabel" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Placeholder</label>
                    <input v-model="studioPlaceholder" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Petunjuk (Hint)</label>
                    <input v-model="studioHint" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Batas Karakter (Maxlength)</label>
                    <input v-model.number="studioMaxlength" type="number" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Baris Default (Rows)</label>
                    <input v-model.number="studioRows" type="number" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Gaya Resize</label>
                    <select v-model="studioResize" class="form-select form-select-sm">
                        <option value="vertical">vertical</option>
                        <option value="none">none</option>
                        <option value="horizontal">horizontal</option>
                        <option value="both">both</option>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="ds-control-label">Saklar Fitur & Validasi</label>
                    <div class="d-flex flex-wrap align-items-center gap-3 pt-1">
                        <div class="form-check form-switch m-0">
                            <input id="ta-req" v-model="studioRequired" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ta-req">Wajib</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ta-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ta-dis">Disabled</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ta-clr" v-model="studioClearable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ta-clr">Clearable</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ta-auto" v-model="studioAutoResize" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ta-auto">Auto-Resize</label>
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
            <span class="ds-group-label">1. Skenario Auto-Resize & Clearable:</span>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <HcisTextarea
                        id="ta-sc-1"
                        v-model="panjang"
                        label="Auto Resize Otomatis"
                        placeholder="Tulis beberapa paragraf..."
                        auto-resize
                        :rows="3"
                    />
                </div>
                <div class="col-12 col-md-6">
                    <HcisTextarea
                        id="ta-sc-2"
                        v-model="alasan"
                        label="Dapat Dibersihkan (Clearable)"
                        placeholder="Ketik alasan pengajuan..."
                        clearable
                        hint="Klik ikon 'x' di kanan atas untuk menghapus isi teks."
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">State Validasi & Siklus Disabled:</span>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <HcisTextarea id="ta-st-1" v-model="alasan" label="Validasi Sukses" success="Format catatan evaluasi memenuhi standar." />
                </div>
                <div class="col-12 col-md-6">
                    <HcisTextarea id="ta-st-2" v-model="pendek" label="Galat Validasi" error="Alasan pengajuan wajib diisi minimal 20 karakter." required />
                </div>
                <div class="col-12 col-md-6">
                    <HcisTextarea id="ta-st-3" v-model="hampirHabis" label="Batas Karakter Mendekati Maksimal" :maxlength="50" hint="Angka counter berubah oranye/merah saat batas didekati." />
                </div>
                <div class="col-12 col-md-6">
                    <HcisTextarea id="ta-st-4" v-model="terkunci" label="Disabled (Frosted Blur)" disabled hint="Terkunci setelah pengajuan disetujui atasan." />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisTextarea, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioValue = ref('Pengajuan cuti tahunan untuk keperluan keluarga.');
const studioLabel = ref('Alasan Pengajuan Cuti');
const studioPlaceholder = ref('Jelaskan rincian keperluan pengajuan Anda...');
const studioHint = ref('Akan ditinjau oleh manajer departemen dan HRD.');
const studioError = ref('');
const studioSuccess = ref('');
const studioMaxlength = ref(250);
const studioRows = ref(4);
const studioRequired = ref(true);
const studioDisabled = ref(false);
const studioClearable = ref(true);
const studioAutoResize = ref(false);
const studioResize = ref('vertical');
const copied = ref(false);

const alasan = ref('Keperluan keluarga di luar kota.');
const pendek = ref('Singkat');
const panjang = ref('Ini adalah paragraf panjang.\nKetika menambah baris baru, tinggi textarea langsung menyesuaikan otomatis secara mulus tanpa scrollbar!');
const hampirHabis = ref('Teks ini sudah sangat panjang mendekati batas 50');
const terkunci = ref('Sudah disetujui pada 12 Agustus 2026.');

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`id="alasan-pengajuan"`);
    props.push(`v-model="catatan"`);
    props.push(`label="${studioLabel.value}"`);
    if (studioPlaceholder.value) props.push(`placeholder="${studioPlaceholder.value}"`);
    if (studioHint.value) props.push(`hint="${studioHint.value}"`);
    if (studioMaxlength.value) props.push(`:maxlength="${studioMaxlength.value}"`);
    if (studioRows.value !== 4) props.push(`:rows="${studioRows.value}"`);
    if (studioRequired.value) props.push(`required`);
    if (studioDisabled.value) props.push(`disabled`);
    if (studioClearable.value) props.push(`clearable`);
    if (studioAutoResize.value) props.push(`auto-resize`);
    if (studioResize.value !== 'vertical') props.push(`resize="${studioResize.value}"`);

    return `<HcisTextarea\n    ${props.join('\n    ')}\n/>`;
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
