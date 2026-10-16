<template>
    <div class="video-item border-bottom pb-3 mb-3" :class="{ 'border-success bg-success-soft': completed }">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            
            <div class="d-flex align-items-center gap-3 ms-4 ps-2 border-start border-3 border-primary border-opacity-25 py-1">
                <div class="icon-shape rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                    :class="completed ? 'bg-success text-white' : 'bg-primary-subtle text-primary'"
                    style="width: 35px; height: 35px;">
                    <i class="fs-6 bi bi-chat-left-text-fill"></i>
                </div>
                
                <div>
                    <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.9rem;" :class="{ 'text-muted text-decoration-line-through': completed }">
                        {{ title }}
                    </h6>
                    <p v-if="keterangan" class="mb-0 text-muted mt-1" style="font-size: 0.75rem;">
                        {{ keterangan }}
                    </p>
                </div>
            </div>

            <div class="text-end">
                <button
                    v-if="!isCompleted"
                    class="btn btn-sm btn-light border rounded-pill px-3 text-primary fw-medium transition-all"
                    :disabled="locked"
                    @click="!locked && $emit('complete')"
                >
                    <i class="bi bi-pencil-square me-1"></i> {{ actionLabel }}
                </button>
                <div v-else class="badge bg-success-subtle text-success px-3 py-1 rounded-pill fw-bold" style="font-size: 0.75rem">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    <span v-if="score != null">Score: {{ score }}</span>
                    <span v-else>Passed</span>
                </div>
                <div v-if="statusHint && !isCompleted" class="mt-2 text-muted quiz-status-hint">
                    <i class="bi bi-info-circle me-1"></i>{{ statusHint }}
                </div>
            </div>
            
        </div>
    </div>
</template>

<script>
export default {
    name: "TrainingDetailQuiz",
    props: {
        title: { type: String, default: "Quiz" },
        keterangan: { type: String, default: "" },
        completed: { type: Boolean, default: false },
        status: { type: String, default: "not-started" },
        score: { type: Number, default: null },
        locked: { type: Boolean, default: false },
    },
    emits: ["complete"],
    computed: {
        isCompleted() {
            return this.completed || this.status === "passed" || this.status === "done";
        },
        actionLabel() {
            return this.status === "in-progress" ? "Lanjutkan Quiz" : "Mulai Quiz";
        },
        statusHint() {
            if (this.status === "in-progress") {
                return "Status quiz sedang berlangsung. Gunakan tombol di atas untuk membuka kembali halaman quiz.";
            }

            if (this.isCompleted) {
                return "Status quiz sudah selesai.";
            }

            return null;
        },
    },
};
</script>

<style scoped>
.bi {
    display: inline-table !important;
}

.video-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

.quiz-status-hint {
    font-size: 0.76rem;
    line-height: 1.45;
}

.transition-all {
    transition: all 0.2s ease;
}
.transition-all:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2) !important;
}
</style>
