<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisBreadcrumb Navigator Configurator</h3>
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
                <div class="p-3 bg-white rounded-xl shadow-sm border border-slate-200" style="width: 100%; max-width: 720px;">
                    <HcisBreadcrumb
                        :items="demoBreadcrumbItems"
                        :separator="studioSeparator"
                        :variant="studioVariant"
                        :size="studioSize"
                        :show-home="studioShowHome"
                        :max-items="studioMaxItems ? 3 : 0"
                    />
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Pemisah (Separator)</label>
                    <select v-model="studioSeparator" class="form-select form-select-sm">
                        <option value="chevron">Chevron (›)</option>
                        <option value="slash">Slash (/)</option>
                        <option value="bullet">Bullet (•)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Varian Wadah (Variant)</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="plain">Plain (Polos Bersih)</option>
                        <option value="pills">Pills (Kapsul Mewah)</option>
                        <option value="card">Card (Kartu Frame)</option>
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
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="bc-home" v-model="studioShowHome" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="bc-home">Ikon Beranda (Home)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="bc-max" v-model="studioMaxItems" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="bc-max">Singkat Jalur Panjang (maxItems=3)</label>
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
            <span class="ds-group-label">1. Skenario Jalur Navigasi Hierarki Modul HCIS:</span>
            <div class="d-flex flex-column gap-3">
                <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                    <span class="text-xs text-slate-500 font-semibold mb-2 d-block">Navigasi Modul Standar:</span>
                    <HcisBreadcrumb
                        show-home
                        separator="chevron"
                        :items="[
                            { label: 'Manajemen Pegawai', href: '/pegawai', icon: 'people' },
                            { label: 'Kompensasi & Benefit', href: '/pegawai/benefit' },
                            { label: 'Rincian Slip Gaji Periode Agustus 2026' }
                        ]"
                    />
                </div>

                <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                    <span class="text-xs text-slate-500 font-semibold mb-2 d-block">Varian Kapsul Pills:</span>
                    <HcisBreadcrumb
                        variant="pills"
                        separator="chevron"
                        :items="[
                            { label: 'Modul SOP', href: '/sop', icon: 'journal-text' },
                            { label: 'Dokumen Terbit', href: '/sop/terbit' },
                            { label: 'SOP Pelaporan Lembur' }
                        ]"
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
            <span class="ds-group-label">Penyingkatan Jalur Bertingkat Dalam (maxItems=3):</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200">
                <HcisBreadcrumb
                    show-home
                    :max-items="3"
                    separator="chevron"
                    :items="[
                        { label: 'HCIS Enterprise', href: '/' },
                        { label: 'Human Capital Management', href: '/hcm' },
                        { label: 'Divisi Produksi Pabrik', href: '/hcm/mfg' },
                        { label: 'Roster Shift Lembur', href: '/hcm/mfg/shift' },
                        { label: 'Penilaian Efektivitas Kerja' }
                    ]"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisBreadcrumb, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioSeparator = ref('chevron');
const studioVariant = ref('plain');
const studioSize = ref('md');
const studioShowHome = ref(true);
const studioMaxItems = ref(false);
const copied = ref(false);

const demoBreadcrumbItems = [
    { label: 'Modul SOP', href: '/sop', icon: 'journal-text' },
    { label: 'Penjadwalan', href: '/sop/jadwal' },
    { label: 'Paket Evaluasi Agustus 2026' },
];

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`:items="breadcrumbItems"`);
    if (studioSeparator.value !== 'slash') props.push(`separator="${studioSeparator.value}"`);
    if (studioVariant.value !== 'plain') props.push(`variant="${studioVariant.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioShowHome.value) props.push(`show-home`);
    if (studioMaxItems.value) props.push(`:max-items="3"`);

    return `<HcisBreadcrumb\n    ${props.join('\n    ')}\n/>`;
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
