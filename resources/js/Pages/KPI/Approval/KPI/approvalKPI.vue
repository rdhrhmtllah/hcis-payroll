<template>
    <div class="container-fluid px-0">
        <!-- Header -->
        <div class="row d-flex align-items-center justify-content-center mb-3 fade-in">
            <div class="mb-1 w-100">
                <div class="modern-header">
                    <div class="header-background"></div>
                    <div class="header-content">
                        <div class="row align-items-center">
                            <div class="col-sm-8">
                                <div class="header-info">
                                    <div class="icon-container d-flex align-items-center justify-content-center">
                                        <i
                                            class="bi bi-check-circle-fill d-flex justify-content-center align-items-center"
                                        ></i>
                                    </div>
                                    <div class="text-content">
                                        <h2 class="title fw-bold text-white text-start">KPI Approval</h2>
                                        <p class="subtitle">Manajemen persetujuan KPI</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div
                                    class="header-actions d-flex gap-2 flex-nowrap flex-sm-column justify-content-center justify-content-md-start justify-content-md-center align-items-md-end"
                                >
                                    <div class="date-range px-2 px-md-3 py-1 gap-2 gap-md-3">
                                        <div class="date-item">
                                            <i
                                                v-if="dayNight == 'Siang'"
                                                class="bi bi-cloud-sun-fill me-2 fs-4 fade-in"
                                            ></i>
                                            <i v-else class="bi bi-cloud-moon-fill me-2 fs-4 fade-in"></i>
                                            <span class="fs-md-6 text-header">{{ formatDateString(currentDate) }}</span>
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
        <ApprovalTabs :tabs="approvalTabs" :initialTab="activeTab" @tab-change="handleTabChange">
            <!-- Tab 1: Approval Queue -->
            <template #queue>
                <div class="queue-content">
                    <!-- Bulk Actions -->
                    <div class="bulk-actions-bar" v-if="selectedApprovals.length > 0">
                        <div class="bulk-actions-content">
                            <div class="selected-count">
                                <i
                                    class="bi bi-check-circle-fill text-primary d-flex justify-content-center align-items-center"
                                ></i>
                                <span>{{ selectedApprovals.length }} selected</span>
                            </div>
                            <div class="bulk-buttons">
                                <button class="btn-bulk btn-approve" @click="bulkApprove">
                                    <i class="d-flex justify-content-center align-items-center bi bi-check-lg"></i>
                                    Approve Selected
                                </button>
                                <button class="btn-bulk btn-reject" @click="openBulkRejectModal">
                                    <i class="d-flex justify-content-center align-items-center bi bi-x-lg"></i>
                                    Reject Selected
                                </button>
                                <button class="btn-bulk btn-clear" @click="clearSelection">
                                    <i class="d-flex justify-content-center align-items-center bi bi-x-circle"></i>
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
                                    <i class="d-flex justify-content-center align-items-center bi bi-search"></i>
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
                                <select v-model="filters.division" class="filter-select" @change="fetchApprovals()">
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
                                <select v-model="filters.level" class="filter-select" @change="fetchApprovals()">
                                    <option value="">All Level</option>
                                    <option v-for="level in filterOptions.levels" :key="level.id" :value="level.id">
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
                                    <button class="btn-clear-filters" @click="clearFilters">
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
                        <div v-if="!isMobile && filteredApprovals.length > 0" class="table-view-container" key="table">
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
                                            <!-- <td class="select-column">
                                                <input
                                                    type="checkbox"
                                                    :checked="
                                                        isSelected(
                                                            approval.id_Approval_Request
                                                        )
                                                    "
                                                    @change="
                                                        toggleSelection(
                                                            approval.id_Approval_Request
                                                        )
                                                    "
                                                    class="row-checkbox"
                                                />
                                            </td> -->
                                            <td>
                                                <div class="transaction-cell">
                                                    <div class="transaction-id">
                                                        {{ approval.No_Transaksi }}
                                                    </div>
                                                    <div class="transaction-time">
                                                        {{ formatTime(approval.create_at) }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="employee-cell">
                                                    <div class="avatar-sm">
                                                        {{ getInitials(approval.requester?.Nama) }}
                                                    </div>
                                                    <div class="employee-details">
                                                        <div class="employee-name">
                                                            {{ truncateText(approval.requester?.Nama, 20) }}
                                                        </div>
                                                        <div class="employee-code">
                                                            {{ truncateText(approval.requester?.Kode_Karyawan, 20) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="division-cell">
                                                    {{ approval.requester?.Department }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="level-cell">
                                                    {{ approval.requester?.Jabatan }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="date-cell">
                                                    {{ formatDateStringshort(approval.create_at) }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="kpi-count-cell">
                                                    <span class="count-badge">{{ approval.totalKPI }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="weight-cell">
                                                    <span class="weight-value"
                                                        >{{ calculateTotalWeight(approval) }}%</span
                                                    >
                                                </div>
                                            </td>
                                            <td>
                                                <div class="status-cell">
                                                    <span
                                                        :class="`status-badge status-${getStatusClass(
                                                            approval.Flag_Approval,
                                                        )}`"
                                                    >
                                                        <i :class="getStatusIcon(approval.Flag_Approval)"></i>
                                                        {{ getStatusText(approval.Flag_Approval) }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-cell">
                                                    <button
                                                        v-if="approval.Flag_Approval === null"
                                                        class="btn-action btn-approve-sm"
                                                        @click.stop="openApproveModal(approval)"
                                                        title="Approve"
                                                    >
                                                        <i
                                                            class="bi bi-check-lg d-flex justify-content-center align-items-center"
                                                        ></i>
                                                    </button>
                                                    <button
                                                        v-if="approval.Flag_Approval === null"
                                                        class="btn-action btn-reject-sm"
                                                        @click.stop="openRejectModal(approval)"
                                                        title="Reject"
                                                    >
                                                        <i
                                                            class="bi bi-x-lg d-flex justify-content-center align-items-center"
                                                        ></i>
                                                    </button>
                                                    <button
                                                        class="btn-action btn-detail"
                                                        @click.stop="showDetailModal(approval)"
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
                            <div class="pagination-container" v-if="approvalsPagination.totalPages > 1">
                                <div class="pagination-info">
                                    Menampilkan
                                    {{ approvalsPagination.startItem }}-{{ approvalsPagination.endItem }}
                                    dari
                                    {{ approvalsPagination.totalDatas }}
                                    data
                                </div>
                                <div class="pagination-controls">
                                    <button
                                        class="pagination-btn pagination-prev"
                                        @click="fetchApprovals(approvalsPagination.prevLink)"
                                        :disabled="!approvalsPagination.prevLink"
                                    >
                                        <i
                                            class="bi bi-chevron-left d-flex justify-content-center align-items-center"
                                        ></i>
                                    </button>

                                    <button
                                        class="pagination-btn pagination-next"
                                        @click="fetchApprovals(approvalsPagination.nextLink)"
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
                                        selected: isSelected(approval.id_Approval_Request),
                                        'status-pending': approval.Flag_Approval === null,
                                        'status-approved': approval.Flag_Approval === 'Y',
                                        'status-rejected': approval.Flag_Approval === 'N',
                                    }"
                                >
                                    <div class="card-header">
                                        <div class="header-left">
                                            <!-- <input
                                                type="checkbox"
                                                :checked="
                                                    isSelected(
                                                        approval.id_Approval_Request
                                                    )
                                                "
                                                @change="
                                                    toggleSelection(
                                                        approval.id_Approval_Request
                                                    )
                                                "
                                                class="card-checkbox"
                                            /> -->
                                            <div class="transaction-info">
                                                <div class="transaction-id">
                                                    {{ approval.No_Transaksi }}
                                                </div>
                                                <div class="transaction-date">
                                                    {{ formatDateString(approval.create_at) }}
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="status-badge-mobile"
                                            :class="`status-${getStatusClass(approval.Flag_Approval)}`"
                                        >
                                            {{ getStatusText(approval.Flag_Approval) }}
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="employee-info-mobile">
                                            <div class="avatar-sm">
                                                {{ getInitials(approval.requester?.Nama) }}
                                            </div>
                                            <div class="employee-details-mobile">
                                                <div class="employee-name">
                                                    {{ approval.requester?.Nama }}
                                                </div>
                                                <div class="employee-meta">
                                                    {{ approval.requester?.Kode_Karyawan }}
                                                </div>
                                                <div class="employee-meta">
                                                    {{ approval.requester?.Department }}
                                                    •
                                                    {{ approval.requester?.Jabatan }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="kpi-summary-mobile">
                                            <div class="summary-item">
                                                <i
                                                    class="d-flex justify-content-center align-items-center bi bi-layers"
                                                ></i>
                                                <span>{{ approval.totalKPI }} KPI</span>
                                            </div>
                                            <div class="summary-item">
                                                <i
                                                    class="d-flex justify-content-center align-items-center bi bi-speedometer2"
                                                ></i>
                                                <span>{{ calculateTotalWeight(approval) }}% Weight</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-footer">
                                        <button class="btn-card btn-detail" @click="showDetailModal(approval)">
                                            <i class="d-flex justify-content-center align-items-center bi bi-eye"></i>
                                            Details
                                        </button>
                                        <div class="action-buttons-mobile" v-if="approval.Flag_Approval === null">
                                            <button class="btn-card btn-approve" @click="openApproveModal(approval)">
                                                <i
                                                    class="d-flex justify-content-center align-items-center bi bi-check-lg"
                                                ></i>
                                                Approve
                                            </button>
                                            <button class="btn-card btn-reject" @click="openRejectModal(approval)">
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
                                <i class="bi bi-inbox d-flex justify-content-center align-items-center"></i>
                            </div>
                            <h4>No Approvals Found</h4>
                            <p>There are no approvals matching your criteria.</p>
                            <button class="btn-modern btn-primary mt-3" @click="clearFilters">
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
                                    <i class="d-flex justify-content-center align-items-center bi bi-search"></i>
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
                                <select v-model="historyFilters.division" class="filter-select" @change="fetchHistory()">
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
                                <select v-model="historyFilters.level" class="filter-select" @change="fetchHistory()">
                                    <option value="">All Level</option>
                                    <option v-for="level in filterOptions.levels" :key="level.id" :value="level.id">
                                        {{ level.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select v-model="historyFilters.status" class="filter-select" @change="fetchHistory()">
                                    <option value="">All Status</option>
                                    <option value="approved">Disetujui</option>
                                    <option value="rejected">Ditolak</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn-clear-filters w-100" @click="clearHistoryFilters">
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
                                                    {{ formatTime(approval.update_at) }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="employee-cell">
                                                <div class="avatar-sm">
                                                    {{ getInitials(approval.requester?.Nama) }}
                                                </div>
                                                <div class="employee-details">
                                                    <div class="employee-name">
                                                        {{ approval.requester?.Nama }}
                                                    </div>
                                                    <div class="employee-code">
                                                        {{ approval.requester?.Kode_Karyawan }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="division-cell">
                                                {{ approval.requester?.Department }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="level-cell">
                                                {{ approval.requester?.Jabatan }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-cell">
                                                {{ formatDateStringshort(approval.update_at) }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="kpi-count-cell">
                                                <span class="count-badge">{{ approval.totalKPI }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="weight-cell">
                                                <span class="weight-value">{{ calculateTotalWeight(approval) }}%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="status-cell">
                                                <span
                                                    :class="`status-badge status-${getStatusClass(
                                                        approval.Flag_Approval,
                                                    )}`"
                                                >
                                                    <i :class="getStatusIcon(approval.Flag_Approval)"></i>
                                                    {{ getStatusText(approval.Flag_Approval) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-cell">
                                                <button
                                                    class="btn-action btn-detail"
                                                    @click.stop="showDetailModal(approval)"
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
                                :class="`status-${getStatusClass(approval.Flag_Approval)}`"
                            >
                                <div class="card-header">
                                    <div class="header-left">
                                        <div class="transaction-info">
                                            <div class="transaction-id">
                                                {{ approval.No_Transaksi }}
                                            </div>
                                            <div class="transaction-date">
                                                {{ formatDateString(approval.update_at) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="status-badge-mobile"
                                        :class="`status-${getStatusClass(approval.Flag_Approval)}`"
                                    >
                                        {{ getStatusText(approval.Flag_Approval) }}
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div class="employee-info-mobile">
                                        <div class="avatar-sm">
                                            {{ getInitials(approval.requester?.Nama) }}
                                        </div>
                                        <div class="employee-details-mobile">
                                            <div class="employee-name">
                                                {{ approval.requester?.Nama }}
                                            </div>
                                            <div class="employee-meta">
                                                {{ approval.requester?.Kode_Karyawan }}
                                            </div>
                                            <div class="employee-meta">
                                                {{ approval.requester?.Department }}
                                                •
                                                {{ approval.requester?.Jabatan }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="kpi-summary-mobile">
                                        <div class="summary-item">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-layers"
                                            ></i>
                                            <span>{{ approval.totalKPI }} KPI</span>
                                        </div>
                                        <div class="summary-item">
                                            <i
                                                class="d-flex justify-content-center align-items-center bi bi-speedometer2"
                                            ></i>
                                            <span>{{ calculateTotalWeight(approval) }}% Weight</span>
                                        </div>
                                    </div>
                                    <div v-if="approval.Alasan" class="history-reason">
                                        <strong>Alasan:</strong>
                                        {{ approval.Alasan }}
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <button class="btn-card btn-detail" @click="showDetailModal(approval)">
                                        <i class="d-flex justify-content-center align-items-center bi bi-eye"></i>
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
                            <i class="d-flex justify-content-center align-items-center bi bi-clock-history"></i>
                        </div>
                        <h4>No History Found</h4>
                        <p>There are no approval history records matching your criteria.</p>
                        <button class="btn-modern btn-primary mt-3" @click="clearFilters">
                            <i class="bi bi-arrow-clockwise d-flex justify-content-center align-items-center me-2"></i>
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
            :title="`KPI Details - ${selectedApproval?.No_Transaksi || 'Loading...'} `"
            size="large"
            @close="closeDetailModal"
        >
            <template #body>
                <!-- Loading State -->
                <div v-if="loading.detail" class="kpi-loading-overlay">
                    <div class="kpi-loading-spinner">
                        <div class="kpi-spinner-circle"></div>
                        <p>Memuat detail performance...</p>
                    </div>
                </div>

                <div class="detail-modal-content" :class="{ 'content-blur': loading.detail }">
                    <!-- Alasan penolakan GLOBAL (komentar umum approver) -->
                    <div v-if="selectedApproval?.alasan_reject_global" class="global-reject-banner">
                        <i class="bi bi-x-octagon-fill"></i>
                        <div class="global-reject-text">
                            <span class="global-reject-label">Alasan Penolakan</span>
                            <p class="global-reject-value">
                                {{ selectedApproval.alasan_reject_global }}
                            </p>
                        </div>
                    </div>

                    <!-- Header Info -->
                    <div class="detail-section mb-4">
                        <div class="detail-header-compact">
                            <div class="requester-info-compact">
                                <div class="avatar-lg">
                                    {{ getInitials(selectedApproval?.requester?.Nama) }}
                                </div>
                                <div class="requester-details-compact">
                                    <h5>
                                        {{ selectedApproval?.requester?.Nama }}
                                    </h5>
                                    <p class="employee-meta">
                                        {{ selectedApproval?.requester?.Kode_Karyawan }}
                                        •
                                        {{ selectedApproval?.requester?.Department }}
                                    </p>
                                    <p class="employee-role">
                                        {{ selectedApproval?.requester?.Jabatan }}
                                    </p>
                                </div>
                            </div>
                            <div class="transaction-info-compact">
                                <div class="info-grid">
                                    <div class="info-item-compact">
                                        <label>Transaction ID:</label>
                                        <span class="transaction-id">{{ selectedApproval?.No_Transaksi }}</span>
                                    </div>
                                    <div class="info-item-compact">
                                        <label>Submitted:</label>
                                        <span>{{ formatDateString(selectedApproval?.create_at) }}</span>
                                    </div>
                                    <div class="info-item-compact">
                                        <label>Total KPIs:</label>
                                        <span>{{ selectedApproval?.totalKPI }}</span>
                                    </div>
                                    <div class="info-item-compact">
                                        <label>Total Weight:</label>
                                        <span>{{ calculateTotalWeight(selectedApproval) }}%</span>
                                    </div>
                                    <div class="info-item-compact">
                                        <label>Status:</label>
                                        <span
                                            :class="`status-badge status-${getStatusClass(
                                                selectedApproval?.Flag_Approval,
                                            )}`"
                                        >
                                            {{ getStatusText(selectedApproval?.Flag_Approval) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KPI Details -->
                    <div class="detail-section mb-4">
                        <div class="section-header-compact">
                            <div class="section-title-wrapper">
                                <h4 class="section-title">KPI Details</h4>
                                <span class="section-badge">{{ selectedApproval?.totalKPI }} items</span>
                            </div>
                        </div>

                        <div class="kpi-list-compact">
                            <div
                                v-for="(kpi, index) in selectedApproval?.kpiDetails"
                                :key="kpi.Id_Pengajuan_Detail"
                                class="kpi-item-compact"
                            >
                                <div class="kpi-header-compact">
                                    <div class="kpi-number">#{{ index + 1 }}</div>
                                    <div class="kpi-main-info-compact">
                                        <h6>{{ kpi.Nama_Parameter }}</h6>
                                        <div class="kpi-meta-compact">
                                            <span class="category-badge">{{ kpi.Nama_Kategori }}</span>
                                            <span class="weight-badge">{{ formatAchievement(kpi.Bobot) }}%</span>
                                        </div>
                                    </div>
                                    <div class="kpi-icon-compact d-none d-md-flex">
                                        <i
                                            class="d-flex justify-content-center align-content-center"
                                            :class="getKpiIcon(kpi.Id_Bsc_Kategori)"
                                        ></i>
                                    </div>
                                </div>

                                <div class="kpi-content-compact">
                                    <div class="kpi-description-compact">
                                        <strong>Description:</strong>
                                        {{ kpi.Deskripsi }}
                                    </div>

                                    <div class="targets-section-compact" v-if="kpi.targets && kpi.targets.length > 0">
                                        <strong>Targets:</strong>
                                        <div class="targets-grid-compact">
                                            <div
                                                v-for="target in kpi.targets"
                                                :key="target.Nama_Target"
                                                class="target-item-compact"
                                            >
                                                <span class="target-label">{{ target.Nama_Target }}:</span>
                                                <span class="target-value">
                                                    {{
                                                        convertValueUnit(
                                                            target.Nilai_Target,
                                                            kpi.Format_Input,
                                                            kpi.Simbol_Satuan,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Bagian Alasan Tolak -->
                                    <div
                                        class="rejection-section-compact"
                                        v-if="kpi.Alasan_Tolak && kpi.Alasan_Tolak.trim() !== ''"
                                    >
                                        <div class="rejection-header-compact">
                                            <i class="bi bi-x-circle-fill rejection-icon"></i>
                                            <strong class="rejection-title">Alasan Penolakan:</strong>
                                        </div>
                                        <div class="rejection-reason-compact">
                                            {{ kpi.Alasan_Tolak }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rejection Reason
                    <div
                        v-if="
                            selectedApproval?.Flag_Approval === 'T' &&
                            selectedApproval?.Alasan_Tolak
                        "
                        class="detail-section"
                    >
                        <div class="rejection-section">
                            <div class="section-header-compact">
                                <h4 class="section-title">Rejection Reason</h4>
                            </div>
                            <div class="rejection-content-compact">
                                <p>{{ selectedApproval.Alasan }}</p>
                            </div>
                        </div>
                    </div> -->
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2" v-if="!loading.detail">
                    <button class="btn-modern btn-secondary" @click="closeDetailModal">
                        <i class="bi bi-x-circle me-2 d-flex justify-content-center align-items-center"></i>
                        Close
                    </button>
                    <div v-if="activeTab !== 'history'" class="action-buttons-group">
                        <button
                            class="btn-modern btn-danger"
                            @click="openRejectModal(selectedApproval)"
                            :disabled="loading.reject"
                        >
                            <i class="bi bi-x-lg me-2 d-flex justify-content-center align-items-center"></i>
                            Reject
                        </button>
                        <button
                            class="btn-modern btn-success"
                            @click="openApproveModal(selectedApproval)"
                            :disabled="loading.approve"
                        >
                            <i class="bi bi-check-lg me-2 d-flex justify-content-center align-items-center"></i>
                            Approve
                        </button>
                    </div>
                </div>
            </template>
        </Modal>

        <!-- Reject Modal -->
        <Modal
            v-if="showRejectModal"
            :show="showRejectModal"
            :title="`Reject KPI - ${selectedApproval?.requester?.Nama}`"
            size="large"
            @close="closeRejectModal"
        >
            <template #body>
                <!-- Loading State -->
                <div v-if="loading.detail" class="kpi-loading-overlay">
                    <div class="kpi-loading-spinner">
                        <div class="kpi-spinner-circle"></div>
                        <p>Memuat detail performance...</p>
                    </div>
                </div>
                <div class="reject-modal-content" :class="{ 'content-blur': loading.detail }">
                    <div class="reject-banner">
                        <i class="bi bi-info-circle"></i>
                        <div class="reject-banner-text">
                            <strong>Pengajuan akan dikembalikan ke karyawan untuk direvisi.</strong>
                            <span>
                                Alasan umum wajib diisi. Anda juga dapat menandai parameter tertentu yang perlu
                                diperbaiki.
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
                            Centang parameter yang perlu direvisi, lalu tulis komentar spesifiknya.
                        </p>

                        <div class="reject-param-list">
                            <div
                                v-for="(kpi, index) in selectedApproval?.kpiDetails || []"
                                :key="kpi.Id_Pengajuan_Detail"
                                class="reject-param-item"
                                :class="{ active: rejectionKpiSelection[index] }"
                            >
                                <label class="reject-param-row">
                                    <input
                                        type="checkbox"
                                        class="reject-param-check"
                                        v-model="rejectionKpiSelection[index]"
                                        @change="handleKpiSelectionChange(index)"
                                    />
                                    <span class="reject-param-info">
                                        <span class="reject-param-name">
                                            {{ kpi.Nama_Parameter }}
                                        </span>
                                        <span class="reject-param-chips">
                                            <span class="reject-chip">{{ kpi.Nama_Kategori }}</span>
                                            <span class="reject-chip">Bobot {{ formatAchievement(kpi.Bobot) }}%</span>
                                        </span>
                                    </span>
                                </label>

                                <div v-if="rejectionKpiSelection[index]" class="reject-param-comment">
                                    <textarea
                                        v-model="rejectionComments[index]"
                                        class="reject-textarea"
                                        rows="2"
                                        :placeholder="`Apa yang perlu diperbaiki pada ${kpi.Nama_Parameter}?`"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2" v-if="!loading.detail">
                    <button class="btn-modern btn-secondary" @click="closeRejectModal">
                        <i class="bi bi-x-circle me-2 d-flex justify-content-center align-items-center"></i>
                        Batal
                    </button>
                    <button class="btn-modern btn-danger" @click="confirmRejectWithDetail" :disabled="loading.reject">
                        <i class="bi bi-x-lg me-2 d-flex justify-content-center align-items-center"></i>
                        {{ loading.reject ? 'Processing...' : 'Reject' }}
                    </button>
                </div>
            </template>
        </Modal>

        <!-- Approve Modal -->
        <Modal
            v-if="showApproveModal"
            :show="showApproveModal"
            :title="`Approve KPI - ${selectedApproval?.requester?.Nama}`"
            size="medium"
            @close="closeApproveModal"
        >
            <template #body>
                <div class="approve-modal-content">
                    <div class="modal-section">
                        <!-- <div class="modal-section-header">
                            <div class="modal-section-icon" style="background: var(--success)">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <h4 class="modal-section-title">Approve KPI Submission</h4>
                        </div> -->

                        <div class="approval-confirmation text-center">
                            <lord-icon
                                src="https://cdn.lordicon.com/uvofdfal.json"
                                trigger="loop"
                                delay="300"
                                colors="primary:#10b981"
                                style="width: 100px; height: 100px"
                            ></lord-icon>

                            <h5 class="mt-3">Confirm Approval</h5>
                            <p class="text-muted">Are you sure you want to approve this KPI submission?</p>
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
                                    {{ selectedApproval?.totalKPI }}
                                </div>
                                <div class="summary-item">
                                    <strong>Total Weight:</strong>
                                    {{ calculateTotalWeight(selectedApproval) }}%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <button class="btn-modern btn-secondary" @click="closeApproveModal">
                        <i class="bi bi-x-circle me-2 d-flex justify-content-center align-items-center"></i>
                        Batal
                    </button>
                    <button class="btn-modern btn-success" @click="confirmApprove" :disabled="loading.approve">
                        <i class="bi bi-check-lg me-2 d-flex justify-content-center align-items-center"></i>
                        {{ loading.approve ? 'Processing...' : 'Approve' }}
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
                        <!-- <div class="modal-section-header">
                            <div class="modal-section-icon" style="background: var(--warning)">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <h4 class="modal-section-title">Bulk Rejection</h4>
                        </div> -->

                        <div class="bulk-reject-info text-center">
                            <i class="bi bi-exclamation-triangle text-warning fs-1 mb-3"></i>
                            <h5>
                                Reject
                                {{ selectedApprovals.length }} Submissions
                            </h5>
                            <p class="text-muted">You are about to reject the following submissions:</p>

                            <div class="selected-list">
                                <div v-for="id in selectedApprovals" :key="id" class="selected-item">
                                    {{ getApprovalById(id)?.No_Transaksi }} -
                                    {{ getApprovalById(id)?.requester?.Nama }}
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
                            <div v-if="bulkRejectionError" class="error-message">
                                {{ bulkRejectionError }}
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="wrapper-button-footer w-100 gap-2">
                    <button class="btn-modern btn-secondary" @click="closeBulkRejectModal">
                        <i class="bi bi-x-circle me-2 d-flex justify-content-center align-items-center"></i>
                        Batal
                    </button>
                    <button class="btn-modern btn-danger" @click="confirmBulkReject" :disabled="loading.bulkReject">
                        <i class="bi bi-x-lg me-2 d-flex justify-content-center align-items-center"></i>
                        {{ loading.bulkReject ? 'Processing...' : 'Reject All' }}
                    </button>
                </div>
            </template>
        </Modal>
    </div>
</template>

<script>
import { ElMessage } from 'element-plus';
import quickStat from '@/components/legacy/quickStat.vue';
import ApprovalTabs from '../../components/ui/toggleSection.vue';
import axios from 'axios';
import Modal from '@/components/legacy/modalV2-noir.vue';
import { tanggalPanjang } from '@/utils/tanggal';

export default {
    name: 'KpiApprovalEnhanced',
    components: {
        Modal,
        quickStat,
        ApprovalTabs,
    },
    data() {
        return {
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
            loading: {
                fetch: false,
                history: false,
                approve: false,
                reject: false,
                bulkReject: false,
                detail: false,
            },
            showDetailModalFlag: false,
            showRejectModal: false,
            showApproveModal: false,
            showBulkRejectModal: false,
            selectedApproval: null,
            rejectionReason: '',
            bulkRejectionReason: '',
            rejectionError: '',
            bulkRejectionError: '',
            rejectionComments: [],
            rejectionKpiSelection: [],
            isMobile: window.innerWidth < 768,
            activeTab: 'queue',
            approvalTabs: [
                {
                    id: 'queue',
                    label: 'Approval Queue',
                    icon: 'bi bi-inbox d-flex justify-content-center align-items-center ',
                    count: 0,
                },
                {
                    id: 'history',
                    label: 'Approval History',
                    icon: 'bi bi-clock-history d-flex justify-content-center align-items-center ',
                    count: 0,
                },
            ],
            filters: {
                search: '',
                division: '',
                level: '',
            },
            historyFilters: {
                search: '',
                division: '',
                level: '',
                status: '',
            },
            filterOptions: {
                levels: [],
                divisions: [],
            },
            searchTimeout: null,
            totalPending: 0,
            totalApprove: 0,
            totalReject: 0,
            hasApprovalSchedule: true,
        };
    },
    computed: {
        isKpiCommetsFull() {
            const isAnyCommentFilled = this.rejectionComments.some((comment) => {
                return comment.trim().length > 0;
            });

            return isAnyCommentFilled;
        },
        dayNight() {
            const jam = new Date().getHours();
            return jam >= 6 && jam < 18 ? 'Siang' : 'Malam';
        },
        quickStatField() {
            return [
                {
                    title: 'Pending',
                    value: this.pendingCount,
                    unit: '',
                    icon: 'bi bi-clock-history',
                    bgIcon: '',
                    size: 'col-4',
                },
                {
                    title: 'Approved',
                    value: this.approvedCount,
                    unit: '',
                    icon: 'bi bi-check-circle',
                    bgIcon: '',
                    size: 'col-4',
                },
                {
                    title: 'Rejected',
                    value: this.rejectedCount,
                    unit: '',
                    icon: 'bi bi-x-circle',
                    bgIcon: '',
                    size: 'col-4',
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
            return this.approvals;
        },
        isAllSelected() {
            return this.filteredApprovals.length > 0 && this.selectedApprovals.length === this.filteredApprovals.length;
        },
    },
    methods: {
        truncateText(text, length) {
            return text.length > length ? text.substring(0, length) + '...' : text;
        },
        // convertValueUnit(value, format, simbol = null) {
        //     if (!format || !value) return "Undefined";

        //     let simbolSatuan = simbol ? simbol?.trim() : "";
        //     // console.log(simbolSatuan);
        //     let decimals = 2;
        //     let result = "";

        //     if (format == "currency") {
        //         result = new Intl.NumberFormat("id-ID").format(value);
        //     } else if (format == "percentage") {
        //         const numericValue = parseFloat(value) || 0;
        //         const strValue = value.toString();

        //         const userInputDecimal =
        //             strValue.includes(".") || strValue.includes(",");

        //         if (!userInputDecimal && numericValue % 1 === 0) {
        //             result = `${numericValue}`;
        //         }

        //         result = `${numericValue.toFixed(decimals)}`;
        //     } else {
        //         const numericValue = parseFloat(value) || 0;
        //         result = new Intl.NumberFormat("id-ID", {
        //             minimumFractionDigits: 0,
        //             maximumFractionDigits: 0,
        //         }).format(numericValue);
        //     }

        //     return format == "currency"
        //         ? simbolSatuan + ". " + result
        //         : result + " " + simbolSatuan;
        // },
        convertValueUnit(value, format, simbol = null) {
            if (value === null || value === undefined || value === '') return 'Undefined';

            let simbolSatuan = simbol ? simbol.trim() : '';
            let decimals = 2;
            let result = '';

            const numericValue = parseFloat(value);

            if (format === 'currency') {
                result = new Intl.NumberFormat('id-ID').format(numericValue);
                return simbolSatuan + '. ' + result;
            }

            if (format === 'percentage') {
                const isDecimal = numericValue % 1 !== 0;

                result = isDecimal ? numericValue.toFixed(decimals) : numericValue.toString();

                return result + ' ' + simbolSatuan;
            }

            // default (number)
            const isDecimal = numericValue % 1 !== 0;

            result = new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: isDecimal ? decimals : 0,
                maximumFractionDigits: decimals,
            }).format(numericValue);

            return result + ' ' + simbolSatuan;
        },
        async fetchApprovals(url = null) {
            this.loading.fetch = true;
            try {
                const params = new URLSearchParams();

                if (this.filters.search) {
                    params.append('search', this.filters.search);
                }
                if (this.filters.division) {
                    params.append('division', this.filters.division);
                }
                if (this.filters.level) {
                    params.append('level', this.filters.level);
                }
                this.approvals = [];

                const endpoint = url || `/api/v1/kpi-approval/approvals?${params.toString()}`;

                const response = await axios.get(endpoint);

                if (response.data?.success) {
                    this.approvals = response.data.result.data.data;
                    this.hasApprovalSchedule = response.data.result.data.total;
                    this.approvalsPagination.currentPage = response.data.result.data.current_page;
                    this.approvalsPagination.totalDatas = response.data.result.data.total;
                    this.approvalsPagination.totalPages = response.data.result.data.last_page;
                    this.approvalsPagination.startItem = response.data.result.data.from;
                    this.approvalsPagination.endItem = response.data.result.data.to;
                    this.approvalsPagination.nextLink = response.data.result.data.next_page_url;
                    this.approvalsPagination.prevLink = response.data.result.data.prev_page_url;
                    this.approvalsPagination.partPagin = response.data.result.data.links;
                    this.totalApprove = response.data.result.totalApproved;
                    this.totalPending = response.data.result.totalPending;
                    this.totalReject = response.data.result.totalReject;
                    this.approvalTabs[0].count = this.totalPending;
                } else {
                    this.approvals = [];
                }
            } catch (error) {
                this.approvals = [];
                console.error('Error fetching performance approvals:', error);
                this.showSimpleAlert('Failed to load performance approvals', 'error');
            } finally {
                this.loading.fetch = false;
            }
        },

        async fetchHistory(url = null) {
            this.loading.history = true;
            try {
                const params = new URLSearchParams();

                if (this.historyFilters.search) {
                    params.append('search', this.historyFilters.search);
                }
                if (this.historyFilters.division) {
                    params.append('division', this.historyFilters.division);
                }
                if (this.historyFilters.level) {
                    params.append('level', this.historyFilters.level);
                }
                if (this.historyFilters.status) {
                    params.append('status', this.historyFilters.status);
                }

                // tetap true
                params.append('history', true);

                // console.log(params.toString());

                const endpoint = url || `/api/v1/kpi-approval/approvals?${params.toString()}`;

                const response = await axios.get(endpoint);

                if (response.data?.success) {
                    this.historyApprovals = response.data.result.data.data;
                    // Urutkan yang terbaru di-approve di atas
                    this.historyApprovals.sort((a, b) => new Date(b.update_at || b.updated_at || 0) - new Date(a.update_at || a.updated_at || 0));
                    this.historyApprovalsPagination.currentPage = response.data.result.data.current_page;
                    this.historyApprovalsPagination.totalDatas = response.data.result.data.total;
                    this.historyApprovalsPagination.totalPages = response.data.result.data.last_page;
                    this.historyApprovalsPagination.startItem = response.data.result.data.from;
                    this.historyApprovalsPagination.endItem = response.data.result.data.to;
                    this.historyApprovalsPagination.nextLink = response.data.result.data.next_page_url;
                    this.historyApprovalsPagination.prevLink = response.data.result.data.prev_page_url;
                    this.historyApprovalsPagination.partPagin = response.data.result.data.links;
                    this.totalApprove = response.data.result.totalApproved;
                    this.totalPending = response.data.result.totalPending;
                    this.totalReject = response.data.result.totalReject;
                    // console.log(this.historyApprovals);
                    this.approvalTabs[1].count = response.data.result.data.total;
                }
            } catch (error) {
                console.error('Error fetching history:', error);
                this.showSimpleAlert('Failed to load history', 'error');
            } finally {
                this.loading.history = false;
            }
        },

        async fetchFilterOptions() {
            try {
                const [levelsResponse, divisionsResponse] = await Promise.all([
                    axios.get('/api/v1/kpi-approval/approvals/filter-options/levels'),
                    axios.get('/api/v1/kpi-approval/approvals/filter-options/divisions'),
                ]);

                if (levelsResponse.data?.success) {
                    this.filterOptions.levels = levelsResponse.data.result;
                    // console.log(this.filterOptions.levels);
                }
                if (divisionsResponse.data?.success) {
                    this.filterOptions.divisions = divisionsResponse.data.result;
                    // console.log(this.filterOptions.divisions);
                }
            } catch (error) {
                console.error('Error fetching filter options:', error);
                // this.filterOptions.levels = [
                //     { id: "manager", name: "Manager" },
                //     { id: "supervisor", name: "Supervisor" },
                //     { id: "staff", name: "Staff" },
                // ];
                // this.filterOptions.divisions = [
                //     { id: "it", name: "IT" },
                //     { id: "hr", name: "HR" },
                //     { id: "finance", name: "Finance" },
                // ];
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
                search: '',
                division: '',
                level: '',
            };
            this.fetchApprovals();
        },

        clearHistoryFilters() {
            this.historyFilters = {
                search: '',
                division: '',
                level: '',
                status: '',
            };
            this.fetchHistory();
        },

        handleTabChange(tabId) {
            this.activeTab = tabId;
            this.clearSelection();
            if (tabId === 'history') {
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
                this.selectedApprovals = this.filteredApprovals.map((a) => a.id_Approval_Request);
            }
        },

        clearSelection() {
            this.selectedApprovals = [];
        },

        getApprovalById(id) {
            return this.approvals.find((a) => a.id_Approval_Request === id);
        },

        getInitials(name) {
            if (!name) return '??';
            return name
                .split(' ')
                .map((n) => n[0])
                .join('')
                .toUpperCase();
        },

        getKpiIcon(categoryId) {
            const icons = {
                1: 'bi bi-currency-dollar',
                2: 'bi bi-people',
                3: 'bi bi-gear',
                4: 'bi bi-graph-up',
            };
            return icons[categoryId] || 'bi bi-file-text';
        },
        formatAchievement(value) {
            const num = Number(value);
            if (isNaN(num)) return '0';
            // cek apakah punya desimal
            return num % 1 === 0 ? num.toString() : num.toFixed(1);
        },

        calculateTotalWeight(approval) {
            if (!approval) return 0;
            return this.formatAchievement(approval.Bobot);
        },

        getStatusClass(status) {
            switch (status) {
                case 'Y':
                    return 'approved';
                case 'T':
                    return 'rejected';
                default:
                    return 'pending';
            }
        },

        getStatusText(status) {
            switch (status) {
                case 'Y':
                    return 'Disetujui';
                case 'T':
                    return 'Ditolak';
                default:
                    return 'Menunggu';
            }
        },

        getStatusIcon(status) {
            switch (status) {
                case 'Y':
                    return 'bi bi-check-circle';
                case 'T':
                    return 'bi bi-x-circle';
                default:
                    return 'bi bi-clock';
            }
        },

        formatDateString(dateString) {
            return tanggalPanjang(dateString);
        },

        formatDateStringshort(dateString) {
            if (!dateString) return '';
            try {
                const date = new Date(dateString);
                const options = {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                };
                return new Intl.DateTimeFormat('id-ID', options).format(date);
            } catch (error) {
                return dateString;
            }
        },

        formatDate(dateString) {
            if (!dateString) return '';
            return new Date(dateString).toLocaleDateString('id-ID');
        },

        formatTime(dateString) {
            if (!dateString) return '';
            return new Date(dateString).toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            });
        },

        formatDateTime(dateString) {
            if (!dateString) return '';
            try {
                const date = new Date(dateString);
                return date.toLocaleString('id-ID', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            } catch (error) {
                return dateString;
            }
        },

        showSimpleAlert(message, type = 'warning') {
            ElMessage({
                type: type,
                message: message,
            });
        },

        handleResize() {
            this.isMobile = window.innerWidth < 768;
        },

        // showDetailModal(approval) {
        //     this.selectedApproval = approval;
        //     this.showDetailModalFlag = true;
        // },

        showDetailModal(approval) {
            // this.selectedApproval = approval;
            // console.log(approval);
            if (!approval) return;
            this.showDetailModalFlag = true;
            this.getDetail(approval);
        },

        async getDetail(approval) {
            this.loading.detail = true;
            try {
                const response = await axios.get(
                    `/api/v1/kpi-approval/approvals/${approval.No_Transaksi}/getApprovalDetail`,
                );
                if (response.status == 200) {
                    this.selectedApproval = {
                        ...approval,
                        ...response.data.result,
                    };
                    console.log(this.selectedApproval);
                }
            } catch (error) {
                this.selectedApproval = [];
                console.log(error);
            } finally {
                this.loading.detail = false;
            }
        },
        closeDetailModal() {
            this.showDetailModalFlag = false;
            this.selectedApproval = null;
        },

        async openRejectModal(approval) {
            this.showRejectModal = true;
            this.closeDetailModal();
            // Muat detail dulu supaya kpiDetails terisi, baru samakan ukuran array
            // pilihan/komentar reject dengan jumlah parameter (checkbox per-parameter reaktif).
            await this.getDetail(approval);
            const n = this.selectedApproval?.kpiDetails?.length || 0;
            this.rejectionReason = '';
            this.rejectionError = '';
            this.rejectionKpiSelection = new Array(n).fill(false);
            this.rejectionComments = new Array(n).fill('');
        },

        handleKpiSelectionChange(index) {
            if (!this.rejectionKpiSelection[index]) {
                this.rejectionComments[index] = '';
            }
        },

        closeRejectModal() {
            this.showRejectModal = false;
            this.selectedApproval = null;
            this.rejectionReason = '';
            this.rejectionError = '';
            this.rejectionComments = [];
            this.rejectionKpiSelection = [];
        },

        openApproveModal(approval) {
            this.closeDetailModal();
            this.selectedApproval = approval;
            this.showApproveModal = true;
        },

        closeApproveModal() {
            this.showApproveModal = false;
            this.selectedApproval = null;
        },

        async confirmRejectWithDetail() {
            // Alasan global WAJIB. Komentar per-parameter opsional — boleh menolak
            // dengan alasan global saja, atau menambah komentar parameter tertentu.
            if (!this.rejectionReason.trim()) {
                this.rejectionError = 'Alasan penolakan wajib diisi';
                return;
            }

            // Jika parameter dicentang, komentarnya harus diisi (centang tanpa komentar
            // tidak bermakna).
            const checkedButEmpty = this.rejectionKpiSelection.some(
                (selected, index) => selected && !this.rejectionComments[index]?.trim(),
            );
            if (checkedButEmpty) {
                this.rejectionError = 'Isi komentar untuk parameter yang dicentang, atau hapus centangnya';
                return;
            }

            this.rejectionError = '';
            this.loading.reject = true;
            try {
                // Backend parameter mencocokkan kpi_comments BY INDEX urutan detail.
                // Kirim array selaras indeks: komentar di indeks yang dicentang, '' selain itu.
                const total = this.selectedApproval?.kpiDetails?.length || 0;
                const kpiComments = new Array(total).fill('');
                this.rejectionKpiSelection.forEach((selected, index) => {
                    if (selected && this.rejectionComments[index]?.trim()) {
                        kpiComments[index] = this.rejectionComments[index];
                    }
                });

                const payload = {
                    reason: this.rejectionReason,
                    kpi_comments: kpiComments,
                    // no_transaksi dipakai backend untuk routing engine (CENTRAL/LEGACY)
                    // agar tidak salah-route akibat tabrakan numeric id antar tabel.
                    no_transaksi: this.selectedApproval.No_Transaksi,
                };

                await axios.post(
                    `/api/v1/kpi-approval/approvals/${this.selectedApproval.id_Approval_Request}/reject-with-detail`,
                    payload,
                );

                this.showSimpleAlert('Submission rejected successfully', 'success');
                this.closeRejectModal();
                this.closeDetailModal();
                await this.fetchApprovals();
                await this.fetchHistory();
            } catch (error) {
                console.error('Error rejecting submission:', error);
                this.showSimpleAlert('Failed to reject submission', 'error');
            } finally {
                this.loading.reject = false;
            }
        },

        async confirmApprove() {
            this.loading.approve = true;
            try {
                await axios.post(
                    `/api/v1/kpi-approval/approvals/${this.selectedApproval.id_Approval_Request}/approve`,
                    { no_transaksi: this.selectedApproval.No_Transaksi },
                );
                this.showSimpleAlert('Submission approved successfully', 'success');
                this.closeApproveModal();
                this.closeDetailModal();
                await this.fetchApprovals();
                await this.fetchHistory();
            } catch (error) {
                console.error('Error approving submission:', error);
                this.showSimpleAlert('Failed to approve submission', 'error');
            } finally {
                this.loading.approve = false;
            }
        },

        async bulkApprove() {
            this.loading.approve = true;
            try {
                for (const id of this.selectedApprovals) {
                    await axios.post(`/api/v1/kpi-approval/approvals/${id}/approve`, {
                        no_transaksi: this.getApprovalById(id)?.No_Transaksi,
                    });
                }
                this.showSimpleAlert(`${this.selectedApprovals.length} submissions approved successfully`, 'success');
                this.clearSelection();
                await this.fetchApprovals();
            } catch (error) {
                console.error('Error bulk approving:', error);
                this.showSimpleAlert('Failed to approve some submissions', 'error');
            } finally {
                this.loading.approve = false;
            }
        },

        openBulkRejectModal() {
            this.bulkRejectionReason = '';
            this.bulkRejectionError = '';
            this.showBulkRejectModal = true;
        },

        closeBulkRejectModal() {
            this.showBulkRejectModal = false;
            this.bulkRejectionReason = '';
            this.bulkRejectionError = '';
        },

        async confirmBulkReject() {
            if (!this.bulkRejectionReason.trim()) {
                this.bulkRejectionError = 'Rejection reason is required';
                return;
            }

            this.loading.bulkReject = true;
            try {
                for (const id of this.selectedApprovals) {
                    await axios.post(`/api/v1/kpi-approval/approvals/${id}/reject`, {
                        reason: this.bulkRejectionReason,
                        no_transaksi: this.getApprovalById(id)?.No_Transaksi,
                    });
                }
                this.showSimpleAlert(`${this.selectedApprovals.length} submissions rejected successfully`, 'success');
                this.closeBulkRejectModal();
                this.clearSelection();
                await this.fetchApprovals();
            } catch (error) {
                console.error('Error bulk rejecting:', error);
                this.showSimpleAlert('Failed to reject some submissions', 'error');
            } finally {
                this.loading.bulkReject = false;
            }
        },
    },
    async mounted() {
        await this.fetchFilterOptions();
        await this.fetchApprovals();
        await this.fetchHistory();
        window.addEventListener('resize', this.handleResize);
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
        clearTimeout(this.searchTimeout);
    },
};
</script>

<style scoped>
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
    --vs-border-radius: 8px;
    --vs-border-color: var(--hcis-slate-200);
    --vs-border-style: solid;
    --vs-border-width: 1px;
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

.rejection-section-compact {
    margin-top: 1rem;
    padding: 0.875rem;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(239, 68, 68, 0.04) 100%);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 8px;
    border-left: 4px solid var(--danger);
}

.rejection-header-compact {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.rejection-icon {
    font-size: 1rem;
    color: var(--danger);
    flex-shrink: 0;
}

.rejection-title {
    color: var(--danger);
    font-size: 0.875rem;
    font-weight: 600;
    margin: 0;
}

.rejection-reason-compact {
    color: var(--text);
    font-size: 0.875rem;
    line-height: 1.5;
    padding-left: 1.5rem;
}

/* Responsive Design untuk Rejection Section */
@media (max-width: 768px) {
    .rejection-section-compact {
        padding: 0.75rem;
        margin-top: 0.875rem;
    }

    .rejection-header-compact {
        gap: 0.375rem;
    }

    .rejection-reason-compact {
        padding-left: 1.25rem;
        font-size: 0.8125rem;
    }
}

/* Animation untuk rejection section */
.rejection-section-compact {
    animation: slideInRejection 0.3s ease-out;
}

@keyframes slideInRejection {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Hover effect */
.rejection-section-compact:hover {
    border-color: rgba(239, 68, 68, 0.3);
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.1);
    transform: translateY(-1px);
    transition: all 0.2s ease;
}
/* KPI DETAIL - LOADING OVERLAY */
.kpi-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 100;
    border-radius: var(--border-radius);
}

.kpi-loading-spinner {
    text-align: center;
    color: var(--primary);
}

.kpi-spinner-circle {
    width: 48px;
    height: 48px;
    border: 4px solid var(--primary-light);
    border-top: 4px solid var(--primary);
    border-radius: 50%;
    animation: kpi-spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes kpi-spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.kpi-content-blur {
    filter: blur(2px);
    pointer-events: none;
    user-select: none;
}

/* Skeleton Loading */
.kpi-skeleton-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kpi-skeleton-item {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--border-radius);
    padding: 1.25rem;
    animation: kpi-pulse 2s infinite;
}

/* dst… tetap aman karena sudah punya nama unik */
@keyframes kpi-pulse {
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

@media (max-width: 768px) {
    .kpi-loading-spinner {
        padding: 2rem;
    }

    .kpi-spinner-circle {
        width: 40px;
        height: 40px;
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
    background: linear-gradient(135deg, var(--hcis-emerald-500) 0%, var(--hcis-emerald-600) 100%);
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
    color: var(--hcis-emerald-400);
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
    background: linear-gradient(135deg, var(--success), var(--hcis-emerald-400));
}

.summary-icon.status {
    background: linear-gradient(135deg, var(--info), var(--hcis-cyan-500));
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
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
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
    content: '';
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
    background: linear-gradient(135deg, var(--surface) 0%, var(--hcis-slate-50) 100%);
}

.compact-card.completed {
    border-left: 4px solid var(--success);
}

.compact-card.draft {
    border-left: 4px solid var(--warning);
}

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
    background: var(--secondary);
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

/* Detail Modal
.detail-modal-content {
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
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
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
    color: var(--hcis-red-900);
    font-size: 0.9rem;
    line-height: 1.4;
}

.overall-rejection {
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
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
    color: var(--hcis-red-900);
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
/* .pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1.5rem 0;
    border-top: 1px solid var(--border);
    margin-top: 1.5rem;
} */

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
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-radius: 6px;
    padding: 0.75rem;
    font-size: 0.85rem;
    color: var(--hcis-red-900);
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
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-radius: 6px;
    padding: 0.75rem;
    font-size: 0.85rem;
    color: var(--hcis-red-900);
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
</style>

<style scoped>
/* ===== Reject modal (samakan dgn Update Performance) ===== */
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
    color: var(--hcis-red-500);
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
    color: var(--text-primary, var(--hcis-slate-800));
}
.reject-banner-text span {
    color: var(--text-secondary, var(--hcis-slate-500));
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
    color: var(--text-primary, var(--hcis-slate-800));
    margin-bottom: 0.5rem;
}
.reject-req {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--hcis-red-600);
    background: rgba(239, 68, 68, 0.12);
    padding: 2px 7px;
    border-radius: 999px;
}
.reject-textarea {
    width: 100%;
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 10px;
    padding: 0.65rem 0.8rem;
    font-size: 0.88rem;
    line-height: 1.45;
    resize: vertical;
    background: var(--surface, #fff);
    color: var(--text-primary, var(--hcis-slate-800));
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease;
}
.reject-textarea:focus {
    outline: none;
    border-color: var(--primary, var(--hcis-indigo-500));
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.reject-textarea.is-invalid {
    border-color: var(--hcis-red-500);
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
}
.reject-error {
    display: flex;
    align-items: center;
    margin-top: 0.4rem;
    font-size: 0.78rem;
    color: var(--hcis-red-600);
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
    color: var(--text-primary, var(--hcis-slate-800));
}
.reject-params-opt {
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--text-secondary, var(--hcis-slate-500));
    background: var(--surface-subtle, var(--hcis-slate-100));
    padding: 2px 8px;
    border-radius: 999px;
}
.reject-params-hint {
    font-size: 0.8rem;
    color: var(--text-secondary, var(--hcis-slate-500));
    margin: 0 0 0.75rem;
}
.reject-param-list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.reject-param-item {
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 12px;
    background: var(--surface, #fff);
    overflow: hidden;
    transition:
        border-color 0.15s ease,
        background 0.15s ease,
        box-shadow 0.15s ease;
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
    accent-color: var(--hcis-red-500);
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
    color: var(--text-primary, var(--hcis-slate-800));
}
.reject-param-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.reject-chip {
    font-size: 0.72rem;
    font-weight: 500;
    color: var(--text-secondary, var(--hcis-slate-600));
    background: var(--surface-subtle, var(--hcis-slate-100));
    border: 1px solid var(--border, var(--hcis-slate-200));
    padding: 2px 8px;
    border-radius: 999px;
    white-space: nowrap;
}
.reject-param-comment {
    padding: 0 0.9rem 0.85rem;
}
/* ===== Banner alasan penolakan global (detail modal) ===== */
.global-reject-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-left: 4px solid var(--hcis-red-500);
    border-radius: 10px;
    padding: 0.85rem 1rem;
    margin-bottom: 1.25rem;
}
.global-reject-banner > i {
    color: var(--hcis-red-500);
    font-size: 1.15rem;
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
    color: var(--hcis-red-700);
    margin-bottom: 0.2rem;
}
.global-reject-value {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.55;
    color: var(--hcis-red-900);
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: pre-line;
}

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

/* Loading States */
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
    content: '';
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
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
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
    align-items: center;
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

/* Detail Modal Styles */
/* .detail-modal-content {
    max-height: 70vh;
    overflow-y: auto;
} */

.detail-section {
    margin-bottom: 1.5rem;
}

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
    /* font-family: monospace; */
}

/* KPI List Compact */
.section-header-compact {
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.section-title-wrapper {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.25rem;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.section-badge {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

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
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-radius: 8px;
    padding: 1rem;
    color: var(--hcis-red-900);
    line-height: 1.5;
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
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    border-radius: 6px;
    padding: 0.75rem;
    font-size: 0.85rem;
    color: var(--hcis-red-900);
    margin-top: 0.75rem;
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

/* Modal Footer */
.wrapper-button-footer {
    display: flex;
    justify-content: end;
    align-items: center;
    padding: 0;
    gap: 0.5rem;
}

.action-buttons-group {
    display: flex;
    gap: 0.75rem;
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

/* Reject Modal Styles */
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

/* Approve Modal Styles */
.approval-confirmation {
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
    border-bottom: 1px solid var(--border-soft);
    font-size: 0.9rem;
}

.selected-item:last-child {
    border-bottom: none;
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

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

@keyframes bounce {
    0%,
    80%,
    100% {
        transform: scale(0);
    }
    40% {
        transform: scale(1);
    }
}

.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

/* Responsive Design */
@media (max-width: 768px) {
    .header-content {
        padding: 1rem;
    }

    .summary-item {
        margin: 0;
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

    .detail-header-compact {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .requester-info-compact {
        flex-direction: column;
        text-align: center;
        justify-content: center;
        align-items: center;
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

    /* .kpi-meta-compact {
        justify-content: center;
    } */

    .card-grid {
        grid-template-columns: 1fr;
    }

    .bulk-actions-content {
        flex-direction: column;
        align-items: stretch;
    }

    .wrapper-button-footer {
        flex-direction: column;
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
}
</style>
