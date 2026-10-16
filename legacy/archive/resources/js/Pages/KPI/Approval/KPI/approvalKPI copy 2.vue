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
                                            class="bi bi-check-circle-fill d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2
                                            class="title fw-bold text-white text-start"
                                        >
                                            KPI Approval
                                        </h2>
                                        <p class="subtitle">
                                            Manajemen persetujuan KPI
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
                                        placeholder="Search by transaction ID or requester..."
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
                        <p>Loading approvals...</p>
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
                                                    <span>Pengaju</span>
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
                                                        class="bi bi-diagram-3 me-2 d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    <span>Jabatan</span>
                                                </div>
                                            </th>
                                            <th class="header-cell">
                                                <div
                                                    class="header-cell-content"
                                                >
                                                    <i
                                                        class="bi bi-calendar me-2 d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    <span>Tanggal</span>
                                                </div>
                                            </th>
                                            <th class="header-cell">
                                                <div
                                                    class="header-cell-content"
                                                >
                                                    <i
                                                        class="bi bi-layers me-2 d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    <span>Total KPI</span>
                                                </div>
                                            </th>
                                            <th class="header-cell">
                                                <div
                                                    class="header-cell-content"
                                                >
                                                    <i
                                                        class="bi bi-speedometer2 me-2 d-flex justify-content-center align-items-center"
                                                    ></i>
                                                    <span>Total Weight</span>
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
                                            :key="approval.id_Approval_Request"
                                            class="table-row"
                                            @click="showDetailModal(approval)"
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
                                                                    .requester
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
                                                                    .requester
                                                                    ?.Nama
                                                            }}
                                                        </div>
                                                        <div
                                                            class="employee-code"
                                                        >
                                                            {{
                                                                approval
                                                                    .requester
                                                                    ?.Kode_Karyawan
                                                            }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="division-cell">
                                                    {{
                                                        approval.requester
                                                            ?.Department
                                                    }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="level-cell">
                                                    {{
                                                        approval.requester
                                                            ?.Jabatan
                                                    }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="date-cell">
                                                    {{
                                                        formatDateStringshort(
                                                            approval.create_at
                                                        )
                                                    }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="kpi-count-cell">
                                                    <span class="count-badge">{{
                                                        approval.kpiDetails
                                                            ?.length || 0
                                                    }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="weight-cell">
                                                    <span class="weight-value"
                                                        >{{
                                                            calculateTotalWeight(
                                                                approval
                                                            )
                                                        }}%</span
                                                    >
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
                                                            class="bi bi-check-lg"
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
                                                                approval
                                                            )
                                                        "
                                                        title="Reject"
                                                    >
                                                        <i
                                                            class="bi bi-x-lg"
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
                                                            class="bi bi-eye"
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
                                    :key="approval.id_Approval_Request"
                                    class="approval-card"
                                    :class="{
                                        selected: isSelected(
                                            approval.id_Approval_Request
                                        ),
                                        'status-pending':
                                            approval.Flag_Approval === null,
                                        'status-approved':
                                            approval.Flag_Approval === 'Y',
                                        'status-rejected':
                                            approval.Flag_Approval === 'N',
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
                                                        approval.requester?.Nama
                                                    )
                                                }}
                                            </div>
                                            <div
                                                class="employee-details-mobile"
                                            >
                                                <div class="employee-name">
                                                    {{
                                                        approval.requester?.Nama
                                                    }}
                                                </div>
                                                <div class="employee-meta">
                                                    {{
                                                        approval.requester
                                                            ?.Kode_Karyawan
                                                    }}
                                                </div>
                                                <div class="employee-meta">
                                                    {{
                                                        approval.requester
                                                            ?.Department
                                                    }}
                                                    •
                                                    {{
                                                        approval.requester
                                                            ?.Jabatan
                                                    }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="kpi-summary-mobile">
                                            <div class="summary-item">
                                                <i
                                                    class="d-flex justify-content-center align-items-center bi bi-layers"
                                                ></i>
                                                <span
                                                    >{{
                                                        approval.kpiDetails
                                                            ?.length || 0
                                                    }}
                                                    KPI</span
                                                >
                                            </div>
                                            <div class="summary-item">
                                                <i
                                                    class="bi bi-speedometer2"
                                                ></i>
                                                <span
                                                    >{{
                                                        calculateTotalWeight(
                                                            approval
                                                        )
                                                    }}% Weight</span
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
                                            Details
                                        </button>
                                        <div
                                            class="action-buttons-mobile"
                                            v-if="
                                                approval.Flag_Approval === null
                                            "
                                        >
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
                                            <button
                                                class="btn-card btn-reject"
                                                @click="
                                                    openRejectModal(approval)
                                                "
                                            >
                                                <i
                                                    class="d-flex justify-content-center align-items-center bi bi-x-lg"
                                                ></i>
                                                Reject
                                            </button>
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
                            <h4>No Approvals Found</h4>
                            <p>
                                There are no approvals matching your criteria.
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
                                                <span>Pengaju</span>
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
                                                    class="bi bi-diagram-3 me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Jabatan</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-calendar me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Tanggal</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-layers me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Total KPI</span>
                                            </div>
                                        </th>
                                        <th class="header-cell">
                                            <div class="header-cell-content">
                                                <i
                                                    class="bi bi-speedometer2 me-2 d-flex justify-content-center align-items-center"
                                                ></i>
                                                <span>Total Weight</span>
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
                                        :key="approval.id_Approval_Request"
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
                                                            approval.requester
                                                                ?.Nama
                                                        )
                                                    }}
                                                </div>
                                                <div class="employee-details">
                                                    <div class="employee-name">
                                                        {{
                                                            approval.requester
                                                                ?.Nama
                                                        }}
                                                    </div>
                                                    <div class="employee-code">
                                                        {{
                                                            approval.requester
                                                                ?.Kode_Karyawan
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="division-cell">
                                                {{
                                                    approval.requester
                                                        ?.Department
                                                }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="level-cell">
                                                {{
                                                    approval.requester?.Jabatan
                                                }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-cell">
                                                {{
                                                    formatDateStringshort(
                                                        approval.update_at
                                                    )
                                                }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="kpi-count-cell">
                                                <span class="count-badge">{{
                                                    approval.kpiDetails
                                                        ?.length || 0
                                                }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="weight-cell">
                                                <span class="weight-value"
                                                    >{{
                                                        calculateTotalWeight(
                                                            approval
                                                        )
                                                    }}%</span
                                                >
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
                                :key="approval.id_Approval_Request"
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
                                            <div class="transaction-date">
                                                {{ approval.update_at }}
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
                                                    approval.requester?.Nama
                                                )
                                            }}
                                        </div>
                                        <div class="employee-details-mobile">
                                            <div class="employee-name">
                                                {{ approval.requester?.Nama }}
                                            </div>
                                            <div class="employee-meta">
                                                {{
                                                    approval.requester
                                                        ?.Kode_Karyawan
                                                }}
                                            </div>
                                            <div class="employee-meta">
                                                {{
                                                    approval.requester
                                                        ?.Department
                                                }}
                                                •
                                                {{
                                                    approval.requester?.Jabatan
                                                }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="kpi-summary-mobile">
                                        <div class="summary-item">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-layers"
                                            ></i>
                                            <span
                                                >{{
                                                    approval.kpiDetails
                                                        ?.length || 0
                                                }}
                                                KPI</span
                                            >
                                        </div>
                                        <div class="summary-item">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-speedometer2"
                                            ></i>
                                            <span
                                                >{{
                                                    calculateTotalWeight(
                                                        approval
                                                    )
                                                }}% Weight</span
                                            >
                                        </div>
                                    </div>
                                    <div
                                        v-if="approval.Alasan"
                                        class="history-reason"
                                    >
                                        <strong>Alasan:</strong>
                                        {{ approval.Alasan }}
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
                            There are no approval history records matching your
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
                </div>
            </template>
        </ApprovalTabs>

        <!-- Detail Modal Overlay -->
        <div
            v-if="showDetailModalFlag"
            class="modal-overlay"
            @click="closeDetailModal"
        >
            <div class="modal-content detail-modal" @click.stop>
                <div class="modal-header">
                    <h3 class="modal-title">
                        KPI Details - {{ selectedApproval?.No_Transaksi }}
                    </h3>
                    <button class="modal-close" @click="closeDetailModal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="modal-body-content" v-if="selectedApproval">
                        <!-- Header Info -->
                        <div class="modal-section">
                            <div class="detail-header-compact">
                                <div class="requester-info-compact">
                                    <div class="avatar-lg">
                                        {{
                                            getInitials(
                                                selectedApproval.requester?.Nama
                                            )
                                        }}
                                    </div>
                                    <div class="requester-details-compact">
                                        <h5>
                                            {{
                                                selectedApproval.requester?.Nama
                                            }}
                                        </h5>
                                        <p class="employee-meta">
                                            {{
                                                selectedApproval.requester
                                                    ?.Kode_Karyawan
                                            }}
                                            •
                                            {{
                                                selectedApproval.requester
                                                    ?.Department
                                            }}
                                        </p>
                                        <p class="employee-role">
                                            {{
                                                selectedApproval.requester
                                                    ?.Jabatan
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <div class="transaction-info-compact">
                                    <div class="info-grid">
                                        <div class="info-item-compact">
                                            <label>Transaction ID:</label>
                                            <span class="transaction-id">{{
                                                selectedApproval.No_Transaksi
                                            }}</span>
                                        </div>
                                        <div class="info-item-compact">
                                            <label>Submitted:</label>
                                            <span>{{
                                                formatDateString(
                                                    selectedApproval.create_at
                                                )
                                            }}</span>
                                        </div>
                                        <div class="info-item-compact">
                                            <label>Total KPIs:</label>
                                            <span>{{
                                                selectedApproval.kpiDetails
                                                    ?.length
                                            }}</span>
                                        </div>
                                        <div class="info-item-compact">
                                            <label>Total Weight:</label>
                                            <span
                                                >{{
                                                    calculateTotalWeight(
                                                        selectedApproval
                                                    )
                                                }}%</span
                                            >
                                        </div>
                                        <div class="info-item-compact">
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
                            </div>
                        </div>

                        <!-- KPI Details -->
                        <div class="modal-section">
                            <div class="modal-section-header">
                                <div class="modal-section-icon">
                                    <i class="bi bi-list-check"></i>
                                </div>
                                <h4 class="modal-section-title">KPI Details</h4>
                                <span class="badge-count"
                                    >{{
                                        selectedApproval.kpiDetails?.length
                                    }}
                                    items</span
                                >
                            </div>

                            <div class="kpi-list-compact">
                                <div
                                    v-for="(
                                        kpi, index
                                    ) in selectedApproval.kpiDetails"
                                    :key="kpi.Id_Pengajuan_Detail"
                                    class="kpi-item-compact"
                                >
                                    <div class="kpi-header-compact">
                                        <div class="kpi-number">
                                            #{{ index + 1 }}
                                        </div>
                                        <div class="kpi-main-info-compact">
                                            <h6>{{ kpi.Nama_Parameter }}</h6>
                                            <div class="kpi-meta-compact">
                                                <span class="category-badge">{{
                                                    kpi.Nama_Kategori
                                                }}</span>
                                                <span class="weight-badge"
                                                    >{{ kpi.Bobot }}%</span
                                                >
                                            </div>
                                        </div>
                                        <div class="kpi-icon-compact">
                                            <i
                                                :class="
                                                    getKpiIcon(
                                                        kpi.Id_Bsc_Kategori
                                                    )
                                                "
                                            ></i>
                                        </div>
                                    </div>

                                    <div class="kpi-content-compact">
                                        <div class="kpi-description-compact">
                                            <strong>Description:</strong>
                                            {{ kpi.Deskripsi }}
                                        </div>

                                        <div
                                            class="targets-section-compact"
                                            v-if="
                                                kpi.targets &&
                                                kpi.targets.length > 0
                                            "
                                        >
                                            <strong>Targets:</strong>
                                            <div class="targets-grid-compact">
                                                <div
                                                    v-for="target in kpi.targets"
                                                    :key="target.Nama_Target"
                                                    class="target-item-compact"
                                                >
                                                    <span class="target-label"
                                                        >{{
                                                            target.Nama_Target
                                                        }}:</span
                                                    >
                                                    <span class="target-value">
                                                        {{
                                                            convertValueUnit(
                                                                target.Nilai_Target,
                                                                kpi.Format_Input,
                                                                kpi.Simbol_Satuan
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rejection Reason (jika ada) -->
                        <div
                            v-if="
                                selectedApproval.Flag_Approval === 'N' &&
                                selectedApproval.Alasan
                            "
                            class="modal-section rejection-section"
                        >
                            <div class="modal-section-header">
                                <div
                                    class="modal-section-icon"
                                    style="background: var(--danger)"
                                >
                                    <i class="bi bi-x-circle"></i>
                                </div>
                                <h4 class="modal-section-title">
                                    Rejection Reason
                                </h4>
                            </div>
                            <div class="rejection-content-compact">
                                <p>{{ selectedApproval.Alasan }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="modal-footer-actions">
                        <button
                            class="btn-modern  "
                            @click="closeDetailModal"
                        >
                            <i class="bi bi-x-circle me-2"></i>
                            Close
                        </button>

                        <div
                            v-if="activeTab !== 'history'"
                            class="action-buttons-group"
                        >
                            <button
                                class="btn-modern btn-danger"
                                @click="openRejectModal(selectedApproval)"
                                :disabled="loading.reject"
                            >
                                <i class="bi bi-x-lg me-2"></i>
                                Reject
                            </button>
                            <button
                                class="btn-modern btn-success"
                                @click="openApproveModal(selectedApproval)"
                                :disabled="loading.approve"
                            >
                                <i class="bi bi-check-lg me-2"></i>
                                Approve
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject Modal Overlay -->
        <div
            v-if="showRejectModal"
            class="modal-overlay"
            @click="closeRejectModal"
        >
            <div class="modal-content reject-modal" @click.stop>
                <div class="modal-header">
                    <h3 class="modal-title">
                        Reject KPI - {{ selectedApproval?.requester?.Nama }}
                    </h3>
                    <button class="modal-close" @click="closeRejectModal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="modal-body-content">
                        <div class="modal-section">
                            <div class="modal-section-header">
                                <div
                                    class="modal-section-icon"
                                    style="background: var(--danger)"
                                >
                                    <i class="bi bi-x-circle"></i>
                                </div>
                                <h4 class="modal-section-title">
                                    Reject KPI Submission
                                </h4>
                            </div>

                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Perhatian:</strong> Berikan alasan
                                penolakan untuk setiap KPI yang bermasalah
                            </div>

                            <div class="reject-kpi-comments mb-4">
                                <h6>KPI Comments</h6>
                                <p class="text-muted small">
                                    Masukkan alasan penolakan untuk setiap KPI
                                    yang perlu direvisi
                                </p>

                                <div class="kpi-comments-list">
                                    <div
                                        v-for="(
                                            kpi, index
                                        ) in selectedApproval?.kpiDetails || []"
                                        :key="kpi.Id_Pengajuan_Detail"
                                        class="kpi-comment-item"
                                    >
                                        <div class="kpi-comment-header">
                                            <strong>{{
                                                kpi.Nama_Parameter
                                            }}</strong>
                                            <span class="kpi-meta"
                                                >{{ kpi.Nama_Kategori }} •
                                                {{ kpi.Bobot }}%</span
                                            >
                                        </div>
                                        <textarea
                                            v-model="rejectionComments[index]"
                                            class="form-control kpi-comment-textarea"
                                            rows="3"
                                            :placeholder="`Masukkan alasan penolakan untuk ${kpi.Nama_Parameter}...`"
                                        ></textarea>
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
                                    placeholder="Berikan alasan keseluruhan untuk penolakan pengajuan ini..."
                                    :class="{ 'is-invalid': rejectionError }"
                                ></textarea>
                                <div
                                    v-if="rejectionError"
                                    class="error-message"
                                >
                                    {{ rejectionError }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="modal-footer-actions">
                        <button
                            class="btn-modern  "
                            @click="closeRejectModal"
                        >
                            <i class="bi bi-x-circle me-2"></i>
                            Batal
                        </button>
                        <button
                            class="btn-modern btn-danger"
                            @click="confirmRejectWithDetail"
                            :disabled="loading.reject"
                        >
                            <i class="bi bi-x-lg me-2"></i>
                            {{ loading.reject ? "Processing..." : "Reject" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approve Modal Overlay -->
        <div
            v-if="showApproveModal"
            class="modal-overlay"
            @click="closeApproveModal"
        >
            <div class="modal-content approve-modal" @click.stop>
                <div class="modal-header">
                    <h3 class="modal-title">
                        Approve KPI - {{ selectedApproval?.requester?.Nama }}
                    </h3>
                    <button class="modal-close" @click="closeApproveModal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="modal-body-content">
                        <div class="modal-section">
                            <div class="modal-section-header">
                                <div
                                    class="modal-section-icon"
                                    style="background: var(--success)"
                                >
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <h4 class="modal-section-title">
                                    Approve KPI Submission
                                </h4>
                            </div>

                            <div class="approval-confirmation text-center">
                                <lord-icon
                                    src="https://cdn.lordicon.com/uvofdfal.json"
                                    trigger="loop"
                                    delay="300"
                                    colors="primary:#10b981"
                                    style="width: 100px; height: 100px"
                                >
                                </lord-icon>

                                <h5 class="mt-3">Confirm Approval</h5>
                                <p class="text-muted">
                                    Are you sure you want to approve this KPI
                                    submission?
                                </p>
                                <div class="approval-summary">
                                    <div class="summary-item">
                                        <strong>Transaction:</strong>
                                        {{ selectedApproval?.No_Transaksi }}
                                    </div>
                                    <div class="summary-item">
                                        <strong>Requester:</strong>
                                        {{ selectedApproval?.requester?.Nama }}
                                    </div>
                                    <div class="summary-item">
                                        <strong>Total KPIs:</strong>
                                        {{
                                            selectedApproval?.kpiDetails?.length
                                        }}
                                    </div>
                                    <div class="summary-item">
                                        <strong>Total Weight:</strong>
                                        {{
                                            calculateTotalWeight(
                                                selectedApproval
                                            )
                                        }}%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="modal-footer-actions">
                        <button
                            class="btn-modern  "
                            @click="closeApproveModal"
                        >
                            <i class="bi bi-x-circle me-2"></i>
                            Batal
                        </button>
                        <button
                            class="btn-modern btn-success"
                            @click="confirmApprove"
                            :disabled="loading.approve"
                        >
                            <i class="bi bi-check-lg me-2"></i>
                            {{ loading.approve ? "Processing..." : "Approve" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Reject Modal Overlay -->
        <div
            v-if="showBulkRejectModal"
            class="modal-overlay"
            @click="closeBulkRejectModal"
        >
            <div class="modal-content bulk-reject-modal" @click.stop>
                <div class="modal-header">
                    <h3 class="modal-title">Reject Selected Submissions</h3>
                    <button class="modal-close" @click="closeBulkRejectModal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="modal-body-content">
                        <div class="modal-section">
                            <div class="modal-section-header">
                                <div
                                    class="modal-section-icon"
                                    style="background: var(--warning)"
                                >
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <h4 class="modal-section-title">
                                    Bulk Rejection
                                </h4>
                            </div>

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
                                        {{ getApprovalById(id)?.No_Transaksi }}
                                        -
                                        {{
                                            getApprovalById(id)?.requester?.Nama
                                        }}
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mt-4">
                                <label class="form-label"
                                    >Rejection Reason *</label
                                >
                                <textarea
                                    v-model="bulkRejectionReason"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Please provide a reason for rejection..."
                                    :class="{
                                        'is-invalid': bulkRejectionError,
                                    }"
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
                </div>
                <div class="modal-footer">
                    <div class="modal-footer-actions">
                        <button
                            class="btn-modern  "
                            @click="closeBulkRejectModal"
                        >
                            <i class="bi bi-x-circle me-2"></i>
                            Batal
                        </button>
                        <button
                            class="btn-modern btn-danger"
                            @click="confirmBulkReject"
                            :disabled="loading.bulkReject"
                        >
                            <i class="bi bi-x-lg me-2"></i>
                            {{
                                loading.bulkReject
                                    ? "Processing..."
                                    : "Reject All"
                            }}
                        </button>
                    </div>
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
    name: "KpiApprovalEnhanced",
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
                bulkReject: false,
            },
            showDetailModalFlag: false,
            showRejectModal: false,
            showApproveModal: false,
            showBulkRejectModal: false,
            selectedApproval: null,
            rejectionReason: "",
            bulkRejectionReason: "",
            rejectionError: "",
            bulkRejectionError: "",
            rejectionComments: [],
            isMobile: window.innerWidth < 768,
            activeTab: "queue",
            approvalTabs: [
                {
                    id: "queue",
                    label: "Approval Queue",
                    icon: "bi bi-inbox d-flex justify-content-center align-items-center ",
                    count: 0,
                },
                {
                    id: "history",
                    label: "Approval History",
                    icon: "bi bi-clock-history d-flex justify-content-center align-items-center ",
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
            return this.approvals.filter((a) => a.Flag_Approval === "T").length;
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
        convertValueUnit(value, format, simbol = null) {
            if (!format || !value) return "Undefined";

            let simbolSatuan = simbol ? simbol?.trim() : "";
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
                return new Intl.NumberFormat("id-ID", {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(numericValue);
            }

            return format == "currency"
                ? simbolSatuan + ". " + result
                : result + " " + simbolSatuan;
        },
        async fetchApprovals() {
            this.loading.fetch = true;
            try {
                this.approvals = [];
                this.approvalTabs[0].count = 0;
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
                    "/api/v1/kpi-approval/approvals",
                    { params }
                );

                if (response.data?.success) {
                    this.approvals = response.data.result;
                    this.approvalTabs[0].count = this.pendingCount;
                }
            } catch (error) {
                console.error("Error fetching approvals:", error);
                this.showSimpleAlert("Failed to load approvals", "error");
            } finally {
                this.loading.fetch = false;
            }
        },

        async fetchHistory() {
            this.loading.history = true;
            try {
                this.historyApprovals = [];
                this.approvalTabs[1].count = 0;
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
                    "/api/v1/kpi-approval/approvals",
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
                    axios.get(
                        "/api/v1/kpi-approval/approvals/filter-options/levels"
                    ),
                    axios.get(
                        "/api/v1/kpi-approval/approvals/filter-options/divisions"
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
                    (a) => a.id_Approval_Request
                );
            }
        },

        clearSelection() {
            this.selectedApprovals = [];
        },

        getApprovalById(id) {
            return this.approvals.find((a) => a.id_Approval_Request === id);
        },

        getInitials(name) {
            if (!name) return "??";
            return name
                .split(" ")
                .map((n) => n[0])
                .join("")
                .toUpperCase();
        },

        getKpiIcon(categoryId) {
            const icons = {
                1: "bi bi-currency-dollar",
                2: "bi bi-people",
                3: "bi bi-gear",
                4: "bi bi-graph-up",
            };
            return icons[categoryId] || "bi bi-file-text";
        },

        calculateTotalWeight(approval) {
            if (!approval?.kpiDetails) return 0;
            return approval.kpiDetails.reduce(
                (sum, kpi) => sum + (parseFloat(kpi.Bobot) || 0),
                0
            );
        },

        getStatusClass(status) {
            switch (status) {
                case "Y":
                    return "approved";
                case "T":
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

        formatDateStringshort(dateString) {
            if (!dateString) return "";
            try {
                const date = new Date(dateString);
                const options = {
                    day: "numeric",
                    month: "short",
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

        formatTime(dateString) {
            if (!dateString) return "";
            return new Date(dateString).toLocaleTimeString("id-ID", {
                hour: "2-digit",
                minute: "2-digit",
            });
        },

        formatDateTime(dateString) {
            if (!dateString) return "";
            try {
                const date = new Date(dateString);
                const day = date.toLocaleDateString("id-ID", {
                    day: "numeric",
                    month: "long",
                    year: "numeric",
                });

                const hour = String(d.getHours()).padStart(2, "0");
                const minute = String(d.getMinutes()).padStart(2, "0");

                return `${day}, ${hour}:${minute}`;
            } catch (error) {
                return dateString;
            }
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

        showDetailModal(approval) {
            this.selectedApproval = approval;
            this.showDetailModalFlag = true;
            document.body.style.overflow = "hidden";
        },

        closeDetailModal() {
            this.showDetailModalFlag = false;
            this.selectedApproval = null;
            document.body.style.overflow = "";
        },

        openRejectModal(approval) {
            this.selectedApproval = approval;
            this.rejectionReason = "";
            this.rejectionError = "";
            this.rejectionComments = new Array(
                approval.kpiDetails?.length || 0
            ).fill("");
            this.showRejectModal = true;
            document.body.style.overflow = "hidden";
        },

        closeRejectModal() {
            this.showRejectModal = false;
            this.selectedApproval = null;
            this.rejectionReason = "";
            this.rejectionError = "";
            this.rejectionComments = [];
            document.body.style.overflow = "";
        },

        openApproveModal(approval) {
            this.selectedApproval = approval;
            this.showApproveModal = true;
            document.body.style.overflow = "hidden";
        },

        closeApproveModal() {
            this.showApproveModal = false;
            this.selectedApproval = null;
            document.body.style.overflow = "";
        },

        async confirmRejectWithDetail() {
            if (!this.rejectionReason.trim()) {
                this.rejectionError = "Overall rejection reason is required";
                return;
            }

            this.loading.reject = true;
            try {
                const payload = {
                    reason: this.rejectionReason,
                    kpi_comments: this.rejectionComments,
                };

                await axios.post(
                    `/api/v1/kpi-approval/approvals/${this.selectedApproval.id_Approval_Request}/reject-with-detail`,
                    payload
                );

                this.showSimpleAlert(
                    "Submission rejected successfully",
                    "success"
                );
                this.closeRejectModal();
                this.closeDetailModal();
                await this.fetchApprovals();
                await this.fetchHistory();
            } catch (error) {
                console.error("Error rejecting submission:", error);
                this.showSimpleAlert("Failed to reject submission", "error");
            } finally {
                this.loading.reject = false;
            }
        },

        async confirmApprove() {
            this.loading.approve = true;
            try {
                await axios.post(
                    `/api/v1/kpi-approval/approvals/${this.selectedApproval.id_Approval_Request}/approve`
                );
                this.showSimpleAlert(
                    "Submission approved successfully",
                    "success"
                );
                this.closeApproveModal();
                this.closeDetailModal();
                await this.fetchApprovals();
            } catch (error) {
                console.error("Error approving submission:", error);
                this.showSimpleAlert("Failed to approve submission", "error");
            } finally {
                this.loading.approve = false;
            }
        },

        async bulkApprove() {
            this.loading.approve = true;
            try {
                for (const id of this.selectedApprovals) {
                    await axios.post(
                        `/api/v1/kpi-approval/approvals/${id}/approve`
                    );
                }
                this.showSimpleAlert(
                    `${this.selectedApprovals.length} submissions approved successfully`,
                    "success"
                );
                this.clearSelection();
                await this.fetchApprovals();
            } catch (error) {
                console.error("Error bulk approving:", error);
                this.showSimpleAlert(
                    "Failed to approve some submissions",
                    "error"
                );
            } finally {
                this.loading.approve = false;
            }
        },

        openBulkRejectModal() {
            this.bulkRejectionReason = "";
            this.bulkRejectionError = "";
            this.showBulkRejectModal = true;
            document.body.style.overflow = "hidden";
        },

        closeBulkRejectModal() {
            this.showBulkRejectModal = false;
            this.bulkRejectionReason = "";
            this.bulkRejectionError = "";
            document.body.style.overflow = "";
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
                        `/api/v1/kpi-approval/approvals/${id}/reject`,
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
        document.body.style.overflow = "";
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

.btn-reject-sm {
    color: var(--danger);
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
    /* min-width: 200px; */
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

/* .history-status.status-approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
} */

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
        width: 100%;
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

/* Modal Overlay System */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 1rem;
    animation: fadeIn 0.2s ease;
}

.modal-content {
    background: var(--surface);
    border-radius: 16px;
    box-shadow: var(--shadow-lg);
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    animation: slideUp 0.3s ease;
    border: 1px solid var(--border);
}

/* Modal Sizes */
.modal-content.detail-modal {
    width: min(1200px, 95vw);
    height: min(800px, 93vh);
}

.modal-content.reject-modal,
.modal-content.approve-modal,
.modal-content.bulk-reject-modal {
    width: min(700px, 95vw);
    max-height: min(700px, 93vh);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border);
    background: var(--surface-soft);
    border-radius: 16px 16px 0 0;
}

.modal-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: var(--border);
    color: var(--danger);
}

.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 2rem;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid var(--border);
    background: var(--surface-soft);
    border-radius: 0 0 16px 16px;
}

/* Modal Content Styles */
.detail-modal-content {
    max-height: 100%;
    overflow-y: auto;
}

/* Responsive Modal */
@media (max-width: 768px) {
    .modal-overlay {
        padding: 0rem;
        align-items: flex-end;
    }

    .modal-content {
        max-width: 100vw;
        max-height: 95vh;
        border-radius: 16px 16px 0 0;
        width: 100%;
    }

    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1rem 1.25rem;
    }

    .modal-content.detail-modal {
        width: min(1200px, 95vw);
        height: min(800px, 93vh);
    }
}

@media (max-width: 480px) {
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 0.75rem 1rem;
    }

    .modal-title {
        font-size: 1.1rem;
    }
}

/* Animations */
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

/* Existing Styles (dari kode sebelumnya) */
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

/* Buttons */
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

.  {
    background-color: var(--secondary);
}

.btn-modern:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Table Styles */
.table-view-container {
    width: 100%;
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: 0 1px 3px #0000001a;
    -webkit-overflow-scrolling: touch;
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

/* Card Styles */
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

/* Status Badges */
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
    color: var(--warning);
}

.status-badge.status-approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
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

/* Detail Modal Content */
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

.kpi-details-section {
    margin-bottom: 1.5rem;
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

    .detail-header {
        flex-direction: column;
        gap: 1rem;
    }

    .card-grid {
        grid-template-columns: 1fr;
    }

    .bulk-actions-content {
        flex-direction: column;
        align-items: stretch;
    }

    .modal-footer-buttons {
        flex-direction: row;
    }

    .action-buttons {
        flex-direction: row;
    }
}

@media (max-width: 480px) {
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 0.75rem 1rem;
    }

    .filter-section {
        padding: 0.5rem;
    }

    .search-input {
        padding: 0.5rem 0.6rem 0.5rem 2rem;
        font-size: 0.85rem;
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

/* Buttons */
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

.  {
    background-color: var(--secondary);
}

.btn-modern:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Modal System */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 1rem;
    animation: fadeIn 0.2s ease;
}

.modal-content {
    background: var(--surface);
    border-radius: 16px;
    box-shadow: var(--shadow-lg);
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    animation: slideUp 0.3s ease;
    border: 1px solid var(--border);
    width: min(800px, 95vw);
    max-height: min(700px, 93vh);
}

.modal-content.detail-modal {
    width: min(1000px, 95vw);
    max-height: min(800px, 93vh);
}

.modal-content.wide-modal {
    width: min(1200px, 95vw);
    max-height: min(900px, 93vh);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border);
    background: var(--surface-soft);
    border-radius: 16px 16px 0 0;
}

.modal-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: var(--border);
    color: var(--danger);
}

.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 2rem;
}

.modal-body-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    max-height: calc(93vh - 140px);
    overflow-y: auto;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid var(--border);
    background: var(--surface-soft);
    border-radius: 0 0 16px 16px;
}

.modal-footer-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.action-buttons-group {
    display: flex;
    gap: 0.75rem;
}

/* Modal Sections */
.modal-section {
    background: var(--surface);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border);
}

.modal-section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.modal-section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.modal-section-icon {
    width: 32px;
    height: 32px;
    background: var(--primary);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.badge-count {
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Detail Modal Compact Styles */
.detail-header-compact {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    align-items: start;
}

.requester-info-compact {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
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

.requester-details-compact h5 {
    margin: 0 0 0.5rem 0;
    font-size: 1.2rem;
}

.employee-meta {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin: 0.25rem 0;
}

.employee-role {
    font-size: 0.85rem;
    color: var(--primary);
    font-weight: 500;
    margin: 0;
}

.transaction-info-compact {
    background: var(--surface-soft);
    border-radius: 10px;
    padding: 1rem;
}

.info-grid {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.info-item-compact {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.info-item-compact label {
    font-weight: 500;
    color: var(--text-muted);
}

.info-item-compact span:not(.status-badge) {
    font-weight: 600;
    color: var(--dark);
}

.transaction-id {
    color: var(--primary);
    font-family: monospace;
}

/* KPI List Compact */
.kpi-list-compact {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kpi-item-compact {
    background: var(--surface-soft);
    border-radius: 10px;
    padding: 1.25rem;
    border-left: 4px solid var(--primary);
    transition: all 0.2s ease;
}

.kpi-item-compact:hover {
    transform: translateX(4px);
    box-shadow: var(--shadow);
}

.kpi-header-compact {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.kpi-number {
    background: var(--primary);
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    flex-shrink: 0;
}

.kpi-main-info-compact {
    flex: 1;
}

.kpi-main-info-compact h6 {
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark);
}

.kpi-meta-compact {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.category-badge {
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.25rem 0.6rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 500;
}

.weight-badge {
    color: var(--primary);
    font-weight: 600;
    font-size: 0.85rem;
}

.kpi-icon-compact {
    width: 36px;
    height: 36px;
    background: var(--primary-light);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1rem;
    flex-shrink: 0;
}

.kpi-content-compact {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kpi-description-compact {
    font-size: 0.9rem;
    line-height: 1.5;
    color: var(--text);
}

.targets-section-compact strong {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.targets-grid-compact {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.5rem;
}

.target-item-compact {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0.75rem;
    background: var(--surface);
    border-radius: 6px;
    font-size: 0.85rem;
}

.target-label {
    color: var(--text-muted);
    font-weight: 500;
}

.target-value {
    font-weight: 600;
    color: var(--dark);
}

.rejection-section {
    border-left: 4px solid var(--danger);
}

.rejection-content-compact {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 1rem;
    color: #7f1d1d;
    line-height: 1.5;
}

/* Table Styles */
.table-view-container {
    width: 100%;
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: 0 1px 3px #0000001a;
    -webkit-overflow-scrolling: touch;
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

/* Card Styles */
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

/* Status Badges */
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

.status-badge.status-approved {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-badge.status-rejected {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
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

/* Form Validation */
.is-invalid {
    border-color: var(--danger) !important;
}

.error-message {
    color: var(--danger);
    font-size: 0.8rem;
    margin-top: 0.25rem;
}

/* Animations */
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

    .modal-content {
        max-width: 100vw;
        max-height: 95vh;
        border-radius: 16px 16px 0 0;
        width: 100%;
    }

    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1rem 1.25rem;
    }

    .detail-header-compact {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .requester-info-compact {
        flex-direction: column;
        text-align: center;
    }

    .info-item-compact {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .targets-grid-compact {
        grid-template-columns: 1fr;
    }

    .kpi-header-compact {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }

    .kpi-meta-compact {
        justify-content: center;
    }

    .card-grid {
        grid-template-columns: 1fr;
    }

    .bulk-actions-content {
        flex-direction: column;
        align-items: stretch;
    }

    .modal-footer-actions {
        flex-direction: column;
        gap: 1rem;
    }

    .action-buttons-group {
        width: 100%;
    }

    .action-buttons-group .btn-modern {
        flex: 1;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 0.75rem 1rem;
    }

    .filter-section {
        padding: 0.5rem;
    }

    .search-input {
        padding: 0.5rem 0.6rem 0.5rem 2rem;
        font-size: 0.85rem;
    }
}
</style>
