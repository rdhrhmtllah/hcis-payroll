<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisAvatarGroup Stack Configurator</h3>
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
                <div class="p-3 bg-white rounded-xl shadow-sm border border-slate-200" style="max-width: 520px;">
                    <HcisAvatarGroup
                        :users="sampleTeam"
                        :max="studioMax"
                        :size="studioSize"
                        :spacing="studioSpacing"
                        :shape="studioShape"
                        :interactive="studioInteractive"
                        @user-click="handleUserClick"
                        @overflow-click="handleOverflowClick"
                    />

                    <div v-if="lastEventLog" class="mt-3 p-2 bg-slate-900 text-slate-100 rounded-lg text-xs font-mono d-flex align-items-center">
                        <HcisIcon name="terminal-fill" size="2xs" class="text-indigo-400 me-2" />
                        <span><strong>Event:</strong> {{ lastEventLog }}</span>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Batas Tampil (Max)</label>
                    <select v-model.number="studioMax" class="form-select form-select-sm">
                        <option :value="3">Maks 3 Anggota</option>
                        <option :value="4">Maks 4 Anggota</option>
                        <option :value="5">Maks 5 Anggota</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="xs">xs (24px)</option>
                        <option value="sm">sm (32px)</option>
                        <option value="md">md (40px)</option>
                        <option value="lg">lg (48px)</option>
                        <option value="xl">xl (56px)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Jarak Tumpuk (Spacing)</label>
                    <select v-model="studioSpacing" class="form-select form-select-sm">
                        <option value="compact">Compact (Rapat)</option>
                        <option value="normal">Normal (Standar)</option>
                        <option value="loose">Loose (Renggang)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Bentuk (Shape)</label>
                    <select v-model="studioShape" class="form-select form-select-sm">
                        <option value="circle">Circle (Bulat)</option>
                        <option value="rounded">Rounded Squircle</option>
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
            <span class="ds-group-label">1. Skenario Tim Approver Departemen HC:</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                <HcisAvatarGroup :users="sampleTeam" :max="4" size="md" interactive />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Jarak Tumpukan (Spacing Scale):</span>
            <div class="d-flex flex-column gap-3 p-3 bg-white rounded-xl border border-slate-200">
                <div class="d-flex align-items-center gap-3">
                    <span class="text-xs font-semibold text-slate-500 w-24">Compact:</span>
                    <HcisAvatarGroup :users="sampleTeam" spacing="compact" />
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-xs font-semibold text-slate-500 w-24">Loose:</span>
                    <HcisAvatarGroup :users="sampleTeam" spacing="loose" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisAvatarGroup, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioMax = ref(4);
const studioSize = ref('md');
const studioSpacing = ref('normal');
const studioShape = ref('circle');
const studioInteractive = ref(true);
const lastEventLog = ref('');
const copied = ref(false);

const sampleTeam = [
    { id: 101, name: 'Budi Santoso', role: 'Approver Tier 1', status: 'online' },
    { id: 102, name: 'Siti Aminah', role: 'Approver Tier 2', status: 'busy' },
    { id: 103, name: 'Rahmat Hidayat', role: 'Head of HR', status: 'online' },
    { id: 104, name: 'Dewi Lestari', role: 'Payroll Manager', status: 'offline' },
    { id: 105, name: 'Eko Prasetyo', role: 'HR Generalist', status: 'online' },
    { id: 106, name: 'Fitriani Putri', role: 'Recruitment Lead', status: 'busy' },
];

function handleUserClick(payload) {
    lastEventLog.value = `Anggota diklik: ${payload.user.name} (${payload.user.role})`;
}

function handleOverflowClick(payload) {
    lastEventLog.value = `Overflow counter diklik (+${payload.count} anggota lainnya)`;
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`:users="teamMembers"`);
    if (studioMax.value !== 4) props.push(`:max="${studioMax.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioSpacing.value !== 'normal') props.push(`spacing="${studioSpacing.value}"`);
    if (studioShape.value !== 'circle') props.push(`shape="${studioShape.value}"`);
    if (studioInteractive.value) props.push(`interactive`);

    return `<HcisAvatarGroup\n    ${props.join('\n    ')}\n    @user-click="onMemberClick"\n/>`;
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
