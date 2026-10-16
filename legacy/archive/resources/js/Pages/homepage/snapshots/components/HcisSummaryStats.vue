<template>
    <div class="row g-3">
        <div v-for="item in statItems" :key="item.label" class="col-6 col-md-4 col-xl-2">
            <div class="stat-tile h-100">
                <div class="stat-icon" :style="{ background: item.bg, color: item.color }">
                    <i :class="item.icon"></i>
                </div>
                <small class="text-muted text-uppercase fw-semibold">{{ item.label }}</small>
                <div class="stat-value">{{ item.value }}</div>
                <small class="stat-helper">{{ item.helper }}</small>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'HcisSummaryStats',
    props: {
        leaveSummary: {
            type: Object,
            default: () => ({}),
        },
        monthlyStats: {
            type: Object,
            default: () => ({}),
        },
    },
    computed: {
        statItems() {
            return [
                {
                    label: 'Sisa Cuti',
                    value: `${this.leaveSummary?.remaining ?? 0} hari`,
                    helper: 'Saldo aktif',
                    icon: 'bi bi-briefcase-fill',
                    bg: '#ecfeff',
                    color: '#0f766e',
                },
                {
                    label: 'Hutang Cuti',
                    value: `${this.leaveSummary?.debt ?? 0} hari`,
                    helper: 'Saldo minus',
                    icon: 'bi bi-exclamation-diamond-fill',
                    bg: '#fff1f2',
                    color: '#be123c',
                },
                {
                    label: 'Terlambat',
                    value: `${this.monthlyStats?.late_count ?? 0}x`,
                    helper: `${this.monthlyStats?.late_minutes ?? 0} menit`,
                    icon: 'bi bi-alarm-fill',
                    bg: '#fffbeb',
                    color: '#b45309',
                },
                {
                    label: 'Kehadiran',
                    value: `${this.monthlyStats?.attendance_rate ?? 0}%`,
                    helper: `${this.monthlyStats?.present_days ?? 0}/${this.monthlyStats?.work_days ?? 0} hari`,
                    icon: 'bi bi-person-check-fill',
                    bg: '#ecfdf5',
                    color: '#047857',
                },
                {
                    label: 'Izin/Sakit/Cuti',
                    value: `${(this.monthlyStats?.izin_count ?? 0) + (this.monthlyStats?.sakit_count ?? 0) + (this.monthlyStats?.cuti_count ?? 0)}x`,
                    helper: `${this.monthlyStats?.izin_count ?? 0} izin | ${this.monthlyStats?.sakit_count ?? 0} sakit`,
                    icon: 'bi bi-file-earmark-medical-fill',
                    bg: '#f5f3ff',
                    color: '#6d28d9',
                },
                {
                    label: 'Lembur',
                    value: `${this.monthlyStats?.overtime_hours ?? 0} jam`,
                    helper: 'Bulan berjalan',
                    icon: 'bi bi-clock-history',
                    bg: '#eff6ff',
                    color: '#1d4ed8',
                },
            ];
        },
    },
};
</script>

<style scoped>
.stat-tile {
    background: #fff;
    border-radius: 1.1rem;
    padding: 1rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.04);
}

.stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.8rem;
    font-size: 1rem;
}

.stat-value {
    font-size: 1.15rem;
    font-weight: 800;
    margin-top: 0.25rem;
}

.stat-helper {
    color: #64748b;
    display: block;
    margin-top: 0.2rem;
}
</style>
