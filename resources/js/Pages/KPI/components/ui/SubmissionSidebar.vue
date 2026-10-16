<template>
    <div class="submission-sidebar">
        <!-- Progress Approval Section -->
        <div v-if="activeApproval" class="sidebar-section">
            <div class="section-header" @click="toggleSection('approval')">
                <div class="section-title-wrapper">
                    <i
                        class="bi d-flex justify-content-center align-items-center bi-person-check"
                    ></i>
                    <h4 class="section-title">Progress Approval</h4>
                </div>
                <i
                    class="bi"
                    :class="
                        sections.approval ? 'bi-chevron-up' : 'bi-chevron-down'
                    "
                ></i>
            </div>
            <div v-if="sections.approval" class="section-content">
                <div class="approval-progress">
                    <div
                        v-for="(approver, index) in approvalData"
                        :key="index"
                        class="approver-item"
                        :class="getApproverStatusClass(approver.status)"
                    >
                        <div class="approver-info">
                            <div
                                class="approver-avatar"
                                :class="getApproverStatusClass(approver.status)"
                            >
                                <i
                                    class="bi d-flex justify-content-center align-items-center"
                                    :class="getApproverIcon(approver.status)"
                                ></i>
                            </div>
                            <div class="approver-details">
                                <span class="approver-name">{{
                                    approver.name
                                }}</span>
                                <span class="approver-role">{{
                                    approver.role
                                }}</span>
                            </div>
                        </div>
                        <div class="approval-status">
                            <span
                                class="status-badge"
                                :class="getStatusBadgeClass(approver.status)"
                            >
                                {{ getStatusText(approver.status) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity History Section -->
        <div v-if="activeHistory" class="sidebar-section">
            <div class="section-header" @click="toggleSection('activity')">
                <div class="section-title-wrapper">
                    <i
                        class="bi d-flex justify-content-center align-items-center bi-clock-history"
                    ></i>
                    <h4 class="section-title">Activity History</h4>
                </div>
                <i
                    class="bi"
                    :class="
                        sections.activity ? 'bi-chevron-up' : 'bi-chevron-down'
                    "
                ></i>
            </div>
            <div v-if="sections.activity" class="section-content">
                <div class="activity-timeline">
                    <div
                        v-for="(activity, index) in activities"
                        :key="index"
                        class="activity-item"
                    >
                        <div
                            class="activity-timeline-marker"
                            :class="getActivityMarkerClass(index)"
                        ></div>
                        <div class="activity-content">
                            <div class="activity-title">
                                {{ activity.title }}
                            </div>
                            <div
                                class="activity-description"
                                v-if="activity.description"
                            >
                                {{ activity.description }}
                            </div>
                            <div class="activity-meta">
                                <span class="activity-date">{{
                                    activity.date
                                }}</span>
                                <span class="activity-time">{{
                                    activity.time
                                }}</span>
                            </div>
                            <div v-if="activity.source" class="activity-source">
                                <a
                                    :href="activity.source"
                                    target="_blank"
                                    class="source-link"
                                >
                                    <i
                                        class="bi d-flex justify-content-center align-items-center bi-link-45deg"
                                    ></i>
                                    Lihat sumber
                                </a>
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
    name: "SubmissionSidebar",
    props: {
        approvalData: {
            type: Array,
            default: () => [],
        },
        activities: {
            type: Array,
            default: () => [],
        },
        activeApproval: { type: Boolean, default: false },
        activeHistory: { type: Boolean, default: false },
    },
    data() {
        return {
            sections: {
                approval: true,
                activity: true,
            },
        };
    },
    methods: {
        toggleSection(section) {
            this.sections[section] = !this.sections[section];
        },
        getApproverStatusClass(status) {
            const statusMap = {
                approved: "completed",
                rejected: "rejected",
                inprogress: "current",
                pending: "pending",
                // Pengajuan lama yang jejak per-approver-nya memang tidak pernah
                // dicatat. Menggambarnya sebagai "menunggu" akan menyatakan
                // sesuatu yang tidak diketahui sebagai fakta.
                unknown: "pending",
            };
            return statusMap[status] || "pending";
        },
        getApproverIcon(status) {
            const iconMap = {
                approved: "bi-check-circle-fill",
                rejected: "bi-x-circle-fill",
                inprogress: "bi-arrow-repeat",
                pending: "bi-clock",
                unknown: "bi-dash-circle",
            };
            return iconMap[status] || "bi-clock";
        },
        getStatusBadgeClass(status) {
            const badgeMap = {
                approved: "badge-success",
                rejected: "badge-danger",
                inprogress: "badge-warning",
                pending: "badge-secondary",
                unknown: "badge-secondary",
            };
            return badgeMap[status] || "badge-secondary";
        },
        getStatusText(status) {
            const textMap = {
                approved: "Disetujui",
                rejected: "Ditolak",
                inprogress: "Diproses",
                pending: "Menunggu",
                unknown: "Alur Approval",
            };
            return textMap[status] || "Menunggu";
        },
        getActivityMarkerClass(index) {
            return index === this.activities.length - 1 ? "last" : "";
        },
    },
};
</script>

<style scoped>
.submission-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.sidebar-section {
    background: var(--surface);
    border-radius: 12px;
    padding: 0;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    overflow: hidden;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    cursor: pointer;
    transition: background-color 0.3s ease;
    border-bottom: 1px solid transparent;
}

.section-header:hover {
    background: var(--surface-soft);
}

.section-header:has(+ .section-content) {
    border-bottom-color: var(--border);
}

.section-title-wrapper {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-header i:not(.section-title-wrapper i) {
    color: var(--text-muted);
    transition: transform 0.3s ease;
}

.section-header i.bi-chevron-up {
    transform: rotate(0deg);
}

.section-header i.bi-chevron-down {
    transform: rotate(180deg);
}

.section-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.section-title-wrapper i {
    color: var(--primary);
    font-size: 1.1rem;
}

.section-content {
    padding: 1.25rem;
}

/* Approval Progress Styles */
.approval-progress {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.approver-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem;
    border-radius: 8px;
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    background: var(--surface-soft);
}

.approver-item.current {
    border-color: var(--warning);
    background: linear-gradient(135deg, var(--surface-soft) 0%, #fffbf0 100%);
    box-shadow: 0 0 0 1px var(--warning);
    animation: pulse-current 2s ease-in-out infinite;
}

.approver-item.completed {
    border-color: var(--success);
    background: linear-gradient(135deg, var(--surface-soft) 0%, #f0fff4 100%);
}

.approver-item.rejected {
    border-color: var(--danger);
    background: linear-gradient(135deg, var(--surface-soft) 0%, #fff5f5 100%);
}

.approver-item.pending {
    border-color: var(--border);
    background: var(--surface-soft);
    opacity: 0.7;
}

@keyframes pulse-current {
    0%,
    100% {
        box-shadow: 0 0 0 1px var(--warning);
    }
    50% {
        box-shadow: 0 0 0 3px var(--warning);
    }
}

.approver-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
}

.approver-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.approver-avatar.current {
    background: var(--warning);
    color: white;
}

.approver-avatar.completed {
    background: var(--success);
    color: white;
}

.approver-avatar.rejected {
    background: var(--danger);
    color: white;
}

.approver-avatar.pending {
    background: var(--secondary);
    color: white;
}

.approver-details {
    display: flex;
    flex-direction: column;
}

.approver-name {
    font-weight: 600;
    color: var(--dark);
    font-size: 0.85rem;
}

.approver-role {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.approval-status {
    flex-shrink: 0;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    white-space: nowrap;
}

.badge-success {
    background: var(--success);
    color: white;
}

.badge-warning {
    background: var(--warning);
    color: white;
}

.badge-danger {
    background: var(--danger);
    color: white;
}

.badge-secondary {
    background: var(--secondary);
    color: white;
}

/* Activity Timeline Styles */
.activity-timeline {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    position: relative;
}

.activity-timeline::before {
    content: "";
    position: absolute;
    top: 0;
    bottom: 0;
    left: 16px;
    width: 2px;
    background: var(--border);
    z-index: 1;
}

.activity-item {
    display: flex;
    gap: 1rem;
    position: relative;
    z-index: 2;
}

.activity-timeline-marker {
    width: 12px;
    height: 12px;
    background: var(--primary);
    border-radius: 50%;
    border: 2px solid var(--surface);
    box-shadow: 0 0 0 2px var(--primary);
    flex-shrink: 0;
    margin-top: 0.25rem;
    position: relative;
    z-index: 2;
}

.activity-timeline-marker.last {
    background: var(--success);
    box-shadow: 0 0 0 2px var(--success);
}

.activity-content {
    flex: 1;
    padding-bottom: 0.5rem;
}

.activity-title {
    font-weight: 600;
    color: var(--dark);
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.activity-description {
    color: var(--text-muted);
    font-size: 0.8rem;
    line-height: 1.4;
    margin-bottom: 0.5rem;
}

.activity-meta {
    display: flex;
    gap: 0.75rem;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.activity-source {
    margin-top: 0.5rem;
}

.source-link {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: var(--primary);
    text-decoration: none;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    background: var(--surface-soft);
    border-radius: 6px;
    transition: all 0.3s ease;
}

.source-link:hover {
    background: var(--primary-light);
    color: var(--primary-dark);
    text-decoration: none;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .submission-sidebar {
        gap: 0.75rem;
    }

    .section-header {
        padding: 0.875rem 1rem;
    }

    .section-content {
        padding: 1rem;
    }

    .approver-item {
        padding: 0.6rem;
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }

    .approver-info {
        width: 100%;
    }

    .approval-status {
        align-self: flex-end;
    }

    .activity-item {
        gap: 0.75rem;
    }

    .activity-timeline-marker {
        width: 10px;
        height: 10px;
        margin-top: 0.2rem;
    }

    .activity-timeline::before {
        left: 15px;
    }
}

@media (max-width: 576px) {
    .sidebar-section {
        border-radius: 8px;
    }

    .section-header {
        padding: 0.75rem;
    }

    .section-content {
        padding: 0.75rem;
    }

    .activity-meta {
        flex-direction: column;
        gap: 0.25rem;
    }

    .approver-avatar {
        width: 32px;
        height: 32px;
        font-size: 0.9rem;
    }
}
</style>
