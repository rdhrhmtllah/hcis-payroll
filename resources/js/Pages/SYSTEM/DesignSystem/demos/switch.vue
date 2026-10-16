<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisSwitch Configurator</h3>
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
                <div class="d-flex flex-column align-items-center justify-content-center p-3 gap-3">
                    <HcisSwitch
                        v-model="studioChecked"
                        :size="studioSize"
                        :variant="studioVariant"
                        :active-text="studioShowLabel ? studioActiveText : ''"
                        :inactive-text="studioShowLabel ? studioInactiveText : ''"
                        :active-thumb-icon="studioActiveIcon || undefined"
                        :inactive-thumb-icon="studioInactiveIcon || undefined"
                        :active-thumb-icon-color="studioActiveIconColor || undefined"
                        :inactive-thumb-icon-color="studioInactiveIconColor || undefined"
                        :inline-prompt="studioInlinePrompt"
                        :disabled="studioDisabled"
                        :loading="studioLoading"
                    />

                    <div class="text-xs fw-semibold" :class="studioStageBg === 'dark' ? 'text-slate-300' : 'text-slate-600'">
                        Status Nilai v-model:
                        <span class="badge" :class="studioChecked ? 'bg-primary' : 'bg-secondary'">
                            {{ studioChecked ? 'TRUE (Aktif)' : 'FALSE (Non-Aktif)' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="sm">Kecil (sm - 36x20px)</option>
                        <option value="md">Sedang (md - 44x24px)</option>
                        <option value="lg">Besar (lg - 54x28px)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Varian Warna (Variant)</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="brand">Brand Safir (Indigo)</option>
                        <option value="success">Success Zamrud (Emerald)</option>
                        <option value="warning">Warning Amber (Kuning)</option>
                        <option value="danger">Danger Koral (Merah)</option>
                        <option value="info">Info Sian (Biru Terang)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Ikon Thumb Aktif / Non-Aktif</label>
                    <select v-model="studioIconPreset" class="form-select form-select-sm" @change="applyIconPreset">
                        <option value="none">Tanpa Ikon Thumb</option>
                        <option value="darkmode">Mode Gelap / Terang (Moon / Sun)</option>
                        <option value="security">Keamanan (Shield Check / Shield X)</option>
                        <option value="bell">Notifikasi (Bell / Bell Slash)</option>
                        <option value="lock">Gembok Kunci (Lock / Unlock)</option>
                    </select>
                </div>

                <div class="col-12 col-md-6">
                    <label class="ds-control-label">Teks Label Aktif (Right / Active)</label>
                    <input v-model="studioActiveText" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-6">
                    <label class="ds-control-label">Teks Label Non-Aktif (Left / Inactive)</label>
                    <input v-model="studioInactiveText" type="text" class="form-control form-control-sm" />
                </div>

                <div class="col-12">
                    <label class="ds-control-label">Fitur & Mode Interaktif</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-lbl" v-model="studioShowLabel" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="sw-lbl">Tampilkan Label Luar</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-inl" v-model="studioInlinePrompt" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="sw-inl">Prompt Di Dalam Rel (inlinePrompt)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-lod" v-model="studioLoading" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="sw-lod">Memuat (Loading Spinner)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="sw-dis" v-model="studioDisabled" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="sw-dis">Nonaktif (Disabled)</label>
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
        <!-- Group 1: Security & Governance Toggles -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Pengaturan Keamanan & Otorisasi Sistem:</span>
            <div class="hcis-grid hcis-grid--2">
                <HcisCard title="Otentikasi Dua Faktor (2FA Biometrik)" icon="shield-lock" pattern="grid" size="sm">
                    <p class="text-xs text-slate-600 mb-3">Wajibkan verifikasi OTP setiap login dari perangkat baru.</p>
                    <HcisSwitch
                        v-model="demo2Fa"
                        variant="brand"
                        active-text="Wajib 2FA Aktif"
                        inactive-text="2FA Dimatikan"
                        active-thumb-icon="shield-check"
                        inactive-thumb-icon="shield-x"
                        active-thumb-icon-color="#4f46e5"
                        inactive-thumb-icon-color="#94a3b8"
                    />
                </HcisCard>

                <HcisCard title="Sinkronisasi Presensi Mesin Biometrik" icon="arrow-repeat" pattern="dots" size="sm">
                    <p class="text-xs text-slate-600 mb-3">Tarik log absensi fingerprint & face ID real-time tiap 15 menit.</p>
                    <HcisSwitch
                        v-model="demoSyncAbsen"
                        variant="success"
                        active-text="Auto Sync Aktif (15m)"
                        inactive-text="Sinkronisasi Manual"
                        active-thumb-icon="check-circle-fill"
                        active-thumb-icon-color="#10b981"
                    />
                </HcisCard>
            </div>
        </div>

        <!-- Group 2: User Preference & Interface Customization -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Preferensi Notifikasi & Tampilan Antarmuka:</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisCard title="Notifikasi WhatsApp" icon="whatsapp" size="sm">
                    <HcisSwitch
                        v-model="demoNotifWa"
                        variant="success"
                        active-text="Notif Aktif"
                        inactive-text="Senyap"
                    />
                </HcisCard>

                <HcisCard title="Mode Gelap Eksekutif" icon="moon-stars-fill" size="sm">
                    <HcisSwitch
                        v-model="demoDarkMode"
                        variant="brand"
                        active-text="Dark Mode"
                        inactive-text="Light Mode"
                        active-thumb-icon="moon-fill"
                        inactive-thumb-icon="sun-fill"
                        active-thumb-icon-color="#6366f1"
                        inactive-thumb-icon-color="#f59e0b"
                    />
                </HcisCard>

                <HcisCard title="Otomatisasi Lembur PKWT" icon="clock-history" size="sm">
                    <HcisSwitch
                        v-model="demoOvertimeAuto"
                        variant="warning"
                        active-text="Kalkulasi Otomatis"
                        inactive-text="Persetujuan Manual"
                    />
                </HcisCard>
            </div>
        </div>

        <!-- Group 3: Sizing Hierarchy -->
        <div class="ds-demo-group">
            <span class="ds-group-label">3. Perbandingan Ukuran Sakelar (sm / md / lg):</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisCard title="Kecil (sm - 36x20px)" size="sm">
                    <HcisSwitch v-model="demoSizeSm" size="sm" active-text="Status Aktif" />
                </HcisCard>
                <HcisCard title="Standar (md - 44x24px)" size="sm">
                    <HcisSwitch v-model="demoSizeMd" size="md" active-text="Status Aktif" />
                </HcisCard>
                <HcisCard title="Besar (lg - 54x28px)" size="sm">
                    <HcisSwitch v-model="demoSizeLg" size="lg" active-text="Status Aktif" />
                </HcisCard>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Matriks Status Siklus Hidup Sakelar:</span>
            <div class="hcis-grid hcis-grid--3">
                <HcisCard title="Status Aktif (Checked)" size="sm">
                    <HcisSwitch :model-value="true" active-text="Berlaku Seketika" />
                </HcisCard>
                <HcisCard title="Status Memuat (Loading API)" size="sm">
                    <HcisSwitch :model-value="true" loading active-text="Menyimpan ke Server..." />
                </HcisCard>
                <HcisCard title="Status Terkunci (Disabled)" size="sm">
                    <HcisSwitch :model-value="true" disabled active-text="Izin Terkunci Induk" />
                </HcisCard>
            </div>
        </div>

        <HcisAlert
            variant="warning"
            title="Prinsip Penggunaan Sakelar (Switch Standard)"
            description="Sakelar memberikan ekspektasi perubahan status seketika (instant commit). Jangan gunakan sakelar pada formulir bertombol 'Simpan'; untuk formulir tersebut gunakan HcisCheckbox."
        />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisCard, HcisAlert, HcisSwitch, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioChecked = ref(true);
const studioSize = ref('md');
const studioVariant = ref('brand');
const studioActiveText = ref('Fitur Otomatisasi Aktif');
const studioInactiveText = ref('Fitur Dinonaktifkan');
const studioShowLabel = ref(true);
const studioInlinePrompt = ref(false);
const studioIconPreset = ref('none');
const studioActiveIcon = ref('');
const studioInactiveIcon = ref('');
const studioActiveIconColor = ref('');
const studioInactiveIconColor = ref('');
const studioDisabled = ref(false);
const studioLoading = ref(false);
const copied = ref(false);

function applyIconPreset() {
    if (studioIconPreset.value === 'darkmode') {
        studioActiveIcon.value = 'moon-fill';
        studioInactiveIcon.value = 'sun-fill';
        studioActiveIconColor.value = '#6366f1';
        studioInactiveIconColor.value = '#f59e0b';
    } else if (studioIconPreset.value === 'security') {
        studioActiveIcon.value = 'shield-check';
        studioInactiveIcon.value = 'shield-x';
        studioActiveIconColor.value = '#10b981';
        studioInactiveIconColor.value = '#ef4444';
    } else if (studioIconPreset.value === 'bell') {
        studioActiveIcon.value = 'bell-fill';
        studioInactiveIcon.value = 'bell-slash';
        studioActiveIconColor.value = '#4f46e5';
        studioInactiveIconColor.value = '#94a3b8';
    } else if (studioIconPreset.value === 'lock') {
        studioActiveIcon.value = 'lock-fill';
        studioInactiveIcon.value = 'unlock-fill';
        studioActiveIconColor.value = '#4f46e5';
        studioInactiveIconColor.value = '#94a3b8';
    } else {
        studioActiveIcon.value = '';
        studioInactiveIcon.value = '';
        studioActiveIconColor.value = '';
        studioInactiveIconColor.value = '';
    }
}

// Scenarios Demo State
const demo2Fa = ref(true);
const demoSyncAbsen = ref(true);
const demoNotifWa = ref(true);
const demoDarkMode = ref(true);
const demoOvertimeAuto = ref(false);
const demoSizeSm = ref(true);
const demoSizeMd = ref(true);
const demoSizeLg = ref(true);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="switchValue"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioVariant.value !== 'brand') props.push(`variant="${studioVariant.value}"`);
    if (studioShowLabel.value && studioActiveText.value) props.push(`active-text="${studioActiveText.value}"`);
    if (studioShowLabel.value && studioInactiveText.value) props.push(`inactive-text="${studioInactiveText.value}"`);
    if (studioActiveIcon.value) props.push(`active-thumb-icon="${studioActiveIcon.value}"`);
    if (studioInactiveIcon.value) props.push(`inactive-thumb-icon="${studioInactiveIcon.value}"`);
    if (studioActiveIconColor.value) props.push(`active-thumb-icon-color="${studioActiveIconColor.value}"`);
    if (studioInlinePrompt.value) props.push(`inline-prompt`);
    if (studioDisabled.value) props.push(`disabled`);
    if (studioLoading.value) props.push(`loading`);

    return `<HcisSwitch\n    ${props.join('\n    ')}\n/>`;
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
