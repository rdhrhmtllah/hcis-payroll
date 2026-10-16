<template>
    <!-- ═══════════════════════════════════════════════════════════════════════
         1. ANATOMY & INTERACTIVE CONFIGURATOR STUDIO
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-if="section === 'anatomy' || section === 'all'" class="ds-studio-panel">
        <div class="ds-studio-header">
            <div class="d-flex align-items-center gap-2">
                <span class="ds-studio-badge">Live Studio</span>
                <h3 class="ds-studio-title">HcisChart ECharts Interactive Studio</h3>
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
                <div style="width: 100%; max-width: 640px;">
                    <HcisChart
                        :type="studioType"
                        :title="studioTitle"
                        :kicker="studioKicker"
                        :description="studioDesc"
                        :badge="studioBadge"
                        :badge-radar="studioBadgeRadar"
                        :icon="studioIcon"
                        :sentiment="studioSentiment"
                        :variant="studioVariant"
                        :pattern="studioPattern"
                        :categories="activeCategories"
                        :data="activeData"
                        :series="activeSeries"
                        :metric-value="studioShowMetric ? studioMetricValue : ''"
                        :metric-unit="studioMetricUnit"
                        :metric-label="studioMetricLabel"
                        :metric-trend="studioMetricTrend"
                        :metric-trend-direction="studioMetricTrendDir"
                        :insight="studioShowInsight ? studioInsight : ''"
                        :target-value="studioShowTarget ? studioTargetValue : null"
                        :target-label="studioTargetLabel"
                        :time-filters="studioShowTimeFilters ? ['7H', '30H', '3B', '1T'] : []"
                        :allow-table-toggle="studioAllowTable"
                        :allow-export="studioAllowExport"
                        :data-zoom="studioDataZoom"
                        :loading="studioLoading"
                        :smooth="studioSmooth"
                        :card="studioCard"
                        :height="320"
                    />
                </div>
            </div>

            <!-- Studio Controls Grid -->
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Jenis Grafik (Type)</label>
                    <select v-model="studioType" class="form-select form-select-sm">
                        <option value="area">area (Smooth Gradient Area)</option>
                        <option value="line">line (Multi-Series Line)</option>
                        <option value="bar">bar (Rounded Column Bar)</option>
                        <option value="bar-horizontal">bar-horizontal (Leaderboard)</option>
                        <option value="heatmap">heatmap (Rounded Activity Grid)</option>
                        <option value="donut">donut (Ring Donut Ratio)</option>
                        <option value="radar">radar (Competency Matrix)</option>
                        <option value="gauge">gauge (KPI Speedometer)</option>
                        <option value="funnel">funnel (Recruitment Pipeline)</option>
                        <option value="scatter">scatter (9-Box Talent Grid)</option>
                        <option value="treemap">treemap (Budget & Org Matrix)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Sentimen Warna</label>
                    <select v-model="studioSentiment" class="form-select form-select-sm">
                        <option value="brand">brand (Royal Sapphire)</option>
                        <option value="purple">purple (Amethyst SOP)</option>
                        <option value="teal">teal (Mint SOP)</option>
                        <option value="success">success (Emerald)</option>
                        <option value="warning">warning (Amber)</option>
                        <option value="danger">danger (Crimson)</option>
                        <option value="multi">multi (Enterprise Rainbow)</option>
                        <option value="obsidian">obsidian (Midnight Crystal)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Varian Permukaan</label>
                    <select v-model="studioVariant" class="form-select form-select-sm">
                        <option value="glass">glass (Signature Frosted)</option>
                        <option value="default">default (Clean Surface)</option>
                        <option value="raised">raised (Deep Elevation)</option>
                        <option value="flat">flat (Muted Soft)</option>
                        <option value="outlined">outlined (Border Grid)</option>
                        <option value="obsidian">obsidian (Dark Crystal)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="ds-control-label">Pola Signature (Pattern)</label>
                    <select v-model="studioPattern" class="form-select form-select-sm">
                        <option value="none">none (Tanpa Pola)</option>
                        <option value="grid">grid (Blueprint Grid)</option>
                        <option value="dots">dots (Micro-Dot Matrix)</option>
                        <option value="crosshair">crosshair (Crosshair Matrix)</option>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="ds-control-label">Judul Grafik</label>
                    <input v-model="studioTitle" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-4">
                    <label class="ds-control-label">Teks Kicker</label>
                    <input v-model="studioKicker" type="text" class="form-control form-control-sm" />
                </div>
                <div class="col-6 col-md-4">
                    <label class="ds-control-label">Ikon Header</label>
                    <input v-model="studioIcon" type="text" class="form-control form-control-sm" />
                </div>

                <div class="col-12">
                    <label class="ds-control-label">Fitur Eksekutif & Interaktivitas Cerdas</label>
                    <div class="d-flex flex-wrap gap-3 pt-1">
                        <div class="form-check form-switch m-0">
                            <input id="ch-insight" v-model="studioShowInsight" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ch-insight">Insight Capsule</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ch-metric" v-model="studioShowMetric" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ch-metric">Banner Ringkasan KPI</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ch-table" v-model="studioAllowTable" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ch-table">Toggle Tabel Data</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ch-exp" v-model="studioAllowExport" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ch-exp">Ekspor Gambar PNG</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ch-target" v-model="studioShowTarget" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ch-target">Garis Target Threshold</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ch-zoom" v-model="studioDataZoom" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ch-zoom">Data Zoom Slider</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ch-tf" v-model="studioShowTimeFilters" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ch-tf">Filter Waktu (Time Chips)</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ch-load" v-model="studioLoading" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ch-load">Loading Shimmer</label>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input id="ch-card" v-model="studioCard" class="form-check-input" type="checkbox" />
                            <label class="form-check-label text-xs fw-semibold" for="ch-card">Card Wrapper</label>
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
         2. ENTERPRISE SCENARIOS & DASHBOARD PRESETS
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'variants'" class="ds-card-demo-wrap">
        <!-- Showcase 1: Executive KPI Command Center with Insights -->
        <div class="row g-3">
            <div class="col-12">
                <HcisChart
                    type="area"
                    kicker="RINGKASAN STRATEGIS HCIS"
                    title="Tren Indeks Kinerja Karyawan & Baseline Target Tahunan"
                    description="Evaluasi metrik produktivitas bulanan seluruh direktorat perusahaan"
                    badge="Executive View"
                    icon="graph-up-arrow"
                    sentiment="brand"
                    variant="glass"
                    pattern="grid"
                    :categories="['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des']"
                    :series="[
                        { name: 'Realisasi 2026', data: [74, 78, 82, 85, 89, 92, 95, 98, 97, 99, 102, 105] },
                        { name: 'Target Baseline (2025)', data: [68, 70, 72, 75, 78, 80, 81, 83, 85, 87, 88, 90] }
                    ]"
                    metric-value="105.4"
                    metric-unit="Poin Indeks"
                    metric-label="Pencapaian tertinggi tahun berjalan"
                    metric-trend="+16.8% YoY"
                    metric-trend-direction="up"
                    insight="Insight: Divisi Operasional & IT mencatatkan akselerasi deliverable tertinggi pasca penerapan workflow otomatisasi."
                    :target-value="95"
                    target-label="Target Minimum SLA"
                    :time-filters="['1B', '3B', '6B', '1T']"
                    allow-table-toggle
                    allow-export
                    data-zoom
                    :height="300"
                />
            </div>
        </div>

        <!-- Showcase 2: Rounded Matrix Heatmap (Aktivitas Kehadiran & Shift Kerja) -->
        <div class="row g-3">
            <div class="col-12">
                <HcisChart
                    type="heatmap"
                    kicker="MATRIKS KEHADIRAN & LOG AKTIVITAS"
                    title="Intensitas Jam Kerja & Log Check-in Seluruh Karyawan (Rounded Cells)"
                    description="Distribusi kehadiran per jam kerja pada hari Senin s.d. Jumat"
                    badge="Heatmap 2D"
                    icon="calendar2-week-fill"
                    sentiment="brand"
                    variant="glass"
                    pattern="dots"
                    :categories="['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00']"
                    :series="[{ categories: ['Jumat', 'Kamis', 'Rabu', 'Selasa', 'Senin'] }]"
                    :data="heatmapDemoData"
                    metric-value="98.7%"
                    metric-unit="Tingkat Kehadiran"
                    metric-label="Puncak kehadiran terpadat pada pukul 08:30 - 10:00 WIB"
                    metric-trend="Tepat Waktu"
                    metric-trend-direction="up"
                    insight="Insight: Pola jam kerja menunjukkan puncak efektivitas harian terjadi antara pukul 09:00 - 11:30 WIB."
                    allow-export
                    :height="260"
                />
            </div>
        </div>

        <!-- Showcase 3: Recruitment Pipeline Funnel & 9-Box Talent Matrix -->
        <div class="row g-3">
            <!-- 3A. Recruitment Funnel -->
            <div class="col-12 col-lg-6">
                <HcisChart
                    type="funnel"
                    kicker="PIPELINE REKRUTMEN TALENTA"
                    title="Konversi Rekrutmen Management Trainee"
                    description="Tingkat kelulusan tiap tahapan seleksi"
                    icon="funnel-fill"
                    sentiment="teal"
                    variant="glass"
                    pattern="crosshair"
                    :data="[
                        { value: 1250, name: '1. Berkas Masuk' },
                        { value: 680, name: '2. Tes Psikometri' },
                        { value: 240, name: '3. Wawancara HR & User' },
                        { value: 75, name: '4. Panel Direksi' },
                        { value: 32, name: '5. Offering Diterima' }
                    ]"
                    metric-value="2.56%"
                    metric-unit="Conversion Rate"
                    metric-label="Kandidat terpilih dari 1.250 pendaftar"
                    metric-trend="Kualitas Top 3%"
                    allow-table-toggle
                    allow-export
                    :height="300"
                />
            </div>

            <!-- 3B. 9-Box Talent Assessment Matrix -->
            <div class="col-12 col-lg-6">
                <HcisChart
                    type="scatter"
                    kicker="MAPPING TALENTA 9-BOX"
                    title="Matriks Kinerja vs Potensi Kepemimpinan"
                    description="Pemetaan kuadran suksesi talenta tier 1 & tier 2"
                    badge="Assessment 360"
                    icon="grid-3x3-gap-fill"
                    sentiment="purple"
                    variant="glass"
                    pattern="grid"
                    :data="[
                        [88, 92, 'Budi Santoso (VP Product)', 24],
                        [95, 88, 'Siti Rahma (Lead Architect)', 22],
                        [78, 85, 'Ahmad Fauzi (Head of Sales)', 18],
                        [82, 76, 'Dewi Lestari (Talent Partner)', 16],
                        [92, 95, 'Rian Hidayat (Head of AI)', 26],
                        [65, 70, 'Indra Pratama (Senior Analyst)', 14],
                        [86, 68, 'Maya Kusuma (Finance Lead)', 16],
                        [74, 90, 'Farhan Alamsyah (Design Lead)', 20]
                    ]"
                    insight="Insight: 5 dari 8 kandidat berada pada kuadran 'Star & High Potential' siap suksesi jabatan struktural."
                    allow-export
                    :height="300"
                />
            </div>
        </div>

        <!-- Showcase 4: Horizontal Leaderboard & Treemap Budget Hierarchy -->
        <div class="row g-3">
            <!-- 4A. Horizontal Leaderboard -->
            <div class="col-12 col-lg-6">
                <HcisChart
                    type="bar-horizontal"
                    kicker="LEADERBOARD PRODUKTIVITAS"
                    title="Peringkat Capaian Kinerja per Divisi"
                    description="Skor rata-rata pemenuhan target deliverable"
                    icon="trophy-fill"
                    sentiment="brand"
                    variant="glass"
                    pattern="dots"
                    :categories="['IT & Digital', 'Human Capital', 'Pemasaran & Bisnis', 'Operasional', 'Keuangan & Akuntansi', 'Legal & Kepatuhan']"
                    :data="[98.4, 96.2, 92.8, 89.5, 87.1, 84.6]"
                    metric-value="98.4"
                    metric-unit="Poin (IT & Digital)"
                    metric-label="Divisi dengan performa terbaik bulan ini"
                    metric-trend="Juara 1 🥇"
                    allow-table-toggle
                    allow-export
                    :height="280"
                />
            </div>

            <!-- 4B. Treemap Budget Allocation -->
            <div class="col-12 col-lg-6">
                <HcisChart
                    type="treemap"
                    kicker="ALOKASI ANGGARAN SDM"
                    title="Distribusi Anggaran Pengembangan Talenta"
                    description="Komposisi belanja program per kategori kerja"
                    icon="diagram-3-fill"
                    sentiment="multi"
                    variant="glass"
                    pattern="grid"
                    :data="[
                        { name: 'Sertifikasi Profesi & IT', value: 450 },
                        { name: 'Executive Leadership Program', value: 380 },
                        { name: 'Wellness & Benefit Karyawan', value: 290 },
                        { name: 'Onboarding & Induction', value: 160 },
                        { name: 'Inhouse Training & Workshop', value: 220 }
                    ]"
                    metric-value="Rp 1.50 M"
                    metric-unit="Total Anggaran"
                    metric-label="Realisasi belanja 78.4% sesuai rencana"
                    allow-table-toggle
                    allow-export
                    :height="280"
                />
            </div>
        </div>

        <!-- Showcase 5: Donut Composition, Radar Competency & Gauge KPI -->
        <div class="row g-3">
            <!-- 5A. Donut Ring Ratio -->
            <div class="col-12 col-md-4">
                <HcisChart
                    type="donut"
                    kicker="DEMOGRAFI TALENTA"
                    title="Tingkat Pendidikan"
                    description="Distribusi jenjang lulusan"
                    icon="pie-chart-fill"
                    sentiment="purple"
                    variant="glass"
                    pattern="crosshair"
                    :data="[
                        { name: 'Sarjana (S1)', value: 210 },
                        { name: 'Magister (S2)', value: 74 },
                        { name: 'Diploma (D3)', value: 65 },
                        { name: 'Doktor (S3)', value: 25 }
                    ]"
                    metric-label="Karyawan"
                    allow-export
                    :height="260"
                />
            </div>

            <!-- 5B. Radar Leadership Matrix -->
            <div class="col-12 col-md-4">
                <HcisChart
                    type="radar"
                    kicker="ASSESMEN 360"
                    title="Pilar Kepemimpinan"
                    description="Evaluasi manajerial"
                    badge="SOP"
                    icon="award-fill"
                    sentiment="primary"
                    variant="glass"
                    pattern="grid"
                    :categories="['Inovasi', 'Eksekusi', 'Kolaborasi', 'Leadership', 'Agility', 'Integritas']"
                    :series="[
                        { name: 'Realisasi 2026', data: [88, 92, 85, 94, 90, 96] },
                        { name: 'Benchmark', data: [75, 80, 75, 85, 80, 85] }
                    ]"
                    allow-export
                    :height="260"
                />
            </div>

            <!-- 5C. KPI Speedometer Gauge -->
            <div class="col-12 col-md-4">
                <HcisChart
                    type="gauge"
                    kicker="SKOR CAPAIAN"
                    title="Pemenuhan Target OKR"
                    description="Persentase pemenuhan KPI"
                    icon="speedometer2"
                    sentiment="success"
                    variant="glass"
                    pattern="dots"
                    :data="94.6"
                    metric-label="Sangat Memuaskan"
                    allow-export
                    :height="260"
                />
            </div>
        </div>

        <!-- Showcase 6: Obsidian Midnight Executive NOC Dashboard -->
        <div class="row g-3">
            <div class="col-12">
                <HcisChart
                    type="area"
                    kicker="EXECUTIVE OBSIDIAN DASHBOARD"
                    title="Real-time Server Event Throughput & Gateway API Latency"
                    description="Pemantauan beban sinkronisasi gateway database pusat secara terdistribusi"
                    badge="Audit Sistem"
                    badge-variant="danger"
                    icon="shield-lock-fill"
                    sentiment="obsidian"
                    variant="obsidian"
                    pattern="crosshair"
                    :categories="['00:00', '03:00', '06:00', '09:00', '12:00', '15:00', '18:00', '21:00']"
                    :series="[
                        { name: 'API Requests (k/s)', data: [12, 18, 45, 110, 142, 128, 95, 42] },
                        { name: 'Database Sync (ops)', data: [5, 8, 22, 64, 85, 78, 52, 20] }
                    ]"
                    metric-value="142.4k"
                    metric-unit="ops/dtk"
                    metric-label="Puncak beban pukul 12:00 WIB"
                    metric-trend="99.98% Uptime"
                    metric-trend-direction="up"
                    insight="Insight: Seluruh pipeline sinkronisasi dalam kondisi optimal tanpa ada request timeout."
                    :time-filters="['1J', '6J', '24J', '7H']"
                    allow-table-toggle
                    allow-export
                    :height="280"
                />
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. FORM VALIDATION & LIFECYCLE STATES
         ═══════════════════════════════════════════════════════════════════════ -->
    <div v-else-if="section === 'states'" class="ds-card-demo-wrap">
        <div class="row g-3">
            <!-- Loading Shimmer State -->
            <div class="col-12 col-md-6">
                <HcisChart
                    type="area"
                    title="Memuat Metrik Real-time"
                    description="Menyinkronkan data dengan server analitik pusat"
                    loading
                    loading-text="Menghitung agregasi data kuartal berjalan..."
                    :height="240"
                />
            </div>

            <!-- Empty No-Data State -->
            <div class="col-12 col-md-6">
                <HcisChart
                    type="bar"
                    title="Data Evaluasi Belum Ada"
                    description="Belum ada periode evaluasi yang diarsipkan"
                    :data="[]"
                    empty-text="Silakan buka periode evaluasi baru untuk memulai visualisasi"
                    :height="240"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisChart, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Studio State
const studioStageBg = ref('light');
const studioType = ref('area');
const studioSentiment = ref('brand');
const studioVariant = ref('glass');
const studioPattern = ref('grid');
const studioTitle = ref('Perkembangan Skor Evaluasi KPI');
const studioKicker = ref('ANALISIS KINERJA');
const studioDesc = ref('Rata-rata pencapaian deliverable kuartal berjalan');
const studioBadge = ref('Aktif');
const studioBadgeRadar = ref(true);
const studioIcon = ref('graph-up-arrow');

// Metric Banner State
const studioShowMetric = ref(true);
const studioMetricValue = ref('94.8%');
const studioMetricUnit = ref('Pencapaian');
const studioMetricLabel = ref('Melampaui target kuartal sebelumnya (+4.2%)');
const studioMetricTrend = ref('+8.4% YoY');
const studioMetricTrendDir = ref('up');

// Insight & Threshold Target
const studioShowInsight = ref(true);
const studioInsight = ref('Insight: Skor rata-rata kuartal ini mengalami peningkatan konsisten berkat implementasi milestone mingguan.');
const studioShowTarget = ref(true);
const studioTargetValue = ref(90);
const studioTargetLabel = ref('Target Minimum');

// Controls
const studioShowTimeFilters = ref(true);
const studioAllowTable = ref(true);
const studioAllowExport = ref(true);
const studioDataZoom = ref(false);
const studioLoading = ref(false);
const studioCard = ref(true);
const studioSmooth = ref(true);
const copied = ref(false);

// Active Categories based on Type
const activeCategories = computed(() => {
    if (studioType.value === 'radar') {
        return ['Inovasi', 'Eksekusi', 'Kolaborasi', 'Kepemimpinan', 'Ketangkasan', 'Integritas'];
    }
    if (studioType.value === 'bar-horizontal') {
        return ['Divisi IT', 'Human Capital', 'Marketing', 'Operations', 'Finance', 'Legal'];
    }
    if (studioType.value === 'heatmap') {
        return ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'];
    }
    return ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt'];
});

// Active Data based on Type
const activeData = computed(() => {
    if (studioType.value === 'donut') {
        return [
            { name: 'Sarjana (S1)', value: 180 },
            { name: 'Magister (S2)', value: 65 },
            { name: 'Diploma (D3)', value: 45 },
            { name: 'Doktor (S3)', value: 15 },
        ];
    }
    if (studioType.value === 'funnel') {
        return [
            { value: 1200, name: '1. Berkas Masuk' },
            { value: 650, name: '2. Tes Psikometri' },
            { value: 210, name: '3. Wawancara HR' },
            { value: 65, name: '4. Panel Direksi' },
            { value: 28, name: '5. Offering Diterima' },
        ];
    }
    if (studioType.value === 'scatter') {
        return [
            [88, 92, 'Budi Santoso', 24],
            [95, 88, 'Siti Rahma', 22],
            [78, 85, 'Ahmad Fauzi', 18],
            [82, 76, 'Dewi Lestari', 16],
            [92, 95, 'Rian Hidayat', 26],
        ];
    }
    if (studioType.value === 'treemap') {
        return [
            { name: 'Sertifikasi Profesi', value: 450 },
            { name: 'Leadership Program', value: 380 },
            { name: 'Wellness Karyawan', value: 290 },
            { name: 'Workshop Inhouse', value: 220 },
        ];
    }
    if (studioType.value === 'heatmap') {
        const hData = [];
        const hours = 6;
        const days = 5;
        for (let i = 0; i < hours; i++) {
            for (let j = 0; j < days; j++) {
                hData.push([i, j, ((i + 1) * (j + 1) * 7) % 50 + 10]);
            }
        }
        return hData;
    }
    if (studioType.value === 'gauge') {
        return 94.8;
    }
    if (studioType.value === 'radar') {
        return [85, 92, 78, 90, 88, 95];
    }
    if (studioType.value === 'bar') {
        return [45, 68, 85, 94, 88, 72, 96, 110];
    }
    if (studioType.value === 'bar-horizontal') {
        return [98.4, 96.2, 92.8, 89.5, 87.1, 84.6];
    }
    return [65, 72, 78, 85, 89, 92, 95, 98];
});

const activeSeries = computed(() => {
    if (studioType.value === 'line') {
        return [
            { name: 'Realisasi 2026', data: [65, 72, 78, 85, 89, 92, 95, 98] },
            { name: 'Target Baseline', data: [60, 65, 70, 75, 80, 85, 88, 90] },
        ];
    }
    if (studioType.value === 'heatmap') {
        return [{ categories: ['Jumat', 'Kamis', 'Rabu', 'Selasa', 'Senin'] }];
    }
    return [];
});

// Heatmap Matrix Data (Senin s.d. Jumat, 10 jam kerja)
const heatmapDemoData = ref([
    [0, 0, 85], [1, 0, 92], [2, 0, 78], [3, 0, 65], [4, 0, 20], [5, 0, 45], [6, 0, 80], [7, 0, 88], [8, 0, 75], [9, 0, 30],
    [0, 1, 90], [1, 1, 95], [2, 1, 88], [3, 1, 70], [4, 1, 25], [5, 1, 50], [6, 1, 85], [7, 1, 92], [8, 1, 80], [9, 1, 40],
    [0, 2, 88], [1, 2, 94], [2, 2, 82], [3, 2, 68], [4, 2, 30], [5, 2, 55], [6, 2, 82], [7, 2, 90], [8, 2, 78], [9, 2, 35],
    [0, 3, 92], [1, 3, 98], [2, 3, 85], [3, 3, 72], [4, 3, 22], [5, 3, 48], [6, 3, 88], [7, 3, 94], [8, 3, 82], [9, 3, 42],
    [0, 4, 80], [1, 4, 88], [2, 4, 75], [3, 4, 60], [4, 4, 18], [5, 4, 40], [6, 4, 72], [7, 4, 80], [8, 4, 65], [9, 4, 20],
]);

// Code Snippet Generator
const generatedSnippet = computed(() => {
    const props = [];
    props.push(`type="${studioType.value}"`);
    if (studioKicker.value) props.push(`kicker="${studioKicker.value}"`);
    props.push(`title="${studioTitle.value}"`);
    if (studioDesc.value) props.push(`description="${studioDesc.value}"`);
    if (studioBadge.value) props.push(`badge="${studioBadge.value}"`);
    if (studioBadgeRadar.value) props.push(`badge-radar`);
    if (studioIcon.value) props.push(`icon="${studioIcon.value}"`);
    if (studioSentiment.value !== 'brand') props.push(`sentiment="${studioSentiment.value}"`);
    if (studioVariant.value !== 'glass') props.push(`variant="${studioVariant.value}"`);
    if (studioPattern.value !== 'none') props.push(`pattern="${studioPattern.value}"`);
    props.push(`:categories="['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt']"`);
    props.push(`:data="[65, 72, 78, 85, 89, 92, 95, 98]"`);
    if (studioShowMetric.value) {
        props.push(`metric-value="${studioMetricValue.value}"`);
        props.push(`metric-unit="${studioMetricUnit.value}"`);
        props.push(`metric-trend="${studioMetricTrend.value}"`);
    }
    if (studioShowInsight.value) {
        props.push(`insight="${studioInsight.value}"`);
    }
    if (studioShowTarget.value) {
        props.push(`:target-value="${studioTargetValue.value}"`);
    }
    if (studioShowTimeFilters.value) {
        props.push(`:time-filters="['7H', '30H', '3B', '1T']"`);
    }
    if (studioAllowTable.value) props.push(`allow-table-toggle`);
    if (studioAllowExport.value) props.push(`allow-export`);
    if (studioDataZoom.value) props.push(`data-zoom`);
    if (!studioCard.value) props.push(`:card="false"`);
    if (studioLoading.value) props.push(`loading`);
    return `<HcisChart\n    ${props.join('\n    ')}\n/>`;
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
