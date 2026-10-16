<template>
    <div class="container-fluid px-0">
        <!-- Header -->
        <div
            class="row d-flex align-items-center justify-content-center mb-3 fade-in"
        >
            <div class="mb-1 w-100">
                <div class="modern-header">
                    <div class="header-background"></div>
                    <div class="header-content">
                        <div class="row align-items-center">
                            <div class="col-sm-8">
                                <div class="header-info">
                                    <div
                                        class="icon-container d-flex align-items-center justify-content-center"
                                    >
                                        <i
                                            class="bi bi-people-fill d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2
                                            class="title fw-bold text-white text-start"
                                        >
                                            Team KPI Management
                                        </h2>
                                        <p class="subtitle">
                                            Kelola KPI untuk seluruh anggota tim
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div
                                    class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                                >
                                    <div
                                        class="date-range px-2 px-md-3 py-1 gap-2 gap-md-3"
                                    >
                                        <div class="date-item">
                                            <i
                                                v-if="dayNight == 'Siang'"
                                                class="bi bi-cloud-sun-fill me-2 fs-4 fade-in"
                                            ></i>
                                            <i
                                                v-else
                                                class="bi bi-cloud-moon-fill me-2 fs-4 fade-in"
                                            ></i>
                                            <span class="fs-md-6 text-header">{{
                                                formatDateString(currentDate)
                                            }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="team-stats-overview">
                    <div class="stat-card">
                        <div class="stat-icon team">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ teamMembers.length }}</h3>
                            <p>Total Anggota Tim</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon pending">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ pendingSubmissions }}</h3>
                            <p>Menunggu Pengisian</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon completed">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ completedSubmissions }}</h3>
                            <p>Sudah Disubmit</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon approved">
                            <i class="bi bi-star-fill"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ approvedKPIs }}</h3>
                            <p>KPI Disetujui</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row mb-4">
            <div class="col-12 col-lg-8">
                <div class="recent-activity-section">
                    <div class="section-card">
                        <div class="section-header">
                            <h4>
                                <i class="bi bi-activity me-2"></i>Aktivitas
                                Terkini
                            </h4>
                            <button
                                class="btn-view-all"
                                @click="$router.push('/kpi-team/management')"
                            >
                                Lihat Semua
                            </button>
                        </div>
                        <div class="activity-list">
                            <div
                                v-for="activity in recentActivities"
                                :key="activity.id"
                                class="activity-item"
                            >
                                <div class="activity-avatar">
                                    {{ getInitials(activity.memberName) }}
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">
                                        <strong>{{
                                            activity.memberName
                                        }}</strong>
                                        {{ activity.action }}
                                    </p>
                                    <small class="activity-time">{{
                                        formatRelativeTime(activity.timestamp)
                                    }}</small>
                                </div>
                                <div
                                    class="activity-status"
                                    :class="`status-${activity.status}`"
                                >
                                    {{ getStatusText(activity.status) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="progress-overview-section">
                    <div class="section-card">
                        <div class="section-header">
                            <h4>
                                <i class="bi bi-graph-up me-2"></i>Progress Tim
                            </h4>
                        </div>
                        <div class="progress-overview">
                            <div class="overall-progress">
                                <div class="progress-info">
                                    <span class="progress-label"
                                        >Progress Keseluruhan</span
                                    >
                                    <span class="progress-value"
                                        >{{ overallProgress }}%</span
                                    >
                                </div>
                                <div class="progress-bar">
                                    <div
                                        class="progress-fill"
                                        :style="{
                                            width: overallProgress + '%',
                                        }"
                                        :class="
                                            getProgressBarClass(overallProgress)
                                        "
                                    ></div>
                                </div>
                            </div>

                            <div class="progress-breakdown">
                                <div class="breakdown-item">
                                    <span class="breakdown-label"
                                        >Belum Mulai</span
                                    >
                                    <span class="breakdown-value">{{
                                        progressBreakdown.notStarted
                                    }}</span>
                                </div>
                                <div class="breakdown-item">
                                    <span class="breakdown-label"
                                        >Dalam Proses</span
                                    >
                                    <span class="breakdown-value">{{
                                        progressBreakdown.inProgress
                                    }}</span>
                                </div>
                                <div class="breakdown-item">
                                    <span class="breakdown-label">Selesai</span>
                                    <span class="breakdown-value">{{
                                        progressBreakdown.completed
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="quick-actions-section">
                    <div class="section-card">
                        <div class="section-header">
                            <h4>
                                <i class="bi bi-lightning me-2"></i>Quick
                                Actions
                            </h4>
                        </div>
                        <div class="action-grid">
                            <div
                                class="action-card"
                                @click="$router.push('/kpi-team/management')"
                            >
                                <div class="action-icon">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <div class="action-content">
                                    <h5>Kelola Tim</h5>
                                    <p>Kelola anggota tim dan assign KPI</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </div>

                            <div
                                class="action-card"
                                @click="$router.push('/kpi-team/multi-form')"
                            >
                                <div class="action-icon">
                                    <i class="bi bi-pencil-square"></i>
                                </div>
                                <div class="action-content">
                                    <h5>Input KPI Massal</h5>
                                    <p>Input KPI untuk multiple anggota</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </div>

                            <div class="action-card" @click="openBulkTemplate">
                                <div class="action-icon">
                                    <i class="bi bi-send-check"></i>
                                </div>
                                <div class="action-content">
                                    <h5>Assign Template</h5>
                                    <p>Gunakan template KPI standar</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </div>

                            <div
                                class="action-card"
                                @click="$router.push('/kpi-team/approval')"
                            >
                                <div class="action-icon">
                                    <i class="bi bi-clipboard-check"></i>
                                </div>
                                <div class="action-content">
                                    <h5>Monitoring Approval</h5>
                                    <p>Pantau status persetujuan KPI</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Deadlines -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="deadlines-section">
                    <div class="section-card">
                        <div class="section-header">
                            <h4>
                                <i class="bi bi-calendar-check me-2"></i
                                >Deadline Mendatang
                            </h4>
                        </div>
                        <div class="deadlines-list">
                            <div
                                v-for="deadline in upcomingDeadlines"
                                :key="deadline.id"
                                class="deadline-item"
                            >
                                <div
                                    class="deadline-indicator"
                                    :class="`priority-${deadline.priority}`"
                                ></div>
                                <div class="deadline-content">
                                    <h6>{{ deadline.title }}</h6>
                                    <p>
                                        {{ deadline.memberName }} • Due:
                                        {{ formatDate(deadline.dueDate) }}
                                    </p>
                                </div>
                                <div class="deadline-actions">
                                    <button
                                        class="btn-action btn-remind"
                                        @click="sendReminder(deadline)"
                                    >
                                        <i class="bi bi-bell"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ElMessage } from "element-plus";

export default {
    name: "TeamKpiDashboard",
    data() {
        return {
            currentDate: new Date(),
            teamMembers: [
                {
                    id: 1,
                    name: "Ahmad Wijaya",
                    position: "Sales Executive",
                    status: "completed",
                    progress: 100,
                },
                {
                    id: 2,
                    name: "Siti Rahma",
                    position: "Marketing Specialist",
                    status: "in-progress",
                    progress: 75,
                },
                {
                    id: 3,
                    name: "Budi Santoso",
                    position: "Sales Manager",
                    status: "not-started",
                    progress: 0,
                },
                {
                    id: 4,
                    name: "Dewi Lestari",
                    position: "Account Executive",
                    status: "in-progress",
                    progress: 50,
                },
            ],
            recentActivities: [
                {
                    id: 1,
                    memberName: "Ahmad Wijaya",
                    action: "menyelesaikan pengisian KPI",
                    status: "completed",
                    timestamp: new Date(Date.now() - 2 * 60 * 60 * 1000),
                },
                {
                    id: 2,
                    memberName: "Siti Rahma",
                    action: "mengupdate target KPI",
                    status: "in-progress",
                    timestamp: new Date(Date.now() - 4 * 60 * 60 * 1000),
                },
                {
                    id: 3,
                    memberName: "Budi Santoso",
                    action: "memulai pengisian KPI",
                    status: "in-progress",
                    timestamp: new Date(Date.now() - 1 * 24 * 60 * 60 * 1000),
                },
                {
                    id: 4,
                    memberName: "Dewi Lestari",
                    action: "mengajukan KPI untuk approval",
                    status: "pending",
                    timestamp: new Date(Date.now() - 6 * 60 * 60 * 1000),
                },
            ],
            upcomingDeadlines: [
                {
                    id: 1,
                    title: "Submit KPI Q1",
                    memberName: "Budi Santoso",
                    dueDate: new Date(Date.now() + 2 * 24 * 60 * 60 * 1000),
                    priority: "high",
                },
                {
                    id: 2,
                    title: "Review Performance",
                    memberName: "Siti Rahma",
                    dueDate: new Date(Date.now() + 5 * 24 * 60 * 60 * 1000),
                    priority: "medium",
                },
                {
                    id: 3,
                    title: "Target Assessment",
                    memberName: "Dewi Lestari",
                    dueDate: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000),
                    priority: "low",
                },
            ],
        };
    },
    computed: {
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },
        pendingSubmissions() {
            return this.teamMembers.filter(
                (m) => m.status === "not-started" || m.status === "in-progress"
            ).length;
        },
        completedSubmissions() {
            return this.teamMembers.filter((m) => m.status === "completed")
                .length;
        },
        approvedKPIs() {
            return this.teamMembers.filter((m) => m.status === "approved")
                .length;
        },
        overallProgress() {
            if (this.teamMembers.length === 0) return 0;
            const total = this.teamMembers.reduce(
                (sum, member) => sum + member.progress,
                0
            );
            return Math.round(total / this.teamMembers.length);
        },
        progressBreakdown() {
            return {
                notStarted: this.teamMembers.filter((m) => m.progress === 0)
                    .length,
                inProgress: this.teamMembers.filter(
                    (m) => m.progress > 0 && m.progress < 100
                ).length,
                completed: this.teamMembers.filter((m) => m.progress === 100)
                    .length,
            };
        },
    },
    methods: {
        formatDateString(date) {
            return new Date(date).toLocaleDateString("id-ID", {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric",
            });
        },
        formatRelativeTime(date) {
            const now = new Date();
            const diffMs = now - new Date(date);
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return "Baru saja";
            if (diffMins < 60) return `${diffMins} menit lalu`;
            if (diffHours < 24) return `${diffHours} jam lalu`;
            if (diffDays === 1) return "Kemarin";
            return `${diffDays} hari lalu`;
        },
        formatDate(date) {
            return new Date(date).toLocaleDateString("id-ID", {
                day: "numeric",
                month: "short",
                year: "numeric",
            });
        },
        getInitials(name) {
            if (!name) return "??";
            return name
                .split(" ")
                .map((n) => n[0])
                .join("")
                .toUpperCase();
        },
        getStatusText(status) {
            const statusMap = {
                completed: "Selesai",
                "in-progress": "Dalam Proses",
                "not-started": "Belum Mulai",
                pending: "Menunggu",
                approved: "Disetujui",
            };
            return statusMap[status] || status;
        },
        getProgressBarClass(progress) {
            if (progress < 30) return "low";
            if (progress < 70) return "medium";
            return "high";
        },
        openBulkTemplate() {
            ElMessage.info("Fitur Assign Template akan segera tersedia");
        },
        sendReminder(deadline) {
            ElMessage.success(`Reminder dikirim ke ${deadline.memberName}`);
        },
    },
    mounted() {
        // Inisialisasi data dashboard
        console.log("Team KPI Dashboard mounted");
    },
};
</script>

<style scoped>
/* Dashboard Specific Styles */
.team-stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--primary);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.stat-icon.team {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
}
.stat-icon.pending {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}
.stat-icon.completed {
    background: linear-gradient(135deg, #10b981, #059669);
}
.stat-icon.approved {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}

.stat-content h3 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--dark);
}

.stat-content p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

/* Section Cards */
.section-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    height: 100%;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.section-header h4 {
    margin: 0;
    color: var(--dark);
    font-size: 1.1rem;
    display: flex;
    align-items: center;
}

.btn-view-all {
    background: none;
    border: none;
    color: var(--primary);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.btn-view-all:hover {
    background: var(--surface-soft);
}

/* Activity List */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--surface-soft);
    border-radius: 8px;
    transition: all 0.3s ease;
}

.activity-item:hover {
    background: var(--surface);
    box-shadow: var(--shadow);
}

.activity-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.activity-content {
    flex: 1;
}

.activity-text {
    margin: 0 0 0.25rem 0;
    color: var(--text);
    font-size: 0.9rem;
}

.activity-time {
    color: var(--text-muted);
    font-size: 0.8rem;
}

.activity-status {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.activity-status.status-completed {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.activity-status.status-in-progress {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.activity-status.status-pending {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

/* Progress Overview */
.progress-overview {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.overall-progress {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.progress-label {
    color: var(--text);
    font-weight: 500;
}

.progress-value {
    color: var(--dark);
    font-weight: 600;
    font-size: 1.1rem;
}

.progress-bar {
    height: 8px;
    background: var(--border);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.progress-fill.low {
    background: var(--danger);
}
.progress-fill.medium {
    background: var(--warning);
}
.progress-fill.high {
    background: var(--success);
}

.progress-breakdown {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.breakdown-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--surface-soft);
    border-radius: 6px;
}

.breakdown-label {
    color: var(--text);
    font-size: 0.9rem;
}

.breakdown-value {
    color: var(--dark);
    font-weight: 600;
    background: var(--surface);
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8rem;
}

/* Quick Actions */
.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.action-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--surface-soft);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.action-card:hover {
    background: var(--surface);
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.action-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.action-content {
    flex: 1;
}

.action-content h5 {
    margin: 0 0 0.25rem 0;
    color: var(--dark);
    font-size: 1rem;
}

.action-content p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.action-arrow {
    color: var(--text-muted);
    font-size: 1.1rem;
}

/* Deadlines */
.deadlines-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.deadline-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--surface-soft);
    border-radius: 8px;
    transition: all 0.3s ease;
}

.deadline-item:hover {
    background: var(--surface);
}

.deadline-indicator {
    width: 4px;
    height: 40px;
    border-radius: 2px;
    flex-shrink: 0;
}

.deadline-indicator.priority-high {
    background: var(--danger);
}
.deadline-indicator.priority-medium {
    background: var(--warning);
}
.deadline-indicator.priority-low {
    background: var(--success);
}

.deadline-content {
    flex: 1;
}

.deadline-content h6 {
    margin: 0 0 0.25rem 0;
    color: var(--dark);
    font-size: 0.95rem;
}

.deadline-content p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    background: var(--surface);
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-action:hover {
    background: var(--primary);
    color: white;
}

/* Responsive Design */
@media (max-width: 768px) {
    .team-stats-overview {
        grid-template-columns: repeat(2, 1fr);
    }

    .action-grid {
        grid-template-columns: 1fr;
    }

    .activity-item {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .stat-card {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .action-card {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
}

@media (max-width: 480px) {
    .team-stats-overview {
        grid-template-columns: 1fr;
    }

    .section-header {
        flex-direction: column;
        gap: 0.75rem;
        align-items: flex-start;
    }
}
</style>

<style scoped>
/* CSS Variables */
*,
*::before,
*::after {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #a5b4fc;
    --secondary: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --light: #f8fafc;
    --dark: #1e293b;
    --surface: #ffffff;
    --surface-soft: #f1f5f9;
    --border: #e2e8f0;
    --text: #334155;
    --text-muted: #64748b;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* Loading Animation */
.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
}

.loading-animation {
    position: relative;
    margin-bottom: 1.5rem;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 3px solid var(--border);
    border-top: 3px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.loading-dots {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
}

.loading-dots span {
    width: 8px;
    height: 8px;
    background: var(--primary);
    border-radius: 50%;
    animation: bounce 1.4s ease-in-out infinite both;
}

.loading-dots span:nth-child(1) {
    animation-delay: -0.32s;
}
.loading-dots span:nth-child(2) {
    animation-delay: -0.16s;
}

.loading-text {
    color: var(--text-muted);
    font-size: 1.1rem;
    margin: 0;
}

/* target */

/* CSS untuk target form dalam modal */
.compact-target-form .compact-form-section {
    padding: 0;
    margin-top: 0;
    box-shadow: none;
    background: transparent;
}

.compact-target-form .optimized-form-grid {
    gap: 0.75rem;
}

.compact-target-form .form-group-optimized {
    margin-bottom: 0;
}

.compact-target-form .form-label-optimized {
    font-size: 0.8rem;
    margin-bottom: 0.25rem;
}

.compact-target-form .form-input-optimized {
    padding: 0.6rem 0.75rem;
    font-size: 0.85rem;
}

/* Responsive untuk monthly targets */
@media (max-width: 768px) {
    .compact-target-form .form-label-optimized {
        font-size: 0.75rem;
    }

    .compact-target-form .form-input-optimized {
        padding: 0.5rem 0.6rem;
        font-size: 0.8rem;
    }
}

/* akhir target */

/* Submission Layout Styles */
.submission-layout {
    min-height: 600px;
}

.mobileStatus {
    width: fit-content !important;
    /* background: none !important; */
    /* color: var(--danger) !important; */
    padding: 0 1rem !important;
}

.sticky-kpi-sidebar {
    position: sticky;
    top: 1rem;
    height: fit-content;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
}

.submission-content {
    height: 100%;
    min-height: 600px;
}

.mobile-submission-layout {
    display: flex;
    flex-direction: column;
}

/* Compact KPI List Adjustments untuk sticky */
.compact-kpi-list {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    margin-bottom: 1rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .compact-kpi-list {
        padding: 1rem;
        margin-bottom: 0.75rem;
    }

    .submission-actions-compact {
        margin-bottom: 0.75rem;
    }

    .approval-notes {
        margin-bottom: 0.75rem;
    }
}

/* Scrollbar styling untuk sticky sidebar */
.sticky-kpi-sidebar::-webkit-scrollbar {
    width: 4px;
}

.sticky-kpi-sidebar::-webkit-scrollbar-track {
    background: var(--surface-soft);
    border-radius: 2px;
}

.sticky-kpi-sidebar::-webkit-scrollbar-thumb {
    background: var(--primary-light);
    border-radius: 2px;
}

.sticky-kpi-sidebar::-webkit-scrollbar-thumb:hover {
    background: var(--primary);
}
/* akhir form submitted */

.modern-stepper-container {
    margin-bottom: 0 !important;
}

/* Optional: Styling tambahan untuk stepper */
.stepper-container {
    margin-bottom: 2rem;
}

/* KPI Detail Modal */
.kpi-detail-content {
    padding: 0.5rem;
}

.detail-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.detail-row {
    display: flex;
    border-bottom: 1px solid var(--border);
    padding-bottom: 0.75rem;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: var(--dark);
    width: 120px;
    flex-shrink: 0;
    font-size: 0.9rem;
}

.detail-value {
    flex: 1;
    color: var(--text);
    font-size: 0.9rem;
}

/* Status Header */
.status-header {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
}

.status-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    opacity: 0.95;
}

.badge-primary {
    background: var(--primary);
}
.badge-success {
    background: var(--success);
}

.badge-danger {
    border: 1px solid #ff6b35;
    background: var(--danger);
    box-shadow: 0 0 10px rgba(255, 107, 53, 0.3),
        inset 0 0 0 1px rgba(255, 107, 53, 0.1);
    animation: pulse-revision 2s infinite;
}

@keyframes pulse-revision {
    0%,
    100% {
        box-shadow: 0 0 10px rgba(255, 107, 53, 0.3);
    }
    50% {
        box-shadow: 0 0 15px rgba(255, 107, 53, 0.6);
    }
}

.status-pending .status-background {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.status-approved .status-background {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.status-rejected .status-background {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.status-content {
    position: relative;
    z-index: 2;
    padding: 1.5rem;
    color: white;
}

.status-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.status-info .icon-container {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.status-actions {
    display: flex;
    justify-content: flex-end;
}

.approval-info,
.submission-info {
    margin-top: 0.5rem;
    opacity: 0.9;
}

/* Compact KPI List */
.compact-kpi-list {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
}

.section-header-compact {
    margin-bottom: 1.5rem;
}

.section-header-compact .section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.section-header-compact .section-subtitle {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.85rem;
}

.kpi-grid {
    width: 100%;
}

.kpi-card-compact {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1rem;
    min-width: 15rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.kpi-card-compact.revision {
    border: 2px solid #ff6b35;
    background: linear-gradient(135deg, var(--surface-soft) 0%, #fff8f6 100%);
    box-shadow: 0 0 0 1px #ff6b35 inset;
    position: relative;
}

.kpi-card-compact.revision::before {
    content: "⚠️";
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff4444;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.kpi-card-compact:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    border-color: var(--primary-light);
}

.kpi-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.3rem;
}

.kpi-number {
    background: var(--primary);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
}
.kpi-number.disabled {
    background: var(--primary);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    opacity: 30%;
    cursor: not-allowed;
}

.kpi-card-body {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.kpi-icon-compact {
    width: 46px;
    height: 46px;
    background: var(--primary-light);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1rem;
    flex-shrink: 0;
}

.kpi-content {
    flex: 1;
    min-width: 0;
}

.kpi-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kpi-meta-compact {
    display: flex;
    gap: 0.5rem;
    font-size: 0.75rem;
}

.category-badge {
    background: var(--surface);
    color: var(--text-muted);
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    border: 1px solid var(--border);
}

.weight-badge {
    background: var(--primary);
    color: white;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    font-weight: 600;
}

.kpi-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.completion-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.completion-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--warning);
}

.completion-dot.completed {
    background: var(--success);
}

/* Compact Action Buttons */
.submission-actions-compact {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.btn-action-compact {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-action-compact.primary {
    background: var(--primary);
    color: white;
}

.btn-action-compact.secondary {
    background: var(--surface-soft);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-action-compact:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Approval Notes */
.approval-notes {
    background: var(--surface-soft);
    border-radius: 10px;
    padding: 1.25rem;
    border-left: 4px solid var(--info);
}

.notes-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    font-weight: 600;
    color: var(--dark);
}

.notes-header i {
    color: var(--info);
}

.notes-content {
    color: var(--text);
    line-height: 1.5;
    font-size: 0.9rem;
}

/* Loading State */
.loading-state {
    background: var(--surface);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.loading-spinner {
    width: 80px;
    height: 80px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--primary);
    font-size: 2rem;
}

.loading-spinner .bi-arrow-repeat {
    animation: spin 1s linear infinite;
}

.loading-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.loading-description {
    color: var(--text-muted);
    margin: 0;
}

/* Spin Animation */
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Empty KPI State */
.empty-kpi-state {
    background: var(--surface);
    border-radius: 16px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
}

.empty-kpi-state .empty-icon {
    width: 80px;
    height: 80px;
    background: var(--surface-soft);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--text-muted);
    font-size: 2rem;
}

.empty-kpi-state .empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.empty-kpi-state .empty-description {
    color: var(--text-muted);
    margin-bottom: 1.5rem;
}

/* Section Header Compact dengan Badge */
.section-title-wrapper {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.25rem;
}

.section-badge {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* No Schedule State */
.empty-state-schedule {
    background: var(--surface);
    border-radius: 20px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.empty-icon-container {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
    color: white;
    font-size: 2.5rem;
}

.empty-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 1rem;
}

.empty-description {
    color: var(--text-muted);
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.empty-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 2rem;
}

/* modal target */
.modal-target-content {
    padding: 0.5rem;
}

.target-scale-section {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.25rem;
    border: 1px solid var(--border);
}

.label-icon-container {
    width: 40px;
    height: 40px;
    background: var(--primary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.label-text {
    flex: 1;
}

.label-title {
    display: block;
    font-weight: 600;
    color: var(--dark);
    font-size: 0.95rem;
}

.label-subtitle {
    display: block;
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.form-label-optimized {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem !important;
}

:deep(.target-scale-select .vs__dropdown-toggle) {
    padding: 0.75rem !important;
    border: 2px solid var(--border) !important;
    border-radius: 10px !important;
    background: var(--surface);
    transition: all 0.3s ease;
}

/* Menggunakan class 'vs--disabled' pada kontainer terluar */
:deep(.target-scale-select.vs--disabled .vs__dropdown-toggle) {
    background: #f8f8f8 !important; /* Gunakan !important jika diperlukan */
    /* Opsional: Terapkan gaya kursor non-aktif */
    pointer-events: none;
    cursor: not-allowed !important;
    cursor: not-allowed;
}

:deep(.target-scale-select .vs__dropdown-toggle:focus-within) {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
}

:deep(.target-scale-select .vs__selected) {
    background: var(--primary-light) !important;
    color: var(--primary-dark) !important;
    border: none !important;
    border-radius: 6px !important;
    font-weight: 500;
}

/* Target Form Section */
.target-form-section {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border);
    animation: slideIn 0.3s ease;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.section-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--success), #34d399);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.section-subtitle {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin: 0.25rem 0 0 0;
}

.target-fields-grid {
    margin-top: 1rem;
}

:deep(.compact-form .form-group) {
    margin-bottom: 1rem;
}

:deep(.compact-form .form-label) {
    font-weight: 600;
    color: var(--dark);
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

:deep(.compact-form .form-control) {
    border: 2px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

:deep(.compact-form .form-control:focus) {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: var(--surface-soft);
    border-radius: 12px;
    border: 2px dashed var(--border);
}

.empty-icon {
    font-size: 3rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

.empty-text {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.95rem;
}

/* Instruction State */
.instruction-state {
    text-align: center;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    padding: 3rem 2rem;
    background: var(--surface-soft);
    border-radius: 12px;
    border: 2px dashed var(--border);
}

.instruction-icon {
    font-size: 3rem;
    color: var(--primary);
    /* margin-bottom: 1rem; */
    opacity: 0.7;
}

.instruction-title {
    color: var(--dark);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.instruction-text {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.5;
}

/* General Buttons */
.btn-modern {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.2rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    color: white !important;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.btn-modern::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.2),
        transparent
    );
    transition: left 0.5s;
}

.btn-modern:hover::before {
    left: 100%;
}

.btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-modern:disabled:hover {
    transform: none !important;
    box-shadow: none !important;
}

/* Header */
.modern-header {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--shadow);
    min-height: 140px;
}

.header-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--gradient-primary);
    opacity: 0.95;
}

.header-background::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><g fill="%23ffffff" fill-opacity="0.1"><circle cx="30" cy="30" r="2"/></g></svg>');
    animation: float 20s ease-in-out infinite;
}

.header-content {
    position: relative;
    z-index: 2;
    padding: 2.5rem;
    color: white;
}

.header-info {
    display: flex;
    align-items: center;
    justify-content: start;
    gap: 1.5rem;
}

.icon-container {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-container:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.icon-container i {
    font-size: 2.2rem;
    z-index: 2;
}

.title {
    font-size: 2rem;
    font-weight: 400;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    letter-spacing: -0.025em;
}

.subtitle {
    text-align: start;
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
    font-weight: 400;
}

.header-actions {
    height: 100%;
}

.date-range {
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.date-item {
    text-align: center;
    color: white;
}

.btn-primary {
    background-color: var(--primary);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(20px);
}

.btn-primary:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    color: var(--text);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

/* Compact Dashboard Styles */
.compact-dashboard {
    background: var(--surface);
    border-radius: 16px;
    box-shadow: var(--shadow);
    padding: 0.3rem 1rem;
    margin-bottom: 1rem;
}

/* Horizontal Scroll Cards */
.cards-scroll-container {
    position: relative;
    margin-top: 1rem;
}

.cards-scroll-wrapper {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    scroll-behavior: smooth;
    padding-bottom: 1rem;
    padding-top: 0.4rem;
}
/* Compact Card Base */
.compact-card {
    min-width: 260px;
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    flex-shrink: 0;
}

/* Active State */
.compact-card.active {
    border-color: var(--primary);
    background: linear-gradient(135deg, var(--surface) 0%, #f8fafc 100%);
}

/* Completed State - hanya apply jika bukan revision */
.compact-card.completed {
    border-left: 4px solid var(--success);
}

/* Hover Effect untuk kartu normal */
.compact-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-light);
}

/* ========== REVISION STATE (Prioritas Tinggi) ========== */
.compact-card.revision {
    background: linear-gradient(135deg, var(--surface-soft) 0%, #fff8f6 100%);
    border: 2px solid #ff6b35;
    box-shadow: 0 0 0 1px #ff6b35 inset, 0 4px 12px rgba(255, 107, 53, 0.15);
    animation: pulse-revision 2s ease-in-out infinite;

    /* Pastikan revision tetap interaktif */
    cursor: pointer;
    pointer-events: auto;
    opacity: 1;
    filter: none;
}

/* Badge/indicator revision */
.compact-card.revision::before {
    content: "⚠";
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ff4444;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    z-index: 10;
}

/* Pulse animation untuk revision */
@keyframes pulse-revision {
    0%,
    100% {
        box-shadow: 0 0 0 1px #ff6b35 inset, 0 4px 12px rgba(255, 107, 53, 0.15);
    }
    50% {
        box-shadow: 0 0 0 1px #ff6b35 inset, 0 4px 20px rgba(255, 107, 53, 0.3);
    }
}

/* Hover effect untuk revision */
.compact-card.revision:hover {
    background: linear-gradient(135deg, var(--surface-soft) 0%, #fff1eb 100%);
    border-color: #ff5722;
    transform: translateY(-2px);
    box-shadow: 0 0 0 1px #ff5722 inset, 0 6px 16px rgba(255, 87, 34, 0.25);
}

/* Active state untuk revision */
.compact-card.revision:active {
    transform: translateY(0);
    box-shadow: 0 0 0 1px #ff6b35 inset, 0 2px 8px rgba(255, 107, 53, 0.2);
}

/* ========== DISABLED STATE (hanya apply jika BUKAN revision) ========== */
.compact-card.disabled:not(.revision) {
    background: var(--surface-disabled, #f5f5f5);
    border: 2px solid var(--border-disabled, #e0e0e0);
    border-radius: 12px;
    padding: 1rem;
    position: relative;
    flex-shrink: 0;

    /* Non-interactive state */
    cursor: not-allowed;
    pointer-events: none;

    /* Visual disabled effect */
    opacity: 0.6;
    filter: grayscale(0.3);

    /* Remove hover/focus transitions */
    transition: none;
}

/* Override hover untuk disabled */
.compact-card.disabled:not(.revision):hover,
.compact-card.disabled:not(.revision):focus {
    background: var(--surface-disabled, #f5f5f5);
    border-color: var(--border-disabled, #e0e0e0);
    transform: none;
    box-shadow: none;
}

/* Text styling untuk disabled */
.compact-card.disabled:not(.revision) .card-content {
    color: var(--text-disabled, #9e9e9e);
}

/* Overlay disabled icon */
.compact-card.disabled:not(.revision)::after {
    content: "🚫";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 1.5rem;
    opacity: 0.8;
    z-index: 2;
}

/* ========== ADD NEW CARD ========== */
.compact-card.add-new {
    background: linear-gradient(135deg, var(--surface-soft) 0%, #f1f5f9 100%);
    border: 2px dashed var(--border);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100px;
}
.compact-card.add-new:hover {
    border-color: var(--primary);
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.card-badge {
    background: var(--primary);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
}

.card-actions {
    opacity: 1;
    transition: opacity 0.3s ease;
}

.btn-remove {
    background: var(--danger);
    color: white;
    border: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    cursor: pointer;
}

.card-main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.add-icon {
    width: 40px;
    height: 40px;
    background: var(--primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.kpi-icon {
    width: 40px;
    height: 40px;
    background: var(--primary-light);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.2rem;
    flex-shrink: 0;
}

.kpi-info {
    flex: 1;
    min-width: 0;
}

.card-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kpi-meta {
    display: flex;
    gap: 0.5rem;
    font-size: 0.75rem;
}

.category {
    color: var(--text-muted);
    background: var(--surface-soft);
    padding: 0.2rem 0.4rem;
    border-radius: 6px;
}

.weight {
    color: var(--primary);
    font-weight: 600;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}

.progress-container {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.progress-bar {
    flex: 1;
    height: 4px;
    background: var(--border);
    border-radius: 2px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--success);
    border-radius: 2px;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-muted);
    min-width: 30px;
}

.card-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-align: center;
    margin-top: 0.5rem;
}

/* Bottom Action Bar */
.bottom-action-bar-integrated {
    position: sticky;
    bottom: 0;
    border-radius: 12px;
    background: var(--surface);
    border-top: 1px solid var(--border);
    padding: 1rem;
    margin-top: 1rem;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
    z-index: 100;
}

.action-content-integrated {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    flex-wrap: nowrap;
}

.action-info {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex: 1;
}

.info-text {
    font-weight: 600;
    color: var(--dark);
    white-space: nowrap;
}

.weight-info {
    font-size: 0.85rem;
    color: var(--text-muted);
    background: var(--surface-soft);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    white-space: nowrap;
}

.weight-info.danger {
    color: var(--surface);
    background: var(--danger);
}

.action-buttons-integrated {
    display: flex;
    gap: 0.75rem;
    flex-wrap: nowrap;
}

.btn-action-integrated {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.btn-action-integrated.validate {
    background: var(--warning);
    color: white;
}

.btn-action-integrated.submit {
    background: var(--success);
    color: white;
}

.btn-action-integrated:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Modal Styles */
.delete-modal-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    text-align: center;
}

.warning-icon {
    font-size: 2rem;
    color: var(--warning);
}

.delete-modal-footer {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.btn-secondary {
    background: var(--surface-soft);
    color: var(--text-muted);
}

.btn-success {
    background: var(--success);
    color: white;
}

/* Responsive Design */
@media (max-widt: 920px) {
    section-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .form-label-optimized {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .empty-state,
    .instruction-state {
        padding: 2rem 1rem;
    }
}
@media (max-width: 768px) {
    .detail-row {
        flex-direction: column;
        gap: 0.25rem;
    }

    .detail-label {
        width: 100%;
    }

    .modern-header {
        min-height: 120px;
    }

    .header-content {
        padding: 1rem;
    }

    .header-info {
        gap: 0.75rem;
    }

    .icon-container {
        width: 50px;
        height: 50px;
    }

    .icon-container i {
        font-size: 1.5rem;
    }

    .title {
        font-size: 1.25rem;
    }

    .subtitle {
        font-size: 0.9rem;
    }

    .header-actions {
        flex-direction: row;
        justify-content: center;
        flex-wrap: wrap;
    }

    .date-range {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
    }

    .btn-modern {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }

    .action-content-integrated {
        flex-direction: row;
        gap: 1rem;
    }

    .action-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .action-buttons-integrated {
        flex: 1;
        justify-content: flex-end;
    }

    .btn-action-integrated {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }

    .modal-target-content {
        padding: 0.25rem;
    }

    .target-scale-section {
        padding: 1rem;
    }

    .target-form-section {
        padding: 1rem;
    }

    /* .section-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .form-label-optimized {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .empty-state,
    .instruction-state {
        padding: 2rem 1rem;
    } */
}

@media (max-width: 576px) {
    .header-content {
        padding: 1rem;
    }

    .title {
        font-size: 1.1rem;
    }

    .subtitle {
        font-size: 0.85rem;
    }

    .header-actions {
        flex-direction: column;
        width: 100%;
    }

    .date-range,
    .btn-modern {
        width: 100%;
        justify-content: center;
    }

    .action-content-integrated {
        flex-direction: column;
        gap: 0.75rem;
        text-align: center;
    }

    .action-info {
        flex-direction: row;
        justify-content: center;
        width: 100%;
    }

    .action-buttons-integrated {
        width: 100%;
        justify-content: center;
    }

    .btn-action-integrated {
        flex: 1;
        justify-content: center;
    }
}

/* Animations */
@keyframes float {
    0%,
    100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

.compact-card {
    opacity: 0;
    animation: slideIn 0.2s ease forwards;
    will-change: transform, opacity;
}

.compact-card:nth-child(odd) {
    animation-delay: 0.1s;
}

.compact-card:nth-child(even) {
    animation-delay: 0.2s;
}

:deep(.vs__dropdown-toggle) {
    padding: 0.62rem !important;
    border: 1px solid var(--border) !important;
    border-radius: 8px !important;
    font-size: 0.9rem !important;
}

:deep(.v-select .vs__dropdown-toggle),
:deep(.v-select .vs__selected),
:deep(.v-select .vs__search) {
    font-size: 14px !important;
    color: var(--text) !important;
}

/* Grid layout untuk monthly targets */
:deep(.target-fields-grid .row) {
    margin: -0.5rem;
}

:deep(.target-fields-grid .col-6) {
    padding: 0.5rem;
}

:deep(.target-fields-grid .form-control) {
    background: var(--surface);
}
</style>
