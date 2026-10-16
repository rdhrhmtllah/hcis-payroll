<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisTooltip Configurator</h3>
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
                <div class="p-5 text-center">
                    <HcisTooltip
                        :title="studioTitle"
                        :content="studioContent"
                        :theme="studioTheme"
                        :placement="studioPlacement"
                        :trigger="studioTrigger"
                        :show-arrow="studioShowArrow"
                    >
                        <HcisButton
                            label="Arahkan Kursor / Klik ke Sini"
                            variant="primary"
                            size="md"
                        >
                            <template #leading><HcisIcon name="info-circle" size="xs" /></template>
                        </HcisButton>
                    </HcisTooltip>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Judul Popover (Opsional)</label>
                    <input v-model="studioTitle" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Isi Petunjuk (Content)</label>
                    <input v-model="studioContent" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Tema Visual (Theme)</label>
                    <select v-model="studioTheme" class="form-select form-select-sm">
                        <option value="dark">Dark (Hitam Karbon)</option>
                        <option value="light">Light (Putih Kristal)</option>
                        <option value="glass">Glass (Frosted Kaca)</option>
                        <option value="brand">Brand (Indigo Luxury)</option>
                        <option value="success">Success (Emerald)</option>
                        <option value="danger">Danger (Merah Ruby)</option>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Posisi Melayang (Placement)</label>
                    <select v-model="studioPlacement" class="form-select form-select-sm">
                        <option value="top">Top (Atas)</option>
                        <option value="bottom">Bottom (Bawah)</option>
                        <option value="left">Left (Kiri)</option>
                        <option value="right">Right (Kanan)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Metode Pemicu (Trigger)</label>
                    <select v-model="studioTrigger" class="form-select form-select-sm">
                        <option value="hover">Hover (Arahkan Mouse)</option>
                        <option value="click">Click (Klik Tombol)</option>
                        <option value="focus">Focus (Fokus Keyboard)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Tanda Panah (Arrow)</label>
                    <div class="form-check form-switch pt-1">
                        <input id="tt-arr" v-model="studioShowArrow" class="form-check-input" type="checkbox" />
                        <label class="form-check-label text-xs fw-semibold" for="tt-arr">Tampilkan Panah</label>
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
            <span class="ds-group-label">1. Skenario Penjelasan Formula Lembur & Metrik KPI:</span>
            <div class="hcis-grid hcis-grid--2">
                <!-- Skenario 1: Lembur -->
                <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs text-slate-500 font-semibold">Total Lembur Bulan Ini</div>
                        <div class="text-xl fw-bold text-slate-900">22.5 <span class="text-xs text-slate-500">Jam</span></div>
                    </div>
                    <HcisTooltip
                        title="Rincian Formula Lembur"
                        theme="dark"
                        placement="left"
                    >
                        <button type="button" class="btn btn-sm btn-outline-secondary p-1 rounded-circle" style="width: 28px; height: 28px;">
                            <HcisIcon name="info-circle" size="xs" />
                        </button>
                        <template #content>
                            <div class="d-flex flex-column gap-1 text-2xs py-1" style="min-width: 180px;">
                                <div class="d-flex justify-content-between">
                                    <span>Hari Kerja (1.5x):</span>
                                    <strong>14.5 Jam</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Akhir Pekan (2.0x):</span>
                                    <strong>8.0 Jam</strong>
                                </div>
                                <div class="border-top border-slate-700 my-1"></div>
                                <div class="d-flex justify-content-between text-indigo-300 fw-bold">
                                    <span>Estimasi Uang Lembur:</span>
                                    <span>Rp 1.450.000</span>
                                </div>
                            </div>
                        </template>
                    </HcisTooltip>
                </div>

                <!-- Skenario 2: Glass Theme -->
                <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs text-slate-500 font-semibold">Capaian Target KPI Q3</div>
                        <div class="text-xl fw-bold text-emerald-600">107.9% <span class="text-xs text-emerald-700 font-medium">Sangat Baik</span></div>
                    </div>
                    <HcisTooltip
                        title="Metrik Perhitungan KPI"
                        theme="glass"
                        placement="left"
                    >
                        <button type="button" class="btn btn-sm btn-outline-secondary p-1 rounded-circle" style="width: 28px; height: 28px;">
                            <HcisIcon name="graph-up-arrow" size="xs" />
                        </button>
                        <template #content>
                            <div class="d-flex flex-column gap-1 text-2xs py-1" style="min-width: 160px;">
                                <div class="d-flex justify-content-between">
                                    <span>Bobot Utama:</span>
                                    <strong>95.4%</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Normalized Bonus:</span>
                                    <strong class="text-emerald-700">+12.5%</strong>
                                </div>
                            </div>
                        </template>
                    </HcisTooltip>
                </div>
            </div>
        </div>

        <div class="ds-demo-group">
            <span class="ds-group-label">2. Posisi Pendaran Melayang (Placement):</span>
            <div class="d-flex flex-wrap gap-3">
                <HcisTooltip content="Petunjuk di Atas" placement="top">
                    <HcisButton label="Atas (Top)" variant="secondary" size="sm" />
                </HcisTooltip>
                <HcisTooltip content="Petunjuk di Bawah" placement="bottom">
                    <HcisButton label="Bawah (Bottom)" variant="secondary" size="sm" />
                </HcisTooltip>
                <HcisTooltip content="Petunjuk di Kiri" placement="left">
                    <HcisButton label="Kiri (Left)" variant="secondary" size="sm" />
                </HcisTooltip>
                <HcisTooltip content="Petunjuk di Kanan" placement="right">
                    <HcisButton label="Kanan (Right)" variant="secondary" size="sm" />
                </HcisTooltip>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <HcisAlert
            variant="info"
            title="Aksesibilitas & Teleporting HcisTooltip"
            description="HcisTooltip diletakkan di Teleport body dengan auto-repositioning saat scrolling atau resizing agar konten tooltip tidak pernah terpotong overflow."
        />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisTooltip, HcisButton, HcisAlert, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioTitle = ref('Informasi Tambahan');
const studioContent = ref('Petunjuk bantuan untuk memandu pengguna saat mengisi formulir atau melihat metrik data.');
const studioTheme = ref('dark');
const studioPlacement = ref('top');
const studioTrigger = ref('hover');
const studioShowArrow = ref(true);
const copied = ref(false);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    if (studioTitle.value) props.push(`title="${studioTitle.value}"`);
    if (studioContent.value) props.push(`content="${studioContent.value}"`);
    if (studioTheme.value !== 'dark') props.push(`theme="${studioTheme.value}"`);
    if (studioPlacement.value !== 'top') props.push(`placement="${studioPlacement.value}"`);
    if (studioTrigger.value !== 'hover') props.push(`trigger="${studioTrigger.value}"`);
    if (!studioShowArrow.value) props.push(`:show-arrow="false"`);

    return `<HcisTooltip\n    ${props.join('\n    ')}\n>\n    <HcisButton label="Pemicu Tooltip" variant="primary" size="md" />\n</HcisTooltip>`;
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
