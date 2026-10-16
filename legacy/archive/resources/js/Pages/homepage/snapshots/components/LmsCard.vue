<template>
    <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <div class="icon-box" style="background:#EFF6FF;"><i class="bi bi-mortarboard" style="color:#3B82F6;"></i></div>
                Learning Management
            </h5>
            <span class="badge bg-info-subtle text-info rounded-pill px-3 py-2">{{ data.completed_courses }}/{{ data.enrolled_courses }} Selesai</span>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <h6 class="section-label">Sesi Mendatang</h6>
                <div class="p-3 rounded-4 text-white shadow-lg position-relative overflow-hidden" style="background:linear-gradient(135deg,#3B82F6,#1D4ED8);">
                    <div class="position-relative" style="z-index:1;">
                        <small class="opacity-75 d-block mb-1">Materi Berikutnya</small>
                        <h6 class="fw-bold mb-3">{{ data.next_session }}</h6>
                        <div class="d-flex align-items-center gap-2 small"><i class="bi bi-clock"></i><span>{{ data.next_session_date }}</span></div>
                        <div v-if="daysUntil !== null" class="mt-2">
                            <span class="badge bg-white bg-opacity-25 rounded-pill px-3 py-1 small">
                                {{ daysUntil === 0 ? 'Hari ini!' : daysUntil === 1 ? 'Besok' : daysUntil + ' hari lagi' }}
                            </span>
                        </div>
                    </div>
                    <i class="bi bi-journal-bookmark position-absolute bottom-0 end-0 opacity-25" style="font-size:5rem;margin-bottom:-1rem;margin-right:-1rem;"></i>
                </div>
                <div v-if="data.mandatory_pending > 0" class="mt-3 p-2 rounded-3 d-flex align-items-center gap-2" style="background:#FEF2F2;">
                    <i class="bi bi-exclamation-triangle-fill" style="color:#EF4444;"></i>
                    <small class="fw-bold" style="color:#991B1B;">{{ data.mandatory_pending }} training wajib belum selesai</small>
                </div>
            </div>
            <div class="col-md-6">
                <h6 class="section-label">Progres Kursus</h6>
                <div v-for="(c, i) in data.learning_progress" :key="i" class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="fw-bold text-truncate" style="max-width:160px;">{{ c.title }}</small>
                        <small class="text-muted">{{ c.progress }}%</small>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar" :style="{ width: c.progress+'%', background: c.progress===100?'#10B981':'#3B82F6' }"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-3 rounded-4 bg-light mt-auto">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-white rounded-circle shadow-sm h4 mb-0" style="color:#3B82F6;"><i class="bi bi-hourglass-split"></i></div>
                    <div><h4 class="fw-bold mb-0">{{ data.learning_hours }}</h4><small class="text-muted">Jam Belajar Bulan Ini</small></div>
                </div>
                <button id="cta-lms" class="btn btn-outline-primary rounded-pill px-4 fw-bold btn-sm" @click="$emit('navigate','/training-catalog')">Buka Katalog</button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'LmsCard',
    props: { data: { type: Object, required: true } },
    emits: ['navigate'],
    computed: {
        daysUntil() {
            if (!this.data.next_session_date) return null;
            const diff = Math.ceil((new Date(this.data.next_session_date) - new Date()) / 86400000);
            return diff >= 0 ? diff : null;
        },
    },
};
</script>

<style scoped>
.icon-box { width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:10px;font-size:1.15rem;flex-shrink:0; }
.section-label { font-weight:700;margin-bottom:0.75rem;font-size:0.75rem;text-transform:uppercase;color:#94A3B8;letter-spacing:0.05em; }
</style>
