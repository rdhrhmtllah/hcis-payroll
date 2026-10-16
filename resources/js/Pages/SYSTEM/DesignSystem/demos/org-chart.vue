<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisOrgChart Tree Engine Configurator</h3>
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
                <div style="width: 100%; max-width: 960px;">
                    <HcisOrgChart
                        :title="studioTitle"
                        :subtitle="studioSubtitle"
                        :icon="studioIcon"
                        :variant="studioVariant"
                        :allow-zoom="studioAllowZoom"
                        :data="sampleOrgTree"
                        @node-click="handleNodeClick"
                        @node-action="handleNodeAction"
                    />

                    <div v-if="lastEventLog" class="mt-3 p-2 bg-slate-900 text-slate-100 rounded-lg text-xs font-mono d-flex align-items-center">
                        <HcisIcon name="terminal-fill" size="2xs" class="text-indigo-400 me-2" />
                        <span><strong>Event:</strong> {{ lastEventLog }}</span>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="ds-control-label">Varian Visual (Variant)</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="card">Card (Surface Solid)</option>
                        <option value="glass">Glass (Frosted Translucent)</option>
                        <option value="flat">Flat (Minimalist)</option>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="ds-control-label">Interaktivitas Zoom & Pan</label>
                    <div class="form-check form-switch pt-2">
                        <input id="org-zm" v-model="studioAllowZoom" class="form-check-input" type="checkbox" />
                        <label class="form-check-label text-xs fw-semibold" for="org-zm">Aktifkan Kontrol Zoom & Pan</label>
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
            <span class="ds-group-label">1. Skenario Pohon Struktur Direktorat Keuangan:</span>
            <div class="d-flex flex-column gap-3">
                <HcisOrgChart
                    variant="glass"
                    title="Struktur Direktorat Keuangan & Akuntansi"
                    :data="financeTree"
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Struktur Sub-Unit IT & Digital Systems:</span>
            <HcisOrgChart
                variant="flat"
                title="Struktur Divisi IT & Digital Systems"
                :data="itSubTree"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisOrgChart, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioTitle = ref('Struktur Organisasi PT HCIS Nusantara');
const studioSubtitle = ref('Pohon struktur jabatan bertingkat dari Direksi hingga Team Leader');
const studioIcon = ref('diagram-2');
const studioVariant = ref('card');
const studioAllowZoom = ref(true);
const lastEventLog = ref('');
const copied = ref(false);

const sampleOrgTree = ref({
    id: 'ceo',
    name: 'Dr. Ir. Hendra Wijaya, M.M.',
    role: 'Chief Executive Officer (CEO)',
    department: 'Direksi Utama',
    avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80',
    actions: [
        { key: 'view-profile', label: 'Profil Eksekutif', variant: 'primary' }
    ],
    children: [
        {
            id: 'cfo',
            name: 'Dewi Lestari, S.E., Ak.',
            role: 'Chief Financial Officer (CFO)',
            department: 'Direktorat Keuangan',
            avatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=100&auto=format&fit=crop&q=80',
            children: [
                {
                    id: 'fin-mgr',
                    name: 'Budi Santoso',
                    role: 'Finance & Tax Manager',
                    department: 'Departemen Keuangan',
                },
                {
                    id: 'act-mgr',
                    name: 'Siti Aminah',
                    role: 'Accounting & Payroll Manager',
                    department: 'Departemen Akuntansi',
                }
            ]
        },
        {
            id: 'cto',
            name: 'Ahmad Fauzi, S.Kom., M.T.',
            role: 'Chief Technology Officer (CTO)',
            department: 'Direktorat Teknologi',
            avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&auto=format&fit=crop&q=80',
            children: [
                {
                    id: 'eng-lead',
                    name: 'Rian Hidayat',
                    role: 'Engineering Lead',
                    department: 'Software Engineering',
                },
                {
                    id: 'infra-lead',
                    name: 'Bayu Pratama',
                    role: 'DevOps & Infra Lead',
                    department: 'Cloud Infrastructure',
                }
            ]
        }
    ]
});

const financeTree = ref({
    id: 'fin-dir',
    name: 'Dewi Lestari',
    role: 'Finance Director',
    department: 'Keuangan',
    children: [
        { id: 'tax-1', name: 'Rudy Hartono', role: 'Tax Supervisor', department: 'Pajak' },
        { id: 'tre-1', name: 'Nadia Putri', role: 'Treasury Lead', department: 'Kas & Bank' },
    ]
});

const itSubTree = ref({
    id: 'it-lead',
    name: 'Ahmad Fauzi',
    role: 'IT Lead',
    department: 'Technology',
    children: [
        { id: 'dev-1', name: 'Rian Hidayat', role: 'Backend Lead', department: 'Core Service' },
        { id: 'dev-2', name: 'Clara Bella', role: 'Frontend Lead', department: 'UI Platform' },
    ]
});

function handleNodeClick(payload) {
    lastEventLog.value = `Node organisasi diklik: ${payload.node.name} (${payload.node.role || payload.node.title || ''})`;
}

function handleNodeAction(payload) {
    lastEventLog.value = `Aksi ${payload.action.label} pada ${payload.node.name}`;
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`title="${studioTitle.value}"`);
    if (studioVariant.value !== 'card') props.push(`variant="${studioVariant.value}"`);
    if (studioAllowZoom.value) props.push(`allow-zoom`);
    props.push(`:data="orgTreeData"`);

    return `<HcisOrgChart\n    ${props.join('\n    ')}\n    @node-click="onNodeClick"\n/>`;
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

.ds-org-demo,
.ds-stack-demo {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    width: 100%;
}

.ds-card {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgb(var(--hcis-slate-900-rgb) / 0.04);
}

.ds-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-100));
}

.ds-card__title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ds-card__badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 9999px;
    background: var(--hcis-brand-50, var(--hcis-brand-50));
    color: var(--hcis-brand-700, var(--hcis-brand-700));
}

.ds-card__tag {
    font-size: 0.7rem;
    font-weight: 600;
    font-family: monospace;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.ds-card__body {
    padding: 1.25rem;
}

.ds-demo-label {
    font-size: 0.775rem;
    font-weight: 700;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    margin-bottom: 0.5rem;
    display: block;
}

.ds-event-log {
    padding: 0.625rem 0.875rem;
    border-radius: 8px;
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    font-size: 0.8rem;
    color: var(--hcis-text, var(--hcis-slate-700));
}
</style>
