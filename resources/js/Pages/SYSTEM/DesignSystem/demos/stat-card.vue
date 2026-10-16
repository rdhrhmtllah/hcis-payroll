<template>
    <!-- Section: Anatomy & Interactive Studio -->
    <div v-if="section === 'anatomy'" class="ds-stat-demo">
        <!-- Interactive Live Studio & Code Generator -->
        <div class="ds-card mb-4">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="sliders" size="sm" class="text-indigo-600 me-2" />
                    Interactive Stat Card Studio & Code Generator
                </span>
                <div class="d-flex align-items-center gap-2">
                    <span class="ds-meta-tag d-none d-sm-inline">Latar Belakang:</span>
                    <div class="btn-group btn-group-sm">
                        <button
                            type="button"
                            class="btn btn-sm"
                            :class="studioStageBg === 'light' ? 'btn-primary' : 'btn-outline-secondary'"
                            @click="studioStageBg = 'light'"
                        >
                            Light
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm"
                            :class="studioStageBg === 'dark' ? 'btn-primary' : 'btn-outline-secondary'"
                            @click="studioStageBg = 'dark'"
                        >
                            Dark 🌑
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm"
                            :class="studioStageBg === 'mesh' ? 'btn-primary' : 'btn-outline-secondary'"
                            @click="studioStageBg = 'mesh'"
                        >
                            Mesh 🔮
                        </button>
                    </div>
                </div>
            </div>
            <div class="ds-card__body">
                <!-- Live Preview Stage -->
                <div class="ds-studio-stage mb-4 d-flex justify-content-center" :class="`ds-stage-bg--${studioStageBg}`">
                    <div style="width: 100%; max-width: 24rem;">
                        <HcisStatCard
                            :label="studioLabel"
                            :value="studioValue"
                            :caption="studioCaption"
                            :layout="studioLayout"
                            :appearance="studioAppearance"
                            :sentiment="studioSentiment"
                            :pattern="studioPattern"
                            :prefix="studioPrefix"
                            :suffix="studioSuffix"
                            :delta="studioDelta"
                            :trend="studioTrend"
                            :progress="studioHasProgress ? studioProgress : null"
                            :target="studioHasProgress ? studioTarget : ''"
                            :icon="studioIcon"
                            :icon-animation="studioIconAnimation"
                            :loading="studioLoading"
                            :interactive="studioInteractive"
                            @click="handleStatClick"
                        />
                    </div>
                </div>

                <!-- Controls Grid -->
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="ds-control-label">Tata Letak (Layout)</label>
                        <select v-model="studioLayout" class="form-select form-select-sm">
                            <option value="default">Default (Vertikal Eksekutif)</option>
                            <option value="horizontal">Horizontal (Side-by-Side)</option>
                            <option value="compact">Compact (Mini Widget)</option>
                            <option value="hero">Hero (KPI Berdampak Tinggi)</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="ds-control-label">Tampilan (Appearance)</label>
                        <select v-model="studioAppearance" class="form-select form-select-sm">
                            <option value="crystal">Crystal Glassmorphism (Baku)</option>
                            <option value="solid">Gemstone Solid</option>
                            <option value="glass">Frosted Crystal Glass</option>
                            <option value="obsidian">Obsidian Dark Crystal</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="ds-control-label">Pola Latar (Pattern)</label>
                        <select v-model="studioPattern" class="form-select form-select-sm">
                            <option value="none">Polos (Tanpa Pola)</option>
                            <option value="dots">Titik-Titik (Dots Matrix)</option>
                            <option value="grid">Garis Grid (Blueprint Grid)</option>
                            <option value="crosshair">Crosshair Plus (+ Matrix)</option>
                            <option value="stripes">Arsir Diagonal (Stripes /)</option>
                            <option value="rings">Orbit Rings (Radar ◯)</option>
                            <option value="hex">Honeycomb (Hexagon ⬡)</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="ds-control-label">Sentimen Warna</label>
                        <select v-model="studioSentiment" class="form-select form-select-sm">
                            <option value="primary">Primary / Brand (Sapphire)</option>
                            <option value="success">Success / Good (Emerald)</option>
                            <option value="warning">Warning (Amber)</option>
                            <option value="danger">Danger / Bad (Crimson)</option>
                            <option value="info">Info (Sky)</option>
                            <option value="purple">Purple (Violet)</option>
                            <option value="teal">Teal (Toska)</option>
                            <option value="neutral">Neutral (Slate)</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="ds-control-label">Arah Tren (Trend)</label>
                        <select v-model="studioTrend" class="form-select form-select-sm">
                            <option value="">Tanpa Tren</option>
                            <option value="up">Naik (↑ Up)</option>
                            <option value="down">Turun (↓ Down)</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="ds-control-label">Label Metrik</label>
                        <input v-model="studioLabel" type="text" class="form-control form-control-sm" />
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="ds-control-label">Nilai Metrik (Value)</label>
                        <input v-model="studioValue" type="text" class="form-control form-control-sm" />
                    </div>

                    <div class="col-3 col-md-2">
                        <label class="ds-control-label">Prefix</label>
                        <input v-model="studioPrefix" type="text" placeholder="Rp" class="form-control form-control-sm" />
                    </div>

                    <div class="col-3 col-md-3">
                        <label class="ds-control-label">Suffix</label>
                        <input v-model="studioSuffix" type="text" placeholder="Org / %" class="form-control form-control-sm" />
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="ds-control-label">Teks Keterangan (Caption)</label>
                        <input v-model="studioCaption" type="text" class="form-control form-control-sm" />
                    </div>

                    <div class="col-6 col-md-4">
                        <label class="ds-control-label">Delta Nilai</label>
                        <input v-model="studioDelta" type="text" placeholder="12.5% bln ini" class="form-control form-control-sm" />
                    </div>

                    <div class="col-6 col-md-4">
                        <label class="ds-control-label">Ikon Wadah Permata</label>
                        <select v-model="studioIcon" class="form-select form-select-sm">
                            <option value="people-fill">people-fill</option>
                            <option value="check-circle-fill">check-circle-fill</option>
                            <option value="clock-history">clock-history</option>
                            <option value="shield-check">shield-check</option>
                            <option value="graph-up-arrow">graph-up-arrow</option>
                            <option value="currency-dollar">currency-dollar</option>
                            <option value="gear-fill">gear-fill</option>
                            <option value="exclamation-triangle-fill">exclamation-triangle-fill</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="ds-control-label">Animasi Ikon</label>
                        <select v-model="studioIconAnimation" class="form-select form-select-sm">
                            <option value="">Tanpa Gerak (Statis)</option>
                            <option value="float">Melayang Halus (Float)</option>
                            <option value="spin">Putaran Laser (Spin)</option>
                            <option value="gear">Rotasi Roda Gigi (Gear)</option>
                            <option value="pulse">Denyut Kejut (Pulse)</option>
                            <option value="ring">Lonceng Berdering (Ring)</option>
                            <option value="sparkle">Kilau Permata (Sparkle)</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-8 d-flex align-items-center gap-4 pt-2">
                        <div class="form-check form-switch">
                            <input id="sw-progress" v-model="studioHasProgress" class="form-check-input" type="checkbox" />
                            <label class="form-check-label small fw-semibold" for="sw-progress">Tampilkan Target Bar</label>
                        </div>
                        <div class="form-check form-switch">
                            <input id="sw-interactive" v-model="studioInteractive" class="form-check-input" type="checkbox" />
                            <label class="form-check-label small fw-semibold" for="sw-interactive">Interaktif (Klik + Angkat)</label>
                        </div>
                        <div class="form-check form-switch">
                            <input id="sw-loading" v-model="studioLoading" class="form-check-input" type="checkbox" />
                            <label class="form-check-label small fw-semibold" for="sw-loading">Status Memuat (Skeleton)</label>
                        </div>
                    </div>

                    <div v-if="studioHasProgress" class="col-12 col-md-6">
                        <label class="ds-control-label">Progress Capaian ({{ studioProgress }}%)</label>
                        <input v-model.number="studioProgress" type="range" min="0" max="100" class="form-range" />
                    </div>

                    <div v-if="studioHasProgress" class="col-12 col-md-6">
                        <label class="ds-control-label">Label Target</label>
                        <input v-model="studioTarget" type="text" placeholder="100.000 Org" class="form-control form-control-sm" />
                    </div>
                </div>

                <!-- Live Generated Code Box -->
                <div class="mt-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="ds-demo-label mb-0">Generated Vue Template Code:</span>
                        <button
                            type="button"
                            class="btn btn-link btn-sm text-decoration-none text-primary p-0"
                            @click="copyGeneratedCode"
                        >
                            <HcisIcon :name="copied ? 'check-lg' : 'clipboard'" size="xs" class="me-1" />
                            {{ copied ? 'Tersalin ke Clipboard!' : 'Salin Kode' }}
                        </button>
                    </div>
                    <pre class="ds-code-box"><code>{{ generatedCode }}</code></pre>
                </div>
            </div>
        </div>

        <!-- Layout Matrix Showcase -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="grid-1x2" size="sm" class="text-indigo-600 me-2" />
                    Matriks 4 Mode Tata Letak (Layouts)
                </span>
                <span class="ds-card__tag">default · horizontal · compact · hero</span>
            </div>
            <div class="ds-card__body d-flex flex-column gap-4">
                <div>
                    <span class="ds-demo-label">1. Default (Vertikal Eksekutif Standar):</span>
                    <div class="row g-3 mt-1">
                        <div class="col-12 col-md-4">
                            <HcisStatCard
                                label="Total Pegawai Aktif"
                                value="1,420"
                                suffix="Org"
                                delta="3.8%"
                                trend="up"
                                sentiment="primary"
                                icon="people-fill"
                                caption="Tersebar di 14 kantor cabang"
                            />
                        </div>
                        <div class="col-12 col-md-4">
                            <HcisStatCard
                                label="Tingkat Presensi"
                                value="98.5"
                                suffix="%"
                                delta="0.4%"
                                trend="up"
                                sentiment="success"
                                icon="check-circle-fill"
                                :progress="98.5"
                                target="Target 98.0%"
                                caption="Bulan Berjalan Agustus 2026"
                            />
                        </div>
                        <div class="col-12 col-md-4">
                            <HcisStatCard
                                label="Keterlambatan Masuk"
                                value="24"
                                suffix="Kasus"
                                delta="12%"
                                trend="up"
                                sentiment="danger"
                                icon="clock-history"
                                caption="Membutuhkan perhatian HR Admin"
                            />
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-top">
                    <span class="ds-demo-label">2. Horizontal (Side-by-Side untuk Baris Filter & Ringkasan Cepat):</span>
                    <div class="row g-3 mt-1">
                        <div class="col-12 col-md-6">
                            <HcisStatCard
                                layout="horizontal"
                                label="Anggaran Lembur Terserap"
                                value="342.5"
                                prefix="Rp"
                                suffix="Jt"
                                delta="8.2%"
                                trend="down"
                                sentiment="primary"
                                icon="currency-dollar"
                                caption="68% dari total pagu triwulan III"
                            />
                        </div>
                        <div class="col-12 col-md-6">
                            <HcisStatCard
                                layout="horizontal"
                                label="SOP Menunggu Peninjauan"
                                value="14"
                                suffix="Dokumen"
                                delta="2 baru"
                                trend="up"
                                sentiment="warning"
                                icon="file-earmark-text-fill"
                                caption="Batas waktu reviu 25 Agustus"
                            />
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-top">
                    <span class="ds-demo-label">3. Compact (Widget Mini untuk Sidebar & Panel Tumpukan Padat):</span>
                    <div class="row g-2 mt-1">
                        <div class="col-6 col-md-3">
                            <HcisStatCard
                                layout="compact"
                                label="Pending Cuti"
                                value="18"
                                delta="↑ 3"
                                sentiment="warning"
                                icon="clock-history"
                            />
                        </div>
                        <div class="col-6 col-md-3">
                            <HcisStatCard
                                layout="compact"
                                label="Disetujui"
                                value="94"
                                delta="↑ 12"
                                sentiment="success"
                                icon="check-circle-fill"
                            />
                        </div>
                        <div class="col-6 col-md-3">
                            <HcisStatCard
                                layout="compact"
                                label="Ditolak"
                                value="4"
                                delta="↓ 1"
                                sentiment="danger"
                                icon="x-circle-fill"
                            />
                        </div>
                        <div class="col-6 col-md-3">
                            <HcisStatCard
                                layout="compact"
                                label="Draf"
                                value="7"
                                sentiment="neutral"
                                icon="file-earmark-text"
                            />
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-top">
                    <span class="ds-demo-label">4. Hero KPI (Metrik Utama Berdampak Tinggi dengan Latar Belakang Kristal):</span>
                    <div class="row g-3 mt-1">
                        <div class="col-12 col-md-6">
                            <HcisStatCard
                                layout="hero"
                                appearance="crystal"
                                label="Indeks Kinerja Pegawai Nasional"
                                value="94.8"
                                suffix="/100"
                                delta="4.2 Poin"
                                trend="up"
                                sentiment="primary"
                                icon="graph-up-arrow"
                                icon-animation="float"
                                :progress="94.8"
                                target="Target 90.0 Poin"
                                caption="Evaluasi menyeluruh 1.420 pegawai di seluruh unit kerja HCIS"
                            />
                        </div>
                        <div class="col-12 col-md-6">
                            <HcisStatCard
                                layout="hero"
                                appearance="obsidian"
                                label="Skor Kepatuhan Audit SDM & ISO"
                                value="99.2"
                                suffix="%"
                                delta="Sempurna"
                                trend="up"
                                sentiment="success"
                                icon="shield-check"
                                icon-animation="sparkle"
                                :progress="99.2"
                                target="Standar Mutu 95%"
                                caption="Zero audit non-conformity findings per Semester I 2026"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Variants & Sentiment Hierarchy -->
    <div v-else-if="section === 'variants'" class="ds-stat-demo d-flex flex-column gap-4">
        <!-- 9 Sentiment Color Palette Grid -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="palette" size="sm" class="text-indigo-600 me-2" />
                    Hierarki 9 Rona Sentimen HCIS Enterprise
                </span>
                <span class="ds-card__tag">Jewel Box & Radial Glow</span>
            </div>
            <div class="ds-card__body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <HcisStatCard label="Primary / Brand" value="1,248" suffix="Karyawan" delta="4.5%" trend="up" sentiment="primary" icon="award-fill" caption="Sapphire Gemstone Glow" />
                    </div>
                    <div class="col-12 col-md-4">
                        <HcisStatCard label="Success / Good" value="98.7" suffix="%" delta="1.2%" trend="up" sentiment="success" icon="check-circle-fill" caption="Emerald Gemstone Glow" />
                    </div>
                    <div class="col-12 col-md-4">
                        <HcisStatCard label="Warning / Alert" value="28" suffix="Antrean" delta="5 baru" trend="up" sentiment="warning" icon="hourglass-split" caption="Amber Gemstone Glow" />
                    </div>
                    <div class="col-12 col-md-4">
                        <HcisStatCard label="Danger / Bad" value="14" suffix="Kasus" delta="2%" trend="up" sentiment="danger" icon="exclamation-octagon-fill" caption="Crimson Gemstone Glow" />
                    </div>
                    <div class="col-12 col-md-4">
                        <HcisStatCard label="Info / Sky" value="512" suffix="Tiket" delta="99%" trend="up" sentiment="info" icon="info-circle-fill" caption="Sky Luminescence Glow" />
                    </div>
                    <div class="col-12 col-md-4">
                        <HcisStatCard label="Purple / Violet" value="84" suffix="Inovasi" delta="18%" trend="up" sentiment="purple" icon="lightning-charge-fill" caption="Purple Crystal Glow" />
                    </div>
                    <div class="col-12 col-md-4">
                        <HcisStatCard label="Teal / Toska" value="43" suffix="Modul" delta="100%" trend="up" sentiment="teal" icon="boxes" caption="Teal Marine Glow" />
                    </div>
                    <div class="col-12 col-md-4">
                        <HcisStatCard label="Obsidian Dark" value="0" suffix="Insiden" delta="Nir-Bahaya" trend="up" sentiment="obsidian" icon="moon-stars-fill" caption="Executive Dark Crystal" />
                    </div>
                    <div class="col-12 col-md-4">
                        <HcisStatCard label="Neutral / Slate" value="1,840" suffix="Arsip" sentiment="neutral" icon="archive-fill" caption="Slate Surface Soft" />
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Visual Appearance Tiers -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="layers" size="sm" class="text-indigo-600 me-2" />
                    4 Tingkatan Penampilan Visual (Visual Appearance Tiers)
                </span>
                <span class="ds-card__tag">crystal · solid · glass · obsidian</span>
            </div>
            <div class="ds-card__body">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <HcisStatCard
                            appearance="crystal"
                            label="1. Crystal (Baku)"
                            value="84.2%"
                            sentiment="primary"
                            icon="gem"
                            caption="135° tint & crystal top sheen"
                        />
                    </div>
                    <div class="col-12 col-md-3">
                        <HcisStatCard
                            appearance="solid"
                            label="2. Gemstone Solid"
                            value="99.4%"
                            sentiment="primary"
                            icon="stars"
                            caption="Vivid high-contrast gradient"
                        />
                    </div>
                    <div class="col-12 col-md-3">
                        <HcisStatCard
                            appearance="glass"
                            label="3. Frosted Glass"
                            value="76.8%"
                            sentiment="primary"
                            icon="window"
                            caption="16px blur & glass refraction"
                        />
                    </div>
                    <div class="col-12 col-md-3">
                        <HcisStatCard
                            appearance="obsidian"
                            label="4. Obsidian Dark"
                            value="100%"
                            sentiment="primary"
                            icon="shield-lock-fill"
                            caption="Midnight executive crystal"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: States & Dashboard Stat Group -->
    <div v-else-if="section === 'states'" class="ds-stat-demo d-flex flex-column gap-4">
        <!-- Interactive Group & Trend Direction Matrix -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="grid-3x3-gap" size="sm" class="text-indigo-600 me-2" />
                    Grup Statistik Terintegrasi (HcisStatGroup Component)
                </span>
                <span class="ds-card__tag">cols="4" · gap="md"</span>
            </div>
            <div class="ds-card__body">
                <p class="text-muted small mb-3">
                    Gunakan <code>&lt;HcisStatGroup cols="4" gap="md" :stats="[...]" /&gt;</code> untuk menyusun baris metrik dashboard yang responsif dan otomatis tertata rapi:
                </p>
                <HcisStatGroup :stats="dashboardStats" cols="4" gap="md" @item-click="handleGroupItemClick" />
            </div>
        </div>

        <!-- Shimmer Skeleton Loading State -->
        <div class="ds-card">
            <div class="ds-card__header">
                <span class="ds-card__title">
                    <HcisIcon name="hourglass-split" size="sm" class="text-indigo-600 me-2" />
                    Status Memuat Shimmer Halus (Loading Skeleton State)
                </span>
                <span class="ds-card__tag">:loading="true"</span>
            </div>
            <div class="ds-card__body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <HcisStatCard label="Sinkronisasi Data Presensi" value="" loading icon="arrow-repeat" icon-animation="spin" />
                    </div>
                    <div class="col-12 col-md-4">
                        <HcisStatCard layout="horizontal" label="Menghitung Beban Kerja" value="" loading icon="gear-wide-connected" icon-animation="gear" />
                    </div>
                    <div class="col-12 col-md-4">
                        <HcisStatCard layout="compact" label="Kompilasi Laporan" value="" loading icon="clock-history" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisStatCard, HcisStatGroup, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Interactive Live Studio State
const studioStageBg = ref('light');
const studioLayout = ref('default');
const studioAppearance = ref('crystal');
const studioSentiment = ref('primary');
const studioPattern = ref('dots');
const studioLabel = ref('Total Pengajuan Cuti');
const studioValue = ref('128');
const studioPrefix = ref('');
const studioSuffix = ref('Dokumen');
const studioCaption = ref('Dibandingkan bulan lalu (114 pengajuan)');
const studioDelta = ref('12.5%');
const studioTrend = ref('up');
const studioIcon = ref('people-fill');
const studioIconAnimation = ref('float');
const studioHasProgress = ref(true);
const studioProgress = ref(78);
const studioTarget = ref('Target 150 Dokumen');
const studioInteractive = ref(true);
const studioLoading = ref(false);
const copied = ref(false);

const generatedCode = computed(() => {
    let code = `<HcisStatCard\n    label="${studioLabel.value}"\n    value="${studioValue.value}"`;

    if (studioLayout.value !== 'default') code += `\n    layout="${studioLayout.value}"`;
    if (studioAppearance.value !== 'crystal') code += `\n    appearance="${studioAppearance.value}"`;
    if (studioSentiment.value !== 'neutral') code += `\n    sentiment="${studioSentiment.value}"`;
    if (studioPattern.value !== 'none') code += `\n    pattern="${studioPattern.value}"`;
    if (studioPrefix.value) code += `\n    prefix="${studioPrefix.value}"`;
    if (studioSuffix.value) code += `\n    suffix="${studioSuffix.value}"`;
    if (studioDelta.value) code += `\n    delta="${studioDelta.value}"`;
    if (studioTrend.value) code += `\n    trend="${studioTrend.value}"`;
    if (studioIcon.value) code += `\n    icon="${studioIcon.value}"`;
    if (studioIconAnimation.value) code += `\n    icon-animation="${studioIconAnimation.value}"`;
    if (studioCaption.value) code += `\n    caption="${studioCaption.value}"`;
    if (studioHasProgress.value) {
        code += `\n    :progress="${studioProgress.value}"`;
        if (studioTarget.value) code += `\n    target="${studioTarget.value}"`;
    }
    if (studioInteractive.value) code += `\n    interactive`;
    if (studioLoading.value) code += `\n    loading`;

    code += `\n/>`;
    return code;
});

function copyGeneratedCode() {
    navigator.clipboard.writeText(generatedCode.value);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}

function handleStatClick() {
    // Feedback for interactive click
}

// Group Mockup Data
const dashboardStats = ref([
    {
        label: 'Total Pegawai',
        value: '1,420',
        suffix: 'Org',
        trend: 'up',
        trendValue: '3.8%',
        sentiment: 'primary',
        icon: 'people-fill',
        caption: 'Tersebar di 14 cabang',
        clickable: true,
    },
    {
        label: 'Tingkat Kehadiran',
        value: '98.5',
        suffix: '%',
        trend: 'up',
        trendValue: '0.4%',
        sentiment: 'success',
        icon: 'check-circle-fill',
        progress: 98.5,
        target: '98.0%',
        caption: 'Bulan Agustus 2026',
        clickable: true,
    },
    {
        label: 'Keterlambatan',
        value: '24',
        suffix: 'Kasus',
        trend: 'up',
        trendValue: '12%',
        sentiment: 'danger',
        icon: 'clock-history',
        caption: 'Perlu verifikasi HR',
        clickable: true,
    },
    {
        label: 'Pagu Lembur',
        value: '68',
        suffix: '%',
        trend: 'down',
        trendValue: '2.1%',
        sentiment: 'warning',
        icon: 'currency-dollar',
        progress: 68,
        target: 'Max 80%',
        caption: 'Terserap Triwulan III',
        clickable: true,
    },
]);

function handleGroupItemClick({ item }) {
    // Group click feedback
}
</script>

<style scoped>
.ds-stat-demo {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.ds-card {
    background: var(--hcis-surface, var(--hcis-white));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.04);
    overflow: hidden;
}

.ds-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 250, 252, 0.7) 100%);
}

.ds-card__title {
    font-weight: 750;
    font-size: 0.95rem;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    display: flex;
    align-items: center;
}

.ds-card__tag {
    font-size: 0.75rem;
    font-weight: 700;
    font-family: ui-monospace, Consolas, monospace;
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    background: var(--hcis-surface-soft, var(--hcis-slate-100));
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.ds-card__body {
    padding: 1.25rem;
}

.ds-studio-stage {
    min-height: 180px;
    padding: 2.5rem 1.5rem;
    border-radius: var(--hcis-radius-lg, 12px);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px dashed var(--hcis-border-medium, var(--hcis-slate-300));
    transition: background 300ms ease;
}

.ds-stage-bg--light {
    background: radial-gradient(circle, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
}

.ds-stage-bg--dark {
    background: radial-gradient(circle, var(--hcis-slate-800) 0%, var(--hcis-slate-900) 100%);
}

.ds-stage-bg--mesh {
    background: radial-gradient(at 0% 0%, rgb(99 102 241 / 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgb(168 85 247 / 0.15) 0px, transparent 50%),
                var(--hcis-slate-50);
}

.ds-control-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 750;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    margin-bottom: 0.35rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.ds-demo-label {
    display: block;
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    margin-bottom: 0.5rem;
}

.ds-meta-tag {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.ds-code-box {
    background: var(--hcis-slate-900);
    color: var(--hcis-slate-50);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 1rem;
    font-size: 0.8125rem;
    line-height: 1.5;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    overflow-x: auto;
    margin: 0;
}
</style>
