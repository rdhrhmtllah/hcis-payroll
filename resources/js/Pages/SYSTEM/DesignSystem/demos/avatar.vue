<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisAvatar Monogram & Presence Configurator</h3>
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
                <div class="d-flex align-items-center gap-3 p-3 bg-white rounded-xl shadow-sm border border-slate-200" style="max-width: 480px;">
                    <HcisAvatar
                        :name="studioName"
                        :src="studioSrc"
                        :size="studioSize"
                        :shape="studioShape"
                        :status="studioStatus"
                        :ring="studioRing"
                        :interactive="studioInteractive"
                    />
                    <div>
                        <strong class="d-block text-sm text-slate-800 font-bold">{{ studioName }}</strong>
                        <span class="d-block text-xs text-slate-500 font-medium">Head of Technology · Online Presence</span>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Nama Pegawai (Name)</label>
                    <input v-model="studioName" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="xs">Extra Small (xs - 24px)</option>
                        <option value="sm">Small (sm - 32px)</option>
                        <option value="md">Medium (md - 40px)</option>
                        <option value="lg">Large (lg - 48px)</option>
                        <option value="xl">Extra Large (xl - 56px)</option>
                        <option value="2xl">2X Large (2xl - 64px)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Bentuk (Shape)</label>
                    <select v-model="studioShape" class="form-select form-select-sm">
                        <option value="circle">Circle (Bulat Penuh)</option>
                        <option value="rounded">Rounded Squircle (Sudut Membulat)</option>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Status Presensi (Presence)</label>
                    <select v-model="studioStatus" class="form-select form-select-sm">
                        <option value="">Tanpa Status</option>
                        <option value="online">Online (Hijau Pulse Halo)</option>
                        <option value="away">Away (Kuning Siaga)</option>
                        <option value="busy">Busy (Merah DND)</option>
                        <option value="offline">Offline (Abu-abu)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Cincin Bersinar (Ring)</label>
                    <select v-model="studioRing" class="form-select form-select-sm">
                        <option value="">Tanpa Ring</option>
                        <option value="gradient">Gradient Glow (VIP / Direksi)</option>
                        <option value="brand">Brand Indigo Ring</option>
                        <option value="success">Success Emerald Ring</option>
                        <option value="warning">Warning Amber Ring</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Interaktivitas</label>
                    <div class="form-check form-switch pt-2">
                        <input id="av-inter" v-model="studioInteractive" class="form-check-input" type="checkbox" />
                        <label class="form-check-label text-xs fw-semibold" for="av-inter">Hover Lift & Click Scale</label>
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
            <span class="ds-group-label">1. Skenario Spektrum Ukuran & Monogram Otomatis:</span>
            <div class="d-flex flex-wrap align-items-center gap-3 p-3 bg-white rounded-xl border border-slate-200">
                <HcisAvatar name="Ahmad Fauzi" size="xs" />
                <HcisAvatar name="Budi Santoso" size="sm" />
                <HcisAvatar name="Citra Dewi" size="md" />
                <HcisAvatar name="Dewi Sartika" size="lg" ring="gradient" status="online" />
                <HcisAvatar name="Eko Purnomo" size="xl" status="busy" />
                <HcisAvatar name="Fahmi Reza" size="2xl" ring="brand" />
            </div>
        </div>

        <div class="ds-demo-group">
            <span class="ds-group-label">2. Skenario Tumpukan Tim Proyek & Approver Stack:</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                <HcisAvatar :users="timUsers" :max="4" size="md" interactive />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Status Presensi Real-Time Pegawai:</span>
            <div class="d-flex flex-wrap gap-4 p-3 bg-white rounded-xl border border-slate-200">
                <div class="d-flex align-items-center gap-2">
                    <HcisAvatar name="Ahmad Fauzi" size="md" status="online" />
                    <span class="text-xs font-semibold text-slate-700">Online Active</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <HcisAvatar name="Budi Santoso" size="md" status="away" />
                    <span class="text-xs font-semibold text-slate-700">Away / Lunch</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <HcisAvatar name="Citra Dewi" size="md" status="busy" />
                    <span class="text-xs font-semibold text-slate-700">In Meeting (Busy)</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <HcisAvatar name="Dewi Sartika" size="md" status="offline" />
                    <span class="text-xs font-semibold text-slate-700">Offline (Cuti)</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisAvatar, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioName = ref('Ahmad Fauzi');
const studioSrc = ref('');
const studioSize = ref('lg');
const studioShape = ref('circle');
const studioStatus = ref('online');
const studioRing = ref('gradient');
const studioInteractive = ref(true);
const copied = ref(false);

const timUsers = ref([
    { name: 'Ahmad Fauzi', role: 'Manager HCIS', status: 'online' },
    { name: 'Budi Santoso', role: 'Direktur HC', status: 'online' },
    { name: 'Citra Dewi', role: 'HR Specialist', status: 'busy' },
    { name: 'Dedi Kurniawan', role: 'Software Engineer', status: 'away' },
    { name: 'Eka Putri', role: 'Recruiter', status: 'offline' },
    { name: 'Fahmi Reza', role: 'UI/UX Designer', status: 'online' },
]);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`name="${studioName.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioShape.value !== 'circle') props.push(`shape="${studioShape.value}"`);
    if (studioStatus.value) props.push(`status="${studioStatus.value}"`);
    if (studioRing.value) props.push(`ring="${studioRing.value}"`);
    if (studioInteractive.value) props.push(`interactive`);

    return `<HcisAvatar\n    ${props.join('\n    ')}\n/>`;
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
