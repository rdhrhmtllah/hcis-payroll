<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisContextBanner Identity Studio</h3>
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
                <div style="width: 100%; max-width: 820px;">
                    <HcisContextBanner
                        :title="studioTitle"
                        :code="studioCode"
                        :kicker="studioKicker"
                        :icon="studioIcon"
                        :role-label="studioRoleLabel"
                        :scope-label="studioScopeLabel"
                        :scope-variant="studioScopeVariant"
                        :scope-radar="studioScopeRadar"
                        :variant="studioVariant"
                        :sentiment="studioSentiment"
                        :pattern="studioPattern"
                        :capabilities="studioCapabilities"
                    >
                        <template #actions>
                            <HcisButton
                                label="Beralih Unit"
                                size="xs"
                                variant="secondary"
                                leading-icon="arrow-left-right"
                            />
                        </template>
                    </HcisContextBanner>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Nama Modul / Fitur (Title)</label>
                    <input v-model="studioTitle" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Kode Singkat (Code)</label>
                    <input v-model="studioCode" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Kicker / Kategori</label>
                    <input v-model="studioKicker" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Varian Visual (Variant)</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="glass">Glass (Kaca Blur Translucent)</option>
                        <option value="surface">Surface (Solid White)</option>
                        <option value="brand">Brand (Gradasi Royal Indigo)</option>
                        <option value="obsidian">Obsidian (Dark Mode)</option>
                        <option value="flat">Flat (Minimalis)</option>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Sentimen Warna</label>
                    <select v-model="studioSentiment" class="form-select form-select-sm">
                        <option value="brand">Brand (Indigo)</option>
                        <option value="success">Success (Emerald)</option>
                        <option value="purple">Purple / Violet</option>
                        <option value="teal">Teal / Cyan</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Pola Latar (Pattern)</label>
                    <select v-model="studioPattern" class="form-select form-select-sm">
                        <option value="grid">Grid Pattern</option>
                        <option value="dots">Dots Pattern</option>
                        <option value="crosshair">Crosshair</option>
                        <option value="none">None</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Label Peran (Role)</label>
                    <input v-model="studioRoleLabel" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Label Scope Unit</label>
                    <input v-model="studioScopeLabel" type="text" class="form-control form-control-sm" />
                </div>

                <div class="col-12">
                    <div class="form-check form-switch m-0 pt-1">
                        <input id="cb-rdr" v-model="studioScopeRadar" class="form-check-input" type="checkbox" />
                        <label class="form-check-label text-xs fw-semibold" for="cb-rdr">Aktifkan Pulsing Radar Beacon pada Badge Scope</label>
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
            <span class="ds-group-label">1. Skenario Konteks Otorisasi Modul & Kepegawaian HCIS:</span>
            <div class="d-flex flex-column gap-3">
                <HcisContextBanner
                    title="Grup Manajemen Kinerja & Evaluasi KPI"
                    code="MOD-04-KPI"
                    kicker="MODUL STRATEGIS HCIS"
                    icon="graph-up-arrow"
                    role-label="Executive Approver"
                    scope-label="Scope Global (Holding Corporation)"
                    scope-variant="brand"
                    scope-radar
                    variant="glass"
                    sentiment="brand"
                    pattern="grid"
                    :capabilities="[
                        { id: 'appr', label: 'Pengesahan Skor KPI', icon: 'check-circle' },
                        { id: 'audit', label: 'Log Audit Evaluasi', icon: 'shield-lock' },
                        { id: 'matrix', label: '9-Box Talent Matrix', icon: 'grid-3x3' },
                    ]"
                >
                    <template #actions>
                        <HcisButton label="Ganti Role" size="xs" variant="secondary" leading-icon="person-gear" />
                    </template>
                </HcisContextBanner>

                <HcisContextBanner
                    title="Divisi Pabrik Cikarang — Bagian Rekayasa Produksi"
                    code="PLANT-CKR-PROD"
                    kicker="KONTEKS OPERASIONAL PABRIK"
                    icon="building"
                    role-label="Plant Manager"
                    scope-label="Scope Unit (Pabrik Cikarang)"
                    scope-variant="success"
                    variant="surface"
                    sentiment="success"
                    pattern="dots"
                    :capabilities="[
                        { id: 'shift', label: 'Atur Shift Lembur', icon: 'clock' },
                        { id: 'roster', label: 'Roster Kehadiran', icon: 'calendar-check' },
                        { id: 'payroll', label: 'Otorisasi Insentif', icon: 'cash-stack' },
                    ]"
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Konteks Scope Ditolak / Terbatas (Access Denied):</span>
            <HcisContextBanner
                title="Akses Terbatas — Otorisasi Memerlukan Persetujuan Direksi"
                code="RESTRICTED"
                kicker="STATUS HAK AKSES SISTEM"
                icon="shield-slash"
                role-label="Viewer Tamu"
                scope-label="Akses Ditolak (DENIED)"
                scope-variant="danger"
                variant="flat"
                sentiment="danger"
                :capabilities="[]"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisContextBanner, HcisButton, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioTitle = ref('Grup Penggajian & Otorisasi Payroll');
const studioCode = ref('MOD-07-PAYROLL');
const studioKicker = ref('GRUP FITUR STRATEGIS');
const studioIcon = ref('cash-stack');
const studioRoleLabel = ref('Super Administrator');
const studioScopeLabel = ref('Holding Global (Seluruh Unit Bisnis)');
const studioScopeVariant = ref('brand');
const studioScopeRadar = ref(true);
const studioVariant = ref('glass');
const studioSentiment = ref('brand');
const studioPattern = ref('grid');
const studioCapabilities = [
    { id: 'calc', label: 'Hitung Payroll', icon: 'calculator' },
    { id: 'approval', label: 'Otorisasi Disbursal', icon: 'check2-all' },
    { id: 'export', label: 'Ekspor Bank Transfer', icon: 'file-earmark-excel' }
];
const copied = ref(false);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`title="${studioTitle.value}"`);
    if (studioCode.value) props.push(`code="${studioCode.value}"`);
    if (studioKicker.value) props.push(`kicker="${studioKicker.value}"`);
    if (studioIcon.value !== 'collection') props.push(`icon="${studioIcon.value}"`);
    if (studioRoleLabel.value) props.push(`role-label="${studioRoleLabel.value}"`);
    if (studioScopeLabel.value) props.push(`scope-label="${studioScopeLabel.value}"`);
    if (studioScopeVariant.value !== 'brand') props.push(`scope-variant="${studioScopeVariant.value}"`);
    if (studioScopeRadar.value) props.push(`scope-radar`);
    if (studioVariant.value !== 'glass') props.push(`variant="${studioVariant.value}"`);
    if (studioSentiment.value !== 'brand') props.push(`sentiment="${studioSentiment.value}"`);
    if (studioPattern.value !== 'none') props.push(`pattern="${studioPattern.value}"`);

    return `<HcisContextBanner\n    ${props.join('\n    ')}\n    :capabilities="currentCapabilities"\n/>`;
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
