<template>
    <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <div class="icon-box" style="background:#FFFBEB;"><i class="bi bi-person-badge" style="color:#F59E0B;"></i></div>
                Visitor Management
            </h5>
            <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-2">{{ data.active_now }} Aktif</span>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-7">
                <h6 class="section-label">Statistik Hari Ini</h6>
                <div class="row g-3 mb-3">
                    <div class="col-6 text-center">
                        <div class="p-3 rounded-4 bg-light"><h3 class="fw-bold mb-0" style="color:#F59E0B;">{{ data.total_today }}</h3><small class="text-muted">Total</small></div>
                    </div>
                    <div class="col-6 text-center">
                        <div class="p-3 rounded-4 bg-light"><h3 class="fw-bold mb-0 text-dark">{{ data.scheduled_appointments }}</h3><small class="text-muted">Terjadwal</small></div>
                    </div>
                </div>
                <h6 class="section-label">Kepadatan Pengunjung</h6>
                <apexchart type="bar" height="110" :options="chartOpts" :series="chartSeries" />
            </div>
            <div class="col-md-5">
                <h6 class="section-label">Pengunjung Terbaru</h6>
                <div class="visitor-list">
                    <div v-for="(v, i) in data.recent_visitors" :key="i" class="p-2 rounded-3 mb-2 bg-light d-flex align-items-center gap-2">
                        <div class="bg-white rounded-circle d-flex align-items-center justify-content-center border shadow-sm" style="width:32px;height:32px;flex-shrink:0;">
                            <i class="bi bi-person small"></i>
                        </div>
                        <div class="overflow-hidden">
                            <p class="mb-0 small fw-bold text-truncate">{{ v.name }}</p>
                            <small class="text-muted d-block text-truncate">{{ v.company }} · {{ v.time }}</small>
                        </div>
                        <span class="ms-auto rounded-circle flex-shrink-0" :class="v.status==='Active'?'bg-success':v.status==='Waiting'?'bg-warning':'bg-secondary'" style="width:8px;height:8px;display:block;"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center p-3 rounded-4 mt-auto" style="background:#fffdf1;">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 bg-white rounded-3 shadow-sm" style="color:#F59E0B;"><i class="bi bi-shield-check"></i></div>
                <div><small class="text-muted d-block">Tujuan Utama</small><span class="fw-bold">{{ data.top_purpose }}</span></div>
            </div>
            <button id="cta-visitor" class="btn btn-warning rounded-pill px-4 fw-bold btn-sm text-white" @click="$emit('navigate','/visitor/tamu')">Kelola Pengunjung</button>
        </div>
    </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
export default {
    name: 'VisitorCard',
    components: { apexchart: VueApexCharts },
    props: { data: { type: Object, required: true } },
    emits: ['navigate'],
    computed: {
        chartSeries() { return [{ name: 'Pengunjung', data: this.data.hourly_visitors || [] }]; },
        chartOpts() {
            return {
                chart: { type:'bar', toolbar:{ show:false } },
                colors: ['#F59E0B'],
                plotOptions: { bar: { borderRadius:4, columnWidth:'60%' } },
                xaxis: { categories:['08','09','10','11','12','13','14','15'], labels:{ style:{ fontSize:'9px',colors:'#94A3B8' } } },
                yaxis: { show:false }, dataLabels:{ enabled:false }, grid:{ show:false },
                tooltip: { theme:'light', y:{ formatter: v => v+' orang' } },
            };
        },
    },
};
</script>

<style scoped>
.icon-box { width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:10px;font-size:1.15rem;flex-shrink:0; }
.section-label { font-weight:700;margin-bottom:0.75rem;font-size:0.75rem;text-transform:uppercase;color:#94A3B8;letter-spacing:0.05em; }
.visitor-list { max-height:200px;overflow-y:auto; }
</style>
