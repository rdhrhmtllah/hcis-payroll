<template>
    <div class="performance-update-details">
        <div
            v-for="(perf, index) in performanceData"
            :key="index"
            class="perf-item mb-1"
        >
            <div class="perf-info">
                <span class="perf-parameter">{{ perf.parameter }}</span>
                <div class="perf-values">
                    <span>Target: <strong>{{ perf.target }}</strong></span>
                    <span>Aktual: <strong class="actual-value">{{ perf.actual }}</strong></span>
                    <span v-if="perf.achievement !== undefined && perf.achievement !== null">
                        Capaian: <strong>{{ perf.achievement }}%</strong>
                    </span>
                </div>
            </div>
            <div
                class="perf-status"
                :class="getAchievementStatus(perf).className"
            >
                <i :class="getAchievementStatus(perf).icon"></i>
                <span>{{ getAchievementStatus(perf).text }}</span>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "DetailPerformanceUpdate",
    props: {
        performanceData: { type: Array, required: true },
    },
    methods: {
        getAchievementStatus(perf) {
            // Gunakan achievement dari API jika tersedia
            if (perf.achievement !== undefined && perf.achievement !== null) {
                const pct = parseFloat(perf.achievement);
                if (isNaN(pct)) {
                    return { text: "N/A", className: "status-info", icon: "bi bi-question-circle" };
                }
                if (pct >= 100) {
                    return { text: "Tercapai", className: "status-success", icon: "bi bi-check-circle-fill" };
                }
                if (pct >= 80) {
                    return { text: `Hampir (${pct}%)`, className: "status-warning", icon: "bi bi-exclamation-circle-fill" };
                }
                return { text: `Tidak Tercapai (${pct}%)`, className: "status-danger", icon: "bi bi-x-circle-fill" };
            }

            // Fallback: tidak ada achievement, hanya tampilkan N/A
            return { text: "N/A", className: "status-info", icon: "bi bi-question-circle" };
        },
    },
};
</script>

<style scoped>
.performance-update-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.perf-item {
    display: flex;
    align-items: stretch;
    background-color: #f5f7fa;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    overflow: hidden;
}
.perf-info {
    padding: 0.75rem 1rem;
    flex-grow: 1;
}
.perf-parameter {
    font-weight: 600;
    display: block;
    margin-bottom: 0.5rem;
    color: #303133;
}
.perf-values {
    display: flex;
    gap: 1.5rem;
    font-size: 0.85rem;
    flex-wrap: wrap;
}
.perf-values span {
    color: #606266;
}
.perf-values .actual-value {
    color: var(--color-primary);
}
.perf-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0 1rem;
    font-size: 0.8rem;
    font-weight: bold;
    color: white;
    min-width: 140px;
    text-align: center;
}

.status-success {
    background-color: #67c23a;
}
.status-warning {
    background-color: #e6a23c;
}
.status-danger {
    background-color: #f56c6c;
}
.status-info {
    background-color: #909399;
}

@media (max-width: 576px) {
    .perf-item {
        flex-direction: column;
    }
    .perf-status {
        justify-content: flex-start;
        padding: 0.5rem 1rem;
        border-top: 1px solid #e4e7ed;
    }
    .perf-info {
        padding: 0.75rem;
    }
}
</style>
