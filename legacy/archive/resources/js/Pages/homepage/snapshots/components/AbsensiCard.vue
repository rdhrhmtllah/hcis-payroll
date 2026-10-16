<template>
    <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white h-100">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <div class="icon-box" style="background:#ECFDF5;">
                    <i class="bi bi-calendar-check" style="color:#10B981;"></i>
                </div>
                Absensi & Kehadiran
            </h5>
            <span class="badge rounded-pill px-3 py-2"
                :class="statusBadgeClass">
                <i :class="statusIcon" class="me-1"></i>{{ data.status }}
            </span>
        </div>

        <!-- Check In / Out / Cuti -->
        <div class="row g-3 mb-4">
            <div class="col-4">
                <div class="stat-box p-3 rounded-4 text-center" style="background:#e7f1ff;">
                    <small class="text-muted d-block mb-1">Check In</small>
                    <span class="fw-bold h5 mb-0 text-primary">{{ data.check_in }}</span>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-box p-3 rounded-4 text-center" style="background:#fff5e9;">
                    <small class="text-muted d-block mb-1">Check Out</small>
                    <span class="fw-bold h5 mb-0" style="color:#fd7e14;">{{ data.check_out }}</span>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-box p-3 rounded-4 text-center" style="background:#e9f7ef;">
                    <small class="text-muted d-block mb-1">Sisa Cuti</small>
                    <span class="fw-bold h5 mb-0 text-success">{{ data.leave_remaining }}</span>
                </div>
            </div>
        </div>

        <!-- Detail Stats Row -->
        <div class="row g-2 mb-4">
            <div class="col-6 col-md-3" v-for="(s, i) in detailStats" :key="i">
                <div class="p-2 rounded-3 bg-light text-center">
                    <small class="text-muted d-block" style="font-size:0.7rem;">{{ s.label }}</small>
                    <span class="fw-bold small" :style="{ color: s.color }">{{ s.value }}</span>
                </div>
            </div>
        </div>

        <!-- Chart + Timeline Row -->
        <div class="row mb-4">
            <div class="col-md-7">
                <h6 class="section-label">Tren Kehadiran Mingguan</h6>
                <apexchart type="area" height="160" :options="chartOptions" :series="chartSeries" />
            </div>
            <div class="col-md-5">
                <h6 class="section-label">Log Aktivitas</h6>
                <div class="activity-timeline">
                    <div v-for="(log, i) in data.recent_logs" :key="i" class="d-flex gap-3 mb-3">
                        <div class="text-center" style="width:50px;flex-shrink:0;">
                            <small class="fw-bold">{{ log.time }}</small>
                        </div>
                        <div class="border-start ps-3 position-relative" style="border-color:#d1fae5 !important;">
                            <div class="timeline-dot" :style="{ background: dotColor(log) }"></div>
                            <p class="mb-0 small fw-bold">{{ log.activity }}</p>
                            <small class="text-muted">{{ log.status }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <div class="mt-auto">
            <button id="cta-absensi" class="btn btn-outline-success w-100 rounded-pill fw-bold btn-sm" @click="$emit('navigate', '/uDash')">
                Lihat Detail Kehadiran <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';

export default {
    name: 'AbsensiCard',
    components: { apexchart: VueApexCharts },
    props: {
        data: { type: Object, required: true },
    },
    emits: ['navigate'],
    computed: {
        statusBadgeClass() {
            const s = (this.data.status || '').toUpperCase();
            if (s === 'HADIR') return 'bg-success-subtle text-success';
            if (s === 'TERLAMBAT') return 'bg-warning-subtle text-warning';
            return 'bg-secondary-subtle text-secondary';
        },
        statusIcon() {
            const s = (this.data.status || '').toUpperCase();
            if (s === 'HADIR') return 'bi bi-check-circle-fill';
            if (s === 'TERLAMBAT') return 'bi bi-clock-fill';
            return 'bi bi-dash-circle';
        },
        detailStats() {
            return [
                { label: 'Hadir', value: `${this.data.total_hadir ?? 0}/${this.data.total_hari_kerja ?? 0}`, color: '#10B981' },
                { label: 'Terlambat', value: `${this.data.total_terlambat_menit ?? 0} min`, color: '#F59E0B' },
                { label: 'Lembur', value: `${this.data.total_lembur_jam ?? 0} jam`, color: '#6366F1' },
                { label: 'Sakit/Izin/Cuti', value: `${(this.data.total_sakit ?? 0) + (this.data.total_izin ?? 0) + (this.data.total_cuti ?? 0)}`, color: '#EC4899' },
            ];
        },
        chartSeries() {
            return [{ name: 'Kehadiran %', data: this.data.weekly_stats || [0,0,0,0,0,0,0] }];
        },
        chartOptions() {
            return {
                chart: { type: 'area', toolbar: { show: false }, sparkline: { enabled: false } },
                colors: ['#10B981'],
                stroke: { curve: 'smooth', width: 2.5 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
                xaxis: { categories: ['Sen','Sel','Rab','Kam','Jum','Sab','Min'], labels: { style: { fontSize: '10px', colors: '#94A3B8' } } },
                yaxis: { show: false, min: 0, max: 100 },
                dataLabels: { enabled: false },
                grid: { show: false },
                tooltip: { theme: 'light', y: { formatter: v => v + '%' } },
            };
        },
    },
    methods: {
        dotColor(log) {
            if (log.type === 'checkin') return '#10B981';
            if (log.type === 'checkout') return '#EF4444';
            return '#3B82F6';
        },
    },
};
</script>

<style scoped>
.icon-box { width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:10px;font-size:1.15rem;flex-shrink:0; }
.section-label { font-weight:700;margin-bottom:0.75rem;font-size:0.75rem;text-transform:uppercase;color:#94A3B8;letter-spacing:0.05em; }
.stat-box { transition: transform 0.2s ease; }
.stat-box:hover { transform: translateY(-2px); }
.activity-timeline { max-height:200px;overflow-y:auto;padding-right:4px; }
.timeline-dot { position:absolute;left:-6px;top:5px;width:10px;height:10px;border-radius:50%;border:2px solid #fff; }
</style>
