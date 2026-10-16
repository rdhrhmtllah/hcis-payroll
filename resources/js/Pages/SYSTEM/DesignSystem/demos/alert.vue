<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisAlert Luxury Notification Banner</h3>
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
                    { 'ds-stage-dark-mode': studioStageBg === 'dark' || studioAppearance === 'obsidian' || studioVariant === 'obsidian' }
                ]"
            >
                <div style="width: 100%; max-width: 620px; min-width: 0;">
                    <HcisAlert
                        v-if="studioVisible"
                        :variant="studioVariant"
                        :appearance="studioAppearance"
                        :size="studioSize"
                        :rounded="studioRounded"
                        :glow="studioGlow"
                        :pattern="studioPattern === 'none' ? false : studioPattern"
                        :title="studioTitle"
                        :badge="studioBadge"
                        :description="studioDescription"
                        :closable="studioClosable"
                        @close="handleAlertClose"
                    >
                        <template v-if="studioWithActions" #actions>
                            <HcisButton label="Periksa Detail" variant="secondary" size="xs" />
                            <HcisButton label="Otorisasi Cepat" variant="primary" size="xs" />
                        </template>
                    </HcisAlert>

                    <div v-else class="text-center p-3 bg-slate-100 dark:bg-slate-800 rounded-xl">
                        <span class="text-xs text-slate-500 me-2">Alert telah ditutup</span>
                        <HcisButton label="Tampilkan Ulang" variant="secondary" size="sm" @click="studioVisible = true" />
                    </div>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Sentimen Warna (Variant)</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="brand">Brand (Royal Sapphire)</option>
                        <option value="info">Info (Sky Marine)</option>
                        <option value="success">Success (Zamrud Emerald)</option>
                        <option value="warning">Warning (Amber Gold)</option>
                        <option value="danger">Danger (Coral Crimson)</option>
                        <option value="purple">Purple (Amethyst Violet)</option>
                        <option value="teal">Teal (Mint Turquoise)</option>
                        <option value="neutral">Neutral (Executive Slate)</option>
                        <option value="obsidian">Obsidian (Midnight Dark)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Tampilan Permukaan (Appearance)</label>
                    <select v-model="studioAppearance" class="form-select form-select-sm">
                        <option value="soft">Soft Tint (Pastel Kristal Multi-Gradien)</option>
                        <option value="glass">Glass (Frosted Kaca Translucent Glow)</option>
                        <option value="solid">Solid (Vibrant 3D Gradient Sheen)</option>
                        <option value="outline">Outline (Crisp Border Tint)</option>
                        <option value="obsidian">Obsidian (Midnight Dark Mode)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Micro-Pattern Overlay</label>
                    <select v-model="studioPattern" class="form-select form-select-sm">
                        <option value="dots">Dot-Matrix (Crystal Stippling)</option>
                        <option value="grid">Micro-Grid (Geometric Grid)</option>
                        <option value="mesh">Diagonal Mesh (Isometric)</option>
                        <option value="none">Polos (Tanpa Pattern)</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Kelengkungan Sudut (Rounded)</label>
                    <select v-model="studioRounded" class="form-select form-select-sm">
                        <option value="none">none (0px - Siku Tajam)</option>
                        <option value="sm">sm (6px - Bevel Halus)</option>
                        <option value="md">md (10px - Golden Squircle)</option>
                        <option value="lg">lg (14px - Lengkung Lebar)</option>
                        <option value="full">full (9999px - Kapsul Penuh)</option>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Judul Banner (Title)</label>
                    <input v-model="studioTitle" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Lencana Tag (Badge)</label>
                    <input v-model="studioBadge" type="text" class="form-control form-control-sm" placeholder="Contoh: SLA 24 Jam" />
                </div>
                <div class="col-12 col-md-5">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="sm">sm (Kompak - Ringkas)</option>
                        <option value="md">md (Standar - Seimbang 10px)</option>
                        <option value="lg">lg (Lapang - Executive Focus)</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="ds-control-label">Deskripsi Lengkap Pesan</label>
                    <input v-model="studioDescription" type="text" class="form-control form-control-sm" />
                </div>

                <div class="col-12">
                    <label class="ds-control-label">Fitur Efek & Slot Interaktivitas</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="alt-glow" v-model="studioGlow" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="alt-glow">Halo Ambient Glow</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="alt-cls" v-model="studioClosable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="alt-cls">Tombol Tutup (Closable)</label>
                        </div>
                        <div class="form-check form-check-inline form-switch m-0">
                            <input id="alt-act" v-model="studioWithActions" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="alt-act">Tombol Aksi (#actions Slot)</label>
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
            <span class="ds-group-label">1. Skenario Frosted Glass Acrylic & Ambient Halo Glow:</span>
            <div class="d-flex flex-column gap-3">
                <HcisAlert
                    variant="brand"
                    appearance="glass"
                    glow
                    title="Pembaruan Sistem Kepegawaian v2.8"
                    badge="Wajib Dibaca"
                    description="Seluruh sesi pengguna akan dialihkan ke kluster server cadangan malam ini pukul 23:00 WIB."
                    closable
                >
                    <template #actions>
                        <HcisButton label="Panduan Migrasi" variant="primary" size="xs" />
                    </template>
                </HcisAlert>

                <HcisAlert
                    variant="success"
                    appearance="glass"
                    glow
                    title="Pengajuan Cuti Tahunan Berhasil Disetujui"
                    badge="Otorisasi Selesai"
                    description="4 hari kerja cuti tahunan telah dicatat ke kalender HR dan kuota cuti telah diperbarui."
                    closable
                />
            </div>
        </div>

        <div class="ds-demo-group">
            <span class="ds-group-label">2. Skenario Solid Gradient Vibe & Multi-Stop Soft Tint:</span>
            <div class="d-flex flex-column gap-3">
                <HcisAlert
                    variant="warning"
                    appearance="soft"
                    title="Batas Akhir Evaluasi KPI Kuartal II"
                    badge="Tersisa 2 Hari"
                    description="Portal penilaian akan dikunci otomatis pada tanggal 31 Agustus 2026 pukul 17:00 WIB."
                >
                    <template #actions>
                        <HcisButton label="Buka Evaluasi Sekarang" variant="secondary" size="xs" />
                    </template>
                </HcisAlert>

                <HcisAlert
                    variant="danger"
                    appearance="solid"
                    title="Kegagalan Koneksi Database Payroll"
                    description="Kalkulasi lembur massal tertunda sementara waktu karena server basis data sedang sibuk. Silakan coba kembali sesaat lagi."
                    closable
                />
            </div>
        </div>

        <div class="ds-demo-group">
            <span class="ds-group-label">3. Skenario Obsidian Midnight Dark Mode:</span>
            <HcisAlert
                variant="obsidian"
                appearance="obsidian"
                glow
                title="Pusat Keamanan & Audit Kepegawaian (SOC 2)"
                badge="Protokol Enkripsi Aktif"
                description="Seluruh rekaman ekspor data gaji dienkripsi menggunakan standar AES-256 dan diawasi oleh tim infosec."
                closable
            />
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="ds-demo-group">
            <span class="ds-group-label">Matriks Skala Ukuran (Size Tiers):</span>
            <div class="d-flex flex-column gap-3">
                <HcisAlert variant="brand" size="sm" description="Mode Rapat (sm): Sistem audit HCIS aktif memantau seluruh perubahan rekaman data." />
                <HcisAlert variant="info" size="md" description="Mode Standar (md): Laporan bulanan dapat diunduh dalam format PDF atau XLSX." />
                <HcisAlert variant="neutral" size="lg" title="Mode Lapang (lg)" description="Pemberitahuan administratif penting dengan keterbacaan tinggi untuk pengumuman manajemen." />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisAlert, HcisButton, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioVisible = ref(true);
const studioVariant = ref('brand');
const studioAppearance = ref('glass');
const studioPattern = ref('dots');
const studioRounded = ref('md');
const studioSize = ref('md');
const studioGlow = ref(true);
const studioTitle = ref('Pembaruan Sistem Kepegawaian v2.8');
const studioBadge = ref('Fitur Baru');
const studioDescription = ref('Fitur kalkulasi lembur otomatis dan rekonsiliasi absensi kini telah aktif di portal HCIS.');
const studioClosable = ref(true);
const studioWithActions = ref(true);
const copied = ref(false);

function handleAlertClose() {
    studioVisible.value = false;
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    if (studioVariant.value !== 'info') props.push(`variant="${studioVariant.value}"`);
    if (studioAppearance.value !== 'soft') props.push(`appearance="${studioAppearance.value}"`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioRounded.value !== 'md') props.push(`rounded="${studioRounded.value}"`);
    if (studioGlow.value) props.push(`glow`);
    if (studioPattern.value !== 'dots') props.push(`pattern="${studioPattern.value}"`);
    if (studioTitle.value) props.push(`title="${studioTitle.value}"`);
    if (studioBadge.value) props.push(`badge="${studioBadge.value}"`);
    if (studioDescription.value) props.push(`description="${studioDescription.value}"`);
    if (studioClosable.value) props.push(`closable`);

    if (studioWithActions.value) {
        return `<HcisAlert\n    ${props.join('\n    ')}\n>\n    <template #actions>\n        <HcisButton label="Periksa Detail" variant="secondary" size="xs" />\n        <HcisButton label="Otorisasi Cepat" variant="primary" size="xs" />\n    </template>\n</HcisAlert>`;
    }

    return `<HcisAlert\n    ${props.join('\n    ')}\n/>`;
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
.ds-card-demo-wrap { display: flex; flex-direction: column; gap: 1.5rem; width: 100%; max-width: 100%; min-width: 0; }
.ds-demo-group { display: flex; flex-direction: column; gap: 0.75rem; width: 100%; max-width: 100%; min-width: 0; }
.ds-group-label { font-size: 0.8125rem; font-weight: 700; color: var(--hcis-slate-800, var(--hcis-slate-800)); letter-spacing: -0.01em; }

.ds-studio-panel {
    min-width: 0;
    max-width: 100%;
    width: 100%;
    box-sizing: border-box;
}

.ds-preview-stage {
    min-width: 0;
    max-width: 100%;
    width: 100%;
    overflow-x: hidden;
    box-sizing: border-box;
    padding: 1.5rem;
}

@media (max-width: 768px) {
    .ds-preview-stage {
        padding: 0.75rem 0.25rem !important;
        min-height: auto;
    }
    .ds-studio-body {
        padding: 0.75rem !important;
    }
    .ds-studio-header {
        padding: 0.75rem 1rem !important;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .ds-stage-switcher {
        width: 100%;
        justify-content: space-between;
    }
    .ds-stage-btn {
        flex: 1;
        text-align: center;
    }
}
</style>
