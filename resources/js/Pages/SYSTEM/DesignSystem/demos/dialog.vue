<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisDialog Enterprise Modal Studio</h3>
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
                    Dark
                </button>
                <button
                    type="button"
                    class="ds-stage-btn"
                    :class="{ 'is-active': studioStageBg === 'mesh' }"
                    @click="studioStageBg = 'mesh'"
                >
                    Mesh
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
                        :label="`Buka Dialog (${studioSentiment.toUpperCase()} · ${studioSize.toUpperCase()} · ${studioVariant.toUpperCase()})`"
                        :variant="studioSentiment === 'danger' ? 'danger' : studioSentiment === 'obsidian' ? 'obsidian' : 'primary'"
                        size="md"
                        leading-icon="window-stack"
                        @click="isDialogOpen = true"
                    />
                    <span class="text-xs text-slate-500 font-medium">
                        Klik tombol di atas untuk membuka modal dialog dengan konfigurasi aktif
                    </span>
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Kicker / Modul</label>
                    <input v-model="studioKicker" type="text" class="form-control form-control-sm" placeholder="e.g. MODUL EVALUASI" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Judul Dialog (Title)</label>
                    <input v-model="studioTitle" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="ds-control-label">Deskripsi Subtitle</label>
                    <input v-model="studioDesc" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Badge Label</label>
                    <input v-model="studioBadge" type="text" class="form-control form-control-sm" placeholder="e.g. Respon Cepat" />
                </div>

                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Ukuran (Size)</label>
                    <select v-model="studioSize" class="form-select form-select-sm">
                        <option value="xs">xs (Mini - 352px)</option>
                        <option value="sm">sm (Kecil - 416px)</option>
                        <option value="md">md (Sedang - 544px)</option>
                        <option value="lg">lg (Besar - 736px)</option>
                        <option value="xl">xl (Ekstra - 960px)</option>
                        <option value="2xl">2xl (Studio Grid - 1184px)</option>
                        <option value="full">full (Layar Penuh)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Sentiment Palet</label>
                    <select v-model="studioSentiment" class="form-select form-select-sm">
                        <option value="primary">primary / brand (Royal Sapphire)</option>
                        <option value="purple">purple / violet (Amethyst SOP)</option>
                        <option value="teal">teal / cyan (Mint Marine)</option>
                        <option value="success">success (Zamrud Emerald)</option>
                        <option value="warning">warning (Amber Gold)</option>
                        <option value="danger">danger (Coral Crimson)</option>
                        <option value="info">info (Sky Blue)</option>
                        <option value="neutral">neutral (Slate Platinum)</option>
                        <option value="obsidian">obsidian (Midnight Dark Crystal)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Varian Surface</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="default">default (Clean Crystal White)</option>
                        <option value="glass">glass (Frosted Translucent)</option>
                        <option value="hero">hero (Top Banner Accent)</option>
                        <option value="raised">raised (Dramatic Elevation)</option>
                        <option value="obsidian">obsidian (Dark Crystal Mode)</option>
                        <option value="flat">flat (Muted Soft)</option>
                        <option value="outlined">outlined (Border Grid)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Pola Signature (Pattern)</label>
                    <select v-model="studioPattern" class="form-select form-select-sm">
                        <option value="none">none (Tanpa Pola)</option>
                        <option value="grid">grid (Executive Blueprint Grid)</option>
                        <option value="dots">dots (Micro-Dot Matrix)</option>
                        <option value="crosshair">crosshair (Precision Crosshair)</option>
                        <option value="stripes">stripes (Diagonal Stripes)</option>
                        <option value="rings">rings (Concentric Orbit Rings)</option>
                        <option value="hex">hex (Honeycomb Hex)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Posisi Pola (Placement)</label>
                    <select v-model="studioPatternPlacement" class="form-select form-select-sm">
                        <option value="header">header (Header Saja - Standar)</option>
                        <option value="footer">footer (Footer Saja)</option>
                        <option value="both">both (Header & Footer)</option>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Wadah Ikon (Icon Variant)</label>
                    <select v-model="studioIconVariant" class="form-select form-select-sm">
                        <option value="glass">glass (Frosted Jewel)</option>
                        <option value="solid">solid (Penuh Warna)</option>
                        <option value="soft">soft (Muted Soft)</option>
                        <option value="bordered">bordered (Garis Transparan)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Animasi Ikon</label>
                    <select v-model="studioIconAnim" class="form-select form-select-sm">
                        <option value="">none (Statis)</option>
                        <option value="float">float (Mengambang)</option>
                        <option value="pulse">pulse (Detak Napas)</option>
                        <option value="spin">spin (Putar Cepat)</option>
                        <option value="gear">gear (Roda Gigi)</option>
                        <option value="ring">ring (Lonceng Berdering)</option>
                        <option value="sparkle">sparkle (Kilau Bintang)</option>
                        <option value="flip">flip (Balik Jam Pasir)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Penempatan (Placement)</label>
                    <select v-model="studioPlacement" class="form-select form-select-sm">
                        <option value="auto">auto (Responsive Auto)</option>
                        <option value="center">center (Selalu Tengah)</option>
                        <option value="top">top (Atas Layar)</option>
                        <option value="bottom">bottom (Bottom Sheet)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Perataan Footer</label>
                    <select v-model="studioFooterAlign" class="form-select form-select-sm">
                        <option value="end">end (Kanan - Standar)</option>
                        <option value="between">between (Rata Kiri & Kanan)</option>
                        <option value="start">start (Kiri)</option>
                        <option value="center">center (Tengah)</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="ds-control-label">Opsi Fitur & Interaktivitas</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-switch m-0">
                            <input id="dlg-load" v-model="studioLoading" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dlg-load">Loading Overlay</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="dlg-sticky" v-model="studioStickyFooter" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dlg-sticky">Sticky Footer Dock</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="dlg-btn-close" v-model="studioShowClose" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dlg-btn-close">Tombol X (Close)</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="dlg-close-overlay" v-model="studioCloseOnOverlay" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dlg-close-overlay">Tutup saat Klik Overlay</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="dlg-close-esc" v-model="studioCloseOnEsc" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dlg-close-esc">Tutup saat Tekan ESC</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="dlg-esc-hint" v-model="studioEscHint" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dlg-esc-hint">Capsule ESC Hint di Footer</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="dlg-badge-radar" v-model="studioBadgeRadar" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dlg-badge-radar">Badge Radar Ping</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="dlg-glow" v-model="studioGlow" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="dlg-glow">Ambient Halo Glow Aura</label>
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

        <!-- Interactive Dialog Instance for Studio -->
        <HcisDialog
            v-model="isDialogOpen"
            :kicker="studioKicker"
            :title="studioTitle"
            :description="studioDesc"
            :badge="studioBadge"
            :badge-radar="studioBadgeRadar"
            :size="studioSize"
            :sentiment="studioSentiment"
            :variant="studioVariant"
            :pattern="studioPattern"
            :pattern-placement="studioPatternPlacement"
            :glow="studioGlow"
            :icon-variant="studioIconVariant"
            :icon-animation="studioIconAnim"
            :placement="studioPlacement"
            :loading="studioLoading"
            :sticky-footer="studioStickyFooter"
            :footer-align="studioFooterAlign"
            :esc-hint="studioEscHint"
            :show-close="studioShowClose"
            :close-on-overlay="studioCloseOnOverlay"
            :close-on-esc="studioCloseOnEsc"
        >
            <div class="d-flex flex-column gap-3">
                <HcisAlert
                    v-if="studioSentiment === 'danger'"
                    variant="danger"
                    title="Perhatian Penting"
                    description="Tindakan ini akan mempengaruhi hak akses atau status data karyawan secara permanen."
                />
                <HcisField label="Nama Parameter / Judul Penugasan" required>
                    <input v-model="studioInputDemo" type="text" class="form-control form-control-sm" />
                </HcisField>
                <div v-if="studioStickyFooter" class="d-flex flex-column gap-2 text-xs text-slate-600">
                    <p class="fw-semibold text-slate-700 m-0">Contoh konten panjang untuk menguji docking sticky footer saat digulir:</p>
                    <div v-for="n in 6" :key="n" class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg">
                        <strong class="text-slate-800">Kriteria Evaluasi #{{ n }}</strong>
                        <p class="m-0 text-slate-500">Standar pencapaian output kerja dan kepatuhan operasional HCIS Enterprise Suite.</p>
                    </div>
                </div>
            </div>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" @click="isDialogOpen = false" />
                <HcisButton
                    :label="studioSentiment === 'danger' ? 'Ya, Konfirmasi' : 'Simpan Perubahan'"
                    :variant="studioSentiment === 'danger' ? 'danger' : studioSentiment === 'obsidian' ? 'obsidian' : 'primary'"
                    size="sm"
                    @click="isDialogOpen = false"
                />
            </template>
        </HcisDialog>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         2. ENTERPRISE SCENARIOS & IMPERATIVE MODALS
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'variants'" class="ds-card-demo-wrap">
        <!-- Imperative Modal Calls Section -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Modal Imperatif Tanpa Template Boilerplate (hcisDialog.confirm / alert / prompt):</span>
            <div class="d-flex flex-wrap gap-2.5 p-3 bg-white rounded-xl border border-slate-200 shadow-sm align-items-center">
                <HcisButton
                    label="hcisDialog.confirm() (Konfirmasi Tolak Cuti)"
                    variant="danger"
                    size="sm"
                    leading-icon="exclamation-triangle-fill"
                    @click="triggerImperativeConfirm"
                />
                <HcisButton
                    label="hcisDialog.alert() (Pemberitahuan Sistem)"
                    variant="primary"
                    size="sm"
                    leading-icon="info-circle-fill"
                    @click="triggerImperativeAlert"
                />
                <HcisButton
                    label="hcisDialog.prompt() (Input Catatan Cepat)"
                    variant="secondary"
                    size="sm"
                    leading-icon="pencil-square"
                    @click="triggerImperativePrompt"
                />
                <HcisButton
                    label="hcisDialog.confirm() (Obsidian Mode)"
                    variant="obsidian"
                    size="sm"
                    leading-icon="shield-lock-fill"
                    @click="triggerImperativeObsidian"
                />
            </div>
            <div v-if="imperativeResult" class="p-2.5 bg-indigo-50 border border-indigo-200 rounded-lg text-xs text-indigo-900 d-flex align-items-center gap-2">
                <HcisIcon name="info-circle" size="xs" />
                <span>Hasil Eksekusi Imperatif Terakhir: <strong>{{ imperativeResult }}</strong></span>
            </div>
        </div>

        <!-- Real-world Domain Scenarios -->
        <div class="ds-demo-group">
            <span class="ds-group-label">2. Skenario Modal Form Penyesuaian KPI & Simulasi Async Submission:</span>
            <div class="d-flex flex-wrap gap-2.5">
                <HcisButton label="Buka Modal Form Penyesuaian Bobot KPI (Glass + Pattern)" variant="primary" size="sm" @click="demoFormOpen = true">
                    <template #leading><HcisIcon name="sliders" size="xs" /></template>
                </HcisButton>
                <HcisButton label="Buka Modal Simulasi Async Loading" variant="secondary" size="sm" @click="demoAsyncOpen = true">
                    <template #leading><HcisIcon name="arrow-repeat" size="xs" /></template>
                </HcisButton>
                <HcisButton label="Buka Modal Dark Obsidian Security Access" variant="obsidian" size="sm" @click="demoObsidianOpen = true">
                    <template #leading><HcisIcon name="shield-lock-fill" size="xs" /></template>
                </HcisButton>
            </div>
        </div>

        <!-- Skenario 1: Form KPI -->
        <HcisDialog
            v-model="demoFormOpen"
            kicker="PERENCANAAN TALENTA"
            title="Formulir Penyesuaian Bobot KPI"
            description="Perbarui distribusi persentase bobot KPI kuartal berjalan"
            badge="Resmi"
            badge-radar
            size="md"
            sentiment="primary"
            variant="glass"
            pattern="grid"
            icon-animation="gear"
        >
            <div class="d-flex flex-column gap-3">
                <HcisField label="Nama Divisi Kerja">
                    <input type="text" class="form-control form-control-sm" value="Human Capital & Talent Development" readonly />
                </HcisField>
                <div class="row g-2">
                    <div class="col-6">
                        <HcisField label="Bobot Deliverable (%)" required>
                            <input type="number" class="form-control form-control-sm" value="60" />
                        </HcisField>
                    </div>
                    <div class="col-6">
                        <HcisField label="Bobot Behavioral (%)" required>
                            <input type="number" class="form-control form-control-sm" value="40" />
                        </HcisField>
                    </div>
                </div>
            </div>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" @click="demoFormOpen = false" />
                <HcisButton label="Simpan Distribusi" variant="primary" size="sm" @click="demoFormOpen = false" />
            </template>
        </HcisDialog>

        <!-- Skenario 2: Async Loading Submission -->
        <HcisDialog
            v-model="demoAsyncOpen"
            kicker="SINKRONISASI DATA"
            title="Sinkronisasi Master Karyawan"
            description="Mengirim data profil ke database server pusat"
            size="sm"
            sentiment="info"
            variant="hero"
            pattern="dots"
            :loading="demoAsyncLoading"
            loading-text="Menyinkronkan data profil..."
        >
            <p class="text-xs text-slate-600 m-0 leading-relaxed">
                Klik tombol <strong>Mulai Sinkronisasi</strong> di bawah untuk menguji animasi overlay pemrosesan laser-spinner tanpa menutup modal.
            </p>
            <template #footer>
                <HcisButton label="Tutup" variant="secondary" size="sm" :disabled="demoAsyncLoading" @click="demoAsyncOpen = false" />
                <HcisButton label="Mulai Sinkronisasi" variant="primary" size="sm" :disabled="demoAsyncLoading" @click="runAsyncSync" />
            </template>
        </HcisDialog>

        <!-- Skenario 3: Obsidian Dark Security Access -->
        <HcisDialog
            v-model="demoObsidianOpen"
            kicker="KEAMANAN OTORISASI"
            title="Persetujuan Hak Akses SuperAdmin"
            description="Tindakan ini memerlukan verifikasi ganda dan otorisasi level tinggi."
            badge="Sensitif"
            badge-variant="danger"
            size="sm"
            sentiment="obsidian"
            variant="obsidian"
            pattern="crosshair"
            icon="shield-lock-fill"
            icon-animation="pulse"
        >
            <p class="text-xs text-slate-300 m-0 leading-relaxed">
                Anda akan memberikan hak akses audit sistem penuh kepada pengguna bersangkutan. Seluruh riwayat perubahan akan dicatat dalam audit trail.
            </p>
            <template #footer>
                <HcisButton label="Batal" variant="ghost" size="sm" @click="demoObsidianOpen = false" />
                <HcisButton label="Otorisasi Akses" variant="danger" size="sm" @click="demoObsidianOpen = false" />
            </template>
        </HcisDialog>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <HcisAlert
            variant="info"
            title="Fokus & Aksesibilitas WCAG HcisDialog"
            description="Dialog secara otomatis mengunci scrolling halaman latar (scroll-lock), mengurung fokus keyboard (trap-focus), mendukung navigasi tombol ESC, dan menyediakan gestur drag-to-dismiss di layar seluler."
        />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisButton, HcisField, HcisDialog, HcisAlert, HcisIcon, hcisDialog } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const isDialogOpen = ref(false);
const studioKicker = ref('MODUL EVALUASI');
const studioTitle = ref('Ubah Parameter Evaluasi');
const studioDesc = ref('Perbarui bobot kuartal berjalan');
const studioBadge = ref('Aktif');
const studioBadgeRadar = ref(false);
const studioSize = ref('md');
const studioSentiment = ref('primary');
const studioVariant = ref('glass');
const studioPattern = ref('grid');
const studioPatternPlacement = ref('header');
const studioGlow = ref(false);
const studioIconVariant = ref('glass');
const studioIconAnim = ref('');
const studioPlacement = ref('auto');
const studioFooterAlign = ref('end');
const studioLoading = ref(false);
const studioStickyFooter = ref(false);
const studioEscHint = ref(true);
const studioShowClose = ref(true);
const studioCloseOnOverlay = ref(true);
const studioCloseOnEsc = ref(true);
const studioInputDemo = ref('KPI Semester II - 2026');
const copied = ref(false);

// Scenarios State
const demoFormOpen = ref(false);
const demoAsyncOpen = ref(false);
const demoAsyncLoading = ref(false);
const demoObsidianOpen = ref(false);
const imperativeResult = ref('');

async function triggerImperativeConfirm() {
    const result = await hcisDialog.confirm({
        title: 'Tolak Pengajuan Cuti?',
        description: 'Apakah Anda yakin ingin menolak permohonan cuti tahunan ini? Catatan penolakan akan dikirim ke karyawan bersangkutan.',
        sentiment: 'danger',
        pattern: 'dots',
        iconAnimation: 'pulse',
        confirmText: 'Ya, Tolak Permohonan',
        cancelText: 'Kembali',
    });
    imperativeResult.value = result ? 'Dikonfirmasi (true)' : 'Dibatalkan (false)';
}

async function triggerImperativeAlert() {
    await hcisDialog.alert({
        title: 'Batas Kuota Tercapai',
        description: 'Pengajuan lembur bulan ini telah mencapai 100% dari alokasi departemen.',
        sentiment: 'warning',
        pattern: 'grid',
        iconAnimation: 'ring',
        buttonText: 'Saya Mengerti',
    });
    imperativeResult.value = 'Alert diakui';
}

async function triggerImperativePrompt() {
    const input = await hcisDialog.prompt({
        title: 'Alasan Revisi KPI',
        description: 'Masukkan catatan perbaikan bobot deliverable:',
        placeholder: 'Contoh: Penyesuaian target revenue kuartal III...',
        sentiment: 'primary',
        pattern: 'crosshair',
    });
    imperativeResult.value = input !== null ? `Input tersimpan: "${input}"` : 'Prompt dibatalkan (null)';
}

async function triggerImperativeObsidian() {
    const result = await hcisDialog.confirm({
        title: 'Konfirmasi Otorisasi Root',
        description: 'Apakah Anda yakin ingin mengeksekusi sinkronisasi database kredensial?',
        sentiment: 'obsidian',
        variant: 'obsidian',
        pattern: 'hex',
        confirmText: 'Ya, Eksekusi',
        confirmVariant: 'danger',
    });
    imperativeResult.value = result ? 'Obsidian Dikonfirmasi (true)' : 'Obsidian Dibatalkan (false)';
}

function runAsyncSync() {
    demoAsyncLoading.value = true;
    setTimeout(() => {
        demoAsyncLoading.value = false;
        demoAsyncOpen.value = false;
    }, 2000);
}

// Computed Code Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`v-model="isOpen"`);
    if (studioKicker.value) props.push(`kicker="${studioKicker.value}"`);
    props.push(`title="${studioTitle.value}"`);
    if (studioDesc.value) props.push(`description="${studioDesc.value}"`);
    if (studioBadge.value) props.push(`badge="${studioBadge.value}"`);
    if (studioBadgeRadar.value) props.push(`badge-radar`);
    if (studioSize.value !== 'md') props.push(`size="${studioSize.value}"`);
    if (studioSentiment.value !== 'primary') props.push(`sentiment="${studioSentiment.value}"`);
    if (studioVariant.value !== 'default') props.push(`variant="${studioVariant.value}"`);
    if (studioPattern.value !== 'none') props.push(`pattern="${studioPattern.value}"`);
    if (studioPatternPlacement.value !== 'header') props.push(`pattern-placement="${studioPatternPlacement.value}"`);
    if (studioIconVariant.value !== 'boxed') props.push(`icon-variant="${studioIconVariant.value}"`);
    if (studioIconAnim.value) props.push(`icon-animation="${studioIconAnim.value}"`);
    if (studioFooterAlign.value !== 'right') props.push(`footer-align="${studioFooterAlign.value}"`);
    if (studioStickyFooter.value) props.push(`sticky-footer`);
    if (studioEscHint.value) props.push(`:esc-hint="true"`);
    if (studioLoading.value) props.push(`:loading="true"`);
    if (!studioShowClose.value) props.push(`:show-close="false"`);
    if (!studioCloseOnOverlay.value) props.push(`:close-on-overlay="false"`);

    return `<HcisDialog\n    ${props.join('\n    ')}\n>\n    <p>Konten isi modal dialog...</p>\n    <template #footer>\n        <HcisButton label="Batal" variant="secondary" size="sm" @click="isOpen = false" />\n        <HcisButton label="Simpan" variant="primary" size="sm" @click="isOpen = false" />\n    </template>\n</HcisDialog>`;
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
