<template>
    <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <p class="section-kicker mb-1">Insight Personal</p>
                <h5 class="fw-bold mb-0">Actionable & Analytical Insights</h5>
            </div>
            <span class="badge text-bg-dark rounded-pill">
                {{ (insights?.actionable?.length || 0) + (insights?.analytical?.length || 0) }} insight
            </span>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-6">
                <div class="insight-block tone-actionable">
                    <div class="block-head">
                        <div class="block-icon">
                            <i class="bi bi-lightning-charge-fill"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Actionable</h6>
                            <small>Butuh tindakan atau perhatian langsung</small>
                        </div>
                    </div>
                    <div class="insight-list">
                        <div v-for="item in insights.actionable" :key="item.title" class="insight-item">
                            <div class="d-flex justify-content-between gap-3 align-items-start">
                                <div>
                                    <div class="fw-bold">{{ item.title }}</div>
                                    <div class="text-muted small mt-1">{{ item.message }}</div>
                                </div>
                                <span class="confidence-pill" :class="confidenceClass(item.confidence)">{{ item.confidence }}</span>
                            </div>
                            <button v-if="item.route" class="btn btn-sm btn-outline-dark rounded-pill mt-3 px-3" @click="$emit('navigate', item.route)">
                                Buka Modul
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="insight-block tone-analytical">
                    <div class="block-head">
                        <div class="block-icon">
                            <i class="bi bi-bar-chart-line-fill"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Analytical</h6>
                            <small>Pola, tren, dan pembacaan kebiasaan kerja</small>
                        </div>
                    </div>
                    <div class="insight-list">
                        <div v-for="item in insights.analytical" :key="item.title" class="insight-item">
                            <div class="d-flex justify-content-between gap-3 align-items-start">
                                <div>
                                    <div class="fw-bold">{{ item.title }}</div>
                                    <div class="text-muted small mt-1">{{ item.message }}</div>
                                </div>
                                <span class="confidence-pill" :class="confidenceClass(item.confidence)">{{ item.confidence }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'HcisInsightsPanel',
    props: {
        insights: {
            type: Object,
            default: () => ({
                actionable: [],
                analytical: [],
            }),
        },
    },
    emits: ['navigate'],
    methods: {
        confidenceClass(level) {
            const map = {
                high: 'high',
                medium: 'medium',
                low: 'low',
            };

            return map[level] || 'low';
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

.insight-block {
    border-radius: 1.2rem;
    padding: 1rem;
    height: 100%;
}

.tone-actionable {
    background: linear-gradient(135deg, #fff7ed, #ffffff);
    border: 1px solid #fed7aa;
}

.tone-analytical {
    background: linear-gradient(135deg, #f8fafc, #eef2ff);
    border: 1px solid #c7d2fe;
}

.block-head {
    display: flex;
    gap: 0.9rem;
    align-items: center;
    margin-bottom: 1rem;
}

.block-head small {
    color: #64748b;
}

.block-icon {
    width: 46px;
    height: 46px;
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    background: #0f172a;
    color: #fff;
}

.insight-list {
    display: grid;
    gap: 0.85rem;
}

.insight-item {
    padding: 1rem;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.78);
    border: 1px solid rgba(148, 163, 184, 0.16);
}

.confidence-pill {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 800;
    border-radius: 999px;
    padding: 0.38rem 0.65rem;
    white-space: nowrap;
}

.confidence-pill.high {
    background: #dcfce7;
    color: #166534;
}

.confidence-pill.medium {
    background: #fef3c7;
    color: #92400e;
}

.confidence-pill.low {
    background: #e2e8f0;
    color: #334155;
}
</style>
