<template>
    <el-card class="timeline-card" shadow="hover">
        <div class="timeline-card-header">
            <div class="timeline-title-container">
                <div class="timeline-icon"><i :class="icon"></i></div>
                <h4 class="timeline-title">{{ title }}</h4>
            </div>
            <span class="timeline-time">{{ time }}</span>
        </div>
        <div class="timeline-card-body">
            <p class="timeline-actor">
                <i class="bi bi-person-fill mt-custom"></i>
                <span
                    >Oleh: <strong>{{ actor }}</strong></span
                >
            </p>
            <slot></slot>
        </div>
    </el-card>
</template>

<script>
import { ElCard } from "element-plus";
export default {
    name: "HistoryCard",
    components: { ElCard },
    props: ["title", "icon", "time", "actor"],
};
</script>

<style scoped>
.mt-custom {
    margin-top: -10px;
}
.timeline-card {
    border-left-width: 5px;
    border-radius: 8px;
    transition: all 0.3s ease;
}
.timeline-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px 0 rgba(0, 0, 0, 0.08);
}
:deep(.el-card__body) {
    padding: 0;
}
.timeline-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    border-bottom: 1px solid #e4e7ed;
    background-color: #f5f7fa;
    flex-wrap: wrap;
    gap: 10px;
}
.timeline-title-container {
    display: flex;
    align-items: center;
    gap: 12px;
}
.timeline-icon {
    font-size: 1.25rem;
}
.timeline-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}
.timeline-time {
    font-size: 0.85rem;
    color: #606266;
}
.timeline-card-body {
    padding: 20px;
}
.timeline-actor {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 15px 0;
    color: #606266;
}
.timeline-actor strong {
    color: #303133;
}

@media (max-width: 991.98px) {
}
</style>
