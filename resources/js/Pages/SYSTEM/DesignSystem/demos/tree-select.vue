<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisTreeSelect Organization Studio</h3>
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
                <div class="p-4 bg-white rounded-xl shadow-sm border border-slate-200 w-100" style="max-width: 480px;">
                    <label class="form-label text-xs fw-bold text-slate-700 mb-1.5 d-flex align-items-center gap-1.5">
                        <HcisIcon name="diagram-2-fill" size="xs" class="text-indigo-600" />
                        <span>{{ studioLabel }}</span>
                    </label>
                    <HcisTreeSelect
                        v-model="studioValue"
                        :data="orgTree"
                        :placeholder="studioPlaceholder"
                        :filterable="studioFilterable"
                        :clearable="studioClearable"
                        :disabled="studioDisabled"
                        :loading="studioLoading"
                    />

                    <div class="mt-3 p-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs d-flex align-items-center justify-content-between">
                        <span class="text-slate-500">Node ID Terpilih:</span>
                        <strong class="text-indigo-700 font-mono">{{ studioValue || '(Kosong)' }}</strong>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Label Field</label>
                    <input v-model="studioLabel" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Placeholder</label>
                    <input v-model="studioPlaceholder" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Opsi State</label>
                    <div class="d-flex align-items-center gap-3 pt-2">
                        <div class="form-check form-switch m-0">
                            <input id="ts-flt" v-model="studioFilterable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ts-flt">Search Filter</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ts-clr" v-model="studioClearable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ts-clr">Clearable</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ts-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ts-dis">Disabled</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ts-load" v-model="studioLoading" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ts-load">Loading</label>
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
            <span class="ds-group-label">1. Skenario Struktur Kategori Aset & Inventaris Perusahaan:</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm" style="max-width: 500px;">
                <label class="form-label text-xs fw-bold text-slate-700 mb-1">Kategori Aset</label>
                <HcisTreeSelect v-model="selectedCategory" :data="categoryTree" placeholder="Pilih Kategori Aset..." clearable />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Signature Frosted Disabled & Loading States:</span>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-white rounded-xl border border-slate-200">
                        <label class="form-label text-xs fw-bold text-slate-500 mb-1">State: Disabled (Terkunci)</label>
                        <HcisTreeSelect model-value="sec_rec" :data="orgTree" disabled />
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-white rounded-xl border border-slate-200">
                        <label class="form-label text-xs fw-bold text-slate-700 mb-1">State: Loading (Shimmer Pulse)</label>
                        <HcisTreeSelect model-value="sec_dev" :data="orgTree" loading />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisTreeSelect, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioLabel = ref('Unit Organisasi Penempatan');
const studioPlaceholder = ref('Pilih Unit Organisasi...');
const studioValue = ref('sec_rec');
const studioFilterable = ref(true);
const studioClearable = ref(true);
const studioDisabled = ref(false);
const studioLoading = ref(false);
const copied = ref(false);

const selectedCategory = ref('cat_hardware');

const orgTree = [
    {
        value: 'dir_utama',
        label: 'Direktorat Utama',
        children: [
            {
                value: 'div_hc',
                label: 'Divisi Human Capital',
                children: [
                    { value: 'sec_rec', label: 'Seksi Rekrutmen & Asesmen' },
                    { value: 'sec_pay', label: 'Seksi Remunerasi & Gaji' },
                ],
            },
            {
                value: 'div_it',
                label: 'Divisi Teknologi Informasi',
                children: [
                    { value: 'sec_dev', label: 'Seksi Pengembangan Aplikasi' },
                    { value: 'sec_ops', label: 'Seksi Infrastruktur & Ops' },
                ],
            },
        ],
    },
];

const categoryTree = [
    {
        value: 'cat_it',
        label: 'Aset Teknologi',
        children: [
            { value: 'cat_hardware', label: 'Perangkat Keras (Laptop/PC)' },
            { value: 'cat_software', label: 'Lisensi Perangkat Lunak' },
        ],
    },
    {
        value: 'cat_facility',
        label: 'Fasilitas Kantor',
        children: [
            { value: 'cat_desk', label: 'Meja & Kursi Kerja' },
            { value: 'cat_vehicle', label: 'Kendaraan Operasional' },
        ],
    },
];

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="selectedNode"`);
    props.push(`:data="orgTree"`);
    if (studioPlaceholder.value) props.push(`placeholder="${studioPlaceholder.value}"`);
    if (!studioFilterable.value) props.push(`:filterable="false"`);
    if (studioClearable.value) props.push(`clearable`);
    if (studioDisabled.value) props.push(`disabled`);
    if (studioLoading.value) props.push(`loading`);

    return `<HcisTreeSelect\n    ${props.join('\n    ')}\n/>`;
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
