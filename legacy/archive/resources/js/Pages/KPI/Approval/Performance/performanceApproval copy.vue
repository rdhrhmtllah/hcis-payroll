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
                                            class="bi bi-graph-up d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2
                                            class="title fw-bold text-white text-start"
                                        >
                                            Performance Approval
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
        <quick-stat :stats="quickStatField" />

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
                            <div class="col-md-6">
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
                            <div class="col-md-3">
                                <select
                                    v-model="filters.division"
                                    class="filter-select"
                                    @change="fetchApprovals"
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
                                    @change="fetchApprovals"
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
                                    <span class="filter-text"
                                        >Showing
                                        {{ filteredApprovals.length }}
                                        results</span
                                    >
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
                        <h4 class="empty-title">Tidak Ada Jadwal Approval</h4>
                        <p class="empty-description">
                            Saat ini tidak ada jadwal approval performance yang
                            aktif. Silakan hubungi administrator untuk informasi
                            lebih lanjut.
                        </p>
                        <div class="schedule-info-card">
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
                        </div>
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
                                                    <span>Score</span>
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
                                                                approval.create_at
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
                                                                approval
                                                                    .employee
                                                                    ?.Nama
                                                            )
                                                        }}
                                                    </div>
                                                    <div
                                                        class="employee-details"
                                                    >
                                                        <div
                                                            class="employee-name"
                                                        >
                                                            {{
                                                                approval
                                                                    .employee
                                                                    ?.Nama
                                                            }}
                                                        </div>
                                                        <div
                                                            class="employee-code"
                                                        >
                                                            {{
                                                                approval
                                                                    .employee
                                                                    ?.Kode_Karyawan
                                                            }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="division-cell">
                                                    {{
                                                        approval.employee
                                                            ?.Department
                                                    }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="period-cell">
                                                    {{ approval.Periode }}
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
                                                                            approval
                                                                        ) + '%',
                                                                }"
                                                                :class="
                                                                    getScoreClass(
                                                                        approval.total_score
                                                                    )
                                                                "
                                                            ></div>
                                                        </div>
                                                        <span
                                                            class="score-value"
                                                        >
                                                            {{
                                                                Number(
                                                                    approval.total_score ||
                                                                        0
                                                                ).toFixed(0)
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
                                                            approval.Flag_Approval
                                                        )}`"
                                                    >
                                                        <i
                                                            :class="
                                                                getStatusIcon(
                                                                    approval.Flag_Approval
                                                                )
                                                            "
                                                        ></i>
                                                        {{
                                                            getStatusText(
                                                                approval.Flag_Approval
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
                                                                approval
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
                                                                approval
                                                            )
                                                        "
                                                        title="Edit"
                                                    >
                                                        <i
                                                            class="bi bi-pencil d-flex justify-content-center align-items-center"
                                                        ></i>
                                                    </button>
                                                    <button
                                                        class="btn-action btn-detail"
                                                        @click.stop="
                                                            showDetailModal(
                                                                approval
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
                                                            approval.create_at
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="status-badge-mobile"
                                            :class="`status-${getStatusClass(
                                                approval.Flag_Approval
                                            )}`"
                                        >
                                            {{
                                                getStatusText(
                                                    approval.Flag_Approval
                                                )
                                            }}
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="employee-info-mobile">
                                            <div class="avatar-sm">
                                                {{
                                                    getInitials(
                                                        approval.employee?.Nama
                                                    )
                                                }}
                                            </div>
                                            <div
                                                class="employee-details-mobile"
                                            >
                                                <div class="employee-name">
                                                    {{
                                                        approval.employee?.Nama
                                                    }}
                                                </div>
                                                <div class="employee-meta">
                                                    {{
                                                        approval.employee
                                                            ?.Kode_Karyawan
                                                    }}
                                                </div>
                                                <div class="employee-meta">
                                                    {{
                                                        approval.employee
                                                            ?.Department
                                                    }}
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
                                                    class="bi bi-speedometer2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>
                                                    {{
                                                        Number(
                                                            approval.total_score ||
                                                                0
                                                        ).toFixed(0)
                                                    }}% Score</span
                                                >
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
                                        </div>

                                        <div class="score-progress-mobile">
                                            <div class="progress-bar">
                                                <div
                                                    class="progress-fill"
                                                    :style="{
                                                        width:
                                                            calculateScorePercentage(
                                                                approval
                                                            ) + '%',
                                                    }"
                                                    :class="
                                                        getScoreClass(
                                                            approval.total_score
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
                            <h4>No Performance Approvals Found</h4>
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
                                <select
                                    v-model="historyFilters.division"
                                    class="filter-select"
                                    @change="fetchHistory"
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
                                    @change="fetchHistory"
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
                                    @change="fetchHistory"
                                >
                                    <option value="">All Status</option>
                                    <option value="approved">Disetujui</option>
                                    <option value="rejected">Ditolak</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button
                                    class="btn-clear-filters w-100"
                                    @click="clearHistoryFilters"
                                >
                                    <i
                                        class="d-flex justify-content-center align-items-center bi bi-arrow-clockwise"
                                    ></i>
                                    Reset
                                </button>
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
                                        @click="showDetailModal(approval)"
                                    >
                                        <td>
                                            <div class="transaction-cell">
                                                <div class="transaction-id">
                                                    {{ approval.No_Transaksi }}
                                                </div>
                                                <div class="transaction-time">
                                                    {{
                                                        formatTime(
                                                            approval.update_at
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
                                                            approval.employee
                                                                ?.Nama
                                                        )
                                                    }}
                                                </div>
                                                <div class="employee-details">
                                                    <div class="employee-name">
                                                        {{
                                                            approval.employee
                                                                ?.Nama
                                                        }}
                                                    </div>
                                                    <div class="employee-code">
                                                        {{
                                                            approval.employee
                                                                ?.Kode_Karyawan
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="division-cell">
                                                {{
                                                    approval.employee
                                                        ?.Department
                                                }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="period-cell">
                                                {{ approval.Periode }}
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
                                                                        approval
                                                                    ) + '%',
                                                            }"
                                                            :class="
                                                                getScoreClass(
                                                                    approval.total_score
                                                                )
                                                            "
                                                        ></div>
                                                    </div>
                                                    <span class="score-value">
                                                        {{
                                                            Number(
                                                                approval.total_score ||
                                                                    0
                                                            ).toFixed(0)
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
                                                        approval.Flag_Approval
                                                    )}`"
                                                >
                                                    <i
                                                        :class="
                                                            getStatusIcon(
                                                                approval.Flag_Approval
                                                            )
                                                        "
                                                    ></i>
                                                    {{
                                                        getStatusText(
                                                            approval.Flag_Approval
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
                                                            approval
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
                                    approval.Flag_Approval
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
                                                        formatDate(
                                                            approval.update_at
                                                        )
                                                    }}
                                                </div>
                                                <div
                                                    class="status-badge-mobile"
                                                    :class="`status-${getStatusClass(
                                                        approval.Flag_Approval
                                                    )}`"
                                                >
                                                    {{
                                                        getStatusText(
                                                            approval.Flag_Approval
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
                                            {{
                                                getInitials(
                                                    approval.employee?.Nama
                                                )
                                            }}
                                        </div>
                                        <div class="employee-details-mobile">
                                            <div class="employee-name">
                                                {{ approval.employee?.Nama }}
                                            </div>
                                            <div class="employee-meta">
                                                {{
                                                    approval.employee
                                                        ?.Kode_Karyawan
                                                }}
                                            </div>
                                            <div class="employee-meta">
                                                {{
                                                    approval.employee
                                                        ?.Department
                                                }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="performance-summary-mobile">
                                        <div class="summary-item">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-calendar-range"
                                            ></i>
                                            <span>{{ approval.Periode }}</span>
                                        </div>
                                        <div class="summary-item">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-speedometer2"
                                            ></i>
                                            <span
                                                >{{
                                                    Number(
                                                        approval.total_score ||
                                                            0
                                                    ).toFixed(0)
                                                }}% Score</span
                                            >
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
            :title="`Performance Details - ${selectedApproval?.employee?.Nama}`"
            size="large"
            @close="closeDetailModal"
        >
            <template #body>
                <div class="performance-detail-content" v-if="selectedApproval">
                    <!-- Header Info -->
                    <div class="detail-header">
                        <div class="employee-info">
                            <div class="avatar-lg">
                                {{
                                    getInitials(selectedApproval.employee?.Nama)
                                }}
                            </div>
                            <div class="employee-details">
                                <h5>{{ selectedApproval.employee?.Nama }}</h5>
                                <p>
                                    {{
                                        selectedApproval.employee?.Kode_Karyawan
                                    }}
                                    •
                                    {{ selectedApproval.employee?.Department }}
                                </p>
                                <p class="text-muted">
                                    {{ selectedApproval.employee?.Jabatan }}
                                </p>
                            </div>
                        </div>
                        <div class="performance-summary">
                            <div class="summary-card">
                                <div class="summary-icon score">
                                    <i
                                        class="bi bi-speedometer2 d-flex justify-content-center align-items-center"
                                    ></i>
                                </div>
                                <div class="summary-content">
                                    <div class="summary-value">
                                        {{
                                            Number(
                                                selectedApproval.total_score ||
                                                    0
                                            ).toFixed(0)
                                        }}%
                                    </div>
                                    <div class="summary-label">Achivement</div>
                                </div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-icon kpi">
                                    <i
                                        class="bi bi-list-task d-flex justify-content-center align-items-center"
                                    ></i>
                                </div>
                                <div class="summary-content">
                                    <div class="summary-value">
                                        {{ selectedApproval.kpi_count }}
                                    </div>
                                    <div class="summary-label">Total KPI</div>
                                </div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-icon pica">
                                    <i
                                        class="bi bi-exclamation-triangle d-flex justify-content-center align-items-center"
                                    ></i>
                                </div>
                                <div class="summary-content">
                                    <div class="summary-value">
                                        {{ selectedApproval.pica_count }}
                                    </div>
                                    <div class="summary-label">PICA Items</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction Info -->
                    <div class="transaction-info-section mb-4">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>No. Transaksi:</label>
                                <span>{{ selectedApproval.No_Transaksi }}</span>
                            </div>
                            <div class="info-item">
                                <label>Periode:</label>
                                <span>{{ selectedApproval.Periode }}</span>
                            </div>
                            <div class="info-item">
                                <label>Dibuat:</label>
                                <span>{{
                                    formatDateTime(selectedApproval.create_at)
                                }}</span>
                            </div>
                            <div class="info-item">
                                <label>Status:</label>
                                <span
                                    :class="`status-badge status-${getStatusClass(
                                        selectedApproval.Flag_Approval
                                    )}`"
                                >
                                    {{
                                        getStatusText(
                                            selectedApproval.Flag_Approval
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI Performance Section -->
                    <div class="kpi-performance-section">
                        <h6>KPI Performance Details</h6>
                        <div class="kpi-list">
                            <div
                                v-for="(
                                    kpi, index
                                ) in selectedApproval.kpiDetails"
                                :key="kpi.id"
                                class="kpi-performance-item"
                                :class="{
                                    'requires-pica': kpi.Flag_Pica === 'Y',
                                    disabled: kpi.Is_Disabled === 'Y',
                                }"
                            >
                                <div class="kpi-header">
                                    <div class="kpi-main-info">
                                        <div class="kpi-number">
                                            #{{ index + 1 }}
                                        </div>
                                        <div class="kpi-details">
                                            <h6>{{ kpi.parameter }}</h6>
                                            <div class="kpi-meta">
                                                <span class="category">{{
                                                    kpi.bsc_category
                                                }}</span>
                                                <span class="weight"
                                                    >{{ kpi.weight }}%</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div class="kpi-performance-indicator">
                                        <div
                                            class="achievement-badge"
                                            :class="
                                                getAchievementClass(
                                                    kpi.achievement
                                                )
                                            "
                                        >
                                            {{
                                                formatAchievement(
                                                    kpi.achievement
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
                                                <label>Target Value:</label>
                                                <span class="metric-value">{{
                                                    kpi.Nilai_Target
                                                }}</span>
                                            </div>
                                            <div class="metric-item">
                                                <label>Actual Value:</label>
                                                <span class="metric-value">{{
                                                    kpi.actual_value
                                                }}</span>
                                            </div>
                                            <div class="metric-item">
                                                <label>Unit:</label>
                                                <span class="metric-value">{{
                                                    kpi.unit
                                                }}</span>
                                            </div>
                                            <div class="metric-item">
                                                <label>Score:</label>
                                                <span
                                                    class="metric-value score"
                                                >
                                                    {{
                                                        Number(
                                                            kpi.score || 0
                                                        ).toFixed(0)
                                                    }}%
                                                </span>
                                            </div>
                                            <div class="metric-item">
                                                <label>Achievement:</label>
                                                <span class="metric-value"
                                                    >{{
                                                        formatAchievement(
                                                            kpi.achievement
                                                        )
                                                    }}%</span
                                                >
                                            </div>
                                            <div class="metric-item">
                                                <label>Weight:</label>
                                                <span class="metric-value"
                                                    >{{ kpi.weight }}%</span
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Target Description -->
                                    <div
                                        class="target-section"
                                        v-if="kpi.target_description"
                                    >
                                        <h6>Target Description</h6>
                                        <p class="target-description">
                                            {{ kpi.target_description }}
                                        </p>
                                    </div>

                                    <!-- PICA Section -->
                                    <div
                                        class="pica-section"
                                        v-if="kpi.pica && kpi.pica.length > 0"
                                    >
                                        <div class="pica-header">
                                            <h6>
                                                <i
                                                    class="bi bi-exclamation-triangle"
                                                ></i>
                                                PICA (Corrective Action)
                                            </h6>
                                        </div>
                                        <div class="pica-content">
                                            <div class="pica-grid">
                                                <div class="pica-item">
                                                    <label
                                                        >Problem
                                                        Identification:</label
                                                    >
                                                    <p>
                                                        {{
                                                            kpi.pica[0]
                                                                .problem_identification
                                                        }}
                                                    </p>
                                                </div>
                                                <div class="pica-item">
                                                    <label
                                                        >Corrective
                                                        Actions:</label
                                                    >
                                                    <p>
                                                        {{
                                                            kpi.pica[0]
                                                                .corrective_actions
                                                        }}
                                                    </p>
                                                </div>
                                                <div class="pica-item">
                                                    <label>Due Date:</label>
                                                    <p>
                                                        {{
                                                            formatDate(
                                                                kpi.pica[0]
                                                                    .due_date
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
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <!-- <button
                        class="btn-modern btn-secondary"
                        @click="closeDetailModal"
                    >
                        <i
                            class="d-flex justify-content-center align-items-center bi bi-x-circle"
                        ></i>
                        Tutup
                    </button> -->
                    <div
                        class="action-buttons"
                        v-if="selectedApproval?.Flag_Approval === null"
                    >
                        <button
                            class="btn-modern btn-danger"
                            @click="openRejectModal(selectedApproval)"
                        >
                            <i
                                class="d-flex justify-content-center align-items-center bi bi-x-lg"
                            ></i>
                            Tolak
                        </button>
                        <button
                            class="btn-modern btn-warning"
                            @click="openEditModal(selectedApproval)"
                        >
                            <i
                                class="d-flex justify-content-center align-items-center bi bi-pencil"
                            ></i>
                            Edit
                        </button>
                        <button
                            class="btn-modern btn-success"
                            @click="openApproveModal(selectedApproval)"
                        >
                            <i
                                class="d-flex justify-content-center align-items-center bi bi-check-lg"
                            ></i>
                            Terima
                        </button>
                    </div>
                </div>
            </template>
        </Modal>

        <!-- Reject Modal -->
        <Modal
            v-if="showRejectModal"
            :show="showRejectModal"
            :title="`Reject Performance - ${selectedApproval?.employee?.Nama}`"
            size="large"
            @close="closeRejectModal"
        >
            <template #body>
                <div class="reject-modal-content">
                    <div class="alert alert-warning">
                        <i
                            class="d-flex justify-content-center align-items-center bi bi-exclamation-triangle"
                        ></i>
                        <strong>Perhatian:</strong> Berikan alasan penolakan
                        untuk KPI yang bermasalah
                    </div>

                    <div class="rejection-kpi-selection mb-4">
                        <h6>Pilih KPI yang Perlu Revisi</h6>
                        <p class="text-muted small">
                            Centang KPI yang memerlukan perbaikan dan berikan
                            komentar
                        </p>

                        <div class="kpi-selection-list">
                            <div
                                v-for="(
                                    kpi, index
                                ) in selectedApproval?.kpiDetails || []"
                                :key="kpi.id"
                                class="kpi-selection-item"
                            >
                                <div class="kpi-selection-header">
                                    <label class="kpi-checkbox">
                                        <input
                                            type="checkbox"
                                            v-model="
                                                rejectionKpiSelection[index]
                                            "
                                            @change="
                                                handleKpiSelectionChange(index)
                                            "
                                        />
                                        <span class="checkmark"></span>
                                    </label>
                                    <div class="kpi-selection-info">
                                        <strong>{{ kpi.parameter }}</strong>
                                        <div class="kpi-meta">
                                            <span class="category">{{
                                                kpi.bsc_category
                                            }}</span>
                                            <span class="weight"
                                                >{{ kpi.weight }}%</span
                                            >
                                            <span class="achievement"
                                                >{{
                                                    formatAchievement(
                                                        kpi.achievement
                                                    )
                                                }}%</span
                                            >
                                            <span
                                                v-if="
                                                    kpi.pica &&
                                                    kpi.pica.length > 0
                                                "
                                                class="pica-indicator-selection"
                                            >
                                                <i
                                                    class="bi bi-exclamation-triangle"
                                                ></i>
                                                Ada PICA
                                            </span>
                                            <span
                                                v-else
                                                class="pica-indicator-selection no-pica"
                                            >
                                                <i
                                                    class="bi bi-check-circle"
                                                ></i>
                                                No PICA
                                            </span>
                                        </div>
                                        <div class="review-metrics">
                                            <span
                                                >Target:
                                                {{ kpi.Nilai_Target }}</span
                                            >
                                            <span
                                                >Actual:
                                                {{ kpi.actual_value }}</span
                                            >
                                            <span
                                                >Score:
                                                {{
                                                    Number(
                                                        kpi.score || 0
                                                    ).toFixed(0)
                                                }}%</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="kpi-comment"
                                    v-if="rejectionKpiSelection[index]"
                                >
                                    <textarea
                                        v-model="rejectionComments[index]"
                                        class="form-control kpi-comment-textarea"
                                        rows="3"
                                        :placeholder="`Masukkan alasan penolakan untuk ${kpi.parameter}...`"
                                        required
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"
                            >Overall Rejection Reason *</label
                        >
                        <textarea
                            v-model="rejectionReason"
                            class="form-control"
                            rows="4"
                            placeholder="Berikan alasan keseluruhan untuk penolakan performance ini..."
                            :class="{ 'is-invalid': rejectionError }"
                            required
                        ></textarea>
                        <div v-if="rejectionError" class="error-message">
                            {{ rejectionError }}
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
            :title="`Edit Performance - ${selectedApproval?.employee?.Nama}`"
            size="large"
            @close="closeEditModal"
        >
            <template #body>
                <div class="edit-modal-content" v-if="selectedApproval">
                    <div class="alert alert-info">
                        <i
                            class="d-flex justify-content-center align-items-center bi bi-info-circle"
                        ></i>
                        <strong>Edit Performance:</strong> Anda dapat mengubah
                        nilai actual dan menambahkan PICA sebelum menyetujui
                    </div>

                    <div class="edit-kpi-list">
                        <div
                            v-for="(kpi, index) in selectedApproval.kpiDetails"
                            :key="kpi.id"
                            class="edit-kpi-item"
                        >
                            <div class="edit-kpi-header">
                                <h6>{{ kpi.parameter }}</h6>
                                <div class="kpi-meta">
                                    <span
                                        class="badge d-flex justify-content-center align-items-center"
                                        >{{ kpi.bsc_category }}</span
                                    >
                                    <!-- <span class="weight"
                                        >{{ kpi.weight }}%</span
                                    > -->
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

                            <div class="edit-kpi-content">
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Target Value</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            :value="kpi.Nilai_Target"
                                            disabled
                                        />
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Unit</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            :value="kpi.unit"
                                            disabled
                                        />
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Actual Value *</label>
                                        <!-- <input
                                            type="number"
                                            step="0.01"
                                            class="form-control"
                                            v-model="editActualValues[index]"
                                            :placeholder="`Masukkan actual ${kpi.unit}`"
                                            @input="
                                                handleActualValueChange(
                                                    index,
                                                    kpi
                                                )
                                            "
                                        /> -->
                                        <input
                                            type="text"
                                            class="form-control"
                                            :value="
                                                convertValueUnit(
                                                    editActualValues[index] ||
                                                        0,
                                                    kpi.format_type,
                                                    kpi.unit
                                                )
                                            "
                                            @input="
                                                handleInput($event, index, kpi)
                                            "
                                            :placeholder="`Masukkan actual ${kpi.unit}`"
                                        />

                                        <small class="form-text text-muted">
                                            Previous: {{ kpi.actual_value }}
                                            {{ kpi.unit }}
                                        </small>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Current Score</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            :value="
                                                Number(kpi.score || 0).toFixed(
                                                    0
                                                ) + '%'
                                            "
                                            disabled
                                        />
                                    </div>
                                </div>

                                <!-- PICA Section - Auto show jika actual < target -->
                                <div
                                    class="pica-edit-section"
                                    v-if="shouldShowPicaSection(index, kpi)"
                                >
                                    <div class="pica-toggle">
                                        <span
                                            class="pica-warning"
                                            v-if="
                                                isActualBelowTarget(index, kpi)
                                            "
                                        >
                                            <i
                                                class="bi bi-exclamation-triangle"
                                            ></i>
                                            Actual value di bawah target, wajib
                                            untuk menambahkan PICA
                                        </span>
                                    </div>

                                    <div class="pica-form">
                                        <div class="form-group">
                                            <label
                                                >Problem Identification *</label
                                            >
                                            <textarea
                                                v-model="
                                                    editPicaData[index]
                                                        .problem_identification
                                                "
                                                class="form-control"
                                                rows="3"
                                                placeholder="Jelaskan akar masalah yang ditemukan..."
                                                required
                                            ></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label>Corrective Actions *</label>
                                            <textarea
                                                v-model="
                                                    editPicaData[index]
                                                        .corrective_actions
                                                "
                                                class="form-control"
                                                rows="3"
                                                placeholder="Jelaskan langkah-langkah perbaikan segera..."
                                                required
                                            ></textarea>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label>Due Date *</label>
                                                <input
                                                    type="date"
                                                    class="form-control"
                                                    v-model="
                                                        editPicaData[index]
                                                            .due_date
                                                    "
                                                    required
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="current-pica"
                                        v-if="kpi.pica && kpi.pica.length > 0"
                                    >
                                        <h6>Current PICA</h6>
                                        <div class="current-pica-content">
                                            <p>
                                                <strong>Problem:</strong>
                                                {{
                                                    kpi.pica[0]
                                                        .problem_identification
                                                }}
                                            </p>
                                            <p>
                                                <strong>Actions:</strong>
                                                {{
                                                    kpi.pica[0]
                                                        .corrective_actions
                                                }}
                                            </p>
                                            <p>
                                                <strong>Due:</strong>
                                                {{
                                                    formatDate(
                                                        kpi.pica[0].due_date
                                                    )
                                                }}
                                            </p>
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
                        :disabled="loading.edit"
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

        <!--  -->

        <!-- Approve Modal -->
        <Modal
            v-if="showApproveModal"
            :show="showApproveModal"
            :title="`Approve Performance - ${selectedApproval?.employee?.Nama}`"
            size="medium"
            @close="closeApproveModal"
        >
            <template #body>
                <div class="approve-modal-content">
                    <div class="approval-confirmation">
                        <div
                            class="d-flex justify-content-center align-items-center mb-4"
                        >
                            <i
                                class="d-flex justify-content-center align-items-center bi bi-check-circle-fill text-success fs-1"
                            ></i>
                        </div>
                        <h5>Confirm Approval</h5>
                        <p>
                            Are you sure you want to approve this performance
                            submission?
                        </p>
                        <div class="approval-summary">
                            <div class="summary-item">
                                <strong>Employee:</strong>
                                {{ selectedApproval?.employee?.Nama }}
                            </div>
                            <div class="summary-item">
                                <strong>Period:</strong>
                                {{ selectedApproval?.Periode }}
                            </div>
                            <div class="summary-item">
                                <strong>Total Score:</strong>
                                {{ selectedApproval?.total_score }}%
                            </div>
                            <div class="summary-item">
                                <strong>PICA Items:</strong>
                                {{ selectedApproval?.pica_count }}
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
                                : "Confirm Approve"
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
                            <i
                                class="bi bi-exclamation-triangle text-warning fs-1 mb-3"
                            ></i>
                            <h5>
                                Reject
                                {{ selectedApprovals.length }} Submissions
                            </h5>
                            <p class="text-muted">
                                You are about to reject the following
                                submissions:
                            </p>

                            <div class="selected-list">
                                <div
                                    v-for="id in selectedApprovals"
                                    :key="id"
                                    class="selected-item"
                                >
                                    {{ getApprovalById(id)?.No_Transaksi }} -
                                    {{ getApprovalById(id)?.employee?.Nama }}
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <label class="form-label">Rejection Reason *</label>
                            <textarea
                                v-model="bulkRejectionReason"
                                class="form-control"
                                rows="4"
                                placeholder="Please provide a reason for rejection..."
                                :class="{ 'is-invalid': bulkRejectionError }"
                            ></textarea>
                            <div
                                v-if="bulkRejectionError"
                                class="error-message"
                            >
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
                            class="bi bi-x-lg me-2 d-flex justify-content-center align-items-center"
                        ></i>
                        {{
                            loading.bulkReject ? "Processing..." : "Reject All"
                        }}
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import { ElMessage } from "element-plus";
import quickStat from "@/components/ui/quickStat.vue";
import ApprovalTabs from "../../components/ui/toggleSection.vue";
import axios from "axios";
import Modal from "@/components/ui/modalV2-noir.vue";

export default {
    name: "PerformanceApproval",
    components: {
        quickStat,
        ApprovalTabs,
        Modal,
    },
    data() {
        return {
            currentDate: new Date(),
            approvals: [],
            historyApprovals: [],
            selectedApprovals: [],
            loading: {
                fetch: false,
                history: false,
                approve: false,
                reject: false,
                edit: false,
                bulkReject: false,
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
            editActualValues: [],
            editPicaData: [],
            isMobile: window.innerWidth < 768,
            activeTab: "queue",
            approvalTabs: [
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
                division: "",
                level: "",
            },
            historyFilters: {
                search: "",
                division: "",
                level: "",
                status: "",
            },
            filterOptions: {
                levels: [],
                divisions: [],
            },
            searchTimeout: null,
            hasApprovalSchedule: true, // Dummy state untuk simulasi jadwal approval
        };
    },
    computed: {
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
            return this.approvals.filter((a) => a.Flag_Approval === null)
                .length;
        },
        approvedCount() {
            return this.approvals.filter((a) => a.Flag_Approval === "Y").length;
        },
        rejectedCount() {
            return this.approvals.filter((a) => a.Flag_Approval === "N").length;
        },
        filteredApprovals() {
            return this.approvals.filter(
                (approval) => approval.Flag_Approval === null
            );
        },
        isAllSelected() {
            return (
                this.filteredApprovals.length > 0 &&
                this.selectedApprovals.length === this.filteredApprovals.length
            );
        },
    },
    methods: {
        formatInputValue(value, format) {
            if (value == null || value === "") return "";

            switch (format) {
                case "currency":
                    return new Intl.NumberFormat("id-ID", {
                        style: "currency",
                        currency: "IDR",
                        minimumFractionDigits: 0,
                    }).format(value);
                case "percent":
                    return `${value}%`;
                default:
                    return value;
            }
        },

        handleInput(event, index, kpi) {
    // Ambil angka mentah dari input (hapus simbol, spasi, dsb)
    const rawValue = event.target.value.replace(/[^0-9.,-]/g, "");
    this.editActualValues[index] = rawValue;
  },

  formatOnBlur(index, kpi) {
    // Saat input kehilangan fokus (blur), tampilkan hasil format rapi
    const value = parseFloat(this.editActualValues[index]) || 0;
    this.editActualValues[index] = this.convertValueUnit(
      value,
      kpi.format_type,
      kpi.unit
    );
  },
        convertValueUnit(value, format, simbol = null) {

            console.log(value);
            if (!format || !value) return "Undefined";

            let simbolSatuan = simbol ? simbol?.trim() : "";
            // console.log(simbolSatuan);
            let decimals = 2;
            let result = "";

            if (format == "currency") {
                result = new Intl.NumberFormat("id-ID").format(value);
            } else if (format == "percentage") {
                const numericValue = parseFloat(value) || 0;
                const strValue = value.toString();

                const userInputDecimal =
                    strValue.includes(".") || strValue.includes(",");

                if (!userInputDecimal && numericValue % 1 === 0) {
                    result = `${numericValue}`;
                }

                result = `${numericValue.toFixed(decimals)}`;
            } else {
                const numericValue = parseFloat(value) || 0;
                result = new Intl.NumberFormat("id-ID", {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(numericValue);
            }

            return format == "currency"
                ? simbolSatuan + ". " + result
                : result + " " + simbolSatuan;
        },
        parseFormattedInput(rawValue, format) {
            if (!rawValue) return 0;

            // Hapus semua karakter non-digit dan tanda koma
            const clean = rawValue.replace(/[^\d.,-]/g, "").replace(",", ".");
            const number = parseFloat(clean) || 0;

            if (format === "percentage") {
                return number;
            }

            return number;
        },

        handleInput(event, index, kpi) {
            const format = kpi.format_type || "number";
            const simbol = kpi.unit || "";
            const raw = event.target.value;

            // Parse string terformat → number murni
            const parsed = this.parseFormattedInput(raw, format);
            this.editActualValues[index] = parsed;

            // Tampilkan versi terformat kembali di input
            event.target.value = this.convertValueUnit(parsed, format, simbol);

            // Jalankan fungsi existing kalau ada
            this.handleActualValueChange(index, kpi);
        },

        async fetchApprovals() {
            this.loading.fetch = true;
            try {
                const params = {
                    search: this.filters.search,
                    division: this.filters.division,
                    level: this.filters.level,
                };

                Object.keys(params).forEach((key) => {
                    if (params[key] === "" || params[key] === null) {
                        delete params[key];
                    }
                });
                this.approvals = [];
                const response = await axios.get(
                    "/performance-approval/approvals",
                    { params }
                );

                if (response.data?.success) {
                    this.approvals = response.data.result;
                    this.approvalTabs[0].count = this.pendingCount;

                    // Simulasi: jika tidak ada data, anggap tidak ada jadwal approval
                    this.hasApprovalSchedule = this.approvals.length > 0;
                }
            } catch (error) {
                console.error("Error fetching performance approvals:", error);
                this.showSimpleAlert(
                    "Failed to load performance approvals",
                    "error"
                );
            } finally {
                this.loading.fetch = false;
            }
        },

        async fetchHistory() {
            this.loading.history = true;
            try {
                const params = {
                    search: this.historyFilters.search,
                    division: this.historyFilters.division,
                    level: this.historyFilters.level,
                    status: this.historyFilters.status,
                    history: true,
                };

                Object.keys(params).forEach((key) => {
                    if (params[key] === "" || params[key] === null) {
                        delete params[key];
                    }
                });

                const response = await axios.get(
                    "/performance-approval/approvals",
                    { params }
                );

                if (response.data?.success) {
                    this.historyApprovals = response.data.result;
                    this.approvalTabs[1].count = this.historyApprovals.length;
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
                    axios.get("/performance-approval/filter-options/levels"),
                    axios.get("/performance-approval/filter-options/divisions"),
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
                    `/performance-approval/approvals/${this.selectedApproval.Id_Approval_Request}/approve`
                );
                this.showSimpleAlert(
                    "Performance approved successfully",
                    "success"
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
            if (!this.rejectionReason.trim()) {
                this.rejectionError = "Overall rejection reason is required";
                return;
            }

            // Validate that at least one KPI is selected with comment
            const hasSelectedKpiWithComment = this.rejectionKpiSelection.some(
                (selected, index) => {
                    return selected && this.rejectionComments[index]?.trim();
                }
            );

            if (!hasSelectedKpiWithComment) {
                this.rejectionError =
                    "Please select at least one KPI and provide rejection comments";
                return;
            }

            this.loading.reject = true;
            try {
                // Prepare KPI comments payload
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
                    `/performance-approval/approvals/${this.selectedApproval.Id_Approval_Request}/reject`,
                    payload
                );

                this.showSimpleAlert(
                    "Performance submission rejected successfully",
                    "success"
                );
                this.closeRejectModal();
                this.closeDetailModal();
                await this.fetchApprovals();
                await this.fetchHistory();
            } catch (error) {
                console.error("Error rejecting performance submission:", error);
                this.showSimpleAlert(
                    "Failed to reject performance submission",
                    "error"
                );
            } finally {
                this.loading.reject = false;
            }
        },

        async confirmEditAndApprove() {
            // Validate PICA data if actual below target
            for (let i = 0; i < this.selectedApproval.kpiDetails.length; i++) {
                const kpi = this.selectedApproval.kpiDetails[i];
                if (this.isActualBelowTarget(i, kpi)) {
                    const picaData = this.editPicaData[i];
                    if (
                        !picaData.problem_identification?.trim() ||
                        !picaData.corrective_actions?.trim() ||
                        !picaData.due_date
                    ) {
                        this.showSimpleAlert(
                            "Please complete all required PICA fields for KPI: " +
                                kpi.parameter +
                                " (Actual value below target)",
                            "error"
                        );
                        return;
                    }
                }
            }

            this.loading.edit = true;
            try {
                // Prepare actual values payload
                const actualValues = {};
                this.editActualValues.forEach((value, index) => {
                    if (value !== undefined && value !== null && value !== "") {
                        const kpiId =
                            this.selectedApproval.kpiDetails[index].id;
                        actualValues[kpiId] = value;
                    }
                });

                // Prepare PICA data payload
                const picaData = {};
                this.selectedApproval.kpiDetails.forEach((kpi, index) => {
                    if (this.isActualBelowTarget(index, kpi)) {
                        const kpiId = kpi.id;
                        picaData[kpiId] = {
                            problem_identification:
                                this.editPicaData[index].problem_identification,
                            corrective_actions:
                                this.editPicaData[index].corrective_actions,
                            due_date: this.editPicaData[index].due_date,
                        };
                    }
                });

                const payload = {
                    actual_values: actualValues,
                    pica_data: picaData,
                };

                await axios.post(
                    `/performance-approval/approvals/${this.selectedApproval.Id_Approval_Request}/edit-and-approve`,
                    payload
                );

                this.showSimpleAlert(
                    "Performance edited and approved successfully",
                    "success"
                );
                this.closeEditModal();
                this.closeDetailModal();
                await this.fetchApprovals();
                await this.fetchHistory();
            } catch (error) {
                console.error(
                    "Error editing and approving performance:",
                    error
                );
                this.showSimpleAlert(
                    "Failed to edit and approve performance",
                    "error"
                );
            } finally {
                this.loading.edit = false;
            }
        },

        updateKpiMetrics(item) {
            if (item.actual === null || item.actual === "" || !item.rumus) {
                item.achievement = 0;
                item.score = 0;
                return;
            }

            let achievementRatio = 0;
            const actual = parseFloat(item.actual);

            try {
                let formula = item.rumus.replace(/\[actual\]/g, actual);
                achievementRatio = new Function("return " + formula)();

                if (!isFinite(achievementRatio)) {
                    achievementRatio = 0;
                }
            } catch (e) {
                console.error("Error evaluating formula:", item.rumus, e);
                achievementRatio = 0;
            }

            item.achievement = achievementRatio * 100;
            item.score = item.weight * achievementRatio;
        },

        handleKpiSelectionChange(index) {
            if (!this.rejectionKpiSelection[index]) {
                this.rejectionComments[index] = "";
            }
        },

        handleActualValueChange(index, kpi) {
            const newValue = parseFloat(this.editActualValues[index]);
            const targetValue = parseFloat(kpi.Nilai_Target);

            // Auto initialize PICA data jika actual value lebih kecil dari target
            if (
                !isNaN(newValue) &&
                !isNaN(targetValue) &&
                newValue < targetValue
            ) {
                // Initialize PICA data jika belum ada
                if (!this.editPicaData[index].problem_identification) {
                    this.editPicaData[index] = {
                        problem_identification: `Pencapaian aktual (${newValue}) di bawah target (${targetValue})`,
                        corrective_actions: "",
                        due_date: this.getDefaultDueDate(),
                    };
                }
            }
            this.updateKpiMetrics(kpi);
        },

        shouldShowPicaSection(index, kpi) {
            // Tampilkan PICA section jika:
            // 1. Sudah ada PICA sebelumnya, atau
            // 2. Actual value di bawah target
            return (
                (kpi.pica && kpi.pica.length > 0) ||
                this.isActualBelowTarget(index, kpi)
            );
        },

        isActualBelowTarget(index, kpi) {
            const newValue = parseFloat(this.editActualValues[index]);
            const targetValue = parseFloat(kpi.Nilai_Target);
            return (
                !isNaN(newValue) &&
                !isNaN(targetValue) &&
                newValue < targetValue
            );
        },

        getDefaultDueDate() {
            const date = new Date();
            date.setMonth(date.getMonth() + 1); // Default 1 bulan dari sekarang
            return date.toISOString().split("T")[0];
        },

        showDetailModal(approval) {
            this.selectedApproval = approval;
            this.showDetailModalFlag = true;
        },

        openRejectModal(approval) {
            this.selectedApproval = approval;
            this.rejectionReason = "";
            this.rejectionError = "";
            this.rejectionKpiSelection = new Array(
                approval.kpiDetails?.length || 0
            ).fill(false);
            this.rejectionComments = new Array(
                approval.kpiDetails?.length || 0
            ).fill("");
            this.showRejectModal = true;
        },

        async openEditModal(approval) {
            this.selectedApproval = approval;

            // Pastikan data KPI sudah siap
            await this.$nextTick();

            if (approval?.kpiDetails?.length) {
                this.editActualValues = approval.kpiDetails.map(
                    (kpi) => parseFloat(kpi.actual_value) || 0
                );

                this.editPicaData = approval.kpiDetails.map((kpi) => ({
                    problem_identification:
                        kpi.pica?.[0]?.problem_identification || "",
                    corrective_actions: kpi.pica?.[0]?.corrective_actions || "",
                    due_date: kpi.pica?.[0]?.due_date || "",
                }));
            } else {
                this.editActualValues = [];
                this.editPicaData = [];
            }

            this.showEditModal = true;
        },

        openApproveModal(approval) {
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
            this.showEditModal = false;
            this.selectedApproval = null;
            this.editActualValues = [];
            this.editPicaData = [];
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

        // Utility methods
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

        formatAchievement(value) {
            const num = Number(value);

            if (isNaN(num) || !isFinite(num)) {
                return 0;
            }

            return num % 1 === 0 ? num : num.toFixed(1);
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
                division: "",
                level: "",
            };
            this.fetchApprovals();
        },

        clearHistoryFilters() {
            this.historyFilters = {
                search: "",
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

        isSelected(id) {
            return this.selectedApprovals.includes(id);
        },

        toggleSelectAll() {
            if (this.isAllSelected) {
                this.selectedApprovals = [];
            } else {
                this.selectedApprovals = this.filteredApprovals.map(
                    (a) => a.id_Performance_Approval
                );
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
                case "N":
                    return "bi bi-x-circle";
                default:
                    return "bi bi-clock";
            }
        },

        formatDateString(dateString) {
            if (!dateString) return "";
            try {
                const date = new Date(dateString);
                const options = {
                    day: "numeric",
                    month: "long",
                    year: "numeric",
                };
                return new Intl.DateTimeFormat("id-ID", options).format(date);
            } catch (error) {
                return dateString;
            }
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
                            `/performance-approval/approvals/${approval.Id_Approval_Request}/approve`
                        );
                    }
                }
                this.showSimpleAlert(
                    `${this.selectedApprovals.length} performance submissions approved successfully`,
                    "success"
                );
                this.clearSelection();
                await this.fetchApprovals();
            } catch (error) {
                console.error("Error bulk approving:", error);
                this.showSimpleAlert(
                    "Failed to approve some performance submissions",
                    "error"
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
                        `/performance-approval/approvals/${id}/reject`,
                        {
                            reason: this.bulkRejectionReason,
                        }
                    );
                }
                this.showSimpleAlert(
                    `${this.selectedApprovals.length} submissions rejected successfully`,
                    "success"
                );
                this.closeBulkRejectModal();
                this.clearSelection();
                await this.fetchApprovals();
            } catch (error) {
                console.error("Error bulk rejecting:", error);
                this.showSimpleAlert(
                    "Failed to reject some submissions",
                    "error"
                );
            } finally {
                this.loading.bulkReject = false;
            }
        },
    },
    async mounted() {
        await this.fetchFilterOptions();
        await this.fetchApprovals();
        await this.fetchHistory();
        window.addEventListener("resize", this.handleResize);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.handleResize);
        clearTimeout(this.searchTimeout);
    },
};
</script>

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
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.5rem;
    background: var(--surface-soft);
    border-radius: 12px;
    margin-bottom: 1.5rem;
    gap: 2rem;
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.avatar-lg {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: 600;
    flex-shrink: 0;
}

.performance-summary {
    display: flex;
    gap: 1rem;
    min-width: 300px;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--surface);
    border-radius: 8px;
    border: 1px solid var(--border);
    flex: 1;
}

.summary-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
}

.summary-icon.score {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
}

.summary-icon.kpi {
    background: linear-gradient(135deg, var(--info), #06b6d4);
}

.summary-icon.pica {
    background: linear-gradient(135deg, var(--warning), #f59e0b);
}

.summary-content {
    display: flex;
    flex-direction: column;
}

.summary-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    line-height: 1;
}

.summary-label {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.transaction-info-section {
    margin-bottom: 1.5rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--surface-soft);
    border-radius: 8px;
}

.info-item label {
    font-weight: 500;
    color: var(--text-muted);
}

.info-item span:not(.status-badge) {
    font-weight: 600;
    color: var(--dark);
}

/* KPI Performance Section */
.kpi-performance-section {
    margin-bottom: 1.5rem;
}

.kpi-performance-section h6 {
    margin-bottom: 1rem;
    color: var(--dark);
    font-weight: 600;
    font-size: 1.1rem;
}

.kpi-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kpi-performance-item {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.kpi-performance-item.requires-pica {
    border-left: 4px solid var(--warning);
}

.kpi-performance-item.disabled {
    position: relative;
    opacity: 0.7;
    background: var(--surface-soft);
    pointer-events: none;
    user-select: none;
}

.kpi-performance-item:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow);
}

.kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.25rem;
    background: var(--surface-soft);
    border-bottom: 1px solid var(--border);
}

.kpi-main-info {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    flex: 1;
}

.kpi-number {
    background: var(--primary);
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    flex-shrink: 0;
}

.kpi-details h6 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
    font-size: 1rem;
}

.kpi-meta {
    display: flex;
    gap: 0.5rem;
    font-size: 0.8rem;
}

.kpi-meta .category {
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 500;
}
.kpi-meta .badge {
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 500;
}

.kpi-meta .weight {
    color: var(--primary);
    font-weight: 600;
}

.kpi-performance-indicator {
    flex-shrink: 0;
}

.achievement-badge {
    padding: 0.5rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
    min-width: 80px;
}

.achievement-badge.small {
    padding: 0.1rem 0.45rem;
    border-radius: 20px;
    /* font-size: 0.8rem; */
    font-weight: 600;
    text-align: center;
    /* min-width: 10px; */
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

.kpi-content {
    padding: 1.25rem;
}

.performance-metrics {
    margin-bottom: 1.5rem;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.metric-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--surface-soft);
    border-radius: 8px;
    border-left: 4px solid var(--primary);
}

.metric-item label {
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.85rem;
}

.metric-value {
    font-weight: 600;
    color: var(--dark);
}

.metric-value.score {
    color: var(--primary);
}

.target-section {
    margin-bottom: 1.5rem;
}

.target-section h6 {
    margin-bottom: 0.5rem;
    color: var(--dark);
    font-weight: 600;
}

.target-description {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid var(--info);
    margin: 0;
    line-height: 1.5;
}

.pica-section {
    background: #fef6e6;
    border: 1px solid #fed7aa;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.pica-header {
    margin-bottom: 0.75rem;
    color: var(--warning);
}

.pica-content {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pica-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.pica-item label {
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.9rem;
    display: block;
    margin-bottom: 0.25rem;
}

.pica-item p {
    margin: 0;
    color: var(--text);
    line-height: 1.4;
    background: var(--surface);
    padding: 0.75rem;
    border-radius: 6px;
    border: 1px solid var(--border);
}

.rejection-comment {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.rejection-comment h6 {
    color: var(--danger);
    margin: 0 0 0.5rem 0;
    font-weight: 600;
}

.rejection-comment p {
    margin: 0;
    color: #7f1d1d;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Edit Modal Styles */
/* .edit-modal-content {
    max-height: 70vh;
    overflow-y: auto;
} */

.edit-kpi-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.edit-kpi-item {
    background: var(--surface-soft);
    border-radius: 8px;
    padding: 1.5rem;
    border: 1px solid var(--border);
}

.edit-kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.edit-kpi-header h6 {
    margin: 0;
    color: var(--dark);
    flex: 1;
}

.edit-kpi-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.form-row {
    display: flex;
    gap: 1rem;
}

.form-group {
    flex: 1;
}

.form-group label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
    display: block;
}

.form-control {
    border: 2px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    background: var(--surface);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-control:disabled {
    background: var(--surface-soft);
    color: var(--text-muted);
}

.form-text {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.pica-edit-section {
    border-top: 1px solid var(--border);
    padding-top: 1rem;
}

.pica-toggle {
    margin-bottom: 1rem;
}

.pica-warning {
    color: var(--warning);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
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

/* Reject Modal Styles */
.reject-modal-content {
    max-height: 70vh;
    overflow-y: auto;
}

.alert {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert-warning {
    background: #fef3cd;
    border: 1px solid #ffc107;
    color: #856404;
}

.alert-warning i {
    color: #ffc107;
}

.alert-info {
    background: #cff4fc;
    border: 1px solid #06b6d4;
    color: #055160;
}

.alert-info i {
    color: #06b6d4;
}

.rejection-kpi-selection {
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    background: var(--surface-soft);
}

.kpi-selection-list {
    max-height: 400px;
    overflow-y: auto;
    margin-top: 1rem;
}

.kpi-selection-item {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    transition: background-color 0.2s ease;
}

.kpi-selection-item:last-child {
    border-bottom: none;
}

.kpi-selection-item:hover {
    background: var(--surface);
}

.kpi-selection-header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.kpi-checkbox {
    margin-top: 0.25rem;
}

.kpi-selection-info {
    flex: 1;
}

.kpi-selection-info .kpi-meta {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0.5rem 0;
}

.pica-indicator-selection {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.pica-indicator-selection:not(.no-pica) {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.pica-indicator-selection.no-pica {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.review-metrics {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.5rem;
}

.review-metrics span {
    background: var(--surface-soft);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

.kpi-comment {
    margin-top: 1rem;
}

.kpi-comment-textarea {
    font-size: 0.9rem;
    resize: vertical;
    min-height: 80px;
}

.is-invalid {
    border-color: var(--danger) !important;
}

.error-message {
    color: var(--danger);
    font-size: 0.8rem;
    margin-top: 0.25rem;
}

/* Approve Modal Styles */
.approve-modal-content {
    padding: 1rem;
}

.approval-confirmation {
    text-align: center;
    padding: 1rem;
}

.approval-summary {
    background: var(--surface-soft);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.summary-item:last-child {
    margin-bottom: 0;
}

/* Bulk Reject Modal Styles */
.bulk-reject-content {
    padding: 1rem;
}

.bulk-reject-info {
    padding: 1rem;
}

.selected-list {
    max-height: 200px;
    overflow-y: auto;
    margin-top: 1rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem;
}

.selected-item {
    padding: 0.5rem;
    border-bottom: 1px solid var(--border);
    font-size: 0.9rem;
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
        flex-direction: column;
        gap: 1rem;
    }

    .performance-summary {
        width: 100%;
        min-width: auto;
    }

    .summary-card {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
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
