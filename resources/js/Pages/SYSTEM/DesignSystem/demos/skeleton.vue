<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisSkeleton Studio</h3>
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
                <div class="p-4 bg-white rounded-xl shadow-sm border border-slate-200 w-100" style="max-width: 500px;">
                    <template v-if="studioType === 'card'">
                        <HcisSkeleton type="card" :animation="studioAnimation" />
                    </template>
                    <template v-else-if="studioType === 'avatar-text'">
                        <div class="d-flex align-items-center gap-3">
                            <HcisSkeleton type="avatar" width="3.25rem" height="3.25rem" :animation="studioAnimation" />
                            <div class="flex-grow-1">
                                <HcisSkeleton width="65%" height="1.1rem" class="mb-2" :animation="studioAnimation" />
                                <HcisSkeleton width="45%" height="0.8rem" :animation="studioAnimation" />
                            </div>
                        </div>
                    </template>
                    <template v-else-if="studioType === 'table-row'">
                        <div class="d-flex flex-column gap-2">
                            <HcisSkeleton width="100%" height="2.25rem" rounded="md" :animation="studioAnimation" />
                            <HcisSkeleton width="100%" height="2.25rem" rounded="md" :animation="studioAnimation" />
                            <HcisSkeleton width="100%" height="2.25rem" rounded="md" :animation="studioAnimation" />
                        </div>
                    </template>
                    <template v-else>
                        <HcisSkeleton :lines="studioLines" :animation="studioAnimation" />
                    </template>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Preset Tipe Skeleton</label>
                    <select v-model="studioType" class="form-select form-select-sm">
                        <option value="card">Card Block (Kartu Utuh)</option>
                        <option value="avatar-text">Avatar & Teks Profil</option>
                        <option value="table-row">Baris Tabel Data</option>
                        <option value="lines">Multi-line Paragraph</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Gaya Animasi</label>
                    <select v-model="studioAnimation" class="form-select form-select-sm">
                        <option value="shimmer">Shimmer Wave (Gelombang Mewah)</option>
                        <option value="pulse">Pulse Glow (Pendar Halus)</option>
                        <option value="none">Tanpa Animasi (Static)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4" v-if="studioType === 'lines'">
                    <label class="ds-control-label">Jumlah Baris ({{ studioLines }})</label>
                    <input v-model.number="studioLines" type="range" min="1" max="6" class="form-range" />
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
            <span class="ds-group-label">1. Skenario Kartu Profil Sedang Memuat vs Terisi:</span>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm h-100">
                        <span class="text-xs font-semibold text-slate-500 mb-2 d-block">State: Loading (Shimmer):</span>
                        <HcisSkeleton type="card" />
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm h-100">
                        <span class="text-xs font-semibold text-slate-500 mb-2 d-block">State: Loaded (Siap):</span>
                        <HcisCard title="Ayu Rahmawati" description="Senior HC Specialist" icon="person-circle">
                            <p class="text-xs text-slate-600 mb-0">Unit: Organization Development & Talent Management.</p>
                        </HcisCard>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Variasi Shimmer vs Pulse:</span>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-white rounded-xl border border-slate-200">
                        <span class="text-xs font-semibold text-slate-500 mb-2 d-block">Shimmer:</span>
                        <HcisSkeleton :lines="3" animation="shimmer" />
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="p-3 bg-white rounded-xl border border-slate-200">
                        <span class="text-xs font-semibold text-slate-500 mb-2 d-block">Pulse:</span>
                        <HcisSkeleton :lines="3" animation="pulse" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisCard, HcisSkeleton, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioType = ref('card');
const studioAnimation = ref('shimmer');
const studioLines = ref(3);
const copied = ref(false);

// Computed Code Generator
const generatedSnippet = computed(() => {
    if (studioType.value === 'card') {
        return `<HcisSkeleton type="card" animation="${studioAnimation.value}" />`;
    }
    if (studioType.value === 'avatar-text') {
        return `<div class="d-flex align-items-center gap-3">\n    <HcisSkeleton type="avatar" width="3.25rem" height="3.25rem" />\n    <div class="flex-grow-1">\n        <HcisSkeleton width="65%" height="1.1rem" class="mb-2" />\n        <HcisSkeleton width="45%" height="0.8rem" />\n    </div>\n</div>`;
    }
    return `<HcisSkeleton :lines="${studioLines.value}" animation="${studioAnimation.value}" />`;
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
