<template>
    <div
        class="hcis-chart-wrapper"
        :class="[
            `hcis-chart-wrapper--sentiment-${sentiment}`,
            `hcis-chart-wrapper--variant-${variant}`,
            { 'hcis-chart-wrapper--standalone': !card }
        ]"
    >
        <!-- Mode 1: Terbungkus HcisCard Suite Eksekutif -->
        <HcisCard
            v-if="card"
            :title="title"
            :kicker="kicker"
            :description="description"
            :badge="badge"
            :badge-variant="badgeVariant"
            :badge-radar="badgeRadar"
            :icon="icon"
            :sentiment="sentiment"
            :variant="variant"
            :pattern="pattern"
            :pattern-placement="patternPlacement"
        >
            <!-- Slot Header Aksi: Time Filter Chips, View Switcher & Snapshot Export -->
            <template #actions>
                <div class="hcis-chart__header-tools">
                    <!-- Time Filter Chips -->
                    <div
                        v-if="timeFilters && timeFilters.length > 0 && currentView === 'chart'"
                        class="hcis-chart__time-chips"
                        role="group"
                        aria-label="Filter Rentang Waktu"
                    >
                        <button
                            v-for="tf in timeFilters"
                            :key="tf.value || tf"
                            type="button"
                            class="hcis-chart__time-chip"
                            :class="{ 'hcis-chart__time-chip--active': activeTimeFilter === (tf.value || tf) }"
                            @click="handleTimeFilterChange(tf.value || tf)"
                        >
                            {{ tf.label || tf }}
                        </button>
                    </div>

                    <!-- View Mode Toggle (Chart vs Data Table) -->
                    <div v-if="allowTableToggle" class="hcis-chart__view-toggle">
                        <button
                            type="button"
                            class="hcis-chart__tool-btn"
                            :class="{ 'hcis-chart__tool-btn--active': currentView === 'chart' }"
                            title="Tampilan Grafik Visual"
                            @click="currentView = 'chart'"
                        >
                            <HcisIcon name="graph-up" size="xs" />
                        </button>
                        <button
                            type="button"
                            class="hcis-chart__tool-btn"
                            :class="{ 'hcis-chart__tool-btn--active': currentView === 'table' }"
                            title="Tampilan Tabel Data"
                            @click="currentView = 'table'"
                        >
                            <HcisIcon name="table" size="xs" />
                        </button>
                    </div>

                    <!-- Export Snapshot Image Button -->
                    <button
                        v-if="allowExport && currentView === 'chart'"
                        type="button"
                        class="hcis-chart__tool-btn hcis-chart__tool-btn--export"
                        title="Unduh Gambar Resolusi Tinggi (PNG)"
                        aria-label="Unduh Gambar"
                        @click="exportImage"
                    >
                        <HcisIcon name="download" size="xs" />
                    </button>
                </div>
            </template>

            <!-- Stat Summary KPI Metric Row & AI Insight Capsule -->
            <div v-if="metricValue || insight || $slots.metric" class="hcis-chart__top-banner">
                <!-- KPI Metric Main -->
                <div v-if="metricValue" class="hcis-chart__metric-banner">
                    <div class="hcis-chart__metric-main">
                        <span class="hcis-chart__metric-value">{{ metricValue }}</span>
                        <span v-if="metricUnit" class="hcis-chart__metric-unit">{{ metricUnit }}</span>
                        <span
                            v-if="metricTrend"
                            class="hcis-chart__metric-trend"
                            :class="`hcis-chart__metric-trend--${metricTrendDirection || 'up'}`"
                        >
                            <HcisIcon
                                :name="metricTrendDirection === 'down' ? 'arrow-down-right' : 'arrow-up-right'"
                                size="2xs"
                            />
                            {{ metricTrend }}
                        </span>
                    </div>
                    <span v-if="metricLabel" class="hcis-chart__metric-label">{{ metricLabel }}</span>
                </div>

                <!-- Insight Smart Capsule -->
                <div v-if="insight" class="hcis-chart__ai-capsule">
                    <div class="hcis-chart__ai-badge">
                        <HcisIcon name="stars" size="xs" />
                        <span>{{ insightLabel || 'Insight' }}</span>
                    </div>
                    <p class="hcis-chart__ai-text">{{ insight }}</p>
                </div>
            </div>

            <!-- VIEW 1: ECHARTS CANVAS VISUALIZATION -->
            <div
                v-show="currentView === 'chart'"
                ref="chartContainerRef"
                class="hcis-chart__canvas-container"
                :style="{ height: formattedHeight }"
            >
                <div ref="chartDomRef" class="hcis-chart__dom" />

                <!-- State: Loading Skeleton -->
                <div v-if="loading" class="hcis-chart__overlay hcis-chart__overlay--loading">
                    <div class="hcis-chart__loading-skeleton-box">
                        <HcisSkeleton type="image" rounded="lg" animation="shimmer" width="100%" height="100%" />
                        <div class="hcis-chart__loading-badge">
                            <div class="hcis-chart__spinner" />
                            <span class="hcis-chart__loading-text">{{ loadingText || 'Memproses data visualisasi...' }}</span>
                        </div>
                    </div>
                </div>

                <!-- State: Empty State Component -->
                <div v-else-if="isEmpty" class="hcis-chart__overlay hcis-chart__overlay--empty">
                    <HcisEmptyState
                        :title="emptyText || 'Belum Ada Data Visualisasi'"
                        description="Tidak ada rekaman data analitik yang cocok untuk ditampilkan."
                        icon="bar-chart-line"
                        size="sm"
                        variant="flat"
                        sentiment="neutral"
                    />
                </div>
            </div>

            <!-- VIEW 2: DATA TABLE ACCESSIBILITY VIEW -->
            <div
                v-if="allowTableToggle && currentView === 'table'"
                class="hcis-chart__table-view"
                :style="{ maxHeight: formattedHeight }"
            >
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 40%;">Kategori / Dimensi</th>
                            <th
                                v-for="(col, cIdx) in computedTableColumns"
                                :key="cIdx"
                                scope="col"
                                class="text-end"
                            >
                                {{ col }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, rIdx) in computedTableRows" :key="rIdx">
                            <td class="fw-semibold text-slate-800">{{ row.category }}</td>
                            <td
                                v-for="(val, vIdx) in row.values"
                                :key="vIdx"
                                class="text-end font-monospace text-slate-700"
                            >
                                {{ val }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Custom Card Footer (Jika Ada Slot) -->
            <template v-if="$slots.footer" #footer>
                <slot name="footer" />
            </template>
        </HcisCard>

        <!-- Mode 2: Standalone Canvas (Tanpa Card Wrapper) -->
        <div
            v-else
            ref="chartContainerRef"
            class="hcis-chart__canvas-container"
            :style="{ height: formattedHeight }"
        >
            <div ref="chartDomRef" class="hcis-chart__dom" />

            <!-- State: Loading Skeleton -->
            <div v-if="loading" class="hcis-chart__overlay hcis-chart__overlay--loading">
                <div class="hcis-chart__loading-skeleton-box">
                    <HcisSkeleton type="image" rounded="lg" animation="shimmer" width="100%" height="100%" />
                    <div class="hcis-chart__loading-badge">
                        <div class="hcis-chart__spinner" />
                        <span class="hcis-chart__loading-text">{{ loadingText || 'Memuat data...' }}</span>
                    </div>
                </div>
            </div>

            <!-- State: Empty State Component -->
            <div v-else-if="isEmpty" class="hcis-chart__overlay hcis-chart__overlay--empty">
                <HcisEmptyState
                    :title="emptyText || 'Belum Ada Data'"
                    description="Tidak ada rekaman data untuk ditampilkan."
                    icon="bar-chart-line"
                    size="sm"
                    variant="flat"
                    sentiment="neutral"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import * as echarts from 'echarts';
import HcisCard from './HcisCard.vue';
import HcisIcon from './HcisIcon.vue';
import HcisEmptyState from './HcisEmptyState.vue';
import HcisSkeleton from './HcisSkeleton.vue';

const props = defineProps({
    // Type & Options
    type: {
        type: String,
        default: 'area',
        validator: (val) => [
            'area',
            'line',
            'bar',
            'bar-horizontal',
            'donut',
            'pie',
            'radar',
            'gauge',
            'funnel',
            'heatmap',
            'treemap',
            'scatter',
            'custom',
        ].includes(val),
    },
    options: { type: Object, default: () => ({}) },
    data: { type: [Array, Object, Number], default: () => [] },
    categories: { type: Array, default: () => [] },
    series: { type: Array, default: () => [] },
    height: { type: [Number, String], default: 320 },
    renderer: { type: String, default: 'canvas', validator: (val) => ['canvas', 'svg'].includes(val) },

    // Tooltip Customization
    tooltipFormatter: { type: [Function, String], default: null },
    showTooltip: { type: Boolean, default: true },

    // Threshold & Target Lines
    targetValue: { type: Number, default: null },
    targetLabel: { type: String, default: 'Target' },
    /**
     * Heatmap: nama besaran yang diukur dan satuannya, dipakai tooltip bawaan.
     *
     * Dulu tooltip heatmap menulis "Jumlah Kedatangan: N Tamu" secara mati --
     * kalimat milik satu pemakai (peta jam sibuk tamu) yang ikut terbawa ke
     * SETIAP heatmap lain, termasuk peta capaian KPI dan peta aktivitas SOP.
     */
    valueLabel: { type: String, default: 'Nilai' },
    valueSuffix: { type: String, default: '' },
    /**
     * Isi sumbu X heatmap. 'jam' membuat label dipadankan jadi HH:00 dan judul
     * tooltip berbunyi "Pukul HH:00 WIB"; kosong (baku) memakai label apa adanya,
     * karena sumbu X tidak selalu jam -- pada peta capaian KPI isinya bulan.
     */
    categoryUnit: { type: String, default: '', validator: (v) => ['', 'jam'].includes(v) },

    // Card Wrapper Props
    card: { type: Boolean, default: true },
    title: { type: String, default: '' },
    kicker: { type: String, default: '' },
    description: { type: String, default: '' },
    badge: { type: String, default: '' },
    badgeVariant: { type: String, default: '' },
    badgeRadar: { type: Boolean, default: false },
    icon: { type: String, default: '' },

    // Styling & Theme
    sentiment: {
        type: String,
        default: 'brand',
        validator: (val) => ['brand', 'primary', 'purple', 'violet', 'teal', 'cyan', 'success', 'warning', 'danger', 'info', 'obsidian', 'neutral', 'multi'].includes(val),
    },
    variant: {
        type: String,
        default: 'glass',
        validator: (val) => ['glass', 'default', 'flat', 'raised', 'outlined', 'obsidian'].includes(val),
    },
    pattern: {
        type: String,
        default: 'none',
        validator: (val) => ['none', 'grid', 'dots', 'crosshair', 'stripes', 'rings', 'hex'].includes(val),
    },
    patternPlacement: { type: String, default: 'header' },

    // Interactive Metric Summary Banner & Insight
    metricValue: { type: [String, Number], default: '' },
    metricUnit: { type: String, default: '' },
    metricLabel: { type: String, default: '' },
    metricTrend: { type: String, default: '' },
    metricTrendDirection: { type: String, default: 'up', validator: (val) => ['up', 'down', 'neutral'].includes(val) },
    insight: { type: String, default: '' },
    insightLabel: { type: String, default: 'Insight' },

    // Filter Chips & View Tools
    timeFilters: { type: Array, default: () => [] },
    modelValueFilter: { type: [String, Number], default: '' },
    allowTableToggle: { type: Boolean, default: false },
    allowExport: { type: Boolean, default: false },
    dataZoom: { type: Boolean, default: false },

    // States & Customizations
    loading: { type: Boolean, default: false },
    loadingText: { type: String, default: '' },
    emptyText: { type: String, default: '' },
    smooth: { type: Boolean, default: true },
    stacked: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValueFilter', 'filter-change', 'chart-click', 'chart-ready']);

const chartContainerRef = ref(null);
const chartDomRef = ref(null);
const currentView = ref('chart');
let chartInstance = null;
let resizeObserver = null;

const activeTimeFilter = ref(props.modelValueFilter || (props.timeFilters.length > 0 ? (props.timeFilters[0].value || props.timeFilters[0]) : ''));

const handleTimeFilterChange = (val) => {
    activeTimeFilter.value = val;
    emit('update:modelValueFilter', val);
    emit('filter-change', val);
};

const exportImage = () => {
    if (!chartInstance) return;
    const url = chartInstance.getDataURL({
        type: 'png',
        pixelRatio: 2,
        backgroundColor: props.variant === 'obsidian' ? '#0f172a' : '#ffffff',
    });
    const a = document.createElement('a');
    a.href = url;
    a.download = `${props.title ? props.title.toLowerCase().replace(/\s+/g, '-') : 'hcis-chart'}-${Date.now()}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
};

const formattedHeight = computed(() => {
    if (typeof props.height === 'number') return `${props.height}px`;
    if (typeof props.height === 'string') {
        const trimmed = props.height.trim();
        if (/^\d+(\.\d+)?$/.test(trimmed)) return `${trimmed}px`;
        return trimmed;
    }
    return '320px';
});

const isEmpty = computed(() => {
    if (props.type === 'custom') {
        return !props.options || Object.keys(props.options).length === 0;
    }
    if (props.type === 'radar') {
        if (props.series && props.series.length > 0) {
            const hasData = props.series.some((s) => (Array.isArray(s.data) && s.data.length > 0) || (Array.isArray(s.value) && s.value.length > 0));
            return !hasData;
        }
        if (Array.isArray(props.data)) return props.data.length === 0;
        return false;
    }
    if (props.type === 'heatmap' || props.type === 'donut' || props.type === 'pie' || props.type === 'gauge' || props.type === 'funnel' || props.type === 'treemap') {
        if (Array.isArray(props.data)) return props.data.length === 0;
    }
    if (props.series && props.series.length > 0) {
        if (props.series.some((s) => Array.isArray(s.data) || Array.isArray(s.value))) {
            const hasData = props.series.some((s) => (Array.isArray(s.data) && s.data.length > 0) || (Array.isArray(s.value) && s.value.length > 0));
            return !hasData;
        }
    }
    if (Array.isArray(props.data)) {
        return props.data.length === 0;
    }
    return false;
});

// Table Accessibility View Computed
const computedTableColumns = computed(() => {
    if (props.series && props.series.length > 0) {
        return props.series.map((s) => s.name || 'Nilai');
    }
    return ['Nilai'];
});

const computedTableRows = computed(() => {
    if (props.type === 'donut' || props.type === 'pie' || props.type === 'funnel') {
        const d = Array.isArray(props.data) ? props.data : [];
        return d.map((item) => ({
            category: item.name || 'Item',
            values: [item.value],
        }));
    }

    if (props.categories && props.categories.length > 0) {
        return props.categories.map((cat, idx) => {
            const rowValues = [];
            if (props.series && props.series.length > 0) {
                props.series.forEach((s) => {
                    rowValues.push(s.data?.[idx] ?? '-');
                });
            } else if (Array.isArray(props.data)) {
                rowValues.push(props.data[idx] ?? '-');
            }
            return {
                category: typeof cat === 'string' ? cat : cat.name || `Baris ${idx + 1}`,
                values: rowValues,
            };
        });
    }

    return [];
});

// Palette & Color Tokens
const PALETTES = {
    brand: ['#4f46e5', '#6366f1', '#818cf8', '#a5b4fc', '#c7d2fe'],
    primary: ['#4f46e5', '#6366f1', '#818cf8', '#a5b4fc', '#c7d2fe'],
    purple: ['#7c3aed', '#8b5cf6', '#a78bfa', '#c4b5fd', '#ddd6fe'],
    violet: ['#7c3aed', '#8b5cf6', '#a78bfa', '#c4b5fd', '#ddd6fe'],
    teal: ['#0d9488', '#14b8a6', '#2dd4bf', '#5eead4', '#99f6e4'],
    cyan: ['#0d9488', '#14b8a6', '#2dd4bf', '#5eead4', '#99f6e4'],
    success: ['#059669', '#10b981', '#34d399', '#6ee7b7', '#a7f3d0'],
    warning: ['#d97706', '#f59e0b', '#fbbf24', '#fcd34d', '#fef3c7'],
    danger: ['#dc2626', '#ef4444', '#f87171', '#fca5a5', '#fee2e2'],
    info: ['#0284c7', '#0ea5e9', '#38bdf8', '#7dd3fc', '#bae6fd'],
    obsidian: ['#818cf8', '#38bdf8', '#34d399', '#f43f5e', '#fbbf24', '#a855f7'],
    neutral: ['#475569', '#64748b', '#94a3b8', '#cbd5e1', '#e2e8f0'],
    multi: ['#4f46e5', '#0d9488', '#7c3aed', '#0284c7', '#10b981', '#f59e0b', '#dc2626'],
};

const getThemeColors = () => {
    return PALETTES[props.sentiment] || PALETTES.brand;
};

/**
 * Preset yang MENYERAP `options.series` sendiri ke dalam seri bawaannya.
 *
 * Menggabungkannya sekali lagi di penggabungan akhir akan menimpa seluruh gaya
 * preset itu — donat kehilangan lubangnya, gauge kehilangan jarumnya — jadi
 * `series` sengaja ditahan untuk tipe-tipe ini.
 */
const PRESET_MENYERAP_SERIES = ['donut', 'pie', 'gauge', 'funnel', 'heatmap', 'treemap', 'radar'];

/** Objek biasa (bukan array, bukan instans kelas seperti LinearGradient). */
const objekBiasa = (v) => Object.prototype.toString.call(v) === '[object Object]';

/**
 * Gabung DALAM: kunci bersarang menyatu, tidak saling menelan.
 *
 * Dulu opsi pemanggil dipasang dengan spread dangkal (`...props.options`).
 * Akibatnya siapa pun yang cuma ingin mengubah `yAxis.axisLabel.formatter`
 * kehilangan SELURUH sumbu bawaan — garis, tik, splitLine, warna label — dan
 * `{ tooltip: { valueFormatter } }` menghapus formatter beserta seluruh gaya
 * tooltip milik sistem desain. Grafiknya tetap tergambar, hanya kehilangan
 * bahasa visualnya, sehingga cacat ini tak pernah terbaca sebagai galat.
 *
 * Array TIDAK digabung per indeks tapi diganti utuh: `data: [1, 2, 3]` yang
 * ditimpa `[4, 5]` harus jadi `[4, 5]`, bukan `[4, 5, 3]`.
 */
const gabungDalam = (dasar, timpa) => {
    if (!objekBiasa(timpa)) return timpa === undefined ? dasar : timpa;
    if (!objekBiasa(dasar)) return { ...timpa };

    const hasil = { ...dasar };
    for (const [kunci, nilai] of Object.entries(timpa)) {
        hasil[kunci] = objekBiasa(nilai) && objekBiasa(dasar[kunci])
            ? gabungDalam(dasar[kunci], nilai)
            : nilai;
    }

    return hasil;
};

// ECharts Preset Builders
const bangunPreset = () => {
    if (props.type === 'custom') {
        return props.options;
    }

    const colors = getThemeColors();
    const primaryColor = colors[0];
    const isDark = props.variant === 'obsidian';

    const baseGrid = {
        top: 14,
        right: 14,
        bottom: props.dataZoom ? 38 : 14,
        left: 10,
        containLabel: true,
    };

    const baseTooltip = {
        show: props.showTooltip,
        trigger: 'axis',
        confine: true,
        backgroundColor: isDark ? 'rgba(15, 23, 42, 0.96)' : 'rgba(255, 255, 255, 0.97)',
        borderColor: isDark ? 'rgba(255, 255, 255, 0.14)' : 'rgba(226, 232, 240, 0.95)',
        borderWidth: 1,
        padding: [10, 14],
        textStyle: {
            color: isDark ? '#f8fafc' : '#0f172a',
            fontSize: 12,
            fontFamily: 'inherit',
        },
        extraCssText: 'box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.18), 0 0 0 1px rgba(255, 255, 255, 0.9) inset; border-radius: 12px; backdrop-filter: blur(14px); z-index: 999999;',
    };

    const baseAxisLabel = {
        color: isDark ? '#94a3b8' : '#64748b',
        fontSize: 11,
        fontFamily: 'inherit',
        fontWeight: 500,
        hideOverlap: true,
    };

    const baseSplitLine = {
        lineStyle: {
            color: isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(226, 232, 240, 0.65)',
            type: 'dashed',
        },
    };

    const targetMarkLine = props.targetValue !== null ? {
        symbol: ['none', 'none'],
        data: [
            {
                yAxis: props.targetValue,
                lineStyle: {
                    color: '#f59e0b',
                    type: 'dashed',
                    width: 2,
                    shadowColor: 'rgba(245, 158, 11, 0.4)',
                    shadowBlur: 6,
                },
                label: {
                    show: true,
                    formatter: `${props.targetLabel}: ${props.targetValue}`,
                    position: 'end',
                    color: isDark ? '#fbbf24' : '#d97706',
                    fontSize: 11,
                    fontWeight: 600,
                },
            },
        ],
    } : undefined;

    const dataZoomConfig = props.dataZoom ? [
        {
            type: 'slider',
            show: true,
            bottom: 4,
            height: 16,
            borderColor: 'transparent',
            backgroundColor: isDark ? 'rgba(30, 41, 59, 0.5)' : '#f1f5f9',
            fillerColor: isDark ? 'rgba(99, 102, 241, 0.3)' : 'rgba(99, 102, 241, 0.2)',
            handleStyle: { color: primaryColor, borderWidth: 0 },
            textStyle: { color: 'transparent' },
        },
    ] : undefined;

    /* ---------- SERI CAMPURAN ----------

       Sebuah seri boleh menyebut `type`-nya sendiri, dan grafik kartesian akan
       menghormatinya: garis di atas batang, batang di antara garis. Sebelumnya
       tipe per-seri memang lolos (spread `...s` di akhir menimpa `type`), tapi
       seri itu tetap mewarisi SELURUH dekorasi batang — pita latar
       `showBackground`, `barMaxWidth`, dan gradasi vertikal pada `itemStyle`.
       Hasilnya sebuah garis yang digambar di atas pita abu-abu, dengan simpul
       berwarna gradasi dan tanpa lebar garis: tergambar, tapi berantakan.

       Tipe seri ditentukan SEKALI di sini, lalu dekorasinya menyusul tipe itu. */

    /* ---------- LEGENDA ----------

       Grafik kartesian sebelumnya tidak punya legenda sama sekali — hanya donat
       dan pai yang punya. Satu seri memang tak memerlukannya (judul kartu sudah
       menamainya), tapi begitu ada dua seri atau lebih, pembaca tak punya cara
       apa pun mengetahui warna mana milik siapa.

       Ikonnya sengaja TIDAK dipaksa: ECharts memilih sendiri persegi untuk
       batang dan garis-bersimpul untuk garis, sehingga legendanya sekaligus
       memberi tahu seri mana yang digambar sebagai garis. Memaksa 'roundRect'
       juga akan menampilkan kotak PUTIH untuk seri garis, karena simpul garis
       memang berisi putih dengan tepi berwarna. */

    const adaLegenda = props.series.length > 1;

    const baseLegend = adaLegenda
        ? {
              show: true,
              top: 0,
              left: 0,
              itemGap: 18,
              textStyle: {
                  color: isDark ? '#cbd5e1' : '#475569',
                  fontSize: 11,
                  fontWeight: 600,
                  fontFamily: 'inherit',
              },
          }
        : undefined;

    // Legenda menempati baris paling atas; tanpa menurunkan grid, batang
    // tertinggi akan menembusnya.
    const gridLegenda = { ...baseGrid, top: adaLegenda ? 38 : baseGrid.top };

    const adaSeriGaris = props.series.some((s) => s.type === 'line' || s.type === 'area');

    const seriGaris = (s, idx, sColor, pakaiArea) => ({
        name: s.name,
        type: 'line',
        smooth: props.smooth,
        data: s.data,
        showSymbol: true,
        symbol: 'circle',
        symbolSize: 6,
        // Garis selalu di atas batang. Tanpa ini ECharts menumpuk menurut urutan
        // seri, dan garis pembanding hilang di balik batang yang lebih tinggi.
        z: 3,
        lineStyle: {
            width: 3.5,
            color: sColor,
            shadowColor: `${sColor}55`,
            shadowBlur: 14,
            shadowOffsetY: 4,
        },
        itemStyle: {
            color: isDark ? '#0f172a' : '#ffffff',
            borderColor: sColor,
            borderWidth: 2.5,
            shadowColor: `${sColor}80`,
            shadowBlur: 6,
        },
        emphasis: {
            scale: 1.6,
            itemStyle: {
                color: sColor,
                borderColor: '#ffffff',
                borderWidth: 3.5,
                shadowBlur: 14,
                shadowColor: `${sColor}80`,
            },
        },
        areaStyle: pakaiArea
            ? {
                  color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                      { offset: 0, color: `${sColor}45` },
                      { offset: 0.4, color: `${sColor}18` },
                      { offset: 0.8, color: `${sColor}06` },
                      { offset: 1, color: `${sColor}00` },
                  ]),
              }
            : undefined,
        markLine: idx === 0 ? targetMarkLine : undefined,
        ...s,
    });

    const seriBatang = (s, idx, sColor) => {
        const defaultBorderRadius = props.stacked
            ? [0, 0, 0, 0]
            : (Array.isArray(s.data) && s.data.some((v) => Number(v) < 0)
                ? [8, 8, 8, 8]
                : [8, 8, 0, 0]);

        return {
            name: s.name,
            type: 'bar',
            stack: props.stacked ? 'total' : undefined,
            data: s.data,
            barMaxWidth: 26,
            showBackground: !adaSeriGaris,
            backgroundStyle: {
                color: isDark ? 'rgba(255, 255, 255, 0.03)' : 'rgba(241, 245, 249, 0.75)',
                borderRadius: props.stacked ? [0, 0, 0, 0] : [8, 8, 0, 0],
            },
            emphasis: {
                itemStyle: {
                    shadowBlur: 14,
                    shadowColor: `${sColor}70`,
                },
                ...s.emphasis,
            },
            markLine: idx === 0 ? targetMarkLine : undefined,
            ...s,
            itemStyle: {
                borderRadius: s.itemStyle?.borderRadius || defaultBorderRadius,
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: sColor },
                    { offset: 0.8, color: `${sColor}cc` },
                    { offset: 1, color: `${sColor}77` },
                ]),
                shadowColor: `${sColor}35`,
                shadowBlur: 8,
                shadowOffsetY: 3,
                ...s.itemStyle,
                borderRadius: s.itemStyle?.borderRadius || defaultBorderRadius,
            },
        };
    };

    /** Bentuk satu seri menurut tipe yang IA sebut, bukan tipe grafiknya. */
    const bangunSeri = (s, idx, tipeBawaan) => {
        const sColor = colors[idx % colors.length];
        const tipe = s.type || tipeBawaan;

        if (tipe === 'line' || tipe === 'area') return seriGaris(s, idx, sColor, tipe === 'area');

        return seriBatang(s, idx, sColor);
    };

    // 1. ULTRA-PREMIUM NEON AREA & LINE SPLINE CHARTS
    if (props.type === 'area' || props.type === 'line') {
        const isArea = props.type === 'area';
        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Nilai', data: props.data }];

        return {
            color: colors,
            grid: gridLegenda,
            legend: baseLegend,
            tooltip: {
                ...baseTooltip,
                axisPointer: {
                    type: 'line',
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.25)' : 'rgba(99, 102, 241, 0.4)',
                        width: 1.5,
                        type: 'dashed',
                    },
                },
                formatter: props.tooltipFormatter || ((params) => {
                    if (!Array.isArray(params) || params.length === 0) return '';
                    let html = `<div style="font-weight: 750; margin-bottom: 6px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${params[0].axisValueLabel || params[0].name}</div>`;
                    params.forEach((item) => {
                        const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${item.color};"></span>`;
                        const val = typeof item.value === 'number' ? item.value.toLocaleString('id-ID') : item.value;
                        const unit = props.metricUnit ? ` ${props.metricUnit}` : '';
                        html += `<div style="display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-top: 3px; font-size: 11.5px;">
                            <span>${dot}${item.seriesName}</span>
                            <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${val}${unit}</strong>
                        </div>`;
                    });
                    return html;
                }),
            },
            dataZoom: dataZoomConfig,
            xAxis: {
                type: 'category',
                data: props.categories,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
            },
            yAxis: {
                type: 'value',
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            series: rawSeries.map((s, idx) => bangunSeri(s, idx, isArea ? 'area' : 'line')),
        };
    }

    // 2. ULTRA-PREMIUM PILL-CAP VERTICAL BAR CHART
    if (props.type === 'bar') {
        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Total', data: props.data }];

        return {
            color: colors,
            grid: gridLegenda,
            legend: baseLegend,
            tooltip: {
                ...baseTooltip,
                formatter: props.tooltipFormatter || ((params) => {
                    if (!Array.isArray(params) || params.length === 0) return '';
                    let html = `<div style="font-weight: 750; margin-bottom: 6px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${params[0].axisValueLabel || params[0].name}</div>`;
                    params.forEach((item) => {
                        const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${item.color};"></span>`;
                        const val = typeof item.value === 'number' ? item.value.toLocaleString('id-ID') : item.value;
                        const unit = props.metricUnit ? ` ${props.metricUnit}` : '';
                        html += `<div style="display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-top: 3px; font-size: 11.5px;">
                            <span>${dot}${item.seriesName}</span>
                            <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${val}${unit}</strong>
                        </div>`;
                    });
                    return html;
                }),
            },
            dataZoom: dataZoomConfig,
            xAxis: {
                type: 'category',
                data: props.categories,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
            },
            yAxis: {
                type: 'value',
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            series: rawSeries.map((s, idx) => bangunSeri(s, idx, 'bar')),
        };
    }

    // 3. ULTRA-PREMIUM CAPSULE HORIZONTAL LEADERBOARD
    if (props.type === 'bar-horizontal') {
        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Skor', data: props.data }];

        const isPercentage = props.metricUnit === '%' || props.targetValue === 100;
        const axisMax = props.targetValue !== null
            ? Math.max(props.targetValue, isPercentage ? 100 : props.targetValue)
            : (isPercentage ? 100 : undefined);

        const horizontalMarkLine = props.targetValue !== null ? {
            symbol: ['none', 'none'],
            data: [
                {
                    xAxis: props.targetValue,
                    lineStyle: {
                        color: '#f59e0b',
                        type: 'dashed',
                        width: 2,
                        shadowColor: 'rgba(245, 158, 11, 0.4)',
                        shadowBlur: 6,
                    },
                    label: {
                        show: true,
                        formatter: `${props.targetLabel || 'Target'}: ${props.targetValue}${isPercentage ? '%' : ''}`,
                        position: 'end',
                        color: isDark ? '#fbbf24' : '#d97706',
                        fontSize: 11,
                        fontWeight: 600,
                    },
                },
            ],
        } : undefined;

        return {
            color: colors,
            grid: {
                top: 12,
                right: 54,
                bottom: 12,
                left: 10,
                containLabel: true,
            },
            tooltip: {
                ...baseTooltip,
                formatter: props.tooltipFormatter || ((params) => {
                    if (!Array.isArray(params) || params.length === 0) return '';
                    const item = params[0];
                    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${item.color};"></span>`;
                    const unit = props.metricUnit ? ` ${props.metricUnit}` : (isPercentage ? '%' : ' Poin');
                    return `<div style="font-weight: 750; margin-bottom: 4px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${item.name}</div>
                    <div style="font-size: 11.5px; display: flex; align-items: center; justify-content: space-between; gap: 14px;">
                        <span>${dot}${item.seriesName || props.title || 'Penyelesaian'}</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${item.value}${unit}</strong>
                    </div>`;
                }),
            },
            xAxis: {
                type: 'value',
                min: 0,
                max: axisMax,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: {
                    ...baseAxisLabel,
                    formatter: isPercentage ? '{value}%' : undefined,
                },
                splitLine: baseSplitLine,
            },
            yAxis: {
                type: 'category',
                data: props.categories,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: {
                    ...baseAxisLabel,
                    fontWeight: 650,
                    width: 130,
                    overflow: 'truncate',
                    ellipsis: '…',
                },
                inverse: true,
            },
            series: rawSeries.map((s, idx) => {
                const sColor = colors[idx % colors.length];
                return {
                    name: s.name,
                    type: 'bar',
                    data: s.data,
                    barMaxWidth: 24,
                    barCategoryGap: '30%',
                    showBackground: true,
                    backgroundStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.04)' : 'rgba(241, 245, 249, 0.8)',
                        borderRadius: [0, 999, 999, 0],
                    },
                    itemStyle: {
                        borderRadius: [0, 999, 999, 0],
                        color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                            { offset: 0, color: `${sColor}88` },
                            { offset: 0.85, color: sColor },
                            { offset: 1, color: colors[1] || sColor },
                        ]),
                        shadowColor: `${sColor}35`,
                        shadowBlur: 8,
                    },
                    label: {
                        show: true,
                        position: 'right',
                        distance: 10,
                        formatter: (param) => `${param.value}${isPercentage ? '%' : (props.metricUnit ? ` ${props.metricUnit}` : '')}`,
                        color: isDark ? '#f8fafc' : '#0f172a',
                        fontWeight: 800,
                        fontSize: 11.5,
                        fontFamily: 'inherit',
                    },
                    markLine: idx === 0 ? horizontalMarkLine : undefined,
                    emphasis: {
                        itemStyle: {
                            color: sColor,
                            shadowBlur: 14,
                            shadowColor: `${sColor}70`,
                        },
                    },
                    ...s,
                };
            }),
        };
    }

    // 4. ULTRA-PREMIUM DONUT / PIE CHART WITH DEDICATED VECTOR CENTERPIECE
    if (props.type === 'donut' || props.type === 'pie') {
        const isDonut = props.type === 'donut';
        const pieData = Array.isArray(props.data) ? props.data : [];

        const grandTotal = pieData.reduce((acc, curr) => acc + (Number(curr.value) || 0), 0);
        const formattedTotal = (props.metricValue !== '' && props.metricValue !== null && typeof props.metricValue !== 'undefined')
            ? String(props.metricValue)
            : grandTotal.toLocaleString('id-ID');

        const isCompact = (typeof props.height === 'number' ? props.height : parseInt(props.height, 10)) <= 220;
        const customTitle = props.options?.title || {};
        const customLegend = props.options?.legend;
        const customSeries = props.options?.series;
        const customSeries0 = Array.isArray(customSeries) ? customSeries[0] : (customSeries || {});

        const hasSubtext = typeof props.metricLabel === 'string'
            ? props.metricLabel.trim().length > 0
            : Boolean(props.metricLabel);

        return {
            color: colors,
            title: isDonut
                ? {
                      text: String(formattedTotal),
                      subtext: hasSubtext ? props.metricLabel : undefined,
                      left: 'center',
                      top: hasSubtext ? (isCompact ? '32%' : '37%') : (isCompact ? '40%' : '44%'),
                      textStyle: {
                          fontSize: isCompact ? 18 : 28,
                          fontWeight: 900,
                          color: isDark ? '#ffffff' : '#0f172a',
                          fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
                          lineHeight: isCompact ? 18 : 28,
                      },
                      subtextStyle: hasSubtext
                          ? {
                                fontSize: isCompact ? 9.5 : 11,
                                fontWeight: 600,
                                color: isDark ? '#94a3b8' : '#64748b',
                                fontFamily: 'inherit',
                            }
                          : undefined,
                      ...customTitle,
                  }
                : undefined,
            tooltip: {
                ...baseTooltip,
                trigger: 'item',
                formatter: props.tooltipFormatter || ((p) => {
                    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${p.color};"></span>`;
                    const val = typeof p.value === 'number' ? p.value.toLocaleString('id-ID') : p.value;
                    return `<div style="font-weight: 750; margin-bottom: 4px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${dot}${p.name}</div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px;">
                        <span>Jumlah:</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${val} (${p.percent}%)</strong>
                    </div>`;
                }),
            },
            legend: customLegend !== undefined
                ? (customLegend === false ? { show: false } : { bottom: 4, left: 'center', icon: 'circle', itemWidth: 8, itemHeight: 8, itemGap: 16, textStyle: baseAxisLabel, ...customLegend })
                : (isCompact ? { show: false } : {
                      bottom: 4,
                      left: 'center',
                      icon: 'circle',
                      itemWidth: 8,
                      itemHeight: 8,
                      itemGap: 16,
                      textStyle: baseAxisLabel,
                  }),
            series: [
                {
                    name: props.title || 'Distribusi',
                    type: 'pie',
                    radius: isDonut ? (isCompact ? ['58%', '82%'] : ['54%', '80%']) : '78%',
                    center: ['50%', isCompact || customLegend?.show === false ? '50%' : '48%'],
                    avoidLabelOverlap: true,
                    itemStyle: {
                        borderRadius: 8,
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 3,
                        shadowColor: 'rgba(15, 23, 42, 0.08)',
                        shadowBlur: 8,
                    },
                    label: {
                        show: !isDonut,
                        position: 'outside',
                        color: isDark ? '#f8fafc' : '#0f172a',
                        fontWeight: 600,
                        fontSize: 12,
                    },
                    emphasis: {
                        scale: true,
                        scaleSize: 6,
                    },
                    data: pieData,
                    ...customSeries0,
                },
            ],
        };
    }

    // 5. ULTRA-PREMIUM RADAR 360 COMPETENCY MATRIX
    if (props.type === 'radar') {
        const rawSeries = props.series && props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Skor', value: props.data }];

        // Hitung skala maksimum dinamis agar nilai > 100 tidak terpotong poligonnya
        let calculatedMax = 100;
        rawSeries.forEach((s) => {
            const arr = Array.isArray(s.value) ? s.value : (Array.isArray(s.data) ? s.data : []);
            arr.forEach((val) => {
                const num = Number(val);
                if (Number.isFinite(num) && num > calculatedMax) {
                    calculatedMax = Math.ceil(num / 10) * 10;
                }
            });
        });

        const indicators = props.categories.map((c) => {
            const name = typeof c === 'string' ? c : (c.name || c.Nama_Competency || c.Kode_Competency || 'Kompetensi');
            const explicitMax = typeof c === 'object' && c.max ? c.max : undefined;
            return {
                name,
                max: explicitMax ?? calculatedMax,
            };
        });

        const adaLegenda = rawSeries.length > 1;
        const customLegend = props.options?.legend;
        const countIndicators = indicators.length;
        const defaultRadius = adaLegenda
            ? (countIndicators > 8 ? '60%' : '66%')
            : (countIndicators > 8 ? '68%' : '74%');
        const defaultCenter = ['50%', adaLegenda ? '48%' : '50%'];

        return {
            color: colors,
            legend: customLegend !== undefined
                ? (customLegend === false ? { show: false } : { bottom: 0, left: 'center', icon: 'roundRect', itemWidth: 10, itemHeight: 8, itemGap: 16, textStyle: baseAxisLabel, ...customLegend })
                : (adaLegenda ? {
                      bottom: 0,
                      left: 'center',
                      icon: 'roundRect',
                      itemWidth: 10,
                      itemHeight: 8,
                      itemGap: 16,
                      textStyle: baseAxisLabel,
                  } : { show: false }),
            tooltip: {
                ...baseTooltip,
                trigger: 'item',
                formatter: props.tooltipFormatter || ((p) => {
                    const rawValues = Array.isArray(p.value) ? p.value : [];
                    let html = `<div style="font-weight: 750; margin-bottom: 6px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${p.name}</div>`;
                    indicators.forEach((ind, i) => {
                        const score = rawValues[i] !== null && rawValues[i] !== undefined ? rawValues[i] : '-';
                        html += `<div style="display: flex; justify-content: space-between; gap: 16px; margin-top: 3px; font-size: 11.5px;">
                            <span>${ind.name}:</span>
                            <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${score} / ${ind.max || 100}</strong>
                        </div>`;
                    });
                    return html;
                }),
            },
            radar: {
                indicator: indicators,
                shape: 'polygon',
                splitNumber: 4,
                radius: defaultRadius,
                center: defaultCenter,
                axisName: {
                    color: isDark ? '#f8fafc' : '#0f172a',
                    fontSize: 11,
                    fontWeight: 700,
                    fontFamily: 'inherit',
                    lineHeight: 14,
                    padding: [2, 4],
                    formatter: (val) => {
                        if (!val) return '';
                        // Bungkus kata panjang ke baris kedua tanpa memotong kata
                        if (val.length > 14 && val.includes(' ')) {
                            const words = val.split(' ');
                            let l1 = '';
                            let l2 = '';
                            for (const w of words) {
                                if (!l1 || (l1 + ' ' + w).length <= 15) {
                                    l1 = l1 ? `${l1} ${w}` : w;
                                } else {
                                    l2 = l2 ? `${l2} ${w}` : w;
                                }
                            }
                            return l2 ? `${l1}\n${l2}` : l1;
                        }
                        return val;
                    },
                    ...props.options?.radar?.axisName,
                },
                splitLine: {
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.12)' : 'rgba(203, 213, 225, 0.9)',
                        width: 1.5,
                    },
                },
                splitArea: {
                    show: true,
                    areaStyle: {
                        color: isDark
                            ? ['rgba(30, 41, 59, 0.35)', 'rgba(15, 23, 42, 0.5)']
                            : ['rgba(248, 250, 252, 0.75)', 'rgba(238, 242, 255, 0.88)'],
                    },
                },
                axisLine: {
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.14)' : 'rgba(203, 213, 225, 0.95)',
                    },
                },
                ...props.options?.radar,
            },
            series: [
                {
                    type: 'radar',
                    data: rawSeries.map((s, idx) => {
                        const sName = s.name || (idx === 0 ? 'Skor' : `Seri ${idx + 1}`);
                        const isAmbang = /ambang|kkm|target|standar/i.test(sName);
                        const sColor = isAmbang ? '#f59e0b' : colors[idx % colors.length];
                        const valArr = Array.isArray(s.value) ? s.value : (Array.isArray(s.data) ? s.data : []);

                        return {
                            name: sName,
                            value: valArr,
                            symbol: 'circle',
                            symbolSize: isAmbang ? 6 : 8,
                            itemStyle: {
                                color: sColor,
                                borderColor: isDark ? '#0f172a' : '#ffffff',
                                borderWidth: isAmbang ? 2 : 3,
                                shadowColor: `${sColor}60`,
                                shadowBlur: isAmbang ? 3 : 8,
                            },
                            lineStyle: {
                                width: isAmbang ? 2.25 : 3.25,
                                type: isAmbang ? 'dashed' : 'solid',
                                color: sColor,
                                shadowColor: `${sColor}50`,
                                shadowBlur: isAmbang ? 0 : 10,
                                shadowOffsetY: isAmbang ? 0 : 3,
                            },
                            areaStyle: isAmbang
                                ? { opacity: 0 }
                                : {
                                      color: new echarts.graphic.RadialGradient(0.5, 0.5, 0.8, [
                                          { offset: 0, color: `${sColor}60` },
                                          { offset: 1, color: `${sColor}15` },
                                      ]),
                                  },
                            emphasis: {
                                lineStyle: { width: isAmbang ? 3 : 4 },
                                itemStyle: { symbolSize: isAmbang ? 8 : 10, borderWidth: 3 },
                            },
                            ...s,
                        };
                    }),
                },
            ],
        };
    }

    // 6. DUAL-LAYER AEROSPACE LUXURY GAUGE KPI INSTRUMENT
    if (props.type === 'gauge') {
        const gaugeValue = typeof props.data === 'number' ? props.data : (props.data?.[0]?.value || 0);

        const { series: customSeries } = props.options;
        const customSeries0 = Array.isArray(customSeries) ? customSeries[0] : (customSeries || {});
        const customSeries1 = Array.isArray(customSeries) && customSeries.length > 1 ? customSeries[1] : {};

        return {
            color: colors,
            tooltip: {
                ...baseTooltip,
                trigger: 'item',
                formatter: props.tooltipFormatter || ((p) => {
                    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${primaryColor};"></span>`;
                    return `<div style="font-weight: 750; margin-bottom: 4px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${dot}${p.name || 'Penyelesaian'}</div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px;">
                        <span>Skor Capaian:</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${p.value}%</strong>
                    </div>
                    <div style="font-size: 11px; color: ${isDark ? '#94a3b8' : '#64748b'}; margin-top: 2px;">Target Evaluasi: 100%</div>`;
                }),
            },
            series: [
                // Layer 1: Outer Precision Calibration Ring & Scale Ticks
                {
                    type: 'gauge',
                    startAngle: 210,
                    endAngle: -30,
                    min: 0,
                    max: 100,
                    splitNumber: 5,
                    radius: '92%',
                    center: ['50%', '50%'],
                    axisLine: {
                        lineStyle: {
                            width: 2,
                            color: [[1, isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(226, 232, 240, 0.95)']],
                        },
                    },
                    splitLine: {
                        distance: 0,
                        length: 8,
                        lineStyle: {
                            width: 2,
                            color: isDark ? 'rgba(255, 255, 255, 0.3)' : '#94a3b8',
                            cap: 'round',
                        },
                    },
                    axisTick: {
                        distance: 0,
                        splitNumber: 4,
                        length: 4,
                        lineStyle: {
                            width: 1,
                            color: isDark ? 'rgba(255, 255, 255, 0.15)' : '#cbd5e1',
                            cap: 'round',
                        },
                    },
                    axisLabel: {
                        distance: -14,
                        color: isDark ? '#94a3b8' : '#64748b',
                        fontSize: 10,
                        fontWeight: 700,
                        fontFamily: 'inherit',
                    },
                    pointer: { show: false },
                    detail: { show: false },
                    ...customSeries0,
                },

                // Layer 2: Radiant Luminous Gradient Progress Arc with Jewel Center
                {
                    type: 'gauge',
                    startAngle: 210,
                    endAngle: -30,
                    min: 0,
                    max: 100,
                    radius: '82%',
                    center: ['50%', '50%'],
                    progress: {
                        show: true,
                        roundCap: true,
                        width: 16,
                        itemStyle: {
                            color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                                { offset: 0, color: `${primaryColor}77` },
                                { offset: 0.6, color: primaryColor },
                                { offset: 1, color: colors[1] || primaryColor },
                            ]),
                            shadowColor: `${primaryColor}55`,
                            shadowBlur: 16,
                            shadowOffsetY: 3,
                        },
                    },
                    axisLine: {
                        roundCap: true,
                        lineStyle: {
                            width: 16,
                            color: [[1, isDark ? 'rgba(30, 41, 59, 0.55)' : 'rgba(241, 245, 249, 0.95)']],
                            shadowColor: 'rgba(15, 23, 42, 0.04)',
                            shadowBlur: 6,
                        },
                    },
                    axisTick: { show: false },
                    splitLine: { show: false },
                    axisLabel: { show: false },
                    pointer: {
                        show: true,
                        length: '56%',
                        width: 5,
                        itemStyle: {
                            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                { offset: 0, color: isDark ? '#ffffff' : '#1e293b' },
                                { offset: 1, color: isDark ? '#94a3b8' : '#64748b' },
                            ]),
                            shadowColor: 'rgba(0, 0, 0, 0.25)',
                            shadowBlur: 8,
                        },
                    },
                    anchor: {
                        show: true,
                        showAbove: true,
                        size: 18,
                        itemStyle: {
                            color: primaryColor,
                            borderWidth: 3.5,
                            borderColor: isDark ? '#0f172a' : '#ffffff',
                            shadowColor: `${primaryColor}60`,
                            shadowBlur: 10,
                        },
                    },
                    title: {
                        show: Boolean(props.metricLabel || props.title),
                        offsetCenter: [0, '36%'],
                        fontSize: 11.5,
                        fontWeight: 700,
                        color: isDark ? '#cbd5e1' : '#64748b',
                        fontFamily: 'inherit',
                    },
                    detail: {
                        valueAnimation: true,
                        offsetCenter: [0, '58%'],
                        fontSize: 32,
                        fontWeight: 900,
                        fontFamily: 'inherit',
                        letterSpacing: '-0.03em',
                        color: isDark ? '#ffffff' : '#0f172a',
                        formatter: '{value}%',
                    },
                    data: [
                        {
                            value: gaugeValue,
                            name: props.metricLabel || props.title || '',
                        },
                    ],
                    ...customSeries1,
                },
            ],
        };
    }

    // 7. ULTRA-PREMIUM ROUNDED FUNNEL RECRUITMENT PIPELINE
    if (props.type === 'funnel') {
        const rawFunnel = Array.isArray(props.data) ? props.data : [];
        const maxVal = rawFunnel[0]?.value || 100;

        const formattedFunnelData = rawFunnel.map((item, idx) => {
            const sColor = colors[idx % colors.length];
            return {
                ...item,
                itemStyle: {
                    borderRadius: 10,
                    borderColor: isDark ? '#0f172a' : '#ffffff',
                    borderWidth: 3,
                    color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                        { offset: 0, color: sColor },
                        { offset: 1, color: `${sColor}cc` },
                    ]),
                    shadowColor: `${sColor}35`,
                    shadowBlur: 8,
                    shadowOffsetY: 3,
                },
            };
        });

        return {
            color: colors,
            tooltip: {
                ...baseTooltip,
                trigger: 'item',
                formatter: props.tooltipFormatter || ((p) => {
                    const d = p.data;
                    const pct = ((d.value / maxVal) * 100).toFixed(1);
                    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${colors[p.dataIndex % colors.length]};"></span>`;
                    return `<div style="font-weight: 750; margin-bottom: 4px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${dot}${d.name}</div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px;">
                        <span>Kandidat Lolos:</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${d.value.toLocaleString('id-ID')} Orang</strong>
                    </div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px; margin-top: 2px;">
                        <span>Rasio Konversi:</span>
                        <strong style="color: ${isDark ? '#34d399' : '#059669'}; font-family: monospace;">${pct}% dari Total</strong>
                    </div>`;
                }),
            },
            series: [
                {
                    name: props.title || 'Funnel Pipeline',
                    type: 'funnel',
                    left: '8%',
                    top: 16,
                    bottom: 16,
                    width: '84%',
                    min: 0,
                    max: maxVal,
                    minSize: '22%',
                    maxSize: '100%',
                    sort: 'descending',
                    gap: 8,
                    itemStyle: {
                        borderRadius: 10,
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 3,
                        shadowColor: 'rgba(15, 23, 42, 0.08)',
                        shadowBlur: 8,
                    },
                    label: {
                        show: true,
                        position: 'inside',
                        formatter: '{title|{b}}\n{val|{c} Kandidat ({d}%)}',
                        rich: {
                            title: {
                                color: '#ffffff',
                                fontSize: 12,
                                fontWeight: 800,
                                textShadowColor: 'rgba(0,0,0,0.35)',
                                textShadowBlur: 4,
                                lineHeight: 18,
                            },
                            val: {
                                color: 'rgba(255, 255, 255, 0.95)',
                                fontSize: 11,
                                fontWeight: 600,
                                textShadowColor: 'rgba(0,0,0,0.25)',
                                textShadowBlur: 2,
                                lineHeight: 14,
                            },
                        },
                    },
                    emphasis: {
                        itemStyle: {
                            borderRadius: 12,
                            shadowBlur: 16,
                            shadowColor: 'rgba(15, 23, 42, 0.25)',
                        },
                    },
                    data: formattedFunnelData,
                    ...props.options?.series?.[0],
                },
            ],
        };
    }

    // 8. ULTRA-PREMIUM 9-BOX TALENT ASSESSMENT SCATTER MATRIX
    if (props.type === 'scatter') {
        const scatterData = Array.isArray(props.data) ? props.data : [];

        return {
            color: colors,
            grid: {
                top: 24,
                right: 28,
                bottom: 38,
                left: 38,
                containLabel: true,
            },
            tooltip: {
                ...baseTooltip,
                trigger: 'item',
                formatter: props.tooltipFormatter || ((p) => {
                    const d = p.data;
                    const name = d[2] || 'Talenta';
                    const perf = d[0];
                    const pot = d[1];
                    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${primaryColor};"></span>`;
                    let quadrant = 'Core Performer';
                    if (perf >= 80 && pot >= 80) quadrant = '🌟 Star & Future Leader (Tier 1)';
                    else if (perf >= 80 && pot < 80) quadrant = '⚡ High Performer / Specialist';
                    else if (perf < 80 && pot >= 80) quadrant = '🚀 High Potential / Growth';
                    else if (perf < 60 && pot < 60) quadrant = '⚠️ Underperformer / Need Coaching';
                    else quadrant = '📈 Solid Core Contributor';

                    return `<div style="font-weight: 750; margin-bottom: 4px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${dot}${name}</div>
                    <div style="font-size: 11px; margin-bottom: 6px; color: ${isDark ? '#818cf8' : '#4f46e5'}; font-weight: 700;">${quadrant}</div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px;">
                        <span>Skor Kinerja (X):</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${perf} / 100</strong>
                    </div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px; margin-top: 2px;">
                        <span>Skor Potensi (Y):</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${pot} / 100</strong>
                    </div>`;
                }),
            },
            xAxis: {
                type: 'value',
                name: 'Kinerja (Performance) →',
                nameLocation: 'middle',
                nameGap: 26,
                nameTextStyle: { ...baseAxisLabel, fontWeight: 700, fontSize: 11.5 },
                min: 0,
                max: 100,
                axisLabel: baseAxisLabel,
                splitLine: {
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(226, 232, 240, 0.8)',
                        type: 'dashed',
                    },
                },
            },
            yAxis: {
                type: 'value',
                name: 'Potensi (Potential) →',
                nameLocation: 'middle',
                nameGap: 26,
                nameTextStyle: { ...baseAxisLabel, fontWeight: 700, fontSize: 11.5 },
                min: 0,
                max: 100,
                axisLabel: baseAxisLabel,
                splitLine: {
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(226, 232, 240, 0.8)',
                        type: 'dashed',
                    },
                },
            },
            series: [
                {
                    name: 'Talenta',
                    type: 'scatter',
                    symbolSize: (data) => Math.max(16, (data[3] || 20)),
                    data: scatterData,
                    itemStyle: {
                        color: new echarts.graphic.RadialGradient(0.3, 0.3, 0.85, [
                            { offset: 0, color: '#ffffff' },
                            { offset: 0.3, color: primaryColor },
                            { offset: 1, color: `${primaryColor}cc` },
                        ]),
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 2.5,
                        shadowColor: `${primaryColor}60`,
                        shadowBlur: 14,
                        shadowOffsetY: 4,
                    },
                    label: {
                        show: true,
                        position: 'bottom',
                        distance: 6,
                        formatter: (p) => (p.data[2] ? p.data[2].split(' ')[0] : ''),
                        color: isDark ? '#cbd5e1' : '#475569',
                        fontSize: 10.5,
                        fontWeight: 700,
                        fontFamily: 'inherit',
                    },
                    emphasis: {
                        scale: 1.4,
                        itemStyle: {
                            borderColor: '#ffffff',
                            borderWidth: 3.5,
                            shadowBlur: 22,
                            shadowColor: `${primaryColor}99`,
                        },
                    },
                    markArea: {
                        silent: true,
                        data: [
                            // 1. Top-Right Star Talent Zone
                            [
                                {
                                    name: '🌟 STAR TALENT',
                                    xAxis: 80,
                                    yAxis: 80,
                                    itemStyle: {
                                        color: isDark ? 'rgba(99, 102, 241, 0.12)' : 'rgba(99, 102, 241, 0.06)',
                                    },
                                    label: {
                                        position: 'insideTopRight',
                                        offset: [-6, 6],
                                        color: isDark ? '#a5b4fc' : '#4f46e5',
                                        fontWeight: 800,
                                        fontSize: 10,
                                        fontFamily: 'inherit',
                                    },
                                },
                                { xAxis: 100, yAxis: 100 },
                            ],
                            // 2. Bottom-Left Development Zone
                            [
                                {
                                    name: '⚠️ DEV NEEDED',
                                    xAxis: 0,
                                    yAxis: 0,
                                    itemStyle: {
                                        color: isDark ? 'rgba(239, 68, 68, 0.07)' : 'rgba(239, 68, 68, 0.035)',
                                    },
                                    label: {
                                        position: 'insideBottomLeft',
                                        offset: [6, -6],
                                        color: isDark ? '#fca5a5' : '#dc2626',
                                        fontWeight: 750,
                                        fontSize: 9.5,
                                        fontFamily: 'inherit',
                                    },
                                },
                                { xAxis: 60, yAxis: 60 },
                            ],
                        ],
                    },
                    markLine: {
                        silent: true,
                        symbol: ['none', 'none'],
                        lineStyle: {
                            type: 'dashed',
                            color: isDark ? 'rgba(255, 255, 255, 0.18)' : 'rgba(148, 163, 184, 0.65)',
                            width: 1.5,
                        },
                        data: [
                            { xAxis: 60 },
                            { xAxis: 80 },
                            { yAxis: 60 },
                            { yAxis: 80 },
                        ],
                    },
                },
            ],
        };
    }

    // 9. ULTRA-PREMIUM ROUNDED JEWEL TREEMAP
    if (props.type === 'treemap') {
        const rawTreemap = Array.isArray(props.data) ? props.data : [];
        const formattedTreemapData = rawTreemap.map((item, idx) => {
            const sColor = colors[idx % colors.length];
            return {
                ...item,
                itemStyle: {
                    borderRadius: 8,
                    borderColor: isDark ? '#0f172a' : '#ffffff',
                    borderWidth: 3,
                    gapWidth: 4,
                    color: new echarts.graphic.LinearGradient(0, 0, 1, 1, [
                        { offset: 0, color: sColor },
                        { offset: 1, color: `${sColor}cc` },
                    ]),
                },
            };
        });

        return {
            color: colors,
            tooltip: {
                ...baseTooltip,
                trigger: 'item',
                formatter: props.tooltipFormatter || ((p) => {
                    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${colors[p.dataIndex % colors.length]};"></span>`;
                    const val = typeof p.value === 'number' ? p.value.toLocaleString('id-ID') : p.value;
                    return `<div style="font-weight: 750; margin-bottom: 4px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${dot}${p.name}</div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px;">
                        <span>Alokasi Anggaran:</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">Rp ${val} Juta</strong>
                    </div>`;
                }),
            },
            series: [
                {
                    name: props.title || 'Alokasi',
                    type: 'treemap',
                    roam: false,
                    nodeClick: false,
                    breadcrumb: { show: false },
                    left: 6,
                    top: 6,
                    right: 6,
                    bottom: 6,
                    data: formattedTreemapData,
                    itemStyle: {
                        borderRadius: 8,
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 3,
                        gapWidth: 4,
                        shadowColor: 'rgba(15, 23, 42, 0.08)',
                        shadowBlur: 8,
                    },
                    levels: [
                        {
                            itemStyle: {
                                borderRadius: 8,
                                borderColor: isDark ? '#0f172a' : '#ffffff',
                                borderWidth: 3,
                                gapWidth: 4,
                            },
                        },
                        {
                            itemStyle: {
                                borderRadius: 8,
                                borderColor: isDark ? '#0f172a' : '#ffffff',
                                borderWidth: 3,
                                gapWidth: 4,
                            },
                        },
                    ],
                    label: {
                        show: true,
                        position: 'insideTopLeft',
                        padding: [10, 12],
                        formatter: '{title|{b}}\n{val|Rp {c} Jt}',
                        rich: {
                            title: {
                                fontSize: 12,
                                fontWeight: 800,
                                color: '#ffffff',
                                textShadowColor: 'rgba(0,0,0,0.35)',
                                textShadowBlur: 4,
                                lineHeight: 18,
                            },
                            val: {
                                fontSize: 11,
                                fontWeight: 600,
                                color: 'rgba(255, 255, 255, 0.92)',
                                textShadowColor: 'rgba(0,0,0,0.25)',
                                textShadowBlur: 2,
                                lineHeight: 15,
                            },
                        },
                    },
                    emphasis: {
                        itemStyle: {
                            borderRadius: 10,
                            shadowBlur: 16,
                            shadowColor: 'rgba(15, 23, 42, 0.25)',
                        },
                    },
                },
            ],
        };
    }

    // 10. ULTRA-CLEAN ROUNDED HEATMAP (ACTIVITY / ATTENDANCE MATRIX)
    // 10. ULTRA-CLEAN ROUNDED HEATMAP (ACTIVITY / ATTENDANCE MATRIX)
    if (props.type === 'heatmap') {
        const heatmapData = Array.isArray(props.data) ? props.data : [];
        const xData = props.categories.length > 0 ? props.categories : ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'];
        const yData = props.series.length > 0 && props.series[0].categories
            ? props.series[0].categories
            : ['Sen', 'Sel', 'Rab', 'Kam', 'Jum'];

        // Label sumbu X: jam dipadankan ke HH:00, selain itu apa adanya.
        const perJam = props.categoryUnit === 'jam';
        const labelSumbuX = (nilai) => {
            const s = String(nilai);
            if (!perJam) return s;
            return s.includes(':') ? s : `${s.padStart(2, '0')}:00`;
        };

        let maxVal = 1;
        heatmapData.forEach((d) => {
            const val = Array.isArray(d) ? d[2] : (d.value || 0);
            if (val > maxVal) maxVal = val;
        });

        const isDense = xData.length > 12;

        return {
            color: colors,
            grid: {
                top: 24,
                right: 18,
                bottom: 48,
                left: 14,
                containLabel: true,
            },
            tooltip: {
                ...baseTooltip,
                trigger: 'item',
                position: 'top',
                formatter: props.tooltipFormatter || ((p) => {
                    const d = p.data;
                    const val = Array.isArray(d) ? d[2] : (d.value ?? d);
                    const xLabel = xData[d[0]] !== undefined ? xData[d[0]] : d[0];
                    const yLabel = yData[d[1]] !== undefined ? yData[d[1]] : d[1];
                    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${primaryColor};"></span>`;
                    const judulX = perJam ? `Pukul ${labelSumbuX(xLabel)} WIB` : labelSumbuX(xLabel);
                    const satuan = props.valueSuffix ? ` ${props.valueSuffix}` : '';
                    return `<div style="font-weight: 750; margin-bottom: 4px; color: ${isDark ? '#f8fafc' : '#0f172a'};">${dot}${yLabel} · ${judulX}</div>
                    <div style="font-size: 11.5px; display: flex; justify-content: space-between; gap: 14px;">
                        <span>${props.valueLabel}:</span>
                        <strong style="color: ${isDark ? '#ffffff' : '#0f172a'}; font-family: monospace;">${val}${satuan}</strong>
                    </div>`;
                }),
            },
            xAxis: {
                type: 'category',
                data: xData,
                splitArea: { show: false },
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: {
                    ...baseAxisLabel,
                    interval: isDense ? (index) => index % 3 === 0 : 'auto',
                    formatter: labelSumbuX,
                },
            },
            yAxis: {
                type: 'category',
                data: yData,
                splitArea: { show: false },
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { ...baseAxisLabel, fontWeight: 600 },
            },
            visualMap: {
                min: 0,
                max: maxVal,
                calculable: false,
                orient: 'horizontal',
                left: 'center',
                bottom: 4,
                itemWidth: 10,
                itemHeight: 70,
                inRange: {
                    color: isDark
                        ? ['rgba(30, 41, 59, 0.45)', `${primaryColor}45`, `${primaryColor}85`, primaryColor]
                        : ['#f8fafc', `${primaryColor}30`, `${primaryColor}75`, primaryColor],
                },
                text: ['Tinggi', 'Rendah'],
                textStyle: baseAxisLabel,
            },
            series: [
                gabungDalam(
                    {
                        name: props.title || 'Aktivitas',
                        type: 'heatmap',
                        data: heatmapData,
                        label: {
                            show: !isDense,
                            formatter: (p) => {
                                const val = Array.isArray(p.data) ? p.data[2] : (p.data.value ?? p.data);
                                if (val === 0 || val === '-' || val === null || val === undefined) return '';
                                return props.metricUnit === '%' ? `${val}%` : String(val);
                            },
                            color: (p) => {
                                const val = Array.isArray(p.data) ? p.data[2] : (p.data.value ?? p.data);
                                const num = Number(val);
                                if (isNaN(num) || num === 0) return isDark ? '#64748b' : '#94a3b8';
                                return num >= (maxVal * 0.65) ? '#ffffff' : (isDark ? '#f8fafc' : '#0f172a');
                            },
                            fontSize: 10.5,
                            fontWeight: 750,
                            fontFamily: 'inherit',
                        },
                        itemStyle: {
                            borderRadius: 6,
                            borderColor: isDark ? '#0f172a' : '#ffffff',
                            borderWidth: 3,
                            shadowColor: 'rgba(15, 23, 42, 0.04)',
                            shadowBlur: 4,
                        },
                        emphasis: {
                            itemStyle: {
                                borderRadius: 7,
                                borderColor: '#ffffff',
                                borderWidth: 3.5,
                                shadowBlur: 12,
                                shadowColor: `${primaryColor}80`,
                            },
                            label: {
                                show: true,
                                fontWeight: 900,
                                fontSize: 11.5,
                                fontFamily: 'inherit',
                                formatter: (p) => {
                                    const val = Array.isArray(p.data) ? p.data[2] : (p.data.value ?? p.data);
                                    return val > 0 ? String(val) : '';
                                },
                            },
                        },
                    },
                    props.options?.series?.[0] || {}
                ),
            ],
        };
    }

    return props.options;
};

/**
 * Opsi akhir = preset sistem desain, lalu opsi pemanggil digabung DALAM di
 * atasnya. Pemanggil selalu menang pada kunci yang ia sebut, dan hanya pada
 * kunci itu.
 */
const buildOptions = () => {
    if (props.type === 'custom') return props.options;

    const preset = bangunPreset();
    if (!props.options || Object.keys(props.options).length === 0) return preset;

    const { series: seriPemanggil, ...sisanya } = props.options;
    const timpa = PRESET_MENYERAP_SERIES.includes(props.type)
        ? sisanya
        : props.options;

    return gabungDalam(preset, timpa);
};

// Lifecycle & Resize Observer
const initChart = () => {
    if (!chartDomRef.value) return;

    if (chartInstance) {
        chartInstance.dispose();
    }

    chartInstance = echarts.init(chartDomRef.value, null, {
        renderer: props.renderer,
    });

    const opt = buildOptions();
    chartInstance.setOption(opt, true);

    nextTick(() => {
        if (chartInstance) {
            chartInstance.resize();
        }
    });

    chartInstance.on('click', (params) => {
        emit('chart-click', params);
    });

    emit('chart-ready', chartInstance);
};

const updateChart = () => {
    if (!chartInstance) {
        initChart();
        return;
    }
    const opt = buildOptions();
    chartInstance.setOption(opt, true);

    nextTick(() => {
        if (chartInstance) {
            chartInstance.resize();
        }
    });
};

onMounted(() => {
    nextTick(() => {
        initChart();

        if (chartContainerRef.value && typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(() => {
                if (chartInstance) {
                    chartInstance.resize();
                }
            });
            resizeObserver.observe(chartContainerRef.value);
        }
    });
});

onBeforeUnmount(() => {
    if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver = null;
    }
    if (chartInstance) {
        chartInstance.dispose();
        chartInstance = null;
    }
});

watch(
    [
        () => props.options,
        () => props.data,
        () => props.categories,
        () => props.series,
        () => props.height,
        () => props.sentiment,
        () => props.type,
        () => props.variant,
        () => props.loading,
        () => props.targetValue,
        () => props.dataZoom,
        () => props.tooltipFormatter,
        () => props.showTooltip,
    ],
    () => {
        nextTick(updateChart);
    },
    { deep: true }
);

defineExpose({
    getInstance: () => chartInstance,
    resize: () => chartInstance && chartInstance.resize(),
    exportImage,
});
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   HCIS ENTERPRISE SUITE - CHART COMPONENT
   ═══════════════════════════════════════════════════════════════════════════ */

.hcis-chart-wrapper {
    position: relative;
    width: 100%;
    font-family: var(--hcis-font-body, inherit);
}

.hcis-chart-wrapper--standalone {
    background: transparent;
}

/* Header Tools & Buttons */
.hcis-chart__header-tools {
    display: flex;
    align-items: center;
    gap: 6px;
}

.hcis-chart__time-chips {
    display: inline-flex;
    align-items: center;
    padding: 3px;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-md, 8px);
    gap: 2px;
}

.hcis-chart__time-chip {
    border: none;
    background: transparent;
    padding: 3px 9px;
    font-size: 0.6875rem;
    font-weight: 650;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    border-radius: 6px;
    cursor: pointer;
    line-height: 1.2;
    transition-property: var(--hcis-transition-props);
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-chart__time-chip:hover {
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    background: rgba(255, 255, 255, 0.6);
}

.hcis-chart__time-chip--active {
    background: var(--hcis-white) !important;
    color: var(--hcis-brand-600, var(--hcis-indigo-600)) !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(99, 102, 241, 0.15);
}

.hcis-chart__view-toggle {
    display: inline-flex;
    align-items: center;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 2px;
    gap: 2px;
}

.hcis-chart__tool-btn {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 6px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.875rem;
    transition-property: var(--hcis-transition-props);
    transition-duration: 140ms;
    transition-timing-function: ease;
}

.hcis-chart__tool-btn:hover {
    background: rgba(255, 255, 255, 0.8);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.hcis-chart__tool-btn--active {
    background: var(--hcis-white) !important;
    color: var(--hcis-brand-600, var(--hcis-indigo-600)) !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.hcis-chart__tool-btn--export {
    border: 1px solid rgba(226, 232, 240, 0.85);
    background: rgba(241, 245, 249, 0.9);
}

.hcis-chart__tool-btn--export:hover {
    border-color: rgba(165, 180, 252, 0.8);
    background: var(--hcis-white);
}

/* Top Banner Stack (KPI Metric & AI Capsule) */
.hcis-chart__top-banner {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    margin-bottom: 0.75rem;
}

/* Metric Summary Banner */
.hcis-chart__metric-banner {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 0 0.25rem;
}

.hcis-chart__metric-main {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.hcis-chart__metric-value {
    font-family: var(--hcis-font-heading, inherit);
    font-size: 1.75rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    line-height: 1.1;
}

.hcis-chart__metric-unit {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.hcis-chart__metric-trend {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 7px;
    border-radius: 999px;
    font-size: 0.71875rem;
    font-weight: 750;
    line-height: 1;
}

.hcis-chart__metric-trend--up {
    background-color: var(--hcis-success-50, var(--hcis-emerald-50));
    color: var(--hcis-success-700, var(--hcis-emerald-700));
    border: 1px solid rgba(167, 243, 208, 0.8);
}

.hcis-chart__metric-trend--down {
    background-color: var(--hcis-danger-50, var(--hcis-red-50));
    color: var(--hcis-danger-700, var(--hcis-red-700));
    border: 1px solid rgba(254, 202, 202, 0.8);
}

.hcis-chart__metric-label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

/* AI Insight Capsule */
.hcis-chart__ai-capsule {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(245, 243, 255, 0.95) 0%, rgba(238, 242, 255, 0.85) 100%);
    border: 1px solid rgba(196, 181, 253, 0.6);
    box-shadow: 0 1px 3px rgba(124, 58, 237, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.hcis-chart__ai-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 7px;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--hcis-violet-600) 0%, var(--hcis-indigo-600) 100%);
    color: var(--hcis-white);
    font-size: 0.65625rem;
    font-weight: 750;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    flex-shrink: 0;
    box-shadow: 0 2px 6px -1px rgba(124, 58, 237, 0.35);
}

.hcis-chart__ai-text {
    font-size: 0.775rem;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    font-weight: 500;
    margin: 0;
    line-height: 1.35;
}

/* Canvas Container */
.hcis-chart__canvas-container {
    position: relative;
    width: 100%;
    min-height: 160px;
}

.hcis-chart__dom {
    width: 100%;
    height: 100%;
}

/* Table View */
.hcis-chart__table-view {
    width: 100%;
    overflow-y: auto;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid rgba(226, 232, 240, 0.9);
}

/* State Overlays */
.hcis-chart__overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(4px);
    z-index: 10;
}

.hcis-chart__loading-skeleton-box {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--hcis-radius-lg, 12px);
    overflow: hidden;
}

.hcis-chart__loading-badge {
    position: absolute;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
    backdrop-filter: blur(8px);
    z-index: 2;
}

.hcis-chart__spinner {
    width: 1.125rem;
    height: 1.125rem;
    border: 2px solid rgba(99, 102, 241, 0.25);
    border-top-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    border-radius: 50%;
    animation: hcis-chart-spin 0.8s linear infinite;
}

@keyframes hcis-chart-spin {
    to { transform: rotate(360deg); }
}

.hcis-chart__loading-text {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
}

/* Obsidian Dark Mode Overrides */
.hcis-chart-wrapper--variant-obsidian .hcis-chart__time-chips,
.hcis-chart-wrapper--variant-obsidian .hcis-chart__view-toggle,
.hcis-chart-wrapper--variant-obsidian .hcis-chart__tool-btn--export {
    background: rgba(30, 41, 59, 0.8) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__time-chip,
.hcis-chart-wrapper--variant-obsidian .hcis-chart__tool-btn {
    color: var(--hcis-slate-400) !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__time-chip:hover,
.hcis-chart-wrapper--variant-obsidian .hcis-chart__tool-btn:hover {
    color: var(--hcis-white) !important;
    background: rgba(51, 65, 85, 0.6) !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__time-chip--active,
.hcis-chart-wrapper--variant-obsidian .hcis-chart__tool-btn--active {
    background: var(--hcis-slate-800) !important;
    color: var(--hcis-indigo-400) !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(129, 140, 248, 0.3) !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__metric-value {
    color: var(--hcis-white) !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__metric-unit,
.hcis-chart-wrapper--variant-obsidian .hcis-chart__metric-label {
    color: var(--hcis-slate-400) !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__ai-capsule {
    background: linear-gradient(135deg, rgba(30, 27, 75, 0.6) 0%, rgba(15, 23, 42, 0.8) 100%) !important;
    border-color: rgba(139, 92, 246, 0.3) !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__ai-text {
    color: var(--hcis-slate-300) !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__loading-badge {
    background: rgba(15, 23, 42, 0.92) !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__loading-text {
    color: var(--hcis-slate-300) !important;
}
</style>
