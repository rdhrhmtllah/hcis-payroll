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
                                            Team Management
                                        </h2>
                                        <p class="subtitle">
                                            Kelola anggota tim dan assign KPI
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

        <!-- Action Bar -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="action-bar">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4 class="section-title mb-0">Anggota Tim</h4>
                            <p class="section-subtitle mb-0">
                                {{ teamMembers.length }} anggota terdaftar
                            </p>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="action-buttons">
                                <button
                                    class="btn-modern btn-secondary"
                                    @click="openImportModal"
                                >
                                    <i class="bi bi-upload"></i>
                                    Import
                                </button>
                                <button
                                    class="btn-modern btn-primary"
                                    @click="openBulkAssignment"
                                >
                                    <i class="bi bi-send-check"></i>
                                    Assign KPI Massal
                                </button>
                                <button
                                    class="btn-modern btn-success"
                                    @click="openAddMemberModal"
                                >
                                    <i class="bi bi-person-plus"></i>
                                    Tambah Anggota
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="filter-section">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input
                                    type="text"
                                    v-model="filters.search"
                                    placeholder="Cari anggota tim..."
                                    class="search-input"
                                />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select
                                v-model="filters.department"
                                class="filter-select"
                            >
                                <option value="">Semua Departemen</option>
                                <option
                                    v-for="dept in departments"
                                    :key="dept"
                                    :value="dept"
                                >
                                    {{ dept }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select
                                v-model="filters.status"
                                class="filter-select"
                            >
                                <option value="">Semua Status</option>
                                <option value="not-started">Belum Mulai</option>
                                <option value="in-progress">
                                    Dalam Proses
                                </option>
                                <option value="completed">Selesai</option>
                                <option value="approved">Disetujui</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button
                                class="btn-clear-filters w-100"
                                @click="clearFilters"
                            >
                                <i class="bi bi-arrow-clockwise"></i>
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Grid -->
        <div class="row">
            <div class="col-12">
                <div class="team-grid">
                    <!-- Team Member Cards -->
                    <div
                        v-for="member in filteredMembers"
                        :key="member.id"
                        class="team-member-card"
                        :class="getMemberCardClass(member)"
                    >
                        <div class="member-header">
                            <div class="member-avatar">
                                {{ getInitials(member.name) }}
                            </div>
                            <div class="member-info">
                                <h5 class="member-name">{{ member.name }}</h5>
                                <p class="member-position">
                                    {{ member.position }}
                                </p>
                                <p class="member-department">
                                    {{ member.department }}
                                </p>
                                <div class="member-status">
                                    <span
                                        :class="`status-badge status-${member.kpiStatus}`"
                                    >
                                        {{ getStatusText(member.kpiStatus) }}
                                    </span>
                                </div>
                            </div>
                            <div class="member-actions">
                                <button
                                    class="btn-action btn-edit"
                                    @click="editMemberKPI(member)"
                                    :title="getActionButtonTitle(member)"
                                >
                                    <i
                                        class="bi"
                                        :class="getActionButtonIcon(member)"
                                    ></i>
                                </button>
                                <button
                                    class="btn-action btn-view"
                                    @click="viewMemberKPI(member)"
                                    title="Lihat Detail"
                                >
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button
                                    class="btn-action btn-more"
                                    @click="openMemberMenu(member)"
                                    title="Lainnya"
                                >
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                            </div>
                        </div>

                        <div class="member-progress">
                            <div class="progress-info">
                                <span class="progress-label">Progress KPI</span>
                                <span class="progress-value"
                                    >{{ member.kpiProgress }}%</span
                                >
                            </div>
                            <div class="progress-bar">
                                <div
                                    class="progress-fill"
                                    :style="{ width: member.kpiProgress + '%' }"
                                    :class="
                                        getProgressBarClass(member.kpiProgress)
                                    "
                                ></div>
                            </div>
                        </div>

                        <div class="member-kpi-summary">
                            <div class="kpi-count">
                                <i class="bi bi-layers"></i>
                                {{ member.kpiCount }} KPI
                            </div>
                            <div class="total-weight">
                                <i class="bi bi-speedometer2"></i>
                                {{ member.totalWeight }}% Bobot
                            </div>
                            <div class="last-update">
                                <i class="bi bi-clock"></i>
                                {{ formatRelativeTime(member.lastUpdate) }}
                            </div>
                        </div>

                        <div class="member-footer">
                            <button
                                class="btn-member-action primary"
                                @click="editMemberKPI(member)"
                                :disabled="member.kpiStatus === 'approved'"
                            >
                                {{ getPrimaryActionText(member) }}
                            </button>
                            <button
                                class="btn-member-action secondary"
                                @click="viewMemberDetails(member)"
                            >
                                Detail
                            </button>
                        </div>
                    </div>

                    <!-- Add New Member Card -->
                    <div
                        class="team-member-card add-new"
                        @click="openAddMemberModal"
                    >
                        <div class="add-new-content">
                            <i class="bi bi-person-plus"></i>
                            <h5>Tambah Anggota Tim</h5>
                            <p>Tambahkan anggota baru ke dalam tim</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="filteredMembers.length === 0" class="row mt-5">
            <div class="col-12">
                <div class="empty-state text-center">
                    <i class="bi bi-people empty-icon"></i>
                    <h4>Tidak ada anggota tim</h4>
                    <p>Tambah anggota tim untuk mulai mengelola KPI</p>
                    <button
                        class="btn-modern btn-primary"
                        @click="openAddMemberModal"
                    >
                        <i class="bi bi-person-plus"></i>
                        Tambah Anggota Pertama
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <div class="bulk-actions-bar" v-if="selectedMembers.length > 0">
            <div class="bulk-actions-content">
                <div class="selected-count">
                    <i class="bi bi-check-circle-fill text-primary"></i>
                    <span>{{ selectedMembers.length }} anggota terpilih</span>
                </div>
                <div class="bulk-buttons">
                    <button class="btn-bulk btn-assign" @click="bulkAssignKPI">
                        <i class="bi bi-send-check"></i>
                        Assign KPI
                    </button>
                    <button class="btn-bulk btn-export" @click="bulkExport">
                        <i class="bi bi-download"></i>
                        Export Data
                    </button>
                    <button class="btn-bulk btn-clear" @click="clearSelection">
                        <i class="bi bi-x-circle"></i>
                        Batalkan Pilihan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Member Modal -->
    <div
        v-if="showAddMemberModal"
        class="modal-overlay"
        @click="closeAddMemberModal"
    >
        <div class="modal-content" @click.stop>
            <div class="modal-header">
                <h3 class="modal-title">Tambah Anggota Tim</h3>
                <button class="modal-close" @click="closeAddMemberModal">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <!-- Add member form content -->
            </div>
        </div>
    </div>
</template>

<script>
import { ElMessage } from "element-plus";

export default {
    name: "TeamManagement",
    data() {
        return {
            currentDate: new Date(),
            teamMembers: [
                {
                    id: 1,
                    name: "Ahmad Wijaya",
                    position: "Sales Executive",
                    department: "Sales",
                    kpiStatus: "completed",
                    kpiProgress: 100,
                    kpiCount: 5,
                    totalWeight: 100,
                    lastUpdate: new Date(Date.now() - 2 * 60 * 60 * 1000),
                },
                {
                    id: 2,
                    name: "Siti Rahma",
                    position: "Marketing Specialist",
                    department: "Marketing",
                    kpiStatus: "in-progress",
                    kpiProgress: 75,
                    kpiCount: 4,
                    totalWeight: 85,
                    lastUpdate: new Date(Date.now() - 4 * 60 * 60 * 1000),
                },
                {
                    id: 3,
                    name: "Budi Santoso",
                    position: "Sales Manager",
                    department: "Sales",
                    kpiStatus: "not-started",
                    kpiProgress: 0,
                    kpiCount: 0,
                    totalWeight: 0,
                    lastUpdate: new Date(Date.now() - 24 * 60 * 60 * 1000),
                },
                {
                    id: 4,
                    name: "Dewi Lestari",
                    position: "Account Executive",
                    department: "Sales",
                    kpiStatus: "in-progress",
                    kpiProgress: 50,
                    kpiCount: 3,
                    totalWeight: 60,
                    lastUpdate: new Date(Date.now() - 6 * 60 * 60 * 1000),
                },
                {
                    id: 5,
                    name: "Rizky Pratama",
                    position: "Digital Marketer",
                    department: "Marketing",
                    kpiStatus: "approved",
                    kpiProgress: 100,
                    kpiCount: 6,
                    totalWeight: 100,
                    lastUpdate: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000),
                },
            ],
            selectedMembers: [],
            filters: {
                search: "",
                department: "",
                status: "",
            },
            departments: ["Sales", "Marketing", "Operations", "IT", "HR"],
            showAddMemberModal: false,
        };
    },
    computed: {
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },
        filteredMembers() {
            return this.teamMembers.filter((member) => {
                const matchesSearch =
                    !this.filters.search ||
                    member.name
                        .toLowerCase()
                        .includes(this.filters.search.toLowerCase()) ||
                    member.position
                        .toLowerCase()
                        .includes(this.filters.search.toLowerCase());

                const matchesDept =
                    !this.filters.department ||
                    member.department === this.filters.department;
                const matchesStatus =
                    !this.filters.status ||
                    member.kpiStatus === this.filters.status;

                return matchesSearch && matchesDept && matchesStatus;
            });
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
        getInitials(name) {
            if (!name) return "??";
            return name
                .split(" ")
                .map((n) => n[0])
                .join("")
                .toUpperCase();
        },
        getMemberCardClass(member) {
            return `status-${member.kpiStatus}`;
        },
        getStatusText(status) {
            const statusMap = {
                "not-started": "Belum Mulai",
                "in-progress": "Dalam Proses",
                completed: "Selesai",
                approved: "Disetujui",
            };
            return statusMap[status] || status;
        },
        getActionButtonIcon(member) {
            switch (member.kpiStatus) {
                case "not-started":
                    return "bi-pencil-square";
                case "in-progress":
                    return "bi-pencil";
                case "completed":
                    return "bi-eye";
                case "approved":
                    return "bi-check-circle";
                default:
                    return "bi-pencil-square";
            }
        },
        getActionButtonTitle(member) {
            switch (member.kpiStatus) {
                case "not-started":
                    return "Isi KPI";
                case "in-progress":
                    return "Lanjutkan Edit";
                case "completed":
                    return "Review KPI";
                case "approved":
                    return "Lihat KPI Disetujui";
                default:
                    return "Isi KPI";
            }
        },
        getPrimaryActionText(member) {
            switch (member.kpiStatus) {
                case "not-started":
                    return "Mulai Isi KPI";
                case "in-progress":
                    return "Lanjutkan Edit";
                case "completed":
                    return "Lihat KPI";
                case "approved":
                    return "KPI Disetujui";
                default:
                    return "Mulai Isi KPI";
            }
        },
        getProgressBarClass(progress) {
            if (progress < 30) return "low";
            if (progress < 70) return "medium";
            return "high";
        },
        editMemberKPI(member) {
            if (member.kpiStatus === "approved") {
                ElMessage.info("KPI sudah disetujui dan tidak dapat diedit");
                return;
            }
            this.$router.push(`/kpi-team/multi-form?member=${member.id}`);
        },
        viewMemberKPI(member) {
            // Navigate to view mode
            this.$router.push(
                `/kpi-team/multi-form?member=${member.id}&view=true`
            );
        },
        viewMemberDetails(member) {
            ElMessage.info(`Membuka detail untuk ${member.name}`);
        },
        openMemberMenu(member) {
            ElMessage.info(`Menu untuk ${member.name}`);
        },
        openAddMemberModal() {
            this.showAddMemberModal = true;
        },
        closeAddMemberModal() {
            this.showAddMemberModal = false;
        },
        openBulkAssignment() {
            this.$router.push("/kpi-team/multi-form");
        },
        openImportModal() {
            ElMessage.info("Fitur import akan segera tersedia");
        },
        clearFilters() {
            this.filters = {
                search: "",
                department: "",
                status: "",
            };
        },
        bulkAssignKPI() {
            ElMessage.success(
                `Assign KPI ke ${this.selectedMembers.length} anggota`
            );
        },
        bulkExport() {
            ElMessage.info("Export data dalam proses");
        },
        clearSelection() {
            this.selectedMembers = [];
        },
    },
};
</script>

<style scoped>
/* Team Management Specific Styles */
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

/* Filter Section */
.filter-section {
    background: var(--surface);
    padding: 1rem;
    border-radius: 12px;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
}

.search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box i {
    position: absolute;
    left: 1rem;
    color: var(--text-muted);
    z-index: 2;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.filter-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.btn-clear-filters {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    color: var(--text-muted);
    padding: 0.75rem 1rem;
    border-radius: 10px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
}

.btn-clear-filters:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Team Grid */
.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.team-member-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    cursor: pointer;
    border-left: 4px solid var(--border);
}

.team-member-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.team-member-card.status-not-started {
    border-left-color: var(--secondary);
}

.team-member-card.status-in-progress {
    border-left-color: var(--warning);
}

.team-member-card.status-completed {
    border-left-color: var(--success);
}

.team-member-card.status-approved {
    border-left-color: var(--primary);
}

.team-member-card.add-new {
    border: 2px dashed var(--border);
    background: var(--surface-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 300px;
    cursor: pointer;
}

.add-new-content {
    text-align: center;
    color: var(--text-muted);
}

.add-new-content i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: var(--primary);
    opacity: 0.7;
}

.add-new-content h5 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
}

.add-new-content p {
    margin: 0;
    font-size: 0.9rem;
}

/* Member Header */
.member-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.member-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.member-info {
    flex: 1;
}

.member-name {
    margin: 0 0 0.25rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
}

.member-position {
    margin: 0 0 0.25rem 0;
    font-size: 0.9rem;
    color: var(--text);
    font-weight: 500;
}

.member-department {
    margin: 0 0 0.75rem 0;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.member-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.status-not-started {
    background: rgba(100, 116, 139, 0.1);
    color: var(--secondary);
}

.status-badge.status-in-progress {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.status-badge.status-completed {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-badge.status-approved {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

.member-actions {
    display: flex;
    gap: 0.25rem;
    flex-shrink: 0;
}

.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.btn-edit {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

.btn-edit:hover {
    background: var(--primary);
    color: white;
}

.btn-view {
    background: rgba(100, 116, 139, 0.1);
    color: var(--secondary);
}

.btn-view:hover {
    background: var(--secondary);
    color: white;
}

.btn-more {
    background: rgba(100, 116, 139, 0.1);
    color: var(--secondary);
}

.btn-more:hover {
    background: var(--secondary);
    color: white;
}

/* Member Progress */
.member-progress {
    margin-bottom: 1rem;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
}

.progress-label {
    color: var(--text);
    font-weight: 500;
}

.progress-value {
    color: var(--dark);
    font-weight: 600;
}

.progress-bar {
    height: 6px;
    background: var(--border);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 3px;
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

/* KPI Summary */
.member-kpi-summary {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: var(--surface-soft);
    border-radius: 8px;
    font-size: 0.8rem;
}

.member-kpi-summary > div {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
}

.member-kpi-summary i {
    color: var(--primary);
}

/* Member Footer */
.member-footer {
    display: flex;
    gap: 0.5rem;
}

.btn-member-action {
    flex: 1;
    padding: 0.6rem 1rem;
    border: none;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-member-action.primary {
    background: var(--primary);
    color: white;
}

.btn-member-action.primary:hover:not(:disabled) {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-member-action.primary:disabled {
    background: var(--border);
    color: var(--text-muted);
    cursor: not-allowed;
    transform: none;
}

.btn-member-action.secondary {
    background: var(--surface-soft);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-member-action.secondary:hover {
    background: var(--surface);
    border-color: var(--primary);
}

/* Bulk Actions */
.bulk-actions-bar {
    position: fixed;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    background: var(--surface);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    box-shadow: var(--shadow-lg);
    border-left: 4px solid var(--primary);
    z-index: 1000;
    min-width: 400px;
}

.bulk-actions-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.selected-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--primary);
}

.bulk-buttons {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.btn-bulk {
    padding: 0.6rem 1.2rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.btn-bulk.btn-assign {
    background: var(--success);
    color: white;
}

.btn-bulk.btn-export {
    background: var(--primary);
    color: white;
}

.btn-bulk.btn-clear {
    background: var(--surface-soft);
    color: var(--text-muted);
    border: 1px solid var(--border);
}

.btn-bulk:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Empty State */
.empty-state {
    padding: 4rem 2rem;
    background: var(--surface);
    border-radius: 12px;
    box-shadow: var(--shadow);
}

.empty-icon {
    font-size: 4rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h4 {
    margin-bottom: 1rem;
    color: var(--text-muted);
}

.empty-state p {
    color: var(--text-muted);
    margin-bottom: 2rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .team-grid {
        grid-template-columns: 1fr;
    }

    .action-bar .row {
        flex-direction: column;
        gap: 1rem;
    }

    .action-buttons {
        justify-content: flex-start;
    }

    .member-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .member-actions {
        justify-content: center;
    }

    .member-kpi-summary {
        flex-direction: column;
        gap: 0.5rem;
        align-items: center;
    }

    .bulk-actions-bar {
        min-width: 90%;
        left: 5%;
        transform: none;
    }

    .bulk-actions-content {
        flex-direction: column;
        gap: 0.75rem;
    }

    .bulk-buttons {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .filter-section .row {
        flex-direction: column;
    }

    .member-footer {
        flex-direction: column;
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
