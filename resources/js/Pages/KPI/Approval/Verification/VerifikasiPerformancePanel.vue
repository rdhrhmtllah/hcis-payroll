<template>
    <div class="container-fluid px-0" :class="{ 'embedded-panel': embedded }">
        <!-- Header -->
        <div
            v-if="!embedded"
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
                                            class="bi bi-graph-up d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2
                                            class="title fw-bold text-white text-start"
                                        >
                                            Verifikasi Performance
                                        </h2>
                                        <p class="subtitle">
                                            Manajemen persetujuan update
                                            performance KPI
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
        <quick-stat v-if="!embedded" :stats="quickStatField" />

        <!-- Approval Tabs Section -->
        <ApprovalTabs
            :tabs="approvalTabs"
            :initialTab="activeTab"
            @tab-change="handleTabChange"
        >
            <!-- Tab 1: Approval Queue -->
            <template #queue>
                <div class="queue-content">
                    <!-- Bulk Actions -->
                    <div
                        class="bulk-actions-bar"
                        v-if="selectedApprovals.length > 0"
                    >
                        <div class="bulk-actions-content">
                            <div class="selected-count">
                                <i
                                    class="bi bi-check-circle-fill text-primary d-flex justify-content-center align-items-center"
                                ></i>
                                <span
                                    >{{
                                        selectedApprovals.length
                                    }}
                                    selected</span
                                >
                            </div>
                            <div class="bulk-buttons">
                                <button
                                    class="btn-bulk btn-approve"
                                    @click="bulkApprove"
                                >
                                    <i
                                        class="d-flex justify-content-center align-items-center bi bi-check-lg"
                                    ></i>
                                    Approve Selected
                                </button>
                                <button
                                    class="btn-bulk btn-reject"
                                    @click="openBulkRejectModal"
                                >
                                    <i
                                        class="d-flex justify-content-center align-items-center bi bi-x-lg"
                                    ></i>
                                    Reject Selected
                                </button>
                                <button
                                    class="btn-bulk btn-clear"
                                    @click="clearSelection"
                                >
                                    <i
                                        class="d-flex justify-content-center align-items-center bi bi-x-circle"
                                    ></i>
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Search and Filter -->
                    <div class="filter-section mb-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <div class="search-box">
                                    <i
                                        class="d-flex justify-content-center align-items-center bi bi-search"
                                    ></i>
                                    <input
                                        type="text"
                                        v-model="filters.search"
                                        placeholder="Search by employee name or transaction ID..."
                                        class="search-input"
                                        @input="handleSearchInput"
                                    />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <input
                                    type="month"
                                    v-model="filters.periode"
                                    class="filter-select"
                                    @change="fetchApprovals()"
                                />
                            </div>
                            <div class="col-md-3">
                                <select
                                    v-model="filters.division"
                                    class="filter-select"
                                    @change="fetchApprovals()"
                                >
                                    <option value="">All Division</option>
                                    <option
                                        v-for="division in filterOptions.divisions"
                                        :key="division.id"
                                        :value="division.id"
                                    >
                                        {{ division.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select
                                    v-model="filters.level"
                                    class="filter-select"
                                    @change="fetchApprovals()"
                                >
                                    <option value="">All Level</option>
                                    <option
                                        v-for="level in filterOptions.levels"
                                        :key="level.id"
                                        :value="level.id"
                                    >
                                        {{ level.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="filter-info">
                                    <span class="filter-text">
                                        <div class="pagination-info">
                                            Menampilkan
                                            {{
                                                approvalsPagination.startItem
                                            }}-{{ approvalsPagination.endItem }}
                                            dari
                                            {{ approvalsPagination.totalDatas }}
                                            data
                                        </div>
                                    </span>
                                    <button
                                        class="btn-clear-filters"
                                        @click="clearFilters"
                                    >
                                        <i
                                            class="d-flex justify-content-center align-items-center bi bi-arrow-clockwise"
                                        ></i>
                                        Reset Filters
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div v-if="loading.fetch" class="loading-state">
                        <div class="spinner"></div>
                        <p>Loading performance approvals...</p>
                    </div>

                    <!-- Empty State - No Schedule -->
                    <div
                        v-if="
                            !loading.fetch &&
                            filteredApprovals.length === 0 &&
                            !hasApprovalSchedule
                        "
                        class="empty-state-schedule flex-column d-flex justify-content-center align-items-center"
                    >
                        <div class="empty-icon-container">
                            <i
                                class="bi bi-calendar-x d-flex justify-content-center align-items-center"
                            ></i>
                        </div>
                        <h4 class="empty-title">Tidak Ada Approval</h4>
                        <p class="empty-description">
                            Saat ini tidak ada approval performance yang aktif.
                        </p>
                        <!-- <div class="schedule-info-card">
                            <div class="info-header">
                                <i class="bi bi-info-circle"></i>
                                <span>Informasi Jadwal</span>
                            </div>
                            <div class="info-content">
                                <div class="info-item">
                                    <span class="info-label">Status:</span>
                                    <span class="status-inactive"
                                        >Tidak Aktif</span
                                    >
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Periode:</span>
                                    <span class="info-value-form">-</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Batas Waktu:</span>
                                    <span class="info-value-form">-</span>
                                </div>
                            </div>
                        </div> -->
                    </div>

                    <!-- Table/Card View -->
                    <transition name="fade-switch" mode="out-in">
                        <!-- Desktop Table View -->
                        <div
                            v-if="!isMobile && filteredApprovals.length > 0"
                            class="table-view-container"
                            key="table"
                        >
                            <div class="table-responsive-wrapper">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th class="header-cell">
                                                <div
                                                    class="header-cell-content"
                                                >
                                                    <i
                                                        class="bi bi-hash me-2 d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    <span>No Transaksi</span>
                                                </div>
                                            </th>
                                            <th class="header-cell">
                                                <div
                                                    class="header-cell-content"
                                                >
                                                    <i
                                                        class="bi bi-person me-2 d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    <span>Karyawan</span>
                                                </div>
                                            </th>
                                            <th class="header-cell">
                                                <div
                                                    class="header-cell-content"
                                                >
                                                    <i
                                                        class="bi bi-building me-2 d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    <span>Divisi</span>
                                                </div>
                                            </th>
                                            <th class="header-cell">
                                                <div
                                                    class="header-cell-content"
                                                >
                                                    <i
                                                        class="bi bi-calendar-range me-2 d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    <span>Periode</span>
                                                </div>
                                            </th>
                                            <th class="header-cell">
                                                <div
                                                    class="header-cell-content"
                                                >
                                                    <i
                                                        class="bi bi-speedometer2 me-2 d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    <span>Achievement</span>
                                                </div>
                                            </th>
                                            <th class="header-cell">
                                                <div
                                                    class="header-cell-content"
                                                >
                                                    <i
                                                        class="bi bi-exclamation-circle me-2 d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    <span>PICA</span>
                                                </div>
                                            </th>
                                            <th class="header-cell">
                                                <div
                                                    class="header-cell-content"
                                                >
                                                    <i
                                                        class="bi bi-check-circle me-2 d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    <span>Status</span>
                                                </div>
                                            </th>
                                            <th class="header-cell">
                                                <div
                                                    class="header-cell-content"
                                                >
                                                    <i
                                                        class="bi bi-gear me-2 d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    <span>Actions</span>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="approval in filteredApprovals"
                                            :key="
                                                approval.id_Performance_Approval
                                            "
                                            class="table-row"
                                        >
                                            <td>
                                                <div class="transaction-cell">
                                                    <div class="transaction-id">
                                                        {{
                                                            approval.No_Transaksi
                                                        }}
                                                    </div>
                                                    <div
                                                        class="transaction-time"
                                                    >
                                                        {{
                                                            formatTime(
                                                                approval.create_at,
                                                            )
                                                        }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="employee-cell">
                                                    <div class="avatar-sm">
                                                        {{
                                                            getInitials(
                                                                approval?.Nama,
                                                            )
                                                        }}
                                                    </div>
                                                    <div
                                                        class="employee-details"
                                                    >
                                                        <div
                                                            class="employee-name"
                                                        >
                                                            {{ approval?.Nama }}
                                                        </div>
                                                        <div
                                                            class="employee-code"
                                                        >
                                                            {{
                                                                approval?.Kode_Karyawan
                                                            }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="division-cell">
                                                    {{ approval?.Department }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="period-cell">
                                                    {{
                                                        convertPeriode(
                                                            approval?.Periode,
                                                        )
                                                    }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="score-cell">
                                                    <div class="score-progress">
                                                        <div
                                                            class="progress-bar"
                                                        >
                                                            <div
                                                                class="progress-fill"
                                                                :style="{
                                                                    width:
                                                                        calculateScorePercentage(
                                                                            approval,
                                                                        ) + '%',
                                                                }"
                                                                :class="
                                                                    getScoreClass(
                                                                        approval.total_score,
                                                                    )
                                                                "
                                                            ></div>
                                                        </div>
                                                        <span
                                                            class="score-value"
                                                        >
                                                            {{
                                                                formatAchievement(
                                                                    Number(
                                                                        approval.total_score ||
                                                                            0,
                                                                    ),
                                                                )
                                                            }}%
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="pica-indicator">
                                                    <span
                                                        v-if="
                                                            approval.pica_count >
                                                            0
                                                        "
                                                        class="pica-badge"
                                                    >
                                                        <i
                                                            class="bi bi-exclamation-triangle"
                                                        ></i>
                                                        {{
                                                            approval.pica_count
                                                        }}
                                                        PICA
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="pica-badge no-pica"
                                                    >
                                                        <i
                                                            class="bi bi-check-circle"
                                                        ></i>
                                                        No PICA
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="status-cell">
                                                    <span
                                                        :class="`status-badge status-${getStatusClass(
                                                            approval.Flag_Approval,
                                                        )}`"
                                                    >
                                                        <i
                                                            :class="
                                                                getStatusIcon(
                                                                    approval.Flag_Approval,
                                                                )
                                                            "
                                                        ></i>
                                                        {{
                                                            getStatusText(
                                                                approval.Flag_Approval,
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-cell">
                                                    <button
                                                        v-if="
                                                            approval.Flag_Approval ===
                                                            null
                                                        "
                                                        class="btn-action btn-approve-sm"
                                                        @click.stop="
                                                            openApproveModal(
                                                                approval,
                                                            )
                                                        "
                                                        title="Approve"
                                                    >
                                                        <i
                                                            class="bi bi-check-lg d-flex justify-content-center align-items-center"
                                                        ></i>
                                                    </button>
                                                    <button
                                                        v-if="
                                                            approval.Flag_Approval ===
                                                            null
                                                        "
                                                        class="btn-action btn-edit-sm"
                                                        @click.stop="
                                                            openEditModal(
                                                                approval,
                                                            )
                                                        "
                                                        title="Edit"
                                                    >
                                                        <i
                                                            class="bi bi-pencil d-flex justify-content-center align-items-center"
                                                        ></i>
                                                    </button>
                                                    <button
                                                        v-if="
                                                            approval.Flag_Approval ===
                                                            null
                                                        "
                                                        class="btn-action btn-reject-sm"
                                                        @click.stop="
                                                            openRejectModal(
                                                                approval,
                                                            )
                                                        "
                                                        title="Tolak"
                                                    >
                                                        <i
                                                            class="bi bi-x-lg d-flex justify-content-center align-items-center"
                                                        ></i>
                                                    </button>
                                                    <button
                                                        class="btn-action btn-detail"
                                                        @click.stop="
                                                            showDetailModal(
                                                                approval,
                                                            )
                                                        "
                                                        title="View Details"
                                                    >
                                                        <i
                                                            class="bi bi-eye d-flex justify-content-center align-items-center"
                                                        ></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination -->
                            <div
                                class="pagination-container justify-content-md-end"
                                v-if="approvalsPagination.totalPages > 1"
                            >
                                <div class="pagination-info">
                                    Menampilkan
                                    {{ approvalsPagination.startItem }}-{{
                                        approvalsPagination.endItem
                                    }}
                                    dari
                                    {{ approvalsPagination.totalDatas }} data
                                </div>
                                <div class="pagination-controls">
                                    <button
                                        class="pagination-btn pagination-prev"
                                        @click="
                                            fetchApprovals(
                                                approvalsPagination.prevLink,
                                            )
                                        "
                                        :disabled="
                                            !approvalsPagination.prevLink
                                        "
                                    >
                                        <i
                                            class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button>

                                    <!-- <div class="pagination-pages">
                                    <button
                                        v-for="page in visiblePages"
                                        :key="page"
                                        class="pagination-page"
                                        :class="{ active: page === currentPage }"
                                        @click="goToPage(page)"
                                    >
                                        {{ page }}
                                    </button>

                                    <span class="pagination-ellipsis" v-if="showEndEllipsis">
                                        ...
                                    </span>
                                </div> -->

                                    <button
                                        class="pagination-btn pagination-next"
                                        @click="
                                            fetchApprovals(
                                                approvalsPagination.nextLink,
                                            )
                                        "
                                        :disabled="
                                            approvalsPagination.currentPage ===
                                            approvalsPagination.totalPages
                                        "
                                    >
                                        <i
                                            class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button>
                                </div>

                                <!-- <div class="pagination-page-size">
                                <label for="pageSize">Items per page:</label>
                                <select
                                    id="pageSize"
                                    v-model="pageSize"
                                    @change="onPageSizeChange"
                                    class="page-size-select"
                                >
                                    <option value="10">10</option>

                                </select>
                            </div> -->
                            </div>
                        </div>

                        <!-- Mobile Card View -->
                        <div
                            v-else-if="isMobile && filteredApprovals.length > 0"
                            class="card-view-container"
                            key="card"
                        >
                            <div class="card-grid">
                                <div
                                    v-for="approval in filteredApprovals"
                                    :key="approval.id_Performance_Approval"
                                    class="approval-card"
                                    :class="{
                                        'status-pending':
                                            approval.Flag_Approval === null,
                                    }"
                                >
                                    <div class="card-header">
                                        <div class="header-left">
                                            <div class="transaction-info">
                                                <div class="transaction-id">
                                                    {{ approval.No_Transaksi }}
                                                </div>
                                                <div class="transaction-date">
                                                    {{
                                                        formatDate(
                                                            approval?.create_at,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="status-badge-mobile"
                                            :class="`status-${getStatusClass(
                                                approval.Flag_Approval,
                                            )}`"
                                        >
                                            {{
                                                getStatusText(
                                                    approval.Flag_Approval,
                                                )
                                            }}
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="employee-info-mobile">
                                            <div class="avatar-sm">
                                                {{
                                                    getInitials(approval?.Nama)
                                                }}
                                            </div>
                                            <div
                                                class="employee-details-mobile"
                                            >
                                                <div class="employee-name">
                                                    {{ approval?.Nama }}
                                                </div>
                                                <div class="employee-meta">
                                                    {{
                                                        approval?.Kode_Karyawan
                                                    }}
                                                </div>
                                                <div class="employee-meta">
                                                    {{ approval?.Department }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="performance-summary-mobile">
                                            <div class="summary-item">
                                                <i
                                                    class="d-flex justify-content-center align-items-center bi bi-calendar-range"
                                                ></i>
                                                <span>{{
                                                    approval.Periode
                                                }}</span>
                                            </div>
                                            <div class="summary-item">
                                                <i
                                                    class="bi bi-exclamation-circle d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span
                                                    >{{
                                                        approval.pica_count
                                                    }}
                                                    PICA</span
                                                >
                                            </div>
                                            <div class="summary-item">
                                                <i
                                                    class="bi bi-speedometer2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>
                                                    {{
                                                        Number(
                                                            approval.total_score ||
                                                                0,
                                                        ).toFixed(0)
                                                    }}% Score</span
                                                >
                                            </div>
                                        </div>

                                        <div class="score-progress-mobile">
                                            <div class="progress-bar">
                                                <div
                                                    class="progress-fill"
                                                    :style="{
                                                        width:
                                                            calculateScorePercentage(
                                                                approval,
                                                            ) + '%',
                                                    }"
                                                    :class="
                                                        getScoreClass(
                                                            approval.total_score,
                                                        )
                                                    "
                                                ></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-footer">
                                        <button
                                            class="btn-card btn-detail"
                                            @click="showDetailModal(approval)"
                                        >
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-eye"
                                            ></i>
                                            Details
                                        </button>
                                        <div
                                            class="action-buttons-mobile"
                                            v-if="
                                                approval.Flag_Approval === null
                                            "
                                        >
                                            <button
                                                class="btn-card btn-edit"
                                                @click="openEditModal(approval)"
                                            >
                                                <i
                                                    class="d-flex justify-content-center align-items-center bi bi-pencil"
                                                ></i>
                                                Edit
                                            </button>
                                            <button
                                                class="btn-card btn-reject"
                                                @click="openRejectModal(approval)"
                                            >
                                                <i
                                                    class="d-flex justify-content-center align-items-center bi bi-x-lg"
                                                ></i>
                                                Tolak
                                            </button>
                                            <button
                                                class="btn-card btn-approve"
                                                @click="
                                                    openApproveModal(approval)
                                                "
                                            >
                                                <i
                                                    class="d-flex justify-content-center align-items-center bi bi-check-lg"
                                                ></i>
                                                Approve
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Pagination -->
                            <div
                                class="pagination-container"
                                v-if="approvalsPagination.totalPages > 1"
                            >
                                <div class="pagination-controls">
                                    <button
                                        class="pagination-btn pagination-prev"
                                        @click="
                                            fetchApprovals(
                                                approvalsPagination.prevLink,
                                            )
                                        "
                                        :disabled="
                                            !approvalsPagination.prevLink
                                        "
                                    >
                                        <i
                                            class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button>

                                    <!-- <div class="pagination-pages">
                                    <button
                                        v-for="page in visiblePages"
                                        :key="page"
                                        class="pagination-page"
                                        :class="{ active: page === currentPage }"
                                        @click="goToPage(page)"
                                    >
                                        {{ page }}
                                    </button>

                                    <span class="pagination-ellipsis" v-if="showEndEllipsis">
                                        ...
                                    </span>
                                </div> -->

                                    <button
                                        class="pagination-btn pagination-next"
                                        @click="
                                            fetchApprovals(
                                                approvalsPagination.nextLink,
                                            )
                                        "
                                        :disabled="
                                            approvalsPagination.currentPage ===
                                            approvalsPagination.totalPages
                                        "
                                    >
                                        <i
                                            class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button>
                                </div>

                                <!-- <div class="pagination-page-size">
                                <label for="pageSize">Items per page:</label>
                                <select
                                    id="pageSize"
                                    v-model="pageSize"
                                    @change="onPageSizeChange"
                                    class="page-size-select"
                                >
                                    <option value="10">10</option>

                                </select>
                            </div> -->
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-else-if="
                                !loading.fetch &&
                                filteredApprovals.length === 0 &&
                                hasApprovalSchedule
                            "
                            class="empty-state flex-column d-flex justify-content-center align-items-center"
                            key="empty"
                        >
                            <div class="empty-icon">
                                <i
                                    class="bi bi-inbox d-flex justify-content-center align-items-center"
                                ></i>
                            </div>
                            <h4>No Verifikasi Performances Found</h4>
                            <p>
                                There are no performance approvals matching your
                                criteria.
                            </p>
                            <button
                                class="btn-modern btn-primary mt-3"
                                @click="clearFilters"
                            >
                                <i
                                    class="bi bi-arrow-clockwise d-flex justify-content-center align-items-center me-2"
                                ></i>
                                Clear Filters
                            </button>
                        </div>
                    </transition>
                </div>
            </template>

            <!-- Tab 2: Approval History -->
            <template #history>
                <div class="history-content">
                    <!-- History Filter -->
                    <div class="filter-section mb-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <div class="search-box">
                                    <i
                                        class="d-flex justify-content-center align-items-center bi bi-search"
                                    ></i>
                                    <input
                                        type="text"
                                        v-model="historyFilters.search"
                                        placeholder="Search history..."
                                        class="search-input"
                                        @input="handleHistorySearch"
                                    />
                                </div>
                            </div>
                            <div class="col-md-2">
                                <input
                                    type="month"
                                    v-model="historyFilters.periode"
                                    class="filter-select"
                                    @change="fetchHistory()"
                                />
                            </div>
                            <div class="col-md-2">
                                <select
                                    v-model="historyFilters.division"
                                    class="filter-select"
                                    @change="fetchHistory()"
                                >
                                    <option value="">All Division</option>
                                    <option
                                        v-for="division in filterOptions.divisions"
                                        :key="division.id"
                                        :value="division.id"
                                    >
                                        {{ division.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select
                                    v-model="historyFilters.level"
                                    class="filter-select"
                                    @change="fetchHistory()"
                                >
                                    <option value="">All Level</option>
                                    <option
                                        v-for="level in filterOptions.levels"
                                        :key="level.id"
                                        :value="level.id"
                                    >
                                        {{ level.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select
                                    v-model="historyFilters.status"
                                    class="filter-select"
                                    @change="fetchHistory()"
                                >
                                    <option value="">All Status</option>
                                    <option value="approved">Disetujui</option>
                                    <option value="rejected">Ditolak</option>
                                </select>
                            </div>
                            <div class="w-100">
                                <div class="row mt-2 p-0">
                                    <div class="col-12">
                                        <div class="filter-info">
                                            <span class="filter-text">
                                                <div class="pagination-info">
                                                    Menampilkan
                                                    {{
                                                        historyApprovalsPagination.startItem
                                                    }}-{{
                                                        historyApprovalsPagination.endItem
                                                    }}
                                                    dari
                                                    {{
                                                        historyApprovalsPagination.totalDatas
                                                    }}
                                                    data
                                                </div>
                                            </span>
                                            <button
                                                class="btn-clear-filters"
                                                @click="clearHistoryFilters"
                                            >
                                                <i
                                                    class="d-flex justify-content-center align-items-center bi bi-arrow-clockwise"
                                                ></i>
                                                Reset Filters
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- History Content -->
                    <div v-if="loading.history" class="loading-state">
                        <div class="spinner"></div>
                        <p>Loading history...</p>
                    </div>

                    <!-- Desktop Table View for History -->
                    <div
                        v-else-if="!isMobile && historyApprovals.length > 0"
                        class="table-view-container"
                        key="table-history"
                    >
                        <div class="table-responsive-wrapper">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-hash me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>No Transaksi</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-person me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Karyawan</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-building me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Divisi</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-calendar-range me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Periode</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-speedometer2 me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Score</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-exclamation-circle me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>PICA</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-check-circle me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Status</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-eye me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Details</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="approval in historyApprovals"
                                        :key="approval.id_Performance_Approval"
                                        class="table-row"
                                        @click="console.log(approval)"
                                    >
                                        <td>
                                            <div class="transaction-cell">
                                                <div class="transaction-id">
                                                    {{ approval.No_Transaksi }}
                                                </div>
                                                <div class="transaction-time">
                                                    {{
                                                        formatTime(
                                                            approval.update_at,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="employee-cell">
                                                <div class="avatar-sm">
                                                    {{
                                                        getInitials(
                                                            approval.Nama,
                                                        )
                                                    }}
                                                </div>
                                                <div class="employee-details">
                                                    <div class="employee-name">
                                                        {{ approval.Nama }}
                                                    </div>
                                                    <div class="employee-code">
                                                        {{
                                                            approval.Kode_Karyawan
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="division-cell">
                                                {{ approval.Department }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="period-cell">
                                                {{
                                                    convertPeriode(
                                                        approval?.Periode,
                                                    )
                                                }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="score-cell">
                                                <div class="score-progress">
                                                    <div class="progress-bar">
                                                        <div
                                                            class="progress-fill"
                                                            :style="{
                                                                width:
                                                                    calculateScorePercentage(
                                                                        approval,
                                                                    ) + '%',
                                                            }"
                                                            :class="
                                                                getScoreClass(
                                                                    approval.total_score,
                                                                )
                                                            "
                                                        ></div>
                                                    </div>
                                                    <span class="score-value">
                                                        {{
                                                            formatAchievement(
                                                                Number(
                                                                    approval.total_score ||
                                                                        0,
                                                                ),
                                                            )
                                                        }}%
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="pica-indicator">
                                                <span
                                                    v-if="
                                                        approval.pica_count > 0
                                                    "
                                                    class="pica-badge"
                                                >
                                                    <i
                                                        class="bi bi-exclamation-triangle"
                                                    ></i>
                                                    {{ approval.pica_count }}
                                                    PICA
                                                </span>
                                                <span
                                                    v-else
                                                    class="pica-badge no-pica"
                                                >
                                                    <i
                                                        class="bi bi-check-circle"
                                                    ></i>
                                                    No PICA
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="status-cell">
                                                <span
                                                    :class="`status-badge status-${getStatusClass(
                                                        approval.Flag_Approval,
                                                    )}`"
                                                >
                                                    <i
                                                        :class="
                                                            getStatusIcon(
                                                                approval.Flag_Approval,
                                                            )
                                                        "
                                                    ></i>
                                                    {{
                                                        getStatusText(
                                                            approval.Flag_Approval,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-cell">
                                                <button
                                                    class="btn-action btn-detail"
                                                    @click.stop="
                                                        showDetailModal(
                                                            approval,
                                                        )
                                                    "
                                                    title="View Details"
                                                >
                                                    <i
                                                        class="d-flex justify-content-center align-items-center bi bi-eye"
                                                    ></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->
                        <div
                            class="pagination-container"
                            v-if="historyApprovalsPagination.totalPages > 1"
                        >
                            <div class="pagination-info">
                                Menampilkan
                                {{ historyApprovalsPagination.startItem }}-{{
                                    historyApprovalsPagination.endItem
                                }}
                                dari
                                {{ historyApprovalsPagination.totalDatas }} data
                            </div>
                            <div class="pagination-controls">
                                <button
                                    class="pagination-btn pagination-prev"
                                    @click="
                                        fetchHistory(
                                            historyApprovalsPagination.prevLink,
                                        )
                                    "
                                    :disabled="
                                        !historyApprovalsPagination.prevLink
                                    "
                                >
                                    <i
                                        class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                                    ></i>
                                </button>

                                <!-- <div class="pagination-pages">
                                    <button
                                        v-for="page in visiblePages"
                                        :key="page"
                                        class="pagination-page"
                                        :class="{ active: page === currentPage }"
                                        @click="goToPage(page)"
                                    >
                                        {{ page }}
                                    </button>

                                    <span class="pagination-ellipsis" v-if="showEndEllipsis">
                                        ...
                                    </span>
                                </div> -->

                                <button
                                    class="pagination-btn pagination-next"
                                    @click="
                                        fetchHistory(
                                            historyApprovalsPagination.nextLink,
                                        )
                                    "
                                    :disabled="
                                        historyApprovalsPagination.currentPage ===
                                        historyApprovalsPagination.totalPages
                                    "
                                >
                                    <i
                                        class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                                    ></i>
                                </button>
                            </div>

                            <!-- <div class="pagination-page-size">
                                <label for="pageSize">Items per page:</label>
                                <select
                                    id="pageSize"
                                    v-model="pageSize"
                                    @change="onPageSizeChange"
                                    class="page-size-select"
                                >
                                    <option value="10">10</option>

                                </select>
                            </div> -->
                        </div>
                    </div>

                    <!-- Mobile Card View for History -->
                    <div
                        v-else-if="isMobile && historyApprovals.length > 0"
                        class="card-view-container"
                        key="card-history"
                    >
                        <div class="card-grid">
                            <div
                                v-for="approval in historyApprovals"
                                :key="approval.id_Performance_Approval"
                                class="history-card"
                                :class="`status-${getStatusClass(
                                    approval.Flag_Approval,
                                )}`"
                            >
                                <div class="card-header">
                                    <div class="header-left">
                                        <div class="transaction-info">
                                            <div class="transaction-id">
                                                {{ approval.No_Transaksi }}
                                            </div>
                                            <div
                                                class="transaction-date d-flex justify-content-between"
                                            >
                                                <div>
                                                    {{
                                                        formatDateString(
                                                            approval.update_at,
                                                        )
                                                    }}
                                                </div>
                                                <div
                                                    class="status-badge-mobile"
                                                    :class="`status-${getStatusClass(
                                                        approval.Flag_Approval,
                                                    )}`"
                                                >
                                                    {{
                                                        getStatusText(
                                                            approval.Flag_Approval,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div class="employee-info-mobile">
                                        <div class="avatar-sm">
                                            {{ getInitials(approval.Nama) }}
                                        </div>
                                        <div class="employee-details-mobile">
                                            <div class="employee-name">
                                                {{ approval.Nama }}
                                            </div>
                                            <div class="employee-meta">
                                                {{ approval.Kode_Karyawan }}
                                            </div>
                                            <div class="employee-meta">
                                                {{ approval.Department }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="performance-summary-mobile">
                                        <div class="summary-item">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-calendar-range"
                                            ></i>
                                            <span>{{
                                                convertPeriode(
                                                    approval?.Periode,
                                                )
                                            }}</span>
                                        </div>
                                        <div class="summary-item">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-exclamation-circle"
                                            ></i>
                                            <span
                                                >{{
                                                    approval.pica_count
                                                }}
                                                PICA</span
                                            >
                                        </div>
                                        <div class="summary-item">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-speedometer2"
                                            ></i>
                                            <span
                                                >{{
                                                    formatAchievement(
                                                        Number(
                                                            approval.total_score ||
                                                                0,
                                                        ),
                                                    )
                                                }}% Score</span
                                            >
                                        </div>
                                    </div>
                                    <div class="score-progress-mobile">
                                        <div class="progress-bar">
                                            <div
                                                class="progress-fill"
                                                :style="{
                                                    width:
                                                        calculateScorePercentage(
                                                            approval,
                                                        ) + '%',
                                                }"
                                                :class="
                                                    getScoreClass(
                                                        approval.total_score,
                                                    )
                                                "
                                            ></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <button
                                        class="btn-card btn-detail"
                                        @click="showDetailModal(approval)"
                                    >
                                        <i
                                            class="d-flex justify-content-center align-items-center bi bi-eye"
                                        ></i>
                                        View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Pagination -->
                        <div
                            class="pagination-container"
                            v-if="historyApprovalsPagination.totalPages > 1"
                        >
                            <!-- <div class="pagination-info">
                                Menampilkan
                                {{ historyApprovalsPagination.startItem }}-{{
                                    historyApprovalsPagination.endItem
                                }}
                                dari
                                {{ historyApprovalsPagination.totalDatas }} data
                            </div> -->
                            <div class="pagination-controls">
                                <button
                                    class="pagination-btn pagination-prev"
                                    @click="
                                        fetchHistory(
                                            historyApprovalsPagination.prevLink,
                                        )
                                    "
                                    :disabled="
                                        !historyApprovalsPagination.prevLink
                                    "
                                >
                                    <i
                                        class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                                    ></i>
                                </button>

                                <!-- <div class="pagination-pages">
                                    <button
                                        v-for="page in visiblePages"
                                        :key="page"
                                        class="pagination-page"
                                        :class="{ active: page === currentPage }"
                                        @click="goToPage(page)"
                                    >
                                        {{ page }}
                                    </button>

                                    <span class="pagination-ellipsis" v-if="showEndEllipsis">
                                        ...
                                    </span>
                                </div> -->

                                <button
                                    class="pagination-btn pagination-next"
                                    @click="
                                        fetchHistory(
                                            historyApprovalsPagination.nextLink,
                                        )
                                    "
                                    :disabled="
                                        historyApprovalsPagination.currentPage ===
                                        historyApprovalsPagination.totalPages
                                    "
                                >
                                    <i
                                        class="bi bi-chevron-right d-flex justify-content-center align-items-center"
                                    ></i>
                                </button>
                            </div>

                            <!-- <div class="pagination-page-size">
                                <label for="pageSize">Items per page:</label>
                                <select
                                    id="pageSize"
                                    v-model="pageSize"
                                    @change="onPageSizeChange"
                                    class="page-size-select"
                                >
                                    <option value="10">10</option>

                                </select>
                            </div> -->
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else-if="!loading.history"
                        class="empty-state flex-column d-flex justify-content-center align-items-center"
                        key="empty"
                    >
                        <div class="empty-icon mb-5">
                            <i
                                class="d-flex justify-content-center align-items-center bi bi-clock-history"
                            ></i>
                        </div>
                        <h4>No History Found</h4>
                        <p>
                            There are no performance approval history records
                            matching your criteria.
                        </p>
                        <button
                            class="btn-modern btn-primary mt-3"
                            @click="clearHistoryFilters"
                        >
                            <i
                                class="bi bi-arrow-clockwise d-flex justify-content-center align-items-center me-2"
                            ></i>
                            Clear Filters
                        </button>
                    </div>
                </div>
            </template>
        </ApprovalTabs>

        <!-- Detail Modal -->
        <Modal
            v-if="showDetailModalFlag"
            :show="showDetailModalFlag"
            :title="`Performance Details - ${selectedApproval?.Nama}`"
            size="xlarge"
            @close="closeDetailModal"
        >
            <template #body>
                <div class="performance-detail-content">
                    <!-- Loading State -->
                    <div v-if="loading.detail" class="perf-loading-overlay">
                        <div class="perf-loading-card">
                            <div class="perf-spinner-circle"></div>
                            <p class="perf-loading-text">Memuat detail performance...</p>
                        </div>
                    </div>

                    <!-- Content -->
                    <div :class="{ 'content-blur': loading.detail }">
                        <!-- Alasan penolakan GLOBAL (komentar umum approver) -->
                        <div
                            v-if="selectedApproval?.alasan_reject_global"
                            class="global-reject-banner"
                        >
                            <i class="bi bi-x-octagon-fill"></i>
                            <div class="global-reject-text">
                                <span class="global-reject-label"
                                    >Alasan Penolakan</span
                                >
                                <p class="global-reject-value">
                                    {{ selectedApproval.alasan_reject_global }}
                                </p>
                            </div>
                        </div>

                        <!-- Header Info -->
                        <div class="detail-header">
                            <div class="detail-header-main">
                                <div class="employee-info">
                                    <div class="avatar-lg">
                                        {{ getInitials(selectedApproval?.Nama) }}
                                    </div>
                                    <div class="employee-details">
                                        <h5 class="employee-name">{{ selectedApproval?.Nama }}</h5>
                                        <div class="employee-meta-badges">
                                            <span class="meta-badge job-class">
                                                <i class="bi bi-person-badge"></i>
                                                {{ selectedApproval?.Kode_Karyawan }}
                                            </span>
                                            <span class="meta-badge dept">
                                                <i class="bi bi-building"></i>
                                                {{ selectedApproval?.Department }}
                                            </span>
                                            <span class="meta-badge position">
                                                <i class="bi bi-briefcase"></i>
                                                {{ selectedApproval?.Jabatan }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="performance-summary">
                                    <div class="summary-card card-achievement">
                                        <div class="summary-icon score">
                                            <i class="bi bi-speedometer2"></i>
                                        </div>
                                        <div class="summary-content">
                                            <div class="summary-value">
                                                {{ formatAchievement(cappedAchievement) }}%
                                                <span
                                                    v-if="isScoreCapped"
                                                    class="cap-indicator"
                                                    :title="capNote"
                                                >
                                                    <i class="bi bi-info-circle-fill"></i>
                                                </span>
                                            </div>
                                            <div class="summary-label">
                                                Achievement
                                            </div>
                                        </div>
                                    </div>
                                    <div class="summary-card card-kpi">
                                        <div class="summary-icon kpi">
                                            <i class="bi bi-list-task"></i>
                                        </div>
                                        <div class="summary-content">
                                            <div class="summary-value">
                                                {{ selectedApproval?.kpi_count }}
                                            </div>
                                            <div class="summary-label">
                                                Total KPI
                                            </div>
                                        </div>
                                    </div>
                                    <div class="summary-card card-pica">
                                        <div class="summary-icon pica">
                                            <i class="bi bi-exclamation-triangle"></i>
                                        </div>
                                        <div class="summary-content">
                                            <div class="summary-value">
                                                {{ selectedApproval?.pica_count }}
                                            </div>
                                            <div class="summary-label">
                                                PICA Items
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Audit / Capped Note Banner -->
                            <div
                                v-if="isScoreCapped && capNote"
                                class="cap-note-banner"
                            >
                                <div class="cap-note-icon-wrap">
                                    <i class="bi bi-shield-exclamation"></i>
                                </div>
                                <div class="cap-note-text">
                                    <span class="cap-note-title">Penyesuaian Batas Capaian:</span>
                                    <span class="cap-note-desc">{{ capNote }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Transaction Info -->
                        <div class="transaction-info-section mb-4">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-item-label">
                                        <i class="bi bi-hash"></i>
                                        <span>No. Transaksi</span>
                                    </div>
                                    <span class="info-item-val">{{ selectedApproval?.No_Transaksi || '-' }}</span>
                                </div>
                                <div class="info-item">
                                    <div class="info-item-label">
                                        <i class="bi bi-calendar-range"></i>
                                        <span>Periode</span>
                                    </div>
                                    <span class="info-item-val">{{ convertPeriode(selectedApproval?.Periode) || '-' }}</span>
                                </div>
                                <div class="info-item">
                                    <div class="info-item-label">
                                        <i class="bi bi-clock-history"></i>
                                        <span>Dibuat</span>
                                    </div>
                                    <span class="info-item-val">{{ formatDateString(selectedApproval?.create_at) || '-' }}</span>
                                </div>
                                <div class="info-item">
                                    <div class="info-item-label">
                                        <i class="bi bi-shield-check"></i>
                                        <span>Status</span>
                                    </div>
                                    <span
                                        :class="`status-badge status-${getStatusClass(
                                            selectedApproval?.Flag_Approval,
                                        )}`"
                                    >
                                        {{
                                            getStatusText(
                                                selectedApproval?.Flag_Approval,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- KPI Performance Section -->
                        <div class="kpi-performance-section">
                            <div class="section-title-wrap mb-3">
                                <i class="bi bi-list-stars"></i>
                                <h6>KPI Performance Details</h6>
                            </div>

                            <!-- KPI List Loading Skeleton -->
                            <div
                                v-if="loading.detail"
                                class="kpi-list-skeleton"
                            >
                                <div
                                    v-for="n in 3"
                                    :key="n"
                                    class="kpi-skeleton-item"
                                >
                                    <div class="skeleton-header">
                                        <div class="skeleton-avatar"></div>
                                        <div class="skeleton-content">
                                            <div
                                                class="skeleton-line short"
                                            ></div>
                                            <div
                                                class="skeleton-line medium"
                                            ></div>
                                        </div>
                                        <div class="skeleton-badge"></div>
                                    </div>
                                    <div class="skeleton-body">
                                        <div class="skeleton-progress"></div>
                                        <div class="skeleton-stats">
                                            <div class="skeleton-line"></div>
                                            <div class="skeleton-line"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actual KPI List -->
                            <div v-else class="kpi-list">
                                <div
                                    v-for="(
                                        kpi, index
                                    ) in selectedApproval?.kpiDetails"
                                    :key="kpi.id"
                                    class="kpi-performance-item"
                                    :class="{
                                        'requires-pica': kpi.Flag_Pica === 'Y',
                                        disabled: kpi.Is_Disabled === 'Y',
                                    }"
                                >
                                    <div
                                        v-if="kpi.Is_Disabled === 'Y'"
                                        class="disabled-badge"
                                    >
                                        <i class="bi bi-slash-circle"></i>
                                        KPI Tidak Aktif pada Periode ini
                                    </div>
                                    <div class="kpi-header">
                                        <div class="kpi-main-info">
                                            <div class="kpi-number">
                                                #{{ index + 1 }}
                                            </div>
                                            <div class="kpi-details">
                                                <h6 class="kpi-title">{{ kpi.parameter }}</h6>
                                                <div class="kpi-meta">
                                                    <span class="category">
                                                        <i class="bi bi-tag me-1"></i>
                                                        {{ kpi.bsc_category }}
                                                    </span>
                                                    <span class="weight">
                                                        <i class="bi bi-pie-chart me-1"></i>
                                                        Bobot {{ formatAchievement(kpi.weight) }}%
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kpi-performance-indicator">
                                            <div
                                                class="achievement-badge"
                                                :class="
                                                    getAchievementClass(
                                                        kpi.score,
                                                    )
                                                "
                                            >
                                                {{
                                                    formatAchievement(
                                                        kpi.score,
                                                    )
                                                }}%
                                            </div>
                                        </div>
                                    </div>

                                    <div class="kpi-content">
                                        <!-- Performance Metrics -->
                                        <div class="performance-metrics">
                                            <div class="metrics-grid">
                                                <div class="metric-item">
                                                    <span class="metric-label">Target Value</span>
                                                    <span class="metric-value">
                                                        {{
                                                            convertValueUnit(
                                                                Number(kpi.Nilai_Target),
                                                                kpi.Format_Input,
                                                                kpi.unit,
                                                            ) || "-"
                                                        }}
                                                    </span>
                                                </div>
                                                <div class="metric-item">
                                                    <span class="metric-label">Actual Value</span>
                                                    <span class="metric-value">
                                                        {{
                                                            convertValueUnit(
                                                                Number(kpi.actual_value),
                                                                kpi.Format_Input,
                                                                kpi.unit,
                                                            ) || "-"
                                                        }}
                                                    </span>
                                                </div>
                                                <div class="metric-item metric-score">
                                                    <span class="metric-label">Score</span>
                                                    <span class="metric-value score">
                                                        {{ formatScore(kpi.score || 0) }}%
                                                    </span>
                                                </div>
                                                <div class="metric-item metric-achieve">
                                                    <span class="metric-label">Achievement</span>
                                                    <span class="metric-value achieve">
                                                        {{ formatAchievement(kpi.achievement) }}%
                                                    </span>
                                                </div>
                                                <div class="metric-item">
                                                    <span class="metric-label">Weight</span>
                                                    <span class="metric-value">
                                                        {{ formatAchievement(Number(kpi.weight)) }}%
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Target Description -->
                                        <div
                                            class="target-section"
                                            v-if="kpi.target_description"
                                        >
                                            <div class="target-section-header">
                                                <i class="bi bi-card-text me-1"></i>
                                                <span>Target Description</span>
                                            </div>
                                            <p class="target-description">
                                                {{ kpi.target_description }}
                                            </p>
                                        </div>

                                        <!-- Attachment Section -->
                                        <div
                                            class="attachment-section"
                                            v-if="
                                                kpi.File_Berkas &&
                                                kpi.File_Berkas.trim() !== ''
                                            "
                                        >
                                            <div class="attachment-header">
                                                <h6>
                                                    <i
                                                        class="bi bi-paperclip attachment-icon"
                                                    ></i>
                                                    Lampiran Dokumen
                                                </h6>
                                            </div>
                                            <div class="attachment-content">
                                                <div class="attachment-item">
                                                    <div
                                                        class="attachment-info"
                                                    >
                                                        <div class="attachment-icon-box">
                                                            <i class="bi bi-file-earmark-text"></i>
                                                        </div>
                                                        <div
                                                            class="file-details"
                                                        >
                                                            <span
                                                                class="file-name"
                                                                >{{
                                                                    kpi.File_Berkas
                                                                        ? "File Lampiran Data Source"
                                                                        : "Tidak ada Lampiran"
                                                                }}</span
                                                            >
                                                            <span class="file-sub">Lampiran bukti pencapaian target</span>
                                                        </div>
                                                    </div>
                                                    <div
                                                        class="attachment-actions"
                                                        v-if="kpi.File_Berkas"
                                                    >
                                                        <button
                                                            @click="
                                                                getAttachmentUrl(
                                                                    kpi.File_Berkas,
                                                                    kpi.id,
                                                                )
                                                            "
                                                            class="btn-attachment btn-download"
                                                            :class="{
                                                                downloading:
                                                                    downloadingStates[
                                                                        kpi.id
                                                                    ] ===
                                                                    'downloading',
                                                                downloaded:
                                                                    downloadingStates[
                                                                        kpi.id
                                                                    ] ===
                                                                    'downloaded',
                                                            }"
                                                            :disabled="
                                                                downloadingStates[
                                                                    kpi.id
                                                                ] ===
                                                                'downloading'
                                                            "
                                                        >
                                                            <span
                                                                class="d-flex justify-content-center align-items-center"
                                                            >
                                                                <template
                                                                    v-if="
                                                                        downloadingStates[
                                                                            kpi
                                                                                .id
                                                                        ] ===
                                                                        'downloading'
                                                                    "
                                                                >
                                                                    <i
                                                                        class="bi bi-arrow-repeat d-flex justify-content-center align-items-center me-2 spinning"
                                                                    ></i>
                                                                    Downloading...
                                                                </template>
                                                                <template
                                                                    v-else-if="
                                                                        downloadingStates[
                                                                            kpi
                                                                                .id
                                                                        ] ===
                                                                        'downloaded'
                                                                    "
                                                                >
                                                                    <i
                                                                        class="bi bi-check-circle d-flex justify-content-center align-items-center me-2"
                                                                    ></i>
                                                                    Downloaded
                                                                </template>
                                                                <template
                                                                    v-else
                                                                >
                                                                    <i
                                                                        class="bi bi-download d-flex justify-content-center align-items-center me-2"
                                                                    ></i>
                                                                    Download File
                                                                </template>
                                                            </span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- PICA Section -->
                                        <div
                                            class="pica-section"
                                            v-if="
                                                kpi.pica && kpi.pica.length > 0
                                            "
                                        >
                                            <div class="pica-header">
                                                <i class="bi bi-exclamation-triangle-fill"></i>
                                                <h6>PICA (Corrective Action)</h6>
                                            </div>
                                            <div class="pica-content">
                                                <div class="pica-grid">
                                                    <div class="pica-item">
                                                        <label><i class="bi bi-search me-1"></i> Problem Identification:</label>
                                                        <p>
                                                            {{
                                                                kpi.pica[0]
                                                                    .problem_identification
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div class="pica-item">
                                                        <label><i class="bi bi-tools me-1"></i> Corrective Actions:</label>
                                                        <p>
                                                            {{
                                                                kpi.pica[0]
                                                                    .corrective_actions
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div class="pica-item">
                                                        <label><i class="bi bi-calendar-event me-1"></i> Due Date:</label>
                                                        <p class="due-date-pill">
                                                            {{
                                                                formatDateString(
                                                                    kpi.pica[0]
                                                                        .due_date,
                                                                )
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Rejection Comment -->
                                        <div
                                            class="rejection-comment"
                                            v-if="kpi.Alasan_Tolak"
                                        >
                                            <h6>
                                                <i
                                                    class="bi bi-x-circle text-danger"
                                                ></i>
                                                Alasan Penolakan
                                            </h6>
                                            <p>{{ kpi.Alasan_Tolak }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <!-- Loading State for Footer Buttons -->
                    <div v-if="loading.detail" class="footer-loading">
                        <div class="skeleton-button"></div>
                        <div class="skeleton-button"></div>
                        <div class="skeleton-button"></div>
                    </div>
                    <div v-else style="width: 100%">
                        <div
                            class="w-100"
                            v-if="selectedApproval?.Flag_Approval == null"
                        >
                            <div class="action-buttons justify-content-md-end">
                                <button
                                    class="btn-modern btn-warning"
                                    @click="openEditModal(selectedApproval)"
                                    :disabled="loading.detail"
                                >
                                    <i
                                        class="d-flex justify-content-center align-items-center bi bi-pencil me-2"
                                    ></i>
                                    Edit
                                </button>
                                <button
                                    class="btn-modern btn-danger"
                                    @click="openRejectModal(selectedApproval)"
                                    :disabled="loading.detail"
                                >
                                    <i
                                        class="d-flex justify-content-center align-items-center bi bi-x-lg me-2"
                                    ></i>
                                    Tolak
                                </button>
                                <button
                                    class="btn-modern btn-success"
                                    @click="openApproveModal(selectedApproval)"
                                    :disabled="loading.detail"
                                >
                                    <i
                                        class="d-flex justify-content-center align-items-center bi bi-check-lg me-2"
                                    ></i>
                                    Terima
                                </button>
                            </div>
                        </div>
                        <div v-else>
                            <div class="action-buttons justify-content-md-end">
                                <button
                                    class="btn-modern btn-secondary"
                                    @click="closeDetailModal()"
                                >
                                    <i
                                        class="d-flex justify-content-center align-items-center bi bi-x me-2"
                                    ></i>
                                    Tutup
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </Modal>
        <!-- Reject Modal -->
        <Modal
            v-if="showRejectModal"
            :show="showRejectModal"
            :title="`Reject Performance - ${selectedApproval?.Nama || selectedApproval?.employee?.Nama || ''}`"
            size="large"
            @close="closeRejectModal"
        >
            <template #body>
                <div class="reject-modal-content">
                    <div class="reject-banner">
                        <i class="bi bi-info-circle"></i>
                        <div class="reject-banner-text">
                            <strong>
                                Pengajuan akan dikembalikan ke karyawan untuk
                                direvisi.
                            </strong>
                            <span>
                                Alasan umum wajib diisi. Anda juga dapat menandai
                                parameter tertentu yang perlu diperbaiki.
                            </span>
                        </div>
                    </div>

                    <!-- Alasan umum (wajib) -->
                    <div class="reject-field">
                        <label class="reject-field-label">
                            Alasan Penolakan
                            <span class="reject-req">wajib</span>
                        </label>
                        <textarea
                            v-model="rejectionReason"
                            class="reject-textarea"
                            :class="{ 'is-invalid': rejectionError }"
                            rows="3"
                            placeholder="Jelaskan alasan penolakan pengajuan ini..."
                            @input="rejectionError = ''"
                        ></textarea>
                        <div v-if="rejectionError" class="reject-error">
                            <i class="bi bi-exclamation-circle me-1"></i>
                            {{ rejectionError }}
                        </div>
                    </div>

                    <!-- Komentar per parameter (opsional) -->
                    <div class="reject-params">
                        <div class="reject-params-head">
                            <span class="reject-params-title">
                                <i class="bi bi-list-check me-2"></i>
                                Komentar per Parameter
                            </span>
                            <span class="reject-params-opt">Opsional</span>
                        </div>
                        <p class="reject-params-hint">
                            Centang parameter yang perlu direvisi, lalu tulis
                            komentar spesifiknya.
                        </p>

                        <div class="reject-param-list">
                            <div
                                v-for="(
                                    kpi, index
                                ) in selectedApproval?.kpiDetails || []"
                                :key="kpi.id"
                                class="reject-param-item"
                                :class="{
                                    active: rejectionKpiSelection[index],
                                }"
                            >
                                <label class="reject-param-row">
                                    <input
                                        type="checkbox"
                                        class="reject-param-check"
                                        v-model="rejectionKpiSelection[index]"
                                        @change="
                                            handleKpiSelectionChange(index)
                                        "
                                    />
                                    <span class="reject-param-info">
                                        <span class="reject-param-name">
                                            {{ kpi.parameter }}
                                        </span>
                                        <span class="reject-param-chips">
                                            <span class="reject-chip">
                                                {{ kpi.bsc_category }}
                                            </span>
                                            <span class="reject-chip">
                                                Bobot {{ kpi.weight }}%
                                            </span>
                                            <span class="reject-chip">
                                                Target {{ kpi.Nilai_Target }}
                                            </span>
                                            <span class="reject-chip">
                                                Actual {{ kpi.actual_value }}
                                            </span>
                                            <span
                                                class="reject-chip reject-chip-score"
                                            >
                                                Score
                                                {{
                                                    Number(
                                                        kpi.score || 0,
                                                    ).toFixed(0)
                                                }}%
                                            </span>
                                        </span>
                                    </span>
                                </label>

                                <div
                                    v-if="rejectionKpiSelection[index]"
                                    class="reject-param-comment"
                                >
                                    <textarea
                                        v-model="rejectionComments[index]"
                                        class="reject-textarea"
                                        rows="2"
                                        :placeholder="`Apa yang perlu diperbaiki pada ${kpi.parameter}?`"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <button
                        class="btn-modern btn-secondary"
                        @click="closeRejectModal"
                    >
                        <i
                            class="d-flex justify-content-center align-items-center bi bi-x-circle"
                        ></i>
                        Batal
                    </button>
                    <button
                        class="btn-modern btn-danger"
                        @click="confirmReject"
                        :disabled="loading.reject"
                    >
                        <i
                            v-if="loading.reject"
                            class="spinner-border spinner-border-sm me-2"
                        ></i>
                        <i
                            v-else
                            class="d-flex justify-content-center align-items-center bi bi-x-lg me-2"
                        ></i>
                        {{
                            loading.reject ? "Processing..." : "Confirm Reject"
                        }}
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Edit Modal -->
        <Modal
            v-if="showEditModal"
            :show="showEditModal"
            :title="`Edit Performance - ${selectedApproval?.Nama ? selectedApproval?.Nama : 'Loading..'}`"
            size="large"
            @close="closeEditModal"
        >
            <template #body>
                <!-- Loading State -->
                <div v-if="loading.detail" class="perf-loading-overlay">
                    <div class="perf-loading-card">
                        <div class="perf-spinner-circle"></div>
                        <p class="perf-loading-text">Memuat detail performance...</p>
                    </div>
                </div>
                <div
                    class="edit-modal-content position-relative"
                    :class="{ 'content-blur': loading.detail }"
                >
                    <!-- Employee Context Header in Edit Modal -->
                    <div class="edit-employee-header mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md">
                                {{ getInitials(selectedApproval?.Nama) }}
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold text-dark">{{ selectedApproval?.Nama }}</h6>
                                <div class="employee-meta-badges">
                                    <span class="meta-badge job-class">
                                        <i class="bi bi-person-badge"></i>
                                        {{ selectedApproval?.Kode_Karyawan }}
                                    </span>
                                    <span class="meta-badge dept">
                                        <i class="bi bi-building"></i>
                                        {{ selectedApproval?.Department }}
                                    </span>
                                    <span class="meta-badge position">
                                        <i class="bi bi-briefcase"></i>
                                        {{ selectedApproval?.Jabatan }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-info-circle-fill flex-shrink-0 fs-5 text-info"></i>
                        <div>
                            <strong>Edit Performance:</strong> Anda dapat mengubah nilai actual dan menambahkan PICA sebelum menyetujui.
                        </div>
                    </div>

                    <!-- Summary dan total -->
                    <div class="edit-summary-sticky-bar mb-3">
                        <div class="performance-summary">
                            <div class="summary-card card-achievement">
                                <div class="summary-icon score">
                                    <i class="bi bi-speedometer2"></i>
                                </div>
                                <div class="summary-content">
                                    <div class="summary-value">
                                        {{ formatAchievement(cappedAchievement) }}%
                                        <span
                                            v-if="isScoreCapped"
                                            class="cap-indicator"
                                            :title="capNote"
                                        >
                                            <i class="bi bi-info-circle-fill"></i>
                                        </span>
                                    </div>
                                    <div class="summary-label">Achievement</div>
                                </div>
                            </div>
                            <div class="summary-card card-score">
                                <div class="summary-icon kpi">
                                    <i class="bi bi-award"></i>
                                </div>
                                <div class="summary-content">
                                    <div class="summary-value">
                                        {{
                                            formatAchievement(
                                                Number(
                                                    selectedApproval?.total_score,
                                                ),
                                            )
                                        }}%
                                    </div>
                                    <div class="summary-label">Total Score</div>
                                </div>
                            </div>
                            <div class="summary-card card-pica">
                                <div class="summary-icon pica">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <div class="summary-content">
                                    <div class="summary-value">
                                        {{ selectedApproval?.pica_count }}
                                    </div>
                                    <div class="summary-label">PICA Items</div>
                                </div>
                            </div>
                        </div>

                        <!-- Capped Note in Edit Modal -->
                        <div
                            v-if="isScoreCapped && capNote"
                            class="cap-note-banner mt-2"
                        >
                            <div class="cap-note-icon-wrap">
                                <i class="bi bi-shield-exclamation"></i>
                            </div>
                            <div class="cap-note-text">
                                <span class="cap-note-title">Penyesuaian Batas Capaian:</span>
                                <span class="cap-note-desc">{{ capNote }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="edit-kpi-list">
                        <div
                            v-for="(kpi, index) in selectedApproval?.kpiDetails"
                            :key="kpi.id"
                            class="edit-kpi-item"
                            :class="{ 'kpi-disabled': kpi.Is_Disabled === 'Y' }"
                        >
                            <!-- Tambahkan badge disabled -->
                            <div
                                v-if="kpi.Is_Disabled === 'Y'"
                                class="disabled-badge"
                            >
                                <i class="bi bi-slash-circle"></i>
                                KPI ini tidak aktif di periode ini
                            </div>

                            <!-- Section PICA yang dimodifikasi -->
                            <div
                                class="pica-edit-section"
                                v-if="
                                    shouldShowPicaSection(index, kpi) ||
                                    editPicaFlags[index]?.shouldRemovePica
                                "
                            >
                                <!-- Tampilkan pesan berbeda untuk PICA yang akan dihapus -->
                                <div
                                    class="pica-toggle"
                                    v-if="
                                        editPicaFlags[index]?.shouldRemovePica
                                    "
                                >
                                    <span class="pica-success">
                                        <i class="bi bi-check-circle"></i>
                                        Target tercapai - PICA akan dihapus
                                        otomatis
                                    </span>
                                </div>
                            </div>

                            <div class="edit-kpi-header">
                                <h6>{{ kpi.parameter }}</h6>
                                <div class="kpi-meta">
                                    <span
                                        class="badge d-flex justify-content-center align-items-center"
                                        >{{ kpi.bsc_category }}</span
                                    >
                                    <div
                                        class="achievement-badge small d-flex align-items-center justify-content-center achievement-good"
                                    >
                                        <i
                                            class="d-flex me-2 justify-content-center align-items-center bi bi-list-ol"
                                        ></i>
                                        {{ kpi.weight }}%
                                    </div>
                                    <div
                                        class="achievement-badge small d-flex align-items-center justify-content-center"
                                        :class="
                                            getAchievementClass(kpi.achievement)
                                        "
                                    >
                                        <i
                                            class="d-flex me-2 justify-content-center align-items-center bi bi-trophy"
                                        ></i>
                                        {{
                                            formatAchievement(kpi.achievement)
                                        }}%
                                    </div>
                                </div>
                            </div>

                            <!-- Form fields untuk edit KPI -->
                            <div
                                class="edit-kpi-content"
                                v-if="
                                    kpi.Is_Disabled === 'T' &&
                                    editKpiData[index]
                                "
                            >
                                <!-- Form fields untuk edit KPI -->
                                <div class="edit-kpi-content">
                                    <div class="row g-3">
                                        <!-- Target Value (Readonly) -->
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">
                                                    <i
                                                        class="bi bi-bullseye me-2"
                                                    ></i>
                                                    Target Value
                                                </label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    :value="
                                                        formatValueForDisplay(
                                                            kpi.Nilai_Target,
                                                            kpi.unit,
                                                        )
                                                    "
                                                    readonly
                                                />
                                            </div>
                                        </div>

                                        <!-- Unit (Readonly) -->
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">
                                                    <i
                                                        class="bi bi-rulers me-2"
                                                    ></i>
                                                    Unit
                                                </label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    :value="kpi.unit"
                                                    readonly
                                                />
                                            </div>
                                        </div>

                                        <!-- Actual Value (Editable) -->
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">
                                                    <i
                                                        class="bi bi-graph-up me-2"
                                                    ></i>
                                                    Actual Value *
                                                </label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    v-model="
                                                        editKpiData[index]
                                                            .actual_value
                                                    "
                                                    :placeholder="`Masukkan actual ${kpi.unit}`"
                                                    @input="
                                                        handleActualValueInput(
                                                            $event,
                                                            index,
                                                            kpi,
                                                        )
                                                    "
                                                    @blur="
                                                        formatActualValue(
                                                            index,
                                                            kpi,
                                                        )
                                                    "
                                                    @keypress="
                                                        allowNumericOnly($event)
                                                    "
                                                />
                                            </div>
                                        </div>

                                        <!-- Previous Actual (Readonly) -->
                                        <div class="col-12 col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">
                                                    <i
                                                        class="bi bi-clock-history me-2"
                                                    ></i>
                                                    Previous Actual
                                                </label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    :value="
                                                        formatValueForDisplay(
                                                            kpi.actual_value,
                                                            kpi.unit,
                                                        )
                                                    "
                                                    readonly
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Data Source Section (lihat + ganti; tidak boleh kosong) -->
                                <div
                                    class="data-source-edit-section"
                                    v-if="
                                        kpi.Is_Disabled === 'T' &&
                                        editDataSource[index]
                                    "
                                >
                                    <label class="data-source-label">
                                        <i class="bi bi-paperclip"></i>
                                        Data Source
                                    </label>

                                    <div
                                        class="data-source-card"
                                        :class="{
                                            'is-replaced':
                                                editDataSource[index].newFile,
                                            'has-error':
                                                editDataSource[index].error,
                                        }"
                                    >
                                        <div class="ds-fileinfo">
                                            <span class="ds-icon">
                                                <i
                                                    class="bi"
                                                    :class="
                                                        editDataSource[index]
                                                            .newFile
                                                            ? 'bi-file-earmark-arrow-up'
                                                            : 'bi-file-earmark-check'
                                                    "
                                                ></i>
                                            </span>
                                            <div class="ds-meta">
                                                <span class="ds-name">
                                                    {{
                                                        editDataSource[index]
                                                            .newFile
                                                            ? editDataSource[
                                                                  index
                                                              ].newFileName
                                                            : "File lampiran tersimpan"
                                                    }}
                                                </span>
                                                <span
                                                    class="ds-status"
                                                    :class="
                                                        editDataSource[index]
                                                            .newFile
                                                            ? 'text-success'
                                                            : 'text-muted'
                                                    "
                                                >
                                                    {{
                                                        editDataSource[index]
                                                            .newFile
                                                            ? "Akan diganti setelah disetujui"
                                                            : "Data source saat ini"
                                                    }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="ds-actions">
                                            <button
                                                type="button"
                                                v-if="
                                                    editDataSource[index]
                                                        .existingPath &&
                                                    !editDataSource[index]
                                                        .newFile
                                                "
                                                class="ds-btn ds-btn-view"
                                                :disabled="
                                                    downloadingStates[kpi.id] ===
                                                    'downloading'
                                                "
                                                @click="
                                                    getAttachmentUrl(
                                                        editDataSource[index]
                                                            .existingPath,
                                                        kpi.id,
                                                    )
                                                "
                                            >
                                                <i
                                                    class="bi bi-eye me-1"
                                                ></i>
                                                Lihat
                                            </button>

                                            <label
                                                v-if="
                                                    !editDataSource[index]
                                                        .newFile
                                                "
                                                class="ds-btn ds-btn-replace"
                                            >
                                                <i
                                                    class="bi bi-arrow-repeat me-1"
                                                ></i>
                                                Ganti Data Source
                                                <input
                                                    type="file"
                                                    class="d-none"
                                                    :key="`ds-${index}-${fileInputKey}`"
                                                    accept=".pdf,.jpg,.jpeg,.png"
                                                    @change="
                                                        onDataSourceChange(
                                                            index,
                                                            $event,
                                                        )
                                                    "
                                                />
                                            </label>

                                            <button
                                                type="button"
                                                v-if="
                                                    editDataSource[index].newFile
                                                "
                                                class="ds-btn ds-btn-view"
                                                @click="
                                                    previewNewDataSource(index)
                                                "
                                            >
                                                <i class="bi bi-eye me-1"></i>
                                                Lihat
                                            </button>

                                            <button
                                                type="button"
                                                v-if="
                                                    editDataSource[index].newFile
                                                "
                                                class="ds-btn ds-btn-cancel"
                                                @click="
                                                    cancelDataSourceChange(index)
                                                "
                                            >
                                                <i class="bi bi-x-lg me-1"></i>
                                                Batal
                                            </button>
                                        </div>
                                    </div>

                                    <small
                                        v-if="editDataSource[index].error"
                                        class="data-source-hint text-danger"
                                    >
                                        <i
                                            class="bi bi-exclamation-triangle-fill me-1"
                                        ></i>
                                        {{ editDataSource[index].error }}
                                    </small>
                                    <small
                                        v-else
                                        class="data-source-hint text-muted"
                                    >
                                        Maks 3MB — PDF, JPG, JPEG, PNG
                                    </small>
                                </div>

                                <!-- PICA Section -->
                                <div
                                    class="pica-edit-section"
                                    v-if="
                                        editPicaData[index] &&
                                        shouldShowPicaSection(index, kpi)
                                    "
                                >
                                    <div class="pica-toggle">
                                        <span class="pica-warning">
                                            <i
                                                class="bi bi-exclamation-triangle"
                                            ></i>
                                            Current score di bawah Grading
                                            Threshold, wajib untuk menambahkan
                                            PICA
                                        </span>
                                    </div>

                                    <!-- Form fields untuk PICA -->
                                    <div class="pica-form">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label">
                                                        <i
                                                            class="bi bi-search me-2"
                                                        ></i>
                                                        Problem Identification *
                                                    </label>
                                                    <textarea
                                                        class="form-control"
                                                        rows="3"
                                                        v-model="
                                                            editPicaData[index]
                                                                .problem_identification
                                                        "
                                                        placeholder="Jelaskan akar masalah yang ditemukan..."
                                                    ></textarea>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label">
                                                        <i
                                                            class="bi bi-tools me-2"
                                                        ></i>
                                                        Corrective Actions *
                                                    </label>
                                                    <textarea
                                                        class="form-control"
                                                        rows="3"
                                                        v-model="
                                                            editPicaData[index]
                                                                .corrective_actions
                                                        "
                                                        placeholder="Jelaskan langkah-langkah perbaikan segera..."
                                                    ></textarea>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">
                                                        <i
                                                            class="bi bi-calendar-check me-2"
                                                        ></i>
                                                        Due Date *
                                                    </label>
                                                    <input
                                                        type="date"
                                                        class="form-control"
                                                        v-model="
                                                            editPicaData[index]
                                                                .due_date
                                                        "
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Calculated Metrics Display -->
                                <div class="calculated-metrics mt-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="metric-display">
                                                <label>Achievement</label>
                                                <div class="metric-value score">
                                                    {{
                                                        getCalculatedScore(
                                                            index,
                                                            kpi,
                                                        )
                                                    }}%
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="metric-display">
                                                <label>Current Score</label>
                                                <div
                                                    class="achievement-badge"
                                                    :class="
                                                        getAchievementClass(
                                                            getCalculatedAchievement(
                                                                index,
                                                                kpi,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    {{
                                                        getCalculatedAchievement(
                                                            index,
                                                            kpi,
                                                        )
                                                    }}%
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tampilkan pesan untuk KPI disabled -->
                            <div v-else class="disabled-message">
                                <p>
                                    <i class="bi bi-info-circle"></i> KPI ini
                                    dinonaktifkan untuk periode ini dan tidak
                                    akan mempengaruhi perhitungan total.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <button
                        class="btn-modern btn-secondary"
                        @click="closeEditModal"
                    >
                        <i
                            class="d-flex justify-content-center align-items-center bi bi-x-circle"
                        ></i>
                        Batal
                    </button>
                    <button
                        class="btn-modern btn-success"
                        @click="confirmEditAndApprove"
                        :disabled="loading.edit || !isFormEditValid"
                    >
                        <i
                            v-if="loading.edit"
                            class="spinner-border spinner-border-sm me-2"
                        ></i>
                        <i
                            v-else
                            class="d-flex justify-content-center align-items-center bi bi-check-lg me-2"
                        ></i>
                        {{ loading.edit ? "Processing..." : "Save & Approve" }}
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Approve Modal -->
        <Modal
            v-if="showApproveModal"
            :show="showApproveModal"
            :title="`Approve Performance - ${selectedApproval?.employee?.Nama || selectedApproval?.Nama || ''}`"
            size="medium"
            @close="closeApproveModal"
        >
            <template #body>
                <div class="approve-modal-content">
                    <div class="approval-confirmation">
                        <div class="confirm-icon-wrap mb-3">
                            <div class="confirm-icon-circle success">
                                <i class="bi bi-check-lg"></i>
                            </div>
                        </div>
                        <h5 class="confirm-title">Konfirmasi Persetujuan</h5>
                        <p class="confirm-desc">
                            Apakah Anda yakin ingin menyetujui pengajuan performance ini?
                        </p>
                        <div class="approval-summary">
                            <div class="summary-item">
                                <span class="summary-key"><i class="bi bi-person me-2"></i>Karyawan</span>
                                <span class="summary-val">{{ selectedApproval?.employee?.Nama || selectedApproval?.Nama || '-' }}</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-key"><i class="bi bi-calendar-range me-2"></i>Periode</span>
                                <span class="summary-val">{{ convertPeriode(selectedApproval?.Periode) || '-' }}</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-key"><i class="bi bi-speedometer2 me-2"></i>Achievement</span>
                                <span class="summary-val text-success fw-bold">
                                    {{ formatAchievement(selectedApproval?.total_score) }}%
                                </span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-key"><i class="bi bi-exclamation-triangle me-2"></i>PICA Items</span>
                                <span class="summary-val" :class="selectedApproval?.pica_count > 0 ? 'text-warning fw-bold' : ''">
                                    {{ selectedApproval?.pica_count || 0 }} Item
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <button
                        class="btn-modern btn-secondary"
                        @click="closeApproveModal"
                    >
                        <i
                            class="d-flex justify-content-center align-items-center bi bi-x-circle"
                        ></i>
                        Batal
                    </button>
                    <button
                        class="btn-modern btn-success"
                        @click="confirmApprove"
                        :disabled="loading.approve"
                    >
                        <i
                            v-if="loading.approve"
                            class="spinner-border spinner-border-sm me-2"
                        ></i>
                        <i
                            v-else
                            class="d-flex justify-content-center align-items-center bi bi-check-lg me-2"
                        ></i>
                        {{
                            loading.approve
                                ? "Processing..."
                                : "Setujui Pengajuan"
                        }}
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Bulk Reject Modal -->
        <Modal
            v-if="showBulkRejectModal"
            :show="showBulkRejectModal"
            title="Reject Selected Submissions"
            size="medium"
            @close="closeBulkRejectModal"
        >
            <template #body>
                <div class="bulk-reject-content">
                    <div class="modal-section">
                        <div class="bulk-reject-info text-center">
                            <div class="confirm-icon-wrap mb-3">
                                <div class="confirm-icon-circle danger">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                            </div>
                            <h5 class="confirm-title">
                                Tolak {{ selectedApprovals.length }} Pengajuan Terpilih
                            </h5>
                            <p class="confirm-desc">
                                Pengajuan yang dipilih berikut akan dikembalikan ke karyawan:
                            </p>

                            <div class="selected-list">
                                <div
                                    v-for="id in selectedApprovals"
                                    :key="id"
                                    class="selected-item"
                                >
                                    <i class="bi bi-file-earmark-person me-2 text-primary"></i>
                                    <strong>{{ getApprovalById(id)?.No_Transaksi }}</strong>
                                    <span class="mx-1 text-muted">•</span>
                                    <span>{{ getApprovalById(id)?.employee?.Nama || getApprovalById(id)?.Nama }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-3">
                            <label class="reject-field-label">
                                Alasan Penolakan
                                <span class="reject-req">wajib</span>
                            </label>
                            <textarea
                                v-model="bulkRejectionReason"
                                class="reject-textarea"
                                rows="3"
                                placeholder="Jelaskan alasan penolakan untuk semua pengajuan terpilih..."
                                :class="{ 'is-invalid': bulkRejectionError }"
                            ></textarea>
                            <div
                                v-if="bulkRejectionError"
                                class="reject-error"
                            >
                                <i class="bi bi-exclamation-circle me-1"></i>
                                {{ bulkRejectionError }}
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <button
                        class="btn-modern btn-secondary"
                        @click="closeBulkRejectModal"
                    >
                        <i
                            class="bi bi-x-circle me-2 d-flex justify-content-center align-items-center"
                        ></i>
                        Batal
                    </button>
                    <button
                        class="btn-modern btn-danger"
                        @click="confirmBulkReject"
                        :disabled="loading.bulkReject"
                    >
                        <i
                            v-if="loading.bulkReject"
                            class="spinner-border spinner-border-sm me-2"
                        ></i>
                        <i
                            v-else
                            class="bi bi-x-lg me-2 d-flex justify-content-center align-items-center"
                        ></i>
                        {{
                            loading.bulkReject ? "Processing..." : "Tolak Semua"
                        }}
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import { ElMessage } from "element-plus";
import quickStat from "@/components/legacy/quickStat.vue";
import ApprovalTabs from "../../components/ui/toggleSection.vue";
import axios from "axios";
import { BATAS_BAKU, batasDariConfig, hitungMetrikParameter } from '@/Pages/KPI/utils/kpiCap';
import Modal from "@/components/legacy/modalV2-noir.vue";
import { tanggalPanjang } from '@/utils/tanggal';
import { hitungRumus } from '@/utils/rumusKpi';

export default {
    name: "VerifikasiPerformancePanel",
    components: {
        quickStat,
        ApprovalTabs,
        Modal,
    },
    props: {
        dataPerformance: { type: Number, default: 100 },
        embedded: { type: Boolean, default: false },
        mode: { type: String, default: "queue" },
    },
    data() {
        return {
            totalApprove: 0,
            totalPending: 0,
            totalReject: 0,
            gradingThreshold: 100,
            originalApprovalData: null,
            originalKpiData: [],
            originalPicaData: [],
            editKpiData: [],
            editPicaData: [],
            editPicaFlags: [],
            editDataSource: [],
            // Dinaikkan untuk memaksa <input type=file> me-render ulang (mengosongkan
            // pilihan file) saat verifikator membatalkan penggantian lampiran.
            fileInputKey: 0,
            editCalculatedMetrics: {},
            currentDate: new Date(),
            approvals: [],
            approvalsPagination: {
                currentPage: 1,
                totalPages: 0,
                totalDatas: 0,
                startItem: null,
                endItem: null,
                nextLink: null,
                prevLink: null,
                partPagin: null,
            },
            historyApprovals: [],
            historyApprovalsPagination: {
                currentPage: 1,
                totalPages: 0,
                totalDatas: 0,
                startItem: null,
                endItem: null,
                nextLink: null,
                prevLink: null,
                partPagin: null,
            },
            selectedApprovals: [],
            downloadingStates: {},
            loading: {
                fetch: false,
                history: false,
                approve: false,
                reject: false,
                edit: false,
                bulkReject: false,
                detail: false,
                attachment: false,
            },
            showDetailModalFlag: false,
            showRejectModal: false,
            showApproveModal: false,
            showEditModal: false,
            showBulkRejectModal: false,
            selectedApproval: null,
            rejectionReason: "",
            bulkRejectionReason: "",
            rejectionError: "",
            bulkRejectionError: "",
            rejectionKpiSelection: [],
            rejectionComments: [],
            isMobile: window.innerWidth < 768,
            activeTab: this.mode === "history" ? "history" : "queue",
            approvalTabs: this.embedded
                ? [
                      {
                          id: this.mode === "history" ? "history" : "queue",
                          label:
                              this.mode === "history"
                                  ? "Riwayat Verifikasi Performance"
                                  : "Daftar Verifikasi Performance",
                          icon:
                              this.mode === "history"
                                  ? "bi bi-clock-history d-flex justify-content-center align-items-center"
                                  : "bi bi-inbox d-flex justify-content-center align-items-center",
                          count: 0,
                      },
                  ]
                : [
                      {
                          id: "queue",
                          label: "Approval Queue",
                          icon: "bi bi-inbox d-flex justify-content-center align-items-center",
                          count: 0,
                      },
                      {
                          id: "history",
                          label: "Approval History",
                          icon: "bi bi-clock-history d-flex justify-content-center align-items-center",
                          count: 0,
                      },
                  ],
            filters: {
                search: "",
                periode: "",
                division: "",
                level: "",
            },
            historyFilters: {
                search: "",
                periode: "",
                division: "",
                level: "",
                status: "",
            },
            filterOptions: {
                levels: [],
                divisions: [],
            },
            searchTimeout: null,
            hasApprovalSchedule: true,
            kpiMaxScore: 125,
            kpiMinScore: 50,
            batasParameter: { ...BATAS_BAKU },
        };
    },
    computed: {
        isFormEditValid() {
            // Guard: jika tidak ada data approval atau kpiDetails, return true (tidak invalid)
            if (
                !this.selectedApproval?.kpiDetails ||
                this.selectedApproval.kpiDetails.length === 0
            ) {
                return true;
            }

            // Guard: jika editKpiData atau editPicaData belum diinisialisasi, return true
            if (
                !this.editKpiData ||
                this.editKpiData.length === 0 ||
                !this.editPicaData ||
                this.editPicaData.length === 0 ||
                !this.editPicaFlags ||
                this.editPicaFlags.length === 0
            ) {
                return true;
            }

            // Lampiran yang ditolak client-side (ukuran/ekstensi) memblokir submit.
            if (this.editDataSource.some((ds) => ds && ds.error)) {
                return false;
            }

            const hasInvalid = this.selectedApproval.kpiDetails.some(
                (kpi, index) => {
                    // ⛔ Abaikan KPI yang disabled
                    if (kpi.Is_Disabled === "Y") {
                        return false; // dianggap valid, lanjut ke item berikutnya
                    }

                    // Guard check untuk data index
                    if (!this.editKpiData[index] || !this.editPicaData[index]) {
                        return false;
                    }

                    // Cek apakah harus tampilkan PICA
                    const needPica = this.shouldShowPicaSection(index, kpi);

                    // Jika tidak butuh PICA → valid
                    if (!needPica) {
                        return false;
                    }

                    // Ambil data PICA
                    const pica = this.editPicaData[index] || {};

                    // Validasi field PICA
                    const isEmpty =
                        !pica.problem_identification?.trim() ||
                        !pica.corrective_actions?.trim() ||
                        !pica.due_date;

                    // true → ketemu yang invalid → .some berhenti
                    return isEmpty;
                },
            );

            // console.log(hasInvalid);
            return !hasInvalid;
        },
        cappedAchievement() {
            if (!this.selectedApproval) return 0;
            // Detail modal: pakai total_score (achievement % dari API)
            // Edit modal: pakai achievement (dihitung ulang oleh updateSummaryTotals)
            const raw = this.selectedApproval.achievement ?? this.selectedApproval.total_score;
            if (raw === null || raw === undefined || isNaN(raw)) return 0;
            if (Number(raw) === 0) return 0;
            return Math.min(Math.max(Number(raw), this.kpiMinScore), this.kpiMaxScore);
        },
        /**
         * Cacah parameter yang nilainya dipangkas plafon atau diangkat lantai.
         *
         * Dua sumber, karena modalnya dua rupa: saat approver MENGEDIT angkanya
         * dihitung ulang di klien (editCalculatedMetrics), saat modal hanya
         * DIBACA angkanya datang dari server (flag_capped per detail).
         */
        jumlahParameterTerjepit() {
            return (this.selectedApproval?.kpiDetails || []).reduce((cacah, kpi, index) => {
                if (kpi?.Is_Disabled !== 'T') return cacah;

                // Fallback PER BARIS, pola yang sama dengan updateSummaryTotals:
                // calculateEditMetrics bekerja per baris dan MENGHAPUS entrinya
                // saat isian dikosongkan. Memilih satu sumber untuk seluruh baris
                // akan salah di kedua arah — baris yang belum disentuh kehilangan
                // jejak servernya, dan baris yang sudah diperbaiki approver tetap
                // dihitung terjepit karena barisnya di database belum berubah.
                const hasilEdit = this.editCalculatedMetrics?.[index];
                const terjepit = hasilEdit ? !!hasilEdit.terjepit : kpi?.flag_capped === 'Y';

                return terjepit ? cacah + 1 : cacah;
            }, 0);
        },
        /**
         * Sejak penjepitan pindah ke tingkat parameter, perbandingan di tingkat
         * TOTAL praktis tidak pernah menyala: rata-rata tertimbang dari nilai
         * yang semuanya sudah di dalam rentang pasti ikut di dalam rentang.
         * Kalau keterangan ini tetap bersandar pada total saja, approver tidak
         * akan pernah diberi tahu bahwa ada angka yang disesuaikan.
         *
         * Perbandingan total dipertahankan sebagai jaring pengaman untuk record
         * beraturan lama dan untuk keadaan batas per-parameter dimatikan.
         */
        isScoreCapped() {
            if (!this.selectedApproval) return false;
            if (this.jumlahParameterTerjepit > 0) return true;

            const raw = this.selectedApproval.achievement ?? this.selectedApproval.total_score;
            if (raw === null || raw === undefined || isNaN(raw)) return false;
            if (Number(raw) <= 0) return false;
            const capped = this.selectedApproval.capped_achievement ?? this.cappedAchievement;
            return Math.abs(Number(raw) - Number(capped)) > 0.01;
        },
        capNote() {
            if (!this.isScoreCapped) return '';

            const n = this.jumlahParameterTerjepit;
            if (n > 0) {
                const batas = `${this.batasParameter.min}%–${this.batasParameter.max}%`;
                return `${n} parameter disesuaikan ke batas ${batas}. Nilai sebelum penyesuaian tetap tersimpan sebagai jejak audit.`;
            }

            const raw = this.selectedApproval.achievement ?? this.selectedApproval.total_score;
            const capped = this.selectedApproval.capped_achievement ?? this.cappedAchievement;
            const rawFmt = this.formatAchievement(Number(raw));
            const capFmt = this.formatAchievement(Number(capped));
            if (Number(raw) > this.kpiMaxScore) {
                return `Skor (${rawFmt}%) melebihi batas maksimum ${this.kpiMaxScore}% sehingga disesuaikan menjadi ${capFmt}%.`;
            }
            return `Skor (${rawFmt}%) berada di bawah batas minimum ${this.kpiMinScore}% sehingga disesuaikan menjadi ${capFmt}%.`;
        },
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? "Siang" : "Malam";
        },
        quickStatField() {
            return [
                {
                    title: "Pending",
                    value: this.pendingCount,
                    unit: "",
                    icon: "bi bi-clock-history",
                    bgIcon: "",
                    size: "col-4",
                },
                {
                    title: "Approved",
                    value: this.approvedCount,
                    unit: "",
                    icon: "bi bi-check-circle",
                    bgIcon: "",
                    size: "col-4",
                },
                {
                    title: "Rejected",
                    value: this.rejectedCount,
                    unit: "",
                    icon: "bi bi-x-circle",
                    bgIcon: "",
                    size: "col-4",
                },
            ];
        },
        pendingCount() {
            return this.totalPending;
        },
        approvedCount() {
            return this.totalApprove;
        },
        rejectedCount() {
            return this.totalReject;
        },
        filteredApprovals() {
            return this.approvals.filter(
                (approval) => approval.Flag_Approval === null,
            );
        },
    },
    methods: {
        async getAttachmentUrl(attachmentPath, kpiId) {
            if (!attachmentPath) {
                ElMessage({
                    message: "Tidak ada lampiran untuk ditampilkan.",
                    type: "warning",
                });
                return;
            }

            // Set state to downloading
            this.downloadingStates[kpiId] = "downloading";
            // console.log(this.downloadingStates);
            this.loading.attachment = true;

            try {
                const response = await axios.post(
                    "/api/v1/kpi-verifikasi/performance/approvals/getAttachmentUrl",
                    {
                        path: attachmentPath,
                    },
                    {
                        responseType: "blob",
                    },
                );

                if (response.status === 200) {
                    // Buat blob URL untuk download
                    const blob = new Blob([response.data]);
                    const url = window.URL.createObjectURL(blob);

                    // Extract filename dari path
                    const fileName = attachmentPath.substring(
                        attachmentPath.lastIndexOf("/") + 1,
                    );

                    // Trigger download
                    const downloadLink = document.createElement("a");
                    downloadLink.href = url;
                    downloadLink.download = fileName;
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
                    document.body.removeChild(downloadLink);

                    window.URL.revokeObjectURL(url);

                    // Set state to downloaded
                    this.downloadingStates[kpiId] = "downloaded";

                    ElMessage({
                        message: "File berhasil didownload.",
                        type: "success",
                    });

                    // Reset to idle state after 3 seconds
                    setTimeout(() => {
                        this.downloadingStates[kpiId] = "idle";
                    }, 5000);
                }
            } catch (error) {
                console.error("Error downloading file:", error);

                // Reset state on error
                this.downloadingStates[kpiId] = "idle";

                ElMessage({
                    message: "Terjadi kesalahan saat mendownload file.",
                    type: "error",
                });
            } finally {
                this.loading.attachment = false;
            }
        },
        allowNumericOnly(event) {
            const char = event.key;

            const allowed =
                /[0-9.-]/.test(char) ||
                [
                    "Backspace",
                    "Delete",
                    "ArrowLeft",
                    "ArrowRight",
                    "Tab",
                ].includes(char);

            if (!allowed) {
                event.preventDefault();
            }

            if (char === "," && event.target.value.includes(",")) {
                event.preventDefault();
            }

            if (
                char === "-" &&
                (event.target.value.includes("-") ||
                    event.target.selectionStart !== 0)
            ) {
                event.preventDefault();
            }
        },
        formatScore(value) {
            // console.log(value);
            if (typeof value !== "number" || !isFinite(value)) {
                return 0;
            }

            // Pastikan pembulatan 1 desimal benar-benar akurat
            const rounded = Math.round((value + Number.EPSILON) * 10) / 10;

            if (Number.isInteger(rounded)) {
                return rounded;
            }

            return rounded;
        },
        convertPeriode(dateStr) {
            if (!dateStr) return "";
            const [tahun, bulan] = dateStr.split("-");

            const namaBulan = [
                "Januari",
                "Februari",
                "Maret",
                "April",
                "Mei",
                "Juni",
                "Juli",
                "Agustus",
                "September",
                "Oktober",
                "November",
                "Desember",
            ];

            const bulanNama = namaBulan[parseInt(bulan) - 1];

            return `${bulanNama} ${tahun}`;
        },
        convertToInputDate(datetimeStr) {
            if (!datetimeStr) return "";

            const [datePart] = datetimeStr.split(" ");

            const parts = datePart.split("-");
            if (parts.length !== 3) return "";

            const [year, month, day] = parts;
            return `${year}-${month.padStart(2, "0")}-${day.padStart(2, "0")}`;
        },
        formatValueForDisplay(value, unit) {
            if (!value) return "";

            const format = this.getInputFormat(unit);
            return this.convertValueUnit(value, format, unit);
        },

        handleActualValueInput(event, index, kpi) {
            const value = event.target.value;

            if (this.getInputFormat(kpi.unit) === "currency") {
                const numericValue = this.parseActualValue(value, kpi.unit);
                if (numericValue !== null) {
                    const formattedValue = this.formatCurrencyRealTime(
                        numericValue,
                        kpi.unit,
                    );
                    this.editKpiData[index].actual_value = formattedValue;
                } else {
                    this.editKpiData[index].actual_value = value;
                }
            } else {
                this.editKpiData[index].actual_value = value;
            }

            // Hitung ulang metrics secara real-time
            this.calculateEditMetrics(index, kpi);
        },
        formatCurrencyRealTime(value, unit) {
            if (!value && value !== 0) return "";

            const numericValue = parseFloat(value) || 0;
            const formatted = new Intl.NumberFormat("id-ID").format(
                numericValue,
            );

            // Satuan di depan seperti di gambar
            return `${this.getUnitSymbol(unit)} ${formatted}`;
        },

        getUnitSymbol(unit) {
            const unitStr = String(unit).toLowerCase();
            if (unitStr === "rp" || unitStr === "idr" || unitStr === "rupiah") {
                return "Rp";
            } else if (unitStr === "%" || unitStr === "persen") {
                return "%";
            } else {
                return unitStr;
            }
        },

        // Format actual value saat blur
        // formatActualValue(index, kpi) {
        //     const value = this.editKpiData[index].actual_value;
        //     if (!value) return;

        //     const numericValue = this.parseActualValue(value, kpi.unit);
        //     if (numericValue !== null) {
        //         const format = this.getInputFormat(kpi.unit);
        //         this.editKpiData[index].actual_value = this.convertValueUnit(
        //             numericValue,
        //             format,
        //             kpi.unit
        //         );
        //     }

        //     // Update calculations setelah format
        //     this.calculateEditMetrics(index, kpi);
        // },

        formatActualValue(index, kpi) {
            const value = this.editKpiData[index].actual_value;

            // UBAH BARIS INI:
            // if (!value) return;

            // MENJADI:
            if (value === null || value === undefined || value === "") return;

            const numericValue = this.parseActualValue(value, kpi.unit);

            // Ubah logika pengecekan numericValue agar 0 tidak dianggap false
            if (numericValue !== null) {
                const format = this.getInputFormat(kpi.unit);
                this.editKpiData[index].actual_value = this.convertValueUnit(
                    numericValue,
                    format,
                    kpi.unit,
                );
            }

            this.calculateEditMetrics(index, kpi);
        },

        getCalculatedScore(index, kpi) {
            // Cek apakah ada nilai di editCalculatedMetrics
            const calculated = this.editCalculatedMetrics[index]?.score;

            // JANGAN PAKE ||. Gunakan pengecekan undefined/null
            // Jika calculated ada nilainya (termasuk 0), pakai itu. Jika tidak, pakai kpi.score
            const score =
                calculated !== undefined && calculated !== null
                    ? calculated
                    : kpi.score || 0;

            return this.formatAchievement(Number(score));
        },

        getCalculatedAchievement(index, kpi) {
            const calculated = this.editCalculatedMetrics[index]?.achievement;

            // Perbaikan logika yang sama
            const achievement =
                calculated !== undefined && calculated !== null
                    ? calculated
                    : kpi.achievement || 0;

            return this.formatAchievement(achievement);
        },

        formatAchievement(value) {
            const num = Number(value);
            if (isNaN(num) || !isFinite(num)) {
                return 0;
            }
            return num % 1 === 0 ? num : num.toFixed(1);
        },

        // initializeEditData() {
        //     if (!this.selectedApproval.kpiDetails) {
        //         return;
        //     }

        //     console.log(
        //         "🔄 initializeEditData:",
        //         this.selectedApproval.kpiDetails
        //     );

        //     this.editKpiData = [];
        //     this.editPicaData = [];
        //     this.editCalculatedMetrics = {};

        //     this.selectedApproval.kpiDetails.forEach((kpi, index) => {
        //         // Initialize KPI data dengan format yang sesuai
        //         const initialActualValue = kpi.actual_value || "";
        //         const formattedActualValue = initialActualValue
        //             ? this.formatValueForDisplay(initialActualValue, kpi.unit)
        //             : "";

        //         this.editKpiData[index] = {
        //             actual_value: formattedActualValue,
        //             unit: kpi.unit || "",
        //             target_value: kpi.Nilai_Target || 0,
        //             previous_actual: Number(kpi.actual_value) || "",
        //         };

        //         // Initialize PICA data
        //         this.editPicaData[index] = {
        //             problem_identification:
        //                 kpi.pica?.[0]?.problem_identification || "",
        //             corrective_actions: kpi.pica?.[0]?.corrective_actions || "",
        //             due_date:
        //                 kpi.pica?.[0]?.due_date || this.getDefaultDueDate(),
        //         };

        //         // Initialize calculated metrics
        //         this.editCalculatedMetrics[index] = {
        //             achievement: Number(kpi.achievement || 0),
        //             score: Number(kpi.score || 0),
        //         };

        //         // Calculate initial metrics
        //         this.calculateEditMetrics(index, kpi);
        //     });

        //     // Force reactivity
        //     this.editKpiData = [...this.editKpiData];
        //     this.editPicaData = [...this.editPicaData];
        //     this.editCalculatedMetrics = { ...this.editCalculatedMetrics };
        // },
        initializeEditData() {
            if (!this.selectedApproval?.kpiDetails) {
                return;
            }

            this.editKpiData = [];
            this.editPicaData = [];
            this.editPicaFlags = []; // Initialize flags
            this.editDataSource = [];
            this.editCalculatedMetrics = {};

            this.selectedApproval?.kpiDetails.forEach((kpi, index) => {
                // Initialize KPI data
                const initialActualValue = kpi.actual_value || "";
                const formattedActualValue = initialActualValue
                    ? this.formatValueForDisplay(initialActualValue, kpi.unit)
                    : "";
                this.editKpiData[index] = {
                    actual_value: formattedActualValue,
                    unit: kpi.unit || "",
                    target_value: kpi.Nilai_Target || 0,
                    previous_actual: Number(kpi.actual_value) || "",
                    Is_Disabled: kpi.Is_Disabled || "Y",
                };

                // Initialize PICA data
                this.editPicaData[index] = {
                    problem_identification:
                        kpi.pica?.[0]?.problem_identification || "",
                    corrective_actions: kpi.pica?.[0]?.corrective_actions || "",
                    due_date:
                        this.convertToInputDate(kpi.pica?.[0]?.due_date) ||
                        this.getDefaultDueDate(),
                };
                // console.log(this.editPicaData[index]);

                // Initialize PICA flags - true jika ada PICA sebelumnya
                this.editPicaFlags[index] = {
                    hasExistingPica: !!(kpi.pica && kpi.pica.length > 0),
                    shouldRemovePica: false, // Default false
                    picaRemovalNotified: false, // Untuk mencegah notifikasi berulang
                };

                // Initialize data source (lampiran bukti) per parameter.
                // Hanya lihat + ganti — data source tidak boleh dikosongkan.
                this.editDataSource[index] = {
                    existingPath: kpi.File_Berkas || "",
                    newFile: null,
                    newFileName: "",
                    previewUrl: null,
                    error: "",
                };

                // Initialize calculated metrics
                this.editCalculatedMetrics[index] = {
                    achievement: Number(kpi.achievement || 0),
                    score: Number(kpi.score || 0),
                };

                // Calculate initial metrics
                this.calculateEditMetrics(index, kpi);
            });

            // Force reactivity
            this.editKpiData = [...this.editKpiData];
            this.editPicaData = [...this.editPicaData];
            this.editPicaFlags = [...this.editPicaFlags];
            this.editDataSource = [...this.editDataSource];
            this.editCalculatedMetrics = { ...this.editCalculatedMetrics };
        },

        onDataSourceChange(index, event) {
            const file = event.target.files?.[0] || null;
            const entry = this.editDataSource[index];
            if (!entry) return;

            entry.error = "";
            entry.newFile = null;
            entry.newFileName = "";

            if (!file) {
                this.editDataSource = [...this.editDataSource];
                return;
            }

            const allowed = ["pdf", "jpg", "jpeg", "png"];
            const ext = file.name.split(".").pop()?.toLowerCase() || "";

            if (!allowed.includes(ext)) {
                entry.error = `Format .${ext} tidak diperbolehkan.`;
            } else if (file.size > 3 * 1024 * 1024) {
                entry.error = "Ukuran file melebihi 3MB.";
            } else {
                entry.newFile = file;
                entry.newFileName = file.name;
            }

            this.editDataSource = [...this.editDataSource];
        },

        cancelDataSourceChange(index) {
            const entry = this.editDataSource[index];
            if (!entry) return;

            if (entry.previewUrl) {
                URL.revokeObjectURL(entry.previewUrl);
                entry.previewUrl = null;
            }
            entry.newFile = null;
            entry.newFileName = "";
            entry.error = "";
            this.fileInputKey++; // paksa <input type=file> reset
            this.editDataSource = [...this.editDataSource];
        },

        // Preview file pengganti yang dipilih (masih lokal, belum ter-upload).
        // Object URL dibuat sekali lalu dipakai ulang; dibebaskan saat batal/tutup modal.
        previewNewDataSource(index) {
            const entry = this.editDataSource[index];
            if (!entry?.newFile) return;

            if (!entry.previewUrl) {
                entry.previewUrl = URL.createObjectURL(entry.newFile);
            }
            window.open(entry.previewUrl, "_blank");
        },

        revokeDataSourcePreviews() {
            (this.editDataSource || []).forEach((entry) => {
                if (entry?.previewUrl) {
                    URL.revokeObjectURL(entry.previewUrl);
                    entry.previewUrl = null;
                }
            });
        },

        shouldShowPicaSection(index, kpi) {
            // ⛔ Jangan tampilkan PICA untuk KPI yang disabled/tidak aktif
            if (kpi.Is_Disabled === "Y") {
                return false;
            }

            // 1. Ambil Achievement yang sudah dihitung
            const calculatedMetrics = this.editCalculatedMetrics[index];

            // Perbaikan Logika "Ghost 0":
            // Jika ada hasil hitungan (termasuk 0), pakai itu. Jika belum ada, pakai data lama.
            let currentScore =
                calculatedMetrics?.achievement !== undefined &&
                calculatedMetrics?.achievement !== null
                    ? calculatedMetrics.achievement
                    : kpi.achievement; // atau kpi.score, sesuaikan dengan logic bisnis (biasanya achievement yg di compare threshold)

            // Convert ke float agar aman
            currentScore = parseFloat(currentScore);

            // 2. Cek flag removal (dari logic sebelumnya)
            if (this.editPicaFlags[index]?.shouldRemovePica) {
                return false;
            }

            // console.log("🔍 shouldShowPicaSection:", {
            //     index,
            //     kpiId: kpi.id,
            //     currentScore,
            //     threshold: this.gradingThreshold,
            //     hasExistingPica: this.editPicaFlags[index]?.hasExistingPica,
            // });

            // 3. Logika PICA muncul:
            // Pastikan currentScore valid (bukan NaN) DAN kurang dari threshold
            return !isNaN(currentScore) && currentScore < this.gradingThreshold;
        },
        getDefaultDueDate() {
            const date = new Date();
            date.setMonth(date.getMonth() + 1);
            return date.toISOString().split("T")[0];
        },

        // calculateEditMetrics(index, kpi) {
        //     const actualValue = this.parseActualValue(
        //         this.editKpiData[index]?.actual_value,
        //         kpi.unit
        //     );

        //     console.log("🧮 calculateEditMetrics:", {
        //         index,
        //         actualValue,
        //         kpi,
        //         rumus: kpi.rumus,
        //     });

        //     if (actualValue === null || !kpi.rumus) {
        //         this.editCalculatedMetrics = {
        //             ...this.editCalculatedMetrics,
        //             [index]: {
        //                 achievement: 0,
        //                 score: 0,
        //             },
        //         };
        //         return;
        //     }

        //     try {
        //         // Dapatkan target value dari KPI
        //         const targetValue = parseFloat(kpi.Nilai_Target) || 0;

        //         console.log("🎯 Values:", {
        //             actualValue,
        //             targetValue,
        //             rumus: kpi.rumus,
        //         });

        //         // Ganti semua variabel dalam rumus
        //         let formula = kpi.rumus
        //             .replace(/actual/gi, actualValue) // ganti actual (case insensitive)
        //             .replace(/target/gi, targetValue) // ganti target (case insensitive)
        //             .replace(/\[actual\]/gi, actualValue) // ganti [actual]
        //             .replace(/\[target\]/gi, targetValue); // ganti [target]

        //         console.log("📐 Formula after replacement:", formula);

        //         // Validasi formula dasar
        //         if (!this.isValidFormula(formula)) {
        //             throw new Error("Invalid formula format");
        //         }

        //         let achievementRatio = new Function("return " + formula)();
        //         console.log("📊 Achievement ratio:", achievementRatio);

        //         if (!isFinite(achievementRatio)) {
        //             achievementRatio = 0;
        //         }

        //         // Perbaikan perhitungan score dan achievement
        //         const achievement = Number(achievementRatio * 100);
        //         const score = Number(kpi.weight * achievementRatio);

        //         console.log("🎯 Final calculated:", {
        //             achievement,
        //             score,
        //             weight: kpi.weight,
        //         });

        //         this.editCalculatedMetrics = {
        //             ...this.editCalculatedMetrics,
        //             [index]: {
        //                 achievement: achievement,
        //                 score: score,
        //             },
        //         };

        //         console.log(
        //             "✅ Updated metrics:",
        //             this.editCalculatedMetrics[index]
        //         );

        //         // Auto update summary totals
        //         this.updateSummaryTotals();
        //     } catch (e) {
        //         console.error("❌ Error calculating metrics:", e);
        //         this.editCalculatedMetrics = {
        //             ...this.editCalculatedMetrics,
        //             [index]: {
        //                 achievement: 0,
        //                 score: 0,
        //             },
        //         };
        //     }
        // },

        calculateEditMetrics(index, kpi) {
            const actualValue = this.parseActualValue(
                this.editKpiData[index]?.actual_value,
                kpi.unit,
            );

            // console.log("🧮 calculateEditMetrics:", {
            //     index,
            //     actualValue,
            //     kpi,
            //     rumus: kpi.rumus,
            // });

            if (actualValue === null || !kpi.rumus) {
                // Jangan set {achievement:0, score:0} karena 0 tidak nullish
                // sehingga fallback di updateSummaryTotals tidak akan terpakai.
                // Hapus entry agar fallback ke data API (kpi.achievement / kpi.score) bekerja.
                if (index in this.editCalculatedMetrics) {
                    const newMetrics = { ...this.editCalculatedMetrics };
                    delete newMetrics[index];
                    this.editCalculatedMetrics = newMetrics;
                }
                return;
            }

            try {
                const targetValue = parseFloat(kpi.Nilai_Target) || 0;

                // Ganti placeholder — urutan dari paling spesifik (bracket) ke
                // bentuk humanized tanpa bracket (rumus dari API sudah di-humanize
                // menjadi "actual"/"Target", mis. "actual/Target"). Tanpa 2 replace
                // word-boundary terakhir, formula humanized gagal di-eval -> 0.
                let formula = kpi.rumus
                    .replace(/\[actualKpiEvo\]/gi, `(${actualValue})`)
                    .replace(/\[targetKpiEvo\]/gi, `(${targetValue})`)
                    .replace(/\[actual\]/gi, `(${actualValue})`)
                    .replace(/\[target\]/gi, `(${targetValue})`)
                    .replace(/\bactual\b/gi, `(${actualValue})`)
                    .replace(/\bTarget\b/g, `(${targetValue})`);

                /*
                 * Daftar putih di klien, cermin KpiFormulaHelper::bentukAman di server.
                 * Sampai perubahan ini rumus dari basis data diserahkan langsung ke
                 * new Function tanpa validasi apa pun. hitungRumus mengembalikan null
                 * bila tidak aman, dan pemeriksaan isFinite di bawah sudah menurunkannya
                 * menjadi nol seperti sebelumnya.
                 */
                let achievementRatio = hitungRumus(formula);
                // console.log("📊 Achievement ratio:", achievementRatio);

                if (!isFinite(achievementRatio)) {
                    achievementRatio = 0;
                }

                const metrik = hitungMetrikParameter(achievementRatio, kpi.weight, this.batasParameter);
                const achievement = metrik.achievement;
                const score = metrik.score;
                // Jejak penjepitan ikut disimpan; tanpa ini approver melihat
                // angka yang sudah dipangkas tanpa tanda apa pun.
                const achievementRaw = metrik.achievementRaw;
                const terjepit = metrik.terjepit;

                // console.log("🎯 Final calculated:", {
                //     achievement,
                //     score,
                //     weight: kpi.weight,
                // });

                this.editCalculatedMetrics = {
                    ...this.editCalculatedMetrics,
                    [index]: {
                        achievement: achievement,
                        score: score,
                        achievementRaw: achievementRaw,
                        terjepit: terjepit,
                    },
                };

                this.checkPicaRemovalCondition(
                    index,
                    kpi,
                    actualValue,
                    targetValue,
                    achievement,
                );

                // Auto update summary totals
                this.updateSummaryTotals();
            } catch (e) {
                console.error("❌ Error calculating metrics:", e);
                this.editCalculatedMetrics = {
                    ...this.editCalculatedMetrics,
                    [index]: {
                        achievement: 0,
                        score: 0,
                    },
                };
            }
        },

        checkPicaRemovalCondition(
            index,
            kpi,
            actualValue,
            targetValue,
            achievement,
        ) {
            if (!this.editPicaFlags[index]) return;

            const hasExistingPica = this.editPicaFlags[index].hasExistingPica;
            const isAchievementMet = achievement >= 100;
            // const isActualAboveTarget = actualValue >= targetValue;

            // Jika ada PICA sebelumnya DAN achievement sudah tercapai
            if (hasExistingPica && isAchievementMet) {
                this.editPicaFlags[index].shouldRemovePica = true;

                // Tampilkan notifikasi hanya sekali
                if (!this.editPicaFlags[index].picaRemovalNotified) {
                    this.showPicaRemovalNotification(kpi.parameter);
                    this.editPicaFlags[index].picaRemovalNotified = true;
                }
            } else {
                // Reset flags jika kondisi tidak terpenuhi
                this.editPicaFlags[index].shouldRemovePica = false;
                this.editPicaFlags[index].picaRemovalNotified = false;
            }

            // console.log(`📝 PICA Status for ${kpi.parameter}:`, {
            //     hasExistingPica,
            //     shouldRemovePica: this.editPicaFlags[index].shouldRemovePica,
            //     achievement,
            //     actualValue,
            //     targetValue,
            // });
        },
        showPicaRemovalNotification(kpiName) {
            ElMessage({
                type: "success",
                message: `PICA untuk ${kpiName} akan dihapus karena target telah tercapai`,
                duration: 4000,
                showClose: true,
            });
        },
        // Tambahkan method untuk update summary totals
        updateSummaryTotals() {
            if (!this.selectedApproval.kpiDetails) return;

            let totalScore = 0;
            let totalAchievement = 0;
            let activeKpiCount = 0;
            let totalBobot = 0;
            let picaCount = 0;

            this.selectedApproval.kpiDetails.forEach((kpi, index) => {
                // Hanya hitung KPI yang aktif (Is_Disabled = 'T')
                if (kpi.Is_Disabled === "T") {
                    // NOTE: DB menyimpan Score = achievement % dan Achievement = weighted score.
                    // editCalculatedMetrics.score = weighted (weight * ratio)
                    // editCalculatedMetrics.achievement = percentage (ratio * 100)
                    // Jadi fallback-nya: score → kpi.achievement (weighted), achievement → kpi.score (%)
                    const calculatedScore =
                        this.editCalculatedMetrics[index]?.score ??
                        kpi.achievement ??
                        0;

                    const calculatedAchievement =
                        this.editCalculatedMetrics[index]?.achievement ??
                        kpi.score ??
                        0;
                    const calculatedBobot = kpi.weight || 0;
                    // console.log(calculatedScore);

                    totalScore += Number(calculatedScore);
                    totalAchievement += Number(calculatedAchievement);
                    totalBobot += Number(calculatedBobot);
                    activeKpiCount++;

                    // Hitung PICA count - jika achievement < 100% dan actual < target
                    const actualValue = this.parseActualValue(
                        this.editKpiData[index]?.actual_value,
                        kpi.unit,
                    );
                    const targetValue = parseFloat(kpi.Nilai_Target) || 0;

                    if (
                        calculatedAchievement !== null &&
                        calculatedAchievement < this.gradingThreshold
                    ) {
                        picaCount++;
                    }
                }
            });

            // Update selectedApproval dengan nilai yang dihitung
            this.selectedApproval.total_score = totalScore;

            this.selectedApproval.achievement = (totalScore / totalBobot) * 100;

            this.selectedApproval.capped_achievement = Math.min(
                Math.max(this.selectedApproval.achievement, this.kpiMinScore),
                this.kpiMaxScore,
            );

            this.selectedApproval.kpi_count = activeKpiCount;
            this.selectedApproval.pica_count = picaCount;

            // console.log("📊 Updated Summary:", {
            //     totalScore,
            //     activeKpiCount,
            //     picaCount,
            // });
        },

        // Tambahkan method validasi formula
        isValidFormula(formula) {
            const safePattern = /^[0-9a-zA-Z_+\-*/().,\s!=<>|&?:]+$/;
            return safePattern.test(formula.replace(/\s/g, ""));
        },

        async fetchKpiCapConfig() {
            try {
                const resp = await axios.get('/api/v1/update-performance/kpi-cap-config');
                if (resp.status === 200 && resp.data?.result) {
                    this.kpiMaxScore = resp.data.result.max_achievement_score ?? 125;
                    this.kpiMinScore = resp.data.result.min_achievement_score ?? 50;
                    this.batasParameter = batasDariConfig(resp.data.result);
                }
            } catch (e) {
                console.error('Gagal mengambil konfigurasi cap KPI:', e);
            }
        },

        getInputFormat(unit) {
            const unitStr = String(unit).toLowerCase();
            if (unitStr === "rp" || unitStr === "idr" || unitStr === "rupiah") {
                return "currency";
            } else if (unitStr === "%" || unitStr === "persen") {
                return "percentage";
            } else {
                return "numeric";
            }
        },

        // parseActualValue(value, unit) {
        //     // if (!value) return null;

        //     let cleanValue = value;
        //     const format = this.getInputFormat(unit);

        //     if (format === "currency") {
        //         cleanValue = value.replace(/[^\d]/g, "");
        //     } else if (format === "percentage") {
        //         cleanValue = value.replace(/[^\d.,]/g, "").replace(",", ".");
        //     } else {
        //         cleanValue = value.replace(/[^\d.,]/g, "").replace(",", ".");
        //     }

        //     return parseFloat(cleanValue);
        // },
        parseActualValue(value, unit) {
            // Cek ketat: hanya return null jika benar-benar kosong, undefined, atau null
            // Angka 0 atau string "0" akan lolos dari cek ini
            if (
                value === null ||
                value === undefined ||
                String(value).trim() === ""
            ) {
                return null;
            }

            let cleanValue = String(value); // Pastikan jadi string dulu
            const format = this.getInputFormat(unit);

            if (format === "currency") {
                // Hapus semua karakter kecuali angka dan minus
                cleanValue = cleanValue.replace(/[^\d-]/g, "");
            } else if (format === "percentage") {
                // Hapus karakter non-digit selain titik, koma, minus
                cleanValue = cleanValue
                    .replace(/[^\d.,-]/g, "")
                    .replace(",", ".");
            } else {
                cleanValue = cleanValue
                    .replace(/[^\d.,-]/g, "")
                    .replace(",", ".");
            }

            const parsed = parseFloat(cleanValue);

            // Cek jika hasil parsed adalah NaN (Not a Number)
            // Jika parsed adalah 0, isNaN(0) itu false, jadi akan me-return 0 (benar)
            return isNaN(parsed) ? null : parsed;
        },

        convertValueUnit(value, format, simbol = null) {
            if (!format || !value) return 0;

            let simbolSatuan = simbol ? simbol.trim() : "";
            let decimals = 0;
            let result = "";

            if (format === "currency") {
                result = new Intl.NumberFormat("id-ID").format(value);
            } else if (format === "percentage") {
                const numericValue = parseFloat(value) || 0;
                const strValue = value.toString();

                const userInputDecimal =
                    strValue.includes(".") || strValue.includes(",");

                if (!userInputDecimal && numericValue % 1 === 0) {
                    result = `${numericValue}`;
                } else {
                    result = `${numericValue}`;
                }
            } else {
                result = parseFloat(value);
            }

            return format === "currency"
                ? simbolSatuan + ". " + result
                : result + " " + simbolSatuan;
        },

        async fetchApprovals(url = null) {
            this.loading.fetch = true;
            try {
                const params = new URLSearchParams();

                if (this.filters.search) {
                    params.append("search", this.filters.search);
                }
                if (this.filters.periode) {
                    params.append("periode", this.filters.periode);
                }
                if (this.filters.division) {
                    params.append("division", this.filters.division);
                }
                if (this.filters.level) {
                    params.append("level", this.filters.level);
                }
                this.approvals = [];

                const endpoint =
                    url ||
                    `/api/v1/kpi-verifikasi/performance/approvals?${params.toString()}`;

                const response = await axios.get(endpoint);

                if (response.data?.success) {
                    this.approvals = response.data.result.data.data;
                    this.approvalTabs[0].count =
                        response.data.result.data.total;

                    this.hasApprovalSchedule = response.data.result.data.total;
                    this.approvalsPagination.currentPage =
                        response.data.result.data.current_page;
                    this.approvalsPagination.totalDatas =
                        response.data.result.data.total;
                    this.approvalsPagination.totalPages =
                        response.data.result.data.last_page;
                    this.approvalsPagination.startItem =
                        response.data.result.data.from;
                    this.approvalsPagination.endItem =
                        response.data.result.data.to;
                    this.approvalsPagination.nextLink =
                        response.data.result.data.next_page_url;
                    this.approvalsPagination.prevLink =
                        response.data.result.data.prev_page_url;
                    this.approvalsPagination.partPagin =
                        response.data.result.data.links;
                    this.totalApprove = response.data.result.totalApproved;
                    this.totalPending = response.data.result.totalPending;
                    this.totalReject = response.data.result.totalReject;
                } else {
                    this.approvals = [];
                }
            } catch (error) {
                this.approvals = [];
                console.error("Error fetching performance approvals:", error);
                this.showSimpleAlert(
                    "Failed to load performance approvals",
                    "error",
                );
            } finally {
                this.loading.fetch = false;
            }
        },

        async fetchHistory(url = null) {
            this.loading.history = true;
            try {
                const params = new URLSearchParams();

                // console.log(this.historyFilters);
                if (this.historyFilters.search) {
                    params.append("search", this.historyFilters.search);
                }
                if (this.historyFilters.periode) {
                    params.append("periode", this.historyFilters.periode);
                }
                if (this.historyFilters.division) {
                    params.append("division", this.historyFilters.division);
                }
                if (this.historyFilters.level) {
                    params.append("level", this.historyFilters.level);
                }
                if (this.historyFilters.status) {
                    params.append("status", this.historyFilters.status);
                }

                // tetap true
                params.append("history", true);

                // console.log(params.toString());

                const endpoint =
                    url ||
                    `/api/v1/kpi-verifikasi/performance/approvals?${params.toString()}`;

                const response = await axios.get(endpoint);

                if (response.data?.success) {
                    this.historyApprovals = response.data.result.data.data;
                    // Urutkan yang terbaru di-approve di atas
                    this.historyApprovals.sort((a, b) => new Date(b.update_at || b.updated_at || 0) - new Date(a.update_at || a.updated_at || 0));
                    this.historyApprovalsPagination.currentPage =
                        response.data.result.data.current_page;
                    this.historyApprovalsPagination.totalDatas =
                        response.data.result.data.total;
                    this.historyApprovalsPagination.totalPages =
                        response.data.result.data.last_page;
                    this.historyApprovalsPagination.startItem =
                        response.data.result.data.from;
                    this.historyApprovalsPagination.endItem =
                        response.data.result.data.to;
                    this.historyApprovalsPagination.nextLink =
                        response.data.result.data.next_page_url;
                    this.historyApprovalsPagination.prevLink =
                        response.data.result.data.prev_page_url;
                    this.historyApprovalsPagination.partPagin =
                        response.data.result.data.links;
                    this.totalApprove = response.data.result.totalApproved;
                    this.totalPending = response.data.result.totalPending;
                    this.totalReject = response.data.result.totalReject;
                    // console.log(this.historyApprovals);
                    const historyTab = this.approvalTabs.find((tab) => tab.id === "history");
                    if (historyTab) {
                        historyTab.count = response.data.result.data.total;
                    }
                }
            } catch (error) {
                console.error("Error fetching history:", error);
                this.showSimpleAlert("Failed to load history", "error");
            } finally {
                this.loading.history = false;
            }
        },

        async fetchFilterOptions() {
            try {
                const [levelsResponse, divisionsResponse] = await Promise.all([
                    axios.get(
                        "/api/v1/kpi-verifikasi/performance/approvals/filter-options/levels",
                    ),
                    axios.get(
                        "/api/v1/kpi-verifikasi/performance/approvals/filter-options/divisions",
                    ),
                ]);

                if (levelsResponse.data?.success) {
                    this.filterOptions.levels = levelsResponse.data.result;
                }
                if (divisionsResponse.data?.success) {
                    this.filterOptions.divisions =
                        divisionsResponse.data.result;
                }
            } catch (error) {
                console.error("Error fetching filter options:", error);
            }
        },

        async confirmApprove() {
            this.loading.approve = true;
            try {
                await axios.post(
                    `/api/v1/kpi-verifikasi/performance/approvals/${this.selectedApproval.Id_Approval_Request}/approve`,
                );
                this.showSimpleAlert(
                    "Performance approved successfully",
                    "success",
                );
                this.closeApproveModal();
                this.closeDetailModal();
                await this.fetchApprovals();
                await this.fetchHistory();
            } catch (error) {
                console.error("Error approving performance:", error);
                this.showSimpleAlert("Failed to approve performance", "error");
            } finally {
                this.loading.approve = false;
            }
        },

        async confirmReject() {
            // Alasan global wajib. Komentar per-parameter opsional — verifikator boleh
            // menolak global saja, atau menambahkan komentar per parameter tertentu.
            if (!this.rejectionReason.trim()) {
                this.rejectionError = "Alasan penolakan wajib diisi";
                return;
            }

            // Jika ada parameter dicentang, komentarnya harus diisi.
            const checkedButEmpty = this.rejectionKpiSelection.some(
                (selected, index) =>
                    selected && !this.rejectionComments[index]?.trim(),
            );
            if (checkedButEmpty) {
                this.rejectionError =
                    "Isi komentar untuk parameter yang dicentang, atau hapus centangnya";
                return;
            }

            this.rejectionError = "";
            this.loading.reject = true;
            try {
                const kpiComments = {};
                this.rejectionKpiSelection.forEach((selected, index) => {
                    if (selected && this.rejectionComments[index]?.trim()) {
                        const kpiId =
                            this.selectedApproval.kpiDetails[index].id;
                        kpiComments[kpiId] = this.rejectionComments[index];
                    }
                });

                const payload = {
                    reason: this.rejectionReason,
                    kpi_comments: kpiComments,
                };

                await axios.post(
                    `/api/v1/kpi-verifikasi/performance/approvals/${this.selectedApproval.Id_Approval_Request}/reject`,
                    payload,
                );

                this.showSimpleAlert(
                    "Performance submission rejected successfully",
                    "success",
                );
                this.closeRejectModal();
                this.closeDetailModal();
                await this.fetchApprovals();
                await this.fetchHistory();
            } catch (error) {
                console.error("Error rejecting performance submission:", error);
                this.showSimpleAlert(
                    error.response?.data?.message ||
                        "Failed to reject performance submission",
                    "error",
                );
            } finally {
                this.loading.reject = false;
            }
        },

        async confirmEditAndApprove() {
            if (!this.isFormEditValid) {
                ElMessage.warning("Mohon isi PICA KPI yang di bawah target.");
                return;
            }
            this.loading.edit = true;

            try {
                // Multipart: lampiran data source ikut dikirim bersama edit actual/PICA.
                // Flag boolean HANYA di-append saat bernilai true — string "false"
                // bersifat truthy di PHP.
                const payload = new FormData();
                payload.append(
                    "no_transaksi",
                    this.selectedApproval.No_Transaksi,
                );

                this.selectedApproval.kpiDetails.forEach((kpi, index) => {
                    if (kpi.Is_Disabled !== "T") return;

                    const actualValue = this.parseActualValue(
                        this.editKpiData[index]?.actual_value,
                        kpi.unit,
                    );
                    if (actualValue !== null) {
                        payload.append(`actual_values[${kpi.id}]`, actualValue);
                    }

                    if (this.editPicaFlags[index]?.shouldRemovePica) {
                        payload.append(`pica_removal_flags[${kpi.id}]`, "1");
                    } else if (this.shouldShowPicaSection(index, kpi)) {
                        const pica = this.editPicaData[index] || {};
                        payload.append(`pica_data[${kpi.id}][enabled]`, "1");
                        payload.append(
                            `pica_data[${kpi.id}][problem_identification]`,
                            pica.problem_identification || "",
                        );
                        payload.append(
                            `pica_data[${kpi.id}][corrective_actions]`,
                            pica.corrective_actions || "",
                        );
                        payload.append(
                            `pica_data[${kpi.id}][due_date]`,
                            pica.due_date || "",
                        );
                    }

                    const ds = this.editDataSource[index];
                    if (ds?.newFile) {
                        payload.append(
                            `data_source_files[${kpi.id}]`,
                            ds.newFile,
                        );
                    }
                });

                const response = await axios.post(
                    `/api/v1/kpi-verifikasi/performance/approvals/${this.selectedApproval.Id_Approval_Request}/edit-approve`,
                    payload,
                    { headers: { "Content-Type": "multipart/form-data" } },
                );

                if (response.status === 200) {
                    ElMessage({
                        type: "success",
                        message: "Performance berhasil diedit dan disetujui",
                    });

                    this.closeEditModal();
                    await this.fetchApprovals();
                    this.loading.edit = false;
                }
            } catch (error) {
                ElMessage({
                    type: "error",
                    message:
                        error.response?.data?.message || "Terjadi kesalahan",
                });
                this.loading.edit = false;
            }
        },

        // showDetailModal(approval) {
        //     this.selectedApproval = approval;
        //     this.showDetailModalFlag = true;
        // },

        async showDetailModal(approval) {
            // this.selectedApproval = approval;
            if (!approval) return;
            this.showDetailModalFlag = true;
            this.getDetail(approval);
        },
        async getDetail(approval) {
            this.loading.detail = true;
            try {
                const response = await axios.get(
                    `/api/v1/kpi-verifikasi/performance/approvals/detail/${approval.Id_Pengajuan}/${approval.Periode}`,
                );
                if (response.status == 200) {
                    this.selectedApproval = {
                        ...approval,
                        ...response.data.result,
                    };
                    // console.log(this.selectedApproval);
                }
            } catch (error) {
                this.selectedApproval = [];
                console.log(error);
            } finally {
                this.loading.detail = false;
            }
        },

        async openRejectModal(approval) {
            this.showRejectModal = true;
            this.closeDetailModal();
            // Muat detail dulu supaya kpiDetails terisi, baru samakan ukuran array
            // pilihan/komentar reject dengan jumlah parameter (jika tidak, checkbox
            // per-parameter tidak reaktif).
            await this.getDetail(approval);
            const n = this.selectedApproval?.kpiDetails?.length || 0;
            this.rejectionReason = "";
            this.rejectionError = "";
            this.rejectionKpiSelection = new Array(n).fill(false);
            this.rejectionComments = new Array(n).fill("");
        },

        async openEditModal(approval) {
            this.showEditModal = true;
            this.closeDetailModal();
            await this.getDetail(approval);
            this.backupOriginalData();
            this.initializeEditData();
        },
        backupOriginalData() {
            if (!this.selectedApproval) return;

            // Backup data approval utama
            this.originalApprovalData = JSON.parse(
                JSON.stringify(this.selectedApproval),
            );

            // Backup data KPI details
            this.originalKpiData = JSON.parse(
                JSON.stringify(this.selectedApproval.kpiDetails || []),
            );

            // Backup data PICA
            this.originalPicaData = [];
            if (this.selectedApproval.kpiDetails) {
                this.selectedApproval.kpiDetails.forEach((kpi, index) => {
                    this.originalPicaData[index] = JSON.parse(
                        JSON.stringify({
                            problem_identification:
                                kpi.pica?.[0]?.problem_identification || "",
                            corrective_actions:
                                kpi.pica?.[0]?.corrective_actions || "",
                            due_date: kpi.pica?.[0]?.due_date || "",
                        }),
                    );
                });
            }
        },

        openApproveModal(approval) {
            this.closeDetailModal();
            this.selectedApproval = approval;
            this.showApproveModal = true;
        },

        openBulkRejectModal() {
            this.bulkRejectionReason = "";
            this.bulkRejectionError = "";
            this.showBulkRejectModal = true;
        },

        closeDetailModal() {
            this.showDetailModalFlag = false;
            this.selectedApproval = null;
        },

        closeRejectModal() {
            this.showRejectModal = false;
            this.selectedApproval = null;
            this.rejectionReason = "";
            this.rejectionError = "";
            this.rejectionKpiSelection = [];
            this.rejectionComments = [];
        },

        closeEditModal() {
            // Restore data asli dari backup sebelum menutup modal
            this.restoreOriginalData();

            this.revokeDataSourcePreviews();
            this.showEditModal = false;
            this.selectedApproval = null;
            this.editKpiData = [];
            this.editPicaData = [];
            this.editPicaFlags = [];
            this.editDataSource = [];
            this.fileInputKey++;
            this.editCalculatedMetrics = {};
        },
        restoreOriginalData() {
            if (!this.originalApprovalData) return;

            // Restore data approval utama
            Object.keys(this.originalApprovalData).forEach((key) => {
                if (this.selectedApproval && key in this.selectedApproval) {
                    this.selectedApproval[key] = this.originalApprovalData[key];
                }
            });

            // Restore data KPI details
            if (this.selectedApproval && this.selectedApproval.kpiDetails) {
                this.selectedApproval.kpiDetails = JSON.parse(
                    JSON.stringify(this.originalKpiData),
                );
            }

            // Clear backup data
            this.originalApprovalData = null;
            this.originalKpiData = [];
            this.originalPicaData = [];
        },

        closeApproveModal() {
            this.showApproveModal = false;
            this.selectedApproval = null;
        },

        closeBulkRejectModal() {
            this.showBulkRejectModal = false;
            this.bulkRejectionReason = "";
            this.bulkRejectionError = "";
        },

        calculateScorePercentage(approval) {
            return Math.min(approval.total_score, 100);
        },

        getScoreClass(score) {
            if (score >= 90) return "score-excellent";
            if (score >= 80) return "score-good";
            if (score >= 70) return "score-average";
            return "score-poor";
        },

        getAchievementClass(achievement) {
            if (achievement >= 100) return "achievement-excellent";
            if (achievement >= 80) return "achievement-good";
            if (achievement >= 70) return "achievement-average";
            return "achievement-poor";
        },

        handleSearchInput() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.fetchApprovals();
            }, 500);
        },

        handleHistorySearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.fetchHistory();
            }, 500);
        },

        clearFilters() {
            this.filters = {
                search: "",
                periode: "",
                division: "",
                level: "",
            };
            this.fetchApprovals();
        },

        clearHistoryFilters() {
            this.historyFilters = {
                search: "",
                periode: "",
                division: "",
                level: "",
                status: "",
            };
            this.fetchHistory();
        },

        handleTabChange(tabId) {
            this.activeTab = tabId;
            this.clearSelection();
            if (tabId === "history") {
                this.fetchHistory();
            } else {
                this.fetchApprovals();
            }
        },

        toggleSelection(id) {
            const index = this.selectedApprovals.indexOf(id);
            if (index > -1) {
                this.selectedApprovals.splice(index, 1);
            } else {
                this.selectedApprovals.push(id);
            }
        },

        clearSelection() {
            this.selectedApprovals = [];
        },

        getApprovalById(id) {
            return this.approvals.find((a) => a.id_Performance_Approval === id);
        },

        getInitials(name) {
            if (!name) return "??";
            return name
                .split(" ")
                .map((n) => n[0])
                .join("")
                .toUpperCase();
        },

        getStatusClass(status) {
            switch (status) {
                case "Y":
                    return "approved";
                // Backend menandai penolakan dengan Flag_Approval = 'T'.
                case "T":
                case "N":
                    return "rejected";
                default:
                    return "pending";
            }
        },

        getStatusText(status) {
            switch (status) {
                case "Y":
                    return "Disetujui";
                case "T":
                case "N":
                    return "Ditolak";
                default:
                    return "Menunggu";
            }
        },

        getStatusIcon(status) {
            switch (status) {
                case "Y":
                    return "bi bi-check-circle";
                case "T":
                case "N":
                    return "bi bi-x-circle";
                default:
                    return "bi bi-clock";
            }
        },

        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },

        formatDate(dateString) {
            if (!dateString) return "";
            return new Date(dateString).toLocaleDateString("id-ID");
        },

        formatDateTime(dateString) {
            if (!dateString) return "";
            const date = new Date(dateString);
            return (
                date.toLocaleDateString("id-ID") +
                " " +
                date.toLocaleTimeString("id-ID", {
                    hour: "2-digit",
                    minute: "2-digit",
                })
            );
        },

        formatTime(dateString) {
            if (!dateString) return "";
            return new Date(dateString).toLocaleTimeString("id-ID", {
                hour: "2-digit",
                minute: "2-digit",
            });
        },

        showSimpleAlert(message, type = "warning") {
            ElMessage({
                type: type,
                message: message,
            });
        },

        handleResize() {
            this.isMobile = window.innerWidth < 768;
        },

        async bulkApprove() {
            this.loading.approve = true;
            try {
                for (const id of this.selectedApprovals) {
                    const approval = this.getApprovalById(id);
                    if (approval) {
                        await axios.post(
                            `/api/v1/kpi-verifikasi/performance/approvals/${approval.Id_Approval_Request}/approve`,
                        );
                    }
                }
                this.showSimpleAlert(
                    `${this.selectedApprovals.length} performance submissions approved successfully`,
                    "success",
                );
                this.clearSelection();
                await this.fetchApprovals();
            } catch (error) {
                console.error("Error bulk approving:", error);
                this.showSimpleAlert(
                    "Failed to approve some performance submissions",
                    "error",
                );
            } finally {
                this.loading.approve = false;
            }
        },

        async confirmBulkReject() {
            if (!this.bulkRejectionReason.trim()) {
                this.bulkRejectionError = "Rejection reason is required";
                return;
            }

            this.loading.bulkReject = true;
            try {
                for (const id of this.selectedApprovals) {
                    await axios.post(
                        `/api/v1/kpi-verifikasi/performance/approvals/${id}/reject`,
                        {
                            reason: this.bulkRejectionReason,
                        },
                    );
                }
                this.showSimpleAlert(
                    `${this.selectedApprovals.length} submissions rejected successfully`,
                    "success",
                );
                this.closeBulkRejectModal();
                this.clearSelection();
                await this.fetchApprovals();
            } catch (error) {
                console.error("Error bulk rejecting:", error);
                this.showSimpleAlert(
                    "Failed to reject some submissions",
                    "error",
                );
            } finally {
                this.loading.bulkReject = false;
            }
        },

        handleKpiSelectionChange(index) {
            if (!this.rejectionKpiSelection[index]) {
                this.rejectionComments[index] = "";
            }
        },
    },
    async mounted() {
        await this.fetchFilterOptions();
        await this.fetchKpiCapConfig();
        if (this.mode === "history") {
            await this.fetchHistory();
            window.addEventListener("resize", this.handleResize);
            return;
        }
        await this.fetchApprovals();
        if (!this.embedded) {
            await this.fetchHistory();
        }
        window.addEventListener("resize", this.handleResize);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.handleResize);
        clearTimeout(this.searchTimeout);
    },
};
</script>

<style scoped>
.embedded-panel :deep(.approval-tabs-container) {
    border: 0;
    border-radius: 0;
    box-shadow: none;
    margin-bottom: 0;
    overflow: visible;
    background: transparent;
}

.embedded-panel :deep(.desktop-tabs),
.embedded-panel :deep(.mobile-tabs) {
    display: none;
}

.embedded-panel :deep(.tab-content) {
    padding: 0;
    background: transparent;
}

/* Pagination Styles */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 1rem;
    background-color: var(--surface);
    border-top: 1px solid var(--border);
    margin-top: 0;
}

.pagination-info {
    font-size: 0.875rem;
    color: var(--text-muted);
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border: 1px solid var(--border);
    background-color: var(--surface);
    border-radius: 6px;
    color: var(--text);
    cursor: pointer;
    transition: all 0.2s ease;
}

.pagination-btn:hover:not(:disabled) {
    background-color: var(--surface-soft);
    border-color: var(--primary);
    color: var(--primary);
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-pages {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.pagination-page {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2.5rem;
    padding: 0 0.75rem;
    border: 1px solid var(--border);
    background-color: var(--surface);
    border-radius: 6px;
    color: var(--text);
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.pagination-page:hover {
    background-color: var(--surface-soft);
    border-color: var(--primary);
}

.pagination-page.active {
    background-color: var(--primary);
    border-color: var(--primary);
    color: white;
}

.pagination-ellipsis {
    padding: 0 0.5rem;
    color: var(--text-muted);
    font-size: 0.875rem;
}

.pagination-page-size {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pagination-page-size label {
    font-size: 0.875rem;
    color: var(--text-muted);
    white-space: nowrap;
}

.page-size-select {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    background-color: var(--surface);
    color: var(--text);
    font-size: 0.875rem;
    cursor: pointer;
    transition: border-color 0.2s ease;
}

.page-size-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Responsive Design */
@media (max-width: 768px) {
    .pagination-container {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem;
    }

    .btn-clear-filters {
        padding: 0.5rem 0.2rem;
    }

    .pagination-controls {
        order: 1;
    }

    .pagination-info {
        order: 2;
    }

    .pagination-page-size {
        order: 3;
    }

    .pagination-pages {
        gap: 0.125rem;
    }

    .pagination-page {
        min-width: 2.25rem;
        height: 2.25rem;
        padding: 0 0.5rem;
        font-size: 0.8125rem;
    }

    .pagination-btn {
        width: 2.25rem;
        height: 2.25rem;
    }
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
    display: block;
}

.pica-removal-indicator {
    margin-bottom: 1rem;
}

.pica-success {
    color: var(--success);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 8px;
    border-left: 4px solid var(--success);
}

.current-pica .bi-trash {
    color: var(--danger);
}
/* Tambahkan di bagian CSS */
.kpi-disabled {
    position: relative;
    opacity: 0.6;
    background: var(--surface-soft);
}

.kpi-disabled::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.02);
    z-index: 1;
}

.disabled-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: var(--secondary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.disabled-message {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid var(--secondary);
    margin-top: 1rem;
}

.disabled-message p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Style untuk form fields yang disabled */
.kpi-disabled .form-control {
    background: var(--surface-soft);
    color: var(--text-muted);
    cursor: not-allowed;
}

.kpi-disabled .edit-kpi-content {
    pointer-events: none;
}

.form-control {
    border: 2px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    background: var(--surface);
    width: 100%;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-control:read-only {
    background: var(--surface-soft);
    color: var(--text-muted);
}

.calculated-metrics {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--border);
}

.metric-display {
    text-align: center;
}

.metric-display label {
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.9rem;
    display: block;
    margin-bottom: 0.5rem;
}

.metric-value.score {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
}

.achievement-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 1rem;
}

.achievement-badge.small {
    padding: 0.25rem 0.75rem;
    font-size: 0.8rem;
}

.achievement-excellent {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.achievement-good {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.achievement-average {
    background: rgba(255, 165, 0, 0.1);
    color: #ffa500;
}

.achievement-poor {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.pica-warning {
    color: var(--warning);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: rgba(245, 158, 11, 0.1);
    border-radius: 8px;
    border-left: 4px solid var(--warning);
}

.pica-form {
    background: var(--surface);
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid var(--border);
}

.current-pica {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--surface-soft);
    border-radius: 8px;
    border-left: 4px solid var(--warning);
}

.current-pica h6 {
    margin-bottom: 0.75rem;
    color: var(--dark);
}

.current-pica-content p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .edit-kpi-content .row {
        flex-direction: column;
    }

    .performance-summary {
        gap: 0.1rem;
    }

    .form-group {
        margin-bottom: 0.75rem;
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
/* Download Button States */
.btn-download {
    transition: all 0.3s ease;
}

.btn-download.downloading {
    background-color: var(--warning) !important;
    border-color: var(--warning) !important;
    cursor: not-allowed;
}

.btn-download.downloaded {
    background-color: var(--success) !important;
    border-color: var(--success) !important;
}

.btn-download:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* Spinning Animation */
.spinning {
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

/* Button Base Styles */
.btn-attachment {
    padding: 0.5rem 0.875rem;
    border: 1px solid;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-download {
    background-color: var(--primary);
    border-color: var(--primary);
    color: var(--surface);
}

.btn-download:hover:not(:disabled) {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.attachment-section {
    margin-top: 1.25rem;
    padding: 0.6rem;
    background: linear-gradient(
        135deg,
        rgba(99, 102, 241, 0.05) 0%,
        rgba(99, 102, 241, 0.02) 100%
    );
    border: 1px solid var(--border);
    border-radius: 8px;
    border-left: 4px solid var(--primary);
}

.attachment-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.attachment-icon {
    font-size: 1rem;
    color: var(--primary);
}

.attachment-header h6 {
    color: var(--dark);
    font-size: 0.875rem;
    font-weight: 600;
    margin: 0;
}

.attachment-content {
    background-color: var(--surface);
    border-radius: 6px;
    padding: 0.75rem;
    border: 1px solid var(--border);
}

.attachment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.attachment-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
}

.file-icon {
    font-size: 1.5rem;
    color: var(--primary);
    flex-shrink: 0;
}

.file-details {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.file-name {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--dark);
    word-break: break-word;
}

.file-size {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.attachment-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

.btn-attachment {
    padding: 0.5rem 0.875rem;
    border: 1px solid;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-preview {
    background-color: var(--surface);
    border-color: var(--primary);
    color: var(--primary);
}

.btn-preview:hover {
    background-color: var(--primary);
    color: var(--surface);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.btn-download {
    background-color: var(--primary);
    border-color: var(--primary);
    color: var(--surface);
}

.btn-download:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

/* Animation */
.attachment-section {
    animation: slideInAttachment 0.3s ease-out;
}

@keyframes slideInAttachment {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .attachment-section {
        padding: 0.875rem;
        margin-top: 1rem;
    }

    .attachment-item {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }

    .attachment-actions {
        justify-content: stretch;
    }

    .btn-attachment {
        flex: 1;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .attachment-actions {
        flex-direction: column;
    }

    .btn-attachment {
        justify-content: center;
    }
}

/* Unique Loading Overlay and Card */
.perf-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(4px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 99;
    border-radius: 0 0 16px 16px;
}

.perf-loading-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    text-align: center;
    background: var(--surface, var(--hcis-white));
    padding: 2rem;
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border);
    width: 280px;
    height: 160px;
    box-sizing: border-box;
    flex-shrink: 0;
}

.perf-spinner-circle {
    width: 48px;
    height: 48px;
    min-width: 48px;
    min-height: 48px;
    flex-shrink: 0;
    border: 4px solid var(--primary-light);
    border-top: 4px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    box-sizing: border-box;
}

.perf-loading-text {
    margin: 0;
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--text);
    white-space: nowrap;
}

.performance-detail-content,
.edit-modal-content {
    min-height: 250px;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.content-blur {
    filter: blur(2px);
    pointer-events: none;
    user-select: none;
}

/* Skeleton Loading */
.kpi-list-skeleton {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kpi-skeleton-item {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
    padding: 1.25rem;
    animation: pulse 2s infinite;
}

.skeleton-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.skeleton-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--surface-soft);
}

.skeleton-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.skeleton-line {
    height: 12px;
    background: var(--surface-soft);
    border-radius: 6px;
}

.skeleton-line.short {
    width: 60%;
}

.skeleton-line.medium {
    width: 40%;
}

.skeleton-badge {
    width: 80px;
    height: 24px;
    background: var(--surface-soft);
    border-radius: 12px;
}

.skeleton-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.skeleton-progress {
    height: 8px;
    background: var(--surface-soft);
    border-radius: 4px;
}

.skeleton-stats {
    display: flex;
    gap: 2rem;
}

/* Footer Loading */
.footer-loading {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

.skeleton-button {
    width: 100px;
    height: 40px;
    background: var(--surface-soft);
    border-radius: var(--border-radius);
    animation: pulse 2s infinite;
}

/* Pulse Animation */
@keyframes pulse {
    0% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
    100% {
        opacity: 1;
    }
}

/* Disabled State for Buttons */
.btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-modern:disabled:hover {
    box-shadow: none;
    transform: none !important;
}

/* Responsive Design */
@media (max-width: 768px) {
    .loading-spinner {
        padding: 2rem;
    }

    .spinner-circle {
        width: 40px;
        height: 40px;
    }

    .skeleton-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .footer-loading {
        justify-content: center;
    }

    .skeleton-button {
        width: 80px;
        height: 36px;
    }
}
.calculated-metrics {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--border);
}

.metric-display {
    text-align: center;
}

.metric-display label {
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.9rem;
    display: block;
    margin-bottom: 0.5rem;
}

.metric-value.score {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
}

.achievement-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 1rem;
}

.achievement-excellent {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.achievement-good {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.achievement-average {
    background: rgba(255, 165, 0, 0.1);
    color: #ffa500;
}

.achievement-poor {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

/* Loading States */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: var(--text-muted);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--border);
    border-left: 4px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 1rem;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
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

/* Buttons */
.btn-modern {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.2rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    color: white;
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

.btn-primary {
    background-color: var(--primary);
}

.btn-success {
    background-color: var(--success);
}

.btn-danger {
    background-color: var(--danger);
}

.btn-warning {
    background-color: var(--warning);
}

.btn-secondary {
    background-color: var(--secondary);
}

.btn-modern:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Bulk Actions */
.bulk-actions-bar {
    background: var(--surface);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--primary);
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

.btn-bulk.btn-approve {
    background: var(--success);
    color: white;
}

.btn-bulk.btn-reject {
    background: var(--danger);
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

/* Filter Section */
.filter-section {
    background: var(--surface);
    padding: 1.5rem;
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
    font-size: 0.9rem;
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
    font-size: 0.9rem;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.filter-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
    color: var(--text-muted);
}

.filter-text {
    font-weight: 500;
}

.btn-clear-filters {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    color: var(--text-muted);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-clear-filters:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Empty State - No Schedule */
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

.schedule-info-card {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.5rem;
    border-left: 4px solid var(--warning);
    max-width: 400px;
    margin: 0 auto;
}

.info-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    font-weight: 600;
    color: var(--dark);
}

.info-header i {
    color: var(--warning);
}

.info-content {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.info-label {
    color: var(--text-muted);
    font-size: 0.9rem;
}

.info-value-form {
    font-weight: 600;
    font-size: 0.9rem;
}

.status-inactive {
    color: var(--danger);
    background: rgba(239, 68, 68, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h4 {
    margin-bottom: 1rem;
    color: var(--text-muted);
}

.empty-state p {
    margin: 0 0 1.5rem 0;
}

/* Table View */
.table-view-container {
    width: 100%;
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: var(--shadow);
}

.table-responsive-wrapper {
    min-width: 800px;
}

.modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.875rem;
    background: var(--surface);
    border-radius: 12px;
    overflow: hidden;
}

.modern-table th,
.modern-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border);
}

.modern-table th {
    position: sticky;
    top: 0;
    background-color: var(--surface-soft);
    z-index: 10;
    font-weight: 600;
    color: var(--text);
    border-bottom: 2px solid var(--border);
}

.header-cell-content {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.table-row {
    transition: background-color 0.2s ease;
    cursor: pointer;
}

.table-row:hover {
    background-color: var(--surface-soft);
}

/* Table Cells */
.transaction-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.transaction-id {
    font-weight: 600;
    color: var(--primary);
    font-size: 0.9rem;
}

.transaction-time {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.employee-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.avatar-sm {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    flex-shrink: 0;
}

.employee-details {
    min-width: 0;
}

.employee-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.125rem;
    font-size: 0.9rem;
}

.employee-code {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.division-cell,
.period-cell {
    font-size: 0.875rem;
    color: var(--text);
}

.score-cell {
    min-width: 120px;
}

.score-progress {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.progress-bar {
    flex: 1;
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

.progress-fill.score-excellent {
    background: var(--success);
}

.progress-fill.score-good {
    background: var(--warning);
}

.progress-fill.score-average {
    background: #ffa500;
}

.progress-fill.score-poor {
    background: var(--danger);
}

.score-value {
    font-weight: 600;
    font-size: 0.875rem;
    min-width: 40px;
    text-align: right;
}

.pica-indicator {
    text-align: center;
}

.pica-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.pica-badge:not(.no-pica) {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.pica-badge.no-pica {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-cell {
    text-align: center;
}

.status-badge {
    padding: 0.4rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.status-pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.status-approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.action-cell {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    justify-content: center;
}

.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.btn-action:hover {
    transform: translateY(-1px);
}

.btn-detail {
    color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}

.btn-detail:hover {
    background: rgba(99, 102, 241, 0.2);
}

.btn-approve-sm {
    color: var(--success);
    background: rgba(16, 185, 129, 0.1);
}

.btn-approve-sm:hover {
    background: rgba(16, 185, 129, 0.2);
}

.btn-edit-sm {
    color: var(--warning);
    background: rgba(245, 158, 11, 0.1);
}

.btn-edit-sm:hover {
    background: rgba(245, 158, 11, 0.2);
}

.btn-reject-sm {
    color: var(--hcis-red-500);
    background: rgba(239, 68, 68, 0.1);
}

.btn-reject-sm:hover {
    background: rgba(239, 68, 68, 0.2);
}

/* Card View */
.card-view-container {
    padding: 0.5rem;
}

.card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}

.approval-card {
    background: var(--surface);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
}

.approval-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

.approval-card.status-pending {
    border-left: 4px solid var(--warning);
}

.approval-card.status-approved {
    border-left: 4px solid var(--success);
}

.approval-card.status-rejected {
    border-left: 4px solid var(--danger);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--surface-soft);
    border-bottom: 1px solid var(--border);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
}

.transaction-info {
    flex: 1;
}

.transaction-id {
    font-weight: 600;
    color: var(--primary);
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.transaction-date {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.status-badge-mobile {
    padding: 0.375rem 0.75rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge-mobile.status-pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.status-badge-mobile.status-approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-badge-mobile.status-rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.card-body {
    padding: 1rem;
}

.employee-info-mobile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.employee-details-mobile {
    flex: 1;
}

.employee-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.employee-meta {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.performance-summary-mobile {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.summary-item i {
    color: var(--primary);
}

.score-progress-mobile {
    margin-top: 0.5rem;
}

.score-progress-mobile .progress-bar {
    height: 8px;
}

.card-footer {
    padding: 1rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}

.btn-card {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: none;
    font-size: 0.8rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-card.btn-detail {
    background: var(--surface-soft);
    color: var(--text);
}

.btn-card.btn-approve {
    background: var(--success);
    color: white;
}

.btn-card.btn-edit {
    background: var(--warning);
    color: white;
}

.btn-card.btn-reject {
    background: var(--hcis-red-500);
    color: white;
}

.btn-card:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.action-buttons-mobile {
    display: flex;
    gap: 0.5rem;
}

/* Detail Modal Styles */
/* .performance-detail-content {
    max-height: 70vh;
    overflow-y: auto;
} */

.detail-header {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 1.5rem;
    background: linear-gradient(145deg, #ffffff 0%, var(--surface-soft) 100%);
    border: 1px solid var(--border);
    border-radius: 16px;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.detail-header-main {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    flex: 1;
    min-width: 280px;
}

.avatar-lg {
    width: 64px;
    height: 64px;
    border-radius: 18px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
    letter-spacing: 0.5px;
}

.avatar-md {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 3px 10px rgba(79, 70, 229, 0.2);
}

.employee-details {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.employee-name {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--dark);
    letter-spacing: -0.01em;
}

.employee-meta-badges {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1.2;
}

.meta-badge.job-class {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.meta-badge.dept {
    background: rgba(79, 70, 229, 0.08);
    color: var(--primary);
    border: 1px solid rgba(79, 70, 229, 0.18);
}

.meta-badge.position {
    background: #f8fafc;
    color: #64748b;
    border: 1px solid #e2e8f0;
}

.performance-summary {
    display: flex;
    align-items: stretch;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1.15rem;
    background: white;
    border-radius: 12px;
    border: 1px solid var(--border);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
    min-width: 135px;
    transition: all 0.2s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}

.summary-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.summary-icon.score {
    background: linear-gradient(135deg, #4f46e5, #3730a3);
    box-shadow: 0 3px 8px rgba(79, 70, 229, 0.25);
}

.summary-icon.kpi {
    background: linear-gradient(135deg, #0284c7, #0369a1);
    box-shadow: 0 3px 8px rgba(2, 132, 199, 0.25);
}

.summary-icon.pica {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    box-shadow: 0 3px 8px rgba(245, 158, 11, 0.25);
}

.summary-content {
    display: flex;
    flex-direction: column;
}

.summary-value {
    font-size: 1.35rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.1;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.summary-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.25rem;
}

.cap-indicator {
    font-size: 0.95rem;
    color: #f59e0b;
    cursor: help;
    display: inline-flex;
    align-items: center;
}

.cap-note-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.6), rgba(253, 230, 138, 0.3));
    border: 1px solid rgba(245, 158, 11, 0.35);
    border-left: 4px solid #f59e0b;
    border-radius: 10px;
    font-size: 0.82rem;
    color: #92400e;
    line-height: 1.5;
}

.cap-note-icon-wrap {
    font-size: 1.1rem;
    color: #d97706;
    flex-shrink: 0;
    margin-top: 0.05rem;
}

.cap-note-text {
    flex: 1;
}

.cap-note-title {
    font-weight: 700;
    color: #78350f;
    margin-right: 0.35rem;
}

.cap-note-desc {
    font-weight: 400;
    color: #92400e;
}

.edit-employee-header {
    padding: 0.85rem 1rem;
    background: var(--surface-soft);
    border-radius: 12px;
    border: 1px solid var(--border);
}

.edit-summary-sticky-bar {
    position: sticky;
    top: -0.5rem;
    z-index: 10;
    background: var(--surface);
    padding: 0.75rem;
    border-radius: 12px;
    border: 1px solid var(--border);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
}

.transaction-info-section {
    margin-bottom: 1.5rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.85rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.info-item label {
    font-weight: 600;
    font-size: 0.82rem;
    color: #64748b;
    margin: 0;
}

.info-item span:not(.status-badge) {
    font-weight: 700;
    font-size: 0.9rem;
    color: #0f172a;
}

/* KPI Performance Section */
.kpi-performance-section {
    margin-bottom: 1.5rem;
}

.kpi-performance-section h6 {
    margin-bottom: 1rem;
    color: #0f172a;
    font-weight: 700;
    font-size: 1.05rem;
}

.section-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--dark, #0f172a);
}

.section-title-wrap i {
    font-size: 1.2rem;
    color: var(--primary, #4f46e5);
}

.section-title-wrap h6 {
    margin: 0;
    font-weight: 700;
    font-size: 1.05rem;
}

.kpi-list {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.kpi-performance-item {
    background: #ffffff;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
    transition: all 0.25s ease;
}

.kpi-performance-item.requires-pica {
    border-left: 4px solid #f59e0b;
}

.kpi-performance-item.disabled {
    position: relative;
    opacity: 0.65;
    background: var(--surface-soft, #f8fafc);
    pointer-events: none;
    user-select: none;
}

.kpi-performance-item:hover {
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}

.kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    border-bottom: 1px solid var(--border, #e2e8f0);
    gap: 1rem;
}

.kpi-main-info {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex: 1;
}

.kpi-number {
    background: linear-gradient(135deg, var(--primary, #4f46e5), #3730a3);
    color: white;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
}

.kpi-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.kpi-title {
    margin: 0;
    color: var(--dark, #0f172a);
    font-weight: 700;
    font-size: 0.98rem;
    line-height: 1.35;
}

.kpi-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.76rem;
    flex-wrap: wrap;
}

.kpi-meta .category {
    background: rgba(79, 70, 229, 0.08);
    color: var(--primary, #4f46e5);
    border: 1px solid rgba(79, 70, 229, 0.16);
    padding: 0.18rem 0.55rem;
    border-radius: 6px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}

.kpi-meta .badge {
    background: rgba(79, 70, 229, 0.08);
    color: var(--primary, #4f46e5);
    border: 1px solid rgba(79, 70, 229, 0.16);
    padding: 0.18rem 0.55rem;
    border-radius: 6px;
    font-weight: 600;
}

.kpi-meta .weight {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    padding: 0.18rem 0.55rem;
    border-radius: 6px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}

.kpi-performance-indicator {
    flex-shrink: 0;
}

.achievement-badge {
    padding: 0.4rem 0.85rem;
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 700;
    text-align: center;
    min-width: 80px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.achievement-badge.small {
    padding: 0.2rem 0.55rem;
    font-size: 0.75rem;
    min-width: auto;
}

.achievement-excellent {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.25);
}

.achievement-good {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
    border: 1px solid rgba(245, 158, 11, 0.25);
}

.achievement-average {
    background: rgba(249, 115, 22, 0.12);
    color: #ea580c;
    border: 1px solid rgba(249, 115, 22, 0.25);
}

.achievement-poor {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

.kpi-content {
    padding: 1.25rem;
}

.performance-metrics {
    margin-bottom: 1.25rem;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 0.75rem;
}

.metric-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.65rem 0.85rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    transition: all 0.2s ease;
}

.metric-item:hover {
    background: #f1f5f9;
}

.metric-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #64748b;
}

.metric-value {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}

.metric-item.metric-score {
    background: rgba(79, 70, 229, 0.04);
    border-color: rgba(79, 70, 229, 0.2);
}

.metric-item.metric-score .metric-label {
    color: var(--primary, #4f46e5);
}

.metric-item.metric-score .metric-value {
    color: var(--primary, #4f46e5);
    font-size: 1.05rem;
}

.metric-item.metric-achieve {
    background: rgba(16, 185, 129, 0.04);
    border-color: rgba(16, 185, 129, 0.2);
}

.metric-item.metric-achieve .metric-label {
    color: #059669;
}

.metric-item.metric-achieve .metric-value {
    color: #059669;
    font-size: 1.05rem;
}

.target-section {
    margin-bottom: 1.25rem;
}

.target-section-header {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 0.4rem;
}

.target-section-header i {
    color: #0284c7;
}

.target-description {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-left: 4px solid #0284c7;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin: 0;
    font-size: 0.88rem;
    line-line: 1.5;
    color: #0369a1;
}

/* Attachment Section */
.attachment-section {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px dashed var(--border, #e2e8f0);
}

.attachment-header h6 {
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #475569;
    margin-bottom: 0.6rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.attachment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    gap: 1rem;
    flex-wrap: wrap;
}

.attachment-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.attachment-icon-box {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(79, 70, 229, 0.1);
    color: var(--primary, #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

.file-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.file-name {
    font-size: 0.88rem;
    font-weight: 600;
    color: #0f172a;
}

.file-sub {
    font-size: 0.75rem;
    color: #64748b;
}

.btn-attachment {
    padding: 0.45rem 0.95rem;
    border-radius: 8px;
    border: 1px solid transparent;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-attachment.btn-download {
    background: var(--primary, #4f46e5);
    color: white;
}

.btn-attachment.btn-download:hover:not(:disabled) {
    background: #4338ca;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.35);
}

.btn-attachment.btn-download.downloaded {
    background: #10b981;
}

/* PICA Section */
.pica-section {
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.4), rgba(254, 249, 195, 0.2));
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 12px;
    padding: 1rem 1.15rem;
    margin-top: 1.15rem;
}

.pica-header {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.85rem;
    color: #b45309;
}

.pica-header i {
    font-size: 1.1rem;
}

.pica-header h6 {
    margin: 0;
    font-weight: 700;
    font-size: 0.92rem;
}

.pica-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.85rem;
}

.pica-item label {
    font-weight: 600;
    color: #78350f;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    display: flex;
    align-items: center;
    margin-bottom: 0.3rem;
}

.pica-item p {
    margin: 0;
    color: #0f172a;
    line-height: 1.45;
    background: #ffffff;
    padding: 0.65rem 0.85rem;
    border-radius: 8px;
    border: 1px solid rgba(245, 158, 11, 0.25);
    font-size: 0.88rem;
}

.due-date-pill {
    font-weight: 600 !important;
    color: #b45309 !important;
}

.global-reject-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-left: 4px solid #ef4444;
    border-radius: 12px;
    padding: 0.9rem 1.15rem;
    margin-bottom: 1.25rem;
}

.global-reject-banner > i {
    color: #ef4444;
    font-size: 1.2rem;
    margin-top: 1px;
}

.global-reject-text {
    min-width: 0;
}

.global-reject-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #b91c1c;
    margin-bottom: 0.2rem;
}

.global-reject-value {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.55;
    color: #7f1d1d;
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: pre-line;
}

.rejection-comment {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-left: 4px solid #ef4444;
    border-radius: 10px;
    padding: 0.85rem 1rem;
    margin-top: 1rem;
}

.rejection-comment h6 {
    color: #dc2626;
    margin: 0 0 0.4rem 0;
    font-weight: 700;
    font-size: 0.88rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.rejection-comment p {
    margin: 0;
    color: #7f1d1d;
    font-size: 0.88rem;
    line-height: 1.45;
}

/* ===== Reject modal styles ===== */
.reject-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
    padding: 0.85rem 1rem;
    border-radius: 12px;
    background: rgba(239, 68, 68, 0.07);
    border: 1px solid rgba(239, 68, 68, 0.18);
    margin-bottom: 1.25rem;
}

.reject-banner i {
    color: #ef4444;
    font-size: 1.1rem;
    margin-top: 1px;
}

.reject-banner-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 0.85rem;
    line-height: 1.35;
}

.reject-banner-text strong {
    color: #0f172a;
}

.reject-banner-text span {
    color: #64748b;
}

.reject-field {
    margin-bottom: 1.5rem;
}

.reject-field-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 0.5rem;
}

.reject-req {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #dc2626;
    background: rgba(239, 68, 68, 0.12);
    padding: 2px 7px;
    border-radius: 999px;
}

.reject-textarea {
    width: 100%;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    padding: 0.65rem 0.8rem;
    font-size: 0.88rem;
    line-height: 1.45;
    resize: vertical;
    background: #fff;
    color: #0f172a;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.reject-textarea:focus {
    outline: none;
    border-color: var(--primary, #4f46e5);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.reject-textarea.is-invalid {
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
}

.reject-error {
    display: flex;
    align-items: center;
    margin-top: 0.4rem;
    font-size: 0.78rem;
    color: #dc2626;
}

.reject-params-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.25rem;
}

.reject-params-title {
    display: flex;
    align-items: center;
    font-size: 0.9rem;
    font-weight: 600;
    color: #0f172a;
}

.reject-params-opt {
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #64748b;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 999px;
}

.reject-params-hint {
    font-size: 0.8rem;
    color: #64748b;
    margin: 0 0 0.75rem;
}

.reject-param-list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.reject-param-item {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    overflow: hidden;
    transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
}

.reject-param-item.active {
    border-color: rgba(239, 68, 68, 0.45);
    background: rgba(239, 68, 68, 0.03);
    box-shadow: 0 1px 4px rgba(239, 68, 68, 0.08);
}

.reject-param-row {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.8rem 0.9rem;
    margin: 0;
    cursor: pointer;
}

.reject-param-check {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    flex: 0 0 18px;
    accent-color: #ef4444;
    cursor: pointer;
}

.reject-param-info {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    min-width: 0;
}

.reject-param-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: #0f172a;
}

.reject-param-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.reject-chip {
    font-size: 0.72rem;
    font-weight: 500;
    color: #475569;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    padding: 2px 8px;
    border-radius: 999px;
    white-space: nowrap;
}

.reject-chip-score {
    color: var(--primary, #4f46e5);
    background: rgba(79, 70, 229, 0.08);
    border-color: rgba(79, 70, 229, 0.2);
    font-weight: 600;
}

.reject-param-comment {
    padding: 0 0.9rem 0.85rem;
}

/* Edit Modal Styles */
.edit-kpi-list {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.edit-kpi-item {
    background: #ffffff;
    border-radius: 14px;
    padding: 1.25rem;
    border: 1px solid var(--border, #e2e8f0);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.edit-kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border, #e2e8f0);
}

.edit-kpi-header h6 {
    margin: 0;
    color: var(--dark, #0f172a);
    font-weight: 700;
    font-size: 0.98rem;
    flex: 1;
}

.edit-kpi-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.form-group label {
    font-weight: 600;
    font-size: 0.82rem;
    color: #334155;
    margin-bottom: 0.4rem;
    display: flex;
    align-items: center;
}

.form-control {
    border: 1.5px solid var(--border, #cbd5e1);
    border-radius: 10px;
    padding: 0.65rem 0.85rem;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    background: var(--surface, #fff);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary, #4f46e5);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.form-control[readonly] {
    background: #f8fafc;
    color: #475569;
    border-color: #e2e8f0;
}

/* Data Source Section */
.data-source-edit-section {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px dashed var(--border, #e2e8f0);
}

.data-source-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.data-source-label i {
    color: var(--primary, #4f46e5);
}

.data-source-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 0.75rem 0.9rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
}

.data-source-card.is-replaced {
    border-color: #22c55e;
    background: rgba(34, 197, 94, 0.06);
}

.data-source-card.has-error {
    border-color: #ef4444;
    background: rgba(239, 68, 68, 0.06);
}

.ds-fileinfo {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    min-width: 0;
    flex: 1 1 auto;
}

.ds-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #e2e8f0;
    font-size: 1.15rem;
    color: var(--primary, #4f46e5);
}

.data-source-card.is-replaced .ds-icon {
    color: #16a34a;
    border-color: #86efac;
}

.ds-meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.ds-name {
    font-size: 0.88rem;
    font-weight: 600;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 260px;
}

.ds-status {
    font-size: 0.75rem;
    margin-top: 1px;
}

.ds-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 0 0 auto;
}

.ds-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.15rem;
    padding: 0.42rem 0.85rem;
    font-size: 0.8rem;
    font-weight: 600;
    line-height: 1;
    border-radius: 9px;
    border: 1px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    transition: transform 0.12s ease, background 0.2s ease, box-shadow 0.2s ease;
    margin-bottom: 0;
}

.ds-btn:active {
    transform: translateY(1px);
}

.ds-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.ds-btn-view {
    background: #fff;
    border-color: #cbd5e1;
    color: #475569;
}

.ds-btn-view:hover:not(:disabled) {
    border-color: var(--primary, #4f46e5);
    color: var(--primary, #4f46e5);
}

.ds-btn-replace {
    background: var(--primary, #4f46e5);
    color: #fff;
    box-shadow: 0 1px 3px rgba(99, 102, 241, 0.35);
}

.ds-btn-replace:hover {
    background: #4338ca;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.4);
}

.ds-btn-cancel {
    background: #fff;
    border-color: #fca5a5;
    color: #dc2626;
}

.ds-btn-cancel:hover {
    background: #fef2f2;
}

.data-source-hint {
    display: inline-flex;
    align-items: center;
    margin-top: 0.45rem;
    font-size: 0.76rem;
}

/* Confirmation & Summary Modal Styles */
.confirm-icon-wrap {
    display: flex;
    justify-content: center;
}

.confirm-icon-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.confirm-icon-circle.success {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    border: 2px solid rgba(16, 185, 129, 0.25);
}

.confirm-icon-circle.danger {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
    border: 2px solid rgba(239, 68, 68, 0.25);
}

.confirm-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--dark, #0f172a);
    margin: 0.5rem 0 0.25rem 0;
}

.confirm-desc {
    font-size: 0.9rem;
    color: #64748b;
    margin-bottom: 1.25rem;
}

.approval-summary {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.85rem 1.15rem;
    margin-top: 1rem;
    text-align: left;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.88rem;
}

.summary-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.summary-item:first-child {
    padding-top: 0;
}

.summary-key {
    color: #64748b;
    font-weight: 500;
    display: flex;
    align-items: center;
}

.summary-val {
    color: #0f172a;
    font-weight: 600;
}

/* Bulk Reject Modal Styles */
.bulk-reject-content {
    padding: 0.5rem;
}

.selected-list {
    max-height: 220px;
    overflow-y: auto;
    margin: 1rem 0;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.5rem;
    background: #f8fafc;
    text-align: left;
}

.selected-item {
    padding: 0.55rem 0.75rem;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.86rem;
    display: flex;
    align-items: center;
}

.selected-item:last-child {
    border-bottom: none;
}

/* History Card Styles */
.history-card {
    background: var(--surface);
    border-radius: 12px;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--border);
    overflow: hidden;
    transition: all 0.3s ease;
}

.history-card.status-approved {
    border-left-color: var(--success);
}

.history-card.status-rejected {
    border-left-color: var(--danger);
}

.history-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Modal Footer */
.wrapper-button-footer {
    display: flex;
    justify-content: end;
    align-items: center;
    padding: 0;
    gap: 0.5rem;
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
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
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

.fade-switch-enter-active,
.fade-switch-leave-active {
    transition: all 0.3s ease;
}

.fade-switch-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.fade-switch-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

/* Responsive Design */
@media (max-width: 768px) {
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

    .detail-header {
        padding: 1rem;
        gap: 1rem;
    }

    .detail-header-main {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .performance-summary {
        width: 100%;
        min-width: auto;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
    }

    .summary-card {
        flex-direction: column;
        text-align: center;
        padding: 0.75rem 0.4rem;
        gap: 0.35rem;
        min-width: 0;
    }

    .summary-icon {
        width: 36px;
        height: 36px;
        font-size: 1.05rem;
    }

    .summary-value {
        font-size: 1.15rem;
    }

    .summary-label {
        font-size: 0.65rem;
    }


    .info-grid {
        grid-template-columns: 1fr;
    }

    .kpi-header {
        /* flex-direction: column; */
        gap: 1rem;
    }

    .metrics-grid {
        grid-template-columns: 1fr;
    }

    .pica-grid {
        grid-template-columns: 1fr;
    }

    .form-row {
        flex-direction: column;
        gap: 0.75rem;
    }

    .card-grid {
        grid-template-columns: 1fr;
    }

    .bulk-actions-content {
        flex-direction: column;
        align-items: stretch;
    }

    .bulk-buttons {
        justify-content: center;
    }

    .card-footer {
        flex-direction: column;
    }

    .action-buttons-mobile {
        width: 100%;
        justify-content: stretch;
    }

    .action-buttons-mobile .btn-card {
        flex: 1;
        justify-content: center;
    }

    .wrapper-button-footer {
        flex-direction: column;
    }

    .action-buttons {
        width: 100%;
    }

    .action-buttons .btn-modern {
        flex: 1;
        justify-content: center;
    }
}

@media (max-width: 480px) {
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

    .filter-section {
        padding: 1rem;
    }

    .search-input {
        padding: 0.5rem 0.6rem 0.5rem 2rem;
        font-size: 0.85rem;
    }

    .kpi-meta {
        gap: 0.5rem;
    }

    .edit-kpi-header {
        flex-direction: column;
        gap: 0.5rem;
    }

    .review-metrics {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>

