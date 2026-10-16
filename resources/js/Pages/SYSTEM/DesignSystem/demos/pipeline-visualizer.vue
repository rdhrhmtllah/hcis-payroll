<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisPipelineVisualizer Flow Engine Configurator</h3>
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
                <div style="width: 100%; max-width: 920px;">
                    <HcisPipelineVisualizer
                        :title="studioTitle"
                        :subtitle="studioSubtitle"
                        :icon="studioIcon"
                        :variant="studioVariant"
                        :direction="studioDirection"
                        :active-step="studioActiveStep"
                        :allow-zoom="studioAllowZoom"
                        :allow-add="studioAllowAdd"
                        :steps="demoSteps"
                        @node-click="handleNodeClick"
                        @node-action="handleNodeAction"
                        @add-node="handleAddNode"
                    />

                    <div v-if="lastEventLog" class="mt-3 p-2 bg-slate-900 text-slate-100 rounded-lg text-xs font-mono d-flex align-items-center">
                        <HcisIcon name="terminal-fill" size="2xs" class="text-indigo-400 me-2" />
                        <span><strong>Event:</strong> {{ lastEventLog }}</span>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Varian Visual (Variant)</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="card">Card (Solid Surface)</option>
                        <option value="glass">Glass (Frosted Crystal)</option>
                        <option value="flat">Flat (Minimalist)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Arah Aliran (Direction)</label>
                    <select v-model="studioDirection" class="form-select form-select-sm">
                        <option value="horizontal">Horizontal (Mendatar)</option>
                        <option value="vertical">Vertical (Tegak)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Simulasi Langkah Aktif (Active Step)</label>
                    <select v-model="studioActiveStep" class="form-select form-select-sm">
                        <option value="1">Step 1: Pengajuan</option>
                        <option value="2">Step 2: Review SPV</option>
                        <option value="3">Step 3: Approval HRD</option>
                        <option value="4">Step 4: Payroll Selesai</option>
                    </select>
                </div>

                <div class="col-12">
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="pip-zoom" v-model="studioAllowZoom" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="pip-zoom">Kontrol Zoom & Pan Canvas</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="pip-add" v-model="studioAllowAdd" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="pip-add">Tombol Tambah Node (Allow Add)</label>
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
            <span class="ds-group-label">1. Skenario Otorisasi Berantai Multi-Hirarki:</span>
            <div class="d-flex flex-column gap-3">
                <HcisPipelineVisualizer
                    title="Otorisasi Permohonan Anggaran & Mutasi"
                    subtitle="Jenjang otorisasi berantai sesuai struktur jabatan perusahaan."
                    icon="diagram-2"
                    variant="card"
                    active-step="2"
                    :steps="orgHierarchySteps"
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Alur Bercabang Fleksibel:</span>
            <HcisPipelineVisualizer
                variant="glass"
                title="Pemeriksaan Medis & Klaim Asuransi"
                active-step="1"
                :steps="demoSteps"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisPipelineVisualizer, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioTitle = ref('Alur Persetujuan Pengajuan Cuti Karyawan');
const studioSubtitle = ref('Jalur aktif diterangi pendaran sinar mengalir.');
const studioIcon = ref('diagram-3');
const studioVariant = ref('card');
const studioDirection = ref('horizontal');
const studioActiveStep = ref('2');
const studioAllowZoom = ref(true);
const studioAllowAdd = ref(true);
const lastEventLog = ref('');
const copied = ref(false);

const demoSteps = ref([
    {
        id: '1',
        title: 'Pengajuan Form Cuti',
        name: 'Rian Hidayat',
        role: 'Requester Staff',
        status: 'done',
        icon: 'file-earmark-text',
        badge: 'Tahap 1',
    },
    {
        id: '2',
        title: 'Review Supervisor',
        name: 'Ahmad Fauzi',
        role: 'Team Lead',
        status: 'active',
        icon: 'person-check',
        badge: 'Tahap 2',
    },
    {
        id: '3',
        title: 'Pengesahan HRD',
        name: 'Clarissa Maharani',
        role: 'HR Manager',
        status: 'pending',
        icon: 'shield-check',
        badge: 'Tahap 3',
    },
    {
        id: '4',
        title: 'Pencatatan Payroll',
        name: 'Sistem Otomatis',
        role: 'HRIS Bot',
        status: 'pending',
        icon: 'calendar-check',
        badge: 'Selesai',
    },
]);

const orgHierarchySteps = ref([
    { id: '1', title: 'Staff Pemohon', role: 'Staff Grade 3', status: 'done', icon: 'person' },
    { id: '2', title: 'Section Head', role: 'SPV Grade 5', status: 'active', icon: 'person-badge' },
    { id: '3', title: 'Department Head', role: 'Manager Grade 7', status: 'pending', icon: 'briefcase' },
    { id: '4', title: 'Direktur Terkait', role: 'Board of Directors', status: 'pending', icon: 'building' },
]);

function handleNodeClick(payload) {
    lastEventLog.value = `Node diklik: ${payload.node.title}`;
}

function handleNodeAction(payload) {
    lastEventLog.value = `Aksi node: ${payload.action.label} pada ${payload.node.title}`;
}

function handleAddNode(payload) {
    lastEventLog.value = `Tambah node baru urutan: #${payload.order}`;
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`title="${studioTitle.value}"`);
    if (studioVariant.value !== 'card') props.push(`variant="${studioVariant.value}"`);
    if (studioDirection.value !== 'horizontal') props.push(`direction="${studioDirection.value}"`);
    props.push(`:active-step="${studioActiveStep.value}"`);
    if (studioAllowZoom.value) props.push(`allow-zoom`);
    if (studioAllowAdd.value) props.push(`allow-add`);
    props.push(`:steps="pipelineSteps"`);

    return `<HcisPipelineVisualizer\n    ${props.join('\n    ')}\n    @node-click="onNodeClick"\n/>`;
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
