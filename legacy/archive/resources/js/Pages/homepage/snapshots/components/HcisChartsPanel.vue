<template>
    <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <p class="section-kicker mb-1">Heavy Analytics</p>
                <h5 class="fw-bold mb-0">Grafik HCIS Absensi</h5>
            </div>
            <span class="badge bg-dark rounded-pill">Data nyata, bukan mock</span>
        </div>

        <div v-if="hasChartData" class="row g-4">
            <div class="col-12 col-xl-8">
                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <h6 class="fw-bold mb-1">Attendance Trend</h6>
                            <small>Tren hadir vs keterlambatan beberapa hari terakhir</small>
                        </div>
                    </div>
                    <apexchart type="line" height="300" :options="attendanceTrendOptions" :series="charts.attendance_trend?.series || []" />
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <h6 class="fw-bold mb-1">Status Breakdown</h6>
                            <small>Distribusi status bulan berjalan</small>
                        </div>
                    </div>
                    <apexchart type="donut" height="300" :options="statusBreakdownOptions" :series="charts.status_breakdown?.series || []" />
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <h6 class="fw-bold mb-1">Overtime Trend</h6>
                            <small>Total lembur per pekan</small>
                        </div>
                    </div>
                    <apexchart type="bar" height="280" :options="overtimeOptions" :series="charts.overtime_trend?.series || []" />
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <h6 class="fw-bold mb-1">Shift Mix</h6>
                            <small>Distribusi shift yang paling sering muncul</small>
                        </div>
                    </div>
                    <apexchart type="bar" height="280" :options="shiftMixOptions" :series="charts.shift_mix?.series || []" />
                </div>
            </div>

            <div class="col-12">
                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <h6 class="fw-bold mb-1">Attendance Heatmap</h6>
                            <small>0 = absen kosong, 1 = OFF, 2 = hadir</small>
                        </div>
                    </div>
                    <apexchart type="heatmap" height="320" :options="heatmapOptions" :series="charts.attendance_heatmap?.series || []" />
                </div>
            </div>

            <div class="col-12" :class="charts.leader_team_chart ? 'col-xl-8' : 'col-xl-12'">
                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <h6 class="fw-bold mb-1">Check-In Time Trend</h6>
                            <small>Jam check-in aktual beberapa hari terakhir</small>
                        </div>
                    </div>
                    <apexchart type="area" height="280" :options="checkinOptions" :series="charts.checkin_time_trend?.series || []" />
                </div>
            </div>

            <div v-if="charts.leader_team_chart" class="col-12 col-xl-4">
                <div class="chart-card">
                    <div class="chart-head">
                        <div>
                            <h6 class="fw-bold mb-1">Leader Team Chart</h6>
                            <small>Distribusi status tim hari ini</small>
                        </div>
                    </div>
                    <apexchart type="polarArea" height="280" :options="leaderOptions" :series="charts.leader_team_chart?.series || []" />
                </div>
            </div>
        </div>

        <div v-else class="chart-empty">
            Grafik HCIS akan tampil otomatis setelah histori absensi dan transaksi cukup untuk divisualisasikan.
        </div>
    </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';

export default {
    name: 'HcisChartsPanel',
    components: {
        apexchart: VueApexCharts,
    },
    props: {
        charts: {
            type: Object,
            default: () => ({}),
        },
    },
    computed: {
        hasChartData() {
            const hasSeries = (series) => Array.isArray(series) && series.some((item) => Array.isArray(item?.data) ? item.data.length > 0 : Number(item) > 0);
            const hasValueSeries = (series) => Array.isArray(series) && series.some((value) => Number(value) > 0);

            return (
                hasSeries(this.charts.attendance_trend?.series) ||
                hasValueSeries(this.charts.status_breakdown?.series) ||
                hasSeries(this.charts.overtime_trend?.series) ||
                hasSeries(this.charts.shift_mix?.series) ||
                hasSeries(this.charts.attendance_heatmap?.series) ||
                hasSeries(this.charts.checkin_time_trend?.series) ||
                hasValueSeries(this.charts.leader_team_chart?.series)
            );
        },
        attendanceTrendOptions() {
            return {
                chart: { toolbar: { show: false }, zoom: { enabled: false } },
                stroke: { width: [3, 2], curve: 'smooth' },
                colors: ['#0f766e', '#f97316'],
                xaxis: { categories: this.charts.attendance_trend?.categories || [] },
                yaxis: [
                    {
                        min: 0,
                        max: 100,
                        labels: { formatter: (value) => `${value}%` },
                    },
                    {
                        opposite: true,
                        labels: { formatter: (value) => `${value}m` },
                    },
                ],
                tooltip: { shared: true },
                grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                markers: { size: 4 },
            };
        },
        statusBreakdownOptions() {
            return {
                labels: this.charts.status_breakdown?.labels || [],
                colors: ['#047857', '#f59e0b', '#3b82f6', '#ef4444', '#06b6d4', '#7c3aed'],
                legend: { position: 'bottom' },
                chart: { toolbar: { show: false } },
                dataLabels: { enabled: true },
                stroke: { colors: ['#fff'] },
            };
        },
        overtimeOptions() {
            return {
                chart: { toolbar: { show: false } },
                colors: ['#2563eb'],
                plotOptions: { bar: { borderRadius: 8, columnWidth: '45%' } },
                xaxis: { categories: this.charts.overtime_trend?.categories || [] },
                dataLabels: { enabled: false },
                grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
            };
        },
        shiftMixOptions() {
            return {
                chart: { toolbar: { show: false } },
                colors: ['#8b5cf6'],
                plotOptions: { bar: { horizontal: true, borderRadius: 8 } },
                xaxis: { categories: this.charts.shift_mix?.categories || [] },
                dataLabels: { enabled: false },
                grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
            };
        },
        heatmapOptions() {
            return {
                chart: { toolbar: { show: false } },
                dataLabels: { enabled: false },
                colors: ['#0f766e'],
                xaxis: {
                    type: 'category',
                },
                plotOptions: {
                    heatmap: {
                        shadeIntensity: 0.35,
                        colorScale: {
                            ranges: [
                                { from: 0, to: 0, color: '#fee2e2', name: 'Kosong' },
                                { from: 1, to: 1, color: '#e2e8f0', name: 'OFF' },
                                { from: 2, to: 2, color: '#a7f3d0', name: 'Hadir' },
                            ],
                        },
                    },
                },
            };
        },
        checkinOptions() {
            return {
                chart: { toolbar: { show: false } },
                colors: ['#ea580c'],
                stroke: { curve: 'smooth', width: 3 },
                xaxis: { categories: this.charts.checkin_time_trend?.categories || [] },
                yaxis: {
                    labels: {
                        formatter: (value) => {
                            const hours = Math.floor(value / 60);
                            const minutes = Math.round(value % 60);
                            if (!Number.isFinite(hours) || !Number.isFinite(minutes)) return '--:--';
                            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
                        },
                    },
                },
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 },
                },
                dataLabels: { enabled: false },
                grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
            };
        },
        leaderOptions() {
            return {
                labels: this.charts.leader_team_chart?.labels || [],
                colors: ['#ef4444', '#f59e0b', '#3b82f6', '#ec4899', '#06b6d4', '#94a3b8', '#10b981'],
                stroke: { colors: ['#fff'] },
                legend: { position: 'bottom' },
                chart: { toolbar: { show: false } },
            };
        },
    },
};
</script>

<style scoped>
.section-kicker {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    font-weight: 700;
}

.chart-card {
    height: 100%;
    border-radius: 1.2rem;
    border: 1px solid #e2e8f0;
    background: linear-gradient(135deg, #ffffff, #f8fafc);
    padding: 1rem;
}

.chart-head {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
}

.chart-head small {
    color: #64748b;
}

.chart-empty {
    min-height: 180px;
    border-radius: 1rem;
    border: 1px dashed #cbd5e1;
    background: linear-gradient(135deg, #ffffff, #f8fafc);
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 1.25rem;
    font-weight: 600;
}
</style>
