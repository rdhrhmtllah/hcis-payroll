<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisDrawer Slide-Over Configurator</h3>
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
                <div class="d-flex flex-column align-items-center gap-3">
                    <HcisButton
                        :label="`Buka Drawer (${studioDirection.toUpperCase()} - ${studioSize.toUpperCase()})`"
                        variant="primary"
                        size="md"
                        @click="isDrawerOpen = true"
                    >
                        <template #leading>
                            <HcisIcon name="layout-sidebar-inset-reverse" size="xs" />
                        </template>
                    </HcisButton>
                    <span class="text-xs text-slate-500 font-medium">
                        Klik tombol untuk meluncurkan slide-over drawer interaktif
                    </span>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Judul Drawer (Title)</label>
                    <input v-model="studioTitle" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Arah Luncuran (Direction)</label>
                    <select v-model="studioDirection" class="form-select form-select-sm">
                        <option value="rtl">Kanan ke Kiri (rtl - Standar)</option>
                        <option value="ltr">Kiri ke Kanan (ltr - Navigasi)</option>
                        <option value="btt">Bawah ke Atas (btt - Bottom Sheet)</option>
                        <option value="ttb">Atas ke Bawah (ttb - Banner Notif)</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="ds-control-label">Ukuran Preset</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="sm">Kecil (sm - 22rem)</option>
                        <option value="md">Sedang (md - 30rem)</option>
                        <option value="lg">Besar (lg - 40rem)</option>
                        <option value="xl">Ekstra (xl - 52rem)</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="ds-control-label">Sentiment</label>
                    <select v-model="studioSentiment" class="form-select form-select-sm">
                        <option value="primary">Primary</option>
                        <option value="neutral">Neutral</option>
                        <option value="danger">Danger</option>
                        <option value="warning">Warning</option>
                        <option value="success">Success</option>
                        <option value="info">Info</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="ds-control-label">Fitur & Interaksi Drawer</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="drw-btn-close" v-model="studioShowClose" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="drw-btn-close">Tombol X (Close)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="drw-close-overlay" v-model="studioCloseOnOverlay" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="drw-close-overlay">Tutup saat Klik Luar (Overlay)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="drw-close-esc" v-model="studioCloseOnEsc" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="drw-close-esc">Tutup saat Tekan ESC</label>
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

        <!-- Interactive Drawer Instance for Studio -->
        <HcisDrawer
            v-model="isDrawerOpen"
            :title="studioTitle"
            :description="studioDesc"
            :direction="studioDirection"
            :size="studioSize"
            :sentiment="studioSentiment"
            :show-close="studioShowClose"
            :close-on-overlay="studioCloseOnOverlay"
            :close-on-esc="studioCloseOnEsc"
        >
            <div class="d-flex flex-column gap-3">
                <div class="d-flex align-items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                    <HcisAvatar name="Budi Santoso" size="lg" />
                    <div>
                        <div class="fw-bold text-slate-900 text-sm">Budi Santoso</div>
                        <div class="text-xs text-slate-500">Senior Operator Mesin · Divisi Produksi I</div>
                        <div class="text-2xs text-slate-400 font-mono mt-1">NIK: EMP-2019-0431</div>
                    </div>
                </div>

                <HcisDivider label="Statistik Kinerja" />

                <div class="row g-2">
                    <div class="col-6">
                        <div class="p-2 bg-slate-50 rounded-lg border border-slate-200 text-center">
                            <div class="text-2xs text-slate-500 uppercase fw-bold">Kehadiran</div>
                            <div class="text-base fw-bold text-emerald-600">98.5%</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-slate-50 rounded-lg border border-slate-200 text-center">
                            <div class="text-2xs text-slate-500 uppercase fw-bold">Skor KPI</div>
                            <div class="text-base fw-bold text-indigo-600">112.0</div>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <HcisButton label="Tutup Laci" variant="secondary" size="sm" @click="isDrawerOpen = false" />
                <HcisButton label="Simpan Catatan" variant="primary" size="sm" @click="isDrawerOpen = false" />
            </template>
        </HcisDrawer>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         2. ENTERPRISE SCENARIOS & REAL-WORLD WORKFLOWS
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'variants'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Skenario Laci Cockpit Detail Karyawan & Filter Bar Samping:</span>
            <div class="d-flex flex-wrap gap-2">
                <HcisButton label="Buka Panel Kanan (RTL Profile)" variant="primary" size="sm" @click="demoProfileOpen = true">
                    <template #leading><HcisIcon name="person-badge" size="xs" /></template>
                </HcisButton>
                <HcisButton label="Buka Filter Cockpit (LTR Left)" variant="secondary" size="sm" @click="demoFilterOpen = true">
                    <template #leading><HcisIcon name="sliders" size="xs" /></template>
                </HcisButton>
                <HcisButton label="Buka Bottom Sheet (BTT Mobile)" variant="secondary" size="sm" @click="demoBottomOpen = true">
                    <template #leading><HcisIcon name="phone" size="xs" /></template>
                </HcisButton>
            </div>
        </div>

        <!-- Skenario 1: Profile Cockpit RTL -->
        <HcisDrawer
            v-model="demoProfileOpen"
            title="Cockpit Data Karyawan"
            description="Informasi rinci kepegawaian dan absensi terpusat"
            direction="rtl"
            size="md"
            sentiment="primary"
        >
            <div class="d-flex flex-column gap-3">
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 d-flex align-items-center gap-3">
                    <HcisAvatar name="Clarissa Maharani" size="lg" />
                    <div>
                        <div class="fw-bold text-slate-900 text-sm">Clarissa Maharani</div>
                        <div class="text-xs text-slate-500">Talent Acquisition Lead · Human Capital</div>
                    </div>
                </div>
            </div>
            <template #footer>
                <HcisButton label="Tutup" variant="secondary" size="sm" @click="demoProfileOpen = false" />
            </template>
        </HcisDrawer>

        <!-- Skenario 2: Filter Cockpit LTR -->
        <HcisDrawer
            v-model="demoFilterOpen"
            title="Cockpit Filter Data Lanjutan"
            description="Konfigurasi parameter filter multi-kriteria"
            direction="ltr"
            size="sm"
            sentiment="info"
        >
            <div class="d-flex flex-column gap-3">
                <HcisField label="Rentang Waktu Kerja">
                    <input type="text" class="form-control form-control-sm" value="Tahun Berjalan (2026)" />
                </HcisField>
            </div>
            <template #footer>
                <HcisButton label="Reset" variant="ghost" size="sm" />
                <HcisButton label="Terapkan Filter" variant="primary" size="sm" @click="demoFilterOpen = false" />
            </template>
        </HcisDrawer>

        <!-- Skenario 3: Bottom Sheet BTT -->
        <HcisDrawer
            v-model="demoBottomOpen"
            title="Menu Aksi Cepat Mobile"
            description="Pilihan tindakan cepat persetujuan cuti & lembur"
            direction="btt"
            size="md"
            sentiment="neutral"
        >
            <div class="d-flex flex-column gap-2">
                <HcisButton label="Setujui Seluruh Pengajuan" variant="primary" size="sm" />
                <HcisButton label="Tolak dengan Alasan" variant="danger-quiet" size="sm" />
            </div>
            <template #footer>
                <HcisButton label="Tutup" variant="secondary" size="sm" @click="demoBottomOpen = false" />
            </template>
        </HcisDrawer>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <HcisAlert
            variant="info"
            title="Performa Animasi 120fps HcisDrawer"
            description="HcisDrawer menggunakan transform hardware acceleration translate3d dengan isolasi render layout terpisah untuk performa optimal di perangkat mobile maupun desktop."
        />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisButton, HcisField, HcisDrawer, HcisAvatar, HcisDivider, HcisAlert, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const isDrawerOpen = ref(false);
const studioTitle = ref('Profil & Dashboard Karyawan');
const studioDesc = ref('Informasi ringkas dan performa anggota tim');
const studioDirection = ref('rtl');
const studioSize = ref('md');
const studioSentiment = ref('primary');
const studioShowClose = ref(true);
const studioCloseOnOverlay = ref(true);
const studioCloseOnEsc = ref(true);
const copied = ref(false);

// Scenarios State
const demoProfileOpen = ref(false);
const demoFilterOpen = ref(false);
const demoBottomOpen = ref(false);

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="isOpen"`);
    props.push(`title="${studioTitle.value}"`);
    if (studioDirection.value !== 'rtl') props.push(`direction="${studioDirection.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioSentiment.value !== 'primary') props.push(`sentiment="${studioSentiment.value}"`);
    if (!studioShowClose.value) props.push(`:show-close="false"`);
    if (!studioCloseOnOverlay.value) props.push(`:close-on-overlay="false"`);

    return `<HcisDrawer\n    ${props.join('\n    ')}\n>\n    <p>Konten panel laci samping...</p>\n    <template #footer>\n        <HcisButton label="Tutup" variant="secondary" size="sm" @click="isOpen = false" />\n    </template>\n</HcisDrawer>`;
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
