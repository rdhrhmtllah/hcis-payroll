<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisDropdown Menu Studio</h3>
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
                <div class="p-3 bg-white rounded-xl shadow-sm border border-slate-200 d-flex flex-column align-items-center" style="min-height: 220px;">
                    <HcisDropdown
                        :label="studioLabel"
                        :items="demoItems"
                        :variant="studioVariant"
                        :size="studioSize"
                        :align="studioAlign"
                        @select="handleSelect"
                    />

                    <div v-if="lastSelection" class="mt-4 p-2 bg-slate-900 text-slate-100 rounded-lg text-xs font-mono d-flex align-items-center">
                        <HcisIcon name="check-circle-fill" size="2xs" class="text-emerald-400 me-2" />
                        <span>Menu Diklik: <strong>{{ lastSelection.label }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Label Trigger</label>
                    <input v-model="studioLabel" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Varian Tombol</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="primary">Primary (Brand 600)</option>
                        <option value="secondary">Secondary (Pristine Slate)</option>
                        <option value="ghost">Ghost (Flat Minimal)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="xs">xs</option>
                        <option value="sm">sm</option>
                        <option value="md">md</option>
                        <option value="lg">lg</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Perataan Menu (Align)</label>
                    <select v-model="studioAlign" class="form-select form-select-sm">
                        <option value="left">Rata Kiri (Left)</option>
                        <option value="right">Rata Kanan (Right)</option>
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
            <span class="ds-group-label">1. Skenario Trigger Tombol Icon-Only (Aksi Tabel Tiga Titik):</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm d-flex align-items-center gap-3">
                <span class="text-xs text-slate-600 font-semibold">Tabel Karyawan: Ahmad Fauzi</span>
                <HcisDropdown :items="demoItems" align="right">
                    <template #trigger>
                        <HcisButton size="xs" variant="ghost" icon-only leading-icon="three-dots-vertical" />
                    </template>
                </HcisDropdown>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Item Non-Aktif & Varian Bahaya (Danger):</span>
            <div class="p-3 bg-white rounded-xl border border-slate-200" style="max-width: 320px;">
                <HcisDropdown
                    label="Kelola Akun"
                    :items="[
                        { label: 'Lihat Ringkasan Profil', icon: 'eye' },
                        { label: 'Ubah Sandi (Terkunci)', icon: 'key', disabled: true },
                        { divider: true },
                        { label: 'Hapus Akun Permanen', icon: 'trash-fill', variant: 'danger' }
                    ]"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisDropdown, HcisButton, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioLabel = ref('Opsi Aksi Pengguna');
const studioVariant = ref('secondary');
const studioSize = ref('sm');
const studioAlign = ref('left');
const lastSelection = ref(null);
const copied = ref(false);

const demoItems = [
    { label: 'Lihat Detail Profil', icon: 'person-bounding-box' },
    { label: 'Ubah Data Otorisasi', icon: 'pencil-fill' },
    { label: 'Ekspor Riwayat (.pdf)', icon: 'file-earmark-pdf-fill' },
    { divider: true },
    { label: 'Batalkan Otorisasi', icon: 'x-circle-fill', variant: 'danger' },
];

function handleSelect(item) {
    lastSelection.value = item;
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`:items="menuItems"`);
    if (studioLabel.value) props.push(`label="${studioLabel.value}"`);
    if (studioVariant.value !== 'secondary') props.push(`variant="${studioVariant.value}"`);
    if (studioSize.value !== 'sm') props.push(`size="${studioSize.value}"`);
    if (studioAlign.value !== 'left') props.push(`align="${studioAlign.value}"`);

    return `<HcisDropdown\n    ${props.join('\n    ')}\n    @select="onSelect"\n/>`;
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
