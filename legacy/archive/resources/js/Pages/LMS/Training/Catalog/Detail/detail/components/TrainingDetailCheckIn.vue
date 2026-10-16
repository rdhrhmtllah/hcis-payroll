<template>
    <div class="mb-4">
        <h5 class="fw-bold text-dark mb-3" style="font-size: 1rem;">Check-In</h5>
        <div class="stitch-card p-3 p-md-4 d-flex align-items-center justify-content-between flex-wrap gap-3" :class="{'border-success bg-success-soft': checkIn.status === 'done'}">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-shape rounded-circle d-flex align-items-center justify-content-center"
                    :class="checkIn.status === 'done' ? 'bg-success text-white' : 'bg-warning-subtle text-warning'">
                    <i class="fs-4" :class="checkIn.status === 'done' ? 'bi bi-check2-all' : 'bi bi-person-bounding-box'"></i>
                </div>
                <div>
                    <h6 class="mb-1 fw-bold text-dark">Kehadiran (Absensi)</h6>
                    <p class="mb-0 text-muted" style="font-size: 0.85rem;">
                        <span v-if="checkIn.status !== 'done'">Face Recognition Verification Required</span>
                        <span v-else class="text-success fw-medium">Verified Checked-In</span>
                    </p>
                </div>
            </div>

            <div v-if="checkIn.status !== 'done'">
                <button
                    class="btn btn-sm btn-primary rounded-pill px-3 fw-medium shadow-sm transition-all d-flex align-items-center gap-2"
                    :disabled="!canOpenAbsensi"
                    @click="handleCheckInClick"
                >
                    <i class="bi bi-camera me-1"></i> Check-In
                </button>
            </div>
            <div v-else class="text-end">
                <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold" style="font-size: 0.85rem;">
                    <i class="bi bi-check-circle-fill me-1"></i> Done
                </span>
            </div>
        </div>

        <div v-if="checkIn.status !== 'done' && !canOpenAbsensi" class="attendance-note mt-2">
            <i class="bi bi-info-circle me-2"></i>
            Absensi belum dapat dibuka karena data penjadwalan training untuk akun ini belum tersedia.
            Pastikan Anda membuka training ini dengan akun peserta yang memang ditugaskan.
        </div>

        <!-- LmsAbsensi Modal Component -->
        <LmsAbsensi
            v-if="activityItem?.id_penjadwalan"
            :show="showAbsensi"
            @update:show="showAbsensi = $event"
            type="In"
            :activityItem="activityItem"
            @absensi-complete="handleAbsensiComplete"
            @status-change="status => $emit('status-change', status)"
        />
    </div>
</template>

<script>
import LmsAbsensi from "../../../../Attendance/LmsAbsensi.vue";

export default {
    name: "TrainingDetailCheckIn",
    components: {
        LmsAbsensi
    },
    props: {
        checkIn: { type: Object, required: true },
        activityItem: { type: Object, required: true }
    },
    data() {
        return {
            showAbsensi: false
        };
    },
    computed: {
        canOpenAbsensi() {
            return Boolean(this.activityItem?.id_penjadwalan);
        },
    },
    methods: {
        handleCheckInClick() {
            if (!this.canOpenAbsensi) {
                this.$toast?.error?.('Data penjadwalan training belum tersedia untuk user ini.');
                return;
            }

            this.showAbsensi = true;
            this.$emit('check-in-initiated');
        },
        handleAbsensiComplete(data) {
            // Emits { type: 'In', timestamp, status: 'success' }
            this.$emit('check-in-complete', data);
        }
    }
};
</script>

<style scoped>
.bi {
    display: inline-table !important;
}

.stitch-card {
    background: var(--surface, #ffffff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 12px;
    box-shadow: 0 2px 4px -1px rgba(0,0,0,0.05);
    transition: all 0.2s;
}

.bg-success-soft {
    background-color: #f0fdf4 !important;
    border-color: #bbf7d0 !important;
}

.icon-shape {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
}

.transition-all {
    transition: all 0.2s ease;
}
.transition-all:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3) !important;
}

.attendance-note {
    display: flex;
    align-items: flex-start;
    padding: 0.75rem 0.9rem;
    border-radius: 10px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    font-size: 0.82rem;
    line-height: 1.45;
}
</style>
