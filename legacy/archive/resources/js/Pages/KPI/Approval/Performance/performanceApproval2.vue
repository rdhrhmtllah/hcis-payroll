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
                                            <!-- <th
                                                class="header-cell select-column"
                                            >
                                                <div
                                                    class="header-cell-content"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :checked="isAllSelected"
                                                        @change="
                                                            toggleSelectAll
                                                        "
                                                        class="header-checkbox"
                                                    />
                                                </div>
                                            </th> -->
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
                                            <!-- <th class="header-cell">
                                                <div
                                                    class="header-cell-content"
                                                >
                                                    <i
                                                        class="bi bi-diagram-3 me-2 d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    <span>Jabatan</span>
                                                </div>
                                            </th> -->
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
                                            <!-- <td class="select-column">
                                                <input
                                                    type="checkbox"
                                                    :checked="
                                                        isSelected(
                                                            approval.id_Performance_Approval
                                                        )
                                                    "
                                                    @change="
                                                        toggleSelection(
                                                            approval.id_Performance_Approval
                                                        )
                                                    "
                                                    class="row-checkbox"
                                                />
                                            </td> -->
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
                                            <!-- <td>
                                                <div class="level-cell">
                                                    {{
                                                        approval.employee
                                                            ?.Jabatan
                                                    }}
                                                </div>
                                            </td> -->
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
                                                    <!-- <button
                                                        v-if="
                                                            approval.Flag_Approval ===
                                                            null
                                                        "
                                                        class="btn-action btn-reject-sm"
                                                        @click.stop="
                                                            openRejectModal(
                                                                approval
                                                            )
                                                        "
                                                        title="Reject"
                                                    >
                                                        <i
                                                            class="bi bi-x-lg"
                                                        ></i>
                                                    </button> -->
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
                                        selected: isSelected(
                                            approval.id_Performance_Approval
                                        ),
                                        'status-pending':
                                            approval.Flag_Approval === null,
                                    }"
                                >
                                    <div class="card-header">
                                        <div class="header-left">
                                            <!-- <input
                                                type="checkbox"
                                                :checked="
                                                    isSelected(
                                                        approval.id_Performance_Approval
                                                    )
                                                "
                                                @change="
                                                    toggleSelection(
                                                        approval.id_Performance_Approval
                                                    )
                                                "
                                                class="card-checkbox"
                                            /> -->
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
                                                    <!-- •
                                                    {{
                                                        approval.employee
                                                            ?.Jabatan
                                                    }} -->
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
                                            <!-- <button
                                                class="btn-card btn-reject"
                                                @click="
                                                    openRejectModal(approval)
                                                "
                                            >
                                                <i
                                                    class="d-flex justify-content-center align-items-center bi bi-x-lg"
                                                ></i>
                                                Reject
                                            </button> -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-else-if="!loading.fetch"
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
                                                •
                                                {{ approval.employee?.Jabatan }}
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
                                                >{{ approval.total_score }}%
                                                Score</span
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
                        v-else-if="!loading.fetch"
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
                            @click="clearFilters"
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
        <div
            v-if="showDetailModalFlag"
            class="modal-overlay"
            @click="closeDetailModal"
        >
            <div class="modal-container modal-xl" @click.stop>
                <div class="modal-header">
                    <h5 class="modal-title">
                        Performance Details -
                        {{ selectedApproval?.employee?.Nama }}
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        @click="closeDetailModal"
                    >
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <div
                    class="modal-body performance-detail-content"
                    v-if="selectedApproval"
                >
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
                <div class="modal-footer">
                    <button
                        class="btn-modern  "
                        @click="closeDetailModal"
                    >
                        <i
                            class="d-flex justify-content-center align-items-center bi bi-x-circle"
                        ></i>
                        Tutup
                    </button>
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
            </div>
        </div>

        <!-- Reject Modal -->
        <div
            v-if="showRejectModal"
            class="modal-overlay"
            @click="closeRejectModal"
        >
            <div class="modal-container modal-lg" @click.stop>
                <div class="modal-header">
                    <h5 class="modal-title">
                        Reject Performance -
                        {{ selectedApproval?.employee?.Nama }}
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        @click="closeRejectModal"
                    >
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <div class="modal-body reject-modal-content">
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
                                            <span>Score: {{ kpi.score }}%</span>
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
                <div class="modal-footer">
                    <button
                        class="btn-modern  "
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
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="showEditModal" class="modal-overlay" @click="closeEditModal">
            <div class="modal-container modal-xl" @click.stop>
                <div class="modal-header">
                    <h5 class="modal-title">
                        Edit Performance -
                        {{ selectedApproval?.employee?.Nama }}
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        @click="closeEditModal"
                    >
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <div
                    class="modal-body edit-modal-content"
                    v-if="selectedApproval"
                >
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
                                    <span class="badge">{{
                                        kpi.bsc_category
                                    }}</span>
                                    <span class="weight"
                                        >{{ kpi.weight }}%</span
                                    >
                                    <span class="achievement"
                                        >{{
                                            formatAchievement(kpi.achievement)
                                        }}%</span
                                    >
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
                                        <input
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
                                            :value="kpi.score + '%'"
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
                                        <label class="toggle-label">
                                            <input
                                                type="checkbox"
                                                v-model="editPicaEnabled[index]"
                                                @change="
                                                    togglePicaEdit(index, kpi)
                                                "
                                            />
                                            <span class="toggle-slider"></span>
                                            <span class="toggle-text">
                                                {{
                                                    editPicaEnabled[index]
                                                        ? "Edit PICA"
                                                        : "Add PICA"
                                                }}
                                            </span>
                                        </label>
                                        <span
                                            class="pica-warning"
                                            v-if="
                                                isActualBelowTarget(index, kpi)
                                            "
                                        >
                                            <i
                                                class="bi bi-exclamation-triangle"
                                            ></i>
                                            Actual value di bawah target,
                                            disarankan untuk menambahkan PICA
                                        </span>
                                    </div>

                                    <div
                                        class="pica-form"
                                        v-if="editPicaEnabled[index]"
                                    >
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
                                        v-if="
                                            kpi.pica &&
                                            kpi.pica.length > 0 &&
                                            !editPicaEnabled[index]
                                        "
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
                <div class="modal-footer">
                    <button
                        class="btn-modern  "
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
            </div>
        </div>

        <!-- Approve Modal -->
        <div
            v-if="showApproveModal"
            class="modal-overlay"
            @click="closeApproveModal"
        >
            <div class="modal-container" @click.stop>
                <div class="modal-header">
                    <h5 class="modal-title">
                        Approve Performance -
                        {{ selectedApproval?.employee?.Nama }}
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        @click="closeApproveModal"
                    >
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <div class="modal-body approve-modal-content">
                    <div class="approval-confirmation">
                        <i
                            class="d-flex justify-content-center align-items-center bi bi-check-circle-fill text-success fs-1"
                        ></i>
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
                <div class="modal-footer">
                    <button
                        class="btn-modern  "
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
            </div>
        </div>
    </div>
</template>

<script>
import { ElMessage } from "element-plus";
import quickStat from "@/components/ui/quickStat.vue";
import ApprovalTabs from "../../components/ui/toggleSection.vue";
import axios from "axios";

export default {
    name: "PerformanceApproval",
    components: {
        quickStat,
        ApprovalTabs,
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
            },
            showDetailModalFlag: false,
            showRejectModal: false,
            showApproveModal: false,
            showEditModal: false,
            selectedApproval: null,
            rejectionReason: "",
            rejectionError: "",
            rejectionKpiSelection: [],
            rejectionComments: [],
            editActualValues: [],
            editPicaEnabled: [],
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

                const response = await axios.get(
                    "/performance-approval/approvals",
                    { params }
                );

                if (response.data?.success) {
                    this.approvals = response.data.result;
                    this.approvalTabs[0].count = this.pendingCount;
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
            // Validate PICA data if enabled
            for (let i = 0; i < this.editPicaEnabled.length; i++) {
                if (this.editPicaEnabled[i]) {
                    const picaData = this.editPicaData[i];
                    if (
                        !picaData.problem_identification?.trim() ||
                        !picaData.corrective_actions?.trim() ||
                        !picaData.due_date
                    ) {
                        this.showSimpleAlert(
                            "Please complete all required PICA fields for KPI: " +
                                this.selectedApproval.kpiDetails[i].parameter,
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
                this.editPicaEnabled.forEach((enabled, index) => {
                    if (enabled) {
                        const kpiId =
                            this.selectedApproval.kpiDetails[index].id;
                        picaData[kpiId] = {
                            enabled: true,
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
            console.log(item);
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

        handleCurrencyInput(event, item) {
            const rawValue = event.target.value.replace(/\D/g, "");
            item.actual = rawValue ? parseInt(rawValue, 10) : null;
            this.updateKpiMetrics(item);
        },

        handleKpiSelectionChange(index) {
            if (!this.rejectionKpiSelection[index]) {
                this.rejectionComments[index] = "";
            }
        },

        handleActualValueChange(index, kpi) {
            const newValue = parseFloat(this.editActualValues[index]);
            const targetValue = parseFloat(kpi.Nilai_Target);

            // Auto enable PICA jika actual value lebih kecil dari target
            if (
                !isNaN(newValue) &&
                !isNaN(targetValue) &&
                newValue < targetValue
            ) {
                if (!this.editPicaEnabled[index]) {
                    this.editPicaEnabled[index] = true;
                    // Initialize PICA data jika belum ada
                    if (!this.editPicaData[index].problem_identification) {
                        this.editPicaData[index] = {
                            problem_identification: `Pencapaian aktual (${newValue}) di bawah target (${targetValue})`,
                            corrective_actions: "",
                            due_date: this.getDefaultDueDate(),
                        };
                    }
                }
            }
            this.updateKpiMetrics(kpi);
        },

        shouldShowPicaSection(index, kpi) {
            // Tampilkan PICA section jika:
            // 1. Sudah ada PICA sebelumnya, atau
            // 2. Actual value di bawah target, atau
            // 3. Manual enabled oleh user
            return (
                (kpi.pica && kpi.pica.length > 0) ||
                this.isActualBelowTarget(index, kpi) ||
                this.editPicaEnabled[index]
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
            console.log(approval);
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

        openEditModal(approval) {
            console.log(approval);
            this.selectedApproval = approval;
            this.editActualValues =
                approval.kpiDetails?.map((kpi) => kpi.actual_value) || [];
            this.editPicaEnabled = new Array(
                approval.kpiDetails?.length || 0
            ).fill(false);
            this.editPicaData = new Array(
                approval.kpiDetails?.length || 0
            ).fill({
                problem_identification: "",
                corrective_actions: "",
                due_date: "",
            });

            // Initialize PICA data if exists
            approval.kpiDetails?.forEach((kpi, index) => {
                if (kpi.pica && kpi.pica.length > 0) {
                    this.editPicaEnabled[index] = true;
                    this.editPicaData[index] = {
                        problem_identification:
                            kpi.pica[0].problem_identification || "",
                        corrective_actions:
                            kpi.pica[0].corrective_actions || "",
                        due_date: kpi.pica[0].due_date || "",
                    };
                }
            });

            this.showEditModal = true;
        },

        openApproveModal(approval) {
            this.selectedApproval = approval;
            this.showApproveModal = true;
        },

        togglePicaEdit(index, kpi) {
            if (
                this.editPicaEnabled[index] &&
                !this.editPicaData[index].problem_identification
            ) {
                // Initialize with empty data if enabling PICA
                this.editPicaData[index] = {
                    problem_identification:
                        kpi.pica?.[0]?.problem_identification || "",
                    corrective_actions: kpi.pica?.[0]?.corrective_actions || "",
                    due_date:
                        kpi.pica?.[0]?.due_date || this.getDefaultDueDate(),
                };
            }
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
            this.editPicaEnabled = [];
            this.editPicaData = [];
        },

        closeApproveModal() {
            this.showApproveModal = false;
            this.selectedApproval = null;
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
            if (typeof value !== "number" || !isFinite(value)) {
                return 0;
            }
            return value % 1 === 0 ? value : value.toFixed(1);
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

        getApprovalById(id) {
            return this.approvals.find((a) => a.id_Performance_Approval === id);
        },

        previewFile(file) {
            if (file) {
                window.open(file, "_blank");
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
    --vs-border-radius: 8px;
    --vs-border-color: #e2e8f0;
    --vs-border-style: solid;
    --vs-border-width: 1px;
}

/* Style untuk disabled KPI card */
.kpi-performance-item.disabled {
    position: relative;
    opacity: 0.7;
    background: var(--surface-soft);
    border: 1px solid var(--border);
    pointer-events: none;
    user-select: none;
}

.kpi-performance-item.disabled::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        135deg,
        rgba(248, 250, 252, 0.9) 0%,
        rgba(241, 245, 249, 0.8) 100%
    );
    z-index: 1;
    border-radius: inherit;
}

.kpi-performance-item.disabled .kpi-header,
.kpi-performance-item.disabled .kpi-content,
.kpi-performance-item.disabled .performance-metrics,
.kpi-performance-item.disabled .target-section,
.kpi-performance-item.disabled .pica-section {
    position: relative;
    z-index: 2;
}

/* Header disabled style */
.kpi-performance-item.disabled .kpi-header {
    border-bottom: 1px dashed var(--border);
}

.kpi-performance-item.disabled .kpi-number {
    background: var(--secondary);
    color: var(--light);
}

.kpi-performance-item.disabled .kpi-details h6 {
    color: var(--text-muted);
}

.kpi-performance-item.disabled .kpi-meta .category,
.kpi-performance-item.disabled .kpi-meta .weight {
    background: var(--secondary);
    color: var(--light);
    opacity: 0.8;
}

/* Achievement badge disabled style */
.kpi-performance-item.disabled .achievement-badge {
    background: var(--secondary) !important;
    color: var(--light) !important;
    opacity: 0.8;
}

/* Metrics disabled style */
.kpi-performance-item.disabled .metric-item label {
    color: var(--text-muted);
}

.kpi-performance-item.disabled .metric-value {
    color: var(--text-muted);
    font-weight: 500;
}

.kpi-performance-item.disabled .metric-value.score {
    color: var(--secondary);
}

/* Section headers disabled style */
.kpi-performance-item.disabled .target-section h6,
.kpi-performance-item.disabled .pica-header h6 {
    color: var(--text-muted);
    border-left: 3px solid var(--secondary);
}

.kpi-performance-item.disabled .target-description,
.kpi-performance-item.disabled .pica-item p {
    color: var(--text-muted);
}

/* PICA section disabled style */
.kpi-performance-item.disabled .pica-header {
    background: linear-gradient(135deg, var(--surface-soft) 0%, #e2e8f0 100%);
    border-left: 4px solid var(--secondary);
}

.kpi-performance-item.disabled .pica-header h6 i {
    color: var(--secondary);
}

/* Disabled overlay icon */
.kpi-performance-item.disabled::after {
    content: "⛔";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 2rem;
    color: var(--secondary);
    opacity: 0.1;
    z-index: 3;
    pointer-events: none;
}

/* Hover effect untuk disabled cards */
.kpi-performance-item.disabled:hover {
    transform: none;
    box-shadow: var(--shadow);
    cursor: not-allowed;
}

/* Responsive adjustment */
@media (max-width: 768px) {
    .kpi-performance-item.disabled::after {
        font-size: 1.5rem;
    }
}

/* Sudah Ada Submit KPI */
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

/* Submitted State */
.submission-success-header {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--shadow);
    min-height: 140px;
    margin-bottom: 2rem;
}

.success-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    opacity: 0.95;
}

.success-content {
    position: relative;
    z-index: 2;
    padding: 2.5rem;
    color: white;
}

.success-info {
    display: flex;
    align-items: center;
    justify-content: start;
    gap: 1.5rem;
}

.icon-container.success {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.icon-container.success i {
    color: #34d399;
}

.submission-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
}

.meta-item {
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.15);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

/* Submission Summary */
.submission-summary {
    background: var(--surface);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-left: 4px solid var(--primary);
}

.summary-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.summary-icon.total {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
}

.summary-icon.weight {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.summary-icon.status {
    background: linear-gradient(135deg, var(--info), #06b6d4);
}

.summary-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0;
}

.summary-label {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
}

/* Submitted KPI List */
.submitted-kpis {
    background: var(--surface);
    border-radius: 12px;
    border: 1px solid var(--border);
    padding: 1.5rem;
}

.section-header {
    margin-bottom: 1.5rem;
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.5rem 0;
}

.section-subtitle {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
}

.kpi-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.submitted-kpi-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--surface-soft);
    border-radius: 8px;
    border-left: 4px solid var(--success);
}

.kpi-main-info {
    display: flex;
    align-items: center;
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
}

.kpi-icon {
    width: 40px;
    height: 40px;
    background: var(--primary-light);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.1rem;
}

.kpi-details {
    flex: 1;
}

.kpi-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.kpi-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
}

.kpi-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(107, 114, 128, 0.1);
    border-radius: 20px;
    color: var(--text-muted);
    font-size: 0.8rem;
    font-weight: 500;
}

.kpi-status.locked i {
    color: var(--warning);
}

.submission-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 2rem;
}

.btn-outline {
    background: transparent;
    border: 2px solid var(--border);
    color: var(--text);
}

.btn-outline:hover {
    background: var(--surface-soft);
    border-color: var(--primary);
    color: var(--primary);
}

/* Responsive Design */
@media (max-width: 768px) {
    .empty-state-schedule {
        padding: 2rem 1rem;
    }
    .kpi-details {
        width: 100%;
    }
    .kpi-main-info {
        width: 100%;
    }

    .empty-icon-container {
        width: 80px;
        height: 80px;
        font-size: 2rem;
    }

    .empty-title {
        font-size: 1.25rem;
    }

    .empty-actions {
        flex-direction: column;
        align-items: center;
    }

    .success-content {
        padding: 1.5rem;
    }

    .success-info {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .submission-meta {
        align-items: center;
        margin-top: 1rem;
    }

    .summary-cards {
        grid-template-columns: 1fr;
    }

    .submitted-kpi-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .kpi-status {
        align-self: stretch;
        justify-content: center;
    }

    .submission-actions {
        flex-direction: column;
    }

    .submission-actions .btn-modern {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .summary-card {
        flex-direction: column;
        text-align: center;
    }

    .kpi-main-info {
        flex-direction: column;
        text-align: center;
    }

    .kpi-meta {
        justify-content: center;
    }
}
/* Akhir Sudah Ada Submit KPI */

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

.form-label-optimized {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* modal target */

/* Modal Target Styles */
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
    padding: 3rem 2rem;
    background: var(--surface-soft);
    border-radius: 12px;
    border: 2px dashed var(--border);
}

.instruction-icon {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 1rem;
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

/* Responsive Design */
@media (max-width: 768px) {
    .modal-target-content {
        padding: 0.25rem;
    }

    .target-scale-section {
        padding: 1rem;
    }

    .target-form-section {
        padding: 1rem;
    }

    .section-header {
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

/* Animation */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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

.stats-bar-simple {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    justify-content: center;
    flex-wrap: nowrap;
}

.stat-item-simple {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: var(--surface-soft);
    border-radius: 10px;
    min-width: 120px;
    flex: 1;
    justify-content: center;
}

.stat-item-simple i {
    color: var(--primary);
    font-size: 1.1rem;
}

.stat-number {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--dark);
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
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
}

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

.compact-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-light);
}

.compact-card.active {
    border-color: var(--primary);
    background: linear-gradient(135deg, var(--surface) 0%, #f8fafc 100%);
}

.compact-card.completed {
    border-left: 4px solid var(--success);
}

.compact-card.draft {
    border-left: 4px solid var(--warning);
}

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

.  {
    background: var(--surface-soft);
    color: var(--text-muted);
}

.btn-success {
    background: var(--success);
    color: white;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .modern-header {
        min-height: 120px;
    }
}

@media (max-width: 992px) {
    .header-info {
        width: 100%;
        padding-bottom: 0.6rem;
        gap: 1rem;
    }
    .icon-container {
        width: 80px;
        height: 80px;
        padding: 1rem;
    }
    .title {
        font-size: 1.5rem;
    }
    .subtitle {
        font-size: 1rem;
    }
    .cards-scroll-wrapper {
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    .cards-scroll-wrapper::-webkit-scrollbar {
        display: none;
    }
}

@media (max-width: 768px) {
    .modern-header {
        min-height: 100px;
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
    .stats-bar-simple {
        flex-direction: row;
        gap: 0.5rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
        justify-content: flex-start;
    }
    .stat-item-simple {
        min-width: 110px;
        flex: none;
        padding: 0.6rem 0.8rem;
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
}

@media (max-width: 480px) {
    .stat-item-simple {
        min-width: 100px;
        padding: 0.5rem 0.6rem;
    }
    .stat-number {
        font-size: 1rem;
    }
    .stat-label {
        font-size: 0.7rem;
    }
    .compact-card {
        min-width: 200px;
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
.compact-card {
    opacity: 0; /* default sebelum animasi */
    animation: slideIn 0.2s ease forwards;
    will-change: transform, opacity;
}

.compact-card:nth-child(odd) {
    animation-delay: 0.1s;
}

.compact-card:nth-child(even) {
    animation-delay: 0.2s;
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

/* Table View */
.table-view-container {
    width: 100%;
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: 0 1px 3px #0000001a;
    -webkit-overflow-scrolling: touch;
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
    box-shadow: var(--shadow);
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

.select-column {
    width: 50px;
    text-align: center;
}

.header-checkbox,
.row-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.table-row {
    transition: background-color 0.2s ease;
    cursor: pointer;
}

.table-row:hover {
    background-color: var(--surface-soft);
}

.table-row.selected {
    background-color: #e3f2fd;
}

.table-row.status-pending {
    border-left: 4px solid var(--warning);
}

.table-row.status-approved {
    border-left: 4px solid var(--success);
}

.table-row.status-rejected {
    border-left: 4px solid var(--danger);
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

.employee-dept {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.date-cell {
    white-space: nowrap;
    font-size: 0.875rem;
}

.kpi-count-cell {
    text-align: center;
}

.count-badge {
    background: var(--primary-light);
    color: var(--primary);
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.weight-cell {
    text-align: center;
}

.weight-value {
    font-weight: 600;
    color: var(--primary);
    font-size: 0.875rem;
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
    /* background: rgba(245, 158, 11, 0.1); */
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
    color: var(--success);
    background: rgba(185, 117, 16, 0.1);
}

.btn-edit-sm:hover {
    background: rgba(185, 117, 16, 0.2);
}

.btn-reject-sm {
    color: var(--danger);
    background: rgba(68, 239, 119, 0.1);
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

.approval-card.selected {
    border-color: var(--primary);
    background: #f8f9ff;
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

.card-checkbox {
    width: 18px;
    height: 18px;
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

.kpi-summary-mobile {
    display: flex;
    gap: 1rem;
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

.btn-card.btn-reject {
    background: var(--danger);
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

/* Detail Modal */
.detail-modal-content {
    max-height: 70vh;
    overflow-y: auto;
}

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

.requester-info {
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

.requester-details h5 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
}

.requester-details p {
    margin: 0.25rem 0;
    font-size: 0.9rem;
}

.transaction-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 200px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.info-item label {
    font-weight: 500;
    color: var(--text-muted);
}

.info-item span {
    font-weight: 600;
    color: var(--dark);
}

.kpi-details-section {
    margin-bottom: 1.5rem;
}

.kpi-details-section h6 {
    margin-bottom: 1rem;
    color: var(--dark);
    font-weight: 600;
    font-size: 1.1rem;
}

.kpi-list-detail {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kpi-detail-item {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.kpi-detail-item:hover {
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

.kpi-title-section {
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

.kpi-main-info h6 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-weight: 600;
    font-size: 1rem;
}

.kpi-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
}

.kpi-meta .category {
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

.kpi-icon {
    width: 40px;
    height: 40px;
    background: var(--primary-light);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.1rem;
    flex-shrink: 0;
}

.kpi-content {
    padding: 1.25rem;
}

.kpi-description {
    margin-bottom: 1rem;
    font-size: 0.9rem;
    line-height: 1.5;
    color: var(--text);
}

.targets-section {
    margin-bottom: 1rem;
}

.targets-section strong {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--dark);
}

.targets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.5rem;
}

.target-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: var(--surface-soft);
    border-radius: 6px;
    font-size: 0.85rem;
}

.target-name {
    color: var(--text-muted);
    font-weight: 500;
}

.target-value {
    font-weight: 600;
    color: var(--dark);
}

.rejection-comment {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.rejection-comment strong {
    color: var(--danger);
    display: block;
    margin-bottom: 0.5rem;
}

.rejection-comment p {
    margin: 0;
    color: #7f1d1d;
    font-size: 0.9rem;
    line-height: 1.4;
}

.overall-rejection {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 1.25rem;
    margin-top: 1.5rem;
}

.overall-rejection h6 {
    color: var(--danger);
    margin: 0 0 0.75rem 0;
    font-weight: 600;
}

.rejection-content p {
    margin: 0;
    color: #7f1d1d;
    line-height: 1.5;
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

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1.5rem 0;
    border-top: 1px solid var(--border);
    margin-top: 1.5rem;
}

.page-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    margin: 0 0.25rem;
}

.page-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-numbers {
    display: flex;
    margin: 0 0.5rem;
}

.page-number {
    min-width: 36px;
    height: 36px;
    padding: 0 0.5rem;
    border-radius: 8px;
    border: 1px solid transparent;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    margin: 0 0.25rem;
    font-weight: 500;
}

.page-number:hover {
    border-color: var(--border);
}

.page-number.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.page-info {
    margin: 0 1rem;
    font-size: 0.875rem;
    color: var(--text-muted);
}

/* Animations */
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
    .detail-header {
        flex-direction: column;
        gap: 1rem;
    }

    .transaction-info {
        width: 100%;
    }

    .card-grid {
        grid-template-columns: 1fr;
    }

    .kpi-header {
        flex-direction: column;
        gap: 1rem;
    }

    .targets-grid {
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
}

@media (max-width: 480px) {
    .kpi-title-section {
        flex-direction: column;
        gap: 0.5rem;
    }

    .kpi-meta {
        flex-direction: column;
        gap: 0.5rem;
    }

    .page-numbers {
        display: none;
    }
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

/* Loading State */
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

/* Division and Level Cells */
.division-cell,
.level-cell {
    font-size: 0.875rem;
    color: var(--text);
}

.employee-code {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* Responsive Design */
@media (max-width: 768px) {
    .filter-section {
        padding: 0.75rem;
    }

    .search-input,
    .filter-select {
        padding: 0.6rem 0.8rem 0.6rem 2.2rem;
        font-size: 0.9rem;
    }

    .search-box i {
        left: 0.8rem;
        font-size: 0.9rem;
    }

    .filter-info {
        flex-direction: column;
        gap: 0.5rem;
        align-items: stretch;
    }

    .btn-clear-filters {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .filter-section {
        padding: 0.5rem;
    }

    .search-input,
    .filter-select {
        padding: 0.5rem 0.6rem 0.5rem 2rem;
        font-size: 0.85rem;
    }
}

/* Filter Tags */
.filter-tags {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}

.filter-tag {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.25rem 0.75rem;
    border-radius: 16px;
    font-size: 0.8rem;
    font-weight: 500;
}

.filter-tag i {
    cursor: pointer;
    font-size: 0.7rem;
}

.filter-tag i:hover {
    color: var(--danger);
}

/* History Styles */
.history-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1rem;
}

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

.history-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--surface-soft);
    border-bottom: 1px solid var(--border);
}

.history-transaction {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.history-date {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.history-status {
    padding: 0.375rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.history-status.status-approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.history-status.status-rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.history-card-body {
    padding: 1rem;
}

.history-requester {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.requester-info {
    flex: 1;
}

.employee-details {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.history-kpi-summary {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

.history-reason {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 6px;
    padding: 0.75rem;
    font-size: 0.85rem;
    color: #7f1d1d;
}

.history-card-footer {
    padding: 1rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
}

/* Reject Modal KPI Selection */
.reject-kpi-selection {
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    background: var(--surface-soft);
}

.kpi-selection-list {
    max-height: 200px;
    overflow-y: auto;
    margin-top: 1rem;
}

.kpi-selection-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem;
    border-bottom: 1px solid var(--border);
    transition: background-color 0.2s ease;
}

.kpi-selection-item:last-child {
    border-bottom: none;
}

.kpi-selection-item:hover {
    background: var(--surface);
}

.kpi-checkbox {
    margin-top: 0.25rem;
}

.kpi-selection-label {
    flex: 1;
    cursor: pointer;
}

.kpi-selection-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.kpi-selection-info .kpi-meta {
    font-size: 0.8rem;
    color: var(--text-muted);
}

/* Form Validation */
.is-invalid {
    border-color: var(--danger) !important;
}

.error-message {
    color: var(--danger);
    font-size: 0.8rem;
    margin-top: 0.25rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .history-cards {
        grid-template-columns: 1fr;
    }

    .history-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .history-status {
        align-self: flex-start;
    }

    .filter-tags {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 576px) {
    .kpi-selection-item {
        flex-direction: column;
        gap: 0.5rem;
    }

    .kpi-checkbox {
        align-self: flex-start;
    }
}

/* Modal Footer Buttons */
.modal-footer-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    width: 100%;
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
}

/* Reject Modal KPI Comments */
.reject-kpi-comments {
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    background: var(--surface-soft);
}

.kpi-comments-list {
    max-height: 400px;
    overflow-y: auto;
    margin-top: 1rem;
}

.kpi-comment-item {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    transition: background-color 0.2s ease;
}

.kpi-comment-item:last-child {
    border-bottom: none;
}

.kpi-comment-item:hover {
    background: var(--surface);
}

.kpi-comment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.kpi-comment-header .kpi-meta {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.kpi-comment-textarea {
    font-size: 0.9rem;
    resize: vertical;
    min-height: 80px;
}

/* Alert Styles */
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

.history-reason {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 6px;
    padding: 0.75rem;
    font-size: 0.85rem;
    color: #7f1d1d;
    margin-top: 0.75rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-footer-buttons {
        flex-direction: column;
    }

    .action-buttons {
        flex-direction: column;
    }

    .kpi-comment-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}

@media (max-width: 576px) {
    .kpi-comment-item {
        padding: 0.75rem;
    }
}

/* Performance Specific Styles */
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

.period-cell {
    white-space: nowrap;
    font-size: 0.875rem;
}

/* Performance Detail Modal Styles */
.performance-detail-content {
    max-height: 80vh;
    overflow-y: auto;
}

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

/* KPI Performance List */
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
    gap: 1rem;
    font-size: 0.8rem;
}

.kpi-meta .category {
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

.performance-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: var(--surface-soft);
    border-radius: 6px;
}

.detail-label {
    color: var(--text-muted);
    font-weight: 500;
    font-size: 0.85rem;
}

.detail-value {
    font-weight: 600;
    color: var(--dark);
}

.detail-value.score {
    color: var(--primary);
}

.data-source-section {
    margin-bottom: 1rem;
}

.file-attachment {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: var(--surface-soft);
    border-radius: 6px;
    margin-top: 0.5rem;
}

.file-name {
    flex: 1;
    font-size: 0.9rem;
}

.btn-preview-file {
    background: none;
    border: none;
    color: var(--primary);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

.btn-preview-file:hover {
    background: var(--primary-light);
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
}

/* Edit Modal Styles */
.edit-modal-content {
    max-height: 70vh;
    overflow-y: auto;
}

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

.pica-edit-section {
    border-top: 1px solid var(--border);
    padding-top: 1rem;
}

.pica-toggle {
    margin-bottom: 1rem;
}

.toggle-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    user-select: none;
}

.toggle-label input {
    display: none;
}

.toggle-slider {
    width: 44px;
    height: 24px;
    background: var(--border);
    border-radius: 12px;
    position: relative;
    transition: background-color 0.3s ease;
}

.toggle-slider::before {
    content: "";
    position: absolute;
    width: 18px;
    height: 18px;
    background: white;
    border-radius: 50%;
    top: 3px;
    left: 3px;
    transition: transform 0.3s ease;
}

.toggle-label input:checked + .toggle-slider {
    background: var(--primary);
}

.toggle-label input:checked + .toggle-slider::before {
    transform: translateX(20px);
}

.toggle-text {
    font-weight: 600;
    color: var(--dark);
}

.pica-form {
    background: var(--surface);
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid var(--border);
}

/* Mobile Performance Summary */
.performance-summary-mobile {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.score-progress-mobile {
    margin-top: 0.5rem;
}

.score-progress-mobile .progress-bar {
    height: 8px;
}

/* Responsive Design */
@media (max-width: 768px) {
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

    .kpi-header {
        flex-direction: column;
        gap: 1rem;
    }

    .kpi-main-info {
        flex-direction: column;
        gap: 0.75rem;
    }

    .performance-details {
        grid-template-columns: 1fr;
    }

    .form-row {
        flex-direction: column;
        gap: 0.75rem;
    }

    .performance-summary-mobile {
        flex-direction: column;
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .kpi-meta {
        flex-direction: column;
        gap: 0.5rem;
    }

    .summary-card {
        padding: 0.75rem;
    }

    .summary-value {
        font-size: 1.25rem;
    }
}

/* Performance Metrics Grid */
.performance-metrics {
    margin-bottom: 1.5rem;
}

.performance-metrics h6 {
    margin-bottom: 1rem;
    color: var(--dark);
    font-weight: 600;
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

/* Target Section */
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

/* Calculation Section */
.calculation-section {
    margin-bottom: 1.5rem;
}

.calculation-section h6 {
    margin-bottom: 0.5rem;
    color: var(--dark);
    font-weight: 600;
}

.calculation-section p {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid var(--warning);
    margin: 0;
    font-family: monospace;
}

/* Evidence Section */
.evidence-section {
    margin-bottom: 1.5rem;
}

.evidence-section h6 {
    margin-bottom: 0.5rem;
    color: var(--dark);
    font-weight: 600;
}

.evidence-content {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid var(--success);
}

.evidence-files {
    margin-top: 0.75rem;
}

.evidence-file {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: var(--surface);
    border-radius: 6px;
    margin-bottom: 0.5rem;
}

/* Notes Section */
.notes-section {
    margin-bottom: 1.5rem;
}

.notes-section h6 {
    margin-bottom: 0.5rem;
    color: var(--dark);
    font-weight: 600;
}

.notes-content {
    background: var(--surface-soft);
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid var(--secondary);
    margin: 0;
    line-height: 1.5;
}

/* Enhanced PICA Section */
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

/* Rejection Type Section */
.rejection-type-section {
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    background: var(--surface-soft);
}

.rejection-type-options {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 1rem;
}

.rejection-type-option {
    display: flex;
    align-items: center;
    padding: 1rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.rejection-type-option:hover {
    border-color: var(--primary-light);
}

.rejection-type-option input[type="radio"]:checked + .option-content {
    border-color: var(--primary);
    background: rgba(99, 102, 241, 0.05);
}

.option-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    width: 100%;
}

.option-content i {
    font-size: 1.5rem;
    color: var(--primary);
}

.option-content div {
    flex: 1;
}

.option-content strong {
    display: block;
    margin-bottom: 0.25rem;
    color: var(--dark);
}

.option-content small {
    color: var(--text-muted);
    font-size: 0.8rem;
}

/* KPI Performance Review */
.kpi-performance-review {
    margin-bottom: 0.75rem;
}

.review-metrics {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.review-metrics span {
    background: var(--surface-soft);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

/* PICA Comments */
.pica-comment-item {
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 1rem;
    background: var(--surface-soft);
}

.pica-comment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.pica-status {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.pica-status.missing {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

/* Edit Tabs */
.edit-tabs {
    display: flex;
    gap: 0.5rem;
    border-bottom: 1px solid var(--border);
    padding-bottom: 1rem;
}

.edit-tab {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    background: var(--surface-soft);
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}

.edit-tab:hover {
    background: var(--primary-light);
    color: var(--primary);
}

.edit-tab.active {
    background: var(--primary);
    color: white;
}

/* Enhanced Edit Forms */
.edit-kpi-content .form-row {
    margin-bottom: 1rem;
}

.edit-kpi-content .form-text {
    font-size: 0.8rem;
}

.achievement-indicator {
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    background: var(--surface-soft);
    color: var(--text-muted);
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

/* Responsive Design for New Features */
@media (max-width: 768px) {
    .modal-overlay {
        padding: 0rem;
    }
    .metrics-grid {
        grid-template-columns: 1fr;
    }

    .pica-grid {
        grid-template-columns: 1fr;
    }

    .rejection-type-options {
        gap: 0.5rem;
    }

    .option-content {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }

    .edit-tabs {
        flex-direction: column;
    }

    .review-metrics {
        flex-direction: column;
        gap: 0.5rem;
    }
}

@media (max-width: 576px) {
    .metric-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .pica-comment-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}

/* Custom Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
    padding: 1rem;
}

.modal-container {
    background: var(--surface);
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    max-width: 1200px;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

.modal-container.modal-lg {
    max-width: 800px;
}

.modal-container.modal-xl {
    max-width: 1200px;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    margin: 0;
    font-weight: 600;
    color: var(--dark);
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    opacity: 0.7;
    cursor: pointer;
    color: var(--text-muted);
}

.btn-close:hover {
    opacity: 1;
    color: var(--danger);
}

.modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

/* PICA Indicator in Rejection Modal */
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

/* PICA Warning in Edit Modal */
.pica-warning {
    color: var(--warning);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
}

/* Enhanced Form Styles */
.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
}

.is-invalid {
    border-color: var(--danger) !important;
}

.error-message {
    color: var(--danger);
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

/* Loading States */
.spinner-border {
    width: 1rem;
    height: 1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-container {
        /* margin: 1rem; */
        max-height: 95vh;
    }

    .modal-header {
        padding: 1rem;
    }

    .modal-body {
        padding: 1rem;
    }

    .modal-footer {
        padding: 1rem;
        flex-direction: column;
    }

    .action-buttons {
        flex-direction: column;
        width: 100%;
    }

    .action-buttons .btn-modern {
        width: 100%;
        justify-content: center;
    }
}

/* Keep all your existing CSS styles, just add these new ones at the end */
</style>
