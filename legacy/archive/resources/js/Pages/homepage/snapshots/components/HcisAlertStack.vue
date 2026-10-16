<template>
    <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <p class="section-kicker mb-1">Perlu Ditindak</p>
                <h5 class="fw-bold mb-0">Pengingat Penting</h5>
            </div>
            <span class="badge text-bg-dark rounded-pill">{{ alerts.length }}</span>
        </div>

        <div v-if="alerts.length" class="d-flex flex-column gap-3">
            <div v-for="alert in alerts" :key="alert.id" class="alert-card" :class="`tone-${alert.severity}`">
                <div class="d-flex gap-3">
                    <div class="alert-icon">
                        <i :class="iconFor(alert.severity)"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold mb-1">{{ alert.title }}</div>
                        <div class="text-muted small">{{ alert.message }}</div>
                    </div>
                </div>
                <div v-if="alert.route" class="mt-3">
                    <button class="btn btn-sm rounded-pill px-3 fw-semibold" :class="buttonClass(alert.severity)" @click="$emit('navigate', alert.route)">
                        {{ alert.action_label || 'Lihat Detail' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="empty-soft">
            <i class="bi bi-check2-circle"></i>
            <span>Tidak ada alert penting saat ini.</span>
        </div>
    </div>
</template>

<script>
export default {
    name: 'HcisAlertStack',
    props: {
        alerts: {
            type: Array,
            default: () => [],
        },
    },
    emits: ['navigate'],
    methods: {
        iconFor(severity) {
            const icons = {
                danger: 'bi bi-exclamation-octagon-fill',
                warning: 'bi bi-exclamation-triangle-fill',
                info: 'bi bi-info-circle-fill',
                primary: 'bi bi-bell-fill',
                success: 'bi bi-check-circle-fill',
            };

            return icons[severity] || 'bi bi-info-circle-fill';
        },
        buttonClass(severity) {
            const classes = {
                danger: 'btn-danger',
                warning: 'btn-warning',
                info: 'btn-info text-white',
                primary: 'btn-primary',
                success: 'btn-success',
            };

            return classes[severity] || 'btn-outline-dark';
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

.alert-card {
    border-radius: 1rem;
    padding: 1rem;
    border: 1px solid transparent;
}

.alert-icon {
    width: 44px;
    height: 44px;
    border-radius: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.tone-danger {
    background: #fff1f2;
    border-color: #fecdd3;
}

.tone-danger .alert-icon {
    background: #ffe4e6;
    color: #be123c;
}

.tone-warning {
    background: #fffbeb;
    border-color: #fde68a;
}

.tone-warning .alert-icon {
    background: #fef3c7;
    color: #b45309;
}

.tone-info,
.tone-primary {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.tone-info .alert-icon,
.tone-primary .alert-icon {
    background: #dbeafe;
    color: #1d4ed8;
}

.tone-success {
    background: #ecfdf5;
    border-color: #a7f3d0;
}

.tone-success .alert-icon {
    background: #d1fae5;
    color: #047857;
}

.empty-soft {
    min-height: 124px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    color: #64748b;
    font-weight: 600;
    background: linear-gradient(135deg, #f8fafc, #eef2ff);
    border-radius: 1rem;
    border: 1px dashed #cbd5e1;
}
</style>
