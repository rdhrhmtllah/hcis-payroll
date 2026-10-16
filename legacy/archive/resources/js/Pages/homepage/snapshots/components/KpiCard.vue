<template>
    <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white h-100">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <div class="icon-box" style="background:#EEF2FF;">
                    <i class="bi bi-graph-up-arrow" style="color:#6366F1;"></i>
                </div>
                Key Performance Indicator
            </h5>
            <span class="badge rounded-pill px-3 py-2"
                :class="data.status === 'On Track' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'">
                {{ data.status }}
            </span>
        </div>

        <!-- Score + Indicators -->
        <div class="row align-items-center mb-4">
            <div class="col-md-4 text-center mb-3 mb-md-0">
                <div class="kpi-radial-wrap d-inline-block position-relative">
                    <apexchart type="radialBar" height="160" :options="radialOpts" :series="[data.current_score || 0]" />
                </div>
            </div>
            <div class="col-md-8">
                <h6 class="section-label">Indikator Utama</h6>
                <div v-for="(ind, i) in data.top_indicators" :key="i" class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="fw-bold">{{ ind.name }}</small>
                        <small class="text-muted">{{ ind.score }}% <span class="opacity-50">(w: {{ ind.weight }})</span></small>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar" style="background:#6366F1;border-radius:3px;" :style="{ width: ind.score+'%' }"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trend Chart -->
        <div class="mb-4">
            <h6 class="section-label">Tren Skor KPI (6 Bulan)</h6>
            <apexchart type="line" height="140" :options="trendOpts" :series="trendSeries" />
        </div>

        <!-- Footer -->
        <div class="d-flex justify-content-between align-items-center p-3 rounded-4 bg-light mt-auto">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 bg-white rounded-3 shadow-sm"><i class="bi bi-clipboard-check" style="color:#6366F1;"></i></div>
                <div>
                    <small class="text-muted d-block">Pending Reviews</small>
                    <span class="fw-bold">{{ data.pending_reviews }} Item</span>
                </div>
            </div>
            <button id="cta-kpi" class="btn rounded-pill px-4 fw-bold btn-sm text-white" style="background:#6366F1;" @click="$emit('navigate', '/kpi')">
                Review Sekarang
            </button>
        </div>
    </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';

export default {
    name: 'KpiCard',
    components: { apexchart: VueApexCharts },
    props: { data: { type: Object, required: true } },
    emits: ['navigate'],
    computed: {
        radialOpts() {
            return {
                chart: { type: 'radialBar' },
                plotOptions: {
                    radialBar: {
                        hollow: { size: '55%' },
                        track: { background: '#EEF2FF', strokeWidth: '100%' },
                        dataLabels: {
                            name: { show: true, fontSize: '11px', color: '#94A3B8', offsetY: 18 },
                            value: { show: true, fontSize: '1.75rem', fontWeight: 700, color: '#6366F1', offsetY: -12, formatter: v => parseFloat(v).toFixed(1) },
                        },
                    },
                },
                fill: { colors: ['#6366F1'] },
                stroke: { lineCap: 'round' },
                labels: ['SKOR KPI'],
            };
        },
        trendSeries() {
            return [{ name: 'KPI Score', data: this.data.monthly_trend || [0,0,0,0,0,0] }];
        },
        trendOpts() {
            return {
                chart: { type: 'line', toolbar: { show: false } },
                colors: ['#6366F1'],
                stroke: { curve: 'smooth', width: 3 },
                markers: { size: 4, colors: ['#6366F1'], strokeColors: '#fff', strokeWidth: 2 },
                xaxis: { categories: ['Okt','Nov','Des','Jan','Feb','Mar'], labels: { style: { fontSize: '10px', colors: '#94A3B8' } } },
                yaxis: { show: false },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 3 },
                dataLabels: { enabled: true, style: { fontSize: '10px', colors: ['#6366F1'] }, background: { enabled: true, foreColor: '#fff', borderRadius: 4, borderColor: '#EEF2FF' } },
                tooltip: { theme: 'light' },
            };
        },
    },
};
</script>

<style scoped>
.icon-box { width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:10px;font-size:1.15rem;flex-shrink:0; }
.section-label { font-weight:700;margin-bottom:0.75rem;font-size:0.75rem;text-transform:uppercase;color:#94A3B8;letter-spacing:0.05em; }
</style>
