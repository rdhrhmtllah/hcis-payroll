<template>
    <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <p class="section-kicker mb-1">Planning</p>
                <h5 class="fw-bold mb-0">Jadwal 7 Hari Ke Depan</h5>
            </div>
            <button class="btn btn-outline-dark btn-sm rounded-pill px-3" @click="$emit('navigate', '/self-shift')">
                Lihat Shift
            </button>
        </div>

        <div class="schedule-stack">
            <div v-for="item in schedule" :key="item.date" class="schedule-row" :class="{ today: item.is_today }">
                <div class="date-chip">
                    <span class="day">{{ item.date_label }}</span>
                    <span class="weekday">{{ item.day_label }}</span>
                </div>

                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                        <span class="fw-bold">{{ item.status_label }}</span>
                        <span class="badge rounded-pill" :class="badgeClass(item.status_tone)">
                            <i :class="item.icon" class="me-1"></i>{{ item.status_code.replaceAll('_', ' ') }}
                        </span>
                    </div>
                    <div class="text-muted small">
                        {{ item.jam_kerja || 'Tidak ada jam kerja aktif' }}
                        <span v-if="item.notes"> | {{ item.notes }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'HcisUpcomingSchedule',
    props: {
        schedule: {
            type: Array,
            default: () => [],
        },
    },
    emits: ['navigate'],
    methods: {
        badgeClass(tone) {
            const map = {
                success: 'bg-success-subtle text-success',
                warning: 'bg-warning-subtle text-warning',
                danger: 'bg-danger-subtle text-danger',
                info: 'bg-info-subtle text-info',
                primary: 'bg-primary-subtle text-primary',
                secondary: 'bg-secondary-subtle text-secondary',
                dark: 'bg-dark text-white',
            };

            return map[tone] || 'bg-light text-dark';
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

.schedule-stack {
    display: grid;
    gap: 0.9rem;
}

.schedule-row {
    display: flex;
    gap: 1rem;
    align-items: center;
    padding: 0.95rem 1rem;
    border-radius: 1rem;
    border: 1px solid #e2e8f0;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
}

.schedule-row.today {
    border-color: #0f766e;
    box-shadow: inset 0 0 0 1px rgba(15, 118, 110, 0.06);
}

.date-chip {
    min-width: 92px;
    border-radius: 0.9rem;
    padding: 0.65rem 0.75rem;
    background: #0f172a;
    color: #fff;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    text-align: center;
}

.date-chip .day {
    font-weight: 800;
}

.date-chip .weekday {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.72);
}
</style>
