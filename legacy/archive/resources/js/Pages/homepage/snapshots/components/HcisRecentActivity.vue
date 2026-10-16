<template>
    <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <p class="section-kicker mb-1">Riwayat Singkat</p>
                <h5 class="fw-bold mb-0">Aktivitas Absensi Terbaru</h5>
            </div>
            <button class="btn btn-outline-dark btn-sm rounded-pill px-3" @click="$emit('navigate', '/absensiSales')">
                Riwayat Lengkap
            </button>
        </div>

        <div v-if="items.length" class="activity-list">
            <div v-for="item in items" :key="`${item.date}-${item.title}`" class="activity-row">
                <div class="activity-date">
                    <span>{{ item.date_label }}</span>
                </div>
                <div class="activity-content">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                        <strong>{{ item.title }}</strong>
                        <span class="badge rounded-pill" :class="toneClass(item.tone)">{{ item.tone }}</span>
                    </div>
                    <div class="text-muted small">{{ item.subtitle }}</div>
                </div>
            </div>
        </div>

        <div v-else class="empty-soft">
            <i class="bi bi-inboxes"></i>
            <span>Belum ada aktivitas penting yang perlu ditampilkan.</span>
        </div>
    </div>
</template>

<script>
export default {
    name: 'HcisRecentActivity',
    props: {
        items: {
            type: Array,
            default: () => [],
        },
    },
    emits: ['navigate'],
    methods: {
        toneClass(tone) {
            const map = {
                danger: 'bg-danger-subtle text-danger',
                warning: 'bg-warning-subtle text-warning',
                info: 'bg-info-subtle text-info',
                primary: 'bg-primary-subtle text-primary',
                success: 'bg-success-subtle text-success',
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

.activity-list {
    display: grid;
    gap: 0.85rem;
}

.activity-row {
    display: flex;
    gap: 1rem;
    padding: 0.9rem 0;
    border-bottom: 1px dashed #dbe4ee;
}

.activity-row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}

.activity-date {
    min-width: 82px;
    font-weight: 800;
    color: #0f172a;
}

.empty-soft {
    min-height: 124px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    color: #64748b;
    font-weight: 600;
    background: linear-gradient(135deg, #fff, #f8fafc);
    border: 1px dashed #dbe4ee;
    border-radius: 1rem;
}
</style>
