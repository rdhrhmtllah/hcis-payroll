<template>
    <div class="mb-4">
        
        <div v-if="locked" class="alert alert-warning py-3 mb-3 bg-warning-subtle text-warning border-0 d-flex align-items-center rounded-3 shadow-sm" style="font-size: 0.85rem;">
            <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
            Must pass post test to be able to take the Evaluation Reaction
        </div>

        <div class="stitch-card p-3 p-md-4" :class="{ 'border-success bg-success-soft': evaluation.status === 'passed' }">
            <div class="row align-items-center g-3 rounded-4 p-1">
                <div class="col-auto">
                    <div class="icon-shape rounded-4 d-flex align-items-center justify-content-center bg-light border"
                        :class="evaluation.status === 'passed' ? 'border-success text-success' : 'border-warning text-warning'" style="width: 50px; height: 50px;">
                        <i class="fs-4 bi bi-clipboard-check"></i>
                    </div>
                </div>
                
                <div class="col-md-4 col-sm-12">
                    <h6 class="mb-1 fw-bold text-dark">{{ evaluation.name }}</h6>
                    <div class="d-flex align-items-center gap-3 text-muted" style="font-size: 0.8rem;">
                        <span><i class="bi bi-clock me-1"></i> {{ evaluation.duration }}</span>
                        <span><i class="bi bi-list-ul me-1"></i> {{ evaluation.questions }} Questions</span>
                    </div>
                </div>

                <div class="col d-flex justify-content-end align-items-center">
                    <button
                        v-if="evaluation.status !== 'passed'"
                        class="btn btn-sm btn-warning text-white rounded-pill px-3 fw-medium shadow-sm transition-all"
                        :disabled="locked"
                        @click="$emit('start-eval')"
                    >
                        Start Evaluation <i class="bi bi-arrow-right-short ms-1"></i>
                    </button>
                    <span v-else class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold" style="font-size: 0.85rem;">
                        <i class="bi bi-check-circle-fill me-1"></i> Completed
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "TrainingDetailEvaluation",
    props: {
        evaluation: { type: Object, required: true },
        locked: { type: Boolean, default: false },
    },
};
</script>

<style scoped>
.bi {
    display: inline-table !important;
}

.stitch-card {
    background: var(--surface, var(--hcis-white));
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 12px;
    box-shadow: 0 2px 4px -1px rgba(0,0,0,0.05);
    transition: all 0.2s;
}

.bg-success-soft {
    background-color: var(--hcis-green-50) !important;
    border-color: var(--hcis-green-200) !important;
}

.icon-shape {
    width: 52px;
    height: 52px;
    flex-shrink: 0;
}

.transition-all {
    transition: all 0.2s ease;
}
.transition-all:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3) !important;
}
</style>
