<template>
    <div class="history-timeline-container">
        <el-timeline>
            <el-timeline-item
                v-for="(activity, index) in formattedHistory"
                :key="index"
                :timestamp="activity.formattedDate"
                :hollow="true"
                :class="`timeline-item-${activity.statusColor}`"
                placement="top"
            >
                <HistoryCard
                    :title="activity.title"
                    :icon="activity.icon"
                    :time="activity.formattedTime"
                    :actor="activity.by"
                    :class="`timeline-item-${activity.statusColor}`"
                >
                    <DetailKpiSubmission
                        v-if="activity.kpiData"
                        :kpi-data="activity.kpiData"
                    />

                    <DetailPerformanceUpdate
                        v-if="activity.performanceData"
                        :performance-data="activity.performanceData"
                    />

                    <DetailChanges
                        v-if="activity.changes"
                        :changes="activity.changes"
                    />
                    <DetailRejection
                        v-if="activity.reason"
                        :reason="activity.reason"
                    />
                    <DetailPica
                        v-if="activity.picaData"
                        :pica-data="activity.picaData"
                    />
                </HistoryCard>
            </el-timeline-item>
        </el-timeline>
    </div>
</template>

<script>
import { ElTimeline, ElTimelineItem } from "element-plus";
import HistoryCard from "./historyCard.vue";
import DetailKpiSubmission from "./detailKpiSubmission.vue";
import DetailChanges from "./detailChanges.vue";
import DetailRejection from "./detailRejection.vue";
import DetailPica from "./detailPica.vue";
import DetailPerformanceUpdate from "./detailPerformanceUpdate.vue";
export default {
    name: "HistoryTimeline",
    components: {
        ElTimeline,
        ElTimelineItem,
        HistoryCard,
        DetailKpiSubmission,
        DetailChanges,
        DetailRejection,
        DetailPica,
        DetailPerformanceUpdate,
    },
    props: {
        history: { type: Array, required: true, default: () => [] },
    },
    computed: {
        formattedHistory() {
            return this.history.map((activity) => {
                const date = new Date(activity.date);
                const formattedDate = date.toLocaleDateString("id-ID", {
                    weekday: "long",
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                });
                const formattedTime = date.toLocaleTimeString("id-ID", {
                    hour: "2-digit",
                    minute: "2-digit",
                    hour12: false,
                });

                let icon = "bi bi-info-circle-fill";
                switch (activity.type) {
                    case "TARGET_SUBMIT":
                        icon = "bi bi-bullseye";
                        break;
                    case "PERFORMANCE_SUBMIT":
                        icon = "bi bi-keyboard-fill";
                        break;
                    case "RESUBMIT":
                        icon = "bi bi-arrow-counterclockwise";
                        break;
                    case "APPROVE":
                        icon = "bi bi-check-circle-fill";
                        break;
                    case "REJECT":
                        icon = "bi bi-x-circle-fill";
                        break;
                    case "PICA_UPDATE":
                        icon = "bi bi-file-earmark-medical-fill";
                        break;
                    case "ESCALATE":
                        icon = "bi bi-shield-lock-fill";
                        break;
                }

                return { ...activity, formattedDate, formattedTime, icon };
            });
        },
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    /* Pastikan variabel warna tersedia */
    --color-primary: #409eff;
    --color-success: #67c23a;
    --color-warning: #e6a23c;
    --color-danger: #f56c6c;
    --color-info: #909399;
}
.history-timeline-container {
    max-width: 100%;
}

:deep(.el-timeline-item__node) {
    width: 24px;
    height: 24px;
    left: -7px;
    top: 4px;
    border-width: 4px;
}
:deep(.el-timeline-item__tail) {
    border-left: 3px solid #e4e7ed;
}
.timeline-item-primary :deep(.el-timeline-item__node) {
    background-color: var(--color-primary);
    border-color: #a0cfff;
}
.timeline-item-success :deep(.el-timeline-item__node) {
    background-color: var(--color-success);
    border-color: #b3e19d;
}
.timeline-item-danger :deep(.el-timeline-item__node) {
    background-color: var(--color-danger);
    border-color: #fab6b6;
}
.timeline-item-warning :deep(.el-timeline-item__node) {
    background-color: var(--color-warning);
    border-color: #f8c983;
}
.timeline-item-info :deep(.el-timeline-item__node) {
    background-color: var(--color-info);
    border-color: #c8c9cc;
}
.timeline-item-primary .timeline-card {
    border-left-color: var(--color-primary);
}
.timeline-item-success .timeline-card {
    border-left-color: var(--color-success);
}
.timeline-item-danger .timeline-card {
    border-left-color: var(--color-danger);
}
.timeline-item-warning .timeline-card {
    border-left-color: var(--color-warning);
}
.timeline-item-info .timeline-card {
    border-left-color: var(--color-info);
}
.timeline-item-primary .timeline-icon,
.timeline-item-primary .timeline-title {
    color: var(--color-primary);
}
.timeline-item-success .timeline-icon,
.timeline-item-success .timeline-title {
    color: var(--color-success);
}
.timeline-item-danger .timeline-icon,
.timeline-item-danger .timeline-title {
    color: var(--color-danger);
}
.timeline-item-warning .timeline-icon,
.timeline-item-warning .timeline-title {
    color: var(--color-warning);
}
.timeline-item-info .timeline-icon,
.timeline-item-info .timeline-title {
    color: var(--color-info);
}
:deep(.el-timeline-item__timestamp) {
    font-size: 0.9rem;
    font-weight: 600;
    color: #303133;
}

@media (max-width: 991.98px) {
    .history-timeline-container {
        margin-left: -30px;
    }
}
</style>
