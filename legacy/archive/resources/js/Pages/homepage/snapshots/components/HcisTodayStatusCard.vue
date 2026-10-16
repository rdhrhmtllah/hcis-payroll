<template>
    <div class="hero-card shadow-sm rounded-4 overflow-hidden h-100">
        <div class="hero-shell p-4 p-lg-5">
            <div class="d-flex flex-column flex-lg-row gap-4 justify-content-between">
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="eyebrow">HCIS Absensi Hari Ini</span>
                        <span class="status-pill" :class="`tone-${today?.status_tone || 'secondary'}`">
                            <i :class="statusIcon" class="me-1"></i>{{ today?.status_label || 'Status' }}
                        </span>
                    </div>

                    <h2 class="hero-title mb-2">{{ today?.headline || 'Status absensi belum tersedia.' }}</h2>
                    <p class="hero-subtitle mb-4">
                        {{ today?.shift_name || 'NO SHIFT' }}
                        <span v-if="today?.jam_kerja"> | {{ today.jam_kerja }}</span>
                        <span v-if="today?.status_hari_label"> | {{ today.status_hari_label }}</span>
                    </p>

                    <div class="row g-3">
                        <div class="col-6 col-lg-3" v-for="item in primaryItems" :key="item.label">
                            <div class="metric-card h-100">
                                <small class="metric-label">{{ item.label }}</small>
                                <div class="metric-value">{{ item.value }}</div>
                                <small v-if="item.helper" class="metric-helper">{{ item.helper }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button
                            v-for="action in visibleActions"
                            :key="action.label"
                            class="btn rounded-pill px-4 btn-sm fw-semibold"
                            :class="action.primary ? 'btn-light text-dark' : 'btn-outline-light'"
                            @click="$emit('navigate', action.route)"
                        >
                            <i :class="action.icon" class="me-1"></i>{{ action.label }}
                        </button>
                    </div>
                </div>

                <div class="hero-aside">
                    <div class="glance-card">
                        <div class="glance-top">
                            <span>Quick Glance</span>
                            <i class="bi bi-stars"></i>
                        </div>
                        <div class="glance-row">
                            <span>Shift</span>
                            <strong>{{ today?.shift_name || 'NO SHIFT' }}</strong>
                        </div>
                        <div class="glance-row">
                            <span>Jam Kerja</span>
                            <strong>{{ today?.jam_kerja || '--:-- - --:--' }}</strong>
                        </div>
                        <div class="glance-row">
                            <span>Keterlambatan</span>
                            <strong>
                                {{
                                    today?.late_minutes
                                        ? `${today.late_minutes} menit`
                                        : today?.running_late_minutes
                                          ? `${today.running_late_minutes} menit berjalan`
                                          : '0 menit'
                                }}
                            </strong>
                        </div>
                        <div class="glance-row" v-if="today?.device">
                            <span>Device</span>
                            <strong>{{ today.device }}</strong>
                        </div>
                        <div class="glance-row" v-if="today?.location">
                            <span>Lokasi</span>
                            <strong>{{ today.location }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'HcisTodayStatusCard',
    props: {
        today: {
            type: Object,
            required: true,
        },
    },
    emits: ['navigate'],
    computed: {
        primaryItems() {
            return [
                {
                    label: 'Check In',
                    value: this.today?.check_in || '--:--',
                    helper: this.today?.check_in ? 'Tercatat' : 'Belum ada catatan',
                },
                {
                    label: 'Check Out',
                    value: this.today?.check_out || '--:--',
                    helper: this.today?.check_out ? 'Tercatat' : 'Belum ada catatan',
                },
                {
                    label: 'Jam Masuk',
                    value: this.today?.jam_masuk || '--:--',
                    helper: this.today?.jam_keluar ? `Pulang ${this.today.jam_keluar}` : null,
                },
                {
                    label: 'Status Hari',
                    value: this.today?.status_hari_label || this.today?.status_label || '-',
                    helper: this.today?.is_day_off ? 'Tidak ada kewajiban hadir' : 'Hari kerja aktif',
                },
            ];
        },
        statusIcon() {
            const code = String(this.today?.status_code || '').toUpperCase();
            if (code.includes('OFF')) return 'bi bi-moon-stars-fill';
            if (code.includes('CUTI')) return 'bi bi-briefcase-fill';
            if (code.includes('SAKIT')) return 'bi bi-heart-pulse-fill';
            if (code.includes('IZIN')) return 'bi bi-envelope-fill';
            if (code.includes('TERLAMBAT')) return 'bi bi-alarm-fill';
            if (code.includes('CHECK_OUT')) return 'bi bi-box-arrow-right';
            if (code.includes('CHECK_IN')) return 'bi bi-box-arrow-in-right';
            return 'bi bi-calendar-check-fill';
        },
        visibleActions() {
            return (this.today?.cta || []).map((action, index) => ({
                ...action,
                primary: index === 0,
            }));
        },
    },
};
</script>

<style scoped>
.hero-card {
    background:
        radial-gradient(circle at 0% 0%, rgba(255, 255, 255, 0.22), transparent 30%),
        radial-gradient(circle at 100% 0%, rgba(255, 210, 180, 0.24), transparent 34%),
        linear-gradient(135deg, #10345d 0%, #14506f 42%, #1f7a6d 100%);
    color: #fff;
}

.hero-shell {
    position: relative;
}

.eyebrow {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.72);
}

.status-pill {
    border-radius: 999px;
    padding: 0.45rem 0.9rem;
    font-size: 0.78rem;
    font-weight: 700;
}

.status-pill.tone-success {
    background: rgba(207, 250, 224, 0.18);
    color: #d1fae5;
}

.status-pill.tone-warning {
    background: rgba(255, 238, 190, 0.18);
    color: #fde68a;
}

.status-pill.tone-danger {
    background: rgba(255, 211, 211, 0.18);
    color: #fecaca;
}

.status-pill.tone-info,
.status-pill.tone-primary {
    background: rgba(219, 234, 254, 0.18);
    color: #bfdbfe;
}

.status-pill.tone-secondary,
.status-pill.tone-dark {
    background: rgba(226, 232, 240, 0.18);
    color: #e2e8f0;
}

.hero-title {
    font-size: clamp(1.45rem, 2vw, 2.2rem);
    line-height: 1.08;
    font-weight: 800;
}

.hero-subtitle {
    color: rgba(255, 255, 255, 0.78);
    max-width: 780px;
}

.metric-card {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 1rem;
    padding: 1rem;
    backdrop-filter: blur(8px);
}

.metric-label {
    display: block;
    color: rgba(255, 255, 255, 0.65);
    margin-bottom: 0.35rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.metric-value {
    font-size: 1.15rem;
    font-weight: 800;
}

.metric-helper {
    display: block;
    margin-top: 0.3rem;
    color: rgba(255, 255, 255, 0.62);
}

.hero-aside {
    width: 100%;
    max-width: 320px;
}

.glance-card {
    background: rgba(6, 24, 38, 0.28);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 1.2rem;
    padding: 1rem 1.1rem;
}

.glance-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.82rem;
    font-weight: 800;
    color: rgba(255, 255, 255, 0.7);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 0.8rem;
}

.glance-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.7rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    font-size: 0.93rem;
}

.glance-row span {
    color: rgba(255, 255, 255, 0.66);
}

.glance-row strong {
    text-align: right;
}

@media (max-width: 991px) {
    .hero-aside {
        max-width: none;
    }
}
</style>
