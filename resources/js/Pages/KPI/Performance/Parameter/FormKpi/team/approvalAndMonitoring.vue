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
                                            class="bi bi-clipboard-check d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2
                                            class="title fw-bold text-white text-start"
                                        >
                                            Approval Monitoring
                                        </h2>
                                        <p class="subtitle">
                                            Pantau status persetujuan KPI tim
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
                <div class="approval-stats-overview">
                    <div class="stat-card" @click="setActiveTab('pending')">
                        <div class="stat-icon pending">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ pendingCount }}</h3>
                            <p>Menunggu Approval</p>
                            <div class="stat-trend" v-if="pendingTrend > 0">
                                <i class="bi bi-arrow-up"></i>
                                {{ pendingTrend }}%
                            </div>
                        </div>
                    </div>
                    <div class="stat-card" @click="setActiveTab('approved')">
                        <div class="stat-icon approved">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ approvedCount }}</h3>
                            <p>Disetujui</p>
                            <div
                                class="stat-trend positive"
                                v-if="approvedTrend > 0"
                            >
                                <i class="bi bi-arrow-up"></i>
                                {{ approvedTrend }}%
                            </div>
                        </div>
                    </div>
                    <div class="stat-card" @click="setActiveTab('rejected')">
                        <div class="stat-icon rejected">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ rejectedCount }}</h3>
                            <p>Ditolak</p>
                            <div class="stat-trend" v-if="rejectedTrend > 0">
                                <i class="bi bi-arrow-up"></i>
                                {{ rejectedTrend }}%
                            </div>
                        </div>
                    </div>
                    <div class="stat-card" @click="setActiveTab('analytics')">
                        <div class="stat-icon analytics">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <div class="stat-content">
                            <h3>{{ approvalRate }}%</h3>
                            <p>Approval Rate</p>
                            <div
                                class="stat-trend positive"
                                v-if="approvalRateTrend > 0"
                            >
                                <i class="bi bi-arrow-up"></i>
                                {{ approvalRateTrend }}%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="action-bar">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4 class="section-title mb-0">
                                Monitoring Approval
                            </h4>
                            <p class="section-subtitle mb-0">
                                Kelola persetujuan KPI untuk tim Anda
                            </p>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="action-buttons">
                                <button
                                    class="btn-modern btn-secondary"
                                    @click="exportReport"
                                >
                                    <i class="bi bi-download"></i>
                                    Export Report
                                </button>
                                <button
                                    class="btn-modern btn-primary"
                                    @click="refreshData"
                                >
                                    <i class="bi bi-arrow-clockwise"></i>
                                    Refresh Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approval Tabs -->
        <div class="approval-tabs-section">
            <div class="tabs-header">
                <div
                    v-for="tab in approvalTabs"
                    :key="tab.id"
                    class="approval-tab"
                    :class="{ active: activeTab === tab.id }"
                    @click="setActiveTab(tab.id)"
                >
                    <i :class="tab.icon"></i>
                    {{ tab.label }}
                    <span class="tab-badge">{{ getTabCount(tab.id) }}</span>
                </div>
            </div>

            <div class="tabs-content">
                <!-- Pending Approval Tab -->
                <div v-if="activeTab === 'pending'" class="tab-pane">
                    <div class="tab-header">
                        <h5>Submissions Menunggu Approval</h5>
                        <div class="tab-actions">
                            <button
                                class="btn-tab-action"
                                @click="bulkApprove"
                                :disabled="selectedSubmissions.length === 0"
                            >
                                <i class="bi bi-check-all"></i>
                                Approve Selected
                            </button>
                            <button
                                class="btn-tab-action"
                                @click="bulkReject"
                                :disabled="selectedSubmissions.length === 0"
                            >
                                <i class="bi bi-x-circle"></i>
                                Reject Selected
                            </button>
                        </div>
                    </div>

                    <div class="approval-list">
                        <div
                            v-for="submission in pendingSubmissions"
                            :key="submission.id"
                            class="approval-item"
                            :class="{
                                selected: isSubmissionSelected(submission.id),
                            }"
                        >
                            <div class="selection-checkbox">
                                <input
                                    type="checkbox"
                                    :checked="
                                        isSubmissionSelected(submission.id)
                                    "
                                    @change="
                                        toggleSubmissionSelection(submission.id)
                                    "
                                />
                            </div>

                            <div
                                class="submission-content"
                                @click="viewSubmission(submission)"
                            >
                                <div class="submission-header">
                                    <div class="submitter-info">
                                        <div class="avatar-md">
                                            {{
                                                getInitials(
                                                    submission.memberName
                                                )
                                            }}
                                        </div>
                                        <div class="submitter-details">
                                            <h5>{{ submission.memberName }}</h5>
                                            <p>
                                                {{ submission.position }} •
                                                {{ submission.department }}
                                            </p>
                                            <div class="submission-meta">
                                                <span class="meta-item">
                                                    <i
                                                        class="bi bi-calendar"
                                                    ></i>
                                                    {{
                                                        formatDate(
                                                            submission.submittedAt
                                                        )
                                                    }}
                                                </span>
                                                <span class="meta-item">
                                                    <i class="bi bi-clock"></i>
                                                    {{
                                                        formatRelativeTime(
                                                            submission.submittedAt
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="submission-stats">
                                        <div class="stat">
                                            <i class="bi bi-layers"></i>
                                            <span
                                                >{{
                                                    submission.kpiCount
                                                }}
                                                KPI</span
                                            >
                                        </div>
                                        <div class="stat">
                                            <i class="bi bi-speedometer2"></i>
                                            <span
                                                >{{ submission.totalWeight }}%
                                                Bobot</span
                                            >
                                        </div>
                                        <div class="stat">
                                            <i class="bi bi-calendar-event"></i>
                                            <span
                                                >Q{{ submission.quarter }}
                                                {{ submission.year }}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div class="submission-preview">
                                    <div class="kpi-preview">
                                        <div
                                            v-for="(
                                                kpi, index
                                            ) in submission.kpisPreview"
                                            :key="index"
                                            class="kpi-preview-item"
                                        >
                                            <span class="kpi-name">{{
                                                kpi.parameter
                                            }}</span>
                                            <span class="kpi-weight"
                                                >{{ kpi.weight }}%</span
                                            >
                                        </div>
                                        <div
                                            class="more-kpis"
                                            v-if="submission.kpiCount > 3"
                                        >
                                            +{{ submission.kpiCount - 3 }} more
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="submission-actions">
                                <button
                                    class="btn-action btn-view"
                                    @click="viewSubmission(submission)"
                                >
                                    <i class="bi bi-eye"></i>
                                    Review
                                </button>
                                <button
                                    class="btn-action btn-approve"
                                    @click="approveSubmission(submission)"
                                >
                                    <i class="bi bi-check-lg"></i>
                                    Approve
                                </button>
                                <button
                                    class="btn-action btn-reject"
                                    @click="rejectSubmission(submission)"
                                >
                                    <i class="bi bi-x-lg"></i>
                                    Reject
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="pendingSubmissions.length === 0"
                        class="empty-state-tab"
                    >
                        <i class="bi bi-check-circle"></i>
                        <h5>Tidak Ada Submission Menunggu</h5>
                        <p>Semua submission KPI sudah diproses</p>
                    </div>
                </div>

                <!-- Approved Tab -->
                <div v-if="activeTab === 'approved'" class="tab-pane">
                    <div class="tab-header">
                        <h5>KPI yang Disetujui</h5>
                        <div class="tab-filters">
                            <select
                                v-model="approvedFilter"
                                class="filter-select"
                            >
                                <option value="all">Semua</option>
                                <option value="week">Minggu Ini</option>
                                <option value="month">Bulan Ini</option>
                                <option value="quarter">Kuartal Ini</option>
                            </select>
                        </div>
                    </div>

                    <div class="approved-grid">
                        <div
                            v-for="submission in filteredApproved"
                            :key="submission.id"
                            class="approved-card"
                        >
                            <div class="card-header">
                                <div class="member-avatar">
                                    {{ getInitials(submission.memberName) }}
                                </div>
                                <div class="member-info">
                                    <h5>{{ submission.memberName }}</h5>
                                    <p>{{ submission.position }}</p>
                                    <div class="approval-details">
                                        <span class="approver"
                                            >Oleh:
                                            {{ submission.approvedBy }}</span
                                        >
                                        <span class="approval-date">{{
                                            formatDate(submission.approvedAt)
                                        }}</span>
                                    </div>
                                </div>
                                <div class="approval-badge approved">
                                    <i class="bi bi-check-circle"></i>
                                    Disetujui
                                </div>
                            </div>

                            <div class="kpi-summary">
                                <div class="summary-item">
                                    <i class="bi bi-layers"></i>
                                    <span>{{ submission.kpiCount }} KPI</span>
                                </div>
                                <div class="summary-item">
                                    <i class="bi bi-speedometer2"></i>
                                    <span
                                        >{{ submission.totalWeight }}%
                                        Bobot</span
                                    >
                                </div>
                                <div class="summary-item">
                                    <i class="bi bi-star"></i>
                                    <span
                                        >Score:
                                        {{
                                            submission.performanceScore
                                        }}/5</span
                                    >
                                </div>
                            </div>

                            <div class="performance-indicator">
                                <div class="performance-label">
                                    Performance Indicator
                                </div>
                                <div class="performance-bar">
                                    <div
                                        class="performance-fill"
                                        :style="{
                                            width:
                                                submission.performanceScore *
                                                    20 +
                                                '%',
                                        }"
                                        :class="
                                            getPerformanceClass(
                                                submission.performanceScore
                                            )
                                        "
                                    ></div>
                                </div>
                                <div class="performance-value">
                                    {{ submission.performanceScore }}/5
                                </div>
                            </div>

                            <div class="card-footer">
                                <button
                                    class="btn-card btn-view"
                                    @click="viewApprovedSubmission(submission)"
                                >
                                    <i class="bi bi-eye"></i>
                                    Lihat Detail
                                </button>
                                <button
                                    class="btn-card btn-download"
                                    @click="downloadApproval(submission)"
                                >
                                    <i class="bi bi-download"></i>
                                    Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rejected Tab -->
                <div v-if="activeTab === 'rejected'" class="tab-pane">
                    <!-- Similar structure to approved tab but with rejection reasons -->
                    <div class="rejected-list">
                        <div
                            v-for="submission in rejectedSubmissions"
                            :key="submission.id"
                            class="rejected-card"
                        >
                            <div class="card-header">
                                <div class="member-avatar">
                                    {{ getInitials(submission.memberName) }}
                                </div>
                                <div class="member-info">
                                    <h5>{{ submission.memberName }}</h5>
                                    <p>{{ submission.position }}</p>
                                    <div class="rejection-details">
                                        <span class="rejector"
                                            >Oleh:
                                            {{ submission.rejectedBy }}</span
                                        >
                                        <span class="rejection-date">{{
                                            formatDate(submission.rejectedAt)
                                        }}</span>
                                    </div>
                                </div>
                                <div class="approval-badge rejected">
                                    <i class="bi bi-x-circle"></i>
                                    Ditolak
                                </div>
                            </div>

                            <div class="rejection-reason">
                                <h6>Alasan Penolakan:</h6>
                                <p>{{ submission.rejectionReason }}</p>
                            </div>

                            <div class="card-footer">
                                <button
                                    class="btn-card btn-view"
                                    @click="viewRejectedSubmission(submission)"
                                >
                                    <i class="bi bi-eye"></i>
                                    Lihat Detail
                                </button>
                                <button
                                    class="btn-card btn-edit"
                                    @click="editRejectedSubmission(submission)"
                                >
                                    <i class="bi bi-pencil"></i>
                                    Revisi
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Tab -->
                <div v-if="activeTab === 'analytics'" class="tab-pane">
                    <div class="analytics-dashboard">
                        <div class="metrics-grid">
                            <div class="metric-card">
                                <div class="metric-icon">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div class="metric-content">
                                    <h3>{{ averageApprovalTime }}</h3>
                                    <p>Rata-rata Waktu Approval</p>
                                    <small>Hari</small>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-icon">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div class="metric-content">
                                    <h3>{{ approvalRate }}<span>%</span></h3>
                                    <p>Approval Rate</p>
                                    <small>Bulan Ini</small>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-icon">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <div class="metric-content">
                                    <h3>{{ performanceAverage }}</h3>
                                    <p>Rata-rata Performance</p>
                                    <small>/5.0</small>
                                </div>
                            </div>
                        </div>

                        <div class="charts-section">
                            <div class="chart-card">
                                <h5>Approval Trend</h5>
                                <div class="chart-placeholder">
                                    <i class="bi bi-bar-chart"></i>
                                    <p>Chart akan ditampilkan di sini</p>
                                </div>
                            </div>

                            <div class="chart-card">
                                <h5>Department Performance</h5>
                                <div class="chart-placeholder">
                                    <i class="bi bi-pie-chart"></i>
                                    <p>Chart akan ditampilkan di sini</p>
                                </div>
                            </div>
                        </div>

                        <div class="department-stats">
                            <h5>Statistik per Department</h5>
                            <div class="stats-table">
                                <div class="table-header">
                                    <span>Department</span>
                                    <span>Submitted</span>
                                    <span>Approved</span>
                                    <span>Rejected</span>
                                    <span>Rate</span>
                                </div>
                                <div
                                    v-for="dept in departmentStats"
                                    :key="dept.name"
                                    class="table-row"
                                >
                                    <span class="dept-name">{{
                                        dept.name
                                    }}</span>
                                    <span class="dept-submitted">{{
                                        dept.submitted
                                    }}</span>
                                    <span class="dept-approved">{{
                                        dept.approved
                                    }}</span>
                                    <span class="dept-rejected">{{
                                        dept.rejected
                                    }}</span>
                                    <span class="dept-rate"
                                        >{{ dept.approvalRate }}%</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submission Detail Modal -->
        <div
            v-if="selectedSubmission"
            class="modal-overlay"
            @click="closeModal"
        >
            <div class="modal-content submission-modal" @click.stop>
                <div class="modal-header">
                    <h3 class="modal-title">Review KPI Submission</h3>
                    <button class="modal-close" @click="closeModal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Submission detail content -->
                    <div class="submission-detail">
                        <div class="detail-header">
                            <div class="member-info">
                                <div class="avatar-lg">
                                    {{
                                        getInitials(
                                            selectedSubmission.memberName
                                        )
                                    }}
                                </div>
                                <div class="member-details">
                                    <h4>{{ selectedSubmission.memberName }}</h4>
                                    <p>
                                        {{ selectedSubmission.position }} •
                                        {{ selectedSubmission.department }}
                                    </p>
                                    <div class="submission-info">
                                        <span
                                            >Submitted:
                                            {{
                                                formatDateTime(
                                                    selectedSubmission.submittedAt
                                                )
                                            }}</span
                                        >
                                        <span
                                            >Period: Q{{
                                                selectedSubmission.quarter
                                            }}
                                            {{ selectedSubmission.year }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                            <div class="submission-status">
                                <span
                                    :class="`status-badge status-${selectedSubmission.status}`"
                                >
                                    {{
                                        getStatusText(selectedSubmission.status)
                                    }}
                                </span>
                            </div>
                        </div>

                        <div class="kpi-detail-list">
                            <div
                                v-for="(kpi, index) in selectedSubmission.kpis"
                                :key="kpi.id"
                                class="kpi-detail-item"
                            >
                                <div class="kpi-header">
                                    <div class="kpi-number">
                                        #{{ index + 1 }}
                                    </div>
                                    <div class="kpi-info">
                                        <h5>{{ kpi.parameter }}</h5>
                                        <div class="kpi-meta">
                                            <span class="category">{{
                                                kpi.category
                                            }}</span>
                                            <span class="weight"
                                                >{{ kpi.weight }}%</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="kpi-content">
                                    <p>
                                        <strong>Target:</strong>
                                        {{ kpi.target }}
                                    </p>
                                    <p>
                                        <strong>Deskripsi:</strong>
                                        {{ kpi.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="modal-footer"
                    v-if="selectedSubmission.status === 'pending'"
                >
                    <button
                        class="btn-modern btn-secondary"
                        @click="closeModal"
                    >
                        Tutup
                    </button>
                    <button
                        class="btn-modern btn-danger"
                        @click="rejectSubmission(selectedSubmission)"
                    >
                        Tolak
                    </button>
                    <button
                        class="btn-modern btn-success"
                        @click="approveSubmission(selectedSubmission)"
                    >
                        Setujui
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ElMessage, ElMessageBox } from "element-plus";

export default {
    name: "ApprovalMonitoring",
    data() {
        return {
            currentDate: new Date(),
            activeTab: "pending",
            selectedSubmissions: [],
            selectedSubmission: null,
            approvedFilter: "all",
            approvalTabs: [
                {
                    id: "pending",
                    label: "Menunggu Approval",
                    icon: "bi bi-clock-history",
                },
                {
                    id: "approved",
                    label: "Disetujui",
                    icon: "bi bi-check-circle",
                },
                { id: "rejected", label: "Ditolak", icon: "bi bi-x-circle" },
                { id: "analytics", label: "Analytics", icon: "bi bi-graph-up" },
            ],
            pendingSubmissions: [
                {
                    id: 1,
                    memberName: "Ahmad Wijaya",
                    position: "Sales Executive",
                    department: "Sales",
                    submittedAt: new Date(Date.now() - 2 * 60 * 60 * 1000),
                    kpiCount: 5,
                    totalWeight: 100,
                    quarter: 1,
                    year: 2024,
                    kpisPreview: [
                        { parameter: "Sales Revenue", weight: 30 },
                        { parameter: "New Clients", weight: 25 },
                        { parameter: "Customer Satisfaction", weight: 20 },
                    ],
                    status: "pending",
                },
                {
                    id: 2,
                    memberName: "Siti Rahma",
                    position: "Marketing Specialist",
                    department: "Marketing",
                    submittedAt: new Date(Date.now() - 5 * 60 * 60 * 1000),
                    kpiCount: 4,
                    totalWeight: 85,
                    quarter: 1,
                    year: 2024,
                    kpisPreview: [
                        { parameter: "Lead Generation", weight: 30 },
                        { parameter: "Social Media Engagement", weight: 25 },
                        { parameter: "Campaign ROI", weight: 20 },
                    ],
                    status: "pending",
                },
            ],
            approvedSubmissions: [
                {
                    id: 3,
                    memberName: "Rizky Pratama",
                    position: "Digital Marketer",
                    department: "Marketing",
                    submittedAt: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000),
                    approvedAt: new Date(Date.now() - 1 * 24 * 60 * 60 * 1000),
                    approvedBy: "Manager Marketing",
                    kpiCount: 6,
                    totalWeight: 100,
                    performanceScore: 4.2,
                    status: "approved",
                },
            ],
            rejectedSubmissions: [
                {
                    id: 4,
                    memberName: "Budi Santoso",
                    position: "Sales Manager",
                    department: "Sales",
                    submittedAt: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000),
                    rejectedAt: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000),
                    rejectedBy: "Director Sales",
                    rejectionReason:
                        "Beberapa target tidak realistis dan perlu penyesuaian",
                    status: "rejected",
                },
            ],
            departmentStats: [
                {
                    name: "Sales",
                    submitted: 12,
                    approved: 10,
                    rejected: 2,
                    approvalRate: 83,
                },
                {
                    name: "Marketing",
                    submitted: 8,
                    approved: 7,
                    rejected: 1,
                    approvalRate: 88,
                },
                {
                    name: "Operations",
                    submitted: 6,
                    approved: 5,
                    rejected: 1,
                    approvalRate: 83,
                },
                {
                    name: "IT",
                    submitted: 4,
                    approved: 4,
                    rejected: 0,
                    approvalRate: 100,
                },
            ],
        };
    },
    computed: {
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },
        pendingCount() {
            return this.pendingSubmissions.length;
        },
        approvedCount() {
            return this.approvedSubmissions.length;
        },
        rejectedCount() {
            return this.rejectedSubmissions.length;
        },
        approvalRate() {
            const total = this.approvedCount + this.rejectedCount;
            return total > 0
                ? Math.round((this.approvedCount / total) * 100)
                : 0;
        },
        pendingTrend() {
            return 12; // Example data
        },
        approvedTrend() {
            return 8; // Example data
        },
        rejectedTrend() {
            return 5; // Example data
        },
        approvalRateTrend() {
            return 3; // Example data
        },
        averageApprovalTime() {
            return "2.5"; // Example data
        },
        performanceAverage() {
            return "4.1"; // Example data
        },
        filteredApproved() {
            // Filter logic based on approvedFilter
            return this.approvedSubmissions;
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
        formatDate(date) {
            return new Date(date).toLocaleDateString("id-ID", {
                day: "numeric",
                month: "short",
                year: "numeric",
            });
        },
        formatDateTime(date) {
            return new Date(date).toLocaleString("id-ID", {
                day: "numeric",
                month: "short",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
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
                pending: "Menunggu Approval",
                approved: "Disetujui",
                rejected: "Ditolak",
            };
            return statusMap[status] || status;
        },
        getTabCount(tabId) {
            switch (tabId) {
                case "pending":
                    return this.pendingCount;
                case "approved":
                    return this.approvedCount;
                case "rejected":
                    return this.rejectedCount;
                case "analytics":
                    return "";
                default:
                    return 0;
            }
        },
        getPerformanceClass(score) {
            if (score >= 4) return "excellent";
            if (score >= 3) return "good";
            return "poor";
        },

        // Tab Management
        setActiveTab(tabId) {
            this.activeTab = tabId;
            this.selectedSubmissions = []; // Clear selection when switching tabs
        },

        // Submission Selection
        isSubmissionSelected(submissionId) {
            return this.selectedSubmissions.includes(submissionId);
        },
        toggleSubmissionSelection(submissionId) {
            const index = this.selectedSubmissions.indexOf(submissionId);
            if (index > -1) {
                this.selectedSubmissions.splice(index, 1);
            } else {
                this.selectedSubmissions.push(submissionId);
            }
        },

        // Submission Actions
        viewSubmission(submission) {
            this.selectedSubmission = {
                ...submission,
                kpis: this.getSubmissionKPIs(submission.id),
            };
        },
        approveSubmission(submission) {
            ElMessageBox.confirm(
                `Setujui KPI submission dari ${submission.memberName}?`,
                "Konfirmasi Approval",
                {
                    confirmButtonText: "Ya, Setujui",
                    cancelButtonText: "Batal",
                    type: "warning",
                }
            ).then(() => {
                // Remove from pending, add to approved
                const index = this.pendingSubmissions.findIndex(
                    (s) => s.id === submission.id
                );
                if (index > -1) {
                    const approvedSubmission = {
                        ...this.pendingSubmissions[index],
                        status: "approved",
                        approvedAt: new Date(),
                        approvedBy: "Anda",
                        performanceScore: 4.5, // Example score
                    };
                    this.pendingSubmissions.splice(index, 1);
                    this.approvedSubmissions.unshift(approvedSubmission);
                }

                ElMessage.success(
                    `KPI untuk ${submission.memberName} berhasil disetujui`
                );
                this.closeModal();
            });
        },
        rejectSubmission(submission) {
            ElMessageBox.prompt(
                "Masukkan alasan penolakan:",
                "Konfirmasi Penolakan",
                {
                    confirmButtonText: "Tolak",
                    cancelButtonText: "Batal",
                    inputPlaceholder: "Alasan penolakan...",
                    inputValidator: (value) => {
                        if (!value) {
                            return "Alasan penolakan harus diisi";
                        }
                    },
                }
            ).then(({ value }) => {
                // Remove from pending, add to rejected
                const index = this.pendingSubmissions.findIndex(
                    (s) => s.id === submission.id
                );
                if (index > -1) {
                    const rejectedSubmission = {
                        ...this.pendingSubmissions[index],
                        status: "rejected",
                        rejectedAt: new Date(),
                        rejectedBy: "Anda",
                        rejectionReason: value,
                    };
                    this.pendingSubmissions.splice(index, 1);
                    this.rejectedSubmissions.unshift(rejectedSubmission);
                }

                ElMessage.success(`KPI untuk ${submission.memberName} ditolak`);
                this.closeModal();
            });
        },

        // Bulk Actions
        bulkApprove() {
            if (this.selectedSubmissions.length === 0) return;

            ElMessageBox.confirm(
                `Setujui ${this.selectedSubmissions.length} submission sekaligus?`,
                "Bulk Approval",
                {
                    confirmButtonText: "Ya, Setujui Semua",
                    cancelButtonText: "Batal",
                    type: "warning",
                }
            ).then(() => {
                this.selectedSubmissions.forEach((submissionId) => {
                    const submission = this.pendingSubmissions.find(
                        (s) => s.id === submissionId
                    );
                    if (submission) {
                        this.approveSubmission(submission);
                    }
                });
                this.selectedSubmissions = [];
            });
        },
        bulkReject() {
            if (this.selectedSubmissions.length === 0) return;

            ElMessageBox.prompt(
                "Masukkan alasan penolakan untuk semua submission:",
                "Bulk Rejection",
                {
                    confirmButtonText: "Tolak Semua",
                    cancelButtonText: "Batal",
                    inputPlaceholder: "Alasan penolakan...",
                    inputValidator: (value) => {
                        if (!value) {
                            return "Alasan penolakan harus diisi";
                        }
                    },
                }
            ).then(({ value }) => {
                this.selectedSubmissions.forEach((submissionId) => {
                    const submission = this.pendingSubmissions.find(
                        (s) => s.id === submissionId
                    );
                    if (submission) {
                        const index = this.pendingSubmissions.findIndex(
                            (s) => s.id === submissionId
                        );
                        if (index > -1) {
                            const rejectedSubmission = {
                                ...this.pendingSubmissions[index],
                                status: "rejected",
                                rejectedAt: new Date(),
                                rejectedBy: "Anda",
                                rejectionReason: value,
                            };
                            this.pendingSubmissions.splice(index, 1);
                            this.rejectedSubmissions.unshift(
                                rejectedSubmission
                            );
                        }
                    }
                });

                ElMessage.success(
                    `${this.selectedSubmissions.length} submission ditolak`
                );
                this.selectedSubmissions = [];
            });
        },

        // Other Actions
        viewApprovedSubmission(submission) {
            this.selectedSubmission = submission;
        },
        viewRejectedSubmission(submission) {
            this.selectedSubmission = submission;
        },
        editRejectedSubmission(submission) {
            this.$router.push(
                `/kpi-team/multi-form?member=${submission.memberId}&edit=true`
            );
        },
        downloadApproval(submission) {
            ElMessage.info(
                `Mendownload approval untuk ${submission.memberName}`
            );
        },
        exportReport() {
            ElMessage.info("Mengekspor report...");
        },
        refreshData() {
            ElMessage.success("Data diperbarui");
        },
        closeModal() {
            this.selectedSubmission = null;
        },

        // Utility Methods
        getSubmissionKPIs(submissionId) {
            // Mock KPI data
            return [
                {
                    id: 1,
                    parameter: "Sales Revenue",
                    category: "Financial",
                    weight: 30,
                    target: "Increase by 15%",
                    description:
                        "Meningkatkan revenue penjualan sebesar 15% dari kuartal sebelumnya",
                },
                {
                    id: 2,
                    parameter: "New Clients",
                    category: "Customer",
                    weight: 25,
                    target: "Acquire 20 new clients",
                    description: "Menambah 20 klien baru dalam kuartal ini",
                },
            ];
        },
    },
};
</script>

<style scoped>
/* Approval Monitoring Specific Styles */
.approval-stats-overview {
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
    cursor: pointer;
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

.stat-icon.pending {
    background: linear-gradient(135deg, var(--hcis-amber-500), var(--hcis-amber-600));
}
.stat-icon.approved {
    background: linear-gradient(135deg, var(--hcis-emerald-500), var(--hcis-emerald-600));
}
.stat-icon.rejected {
    background: linear-gradient(135deg, var(--hcis-red-500), var(--hcis-red-600));
}
.stat-icon.analytics {
    background: linear-gradient(135deg, #8b5cf6, var(--hcis-violet-600));
}

.stat-content h3 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--dark);
}

.stat-content p {
    margin: 0.25rem 0 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.stat-trend {
    font-size: 0.75rem;
    color: var(--warning);
    font-weight: 600;
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.stat-trend.positive {
    color: var(--success);
}

/* Action Bar */
.action-bar {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
}

.section-title {
    margin: 0;
    color: var(--dark);
    font-size: 1.25rem;
    font-weight: 600;
}

.section-subtitle {
    margin: 0.25rem 0 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}

/* Approval Tabs */
.approval-tabs-section {
    background: var(--surface);
    border-radius: 12px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

.tabs-header {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    background: var(--surface-soft);
    border-bottom: 1px solid var(--border);
}

.approval-tab {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1.25rem 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border-bottom: 3px solid transparent;
    font-weight: 500;
    color: var(--text-muted);
}

.approval-tab:hover {
    background: var(--surface);
    color: var(--primary);
}

.approval-tab.active {
    background: var(--surface);
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.tab-badge {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 24px;
    text-align: center;
}

.tabs-content {
    padding: 0;
}

.tab-pane {
    padding: 2rem;
}

.tab-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.tab-header h5 {
    margin: 0;
    color: var(--dark);
    font-size: 1.1rem;
}

.tab-actions {
    display: flex;
    gap: 0.75rem;
}

.btn-tab-action {
    padding: 0.75rem 1.25rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 500;
}

.btn-tab-action:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.btn-tab-action:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Approval List */
.approval-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.approval-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--surface-soft);
    border-radius: 8px;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.approval-item:hover {
    background: var(--surface);
    box-shadow: var(--shadow);
}

.approval-item.selected {
    border-color: var(--primary);
    background: var(--surface);
}

.selection-checkbox {
    margin-top: 0.5rem;
}

.selection-checkbox input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.submission-content {
    flex: 1;
    cursor: pointer;
}

.submission-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    gap: 1.5rem;
}

.submitter-info {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    flex: 1;
}

.avatar-md {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.submitter-details h5 {
    margin: 0 0 0.25rem 0;
    color: var(--dark);
    font-size: 1.1rem;
}

.submitter-details p {
    margin: 0 0 0.75rem 0;
    color: var(--text);
    font-size: 0.9rem;
}

.submission-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.submission-stats {
    display: flex;
    gap: 1.5rem;
    flex-shrink: 0;
}

.stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--text);
}

.stat i {
    color: var(--primary);
}

.submission-preview {
    background: var(--surface);
    padding: 1rem;
    border-radius: 6px;
}

.kpi-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.kpi-preview-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: var(--surface-soft);
    border-radius: 6px;
    font-size: 0.85rem;
}

.kpi-name {
    color: var(--text);
}

.kpi-weight {
    color: var(--primary);
    font-weight: 600;
}

.more-kpis {
    color: var(--text-muted);
    font-size: 0.8rem;
    font-style: italic;
}

.submission-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.btn-action {
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
}

.btn-view {
    background: var(--surface-soft);
    color: var(--text);
}

.btn-view:hover {
    background: var(--primary-light);
    color: var(--primary);
}

.btn-approve {
    background: var(--success);
    color: white;
}

.btn-approve:hover {
    background: var(--hcis-emerald-600);
}

.btn-reject {
    background: var(--danger);
    color: white;
}

.btn-reject:hover {
    background: var(--hcis-red-600);
}

/* Approved Grid */
.approved-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.approved-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--success);
}

.card-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.member-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.member-info {
    flex: 1;
}

.member-info h5 {
    margin: 0 0 0.25rem 0;
    color: var(--dark);
}

.member-info p {
    margin: 0 0 0.5rem 0;
    color: var(--text);
    font-size: 0.9rem;
}

.approval-details {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.approval-details span {
    display: block;
    margin-bottom: 0.125rem;
}

.approval-badge {
    padding: 0.5rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.approval-badge.approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.approval-badge.rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.kpi-summary {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--surface-soft);
    border-radius: 8px;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-muted);
}

.summary-item i {
    color: var(--primary);
}

.performance-indicator {
    margin-bottom: 1.5rem;
}

.performance-label {
    font-size: 0.9rem;
    color: var(--text);
    margin-bottom: 0.5rem;
}

.performance-bar {
    height: 6px;
    background: var(--border);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.performance-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.performance-fill.excellent {
    background: var(--success);
}
.performance-fill.good {
    background: var(--warning);
}
.performance-fill.poor {
    background: var(--danger);
}

.performance-value {
    text-align: center;
    font-weight: 600;
    color: var(--dark);
    font-size: 0.9rem;
}

.card-footer {
    display: flex;
    gap: 0.5rem;
}

.btn-card {
    flex: 1;
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
}

.btn-card.btn-view {
    background: var(--surface-soft);
    color: var(--text);
}

.btn-card.btn-view:hover {
    background: var(--primary-light);
    color: var(--primary);
}

.btn-card.btn-download {
    background: var(--primary);
    color: white;
}

.btn-card.btn-download:hover {
    background: var(--primary-dark);
}

.btn-card.btn-edit {
    background: var(--warning);
    color: white;
}

.btn-card.btn-edit:hover {
    background: var(--hcis-amber-600);
}

/* Rejected Cards */
.rejected-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--danger);
    margin-bottom: 1rem;
}

.rejection-reason {
    margin: 1rem 0;
    padding: 1rem;
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-radius: 6px;
}

.rejection-reason h6 {
    margin: 0 0 0.5rem 0;
    color: var(--danger);
}

.rejection-reason p {
    margin: 0;
    color: var(--hcis-red-900);
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Analytics Dashboard */
.analytics-dashboard {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.metric-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.metric-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.metric-content h3 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--dark);
}

.metric-content p {
    margin: 0.25rem 0 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.metric-content small {
    color: var(--text-muted);
    font-size: 0.8rem;
}

.charts-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.chart-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
}

.chart-card h5 {
    margin: 0 0 1rem 0;
    color: var(--dark);
}

.chart-placeholder {
    height: 200px;
    background: var(--surface-soft);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}

.chart-placeholder i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.department-stats {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
}

.department-stats h5 {
    margin: 0 0 1.5rem 0;
    color: var(--dark);
}

.stats-table {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.table-header,
.table-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
    gap: 1rem;
    padding: 1rem;
    align-items: center;
}

.table-header {
    background: var(--surface-soft);
    border-radius: 6px;
    font-weight: 600;
    color: var(--dark);
    font-size: 0.9rem;
}

.table-row {
    background: var(--surface-soft);
    border-radius: 6px;
    transition: all 0.3s ease;
}

.table-row:hover {
    background: var(--surface);
}

.dept-name {
    font-weight: 500;
    color: var(--dark);
}

.dept-approved {
    color: var(--success);
    font-weight: 600;
}

.dept-rejected {
    color: var(--danger);
    font-weight: 600;
}

.dept-rate {
    color: var(--primary);
    font-weight: 600;
}

/* Empty States */
.empty-state-tab {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-muted);
}

.empty-state-tab i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state-tab h5 {
    margin-bottom: 0.5rem;
    color: var(--text-muted);
}

.empty-state-tab p {
    margin: 0;
}

/* Submission Modal */
.submission-modal {
    width: min(800px, 90vw);
    max-height: min(700px, 90vh);
}

.submission-detail {
    max-height: 60vh;
    overflow-y: auto;
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.member-info {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    flex: 1;
}

.avatar-lg {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.member-details h4 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
}

.member-details p {
    margin: 0 0 1rem 0;
    color: var(--text);
}

.submission-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.9rem;
    color: var(--text-muted);
}

.submission-status {
    flex-shrink: 0;
}

.kpi-detail-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kpi-detail-item {
    background: var(--surface-soft);
    border-radius: 8px;
    padding: 1.5rem;
    border-left: 4px solid var(--primary);
}

.kpi-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.kpi-number {
    background: var(--primary);
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    flex-shrink: 0;
}

.kpi-info h5 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
}

.kpi-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
}

.kpi-meta .category {
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-weight: 500;
}

.kpi-meta .weight {
    color: var(--primary);
    font-weight: 600;
}

.kpi-content p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
    line-height: 1.4;
}

.kpi-content strong {
    color: var(--dark);
}

/* Responsive Design */
@media (max-width: 768px) {
    .approval-stats-overview {
        grid-template-columns: repeat(2, 1fr);
    }

    .tabs-header {
        grid-template-columns: 1fr;
    }

    .submission-header {
        flex-direction: column;
        gap: 1rem;
    }

    .submission-stats {
        justify-content: space-between;
        width: 100%;
    }

    .submission-actions {
        flex-direction: column;
        width: 100%;
    }

    .btn-action {
        justify-content: center;
    }

    .approved-grid {
        grid-template-columns: 1fr;
    }

    .metrics-grid {
        grid-template-columns: 1fr;
    }

    .charts-section {
        grid-template-columns: 1fr;
    }

    .table-header,
    .table-row {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .detail-header {
        flex-direction: column;
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .approval-stats-overview {
        grid-template-columns: 1fr;
    }

    .action-bar .row {
        flex-direction: column;
        gap: 1rem;
    }

    .action-buttons {
        justify-content: flex-start;
    }

    .tab-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .tab-actions {
        width: 100%;
        justify-content: space-between;
    }

    .approval-item {
        flex-direction: column;
    }

    .selection-checkbox {
        align-self: flex-start;
    }
}
</style>

<style scoped>
/* CSS Variables */
*,
*::before,
*::after {
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-300);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --warning: var(--hcis-amber-500);
    --danger: var(--hcis-red-500);
    --info: var(--hcis-cyan-500);
    --light: var(--hcis-slate-50);
    --dark: var(--hcis-slate-800);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
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
    background: linear-gradient(135deg, var(--hcis-amber-500) 0%, var(--hcis-amber-600) 100%);
}

.status-approved .status-background {
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
}

.status-rejected .status-background {
    background: linear-gradient(135deg, var(--hcis-red-500) 0%, var(--hcis-red-600) 100%);
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
    background: linear-gradient(135deg, var(--warning), var(--hcis-amber-400));
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
    background: linear-gradient(135deg, var(--success), var(--hcis-emerald-400));
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
    background: linear-gradient(135deg, var(--surface) 0%, var(--hcis-slate-50) 100%);
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
    background: linear-gradient(135deg, var(--surface-soft) 0%, var(--hcis-slate-100) 100%);
    border: 2px dashed var(--border);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100px;
}
.compact-card.add-new:hover {
    border-color: var(--primary);
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-200) 100%);
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
