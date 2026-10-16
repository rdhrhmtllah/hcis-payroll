<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisSteps Workflow Configurator</h3>
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
                <div class="p-4 bg-white rounded-xl shadow-sm border border-slate-200" style="width: 100%; max-width: 820px;">
                    <HcisSteps
                        v-model:current="currentStep"
                        :steps="wizardSteps"
                        :direction="studioDirection"
                        :variant="studioVariant"
                        :size="studioSize"
                        :clickable="studioClickable"
                    />

                    <!-- Wizard Navigation Actions -->
                    <div class="d-flex justify-content-between align-items-center pt-3 mt-4 border-top border-slate-200">
                        <HcisButton
                            label="Tahap Sebelumnya"
                            variant="secondary"
                            size="sm"
                            leading-icon="arrow-left"
                            :disabled="currentStep <= 0"
                            @click="currentStep--"
                        />
                        <span class="text-xs text-slate-500 font-monospace fw-bold">
                            Tahap {{ currentStep + 1 }} dari {{ wizardSteps.length }}
                        </span>
                        <HcisButton
                            :label="currentStep >= wizardSteps.length - 1 ? 'Selesaikan Alur' : 'Tahap Berikutnya'"
                            variant="primary"
                            size="sm"
                            :trailing-icon="currentStep >= wizardSteps.length - 1 ? 'check-lg' : 'arrow-right'"
                            :disabled="currentStep >= wizardSteps.length - 1"
                            @click="currentStep++"
                        />
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Arah Alur (Direction)</label>
                    <select v-model="studioDirection" class="form-select form-select-sm">
                        <option value="horizontal">Horizontal (Mendatar)</option>
                        <option value="vertical">Vertical (Tegak Timeline)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Varian Visual (Variant)</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="cards">Cards (Executive Workflow Cards)</option>
                        <option value="default">Default (Jewel Line & Numbers)</option>
                        <option value="pills">Pills (Capsule Breadcrumb)</option>
                        <option value="dots">Dots (Minimalist Nodes)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="sm">Kecil (sm)</option>
                        <option value="md">Standar (md)</option>
                        <option value="lg">Besar (lg)</option>
                    </select>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch m-0 pt-1">
                        <input id="st-clk" v-model="studioClickable" class="form-check-input" type="checkbox" />
                        <label class="form-check-label text-xs fw-semibold" for="st-clk">Interaktif / Dapat Diklik (Clickable Navigation)</label>
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
        <!-- Showcase 1: Executive Workflow Cards -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Skenario Alur Pengajuan Cuti & Izin (Cards Variant):</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                <HcisSteps
                    :current="1"
                    variant="cards"
                    :steps="approvalWorkflow"
                    clickable
                />
            </div>
        </div>

        <!-- Showcase 2: Vertical Audit Trail Timeline -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Skenario Audit Trail Penilaian Kinerja (Vertical Timeline):</span>
            <div class="p-4 bg-white rounded-xl border border-slate-200 shadow-sm" style="max-width: 540px;">
                <HcisSteps
                    :current="2"
                    direction="vertical"
                    variant="default"
                    :steps="auditTrailSteps"
                />
            </div>
        </div>

        <!-- Showcase 3: Capsule Breadcrumb Pipeline -->
        <div class="ds-demo-group">
            <span class="ds-group-label">3. Skenario Pipeline Rekrutmen (Pills Breadcrumb):</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                <HcisSteps
                    :current="2"
                    variant="pills"
                    :steps="recruitmentSteps"
                    clickable
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Status Penolakan / Error (Rejected State):</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                <HcisSteps
                    :current="1"
                    :steps="rejectedWorkflow"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisSteps, HcisButton, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const currentStep = ref(1);
const studioDirection = ref('horizontal');
const studioVariant = ref('cards');
const studioSize = ref('md');
const studioClickable = ref(true);
const copied = ref(false);

const wizardSteps = ref([
    {
        title: 'Draft Pengajuan',
        subtitle: 'Pengisian formulir',
        description: 'Input rincian permohonan dinas luar kota.',
        icon: 'file-earmark-text',
        time: '09:00 WIB',
        actor: 'Budi (Pemohon)'
    },
    {
        title: 'Verifikasi Manajer',
        subtitle: 'Pemeriksaan SLA & budget',
        description: 'Verifikasi anggaran perjalanan dan izin atasan.',
        icon: 'shield-check',
        time: '10:30 WIB',
        actor: 'Dewi (Manager)'
    },
    {
        title: 'Persetujuan Direksi',
        subtitle: 'Tinjauan kebijakan',
        description: 'Otorisasi akhir oleh Kepala Divisi SDM.',
        icon: 'award',
        actor: 'Direktur SDM'
    },
    {
        title: 'Penerbitan Surat Tugas',
        subtitle: 'Sinkronisasi payroll',
        description: 'Sistem mencetak nomor surat dan memo resmi.',
        icon: 'check2-circle'
    }
]);

const approvalWorkflow = ref([
    {
        title: 'Pengajuan Cuti',
        subtitle: '3 Hari Kerja',
        icon: 'calendar-check',
        time: 'Kemarin, 14:00',
        actor: 'Rian Pratama'
    },
    {
        title: 'Review Atasan',
        subtitle: 'Menunggu approval',
        icon: 'person-check',
        actor: 'Siti Rahma'
    },
    {
        title: 'Verifikasi HRD',
        subtitle: 'Potong kuota cuti',
        icon: 'building-check'
    }
]);

const auditTrailSteps = ref([
    {
        title: 'Form KPI Dibuat',
        caption: 'Target semester genap telah diunggah oleh karyawan.',
        time: '10 Agustus 2026, 08:30 WIB',
        icon: 'file-text'
    },
    {
        title: 'Evaluasi Atasan Langsung Selesai',
        caption: 'Skor capaian 92/100 telah divalidasi oleh Section Head.',
        time: '14 Agustus 2026, 11:15 WIB',
        icon: 'check-circle'
    },
    {
        title: 'Moderasi Komite Talenta',
        caption: 'Sedang dalam peninjauan kuadran 9-box talent matrix.',
        time: 'Hari ini, 09:00 WIB',
        icon: 'people'
    },
    {
        title: 'Finalisasi Nilai & Raport',
        caption: 'Penerbitan hasil evaluasi kinerja tahunan.',
        icon: 'award'
    }
]);

const recruitmentSteps = ref([
    { title: 'Seleksi Berkas', icon: 'file-earmark' },
    { title: 'Psikotes & Teknis', icon: 'clipboard-check' },
    { title: 'Wawancara User', icon: 'chat-dots' },
    { title: 'Offering & Kontrak', icon: 'pen' }
]);

const rejectedWorkflow = ref([
    { title: 'Pengajuan Lembur', caption: 'Diajukan 2 jam lembur', state: 'done' },
    { title: 'Approval Supervisor', caption: 'Ditolak: Melebihi kuota bulanan', state: 'rejected' },
    { title: 'Pencairan Insentif', caption: 'Dibatalkan', state: 'todo' }
]);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`:current="currentStep"`);
    props.push(`:steps="steps"`);
    if (studioDirection.value !== 'horizontal') props.push(`direction="${studioDirection.value}"`);
    if (studioVariant.value !== 'default') props.push(`variant="${studioVariant.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioClickable.value) props.push(`clickable`);

    return `<HcisSteps\n    ${props.join('\n    ')}\n    @change="handleStepChange"\n/>`;
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
