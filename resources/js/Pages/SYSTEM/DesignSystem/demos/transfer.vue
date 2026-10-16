<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisTransfer Permission Dual-List Studio</h3>
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
                <div class="p-4 bg-white rounded-xl shadow-sm border border-slate-200 w-100" style="max-width: 680px;">
                    <HcisTransfer
                        v-model="studioValue"
                        :data="rolePermissions"
                        :titles="[studioLeftTitle, studioRightTitle]"
                        :filterable="studioFilterable"
                        :disabled="studioDisabled"
                    />

                    <div class="mt-3 p-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs d-flex align-items-center justify-content-between">
                        <span class="text-slate-500">ID Terpilih (Target):</span>
                        <strong class="text-indigo-700 font-mono">{{ studioValue.join(', ') || '(Tidak ada)' }}</strong>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Judul Panel Sumber (Kiri)</label>
                    <input v-model="studioLeftTitle" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Judul Panel Target (Kanan)</label>
                    <input v-model="studioRightTitle" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Opsi Mode</label>
                    <div class="d-flex align-items-center gap-3 pt-2">
                        <div class="form-check form-switch m-0">
                            <input id="tr-flt" v-model="studioFilterable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tr-flt">Search Filter</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="tr-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="tr-dis">Disabled</label>
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
            <span class="ds-group-label">1. Skenario Penugasan Hak Otorisasi Role Admin:</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm" style="max-width: 680px;">
                <HcisTransfer
                    v-model="studioValue"
                    :data="rolePermissionsWithDisabled"
                    :titles="['Hak Tersedia', 'Hak Aktif']"
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Mode Disabled (Nonaktif Terkunci):</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200" style="max-width: 680px;">
                <HcisTransfer :model-value="['2', '4']" :data="rolePermissions" :disabled="true" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisTransfer, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioLeftTitle = ref('Hak Akses Tersedia');
const studioRightTitle = ref('Hak Akses Diberikan');
const studioValue = ref(['2', '4']);
const studioFilterable = ref(true);
const studioDisabled = ref(false);
const copied = ref(false);

const rolePermissions = [
    { key: '1', label: 'Melihat Modul Karyawan' },
    { key: '2', label: 'Menambah Data Karyawan' },
    { key: '3', label: 'Mengubah Gaji & Benefit' },
    { key: '4', label: 'Mencetak Laporan KPI' },
    { key: '5', label: 'Mengelola Pengguna & Peran' },
];

const rolePermissionsWithDisabled = [
    { key: '1', label: 'Melihat Modul Karyawan' },
    { key: '2', label: 'Menambah Data Karyawan (Dibatasi)', disabled: true },
    { key: '3', label: 'Mengubah Gaji & Benefit' },
    { key: '4', label: 'Mencetak Laporan KPI' },
    { key: '5', label: 'Mengelola Pengguna & Peran (Dibatasi)', disabled: true },
];

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="selectedKeys"`);
    props.push(`:data="permissionsData"`);
    props.push(`:titles="['${studioLeftTitle.value}', '${studioRightTitle.value}']"`);
    if (!studioFilterable.value) props.push(`:filterable="false"`);
    if (studioDisabled.value) props.push(`disabled`);

    return `<HcisTransfer\n    ${props.join('\n    ')}\n/>`;
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
