<template>
    <div v-if="filteredActions.length" class="quick-actions mb-4">
        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-lightning-charge-fill text-warning"></i>
            Aksi Cepat
        </h6>
        <div class="row g-2">
            <div v-for="action in filteredActions" :key="action.id" :class="colClass">
                <button
                    :id="`qa-${action.id}`"
                    class="quick-action-btn w-100 p-3 border-0 rounded-4 bg-white shadow-sm d-flex align-items-center gap-3 position-relative"
                    @click="handleClick(action)"
                >
                    <div
                        class="icon-circle d-flex align-items-center justify-content-center rounded-3 flex-shrink-0"
                        :style="{ background: action.bgColor || hexToRgba(action.color, 0.12) }"
                    >
                        <i :class="action.icon" :style="{ color: action.color, fontSize: '1.1rem' }"></i>
                    </div>
                    <span class="action-label fw-semibold small text-start">{{ action.label }}</span>
                    <span
                        v-if="action.badge_count && action.badge_count > 0"
                        class="badge-dot position-absolute"
                    >
                        {{ action.badge_count > 99 ? '99+' : action.badge_count }}
                    </span>
                    <i class="bi bi-chevron-right ms-auto text-muted small d-none d-md-inline"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { router } from '@inertiajs/vue3';

export default {
    name: 'QuickActions',
    props: {
        isLeader: {
            type: Boolean,
            default: false,
        },
        pendingApprovals: {
            type: Number,
            default: 0,
        },
        customActions: {
            type: Array,
            default: null,
        },
    },
    computed: {
        defaultActions() {
            return [
                { id: 'izin', label: 'Ajukan Izin / Cuti', icon: 'bi bi-envelope-paper-fill', route: '/uDash', color: '#6366F1' },
                { id: 'lembur', label: 'Ajukan Lembur', icon: 'bi bi-clock-history', route: '/overtime', color: '#F59E0B' },
                { id: 'shift', label: 'Ubah Shift', icon: 'bi bi-arrow-left-right', route: '/self-shift', color: '#10B981' },
                { id: 'training', label: 'Katalog Training', icon: 'bi bi-mortarboard-fill', route: '/training-catalog', color: '#3B82F6' },
                { id: 'tamu', label: 'Registrasi Tamu', icon: 'bi bi-person-badge-fill', route: '/visitor/tamu', color: '#EC4899' },
                { id: 'kpi', label: 'Input KPI', icon: 'bi bi-graph-up-arrow', route: '/kpi', color: '#8B5CF6' },
            ];
        },
        leaderActions() {
            return [
                { id: 'approve', label: 'Approval Center', icon: 'bi bi-check-circle-fill', route: '/leader-shift', color: '#EF4444', badge_count: this.pendingApprovals, leaderOnly: true },
                { id: 'team', label: 'Ringkasan Tim', icon: 'bi bi-people-fill', route: '/uDash', color: '#14B8A6', leaderOnly: true },
            ];
        },
        filteredActions() {
            const actions = this.customActions || this.defaultActions;
            if (this.isLeader) {
                return [...actions, ...this.leaderActions];
            }
            return actions.filter(a => !a.leaderOnly);
        },
        colClass() {
            const count = this.filteredActions.length;
            if (count <= 4) return 'col-6 col-md-3';
            if (count <= 6) return 'col-6 col-md-4 col-lg-2';
            return 'col-6 col-md-4 col-xl-3';
        },
    },
    methods: {
        handleClick(action) {
            this.$emit('action-click', action);
            if (action.route) {
                router.visit(action.route);
            }
        },
        hexToRgba(hex, alpha) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        },
    },
};
</script>

<style scoped>
.quick-action-btn {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    outline: none;
    min-height: 88px;
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08) !important;
}

.quick-action-btn:active {
    transform: translateY(0);
}

.icon-circle {
    width: 40px;
    height: 40px;
    min-width: 40px;
}

.action-label {
    color: #334155;
    line-height: 1.3;
    font-size: 0.98rem;
}

.badge-dot {
    top: 6px;
    right: 6px;
    background: #EF4444;
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 999px;
    line-height: 1.2;
    min-width: 18px;
    text-align: center;
}

@media (max-width: 576px) {
    .icon-circle {
        width: 36px;
        height: 36px;
        min-width: 36px;
    }

    .action-label {
        font-size: 0.75rem !important;
    }
}
</style>
