import os

chart_path = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"

content = """<template>
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

                <!-- AI Insight Smart Capsule -->
                <div v-if="insight" class="hcis-chart__ai-capsule">
                    <div class="hcis-chart__ai-badge">
                        <HcisIcon name="stars" size="xs" />
                        <span>AI Insight</span>
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

                <!-- State: Loading Shimmer -->
                <div v-if="loading" class="hcis-chart__overlay hcis-chart__overlay--loading">
                    <div class="hcis-chart__spinner" />
                    <span class="hcis-chart__loading-text">{{ loadingText || 'Memproses visualisasi data analitik...' }}</span>
                </div>

                <!-- State: Empty / No Data -->
                <div v-else-if="isEmpty" class="hcis-chart__overlay hcis-chart__overlay--empty">
                    <div class="hcis-chart__empty-icon">
                        <HcisIcon name="bar-chart-line" size="lg" />
                    </div>
                    <span class="hcis-chart__empty-text">{{ emptyText || 'Belum ada data visualisasi yang tersedia' }}</span>
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

            <!-- State: Loading Shimmer -->
            <div v-if="loading" class="hcis-chart__overlay hcis-chart__overlay--loading">
                <div class="hcis-chart__spinner" />
                <span class="hcis-chart__loading-text">{{ loadingText || 'Memuat data...' }}</span>
            </div>

            <!-- State: Empty / No Data -->
            <div v-else-if="isEmpty" class="hcis-chart__overlay hcis-chart__overlay--empty">
                <div class="hcis-chart__empty-icon">
                    <HcisIcon name="bar-chart-line" size="lg" />
                </div>
                <span class="hcis-chart__empty-text">{{ emptyText || 'Belum ada data' }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import * as echarts from 'echarts';
import HcisCard from './HcisCard.vue';
import HcisIcon from './HcisIcon.vue';

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

    // Threshold & Target Lines
    targetValue: { type: Number, default: null },
    targetLabel: { type: String, default: 'Target' },

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

    // Interactive Metric Summary Banner & AI Insight
    metricValue: { type: [String, Number], default: '' },
    metricUnit: { type: String, default: '' },
    metricLabel: { type: String, default: '' },
    metricTrend: { type: String, default: '' },
    metricTrendDirection: { type: String, default: 'up', validator: (val) => ['up', 'down', 'neutral'].includes(val) },
    insight: { type: String, default: '' },

    // Filter Chips & View Tools
    timeFilters: { type: Array, default: () => [] },
    modelValueFilter: { type: String, default: '' },
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
    a.download = `${props.title ? props.title.toLowerCase().replace(/\\s+/g, '-') : 'hcis-chart'}-${Date.now()}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
};

const formattedHeight = computed(() => {
    if (typeof props.height === 'number') return `${props.height}px`;
    return props.height;
});

const isEmpty = computed(() => {
    if (props.type === 'custom') {
        return !props.options || Object.keys(props.options).length === 0;
    }
    if (Array.isArray(props.data) && props.data.length === 0 && (!props.series || props.series.length === 0)) {
        return true;
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

// ECharts Preset Builders
const buildOptions = () => {
    if (props.type === 'custom') {
        return props.options;
    }

    const colors = getThemeColors();
    const primaryColor = colors[0];
    const isDark = props.variant === 'obsidian';

    const baseGrid = {
        top: 28,
        right: 18,
        bottom: props.dataZoom ? 48 : 28,
        left: 14,
        containLabel: true,
    };

    const baseTooltip = {
        trigger: 'axis',
        backgroundColor: isDark ? 'rgba(15, 23, 42, 0.95)' : 'rgba(255, 255, 255, 0.96)',
        borderColor: isDark ? 'rgba(255, 255, 255, 0.12)' : 'rgba(226, 232, 240, 0.9)',
        borderWidth: 1,
        padding: [10, 14],
        textStyle: {
            color: isDark ? '#f8fafc' : '#0f172a',
            fontSize: 12,
            fontFamily: 'inherit',
        },
        extraCssText: 'box-shadow: 0 10px 25px -4px rgba(15, 23, 42, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.9) inset; border-radius: 12px; backdrop-filter: blur(12px);',
    };

    const baseAxisLabel = {
        color: isDark ? '#94a3b8' : '#64748b',
        fontSize: 11,
        fontFamily: 'inherit',
        fontWeight: 500,
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

    // 1. AREA / LINE CHART
    if (props.type === 'area' || props.type === 'line') {
        const isArea = props.type === 'area';
        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Nilai', data: props.data }];

        return {
            color: colors,
            grid: baseGrid,
            tooltip: baseTooltip,
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
            series: rawSeries.map((s, idx) => {
                const sColor = colors[idx % colors.length];
                return {
                    name: s.name,
                    type: 'line',
                    smooth: props.smooth,
                    data: s.data,
                    showSymbol: false,
                    symbolSize: 7,
                    lineStyle: {
                        width: 3,
                        color: sColor,
                        shadowColor: `${sColor}40`,
                        shadowBlur: 10,
                        shadowOffsetY: 4,
                    },
                    itemStyle: {
                        color: sColor,
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 2,
                    },
                    areaStyle: isArea
                        ? {
                              color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                  { offset: 0, color: `${sColor}45` },
                                  { offset: 0.7, color: `${sColor}10` },
                                  { offset: 1, color: `${sColor}00` },
                              ]),
                          }
                        : undefined,
                    markLine: idx === 0 ? targetMarkLine : undefined,
                    ...s,
                };
            }),
            ...props.options,
        };
    }

    // 2. VERTICAL BAR CHART
    if (props.type === 'bar') {
        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Total', data: props.data }];

        return {
            color: colors,
            grid: baseGrid,
            tooltip: baseTooltip,
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
            series: rawSeries.map((s, idx) => {
                const sColor = colors[idx % colors.length];
                return {
                    name: s.name,
                    type: 'bar',
                    stack: props.stacked ? 'total' : undefined,
                    data: s.data,
                    barMaxWidth: 26,
                    itemStyle: {
                        borderRadius: props.stacked ? [0, 0, 0, 0] : [8, 8, 0, 0],
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            { offset: 0, color: sColor },
                            { offset: 1, color: `${sColor}cc` },
                        ]),
                        shadowColor: `${sColor}25`,
                        shadowBlur: 6,
                        shadowOffsetY: 3,
                    },
                    markLine: idx === 0 ? targetMarkLine : undefined,
                    ...s,
                };
            }),
            ...props.options,
        };
    }

    // 3. HORIZONTAL BAR (LEADERBOARD / RANKING)
    if (props.type === 'bar-horizontal') {
        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Skor', data: props.data }];

        return {
            color: colors,
            grid: {
                top: 18,
                right: 32,
                bottom: 18,
                left: 14,
                containLabel: true,
            },
            tooltip: baseTooltip,
            xAxis: {
                type: 'value',
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            yAxis: {
                type: 'category',
                data: props.categories,
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: { ...baseAxisLabel, fontWeight: 600 },
                inverse: true,
            },
            series: rawSeries.map((s, idx) => {
                const sColor = colors[idx % colors.length];
                return {
                    name: s.name,
                    type: 'bar',
                    data: s.data,
                    barMaxWidth: 18,
                    itemStyle: {
                        borderRadius: [0, 999, 999, 0],
                        color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [
                            { offset: 0, color: `${sColor}99` },
                            { offset: 1, color: sColor },
                        ]),
                        shadowColor: `${sColor}30`,
                        shadowBlur: 6,
                    },
                    label: {
                        show: true,
                        position: 'right',
                        color: isDark ? '#f8fafc' : '#0f172a',
                        fontWeight: 700,
                        fontSize: 11,
                    },
                    ...s,
                };
            }),
            ...props.options,
        };
    }

    // 4. DONUT / PIE CHART
    if (props.type === 'donut' || props.type === 'pie') {
        const isDonut = props.type === 'donut';
        const pieData = Array.isArray(props.data) ? props.data : [];

        return {
            color: colors,
            tooltip: {
                trigger: 'item',
                ...baseTooltip,
                formatter: '{b}: <strong>{c}</strong> ({d}%)',
            },
            legend: {
                bottom: 4,
                left: 'center',
                icon: 'circle',
                itemWidth: 8,
                itemHeight: 8,
                itemGap: 16,
                textStyle: baseAxisLabel,
            },
            series: [
                {
                    name: props.title || 'Distribusi',
                    type: 'pie',
                    radius: isDonut ? ['52%', '76%'] : '74%',
                    center: ['50%', '46%'],
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
                        position: isDonut ? 'center' : 'outside',
                        color: isDark ? '#f8fafc' : '#0f172a',
                        fontWeight: 600,
                        fontSize: 12,
                    },
                    data: pieData,
                    ...props.options.series?.[0],
                },
            ],
            ...props.options,
        };
    }

    // 5. RADAR COMPETENCY CHART
    if (props.type === 'radar') {
        const indicators = props.categories.map((c) => ({
            name: typeof c === 'string' ? c : c.name,
            max: typeof c === 'object' && c.max ? c.max : 100,
        }));

        const rawSeries = props.series.length > 0
            ? props.series
            : [{ name: props.title || 'Skor', value: props.data }];

        return {
            color: colors,
            tooltip: {
                trigger: 'item',
                ...baseTooltip,
            },
            radar: {
                indicator: indicators,
                shape: 'polygon',
                splitNumber: 4,
                axisName: {
                    color: isDark ? '#cbd5e1' : '#475569',
                    fontSize: 11,
                    fontWeight: 600,
                    fontFamily: 'inherit',
                },
                splitLine: {
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(203, 213, 225, 0.8)',
                    },
                },
                splitArea: {
                    show: true,
                    areaStyle: {
                        color: isDark
                            ? ['rgba(30, 41, 59, 0.3)', 'rgba(15, 23, 42, 0.4)']
                            : ['rgba(248, 250, 252, 0.6)', 'rgba(241, 245, 249, 0.85)'],
                    },
                },
                axisLine: {
                    lineStyle: {
                        color: isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(203, 213, 225, 0.9)',
                    },
                },
            },
            series: [
                {
                    type: 'radar',
                    data: rawSeries.map((s, idx) => {
                        const sColor = colors[idx % colors.length];
                        return {
                            name: s.name,
                            value: s.value || s.data,
                            symbolSize: 6,
                            itemStyle: { color: sColor },
                            lineStyle: { width: 2.5, color: sColor },
                            areaStyle: {
                                color: new echarts.graphic.RadialGradient(0.5, 0.5, 1, [
                                    { offset: 0, color: `${sColor}50` },
                                    { offset: 1, color: `${sColor}15` },
                                ]),
                            },
                            ...s,
                        };
                    }),
                },
            ],
            ...props.options,
        };
    }

    // 6. GAUGE KPI PERFORMANCE METER
    if (props.type === 'gauge') {
        const gaugeValue = typeof props.data === 'number' ? props.data : (props.data?.[0]?.value || 0);

        return {
            color: colors,
            series: [
                {
                    type: 'gauge',
                    startAngle: 190,
                    endAngle: -10,
                    min: 0,
                    max: 100,
                    splitNumber: 5,
                    radius: '90%',
                    center: ['50%', '62%'],
                    itemStyle: {
                        color: primaryColor,
                        shadowColor: `${primaryColor}40`,
                        shadowBlur: 10,
                        shadowOffsetY: 4,
                    },
                    progress: {
                        show: true,
                        roundCap: true,
                        width: 14,
                    },
                    pointer: {
                        show: true,
                        length: '58%',
                        width: 4,
                        itemStyle: { color: isDark ? '#f8fafc' : '#0f172a' },
                    },
                    axisLine: {
                        roundCap: true,
                        lineStyle: {
                            width: 14,
                            color: [[1, isDark ? 'rgba(255, 255, 255, 0.1)' : '#f1f5f9']],
                        },
                    },
                    axisTick: { show: false },
                    splitLine: {
                        length: 8,
                        lineStyle: {
                            width: 2,
                            color: isDark ? 'rgba(255, 255, 255, 0.2)' : '#cbd5e1',
                        },
                    },
                    axisLabel: {
                        distance: 20,
                        color: isDark ? '#94a3b8' : '#64748b',
                        fontSize: 10,
                        fontFamily: 'inherit',
                    },
                    title: {
                        show: true,
                        offsetCenter: [0, '35%'],
                        fontSize: 12,
                        fontWeight: 600,
                        color: isDark ? '#94a3b8' : '#64748b',
                        fontFamily: 'inherit',
                    },
                    detail: {
                        valueAnimation: true,
                        offsetCenter: [0, '70%'],
                        fontSize: 26,
                        fontWeight: 800,
                        fontFamily: 'inherit',
                        color: isDark ? '#ffffff' : '#0f172a',
                        formatter: '{value}%',
                    },
                    data: [
                        {
                            value: gaugeValue,
                            name: props.metricLabel || props.title || 'Capaian KPI',
                        },
                    ],
                },
            ],
            ...props.options,
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
                trigger: 'item',
                ...baseTooltip,
                formatter: (p) => {
                    const d = p.data;
                    const pct = ((d.value / maxVal) * 100).toFixed(1);
                    return `<strong>${d.name}</strong><br/>Kandidat: <strong>${d.value.toLocaleString('id-ID')} Orang</strong><br/>Konversi Total: <strong>${pct}%</strong>`;
                },
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
                        formatter: '{title|{b}}\\n{val|{c} Kandidat ({d}%)}',
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
                    ...props.options.series?.[0],
                },
            ],
            ...props.options,
        };
    }

    // 8. 9-BOX TALENT ASSESSMENT SCATTER MATRIX
    if (props.type === 'scatter') {
        return {
            color: colors,
            grid: {
                top: 30,
                right: 30,
                bottom: 35,
                left: 35,
                containLabel: true,
            },
            tooltip: {
                trigger: 'item',
                ...baseTooltip,
                formatter: (p) => {
                    const d = p.data;
                    return `<strong>${d[2] || 'Talenta'}</strong><br/>Kinerja (Performance): <strong>${d[0]}</strong><br/>Potensi (Potential): <strong>${d[1]}</strong>`;
                },
            },
            xAxis: {
                type: 'value',
                name: 'Kinerja (Performance)',
                nameLocation: 'middle',
                nameGap: 24,
                nameTextStyle: { ...baseAxisLabel, fontWeight: 700 },
                min: 0,
                max: 100,
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            yAxis: {
                type: 'value',
                name: 'Potensi (Potential)',
                nameLocation: 'middle',
                nameGap: 24,
                nameTextStyle: { ...baseAxisLabel, fontWeight: 700 },
                min: 0,
                max: 100,
                axisLabel: baseAxisLabel,
                splitLine: baseSplitLine,
            },
            series: [
                {
                    type: 'scatter',
                    symbolSize: (data) => Math.max(12, (data[3] || 16)),
                    data: Array.isArray(props.data) ? props.data : [],
                    itemStyle: {
                        color: new echarts.graphic.RadialGradient(0.4, 0.3, 1, [
                            { offset: 0, color: `${primaryColor}ee` },
                            { offset: 1, color: `${primaryColor}88` },
                        ]),
                        shadowColor: `${primaryColor}40`,
                        shadowBlur: 10,
                        shadowOffsetY: 4,
                    },
                },
            ],
            ...props.options,
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
                formatter: '{b}: <strong>Rp {c} Juta</strong>',
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
                        formatter: '{title|{b}}\\n{val|Rp {c} Jt}',
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
            ...props.options,
        };
    }

    // 10. ROUNDED HEATMAP (ACTIVITY / ATTENDANCE MATRIX)
    if (props.type === 'heatmap') {
        const heatmapData = Array.isArray(props.data) ? props.data : [];
        const xData = props.categories.length > 0 ? props.categories : ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'];
        const yData = props.series.length > 0 && props.series[0].categories
            ? props.series[0].categories
            : ['Sen', 'Sel', 'Rab', 'Kam', 'Jum'];

        let maxVal = 10;
        heatmapData.forEach((d) => {
            const val = Array.isArray(d) ? d[2] : (d.value || 0);
            if (val > maxVal) maxVal = val;
        });

        return {
            color: colors,
            grid: {
                top: 20,
                right: 18,
                bottom: 45,
                left: 14,
                containLabel: true,
            },
            tooltip: {
                position: 'top',
                ...baseTooltip,
                formatter: (p) => {
                    const d = p.data;
                    const val = Array.isArray(d) ? d[2] : d;
                    const xLabel = xData[d[0]] || d[0];
                    const yLabel = yData[d[1]] || d[1];
                    return `<strong>${yLabel} · ${xLabel}</strong><br/>Intensitas Aktivitas: <strong>${val} Log</strong>`;
                },
            },
            xAxis: {
                type: 'category',
                data: xData,
                splitArea: { show: false },
                axisLine: { show: false },
                axisTick: { show: false },
                axisLabel: baseAxisLabel,
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
                bottom: 2,
                itemWidth: 12,
                itemHeight: 80,
                inRange: {
                    color: isDark
                        ? ['rgba(30, 41, 59, 0.6)', `${primaryColor}66`, `${primaryColor}cc`, primaryColor]
                        : [`${primaryColor}15`, `${primaryColor}40`, `${primaryColor}88`, primaryColor],
                },
                textStyle: baseAxisLabel,
            },
            series: [
                {
                    name: props.title || 'Aktivitas',
                    type: 'heatmap',
                    data: heatmapData,
                    label: { show: false },
                    itemStyle: {
                        borderRadius: 8,
                        borderColor: isDark ? '#0f172a' : '#ffffff',
                        borderWidth: 3,
                        shadowColor: 'rgba(15, 23, 42, 0.04)',
                        shadowBlur: 4,
                    },
                    emphasis: {
                        itemStyle: {
                            borderRadius: 10,
                            borderColor: primaryColor,
                            borderWidth: 2,
                            shadowBlur: 10,
                            shadowColor: `${primaryColor}60`,
                        },
                    },
                },
            ],
            ...props.options,
        };
    }

    return props.options;
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
        () => props.sentiment,
        () => props.type,
        () => props.variant,
        () => props.targetValue,
        () => props.dataZoom,
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
    color: var(--hcis-slate-600, #475569);
    border-radius: 6px;
    cursor: pointer;
    line-height: 1.2;
    transition: all 140ms ease;
}

.hcis-chart__time-chip:hover {
    color: var(--hcis-text-strong, #0f172a);
    background: rgba(255, 255, 255, 0.6);
}

.hcis-chart__time-chip--active {
    background: #ffffff !important;
    color: var(--hcis-brand-600, #4f46e5) !important;
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
    color: var(--hcis-slate-600, #475569);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.875rem;
    transition: all 140ms ease;
}

.hcis-chart__tool-btn:hover {
    background: rgba(255, 255, 255, 0.8);
    color: var(--hcis-brand-600, #4f46e5);
}

.hcis-chart__tool-btn--active {
    background: #ffffff !important;
    color: var(--hcis-brand-600, #4f46e5) !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.hcis-chart__tool-btn--export {
    border: 1px solid rgba(226, 232, 240, 0.85);
    background: rgba(241, 245, 249, 0.9);
}

.hcis-chart__tool-btn--export:hover {
    border-color: rgba(165, 180, 252, 0.8);
    background: #ffffff;
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
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.1;
}

.hcis-chart__metric-unit {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
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
    background-color: var(--hcis-success-50, #ecfdf5);
    color: var(--hcis-success-700, #047857);
    border: 1px solid rgba(167, 243, 208, 0.8);
}

.hcis-chart__metric-trend--down {
    background-color: var(--hcis-danger-50, #fef2f2);
    color: var(--hcis-danger-700, #b91c1c);
    border: 1px solid rgba(254, 202, 202, 0.8);
}

.hcis-chart__metric-label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--hcis-text-muted, #64748b);
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
    background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%);
    color: #ffffff;
    font-size: 0.65625rem;
    font-weight: 750;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    flex-shrink: 0;
    box-shadow: 0 2px 6px -1px rgba(124, 58, 237, 0.35);
}

.hcis-chart__ai-text {
    font-size: 0.775rem;
    color: var(--hcis-slate-700, #334155);
    font-weight: 500;
    margin: 0;
    line-height: 1.35;
}

/* Canvas Container */
.hcis-chart__canvas-container {
    position: relative;
    width: 100%;
    min-height: 160px;
    overflow: hidden;
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

.hcis-chart__spinner {
    width: 2rem;
    height: 2rem;
    border: 2.5px solid rgba(99, 102, 241, 0.2);
    border-top-color: var(--hcis-brand-600, #4f46e5);
    border-radius: 50%;
    animation: hcis-chart-spin 0.8s linear infinite;
}

@keyframes hcis-chart-spin {
    to { transform: rotate(360deg); }
}

.hcis-chart__loading-text,
.hcis-chart__empty-text {
    font-size: 0.8125rem;
    font-weight: 550;
    color: var(--hcis-text-muted, #64748b);
}

.hcis-chart__empty-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 12px;
    background: #f1f5f9;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
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
    color: #94a3b8 !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__time-chip:hover,
.hcis-chart-wrapper--variant-obsidian .hcis-chart__tool-btn:hover {
    color: #ffffff !important;
    background: rgba(51, 65, 85, 0.6) !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__time-chip--active,
.hcis-chart-wrapper--variant-obsidian .hcis-chart__tool-btn--active {
    background: #1e293b !important;
    color: #818cf8 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(129, 140, 248, 0.3) !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__metric-value {
    color: #ffffff !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__metric-unit,
.hcis-chart-wrapper--variant-obsidian .hcis-chart__metric-label {
    color: #94a3b8 !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__ai-capsule {
    background: linear-gradient(135deg, rgba(30, 27, 75, 0.6) 0%, rgba(15, 23, 42, 0.8) 100%) !important;
    border-color: rgba(139, 92, 246, 0.3) !important;
}

.hcis-chart-wrapper--variant-obsidian .hcis-chart__ai-text {
    color: #cbd5e1 !important;
}
</style>
"""

with open(chart_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("SUCCESS: HcisChart.vue completely clean with Funnel borderRadius: 10 and gap: 8!")
